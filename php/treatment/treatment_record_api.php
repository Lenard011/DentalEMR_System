<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);

$host = "localhost";
$user = "u401132124_dentalclinic";  // change if needed
$pass = "Mho_DentalClinic1st";      // change if needed
$db   = "u401132124_mho_dentalemr";

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    echo json_encode(["success" => false, "message" => "DB connection failed"]);
    exit;
}

$input = json_decode(file_get_contents("php://input"), true);

if (!$input || !isset($input['patient_id'])) {
    echo json_encode(["success" => false, "message" => "Missing patient_id"]);
    exit;
}

$pid = $conn->real_escape_string($input['patient_id']);
$oral_prophylaxis   = $conn->real_escape_string($input['oral_prophylaxis']);
$fluoride           = $conn->real_escape_string($input['fluoride']);
$sealant            = $conn->real_escape_string($input['sealant']);
$permanent_filling  = $conn->real_escape_string($input['permanent_filling']);
$temporary_filling  = $conn->real_escape_string($input['temporary_filling']);
$extraction         = $conn->real_escape_string($input['extraction']);
$consultation       = $conn->real_escape_string($input['consultation']);
$remarks            = $conn->real_escape_string($input['remarks']);

// Check if record already exists for this patient
$check = $conn->query("SELECT id FROM patient_treatment_record WHERE patient_id='$pid' LIMIT 1");

if ($check && $check->num_rows > 0) {
    // Update existing record
    $row = $check->fetch_assoc();
    $id = $row['id'];
    $sql = "UPDATE patient_treatment_record 
            SET oral_prophylaxis='$oral_prophylaxis',
                fluoride='$fluoride',
                sealant='$sealant',
                permanent_filling='$permanent_filling',
                temporary_filling='$temporary_filling',
                extraction='$extraction',
                consultation='$consultation',
                remarks='$remarks',
                updated_at=NOW()
            WHERE id='$id'";
} else {
    // Insert new record
    $sql = "INSERT INTO patient_treatment_record 
            (patient_id, oral_prophylaxis, fluoride, sealant, permanent_filling, temporary_filling, extraction, consultation, remarks, updated_at)
            VALUES ('$pid', '$oral_prophylaxis', '$fluoride', '$sealant', '$permanent_filling', '$temporary_filling', '$extraction', '$consultation', '$remarks', NOW())";
}

if ($conn->query($sql)) {
    echo json_encode(["success" => true]);
} else {
    echo json_encode(["success" => false, "message" => $conn->error]);
}

$conn->close();
