<?php
header("Content-Type: application/json");

$host = "localhost";
$user = "root"; // change as needed
$pass = "";     // change as needed
$db   = "dentalemr_system";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["error" => "Database connection failed"]);
    exit;
}

$sql = "
    SELECT p.patient_id, 
           CONCAT(p.firstname, ' ', p.middlename, ' ', p.surname) AS fullname,
           p.sex,
           p.age,
           p.address,
           MAX(v.visit_date) as last_visit
    FROM patients p
    INNER JOIN visits v ON p.patient_id = v.patient_id
    GROUP BY p.patient_id
    ORDER BY p.patient_id ASC
";

$result = $conn->query($sql);

$patients = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $patients[] = $row;
    }
}

echo json_encode($patients);
$conn->close();
