<?php
header("Content-Type: application/json");

$input = json_decode(file_get_contents("php://input"), true);

if (!$input || !isset($input['metrics'])) {
    echo json_encode(["success" => false, "message" => "Invalid data"]);
    exit;
}

$metrics = $input['metrics'];
$dateRange = $input['dateRange'] ?? '';

// Extract values
$total = $metrics['total'];
$responded = $metrics['responded'];
$closed = $metrics['nonActionable'];
$forResponse = $metrics['forResponse'];
$journey = $metrics['journey'];
$sentiments = $metrics['sentiments'];

// Percentages
$respondedPct = $total > 0 ? round(($responded / $total) * 100, 1) : 0;
$posPct = $total > 0 ? round(($sentiments['Positive'] / $total) * 100) : 0;
$negPct = $total > 0 ? round(($sentiments['Negative'] / $total) * 100) : 0;
$neuPct = $total > 0 ? round(($sentiments['Neutral'] / $total) * 100) : 0;

// Platform HTML (build dynamically if needed)
$platformHtml = '';
if (isset($metrics['rawData'])) {
    $platformCounts = [];
    foreach ($metrics['rawData'] as $row) {
        $p = $row['Social Network'] ?? 'Other';
        $platformCounts[$p] = ($platformCounts[$p] ?? 0) + 1;
    }

    foreach ($platformCounts as $name => $count) {
        $platformHtml .= "<div>$name: <strong>$count</strong></div>";
    }
}

/* 👉 PASTE YOUR $emailBody TEMPLATE HERE 👇 */
$emailBody = '
<html>
<body style="margin:0; padding:0; background:#f5f6f8; font-family:Arial, sans-serif;">

<table width="100%" bgcolor="#f5f6f8" cellpadding="0" cellspacing="0">
<tr><td align="center">

<table width="800" bgcolor="#ffffff" cellpadding="20" cellspacing="0" style="border-radius:12px;">

<!-- HEADER -->
<tr>
<td style="border-bottom:2px solid #071952;">
<h2 style="color:#071952; margin:0;">Social Triage After-care Service</h2>
<p style="color:#666; margin-top:5px;">' . $dateRange . '</p>
</td>
</tr>

<!-- TOP METRICS -->
<tr>
<td>
<table width="100%" cellpadding="10" cellspacing="0" style="border:1px solid #ccc; border-radius:10px;">
<tr>
<td align="center">
<div style="font-size:12px;">Total Sent</div>
<div style="font-size:28px; color:#071952; font-weight:bold;">' . $total . '</div>
</td>

<td align="center">
<div style="font-size:12px;">Responded</div>
<div style="font-size:28px; color:#071952; font-weight:bold;">' . $responded . '</div>
<div style="font-size:12px; color:#888;">(' . $respondedPct . '%)</div>
</td>

<td align="center">
<div style="font-size:12px;">Closed</div>
<div style="font-size:28px; color:#071952; font-weight:bold;">' . $closed . '</div>
</td>

<td align="center">
<div style="font-size:12px;">For Response</div>
<div style="font-size:28px; color:#071952; font-weight:bold;">' . $forResponse . '</div>
</td>
</tr>
</table>
</td>
</tr>

<!-- JOURNEY -->
<tr>
<td>
<table width="100%" cellpadding="10" cellspacing="0" style="border:1px solid #ccc; border-radius:10px;">
<tr>
<td align="center"><strong>Retention</strong><br>' . $journey['Retention'] . '</td>
<td align="center"><strong>Fans</strong><br>' . $journey['Fans'] . '</td>
<td align="center"><strong>Usage</strong><br>' . $journey['Usage'] . '</td>
<td align="center"><strong>Prospecting</strong><br>' . $journey['Prospecting'] . '</td>
</tr>
</table>
</td>
</tr>

<!-- PLATFORM -->
<tr>
<td>
<h4 style="color:#071952;">Social Media Platforms</h4>
' . $platformHtml . '
</td>
</tr>

<!-- SENTIMENT -->
<tr>
<td>
<h4 style="color:#071952;">Message Sentiments</h4>

<div>Positive (' . $sentiments['Positive'] . ')</div>
<div style="background:#eee; height:8px;">
<div style="width:' . $posPct . '%; background:#28a745; height:8px;"></div>
</div>

<div>Negative (' . $sentiments['Negative'] . ')</div>
<div style="background:#eee; height:8px;">
<div style="width:' . $negPct . '%; background:#dc3545; height:8px;"></div>
</div>

<div>Neutral (' . $sentiments['Neutral'] . ')</div>
<div style="background:#eee; height:8px;">
<div style="width:' . $neuPct . '%; background:#17a2b8; height:8px;"></div>
</div>

</td>
</tr>

</table>

</td></tr>
</table>

</body>
</html>';

/* 🔥 Power Automate */
$flowUrl = "https://default10f787270c1845afb9ee97e94fd5bc.d8.environment.api.powerplatform.com:443/powerautomate/automations/direct/workflows/babe04e0152246ce8b282f17605d9fa5/triggers/manual/paths/invoke?api-version=1";

$data = [
    "ToEmail" => "your@email.com",
    "SubjectText" => "Social Triage Report",
    "BodyText" => $emailBody
];

$ch = curl_init($flowUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo json_encode([
    "success" => $httpCode >= 200 && $httpCode < 300
]);
