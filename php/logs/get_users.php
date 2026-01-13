<?php
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

$users = [];

// Get dentists
$dentistsQuery = "SELECT id, name FROM dentist ORDER BY name";
$dentistsResult = $conn->query($dentistsQuery);
while ($row = $dentistsResult->fetch_assoc()) {
    $users[] = [
        'id' => 'D_' . $row['id'], // D_ prefix for Dentist
        'name' => $row['name'],
        'type' => 'Dentist'
    ];
}

// Get staff
$staffQuery = "SELECT id, name FROM staff ORDER BY name";
$staffResult = $conn->query($staffQuery);
while ($row = $staffResult->fetch_assoc()) {
    $users[] = [
        'id' => 'S_' . $row['id'], // S_ prefix for Staff
        'name' => $row['name'],
        'type' => 'Staff'
    ];
}

$conn->close();

echo json_encode([
    'success' => true,
    'users' => $users
]);
