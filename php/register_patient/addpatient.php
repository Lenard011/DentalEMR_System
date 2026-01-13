<?php
session_start();
date_default_timezone_set('Asia/Manila');

// Set header for JSON responses
header('Content-Type: application/json');

// DEBUG: Log session info
error_log("=== ADD PATIENT DEBUG ===");
error_log("Session ID: " . session_id());
error_log("GET uid: " . ($_GET['uid'] ?? 'not set'));
error_log("Full GET: " . print_r($_GET, true));
if (isset($_SESSION['active_sessions'])) {
  error_log("Active sessions count: " . count($_SESSION['active_sessions']));
  error_log("All active sessions: " . print_r($_SESSION['active_sessions'], true));
} else {
  error_log("No active sessions in session");
}
error_log("Full SESSION: " . print_r($_SESSION, true));

/*
|--------------------------------------------------------------------------
| DATABASE CONNECTION
|--------------------------------------------------------------------------
*/
function connectToDatabase()
{
  $host = "localhost";
  $dbUser = "u401132124_dentalclinic";
  $dbPass = "Mho_DentalClinic1st";
  $dbName = "u401132124_mho_dentalemr";

  $conn = new mysqli($host, $dbUser, $dbPass, $dbName);

  if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error);
    return false;
  }

  $conn->set_charset("utf8mb4");
  return $conn;
}

/*
|--------------------------------------------------------------------------
| SIMPLE LOGGING FUNCTIONS
|--------------------------------------------------------------------------
*/
function logActivity($conn, $userId, $userType, $action, $description = null, $tableName = null, $recordId = null)
{
  // Skip if no valid database connection
  if (!$conn || !($conn instanceof mysqli)) {
    error_log("Cannot log activity: Invalid database connection");
    return false;
  }

  // Skip for offline users
  if ($userId === 'offline' || $userType === 'Offline' || $userId === 'offline_user') {
    return true;
  }

  try {
    // DEBUG: What type are we logging?
    error_log("LOG ACTIVITY DEBUG: UserID: $userId, UserType: $userType, Action: $action");

    // Force lowercase for logs to match your log table
    $logUserType = strtolower(trim($userType));
    error_log("LOG ACTIVITY: Using type: $logUserType");

    $sql = "INSERT INTO activity_logs 
            (user_id, user_type, action, description, table_name, record_id, ip_address, user_agent, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
      error_log("Failed to prepare activity log statement: " . $conn->error);
      return false;
    }

    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';

    // Convert null values for binding
    $desc = $description ?: null;
    $table = $tableName ?: null;
    $record = $recordId ?: null;

    // Ensure userId is integer
    $userId = intval($userId);

    $stmt->bind_param(
      "issssiss",
      $userId,
      $logUserType,
      $action,
      $desc,
      $table,
      $record,
      $ip,
      $userAgent
    );

    $result = $stmt->execute();

    if (!$result) {
      error_log("Failed to execute activity log: " . $stmt->error);
      $stmt->close();
      return false;
    }

    $logId = $stmt->insert_id;
    $stmt->close();

    error_log("SUCCESS: Activity logged - ID: $logId, User: $userId ($logUserType)");
    return true;
  } catch (Exception $e) {
    error_log("Exception in logActivity: " . $e->getMessage());
    return false;
  }
}

