<?php
// ===========================
// BACKEND API - view_info.php
// ===========================
session_start();

// Enable error logging for debugging
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_errors.log');
error_reporting(E_ALL);

// Set CORS headers FIRST (before any output)
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE, PATCH");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Credentials: true");

// Add after setting Content-Type header
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Handle OPTIONS request for CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Set JSON header
header('Content-Type: application/json; charset=UTF-8');

// Database connection
$host = "localhost";
$dbUser = "u401132124_dentalclinic";
$dbPass = "Mho_DentalClinic1st";
$dbName = "u401132124_mho_dentalemr";

// Use mysqli with proper error handling
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli($host, $dbUser, $dbPass, $dbName);
    $conn->set_charset("utf8mb4");
    $conn->options(MYSQLI_OPT_CONNECT_TIMEOUT, 5);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "error" => "Database connection failed",
        "message" => $e->getMessage(),
        "code" => $e->getCode()
    ]);
    exit;
}

// ===========================
// HISTORY LOGGING FUNCTION
// ===========================
function addHistoryLog($conn, $tableName, $recordId, $action, $changedByType, $changedById, $oldValues = null, $newValues = null, $description = null)
{
    try {
        $sql = "INSERT INTO history_logs 
                (table_name, record_id, action, changed_by_type, changed_by_id, old_values, new_values, description, ip_address) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            error_log("Failed to prepare history log statement: " . $conn->error);
            return false;
        }

        $oldJSON = $oldValues ? json_encode($oldValues, JSON_UNESCAPED_UNICODE) : null;
        $newJSON = $newValues ? json_encode($newValues, JSON_UNESCAPED_UNICODE) : null;
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

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

        $success = $stmt->execute();
        if (!$success) {
            error_log("Failed to execute history log: " . $stmt->error);
        }

        $stmt->close();
        return $success;
    } catch (Exception $e) {
        error_log("Error in addHistoryLog: " . $e->getMessage());
        return false;
    }
}

// ===========================
// GET USER INFORMATION FUNCTION
// ===========================
function getLoggedUserInfo($conn, $input = [])
{
    $userId = 0;
    $userType = 'System';
    $userName = 'System';

    // Priority: Check POST data first (from form submissions)
    if (!empty($input['uid'])) {
        $userId = intval($input['uid']);
        $userType = $input['user_type'] ?? 'System';
        $userName = $input['user_name'] ?? 'System User';
    }
    // Check GET parameters
    elseif (isset($_GET['uid']) && !empty($_GET['uid'])) {
        $userId = intval($_GET['uid']);
        $userType = 'System';
        $userName = 'System User';
    }

    return [
        'id' => $userId,
        'type' => $userType,
        'name' => $userName
    ];
}

// ===========================
// GET PATIENT NAME FUNCTION
// ===========================
function getPatientName($conn, $patientId)
{
    try {
        $stmt = $conn->prepare("SELECT firstname, surname FROM patients WHERE patient_id = ?");
        $stmt->bind_param("i", $patientId);
        $stmt->execute();
        $result = $stmt->get_result();
        $patient = $result->fetch_assoc();
        $stmt->close();

        if ($patient) {
            $name = trim(($patient['firstname'] ?? '') . ' ' . ($patient['surname'] ?? ''));
            return $name ?: "Patient ID: $patientId";
        }
        return "Unknown Patient (ID: $patientId)";
    } catch (Exception $e) {
        error_log("Error getting patient name: " . $e->getMessage());
        return "Patient ID: $patientId";
    }
}

