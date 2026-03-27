<?php
header("Content-Type: application/json");
include 'db_config.php';

$region = $_GET['region'] ?? '';
$start = $_GET['start'] ?? '';
$end = $_GET['end'] ?? '';

// FIX: If the date is YYYY-MM, convert to YYYY-MM-DD
if (strlen($start) === 7) { 
    $start_query = $start . "-01"; 
    // This creates a date for the end of the month
    $end_query = date("Y-m-t", strtotime($start_query)); 
} else {
    $start_query = $start;
    $end_query = $end;
}

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
    die(json_encode(["error" => sqlsrv_errors()]));
}

$data = [];
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    // FIX: Convert PHP DateTime objects to strings so JSON doesn't break
    if ($row['Inbound Message Date'] instanceof DateTime) {
        $row['Inbound Message Date'] = $row['Inbound Message Date']->format('Y-m-d');
    }
    $data[] = $row;
}

echo json_encode($data);
?>
