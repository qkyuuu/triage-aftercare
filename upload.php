<?php
header("Content-Type: application/json");
require_once 'db_config.php';

// Prevent raw PHP errors from breaking the JSON format
error_reporting(E_ALL);
ini_set('display_errors', 0);

$input = file_get_contents("php://input");
$request = json_decode($input, true);

if (!$request || !isset($request['data'])) {
    die(json_encode([
        "status" => "error", 
        "message" => "No data received or invalid JSON format."
    ]));
}

$region = $request['region'];
$date = $request['date']; 
$dataRows = $request['data'];

$insertedCount = 0;

// Added '$i =>' to track the row number for better error reporting
foreach ($dataRows as $i => $row) {

    // Mapping Excel columns to PHP variables
    $inbound   = isset($row['Inbound Count (SUM)']) ? (int)$row['Inbound Count (SUM)'] : 0;
    $msgDate   = $row['Inbound Message Date'] ?? null; 
    $stage     = $row['Routing Stage (in) (Message)'] ?? '';
    $area      = $row['Country (in) (Message)'] ?? ''; 
    $macro     = $row['Macro Tracker (Message)'] ?? ''; 
    $account   = $row['Account'] ?? '';
    $msgType   = $row['Message Type'] ?? '';
    $network   = $row['Social Network'] ?? '';
    $sentiment = $row['Sentiment'] ?? '';
    $uID       = $row['Universal Message ID'] ?? '';

    // Only process if we have a Unique ID
    if (!empty($uID)) {

        $sql = "INSERT INTO triage_uploads (
            region, upload_date, inbound_message_date, inbound_count,
            routing_stage, global_area, macro_tracker, account_handle,
            message_type, social_network, sentiment, universal_message_id
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $params = [
            $region, $date, $msgDate, $inbound,
            $stage, $area, $macro, $account,
            $msgType, $network, $sentiment, $uID
        ];

        $stmt = sqlsrv_query($conn, $sql, $params);

        if ($stmt) {
            $insertedCount++;
        } else {
            // If the SQL "Engine" fails, we send a JSON error instead of a raw Array
            $sqlErrors = sqlsrv_errors();
            $cleanError = isset($sqlErrors[0]['message']) ? $sqlErrors[0]['message'] : 'Unknown SQL Error';
            
            die(json_encode([
                "status" => "error", 
                "message" => "Error on row " . ($i + 1) . ": " . $cleanError
            ]));
        }
    }
}

// Final Success Response
echo json_encode([
    "status" => "success", 
    "message" => "Processed " . count($dataRows) . " rows. Successfully added $insertedCount records."
]);
?>
