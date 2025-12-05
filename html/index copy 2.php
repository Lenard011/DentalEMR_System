<?php
session_start();
date_default_timezone_set('Asia/Manila');

// Check if we're in offline mode
$isOfflineMode = isset($_GET['offline']) && $_GET['offline'] === 'true';
$offlineDataAvailable = false;

// Enhanced session check with proper offline support
if (!isset($_GET['uid']) && !$isOfflineMode) {
    // If no UID and not explicitly in offline mode, check if we should go offline
    echo "<script>
        if (!navigator.onLine) {
            // Redirect to offline mode on the same page
            window.location.href = '/dentalemr_system/html/index.php?offline=true';
        } else {
            alert('Invalid session. Please log in again.');
            window.location.href = '/dentalemr_system/html/login/login.html';
        }
    </script>";
    exit;
}

// Handle offline mode session
// Handle offline mode session
if ($isOfflineMode) {
    // Enhanced offline session validation
    echo "<script>
        // Wait for the page to fully load and check for offline session
        document.addEventListener('DOMContentLoaded', function() {
            // Check if we have a valid offline session
            const checkOfflineSession = () => {
                try {
                    // Check sessionStorage first (set by login process)
                    const sessionData = sessionStorage.getItem('dentalemr_current_user');
                    if (sessionData) {
                        const user = JSON.parse(sessionData);
                        if (user && user.isOffline) {
                            console.log('Valid offline session detected:', user.email);
                            return true;
                        }
                    }
                    
                    // Fallback: check if localAuth is available
                    if (typeof localAuth !== 'undefined' && localAuth.validateOfflineSession()) {
                        console.log('Valid offline session via localAuth');
                        return true;
                    }
                    
                    // Final fallback: check localStorage for offline users
                    const offlineUsers = localStorage.getItem('dentalemr_local_users');
                    if (offlineUsers) {
                        const users = JSON.parse(offlineUsers);
                        if (users && users.length > 0) {
                            console.log('Offline users found in localStorage, allowing access');
                            return true;
                        }
                    }
                    
                    console.log('No valid offline session found');
                    return false;
                } catch (error) {
                    console.error('Error checking offline session:', error);
                    return false;
                }
            };
            
            // Check session and redirect if invalid
            if (!checkOfflineSession()) {
                alert('Please log in first for offline access.');
                window.location.href = '/dentalemr_system/html/login/login.html';
                return;
            }
            
            console.log('Offline access granted');
        });
    </script>";

    // Create a temporary session for offline mode
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
    // Online mode with UID - normal session validation
    $userId = intval($_GET['uid']);
    $isValidSession = false;

    if (
        isset($_SESSION['active_sessions']) &&
        is_array($_SESSION['active_sessions']) &&
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
        // If database fails but browser is online, show error
        if (!isset($_GET['offline'])) {
            echo "<script>
                if (navigator.onLine) {
                    alert('Database connection failed. Please try again.');
                    console.error('Database error: " . addslashes($conn->connect_error) . "');
                } else {
                    // Switch to offline mode automatically
                    window.location.href = '/dentalemr_system/html/index.php?offline=true&uid=' + $userId;
                }
            </script>";
            exit;
        }
    }

    // Fetch dentist name if user is a dentist (only in online mode)
    if (!$dbError && $loggedUser['type'] === 'Dentist') {
        $stmt = $conn->prepare("SELECT name FROM dentist WHERE id = ?");
        $stmt->bind_param("i", $loggedUser['id']);
        $stmt->execute();
        $stmt->bind_result($dentistName);
        if ($stmt->fetch()) {
            $loggedUser['name'] = $dentistName;
        }
        $stmt->close();
    }
}

// Initialize data variables with default values
$totalPatients = 0;
$totalChildren = 0;
$totalYouth = 0;
$totalAdults = 0;
$activeVisits = 0;
$totalTreatments = 0;
$patientsWithConditions = 0;

// Initialize chart data with empty defaults
$donutData = [['Sex', 'Total']];
$barangayData = [['Barangay', 'Patients']];
$stackData = [['Month']];
$barData = [['Treatment', 'Count']];
$lineData = [['Month', 'Visits']];
$years = [date('Y')];

$recentVisitsData = [];
$recentTreatmentsData = [];

