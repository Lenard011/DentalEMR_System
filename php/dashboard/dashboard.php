<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // Allow Next.js frontend
require_once 'db.php';

$conn = connectDB();

// Total Patients
$totalPatients = $conn->query("SELECT COUNT(*) AS total FROM patients")->fetch_assoc()['total'];

// Sex Distribution
$sexData = [];
$res = $conn->query("SELECT sex, COUNT(*) AS total FROM patients GROUP BY sex");
while ($row = $res->fetch_assoc()) {
    $sexData[] = $row;
}

// Visit Trend
$visitData = [];
$res = $conn->query("SELECT visit_date, COUNT(*) AS total FROM visits GROUP BY visit_date ORDER BY visit_date");
while ($row = $res->fetch_assoc()) {
    $visitData[] = $row;
}

// Most Common Treatments
$treatmentData = [];
$res = $conn->query("
    SELECT t.description AS treatment, COUNT(sm.treatment_id) AS total
    FROM services_monitoring_chart sm
    JOIN treatments t ON sm.treatment_code = t.code
    GROUP BY t.description
    ORDER BY total DESC
");
while ($row = $res->fetch_assoc()) {
    $treatmentData[] = $row;
}

echo json_encode([
    "totalPatients" => $totalPatients,
    "sexCount" => $sexData,
    "visits" => $visitData,
    "treatments" => $treatmentData
]);

$conn->close();
?>
