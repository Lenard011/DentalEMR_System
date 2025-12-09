<?php
// view_oralB_api.php
header('Content-Type: application/json; charset=utf-8');

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    require_once __DIR__ . '/conns.php';
    $db = $pdo ?? null;

    if (!$db) {
        throw new Exception("Database connection failed");
    }

    // Get patient_id from query
    $patient_id = isset($_GET['patient_id']) ? intval($_GET['patient_id']) : 0;

    if ($patient_id <= 0) {
        echo json_encode(["error" => "Invalid or missing patient_id"]);
        exit;
    }

    // First, get patient name
    $patientSql = "SELECT surname, firstname, middlename FROM patients WHERE patient_id = ?";
    $patientStmt = $db->prepare($patientSql);
    $patientStmt->execute([$patient_id]);
    $patient = $patientStmt->fetch(PDO::FETCH_ASSOC);

    $patient_name = "Unknown Patient";
    if ($patient) {
        $middlename = !empty($patient['middlename']) ? " " . $patient['middlename'] . "." : "";
        $patient_name = $patient['firstname'] . $middlename . " " . $patient['surname'];
    }

    // Then get all treatment records for this patient
    $sql = "
        SELECT 
            s.treatment_id,
            s.patient_id,
            t.fdi_number,
            s.treatment_code,
            s.created_at
        FROM services_monitoring_chart s
        LEFT JOIN teeth t ON s.tooth_id = t.tooth_id
        WHERE s.patient_id = ?
        ORDER BY s.created_at DESC
    ";

    $stmt = $db->prepare($sql);
    $stmt->execute([$patient_id]);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "success" => true,
        "patient_name" => $patient_name,
        "records" => $records,
        "count" => count($records)
    ]);
} catch (Exception $e) {
    error_log("API Error: " . $e->getMessage());
    echo json_encode([
        "success" => false,
        "error" => $e->getMessage(),
        "records" => []
    ]);
}
