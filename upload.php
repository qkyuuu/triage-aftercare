<?php
header("Content-Type: application/json"); 
require_once 'db_config.php';

// Turn off raw error display so it doesn't break JSON
ini_set('display_errors', 0); 
error_reporting(E_ALL);

$input = file_get_contents("php://input");
$request = json_decode($input, true);

if (!$request) {
    echo json_encode(["status" => "error", "message" => "Invalid JSON input received by PHP."]);
    exit;
}

$dataRows = $request['data'] ?? [];
$insertedCount = 0;
$debugError = "";

foreach ($dataRows as $i => $row) {
    $uID = $row['Universal Message ID'] ?? '';
    if (empty($uID)) continue;

    // Hardcoded simple query to test if the connection/table is the problem
    $sql = "INSERT INTO triage_uploads (universal_message_id, region) VALUES (?, ?)";
    $params = [$uID, $request['region']];

    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt) {
        $insertedCount++;
    } else {
        // We catch the error and convert it to a string immediately
        $rawErrors = sqlsrv_errors();
        $debugError = "Row $i Error: " . ($rawErrors[0]['message'] ?? 'Unknown SQL Error');
        break; // Stop at the first error so we can read it
    }
}

echo json_encode([
    "status" => $debugError ? "error" : "success",
    "message" => $debugError ?: "Successfully uploaded $insertedCount rows",
    "inserted" => $insertedCount
]);
