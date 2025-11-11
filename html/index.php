<?php
session_start();
date_default_timezone_set('Asia/Manila');

// Check if the user is logged in
if (!isset($_SESSION['logged_user'])) {
    echo "<script>
        alert('Please log in first.');
        window.location.href = '/dentalemr_system/html/login/login.html';
    </script>";
    exit;
}

// Auto logout after 10 minutes of inactivity
$inactiveLimit = 600; // seconds (10 minutes)

if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $inactiveLimit) {
    session_unset();
    session_destroy();
    echo "<script>
        alert('You have been logged out due to inactivity.');
        window.location.href = '/dentalemr_system/html/login/login.html';
    </script>";
    exit;
}

$_SESSION['last_activity'] = time();

// Store user session info safely
$loggedUser = $_SESSION['logged_user'];

// ---------------- DATABASE CONNECTION ----------------
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


// ---------------- KPI CARDS ----------------
$totalPatients = $conn->query("SELECT COUNT(*) AS count FROM patients")->fetch_assoc()['count'];
$today = date('Y-m-d');
$activeVisits = $conn->query("SELECT COUNT(*) AS count FROM visits WHERE visit_date = '$today'")->fetch_assoc()['count'];
$totalTreatments = $conn->query("SELECT COUNT(*) AS count FROM services_monitoring_chart")->fetch_assoc()['count'];
$patientsWithConditions = $conn->query("
    SELECT COUNT(DISTINCT patient_id) AS count 
    FROM oral_health_condition 
    WHERE dental_caries='✓' OR gingivitis='✓' OR periodontal_disease='✓' OR others='✓'
")->fetch_assoc()['count'];

// ---------------- PIE CHART ----------------
$conditions = $conn->query("
    SELECT
        SUM(CASE WHEN dental_caries='✓' THEN 1 ELSE 0 END) AS dental_caries,
        SUM(CASE WHEN gingivitis='✓' THEN 1 ELSE 0 END) AS gingivitis,
        SUM(CASE WHEN periodontal_disease='✓' THEN 1 ELSE 0 END) AS periodontal_disease,
        SUM(CASE WHEN others='✓' THEN 1 ELSE 0 END) AS others
    FROM oral_health_condition
")->fetch_assoc();

$pieData = [
    ['Condition', 'Cases'],
    ['Dental Caries', (int)$conditions['dental_caries']],
    ['Gingivitis', (int)$conditions['gingivitis']],
    ['Periodontal Disease', (int)$conditions['periodontal_disease']],
    ['Others', (int)$conditions['others']]
];

// ---------------- BAR CHART ----------------
$treatmentsResult = $conn->query("
    SELECT t.description AS treatment, COUNT(*) AS count
    FROM services_monitoring_chart s
    JOIN treatments t ON s.treatment_code = t.code
    GROUP BY t.description
    ORDER BY count DESC
");

$barData = [['Treatment', 'Count']];
while ($row = $treatmentsResult->fetch_assoc()) {
    $barData[] = [$row['treatment'], (int)$row['count']];
}

// ---------------- LINE CHART ----------------
$trendResult = $conn->query("
    SELECT DATE_FORMAT(visit_date, '%Y-%m') AS month, COUNT(*) AS count
    FROM visits
    GROUP BY month
    ORDER BY month ASC
");

$lineData = [['Month', 'Visits']];
while ($row = $trendResult->fetch_assoc()) {
    $lineData[] = [$row['month'], (int)$row['count']];
}

// ---------------- TABLES ----------------
$recentVisits = $conn->query("
    SELECT v.visit_date, p.firstname, p.surname
    FROM visits v
    JOIN patients p ON v.patient_id = p.patient_id
    ORDER BY v.visit_date DESC
    LIMIT 5
");

$recentTreatments = $conn->query("
    SELECT s.created_at, p.firstname, p.surname, t.description
    FROM services_monitoring_chart s
    JOIN treatments t ON s.treatment_code = t.code
    JOIN patients p ON s.patient_id = p.patient_id
    ORDER BY s.created_at DESC
    LIMIT 5
");

$conn->close();
?>


<!doctype html>
<html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link href="../css/style.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
    <script>
        google.charts.load('current', {
            packages: ['corechart', 'bar', 'line']
        });
        google.charts.setOnLoadCallback(drawCharts);

        function drawCharts() {
            // Pie Chart
            var pieData = google.visualization.arrayToDataTable(<?php echo json_encode($pieData); ?>);
            var pieOptions = {
                title: 'Oral Health Issues',
                is3D: true,
                pieSliceText: 'percentage',
                animation: {
                    duration: 1000,
                    easing: 'out'
                },
                colors: ['#4f46e5', '#10b981', '#f59e0b', '#ef4444']
            };
            new google.visualization.PieChart(document.getElementById('piechart')).draw(pieData, pieOptions);

            // Bar Chart
            var barData = google.visualization.arrayToDataTable(<?php echo json_encode($barData); ?>);
            var barOptions = {
                title: 'Most Common Treatments',
                legend: {
                    position: 'none'
                },
                animation: {
                    startup: true,
                    duration: 1000,
                    easing: 'out'
                },
                colors: ['#4f46e5']
            };
            new google.visualization.ColumnChart(document.getElementById('barchart')).draw(barData, barOptions);

            // Line Chart
            var lineData = google.visualization.arrayToDataTable(<?php echo json_encode($lineData); ?>);
            var lineOptions = {
                title: 'Monthly Patient Visits Trend',
                curveType: 'function',
                legend: {
                    position: 'bottom'
                },
                animation: {
                    startup: true,
                    duration: 1000,
                    easing: 'inAndOut'
                },
                colors: ['#10b981']
            };
            new google.visualization.LineChart(document.getElementById('linechart')).draw(lineData, lineOptions);
        }
    </script>

    <style>
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
            padding: 25px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            transition: transform .3s, box-shadow .3s;
            opacity: 0;
            animation: fadeUp .8s ease forwards;
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
            padding: 20px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            transition: transform .3s;
        }

        .chart-box:hover {
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
        <nav
            class="bg-white border-b border-gray-200 px-4 py-2.5 dark:bg-gray-800 dark:border-gray-700 fixed left-0 right-0 top-0 z-50">
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
                    <a href="https://flowbite.com" class="flex items-center justify-between mr-4">
                        <img src="https://th.bing.com/th/id/OIP.zjh8eiLAHY9ybXUCuYiqQwAAAA?r=0&rs=1&pid=ImgDetMain&cb=idpwebp1&o=7&rm=3"
                            class="mr-3 h-8" alt="Flowbite Logo" />
                        <span class="self-center text-2xl font-semibold whitespace-nowrap dark:text-white">MHO Dental
                            Clinic</span>
                    </a>

                </div>
                <!-- UserProfile -->
                <div class="flex items-center lg:order-2">
                    <button type="button" data-drawer-toggle="drawer-navigation" aria-controls="drawer-navigation"
                        class="p-2 mr-1 text-gray-500 rounded-lg md:hidden hover:text-gray-900 hover:bg-gray-100 dark:text-gray-400 dark:hover:text-white dark:hover:bg-gray-700 focus:ring-4 focus:ring-gray-300 dark:focus:ring-gray-600">
                        <span class="sr-only">Toggle search</span>
                        <svg aria-hidden="true" class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20"
                            xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                            <path clip-rule="evenodd" fill-rule="evenodd"
                                d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z">
                            </path>
                        </svg>
                    </button>
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
                            <span class="block text-sm font-semibold text-gray-900 dark:text-white">Neil Sims</span>
                            <span class="block text-sm text-gray-900 truncate dark:text-white">name@flowbite.com</span>
                        </div>
                        <ul class="py-1 text-gray-700 dark:text-gray-300" aria-labelledby="dropdown">
                            <li>
                                <a href="#"
                                    class="block py-2 px-4 text-sm hover:bg-gray-100 dark:hover:bg-gray-600 dark:text-gray-400 dark:hover:text-white">My
                                    profile</a>
                            </li>
                            <li>
                                <a href="./manageusers/manageuser.html"
                                    class="block py-2 px-4 text-sm hover:bg-gray-100 dark:hover:bg-gray-600 dark:text-gray-400 dark:hover:text-white">Manage users</a>
                            </li>
                        </ul>
                        <ul class="py-1 text-gray-700 dark:text-gray-300" aria-labelledby="dropdown">
                            <li>
                                <a href="/dentalemr_system/php/login/logout.php"
                                    class="block py-2 px-4 text-sm hover:bg-gray-100 dark:hover:bg-gray-600 dark:hover:text-white">Sign
                                    out</a>
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
                        <a href="addpatient.php"
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
                                <a href="./treatmentrecords/treatmentrecords.php"
                                    class="flex items-center p-2 pl-11 w-full text-base font-medium text-gray-900 rounded-lg transition duration-75 group hover:bg-gray-100 dark:text-white dark:hover:bg-gray-700">Treatment
                                    Records</a>
                            </li>
                            <li>
                                <a href="./addpatienttreatment/patienttreatment.php"
                                    class="flex items-center p-2 pl-11 w-full text-base font-medium text-gray-900 rounded-lg transition duration-75 group hover:bg-gray-100 dark:text-white dark:hover:bg-gray-700">Add
                                    Patient Treatment</a>
                            </li>
                        </ul>
                    </li>
                </ul>
                <ul class="pt-5 mt-5 space-y-2 border-t border-gray-200 dark:border-gray-700">
                    <li>
                        <a href="./reports/targetclientlist.php"
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
                        <a href="./reports/mho_ohp.php"
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
                        <a href="./reports/oralhygienefindings.php"
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
                        <a href="./archived.php"
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
                <h1>🦷 MHO Dental Clinic Dashboard</h1>
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

                <p class="text-gray-600 mb-6">
                    You are logged in as <strong><?php echo htmlspecialchars($loggedUser['type']); ?></strong>.
                </p>
                <!-- KPI Cards -->
                <div class="cards">
                    <div class="card">
                        <h3>Total Patients</h3>
                        <h2><?php echo $totalPatients; ?></h2>
                    </div>
                    <div class="card">
                        <h3>Active Visits Today</h3>
                        <h2><?php echo $activeVisits; ?></h2>
                    </div>
                    <div class="card">
                        <h3>Total Treatments Done</h3>
                        <h2><?php echo $totalTreatments; ?></h2>
                    </div>
                    <div class="card">
                        <h3>Patients with Conditions</h3>
                        <h2><?php echo $patientsWithConditions; ?></h2>
                    </div>
                </div>

                <!-- Charts Section -->
                <div class="charts">
                    <div class="chart-box">
                        <div id="piechart" style="height: 300px;"></div>
                    </div>
                    <div class="chart-box">
                        <div id="barchart" style="height: 300px;"></div>
                    </div>
                    <div class="chart-box" style="grid-column: 1 / -1;">
                        <div id="linechart" style="height: 350px;"></div>
                    </div>
                </div>

                <!-- Tables -->
                <h3>Recent Visits</h3>
                <table>
                    <tr>
                        <th>Date</th>
                        <th>Patient Name</th>
                    </tr>
                    <?php while ($v = $recentVisits->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $v['visit_date']; ?></td>
                            <td><?php echo $v['firstname'] . " " . $v['surname']; ?></td>
                        </tr>
                    <?php endwhile; ?>
                </table>

                <h3>Recent Treatments</h3>
                <table>
                    <tr>
                        <th>Date</th>
                        <th>Patient Name</th>
                        <th>Treatment</th>
                    </tr>
                    <?php while ($t = $recentTreatments->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $t['created_at']; ?></td>
                            <td><?php echo $t['firstname'] . " " . $t['surname']; ?></td>
                            <td><?php echo $t['description']; ?></td>
                        </tr>
                    <?php endwhile; ?>
                </table>
            </div>
        </main>
    </div>

    <script src="../node_modules/flowbite/dist/flowbite.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flowbite@3.1.2/dist/flowbite.min.js"></script>
    <script src="../js/tailwind.config.js"></script>

    <!-- Client-side 10-minute inactivity logout -->
    <script>
        let inactivityTime = 600000; // 10 minutes in ms
        let logoutTimer;

        function resetTimer() {
            clearTimeout(logoutTimer);
            logoutTimer = setTimeout(() => {
                alert("You've been logged out due to 10 minutes of inactivity.");
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