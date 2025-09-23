<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', 0);

header('Content-Type: application/json');
require_once "conn.php";

$patient_id = $_GET['patient_id'] ?? null;
if (!$patient_id) {
    echo json_encode(["success" => false, "message" => "No patient specified"]);
    exit;
}

try {
    $stmt = $conn->prepare("
        SELECT nhts_pr, four_ps, indigenous_people, pwd,
               philhealth_flag, philhealth_number,
               sss_flag, sss_number,
               gsis_flag, gsis_number
        FROM patient_other_info
        WHERE patient_id = ?
    ");
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    $memberships = [];
    if ($row) {
        if ($row['nhts_pr']) $memberships[] = ["field" => "nhts_pr", "label" => "National Household Targeting System - Poverty Reduction (NHTS)"];
        if ($row['four_ps']) $memberships[] = ["field" => "four_ps", "label" => "Pantawid Pamilyang Pilipino Program (4Ps)"];
        if ($row['indigenous_people']) $memberships[] = ["field" => "indigenous_people", "label" => "Indigenous People (IP)"];
        if ($row['pwd']) $memberships[] = ["field" => "pwd", "label" => "Person with Disabilities (PWD)"];
        if ($row['philhealth_flag']) $memberships[] = ["field" => "philhealth_flag", "label" => "PhilHealth (" . ($row['philhealth_number'] ?: "N/A") . ")"];
        if ($row['sss_flag']) $memberships[] = ["field" => "sss_flag", "label" => "SSS (" . ($row['sss_number'] ?: "N/A") . ")"];
        if ($row['gsis_flag']) $memberships[] = ["field" => "gsis_flag", "label" => "GSIS (" . ($row['gsis_number'] ?: "N/A") . ")"];
    }

    echo json_encode([
        "success" => true,
        "memberships" => $memberships,
        "values" => $row ?? []
    ]);
} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
