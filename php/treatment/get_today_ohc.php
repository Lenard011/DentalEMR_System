<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/conns.php';
$db = $db ?? ($pdo ?? null);
if (!$db) {
    echo json_encode(['records' => [], 'visit_id' => 0]);
    exit;
}

$patient_id = intval($_GET['patient_id'] ?? 0);
$date = $_GET['date'] ?? date('Y-m-d');
if ($patient_id <= 0) {
    echo json_encode(['records' => [], 'visit_id' => 0]);
    exit;
}

// Fetch visit
$visitStmt = $db->prepare("SELECT visit_id FROM visits WHERE patient_id=? AND DATE(visit_date)=? LIMIT 1");
$visitStmt->execute([$patient_id, $date]);
$visit = $visitStmt->fetch(PDO::FETCH_ASSOC);
$visit_id = $visit['visit_id'] ?? 0;

$records = [];

if ($visit_id) {
    // Tooth conditions
    $condStmt = $db->prepare("
        SELECT v.box_key, v.color, v.case_type, c.condition_code, 'condition' as type
        FROM visittoothcondition v
        LEFT JOIN conditions c ON c.condition_id=v.condition_id
        WHERE v.visit_id=?
    ");
    $condStmt->execute([$visit_id]);
    $records = array_merge($records, $condStmt->fetchAll(PDO::FETCH_ASSOC));

    // Tooth treatments
    $treatStmt = $db->prepare("
        SELECT v.box_key, v.color, v.case_type, t.treatment_code, 'treatment' as type
        FROM visittoothtreatment v
        LEFT JOIN treatments t ON t.treatment_id=v.treatment_id
        WHERE v.visit_id=?
    ");
    $treatStmt->execute([$visit_id]);
    $records = array_merge($records, $treatStmt->fetchAll(PDO::FETCH_ASSOC));
}

echo json_encode(['records' => $records, 'visit_id' => $visit_id]);
