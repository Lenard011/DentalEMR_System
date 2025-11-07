<?php
ini_set('display_errors', 1);
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

// Function to get column names of a table
function getColumns($conn, $table)
{
    $cols = [];
    $res = $conn->query("SHOW COLUMNS FROM `$table`");
    while ($row = $res->fetch_assoc()) {
        $cols[] = $row['Field'];
    }
    return $cols;
}

// Function to safely restore records between archive and main table
function restoreTable($conn, $main, $archived, $conditionCol, $conditionVal)
{
    $mainCols = getColumns($conn, $main);
    $archCols = getColumns($conn, $archived);

    // Find common columns between both tables
    $common = array_intersect($mainCols, $archCols);
    if (empty($common)) return;

    $columns = implode(",", $common);
    $sql = "INSERT INTO `$main` ($columns) SELECT $columns FROM `$archived` WHERE `$conditionCol` = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $conditionVal);
    $stmt->execute();

    $del = $conn->prepare("DELETE FROM `$archived` WHERE `$conditionCol` = ?");
    $del->bind_param("i", $conditionVal);
    $del->execute();
}

/* =====================================================
   RESTORE PATIENT AND RELATED RECORDS
===================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['restore_id'])) {
    $patient_id = intval($_POST['restore_id']);
    $conn->begin_transaction();

    try {
        // Restore patient
        restoreTable($conn, "patients", "archived_patients", "patient_id", $patient_id);

        // Restore related patient_id tables
        $relatedTables = [
            "oral_health_condition",
            "patient_treatment_record",
            "services_monitoring_chart",
            "dietary_habits",
            "medical_history",
            "vital_signs",
            "visits"
        ];

        foreach ($relatedTables as $table) {
            restoreTable($conn, $table, "archived_$table", "patient_id", $patient_id);
        }

        // Get all restored visit IDs
        $visit_ids = [];
        $stmt = $conn->prepare("SELECT visit_id FROM visits WHERE patient_id = ?");
        $stmt->bind_param("i", $patient_id);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) {
            $visit_ids[] = $r['visit_id'];
        }

        // Restore visit-based tables dynamically
        if (!empty($visit_ids)) {
            foreach (["visittoothcondition", "visittoothtreatment"] as $table) {
                $mainCols = getColumns($conn, $table);
                $archCols = getColumns($conn, "archived_$table");
                $common = array_intersect($mainCols, $archCols);
                if (empty($common)) continue;

                $columns = implode(",", $common);
                $ids = implode(",", $visit_ids);
                $conn->query("
                    INSERT INTO `$table` ($columns)
                    SELECT $columns FROM `archived_$table` WHERE visit_id IN ($ids)
                ");
                $conn->query("DELETE FROM `archived_$table` WHERE visit_id IN ($ids)");
            }
        }

        // Delete restored patient from archive
        $del = $conn->prepare("DELETE FROM archived_patients WHERE patient_id = ?");
        $del->bind_param("i", $patient_id);
        $del->execute();

        $conn->commit();
        echo json_encode(["success" => true, "message" => "Patient and related records restored successfully."]);
    } catch (Exception $e) {
        $conn->rollback();
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Restore failed: " . $e->getMessage()]);
    }

    $conn->close();
    exit;
}

echo json_encode(["success" => false, "message" => "Invalid request."]);
$conn->close();
