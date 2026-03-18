<?php
header("Content-Type: text/plain"); // Change from application/json to plain text

require_once 'db_config.php';

// Show all errors for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

$input = file_get_contents("php://input");
$request = json_decode($input, true);

if (!$request || !isset($request['data'])) {
    die("No data received\nRaw input:\n" . $input);
}

$region = $request['region'];
$date = $request['date'];
$dataRows = $request['data'];

$insertedCount = 0;
$skippedCount = 0;
$otherErrors = [];

foreach ($dataRows as $i => $row) {
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

    $stmt = @sqlsrv_query($conn, $sql, $params);

    if ($stmt) {
        $insertedCount++;
    } else {
        echo "\n--- ERROR on row $i ---\n";
        print_r(sqlsrv_errors());
    }
}

echo "\nProcessed " . count($dataRows) . " rows. Added $insertedCount records.\n";
echo "Duplicates/skipped: $skippedCount\n";
