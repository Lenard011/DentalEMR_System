<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);

$host = "localhost";
$user = "root";
$pass = "";
$db   = "dentalemr_system";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    echo json_encode(["success" => false, "error" => "DB connection failed"]);
    exit;
}

$input = json_decode(file_get_contents("php://input"), true);
if (!$input || !isset($input['patient_id'])) {
    echo json_encode(["success" => false, "error" => "Missing patient_id"]);
    exit;
}

$pid                 = intval($input['patient_id']);
$orally_fit_child    = $input['orally_fit_child'] ?? "";
$dental_caries       = $input['dental_caries'] ?? "";
$gingivitis          = $input['gingivitis'] ?? "";
$periodontal_disease = $input['periodontal_disease'] ?? "";
$others              = $input['others'] ?? "";
$debris              = $input['debris'] ?? "";
$calculus            = $input['calculus'] ?? "";
$abnormal_growth     = $input['abnormal_growth'] ?? "";
$cleft_palate        = $input['cleft_palate'] ?? "";

$perm_teeth_present   = intval($input['perm_teeth_present'] ?? 0);
$perm_sound_teeth     = intval($input['perm_sound_teeth'] ?? 0);
$perm_decayed_teeth_d = intval($input['perm_decayed_teeth_d'] ?? 0);
$perm_missing_teeth_m = intval($input['perm_missing_teeth_m'] ?? 0);
$perm_filled_teeth_f  = intval($input['perm_filled_teeth_f'] ?? 0);
$perm_total_dmf       = intval($input['perm_total_dmf'] ?? 0);

$temp_teeth_present   = intval($input['temp_teeth_present'] ?? 0);
$temp_sound_teeth     = intval($input['temp_sound_teeth'] ?? 0);
$temp_decayed_teeth_d = intval($input['temp_decayed_teeth_d'] ?? 0);
$temp_filled_teeth_f  = intval($input['temp_filled_teeth_f'] ?? 0);
$temp_total_df        = intval($input['temp_total_df'] ?? 0);

$r = $conn->query("SELECT id FROM oral_health_condition WHERE patient_id = {$pid} LIMIT 1");

if ($r && $r->num_rows) {
    // UPDATE
    $sql = "UPDATE oral_health_condition SET 
        orally_fit_child=?, dental_caries=?, gingivitis=?, periodontal_disease=?, others=?, debris=?, calculus=?, abnormal_growth=?, cleft_palate=?,
        perm_teeth_present=?, perm_sound_teeth=?, perm_decayed_teeth_d=?, perm_missing_teeth_m=?, perm_filled_teeth_f=?, perm_total_dmf=?,
        temp_teeth_present=?, temp_sound_teeth=?, temp_decayed_teeth_d=?, temp_filled_teeth_f=?, temp_total_df=?,
        updated_at=NOW()
        WHERE patient_id=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
        "ssssssssiiiiiiiiiiiis",
        $orally_fit_child,
        $dental_caries,
        $gingivitis,
        $periodontal_disease,
        $others,
        $debris,
        $calculus,
        $abnormal_growth,
        $cleft_palate,
        $perm_teeth_present,
        $perm_sound_teeth,
        $perm_decayed_teeth_d,
        $perm_missing_teeth_m,
        $perm_filled_teeth_f,
        $perm_total_dmf,
        $temp_teeth_present,
        $temp_sound_teeth,
        $temp_decayed_teeth_d,
        $temp_filled_teeth_f,
        $temp_total_df,
        $pid
    );
    $stmt->execute();
} else {
    // INSERT
    $sql = "INSERT INTO oral_health_condition (
        patient_id, orally_fit_child, dental_caries, gingivitis, periodontal_disease, others,
        debris, calculus, abnormal_growth, cleft_palate,
        perm_teeth_present, perm_sound_teeth, perm_decayed_teeth_d, perm_missing_teeth_m, perm_filled_teeth_f, perm_total_dmf,
        temp_teeth_present, temp_sound_teeth, temp_decayed_teeth_d, temp_filled_teeth_f, temp_total_df,
        created_at, updated_at
    ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
        "issssssssiiiiiiiiiii",
        $pid,
        $orally_fit_child,
        $dental_caries,
        $gingivitis,
        $periodontal_disease,
        $others,
        $debris,
        $calculus,
        $abnormal_growth,
        $cleft_palate,
        $perm_teeth_present,
        $perm_sound_teeth,
        $perm_decayed_teeth_d,
        $perm_missing_teeth_m,
        $perm_filled_teeth_f,
        $perm_total_dmf,
        $temp_teeth_present,
        $temp_sound_teeth,
        $temp_decayed_teeth_d,
        $temp_filled_teeth_f,
        $temp_total_df
    );
    $stmt->execute();
}

echo json_encode(["success" => true, "message" => "Oral health saved"]);
