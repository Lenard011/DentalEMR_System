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
$inactiveLimit = 600; // 10 minutes

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

?>
<!doctype html>
<html>

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

        input[type="number"] {
            width: 60px;
            padding: 2px;
            border: 1px solid #d1d5db;
            border-radius: 4px;
            text-align: center;
        }

        .nowrap {
            white-space: nowrap;
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
                            <svg class="w-5 h-5 text-gray-500 dark:text-gray-400" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
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
                    <div class="hidden justify-between items-center w-full lg:flex lg:w-auto lg:order-1"
                        id="mobile-menu-2">
                        <ul class="flex flex-col mt-4 font-medium lg:flex-row lg:space-x-8 lg:mt-0">
                            <li>
                                <a href="?uid=<?php echo $userId; ?>&period=monthly"
                                    class="block py-2 pr-4 pl-3 <?php echo (!isset($_GET['period']) || $_GET['period'] == 'monthly') ? 'text-blue-800 font-semibold' : 'text-gray-700'; ?> border-b border-gray-100 hover:bg-gray-50 lg:hover:bg-transparent lg:border-0 lg:hover:text-primary-700 lg:p-0 dark:text-gray-400 lg:dark:hover:text-white dark:hover:bg-gray-700 dark:hover:text-white lg:dark:hover:bg-transparent dark:border-gray-700">Monthly</a>
                            </li>
                            <li>
                                <a href="?uid=<?php echo $userId; ?>&period=quarterly"
                                    class="block py-2 pr-4 pl-3 <?php echo (isset($_GET['period']) && $_GET['period'] == 'quarterly') ? 'text-blue-800 font-semibold' : 'text-gray-700'; ?> border-b border-gray-100 hover:bg-gray-50 lg:hover:bg-transparent lg:border-0 lg:hover:text-primary-700 lg:p-0 dark:text-gray-400 lg:dark:hover:text-white dark:hover:bg-gray-700 dark:hover:text-white lg:dark:hover:bg-transparent dark:border-gray-700">Quarterly</a>
                            </li>
                        </ul>
                    </div>
                </div>
            </nav>
        </header>

        <main class="p-3 md:ml-64 h-auto pt-0.5">
            <section class="bg-white dark:bg-gray-900 p-3 rounded-lg mb-3 mt-3">
                <!-- Report Controls -->
                <div class="w-full flex flex-row p-1 justify-between mb-4">
                    <div class="flex flex-row items-center space-x-4">
                        <div>
                            <label for="report-month" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Month</label>
                            <select id="report-month" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                <option value="1" <?php echo $currentMonth == 'January' ? 'selected' : ''; ?>>January</option>
                                <option value="2" <?php echo $currentMonth == 'February' ? 'selected' : ''; ?>>February</option>
                                <option value="3" <?php echo $currentMonth == 'March' ? 'selected' : ''; ?>>March</option>
                                <option value="4" <?php echo $currentMonth == 'April' ? 'selected' : ''; ?>>April</option>
                                <option value="5" <?php echo $currentMonth == 'May' ? 'selected' : ''; ?>>May</option>
                                <option value="6" <?php echo $currentMonth == 'June' ? 'selected' : ''; ?>>June</option>
                                <option value="7" <?php echo $currentMonth == 'July' ? 'selected' : ''; ?>>July</option>
                                <option value="8" <?php echo $currentMonth == 'August' ? 'selected' : ''; ?>>August</option>
                                <option value="9" <?php echo $currentMonth == 'September' ? 'selected' : ''; ?>>September</option>
                                <option value="10" <?php echo $currentMonth == 'October' ? 'selected' : ''; ?>>October</option>
                                <option value="11" <?php echo $currentMonth == 'November' ? 'selected' : ''; ?>>November</option>
                                <option value="12" <?php echo $currentMonth == 'December' ? 'selected' : ''; ?>>December</option>
                            </select>
                        </div>
                        <div>
                            <label for="report-year" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Year</label>
                            <input type="number" id="report-year" value="<?php echo $currentYear; ?>"
                                class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                        </div>
                        <div class="flex items-end">
                            <button type="button" onclick="loadReportData()"
                                class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                                Load Report
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Main Report Table -->
                <div class="table-container">
                    <form id="oralHealthForm" method="POST" action="save_oral_health.php">
                        <input type="hidden" name="user_id" value="<?php echo $userId; ?>">
                        <input type="hidden" name="report_month" id="form-month" value="<?php echo date('n'); ?>">
                        <input type="hidden" name="report_year" id="form-year" value="<?php echo $currentYear; ?>">

                        <table class="age-table border-collapse border border-gray-400 w-full bg-white">
                            <thead>
                                <!-- Title -->
                                <tr>
                                    <th colspan="42" class="border border-gray-400 px-2 py-2 text-center font-bold bg-gray-100">
                                        CONSOLIDATED ORAL HEALTH STATUS AND SERVICES MONTHLY REPORT
                                    </th>
                                </tr>
                                <!-- Month/Year -->
                                <tr>
                                    <th class="border border-gray-400 px-2 py-1 text-left font-medium">Month/Quarter/Year</th>
                                    <th colspan="41" class="border border-gray-400 px-2 py-1 text-left" id="report-period-display">
                                        <?php echo $currentMonth; ?>/FY<?php echo $currentYear; ?>
                                    </th>
                                </tr>
                                <!-- CHD -->
                                <tr>
                                    <th class="border border-gray-400 px-2 py-1 text-left font-medium">Center for Health Development</th>
                                    <th colspan="41" class="border border-gray-400 px-2 py-1 text-left">MiMaRoPa</th>
                                </tr>
                                <!-- Municipality -->
                                <tr>
                                    <th class="border border-gray-400 px-2 py-1 text-left font-medium">Municipality/City/Province</th>
                                    <th colspan="41" class="border border-gray-400 px-2 py-1 text-left">MAMBURAO/OCCIDENTAL MINDORO</th>
                                </tr>

                                <!-- Complex Age Group Headers -->
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
                                    <th colspan="6" class="border border-gray-400 px-1 py-1 text-center">Pregnant Women</th>

                                    <!-- Older Persons -->
                                    <th colspan="2" class="border border-gray-400 px-1 py-1 text-center">Older Persons</th>

                                    <!-- TOTAL ALL AGES -->
                                    <th colspan="2" class="border border-gray-400 px-1 py-1 text-center">TOTAL<br>ALL AGES</th>

                                    <!-- GRAND TOTAL -->
                                    <th rowspan="2" class="border border-gray-400 px-1 py-1 text-center">GRAND TOTAL</th>
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
                                    <th class="border border-gray-400 px-1 py-1 text-center sticky-header"></th>

                                    <!-- Infant -->
                                    <th class="border border-gray-400 px-1 py-1 text-center">C1</th>
                                    <th class="border border-gray-400 px-1 py-1 text-center">D1</th>

                                    <!-- Under Five Children -->
                                    <th class="border border-gray-400 px-1 py-1 text-center">E1</th>
                                    <th class="border border-gray-400 px-1 py-1 text-center">F1</th>
                                    <th class="border border-gray-400 px-1 py-1 text-center">G1</th>
                                    <th class="border border-gray-400 px-1 py-1 text-center">H1</th>
                                    <th class="border border-gray-400 px-1 py-1 text-center">I1</th>
                                    <th class="border border-gray-400 px-1 py-1 text-center">J1</th>
                                    <th class="border border-gray-400 px-1 py-1 text-center">K1</th>
                                    <th class="border border-gray-400 px-1 py-1 text-center">L1</th>
                                    <th class="border border-gray-400 px-1 py-1 text-center">M1</th>
                                    <th class="border border-gray-400 px-1 py-1 text-center">N1</th>

                                    <!-- School Age Children -->
                                    <th class="border border-gray-400 px-1 py-1 text-center">O1</th>
                                    <th class="border border-gray-400 px-1 py-1 text-center">P1</th>
                                    <th class="border border-gray-400 px-1 py-1 text-center">Q1</th>
                                    <th class="border border-gray-400 px-1 py-1 text-center">R1</th>
                                    <th class="border border-gray-400 px-1 py-1 text-center">S1</th>
                                    <th class="border border-gray-400 px-1 py-1 text-center">T1</th>
                                    <th class="border border-gray-400 px-1 py-1 text-center">U1</th>
                                    <th class="border border-gray-400 px-1 py-1 text-center">V1</th>
                                    <th class="border border-gray-400 px-1 py-1 text-center">W1</th>
                                    <th class="border border-gray-400 px-1 py-1 text-center">X1</th>
                                    <th class="border border-gray-400 px-1 py-1 text-center">Y1</th>
                                    <th class="border border-gray-400 px-1 py-1 text-center">Z1</th>

                                    <!-- Adolescent -->
                                    <th class="border border-gray-400 px-1 py-1 text-center">AA1</th>
                                    <th class="border border-gray-400 px-1 py-1 text-center">AB1</th>
                                    <th class="border border-gray-400 px-1 py-1 text-center">AC1</th>
                                    <th class="border border-gray-400 px-1 py-1 text-center">AD1</th>

                                    <!-- Adult -->
                                    <th class="border border-gray-400 px-1 py-1 text-center">AE1</th>
                                    <th class="border border-gray-400 px-1 py-1 text-center">AF1</th>

                                    <!-- Pregnant Women -->
                                    <th class="border border-gray-400 px-1 py-1 text-center">AG1</th>
                                    <th class="border border-gray-400 px-1 py-1 text-center">AH1</th>
                                    <th class="border border-gray-400 px-1 py-1 text-center">AI1</th>

                                    <!-- Older Persons -->
                                    <th class="border border-gray-400 px-1 py-1 text-center">AJ1</th>
                                    <th class="border border-gray-400 px-1 py-1 text-center">AK1</th>

                                    <!-- TOTAL ALL AGES -->
                                    <th class="border border-gray-400 px-1 py-1 text-center">AL1</th>
                                    <th class="border border-gray-400 px-1 py-1 text-center">AM1</th>

                                    <!-- GRAND TOTAL -->
                                    <th class="border border-gray-400 px-1 py-1 text-center">AN1</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                // Define the sections and rows based on the Excel structure
                                $sections = [
                                    'NO. OF PERSON ATTENDED' => ['row_class' => ''],
                                    'NO. OF PERSON EXAMINED' => ['row_class' => ''],
                                    'A. MEDICAL HISTORY STATUS' => [
                                        'row_class' => 'section-header',
                                        'subrows' => [
                                            ['title' => '1. Total No. with Allergies', 'type' => 'regular'],
                                            ['title' => '2. Total No. with Hypertension/ CVA', 'type' => 'regular'],
                                            ['title' => '3. Total No. with Diabetes Mellitus', 'type' => 'regular'],
                                            ['title' => '4. Total No. with Blood Disorders', 'type' => 'regular'],
                                            ['title' => '5. Total No. with Cardiovascular/Heart Diseases', 'type' => 'regular'],
                                            ['title' => '6. Total No. with Thyroid Disorders', 'type' => 'regular'],
                                            ['title' => '7. Total No. with Hepatitis', 'type' => 'regular'],
                                            ['title' => '8. Total No. with Malignancy', 'type' => 'regular'],
                                            ['title' => '9. Total No. with History of Previous Hospitalization', 'type' => 'regular'],
                                            ['title' => '10. Total No. with Blood Transfusion', 'type' => 'regular'],
                                            ['title' => '11. Total No. with Tattoo', 'type' => 'regular']
                                        ]
                                    ],
                                    'B. DIETARY / SOCIAL HISTORY STATUS' => [
                                        'row_class' => 'section-header',
                                        'subrows' => [
                                            ['title' => '1. Total No. of Sugar Sweetened Beverages/Food Drinker/Eater', 'type' => 'regular'],
                                            ['title' => '2. Total No. of Alcohol Drinker', 'type' => 'regular'],
                                            ['title' => '3. Total No. of Tobacco User', 'type' => 'regular'],
                                            ['title' => '4. Total No. of Betel Nut Chewer', 'type' => 'regular']
                                        ]
                                    ],
                                    'C. ORAL HEALTH STATUS' => [
                                        'row_class' => 'section-header',
                                        'subrows' => [
                                            ['title' => '1. Total No. with Dental Caries', 'type' => 'regular'],
                                            ['title' => '2. Total No. with Gingivitis', 'type' => 'regular'],
                                            ['title' => '3. Total No. with Periodontal Disease', 'type' => 'regular'],
                                            ['title' => '4. Total No. with Oral Debris', 'type' => 'regular'],
                                            ['title' => '5. Total No. with Calculus', 'type' => 'regular'],
                                            ['title' => '6. Total No. with Dento Facial Anomalies (cleft lip/palate. Malocclusion, etc)', 'type' => 'regular'],
                                            [
                                                'title' => '7. Total (d/f)',
                                                'type' => 'subsection',
                                                'subrows' => [
                                                    ['title' => 'a. Total decayed (d)', 'type' => 'regular'],
                                                    ['title' => 'b. Total filled (f)', 'type' => 'regular']
                                                ]
                                            ],
                                            [
                                                'title' => '8. Total (D/M/F)',
                                                'type' => 'subsection',
                                                'subrows' => [
                                                    ['title' => 'a. Total Decayed (D)', 'type' => 'regular'],
                                                    ['title' => 'b. Total Missing (M)', 'type' => 'regular'],
                                                    ['title' => 'c. Total Filled (F)', 'type' => 'regular']
                                                ]
                                            ]
                                        ]
                                    ],
                                    'D. SERVICES RENDERED' => [
                                        'row_class' => 'section-header',
                                        'subrows' => [
                                            ['title' => '1. No. Given OP / Scaling', 'type' => 'regular'],
                                            ['title' => '2. No. Given Permanent Fillings', 'type' => 'regular'],
                                            ['title' => '3. No. Given Temporary Fillings', 'type' => 'regular'],
                                            ['title' => '4. No. Given Extraction', 'type' => 'regular'],
                                            ['title' => '5. No. Given Gum Treatment', 'type' => 'regular'],
                                            ['title' => '6. No. Given Sealant', 'type' => 'regular'],
                                            ['title' => '7. No. Completed Fluoride Therapy', 'type' => 'regular'],
                                            ['title' => '8. No. Given Post-Operative Treatment', 'type' => 'regular'],
                                            ['title' => '9. No. of Patient with Oral Abscess Drained', 'type' => 'regular'],
                                            ['title' => '10. No. Given Other Services (Atraumatic Restorative Treatment, Relief of Pain, etc.)', 'type' => 'regular'],
                                            ['title' => '11. No. Referred', 'type' => 'regular'],
                                            ['title' => '12. No. Given Counseling / Education on Tobacco, Oral Health, Diet, Instruction on Infants Oral Health Care, Advise on Exclusive Breastfeeding, etc.', 'type' => 'regular'],
                                            ['title' => '13. No. of Under Six Children Completed Toothbrush Drill / One-on-One Supervised Toothbrushing', 'type' => 'regular']
                                        ]
                                    ],
                                    'E. NO. OF ORALLY FIT CHILDREN (OFC)' => [
                                        'row_class' => 'section-header',
                                        'subrows' => [
                                            ['title' => '1. OFC Upon Oral Examination', 'type' => 'regular'],
                                            ['title' => '2. OFC Upon Complete Oral Rehabilitation', 'type' => 'regular']
                                        ]
                                    ]
                                ];

                                // Generate table rows
                                $rowIndex = 0;
                                foreach ($sections as $sectionTitle => $sectionData) {
                                    $isSection = isset($sectionData['subrows']);
                                    $rowClass = $sectionData['row_class'] ?? '';

                                    if ($isSection) {
                                        // Section header row
                                        echo "<tr class='$rowClass'>";
                                        echo "<td class='border border-gray-400 px-2 py-1 font-bold sticky-header'>$sectionTitle</td>";
                                        // Add empty cells for all data columns
                                        for ($i = 0; $i < 41; $i++) {
                                            echo "<td class='border border-gray-400 px-1 py-1'></td>";
                                        }
                                        echo "</tr>";

                                        // Sub-rows
                                        foreach ($sectionData['subrows'] as $subrow) {
                                            if ($subrow['type'] === 'subsection') {
                                                // This is a sub-section with its own subrows
                                                $subSectionTitle = $subrow['title'];

                                                echo "<tr>";
                                                echo "<td class='border border-gray-400 px-2 py-1 sticky-header'>$subSectionTitle</td>";
                                                // Add empty cells for all data columns
                                                for ($i = 0; $i < 41; $i++) {
                                                    echo "<td class='border border-gray-400 px-1 py-1'></td>";
                                                }
                                                echo "</tr>";

                                                // Sub-subrows
                                                foreach ($subrow['subrows'] as $subSubrow) {
                                                    $rowIndex++;
                                                    echo "<tr>";
                                                    echo "<td class='border border-gray-400 px-4 py-1 sticky-header'>{$subSubrow['title']}</td>";
                                                    // Generate input cells for all columns
                                                    for ($col = 1; $col <= 41; $col++) {
                                                        $cellId = "cell_{$rowIndex}_{$col}";
                                                        echo "<td class='border border-gray-400 px-1 py-1 text-center'>";
                                                        echo "<input type='number' id='$cellId' name='$cellId' value='0' min='0' class='w-full text-center border-none focus:ring-1 focus:ring-blue-500' onchange='calculateTotals()'>";
                                                        echo "</td>";
                                                    }
                                                    echo "</tr>";
                                                }
                                            } else {
                                                // Regular sub-row
                                                $rowIndex++;
                                                echo "<tr>";
                                                echo "<td class='border border-gray-400 px-2 py-1 sticky-header'>{$subrow['title']}</td>";
                                                // Generate input cells for all columns
                                                for ($col = 1; $col <= 41; $col++) {
                                                    $cellId = "cell_{$rowIndex}_{$col}";
                                                    echo "<td class='border border-gray-400 px-1 py-1 text-center'>";
                                                    echo "<input type='number' id='$cellId' name='$cellId' value='0' min='0' class='w-full text-center border-none focus:ring-1 focus:ring-blue-500' onchange='calculateTotals()'>";
                                                    echo "</td>";
                                                }
                                                echo "</tr>";
                                            }
                                        }
                                    } else {
                                        // Regular row (not a section)
                                        $rowIndex++;
                                        echo "<tr class='$rowClass'>";
                                        echo "<td class='border border-gray-400 px-2 py-1 sticky-header'>$sectionTitle</td>";
                                        // Generate input cells for all columns
                                        for ($col = 1; $col <= 41; $col++) {
                                            $cellId = "cell_{$rowIndex}_{$col}";
                                            echo "<td class='border border-gray-400 px-1 py-1 text-center'>";
                                            echo "<input type='number' id='$cellId' name='$cellId' value='0' min='0' class='w-full text-center border-none focus:ring-1 focus:ring-blue-500' onchange='calculateTotals()'>";
                                            echo "</td>";
                                        }
                                        echo "</tr>";
                                    }
                                }
                                ?>
                            </tbody>
                        </table>

                        <div class="mt-4 flex justify-end space-x-2">
                            <button type="button" onclick="resetForm()" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2">
                                Reset
                            </button>
                            <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2">
                                Save Report
                            </button>
                        </div>
                    </form>
                </div>
            </section>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/flowbite@3.1.2/dist/flowbite.min.js"></script>

    <script>
        // Update report period display
        function updateReportPeriod() {
            const monthSelect = document.getElementById('report-month');
            const yearInput = document.getElementById('report-year');
            const monthNames = [
                'January', 'February', 'March', 'April', 'May', 'June',
                'July', 'August', 'September', 'October', 'November', 'December'
            ];

            const monthName = monthNames[parseInt(monthSelect.value) - 1];
            const year = yearInput.value;

            document.getElementById('report-period-display').textContent = `${monthName}/FY${year}`;
            document.getElementById('form-month').value = monthSelect.value;
            document.getElementById('form-year').value = year;
        }

        // Load report data (placeholder - implement actual API call)
        function loadReportData() {
            updateReportPeriod();
            // Here you would make an AJAX call to load existing data for the selected period
            alert('Loading report data for selected period...');
            // calculateTotals(); // Recalculate after loading data
        }

        // Calculate totals (placeholder - implement actual calculation logic)
        function calculateTotals() {
            // Implement calculation logic based on Excel formulas
            console.log('Calculating totals...');
        }

        // Generate report (download/print)
        function generateReport() {
            updateReportPeriod();
            alert('Generating report for download...');
            // Implement report generation logic
        }

        // Reset form
        function resetForm() {
            if (confirm('Are you sure you want to reset all form data?')) {
                document.getElementById('oralHealthForm').reset();
                calculateTotals();
            }
        }

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            updateReportPeriod();
            document.getElementById('report-month').addEventListener('change', updateReportPeriod);
            document.getElementById('report-year').addEventListener('input', updateReportPeriod);
        });

        // Inactivity timer
        let inactivityTime = 600000; // 10 minutes in ms
        let logoutTimer;

        function resetTimer() {
            clearTimeout(logoutTimer);
            logoutTimer = setTimeout(() => {
                alert("You've been logged out due to 10 minutes of inactivity.");
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