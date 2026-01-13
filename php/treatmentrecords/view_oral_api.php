<?php
// ===========================
// BACKEND API - view_oral_api.php
// ===========================
session_start();

// Enable error logging for debugging
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_errors.log');
error_reporting(E_ALL);

// Set CORS headers FIRST (before any output)
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE, PATCH");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Credentials: true");

// Add after setting Content-Type header
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Handle OPTIONS request for CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Database connection
$host = "localhost";
$dbUser = "u401132124_dentalclinic";
$dbPass = "Mho_DentalClinic1st";
$dbName = "u401132124_mho_dentalemr";

// Use mysqli with proper error handling
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli($host, $dbUser, $dbPass, $dbName);
    $conn->set_charset("utf8mb4");
    $conn->options(MYSQLI_OPT_CONNECT_TIMEOUT, 5);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed: ' . $e->getMessage(),
        'data' => []
    ]);
    exit;
}

// Set default timezone
date_default_timezone_set('Asia/Manila');

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
    // Check GET parameters
    elseif (isset($_GET['uid']) && !empty($_GET['uid'])) {
        $userId = intval($_GET['uid']);
        $userType = 'System';
        $userName = 'System User';
    }
    // Check session (for API calls from view_oral.php)
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

// ===========================
// GET REQUEST HANDLER
// ===========================
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Get patient ID from request
    $patient_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    $record_id = isset($_GET['record']) ? intval($_GET['record']) : 0;

    // Validate patient ID
    if ($patient_id <= 0 && $record_id <= 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Missing or invalid patient ID',
            'data' => []
        ]);
        exit;
    }

    try {
        if ($record_id > 0) {
            // Fetch specific record
            $sql = "SELECT 
                        o.*,
                        CONCAT(p.firstname, ' ', COALESCE(p.middlename, ''), ' ', p.surname) AS patient_name
                    FROM oral_health_condition o
                    LEFT JOIN patients p ON o.patient_id = p.patient_id
                    WHERE o.id = ?";

            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $record_id);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $record = $result->fetch_assoc();

                // Format checkmark fields properly
                $check_fields = [
                    'orally_fit_child',
                    'dental_caries',
                    'gingivitis',
                    'periodontal_disease',
                    'debris',
                    'calculus',
                    'abnormal_growth',
                    'cleft_palate'
                ];

                foreach ($check_fields as $field) {
                    if (isset($record[$field])) {
                        $value = trim($record[$field]);
                        $record[$field . '_bool'] = ($value === '✓' || $value === '1' || $value === 'true' || $value === 'yes');
                    }
                }

                echo json_encode([
                    'success' => true,
                    'message' => 'Record found',
                    'data' => $record
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Record not found',
                    'data' => []
                ]);
            }
            $stmt->close();
        } else {
            // Fetch all records for patient
            $sql = "SELECT 
                        o.*,
                        CONCAT(p.firstname, ' ', COALESCE(p.middlename, ''), ' ', p.surname) AS patient_name
                    FROM oral_health_condition o
                    LEFT JOIN patients p ON o.patient_id = p.patient_id
                    WHERE o.patient_id = ?
                    ORDER BY o.created_at DESC, o.id DESC";

            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $patient_id);
            $stmt->execute();
            $result = $stmt->get_result();

            $records = [];

            if ($result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    // Ensure all fields have proper values
                    $row['id'] = intval($row['id']);
                    $row['patient_id'] = intval($row['patient_id']);

                    // Convert numeric fields
                    $numeric_fields = [
                        'perm_total_dmf',
                        'perm_teeth_present',
                        'perm_sound_teeth',
                        'perm_decayed_teeth_d',
                        'perm_missing_teeth_m',
                        'perm_filled_teeth_f',
                        'temp_total_df',
                        'temp_teeth_present',
                        'temp_sound_teeth',
                        'temp_decayed_teeth_d',
                        'temp_filled_teeth_f'
                    ];

                    foreach ($numeric_fields as $field) {
                        $row[$field] = isset($row[$field]) ? intval($row[$field]) : 0;
                    }

                    // Add boolean versions of checkmark fields
                    $check_fields = [
                        'orally_fit_child',
                        'dental_caries',
                        'gingivitis',
                        'periodontal_disease',
                        'debris',
                        'calculus',
                        'abnormal_growth',
                        'cleft_palate'
                    ];

                    foreach ($check_fields as $field) {
                        if (isset($row[$field])) {
                            $value = trim($row[$field]);
                            $row[$field . '_bool'] = ($value === '✓' || $value === '1' || $value === 'true' || $value === 'yes');
                        } else {
                            $row[$field . '_bool'] = false;
                        }
                    }

                    // Ensure 'others' field exists
                    $row['others'] = isset($row['others']) ? $row['others'] : '';

                    $records[] = $row;
                }

                echo json_encode([
                    'success' => true,
                    'message' => 'Records found',
                    'count' => count($records),
                    'data' => $records
                ]);
            } else {
                echo json_encode([
                    'success' => true,
                    'message' => 'No records found',
                    'count' => 0,
                    'data' => []
                ]);
            }

            $stmt->close();
        }
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Database error: ' . $e->getMessage(),
            'data' => []
        ]);
    }
}

