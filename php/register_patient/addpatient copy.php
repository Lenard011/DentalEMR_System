<?php
error_reporting(E_ERROR | E_PARSE);
include "../conn.php";
header('Content-Type: application/json');
ob_start();

$response = [
  'status'  => 'error',
  'title'   => '',
  'message' => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

  // ============================
  // 🧍 PATIENT INFO
  // ============================
  $surname      = trim($_POST["surname"]      ?? '');
  $firstname    = trim($_POST["firstname"]    ?? '');
  $middlename   = trim($_POST["middlename"]   ?? '');
  $date_of_birth = $_POST["dob"]             ?? null;
  $placeofbirth = trim($_POST["pob"]         ?? '');
  $age          = isset($_POST["age"])       ? intval($_POST["age"])       : 0;
  $months_old   = isset($_POST["agemonth"])  ? intval($_POST["agemonth"])  : 0;
  $sex          = $_POST["sex"]              ?? null;
  $address      = trim($_POST["address"]     ?? '');
  $occupation   = trim($_POST["occupation"]  ?? '');
  $pregnant     = $_POST["pregnant"]         ?? null;
  $guardian     = trim($_POST["guardian"]    ?? '');

  // ============================
  // AGE VALIDATION
  // ============================
  if ($age < 0 || $months_old < 0 || $months_old > 59) {
    $response['title']   = "Invalid input detected!";
    $response['message'] = "'Age' must be ≥ 0 and 'Months' must be between 0–59.";
    echo json_encode($response);
    exit;
  }

  // ============================
  // DUPLICATE CHECK
  // ============================
  $check = $conn->prepare("SELECT patient_id FROM patients WHERE surname=? AND firstname=? AND date_of_birth=? LIMIT 1");
  $check->bind_param("sss", $surname, $firstname, $date_of_birth);
  $check->execute();
  $check->store_result();

  if ($check->num_rows > 0) {
    $check->close();
    $response['title']   = "Duplicate Entry Detected!";
    $response['message'] = "This patient ($firstname $surname, born on $date_of_birth) already exists.";
    echo json_encode($response);
    exit;
  }
  $check->close();

  // ============================
  // INSERT INTO patients
  // ============================
  $stmt = $conn->prepare("
        INSERT INTO patients (
            surname, firstname, middlename, date_of_birth, place_of_birth,
            age, months_old, sex, address, occupation, pregnant, guardian
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

  $stmt->bind_param(
    "sssssiisssss",
    $surname,
    $firstname,
    $middlename,
    $date_of_birth,
    $placeofbirth,
    $age,
    $months_old,
    $sex,
    $address,
    $occupation,
    $pregnant,
    $guardian
  );

  if (!$stmt->execute()) {
    $response['title']   = "Database Error";
    $response['message'] = "Patient insert failed: " . htmlspecialchars($stmt->error);
    echo json_encode($response);
    exit;
  }

  $patient_id = $stmt->insert_id;
  $stmt->close();

  // ============================
  // OTHER PATIENT INFO
  // ============================
  $flags = [
    'nhts_pr',
    'four_ps',
    'indigenous_people',
    'pwd',
    'philhealth_flag',
    'sss_flag',
    'gsis_flag'
  ];

  foreach ($flags as $flag) {
    $$flag = isset($_POST[$flag]) ? 1 : 0;
  }

  $philno = $_POST['philhealth_number'] ?? null;
  $sssno  = $_POST['sss_number']       ?? null;
  $gsisno = $_POST['gsis_number']      ?? null;

  $stmt = $conn->prepare("
        INSERT INTO patient_other_info (
            patient_id, nhts_pr, four_ps, indigenous_people, pwd,
            philhealth_flag, philhealth_number,
            sss_flag, sss_number,
            gsis_flag, gsis_number
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

  $stmt->bind_param(
    "iiiiisissis",
    $patient_id,
    $nhts_pr,
    $four_ps,
    $indigenous_people,
    $pwd,
    $philhealth_flag,
    $philno,
    $sss_flag,
    $sssno,
    $gsis_flag,
    $gsisno
  );

  $stmt->execute();
  $stmt->close();

  // ============================
  // VITAL SIGNS
  // ============================
  $bp   = $_POST['blood_pressure'] ?? null;
  $pr   = $_POST['pulse_rate']     ?? null;
  $temp = $_POST['temperature']    ?? null;
  $wt   = $_POST['weight']         ?? null;

  $stmt = $conn->prepare("INSERT INTO vital_signs (patient_id, blood_pressure, pulse_rate, temperature, weight) VALUES (?, ?, ?, ?, ?)");
  $stmt->bind_param("isidd", $patient_id, $bp, $pr, $temp, $wt);
  $stmt->execute();
  $stmt->close();

  // ============================
  // MEDICAL HISTORY
  // ============================
  $fields = [
    'allergies_flag',
    'hypertension_cva',
    'diabetes_mellitus',
    'blood_disorders',
    'heart_disease',
    'thyroid_disorders',
    'hepatitis_flag',
    'malignancy_flag',
    'prev_hospitalization_flag',
    'blood_transfusion_flag',
    'tattoo',
    'other_conditions_flag'
  ];

  foreach ($fields as $f) {
    $$f = isset($_POST[$f]) ? 1 : 0;
  }

  $allergies_details    = $_POST['allergies_details']    ?? null;
  $hepatitis_details    = $_POST['hepatitis_details']    ?? null;
  $malignancy_details   = $_POST['malignancy_details']   ?? null;
  $last_admission_date  = $_POST['last_admission_date']  ?? null;
  $admission_cause      = $_POST['admission_cause']      ?? null;
  $surgery_details      = $_POST['surgery_details']      ?? null;
  $blood_transfusion    = $_POST['blood_transfusion']    ?? null;
  $other_conditions     = $_POST['other_conditions']     ?? null;

  $stmt = $conn->prepare("
        INSERT INTO medical_history (
            patient_id, allergies_flag, allergies_details, hypertension_cva,
            diabetes_mellitus, blood_disorders, heart_disease, thyroid_disorders,
            hepatitis_flag, hepatitis_details, malignancy_flag, malignancy_details,
            prev_hospitalization_flag, last_admission_date, admission_cause,
            surgery_details, blood_transfusion_flag, blood_transfusion,
            tattoo, other_conditions_flag, other_conditions
        ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
    ");

  $stmt->bind_param(
    "isiiiiiisisisssisiiss",
    $patient_id,
    $allergies_flag,
    $allergies_details,
    $hypertension_cva,
    $diabetes_mellitus,
    $blood_disorders,
    $heart_disease,
    $thyroid_disorders,
    $hepatitis_flag,
    $hepatitis_details,
    $malignancy_flag,
    $malignancy_details,
    $prev_hospitalization_flag,
    $last_admission_date,
    $admission_cause,
    $surgery_details,
    $blood_transfusion_flag,
    $blood_transfusion,
    $tattoo,
    $other_conditions_flag,
    $other_conditions
  );

  $stmt->execute();
  $stmt->close();

  // ============================
  // DIETARY HABITS
  // ============================
  $habits = ['sugar', 'alcohol', 'tobacco', 'betel_nut'];

  foreach ($habits as $h) {
    ${$h . '_flag'}    = isset($_POST[$h . '_flag']) ? 1 : 0;
    ${$h . '_details'} = $_POST[$h . '_details']      ?? null;
  }

  $stmt = $conn->prepare("
        INSERT INTO dietary_habits (
            patient_id, sugar_flag, sugar_details, alcohol_flag, alcohol_details,
            tobacco_flag, tobacco_details, betel_nut_flag, betel_nut_details
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

  $stmt->bind_param(
    "iisisisis",
    $patient_id,
    $sugar_flag,
    $sugar_details,
    $alcohol_flag,
    $alcohol_details,
    $tobacco_flag,
    $tobacco_details,
    $betel_nut_flag,
    $betel_nut_details
  );

  $stmt->execute();
  $stmt->close();

  // ============================
  // SUCCESS RESPONSE
  // ============================
  $response['status']  = 'success';
  $response['title']   = "Success!";
  $response['message'] = "Patient registration added successfully!";
}

echo json_encode($response);
ob_end_flush();
$conn->close();
