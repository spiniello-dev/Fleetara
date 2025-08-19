<?php
// Accepts a POSTed CSV/TSV file and appends its non-duplicate rows to the master file in /reports/Spiniello Fuel Transactions.csv
header('Content-Type: application/json');
$targetFile = __DIR__ . '/reports/Spiniello Fuel Transactions.csv';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['importFile'])) {
    echo json_encode(['success' => false, 'error' => 'No file uploaded.']);
    exit;
}

$tmpName = $_FILES['importFile']['tmp_name'];
if (!is_uploaded_file($tmpName)) {
    echo json_encode(['success' => false, 'error' => 'Upload failed.']);
    exit;
}

// Normalize header names (lowercase, remove non-alphanumerics)
function norm_name($s) {
    $s = strtolower(trim((string)$s));
    return preg_replace('/[^a-z0-9]+/', '', $s);
}

// Build a quick lookup of header name -> index
function build_index(array $header) {
    $map = [];
    foreach ($header as $i => $h) {
        $map[norm_name($h)] = $i;
    }
    return $map;
}

// Find the first matching index for a list of candidate names (already normalized in build_index)
function find_idx(array $idxMap, array $candidates) {
    foreach ($candidates as $c) {
        $n = norm_name($c);
        if (array_key_exists($n, $idxMap)) return $idxMap[$n];
    }
    return -1;
}

// Detect delimiter (tab, comma, semicolon) by sampling first line
function detect_delimiter($filePath) {
    $fh = fopen($filePath, 'r');
    if (!$fh) return ',';
    $sample = fgets($fh, 4096);
    fclose($fh);
    if ($sample === false) return ',';
    $counts = ["\t" => substr_count($sample, "\t"), ',' => substr_count($sample, ','), ';' => substr_count($sample, ';')];
    arsort($counts);
    $delim = array_key_first($counts);
    return $counts[$delim] > 0 ? $delim : ',';
}

// Ensure the master file ends with a newline before appending
function ensure_trailing_newline($filePath) {
    if (!is_file($filePath)) return; // nothing to do
    $size = filesize($filePath);
    if ($size === 0) return;
    $fh = fopen($filePath, 'r+');
    if (!$fh) return;
    if ($size >= 2) {
        fseek($fh, -2, SEEK_END);
        $tail = fread($fh, 2);
        if ($tail !== "\r\n") {
            fseek($fh, 0, SEEK_END);
            fwrite($fh, "\r\n");
        }
    } else {
        fseek($fh, 0, SEEK_END);
        fwrite($fh, "\r\n");
    }
    fclose($fh);
}

// 1) Load master header and existing keys to avoid duplicates
$existingIds = [];
$existingFallback = [];
$masterHeader = null;
if (is_file($targetFile) && ($mh = fopen($targetFile, 'r')) !== false) {
    $masterHeader = fgetcsv($mh);
    if ($masterHeader !== false && $masterHeader !== null) {
        $mIdx = build_index($masterHeader);
        $mTransIdIdx = find_idx($mIdx, ['Trans ID','Transaction ID','TransactionID','TransID']);
        $mDateIdx    = find_idx($mIdx, ['Trans Date','Transaction Date','Date']);
        $mAssetIdx   = find_idx($mIdx, ['Asset #','Asset','Asset ID','AssetID']);
        $mGallonsIdx = find_idx($mIdx, ['Gallons','Units','Quantity']);
        while (($r = fgetcsv($mh)) !== false) {
            if (!is_array($r) || !array_filter($r)) continue;
            if ($mTransIdIdx >= 0 && isset($r[$mTransIdIdx]) && $r[$mTransIdIdx] !== '') {
                $existingIds[trim((string)$r[$mTransIdIdx])] = true;
            } else {
                $d = $mDateIdx >= 0 && isset($r[$mDateIdx]) ? trim((string)$r[$mDateIdx]) : '';
                $a = $mAssetIdx >= 0 && isset($r[$mAssetIdx]) ? trim((string)$r[$mAssetIdx]) : '';
                $g = $mGallonsIdx >= 0 && isset($r[$mGallonsIdx]) ? trim((string)$r[$mGallonsIdx]) : '';
                $key = strtolower($d.'|'.$a.'|'.$g);
                if ($key !== '||') $existingFallback[$key] = true;
            }
        }
    }
    fclose($mh);
}

