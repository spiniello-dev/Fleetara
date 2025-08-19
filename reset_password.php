<?php
session_start();
require_once __DIR__ . '/functions/connectdb.php';

$token = $_GET['token'] ?? '';
$tokenSafe = mysqli_real_escape_string($con, $token);

$step = 'form';
$userId = 0;

if ($token === '') {
    $step = 'invalid';
} else {
    $now = date('Y-m-d H:i:s');
    $sql = "SELECT pr.user_id, pr.expires_at, pr.used, u.email
            FROM password_resets pr
            JOIN users u ON u.id = pr.user_id
            WHERE pr.token = '$tokenSafe' LIMIT 1";
    $res = mysqli_query($con, $sql);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    if (!$row) {
        $step = 'invalid';
    } elseif ((int)$row['used'] === 1 || strtotime($row['expires_at']) < time()) {
        $step = 'expired';
    } else {
        $userId = (int)$row['user_id'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $userId > 0) {
    $pwd = $_POST['password'] ?? '';
    $confirm = $_POST['confirm'] ?? '';
    if ($pwd === '' || $pwd !== $confirm) {
        $err = 'Passwords must match and not be empty.';
    } else {
        $hash = hash('sha512', $pwd);
        mysqli_query($con, "UPDATE users SET pass = '" . mysqli_real_escape_string($con, $hash) . "' WHERE id = $userId");
        mysqli_query($con, "UPDATE password_resets SET used = 1 WHERE token = '$tokenSafe'");
        header('Location: login.php?msg=resetgood');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <link rel="icon" href="assets/favicon.png" type="image/x-icon">
  <title>Reset Password - Fleetara</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
  <style>
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
        <img style="width: 80px;" src="assets/logo.png" alt="Fleetara Logo"/> Fleetara
      </a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
      </button>
    </div>
  </nav>
</header>
<main class="container d-flex justify-content-center align-items-center min-vh-100">
  <div class="card p-4 shadow-lg" style="width: 380px;">
    <h2 class="text-center mb-3">Reset Password</h2>
    <?php if (isset($err)) echo '<div class="alert alert-danger">' . htmlspecialchars($err) . '</div>'; ?>
    <?php if ($step === 'invalid'): ?>
      <div class="alert alert-danger">Invalid or unknown reset link.</div>
      <a href="forgotpass.php" class="btn btn-secondary w-100">Request New Link</a>
    <?php elseif ($step === 'expired'): ?>
      <div class="alert alert-warning">This reset link has expired.</div>
      <a href="forgotpass.php" class="btn btn-secondary w-100">Request New Link</a>
    <?php else: ?>
      <form method="POST">
        <div class="mb-3">
          <label class="form-label">New Password</label>
          <input type="password" name="password" class="form-control" required />
        </div>
        <div class="mb-3">
          <label class="form-label">Confirm Password</label>
          <input type="password" name="confirm" class="form-control" required />
        </div>
        <button type="submit" class="btn btn-primary w-100">Update Password</button>
      </form>
    <?php endif; ?>
  </div>
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function(){
  function pad(){
    var nav=document.querySelector('header .navbar'); if(!nav) return;
    var h=Math.ceil(nav.getBoundingClientRect().height); document.body.style.paddingTop=(h+12)+'px';
  }
  pad(); window.addEventListener('load',pad); window.addEventListener('resize',pad);
})();
</script>
</body>
</html>
