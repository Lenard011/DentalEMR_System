<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

$servername = "localhost";
$username   = "root";      // default for XAMPP
$password   = "";          // default for XAMPP (empty)
$dbname     = "dentalemr_system"; // CHANGE to your actual DB name

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    echo json_encode(["success" => false, "message" => "DB connection failed: " . $conn->connect_error]);
    exit;
}

// ✅ Read raw JSON input
$input = json_decode(file_get_contents("php://input"), true);

if (!$input) {
    echo json_encode(["success" => false, "message" => "Invalid JSON input"]);
    exit;
}

$patient_id = $conn->real_escape_string($input['patient_id']);
$treatments = $input['treatments'];

if (empty($treatments)) {
    echo json_encode(["success" => false, "message" => "No treatments provided"]);
    exit;
}

// ✅ Example table: patient_treatments (make sure it exists)
$stmt = $conn->prepare("INSERT INTO services_monitoring_chart (patient_id, tooth_id, treatment_code) VALUES (?, ?, ?)");

foreach ($treatments as $t) {
    $tooth_id = $t['tooth_id'];
    $treatment_id = $t['treatment_id'];
    $stmt->bind_param("iss", $patient_id, $tooth_id, $treatment_id);
    $stmt->execute();
}

$stmt->close();
$conn->close();

echo json_encode(["success" => true, "message" => "Treatments saved successfully"]);
