<?php
header('Content-Type: application/json');

// Database credentials
$host = "localhost";
$user = "root";
$pass = "";
$dbname = "dentalemr_system";

// Create connection
$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    echo json_encode(["error" => "Database connection failed: " . $conn->connect_error]);
    exit;
}

// Get patient_id from query
$patient_id = isset($_GET['patient_id']) ? intval($_GET['patient_id']) : 0;
if ($patient_id <= 0) {
    echo json_encode(["error" => "Invalid or missing patient_id"]);
    exit;
}

// Fetch patient info
$patient_sql = "SELECT surname, firstname, middlename FROM patients WHERE patient_id = ?";
$patient_stmt = $conn->prepare($patient_sql);
$patient_stmt->bind_param("i", $patient_id);
$patient_stmt->execute();
$patient_result = $patient_stmt->get_result();

$patient_name = "";
if ($patient_result && $patient_result->num_rows > 0) {
    $row = $patient_result->fetch_assoc();
    $middlename = $row['middlename'] ? " " . $row['middlename'] : "";
    $patient_name = $row['firstname'] . $middlename . ". " . $row['surname'];
}
$patient_stmt->close();

// Fetch treatment records with FDI number
$sql = "
    SELECT smc.treatment_id, smc.patient_id, t.fdi_number, smc.treatment_code, smc.created_at
    FROM services_monitoring_chart smc
    LEFT JOIN teeth t ON smc.tooth_id = t.tooth_id
    WHERE smc.patient_id = ?
    ORDER BY smc.created_at DESC
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$result = $stmt->get_result();

$records = [];
while ($row = $result->fetch_assoc()) {
    $records[] = $row;
}

$stmt->close();
$conn->close();

echo json_encode([
    "patient_name" => $patient_name,
    "records" => $records
]);
