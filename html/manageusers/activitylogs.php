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

?>
<!doctype html>
<html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MHO Dental Clinic </title>
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
                                <a href="#"
                                    class="block py-2 px-4 text-sm hover:bg-gray-100 dark:hover:bg-gray-600 dark:text-gray-400 dark:hover:text-white">Activity logs</a>
                            </li>
                        </ul>
                        <ul class="py-1 text-gray-700 dark:text-gray-300" aria-labelledby="dropdown">
                            <a href="#"
                                onclick="confirmLogout(<?php echo $loggedUser['id']; ?>)"
                                class="block py-2 px-4 text-sm hover:bg-gray-100 dark:hover:bg-gray-600 dark:hover:text-white">
                                Sign out
                            </a>
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
                        <a href="/dentalemr_system/html/index.php?uid=<?php echo $userId; ?>"
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
                        <a href="/dentalemr_system/html/addpatient.php?uid=<?php echo $userId; ?>"
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
                                <a href="/dentalemr_system/html/treatmentrecords/treatmentrecords.php?uid=<?php echo $userId; ?>"
                                    class="flex items-center p-2 pl-11 w-full text-base font-medium text-gray-900 rounded-lg transition duration-75 group hover:bg-gray-100 dark:text-white dark:hover:bg-gray-700">Treatment
                                    Records</a>
                            </li>
                            <li>
                                <a href="/dentalemr_system/html/addpatienttreatment/patienttreatment.php?uid=<?php echo $userId; ?>"
                                    class="flex items-center p-2 pl-11 w-full text-base font-medium text-gray-900 rounded-lg transition duration-75 group hover:bg-gray-100 dark:text-white dark:hover:bg-gray-700">Add
                                    Patient Treatment</a>
                            </li>
                        </ul>
                    </li>
                </ul>
                <ul class="pt-5 mt-5 space-y-2 border-t border-gray-200 dark:border-gray-700">
                    <li>
                        <a href="/dentalemr_system/html/reports/targetclientlist.php?uid=<?php echo $userId; ?>"
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
                        <a href="/dentalemr_system/html/reports/mho_ohp.php?uid=<?php echo $userId; ?>"
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
                        <a href="/dentalemr_system/html/reports/oralhygienefindings.php?uid=<?php echo $userId; ?>"
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
                        <a href="/dentalemr_system/html/archived.php?uid=<?php echo $userId; ?>"
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
            <h1 class="text-xl text-center w-full font-bold">Activity Logs</h1>
            <section id="activity" class="bg-gray-50 dark:bg-gray-900 p-3 sm:p-5">
                <div class="mx-auto max-w-screen-xl px-4 lg:px-12">
                    <div class="bg-white dark:bg-gray-800 relative shadow-md sm:rounded-lg ">
                        <!-- sticky header -->
                        <div class="sticky top-12 z-40 bg-white dark:bg-gray-800 sm:rounded-t-lg border-b border-gray-200 dark:border-gray-700">
                            <div>
                                <p class="text-2xl py-2 font-semibold px-5 mt-5 text-gray-900 dark:text-white">Activity Logs</p>
                            </div>
                            <div class="flex flex-col md:flex-row items-center justify-between space-y-3 md:space-y-0 md:space-x-4 p-4">
                                <div class="w-full md:w-1/2">
                                    <form class="flex items-center" onsubmit="searchActivities(event)">
                                        <label for="simple-search" class="sr-only">Search</label>
                                        <div class="relative w-full">
                                            <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                                <svg aria-hidden="true" class="w-5 h-5 text-gray-500 dark:text-gray-400" fill="currentColor" viewbox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                                                    <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd" />
                                                </svg>
                                            </div>
                                            <input type="text" id="simple-search" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full pl-10 p-2 dark:bg-gray-700 dark:border-blue-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500" placeholder="Search activities..." oninput="debouncedSearch()">
                                        </div>
                                    </form>
                                </div>
                                <div class="w-full md:w-auto flex flex-col md:flex-row space-y-2 md:space-y-0 items-stretch md:items-center justify-end md:space-x-3 flex-shrink-0">
                                    <!-- Bulk Delete Button -->
                                    <button type="button" onclick="confirmBulkDelete()" id="bulkDeleteBtn" class="hidden flex items-center justify-center cursor-pointer text-white bg-red-700 hover:bg-red-800 font-medium rounded-lg text-sm px-4 py-2 dark:bg-red-600 dark:hover:bg-red-700">
                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                        </svg>
                                        Delete Selected
                                    </button>

                                    <!-- Save Logs Button -->
                                    <button type="button" onclick="saveLogs()" class="flex items-center justify-center cursor-pointer text-white bg-green-700 hover:bg-green-800 font-medium rounded-lg text-sm px-4 py-2 dark:bg-green-600 dark:hover:bg-green-700">
                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                        </svg>
                                        Save Logs
                                    </button>

                                    <button type="button" onclick="refreshActivities()" class="flex items-center justify-center cursor-pointer text-white bg-blue-700 hover:bg-blue-800 font-medium rounded-lg text-sm px-4 py-2 dark:bg-blue-600 dark:hover:bg-blue-700">
                                        <svg class="h-3.5 w-3.5 mr-2" fill="currentColor" viewbox="0 0 20 20" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                            <path clip-rule="evenodd" fill-rule="evenodd" d="M4 2a1 1 0 011 1v2.101a7.002 7.002 0 0111.601 2.566 1 1 0 11-1.885.666A5.002 5.002 0 005.999 7H9a1 1 0 010 2H4a1 1 0 01-1-1V3a1 1 0 011-1zm.008 9.057a1 1 0 011.276.61A5.002 5.002 0 0014.001 13H11a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0v-2.101a7.002 7.002 0 01-11.601-2.566 1 1 0 01.61-1.276z" />
                                        </svg>
                                        Refresh
                                    </button>
                                </div>
                            </div>

                            <!-- Bulk Actions Info -->
                            <div id="bulkActionsInfo" class="hidden px-4 py-2 bg-blue-50 dark:bg-blue-900 border-b">
                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-blue-700 dark:text-blue-300">
                                        <span id="selectedCount">0</span> activities selected
                                    </span>
                                    <button onclick="clearSelection()" class="text-sm text-blue-600 dark:text-blue-400 hover:underline">
                                        Clear selection
                                    </button>
                                </div>
                            </div>
                        </div>


                        <!-- Table -->
                        <div class="overflow-x-auto">
                            <table id="activityTable" class="w-full text-sm text-left text-gray-500 dark:text-gray-400">
                                <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
                                    <tr>
                                        <th class="px-4 py-3 text-center w-12">
                                            <input type="checkbox" id="selectAll" onchange="toggleSelectAll(this)" class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600">
                                        </th>
                                        <th class="px-4 py-3 text-center">No.</th>
                                        <th class="px-4 py-3 text-center">Activity ID</th>
                                        <th class="px-4 py-3 text-center">User</th>
                                        <th class="px-4 py-3 text-center">Target</th>
                                        <th class="px-4 py-3 text-center">Details</th>
                                        <th class="px-4 py-3 text-center">IP Address</th>
                                        <th class="px-4 py-3 text-center">Date</th>
                                    </tr>
                                </thead>
                                <tbody id="activityBody">
                                    <tr class="border-b dark:border-gray-700 border-gray-200">
                                        <td colspan="8" class="px-4 py-3 text-center font-medium text-gray-900 whitespace-nowrap dark:text-white">
                                            Loading activities...
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <nav id="paginationNav"
                            class="flex flex-col md:flex-row justify-between items-start md:items-center space-y-3 md:space-y-0 p-3"
                            aria-label="Table navigation">
                        </nav>
                    </div>
                </div>
            </section>
        </main>

        <!-- View Details Modal -->
        <div id="detailsModal" class="fixed inset-0 bg-gray-600/50 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
            <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white dark:bg-gray-800">
                <div class="mt-3">
                    <div class="flex justify-between items-center pb-3 border-b">
                        <h3 class="text-xl font-medium text-gray-900 dark:text-white">Activity Details</h3>
                        <button onclick="closeDetailsModal()" class="text-gray-400 cursor-pointer hover:text-gray-600">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                    <div class="mt-4">
                        <div id="modalContent" class="space-y-3 max-h-96 overflow-y-auto">
                            <!-- Content will be populated by JavaScript -->
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Delete Confirmation Modal -->
        <div id="deleteModal" class="fixed inset-0 bg-gray-600/50 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
            <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-1/3 shadow-lg rounded-md bg-white dark:bg-gray-800">
                <div class="mt-3">
                    <div class="flex justify-between items-center pb-3 border-b">
                        <h3 class="text-xl font-medium text-gray-900 dark:text-white" id="deleteModalTitle">Delete Activity</h3>
                        <button onclick="closeDeleteModal()" class="text-gray-400 cursor-pointer hover:text-gray-600">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                    <div class="mt-4">
                        <p id="deleteModalMessage" class="text-gray-700 dark:text-gray-300">Are you sure you want to delete this activity?</p>
                        <div class="flex justify-end space-x-3 mt-6">
                            <button onclick="closeDeleteModal()" class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-200 hover:bg-gray-300 rounded-lg dark:bg-gray-600 dark:text-gray-200 dark:hover:bg-gray-500">
                                Cancel
                            </button>
                            <button onclick="confirmDelete()" id="confirmDeleteBtn" class="px-4 py-2 text-sm font-medium text-white bg-red-600 hover:bg-red-700 rounded-lg dark:bg-red-500 dark:hover:bg-red-600">
                                Delete
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Save Format Modal -->
        <div id="saveFormatModal" class="fixed inset-0 bg-gray-600/50 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
            <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-1/3 shadow-lg rounded-md bg-white dark:bg-gray-800">
                <div class="mt-3">
                    <div class="flex justify-between items-center pb-3 border-b">
                        <h3 class="text-xl font-medium text-gray-900 dark:text-white">Export Activity Logs</h3>
                        <button onclick="closeSaveFormatModal()" class="text-gray-400 cursor-pointer hover:text-gray-600">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                    <div class="mt-4">
                        <p class="text-gray-700 dark:text-gray-300 mb-4">Choose export format:</p>
                        <div class="grid grid-cols-2 gap-4">
                            <button onclick="downloadLogs('csv')" class="p-4 border-2 border-gray-200 rounded-lg hover:border-blue-500 hover:bg-blue-50 dark:border-gray-600 dark:hover:border-blue-400 dark:hover:bg-blue-900/20 transition-colors">
                                <div class="text-center">
                                    <svg class="w-8 h-8 mx-auto mb-2 text-gray-600 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2a3 3 0 00-3-3H5a3 3 0 00-3 3v2a1 1 0 001 1h12a1 1 0 001-1z M12 12v-2m0 0V8m0 2h2m-2 0H10" />
                                    </svg>
                                    <span class="font-medium text-gray-900 dark:text-white">CSV</span>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Excel compatible</p>
                                </div>
                            </button>
                            <button onclick="downloadLogs('json')" class="p-4 border-2 border-gray-200 rounded-lg hover:border-green-500 hover:bg-green-50 dark:border-gray-600 dark:hover:border-green-400 dark:hover:bg-green-900/20 transition-colors">
                                <div class="text-center">
                                    <svg class="w-8 h-8 mx-auto mb-2 text-gray-600 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4" />
                                    </svg>
                                    <span class="font-medium text-gray-900 dark:text-white">JSON</span>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Structured data</p>
                                </div>
                            </button>
                            <button onclick="downloadLogs('txt')" class="p-4 border-2 border-gray-200 rounded-lg hover:border-purple-500 hover:bg-purple-50 dark:border-gray-600 dark:hover:border-purple-400 dark:hover:bg-purple-900/20 transition-colors">
                                <div class="text-center">
                                    <svg class="w-8 h-8 mx-auto mb-2 text-gray-600 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                    <span class="font-medium text-gray-900 dark:text-white">Text</span>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Readable format</p>
                                </div>
                            </button>
                            <button onclick="downloadLogs('pdf')" class="p-4 border-2 border-gray-200 rounded-lg hover:border-red-500 hover:bg-red-50 dark:border-gray-600 dark:hover:border-red-400 dark:hover:bg-red-900/20 transition-colors">
                                <div class="text-center">
                                    <svg class="w-8 h-8 mx-auto mb-2 text-gray-600 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                    </svg>
                                    <span class="font-medium text-gray-900 dark:text-white">PDF</span>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Printable format</p>
                                </div>
                            </button>
                        </div>
                        <div class="mt-6 text-center">
                            <button onclick="closeSaveFormatModal()" class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-200 hover:bg-gray-300 rounded-lg dark:bg-gray-600 dark:text-gray-200 dark:hover:bg-gray-500">
                                Cancel
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- <script src="../node_modules/flowbite/dist/flowbite.min.js"></script> -->
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

    <script>
        let currentPage = 1;
        let totalPages = 1;
        let searchTimeout;
        let selectedActivities = new Set();
        let deleteMode = 'single';
        let activityToDelete = null;

        function debouncedSearch() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                currentPage = 1;
                loadActivities();
            }, 500);
        }

        function refreshActivities() {
            document.getElementById('simple-search').value = '';
            currentPage = 1;
            clearSelection();
            loadActivities();
        }

        function changePage(page) {
            if (page >= 1) {
                currentPage = page;
                clearSelection();
                loadActivities();
            }
        }

        function loadActivities() {
            const searchTerm = document.getElementById('simple-search').value;
            const tbody = document.getElementById('activityBody');

            tbody.innerHTML = '<tr><td colspan="8" class="px-4 py-3 text-center">Loading activities...</td></tr>';

            const params = new URLSearchParams();
            if (searchTerm) {
                params.append('search', searchTerm);
            }
            params.append('page', currentPage);
            params.append('limit', 10); // Changed from 50 to 10

            // Add user ID for authorization
            const urlParams = new URLSearchParams(window.location.search);
            const userId = urlParams.get('uid');
            if (userId) {
                params.append('uid', userId);
            }

            fetch(`/dentalemr_system/php/manageusers/activity_logs.php?${params}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        displayActivities(data.data);
                        // Make sure your backend returns total count, limit, and current page
                        updatePagination({
                            total: data.total || data.data.length,
                            limit: data.limit || 10, // Changed from 50 to 10
                            page: data.page || currentPage
                        });
                    } else {
                        tbody.innerHTML = `<tr><td colspan="8" class="px-4 py-3 text-center text-red-500">Error: ${data.error}</td></tr>`;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    tbody.innerHTML = `<tr><td colspan="8" class="px-4 py-3 text-center text-red-500">Failed to load activities</td></tr>`;
                });
        }

        function displayActivities(activities) {
            const tbody = document.getElementById('activityBody');

            if (!activities || activities.length === 0) {
                tbody.innerHTML = '<tr><td colspan="8" class="px-4 py-3 text-center">No activities found</td></tr>';
                return;
            }

            tbody.innerHTML = activities.map((activity, index) => {
                // Calculate display number based on current page and position (10 per page)
                const displayNumber = ((currentPage - 1) * 10) + index + 1; // Changed from 50 to 10

                // Format date properly
                let formattedDate = 'N/A';
                try {
                    const date = new Date(activity.created_at || activity.date);
                    if (!isNaN(date.getTime())) {
                        formattedDate = date.toLocaleDateString('en-PH', {
                            year: 'numeric',
                            month: 'short',
                            day: 'numeric',
                            hour: '2-digit',
                            minute: '2-digit'
                        });
                    }
                } catch (e) {
                    console.error('Date formatting error:', e);
                }

                // Clean user name - remove any brackets or ID references
                let userName = activity.user_name || 'System';
                // Remove any [ID:xxx] patterns from user name
                userName = userName.replace(/\s*\[ID:\d+\]\s*/, '').trim();
                if (!userName || userName === 'System') {
                    userName = 'System';
                }

                return `
            <tr class="border-b dark:border-gray-700 border-gray-200 hover:bg-gray-50 dark:hover:bg-gray-600 cursor-pointer" 
                onclick="showDetails(${JSON.stringify(activity).replace(/"/g, '&quot;')})">
                <td class="px-4 py-3 text-center" onclick="event.stopPropagation()">
                    <input type="checkbox" value="${activity.id}" onchange="toggleActivitySelection(${activity.id})" 
                        class="activity-checkbox w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600">
                </td>
                <td class="px-4 py-3 text-center font-medium text-gray-900 whitespace-nowrap dark:text-white">
                    ${displayNumber}
                </td>
                <td class="px-4 py-3 text-center text-sm text-gray-500 dark:text-gray-400">
                    ${activity.id}
                </td>
                <td class="px-4 py-3 text-center">
                    <div class="font-medium text-gray-900 dark:text-white">${userName}</div>
                    <div class="text-xs text-gray-500">ID: ${activity.user_id || 'N/A'}</div>
                </td>
                <td class="px-4 py-3 text-center">${activity.target || 'System'}</td>
                <td class="px-4 py-3 text-center max-w-xs truncate" title="${activity.details || 'No details'}">
                    ${activity.details || 'N/A'}
                </td>
                <td class="px-4 py-3 text-center text-xs font-mono">${activity.ip_address || 'N/A'}</td>
                <td class="px-4 py-3 text-center text-sm">${formattedDate}</td>
            </tr>
        `;
            }).join('');

            // Update selection checkboxes
            updateCheckboxStates();
        }

        function updateCheckboxStates() {
            const checkboxes = document.querySelectorAll('.activity-checkbox');
            checkboxes.forEach(checkbox => {
                const activityId = parseInt(checkbox.value);
                checkbox.checked = selectedActivities.has(activityId);
            });
            updateBulkActions();
        }

        function updatePagination(data) {
            const total = data.total || 0;
            const limitVal = data.limit || 50;
            const page = data.page || 1;

            renderPagination(total, limitVal, page);
        }

        function renderPagination(total, limitVal, page) {
            const paginationNav = document.getElementById("paginationNav");
            const totalPages = Math.max(1, Math.ceil(total / limitVal));
            const start = (page - 1) * limitVal + 1;
            const end = Math.min(page * limitVal, total);

            const showingText = `
        <span class="text-sm font-normal text-gray-500 dark:text-gray-400">
            Showing <span class="font-semibold text-gray-700 dark:text-white">${start}-${end}</span>
            of <span class="font-semibold text-gray-700 dark:text-white">${total}</span>
        </span>
    `;

            let pagesHTML = "";

            // Previous button
            if (page > 1) {
                pagesHTML += `
            <li>
                <a href="#" onclick="changePage(${page - 1}); return false;" 
                   class="flex items-center justify-center h-full py-1.5 px-2 ml-0 text-gray-500 bg-white rounded-l-sm border border-gray-300 hover:bg-gray-100 hover:text-gray-700 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white">
                    <svg class="w-5 h-5" aria-hidden="true" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                        <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
                    </svg>
                </a>
            </li>`;
            }

            // Page numbers - show limited pages for better UX with 10 rows
            const maxVisiblePages = 5;
            let startPage = Math.max(1, page - Math.floor(maxVisiblePages / 2));
            let endPage = Math.min(totalPages, startPage + maxVisiblePages - 1);

            // Adjust start page if we're near the end
            if (endPage - startPage + 1 < maxVisiblePages) {
                startPage = Math.max(1, endPage - maxVisiblePages + 1);
            }

            // First page and ellipsis
            if (startPage > 1) {
                pagesHTML += `
            <li>
                <a href="#" onclick="changePage(1); return false;" 
                   class="flex items-center justify-center text-sm py-2 px-3 text-gray-500 bg-white border border-gray-300 hover:bg-gray-100 hover:text-gray-700 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white">
                    1
                </a>
            </li>`;
                if (startPage > 2) {
                    pagesHTML += `
                <li>
                    <span class="flex items-center justify-center text-sm py-2 px-3 text-gray-500 bg-white border border-gray-300 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-400">
                        ...
                    </span>
                </li>`;
                }
            }

            // Page numbers
            for (let i = startPage; i <= endPage; i++) {
                if (i === page) {
                    pagesHTML += `
                <li>
                    <span class="flex items-center justify-center text-sm z-10 py-2 px-3 text-blue-600 bg-blue-50 border border-blue-300 hover:bg-blue-100 hover:text-blue-700 dark:border-gray-700 dark:bg-gray-700 dark:text-white">
                        ${i}
                    </span>
                </li>`;
                } else {
                    pagesHTML += `
                <li>
                    <a href="#" onclick="changePage(${i}); return false;" 
                       class="flex items-center justify-center text-sm py-2 px-3 text-gray-500 bg-white border border-gray-300 hover:bg-gray-100 hover:text-gray-700 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white">
                        ${i}
                    </a>
                </li>`;
                }
            }

            // Last page and ellipsis
            if (endPage < totalPages) {
                if (endPage < totalPages - 1) {
                    pagesHTML += `
                <li>
                    <span class="flex items-center justify-center text-sm py-2 px-3 text-gray-500 bg-white border border-gray-300 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-400">
                        ...
                    </span>
                </li>`;
                }
                pagesHTML += `
            <li>
                <a href="#" onclick="changePage(${totalPages}); return false;" 
                   class="flex items-center justify-center text-sm py-2 px-3 text-gray-500 bg-white border border-gray-300 hover:bg-gray-100 hover:text-gray-700 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white">
                    ${totalPages}
                </a>
            </li>`;
            }

            // Next button
            if (page < totalPages) {
                pagesHTML += `
            <li>
                <a href="#" onclick="changePage(${page + 1}); return false;" 
                   class="flex items-center justify-center h-full py-1.5 px-2 leading-tight text-gray-500 bg-white rounded-r-sm border border-gray-300 hover:bg-gray-100 hover:text-gray-700 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white">
                    <svg class="w-5 h-5" aria-hidden="true" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                        <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                    </svg>
                </a>
            </li>`;
            }

            paginationNav.innerHTML = `${showingText} <ul class="inline-flex -space-x-px">${pagesHTML}</ul>`;
        }

        // Selection Functions
        function toggleSelectAll(checkbox) {
            const checkboxes = document.querySelectorAll('.activity-checkbox');
            checkboxes.forEach(cb => {
                cb.checked = checkbox.checked;
                const activityId = parseInt(cb.value);
                if (checkbox.checked) {
                    selectedActivities.add(activityId);
                } else {
                    selectedActivities.delete(activityId);
                }
            });
            updateBulkActions();
        }

        function toggleActivitySelection(activityId) {
            if (selectedActivities.has(activityId)) {
                selectedActivities.delete(activityId);
            } else {
                selectedActivities.add(activityId);
            }
            updateBulkActions();
        }

        function updateBulkActions() {
            const selectedCount = selectedActivities.size;
            const bulkActionsInfo = document.getElementById('bulkActionsInfo');
            const bulkDeleteBtn = document.getElementById('bulkDeleteBtn');
            const selectAllCheckbox = document.getElementById('selectAll');

            if (selectedCount > 0) {
                bulkActionsInfo.classList.remove('hidden');
                bulkDeleteBtn.classList.remove('hidden');
                document.getElementById('selectedCount').textContent = selectedCount;
            } else {
                bulkActionsInfo.classList.add('hidden');
                bulkDeleteBtn.classList.add('hidden');
            }

            // Update select all checkbox state
            const totalCheckboxes = document.querySelectorAll('.activity-checkbox').length;
            selectAllCheckbox.checked = selectedCount > 0 && selectedCount === totalCheckboxes;
            selectAllCheckbox.indeterminate = selectedCount > 0 && selectedCount < totalCheckboxes;
        }

        function clearSelection() {
            selectedActivities.clear();
            const checkboxes = document.querySelectorAll('.activity-checkbox');
            checkboxes.forEach(cb => cb.checked = false);
            updateBulkActions();
        }

        // Delete Functions
        function confirmSingleDelete(activityId) {
            deleteMode = 'single';
            activityToDelete = activityId;

            // Get activity details for confirmation message
            const activity = getActivityById(activityId);
            const userName = activity ? (activity.user_name || 'System').replace(/\s*\[ID:\d+\]\s*/, '') : 'Unknown User';

            document.getElementById('deleteModalTitle').textContent = 'Delete Activity';
            document.getElementById('deleteModalMessage').textContent = `Are you sure you want to delete this activity?\n\nUser: ${userName}\n\nThis action cannot be undone.`;
            document.getElementById('deleteModal').classList.remove('hidden');
        }

        function confirmBulkDelete() {
            if (selectedActivities.size === 0) return;

            deleteMode = 'bulk';
            document.getElementById('deleteModalTitle').textContent = 'Delete Activities';
            document.getElementById('deleteModalMessage').textContent = `Are you sure you want to delete ${selectedActivities.size} selected activities?\n\nThis action cannot be undone.`;
            document.getElementById('deleteModal').classList.remove('hidden');
        }

        function getActivityById(activityId) {
            // This would need to be implemented based on how you store activities
            // For now, we'll try to find it in the current table
            const rows = document.querySelectorAll('#activityBody tr');
            for (let row of rows) {
                const idCell = row.querySelector('td:nth-child(2)');
                if (idCell && parseInt(idCell.textContent) === activityId) {
                    // Extract data from row (simplified approach)
                    return {
                        id: activityId,
                        user_name: row.querySelector('td:nth-child(4) div:first-child')?.textContent || 'System'
                    };
                }
            }
            return null;
        }

        function confirmDelete() {
            const activityIds = deleteMode === 'single' ? [activityToDelete] : Array.from(selectedActivities);

            // Add user ID for authorization
            const urlParams = new URLSearchParams(window.location.search);
            const userId = urlParams.get('uid');

            fetch('/dentalemr_system/php/manageusers/delete_activity.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        activity_ids: activityIds,
                        user_id: userId
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showNotification(`Successfully deleted ${data.deleted_count} activity(s)`, 'success');
                        closeDeleteModal();
                        clearSelection();
                        loadActivities(); // Refresh the list
                    } else {
                        showNotification('Error deleting activities: ' + (data.error || 'Unknown error'), 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNotification('Network error: ' + error.message, 'error');
                });
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.add('hidden');
            activityToDelete = null;
        }

        // Notification function
        function showNotification(message, type = 'info') {
            // Remove existing notification if any
            const existingNotification = document.getElementById('global-notification');
            if (existingNotification) {
                existingNotification.remove();
            }

            const notification = document.createElement('div');
            notification.id = 'global-notification';
            notification.className = `fixed top-4 right-4 z-50 p-4 rounded-lg shadow-lg text-white ${
        type === 'success' ? 'bg-green-500' :
        type === 'error' ? 'bg-red-500' :
        type === 'warning' ? 'bg-yellow-500' :
        'bg-blue-500'
    }`;
            notification.innerHTML = `
        <div class="flex items-center">
            <span>${message}</span>
            <button onclick="this.parentElement.parentElement.remove()" class="ml-4 text-white hover:text-gray-200">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
    `;

            document.body.appendChild(notification);

            // Auto remove after 5 seconds
            setTimeout(() => {
                if (notification.parentElement) {
                    notification.remove();
                }
            }, 5000);
        }

        // Existing modal functions
        function showDetails(activity) {
            const modal = document.getElementById('detailsModal');
            const content = document.getElementById('modalContent');

            // Clean user name for display
            let userName = activity.user_name || 'System';
            userName = userName.replace(/\s*\[ID:\d+\]\s*/, '').trim();

            let formattedDate = 'N/A';
            try {
                const date = new Date(activity.created_at || activity.date);
                if (!isNaN(date.getTime())) {
                    formattedDate = date.toLocaleDateString('en-PH', {
                        year: 'numeric',
                        month: 'long',
                        day: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit',
                        second: '2-digit'
                    });
                }
            } catch (e) {
                console.error('Date formatting error:', e);
            }

            let detailsHtml = `
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div><strong>Activity ID:</strong> ${activity.id}</div>
        <div><strong>User ID:</strong> ${activity.user_id || 'N/A'}</div>
        <div><strong>User Name:</strong> ${userName}</div>
        <div><strong>Target:</strong> ${activity.target || 'System'}</div>
        <div><strong>IP Address:</strong> ${activity.ip_address || 'N/A'}</div>
        <div class="md:col-span-2"><strong>Date:</strong> ${formattedDate}</div>
    </div>
    <div class="mt-4">
        <strong>Details:</strong> 
        <div class="mt-1 p-3 bg-gray-50 dark:bg-gray-700 rounded text-sm">${activity.details || 'No details available'}</div>
    </div>
