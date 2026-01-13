<?php
session_start();
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Set error handling
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

header("Content-Type: application/json");

// Add performance headers
header("Cache-Control: max-age=0, no-cache, must-revalidate, proxy-revalidate");
header("Pragma: no-cache");

$host = "localhost";
$user = "u401132124_dentalclinic";
$pass = "Mho_DentalClinic1st";
$db   = "u401132124_mho_dentalemr";

try {
    $conn = new mysqli($host, $user, $pass, $db);
    $conn->set_charset("utf8mb4");
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "DB connection failed: " . $e->getMessage()]);
    exit;
}

// =====================================================
// LOGGING FUNCTIONS (SIMPLIFIED TO MATCH WORKING VERSION)
// =====================================================

/**
 * Function to add history log - EXACTLY like working version
 */
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

// =====================================================
// PATIENT FUNCTIONS
// =====================================================

function getPatientById($conn, $patient_id)
{
    $stmt = $conn->prepare("SELECT patient_id, firstname, surname, middlename, date_of_birth, sex, age, place_of_birth, address, occupation, guardian FROM patients WHERE patient_id=? AND if_treatment=1");
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $patient = $result->fetch_assoc();
    $stmt->close();
    return $patient;
}

// Check if we're in offline sync mode and verify patient existence
function verifyPatientExists($conn, $patient_id)
{
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM patients WHERE patient_id = ?");
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    return $row['count'] > 0;
}

// Function to check if patient is already archived
function isPatientAlreadyArchived($conn, $patient_id)
{
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM archived_patients WHERE patient_id = ?");
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    return $row['count'] > 0;
}

