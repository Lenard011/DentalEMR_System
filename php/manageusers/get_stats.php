<?php
session_start();
require_once './db_connection.php';

header('Content-Type: application/json');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);

if (!isset($_GET['uid']) || !isset($_SESSION['active_sessions'][$_GET['uid']])) {
    echo json_encode(['success' => false, 'message' => 'Invalid session']);
    exit;
}

try {
    // Check database connection
    if (!$conn || $conn->connect_error) {
        throw new Exception("Database connection failed");
    }

    // Total logs from both tables
    $totalQuery = "
        SELECT (
            (SELECT COUNT(*) FROM activity_logs WHERE user_id IS NOT NULL) + 
            (SELECT COUNT(*) FROM history_logs WHERE changed_by_id IS NOT NULL)
        ) as total
    ";

    $result = $conn->query($totalQuery);
    if (!$result) {
        throw new Exception("Total logs query failed: " . $conn->error);
    }
    $totalRow = $result->fetch_assoc();
    $totalLogs = $totalRow['total'] ?? 0;

    // Today's logs
    $today = date('Y-m-d');
    $todayQuery = "
        SELECT (
            (SELECT COUNT(*) FROM activity_logs WHERE DATE(created_at) = ? AND user_id IS NOT NULL) + 
            (SELECT COUNT(*) FROM history_logs WHERE DATE(created_at) = ? AND changed_by_id IS NOT NULL)
        ) as total
    ";

    $stmt = $conn->prepare($todayQuery);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    $stmt->bind_param('ss', $today, $today);
    $stmt->execute();
    $todayResult = $stmt->get_result();
    $todayRow = $todayResult->fetch_assoc();
    $todayLogs = $todayRow['total'] ?? 0;
    $stmt->close();

    // Active users (unique users who performed activities today)
    $activeUsers = 0;

    // From activity_logs
    $activityUsersQuery = "SELECT COUNT(DISTINCT user_id) as total FROM activity_logs WHERE DATE(created_at) = ? AND user_id IS NOT NULL";
    $stmt = $conn->prepare($activityUsersQuery);
    $stmt->bind_param('s', $today);
    $stmt->execute();
    $activityResult = $stmt->get_result();
    if ($activityRow = $activityResult->fetch_assoc()) {
        $activeUsers += $activityRow['total'] ?? 0;
    }
    $stmt->close();

    // From history_logs
    $historyUsersQuery = "SELECT COUNT(DISTINCT changed_by_id) as total FROM history_logs WHERE DATE(created_at) = ? AND changed_by_id IS NOT NULL";
    $stmt = $conn->prepare($historyUsersQuery);
    $stmt->bind_param('s', $today);
    $stmt->execute();
    $historyResult = $stmt->get_result();
    if ($historyRow = $historyResult->fetch_assoc()) {
        $activeUsers += $historyRow['total'] ?? 0;
    }
    $stmt->close();

    // Database size - simplified
    $dbSize = 0;
    $sizeQuery = "SELECT SUM(data_length + index_length) / 1024 / 1024 as size_mb FROM information_schema.tables WHERE table_schema = DATABASE()";
    $result = $conn->query($sizeQuery);
    if ($result && $row = $result->fetch_assoc()) {
        $dbSize = round($row['size_mb'] ?? 0, 2);
    }

    echo json_encode([
        'success' => true,
        'total_logs' => (int)$totalLogs,
        'today_logs' => (int)$todayLogs,
        'active_users' => (int)$activeUsers,
        'db_size' => (float)$dbSize
    ]);
} catch (Exception $e) {
    error_log("Error in get_stats.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'total_logs' => 0,
        'today_logs' => 0,
        'active_users' => 0,
        'db_size' => 0
    ]);
}

if (isset($conn)) {
    $conn->close();
}
