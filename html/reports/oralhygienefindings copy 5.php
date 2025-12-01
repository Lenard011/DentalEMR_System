<?php
session_start();
date_default_timezone_set('Asia/Manila');

// REQUIRE userId parameter for each page
if (!isset($_GET['uid'])) {
    echo "<script>
        alert('Invalid session. Please log in again.');
        window.location.href = '/dentalemr_system/html/login/login.html';
    </script>";
    exit;
}

$userId = intval($_GET['uid']);

// CHECK IF THIS USER IS REALLY LOGGED IN
if (
    !isset($_SESSION['active_sessions']) ||
    !isset($_SESSION['active_sessions'][$userId])
) {
    echo "<script>
        alert('Please log in first.');
        window.location.href = '/dentalemr_system/html/login/login.html';
    </script>";
    exit;
}

// PER-USER INACTIVITY TIMEOUT
$inactiveLimit = 1800; // 30 minutes

if (isset($_SESSION['active_sessions'][$userId]['last_activity'])) {
    $lastActivity = $_SESSION['active_sessions'][$userId]['last_activity'];

    if ((time() - $lastActivity) > $inactiveLimit) {
        unset($_SESSION['active_sessions'][$userId]);
        if (empty($_SESSION['active_sessions'])) {
            session_unset();
            session_destroy();
        }

        echo "<script>
            alert('You have been logged out due to inactivity.');
            window.location.href = '/dentalemr_system/html/login/login.html';
        </script>";
        exit;
    }
}

// Update last activity timestamp
$_SESSION['active_sessions'][$userId]['last_activity'] = time();

// GET USER DATA FOR PAGE USE
$loggedUser = $_SESSION['active_sessions'][$userId];

// Store user session info safely
$host = "localhost";
$dbUser = "root";
$dbPass = "";
$dbName = "dentalemr_system";

$conn = new mysqli($host, $dbUser, $dbPass, $dbName);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch dentist name if user is a dentist
if ($loggedUser['type'] === 'Dentist') {
    $stmt = $conn->prepare("SELECT name FROM dentist WHERE id = ?");
    $stmt->bind_param("i", $loggedUser['id']);
    $stmt->execute();
    $stmt->bind_result($dentistName);
    if ($stmt->fetch()) {
        $loggedUser['name'] = $dentistName;
    }
    $stmt->close();
}

// Get current month and year for the report
$currentMonth = date('F');
$currentYear = date('Y');

// Function to calculate age group
function getAgeGroup($age, $monthsOld, $sex, $pregnant)
{
    if ($age === null) return null;

    if ($age == 0 && $monthsOld < 12) {
        return 'infant';
    } elseif ($age >= 1 && $age <= 4) {
        return 'under_five';
    } elseif ($age >= 5 && $age <= 9) {
        return 'school_age';
    } elseif ($age >= 10 && $age <= 19) {
        return 'adolescent';
    } elseif ($age >= 20 && $age <= 59) {
        return 'adult';
    } elseif ($age >= 60) {
        return 'older_person';
    }
    return null;
}

// Function to get detailed age-sex category - FIXED GENDER HANDLING
function getDetailedCategory($age, $monthsOld, $sex, $pregnant)
{
    if ($age === null || $sex === null) return null;

    // Normalize gender - handle both 'Male'/'Female' and 'male'/'female'
    $sex = strtolower(trim($sex));

    if ($age == 0 && $monthsOld < 12) {
        return $sex == 'male' ? 'infant_m' : 'infant_f';
    } elseif ($age >= 1 && $age <= 4) {
        return $sex == 'male' ? "under_five_{$age}_m" : "under_five_{$age}_f";
    } elseif ($age >= 5 && $age <= 9) {
        return $sex == 'male' ? "school_age_{$age}_m" : "school_age_{$age}_f";
    } elseif ($age >= 10 && $age <= 14) {
        return $sex == 'male' ? 'adolescent_10_14_m' : 'adolescent_10_14_f';
    } elseif ($age >= 15 && $age <= 19) {
        return $sex == 'male' ? 'adolescent_15_19_m' : 'adolescent_15_19_f';
    } elseif ($age >= 20 && $age <= 59) {
        return $sex == 'male' ? 'adult_20_59_m' : 'adult_20_59_f';
    } elseif ($age >= 60) {
        return $sex == 'male' ? 'older_60_m' : 'older_60_f';
    }

    return null;
}

// Function to process pregnant women
function isPregnantCategory($age, $sex, $pregnant)
{
    // Normalize gender and pregnant status
    $sex = strtolower(trim($sex));
    $pregnant = strtolower(trim($pregnant));

    if ($pregnant === 'yes' && $sex === 'female') {
        if ($age >= 10 && $age <= 25) return 'pregnant_10_25';
        if ($age >= 15 && $age <= 19) return 'pregnant_15_19';
        if ($age >= 20 && $age <= 49) return 'pregnant_20_49';
    }
    return null;
}

// Function to get quarter dates
function getQuarterDates($quarter, $year)
{
    switch ($quarter) {
        case 1: // Q1: Jan-Mar
            $startDate = "$year-01-01";
            $endDate = "$year-03-31";
            break;
        case 2: // Q2: Apr-Jun
            $startDate = "$year-04-01";
            $endDate = "$year-06-30";
            break;
        case 3: // Q3: Jul-Sep
            $startDate = "$year-07-01";
            $endDate = "$year-09-30";
            break;
        case 4: // Q4: Oct-Dec
            $startDate = "$year-10-01";
            $endDate = "$year-12-31";
            break;
        default:
            $startDate = "$year-01-01";
            $endDate = "$year-12-31";
    }
    return [
        'startDate' => $startDate,
        'endDate' => $endDate,
        'startDateTime' => $startDate . " 00:00:00",
        'endDateTime' => $endDate . " 23:59:59"
    ];
}

// Function to get month name from quarter
function getQuarterMonthName($quarter)
{
    switch ($quarter) {
        case 1:
            return 'January-March';
        case 2:
            return 'April-June';
        case 3:
            return 'July-September';
        case 4:
            return 'October-December';
        default:
            return 'Unknown Quarter';
    }
}
// Function to get semi-annual and annual dates
function getSemiAnnualDates($semiAnnual, $year)
{
    switch ($semiAnnual) {
        case 1: // Semi-Annual 1: Jan-Jun
            $startDate = "$year-01-01";
            $endDate = "$year-06-30";
            break;
        case 2: // Semi-Annual 2: Jul-Dec
            $startDate = "$year-07-01";
            $endDate = "$year-12-31";
            break;
        case 3: // Annual: Jan-Dec
            $startDate = "$year-01-01";
            $endDate = "$year-12-31";
            break;
        default:
            $startDate = "$year-01-01";
            $endDate = "$year-12-31";
    }
    return [
        'startDate' => $startDate,
        'endDate' => $endDate,
        'startDateTime' => $startDate . " 00:00:00",
        'endDateTime' => $endDate . " 23:59:59"
    ];
}

// Function to get period display name
function getPeriodDisplayName($periodType, $selectedValue)
{
    switch ($periodType) {
        case 'monthly':
            $monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
            return $monthNames[$selectedValue - 1] . "/FY" . date('Y');

        case 'quarterly':
            $quarterNames = ['January-March', 'April-June', 'July-September', 'October-December'];
            $quarterYears = ['1st', '2nd', '3rd', '4th'];
            return $quarterNames[$selectedValue - 1] . "/" . $quarterYears[$selectedValue - 1] . " qtr./" . date('Y');

        case 'semi_annual':
            $semiAnnualNames = ['January-June', 'July-December'];
            return $semiAnnualNames[$selectedValue - 1] . "/" . date('Y');

        case 'annual':
            return "January-December/" . date('Y');

        default:
            return date('F') . "/FY" . date('Y');
    }
}

// Initialize report data structure
$reportData = [
    // Person attended/examined
    'person_attended' => [],
    'person_examined' => [],

    // Medical History
    'allergies' => [],
    'hypertension' => [],
    'diabetes' => [],
    'blood_disorders' => [],
    'heart_disease' => [],
    'thyroid_disorders' => [],
    'hepatitis' => [],
    'malignancy' => [],
    'prev_hospitalization' => [],
    'blood_transfusion' => [],
    'tattoo' => [],

    // Dietary/Social History
    'sugar_consumer' => [],
    'alcohol_drinker' => [],
    'tobacco_user' => [],
    'betel_nut_chewer' => [],

    // Oral Health Status
    'dental_caries' => [],
    'gingivitis' => [],
    'periodontal_disease' => [],
    'oral_debris' => [],
    'calculus' => [],
    'dento_facial_anomalies' => [],

    // DMF Scores
    'decayed_primary' => [],
    'filled_primary' => [],
    'decayed_permanent' => [],
    'missing_permanent' => [],
    'filled_permanent' => [],

    // Services Rendered
    'oral_prophylaxis' => [],
    'permanent_filling' => [],
    'temporary_filling' => [],
    'extraction' => [],
    'gum_treatment' => [],
    'sealant' => [],
    'fluoride_therapy' => [],
    'post_operative' => [],
    'abscess_drained' => [],
    'other_services' => [],
    'referred' => [],
    'counseling' => [],
    'toothbrush_drill' => [],

    // Orally Fit Children
    'ofc_examination' => [],
    'ofc_rehabilitation' => []
];

// Determine report period type
$periodType = isset($_GET['period']) ? $_GET['period'] : 'monthly';
$selectedMonth = isset($_GET['month']) ? intval($_GET['month']) : date('n');
$selectedYear = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
$selectedQuarter = isset($_GET['quarter']) ? intval($_GET['quarter']) : ceil(date('n') / 3);
$selectedSemiAnnual = isset($_GET['semi_annual']) ? intval($_GET['semi_annual']) : (date('n') <= 6 ? 1 : 2);
// Determine report type and FHSIS period
$reportType = isset($_GET['report_type']) ? $_GET['report_type'] : 'main';
$fhisPeriod = isset($_GET['fhis_period']) ? $_GET['fhis_period'] : 'quarterly';
$fhisQuarter = isset($_GET['fhis_quarter']) ? intval($_GET['fhis_quarter']) : 1;
$fhisSemiAnnual = isset($_GET['fhis_semi_annual']) ? intval($_GET['fhis_semi_annual']) : 1;

// Set dates based on period type
switch ($periodType) {
    case 'quarterly':
        $quarterDates = getQuarterDates($selectedQuarter, $selectedYear);
        $startDate = $quarterDates['startDate'];
        $endDate = $quarterDates['endDate'];
        $startDateTime = $quarterDates['startDateTime'];
        $endDateTime = $quarterDates['endDateTime'];
        $periodDisplay = getPeriodDisplayName('quarterly', $selectedQuarter);
        break;

    case 'semi_annual':
        $semiAnnualDates = getSemiAnnualDates($selectedSemiAnnual, $selectedYear);
        $startDate = $semiAnnualDates['startDate'];
        $endDate = $semiAnnualDates['endDate'];
        $startDateTime = $semiAnnualDates['startDateTime'];
        $endDateTime = $semiAnnualDates['endDateTime'];
        $periodDisplay = getPeriodDisplayName('semi_annual', $selectedSemiAnnual);
        break;

    case 'annual':
        $annualDates = getSemiAnnualDates(3, $selectedYear); // Use 3 for annual
        $startDate = $annualDates['startDate'];
        $endDate = $annualDates['endDate'];
        $startDateTime = $annualDates['startDateTime'];
        $endDateTime = $annualDates['endDateTime'];
        $periodDisplay = getPeriodDisplayName('annual', 3);
        break;

    default: // monthly
        $startDate = "$selectedYear-$selectedMonth-01";
        $endDate = date('Y-m-t', strtotime($startDate));
        $startDateTime = $startDate . " 00:00:00";
        $endDateTime = $endDate . " 23:59:59";
        $periodDisplay = getPeriodDisplayName('monthly', $selectedMonth);
        break;
}


// FIXED: Simplified approach - get patients who had activity in the selected period
$allPatientsQuery = "
    SELECT DISTINCT p.patient_id, p.surname, p.firstname, p.date_of_birth, p.age, p.months_old, 
        p.sex, p.pregnant, p.if_treatment, p.created_at
    FROM patients p
    WHERE p.created_at BETWEEN ? AND ?
       OR EXISTS (SELECT 1 FROM visits v WHERE v.patient_id = p.patient_id AND v.visit_date BETWEEN ? AND ?)
       OR EXISTS (SELECT 1 FROM oral_health_condition ohc WHERE ohc.patient_id = p.patient_id AND (ohc.created_at BETWEEN ? AND ? OR ohc.updated_at BETWEEN ? AND ?))
       OR EXISTS (SELECT 1 FROM patient_treatment_record ptr WHERE ptr.patient_id = p.patient_id AND ptr.created_at BETWEEN ? AND ?)
    ORDER BY p.patient_id
