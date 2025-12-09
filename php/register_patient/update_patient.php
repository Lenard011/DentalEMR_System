<?php
session_start();
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Helper function to check if request is AJAX
function thisIsAjaxRequest()
{
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
}

// Set error handling
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors to users
ini_set('log_errors', 1);

// Log for debugging
error_log("Update patient script accessed. POST data: " . print_r($_POST, true));

// Check for AJAX request first to set appropriate headers
if (thisIsAjaxRequest()) {
    header("Content-Type: application/json; charset=UTF-8");
}

// REQUIRE userId parameter for each page
if (!isset($_GET['uid'])) {
    $response = ["success" => false, "error" => "Invalid session. Please log in again."];
    if (thisIsAjaxRequest()) {
        echo json_encode($response);
    } else {
        echo "<script>alert('Invalid session. Please log in again.'); window.location.href = '/dentalemr_system/html/login/login.html';</script>";
    }
    exit;
}

$userId = intval($_GET['uid']);

// CHECK IF THIS USER IS REALLY LOGGED IN
if (!isset($_SESSION['active_sessions']) || !isset($_SESSION['active_sessions'][$userId])) {
    $response = ["success" => false, "error" => "Please log in first."];
    if (thisIsAjaxRequest()) {
        echo json_encode($response);
    } else {
        echo "<script>alert('Please log in first.'); window.location.href = '/dentalemr_system/html/login/login.html';</script>";
    }
    exit;
}

// PER-USER INACTIVITY TIMEOUT
$inactiveLimit = 600; // 10 minutes

if (isset($_SESSION['active_sessions'][$userId]['last_activity'])) {
    $lastActivity = $_SESSION['active_sessions'][$userId]['last_activity'];

    if ((time() - $lastActivity) > $inactiveLimit) {
        unset($_SESSION['active_sessions'][$userId]);
        if (empty($_SESSION['active_sessions'])) {
            session_unset();
            session_destroy();
        }

        $response = ["success" => false, "error" => "You have been logged out due to inactivity."];
        if (thisIsAjaxRequest()) {
            echo json_encode($response);
        } else {
            echo "<script>alert('You have been logged out due to inactivity.'); window.location.href = '/dentalemr_system/html/login/login.html';</script>";
        }
        exit;
    }
}

// Update last activity timestamp
$_SESSION['active_sessions'][$userId]['last_activity'] = time();

// GET USER DATA FOR PAGE USE
$loggedUser = $_SESSION['active_sessions'][$userId];

// Database connection
$conn = new mysqli("localhost", "root", "", "dentalemr_system");
if ($conn->connect_error) {
    $response = ["success" => false, "error" => "Database connection failed"];
    if (thisIsAjaxRequest()) {
        echo json_encode($response);
    } else {
        die("Database connection failed");
    }
    exit;
}

// Function to add history log
function addHistoryLog($conn, $tableName, $recordId, $action, $changedByType, $changedById, $oldValues = null, $newValues = null, $description = null)
{
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
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;

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

    return $stmt->execute();
}

// Check if this is an update request
$isUpdateRequest = isset($_POST['update_patient']) || (isset($_POST['patient_id']) && isset($_POST['firstname']));

