<?php
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/conns.php';

// Detect valid DB connection variable
$db = $pdo ?? ($db ?? ($conn ?? null));
if (!$db) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database connection not found. Expected $pdo, $db, or $conn in conns.php'
    ]);
    exit;
}

// Roman numeral converter
function romanNumeral($num): string
{
    $map = [
        1 => 'I',
        2 => 'II',
        3 => 'III',
        4 => 'IV',
        5 => 'V',
        6 => 'VI',
        7 => 'VII',
        8 => 'VIII',
        9 => 'IX',
        10 => 'X'
    ];
    return $map[$num] ?? (string)$num;
}

try {
    if (empty($_GET['patient_id'])) {
        throw new Exception('Missing required parameter: patient_id');
    }

    $patient_id = (int) $_GET['patient_id'];

    // Fetch patient information
    $stmtPatient = $db->prepare("
        SELECT 
            patient_id,
            surname,
            firstname,
            middlename,
            date_of_birth,
            place_of_birth,
            age,
            sex,
            address,
            pregnant,
            occupation,
            guardian,
            created_at
        FROM patients
        WHERE patient_id = ?
        LIMIT 1
    ");
    $stmtPatient->execute([$patient_id]);
    $patient = $stmtPatient->fetch(PDO::FETCH_ASSOC);

    if (!$patient) {
        throw new Exception('Patient not found for ID ' . $patient_id);
    }

    // Fetch ALL visits for this patient
    $stmt = $db->prepare("
        SELECT visit_id, patient_id, visit_date, visit_number
        FROM visits
        WHERE patient_id = ?
        ORDER BY visit_number ASC
    ");
    $stmt->execute([$patient_id]);
    $visits = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // If no visits, still return patient info
    if (!$visits) {
        echo json_encode([
            'success' => true,
            'patient_id' => $patient_id,
            'patient' => $patient,
            'visits' => []
        ]);
        exit;
    }

    $output = [];

    foreach ($visits as $v) {
        $visit_id = $v['visit_id'];

        // Fetch conditions for this visit
        $stmtCond = $db->prepare("
            SELECT 
                vc.id,
                vc.tooth_id,
                vc.condition_id,
                vc.box_key,
                vc.color,
                vc.case_type,
                t.fdi_number,
                t.type AS tooth_type,
                t.location AS tooth_location,
                c.code AS condition_code,
                c.description AS condition_description,
                c.is_permanent
            FROM visittoothcondition vc
            INNER JOIN teeth t ON vc.tooth_id = t.tooth_id
            INNER JOIN conditions c ON vc.condition_id = c.condition_id
            WHERE vc.visit_id = ?
            ORDER BY vc.id
        ");
        $stmtCond->execute([$visit_id]);
        $conditions = $stmtCond->fetchAll(PDO::FETCH_ASSOC);

        // Fetch treatments for this visit
        $stmtTreat = $db->prepare("
            SELECT 
                vt.id,
                vt.tooth_id,
                vt.treatment_id,
                vt.box_key,
                vt.color,
                vt.case_type,
                t.fdi_number,
                t.type AS tooth_type,
                t.location AS tooth_location,
                tr.code AS treatment_code,
                tr.description AS treatment_description
            FROM visittoothtreatment vt
            INNER JOIN teeth t ON vt.tooth_id = t.tooth_id
            INNER JOIN treatments tr ON vt.treatment_id = tr.treatment_id
            WHERE vt.visit_id = ?
            ORDER BY vt.id
        ");
        $stmtTreat->execute([$visit_id]);
        $treatments = $stmtTreat->fetchAll(PDO::FETCH_ASSOC);

        // Process condition codes based on case_type
        foreach ($conditions as &$condition) {
            if ($condition['condition_code']) {
                if (
                    $condition['case_type'] === 'temporary' ||
                    ($condition['tooth_type'] === 'temporary' && $condition['is_permanent'] == 0)
                ) {
                    $condition['condition_code'] = strtolower($condition['condition_code']);
                } else {
                    $condition['condition_code'] = strtoupper($condition['condition_code']);
                }
            }
        }

        // Process treatment codes to uppercase
        foreach ($treatments as &$treatment) {
            if ($treatment['treatment_code']) {
                $treatment['treatment_code'] = strtoupper($treatment['treatment_code']);
            }
        }

        // Combine all visit data
        $output[] = [
            'visit_id'      => $visit_id,
            'visit_number'  => (int)$v['visit_number'],
            'visit_label'   => 'Year ' . romanNumeral($v['visit_number']),
            'visit_date'    => $v['visit_date'],
            'conditions'    => $conditions,
            'treatments'    => $treatments
        ];
    }

    // Output JSON (with patient details + visits)
    echo json_encode([
        'success' => true,
        'patient_id' => $patient_id,
        'patient' => $patient,
        'visits' => $output
    ], JSON_PRETTY_PRINT);
    exit;
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
    exit;
}
