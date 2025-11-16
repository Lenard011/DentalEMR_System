<?php
session_start();
header('Content-Type: application/json');
error_reporting(E_ERROR | E_PARSE);
include "../conn.php";

// ------------------------
// REQUIRE uid & session
// ------------------------
if (!isset($_GET['uid']) || !isset($_SESSION['active_sessions'][$_GET['uid']])) {
  echo json_encode([
    "status" => "error",
    "title" => "Invalid Session",
    "message" => "Please log in first."
  ]);
  exit;
}

$userId = intval($_GET['uid']);
$loggedUser = $_SESSION['active_sessions'][$userId];

// ------------------------
// HISTORY LOG FUNCTION
// ------------------------
function addHistoryLog($conn, $tableName, $recordId, $action, $changedByType, $changedById, $oldValues = null, $newValues = null, $description = null)
{
  $sql = "INSERT INTO history_logs 
            (table_name, record_id, action, changed_by_type, changed_by_id, old_values, new_values, description, ip_address) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

  $stmt = $conn->prepare($sql);

  $oldJSON = $oldValues ? json_encode($oldValues) : null;
  $newJSON = $newValues ? json_encode($newValues) : null;
  $ip = $_SERVER['REMOTE_ADDR'] ?? null;

  $stmt->bind_param(
    "sississss",
    $tableName,
    $recordId,
    $action,
    $changedByType,
    $changedById,
    $oldJSON,
    $newJSON,
    $description,
    $ip
  );

  return $stmt->execute();
}

// ------------------------
// DEFAULT RESPONSE
// ------------------------
$response = [
  'status'  => 'error',
  'title'   => '',
  'message' => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

  // ---------------------------------------
  // PATIENT INFO
  // ---------------------------------------
  $surname      = trim($_POST["surname"]      ?? '');
  $firstname    = trim($_POST["firstname"]    ?? '');
  $middlename   = trim($_POST["middlename"]   ?? '');
  $date_of_birth = $_POST["dob"]              ?? null;
  $placeofbirth = trim($_POST["pob"]          ?? '');
  $age          = intval($_POST["age"]        ?? 0);
  $months_old   = intval($_POST["agemonth"]   ?? 0);
  $sex          = $_POST["sex"]               ?? null;
  $address      = trim($_POST["address"]      ?? '');
  $occupation   = trim($_POST["occupation"]   ?? '');
  $pregnant     = $_POST["pregnant"]          ?? null;
  $guardian     = trim($_POST["guardian"]     ?? '');

  // AGE VALIDATION
  if ($age < 0 || $months_old < 0 || $months_old > 59) {
    $response['title']   = "Invalid Age Input";
    $response['message'] = "Age must be ≥ 0 and months must be 0–59.";
    echo json_encode($response);
    exit;
  }

  // DUPLICATE CHECK
  $check = $conn->prepare("SELECT patient_id FROM patients WHERE surname=? AND firstname=? AND date_of_birth=? LIMIT 1");
  $check->bind_param("sss", $surname, $firstname, $date_of_birth);
  $check->execute();
  $check->store_result();

  if ($check->num_rows > 0) {
    $response['title']   = "Duplicate Entry";
    $response['message'] = "This patient already exists.";
    echo json_encode($response);
    exit;
  }
  $check->close();

  // ---------------------------------------
  // INSERT INTO patients
  // ---------------------------------------
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
    $response['title'] = "Database Error";
    $response['message'] = $stmt->error;
    echo json_encode($response);
    exit;
  }

  $patient_id = $stmt->insert_id;
  $stmt->close();

  // HISTORY LOG — PATIENTS
  addHistoryLog(
    $conn,
    "patients",
    $patient_id,
    "INSERT",
    $loggedUser['type'],
    $loggedUser['id'],
    null,
    [
      "surname" => $surname,
      "firstname" => $firstname,
      "middlename" => $middlename,
      "date_of_birth" => $date_of_birth,
      "place_of_birth" => $placeofbirth,
      "age" => $age,
      "months_old" => $months_old,
      "sex" => $sex,
      "address" => $address,
      "occupation" => $occupation,
      "pregnant" => $pregnant,
      "guardian" => $guardian
    ],
    "New patient registration"
  );


  // ---------------------------------------
  // PATIENT OTHER INFO
  // ---------------------------------------
  $flags = [
    'nhts_pr',
    'four_ps',
    'indigenous_people',
    'pwd',
    'philhealth_flag',
    'sss_flag',
    'gsis_flag'
  ];
  foreach ($flags as $flag) $$flag = isset($_POST[$flag]) ? 1 : 0;

  $philno = $_POST['philhealth_number'] ?? null;
  $sssno  = $_POST['sss_number']        ?? null;
  $gsisno = $_POST['gsis_number']       ?? null;

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

  // HISTORY LOG — other info
  addHistoryLog(
    $conn,
    "patient_other_info",
    $patient_id,
    "INSERT",
    $loggedUser['type'],
    $loggedUser['id'],
    null,
    $_POST,
    "Added patient other info"
  );


  // ---------------------------------------
  // VITAL SIGNS
  // ---------------------------------------
  $bp   = $_POST['blood_pressure'] ?? null;
  $pr   = $_POST['pulse_rate']     ?? null;
  $temp = $_POST['temperature']    ?? null;
  $wt   = $_POST['weight']         ?? null;

  $stmt = $conn->prepare("
      INSERT INTO vital_signs (patient_id, blood_pressure, pulse_rate, temperature, weight) 
      VALUES (?, ?, ?, ?, ?)
  ");
  $stmt->bind_param("isidd", $patient_id, $bp, $pr, $temp, $wt);
  $stmt->execute();
  $stmt->close();

  addHistoryLog(
    $conn,
    "vital_signs",
    $patient_id,
    "INSERT",
    $loggedUser['type'],
    $loggedUser['id'],
    null,
    $_POST,
    "Added vital signs"
  );


  // ---------------------------------------
  // MEDICAL HISTORY
  // ---------------------------------------
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

  foreach ($fields as $f) $$f = isset($_POST[$f]) ? 1 : 0;

  $details = [
    'allergies_details',
    'hepatitis_details',
    'malignancy_details',
    'last_admission_date',
    'admission_cause',
    'surgery_details',
    'blood_transfusion',
    'other_conditions'
  ];

  foreach ($details as $d) $$d = $_POST[$d] ?? null;

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

  addHistoryLog(
    $conn,
    "medical_history",
    $patient_id,
    "INSERT",
    $loggedUser['type'],
    $loggedUser['id'],
    null,
    $_POST,
    "Added medical history"
  );


  // ---------------------------------------
  // DIETARY HABITS
  // ---------------------------------------
  $habits = ['sugar', 'alcohol', 'tobacco', 'betel_nut'];
  foreach ($habits as $h) {
    ${$h . '_flag'} = isset($_POST[$h . '_flag']) ? 1 : 0;
    ${$h . '_details'} = $_POST[$h . '_details'] ?? null;
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

  addHistoryLog(
    $conn,
    "dietary_habits",
    $patient_id,
    "INSERT",
    $loggedUser['type'],
    $loggedUser['id'],
    null,
    $_POST,
    "Added dietary habits"
  );


  // ---------------------------------------
  // SUCCESS
  // ---------------------------------------
  $response['status']  = 'success';
  $response['title']   = "Success!";
  $response['message'] = "Patient registration added successfully!";
}

echo json_encode($response);
$conn->close();
