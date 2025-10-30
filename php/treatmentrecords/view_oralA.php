<?php
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/conns.php';

// ✅ Detect valid DB connection variable
$db = $pdo ?? ($db ?? ($conn ?? null));
if (!$db) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database connection not found. Expected $pdo, $db, or $conn in conns.php'
    ]);
    exit;
}

// ✅ Roman numeral converter
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

    // ✅ NEW: Fetch patient information
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

    // ✅ Optional: filter by visit_id
    $filter_visit_id = isset($_GET['visit_id']) ? (int) $_GET['visit_id'] : null;

    // ✅ Fetch visits
    if ($filter_visit_id) {
        $stmt = $db->prepare("
            SELECT visit_id, patient_id, visit_date, visit_number
            FROM visits
            WHERE patient_id = ? AND visit_id = ?
            ORDER BY visit_number ASC
        ");
        $stmt->execute([$patient_id, $filter_visit_id]);
    } else {
        $stmt = $db->prepare("
            SELECT visit_id, patient_id, visit_date, visit_number
            FROM visits
            WHERE patient_id = ?
            ORDER BY visit_number ASC
        ");
        $stmt->execute([$patient_id]);
    }

    $visits = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ✅ If no visits, still return patient info
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

        // 🦷 Fetch conditions (with correct color source)
        $stmtCond = $db->prepare("
            SELECT 
                vc.id,
                vc.tooth_id,
                t.fdi_number,
                t.type AS tooth_type,
                t.location AS tooth_location,
                c.condition_id,
                c.code AS condition_code,
                c.description AS condition_description,
                c.is_permanent,
                vc.box_key,
                vc.color,
                vc.case_type
            FROM visittoothcondition vc
            LEFT JOIN teeth t ON vc.tooth_id = t.tooth_id
            LEFT JOIN conditions c ON vc.condition_id = c.condition_id
            WHERE vc.visit_id = ?
        ");
        $stmtCond->execute([$visit_id]);
        $conditions = $stmtCond->fetchAll(PDO::FETCH_ASSOC);

        // 💉 Fetch treatments (ignore color)
        $stmtTreat = $db->prepare("
            SELECT 
                vt.id,
                vt.tooth_id,
                t.fdi_number,
                t.type AS tooth_type,
                t.location AS tooth_location,
                tr.treatment_id,
                tr.code AS treatment_code,
                tr.description AS treatment_description,
                vt.box_key,
                vt.color,
                vt.case_type
            FROM visittoothtreatment vt
            LEFT JOIN teeth t ON vt.tooth_id = t.tooth_id
            LEFT JOIN treatments tr ON vt.treatment_id = tr.treatment_id
            WHERE vt.visit_id = ?
        ");
        $stmtTreat->execute([$visit_id]);
        $treatments = $stmtTreat->fetchAll(PDO::FETCH_ASSOC);

        // 🧩 Combine all visit data
        $output[] = [
            'visit_id'      => $visit_id,
            'visit_number'  => $v['visit_number'],
            'visit_label'   => 'Year ' . romanNumeral($v['visit_number']),
            'visit_date'    => $v['visit_date'],
            'conditions'    => $conditions,
            'treatments'    => $treatments
        ];
    }

    // ✅ Output JSON (with patient details + visits)
    echo json_encode([
        'success' => true,
        'patient_id' => $patient_id,
        'patient' => $patient,
        'visits' => $output
    ]);
    exit;
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
    exit;
}
