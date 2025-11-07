<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . './conns.php';

try {
    $method = $_SERVER['REQUEST_METHOD'];

    // -------------------- ADD RECORD (POST) --------------------
    if ($method === 'POST') {
        $data = $_POST;

        if (empty($data['patient_id'])) {
            throw new Exception("Missing patient ID.");
        }

        $stmt = $pdo->prepare("
            INSERT INTO patient_treatment_record (
                patient_id, oral_prophylaxis, fluoride, sealant,
                permanent_filling, temporary_filling, extraction,
                consultation, remarks, created_at
            ) VALUES (
                :patient_id, :oral_prophylaxis, :fluoride, :sealant,
                :permanent_filling, :temporary_filling, :extraction,
                :consultation, :remarks, NOW()
            )
        ");

        $stmt->execute([
            ':patient_id' => $data['patient_id'],
            ':oral_prophylaxis' => $data['oral_prophylaxis'],
            ':fluoride' => $data['fluoride'],
            ':sealant' => $data['sealant'],
            ':permanent_filling' => $data['permanent_filling'],
            ':temporary_filling' => $data['temporary_filling'],
            ':extraction' => $data['extraction'],
            ':consultation' => $data['consultation'],
            ':remarks' => $data['remarks']
        ]);

        echo json_encode([
            "success" => true,
            "message" => "Record added successfully."
        ]);
        exit;
    }

    // -------------------- VIEW RECORDS (GET) --------------------
    elseif ($method === 'GET') {
        $patient_id = isset($_GET['patient_id']) ? intval($_GET['patient_id']) : 0;
        if ($patient_id <= 0) throw new Exception("Invalid patient ID");

        // 🩺 Fetch patient name
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

        // Fetch records (only DATE from created_at)
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
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
    exit;
}