// ===========================
// GET REQUESTS
// ===========================
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!isset($_GET['action'])) {
        http_response_code(400);
        echo json_encode(["error" => "Action parameter required"]);
        exit;
    }

    $action = $_GET['action'];
    $patientId = isset($_GET['patient_id']) ? intval($_GET['patient_id']) : 0;

    if ($patientId <= 0) {
        http_response_code(400);
        echo json_encode(["error" => "Valid patient_id parameter required"]);
        exit;
    }

    try {
        // GET PATIENT INFO
        if ($action === 'get_patient') {
            $sql = "SELECT patient_id, firstname, surname, middlename, 
                   DATE_FORMAT(date_of_birth, '%Y-%m-%d') as date_of_birth,
                   sex, age, months_old, pregnant, place_of_birth, address, occupation, guardian 
            FROM patients 
            WHERE patient_id=? AND if_treatment=1";

            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                error_log("Prepare failed: " . $conn->error);
                throw new Exception("Database prepare failed: " . $conn->error);
            }

            $stmt->bind_param("i", $patientId);

            if (!$stmt->execute()) {
                error_log("Execute failed: " . $stmt->error);
                throw new Exception("Failed to execute query: " . $stmt->error);
            }

            $result = $stmt->get_result();

            if (!$result) {
                throw new Exception("Failed to get result: " . $stmt->error);
            }

            $patient = $result->fetch_assoc();
            $stmt->close();

            if (!$patient) {
                http_response_code(404);
                echo json_encode(["error" => "Patient not found or not in treatment"]);
                exit;
            }

            // Clean and format data for consistent browser display
            foreach ($patient as $key => $value) {
                if (is_string($value)) {
                    $patient[$key] = htmlspecialchars_decode($value, ENT_QUOTES);
                    $patient[$key] = trim($patient[$key]);
                }
            }

            echo json_encode([
                "success" => true,
                "patient" => $patient
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }

        // GET MEMBERSHIP INFO
        if ($action === 'get_membership') {
            $stmt = $conn->prepare("SELECT * FROM patient_other_info WHERE patient_id = ?");
            $stmt->bind_param("i", $patientId);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $stmt->close();

            $membership = [];
            if ($row) {
                $labels = [
                    'nhts_pr' => 'National Household Targeting System - Poverty Reduction (NHTS)',
                    'four_ps' => 'Pantawid Pamilyang Pilipino Program (4Ps)',
                    'indigenous_people' => 'Indigenous People (IP)',
                    'pwd' => 'Person with Disabilities (PWD)'
                ];

                foreach ($labels as $field => $label) {
                    if (!empty($row[$field]) && $row[$field] == 1) {
                        $membership[] = ["field" => $field, "label" => $label];
                    }
                }

                if (!empty($row['philhealth_flag']) && $row['philhealth_flag'] == 1) {
                    $membership[] = [
                        "field" => "philhealth_flag",
                        "label" => "PhilHealth: " . ($row['philhealth_number'] ?: "N/A")
                    ];
                }

                if (!empty($row['sss_flag']) && $row['sss_flag'] == 1) {
                    $membership[] = [
                        "field" => "sss_flag",
                        "label" => "SSS: " . ($row['sss_number'] ?: "N/A")
                    ];
                }

                if (!empty($row['gsis_flag']) && $row['gsis_flag'] == 1) {
                    $membership[] = [
                        "field" => "gsis_flag",
                        "label" => "GSIS: " . ($row['gsis_number'] ?: "N/A")
                    ];
                }
            }

            echo json_encode([
                "success" => true,
                "membership" => $membership,
                "values" => $row ?? []
            ]);
            exit;
        }

        // GET VITAL SIGNS
        if ($action === 'get_vitals') {
            $stmt = $conn->prepare("SELECT blood_pressure, pulse_rate, temperature, weight, recorded_at FROM vital_signs WHERE patient_id=? ORDER BY recorded_at DESC LIMIT 10");
            $stmt->bind_param("i", $patientId);
            $stmt->execute();
            $result = $stmt->get_result();
            $vitals = $result->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            echo json_encode([
                'success' => true,
                'vitals' => $vitals
            ]);
            exit;
        }

        // GET MEDICAL HISTORY
        if ($action === "get_medical") {
            $stmt = $conn->prepare("SELECT * FROM medical_history WHERE patient_id = ?");
            $stmt->bind_param("i", $patientId);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $stmt->close();

            $medical = [];
            if ($row) {
                $medicalFields = [
                    'allergies_flag' => 'Allergies',
                    'hypertension_cva' => 'Hypertension / CVA',
                    'diabetes_mellitus' => 'Diabetes Mellitus',
                    'blood_disorders' => 'Blood Disorders',
                    'heart_disease' => 'Cardiovascular / Heart Disease',
                    'thyroid_disorders' => 'Thyroid Disorders',
                    'hepatitis_flag' => 'Hepatitis',
                    'malignancy_flag' => 'Malignancy',
                    'prev_hospitalization_flag' => 'Previous Hospitalization',
                    'surgery_details' => 'Surgery',
                    'blood_transfusion_flag' => 'Blood Transfusion',
                    'tattoo' => 'Tattoo',
                    'other_conditions_flag' => 'Others'
                ];

                foreach ($medicalFields as $field => $label) {
                    if (!empty($row[$field]) && $row[$field] == 1) {
                        $details = '';

                        switch ($field) {
                            case 'allergies_flag':
                                $details = $row['allergies_details'] ?? '';
                                break;
                            case 'hepatitis_flag':
                                $details = $row['hepatitis_details'] ?? '';
                                break;
                            case 'malignancy_flag':
                                $details = $row['malignancy_details'] ?? '';
                                break;
                            case 'prev_hospitalization_flag':
                                $date = $row['last_admission_date'] ?? '';
                                $cause = $row['admission_cause'] ?? '';
                                $details = $date ? "$date - $cause" : '';
                                break;
                            case 'surgery_details':
                                $details = $row[$field] ?? '';
                                break;
                            case 'blood_transfusion_flag':
                                $details = $row['blood_transfusion'] ?? '';
                                break;
                            case 'other_conditions_flag':
                                $details = $row['other_conditions'] ?? '';
                                break;
                        }

                        $fullLabel = $label . ($details ? " - ($details)" : "");
                        $medical[] = ["field" => $field, "label" => $fullLabel];
                    }
                }
            }

            echo json_encode([
                "success" => true,
                "medical" => $medical,
                "values" => $row ?? []
            ]);
            exit;
        }

        // GET DIETARY HABITS
        if ($action === "get_dietary") {
            $stmt = $conn->prepare("SELECT * FROM dietary_habits WHERE patient_id = ?");
            $stmt->bind_param("i", $patientId);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $stmt->close();

            $dietary = [];
            if ($row) {
                $dietaryFields = [
                    'sugar_flag' => 'Sugar Sweetened Beverages/Food',
                    'alcohol_flag' => 'Use of Alcohol',
                    'tobacco_flag' => 'Use of Tobacco',
                    'betel_nut_flag' => 'Betel Nut Chewing'
                ];

                foreach ($dietaryFields as $field => $label) {
                    if (!empty($row[$field]) && $row[$field] == 1) {
                        $detailsField = str_replace('_flag', '_details', $field);
                        $details = $row[$detailsField] ?? '';
                        $fullLabel = $label . ($details ? " - ($details)" : "");
                        $dietary[] = ["field" => $field, "label" => $fullLabel];
                    }
                }
            }

            echo json_encode([
                "success" => true,
                "dietary" => $dietary,
                "values" => $row ?? []
            ]);
            exit;
        }

        // Invalid action
        http_response_code(400);
        echo json_encode(["error" => "Invalid action"]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["error" => "Database error: " . $e->getMessage()]);
    }
}