function logHistory($conn, $tableName, $recordId, $action, $userType, $userId, $oldValues = null, $newValues = null, $description = null)
{
  // Skip if no valid database connection
  if (!$conn || !($conn instanceof mysqli)) {
    error_log("Cannot log history: Invalid database connection");
    return false;
  }

  // Skip for offline users
  if ($userId === 'offline' || $userType === 'Offline' || $userId === 'offline_user') {
    return true;
  }

  try {
    // DEBUG: What type are we logging?
    error_log("LOG HISTORY DEBUG: UserID: $userId, UserType: $userType, Action: $action");

    // Force lowercase for logs to match your log table
    $logUserType = strtolower(trim($userType));
    error_log("LOG HISTORY: Using type: $logUserType");

    $sql = "INSERT INTO history_logs 
            (table_name, record_id, action, changed_by_type, changed_by_id, 
             old_values, new_values, description, ip_address, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
      error_log("Failed to prepare history log statement: " . $conn->error);
      return false;
    }

    $oldJSON = ($oldValues && !empty($oldValues)) ? json_encode($oldValues, JSON_UNESCAPED_UNICODE) : null;
    $newJSON = ($newValues && !empty($newValues)) ? json_encode($newValues, JSON_UNESCAPED_UNICODE) : null;
    $desc = $description ?: null;
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

    // Ensure userId is integer
    $userId = intval($userId);

    $stmt->bind_param(
      "sississss",
      $tableName,
      $recordId,
      $action,
      $logUserType,
      $userId,
      $oldJSON,
      $newJSON,
      $desc,
      $ip
    );

    $result = $stmt->execute();

    if (!$result) {
      error_log("Failed to execute history log: " . $stmt->error);
      $stmt->close();
      return false;
    }

    $logId = $stmt->insert_id;
    $stmt->close();

    error_log("SUCCESS: History logged - ID: $logId, User: $userId ($logUserType)");
    return true;
  } catch (Exception $e) {
    error_log("Exception in logHistory: " . $e->getMessage());
    return false;
  }
}

/*
|--------------------------------------------------------------------------
| SESSION VALIDATION WITH DEBUG
|--------------------------------------------------------------------------
*/
function validateSession()
{
  // Check for offline mode
  if (isset($_GET['offline']) && $_GET['offline'] === 'true') {
    error_log("DEBUG: Offline mode detected");
    if (isset($_SESSION['offline_user'])) {
      error_log("DEBUG: Found existing offline user: " . print_r($_SESSION['offline_user'], true));
      return $_SESSION['offline_user'];
    } else {
      // For offline mode, we need to determine the user type
      // Check if this is coming from staff or dentist interface
      $userType = 'Dentist'; // Default

      // Check if there's a referrer or check the URL path
      if (isset($_SERVER['HTTP_REFERER'])) {
        error_log("DEBUG: HTTP_REFERER: " . $_SERVER['HTTP_REFERER']);
        if (
          strpos($_SERVER['HTTP_REFERER'], '/a_staff/') !== false ||
          strpos($_SERVER['HTTP_REFERER'], 'staff_') !== false
        ) {
          $userType = 'Staff';
          error_log("DEBUG: Detected staff interface from referrer");
        }
      }

      // Also check POST data for clues
      if (isset($_POST['user_type'])) {
        $userType = $_POST['user_type'];
        error_log("DEBUG: Got user_type from POST: $userType");
      }

      $_SESSION['offline_user'] = [
        'id' => 'offline_user',
        'name' => 'Offline User',
        'email' => 'offline@dentalclinic.com',
        'type' => $userType,
        'isOffline' => true
      ];

      error_log("DEBUG: Created offline user with type: $userType");
      return $_SESSION['offline_user'];
    }
  }

  // Online mode - check for active sessions
  if (isset($_SESSION['active_sessions']) && is_array($_SESSION['active_sessions'])) {
    error_log("DEBUG: Active sessions exist, count: " . count($_SESSION['active_sessions']));

    // Try to get user from GET parameter
    if (isset($_GET['uid'])) {
      $uid = $_GET['uid'];
      error_log("DEBUG: Looking for uid $uid in active_sessions");

      if (isset($_SESSION['active_sessions'][$uid])) {
        $user = $_SESSION['active_sessions'][$uid];
        error_log("DEBUG: Found user in session - ID: " . ($user['id'] ?? 'none') . ", Type: " . ($user['type'] ?? 'none'));
        error_log("DEBUG: Full user data: " . print_r($user, true));

        // Update last activity
        $_SESSION['active_sessions'][$uid]['last_activity'] = time();
        return $user;
      } else {
        error_log("DEBUG: uid $uid NOT FOUND in active_sessions");
        // List all available uids
        $availableUids = array_keys($_SESSION['active_sessions']);
        error_log("DEBUG: Available uids: " . implode(', ', $availableUids));
      }
    } else {
      error_log("DEBUG: No uid parameter in GET");
    }

    // Get the most recent session as fallback
    $sessions = $_SESSION['active_sessions'];
    if (!empty($sessions)) {
      $user = end($sessions);
      if (isset($user['id']) && isset($user['type'])) {
        error_log("DEBUG: Using most recent session - ID: {$user['id']}, Type: {$user['type']}");
        return $user;
      }
    }
  } else {
    error_log("DEBUG: No active_sessions in session");
  }

  // No valid session found
  error_log("DEBUG: No valid session found");
  return null;
}
// Get current user
$currentUser = validateSession();

