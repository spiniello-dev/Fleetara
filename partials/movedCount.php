<?php
include '../functions.php';

$assets = getSamsaraAssets();
$moved = getSamsaraVehiclesMoved($assets);
echo "<h2 class='card-title'>$moved</h2>";