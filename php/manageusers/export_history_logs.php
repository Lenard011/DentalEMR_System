<?php
session_start();
header('Content-Type: text/plain');

// Database connection
$host = "localhost";
$dbUser = "root";
$dbPass = "";
$dbName = "dentalemr_system";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Get parameters
$format = $_GET['format'] ?? 'csv';
$search = $_GET['search'] ?? '';

// Build query
$where = "";
if (!empty($search)) {
    $where = " WHERE table_name LIKE :search OR action LIKE :search OR description LIKE :search OR changed_by_type LIKE :search";
}

$query = "SELECT * FROM history_logs" . $where . " ORDER BY history_id DESC";
$stmt = $pdo->prepare($query);

if (!empty($search)) {
    $searchTerm = "%$search%";
    $stmt->bindParam(':search', $searchTerm);
}

$stmt->execute();
$historyLogs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Set filename with timestamp
$timestamp = date('Y-m-d_H-i-s');
$filename = "history_logs_export_{$timestamp}";

switch ($format) {
    case 'csv':
        exportToCSV($historyLogs, $filename);
        break;
    case 'json':
        exportToJSON($historyLogs, $filename);
        break;
    case 'txt':
        exportToTXT($historyLogs, $filename);
        break;
    case 'pdf':
        exportToPDF($historyLogs, $filename);
        break;
    default:
        exportToCSV($historyLogs, $filename);
}

function exportToCSV($data, $filename)
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');

    $output = fopen('php://output', 'w');

    // Add BOM for UTF-8
    fputs($output, $bom = (chr(0xEF) . chr(0xBB) . chr(0xBF)));

    // Headers
    fputcsv($output, [
        'History ID',
        'Table Name',
        'Record ID',
        'Action',
        'Changed By Type',
        'Changed By ID',
        'Old Values',
        'New Values',
        'Description',
        'IP Address',
        'Created At'
    ]);

    // Data
    foreach ($data as $row) {
        fputcsv($output, [
            $row['history_id'],
            $row['table_name'],
            $row['record_id'],
            $row['action'],
            $row['changed_by_type'],
            $row['changed_by_id'],
            $row['old_values'],
            $row['new_values'],
            $row['description'],
            $row['ip_address'],
            $row['created_at']
        ]);
    }

    fclose($output);
    exit;
}

function exportToJSON($data, $filename)
{
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="' . $filename . '.json"');

    $exportData = [
        'export_info' => [
            'exported_at' => date('Y-m-d H:i:s'),
            'total_records' => count($data),
            'format' => 'json'
        ],
        'history_logs' => $data
    ];

    echo json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

function exportToTXT($data, $filename)
{
    header('Content-Type: text/plain');
    header('Content-Disposition: attachment; filename="' . $filename . '.txt"');

    echo "HISTORY LOGS EXPORT\n";
    echo "Generated on: " . date('Y-m-d H:i:s') . "\n";
    echo "Total records: " . count($data) . "\n";
    echo str_repeat("=", 50) . "\n\n";

    foreach ($data as $index => $row) {
        echo "RECORD #" . ($index + 1) . "\n";
        echo str_repeat("-", 30) . "\n";
        echo "History ID: " . $row['history_id'] . "\n";
        echo "Table Name: " . $row['table_name'] . "\n";
        echo "Record ID: " . $row['record_id'] . "\n";
        echo "Action: " . $row['action'] . "\n";
        echo "Changed By: " . $row['changed_by_type'] . " (ID: " . $row['changed_by_id'] . ")\n";
        echo "Description: " . ($row['description'] ?: 'N/A') . "\n";
        echo "IP Address: " . ($row['ip_address'] ?: 'N/A') . "\n";
        echo "Date: " . $row['created_at'] . "\n";

        if (!empty($row['old_values'])) {
            echo "Old Values:\n" . $row['old_values'] . "\n";
        }

        if (!empty($row['new_values'])) {
            echo "New Values:\n" . $row['new_values'] . "\n";
        }

        echo "\n" . str_repeat("=", 50) . "\n\n";
    }
    exit;
}

function exportToPDF($data, $filename)
{
    // For PDF generation, we'll create a simple HTML that can be printed as PDF
    // Users can use browser's "Print to PDF" functionality

    header('Content-Type: text/html');
    echo generatePDFHTML($data, $filename);
    exit;
}

function generatePDFHTML($data, $filename)
{
    $html = '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>History Logs Export</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #333; padding-bottom: 10px; }
            .table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
            .table th, .table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            .table th { background-color: #f5f5f5; font-weight: bold; }
            .summary { margin-bottom: 20px; padding: 10px; background-color: #f9f9f9; border-left: 4px solid #007bff; }
            .json-data { font-family: monospace; font-size: 10px; white-space: pre-wrap; max-width: 200px; }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>History Logs Export</h1>
            <p>Generated on: ' . date('Y-m-d H:i:s') . '</p>
            <p>Total Records: ' . count($data) . '</p>
        </div>';

    if (!empty($data)) {
        $html .= '<table class="table">
            <thead>
                <tr>
                    <th>History ID</th>
                    <th>Table Name</th>
                    <th>Record ID</th>
                    <th>Action</th>
                    <th>Changed By</th>
                    <th>Description</th>
                    <th>IP Address</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>';

        foreach ($data as $row) {
            $html .= '<tr>
                <td>' . htmlspecialchars($row['history_id']) . '</td>
                <td>' . htmlspecialchars($row['table_name']) . '</td>
                <td>' . htmlspecialchars($row['record_id']) . '</td>
                <td>' . htmlspecialchars($row['action']) . '</td>
                <td>' . htmlspecialchars($row['changed_by_type']) . ' (ID: ' . htmlspecialchars($row['changed_by_id']) . ')</td>
                <td>' . htmlspecialchars($row['description'] ?: 'N/A') . '</td>
                <td>' . htmlspecialchars($row['ip_address'] ?: 'N/A') . '</td>
                <td>' . htmlspecialchars($row['created_at']) . '</td>
            </tr>';
        }

        $html .= '</tbody></table>';

        // Add JSON data section
        $html .= '<div class="summary">
            <h3>Detailed Data (First 3 records):</h3>';

        for ($i = 0; $i < min(3, count($data)); $i++) {
            $row = $data[$i];
            $html .= '<div style="margin-bottom: 20px;">
                <h4>Record #' . ($i + 1) . ' - History ID: ' . $row['history_id'] . '</h4>
                <div class="json-data">
                    <strong>Old Values:</strong><br>' . htmlspecialchars($row['old_values'] ?: 'N/A') . '<br><br>
                    <strong>New Values:</strong><br>' . htmlspecialchars($row['new_values'] ?: 'N/A') . '
                </div>
            </div>';
        }

        $html .= '</div>';
    } else {
        $html .= '<p>No history logs found for export.</p>';
    }

    $html .= '
        <script>
            // Auto-trigger print dialog for PDF
            window.onload = function() {
                window.print();
            };
        </script>
    </body>
    </html>';

    return $html;
}
