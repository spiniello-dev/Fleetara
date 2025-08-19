<?php
require_once __DIR__ . '/vendor/autoload.php';
if (class_exists('Dotenv\\Dotenv')) { $dotenv = Dotenv\Dotenv::createImmutable(__DIR__); $dotenv->safeLoad(); }
// Returns all fuel table and dashboard data as JSON for AJAX loading
header('Content-Type: application/json');
// Optional: enable gzip compression if available
if (function_exists('ob_gzhandler')) { ob_start('ob_gzhandler'); }

$endDate = isset($_GET['endDate']) ? $_GET['endDate'] . 'T00:00:00-04:00' : date('Y-m-d') . 'T00:00:00-04:00';
$startDate = isset($_GET['startDate']) ? $_GET['startDate'] . 'T00:00:00-04:00' : date('Y-m-d',  strtotime('-30 days')) . 'T00:00:00-04:00';
$bypassCache = isset($_GET['bypassCache']) && ($_GET['bypassCache'] === '1' || $_GET['bypassCache'] === 'true');

// Simple file cache (5 minutes)
$cacheDir = __DIR__ . DIRECTORY_SEPARATOR . 'cache';
if (!is_dir($cacheDir)) { @mkdir($cacheDir, 0777, true); }
$cacheKey = 'fuel_' . md5($startDate . '|' . $endDate);
$cacheFile = $cacheDir . DIRECTORY_SEPARATOR . $cacheKey . '.json';
$ttl = 300; // seconds
if (!$bypassCache && is_file($cacheFile) && (time() - filemtime($cacheFile) < $ttl)) {
	$cached = @file_get_contents($cacheFile);
	if ($cached !== false) { echo $cached; exit; }
}

// --- API and CSV aggregation logic ---
$vehicleTypes = [];
$regions = [];
$fleetioVehicles = [];

// Samsara API for vehicle types with pagination
$samsaraUrl = "https://api.samsara.com/fleet/vehicles?limit=512";
$samsaraAPIKey = $_ENV['SAMSARA_API_KEY'] ?? '';
$ch2 = curl_init();
curl_setopt($ch2, CURLOPT_URL, $samsaraUrl);
curl_setopt($ch2, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch2, CURLOPT_HTTPHEADER, [
	"Authorization: Bearer $samsaraAPIKey",
	"Accept: application/json"
]);
curl_setopt($ch2, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch2, CURLOPT_SSL_VERIFYHOST, false);
$response2 = curl_exec($ch2);
$samsaraVehicles = [];
if (!curl_errno($ch2)) {
	$samsaraVehicles = json_decode($response2, true);
	if (isset($samsaraVehicles['data'])) {
		foreach ($samsaraVehicles['data'] as $vehicle) {
			if (isset($vehicle['vehicleType'])) {
				$vehicleTypes = array_merge($vehicleTypes, $vehicle['vehicleType']);
			}
		}
	}
}
curl_close($ch2);

