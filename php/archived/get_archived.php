<?php
header('Content-Type: application/json; charset=utf-8');
mysqli_report(MYSQLI_REPORT_OFF);

// Database connection
$mysqli = new mysqli("localhost", "root", "", "dentalemr_system");

// Handle connection error safely
if ($mysqli->connect_errno) {
    echo json_encode([]);
    exit;
}

// Ensure table exists
$check = $mysqli->query("SHOW TABLES LIKE 'archived_patients'");
if (!$check || $check->num_rows === 0) {
    echo json_encode([]);
    exit;
}

// ✅ Adjusted columns based on your table
$sql = "SELECT archive_id, patient_id, firstname, middlename, surname, sex, age, address, created_at 
        FROM archived_patients 
        ORDER BY archive_id DESC";

$result = $mysqli->query($sql);

// Handle query failure
if (!$result) {
    echo json_encode([]);
    exit;
}

$patients = [];
while ($row = $result->fetch_assoc()) {
    $patients[] = $row;
}

// Output JSON
echo json_encode($patients);
$mysqli->close();
exit;
