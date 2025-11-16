<?php
header("Content-Type: application/json; charset=UTF-8");
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli("localhost", "root", "", "dentalemr_system");
    $conn->set_charset("utf8mb4");

    // Get patient ID from GET query
    $patient_id = $_GET['id'] ?? null;
    if (!$patient_id || !is_numeric($patient_id)) {
        echo json_encode(["success" => false, "error" => "Missing or invalid patient ID"]);
        exit();
    }

    // Fetch patient data from DB
    $stmt = $conn->prepare("
        SELECT 
            patient_id,
            surname,
            firstname,
            middlename,
            date_of_birth,
            place_of_birth,
            age,
            months_old AS agemonth,
            sex,
            pregnant,
            address,
            occupation,
            guardian,
            created_at,
            updated_at
        FROM patients
        WHERE patient_id = ?
        LIMIT 1
    ");
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        // Generate display_age without changing DB
        if ((int)$row['age'] === 0) {
            $row['display_age'] = $row['agemonth'] . " months old";
        } else {
            $row['display_age'] = $row['age'] . " years old";
        }

        echo json_encode(["success" => true, "patient" => $row], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode(["success" => false, "error" => "Patient not found"]);
    }

    $stmt->close();
    $conn->close();
} catch (Exception $e) {
    echo json_encode(["success" => false, "error" => "Server error: " . $e->getMessage()]);
}
