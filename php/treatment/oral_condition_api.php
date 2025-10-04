<?php
// oral_condition_api.php
header('Content-Type: application/json; charset=utf-8');
// don't display raw PHP errors to the browser (prevents HTML output)
ini_set('display_errors', '0');
error_reporting(E_ALL);

require_once __DIR__ . '/conns.php';

// Accept either $db or $pdo from your conns.php
$db = $db ?? ($pdo ?? null);
if (!$db) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database connection not found (expected $db or $pdo in conns.php)']);
    exit;
}

// helper: check if a column exists in a table
function columnExists($db, $table, $column)
{
    $stmt = $db->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?");
    $stmt->execute([$table, $column]);
    return (bool)$stmt->fetchColumn();
}

// helper: try to resolve a treatment id given value (numeric id OR a code/string that lives in some column)
function resolveTreatmentId($db, $value)
{
    if (!$value) return null;

    // if numeric and exists
    if (is_numeric($value)) {
        $stmt = $db->prepare("SELECT treatment_id FROM treatments WHERE treatment_id = ? LIMIT 1");
        $stmt->execute([(int)$value]);
        $r = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($r) return (int)$r['treatment_id'];
    }

    // candidate columns to try search by
    $candidateCols = ['treatment_code', 'code', 'short', 'abbrev', 'abbreviation', 'name', 'treatment_name', 'title'];
    foreach ($candidateCols as $col) {
        if (!columnExists($db, 'treatments', $col)) continue;
        $sql = "SELECT treatment_id FROM treatments WHERE `$col` = ? LIMIT 1";
        $stmt = $db->prepare($sql);
        $stmt->execute([$value]);
        $r = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($r) return (int)$r['treatment_id'];
    }
    return null;
}

// helper: try to resolve a condition id given value
function resolveConditionId($db, $value)
{
    if (!$value) return null;
    if (is_numeric($value)) {
        $stmt = $db->prepare("SELECT condition_id FROM conditions WHERE condition_id = ? LIMIT 1");
        $stmt->execute([(int)$value]);
        $r = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($r) return (int)$r['condition_id'];
    }
    $candidateCols = ['condition_code', 'code', 'short', 'abbrev', 'abbreviation', 'symbol', 'name', 'condition_name', 'label'];
    foreach ($candidateCols as $col) {
        if (!columnExists($db, 'conditions', $col)) continue;
        $sql = "SELECT condition_id FROM conditions WHERE `$col` = ? LIMIT 1";
        $stmt = $db->prepare($sql);
        $stmt->execute([$value]);
        $r = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($r) return (int)$r['condition_id'];
    }
    return null;
}

// helper: resolve tooth_id (accept either real tooth_id or FDI number)
function resolveToothId($db, $value)
{
    if (!$value && $value !== 0 && $value !== '0') return null;
    // try by tooth_id
    $stmt = $db->prepare("SELECT tooth_id FROM teeth WHERE tooth_id = ? LIMIT 1");
    $stmt->execute([$value]);
    $r = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($r) return (int)$r['tooth_id'];
    // then try by fdi_number
    $stmt = $db->prepare("SELECT tooth_id FROM teeth WHERE fdi_number = ? LIMIT 1");
    $stmt->execute([$value]);
    $r = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($r) return (int)$r['tooth_id'];
    return null;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input || !isset($input['action']) || $input['action'] !== 'save') {
        throw new Exception('Invalid request');
    }

    $patient_id = $input['patient_id'] ?? null;
    $oral_data = $input['oral_data'] ?? [];
    $visit_id = $input['visit_id'] ?? null;

    if (!$patient_id) throw new Exception('Missing patient_id');

    // verify patient exists
    $stmt = $db->prepare("SELECT COUNT(*) FROM patients WHERE patient_id = ?");
    $stmt->execute([$patient_id]);
    if ($stmt->fetchColumn() == 0) throw new Exception('Patient ID does not exist');

    // start transaction
    $db->beginTransaction();

    // create visit if missing
    if (!$visit_id || $visit_id == 0) {
        $stmt = $db->prepare("SELECT visit_number FROM visits WHERE patient_id = ? ORDER BY visit_date DESC LIMIT 1");
        $stmt->execute([$patient_id]);
        $r = $stmt->fetch(PDO::FETCH_ASSOC);
        $visit_number = $r ? ($r['visit_number'] + 1) : 1;
        $stmt = $db->prepare("INSERT INTO visits (patient_id, visit_date, visit_number) VALUES (?, NOW(), ?)");
        $stmt->execute([$patient_id, $visit_number]);
        $visit_id = (int)$db->lastInsertId();
    }

    // prepare insert statements
    $stmt_insert_condition = $db->prepare("INSERT INTO visittoothcondition (visit_id, tooth_id, condition_id, box_key) VALUES (?, ?, ?, ?)");
    $stmt_insert_treatment = $db->prepare("INSERT INTO visittoothtreatment (visit_id, tooth_id, treatment_id, box_key, color, case_type) VALUES (?, ?, ?, ?, ?, ?)");

    $warnings = [];
    $inserted = 0;

    foreach ($oral_data as $item) {
        if (!isset($item['type'])) continue;

        if ($item['type'] === 'condition') {
            // resolve tooth_id
            $rawTooth = $item['tooth_id'] ?? null;
            $realToothId = resolveToothId($db, $rawTooth);
            if (!$realToothId) {
                $warnings[] = "Skipped condition (tooth not found): " . json_encode($rawTooth);
                continue;
            }

            // resolve condition id (accept condition_code OR numeric passed)
            $condId = null;
            if (isset($item['condition_id']) && $item['condition_id']) $condId = resolveConditionId($db, $item['condition_id']);
            if (!$condId && isset($item['condition_code'])) $condId = resolveConditionId($db, $item['condition_code']);
            if (!$condId) {
                $warnings[] = "Skipped condition (condition not found): " . json_encode($item);
                continue;
            }

            // insert
            $stmt_insert_condition->execute([$visit_id, $realToothId, $condId, $item['box_key'] ?? null]);
            $inserted++;
        } elseif ($item['type'] === 'treatment') {
            // resolve treatment id (accept number or code)
            $tId = null;
            if (isset($item['treatment_id']) && $item['treatment_id']) $tId = resolveTreatmentId($db, $item['treatment_id']);
            if (!$tId && isset($item['treatment_code'])) $tId = resolveTreatmentId($db, $item['treatment_code']);
            if (!$tId) {
                $warnings[] = "Skipped treatment (treatment not found): " . json_encode($item);
                continue;
            }

            // tooth may be optional for treatments; if present resolve it
            $rawTooth = $item['tooth_id'] ?? null;
            $realToothId = $rawTooth ? resolveToothId($db, $rawTooth) : null;

            // insert (tooth_id can be null if not applicable)
            $stmt_insert_treatment->execute([$visit_id, $realToothId, $tId, $item['box_key'] ?? null, $item['color'] ?? null, $item['case_type'] ?? null]);
            $inserted++;
        }
    }

    $db->commit();
    echo json_encode(['success' => true, 'visit_id' => $visit_id, 'inserted' => $inserted, 'warnings' => $warnings]);
    exit;
} catch (Exception $e) {
    if ($db && $db->inTransaction()) $db->rollBack();
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}
