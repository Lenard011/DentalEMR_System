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
$patient = $result->fetch_assoc();

if (!$patient) {
    echo json_encode(["error" => "Patient not found"]);
    exit();
}
$stmt->close();

// Query vital signs (date only for recorded_at)
$vital_sql = "SELECT 
                vital_id, 
                blood_pressure, 
                pulse_rate, 
                temperature, 
                weight, 
                DATE(recorded_at) AS recorded_at
              FROM vital_signs
              WHERE patient_id = ?
              ORDER BY recorded_at DESC";
$vstmt = $conn->prepare($vital_sql);
$vstmt->bind_param("i", $id);
$vstmt->execute();
$vresult = $vstmt->get_result();

$vitals = [];
while ($row = $vresult->fetch_assoc()) {
    $vitals[] = $row;
}
$vstmt->close();

// Query medical history
$med_sql = "SELECT * FROM medical_history WHERE patient_id = ? LIMIT 1";
$mstmt = $conn->prepare($med_sql);
$mstmt->bind_param("i", $id);
$mstmt->execute();
$mresult = $mstmt->get_result();
$medical = $mresult->fetch_assoc();
$mstmt->close();

// Query dietary / social history
$diet_sql = "SELECT * FROM dietary_habits WHERE patient_id = ? LIMIT 1";
$dstmt = $conn->prepare($diet_sql);
$dstmt->bind_param("i", $id);
$dstmt->execute();
$dresult = $dstmt->get_result();
$diet = $dresult->fetch_assoc();
$dstmt->close();

$conn->close();

// Combine patient + vitals + medical history + dietary history
$patient["vital_signs"] = $vitals;
$patient["medical_history"] = $medical; 
$patient["dietary_habits"] = $diet;

echo json_encode($patient);
?>
