<?php
header("Content-Type: application/json");

$conn = new mysqli("localhost", "root", "", "dentalemr_system");

if ($conn->connect_error) {
    echo json_encode(["error" => "Database connection failed: " . $conn->connect_error]);
    exit;
}

// ✅ Use actual column names from your patients table
$sql = "SELECT patient_id, surname, firstname, middlename, sex, age, address FROM patients";
$result = $conn->query($sql);

if (!$result) {
    echo json_encode(["error" => "Query failed: " . $conn->error]);
    exit;
}

$patients = [];
while ($row = $result->fetch_assoc()) {
    $patients[] = $row;
}

if (empty($patients)) {
    echo json_encode(["message" => "No rows found in patients table"]);
} else {
    echo json_encode($patients);
}

$conn->close();
