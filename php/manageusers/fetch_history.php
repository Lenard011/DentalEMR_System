<?php
header("Content-Type: application/json");

// DB connection
$conn = new mysqli("localhost", "root", "", "dentalemr_system");
if ($conn->connect_error) {
    die(json_encode(["error" => "Database connection failed"]));
}

// Search filter
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : "";

$query = "
    SELECT *
    FROM history_logs
    WHERE 
        table_name LIKE '%$search%' OR
        action LIKE '%$search%' OR
        description LIKE '%$search%' OR
        changed_by_type LIKE '%$search%'
    ORDER BY history_id DESC
";

$result = $conn->query($query);

$history = [];
while ($row = $result->fetch_assoc()) {
    $history[] = $row;
}

echo json_encode($history);
?>
