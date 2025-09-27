<?php
header('Content-Type: application/json');

$conn = new mysqli("localhost", "root", "", "dentalemr_system");
if ($conn->connect_error) {
    echo json_encode(["success" => false, "error" => "Database connection failed"]);
    exit;
}

$q  = $_GET['q'] ?? null;
$id = $_GET['id'] ?? null;

/**
 * 🔍 Case 1: Suggestions by query
 */
if ($q) {
    $qLike = "%$q%";
    $sql = "SELECT patient_id, surname, firstname, middlename, address, guardian
            FROM patients
            WHERE surname LIKE ? OR firstname LIKE ?
            ORDER BY surname ASC
            LIMIT 10";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $qLike, $qLike);
    $stmt->execute();
    $res = $stmt->get_result();

    $patients = [];
    while ($row = $res->fetch_assoc()) {
        $patients[] = $row;
    }

    echo json_encode($patients);
    exit;
}

/**
 * 📄 Case 2: Full patient details by ID
 */
if ($id) {
    $sql = "SELECT * FROM patients WHERE patient_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows == 0) {
        echo json_encode(["success" => false, "error" => "Patient not found"]);
        exit;
    }

    $patient = $res->fetch_assoc();

    // Get other info
    $sql2 = "SELECT * FROM patient_other_info WHERE patient_id = ?";
    $stmt2 = $conn->prepare($sql2);
    $stmt2->bind_param("i", $patient['patient_id']);
    $stmt2->execute();
    $info = $stmt2->get_result()->fetch_assoc();

    echo json_encode([
        "success" => true,
        "patient" => $patient,
        "info"    => $info
    ]);
    exit;
}

echo json_encode(["success" => false, "error" => "Invalid request"]);
