<?php
// migrate_to_history.php
header('Content-Type: text/html; charset=utf-8');

try {
    require_once __DIR__ . '/conns.php';
    $db = $pdo ?? null;
    
    if (!$db) {
        throw new Exception("Database connection failed");
    }

    echo "<h1>Migration to Treatment History System</h1>";
    
    // Check if table exists
    $tableExists = $db->query("SHOW TABLES LIKE 'treatment_history'")->rowCount() > 0;
    
    if (!$tableExists) {
        echo "<p>Creating treatment_history table...</p>";
        
        $createSql = "CREATE TABLE treatment_history (
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
        
        $db->exec($createSql);
        echo "<p style='color:green'>✓ Table created successfully</p>";
    } else {
        echo "<p style='color:orange'>✓ Table already exists</p>";
    }
    
    // Copy data
    echo "<p>Copying existing data...</p>";
    
    $countBefore = $db->query("SELECT COUNT(*) as count FROM treatment_history")->fetch(PDO::FETCH_ASSOC)['count'];
    
    $copySql = "INSERT IGNORE INTO treatment_history 
                (patient_id, tooth_id, treatment_code, treatment_date, created_at)
                SELECT 
                    patient_id, 
                    tooth_id, 
                    treatment_code, 
                    DATE(created_at) as treatment_date,
                    created_at
                FROM services_monitoring_chart
                WHERE NOT EXISTS (
                    SELECT 1 FROM treatment_history th 
                    WHERE th.patient_id = services_monitoring_chart.patient_id 
                    AND th.tooth_id = services_monitoring_chart.tooth_id 
                    AND th.treatment_date = DATE(services_monitoring_chart.created_at)
                )";
    
    $stmt = $db->prepare($copySql);
    $stmt->execute();
    $copied = $stmt->rowCount();
    
    $countAfter = $db->query("SELECT COUNT(*) as count FROM treatment_history")->fetch(PDO::FETCH_ASSOC)['count'];
    
    echo "<p style='color:green'>✓ Copied $copied new records</p>";
    echo "<p>Total records in history table: $countAfter</p>";
    
    // Show sample data
    echo "<h2>Sample Data (showing multiple treatments per tooth)</h2>";
    
    $sampleSql = "SELECT 
                    p.firstname, 
                    p.surname,
                    t.fdi_number,
                    th.treatment_code,
                    th.treatment_date,
                    COUNT(*) as treatment_count
                FROM treatment_history th
                JOIN patients p ON th.patient_id = p.patient_id
                JOIN teeth t ON th.tooth_id = t.tooth_id
                GROUP BY th.patient_id, th.tooth_id
                HAVING treatment_count > 1
                ORDER BY treatment_count DESC
                LIMIT 10";
    
    $stmt = $db->query($sampleSql);
    $samples = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($samples) > 0) {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>Patient</th><th>Tooth</th><th>Treatments</th><th>Count</th></tr>";
        foreach ($samples as $sample) {
            echo "<tr>";
            echo "<td>{$sample['firstname']} {$sample['surname']}</td>";
            echo "<td>{$sample['fdi_number']}</td>";
            echo "<td>{$sample['treatment_code']}</td>";
            echo "<td>{$sample['treatment_count']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No patients with multiple treatments on same tooth yet.</p>";
    }
    
    echo "<h2 style='color:green'>✓ Migration Complete!</h2>";
    echo "<p>Your system now supports multiple treatments per tooth.</p>";
    echo "<p>New treatments will be saved to the treatment_history table.</p>";

} catch (Exception $e) {
    echo "<h1 style='color:red'>Error</h1>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}