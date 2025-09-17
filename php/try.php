<?php include "conn.php"; ?>
<?php
    // Database connection
    $conn = new mysqli("localhost", "root", "", "dentalemr_system");
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    if (isset($_POST["patient"])) {
        /*
     * STEP 1: PATIENT INFO
     */
        $surname = $_POST["surname"] ?? null;
        $firstname   = $_POST["firstname"] ?? null;
        $middlename  = $_POST["middlename"] ?? null;
        $date_of_birth = $_POST["dob"] ?? null;
        $placeofbirth  = $_POST["pob"] ?? null;
        $age         = $_POST["age"] ?? null;
        $sex         = $_POST["sex"] ?? null;
        $address     = $_POST["address"] ?? null;
        $occupation  = $_POST["occupation"] ?? null;

        $stmt = $conn->prepare("INSERT INTO patients 
        (surname, firstname, middlename, date_of_birth, place_of_birth, age, sex, address, occupation) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssssss", $surname, $firstname, $middlename, $date_of_birth, $placeofbirth, $age, $sex, $address, $occupation);

        if ($stmt->execute()) {
            $patient_id = $stmt->insert_id; // PK for relations
        } else {
            die("❌ Patient insert failed: " . $stmt->error);
        }
        $stmt->close();


        /*
     * STEP 2: OTHER PATIENT INFO
     */
        $nhts     = isset($_POST['nhts_pr']) ? 1 : 0;
        $fourps   = isset($_POST['four_ps']) ? 1 : 0;
        $ip       = isset($_POST['indigenous_people']) ? 1 : 0;
        $pwd      = isset($_POST['pwd']) ? 1 : 0;
        $philflag = isset($_POST['philhealth_flag']) ? 1 : 0;
        $philno   = $_POST['philhealth_number'] ?? null;
        $sssflag  = isset($_POST['sss_flag']) ? 1 : 0;
        $sssno    = $_POST['sss_number'] ?? null;
        $gsisflag = isset($_POST['gsis_flag']) ? 1 : 0;
        $gsisno   = $_POST['gsis_number'] ?? null;

        $stmt = $conn->prepare("INSERT INTO patient_other_info 
        (patient_id, nhts_pr, four_ps, indigenous_people, pwd, philhealth_flag, philhealth_number, 
         sss_flag, sss_number, gsis_flag, gsis_number) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iiiiisissis", $patient_id, $nhts, $fourps, $ip, $pwd, $philflag, $philno, $sssflag, $sssno, $gsisflag, $gsisno);
        $stmt->execute();
        $stmt->close();


        /*
     * STEP 3: VITAL SIGNS
     */
        $bp   = $_POST['blood_pressure'] ?? null;
        $pr   = $_POST['pulse_rate'] ?? null;
        $temp = $_POST['temperature'] ?? null;
        $wt   = $_POST['weight'] ?? null;

        $stmt = $conn->prepare("INSERT INTO vital_signs (patient_id, blood_pressure, pulse_rate, temperature, weight) 
        VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("isidd", $patient_id, $bp, $pr, $temp, $wt);
        $stmt->execute();
        $stmt->close();


        /*
     * STEP 4: MEDICAL HISTORY
     */
        $allergies_flag   = isset($_POST['allergies_flag']) ? 1 : 0;
        $allergies_det    = $_POST['allergies_details'] ?? null;
        $hypertension     = isset($_POST['hypertension_cva']) ? 1 : 0;
        $diabetes         = isset($_POST['diabetes_mellitus']) ? 1 : 0;
        $blood_disorders  = isset($_POST['blood_disorders']) ? 1 : 0;
        $heart_disease    = isset($_POST['heart_disease']) ? 1 : 0;
        $thyroid          = isset($_POST['thyroid_disorders']) ? 1 : 0;
        $hepatitis_flag   = isset($_POST['hepatitis_flag']) ? 1 : 0;
        $hepatitis_det    = $_POST['hepatitis_details'] ?? null;
        $malignancy_flag  = isset($_POST['malignancy_flag']) ? 1 : 0;
        $malignancy_det   = $_POST['malignancy_details'] ?? null;
        $prev_hosp_flag   = isset($_POST['prev_hospitalization_flag']) ? 1 : 0;
        $admission_cause  = $_POST['admission_cause'] ?? null;
        $surgery_det      = $_POST['surgery_details'] ?? null;
        $blood_trans_flag = isset($_POST['blood_transfusion_flag']) ? 1 : 0;
        $blood_trans      = $_POST['blood_transfusion'] ?? null;
        $tattoo           = isset($_POST['tattoo']) ? 1 : 0;
        $other_conditions_flag  = isset($_POST['other_conditions_flag']) ? 1 : 0;
        $other_conditions = $_POST['other_conditions'] ?? null;

        $stmt = $conn->prepare("INSERT INTO medical_history 
        (patient_id, allergies_flag, allergies_details, hypertension_cva, diabetes_mellitus, 
         blood_disorders, heart_disease, thyroid_disorders, hepatitis_flag, hepatitis_details,
         malignancy_flag, malignancy_details, prev_hospitalization_flag, admission_cause, 
         surgery_details, blood_transfusion_flag, blood_transfusion, tattoo, other_conditions_flag, other_conditions) 
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");

        $stmt->bind_param(
            "isiiiiiisisissisiiss",
            $patient_id,
            $allergies_flag,
            $allergies_det,
            $hypertension,
            $diabetes,
            $blood_disorders,
            $heart_disease,
            $thyroid,
            $hepatitis_flag,
            $hepatitis_det,
            $malignancy_flag,
            $malignancy_det,
            $prev_hosp_flag,
            $admission_cause,
            $surgery_det,
            $blood_trans_flag,
            $blood_trans,
            $tattoo,
            $other_conditions_flag,
            $other_conditions
        );
        $stmt->execute();
        $stmt->close();
        /*
     * STEP 5: DIETARY HABITS
     */
        $sugar_flag  = isset($_POST['sugar_flag']) ? 1 : 0;
        $sugar_det   = $_POST['sugar_details'] ?? null;
        $alcohol_flag = isset($_POST['alcohol_flag']) ? 1 : 0;
        $alcohol_det  = $_POST['alcohol_details'] ?? null;
        $tobacco_flag = isset($_POST['tobacco_flag']) ? 1 : 0;
        $tobacco_det  = $_POST['tobacco_details'] ?? null;
        $betel_flag   = isset($_POST['betel_nut_flag']) ? 1 : 0;
        $betel_det    = $_POST['betel_nut_details'] ?? null;

        $stmt = $conn->prepare("INSERT INTO dietary_habits 
        (patient_id, sugar_flag, sugar_details, alcohol_flag, alcohol_details, 
         tobacco_flag, tobacco_details, betel_nut_flag, betel_nut_details) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iisisisis", $patient_id, $sugar_flag, $sugar_det, $alcohol_flag, $alcohol_det, $tobacco_flag, $tobacco_det, $betel_flag, $betel_det);
        $stmt->execute();
        $stmt->close();


        /*
     * SUCCESS
     */
        echo "<script>
        alert('✅ Patient record saved successfully!');
        window.location.href='addpatient.html';
      </script>";
    }

    $conn->close();
    ?>
