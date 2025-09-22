<?php
header('Content-Type: application/json');
require_once "conn.php"; // $conn = new mysqli(...)

$patient_id = $_GET['patient_id'] ?? null;
if (!$patient_id) {
    echo json_encode(["success" => false, "message" => "No patient ID provided"]);
    exit;
}

try {
    $stmt = $conn->prepare("SELECT * FROM medical_history WHERE patient_id = ?");
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $history = $result->fetch_assoc();

    echo json_encode([
        "success" => true,
        "data" => $history ?: []
    ]);
} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
