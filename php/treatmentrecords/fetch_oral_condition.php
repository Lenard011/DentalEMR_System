<?php
// fetch_oral_condition.php - FINAL VERSION
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');
ini_set('display_errors', 0);
error_reporting(0);

require_once __DIR__ . '/conns.php';

$db = $pdo ?? ($db ?? ($conn ?? null));
if (!$db) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database connection error']);
    exit;
}

try {
    if (empty($_GET['patient_id'])) {
        throw new Exception('Patient ID required');
    }

    $patient_id = (int)$_GET['patient_id'];

    // Fetch patient info
    $stmt = $db->prepare("SELECT patient_id, surname, firstname, middlename FROM patients WHERE patient_id = ?");
    $stmt->execute([$patient_id]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$patient) {
        throw new Exception('Patient not found');
    }

    // Fetch all visits
    $stmt = $db->prepare("
        SELECT visit_id, patient_id, visit_date, visit_number 
        FROM visits 
        WHERE patient_id = ? 
        ORDER BY visit_number ASC, visit_date ASC
    ");
    $stmt->execute([$patient_id]);
    $visits = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $result = [];

    foreach ($visits as $visit) {
        $visit_id = $visit['visit_id'];
        $visit_number = (int)$visit['visit_number'];

        // Conditions
        $stmtCond = $db->prepare("
            SELECT vc.id, vc.tooth_id, vc.condition_id, vc.box_key, vc.color, vc.case_type,
                   t.fdi_number, c.code AS condition_code
            FROM visittoothcondition vc
            LEFT JOIN teeth t ON vc.tooth_id = t.tooth_id
            LEFT JOIN conditions c ON vc.condition_id = c.condition_id
            WHERE vc.visit_id = ?
        ");
        $stmtCond->execute([$visit_id]);
        $conditions = $stmtCond->fetchAll(PDO::FETCH_ASSOC);

        // Treatments
        $stmtTreat = $db->prepare("
            SELECT vt.id, vt.tooth_id, vt.treatment_id, vt.box_key, vt.color, vt.case_type,
                   t.fdi_number, tr.code AS treatment_code
            FROM visittoothtreatment vt
            LEFT JOIN teeth t ON vt.tooth_id = t.tooth_id
            LEFT JOIN treatments tr ON vt.treatment_id = tr.treatment_id
            WHERE vt.visit_id = ?
        ");
        $stmtTreat->execute([$visit_id]);
        $treatments = $stmtTreat->fetchAll(PDO::FETCH_ASSOC);

        // Convert to Roman numeral
        $roman = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X'];
        $visit_label = 'Year ' . ($roman[$visit_number - 1] ?? $visit_number);

        $result[] = [
            'visit_id' => $visit_id,
            'visit_number' => $visit_number,
            'visit_label' => $visit_label,
            'visit_date' => $visit['visit_date'],
            'conditions' => $conditions,
            'treatments' => $treatments
        ];
    }

    // If no visits, create empty Year I
    if (empty($result)) {
        $result[] = [
            'visit_id' => 0,
            'visit_number' => 1,
            'visit_label' => 'Year I',
            'visit_date' => null,
            'conditions' => [],
            'treatments' => []
        ];
    }

    echo json_encode([
        'success' => true,
        'patient' => $patient,
        'visits' => $result
    ]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
