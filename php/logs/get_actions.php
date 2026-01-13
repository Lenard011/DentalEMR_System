<?php
// get_all_actions.php
session_start();
header('Content-Type: application/json');

$host = "localhost";
$dbUser = "u401132124_dentalclinic";
$dbPass = "Mho_DentalClinic1st";
$dbName = "u401132124_mho_dentalemr";

$conn = new mysqli($host, $dbUser, $dbPass, $dbName);

if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// Get all unique actions from activity_logs
$query1 = "SELECT DISTINCT action FROM activity_logs WHERE action IS NOT NULL AND action != ''";
$result1 = $conn->query($query1);

$actions = [];
while ($row = $result1->fetch_assoc()) {
    $actions[] = $row['action'];
}

// Get all unique actions from history_logs
$query2 = "SELECT DISTINCT action FROM history_logs WHERE action IS NOT NULL AND action != ''";
$result2 = $conn->query($query2);
while ($row = $result2->fetch_assoc()) {
    $actions[] = $row['action'];
}

// Remove duplicates and sort
$actions = array_unique($actions);
sort($actions);

$conn->close();

echo json_encode([
    'success' => true,
    'actions' => array_values($actions) // Re-index array
]);
