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

// Rentals are identified by Asset # (name) starting with 'Z'.
// Use Fleetio filter[name][like]=Z to fetch only those assets.
function getRentalVehicles() {
    $url = 'https://secure.fleetio.com/api/vehicles';
    $headers = [
        'Accept: application/json',
    'Authorization: Token ' . ($_ENV['FLEETIO_API_KEY'] ?? ''),
    'Account-Token: ' . ($_ENV['FLEETIO_ACCOUNT_TOKEN'] ?? '')
    ];

    $vehicles = [];
    $perPage = 50;
    $startCursor = null;

    do {
        $urlWithParams = $url . '?per_page=' . $perPage . '&filter[name][like]=Z';
        if ($startCursor) {
            $urlWithParams .= '&start_cursor=' . urlencode($startCursor);
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $urlWithParams);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        $response = curl_exec($ch);
        curl_close($ch);

        if ($response === false) {
            return null;
        }

        $data = json_decode($response, true);

        if (isset($data['records']) && is_array($data['records'])) {
            $vehicles = array_merge($vehicles, $data['records']);
        }

        $startCursor = isset($data['next_cursor']) ? $data['next_cursor'] : null;
    } while ($startCursor);

    // Extra safety: only keep names that really start with 'Z'
    $vehicles = array_values(array_filter($vehicles, function ($v) {
        return isset($v['name']) && strpos($v['name'], 'Z') === 0;
    }));

    return $vehicles;
}

