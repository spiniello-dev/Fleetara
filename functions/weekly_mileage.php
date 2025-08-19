<?php
require_once __DIR__ . '/../vendor/autoload.php';
if (class_exists('Dotenv\\Dotenv')) { $dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__)); $dotenv->safeLoad(); }
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);
define('API_URL', 'https://api.samsara.com/fleet/vehicles/stats?types=obdOdometerMeters');
define('API_TOKEN', $_ENV['SAMSARA_API_KEY'] ?? '');
define('CSV_FILE', __DIR__ . '/pivoted_mileage_report.csv');

// Fetch data from Samsara API
function fetchMileageData() {
    $API_TOKEN = API_TOKEN; // Ensure API token is defined
    $API_URL = API_URL; // Ensure API URL is defined

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $API_URL);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $API_TOKEN",
        "Accept: application/json"
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    $response = curl_exec($ch);

    $vehicles = json_decode($response, true);
    echo '<script>console.log("vehicles: ", ' . json_encode($vehicles) . ');</script>';

    curl_close($ch);

    if (!$vehicles) {
        die("Error fetching data.");
    }

    return $vehicles;
}

// Load existing data
function loadCsvToArray($filename) {
    if (!file_exists($filename)) return [];

    $rows = array_map('str_getcsv', file($filename));
    $header = array_shift($rows);
    $result = [];

    foreach ($rows as $row) {
        $id = $row[0];
        $result[$id] = array_combine($header, $row);
    }

    return [$header, $result];
}

// // Save updated pivot data
function saveCsvFromArray($header, $rows, $filename) {
    $fp = fopen($filename, 'w');
    fputcsv($fp, $header);
    foreach ($rows as $row) {
        $line = [];
        foreach ($header as $col) {
            $line[] = isset($row[$col]) ? $row[$col] : '';
        }
        fputcsv($fp, $line);
    }
    fclose($fp);
}

// // Main logic
$vehicles = fetchMileageData();
echo "<pre>Fetched " . count($vehicles['data']) . " vehicles from Samsara.\n</pre>";
$today = date('Y-m-d');

// Load or initialize
list($header, $data) = loadCsvToArray(CSV_FILE);

// Ensure $header is always an array
if (!is_array($header)) {
    $header = [];
}

// Add 'name' to header if it's missing
if (!in_array('name', $header)) {
    $header[] = 'name';
}

// Add $today to header if it's missing
if (!in_array($today, $header)) {
    $header[] = $today;
}

// // Build rows
foreach ($vehicles['data'] as $vehicle) {
    $name = isset($vehicle['name']) ? (string)$vehicle['name'] : 'UNKNOWN';
    $meters = isset($vehicle['obdOdometerMeters']['value']) ? $vehicle['obdOdometerMeters']['value'] : null;

    if ($meters === null) continue; // Skip vehicles without mileage

    $miles = round($meters / 1609.34, 1); // meters → miles

    // Use vehicle name as key
    if (!isset($data[$name])) {
        $data[$name] = ['name' => $name];
    }

    $data[$name][$today] = $miles;
}

// // Save to CSV
saveCsvFromArray($header, $data, CSV_FILE);

echo "Pivoted mileage report updated.\n";
?>
