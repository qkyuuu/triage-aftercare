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

// 3. Build Dynamic Data Lists
$platformHtml = "";
if (isset($metrics['rawData'])) {
    $counts = array_count_values(array_column($metrics['rawData'], 'Social Network'));
    foreach ($counts as $name => $count) {
        $platformHtml .= "<div style='margin-bottom:5px; font-size:13px;'>$name: <strong>$count</strong></div>";
    }
}

$areaHtml = ""; // Mimicking the Area Chart with simple horizontal bars
if (isset($metrics['areaData'])) {
    foreach ($metrics['areaData'] as $area => $val) {
        $areaPct = $total > 0 ? ($val / $total) * 100 : 0;
        $areaHtml .= "
            <div style='margin-bottom:8px;'>
                <div style='font-size:11px; color:#666;'>$area ($val)</div>
                <div style='background:#eee; height:12px; border-radius:3px;'>
                    <div style='background:#071952; height:12px; width:{$areaPct}%; border-radius:3px;'></div>
                </div>
            </div>";
    }
}

// 4. Construct the Full Email Body
$emailBody = "
<div style='font-family: Arial, sans-serif; background-color: #f5f6f8; padding: 30px;'>
    <div style='max-width: 800px; margin: 0 auto; background: #ffffff; padding: 25px; border-radius: 12px; border: 1px solid #e5e5e7;'>
        
        <div style='padding-bottom:10px; margin-bottom: 10px; border-bottom: 2px solid #071952;'>
            <h2 style='color: #071952; margin: 0;'>Social Triage After-care Service</h2>
        </div>
        <div style='color: #888; font-weight: bold; margin-bottom: 25px;'>$dateRange</div>

        <table width='100%' cellpadding='0' cellspacing='0'>
            <tr>
                <td width='240' valign='top' style='border: 1px solid #ccc; border-radius: 15px; padding: 20px; text-align: center;'>
                    <div style='color: #6c757d; font-weight: bold; font-size: 11px; text-transform: uppercase;'>RSCC Performance</div>
                    <hr style='border:0; border-top:1px solid #eee; margin:15px 0;'>
                    
                    <div style='font-size: 11px; color: #888;'>Total Sent to SCC</div>
                    <div style='font-size: 52px; font-weight: bold; color: #071952;'>$total</div>
                    <hr style='border:0; border-top:1px solid #eee; margin:15px 0;'>
                    
                    <div style='font-size: 11px; color: #888;'>Total Responded</div>
                    <div style='font-size: 42px; font-weight: bold; color: #071952;'>$responded</div>
                    <div style='color: #28a745; font-size: 14px; font-weight:bold;'>$respondedPct%</div>
                    <hr style='border:0; border-top:1px solid #eee; margin:15px 0;'>
                    
                    <div style='font-size: 11px; color: #888;'>Total Closed</div>
                    <div style='font-size: 42px; font-weight: bold; color: #071952;'>$closed</div>
                    <hr style='border:0; border-top:1px solid #eee; margin:15px 0;'>
                    
                    <div style='font-size: 11px; color: #888;'>For Response</div>
                    <div style='font-size: 42px; font-weight: bold; color: #071952;'>$forResponse</div>
                </td>

                <td width='25'>&nbsp;</td>

                <td valign='top'>
                    <div style='border: 1px solid #ccc; border-radius: 12px; padding: 15px; margin-bottom: 15px;'>
                        <div style='font-weight: bold; font-size: 10px; color: #888; text-transform: uppercase; margin-bottom: 10px;'>Performance by Area (Country)</div>
                        $areaHtml
                    </div>

                    <div style='border: 1px solid #ccc; border-radius: 12px; padding: 15px; margin-bottom: 15px;'>
                        <div style='font-weight: bold; font-size: 10px; color: #888; text-transform: uppercase; margin-bottom: 10px;'>Customer Journey</div>
                        <table width='100%' style='text-align: center;'>
                            <tr>
                                <td width='25%' style='border-right:1px solid #eee;'><strong>".($journey['Retention']??0)."</strong><br><small style='color:#999;'>Retention</small></td>
                                <td width='25%' style='border-right:1px solid #eee;'><strong>".($journey['Fans']??0)."</strong><br><small style='color:#999;'>Fans</small></td>
                                <td width='25%' style='border-right:1px solid #eee;'><strong>".($journey['Usage']??0)."</strong><br><small style='color:#999;'>Usage</small></td>
                                <td width='25%'><strong>".($journey['Prospecting']??0)."</strong><br><small style='color:#999;'>Prospecting</small></td>
                            </tr>
                        </table>
                    </div>

                    <div style='border: 1px solid #ccc; border-radius: 12px; padding: 15px;'>
                        <div style='font-weight: bold; font-size: 10px; color: #888; text-transform: uppercase; margin-bottom: 10px;'>Message Sentiments</div>
                        <table width='100%'>
                            <tr>
                                <td style='padding-bottom:10px;'>
                                    <span style='font-size:12px;'>Positive (".($sentiments['Positive']??0).")</span>
                                    <div style='background:#eee; height:6px; border-radius:3px;'><div style='background:#28a745; height:6px; width:".($total>0?($sentiments['Positive']/$total)*100:0)."%; border-radius:3px;'></div></div>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <span style='font-size:12px;'>Negative (".($sentiments['Negative']??0).")</span>
                                    <div style='background:#eee; height:6px; border-radius:3px;'><div style='background:#dc3545; height:6px; width:".($total>0?($sentiments['Negative']/$total)*100:0)."%; border-radius:3px;'></div></div>
                                </td>
                            </tr>
                        </table>
                    </div>
                </td>
            </tr>
        </table>

        <div style='margin-top: 20px; border: 1px solid #ccc; border-radius: 12px; padding: 15px;'>
            <div style='font-weight: bold; font-size: 10px; color: #888; text-transform: uppercase; margin-bottom: 10px;'>Social Media Platforms</div>
            $platformHtml
        </div>

    </div>
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
