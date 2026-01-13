<?php
session_start();
date_default_timezone_set('Asia/Manila');

// Enable error logging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if we're in offline mode
$isOfflineMode = isset($_GET['offline']) && $_GET['offline'] === 'true';

// ... (rest of session validation code remains the same) ...

/*
|--------------------------------------------------------------------------
| DATABASE CONNECTION (Online mode only)
|--------------------------------------------------------------------------
*/
$conn = null;
$dbError = false;
$dbErrorMessage = '';

if (!$isOfflineMode) {
    include "../conn.php";

    if (!$conn || !($conn instanceof mysqli)) {
        $dbError = true;
        $dbErrorMessage = "Database connection failed to initialize";
        error_log("DB Error: Connection failed");
    } elseif ($conn->connect_error) {
        $dbError = true;
        $dbErrorMessage = $conn->connect_error;
        error_log("DB Error: " . $conn->connect_error);
    }
}

/*
|--------------------------------------------------------------------------
| SYSTEM LOGS FUNCTION - SIMPLIFIED VERSION
|--------------------------------------------------------------------------
*/
function addSystemLog($conn, $userId, $userType, $action, $actionType, $tableName, $recordId, $description)
{
    if (!$conn || !($conn instanceof mysqli)) {
        error_log("System Log: No database connection");
        return false;
    }

    // Simple insert without optional fields first
    $sql = "INSERT INTO system_logs 
            (user_id, user_type, action, action_type, table_name, record_id, description, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        error_log("System Log: Prepare failed - " . $conn->error);
        return false;
    }

    // Convert user ID to integer
    $logUserId = is_numeric($userId) ? intval($userId) : 0;

    // Validate user type
    $validUserTypes = ['Dentist', 'Staff', 'Patient', 'System', 'Offline'];
    if (!in_array($userType, $validUserTypes)) {
        $userType = 'Dentist';
    }

    // Validate action type
    $actionType = strtoupper($actionType);
    $validActionTypes = ['INSERT', 'UPDATE', 'DELETE', 'LOGIN', 'LOGOUT', 'SYNC', 'ERROR', 'ARCHIVE', 'RESTORE'];
    if (!in_array($actionType, $validActionTypes)) {
        $actionType = 'INSERT';
    }

    // Convert recordId
    $recordIdInt = is_numeric($recordId) ? intval($recordId) : 0;

    $stmt->bind_param(
        "issssis",
        $logUserId,
        $userType,
        $action,
        $actionType,
        $tableName,
        $recordIdInt,
        $description
    );

    $result = $stmt->execute();

    if (!$result) {
        error_log("System Log: Execute failed - " . $stmt->error);
        error_log("SQL: " . $sql);
        error_log("Params: user_id=$logUserId, user_type=$userType, action=$action, action_type=$actionType, table_name=$tableName, record_id=$recordIdInt");
    } else {
        error_log("System Log: Success - ID: " . $stmt->insert_id);
    }

    $stmt->close();
    return $result;
}

/*
|--------------------------------------------------------------------------
| DEFAULT RESPONSE STRUCTURE
|--------------------------------------------------------------------------
*/
$response = [
    'status'  => 'error',
    'title'   => '',
    'message' => '',
    'isOffline' => $isOfflineMode,
    'debug'   => []
];

