<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
header("Content-Type: application/json");

// Add performance headers
header("Cache-Control: max-age=0, no-cache, must-revalidate, proxy-revalidate");
header("Pragma: no-cache");

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

// Pre-cache all table columns at once to avoid multiple SHOW COLUMNS queries
$columnsCache = [];
function getAllTableColumns($conn)
{
    global $columnsCache;

    if (!empty($columnsCache)) {
        return $columnsCache;
    }

    $tables = [
        'patients',
        'archived_patients',
        'visits',
        'archived_visits',
        'oral_health_condition',
        'archived_oral_health_condition',
        'patient_treatment_record',
        'archived_patient_treatment_record',
        'services_monitoring_chart',
        'archived_services_monitoring_chart',
        'dietary_habits',
        'archived_dietary_habits',
        'medical_history',
        'archived_medical_history',
        'vital_signs',
        'archived_vital_signs',
        'patient_other_info',
        'archived_patient_other_info',
        'visittoothcondition',
        'archived_visittoothcondition',
        'visittoothtreatment',
        'archived_visittoothtreatment'
    ];

    foreach ($tables as $table) {
        $cols = [];
        $res = $conn->query("SHOW COLUMNS FROM `$table`");
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $cols[] = $row['Field'];
            }
            $columnsCache[$table] = $cols;
        }
    }

    return $columnsCache;
}

// Initialize column cache
$columnsCache = getAllTableColumns($conn);

function getColumns($table)
{
    global $columnsCache;
    return $columnsCache[$table] ?? [];
}

// =====================================================
// FAST ARCHIVE FUNCTIONS
// =====================================================
function fastArchivePatient($conn, $patient_id)
{
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

        // 2. Archive all data using optimized batch queries
        $archiveQueries = [
            // Archive patient and related tables with patient_id
            "INSERT INTO archived_patients SELECT p.*, NOW() FROM patients p WHERE p.patient_id = ?",
            "INSERT INTO archived_visits SELECT v.*, NOW() FROM visits v WHERE v.patient_id = ?",
            "INSERT INTO archived_oral_health_condition SELECT o.*, NOW() FROM oral_health_condition o WHERE o.patient_id = ?",
            "INSERT INTO archived_patient_treatment_record SELECT t.*, NOW() FROM patient_treatment_record t WHERE t.patient_id = ?",
            "INSERT INTO archived_services_monitoring_chart SELECT s.*, NOW() FROM services_monitoring_chart s WHERE s.patient_id = ?",
            "INSERT INTO archived_dietary_habits SELECT d.*, NOW() FROM dietary_habits d WHERE d.patient_id = ?",
            "INSERT INTO archived_medical_history SELECT m.*, NOW() FROM medical_history m WHERE m.patient_id = ?",
            "INSERT INTO archived_vital_signs SELECT v.*, NOW() FROM vital_signs v WHERE v.patient_id = ?",
            "INSERT INTO archived_patient_other_info SELECT p.*, NOW() FROM patient_other_info p WHERE p.patient_id = ?",
        ];

        foreach ($archiveQueries as $query) {
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $patient_id);
            $stmt->execute();
            $stmt->close();
        }

        // 3. Archive visit-based tables if visits exist
        if (!empty($visit_ids)) {
            $ids_placeholders = implode(",", array_fill(0, count($visit_ids), '?'));

            $visitArchiveQueries = [
                "INSERT INTO archived_visittoothcondition SELECT v.*, NOW() FROM visittoothcondition v WHERE v.visit_id IN ($ids_placeholders)",
                "INSERT INTO archived_visittoothtreatment SELECT v.*, NOW() FROM visittoothtreatment v WHERE v.visit_id IN ($ids_placeholders)"
            ];

            foreach ($visitArchiveQueries as $query) {
                $stmt = $conn->prepare($query);
                $stmt->bind_param(str_repeat("i", count($visit_ids)), ...$visit_ids);
                $stmt->execute();
                $stmt->close();
            }
        }

        // 4. Delete all archived data (optimized order)
        $deleteQueries = [
            // Delete visit-based tables first
            !empty($visit_ids) ? "DELETE FROM visittoothtreatment WHERE visit_id IN (" . implode(",", $visit_ids) . ")" : null,
            !empty($visit_ids) ? "DELETE FROM visittoothcondition WHERE visit_id IN (" . implode(",", $visit_ids) . ")" : null,
            // Delete patient-based tables
            "DELETE FROM visits WHERE patient_id = ?",
            "DELETE FROM oral_health_condition WHERE patient_id = ?",
            "DELETE FROM patient_treatment_record WHERE patient_id = ?",
            "DELETE FROM services_monitoring_chart WHERE patient_id = ?",
            "DELETE FROM dietary_habits WHERE patient_id = ?",
            "DELETE FROM medical_history WHERE patient_id = ?",
            "DELETE FROM patient_other_info WHERE patient_id = ?",
            "DELETE FROM vital_signs WHERE patient_id = ?",
            "DELETE FROM patients WHERE patient_id = ?"
        ];

        foreach ($deleteQueries as $query) {
            if ($query) {
                if (strpos($query, 'IN (') !== false) {
                    // For IN queries with multiple IDs
                    $conn->query($query);
                } else {
                    // For single parameter queries
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param("i", $patient_id);
                    $stmt->execute();
                    $stmt->close();
                }
            }
        }

        $conn->commit();
        return true;
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
}

