<?php
header('Content-Type: application/json');
session_start();
date_default_timezone_set('Asia/Manila');

// Include database connection
require_once '../conn.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);

// ===========================
// HISTORY LOGGING FUNCTION
// ===========================
function addHistoryLog($conn, $tableName, $recordId, $action, $changedByType, $changedById, $oldValues = null, $newValues = null, $description = null)
{
    try {
        $sql = "INSERT INTO history_logs 
                (table_name, record_id, action, changed_by_type, changed_by_id, old_values, new_values, description, ip_address) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            error_log("Failed to prepare history log statement: " . $conn->error);
            return false;
        }

        $oldJSON = $oldValues ? json_encode($oldValues, JSON_UNESCAPED_UNICODE) : null;
        $newJSON = $newValues ? json_encode($newValues, JSON_UNESCAPED_UNICODE) : null;
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        $stmt->bind_param(
            "sississss",
            $tableName,
            $recordId,
            $action,
            $changedByType,
            $changedById,
            $oldJSON,
            $newJSON,
            $description,
            $ip
        );

        $success = $stmt->execute();
        if (!$success) {
            error_log("Failed to execute history log: " . $stmt->error);
        }

        $stmt->close();
        return $success;
    } catch (Exception $e) {
        error_log("Error in addHistoryLog: " . $e->getMessage());
        return false;
    }
}

