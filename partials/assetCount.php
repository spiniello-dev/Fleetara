<?php
include '../functions.php';
$assets = getSamsaraAssets();

$assetCount = (isset($assets['data']) && is_array($assets['data'])) ? count($assets['data']) : 0;

echo "<h2 class='card-title'>" . $assetCount . "</h2>";
