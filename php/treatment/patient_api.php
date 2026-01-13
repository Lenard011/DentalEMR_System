<?php
header('Content-Type: application/json');

$host = "localhost";
$user = "u401132124_dentalclinic";
$pass = "Mho_DentalClinic1st";
$db   = "u401132124_mho_dentalemr";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    echo json_encode(["success" => false, "error" => "DB connection failed"]);
    exit;
}

$q  = $_GET['q'] ?? null;
$id = isset($_GET['id']) ? intval($_GET['id']) : null;
$input = json_decode(file_get_contents('php://input'), true);

// --------------------
// Case 1: suggestions
// --------------------
// --------------------
// Case 1: suggestions (improved: only if_treatment = 0)
// --------------------
if ($q !== null) {
    $qLike = "%{$q}%";

    $sql = "SELECT patient_id, surname, firstname, middlename, address, guardian, if_treatment
            FROM patients
            WHERE (surname LIKE ? OR firstname LIKE ?)
            ORDER BY surname ASC
            LIMIT 10";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $qLike, $qLike);
    $stmt->execute();
    $res = $stmt->get_result();

    $patients = [];
    while ($row = $res->fetch_assoc()) {
        $patients[] = $row;
    }

    echo json_encode($patients); // JS will handle if_treatment
    exit;
}



