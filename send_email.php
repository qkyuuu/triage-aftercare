<?php
header("Content-Type: application/json");

// Turn off display_errors so they don't leak into the JSON response
ini_set('display_errors', 0);
error_reporting(E_ALL);

$input = json_decode(file_get_contents("php://input"), true);

if (!$input || !isset($input['metrics'])) {
    echo json_encode(["success" => false, "message" => "Invalid data received"]);
    exit;
}

$metrics = $input['metrics'];
$dateRange = $input['dateRange'] ?? 'Report';

// Using ?? 0 and ?? [] ensures the script doesn't crash if a value is missing
$total       = $metrics['total'] ?? 0;
$responded   = $metrics['responded'] ?? 0;
$closed      = $metrics['nonActionable'] ?? 0; // Check if this should be 'nonActionable' or 'non_actionable'
$forResponse = $metrics['forResponse'] ?? 0;
$journey     = $metrics['journey'] ?? ['Retention'=>0, 'Fans'=>0, 'Usage'=>0, 'Prospecting'=>0];
$sentiments  = $metrics['sentiments'] ?? ['Positive'=>0, 'Negative'=>0, 'Neutral'=>0];

// Calculate Percentages safely
$respondedPct = $total > 0 ? round(($responded / $total) * 100, 1) : 0;
$posPct = $total > 0 ? round((($sentiments['Positive'] ?? 0) / $total) * 100) : 0;
$negPct = $total > 0 ? round((($sentiments['Negative'] ?? 0) / $total) * 100) : 0;
$neuPct = $total > 0 ? round((($sentiments['Neutral'] ?? 0) / $total) * 100) : 0;

// Platform HTML logic
$platformHtml = '';
if (isset($metrics['rawData']) && is_array($metrics['rawData'])) {
    $platformCounts = [];
    foreach ($metrics['rawData'] as $row) {
        $p = $row['Social Network'] ?? 'Other';
        $platformCounts[$p] = ($platformCounts[$p] ?? 0) + 1;
    }
    foreach ($platformCounts as $name => $count) {
        $platformHtml .= "<div style='margin-bottom:5px;'>$name: <strong>$count</strong></div>";
    }
}

/* EMAIL BODY TEMPLATE */
// (Keeping your template as is, it looks great for Outlook/HTML mail)
$emailBody = '...'; // Your existing $emailBody code here

/* 🔥 Power Automate Config */
$flowUrl = "https://default10f787270c1845afb9ee97e94fd5bc.d8.environment.api.powerplatform.com:443/powerautomate/automations/direct/workflows/babe04e0152246ce8b282f17605d9fa5/triggers/manual/paths/invoke?api-version=1";

$data = [
    "ToEmail" => "v-jopastoral@microsoft.com",
    "SubjectText" => "Social Triage Report: " . $dateRange,
    "BodyText" => $emailBody
];

$ch = curl_init($flowUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

// IMPORTANT: This allows the request to succeed even if your server has SSL issues
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    echo json_encode(["success" => false, "message" => "CURL Error: " . $curlError]);
} else {
    echo json_encode([
        "success" => $httpCode >= 200 && $httpCode < 300,
        "http_code" => $httpCode
    ]);
}
