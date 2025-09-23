<?php
header('Content-Type: application/json');
require_once "conn.php"; // must define $conn = new mysqli(...)

try {
    if (!isset($conn) || $conn->connect_error) {
        echo json_encode(["success" => false, "message" => "Database connection failed"]);
        exit;
    }

    $patient_id = $_POST['patient_id'] ?? null;
    if (!$patient_id) {
        echo json_encode(["success" => false, "message" => "No patient specified"]);
        exit;
    }

    // Collect values
    $allergies_flag        = isset($_POST['allergies_flag']) ? 1 : 0;
    $allergies_details     = $_POST['allergies_details'] ?? null;

    $hypertension_cva      = isset($_POST['hypertension_cva']) ? 1 : 0;
    $diabetes_mellitus     = isset($_POST['diabetes_mellitus']) ? 1 : 0;
    $blood_disorders       = isset($_POST['blood_disorders']) ? 1 : 0;
    $heart_disease         = isset($_POST['heart_disease']) ? 1 : 0;
    $thyroid_disorders     = isset($_POST['thyroid_disorders']) ? 1 : 0;

    $hepatitis_flag        = isset($_POST['hepatitis_flag']) ? 1 : 0;
    $hepatitis_details     = $_POST['hepatitis_details'] ?? null;

    $malignancy_flag       = isset($_POST['malignancy_flag']) ? 1 : 0;
    $malignancy_details    = $_POST['malignancy_details'] ?? null;

    $prev_hospitalization_flag = isset($_POST['prev_hospitalization_flag']) ? 1 : 0;
    $last_admission_date   = $_POST['last_admission_date'] ?? null;
    $admission_cause       = $_POST['admission_cause'] ?? null;
    $surgery_details       = $_POST['surgery_details'] ?? null;

    $blood_transfusion_flag = isset($_POST['blood_transfusion_flag']) ? 1 : 0;
    $tattoo                = isset($_POST['tattoo']) ? 1 : 0;

    $other_conditions_flag = isset($_POST['other_conditions_flag']) ? 1 : 0;
    $other_conditions      = $_POST['other_conditions'] ?? null;

    // Check if row exists
    $check = $conn->prepare("SELECT history_id FROM medical_history WHERE patient_id = ?");
    $check->bind_param("i", $patient_id);
    $check->execute();
    $res = $check->get_result();

    if ($res->num_rows > 0) {
        // Update existing record
        $stmt = $conn->prepare("
            UPDATE medical_history SET
                allergies_flag=?, allergies_details=?,
                hypertension_cva=?, diabetes_mellitus=?, blood_disorders=?, heart_disease=?, thyroid_disorders=?,
                hepatitis_flag=?, hepatitis_details=?,
                malignancy_flag=?, malignancy_details=?,
                prev_hospitalization_flag=?, last_admission_date=?, admission_cause=?, surgery_details=?,
                blood_transfusion_flag=?, tattoo=?,
                other_conditions_flag=?, other_conditions=?
            WHERE patient_id=?
        ");
        $stmt->bind_param(
            "isiiiiisisiisssiisi",
            $allergies_flag, $allergies_details,
            $hypertension_cva, $diabetes_mellitus, $blood_disorders, $heart_disease, $thyroid_disorders,
            $hepatitis_flag, $hepatitis_details,
            $malignancy_flag, $malignancy_details,
            $prev_hospitalization_flag, $last_admission_date, $admission_cause, $surgery_details,
            $blood_transfusion_flag, $tattoo,
            $other_conditions_flag, $other_conditions,
            $patient_id
        );
        $stmt->execute();
    } else {
        // Insert new record
        $stmt = $conn->prepare("
            INSERT INTO medical_history (
                patient_id, allergies_flag, allergies_details,
                hypertension_cva, diabetes_mellitus, blood_disorders, heart_disease, thyroid_disorders,
                hepatitis_flag, hepatitis_details,
                malignancy_flag, malignancy_details,
                prev_hospitalization_flag, last_admission_date, admission_cause, surgery_details,
                blood_transfusion_flag, tattoo,
                other_conditions_flag, other_conditions
            ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
        ");
        $stmt->bind_param(
            "iisiiiiisisiisssiis",
            $patient_id,
            $allergies_flag, $allergies_details,
            $hypertension_cva, $diabetes_mellitus, $blood_disorders, $heart_disease, $thyroid_disorders,
            $hepatitis_flag, $hepatitis_details,
            $malignancy_flag, $malignancy_details,
            $prev_hospitalization_flag, $last_admission_date, $admission_cause, $surgery_details,
            $blood_transfusion_flag, $tattoo,
            $other_conditions_flag, $other_conditions
        );
        $stmt->execute();
    }

    echo json_encode(["success" => true, "message" => "Medical history saved successfully"]);
} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