// Check if user is valid
if (!$currentUser) {
  error_log("DEBUG: Invalid session, redirecting to login");
  echo json_encode([
    'status' => 'error',
    'title' => 'Session Expired',
    'message' => 'Please log in again.',
    'redirect' => '/DentalEMR_System/html/login/login.html'
  ]);
  exit;
}
// After getting current user, add this fix:
if ($currentUser['type'] === 'Unknown' || $currentUser['type'] === 'Dentist') {
  // Try to detect if this is actually a staff user
  // Check POST data first
  if (isset($_POST['user_type']) && $_POST['user_type'] === 'Staff') {
    $currentUser['type'] = 'Staff';
    error_log("DEBUG: Overriding user type to 'Staff' based on POST data");
  }
  // Check referrer
  elseif (
    isset($_SERVER['HTTP_REFERER']) &&
    (strpos($_SERVER['HTTP_REFERER'], '/a_staff/') !== false ||
      strpos($_SERVER['HTTP_REFERER'], 'staff_') !== false)
  ) {
    $currentUser['type'] = 'Staff';
    error_log("DEBUG: Overriding user type to 'Staff' based on referrer: " . $_SERVER['HTTP_REFERER']);
  }
}
// ADD THIS DEBUG SECTION
error_log("=== FINAL USER DATA CHECK ===");
error_log("User ID: " . ($currentUser['id'] ?? 'null'));
error_log("User Type: " . ($currentUser['type'] ?? 'null'));
error_log("Full user array: " . print_r($currentUser, true));

// Check if this is from staff page - look for clues
if (isset($_SERVER['HTTP_REFERER'])) {
  error_log("Referrer URL: " . $_SERVER['HTTP_REFERER']);
  if (strpos($_SERVER['HTTP_REFERER'], 'a_staff') !== false) {
    error_log("WARNING: This appears to be from staff page but user type is: " . ($currentUser['type'] ?? 'null'));
  }
}

// Check if offline sync
if (isset($_POST['offline_sync']) && $_POST['offline_sync'] === 'true') {
  error_log("DEBUG: This is an offline sync request");
  if (isset($_POST['user_type'])) {
    error_log("DEBUG: User type from POST: " . $_POST['user_type']);
  }
}


// Check if offline mode
$isOfflineMode = isset($currentUser['isOffline']) && $currentUser['isOffline'];

// DEBUG: Log the current user
error_log("DEBUG CURRENT USER: " . print_r($currentUser, true));
error_log("User ID: " . ($currentUser['id'] ?? 'none'));
error_log("User Type: " . ($currentUser['type'] ?? 'none'));
error_log("User Name: " . ($currentUser['name'] ?? 'none'));

