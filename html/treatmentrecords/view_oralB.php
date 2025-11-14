<?php
session_start();
date_default_timezone_set('Asia/Manila');

// REQUIRE userId parameter for each page
// Example usage: dashboard.php?uid=5
if (!isset($_GET['uid'])) {
    echo "<script>
        alert('Invaluid session. Please log in again.');
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
    <title>Patient Treatment Records</title>
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

        <header class="md:ml-64 pt-13 ">
            <nav class="bg-white border-gray-200 dark:bg-gray-800 w-full drop-shadow-sm pb-2">
                <div class="flex flex-col justify-between items-center mx-auto max-w-screen-xl">
                    <div class="flex items-center justify-between lg:order-1 w-full ">
                        <!-- Back Btn-->
                        <div class="relative group inline-block ">
                            <button type="button" onclick="backmain()" class="cursor-pointer">
                                <svg class="w-[35px] h-[35px] text-blue-800 dark:blue-white " aria-hidden="true"
                                    xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none"
                                    viewBox="0 0 24 24">
                                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"
                                        stroke-width="2.5" d="M5 12h14M5 12l4-4m-4 4 4 4" />
                                </svg>
                            </button>
                            <!-- Tooltip -->
                            <span class="absolute left-1/4 -translate-x-1/4  hidden group-hover:block 
                             bg-gray-100/50 text-gray-900 text-sm px-2 py-1 rounded-sm shadow-sm whitespace-nowrap">
                                Go back
                            </span>
                        </div>
                        <p class="text-xl font-semibold px-5  text-gray-900 dark:text-white">Patient Treatment
                            Record
                        </p>
                        <!-- Print Btn -->
                        <a href="" id="printdLink"
                            class="text-white cursor-pointer flex flex-row items-center justify-center gap-1 bg-blue-700 hover:bg-blue-800 font-medium rounded-sm text-xs px-1 lg:py-1 mr-2 mt-1 dark:bg-primary-600 dark:hover:bg-primary-700 focus:outline-none dark:focus:ring-primary-800">
                            <svg class="w-5 h-4 text-primary-800 dark:text-white" aria-hidden="true"
                                xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor"
                                viewBox="0 0 24 24">
                                <path fill-rule="evenodd"
                                    d="M8 3a2 2 0 0 0-2 2v3h12V5a2 2 0 0 0-2-2H8Zm-3 7a2 2 0 0 0-2 2v5a2 2 0 0 0 2 2h1v-4a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v4h1a2 2 0 0 0 2-2v-5a2 2 0 0 0-2-2H5Zm4 11a1 1 0 0 1-1-1v-4h8v4a1 1 0 0 1-1 1H9Z"
                                    clip-rule="evenodd" />
                            </svg>
                            Print
                        </a>
                    </div>
                    <div class="hidden justify-between items-center w-full lg:flex lg:w-auto lg:order-1"
                        id="mobile-menu-2">
                        <ul class="flex flex-col mt-4 font-medium lg:flex-row lg:space-x-8 lg:mt-0">
                            <li>
                                <a href="view_info.php" id="patientInfoLink"
                                    class="block py-2 pr-4 pl-3 text-gray-800 border-b font-semibold border-gray-100 hover:bg-gray-50 lg:hover:bg-transparent lg:border-0 lg:hover:text-primary-700 lg:p-0 dark:text-gray-400 lg:dark:hover:text-white dark:hover:bg-gray-700 dark:hover:text-white lg:dark:hover:bg-transparent dark:border-gray-700">Patient
                                    Information</a>
                            </li>
                            <li>
                                <a href="#"
                                    class="block py-2 pr-4 pl-3 text-blue-800 border-b font-semibold border-gray-100 hover:bg-gray-50 lg:hover:bg-transparent lg:border-0 lg:hover:text-primary-700 lg:p-0 dark:text-gray-400 lg:dark:hover:text-white dark:hover:bg-gray-700 dark:hover:text-white lg:dark:hover:bg-transparent dark:border-gray-700">Oral
                                    Health Condition</a>
                            </li>
                            <li>
                                <a href="#" id="servicesRenderedLink"
                                    class="block py-2 pr-4 pl-3 text-gray-800 border-b font-semibold border-gray-100 hover:bg-gray-50 lg:hover:bg-transparent lg:border-0 lg:hover:text-primary-700 lg:p-0 dark:text-gray-400 lg:dark:hover:text-white dark:hover:bg-gray-700 dark:hover:text-white lg:dark:hover:bg-transparent dark:border-gray-700">Record
                                    of Sevices Rendered</a>
                            </li>
                        </ul>
                    </div>
                </div>
            </nav>
        </header>
        <main class="p-1.5 md:ml-64 h-auto pt-1">
            <section class="bg-white dark:bg-gray-900 p-2 sm:p-2 rounded-lg">
                <div class="items-center justify-between flex flex-row mb-3">
                    <p id="patientName" class="italic text-lg font-medium text-gray-900 dark:text-white mb-2">Loading
                        ...
                    </p>
                    <button type="button" id="addSMC"
                        class="text-white cursor-pointer flex flex-row items-center justify-center gap-1 bg-blue-700 hover:bg-blue-800 font-medium rounded-sm text-xs px-1 lg:py-1 mr-2 dark:bg-primary-600 dark:hover:bg-primary-700 focus:outline-none dark:focus:ring-primary-800">
                        <svg class="h-3.5 w-3.5" fill="currentColor" viewbox="0 0 20 20"
                            xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                            <path clip-rule="evenodd" fill-rule="evenodd"
                                d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" />
                        </svg>
                        Add
                    </button>
                </div>
                <div id="tables-container"
                    class="mx-auto flex flex-col justify-center items-center max-w-screen-xl px-1.5 py-2 lg:px-1.5 bg-white rounded-lg shadow dark:border shadow-stone-300 drop-shadow-sm dark:bg-gray-800 dark:border-gray-950">
                    <div class="items-center justify-between flex flex-row w-full">
                        <p class="text-base font-normal text-gray-950 dark:text-white ">B. Services Monitoring Chart</p>
                    </div>
                    <!-- First table -->
                    <div class="mx-auto max-w-screen-xl px-4 lg:px-12 mt-10">
                        <!-- Start coding here -->
                        <div class="bg-white dark:bg-gray-800 relative shadow-md sm:rounded-lg overflow-hidden">

                            <!-- Table -->
                            <div class="overflow-x-auto">
                                <table id="table-first"
                                    class="w-full text-sm text-left text-gray-500 dark:text-gray-400">
                                    <thead
                                        class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
                                        <tr class="">
                                            <th scope="col" class="px-4 py-3 text-center">Date</th>
                                            <th scope="col" class="px-4 py-3 text-center">55</th>
                                            <th scope="col" class="px-4 py-3 text-center">54</th>
                                            <th scope="col" class="px-4 py-3 text-center">53</th>
                                            <th scope="col" class="px-4 py-3 text-center">52</th>
                                            <th scope="col" class="px-4 py-3 text-center">51</th>
                                            <th scope="col" class="px-4 py-3 text-center">61</th>
                                            <th scope="col" class="px-4 py-3 text-center">62</th>
                                            <th scope="col" class="px-4 py-3 text-center">63</th>
                                            <th scope="col" class="px-4 py-3 text-center">64</th>
                                            <th scope="col" class="px-4 py-3 text-center">65</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <!-- Second table -->
                    <div class="mx-auto max-w-screen-xl px-4 lg:px-12 mt-10">
                        <!-- Start coding here -->
                        <div class="bg-white dark:bg-gray-800 relative shadow-md sm:rounded-lg overflow-hidden">

                            <!-- Table -->
                            <div class="overflow-x-auto">
                                <table id="table-second"
                                    class="w-full text-sm text-left text-gray-500 dark:text-gray-400">
                                    <thead
                                        class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
                                        <tr class="">
                                            <th scope="col" class="px-4 py-3 text-center">Date</th>
                                            <th scope="col" class="px-4 py-3 text-center">85</th>
                                            <th scope="col" class="px-4 py-3 text-center">84</th>
                                            <th scope="col" class="px-4 py-3 text-center">83</th>
                                            <th scope="col" class="px-4 py-3 text-center">82</th>
                                            <th scope="col" class="px-4 py-3 text-center">81</th>
                                            <th scope="col" class="px-4 py-3 text-center">71</th>
                                            <th scope="col" class="px-4 py-3 text-center">72</th>
                                            <th scope="col" class="px-4 py-3 text-center">73</th>
                                            <th scope="col" class="px-4 py-3 text-center">74</th>
                                            <th scope="col" class="px-4 py-3 text-center">75</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <!-- Third table -->
                    <div class="mx-auto max-w-screen-xl px-4 lg:px-12 mt-10">
                        <!-- Start coding here -->
                        <div class="bg-white dark:bg-gray-800 relative shadow-md sm:rounded-lg overflow-hidden">

                            <!-- Table -->
                            <div class="overflow-x-auto">
                                <table id="table-third"
                                    class="w-full text-sm text-left text-gray-500 dark:text-gray-400">
                                    <thead
                                        class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
                                        <tr class="">
                                            <th scope="col" class="px-4 py-3 text-center">Date</th>
                                            <th scope="col" class="px-4 py-3 text-center">18</th>
                                            <th scope="col" class="px-4 py-3 text-center">17</th>
                                            <th scope="col" class="px-4 py-3 text-center">16</th>
                                            <th scope="col" class="px-4 py-3 text-center">15</th>
                                            <th scope="col" class="px-4 py-3 text-center">14</th>
                                            <th scope="col" class="px-4 py-3 text-center">13</th>
                                            <th scope="col" class="px-4 py-3 text-center">12</th>
                                            <th scope="col" class="px-4 py-3 text-center">11</th>
                                            <th scope="col" class="px-4 py-3 text-center">21</th>
                                            <th scope="col" class="px-4 py-3 text-center">22</th>
                                            <th scope="col" class="px-4 py-3 text-center">23</th>
                                            <th scope="col" class="px-4 py-3 text-center">24</th>
                                            <th scope="col" class="px-4 py-3 text-center">25</th>
                                            <th scope="col" class="px-4 py-3 text-center">26</th>
                                            <th scope="col" class="px-4 py-3 text-center">27</th>
                                            <th scope="col" class="px-4 py-3 text-center">28</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <!-- Fourth table -->
                    <div class="mx-auto max-w-screen-xl px-4 lg:px-12 mt-10 mb-10">
                        <!-- Start coding here -->
                        <div class="bg-white dark:bg-gray-800 relative shadow-md sm:rounded-lg overflow-hidden">

                            <!-- Table -->
                            <div class="overflow-x-auto">
                                <table id="table-fourth"
                                    class="w-full text-sm text-left text-gray-500 dark:text-gray-400">
                                    <thead
                                        class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
                                        <tr class="">
                                            <th scope="col" class="px-4 py-3 text-center">Date</th>
                                            <th scope="col" class="px-4 py-3 text-center">48</th>
                                            <th scope="col" class="px-4 py-3 text-center">47</th>
                                            <th scope="col" class="px-4 py-3 text-center">46</th>
                                            <th scope="col" class="px-4 py-3 text-center">45</th>
                                            <th scope="col" class="px-4 py-3 text-center">44</th>
                                            <th scope="col" class="px-4 py-3 text-center">43</th>
                                            <th scope="col" class="px-4 py-3 text-center">42</th>
                                            <th scope="col" class="px-4 py-3 text-center">41</th>
                                            <th scope="col" class="px-4 py-3 text-center">31</th>
                                            <th scope="col" class="px-4 py-3 text-center">32</th>
                                            <th scope="col" class="px-4 py-3 text-center">33</th>
                                            <th scope="col" class="px-4 py-3 text-center">34</th>
                                            <th scope="col" class="px-4 py-3 text-center">35</th>
                                            <th scope="col" class="px-4 py-3 text-center">36</th>
                                            <th scope="col" class="px-4 py-3 text-center">37</th>
                                            <th scope="col" class="px-4 py-3 text-center">38</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="w-full">
                    <div class="flex justify-between mt-5">
                        <button type="button" onclick="back()"
                            class="text-white justify-center  cursor-pointer inline-flex items-center bg-blue-700 hover:bg-blue-800  focus:outline-none focus:ring-blue-300 font-medium rounded-lg text-sm p-1 w-15 dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800">
                            Back
                        </button>
                    </div>
                </div>
            </section>
            <!-- modal  -->
            <div id="SMCModal" tabindex="-1" aria-hidden="true"
                class="fixed inset-0 hidden flex justify-center items-center z-50 bg-gray-600/50">
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg w-full max-w-5xl p-6">
                    <div class="flex flex-row justify-between items-center mb-4">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Add Oral Health Condition</h2>
                        <button type="button" id="cancelMedicalBtn"
                            class="relative cursor-pointer text-gray-500 hover:text-gray-800 dark:hover:text-white"
                            onclick="closeSMC()">
                            ✕
                        </button>
                    </div>
                    <form id="ohcForm" class="space-y-4">
                        <input type="hidden" name="patient_id" id="patient_id" value="">
                        <div>
                            <div class="mb-3">
                                <p class="text-14 font-semibold  text-gray-900 dark:text-white">B. Services Monitoring
                                    Chart
                                </p>
                            </div>
                            <div class="flex flex-col w-full justify-between items-center">
                                <!-- top -->
                                <div class="w-full flex flex-row  gap-10">
                                    <div class="w-160 flex flex-col">
                                        <p style="font-size: 14.2px;"
                                            class=" font-normal text-gray-900 dark:text-white p-1 mb-2"> Fluoride
                                            Varnish/Fluoride Gel, Pit and fissure Sealant, Permanent Filling, temporary
                                            Filling, Extraction
                                        </p>
                                        <div class="flex flex-row justify-between items-center w-full px-1 mb-5">
                                            <div class="flex flex-col items-center gap-2">
                                                <input type="text" data-tooth-id="55" readonly
                                                    class="bg-gray-50 border border-gray-300 w-10 text-gray-900 text-xs rounded-sm focus:ring-primary-600 focus:border-primary-600 p-1 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500">
                                                <label for="name"
                                                    class="flex text-sm font-medium text-gray-900 dark:text-white">55</label>
                                            </div>
                                            <div class="flex flex-col items-center gap-2">
                                                <input type="text" readonly data-tooth-id="54"
                                                    class="bg-gray-50 border border-gray-300 w-10 text-gray-900 text-xs rounded-sm focus:ring-primary-600 focus:border-primary-600 p-1 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500">
                                                <label for="name"
                                                    class="flex text-sm font-medium text-gray-900 dark:text-white">54</label>
                                            </div>
                                            <div class="flex flex-col items-center gap-2">
                                                <input type="text" readonly data-tooth-id="53"
                                                    class="bg-gray-50 border border-gray-300 w-10 text-gray-900 text-xs rounded-sm focus:ring-primary-600 focus:border-primary-600 p-1 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500">
                                                <label for="name"
                                                    class="flex text-sm font-medium text-gray-900 dark:text-white">53</label>
                                            </div>
                                            <div class="flex flex-col items-center gap-2">
                                                <input type="text" readonly data-tooth-id="52"
                                                    class="bg-gray-50 border border-gray-300 w-10 text-gray-900 text-xs rounded-sm focus:ring-primary-600 focus:border-primary-600 p-1 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500">
                                                <label for="name"
                                                    class="flex text-sm font-medium text-gray-900 dark:text-white">52</label>
                                            </div>
                                            <div class="flex flex-col items-center gap-2">
                                                <input type="text" readonly data-tooth-id="51"
                                                    class="bg-gray-50 border border-gray-300 w-10 text-gray-900 text-xs rounded-sm focus:ring-primary-600 focus:border-primary-600 p-1 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500">
                                                <label for="name"
                                                    class="flex text-sm font-medium text-gray-900 dark:text-white">51</label>
                                            </div>
                                            <div class="flex flex-col items-center gap-2">
                                                <input type="text" readonly data-tooth-id="61"
                                                    class="bg-gray-50 border border-gray-300 w-10 text-gray-900 text-xs rounded-sm focus:ring-primary-600 focus:border-primary-600 p-1 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500">
                                                <label for="name"
                                                    class="flex text-sm font-medium text-gray-900 dark:text-white">61</label>
                                            </div>
                                            <div class="flex flex-col items-center gap-2">
                                                <input type="text" readonly data-tooth-id="62"
                                                    class="bg-gray-50 border border-gray-300 w-10 text-gray-900 text-xs rounded-sm focus:ring-primary-600 focus:border-primary-600 p-1 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500">
                                                <label for="name"
                                                    class="flex text-sm font-medium text-gray-900 dark:text-white">62</label>
                                            </div>
                                            <div class="flex flex-col items-center gap-2">
                                                <input type="text" readonly data-tooth-id="63"
                                                    class="bg-gray-50 border border-gray-300 w-10 text-gray-900 text-xs rounded-sm focus:ring-primary-600 focus:border-primary-600 p-1 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500">
                                                <label for="name"
                                                    class="flex text-sm font-medium text-gray-900 dark:text-white">63</label>
                                            </div>
                                            <div class="flex flex-col items-center gap-2">
                                                <input type="text" readonly data-tooth-id="64"
                                                    class="bg-gray-50 border border-gray-300 w-10 text-gray-900 text-xs rounded-sm focus:ring-primary-600 focus:border-primary-600 p-1 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500">
                                                <label for="name"
                                                    class="flex text-sm font-medium text-gray-900 dark:text-white">64</label>
                                            </div>
                                            <div class="flex flex-col items-center gap-2">
                                                <input type="text" readonly data-tooth-id="65"
                                                    class="bg-gray-50 border border-gray-300 w-10 text-gray-900 text-xs rounded-sm focus:ring-primary-600 focus:border-primary-600 p-1 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500">
                                                <label for="name"
                                                    class="flex text-sm font-medium text-gray-900 dark:text-white">65</label>
                                            </div>
                                        </div>
                                        <div class="flex flex-row justify-between items-center w-full px-1">
                                            <div class="flex flex-col items-center gap-2">
                                                <input type="text" readonly data-tooth-id="85"
                                                    class="bg-gray-50 border border-gray-300 w-10 text-gray-900 text-xs rounded-sm focus:ring-primary-600 focus:border-primary-600 p-1 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500"><label
                                                    for="name"
                                                    class="flex text-sm font-medium text-gray-900 dark:text-white">85</label>
                                            </div>
                                            <div class="flex flex-col items-center gap-2">
                                                <input type="text" readonly data-tooth-id="84"
                                                    class="bg-gray-50 border border-gray-300 w-10 text-gray-900 text-xs rounded-sm focus:ring-primary-600 focus:border-primary-600 p-1 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500">
                                                <label for="name"
                                                    class="flex text-sm font-medium text-gray-900 dark:text-white">84</label>
                                            </div>
                                            <div class="flex flex-col items-center gap-2">
                                                <input type="text" readonly data-tooth-id="83"
                                                    class="bg-gray-50 border border-gray-300 w-10 text-gray-900 text-xs rounded-sm focus:ring-primary-600 focus:border-primary-600 p-1 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500">
                                                <label for="name"
                                                    class="flex text-sm font-medium text-gray-900 dark:text-white">83</label>
                                            </div>
                                            <div class="flex flex-col items-center gap-2">
                                                <input type="text" readonly data-tooth-id="82"
                                                    class="bg-gray-50 border border-gray-300 w-10 text-gray-900 text-xs rounded-sm focus:ring-primary-600 focus:border-primary-600 p-1 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500">
                                                <label for="name"
                                                    class="flex text-sm font-medium text-gray-900 dark:text-white">82</label>
                                            </div>
                                            <div class="flex flex-col items-center gap-2">
                                                <input type="text" readonly data-tooth-id="81"
                                                    class="bg-gray-50 border border-gray-300 w-10 text-gray-900 text-xs rounded-sm focus:ring-primary-600 focus:border-primary-600 p-1 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500">
                                                <label for="name"
                                                    class="flex text-sm font-medium text-gray-900 dark:text-white">81</label>
                                            </div>
                                            <div class="flex flex-col items-center gap-2">
                                                <input type="text" readonly data-tooth-id="71"
                                                    class="bg-gray-50 border border-gray-300 w-10 text-gray-900 text-xs rounded-sm focus:ring-primary-600 focus:border-primary-600 p-1 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500">
                                                <label for="name"
                                                    class="flex text-sm font-medium text-gray-900 dark:text-white">71</label>
                                            </div>
                                            <div class="flex flex-col items-center gap-2">
                                                <input type="text" readonly data-tooth-id="72"
                                                    class="bg-gray-50 border border-gray-300 w-10 text-gray-900 text-xs rounded-sm focus:ring-primary-600 focus:border-primary-600 p-1 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500">
                                                <label for="name"
                                                    class="flex text-sm font-medium text-gray-900 dark:text-white">72</label>
                                            </div>
                                            <div class="flex flex-col items-center gap-2">
                                                <input type="text" readonly data-tooth-id="73"
                                                    class="bg-gray-50 border border-gray-300 w-10 text-gray-900 text-xs rounded-sm focus:ring-primary-600 focus:border-primary-600 p-1 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500">
                                                <label for="name"
                                                    class="flex text-sm font-medium text-gray-900 dark:text-white">73</label>
                                            </div>
                                            <div class="flex flex-col items-center gap-2">
                                                <input type="text" readonly data-tooth-id="74"
                                                    class="bg-gray-50 border border-gray-300 w-10 text-gray-900 text-xs rounded-sm focus:ring-primary-600 focus:border-primary-600 p-1 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500">
                                                <label for="name"
                                                    class="flex text-sm font-medium text-gray-900 dark:text-white">74</label>
                                            </div>
                                            <div class="flex flex-col items-center gap-2">
                                                <input type="text" readonly data-tooth-id="75"
                                                    class="bg-gray-50 border border-gray-300 w-10 text-gray-900 text-xs rounded-sm focus:ring-primary-600 focus:border-primary-600 p-1 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500">
                                                <label for="name"
                                                    class="flex text-sm font-medium text-gray-900 dark:text-white">75</label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="controls  flex rounded-sm flex-col ">
                                        <div class="w-full flex flex-col justify-center items-center ">
                                            <div class="flex flex-col gap-0.5">
                                                <p style="font-size: 14.2px;"
                                                    class="text-sm font-medium  text-gray-900 dark:text-white">Legend:
                                                    <span class="font-normal">Treament</span>
                                                </p>
                                                <div class="flex flex-col gap-0.5">
                                                    <p style="font-size: 14.2px;"
                                                        class="text-sm font-normal  text-gray-900 dark:text-white">
                                                        Topical
                                                        Fluoride
                                                        Application:
                                                    </p>
                                                    <p style="font-size: 14.2px;"
                                                        class="text-sm font-normal ml-5 text-gray-900 dark:text-white">
                                                        FV -
                                                        Fluoride
                                                        Varnish
                                                    <p style="font-size: 14.2px;"
                                                        class="text-sm font-normal ml-5 text-gray-900 dark:text-white">
                                                        FG -
                                                        Fluoride
                                                        Gel
                                                    </p>
                                                </div>
                                                <p style="font-size: 14.2px;"
                                                    class="text-sm font-normal  text-gray-900 dark:text-white">PFS - Pit
                                                    and
                                                    Fissure Sealant
                                                </p>
                                                <p style="font-size: 14.2px;"
                                                    class="text-sm font-normal  text-gray-900 dark:text-white">PF -
                                                    Permanent
                                                    Filling (Composite, Am, ART)
                                                </p>
                                                <p style="font-size: 14.2px;"
                                                    class="text-sm font-normal  text-gray-900 dark:text-white">TF -
                                                    Temporary
                                                    Filling
                                                </p>
                                                <p style="font-size: 14.2px;"
                                                    class="text-sm font-normal  text-gray-900 dark:text-white">X -
                                                    Extraction
                                                </p>
                                                <p style="font-size: 14.2px;"
                                                    class="text-sm font-normal  text-gray-900 dark:text-white">O -
                                                    Others
                                                </p>
                                            </div>

                                        </div>
                                    </div>
                                    <div class="w-48 flex flex-col p-2">
                                        <select id="selcttreatment"
                                            class="bg-gray-50 border border-gray-300 w-full text-gray-900 text-xs rounded-sm focus:ring-primary-600 focus:border-primary-600 p-1 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500">
                                            <option value="">-- Select Treatment --</option>
                                            <option value="FV">FV - Fluoride Varnish</option>
                                            <option value="FG">FG - Fluoride Gel</option>
                                            <option value="PFS">PFS - Pit and Fissure Sealant</option>
                                            <option value="PF">PF - Permanent Filling</option>
                                            <option value="TF">TF - Temporary Filling</option>
                                            <option value="X">X - Extraction</option>
                                            <option value="O">O - Others</option>
                                        </select>
                                    </div>
                                </div>
                                <!-- Bot -->
                                <div style="margin-top: -25px;" class="w-full flex flex-row gap-5">
                                    <div class="w-full flex flex-col">
                                        <p style="font-size: 14.2px;"
                                            class=" font-normal text-gray-900 dark:text-white p-1 mb-2"> Fluoride
                                            Varnish/Fluoride Gel, Pit and fissure Sealant, Permanent Filling, temporary
                                            Filling, Extraction
                                        </p>
                                        <div class="flex flex-row justify-between items-center w-full px-1 mb-5">
                                            <div class="flex flex-col items-center gap-2">
                                                <input type="text" readonly data-tooth-id="18"
                                                    class="bg-gray-50 border border-gray-300 w-10 text-gray-900 text-xs rounded-sm focus:ring-primary-600 focus:border-primary-600 p-1 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500">
                                                <label for="name"
                                                    class="flex text-sm font-medium text-gray-900 dark:text-white">18</label>
                                            </div>
                                            <div class="flex flex-col items-center gap-2">
                                                <input type="text" readonly data-tooth-id="17"
                                                    class="bg-gray-50 border border-gray-300 w-10 text-gray-900 text-xs rounded-sm focus:ring-primary-600 focus:border-primary-600 p-1 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500">
                                                <label for="name"
                                                    class="flex text-sm font-medium text-gray-900 dark:text-white">17</label>
                                            </div>
                                            <div class="flex flex-col items-center gap-2">
                                                <input type="text" readonly data-tooth-id="16"
                                                    class="bg-gray-50 border border-gray-300 w-10 text-gray-900 text-xs rounded-sm focus:ring-primary-600 focus:border-primary-600 p-1 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500">
                                                <label for="name"
                                                    class="flex text-sm font-medium text-gray-900 dark:text-white">16</label>
                                            </div>
                                            <div class="flex flex-col items-center gap-2">
                                                <input type="text" readonly data-tooth-id="15"
                                                    class="bg-gray-50 border border-gray-300 w-10 text-gray-900 text-xs rounded-sm focus:ring-primary-600 focus:border-primary-600 p-1 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500">
                                                <label for="name"
                                                    class="flex text-sm font-medium text-gray-900 dark:text-white">15</label>
                                            </div>
                                            <div class="flex flex-col items-center gap-2">
                                                <input type="text" readonly data-tooth-id="14"
                                                    class="bg-gray-50 border border-gray-300 w-10 text-gray-900 text-xs rounded-sm focus:ring-primary-600 focus:border-primary-600 p-1 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500">
                                                <label for="name"
                                                    class="flex text-sm font-medium text-gray-900 dark:text-white">14</label>
                                            </div>
                                            <div class="flex flex-col items-center gap-2">
                                                <input type="text" readonly data-tooth-id="13"
                                                    class="bg-gray-50 border border-gray-300 w-10 text-gray-900 text-xs rounded-sm focus:ring-primary-600 focus:border-primary-600 p-1 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500">
                                                <label for="name"
                                                    class="flex text-sm font-medium text-gray-900 dark:text-white">13</label>
                                            </div>
                                            <div class="flex flex-col items-center gap-2">
                                                <input type="text" readonly data-tooth-id="12"
                                                    class="bg-gray-50 border border-gray-300 w-10 text-gray-900 text-xs rounded-sm focus:ring-primary-600 focus:border-primary-600 p-1 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500">
                                                <label for="name"
                                                    class="flex text-sm font-medium text-gray-900 dark:text-white">12</label>
                                            </div>
                                            <div class="flex flex-col items-center gap-2">
                                                <input type="text" readonly data-tooth-id="11"
                                                    class="bg-gray-50 border border-gray-300 w-10 text-gray-900 text-xs rounded-sm focus:ring-primary-600 focus:border-primary-600 p-1 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500">
                                                <label for="name"
                                                    class="flex text-sm font-medium text-gray-900 dark:text-white">11</label>
                                            </div>
                                            <div class="flex flex-col items-center gap-2">
                                                <input type="text" readonly data-tooth-id="21"
                                                    class="bg-gray-50 border border-gray-300 w-10 text-gray-900 text-xs rounded-sm focus:ring-primary-600 focus:border-primary-600 p-1 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500">
                                                <label for="name"
                                                    class="flex text-sm font-medium text-gray-900 dark:text-white">21</label>
                                            </div>
                                            <div class="flex flex-col items-center gap-2">
                                                <input type="text" readonly data-tooth-id="22"
                                                    class="bg-gray-50 border border-gray-300 w-10 text-gray-900 text-xs rounded-sm focus:ring-primary-600 focus:border-primary-600 p-1 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500">
                                                <label for="name"
                                                    class="flex text-sm font-medium text-gray-900 dark:text-white">22</label>
                                            </div>
                                            <div class="flex flex-col items-center gap-2">
                                                <input type="text" readonly data-tooth-id="23"
                                                    class="bg-gray-50 border border-gray-300 w-10 text-gray-900 text-xs rounded-sm focus:ring-primary-600 focus:border-primary-600 p-1 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500">
                                                <label for="name"
                                                    class="flex text-sm font-medium text-gray-900 dark:text-white">23</label>
                                            </div>
                                            <div class="flex flex-col items-center gap-2">
                                                <input type="text" readonly data-tooth-id="24"
                                                    class="bg-gray-50 border border-gray-300 w-10 text-gray-900 text-xs rounded-sm focus:ring-primary-600 focus:border-primary-600 p-1 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500">
                                                <label for="name"
                                                    class="flex text-sm font-medium text-gray-900 dark:text-white">24</label>
                                            </div>
                                            <div class="flex flex-col items-center gap-2">
                                                <input type="text" readonly data-tooth-id="25"
                                                    class="bg-gray-50 border border-gray-300 w-10 text-gray-900 text-xs rounded-sm focus:ring-primary-600 focus:border-primary-600 p-1 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500">
                                                <label for="name"
                                                    class="flex text-sm font-medium text-gray-900 dark:text-white">25</label>
                                            </div>
                                            <div class="flex flex-col items-center gap-2">
                                                <input type="text" readonly data-tooth-id="26"
                                                    class="bg-gray-50 border border-gray-300 w-10 text-gray-900 text-xs rounded-sm focus:ring-primary-600 focus:border-primary-600 p-1 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500">
                                                <label for="name"
                                                    class="flex text-sm font-medium text-gray-900 dark:text-white">26</label>
                                            </div>
                                            <div class="flex flex-col items-center gap-2">
                                                <input type="text" readonly data-tooth-id="27"
                                                    class="bg-gray-50 border border-gray-300 w-10 text-gray-900 text-xs rounded-sm focus:ring-primary-600 focus:border-primary-600 p-1 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500">
                                                <label for="name"
                                                    class="flex text-sm font-medium text-gray-900 dark:text-white">27</label>
                                            </div>
                                            <div class="flex flex-col items-center gap-2">
                                                <input type="text" readonly data-tooth-id="28"
                                                    class="bg-gray-50 border border-gray-300 w-10 text-gray-900 text-xs rounded-sm focus:ring-primary-600 focus:border-primary-600 p-1 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500">
                                                <label for="name"
                                                    class="flex text-sm font-medium text-gray-900 dark:text-white">28</label>
                                            </div>
                                        </div>
                                        <div class="flex flex-row justify-between items-center w-full px-1 mb-3">
                                            <div class="flex flex-col items-center gap-2">
                                                <input type="text" readonly data-tooth-id="48"
                                                    class="bg-gray-50 border border-gray-300 w-10 text-gray-900 text-xs rounded-sm focus:ring-primary-600 focus:border-primary-600 p-1 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500">
                                                <label for="name"
                                                    class="flex text-sm font-medium text-gray-900 dark:text-white">48</label>
                                            </div>
                                            <div class="flex flex-col items-center gap-2">
                                                <input type="text" readonly data-tooth-id="47"
                                                    class="bg-gray-50 border border-gray-300 w-10 text-gray-900 text-xs rounded-sm focus:ring-primary-600 focus:border-primary-600 p-1 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500">
                                                <label for="name"
                                                    class="flex text-sm font-medium text-gray-900 dark:text-white">47</label>
                                            </div>
                                            <div class="flex flex-col items-center gap-2">
                                                <input type="text" readonly data-tooth-id="46"
                                                    class="bg-gray-50 border border-gray-300 w-10 text-gray-900 text-xs rounded-sm focus:ring-primary-600 focus:border-primary-600 p-1 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500">
                                                <label for="name"
                                                    class="flex text-sm font-medium text-gray-900 dark:text-white">46</label>
                                            </div>
                                            <div class="flex flex-col items-center gap-2">
                                                <input type="text" readonly data-tooth-id="45"
                                                    class="bg-gray-50 border border-gray-300 w-10 text-gray-900 text-xs rounded-sm focus:ring-primary-600 focus:border-primary-600 p-1 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500">
                                                <label for="name"
                                                    class="flex text-sm font-medium text-gray-900 dark:text-white">45</label>
                                            </div>
                                            <div class="flex flex-col items-center gap-2">
                                                <input type="text" readonly data-tooth-id="44"
                                                    class="bg-gray-50 border border-gray-300 w-10 text-gray-900 text-xs rounded-sm focus:ring-primary-600 focus:border-primary-600 p-1 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500">
                                                <label for="name"
                                                    class="flex text-sm font-medium text-gray-900 dark:text-white">44</label>
                                            </div>
                                            <div class="flex flex-col items-center gap-2">
                                                <input type="text" readonly data-tooth-id="43"
                                                    class="bg-gray-50 border border-gray-300 w-10 text-gray-900 text-xs rounded-sm focus:ring-primary-600 focus:border-primary-600 p-1 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500">
                                                <label for="name"
                                                    class="flex text-sm font-medium text-gray-900 dark:text-white">43</label>
                                            </div>
                                            <div class="flex flex-col items-center gap-2">
                                                <input type="text" readonly data-tooth-id="42"
                                                    class="bg-gray-50 border border-gray-300 w-10 text-gray-900 text-xs rounded-sm focus:ring-primary-600 focus:border-primary-600 p-1 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500">
                                                <label for="name"
                                                    class="flex text-sm font-medium text-gray-900 dark:text-white">42</label>
                                            </div>
                                            <div class="flex flex-col items-center gap-2">
                                                <input type="text" readonly data-tooth-id="41"
                                                    class="bg-gray-50 border border-gray-300 w-10 text-gray-900 text-xs rounded-sm focus:ring-primary-600 focus:border-primary-600 p-1 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500">
                                                <label for="name"
                                                    class="flex text-sm font-medium text-gray-900 dark:text-white">41</label>
                                            </div>
                                            <div class="flex flex-col items-center gap-2">
                                                <input type="text" readonly data-tooth-id="31"
                                                    class="bg-gray-50 border border-gray-300 w-10 text-gray-900 text-xs rounded-sm focus:ring-primary-600 focus:border-primary-600 p-1 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500">
                                                <label for="name"
                                                    class="flex text-sm font-medium text-gray-900 dark:text-white">31</label>
                                            </div>
                                            <div class="flex flex-col items-center gap-2">
                                                <input type="text" readonly data-tooth-id="32"
                                                    class="bg-gray-50 border border-gray-300 w-10 text-gray-900 text-xs rounded-sm focus:ring-primary-600 focus:border-primary-600 p-1 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500">
                                                <label for="name"
                                                    class="flex text-sm font-medium text-gray-900 dark:text-white">32</label>
                                            </div>
                                            <div class="flex flex-col items-center gap-2">
                                                <input type="text" readonly data-tooth-id="33"
                                                    class="bg-gray-50 border border-gray-300 w-10 text-gray-900 text-xs rounded-sm focus:ring-primary-600 focus:border-primary-600 p-1 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500">
                                                <label for="name"
                                                    class="flex text-sm font-medium text-gray-900 dark:text-white">33</label>
                                            </div>
                                            <div class="flex flex-col items-center gap-2">
                                                <input type="text" readonly data-tooth-id="34"
                                                    class="bg-gray-50 border border-gray-300 w-10 text-gray-900 text-xs rounded-sm focus:ring-primary-600 focus:border-primary-600 p-1 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500">
                                                <label for="name"
                                                    class="flex text-sm font-medium text-gray-900 dark:text-white">34</label>
                                            </div>
                                            <div class="flex flex-col items-center gap-2">
                                                <input type="text" readonly data-tooth-id="35"
                                                    class="bg-gray-50 border border-gray-300 w-10 text-gray-900 text-xs rounded-sm focus:ring-primary-600 focus:border-primary-600 p-1 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500">
                                                <label for="name"
                                                    class="flex text-sm font-medium text-gray-900 dark:text-white">35</label>
                                            </div>
                                            <div class="flex flex-col items-center gap-2">
                                                <input type="text" readonly data-tooth-id="36"
                                                    class="bg-gray-50 border border-gray-300 w-10 text-gray-900 text-xs rounded-sm focus:ring-primary-600 focus:border-primary-600 p-1 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500">
                                                <label for="name"
                                                    class="flex text-sm font-medium text-gray-900 dark:text-white">36</label>
                                            </div>
                                            <div class="flex flex-col items-center gap-2">
                                                <input type="text" readonly data-tooth-id="37"
                                                    class="bg-gray-50 border border-gray-300 w-10 text-gray-900 text-xs rounded-sm focus:ring-primary-600 focus:border-primary-600 p-1 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500">
                                                <label for="name"
                                                    class="flex text-sm font-medium text-gray-900 dark:text-white">37</label>
                                            </div>
                                            <div class="flex flex-col items-center gap-2">
                                                <input type="text" readonly data-tooth-id="38"
                                                    class="bg-gray-50 border border-gray-300 w-10 text-gray-900 text-xs rounded-sm focus:ring-primary-600 focus:border-primary-600 p-1 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500">
                                                <label for="name"
                                                    class="flex text-sm font-medium text-gray-900 dark:text-white">38</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="flex justify-end gap-2">
                            <button type="button" onclick="saveSMC()"
                                class="text-white justify-center cursor-pointer inline-flex items-center bg-blue-700 hover:bg-blue-800 focus:outline-none focus:ring-blue-300 font-medium rounded-lg text-sm p-1 w-18 dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800">
                                Save
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            <div id="notice"
                style="position:fixed; top:14px; right:14px; display:none; padding:10px 14px; border-radius:6px; background:blue; color:white; z-index:60">
            </div>
        </main>
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
                window.location.href = "/dentalemr_system/php/login/logout.php?uid=<?php echo $userId; ?>";
            }, inactivityTime);
        }

        ["click", "mousemove", "keypress", "scroll", "touchstart"].forEach(evt => {
            document.addEventListener(evt, resetTimer, false);
        });

        resetTimer();
    </script>

    <script>
        function backmain() {
            location.href = ("treatmentrecords.php?uid=<?php echo $userId; ?>");
        }
    </script>

    <script>
        const params = new URLSearchParams(window.location.search);
        const patientId = params.get('id');

        const patientInfoLink = document.getElementById("patientInfoLink");
        if (patientInfoLink && patientId) {
            patientInfoLink.href = `view_info.php?uid=<?php echo $userId; ?>&id=${encodeURIComponent(patientId)}`;
        } else {
            patientInfoLink.addEventListener("click", (e) => {
                e.preventDefault();
                alert("Please select a patient first.");
            });
        }

        const servicesRenderedLink = document.getElementById("servicesRenderedLink");
        if (servicesRenderedLink && patientId) {
            servicesRenderedLink.href = `view_record.php?uid=<?php echo $userId; ?>&id=${encodeURIComponent(patientId)}`;
        } else {
            // Optional fallback: disable link if no patient selected
            servicesRenderedLink.addEventListener("click", (e) => {
                e.preventDefault();
                alert("Please select a patient first.");
            });
        }

        function back() {
            // Get patient ID from URL
            const params = new URLSearchParams(window.location.search);
            const patientId = params.get("id");

            if (!patientId) {
                alert("Missing patient ID.");
                return;
            }
            // Navigate to view_oralA.php while keeping patient ID in the URL
            window.location.href = `view_oralA.php?uid=<?php echo $userId; ?>&id=${encodeURIComponent(patientId)}`;
        }
        const printdLink = document.getElementById("printdLink");
        if (printdLink && patientId) {
            printdLink.href = `print.php?uid=<?php echo $userId; ?>&id=${encodeURIComponent(patientId)}`;
        } else {
            // Optional fallback: disable link if no patient selected
            printdLink.addEventListener("click", (e) => {
                e.preventDefault();
                alert("Please select a patient first.");
            });
        }
    </script>

    <!-- table fetch  -->
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const urlParams = new URLSearchParams(window.location.search);
            const patientId = urlParams.get("id");

            if (!patientId) {
                console.error("No patient ID provided in URL");
                return;
            }

            fetch(`/dentalemr_system/php/treatmentrecords/view_oralB.php?patient_id=${patientId}`)
                .then(response => response.json())
                .then(data => {
                    if (!data.records) data.records = [];

                    // Set patient name
                    const nameEl = document.getElementById("patientName");
                    if (nameEl && data.patient_name) {
                        nameEl.textContent = data.patient_name;
                    }

                    populateTables(data.records);
                })
                .catch(error => console.error('Error loading data:', error));
        });

        function populateTables(records) {
            const tableGroups = {
                "table-first": [55, 54, 53, 52, 51, 61, 62, 63, 64, 65],
                "table-second": [85, 84, 83, 82, 81, 71, 72, 73, 74, 75],
                "table-third": [18, 17, 16, 15, 14, 13, 12, 11, 21, 22, 23, 24, 25, 26, 27, 28],
                "table-fourth": [48, 47, 46, 45, 44, 43, 42, 41, 31, 32, 33, 34, 35, 36, 37, 38]
            };

            // Group by date and FDI number
            const groupedByDate = {};
            records.forEach(row => {
                const date = new Date(row.created_at).toLocaleDateString();
                if (!groupedByDate[date]) groupedByDate[date] = {};
                groupedByDate[date][row.fdi_number] = row.treatment_code;
            });

            for (const [tableId, teeth] of Object.entries(tableGroups)) {
                const tbody = document.querySelector(`#${tableId} tbody`);
                tbody.innerHTML = "";

                for (const date of Object.keys(groupedByDate)) {
                    const tr = document.createElement("tr");
                    tr.classList.add("border-b", "border-gray-200", "dark:border-gray-700");

                    // Date cell
                    const th = document.createElement("th");
                    th.className = "px-4 py-3 text-center font-medium text-gray-900 dark:text-white";
                    th.textContent = date;
                    tr.appendChild(th);

                    // Tooth cells (match FDI numbers)
                    teeth.forEach(fdi => {
                        const td = document.createElement("td");
                        td.className = "px-4 py-3 text-center font-medium text-gray-900 dark:text-white";
                        td.textContent = groupedByDate[date][fdi] || "";
                        tr.appendChild(td);
                    });

                    tbody.appendChild(tr);
                }

                // If no records at all, show empty row
                if (Object.keys(groupedByDate).length === 0) {
                    const tr = document.createElement("tr");
                    tr.classList.add("border-b", "border-gray-200", "dark:border-gray-700");

                    const th = document.createElement("th");
                    th.className = "px-4 py-3 text-center font-medium text-gray-900 dark:text-white";
                    th.textContent = "";
                    tr.appendChild(th);

                    teeth.forEach(() => {
                        const td = document.createElement("td");
                        td.className = "px-4 py-3 text-center font-medium text-gray-900 dark:text-white";
                        td.textContent = "";
                        tr.appendChild(td);
                    });

                    tbody.appendChild(tr);
                }
            }
        }
    </script>

    <script>
        const notice = document.getElementById("notice");

        function showNotice(message, color = "blue") {
            notice.textContent = message;
            notice.style.background = color;
            notice.style.display = "block";
            notice.style.opacity = "1";

            setTimeout(() => {
                notice.style.transition = "opacity 0.6s";
                notice.style.opacity = "0";
                setTimeout(() => {
                    notice.style.display = "none";
                    notice.style.transition = "";
                }, 1500);
            }, 5000);
        }

        let selectedTreatmentCode = null;

        // Treatment dropdown selection
        document.getElementById("selcttreatment").addEventListener("change", function() {
            selectedTreatmentCode = this.value;
        });

        // Initialize tooth click events
        function initSMCTreatmentClick() {
            const toothInputs = document.querySelectorAll("#SMCModal input[data-tooth-id]");
            toothInputs.forEach(input => {
                const newInput = input.cloneNode(true);
                input.parentNode.replaceChild(newInput, input);
            });

            document.querySelectorAll("#SMCModal input[data-tooth-id]").forEach(input => {
                input.addEventListener("click", () => {
                    if (!selectedTreatmentCode) return showNotice("Please select a treatment first!", "red");
                    input.value = selectedTreatmentCode;
                    input.dataset.treatmentId = selectedTreatmentCode;
                    input.style.backgroundColor = "#e5e7eb";
                });

                input.addEventListener("dblclick", () => {
                    input.value = "";
                    delete input.dataset.treatmentId;
                    input.style.backgroundColor = "white";
                });
            });
        }

        // Open modal and load today's treatments
        document.getElementById("addSMC").addEventListener("click", async () => {
            const patientId = new URLSearchParams(window.location.search).get("id");
            if (!patientId) return showNotice("No patient selected", "red");

            document.getElementById("patient_id").value = patientId;

            // Get today's date in YYYY-MM-DD format
            const today = new Date().toISOString().split('T')[0];

            try {
                const response = await fetch(`/dentalemr_system/php/treatmentrecords/get_today_smc.php?patient_id=${patientId}&date=${today}`);
                const data = await response.json();

                // Clear all previous inputs first
                document.querySelectorAll("#SMCModal input[data-tooth-id]").forEach(input => {
                    input.value = "";
                    delete input.dataset.treatmentId;
                    input.style.backgroundColor = "white";
                });

                // Fill modal inputs with today's records if available
                if (data.records && data.records.length > 0) {
                    data.records.forEach(rec => {
                        const input = document.querySelector(`#SMCModal input[data-tooth-id='${rec.fdi_number}']`);
                        if (input) {
                            input.value = rec.treatment_code;
                            input.dataset.treatmentId = rec.treatment_code;
                            input.style.backgroundColor = "#e5e7eb";
                        }
                    });
                }

                document.getElementById("SMCModal").classList.remove("hidden");
                initSMCTreatmentClick();
            } catch (err) {
                console.error("Failed to load today's treatments", err);
                showNotice("❌ Failed to load today's treatments", "red");
            }
        });


        // Save SMC with update or insert logic
        function saveSMC() {
            const patientId = document.getElementById("patient_id").value;
            if (!patientId) return showNotice("Patient ID not set", "red");

            const today = new Date().toISOString().split('T')[0]; // YYYY-MM-DD
            const treatments = [];

            document.querySelectorAll("#SMCModal input[data-tooth-id]").forEach(input => {
                treatments.push({
                    tooth_id: input.dataset.toothId,
                    treatment_code: input.dataset.treatmentId || "" // empty if cleared
                });
            });

            fetch("/dentalemr_system/php/treatmentrecords/add_or_update_vieworalB.php", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json"
                    },
                    body: JSON.stringify({
                        patient_id: parseInt(patientId),
                        treatments,
                        date: today
                    })
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        showNotice(data.message, "blue");
                        document.getElementById("SMCModal").classList.add("hidden");
                        refreshTables(); // reload tables dynamically
                    } else {
                        showNotice("❌ " + data.message, "red");
                    }
                })
                .catch(err => {
                    console.error("Save SMC error:", err);
                    showNotice("❌ Request failed. Check console.", "red");
                });
        }


        // Close modal
        function closeSMC() {
            document.getElementById("SMCModal").classList.add("hidden");
        }
    </script>

    <script>
        function refreshTables() {
            const urlParams = new URLSearchParams(window.location.search);
            const patientId = urlParams.get("id");
            if (!patientId) return;

            fetch(`/dentalemr_system/php/treatmentrecords/view_oralB.php?patient_id=${patientId}`)
                .then(response => response.json())
                .then(data => {
                    if (!data.records) data.records = [];
                    populateTables(data.records); // Reuse your existing function
                })
                .catch(error => {
                    console.error('Error refreshing tables:', error);
                    showNotice("❌ Failed to refresh tables", "red");
                });
        }
    </script>


</body>

</html>