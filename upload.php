<?php
header("Content-Type: application/json");
require_once 'db_config.php';

// Disable warnings/notices that might break JSON output
error_reporting(E_ERROR | E_PARSE);

$input = file_get_contents("php://input");
$request = json_decode($input, true);

if (!$request || !isset($request['data'])) {
    echo json_encode(["status" => "error", "error" => "No data received"]);
    exit;
}

$region = $request['region'];
$date = $request['date'];
$dataRows = $request['data'];

$insertedCount = 0;
$skippedCount = 0;
$otherErrors = [];

foreach ($dataRows as $row) {
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

    $stmt = @sqlsrv_query($conn, $sql, $params); // suppress warnings

    if ($stmt) {
        $insertedCount++;
    } else {
        $errorInfo = sqlsrv_errors() ?: [];
        $isDuplicate = false;
        foreach ($errorInfo as $err) {
            if (strpos(strtoupper($err['message']), 'UNIQUE') !== false) {
                $isDuplicate = true;
                break;
            }
        }
        if ($isDuplicate) {
            $skippedCount++;
        } else {
            $otherErrors[] = $errorInfo;
        }
    }
}

$response = ["status" => "success"];
$msgParts = [];
if ($insertedCount > 0) $msgParts[] = "$insertedCount new records added";
if ($skippedCount > 0) $msgParts[] = "$skippedCount duplicates skipped";

if (!empty($otherErrors)) {
    $response = ["status" => "error", "error" => json_encode($otherErrors)];
} else {
    $response["message"] = implode(". ", $msgParts) ?: "No records processed.";
}

echo json_encode($response);
exit;