/*
|--------------------------------------------------------------------------
| MAIN LOGIC
|--------------------------------------------------------------------------
*/
$response = [
  'status'  => 'error',
  'title'   => '',
  'message' => '',
  'isOffline' => $isOfflineMode,
  'debug_user_type' => $currentUser['type'] ?? 'unknown' // Added for debugging
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Check if this is an offline sync request
  $isOfflineSync = isset($_POST['offline_sync']) && $_POST['offline_sync'] === 'true';

  if ($isOfflineMode && !$isOfflineSync) {
    $response['status'] = 'success';
    $response['title'] = "Offline Mode";
    $response['message'] = "Patient data should be saved locally by the browser.";

    echo json_encode($response);
    exit;
  } else {
    // Try to connect to database
    $conn = connectToDatabase();

    if (!$conn) {
      $response['title'] = "Database Error";
      $response['message'] = "Cannot connect to database. Please try again or use offline mode.";
      $response['suggest_offline'] = true;
      echo json_encode($response);
      exit;
    }

    // Begin transaction for atomic operations
    $conn->begin_transaction();

    try {
      // Validate required fields
      $requiredFields = ['surname', 'firstname', 'dob', 'sex', 'address', 'occupation', 'guardian'];
      $missingFields = [];

      foreach ($requiredFields as $field) {
        if (empty($_POST[$field] ?? '')) {
          $missingFields[] = $field;
        }
      }

      if (!empty($missingFields)) {
        throw new Exception("Please fill in all required fields: " . implode(', ', $missingFields));
      }

      // Sanitize inputs
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
        throw new Exception("Age must be ≥ 0 and months must be between 0–59.");
      }

      // DUPLICATE CHECK
      $check = $conn->prepare("SELECT patient_id FROM patients WHERE surname=? AND firstname=? AND date_of_birth=? LIMIT 1");
      if (!$check) {
        throw new Exception("Database error: " . $conn->error);
      }

      $check->bind_param("sss", $surname, $firstname, $date_of_birth);
      $check->execute();
      $check->store_result();

      if ($check->num_rows > 0) {
        $check->close();
        throw new Exception("This patient already exists.");
      }
      $check->close();

      // IMPORTANT: Validate and normalize user type
      $addedByType = $currentUser['type'] ?? 'Unknown';
      $addedById = ($currentUser['id'] !== 'offline' && $currentUser['id'] !== 'offline_user') ? intval($currentUser['id']) : 0;
      $addedByName = $currentUser['name'] ?? 'Unknown';

      // Normalize user type to match your database
      if (strtolower($addedByType) === 'staff') {
        $addedByType = 'Staff';
      } elseif (strtolower($addedByType) === 'dentist') {
        $addedByType = 'Dentist';
      } elseif (strtolower($addedByType) === 'admin') {
        $addedByType = 'Admin';
      }

      // CRITICAL DEBUG: What user info are we using?
      error_log("=== CRITICAL DEBUG ===");
      error_log("Current User Array: " . print_r($currentUser, true));
      error_log("Adding patient with: ID=$addedById, TYPE='$addedByType', NAME='$addedByName'");
      error_log("Patient: $surname, $firstname $middlename");

      // INSERT INTO patients
      $stmt = $conn->prepare("
                INSERT INTO patients (
                    surname, firstname, middlename, date_of_birth, place_of_birth,
                    age, months_old, sex, address, occupation, pregnant, guardian, if_treatment,
                    added_by_type, added_by_id, added_by_name
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

      if (!$stmt) {
        throw new Exception("Database error: " . $conn->error);
      }

      $if_treatment = 0;

      $stmt->bind_param(
        "sssssiisssssisss",
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
        $if_treatment,
        $addedByType,
        $addedById,
        $addedByName
      );

      if (!$stmt->execute()) {
        throw new Exception("Error saving patient: " . $stmt->error);
      }

      $patient_id = $stmt->insert_id;
      $stmt->close();

      // LOG ACTIVITY AND HISTORY
      if ($addedById > 0 && $addedByType !== 'Offline') {
        // Log activity
        logActivity(
          $conn,
          $addedById,
          $addedByType,
          'ADD_PATIENT',
          "Added new patient: $surname, $firstname $middlename",
          'patients',
          $patient_id
        );

        // Log history
        logHistory(
          $conn,
          'patients',
          $patient_id,
          'INSERT',
          $addedByType,
          $addedById,
          null,
          [
            'surname' => $surname,
            'firstname' => $firstname,
            'middlename' => $middlename,
            'date_of_birth' => $date_of_birth,
            'sex' => $sex,
            'address' => $address,
            'added_by' => $addedByName,
            'added_by_type' => $addedByType  // Add this to track in new_values
          ],
          "New patient registration"
        );

        error_log("SUCCESS LOGGED: Patient registered by user ID: $addedById, Type: '$addedByType'");
      }

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
      foreach ($flags as $flag) {
        $$flag = isset($_POST[$flag]) ? 1 : 0;
      }

      $philno = !empty($_POST['philhealth_number']) ? $_POST['philhealth_number'] : null;
      $sssno  = !empty($_POST['sss_number']) ? $_POST['sss_number'] : null;
      $gsisno = !empty($_POST['gsis_number']) ? $_POST['gsis_number'] : null;

      $stmt2 = $conn->prepare("
                INSERT INTO patient_other_info (
                    patient_id, nhts_pr, four_ps, indigenous_people, pwd,
                    philhealth_flag, philhealth_number,
                    sss_flag, sss_number,
                    gsis_flag, gsis_number
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

      if ($stmt2) {
        $stmt2->bind_param(
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
        $stmt2->execute();
        $stmt2->close();
      }

      // VITAL SIGNS
      $bp   = !empty($_POST['blood_pressure']) ? $_POST['blood_pressure'] : null;
      $pr   = !empty($_POST['pulse_rate']) ? $_POST['pulse_rate'] : null;
      $temp = !empty($_POST['temperature']) ? $_POST['temperature'] : null;
      $wt   = !empty($_POST['weight']) ? $_POST['weight'] : null;

      $stmt3 = $conn->prepare("
                INSERT INTO vital_signs (patient_id, blood_pressure, pulse_rate, temperature, weight) 
                VALUES (?, ?, ?, ?, ?)
            ");
      if ($stmt3) {
        $stmt3->bind_param("isidd", $patient_id, $bp, $pr, $temp, $wt);
        $stmt3->execute();
        $stmt3->close();
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

      foreach ($fields as $f) {
        $$f = isset($_POST[$f]) ? 1 : 0;
      }

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

      foreach ($details as $d) {
        $$d = !empty($_POST[$d]) ? $_POST[$d] : null;
      }

      $stmt4 = $conn->prepare("
                INSERT INTO medical_history (
                    patient_id, allergies_flag, allergies_details, hypertension_cva,
                    diabetes_mellitus, blood_disorders, heart_disease, thyroid_disorders,
                    hepatitis_flag, hepatitis_details, malignancy_flag, malignancy_details,
                    prev_hospitalization_flag, last_admission_date, admission_cause,
                    surgery_details, blood_transfusion_flag, blood_transfusion,
                    tattoo, other_conditions_flag, other_conditions
                ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
            ");

      if ($stmt4) {
        $stmt4->bind_param(
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
        $stmt4->execute();
        $stmt4->close();
      }

      // DIETARY HABITS
      $habits = ['sugar', 'alcohol', 'tobacco', 'betel_nut'];
      foreach ($habits as $h) {
        ${$h . '_flag'} = isset($_POST[$h . '_flag']) ? 1 : 0;
        ${$h . '_details'} = !empty($_POST[$h . '_details']) ? $_POST[$h . '_details'] : null;
      }

      $stmt5 = $conn->prepare("
                INSERT INTO dietary_habits (
                    patient_id, sugar_flag, sugar_details, alcohol_flag, alcohol_details,
                    tobacco_flag, tobacco_details, betel_nut_flag, betel_nut_details
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

      if ($stmt5) {
        $stmt5->bind_param(
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
        $stmt5->execute();
        $stmt5->close();
      }

      // Commit transaction
      $conn->commit();

      // SUCCESS RESPONSE
      $response['status']  = 'success';
      $response['title']   = "Success!";
      $response['message'] = "Patient registration added successfully!";
      $response['patient_id'] = $patient_id;
      $response['debug'] = [
        'user_id' => $addedById,
        'user_type' => $addedByType,
        'user_name' => $addedByName
      ];

      // Log success activity
      if ($addedById > 0 && $addedByType !== 'Offline') {
        logActivity(
          $conn,
          $addedById,
          $addedByType,
          'PATIENT_ADDED_SUCCESS',
          "Successfully added patient ID: $patient_id - $surname, $firstname"
        );
      }
    } catch (Exception $e) {
      // Rollback on error
      if ($conn) {
        $conn->rollback();
      }

      $response['title'] = "Error";
      $response['message'] = $e->getMessage();
      error_log("Patient registration error: " . $e->getMessage());
    } finally {
      // Close connection
      if ($conn) {
        $conn->close();
      }
    }
  }
} else {
  // GET request - just return info
  $response['status'] = 'info';
  $response['title'] = 'Form Loaded';
  $response['message'] = 'Add Patient form loaded successfully';
  $response['isOffline'] = $isOfflineMode;
  $response['debug_session'] = [
    'user_id' => $currentUser['id'] ?? null,
    'user_type' => $currentUser['type'] ?? null,
    'user_name' => $currentUser['name'] ?? null
  ];
}

echo json_encode($response);
exit;
