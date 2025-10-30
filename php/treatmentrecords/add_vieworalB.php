<?php
header('Content-Type: application/json; charset=utf-8');

try {
    require_once __DIR__ . './conns.php'; // include DB connection
    $db = $pdo ?? null;
    if (!$db) throw new Exception("DB not available");

    $input = json_decode(file_get_contents("php://input"), true);
    if (!is_array($input)) throw new Exception("Invalid JSON");

    $patient_id = intval($input['patient_id'] ?? 0);
    if ($patient_id <= 0) throw new Exception("No patient selected");

    $stmt = $db->prepare("SELECT 1 FROM patients WHERE patient_id = :id LIMIT 1");
    $stmt->execute([':id' => $patient_id]);
    if (!$stmt->fetchColumn()) throw new Exception("Patient not found");

    $treatments = $input['treatments'] ?? [];
    if (!is_array($treatments) || empty($treatments)) throw new Exception("No treatments provided");

    $findTooth = $db->prepare("SELECT tooth_id FROM teeth WHERE fdi_number = :fdi LIMIT 1");
    $insertStmt = $db->prepare("INSERT INTO services_monitoring_chart (patient_id, tooth_id, treatment_code, created_at) VALUES (:patient_id, :tooth_id, :treatment_code, NOW())");

    $db->beginTransaction();
    $added = 0;

    foreach ($treatments as $t) {
        $fdi = trim((string)($t['tooth_id'] ?? ''));
        $treatment_code = trim((string)($t['treatment_id'] ?? ''));
        if ($fdi === '' || $treatment_code === '') continue;

        $findTooth->execute([':fdi' => $fdi]);
        $row = $findTooth->fetch(PDO::FETCH_ASSOC);
        if (!$row) throw new Exception("Tooth {$fdi} not found");

        $insertStmt->execute([
            ':patient_id' => $patient_id,
            ':tooth_id' => intval($row['tooth_id']),
            ':treatment_code' => $treatment_code
        ]);
        $added++;
    }

    $db->commit();
    echo json_encode(["success" => true, "message" => "$added treatment record(s) added."]);
} catch (Exception $e) {
    if ($db && $db->inTransaction()) $db->rollBack();
    error_log("SMC save error: " . $e->getMessage());
    echo json_encode(["success" => false, "message" => "Save SMC error: " . $e->getMessage()]);
}
exit;
