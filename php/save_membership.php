<?php
// Prevent PHP notices/warnings from breaking JSON output
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', 0);

header('Content-Type: application/json');
require_once "conn.php"; // must define $conn = new mysqli(...)

try {
    if (!isset($conn) || $conn->connect_error) {
        echo json_encode(["success" => false, "message" => "Database connection failed"]);
        exit;
    }

    $patient_id = $_POST['patient_id'] ?? null;
    if (!$patient_id) {
        echo json_encode(["success" => false, "message" => "No patient specified"]);
        exit;
    }

    // Ensure patient record exists
    $check = $conn->prepare("SELECT * FROM patient_other_info WHERE patient_id = ?");
    $check->bind_param("i", $patient_id);
    $check->execute();
    $result = $check->get_result();
    $row = $result->fetch_assoc();

    if (!$row) {
        $stmt = $conn->prepare("
            INSERT INTO patient_other_info
            (patient_id, nhts_pr, four_ps, indigenous_people, pwd,
             philhealth_flag, philhealth_number,
             sss_flag, sss_number,
             gsis_flag, gsis_number)
            VALUES (?, 0,0,0,0, 0,NULL, 0,NULL, 0,NULL)
        ");
        $stmt->bind_param("i", $patient_id);
        $stmt->execute();
    }

    // ✅ Directly use POST values (0 if unchecked / not set)
    $nhts     = isset($_POST['nhts_pr']) ? 1 : 0;
    $fourps   = isset($_POST['four_ps']) ? 1 : 0;
    $ip       = isset($_POST['indigenous_people']) ? 1 : 0;
    $pwd      = isset($_POST['pwd']) ? 1 : 0;

    $philflag = isset($_POST['philhealth_flag']) ? 1 : 0;
    $philno   = $philflag ? ($_POST['philhealth_number'] ?? null) : null;

    $sssflag  = isset($_POST['sss_flag']) ? 1 : 0;
    $sssno    = $sssflag ? ($_POST['sss_number'] ?? null) : null;

    $gsisflag = isset($_POST['gsis_flag']) ? 1 : 0;
    $gsisno   = $gsisflag ? ($_POST['gsis_number'] ?? null) : null;

    // Update record
    $stmt = $conn->prepare("
        UPDATE patient_other_info SET
            nhts_pr=?, four_ps=?, indigenous_people=?, pwd=?,
            philhealth_flag=?, philhealth_number=?,
            sss_flag=?, sss_number=?,
            gsis_flag=?, gsis_number=?
        WHERE patient_id=?
    ");
    $stmt->bind_param(
        "iiiisiisisi",
        $nhts,
        $fourps,
        $ip,
        $pwd,
        $philflag,
        $philno,
        $sssflag,
        $sssno,
        $gsisflag,
        $gsisno,
        $patient_id
    );
    $stmt->execute();
    // Build updated memberships list
    $memberships = [];
    if ($nhts) $memberships[] = ["field" => "nhts_pr", "label" => "National Household Targeting System - Poverty Reduction (NHTS)"];
    if ($fourps) $memberships[] = ["field" => "four_ps", "label" => "Pantawin Pamilyang Pilipino Program (4Ps)"];
    if ($ip) $memberships[] = ["field" => "indigenous_people", "label" => "Indigenous People (IP)"];
    if ($pwd) $memberships[] = ["field" => "pwd", "label" => "Person with Disabilities (PWD)"];
    if ($philflag) $memberships[] = ["field" => "philhealth_flag", "label" => "PhilHealth (" . ($philno ?: "N/A") . ")"];
    if ($sssflag) $memberships[] = ["field" => "sss_flag", "label" => "SSS (" . ($sssno ?: "N/A") . ")"];
    if ($gsisflag) $memberships[] = ["field" => "gsis_flag", "label" => "GSIS (" . ($gsisno ?: "N/A") . ")"];

    echo json_encode(["success" => true, "memberships" => $memberships]);
} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
