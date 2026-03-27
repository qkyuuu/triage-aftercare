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

$region = $request['region'] ?? 'Unknown';
// Use provided date or fallback to today's date for the upload timestamp
$uploadDate = $request['date'] ?? date('Y-m-d'); 
$dataRows = $request['data'];

$insertedCount = 0;
$skippedCount = 0;

foreach ($dataRows as $i => $row) {

    // 1. Mapping Excel/CSV columns to PHP variables
    // We ensure types are cast correctly to avoid SQL Server confusion
    $inbound   = isset($row['Inbound Count (SUM)']) ? (int)$row['Inbound Count (SUM)'] : 0;
    $msgDate   = !empty($row['Inbound Message Date']) ? $row['Inbound Message Date'] : null; 
    $stage     = $row['Routing Stage (in) (Message)'] ?? '';
    $area      = $row['Country (in) (Message)'] ?? ''; 
    $macro     = $row['Macro Tracker (Message)'] ?? ''; 
    $account   = $row['Account'] ?? '';
    $msgType   = $row['Message Type'] ?? '';
    $network   = $row['Social Network'] ?? '';
    $sentiment = $row['Sentiment'] ?? '';
    $uID       = $row['Universal Message ID'] ?? '';

    // Only process if we have a Unique ID to prevent empty row errors
    if (!empty($uID)) {

        // 2. SQL Logic: 13 Total Question Marks
        $sql = "IF NOT EXISTS (SELECT 1 FROM triage_uploads WHERE universal_message_id = ?)
                BEGIN
                    INSERT INTO triage_uploads (
                        region, 
                        upload_date, 
                        inbound_message_date, 
                        inbound_count,
                        routing_stage, 
                        global_area, 
                        macro_tracker, 
                        account_handle,
                        message_type, 
                        social_network, 
                        sentiment, 
                        universal_message_id
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                END";

        // 3. Parameters must perfectly align with the order of the ? placeholders above
        $params = [
            $uID,         // 1.  Check for existing ID
            $region,      // 2.  region
            $uploadDate,  // 3.  upload_date
            $msgDate,     // 4.  inbound_message_date (The critical date string)
            $inbound,     // 5.  inbound_count
            $stage,       // 6.  routing_stage
            $area,        // 7.  global_area
            $macro,       // 8.  macro_tracker
            $account,     // 9.  account_handle
            $msgType,     // 10. message_type
            $network,     // 11. social_network
            $sentiment,   // 12. sentiment
            $uID          // 13. universal_message_id (Final insertion)
        ];

        $stmt = sqlsrv_query($conn, $sql, $params);

        if ($stmt) {
            // Check if an actual INSERT happened or if it was skipped by the IF NOT EXISTS logic
            $rowsAffected = sqlsrv_rows_affected($stmt);
            if ($rowsAffected > 0) {
                $insertedCount++;
            } else {
                $skippedCount++;
            }
        } else {
            $sqlErrors = sqlsrv_errors();
            $cleanError = isset($sqlErrors[0]['message']) ? $sqlErrors[0]['message'] : 'Unknown SQL Error';
            
            die(json_encode([
                "status" => "error", 
                "message" => "Error on row " . ($i + 1) . ": " . $cleanError
            ]));
        }
    }
}

// 4. Return final JSON response
echo json_encode([
    "status" => "success", 
    "message" => "$skippedCount records already exist. $insertedCount new records added."
]);
?>
