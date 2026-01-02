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

    // Top 5 most active users (from both activity_logs and history_logs)
    $topUsersQuery = "
        SELECT 
            COALESCE(
                CASE 
                    WHEN user_id IN (SELECT id FROM dentist) THEN (SELECT name FROM dentist WHERE id = user_id LIMIT 1)
                    WHEN user_id IN (SELECT id FROM staff) THEN (SELECT username FROM staff WHERE id = user_id LIMIT 1)
                    ELSE user_name
                END,
                'Anonymous'
            ) as name, 
            COUNT(*) as count
        FROM (
            SELECT user_id, user_name FROM activity_logs 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) 
            AND (user_id IS NOT NULL OR user_name IS NOT NULL)
            
            UNION ALL
            
            SELECT changed_by_id as user_id, NULL as user_name 
            FROM history_logs 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) 
            AND changed_by_id IS NOT NULL
        ) as all_logs
        GROUP BY user_id, user_name
        ORDER BY count DESC
        LIMIT 5
    ";

    $result = $conn->query($topUsersQuery);
    $topUsers = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $topUsers[] = [
                'name' => $row['name'] ?? 'Unknown',
                'count' => (int)($row['count'] ?? 0)
            ];
        }
        $result->free();
    }

    // Activity types distribution from both tables
    $activityTypesQuery = "
        SELECT 
            CASE 
                WHEN LOWER(action) LIKE '%login%' THEN 'Login'
                WHEN LOWER(action) LIKE '%logout%' THEN 'Logout'
                WHEN LOWER(action) LIKE '%create%' OR LOWER(action) LIKE '%add%' OR LOWER(action) LIKE '%insert%' THEN 'Create'
                WHEN LOWER(action) LIKE '%update%' OR LOWER(action) LIKE '%edit%' OR LOWER(action) LIKE '%modify%' THEN 'Update'
                WHEN LOWER(action) LIKE '%delete%' OR LOWER(action) LIKE '%remove%' THEN 'Delete'
                WHEN LOWER(action) LIKE '%view%' OR LOWER(action) LIKE '%read%' OR LOWER(action) LIKE '%access%' THEN 'View'
                WHEN LOWER(action) LIKE '%export%' THEN 'Export'
                WHEN LOWER(action) LIKE '%import%' THEN 'Import'
                ELSE 'Other'
            END as type,
            COUNT(*) as count
        FROM (
            SELECT action FROM activity_logs 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            
            UNION ALL
            
            SELECT action FROM history_logs 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ) as all_actions
        GROUP BY 
            CASE 
                WHEN LOWER(action) LIKE '%login%' THEN 'Login'
                WHEN LOWER(action) LIKE '%logout%' THEN 'Logout'
                WHEN LOWER(action) LIKE '%create%' OR LOWER(action) LIKE '%add%' OR LOWER(action) LIKE '%insert%' THEN 'Create'
                WHEN LOWER(action) LIKE '%update%' OR LOWER(action) LIKE '%edit%' OR LOWER(action) LIKE '%modify%' THEN 'Update'
                WHEN LOWER(action) LIKE '%delete%' OR LOWER(action) LIKE '%remove%' THEN 'Delete'
                WHEN LOWER(action) LIKE '%view%' OR LOWER(action) LIKE '%read%' OR LOWER(action) LIKE '%access%' THEN 'View'
                WHEN LOWER(action) LIKE '%export%' THEN 'Export'
                WHEN LOWER(action) LIKE '%import%' THEN 'Import'
                ELSE 'Other'
            END
        ORDER BY count DESC
    ";

    $result = $conn->query($activityTypesQuery);
    $activityTypes = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $activityTypes[] = [
                'type' => $row['type'] ?? 'Other',
                'count' => (int)($row['count'] ?? 0)
            ];
        }
        $result->free();
    }

    echo json_encode([
        'success' => true,
        'top_users' => $topUsers,
        'activity_types' => $activityTypes
    ]);
} catch (Exception $e) {
    error_log("Error in get_quick_stats.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'top_users' => [],
        'activity_types' => []
    ]);
}

if (isset($conn)) {
    $conn->close();
}