<?php
// ===========================
// BACKEND (PHP) - IMPROVED VERSION
// ===========================
header('Content-Type: application/json');

// Enable error reporting for development (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors to users

// Validate request method
if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'])) {
    http_response_code(405);
    exit(json_encode(["error" => "Method not allowed"]));
}

require_once('../conn.php');

// CSRF protection for POST requests
function verifyCsrfToken()
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf_token'] ?? '';
        if (empty($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
            http_response_code(403);
            exit(json_encode(["error" => "Invalid CSRF token"]));
        }
    }
}

// Input validation and sanitization
function sanitizeInput($data)
{
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

function validateInteger($value, $min = 1, $max = PHP_INT_MAX)
{
    $value = filter_var($value, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => $min, 'max_range' => $max]
    ]);
    return $value !== false ? $value : false;
}

function validateFloat($value, $min = 0, $max = 999.99)
{
    $value = filter_var($value, FILTER_VALIDATE_FLOAT);
    if ($value === false) return false;
    return ($value >= $min && $value <= $max) ? $value : false;
}

function validateDate($date, $format = 'Y-m-d')
{
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}

/**
 * Helper function: dynamic bind_param with type validation
 */
function bind_params_dynamic($stmt, $params)
{
    if (empty($params)) return true;

    $types = '';
    $processedParams = [];

    foreach ($params as $param) {
        if (is_int($param)) {
            $types .= 'i';
            $processedParams[] = $param;
        } elseif (is_float($param)) {
            $types .= 'd';
            $processedParams[] = $param;
        } elseif (is_string($param)) {
            $types .= 's';
            $processedParams[] = $param;
        } else {
            $types .= 's';
            $processedParams[] = (string)$param;
        }
    }

    return $stmt->bind_param($types, ...$processedParams);
}

// Rate limiting (simple implementation)
function checkRateLimit($key, $limit = 60, $window = 60)
{
    if (!session_id()) session_start();

    $now = time();
    $requests = $_SESSION['rate_limit'][$key] ?? [];

    // Remove old requests
    $requests = array_filter($requests, function ($time) use ($now, $window) {
        return ($now - $time) < $window;
    });

    if (count($requests) >= $limit) {
        http_response_code(429);
        exit(json_encode(["error" => "Too many requests. Please try again later."]));
    }

    $requests[] = $now;
    $_SESSION['rate_limit'][$key] = array_slice($requests, -$limit);
}

