<?php
require_once __DIR__ . '/../vendor/autoload.php';
if (class_exists('Dotenv\\Dotenv')) {
    $dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
    if (method_exists($dotenv, 'safeLoad')) {
        $dotenv->safeLoad();
    } else {
        $dotenv->load();
    }
}

$database = $_ENV['DB_NAME'] ?? 'Fleetara';
$dBuser   = $_ENV['DB_USER'] ?? 'root';
$dBpassword = $_ENV['DB_PASS'] ?? '';
$host     = $_ENV['DB_HOST'] ?? 'localhost';

$con = mysqli_connect($host, $dBuser, $dBpassword);

if (!$con) {
    die('I cannot connect to the database because: ' . mysqli_connect_error());
}

if (!mysqli_select_db($con, $database)) {
    die('Database selection failed: ' . mysqli_error($con));
}
?>