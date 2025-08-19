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

// Define the vehicle types you're interested in
$vehicleTypes = [
    "Trailer",
    "TRAILER - REEFER",
    "TRAILERS (BOILER)",
    "TRAILERS (DROP DECK)",
    "TRAILERS (ENCLOSED TOW-BEHIND)",
    "TRAILERS (FLATBED)",
    "TRAILERS (LOWBOY)",
    "TRAILERS (TOW-BEHIND)",
    "TRAILERS (WET-OUT)"
];

// Function to fetch vehicles (pagination handled)
function getVehicles() {
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
        $urlWithParams = $url . '?per_page=' . $perPage;
        if ($startCursor) {
            $urlWithParams .= '&start_cursor=' . $startCursor;
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

    return $vehicles;
}

// Function to fetch submitted trailer inspection reports
function getSubmittedInspectionReports() {
    $url = 'https://secure.fleetio.com/api/submitted_inspection_forms?filter[inspection_form_id][eq]=309431' . 
    '&filter[submitted_at][gte]=' . urlencode($GLOBALS['currentDate']) . 
    '&filter[submitted_at][lte]=' . urlencode($GLOBALS['nextDate']);
    $headers = [
        'Accept: application/json',
    'Authorization: Token ' . ($_ENV['FLEETIO_API_KEY'] ?? ''),
    'Account-Token: ' . ($_ENV['FLEETIO_ACCOUNT_TOKEN'] ?? '')
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    $response = curl_exec($ch);
    curl_close($ch);

    if ($response === false) {
        return [];
    }

    $decoded = json_decode($response, true);
    if (!is_array($decoded)) {
        return [];
    }
    return isset($decoded['records']) && is_array($decoded['records']) ? $decoded['records'] : [];
}

// Fetch vehicles and submitted inspection reports
$vehicles = getVehicles();
$inspectionReports = getSubmittedInspectionReports();

$filteredVehicles = [];

if ($vehicles) {
    foreach ($vehicles as $vehicle) {
        if (in_array($vehicle['vehicle_type_name'], $vehicleTypes)) {
            $inspectionDetails = null;
            if (!empty($inspectionReports) && is_array($inspectionReports)) {
                foreach ($inspectionReports as $report) {
                    if (isset($report['vehicle']['id']) && $report['vehicle']['id'] == $vehicle['id']) {
                        $inspectionDetails = [
                            'submitted_at' => $report['submitted_at'] ?? 'N/A',
                            'submitted_by' => $report['user']['name'] ?? 'N/A'
                        ];
                        break;
                    }
                }
            }

            if ($inspectionDetails) {
                $filteredVehicles[] = [
                    'vehicle_name' => $vehicle['name'],
                    'id' => $vehicle['id'],
                    'driver' => 'N/A', 
                    'make_model' => $vehicle['make'] . ' ' . $vehicle['model'],
                    'inspection_details' => $inspectionDetails,
                    'vehicle_type' => $vehicle['vehicle_type_name'],
                    'region' => $vehicle['group_name'] ?? 'N/A',
                    'is_submitted' => true
                ];
            } else {
                $filteredVehicles[] = [
                    'vehicle_name' => $vehicle['name'],
                    'id' => $vehicle['id'],
                    'driver' => 'N/A', 
                    'make_model' => $vehicle['make'] . ' ' . $vehicle['model'],
                    'inspection_details' => null,
                    'vehicle_type' => $vehicle['vehicle_type_name'],
                    'region' => $vehicle['group_name'] ?? 'N/A',
                    'is_submitted' => false
                ];
            }
        }
    }
    echo '<script>console.log("Vehicles: ", ' . json_encode($filteredVehicles) . ');</script>';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fleetara - Trailers</title>
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
                            <a class="nav-link active" href="trailers.php">Trailers</a>
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
            <h2 class="text-center mb-4">Trailer Inspection Reports</h2>
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
                            <?php
                            foreach ($vehicleTypes as $type) {
                                echo "<option value='$type'>$type</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="col-auto">
                        <label for="regionFilter" class="form-label">Region:</label>
                        <select id="regionFilter" class="form-select">
                            <option value="">All Regions</option>
                            <?php
                            $regions = array_unique(array_column($filteredVehicles, 'region'));
                            foreach ($regions as $region) {
                                echo "<option value='$region'>$region</option>";
                            }
                            ?>
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
                        <th>Fleetio</th> <!-- Hidden column for region -->
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
                        echo '<tr><td colspan="7" class="text-center">No vehicles found with submitted reports.</td></tr>';
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
