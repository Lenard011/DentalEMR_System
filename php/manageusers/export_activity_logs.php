<?php
session_start();
date_default_timezone_set('Asia/Manila');

// Check if user is authorized using GET parameter (same as other pages)
if (!isset($_GET['uid']) || !isset($_SESSION['active_sessions'][$_GET['uid']])) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized - Please log in again']);
    exit;
}

$userId = $_GET['uid'];
$format = $_GET['format'] ?? 'csv';
$searchTerm = $_GET['search'] ?? '';

// Database connection
$host = "localhost";
$dbUser = "root";
$dbPass = "";
$dbName = "dentalemr_system";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbName;charset=utf8", $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Build query
    $sql = "SELECT * FROM activity_logs";
    $params = [];

    if (!empty($searchTerm)) {
        $sql .= " WHERE user_name LIKE ? OR action LIKE ? OR target LIKE ? OR details LIKE ?";
        $searchParam = "%$searchTerm%";
        $params = array_fill(0, 4, $searchParam);
    }

    $sql .= " ORDER BY created_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Set headers based on format
    $currentDate = date('Y-m-d');
    $filename = "activity_logs_{$currentDate}.{$format}";

    switch ($format) {
        case 'csv':
            exportCSV($activities, $filename);
            break;
        case 'json':
            exportJSON($activities, $filename);
            break;
        case 'txt':
            exportTXT($activities, $filename);
            break;
        case 'pdf':
            exportPDF($activities, $filename);
            break;
        default:
            exportCSV($activities, $filename);
    }
} catch (PDOException $e) {
    http_response_code(500);
    die('Database error: ' . $e->getMessage());
}

function exportCSV($data, $filename)
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');

    // Add BOM for Excel compatibility
    fputs($output, $bom = (chr(0xEF) . chr(0xBB) . chr(0xBF)));

    // Headers
    fputcsv($output, [
        'ID',
        'User ID',
        'User Name',
        'Action',
        'Target',
        'Details',
        'IP Address',
        'User Agent',
        'Date'
    ]);

    // Data
    foreach ($data as $row) {
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

    fclose($output);
    exit;
}

function exportJSON($data, $filename)
{
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $exportData = [
        'export_date' => date('Y-m-d H:i:s'),
        'total_records' => count($data),
        'activities' => $data
    ];

    echo json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

function exportTXT($data, $filename)
{
    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    echo "ACTIVITY LOGS EXPORT\n";
    echo "Generated on: " . date('Y-m-d H:i:s') . "\n";
    echo "Total records: " . count($data) . "\n";
    echo str_repeat("=", 80) . "\n\n";

    foreach ($data as $index => $row) {
        echo "Record #" . ($index + 1) . "\n";
        echo str_repeat("-", 40) . "\n";
        echo "ID: " . $row['id'] . "\n";
        echo "User: " . $row['user_name'] . " (ID: " . $row['user_id'] . ")\n";
        echo "Action: " . $row['action'] . "\n";
        echo "Target: " . $row['target'] . "\n";
        echo "Details: " . $row['details'] . "\n";
        echo "IP Address: " . $row['ip_address'] . "\n";
        echo "Date: " . $row['created_at'] . "\n";
        echo "\n";
    }
    exit;
}

function exportPDF($data, $filename)
{
    // For PDF, we'll create a simple HTML that browsers can print as PDF
    header('Content-Type: text/html; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $html = '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Activity Logs</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            h1 { color: #333; border-bottom: 2px solid #333; padding-bottom: 10px; }
            table { width: 100%; border-collapse: collapse; margin-top: 20px; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; font-size: 12px; }
            th { background-color: #f5f5f5; }
            .header-info { margin-bottom: 20px; }
        </style>
    </head>
    <body>
        <h1>Activity Logs Report</h1>
        <div class="header-info">
            <p><strong>Generated on:</strong> ' . date('Y-m-d H:i:s') . '</p>
            <p><strong>Total records:</strong> ' . count($data) . '</p>
        </div>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>User</th>
                    <th>Action</th>
                    <th>Target</th>
                    <th>Details</th>
                    <th>IP Address</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>';

    foreach ($data as $row) {
        $html .= '
                <tr>
                    <td>' . htmlspecialchars($row['id']) . '</td>
                    <td>' . htmlspecialchars($row['user_name']) . ' (ID: ' . htmlspecialchars($row['user_id']) . ')</td>
                    <td>' . htmlspecialchars($row['action']) . '</td>
                    <td>' . htmlspecialchars($row['target']) . '</td>
                    <td>' . htmlspecialchars($row['details']) . '</td>
                    <td>' . htmlspecialchars($row['ip_address']) . '</td>
                    <td>' . htmlspecialchars($row['created_at']) . '</td>
                </tr>';
    }

    $html .= '
            </tbody>
        </table>
        <script>
            // Auto-print and close after a short delay
            setTimeout(function() {
                window.print();
                setTimeout(function() {
                    window.close();
                }, 500);
            }, 1000);
        </script>
    </body>
    </html>';

    echo $html;
    exit;
}
