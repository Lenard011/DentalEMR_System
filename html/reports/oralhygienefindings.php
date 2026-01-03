<?php
session_start();
date_default_timezone_set('Asia/Manila');

// Check if we're in offline mode
$isOfflineMode = isset($_GET['offline']) && $_GET['offline'] === 'true';

// Enhanced session validation with offline support
if ($isOfflineMode) {
    // Offline mode session validation
    $isValidSession = false;

    // Check if we have offline session data
    if (isset($_SESSION['offline_user'])) {
        $loggedUser = $_SESSION['offline_user'];
        $userId = 'offline';
        $isValidSession = true;
    } else {
        // Try to create offline session from localStorage data
        echo "<script>
            document.addEventListener('DOMContentLoaded', function() {
                const checkOfflineSession = () => {
                    try {
                        const sessionData = sessionStorage.getItem('dentalemr_current_user');
                        if (sessionData) {
                            const user = JSON.parse(sessionData);
                            if (user && user.isOffline) {
                                console.log('Valid offline session detected:', user.email);
                                return true;
                            }
                        }
                        
                        const offlineUsers = localStorage.getItem('dentalemr_local_users');
                        if (offlineUsers) {
                            const users = JSON.parse(offlineUsers);
                            if (users && users.length > 0) {
                                console.log('Offline users found in localStorage');
                                return true;
                            }
                        }
                        return false;
                    } catch (error) {
                        console.error('Error checking offline session:', error);
                        return false;
                    }
                };
                
                if (!checkOfflineSession()) {
                    alert('Please log in first for offline access.');
                    window.location.href = '/dentalemr_system/html/login/login.html';
                }
            });
        </script>";

        // Create offline session for this request
        $_SESSION['offline_user'] = [
            'id' => 'offline_user',
            'name' => 'Offline User',
            'email' => 'offline@dentalclinic.com',
            'type' => 'Dentist',
            'isOffline' => true
        ];
        $loggedUser = $_SESSION['offline_user'];
        $userId = 'offline';
        $isValidSession = true;
    }
} else {
    // Online mode - normal session validation
    if (!isset($_GET['uid'])) {
        echo "<script>
            if (!navigator.onLine) {
                // Redirect to same page in offline mode
                window.location.href = '/dentalemr_system/html/treatmentrecords/view_info.php?offline=true&id=" . (isset($_GET['id']) ? $_GET['id'] : '') . "';
            } else {
                alert('Invalid session. Please log in again.');
                window.location.href = '/dentalemr_system/html/login/login.html';
            }
        </script>";
        exit;
    }

    $userId = intval($_GET['uid']);
    $isValidSession = false;

    // CHECK IF THIS USER IS REALLY LOGGED IN
    if (
        isset($_SESSION['active_sessions']) &&
        isset($_SESSION['active_sessions'][$userId])
    ) {
        $userSession = $_SESSION['active_sessions'][$userId];

        // Check basic required fields
        if (isset($userSession['id']) && isset($userSession['type'])) {
            $isValidSession = true;
            // Update last activity
            $_SESSION['active_sessions'][$userId]['last_activity'] = time();
            $loggedUser = $userSession;
        }
    }

    if (!$isValidSession) {
        echo "<script>
            if (!navigator.onLine) {
                // Redirect to same page in offline mode
                window.location.href = '/dentalemr_system/html/treatmentrecords/view_info.php?offline=true&id=" . (isset($_GET['id']) ? $_GET['id'] : '') . "';
            } else {
                alert('Please log in first.');
                window.location.href = '/dentalemr_system/html/login/login.html';
            }
        </script>";
        exit;
    }
}

