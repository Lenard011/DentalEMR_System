<?php
// get_smc_data.php
header('Content-Type: application/json; charset=utf-8');

try {
    require_once __DIR__ . '/conns.php';
    $db = $pdo ?? null;

    if (!$db) {
        throw new Exception("Database connection failed");
    }

    $patient_id = intval($_GET['patient_id'] ?? 0);
    $date_filter = $_GET['date'] ?? null;

    if ($patient_id <= 0) {
        echo json_encode(["success" => false, "error" => "Invalid patient ID"]);
        exit;
    }

    // Simple query for services_monitoring_chart table
    $sql = "SELECT 
                s.treatment_id,
                s.patient_id,
                t.fdi_number,
                s.treatment_code,
                s.created_at,
                DATE(s.created_at) as treatment_date
            FROM services_monitoring_chart s
            JOIN teeth t ON s.tooth_id = t.tooth_id
            WHERE s.patient_id = ?";

    $params = [$patient_id];

    if ($date_filter) {
        $sql .= " AND DATE(s.created_at) = ?";
        $params[] = $date_filter;
    }

    $sql .= " ORDER BY s.created_at DESC";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get patient name
    $patientSql = "SELECT surname, firstname, middlename FROM patients WHERE patient_id = ?";
    $patientStmt = $db->prepare($patientSql);
    $patientStmt->execute([$patient_id]);
    $patient = $patientStmt->fetch(PDO::FETCH_ASSOC);

    $patient_name = "Unknown Patient";
    if ($patient) {
        $middle = !empty($patient['middlename']) ? " " . $patient['middlename'][0] . "." : "";
        $patient_name = $patient['firstname'] . $middle . " " . $patient['surname'];
    }

    echo json_encode([
        "success" => true,
        "patient_name" => $patient_name,
        "records" => $records,
        "count" => count($records),
        "table_used" => "services_monitoring_chart"
    ]);
} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "error" => $e->getMessage(),
        "records" => []
    ]);
}
exit;