// Fleetio API for regions with pagination
$fleetioUrl = "https://secure.fleetio.com/api/v1/vehicles?per_page=100";
$fleetioApiKey = $_ENV['FLEETIO_API_KEY'] ?? '';
$hasNextPage = false;
$nextCursor = null;
$fleetioVehicles = [];
do {
	$url = $fleetioUrl . ($hasNextPage && $nextCursor ? "&start_cursor=$nextCursor" : "");
	$ch3 = curl_init();
	curl_setopt($ch3, CURLOPT_URL, $url);
	curl_setopt($ch3, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch3, CURLOPT_HTTPHEADER, [
		"Authorization: Token $fleetioApiKey",
		"Account-Token: " . ($_ENV['FLEETIO_ACCOUNT_TOKEN'] ?? ''),
		"X-Api-Version: 2024-06-30",
		"Accept: application/json"
	]);
	curl_setopt($ch3, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($ch3, CURLOPT_SSL_VERIFYHOST, false);
	$response3 = curl_exec($ch3);
	if (!curl_errno($ch3)) {
		$fleetioVehiclesPage = json_decode($response3, true);
		if (isset($fleetioVehiclesPage['records'])) {
			$fleetioVehicles = array_merge($fleetioVehicles, $fleetioVehiclesPage['records']);
		}
		$nextCursor = $fleetioVehiclesPage['next_cursor'];
		$hasNextPage = !empty($nextCursor);
	}
	curl_close($ch3);
} while ($hasNextPage);

$vehicleTypes = array_keys($vehicleTypes);
sort($vehicleTypes);
$regions = array_keys($regions);
sort($regions);

// --- Fuel transactions ---
function getFuelTransactions($startDate, $endDate) {
	$transactions = [];
	$file = fopen('reports/Spiniello Fuel Transactions.csv', 'r');
	$startTimestamp = strtotime($startDate);
	$endTimestamp = strtotime($endDate);
	if ($file) {
		fgetcsv($file);
		while (($row = fgetcsv($file)) !== false) {
			if (empty($row[1]) && empty($row[6]) && empty($row[22])) continue;
			$transDate = !empty($row[1]) ? strtotime($row[1]) : false;
			if (!$transDate) continue;
			if ($transDate >= $startTimestamp && $transDate <= $endTimestamp) {
				$assetId = trim($row[6]);
				if ($assetId === 'RENTAL' || empty($assetId)) continue;
				if (!isset($transactions[$assetId])) {
					$transactions[$assetId] = [
						'totalGallons' => 0,
						'totalCost' => 0
					];
				}
				$cost = 0;
				if (!empty($row[23])) {
					$cost = floatval(trim($row[23]));
				} elseif (!empty($row[24])) {
					$cost = floatval(trim($row[24]));
				}
				$gallons = !empty($row[22]) ? floatval(trim($row[22])) : 0;
				if ($gallons > 0 || $cost > 0) {
					$transactions[$assetId]['totalGallons'] += $gallons;
					$transactions[$assetId]['totalCost'] += $cost;
				}
			}
		}
		fclose($file);
	}
	return $transactions;
}
$cleanStartDate = substr($startDate, 0, 10);
$cleanEndDate = substr($endDate, 0, 10);
$fuelTransactions = getFuelTransactions($cleanStartDate, $cleanEndDate);

// --- Samsara API for fuel/energy report ---
$apiKey = $_ENV['SAMSARA_API_KEY'] ?? '';
$baseUrl = "https://api.samsara.com/fleet/reports/vehicles/fuel-energy?startDate=$startDate&endDate=$endDate";
$assets = [];
$hasNextPage = true;
$nextCursor = '';

function fetchSamsaraIdlingEvents($startDate, $endDate, $apiKey) {
	$base = 'https://api.samsara.com/idling/events';
	$queryBase = $base . '?excludeEventsWithUnknownAirTemperature=false&limit=200'
				. '&startTime=' . urlencode($startDate)
				. '&endTime=' . urlencode($endDate);
	$after = '';
	$idleMlByAssetId = [];
	$seenEventUuids = [];
	$loopGuard = 0;
	while (true) {
		if ($loopGuard++ > 50) break;
		$url = $queryBase . ($after ? '&after=' . urlencode($after) : '');
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_HTTPHEADER, [
			'Authorization: Bearer ' . $apiKey,
			'Accept: application/json'
		]);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
		$resp = curl_exec($ch);
		if (curl_errno($ch)) { curl_close($ch); break; }
		$data = json_decode($resp, true);
		curl_close($ch);
		if (!$data || !isset($data['data']) || !is_array($data['data'])) break;
		foreach ($data['data'] as $event) {
			if (!isset($event['assetId'])) continue;
			$assetId = (string)$event['assetId'];
			if (isset($event['eventUuid'])) {
				if (isset($seenEventUuids[$event['eventUuid']])) continue;
				$seenEventUuids[$event['eventUuid']] = true;
			}
			$idleMl = 0.0;
			if (isset($event['fuelConsumedMilliliters'])) {
				$idleMl = (float)$event['fuelConsumedMilliliters'];
			} elseif (isset($event['fuelConsumedDuringIdleMl'])) {
				$idleMl = (float)$event['fuelConsumedDuringIdleMl'];
			} elseif (isset($event['idleFuelConsumedMl'])) {
				$idleMl = (float)$event['idleFuelConsumedMl'];
			} elseif (isset($event['fuelConsumedMl'])) {
				$idleMl = (float)$event['fuelConsumedMl'];
			}
			if ($idleMl <= 0) continue;
			if (!isset($idleMlByAssetId[$assetId])) $idleMlByAssetId[$assetId] = 0.0;
			$idleMlByAssetId[$assetId] += $idleMl;
		}
		$hasNext = $data['pagination']['hasNextPage'] ?? false;
		$after = $data['pagination']['endCursor'] ?? '';
		if (!$hasNext || !$after) break;
	}
	$out = [];
	$ML_PER_GALLON = 3785.411784;
	foreach ($idleMlByAssetId as $id => $ml) {
		$out[$id] = $ml / $ML_PER_GALLON;
	}
	return $out;
}
$idleFuelByVehicleId = fetchSamsaraIdlingEvents($startDate, $endDate, $apiKey);

