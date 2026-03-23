<?php
// 1. Setup & Error Handling
ob_clean(); // Clear any accidental whitespace or echo outputs
header("Content-Type: application/json");

// Disable error display to prevent HTML error messages from breaking JSON response
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Increase memory limit for processing large Base64 image strings
ini_set('memory_limit', '256M');

// 2. Read and Decode the Input
$rawInput = file_get_contents("php://input");
$input = json_decode($rawInput, true);

// Check if JSON decoding failed
if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode([
        "success" => false, 
        "message" => "JSON Decode Error: " . json_last_error_msg(),
        "debug_info" => "Received " . strlen($rawInput) . " bytes"
    ]);
    exit;
}

// Verify the 'image' key exists (sent from html2canvas in your JS)
if (!$input || !isset($input['image'])) {
    echo json_encode([
        "success" => false, 
        "message" => "No image data received",
        "received_keys" => array_keys((array)$input)
    ]);
    exit;
}

$imageData = $input['image']; // The Base64 string
$dateRange = $input['dateRange'] ?? 'Latest Report';
$recipientEmail = $input['recipient'] ?? 'v-jopastoral@microsoft.com';

// 3. Construct the Email Body (HTML)
$emailBody = "
<div style='font-family: Arial, sans-serif; padding: 30px; text-align: center;'>
    <div style='max-width: 850px; margin: 0 auto; background: #ffffff; padding: 25px; border-radius: 12px; border: 1px solid #e5e5e7; box-shadow: 0 4px 10px rgba(0,0,0,0.05);'>
        
        // <div style='padding-bottom:15px; margin-bottom: 20px; border-bottom: 2px solid #071952; text-align: left;'>
        //     <h2 style='color: #071952; margin: 0; font-size: 24px;'>Social Triage After-care Service</h2>
        //     <div style='color: #888; font-weight: bold; font-size: 14px; margin-top: 5px;'>Performance Report: $dateRange</div>
        // </div>

        <div style='margin-bottom: 25px;'>
            <img src='$imageData' alt='Dashboard Report' style='width: 100%; max-width: 100%; height: auto; display: block; border-radius: 8px; border: 1px solid #ddd;'>
        </div>

        <div style='border-top: 1px solid #eee; padding-top: 15px; color: #999; font-size: 12px; text-align: left;'>
            <p>This is an automated visual capture of the Social Triage Dashboard. To interact with the live data, please visit the portal.</p>
            <p style='margin-top: 5px;'>&copy; 2026 Social Triage Analytics</p>
        </div>
    </div>
</div>";

// 4. Power Automate API Configuration
$flowUrl = "https://default10f787270c1845afb9ee97e94fd5bc.d8.environment.api.powerplatform.com:443/powerautomate/automations/direct/workflows/babe04e0152246ce8b282f17605d9fa5/triggers/manual/paths/invoke?api-version=1&sp=%2Ftriggers%2Fmanual%2Frun&sv=1.0&sig=x51ZUJuSWT1NSbpct3opH1wCkPIJDHfin5zX7L-dpfA";

$payload = [
    "ToEmail" => $recipientEmail, // UPDATED: Now uses the dynamic variable
    "SubjectText" => "Social Triage Report: " . $dateRange,
    "BodyText" => $emailBody
];

$jsonPayload = json_encode($payload);

// 5. Execute cURL Request
$ch = curl_init($flowUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Content-Length: ' . strlen($jsonPayload)
]);

// Crucial for Azure/Corporate environments with strict SSL
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
curl_setopt($ch, CURLOPT_TIMEOUT, 30); // Prevent hanging on large payloads

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

// 6. Final Response back to JavaScript
if ($httpCode >= 200 && $httpCode < 300) {
    echo json_encode([
        "success" => true,
        "message" => "Email sent successfully via Power Automate",
        "status_code" => $httpCode
    ]);
} else {
    echo json_encode([
        "success" => false,
        "message" => "Power Automate Error (Code: $httpCode)",
        "curl_error" => $curlError,
        "flow_response" => $response
    ]);
}
?>
