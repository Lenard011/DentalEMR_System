    <?php
// delete_logs.php
session_start();
require_once './db_connection.php';

header('Content-Type: application/json');

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);
$logIds = $data['log_ids'] ?? [];
$userId = $data['user_id'] ?? 0;

// Validate session
if (!isset($_SESSION['active_sessions'][$userId])) {
    echo json_encode(['success' => false, 'message' => 'Invalid session']);
    exit;
}

if (empty($logIds) || !is_array($logIds)) {
    echo json_encode(['success' => false, 'message' => 'No logs selected']);
    exit;
}

try {
    if (!$conn || $conn->connect_error) {
        throw new Exception("Database connection failed");
    }

    // Filter to numeric IDs only
    $logIds = array_filter($logIds, 'is_numeric');

    if (empty($logIds)) {
        echo json_encode(['success' => false, 'message' => 'Invalid log IDs']);
        exit;
    }

    // Create placeholders for prepared statement
    $placeholders = implode(',', array_fill(0, count($logIds), '?'));

    // Delete from activity_logs
    $stmt1 = $conn->prepare("DELETE FROM activity_logs WHERE id IN ($placeholders)");
    if ($stmt1) {
        $types = str_repeat('i', count($logIds));
        $stmt1->bind_param($types, ...$logIds);
        $stmt1->execute();
        $stmt1->close();
    }

    // Delete from history_logs
    $stmt2 = $conn->prepare("DELETE FROM history_logs WHERE history_id IN ($placeholders)");
    if ($stmt2) {
        $types = str_repeat('i', count($logIds));
        $stmt2->bind_param($types, ...$logIds);
        $stmt2->execute();
        $stmt2->close();
    }

    // Log this deletion activity
    $userName = $_SESSION['active_sessions'][$userId]['name'] ?? 'Unknown';
    $action = "Deleted " . count($logIds) . " system logs";
    $details = "User deleted logs with IDs: " . implode(', ', $logIds);

    $logStmt = $conn->prepare("INSERT INTO activity_logs (user_id, user_name, action, details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?)");
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

    if ($logStmt) {
        $logStmt->bind_param('isssss', $userId, $userName, $action, $details, $ip, $agent);
        $logStmt->execute();
        $logStmt->close();
    }

    echo json_encode([
        'success' => true,
        'message' => 'Successfully deleted ' . count($logIds) . ' log(s)'
    ]);
} catch (Exception $e) {
    error_log("Delete logs error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to delete logs: ' . $e->getMessage()
    ]);
}

if (isset($conn)) {
    $conn->close();
}
