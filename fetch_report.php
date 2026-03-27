<?php
header("Content-Type: application/json");
include 'db_config.php';

$region = $_GET['region'] ?? '';
$start = $_GET['start'] ?? '';
$end = $_GET['end'] ?? '';

// Pad dates for SQL BETWEEN if only Month is selected
$start_query = (strlen($start) == 7) ? $start . "-01" : $start;
$end_query = (strlen($end) == 7) ? $end . "-31" : $end;

// NOTE: I removed an invisible non-breaking space after SELECT that often causes SQL errors
$sql = "SELECT 
    inbound_count AS [Inbound Count (SUM)], 
    inbound_message_date AS [Inbound Message Date], 
    routing_stage AS [Routing Stage (in) (Message)], 
    global_area AS [Country (in) (Message)], 
    macro_tracker AS [Macro Tracker (Message)], 
    account_handle AS [Account],
    social_network AS [Social Network], 
    message_type AS [Message Type],
    sentiment AS [Sentiment]
FROM triage_uploads 
WHERE region = ? AND inbound_message_date BETWEEN ? AND ?";

$params = [$region, $start_query, $end_query];
$stmt = sqlsrv_query($conn, $sql, $params);

if ($stmt === false) {
    // Return actual error details for debugging
    die(json_encode(["error" => sqlsrv_errors()]));
}

$data = [];
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    // CRITICAL: Convert DateTime object to string for JSON
    if ($row['Inbound Message Date'] instanceof DateTime) {
        $row['Inbound Message Date'] = $row['Inbound Message Date']->format('Y-m-d');
    }
    $data[] = $row;
}

echo json_encode($data);
?>