";

$allPatientsStmt = $conn->prepare($allPatientsQuery);
if (!$allPatientsStmt) {
    die("Error preparing statement: " . $conn->error);
}

// Bind parameters for the simplified query
$allPatientsStmt->bind_param(
    "ssssssssss",
    $startDateTime,
    $endDateTime,     // p.created_at
    $startDate,
    $endDate,         // visits v.visit_date  
    $startDateTime,
    $endDateTime,     // ohc.created_at
    $startDateTime,
    $endDateTime,     // ohc.updated_at
    $startDateTime,
    $endDateTime      // ptr.created_at
);

$allPatientsStmt->execute();
$allPatientsResult = $allPatientsStmt->get_result();

// Check for query execution errors
if (!$allPatientsResult) {
    die("Error executing query: " . $allPatientsStmt->error);
}

$allProcessedPatients = [];

while ($patient = $allPatientsResult->fetch_assoc()) {
    $patientId = $patient['patient_id'];

    // Skip if we've already processed this patient
    if (in_array($patientId, $allProcessedPatients)) {
        continue;
    }
    $allProcessedPatients[] = $patientId;

    $age = $patient['age'];
    $monthsOld = $patient['months_old'] ?? 0; // Handle NULL months_old
    $sex = $patient['sex'];
    $pregnant = $patient['pregnant'];

    // Get age group and detailed category
    $ageGroup = getAgeGroup($age, $monthsOld, $sex, $pregnant);
    $detailedCategory = getDetailedCategory($age, $monthsOld, $sex, $pregnant);

    if (!$ageGroup || !$detailedCategory) continue;

    // Person attended - count patients with activity in the selected period
    $reportData['person_attended'][$detailedCategory] = ($reportData['person_attended'][$detailedCategory] ?? 0) + 1;

    // Person examined - count only patients with if_treatment = 1 AND activity in the selected period
    if ($patient['if_treatment'] == 1) {
        $reportData['person_examined'][$detailedCategory] = ($reportData['person_examined'][$detailedCategory] ?? 0) + 1;
    }
}

$allPatientsStmt->close();

// Fetch oral health condition data separately to ensure we get all records
$oralHealthQuery = "
    SELECT ohc.*, p.age, p.months_old, p.sex, p.pregnant
    FROM oral_health_condition ohc
    INNER JOIN patients p ON ohc.patient_id = p.patient_id
    WHERE (ohc.created_at BETWEEN ? AND ? OR ohc.updated_at BETWEEN ? AND ?)
    ORDER BY ohc.patient_id, ohc.created_at DESC
";

$oralHealthStmt = $conn->prepare($oralHealthQuery);
if (!$oralHealthStmt) {
    die("Error preparing oral health statement: " . $conn->error);
}

$oralHealthStmt->bind_param("ssss", $startDateTime, $endDateTime, $startDateTime, $endDateTime);
$oralHealthStmt->execute();
$oralHealthResult = $oralHealthStmt->get_result();

if (!$oralHealthResult) {
    die("Error executing oral health query: " . $oralHealthStmt->error);
}

$processedOralHealth = [];
$latestOralHealthRecords = [];

// First, get the latest record for each patient
while ($oralHealth = $oralHealthResult->fetch_assoc()) {
    $patientId = $oralHealth['patient_id'];

    // Only keep the latest record for each patient
    if (!isset($latestOralHealthRecords[$patientId])) {
        $latestOralHealthRecords[$patientId] = $oralHealth;
    }
}

// Now process the latest records
foreach ($latestOralHealthRecords as $oralHealth) {
    $patientId = $oralHealth['patient_id'];
    $age = $oralHealth['age'];
    $monthsOld = $oralHealth['months_old'] ?? 0;
    $sex = $oralHealth['sex'];
    $pregnant = $oralHealth['pregnant'];

    // Get age group and detailed category
    $ageGroup = getAgeGroup($age, $monthsOld, $sex, $pregnant);
    $detailedCategory = getDetailedCategory($age, $monthsOld, $sex, $pregnant);

    if (!$ageGroup || !$detailedCategory) continue;

    // Oral Health Status - FIXED: Check for '✓' and also count if values are > 0
    if ($oralHealth['dental_caries'] === '✓') {
        $reportData['dental_caries'][$detailedCategory] = ($reportData['dental_caries'][$detailedCategory] ?? 0) + 1;
    }

    if ($oralHealth['gingivitis'] === '✓') {
        $reportData['gingivitis'][$detailedCategory] = ($reportData['gingivitis'][$detailedCategory] ?? 0) + 1;
    }

    if ($oralHealth['periodontal_disease'] === '✓') {
        $reportData['periodontal_disease'][$detailedCategory] = ($reportData['periodontal_disease'][$detailedCategory] ?? 0) + 1;
    }

    if ($oralHealth['debris'] === '✓') {
        $reportData['oral_debris'][$detailedCategory] = ($reportData['oral_debris'][$detailedCategory] ?? 0) + 1;
    }

    if ($oralHealth['calculus'] === '✓') {
        $reportData['calculus'][$detailedCategory] = ($reportData['calculus'][$detailedCategory] ?? 0) + 1;
    }

    // FIXED: Count each dento-facial anomaly condition separately
    $anomalyCount = 0;
    if ($oralHealth['abnormal_growth'] === '✓') $anomalyCount++;
    if ($oralHealth['cleft_palate'] === '✓') $anomalyCount++;
    if ($oralHealth['others'] === '✓') $anomalyCount++;

    if ($anomalyCount > 0) {
        $reportData['dento_facial_anomalies'][$detailedCategory] = ($reportData['dento_facial_anomalies'][$detailedCategory] ?? 0) + $anomalyCount;
    }

    // DMF Scores - FIXED: Accumulate actual tooth counts (not patient counts)
    $reportData['decayed_primary'][$detailedCategory] = ($reportData['decayed_primary'][$detailedCategory] ?? 0) + ($oralHealth['temp_decayed_teeth_d'] ?? 0);
    $reportData['filled_primary'][$detailedCategory] = ($reportData['filled_primary'][$detailedCategory] ?? 0) + ($oralHealth['temp_filled_teeth_f'] ?? 0);
    $reportData['decayed_permanent'][$detailedCategory] = ($reportData['decayed_permanent'][$detailedCategory] ?? 0) + ($oralHealth['perm_decayed_teeth_d'] ?? 0);
    $reportData['missing_permanent'][$detailedCategory] = ($reportData['missing_permanent'][$detailedCategory] ?? 0) + ($oralHealth['perm_missing_teeth_m'] ?? 0);
    $reportData['filled_permanent'][$detailedCategory] = ($reportData['filled_permanent'][$detailedCategory] ?? 0) + ($oralHealth['perm_filled_teeth_f'] ?? 0);
}

$oralHealthStmt->close();

// Fetch patients who had activity during the selected period for other report data
$patientQuery = "
    SELECT DISTINCT p.patient_id, p.surname, p.firstname, p.date_of_birth, p.age, p.months_old, 
        p.sex, p.pregnant, p.created_at,
        mh.allergies_flag, mh.hypertension_cva, mh.diabetes_mellitus, mh.blood_disorders,
        mh.heart_disease, mh.thyroid_disorders, mh.hepatitis_flag, mh.malignancy_flag,
        mh.prev_hospitalization_flag, mh.blood_transfusion_flag, mh.tattoo,
        dh.sugar_flag, dh.alcohol_flag, dh.tobacco_flag, dh.betel_nut_flag,
        ptr.oral_prophylaxis, ptr.fluoride, ptr.sealant, ptr.permanent_filling, 
        ptr.temporary_filling, ptr.extraction, ptr.consultation,
        v.visit_date
    FROM patients p
    LEFT JOIN medical_history mh ON p.patient_id = mh.patient_id
    LEFT JOIN dietary_habits dh ON p.patient_id = dh.patient_id
    LEFT JOIN patient_treatment_record ptr ON p.patient_id = ptr.patient_id
    LEFT JOIN visits v ON p.patient_id = v.patient_id
    WHERE (p.created_at BETWEEN ? AND ? OR v.visit_date BETWEEN ? AND ?)
    ORDER BY p.patient_id
";

$stmt = $conn->prepare($patientQuery);
if (!$stmt) {
    die("Error preparing patient query statement: " . $conn->error);
}

$stmt->bind_param("ssss", $startDateTime, $endDateTime, $startDate, $endDate);
$stmt->execute();
$result = $stmt->get_result();

if (!$result) {
    die("Error executing patient query: " . $stmt->error);
}

$processedPatients = [];

while ($patient = $result->fetch_assoc()) {
    $patientId = $patient['patient_id'];

    // Skip if we've already processed this patient
    if (in_array($patientId, $processedPatients)) {
        continue;
    }
    $processedPatients[] = $patientId;

    $age = $patient['age'];
    $monthsOld = $patient['months_old'] ?? 0;
    $sex = $patient['sex'];
    $pregnant = $patient['pregnant'];

    // Get age group and detailed category
    $ageGroup = getAgeGroup($age, $monthsOld, $sex, $pregnant);
    $detailedCategory = getDetailedCategory($age, $monthsOld, $sex, $pregnant);
    $pregnantCategory = isPregnantCategory($age, $sex, $pregnant);

    if (!$ageGroup || !$detailedCategory) continue;

    // NOTE: We DON'T count person_attended, person_examined, oral health status, or DMF scores here anymore
    // since we already counted them from separate queries

    // Medical History
    if ($patient['allergies_flag']) $reportData['allergies'][$detailedCategory] = ($reportData['allergies'][$detailedCategory] ?? 0) + 1;
    if ($patient['hypertension_cva']) $reportData['hypertension'][$detailedCategory] = ($reportData['hypertension'][$detailedCategory] ?? 0) + 1;
    if ($patient['diabetes_mellitus']) $reportData['diabetes'][$detailedCategory] = ($reportData['diabetes'][$detailedCategory] ?? 0) + 1;
    if ($patient['blood_disorders']) $reportData['blood_disorders'][$detailedCategory] = ($reportData['blood_disorders'][$detailedCategory] ?? 0) + 1;
    if ($patient['heart_disease']) $reportData['heart_disease'][$detailedCategory] = ($reportData['heart_disease'][$detailedCategory] ?? 0) + 1;
    if ($patient['thyroid_disorders']) $reportData['thyroid_disorders'][$detailedCategory] = ($reportData['thyroid_disorders'][$detailedCategory] ?? 0) + 1;
    if ($patient['hepatitis_flag']) $reportData['hepatitis'][$detailedCategory] = ($reportData['hepatitis'][$detailedCategory] ?? 0) + 1;
    if ($patient['malignancy_flag']) $reportData['malignancy'][$detailedCategory] = ($reportData['malignancy'][$detailedCategory] ?? 0) + 1;
    if ($patient['prev_hospitalization_flag']) $reportData['prev_hospitalization'][$detailedCategory] = ($reportData['prev_hospitalization'][$detailedCategory] ?? 0) + 1;
    if ($patient['blood_transfusion_flag']) $reportData['blood_transfusion'][$detailedCategory] = ($reportData['blood_transfusion'][$detailedCategory] ?? 0) + 1;
    if ($patient['tattoo']) $reportData['tattoo'][$detailedCategory] = ($reportData['tattoo'][$detailedCategory] ?? 0) + 1;

    // Dietary/Social History
    if ($patient['sugar_flag']) $reportData['sugar_consumer'][$detailedCategory] = ($reportData['sugar_consumer'][$detailedCategory] ?? 0) + 1;
    if ($patient['alcohol_flag']) $reportData['alcohol_drinker'][$detailedCategory] = ($reportData['alcohol_drinker'][$detailedCategory] ?? 0) + 1;
    if ($patient['tobacco_flag']) $reportData['tobacco_user'][$detailedCategory] = ($reportData['tobacco_user'][$detailedCategory] ?? 0) + 1;
    if ($patient['betel_nut_flag']) $reportData['betel_nut_chewer'][$detailedCategory] = ($reportData['betel_nut_chewer'][$detailedCategory] ?? 0) + 1;

    // Services Rendered
    if ($patient['oral_prophylaxis'] === 'Yes') $reportData['oral_prophylaxis'][$detailedCategory] = ($reportData['oral_prophylaxis'][$detailedCategory] ?? 0) + 1;
    if ($patient['permanent_filling'] === 'Yes') $reportData['permanent_filling'][$detailedCategory] = ($reportData['permanent_filling'][$detailedCategory] ?? 0) + 1;
    if ($patient['temporary_filling'] === 'Yes') $reportData['temporary_filling'][$detailedCategory] = ($reportData['temporary_filling'][$detailedCategory] ?? 0) + 1;
    if ($patient['extraction'] === 'Yes') $reportData['extraction'][$detailedCategory] = ($reportData['extraction'][$detailedCategory] ?? 0) + 1;
    if ($patient['fluoride'] === 'Yes') $reportData['fluoride_therapy'][$detailedCategory] = ($reportData['fluoride_therapy'][$detailedCategory] ?? 0) + 1;
    if ($patient['sealant'] === 'Yes') $reportData['sealant'][$detailedCategory] = ($reportData['sealant'][$detailedCategory] ?? 0) + 1;
    if ($patient['consultation'] === 'Yes') $reportData['counseling'][$detailedCategory] = ($reportData['counseling'][$detailedCategory] ?? 0) + 1;

    // Orally Fit Children (simplified logic - needs refinement based on business rules)
    if ($age <= 12) {
        // Check if patient has oral health condition record and is orally fit
        if (in_array($patientId, $processedOralHealth)) {
            // We need to check the specific oral health condition for this patient
            // This would require additional logic to check if the child is orally fit
            // For now, we'll use a simplified approach
            $reportData['ofc_examination'][$detailedCategory] = ($reportData['ofc_examination'][$detailedCategory] ?? 0) + 1;
        }
    }
}

