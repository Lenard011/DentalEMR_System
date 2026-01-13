<?php
// save_smc.php
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

    if (empty($treatments)) {
        echo json_encode([
            "success" => false,
            "message" => "No treatments provided"
        ]);
        exit;
    }

    $db->beginTransaction();

    $results = [
        'inserted' => 0,
        'updated' => 0,
        'skipped' => 0,
        'errors' => []
    ];

    $date_time = date('Y-m-d H:i:s');

    foreach ($treatments as $index => $t) {
        $fdi = trim((string)($t['tooth_id'] ?? ''));
        $treatment_code = trim((string)($t['treatment_code'] ?? ''));

        if (empty($fdi) || empty($treatment_code)) {
            $results['skipped']++;
            continue;
        }

        // Get tooth_id from FDI
        $toothSql = "SELECT tooth_id FROM teeth WHERE fdi_number = ?";
        $toothStmt = $db->prepare($toothSql);
        $toothStmt->execute([$fdi]);
        $tooth = $toothStmt->fetch(PDO::FETCH_ASSOC);

        if (!$tooth) {
            $results['skipped']++;
            $results['errors'][] = "Tooth $fdi not found";
            continue;
        }

        $tooth_id = $tooth['tooth_id'];

        // Check if treatment already exists for same patient+tooth on same day
        $checkSql = "SELECT treatment_id FROM services_monitoring_chart 
                     WHERE patient_id = ? 
                     AND tooth_id = ? 
                     AND DATE(created_at) = ?";
        $checkStmt = $db->prepare($checkSql);
        $checkStmt->execute([$patient_id, $tooth_id, $date]);
        $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            // UPDATE existing record
            $updateSql = "UPDATE services_monitoring_chart 
                          SET treatment_code = ?, created_at = ?
                          WHERE treatment_id = ?";
            $updateStmt = $db->prepare($updateSql);
            $result = $updateStmt->execute([$treatment_code, $date_time, $existing['treatment_id']]);

            if ($result) {
                $results['updated']++;
            } else {
                $results['skipped']++;
                $results['errors'][] = "Failed to update tooth $fdi";
            }
        } else {
            // INSERT new treatment record
            $insertSql = "INSERT INTO services_monitoring_chart 
                          (patient_id, tooth_id, treatment_code, created_at) 
                          VALUES (?, ?, ?, ?)";
            $insertStmt = $db->prepare($insertSql);
            $result = $insertStmt->execute([$patient_id, $tooth_id, $treatment_code, $date_time]);

            if ($result) {
                $results['inserted']++;
            } else {
                $results['skipped']++;
                $results['errors'][] = "Failed to insert tooth $fdi";
            }
        }
    }

    $db->commit();

    $total = $results['inserted'] + $results['updated'];

    if ($total > 0) {
        $message = "Success! ";
        if ($results['inserted'] > 0) {
            $message .= "{$results['inserted']} new treatment(s) added. ";
        }
        if ($results['updated'] > 0) {
            $message .= "{$results['updated']} treatment(s) updated. ";
        }
        if ($results['skipped'] > 0) {
            $message .= "{$results['skipped']} skipped.";
        }

        echo json_encode([
            "success" => true,
            "message" => trim($message),
            "results" => $results,
            "date" => $date
        ]);
    } else {
        echo json_encode([
            "success" => false,
            "message" => "No treatments saved. " .
                (isset($results['errors'][0]) ? $results['errors'][0] : ""),
            "results" => $results
        ]);
    }
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