// Enhanced fastArchivePatient with correct column mappings
function fastArchivePatient($conn, $patient_id, $loggedUser, $isOfflineSync = false)
{
    // Skip if patient doesn't exist (might have been archived already)
    if (!$isOfflineSync && !verifyPatientExists($conn, $patient_id)) {
        error_log("fastArchivePatient: Patient $patient_id does not exist");
        return false; // Patient doesn't exist
    }

    // Check if already archived (for offline sync)
    if ($isOfflineSync && isPatientAlreadyArchived($conn, $patient_id)) {
        error_log("fastArchivePatient: Patient $patient_id already archived");
        return true; // Already archived, skip
    }

    // Get patient data for logging before starting transaction
    $patientData = null;
    try {
        $stmt = $conn->prepare("SELECT patient_id, firstname, surname, middlename FROM patients WHERE patient_id = ?");
        $stmt->bind_param("i", $patient_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $patientData = $result->fetch_assoc();
        $stmt->close();

        if (!$patientData) {
            error_log("fastArchivePatient: Could not fetch patient data for ID $patient_id");
        }
    } catch (Exception $e) {
        error_log("fastArchivePatient: Error fetching patient data: " . $e->getMessage());
        // Silently continue
    }

    // Use a single optimized transaction
    $conn->begin_transaction();

    try {
        // 1. Get all visit IDs first
        $visit_ids = [];
        $stmt = $conn->prepare("SELECT visit_id FROM visits WHERE patient_id = ?");
        $stmt->bind_param("i", $patient_id);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $visit_ids[] = $row['visit_id'];
        }
        $stmt->close();

        error_log("fastArchivePatient: Found " . count($visit_ids) . " visits for patient $patient_id");

        // 2. Archive all data using correct column mappings based on actual table structures
        $archiveQueries = [];

        // Archive patients table - based on debug output
        if (tableExists($conn, 'patients') && tableExists($conn, 'archived_patients')) {
            // Map based on actual columns from debug output
            $archiveQueries[] = "INSERT INTO archived_patients (
                patient_id, firstname, middlename, surname, date_of_birth, 
                place_of_birth, age, months_old, if_treatment, sex, address, 
                pregnant, occupation, guardian, archived_at
            ) SELECT 
                patient_id, firstname, middlename, surname, date_of_birth, 
                place_of_birth, age, months_old, if_treatment, sex, address, 
                pregnant, occupation, guardian, NOW() 
            FROM patients WHERE patient_id = ?";
        }

        // Archive visits table - based on debug output (no chief_complaint, blood_pressure, temperature)
        if (tableExists($conn, 'visits') && tableExists($conn, 'archived_visits')) {
            $archiveQueries[] = "INSERT INTO archived_visits (
                visit_id, patient_id, visit_date, visit_number, archived_at
            ) SELECT 
                visit_id, patient_id, visit_date, visit_number, NOW() 
            FROM visits WHERE patient_id = ?";
        }

        // Archive oral_health_condition - based on debug output
        if (tableExists($conn, 'oral_health_condition') && tableExists($conn, 'archived_oral_health_condition')) {
            $archiveQueries[] = "INSERT INTO archived_oral_health_condition (
                id, patient_id, orally_fit_child, dental_caries, gingivitis, 
                periodontal_disease, others, debris, calculus, abnormal_growth, 
                cleft_palate, perm_teeth_present, perm_sound_teeth, perm_decayed_teeth_d, 
                perm_missing_teeth_m, perm_filled_teeth_f, perm_total_dmf, temp_teeth_present, 
                temp_sound_teeth, temp_decayed_teeth_d, temp_filled_teeth_f, temp_total_df, 
                created_at, updated_at, archived_at
            ) SELECT 
                id, patient_id, orally_fit_child, dental_caries, gingivitis, 
                periodontal_disease, others, debris, calculus, abnormal_growth, 
                cleft_palate, perm_teeth_present, perm_sound_teeth, perm_decayed_teeth_d, 
                perm_missing_teeth_m, perm_filled_teeth_f, perm_total_dmf, temp_teeth_present, 
                temp_sound_teeth, temp_decayed_teeth_d, temp_filled_teeth_f, temp_total_df, 
                created_at, updated_at, NOW() 
            FROM oral_health_condition WHERE patient_id = ?";
        }

        // Archive patient_treatment_record - based on debug output
        if (tableExists($conn, 'patient_treatment_record') && tableExists($conn, 'archived_patient_treatment_record')) {
            $archiveQueries[] = "INSERT INTO archived_patient_treatment_record (
                id, patient_id, oral_prophylaxis, fluoride, sealant, permanent_filling, 
                temporary_filling, extraction, consultation, remarks, created_at, updated_at, archived_at
            ) SELECT 
                id, patient_id, oral_prophylaxis, fluoride, sealant, permanent_filling, 
                temporary_filling, extraction, consultation, remarks, created_at, updated_at, NOW() 
            FROM patient_treatment_record WHERE patient_id = ?";
        }

        // Check for other tables that might exist but weren't in debug output
        $additionalTables = [
            'services_monitoring_chart' => 'archived_services_monitoring_chart',
            'dietary_habits' => 'archived_dietary_habits',
            'medical_history' => 'archived_medical_history',
            'vital_signs' => 'archived_vital_signs',
            'patient_other_info' => 'archived_patient_other_info'
        ];

        foreach ($additionalTables as $sourceTable => $targetTable) {
            if (tableExists($conn, $sourceTable) && tableExists($conn, $targetTable)) {
                // Get columns for both tables
                $sourceColumns = getTableColumns($conn, $sourceTable);
                $targetColumns = getTableColumns($conn, $targetTable);

                // Remove 'archived_at' from target if it exists
                $targetColumns = array_filter($targetColumns, function ($col) {
                    return strtolower($col) !== 'archived_at';
                });

                // Find common columns
                $commonColumns = array_intersect($sourceColumns, $targetColumns);

                if (!empty($commonColumns)) {
                    $columnsStr = implode(', ', $commonColumns);
                    $query = "INSERT INTO $targetTable ($columnsStr, archived_at) 
                             SELECT $columnsStr, NOW() 
                             FROM $sourceTable WHERE patient_id = ?";

                    $archiveQueries[] = $query;
                    error_log("Added archive query for $sourceTable -> $targetTable");
                }
            }
        }

        // Execute all archive queries
        foreach ($archiveQueries as $index => $query) {
            try {
                error_log("Executing archive query $index: " . substr($query, 0, 100) . "...");
                $stmt = $conn->prepare($query);
                $stmt->bind_param("i", $patient_id);
                $stmt->execute();
                $affected = $stmt->affected_rows;
                $stmt->close();
                error_log("Query $index executed successfully. Affected rows: $affected");
            } catch (Exception $e) {
                error_log("Error in archive query $index: " . $e->getMessage());
                error_log("Failed query: " . $query);
                // If it's a "no rows affected" issue or "duplicate entry", continue
                if (strpos($e->getMessage(), 'Duplicate entry') === false) {
                    // Re-throw only if it's not a duplicate entry
                    throw $e;
                } else {
                    error_log("Duplicate entry - ignoring error");
                }
            }
        }

        // 3. Archive visit-based tables if visits exist
        if (!empty($visit_ids)) {
            $ids_placeholders = implode(",", array_fill(0, count($visit_ids), '?'));
            error_log("Archiving data for visit IDs: " . implode(', ', $visit_ids));

            $visitArchiveQueries = [];

            // For visittoothcondition -> archived_visittoothcondition
            if (tableExists($conn, 'visittoothcondition') && tableExists($conn, 'archived_visittoothcondition')) {
                // Check what columns exist in target
                $targetColumns = getTableColumns($conn, 'archived_visittoothcondition');
                $hasColor = in_array('color', $targetColumns);
                $hasCaseType = in_array('case_type', $targetColumns);

                if ($hasColor && $hasCaseType) {
                    // Target has all columns
                    $visitArchiveQueries[] = [
                        'query' => "INSERT INTO archived_visittoothcondition (id, visit_id, tooth_id, condition_id, box_key, color, case_type, archived_at) 
                           SELECT id, visit_id, tooth_id, condition_id, box_key, color, case_type, NOW() 
                           FROM visittoothcondition WHERE visit_id IN ($ids_placeholders)",
                        'name' => 'visittoothcondition'
                    ];
                } else {
                    // Target is missing columns, use only available ones
                    $visitArchiveQueries[] = [
                        'query' => "INSERT INTO archived_visittoothcondition (id, visit_id, tooth_id, condition_id, box_key, archived_at) 
                           SELECT id, visit_id, tooth_id, condition_id, box_key, NOW() 
                           FROM visittoothcondition WHERE visit_id IN ($ids_placeholders)",
                        'name' => 'visittoothcondition (without color/case_type)'
                    ];
                }
            } else {
                error_log("Skipping visittoothcondition: one or both tables don't exist");
            }

            // For visittoothtreatment -> archived_visittoothtreatment
            if (tableExists($conn, 'visittoothtreatment') && tableExists($conn, 'archived_visittoothtreatment')) {
                $visitArchiveQueries[] = [
                    'query' => "INSERT INTO archived_visittoothtreatment (id, visit_id, tooth_id, treatment_id, box_key, color, case_type, archived_at) 
                       SELECT id, visit_id, tooth_id, treatment_id, box_key, color, case_type, NOW() 
                       FROM visittoothtreatment WHERE visit_id IN ($ids_placeholders)",
                    'name' => 'visittoothtreatment'
                ];
            } else {
                error_log("Skipping visittoothtreatment: one or both tables don't exist");
            }

            foreach ($visitArchiveQueries as $index => $queryInfo) {
                try {
                    error_log("Executing visit archive query {$queryInfo['name']}");
                    $stmt = $conn->prepare($queryInfo['query']);
                    $stmt->bind_param(str_repeat("i", count($visit_ids)), ...$visit_ids);
                    $stmt->execute();
                    $affected = $stmt->affected_rows;
                    $stmt->close();
                    error_log("Visit query {$queryInfo['name']} executed successfully. Affected rows: $affected");

                    if ($affected === 0) {
                        error_log("Warning: No rows archived for {$queryInfo['name']}. Table might be empty.");
                    }
                } catch (Exception $e) {
                    error_log("Error in visit archive query {$queryInfo['name']}: " . $e->getMessage());
                    // Don't throw - just log and continue
                }
            }
        }

        // 4. Delete all archived data (in reverse order to respect foreign keys)
        $deleteQueries = [
            // Delete visit-based tables first (if they exist)
            !empty($visit_ids) && tableExists($conn, 'visittoothtreatment') ?
                "DELETE FROM visittoothtreatment WHERE visit_id IN (" . implode(",", $visit_ids) . ")" : null,
            !empty($visit_ids) && tableExists($conn, 'visittoothcondition') ?
                "DELETE FROM visittoothcondition WHERE visit_id IN (" . implode(",", $visit_ids) . ")" : null,
            // Delete patient-based tables (in reverse order of dependencies)
            tableExists($conn, 'visits') ? "DELETE FROM visits WHERE patient_id = ?" : null,
            tableExists($conn, 'oral_health_condition') ? "DELETE FROM oral_health_condition WHERE patient_id = ?" : null,
            tableExists($conn, 'patient_treatment_record') ? "DELETE FROM patient_treatment_record WHERE patient_id = ?" : null,
            // Additional tables that might exist
            tableExists($conn, 'services_monitoring_chart') ? "DELETE FROM services_monitoring_chart WHERE patient_id = ?" : null,
            tableExists($conn, 'dietary_habits') ? "DELETE FROM dietary_habits WHERE patient_id = ?" : null,
            tableExists($conn, 'medical_history') ? "DELETE FROM medical_history WHERE patient_id = ?" : null,
            tableExists($conn, 'patient_other_info') ? "DELETE FROM patient_other_info WHERE patient_id = ?" : null,
            tableExists($conn, 'vital_signs') ? "DELETE FROM vital_signs WHERE patient_id = ?" : null,
            tableExists($conn, 'patients') ? "DELETE FROM patients WHERE patient_id = ?" : null
        ];
        // Debug: Check what data exists in visittoothcondition
        if (!empty($visit_ids) && tableExists($conn, 'visittoothcondition')) {
            $debug_ids_placeholders = implode(",", array_fill(0, count($visit_ids), '?'));
            $debugStmt = $conn->prepare("SELECT COUNT(*) as count FROM visittoothcondition WHERE visit_id IN ($debug_ids_placeholders)");
            $debugStmt->bind_param(str_repeat("i", count($visit_ids)), ...$visit_ids);
            $debugStmt->execute();
            $debugResult = $debugStmt->get_result();
            $debugRow = $debugResult->fetch_assoc();
            error_log("Found {$debugRow['count']} records in visittoothcondition for these visits");
            $debugStmt->close();

            // Also show some sample data
            $sampleStmt = $conn->prepare("SELECT * FROM visittoothcondition WHERE visit_id IN ($debug_ids_placeholders) LIMIT 3");
            $sampleStmt->bind_param(str_repeat("i", count($visit_ids)), ...$visit_ids);
            $sampleStmt->execute();
            $sampleResult = $sampleStmt->get_result();
            $samples = [];
            while ($row = $sampleResult->fetch_assoc()) {
                $samples[] = $row;
            }
            error_log("Sample visittoothcondition data: " . json_encode($samples));
            $sampleStmt->close();
        }
        foreach ($deleteQueries as $index => $query) {
            if ($query) {
                try {
                    error_log("Executing delete query $index");
                    if (strpos($query, 'IN (') !== false) {
                        // For IN queries with multiple IDs
                        $result = $conn->query($query);
                        error_log("Delete query $index executed. Affected rows: " . $conn->affected_rows);
                    } else {
                        // For single parameter queries
                        $stmt = $conn->prepare($query);
                        $stmt->bind_param("i", $patient_id);
                        $stmt->execute();
                        error_log("Delete query $index executed. Affected rows: " . $stmt->affected_rows);
                        $stmt->close();
                    }
                } catch (Exception $e) {
                    error_log("Error in delete query $index: " . $e->getMessage());
                    // Continue with other deletes even if one fails
                }
            }
        }

        $conn->commit();
        error_log("Transaction committed successfully for patient $patient_id");

        // Log successful archive after commit
        if ($patientData) {
            $patientName = trim(($patientData['firstname'] ?? '') . ' ' . ($patientData['surname'] ?? ''));
            $description = "Patient archived: " . ($patientName ?: "Unknown") .
                " (ID: {$patient_id})" . ($isOfflineSync ? " - Offline sync" : "");

            addHistoryLog(
                $conn,
                "patients",
                $patient_id,
                "ARCHIVE",
                $loggedUser['type'] ?? 'System',
                $loggedUser['id'] ?? 0,
                ['patient_info' => $patientData],
                null,
                $description
            );
            error_log("fastArchivePatient: History log added for patient $patient_id");
        }

        return true;
    } catch (Exception $e) {
        $conn->rollback();
        error_log("fastArchivePatient: Transaction rolled back for patient $patient_id: " . $e->getMessage());

        // Check if it's a duplicate entry error (already archived)
        if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
            error_log("fastArchivePatient: Duplicate entry error - patient already archived");
            return true; // Already archived, consider successful
        }

        throw $e;
    }
}

