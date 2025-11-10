<?php
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/conns.php';

$db = $pdo ?? ($db ?? ($conn ?? null));
if (!$db) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database connection not found. Expected $pdo, $db, or $conn in conns.php'
    ]);
    exit;
}

function romanNumeral($num): string {
    $map = [1=>'I',2=>'II',3=>'III',4=>'IV',5=>'V'];
    return $map[$num] ?? (string)$num;
}

try {
    if (empty($_GET['patient_id'])) throw new Exception('Missing patient_id');
    $patient_id = (int)$_GET['patient_id'];

    // Fetch visits (max 5)
    $stmt = $db->prepare("
        SELECT visit_id, visit_date, visit_number
        FROM visits
        WHERE patient_id = ?
        ORDER BY visit_number ASC
        LIMIT 5
    ");
    $stmt->execute([$patient_id]);
    $visits = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $output = [];
    foreach ($visits as $v) {
        $visit_id = $v['visit_id'];

        // Fetch conditions
        $stmtCond = $db->prepare("
            SELECT 
                vc.box_key,
                vc.color,
                vc.case_type,
                CASE
                    WHEN t.type='temporary' OR c.is_permanent=0 THEN LOWER(c.code)
                    ELSE UPPER(c.code)
                END AS condition_code
            FROM visittoothcondition vc
            LEFT JOIN teeth t ON vc.tooth_id = t.tooth_id
            LEFT JOIN conditions c ON vc.condition_id = c.condition_id
            WHERE vc.visit_id = ?
        ");
        $stmtCond->execute([$visit_id]);
        $conditions = $stmtCond->fetchAll(PDO::FETCH_ASSOC);

        // Fetch treatments
        $stmtTreat = $db->prepare("
            SELECT 
                vt.box_key,
                tr.code AS treatment_code,
                vt.color,
                vt.case_type
            FROM visittoothtreatment vt
            LEFT JOIN treatments tr ON vt.treatment_id = tr.treatment_id
            WHERE vt.visit_id = ?
        ");
        $stmtTreat->execute([$visit_id]);
        $treatments = $stmtTreat->fetchAll(PDO::FETCH_ASSOC);

        $output[] = [
            'visit_id' => $visit_id,
            'visit_number' => $v['visit_number'],
            'visit_label' => 'Year ' . romanNumeral($v['visit_number']),
            'visit_date' => $v['visit_date'],
            'conditions' => $conditions,
            'treatments' => $treatments
        ];
    }

    echo json_encode([
        'success' => true,
        'visits' => $output
    ]);

} catch(Exception $e){
    http_response_code(400);
    echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}
