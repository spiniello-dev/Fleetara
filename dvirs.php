<?php
require_once __DIR__ . '/vendor/autoload.php';
if (class_exists('Dotenv\\Dotenv')) { $dotenv = Dotenv\Dotenv::createImmutable(__DIR__); $dotenv->safeLoad(); }
session_start();

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

include ("./functions/login_messages.php");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="assets/favicon.png" type="image/x-icon">
    <title>Fleetara</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
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
                            <a class="nav-link" href="rentals.php">Rentals</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="assets.php">Assets</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="fuel.php">Fuel</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="dvirs.php">DVIRS</a>
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
            <h2 class="text-center mb-4">Fleetio DVIR Submissions</h2>
            <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th scope="col">Asset #</th>
                        <th scope="col">Driver</th>
                        <th scope="col">Make/Model</th>
                        <th scope="col">Submission (EST)</th>
                        <th scope="col">Longitude/Latitude</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $currentDate = date('Y-m-d') . 'T00:00:00-04:00';
                    
                    $apiKey = $_ENV['FLEETIO_API_KEY'] ?? '';
                    // Construct the URL with the dynamic date
                    $url = "https://secure.fleetio.com/api/v1/submitted_inspection_forms?filter[inspection_form_id][eq]=189480&filter[submitted_at][gte]=$currentDate";
                    
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $url);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, [
                        "Authorization: Token $apiKey",
                        "Account-Token: " . ($_ENV['FLEETIO_ACCOUNT_TOKEN'] ?? ''),
                        "X-Api-Version: 2024-06-30",
                        "Accept: application/json"
                    ]);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

                    $response = curl_exec($ch);

                    if (curl_errno($ch)) {
                        echo 'cURL error: ' . curl_error($ch);
                    } else {
                        $data = json_decode($response, true);
                        echo '<pre>';
                        //print_r($data);
                        echo '</pre>';
                    }

                    curl_close($ch);

                    $records = json_decode($response, true);
                    echo '<script>console.log("Records: ", ' . json_encode($records) . ');</script>';

                    if (isset($records['records'])) {
                        foreach ($records['records'] as $record) {
                            echo '<tr>';
                            echo '<td>' . htmlspecialchars($record['vehicle']['name']) . '</td>';
                            echo '<td>' . htmlspecialchars($record['user']['name']) . '</td>';
                            echo '<td>' . htmlspecialchars($record['vehicle']['make'] . ' ' . $record['vehicle']['model']) . '</td>';
                            echo '<td>' . htmlspecialchars(date('F j, Y g:i A', strtotime($record['submitted_at']))) . '</td>';
                            echo '<td>' . htmlspecialchars($record['submitted_longitude'] . ' | ' . $record['submitted_latitude']) . '</td>';
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