// Helper function to check if table exists
function tableExists($conn, $tableName)
{
    $result = $conn->query("SHOW TABLES LIKE '$tableName'");
    return $result && $result->num_rows > 0;
}

// Function to get table columns
function getTableColumns($conn, $tableName)
{
    $columns = [];
    $result = $conn->query("SHOW COLUMNS FROM $tableName");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $columns[] = $row['Field'];
        }
    }
    return $columns;
}



// Function to compare two tables' columns
function compareTableColumns($conn, $sourceTable, $targetTable)
{
    $sourceColumns = getTableColumns($conn, $sourceTable);
    $targetColumns = getTableColumns($conn, $targetTable);

    error_log("Comparing $sourceTable (" . count($sourceColumns) . " cols) with $targetTable (" . count($targetColumns) . " cols)");

    $sourceOnly = array_diff($sourceColumns, $targetColumns);
    $targetOnly = array_diff($targetColumns, $sourceColumns);

    if (!empty($sourceOnly)) {
        error_log("Columns in $sourceTable but not in $targetTable: " . implode(', ', $sourceOnly));
    }

    if (!empty($targetOnly)) {
        error_log("Columns in $targetTable but not in $sourceTable: " . implode(', ', $targetOnly));
    }

    return [
        'source_columns' => $sourceColumns,
        'target_columns' => $targetColumns,
        'source_only' => $sourceOnly,
        'target_only' => $targetOnly
    ];
}

