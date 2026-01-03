<?php
session_start();
date_default_timezone_set('Asia/Manila');

// Check if we're in offline mode
$isOfflineMode = isset($_GET['offline']) && $_GET['offline'] === 'true';
$offlineDataAvailable = false;

// Enhanced session check with proper offline support
if (!isset($_GET['uid']) && !$isOfflineMode) {
    echo "<script>
        if (!navigator.onLine) {
            window.location.href = '/dentalemr_system/html/index.php?offline=true';
        } else {
            alert('Invalid session. Please log in again.');
            window.location.href = '/dentalemr_system/html/login/login.html';
        }
    </script>";
    exit;
}

// Handle offline mode session
if ($isOfflineMode) {
    echo "<script>
        document.addEventListener('DOMContentLoaded', function() {
            const checkOfflineSession = () => {
                try {
                    const sessionData = sessionStorage.getItem('dentalemr_current_user');
                    if (sessionData) {
                        const user = JSON.parse(sessionData);
                        if (user && user.isOffline) return true;
                    }
                    
                    if (typeof localAuth !== 'undefined' && localAuth.validateOfflineSession()) return true;
                    
                    const offlineUsers = localStorage.getItem('dentalemr_local_users');
                    if (offlineUsers) {
                        const users = JSON.parse(offlineUsers);
                        if (users && users.length > 0) return true;
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
                return;
            }
            
            console.log('Offline access granted');
        });
    </script>";

    $loggedUser = [
        'id' => 'offline_user',
        'name' => 'Offline User',
        'email' => 'offline@dentalclinic.com',
        'type' => 'Dentist',
        'isOffline' => true
    ];
    $userId = 'offline';
    $isValidSession = true;
} else {
    $userId = intval($_GET['uid']);
    $isValidSession = false;

    if (
        isset($_SESSION['active_sessions']) &&
        is_array($_SESSION['active_sessions']) &&
        isset($_SESSION['active_sessions'][$userId])
    ) {
        $userSession = $_SESSION['active_sessions'][$userId];

        if (isset($userSession['id']) && isset($userSession['type'])) {
            $isValidSession = true;
            $_SESSION['active_sessions'][$userId]['last_activity'] = time();
            $loggedUser = $userSession;
        }
    }

    if (!$isValidSession) {
        echo "<script>
            if (!navigator.onLine) {
                window.location.href = '/dentalemr_system/html/index.php?offline=true';
            } else {
                alert('Please log in first.');
                window.location.href = '/dentalemr_system/html/login/login.html';
            }
        </script>";
        exit;
    }
}

// ---------------- DATABASE CONNECTION ----------------
$conn = null;
$dbError = false;

if (!$isOfflineMode) {
    $host = "localhost";
    $dbUser = "root";
    $dbPass = "";
    $dbName = "dentalemr_system";

    $conn = new mysqli($host, $dbUser, $dbPass, $dbName);
    if ($conn->connect_error) {
        $dbError = true;
        if (!isset($_GET['offline'])) {
            echo "<script>
                if (navigator.onLine) {
                    alert('Database connection failed. Please try again.');
                    console.error('Database error: " . addslashes($conn->connect_error) . "');
                } else {
                    window.location.href = '/dentalemr_system/html/index.php?offline=true&uid=' + $userId;
                }
            </script>";
            exit;
        }
    }

    if (!$dbError && $loggedUser['type'] === 'Dentist') {
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

// Initialize data variables
$totalPatients = 0;
$totalChildren = 0;
$totalYouth = 0;
$totalAdults = 0;
$activeVisits = 0;
$totalTreatments = 0;
$patientsWithConditions = 0;

// Initialize chart data
$donutData = [['Sex', 'Total']];
$barangayData = [['Barangay', 'Patients']];
$stackData = [['Month']];
$barData = [['Treatment', 'Count']];
$lineData = [['Month', 'Visits']];
$years = [date('Y')];

$recentVisitsData = [];
$recentTreatmentsData = [];

// NEW: Data arrays for clickable functionality
$malePatients = [];
$femalePatients = [];
$unknownGenderPatients = [];
$patientsByBarangay = [];
$patientsByCondition = [];
$patientsByTreatment = [];
$patientsByMonth = [];
$patientsByAgeGroup = [
    'children' => [],
    'youth' => [],
    'adults' => []
];
$patientsWithActiveVisits = [];
$patientsWithTreatments = [];
$patientsWithOralConditions = [];

// Load data only if we're online
if (!$isOfflineMode && $conn && !$dbError) {
    // ---------------- KPI CARDS ----------------
    function get_count($conn, $sql)
    {
        $res = $conn->query($sql);
        if ($res && ($row = $res->fetch_assoc())) {
            return (int)$row['count'];
        }
        return 0;
    }

    $totalPatients = get_count($conn, "SELECT COUNT(*) AS count FROM patients");
    $totalChildren = get_count($conn, "SELECT COUNT(*) AS count FROM patients WHERE age BETWEEN 0 AND 12");
    $totalYouth    = get_count($conn, "SELECT COUNT(*) AS count FROM patients WHERE age BETWEEN 13 AND 24");
    $totalAdults   = get_count($conn, "SELECT COUNT(*) AS count FROM patients WHERE age >= 25");

    $today = date('Y-m-d');
    $activeVisitsResult = $conn->query("SELECT COUNT(*) AS count FROM patients WHERE DATE(created_at) = '$today'");
    $activeVisits = $activeVisitsResult ? $activeVisitsResult->fetch_assoc()['count'] : 0;

    $totalTreatmentsResult = $conn->query("SELECT COUNT(DISTINCT patient_id) AS count FROM services_monitoring_chart");
    $totalTreatments = $totalTreatmentsResult ? $totalTreatmentsResult->fetch_assoc()['count'] : 0;

    $patientsWithConditionsResult = $conn->query("SELECT COUNT(DISTINCT patient_id) AS count FROM oral_health_condition WHERE dental_caries='✓' OR gingivitis='✓' OR periodontal_disease='✓' OR others='✓'");
    $patientsWithConditions = $patientsWithConditionsResult ? $patientsWithConditionsResult->fetch_assoc()['count'] : 0;

    // NEW: Fetch detailed patient data for all categories
    $allPatientsQuery = $conn->query("
        SELECT 
            p.patient_id,
            p.firstname,
            p.surname,
            p.middlename,
            p.age,
            p.address,
            p.sex,
            p.created_at
        FROM patients p
        ORDER BY p.firstname, p.surname
    ");

    if ($allPatientsQuery) {
        while ($patient = $allPatientsQuery->fetch_assoc()) {
            // Gender categorization
            $sex = strtoupper(trim($patient['sex']));
            if ($sex === 'M' || $sex === 'MALE') {
                $malePatients[] = $patient;
            } elseif ($sex === 'F' || $sex === 'FEMALE') {
                $femalePatients[] = $patient;
            } else {
                $unknownGenderPatients[] = $patient;
            }

            // Barangay categorization
            $barangay = $patient['address'] ? trim($patient['address']) : 'Unknown';
            if (!isset($patientsByBarangay[$barangay])) {
                $patientsByBarangay[$barangay] = [];
            }
            $patientsByBarangay[$barangay][] = $patient;

            // Age group categorization
            $age = intval($patient['age']);
            if ($age <= 12) {
                $patientsByAgeGroup['children'][] = $patient;
            } elseif ($age <= 24) {
                $patientsByAgeGroup['youth'][] = $patient;
            } else {
                $patientsByAgeGroup['adults'][] = $patient;
            }
        }
    }

    // NEW: Fetch patients with active visits today
    $activeVisitsPatientsQuery = $conn->query("
        SELECT 
            p.patient_id,
            p.firstname,
            p.surname,
            p.middlename,
            p.age,
            p.address,
            p.sex,
            p.created_at
        FROM patients p
        WHERE DATE(p.created_at) = '$today'
        ORDER BY p.created_at DESC
    ");

    if ($activeVisitsPatientsQuery) {
        while ($patient = $activeVisitsPatientsQuery->fetch_assoc()) {
            $patientsWithActiveVisits[] = $patient;
        }
    }

    // NEW: Fetch patients with treatments
    $treatedPatientsQuery = $conn->query("
        SELECT DISTINCT
            p.patient_id,
            p.firstname,
            p.surname,
            p.middlename,
            p.age,
            p.address,
            p.sex,
            p.created_at
        FROM patients p
        JOIN services_monitoring_chart s ON p.patient_id = s.patient_id
        ORDER BY p.firstname, p.surname
    ");

    if ($treatedPatientsQuery) {
        while ($patient = $treatedPatientsQuery->fetch_assoc()) {
            $patientsWithTreatments[] = $patient;
        }
    }

    // NEW: Fetch patients with oral conditions
    $oralConditionPatientsQuery = $conn->query("
        SELECT DISTINCT
            p.patient_id,
            p.firstname,
            p.surname,
            p.middlename,
            p.age,
            p.address,
            p.sex,
            p.created_at
        FROM patients p
        JOIN oral_health_condition o ON p.patient_id = o.patient_id
        WHERE o.dental_caries='✓' OR o.gingivitis='✓' OR o.periodontal_disease='✓' OR o.others='✓'
        ORDER BY p.firstname, p.surname
    ");

    if ($oralConditionPatientsQuery) {
        while ($patient = $oralConditionPatientsQuery->fetch_assoc()) {
            $patientsWithOralConditions[] = $patient;
        }
    }

    // ---------------- PIE CHART ----------------
    $conditionFields = [
        'orally_fit_child',
        'dental_caries',
        'gingivitis',
        'periodontal_disease',
        'others',
        'debris',
        'calculus',
        'abnormal_growth',
        'cleft_palate'
    ];

    $sqlParts = [];
    foreach ($conditionFields as $field) {
        $sqlParts[] = "SUM(CASE WHEN $field IN ('✓','Yes','yes','YES') THEN 1 ELSE 0 END) AS $field";
    }

    $sql = "
        SELECT 
            YEAR(created_at) AS year,
            MONTH(created_at) AS month,
            DATE_FORMAT(created_at, '%M') AS month_name,
            " . implode(", ", $sqlParts) . "
        FROM oral_health_condition
        GROUP BY YEAR(created_at), MONTH(created_at)
        ORDER BY YEAR(created_at), MONTH(created_at)
    ";

    $res = $conn->query($sql);
    $result = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $result[] = $row;
        }
    }

    $labelNames = [
        'orally_fit_child'     => 'Orally Fit Child',
        'dental_caries'        => 'Dental Caries',
        'gingivitis'           => 'Gingivitis',
        'periodontal_disease'  => 'Periodontal Disease',
        'others'               => 'Others',
        'debris'               => 'Debris',
        'calculus'             => 'Calculus',
        'abnormal_growth'      => 'Abnormal Growth',
        'cleft_palate'         => 'Cleft Palate'
    ];

    // NEW: Fetch patients by oral condition
    foreach ($conditionFields as $field) {
        $conditionPatientsQuery = $conn->query("
            SELECT DISTINCT
                p.patient_id,
                p.firstname,
                p.surname,
                p.middlename,
                p.age,
                p.address,
                p.sex,
                p.created_at
            FROM patients p
            JOIN oral_health_condition o ON p.patient_id = o.patient_id
            WHERE o.$field IN ('✓','Yes','yes','YES')
            ORDER BY p.firstname, p.surname
        ");

        $patientsByCondition[$field] = [];
        if ($conditionPatientsQuery) {
            while ($patient = $conditionPatientsQuery->fetch_assoc()) {
                $patientsByCondition[$field][] = $patient;
            }
        }
    }

    // ---------------- STACKED BAR CHART ----------------
    $stackData = [['Month']];
    foreach ($labelNames as $label) {
        $stackData[0][] = $label;
    }

    foreach ($result as $row) {
        $r = [
            $row['month_name'] . " (" . $row['year'] . ")"
        ];

        foreach ($conditionFields as $field) {
            $r[] = (int)$row[$field];
        }

        $stackData[] = $r;
    }

    // ---------------- BAR CHART ----------------
    // FIXED: This query was working before, but let's debug it
    $treatmentsResult = $conn->query("
        SELECT t.code, t.description AS treatment, COUNT(*) AS count
        FROM services_monitoring_chart s
        JOIN treatments t ON s.treatment_code = t.code
        GROUP BY t.code, t.description
        ORDER BY count DESC
    ");

    // Debug: Check if query has results
    $treatmentDebug = "Treatments query returned: ";
    if ($treatmentsResult) {
        $treatmentDebug .= $treatmentsResult->num_rows . " rows\n";
    } else {
        $treatmentDebug .= "Error: " . $conn->error . "\n";
    }

    $barData = [['Treatment', 'Count']];
    if ($treatmentsResult && $treatmentsResult->num_rows > 0) {
        while ($row = $treatmentsResult->fetch_assoc()) {
            $barData[] = [$row['treatment'], (int)$row['count']];

            // NEW: Fetch patients by treatment
            $treatmentPatientsQuery = $conn->query("
                SELECT DISTINCT
                    p.patient_id,
                    p.firstname,
                    p.surname,
                    p.middlename,
                    p.age,
                    p.address,
                    p.sex,
                    p.created_at
                FROM patients p
                JOIN services_monitoring_chart s ON p.patient_id = s.patient_id
                JOIN treatments t ON s.treatment_code = t.code
                WHERE t.code = '{$row['code']}'
                ORDER BY p.firstname, p.surname
            ");

            $patientsByTreatment[$row['treatment']] = [];
            if ($treatmentPatientsQuery) {
                while ($patient = $treatmentPatientsQuery->fetch_assoc()) {
                    $patientsByTreatment[$row['treatment']][] = $patient;
                }
            }
        }
    } else {
        // If no treatments, add placeholder
        $barData[] = ['No treatments recorded', 0];
    }

    // Male&Female Query for donut chart
    $sexQuery = $conn->query("
        SELECT 
            sex,
            COUNT(*) AS total
        FROM patients
        GROUP BY sex
    ");

    $donutData = [['Sex', 'Total']];
    if ($sexQuery) {
        while ($row = $sexQuery->fetch_assoc()) {
            $sex = strtoupper(trim($row['sex']));
            if ($sex === 'M' || $sex === 'MALE') {
                $label = 'Male';
            } elseif ($sex === 'F' || $sex === 'FEMALE') {
                $label = 'Female';
            } else {
                $label = 'Unknown';
            }
            $donutData[] = [$label, (int)$row['total']];
        }
    }

    // Barangay data
    $selectedYear = $_GET['year'] ?? '';
    if ($selectedYear == "") {
        $barangayQuery = $conn->query("
            SELECT 
                address AS barangay,
                COUNT(*) AS total
            FROM patients
            GROUP BY address
            ORDER BY address ASC
        ");
    } else {
        $barangayQuery = $conn->query("
            SELECT 
                address AS barangay,
                COUNT(*) AS total
            FROM patients
            WHERE YEAR(created_at) = '$selectedYear'
            GROUP BY address
            ORDER BY address ASC
        ");
    }

    $barangayData = [['Barangay', 'Patients']];
    if ($barangayQuery) {
        while ($row = $barangayQuery->fetch_assoc()) {
            $barangayData[] = [$row['barangay'], (int)$row['total']];
        }
    }

    // Load years
    $yearsQuery = $conn->query("
        SELECT DISTINCT YEAR(created_at) AS yr
        FROM patients
        ORDER BY yr DESC
    ");

    $years = [];
    if ($yearsQuery) {
        while ($y = $yearsQuery->fetch_assoc()) {
            $years[] = $y['yr'];
        }
    }

    // ---------------- LINE CHART ----------------
    $trendResult = $conn->query("
        SELECT DATE_FORMAT(visit_date, '%Y-%m') AS month, COUNT(*) AS count
        FROM visits
        GROUP BY month
        ORDER BY month ASC
    ");

    $lineData = [['Month', 'Visits']];
    if ($trendResult) {
        while ($row = $trendResult->fetch_assoc()) {
            $lineData[] = [$row['month'], (int)$row['count']];

            // NEW: Fetch patients by month
            $monthPatientsQuery = $conn->query("
                SELECT DISTINCT
                    p.patient_id,
                    p.firstname,
                    p.surname,
                    p.middlename,
                    p.age,
                    p.address,
                    p.sex,
                    p.created_at
                FROM patients p
                JOIN visits v ON p.patient_id = v.patient_id
                WHERE DATE_FORMAT(v.visit_date, '%Y-%m') = '{$row['month']}'
                ORDER BY p.firstname, p.surname
            ");

            $patientsByMonth[$row['month']] = [];
            if ($monthPatientsQuery) {
                while ($patient = $monthPatientsQuery->fetch_assoc()) {
                    $patientsByMonth[$row['month']][] = $patient;
                }
            }
        }
    }

    // ---------------- TABLES ----------------
    // FIXED: Recent Visits query - check if visits table exists and has data
    $checkVisitsTable = $conn->query("SHOW TABLES LIKE 'visits'");
    if ($checkVisitsTable && $checkVisitsTable->num_rows > 0) {
        $recentVisits = $conn->query("
            SELECT v.visit_id, v.visit_date, p.patient_id, p.firstname, p.surname, p.middlename, p.age, p.address, p.sex
            FROM visits v
            JOIN patients p ON v.patient_id = p.patient_id
            ORDER BY v.visit_date DESC
            LIMIT 5
        ");

        if ($recentVisits) {
            while ($v = $recentVisits->fetch_assoc()) {
                $recentVisitsData[] = $v;
            }
        }
    } else {
        // Debug info
        $visitDebug = "Visits table doesn't exist or is empty";
    }

    // FIXED: Recent Treatments query - check if services_monitoring_chart table exists
    $checkTreatmentsTable = $conn->query("SHOW TABLES LIKE 'services_monitoring_chart'");
    if ($checkTreatmentsTable && $checkTreatmentsTable->num_rows > 0) {
        $recentTreatments = $conn->query("
            SELECT 
                s.id, 
                s.created_at, 
                p.patient_id, 
                p.firstname, 
                p.surname, 
                p.middlename, 
                p.age, 
                p.address, 
                p.sex, 
                t.description, 
                t.code
            FROM services_monitoring_chart s
            LEFT JOIN treatments t ON s.treatment_code = t.code
            LEFT JOIN patients p ON s.patient_id = p.patient_id
            ORDER BY s.created_at DESC
            LIMIT 5
        ");

        // Debug: Check what the query returns
        $treatmentQueryDebug = "";
        if ($recentTreatments) {
            $treatmentQueryDebug = "Recent treatments query returned: " . $recentTreatments->num_rows . " rows\n";
            if ($recentTreatments->num_rows > 0) {
                while ($t = $recentTreatments->fetch_assoc()) {
                    $recentTreatmentsData[] = $t;
                    $treatmentQueryDebug .= "Found: " . $t['firstname'] . " " . $t['surname'] . " - " . $t['description'] . "\n";
                }
            }
        } else {
            $treatmentQueryDebug = "Recent treatments query error: " . $conn->error . "\n";
        }
    } else {
        $treatmentQueryDebug = "services_monitoring_chart table doesn't exist\n";
    }

    // Let's also check what tables we have
    $tablesResult = $conn->query("SHOW TABLES");
    $tableList = [];
    while ($row = $tablesResult->fetch_array()) {
        $tableList[] = $row[0];
    }

    if ($conn) {
        $conn->close();
    }
} else {
    // Offline mode - set placeholder data
    $offlineDataAvailable = true;
    $donutData = [['Sex', 'Total'], ['Male', 0], ['Female', 0]];
    $barangayData = [['Barangay', 'Patients'], ['No Data', 0]];
    $barData = [['Treatment', 'Count'], ['No Data', 0]];
    $lineData = [['Month', 'Visits'], [date('Y-m'), 0]];
    $stackData = [['Month', 'Dental Caries', 'Gingivitis']];
    $currentMonth = date('F Y');
    $stackData[] = [$currentMonth, 0, 0];
}
?>

<!doctype html>
<html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $isOfflineMode ? 'Offline Dashboard' : 'Dashboard'; ?> - MHO Dental Clinic</title>
    <link href="../css/style.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        /* Your existing styles remain the same with additions */
        .clickable {
            cursor: pointer !important;
            transition: all 0.3s ease !important;
        }

        .clickable:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15) !important;
        }

        .clickable-card {
            cursor: pointer !important;
            transition: all 0.3s ease !important;
        }

        .clickable-card:hover {
            transform: translateY(-5px) !important;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2) !important;
        }

        .clickable-row {
            cursor: pointer !important;
            transition: background-color 0.2s ease !important;
        }

        .clickable-row:hover {
            background-color: #dbeafe !important;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1050;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.5);
        }

        .modal-content {
            background-color: #fff;
            margin: 5% auto;
            padding: 20px;
            border-radius: 10px;
            width: 80%;
            max-width: 1000px;
            max-height: 80vh;
            overflow-y: auto;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            animation: modalFadeIn 0.3s;
        }

        @keyframes modalFadeIn {
            from {
                opacity: 0;
                transform: translateY(-50px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }

        .modal-title {
            font-size: 1.5rem;
            font-weight: bold;
            color: #1e3a8a;
        }

        .close-btn {
            font-size: 1.5rem;
            cursor: pointer;
            color: #6b7280;
            transition: color 0.3s;
        }

        .close-btn:hover {
            color: #ef4444;
        }

        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 600;
            margin-left: 10px;
        }

        .badge-male {
            background-color: #dbeafe;
            color: #1e40af;
        }

        .badge-female {
            background-color: #fce7f3;
            color: #be185d;
        }

        .badge-unknown {
            background-color: #f3f4f6;
            color: #6b7280;
        }

        .badge-barangay {
            background-color: #e0f2fe;
            color: #0369a1;
        }

        .badge-treatment {
            background-color: #dcfce7;
            color: #166534;
        }

        .badge-condition {
            background-color: #fef3c7;
            color: #92400e;
        }

        .badge-visit {
            background-color: #f3e8ff;
            color: #7c3aed;
        }

        .patient-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        .patient-table th {
            background-color: #f8fafc;
            padding: 12px;
            text-align: left;
            border-bottom: 2px solid #e2e8f0;
            font-weight: 600;
            color: #475569;
        }

        .patient-table td {
            padding: 12px;
            border-bottom: 1px solid #e2e8f0;
        }

        .patient-table tr:hover {
            background-color: #f1f5f9;
        }

        .search-box {
            margin-bottom: 20px;
            position: relative;
        }

        .search-box input {
            width: 100%;
            padding: 10px 15px 10px 40px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 14px;
        }

        .search-box i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
        }

        .patient-count {
            background-color: #e0f2fe;
            color: #0369a1;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 600;
            margin-left: 10px;
        }

        .modal-tabs {
            display: flex;
            border-bottom: 1px solid #e5e7eb;
            margin-bottom: 20px;
        }

        .modal-tab {
            padding: 10px 20px;
            cursor: pointer;
            border-bottom: 3px solid transparent;
            transition: all 0.3s;
        }

        .modal-tab.active {
            border-bottom: 3px solid #3b82f6;
            color: #3b82f6;
            font-weight: 600;
        }

        .modal-tab:hover {
            background-color: #f3f4f6;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .info-icon {
            position: absolute;
            top: 10px;
            right: 10px;
            color: #6b7280;
            cursor: help;
            font-size: 14px;
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #6b7280;
        }

        .empty-state i {
            font-size: 48px;
            margin-bottom: 16px;
            color: #d1d5db;
        }

        /* Rest of your existing styles remain the same */
        .connection-status {
            position: fixed;
            top: 80px;
            right: 20px;
            z-index: 40;
            padding: 8px 16px;
            border-radius: 8px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .connection-status.online {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        .connection-status.offline {
            background: #fef3c7;
            color: #92400e;
            border: 1px solid #fde68a;
        }

        .charts {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }

        .chart-box {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            position: relative;
        }

        .chart-title {
            font-size: 18px;
            color: #475569;
            font-weight: bold;
            margin-bottom: 10px;
            text-align: left;
        }

        .dashboard {
            max-width: 1200px;
            margin: auto;
            animation: fadeIn 1s ease;
        }

        h1 {
            text-align: center;
            color: #1e3a8a;
            animation: slideDown 1s ease;
        }

        .cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin: 2rem 0;
        }

        .card {
            background: white;
            border-radius: 15px;
            padding: 10px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            transition: transform .3s, box-shadow .3s;
            opacity: 0;
            animation: fadeUp .8s ease forwards;
            position: relative;
        }

        #kpi-cards-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            margin-bottom: 20px;
        }

        .patient-cards-section {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            flex: 1 1 auto;
            min-width: 200px;
            position: relative;
        }

        .filter-btn {
            position: absolute;
            top: -0.75rem;
            right: -0.75rem;
            z-index: 50;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.4rem 0.8rem;
            background: white;
            border: 1px solid #cbd5e1;
            border-radius: 0.5rem;
            cursor: pointer;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
            font-size: 0.875rem;
        }

        #filterDropdown {
            position: absolute;
            top: 2.5rem;
            right: 0;
            z-index: 50;
            display: none;
            width: 12rem;
            background: white;
            border-radius: 0.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 0.5rem 0;
        }

        #filterDropdown.show {
            display: block;
        }

        .patient-cards-section .card {
            flex: 1 1 200px;
            min-width: 200px;
        }

        #kpi-cards-grid>.card {
            flex: 1 1 200px;
            min-width: 200px;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
        }

        .card:nth-child(1) {
            animation-delay: .2s;
        }

        .card:nth-child(2) {
            animation-delay: .4s;
        }

        .card:nth-child(3) {
            animation-delay: .6s;
        }

        .card:nth-child(4) {
            animation-delay: .8s;
        }

        .card h3 {
            color: #475569;
            margin-bottom: 10px;
        }

        .card h2 {
            color: #1e293b;
            font-size: 2em;
            margin: 0;
        }

        .charts {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 1.5rem;
        }

        .chart-box {
            background: white;
            border-radius: 15px;
            padding: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            transition: transform .3s;
        }

        .chart-box:hover {
            transform: scale(1.02);
        }

        .tables {
            background: white;
            border-radius: 15px;
            margin-top: 20px;
            padding: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            transition: transform .3s;
        }

        .tables:hover {
            transform: scale(1.02);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 10px;
            margin-top: 1.5rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }

        th,
        td {
            padding: 10px;
            text-align: left;
        }

        th {
            background: #f1f5f9;
            color: #334155;
        }

        tr:nth-child(even) {
            background: #f9fafb;
        }

        tr:hover {
            background: #e0f2fe;
            transition: .3s;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
        }

        @keyframes fadeUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes slideDown {
            from {
                transform: translateY(-20px);
                opacity: 0;
            }

            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
    </style>
</head>

<body>
    <!-- Patient Details Modal -->
    <div id="patientModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="modalTitle">Patient Details</h2>
                <span class="close-btn" onclick="closeModal()">&times;</span>
            </div>

            <div class="modal-tabs" id="modalTabs">
                <div class="modal-tab active" data-tab="patients">Patients</div>
                <div class="modal-tab" data-tab="stats">Statistics</div>
            </div>

            <div class="tab-content active" id="patientsTab">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" id="patientSearch" placeholder="Search patients by name, age, or address..."
                        onkeyup="searchPatients()">
                </div>
                <div id="patientCount" class="text-sm text-gray-600 mb-4"></div>
                <div class="overflow-x-auto">
                    <table class="patient-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Age</th>
                                <th>Address</th>
                                <th>Gender</th>
                                <th>Registration Date</th>
                            </tr>
                        </thead>
                        <tbody id="patientList">
                            <!-- Patient list will be populated here -->
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="tab-content" id="statsTab">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                    <div class="bg-blue-50 p-4 rounded-lg">
                        <h4 class="font-semibold text-blue-800">Gender Distribution</h4>
                        <div id="modalGenderChart" class="mt-2" style="height: 150px;"></div>
                    </div>
                    <div class="bg-green-50 p-4 rounded-lg">
                        <h4 class="font-semibold text-green-800">Age Groups</h4>
                        <div id="modalAgeChart" class="mt-2" style="height: 150px;"></div>
                    </div>
                    <div class="bg-purple-50 p-4 rounded-lg">
                        <h4 class="font-semibold text-purple-800">Registration Year</h4>
                        <div id="modalYearChart" class="mt-2" style="height: 150px;"></div>
                    </div>
                </div>
                <div class="text-sm text-gray-600">
                    <p>Statistics based on the filtered patient list.</p>
                </div>
            </div>
        </div>
    </div>

    <div class="antialiased bg-gray-50 dark:bg-gray-900">
        <!-- Connection Status Indicator -->
        <div id="connectionStatus" class="connection-status <?php echo $isOfflineMode ? 'offline' : 'online'; ?> hidden">
            <i class="fas fa-<?php echo $isOfflineMode ? 'wifi-slash' : 'wifi'; ?>"></i>
            <span><?php echo $isOfflineMode ? 'Offline Mode' : 'Online'; ?></span>
        </div>
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
                    <a href="#" class="flex items-center justify-between mr-4">
                        <img src="https://th.bing.com/th/id/OIP.zjh8eiLAHY9ybXUCuYiqQwAAAA?r=0&rs=1&pid=ImgDetMain&cb=idpwebp1&o=7&rm=3"
                            class="mr-3 h-8" alt="MHO Logo" />
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
                        <button type="button" id="user-menu-button" aria-expanded="false" data-dropdown-toggle="dropdown" class="flex items-center space-x-2 p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700">
                            <div class="w-8 h-8 rounded-full bg-gray-200 dark:bg-gray-700 flex items-center justify-center overflow-hidden">
                                <?php if (!empty($loggedUser['profile_picture'])): ?>
                                    <img src="<?php echo htmlspecialchars($loggedUser['profile_picture']); ?>" alt="Profile" class="w-full h-full object-cover">
                                <?php else: ?>
                                    <i class="fas fa-user text-gray-600 dark:text-gray-400"></i>
                                <?php endif; ?>
                            </div>
                            <div class="hidden md:block text-left">
                                <div class="text-sm font-medium truncate max-w-[150px]">
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
                                <div class="text-xs text-gray-500 dark:text-gray-400 truncate max-w-[150px]">
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
                                <div class="text-sm font-semibold">
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
                                <div class="text-xs text-gray-600 dark:text-gray-400 mt-1">
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
                                    class="flex items-center px-4 py-2 text-sm hover:bg-gray-100 dark:hover:bg-gray-700">
                                    <i class="fas fa-user-circle mr-3 text-gray-500"></i>
                                    My Profile
                                </a>
                                <a href="/dentalemr_system/html/manageusers/manageuser.php?uid=<?php echo $userId;
                                                                                                echo $isOfflineMode ? '&offline=true' : ''; ?>"
                                    class="flex items-center px-4 py-2 text-sm hover:bg-gray-100 dark:hover:bg-gray-700">
                                    <i class="fas fa-users-cog mr-3 text-gray-500"></i>
                                    Manage Users
                                </a>
                                <a href="/dentalemr_system/html/manageusers/systemlogs.php?uid=<?php echo $userId;
                                                                                                echo $isOfflineMode ? '&offline=true' : ''; ?>"
                                    class="flex items-center px-4 py-2 text-sm hover:bg-gray-100 dark:hover:bg-gray-700">
                                    <i class="fas fa-history mr-3 text-gray-500"></i>
                                    System Logs
                                </a>
                            </div>
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
        <aside
            class="fixed top-0 left-0 z-40 w-64 h-screen pt-14 transition-transform -translate-x-full bg-white border-r border-gray-200 md:translate-x-0 dark:bg-gray-800 dark:border-gray-700"
            aria-label="Sidenav" id="drawer-navigation">
            <div class="overflow-y-auto py-5 px-3 h-full bg-white dark:bg-gray-800">
                <form action="#" method="GET" class="md:hidden mb-2">
                    <label for="sidebar-search" class="sr-only">Search</label>
                    <div class="relative">
                        <div class="flex absolute inset-y-0 left-0 items-center pl-3 pointer-events-none">
                            <svg class="w-5 h-5 text-gray-500 dark:text-gray-400" fill="currentColor"
                                viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
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
                        <a href="#"
                            class="flex items-center p-2 text-base font-medium text-blue-600 rounded-lg dark:text-blue bg-blue-100  dark:hover:bg-blue-700 group">
                            <svg aria-hidden="true"
                                class="w-6 h-6 text-blue-600 transition duration-75 dark:text-blue-400  dark:group-hover:text-blue"
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
                        <a href="addpatient.php?uid=<?php echo $userId;
                                                    echo $isOfflineMode ? '&offline=true' : ''; ?>"
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
                                <a href="./treatmentrecords/treatmentrecords.php?uid=<?php echo $userId;
                                                                                        echo $isOfflineMode ? '&offline=true' : ''; ?>"
                                    class="flex items-center p-2 pl-11 w-full text-base font-medium text-gray-900 rounded-lg transition duration-75 group hover:bg-gray-100 dark:text-white dark:hover:bg-gray-700">Treatment
                                    Records</a>
                            </li>
                            <li>
                                <a href="./addpatienttreatment/patienttreatment.php?uid=<?php echo $userId;
                                                                                        echo $isOfflineMode ? '&offline=true' : ''; ?>"
                                    class="flex items-center p-2 pl-11 w-full text-base font-medium text-gray-900 rounded-lg transition duration-75 group hover:bg-gray-100 dark:text-white dark:hover:bg-gray-700">Add
                                    Patient Treatment</a>
                            </li>
                        </ul>
                    </li>
                </ul>
                <ul class="pt-5 mt-5 space-y-2 border-t border-gray-200 dark:border-gray-700">
                    <li>
                        <a href="./reports/targetclientlist.php?uid=<?php echo $userId; ?>"
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
                        <a href="./reports/mho_ohp.php?uid=<?php echo $userId; ?>"
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
                        <a href="./reports/oralhygienefindings.php?uid=<?php echo $userId; ?>"
                            class="flex items-center p-2 text-base font-medium text-gray-900 rounded-lg dark:text-white hover:bg-gray-100 dark:hover:bg-gray-700 group">
                            <svg class="flex-shrink-0 w-6 h-6 text-gray-500 transition duration-75 dark:text-gray-400 group-hover:text-gray-900 dark:group-hover:text-white"
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
                        <a href="./archived.php?uid=<?php echo $userId; ?>"
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

        <main class="p-4 md:ml-64 h-auto pt-20">
            <div class="dashboard">
                <h1>🦷 MHO Dental Clinic Dashboard <?php if ($isOfflineMode): ?><span class="text-orange-500">(Offline Mode)</span><?php endif; ?></h1>
                <h1 class="text-2xl font-bold mb-4">
                    Welcome,
                    <?php
                    echo htmlspecialchars(
                        !empty($loggedUser['name'])
                            ? $loggedUser['name']
                            : ($loggedUser['email'] ?? 'User')
                    );
                    ?>!
                </h1>

                <!-- Debug Information (Hidden by default) -->
                <?php if (!$isOfflineMode && isset($treatmentQueryDebug)): ?>
                    <div class="bg-gray-100 p-4 rounded-lg mb-6 hidden" id="debugInfo">
                        <h3 class="font-bold mb-2">Database Debug Info:</h3>
                        <pre class="text-sm"><?php
                                                echo "Tables in database: " . implode(', ', $tableList) . "\n";
                                                echo "Treatments chart query: " . ($treatmentDebug ?? 'N/A') . "\n";
                                                echo "Recent treatments query: " . ($treatmentQueryDebug ?? 'N/A') . "\n";
                                                echo "Visits query: " . ($visitDebug ?? 'N/A') . "\n";
                                                ?></pre>
                    </div>
                <?php endif; ?>

                <!-- Offline Data Management Panel -->
                <?php if ($isOfflineMode): ?>
                    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-6 mb-6">
                        <!-- Your existing offline panel -->
                    </div>
                <?php endif; ?>

                <!-- Rest of your existing content -->
                <div id="kpi-cards-grid">
                    <!-- Patient Cards Section -->
                    <div class="patient-cards-section">
                        <!-- Filter button -->
                        <?php if (!$isOfflineMode): ?>
                            <button id="filterDropdownButton" class="filter-btn">
                                Filter
                                <svg class="w-4 h-4 ml-1" fill="currentColor" viewBox="0 0 20 20">
                                    <path clip-rule="evenodd" fill-rule="evenodd"
                                        d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" />
                                </svg>
                            </button>

                            <!-- Dropdown -->
                            <div id="filterDropdown">
                                <ul class="text-sm">
                                    <li><button data-group="total" class="filter-option flex w-full font-normal text-gray-900 px-2 py-1.5 cursor-pointer transition duration-75 hover:bg-gray-100 dark:hover:bg-gray-700 dark:text-white">Total Patients</button></li>
                                    <li><button data-group="children" class="filter-option flex w-full font-normal text-gray-900 px-2 py-1.5 cursor-pointer transition duration-75 hover:bg-gray-100 dark:hover:bg-gray-700 dark:text-white">Children</button></li>
                                    <li><button data-group="youth" class="filter-option flex w-full font-normal text-gray-900 px-2 py-1.5 cursor-pointer transition duration-75 hover:bg-gray-100 dark:hover:bg-gray-700 dark:text-white">Youth</button></li>
                                    <li><button data-group="adults" class="filter-option flex w-full font-normal text-gray-900 px-2 py-1.5 cursor-pointer transition duration-75 hover:bg-gray-100 dark:hover:bg-gray-700 dark:text-white">Adults</button></li>
                                    <li><button data-group="all" class="filter-option flex w-full font-normal text-gray-900 px-2 py-1.5 cursor-pointer transition duration-75 hover:bg-gray-100 dark:hover:bg-gray-700 dark:text-white">Show All</button></li>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <!-- Patient Cards (Clickable) -->
                        <div class="card clickable-card <?php echo $isOfflineMode ? 'offline-card' : ''; ?>"
                            data-group="total"
                            onclick="<?php if (!$isOfflineMode): ?>showAllPatients()<?php endif; ?>"
                            title="Click to view all patients">
                            <i class="fas fa-info-circle info-icon" title="Total registered patients"></i>
                            <h3 class="font-medium">Total Patients</h3>
                            <h2 class="count-up" data-value="<?php echo $totalPatients; ?>">0</h2>
                            <?php if ($isOfflineMode): ?>
                                <div class="text-xs text-orange-600 mt-1">Local data only</div>
                            <?php endif; ?>
                        </div>

                        <div class="card clickable-card <?php echo $isOfflineMode ? 'offline-card' : ''; ?>"
                            data-group="children"
                            style="display:none;"
                            onclick="<?php if (!$isOfflineMode): ?>showAgeGroupPatients('children')<?php endif; ?>"
                            title="Click to view children patients (0-12 years)">
                            <h3>Total Children</h3>
                            <h2 class="count-up" data-value="<?php echo $totalChildren; ?>">0</h2>
                        </div>

                        <div class="card clickable-card <?php echo $isOfflineMode ? 'offline-card' : ''; ?>"
                            data-group="youth"
                            style="display:none;"
                            onclick="<?php if (!$isOfflineMode): ?>showAgeGroupPatients('youth')<?php endif; ?>"
                            title="Click to view youth patients (13-24 years)">
                            <h3 class="font-medium">Total Youth</h3>
                            <h2 class="count-up" data-value="<?php echo $totalYouth; ?>">0</h2>
                        </div>

                        <div class="card clickable-card <?php echo $isOfflineMode ? 'offline-card' : ''; ?>"
                            data-group="adults"
                            style="display:none;"
                            onclick="<?php if (!$isOfflineMode): ?>showAgeGroupPatients('adults')<?php endif; ?>"
                            title="Click to view adult patients (25+ years)">
                            <h3 class="font-medium">Total Adults</h3>
                            <h2 class="count-up" data-value="<?php echo $totalAdults; ?>">0</h2>
                        </div>
                    </div>

                    <!-- Other KPI Cards (Clickable) -->
                    <div class="card clickable-card <?php echo $isOfflineMode ? 'offline-card' : ''; ?>"
                        onclick="<?php if (!$isOfflineMode): ?>showActiveVisitsPatients()<?php endif; ?>"
                        title="Click to view patients with visits today">
                        <i class="fas fa-info-circle info-icon" title="Patients who visited today"></i>
                        <h3 class="font-medium">Active Visits Today</h3>
                        <h2 class="count-up" data-value="<?php echo $activeVisits; ?>">0</h2>
                    </div>

                    <div class="card clickable-card <?php echo $isOfflineMode ? 'offline-card' : ''; ?>"
                        onclick="<?php if (!$isOfflineMode): ?>showTreatedPatients()<?php endif; ?>"
                        title="Click to view patients who received treatments">
                        <i class="fas fa-info-circle info-icon" title="Patients who received treatments"></i>
                        <h3 class="font-medium">Total Treatments Done</h3>
                        <h2 class="count-up" data-value="<?php echo $totalTreatments; ?>">0</h2>
                    </div>

                    <div class="card clickable-card <?php echo $isOfflineMode ? 'offline-card' : ''; ?>"
                        onclick="<?php if (!$isOfflineMode): ?>showOralConditionPatients()<?php endif; ?>"
                        title="Click to view patients with oral health conditions">
                        <i class="fas fa-info-circle info-icon" title="Patients with oral health conditions"></i>
                        <h3 class="font-medium">Patients with Conditions</h3>
                        <h2 class="count-up" data-value="<?php echo $patientsWithConditions; ?>">0</h2>
                    </div>
                </div>

                <!-- Charts Section -->
                <div class="charts">
                    <?php if ($isOfflineMode): ?>
                        <!-- Offline placeholder for charts -->
                        <div class="chart-box offline-card">
                            <div class="chart-title">📊 Data Visualization</div>
                            <div class="offline-data-placeholder">
                                <i class="fas fa-chart-bar text-4xl mb-3 text-gray-400"></i>
                                <p>Charts are available in online mode only</p>
                                <p class="text-sm mt-2">Real-time data visualization requires internet connection</p>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- Online charts (All clickable) -->
                        <div class="chart-box clickable" onclick="document.getElementById('donutchart').click()">
                            <div class="chart-title">Male and Female Patients</div>
                            <div id="donutchart" style="height: 320px;"></div>
                        </div>

                        <div class="chart-box clickable" onclick="document.getElementById('combochart_barangay').click()">
                            <div class="chart-title">Patients per Barangay</div>
                            <div style="margin-bottom: 10px; text-align:center;">
                                <label style="font-weight: medium; font-size:14px ;">Filter Year: </label>
                                <select id="yearFilter" style="font-weight: medium; font-size:14px ; cursor: pointer;" onchange="changeYear()">
                                    <option value="">All</option>
                                    <?php foreach ($years as $yr): ?>
                                        <option value="<?= $yr ?>" <?= ($yr == $selectedYear) ? 'selected' : '' ?>>
                                            <?= $yr ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div id="combochart_barangay" style="height: 320px;"></div>
                        </div>

                        <div class="chart-box clickable" onclick="document.getElementById('oralChart').click()">
                            <div class="chart-title">Oral Health Condition</div>
                            <div style="text-align:center; margin-bottom:10px;">
                                <label style="font-weight: medium; font-size:14px ;"><b>Filter Year:</b></label>
                                <select id="oralYearFilter" style="font-weight: medium; font-size:14px ; cursor: pointer;" onchange="drawCharts()">
                                    <option value="">All</option>
                                    <?php foreach ($years as $y): ?>
                                        <option value="<?= $y ?>"><?= $y ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div id="oralChart" style="height: 320px;"></div>
                        </div>

                        <div class="chart-box clickable" onclick="document.getElementById('barchart').click()">
                            <div class="chart-title">Most Common Treatments</div>
                            <div id="barchart" style="height: 320px;"></div>
                        </div>

                        <div class="chart-box clickable" style="grid-column: 1 / -1;" onclick="document.getElementById('linechart').click()">
                            <div class="chart-title">Monthly Patient Visits Trend</div>
                            <div id="linechart" style="height: 380px;" class="relative"></div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Tables (Clickable rows) -->
                <?php if (!$isOfflineMode): ?>
                    <!-- Recent Visits Table -->
                    <div class="tables">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="font-bold">Recent Visits</h3>
                            <?php if (empty($recentVisitsData)): ?>
                                <span class="text-sm text-gray-500">No visits recorded</span>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($recentVisitsData)): ?>
                            <table>
                                <tr>
                                    <th>Date</th>
                                    <th>Patient Name</th>
                                    <th>Action</th>
                                </tr>
                                <?php foreach ($recentVisitsData as $v): ?>
                                    <tr class="clickable-row" onclick="viewPatientDetails(<?php echo $v['patient_id']; ?>)">
                                        <td><?php echo $v['visit_date']; ?></td>
                                        <td><?php echo $v['firstname'] . " " . $v['surname']; ?></td>
                                        <td>
                                            <button class="text-blue-600 hover:text-blue-800 text-sm"
                                                onclick="event.stopPropagation(); viewVisitDetails(<?php echo $v['visit_id']; ?>)">
                                                <i class="fas fa-eye mr-1"></i>View Details
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </table>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-calendar-check"></i>
                                <p>No recent visits found</p>
                                <p class="text-sm mt-2">Patient visits will appear here once recorded</p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Recent Treatments Table -->
                    <div class="tables">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="font-bold">Recent Treatments</h3>
                            <?php if (empty($recentTreatmentsData)): ?>
                                <span class="text-sm text-gray-500">No treatments recorded</span>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($recentTreatmentsData)): ?>
                            <table>
                                <tr>
                                    <th>Date</th>
                                    <th>Patient Name</th>
                                    <th>Treatment</th>
                                    <th>Action</th>
                                </tr>
                                <?php foreach ($recentTreatmentsData as $t): ?>
                                    <tr class="clickable-row" onclick="viewPatientDetails(<?php echo $t['patient_id']; ?>)">
                                        <td><?php echo $t['created_at']; ?></td>
                                        <td><?php echo $t['firstname'] . " " . $t['surname']; ?></td>
                                        <td><?php echo $t['description'] ?? 'Unknown Treatment'; ?></td>
                                        <td>
                                            <button class="text-blue-600 hover:text-blue-800 text-sm"
                                                onclick="event.stopPropagation(); viewTreatmentDetails(<?php echo $t['id']; ?>, '<?php echo $t['description'] ?? 'Unknown'; ?>')">
                                                <i class="fas fa-eye mr-1"></i>View Details
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </table>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-stethoscope"></i>
                                <p>No recent treatments found</p>
                                <p class="text-sm mt-2">Patient treatments will appear here once recorded</p>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script src="../node_modules/flowbite/dist/flowbite.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flowbite@3.1.2/dist/flowbite.min.js"></script>
    <script src="../js/tailwind.config.js"></script>

    <!-- Load offline systems -->
    <script src="/dentalemr_system/js/offline-storage.js"></script>
    <script src="/dentalemr_system/js/local-auth.js"></script>

    <script>
        // Data passed from PHP
        const genderData = {
            male: <?php echo json_encode($malePatients); ?>,
            female: <?php echo json_encode($femalePatients); ?>,
            unknown: <?php echo json_encode($unknownGenderPatients); ?>
        };

        const barangayData = <?php echo json_encode($patientsByBarangay); ?>;
        const conditionData = <?php echo json_encode($patientsByCondition); ?>;
        const treatmentData = <?php echo json_encode($patientsByTreatment); ?>;
        const monthData = <?php echo json_encode($patientsByMonth); ?>;
        const ageGroupData = <?php echo json_encode($patientsByAgeGroup); ?>;

        // Additional patient lists
        const allPatients = <?php echo json_encode(array_merge($malePatients, $femalePatients, $unknownGenderPatients)); ?>;
        const activeVisitsPatients = <?php echo json_encode($patientsWithActiveVisits); ?>;
        const treatedPatients = <?php echo json_encode($patientsWithTreatments); ?>;
        const oralConditionPatients = <?php echo json_encode($patientsWithOralConditions); ?>;

        // Debug: Show data counts
        console.log('Data loaded:', {
            allPatients: allPatients.length,
            recentTreatmentsData: <?php echo json_encode($recentTreatmentsData); ?>,
            treatmentData: Object.keys(treatmentData || {}).length,
            treatedPatients: treatedPatients.length
        });

        // Current modal data
        let currentModalData = {
            type: '',
            title: '',
            patients: []
        };

        // ========== CARD FUNCTIONS ==========
        function showAllPatients() {
            showPatientDetails('All Patients', allPatients, 'badge-barangay');
        }

        function showAgeGroupPatients(ageGroup) {
            let title = '';
            switch (ageGroup) {
                case 'children':
                    title = 'Children Patients (0-12 years)';
                    break;
                case 'youth':
                    title = 'Youth Patients (13-24 years)';
                    break;
                case 'adults':
                    title = 'Adult Patients (25+ years)';
                    break;
            }
            showPatientDetails(title, ageGroupData[ageGroup] || [], 'badge-treatment');
        }

        function showActiveVisitsPatients() {
            showPatientDetails('Patients with Visits Today', activeVisitsPatients, 'badge-visit');
        }

        function showTreatedPatients() {
            showPatientDetails('Patients Who Received Treatments', treatedPatients, 'badge-treatment');
        }

        function showOralConditionPatients() {
            showPatientDetails('Patients with Oral Health Conditions', oralConditionPatients, 'badge-condition');
        }

        // ========== CHART FUNCTIONS ==========
        function showGenderDetails(gender) {
            let genderText = '';
            let badgeClass = '';
            let patients = [];

            switch (gender) {
                case 'Male':
                    genderText = 'Male Patients';
                    badgeClass = 'badge-male';
                    patients = genderData.male;
                    break;
                case 'Female':
                    genderText = 'Female Patients';
                    badgeClass = 'badge-female';
                    patients = genderData.female;
                    break;
                case 'Unknown':
                    genderText = 'Unknown Gender Patients';
                    badgeClass = 'badge-unknown';
                    patients = genderData.unknown;
                    break;
            }

            showPatientDetails(genderText, patients, badgeClass);
        }

        function showBarangayDetails(barangay) {
            const patients = barangayData[barangay] || [];
            showPatientDetails(`${barangay} Patients`, patients, 'badge-barangay');
        }

        function showConditionDetails(conditionName) {
            // Map display names to field names
            const conditionMap = {
                'Orally Fit Child': 'orally_fit_child',
                'Dental Caries': 'dental_caries',
                'Gingivitis': 'gingivitis',
                'Periodontal Disease': 'periodontal_disease',
                'Others': 'others',
                'Debris': 'debris',
                'Calculus': 'calculus',
                'Abnormal Growth': 'abnormal_growth',
                'Cleft Palate': 'cleft_palate'
            };

            const fieldName = conditionMap[conditionName];
            const patients = conditionData[fieldName] || [];
            showPatientDetails(`${conditionName} Patients`, patients, 'badge-condition');
        }

        function showTreatmentDetails(treatmentName) {
            const patients = treatmentData[treatmentName] || [];
            showPatientDetails(`${treatmentName} Patients`, patients, 'badge-treatment');
        }

        function showMonthDetails(month) {
            const patients = monthData[month] || [];
            const formattedMonth = formatMonth(month);
            showPatientDetails(`Patients Visited in ${formattedMonth}`, patients, 'badge-visit');
        }

        function formatMonth(monthString) {
            if (!monthString) return 'Unknown Month';
            const [year, month] = monthString.split('-');
            const date = new Date(year, month - 1);
            return date.toLocaleDateString('en-US', {
                month: 'long',
                year: 'numeric'
            });
        }

        // ========== MAIN MODAL FUNCTION ==========
        function showPatientDetails(title, patients, badgeClass) {
            const modal = document.getElementById('patientModal');
            const modalTitle = document.getElementById('modalTitle');

            // Store current modal data
            currentModalData = {
                type: 'custom',
                title: title,
                patients: patients
            };

            const badgeText = badgeClass ? `<span class="badge ${badgeClass}">${patients.length} patients</span>` : '';
            modalTitle.innerHTML = `${title} ${badgeText}`;
            updatePatientList(patients);

            // Reset to patients tab
            switchTab('patients');

            // Show modal
            modal.style.display = 'block';

            // Reset search
            document.getElementById('patientSearch').value = '';

            // Draw statistics charts
            setTimeout(() => drawStatisticsCharts(patients), 100);
        }

        function updatePatientList(patients) {
            const patientList = document.getElementById('patientList');
            const patientCount = document.getElementById('patientCount');

            // Update patient count
            patientCount.innerHTML = `
                Showing ${patients.length} patients. Click on a patient for detailed view.
            `;

            // Clear previous list
            patientList.innerHTML = '';

            // Populate patient list
            patients.forEach(patient => {
                const row = document.createElement('tr');
                const fullName = `${patient.firstname} ${patient.middlename ? patient.middlename + ' ' : ''}${patient.surname}`;
                const genderBadge = getGenderBadge(patient.sex);

                row.innerHTML = `
                    <td>${patient.patient_id}</td>
                    <td class="font-medium">${fullName}</td>
                    <td>${patient.age}</td>
                    <td>${patient.address || 'N/A'}</td>
                    <td>${genderBadge}</td>
                    <td>${new Date(patient.created_at).toLocaleDateString()}</td>
                `;
                row.style.cursor = 'pointer';
                row.onclick = () => viewPatientDetails(patient.patient_id);
                patientList.appendChild(row);
            });
        }

        function drawStatisticsCharts(patients) {
            if (patients.length === 0) return;

            // Gender distribution
            const genderCounts = {
                male: 0,
                female: 0,
                unknown: 0
            };

            patients.forEach(patient => {
                const sex = patient.sex ? patient.sex.toUpperCase() : '';
                if (sex === 'M' || sex === 'MALE') {
                    genderCounts.male++;
                } else if (sex === 'F' || sex === 'FEMALE') {
                    genderCounts.female++;
                } else {
                    genderCounts.unknown++;
                }
            });

            const genderChartData = [
                ['Gender', 'Count'],
                ['Male', genderCounts.male],
                ['Female', genderCounts.female],
                ['Unknown', genderCounts.unknown]
            ];

            // Age groups
            const ageGroups = {
                'Children (0-12)': 0,
                'Youth (13-24)': 0,
                'Adults (25+)': 0
            };

            patients.forEach(patient => {
                const age = parseInt(patient.age) || 0;
                if (age <= 12) {
                    ageGroups['Children (0-12)']++;
                } else if (age <= 24) {
                    ageGroups['Youth (13-24)']++;
                } else {
                    ageGroups['Adults (25+)']++;
                }
            });

            const ageChartData = [
                ['Age Group', 'Count'],
                ['Children (0-12)', ageGroups['Children (0-12)']],
                ['Youth (13-24)', ageGroups['Youth (13-24)']],
                ['Adults (25+)', ageGroups['Adults (25+)']]
            ];

            // Registration years
            const yearCounts = {};
            patients.forEach(patient => {
                const year = new Date(patient.created_at).getFullYear();
                yearCounts[year] = (yearCounts[year] || 0) + 1;
            });

            const yearChartData = [
                ['Year', 'Count']
            ];
            Object.keys(yearCounts).sort().forEach(year => {
                yearChartData.push([year.toString(), yearCounts[year]]);
            });

            // Draw charts
            google.charts.setOnLoadCallback(() => {
                // Gender chart
                const genderDataTable = google.visualization.arrayToDataTable(genderChartData);
                const genderOptions = {
                    pieHole: 0.4,
                    legend: {
                        position: 'bottom'
                    },
                    colors: ['#3b82f6', '#ef4444', '#6b7280'],
                    chartArea: {
                        width: '90%',
                        height: '80%'
                    }
                };
                const genderChart = new google.visualization.PieChart(document.getElementById('modalGenderChart'));
                genderChart.draw(genderDataTable, genderOptions);

                // Age chart
                const ageDataTable = google.visualization.arrayToDataTable(ageChartData);
                const ageOptions = {
                    legend: {
                        position: 'none'
                    },
                    colors: ['#10b981', '#f59e0b', '#8b5cf6'],
                    chartArea: {
                        width: '80%',
                        height: '80%'
                    },
                    bar: {
                        groupWidth: '50%'
                    }
                };
                const ageChart = new google.visualization.ColumnChart(document.getElementById('modalAgeChart'));
                ageChart.draw(ageDataTable, ageOptions);

                // Year chart
                if (yearChartData.length > 1) {
                    const yearDataTable = google.visualization.arrayToDataTable(yearChartData);
                    const yearOptions = {
                        legend: {
                            position: 'none'
                        },
                        colors: ['#8b5cf6'],
                        chartArea: {
                            width: '80%',
                            height: '80%'
                        },
                        hAxis: {
                            title: 'Year'
                        },
                        vAxis: {
                            title: 'Patients'
                        }
                    };
                    const yearChart = new google.visualization.ColumnChart(document.getElementById('modalYearChart'));
                    yearChart.draw(yearDataTable, yearOptions);
                }
            });
        }

        function switchTab(tabName) {
            // Update tabs
            document.querySelectorAll('.modal-tab').forEach(tab => {
                tab.classList.remove('active');
                if (tab.dataset.tab === tabName) {
                    tab.classList.add('active');
                }
            });

            // Update content
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
                if (content.id === tabName + 'Tab') {
                    content.classList.add('active');
                }
            });

            // If switching to stats tab, draw charts
            if (tabName === 'stats' && currentModalData.patients.length > 0) {
                drawStatisticsCharts(currentModalData.patients);
            }
        }

        function getGenderBadge(sex) {
            const sexUpper = sex ? sex.toUpperCase() : '';
            let badgeClass = '';
            let badgeText = '';

            if (sexUpper === 'M' || sexUpper === 'MALE') {
                badgeClass = 'badge-male';
                badgeText = 'Male';
            } else if (sexUpper === 'F' || sexUpper === 'FEMALE') {
                badgeClass = 'badge-female';
                badgeText = 'Female';
            } else {
                badgeClass = 'badge-unknown';
                badgeText = 'Unknown';
            }

            return `<span class="badge ${badgeClass}">${badgeText}</span>`;
        }

        function closeModal() {
            document.getElementById('patientModal').style.display = 'none';
        }

        function searchPatients() {
            const searchTerm = document.getElementById('patientSearch').value.toLowerCase();
            const rows = document.querySelectorAll('#patientList tr');
            let visibleCount = 0;

            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                if (text.includes(searchTerm)) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });

            // Update count
            document.getElementById('patientCount').innerHTML = `
                Showing ${visibleCount} of ${rows.length} patients. ${searchTerm ? '(Filtered)' : ''}
            `;
        }

        function viewPatientDetails(patientId) {
            alert(`Viewing details for patient ID: ${patientId}\n\nRedirecting to patient profile...`);
            window.location.href = `/dentalemr_system/html/treatmentrecords/view_info.php?id=${patientId}&uid=<?php echo $userId; ?>`;
        }

        function viewVisitDetails(visitId) {
            alert(`Viewing details for visit ID: ${visitId}\n\nThis would show visit-specific information.`);
        }

        function viewTreatmentDetails(treatmentId, treatmentName) {
            alert(`Viewing details for treatment: ${treatmentName}\n\nTreatment ID: ${treatmentId}`);
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('patientModal');
            if (event.target == modal) {
                closeModal();
            }
        }

        // ========== GOOGLE CHARTS ==========
        <?php if (!$isOfflineMode): ?>
            google.charts.load('current', {
                packages: ['corechart', 'bar', 'line']
            });
            google.charts.setOnLoadCallback(drawCharts);

            function drawCharts() {
                // 1. Donut Chart (Male and Female)
                var donutData = google.visualization.arrayToDataTable(<?php echo json_encode($donutData); ?>);
                var donutOptions = {
                    pieHole: 0.45,
                    legend: {
                        position: 'right'
                    },
                    chartArea: {
                        width: '85%',
                        height: '80%'
                    },
                    colors: ['#4f46e5', '#ef4444', '#6b7280'],
                    animation: {
                        duration: 1000,
                        easing: 'out'
                    },
                    tooltip: {
                        text: 'value'
                    }
                };

                var donutChart = new google.visualization.PieChart(document.getElementById('donutchart'));
                donutChart.draw(donutData, donutOptions);

                // Add click event to donut chart
                google.visualization.events.addListener(donutChart, 'select', function() {
                    var selection = donutChart.getSelection();
                    if (selection.length > 0) {
                        var item = selection[0];
                        var gender = donutData.getValue(item.row, 0);
                        showGenderDetails(gender);
                    }
                });

                // 2. Barangay Chart
                var rawBarangay = <?php echo json_encode($barangayData); ?>;
                var barangayPieData = google.visualization.arrayToDataTable(rawBarangay);
                var barangayPieOptions = {
                    is3D: true,
                    chartArea: {
                        width: '90%',
                        height: '85%'
                    },
                    animation: {
                        startup: true,
                        duration: 1000,
                        easing: 'out'
                    },
                    colors: ['#4f46e5', '#10b981', '#f59e0b', '#ef4444', '#6366f1', '#14b8a6', '#f472b6', '#8b5cf6', '#06b6d4', '#db2777', '#84cc16'],
                    tooltip: {
                        text: 'value'
                    }
                };

                var barangayChart = new google.visualization.PieChart(document.getElementById('combochart_barangay'));
                barangayChart.draw(barangayPieData, barangayPieOptions);

                // Add click event to barangay chart
                google.visualization.events.addListener(barangayChart, 'select', function() {
                    var selection = barangayChart.getSelection();
                    if (selection.length > 0) {
                        var item = selection[0];
                        if (item.row > 0) {
                            var barangay = barangayPieData.getValue(item.row, 0);
                            showBarangayDetails(barangay);
                        }
                    }
                });

                // 3. Oral Examination Chart (Stacked Column Chart)
                let rawStack = <?php echo json_encode($stackData); ?>;
                const selectedYear = document.getElementById("oralYearFilter")?.value || "";
                let filteredStack = rawStack;

                if (selectedYear !== "") {
                    filteredStack = rawStack.filter((row, index) => {
                        if (index === 0) return true;
                        return row[0].includes("(" + selectedYear + ")");
                    });
                }

                filteredStack = filteredStack.map((row, index) => {
                    if (index === 0) return row;
                    row[0] = String(row[0]);
                    return row;
                });

                const stackDataTable = google.visualization.arrayToDataTable(filteredStack);
                const condColors = ['#4f46e5', '#10b981', '#f59e0b', '#ef4444', '#6366f1', '#14b8a6', '#f472b6', '#8b5cf6', '#06b6d4', '#f87171', '#84cc16', '#ec4899'];
                const stackOptions = {
                    title: "Total Cases of Oral Examination",
                    isStacked: true,
                    legend: {
                        position: 'top',
                        maxLines: 3
                    },
                    chartArea: {
                        width: '80%',
                        height: '70%'
                    },
                    hAxis: {
                        title: "Month",
                        slantedText: true,
                        slantedTextAngle: 45
                    },
                    vAxis: {
                        title: "Number of Cases"
                    },
                    colors: condColors.slice(0, stackDataTable.getNumberOfColumns() - 1),
                    animation: {
                        startup: true,
                        duration: 1200,
                        easing: 'out'
                    },
                    tooltip: {
                        text: 'value'
                    }
                };

                var oralChart = new google.visualization.ColumnChart(document.getElementById('oralChart'));
                oralChart.draw(stackDataTable, stackOptions);

                // Add click event to oral health chart
                google.visualization.events.addListener(oralChart, 'select', function() {
                    var selection = oralChart.getSelection();
                    if (selection.length > 0) {
                        var item = selection[0];
                        var columnIndex = item.column;
                        if (columnIndex > 0) {
                            var conditionName = stackDataTable.getColumnLabel(columnIndex);
                            showConditionDetails(conditionName);
                        }
                    }
                });

                // 4. Most Common treatment Chart
                var rawBar = <?php echo json_encode($barData); ?>;
                var barDataTable = new google.visualization.DataTable();
                barDataTable.addColumn('string', rawBar[0][0]);
                barDataTable.addColumn('number', rawBar[0][1]);
                barDataTable.addColumn({
                    type: 'string',
                    role: 'style'
                });

                var colors = ['#4f46e5', '#10b981', '#f59e0b', '#ef4444', '#6366f1', '#14b8a6', '#f472b6'];
                for (var i = 1; i < rawBar.length; i++) {
                    barDataTable.addRow([rawBar[i][0], rawBar[i][1], 'color: ' + colors[(i - 1) % colors.length]]);
                }

                var barOptions = {
                    legend: {
                        position: 'none'
                    },
                    chartArea: {
                        width: '85%',
                        height: '75%'
                    },
                    animation: {
                        startup: true,
                        duration: 1000,
                        easing: 'out'
                    },
                    tooltip: {
                        text: 'value'
                    },
                    hAxis: {
                        slantedText: true,
                        slantedTextAngle: 45
                    }
                };

                var barChart = new google.visualization.ColumnChart(document.getElementById('barchart'));
                barChart.draw(barDataTable, barOptions);

                // Add click event to treatment chart
                google.visualization.events.addListener(barChart, 'select', function() {
                    var selection = barChart.getSelection();
                    if (selection.length > 0) {
                        var item = selection[0];
                        var treatmentName = barDataTable.getValue(item.row, 0);
                        showTreatmentDetails(treatmentName);
                    }
                });

                // 5. Line Chart
                var lineDataTable = google.visualization.arrayToDataTable(<?php echo json_encode($lineData); ?>);
                var lineOptions = {
                    legend: {
                        position: 'bottom'
                    },
                    chartArea: {
                        width: '85%',
                        height: '70%'
                    },
                    curveType: 'function',
                    animation: {
                        startup: true,
                        duration: 1000,
                        easing: 'inAndOut'
                    },
                    colors: ['#10b981'],
                    tooltip: {
                        text: 'value'
                    },
                    hAxis: {
                        slantedText: true,
                        slantedTextAngle: 45
                    }
                };

                var lineChart = new google.visualization.LineChart(document.getElementById('linechart'));
                lineChart.draw(lineDataTable, lineOptions);

                // Add click event to line chart
                google.visualization.events.addListener(lineChart, 'select', function() {
                    var selection = lineChart.getSelection();
                    if (selection.length > 0) {
                        var item = selection[0];
                        var month = lineDataTable.getValue(item.row, 0);
                        showMonthDetails(month);
                    }
                });
            }
        <?php endif; ?>

        // ========== INITIALIZATION ==========
        const isOfflineMode = <?php echo $isOfflineMode ? 'true' : 'false'; ?>;

        document.addEventListener('DOMContentLoaded', function() {
            const connectionStatus = document.getElementById('connectionStatus');
            if (connectionStatus) {
                connectionStatus.classList.remove('hidden');

                if (!navigator.onLine) {
                    connectionStatus.className = 'connection-status offline';
                    connectionStatus.innerHTML = '<i class="fas fa-wifi-slash"></i><span>Offline Mode</span>';
                } else {
                    connectionStatus.className = 'connection-status online';
                    connectionStatus.innerHTML = '<i class="fas fa-wifi"></i><span>Online</span>';
                }
            }

            if (isOfflineMode) {
                updateOfflineStats();
            }

            // Count-up animation
            const counters = document.querySelectorAll(".count-up");
            counters.forEach(counter => {
                const finalValue = parseInt(counter.getAttribute("data-value")) || 0;
                let current = 0;
                const duration = 1000;
                const steps = 60;
                const increment = finalValue / steps;

                function updateCounter() {
                    current += increment;
                    if (current < finalValue) {
                        counter.innerText = Math.floor(current);
                        requestAnimationFrame(updateCounter);
                    } else {
                        counter.innerText = finalValue;
                    }
                }
                updateCounter();
            });

            // Tab switching
            document.querySelectorAll('.modal-tab').forEach(tab => {
                tab.addEventListener('click', () => {
                    switchTab(tab.dataset.tab);
                });
            });

            // Filter functionality (only in online mode)
            <?php if (!$isOfflineMode): ?>
                const filterButton = document.getElementById("filterDropdownButton");
                const filterDropdown = document.getElementById("filterDropdown");
                const options = document.querySelectorAll(".filter-option");
                const patientCards = document.querySelectorAll(".patient-cards-section .card");

                if (filterButton) {
                    filterButton.addEventListener("click", () => {
                        filterDropdown.classList.toggle("show");
                    });

                    options.forEach(option => {
                        option.addEventListener("click", () => {
                            const group = option.getAttribute("data-group");
                            patientCards.forEach(card => {
                                card.style.display = (group === "all" || card.getAttribute("data-group") === group) ? "block" : "none";
                            });
                            filterDropdown.classList.remove("show");
                        });
                    });

                    document.addEventListener("click", (e) => {
                        if (!filterButton.contains(e.target) && !filterDropdown.contains(e.target)) {
                            filterDropdown.classList.remove("show");
                        }
                    });
                }

                // Show debug info on double-click of title
                const title = document.querySelector('h1');
                if (title) {
                    let clickCount = 0;
                    title.addEventListener('click', function() {
                        clickCount++;
                        if (clickCount === 2) {
                            const debugInfo = document.getElementById('debugInfo');
                            if (debugInfo) {
                                debugInfo.classList.toggle('hidden');
                                clickCount = 0;
                            }
                        }
                        setTimeout(() => {
                            clickCount = 0;
                        }, 500);
                    });
                }
            <?php endif; ?>
        });

        // ========== CONNECTION MONITORING ==========
        window.addEventListener('online', function() {
            const connectionStatus = document.getElementById('connectionStatus');
            if (connectionStatus) {
                connectionStatus.className = 'connection-status online';
                connectionStatus.innerHTML = '<i class="fas fa-wifi"></i><span>Online</span>';
            }

            if (isOfflineMode) {
                if (confirm('Internet connection restored. Would you like to reload the page in online mode?')) {
                    window.location.href = window.location.href.replace('offline=true', '');
                }
            }
        });

        window.addEventListener('offline', function() {
            const connectionStatus = document.getElementById('connectionStatus');
            if (connectionStatus) {
                connectionStatus.className = 'connection-status offline';
                connectionStatus.innerHTML = '<i class="fas fa-wifi-slash"></i><span>Offline Mode</span>';
            }

            if (!isOfflineMode && !window.location.href.includes('offline=true')) {
                setTimeout(() => {
                    if (confirm('Internet connection lost. Switch to offline mode?')) {
                        window.location.href += (window.location.href.includes('?') ? '&' : '?') + 'offline=true';
                    }
                }, 2000);
            }
        });

        // ========== UTILITY FUNCTIONS ==========
        async function updateOfflineStats() {
            if (typeof offlineStorage !== 'undefined') {
                try {
                    const stats = await offlineStorage.getStorageStats();
                    document.getElementById('offlinePatientCount').textContent = stats.totalPatients || 0;
                    document.getElementById('offlineTreatmentCount').textContent = stats.totalTreatments || 0;
                    document.getElementById('pendingSyncCount').textContent =
                        (stats.unsyncedPatients || 0) + (stats.unsyncedTreatments || 0);
                } catch (error) {
                    console.error('Error updating offline stats:', error);
                }
            }
        }

        async function syncOfflineData() {
            if (!navigator.onLine) {
                alert('You are currently offline. Please check your internet connection and try again.');
                return;
            }

            if (typeof offlineStorage !== 'undefined') {
                try {
                    const success = await offlineStorage.syncOfflineData();
                    if (success) {
                        alert('Data synced successfully!');
                        window.location.reload();
                    } else {
                        alert('Some data failed to sync. Please try again.');
                    }
                } catch (error) {
                    console.error('Sync error:', error);
                    alert('Sync failed. Please try again.');
                }
            } else {
                alert('Offline storage not available. Please go online first.');
            }
        }

        function viewOfflinePatients() {
            alert('This would show a modal with locally stored patients. Implementation needed.');
        }

        function changeYear() {
            let year = document.getElementById("yearFilter").value;
            const params = new URLSearchParams(window.location.search);
            params.set("year", year);
            window.location.href = "http://localhost/dentalemr_system/html/index.php?" + params.toString();
        }

        // ========== INACTIVITY LOGOUT ==========
        let inactivityTime = 1800000;
        let logoutTimer;

        function resetTimer() {
            clearTimeout(logoutTimer);
            logoutTimer = setTimeout(() => {
                alert("You've been logged out due to 30 minutes of inactivity.");
                window.location.href = "/dentalemr_system/php/login/logout.php";
            }, inactivityTime);
        }

        ["click", "mousemove", "keypress", "scroll", "touchstart"].forEach(evt => {
            document.addEventListener(evt, resetTimer, false);
        });

        resetTimer();
    </script>
</body>

</html>