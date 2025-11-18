<?php
// activity_logger.php
function logActivity($pdo, $userId, $userName, $action, $target, $details, $ipAddress = null, $userAgent = null) {
    try {
        if ($ipAddress === null) {
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        }
        if ($userAgent === null) {
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO activity_logs (user_id, user_name, action, target, details, ip_address, user_agent, created_at) 
            VALUES (:user_id, :user_name, :action, :target, :details, :ip_address, :user_agent, NOW())
        ");
        
        $stmt->execute([
            ':user_id' => $userId,
            ':user_name' => $userName,
            ':action' => $action,
            ':target' => $target,
            ':details' => $details,
            ':ip_address' => $ipAddress,
            ':user_agent' => $userAgent
        ]);
        
        return true;
    } catch (PDOException $e) {
        error_log("Activity Log Error: " . $e->getMessage());
        return false; // Don't break the main functionality if logging fails
    }
}
?>