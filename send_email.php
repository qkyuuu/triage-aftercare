<?php
header("Content-Type: application/json");

// 1. Prevent random PHP errors from ruining the JSON output
ini_set('display_errors', 0);
error_reporting(E_ALL);

$input = json_decode(file_get_contents("php://input"), true);

if (!$input || !isset($input['metrics'])) {
    echo json_encode(["success" => false, "message" => "Invalid data received"]);
    exit;
}

$metrics = $input['metrics'];
$dateRange = $input['dateRange'] ?? 'Latest Report';

// 2. Safely extract values (using ?? 0 prevents "Undefined Index" crashes)
$total       = $metrics['total'] ?? 0;
$responded   = $metrics['responded'] ?? 0;
$closed      = $metrics['nonActionable'] ?? 0;
$forResponse = $metrics['forResponse'] ?? 0;

// Sub-arrays
$journey    = $metrics['journey'] ?? [];
$sentiments = $metrics['sentiments'] ?? [];

// Calculate percentages safely to avoid "Division by zero"
$respondedPct = $total > 0 ? round(($responded / $total) * 100, 1) : 0;

// 3. Build Platform List
$platformHtml = '';
if (isset($metrics['rawData']) && is_array($metrics['rawData'])) {
    $counts = array_count_values(array_column($metrics['rawData'], 'Social Network'));
    foreach ($counts as $name => $count) {
        $platformHtml .= "<div style='margin-bottom:4px;'>$name: <strong>$count</strong></div>";
    }
}

// 4. Construct the Email Body (Keep your HTML table structure here)
$emailBody = "
<div style='font-family: Arial, sans-serif; color: #333;'>
    <h2 style='color: #071952;'>Social Triage Report</h2>
    <p><strong>Date Range:</strong> $dateRange</p>
    <hr>
    <table width='100%' style='border-collapse: collapse;'>
        <tr>
            <td style='padding: 10px; border: 1px solid #eee;'>Total Sent: <strong>$total</strong></td>
            <td style='padding: 10px; border: 1px solid #eee;'>Responded: <strong>$responded ($respondedPct%)</strong></td>
        </tr>
    </table>
    <h4 style='color: #071952;'>Platforms</h4>
    $platformHtml
</div>";

// 5. 🔥 Power Automate API Call
$flowUrl = "https://default10f787270c1845afb9ee97e94fd5bc.d8.environment.api.powerplatform.com:443/powerautomate/automations/direct/workflows/babe04e0152246ce8b282f17605d9fa5/triggers/manual/paths/invoke?api-version=1&sp=%2Ftriggers%2Fmanual%2Frun&sv=1.0&sig=x51ZUJuSWT1NSbpct3opH1wCkPIJDHfin5zX7L-dpfA";

$payload = [
    "ToEmail" => "v-jopastoral@microsoft.com",
    "SubjectText" => "Social Triage Report: " . $dateRange,
    "BodyText" => $emailBody
];

$ch = curl_init($flowUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Content-Length: ' . strlen(json_encode($payload))
]);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Crucial for Azure/Local environments

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// 6. Return response to Dashboard
echo json_encode([
    "success" => ($httpCode >= 200 && $httpCode < 300),
    "status_code" => $httpCode,
    "debug_msg" => "Power Automate returned code: $httpCode"
]);
