<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// Include database connection
include_once("../conn.php");

// Set default timezone
date_default_timezone_set('Asia/Manila');

// Get patient ID from request
$patient_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$record_id = isset($_GET['record']) ? intval($_GET['record']) : 0;

// Validate patient ID
if ($patient_id <= 0 && $record_id <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Missing or invalid patient ID',
        'data' => []
    ]);
    exit;
}

try {
    if ($record_id > 0) {
        // Fetch specific record
        $sql = "SELECT 
                    o.*,
                    CONCAT(p.firstname, ' ', COALESCE(p.middlename, ''), ' ', p.surname) AS patient_name
                FROM oral_health_condition o
                LEFT JOIN patients p ON o.patient_id = p.patient_id
                WHERE o.id = ?";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $record_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $record = $result->fetch_assoc();

            // Format checkmark fields properly
            $check_fields = [
                'orally_fit_child',
                'dental_caries',
                'gingivitis',
                'periodontal_disease',
                'debris',
                'calculus',
                'abnormal_growth',
                'cleft_palate'
            ];

            foreach ($check_fields as $field) {
                if (isset($record[$field])) {
                    $value = trim($record[$field]);
                    $record[$field . '_bool'] = ($value === '✓' || $value === '1' || $value === 'true' || $value === 'yes');
                }
            }

            echo json_encode([
                'success' => true,
                'message' => 'Record found',
                'data' => $record
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Record not found',
                'data' => []
            ]);
        }
        $stmt->close();
    } else {
        // Fetch all records for patient
        $sql = "SELECT 
                    o.*,
                    CONCAT(p.firstname, ' ', COALESCE(p.middlename, ''), ' ', p.surname) AS patient_name
                FROM oral_health_condition o
                LEFT JOIN patients p ON o.patient_id = p.patient_id
                WHERE o.patient_id = ?
                ORDER BY o.created_at DESC, o.id DESC";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $patient_id);
        $stmt->execute();
        $result = $stmt->get_result();

        $records = [];

        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                // Ensure all fields have proper values
                $row['id'] = intval($row['id']);
                $row['patient_id'] = intval($row['patient_id']);

                // Convert numeric fields
                $numeric_fields = [
                    'perm_total_dmf',
                    'perm_teeth_present',
                    'perm_sound_teeth',
                    'perm_decayed_teeth_d',
                    'perm_missing_teeth_m',
                    'perm_filled_teeth_f',
                    'temp_total_df',
                    'temp_teeth_present',
                    'temp_sound_teeth',
                    'temp_decayed_teeth_d',
                    'temp_filled_teeth_f'
                ];

                foreach ($numeric_fields as $field) {
                    $row[$field] = isset($row[$field]) ? intval($row[$field]) : 0;
                }

                // Add boolean versions of checkmark fields
                $check_fields = [
                    'orally_fit_child',
                    'dental_caries',
                    'gingivitis',
                    'periodontal_disease',
                    'debris',
                    'calculus',
                    'abnormal_growth',
                    'cleft_palate'
                ];

                foreach ($check_fields as $field) {
                    if (isset($row[$field])) {
                        $value = trim($row[$field]);
                        $row[$field . '_bool'] = ($value === '✓' || $value === '1' || $value === 'true' || $value === 'yes');
                    } else {
                        $row[$field . '_bool'] = false;
                    }
                }

                // Ensure 'others' field exists
                $row['others'] = isset($row['others']) ? $row['others'] : '';

                $records[] = $row;
            }

            echo json_encode([
                'success' => true,
                'message' => 'Records found',
                'count' => count($records),
                'data' => $records
            ]);
        } else {
            echo json_encode([
                'success' => true,
                'message' => 'No records found',
                'count' => 0,
                'data' => []
            ]);
        }

        $stmt->close();
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage(),
        'data' => []
    ]);
}

$conn->close();