// =====================================================
// REQUEST HANDLING
// =====================================================

// SINGLE PATIENT ARCHIVE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['archive_id']) && !isset($_POST['sync_offline_archives'])) {
    $patient_id = intval($_POST['archive_id']);

    try {
        $success = fastArchivePatient($conn, $patient_id);
        echo json_encode(["success" => true, "message" => "Patient archived successfully."]);
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Archive failed: " . $e->getMessage()]);
    }

    $conn->close();
    exit;
}

// BULK SYNC OFFLINE ARCHIVES
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sync_offline_archives'])) {
    if (isset($_POST['archive_ids']) && is_array($_POST['archive_ids'])) {
        $patientIds = array_map('intval', $_POST['archive_ids']);
        $patientIds = array_filter(array_unique($patientIds), function ($id) {
            return $id > 0;
        });

        if (empty($patientIds)) {
            echo json_encode([
                "success" => false,
                "message" => "No valid patient IDs provided"
            ]);
            $conn->close();
            exit;
        }

        $success = 0;
        $errors = [];
        $conn->begin_transaction();

        try {
            foreach ($patientIds as $patientId) {
                try {
                    fastArchivePatient($conn, $patientId);
                    $success++;
                } catch (Exception $e) {
                    $errors[] = "Patient $patientId: " . $e->getMessage();
                }
            }

            $conn->commit();
            echo json_encode([
                'success' => true,
                'message' => "Archived $success patient(s) successfully",
                'synced_count' => $success,
                'error_count' => count($errors),
                'errors' => $errors
            ]);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode([
                'success' => false,
                'message' => "Transaction failed: " . $e->getMessage(),
                'errors' => [$e->getMessage()]
            ]);
        }

        $conn->close();
        exit;
    }
}

// FETCH ACTIVE PATIENTS WITH PAGINATION
try {
    // Get pagination parameters
    $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 50; // Increased default limit
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
        // Removed unnecessary GROUP BY and MAX() for visits
        $sql = "
            SELECT 
                p.patient_id,
                CONCAT_WS(' ', p.surname, p.firstname, p.middlename) AS fullname,
                p.sex,
                p.age,
                p.address,
                (SELECT visit_date FROM visits v WHERE v.patient_id = p.patient_id ORDER BY visit_date DESC LIMIT 1) AS last_visit,
                p.if_treatment
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
                'if_treatment' => (int)$row['if_treatment']
            ];
        }

        // Add metadata for pagination
        $response = [
            'patients' => $patients,
            'page' => $page,
            'limit' => $limit,
            'hasMore' => count($patients) === $limit
        ];

        echo json_encode($response, JSON_NUMERIC_CHECK);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Fetch failed: " . $e->getMessage()]);
}

$conn->close();
