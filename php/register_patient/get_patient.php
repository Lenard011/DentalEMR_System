<?php
header("Content-Type: application/json; charset=UTF-8");
$conn = new mysqli("localhost", "root", "", "dentalemr_system");
if ($conn->connect_error) {
    echo json_encode(["success" => false, "error" => "DB connection failed"]);
    exit();
}

$patient_id = $_GET['id'] ?? null;
if (!$patient_id) {
    echo json_encode(["success" => false, "error" => "Missing id"]);
    exit();
}

$stmt = $conn->prepare("SELECT * FROM patients WHERE patient_id = ?");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    echo json_encode(["success" => true, "patient" => $row]);
} else {
    echo json_encode(["success" => false, "error" => "Patient not found"]);
}

$stmt->close();
$conn->close();
?>
