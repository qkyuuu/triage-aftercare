<?php
header("Content-Type: application/json");
include 'db_config.php';

$region = $_GET['region'] ?? '';
$start = $_GET['start'] ?? '';
$end = $_GET['end'] ?? '';

// 1. Correct Date Padding Logic
// If user picks a month (7 chars: YYYY-MM), we force the range to be the full month
if (strlen($start) === 7) { 
    $start_query = $start . "-01"; 
    // Use the $end variable to find the last day of THAT specific month
    $end_query = date("Y-m-t", strtotime($end . "-01")); 
} else {
    $start_query = $start;
    $end_query = $end;
}

// 2. Cleaned SQL Query (Removed invisible formatting characters)
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
    // This will help you see the exact SQL error in the browser console
    die(json_encode(["error" => sqlsrv_errors()]));
}

$data = [];
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    // 3. Format Date for JavaScript
    // JavaScript's Date() or Chart.js cannot read PHP/SQL Date Objects directly
    if (isset($row['Inbound Message Date']) && $row['Inbound Message Date'] instanceof DateTime) {
        $row['Inbound Message Date'] = $row['Inbound Message Date']->format('Y-m-d');
    }
    $data[] = $row;
}

echo json_encode($data);
?>
