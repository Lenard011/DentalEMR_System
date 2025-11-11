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

// Get column names of a table
function getColumns($conn, $table)
{
    $cols = [];
    $res = $conn->query("SHOW COLUMNS FROM `$table`");
    while ($row = $res->fetch_assoc()) $cols[] = $row['Field'];
    return $cols;
}

// Restore a table from archive safely
function restoreTable($conn, $main, $archived, $conditionCol, $conditionVal)
{
    $mainCols = getColumns($conn, $main);
    $archCols = getColumns($conn, $archived);

    $common = array_intersect($mainCols, $archCols);
    if (empty($common)) return;

    $columns = implode(",", $common);

    $sql = "INSERT INTO `$main` ($columns)
            SELECT $columns FROM `$archived` WHERE `$conditionCol` = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $conditionVal);
    $stmt->execute();

    $del = $conn->prepare("DELETE FROM `$archived` WHERE `$conditionCol` = ?");
    $del->bind_param("i", $conditionVal);
    $del->execute();
}

// Restore visit-based tables
function restoreVisitTables($conn, $patient_id)
{
    $stmt = $conn->prepare("SELECT visit_id FROM visits WHERE patient_id = ?");
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $res = $stmt->get_result();

    $visit_ids = [];
    while ($r = $res->fetch_assoc()) $visit_ids[] = $r['visit_id'];
    if (empty($visit_ids)) return;

    foreach (['visittoothcondition', 'visittoothtreatment'] as $table) {
        $mainCols = getColumns($conn, $table);
        $archCols = getColumns($conn, "archived_$table");
        $common = array_intersect($mainCols, $archCols);
        if (empty($common)) continue;

        $columns = implode(",", $common);
        $placeholders = implode(',', array_fill(0, count($visit_ids), '?'));
        $types = str_repeat('i', count($visit_ids));

        $stmtInsert = $conn->prepare(
            "INSERT INTO `$table` ($columns)
             SELECT $columns FROM `archived_$table` WHERE visit_id IN ($placeholders)"
        );
        $stmtInsert->bind_param($types, ...$visit_ids);
        $stmtInsert->execute();

        $stmtDelete = $conn->prepare(
            "DELETE FROM `archived_$table` WHERE visit_id IN ($placeholders)"
        );
        $stmtDelete->bind_param($types, ...$visit_ids);
        $stmtDelete->execute();
    }
}

/* =====================================================
   RESTORE PATIENT AND RELATED RECORDS
===================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['restore_id'])) {
    $patient_id = intval($_POST['restore_id']);
    $conn->begin_transaction();

    try {
        // Restore main patient record
        restoreTable($conn, "patients", "archived_patients", "patient_id", $patient_id);

        // Restore related tables
        $relatedTables = [
            "oral_health_condition",
            "patient_treatment_record",   // preserves created_at & updated_at
            "services_monitoring_chart",
            "dietary_habits",
            "medical_history",
            "vital_signs",
            "visits",
            "patient_other_info"
        ];
        foreach ($relatedTables as $table) {
            restoreTable($conn, $table, "archived_$table", "patient_id", $patient_id);
        }

        // Restore visit-based tables
        restoreVisitTables($conn, $patient_id);

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
