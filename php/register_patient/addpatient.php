<?php
include "../conn.php";

if (isset($_POST["patient"])) {
  // ============================
  // 🧍 PATIENT INFO
  // ============================
  $surname       = trim($_POST["surname"] ?? '');
  $firstname     = trim($_POST["firstname"] ?? '');
  $middlename    = trim($_POST["middlename"] ?? '');
  $date_of_birth = $_POST["dob"] ?? null;
  $placeofbirth  = trim($_POST["pob"] ?? '');
  $age           = isset($_POST["age"]) ? intval($_POST["age"]) : 0;
  $months_old    = isset($_POST["agemonth"]) ? intval($_POST["agemonth"]) : 0;
  $sex           = $_POST["sex"] ?? null;
  $address       = trim($_POST["address"] ?? '');
  $occupation    = trim($_POST["occupation"] ?? '');
  $pregnant      = $_POST["pregnant"] ?? null;
  $guardian      = trim($_POST["guardian"] ?? '');

  // ============================
  //  AGE VALIDATION (0–59 months)
  // ============================
  if ($age < 0 || $months_old < 0 || $months_old > 59) {
    echoPopup(
      "Invalid input detected!",
      "'Age' must be ≥ 0 and 'Months' must be between 0–59.",
      "error"
    );
    exit;
  }

  // ============================
  // 🔍 DUPLICATE CHECK
  // ============================
  $check = $conn->prepare("SELECT patient_id FROM patients WHERE surname=? AND firstname=? AND date_of_birth=? LIMIT 1");
  $check->bind_param("sss", $surname, $firstname, $date_of_birth);
  $check->execute();
  $check->store_result();

  if ($check->num_rows > 0) {
    $check->close();
    echoPopup(
      "Duplicate Entry Detected!",
      "This patient (<b>$firstname $surname</b>, born on <b>$date_of_birth</b>) already exists in the system.",
      "error"
    );
    $conn->close();
    exit;
  }
  $check->close();

  // ============================
  // 🧾 INSERT INTO patients
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
    echoPopup("Database Error", "Patient insert failed: " . htmlspecialchars($stmt->error), "error");
    exit;
  }

  $patient_id = $stmt->insert_id;
  $stmt->close();

  // ============================
  //  OTHER PATIENT INFO
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
  $sssno  = $_POST['sss_number'] ?? null;
  $gsisno = $_POST['gsis_number'] ?? null;

  $stmt = $conn->prepare("
    INSERT INTO patient_other_info (
      patient_id, nhts_pr, four_ps, indigenous_people, pwd,
      philhealth_flag, philhealth_number, sss_flag, sss_number, gsis_flag, gsis_number
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
  $pr   = $_POST['pulse_rate'] ?? null;
  $temp = $_POST['temperature'] ?? null;
  $wt   = $_POST['weight'] ?? null;

  $stmt = $conn->prepare("
    INSERT INTO vital_signs (patient_id, blood_pressure, pulse_rate, temperature, weight)
    VALUES (?, ?, ?, ?, ?)
  ");
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

  $allergies_details   = $_POST['allergies_details'] ?? null;
  $hepatitis_details   = $_POST['hepatitis_details'] ?? null;
  $malignancy_details  = $_POST['malignancy_details'] ?? null;
  $last_admission_date = $_POST['last_admission_date'] ?? null;
  $admission_cause     = $_POST['admission_cause'] ?? null;
  $surgery_details     = $_POST['surgery_details'] ?? null;
  $blood_transfusion   = $_POST['blood_transfusion'] ?? null;
  $other_conditions    = $_POST['other_conditions'] ?? null;

  $stmt = $conn->prepare("
    INSERT INTO medical_history (
      patient_id, allergies_flag, allergies_details, hypertension_cva, diabetes_mellitus,
      blood_disorders, heart_disease, thyroid_disorders, hepatitis_flag, hepatitis_details,
      malignancy_flag, malignancy_details, prev_hospitalization_flag, last_admission_date,
      admission_cause, surgery_details, blood_transfusion_flag, blood_transfusion, tattoo,
      other_conditions_flag, other_conditions
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

  // ============================
  // SUCCESS POPUP
  // ============================
  echoPopup("Success!", "Patient registration added successfully!", "success");
}

$conn->close();


// ============================
// Popup Function (unchanged)
// ============================
function echoPopup($title, $message, $type = "info")
{
  $color = $type === "error" ? "red" : ($type === "success" ? "green" : "blue");
  echo "
  <div id='popup'>
    <div class='popup-content'>
      <p style='color:$color; font-weight:bold;'>$title</p>
      <p>$message</p>
      <button onclick=\"window.location.href='/dentalemr_system/html/addpatient.php'\">OK</button>
    </div>
  </div>
  <style>
    #popup {
      position: fixed;
      top: 0; left: 0;
      width: 100%; height: 100%;
      background: rgba(0,0,0,0.2);
      backdrop-filter: blur(10px);
      display: flex; justify-content: center; align-items: center;
      z-index: 9999;
    }
    .popup-content {
      background: #fff;
      padding: 20px 30px;
      border-radius: 12px;
      text-align: center;
      box-shadow: 0 5px 15px rgba(0,0,0,0.3);
      font-family: Arial, sans-serif;
      animation: fadeIn 0.3s ease-in-out;
    }
    .popup-content p { font-size: 14px; margin-bottom: 10px; }
    .popup-content button {
      padding: 8px 16px;
      background: $color;
      border: none;
      color: white;
      border-radius: 6px;
      cursor: pointer;
      font-size: 12px;
    }
    .popup-content button:hover { opacity: 0.9; }
    @keyframes fadeIn {
      from { opacity: 0; transform: scale(0.9); }
      to { opacity: 1; transform: scale(1); }
    }
  </style>
  ";
}