// =====================================================
// REQUEST HANDLING
// =====================================================

// SINGLE PATIENT ARCHIVE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['archive_id']) && !isset($_POST['sync_offline_archives'])) {
    $patient_id = intval($_POST['archive_id']);

    // Get user ID from POST or GET (same as update_patient.php)
    $userId = isset($_POST['uid']) ? intval($_POST['uid']) : (isset($_GET['uid']) ? intval($_GET['uid']) : 0);

    // CHECK IF THIS USER IS REALLY LOGGED IN (same as update_patient.php)
    if (!isset($_SESSION['active_sessions']) || !isset($_SESSION['active_sessions'][$userId])) {
        echo json_encode([
            "success" => false,
            "message" => "Please log in first.",
            "patient_id" => $patient_id
        ]);
        exit;
    }

    $loggedUser = $_SESSION['active_sessions'][$userId];

    try {
        // Verify patient exists before archiving
        if (!verifyPatientExists($conn, $patient_id)) {
            echo json_encode([
                "success" => false,
                "message" => "Patient not found or already archived.",
                "patient_id" => $patient_id
            ]);
            exit;
        }

        $success = fastArchivePatient($conn, $patient_id, $loggedUser);

        if ($success) {
            echo json_encode([
                "success" => true,
                "message" => "Patient archived successfully.",
                "patient_id" => $patient_id
            ]);
        } else {
            echo json_encode([
                "success" => false,
                "message" => "Archive operation failed (patient may not exist).",
                "patient_id" => $patient_id
            ]);
        }
    } catch (Exception $e) {
        // Log the detailed error
        error_log("Archive error for patient $patient_id: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());

        http_response_code(400);
        echo json_encode([
            "success" => false,
            "message" => "Archive failed: " . $e->getMessage(),
            "patient_id" => $patient_id,
            "error_details" => $e->getMessage() // Add this for debugging
        ]);
    }

    $conn->close();
    exit;
}