// ===========================
//  GET HANDLING
// ===========================
if ($_SERVER['REQUEST_METHOD'] === 'GET') {

    // Apply rate limiting for GET requests
    $clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    checkRateLimit("get_$clientIp", 100, 60);

    // Validate required parameters
    if (!isset($_GET['action'])) {
        http_response_code(400);
        exit(json_encode(["error" => "Action parameter required"]));
    }

    $action = $_GET['action'];
    $allowedActions = ['get_patient', 'get_membership', 'get_vitals', 'get_medical', 'get_dietary'];

    if (!in_array($action, $allowedActions)) {
        http_response_code(400);
        exit(json_encode(["error" => "Invalid action"]));
    }

    // Common patient ID validation
    $patientId = validateInteger($_GET['patient_id'] ?? 0);
    if (!$patientId) {
        http_response_code(400);
        exit(json_encode(["error" => "Invalid patient ID"]));
    }

    try {
        // ========================
        // GET PATIENT INFO
        // ========================
        if ($action === 'get_patient') {
            $stmt = $conn->prepare("SELECT patient_id, firstname, surname, middlename, date_of_birth, sex, age, place_of_birth, address, occupation, guardian FROM patients WHERE patient_id=? AND archived=0");
            if (!$stmt) {
                throw new Exception("Database prepare failed: " . $conn->error);
            }

            $stmt->bind_param("i", $patientId);
            $stmt->execute();
            $result = $stmt->get_result();
            $res = $result->fetch_assoc();
            $stmt->close();

            if (!$res) {
                http_response_code(404);
                exit(json_encode(["error" => "Patient not found or archived"]));
            }

            echo json_encode([
                "success" => true,
                "patient" => $res
            ]);
            exit;
        }

        // ========================
        // GET MEMBERSHIP INFO
        // ========================
        if ($action === "get_membership") {
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
                        "label" => "PhilHealth: (" . ($row['philhealth_number'] ?: "N/A") . ")"
                    ];
                }

                if (!empty($row['sss_flag']) && $row['sss_flag'] == 1) {
                    $membership[] = [
                        "field" => "sss_flag",
                        "label" => "SSS: (" . ($row['sss_number'] ?: "N/A") . ")"
                    ];
                }

                if (!empty($row['gsis_flag']) && $row['gsis_flag'] == 1) {
                    $membership[] = [
                        "field" => "gsis_flag",
                        "label" => "GSIS: (" . ($row['gsis_number'] ?: "N/A") . ")"
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

        // ========================
        // GET VITAL SIGNS
        // ========================
        if ($action === 'get_vitals') {
            $stmt = $conn->prepare("SELECT blood_pressure, pulse_rate, temperature, weight, recorded_at FROM vital_signs WHERE patient_id=? ORDER BY recorded_at DESC LIMIT 10");
            $stmt->bind_param("i", $patientId);
            $stmt->execute();
            $result = $stmt->get_result();
            $vitals = $result->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            // Format dates for display
            foreach ($vitals as &$vital) {
                if (!empty($vital['recorded_at'])) {
                    $vital['recorded_at_formatted'] = date('Y-m-d H:i', strtotime($vital['recorded_at']));
                }
            }

            echo json_encode([
                'success' => true,
                'vitals' => $vitals
            ]);
            exit;
        }

        // ========================
        // GET MEDICAL HISTORY
        // ========================
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

        // ========================
        // GET DIETARY HABITS
        // ========================
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
    } catch (Exception $e) {
        error_log("Database error: " . $e->getMessage());
        http_response_code(500);
        exit(json_encode(["error" => "Database error occurred"]));
    }
}

// ===========================
//  POST HANDLING
// ===========================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Apply rate limiting for POST requests
    $clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    checkRateLimit("post_$clientIp", 30, 60);

    // Validate required parameters
    if (!isset($_POST['action'])) {
        http_response_code(400);
        exit(json_encode(["error" => "Action parameter required"]));
    }

    $action = $_POST['action'];
    $allowedActions = ['save_vitals', 'save_medical', 'save_dietary', 'save_membership', 'save_patient'];

    if (!in_array($action, $allowedActions)) {
        http_response_code(400);
        exit(json_encode(["error" => "Invalid action"]));
    }

    // Common patient ID validation
    $patientId = validateInteger($_POST['patient_id'] ?? 0);
    if (!$patientId) {
        http_response_code(400);
        exit(json_encode(["error" => "Invalid patient ID"]));
    }

    try {
        // ========================
        // SAVE VITAL SIGNS
        // ========================
        if ($action === 'save_vitals') {
            // Validate inputs
            $bloodPressure = filter_var($_POST['blood_pressure'] ?? '', FILTER_SANITIZE_STRING);
            $pulseRate = validateInteger($_POST['pulse_rate'] ?? 0, 0, 300);
            $temperature = validateFloat($_POST['temperature'] ?? 0, 30, 45);
            $weight = validateFloat($_POST['weight'] ?? 0, 0, 500);

            if ($pulseRate === false || $temperature === false || $weight === false) {
                http_response_code(400);
                exit(json_encode(["error" => "Invalid vital sign values"]));
            }

            $stmt = $conn->prepare("INSERT INTO vital_signs (patient_id, blood_pressure, pulse_rate, temperature, weight, recorded_at) VALUES (?, ?, ?, ?, ?, NOW())");
            $stmt->bind_param("issss", $patientId, $bloodPressure, $pulseRate, $temperature, $weight);

            if ($stmt->execute()) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Vital signs saved successfully',
                    'record_id' => $stmt->insert_id
                ]);
            } else {
                throw new Exception("Failed to save vital signs: " . $stmt->error);
            }
            $stmt->close();
            exit;
        }

        // ========================
        // SAVE MEDICAL HISTORY
        // ========================
        if ($action === "save_medical") {
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

            // Prepare update statement
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

            // Sanitize and validate inputs
            $params = [
                (int)!empty($_POST['allergies_flag']),
                !empty($_POST['allergies_details']) ? sanitizeInput($_POST['allergies_details']) : null,
                (int)!empty($_POST['hypertension_cva']),
                (int)!empty($_POST['diabetes_mellitus']),
                (int)!empty($_POST['blood_disorders']),
                (int)!empty($_POST['heart_disease']),
                (int)!empty($_POST['thyroid_disorders']),
                (int)!empty($_POST['hepatitis_flag']),
                !empty($_POST['hepatitis_details']) ? sanitizeInput($_POST['hepatitis_details']) : null,
                (int)!empty($_POST['malignancy_flag']),
                !empty($_POST['malignancy_details']) ? sanitizeInput($_POST['malignancy_details']) : null,
                (int)!empty($_POST['prev_hospitalization_flag']),
                !empty($_POST['last_admission_date']) && validateDate($_POST['last_admission_date']) ? $_POST['last_admission_date'] : null,
                !empty($_POST['admission_cause']) ? sanitizeInput($_POST['admission_cause']) : null,
                !empty($_POST['surgery_details']) ? sanitizeInput($_POST['surgery_details']) : null,
                (int)!empty($_POST['blood_transfusion_flag']),
                !empty($_POST['blood_transfusion']) && validateDate($_POST['blood_transfusion']) ? $_POST['blood_transfusion'] : null,
                (int)!empty($_POST['tattoo']),
                (int)!empty($_POST['other_conditions_flag']),
                !empty($_POST['other_conditions']) ? sanitizeInput($_POST['other_conditions']) : null,
                $patientId
            ];

            bind_params_dynamic($stmt, $params);

            if ($stmt->execute()) {
                echo json_encode(["success" => true, "message" => "Medical history saved successfully"]);
            } else {
                throw new Exception("Failed to save medical history: " . $stmt->error);
            }
            $stmt->close();
            exit;
        }

        // ========================
        // SAVE DIETARY HABITS
        // ========================
        if ($action === "save_dietary") {
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

            $params = [
                (int)!empty($_POST['sugar_flag']),
                !empty($_POST['sugar_details']) ? sanitizeInput($_POST['sugar_details']) : null,
                (int)!empty($_POST['alcohol_flag']),
                !empty($_POST['alcohol_details']) ? sanitizeInput($_POST['alcohol_details']) : null,
                (int)!empty($_POST['tobacco_flag']),
                !empty($_POST['tobacco_details']) ? sanitizeInput($_POST['tobacco_details']) : null,
                (int)!empty($_POST['betel_nut_flag']),
                !empty($_POST['betel_nut_details']) ? sanitizeInput($_POST['betel_nut_details']) : null,
                $patientId
            ];

            bind_params_dynamic($stmt, $params);

            if ($stmt->execute()) {
                echo json_encode(["success" => true, "message" => "Dietary habits saved successfully"]);
            } else {
                throw new Exception("Failed to save dietary habits: " . $stmt->error);
            }
            $stmt->close();
            exit;
        }

        // ========================
        // SAVE MEMBERSHIP INFO
        // ========================
        if ($action === "save_membership") {
            // Check if record exists
            $check = $conn->prepare("SELECT 1 FROM patient_other_info WHERE patient_id=?");
            $check->bind_param("i", $patientId);
            $check->execute();

            if (!$check->get_result()->fetch_assoc()) {
                $ins = $conn->prepare("INSERT INTO patient_other_info (patient_id) VALUES (?)");
                $ins->bind_param("i", $patientId);
                $ins->execute();
                $ins->close();
            }
            $check->close();

            $sql = "UPDATE patient_other_info SET
                nhts_pr=?, 
                four_ps=?, 
                indigenous_people=?, 
                pwd=?, 
                philhealth_flag=?, 
                philhealth_number=?, 
                sss_flag=?, 
                sss_number=?, 
                gsis_flag=?, 
                gsis_number=?
            WHERE patient_id=?";

            $stmt = $conn->prepare($sql);

            $params = [
                (int)!empty($_POST['nhts_pr']),
                (int)!empty($_POST['four_ps']),
                (int)!empty($_POST['indigenous_people']),
                (int)!empty($_POST['pwd']),
                (int)!empty($_POST['philhealth_flag']),
                !empty($_POST['philhealth_number']) ? sanitizeInput($_POST['philhealth_number']) : null,
                (int)!empty($_POST['sss_flag']),
                !empty($_POST['sss_number']) ? sanitizeInput($_POST['sss_number']) : null,
                (int)!empty($_POST['gsis_flag']),
                !empty($_POST['gsis_number']) ? sanitizeInput($_POST['gsis_number']) : null,
                $patientId
            ];

            bind_params_dynamic($stmt, $params);

            if ($stmt->execute()) {
                echo json_encode(["success" => true, "message" => "Membership info saved successfully"]);
            } else {
                throw new Exception("Failed to save membership info: " . $stmt->error);
            }
            $stmt->close();
            exit;
        }

        // ========================
        // UPDATE PATIENT INFO
        // ========================
        if ($action === "save_patient") {
            // Validate required fields
            $required = ['firstname', 'surname', 'date_of_birth', 'sex', 'age'];
            foreach ($required as $field) {
                if (empty($_POST[$field])) {
                    http_response_code(400);
                    exit(json_encode(["error" => "Missing required field: $field"]));
                }
            }

            // Sanitize inputs
            $firstname = sanitizeInput($_POST['firstname']);
            $surname = sanitizeInput($_POST['surname']);
            $middlename = !empty($_POST['middlename']) ? sanitizeInput($_POST['middlename']) : null;
            $dateOfBirth = validateDate($_POST['date_of_birth']) ? $_POST['date_of_birth'] : null;
            $sex = in_array($_POST['sex'], ['Male', 'Female', 'Other']) ? $_POST['sex'] : null;
            $age = validateInteger($_POST['age'], 0, 150);
            $placeOfBirth = !empty($_POST['place_of_birth']) ? sanitizeInput($_POST['place_of_birth']) : null;
            $address = !empty($_POST['address']) ? sanitizeInput($_POST['address']) : null;
            $occupation = !empty($_POST['occupation']) ? sanitizeInput($_POST['occupation']) : null;
            $guardian = !empty($_POST['guardian']) ? sanitizeInput($_POST['guardian']) : null;

            if (!$dateOfBirth || !$sex || $age === false) {
                http_response_code(400);
                exit(json_encode(["error" => "Invalid input values"]));
            }

            $stmt = $conn->prepare("UPDATE patients SET 
                firstname=?, surname=?, middlename=?, date_of_birth=?, sex=?, age=?, 
                place_of_birth=?, address=?, occupation=?, guardian=?, updated_at=NOW()
                WHERE patient_id=? AND archived=0");

            $stmt->bind_param(
                "sssssissssi",
                $firstname,
                $surname,
                $middlename,
                $dateOfBirth,
                $sex,
                $age,
                $placeOfBirth,
                $address,
                $occupation,
                $guardian,
                $patientId
            );

            if ($stmt->execute()) {
                echo json_encode([
                    "success" => true,
                    "message" => "Patient info updated successfully"
                ]);
            } else {
                throw new Exception("Failed to update patient: " . $stmt->error);
            }
            $stmt->close();
            exit;
        }
    } catch (Exception $e) {
        error_log("Database error: " . $e->getMessage());
        http_response_code(500);
        exit(json_encode(["error" => "Database error occurred"]));
    }
}

// If no action matched
http_response_code(400);
echo json_encode(["error" => "Invalid request or missing parameters"]);
exit;
