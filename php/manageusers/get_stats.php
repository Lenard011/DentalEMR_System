<?php
session_start();
require_once './db_connection.php';

header('Content-Type: application/json');

error_reporting(E_ALL);
ini_set('display_errors', 0);

if (!isset($_GET['uid']) || !isset($_SESSION['active_sessions'][$_GET['uid']])) {
    echo json_encode(['success' => false, 'message' => 'Invalid session']);
    exit;
}

try {
    if (!$conn || $conn->connect_error) {
        throw new Exception("Database connection failed");
    }

    $today = date('Y-m-d');

    // FIXED QUERY: Count users with successful "Login" (case-sensitive exact match)
    // Based on your database output, the action is "Login" (with capital L)
    $activeUsersQuery = "
        SELECT COUNT(DISTINCT user_id) as active_users_today
        FROM activity_logs 
        WHERE DATE(created_at) = ? 
        AND user_id IS NOT NULL 
        AND user_id != 0
        AND action = 'Login'  -- Exact match for 'Login' with capital L
    ";

    $stmt = $conn->prepare($activeUsersQuery);
    if (!$stmt) {
        throw new Exception("Prepare failed for active users: " . $conn->error);
    }
    $stmt->bind_param('s', $today);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $activeUsers = $row['active_users_today'] ?? 0;
    $stmt->close();

    // If the above returns 0, try case-insensitive
    if ($activeUsers == 0) {
        $caseInsensitiveQuery = "
            SELECT COUNT(DISTINCT user_id) as active_users_today
            FROM activity_logs 
            WHERE DATE(created_at) = ? 
            AND user_id IS NOT NULL 
            AND user_id != 0
            AND (
                action = 'Login' OR  -- Exact match
                LOWER(action) = 'login'  -- Case-insensitive match
            )
        ";

        $stmt = $conn->prepare($caseInsensitiveQuery);
        $stmt->bind_param('s', $today);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $activeUsers = $row['active_users_today'] ?? 0;
        $stmt->close();
    }

    // If still 0, use a more flexible approach
    if ($activeUsers == 0) {
        $flexibleQuery = "
            SELECT COUNT(DISTINCT user_id) as active_users_today
            FROM activity_logs 
            WHERE DATE(created_at) = ? 
            AND user_id IS NOT NULL 
            AND user_id != 0
            AND LOWER(action) LIKE '%login%'
            AND LOWER(action) NOT LIKE '%failed%'
        ";

        $stmt = $conn->prepare($flexibleQuery);
        $stmt->bind_param('s', $today);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $activeUsers = $row['active_users_today'] ?? 0;
        $stmt->close();
    }

    // Verify with raw data
    $verifyQuery = "
        SELECT DISTINCT user_id, action
        FROM activity_logs 
        WHERE DATE(created_at) = ? 
        AND user_id IS NOT NULL 
        AND user_id != 0
        AND LOWER(action) LIKE '%login%'
        ORDER BY user_id
    ";

    $stmt = $conn->prepare($verifyQuery);
    $stmt->bind_param('s', $today);
    $stmt->execute();
    $verifyResult = $stmt->get_result();

    $userActions = [];
    while ($verifyRow = $verifyResult->fetch_assoc()) {
        $userId = $verifyRow['user_id'];
        $action = $verifyRow['action'];
        $userActions[$userId][] = $action;
    }
    $stmt->close();

    // Manual count based on verification
    $manualCount = 0;
    foreach ($userActions as $userId => $actions) {
        $hasSuccessfulLogin = false;
        foreach ($actions as $action) {
            $lowerAction = strtolower($action);
            if ($lowerAction === 'login') {
                $hasSuccessfulLogin = true;
                break;
            }
        }
        if ($hasSuccessfulLogin) {
            $manualCount++;
        }
    }

    // Use the manual count if it's different from query result
    if ($manualCount > $activeUsers) {
        $activeUsers = $manualCount;
    }

    // 1. Get total logs count
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

    // 2. Today's logs count
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

    // 3. Database size
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
        'db_size' => (float)$dbSize,
        '_verification' => [ // Debug info
            'query_result' => $activeUsers,
            'manual_count' => $manualCount,
            'user_actions' => $userActions,
            'today' => $today
        ]
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
