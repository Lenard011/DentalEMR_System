<?php
header('Content-Type: application/json; charset=utf-8');

// ✅ Create a PDO connection
$host = "localhost";
$db   = "u401132124_mho_dentalemr";
$user = "u401132124_dentalclinic";
$pass = "Mho_DentalClinic1st";
$charset = "utf8mb4";

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (Exception $e) {
    exit(json_encode(["success" => false, "message" => "Database connection failed: " . $e->getMessage()]));
}
