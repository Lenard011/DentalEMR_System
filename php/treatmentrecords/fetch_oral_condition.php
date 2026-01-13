<?php
// fetch_oral_condition.php - UPDATED WITH HISTORY LOGGING
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');
ini_set('display_errors', 0);
error_reporting(0);

// Enable error logging for debugging
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_errors.log');

// Set CORS headers
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE, PATCH");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Credentials: true");

// Handle OPTIONS request for CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/conns.php';

$db = $pdo ?? ($db ?? ($conn ?? null));
if (!$db) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database connection error']);
    exit;
}

// ===========================
// HISTORY LOGGING FUNCTION
// ===========================
function addHistoryLog($db, $tableName, $recordId, $action, $changedByType, $changedById, $oldValues = null, $newValues = null, $description = null)
{
    try {
        $sql = "INSERT INTO history_logs 
                (table_name, record_id, action, changed_by_type, changed_by_id, old_values, new_values, description, ip_address) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $db->prepare($sql);
        if (!$stmt) {
            error_log("Failed to prepare history log statement: " . implode(' ', $db->errorInfo()));
            return false;
        }

        $oldJSON = $oldValues ? json_encode($oldValues, JSON_UNESCAPED_UNICODE) : null;
        $newJSON = $newValues ? json_encode($newValues, JSON_UNESCAPED_UNICODE) : null;
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        $stmt->execute([
            $tableName,
            $recordId,
            $action,
            $changedByType,
            $changedById,
            $oldJSON,
            $newJSON,
            $description,
            $ip
        ]);

        return true;
    } catch (Exception $e) {
        error_log("Error in addHistoryLog: " . $e->getMessage());
        return false;
    }
}

// ===========================
// GET LOGGED USER INFORMATION
// ===========================
function getLoggedUserInfo($input = [])
{
    $userId = 0;
    $userType = 'System';
    $userName = 'System';

    // Check GET parameters
    if (isset($_GET['uid']) && !empty($_GET['uid'])) {
        $userId = intval($_GET['uid']);
        $userType = 'System';
        $userName = 'System User';
    }

    return [
        'id' => $userId,
        'type' => $userType,
        'name' => $userName
    ];
}

// ===========================
// GET PATIENT NAME FUNCTION
// ===========================
function getPatientName($db, $patientId)
{
    try {
        $stmt = $db->prepare("SELECT firstname, surname FROM patients WHERE patient_id = ?");
        $stmt->execute([$patientId]);
        $patient = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($patient) {
            $name = trim(($patient['firstname'] ?? '') . ' ' . ($patient['surname'] ?? ''));
            return $name ?: "Patient ID: $patientId";
        }
        return "Unknown Patient (ID: $patientId)";
    } catch (Exception $e) {
        error_log("Error getting patient name: " . $e->getMessage());
        return "Patient ID: $patientId";
    }
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

    // Get logged user info for potential logging (if uid is provided)
    $loggedUser = getLoggedUserInfo();

    // Log the fetch action
    if ($loggedUser['id'] > 0) {
        addHistoryLog(
            $db,
            "patients",
            $patient_id,
            "VIEW",
            $loggedUser['type'],
            $loggedUser['id'],
            null,
            null,
            "Fetched oral health condition data for patient: " . getPatientName($db, $patient_id)
        );
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
        'visits' => $result,
        'logged_by' => $loggedUser['id'] > 0 ? [
            "type" => $loggedUser['type'],
            "name" => $loggedUser['name'],
            "id" => $loggedUser['id']
        ] : null
    ]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
