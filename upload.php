<?php
header("Content-Type: application/json");
require_once 'db_config.php';

$input = file_get_contents("php://input");
$request = json_decode($input, true);

if (!$request || !isset($request['data'])) {
    die(json_encode(["status" => "error", "error" => "No data received"]));
}

$region = $request['region'];
$date = $request['date']; 
$dataRows = $request['data'];

$insertedCount = 0;

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
    // This converts the error to a string so it doesn't crash the JSON parser
    $errors = sqlsrv_errors();
    die(json_encode([
        "status" => "error", 
        "message" => "SQL Error on row $i: " . $errors[0]['message']
    ]));
}
    }
}

echo json_encode([
    "status" => "success", 
    "message" => "Processed " . count($dataRows) . " rows. Added $insertedCount records."
]);
?>
