<?php
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', '1');
error_reporting(E_ALL);

// Try to include conns.php with multiple possible paths
$conns_path = __DIR__ . '/conns.php';
if (!file_exists($conns_path)) {
    $conns_path = __DIR__ . '/../conns.php';
}

if (!file_exists($conns_path)) {
    echo json_encode(['success' => false, 'error' => 'Database configuration file not found']);
    exit;
}

require_once $conns_path;

// Try different database connection variable names
$db = null;

if (isset($pdo) && $pdo instanceof PDO) {
    $db = $pdo;
} elseif (isset($conn) && $conn instanceof PDO) {
    $db = $conn;
} elseif (isset($db) && $db instanceof PDO) {
    // $db is already set
} else {
    echo json_encode(['success' => false, 'error' => 'Database connection not found']);
    exit;
}

// Simple debug logging function
function debug_log($message)
{
    $log_file = __DIR__ . '/debug.log';
    $timestamp = date('Y-m-d H:i:s');
    $message = $timestamp . ' - ' . $message . PHP_EOL;
    file_put_contents($log_file, $message, FILE_APPEND);
}

// Check if column exists
function columnExists($db, $table, $column)
{
    try {
        $stmt = $db->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?");
        $stmt->execute([$table, $column]);
        return (bool)$stmt->fetchColumn();
    } catch (Exception $e) {
        debug_log("Error checking column $column in $table: " . $e->getMessage());
        return false;
    }
}

// Find condition ID
function resolveConditionId($db, $val, $caseType = 'permanent')
{
    if (!$val) {
        debug_log("resolveConditionId: empty value");
        return null;
    }

    // Clean the value
    $val = trim($val);

    // Special handling for checkmark
    if ($val === '✓' || $val === 'check' || $val === 'Check') {
        $val = '✓';
    }

    // Determine if permanent or temporary
    $isPermanent = ($caseType === 'permanent') ? 1 : 0;
    debug_log("Looking for condition: '$val', caseType: $caseType, isPermanent: $isPermanent");

    // First try exact match with case type
    $columns = ['condition_code', 'code', 'short_code', 'abbreviation', 'symbol', 'name'];
    foreach ($columns as $col) {
        if (!columnExists($db, 'conditions', $col)) continue;

        try {
            $q = $db->prepare("SELECT condition_id FROM conditions WHERE `$col` = ? AND is_permanent = ? LIMIT 1");
            $q->execute([$val, $isPermanent]);
            $r = $q->fetch(PDO::FETCH_ASSOC);
            if ($r) {
                debug_log("Found condition_id: " . $r['condition_id'] . " for '$val' in column $col");
                return (int)$r['condition_id'];
            }
        } catch (Exception $e) {
            debug_log("Error querying conditions table for $col: " . $e->getMessage());
        }
    }

    // Try without is_permanent filter
    foreach ($columns as $col) {
        if (!columnExists($db, 'conditions', $col)) continue;

        try {
            $q = $db->prepare("SELECT condition_id FROM conditions WHERE `$col` = ? LIMIT 1");
            $q->execute([$val]);
            $r = $q->fetch(PDO::FETCH_ASSOC);
            if ($r) {
                debug_log("Found condition_id (fallback): " . $r['condition_id'] . " for '$val'");
                return (int)$r['condition_id'];
            }
        } catch (Exception $e) {
            debug_log("Error querying conditions table (fallback): " . $e->getMessage());
        }
    }

    // Try case-insensitive search
    foreach ($columns as $col) {
        if (!columnExists($db, 'conditions', $col)) continue;

        try {
            $q = $db->prepare("SELECT condition_id FROM conditions WHERE LOWER(`$col`) = LOWER(?) LIMIT 1");
            $q->execute([$val]);
            $r = $q->fetch(PDO::FETCH_ASSOC);
            if ($r) {
                debug_log("Found condition_id (case-insensitive): " . $r['condition_id'] . " for '$val'");
                return (int)$r['condition_id'];
            }
        } catch (Exception $e) {
            debug_log("Error querying conditions table (case-insensitive): " . $e->getMessage());
        }
    }

    debug_log("No condition_id found for: '$val'");
    return null;
}

