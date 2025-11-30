<?php
session_start();
date_default_timezone_set('Asia/Manila');

// Check if we're in offline mode
$isOfflineMode = isset($_GET['offline']) && $_GET['offline'] === 'true';

// Enhanced session validation with offline support
if ($isOfflineMode) {
  // Offline mode session validation
  $isValidSession = false;

  // Check if we have offline session data
  if (isset($_SESSION['offline_user'])) {
    $loggedUser = $_SESSION['offline_user'];
    $userId = 'offline';
    $isValidSession = true;
  } else {
    // Try to create offline session from localStorage data (via JavaScript)
    echo "<script>
            document.addEventListener('DOMContentLoaded', function() {
                const checkOfflineSession = () => {
                    try {
                        // Check sessionStorage first
                        const sessionData = sessionStorage.getItem('dentalemr_current_user');
                        if (sessionData) {
                            const user = JSON.parse(sessionData);
                            if (user && user.isOffline) {
                                console.log('Valid offline session detected:', user.email);
                                return true;
                            }
                        }
                        
                        // Fallback: check localStorage for offline users
                        const offlineUsers = localStorage.getItem('dentalemr_local_users');
                        if (offlineUsers) {
                            const users = JSON.parse(offlineUsers);
                            if (users && users.length > 0) {
                                console.log('Offline users found in localStorage');
                                return true;
                            }
                        }
                        return false;
                    } catch (error) {
                        console.error('Error checking offline session:', error);
                        return false;
                    }
                };
                
                if (!checkOfflineSession()) {
                    alert('Please log in first for offline access.');
                    window.location.href = '/dentalemr_system/html/login/login.html';
                }
            });
        </script>";

    // Create offline session for this request
    $_SESSION['offline_user'] = [
      'id' => 'offline_user',
      'name' => 'Offline User',
      'email' => 'offline@dentalclinic.com',
      'type' => 'Dentist',
      'isOffline' => true
    ];
    $loggedUser = $_SESSION['offline_user'];
    $userId = 'offline';
    $isValidSession = true;
  }
} else {
  // Online mode session validation
  $isValidSession = false;
  $userId = null;

  if (
    isset($_SESSION['active_sessions']) &&
    is_array($_SESSION['active_sessions']) &&
    !empty($_SESSION['active_sessions'])
  ) {
    $loggedUser = end($_SESSION['active_sessions']);
    $userId = intval($loggedUser['id']);

    if ($userId && !empty($loggedUser['type'])) {
      $isValidSession = true;
      // Update last activity
      $_SESSION['active_sessions'][$userId]['last_activity'] = time();
    }
  }

  if (!$isValidSession) {
    echo "<script>
            if (!navigator.onLine) {
                // Redirect to same page in offline mode
                window.location.href = '/dentalemr_system/html/addpatient.php?offline=true';
            } else {
                alert('Please log in first.');
                window.location.href = '/dentalemr_system/html/login/login.html';
            }
        </script>";
    exit;
  }
}

// Set header for JSON responses
header('Content-Type: application/json');

/*
|--------------------------------------------------------------------------
| DATABASE CONNECTION (Online mode only)
|--------------------------------------------------------------------------
*/
$conn = null;
$dbError = false;
$dbErrorMessage = '';

if (!$isOfflineMode) {
  include "../conn.php";

  // Check if connection was successful and $conn is a valid mysqli object
  if (!$conn || !($conn instanceof mysqli)) {
    $dbError = true;
    $dbErrorMessage = "Database connection failed to initialize";
  } elseif ($conn->connect_error) {
    $dbError = true;
    $dbErrorMessage = $conn->connect_error;
  }

  if ($dbError) {
    if (!isset($_GET['offline'])) {
      echo json_encode([
        "status" => "error",
        "title" => "Database Error",
        "message" => "Database connection failed. Switching to offline mode..."
      ]);

      echo "<script>
                if (!navigator.onLine) {
                    window.location.href = '/dentalemr_system/html/addpatient.php?offline=true';
                } else {
                    alert('Database connection failed. Please try again.');
                }
            </script>";
      exit;
    }
  }
}

/*
|--------------------------------------------------------------------------
| HISTORY LOG FUNCTION (Online mode only)
|--------------------------------------------------------------------------
*/
function addHistoryLog($conn, $tableName, $recordId, $action, $changedByType, $changedById, $oldValues = null, $newValues = null, $description = null)
{
  if (!$conn || !($conn instanceof mysqli)) return false;

  $sql = "INSERT INTO history_logs 
            (table_name, record_id, action, changed_by_type, changed_by_id, old_values, new_values, description, ip_address) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

  $stmt = $conn->prepare($sql);

  // Check if prepare was successful
  if (!$stmt) {
    error_log("Failed to prepare history log statement: " . $conn->error);
    return false;
  }

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

  $result = $stmt->execute();

  if (!$result) {
    error_log("Failed to execute history log: " . $stmt->error);
  }

  $stmt->close();
  return $result;
}

/*
|--------------------------------------------------------------------------
| DEFAULT RESPONSE STRUCTURE
|--------------------------------------------------------------------------
*/
$response = [
  'status'  => 'error',
  'title'   => '',
  'message' => '',
  'isOffline' => $isOfflineMode
];

