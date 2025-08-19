<?php
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
    <style>
        .spinner {
            color: gray;
            font-style: italic;
            font-size: 1.2em;
            display: inline-block;
            width: 2em;
            height: 2em;
            border: 4px solid transparent;
            border-top: 4px solid gray;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        /* Navbar should overlay content and stick to top */
        header .navbar { z-index: 1050; }
    body { margin: 0; }
        /* Solid background for expanded mobile menu */
        @media (max-width: 991.98px) {
            .navbar-collapse { background-color: #212529; }
        }
        /* Make navbar brand image smaller on very small screens to reduce navbar height */
        @media (max-width: 575.98px) {
            header .navbar .navbar-brand img { width: 56px; height: auto; }
        }
        /* Use dynamic navbar height to offset the main content */
        .offset-by-nav { padding-top: calc(var(--navH, 70px) + 12px); }
        @media (max-width: 575.98px) {
            .offset-by-nav { padding-top: calc(var(--navH, 70px) + 16px); }
        }
    /* Remove extra scroll height on desktop: 100vh minus navbar height */
    main.container { min-height: calc(100vh - var(--navH, 70px)) !important; }
    </style>
</head>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
    $(document).ready(function () {
        $('#dvirCount').load('partials/dvirCount.php');
        $('#assetCount').load('partials/assetCount.php');
        $('#movedCount').load('partials/movedCount.php');
        adjustBodyPadding();
    });
    // Ensure body top padding matches actual navbar height on load and resize
    function adjustBodyPadding() {
        var nav = document.querySelector('header .navbar');
        if (!nav) return;
        var h = Math.ceil(nav.getBoundingClientRect().height);
        document.body.style.paddingTop = h + 'px';
        document.documentElement.style.setProperty('--navH', h + 'px');
    }
    // Run ASAP to avoid layout jump
    adjustBodyPadding();
    window.addEventListener('load', adjustBodyPadding);
    window.addEventListener('resize', adjustBodyPadding);
</script>
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
                            <a class="nav-link active" href="index.php">Home</a>
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
    <main class="container d-flex flex-column justify-content-center align-items-center min-vh-100 pb-5">
        <h1 class="mb-4">
            Welcome to Fleetara! <img style="width: 160px;" src="assets/logo.png" alt="Fleetara Logo"/>
        </h1>
        <div class="row gx-3 gy-4 w-100">
            <!-- DVIR Submission Count Card -->
            <div class="col-md-4">
                <div class="card p-4 shadow-lg">
                    <h4 class="text-center">DVIR Submission Count</h4>
                    <div class="card-body text-center" id="dvirCount">
                        <div class="spinner"></div>
                        
                    </div>
                    <p class='card-text text-center'>Total number of DVIR submissions today.</p>
                </div>
            </div>

            <!-- Total Asset Count Card -->
            <div class="col-md-4">
                <div class="card p-4 shadow-lg">
                    <h4 class="text-center">Total Asset Count</h4>
                    <div class="card-body text-center" id="assetCount">
                        <div class="spinner"></div>
                    </div>
                    <p class='card-text text-center'>Total number of assets in the system.</p>
                </div>
            </div>

            <!-- Total Assets Moved Today Card -->
            <div class="col-md-4">
                <div class="card p-4 shadow-lg">
                    <h4 class="text-center">Total Assets Moved Today</h4>
                    <div class="card-body text-center" id="movedCount">
                        <div class="spinner"></div>
                    </div>
                    <p class='card-text text-center'>Total number of assets moved today.</p>
                </div>
            </div>
        </div>
    </main>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>