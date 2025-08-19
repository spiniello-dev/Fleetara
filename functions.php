<?php
require_once __DIR__ . '/vendor/autoload.php';
if (class_exists('Dotenv\\Dotenv')) { $dotenv = Dotenv\Dotenv::createImmutable(__DIR__); $dotenv->safeLoad(); }

function getFleetioDVIRSubmissions($date = null) {
    $apiKey2 = $_ENV['FLEETIO_API_KEY'] ?? '';
    $baseUrl = "https://secure.fleetio.com/api/v1/submitted_inspection_forms";

    $currentDate = isset($date) ? $date : date('Y-m-d');
    $start = $currentDate . 'T00:00:00-04:00';
    $end = date('Y-m-d', strtotime($currentDate . ' +1 day')) . 'T00:00:00-04:00';

    $allRecords = [];
    $nextCursor = null;

    do {
        $url = "$baseUrl?filter[inspection_form_id][eq]=189480&filter[submitted_at][gte]=$start&filter[submitted_at][lte]=$end&limit=50";
        if ($nextCursor) {
            $url .= "&start_cursor=$nextCursor";
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                "Authorization: Token $apiKey2",
                "Account-Token: " . ($_ENV['FLEETIO_ACCOUNT_TOKEN'] ?? ''),
                "X-Api-Version: 2024-06-30",
                "Accept: application/json"
            ],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false
        ]);
        $response = curl_exec($ch);
        curl_close($ch);

        $json = json_decode($response, true);
        $allRecords = array_merge($allRecords, isset($json['records']) ? $json['records'] : []);
        $nextCursor = isset($json['next_cursor']) ? $json['next_cursor'] : null;

    } while (!empty($nextCursor));

    return $allRecords;
}

function getSamsaraAssets() {
    $apiKey = $_ENV['SAMSARA_API_KEY'] ?? '';
    $url = 'https://api.samsara.com/fleet/vehicles?limit=512';

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer $apiKey",
            "Accept: application/json"
        ],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false
    ]);
    $response = curl_exec($ch);
    curl_close($ch);

    $data = json_decode($response, true);
    return $data;
}

function getSamsaraVehiclesMoved($assets, $date = null) {
    $apiKey = $_ENV['SAMSARA_API_KEY'] ?? '';
    $currentDate = isset($date) ? $date : date('Y-m-d');
    $start = $currentDate . 'T00:00:00-04:00';
    $end = date('Y-m-d', strtotime($currentDate . ' +1 day')) . 'T00:00:00-04:00';

    $vehicleIds = array_column($assets, 'id');
    $ids = implode(',', $vehicleIds);

    // Initial API URL for stats/history with pagination handling
    $url = "https://api.samsara.com/fleet/vehicles/stats/history?startTime=$start&endTime=$end&vehicleIds=$ids&types=engineStates";
    
    // To store all the data across pages
    $allInfo = [];
    $nextCursor = null;

    do {
        // Append pagination cursor if available
        $paginationUrl = $nextCursor ? $url . "&after=$nextCursor" : $url;
        
        // Initialize cURL
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $paginationUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer $apiKey",
                "Accept: application/json"
            ],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false
        ]);
        $response = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($response, true);

         // Merge current page's data if 'data' exists
        if (isset($data['data'])) {
            $allInfo = array_merge($allInfo, $data['data']);
        }
        
        // Check for next page cursor if it exists
        if (isset($data['pagination']['endCursor'])) {
            $nextCursor = $data['pagination']['endCursor'];
        } else {
            $nextCursor = null;
        }

    } while ($nextCursor); // Keep fetching until no nextCursor is present

    // Now process the data to count vehicles moved
    $vehiclesMoved = 0;
    if (isset($assets['data'])) {
        foreach ($assets['data'] as $index => $asset) {
            if (isset($allInfo)) {
                foreach ($allInfo as $state) {
                    if ($state['name'] === $asset['name']) {
                        foreach ($state['engineStates'] as $engineStateEntry) {
                            // Ensure 'time' and 'value' exist in the entry
                            if (isset($engineStateEntry['time'], $engineStateEntry['value'])) {
                                // Check if this engine start time is after or equal to the submitted_at time
                                if ($engineStateEntry['value'] === 'On') {
                                    $vehiclesMoved++;
                                    break 2;
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    return $vehiclesMoved;
}