$stmt->close();

// Calculate totals and grand totals
foreach ($reportData as $dataType => $categories) {
    // Calculate under_five_total_m and under_five_total_f
    $underFiveTotalM = 0;
    $underFiveTotalF = 0;
    for ($age = 1; $age <= 4; $age++) {
        $underFiveTotalM += $categories["under_five_{$age}_m"] ?? 0;
        $underFiveTotalF += $categories["under_five_{$age}_f"] ?? 0;
    }
    $reportData[$dataType]['under_five_total_m'] = $underFiveTotalM;
    $reportData[$dataType]['under_five_total_f'] = $underFiveTotalF;

    // Calculate school_age_total_m and school_age_total_f
    $schoolAgeTotalM = 0;
    $schoolAgeTotalF = 0;
    for ($age = 5; $age <= 9; $age++) {
        $schoolAgeTotalM += $categories["school_age_{$age}_m"] ?? 0;
        $schoolAgeTotalF += $categories["school_age_{$age}_f"] ?? 0;
    }
    $reportData[$dataType]['school_age_total_m'] = $schoolAgeTotalM;
    $reportData[$dataType]['school_age_total_f'] = $schoolAgeTotalF;

    // FIXED: Calculate total_m and total_f by summing ALL age categories
    $totalM = 0;
    $totalF = 0;

    // Sum ALL male categories including infant, adolescent, adult, older persons
    $totalM += $categories['infant_m'] ?? 0;
    $totalM += $underFiveTotalM;
    $totalM += $schoolAgeTotalM;
    $totalM += $categories['adolescent_10_14_m'] ?? 0;
    $totalM += $categories['adolescent_15_19_m'] ?? 0;
    $totalM += $categories['adult_20_59_m'] ?? 0;
    $totalM += $categories['older_60_m'] ?? 0;

    // Sum ALL female categories including infant, adolescent, adult, older persons
    $totalF += $categories['infant_f'] ?? 0;
    $totalF += $underFiveTotalF;
    $totalF += $schoolAgeTotalF;
    $totalF += $categories['adolescent_10_14_f'] ?? 0;
    $totalF += $categories['adolescent_15_19_f'] ?? 0;
    $totalF += $categories['adult_20_59_f'] ?? 0;
    $totalF += $categories['older_60_f'] ?? 0;

    $reportData[$dataType]['total_m'] = $totalM;
    $reportData[$dataType]['total_f'] = $totalF;

    // Calculate grand total (total_m + total_f)
    $reportData[$dataType]['grand_total'] = $totalM + $totalF;
}

$conn->close();

// Function to get value for a specific category and data type
function getReportValue($dataType, $category, $reportData)
{
    return $reportData[$dataType][$category] ?? 0;
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Oral Hygiene Findings - MHO Dental Clinic</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <style>
        .age-table {
            font-size: 0.7rem;
        }

        .sticky-header {
            position: sticky;
            left: 0;
            background: white;
            z-index: 10;
        }

        .table-container {
            overflow-x: auto;
            max-height: 80vh;
        }

        .section-header {
            background-color: #e5e7eb;
            font-weight: bold;
        }

        .subtotal-row {
            background-color: #f3f4f6;
            font-weight: 600;
        }

        .grand-total-row {
            background-color: #d1d5db;
            font-weight: bold;
        }

        .data-cell {
            width: 60px;
            padding: 2px;
            text-align: center;
            border: 1px solid #d1d5db;
        }

        .nowrap {
            white-space: nowrap;
        }

        /* Hide FHSIS section by default */
        #fhis-section {
            display: none;
        }
    </style>
</head>

