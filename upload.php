<?php
// We keep it as plain text so the browser just displays the errors as they happen
header("Content-Type: text/plain"); 

require_once 'db_config.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

$input = file_get_contents("php://input");
$request = json_decode($input, true);

// --- COMMENTED OUT FOR DEBUGGING ---
/*
if (!$request || !isset($request['data'])) {
    die("No data received\nRaw input:\n" . $input);
}
*/

$region = $request['region'] ?? 'N/A';
$date = $request['date'] ?? 'N/A';
$dataRows = $request['data'] ?? [];

$insertedCount = 0;
$skippedCount = 0;

foreach ($dataRows as $i => $row) {
    // Mapping your columns
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

    if (empty($uID)) continue;

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

    // Removed the '@' symbol so errors aren't suppressed
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt) {
        $insertedCount++;
    } else {
        // This is what was causing the "Unexpected token A" error.
        // It will now show clearly in your console/network tab.
        echo "\n--- SQL ERROR ON ROW $i ---\n";
        var_dump(sqlsrv_errors()); 
    }
}

echo "\n--- SUMMARY ---\n";
echo "Processed " . count($dataRows) . " rows.\n";
echo "Successfully Added: $insertedCount\n";