// Find treatment ID
function resolveTreatmentId($db, $val)
{
    if (!$val) {
        debug_log("resolveTreatmentId: empty value");
        return null;
    }

    $val = trim($val);
    debug_log("Looking for treatment: '$val'");

    $columns = ['treatment_code', 'code', 'short_code', 'abbreviation', 'name', 'description'];
    foreach ($columns as $col) {
        if (!columnExists($db, 'treatments', $col)) continue;

        try {
            $q = $db->prepare("SELECT treatment_id FROM treatments WHERE `$col` = ? LIMIT 1");
            $q->execute([$val]);
            $r = $q->fetch(PDO::FETCH_ASSOC);
            if ($r) {
                debug_log("Found treatment_id: " . $r['treatment_id'] . " for '$val'");
                return (int)$r['treatment_id'];
            }
        } catch (Exception $e) {
            debug_log("Error querying treatments table: " . $e->getMessage());
        }
    }

    // Try case-insensitive
    foreach ($columns as $col) {
        if (!columnExists($db, 'treatments', $col)) continue;

        try {
            $q = $db->prepare("SELECT treatment_id FROM treatments WHERE LOWER(`$col`) = LOWER(?) LIMIT 1");
            $q->execute([$val]);
            $r = $q->fetch(PDO::FETCH_ASSOC);
            if ($r) {
                debug_log("Found treatment_id (case-insensitive): " . $r['treatment_id'] . " for '$val'");
                return (int)$r['treatment_id'];
            }
        } catch (Exception $e) {
            debug_log("Error querying treatments table (case-insensitive): " . $e->getMessage());
        }
    }

    debug_log("No treatment_id found for: '$val'");
    return null;
}

// Find tooth ID
function resolveToothId($db, $val)
{
    if (!$val) {
        debug_log("resolveToothId: empty value");
        return null;
    }

    // Clean the value
    $val = trim($val);
    debug_log("Resolving tooth_id for: '$val'");

    // Try as integer tooth_id
    if (is_numeric($val)) {
        try {
            $q = $db->prepare("SELECT tooth_id FROM teeth WHERE tooth_id = ? LIMIT 1");
            $q->execute([$val]);
            $r = $q->fetch(PDO::FETCH_ASSOC);
            if ($r) {
                debug_log("Found tooth by tooth_id: " . $val . " => " . $r['tooth_id']);
                return (int)$r['tooth_id'];
            }
        } catch (Exception $e) {
            debug_log("Error querying teeth by tooth_id: " . $e->getMessage());
        }
    }

    // Try as FDI number (numeric)
    if (is_numeric($val)) {
        try {
            $q = $db->prepare("SELECT tooth_id FROM teeth WHERE fdi_number = ? LIMIT 1");
            $q->execute([$val]);
            $r = $q->fetch(PDO::FETCH_ASSOC);
            if ($r) {
                debug_log("Found tooth by FDI: " . $val . " => " . $r['tooth_id']);
                return (int)$r['tooth_id'];
            }
        } catch (Exception $e) {
            debug_log("Error querying teeth by FDI: " . $e->getMessage());
        }
    }

    // Last resort: if value is numeric, create fallback mapping
    if (is_numeric($val)) {
        $fdi = (int)$val;

        // Create a simple mapping based on common FDI numbers
        $fdiToId = [
            11 => 1,
            12 => 2,
            13 => 3,
            14 => 4,
            15 => 5,
            16 => 6,
            17 => 7,
            18 => 8,
            21 => 9,
            22 => 10,
            23 => 11,
            24 => 12,
            25 => 13,
            26 => 14,
            27 => 15,
            28 => 16,
            31 => 17,
            32 => 18,
            33 => 19,
            34 => 20,
            35 => 21,
            36 => 22,
            37 => 23,
            38 => 24,
            41 => 25,
            42 => 26,
            43 => 27,
            44 => 28,
            45 => 29,
            46 => 30,
            47 => 31,
            48 => 32,
            51 => 33,
            52 => 34,
            53 => 35,
            54 => 36,
            55 => 37,
            61 => 38,
            62 => 39,
            63 => 40,
            64 => 41,
            65 => 42,
            71 => 43,
            72 => 44,
            73 => 45,
            74 => 46,
            75 => 47,
            81 => 48,
            82 => 49,
            83 => 50,
            84 => 51,
            85 => 52
        ];

        if (isset($fdiToId[$fdi])) {
            debug_log("Using fallback mapping for FDI: " . $fdi . " => " . $fdiToId[$fdi]);
            return $fdiToId[$fdi];
        }

        // If not in mapping but numeric, just use it
        debug_log("Using numeric value as tooth_id: " . $fdi);
        return $fdi;
    }

    debug_log("Could not resolve tooth_id for value: " . $val);
    return null;
}

