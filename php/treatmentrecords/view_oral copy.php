<?php
header("Content-Type: application/json");
include_once("../conn.php"); // Ensure this path is correct and provides $conn (MySQLi)
// Handle "fetch single record" mode (used when changing date)
if (isset($_GET['record'])) {
    $record_id = intval($_GET['record']);

    $sql = "
        SELECT 
            o.*,
            CONCAT(p.firstname, ' ', p.middlename, '. ', p.surname) AS patient_name,
            p.age,
            p.sex
        FROM oral_health_condition o
        LEFT JOIN patients p ON o.patient_id = p.patient_id
        WHERE o.id = ?
        LIMIT 1
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        echo json_encode(["error" => "SQL prepare failed: " . $conn->error]);
        exit;
    }

    $stmt->bind_param("i", $record_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        echo json_encode($row);
    } else {
        echo json_encode(["error" => "Record not found"]);
    }

    $stmt->close();
    $conn->close();
    exit;
}

// Handle "fetch all records for a patient" mode (default)
if (!isset($_GET['id'])) {
    echo json_encode(["error" => "Missing patient ID"]);
    exit;
}

$patient_id = intval($_GET['id']);

$sql = "
    SELECT 
        o.*,
        CONCAT(p.firstname, ' ', p.middlename, '. ', p.surname) AS patient_name,
        p.age,
        p.sex
    FROM oral_health_condition o
    LEFT JOIN patients p ON o.patient_id = p.patient_id
    WHERE o.patient_id = ?
    ORDER BY o.created_at DESC
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(["error" => "SQL prepare failed: " . $conn->error]);
    exit;
}

$stmt->bind_param("i", $patient_id);
$stmt->execute();
$result = $stmt->get_result();

$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}

echo json_encode($data);
$stmt->close();
$conn->close();