`;

            if (activity.user_agent && activity.user_agent !== 'N/A' && activity.user_agent !== 'Unknown') {
                detailsHtml += `
        <div class="mt-4">
            <strong>User Agent:</strong>
            <div class="mt-1 p-3 bg-gray-50 dark:bg-gray-700 rounded text-xs overflow-auto max-h-32">
                ${activity.user_agent}
            </div>
        </div>
    `;
            }

            content.innerHTML = detailsHtml;
            modal.classList.remove('hidden');
        }

        function closeDetailsModal() {
            document.getElementById('detailsModal').classList.add('hidden');
        }

        // Close modals when clicking outside
        document.getElementById('detailsModal').addEventListener('click', function(e) {
            if (e.target.id === 'detailsModal') {
                closeDetailsModal();
            }
        });

        document.getElementById('deleteModal').addEventListener('click', function(e) {
            if (e.target.id === 'deleteModal') {
                closeDeleteModal();
            }
        });

        // Load activities when page loads
        document.addEventListener('DOMContentLoaded', function() {
            loadActivities();
        });

        function searchActivities(event) {
            event.preventDefault();
            loadActivities();
        }



        // Add these new functions to your existing JavaScript

        function saveLogs() {
            showNotification('Preparing download...', 'info');

            // Show format selection modal or use default
            showSaveFormatModal();
        }

        function showSaveFormatModal() {
            const modal = document.getElementById('saveFormatModal');
            modal.classList.remove('hidden');
        }

        function closeSaveFormatModal() {
            document.getElementById('saveFormatModal').classList.add('hidden');
        }

        function downloadLogs(format) {
            closeSaveFormatModal();

            const searchTerm = document.getElementById('simple-search').value;

            // Get user ID from URL for authorization
            const urlParams = new URLSearchParams(window.location.search);
            const userId = urlParams.get('uid');

            if (!userId) {
                showNotification('User ID not found. Please refresh the page.', 'error');
                return;
            }

            const params = new URLSearchParams();

            if (searchTerm) {
                params.append('search', searchTerm);
            }
            params.append('export', 'true');
            params.append('format', format);
            params.append('uid', userId); // Add user ID for authorization

            // Get current date for filename
            const currentDate = new Date().toISOString().split('T')[0];
            const filename = `activity_logs_${currentDate}.${format}`;

            showNotification('Preparing download...', 'info');

            // Create a temporary link to trigger download
            const link = document.createElement('a');
            link.href = `/dentalemr_system/php/manageusers/export_activity_logs.php?${params}`;
            link.download = filename;
            link.target = '_blank'; // Open in new tab to avoid navigation issues

            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);

            showNotification(`Logs exported as ${format.toUpperCase()}`, 'success');
        }

        // Enhanced notification function
        function showNotification(message, type = 'info') {
            // Remove existing notification if any
            const existingNotification = document.getElementById('global-notification');
            if (existingNotification) {
                existingNotification.remove();
            }

            const notification = document.createElement('div');
            notification.id = 'global-notification';
            notification.className = `fixed top-4 right-4 z-50 p-4 rounded-lg shadow-lg text-white ${
        type === 'success' ? 'bg-green-500' :
        type === 'error' ? 'bg-red-500' :
        type === 'warning' ? 'bg-yellow-500' :
        'bg-blue-500'
            }`;
            notification.innerHTML = `
                <div class="flex items-center">
                    <span>${message}</span>
                    <button onclick="this.parentElement.parentElement.remove()" class="ml-4 text-white hover:text-gray-200">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
            `;

            document.body.appendChild(notification);

            // Auto remove after 5 seconds
            setTimeout(() => {
                if (notification.parentElement) {
                    notification.remove();
                }
            }, 5000);
        }

        // Add this to your existing DOMContentLoaded event listener
        document.addEventListener('DOMContentLoaded', function() {
            loadActivities();

            // Close save format modal when clicking outside
            document.getElementById('saveFormatModal').addEventListener('click', function(e) {
                if (e.target.id === 'saveFormatModal') {
                    closeSaveFormatModal();
                }
            });
        });
    </script>

    <script>
        function confirmLogout(userId) {
            if (confirm('Are you sure you want to logout?')) {
                window.location.href = '/dentalemr_system/php/login/logout.php?uid=' + userId;
            }
        }
    </script>

    <!-- Load offline storage -->
    <script src="/dentalemr_system/js/offline-storage.js"></script>

    <script>
        // ========== OFFLINE SUPPORT FOR MANAGE USERS - START ==========

        function setupManageUsersOffline() {
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
          <span>Offline Mode - User management disabled</span>
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
            setupManageUsersOffline();

            if ('serviceWorker' in navigator) {
                navigator.serviceWorker.register('/dentalemr_system/sw.js')
                    .then(function(registration) {
                        console.log('SW registered for manage users');
                    })
                    .catch(function(error) {
                        console.log('SW registration failed:', error);
                    });
            }
        });

        // ========== OFFLINE SUPPORT FOR MANAGE USERS - END ==========
    </script>
</body>

</html>