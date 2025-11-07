<?php
header('Content-Type: application/json; charset=utf-8');

try {
    require_once __DIR__ . './conns.php';
    $db = $pdo ?? null;
    if (!$db) throw new Exception("DB not available");

    $patient_id = intval($_GET['patient_id'] ?? 0);
    $date = $_GET['date'] ?? date('Y-m-d');

    if ($patient_id <= 0) throw new Exception("No patient selected");

    $stmt = $db->prepare("
        SELECT t.fdi_number, s.treatment_code
        FROM services_monitoring_chart s
        JOIN teeth t ON t.tooth_id = s.tooth_id
        WHERE s.patient_id = :patient_id AND DATE(s.created_at) = :date
    ");
    $stmt->execute([':patient_id' => $patient_id, ':date' => $date]);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['records' => $records]);
} catch (Exception $e) {
    error_log("Failed to get today's SMC: " . $e->getMessage());
    echo json_encode(['records' => [], 'error' => $e->getMessage()]);
}
exit;
