<?php
session_start();

if (!isset($_GET['uid'])) {
    die('Invalid request');
}

$userId = intval($_GET['uid']);

// Validate session
if (!isset($_SESSION['active_sessions'][$userId])) {
    die('Session expired');
}

$conn = new mysqli("localhost", "u401132124_dentalclinic", "Mho_DentalClinic1st", "u401132124_mho_dentalemr");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get filters
$filters = [
    'dateFrom' => $_GET['dateFrom'] ?? '',
    'dateTo' => $_GET['dateTo'] ?? '',
    'userId' => $_GET['userId'] ?? '',
    'action' => $_GET['action'] ?? '',
    'logType' => $_GET['logType'] ?? 'activity',
    'format' => $_GET['format'] ?? 'csv'
];

// Build query (similar to fetch_logs.php)
$table = $filters['logType'] === 'activity' ? 'activity_logs' : 'history_logs';
$query = "SELECT * FROM $table WHERE 1=1";

// Apply filters...

$result = $conn->query($query);

if ($filters['format'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=system_logs_' . date('Y-m-d_H-i-s') . '.csv');
    
    $output = fopen('php://output', 'w');
    
    // Write headers
    if ($filters['logType'] === 'activity') {
        fputcsv($output, ['ID', 'User ID', 'User Name', 'Action', 'Target', 'Details', 'IP Address', 'User Agent', 'Timestamp']);
        
        while ($row = $result->fetch_assoc()) {
            fputcsv($output, [
                $row['id'],
                $row['user_id'],
                $row['user_name'],
                $row['action'],
                $row['target'],
                $row['details'],
                $row['ip_address'],
                $row['user_agent'],
                $row['created_at']
            ]);
        }
    } else {
        fputcsv($output, ['ID', 'Table', 'Record ID', 'Action', 'Changed By', 'Old Values', 'New Values', 'Description', 'IP', 'Timestamp']);
        
        while ($row = $result->fetch_assoc()) {
            fputcsv($output, [
                $row['history_id'],
                $row['table_name'],
                $row['record_id'],
                $row['action'],
                $row['changed_by_type'] . ' ID: ' . $row['changed_by_id'],
                $row['old_values'],
                $row['new_values'],
                $row['description'],
                $row['ip_address'],
                $row['created_at']
            ]);
        }
    }
    
    fclose($output);
} elseif ($filters['format'] === 'json') {
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename=system_logs_' . date('Y-m-d_H-i-s') . '.json');
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    
    echo json_encode($data, JSON_PRETTY_PRINT);
}

$conn->close();
?>