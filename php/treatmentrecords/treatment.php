<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
header("Content-Type: application/json");

$host = "localhost";
$user = "root";
$pass = "";
$db   = "dentalemr_system";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli($host, $user, $pass, $db);
    $conn->set_charset("utf8mb4");
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "DB connection failed: " . $e->getMessage()]);
    exit;
}

/* =====================================================
   ARCHIVE REQUEST
===================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['archive_id'])) {
    $patient_id = intval($_POST['archive_id']);
    $conn->begin_transaction();

    try {
        // 1️⃣ Fetch patient
        $stmt = $conn->prepare("SELECT * FROM patients WHERE patient_id = ?");
        $stmt->bind_param("i", $patient_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows === 0) {
            throw new Exception("Patient not found.");
        }
        $patient = $result->fetch_assoc();
        $original_json = json_encode($patient, JSON_UNESCAPED_UNICODE);

        // 2️⃣ Archive patient
        $insert_sql = "
            INSERT INTO archived_patients
            (patient_id, firstname, middlename, surname, date_of_birth, place_of_birth,
             age, sex, address, pregnant, occupation, guardian, original_data, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ";
        $insert_stmt = $conn->prepare($insert_sql);
        $insert_stmt->bind_param(
            "isssssississs",
            $patient['patient_id'],
            $patient['firstname'],
            $patient['middlename'],
            $patient['surname'],
            $patient['date_of_birth'],
            $patient['place_of_birth'],
            $patient['age'],
            $patient['sex'],
            $patient['address'],
            $patient['pregnant'],
            $patient['occupation'],
            $patient['guardian'],
            $original_json
        );
        $insert_stmt->execute();

        // 3️⃣ Get all visit IDs for this patient
        $visit_ids = [];
        $res = $conn->prepare("SELECT visit_id FROM visits WHERE patient_id = ?");
        $res->bind_param("i", $patient_id);
        $res->execute();
        $r = $res->get_result();
        while ($row = $r->fetch_assoc()) {
            $visit_ids[] = $row['visit_id'];
        }

        // 4️⃣ Archive related tables that have patient_id
        $relatedTables = [
            'visits',
            'oral_health_condition',
            'patient_treatment_record',
            'services_monitoring_chart',
            'dietary_habits',
            'medical_history',
            'vital_signs'
        ];

        foreach ($relatedTables as $table) {
            $archive_table = "archived_" . $table;
            $cols_source = [];
            $cols_archive = [];

            $res1 = $conn->query("SHOW COLUMNS FROM `$table`");
            while ($r = $res1->fetch_assoc()) $cols_source[] = $r['Field'];

            $res2 = $conn->query("SHOW COLUMNS FROM `$archive_table`");
            while ($r = $res2->fetch_assoc()) $cols_archive[] = $r['Field'];

            $common_cols = array_intersect($cols_source, $cols_archive);
            if (empty($common_cols)) continue;

            $col_list = "`" . implode("`, `", $common_cols) . "`";

            if (!in_array('patient_id', $cols_source)) continue;

            $copy_sql = "INSERT INTO `$archive_table` ($col_list)
                         SELECT $col_list FROM `$table` WHERE patient_id = ?";
            $copy_stmt = $conn->prepare($copy_sql);
            $copy_stmt->bind_param("i", $patient_id);
            $copy_stmt->execute();
        }

        // 5️⃣ Archive visit-based tables (tooth treatment & condition)
        if (!empty($visit_ids)) {
            $visit_ids_list = implode(",", $visit_ids);

            $visit_based_tables = [
                'visittoothcondition',
                'visittoothtreatment'
            ];

            foreach ($visit_based_tables as $table) {
                $archive_table = "archived_" . $table;
                $cols_source = [];
                $cols_archive = [];

                $res1 = $conn->query("SHOW COLUMNS FROM `$table`");
                while ($r = $res1->fetch_assoc()) $cols_source[] = $r['Field'];

                $res2 = $conn->query("SHOW COLUMNS FROM `$archive_table`");
                while ($r = $res2->fetch_assoc()) $cols_archive[] = $r['Field'];

                $common_cols = array_intersect($cols_source, $cols_archive);
                if (empty($common_cols)) continue;

                $col_list = "`" . implode("`, `", $common_cols) . "`";

                // copy all rows linked to patient's visits
                $copy_sql = "INSERT INTO `$archive_table` ($col_list)
                             SELECT $col_list FROM `$table`
                             WHERE visit_id IN ($visit_ids_list)";
                $conn->query($copy_sql);
            }
        }

        // 6️⃣ Delete original records
        $deleteOrder = [
            'visittoothtreatment',
            'visittoothcondition',
            'visits',
            'oral_health_condition',
            'patient_treatment_record',
            'services_monitoring_chart',
            'dietary_habits',
            'medical_history',
            'vital_signs',
            'patients'
        ];

        foreach ($deleteOrder as $table) {
            $res = $conn->query("SHOW COLUMNS FROM `$table`");
            $has_pid = false;
            $has_vid = false;
            while ($r = $res->fetch_assoc()) {
                if ($r['Field'] === 'patient_id') $has_pid = true;
                if ($r['Field'] === 'visit_id') $has_vid = true;
            }

            if ($has_pid) {
                $stmt = $conn->prepare("DELETE FROM `$table` WHERE patient_id = ?");
                $stmt->bind_param("i", $patient_id);
                $stmt->execute();
            } elseif ($has_vid && !empty($visit_ids)) {
                $visit_ids_list = implode(",", $visit_ids);
                $conn->query("DELETE FROM `$table` WHERE visit_id IN ($visit_ids_list)");
            }
        }

        $conn->commit();
        echo json_encode(["success" => true, "message" => "Patient and related data archived successfully."]);
    } catch (Exception $e) {
        $conn->rollback();
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Archive failed: " . $e->getMessage()]);
    }

    $conn->close();
    exit;
}

/* =====================================================
   FETCH ACTIVE PATIENTS THAT HAVE VISITS
===================================================== */
try {
    $sql = "
        SELECT 
    p.patient_id,
    CONCAT_WS(' ', p.firstname, p.middlename, p.surname) AS fullname,
    p.sex,
    p.age,
    p.address,
    MAX(v.visit_date) AS last_visit
    FROM patients p
    INNER JOIN visits v ON p.patient_id = v.patient_id
    GROUP BY p.patient_id
    ORDER BY p.patient_id ASC

    ";
    $result = $conn->query($sql);
    $patients = [];
    while ($row = $result->fetch_assoc()) {
        $patients[] = $row;
    }
    echo json_encode($patients);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Fetch failed: " . $e->getMessage()]);
}

$conn->close();
