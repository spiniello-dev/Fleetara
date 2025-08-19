<?php
// Returns historical fueling metrics for a specific asset as JSON
header('Content-Type: application/json');

$asset = isset($_GET['asset']) ? trim($_GET['asset']) : '';
$assetKey = strtoupper($asset);
$startDate = isset($_GET['startDate']) ? $_GET['startDate'] : null; // YYYY-MM-DD
$endDate   = isset($_GET['endDate']) ? $_GET['endDate'] : null;     // YYYY-MM-DD

if ($asset === '') {
    echo json_encode(['success' => false, 'error' => 'Missing asset parameter']);
    exit;
}

$csvPath = __DIR__ . '/reports/Spiniello Fuel Transactions.csv';
if (!is_file($csvPath)) {
    echo json_encode(['success' => false, 'error' => 'Master CSV not found']);
    exit;
}

// Helper for safe float
function fnum($v) {
    $v = is_string($v) ? trim($v) : $v;
    $n = floatval(str_replace([',','$'], '', (string)$v));
    return is_finite($n) ? $n : 0.0;
}

// Load and index header
$fh = fopen($csvPath, 'r');
if (!$fh) { echo json_encode(['success' => false, 'error' => 'Could not open CSV']); exit; }
$header = fgetcsv($fh);
if (!$header) { fclose($fh); echo json_encode(['success' => false, 'error' => 'CSV header missing']); exit; }
$idx = [];
foreach ($header as $i => $h) { $idx[strtolower(trim($h))] = $i; }

// Expected canonical columns
$iDate    = $idx['trans date'] ?? 1;
$iAsset   = $idx['asset #']    ?? 6;
$iGallons = $idx['gallons']    ?? 22;
$iGross   = $idx['gross dollars'] ?? 23;
$iNet     = $idx['net dollars']   ?? 24;
$iUnit    = $idx['unit cost']  ?? 25;
$iNetUnit = $idx['net unit cost'] ?? 26;
$iProd    = $idx['product']    ?? 27;
$iOdo     = $idx['odometer']   ?? 5;  // optional

// Date filters
$startTs = $startDate ? strtotime($startDate.' 00:00:00') : null;
$endTs   = $endDate   ? strtotime($endDate.' 23:59:59') : null;

$daily = []; // date => { gallons, cost, txns, unitCosts:[], productCounts:{} }
$totals = [
    'gallons' => 0.0,
    'cost' => 0.0,
    'transactions' => 0,
    'miles' => 0.0
];
$firstDate = null; $lastDate = null;

while (($row = fgetcsv($fh)) !== false) {
    if (!is_array($row) || !array_filter($row)) continue;
    $assetVal = isset($row[$iAsset]) ? trim($row[$iAsset]) : '';
    if (strtoupper($assetVal) !== $assetKey) continue;
    $dateStr = isset($row[$iDate]) ? trim($row[$iDate]) : '';
    if ($dateStr === '') continue;
    $ts = strtotime($dateStr);
    if ($ts === false) continue;
    if ($startTs && $ts < $startTs) continue;
    if ($endTs && $ts > $endTs) continue;
    $day = date('Y-m-d', $ts);

    $gal = isset($row[$iGallons]) ? fnum($row[$iGallons]) : 0.0;
    $cost = 0.0;
    if (isset($row[$iGross]) && $row[$iGross] !== '') $cost = fnum($row[$iGross]);
    elseif (isset($row[$iNet]) && $row[$iNet] !== '') $cost = fnum($row[$iNet]);

    if (!isset($daily[$day])) {
        $daily[$day] = [
            'gallons' => 0.0,
            'cost' => 0.0,
            'transactions' => 0,
            'unitCosts' => [],
            'productCounts' => [],
            'odo' => []
        ];
    }
    $daily[$day]['gallons'] += $gal;
    $daily[$day]['cost'] += $cost;
    $daily[$day]['transactions'] += 1;
    if (isset($row[$iUnit]) && $row[$iUnit] !== '') $daily[$day]['unitCosts'][] = fnum($row[$iUnit]);
    if (isset($row[$iProd]) && $row[$iProd] !== '') {
        $prod = trim($row[$iProd]);
        $daily[$day]['productCounts'][$prod] = ($daily[$day]['productCounts'][$prod] ?? 0) + 1;
    }
    if ($iOdo !== null && isset($row[$iOdo]) && $row[$iOdo] !== '') {
        $daily[$day]['odo'][] = fnum($row[$iOdo]);
    }

    $totals['gallons'] += $gal;
    $totals['cost'] += $cost;
    $totals['transactions'] += 1;

    if ($firstDate === null || $day < $firstDate) $firstDate = $day;
    if ($lastDate === null || $day > $lastDate) $lastDate = $day;
}
fclose($fh);

