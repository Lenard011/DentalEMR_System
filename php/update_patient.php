<?php
// simple redirect-style update handler (no JSON)
$conn = new mysqli("localhost", "root", "", "dentalemr_system");
if ($conn->connect_error) {
    die("DB connection failed: " . $conn->connect_error);
}

if (isset($_POST['update_patient'])) {
    $patient_id = $_POST['patient_id'] ?? null;
    $firstname  = $_POST['firstname'] ?? null;
    $surname    = $_POST['surname'] ?? null;
    $middlename = $_POST['middlename'] ?? null;
    $dob        = $_POST['date_of_birth'] ?? null;
    $sex        = $_POST['sex'] ?? null;
    $age        = $_POST['age'] ?? null;
    $pob        = $_POST['place_of_birth'] ?? null;
    $occupation = $_POST['occupation'] ?? null;
    $address    = $_POST['address'] ?? null;
    $guardian   = $_POST['guardian'] ?? null;

    if (!$patient_id) {
        header("Location: ../html/viewrecord.html?error=nopid");
        exit();
    }

    $stmt = $conn->prepare("UPDATE patients 
        SET firstname=?, surname=?, middlename=?, date_of_birth=?, sex=?,  age=?, place_of_birth=?, occupation=?, address=?, guardian=? 
        WHERE patient_id=?");

    if (!$stmt) {
        // debug: show DB error (during development)
        die("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param("ssssssssssi", 
        $firstname, $surname, $middlename, $dob, $sex, $age, $pob, $occupation, $address, $guardian, $patient_id
    );

    if ($stmt->execute()) {
        // redirect back to the same patient page and indicate success
        header("Location: ../html/viewrecord.html?id=" . urlencode($patient_id) . "&updated=1");
        exit();
    } else {
        // redirect back with error flag (or handle as you like)
        header("Location: ../html/viewrecord.html?id=" . urlencode($patient_id) . "&updated=0&error=db");
        exit();
    }
}

$conn->close();
?>
