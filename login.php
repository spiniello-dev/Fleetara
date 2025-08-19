<?php
session_start();
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
        .sign-in {
            transition: background-color 0.3s ease-in-out, transform 0.2s ease;
        }
        .sign-in:hover {
            transform: scale(1.05);
        }
    header .navbar { z-index: 1050; }
    body { margin: 0; }
    @media (max-width: 991.98px) { .navbar-collapse { background-color: #212529; } }
    /* Reduce brand image on very small screens to keep nav compact */
    @media (max-width: 575.98px) { header .navbar .navbar-brand img { width: 56px; height: auto; } }
    /* Ensure no extra scroll: main fills viewport minus navbar height */
    main.container { height: calc(100vh - var(--navH, 70px)) !important; }
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
                <!-- <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav ms-auto">
                        <li class="nav-item">
                            <a class="nav-link" href="#">Home</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#">Reports</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#">Assets</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#">DVIRS</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-danger" href="#">Logout</a>
                        </li>
                    </ul>
                </div> -->
            </div>
        </nav>
    </header>
    <main class="container d-flex justify-content-center align-items-center vh-100">
        <div class="card p-4 shadow-lg" style="width: 350px;">
            <h2 class="text-center">Sign In</h2>
            <?php echo $alert;?>
            <form name="SignUp" action="ProcessLogin.php" method="POST" enctype="multipart/form-data">
                <div class="mb-3">
                    <label class="form-label">Email Address</label>
                    <input type="text" class="form-control" name="email" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Password</label>
                    <input type="password" class="form-control" name="pass" required>
                </div>
                <div class="mb-3 text-end">
                    <a href="forgotpass.php" class="text-decoration-none">Forgot password?</a>
                </div>
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary sign-in">Sign In</button>
                </div>
            </form>
        </div>
    </main>
    <footer></footer>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Dynamically set body padding and CSS var to actual navbar height (prevents extra scroll on mobile)
    (function() {
        function adjustNavOffset() {
            var nav = document.querySelector('header .navbar');
            if (!nav) return;
            var h = Math.ceil(nav.getBoundingClientRect().height);
            document.body.style.paddingTop = h + 'px';
            document.documentElement.style.setProperty('--navH', h + 'px');
        }
        window.addEventListener('load', adjustNavOffset);
        window.addEventListener('resize', adjustNavOffset);
    })();
    </script>
</body>
</html>