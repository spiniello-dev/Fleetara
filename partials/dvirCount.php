<?php
include '../functions.php';

$dvirRecords = getFleetioDVIRSubmissions();
echo "<h2 class='card-title'>" . count($dvirRecords) . "</h2>";