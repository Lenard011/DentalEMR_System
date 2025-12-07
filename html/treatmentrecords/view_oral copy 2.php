<?php
session_start();
date_default_timezone_set('Asia/Manila');

// REQUIRE userId parameter for each page
// Example usage: dashboard.php?uid=5
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

// Get patient ID from URL
$patientId = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Verify patient exists
if ($patientId > 0) {
    $stmt = $conn->prepare("SELECT patient_id, firstname, middlename, surname FROM patients WHERE patient_id = ?");
    $stmt->bind_param("i", $patientId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        $patientId = 0; // Reset if patient doesn't exist
    }
    $stmt->close();
}

$conn->close();
?>
<!doctype html>
<html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Treatment Records - Oral Health</title>
    <!-- <link href="../css/style.css" rel="stylesheet"> -->
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
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
                            <span class="block text-sm font-semibold text-gray-900 dark:text-white">
                                <?php
                                echo htmlspecialchars(
                                    !empty($loggedUser['name'])
                                        ? $loggedUser['name']
                                        : ($loggedUser['email'] ?? 'User')
                                );
                                ?>
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
                                    class="block py-2 px-4 text-sm hover:bg-gray-100 dark:hover:bg-gray-600 dark:text-gray-400 dark:hover:text-white">My
                                    profile</a>
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
                        <ul id="dropdown-pages" class="visible py-2 space-y-2">
                            <li>
                                <a href="#"
                                    class="pl-11 flex items-center p-2 text-base font-medium text-blue-600 rounded-lg dark:text-blue bg-blue-100  dark:hover:bg-blue-700 group">Treatment
                                    Records</a>
                            </li>
                            <li>
                                <a href="../addpatienttreatment/patienttreatment.php?uid=<?php echo $userId; ?>"
                                    class="flex items-center p-2 pl-11 w-full text-base font-medium text-gray-900 rounded-lg transition duration-75 group hover:bg-gray-100 dark:text-white dark:hover:bg-gray-700">Add
                                    Patient Treatment</a>
                            </li>
                        </ul>
                    </li>
                </ul>
                <ul class="pt-5 mt-5 space-y-2 border-t border-gray-200 dark:border-gray-700">
                    <li>
                        <a href="../reports/targetclientlist.php?uid=<?php echo $userId; ?>"
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
                        <a href="../reports/mho_ohp.php?uid=<?php echo $userId; ?>"
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
                        <a href="../reports/oralhygienefindings.php?uid=<?php echo $userId; ?>"
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

        <header class="md:ml-64 pt-13">
            <nav class="bg-white border-gray-200 dark:bg-gray-800 w-full drop-shadow-sm pb-2">
                <div class="flex flex-col justify-between items-center mx-auto px-2 sm:px-4">
                    <!-- Top Section: Back Button, Title, Print Button -->
                    <div class="flex items-center justify-between w-full py-2">
                        <!-- Back Button -->
                        <div class="relative group inline-block">
                            <button type="button" onclick="backmain()" class="cursor-pointer">
                                <svg class="w-6 h-6 sm:w-8 sm:h-8 text-blue-800 dark:text-white" aria-hidden="true"
                                    xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none"
                                    viewBox="0 0 24 24">
                                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"
                                        stroke-width="2.5" d="M5 12h14M5 12l4-4m-4 4 4 4" />
                                </svg>
                            </button>
                            <!-- Tooltip -->
                            <span class="absolute left-1/4 -translate-x-1/4 hidden group-hover:block 
                             bg-gray-100/50 text-gray-900 text-sm px-2 py-1 rounded-sm shadow-sm whitespace-nowrap">
                                Go back
                            </span>
                        </div>

                        <!-- Title -->
                        <p class="text-lg sm:text-xl font-semibold px-2 sm:px-4 text-gray-900 dark:text-white text-center flex-1">
                            Patient Treatment Record
                        </p>

                        <!-- Print Button -->
                        <a href="" id="printdLink"
                            class="text-white cursor-pointer flex flex-row items-center justify-center gap-1 bg-blue-700 hover:bg-blue-800 font-medium rounded-sm text-xs px-2 sm:px-3 py-1.5 sm:py-2 dark:bg-primary-600 dark:hover:bg-primary-700 focus:outline-none dark:focus:ring-primary-800 min-w-[60px]">
                            <svg class="w-4 h-4 text-white" aria-hidden="true"
                                xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor"
                                viewBox="0 0 24 24">
                                <path fill-rule="evenodd"
                                    d="M8 3a2 2 0 0 0-2 2v3h12V5a2 2 0 0 0-2-2H8Zm-3 7a2 2 0 0 0-2 2v5a2 2 0 0 0 2 2h1v-4a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v4h1a2 2 0 0 0 2-2v-5a2 2 0 0 0-2-2H5Zm4 11a1 1 0 0 1-1-1v-4h8v4a1 1 0 0 1-1 1H9Z"
                                    clip-rule="evenodd" />
                            </svg>
                            <span class="hidden sm:inline">Print</span>
                        </a>
                    </div>

                    <!-- Navigation Tabs -->
                    <div class="w-full border-t border-gray-200 dark:border-gray-700 pt-2">
                        <ul class="flex flex-col sm:flex-row justify-center font-medium w-full sm:space-x-4 sm:space-y-0 space-y-2">
                            <li class="w-full sm:w-auto">
                                <a href="#" id="patientInfoLink"
                                    class="block py-2 px-3 text-gray-700 border-b font-semibold border-gray-100 hover:bg-gray-50 lg:hover:bg-transparent lg:border-0 lg:hover:text-primary-700 lg:p-0 dark:text-gray-400 lg:dark:hover:text-white dark:hover:bg-gray-700 dark:hover:text-white lg:dark:hover:bg-transparent dark:border-gray-700 text-center sm:text-left">
                                    Patient Information
                                </a>
                            </li>
                            <li class="w-full sm:w-auto">
                                <a href="#"
                                    class="block py-2 px-3 text-blue-800 border-b-2 font-semibold border-blue-800 hover:bg-gray-50 lg:hover:bg-transparent lg:border-0 lg:hover:text-primary-700 lg:p-0 dark:text-blue-400 lg:dark:hover:text-white dark:hover:bg-gray-700 dark:hover:text-white lg:dark:hover:bg-transparent dark:border-gray-700 text-center sm:text-left">
                                    Oral Health Condition
                                </a>
                            </li>
                            <li class="w-full sm:w-auto">
                                <a href="#" id="servicesRenderedLink"
                                    class="block py-2 px-3 text-gray-700 border-b font-semibold border-gray-100 hover:bg-gray-50 lg:hover:bg-transparent lg:border-0 lg:hover:text-primary-700 lg:p-0 dark:text-gray-400 lg:dark:hover:text-white dark:hover:bg-gray-700 dark:hover:text-white lg:dark:hover:bg-transparent dark:border-gray-700 text-center sm:text-left">
                                    Record of Services Rendered
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
            </nav>
        </header>

        <main class="p-2 md:ml-64 h-auto pt-2">
            <section class="bg-white dark:bg-gray-900 p-3 sm:p-4 rounded-lg">
                <!-- Patient Name and Add Button -->
                <div class="items-center justify-between flex flex-col sm:flex-row mb-4 gap-2">
                    <p id="patientName" class="italic text-base sm:text-lg font-medium text-gray-900 dark:text-white">
                        Loading ...
                    </p>
                    <button type="button" id="addOHCbtn" onclick="openOHCModal()"
                        class="text-white cursor-pointer flex flex-row items-center justify-center gap-1 bg-blue-700 hover:bg-blue-800 font-medium rounded-sm text-xs px-3 py-2 w-full sm:w-auto">
                        <svg class="h-3.5 w-3.5" fill="currentColor" viewbox="0 0 20 20"
                            xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                            <path clip-rule="evenodd" fill-rule="evenodd"
                                d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" />
                        </svg>
                        Add Oral Health Record
                    </button>
                </div>

                <!-- Oral Examination Section -->
                <div class="mx-auto mb-4 p-3 sm:p-4 bg-white rounded-lg shadow dark:border shadow-stone-300 drop-shadow-sm dark:bg-gray-800 dark:border-gray-950">
                    <div class="items-center justify-between flex flex-row mb-3">
                        <p class="text-base font-normal text-gray-950 dark:text-white">Oral Examination</p>
                    </div>

                    <!-- Date Selector -->
                    <div class="mb-4">
                        <div class="flex flex-col sm:flex-row items-start sm:items-center gap-2">
                            <label for="dataSelect" class="text-sm sm:text-base text-gray-900 dark:text-white">Select Examination Date:</label>
                            <select id="dataSelect"
                                class="bg-gray-50 border border-gray-300 text-gray-900 text-xs sm:text-sm rounded-sm w-full sm:w-48 p-2 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white"
                                onchange="loadSelectedRecord()">
                                <option value="" selected disabled>Loading records...</option>
                            </select>
                        </div>
                    </div>

                    <!-- Status Message -->
                    <div id="loadingStatus" class="mb-4 text-center text-sm text-gray-600 dark:text-gray-400 hidden">
                        Loading data...
                    </div>
                    <div id="noRecordsMessage" class="mb-4 text-center text-gray-600 dark:text-gray-400 hidden">
                        No oral health records found for this patient.
                    </div>

                    <!-- Oral Data Container -->
                    <div id="oralDataContainer" class="space-y-4">
                        <!-- Data will be loaded here -->
                    </div>
                </div>

                <!-- Next Button -->
                <div class="flex justify-end mt-4">
                    <button type="button" onclick="next()"
                        class="text-white cursor-pointer inline-flex items-center justify-center bg-blue-700 hover:bg-blue-800 focus:outline-none focus:ring-blue-300 font-medium rounded-lg text-sm px-4 py-2 w-full sm:w-auto dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800">
                        Next
                    </button>
                </div>
            </section>
        </main>

        <!-- Modal  -->
        <div id="ohcModal" tabindex="-1" aria-hidden="true"
            class="fixed inset-0 hidden flex justify-center items-center z-50 bg-gray-600/50">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg w-full max-w-5xl p-4 m-2 max-h-[90vh] overflow-y-auto">
                <div class="flex flex-row justify-between items-center mb-4">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Add Oral Health Condition</h2>
                    <button type="button" id="cancelMedicalBtn"
                        class="relative cursor-pointer text-gray-500 hover:text-gray-800 dark:hover:text-white"
                        onclick="closeOHCModal()">
                        ✕
                    </button>
                </div>
                <form id="ohcForm" class="space-y-4">
                    <input type="hidden" name="patient_id" id="patient_id" value="<?php echo $patientId; ?>">

                    <div class="grid gap-2 mb-4">
                        <div class="mb-3">
                            <p class="text-14 font-semibold text-gray-900 dark:text-white">
                                A. Check (✓) if present (✗) if absent
                            </p>
                        </div>
                        <div class="flex justify-between col-span-2">
                            <div class="flex flex-col gap-2">
                                <div class="flex flex-row justify-between w-120 items-center">
                                    <label for="ofc"
                                        class="flex text-sm font-medium text-gray-900 dark:text-white">Orally Fit Child
                                        (OFC):</label>
                                    <input type="text" name="orally_fit_child" id="orally_fit_child"
                                        class="bg-gray-50 border border-gray-300 text-gray-900 text-center text-xs rounded-sm focus:ring-primary-600 focus:border-primary-600 block w-50 p-1 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500"
                                        readonly onclick="toggleCheck(this)" placeholder="Click" />
                                </div>
                                <div class="flex flex-row justify-between w-120 items-center">
                                    <label for="dental_caries"
                                        class="flex text-sm font-medium text-gray-900 dark:text-white">Dental
                                        Caries:</label>
                                    <input type="text" name="dental_caries" id="dental_caries"
                                        class="bg-gray-50 border border-gray-300 text-center text-gray-900 text-xs rounded-sm focus:ring-primary-600 focus:border-primary-600 block w-50 p-1 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500"
                                        readonly onclick="toggleCheck(this)" placeholder="Click" />
                                </div>
                                <div class="flex flex-row justify-between w-120 items-center">
                                    <label for="gingivitis"
                                        class="flex text-sm font-medium text-gray-900 dark:text-white">Gingivitis:</label>
                                    <input type="text" name="gingivitis" id="gingivitis"
                                        class="bg-gray-50 border border-gray-300 text-center text-gray-900 text-xs rounded-sm focus:ring-primary-600 focus:border-primary-600 block w-50 p-1 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500"
                                        readonly onclick="toggleCheck(this)" placeholder="Click" />
                                </div>
                                <div class="flex flex-row justify-between w-120 items-center">
                                    <label for="periodontal_disease"
                                        class="flex text-sm font-medium text-gray-900 dark:text-white">Periodontal
                                        Disease:</label>
                                    <input type="text" name="periodontal_disease" id="periodontal_disease"
                                        class="bg-gray-50 border border-gray-300 text-center text-gray-900 text-xs rounded-sm focus:ring-primary-600 focus:border-primary-600 block w-50 p-1 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500"
                                        readonly onclick="toggleCheck(this)" placeholder="Click" />
                                </div>
                                <div class="flex flex-row justify-between w-120">
                                    <label for="others"
                                        class="flex text-sm font-medium text-gray-900 dark:text-white">Others
                                        (supernumerary/mesiodens, <br>malocclusions, etc.):</label>
                                    <input type="text" name="others" id="others"
                                        class="bg-gray-50 border border-gray-300 text-center text-gray-900 text-xs rounded-sm focus:ring-primary-600 focus:border-primary-600 block w-50 p-1 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500"
                                        readonly onclick="toggleCheck(this)" placeholder="Click" />
                                </div>
                            </div>
                            <div class="flex flex-col gap-2">
                                <div class="flex flex-row justify-between w-120 items-center">
                                    <label for="debris"
                                        class="flex text-sm font-medium text-gray-900 dark:text-white">Debris:</label>
                                    <input type="text" name="debris" id="debris"
                                        class="bg-gray-50 border border-gray-300 text-center text-gray-900 text-xs rounded-sm focus:ring-primary-600 focus:border-primary-600 block w-50 p-1 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500"
                                        readonly onclick="toggleCheck(this)" placeholder="Click" />
                                </div>
                                <div class="flex flex-row justify-between w-120 items-center">
                                    <label for="calculus"
                                        class="flex text-sm font-medium text-gray-900 dark:text-white">Calculus:</label>
                                    <input type="text" name="calculus" id="calculus"
                                        class="bg-gray-50 border border-gray-300 text-center text-gray-900 text-xs rounded-sm focus:ring-primary-600 focus:border-primary-600 block w-50 p-1 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500"
                                        readonly onclick="toggleCheck(this)" placeholder="Click" />
                                </div>
                                <div class="flex flex-row justify-between w-120 items-center">
                                    <label for="abnormal_growth"
                                        class="flex text-sm font-medium text-gray-900 dark:text-white">Abnormal
                                        Growth:</label>
                                    <input type="text" name="abnormal_growth" id="abnormal_growth"
                                        class="bg-gray-50 border border-gray-300 text-center text-gray-900 text-xs rounded-sm focus:ring-primary-600 focus:border-primary-600 block w-50 p-1 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500"
                                        readonly onclick="toggleCheck(this)" placeholder="Click" />
                                </div>
                                <div class="flex flex-row justify-between w-120 items-center">
                                    <label for="cleft_palate"
                                        class="flex text-sm font-medium text-gray-900 dark:text-white">Cleft Lip /
                                        Palate:</label>
                                    <input type="text" name="cleft_palate" id="cleft_palate"
                                        class="bg-gray-50 border border-gray-300 text-center text-gray-900 text-xs rounded-sm focus:ring-primary-600 focus:border-primary-600 block w-50 p-1 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500"
                                        readonly onclick="toggleCheck(this)" placeholder="Click" />
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <p class="text-14 font-semibold text-gray-900 dark:text-white">B. Indicate Number</p>
                        </div>
                        <div class="flex justify-between col-span-2">
                            <div class="flex flex-col gap-2">
                                <div class="flex flex-row justify-between w-120 items-center">
                                    <label class="flex text-sm font-medium text-gray-900 dark:text-white">No. of Perm.
                                        Teeth Present:</label>
                                    <input type="number" name="perm_teeth_present" id="perm_teeth_present"
                                        class="bg-gray-50 border border-gray-300 text-gray-900 text-xs rounded-sm focus:ring-primary-600 focus:border-primary-600 block w-50 p-1 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500" />
                                </div>
                                <div class="flex flex-row justify-between w-120 items-center">
                                    <label class="flex text-sm font-medium text-gray-900 dark:text-white">No. of Perm.
                                        Sound Teeth:</label>
                                    <input type="number" name="perm_sound_teeth" id="perm_sound_teeth"
                                        class="bg-gray-50 border border-gray-300 text-gray-900 text-xs rounded-sm focus:ring-primary-600 focus:border-primary-600 block w-50 p-1 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500" />
                                </div>
                                <div class="flex flex-row justify-between w-120 items-center">
                                    <label class="flex text-sm font-medium text-gray-900 dark:text-white">No. of Decayed
                                        Teeth(D):</label>
                                    <input type="number" name="perm_decayed_teeth_d" id="perm_decayed_teeth_d"
                                        oninput="calcTotals()"
                                        class="bg-gray-50 border border-gray-300 text-gray-900 text-xs rounded-sm focus:ring-primary-600 focus:border-primary-600 block w-50 p-1 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500" />
                                </div>
                                <div class="flex flex-row justify-between w-120 items-center">
                                    <label class="flex text-sm font-medium text-gray-900 dark:text-white">No. of Missing
                                        Teeth(M):</label>
                                    <input type="number" name="perm_missing_teeth_m" id="perm_missing_teeth_m"
                                        oninput="calcTotals()"
                                        class="bg-gray-50 border border-gray-300 text-gray-900 text-xs rounded-sm focus:ring-primary-600 focus:border-primary-600 block w-50 p-1 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500" />
                                </div>
                                <div class="flex flex-row justify-between w-120 items-center">
                                    <label class="flex text-sm font-medium text-gray-900 dark:text-white">No. of Filled
                                        Teeth(F):</label>
                                    <input type="number" name="perm_filled_teeth_f" id="perm_filled_teeth_f"
                                        oninput="calcTotals()"
                                        class="bg-gray-50 border border-gray-300 text-gray-900 text-xs rounded-sm focus:ring-primary-600 focus:border-primary-600 block w-50 p-1 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500" />
                                </div>
                                <div class="flex flex-row justify-between w-120 items-center">
                                    <label class="flex text-sm font-medium text-gray-900 dark:text-white">Total DMF
                                        Teeth:</label>
                                    <input type="number" name="perm_total_dmf" id="perm_total_dmf"
                                        placeholder="Total DMF Teeth" disabled
                                        class="bg-gray-50 border border-gray-300 text-gray-900 text-xs rounded-sm focus:ring-primary-600 focus:border-primary-600 block w-50 p-1 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500" />
                                </div>
                            </div>

                            <div class="flex flex-col gap-2">
                                <div class="flex flex-row justify-between w-120 items-center">
                                    <label class="flex text-sm font-medium text-gray-900 dark:text-white">No. of Temp.
                                        Teeth Present:</label>
                                    <input type="number" name="temp_teeth_present" id="temp_teeth_present"
                                        class="bg-gray-50 border border-gray-300 text-gray-900 text-xs rounded-sm focus:ring-primary-600 focus:border-primary-600 block w-50 p-1 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500" />
                                </div>
                                <div class="flex flex-row justify-between w-120 items-center">
                                    <label class="flex text-sm font-medium text-gray-900 dark:text-white">No. of Temp.
                                        Sound Teeth:</label>
                                    <input type="number" name="temp_sound_teeth" id="temp_sound_teeth"
                                        class="bg-gray-50 border border-gray-300 text-gray-900 text-xs rounded-sm focus:ring-primary-600 focus:border-primary-600 block w-50 p-1 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500" />
                                </div>
                                <div class="flex flex-row justify-between w-120 items-center">
                                    <label class="flex text-sm font-medium text-gray-900 dark:text-white">No. of Decayed
                                        Teeth(d):</label>
                                    <input type="number" name="temp_decayed_teeth_d" id="temp_decayed_teeth_d"
                                        oninput="calcTotals()"
                                        class="bg-gray-50 border border-gray-300 text-gray-900 text-xs rounded-sm focus:ring-primary-600 focus:border-primary-600 block w-50 p-1 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500" />
                                </div>
                                <div class="flex flex-row justify-between w-120 items-center">
                                    <label class="flex text-sm font-medium text-gray-900 dark:text-white">No. of Filled
                                        Teeth(f):</label>
                                    <input type="number" name="temp_filled_teeth_f" id="temp_filled_teeth_f"
                                        oninput="calcTotals()"
                                        class="bg-gray-50 border border-gray-300 text-gray-900 text-xs rounded-sm focus:ring-primary-600 focus:border-primary-600 block w-50 p-1 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500" />
                                </div>
                                <div class="flex flex-row justify-between w-120 items-center">
                                    <label class="flex text-sm font-medium text-gray-900 dark:text-white">Total df
                                        Teeth:</label>
                                    <input type="number" name="temp_total_df" id="temp_total_df"
                                        placeholder="Total df Teeth" disabled
                                        class="bg-gray-50 border border-gray-300 text-gray-900 text-xs rounded-sm focus:ring-primary-600 focus:border-primary-600 block w-50 p-1 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500" />
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end gap-2">
                        <button type="button" onclick="saveOHC()"
                            class="text-white justify-center cursor-pointer inline-flex items-center bg-blue-700 hover:bg-blue-800 focus:outline-none focus:ring-blue-300 font-medium rounded-lg text-sm p-1 w-18 dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800">
                            Save
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- <script src="../node_modules/flowbite/dist/flowbite.min.js"></script> -->
    <script src="https://cdn.jsdelivr.net/npm/flowbite@3.1.2/dist/flowbite.min.js"></script>
    <script src="../js/tailwind.config.js"></script>

    <!-- Notice Element for Offline Sync -->
    <div id="notice" style="display: none;"></div>

    <script>
        // Global variables
        let oralRecords = [];
        let currentPatientId = <?php echo $patientId; ?>;
        let currentUserId = <?php echo $userId; ?>;
        let patientName = '';

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            initializePage();
        });

        function initializePage() {
            if (!currentPatientId || currentPatientId <= 0) {
                showAlert('Missing patient ID. Please select a patient first.', 'error');
                if (currentUserId) {
                    setTimeout(() => {
                        window.location.href = `treatmentrecords.php?uid=${currentUserId}`;
                    }, 2000);
                }
                return;
            }

            // Set navigation links
            updateNavigationLinks();

            // Load patient name first
            loadPatientInfo();

            // Load oral records
            loadPatientOralRecords();
        }

        function updateNavigationLinks() {
            // Set patient info link
            const patientInfoLink = document.getElementById("patientInfoLink");
            if (patientInfoLink && currentPatientId && currentUserId) {
                patientInfoLink.href = `view_info.php?uid=${currentUserId}&id=${currentPatientId}`;
            }

            // Set services rendered link
            const servicesRenderedLink = document.getElementById("servicesRenderedLink");
            if (servicesRenderedLink && currentPatientId && currentUserId) {
                servicesRenderedLink.href = `view_record.php?uid=${currentUserId}&id=${currentPatientId}`;
            }

            // Set print link
            const printdLink = document.getElementById("printdLink");
            if (printdLink && currentPatientId && currentUserId) {
                printdLink.href = `print.php?uid=${currentUserId}&id=${currentPatientId}`;
            }
        }

        async function loadPatientInfo() {
            try {
                const response = await fetch(`/dentalemr_system/php/patients/get_patient.php?id=${currentPatientId}`);

                if (response.ok) {
                    const result = await response.json();

                    if (result.success && result.data) {
                        const patient = result.data;
                        patientName = `${patient.firstname} ${patient.middlename ? patient.middlename + '. ' : ''}${patient.surname}`;

                        // Update patient name display
                        const patientNameElement = document.getElementById("patientName");
                        if (patientNameElement) {
                            patientNameElement.textContent = patientName;
                            patientNameElement.classList.remove('italic');
                        }
                    }
                }
            } catch (error) {
                console.warn('Could not load patient info:', error);
                const patientNameElement = document.getElementById("patientName");
                if (patientNameElement) {
                    patientNameElement.textContent = 'Patient ID: ' + currentPatientId;
                    patientNameElement.classList.remove('italic');
                }
            }
        }

        async function loadPatientOralRecords() {
            const dateSelect = document.getElementById("dataSelect");
            const loadingStatus = document.getElementById("loadingStatus");
            const noRecordsMessage = document.getElementById("noRecordsMessage");

            if (!currentPatientId || currentPatientId <= 0) {
                showNoRecordsMessage();
                return;
            }

            try {
                // Show loading state
                showLoading(true);

                if (dateSelect) {
                    dateSelect.innerHTML = '<option value="" selected disabled>Loading records...</option>';
                    dateSelect.disabled = true;
                }

                // Fetch data from API
                const apiUrl = `/dentalemr_system/php/treatmentrecords/view_oral_api.php?id=${currentPatientId}`;
                const response = await fetch(apiUrl);

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const result = await response.json();

                // Hide loading
                showLoading(false);

                // Check if we have data
                if (!result.success) {
                    // If it's a "no records found" message
                    if (result.message && result.message.includes('No records')) {
                        showNoRecordsMessage();
                    } else {
                        showAlert('Error: ' + (result.message || 'Unknown error'), 'error');
                    }
                    return;
                }

                const data = result.data || [];

                if (!Array.isArray(data)) {
                    throw new Error('Invalid data format received from server');
                }

                if (data.length === 0) {
                    showNoRecordsMessage();
                    return;
                }

                // Store records globally
                oralRecords = data;

                // Populate date dropdown
                populateDateDropdown(data);

                // Load first record by default
                if (data.length > 0 && data[0].id) {
                    await loadOralRecord(data[0].id);
                }

            } catch (error) {
                console.error('Error loading oral records:', error);
                showLoading(false);
                showAlert('Failed to load records. Please try again.', 'error');
                showNoRecordsMessage();
            }
        }

        function populateDateDropdown(records) {
            const dateSelect = document.getElementById("dataSelect");
            const noRecordsMessage = document.getElementById("noRecordsMessage");

            if (!dateSelect) return;

            // Clear existing options
            dateSelect.innerHTML = '';

            if (!Array.isArray(records) || records.length === 0) {
                if (noRecordsMessage) noRecordsMessage.classList.remove('hidden');
                dateSelect.disabled = true;
                dateSelect.innerHTML = '<option value="" selected disabled>No records available</option>';
                return;
            }

            // Hide no records message
            if (noRecordsMessage) noRecordsMessage.classList.add('hidden');
            dateSelect.disabled = false;

            // Add options for each record
            records.forEach((record, index) => {
                const option = document.createElement('option');
                option.value = record.id;

                // Format date nicely
                let formattedDate = 'Date not available';
                try {
                    const date = record.created_at ? new Date(record.created_at) : new Date();
                    formattedDate = date.toLocaleDateString('en-PH', {
                        year: 'numeric',
                        month: 'short',
                        day: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit'
                    });
                } catch (e) {
                    // Keep default date
                }

                // Add record number for clarity
                const recordNumber = records.length - index;
                option.textContent = `Record #${recordNumber} - ${formattedDate}`;

                // Store full record data for offline use
                try {
                    option.dataset.record = JSON.stringify(record);
                } catch (e) {
                    // Ignore cache errors
                }

                // Select first item by default
                if (index === 0) {
                    option.selected = true;
                }

                dateSelect.appendChild(option);
            });

            // Cache the data for offline use
            try {
                localStorage.setItem(`oral_records_${currentPatientId}`, JSON.stringify(records));
            } catch (e) {
                // Ignore cache errors
            }
        }

        async function loadSelectedRecord() {
            const dateSelect = document.getElementById("dataSelect");
            const selectedValue = dateSelect.value;

            if (!selectedValue) {
                return;
            }

            // Show loading for record
            const loadingStatus = document.getElementById("loadingStatus");
            if (loadingStatus) loadingStatus.classList.remove('hidden');

            // Try to get data from the option's dataset first (for offline use)
            const selectedOption = dateSelect.options[dateSelect.selectedIndex];
            const cachedRecord = selectedOption.dataset.record;

            if (cachedRecord) {
                try {
                    const recordData = JSON.parse(cachedRecord);
                    displayOralRecord(recordData);
                    if (loadingStatus) loadingStatus.classList.add('hidden');
                    return;
                } catch (e) {
                    // Continue to fetch from server
                }
            }

            // Otherwise fetch from server
            await loadOralRecord(selectedValue);
        }

        async function loadOralRecord(recordId) {
            const loadingStatus = document.getElementById("loadingStatus");
            const oralDataContainer = document.getElementById("oralDataContainer");

            try {
                if (loadingStatus) loadingStatus.classList.remove('hidden');

                // Clear previous data
                if (oralDataContainer) oralDataContainer.innerHTML = '';

                const response = await fetch(`/dentalemr_system/php/treatmentrecords/view_oral_api.php?record=${recordId}`);

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const result = await response.json();

                if (!result.success) {
                    throw new Error(result.message || 'Failed to load record');
                }

                const recordData = result.data;
                displayOralRecord(recordData);

                if (loadingStatus) loadingStatus.classList.add('hidden');

            } catch (error) {
                console.error('Error loading oral record:', error);
                if (loadingStatus) loadingStatus.classList.add('hidden');

                // Show error to user
                if (oralDataContainer) {
                    oralDataContainer.innerHTML = `
                    <div class="p-4 mb-4 text-sm text-red-800 rounded-lg bg-red-50 dark:bg-gray-800 dark:text-red-400" role="alert">
                        <span class="font-medium">Error!</span> Unable to load oral health record.
                    </div>
                `;
                }
            }
        }

        function displayOralRecord(record) {
            const oralDataContainer = document.getElementById("oralDataContainer");

            if (!record || !oralDataContainer) {
                return;
            }

            // Format date for display
            let displayDate = 'Date not available';
            try {
                const date = record.created_at ? new Date(record.created_at) : new Date();
                displayDate = date.toLocaleDateString('en-PH', {
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit'
                });
            } catch (e) {
                // Keep default date
            }

            // Function to check if a condition is present
            const isPresent = (value) => {
                if (value === null || value === undefined) return false;

                // Try boolean field first (ending with _bool)
                if (typeof value === 'boolean') return value;

                // Try regular field
                const strValue = String(value).trim().toLowerCase();
                return ['✓', '√', '1', 'true', 'yes', 'present', 'checked', 'on'].includes(strValue);
            };

            // Function to get display text and color
            const getConditionDisplay = (value, boolValue = null) => {
                const present = boolValue !== null ? boolValue : isPresent(value);
                return {
                    text: present ? 'Present ✓' : 'Absent ✗',
                    colorClass: present ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400',
                    bgColor: present ? 'bg-green-50 dark:bg-green-900/20' : 'bg-red-50 dark:bg-red-900/20',
                    borderColor: present ? 'border-green-200 dark:border-green-800' : 'border-red-200 dark:border-red-800'
                };
            };

            // Handle "others" field specially
            const othersValue = record.others || '';
            const othersPresent = othersValue && othersValue.trim() !== '';

            // Create HTML for oral conditions - FIXED TEMPLATE LITERAL
            const conditionsHTML = `
            <div class="p-4 bg-white rounded-lg shadow dark:border dark:bg-gray-800 dark:border-gray-700 mb-4">
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-4 gap-2">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Oral Health Examination</h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400">${displayDate}</p>
                    </div>
                    <div class="flex flex-col items-end gap-1">
                        <span class="text-xs text-gray-500 dark:text-gray-400 bg-gray-100 dark:bg-gray-700 px-2 py-1 rounded">
                            Record ID: ${record.id || ''}
                        </span>
                    </div>
                </div>

                <div class="mb-6">
                    <h4 class="text-md font-medium text-gray-900 dark:text-white mb-3">A. Oral Conditions</h4>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                        ${[
                            { key: 'orally_fit_child', label: 'Orally Fit Child (OFC)' },
                            { key: 'dental_caries', label: 'Dental Caries' },
                            { key: 'gingivitis', label: 'Gingivitis' },
                            { key: 'periodontal_disease', label: 'Periodontal Disease' },
                            { key: 'debris', label: 'Debris' },
                            { key: 'calculus', label: 'Calculus' },
                            { key: 'abnormal_growth', label: 'Abnormal Growth' },
                            { key: 'cleft_palate', label: 'Cleft Lip/Palate' }
                        ].map(item => {
                            const display = getConditionDisplay(
                                record[item.key], 
                                record[item.key + '_bool']
                            );
                            return `<div class="flex items-center justify-between p-3 ${display.bgColor} rounded-lg border ${display.borderColor}">
                                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">${item.label}</span>
                                <span class="text-sm font-semibold ${display.colorClass}">${display.text}</span>
                            </div>`;
                        }).join('')}
                        
                        <!-- Special handling for Others field -->
                        <div class="flex flex-col p-3 ${othersPresent ? 'bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800' : 'bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800'} rounded-lg">
                            <div class="flex justify-between items-center mb-2">
                                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Other Conditions</span>
                                <span class="text-sm font-semibold ${othersPresent ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'}">
                                    ${othersPresent ? 'Present ✓' : 'Absent ✗'}
                                </span>
                            </div>
                            ${othersPresent ? `
                                <div class="mt-2 p-2 bg-white dark:bg-gray-800 rounded border">
                                    <p class="text-sm text-gray-600 dark:text-gray-300">${othersValue}</p>
                                </div>
                            ` : ''}
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Permanent Teeth -->
                    <div class="p-4 bg-blue-50 dark:bg-blue-900/10 rounded-lg border border-blue-200 dark:border-blue-800">
                        <h4 class="text-md font-medium text-blue-800 dark:text-blue-300 mb-4">Permanent Teeth</h4>
                        <div class="space-y-3">
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-gray-700 dark:text-gray-300">Teeth Present:</span>
                                <span class="text-lg font-bold text-blue-700 dark:text-blue-300">${record.perm_teeth_present || 0}</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-gray-700 dark:text-gray-300">Sound Teeth:</span>
                                <span class="text-lg font-bold text-blue-700 dark:text-blue-300">${record.perm_sound_teeth || 0}</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-gray-700 dark:text-gray-300">Decayed (D):</span>
                                <span class="text-lg font-bold text-blue-700 dark:text-blue-300">${record.perm_decayed_teeth_d || 0}</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-gray-700 dark:text-gray-300">Missing (M):</span>
                                <span class="text-lg font-bold text-blue-700 dark:text-blue-300">${record.perm_missing_teeth_m || 0}</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-gray-700 dark:text-gray-300">Filled (F):</span>
                                <span class="text-lg font-bold text-blue-700 dark:text-blue-300">${record.perm_filled_teeth_f || 0}</span>
                            </div>
                            <div class="flex justify-between items-center pt-3 border-t border-blue-200 dark:border-blue-700">
                                <span class="text-sm font-semibold text-gray-800 dark:text-gray-200">Total DMF:</span>
                                <span class="text-xl font-bold text-blue-800 dark:text-blue-400">${record.perm_total_dmf || 0}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Temporary Teeth -->
                    <div class="p-4 bg-green-50 dark:bg-green-900/10 rounded-lg border border-green-200 dark:border-green-800">
                        <h4 class="text-md font-medium text-green-800 dark:text-green-300 mb-4">Temporary Teeth</h4>
                        <div class="space-y-3">
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-gray-700 dark:text-gray-300">Teeth Present:</span>
                                <span class="text-lg font-bold text-green-700 dark:text-green-300">${record.temp_teeth_present || 0}</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-gray-700 dark:text-gray-300">Sound Teeth:</span>
                                <span class="text-lg font-bold text-green-700 dark:text-green-300">${record.temp_sound_teeth || 0}</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-gray-700 dark:text-gray-300">Decayed (d):</span>
                                <span class="text-lg font-bold text-green-700 dark:text-green-300">${record.temp_decayed_teeth_d || 0}</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-gray-700 dark:text-gray-300">Filled (f):</span>
                                <span class="text-lg font-bold text-green-700 dark:text-green-300">${record.temp_filled_teeth_f || 0}</span>
                            </div>
                            <div class="flex justify-between items-center pt-3 border-t border-green-200 dark:border-green-700">
                                <span class="text-sm font-semibold text-gray-800 dark:text-gray-200">Total df:</span>
                                <span class="text-xl font-bold text-green-800 dark:text-green-400">${record.temp_total_df || 0}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Summary -->
                <div class="mt-6 p-4 bg-gray-50 dark:bg-gray-800/50 rounded-lg border border-gray-200 dark:border-gray-700">
                    <h4 class="text-md font-medium text-gray-900 dark:text-white mb-2">Summary</h4>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div class="text-center p-3 bg-white dark:bg-gray-800 rounded border">
                            <p class="text-sm text-gray-600 dark:text-gray-400">Total Permanent DMF</p>
                            <p class="text-2xl font-bold text-blue-600 dark:text-blue-400">${record.perm_total_dmf || 0}</p>
                        </div>
                        <div class="text-center p-3 bg-white dark:bg-gray-800 rounded border">
                            <p class="text-sm text-gray-600 dark:text-gray-400">Total Temporary df</p>
                            <p class="text-2xl font-bold text-green-600 dark:text-green-400">${record.temp_total_df || 0}</p>
                        </div>
                    </div>
                </div>
            </div>
        `;

            // Display the record
            oralDataContainer.innerHTML = conditionsHTML;
        }

        function showNoRecordsMessage() {
            const noRecordsMessage = document.getElementById("noRecordsMessage");
            const oralDataContainer = document.getElementById("oralDataContainer");
            const dateSelect = document.getElementById("dataSelect");

            if (noRecordsMessage) {
                noRecordsMessage.classList.remove('hidden');
                noRecordsMessage.innerHTML = `
                <div class="text-center py-8">
                    <div class="inline-flex items-center justify-center w-16 h-16 mb-4 rounded-full bg-blue-100 dark:bg-blue-900">
                        <svg class="w-8 h-8 text-blue-600 dark:text-blue-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">No Oral Health Records</h3>
                    <p class="text-gray-600 dark:text-gray-400 mb-6 max-w-md mx-auto">
                        No oral health examination records have been created for ${patientName || 'this patient'} yet.
                    </p>
                    <button onclick="openOHCModal()" 
                        class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition duration-200">
                        <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd"></path>
                        </svg>
                        Create First Oral Health Record
                    </button>
                </div>
            `;
            }

            if (oralDataContainer) oralDataContainer.innerHTML = '';
            if (dateSelect) {
                dateSelect.innerHTML = '<option value="" selected disabled>No records available</option>';
                dateSelect.disabled = true;
            }
        }

        function showNoRecordsMessage() {
            const noRecordsMessage = document.getElementById("noRecordsMessage");
            const oralDataContainer = document.getElementById("oralDataContainer");
            const dateSelect = document.getElementById("dataSelect");

            if (noRecordsMessage) {
                noRecordsMessage.classList.remove('hidden');
                noRecordsMessage.innerHTML = ` <
        div class = "text-center py-8" >
        <
        div class = "inline-flex items-center justify-center w-16 h-16 mb-4 rounded-full bg-blue-100 dark:bg-blue-900" >
        <
        svg class = "w-8 h-8 text-blue-600 dark:text-blue-300"
        fill = "none"
        stroke = "currentColor"
        viewBox = "0 0 24 24" >
            <
            path stroke - linecap = "round"
        stroke - linejoin = "round"
        stroke - width = "2"
        d = "M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" > < /path> <
            /svg> <
            /div> <
            h3 class = "text-lg font-semibold text-gray-900 dark:text-white mb-2" > No Oral Health Records < /h3> <
            p class = "text-gray-600 dark:text-gray-400 mb-6 max-w-md mx-auto" >
            No oral health examination records have been created
        for $ {
            patientName || 'this patient'
        }
        yet. <
            /p> <
            button onclick = "openOHCModal()"
        class = "inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition duration-200" >
        <
        svg class = "w-5 h-5 mr-2"
        fill = "currentColor"
        viewBox = "0 0 20 20" >
            <
            path fill - rule = "evenodd"
        d = "M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z"
        clip - rule = "evenodd" > < /path> <
            /svg>
        Create First Oral Health Record
            <
            /button> <
            /div>
        `;
            }

            if (oralDataContainer) oralDataContainer.innerHTML = '';
            if (dateSelect) {
                dateSelect.innerHTML = '<option value="" selected disabled>No records available</option>';
                dateSelect.disabled = true;
            }
        }

        function showLoading(show) {
            const loadingStatus = document.getElementById("loadingStatus");
            if (loadingStatus) {
                if (show) {
                    loadingStatus.classList.remove('hidden');
                    loadingStatus.innerHTML = ` <
        div class = "flex items-center justify-center space-x-2" >
        <
        div class = "w-4 h-4 border-2 border-blue-600 border-t-transparent rounded-full animate-spin" > < /div> <
        span class = "text-blue-600 dark:text-blue-400" > Loading oral health records... < /span> <
            /div>
        `;
                } else {
                    loadingStatus.classList.add('hidden');
                }
            }
        }

        function showAlert(message, type = 'error') {
            const alertDiv = document.createElement('div');
            alertDiv.className = `
        fixed top - 4 right - 4 z - 50 p - 4 rounded - lg shadow - lg $ {
            type === 'error' ? 'bg-red-100 border border-red-300 text-red-800' : 'bg-blue-100 border border-blue-300 text-blue-800'
        }
        `;
            alertDiv.innerHTML = ` <
        div class = "flex items-center" >
        <
        svg class = "w-5 h-5 mr-2"
        fill = "currentColor"
        viewBox = "0 0 20 20" >
            <
            path fill - rule = "evenodd"
        d = "M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"
        clip - rule = "evenodd" > < /path> <
            /svg> <
            span > $ {
                message
            } < /span> <
            /div>
        `;
            document.body.appendChild(alertDiv);

            setTimeout(() => {
                alertDiv.remove();
            }, 5000);
        }

        function navigateToNext() {
            if (!currentPatientId || !currentUserId) {
                showAlert('Missing patient or user ID.', 'error');
                return;
            }

            window.location.href = `view_oralA.php?uid=${currentUserId}&id=${currentPatientId}`;
        }

        function backmain() {
            if (currentUserId) {
                window.location.href=`treatmentrecords.php?uid=${currentUserId}`;
        //         window.location.href = `
        // treatmentrecords.php ? uid = $ {
        //     currentUserId
        // }
        // `;
            }
        }

        // OHC modal functions
        function openOHCModal() {
            const modal = document.getElementById('ohcModal');
            if (modal) {
                modal.classList.remove('hidden');
                modal.classList.add('flex');

                // Set patient ID in form
                const patientIdInput = document.querySelector('#ohcForm #patient_id');
                if (patientIdInput && currentPatientId) {
                    patientIdInput.value = currentPatientId;
                }
            }
        }

        function closeOHCModal() {
            const modal = document.getElementById('ohcModal');
            if (modal) {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
                
                // Reset form - FIXED THIS SECTION
                const form = document.getElementById('ohcForm');
                if (form) {
                    form.reset();
                    // Reset the check fields to empty
                    const checkFields = [
                        "orally_fit_child", "dental_caries", "gingivitis",
                        "periodontal_disease", "debris", "calculus",
                        "abnormal_growth", "cleft_palate", "others"
                    ];
                    checkFields.forEach(id => {
                        const el = form.querySelector(`#${id}`);
                        if (el) el.value = '';
                    });
                    // Reset calculated fields
                    const calcFields = ["perm_total_dmf", "temp_total_df"];
                    calcFields.forEach(id => {
                        const el = form.querySelector(`#${id}`);
                        if (el) el.value = '0';
                    });
                }
            }
        }

        // Make functions available globally
        window.loadSelectedRecord = loadSelectedRecord;
        window.openOHCModal = openOHCModal;
        window.closeOHCModal = closeOHCModal;
        window.next = navigateToNext;
        window.backmain = backmain;

        // Client-side 10-minute inactivity logout
        let inactivityTime = 600000; // 10 minutes in ms
        let logoutTimer;

        function resetTimer() {
            clearTimeout(logoutTimer);
            logoutTimer = setTimeout(() => {
                alert("You've been logged out due to 10 minutes of inactivity.");
                window.location.href = "/dentalemr_system/php/login/logout.php?uid=" + currentUserId;
            }, inactivityTime);
        }

        ["click", "mousemove", "keypress", "scroll", "touchstart"].forEach(evt => {
            document.addEventListener(evt, resetTimer, false);
        });

        resetTimer();
    </script>

    <script>
        function getValue(id) {
            const form = document.getElementById("ohcForm");
            const el = form.querySelector(`#${id}`);
            if (!el) return "";

            // For check fields (text inputs that toggle ✓/✗)
            if (el.type === "text" && el.hasAttribute('readonly') && el.onclick) {
                return el.value?.trim() || "";
            }
            
            // For number fields
            if (el.type === "number") {
                return el.value || "0";
            }
            
            return el.value?.trim() || "";
        }

        function toggleCheck(input) {
            if (input.value === "") input.value = "✗";
            else if (input.value === "✗") input.value = "✓";
            else input.value = "";
        }

        function calcTotals() {
            const f = document.getElementById("ohcForm");
            const num = id => parseInt(f.querySelector(`#${id}`)?.value) || 0;

            const D = num("perm_decayed_teeth_d");
            const M = num("perm_missing_teeth_m");
            const F = num("perm_filled_teeth_f");
            const d = num("temp_decayed_teeth_d");
            const fT = num("temp_filled_teeth_f");

            f.querySelector("#perm_total_dmf").value = D + M + F;
            f.querySelector("#temp_total_df").value = d + fT;
        }

        async function saveOHC() {
            const form = document.getElementById("ohcForm");
            const patient_id = form.querySelector("#patient_id")?.value;

            if (!patient_id || patient_id <= 0) {
                alert("No patient selected or invalid patient ID.");
                return;
            }

            // Prepare form data
            const formData = new FormData();
            formData.append('patient_id', patient_id);
            
            // Collect all form values
            const fields = [
                'orally_fit_child', 'dental_caries', 'gingivitis', 'periodontal_disease',
                'debris', 'calculus', 'abnormal_growth', 'cleft_palate', 'others',
                'perm_teeth_present', 'perm_sound_teeth', 'perm_decayed_teeth_d',
                'perm_missing_teeth_m', 'perm_filled_teeth_f', 'perm_total_dmf',
                'temp_teeth_present', 'temp_sound_teeth', 'temp_decayed_teeth_d',
                'temp_filled_teeth_f', 'temp_total_df'
            ];

            fields.forEach(field => {
                const value = getValue(field);
                formData.append(field, value);
            });

            console.log("Saving oral health data...");

            try {
                // Use FormData instead of JSON for better compatibility
                const response = await fetch("/dentalemr_system/php/treatmentrecords/save_ohc.php", {
                    method: "POST",
                    body: formData
                });

                const text = await response.text();
                console.log("Server response:", text);

                // Try to parse JSON
                let result;
                try {
                    result = JSON.parse(text);
                } catch (parseError) {
                    console.error("JSON Parse Error:", parseError);
                    console.error("Raw response text:", text);
                    
                    // Check if the response contains HTML or PHP errors
                    if (text.includes('<') || text.includes('PHP') || text.includes('Error')) {
                        alert("Server returned an error page. Please check server logs.");
                    } else {
                        alert("Server returned invalid response. Please try again.");
                    }
                    return;
                }

                if (result.success) {
                    alert(result.message);
                    closeOHCModal();
                    // Reload the page to show new record
                    setTimeout(() => {
                        location.reload();
                    }, 500);
                } else {
                    alert(result.message || "Error saving data.");
                }
            } catch (err) {
                console.error("Network Error:", err);
                alert("Failed to save data. Please check your connection and try again.");
            }
        }

        document.addEventListener("DOMContentLoaded", () => {
            const form = document.getElementById("ohcForm");

            // Enable toggle on click
            [
                "orally_fit_child", "dental_caries", "gingivitis",
                "periodontal_disease", "debris", "calculus",
                "abnormal_growth", "cleft_palate", "others"
            ].forEach(id => {
                const el = form.querySelector(`#${id}`);
                if (el) el.addEventListener("click", () => toggleCheck(el));
            });

            // Auto calc totals
            [
                "perm_decayed_teeth_d", "perm_missing_teeth_m",
                "perm_filled_teeth_f", "temp_decayed_teeth_d", "temp_filled_teeth_f"
            ].forEach(id => {
                const el = form.querySelector(`#${id}`);
                if (el) el.addEventListener("input", calcTotals);
            });

            // Make saveOHC globally available
            window.saveOHC = saveOHC;
        });
    </script>
</body>

</html>