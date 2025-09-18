<?php
header("Content-Type: application/json");

// Database connection
$conn = new mysqli("localhost", "root", "", "dentalemr_system");
if ($conn->connect_error) {
    echo json_encode(["error" => $conn->connect_error]);
    exit;
}

// Get patient ID from GET
$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    echo json_encode(["error" => "Invalid patient ID"]);
    exit;
}

// Detect the actual ID column in the table
$columns = $conn->query("SHOW COLUMNS FROM patients");
$id_column = "id"; // default
while($col = $columns->fetch_assoc()) {
    if (strtolower($col['Field']) === 'patient_id') {
        $id_column = 'patient_id';
        break;
    }
}

// Select patient details
$sql = "SELECT * FROM patients WHERE $id_column = $id LIMIT 1";
$result = $conn->query($sql);

if ($result && $row = $result->fetch_assoc()) {
    echo json_encode($row);
} else {
    echo json_encode(["error" => "Patient not found"]);
}

$conn->close();
?>
