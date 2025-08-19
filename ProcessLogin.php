<?php
session_start();
include_once("./functions/connectdb.php");
include_once("../functions/functions.php");

$client_ip = getIP();
$userName = $_POST["email"] ?? '';
$password = $_POST["pass"] ?? '';
$errMsg = "";

// Prevent SQL injection with prepared statements or at least sanitize inputs (best is prepared)
$userNameSafe = mysqli_real_escape_string($con, $userName);

// $result = mysqli_query($con, "SELECT * FROM login_attempts WHERE username='$userNameSafe'");
// $show = mysqli_fetch_array($result);
// $attempts = $show['attempts'] ?? 0;

// if ($attempts > 5) {
//     header("Location: login.php?msg=disabled");
//     exit;
// }

if (!empty($userName) && !empty($password)) {
    $encryptPassword = hash('sha512', $password);

    $authSql = "SELECT * FROM users WHERE email = '$userNameSafe' AND pass = '$encryptPassword'";
    $authResult = mysqli_query($con, $authSql) or die('Couldn\'t Authenticate Visitor:' . mysqli_error($con));
    $authRow = mysqli_fetch_array($authResult);
    
    $userID = $authRow['id'] ?? 0;
    $userRole = $authRow['role'] ?? '';

    if ($userID > 0) {
        $_SESSION['USER_ID'] = $userID;
        $_SESSION['USR_LOGIN'] = $userName;
        $_SESSION['USR_ROLE'] = $userRole;

        header("Location: index.php");
        exit;
    } else {
        // if (!$attempts) {
        //     $attempts = 1;
        //     $insert = "INSERT INTO login_attempts (ip, attempts, username) VALUES ('$client_ip', '$attempts', '$userNameSafe')";
        //     mysqli_query($con, $insert);
        // } else if ($attempts <= 5) {
        //     $attempts++;
        //     $update = "UPDATE login_attempts SET attempts = '$attempts' WHERE username = '$userNameSafe'";
        //     mysqli_query($con, $update);
        // }
        header("Location: login.php?msg=invalid");
        exit;
    }
} else {
    header("Location: login.php?msg=missing");
    exit;
}
?>
