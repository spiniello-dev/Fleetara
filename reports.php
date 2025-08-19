<?php
require_once __DIR__ . '/vendor/autoload.php';
if (class_exists('Dotenv\\Dotenv')) { $dotenv = Dotenv\Dotenv::createImmutable(__DIR__); $dotenv->safeLoad(); }
session_start();
$currentDate = isset($_GET['date']) ? $_GET['date'] . 'T00:00:00-04:00' : date('Y-m-d') . 'T00:00:00-04:00';
$nextDate = date('Y-m-d', strtotime($currentDate . ' +1 day')) . 'T00:00:00-04:00';

if (!isset($_SESSION['USER_ID'])) {
    header("Location: login.php");
    exit(); // Important: Prevent further execution
}

if (isset($_GET['Logout']) && $_GET['Logout'] == 1) {
    // Destroy session and clear session variables
    session_unset();
    session_destroy();

    // Redirect to login page or home page
    header("Location: login.php");
    exit();
}

function getCurrentDrivers($vehicleIDs, $startMs, $endMs) {
    $apiKey = $_ENV['SAMSARA_API_KEY'] ?? '';
    $multiCurl = curl_multi_init();
    $curlHandles = [];
    $responses = [];

    // Initialize multiple cURL handles
    foreach ($vehicleIDs as $vehicleID) {
        $url = "https://api.samsara.com/v1/fleet/trips?vehicleId=$vehicleID&startMs=$startMs&endMs=$endMs";
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer $apiKey",
            "Accept: application/json"
        ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        curl_multi_add_handle($multiCurl, $ch);
        $curlHandles[$vehicleID] = $ch;
    }

    // Execute all requests in parallel
    do {
        $status = curl_multi_exec($multiCurl, $active);
        curl_multi_select($multiCurl);
    } while ($active && $status == CURLM_OK);

    // Collect responses
    foreach ($curlHandles as $vehicleID => $ch) {
        $responses[$vehicleID] = json_decode(curl_multi_getcontent($ch), true);
        curl_multi_remove_handle($multiCurl, $ch);
        curl_close($ch);
    }
    curl_multi_close($multiCurl);

    // Process responses and extract driver IDs
    $drivers = [];
    foreach ($responses as $vehicleID => $data) {
        if (
            isset($data['trips']) &&
            is_array($data['trips']) &&
            isset($data['trips'][0]) &&
            isset($data['trips'][0]['driverId'])
        ) {
            $driverId = $data['trips'][0]['driverId'];
            if ($driverId) {
                $drivers[$vehicleID] = $driverId;
            }
        }
    }

    return $drivers;
}

function getDriverNames($driverIDs) {
    if (empty($driverIDs)) return [];

    $apiKey = $_ENV['SAMSARA_API_KEY'] ?? '';
    $multiCurl = curl_multi_init();
    $curlHandles = [];
    $responses = [];

    foreach ($driverIDs as $driverID) {
        $url = "https://api.samsara.com/fleet/drivers/$driverID";
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer $apiKey",
            "Accept: application/json"
        ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        curl_multi_add_handle($multiCurl, $ch);
        $curlHandles[$driverID] = $ch;
    }

    do {
        $status = curl_multi_exec($multiCurl, $active);
        curl_multi_select($multiCurl);
    } while ($active && $status == CURLM_OK);

    foreach ($curlHandles as $driverID => $ch) {
        $responses[$driverID] = json_decode(curl_multi_getcontent($ch), true);
        curl_multi_remove_handle($multiCurl, $ch);
        curl_close($ch);
    }
    curl_multi_close($multiCurl);

    // Extract driver names
    $driverNames = [];
foreach ($responses as $driverID => $data) {
    if (isset($data['data']['name'])) {
        $driverNames[$driverID] = $data['data']['name'];
    } else {
        $driverNames[$driverID] = 'Unknown';
    }
}

    return $driverNames;
}


