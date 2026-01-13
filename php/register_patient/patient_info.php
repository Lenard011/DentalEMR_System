<?php
session_start();
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', 1);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

header('Content-Type: application/json');

// ============================================================
// DATABASE CONNECTION (IMPROVED)
// ============================================================
$conn = null;
try {
    require_once "./conn.php";
    
    // Verify connection is alive
    if (!$conn || !mysqli_ping($conn)) {
        throw new Exception("Database connection lost");
    }
} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => "Database connection failed: " . $e->getMessage()]);
    exit;
}

/* ============================================================
   LOAD LOGGED-IN USER FOR HISTORY LOG
============================================================ */
// Get logged user from session (preferred)
$loggedUser = $_SESSION['user']
    ?? $_SESSION['active_user']
    ?? null;

// Fallback: allow uid from GET only if it exists in session
if (!$loggedUser && isset($_GET['uid'])) {
    $uid = intval($_GET['uid']);
    if (isset($_SESSION['active_sessions'][$uid])) {
        $loggedUser = $_SESSION['active_sessions'][$uid];
    }
}

// Final fallback: safe defaults
if (!$loggedUser) {
    $loggedUser = [
        'id' => 0,
        'type' => 'unknown'
    ];
}

/* ============================================================
   HISTORY LOG FUNCTION (direct copy from your working code)
============================================================ */
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

/* ============================================================
   DYNAMIC BIND HELPER (IMPROVED)
============================================================ */
function bind_params_dynamic($stmt, $params)
{
    if (empty($params)) return;
    $types = '';
    foreach ($params as $param) {
        if (is_int($param)) {
            $types .= 'i';
        } elseif (is_float($param)) {
            $types .= 'd';
        } else {
            $types .= 's';
        }
    }
    
    $bind_params = [$types];
    foreach ($params as $k => $v) {
        $bind_params[] = &$params[$k];
    }
    call_user_func_array([$stmt, 'bind_param'], $bind_params);
}

/* ============================================================
   PROCESS ACTION
============================================================ */
$action = $_GET['action'] ?? $_POST['action'] ?? null;
if (!$action) {
    echo json_encode(["success" => false, "message" => "No action specified"]);
    exit;
}

