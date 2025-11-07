<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/conns.php';

$db = $db ?? null;
if (!$db) exit(json_encode(["success" => false, "message" => "DB not available"]));

$input = json_decode(file_get_contents("php://input"), true);
$fdi_list = $input['fdi_list'] ?? [];
if (!is_array($fdi_list) || empty($fdi_list)) {
    exit(json_encode(["success" => false, "message" => "No teeth provided"]));
}

// Find teeth in DB
$placeholders = implode(',', array_fill(0, count($fdi_list), '?'));
$stmt = $db->prepare("SELECT fdi_number FROM teeth WHERE fdi_number IN ($placeholders)");
$stmt->execute($fdi_list);
$existing = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);

$missing = array_diff($fdi_list, $existing);

echo json_encode(["success" => true, "missing" => array_values($missing)]);
