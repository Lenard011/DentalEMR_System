<?php
header("Content-Type: application/json");
include_once "/DentalEMR_System/php/manageusers/log_history.php";
$conn = new mysqli("localhost", "u401132124_dentalclinic", "Mho_DentalClinic1st", "u401132124_mho_dentalemr");
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Database connection failed."]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conn->begin_transaction();

    try {
        // Get all archived patients
        $patients = $conn->query("SELECT * FROM archived_patients");
        if (!$patients || $patients->num_rows === 0) {
            throw new Exception("No archived patients found.");
        }

        $tables = [
            "archived_visits" => "visits",
            "archived_oral_health_condition" => "oral_health_condition",
            "archived_dietary_habits" => "dietary_habits",
            "archived_medical_history" => "medical_history",
            "archived_family_history" => "family_history",
            "archived_dental_history" => "dental_history"
        ];

        while ($patient = $patients->fetch_assoc()) {
            // Insert into main `patients` table
            $insert_patient = $conn->prepare("
                INSERT INTO patients (firstname, middlename, surname, date_of_birth, place_of_birth, age, sex, address, pregnant, occupation, guardian, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $insert_patient->bind_param(
                "ssssissssss",
                $patient['firstname'],
                $patient['middlename'],
                $patient['surname'],
                $patient['date_of_birth'],
                $patient['place_of_birth'],
                $patient['age'],
                $patient['sex'],
                $patient['address'],
                $patient['pregnant'],
                $patient['occupation'],
                $patient['guardian']
            );

            if (!$insert_patient->execute()) {
                throw new Exception("Failed to restore patient " . $patient['firstname'] . ": " . $conn->error);
            }

            // Get new patient ID
            $new_patient_id = $conn->insert_id;
            $old_patient_id = $patient['patient_id'];

            // Restore all related archived data
            foreach ($tables as $archive_table => $main_table) {
                // Check if archive table exists
                $check_table = $conn->query("SHOW TABLES LIKE '$archive_table'");
                if ($check_table->num_rows === 0) continue;

                $result = $conn->query("SELECT * FROM $archive_table WHERE patient_id = $old_patient_id");
                if ($result && $result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) {
                        $row['patient_id'] = $new_patient_id; // replace ID
                        unset($row['archived_at']); // remove archive timestamp if exists

                        $columns = implode(",", array_keys($row));
                        $values = implode(",", array_map(fn($v) =>
                            is_null($v) ? "NULL" : "'" . $conn->real_escape_string($v) . "'", array_values($row))
                        );

                        $insert_sql = "INSERT INTO $main_table ($columns) VALUES ($values)";
                        if (!$conn->query($insert_sql)) {
                            throw new Exception("Error restoring $main_table for patient $old_patient_id: " . $conn->error);
                        }
                    }

                    // Delete restored data from archive table
                    $conn->query("DELETE FROM $archive_table WHERE patient_id = $old_patient_id");
                }
            }

            // Delete patient from archived_patients
            $delete_patient = $conn->prepare("DELETE FROM archived_patients WHERE patient_id = ?");
            $delete_patient->bind_param("i", $old_patient_id);
            $delete_patient->execute();
        }

        $conn->commit();
        echo json_encode([
            "success" => true,
            "message" => "All archived patients and related records restored successfully."
        ]);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode([
            "success" => false,
            "message" => $e->getMessage()
        ]);
    }

    $conn->close();
}
?>