function getTodayTimestamps($currentDate, $timezone = "America/New_York") {
    $tz = new DateTimeZone($timezone);
    
    // Extract the date part
    $dateOnly = substr($currentDate, 0, 10);
    echo '<script>console.log("Date: ", ' . json_encode($dateOnly) . ');</script>';
    
    // Get the start of the given date
    $startOfDay = new DateTime($dateOnly, $tz);
    $startOfDay->setTime(0, 0, 0);
    $startMs = $startOfDay->getTimestamp() * 1000; // Convert to milliseconds
    
    // Get the end of the given date (23:59:59.999)
    $endOfDay = new DateTime($dateOnly, $tz);
    $endOfDay->setTime(23, 59, 59);
    $endOfDay->modify('+999 milliseconds'); // Add 999 milliseconds to make it 23:59:59.999
    $endMs = $endOfDay->getTimestamp() * 1000;

    echo '<script>console.log("startMs: ", ' . json_encode($startMs) . ');</script>';
    echo '<script>console.log("endMs: ", ' . json_encode($endMs) . ');</script>';


    return [
        'date' => $startOfDay->format("Y-m-d"),
        'startMs' => $startMs,
        'endMs' => $endMs
    ];
}

include ("./functions/login_messages.php");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="assets/favicon.png" type="image/x-icon">
    <title>Fleetara</title>
    <script>
        function updateDate() {
            const selectedDate = document.getElementById('datePicker').value;
            window.location.href = '?date=' + selectedDate;
        }
    </script>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" />
    <style>
        /* wrap the input + icon */
        .search-wrapper {
        position: relative;
        }

        /* the magnifier, positioned over the input */
        .search-wrapper .search-icon {
        position: absolute;
        top: 50%;
        left: 0.75rem;
        transform: translateY(-50%);
        pointer-events: none;        /* clicks go to the input */
        color: #6c757d;              /* match .form-control-sm text-muted */
        font-size: 1rem;             /* same size as the input content */
        }

        /* hide the placeholder text itself */
        .search-wrapper input::placeholder {
        color: transparent;
        }

        /* NEW: when focused or not empty, remove extra padding */
        .search-wrapper input:focus,
        .search-wrapper input:not(:placeholder-shown) {
        padding-left: 0.5rem;  /* back to something like the default .form-control-sm */
        }

        /* as soon as the user types (i.e. NOT placeholder-shown), hide the icon */
        .search-wrapper input:focus + .search-icon,
        .search-wrapper input:not(:placeholder-shown) + .search-icon {
        display: none;
        }
    </style>