// BULK SYNC OFFLINE ARCHIVES WITH IMPROVED VALIDATION
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sync_offline_archives'])) {
    $patientIds = [];

    if (isset($_POST['archive_ids']) && is_array($_POST['archive_ids'])) {
        $patientIds = array_map('intval', $_POST['archive_ids']);
    } elseif (isset($_POST['patient_ids']) && is_array($_POST['patient_ids'])) {
        // Alternative parameter name for compatibility
        $patientIds = array_map('intval', $_POST['patient_ids']);
    }

    $patientIds = array_filter(array_unique($patientIds), function ($id) {
        return $id > 0;
    });

    if (empty($patientIds)) {
        echo json_encode([
            "success" => false,
            "message" => "No valid patient IDs provided",
            "provided_ids" => $_POST['archive_ids'] ?? $_POST['patient_ids'] ?? []
        ]);
        $conn->close();
        exit;
    }

    // Get user info for logging (for bulk sync)
    $userId = isset($_POST['uid']) ? intval($_POST['uid']) : (isset($_GET['uid']) ? intval($_GET['uid']) : 0);

    $loggedUser = [
        'id' => $userId,
        'type' => 'System',
        'name' => 'Bulk Sync'
    ];

    // Try to get actual user from session
    if (isset($_SESSION['active_sessions'][$userId])) {
        $loggedUser = $_SESSION['active_sessions'][$userId];
    }

    $success = 0;
    $skipped = 0;
    $errors = [];

    // Process each patient individually (not in transaction for offline sync)
    // This allows partial success if some patients fail
    foreach ($patientIds as $patientId) {
        try {
            // Check if patient exists before attempting archive
            if (!verifyPatientExists($conn, $patientId)) {
                $skipped++;
                $errors[] = "Patient $patientId: Not found or already archived";
                continue;
            }

            // Use offline sync mode
            fastArchivePatient($conn, $patientId, $loggedUser, true);
            $success++;
        } catch (Exception $e) {
            $errors[] = "Patient $patientId: " . $e->getMessage();
        }
    }

    // Log bulk sync summary
    if ($success > 0) {
        $description = "Bulk archive sync completed. " .
            "Archived: {$success}, " .
            "Skipped: {$skipped}, " .
            "Errors: " . count($errors);

        addHistoryLog(
            $conn,
            "patients",
            0,
            "BULK_SYNC",
            $loggedUser['type'] ?? 'System',
            $loggedUser['id'] ?? 0,
            null,
            [
                'success_count' => $success,
                'skipped_count' => $skipped,
                'error_count' => count($errors),
                'patient_ids' => $patientIds
            ],
            $description
        );
    }

    echo json_encode([
        'success' => true,
        'message' => "Offline sync completed. Archived: $success, Skipped: $skipped, Errors: " . count($errors),
        'synced_count' => $success,
        'skipped_count' => $skipped,
        'error_count' => count($errors),
        'errors' => $errors
    ]);

    $conn->close();
    exit;
}