/*
|--------------------------------------------------------------------------
| MAIN POST HANDLER
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Set header for JSON responses
    header('Content-Type: application/json');

    error_log("=== PATIENT FORM SUBMISSION STARTED ===");
    error_log("POST Data: " . print_r($_POST, true));
    error_log("Is Offline Mode: " . ($isOfflineMode ? 'Yes' : 'No'));

    // Check if this is an offline sync request
    $isOfflineSync = isset($_POST['offline_sync']) && $_POST['offline_sync'] === 'true';

    if ($isOfflineMode && !$isOfflineSync) {
        // ... (offline mode code remains the same) ...
    } else {
        // Check database connection
        if ($dbError || !$conn || !($conn instanceof mysqli)) {
            $response['title'] = "Database Error";
            $response['message'] = "Cannot connect to database. Please try again or switch to offline mode.";
            $response['debug']['db_error'] = $dbErrorMessage;
            echo json_encode($response);
            exit;
        }

        // Extract and validate form data
        $surname      = trim($_POST["surname"]      ?? '');
        $firstname    = trim($_POST["firstname"]    ?? '');
        $middlename   = trim($_POST["middlename"]   ?? '');
        $date_of_birth = $_POST["dob"]              ?? null;
        $placeofbirth = trim($_POST["pob"]          ?? '');
        $age          = intval($_POST["age"]        ?? 0);
        $months_old   = intval($_POST["agemonth"]   ?? 0);
        $sex          = $_POST["sex"]               ?? '';
        $address      = trim($_POST["address"]      ?? '');
        $occupation   = trim($_POST["occupation"]   ?? '');
        $pregnant     = $_POST["pregnant"]          ?? 'No';
        $guardian     = trim($_POST["guardian"]     ?? '');

        // Store data in response for debugging
        $response['debug']['form_data'] = [
            'surname' => $surname,
            'firstname' => $firstname,
            'sex' => $sex,
            'age' => $age,
            'address' => $address
        ];

        // Validate required fields
        if (empty($sex)) {
            $response['title'] = "Validation Error";
            $response['message'] = "Sex field is required.";
            echo json_encode($response);
            exit;
        }

        /*
        |--------------------------------------------------------------------------
        | START TRANSACTION
        |--------------------------------------------------------------------------
        */
        $conn->begin_transaction();
        $patient_id = 0;
        $log_success = false;

        try {
            // ... (patient insertion code remains the same, successfully inserts) ...

            // After successful patient insertion, get the patient_id
            $patient_id = $stmt->insert_id;
            error_log("Patient inserted successfully with ID: " . $patient_id);

            // ... (other tables insertion code) ...

            // Commit transaction first
            $conn->commit();
            error_log("Transaction committed successfully");

            /*
            |--------------------------------------------------------------------------
            | SYSTEM LOGS - AFTER SUCCESSFUL COMMIT
            |--------------------------------------------------------------------------
            */
            error_log("Attempting to add system log...");

            // Get user info for log
            $logUserId = isset($loggedUser['id']) ? $loggedUser['id'] : 0;
            $logUserType = isset($loggedUser['type']) ? $loggedUser['type'] : 'Dentist';

            if (is_numeric($logUserId)) {
                $logUserId = intval($logUserId);
            } else {
                $logUserId = 0;
            }

            // Create description
            $fullName = trim($surname . ', ' . $firstname . ($middlename ? ' ' . $middlename : ''));
            $description = "Created new patient: $fullName (ID: $patient_id)";

            // Add system log
            $logResult = addSystemLog(
                $conn,
                $logUserId,
                $logUserType,
                "Create Patient",
                "INSERT",
                "patients",
                $patient_id,
                $description
            );

            $log_success = $logResult;
            $response['debug']['system_log'] = [
                'attempted' => true,
                'success' => $logResult,
                'user_id' => $logUserId,
                'user_type' => $logUserType,
                'patient_id' => $patient_id
            ];

            if ($logResult) {
                error_log("System log added successfully");
            } else {
                error_log("System log failed, but patient was saved");
            }

            /*
            |--------------------------------------------------------------------------
            | SUCCESS RESPONSE
            |--------------------------------------------------------------------------
            */
            $response['status']  = 'success';
            $response['title']   = "Success!";
            $response['message'] = "Patient registration added successfully!";
            $response['patient_id'] = $patient_id;
            $response['system_log_added'] = $log_success;

            error_log("Sending success response");
        } catch (Exception $e) {
            // Rollback transaction on error
            if ($conn) {
                $conn->rollback();
            }

            error_log("Transaction failed: " . $e->getMessage());

            $response['title'] = "Database Error";
            $response['message'] = $e->getMessage();
            $response['debug']['exception'] = $e->getMessage();

            echo json_encode($response);
            exit;
        }
    }

    error_log("Final response: " . print_r($response, true));
    echo json_encode($response);
    exit;
} else {
    // GET request - show form
    $response['status'] = 'info';
    $response['title'] = 'Form Loaded';
    $response['message'] = 'Add Patient form loaded successfully';
    $response['isOffline'] = $isOfflineMode;
}

// Close connection
if ($conn && $conn instanceof mysqli) {
    $conn->close();
}