// ===========================
// GET USER INFORMATION FUNCTION
// ===========================
function getLoggedUserInfo($conn, $input = [])
{
    $userId = 0;
    $userType = 'System';
    $userName = 'System';

    // Check POST data first (from form submissions)
    if (!empty($input['uid'])) {
        $userId = intval($input['uid']);
        $userType = $input['user_type'] ?? 'System';
        $userName = $input['user_name'] ?? 'System User';
    }
    // Check session
    elseif (isset($_SESSION['active_sessions'])) {
        foreach ($_SESSION['active_sessions'] as $session) {
            if (isset($session['id']) && isset($session['type'])) {
                $userId = $session['id'];
                $userType = $session['type'];
                $userName = $session['name'] ?? $session['email'] ?? 'System User';
                break;
            }
        }
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
function getPatientName($conn, $patientId)
{
    try {
        $stmt = $conn->prepare("SELECT firstname, surname FROM patients WHERE patient_id = ?");
        $stmt->bind_param("i", $patientId);
        $stmt->execute();
        $result = $stmt->get_result();
        $patient = $result->fetch_assoc();
        $stmt->close();

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
    // Check if it's a POST request
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Invalid request method");
    }

    // Get POST data
    $patient_id = isset($_POST['patient_id']) ? intval($_POST['patient_id']) : 0;

    if ($patient_id <= 0) {
        throw new Exception("Invalid patient ID.");
    }

    // Get user information for history logging
    $loggedUser = getLoggedUserInfo($conn, $_POST);
    $patientName = getPatientName($conn, $patient_id);

    // Handle examination date
    $examination_date = isset($_POST['examination_date']) ? trim($_POST['examination_date']) : '';

    // Validate and format the date
    if (!empty($examination_date)) {
        // Validate date format (YYYY-MM-DD)
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $examination_date)) {
            throw new Exception("Invalid date format. Please use YYYY-MM-DD format.");
        }

        // Convert to MySQL datetime format
        $examination_datetime = $examination_date . ' ' . date('H:i:s');
    } else {
        // Use current date/time if not provided
        $examination_datetime = date('Y-m-d H:i:s');
        $examination_date = date('Y-m-d');
    }

    // Collect all fields
    $fields = [
        'orally_fit_child' => isset($_POST['orally_fit_child']) ? trim($_POST['orally_fit_child']) : '',
        'dental_caries' => isset($_POST['dental_caries']) ? trim($_POST['dental_caries']) : '',
        'gingivitis' => isset($_POST['gingivitis']) ? trim($_POST['gingivitis']) : '',
        'periodontal_disease' => isset($_POST['periodontal_disease']) ? trim($_POST['periodontal_disease']) : '',
        'debris' => isset($_POST['debris']) ? trim($_POST['debris']) : '',
        'calculus' => isset($_POST['calculus']) ? trim($_POST['calculus']) : '',
        'abnormal_growth' => isset($_POST['abnormal_growth']) ? trim($_POST['abnormal_growth']) : '',
        'cleft_palate' => isset($_POST['cleft_palate']) ? trim($_POST['cleft_palate']) : '',
        'others' => isset($_POST['others']) ? trim($_POST['others']) : '',
        'perm_teeth_present' => isset($_POST['perm_teeth_present']) ? intval($_POST['perm_teeth_present']) : 0,
        'perm_sound_teeth' => isset($_POST['perm_sound_teeth']) ? intval($_POST['perm_sound_teeth']) : 0,
        'perm_decayed_teeth_d' => isset($_POST['perm_decayed_teeth_d']) ? intval($_POST['perm_decayed_teeth_d']) : 0,
        'perm_missing_teeth_m' => isset($_POST['perm_missing_teeth_m']) ? intval($_POST['perm_missing_teeth_m']) : 0,
        'perm_filled_teeth_f' => isset($_POST['perm_filled_teeth_f']) ? intval($_POST['perm_filled_teeth_f']) : 0,
        'perm_total_dmf' => isset($_POST['perm_total_dmf']) ? intval($_POST['perm_total_dmf']) : 0,
        'temp_teeth_present' => isset($_POST['temp_teeth_present']) ? intval($_POST['temp_teeth_present']) : 0,
        'temp_sound_teeth' => isset($_POST['temp_sound_teeth']) ? intval($_POST['temp_sound_teeth']) : 0,
        'temp_decayed_teeth_d' => isset($_POST['temp_decayed_teeth_d']) ? intval($_POST['temp_decayed_teeth_d']) : 0,
        'temp_filled_teeth_f' => isset($_POST['temp_filled_teeth_f']) ? intval($_POST['temp_filled_teeth_f']) : 0,
        'temp_total_df' => isset($_POST['temp_total_df']) ? intval($_POST['temp_total_df']) : 0
    ];

    // Prepare the new data for history logging
    $newData = array_merge([
        'patient_id' => $patient_id,
        'examination_date' => $examination_date,
        'created_at' => $examination_datetime
    ], $fields);

    // Prepare SQL statement
    $sql = "INSERT INTO oral_health_condition (
        patient_id, orally_fit_child, dental_caries, gingivitis, periodontal_disease,
        others, debris, calculus, abnormal_growth, cleft_palate,
        perm_teeth_present, perm_sound_teeth, perm_decayed_teeth_d,
        perm_missing_teeth_m, perm_filled_teeth_f, perm_total_dmf,
        temp_teeth_present, temp_sound_teeth, temp_decayed_teeth_d,
        temp_filled_teeth_f, temp_total_df, created_at, updated_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    // Debug: Check field values
    error_log("Examination datetime: " . $examination_datetime);

    // Bind parameters - FIXED: 23 parameters total (22 fields + NOW() for updated_at)
    $bind_result = $stmt->bind_param(
        "isssssssssiiiiiiiiiiis", // Changed to 22 'i's and 's's
        $patient_id,
        $fields['orally_fit_child'],
        $fields['dental_caries'],
        $fields['gingivitis'],
        $fields['periodontal_disease'],
        $fields['others'],
        $fields['debris'],
        $fields['calculus'],
        $fields['abnormal_growth'],
        $fields['cleft_palate'],
        $fields['perm_teeth_present'],
        $fields['perm_sound_teeth'],
        $fields['perm_decayed_teeth_d'],
        $fields['perm_missing_teeth_m'],
        $fields['perm_filled_teeth_f'],
        $fields['perm_total_dmf'],
        $fields['temp_teeth_present'],
        $fields['temp_sound_teeth'],
        $fields['temp_decayed_teeth_d'],
        $fields['temp_filled_teeth_f'],
        $fields['temp_total_df'],
        $examination_datetime
    );

    if (!$bind_result) {
        throw new Exception("Bind failed: " . $stmt->error);
    }

    // Execute the statement
    if ($stmt->execute()) {
        $recordId = $stmt->insert_id;

        // Log the history
        addHistoryLog(
            $conn,
            "oral_health_condition",
            $recordId,
            "CREATE",
            $loggedUser['type'],
            $loggedUser['id'],
            null, // No old values for new record
            $newData,
            "Added oral health condition for patient: $patientName (ID: $patientId) by {$loggedUser['type']} {$loggedUser['name']}"
        );

        $response = [
            'success' => true,
            'message' => 'Oral health record saved successfully.',
            'record_id' => $recordId,
            'examination_date' => $examination_date,
            'logged_by' => [
                'type' => $loggedUser['type'],
                'name' => $loggedUser['name'],
                'id' => $loggedUser['id']
            ]
        ];

        echo json_encode($response);
    } else {
        throw new Exception("Execute failed: " . $stmt->error);
    }

    $stmt->close();
} catch (Exception $e) {
    error_log("Save OHC Error: " . $e->getMessage());

    $response = [
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ];

    echo json_encode($response);
}

$conn->close();
exit;
