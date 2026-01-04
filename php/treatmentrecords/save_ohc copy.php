<?php
header('Content-Type: application/json');
session_start();
date_default_timezone_set('Asia/Manila');

// Include database connection
require_once '../conn.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Set to 1 for debugging

try {
    // Check if it's a POST request
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Invalid request method");
    }

    // Get POST data
    $patient_id = isset($_POST['patient_id']) ? intval($_POST['patient_id']) : 0;

    if ($patient_id <= 0) {
        throw new Exception("Invalid patient ID.");
    }

    // Collect all fields with proper sanitization
    $fields = [
        'orally_fit_child' => isset($_POST['orally_fit_child']) ? trim($_POST['orally_fit_child']) : '',
        'dental_caries' => isset($_POST['dental_caries']) ? trim($_POST['dental_caries']) : '',
        'gingivitis' => isset($_POST['gingivitis']) ? trim($_POST['gingivitis']) : '',
        'periodontal_disease' => isset($_POST['periodontal_disease']) ? trim($_POST['periodontal_disease']) : '',
        'debris' => isset($_POST['debris']) ? trim($_POST['debris']) : '',
        'calculus' => isset($_POST['calculus']) ? trim($_POST['calculus']) : '',
        'abnormal_growth' => isset($_POST['abnormal_growth']) ? trim($_POST['abnormal_growth']) : '',
        'cleft_palate' => isset($_POST['cleft_palate']) ? trim($_POST['cleft_palate']) : '',
        'others' => isset($_POST['others']) ? trim($_POST['others']) : '',
        'perm_teeth_present' => isset($_POST['perm_teeth_present']) ? intval($_POST['perm_teeth_present']) : 0,
        'perm_sound_teeth' => isset($_POST['perm_sound_teeth']) ? intval($_POST['perm_sound_teeth']) : 0,
        'perm_decayed_teeth_d' => isset($_POST['perm_decayed_teeth_d']) ? intval($_POST['perm_decayed_teeth_d']) : 0,
        'perm_missing_teeth_m' => isset($_POST['perm_missing_teeth_m']) ? intval($_POST['perm_missing_teeth_m']) : 0,
        'perm_filled_teeth_f' => isset($_POST['perm_filled_teeth_f']) ? intval($_POST['perm_filled_teeth_f']) : 0,
        'perm_total_dmf' => isset($_POST['perm_total_dmf']) ? intval($_POST['perm_total_dmf']) : 0,
        'temp_teeth_present' => isset($_POST['temp_teeth_present']) ? intval($_POST['temp_teeth_present']) : 0,
        'temp_sound_teeth' => isset($_POST['temp_sound_teeth']) ? intval($_POST['temp_sound_teeth']) : 0,
        'temp_decayed_teeth_d' => isset($_POST['temp_decayed_teeth_d']) ? intval($_POST['temp_decayed_teeth_d']) : 0,
        'temp_filled_teeth_f' => isset($_POST['temp_filled_teeth_f']) ? intval($_POST['temp_filled_teeth_f']) : 0,
        'temp_total_df' => isset($_POST['temp_total_df']) ? intval($_POST['temp_total_df']) : 0
    ];

    // Prepare SQL statement
    $sql = "INSERT INTO oral_health_condition (
        patient_id, orally_fit_child, dental_caries, gingivitis, periodontal_disease,
        others, debris, calculus, abnormal_growth, cleft_palate,
        perm_teeth_present, perm_sound_teeth, perm_decayed_teeth_d,
        perm_missing_teeth_m, perm_filled_teeth_f, perm_total_dmf,
        temp_teeth_present, temp_sound_teeth, temp_decayed_teeth_d,
        temp_filled_teeth_f, temp_total_df, created_at, updated_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    // Bind parameters
    $stmt->bind_param(
        "isssssssssiiiiiiiiiii",
        $patient_id,
        $fields['orally_fit_child'],
        $fields['dental_caries'],
        $fields['gingivitis'],
        $fields['periodontal_disease'],
        $fields['others'],
        $fields['debris'],
        $fields['calculus'],
        $fields['abnormal_growth'],
        $fields['cleft_palate'],
        $fields['perm_teeth_present'],
        $fields['perm_sound_teeth'],
        $fields['perm_decayed_teeth_d'],
        $fields['perm_missing_teeth_m'],
        $fields['perm_filled_teeth_f'],
        $fields['perm_total_dmf'],
        $fields['temp_teeth_present'],
        $fields['temp_sound_teeth'],
        $fields['temp_decayed_teeth_d'],
        $fields['temp_filled_teeth_f'],
        $fields['temp_total_df']
    );

    // Execute the statement
    if ($stmt->execute()) {
        $response = [
            'success' => true,
            'message' => 'Oral health record saved successfully.',
            'record_id' => $stmt->insert_id
        ];

        // Output clean JSON
        echo json_encode($response);
    } else {
        throw new Exception("Execute failed: " . $stmt->error);
    }

    $stmt->close();
} catch (Exception $e) {
    error_log("Save OHC Error: " . $e->getMessage());

    // Output error as JSON
    $response = [
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ];

    echo json_encode($response);
}

$conn->close();
exit; // Important: stop script execution after output