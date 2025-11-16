<?php
// php/conns.php
// Simple PDO connection. Adjust credentials.

$DB_HOST = '127.0.0.1';
$DB_NAME = 'dentalemr_system';
$DB_USER = 'root';
$DB_PASS = '';

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $db = new PDO("mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4", $DB_USER, $DB_PASS, $options);
} catch (PDOException $ex) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database connection failed: ' . $ex->getMessage()]);
    exit;
}
