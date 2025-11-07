<?php
$conn = new mysqli("localhost", "root", "", "dentalemr_system");
if ($conn->connect_error) die("DB connection failed: " . $conn->connect_error);

if (isset($_POST['update_patient'])) {
    // Get POST data safely
    $patient_id = $_POST['patient_id'] ?? null;
    $firstname  = trim($_POST['firstname'] ?? '');
    $surname    = trim($_POST['surname'] ?? '');
    $middlename = trim($_POST['middlename'] ?? '');
    $dob        = $_POST['date_of_birth'] ?? null;
    $sex        = ucfirst(strtolower(trim($_POST['sex'] ?? '')));
    $age        = $_POST['age'] ?? null;
    $pob        = trim($_POST['place_of_birth'] ?? '');
    $occupation = trim($_POST['occupation'] ?? '');
    $address    = trim($_POST['address'] ?? '');
    $guardian   = trim($_POST['guardian'] ?? '');
    $pregnant   = strtolower(trim($_POST['pregnant'] ?? 'no'));

    // Validate patient ID
    if (!$patient_id) {
        header("Location: ../../html/viewrecord.html?error=nopid");
        exit();
    }

    //  FIXED: ensure 'sex' field is always valid ENUM value
    if (!in_array($sex, ['Male', 'Female'])) {
        $sex = 'Male'; // or use NULL if you want to allow no value
    }

    // Calculate age and months_old from DOB (optional safeguard)
    $months_old = null;
    if ($dob) {
        try {
            $dobDate = new DateTime($dob);
            $today = new DateTime();
            $interval = $dobDate->diff($today);
            $age = $interval->y;
            $months_old = $interval->y * 12 + $interval->m;
        } catch (Exception $e) {
            $age = 0;
            $months_old = 0;
        }
    }

    // Normalize pregnant field (only valid for females)
    if ($sex !== 'Female') {
        $pregnant = 'no';
    } else {
        if (!in_array($pregnant, ['yes', 'no'])) $pregnant = 'no';
    }

    // Prepare and execute the update
    $stmt = $conn->prepare("
        UPDATE patients
        SET firstname=?, surname=?, middlename=?, date_of_birth=?, sex=?, age=?, months_old=?,
            place_of_birth=?, occupation=?, address=?, guardian=?, pregnant=?, updated_at=NOW()
        WHERE patient_id=?
    ");

    if (!$stmt) die("Prepare failed: " . $conn->error);

    $stmt->bind_param(
        "sssssiisssssi",
        $firstname,
        $surname,
        $middlename,
        $dob,
        $sex,
        $age,
        $months_old,
        $pob,
        $occupation,
        $address,
        $guardian,
        $pregnant,
        $patient_id
    );

    if ($stmt->execute()) {
        header("Location: ../../html/viewrecord.php?id=" . urlencode($patient_id) . "&updated=1");
        exit();
    } else {
        header("Location: ../../html/viewrecord.php?id=" . urlencode($patient_id) . "&updated=0&error=db");
        exit();
    }
}

$conn->close();