// FETCH ACTIVE PATIENTS WITH PAGINATION
try {
    // Get pagination parameters
    $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 50;
    $offset = ($page - 1) * $limit;

    // Check if we need total count
    $getCount = isset($_GET['count']) && $_GET['count'] == 1;

    if ($getCount) {
        // Get total count only
        $countSql = "SELECT COUNT(*) as total FROM patients WHERE if_treatment = 1";
        $result = $conn->query($countSql);
        $row = $result->fetch_assoc();
        echo json_encode(['total' => (int)$row['total']]);
    } else {
        // Get paginated data - OPTIMIZED QUERY
        $sql = "
            SELECT 
                p.patient_id,
                CONCAT_WS(' ', p.surname, p.firstname, p.middlename) AS fullname,
                p.sex,
                p.age,
                p.address,
                (SELECT visit_date FROM visits v WHERE v.patient_id = p.patient_id ORDER BY visit_date DESC LIMIT 1) AS last_visit,
                p.if_treatment,
                (SELECT COUNT(*) FROM archived_patients ap WHERE ap.patient_id = p.patient_id) as is_archived
            FROM patients p
            WHERE p.if_treatment = 1
            ORDER BY p.patient_id ASC
            LIMIT ? OFFSET ?
        ";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $limit, $offset);
        $stmt->execute();
        $result = $stmt->get_result();

        $patients = [];
        while ($row = $result->fetch_assoc()) {
            $patients[] = [
                'patient_id' => (int)$row['patient_id'],
                'fullname' => trim($row['fullname']),
                'sex' => $row['sex'] ?? '',
                'age' => (int)$row['age'],
                'address' => $row['address'] ?? '',
                'last_visit' => $row['last_visit'] ?? 'Never',
                'if_treatment' => (int)$row['if_treatment'],
                'is_archived' => (int)$row['is_archived']
            ];
        }

        // Add metadata for pagination
        $response = [
            'patients' => $patients,
            'page' => $page,
            'limit' => $limit,
            'hasMore' => count($patients) === $limit,
            'total_patients' => count($patients)
        ];

        echo json_encode($response, JSON_NUMERIC_CHECK);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Fetch failed: " . $e->getMessage()]);
}
// DEBUG ENDPOINT - REMOVE IN PRODUCTION
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['debug_tables'])) {
    $tables = [
        'patients',
        'archived_patients',
        'visits',
        'archived_visits',
        'oral_health_condition',
        'archived_oral_health_condition',
        'patient_treatment_record',
        'archived_patient_treatment_record'
    ];

    $results = [];
    foreach ($tables as $table) {
        if (tableExists($conn, $table)) {
            $columns = getTableColumns($conn, $table);
            $results[$table] = [
                'exists' => true,
                'columns' => $columns,
                'count' => count($columns)
            ];
        } else {
            $results[$table] = ['exists' => false];
        }
    }

    echo json_encode($results, JSON_PRETTY_PRINT);
    exit;
}
$conn->close();
