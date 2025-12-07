<?php
header("Content-Type: application/json");
include_once("../conn.php");

$patient_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($patient_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid patient ID']);
    exit;
}

try {
    $sql = "SELECT patient_id, firstname, middlename, surname, age, sex, birthdate, address, contact_number 
            FROM patients WHERE patient_id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $patient = $result->fetch_assoc();
        echo json_encode(['success' => true, 'data' => $patient]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Patient not found']);
    }
    
    $stmt->close();
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}

$conn->close();
?>