/*
|--------------------------------------------------------------------------
| MAIN POST HANDLER
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

  // Check if this is an offline sync request
  $isOfflineSync = isset($_POST['offline_sync']) && $_POST['offline_sync'] === 'true';

  if ($isOfflineMode && !$isOfflineSync) {
    /*
        |--------------------------------------------------------------------------
        | OFFLINE MODE - STORE DATA LOCALLY
        |--------------------------------------------------------------------------
        */
    $patientData = $_POST;
    $patientData['created_at'] = date('Y-m-d H:i:s');
    $patientData['offline_id'] = 'offline_' . time() . '_' . rand(1000, 9999);

    // Store in localStorage via JavaScript
    echo "<script>
            document.addEventListener('DOMContentLoaded', function() {
                try {
                    // Get existing offline patients
                    let offlinePatients = JSON.parse(localStorage.getItem('dentalemr_offline_patients') || '[]');
                    
                    // Add new patient
                    offlinePatients.push(" . json_encode($patientData) . ");
                    
                    // Save back to localStorage
                    localStorage.setItem('dentalemr_offline_patients', JSON.stringify(offlinePatients));
                    
                    // Show success message
                    alert('Patient saved locally! Data will be synced when online.');
                    window.location.href = '/dentalemr_system/html/index.php?offline=true';
                    
                } catch (error) {
                    console.error('Error saving offline patient:', error);
                    alert('Error saving patient locally. Please try again.');
                }
            });
        </script>";

    $response['status'] = 'success';
    $response['title'] = "Saved Locally!";
    $response['message'] = "Patient data saved locally and will be synced when online.";

    echo json_encode($response);
    exit;
  } else {
    /*
        |--------------------------------------------------------------------------
        | ONLINE MODE - SAVE TO DATABASE
        |--------------------------------------------------------------------------
        */

    // Check database connection more safely
    if ($dbError || !$conn || !($conn instanceof mysqli)) {
      $response['title'] = "Database Error";
      $response['message'] = "Cannot connect to database. Please try again or switch to offline mode.";
      echo json_encode($response);
      exit;
    }

    /*
        |--------------------------------------------------------------------------
        | PATIENT INFO VALIDATION
        |--------------------------------------------------------------------------
        */
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
      $response['title'] = "Invalid Age Input";
      $response['message'] = "Age must be ≥ 0 and months must be between 0–59.";
      echo json_encode($response);
      exit;
    }

    /*
        |--------------------------------------------------------------------------
        | DUPLICATE CHECK (Online only)
        |--------------------------------------------------------------------------
        */
    $check = $conn->prepare("SELECT patient_id FROM patients WHERE surname=? AND firstname=? AND date_of_birth=? LIMIT 1");
    if (!$check) {
      $response['title'] = "Database Error";
      $response['message'] = "Failed to prepare duplicate check statement: " . $conn->error;
      echo json_encode($response);
      exit;
    }

    $check->bind_param("sss", $surname, $firstname, $date_of_birth);
    $check->execute();
    $check->store_result();

    if ($check->num_rows > 0) {
      $response['title'] = "Duplicate Entry";
      $response['message'] = "This patient already exists.";
      $check->close();
      echo json_encode($response);
      exit;
    }
    $check->close();

    /*
|--------------------------------------------------------------------------
| INSERT INTO patients
|--------------------------------------------------------------------------
*/
    $stmt = $conn->prepare("
    INSERT INTO patients (
        surname, firstname, middlename, date_of_birth, place_of_birth,
        age, months_old, sex, address, occupation, pregnant, guardian, if_treatment
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");

    if (!$stmt) {
      $response['title'] = "Database Error";
      $response['message'] = "Failed to prepare patient insert statement: " . $conn->error;
      echo json_encode($response);
      exit;
    }

    // Add if_treatment = 0 for new patients
    $if_treatment = 0;

    $stmt->bind_param(
      "sssssiisssssi",
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
      $guardian,
      $if_treatment
    );

    if (!$stmt->execute()) {
      $response['title'] = "Database Error";
      $response['message'] = $stmt->error;
      $stmt->close();
      echo json_encode($response);
      exit;
    }

    $patient_id = $stmt->insert_id;
    $stmt->close();

    // PATIENT OTHER INFO
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

    if ($stmt) {
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
    }

    // VITAL SIGNS
    $bp   = $_POST['blood_pressure'] ?? null;
    $pr   = $_POST['pulse_rate']     ?? null;
    $temp = $_POST['temperature']    ?? null;
    $wt   = $_POST['weight']         ?? null;

    $stmt = $conn->prepare("
            INSERT INTO vital_signs (patient_id, blood_pressure, pulse_rate, temperature, weight) 
            VALUES (?, ?, ?, ?, ?)
        ");
    if ($stmt) {
      $stmt->bind_param("isidd", $patient_id, $bp, $pr, $temp, $wt);
      $stmt->execute();
      $stmt->close();
    }

    // MEDICAL HISTORY
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

    if ($stmt) {
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
    }

    // DIETARY HABITS
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

    if ($stmt) {
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
    }

    // HISTORY LOG (Online only)
    $tablesAffected = [
      "patients",
      "patient_other_info",
      "vital_signs",
      "medical_history",
      "dietary_habits"
    ];

    addHistoryLog(
      $conn,
      implode(", ", $tablesAffected),
      $patient_id,
      "INSERT",
      $loggedUser['type'],
      $loggedUser['id'],
      null,
      $_POST,
      "Complete patient registration record added"
    );

    /*
        |--------------------------------------------------------------------------
        | SUCCESS RESPONSE
        |--------------------------------------------------------------------------
        */
    $response['status']  = 'success';
    $response['title']   = "Success!";
    $response['message'] = "Patient registration added successfully!";
  }
} else {
  // GET request - just return info
  $response['status'] = 'info';
  $response['title'] = 'Form Loaded';
  $response['message'] = 'Add Patient form loaded successfully';
  $response['isOffline'] = $isOfflineMode;
}

echo json_encode($response);

// Close connection only if it exists and is valid
if ($conn && $conn instanceof mysqli) {
  $conn->close();
}
