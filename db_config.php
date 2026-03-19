<?php
$serverName = "triage-server.database.windows.net";

$connectionOptions = [
    "Database" => "triage_db",
    "Uid" => "triageadmin", // make sure this is EXACT
    "PWD" => "Codegen!",    // reset if needed
    "Encrypt" => true,
    "TrustServerCertificate" => false,
    "CharacterSet" => "UTF-8"
];

$conn = sqlsrv_connect($serverName, $connectionOptions);

if ($conn === false) {
    header('Content-Type: application/json');

    $errors = sqlsrv_errors();
    $messages = [];

    if ($errors) {
        foreach ($errors as $err) {
            $messages[] = $err['message'];
        }
    }

    die(json_encode([
        "status" => "error",
        "message" => implode(" | ", $messages)
    ]));
}
?>