// --------------------
// Case 2: fetch full patient
// --------------------
if ($id !== null) {
    // patient
    $stmt = $conn->prepare("SELECT patient_id, surname, firstname, middlename, date_of_birth, place_of_birth, age, months_old, sex, address, pregnant, occupation, guardian
                            FROM patients
                            WHERE patient_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $patient = $stmt->get_result()->fetch_assoc();
    if (!$patient) {
        echo json_encode(["success" => false, "error" => "Patient not found"]);
        exit;
    }

    // patient_other_info
    $stmt = $conn->prepare("SELECT * FROM patient_other_info WHERE patient_id = ? LIMIT 1");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $other_info = $stmt->get_result()->fetch_assoc();

    // latest medical_history
    $stmt = $conn->prepare("SELECT * FROM medical_history WHERE patient_id = ? ORDER BY history_id DESC LIMIT 1");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $medical = $stmt->get_result()->fetch_assoc();

    // latest dietary_habits
    $stmt = $conn->prepare("SELECT * FROM dietary_habits WHERE patient_id = ? ORDER BY diet_id DESC LIMIT 1");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $dietary = $stmt->get_result()->fetch_assoc();

    // latest vital_signs
    $stmt = $conn->prepare("SELECT * FROM vital_signs WHERE patient_id = ? ORDER BY recorded_at DESC, vital_id DESC LIMIT 1");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $vital = $stmt->get_result()->fetch_assoc();

    echo json_encode([
        "success" => true,
        "patient" => $patient,
        "info" => $other_info,
        "medical_history" => $medical,
        "dietary_habits" => $dietary,
        "vital_signs" => $vital
    ]);
    exit;
}

// --------------------
// Case 3: update (POST JSON body)
// --------------------
if ($input && isset($input['patient_id'])) {
    $pid = intval($input['patient_id']);

    // Helpers
    $esc = function ($k) use ($conn, $input) {
        return isset($input[$k]) ? "'" . $conn->real_escape_string($input[$k]) . "'" : "NULL";
    };
    $escStr = function ($k) use ($conn, $input) {
        return isset($input[$k]) ? $conn->real_escape_string($input[$k]) : "";
    };
    $escInt = function ($k) use ($input) {
        return isset($input[$k]) && $input[$k] !== "" ? intval($input[$k]) : 0;
    };

    // ------------ Patient fields ------------
    $surname = $escStr('surname');
    $firstname = $escStr('firstname');
    $middlename = $escStr('middlename');
    $date_of_birth = isset($input['date_of_birth']) && $input['date_of_birth'] !== "" ? "'" . $conn->real_escape_string($input['date_of_birth']) . "'" : "NULL";
    $place_of_birth = $escStr('place_of_birth');
    $age_val = isset($input['age']) && $input['age'] !== "" ? intval($input['age']) : "NULL";
    $sex = $escStr('sex');
    $address = $escStr('address');
    $occupation = $escStr('occupation');
    $pregnant = isset($input['pregnant']) && in_array($input['pregnant'], ['Yes', 'No']) ? $input['pregnant'] : 'No';

    // ------------ Update patients ------------
    $sql = "UPDATE patients SET
                surname = '{$surname}',
                firstname = '{$firstname}',
                middlename = '{$middlename}',
                date_of_birth = {$date_of_birth},
                place_of_birth = '{$place_of_birth}',
                age = " . ($age_val === "NULL" ? "NULL" : intval($age_val)) . ",
                sex = '{$sex}',
                address = '{$address}',
                pregnant = '{$pregnant}',
                occupation = '{$occupation}',
                if_treatment = 1,             -- always mark as treated
                updated_at = NOW()            -- refresh timestamp
            WHERE patient_id = {$pid}";

    $conn->query($sql);

    // ------------ Upsert patient_other_info ------------
    $nhts_pr = $escInt('nhts_pr');
    $four_ps = $escInt('four_ps');
    $indigenous_people = $escInt('indigenous_people');
    $pwd = $escInt('pwd');
    $philhealth_flag = $escInt('philhealth_flag');
    $philhealth_number = $escStr('philhealth_number');
    $sss_flag = $escInt('sss_flag');
    $sss_number = $escStr('sss_number');
    $gsis_flag = $escInt('gsis_flag');
    $gsis_number = $escStr('gsis_number');

    $r = $conn->query("SELECT patient_id FROM patient_other_info WHERE patient_id = {$pid} LIMIT 1");
    if ($r && $r->num_rows) {
        $sql = "UPDATE patient_other_info SET
                    nhts_pr = {$nhts_pr}, 
                    four_ps = {$four_ps},
                    indigenous_people = {$indigenous_people},
                    pwd = {$pwd},
                    philhealth_flag = {$philhealth_flag},
                    philhealth_number = '{$philhealth_number}',
                    sss_flag = {$sss_flag},
                    sss_number = '{$sss_number}',
                    gsis_flag = {$gsis_flag},
                    gsis_number = '{$gsis_number}'
                WHERE patient_id = {$pid}";
        $conn->query($sql);
    } else {
        $sql = "INSERT INTO patient_other_info
                (patient_id, nhts_pr, four_ps, indigenous_people, pwd, philhealth_flag, philhealth_number, sss_flag, sss_number, gsis_flag, gsis_number)
                VALUES
                ({$pid}, {$nhts_pr}, {$four_ps}, {$indigenous_people}, {$pwd}, {$philhealth_flag}, '{$philhealth_number}', {$sss_flag}, '{$sss_number}', {$gsis_flag}, '{$gsis_number}')";
        $conn->query($sql);
    }

    // ------------ Upsert medical_history ------------
    $allergies_flag = $escInt('allergies_flag');
    $allergies_details = $escStr('allergies_details');
    $hypertension_cva = $escInt('hypertension_cva');
    $diabetes_mellitus = $escInt('diabetes_mellitus');
    $blood_disorders = $escInt('blood_disorders');
    $heart_disease = $escInt('heart_disease');
    $thyroid_disorders = $escInt('thyroid_disorders');
    $hepatitis_flag = $escInt('hepatitis_flag');
    $hepatitis_details = $escStr('hepatitis_details');
    $malignancy_flag = $escInt('malignancy_flag');
    $malignancy_details = $escStr('malignancy_details');
    $prev_hospitalization_flag = $escInt('prev_hospitalization_flag');
    $last_admission_date = isset($input['last_admission_date']) && $input['last_admission_date'] !== "" ? "'" . $conn->real_escape_string($input['last_admission_date']) . "'" : "NULL";
    $admission_cause = $escStr('admission_cause');
    $surgery_details = $escStr('surgery_details');
    $blood_transfusion_flag = $escInt('blood_transfusion_flag');
    $blood_transfusion = $escStr('blood_transfusion_date');
    $tattoo = $escInt('tattoo');
    $other_conditions_flag = $escInt('other_conditions_flag');
    $other_conditions = $escStr('other_conditions');

    $r = $conn->query("SELECT history_id FROM medical_history WHERE patient_id = {$pid} LIMIT 1");
    if ($r && $r->num_rows) {
        $sql = "UPDATE medical_history SET
                    allergies_flag = {$allergies_flag},
                    allergies_details = '{$allergies_details}',
                    hypertension_cva = {$hypertension_cva},
                    diabetes_mellitus = {$diabetes_mellitus},
                    blood_disorders = {$blood_disorders},
                    heart_disease = {$heart_disease},
                    thyroid_disorders = {$thyroid_disorders},
                    hepatitis_flag = {$hepatitis_flag},
                    hepatitis_details = '{$hepatitis_details}',
                    malignancy_flag = {$malignancy_flag},
                    malignancy_details = '{$malignancy_details}',
                    prev_hospitalization_flag = {$prev_hospitalization_flag},
                    last_admission_date = {$last_admission_date},
                    admission_cause = '{$admission_cause}',
                    surgery_details = '{$surgery_details}',
                    blood_transfusion_flag = {$blood_transfusion_flag},
                    blood_transfusion = '{$blood_transfusion}',
                    tattoo = {$tattoo},
                    other_conditions_flag = {$other_conditions_flag},
                    other_conditions = '{$other_conditions}'
                WHERE patient_id = {$pid}";
        $conn->query($sql);
    } else {
        $sql = "INSERT INTO medical_history
                (patient_id, allergies_flag, allergies_details, hypertension_cva, diabetes_mellitus, blood_disorders, heart_disease, thyroid_disorders, hepatitis_flag, hepatitis_details, malignancy_flag, malignancy_details, prev_hospitalization_flag, last_admission_date, admission_cause, surgery_details, blood_transfusion_flag, blood_transfusion, tattoo, other_conditions_flag, other_conditions)
                VALUES
                ({$pid}, {$allergies_flag}, '{$allergies_details}', {$hypertension_cva}, {$diabetes_mellitus}, {$blood_disorders}, {$heart_disease}, {$thyroid_disorders}, {$hepatitis_flag}, '{$hepatitis_details}', {$malignancy_flag}, '{$malignancy_details}', {$prev_hospitalization_flag}, {$last_admission_date}, '{$admission_cause}', '{$surgery_details}', {$blood_transfusion_flag}, '{$blood_transfusion}', {$tattoo}, {$other_conditions_flag}, '{$other_conditions}')";
        $conn->query($sql);
    }

    // ------------ Upsert dietary_habits ------------
    $sugar_flag = $escInt('sugar_flag');
    $sugar_details = $escStr('sugar_details');
    $alcohol_flag = $escInt('alcohol_flag');
    $alcohol_details = $escStr('alcohol_details');
    $tobacco_flag = $escInt('tobacco_flag');
    $tobacco_details = $escStr('tobacco_details');
    $betel_nut_flag = $escInt('betel_nut_flag');
    $betel_nut_details = $escStr('betel_nut_details');

    $r = $conn->query("SELECT diet_id FROM dietary_habits WHERE patient_id = {$pid} LIMIT 1");
    if ($r && $r->num_rows) {
        $sql = "UPDATE dietary_habits SET
                    sugar_flag = {$sugar_flag},
                    sugar_details = '{$sugar_details}',
                    alcohol_flag = {$alcohol_flag},
                    alcohol_details = '{$alcohol_details}',
                    tobacco_flag = {$tobacco_flag},
                    tobacco_details = '{$tobacco_details}',
                    betel_nut_flag = {$betel_nut_flag},
                    betel_nut_details = '{$betel_nut_details}'
                WHERE patient_id = {$pid}";
        $conn->query($sql);
    } else {
        $sql = "INSERT INTO dietary_habits
                (patient_id, sugar_flag, sugar_details, alcohol_flag, alcohol_details, tobacco_flag, tobacco_details, betel_nut_flag, betel_nut_details)
                VALUES
                ({$pid}, {$sugar_flag}, '{$sugar_details}', {$alcohol_flag}, '{$alcohol_details}', {$tobacco_flag}, '{$tobacco_details}', {$betel_nut_flag}, '{$betel_nut_details}')";
        $conn->query($sql);
    }

    // ------------ Upsert vital_signs ------------
    $bp = $escStr('blood_pressure');
    $pr = $escStr('pulse_rate');
    $temp = $escStr('temperature');
    $weight = $escStr('weight');

    if ($bp !== "" || $pr !== "" || $temp !== "" || $weight !== "") {
        $r = $conn->query("SELECT vital_id FROM vital_signs WHERE patient_id = {$pid} ORDER BY recorded_at DESC, vital_id DESC LIMIT 1");
        if ($r && $r->num_rows) {
            $row = $r->fetch_assoc();
            $vid = intval($row['vital_id']);
            $sql = "UPDATE vital_signs SET
                        blood_pressure = '{$bp}',
                        pulse_rate = '{$pr}',
                        temperature = '{$temp}',
                        weight = '{$weight}',
                        recorded_at = NOW()
                    WHERE vital_id = {$vid}";
            $conn->query($sql);
        } else {
            $sql = "INSERT INTO vital_signs (patient_id, blood_pressure, pulse_rate, temperature, weight, recorded_at)
                    VALUES ({$pid}, '{$bp}', '{$pr}', '{$temp}', '{$weight}', NOW())";
            $conn->query($sql);
        }
    }

    echo json_encode(["success" => true, "message" => "Updated"]);
    exit;
}

echo json_encode(["success" => false, "error" => "Invalid request or missing parameters"]);
