<?php
// get_treatment_history.php
header('Content-Type: application/json; charset=utf-8');

try {
    require_once __DIR__ . '/conns.php';
    $db = $pdo ?? null;
    
    if (!$db) {
        throw new Exception("Database connection failed");
    }

    $patient_id = intval($_GET['patient_id'] ?? 0);
    
    if ($patient_id <= 0) {
        echo json_encode(["error" => "Invalid patient ID"]);
        exit;
    }

    // Check if new table exists
    $usingHistoryTable = $db->query("SHOW TABLES LIKE 'treatment_history'")->rowCount() > 0;
    
    if ($usingHistoryTable) {
        // Use the new treatment_history table
        $sql = "SELECT 
                    th.history_id as treatment_id,
                    th.patient_id,
                    t.fdi_number,
                    th.treatment_code,
                    th.treatment_date,
                    th.created_at,
                    DATE_FORMAT(th.treatment_date, '%Y-%m-%d') as date_only,
                    DATE_FORMAT(th.created_at, '%Y-%m-%d %H:%i:%s') as full_date
                FROM treatment_history th
                JOIN teeth t ON th.tooth_id = t.tooth_id
                WHERE th.patient_id = ?
                ORDER BY th.treatment_date DESC, th.created_at DESC";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([$patient_id]);
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Format for compatibility with existing frontend
        foreach ($records as &$record) {
            $record['created_at'] = $record['full_date'];
        }
        
    } else {
        // Fallback to old table
        $sql = "SELECT 
                    s.treatment_id,
                    s.patient_id,
                    t.fdi_number,
                    s.treatment_code,
                    s.created_at
                FROM services_monitoring_chart s
                JOIN teeth t ON s.tooth_id = t.tooth_id
                WHERE s.patient_id = ?
                ORDER BY s.created_at DESC";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([$patient_id]);
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
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
        "using_history_table" => $usingHistoryTable,
        "note" => $usingHistoryTable ? 
            "Using treatment_history table (allows multiple treatments per tooth)" : 
            "Using old services_monitoring_chart table"
    ]);

} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "error" => $e->getMessage(),
        "records" => []
    ]);
}
exit;