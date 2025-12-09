<?php
// create_history_table.php
header('Content-Type: application/json; charset=utf-8');

try {
    require_once __DIR__ . '/conns.php';
    $db = $pdo ?? null;
    
    if (!$db) {
        throw new Exception("Database connection failed");
    }

    // Create the table
    $sql = "CREATE TABLE IF NOT EXISTS treatment_history (
        history_id INT PRIMARY KEY AUTO_INCREMENT,
        patient_id INT NOT NULL,
        tooth_id INT NOT NULL,
        treatment_code VARCHAR(10) NOT NULL,
        treatment_date DATE NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        notes TEXT,
        FOREIGN KEY (patient_id) REFERENCES patients(patient_id) ON DELETE CASCADE,
        FOREIGN KEY (tooth_id) REFERENCES teeth(tooth_id) ON DELETE CASCADE,
        INDEX idx_patient_tooth (patient_id, tooth_id),
        INDEX idx_treatment_date (treatment_date),
        INDEX idx_patient_date (patient_id, treatment_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    $db->exec($sql);
    
    // Copy existing data
    $copySql = "INSERT INTO treatment_history 
                (patient_id, tooth_id, treatment_code, treatment_date, created_at)
                SELECT 
                    patient_id, 
                    tooth_id, 
                    treatment_code, 
                    DATE(created_at) as treatment_date,
                    created_at
                FROM services_monitoring_chart";
    
    $db->exec($copySql);
    
    $count = $db->query("SELECT COUNT(*) as count FROM treatment_history")->fetch(PDO::FETCH_ASSOC)['count'];
    
    echo json_encode([
        "success" => true,
        "message" => "Treatment history table created successfully. Copied $count records.",
        "records_copied" => $count
    ]);

} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "message" => "Error: " . $e->getMessage()
    ]);
}
exit;