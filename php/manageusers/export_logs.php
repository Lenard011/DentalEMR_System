<?php
session_start();

// Check if user is logged in
if (!isset($_GET['user_id']) || !isset($_SESSION['active_sessions'][$_GET['user_id']])) {
    die('Unauthorized');
}

$userId = intval($_GET['user_id']);
$user = $_SESSION['active_sessions'][$userId];

// Check permission
if (!in_array($user['type'], ['Admin', 'Dentist'])) {
    die('Insufficient permissions');
}

$format = $_GET['format'] ?? 'csv';
$logIds = isset($_GET['log_ids']) ? explode(',', $_GET['log_ids']) : [];

// Database connection
$host = "localhost";
$dbUser = "root";
$dbPass = "";
$dbName = "dentalemr_system";

$conn = new mysqli($host, $dbUser, $dbPass, $dbName);

// Build query
$whereSQL = '';
if (!empty($logIds)) {
    $idsString = implode(',', array_map('intval', $logIds));
    $whereSQL = "WHERE id IN ($idsString)";
}

$sql = "SELECT * FROM system_logs $whereSQL ORDER BY created_at DESC";
$result = $conn->query($sql);

if ($format === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="system_logs_' . date('Y-m-d') . '.csv"');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Type', 'User', 'User Type', 'Action', 'Details', 'Description', 'IP Address', 'User Agent', 'Date']);

    while ($row = $result->fetch_assoc()) {
        fputcsv($output, [
            $row['id'],
            $row['type'],
            $row['user_name'],
            $row['user_type'],
            $row['action'],
            $row['details'],
            $row['description'],
            $row['ip_address'],
            $row['user_agent'],
            $row['created_at']
        ]);
    }

    fclose($output);
} elseif ($format === 'json') {
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="system_logs_' . date('Y-m-d') . '.json"');

    $logs = [];
    while ($row = $result->fetch_assoc()) {
        $logs[] = $row;
    }

    echo json_encode($logs, JSON_PRETTY_PRINT);
} else {
    // For PDF, you would need a PDF library like TCPDF or FPDF
    die('PDF export not implemented yet. Please use CSV or JSON format.');
}

$conn->close();
