<?php
// test_tables.php
session_start();
require_once './db_connection.php';

echo "<h2>Testing Database Tables</h2>";

if (!$conn) {
    echo "Database connection failed!";
    exit;
}

// Check activity_logs table
echo "<h3>Checking activity_logs table</h3>";
$result = $conn->query("SHOW CREATE TABLE activity_logs");
if ($result && $row = $result->fetch_assoc()) {
    echo "✓ activity_logs table exists<br>";
    echo "Structure: " . htmlspecialchars(substr($row['Create Table'], 0, 200)) . "...<br>";
    
    // Count records
    $countResult = $conn->query("SELECT COUNT(*) as count FROM activity_logs");
    if ($countRow = $countResult->fetch_assoc()) {
        echo "Records: " . $countRow['count'] . "<br>";
    }
} else {
    echo "✗ activity_logs table does not exist or error: " . $conn->error . "<br>";
}

// Check history_logs table
echo "<h3>Checking history_logs table</h3>";
$result = $conn->query("SHOW CREATE TABLE history_logs");
if ($result && $row = $result->fetch_assoc()) {
    echo "✓ history_logs table exists<br>";
    echo "Structure: " . htmlspecialchars(substr($row['Create Table'], 0, 200)) . "...<br>";
    
    // Count records
    $countResult = $conn->query("SELECT COUNT(*) as count FROM history_logs");
    if ($countRow = $countResult->fetch_assoc()) {
        echo "Records: " . $countRow['count'] . "<br>";
    }
} else {
    echo "✗ history_logs table does not exist or error: " . $conn->error . "<br>";
}

// Check for sample data
echo "<h3>Sample data from activity_logs</h3>";
$sample = $conn->query("SELECT * FROM activity_logs ORDER BY created_at DESC LIMIT 3");
if ($sample && $sample->num_rows > 0) {
    echo "<table border='1'><tr>";
    while ($row = $sample->fetch_assoc()) {
        echo "<td style='padding: 5px;'>";
        echo "ID: " . $row['id'] . "<br>";
        echo "User: " . ($row['user_name'] ?? 'N/A') . "<br>";
        echo "Action: " . $row['action'] . "<br>";
        echo "Date: " . $row['created_at'] . "<br>";
        echo "</td>";
    }
    echo "</tr></table>";
} else {
    echo "No records found in activity_logs<br>";
}

$conn->close();
?>