<?php
// services_monitoring.php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST");
header("Access-Control-Allow-Headers: Content-Type");

// DB connection
$host = "localhost";
$user = "root";
$pass = ""; // change this if you have a password
$dbname = "dentalemr_system";

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    echo json_encode(["error" => "Database connection failed"]);
    exit;
}

// Save treatment
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);

    $patient_id = $data['patient_id'] ?? null;
    $fdi_number = $data['fdi_number'] ?? null;
    $treatment_code = $data['treatment_code'] ?? null;

    if (!$patient_id || !$fdi_number || !$treatment_code) {
        echo json_encode(["error" => "Missing required fields"]);
        exit;
    }

    $stmt = $conn->prepare("
        INSERT INTO services_monitoring_chart (patient_id, fdi_number, treatment_code)
        VALUES (?, ?, ?)
    ");
    $stmt->bind_param("iis", $patient_id, $fdi_number, $treatment_code);

    if ($stmt->execute()) {
        echo json_encode(["success" => true, "message" => "Treatment saved"]);
    } else {
        echo json_encode(["error" => $stmt->error]);
    }
    exit;
}

// Load treatments by patient
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['patient_id'])) {
    $patient_id = intval($_GET['patient_id']);
    $sql = "SELECT id, fdi_number, treatment_code, created_at
            FROM services_monitoring_chart
            WHERE patient_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }

    echo json_encode($rows);
    exit;
}

echo json_encode(["error" => "Invalid request"]);
