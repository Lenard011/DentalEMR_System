<?php
// export_logs.php
session_start();
require_once './db_connection.php';

header('Content-Type: application/json');

error_reporting(E_ALL);
ini_set('display_errors', 0);

// Check if user is authenticated
if (!isset($_GET['user_id']) || !isset($_SESSION['active_sessions'][$_GET['user_id']])) {
    echo json_encode(['success' => false, 'message' => 'Invalid session']);
    exit;
}

$userId = intval($_GET['user_id']);
$format = isset($_GET['format']) ? $_GET['format'] : 'csv';
$logIds = isset($_GET['log_ids']) ? explode(',', $_GET['log_ids']) : [];

try {
    if (!$conn || $conn->connect_error) {
        throw new Exception("Database connection failed");
    }

    // Build base query for logs (similar to fetch_system_logs.php)
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

    // Combine both tables
    $baseQuery = "FROM (
        $activityQuery 
        UNION ALL 
        $historyQuery
    ) as combined WHERE 1=1";
    
    $selectFields = "id, user_id, user_name, user_type, action, details, ip_address, user_agent, created_at, log_type";

    // Build WHERE conditions
    $whereConditions = [];
    $params = [];
    $types = '';

    // Filter by specific log IDs if provided
    if (!empty($logIds) && is_array($logIds)) {
        $logIds = array_filter($logIds, 'is_numeric');
        if (!empty($logIds)) {
            $placeholders = implode(',', array_fill(0, count($logIds), '?'));
            $whereConditions[] = "id IN ($placeholders)";
            $params = array_merge($params, $logIds);
            $types .= str_repeat('i', count($logIds));
        }
    }

    // Build the WHERE clause
    $whereClause = '';
    if (!empty($whereConditions)) {
        $whereClause = " AND " . implode(" AND ", $whereConditions);
    }

    // Build final query
    $query = "SELECT $selectFields $baseQuery $whereClause ORDER BY created_at DESC";
    
    // Prepare and execute query
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    // Bind parameters if any
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }

    $result = $stmt->get_result();
    $logs = [];

    // Fetch all logs
    while ($row = $result->fetch_assoc()) {
        $logs[] = [
            'ID' => $row['id'],
            'Type' => $row['log_type'] == 'activity' ? 'Activity' : 'History',
            'User' => $row['user_name'],
            'User Type' => $row['user_type'],
            'Action' => $row['action'],
            'Details' => $row['details'],
            'IP Address' => $row['ip_address'],
            'User Agent' => $row['user_agent'],
            'Date' => $row['created_at']
        ];
    }

    $stmt->close();

    if (empty($logs)) {
        throw new Exception("No logs found for export");
    }

    // Export based on format
    switch (strtolower($format)) {
        case 'csv':
            exportCSV($logs);
            break;
        case 'json':
            exportJSON($logs);
            break;
        case 'pdf':
            exportPDF($logs);
            break;
        default:
            exportCSV($logs);
    }

} catch (Exception $e) {
    error_log("Export error: " . $e->getMessage());
    
    // Return JSON error for AJAX calls
    if ($format === 'json') {
        echo json_encode([
            'success' => false,
            'message' => 'Export failed: ' . $e->getMessage()
        ]);
    } else {
        // For other formats, show simple error
        echo "Export Error: " . htmlspecialchars($e->getMessage());
    }
    exit;
}

function exportCSV($logs) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=system_logs_' . date('Y-m-d_H-i-s') . '.csv');
    
    $output = fopen('php://output', 'w');
    
    // Add BOM for UTF-8
    fwrite($output, "\xEF\xBB\xBF");
    
    // Write headers
    if (!empty($logs)) {
        fputcsv($output, array_keys($logs[0]));
    }
    
    // Write data
    foreach ($logs as $log) {
        // Clean data for CSV
        $cleanLog = array_map(function($value) {
            // Remove any HTML tags and trim
            $value = strip_tags($value);
            $value = trim($value);
            // Escape quotes for CSV
            $value = str_replace('"', '""', $value);
            return $value;
        }, $log);
        
        fputcsv($output, $cleanLog);
    }
    
    fclose($output);
    exit;
}

function exportJSON($logs) {
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename=system_logs_' . date('Y-m-d_H-i-s') . '.json');
    
    echo json_encode([
        'success' => true,
        'count' => count($logs),
        'generated_at' => date('Y-m-d H:i:s'),
        'logs' => $logs
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

function exportPDF($logs) {
    // For now, redirect to CSV since PDF requires additional libraries
    // In production, you might want to use a library like TCPDF or mPDF
    header('Content-Type: text/html; charset=utf-8');
    echo "<html><body>";
    echo "<h1>PDF Export Not Implemented</h1>";
    echo "<p>PDF export requires additional libraries. For now, please use CSV or JSON format.</p>";
    echo "<p>Number of logs available for export: " . count($logs) . "</p>";
    echo "</body></html>";
    exit;
}