<body>
    <div class="antialiased bg-gray-50 dark:bg-gray-900">
        <!-- Navigation Header (same as your original) -->
        <nav class="bg-white border-b border-gray-200 px-4 py-2.5 dark:bg-gray-800 dark:border-gray-700 fixed left-0 right-0 top-0 z-50">
            <div class="flex flex-wrap justify-between items-center">
                <div class="flex justify-start items-center">
                    <button data-drawer-target="drawer-navigation" data-drawer-toggle="drawer-navigation"
                        aria-controls="drawer-navigation"
                        class="p-2 mr-2 text-gray-600 rounded-lg cursor-pointer md:hidden hover:text-gray-900 hover:bg-gray-100 focus:bg-gray-100 dark:focus:bg-gray-700 focus:ring-2 focus:ring-gray-100 dark:focus:ring-gray-700 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white">
                        <svg aria-hidden="true" class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20"
                            xmlns="http://www.w3.org/2000/svg">
                            <path fill-rule="evenodd"
                                d="M3 5a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM3 10a1 1 0 011-1h6a1 1 0 110 2H4a1 1 0 01-1-1zM3 15a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z"
                                clip-rule="evenodd"></path>
                        </svg>
                        <span class="sr-only">Toggle sidebar</span>
                    </button>
                    <a href="#" class="flex items-center justify-between mr-4">
                        <img src="https://th.bing.com/th/id/OIP.zjh8eiLAHY9ybXUCuYiqQwAAAA?r=0&rs=1&pid=ImgDetMain&cb=idpwebp1&o=7&rm=3"
                            class="mr-3 h-8" alt="MHO Dental Clinic Logo" />
                        <span class="self-center text-2xl font-semibold whitespace-nowrap dark:text-white">MHO Dental Clinic</span>
                    </a>
                </div>

                <!-- User Profile Dropdown -->
                <div class="flex items-center lg:order-2">
                    <button type="button"
                        class="flex mx-3 cursor-pointer text-sm bg-gray-800 rounded-full md:mr-0 focus:ring-4 focus:ring-gray-300 dark:focus:ring-gray-600"
                        id="user-menu-button" aria-expanded="false" data-dropdown-toggle="dropdown">
                        <span class="sr-only">Open user menu</span>
                        <img class="w-8 h-8 rounded-full"
                            src="https://spng.pngfind.com/pngs/s/378-3780189_member-icon-png-transparent-png.png"
                            alt="user photo" />
                    </button>
                    <!-- Dropdown menu -->
                    <div class="hidden z-50 my-4 w-56 text-base list-none bg-white divide-y divide-gray-100 shadow dark:bg-gray-700 dark:divide-gray-600 rounded-xl"
                        id="dropdown">
                        <div class="py-3 px-4">
                            <span class="block text-sm font-semibold text-gray-900 dark:text-white">
                                <?php echo htmlspecialchars($loggedUser['name'] ?? 'User'); ?>
                            </span>
                            <span class="block text-sm text-gray-900 truncate dark:text-white">
                                <?php echo htmlspecialchars($loggedUser['email'] ?? $loggedUser['name'] ?? 'User'); ?>
                            </span>
                        </div>
                        <ul class="py-1 text-gray-700 dark:text-gray-300" aria-labelledby="dropdown">
                            <li>
                                <a href="#"
                                    class="block py-2 px-4 text-sm hover:bg-gray-100 dark:hover:bg-gray-600 dark:text-gray-400 dark:hover:text-white">My profile</a>
                            </li>
                            <li>
                                <a href="/dentalemr_system/html/manageusers/manageuser.php?uid=<?php echo $userId; ?>"
                                    class="block py-2 px-4 text-sm hover:bg-gray-100 dark:hover:bg-gray-600 dark:text-gray-400 dark:hover:text-white">Manage users</a>
                            </li>
                        </ul>
                        <ul class="py-1 text-gray-700 dark:text-gray-300" aria-labelledby="dropdown">
                            <li>
                                <a href="/dentalemr_system/html/manageusers/historylogs.php?uid=<?php echo $userId; ?>"
                                    class="block py-2 px-4 text-sm hover:bg-gray-100 dark:hover:bg-gray-600 dark:text-gray-400 dark:hover:text-white">History logs</a>
                            </li>
                            <li>
                                <a href="/dentalemr_system/html/manageusers/activitylogs.php?uid=<?php echo $userId; ?>"
                                    class="block py-2 px-4 text-sm hover:bg-gray-100 dark:hover:bg-gray-600 dark:text-gray-400 dark:hover:text-white">Activity logs</a>
                            </li>
                        </ul>
                        <ul class="py-1 text-gray-700 dark:text-gray-300" aria-labelledby="dropdown">
                            <li>
                                <a href="/dentalemr_system/php/login/logout.php?uid=<?php echo $loggedUser['id']; ?>"
                                    class="block py-2 px-4 text-sm hover:bg-gray-100 dark:hover:bg-gray-600 dark:hover:text-white">Sign out</a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Sidebar (same as your original) -->
        <aside class="fixed top-0 left-0 z-40 w-64 h-screen pt-14 transition-transform -translate-x-full bg-white border-r border-gray-200 md:translate-x-0 dark:bg-gray-800 dark:border-gray-700"
            aria-label="Sidenav" id="drawer-navigation">
            <div class="overflow-y-auto py-5 px-3 h-full bg-white dark:bg-gray-800">
                <form action="#" method="GET" class="md:hidden mb-2">
                    <label for="sidebar-search" class="sr-only">Search</label>
                    <div class="relative">
                        <div class="flex absolute inset-y-0 left-0 items-center pl-3 pointer-events-none">
                            <svg class="w-5 h-5 text-gray-500 dark:text-gray-400" fill="currentColor" viewBox="0 0 20 20"
                                xmlns="http://www.w3.org/2000/svg">
                                <path fill-rule="evenodd" clip-rule="evenodd"
                                    d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z">
                                </path>
                            </svg>
                        </div>
                        <input type="text" name="search" id="sidebar-search"
                            class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block w-full pl-10 p-2 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500"
                            placeholder="Search" />
                    </div>
                </form>
                <ul class="space-y-2">
                    <li>
                        <a href="../index.php?uid=<?php echo $userId; ?>"
                            class="flex items-center p-2 text-base font-medium text-gray-900 rounded-lg dark:text-white hover:bg-gray-100 dark:hover:bg-gray-700 group">
                            <svg aria-hidden="true"
                                class="w-6 h-6 text-gray-500 transition duration-75 dark:text-gray-400 group-hover:text-gray-900 dark:group-hover:text-white"
                                fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                                <path d="M2 10a8 8 0 018-8v8h8a8 8 0 11-16 0z"></path>
                                <path d="M12 2.252A8.014 8.014 0 0117.748 8H12V2.252z"></path>
                            </svg>
                            <span class="ml-3">Dashboard</span>
                        </a>
                    </li>
                </ul>
                <ul class="pt-5 mt-5 space-y-2 border-t border-gray-200 dark:border-gray-700">
                    <li>
                        <a href="../addpatient.php?uid=<?php echo $userId; ?>"
                            class="flex items-center p-2 text-base font-medium text-gray-900 rounded-lg transition duration-75 hover:bg-gray-100 dark:hover:bg-gray-700 dark:text-white group">
                            <svg aria-hidden="true"
                                class="flex-shrink-0 w-6 h-6  text-gray-500 transition duration-75 dark:text-gray-400 group-hover:text-gray-900 dark:group-hover:text-white"
                                fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                                <path
                                    d="M12.5 16a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7m.5-5v1h1a.5.5 0 0 1 0 1h-1v1a.5.5 0 0 1-1 0v-1h-1a.5.5 0 0 1 0-1h1v-1a.5.5 0 0 1 1 0m-2-6a3 3 0 1 1-6 0 3 3 0 0 1 6 0" />
                                <path
                                    d="M2 13c0 1 1 1 1 1h5.256A4.5 4.5 0 0 1 8 12.5a4.5 4.5 0 0 1 1.544-3.393Q8.844 9.002 8 9c-5 0-6 3-6 4" />
                            </svg>
                            <span class="ml-3">Add Patient</span>
                        </a>
                    </li>
                    <li>
                        <button type="button"
                            class="flex items-center cursor-pointer p-2 w-full text-base font-medium text-gray-900 rounded-lg transition duration-75 group hover:bg-gray-100 dark:text-white dark:hover:bg-gray-700"
                            aria-controls="dropdown-pages" data-collapse-toggle="dropdown-pages">
                            <svg aria-hidden="true"
                                class="flex-shrink-0 w-6 h-6 text-gray-500 transition duration-75 group-hover:text-gray-900 dark:text-gray-400 dark:group-hover:text-white"
                                fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                                <path fill-rule="evenodd"
                                    d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z"
                                    clip-rule="evenodd"></path>
                            </svg>
                            <span class="flex-1 ml-3 text-left whitespace-nowrap">Patient Treatment</span>
                            <svg aria-hidden="true" class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20"
                                xmlns="http://www.w3.org/2000/svg">
                                <path fill-rule="evenodd"
                                    d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                    clip-rule="evenodd"></path>
                            </svg>
                        </button>
                        <ul id="dropdown-pages" class="hidden py-2 space-y-2">
                            <li>
                                <a href="../treatmentrecords/treatmentrecords.php?uid=<?php echo $userId; ?>"
                                    class="flex items-center p-2 pl-11 w-full text-base font-medium text-gray-900 rounded-lg transition duration-75 group hover:bg-gray-100 dark:text-white dark:hover:bg-gray-700">Treatment Records</a>
                            </li>
                            <li>
                                <a href="../addpatienttreatment/patienttreatment.php?uid=<?php echo $userId; ?>"
                                    class="flex items-center p-2 pl-11 w-full text-base font-medium text-gray-900 rounded-lg transition duration-75 group hover:bg-gray-100 dark:text-white dark:hover:bg-gray-700">Add Patient Treatment</a>
                            </li>
                        </ul>
                    </li>
                </ul>
                <ul class="pt-5 mt-5 space-y-2 border-t border-gray-200 dark:border-gray-700">
                    <li>
                        <a href="./targetclientlist.php?uid=<?php echo $userId; ?>"
                            class="flex items-center p-2 text-base font-medium text-gray-900 rounded-lg dark:text-white hover:bg-gray-100 dark:hover:bg-gray-700 group">
                            <svg aria-hidden="true"
                                class="flex-shrink-0 w-6 h-6 text-gray-500 transition duration-75 dark:text-gray-400 group-hover:text-gray-900 dark:group-hover:text-white"
                                fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                                <path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"></path>
                                <path fill-rule="evenodd"
                                    d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000 2h3a1 1 0 100-2h-3zm-3 4a1 1 0 100 2h.01a1 1 0 100-2H7zm3 0a1 1 0 100 2h3a1 1 0 100-2h-3z"
                                    clip-rule="evenodd"></path>
                            </svg>
                            <span class="ml-3">Target Client List</span>
                        </a>
                    </li>
                    <li>
                        <a href="./mho_ohp.php?uid=<?php echo $userId; ?>"
                            class="flex items-center p-2 text-base font-medium text-gray-900 rounded-lg dark:text-white hover:bg-gray-100 dark:hover:bg-gray-700 group">
                            <svg class="flex-shrink-0 w-6 h-6 text-gray-500 transition duration-75 dark:text-gray-400 group-hover:text-gray-900 dark:group-hover:text-white"
                                aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none"
                                viewBox="0 0 24 24">
                                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M11 9h6m-6 3h6m-6 3h6M6.996 9h.01m-.01 3h.01m-.01 3h.01M4 5h16a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1Z" />
                            </svg>
                            <span class="ml-3">MHO - OHP</span>
                        </a>
                    </li>
                    <li>
                        <a href="#" class="flex items-center p-2 text-base font-medium text-blue-600 rounded-lg dark:text-blue bg-blue-100  dark:hover:bg-blue-700 group">
                            <svg aria-hidden="true"
                                class="w-6 h-6 text-blue-600 transition duration-75 dark:text-blue-400  dark:group-hover:text-blue"
                                aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none"
                                viewBox="0 0 24 24">
                                <path stroke="currentColor" stroke-linecap="round" stroke-width="2"
                                    d="M9 8h10M9 12h10M9 16h10M4.99 8H5m-.02 4h.01m0 4H5" />
                            </svg>
                            <span class="ml-3">Oral Hygiene Findings</span>
                        </a>
                    </li>
                </ul>
                <ul class="pt-5 mt-5 space-y-2 border-t border-gray-200 dark:border-gray-700">
                    <li>
                        <a href="../archived.php?uid=<?php echo $userId; ?>"
                            class="flex items-center p-2 text-base font-medium text-gray-900 rounded-lg dark:text-white hover:bg-gray-100 dark:hover:bg-gray-700 group">
                            <svg class="flex-shrink-0 w-6 h-6 text-gray-500 transition duration-75 dark:text-gray-400 group-hover:text-gray-900 dark:group-hover:text-white"
                                aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                fill="currentColor" viewBox="0 0 24 24">
                                <path fill-rule="evenodd"
                                    d="M20 10H4v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8ZM9 13v-1h6v1a1 1 0 0 1-1 1h-4a1 1 0 0 1-1-1Z"
                                    clip-rule="evenodd" />
                                <path d="M2 6a2 2 0 0 1 2-2h16a2 2 0 1 1 0 4H4a2 2 0 0 1-2-2Z" />
                            </svg>
                            <span class="ml-3">Archived</span>
                        </a>
                    </li>
                    <li>
                        <a href="#"
                            class="flex items-center p-2 text-base font-medium text-gray-900 rounded-lg dark:text-white hover:bg-gray-100 dark:hover:bg-gray-700 group">
                            <svg class="flex-shrink-0 w-6 h-6 text-gray-500 transition duration-75 dark:text-gray-400 group-hover:text-gray-900 dark:group-hover:text-white"
                                aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none"
                                viewBox="0 0 24 24">
                                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"
                                    stroke-width="2" d="M4 4v15a1 1 0 0 0 1 1h15M8 16l2.5-5.5 3 3L17.273 7 20 9.667" />
                            </svg>
                            <span class="ml-3">Analytics</span>
                        </a>
                    </li>
                </ul>
            </div>
        </aside>

        <header class="md:ml-64 pt-13.5 ">
            <nav class="bg-white border-gray-200 dark:bg-gray-800 w-full drop-shadow-sm pb-2">
                <div class="flex flex-col justify-between items-center mx-auto max-w-screen-xl">
                    <div class="realtive flex items-center justify-center w-full">
                        <p class="text-xl font-semibold  text-gray-900 dark:text-white">Oral Hygiene Findings
                        </p>
                        <!-- Print Btn -->
                        <button type="button" onclick="generateReport()"
                            class="text-white cursor-pointer flex flex-row items-center absolute mt-3 right-2 gap-1 bg-blue-700 hover:bg-blue-800 font-medium rounded-sm text-xs px-1 lg:py-1 mr-2  dark:bg-primary-600 dark:hover:bg-primary-700 focus:outline-none dark:focus:ring-primary-800">
                            <svg class="w-5 h-5 text-white-800 dark:text-white" aria-hidden="true"
                                xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none"
                                viewBox="0 0 24 24">
                                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M12 13V4M7 14H5a1 1 0 0 0-1 1v4a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-4a1 1 0 0 0-1-1h-2m-1-5-4 5-4-5m9 8h.01" />
                            </svg>
                            Download
                        </button>
                    </div>
                    <!-- Report Toggle Buttons -->
                    <div class="flex justify-center">
                        <div class="inline-flex rounded-md shadow-sm" role="group">
                            <button type="button" id="main-report-btn" onclick="showMainReport()"
                                class="px-4 py-2 text-sm font-medium text-white bg-blue-600 border border-blue-600 rounded-l-lg hover:bg-blue-100 hover:text-blue-700 cursor-pointer ">
                                Consolidated Oral Health Report
                            </button>
                            <button type="button" id="fhis-report-btn" onclick="showFhisReport()"
                                class="px-4 py-2 text-sm font-medium text-gray-900 bg-white border border-gray-200 rounded-r-md hover:bg-gray-100 hover:text-blue-700 cursor-pointer">
                                FHSIS Report
                            </button>
                        </div>
                    </div>

                    <!-- Consolidated Oral Health Report Navigation (Visible only for main report) -->
                    <div class="justify-between items-center w-full lg:flex lg:w-auto lg:order-1 main-report-nav <?php echo ($reportType !== 'fhis') ? '' : 'hidden'; ?>"
                        id="mobile-menu-2">
                        <ul class="flex flex-col mt-4 font-medium lg:flex-row lg:space-x-8 lg:mt-0">
                            <li>
                                <a href="?uid=<?php echo $userId; ?>&report_type=main&period=monthly"
                                    class="block py-2 pr-4 pl-3 <?php echo (!isset($_GET['period']) || $_GET['period'] == 'monthly') ? 'text-blue-800 font-semibold' : 'text-gray-700'; ?> border-b border-gray-100 hover:bg-gray-50 lg:hover:bg-transparent lg:border-0 lg:hover:text-primary-700 lg:p-0 dark:text-gray-400 lg:dark:hover:text-white dark:hover:bg-gray-700 dark:hover:text-white lg:dark:hover:bg-transparent dark:border-gray-700">Monthly</a>
                            </li>
                            <li>
                                <a href="?uid=<?php echo $userId; ?>&report_type=main&period=quarterly"
                                    class="block py-2 pr-4 pl-3 <?php echo (isset($_GET['period']) && $_GET['period'] == 'quarterly') ? 'text-blue-800 font-semibold' : 'text-gray-700'; ?> border-b border-gray-100 hover:bg-gray-50 lg:hover:bg-transparent lg:border-0 lg:hover:text-primary-700 lg:p-0 dark:text-gray-400 lg:dark:hover:text-white dark:hover:bg-gray-700 dark:hover:text-white lg:dark:hover:bg-transparent dark:border-gray-700">Quarterly</a>
                            </li>
                            <li>
                                <a href="?uid=<?php echo $userId; ?>&report_type=main&period=semi_annual"
                                    class="block py-2 pr-4 pl-3 <?php echo (isset($_GET['period']) && $_GET['period'] == 'semi_annual') ? 'text-blue-800 font-semibold' : 'text-gray-700'; ?> border-b border-gray-100 hover:bg-gray-50 lg:hover:bg-transparent lg:border-0 lg:hover:text-primary-700 lg:p-0 dark:text-gray-400 lg:dark:hover:text-white dark:hover:bg-gray-700 dark:hover:text-white lg:dark:hover:bg-transparent dark:border-gray-700">Semi-Annual</a>
                            </li>
                            <li>
                                <a href="?uid=<?php echo $userId; ?>&report_type=main&period=annual"
                                    class="block py-2 pr-4 pl-3 <?php echo (isset($_GET['period']) && $_GET['period'] == 'annual') ? 'text-blue-800 font-semibold' : 'text-gray-700'; ?> border-b border-gray-100 hover:bg-gray-50 lg:hover:bg-transparent lg:border-0 lg:hover:text-primary-700 lg:p-0 dark:text-gray-400 lg:dark:hover:text-white dark:hover:bg-gray-700 dark:hover:text-white lg:dark:hover:bg-transparent dark:border-gray-700">Annual</a>
                            </li>
                        </ul>
                    </div>

                    <!-- FHSIS Report Navigation (Visible only for FHSIS report) -->
                    <div class="justify-between items-center w-full lg:flex lg:w-auto lg:order-1 fhis-report-nav <?php echo ($reportType === 'fhis') ? '' : 'hidden'; ?>"
                        id="fhis-mobile-menu">
                        <ul class="flex flex-col mt-4 font-medium lg:flex-row lg:space-x-8 lg:mt-0">
                            <li>
                                <a href="?uid=<?php echo $userId; ?>&report_type=fhis&fhis_period=quarterly&fhis_quarter=1"
                                    class="block py-2 pr-4 pl-3 <?php echo (isset($_GET['fhis_period']) && $_GET['fhis_period'] == 'quarterly' && $_GET['fhis_quarter'] == 1) ? 'text-blue-800 font-semibold' : 'text-gray-700'; ?> border-b border-gray-100 hover:bg-gray-50 lg:hover:bg-transparent lg:border-0 lg:hover:text-primary-700 lg:p-0 dark:text-gray-400 lg:dark:hover:text-white dark:hover:bg-gray-700 dark:hover:text-white lg:dark:hover:bg-transparent dark:border-gray-700">FHSIS Q1</a>
                            </li>
                            <li>
                                <a href="?uid=<?php echo $userId; ?>&report_type=fhis&fhis_period=quarterly&fhis_quarter=2"
                                    class="block py-2 pr-4 pl-3 <?php echo (isset($_GET['fhis_period']) && $_GET['fhis_period'] == 'quarterly' && $_GET['fhis_quarter'] == 2) ? 'text-blue-800 font-semibold' : 'text-gray-700'; ?> border-b border-gray-100 hover:bg-gray-50 lg:hover:bg-transparent lg:border-0 lg:hover:text-primary-700 lg:p-0 dark:text-gray-400 lg:dark:hover:text-white dark:hover:bg-gray-700 dark:hover:text-white lg:dark:hover:bg-transparent dark:border-gray-700">FHSIS Q2</a>
                            </li>
                            <li>
                                <a href="?uid=<?php echo $userId; ?>&report_type=fhis&fhis_period=quarterly&fhis_quarter=3"
                                    class="block py-2 pr-4 pl-3 <?php echo (isset($_GET['fhis_period']) && $_GET['fhis_period'] == 'quarterly' && $_GET['fhis_quarter'] == 3) ? 'text-blue-800 font-semibold' : 'text-gray-700'; ?> border-b border-gray-100 hover:bg-gray-50 lg:hover:bg-transparent lg:border-0 lg:hover:text-primary-700 lg:p-0 dark:text-gray-400 lg:dark:hover:text-white dark:hover:bg-gray-700 dark:hover:text-white lg:dark:hover:bg-transparent dark:border-gray-700">FHSIS Q3</a>
                            </li>
                            <li>
                                <a href="?uid=<?php echo $userId; ?>&report_type=fhis&fhis_period=quarterly&fhis_quarter=4"
                                    class="block py-2 pr-4 pl-3 <?php echo (isset($_GET['fhis_period']) && $_GET['fhis_period'] == 'quarterly' && $_GET['fhis_quarter'] == 4) ? 'text-blue-800 font-semibold' : 'text-gray-700'; ?> border-b border-gray-100 hover:bg-gray-50 lg:hover:bg-transparent lg:border-0 lg:hover:text-primary-700 lg:p-0 dark:text-gray-400 lg:dark:hover:text-white dark:hover:bg-gray-700 dark:hover:text-white lg:dark:hover:bg-transparent dark:border-gray-700">FHSIS Q4</a>
                            </li>
                            <li>
                                <a href="?uid=<?php echo $userId; ?>&report_type=fhis&fhis_period=semi_annual&fhis_semi_annual=1"
                                    class="block py-2 pr-4 pl-3 <?php echo (isset($_GET['fhis_period']) && $_GET['fhis_period'] == 'semi_annual' && $_GET['fhis_semi_annual'] == 1) ? 'text-blue-800 font-semibold' : 'text-gray-700'; ?> border-b border-gray-100 hover:bg-gray-50 lg:hover:bg-transparent lg:border-0 lg:hover:text-primary-700 lg:p-0 dark:text-gray-400 lg:dark:hover:text-white dark:hover:bg-gray-700 dark:hover:text-white lg:dark:hover:bg-transparent dark:border-gray-700">FHSIS Semi 1</a>
                            </li>
                            <li>
                                <a href="?uid=<?php echo $userId; ?>&report_type=fhis&fhis_period=semi_annual&fhis_semi_annual=2"
                                    class="block py-2 pr-4 pl-3 <?php echo (isset($_GET['fhis_period']) && $_GET['fhis_period'] == 'semi_annual' && $_GET['fhis_semi_annual'] == 2) ? 'text-blue-800 font-semibold' : 'text-gray-700'; ?> border-b border-gray-100 hover:bg-gray-50 lg:hover:bg-transparent lg:border-0 lg:hover:text-primary-700 lg:p-0 dark:text-gray-400 lg:dark:hover:text-white dark:hover:bg-gray-700 dark:hover:text-white lg:dark:hover:bg-transparent dark:border-gray-700">FHSIS Semi 2</a>
                            </li>
                            <li>
                                <a href="?uid=<?php echo $userId; ?>&report_type=fhis&fhis_period=annual"
                                    class="block py-2 pr-4 pl-3 <?php echo (isset($_GET['fhis_period']) && $_GET['fhis_period'] == 'annual') ? 'text-blue-800 font-semibold' : 'text-gray-700'; ?> border-b border-gray-100 hover:bg-gray-50 lg:hover:bg-transparent lg:border-0 lg:hover:text-primary-700 lg:p-0 dark:text-gray-400 lg:dark:hover:text-white dark:hover:bg-gray-700 dark:hover:text-white lg:dark:hover:bg-transparent dark:border-gray-700">FHSIS Annual</a>
                            </li>
                        </ul>
                    </div>
                </div>
            </nav>
        </header>

        <main class="p-3 md:ml-64 h-auto pt-0.5">
            <!-- Main Report Section -->
            <section id="main-report-section" class="bg-white dark:bg-gray-900 p-3 rounded-lg mb-3 mt-3">
                <!-- Report Controls -->
                <div class="w-full flex flex-row p-1 justify-between mb-4">
                    <div class="flex flex-row items-end space-x-4">
                        <?php if ($periodType === 'quarterly'): ?>
                            <!-- Quarterly Selection -->
                            <div>
                                <label for="report-quarter" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Quarter</label>
                                <select id="report-quarter" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                    <option value="1" <?php echo $selectedQuarter == 1 ? 'selected' : ''; ?>>1st Quarter (Jan-Mar)</option>
                                    <option value="2" <?php echo $selectedQuarter == 2 ? 'selected' : ''; ?>>2nd Quarter (Apr-Jun)</option>
                                    <option value="3" <?php echo $selectedQuarter == 3 ? 'selected' : ''; ?>>3rd Quarter (Jul-Sep)</option>
                                    <option value="4" <?php echo $selectedQuarter == 4 ? 'selected' : ''; ?>>4th Quarter (Oct-Dec)</option>
                                </select>
                            </div>
                        <?php elseif ($periodType === 'semi_annual'): ?>
                            <!-- Semi-Annual Selection -->
                            <div>
                                <label for="report-semi-annual" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Semi-Annual</label>
                                <select id="report-semi-annual" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                    <option value="1" <?php echo $selectedSemiAnnual == 1 ? 'selected' : ''; ?>>Semi-Annual 1 (Jan-Jun)</option>
                                    <option value="2" <?php echo $selectedSemiAnnual == 2 ? 'selected' : ''; ?>>Semi-Annual 2 (Jul-Dec)</option>
                                </select>
                            </div>
                        <?php elseif ($periodType === 'annual'): ?>
                            <!-- Annual Selection (No dropdown needed, just show label) -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Annual Period</label>
                                <div class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-gray-100 rounded-md shadow-sm text-sm">
                                    January - December
                                </div>
                            </div>
                        <?php else: ?>
                            <!-- Monthly Selection -->
                            <div>
                                <label for="report-month" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Month</label>
                                <select id="report-month" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                    <option value="1" <?php echo $selectedMonth == 1 ? 'selected' : ''; ?>>January</option>
                                    <option value="2" <?php echo $selectedMonth == 2 ? 'selected' : ''; ?>>February</option>
                                    <option value="3" <?php echo $selectedMonth == 3 ? 'selected' : ''; ?>>March</option>
                                    <option value="4" <?php echo $selectedMonth == 4 ? 'selected' : ''; ?>>April</option>
                                    <option value="5" <?php echo $selectedMonth == 5 ? 'selected' : ''; ?>>May</option>
                                    <option value="6" <?php echo $selectedMonth == 6 ? 'selected' : ''; ?>>June</option>
                                    <option value="7" <?php echo $selectedMonth == 7 ? 'selected' : ''; ?>>July</option>
                                    <option value="8" <?php echo $selectedMonth == 8 ? 'selected' : ''; ?>>August</option>
                                    <option value="9" <?php echo $selectedMonth == 9 ? 'selected' : ''; ?>>September</option>
                                    <option value="10" <?php echo $selectedMonth == 10 ? 'selected' : ''; ?>>October</option>
                                    <option value="11" <?php echo $selectedMonth == 11 ? 'selected' : ''; ?>>November</option>
                                    <option value="12" <?php echo $selectedMonth == 12 ? 'selected' : ''; ?>>December</option>
                                </select>
                            </div>
                        <?php endif; ?>

                        <div>
                            <label for="report-year" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Year</label>
                            <input type="number" id="report-year" value="<?php echo $selectedYear; ?>"
                                class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                        </div>

                        <div class="flex items-end">
                            <button type="button" onclick="loadReportData()"
                                class="px-4 py-2 cursor-pointer bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                                Load Report
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Main Report Table -->
                <div class="table-container">
                    <table class="age-table border-collapse border border-gray-400 w-full bg-white">
                        <thead>
                            <!-- Title -->
                            <tr>
                                <th colspan="43" class="border border-gray-400 px-2 py-2 text-center font-bold bg-gray-100">
                                    CONSOLIDATED ORAL HEALTH STATUS AND SERVICES
                                    <?php
                                    switch ($periodType) {
                                        case 'quarterly':
                                            echo 'QUARTERLY';
                                            break;
                                        case 'semi_annual':
                                            echo 'SEMI-ANNUAL';
                                            break;
                                        case 'annual':
                                            echo 'ANNUAL';
                                            break;
                                        default:
                                            echo 'MONTHLY';
                                    }
                                    ?>
                                    REPORT
                                </th>
                            </tr>
                            <!-- Month/Year -->
                            <tr>
                                <th class="border border-gray-400 px-2 py-1 text-left font-medium">Month/Quarter/Year</th>
                                <th colspan="42" class="border border-gray-400 px-2 py-1 text-left" id="report-period-display">
                                    <?php echo $periodDisplay; ?>
                                </th>
                            </tr>
                            <!-- CHD -->
                            <tr>
                                <th class="border border-gray-400 px-2 py-1 text-left font-medium">Center for Health Development</th>
                                <th colspan="42" class="border border-gray-400 px-2 py-1 text-left">MiMaRoPa</th>
                            </tr>
                            <!-- Municipality -->
                            <tr>
                                <th class="border border-gray-400 px-2 py-1 text-left font-medium">Municipality/City/Province</th>
                                <th colspan="42" class="border border-gray-400 px-2 py-1 text-left">MAMBURAO/OCCIDENTAL MINDORO</th>
                            </tr>

                            <tr class="bg-gray-200">
                                <th rowspan="3" class="border border-gray-400 px-1 py-1 text-center sticky-header">INDICATORS</th>

                                <!-- Infant (0-11 mos.) -->
                                <th colspan="2" class="border border-gray-400 px-1 py-1 text-center">Infant<br>(0-11 mos.)</th>

                                <!-- Under Five Children -->
                                <th colspan="10" class="border border-gray-400 px-1 py-1 text-center">Under Five Children</th>

                                <!-- School Age Children -->
                                <th colspan="12" class="border border-gray-400 px-1 py-1 text-center">School Age Children</th>

                                <!-- Adolescent -->
                                <th colspan="4" class="border border-gray-400 px-1 py-1 text-center">Adolescent</th>

                                <!-- Adult -->
                                <th colspan="2" class="border border-gray-400 px-1 py-1 text-center">Adult</th>

                                <!-- Pregnant Women -->
                                <th colspan="3" class="border border-gray-400 px-1 py-1 text-center">Pregnant Women</th>

                                <!-- Older Persons -->
                                <th colspan="2" class="border border-gray-400 px-1 py-1 text-center">Older Persons</th>

                                <!-- TOTAL ALL AGES -->
                                <th colspan="2" class="border border-gray-400 px-1 py-1 text-center">TOTAL<br>ALL AGES</th>

                                <!-- GRAND TOTAL -->
                                <th colspan="2" rowspan="2" class="border border-gray-400 px-1 py-1 text-center">GRAND TOTAL</th>
                            </tr>

                            <tr class="bg-gray-100">
                                <!-- Infant -->
                                <th class="border border-gray-400 px-1 py-1 text-center">M</th>
                                <th class="border border-gray-400 px-1 py-1 text-center">F</th>

                                <!-- Under Five Children (1-4) -->
                                <th colspan="2" class="border border-gray-400 px-1 py-1 text-center">1</th>
                                <th colspan="2" class="border border-gray-400 px-1 py-1 text-center">2</th>
                                <th colspan="2" class="border border-gray-400 px-1 py-1 text-center">3</th>
                                <th colspan="2" class="border border-gray-400 px-1 py-1 text-center">4</th>
                                <th class="border border-gray-400 px-1 py-1 text-center">TOTAL M</th>
                                <th class="border border-gray-400 px-1 py-1 text-center">TOTAL F</th>

                                <!-- School Age Children (5-9) -->
                                <th colspan="2" class="border border-gray-400 px-1 py-1 text-center">5</th>
                                <th colspan="2" class="border border-gray-400 px-1 py-1 text-center">6</th>
                                <th colspan="2" class="border border-gray-400 px-1 py-1 text-center">7</th>
                                <th colspan="2" class="border border-gray-400 px-1 py-1 text-center">8</th>
                                <th colspan="2" class="border border-gray-400 px-1 py-1 text-center">9</th>
                                <th class="border border-gray-400 px-1 py-1 text-center">TOTAL M</th>
                                <th class="border border-gray-400 px-1 py-1 text-center">TOTAL F</th>

                                <!-- Adolescent (10-19) -->
                                <th class="border border-gray-400 px-1 py-1 text-center">10-14 M</th>
                                <th class="border border-gray-400 px-1 py-1 text-center">10-14 F</th>
                                <th class="border border-gray-400 px-1 py-1 text-center">15-19 M</th>
                                <th class="border border-gray-400 px-1 py-1 text-center">15-19 F</th>

                                <!-- Adult (20-59) -->
                                <th class="border border-gray-400 px-1 py-1 text-center">20-59 M</th>
                                <th class="border border-gray-400 px-1 py-1 text-center">20-59 F</th>

                                <!-- Pregnant Women -->
                                <th class="border border-gray-400 px-1 py-1 text-center">10-25 F</th>
                                <th class="border border-gray-400 px-1 py-1 text-center">15-19 F</th>
                                <th class="border border-gray-400 px-1 py-1 text-center">20-49 F</th>

                                <!-- Older Persons (60+) -->
                                <th class="border border-gray-400 px-1 py-1 text-center">60+ M</th>
                                <th class="border border-gray-400 px-1 py-1 text-center">60+ F</th>

                                <!-- TOTAL ALL AGES -->
                                <th class="border border-gray-400 px-1 py-1 text-center">TOTAL M</th>
                                <th class="border border-gray-400 px-1 py-1 text-center">TOTAL F</th>
                            </tr>

                            <tr class="bg-gray-50">
                                <!-- Column identifiers for data entry -->
                                <th class="border border-gray-400 px-1 py-1 text-center sticky-header">M</th>
                                <!-- Infant -->
                                <th class="border border-gray-400 px-1 py-1 text-center">F</th>
                                <th class="border border-gray-400 px-1 py-1 text-center">M</th>

                                <!-- Under Five Children -->
                                <th class="border border-gray-400 px-1 py-1 text-center">F</th>
                                <th class="border border-gray-400 px-1 py-1 text-center">M</th>
                                <th class="border border-gray-400 px-1 py-1 text-center">F</th>
                                <th class="border border-gray-400 px-1 py-1 text-center">M</th>
                                <th class="border border-gray-400 px-1 py-1 text-center">F</th>
                                <th class="border border-gray-400 px-1 py-1 text-center">M</th>
                                <th class="border border-gray-400 px-1 py-1 text-center">F</th>
                                <th class="border border-gray-400 px-1 py-1 text-center">M</th>
                                <th class="border border-gray-400 px-1 py-1 text-center">F</th>
                                <th class="border border-gray-400 px-1 py-1 text-center">M</th>

                                <!-- School Age Children -->
                                <th class="border border-gray-400 px-1 py-1 text-center">F</th>
                                <th class="border border-gray-400 px-1 py-1 text-center">M</th>
                                <th class="border border-gray-400 px-1 py-1 text-center">F</th>
                                <th class="border border-gray-400 px-1 py-1 text-center">M</th>
                                <th class="border border-gray-400 px-1 py-1 text-center">F</th>
                                <th class="border border-gray-400 px-1 py-1 text-center">M</th>
                                <th class="border border-gray-400 px-1 py-1 text-center">F</th>
                                <th class="border border-gray-400 px-1 py-1 text-center">M</th>
                                <th class="border border-gray-400 px-1 py-1 text-center">F</th>
                                <th class="border border-gray-400 px-1 py-1 text-center">M</th>
                                <th class="border border-gray-400 px-1 py-1 text-center">F</th>
                                <th class="border border-gray-400 px-1 py-1 text-center">M</th>

                                <!-- Adolescent -->
                                <th class="border border-gray-400 px-1 py-1 text-center">F</th>
                                <th class="border border-gray-400 px-1 py-1 text-center">M</th>
                                <th class="border border-gray-400 px-1 py-1 text-center">F</th>
                                <th class="border border-gray-400 px-1 py-1 text-center">M</th>

                                <!-- Adult -->
                                <th class="border border-gray-400 px-1 py-1 text-center">F</th>
                                <th class="border border-gray-400 px-1 py-1 text-center">F</th>

                                <!-- Pregnant Women -->
                                <th class="border border-gray-400 px-1 py-1 text-center">F</th>
                                <th class="border border-gray-400 px-1 py-1 text-center">F</th>
                                <th class="border border-gray-400 px-1 py-1 text-center">M</th>

                                <!-- Older Persons -->
                                <th class="border border-gray-400 px-1 py-1 text-center">F</th>
                                <th class="border border-gray-400 px-1 py-1 text-center">M</th>

                                <!-- TOTAL ALL AGES -->
                                <th class="border border-gray-400 px-1 py-1 text-center">F</th>
                                <th class="border border-gray-400 px-1 py-1 text-center"></th>

                                <!-- GRAND TOTAL -->
                                <!-- <th class="border border-gray-400 px-1 py-1 text-center">AN1</th> -->
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Define the sections and rows based on the Excel structure
                            $sections = [
                                'NO. OF PERSON ATTENDED' => ['data_type' => 'person_attended', 'row_class' => ''],
                                'NO. OF PERSON EXAMINED' => ['data_type' => 'person_examined', 'row_class' => ''],
                                'A. MEDICAL HISTORY STATUS' => [
                                    'row_class' => 'section-header',
                                    'subrows' => [
                                        ['title' => '1. Total No. with Allergies', 'data_type' => 'allergies'],
                                        ['title' => '2. Total No. with Hypertension/ CVA', 'data_type' => 'hypertension'],
                                        ['title' => '3. Total No. with Diabetes Mellitus', 'data_type' => 'diabetes'],
                                        ['title' => '4. Total No. with Blood Disorders', 'data_type' => 'blood_disorders'],
                                        ['title' => '5. Total No. with Cardiovascular/Heart Diseases', 'data_type' => 'heart_disease'],
                                        ['title' => '6. Total No. with Thyroid Disorders', 'data_type' => 'thyroid_disorders'],
                                        ['title' => '7. Total No. with Hepatitis', 'data_type' => 'hepatitis'],
                                        ['title' => '8. Total No. with Malignancy', 'data_type' => 'malignancy'],
                                        ['title' => '9. Total No. with History of Previous Hospitalization', 'data_type' => 'prev_hospitalization'],
                                        ['title' => '10. Total No. with Blood Transfusion', 'data_type' => 'blood_transfusion'],
                                        ['title' => '11. Total No. with Tattoo', 'data_type' => 'tattoo']
                                    ]
                                ],
                                'B. DIETARY / SOCIAL HISTORY STATUS' => [
                                    'row_class' => 'section-header',
                                    'subrows' => [
                                        ['title' => '1. Total No. of Sugar Sweetened Beverages/Food Drinker/Eater', 'data_type' => 'sugar_consumer'],
                                        ['title' => '2. Total No. of Alcohol Drinker', 'data_type' => 'alcohol_drinker'],
                                        ['title' => '3. Total No. of Tobacco User', 'data_type' => 'tobacco_user'],
                                        ['title' => '4. Total No. of Betel Nut Chewer', 'data_type' => 'betel_nut_chewer']
                                    ]
                                ],
                                'C. ORAL HEALTH STATUS' => [
                                    'row_class' => 'section-header',
                                    'subrows' => [
                                        ['title' => '1. Total No. with Dental Caries', 'data_type' => 'dental_caries'],
                                        ['title' => '2. Total No. with Gingivitis', 'data_type' => 'gingivitis'],
                                        ['title' => '3. Total No. with Periodontal Disease', 'data_type' => 'periodontal_disease'],
                                        ['title' => '4. Total No. with Oral Debris', 'data_type' => 'oral_debris'],
                                        ['title' => '5. Total No. with Calculus', 'data_type' => 'calculus'],
                                        ['title' => '6. Total No. with Dento Facial Anomalies (cleft lip/palate. Malocclusion, etc)', 'data_type' => 'dento_facial_anomalies'],
                                        [
                                            'title' => '7. Total (d/f)',
                                            'type' => 'calculated',
                                            'calculation' => function ($reportData, $category) {
                                                $decayed = $reportData['decayed_primary'][$category] ?? 0;
                                                $filled = $reportData['filled_primary'][$category] ?? 0;
                                                return $decayed + $filled;
                                            }
                                        ],
                                        [
                                            'title' => 'a. Total decayed (d)',
                                            'data_type' => 'decayed_primary'
                                        ],
                                        [
                                            'title' => 'b. Total filled (f)',
                                            'data_type' => 'filled_primary'
                                        ],
                                        [
                                            'title' => '8. Total (D/M/F)',
                                            'type' => 'calculated',
                                            'calculation' => function ($reportData, $category) {
                                                $decayed = $reportData['decayed_permanent'][$category] ?? 0;
                                                $missing = $reportData['missing_permanent'][$category] ?? 0;
                                                $filled = $reportData['filled_permanent'][$category] ?? 0;
                                                return $decayed + $missing + $filled;
                                            }
                                        ],
                                        [
                                            'title' => 'a. Total Decayed (D)',
                                            'data_type' => 'decayed_permanent'
                                        ],
                                        [
                                            'title' => 'b. Total Missing (M)',
                                            'data_type' => 'missing_permanent'
                                        ],
                                        [
                                            'title' => 'c. Total Filled (F)',
                                            'data_type' => 'filled_permanent'
                                        ]
                                    ]
                                ],
                                'D. SERVICES RENDERED' => [
                                    'row_class' => 'section-header',
                                    'subrows' => [
                                        ['title' => '1. No. Given OP / Scaling', 'data_type' => 'oral_prophylaxis'],
                                        ['title' => '2. No. Given Permanent Fillings', 'data_type' => 'permanent_filling'],
                                        ['title' => '3. No. Given Temporary Fillings', 'data_type' => 'temporary_filling'],
                                        ['title' => '4. No. Given Extraction', 'data_type' => 'extraction'],
                                        ['title' => '5. No. Given Gum Treatment', 'data_type' => 'gum_treatment'],
                                        ['title' => '6. No. Given Sealant', 'data_type' => 'sealant'],
                                        ['title' => '7. No. Completed Fluoride Therapy', 'data_type' => 'fluoride_therapy'],
                                        ['title' => '8. No. Given Post-Operative Treatment', 'data_type' => 'post_operative'],
                                        ['title' => '9. No. of Patient with Oral Abscess Drained', 'data_type' => 'abscess_drained'],
                                        ['title' => '10. No. Given Other Services (Atraumatic Restorative Treatment, Relief of Pain, etc.)', 'data_type' => 'other_services'],
                                        ['title' => '11. No. Referred', 'data_type' => 'referred'],
                                        ['title' => '12. No. Given Counseling / Education on Tobacco, Oral Health, Diet, Instruction on Infants Oral Health Care, Advise on Exclusive Breastfeeding, etc.', 'data_type' => 'counseling'],
                                        ['title' => '13. No. of Under Six Children Completed Toothbrush Drill / One-on-One Supervised Toothbrushing', 'data_type' => 'toothbrush_drill']
                                    ]
                                ],
                                'E. NO. OF ORALLY FIT CHILDREN (OFC)' => [
                                    'row_class' => 'section-header',
                                    'subrows' => [
                                        ['title' => '1. OFC Upon Oral Examination', 'data_type' => 'ofc_examination'],
                                        ['title' => '2. OFC Upon Complete Oral Rehabilitation', 'data_type' => 'ofc_rehabilitation']
                                    ]
                                ]
                            ];

                            // Define age-sex categories in the order they appear in the table
                            $ageSexCategories = [
                                // Infant
                                'infant_m',
                                'infant_f',
                                // Under Five Children
                                'under_five_1_m',
                                'under_five_1_f',
                                'under_five_2_m',
                                'under_five_2_f',
                                'under_five_3_m',
                                'under_five_3_f',
                                'under_five_4_m',
                                'under_five_4_f',
                                'under_five_total_m',
                                'under_five_total_f',
                                // School Age Children
                                'school_age_5_m',
                                'school_age_5_f',
                                'school_age_6_m',
                                'school_age_6_f',
                                'school_age_7_m',
                                'school_age_7_f',
                                'school_age_8_m',
                                'school_age_8_f',
                                'school_age_9_m',
                                'school_age_9_f',
                                'school_age_total_m',
                                'school_age_total_f',
                                // Adolescent
                                'adolescent_10_14_m',
                                'adolescent_10_14_f',
                                'adolescent_15_19_m',
                                'adolescent_15_19_f',
                                // Adult
                                'adult_20_59_m',
                                'adult_20_59_f',
                                // Pregnant Women
                                'pregnant_10_25',
                                'pregnant_15_19',
                                'pregnant_20_49',
                                // Older Persons
                                'older_60_m',
                                'older_60_f',
                                // Total All Ages
                                'total_m',
                                'total_f'
                            ];

                            // Updated function to handle display values - ONLY show zeros for specific totals, empty for everything else
                            function getDisplayValue($dataType, $category, $reportData, $calculatedValue = null)
                            {
                                if ($calculatedValue !== null) {
                                    $value = $calculatedValue;
                                } else {
                                    $value = $reportData[$dataType][$category] ?? 0;
                                }

                                // Define which columns should show zeros (only these specific ones)
                                $showZeroColumns = [
                                    'total_m',
                                    'total_f',
                                    'grand_total'
                                ];

                                // If it's one of the specific columns that should show zero, return the value
                                if (in_array($category, $showZeroColumns)) {
                                    return $value;
                                }

                                // For ALL other columns, show empty if zero
                                if ($value == 0) {
                                    return '';
                                }

                                return $value;
                            }

                            // Generate table rows
                            foreach ($sections as $sectionTitle => $sectionData) {
                                $isSection = isset($sectionData['subrows']);
                                $rowClass = $sectionData['row_class'] ?? '';

                                if ($isSection) {
                                    // Section header row
                                    echo "<tr class='$rowClass'>";
                                    echo "<td class='border border-gray-400 px-2 py-1 font-bold sticky-header'>$sectionTitle</td>";
                                    // Add empty cells for all data columns plus grand total
                                    for ($i = 0; $i < 38; $i++) {
                                        echo "<td class='border border-gray-400 px-1 py-1'></td>";
                                    }
                                    echo "</tr>";

                                    // Sub-rows
                                    foreach ($sectionData['subrows'] as $subrow) {
                                        if (isset($subrow['type']) && $subrow['type'] === 'subsection') {
                                            // This is a sub-section with its own subrows
                                            echo "<tr>";
                                            echo "<td class='border border-gray-400 px-2 py-1 sticky-header'>{$subrow['title']}</td>";
                                            // Add empty cells for all data columns plus grand total
                                            for ($i = 0; $i < 38; $i++) {
                                                echo "<td class='border border-gray-400 px-1 py-1'></td>";
                                            }
                                            echo "</tr>";

                                            // Sub-subrows
                                            foreach ($subrow['subrows'] as $subSubrow) {
                                                echo "<tr>";
                                                echo "<td class='border border-gray-400 px-4 py-1 sticky-header'>{$subSubrow['title']}</td>";

                                                // Generate data cells for all categories
                                                foreach ($ageSexCategories as $category) {
                                                    $value = getDisplayValue($subSubrow['data_type'], $category, $reportData);
                                                    echo "<td class='border border-gray-400 px-1 py-1 text-center data-cell'>$value</td>";
                                                }

                                                // Grand total cell
                                                $grandTotal = getDisplayValue($subSubrow['data_type'], 'grand_total', $reportData);
                                                echo "<td class='border border-gray-400 px-1 py-1 text-center data-cell grand-total-row'>$grandTotal</td>";

                                                echo "</tr>";
                                            }
                                        } elseif (isset($subrow['type']) && $subrow['type'] === 'calculated') {
                                            // This is a calculated row
                                            echo "<tr>";
                                            echo "<td class='border border-gray-400 px-2 py-1 sticky-header'>{$subrow['title']}</td>";

                                            // Generate calculated data cells for all categories
                                            foreach ($ageSexCategories as $category) {
                                                $value = call_user_func($subrow['calculation'], $reportData, $category);
                                                // Apply the same display rules
                                                $displayValue = getDisplayValue(null, $category, $reportData, $value);
                                                echo "<td class='border border-gray-400 px-1 py-1 text-center data-cell'>$displayValue</td>";
                                            }

                                            // Calculate and display grand total for calculated row
                                            $grandTotal = 0;
                                            foreach ($ageSexCategories as $category) {
                                                if (in_array($category, ['total_m', 'total_f', 'grand_total'])) {
                                                    $grandTotal += call_user_func($subrow['calculation'], $reportData, $category);
                                                }
                                            }
                                            $displayGrandTotal = ($grandTotal == 0) ? '' : $grandTotal;
                                            echo "<td class='border border-gray-400 px-1 py-1 text-center data-cell grand-total-row'>$displayGrandTotal</td>";

                                            echo "</tr>";
                                        } else {
                                            // Regular sub-row
                                            $dataType = $subrow['data_type'];
                                            echo "<tr>";
                                            echo "<td class='border border-gray-400 px-2 py-1 sticky-header'>{$subrow['title']}</td>";

                                            // Generate data cells for all categories using the display function
                                            foreach ($ageSexCategories as $category) {
                                                $value = getDisplayValue($dataType, $category, $reportData);
                                                echo "<td class='border border-gray-400 px-1 py-1 text-center data-cell'>$value</td>";
                                            }

                                            // Grand total cell
                                            $grandTotal = getDisplayValue($dataType, 'grand_total', $reportData);
                                            echo "<td class='border border-gray-400 px-1 py-1 text-center data-cell grand-total-row'>$grandTotal</td>";

                                            echo "</tr>";
                                        }
                                    }
                                } else {
                                    // Regular row (not a section)
                                    $dataType = $sectionData['data_type'];
                                    echo "<tr class='$rowClass'>";
                                    echo "<td class='border border-gray-400 px-2 py-1 sticky-header'>$sectionTitle</td>";

                                    // Generate data cells for all categories using the display function
                                    foreach ($ageSexCategories as $category) {
                                        $value = getDisplayValue($dataType, $category, $reportData);
                                        echo "<td class='border border-gray-400 px-1 py-1 text-center data-cell'>$value</td>";
                                    }

                                    // Grand total cell
                                    $grandTotal = getDisplayValue($dataType, 'grand_total', $reportData);
                                    echo "<td class='border border-gray-400 px-1 py-1 text-center data-cell grand-total-row'>$grandTotal</td>";

                                    echo "</tr>";
                                }
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- FHSIS Report Section -->
            <section id="fhis-section" class="bg-white dark:bg-gray-900 p-3 rounded-lg mb-3 mt-3">
                <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-4">FHSIS Report - Oral Health Care and Services</h2>

                <div class="table-container">
                    <table class="fhis-table border-collapse border border-gray-400 w-full bg-white">
                        <thead>
                            <tr>
                                <th colspan="9" class="border border-gray-400 px-2 py-2 text-center font-bold bg-gray-100">
                                    FHSIS REPORT for the Quarter:
                                    <?php
                                    if (isset($_GET['fhis_period'])) {
                                        if ($_GET['fhis_period'] == 'quarterly') {
                                            echo ['1ST', '2ND', '3RD', '4TH'][$fhisQuarter - 1] . ' QTR.';
                                        } elseif ($_GET['fhis_period'] == 'semi_annual') {
                                            echo ['1ST', '2ND'][$fhisSemiAnnual - 1] . ' SEMI-ANNUAL';
                                        } elseif ($_GET['fhis_period'] == 'annual') {
                                            echo 'ANNUAL';
                                        }
                                    } else {
                                        echo '1ST QTR.';
                                    }
                                    ?>
                                    YEAR: <?php echo date('Y'); ?> RHU
                                </th>
                            </tr>
                            <tr>
                                <th class="border border-gray-400 px-2 py-1 text-left font-medium">Name of Municipality/City:</th>
                                <th colspan="8" class="border border-gray-400 px-2 py-1 text-left">MAMBURAO</th>
                            </tr>
                            <tr>
                                <th class="border border-gray-400 px-2 py-1 text-left font-medium">Name of Province:</th>
                                <th colspan="8" class="border border-gray-400 px-2 py-1 text-left">OCCIDENTAL MINDORO</th>
                            </tr>
                            <tr>
                                <th class="border border-gray-400 px-2 py-1 text-left font-medium">Projected Population of the Year:</th>
                                <th colspan="8" class="border border-gray-400 px-2 py-1 text-left">49930</th>
                            </tr>
                            <tr>
                                <th colspan="9" class="border border-gray-400 px-2 py-1 text-left font-medium bg-gray-200">
                                    Section D. Oral Health Care and Services
                                </th>
                            </tr>
                            <tr class="bg-gray-200">
                                <th rowspan="2" class="border border-gray-400 px-2 py-2 text-center">Indicators</th>
                                <th rowspan="2" class="border border-gray-400 px-2 py-2 text-center">Eligible Population</th>
                                <th colspan="3" class="border border-gray-400 px-2 py-2 text-center">Counts</th>
                                <th rowspan="2" class="border border-gray-400 px-2 py-2 text-center">%<br>(Col. 5/E.Pop x 100)</th>
                                <th rowspan="2" class="border border-gray-400 px-2 py-2 text-center">Interpretation</th>
                                <th rowspan="2" class="border border-gray-400 px-2 py-2 text-center">Recommendation/Actions Taken</th>
                            </tr>
                            <tr class="bg-gray-200">
                                <th class="border border-gray-400 px-2 py-2 text-center">Male</th>
                                <th class="border border-gray-400 px-2 py-2 text-center">Female</th>
                                <th class="border border-gray-400 px-2 py-2 text-center">Total</th>
                            </tr>
                            <tr class="bg-gray-100">
                                <th class="border border-gray-400 px-2 py-1 text-center">(Col. 1)</th>
                                <th class="border border-gray-400 px-2 py-1 text-center">(Col. 2)</th>
                                <th class="border border-gray-400 px-2 py-1 text-center">(Col. 3)</th>
                                <th class="border border-gray-400 px-2 py-1 text-center">(Col. 4)</th>
                                <th class="border border-gray-400 px-2 py-1 text-center">(Col. 5)</th>
                                <th class="border border-gray-400 px-2 py-1 text-center">(Col. 6)</th>
                                <th class="border border-gray-400 px-2 py-1 text-center">(Col. 7)</th>
                                <th class="border border-gray-400 px-2 py-1 text-center">(Col. 8)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // FHSIS Indicators data - you'll need to calculate these based on your actual data
                            $fhisIndicators = [
                                [
                                    'indicator' => '1. Orally fit children 12-59 months old upon oral examination plus orally fit after rehabilitation - Total',
                                    'eligible_population' => 4194,
                                    'male_count' => getReportValue('ofc_examination', 'under_five_total_m', $reportData) + getReportValue('ofc_rehabilitation', 'under_five_total_m', $reportData),
                                    'female_count' => getReportValue('ofc_examination', 'under_five_total_f', $reportData) + getReportValue('ofc_rehabilitation', 'under_five_total_f', $reportData)
                                ],
                                [
                                    'indicator' => '2. Clients 5 years old and above with cases of DMF - Total',
                                    'eligible_population' => 'Actual',
                                    'male_count' => 41, // You'll need to calculate this from your data
                                    'female_count' => 46  // You'll need to calculate this from your data
                                ],
                                [
                                    'indicator' => '3. Infants 0-11 months old who received BOHC - Total',
                                    'eligible_population' => 1024,
                                    'male_count' => getReportValue('person_examined', 'infant_m', $reportData),
                                    'female_count' => getReportValue('person_examined', 'infant_f', $reportData)
                                ],
                                [
                                    'indicator' => '4. Children 1-4 years old who received BOHC - Total',
                                    'eligible_population' => 4194,
                                    'male_count' => getReportValue('person_examined', 'under_five_total_m', $reportData),
                                    'female_count' => getReportValue('person_examined', 'under_five_total_f', $reportData)
                                ],
                                [
                                    'indicator' => '5. Children 5-9 years old who received BOHC - Total',
                                    'eligible_population' => 5791,
                                    'male_count' => getReportValue('person_examined', 'school_age_total_m', $reportData),
                                    'female_count' => getReportValue('person_examined', 'school_age_total_f', $reportData)
                                ],
                                [
                                    'indicator' => '6. Adolescents 10-14 years old who received BOHC - Total',
                                    'eligible_population' => 5489,
                                    'male_count' => getReportValue('person_examined', 'adolescent_10_14_m', $reportData),
                                    'female_count' => getReportValue('person_examined', 'adolescent_10_14_f', $reportData)
                                ],
                                [
                                    'indicator' => 'Adolescents 15-19 years old who received BOHC - Total',
                                    'eligible_population' => 5238,
                                    'male_count' => getReportValue('person_examined', 'adolescent_15_19_m', $reportData),
                                    'female_count' => getReportValue('person_examined', 'adolescent_15_19_f', $reportData)
                                ],
                                [
                                    'indicator' => '7. Adults 20-59 years old who received BOHC - Total',
                                    'eligible_population' => 25181,
                                    'male_count' => getReportValue('person_examined', 'adult_20_59_m', $reportData),
                                    'female_count' => getReportValue('person_examined', 'adult_20_59_f', $reportData)
                                ],
                                [
                                    'indicator' => '8. Senior citizens 60 years old and above who received BOHC - Total',
                                    'eligible_population' => 3744,
                                    'male_count' => getReportValue('person_examined', 'older_60_m', $reportData),
                                    'female_count' => getReportValue('person_examined', 'older_60_f', $reportData)
                                ],
                                [
                                    'indicator' => '9a. Pregnant women(10-14) who received BOHC - Total',
                                    'eligible_population' => 2624,
                                    'male_count' => 0,
                                    'female_count' => 0 // You'll need to calculate this from pregnant women data
                                ],
                                [
                                    'indicator' => '9b. Pregnant women(15-19) who received BOHC - Total',
                                    'eligible_population' => 2520,
                                    'male_count' => 0,
                                    'female_count' => 0 // You'll need to calculate this from pregnant women data
                                ],
                                [
                                    'indicator' => '9c. Pregnant women(20-49)who received BOHC - Total',
                                    'eligible_population' => 10259,
                                    'male_count' => 0,
                                    'female_count' => 11 // You'll need to calculate this from pregnant women data
                                ]
                            ];

                            foreach ($fhisIndicators as $indicator) {
                                $total = $indicator['male_count'] + $indicator['female_count'];

                                // Calculate percentage only if eligible population is numeric
                                if (is_numeric($indicator['eligible_population']) && $indicator['eligible_population'] > 0) {
                                    $percentage = round(($total / $indicator['eligible_population']) * 100, 2);
                                } else {
                                    $percentage = '';
                                }

                                echo "<tr>";
                                echo "<td class='border border-gray-400 px-2 py-1 text-left'>{$indicator['indicator']}</td>";
                                echo "<td class='border border-gray-400 px-2 py-1 text-center'>{$indicator['eligible_population']}</td>";
                                echo "<td class='border border-gray-400 px-2 py-1 text-center'>{$indicator['male_count']}</td>";
                                echo "<td class='border border-gray-400 px-2 py-1 text-center'>{$indicator['female_count']}</td>";
                                echo "<td class='border border-gray-400 px-2 py-1 text-center'>$total</td>";
                                echo "<td class='border border-gray-400 px-2 py-1 text-center'>$percentage</td>";
                                echo "<td class='border border-gray-400 px-2 py-1 text-center'></td>"; // Interpretation
                                echo "<td class='border border-gray-400 px-2 py-1 text-center'></td>"; // Recommendation
                                echo "</tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>

                <!-- Additional FHSIS Notes -->
                <div class="mt-4 text-sm text-gray-600">
                    <p><strong>Note:</strong> BOHC = Basic Oral Health Care</p>
                    <p><strong>Note:</strong> For submission to PHO / CHO</p>
                </div>
            </section>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/flowbite@3.1.2/dist/flowbite.min.js"></script>
    <script>
        // Load report data - Updated to properly handle report types
        function loadReportData() {
            const periodType = "<?php echo $periodType; ?>";
            const currentReportType = "<?php echo $reportType; ?>";
            let urlParams = new URLSearchParams(window.location.search);

            // Clear all report type parameters first
            urlParams.delete('report_type');
            urlParams.delete('fhis_period');
            urlParams.delete('fhis_quarter');
            urlParams.delete('fhis_semi_annual');

            // Set current report type
            urlParams.set('report_type', currentReportType);
            urlParams.set('period', periodType);
            urlParams.set('year', document.getElementById('report-year').value);

            // Set period-specific parameters and remove others
            switch (periodType) {
                case 'quarterly':
                    urlParams.set('quarter', document.getElementById('report-quarter').value);
                    urlParams.delete('month');
                    urlParams.delete('semi_annual');
                    break;

                case 'semi_annual':
                    urlParams.set('semi_annual', document.getElementById('report-semi-annual').value);
                    urlParams.delete('month');
                    urlParams.delete('quarter');
                    break;

                case 'annual':
                    urlParams.delete('month');
                    urlParams.delete('quarter');
                    urlParams.delete('semi_annual');
                    break;

                default: // monthly
                    urlParams.set('month', document.getElementById('report-month').value);
                    urlParams.delete('quarter');
                    urlParams.delete('semi_annual');
                    break;
            }

            // Redirect to the same page with new parameters
            window.location.href = window.location.pathname + '?' + urlParams.toString();
        }


        // Generate report (download/print)
        function generateReport() {
            alert('Generating report for download...');
            window.print();
        }

        // Update report period display
        function updateReportPeriod() {
            const periodType = "<?php echo $periodType; ?>";
            const periodDisplay = document.getElementById('report-period-display');
            const year = document.getElementById('report-year').value;

            switch (periodType) {
                case 'quarterly':
                    const quarterSelect = document.getElementById('report-quarter');
                    const quarterNames = ['January-March', 'April-June', 'July-September', 'October-December'];
                    const quarterYears = ['1st', '2nd', '3rd', '4th'];
                    const quarterName = quarterNames[parseInt(quarterSelect.value) - 1];
                    const quarterYear = quarterYears[parseInt(quarterSelect.value) - 1];
                    periodDisplay.textContent = `${quarterName}/${quarterYear} qtr./${year}`;
                    break;

                case 'semi_annual':
                    const semiAnnualSelect = document.getElementById('report-semi-annual');
                    const semiAnnualNames = ['January-June', 'July-December'];
                    const semiAnnualName = semiAnnualNames[parseInt(semiAnnualSelect.value) - 1];
                    periodDisplay.textContent = `${semiAnnualName}/${year}`;
                    break;

                case 'annual':
                    periodDisplay.textContent = `January-December/${year}`;
                    break;

                default: // monthly
                    const monthSelect = document.getElementById('report-month');
                    const monthNames = [
                        'January', 'February', 'March', 'April', 'May', 'June',
                        'July', 'August', 'September', 'October', 'November', 'December'
                    ];
                    const monthName = monthNames[parseInt(monthSelect.value) - 1];
                    periodDisplay.textContent = `${monthName}/FY${year}`;
                    break;
            }
        }

        // Toggle between main report and FHSIS report
        function showMainReport() {
            document.getElementById('main-report-section').style.display = 'block';
            document.getElementById('fhis-section').style.display = 'none';

            // Update button styles
            document.getElementById('main-report-btn').classList.remove('bg-white', 'text-gray-900', 'border-gray-200');
            document.getElementById('main-report-btn').classList.add('bg-blue-600', 'text-white', 'border-blue-600');
            document.getElementById('fhis-report-btn').classList.remove('bg-blue-600', 'text-white', 'border-blue-600');
            document.getElementById('fhis-report-btn').classList.add('bg-white', 'text-gray-900', 'border-gray-200');

            // Show/hide navigation menus
            document.querySelector('.main-report-nav').style.display = 'block';
            document.querySelector('.fhis-report-nav').style.display = 'none';
        }

        function showFhisReport() {
            document.getElementById('main-report-section').style.display = 'none';
            document.getElementById('fhis-section').style.display = 'block';

            // Update button styles
            document.getElementById('fhis-report-btn').classList.remove('bg-white', 'text-gray-900', 'border-gray-200');
            document.getElementById('fhis-report-btn').classList.add('bg-blue-600', 'text-white', 'border-blue-600');
            document.getElementById('main-report-btn').classList.remove('bg-blue-600', 'text-white', 'border-blue-600');
            document.getElementById('main-report-btn').classList.add('bg-white', 'text-gray-900', 'border-gray-200');

            // Show/hide navigation menus
            document.querySelector('.fhis-report-nav').style.display = 'block';
            document.querySelector('.main-report-nav').style.display = 'none';
        }


        // Initialize - Enhanced to handle URL parameters properly
        document.addEventListener('DOMContentLoaded', function() {
            updateReportPeriod();

            // Add event listeners based on current period type
            const periodType = "<?php echo $periodType; ?>";
            switch (periodType) {
                case 'quarterly':
                    document.getElementById('report-quarter').addEventListener('change', updateReportPeriod);
                    break;
                case 'semi_annual':
                    document.getElementById('report-semi-annual').addEventListener('change', updateReportPeriod);
                    break;
                default: // monthly and annual don't have change events for their main controls
                    if (document.getElementById('report-month')) {
                        document.getElementById('report-month').addEventListener('change', updateReportPeriod);
                    }
                    break;
            }

            document.getElementById('report-year').addEventListener('input', updateReportPeriod);

            // Set initial state based on URL parameters
            const urlParams = new URLSearchParams(window.location.search);
            const reportTypeParam = urlParams.get('report_type');

            if (reportTypeParam === 'fhis') {
                showFhisReport();
            } else {
                showMainReport();
            }
        });

        // Inactivity timer
        let inactivityTime = 1800000; // 10 minutes in ms
        let logoutTimer;

        function resetTimer() {
            clearTimeout(logoutTimer);
            logoutTimer = setTimeout(() => {
                alert("You've been logged out due to 30 minutes of inactivity.");
                window.location.href = "/dentalemr_system/php/login/logout.php?uid=<?php echo $loggedUser['id']; ?>";
            }, inactivityTime);
        }

        ["click", "mousemove", "keypress", "scroll", "touchstart"].forEach(evt => {
            document.addEventListener(evt, resetTimer, false);
        });

        resetTimer();
    </script>
    <!-- Load offline storage -->
    <script src="/dentalemr_system/js/offline-storage.js"></script>

    <script>
        // ========== OFFLINE SUPPORT FOR REPORTS - START ==========

        function setupReportsOffline() {
            const statusElement = document.getElementById('connectionStatus');
            if (!statusElement) {
                const newStatus = document.createElement('div');
                newStatus.id = 'connectionStatus';
                newStatus.className = 'hidden fixed top-4 right-4 z-50';
                document.body.appendChild(newStatus);
            }

            function updateStatus() {
                const indicator = document.getElementById('connectionStatus');
                if (!navigator.onLine) {
                    indicator.innerHTML = `
        <div class="bg-yellow-500 text-white px-4 py-2 rounded-lg shadow-lg flex items-center">
          <i class="fas fa-wifi-slash mr-2"></i>
          <span>Offline Mode - Limited report functionality</span>
        </div>
      `;
                    indicator.classList.remove('hidden');
                } else {
                    indicator.classList.add('hidden');
                }
            }

            window.addEventListener('online', updateStatus);
            window.addEventListener('offline', updateStatus);
            updateStatus();
        }

        document.addEventListener('DOMContentLoaded', function() {
            setupReportsOffline();

            if ('serviceWorker' in navigator) {
                navigator.serviceWorker.register('/dentalemr_system/sw.js')
                    .then(function(registration) {
                        console.log('SW registered for reports');
                    })
                    .catch(function(error) {
                        console.log('SW registration failed:', error);
                    });
            }
        });

        // ========== OFFLINE SUPPORT FOR REPORTS - END ==========
    </script>
</body>

</html>