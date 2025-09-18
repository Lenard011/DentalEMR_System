<?php
header("Content-Type: application/json");

// Database connection
$conn = new mysqli("localhost", "root", "", "dentalemr_system");
if ($conn->connect_error) {
    echo json_encode(["error" => "Database connection failed: " . $conn->connect_error]);
    exit();
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) {
    echo json_encode(["error" => "Invalid patient ID"]);
    exit();
}

// Query patient details + membership info
$sql = "SELECT 
            p.patient_id, 
            p.surname, p.firstname, p.middlename, 
            p.date_of_birth, p.place_of_birth, p.age, p.sex, 
            p.address, p.occupation, p.guardian,
            o.nhts_pr, o.four_ps, o.indigenous_people, o.pwd,
            o.philhealth_flag, o.philhealth_number,
            o.sss_flag, o.sss_number,
            o.gsis_flag, o.gsis_number
        FROM patients p
        LEFT JOIN patient_other_info o 
            ON p.patient_id = o.patient_id
        WHERE p.patient_id = ?
        LIMIT 1";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    echo json_encode($result->fetch_assoc());
} else {
    echo json_encode(["error" => "Patient not found"]);
}

$stmt->close();
$conn->close();
?>
