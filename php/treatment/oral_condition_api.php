<?php
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/conns.php';

$db = $db ?? ($pdo ?? null);
if (!$db) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database connection not found']);
    exit;
}

function columnExists($db, $table, $column)
{
    $stmt = $db->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?");
    $stmt->execute([$table, $column]);
    return (bool)$stmt->fetchColumn();
}

function resolveConditionId($db, $val)
{
    if (!$val) return null;
    $columns = ['condition_code', 'code', 'short', 'abbreviation', 'symbol', 'name'];
    foreach ($columns as $col) {
        if (!columnExists($db, 'conditions', $col)) continue;
        $q = $db->prepare("SELECT condition_id FROM conditions WHERE `$col` = ? LIMIT 1");
        $q->execute([$val]);
        $r = $q->fetch(PDO::FETCH_ASSOC);
        if ($r) return (int)$r['condition_id'];
    }
    return null;
}

function resolveTreatmentId($db, $val)
{
    if (!$val) return null;
    $columns = ['treatment_code', 'code', 'short', 'abbreviation', 'name'];
    foreach ($columns as $col) {
        if (!columnExists($db, 'treatments', $col)) continue;
        $q = $db->prepare("SELECT treatment_id FROM treatments WHERE `$col` = ? LIMIT 1");
        $q->execute([$val]);
        $r = $q->fetch(PDO::FETCH_ASSOC);
        if ($r) return (int)$r['treatment_id'];
    }
    return null;
}

function resolveToothId($db, $val)
{
    if (!$val) return null;
    $q = $db->prepare("SELECT tooth_id FROM teeth WHERE tooth_id = ? OR fdi_number = ? LIMIT 1");
    $q->execute([$val, $val]);
    $r = $q->fetch(PDO::FETCH_ASSOC);
    return $r ? (int)$r['tooth_id'] : null;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    if (($input['action'] ?? '') !== 'save') throw new Exception('Invalid request');

    $patient_id = $input['patient_id'] ?? null;
    $oral_data = $input['oral_data'] ?? [];
    $visit_id = $input['visit_id'] ?? null;
    if (!$patient_id) throw new Exception('Missing patient_id');

    // Ensure patient exists
    $chk = $db->prepare("SELECT COUNT(*) FROM patients WHERE patient_id = ?");
    $chk->execute([$patient_id]);
    if ($chk->fetchColumn() == 0) throw new Exception("Patient not found");

    $db->beginTransaction();

    // create visit if missing
    if (!$visit_id || $visit_id == 0) {
        $q = $db->prepare("SELECT COALESCE(MAX(visit_number),0)+1 AS nextnum FROM visits WHERE patient_id=?");
        $q->execute([$patient_id]);
        $num = $q->fetch(PDO::FETCH_ASSOC)['nextnum'] ?? 1;
        $q = $db->prepare("INSERT INTO visits (patient_id, visit_date, visit_number) VALUES (?, NOW(), ?)");
        $q->execute([$patient_id, $num]);
        $visit_id = $db->lastInsertId();
    }

    $insCond = $db->prepare("INSERT INTO visittoothcondition (visit_id,tooth_id,condition_id,box_key,color,case_type)
                             VALUES (?,?,?,?,?,?)");
    $insTreat = $db->prepare("INSERT INTO visittoothtreatment (visit_id,tooth_id,treatment_id,box_key,color,case_type)
                              VALUES (?,?,?,?,?,?)");

    $inserted = 0;
    $warnings = [];

    foreach ($oral_data as $item) {
        $type = $item['type'] ?? '';
        $tooth = resolveToothId($db, $item['tooth_id'] ?? null);
        $box = $item['box_key'] ?? '';

        // ✅ Normalize color and case type
        $color = strtolower(trim($item['color'] ?? ''));
        if ($color !== 'blue' && $color !== 'red') $color = '';

        $caseType = strtolower(trim($item['case_type'] ?? ''));
        if ($caseType === 'upper' || $caseType === 'permanent') {
            $caseType = 'permanent';
        } elseif ($caseType === 'lower' || $caseType === 'temporary') {
            $caseType = 'temporary';
        } else {
            $caseType = '';
        }

        if ($type === 'condition') {
            $cid = resolveConditionId($db, $item['condition_code'] ?? null);
            if (!$cid) {
                $warnings[] = "Invalid condition: " . json_encode($item);
                continue;
            }
            $insCond->execute([$visit_id, $tooth, $cid, $box, $color, $caseType]);
            $inserted++;
        } elseif ($type === 'treatment') {
            $tid = resolveTreatmentId($db, $item['treatment_code'] ?? null);
            if (!$tid) {
                $warnings[] = "Invalid treatment: " . json_encode($item);
                continue;
            }
            $insTreat->execute([$visit_id, $tooth, $tid, $box, $color, $caseType]);
            $inserted++;
        }
    }

    $db->commit();

    echo json_encode(['success' => true, 'visit_id' => $visit_id, 'inserted' => $inserted, 'warnings' => $warnings]);
} catch (Exception $e) {
    if ($db && $db->inTransaction()) $db->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
