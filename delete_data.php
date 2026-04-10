<?php
header('Content-Type: application/json');
require_once 'db_config.php'; 

// SET YOUR ADMIN PASSWORD HERE
$ADMIN_PASSWORD = "SDHManila123"; 

$input = json_decode(file_get_contents('php://input'), true);
$region = $input['region'] ?? '';
$dateRange = $input['dateRange'] ?? '';
$userPassword = $input['password'] ?? '';

if ($userPassword !== $ADMIN_PASSWORD) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized: Incorrect password.']);
    exit;
}

if (empty($dateRange)) {
    echo json_encode(['success' => false, 'message' => 'No date range provided.']);
    exit;
}

// PARSE DATE RANGE
$dates = explode(" to ", $dateRange);
$startDate = $dates[0];
$endDate = isset($dates[1]) ? $dates[1] : $dates[0];

try {
    // 1. UPDATED SQL: Using the real column names from your screenshot
    // Table name assumed 'dbo.triage_table' - change if necessary
    if ($region === "ALL") {
        $sql = "DELETE FROM [dbo].[triage_uploads] 
                WHERE [inbound_date] BETWEEN ? AND ?";
        $params = array($startDate, $endDate);
    } else {
        $sql = "DELETE FROM [dbo].[triage_uploads] 
                WHERE [region] = ? 
                AND [inbound_date] BETWEEN ? AND ?";
        $params = array($region, $startDate, $endDate);
    }

    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt === false) {
        $errors = sqlsrv_errors();
        throw new Exception($errors[0]['message']);
    }

    $rowsAffected = sqlsrv_rows_affected($stmt);
    
    echo json_encode([
        'success' => true, 
        'message' => "Successfully cleared $rowsAffected rows."
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database Error: ' . $e->getMessage()]);
} finally {
    sqlsrv_close($conn);
}
?>
