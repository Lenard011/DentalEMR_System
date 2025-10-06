<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/conns.php'; // assumes $db = new PDO(...)

if (!$db) exit(json_encode(["success" => false, "message" => "DB not available"]));

$input = json_decode(file_get_contents("php://input"), true);
if (!is_array($input)) exit(json_encode(["success" => false, "message" => "Invalid JSON"]));

$patient_id = isset($input['patient_id']) ? intval($input['patient_id']) : 0;
if ($patient_id <= 0) exit(json_encode(["success" => false, "message" => "No patient selected"]));

// ✅ Check patient exists
$checkPatient = $db->prepare("SELECT 1 FROM patients WHERE patient_id = :id LIMIT 1");
$checkPatient->execute([':id' => $patient_id]);
if (!$checkPatient->fetchColumn()) {
    exit(json_encode(["success" => false, "message" => "Patient not found"]));
}

try {
    // See if oral health record already exists
    $existsStmt = $db->prepare("SELECT id FROM oral_health_condition WHERE patient_id = :id LIMIT 1");
    $existsStmt->execute([':id' => $patient_id]);
    $exists = $existsStmt->fetchColumn();

    if ($exists) {
        // UPDATE
        $stmt = $db->prepare("
            UPDATE oral_health_condition SET 
                orally_fit_child = :ofc,
                dental_caries = :dc,
                gingivitis = :gg,
                periodontal_disease = :pd,
                others = :others,
                debris = :debris,
                calculus = :calc,
                abnormal_growth = :ag,
                cleft_palate = :cp,
                perm_teeth_present = :ptp,
                perm_sound_teeth = :pst,
                perm_decayed_teeth_d = :pdd,
                perm_missing_teeth_m = :pmm,
                perm_filled_teeth_f = :pff,
                perm_total_dmf = :ptdmf,
                temp_teeth_present = :ttp,
                temp_sound_teeth = :tst,
                temp_decayed_teeth_d = :tdd,
                temp_filled_teeth_f = :tff,
                temp_total_df = :ttdf,
                updated_at = NOW()
            WHERE patient_id = :id
        ");
    } else {
        // INSERT
        $stmt = $db->prepare("
            INSERT INTO oral_health_condition (
                patient_id, orally_fit_child, dental_caries, gingivitis, periodontal_disease, others,
                debris, calculus, abnormal_growth, cleft_palate,
                perm_teeth_present, perm_sound_teeth, perm_decayed_teeth_d, perm_missing_teeth_m, perm_filled_teeth_f, perm_total_dmf,
                temp_teeth_present, temp_sound_teeth, temp_decayed_teeth_d, temp_filled_teeth_f, temp_total_df,
                created_at, updated_at
            ) VALUES (
                :id, :ofc, :dc, :gg, :pd, :others,
                :debris, :calc, :ag, :cp,
                :ptp, :pst, :pdd, :pmm, :pff, :ptdmf,
                :ttp, :tst, :tdd, :tff, :ttdf,
                NOW(), NOW()
            )
        ");
    }

    $stmt->execute([
        ':id'    => $patient_id,
        ':ofc'   => $input['orally_fit_child'] ?? "",
        ':dc'    => $input['dental_caries'] ?? "",
        ':gg'    => $input['gingivitis'] ?? "",
        ':pd'    => $input['periodontal_disease'] ?? "",
        ':others' => $input['others'] ?? "",
        ':debris' => $input['debris'] ?? "",
        ':calc'  => $input['calculus'] ?? "",
        ':ag'    => $input['abnormal_growth'] ?? "",
        ':cp'    => $input['cleft_palate'] ?? "",
        ':ptp'   => intval($input['perm_teeth_present'] ?? 0),
        ':pst'   => intval($input['perm_sound_teeth'] ?? 0),
        ':pdd'   => intval($input['perm_decayed_teeth_d'] ?? 0),
        ':pmm'   => intval($input['perm_missing_teeth_m'] ?? 0),
        ':pff'   => intval($input['perm_filled_teeth_f'] ?? 0),
        ':ptdmf' => intval($input['perm_total_dmf'] ?? 0),
        ':ttp'   => intval($input['temp_teeth_present'] ?? 0),
        ':tst'   => intval($input['temp_sound_teeth'] ?? 0),
        ':tdd'   => intval($input['temp_decayed_teeth_d'] ?? 0),
        ':tff'   => intval($input['temp_filled_teeth_f'] ?? 0),
        ':ttdf'  => intval($input['temp_total_df'] ?? 0),
    ]);

    exit(json_encode(["success" => true, "message" => "Oral health record saved successfully!"]));
} catch (Exception $e) {
    exit(json_encode(["success" => false, "message" => "Execute failed: " . $e->getMessage()]));
}
