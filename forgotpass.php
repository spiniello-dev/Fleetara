<?php
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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css">
    <style>
        .back-to-login {
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            transition: background-color 0.3s ease-in-out, transform 0.2s ease;
        }
        .back-to-login:hover {
            background-color: #007bff;
            color: white !important;
            transform: scale(1.05);
        }
        .submit {
            transition: background-color 0.3s ease-in-out, transform 0.2s ease;
        }
    .submit:hover {
            transform: scale(1.05);
        }
    header .navbar { z-index: 1050; }
    body { margin: 0; }
    @media (max-width: 991.98px) { .navbar-collapse { background-color: #212529; } }
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
    <main class="flex-column container d-flex justify-content-center align-items-center vh-100">
        <div class="card p-4 shadow-lg" style="width: 350px;">
            <h2 class="text-center">Forgot Password</h2>
            <?php if (!empty($alert)) { echo '<div class="mb-2">' . $alert . '</div>'; } ?>
            <p class="text-center">Enter your email below and click "Submit."<br>A password reset link will be emailed to you.</p>
            <form name="SignUp" action="forgotPassProcess.php" method="POST" enctype="multipart/form-data">
                <div class="mb-3">
                    <label class="form-label">Email Address</label>
                    <input type="text" class="form-control" name="email" required>
                </div>
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary submit">Submit</button>
                </div>
            </form>
        </div>
        <a class="flex flex-row card px-4 py-2 shadow-lg text-center m-0 back-to-login mt-4" href="./login.php">
            <i class="bi bi-arrow-left me-2"></i> Back To Login
        </a>
    </main>
    <footer></footer>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    (function() {
        function adjustNavOffset() {
            var nav = document.querySelector('header .navbar');
            if (!nav) return;
            var h = Math.ceil(nav.getBoundingClientRect().height);
            document.body.style.paddingTop = (h + 12) + 'px';
            document.documentElement.style.setProperty('--navH', h + 'px');
        }
        adjustNavOffset();
        window.addEventListener('load', adjustNavOffset);
        window.addEventListener('resize', adjustNavOffset);
    })();
    </script>
</body>
</html>