// Load pivoted mileage CSV to derive daily miles per asset (functions/pivoted_mileage_report.csv)
$pivotPath = __DIR__ . '/functions/pivoted_mileage_report.csv';
$odoByDate = [];
if (is_file($pivotPath)) {
    $pfh = fopen($pivotPath, 'r');
    if ($pfh) {
        $pHeader = fgetcsv($pfh);
        if (is_array($pHeader) && count($pHeader) > 1) {
            // Normalize header dates (index => 'YYYY-MM-DD')
            $dateCols = [];
            foreach ($pHeader as $i => $h) {
                $h = trim($h);
                if ($i === 0) continue; // name column
                // Expect header already as YYYY-MM-DD, keep as-is if valid date
                if ($h !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $h)) {
                    $dateCols[$i] = $h;
                }
            }
            // Find the asset row (case-insensitive)
            while (($prow = fgetcsv($pfh)) !== false) {
                if (!is_array($prow) || !array_filter($prow)) continue;
                $name = isset($prow[0]) ? strtoupper(trim($prow[0])) : '';
                if ($name === $assetKey) {
                    foreach ($dateCols as $ci => $dstr) {
                        if (isset($prow[$ci]) && $prow[$ci] !== '' && is_numeric($prow[$ci])) {
                            $odoByDate[$dstr] = floatval($prow[$ci]);
                        }
                    }
                    break;
                }
            }
        }
        fclose($pfh);
    }
}

// Precompute daily miles from the pivoted odometer readings: delta between consecutive odometer dates
$dailyMilesByDate = [];
if (!empty($odoByDate)) {
    ksort($odoByDate); // sort by date string asc
    $prevDate = null; $prevOdo = null;
    foreach ($odoByDate as $d => $odo) {
        if ($prevOdo !== null && $odo >= $prevOdo) {
            $dailyMilesByDate[$d] = $odo - $prevOdo;
        } else {
            $dailyMilesByDate[$d] = 0.0;
        }
        $prevDate = $d; $prevOdo = $odo;
    }
}

// Build series (fill gaps by date). Respect requested start/end if provided.
if ($firstDate === null) {
    // No transactions for this asset in the period; use requested range if available
    if ($startDate && $endDate) {
        $firstDate = $startDate;
        $lastDate = $endDate;
    } else {
        echo json_encode(['success' => true, 'asset' => $asset, 'series' => [], 'summary' => ['gallons'=>0,'cost'=>0,'transactions'=>0,'miles'=>0,'mpg'=>0]]);
        exit;
    }
} else {
    // Transactions exist; if user provided a range, override window to that
    if ($startDate) $firstDate = $startDate;
    if ($endDate) $lastDate = $endDate;
}

$series = [ 'dates' => [], 'gallons' => [], 'cost' => [], 'avgUnitCost' => [], 'miles' => [], 'mpg' => [] ];
$cursor = strtotime($firstDate);
$endCur = strtotime($lastDate);
// For MPG, compute per fill-up: miles accumulated since previous fuel day divided by today's gallons
$milesSinceLastFuel = 0.0;
while ($cursor <= $endCur) {
    $d = date('Y-m-d', $cursor);
    $dayData = $daily[$d] ?? ['gallons'=>0,'cost'=>0,'transactions'=>0,'unitCosts'=>[],'productCounts'=>[], 'odo'=>[]];
    // Prefer miles from pivoted mileage CSV (per date), else fallback to per-day odometer delta from transactions
    $miles = 0.0;
    if (isset($dailyMilesByDate[$d])) {
        $miles = max(0.0, floatval($dailyMilesByDate[$d]));
    } elseif (!empty($dayData['odo'])) {
        $minO = min($dayData['odo']);
        $maxO = max($dayData['odo']);
        $miles = max(0.0, $maxO - $minO);
    }
    // accumulate miles since last fueling day
    $milesSinceLastFuel += $miles;
    $avgUnit = 0.0;
    if (!empty($dayData['unitCosts'])) {
        $avgUnit = array_sum($dayData['unitCosts']) / max(1, count($dayData['unitCosts']));
    }
    // MPG only on fueling days: miles since last fuel / gallons today
    $mpg = null;
    if ($dayData['gallons'] > 0) {
        $mpg = ($milesSinceLastFuel > 0) ? ($milesSinceLastFuel / $dayData['gallons']) : 0.0;
        $milesSinceLastFuel = 0.0; // reset after assigning to fuel day
    }

    $series['dates'][] = $d;
    $series['gallons'][] = round($dayData['gallons'], 3);
    $series['cost'][] = round($dayData['cost'], 2);
    $series['avgUnitCost'][] = round($avgUnit, 3);
    $series['miles'][] = round($miles, 2);
    $series['mpg'][] = is_null($mpg) ? null : round($mpg, 2);
    $cursor = strtotime('+1 day', $cursor);
}

// Compute per-period summary
$days = max(1, (strtotime($lastDate) - strtotime($firstDate)) / 86400 + 1);
$totalMiles = array_sum($series['miles']);
$summary = [
    'gallons' => round($totals['gallons'], 2),
    'cost' => round($totals['cost'], 2),
    'transactions' => $totals['transactions'],
    'avgDailyGallons' => round($totals['gallons'] / $days, 2),
    'avgDailyCost' => round($totals['cost'] / $days, 2),
    'miles' => round($totalMiles, 1),
    'mpg' => ($totals['gallons'] > 0 ? round($totalMiles / $totals['gallons'], 2) : 0)
];

// Product mix (top 5)
$productCounts = [];
foreach ($daily as $d => $info) {
    foreach ($info['productCounts'] as $p => $c) {
        $productCounts[$p] = ($productCounts[$p] ?? 0) + $c;
    }
}
arsort($productCounts);
$topProducts = array_slice($productCounts, 0, 5, true);

echo json_encode([
    'success' => true,
    'asset' => $asset,
    'series' => $series,
    'summary' => $summary,
    'topProducts' => $topProducts,
]);
