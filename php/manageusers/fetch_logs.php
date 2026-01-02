<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_GET['uid']) || !isset($_SESSION['active_sessions'][$_GET['uid']])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$userId = intval($_GET['uid']);
$user = $_SESSION['active_sessions'][$userId];

// Check permission
if (!in_array($user['type'], ['Admin', 'Dentist'])) {
    echo json_encode(['success' => false, 'message' => 'Insufficient permissions']);
    exit;
}

// Database connection
$host = "localhost";
$dbUser = "root";
$dbPass = "";
$dbName = "dentalemr_system";

$conn = new mysqli($host, $dbUser, $dbPass, $dbName);
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// Get parameters
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'created_at';
$order = isset($_GET['order']) ? $_GET['order'] : 'desc';

// Validate and sanitize sort field
$allowedSortFields = ['id', 'type', 'user_name', 'action', 'created_at'];
if (!in_array($sort, $allowedSortFields)) {
    $sort = 'created_at';
}

// Build WHERE clause
$whereClauses = [];
if ($search) {
    $whereClauses[] = "(user_name LIKE '%$search%' OR action LIKE '%$search%' OR details LIKE '%$search%' OR description LIKE '%$search%')";
}

if ($filter !== 'all') {
    if ($filter === 'activity') {
        $whereClauses[] = "type = 'activity'";
    } elseif ($filter === 'history') {
        $whereClauses[] = "type = 'history'";
    }
}

$whereSQL = $whereClauses ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

// Count total records
$countSql = "SELECT COUNT(*) as total FROM system_logs $whereSQL";
$countResult = $conn->query($countSql);
$total = $countResult->fetch_assoc()['total'];

// Calculate offset
$offset = ($page - 1) * $limit;

// Fetch logs
$sql = "SELECT * FROM system_logs $whereSQL ORDER BY $sort $order LIMIT $limit OFFSET $offset";
$result = $conn->query($sql);

$logs = [];
while ($row = $result->fetch_assoc()) {
    $logs[] = [
        'id' => $row['id'],
        'type' => $row['type'],
        'user_name' => $row['user_name'],
        'user_type' => $row['user_type'],
        'action' => $row['action'],
        'details' => htmlspecialchars($row['details']),
        'description' => htmlspecialchars($row['description']),
        'ip_address' => $row['ip_address'],
        'user_agent' => $row['user_agent'],
        'created_at' => $row['created_at']
    ];
}

// Get updated stats
$stats = [];
$today = date('Y-m-d');
$activeTime = date('Y-m-d H:i:s', strtotime('-30 minutes'));

$result = $conn->query("SELECT COUNT(*) as total FROM system_logs");
$stats['total_logs'] = $result->fetch_assoc()['total'] ?? 0;

$result = $conn->query("SELECT COUNT(*) as today FROM system_logs WHERE DATE(created_at) = '$today'");
$stats['today_logs'] = $result->fetch_assoc()['today'] ?? 0;

$result = $conn->query("SELECT COUNT(DISTINCT user_id) as active FROM system_logs WHERE created_at >= '$activeTime'");
$stats['active_users'] = $result->fetch_assoc()['active'] ?? 0;

$conn->close();

echo json_encode([
    'success' => true,
    'logs' => $logs,
    'total' => $total,
    'stats' => $stats
]);
?>