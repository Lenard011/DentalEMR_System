<?php
// delete_logs.php
session_start();
header('Content-Type: application/json');

// Get input data
$input = json_decode(file_get_contents('php://input'), true);
$logIds = $input['log_ids'] ?? [];
$userId = $input['user_id'] ?? null;

if (empty($logIds)) {
    echo json_encode([
        'success' => false,
        'error' => 'No logs selected'
    ]);
    exit;
}

// Database configuration
$host = 'localhost';
$dbname = 'dentalemr_system';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Database connection failed'
    ]);
    exit;
}

try {
    // Separate activity and history logs
    $activityIds = [];
    $historyIds = [];
    
    foreach ($logIds as $logId) {
        if (is_numeric($logId)) {
            // Simple check: IDs under 10000 are activity logs, over are history logs
            // You might need to adjust this based on your actual ID ranges
            if ($logId < 10000) {
                $activityIds[] = $logId;
            } else {
                $historyIds[] = $logId;
            }
        }
    }
    
    $deletedCount = 0;
    
    // Delete activity logs
    if (!empty($activityIds)) {
        $placeholders = str_repeat('?,', count($activityIds) - 1) . '?';
        $stmt = $pdo->prepare("DELETE FROM activity_logs WHERE id IN ($placeholders)");
        $stmt->execute($activityIds);
        $deletedCount += $stmt->rowCount();
    }
    
    // Delete history logs
    if (!empty($historyIds)) {
        $placeholders = str_repeat('?,', count($historyIds) - 1) . '?';
        $stmt = $pdo->prepare("DELETE FROM history_logs WHERE history_id IN ($placeholders)");
        $stmt->execute($historyIds);
        $deletedCount += $stmt->rowCount();
    }
    
    // Log the deletion activity
    if ($userId) {
        // Get user info
        $stmt = $pdo->prepare("SELECT name FROM dentist WHERE id = ? UNION SELECT name FROM staff WHERE id = ? LIMIT 1");
        $stmt->execute([$userId, $userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        $userName = $user['name'] ?? 'Unknown User';
        
        // Log the deletion
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        
        $stmt = $pdo->prepare("
            INSERT INTO activity_logs 
            (user_id, user_name, action, target, details, ip_address, user_agent, created_at) 
            VALUES (?, ?, 'DELETE', 'System Logs', ?, ?, ?, NOW())
        ");
        
        $details = "Deleted $deletedCount system log(s)";
        $stmt->execute([$userId, $userName, $details, $ipAddress, $userAgent]);
    }
    
    echo json_encode([
        'success' => true,
        'deleted_count' => $deletedCount,
        'message' => "Successfully deleted $deletedCount log(s)"
    ]);
    
} catch (PDOException $e) {
    error_log("Delete Logs Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Failed to delete logs: ' . $e->getMessage()
    ]);
}
?>