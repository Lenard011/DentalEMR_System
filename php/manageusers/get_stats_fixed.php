<?php
// get_stats_fixed.php - SIMPLE ACTIVE USERS COUNT
session_start();
require_once './db_connection.php';

header('Content-Type: application/json');

// NO ERROR SUPPRESSION - we want to see everything
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_GET['uid']) || !isset($_SESSION['active_sessions'][$_GET['uid']])) {
    echo json_encode(['success' => false, 'message' => 'Invalid session']);
    exit;
}

try {
    if (!$conn || $conn->connect_error) {
        throw new Exception("Database connection failed: " . ($conn->connect_error ?? 'Unknown'));
    }

    $today = date('Y-m-d');

    // SIMPLE: Count users with ANY activity in the last 15 minutes
    $fifteenMinutesAgo = date('Y-m-d H:i:s', strtotime('-15 minutes'));

    $activeQuery = "
        SELECT COUNT(DISTINCT user_id) as active_count
        FROM activity_logs 
        WHERE created_at >= ?
        AND user_id IS NOT NULL
        AND user_id > 0
    ";

    $stmt = $conn->prepare($activeQuery);
    $stmt->bind_param('s', $fifteenMinutesAgo);

    if (!$stmt->execute()) {
        throw new Exception("Query failed: " . $stmt->error);
    }

    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $activeUsers = $row['active_count'] ?? 0;

    $stmt->close();

    // Count users who logged in today (for comparison)
    $todayLoginsQuery = "
        SELECT COUNT(DISTINCT user_id) as today_logins
        FROM activity_logs 
        WHERE DATE(created_at) = ?
        AND action = 'Login'
        AND user_id IS NOT NULL
        AND user_id > 0
    ";

    $stmt = $conn->prepare($todayLoginsQuery);
    $stmt->bind_param('s', $today);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $todayLogins = $row['today_logins'] ?? 0;
    $stmt->close();

    // Other stats
    $totalLogs = 0;
    $totalQuery = "SELECT COUNT(*) as total FROM activity_logs WHERE user_id IS NOT NULL AND user_id > 0";
    $result = $conn->query($totalQuery);
    if ($result) {
        $row = $result->fetch_assoc();
        $totalLogs = $row['total'] ?? 0;
    }

    $todayLogs = 0;
    $todayQuery = "SELECT COUNT(*) as total FROM activity_logs WHERE DATE(created_at) = ? AND user_id IS NOT NULL AND user_id > 0";
    $stmt = $conn->prepare($todayQuery);
    $stmt->bind_param('s', $today);
    $stmt->execute();
    $todayResult = $stmt->get_result();
    if ($todayRow = $todayResult->fetch_assoc()) {
        $todayLogs = $todayRow['total'] ?? 0;
    }
    $stmt->close();

    $dbSize = 0;
    $sizeQuery = "SELECT SUM(data_length + index_length) / 1024 / 1024 as size_mb FROM information_schema.tables WHERE table_schema = DATABASE()";
    $result = $conn->query($sizeQuery);
    if ($result && $row = $result->fetch_assoc()) {
        $dbSize = round($row['size_mb'] ?? 0, 2);
    }

    $response = [
        'success' => true,
        'total_logs' => (int)$totalLogs,
        'today_logs' => (int)$todayLogs,
        'active_users' => (int)$activeUsers,
        'today_logins' => (int)$todayLogins, // Added for reference
        'db_size' => (float)$dbSize,
        '_meta' => [
            'query_date' => $today,
            'time_window' => '15 minutes',
            'calculated_at' => date('Y-m-d H:i:s'),
            'note' => 'Users active in last 15 minutes'
        ]
    ];

    echo json_encode($response, JSON_PRETTY_PRINT);
} catch (Exception $e) {
    $response = [
        'success' => false,
        'message' => $e->getMessage(),
        'total_logs' => 0,
        'today_logs' => 0,
        'active_users' => 0,
        'today_logins' => 0,
        'db_size' => 0,
        '_error' => [
            'file' => __FILE__,
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ]
    ];

    echo json_encode($response, JSON_PRETTY_PRINT);
}

if (isset($conn)) {
    $conn->close();
}
