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

// Function to get columns of a table
function getColumns($conn, $table)
{
    $cols = [];
    $res = $conn->query("SHOW COLUMNS FROM `$table`");
    while ($row = $res->fetch_assoc()) {
        $cols[] = $row['Field'];
    }
    return $cols;
}

// Function to archive a table without modifying created_at/updated_at
function archiveTable($conn, $table, $archive_table, $conditionCol, $conditionVal)
{
    $mainCols = getColumns($conn, $table);
    $archCols = getColumns($conn, $archive_table);

    // Preserve only common columns
    $common = array_intersect($mainCols, $archCols);
    if (empty($common)) return;

    $columns = implode(",", $common);

    // Add archived_at column if exists in archive
    $archColsExtra = in_array('archived_at', $archCols) ? ', NOW() as archived_at' : '';

    $sql = "INSERT INTO `$archive_table` ($columns" . (in_array('archived_at', $archCols) ? ',archived_at' : '') . ")
            SELECT $columns $archColsExtra FROM `$table` WHERE `$conditionCol` = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $conditionVal);
    $stmt->execute();
}

/* =====================================================
   ARCHIVE PATIENT AND RELATED RECORDS
===================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['archive_id'])) {
    $patient_id = intval($_POST['archive_id']);
    $conn->begin_transaction();

    try {
        // Archive main patient record
        archiveTable($conn, "patients", "archived_patients", "patient_id", $patient_id);

        // Get all visit IDs
        $visit_ids = [];
        $stmt = $conn->prepare("SELECT visit_id FROM visits WHERE patient_id = ?");
        $stmt->bind_param("i", $patient_id);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) $visit_ids[] = $r['visit_id'];

        // Related tables with patient_id
        $relatedTables = [
            'visits',
            'oral_health_condition',
            'patient_treatment_record',
            'services_monitoring_chart',
            'dietary_habits',
            'medical_history',
            'vital_signs',
            'patient_other_info'
        ];
        foreach ($relatedTables as $table) {
            archiveTable($conn, $table, "archived_$table", "patient_id", $patient_id);
        }

        // Visit-based tables
        if (!empty($visit_ids)) {
            foreach (['visittoothcondition', 'visittoothtreatment'] as $table) {
                $ids_placeholders = implode(",", $visit_ids);
                $mainCols = getColumns($conn, $table);
                $archCols = getColumns($conn, "archived_$table");
                $common = array_intersect($mainCols, $archCols);
                if (empty($common)) continue;

                $columns = implode(",", $common);
                $archColsExtra = in_array('archived_at', $archCols) ? ', NOW() as archived_at' : '';
                $conn->query("INSERT INTO `archived_$table` ($columns" . (in_array('archived_at', $archCols) ? ',archived_at' : '') . ")
                              SELECT $columns $archColsExtra FROM `$table` WHERE visit_id IN ($ids_placeholders)");
            }
        }

        // Delete original records (keeping created_at/updated_at unchanged in archive)
        $deleteOrder = [
            'visittoothtreatment',
            'visittoothcondition',
            'visits',
            'oral_health_condition',
            'patient_treatment_record',
            'services_monitoring_chart',
            'dietary_habits',
            'medical_history',
            'patient_other_info',
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
                $ids_list = implode(",", $visit_ids);
                $conn->query("DELETE FROM `$table` WHERE visit_id IN ($ids_list)");
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
    while ($row = $result->fetch_assoc()) $patients[] = $row;
    echo json_encode($patients);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Fetch failed: " . $e->getMessage()]);
}

$conn->close();