// Function to fetch submitted inspection reports for the date range (no form filter) with pagination
function getSubmittedInspectionReports() {
    $baseUrl = 'https://secure.fleetio.com/api/submitted_inspection_forms';
    $headers = [
        'Accept: application/json',
    'Authorization: Token ' . ($_ENV['FLEETIO_API_KEY'] ?? ''),
    'Account-Token: ' . ($_ENV['FLEETIO_ACCOUNT_TOKEN'] ?? '')
    ];

    $all = [];
    $perPage = 100;
    $startCursor = null;

    do {
        $url = $baseUrl
            . '?per_page=' . $perPage
            . '&filter[submitted_at][gte]=' . urlencode($GLOBALS['currentDate'])
            . '&filter[submitted_at][lte]=' . urlencode($GLOBALS['nextDate']);
        if ($startCursor) {
            $url .= '&start_cursor=' . urlencode($startCursor);
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        $response = curl_exec($ch);
        curl_close($ch);

        if ($response === false) {
            break;
        }

        $decoded = json_decode($response, true);
        if (isset($decoded['records']) && is_array($decoded['records'])) {
            $all = array_merge($all, $decoded['records']);
        }
        $startCursor = isset($decoded['next_cursor']) ? $decoded['next_cursor'] : null;
    } while ($startCursor);

    return $all;
}

// Fetch vehicles and submitted inspection reports
$vehicles = getRentalVehicles();
$inspectionReports = getSubmittedInspectionReports();
echo '<script>console.log("Vehicles: ", ' . json_encode($vehicles) . ');</script>';
echo '<script>console.log("DVIRS: ", ' . json_encode($inspectionReports) . ');</script>';

$filteredVehicles = [];

if ($vehicles) {
    // Prepare rental lookup by id and name
    $rentalsById = [];
    $rentalsByName = [];
    foreach ($vehicles as $v) {
        $rentalsById[$v['id']] = true;
        if (!empty($v['name'])) {
            $rentalsByName[strtoupper(trim($v['name']))] = $v['id'];
        }
    }

    // Map reports to rentals via vehicle/asset id or name; keep latest submission per rental
    $reportsByVehicleId = [];
    foreach ($inspectionReports as $report) {
        $submittedAt = $report['submitted_at'] ?? null;
        $userName = $report['user']['name'] ?? 'N/A';

        $candidateIds = [];
        $candidateNames = [];

        if (isset($report['vehicle']['id'])) $candidateIds[] = $report['vehicle']['id'];
        if (!empty($report['vehicle']['name'])) $candidateNames[] = $report['vehicle']['name'];
        if (isset($report['asset']['id'])) $candidateIds[] = $report['asset']['id'];
        if (!empty($report['asset']['name'])) $candidateNames[] = $report['asset']['name'];

        // Resolve to a rental id we know
        $resolvedId = null;
        foreach ($candidateIds as $cid) {
            if (isset($rentalsById[$cid])) { $resolvedId = $cid; break; }
        }
        if ($resolvedId === null) {
            foreach ($candidateNames as $nm) {
                $key = strtoupper(trim($nm));
                if (isset($rentalsByName[$key])) { $resolvedId = $rentalsByName[$key]; break; }
            }
        }
        if ($resolvedId === null) continue; // not one of our rentals

        $existing = $reportsByVehicleId[$resolvedId] ?? null;
        if (!$existing || ($submittedAt && strtotime($submittedAt) > strtotime($existing['submitted_at']))) {
            $reportsByVehicleId[$resolvedId] = [
                'submitted_at' => $submittedAt ?? 'N/A',
                'submitted_by' => $userName
            ];
        }
    }

    foreach ($vehicles as $vehicle) {
        $inspectionDetails = $reportsByVehicleId[$vehicle['id']] ?? null;
        $filteredVehicles[] = [
            'vehicle_name' => $vehicle['name'],
            'id' => $vehicle['id'],
            'driver' => 'N/A', 
            'make_model' => trim(($vehicle['make'] ?? '') . ' ' . ($vehicle['model'] ?? '')),
            'inspection_details' => $inspectionDetails,
            'vehicle_type' => $vehicle['vehicle_type_name'] ?? 'N/A',
            'region' => $vehicle['group_name'] ?? 'N/A',
            'is_submitted' => $inspectionDetails !== null
        ];
    }
    echo '<script>console.log("Rental Vehicles: ", ' . json_encode($filteredVehicles) . ');</script>';
}

// Build unique lists for filters
$vehicleTypes = array_values(array_unique(array_map(function($v){ return $v['vehicle_type']; }, $filteredVehicles)));
sort($vehicleTypes);
$regions = array_values(array_unique(array_map(function($v){ return $v['region']; }, $filteredVehicles)));
sort($regions);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fleetara - Rentals</title>
    <link rel="icon" href="assets/favicon.png" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .highlight-green {
            --bs-table-bg: #28a745; /* Green background */
            --bs-table-color: white; /* White text for contrast */
            --bs-table-striped-color: white; /* White text for contrast */
        }
    </style>
    <script>
        function updateDate() {
            const selectedDate = document.getElementById('datePicker').value;
            window.location.href = '?date=' + selectedDate;
        }
    </script>
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
                            <a class="nav-link" href="reports.php">Reports</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="trailers.php">Trailers</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="rentals.php">Rentals</a>
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
            <h2 class="text-center mb-4">Rental Inspection Reports</h2>
            <div class="row justify-content-between mb-4">
                <div class="col-auto">
                    <h3 class="text-center align-self-center col-auto">Updated for: <?= date('F j, Y g:i A', strtotime($currentDate)) ?></h3>
                </div>
                <div class="col-auto row">
                    <div class="col-auto">
                        <label for="datePicker" class="form-label">Select Date:</label>
                        <div class="d-flex flex-row">
                            <input type="date" id="datePicker" value="<?php echo date('Y-m-d', strtotime($currentDate)); ?>" class="form-control" style="margin-right: 1em">
                            <button class="btn btn-primary mt-2" onclick="updateDate()">Update</button>
                        </div>
                    </div>
                    <div class="col-auto">
                        <label for="vehicleTypeFilter" class="form-label">Vehicle Type:</label>
                        <select id="vehicleTypeFilter" class="form-select">
                            <option value="">All Types</option>
                            <?php foreach ($vehicleTypes as $type) {
                                echo "<option value='" . htmlspecialchars($type) . "'>" . htmlspecialchars($type) . "</option>";
                            } ?>
                        </select>
                    </div>
                    <div class="col-auto">
                        <label for="regionFilter" class="form-label">Region:</label>
                        <select id="regionFilter" class="form-select">
                            <option value="">All Regions</option>
                            <?php foreach ($regions as $region) {
                                echo "<option value='" . htmlspecialchars($region) . "'>" . htmlspecialchars($region) . "</option>";
                            } ?>
                        </select>
                    </div>
                </div>  
            </div>
            <div class="table-responsive">
            <table id="vehiclesTable" class="table table-striped">
                <thead>
                    <tr>
                        <th>Asset #</th>
                        <th>Driver</th>
                        <th>Make/Model</th>
                        <th>Submitted At</th>
                        <th>Submitted By</th>
                        <th>Vehicle Type</th>
                        <th class="d-none">Region</th> <!-- Hidden column for region -->
                        <th>Fleetio</th>
                    </tr>
                    <tr>
                        <th><input type="text" class="form-control form-control-sm" placeholder="Search Asset #" data-col="0"></th>
                        <th><input type="text" class="form-control form-control-sm" placeholder="Search Driver" data-col="1"></th>
                        <th><input type="text" class="form-control form-control-sm" placeholder="Search Make/Model" data-col="2"></th>
                        <th><input type="text" class="form-control form-control-sm" placeholder="Search Submitted At" data-col="3"></th>
                        <th><input type="text" class="form-control form-control-sm" placeholder="Search Submitted By" data-col="4"></th>
                        <th><input type="text" class="form-control form-control-sm" placeholder="Search Vehicle Type" data-col="5"></th>
                        <th class="d-none"><input type="text" class="form-control form-control-sm" placeholder="Search Region" data-col="6"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if (empty($filteredVehicles)) {
                        echo '<tr><td colspan="8" class="text-center">No rental assets found.</td></tr>';
                    } else {
                        foreach ($filteredVehicles as $vehicle) {
                            $rowClass = $vehicle['is_submitted'] ? 'highlight-green' : ''; // Check if submitted
                            echo '<tr class="' . $rowClass . '">';
                            echo '<td>' . htmlspecialchars($vehicle['vehicle_name']) . '</td>';
                            echo '<td>' . htmlspecialchars($vehicle['driver']) . '</td>';
                            echo '<td>' . htmlspecialchars($vehicle['make_model']) . '</td>';
                            echo '<td>' . htmlspecialchars($vehicle['inspection_details']['submitted_at'] ?? 'N/A') . '</td>';
                            echo '<td>' . htmlspecialchars($vehicle['inspection_details']['submitted_by'] ?? 'N/A') . '</td>';
                            echo '<td class="vehicleType">' . htmlspecialchars($vehicle['vehicle_type']) . '</td>';
                            echo '<td class="region d-none">' . htmlspecialchars($vehicle['region']) . '</td>';
                            echo '<td class="text-center"><a href="https://secure.fleetio.com/b8f9977137/vehicles/' . $vehicle['id'] . '" target="_blank" class="btn btn-sm text-white" style="background-color: rgb(6, 119, 72);">View</a></td>';
                            echo '</tr>';
                        }
                    }
                    ?>
                </tbody>
            </table>
            </div>
        </div>
    </main>
    <!-- Bootstrap JS for navbar toggler -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const table = document.getElementById('vehiclesTable');
            const rows = Array.from(table.tBodies[0].rows);

            // Vehicle type filter
            const vehicleTypeFilter = document.getElementById('vehicleTypeFilter');
            const regionFilter = document.getElementById('regionFilter');

            function applyFilters() {
                const termFilters = Array.from(
                    table.querySelectorAll('thead input[data-col]')
                ).map(input => ({
                    col: +input.dataset.col,
                    txt: input.value.trim().toLowerCase()
                }));

                const vehicleTypeVal = vehicleTypeFilter.value.trim().toLowerCase();
                const regionVal = regionFilter.value.trim().toLowerCase();

                rows.forEach(row => {
                    let show = true;

                    // Column filters
                    for (let {col, txt} of termFilters) {
                        const cellText = row.cells[col].textContent.trim().toLowerCase();
                        if (txt && !cellText.includes(txt)) {
                            show = false;
                            break;
                        }
                    }

                    // Vehicle type filter
                    if (show && vehicleTypeVal) {
                        const vehicleTypeCell = row.querySelector('.vehicleType');
                        if (!vehicleTypeCell || vehicleTypeCell.textContent.trim().toLowerCase() !== vehicleTypeVal) {
                            show = false;
                        }
                    }

                    // Region filter
                    if (show && regionVal) {
                        const regionCell = row.querySelector('.region');
                        if (!regionCell || regionCell.textContent.trim().toLowerCase() !== regionVal) {
                            show = false;
                        }
                    }

                    row.style.display = show ? '' : 'none';
                });
            }

            // Search columns
            table.querySelectorAll('thead input[data-col]').forEach(input => {
                input.addEventListener('input', applyFilters);
            });

            vehicleTypeFilter.addEventListener('change', applyFilters);
            regionFilter.addEventListener('change', applyFilters);
        });
    </script>
</body>
</html>
