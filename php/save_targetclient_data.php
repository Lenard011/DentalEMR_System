<?php
session_start();
header('Content-Type: application/json');

// Allow CORS for development
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

$response = ['success' => false, 'error' => ''];

try {
    // Debug logging
    error_log('Save target client data called: ' . date('Y-m-d H:i:s'));
    error_log('POST data: ' . print_r($_POST, true));

    // Validate input
    if (!isset($_POST['patient_id']) || !isset($_POST['year'])) {
        throw new Exception('Missing required fields: patient_id or year');
    }

    $patientId = intval($_POST['patient_id']);
    $year = intval($_POST['year']);
    $userId = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;

    if ($patientId <= 0) {
        throw new Exception('Invalid patient ID');
    }

    // Database connection
    $host = "localhost";
    $dbUser = "u401132124_dentalclinic";
    $dbPass = "Mho_DentalClinic1st";
    $dbName = "u401132124_mho_dentalemr";

    $conn = new mysqli($host, $dbUser, $dbPass, $dbName);
    if ($conn->connect_error) {
        throw new Exception('Database connection failed: ' . $conn->connect_error);
    }

    // Prepare data
    $fields = [
        'oe',
        'iiohc',
        'aebf',
        'tfa',
        'stb',
        'ohe',
        'ecc',
        'art',
        'ops',
        'pfs',
        'tf',
        'pf',
        'gt',
        'rp',
        'rut',
        'ref',
        'tpec',
        'dr',
        'bohc_0_11',
        'bohc_1_4',
        'bohc_5_9',
        'bohc_10_19',
        'bohc_20_59',
        'bohc_60_plus',
        'bohc_pregnant',
        'remarks'
    ];

    $data = ['patient_id' => $patientId, 'year' => $year];
    foreach ($fields as $field) {
        $data[$field] = isset($_POST[$field]) ? trim($_POST[$field]) : '';
    }

    error_log('Processed data: ' . print_r($data, true));

    // Check if record exists
    $checkStmt = $conn->prepare("SELECT id FROM target_client_list_data WHERE patient_id = ? AND year = ?");
    if (!$checkStmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }

    $checkStmt->bind_param("ii", $patientId, $year);
    if (!$checkStmt->execute()) {
        throw new Exception('Execute failed: ' . $checkStmt->error);
    }

    $checkResult = $checkStmt->get_result();
    $recordExists = $checkResult->num_rows > 0;
    $checkStmt->close();

    if ($recordExists) {
        // Update existing record
        $updateFields = [];
        $types = ''; // Start with empty types string
        $values = [];

        foreach ($fields as $field) {
            $updateFields[] = "{$field} = ?";
            $types .= 's';
            $values[] = $data[$field];
        }

        $sql = "UPDATE target_client_list_data SET " . implode(', ', $updateFields) .
            " WHERE patient_id = ? AND year = ?";
        $types .= 'ii'; // Add types for WHERE clause parameters
        $values[] = $patientId;
        $values[] = $year;

        error_log('Update SQL: ' . $sql);
        error_log('Types: ' . $types);
        error_log('Values: ' . print_r($values, true));

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception('Prepare failed: ' . $conn->error);
        }

        $stmt->bind_param($types, ...$values);
    } else {
        // Insert new record
        $fieldNames = array_merge(['patient_id', 'year'], $fields);
        $placeholders = implode(',', array_fill(0, count($fieldNames), '?'));
        $types = str_repeat('s', count($fieldNames));
        $types[0] = 'i'; // patient_id is integer
        $types[1] = 'i'; // year is integer

        $sql = "INSERT INTO target_client_list_data (" . implode(',', $fieldNames) .
            ") VALUES ({$placeholders})";

        error_log('Insert SQL: ' . $sql);
        error_log('Types: ' . $types);

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception('Prepare failed: ' . $conn->error);
        }

        // Prepare values in correct order
        $values = [$patientId, $year];
        foreach ($fields as $field) {
            $values[] = $data[$field];
        }

        error_log('Insert values: ' . print_r($values, true));

        $stmt->bind_param($types, ...$values);
    }

    if ($stmt->execute()) {
        $response['success'] = true;
        $response['message'] = $recordExists ? 'Data updated successfully' : 'Data inserted successfully';

        // Log the action
        if ($userId > 0) {
            $action = $recordExists ? 'Updated' : 'Added';
            $details = "{$action} target client list data for Patient ID: {$patientId}, Year: {$year}";

            // Check if system_logs table exists
            $logCheck = $conn->query("SHOW TABLES LIKE 'system_logs'");
            if ($logCheck->num_rows > 0) {
                $logStmt = $conn->prepare("INSERT INTO system_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
                if ($logStmt) {
                    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
                    $logStmt->bind_param("isss", $userId, $action, $details, $ipAddress);
                    $logStmt->execute();
                    $logStmt->close();
                }
            }
        }
    } else {
        throw new Exception('Database error: ' . $stmt->error);
    }

    $stmt->close();
    $conn->close();
} catch (Exception $e) {
    $response['error'] = $e->getMessage();
    error_log('Error in save_targetclient_data.php: ' . $e->getMessage());
}

echo json_encode($response);