try {
    // ============================================================
    // MEMBERSHIP
    // ============================================================
    if ($action === "get_membership") {
        $patient_id = intval($_GET['patient_id'] ?? 0);
        $stmt = $conn->prepare("SELECT * FROM patient_other_info WHERE patient_id = ?");
        $stmt->bind_param("i", $patient_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();

        $memberships = [];
        if ($row) {
            if ($row['nhts_pr']) $memberships[] = ["field" => "nhts_pr", "label" => "National Household Targeting System - Poverty Reduction (NHTS)"];
            if ($row['four_ps']) $memberships[] = ["field" => "four_ps", "label" => "Pantawid Pamilyang Pilipino Program (4Ps)"];
            if ($row['indigenous_people']) $memberships[] = ["field" => "indigenous_people", "label" => "Indigenous People (IP)"];
            if ($row['pwd']) $memberships[] = ["field" => "pwd", "label" => "Person with Disabilities (PWD)"];
            if ($row['philhealth_flag']) $memberships[] = ["field" => "philhealth_flag", "label" => "PhilHealth: (" . ($row['philhealth_number'] ?: "N/A") . ")"];
            if ($row['sss_flag']) $memberships[] = ["field" => "sss_flag", "label" => "SSS: (" . ($row['sss_number'] ?: "N/A") . ")"];
            if ($row['gsis_flag']) $memberships[] = ["field" => "gsis_flag", "label" => "GSIS: (" . ($row['gsis_number'] ?: "N/A") . ")"];
        }

        echo json_encode(["success" => true, "memberships" => $memberships, "values" => $row ?? []]);
        exit;
    }
    
    if ($action === "save_membership") {
        $patient_id = intval($_POST['patient_id']);

        // Fetch OLD DATA
        $old = $conn->prepare("SELECT * FROM patient_other_info WHERE patient_id=?");
        $old->bind_param("i", $patient_id);
        $old->execute();
        $oldData = $old->get_result()->fetch_assoc();

        // Ensure row exists
        if (!$oldData) {
            $ins = $conn->prepare("INSERT INTO patient_other_info (patient_id) VALUES (?)");
            $ins->bind_param("i", $patient_id);
            $ins->execute();
            $oldData = [];
        }

        // Build update
        $sql = "
        UPDATE patient_other_info SET
            nhts_pr=?, four_ps=?, indigenous_people=?, pwd=?,
            philhealth_flag=?, philhealth_number=?,
            sss_flag=?, sss_number=?,
            gsis_flag=?, gsis_number=?
        WHERE patient_id=?
    ";
        $stmt = $conn->prepare($sql);

        $params = [
            (string)($_POST['nhts_pr'] ?? 0),
            (string)($_POST['four_ps'] ?? 0),
            (string)($_POST['indigenous_people'] ?? 0),
            (string)($_POST['pwd'] ?? 0),
            (string)($_POST['philhealth_flag'] ?? 0),
            trim($_POST['philhealth_number'] ?? '') ?: null,
            (string)($_POST['sss_flag'] ?? 0),
            trim($_POST['sss_number'] ?? '') ?: null,
            (string)($_POST['gsis_flag'] ?? 0),
            trim($_POST['gsis_number'] ?? '') ?: null,
            (string)$patient_id
        ];

        bind_params_dynamic($stmt, $params);
        $stmt->execute();

        /* Compare old vs new for change log */
        $newData = [];
        foreach ($params as $i => $val) {
            // skip last param (patient_id)
            if ($i < 10) {
                $keys = [
                    "nhts_pr",
                    "four_ps",
                    "indigenous_people",
                    "pwd",
                    "philhealth_flag",
                    "philhealth_number",
                    "sss_flag",
                    "sss_number",
                    "gsis_flag",
                    "gsis_number"
                ];
                $newData[$keys[$i]] = $val;
            }
        }

        $changedOld = [];
        $changedNew = [];
        $justification = [];

        foreach ($newData as $col => $newVal) {
            $oldVal = $oldData[$col] ?? null;
            if ((string)$oldVal !== (string)$newVal) {
                $changedOld[$col] = $oldVal;
                $changedNew[$col] = $newVal;
                $justification[] = ucfirst(str_replace("_", " ", $col)) . " changed from \"$oldVal\" to \"$newVal\"";
            }
        }

        if (!empty($changedOld)) {
            addHistoryLog(
                $conn,
                "patient_other_info",
                $patient_id,
                "UPDATE",
                $loggedUser['type'] ?? "unknown",
                $loggedUser['id'] ?? 0,
                $changedOld,
                $changedNew,
                implode("; ", $justification)
            );
        }

        echo json_encode(["success" => true]);
        exit;
    }

    /* ============================================================
   MEDICAL
============================================================ */
    if ($action === "get_medical") {
        $patient_id = intval($_GET['patient_id'] ?? 0);
        $stmt = $conn->prepare("SELECT * FROM medical_history WHERE patient_id = ?");
        $stmt->bind_param("i", $patient_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();

        $medical = [];
        if ($row) {
            if ($row['allergies_flag']) $medical[] = ["field" => "allergies_flag", "label" => "Allergies (" . ($row['allergies_details'] ?: "N/A") . ")"];
            if ($row['hypertension_cva']) $medical[] = ["field" => "hypertension_cva", "label" => "Hypertension / CVA"];
            if ($row['diabetes_mellitus']) $medical[] = ["field" => "diabetes_mellitus", "label" => "Diabetes Mellitus"];
            if ($row['blood_disorders']) $medical[] = ["field" => "blood_disorders", "label" => "Blood Disorders"];
            if ($row['heart_disease']) $medical[] = ["field" => "heart_disease", "label" => "Cardiovascular / Heart Disease"];
            if ($row['thyroid_disorders']) $medical[] = ["field" => "thyroid_disorders", "label" => "Thyroid Disorders"];
            if ($row['hepatitis_flag']) $medical[] = ["field" => "hepatitis_flag", "label" => "Hepatitis (" . ($row['hepatitis_details'] ?: "N/A") . ")"];
            if ($row['malignancy_flag']) $medical[] = ["field" => "malignancy_flag", "label" => "Malignancy (" . ($row['malignancy_details'] ?: "N/A") . ")"];
            if ($row['prev_hospitalization_flag']) $medical[] = ["field" => "prev_hospitalization_flag", "label" => "Previous Hospitalization (" . ($row['last_admission_date'] ?: "N/A") . " - " . ($row['admission_cause'] ?: "N/A") . ")"];
            if ($row['surgery_details']) $medical[] = ["field" => "surgery_details", "label" => "Surgery (" . $row['surgery_details'] . ")"];
            if ($row['blood_transfusion_flag']) $medical[] = ["field" => "blood_transfusion_flag", "label" => "Blood Transfusion (" . ($row['blood_transfusion'] ?: "N/A") . ")"];
            if ($row['tattoo']) $medical[] = ["field" => "tattoo", "label" => "Tattoo"];
            if ($row['other_conditions_flag']) $medical[] = ["field" => "other_conditions_flag", "label" => "Others (" . ($row['other_conditions'] ?: "N/A") . ")"];
        }

        echo json_encode(["success" => true, "medical" => $medical, "values" => $row ?? []]);
        exit;
    }
    
    if ($action === "save_medical") {
        $patient_id = intval($_POST['patient_id']);

        // OLD DATA
        $old = $conn->prepare("SELECT * FROM medical_history WHERE patient_id=?");
        $old->bind_param("i", $patient_id);
        $old->execute();
        $oldData = $old->get_result()->fetch_assoc();

        if (!$oldData) {
            $ins = $conn->prepare("INSERT INTO medical_history (patient_id) VALUES (?)");
            $ins->bind_param("i", $patient_id);
            $ins->execute();
            $oldData = [];
        }

        $sql = "
        UPDATE medical_history SET
            allergies_flag=?, allergies_details=?,
            hypertension_cva=?, diabetes_mellitus=?, blood_disorders=?, heart_disease=?, thyroid_disorders=?,
            hepatitis_flag=?, hepatitis_details=?,
            malignancy_flag=?, malignancy_details=?,
            prev_hospitalization_flag=?, last_admission_date=?, admission_cause=?,
            surgery_details=?,
            blood_transfusion_flag=?, blood_transfusion=?,
            tattoo=?, other_conditions_flag=?, other_conditions=?
        WHERE patient_id=?
    ";
        $stmt = $conn->prepare($sql);

        $params = [
            (string)($_POST['allergies_flag'] ?? 0),
            trim($_POST['allergies_details'] ?? '') ?: null,
            (string)($_POST['hypertension_cva'] ?? 0),
            (string)($_POST['diabetes_mellitus'] ?? 0),
            (string)($_POST['blood_disorders'] ?? 0),
            (string)($_POST['heart_disease'] ?? 0),
            (string)($_POST['thyroid_disorders'] ?? 0),
            (string)($_POST['hepatitis_flag'] ?? 0),
            trim($_POST['hepatitis_details'] ?? '') ?: null,
            (string)($_POST['malignancy_flag'] ?? 0),
            trim($_POST['malignancy_details'] ?? '') ?: null,
            (string)($_POST['prev_hospitalization_flag'] ?? 0),
            trim($_POST['last_admission_date'] ?? '') ?: null,
            trim($_POST['admission_cause'] ?? '') ?: null,
            trim($_POST['surgery_details'] ?? '') ?: null,
            (string)($_POST['blood_transfusion_flag'] ?? 0),
            trim($_POST['blood_transfusion'] ?? '') ?: null,
            (string)($_POST['tattoo'] ?? 0),
            (string)($_POST['other_conditions_flag'] ?? 0),
            trim($_POST['other_conditions'] ?? '') ?: null,
            (string)$patient_id
        ];

        bind_params_dynamic($stmt, $params);
        $stmt->execute();

        /* COMPARE & LOG */
        $colNames = [
            "allergies_flag",
            "allergies_details",
            "hypertension_cva",
            "diabetes_mellitus",
            "blood_disorders",
            "heart_disease",
            "thyroid_disorders",
            "hepatitis_flag",
            "hepatitis_details",
            "malignancy_flag",
            "malignancy_details",
            "prev_hospitalization_flag",
            "last_admission_date",
            "admission_cause",
            "surgery_details",
            "blood_transfusion_flag",
            "blood_transfusion",
            "tattoo",
            "other_conditions_flag",
            "other_conditions"
        ];

        $newDataAssoc = array_combine($colNames, array_slice($params, 0, 20));

        $changedOld = [];
        $changedNew = [];
        $justification = [];

        foreach ($newDataAssoc as $col => $newVal) {
            $oldVal = $oldData[$col] ?? null;
            if ((string)$oldVal !== (string)$newVal) {
                $changedOld[$col] = $oldVal;
                $changedNew[$col] = $newVal;
                $justification[] = ucfirst(str_replace("_", " ", $col)) . " changed from \"$oldVal\" to \"$newVal\"";
            }
        }

        if (!empty($changedOld)) {
            addHistoryLog(
                $conn,
                "medical_history",
                $patient_id,
                "UPDATE",
                $loggedUser['type'] ?? "unknown",
                $loggedUser['id'] ?? 0,
                $changedOld,
                $changedNew,
                implode("; ", $justification)
            );
        }

        echo json_encode(["success" => true]);
        exit;
    }

    /* ============================================================
   DIETARY
============================================================ */
    if ($action === "get_dietary") {
        $patient_id = intval($_GET['patient_id'] ?? 0);
        $stmt = $conn->prepare("SELECT * FROM dietary_habits WHERE patient_id = ? LIMIT 1");
        $stmt->bind_param("i", $patient_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();

        $dietary = [];
        if ($row) {
            if ($row['sugar_flag']) $dietary[] = ["field" => "sugar_flag", "label" => "Sugar Sweetened Beverages/Food (" . ($row['sugar_details'] ?: "N/A") . ")"];
            if ($row['alcohol_flag']) $dietary[] = ["field" => "alcohol_flag", "label" => "Use of Alcohol (" . ($row['alcohol_details'] ?: "N/A") . ")"];
            if ($row['tobacco_flag']) $dietary[] = ["field" => "tobacco_flag", "label" => "Use of Tobacco (" . ($row['tobacco_details'] ?: "N/A") . ")"];
            if ($row['betel_nut_flag']) $dietary[] = ["field" => "betel_nut_flag", "label" => "Betel Nut Chewing (" . ($row['betel_nut_details'] ?: "N/A") . ")"];
        }

        echo json_encode(["success" => true, "dietary" => $dietary, "values" => $row ?? []]);
        exit;
    }
    
    if ($action === "save_dietary") {
        $patient_id = intval($_POST['patient_id']);

        // OLD
        $old = $conn->prepare("SELECT * FROM dietary_habits WHERE patient_id=?");
        $old->bind_param("i", $patient_id);
        $old->execute();
        $oldData = $old->get_result()->fetch_assoc();

        if (!$oldData) {
            $ins = $conn->prepare("INSERT INTO dietary_habits (patient_id) VALUES (?)");
            $ins->bind_param("i", $patient_id);
            $ins->execute();
            $oldData = [];
        }

        $sql = "
        UPDATE dietary_habits SET
            sugar_flag=?, sugar_details=?,
            alcohol_flag=?, alcohol_details=?,
            tobacco_flag=?, tobacco_details=?,
            betel_nut_flag=?, betel_nut_details=?
        WHERE patient_id=?
    ";

        $stmt = $conn->prepare($sql);

        $params = [
            (string)($_POST['sugar_flag'] ?? 0),
            trim($_POST['sugar_details'] ?? '') ?: null,
            (string)($_POST['alcohol_flag'] ?? 0),
            trim($_POST['alcohol_details'] ?? '') ?: null,
            (string)($_POST['tobacco_flag'] ?? 0),
            trim($_POST['tobacco_details'] ?? '') ?: null,
            (string)($_POST['betel_nut_flag'] ?? 0),
            trim($_POST['betel_nut_details'] ?? '') ?: null,
            (string)$patient_id
        ];

        bind_params_dynamic($stmt, $params);
        $stmt->execute();

        $colNames = [
            "sugar_flag",
            "sugar_details",
            "alcohol_flag",
            "alcohol_details",
            "tobacco_flag",
            "tobacco_details",
            "betel_nut_flag",
            "betel_nut_details"
        ];
        $newData = array_combine($colNames, array_slice($params, 0, 8));

        $changedOld = [];
        $changedNew = [];
        $justification = [];

        foreach ($newData as $col => $newVal) {
            $oldVal = $oldData[$col] ?? null;
            if ((string)$oldVal !== (string)$newVal) {
                $changedOld[$col] = $oldVal;
                $changedNew[$col] = $newVal;
                $justification[] = ucfirst(str_replace("_", " ", $col)) . " changed from \"$oldVal\" to \"$newVal\"";
            }
        }

        if (!empty($changedOld)) {
            addHistoryLog(
                $conn,
                "dietary_habits",
                $patient_id,
                "UPDATE",
                $loggedUser['type'] ?? "unknown",
                $loggedUser['id'] ?? 0,
                $changedOld,
                $changedNew,
                implode("; ", $justification)
            );
        }

        echo json_encode(["success" => true]);
        exit;
    }

    /* ============================================================
   VITAL SIGNS
============================================================ */
    if ($action === "get_vitals") {
        $patient_id = intval($_GET['patient_id'] ?? 0);
        $stmt = $conn->prepare("SELECT 
                vital_id, 
                blood_pressure, 
                pulse_rate, 
                temperature, 
                weight, 
                DATE(recorded_at) AS recorded_at
              FROM vital_signs
              WHERE patient_id = ?
              ORDER BY recorded_at DESC");
        $stmt->bind_param("i", $patient_id);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        echo json_encode(["success" => true, "vitals" => $rows]);
        exit;
    }
    
    if ($action === "save_vitals") {
        $patient_id = intval($_POST['patient_id']);
        $blood_pressure = $_POST['blood_pressure'] ?? '';
        $pulse_rate = floatval($_POST['pulse_rate'] ?? 0);
        $temperature = floatval($_POST['temperature'] ?? 0);
        $weight = floatval($_POST['weight'] ?? 0);

        $sql = "INSERT INTO vital_signs (patient_id, blood_pressure, pulse_rate, temperature, weight, recorded_at)
            VALUES (?, ?, ?, ?, ?, NOW())";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("isddd", $patient_id, $blood_pressure, $pulse_rate, $temperature, $weight);
        $stmt->execute();

        addHistoryLog(
            $conn,
            "vital_signs",
            $stmt->insert_id,
            "INSERT",
            $loggedUser['type'] ?? "unknown",
            $loggedUser['id'] ?? 0,
            null,
            [
                "patient_id" => $patient_id,
                "blood_pressure" => $blood_pressure,
                "pulse_rate" => $pulse_rate,
                "temperature" => $temperature,
                "weight" => $weight
            ],
            "New vitals recorded"
        );

        echo json_encode(["success" => true, "vital_id" => $stmt->insert_id]);
        exit;
    }

    echo json_encode(["success" => false, "message" => "Unknown action"]);
} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}