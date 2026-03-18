<?php
header("Content-Type: application/json"); // Keep it JSON so script.js doesn't crash

require_once 'db_config.php';

error_reporting(E_ALL);
ini_set('display_errors', 0); // Hide raw PHP errors from breaking the JSON

$input = file_get_contents("php://input");
$request = json_decode($input, true);

if (!$request || !isset($request['data'])) {
    echo json_encode(["status" => "error", "message" => "No data received"]);
    exit;
}

$region = $request['region'];
$date = $request['date'];
$dataRows = $request['data'];

$insertedCount = 0;
$errors = [];

foreach ($dataRows as $i => $row) {
    // Basic mapping
    $inbound   = isset($row['Inbound Count (SUM)']) ? (int)$row['Inbound Count (SUM)'] : 0;
    $msgDate   = $row['Inbound Message Date'] ?? null;
    $uID       = $row['Universal Message ID'] ?? '';

    if (empty($uID)) continue;

    $sql = "INSERT INTO triage_uploads (
        region, upload_date, inbound_message_date, inbound_count,
        routing_stage, global_area, macro_tracker, account_handle,
        message_type, social_network, sentiment, universal_message_id
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $params = [
        $region, $date, $msgDate, $inbound,
        $row['Routing Stage (in) (Message)'] ?? '',
        $row['Country (in) (Message)'] ?? '',
        $row['Macro Tracker (Message)'] ?? '',
        $row['Account'] ?? '',
        $row['Message Type'] ?? '',
        $row['Social Network'] ?? '',
        $row['Sentiment'] ?? '',
        $uID
    ];

    // REMOVED the '@' so we can see the error
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt) {
        $insertedCount++;
    } else {
        // Capture the EXACT error from SQL Server
        $errors[] = [
            "row" => $i,
            "details" => sqlsrv_errors()
        ];
        // Break after the first error so we don't flood the screen
        break; 
    }
}

echo json_encode([
    "status" => count($errors) > 0 ? "error" : "success",
    "inserted" => $insertedCount,
    "sql_errors" => $errors
]);
