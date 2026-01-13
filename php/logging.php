<?php

/**
 * Logging Functions for Dental EMR System
 */

// Check if running in CLI (command line) to avoid session errors
if (session_status() === PHP_SESSION_NONE && php_sapi_name() !== 'cli') {
    session_start();
}

/**
 * Debug logging info
 */
function debugLogInfo($userId, $userType, $action, $description = null)
{
    error_log("DEBUG LOG: UserID: $userId, UserType: $userType, Action: $action, Desc: " . ($description ?? 'null'));

    // Also debug session
    if (session_status() === PHP_SESSION_ACTIVE) {
        error_log("DEBUG SESSION: " . print_r($_SESSION, true));
    }
}

function normalizeUserTypeForLogs($userType)
{
    if (empty($userType)) {
        return 'unknown';
    }

    $type = trim($userType);
    $lowerType = strtolower($type);

    // Map variations to standard values
    if ($lowerType === 'staff' || $lowerType === 'employee') {
        return 'staff';
    }

    if ($lowerType === 'dentist' || $lowerType === 'doctor' || $lowerType === 'dr.') {
        return 'dentist';
    }

    if ($lowerType === 'admin' || $lowerType === 'administrator') {
        return 'admin';
    }

    if ($lowerType === 'offline') {
        return 'offline';
    }

    // Return lowercase version
    return $lowerType;
}

/**
 * Log user activity
 */
function logActivity($conn, $userId, $userType, $action, $description = null, $tableName = null, $recordId = null)
{
    // Debug first
    debugLogInfo($userId, $userType, $action, $description);

    // Skip if no valid database connection
    if (!$conn || !($conn instanceof mysqli)) {
        error_log("LOG ERROR: Invalid database connection for activity log");
        return false;
    }

    $normalizedUserType = normalizeUserTypeForLogs($userType);

    // Skip for offline users
    if ($userId === 'offline' || $userType === 'Offline' || $userId === 'offline_user' || $userId === 0) {
        return true;
    }

    try {
        // Check if activity_logs table exists
        $checkTable = $conn->query("SHOW TABLES LIKE 'activity_logs'");
        if (!$checkTable || $checkTable->num_rows == 0) {
            error_log("LOG WARNING: activity_logs table doesn't exist");
            return false;
        }

        $sql = "INSERT INTO activity_logs 
                (user_id, user_type, action, description, table_name, record_id, ip_address, user_agent, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            error_log("LOG ERROR: Failed to prepare activity log statement: " . $conn->error);
            return false;
        }

        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';

        // Convert null values for binding
        $desc = $description ?: null;
        $table = $tableName ?: null;
        $record = $recordId ?: null;

        // Ensure userId is integer
        $userId = intval($userId);

        $stmt->bind_param(
            "issssiss",
            $userId,
            $normalizedUserType,
            $action,
            $desc,
            $table,
            $record,
            $ip,
            $userAgent
        );

        $result = $stmt->execute();

        if (!$result) {
            error_log("LOG ERROR: Failed to execute activity log: " . $stmt->error);
            $stmt->close();
            return false;
        }

        $logId = $stmt->insert_id;
        $stmt->close();

        error_log("LOG SUCCESS: Activity logged with ID $logId for user $userId (type: $normalizedUserType)");
        return true;
    } catch (Exception $e) {
        error_log("LOG EXCEPTION in logActivity: " . $e->getMessage());
        return false;
    }
}

/**
 * Log history changes
 */
