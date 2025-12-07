<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

// Simple direct approach
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "dentalemr_system";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die(json_encode(["error" => "Connection failed: " . $conn->connect_error]));
}

// Get patient ID from URL
$patient_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($patient_id <= 0) {
    die(json_encode(["error" => "Invalid patient ID"]));
}

echo "Debug: Fetching records for patient_id = $patient_id\n\n";

// Simple direct query
$sql = "SELECT 
            o.*,
            CONCAT(p.firstname, ' ', p.middlename, '. ', p.surname) AS patient_name,
            p.age,
            p.sex
        FROM oral_health_condition o
        LEFT JOIN patients p ON o.patient_id = p.patient_id
        WHERE o.patient_id = $patient_id
        ORDER BY o.created_at DESC";

echo "SQL Query: $sql\n\n";

$result = $conn->query($sql);

$records = [];
$count = 0;

if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $count++;
        $records[] = $row;
        echo "Record $count: ID={$row['id']}, Created={$row['created_at']}, Patient={$row['patient_id']}\n";
    }
    echo "\nTotal records found: $count\n\n";
    
    // Return JSON
    echo json_encode([
        "success" => true,
        "count" => $count,
        "records" => $records,
        "sql" => $sql
    ], JSON_PRETTY_PRINT);
} else {
    echo "No records found\n";
    echo json_encode([
        "success" => false,
        "message" => "No records found",
        "count" => 0,
        "records" => []
    ]);
}

$conn->close();
?>