<?php
session_start();
date_default_timezone_set('Asia/Manila');

// Set content type to JSON
header('Content-Type: application/json');

// Check if request is valid
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Get POST data
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data || !isset($data['uid']) || !isset($data['logId'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit;
}

$userId = intval($data['uid']);
$logId = intval($data['logId']);
$logType = $data['logType'] ?? 'activity';

// Validate session
if (!isset($_SESSION['active_sessions']) || !isset($_SESSION['active_sessions'][$userId])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Session expired']);
    exit;
}

// Update last activity
$_SESSION['active_sessions'][$userId]['last_activity'] = time();

// Database connection
$host = "localhost";
$dbUser = "u401132124_dentalclinic";
$dbPass = "Mho_DentalClinic1st";
$dbName = "u401132124_mho_dentalemr";

$conn = new mysqli($host, $dbUser, $dbPass, $dbName);
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// Fetch log details based on type
if ($logType === 'activity') {
    // For activity logs, user_name is already stored, but we need to handle user_type
    $query = "SELECT 
                id,
                user_id,
                user_name,
                action,
                target,
                details,
                ip_address,
                user_agent,
                created_at as timestamp
              FROM activity_logs 
              WHERE id = ?";

    $stmt = $conn->prepare($query);
    if (!$stmt) {
        $conn->close();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database query preparation failed']);
        exit;
    }

    $stmt->bind_param("i", $logId);
} else {
    // For history logs
    $query = "SELECT 
                history_id as id,
                table_name,
                record_id,
                action,
                changed_by_type,
                changed_by_id,
                old_values,
                new_values,
                description,
                ip_address,
                created_at as timestamp
              FROM history_logs 
              WHERE history_id = ?";

    $stmt = $conn->prepare($query);
    if (!$stmt) {
        $conn->close();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database query preparation failed']);
        exit;
    }

    $stmt->bind_param("i", $logId);
}

$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();
    $conn->close();
    echo json_encode(['success' => false, 'message' => 'Log not found']);
    exit;
}

$log = $result->fetch_assoc();

// For history logs, we need to get the changed_by name from dentist or staff table
if ($logType === 'history') {
    $changedByName = 'System';

    if (!empty($log['changed_by_id'])) {
        if ($log['changed_by_type'] === 'dentist') {
            // Get name from dentist table
            $nameQuery = "SELECT name FROM dentist WHERE id = ?";
            $nameStmt = $conn->prepare($nameQuery);
            if ($nameStmt) {
                $nameStmt->bind_param("i", $log['changed_by_id']);
                $nameStmt->execute();
                $nameResult = $nameStmt->get_result();
                if ($nameResult->num_rows > 0) {
                    $nameRow = $nameResult->fetch_assoc();
                    $changedByName = $nameRow['name'];
                }
                $nameStmt->close();
            }
        } elseif ($log['changed_by_type'] === 'staff') {
            // Get name from staff table
            $nameQuery = "SELECT name FROM staff WHERE id = ?";
            $nameStmt = $conn->prepare($nameQuery);
            if ($nameStmt) {
                $nameStmt->bind_param("i", $log['changed_by_id']);
                $nameStmt->execute();
                $nameResult = $nameStmt->get_result();
                if ($nameResult->num_rows > 0) {
                    $nameRow = $nameResult->fetch_assoc();
                    $changedByName = $nameRow['name'];
                }
                $nameStmt->close();
            }
        }
    }

    $log['changed_by'] = $changedByName;
}

$stmt->close();
$conn->close();

// Ensure all fields have values
$log = array_map(function ($value) {
    return $value === null ? '' : $value;
}, $log);

echo json_encode([
    'success' => true,
    'log' => $log
]);
