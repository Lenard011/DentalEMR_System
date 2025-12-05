<?php
// ===========================
// BACKEND API - view_info.php
// ===========================
session_start();

// Suppress HTML error display, log errors instead
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
ini_set('log_errors', 1);
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
    echo json_encode(["error" => "Database connection failed"]);
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
// POST REQUESTS
// ===========================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Initialize input array
    $input = [];

    // Check if it's multipart/form-data (FormData from JavaScript)
    if (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'multipart/form-data') !== false) {
        // Handle FormData submission
        $input = $_POST;

        // Convert checkbox values to integers (0/1)
        foreach ($input as $key => $value) {
            if (strpos($key, '_flag') !== false || in_array($key, ['nhts_pr', 'four_ps', 'indigenous_people', 'pwd', 'tattoo'])) {
                $input[$key] = ($value === '1' || $value === 'on') ? 1 : 0;
            }
        }
    } else {
        // Handle JSON or form-urlencoded
        $rawInput = file_get_contents('php://input');

        // Check if it's form-urlencoded
        if (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/x-www-form-urlencoded') !== false) {
            parse_str($rawInput, $input);
        } else {
            // Assume JSON
            $input = json_decode($rawInput, true);
            if ($input === null && json_last_error() !== JSON_ERROR_NONE) {
                http_response_code(400);
                echo json_encode(["error" => "Invalid JSON input", "details" => json_last_error_msg()]);
                exit;
            }
        }
    }

    // Log the received input for debugging
    error_log("POST Input: " . print_r($input, true));

    if (!$input || !isset($input['action'])) {
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
        // SAVE MEMBERSHIP
        if ($action === 'save_membership') {
            // Debug log the input
            error_log("save_membership called with: " . print_r($input, true));

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

            // Prepare the UPDATE statement WITHOUT updated_at column
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
            $nhts_pr = isset($input['nhts_pr']) ? intval($input['nhts_pr']) : 0;
            $four_ps = isset($input['four_ps']) ? intval($input['four_ps']) : 0;
            $indigenous_people = isset($input['indigenous_people']) ? intval($input['indigenous_people']) : 0;
            $pwd = isset($input['pwd']) ? intval($input['pwd']) : 0;
            $philhealth_flag = isset($input['philhealth_flag']) ? intval($input['philhealth_flag']) : 0;
            $sss_flag = isset($input['sss_flag']) ? intval($input['sss_flag']) : 0;
            $gsis_flag = isset($input['gsis_flag']) ? intval($input['gsis_flag']) : 0;

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

        // SAVE MEDICAL HISTORY
        if ($action === 'save_medical') {
            // Check if record exists
            $check = $conn->prepare("SELECT 1 FROM medical_history WHERE patient_id=?");
            $check->bind_param("i", $patientId);
            $check->execute();

            if (!$check->get_result()->fetch_assoc()) {
                $ins = $conn->prepare("INSERT INTO medical_history (patient_id) VALUES (?)");
                $ins->bind_param("i", $patientId);
                $ins->execute();
                $ins->close();
            }
            $check->close();

            $sql = "UPDATE medical_history SET 
                allergies_flag=?, allergies_details=?,
                hypertension_cva=?, diabetes_mellitus=?, blood_disorders=?, heart_disease=?, thyroid_disorders=?,
                hepatitis_flag=?, hepatitis_details=?,
                malignancy_flag=?, malignancy_details=?,
                prev_hospitalization_flag=?, last_admission_date=?, admission_cause=?,
                surgery_details=?,
                blood_transfusion_flag=?, blood_transfusion=?,
                tattoo=?, other_conditions_flag=?, other_conditions=?
            WHERE patient_id=?";

            $stmt = $conn->prepare($sql);
            $stmt->bind_param(
                "isiiiiisssssssssssi",
                $input['allergies_flag'] ?? 0,
                $input['allergies_details'] ?? null,
                $input['hypertension_cva'] ?? 0,
                $input['diabetes_mellitus'] ?? 0,
                $input['blood_disorders'] ?? 0,
                $input['heart_disease'] ?? 0,
                $input['thyroid_disorders'] ?? 0,
                $input['hepatitis_flag'] ?? 0,
                $input['hepatitis_details'] ?? null,
                $input['malignancy_flag'] ?? 0,
                $input['malignancy_details'] ?? null,
                $input['prev_hospitalization_flag'] ?? 0,
                $input['last_admission_date'] ?? null,
                $input['admission_cause'] ?? null,
                $input['surgery_details'] ?? null,
                $input['blood_transfusion_flag'] ?? 0,
                $input['blood_transfusion'] ?? null,
                $input['tattoo'] ?? 0,
                $input['other_conditions_flag'] ?? 0,
                $input['other_conditions'] ?? null,
                $patientId
            );

            if ($stmt->execute()) {
                echo json_encode(["success" => true, "message" => "Medical history saved successfully"]);
            } else {
                throw new Exception("Failed to save medical history: " . $stmt->error);
            }
            $stmt->close();
            exit;
        }

        // SAVE DIETARY HABITS
        if ($action === 'save_dietary') {
            // Check if record exists
            $check = $conn->prepare("SELECT 1 FROM dietary_habits WHERE patient_id=?");
            $check->bind_param("i", $patientId);
            $check->execute();

            if (!$check->get_result()->fetch_assoc()) {
                $ins = $conn->prepare("INSERT INTO dietary_habits (patient_id) VALUES (?)");
                $ins->bind_param("i", $patientId);
                $ins->execute();
                $ins->close();
            }
            $check->close();

            $sql = "UPDATE dietary_habits SET 
                sugar_flag=?, sugar_details=?,
                alcohol_flag=?, alcohol_details=?,
                tobacco_flag=?, tobacco_details=?,
                betel_nut_flag=?, betel_nut_details=?
            WHERE patient_id=?";

            $stmt = $conn->prepare($sql);
            $stmt->bind_param(
                "isississi",
                $input['sugar_flag'] ?? 0,
                $input['sugar_details'] ?? null,
                $input['alcohol_flag'] ?? 0,
                $input['alcohol_details'] ?? null,
                $input['tobacco_flag'] ?? 0,
                $input['tobacco_details'] ?? null,
                $input['betel_nut_flag'] ?? 0,
                $input['betel_nut_details'] ?? null,
                $patientId
            );

            if ($stmt->execute()) {
                echo json_encode(["success" => true, "message" => "Dietary habits saved successfully"]);
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
        http_response_code(500);
        echo json_encode(["error" => "Database error: " . $e->getMessage()]);
    }
}

$conn->close();
