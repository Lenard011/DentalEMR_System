<?php
// Turn off HTML output but log errors
ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);

// Start output buffering
ob_start();

header('Content-Type: application/json; charset=utf-8');

try {
    // Debug: Check if conns.php exists
    $connsFile = __DIR__ . '/conns.php';
    if (!file_exists($connsFile)) {
        throw new Exception("Database connection file not found: " . $connsFile);
    }
    
    require_once $connsFile;
    
    // Check if $pdo is properly set in conns.php
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        throw new Exception("Database connection not properly initialized.");
    }
    
    // Check database connection
    $pdo->query('SELECT 1');
    
    $method = $_SERVER['REQUEST_METHOD'];

    // -------------------- ADD RECORD (POST) --------------------
    if ($method === 'POST') {
        $data = $_POST;

        if (empty($data['patient_id'])) {
            throw new Exception("Missing patient ID.");
        }

        // Handle custom service date or use current date
        $createdAt = date('Y-m-d H:i:s'); // Default to current timestamp
        if (!empty($data['service_date'])) {
            // Validate date format
            $date = DateTime::createFromFormat('Y-m-d', $data['service_date']);
            if ($date && $date->format('Y-m-d') === $data['service_date']) {
                // Use the selected date at noon (12:00:00)
                $createdAt = $data['service_date'] . ' 12:00:00';
            } else {
                throw new Exception("Invalid date format. Please use YYYY-MM-DD format.");
            }
        }

        $stmt = $pdo->prepare("
            INSERT INTO patient_treatment_record (
                patient_id, oral_prophylaxis, fluoride, sealant,
                permanent_filling, temporary_filling, extraction,
                consultation, remarks, created_at
            ) VALUES (
                :patient_id, :oral_prophylaxis, :fluoride, :sealant,
                :permanent_filling, :temporary_filling, :extraction,
                :consultation, :remarks, :created_at
            )
        ");

        $stmt->execute([
            ':patient_id' => $data['patient_id'],
            ':oral_prophylaxis' => $data['oral_prophylaxis'] ?? '',
            ':fluoride' => $data['fluoride'] ?? '',
            ':sealant' => $data['sealant'] ?? '',
            ':permanent_filling' => $data['permanent_filling'] ?? '',
            ':temporary_filling' => $data['temporary_filling'] ?? '',
            ':extraction' => $data['extraction'] ?? '',
            ':consultation' => $data['consultation'] ?? '',
            ':remarks' => $data['remarks'] ?? '',
            ':created_at' => $createdAt
        ]);

        echo json_encode([
            "success" => true,
            "message" => "Record added successfully.",
            "created_at" => $createdAt
        ]);
        exit;
    }

    // -------------------- VIEW RECORDS (GET) --------------------
    elseif ($method === 'GET') {
        $patient_id = isset($_GET['patient_id']) ? intval($_GET['patient_id']) : 0;
        if ($patient_id <= 0) throw new Exception("Invalid patient ID");

        // Fetch patient name
        $stmtPatient = $pdo->prepare("
            SELECT firstname, middlename, surname
            FROM patients
            WHERE patient_id = :id
            LIMIT 1
        ");
        $stmtPatient->execute([':id' => $patient_id]);
        $patient = $stmtPatient->fetch(PDO::FETCH_ASSOC);

        if (!$patient) throw new Exception("Patient not found");

        $fullname = trim($patient['firstname'] . ' ' .
            ($patient['middlename'] ? substr($patient['middlename'], 0, 1) . '. ' : '') .
            $patient['surname']);

        // Fetch records (display DATE from created_at)
        $stmtRecords = $pdo->prepare("
            SELECT 
                DATE(created_at) AS created_at,
                oral_prophylaxis, fluoride, sealant, 
                permanent_filling, temporary_filling, extraction, 
                consultation, remarks
            FROM patient_treatment_record
            WHERE patient_id = :pid
            ORDER BY created_at DESC
        ");
        $stmtRecords->execute([':pid' => $patient_id]);
        $records = $stmtRecords->fetchAll(PDO::FETCH_ASSOC);

        // Return combined response
        echo json_encode([
            "success" => true,
            "patient" => [
                "id" => $patient_id,
                "fullname" => $fullname
            ],
            "records" => $records
        ]);
        exit;
    } else {
        throw new Exception("Unsupported request method.");
    }
} catch (Exception $e) {
    // Clear any previous output
    ob_clean();
    
    // Log the error
    error_log("view_record.php ERROR: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    
    // Return JSON error
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Server error: " . $e->getMessage(),
        "debug_info" => [
            "file" => $e->getFile(),
            "line" => $e->getLine()
        ]
    ]);
    exit;
} finally {
    // Clean any remaining output buffer
    if (ob_get_length() > 0) {
        ob_end_clean();
    }
}