// ===========================
// POST REQUEST HANDLER (For saving OHC with history logs)
// ===========================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = [];

    if (!empty($_POST)) {
        $input = $_POST;
    } else {
        $rawInput = file_get_contents('php://input');

        if (!empty($rawInput)) {
            $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

            if (strpos($contentType, 'application/json') !== false) {
                $input = json_decode($rawInput, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    echo json_encode([
                        "success" => false,
                        "message" => "Invalid JSON input",
                        "details" => json_last_error_msg()
                    ]);
                    exit;
                }
            } else if (strpos($contentType, 'application/x-www-form-urlencoded') !== false) {
                parse_str($rawInput, $input);
            }
        }
    }

    // Check if this is an OHC save request (from save_ohc.php or direct API)
    $isOHCSave = isset($input['patient_id']) && (
        isset($input['orally_fit_child']) ||
        isset($input['dental_caries']) ||
        isset($input['perm_teeth_present']) ||
        isset($input['temp_teeth_present'])
    );

    if ($isOHCSave) {
        handleSaveOHC($conn, $input);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid request or action not specified',
            'data' => []
        ]);
    }
}

// ===========================
// SAVE OHC WITH HISTORY LOGS
// ===========================
function handleSaveOHC($conn, $input)
{
    $patientId = isset($input['patient_id']) ? intval($input['patient_id']) : 0;

    if ($patientId <= 0) {
        echo json_encode([
            "success" => false,
            "message" => "Valid patient_id required."
        ]);
        exit;
    }

    try {
        // Get logged user info for logging
        $loggedUser = getLoggedUserInfo($conn, $input);
        $patientName = getPatientName($conn, $patientId);

        // Get current OHC data if exists (for UPDATE)
        $currentRecordId = isset($input['record_id']) ? intval($input['record_id']) : 0;
        $oldData = null;

        if ($currentRecordId > 0) {
            $stmt = $conn->prepare("SELECT * FROM oral_health_condition WHERE id = ?");
            $stmt->bind_param("i", $currentRecordId);
            $stmt->execute();
            $result = $stmt->get_result();
            $oldData = $result->fetch_assoc();
            $stmt->close();
        }

        // Prepare data for INSERT or UPDATE
        $fields = [
            'patient_id',
            'examination_date',
            'orally_fit_child',
            'dental_caries',
            'gingivitis',
            'periodontal_disease',
            'debris',
            'calculus',
            'abnormal_growth',
            'cleft_palate',
            'others',
            'perm_teeth_present',
            'perm_sound_teeth',
            'perm_decayed_teeth_d',
            'perm_missing_teeth_m',
            'perm_filled_teeth_f',
            'perm_total_dmf',
            'temp_teeth_present',
            'temp_sound_teeth',
            'temp_decayed_teeth_d',
            'temp_filled_teeth_f',
            'temp_total_df'
        ];

        // Build SQL and values
        $values = [];
        $placeholders = [];
        $types = '';

        foreach ($fields as $field) {
            if (isset($input[$field])) {
                $values[] = $input[$field];
                $placeholders[] = '?';

                if ($field === 'patient_id') {
                    $types .= 'i';
                } elseif (in_array($field, [
                    'perm_teeth_present',
                    'perm_sound_teeth',
                    'perm_decayed_teeth_d',
                    'perm_missing_teeth_m',
                    'perm_filled_teeth_f',
                    'perm_total_dmf',
                    'temp_teeth_present',
                    'temp_sound_teeth',
                    'temp_decayed_teeth_d',
                    'temp_filled_teeth_f',
                    'temp_total_df'
                ])) {
                    $types .= 'i';
                } else {
                    $types .= 's';
                }
            }
        }

        // Check if record exists (for UPDATE)
        $recordExists = false;
        if ($currentRecordId > 0) {
            $checkStmt = $conn->prepare("SELECT id FROM oral_health_condition WHERE id = ?");
            $checkStmt->bind_param("i", $currentRecordId);
            $checkStmt->execute();
            $checkStmt->store_result();
            $recordExists = $checkStmt->num_rows > 0;
            $checkStmt->close();
        }

        if ($recordExists) {
            // UPDATE existing record
            $sql = "UPDATE oral_health_condition SET ";
            $setParts = [];

            foreach ($fields as $field) {
                if (isset($input[$field]) && $field !== 'patient_id') {
                    $setParts[] = "$field = ?";
                }
            }

            $sql .= implode(", ", $setParts);
            $sql .= " WHERE id = ?";

            // Add record ID to values
            $values[] = $currentRecordId;
            $types .= 'i';

            $action = "UPDATE";
            $recordId = $currentRecordId;
        } else {
            // INSERT new record
            $fieldNames = implode(", ", array_filter($fields, function ($field) use ($input) {
                return isset($input[$field]);
            }));

            $sql = "INSERT INTO oral_health_condition ($fieldNames, created_at) VALUES (" .
                implode(", ", $placeholders) . ", NOW())";

            $action = "CREATE";
            $recordId = null; // Will be set after insert
        }

        // Execute query
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Database prepare failed: " . $conn->error);
        }

        $stmt->bind_param($types, ...$values);

        if (!$stmt->execute()) {
            throw new Exception("Failed to save oral health condition: " . $stmt->error);
        }

        if (!$recordExists) {
            $recordId = $stmt->insert_id;
        }

        $stmt->close();

        // Prepare new data for logging
        $newData = [];
        foreach ($fields as $field) {
            if (isset($input[$field])) {
                $newData[$field] = $input[$field];
            }
        }

        // Log the action
        addHistoryLog(
            $conn,
            "oral_health_condition",
            $recordId,
            $action,
            $loggedUser['type'],
            $loggedUser['id'],
            $oldData,
            $newData,
            "{$action}d oral health condition for patient: $patientName (ID: $patientId) by {$loggedUser['type']} {$loggedUser['name']}"
        );

        echo json_encode([
            "success" => true,
            "message" => "Oral health condition saved successfully",
            "record_id" => $recordId,
            "action" => $action,
            "logged_by" => [
                "type" => $loggedUser['type'],
                "name" => $loggedUser['name'],
                "id" => $loggedUser['id']
            ]
        ]);
    } catch (Exception $e) {
        error_log("Exception in handleSaveOHC: " . $e->getMessage());
        echo json_encode([
            "success" => false,
            "message" => "Database error: " . $e->getMessage()
        ]);
    }
}

$conn->close();
