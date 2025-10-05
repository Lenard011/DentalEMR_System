<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/conns.php';

$db = $db ?? null;
if (!$db) {
    echo json_encode(["success" => false, "message" => "DB connection not available"]);
    exit;
}

// Read JSON body
$raw = file_get_contents("php://input");
$input = json_decode($raw, true);
if (!is_array($input)) {
    echo json_encode(["success" => false, "message" => "Invalid JSON input"]);
    exit;
}

// Validate patient_id
$patient_id = isset($input['patient_id']) ? intval($input['patient_id']) : 0;
if ($patient_id <= 0) {
    echo json_encode(["success" => false, "message" => "No patient selected"]);
    exit;
}

// Check patient exists
$checkPatient = $db->prepare("SELECT 1 FROM patients WHERE patient_id = :id LIMIT 1");
$checkPatient->execute([':id' => $patient_id]);
if (!$checkPatient->fetchColumn()) {
    echo json_encode(["success" => false, "message" => "No patient selected"]);
    exit;
}

$treatments = $input['treatments'] ?? [];
if (!is_array($treatments) || count($treatments) === 0) {
    echo json_encode(["success" => false, "message" => "No treatments provided"]);
    exit;
}

try {
    $findTooth = $db->prepare("SELECT tooth_id FROM teeth WHERE fdi_number = :fdi LIMIT 1");
    $insertStmt = $db->prepare("
        INSERT INTO services_monitoring_chart 
        (patient_id, tooth_id, treatment_code) 
        VALUES (:patient_id, :tooth_id, :treatment_code)
    ");

    $db->beginTransaction();

    foreach ($treatments as $t) {
        $fdi = trim((string)($t['tooth_id'] ?? ''));
        $treatment_code = trim((string)($t['treatment_id'] ?? ''));

        if ($fdi === '' || $treatment_code === '') continue;

        // Validate tooth exists
        $findTooth->execute([':fdi' => $fdi]);
        $row = $findTooth->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            $db->rollBack();
            echo json_encode(["success" => false, "message" => "Tooth with FDI {$fdi} not found"]);
            exit;
        }

        $tooth_id = intval($row['tooth_id']);

        // Insert treatment
        $insertStmt->execute([
            ':patient_id' => $patient_id,
            ':tooth_id' => $tooth_id,
            ':treatment_code' => $treatment_code
        ]);
    }

    $db->commit();
    echo json_encode(["success" => true, "message" => "Treatments saved successfully"]);
    exit;

} catch (Exception $e) {
    if ($db->inTransaction()) $db->rollBack();
    echo json_encode(["success" => false, "message" => "Execute failed: " . $e->getMessage()]);
    exit;
}
