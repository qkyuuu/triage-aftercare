<?php
header("Content-Type: application/json");

// 1. Prevent random PHP errors from ruining the JSON output
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Read the JSON input from your JavaScript fetch
$input = json_decode(file_get_contents("php://input"), true);

if (!$input || !isset($input['image'])) {
    echo json_encode(["success" => false, "message" => "No image data received"]);
    exit;
}

$imageData = $input['image']; // This is the Base64 string from html2canvas
$dateRange = $input['dateRange'] ?? 'Latest Report';

// 2. Construct the Email Body with the Embedded Image
// We wrap the image in a container that matches your dashboard's look
$emailBody = "
<div style='font-family: Arial, sans-serif; background-color: #f5f6f8; padding: 30px; text-align: center;'>
    <div style='max-width: 850px; margin: 0 auto; background: #ffffff; padding: 20px; border-radius: 12px; border: 1px solid #e5e5e7; box-shadow: 0 4px 6px rgba(0,0,0,0.05);'>
        
        <div style='padding-bottom:10px; margin-bottom: 20px; border-bottom: 2px solid #071952; text-align: left;'>
            <h2 style='color: #071952; margin: 0;'>Social Triage After-care Service</h2>
            <div style='color: #888; font-weight: bold; font-size: 14px; margin-top: 5px;'>$dateRange</div>
        </div>

        <div style='margin-bottom: 20px;'>
            <img src='$imageData' alt='Dashboard Report' style='width: 100%; height: auto; display: block; border-radius: 8px; border: 1px solid #eee;'>
        </div>

        <div style='border-top: 1px solid #eee; padding-top: 15px; color: #999; font-size: 11px; text-align: left;'>
            This report was automatically generated and captured from the Social Triage Dashboard.
        </div>
    </div>
</div>";

// 3. 🔥 Power Automate API Call
$flowUrl = "https://default10f787270c1845afb9ee97e94fd5bc.d8.environment.api.powerplatform.com:443/powerautomate/automations/direct/workflows/babe04e0152246ce8b282f17605d9fa5/triggers/manual/paths/invoke?api-version=1&sp=%2Ftriggers%2Fmanual%2Frun&sv=1.0&sig=x51ZUJuSWT1NSbpct3opH1wCkPIJDHfin5zX7L-dpfA";

$payload = [
    "ToEmail" => "v-jopastoral@microsoft.com",
    "SubjectText" => "Visual Triage Report: " . $dateRange,
    "BodyText" => $emailBody
];

$jsonPayload = json_encode($payload);

$ch = curl_init($flowUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Content-Length: ' . strlen($jsonPayload)
]);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// 4. Return response to Dashboard
echo json_encode([
    "success" => ($httpCode >= 200 && $httpCode < 300),
    "status_code" => $httpCode,
    "debug_msg" => "Power Automate returned code: $httpCode"
]);
