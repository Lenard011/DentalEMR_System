<?php
header("Content-Type: application/json");
$conn = new mysqli("localhost", "root", "", "dentalemr_system");

if ($conn->connect_error) {
    echo json_encode([]);
    exit;
}

$result = $conn->query("SELECT tooth_id, fdi_number FROM teeth ORDER BY fdi_number ASC");
$teeth = [];
while ($row = $result->fetch_assoc()) {
    $teeth[] = $row;
}
echo json_encode($teeth);
$conn->close();
?>
