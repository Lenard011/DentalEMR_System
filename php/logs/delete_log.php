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

if (!$data || !isset($data['uid']) || !isset($data['logId']) || !isset($data['logType'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit;
}

$userId = intval($data['uid']);
$logId = intval($data['logId']);
$logType = $data['logType'];

// Validate session
if (!isset($_SESSION['active_sessions']) || !isset($_SESSION['active_sessions'][$userId])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Session expired']);
    exit;
}

// Check user permissions (optional - add if you want to restrict deletion)
$userType = $_SESSION['active_sessions'][$userId]['type'] ?? '';
if ($userType !== 'Dentist' && $userType !== 'Admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'You do not have permission to delete logs']);
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

// Delete log based on type
if ($logType === 'activity') {
    $query = "DELETE FROM activity_logs WHERE id = ?";
} else {
    $query = "DELETE FROM history_logs WHERE history_id = ?";
}

$stmt = $conn->prepare($query);
if (!$stmt) {
    $conn->close();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database query preparation failed']);
    exit;
}

$stmt->bind_param("i", $logId);
$stmt->execute();

if ($stmt->affected_rows > 0) {
    // Log the deletion activity
    $userName = $_SESSION['active_sessions'][$userId]['name'] ?? 'Unknown';
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    $action = "DELETE";
    $target = $logType === 'activity' ? "activity_logs" : "history_logs";
    $details = "Deleted log entry ID: {$logId}";
    
    $logQuery = "INSERT INTO activity_logs (user_id, user_name, action, target, details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?)";
    $logStmt = $conn->prepare($logQuery);
    if ($logStmt) {
        $logStmt->bind_param("issssss", $userId, $userName, $action, $target, $details, $ipAddress, $userAgent);
        $logStmt->execute();
        $logStmt->close();
    }
    
    $stmt->close();
    $conn->close();
    
    echo json_encode([
        'success' => true,
        'message' => 'Log entry deleted successfully'
    ]);
} else {
    $stmt->close();
    $conn->close();
    echo json_encode([
        'success' => false,
        'message' => 'Log entry not found or already deleted'
    ]);
}
?>