<?php
header("Content-Type: application/json");
include_once("../conn.php");

$patient_id = 1; // Test with patient ID 1

echo "Testing API for patient_id = $patient_id\n\n";

$sql = "SELECT * FROM oral_health_condition WHERE patient_id = ? ORDER BY created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$result = $stmt->get_result();

$count = 0;
echo "Records found:\n";
while ($row = $result->fetch_assoc()) {
    $count++;
    echo "Record $count: ID={$row['id']}, Date={$row['created_at']}\n";
}

echo "\nTotal records: $count\n";

$stmt->close();
$conn->close();
?>