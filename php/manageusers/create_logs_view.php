<?php
// create_logs_view.php
$host = "localhost";
$dbUser = "root";
$dbPass = "";
$dbName = "dentalemr_system";

$conn = new mysqli($host, $dbUser, $dbPass, $dbName);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "<h2>Creating System Logs View</h2>";

// Create a view that combines activity_logs and history_logs
$viewSql = "CREATE OR REPLACE VIEW system_logs_view AS
SELECT 
    id,
    user_id,
    user_name,
    action,
    details,
    ip_address,
    user_agent,
    created_at,
    'activity' as log_type,
    CASE 
        WHEN user_id IN (SELECT id FROM dentist) THEN 'dentist'
        WHEN user_id IN (SELECT id FROM staff) THEN 'staff'
        ELSE 'user'
    END as user_type
FROM activity_logs
WHERE 1=1
UNION ALL
SELECT 
    history_id as id,
    changed_by_id as user_id,
    NULL as user_name,
    CONCAT(action, ' on ', table_name) as action,
    description as details,
    ip_address,
    NULL as user_agent,
    created_at,
    'history' as log_type,
    changed_by_type as user_type
FROM history_logs
WHERE 1=1";

if ($conn->query($viewSql) === TRUE) {
    echo "✓ system_logs_view created successfully<br>";
    
    // Test the view
    echo "<h3>Sample logs from the view:</h3>";
    $result = $conn->query("SELECT * FROM system_logs_view ORDER BY created_at DESC LIMIT 10");
    echo "<table border='1'>";
    echo "<tr><th>ID</th><th>Type</th><th>User</th><th>Action</th><th>Date</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$row['id']}</td>";
        echo "<td>{$row['log_type']}</td>";
        
        // FIX: Use ternary operator instead of ??
        $userName = isset($row['user_name']) && !empty($row['user_name']) ? $row['user_name'] : $row['user_type'];
        echo "<td>" . htmlspecialchars($userName) . "</td>";
        
        echo "<td>" . htmlspecialchars($row['action']) . "</td>";
        echo "<td>{$row['created_at']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "✗ Error creating view: " . $conn->error . "<br>";
}

$conn->close();
?>