do {
	$url = $baseUrl . ($nextCursor ? "&after=$nextCursor" : "");
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_HTTPHEADER, [
		"Authorization: Bearer $apiKey",
		"Accept: application/json"
	]);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
	$response = curl_exec($ch);
	if (!curl_errno($ch)) {
		$data = json_decode($response, true);
		if (isset($data['data']['vehicleReports'])) {
			$assets = array_merge($assets, $data['data']['vehicleReports']);
		}
		$hasNextPage = $data['pagination']['hasNextPage'] ?? false;
		$nextCursor = $data['pagination']['endCursor'] ?? '';
	} else {
		$hasNextPage = false;
	}
	curl_close($ch);
} while ($hasNextPage && $nextCursor);

// --- Table rows and dashboard totals ---
function varianceClass($pct) {
	if ($pct < 10) return 'variance-green';
	if ($pct < 35) return 'variance-yellow';
	return 'variance-red';
}

$tableRows = [];
$dashboardTotals = [
	'totalCost' => 0,
	'totalMiles' => 0,
	'overallCost' => 0,
	'overallMiles' => 0
];

if (isset($assets)) {
	foreach ($assets as $index => $asset) {
		$row = [];
		$assetName = $asset['vehicle']['name'];
		// Fleetio ID lookup
		$fleetioId = '';
		if (!empty($fleetioVehicles)) {
			foreach ($fleetioVehicles as $vehicle) {
				if (isset($vehicle['name']) && $vehicle['name'] === $assetName) {
					$fleetioId = $vehicle['id'];
					break;
				}
			}
		}
		$ML_PER_GALLON = 3785.411784;
		$fuelUsedGal = (float)$asset['fuelConsumedMl'] / $ML_PER_GALLON;
		$distanceMi = (float)$asset['distanceTraveledMeters'] / 1609.34;
		$effMpge = (float)$asset['efficiencyMpge'];
		$row[] = $assetName;
		$row[] = number_format($effMpge, 2) . ' MPG';
		$row[] = number_format($fuelUsedGal, 2) . ' gal';
		$actualData = isset($fuelTransactions[$assetName]) ? $fuelTransactions[$assetName] : null;
		$actualGallons = $actualData ? (float)$actualData['totalGallons'] : null;
		$potentialMiles = ($actualData && $effMpge > 0) ? $actualGallons * $effMpge : 0;
		$row[] = $actualData ? number_format($actualGallons, 2) . ' gal' : '-';
		// Fuel variance
		if ($fuelUsedGal > 0 && $actualGallons !== null) {
			$fuelVarPct = abs($actualGallons - $fuelUsedGal) / $fuelUsedGal * 100.0;
			$fvClass = varianceClass($fuelVarPct);
			$row[] = [number_format($fuelVarPct, 1) . '%', $fvClass];
		} else {
			$row[] = ['-', ''];
		}
		$row[] = number_format($distanceMi, 2) . ' mi';
		$row[] = $potentialMiles > 0 ? number_format($potentialMiles, 2) . ' mi' : '-';
		// Mileage variance
		if ($distanceMi > 0 && $potentialMiles > 0) {
			$mileVarPct = abs($potentialMiles - $distanceMi) / $distanceMi * 100.0;
			$mvClass = varianceClass($mileVarPct);
			$row[] = [number_format($mileVarPct, 1) . '%', $mvClass];
		} else {
			$row[] = ['-', ''];
		}
		$row[] = '$' . number_format((float)$asset['estFuelEnergyCost']['amount'], 2);
		$row[] = $actualData ? ('$' . number_format($actualData['totalCost'], 2)) : '-';
		$vehIdForIdle = (string)$asset['vehicle']['id'];
		$idleFuelGal = isset($idleFuelByVehicleId[$vehIdForIdle]) ? $idleFuelByVehicleId[$vehIdForIdle] : 0;
		$row[] = $idleFuelGal > 0 ? number_format($idleFuelGal, 2) . ' gal' : '-';
		// Links
		$fleetioLink = $fleetioId ? 'https://secure.fleetio.com/b8f9977137/vehicles/' . $fleetioId : '';
		$samsaraLink = 'https://cloud.samsara.com/o/4461/devices/' . urlencode($asset['vehicle']['id']) . '/vehicle';
		$row[] = [
			'fleetio' => $fleetioLink,
			'samsara' => $samsaraLink
		];
		// Hidden type/region
		$sv = '';
		foreach ($samsaraVehicles['data'] as $samsaraVehicle) {
			if ($samsaraVehicle['id'] === $asset['vehicle']['id']) {
				$sv = $samsaraVehicle;
				break;
			}
		}
		$typeText = '';
		if (
			isset($sv['attributes'][0]['stringValues'])
			&& is_array($sv['attributes'][0]['stringValues'])
			&& isset($sv['attributes'][0]['stringValues'][0])
		) {
			$typeText = $sv['attributes'][0]['stringValues'][0];
		}
		$row[] = $typeText;
		$regionText = '';
		foreach ($fleetioVehicles as $fv) {
			if ($fv['id'] === $fleetioId) {
				$regionText = $fv['group_name'];
				break;
			}
		}
		$row[] = $regionText;

		// Dashboard totals
		$estCost = (float)$asset['estFuelEnergyCost']['amount'];
		$dashboardTotals['totalCost'] += $estCost;
		$dashboardTotals['totalMiles'] += $distanceMi;
		if ($actualData) {
			$dashboardTotals['overallCost'] += (float)$actualData['totalCost'];
		}
		if ($potentialMiles > 0) {
			$dashboardTotals['overallMiles'] += $potentialMiles;
		}

		$tableRows[] = $row;
	}
}

// Vehicle types and regions for filters (indices: type=12, region=13)
$filterTypes = [];
$filterRegions = [];
foreach ($tableRows as $row) {
	if (!empty($row[12])) $filterTypes[$row[12]] = true;
	if (!empty($row[13])) $filterRegions[$row[13]] = true;
}
$filterTypes = array_keys($filterTypes);
sort($filterTypes);
$filterRegions = array_keys($filterRegions);
sort($filterRegions);

$payload = [
	'success' => true,
	'tableRows' => $tableRows,
	'dashboardTotals' => $dashboardTotals,
	'vehicleTypes' => $filterTypes,
	'regions' => $filterRegions
];
$json = json_encode($payload);
// Write to cache (best-effort)
@file_put_contents($cacheFile, $json);
echo $json;