try {
    // Get input
    $input = file_get_contents('php://input');
    debug_log("Received save request: " . $input);

    $data = json_decode($input, true);
    if (!$data) {
        throw new Exception('Invalid JSON input');
    }

    debug_log("Parsed input: " . print_r($data, true));

    if (($data['action'] ?? '') !== 'save') {
        throw new Exception('Invalid request action');
    }

    $patient_id = $data['patient_id'] ?? null;
    $oral_data = $data['oral_data'] ?? [];
    $visit_id = $data['visit_id'] ?? null;

    if (!$patient_id) {
        throw new Exception('Missing patient_id');
    }

    if (empty($oral_data)) {
        throw new Exception('No oral data provided');
    }

    // Ensure patient exists
    $chk = $db->prepare("SELECT COUNT(*) FROM patients WHERE patient_id = ?");
    $chk->execute([$patient_id]);
    if ($chk->fetchColumn() == 0) {
        throw new Exception("Patient not found with ID: $patient_id");
    }

    $db->beginTransaction();
    debug_log("Starting transaction");

    // Create visit if missing
    if (!$visit_id || $visit_id == 0) {
        $q = $db->prepare("SELECT COALESCE(MAX(visit_number),0)+1 AS nextnum FROM visits WHERE patient_id=?");
        $q->execute([$patient_id]);
        $num = $q->fetch(PDO::FETCH_ASSOC)['nextnum'] ?? 1;

        $q = $db->prepare("INSERT INTO visits (patient_id, visit_date, visit_number) VALUES (?, NOW(), ?)");
        $q->execute([$patient_id, $num]);
        $visit_id = $db->lastInsertId();
        debug_log("Created new visit_id: $visit_id for patient: $patient_id");
    }

    // Prepare statements
    $insCond = $db->prepare("INSERT INTO visittoothcondition (visit_id, tooth_id, condition_id, box_key, color, case_type) VALUES (?, ?, ?, ?, ?, ?)");
    $insTreat = $db->prepare("INSERT INTO visittoothtreatment (visit_id, tooth_id, treatment_id, box_key, color, case_type) VALUES (?, ?, ?, ?, ?, ?)");

    $inserted = 0;
    $warnings = [];

    foreach ($oral_data as $index => $item) {
        debug_log("Processing item $index: " . print_r($item, true));

        $type = $item['type'] ?? '';
        $tooth = resolveToothId($db, $item['tooth_id'] ?? null);
        $box = $item['box_key'] ?? '';

        debug_log("Item details: type=$type, tooth=$tooth, box=$box");

        if (!$tooth) {
            $warnings[] = "Invalid tooth_id: " . ($item['tooth_id'] ?? 'null');
            debug_log("Warning: Invalid tooth_id");
            continue;
        }

        // Normalize color
        $color = strtolower(trim($item['color'] ?? ''));
        if ($color !== 'blue' && $color !== 'red') {
            $color = '';
        }

        // Normalize case type
        $caseType = strtolower(trim($item['case_type'] ?? ''));
        if ($caseType === 'upper' || $caseType === 'permanent') {
            $caseType = 'permanent';
        } elseif ($caseType === 'lower' || $caseType === 'temporary') {
            $caseType = 'temporary';
        } else {
            $caseType = 'permanent';
        }

        if ($type === 'condition') {
            $condCode = $item['condition_code'] ?? null;
            debug_log("Processing condition: '$condCode', caseType: $caseType");

            $cid = resolveConditionId($db, $condCode, $caseType);
            if (!$cid) {
                $warnings[] = "Invalid condition code: $condCode for case type: $caseType";
                debug_log("Warning: Invalid condition code");
                continue;
            }

            debug_log("Inserting condition: visit_id=$visit_id, tooth_id=$tooth, condition_id=$cid, box_key='$box', color='$color', case_type='$caseType'");

            try {
                $insCond->execute([$visit_id, $tooth, $cid, $box, $color, $caseType]);
                $inserted++;
                debug_log("Condition inserted successfully");
            } catch (Exception $e) {
                debug_log("Error inserting condition: " . $e->getMessage());
                $warnings[] = "Failed to insert condition: " . $e->getMessage();
            }
        } elseif ($type === 'treatment') {
            $treatCode = $item['treatment_code'] ?? null;
            debug_log("Processing treatment: '$treatCode'");

            $tid = resolveTreatmentId($db, $treatCode);
            if (!$tid) {
                $warnings[] = "Invalid treatment code: $treatCode";
                debug_log("Warning: Invalid treatment code");
                continue;
            }

            debug_log("Inserting treatment: visit_id=$visit_id, tooth_id=$tooth, treatment_id=$tid, box_key='$box', color='$color', case_type='$caseType'");

            try {
                $insTreat->execute([$visit_id, $tooth, $tid, $box, $color, $caseType]);
                $inserted++;
                debug_log("Treatment inserted successfully");
            } catch (Exception $e) {
                debug_log("Error inserting treatment: " . $e->getMessage());
                $warnings[] = "Failed to insert treatment: " . $e->getMessage();
            }
        } else {
            debug_log("Unknown type: $type");
            $warnings[] = "Unknown data type: $type";
        }
    }

    $db->commit();
    debug_log("Transaction committed successfully. Inserted: $inserted items");

    $response = [
        'success' => true,
        'visit_id' => $visit_id,
        'inserted' => $inserted,
        'warnings' => $warnings
    ];

    debug_log("Sending response: " . json_encode($response));
    echo json_encode($response);
} catch (Exception $e) {
    if ($db && $db->inTransaction()) {
        $db->rollBack();
        debug_log("Transaction rolled back due to error");
    }

    debug_log("Error in oral_condition_api.php: " . $e->getMessage());

    $response = [
        'success' => false,
        'error' => $e->getMessage()
    ];

    echo json_encode($response);
}
