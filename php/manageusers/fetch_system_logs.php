<?php
session_start();
require_once './db_connection.php';

header('Content-Type: application/json');

// Debug logging
error_log("Fetch system logs called with params: " . json_encode($_GET));

// Validate user session
if (!isset($_SESSION['active_sessions'])) {
    error_log("No active sessions found in session");
    echo json_encode(['success' => false, 'message' => 'Session expired - no active sessions']);
    exit;
}

// Check if uid is provided and valid
if (!isset($_GET['uid'])) {
    error_log("UID not provided in request");
    echo json_encode(['success' => false, 'message' => 'User ID required']);
    exit;
}

$userId = intval($_GET['uid']);

// Check if user session exists
if (!isset($_SESSION['active_sessions'][$userId])) {
    error_log("User session not found for ID: " . $userId);
    echo json_encode(['success' => false, 'message' => 'Session expired or invalid']);
    exit;
}

// Get parameters with defaults
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'created_at';
$order = isset($_GET['order']) && strtoupper($_GET['order']) === 'ASC' ? 'ASC' : 'DESC';

if ($page < 1) $page = 1;
if ($limit < 1 || $limit > 100) $limit = 10;

$offset = ($page - 1) * $limit;

try {
    // Test database connection
    if (!$conn || $conn->connect_error) {
        throw new Exception("Database connection failed: " . ($conn->connect_error ?? 'Unknown error'));
    }

    error_log("Processing logs with filter: $filter, search: $search, page: $page");

    // Build base queries with proper staff user handling
    $activityQuery = "SELECT 
        al.id,
        al.user_id,
        COALESCE(
            al.user_name, 
            CASE 
                WHEN al.user_id IN (SELECT id FROM dentist) THEN (SELECT name FROM dentist WHERE id = al.user_id LIMIT 1)
                WHEN al.user_id IN (SELECT id FROM staff) THEN (SELECT username FROM staff WHERE id = al.user_id LIMIT 1)
                ELSE 'System'
            END
        ) as user_name,
        al.action,
        al.details,
        al.ip_address,
        al.user_agent,
        al.created_at,
        'activity' as log_type,
        CASE 
            WHEN al.user_id IN (SELECT id FROM dentist) THEN 'Dentist'
            WHEN al.user_id IN (SELECT id FROM staff) THEN 'Staff'
            WHEN al.user_id = 0 THEN 'System'
            ELSE 'User'
        END as user_type
    FROM activity_logs al 
    WHERE 1=1";

    $historyQuery = "SELECT 
        hl.history_id as id,
        hl.changed_by_id as user_id,
        COALESCE(
            (SELECT name FROM dentist WHERE id = hl.changed_by_id AND hl.changed_by_type = 'Dentist' LIMIT 1),
            (SELECT username FROM staff WHERE id = hl.changed_by_id AND hl.changed_by_type = 'Staff' LIMIT 1),
            CASE 
                WHEN hl.changed_by_type = 'System' THEN 'System'
                ELSE 'User'
            END
        ) as user_name,
        CONCAT(hl.action, ' on ', hl.table_name) as action,
        hl.description as details,
        hl.ip_address,
        NULL as user_agent,
        hl.created_at,
        'history' as log_type,
        hl.changed_by_type as user_type
    FROM history_logs hl 
    WHERE 1=1";

    // Apply filter
    if ($filter === 'activity') {
        $baseQuery = "FROM ($activityQuery) as combined WHERE 1=1";
        $selectFields = "id, user_id, user_name, action, details, ip_address, user_agent, created_at, log_type, user_type";
    } elseif ($filter === 'history') {
        $baseQuery = "FROM ($historyQuery) as combined WHERE 1=1";
        $selectFields = "id, user_id, user_name, action, details, ip_address, user_agent, created_at, log_type, user_type";
    } else {
        // Combine both
        $baseQuery = "FROM (
            $activityQuery 
            UNION ALL 
            $historyQuery
        ) as combined WHERE 1=1";
        $selectFields = "id, user_id, user_name, action, details, ip_address, user_agent, created_at, log_type, user_type";
    }

    // Build WHERE conditions
    $whereConditions = [];
    $params = [];
    $types = '';

    if (!empty($search)) {
        $searchTerm = "%$search%";
        $whereConditions[] = "(user_name LIKE ? OR action LIKE ? OR details LIKE ? OR user_type LIKE ?)";
        $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
        $types .= 'ssss';
    }

    if ($filter !== 'all' && $filter !== '') {
        $whereConditions[] = "log_type = ?";
        $params[] = $filter;
        $types .= 's';
    }

    // Build the WHERE clause
    $whereClause = '';
    if (!empty($whereConditions)) {
        $whereClause = " AND " . implode(" AND ", $whereConditions);
    }

    // Get total count
    $countQuery = "SELECT COUNT(*) as total $baseQuery $whereClause";
    error_log("Count query: $countQuery");

    $countStmt = $conn->prepare($countQuery);
    if (!$countStmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    if (!empty($params)) {
        $countStmt->bind_param($types, ...$params);
    }

    if (!$countStmt->execute()) {
        throw new Exception("Execute failed: " . $countStmt->error);
    }

    $countResult = $countStmt->get_result();
    $totalRow = $countResult->fetch_assoc();
    $totalItems = $totalRow['total'] ?? 0;
    $countStmt->close();

    // Validate sort field
    $validSortFields = ['id', 'user_name', 'action', 'created_at', 'log_type', 'user_type'];
    $sort = in_array($sort, $validSortFields) ? $sort : 'created_at';
    $order = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';

    // Build main query
    $mainQuery = "SELECT $selectFields $baseQuery $whereClause ORDER BY $sort $order LIMIT ? OFFSET ?";
    error_log("Main query: $mainQuery");

    // Add limit and offset parameters
    $params[] = $limit;
    $params[] = $offset;
    $types .= 'ii';

    $stmt = $conn->prepare($mainQuery);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }

    $result = $stmt->get_result();

    $logs = [];
    while ($row = $result->fetch_assoc()) {
        // Ensure all required fields exist
        $logs[] = [
            'id' => $row['id'] ?? 0,
            'type' => $row['log_type'] ?? 'activity',
            'user_name' => $row['user_name'] ?? 'System',
            'user_type' => $row['user_type'] ?? 'User',
            'action' => $row['action'] ?? '',
            'details' => $row['details'] ?? '',
            'description' => $row['details'] ?? '',
            'ip_address' => $row['ip_address'] ?? '',
            'user_agent' => $row['user_agent'] ?? '',
            'created_at' => $row['created_at'] ?? date('Y-m-d H:i:s')
        ];
    }

    $stmt->close();

    error_log("Found " . count($logs) . " logs, total items: $totalItems");

    echo json_encode([
        'success' => true,
        'logs' => $logs,
        'total' => $totalItems,
        'page' => $page,
        'limit' => $limit,
        'total_pages' => ceil($totalItems / $limit) ?: 1
    ]);
} catch (Exception $e) {
    error_log("Error fetching logs: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());

    // Return empty data but with success to prevent frontend errors
    echo json_encode([
        'success' => true,
        'logs' => [],
        'total' => 0,
        'page' => $page,
        'limit' => $limit,
        'total_pages' => 0,
        'message' => 'No logs found or error occurred: ' . $e->getMessage()
    ]);
}

if (isset($conn)) {
    $conn->close();
}