function logHistory($conn, $tableName, $recordId, $action, $userType, $userId, $oldValues = null, $newValues = null, $description = null)
{
    // Skip if no valid database connection
    if (!$conn || !($conn instanceof mysqli)) {
        error_log("LOG ERROR: Invalid database connection for history log");
        return false;
    }

    $normalizedUserType = normalizeUserTypeForLogs($userType);

    // Skip for offline users
    if ($userId === 'offline' || $userType === 'Offline' || $userId === 'offline_user' || $userId === 0) {
        return true;
    }

    try {
        $sql = "INSERT INTO history_logs 
                (table_name, record_id, action, changed_by_type, changed_by_id, 
                 old_values, new_values, description, ip_address, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            error_log("LOG ERROR: Failed to prepare history log statement: " . $conn->error);
            return false;
        }

        $oldJSON = ($oldValues && !empty($oldValues)) ? json_encode($oldValues, JSON_UNESCAPED_UNICODE) : null;
        $newJSON = ($newValues && !empty($newValues)) ? json_encode($newValues, JSON_UNESCAPED_UNICODE) : null;
        $desc = $description ?: null;
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        // Ensure userId is integer
        $userId = intval($userId);

        $stmt->bind_param(
            "sississss",
            $tableName,
            $recordId,
            $action,
            $normalizedUserType,
            $userId,
            $oldJSON,
            $newJSON,
            $desc,
            $ip
        );

        $result = $stmt->execute();

        if (!$result) {
            error_log("LOG ERROR: Failed to execute history log: " . $stmt->error);
            $stmt->close();
            return false;
        }

        $logId = $stmt->insert_id;
        $stmt->close();

        error_log("LOG SUCCESS: History logged with ID $logId for table $tableName record $recordId by $normalizedUserType (ID: $userId)");
        return true;
    } catch (Exception $e) {
        error_log("LOG EXCEPTION in logHistory: " . $e->getMessage());
        return false;
    }
}

/**
 * Get current user info from session
 */
function getCurrentUserInfo()
{
    // Check for offline user first
    if (isset($_SESSION['offline_user'])) {
        error_log("DEBUG: Found offline user, type: " . ($_SESSION['offline_user']['type'] ?? 'Unknown'));
        return $_SESSION['offline_user'];
    }

    // Check for user ID in GET parameter
    if (isset($_GET['uid']) && isset($_SESSION['active_sessions'][$_GET['uid']])) {
        $user = $_SESSION['active_sessions'][$_GET['uid']];
        error_log("DEBUG: Found user via GET uid, type: " . ($user['type'] ?? 'Unknown'));
        return $user;
    }

    // Try to get from any active session
    if (isset($_SESSION['active_sessions']) && is_array($_SESSION['active_sessions'])) {
        foreach ($_SESSION['active_sessions'] as $sessionId => $user) {
            if (isset($user['id']) && isset($user['type'])) {
                error_log("DEBUG: Found user in active sessions, ID: {$user['id']}, Type: {$user['type']}");
                return $user;
            }
        }
    }

    error_log("DEBUG: No user info found in session");
    return null;
}

/**
 * Log page access
 */
function logPageAccess($conn, $pageName)
{
    $user = getCurrentUserInfo();

    if (!$user || !isset($user['id']) || !isset($user['type'])) {
        return false;
    }

    // Don't log for offline users
    if (isset($user['isOffline']) && $user['isOffline']) {
        return true;
    }

    return logActivity(
        $conn,
        $user['id'],
        $user['type'],
        'VIEW_PAGE',
        "Accessed $pageName page",
        null,
        null
    );
}

/**
 * Debug function to check tables
 */
function debugLoggingTables($conn)
{
    if (!$conn || !($conn instanceof mysqli)) {
        return "No database connection";
    }

    $tables = ['activity_logs', 'history_logs'];
    $results = [];

    foreach ($tables as $table) {
        $check = $conn->query("SHOW TABLES LIKE '$table'");
        if ($check && $check->num_rows > 0) {
            // Get table structure
            $structure = $conn->query("DESCRIBE $table");
            $columns = [];
            while ($row = $structure->fetch_assoc()) {
                $columns[] = $row['Field'] . ' (' . $row['Type'] . ')';
            }
            $results[$table] = $columns;
        } else {
            $results[$table] = "Table doesn't exist";
        }
    }

    return $results;
}