// Sanity: if master header missing, assume user's provided canonical order
if (!$masterHeader) {
    $masterHeader = [
        'Trans ID','Trans Date','Trans Time','Card','Vehicle #','Odometer','Asset #','Job#','Region #','Vehicle Description','Driver #','First Name','Last Name','Level 1','Level 2','Department','PIN Department','Fuel Site','City','State','On/Off','Posted Date','Gallons','Gross Dollars','Net Dollars','Unit Cost','Net Unit Cost','Product','Pump #','Engine Hours','Previous Engine Hours','Odometer2','Previous Odometer','Network','Trans Type','Miles Per Gallon'
    ];
}

// 2) Read uploaded file with delimiter detection and collect unique new rows (remapped to master order)
$rowsToAppend = [];
$delimiter = detect_delimiter($tmpName);
if (($handle = fopen($tmpName, 'r')) !== false) {
    // Read header with detected delimiter
    $header = fgetcsv($handle, 0, $delimiter);
    if ($header === false) {
        fclose($handle);
        echo json_encode(['success' => false, 'error' => 'Invalid CSV header.']);
        exit;
    }

    $uIdx = build_index($header);

    // Useful index getters for de-dupe
    $uTransIdIdx = find_idx($uIdx, ['Trans ID','Transaction ID','TransactionID','TransID']);
    $uDateIdx    = find_idx($uIdx, ['Trans Date','Transaction Date','Date']);
    $uAssetIdx   = find_idx($uIdx, ['Asset #','Asset','Asset ID','AssetID']);
    $uGallonsIdx = find_idx($uIdx, ['Gallons','Units','Quantity']);

    // Build remap from master->uploaded column index (or -1 if not present)
    $remap = [];
    foreach ($masterHeader as $mhName) {
        // Try exact and common synonyms
        $cands = [$mhName];
        switch (norm_name($mhName)) {
            case 'transid':        $cands = ['Trans ID','Transaction ID','TransactionID','TransID']; break;
            case 'transdate':      $cands = ['Trans Date','Transaction Date','Date']; break;
            case 'transtime':      $cands = ['Trans Time','Transaction Time','Time']; break;
            case 'vehicle':        $cands = ['Vehicle #','Vehicle','Vehicle Number','Unit']; break;
            case 'asset':          $cands = ['Asset #','Asset','Asset ID','AssetID','Unit']; break;
            case 'job':            $cands = ['Job#','Job #','Job']; break;
            case 'region':         $cands = ['Region #','Region']; break;
            case 'vehicledescription': $cands = ['Vehicle Description','Description']; break;
            case 'drivernumber':   $cands = ['Driver #','Driver Number','Driver']; break;
            case 'firstname':      $cands = ['First Name','First']; break;
            case 'lastname':       $cands = ['Last Name','Last']; break;
            case 'level1':         $cands = ['Level 1','Level1']; break;
            case 'level2':         $cands = ['Level 2','Level2']; break;
            case 'department':     $cands = ['Department','Dept']; break;
            case 'pindepartment':  $cands = ['PIN Department','PIN Dept']; break;
            case 'fuelsite':       $cands = ['Fuel Site','Site']; break;
            case 'city':           $cands = ['City']; break;
            case 'state':          $cands = ['State']; break;
            case 'onoff':          $cands = ['On/Off','On Off','OnOff']; break;
            case 'posteddate':     $cands = ['Posted Date','Post Date']; break;
            case 'gallons':        $cands = ['Gallons','Units','Quantity']; break;
            case 'grossdollars':   $cands = ['Gross Dollars','Gross']; break;
            case 'netdollars':     $cands = ['Net Dollars','Net']; break;
            case 'unitcost':       $cands = ['Unit Cost']; break;
            case 'netunitcost':    $cands = ['Net Unit Cost']; break;
            case 'product':        $cands = ['Product']; break;
            case 'pump':           $cands = ['Pump #','Pump']; break;
            case 'enginehours':    $cands = ['Engine Hours','Hours']; break;
            case 'previousenginehours': $cands = ['Previous Engine Hours','Prev Engine Hours']; break;
            case 'odometer2':      $cands = ['Odometer2','Odometer 2']; break;
            case 'previousodometer': $cands = ['Previous Odometer','Prev Odometer']; break;
            case 'network':        $cands = ['Network']; break;
            case 'transtype':      $cands = ['Trans Type','Transaction Type']; break;
            case 'milespergallon': $cands = ['Miles Per Gallon','MPG']; break;
            default:               $cands = [$mhName];
        }
        $remap[] = find_idx($uIdx, $cands);
    }

    while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
        if (!is_array($row) || !array_filter($row)) continue;

        // De-dup check using uploaded indices
        $isDup = false;
        if ($uTransIdIdx >= 0 && isset($row[$uTransIdIdx]) && $row[$uTransIdIdx] !== '') {
            $id = trim((string)$row[$uTransIdIdx]);
            if (isset($existingIds[$id])) { $isDup = true; }
        }
        if (!$isDup) {
            $d = ($uDateIdx    >= 0 && isset($row[$uDateIdx]))    ? trim((string)$row[$uDateIdx])    : '';
            $a = ($uAssetIdx   >= 0 && isset($row[$uAssetIdx]))   ? trim((string)$row[$uAssetIdx])   : '';
            $g = ($uGallonsIdx >= 0 && isset($row[$uGallonsIdx])) ? trim((string)$row[$uGallonsIdx]) : '';
            $key = strtolower($d.'|'.$a.'|'.$g);
            if ($key !== '||' && isset($existingFallback[$key])) { $isDup = true; }
        }
        if ($isDup) continue;

        // Mark as existing to avoid dupes within same upload
        if ($uTransIdIdx >= 0 && isset($row[$uTransIdIdx]) && $row[$uTransIdIdx] !== '') {
            $existingIds[trim((string)$row[$uTransIdIdx])] = true;
        } else {
            $d = ($uDateIdx    >= 0 && isset($row[$uDateIdx]))    ? trim((string)$row[$uDateIdx])    : '';
            $a = ($uAssetIdx   >= 0 && isset($row[$uAssetIdx]))   ? trim((string)$row[$uAssetIdx])   : '';
            $g = ($uGallonsIdx >= 0 && isset($row[$uGallonsIdx])) ? trim((string)$row[$uGallonsIdx]) : '';
            $existingFallback[strtolower($d.'|'.$a.'|'.$g)] = true;
        }

        // Remap row into master order
        $out = [];
        foreach ($remap as $idx) {
            $out[] = ($idx >= 0 && isset($row[$idx])) ? $row[$idx] : '';
        }
        $rowsToAppend[] = $out;
    }
    fclose($handle);
}

if (empty($rowsToAppend)) {
    echo json_encode(['success' => true, 'appendedRows' => 0, 'message' => 'No new rows to append (all duplicates).']);
    exit;
}

// 3) Append to master file with lock, preserving Windows CRLF line endings
// Ensure trailing newline so new rows start on a new line (and recover from any partial write)
ensure_trailing_newline($targetFile);

$master = fopen($targetFile, 'ab'); // binary append
if (!$master) {
    echo json_encode(['success' => false, 'error' => 'Could not open master file for writing.']);
    exit;
}
if (function_exists('flock')) { @flock($master, LOCK_EX); }

foreach ($rowsToAppend as $row) {
    // Build CSV line in-memory to control line endings
    $tmp = fopen('php://temp', 'w+');
    fputcsv($tmp, $row); // default comma separator
    rewind($tmp);
    $csvLine = stream_get_contents($tmp);
    fclose($tmp);
    // Normalize to CRLF and ensure single newline
    $csvLine = rtrim($csvLine, "\r\n");
    fwrite($master, $csvLine . "\r\n");
}

if (function_exists('flock')) { @flock($master, LOCK_UN); }
fclose($master);

echo json_encode(['success' => true, 'appendedRows' => count($rowsToAppend)]);
