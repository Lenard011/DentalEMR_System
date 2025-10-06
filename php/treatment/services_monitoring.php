<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/conns.php';

$db = $db ?? null;
if (!$db) exit(json_encode(["success" => false, "message" => "DB not available"]));

$input = json_decode(file_get_contents("php://input"), true);
if (!is_array($input)) exit(json_encode(["success" => false, "message" => "Invalid JSON"]));

$patient_id = isset($input['patient_id']) ? intval($input['patient_id']) : 0;
if ($patient_id <= 0) exit(json_encode(["success" => false, "message" => "No patient selected"]));

// Check patient exists
$checkPatient = $db->prepare("SELECT 1 FROM patients WHERE patient_id = :id LIMIT 1");
$checkPatient->execute([':id' => $patient_id]);
if (!$checkPatient->fetchColumn()) exit(json_encode(["success" => false, "message" => "Patient not found"]));

$treatments = $input['treatments'] ?? [];
if (!is_array($treatments)) exit(json_encode(["success" => false, "message" => "No treatments provided"]));

try {
    $findTooth = $db->prepare("SELECT tooth_id FROM teeth WHERE fdi_number = :fdi LIMIT 1");

    $insertStmt = $db->prepare("
        INSERT INTO services_monitoring_chart (patient_id, tooth_id, treatment_code)
        VALUES (:patient_id, :tooth_id, :treatment_code)
        ON DUPLICATE KEY UPDATE treatment_code = :treatment_code_update
    ");

    $deleteStmt = $db->prepare("
        DELETE FROM services_monitoring_chart 
        WHERE patient_id = :patient_id AND tooth_id = :tooth_id
    ");

    $db->beginTransaction();

    $summary = [
        'added' => 0,
        'updated' => 0,
        'deleted' => 0
    ];

    foreach ($treatments as $t) {
        $fdi = trim((string)($t['tooth_id'] ?? ''));
        $treatment_code = isset($t['treatment_id']) ? trim((string)$t['treatment_id']) : null;
        if ($fdi === '') continue;

        $findTooth->execute([':fdi' => $fdi]);
        $row = $findTooth->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            $db->rollBack();
            exit(json_encode(["success" => false, "message" => "Tooth {$fdi} not found"]));
        }
        $tooth_id = intval($row['tooth_id']);

        if ($treatment_code === null || $treatment_code === '') {
            $deleteStmt->execute([':patient_id' => $patient_id, ':tooth_id' => $tooth_id]);
            if ($deleteStmt->rowCount()) $summary['deleted']++;
        } else {
            $insertStmt->execute([
                ':patient_id' => $patient_id,
                ':tooth_id' => $tooth_id,
                ':treatment_code' => $treatment_code,
                ':treatment_code_update' => $treatment_code
            ]);
            $affected = $insertStmt->rowCount();
            if ($affected === 1) $summary['added']++;
            elseif ($affected === 2) $summary['updated']++;
        }
    }

    $db->commit();

    // Build message
    $messages = [];
    if ($summary['added']) $messages[] = "{$summary['added']} treatment(s) added";
    if ($summary['updated']) $messages[] = "{$summary['updated']} treatment(s) updated";
    if ($summary['deleted']) $messages[] = "{$summary['deleted']} treatment(s) removed";

    $msg = implode(", ", $messages);
    exit(json_encode(["success" => true, "message" => $msg]));
} catch (Exception $e) {
    if ($db->inTransaction()) $db->rollBack();
    exit(json_encode(["success" => false, "message" => "Execute failed: " . $e->getMessage()]));
}