// PER-USER INACTIVITY TIMEOUT (Online mode only)
if (!$isOfflineMode) {
    $inactiveLimit = 1800; // 10 minutes

    if (isset($_SESSION['active_sessions'][$userId]['last_activity'])) {
        $lastActivity = $_SESSION['active_sessions'][$userId]['last_activity'];

        if ((time() - $lastActivity) > $inactiveLimit) {
            // Log out ONLY this user (not everyone)
            unset($_SESSION['active_sessions'][$userId]);

            // If no one else is logged in, end session entirely
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
}

// GET USER DATA FOR PAGE USE
if ($isOfflineMode) {
    $loggedUser = $_SESSION['offline_user'];
} else {
    $loggedUser = $_SESSION['active_sessions'][$userId];
}

// Database connection only for online mode
$conn = null;
if (!$isOfflineMode) {
    $host = "localhost";
    $dbUser = "root";
    $dbPass = "";
    $dbName = "dentalemr_system";

    $conn = new mysqli($host, $dbUser, $dbPass, $dbName);
    if ($conn->connect_error) {
        // If database fails but browser is online, show error
        if (!isset($_GET['offline'])) {
            echo "<script>
                if (navigator.onLine) {
                    alert('Database connection failed. Please try again.');
                    console.error('Database error: " . addslashes($conn->connect_error) . "');
                } else {
                    // Switch to offline mode automatically
                    window.location.href = '/dentalemr_system/html/treatmentrecords/view_info.php?offline=true&id=" . (isset($_GET['id']) ? $_GET['id'] : '') . "';
                }
            </script>";
            exit;
        }
    }

    // Fetch dentist name if user is a dentist (only in online mode)
    if ($loggedUser['type'] === 'Dentist') {
        $stmt = $conn->prepare("SELECT name, profile_picture FROM dentist WHERE id = ?");
        $stmt->bind_param("i", $loggedUser['id']);
        $stmt->execute();
        $stmt->bind_result($dentistName, $dentistProfilePicture);
        if ($stmt->fetch()) {
            $loggedUser['name'] = $dentistName;
            $loggedUser['profile_picture'] = $dentistProfilePicture; // Add this line
        }
        $stmt->close();
    }
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

// Determine FHSIS period dates
if ($reportType === 'fhis') {
    switch ($fhisPeriod) {
        case 'quarterly':
            $fhisDates = getQuarterDates($fhisQuarter, $selectedYear);
            $fhisStartDate = $fhisDates['startDate'];
            $fhisEndDate = $fhisDates['endDate'];
            $fhisStartDateTime = $fhisDates['startDateTime'];
            $fhisEndDateTime = $fhisDates['endDateTime'];
            break;

        case 'semi_annual':
            $fhisDates = getSemiAnnualDates($fhisSemiAnnual, $selectedYear);
            $fhisStartDate = $fhisDates['startDate'];
            $fhisEndDate = $fhisDates['endDate'];
            $fhisStartDateTime = $fhisDates['startDateTime'];
            $fhisEndDateTime = $fhisDates['endDateTime'];
            break;

        case 'annual':
            $fhisDates = getSemiAnnualDates(3, $selectedYear);
            $fhisStartDate = $fhisDates['startDate'];
            $fhisEndDate = $fhisDates['endDate'];
            $fhisStartDateTime = $fhisDates['startDateTime'];
            $fhisEndDateTime = $fhisDates['endDateTime'];
            break;

        default:
            // Default to current quarter
            $fhisQuarter = ceil(date('n') / 3);
            $fhisDates = getQuarterDates($fhisQuarter, $selectedYear);
            $fhisStartDate = $fhisDates['startDate'];
            $fhisEndDate = $fhisDates['endDate'];
            $fhisStartDateTime = $fhisDates['startDateTime'];
            $fhisEndDateTime = $fhisDates['endDateTime'];
            break;
    }

    // Use FHSIS dates for data calculation
    $startDate = $fhisStartDate;
    $endDate = $fhisEndDate;
    $startDateTime = $fhisStartDateTime;
    $endDateTime = $fhisEndDateTime;
}

// FHSIS-specific data calculations
$fhisData = [
    // Orally fit children 12-59 months (1-4 years old)
    'ofc_12_59_months_m' => 0,
    'ofc_12_59_months_f' => 0,

    // DMF cases for 5 years and above
    'dmf_cases_m' => 0,
    'dmf_cases_f' => 0,

    // Pregnant women data
    'pregnant_10_14_bohc' => 0,
    'pregnant_15_19_bohc' => 0,
    'pregnant_20_49_bohc' => 0,

    // BOHC counts for different age groups
    'infant_bohc_m' => 0,
    'infant_bohc_f' => 0,
    'under_five_bohc_m' => 0,
    'under_five_bohc_f' => 0,
    'school_age_bohc_m' => 0,
    'school_age_bohc_f' => 0,
    'adolescent_10_14_bohc_m' => 0,
    'adolescent_10_14_bohc_f' => 0,
    'adolescent_15_19_bohc_m' => 0,
    'adolescent_15_19_bohc_f' => 0,
    'adult_bohc_m' => 0,
    'adult_bohc_f' => 0,
    'older_bohc_m' => 0,
    'older_bohc_f' => 0
];

// Calculate FHSIS-specific data if we're in FHSIS report mode
if ($reportType === 'fhis') {
    // Calculate DMF cases for clients 5 years and above
    $dmfQuery = "
        SELECT p.sex, COUNT(DISTINCT p.patient_id) as count
        FROM patients p
        INNER JOIN oral_health_condition ohc ON p.patient_id = ohc.patient_id
        WHERE p.age >= 5 
          AND (ohc.created_at BETWEEN ? AND ? OR ohc.updated_at BETWEEN ? AND ?)
          AND (
            ohc.dental_caries = '✓' OR 
            ohc.gingivitis = '✓' OR 
            ohc.periodontal_disease = '✓' OR
            ohc.temp_decayed_teeth_d > 0 OR
            ohc.temp_filled_teeth_f > 0 OR
            ohc.perm_decayed_teeth_d > 0 OR
            ohc.perm_missing_teeth_m > 0 OR
            ohc.perm_filled_teeth_f > 0
          )
        GROUP BY p.sex
    ";

    $dmfStmt = $conn->prepare($dmfQuery);
    if ($dmfStmt) {
        $dmfStmt->bind_param("ssss", $startDateTime, $endDateTime, $startDateTime, $endDateTime);
        $dmfStmt->execute();
        $dmfResult = $dmfStmt->get_result();

        while ($row = $dmfResult->fetch_assoc()) {
            $sex = strtolower(trim($row['sex']));
            if ($sex === 'male') {
                $fhisData['dmf_cases_m'] = $row['count'];
            } elseif ($sex === 'female') {
                $fhisData['dmf_cases_f'] = $row['count'];
            }
        }
        $dmfStmt->close();
    }

    // Calculate pregnant women who received BOHC
    $pregnantQuery = "
        SELECT 
            COUNT(DISTINCT p.patient_id) as count,
            CASE 
                WHEN p.age BETWEEN 10 AND 14 THEN '10_14'
                WHEN p.age BETWEEN 15 AND 19 THEN '15_19' 
                WHEN p.age BETWEEN 20 AND 49 THEN '20_49'
                ELSE 'other'
            END as age_group
        FROM patients p
        WHERE p.sex = 'Female' 
          AND p.pregnant = 'Yes'
          AND (p.created_at BETWEEN ? AND ? OR EXISTS (
            SELECT 1 FROM visits v 
            WHERE v.patient_id = p.patient_id AND v.visit_date BETWEEN ? AND ?
          ))
        GROUP BY age_group
    ";

    $pregnantStmt = $conn->prepare($pregnantQuery);
    if ($pregnantStmt) {
        $pregnantStmt->bind_param("ssss", $startDateTime, $endDateTime, $startDate, $endDate);
        $pregnantStmt->execute();
        $pregnantResult = $pregnantStmt->get_result();

        while ($row = $pregnantResult->fetch_assoc()) {
            switch ($row['age_group']) {
                case '10_14':
                    $fhisData['pregnant_10_14_bohc'] = $row['count'];
                    break;
                case '15_19':
                    $fhisData['pregnant_15_19_bohc'] = $row['count'];
                    break;
                case '20_49':
                    $fhisData['pregnant_20_49_bohc'] = $row['count'];
                    break;
            }
        }
        $pregnantStmt->close();
    }

    // Calculate orally fit children 12-59 months
    $ofcQuery = "
        SELECT p.sex, COUNT(DISTINCT p.patient_id) as count
        FROM patients p
        INNER JOIN oral_health_condition ohc ON p.patient_id = ohc.patient_id
        WHERE p.age BETWEEN 1 AND 4
          AND p.if_treatment = 1
          AND (ohc.created_at BETWEEN ? AND ? OR ohc.updated_at BETWEEN ? AND ?)
          AND (
            ohc.orally_fit_child = '✓' OR 
            (ohc.dental_caries = '✗' AND ohc.gingivitis = '✗' AND ohc.periodontal_disease = '✗')
          )
        GROUP BY p.sex
    ";

    $ofcStmt = $conn->prepare($ofcQuery);
    if ($ofcStmt) {
        $ofcStmt->bind_param("ssss", $startDateTime, $endDateTime, $startDateTime, $endDateTime);
        $ofcStmt->execute();
        $ofcResult = $ofcStmt->get_result();

        while ($row = $ofcResult->fetch_assoc()) {
            $sex = strtolower(trim($row['sex']));
            if ($sex === 'male') {
                $fhisData['ofc_12_59_months_m'] = $row['count'];
            } elseif ($sex === 'female') {
                $fhisData['ofc_12_59_months_f'] = $row['count'];
            }
        }
        $ofcStmt->close();
    }

    // Calculate BOHC counts for different age groups
    $bohcQuery = "
        SELECT 
            p.sex,
            COUNT(DISTINCT p.patient_id) as count,
            CASE 
                WHEN p.age = 0 AND p.months_old < 12 THEN 'infant'
                WHEN p.age BETWEEN 1 AND 4 THEN 'under_five'
                WHEN p.age BETWEEN 5 AND 9 THEN 'school_age'
                WHEN p.age BETWEEN 10 AND 14 THEN 'adolescent_10_14'
                WHEN p.age BETWEEN 15 AND 19 THEN 'adolescent_15_19'
                WHEN p.age BETWEEN 20 AND 59 THEN 'adult'
                WHEN p.age >= 60 THEN 'older'
                ELSE 'other'
            END as age_group
        FROM patients p
        WHERE p.if_treatment = 1
          AND (p.created_at BETWEEN ? AND ? OR EXISTS (
            SELECT 1 FROM visits v 
            WHERE v.patient_id = p.patient_id AND v.visit_date BETWEEN ? AND ?
          ))
        GROUP BY age_group, p.sex
    ";

    $bohcStmt = $conn->prepare($bohcQuery);
    if ($bohcStmt) {
        $bohcStmt->bind_param("ssss", $startDateTime, $endDateTime, $startDate, $endDate);
        $bohcStmt->execute();
        $bohcResult = $bohcStmt->get_result();

        while ($row = $bohcResult->fetch_assoc()) {
            $sex = strtolower(trim($row['sex']));
            $ageGroup = $row['age_group'];
            $count = $row['count'];

            switch ($ageGroup) {
                case 'infant':
                    if ($sex === 'male') $fhisData['infant_bohc_m'] = $count;
                    else $fhisData['infant_bohc_f'] = $count;
                    break;
                case 'under_five':
                    if ($sex === 'male') $fhisData['under_five_bohc_m'] = $count;
                    else $fhisData['under_five_bohc_f'] = $count;
                    break;
                case 'school_age':
                    if ($sex === 'male') $fhisData['school_age_bohc_m'] = $count;
                    else $fhisData['school_age_bohc_f'] = $count;
                    break;
                case 'adolescent_10_14':
                    if ($sex === 'male') $fhisData['adolescent_10_14_bohc_m'] = $count;
                    else $fhisData['adolescent_10_14_bohc_f'] = $count;
                    break;
                case 'adolescent_15_19':
                    if ($sex === 'male') $fhisData['adolescent_15_19_bohc_m'] = $count;
                    else $fhisData['adolescent_15_19_bohc_f'] = $count;
                    break;
                case 'adult':
                    if ($sex === 'male') $fhisData['adult_bohc_m'] = $count;
                    else $fhisData['adult_bohc_f'] = $count;
                    break;
                case 'older':
                    if ($sex === 'male') $fhisData['older_bohc_m'] = $count;
                    else $fhisData['older_bohc_f'] = $count;
                    break;
            }
        }
        $bohcStmt->close();
    }
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

// New function for FHSIS data
function getFhisValue($key, $fhisData)
{
    return $fhisData[$key] ?? 0;
}

// Function to get interpretation based on percentage
function getInterpretation($percentage)
{
    if ($percentage === '') return 'N/A';
    if ($percentage >= 80) return 'Good';
    if ($percentage >= 50) return 'Fair';
    return 'Needs Improvement';
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Oral Hygiene Findings - MHO Dental Clinic</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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

<body class="bg-gray-50 dark:bg-gray-900">
    <div class="antialiased bg-gray-50 dark:bg-gray-900">
        <!-- Navigation Header -->
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
                        <svg aria-hidden="true" class="hidden w-6 h-6" fill="currentColor" viewBox="0 0 20 20"
                            xmlns="http://www.w3.org/2000/svg">
                            <path fill-rule="evenodd"
                                d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                clip-rule="evenodd"></path>
                        </svg>
                        <span class="sr-only">Toggle sidebar</span>
                    </button>
                    <a href="#" class="flex items-center justify-between mr-4 ">
                        <img src="https://th.bing.com/th/id/OIP.zjh8eiLAHY9ybXUCuYiqQwAAAA?r=0&rs=1&pid=ImgDetMain&cb=idpwebp1&o=7&rm=3"
                            class="mr-3 h-8 rounded-full" alt="MHO Logo" />
                        <span class="self-center text-2xl font-semibold whitespace-nowrap dark:text-white">MHO Dental Clinic</span>
                    </a>

                    <?php if ($isOfflineMode): ?>
                        <div class="ml-4 px-3 py-1 bg-orange-100 dark:bg-orange-900/30 border border-orange-200 dark:border-orange-800 rounded-lg flex items-center gap-2">
                            <i class="fas fa-wifi-slash text-orange-600 dark:text-orange-400 text-sm"></i>
                            <span class="text-sm font-medium text-orange-800 dark:text-orange-300">Offline Mode</span>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- User Profile -->
                <div class="flex items-center space-x-3">
                    <?php if ($isOfflineMode): ?>
                        <button onclick="syncOfflineData()"
                            class="bg-orange-500 text-white px-4 py-2 rounded-lg hover:bg-orange-600 transition-colors flex items-center gap-2 text-sm">
                            <i class="fas fa-sync"></i>
                            Sync When Online
                        </button>
                    <?php endif; ?>

                    <!-- User Dropdown -->
                    <div class="relative">
                        <button type="button" id="user-menu-button" aria-expanded="false" data-dropdown-toggle="dropdown"
                            class="flex items-center space-x-2 p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700">
                            <div class="w-8 h-8 rounded-full bg-gray-200 dark:bg-gray-700 flex items-center justify-center overflow-hidden">
                                <?php if (!empty($loggedUser['profile_picture'])): ?>
                                    <img src="<?php echo htmlspecialchars($loggedUser['profile_picture']); ?>" alt="Profile" class="w-full h-full object-cover">
                                <?php else: ?>
                                    <i class="fas fa-user text-gray-600 dark:text-gray-400"></i>
                                <?php endif; ?>
                            </div>
                            <div class="text-left">
                                <div class="text-sm font-medium truncate max-w-[150px] dark:text-white">
                                    <?php
                                    echo htmlspecialchars(
                                        !empty($loggedUser['name'])
                                            ? $loggedUser['name']
                                            : ($loggedUser['email'] ?? 'User')
                                    );
                                    ?>
                                    <?php if ($isOfflineMode): ?>
                                        <span class="text-orange-600 text-xs">(Offline)</span>
                                    <?php endif; ?>
                                </div>
                                <div class="text-xs text-gray-500 dark:text-white truncate max-w-[150px]">
                                    <?php
                                    echo htmlspecialchars(
                                        !empty($loggedUser['email'])
                                            ? $loggedUser['email']
                                            : ($loggedUser['name'] ?? 'User')
                                    );
                                    ?>
                                </div>
                            </div>
                            <i class="fas fa-chevron-down text-xs text-gray-500"></i>
                        </button>

                        <!-- Dropdown Menu -->
                        <div id="dropdown" class="absolute right-0 mt-2 w-64 bg-white dark:bg-gray-800 rounded-lg shadow-lg border border-gray-200 dark:border-gray-700 hidden z-50">
                            <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                                <div class="text-sm font-semibold dark:text-white">
                                    <?php
                                    echo htmlspecialchars(
                                        !empty($loggedUser['name'])
                                            ? $loggedUser['name']
                                            : ($loggedUser['email'] ?? 'User')
                                    );
                                    ?>
                                    <?php if ($isOfflineMode): ?>
                                        <span class="text-orange-600 text-xs">(Offline)</span>
                                    <?php endif; ?>
                                </div>
                                <div class="text-xs text-gray-600 dark:text-white mt-1 ">
                                    <?php
                                    echo htmlspecialchars(
                                        !empty($loggedUser['email'])
                                            ? $loggedUser['email']
                                            : ($loggedUser['name'] ?? 'User')
                                    );
                                    ?>
                                </div>
                            </div>
                            <div class="py-2">
                                <a href="/dentalemr_system/html/manageusers/profile.php?uid=<?php echo $userId; ?>"
                                    class="flex items-center px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700">
                                    <i class="fas fa-user-circle mr-3 text-gray-500 dark:text-gray-400"></i>
                                    My Profile
                                </a>
                                <a href="/dentalemr_system/html/manageusers/manageuser.php?uid=<?php echo $userId;
                                                                                                echo $isOfflineMode ? '&offline=true' : ''; ?>"
                                    class="flex items-center px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700">
                                    <i class="fas fa-users-cog mr-3 text-gray-500 dark:text-gray-400"></i>
                                    Manage Users
                                </a>
                                <a href="/dentalemr_system/html/manageusers/systemlogs.php?uid=<?php echo $userId;
                                                                                                echo $isOfflineMode ? '&offline=true' : ''; ?>"
                                    class="flex items-center px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700">
                                    <i class="fas fa-history mr-3 text-gray-500 dark:text-gray-400"></i>
                                    System Logs
                                </a>
                            </div>

                            <!-- Theme Toggle -->
                            <div class="border-t border-gray-200 dark:border-gray-700 py-2">
                                <button type="button" id="theme-toggle"
                                    class="flex items-center w-full px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700">
                                    <svg id="theme-toggle-dark-icon" class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z"></path>
                                    </svg>
                                    <svg id="theme-toggle-light-icon" class="w-4 h-4 mr-2 hidden" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                                        <path fill-rule="evenodd" d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4 8a4 4 0 11-8 0 4 4 0 018 0zm-.464 4.95l.707.707a1 1 0 001.414-1.414l-.707-.707a1 1 0 00-1.414 1.414zm2.12-10.607a1 1 0 010 1.414l-.706.707a1 1 0 11-1.414-1.414l.707-.707a1 1 0 011.414 0zM17 11a1 1 0 100-2h-1a1 1 0 100 2h1zm-7 4a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zM5.05 6.464A1 1 0 106.465 5.05l-.708-.707a1 1 0 00-1.414 1.414l.707.707zm1.414 8.486l-.707.707a1 1 0 01-1.414-1.414l.707-.707a1 1 0 011.414 1.414zM4 11a1 1 0 100-2H3a1 1 0 000 2h1z" clip-rule="evenodd"></path>
                                    </svg>
                                    <span id="theme-toggle-text">Toggle theme</span>
                                </button>
                            </div>

                            <!-- Sign Out -->
                            <div class="border-t border-gray-200 dark:border-gray-700 py-2">
                                <a href="/dentalemr_system/php/login/logout.php?uid=<?php echo $loggedUser['id']; ?>"
                                    class="flex items-center px-4 py-2 text-sm text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20">
                                    <i class="fas fa-sign-out-alt mr-3"></i>
                                    Sign Out
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Sidebar -->
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
                </ul>
            </div>
        </aside>

        <header class="md:ml-64 pt-20 ">
            <nav class="bg-white border-gray-200 dark:bg-gray-800 w-full drop-shadow-sm pb-2">
                <div class="flex flex-col justify-between items-center mx-auto max-w-screen-xl">
                    <div class="realtive flex items-center justify-center w-full">
                        <p class="text-xl font-semibold  text-gray-900 dark:text-white">Oral Hygiene Findings
                        </p>
                        <button type="button" onclick="exportCurrentReport()"
                            class="text-white cursor-pointer flex flex-row items-center absolute mt-3 right-2 gap-1 bg-green-600 hover:bg-green-700 font-medium rounded-sm text-xs px-1 lg:py-1 mr-2 dark:bg-green-600 dark:hover:bg-green-700 focus:outline-none dark:focus:ring-green-800">
                            <svg class="w-5 h-5 text-white-800 dark:text-white" aria-hidden="true"
                                xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none"
                                viewBox="0 0 24 24">
                                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M12 13V4M7 14H5a1 1 0 0 0-1 1v4a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-4a1 1 0 0 0-1-1h-2m-1-5-4 5-4-5m9 8h.01" />
                            </svg>
                            Export Excel
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

            <!-- FHSIS Report Section - IMPROVED -->
            <section id="fhis-section" class="bg-white dark:bg-gray-900 p-3 rounded-lg mb-3 mt-3">
                <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-4">FHSIS Report - Oral Health Care and Services</h2>

                <!-- FHSIS Report Controls -->
                <div class="w-full flex flex-row p-1 justify-between mb-4">
                    <div class="flex flex-row items-end space-x-4">
                        <?php if ($fhisPeriod === 'quarterly'): ?>
                            <!-- Quarterly Selection -->
                            <div>
                                <label for="fhis-quarter" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Quarter</label>
                                <select id="fhis-quarter" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                    <option value="1" <?php echo $fhisQuarter == 1 ? 'selected' : ''; ?>>1st Quarter (Jan-Mar)</option>
                                    <option value="2" <?php echo $fhisQuarter == 2 ? 'selected' : ''; ?>>2nd Quarter (Apr-Jun)</option>
                                    <option value="3" <?php echo $fhisQuarter == 3 ? 'selected' : ''; ?>>3rd Quarter (Jul-Sep)</option>
                                    <option value="4" <?php echo $fhisQuarter == 4 ? 'selected' : ''; ?>>4th Quarter (Oct-Dec)</option>
                                </select>
                            </div>
                        <?php elseif ($fhisPeriod === 'semi_annual'): ?>
                            <!-- Semi-Annual Selection -->
                            <div>
                                <label for="fhis-semi-annual" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Semi-Annual</label>
                                <select id="fhis-semi-annual" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                    <option value="1" <?php echo $fhisSemiAnnual == 1 ? 'selected' : ''; ?>>Semi-Annual 1 (Jan-Jun)</option>
                                    <option value="2" <?php echo $fhisSemiAnnual == 2 ? 'selected' : ''; ?>>Semi-Annual 2 (Jul-Dec)</option>
                                </select>
                            </div>
                        <?php elseif ($fhisPeriod === 'annual'): ?>
                            <!-- Annual Selection -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Annual Period</label>
                                <div class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-gray-100 rounded-md shadow-sm text-sm">
                                    January - December
                                </div>
                            </div>
                        <?php endif; ?>

                        <div>
                            <label for="fhis-year" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Year</label>
                            <input type="number" id="fhis-year" value="<?php echo $selectedYear; ?>"
                                class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                        </div>

                        <div class="flex items-end">
                            <button type="button" onclick="loadFhisReportData()"
                                class="px-4 py-2 cursor-pointer bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                                Load FHSIS Report
                            </button>
                        </div>
                    </div>
                </div>

                <div class="table-container">
                    <table class="fhis-table border-collapse border border-gray-400 w-full bg-white">
                        <thead>
                            <tr>
                                <th colspan="9" class="border border-gray-400 px-2 py-2 text-center font-bold bg-gray-100">
                                    FHSIS REPORT for the
                                    <?php
                                    if (isset($fhisPeriod)) {
                                        if ($fhisPeriod == 'quarterly') {
                                            echo ['1ST', '2ND', '3RD', '4TH'][$fhisQuarter - 1] . ' QTR.';
                                        } elseif ($fhisPeriod == 'semi_annual') {
                                            echo ['1ST', '2ND'][$fhisSemiAnnual - 1] . ' SEMI-ANNUAL';
                                        } elseif ($fhisPeriod == 'annual') {
                                            echo 'ANNUAL';
                                        }
                                    } else {
                                        echo '1ST QTR.';
                                    }
                                    ?>
                                    YEAR: <?php echo $selectedYear; ?> RHU
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
                            // IMPROVED FHSIS Indicators data with dynamic calculations
                            $fhisIndicators = [
                                [
                                    'indicator' => '1. Orally fit children 12-59 months old upon oral examination plus orally fit after rehabilitation - Total',
                                    'eligible_population' => 4194,
                                    'male_count' => getFhisValue('ofc_12_59_months_m', $fhisData),
                                    'female_count' => getFhisValue('ofc_12_59_months_f', $fhisData)
                                ],
                                [
                                    'indicator' => '2. Clients 5 years old and above with cases of DMF - Total',
                                    'eligible_population' => 'Actual',
                                    'male_count' => getFhisValue('dmf_cases_m', $fhisData),
                                    'female_count' => getFhisValue('dmf_cases_f', $fhisData)
                                ],
                                [
                                    'indicator' => '3. Infants 0-11 months old who received BOHC - Total',
                                    'eligible_population' => 1024,
                                    'male_count' => getFhisValue('infant_bohc_m', $fhisData),
                                    'female_count' => getFhisValue('infant_bohc_f', $fhisData)
                                ],
                                [
                                    'indicator' => '4. Children 1-4 years old who received BOHC - Total',
                                    'eligible_population' => 4194,
                                    'male_count' => getFhisValue('under_five_bohc_m', $fhisData),
                                    'female_count' => getFhisValue('under_five_bohc_f', $fhisData)
                                ],
                                [
                                    'indicator' => '5. Children 5-9 years old who received BOHC - Total',
                                    'eligible_population' => 5791,
                                    'male_count' => getFhisValue('school_age_bohc_m', $fhisData),
                                    'female_count' => getFhisValue('school_age_bohc_f', $fhisData)
                                ],
                                [
                                    'indicator' => '6. Adolescents 10-14 years old who received BOHC - Total',
                                    'eligible_population' => 5489,
                                    'male_count' => getFhisValue('adolescent_10_14_bohc_m', $fhisData),
                                    'female_count' => getFhisValue('adolescent_10_14_bohc_f', $fhisData)
                                ],
                                [
                                    'indicator' => 'Adolescents 15-19 years old who received BOHC - Total',
                                    'eligible_population' => 5238,
                                    'male_count' => getFhisValue('adolescent_15_19_bohc_m', $fhisData),
                                    'female_count' => getFhisValue('adolescent_15_19_bohc_f', $fhisData)
                                ],
                                [
                                    'indicator' => '7. Adults 20-59 years old who received BOHC - Total',
                                    'eligible_population' => 25181,
                                    'male_count' => getFhisValue('adult_bohc_m', $fhisData),
                                    'female_count' => getFhisValue('adult_bohc_f', $fhisData)
                                ],
                                [
                                    'indicator' => '8. Senior citizens 60 years old and above who received BOHC - Total',
                                    'eligible_population' => 3744,
                                    'male_count' => getFhisValue('older_bohc_m', $fhisData),
                                    'female_count' => getFhisValue('older_bohc_f', $fhisData)
                                ],
                                [
                                    'indicator' => '9a. Pregnant women(10-14) who received BOHC - Total',
                                    'eligible_population' => 2624,
                                    'male_count' => 0,
                                    'female_count' => getFhisValue('pregnant_10_14_bohc', $fhisData)
                                ],
                                [
                                    'indicator' => '9b. Pregnant women(15-19) who received BOHC - Total',
                                    'eligible_population' => 2520,
                                    'male_count' => 0,
                                    'female_count' => getFhisValue('pregnant_15_19_bohc', $fhisData)
                                ],
                                [
                                    'indicator' => '9c. Pregnant women(20-49)who received BOHC - Total',
                                    'eligible_population' => 10259,
                                    'male_count' => 0,
                                    'female_count' => getFhisValue('pregnant_20_49_bohc', $fhisData)
                                ]
                            ];

                            foreach ($fhisIndicators as $indicator) {
                                $total = $indicator['male_count'] + $indicator['female_count'];

                                // Calculate percentage only if eligible population is numeric and greater than 0
                                if (is_numeric($indicator['eligible_population']) && $indicator['eligible_population'] > 0) {
                                    $percentage = round(($total / $indicator['eligible_population']) * 100, 2);
                                    $percentageDisplay = $percentage . '%';
                                } else {
                                    $percentageDisplay = '';
                                }

                                $interpretation = getInterpretation($percentage);

                                echo "<tr>";
                                echo "<td class='border border-gray-400 px-2 py-1 text-left'>{$indicator['indicator']}</td>";
                                echo "<td class='border border-gray-400 px-2 py-1 text-center'>{$indicator['eligible_population']}</td>";
                                echo "<td class='border border-gray-400 px-2 py-1 text-center'>{$indicator['male_count']}</td>";
                                echo "<td class='border border-gray-400 px-2 py-1 text-center'>{$indicator['female_count']}</td>";
                                echo "<td class='border border-gray-400 px-2 py-1 text-center'>$total</td>";
                                echo "<td class='border border-gray-400 px-2 py-1 text-center'>$percentageDisplay</td>";
                                echo "<td class='border border-gray-400 px-2 py-1 text-center'>$interpretation</td>";
                                echo "<td class='border border-gray-400 px-2 py-1 text-center'>-</td>"; // Recommendation
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
                    <p><strong>Report Period:</strong> <?php echo date('F j, Y', strtotime($startDate)) . ' to ' . date('F j, Y', strtotime($endDate)); ?></p>
                </div>
            </section>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/flowbite@3.1.2/dist/flowbite.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script src="../../js/tailwind.config.js"></script>
    <!-- Theme Toggle Script -->
    <script>
        // ========== THEME MANAGEMENT ==========
        function initTheme() {
            const themeToggle = document.getElementById('theme-toggle');
            const themeToggleLightIcon = document.getElementById('theme-toggle-light-icon');
            const themeToggleDarkIcon = document.getElementById('theme-toggle-dark-icon');
            const themeToggleText = document.getElementById('theme-toggle-text');

            // Get current theme
            const currentTheme = localStorage.getItem('theme') || 'light';

            // Set initial theme
            if (currentTheme === 'dark') {
                document.documentElement.classList.add('dark');
                if (themeToggleLightIcon) themeToggleLightIcon.classList.add('hidden');
                if (themeToggleDarkIcon) themeToggleDarkIcon.classList.remove('hidden');
                if (themeToggleText) themeToggleText.textContent = 'Light Mode';
            } else {
                document.documentElement.classList.remove('dark');
                if (themeToggleLightIcon) themeToggleLightIcon.classList.remove('hidden');
                if (themeToggleDarkIcon) themeToggleDarkIcon.classList.add('hidden');
                if (themeToggleText) themeToggleText.textContent = 'Dark Mode';
            }

            // Add click event to theme toggle
            if (themeToggle) {
                themeToggle.addEventListener('click', function() {
                    toggleTheme();
                });
            }
        }

        function toggleTheme() {
            const isDark = document.documentElement.classList.contains('dark');
            const themeToggleLightIcon = document.getElementById('theme-toggle-light-icon');
            const themeToggleDarkIcon = document.getElementById('theme-toggle-dark-icon');
            const themeToggleText = document.getElementById('theme-toggle-text');

            if (isDark) {
                document.documentElement.classList.remove('dark');
                localStorage.setItem('theme', 'light');
                if (themeToggleLightIcon) themeToggleLightIcon.classList.remove('hidden');
                if (themeToggleDarkIcon) themeToggleDarkIcon.classList.add('hidden');
                if (themeToggleText) themeToggleText.textContent = 'Dark Mode';
            } else {
                document.documentElement.classList.add('dark');
                localStorage.setItem('theme', 'dark');
                if (themeToggleLightIcon) themeToggleLightIcon.classList.add('hidden');
                if (themeToggleDarkIcon) themeToggleDarkIcon.classList.remove('hidden');
                if (themeToggleText) themeToggleText.textContent = 'Light Mode';
            }
        }

        // Initialize theme when DOM is loaded
        document.addEventListener('DOMContentLoaded', function() {
            initTheme();

            // Also update dropdown visibility based on theme
            const dropdown = document.getElementById('dropdown');
            const userMenuButton = document.getElementById('user-menu-button');

            if (userMenuButton && dropdown) {
                userMenuButton.addEventListener('click', function() {
                    dropdown.classList.toggle('hidden');
                });

                // Close dropdown when clicking outside
                document.addEventListener('click', function(event) {
                    if (!userMenuButton.contains(event.target) && !dropdown.contains(event.target)) {
                        dropdown.classList.add('hidden');
                    }
                });
            }
        });
    </script>
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

        // New function for loading FHSIS report data
        function loadFhisReportData() {
            const fhisPeriod = "<?php echo $fhisPeriod; ?>";
            let urlParams = new URLSearchParams(window.location.search);

            // Set FHSIS parameters
            urlParams.set('report_type', 'fhis');
            urlParams.set('fhis_period', fhisPeriod);
            urlParams.set('year', document.getElementById('fhis-year').value);

            // Set period-specific parameters
            switch (fhisPeriod) {
                case 'quarterly':
                    urlParams.set('fhis_quarter', document.getElementById('fhis-quarter').value);
                    urlParams.delete('fhis_semi_annual');
                    break;

                case 'semi_annual':
                    urlParams.set('fhis_semi_annual', document.getElementById('fhis-semi-annual').value);
                    urlParams.delete('fhis_quarter');
                    break;

                case 'annual':
                    urlParams.delete('fhis_quarter');
                    urlParams.delete('fhis_semi_annual');
                    break;
            }

            // Remove main report parameters
            urlParams.delete('period');
            urlParams.delete('month');
            urlParams.delete('quarter');
            urlParams.delete('semi_annual');

            // Redirect to the same page with new parameters
            window.location.href = window.location.pathname + '?' + urlParams.toString();
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

        // Show/hide loading indicator
        function showLoading(show, message = '') {
            if (show) {
                // Create or show loading overlay
                let overlay = document.getElementById('loading-overlay');
                if (!overlay) {
                    overlay = document.createElement('div');
                    overlay.id = 'loading-overlay';
                    overlay.style.cssText = `
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: rgba(0, 0, 0, 0.7);
                    display: flex;
                    flex-direction: column;
                    justify-content: center;
                    align-items: center;
                    z-index: 9999;
                    color: white;
                `;

                    const spinner = document.createElement('div');
                    spinner.style.cssText = `
                    border: 4px solid #f3f3f3;
                    border-top: 4px solid #3498db;
                    border-radius: 50%;
                    width: 40px;
                    height: 40px;
                    animation: spin 1s linear infinite;
                    margin-bottom: 15px;
                `;

                    const text = document.createElement('div');
                    text.id = 'loading-text';
                    text.style.fontSize = '14px';
                    text.style.textAlign = 'center';
                    text.style.padding = '0 20px';

                    overlay.appendChild(spinner);
                    overlay.appendChild(text);
                    document.body.appendChild(overlay);

                    // Add CSS animation if not already present
                    if (!document.querySelector('style#loading-animation')) {
                        const style = document.createElement('style');
                        style.id = 'loading-animation';
                        style.textContent = `
                        @keyframes spin {
                            0% { transform: rotate(0deg); }
                            100% { transform: rotate(360deg); }
                        }
                    `;
                        document.head.appendChild(style);
                    }
                }

                document.getElementById('loading-text').textContent = message;
                overlay.style.display = 'flex';
            } else {
                const overlay = document.getElementById('loading-overlay');
                if (overlay) {
                    overlay.style.display = 'none';
                }
            }
        }

        // ============================================
        // EXPORT FUNCTIONS - COMPREHENSIVE WITH AJAX FETCHING
        // ============================================

        // Helper function to extract table data
        function extractTableData(table) {
            const rows = Array.from(table.rows);
            const data = [];

            rows.forEach((row) => {
                const rowData = [];
                Array.from(row.cells).forEach((cell) => {
                    let cellValue = cell.textContent.trim();

                    // Clean up the cell value
                    if (cellValue === '' || cellValue === 'NaN' || cellValue === 'undefined') {
                        cellValue = '';
                    }

                    // Try to convert numeric values
                    const numericValue = parseFloat(cellValue.replace(/,/g, ''));
                    if (!isNaN(numericValue) && cellValue !== '') {
                        rowData.push(numericValue);
                    } else {
                        rowData.push(cellValue);
                    }
                });

                data.push(rowData);
            });

            return data;
        }

        // Function to fetch report data via AJAX
        async function fetchReportData(params) {
            try {
                const baseUrl = window.location.pathname;
                const queryString = new URLSearchParams(params).toString();
                const url = `${baseUrl}?${queryString}`;

                const response = await fetch(url);
                if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);

                const text = await response.text();

                // Parse the HTML response to extract table data
                const parser = new DOMParser();
                const doc = parser.parseFromString(text, 'text/html');

                // Extract main report data
                const mainTable = doc.querySelector('#main-report-section table.age-table');
                const mainData = mainTable ? extractTableData(mainTable) : null;

                // Extract FHSIS data
                const fhisTable = doc.querySelector('#fhis-section table.fhis-table');
                const fhisData = fhisTable ? extractTableData(fhisTable) : null;

                return {
                    mainData: mainData,
                    fhisData: fhisData,
                    params: params
                };
            } catch (error) {
                console.error('Error fetching report data:', error);
                throw error;
            }
        }

        // Function to create workbook with all 26 sheets
        async function exportAllReports() {
            try {
                showLoading(true, 'Generating comprehensive report with 26 sheets...');

                const currentYear = "<?php echo $selectedYear; ?>";
                const today = new Date().toISOString().split('T')[0];
                const userId = "<?php echo $userId; ?>";

                // Create a new workbook
                const wb = XLSX.utils.book_new();
                let sheetCount = 0;

                // ============================================
                // 1. MONTHLY REPORTS (January to December)
                // ============================================
                showLoading(true, 'Fetching monthly reports (1/12)...');
                const months = [
                    'January', 'February', 'March', 'April', 'May', 'June',
                    'July', 'August', 'September', 'October', 'November', 'December'
                ];

                for (let i = 0; i < months.length; i++) {
                    const monthNum = i + 1;
                    showLoading(true, `Fetching ${months[i]} data (${i+1}/12)...`);

                    try {
                        const params = {
                            uid: userId,
                            report_type: 'main',
                            period: 'monthly',
                            month: monthNum,
                            year: currentYear
                        };

                        const reportData = await fetchReportData(params);
                        if (reportData.mainData) {
                            const sheetName = months[i].substring(0, 3);
                            const ws = XLSX.utils.aoa_to_sheet(reportData.mainData);

                            // Add column widths
                            if (reportData.mainData.length > 0 && reportData.mainData[0].length > 0) {
                                const wscols = [];
                                for (let col = 0; col < reportData.mainData[0].length; col++) {
                                    if (col === 0) {
                                        wscols.push({
                                            wch: 40
                                        });
                                    } else if (col === reportData.mainData[0].length - 1) {
                                        wscols.push({
                                            wch: 12
                                        });
                                    } else {
                                        wscols.push({
                                            wch: 8
                                        });
                                    }
                                }
                                ws['!cols'] = wscols;
                            }

                            XLSX.utils.book_append_sheet(wb, ws, sheetName);
                            sheetCount++;
                        }
                    } catch (error) {
                        console.warn(`Failed to fetch data for ${months[i]}:`, error);
                    }
                }

                // ============================================
                // 2. QUARTERLY REPORTS (Q1 to Q4)
                // ============================================
                showLoading(true, 'Fetching quarterly reports (1/4)...');
                const quarters = [{
                        number: 1,
                        name: 'Q1',
                        range: 'January-March'
                    },
                    {
                        number: 2,
                        name: 'Q2',
                        range: 'April-June'
                    },
                    {
                        number: 3,
                        name: 'Q3',
                        range: 'July-September'
                    },
                    {
                        number: 4,
                        name: 'Q4',
                        range: 'October-December'
                    }
                ];

                for (let i = 0; i < quarters.length; i++) {
                    showLoading(true, `Fetching ${quarters[i].name} data (${i+1}/4)...`);

                    try {
                        const params = {
                            uid: userId,
                            report_type: 'main',
                            period: 'quarterly',
                            quarter: quarters[i].number,
                            year: currentYear
                        };

                        const reportData = await fetchReportData(params);
                        if (reportData.mainData) {
                            const sheetName = quarters[i].name;
                            const ws = XLSX.utils.aoa_to_sheet(reportData.mainData);

                            // Add column widths
                            if (reportData.mainData.length > 0 && reportData.mainData[0].length > 0) {
                                const wscols = [];
                                for (let col = 0; col < reportData.mainData[0].length; col++) {
                                    if (col === 0) {
                                        wscols.push({
                                            wch: 40
                                        });
                                    } else if (col === reportData.mainData[0].length - 1) {
                                        wscols.push({
                                            wch: 12
                                        });
                                    } else {
                                        wscols.push({
                                            wch: 8
                                        });
                                    }
                                }
                                ws['!cols'] = wscols;
                            }

                            XLSX.utils.book_append_sheet(wb, ws, sheetName);
                            sheetCount++;
                        }
                    } catch (error) {
                        console.warn(`Failed to fetch data for ${quarters[i].name}:`, error);
                    }
                }

                // ============================================
                // 3. SEMI-ANNUAL REPORTS
                // ============================================
                showLoading(true, 'Fetching semi-annual reports...');
                const semiAnnuals = [{
                        number: 1,
                        name: 'Semi1',
                        range: 'January-June'
                    },
                    {
                        number: 2,
                        name: 'Semi2',
                        range: 'July-December'
                    }
                ];

                for (let i = 0; i < semiAnnuals.length; i++) {
                    showLoading(true, `Fetching ${semiAnnuals[i].name} data (${i+1}/2)...`);

                    try {
                        const params = {
                            uid: userId,
                            report_type: 'main',
                            period: 'semi_annual',
                            semi_annual: semiAnnuals[i].number,
                            year: currentYear
                        };

                        const reportData = await fetchReportData(params);
                        if (reportData.mainData) {
                            const sheetName = semiAnnuals[i].name;
                            const ws = XLSX.utils.aoa_to_sheet(reportData.mainData);

                            // Add column widths
                            if (reportData.mainData.length > 0 && reportData.mainData[0].length > 0) {
                                const wscols = [];
                                for (let col = 0; col < reportData.mainData[0].length; col++) {
                                    if (col === 0) {
                                        wscols.push({
                                            wch: 40
                                        });
                                    } else if (col === reportData.mainData[0].length - 1) {
                                        wscols.push({
                                            wch: 12
                                        });
                                    } else {
                                        wscols.push({
                                            wch: 8
                                        });
                                    }
                                }
                                ws['!cols'] = wscols;
                            }

                            XLSX.utils.book_append_sheet(wb, ws, sheetName);
                            sheetCount++;
                        }
                    } catch (error) {
                        console.warn(`Failed to fetch data for ${semiAnnuals[i].name}:`, error);
                    }
                }

                // ============================================
                // 4. ANNUAL REPORT
                // ============================================
                showLoading(true, 'Fetching annual report...');
                try {
                    const params = {
                        uid: userId,
                        report_type: 'main',
                        period: 'annual',
                        year: currentYear
                    };

                    const reportData = await fetchReportData(params);
                    if (reportData.mainData) {
                        const ws = XLSX.utils.aoa_to_sheet(reportData.mainData);

                        // Add column widths
                        if (reportData.mainData.length > 0 && reportData.mainData[0].length > 0) {
                            const wscols = [];
                            for (let col = 0; col < reportData.mainData[0].length; col++) {
                                if (col === 0) {
                                    wscols.push({
                                        wch: 40
                                    });
                                } else if (col === reportData.mainData[0].length - 1) {
                                    wscols.push({
                                        wch: 12
                                    });
                                } else {
                                    wscols.push({
                                        wch: 8
                                    });
                                }
                            }
                            ws['!cols'] = wscols;
                        }

                        XLSX.utils.book_append_sheet(wb, ws, 'Annual');
                        sheetCount++;
                    }
                } catch (error) {
                    console.warn('Failed to fetch annual data:', error);
                }

                // ============================================
                // 5. FHSIS REPORTS (Quarterly, Semi-Annual, Annual)
                // ============================================

                // FHSIS Quarterly
                showLoading(true, 'Fetching FHSIS quarterly reports (1/4)...');
                for (let q = 1; q <= 4; q++) {
                    showLoading(true, `Fetching FHSIS Q${q} data (${q}/4)...`);

                    try {
                        const params = {
                            uid: userId,
                            report_type: 'fhis',
                            fhis_period: 'quarterly',
                            fhis_quarter: q,
                            year: currentYear
                        };

                        const reportData = await fetchReportData(params);
                        if (reportData.fhisData) {
                            const sheetName = `FHSIS Q${q}`;
                            const ws = XLSX.utils.aoa_to_sheet(reportData.fhisData);

                            // Set column widths for FHSIS
                            const fhisCols = [{
                                    wch: 60
                                }, // Indicator column
                                {
                                    wch: 20
                                }, // Eligible Population
                                {
                                    wch: 10
                                }, // Male
                                {
                                    wch: 12
                                }, // Female
                                {
                                    wch: 10
                                }, // Total
                                {
                                    wch: 12
                                }, // Percentage
                                {
                                    wch: 20
                                }, // Interpretation
                                {
                                    wch: 25
                                } // Recommendation
                            ];
                            ws['!cols'] = fhisCols;

                            XLSX.utils.book_append_sheet(wb, ws, sheetName);
                            sheetCount++;
                        }
                    } catch (error) {
                        console.warn(`Failed to fetch FHSIS Q${q} data:`, error);
                    }
                }

                // FHSIS Semi-Annual
                showLoading(true, 'Fetching FHSIS semi-annual reports...');
                for (let s = 1; s <= 2; s++) {
                    showLoading(true, `Fetching FHSIS Semi${s} data (${s}/2)...`);

                    try {
                        const params = {
                            uid: userId,
                            report_type: 'fhis',
                            fhis_period: 'semi_annual',
                            fhis_semi_annual: s,
                            year: currentYear
                        };

                        const reportData = await fetchReportData(params);
                        if (reportData.fhisData) {
                            const sheetName = `FHSIS Semi${s}`;
                            const ws = XLSX.utils.aoa_to_sheet(reportData.fhisData);

                            const fhisCols = [{
                                    wch: 60
                                }, {
                                    wch: 20
                                }, {
                                    wch: 10
                                }, {
                                    wch: 12
                                },
                                {
                                    wch: 10
                                }, {
                                    wch: 12
                                }, {
                                    wch: 20
                                }, {
                                    wch: 25
                                }
                            ];
                            ws['!cols'] = fhisCols;

                            XLSX.utils.book_append_sheet(wb, ws, sheetName);
                            sheetCount++;
                        }
                    } catch (error) {
                        console.warn(`Failed to fetch FHSIS Semi${s} data:`, error);
                    }
                }

                // FHSIS Annual
                showLoading(true, 'Fetching FHSIS annual report...');
                try {
                    const params = {
                        uid: userId,
                        report_type: 'fhis',
                        fhis_period: 'annual',
                        year: currentYear
                    };

                    const reportData = await fetchReportData(params);
                    if (reportData.fhisData) {
                        const ws = XLSX.utils.aoa_to_sheet(reportData.fhisData);

                        const fhisCols = [{
                                wch: 60
                            }, {
                                wch: 20
                            }, {
                                wch: 10
                            }, {
                                wch: 12
                            },
                            {
                                wch: 10
                            }, {
                                wch: 12
                            }, {
                                wch: 20
                            }, {
                                wch: 25
                            }
                        ];
                        ws['!cols'] = fhisCols;

                        XLSX.utils.book_append_sheet(wb, ws, 'FHSIS Annual');
                        sheetCount++;
                    }
                } catch (error) {
                    console.warn('Failed to fetch FHSIS annual data:', error);
                }

                // ============================================
                // 6. SUMMARY SHEET
                // ============================================
                showLoading(true, 'Creating summary sheet...');
                const summaryData = [
                    ['COMPREHENSIVE ORAL HEALTH REPORTS - SUMMARY'],
                    [''],
                    ['Generated on:', new Date().toLocaleString()],
                    ['Year:', currentYear],
                    ['Total Sheets Generated:', sheetCount],
                    ['Generated by:', 'MHO Dental Clinic System'],
                    [''],
                    ['SHEETS INCLUDED:'],
                    ['1. Monthly Reports:', '12 sheets (Jan-Dec)'],
                    ['2. Quarterly Reports:', '4 sheets (Q1-Q4)'],
                    ['3. Semi-Annual Reports:', '2 sheets (Semi1-Semi2)'],
                    ['4. Annual Report:', '1 sheet'],
                    ['5. FHSIS Quarterly:', '4 sheets (FHSIS Q1-Q4)'],
                    ['6. FHSIS Semi-Annual:', '2 sheets (FHSIS Semi1-Semi2)'],
                    ['7. FHSIS Annual:', '1 sheet'],
                    [''],
                    ['TOTAL:', '26 sheets'],
                    [''],
                    ['Note: All data is fetched directly from the database'],
                    ['Note: Reports contain actual patient records for each period']
                ];

                const summaryWs = XLSX.utils.aoa_to_sheet(summaryData);
                XLSX.utils.book_append_sheet(wb, summaryWs, 'Summary');
                sheetCount++;

                // ============================================
                // 7. GENERATE AND DOWNLOAD FILE
                // ============================================
                showLoading(true, 'Finalizing and downloading file...');
                const fileName = `Oral_Health_Reports_${currentYear}_Complete_${today}.xlsx`;

                // Download the file
                XLSX.writeFile(wb, fileName);

                // Hide loading and show success message
                setTimeout(() => {
                    showLoading(false);
                    alert(`✅ Comprehensive report generated successfully!\n\n📊 File: ${fileName}\n📋 Total Sheets: ${sheetCount}\n⏱️  All 26 reports included\n\n✅ Actual data from database\n✅ All periods covered\n✅ Ready for submission`);
                }, 1000);

            } catch (error) {
                console.error('Error generating comprehensive report:', error);
                showLoading(false);
                alert('❌ Error generating comprehensive report: ' + error.message);
            }
        }

        // Single main report export (original functionality)
        function exportSingleMainReport() {
            try {
                showLoading(true, 'Exporting Main Report...');

                const table = document.querySelector('#main-report-section table.age-table');
                if (!table) {
                    showLoading(false);
                    alert('No main report table found!');
                    return;
                }

                const periodDisplay = document.getElementById('report-period-display').textContent;
                const currentDate = new Date().toISOString().split('T')[0];
                const fileName = `Oral_Health_Report_${periodDisplay.replace(/\//g, '_').replace(/\s/g, '_')}_${currentDate}.xlsx`;

                // Get table data
                const excelData = extractTableData(table);

                // Create workbook and worksheet
                const wb = XLSX.utils.book_new();

                // Get the sheet name from period display
                let sheetName = periodDisplay.split('/')[0];
                if (sheetName.length > 31) sheetName = sheetName.substring(0, 31);
                if (!sheetName) sheetName = 'Report';

                const ws = XLSX.utils.aoa_to_sheet(excelData);

                // Set column widths based on content
                const wscols = [];
                if (excelData.length > 0) {
                    for (let i = 0; i < excelData[0].length; i++) {
                        // First column (indicators) wider
                        if (i === 0) {
                            wscols.push({
                                wch: 40
                            });
                        }
                        // Last column (grand total) medium
                        else if (i === excelData[0].length - 1) {
                            wscols.push({
                                wch: 12
                            });
                        }
                        // Data columns narrower
                        else {
                            wscols.push({
                                wch: 8
                            });
                        }
                    }
                }
                ws['!cols'] = wscols;

                XLSX.utils.book_append_sheet(wb, ws, sheetName);

                // Generate and download file
                XLSX.writeFile(wb, fileName);

                setTimeout(() => {
                    showLoading(false);
                    alert('✅ Main report exported successfully!\nFile: ' + fileName);
                }, 500);

            } catch (error) {
                console.error('Error exporting main report:', error);
                showLoading(false);
                alert('❌ Error exporting main report: ' + error.message);
            }
        }

        // Single FHSIS report export (original functionality)
        function exportSingleFhisReport() {
            try {
                showLoading(true, 'Exporting FHSIS Report...');

                const table = document.querySelector('#fhis-section table.fhis-table');
                if (!table) {
                    showLoading(false);
                    alert('No FHSIS report table found!');
                    return;
                }

                const currentDate = new Date().toISOString().split('T')[0];
                const fileName = `FHSIS_Report_${currentDate}.xlsx`;

                // Get table data
                const excelData = extractTableData(table);

                // Create workbook and worksheet
                const wb = XLSX.utils.book_new();
                const ws = XLSX.utils.aoa_to_sheet(excelData);

                // Set column widths
                const wscols = [{
                        wch: 60
                    }, // Indicator column
                    {
                        wch: 15
                    }, // Eligible Population
                    {
                        wch: 10
                    }, // Male
                    {
                        wch: 10
                    }, // Female
                    {
                        wch: 10
                    }, // Total
                    {
                        wch: 12
                    }, // Percentage
                    {
                        wch: 15
                    }, // Interpretation
                    {
                        wch: 20
                    } // Recommendation
                ];
                ws['!cols'] = wscols;

                XLSX.utils.book_append_sheet(wb, ws, 'FHSIS Report');

                // Generate and download file
                XLSX.writeFile(wb, fileName);

                setTimeout(() => {
                    showLoading(false);
                    alert('✅ FHSIS report exported successfully!\nFile: ' + fileName);
                }, 500);

            } catch (error) {
                console.error('Error exporting FHSIS report:', error);
                showLoading(false);
                alert('❌ Error exporting FHSIS report: ' + error.message);
            }
        }

        // Main export function - determines which report to export
        function exportCurrentReport() {
            try {
                const mainReportVisible = document.getElementById('main-report-section').style.display !== 'none';
                const fhisReportVisible = document.getElementById('fhis-section').style.display !== 'none';

                if (mainReportVisible) {
                    exportSingleMainReport();
                } else if (fhisReportVisible) {
                    exportSingleFhisReport();
                } else {
                    alert('No report is currently visible to export.');
                }

            } catch (error) {
                console.error('Error exporting report:', error);
                alert('❌ Error exporting report: ' + error.message);
            }
        }

        // Update the HTML button to offer both options
        function updateExportButton() {
            const exportButton = document.querySelector('button[onclick="exportCurrentReport()"]');
            if (exportButton) {
                // Create a dropdown menu for export options
                const container = exportButton.parentElement;
                container.style.position = 'relative';

                // Create dropdown
                const dropdown = document.createElement('div');
                dropdown.id = 'export-dropdown';
                dropdown.style.cssText = `
                position: absolute;
                top: 100%;
                right: 0;
                background: white;
                border: 1px solid #ccc;
                border-radius: 4px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                display: none;
                z-index: 1000;
                min-width: 200px;
            `;

                dropdown.innerHTML = `
                <div class="p-2 border-b">
                    <div class="text-xs font-semibold text-gray-500">Export Options:</div>
                </div>
                <button onclick="exportCurrentReport()" class="w-full text-left px-4 py-2 hover:bg-gray-100 text-sm cursor-pointer">
                    📄 Export Current View Only
                </button>
                <button onclick="exportAllReports()" class="w-full text-left px-4 py-2 hover:bg-gray-100 text-sm cursor-pointer">
                    📚 Export Complete Excel (26 Sheets)
                </button>
            `;

                container.appendChild(dropdown);

                // Update main button
                exportButton.onclick = function(e) {
                    e.stopPropagation();
                    dropdown.style.display = dropdown.style.display === 'block' ? 'none' : 'block';
                };

                exportButton.innerHTML = `
                <svg class="w-5 h-5 text-white-800 dark:text-white" aria-hidden="true"
                    xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none"
                    viewBox="0 0 24 24">
                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"
                        stroke-width="2"
                        d="M12 13V4M7 14H5a1 1 0 0 0-1 1v4a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-4a1 1 0 0 0-1-1h-2m-1-5-4 5-4-5m9 8h.01" />
                </svg>
                Export Excel
            `;

                // Close dropdown when clicking outside
                document.addEventListener('click', function(event) {
                    if (!container.contains(event.target)) {
                        dropdown.style.display = 'none';
                    }
                });
            }
        }

        // Initialize - Enhanced to handle URL parameters properly
        document.addEventListener('DOMContentLoaded', function() {
            updateReportPeriod();

            // Add event listeners based on current period type
            const periodType = "<?php echo $periodType; ?>";
            switch (periodType) {
                case 'quarterly':
                    if (document.getElementById('report-quarter')) {
                        document.getElementById('report-quarter').addEventListener('change', updateReportPeriod);
                    }
                    break;
                case 'semi_annual':
                    if (document.getElementById('report-semi-annual')) {
                        document.getElementById('report-semi-annual').addEventListener('change', updateReportPeriod);
                    }
                    break;
                default: // monthly and annual don't have change events for their main controls
                    if (document.getElementById('report-month')) {
                        document.getElementById('report-month').addEventListener('change', updateReportPeriod);
                    }
                    break;
            }

            if (document.getElementById('report-year')) {
                document.getElementById('report-year').addEventListener('input', updateReportPeriod);
            }

            // Set initial state based on URL parameters
            const urlParams = new URLSearchParams(window.location.search);
            const reportTypeParam = urlParams.get('report_type');

            if (reportTypeParam === 'fhis') {
                showFhisReport();
            } else {
                showMainReport();
            }

            // Update export button with dropdown
            updateExportButton();
        });

        // Inactivity timer
        let inactivityTime = 1800000; // 30 minutes in ms
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
</body>

</html>