if ($isUpdateRequest) {
    try {
        // Get POST data safely
        $patient_id = $_POST['patient_id'] ?? null;

        if (!$patient_id) {
            $response = ["success" => false, "error" => "Missing patient ID"];
            if (thisIsAjaxRequest()) {
                echo json_encode($response);
            } else {
                header("Location: ../../html/viewrecord.php?uid={$userId}&error=nopid");
            }
            exit();
        }

        error_log("Processing update for patient ID: $patient_id");

        // Fetch old values
        $oldQuery = $conn->prepare("SELECT * FROM patients WHERE patient_id = ?");
        if (!$oldQuery) {
            throw new Exception("Failed to prepare old values query: " . $conn->error);
        }

        $oldQuery->bind_param("i", $patient_id);
        $oldQuery->execute();
        $oldResult = $oldQuery->get_result();
        $oldData = $oldResult->fetch_assoc() ?: [];
        $oldQuery->close();

        // Get form data
        $firstname  = trim($_POST['firstname'] ?? '');
        $surname    = trim($_POST['surname'] ?? '');
        $middlename = trim($_POST['middlename'] ?? '');
        $dob        = !empty($_POST['date_of_birth']) ? $_POST['date_of_birth'] : null;
        $sex        = !empty($_POST['sex']) ? ucfirst(strtolower(trim($_POST['sex']))) : '';
        $age        = isset($_POST['age']) ? (int)$_POST['age'] : null;
        $pob        = trim($_POST['place_of_birth'] ?? '');
        $occupation = trim($_POST['occupation'] ?? '');
        $address    = trim($_POST['address'] ?? '');
        $guardian   = trim($_POST['guardian'] ?? '');
        $pregnant   = !empty($_POST['pregnant']) ? strtolower(trim($_POST['pregnant'])) : 'no';

        // Validate required fields
        if (empty($firstname) || empty($surname) || empty($sex)) {
            throw new Exception("First name, surname, and sex are required fields");
        }

        // Validate sex field
        if (!in_array($sex, ['Male', 'Female'])) {
            $sex = 'Male';
        }

        // Calculate age and months_old from DOB if provided
        $months_old = null;
        if ($dob) {
            try {
                $dobDate = new DateTime($dob);
                $today = new DateTime();
                $interval = $dobDate->diff($today);
                $age = $interval->y;
                $months_old = $interval->y * 12 + $interval->m;
            } catch (Exception $e) {
                error_log("Date calculation error: " . $e->getMessage());
                $age = 0;
                $months_old = 0;
            }
        }

        // Normalize pregnant field
        if ($sex !== 'Female') {
            $pregnant = 'no';
        } else {
            if (!in_array($pregnant, ['yes', 'no'])) {
                $pregnant = 'no';
            }
        }

        // Prepare and execute the update
        $stmt = $conn->prepare("
            UPDATE patients
            SET firstname=?, surname=?, middlename=?, date_of_birth=?, sex=?, age=?, months_old=?,
                place_of_birth=?, occupation=?, address=?, guardian=?, pregnant=?, updated_at=NOW()
            WHERE patient_id=?
        ");

        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }

        $stmt->bind_param(
            "sssssiisssssi",
            $firstname,
            $surname,
            $middlename,
            $dob,
            $sex,
            $age,
            $months_old,
            $pob,
            $occupation,
            $address,
            $guardian,
            $pregnant,
            $patient_id
        );

        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }

        $stmt->close();

        // Prepare new data for logging
        $newData = [
            "firstname"     => $firstname,
            "surname"       => $surname,
            "middlename"    => $middlename,
            "date_of_birth" => $dob,
            "sex"           => $sex,
            "age"           => $age,
            "months_old"    => $months_old,
            "place_of_birth" => $pob,
            "occupation"    => $occupation,
            "address"       => $address,
            "guardian"      => $guardian,
            "pregnant"      => $pregnant
        ];

        // Find changed fields
        $changedOld = [];
        $changedNew = [];
        $justification = [];

        foreach ($newData as $column => $newValue) {
            $oldValue = $oldData[$column] ?? null;
            $oldNorm = ($oldValue === "" || $oldValue === null) ? null : $oldValue;
            $newNorm = ($newValue === "" || $newValue === null) ? null : $newValue;

            if ((string)$oldNorm !== (string)$newNorm) {
                $changedOld[$column] = $oldValue;
                $changedNew[$column] = $newValue;
                $nice = ucfirst(str_replace("_", " ", $column));
                $justification[] = "$nice changed from \"" . ($oldValue ?? 'empty') . "\" to \"" . ($newValue ?? 'empty') . "\"";
            }
        }

        // Log changes if any
        if (!empty($changedOld)) {
            addHistoryLog(
                $conn,
                "patients",
                $patient_id,
                "UPDATE",
                $loggedUser['type'],
                $loggedUser['id'],
                $changedOld,
                $changedNew,
                implode("; ", $justification)
            );
        }

        // Success response
        $response = [
            "success" => true,
            "message" => "Patient updated successfully",
            "patient_id" => $patient_id,
            "redirect" => "/dentalemr_system/html/viewrecord.php?uid={$userId}&id=" . urlencode($patient_id) . "&updated=1"
        ];

        if (thisIsAjaxRequest()) {
            echo json_encode($response);
        } else {
            // Redirect for non-AJAX requests
            header("Location: /dentalemr_system/html/viewrecord.php?uid={$userId}&id=" . urlencode($patient_id) . "&updated=1");
        }

        $conn->close();
        exit();
    } catch (Exception $e) {
        error_log("Update patient error: " . $e->getMessage());

        $response = [
            "success" => false,
            "error" => "Update failed: " . $e->getMessage()
        ];

        if (thisIsAjaxRequest()) {
            echo json_encode($response);
        } else {
            header("Location: /dentalemr_system/html/viewrecord.php?uid={$userId}&id=" . urlencode($patient_id) . "&updated=0&error=" . urlencode($e->getMessage()));
        }

        if (isset($conn) && $conn) {
            $conn->close();
        }
        exit();
    }
} else {
    // No form submitted
    $response = ["success" => false, "error" => "No form data received"];

    if (thisIsAjaxRequest()) {
        echo json_encode($response);
    } else {
        echo "Invalid request";
    }

    if (isset($conn) && $conn) {
        $conn->close();
    }
    exit();
}
