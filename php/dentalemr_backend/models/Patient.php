<?php
class Patient extends BaseModel {
    public function __construct($db) {
        parent::__construct($db);
        $this->table = "patients";
    }

    public function create($data) {
        try {
            $this->db->beginTransaction();

            // Validate required fields
            $this->validateRequiredFields($data, ['surname', 'firstname', 'date_of_birth', 'sex']);

            // Insert patient basic info
            $query = "INSERT INTO patients SET surname=:surname, firstname=:firstname, middlename=:middlename, date_of_birth=:date_of_birth, place_of_birth=:place_of_birth, age=:age, months_old=:months_old, sex=:sex, pregnant=:pregnant, address=:address, occupation=:occupation, guardian=:guardian";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(":surname", $data['surname']);
            $stmt->bindParam(":firstname", $data['firstname']);
            $stmt->bindParam(":middlename", $data['middlename']);
            $stmt->bindParam(":date_of_birth", $data['date_of_birth']);
            $stmt->bindParam(":place_of_birth", $data['place_of_birth']);
            $stmt->bindParam(":age", $data['age']);
            $stmt->bindParam(":months_old", $data['months_old']);
            $stmt->bindParam(":sex", $data['sex']);
            $stmt->bindParam(":pregnant", $data['pregnant']);
            $stmt->bindParam(":address", $data['address']);
            $stmt->bindParam(":occupation", $data['occupation']);
            $stmt->bindParam(":guardian", $data['guardian']);
            
            if (!$stmt->execute()) {
                throw new Exception("Failed to create patient");
            }
            
            $patient_id = $this->db->lastInsertId();

            // Insert other related data
            if (isset($data['other_info'])) {
                $this->createOtherInfo($patient_id, $data['other_info']);
            }
            if (isset($data['vital_signs'])) {
                $this->createVitalSigns($patient_id, $data['vital_signs']);
            }
            if (isset($data['medical_history'])) {
                $this->createMedicalHistory($patient_id, $data['medical_history']);
            }
            if (isset($data['dietary_habits'])) {
                $this->createDietaryHabits($patient_id, $data['dietary_habits']);
            }

            $this->db->commit();
            return $patient_id;

        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    private function createOtherInfo($patient_id, $data) {
        $query = "INSERT INTO patient_other_info SET patient_id=:patient_id, nhts_pr=:nhts_pr, four_ps=:four_ps, indigenous_people=:indigenous_people, pwd=:pwd, philhealth_flag=:philhealth_flag, philhealth_number=:philhealth_number, sss_flag=:sss_flag, sss_number=:sss_number, gsis_flag=:gsis_flag, gsis_number=:gsis_number";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(":patient_id", $patient_id);
        $stmt->bindParam(":nhts_pr", $data['nhts_pr'] ?? 0);
        $stmt->bindParam(":four_ps", $data['four_ps'] ?? 0);
        $stmt->bindParam(":indigenous_people", $data['indigenous_people'] ?? 0);
        $stmt->bindParam(":pwd", $data['pwd'] ?? 0);
        $stmt->bindParam(":philhealth_flag", $data['philhealth_flag'] ?? 0);
        $stmt->bindParam(":philhealth_number", $data['philhealth_number'] ?? '');
        $stmt->bindParam(":sss_flag", $data['sss_flag'] ?? 0);
        $stmt->bindParam(":sss_number", $data['sss_number'] ?? '');
        $stmt->bindParam(":gsis_flag", $data['gsis_flag'] ?? 0);
        $stmt->bindParam(":gsis_number", $data['gsis_number'] ?? '');
        
        return $stmt->execute();
    }

    private function createVitalSigns($patient_id, $data) {
        $query = "INSERT INTO vital_signs SET patient_id=:patient_id, blood_pressure=:blood_pressure, pulse_rate=:pulse_rate, temperature=:temperature, weight=:weight";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(":patient_id", $patient_id);
        $stmt->bindParam(":blood_pressure", $data['blood_pressure'] ?? '');
        $stmt->bindParam(":pulse_rate", $data['pulse_rate'] ?? 0);
        $stmt->bindParam(":temperature", $data['temperature'] ?? 0);
        $stmt->bindParam(":weight", $data['weight'] ?? 0);
        
        return $stmt->execute();
    }

    private function createMedicalHistory($patient_id, $data) {
        $query = "INSERT INTO medical_history SET patient_id=:patient_id, allergies_flag=:allergies_flag, allergies_details=:allergies_details, hypertension_cva=:hypertension_cva, diabetes_mellitus=:diabetes_mellitus, blood_disorders=:blood_disorders, heart_disease=:heart_disease, thyroid_disorders=:thyroid_disorders, hepatitis_flag=:hepatitis_flag, hepatitis_details=:hepatitis_details, malignancy_flag=:malignancy_flag, malignancy_details=:malignancy_details, prev_hospitalization_flag=:prev_hospitalization_flag, last_admission_date=:last_admission_date, admission_cause=:admission_cause, surgery_details=:surgery_details, blood_transfusion_flag=:blood_transfusion_flag, blood_transfusion=:blood_transfusion, tattoo=:tattoo, other_conditions_flag=:other_conditions_flag, other_conditions=:other_conditions";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(":patient_id", $patient_id);
        $stmt->bindParam(":allergies_flag", $data['allergies_flag'] ?? 0);
        $stmt->bindParam(":allergies_details", $data['allergies_details'] ?? '');
        $stmt->bindParam(":hypertension_cva", $data['hypertension_cva'] ?? 0);
        $stmt->bindParam(":diabetes_mellitus", $data['diabetes_mellitus'] ?? 0);
        $stmt->bindParam(":blood_disorders", $data['blood_disorders'] ?? 0);
        $stmt->bindParam(":heart_disease", $data['heart_disease'] ?? 0);
        $stmt->bindParam(":thyroid_disorders", $data['thyroid_disorders'] ?? 0);
        $stmt->bindParam(":hepatitis_flag", $data['hepatitis_flag'] ?? 0);
        $stmt->bindParam(":hepatitis_details", $data['hepatitis_details'] ?? '');
        $stmt->bindParam(":malignancy_flag", $data['malignancy_flag'] ?? 0);
        $stmt->bindParam(":malignancy_details", $data['malignancy_details'] ?? '');
        $stmt->bindParam(":prev_hospitalization_flag", $data['prev_hospitalization_flag'] ?? 0);
        $stmt->bindParam(":last_admission_date", $data['last_admission_date'] ?? null);
        $stmt->bindParam(":admission_cause", $data['admission_cause'] ?? '');
        $stmt->bindParam(":surgery_details", $data['surgery_details'] ?? '');
        $stmt->bindParam(":blood_transfusion_flag", $data['blood_transfusion_flag'] ?? 0);
        $stmt->bindParam(":blood_transfusion", $data['blood_transfusion'] ?? null);
        $stmt->bindParam(":tattoo", $data['tattoo'] ?? 0);
        $stmt->bindParam(":other_conditions_flag", $data['other_conditions_flag'] ?? 0);
        $stmt->bindParam(":other_conditions", $data['other_conditions'] ?? '');
        
        return $stmt->execute();
    }

    private function createDietaryHabits($patient_id, $data) {
        $query = "INSERT INTO dietary_habits SET patient_id=:patient_id, sugar_flag=:sugar_flag, sugar_details=:sugar_details, alcohol_flag=:alcohol_flag, alcohol_details=:alcohol_details, tobacco_flag=:tobacco_flag, tobacco_details=:tobacco_details, betel_nut_flag=:betel_nut_flag, betel_nut_details=:betel_nut_details";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(":patient_id", $patient_id);
        $stmt->bindParam(":sugar_flag", $data['sugar_flag'] ?? 0);
        $stmt->bindParam(":sugar_details", $data['sugar_details'] ?? '');
        $stmt->bindParam(":alcohol_flag", $data['alcohol_flag'] ?? 0);
        $stmt->bindParam(":alcohol_details", $data['alcohol_details'] ?? '');
        $stmt->bindParam(":tobacco_flag", $data['tobacco_flag'] ?? 0);
        $stmt->bindParam(":tobacco_details", $data['tobacco_details'] ?? '');
        $stmt->bindParam(":betel_nut_flag", $data['betel_nut_flag'] ?? 0);
        $stmt->bindParam(":betel_nut_details", $data['betel_nut_details'] ?? '');
        
        return $stmt->execute();
    }

    public function read($patient_id) {
        $query = "SELECT p.*, poi.*, mh.*, dh.*, vs.blood_pressure, vs.pulse_rate, vs.temperature, vs.weight 
                 FROM patients p 
                 LEFT JOIN patient_other_info poi ON p.patient_id = poi.patient_id 
                 LEFT JOIN medical_history mh ON p.patient_id = mh.patient_id 
                 LEFT JOIN dietary_habits dh ON p.patient_id = dh.patient_id 
                 LEFT JOIN vital_signs vs ON p.patient_id = vs.patient_id 
                 WHERE p.patient_id = :patient_id 
                 ORDER BY vs.recorded_at DESC LIMIT 1";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(":patient_id", $patient_id);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getAll($page = 1, $limit = 10, $search = '') {
        $offset = ($page - 1) * $limit;
        
        $query = "SELECT p.* FROM patients p 
                 WHERE (:search = '' OR p.surname LIKE :search_like OR p.firstname LIKE :search_like OR p.middlename LIKE :search_like) 
                 ORDER BY p.created_at DESC 
                 LIMIT :limit OFFSET :offset";
        
        $stmt = $this->db->prepare($query);
        $search_like = "%$search%";
        $stmt->bindParam(":search", $search);
        $stmt->bindParam(":search_like", $search_like);
        $stmt->bindParam(":limit", $limit, PDO::PARAM_INT);
        $stmt->bindParam(":offset", $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function update($patient_id, $data) {
        $query = "UPDATE patients SET surname=:surname, firstname=:firstname, middlename=:middlename, date_of_birth=:date_of_birth, place_of_birth=:place_of_birth, age=:age, months_old=:months_old, sex=:sex, pregnant=:pregnant, address=:address, occupation=:occupation, guardian=:guardian, updated_at=NOW() WHERE patient_id=:patient_id";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(":patient_id", $patient_id);
        $stmt->bindParam(":surname", $data['surname']);
        $stmt->bindParam(":firstname", $data['firstname']);
        $stmt->bindParam(":middlename", $data['middlename']);
        $stmt->bindParam(":date_of_birth", $data['date_of_birth']);
        $stmt->bindParam(":place_of_birth", $data['place_of_birth']);
        $stmt->bindParam(":age", $data['age']);
        $stmt->bindParam(":months_old", $data['months_old']);
        $stmt->bindParam(":sex", $data['sex']);
        $stmt->bindParam(":pregnant", $data['pregnant']);
        $stmt->bindParam(":address", $data['address']);
        $stmt->bindParam(":occupation", $data['occupation']);
        $stmt->bindParam(":guardian", $data['guardian']);
        
        return $stmt->execute();
    }

    public function getTotalCount($search = '') {
        $query = "SELECT COUNT(*) as total FROM patients p 
                 WHERE (:search = '' OR p.surname LIKE :search_like OR p.firstname LIKE :search_like)";
        
        $stmt = $this->db->prepare($query);
        $search_like = "%$search%";
        $stmt->bindParam(":search", $search);
        $stmt->bindParam(":search_like", $search_like);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['total'];
    }
}
?>