// ===========================
// POST REQUESTS WITH HISTORY LOGS
// ===========================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = [];

    if (!empty($_POST)) {
        $input = $_POST;

        $checkboxFields = [
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
            'other_conditions_flag',
            'sugar_flag',
            'alcohol_flag',
            'tobacco_flag',
            'betel_nut_flag',
            'nhts_pr',
            'four_ps',
            'indigenous_people',
            'pwd',
            'philhealth_flag',
            'sss_flag',
            'gsis_flag'
        ];

        foreach ($checkboxFields as $field) {
            if (!isset($input[$field])) {
                $input[$field] = 0;
            } else {
                $input[$field] = intval($input[$field]);
            }
        }
    } else {
        $rawInput = file_get_contents('php://input');

        if (!empty($rawInput)) {
            $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

            if (strpos($contentType, 'application/json') !== false) {
                $input = json_decode($rawInput, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    http_response_code(400);
                    echo json_encode(["error" => "Invalid JSON input", "details" => json_last_error_msg()]);
                    exit;
                }
            } else if (strpos($contentType, 'application/x-www-form-urlencoded') !== false) {
                parse_str($rawInput, $input);
            }
        }
    }

    if (empty($input) || !isset($input['action'])) {
        http_response_code(400);
        echo json_encode(["error" => "Invalid request. No action specified."]);
        exit;
    }

    $action = $input['action'];
    $patientId = isset($input['patient_id']) ? intval($input['patient_id']) : 0;

    if ($patientId <= 0) {
        http_response_code(400);
        echo json_encode(["error" => "Valid patient_id required."]);
        exit;
    }

    try {
        // Get logged user info for logging
        $loggedUser = getLoggedUserInfo($conn, $input);
        $patientName = getPatientName($conn, $patientId);

        // SAVE PATIENT INFO - FIXED: Without updated_at column
        if ($action === 'save_patient') {
            $requiredFields = ['firstname', 'surname', 'date_of_birth', 'sex', 'age'];
            $missingFields = [];

            foreach ($requiredFields as $field) {
                if (empty($input[$field])) {
                    $missingFields[] = $field;
                }
            }

            if (!empty($missingFields)) {
                http_response_code(400);
                echo json_encode(["error" => "Missing required fields: " . implode(', ', $missingFields)]);
                exit;
            }

            // Get months_old (default to 0 if not provided)
            $months_old = isset($input['months_old']) ? intval($input['months_old']) : 0;

            // Get pregnant status (default to 'no' if not provided or not female)
            $pregnant = 'no';
            if (isset($input['sex']) && $input['sex'] === 'Female' && isset($input['pregnant'])) {
                $pregnant = ($input['pregnant'] === 'yes') ? 'yes' : 'no';
            }

            // Get current patient data for logging old values
            $currentStmt = $conn->prepare("SELECT firstname, surname, middlename, date_of_birth, sex, age, months_old, pregnant, place_of_birth, address, occupation, guardian FROM patients WHERE patient_id=?");
            $currentStmt->bind_param("i", $patientId);
            $currentStmt->execute();
            $currentResult = $currentStmt->get_result();
            $oldData = $currentResult->fetch_assoc();
            $currentStmt->close();

            // Prepare the update statement - WITHOUT updated_at column
            $stmt = $conn->prepare("UPDATE patients SET 
                firstname=?, surname=?, middlename=?, date_of_birth=?, sex=?, age=?, 
                months_old=?, pregnant=?, place_of_birth=?, address=?, occupation=?, guardian=?
                WHERE patient_id=?");

            if (!$stmt) {
                throw new Exception("Database prepare failed: " . $conn->error);
            }

            // Handle empty values as NULL
            $middlename = !empty($input['middlename']) ? $input['middlename'] : null;
            $place_of_birth = !empty($input['place_of_birth']) ? $input['place_of_birth'] : null;
            $address = !empty($input['address']) ? $input['address'] : null;
            $occupation = !empty($input['occupation']) ? $input['occupation'] : null;
            $guardian = !empty($input['guardian']) ? $input['guardian'] : null;

            // Note: Changed type string from "sssssiisssssi" to "sssssiissssi" (12 params instead of 13)
            $stmt->bind_param(
                "sssssiissssi",
                $input['firstname'],
                $input['surname'],
                $middlename,
                $input['date_of_birth'],
                $input['sex'],
                $input['age'],
                $months_old,
                $pregnant,
                $place_of_birth,
                $address,
                $occupation,
                $guardian,
                $patientId
            );

            if (!$stmt->execute()) {
                throw new Exception("Failed to update patient: " . $stmt->error);
            }

            $stmt->close();

            // Prepare new data for logging
            $newData = [
                'firstname' => $input['firstname'],
                'surname' => $input['surname'],
                'middlename' => $middlename,
                'date_of_birth' => $input['date_of_birth'],
                'sex' => $input['sex'],
                'age' => $input['age'],
                'months_old' => $months_old,
                'pregnant' => $pregnant,
                'place_of_birth' => $place_of_birth,
                'address' => $address,
                'occupation' => $occupation,
                'guardian' => $guardian
            ];

            // Log the update
            addHistoryLog(
                $conn,
                "patients",
                $patientId,
                "UPDATE",
                $loggedUser['type'],
                $loggedUser['id'],
                $oldData,
                $newData,
                "Updated patient info: $patientName (ID: $patientId) by {$loggedUser['type']} {$loggedUser['name']}"
            );

            echo json_encode([
                "success" => true,
                "message" => "Patient updated successfully",
                "logged_by" => [
                    "type" => $loggedUser['type'],
                    "name" => $loggedUser['name'],
                    "id" => $loggedUser['id']
                ]
            ]);
            exit;
        }

        // SAVE VITAL SIGNS
        if ($action === 'save_vitals') {
            $requiredFields = ['blood_pressure', 'pulse_rate', 'temperature', 'weight'];
            foreach ($requiredFields as $field) {
                if (!isset($input[$field])) {
                    http_response_code(400);
                    echo json_encode(["error" => "Missing required field: $field"]);
                    exit;
                }
            }

            $stmt = $conn->prepare("INSERT INTO vital_signs (patient_id, blood_pressure, pulse_rate, temperature, weight, recorded_at) VALUES (?, ?, ?, ?, ?, NOW())");
            $stmt->bind_param("issss", $patientId, $input['blood_pressure'], $input['pulse_rate'], $input['temperature'], $input['weight']);

            if (!$stmt->execute()) {
                throw new Exception("Failed to save vital signs: " . $stmt->error);
            }

            $vitalId = $stmt->insert_id;
            $stmt->close();

            // Log vital signs addition
            addHistoryLog(
                $conn,
                "vital_signs",
                $vitalId,
                "CREATE",
                $loggedUser['type'],
                $loggedUser['id'],
                null,
                [
                    'blood_pressure' => $input['blood_pressure'],
                    'pulse_rate' => $input['pulse_rate'],
                    'temperature' => $input['temperature'],
                    'weight' => $input['weight']
                ],
                "Added vital signs for patient: $patientName (ID: $patientId) by {$loggedUser['type']} {$loggedUser['name']}"
            );

            echo json_encode([
                "success" => true,
                "message" => "Vital signs saved successfully",
                "logged_by" => [
                    "type" => $loggedUser['type'],
                    "name" => $loggedUser['name'],
                    "id" => $loggedUser['id']
                ]
            ]);
            exit;
        }

        // SAVE MEMBERSHIP - FIXED: Without updated_at column
        if ($action === 'save_membership') {
            // Get current membership data for logging old values
            $currentStmt = $conn->prepare("SELECT * FROM patient_other_info WHERE patient_id=?");
            $currentStmt->bind_param("i", $patientId);
            $currentStmt->execute();
            $currentResult = $currentStmt->get_result();
            $oldData = $currentResult->fetch_assoc();
            $currentStmt->close();

            // Check if record exists
            $check = $conn->prepare("SELECT patient_id FROM patient_other_info WHERE patient_id=?");
            $check->bind_param("i", $patientId);
            $check->execute();
            $exists = $check->get_result()->fetch_assoc();
            $check->close();

            if (!$exists) {
                $ins = $conn->prepare("INSERT INTO patient_other_info (patient_id) VALUES (?)");
                $ins->bind_param("i", $patientId);
                if (!$ins->execute()) {
                    throw new Exception("Failed to create membership record: " . $ins->error);
                }
                $ins->close();
            }

            // FIXED: Remove updated_at column from query
            $sql = "UPDATE patient_other_info SET 
                nhts_pr = ?, 
                four_ps = ?, 
                indigenous_people = ?, 
                pwd = ?, 
                philhealth_flag = ?, 
                philhealth_number = ?, 
                sss_flag = ?, 
                sss_number = ?, 
                gsis_flag = ?, 
                gsis_number = ?
            WHERE patient_id = ?";

            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }

            $nhts_pr = $input['nhts_pr'] ?? 0;
            $four_ps = $input['four_ps'] ?? 0;
            $indigenous_people = $input['indigenous_people'] ?? 0;
            $pwd = $input['pwd'] ?? 0;
            $philhealth_flag = $input['philhealth_flag'] ?? 0;
            $sss_flag = $input['sss_flag'] ?? 0;
            $gsis_flag = $input['gsis_flag'] ?? 0;

            $philhealth_number = !empty($input['philhealth_number']) ? $input['philhealth_number'] : null;
            $sss_number = !empty($input['sss_number']) ? $input['sss_number'] : null;
            $gsis_number = !empty($input['gsis_number']) ? $input['gsis_number'] : null;

            $stmt->bind_param(
                "iiiiisssssi",
                $nhts_pr,
                $four_ps,
                $indigenous_people,
                $pwd,
                $philhealth_flag,
                $philhealth_number,
                $sss_flag,
                $sss_number,
                $gsis_flag,
                $gsis_number,
                $patientId
            );

            if (!$stmt->execute()) {
                throw new Exception("Failed to save membership: " . $stmt->error);
            }

            $stmt->close();

            // Prepare new data for logging
            $newData = [
                'nhts_pr' => $nhts_pr,
                'four_ps' => $four_ps,
                'indigenous_people' => $indigenous_people,
                'pwd' => $pwd,
                'philhealth_flag' => $philhealth_flag,
                'philhealth_number' => $philhealth_number,
                'sss_flag' => $sss_flag,
                'sss_number' => $sss_number,
                'gsis_flag' => $gsis_flag,
                'gsis_number' => $gsis_number
            ];

            // Log membership update
            addHistoryLog(
                $conn,
                "patient_other_info",
                $patientId,
                $exists ? "UPDATE" : "CREATE",
                $loggedUser['type'],
                $loggedUser['id'],
                $oldData,
                $newData,
                "Updated membership info for patient: $patientName (ID: $patientId) by {$loggedUser['type']} {$loggedUser['name']}"
            );

            echo json_encode([
                "success" => true,
                "message" => "Membership updated successfully",
                "logged_by" => [
                    "type" => $loggedUser['type'],
                    "name" => $loggedUser['name'],
                    "id" => $loggedUser['id']
                ]
            ]);
            exit;
        }

        // SAVE MEDICAL HISTORY - FIXED: Use dynamic column detection without updated_at
        if ($action === 'save_medical') {
            // Get current medical history for logging old values
            $currentStmt = $conn->prepare("SELECT * FROM medical_history WHERE patient_id = ?");
            $currentStmt->bind_param("i", $patientId);
            $currentStmt->execute();
            $currentResult = $currentStmt->get_result();
            $oldData = $currentResult->fetch_assoc();
            $currentStmt->close();

            // Clean and validate input values
            $allergies_flag = isset($input['allergies_flag']) ? intval($input['allergies_flag']) : 0;
            $allergies_details = isset($input['allergies_details']) ? trim($input['allergies_details']) : null;
            $hypertension_cva = isset($input['hypertension_cva']) ? intval($input['hypertension_cva']) : 0;
            $diabetes_mellitus = isset($input['diabetes_mellitus']) ? intval($input['diabetes_mellitus']) : 0;
            $blood_disorders = isset($input['blood_disorders']) ? intval($input['blood_disorders']) : 0;
            $heart_disease = isset($input['heart_disease']) ? intval($input['heart_disease']) : 0;
            $thyroid_disorders = isset($input['thyroid_disorders']) ? intval($input['thyroid_disorders']) : 0;
            $hepatitis_flag = isset($input['hepatitis_flag']) ? intval($input['hepatitis_flag']) : 0;
            $hepatitis_details = isset($input['hepatitis_details']) ? trim($input['hepatitis_details']) : null;
            $malignancy_flag = isset($input['malignancy_flag']) ? intval($input['malignancy_flag']) : 0;
            $malignancy_details = isset($input['malignancy_details']) ? trim($input['malignancy_details']) : null;
            $prev_hospitalization_flag = isset($input['prev_hospitalization_flag']) ? intval($input['prev_hospitalization_flag']) : 0;
            $last_admission_date = isset($input['last_admission_date']) && !empty($input['last_admission_date']) ? $input['last_admission_date'] : null;
            $admission_cause = isset($input['admission_cause']) ? trim($input['admission_cause']) : null;
            $surgery_details = isset($input['surgery_details']) ? trim($input['surgery_details']) : null;
            $blood_transfusion_flag = isset($input['blood_transfusion_flag']) ? intval($input['blood_transfusion_flag']) : 0;
            $blood_transfusion = isset($input['blood_transfusion']) ? trim($input['blood_transfusion']) : null;
            $tattoo = isset($input['tattoo']) ? intval($input['tattoo']) : 0;
            $other_conditions_flag = isset($input['other_conditions_flag']) ? intval($input['other_conditions_flag']) : 0;
            $other_conditions = isset($input['other_conditions']) ? trim($input['other_conditions']) : null;

            // Check if record exists
            $checkStmt = $conn->prepare("SELECT patient_id FROM medical_history WHERE patient_id = ?");
            $checkStmt->bind_param("i", $patientId);
            $checkStmt->execute();
            $checkStmt->store_result();
            $exists = $checkStmt->num_rows > 0;
            $checkStmt->close();

            if (!$exists) {
                // Insert a new record
                $insertStmt = $conn->prepare("INSERT INTO medical_history (patient_id) VALUES (?)");
                $insertStmt->bind_param("i", $patientId);
                if (!$insertStmt->execute()) {
                    throw new Exception("Failed to create medical history record: " . $insertStmt->error);
                }
                $insertStmt->close();
            }

            // Build the UPDATE query dynamically based on what columns exist
            $fieldMap = [
                'allergies_flag' => ['value' => $allergies_flag, 'type' => 'i'],
                'allergies_details' => ['value' => $allergies_details, 'type' => 's'],
                'hypertension_cva' => ['value' => $hypertension_cva, 'type' => 'i'],
                'diabetes_mellitus' => ['value' => $diabetes_mellitus, 'type' => 'i'],
                'blood_disorders' => ['value' => $blood_disorders, 'type' => 'i'],
                'heart_disease' => ['value' => $heart_disease, 'type' => 'i'],
                'thyroid_disorders' => ['value' => $thyroid_disorders, 'type' => 'i'],
                'hepatitis_flag' => ['value' => $hepatitis_flag, 'type' => 'i'],
                'hepatitis_details' => ['value' => $hepatitis_details, 'type' => 's'],
                'malignancy_flag' => ['value' => $malignancy_flag, 'type' => 'i'],
                'malignancy_details' => ['value' => $malignancy_details, 'type' => 's'],
                'prev_hospitalization_flag' => ['value' => $prev_hospitalization_flag, 'type' => 'i'],
                'last_admission_date' => ['value' => $last_admission_date, 'type' => 's'],
                'admission_cause' => ['value' => $admission_cause, 'type' => 's'],
                'surgery_details' => ['value' => $surgery_details, 'type' => 's'],
                'blood_transfusion_flag' => ['value' => $blood_transfusion_flag, 'type' => 'i'],
                'blood_transfusion' => ['value' => $blood_transfusion, 'type' => 's'],
                'tattoo' => ['value' => $tattoo, 'type' => 'i'],
                'other_conditions_flag' => ['value' => $other_conditions_flag, 'type' => 'i'],
                'other_conditions' => ['value' => $other_conditions, 'type' => 's']
            ];

            // Check which columns actually exist in the table
            $result = $conn->query("DESCRIBE medical_history");
            $existingColumns = [];
            while ($row = $result->fetch_assoc()) {
                $existingColumns[] = $row['Field'];
            }

            // Build the SQL query only with existing columns
            $sql = "UPDATE medical_history SET ";
            $setParts = [];
            $values = [];
            $types = "";

            foreach ($fieldMap as $column => $data) {
                if (in_array($column, $existingColumns)) {
                    $setParts[] = "$column = ?";
                    $values[] = $data['value'];
                    $types .= $data['type'];
                }
            }

            if (empty($setParts)) {
                throw new Exception("No valid columns found in medical_history table");
            }

            $sql .= implode(", ", $setParts);
            $sql .= " WHERE patient_id = ?";

            // Add patient_id to values
            $values[] = $patientId;
            $types .= 'i';

            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                throw new Exception("Failed to prepare medical history update: " . $conn->error);
            }

            // Bind parameters dynamically
            $bindParams = [$types];
            foreach ($values as $key => $value) {
                $bindParams[] = &$values[$key];
            }

            call_user_func_array([$stmt, 'bind_param'], $bindParams);

            if (!$stmt->execute()) {
                throw new Exception("Failed to save medical history: " . $stmt->error);
            }

            $stmt->close();

            // Prepare new data for logging
            $newData = [];
            foreach ($fieldMap as $column => $data) {
                if (in_array($column, $existingColumns)) {
                    $newData[$column] = $data['value'];
                }
            }

            // Log medical history update
            addHistoryLog(
                $conn,
                "medical_history",
                $patientId, // Using patient_id as record_id since there's no id column
                $exists ? "UPDATE" : "CREATE",
                $loggedUser['type'],
                $loggedUser['id'],
                $oldData,
                $newData,
                "Updated medical history for patient: $patientName (ID: $patientId) by {$loggedUser['type']} {$loggedUser['name']}"
            );

            echo json_encode([
                "success" => true,
                "message" => "Medical history saved successfully",
                "logged_by" => [
                    "type" => $loggedUser['type'],
                    "name" => $loggedUser['name'],
                    "id" => $loggedUser['id']
                ]
            ]);
            exit;
        }

        // SAVE DIETARY HABITS - FIXED: Without updated_at column
        if ($action === 'save_dietary') {
            // Get current dietary habits for logging old values
            $currentStmt = $conn->prepare("SELECT * FROM dietary_habits WHERE patient_id = ?");
            $currentStmt->bind_param("i", $patientId);
            $currentStmt->execute();
            $currentResult = $currentStmt->get_result();
            $oldData = $currentResult->fetch_assoc();
            $currentStmt->close();

            // Check if record exists, create if not
            $check = $conn->prepare("SELECT patient_id FROM dietary_habits WHERE patient_id = ?");
            $check->bind_param("i", $patientId);
            $check->execute();
            $check->store_result();
            $exists = $check->num_rows > 0;
            $check->close();

            if (!$exists) {
                // Create a new record
                $insert = $conn->prepare("INSERT INTO dietary_habits (patient_id) VALUES (?)");
                $insert->bind_param("i", $patientId);
                if (!$insert->execute()) {
                    throw new Exception("Failed to create dietary habits record: " . $insert->error);
                }
                $insert->close();
            }

            // Prepare the UPDATE statement - FIXED: Remove updated_at
            $sql = "UPDATE dietary_habits SET 
                sugar_flag = ?, 
                sugar_details = ?,
                alcohol_flag = ?, 
                alcohol_details = ?,
                tobacco_flag = ?, 
                tobacco_details = ?,
                betel_nut_flag = ?, 
                betel_nut_details = ?
            WHERE patient_id = ?";

            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                throw new Exception("Database prepare failed for dietary habits: " . $conn->error);
            }

            // Get values with defaults
            $sugar_flag = $input['sugar_flag'] ?? 0;
            $sugar_details = !empty($input['sugar_details']) ? trim($input['sugar_details']) : null;
            $alcohol_flag = $input['alcohol_flag'] ?? 0;
            $alcohol_details = !empty($input['alcohol_details']) ? trim($input['alcohol_details']) : null;
            $tobacco_flag = $input['tobacco_flag'] ?? 0;
            $tobacco_details = !empty($input['tobacco_details']) ? trim($input['tobacco_details']) : null;
            $betel_nut_flag = $input['betel_nut_flag'] ?? 0;
            $betel_nut_details = !empty($input['betel_nut_details']) ? trim($input['betel_nut_details']) : null;

            $stmt->bind_param(
                "isississi",
                $sugar_flag,
                $sugar_details,
                $alcohol_flag,
                $alcohol_details,
                $tobacco_flag,
                $tobacco_details,
                $betel_nut_flag,
                $betel_nut_details,
                $patientId
            );

            if (!$stmt->execute()) {
                throw new Exception("Failed to save dietary habits: " . $stmt->error);
            }

            $stmt->close();

            // Prepare new data for logging
            $newData = [
                'sugar_flag' => $sugar_flag,
                'sugar_details' => $sugar_details,
                'alcohol_flag' => $alcohol_flag,
                'alcohol_details' => $alcohol_details,
                'tobacco_flag' => $tobacco_flag,
                'tobacco_details' => $tobacco_details,
                'betel_nut_flag' => $betel_nut_flag,
                'betel_nut_details' => $betel_nut_details
            ];

            // Log dietary habits update
            addHistoryLog(
                $conn,
                "dietary_habits",
                $patientId, // Using patient_id as record_id
                $exists ? "UPDATE" : "CREATE",
                $loggedUser['type'],
                $loggedUser['id'],
                $oldData,
                $newData,
                "Updated dietary habits for patient: $patientName (ID: $patientId) by {$loggedUser['type']} {$loggedUser['name']}"
            );

            echo json_encode([
                "success" => true,
                "message" => "Dietary habits saved successfully",
                "logged_by" => [
                    "type" => $loggedUser['type'],
                    "name" => $loggedUser['name'],
                    "id" => $loggedUser['id']
                ]
            ]);
            exit;
        }

        // Invalid action
        http_response_code(400);
        echo json_encode(["error" => "Invalid action"]);
    } catch (Exception $e) {
        error_log("Exception in POST handler: " . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            "error" => "Database error",
            "message" => $e->getMessage(),
            "action" => $action ?? 'unknown'
        ]);
    }
}

$conn->close();
