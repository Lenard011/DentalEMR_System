<?php
// get_teeth.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/conns.php';
$db = $db ?? ($pdo ?? null);
if (!$db) {
    echo json_encode([]);
    exit;
}
try {
    $stmt = $db->query("SELECT tooth_id, fdi_number FROM teeth");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($rows);
} catch (Exception $e) {
    echo json_encode([]);
}
