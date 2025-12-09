<?php
// ===========================
// BACKEND API - view_info.php
// ===========================
session_start();

// Enable error logging to help debug
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_errors.log');
error_reporting(E_ALL);

// Set JSON header FIRST
header('Content-Type: application/json; charset=UTF-8');

// Database connection
$host = "localhost";
$dbUser = "root";
$dbPass = "";
$dbName = "dentalemr_system";

$conn = new mysqli($host, $dbUser, $dbPass, $dbName);
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["error" => "Database connection failed: " . $conn->connect_error]);
    exit;
}

// Set charset
$conn->set_charset("utf8mb4");

// CORS headers
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// Handle OPTIONS request for CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
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
            $stmt = $conn->prepare("SELECT patient_id, firstname, surname, middlename, date_of_birth, sex, age, place_of_birth, address, occupation, guardian FROM patients WHERE patient_id=? AND if_treatment=1");
            if (!$stmt) {
                throw new Exception("Database prepare failed: " . $conn->error);
            }

            $stmt->bind_param("i", $patientId);
            $stmt->execute();
            $result = $stmt->get_result();
            $patient = $result->fetch_assoc();
            $stmt->close();

            if (!$patient) {
                http_response_code(404);
                echo json_encode(["error" => "Patient not found"]);
                exit;
            }

            echo json_encode([
                "success" => true,
                "patient" => $patient
            ]);
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

                // Handle number-based memberships
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

                        // Add details if available
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
// POST REQUESTS - IMPROVED VERSION
// ===========================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Initialize input array
    $input = [];

    // Always check for FormData first (most common from your frontend)
    if (!empty($_POST)) {
        $input = $_POST;

        // For FormData, unchecked checkboxes are not sent at all
        // So we need to check each possible checkbox field and set default to 0
        $checkboxFields = [
            // Medical history
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
            // Dietary habits
            'sugar_flag',
            'alcohol_flag',
            'tobacco_flag',
            'betel_nut_flag',
            // Membership
            'nhts_pr',
            'four_ps',
            'indigenous_people',
            'pwd',
            'philhealth_flag',
            'sss_flag',
            'gsis_flag'
        ];

        foreach ($checkboxFields as $field) {
            // If not set in POST, it means checkbox was unchecked
            if (!isset($input[$field])) {
                $input[$field] = 0;
            } else {
                // If set, convert to integer
                $input[$field] = intval($input[$field]);
            }
        }
    }
    // If no POST data, try to get raw input (for JSON or form-urlencoded)
    else {
        $rawInput = file_get_contents('php://input');

        if (!empty($rawInput)) {
            // Check Content-Type
            $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

            if (strpos($contentType, 'application/json') !== false) {
                $input = json_decode($rawInput, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    http_response_code(400);
                    echo json_encode(["error" => "Invalid JSON input", "details" => json_last_error_msg()]);
                    exit;
                }
            }
            // Check for form-urlencoded (not common with FormData but for completeness)
            else if (strpos($contentType, 'application/x-www-form-urlencoded') !== false) {
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
        // SAVE PATIENT INFO
        if ($action === 'save_patient') {
            // Required fields validation
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

            // Prepare the update statement
            $stmt = $conn->prepare("UPDATE patients SET 
                firstname=?, surname=?, middlename=?, date_of_birth=?, sex=?, age=?, 
                place_of_birth=?, address=?, occupation=?, guardian=?, updated_at=NOW()
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

            $stmt->bind_param(
                "sssssissssi",
                $input['firstname'],
                $input['surname'],
                $middlename,
                $input['date_of_birth'],
                $input['sex'],
                $input['age'],
                $place_of_birth,
                $address,
                $occupation,
                $guardian,
                $patientId
            );

            if ($stmt->execute()) {
                echo json_encode([
                    "success" => true,
                    "message" => "Patient updated successfully"
                ]);
            } else {
                throw new Exception("Failed to update patient: " . $stmt->error);
            }
            $stmt->close();
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

            if ($stmt->execute()) {
                echo json_encode([
                    "success" => true,
                    "message" => "Vital signs saved successfully"
                ]);
            } else {
                throw new Exception("Failed to save vital signs: " . $stmt->error);
            }
            $stmt->close();
            exit;
        }

        // SAVE MEMBERSHIP
        if ($action === 'save_membership') {
            // Check if record exists
            $check = $conn->prepare("SELECT patient_id FROM patient_other_info WHERE patient_id=?");
            $check->bind_param("i", $patientId);
            $check->execute();
            $exists = $check->get_result()->fetch_assoc();
            $check->close();

            if (!$exists) {
                // Insert new record with default values
                $ins = $conn->prepare("INSERT INTO patient_other_info (patient_id) VALUES (?)");
                $ins->bind_param("i", $patientId);
                if (!$ins->execute()) {
                    throw new Exception("Failed to create membership record: " . $ins->error);
                }
                $ins->close();
            }

            // Prepare the UPDATE statement
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

            // Handle checkbox values - default to 0 if not set
            $nhts_pr = $input['nhts_pr'] ?? 0;
            $four_ps = $input['four_ps'] ?? 0;
            $indigenous_people = $input['indigenous_people'] ?? 0;
            $pwd = $input['pwd'] ?? 0;
            $philhealth_flag = $input['philhealth_flag'] ?? 0;
            $sss_flag = $input['sss_flag'] ?? 0;
            $gsis_flag = $input['gsis_flag'] ?? 0;

            // Handle optional number fields
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

            if ($stmt->execute()) {
                echo json_encode([
                    "success" => true,
                    "message" => "Membership updated successfully"
                ]);
            } else {
                throw new Exception("Failed to save membership: " . $stmt->error);
            }
            $stmt->close();
            exit;
        }

        // SAVE MEDICAL HISTORY - FIXED VERSION
        if ($action === 'save_medical') {
            // First, let's log what we're receiving
            error_log("=== SAVE_MEDICAL REQUEST ===");
            error_log("Patient ID: " . $patientId);
            error_log("Received data: " . print_r($input, true));

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

            // Check if medical_history table exists and has correct structure
            $checkTable = $conn->query("SHOW TABLES LIKE 'medical_history'");
            if ($checkTable->num_rows === 0) {
                throw new Exception("medical_history table does not exist");
            }

            // Check if record exists
            $checkStmt = $conn->prepare("SELECT patient_id FROM medical_history WHERE patient_id = ?");
            if (!$checkStmt) {
                throw new Exception("Check prepare failed: " . $conn->error);
            }

            $checkStmt->bind_param("i", $patientId);
            $checkStmt->execute();
            $checkStmt->store_result();
            $exists = $checkStmt->num_rows > 0;
            $checkStmt->close();

            if (!$exists) {
                // Insert a new record
                $insertStmt = $conn->prepare("INSERT INTO medical_history (patient_id) VALUES (?)");
                if (!$insertStmt) {
                    throw new Exception("Insert prepare failed: " . $conn->error);
                }
                $insertStmt->bind_param("i", $patientId);
                if (!$insertStmt->execute()) {
                    $insertStmt->close();
                    throw new Exception("Failed to create medical history record: " . $conn->error);
                }
                $insertStmt->close();
            }

            // Build the UPDATE query dynamically based on what columns exist
            $columns = [];
            $values = [];
            $types = "";
            $bindParams = [];

            // List all possible columns with their values and types
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

            error_log("Medical History SQL: " . $sql);
            error_log("Types: " . $types);

            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                error_log("Prepare failed: " . $conn->error);
                throw new Exception("Failed to prepare medical history update: " . $conn->error);
            }

            // Bind parameters dynamically
            $bindParams = [$types];
            foreach ($values as $key => $value) {
                $bindParams[] = &$values[$key];
            }

            call_user_func_array([$stmt, 'bind_param'], $bindParams);

            if ($stmt->execute()) {
                if ($stmt->affected_rows > 0) {
                    echo json_encode([
                        "success" => true,
                        "message" => "Medical history saved successfully",
                        "affected_rows" => $stmt->affected_rows
                    ]);
                } else {
                    echo json_encode([
                        "success" => true,
                        "message" => "Medical history updated (no changes detected)",
                        "affected_rows" => 0
                    ]);
                }
            } else {
                error_log("Execute failed: " . $stmt->error);
                throw new Exception("Failed to save medical history: " . $stmt->error);
            }
            $stmt->close();
            exit;
        }

        // SAVE DIETARY HABITS - FIXED VERSION
        if ($action === 'save_dietary') {
            // Check if record exists, create if not
            $check = $conn->prepare("SELECT patient_id FROM dietary_habits WHERE patient_id = ?");
            if (!$check) {
                throw new Exception("Check prepare failed: " . $conn->error);
            }
            $check->bind_param("i", $patientId);
            $check->execute();
            $check->store_result();

            if ($check->num_rows === 0) {
                $check->close();
                // Create a new record
                $insert = $conn->prepare("INSERT INTO dietary_habits (patient_id) VALUES (?)");
                if (!$insert) {
                    throw new Exception("Insert prepare failed: " . $conn->error);
                }
                $insert->bind_param("i", $patientId);
                if (!$insert->execute()) {
                    $insert->close();
                    throw new Exception("Failed to create dietary habits record: " . $conn->error);
                }
                $insert->close();
            } else {
                $check->close();
            }

            // Prepare the UPDATE statement
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

            if ($stmt->execute()) {
                echo json_encode([
                    "success" => true,
                    "message" => "Dietary habits saved successfully"
                ]);
            } else {
                throw new Exception("Failed to save dietary habits: " . $stmt->error);
            }
            $stmt->close();
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
