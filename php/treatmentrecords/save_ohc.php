<?php
header('Content-Type: application/json');
require_once './conns.php'; // adjust path if needed

try {
    $data = json_decode(file_get_contents("php://input"), true);
    if (!$data) {
        echo json_encode(["success" => false, "message" => "Invalid JSON payload."]);
        exit;
    }

    $patient_id = intval($data["patient_id"] ?? 0);
    if ($patient_id <= 0) {
        echo json_encode(["success" => false, "message" => "Invalid patient ID."]);
        exit;
    }

    // Collect all fields safely
    $fields = [
        "orally_fit_child",
        "dental_caries",
        "gingivitis",
        "periodontal_disease",
        "others",
        "debris",
        "calculus",
        "abnormal_growth",
        "cleft_palate",
        "perm_teeth_present",
        "perm_sound_teeth",
        "perm_decayed_teeth_d",
        "perm_missing_teeth_m",
        "perm_filled_teeth_f",
        "perm_total_dmf",
        "temp_teeth_present",
        "temp_sound_teeth",
        "temp_decayed_teeth_d",
        "temp_filled_teeth_f",
        "temp_total_df"
    ];

    $values = [];
    foreach ($fields as $f) {
        $values[$f] = $data[$f] ?? "";
    }

    // ✅ Always INSERT new record
    $sql = "INSERT INTO oral_health_condition (
        patient_id, orally_fit_child, dental_caries, gingivitis, periodontal_disease,
        others, debris, calculus, abnormal_growth, cleft_palate,
        perm_teeth_present, perm_sound_teeth, perm_decayed_teeth_d,
        perm_missing_teeth_m, perm_filled_teeth_f, perm_total_dmf,
        temp_teeth_present, temp_sound_teeth, temp_decayed_teeth_d,
        temp_filled_teeth_f, temp_total_df, created_at, updated_at
    ) VALUES (
        :patient_id, :orally_fit_child, :dental_caries, :gingivitis, :periodontal_disease,
        :others, :debris, :calculus, :abnormal_growth, :cleft_palate,
        :perm_teeth_present, :perm_sound_teeth, :perm_decayed_teeth_d,
        :perm_missing_teeth_m, :perm_filled_teeth_f, :perm_total_dmf,
        :temp_teeth_present, :temp_sound_teeth, :temp_decayed_teeth_d,
        :temp_filled_teeth_f, :temp_total_df, NOW(), NOW()
    )";

    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_merge(["patient_id" => $patient_id], $values));

    echo json_encode(["success" => true, "message" => "✅ New Oral Health Condition record added."]);
} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => "Server Error: " . $e->getMessage()]);
}
