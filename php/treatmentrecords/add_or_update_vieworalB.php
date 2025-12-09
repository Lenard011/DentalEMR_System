<?php
header('Content-Type: application/json; charset=utf-8');

try {
    require_once __DIR__ . '/conns.php';
    $db = $pdo ?? null;
    
    if (!$db) {
        throw new Exception("Database connection failed");
    }

    $rawInput = file_get_contents("php://input");
    $input = json_decode($rawInput, true);
    
    $patient_id = intval($input['patient_id'] ?? 0);
    $treatments = $input['treatments'] ?? [];
    $date = $input['date'] ?? date('Y-m-d');

    if ($patient_id <= 0) {
        throw new Exception("No patient selected");
    }
    
    $db->beginTransaction();
    
    $processed = 0;
    $skipped = 0;
    
    // For EACH tooth, handle individually
    foreach ($treatments as $t) {
        $fdi = trim((string)($t['tooth_id'] ?? ''));
        $treatment_code = trim((string)($t['treatment_code'] ?? ''));
        
        if (empty($fdi) || empty($treatment_code)) {
            $skipped++;
            continue;
        }
        
        // Get tooth_id
        $toothSql = "SELECT tooth_id FROM teeth WHERE fdi_number = ?";
        $toothStmt = $db->prepare($toothSql);
        $toothStmt->execute([$fdi]);
        $tooth = $toothStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$tooth) {
            $skipped++;
            continue;
        }
        
        $tooth_id = $tooth['tooth_id'];
        $date_time = $date . ' ' . date('H:i:s');
        
        // STRATEGY: Delete if exists for same date, then insert
        // This gives you "update" behavior for same date
        
        // 1. Delete existing for this patient+tooth+date
        $deleteSql = "DELETE FROM services_monitoring_chart 
                      WHERE patient_id = ? 
                      AND tooth_id = ? 
                      AND DATE(created_at) = ?";
        $deleteStmt = $db->prepare($deleteSql);
        $deleteStmt->execute([$patient_id, $tooth_id, $date]);
        
        // 2. Insert new treatment
        $insertSql = "INSERT INTO services_monitoring_chart 
                      (patient_id, tooth_id, treatment_code, created_at) 
                      VALUES (?, ?, ?, ?)";
        $insertStmt = $db->prepare($insertSql);
        $result = $insertStmt->execute([$patient_id, $tooth_id, $treatment_code, $date_time]);
        
        if ($result) {
            $processed++;
        }
    }
    
    $db->commit();
    
    echo json_encode([
        "success" => true,
        "message" => "$processed treatment(s) saved for $date",
        "processed" => $processed,
        "skipped" => $skipped
    ]);

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    
    echo json_encode([
        "success" => false,
        "message" => "Error: " . $e->getMessage()
    ]);
}
exit;