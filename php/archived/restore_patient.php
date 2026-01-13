<?php
// Start output buffering to catch any accidental output
ob_start();

// Start session to get logged user info
session_start();

ini_set('display_errors', 1);
error_reporting(E_ALL);
header("Content-Type: application/json");

// Initialize response array
$response = ["success" => false, "message" => ""];

try {
    // Database connection
    $host = "localhost";
    $user = "u401132124_dentalclinic";
    $pass = "Mho_DentalClinic1st";
    $db   = "u401132124_mho_dentalemr";

    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $conn = new mysqli($host, $user, $pass, $db);
    $conn->set_charset("utf8mb4");

    // Get logged user info from session or POST
    $loggedUser = null;
    $userId = isset($_GET['uid']) ? intval($_GET['uid']) : (isset($_POST['uid']) ? intval($_POST['uid']) : 0);

    if ($userId > 0 && isset($_SESSION['active_sessions'][$userId])) {
        $loggedUser = $_SESSION['active_sessions'][$userId];
    } else {
        // Try to get from POST data
        $loggedUser = [
            'id' => $userId,
            'type' => 'System',
            'name' => 'Unknown User'
        ];
    }

    // =====================================================
    // HISTORY LOGGING FUNCTION (EXACTLY LIKE WORKING VERSION)
    // =====================================================
    function addHistoryLog($conn, $tableName, $recordId, $action, $changedByType, $changedById, $oldValues = null, $newValues = null, $description = null)
    {
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

    // Get column names of a table
    function getColumns($conn, $table)
    {
        $cols = [];
        $res = $conn->query("SHOW COLUMNS FROM `$table`");
        while ($row = $res->fetch_assoc()) {
            $cols[] = $row['Field'];
        }
        return $cols;
    }

    // Restore a table from archive safely
    function restoreTable($conn, $main, $archived, $conditionCol, $conditionVal)
    {
        $mainCols = getColumns($conn, $main);
        $archCols = getColumns($conn, $archived);

        $common = array_intersect($mainCols, $archCols);
        if (empty($common)) {
            return 0;
        }

        $columns = implode(",", $common);

        // Insert from archived to main
        $sql = "INSERT INTO `$main` ($columns)
                SELECT $columns FROM `$archived` WHERE `$conditionCol` = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $conditionVal);
        $stmt->execute();
        $affected = $stmt->affected_rows;

        // Delete from archived
        $del = $conn->prepare("DELETE FROM `$archived` WHERE `$conditionCol` = ?");
        $del->bind_param("i", $conditionVal);
        $del->execute();

        return $affected;
    }

    // Restore visit-based tables
    function restoreVisitTables($conn, $patient_id)
    {
        $stmt = $conn->prepare("SELECT visit_id FROM visits WHERE patient_id = ?");
        $stmt->bind_param("i", $patient_id);
        $stmt->execute();
        $res = $stmt->get_result();

        $visit_ids = [];
        while ($r = $res->fetch_assoc()) {
            $visit_ids[] = $r['visit_id'];
        }

        if (empty($visit_ids)) {
            return;
        }

        foreach (['visittoothcondition', 'visittoothtreatment'] as $table) {
            $mainCols = getColumns($conn, $table);
            $archCols = getColumns($conn, "archived_$table");
            $common = array_intersect($mainCols, $archCols);

            if (empty($common)) {
                continue;
            }

            $columns = implode(",", $common);
            $placeholders = implode(',', array_fill(0, count($visit_ids), '?'));
            $types = str_repeat('i', count($visit_ids));

            // Insert from archived
            $stmtInsert = $conn->prepare(
                "INSERT INTO `$table` ($columns)
                 SELECT $columns FROM `archived_$table` WHERE visit_id IN ($placeholders)"
            );
            $stmtInsert->bind_param($types, ...$visit_ids);
            $stmtInsert->execute();

            // Delete from archived
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

        // Validate patient exists in archived
        $checkStmt = $conn->prepare("SELECT COUNT(*) as count FROM archived_patients WHERE patient_id = ?");
        $checkStmt->bind_param("i", $patient_id);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result()->fetch_assoc();

        if ($checkResult['count'] == 0) {
            $response["message"] = "Patient not found in archived records.";
            echo json_encode($response);
            $conn->close();
            ob_end_flush();
            exit;
        }

        // Get patient info for logging BEFORE transaction starts
        $patientInfoStmt = $conn->prepare("SELECT firstname, middlename, surname FROM archived_patients WHERE patient_id = ?");
        $patientInfoStmt->bind_param("i", $patient_id);
        $patientInfoStmt->execute();
        $patientInfoResult = $patientInfoStmt->get_result()->fetch_assoc();
        $patientName = 'Unknown';
        if ($patientInfoResult) {
            $patientName = trim(($patientInfoResult['firstname'] ?? '') . ' ' .
                ($patientInfoResult['middlename'] ?? '') . ' ' .
                ($patientInfoResult['surname'] ?? ''));
            $patientName = $patientName ?: 'Unknown Patient';
        }
        $patientInfoStmt->close();

        $conn->begin_transaction();

        try {
            $totalRestored = 0;

            // Restore main patient record
            $restored = restoreTable($conn, "patients", "archived_patients", "patient_id", $patient_id);
            $totalRestored += $restored;

            if ($restored === 0) {
                throw new Exception("Failed to restore patient record.");
            }

            // Restore related tables
            $relatedTables = [
                "oral_health_condition",
                "patient_treatment_record",
                "services_monitoring_chart",
                "dietary_habits",
                "medical_history",
                "vital_signs",
                "visits",
                "patient_other_info"
            ];

            foreach ($relatedTables as $table) {
                $restored = restoreTable($conn, $table, "archived_$table", "patient_id", $patient_id);
                $totalRestored += $restored;
            }

            // Restore visit-based tables
            restoreVisitTables($conn, $patient_id);

            $conn->commit();

            // Log the restore action AFTER successful commit
            if ($loggedUser) {
                $description = "Restored patient: " . $patientName . " (ID: {$patient_id}) with " . $totalRestored . " related records.";

                addHistoryLog(
                    $conn,
                    "patients",
                    $patient_id,
                    "RESTORE",
                    $loggedUser['type'] ?? 'System',
                    $loggedUser['id'] ?? 0,
                    null, // No old values for restore
                    ['restored_from_archive' => true, 'records_restored' => $totalRestored],
                    $description
                );
            }

            $response["success"] = true;
            $response["message"] = "Patient and all related records ($totalRestored items) restored successfully.";
        } catch (Exception $e) {
            $conn->rollback();
            $response["message"] = "Restore failed: " . $e->getMessage();
            error_log("Restore error for patient $patient_id: " . $e->getMessage());
        }

        echo json_encode($response);
        $conn->close();
        ob_end_flush();
        exit;
    } else {
        $response["message"] = "Invalid request. No patient ID provided.";
        echo json_encode($response);
        $conn->close();
        ob_end_flush();
        exit;
    }
} catch (Exception $e) {
    // Clear any output buffers
    while (ob_get_level()) {
        ob_end_clean();
    }

    $response["message"] = "Database connection failed: " . $e->getMessage();
    echo json_encode($response);
    exit;
}