</head>
<body>
    <header>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top w-100 p-0">
            <div class="container-fluid">
                <a class="navbar-brand" style="font-weight: bold" href="index.php">
                    <img style="width: 80px;"src="assets/logo.png" alt="Fleetara Logo"/>    
                    Fleetara
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav ms-auto" style="font-weight: bold">
                        <li class="nav-item">
                        <a class="nav-link" href="index.php">Home</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="reports.php">Reports</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="trailers.php">Trailers</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="rentals.php">Rentals</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="assets.php">Assets</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="fuel.php">Fuel</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="dvirs.php">DVIRS</a>
                        </li>
                        <?php if (($_SESSION['USR_ROLE'] ?? '') === 'admin'): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="admin.php">Admin</a>
                        </li>
                        <?php endif; ?>
                        <li class="nav-item">
                            <a class="nav-link text-danger" href="index.php?Logout=1">Logout</a>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>
    </header>
    <style>header .navbar{z-index:1050} body{margin:0} @media (max-width:991.98px){.navbar-collapse{background:#212529}} @media (max-width:575.98px){header .navbar .navbar-brand img{width:56px;height:auto}}</style>
    <main class="container d-flex justify-content-center align-items-center min-vh-100">
        <div class="card p-4 shadow-lg" style="width: 100%;">
            <h2 class="text-center mb-4">Fleetio/Samsara Asset Information</h2>
            <div class="row justify-content-between mb-4">
                <h3 class="text-center align-self-center col-auto">Updated for: <?= date('F j, Y g:i A', strtotime($currentDate)) ?></h3>
                <div class="d-flex col-auto">
                    <div class="col-auto mx-2">
                        <label class="form-label d-flex justify-content-center" for="datePicker">Select a Date:</label>
                        <div>
                        <input type="date" id="datePicker" value="<?php echo date('Y-m-d', strtotime($currentDate)); ?>">
                        <button onclick="updateDate()">Update</button>
                        </div>
                        
                    </div>  
                    <?php
                    // build a sorted list of unique vehicle types
                    $types = [];
                    if (!empty($assets['data'])) {
                        foreach ($assets['data'] as $asset) {
                            if (
                                isset($asset['attributes'][0]['stringValues'])
                                && is_array($asset['attributes'][0]['stringValues'])
                                && isset($asset['attributes'][0]['stringValues'][0])
                            ) {
                                $val = $asset['attributes'][0]['stringValues'][0];
                                $types[$val] = true;
                            }
                        }
                    }
                    $types = array_keys($types);
                    sort($types);
                    ?>
                    <div class="col-auto mx-2">
                        <label for="vehicleTypeFilter" class="form-label d-flex justify-content-center">Vehicle Type:</label>
                        <select id="vehicleTypeFilter" class="form-select form-select-sm">
                            <option value="">All Types</option>
                            <?php foreach ($types as $t): ?>
                            <option value="<?= htmlspecialchars($t) ?>"><?= htmlspecialchars($t) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-auto mx-2">
                        <label for="regionFilter" class="form-label d-flex justify-content-center">Region:</label>
                        <select id="regionFilter" class="form-select form-select-sm">
                            <option value="">All Regions</option>
                            <!-- options will be populated by JS -->
                        </select>
                    </div>
                    <div class="col-auto mx-2">
                        <label for="dvirRequiredFilter" class="form-label d-flex justify-content-center">DVIR Required:</label>
                        <select id="dvirRequiredFilter" class="form-select form-select-sm">
                            <option value="">All</option>
                            <option value="Yes">Yes</option>
                            <option value="No">No</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="table-responsive">
            <table id="vehiclesTable" class="table table-striped">
                <thead class="sticky-top bg-white">
                    <!-- your existing header row -->
                    <tr>
                        <th scope="col">Asset #</th>
                        <th scope="col">Assigned Driver</th>
                        <th scope="col">Current Driver</th>
                        <th scope="col">Make/Model</th>
                        <th scope="col">DVIR Submitter</th>
                        <th scope="col">Submitted At (EST)</th>
                        <th scope="col">Engine Start</th>
                        <th scope="col">Engine Stop</th>
                        <th scope="col">Vehicle Type</th>
                        <th scope="col">Region</th>
                        <th scope="col">Fleetio</th>
                        <th scope="col">Samsara</th>
                    </tr>

                    <!-- new “search row” -->
                    <tr>
                        <th>
                            <div class="search-wrapper">
                            <input
                                type="text"
                                class="form-control form-control-sm"
                                placeholder="Search Asset #"
                                data-col="0" />
                            <i class="bi bi-search search-icon"></i>
                            </div>
                        </th>
                        <th>
                            <div class="search-wrapper">
                            <input
                                type="text"
                                class="form-control form-control-sm"
                                placeholder="Search Assigned Driver"
                                data-col="1" />
                            <i class="bi bi-search search-icon"></i>
                            </div>
                        </th>
                        <th>
                            <div class="search-wrapper">
                            <input
                                type="text"
                                class="form-control form-control-sm"
                                placeholder="Search Current Driver"
                                data-col="2" />
                            <i class="bi bi-search search-icon"></i>
                            </div>
                        </th>
                        <th>
                            <div class="search-wrapper">
                            <input
                                type="text"
                                class="form-control form-control-sm"
                                placeholder="Search Make/Model"
                                data-col="3" />
                            <i class="bi bi-search search-icon"></i>
                            </div>
                        </th>
                        <th>
                            <div class="search-wrapper">
                            <input
                                type="text"
                                class="form-control form-control-sm"
                                placeholder="Search DVIR Submitter"
                                data-col="4" />
                            <i class="bi bi-search search-icon"></i>
                            </div>
                        </th>
                        <th>
                            <div class="search-wrapper">
                            <input
                                type="text"
                                class="form-control form-control-sm"
                                placeholder="Search Submitted At"
                                data-col="5" />
                            <i class="bi bi-search search-icon"></i>
                            </div>
                        </th>
                        <th>
                            <div class="search-wrapper">
                            <input
                                type="text"
                                class="form-control form-control-sm"
                                placeholder="Search Engine Start"
                                data-col="6" />
                            <i class="bi bi-search search-icon"></i>
                            </div>
                        </th>
                        <th>
                            <div class="search-wrapper">
                            <input
                                type="text"
                                class="form-control form-control-sm"
                                placeholder="Search Engine Stop"
                                data-col="7" />
                            <i class="bi bi-search search-icon"></i>
                            </div>
                        </th>

                        <th>
                            <div class="search-wrapper">
                            <input
                                type="text"
                                class="form-control form-control-sm"
                                placeholder="Search Vehicle Type"
                                data-col="8" />
                            <i class="bi bi-search search-icon"></i>
                            </div>
                        </th>

                        <th>
                            <div class="search-wrapper">
                            <input
                                type="text"
                                class="form-control form-control-sm"
                                placeholder="Search Region"
                                data-col="9" />
                            <i class="bi bi-search search-icon"></i>
                            </div>
                        </th>

                        <th>
                            <div class="input-group input-group-sm">
                            </div>
                        </th>

                        <th>
                            <div class="input-group input-group-sm">                           
                            </div>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $apiKey = $_ENV['SAMSARA_API_KEY'] ?? '';
                    $apiKey2 = $_ENV['FLEETIO_API_KEY'] ?? '';
                    $url1 = 'https://api.samsara.com/fleet/vehicles?limit=512'; 

                    $baseUrl = "https://secure.fleetio.com/api/v1/submitted_inspection_forms";
                    $baseUrl2 = "https://secure.fleetio.com/api/v1/vehicles?per_page=100";
                    $fleetioVehicles = [];
                    $allRecords = [];
                    $nextCursor = null;

                    $todayTimestamps = getTodayTimestamps($currentDate);

                    do {
                        $url3 = "$baseUrl?filter[inspection_form_id][eq]=189480&filter[submitted_at][gte]=$currentDate&filter[submitted_at][lte]=$nextDate&limit=50";
                        if ($nextCursor) {
                            $url3 .= "&start_cursor=$nextCursor"; // Add pagination cursor if available
                        }

                        // Initialize cURL
                        $ch3 = curl_init();
                        curl_setopt($ch3, CURLOPT_URL, $url3);
                        curl_setopt($ch3, CURLOPT_RETURNTRANSFER, 1);
                        curl_setopt($ch3, CURLOPT_HTTPHEADER, [
                            "Authorization: Token $apiKey2",
                            "Account-Token: " . ($_ENV['FLEETIO_ACCOUNT_TOKEN'] ?? ''),
                            "X-Api-Version: 2024-06-30",
                            "Accept: application/json"
                        ]);
                        curl_setopt($ch3, CURLOPT_SSL_VERIFYPEER, false);
                        curl_setopt($ch3, CURLOPT_SSL_VERIFYHOST, false);

                        $response3 = curl_exec($ch3);
                        curl_close($ch3);

                        // Decode JSON response
                        $records = json_decode($response3, true);

                        // Merge fetched records
                        if (isset($records['records'])) {
                            $allRecords = array_merge($allRecords, $records['records']);
                        }

                        // Check for pagination cursor
                        $nextCursor = $records['next_cursor'];
                        $hasNextPage = !empty($nextCursor); // If there's a next_cursor, continue

                    } while ($hasNextPage);

                    do {
                        $url6 = "$baseUrl2";
                        if ($nextCursor) {
                            $url6 .= "&start_cursor=$nextCursor"; // Add pagination cursor if available
                        }

                        // Initialize cURL
                        $ch6 = curl_init();
                        curl_setopt($ch6, CURLOPT_URL, $url6);
                        curl_setopt($ch6, CURLOPT_RETURNTRANSFER, 1);
                        curl_setopt($ch6, CURLOPT_HTTPHEADER, [
                            "Authorization: Token $apiKey2",
                            "Account-Token: " . ($_ENV['FLEETIO_ACCOUNT_TOKEN'] ?? ''),
                            "X-Api-Version: 2024-06-30",
                            "Accept: application/json"
                        ]);
                        curl_setopt($ch6, CURLOPT_SSL_VERIFYPEER, false);
                        curl_setopt($ch6, CURLOPT_SSL_VERIFYHOST, false);

                        $response6 = curl_exec($ch6);
                        curl_close($ch6);

                        // Decode JSON response
                        $vehicles = json_decode($response6, true);

                        // Merge fetched records
                        if (isset($vehicles['records'])) {
                            $fleetioVehicles = array_merge($fleetioVehicles, $vehicles['records']);
                        }

                        // Check for pagination cursor
                        $nextCursor = isset($vehicles['next_cursor']) ? $vehicles['next_cursor'] : null;
                        $hasNextPage = !empty($nextCursor); // If there's a next_cursor, continue

                    } while ($hasNextPage);

                    // Initialize cURL handles
                    $ch1 = curl_init();          

                    // Set options for the first request
                    curl_setopt($ch1, CURLOPT_URL, $url1);
                    curl_setopt($ch1, CURLOPT_RETURNTRANSFER, 1);
                    curl_setopt($ch1, CURLOPT_HTTPHEADER, [
                        "Authorization: Bearer $apiKey",
                        "Accept: application/json"
                    ]);
                    curl_setopt($ch1, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($ch1, CURLOPT_SSL_VERIFYHOST, false);

                    $response1 = curl_exec($ch1);
                    
                    // Check for errors on each request
                    if (curl_errno($ch1)) {
                        echo 'cURL error on first request: ' . curl_error($ch1);
                    } else {
                        $assets = json_decode($response1, true);
                    }

                    // Close individual handles
                    curl_close($ch1);

                    if (isset($assets['data'])) {
                        $idList = [];
                    
                        foreach ($assets['data'] as $asset) {
                            $idList[] = $asset['id'];
                        }
                    
                        $commaSeparatedList = implode(',', $idList);
                    }

                    $url2 = "https://api.samsara.com/fleet/vehicles/stats/history?startTime=$currentDate&endTime=$nextDate&vehicleIds=$commaSeparatedList&types=engineStates&decorations=&";
                    $allInfo = [];  // Array to store all results
                    $startAfter = null; // Pagination token

                    do {
                        $ch2 = curl_init();
                        curl_setopt($ch2, CURLOPT_URL, $url2);
                        curl_setopt($ch2, CURLOPT_RETURNTRANSFER, 1);
                        curl_setopt($ch2, CURLOPT_HTTPHEADER, [
                            "Authorization: Bearer $apiKey",
                            "Accept: application/json"
                        ]);
                        curl_setopt($ch2, CURLOPT_SSL_VERIFYPEER, false);
                        curl_setopt($ch2, CURLOPT_SSL_VERIFYHOST, false);
                    
                        $response2 = curl_exec($ch2);
                    
                        if (curl_errno($ch2)) {
                            echo 'cURL error on second request: ' . curl_error($ch2);
                        } else {
                            $info = json_decode($response2, true);
                    
                            // Add current page's data to the allData array
                            $allInfo = array_merge($allInfo, $info['data']); // Assuming the records are under 'data'
                            
                            // Get the next cursor if it exists
                            $url2 = isset($info['pagination']['endCursor']) ? 
                                "https://api.samsara.com/fleet/vehicles/stats/history?startTime=$currentDate&endTime=$nextDate&vehicleIds=$commaSeparatedList&types=engineStates&decorations=&after=" . $info['pagination']['endCursor'] : '';
                        }
                    
                        curl_close($ch2);
                    
                    } while (isset($info['pagination']['hasNextPage']) && $info['pagination']['hasNextPage']); // Keep going as long as there is a next page

                    // Now, $allRecords contains all results


                    echo '<script>console.log("Assets: ", ' . json_encode($assets) . ');</script>';
                    echo '<script>console.log("Info: ", ' . json_encode($allInfo) . ');</script>';
                    echo '<script>console.log("Records: ", ' . json_encode($allRecords) . ');</script>';
                    echo '<script>console.log("Vehicles: ", ' . json_encode($fleetioVehicles) . ');</script>';

                    // Ensure $assets['data'] is a valid array before using array_map
                    $vehicleIDs = [];
                    if (!empty($assets['data']) && is_array($assets['data'])) {
                        foreach ($assets['data'] as $asset) {
                            if (isset($asset['id'])) {
                                $vehicleIDs[] = $asset['id'];
                            }
                        }
                    } else {
                        error_log("⚠️ Warning: \$assets['data'] is not an array or is empty.");
                    }

                    $driverIDs = getCurrentDrivers($vehicleIDs, $todayTimestamps['startMs'], $todayTimestamps['endMs']);
                    $driverNames = getDriverNames(array_values($driverIDs));

                    echo '<script>console.log("Vehicle: ", ' . json_encode($vehicleIDs) . ');</script>';
            
                    if (isset($assets['data'])) {
                        foreach ($assets['data'] as $index => $asset) {
                            $vehicleID = $asset['id'];
                            if (!isset($driverIDs[$vehicleID])) {
                                $currentDriver = "Unable to pull";
                            } elseif ($driverIDs[$vehicleID] == -1) {
                                $currentDriver = "Unable to pull";
                            } elseif (isset($driverNames[$driverIDs[$vehicleID]])) {
                                $currentDriver = $driverNames[$driverIDs[$vehicleID]];
                            } else {
                                $currentDriver = "Unable to pull";
                            }
                            
                            // echo '<script>console.log("Vehicle: ", ' . json_encode($driverIDs) . ');</script>';
                            // echo '<script>console.log("Vehicle: ", ' . json_encode($driverIDs[$vehicleID]) . ', ' . json_encode($asset['name']) . ');</script>';
                            // echo '<script>console.log("Driver: ", ' . json_encode($driverNames[$driverIDs[$vehicleID]]) . ');</script>';
                            $engineState = 'N/A';
                            $engineStateOff = 'N/A';
                            if (isset($allInfo)) {
                                foreach ($allInfo as $state) {
                                    if ($state['name'] === $asset['name']) {
                                        foreach ($state['engineStates'] as $engineStateEntry) {
                                            // Ensure 'time' and 'value' exist in the entry
                                            if (isset($engineStateEntry['time'], $engineStateEntry['value'])) {
                                                // Check if this engine start time is after or equal to the submitted_at time
                                                if ($engineState === 'N/A' && $engineStateEntry['value'] === 'On') {
                                                    $engineState = $engineStateEntry['time'];
                                                }
                                                if ($engineState && $engineStateEntry['time'] >= $engineState && $engineStateEntry['value'] === 'Off') {
                                                    $engineStateOff = date('F j, Y g:i A', strtotime($engineStateEntry['time']));
                                                    break 2; // Exit both loops once the first valid entry is found
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                            if($engineState == 'N/A') {
                                $currentDriver = 'N/A';
                            }
                            echo '<tr>';
                            echo '<td>' . htmlspecialchars($asset['name']) . '</td>';
                            $assignedDriver = isset($asset['staticAssignedDriver']['name']) && $asset['staticAssignedDriver']['name'] !== ''
                                ? $asset['staticAssignedDriver']['name']
                                : "Unassigned";

                            echo '<td>' . htmlspecialchars($assignedDriver) . '</td>';                            echo '<td>' . htmlspecialchars($currentDriver) . '</td>';
                            echo '<td>' . htmlspecialchars($asset['make'] . ' ' . $asset['model']) . '</td>';
                            
                            // Default value if no match is found
                            $submittedAt = 'N/A';
                            $submittedBy = 'N/A';
                            
                            // Loop through $records['records'] to find a matching vehicle name
                            if (isset($allRecords)) {
                                foreach ($allRecords as $record) {
                                    if ($record['vehicle']['name'] === $asset['name']) {
                                        if (date('m-d', strtotime($record['submitted_at'])) === date('m-d', strtotime($currentDate))) {
                                            $submittedAt = date('F j, Y g:i A', strtotime($record['submitted_at']));
                                            $submittedBy = $record['user']['name'];
                                        } else {
                                            $submittedAt = "N/A"; // Handle as needed
                                        }
                                    }
                                }
                            }
                            echo '<td>' . htmlspecialchars($submittedBy) . '</td>';
                            echo '<td>' . htmlspecialchars($submittedAt) . '</td>';

                            echo '<td>' . ($engineState === 'N/A' ? 'N/A' : htmlspecialchars(date('F j, Y g:i A', strtotime($engineState)))) . '</td>';
                            echo '<td>' . htmlspecialchars($engineStateOff) . '</td>';

                            $fleetioId = '';
                            if (isset($fleetioVehicles)) {
                                foreach ($fleetioVehicles as $vehicle) {
                                    if ($vehicle['name'] === $asset['name']) {
                                        $fleetioId = $vehicle['id'];
                                        break;
                                    }
                                }
                            }

                            $typeText = '';
                            if (
                                isset($asset['attributes'][0]['stringValues'])
                                && is_array($asset['attributes'][0]['stringValues'])
                                && isset($asset['attributes'][0]['stringValues'][0])
                            ) {
                                $typeText = $asset['attributes'][0]['stringValues'][0];
                            }
                            echo '<td class="vehicleType">'
                            . htmlspecialchars($typeText)
                            . '</td>';

                            $regionText = '';
                            foreach ($fleetioVehicles as $fv) {
                                if ($fv['id'] === $fleetioId) {
                                $regionText = $fv['group_name'];
                                break;
                                }
                            }
                            // emit a hidden cell for filtering
                            echo '<td class="region">'
                                . htmlspecialchars($regionText)
                                . '</td>';

                            $dvirVal = 'No';
                            foreach ($fleetioVehicles as $fv) {
                                if ($fv['id'] === $fleetioId) {
                                // assumes custom_fields['dvir_required'] is truthy/falsey
                                $dvirVal = !empty($fv['custom_fields']['dvir_required']) ? 'Yes' : 'No';
                                break;
                                }
                            }
                            echo '<td class="dvirRequired" style="display:none;">'
                                . htmlspecialchars($dvirVal)
                                . '</td>';

                            echo '<td class="text-center"><a href="https://secure.fleetio.com/b8f9977137/vehicles/' . $fleetioId . '" target="_blank" class="btn btn-sm text-white" style="background-color: rgb(6, 119, 72);">View</a></td>';
                            echo '<td class="text-center"><a href="https://cloud.samsara.com/o/4461/devices/' . $asset['id'] . '/vehicle?end_ms='. $todayTimestamps['endMs'] . '" target="_blank" class="btn btn-sm btn-dark">View</a></td>';
                            echo '</tr>';
                            
                        }
                    } else {
                        echo '<tr><td colspan="4" class="text-center">No data available</td></tr>';
                    }
                    ?>
                </tbody>
            </table>
            </div>
        </div>
    </main>
    <footer></footer>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const table = document.getElementById('vehiclesTable');
        const rows  = Array.from(table.tBodies[0].rows);

        // for each header‐search input…
        table.querySelectorAll('thead input[data-col]').forEach(input => {
        input.addEventListener('input', () => {
            const colIndex = parseInt(input.dataset.col, 10);
            const term     = input.value.trim().toLowerCase();

            rows.forEach(row => {
            const cellText = row.cells[colIndex].textContent.trim().toLowerCase();
            // hide row if this column doesn't match the term
            row.style.display = cellText.includes(term) ? '' : 'none';
            });
        });
        });
    });
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function(){
            const table = document.getElementById('vehiclesTable'),
                rows  = Array.from(table.tBodies[0].rows),
                typeFilter = document.getElementById('vehicleTypeFilter');

            function applyAllFilters() {
            const termFilters = Array.from(
                table.querySelectorAll('thead input[data-col]')
            ).map(input => ({
                col: +input.dataset.col,
                txt: input.value.trim().toLowerCase()
            }));

            const typeVal = typeFilter.value.trim().toLowerCase();

            rows.forEach(row => {
                let show = true;

                // 1) per-column text filters
                for (let {col, txt} of termFilters) {
                if (txt && !row.cells[col].textContent.trim().toLowerCase().includes(txt)) {
                    show = false;
                    break;
                }
                }
                // 2) vehicle-type dropdown filter
                if (show && typeVal) {
                const cell = row.querySelector('.vehicleType');
                if (!cell || cell.textContent.trim().toLowerCase() !== typeVal) {
                    show = false;
                }
                }

                row.style.display = show ? '' : 'none';
            });
            }

            // re-apply whenever a search-input changes…
            table.querySelectorAll('thead input[data-col]')
                .forEach(i => i.addEventListener('input', applyAllFilters));

            // …or whenever our new dropdown changes
            typeFilter.addEventListener('change', applyAllFilters);
        });
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
        const select = document.getElementById('vehicleTypeFilter');
        const types  = new Set();

        // scan every row for the .vehicleType cell
        document
            .querySelectorAll('#vehiclesTable tbody tr .vehicleType')
            .forEach(cell => {
            const txt = cell.textContent.trim();
            if (txt) types.add(txt);
            });

        // sort & append
        Array.from(types)
            .sort((a,b) => a.localeCompare(b))
            .forEach(type => {
            const opt = document.createElement('option');
            opt.value = type;
            opt.textContent = type;
            select.append(opt);
            });
        });
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function(){
        const table        = document.getElementById('vehiclesTable'),
                rows         = Array.from(table.tBodies[0].rows),
                regionSelect = document.getElementById('regionFilter'),
                dvirSel   = document.getElementById('dvirRequiredFilter');

        // 1) collect unique regions
        const regions = new Set();
        rows.forEach(row => {
            const td = row.querySelector('.region');
            if (td) {
            const txt = td.textContent.trim();
            if (txt) regions.add(txt);
            }
        });

        // 2) sort & append to the select
        Array.from(regions).sort().forEach(r => {
            const opt = document.createElement('option');
            opt.value = r;
            opt.textContent = r;
            regionSelect.append(opt);
        });

        // 3) hook into your existing applyAllFilters (or inline it here)
        function applyAllFilters() {
            const typeVal   = document.getElementById('vehicleTypeFilter').value.trim().toLowerCase(),
                regionVal = regionSelect.value.trim().toLowerCase(),
                dvirVal   = dvirSel.value.trim().toLowerCase(),
                // gather your column inputs as before…
                termInputs = Array.from(table.querySelectorAll('thead input[data-col]'))
                    .map(i => ({ col: +i.dataset.col, txt: i.value.trim().toLowerCase() }));
                    

            rows.forEach(row => {
            let show = true;

            // text‐column filters
            for (let {col, txt} of termInputs) {
                if (txt && !row.cells[col].textContent.trim().toLowerCase().includes(txt)) {
                show = false;
                break;
                }
            }

            // vehicle type filter (if set)
            if (show && typeVal) {
                const cell = row.querySelector('.vehicleType');
                if (!cell || cell.textContent.trim().toLowerCase() !== typeVal) {
                show = false;
                }
            }

            // **region** filter (if set)
            if (show && regionVal) {
                const cell = row.querySelector('.region');
                if (!cell || cell.textContent.trim().toLowerCase() !== regionVal) {
                show = false;
                }
            }

            // 4) DVIR Required
            if (show && dvirVal) {
                const cell = row.querySelector('.dvirRequired');
                if (!cell || cell.textContent.trim().toLowerCase() !== dvirVal) {
                show = false;
                }
            }

            row.style.display = show ? '' : 'none';
            });
        }

        // wire events
        table.querySelectorAll('thead input[data-col]')
            .forEach(i => i.addEventListener('input', applyAllFilters));
        document.getElementById('vehicleTypeFilter')
                .addEventListener('change', applyAllFilters);
        regionSelect.addEventListener('change', applyAllFilters);
        dvirSel.addEventListener('change', applyAllFilters);
        });
    </script>
        <script>
        (function(){
                    function padToNav(){
                var nav=document.querySelector('header .navbar');
                        if(!nav)return;var h=Math.ceil(nav.getBoundingClientRect().height);
                        document.body.style.paddingTop=(h+12)+'px';
                document.documentElement.style.setProperty('--navH',h+'px');
            }
            padToNav();window.addEventListener('load',padToNav);window.addEventListener('resize',padToNav);
        })();
        </script>
</body>
</html>