// Load data only if we're online and database is working
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

    // ---------------- PIE CHART (still works with latest year) ----------------
    $latest = $result[count($result) - 1] ?? null;
    $pieData = [['Condition', 'Cases']];

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

    if ($latest) {
        foreach ($conditionFields as $field) {
            $count = (int)$latest[$field];
            if ($count > 0) {
                $pieData[] = [$labelNames[$field], $count];
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
    $treatmentsResult = $conn->query("
        SELECT t.description AS treatment, COUNT(*) AS count
        FROM services_monitoring_chart s
        JOIN treatments t ON s.treatment_code = t.code
        GROUP BY t.description
        ORDER BY count DESC
    ");

    $barData = [['Treatment', 'Count']];
    if ($treatmentsResult) {
        while ($row = $treatmentsResult->fetch_assoc()) {
            $barData[] = [$row['treatment'], (int)$row['count']];
        }
    }

    // Male&Female 
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
        }
    }

    // ---------------- TABLES ----------------
    $recentVisits = $conn->query("
        SELECT v.visit_date, p.firstname, p.surname
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

    $recentTreatments = $conn->query("
        SELECT s.created_at, p.firstname, p.surname, t.description
        FROM services_monitoring_chart s
        JOIN treatments t ON s.treatment_code = t.code
        JOIN patients p ON s.patient_id = p.patient_id
        ORDER BY s.created_at DESC
        LIMIT 5
    ");

    if ($recentTreatments) {
        while ($t = $recentTreatments->fetch_assoc()) {
            $recentTreatmentsData[] = $t;
        }
    }

    if ($conn) {
        $conn->close();
    }
} else {
    // Offline mode - set placeholder data
    $offlineDataAvailable = true;

    // Simple placeholder data for charts
    $donutData = [['Sex', 'Total'], ['Male', 0], ['Female', 0]];
    $barangayData = [['Barangay', 'Patients'], ['No Data', 0]];
    $barData = [['Treatment', 'Count'], ['No Data', 0]];
    $lineData = [['Month', 'Visits'], [date('Y-m'), 0]];

    // Simple stack data for offline
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
        .offline-indicator {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: white;
            padding: 8px 16px;
            border-radius: 8px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            animation: pulse 2s infinite;
            margin-left: 16px;
        }

        @keyframes pulse {

            0%,
            100% {
                opacity: 1;
            }

            50% {
                opacity: 0.8;
            }
        }

        .offline-card {
            border-left: 4px solid #f59e0b;
            background: #fffbeb;
        }

        .offline-feature-disabled {
            opacity: 0.6;
            position: relative;
        }

        .offline-data-placeholder {
            background: #f3f4f6;
            border: 2px dashed #d1d5db;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            color: #6b7280;
        }

        .connection-status {
            position: fixed;
            top: 80px;
            right: 20px;
            z-index: 1000;
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

        /* Rest of your existing styles remain the same */
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
        }

        /* Container for all KPI cards */
        #kpi-cards-grid {
            display: flex;
            flex-wrap: wrap;
            /* flex-direction: row; */
            gap: 1rem;
            margin-bottom: 20px;
            /* border: solid 1px black; */
        }

        /* Patient cards section */
        .patient-cards-section {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            flex: 1 1 auto;
            /* allow to grow/shrink in the row */
            min-width: 200px;
            position: relative;
            /* for filter button positioning */
        }

        /* Filter button floating inside patient cards section */
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

        /* Dropdown menu */
        #filterDropdown {
            position: absolute;
            top: 2.5rem;
            /* below button */
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

        /* Patient cards themselves */
        .patient-cards-section .card {
            flex: 1 1 200px;
            min-width: 200px;
        }

        /* Other KPI cards (always visible) */
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
            /* border: solid 1px black; */
        }

        .chart-box {
            background: white;
            border-radius: 15px;
            padding: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            transition: transform .3s;
            /* border: solid 1px black; */
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

        .table-box:hover {
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
                        <span class="sr-only">Toggle sidebar</span>
                    </button>
                    <a href="#" class="flex items-center justify-between mr-4">
                        <img src="https://th.bing.com/th/id/OIP.zjh8eiLAHY9ybXUCuYiqQwAAAA?r=0&rs=1&pid=ImgDetMain&cb=idpwebp1&o=7&rm=3"
                            class="mr-3 h-8" alt="MHO Logo" />
                        <span class="self-center text-2xl font-semibold whitespace-nowrap dark:text-white">MHO Dental Clinic</span>
                    </a>

                    <?php if ($isOfflineMode): ?>
                        <div class="offline-indicator">
                            <i class="fas fa-wifi-slash"></i>
                            <span>Offline Mode</span>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- UserProfile -->
                <div class="flex items-center lg:order-2">
                    <?php if ($isOfflineMode): ?>
                        <button onclick="syncOfflineData()"
                            class="bg-orange-500 text-white px-4 py-2 rounded-lg hover:bg-orange-600 transition-colors mr-2 flex items-center gap-2">
                            <i class="fas fa-sync"></i>
                            Sync When Online
                        </button>
                    <?php endif; ?>

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
                            </span>
                            <span class="block text-sm text-gray-900 truncate dark:text-white">
                                <?php
                                echo htmlspecialchars(
                                    !empty($loggedUser['email'])
                                        ? $loggedUser['email']
                                        : ($loggedUser['name'] ?? 'User')
                                );
                                ?>
                            </span>
                        </div>
                        <ul class="py-1 text-gray-700 dark:text-gray-300" aria-labelledby="dropdown">
                            <li>
                                <a href="#"
                                    class="block py-2 px-4 text-sm hover:bg-gray-100 dark:hover:bg-gray-600 dark:text-gray-400 dark:hover:text-white">My profile</a>
                            </li>
                            <li>
                                <a href="/dentalemr_system/html/manageusers/manageuser.php?uid=<?php echo $userId;
                                                                                                echo $isOfflineMode ? '&offline=true' : ''; ?>"
                                    class="block py-2 px-4 text-sm hover:bg-gray-100 dark:hover:bg-gray-600 dark:text-gray-400 dark:hover:text-white">Manage users</a>
                            </li>
                        </ul>
                        <ul class="py-1 text-gray-700 dark:text-gray-300" aria-labelledby="dropdown">
                            <li>
                                <a href="/dentalemr_system/html/manageusers/historylogs.php?uid=<?php echo $userId;
                                                                                                echo $isOfflineMode ? '&offline=true' : ''; ?>"
                                    class="block py-2 px-4 text-sm hover:bg-gray-100 dark:hover:bg-gray-600 dark:text-gray-400 dark:hover:text-white">History logs</a>
                            </li>
                            <li>
                                <a href="/dentalemr_system/html/manageusers/activitylogs.php?uid=<?php echo $userId;
                                                                                                    echo $isOfflineMode ? '&offline=true' : ''; ?>"
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
                                <a href="./treatmentrecords/treatmentrecords.php?uid=<?php echo $userId; echo $isOfflineMode ? '&offline=true' : ''; ?>"
                                    class="flex items-center p-2 pl-11 w-full text-base font-medium text-gray-900 rounded-lg transition duration-75 group hover:bg-gray-100 dark:text-white dark:hover:bg-gray-700">Treatment
                                    Records</a>
                            </li>
                            <li>
                                <a href="./addpatienttreatment/patienttreatment.php?uid=<?php echo $userId; echo $isOfflineMode ? '&offline=true' : ''; ?>"
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

                <!-- Offline Data Management Panel -->
                <?php if ($isOfflineMode): ?>
                    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-6 mb-6">
                        <div class="flex items-center mb-4">
                            <i class="fas fa-database text-yellow-600 text-2xl mr-3"></i>
                            <h3 class="text-lg font-semibold text-yellow-800">Offline Data Management</h3>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm mb-4">
                            <div>
                                <span class="font-medium">Local Patients:</span>
                                <span id="offlinePatientCount">0</span>
                            </div>
                            <div>
                                <span class="font-medium">Local Treatments:</span>
                                <span id="offlineTreatmentCount">0</span>
                            </div>
                            <div>
                                <span class="font-medium">Pending Sync:</span>
                                <span id="pendingSyncCount">0</span>
                            </div>
                        </div>
                        <div class="flex gap-2">
                            <button onclick="syncOfflineData()"
                                class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700 transition-colors flex items-center gap-2">
                                <i class="fas fa-sync"></i>
                                Sync When Online
                            </button>
                            <button onclick="viewOfflinePatients()"
                                class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition-colors flex items-center gap-2">
                                <i class="fas fa-list"></i>
                                View Local Data
                            </button>
                        </div>
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

                        <!-- Patient Cards -->
                        <div class="card <?php echo $isOfflineMode ? 'offline-card' : ''; ?>" data-group="total">
                            <h3 class="font-medium">Total Patients</h3>
                            <h2 class="count-up" data-value="<?php echo $totalPatients; ?>">0</h2>
                            <?php if ($isOfflineMode): ?>
                                <div class="text-xs text-orange-600 mt-1">Local data only</div>
                            <?php endif; ?>
                        </div>
                        <div class="card <?php echo $isOfflineMode ? 'offline-card' : ''; ?>" data-group="children" style="display:none;">
                            <h3>Total Children</h3>
                            <h2 class="count-up" data-value="<?php echo $totalChildren; ?>">0</h2>
                        </div>
                        <div class="card <?php echo $isOfflineMode ? 'offline-card' : ''; ?>" data-group="youth" style="display:none;">
                            <h3 class="font-medium">Total Youth</h3>
                            <h2 class="count-up" data-value="<?php echo $totalYouth; ?>">0</h2>
                        </div>
                        <div class="card <?php echo $isOfflineMode ? 'offline-card' : ''; ?>" data-group="adults" style="display:none;">
                            <h3 class="font-medium">Total Adults</h3>
                            <h2 class="count-up" data-value="<?php echo $totalAdults; ?>">0</h2>
                        </div>
                    </div>

                    <!-- Other KPI Cards -->
                    <div class="card <?php echo $isOfflineMode ? 'offline-card' : ''; ?>">
                        <h3 class="font-medium">Active Visits Today</h3>
                        <h2 class="count-up" data-value="<?php echo $activeVisits; ?>">0</h2>
                    </div>
                    <div class="card <?php echo $isOfflineMode ? 'offline-card' : ''; ?>">
                        <h3 class="font-medium">Total Treatments Done</h3>
                        <h2 class="count-up" data-value="<?php echo $totalTreatments; ?>">0</h2>
                    </div>
                    <div class="card <?php echo $isOfflineMode ? 'offline-card' : ''; ?>">
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
                        <!-- Online charts -->
                        <div class="chart-box">
                            <div class="chart-title">Male and Female Patients</div>
                            <div id="donutchart" style="height: 320px;"></div>
                        </div>

                        <div class="chart-box">
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

                        <div class="chart-box">
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

                        <div class="chart-box">
                            <div class="chart-title">Most Common Treatments</div>
                            <div id="barchart" style="height: 320px;"></div>
                        </div>

                        <div class="chart-box" style="grid-column: 1 / -1;">
                            <div class="chart-title">Monthly Patient Visits Trend</div>
                            <div id="linechart" style="height: 380px;" class="relative"></div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Tables -->
                <?php if (!$isOfflineMode && (!empty($recentVisitsData) || !empty($recentTreatmentsData))): ?>
                    <div class="tables">
                        <h3 class="font-bold">Recent Visits</h3>
                        <table>
                            <tr>
                                <th>Date</th>
                                <th>Patient Name</th>
                            </tr>
                            <?php if (!empty($recentVisitsData)): ?>
                                <?php foreach ($recentVisitsData as $v): ?>
                                    <tr>
                                        <td><?php echo $v['visit_date']; ?></td>
                                        <td><?php echo $v['firstname'] . " " . $v['surname']; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="2" class="text-center text-gray-500 py-4">No recent visits</td>
                                </tr>
                            <?php endif; ?>
                        </table>
                    </div>
                    <div class="tables">
                        <h3 class="font-bold">Recent Treatments</h3>
                        <table>
                            <tr>
                                <th>Date</th>
                                <th>Patient Name</th>
                                <th>Treatment</th>
                            </tr>
                            <?php if (!empty($recentTreatmentsData)): ?>
                                <?php foreach ($recentTreatmentsData as $t): ?>
                                    <tr>
                                        <td><?php echo $t['created_at']; ?></td>
                                        <td><?php echo $t['firstname'] . " " . $t['surname']; ?></td>
                                        <td><?php echo $t['description']; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="3" class="text-center text-gray-500 py-4">No recent treatments</td>
                                </tr>
                            <?php endif; ?>
                        </table>
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
        // Only load Google Charts and draw charts if we're online
        <?php if (!$isOfflineMode): ?>
            google.charts.load('current', {
                packages: ['corechart', 'bar', 'line']
            });
            google.charts.setOnLoadCallback(drawCharts);

            function drawCharts() {
                // Donut Chart (Male and Female)
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
                    colors: ['#4f46e5', '#ef4444'],
                    animation: {
                        duration: 1000,
                        easing: 'out'
                    }
                };
                new google.visualization.PieChart(document.getElementById('donutchart')).draw(donutData, donutOptions);

                // Barangay Chart
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
                    colors: ['#4f46e5', '#10b981', '#f59e0b', '#ef4444', '#6366f1', '#14b8a6', '#f472b6', '#8b5cf6', '#06b6d4', '#db2777', '#84cc16']
                };
                new google.visualization.PieChart(document.getElementById('combochart_barangay')).draw(barangayPieData, barangayPieOptions);

                // Oral Examination Chart
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

                const stackData = google.visualization.arrayToDataTable(filteredStack);
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
                        title: "Month"
                    },
                    vAxis: {
                        title: "Number of Cases"
                    },
                    colors: condColors.slice(0, stackData.getNumberOfColumns() - 1),
                    animation: {
                        startup: true,
                        duration: 1200,
                        easing: 'out'
                    }
                };
                new google.visualization.ColumnChart(document.getElementById('oralChart')).draw(stackData, stackOptions);

                // Most Common treatment Chart
                var rawBar = <?php echo json_encode($barData); ?>;
                var barData = new google.visualization.DataTable();
                barData.addColumn('string', rawBar[0][0]);
                barData.addColumn('number', rawBar[0][1]);
                barData.addColumn({
                    type: 'string',
                    role: 'style'
                });

                var colors = ['#4f46e5', '#10b981', '#f59e0b', '#ef4444', '#6366f1', '#14b8a6', '#f472b6'];
                for (var i = 1; i < rawBar.length; i++) {
                    barData.addRow([rawBar[i][0], rawBar[i][1], 'color: ' + colors[(i - 1) % colors.length]]);
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
                    }
                };
                new google.visualization.ColumnChart(document.getElementById('barchart')).draw(barData, barOptions);

                // Line Chart
                var lineData = google.visualization.arrayToDataTable(<?php echo json_encode($lineData); ?>);
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
                    colors: ['#10b981']
                };
                new google.visualization.LineChart(document.getElementById('linechart')).draw(lineData, lineOptions);
            }
        <?php endif; ?>

        // Enhanced offline functionality
        const isOfflineMode = <?php echo $isOfflineMode ? 'true' : 'false'; ?>;

        // Show connection status
        document.addEventListener('DOMContentLoaded', function() {
            const connectionStatus = document.getElementById('connectionStatus');
            if (connectionStatus) {
                connectionStatus.classList.remove('hidden');

                // Update status based on actual connection
                if (!navigator.onLine) {
                    connectionStatus.className = 'connection-status offline';
                    connectionStatus.innerHTML = '<i class="fas fa-wifi-slash"></i><span>Offline Mode</span>';
                } else {
                    connectionStatus.className = 'connection-status online';
                    connectionStatus.innerHTML = '<i class="fas fa-wifi"></i><span>Online</span>';
                }
            }

            // Update offline stats if in offline mode
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
            <?php endif; ?>
        });

        // Connection monitoring
        window.addEventListener('online', function() {
            const connectionStatus = document.getElementById('connectionStatus');
            if (connectionStatus) {
                connectionStatus.className = 'connection-status online';
                connectionStatus.innerHTML = '<i class="fas fa-wifi"></i><span>Online</span>';
            }

            // If we were in offline mode, offer to reload
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

            // If we're not already in offline mode, offer to switch
            if (!isOfflineMode && !window.location.href.includes('offline=true')) {
                setTimeout(() => {
                    if (confirm('Internet connection lost. Switch to offline mode?')) {
                        window.location.href += (window.location.href.includes('?') ? '&' : '?') + 'offline=true';
                    }
                }, 2000);
            }
        });

        // Offline data management functions
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
                        // Reload to show updated data
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
            // You can implement a modal to show offline patients
        }

        function changeYear() {
            let year = document.getElementById("yearFilter").value;
            const params = new URLSearchParams(window.location.search);
            const uid = params.get("uid");
            params.set("year", year);
            window.location.href = "http://localhost/dentalemr_system/html/index.php?" + params.toString();
        }

        // Client-side 10-minute inactivity logout
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