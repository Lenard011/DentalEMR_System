<?php
header('Content-Type: application/json');

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
        'error' => 'Database connection failed: ' . $e->getMessage()
    ]);
    exit;
}

// Get all activity logs
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $search = isset($_GET['search']) ? trim($_GET['search']) : '';
        $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10; // Default to 10
        $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
        $offset = ($page - 1) * $limit;

        // Your SQL query should include:
        // LIMIT $limit OFFSET $offset

        // Validate parameters
        if ($page < 1) $page = 1;
        if ($limit < 1) $limit = 10;

        // Base query using the new activity_logs table
        $sql = "SELECT 
                    id,
                    user_id,
                    user_name,
                    action,
                    target,
                    details,
                    ip_address,
                    user_agent,
                    created_at as date
                FROM activity_logs 
                WHERE 1=1";

        $countSql = "SELECT COUNT(*) as total 
                     FROM activity_logs 
                     WHERE 1=1";

        $params = [];
        $countParams = [];

        if (!empty($search)) {
            $searchTerm = "%$search%";
            $sql .= " AND (user_name LIKE :search 
                          OR action LIKE :search 
                          OR target LIKE :search 
                          OR details LIKE :search 
                          OR ip_address LIKE :search)";
            $countSql .= " AND (user_name LIKE :search 
                              OR action LIKE :search 
                              OR target LIKE :search 
                              OR details LIKE :search 
                              OR ip_address LIKE :search)";
            $params[':search'] = $searchTerm;
            $countParams[':search'] = $searchTerm;
        }

        // Get total count
        $stmtCount = $pdo->prepare($countSql);
        foreach ($countParams as $key => $value) {
            $stmtCount->bindValue($key, $value);
        }
        $stmtCount->execute();
        $totalResult = $stmtCount->fetch(PDO::FETCH_ASSOC);
        $total = $totalResult ? (int)$totalResult['total'] : 0;

        // Get data
        $sql .= " ORDER BY created_at DESC LIMIT :limit OFFSET :offset";

        $stmt = $pdo->prepare($sql);

        // Bind search parameter if it exists
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Ensure all values are properly formatted
        foreach ($logs as &$log) {
            $log['id'] = (int)$log['id'];
            $log['user_id'] = $log['user_id'] ? (int)$log['user_id'] : null;
            $log['user_name'] = $log['user_name'] ?? 'System';
            $log['action'] = $log['action'] ?? 'N/A';
            $log['target'] = $log['target'] ?? 'N/A';
            $log['details'] = $log['details'] ?? 'N/A';
            $log['ip_address'] = $log['ip_address'] ?? 'N/A';
            $log['user_agent'] = $log['user_agent'] ?? 'N/A';
            $log['date'] = $log['date'] ?? date('Y-m-d H:i:s');
        }

        echo json_encode([
            'success' => true,
            'data' => $logs,
            'total' => $total,
            'page' => $page,
            'totalPages' => ceil($total / $limit)
        ]);
    } catch (PDOException $e) {
        error_log("Activity Logs Error: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'error' => 'Failed to fetch activity logs'
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'error' => 'Invalid request method'
    ]);
}
