<?php
header('Content-Type: application/json; charset=utf-8');

try {
    require_once __DIR__ . './conns.php';
    $db = $pdo ?? null;
    if (!$db) throw new Exception("DB not available");

    $input = json_decode(file_get_contents("php://input"), true);
    $patient_id = intval($input['patient_id'] ?? 0);
    $treatments = $input['treatments'] ?? [];
    $date = $input['date'] ?? date('Y-m-d');

    if ($patient_id <= 0) throw new Exception("No patient selected");
    if (!is_array($treatments)) throw new Exception("Invalid treatments");

    // Prepare statements
    $findTooth = $db->prepare("SELECT tooth_id FROM teeth WHERE fdi_number = :fdi LIMIT 1");
    $findRecord = $db->prepare("SELECT treatment_id FROM services_monitoring_chart WHERE patient_id = :patient_id AND tooth_id = :tooth_id AND DATE(created_at) = :date LIMIT 1");
    $updateStmt = $db->prepare("UPDATE services_monitoring_chart SET treatment_code = :treatment_code WHERE patient_id = :patient_id AND tooth_id = :tooth_id AND DATE(created_at) = :date");
    $deleteStmt = $db->prepare("DELETE FROM services_monitoring_chart WHERE patient_id = :patient_id AND tooth_id = :tooth_id AND DATE(created_at) = :date");
    $insertStmt = $db->prepare("INSERT INTO services_monitoring_chart (patient_id, tooth_id, treatment_code, created_at) VALUES (:patient_id, :tooth_id, :treatment_code, NOW())");

    $db->beginTransaction();
    $processed = 0;

    foreach ($treatments as $t) {
        $fdi = trim((string)($t['tooth_id'] ?? ''));
        $treatment_code = trim((string)($t['treatment_code'] ?? ''));

        if ($fdi === '') continue;

        $findTooth->execute([':fdi' => $fdi]);
        $tooth = $findTooth->fetch(PDO::FETCH_ASSOC);
        if (!$tooth) continue;

        $tooth_id = intval($tooth['tooth_id']);
        $findRecord->execute([
            ':patient_id' => $patient_id,
            ':tooth_id' => $tooth_id,
            ':date' => $date
        ]);
        $existing = $findRecord->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            if ($treatment_code === '') {
                $deleteStmt->execute([':patient_id' => $patient_id, ':tooth_id' => $tooth_id, ':date' => $date]);
            } else {
                $updateStmt->execute([':treatment_code' => $treatment_code, ':patient_id' => $patient_id, ':tooth_id' => $tooth_id, ':date' => $date]);
            }
        } else {
            if ($treatment_code !== '') {
                $insertStmt->execute([':patient_id' => $patient_id, ':tooth_id' => $tooth_id, ':treatment_code' => $treatment_code]);
            }
        }
        $processed++;
    }

    $db->commit();
    echo json_encode(["success" => true, "message" => "$processed treatment(s) processed."]);
} catch (Exception $e) {
    if ($db && $db->inTransaction()) $db->rollBack();
    error_log("SMC save/update error: " . $e->getMessage());
    echo json_encode(["success" => false, "message" => "SMC save/update error: " . $e->getMessage()]);
}
exit;
