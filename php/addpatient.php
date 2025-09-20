<?php include "conn.php"; ?>
<?php

if (isset($_POST["patient"])) {

  // ✅ Define required fields (text, number, select, date)
  $required = [
    "surname"        => "Surname",
    "firstname"      => "First Name",
    "middlename"     => "Middle Name",
    "dob"            => "Date of Birth",
    "pob"            => "Place of Birth",
    "age"            => "Age",
    "sex"            => "Sex",
    "address"        => "Address",
    "occupation"     => "Occupation",
    "pregnant"       => "Pregnant Status",
    "guardian"       => "Guardian",

    // Vital Signs
    "blood_pressure" => "Blood Pressure",
    "pulse_rate"     => "Pulse Rate",
    "temperature"    => "Temperature",
    "weight"         => "Weight",

    // Medical History (text/number/date only, not checkboxes)
    "allergies_details"      => "Allergies Details",
    "hepatitis_details"      => "Hepatitis Details",
    "malignancy_details"     => "Malignancy Details",
    "last_admission_date"    => "Last Admission Date",
    "admission_cause"        => "Admission Cause",
    "surgery_details"        => "Surgery Details",
    "blood_transfusion"      => "Blood Transfusion Details",
    "other_conditions"       => "Other Conditions",

    // Dietary Habits (text only)
    "sugar_details"   => "Sugar Details",
    "alcohol_details" => "Alcohol Details",
    "tobacco_details" => "Tobacco Details",
    "betel_nut_details" => "Betel Nut Details",

    // Patient Other Info (text only, not checkboxes)
    "philhealth_number" => "Philhealth Number",
    "sss_number"        => "SSS Number",
    "gsis_number"       => "GSIS Number",
  ];

  $missing = [];

  foreach ($required as $field => $label) {
    if (isset($_POST[$field]) && trim($_POST[$field]) === "") {
      $missing[] = $label;
    }
  }

  if (!empty($missing)) {
    echo "
    <div id='popup'>
      <div class='popup-content'>
        <p style='color:red; font-weight:bold;'>⚠ Please fill in all required fields:</p>
        <p>" . implode(", ", $missing) . "</p>
        <button onclick=\"window.history.back()\">Go Back</button>
      </div>
    </div>

    <style>
      #popup {
        position: fixed;
        top: 0; left: 0;
        width: 100%; height: 100%;
        background: transparent;
        backdrop-filter: blur(10px);
        display: flex;
        justify-content: center;
        align-items: center;
        z-index: 50;
      }
      .popup-content {
        background: #fff;
        padding: 20px 30px;
        border-radius: 12px;
        text-align: center;
        box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        font-family: Arial, sans-serif;
      }
      .popup-content p {
        font-size: 14px;
        margin-bottom: 15px;
      }
      .popup-content button {
        padding: 8px 16px;
        background: red;
        border: none;
        color: white;
        border-radius: 6px;
        cursor: pointer;
        font-size: 12px;
      }
      .popup-content button:hover {
        background: darkred;
      }
    </style>
    ";
    exit; // ⛔ Stop execution if required fields are missing
  }

  // ✅ Continue with inserts only if no missing fields
  $surname       = $_POST["surname"] ?? null;
  $firstname     = $_POST["firstname"] ?? null;
  $middlename    = $_POST["middlename"] ?? null;
  $date_of_birth = $_POST["dob"] ?? null;
  $placeofbirth  = $_POST["pob"] ?? null;
  $age           = $_POST["age"] ?? null;
  $sex           = $_POST["sex"] ?? null;
  $address       = $_POST["address"] ?? null;
  $occupation    = $_POST["occupation"] ?? null;
  $pregnant      = $_POST["pregnant"] ?? null;
  $guardian      = $_POST["guardian"] ?? null;

  $stmt = $conn->prepare("INSERT INTO patients 
    (surname, firstname, middlename, date_of_birth, place_of_birth, age, sex, address, occupation, pregnant, guardian) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

  $stmt->bind_param(
    "sssssssssss",
    $surname,
    $firstname,
    $middlename,
    $date_of_birth,
    $placeofbirth,
    $age,
    $sex,
    $address,
    $occupation,
    $pregnant,
    $guardian
  );

  if ($stmt->execute()) {
    $patient_id = $stmt->insert_id;
  } else {
    die("❌ Patient insert failed: " . $stmt->error);
  }
  $stmt->close();

  // ... keep your other insert queries unchanged ...

  echo "
<div id='popup'>
  <div class='popup-content'>
    <p>✅ Patient registration added successfully!</p>
    <button onclick=\"window.location.href='../html/addpatient.html'\">OK</button>
  </div>
</div>

<style>
  #popup {
    position: fixed;
    top: 0; left: 0;
    width: 100%; height: 100%;
    background: transparent;
    backdrop-filter: blur(10px);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 50;
  }
  .popup-content {
    background: #fff;
    padding: 20px 30px;
    border-radius: 12px;
    text-align: center;
    box-shadow: 0 5px 15px rgba(0,0,0,0.3);
    font-family: Arial, sans-serif;
  }
  .popup-content p {
    font-size: 14px;
    margin-bottom: 15px;
  }
  .popup-content button {
    padding: 8px 16px;
    background: blue;
    border: none;
    color: white;
    border-radius: 6px;
    cursor: pointer;
    font-size: 12px;
  }
  .popup-content button:hover {
    background: rgb(0, 0, 163);
  }
</style>
";
}

$conn->close();
?>
