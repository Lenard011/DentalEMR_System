<?php
session_start();
header('Content-Type: application/json');

// Database connection
$host = "localhost";
$dbUser = "u401132124_dentalclinic";
$dbPass = "Mho_DentalClinic1st";
$dbName = "u401132124_mho_dentalemr";

$conn = new mysqli($host, $dbUser, $dbPass, $dbName);

if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);

// Validate input
if (!isset($input['uid']) || !isset($input['filters'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request parameters']);
    exit;
}

$userId = intval($input['uid']);
$filters = $input['filters'];

// Validate session (simplified for now)
if (!isset($_SESSION['active_sessions'][$userId])) {
    echo json_encode(['success' => false, 'message' => 'Session expired']);
    exit;
}

// Determine which table to query
$table = ($filters['logType'] === 'activity') ? 'activity_logs' : 'history_logs';

// Build the base query
if ($table === 'activity_logs') {
    $baseQuery = "SELECT * FROM activity_logs WHERE 1=1";
    $countQuery = "SELECT COUNT(*) as total FROM activity_logs WHERE 1=1";
} else {
    $baseQuery = "SELECT * FROM history_logs WHERE 1=1";
    $countQuery = "SELECT COUNT(*) as total FROM history_logs WHERE 1=1";
}

$params = [];
$types = "";

// Apply date filters
if (!empty($filters['dateFrom'])) {
    $baseQuery .= " AND DATE(created_at) >= ?";
    $countQuery .= " AND DATE(created_at) >= ?";
    $params[] = $filters['dateFrom'];
    $types .= "s";
}

if (!empty($filters['dateTo'])) {
    $baseQuery .= " AND DATE(created_at) <= ?";
    $countQuery .= " AND DATE(created_at) <= ?";
    $params[] = $filters['dateTo'];
    $types .= "s";
}

// Apply user filter (for activity_logs)
if (!empty($filters['userId']) && $table === 'activity_logs') {
    // Now userId will be either 'dentist' or 'staff'
    if ($filters['userId'] === 'dentist') {
        // Get all dentist IDs
        $dentistQuery = "SELECT id FROM dentist";
        $dentistResult = $conn->query($dentistQuery);
        $dentistIds = [];
        while ($row = $dentistResult->fetch_assoc()) {
            $dentistIds[] = $row['id'];
        }

        if (!empty($dentistIds)) {
            $placeholders = str_repeat('?,', count($dentistIds) - 1) . '?';
            $baseQuery .= " AND user_id IN ($placeholders)";
            $countQuery .= " AND user_id IN ($placeholders)";
            $params = array_merge($params, $dentistIds);
            $types .= str_repeat('i', count($dentistIds));
        }
    } elseif ($filters['userId'] === 'staff') {
        // Get all staff IDs
        $staffQuery = "SELECT id FROM staff";
        $staffResult = $conn->query($staffQuery);
        $staffIds = [];
        while ($row = $staffResult->fetch_assoc()) {
            $staffIds[] = $row['id'];
        }

        if (!empty($staffIds)) {
            $placeholders = str_repeat('?,', count($staffIds) - 1) . '?';
            $baseQuery .= " AND user_id IN ($placeholders)";
            $countQuery .= " AND user_id IN ($placeholders)";
            $params = array_merge($params, $staffIds);
            $types .= str_repeat('i', count($staffIds));
        }
    }
}
// Apply user filter (for history_logs)
if (!empty($filters['userId']) && $table === 'history_logs') {
    if ($filters['userId'] === 'dentist') {
        $baseQuery .= " AND changed_by_type = 'dentist'";
        $countQuery .= " AND changed_by_type = 'dentist'";
    } elseif ($filters['userId'] === 'staff') {
        $baseQuery .= " AND changed_by_type = 'staff'";
        $countQuery .= " AND changed_by_type = 'staff'";
    }
}
// Apply action filter
if (!empty($filters['action'])) {
    $searchAction = $filters['action'];

    if ($table === 'activity_logs') {
        // Filter for activity_logs table
        $actionMapping = [
            // Session Actions
            'Login' => 'Login',
            'Logout' => 'Logout',
            'Failed Login' => 'Failed Login',

            // CRUD Actions (might appear in activity_logs as system actions)
            'INSERT' => 'INSERT',
            'UPDATE' => 'UPDATE',
            'DELETE' => 'DELETE',

            // System Actions
            'Deleted' => 'Deleted',
            'Email Failed' => 'Email Failed',

            // File Operations
            'Export' => 'Export',
            'Import' => 'Import'
        ];

        if (isset($actionMapping[$searchAction])) {
            $baseQuery .= " AND action = ?";
            $countQuery .= " AND action = ?";
            $params[] = $actionMapping[$searchAction];
            $types .= "s";
        } else {
            // Use LIKE for partial matches
            $baseQuery .= " AND action LIKE ?";
            $countQuery .= " AND action LIKE ?";
            $params[] = '%' . $searchAction . '%';
            $types .= "s";
        }
    } elseif ($table === 'history_logs') {
        // Filter for history_logs table - primarily CRUD operations
        $historyActionMap = [
            // CRUD Actions in history_logs
            'INSERT' => 'INSERT',
            'UPDATE' => 'UPDATE',
            'DELETE' => 'DELETE',

            // Map generic terms to history_logs actions
            'Create' => 'INSERT',
            'Edit' => 'UPDATE',
            'Remove' => 'DELETE',

            // Session actions might not appear in history_logs, but handle if they do
            'Login' => 'Login',
            'Logout' => 'Logout'
        ];

        if (isset($historyActionMap[$searchAction])) {
            if (in_array($searchAction, ['INSERT', 'UPDATE', 'DELETE'])) {
                // Exact match for CRUD operations in history_logs
                $baseQuery .= " AND action = ?";
                $countQuery .= " AND action = ?";
                $params[] = $historyActionMap[$searchAction];
                $types .= "s";
            } else {
                // Use LIKE for other actions
                $baseQuery .= " AND action LIKE ?";
                $countQuery .= " AND action LIKE ?";
                $params[] = '%' . $historyActionMap[$searchAction] . '%';
                $types .= "s";
            }
        } else {
            // Fallback to LIKE search
            $baseQuery .= " AND action LIKE ?";
            $countQuery .= " AND action LIKE ?";
            $params[] = '%' . $searchAction . '%';
            $types .= "s";
        }
    }
}
// Get total count
$stmt = $conn->prepare($countQuery);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$countResult = $stmt->get_result();
$total = $countResult->fetch_assoc()['total'];
$stmt->close();

// Apply pagination
$page = intval($filters['page']);
$limit = intval($filters['limit']);
$offset = ($page - 1) * $limit;

$baseQuery .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
$types .= "ii";
$params[] = $limit;
$params[] = $offset;

// Execute main query
$stmt = $conn->prepare($baseQuery);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$logs = [];
while ($row = $result->fetch_assoc()) {
    // Format the data for frontend
    if ($table === 'activity_logs') {
        $logs[] = [
            'id' => $row['id'],
            'user_id' => $row['user_id'],
            'user_name' => $row['user_name'],
            'action' => $row['action'],
            'target' => $row['target'],
            'details' => $row['details'],
            'ip_address' => $row['ip_address'],
            'user_agent' => $row['user_agent'],
            'timestamp' => $row['created_at']
        ];
    } else {
        $logs[] = [
            'id' => $row['history_id'],
            'table_name' => $row['table_name'],
            'record_id' => $row['record_id'],
            'action' => $row['action'],
            'changed_by' => $row['changed_by_type'] . ' ID: ' . $row['changed_by_id'],
            'old_values' => $row['old_values'],
            'new_values' => $row['new_values'],
            'description' => $row['description'],
            'ip_address' => $row['ip_address'],
            'timestamp' => $row['created_at']
        ];
    }
}

$stmt->close();
$conn->close();

echo json_encode([
    'success' => true,
    'logs' => $logs,
    'total' => $total,
    'page' => $page,
    'totalPages' => ceil($total / $limit)
]);
