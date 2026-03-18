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

if( $conn === false ) {
     header('Content-Type: application/json');
     die(json_encode(["status" => "error", "message" => "Database connection failed"]));
}
?>
