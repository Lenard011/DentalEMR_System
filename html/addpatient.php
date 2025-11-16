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
    <title>Add patient</title>
    <!-- <link href="../css/style.css" rel="stylesheet"> -->
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <style>
        /* Popup overlay */
        #validationPopup {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.3);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 9999;
        }

        /* Popup box */
        .popup-content {
            background: #fff;
            padding: 20px 30px;
            border-radius: 12px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
            font-family: Arial, sans-serif;
            max-width: 600px;
            width: 90%;
        }

        .popup-content p {
            font-size: 14px;
            margin-bottom: 15px;
            color: #222;
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
                        <a href="index.php?uid=<?php echo $userId; ?>"
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
                        <a href="#"
                            class="flex items-center p-2 text-base font-medium text-blue-600 rounded-lg dark:text-blue bg-blue-100  dark:hover:bg-blue-700 group">
                            <svg aria-hidden="true"
                                class="w-6 h-6 text-blue-600 transition duration-75 dark:text-blue-400  dark:group-hover:text-blue"
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
                                <a href="./treatmentrecords/treatmentrecords.php?uid=<?php echo $userId; ?>"
                                    class="flex items-center p-2 pl-11 w-full text-base font-medium text-gray-900 rounded-lg transition duration-75 group hover:bg-gray-100 dark:text-white dark:hover:bg-gray-700">Treatment
                                    Records</a>
                            </li>
                            <li>
                                <a href="./addpatienttreatment/patienttreatment.php?uid=<?php echo $userId; ?>"
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
            <section class="bg-gray-50 dark:bg-gray-900 p-3 sm:p-5">
                <div class="mx-auto max-w-screen-xl px-4 lg:px-12">
                    <!-- Start coding here -->
                    <div class="bg-white dark:bg-gray-800 relative shadow-md sm:rounded-lg ">
                        <div>
                            <p class="text-2xl font-semibold px-5 mt-5 text-gray-900 dark:text-white">Patient List</p>
                        </div>
                        <div
                            class="flex flex-col md:flex-row items-center justify-between space-y-3 md:space-y-0 md:space-x-4 p-4">
                            <div class="w-full md:w-1/2">
                                <form class="flex items-center">
                                    <label for="simple-search" class="sr-only">Search</label>
                                    <div class="relative w-full">
                                        <div
                                            class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                            <svg aria-hidden="true" class="w-5 h-5 text-gray-500 dark:text-gray-400"
                                                fill="currentColor" viewbox="0 0 20 20"
                                                xmlns="http://www.w3.org/2000/svg">
                                                <path fill-rule="evenodd"
                                                    d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z"
                                                    clip-rule="evenodd" />
                                            </svg>
                                        </div>
                                        <input type="text" id="simple-search"
                                            class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full pl-10 p-2 dark:bg-gray-700 dark:border-blue-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500"
                                            placeholder="Search" required="">
                                    </div>
                                </form>
                            </div>
                            <div
                                class="w-full md:w-auto flex flex-col md:flex-row space-y-2 md:space-y-0 items-stretch md:items-center justify-end md:space-x-3 flex-shrink-0">
                                <button type="button" id="Addpatientbtn" data-modal-target="addpatientModal"
                                    data-modal-toggle="addpatientModal" class=" flex items-center justify-center cursor-pointer text-white bg-blue-700
                                    hover:bg-blue-800 font-medium rounded-lg text-sm px-4 py-2 dark:bg-blue-600
                                    dark:hover:bg-blue-700">
                                    <svg class="h-3.5 w-3.5 mr-2" fill="currentColor" viewbox="0 0 20 20"
                                        xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                        <path clip-rule="evenodd" fill-rule="evenodd"
                                            d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" />
                                    </svg>
                                    Add Patient
                                </button>
                                <!-- Filter -->
                                <div class="relative flex items-center space-x-3 w-full md:w-auto">
                                    <button id="filterDropdownButton" data-dropdown-toggle="filterDropdown"
                                        class="w-full md:w-auto cursor-pointer flex items-center justify-center py-2 px-4 text-sm font-medium text-gray-900 focus:outline-none bg-white rounded-lg border border-gray-200 hover:bg-gray-100 hover:text-primary-700 focus:z-10  dark:bg-gray-800 dark:text-gray-400 dark:border-gray-600 dark:hover:text-white dark:hover:bg-gray-700"
                                        type="button">
                                        <svg xmlns="http://www.w3.org/2000/svg" aria-hidden="true"
                                            class="h-4 w-4 mr-2 text-gray-400" viewbox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd"
                                                d="M3 3a1 1 0 011-1h12a1 1 0 011 1v3a1 1 0 01-.293.707L12 11.414V15a1 1 0 01-.293.707l-2 2A1 1 0 018 17v-5.586L3.293 6.707A1 1 0 013 6V3z"
                                                clip-rule="evenodd" />
                                        </svg>
                                        Filter
                                        <svg class="-mr-1 ml-1.5 w-5 h-5" fill="currentColor" viewbox="0 0 20 20"
                                            xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                            <path clip-rule="evenodd" fill-rule="evenodd"
                                                d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" />
                                        </svg>
                                    </button>
                                    <div id="filterDropdown"
                                        class="absolute z-9999 hidden w-48 p-3 bg-white rounded-lg shadow dark:bg-gray-700">
                                        <h6 class="mb-3 text-sm font-medium text-gray-900 dark:text-white">Filter by
                                            address
                                        </h6>
                                        <ul class="space-y-2 text-sm" id="filterAddresses"
                                            aria-labelledby="filterDropdownButton">

                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- Table -->
                        <div class="overflow-x-auto">
                            <table id="patientsTable" class="w-full text-sm text-left text-gray-500 dark:text-gray-400">
                                <thead
                                    class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
                                    <tr>
                                        <th class="px-4 py-3 text-center">ID</th>
                                        <th class="px-4 py-3 text-center">Name</th>
                                        <th class="px-4 py-3 text-center">Sex</th>
                                        <th class="px-4 py-3 text-center">Age</th>
                                        <th class="px-4 py-3 text-center">Address</th>
                                        <th class="px-4 py-3 text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="patientsBody">
                                    <tr class="border-b dark:border-gray-700">
                                        <td
                                            class="px-4 py-3 text-center font-medium text-gray-900 whitespace-nowrap dark:text-white">
                                            Loading ...</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <nav id="paginationNav"
                            class="flex flex-col md:flex-row justify-between items-start md:items-center space-y-3 md:space-y-0 p-3"
                            aria-label="Table navigation">
                        </nav>
                    </div>
                </div>
            </section>
            <!-- Add patient Modal -->
            <form id="patientForm" method="POST">
                <!-- FirstModal -->
                <div id="addpatientModal" tabindex="-1" aria-hidden="true"
                    class="hidden realative overflow-y-hidden overflow-x-hidden fixed top-0 right-0 left-0 z-50 justify-center items-center w-auto md:inset-y-13  max-h-150 md:h-150 ">
                    <div class="relative overflow-auto  w-full max-w-4xl h-full md:h-auto">
                        <!-- Modal content -->
                        <div class="scroll relative border-gray-900 bg-white rounded-lg shadow dark:bg-gray-800 sm:p-2">
                            <!-- Modal header -->
                            <div
                                class="flex justify-between items-center pb-4 rounded-t border-b sm:mb-2 dark:border-gray-600">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                                    Patient Registration
                                </h3>
                                <button type="button"
                                    class="text-gray-400 bg-transparent cursor-pointer hover:bg-gray-200 hover:text-gray-900 rounded-lg text-sm p-1.5 ml-auto inline-flex items-center dark:hover:bg-gray-600 dark:hover:text-white"
                                    data-modal-toggle="addpatientModal">
                                    <svg aria-hidden="true" class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"
                                        xmlns="http://www.w3.org/2000/svg">
                                        <path fill-rule="evenodd"
                                            d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                            clip-rule="evenodd"></path>
                                    </svg>
                                    <span class="sr-only">Close modal</span>
                                </button>
                            </div>
                            <!-- Modal body -->
                            <div class=" text-center mb-5 mt-0.5">
                                <p class="text-lg font-semibold mt-5 text-gray-900 dark:text-white">
                                    Individual Patient Treatment Record</p>
                            </div>
                            <div class="flex flex-row items-center justify-between  w-full gap-2 mb-4">
                                <!-- First Col -->
                                <div class="flex items-center flex-col w-full gap-2 ">
                                    <!-- Name -->
                                    <div>
                                        <label for="name"
                                            class="block mb-2 text-xs font-medium text-gray-900 dark:text-white">Name</label>
                                        <div class="gap-1 sm:grid-cols-3  w-130 flex justify-between items-center">
                                            <input type="text" name="surname" data-required data-label="Surname"
                                                class="bg-gray-50 border border-gray-300 text-gray-900 text-xs rounded-sm focus:ring-primary-600 focus:border-primary-600 block w-full p-1 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500"
                                                placeholder="Surname">
                                            <input type="text" name="firstname" data-required data-label="Firstname"
                                                class="bg-gray-50 border border-gray-300 text-gray-900 text-xs rounded-sm focus:ring-primary-600 focus:border-primary-600 block w-full p-1 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500"
                                                placeholder="First name">
                                            <input type="text" name="middlename" data-required data-label="Middlename"
                                                class="bg-gray-50 border border-gray-300 text-gray-900 text-xs rounded-sm focus:ring-primary-600 focus:border-primary-600 block w-26 p-1 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500"
                                                placeholder="Middle initial">
                                        </div>
                                    </div>
                                    <!-- PlaceofBirth&Address -->
                                    <div class="w-full">
                                        <label for="name"
                                            class="block mb-2 text-xs font-medium text-gray-900 dark:text-white">Place
                                            of Birth</label>
                                        <input type="text" name="pob" data-required data-label="Place of Birth"
                                            class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-sm focus:ring-primary-600 focus:border-primary-600 block w-full p-1 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500">
                                    </div>
                                    <div class="w-full">
                                        <label for="name"
                                            class="block mb-2 text-xs font-medium text-gray-900 dark:text-white">Address</label>
                                        <select name="address" data-required data-label="Address"
                                            class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-sm focus:ring-primary-600 focus:border-primary-600 block w-full p-1 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500">
                                            <option selected>-- Select Address --</option>
                                            <option value="Balansay">Balansay</option>
                                            <option value="Fatima">Fatima</option>
                                            <option value="Payompon">Payompon</option>
                                            <option value="Poblacion 1">Poblacion 1</option>
                                            <option value="Poblacion 2">Poblacion 2</option>
                                            <option value="Poblacion 3">Poblacion 3</option>
                                            <option value="Poblacion 4">Poblacion 4</option>
                                            <option value="Poblacion 5">Poblacion 5</option>
                                            <option value="Poblacion 6">Poblacion 6</option>
                                            <option value="Poblacion 7">Poblacion 7</option>
                                            <option value="Poblacion 8">Poblacion 8</option>
                                            <option value="San Luis">San Luis</option>
                                            <option value="Talabaan">Talabaan</option>
                                            <option value="Tangkalan">Tangkalan</option>
                                            <option value="Tayamaan">Tayamaan</option>
                                        </select>
                                    </div>
                                </div>
                                <!-- Second Col -->
                                <div class="flex items-center flex-col w-full gap-2 ">
                                    <!-- DateofBirth -->
                                    <div class="w-full">
                                        <label for="date"
                                            class="block mb-2 text-xs font-medium text-gray-900 dark:text-white">Date of
                                            Birth</label>
                                        <input type="date" id="dob" name="dob" data-required data-label="date of Birth"
                                            class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-600 focus:border-primary-600 block w-full p-1 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500">
                                    </div>
                                    <!-- Age,Sex,Pregnant -->
                                    <div id="form-container" class="grid grid-cols-2 gap-4 w-full">
                                        <!-- Age -->
                                        <div class="flex flex-row items-center justify-between gap-2 ">
                                            <div class="age-wrapper w-full ">
                                                <label for="age"
                                                    class="block mb-2 text-xs font-medium   text-gray-900 dark:text-white">Age</label>
                                                <input type="number" id="age" name="age" min="0" data-required
                                                    data-label="Age"
                                                    class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-sm focus:ring-primary-600 w-full focus:border-primary-600 block  p-1 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500">
                                            </div>
                                            <div id="monthContainer" class="age-wrapper w-full ">
                                                <label for="agemonth"
                                                    class="block mb-2 text-xs font-medium  text-gray-900 dark:text-white">Month</label>
                                                <input type="number" id="agemonth" name="agemonth" min="0" max="59"
                                                    data-label="AgeMonth"
                                                    class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-sm focus:ring-primary-600 w-full focus:border-primary-600 block  p-1 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500">
                                            </div>
                                        </div>
                                        <!-- Sex -->
                                        <div id="sex-wrapper">
                                            <label for="sex"
                                                class="block mb-2 text-xs font-medium text-gray-900 dark:text-white">Sex</label>
                                            <select id="sex" name="sex" data-required data-label="Sex"
                                                class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-sm focus:ring-primary-600 focus:border-primary-600 block w-full p-1 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500">
                                                <option value="">-- Select --</option>
                                                <option value="Male">Male</option>
                                                <option value="Female">Female</option>
                                                <option value="Ohter">Other</option>
                                            </select>
                                        </div>

                                        <!-- Pregnant (hidden by default) -->
                                        <div id="pregnant-section" class="hidden">
                                            <label
                                                class="block mb-2 text-xs font-medium text-gray-900 dark:text-white">Pregnant</label>
                                            <div class="flex flex-row gap-2 items-center">
                                                <div class="flex items-center">
                                                    <input id="pregnant-yes" type="radio" value="Yes" name="pregnant"
                                                        disabled
                                                        class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 focus:ring-blue-500">
                                                    <label for="pregnant-yes"
                                                        class="ms-2 text-sm font-medium text-gray-900 dark:text-gray-300">Yes</label>
                                                </div>
                                                <div class="flex items-center">
                                                    <input id="pregnant-no" type="radio" value="No" name="pregnant"
                                                        checked disabled
                                                        class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 focus:ring-blue-500">
                                                    <label for="pregnant-no"
                                                        class="ms-2 text-sm font-medium text-gray-900 dark:text-gray-300">No</label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- Occupation&Parent/guardian -->
                                    <div class="flex flex-row w-full gap-2">
                                        <!-- Occupation -->
                                        <div class="">
                                            <label for="name"
                                                class="block mb-2 text-xs font-medium text-gray-900 dark:text-white">Occupation</label>
                                            <input type="text" name="occupation" data-required data-label="Occupation"
                                                class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-sm focus:ring-primary-600 focus:border-primary-600 block w-full p-1 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500"
                                                placeholder="">
                                        </div>
                                        <!-- Parent/guardian -->
                                        <div class="">
                                            <label for="name"
                                                class="block mb-2 text-xs font-medium text-gray-900 dark:text-white">Parent/Guardian</label>
                                            <input type="text" name="guardian" data-required
                                                data-label="Parent/Guardian"
                                                class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-sm focus:ring-primary-600 focus:border-primary-600 block w-full p-1 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500"
                                                placeholder="">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- Other Patient Info -->
                            <div class="grid mb-4 gap-2">
                                <p class="text-14 font-semibold text-gray-900 dark:text-white">Other Patient Information
                                    (Membership)
                                </p>
                                <div>
                                    <div class="flex items-center mb-1">
                                        <input type="checkbox" value="1" name="nhts_pr"
                                            class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded-sm focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600">
                                        <label for="default-checkbox"
                                            class="ms-2 text-sm font-medium text-gray-900 dark:text-gray-300">National
                                            Household Targeting System - Poverty Reduction (NHTS-PR)</label>
                                    </div>
                                    <div class="flex items-center mb-1">
                                        <input type="checkbox" value="1" name="four_ps"
                                            class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded-sm focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600">
                                        <label for="default-checkbox"
                                            class="ms-2 text-sm font-medium text-gray-900 dark:text-gray-300">Pantawid
                                            Pamilyang Pilipino Program (4Ps)</label>
                                    </div>
                                    <div class="flex items-center mb-1">
                                        <input type="checkbox" value="1" name="indigenous_people"
                                            class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded-sm focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600">
                                        <label for="default-checkbox"
                                            class="ms-2 text-sm font-medium text-gray-900 dark:text-gray-300">Indigenous
                                            People (IP)</label>
                                    </div>
                                    <div class="flex items-center mb-1">
                                        <input type="checkbox" value="1" name="pwd"
                                            class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded-sm focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600">
                                        <label for="default-checkbox"
                                            class="ms-2 text-sm font-medium text-gray-900 dark:text-gray-300">Person
                                            With Disabilities (PWDs)</label>
                                    </div>
                                    <div class="flex items-center mb-1">
                                        <input type="checkbox" value="1" name="philhealth_flag"
                                            onchange="toggleInput(this, 'philhealth_number')"
                                            class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded-sm focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600">
                                        <div class="grid grid-cols-2 items-center gap-4">
                                            <label for="default-checkbox"
                                                class="ms-2 text-sm font-medium text-gray-900 dark:text-gray-300">PhilHealth
                                                (Indicate Number)</label>
                                            <input type="text" id="philhealth_number" name="philhealth_number" disabled
                                                class="block py-1 h-4.5 px-0 w-full text-sm text-gray-900 bg-transparent border-0 border-b-2 border-gray-300 appearance-none dark:text-white dark:border-gray-600 dark:focus:border-blue-500 focus:outline-none focus:ring-0 focus:border-blue-600 peer"
                                                placeholder="" />
                                        </div>
                                    </div>
                                    <div class="flex items-center mb-1">
                                        <input type="checkbox" name="sss_flag" value="1"
                                            onchange="toggleInput(this, 'sss_number')"
                                            class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded-sm focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600">
                                        <div class="grid grid-cols-2 items-center gap-1">
                                            <label for="default-checkbox"
                                                class="ms-2 not-last-of-type:w-40 text-sm font-medium text-gray-900 dark:text-gray-300">SSS
                                                (Indicate Number)</label>
                                            <input type="text" id="sss_number" name="sss_number" disabled
                                                class="block py-1 h-4.5 px-0 w-50 text-sm text-gray-900 bg-transparent border-0 border-b-2 border-gray-300 appearance-none dark:text-white dark:border-gray-600 dark:focus:border-blue-500 focus:outline-none focus:ring-0 focus:border-blue-600 peer"
                                                placeholder="" />
                                        </div>
                                    </div>
                                    <div class="flex items-center mb-1">
                                        <input type="checkbox" name="gsis_flag" value="1"
                                            onchange="toggleInput(this, 'gsis_number')"
                                            class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded-sm focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600">
                                        <div class="grid grid-cols-2 items-center gap-1">
                                            <label for="default-checkbox"
                                                class="ms-2 w-40 text-sm font-medium text-gray-900 dark:text-gray-300">GSIS
                                                (Indicate Number)</label>
                                            <input type="text" id="gsis_number" name="gsis_number" disabled
                                                class="block py-1 px-0 h-4.5 w-50 text-sm text-gray-900 bg-transparent border-0 border-b-2 border-gray-300 appearance-none dark:text-white dark:border-gray-600 dark:focus:border-blue-500 focus:outline-none focus:ring-0 focus:border-blue-600 peer"
                                                placeholder="" />
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="flex justify-end">
                                <button type="button" id="Addpatientbtn2" onclick="validateStep(1)"
                                    data-modal-hide="addpatientModal" data-modal-target="addpatientModal2"
                                    data-modal-toggle="addpatientModal2"
                                    class="text-white justify-center  cursor-pointer inline-flex items-center bg-blue-700 hover:bg-blue-800  focus:outline-none focus:ring-blue-300 font-medium rounded-lg text-sm p-1 w-15 dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800">
                                    Next
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Second Modal -->
                <div id="addpatientModal2" tabindex="-1" aria-hidden="true"
                    class="hidden overflow-y-hidden overflow-x-hidden fixed top-0 right-0 left-0 z-50 justify-center items-center w-auto md:inset-y-13  max-h-152 md:h-152 ">
                    <div class="relative  w-full max-w-4xl h-full md:h-auto">
                        <!-- Modal content -->
                        <div class="scroll relative border-gray-900 bg-white rounded-lg shadow dark:bg-gray-800 sm:p-2">
                            <!-- Modal header -->
                            <div
                                class="flex justify-between items-center pb-4 rounded-t border-b sm:mb-2 dark:border-gray-600">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                                    Patient Registration
                                </h3>
                                <button type="button"
                                    class="text-gray-400 bg-transparent  cursor-pointer hover:bg-gray-200 hover:text-gray-900 rounded-lg text-sm p-1.5 ml-auto inline-flex items-center dark:hover:bg-gray-600 dark:hover:text-white"
                                    data-modal-toggle="addpatientModal2">
                                    <svg aria-hidden="true" class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"
                                        xmlns="http://www.w3.org/2000/svg">
                                        <path fill-rule="evenodd"
                                            d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                            clip-rule="evenodd"></path>
                                    </svg>
                                    <span class="sr-only">Close modal</span>
                                </button>
                            </div>
                            <!-- Modal body -->
                            <div class=" text-center mb-5 mt-0.5">
                                <p class="text-lg font-semibold mt-5 text-gray-900 dark:text-white">
                                    Individual Patient Treatment Record</p>
                            </div>

                            <p class="text-14 font-semibold text-gray-900 dark:text-white">Vital Signs
                            </p>
                            <div class="grid gap-2 mb-4 w-full">
                                <div class="flex items-center justify-between 1 gap-2 w-full">
                                    <div class="w-full">
                                        <label for="name"
                                            class="block mb-2 text-xs font-medium text-gray-900 dark:text-white">Blood
                                            Preassure</label>
                                        <input type="text" name="blood_pressure" data-required
                                            data-label="Blood Pressure"
                                            class="bg-gray-50 border border-gray-300 text-gray-900 text-xs rounded-sm focus:ring-primary-600 focus:border-primary-600 block w-full p-1 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500"
                                            placeholder="">
                                    </div>
                                    <div class="w-full">
                                        <label for="name"
                                            class="block mb-2 text-xs font-medium text-gray-900 dark:text-white">Temperature</label>
                                        <input type="number" step="0.1" name="temperature" data-required
                                            data-label="Temperature"
                                            class="bg-gray-50 border border-gray-300 text-gray-900 text-xs rounded-sm focus:ring-primary-600 focus:border-primary-600 block w-full p-1 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500"
                                            placeholder="">
                                    </div>
                                    <div class="w-full">
                                        <label for="name"
                                            class="block mb-2 text-xs font-medium text-gray-900 dark:text-white">Pulse
                                            Rate</label>
                                        <input type="number" name="pulse_rate" data-required data-label="Pulse Rate"
                                            class="bg-gray-50 border border-gray-300 text-gray-900 text-xs rounded-sm focus:ring-primary-600 focus:border-primary-600 block w-full p-1 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500"
                                            placeholder="">
                                    </div>
                                    <div class="w-full">
                                        <label for="name"
                                            class="block mb-2 text-xs font-medium text-gray-900 dark:text-white">Weight</label>
                                        <input type="number" step="0.01" name="weight" data-required data-label="Weight"
                                            class="bg-gray-50 border border-gray-300 text-gray-900 text-xs rounded-sm focus:ring-primary-600 focus:border-primary-600 block w-full p-1 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500"
                                            placeholder="">
                                    </div>
                                </div>
                            </div>
                            <div class=" overflow-auto h-[310px] mb-3">
                                <!-- Medical  History -->
                                <div class="grid mb-4 gap-2 ">
                                    <p class="text-14 font-semibold text-gray-900 dark:text-white">Medical History
                                    </p>
                                    <div>
                                        <div class="flex w-125  items-center mb-1">
                                            <input type="checkbox" name="allergies_flag" value="1"
                                                onchange="toggleInput(this, 'allergies_details')"
                                                class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded-sm focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600">
                                            <div class="grid grid-cols-2 items-center gap-1">
                                                <label for="default-checkbox"
                                                    class="ms-2 text-sm font-medium text-gray-900 dark:text-gray-300">Allergies
                                                    (Please specify)</label>
                                                <input type="text" id="allergies_details" name="allergies_details"
                                                    disabled
                                                    class="block py-1 h-4.5 px-0 w-59 text-sm text-gray-900 bg-transparent border-0 border-b-2 border-gray-300 appearance-none dark:text-white dark:border-gray-600 dark:focus:border-blue-500 focus:outline-none focus:ring-0 focus:border-blue-600 peer"
                                                    placeholder=" " />
                                            </div>
                                        </div>
                                        <div class="flex items-center mb-1">
                                            <input type="checkbox" name="hypertension_cva" value="1"
                                                class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded-sm focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600">
                                            <label for="default-checkbox"
                                                class="ms-2 text-sm font-medium text-gray-900 dark:text-gray-300">Hypertension
                                                / CVA</label>
                                        </div>
                                        <div class="flex items-center mb-1">
                                            <input type="checkbox" name="diabetes_mellitus" value="1"
                                                class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded-sm focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600">
                                            <label for="default-checkbox"
                                                class="ms-2 text-sm font-medium text-gray-900 dark:text-gray-300">Diabetes
                                                Mellitus</label>
                                        </div>
                                        <div class="flex items-center mb-1">
                                            <input type="checkbox" name="blood_disorders" value="1"
                                                class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded-sm focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600">
                                            <label for="default-checkbox"
                                                class="ms-2 text-sm font-medium text-gray-900 dark:text-gray-300">Blood
                                                Disorders</label>
                                        </div>
                                        <div class="flex items-center mb-1">
                                            <input type="checkbox" name="heart_disease" value="1"
                                                class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded-sm focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600">
                                            <label for="default-checkbox"
                                                class="ms-2 text-sm font-medium text-gray-900 dark:text-gray-300">cardiovarscular
                                                / Heart Diseases</label>
                                        </div>
                                        <div class="flex items-center mb-1">
                                            <input type="checkbox" name="thyroid_disorders" value="1"
                                                class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded-sm focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600">
                                            <label for="default-checkbox"
                                                class="ms-2 text-sm font-medium text-gray-900 dark:text-gray-300">Thyroid
                                                Disorders</label>
                                        </div>
                                        <div class="flex w-125  items-center mb-1">
                                            <input type="checkbox" name="hepatitis_flag" value="1"
                                                onchange="toggleInput(this, 'hepatitis_details')"
                                                class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded-sm focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600">
                                            <div class="grid grid-cols-2 items-center gap-1">
                                                <label for="default-checkbox"
                                                    class="ms-2 text-sm w-50  font-medium text-gray-900 dark:text-gray-300">Hepatitis
                                                    (Please specify type)</label>
                                                <input type="text" id="hepatitis_details" name="hepatitis_details"
                                                    disabled
                                                    class="block py-1 h-4.5 px-0 w-59 text-sm text-gray-900 bg-transparent border-0 border-b-2 border-gray-300 appearance-none dark:text-white dark:border-gray-600 dark:focus:border-blue-500 focus:outline-none focus:ring-0 focus:border-blue-600 peer"
                                                    placeholder=" " />
                                            </div>
                                        </div>
                                        <div class="flex w-125  items-center mb-1">
                                            <input type="checkbox" name="malignancy_flag" value="1"
                                                onchange="toggleInput(this, 'malignancy_details')"
                                                class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded-sm focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600">
                                            <div class="grid grid-cols-2 items-center gap-1">
                                                <label for="default-checkbox"
                                                    class="ms-2 w-50 text-sm  font-medium text-gray-900 dark:text-gray-300">Malignancy
                                                    (Please specify)</label>
                                                <input type="text" id="malignancy_details" name="malignancy_details"
                                                    disabled
                                                    class="block py-1 h-4.5 px-0 w-59 text-sm text-gray-900 bg-transparent border-0 border-b-2 border-gray-300 appearance-none dark:text-white dark:border-gray-600 dark:focus:border-blue-500 focus:outline-none focus:ring-0 focus:border-blue-600 peer"
                                                    placeholder=" " />
                                            </div>
                                        </div>
                                        <div class="flex items-center mb-1">
                                            <input type="checkbox" name="prev_hospitalization_flag" value="1"
                                                onchange="toggleHospitalization(this)"
                                                class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded-sm focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600">
                                            <div class="grid grid-cols-2 items-center gap-1">
                                                <label for="default-checkbox"
                                                    class="ms-2  w-100 text-sm font-medium text-gray-900 dark:text-gray-300">History
                                                    of Previous Hospitalization:</label>
                                            </div>
                                        </div>
                                        <div class="flex flex-col w-120  ml-4 ">
                                            <label for="default-checkbox"
                                                class="ms-2 w-55 text-sm font-medium text-gray-900 dark:text-gray-300">
                                                Medical </label>
                                            <div class="ms-4 flex flex-row items-center w-full gap-2">
                                                <div class="flex flex-row items-center  gap-1 ">
                                                    <label
                                                        class="w-27 text-sm font-medium text-gray-900 dark:text-gray-300 ">
                                                        Last Admission:</label>
                                                    <input type="date" id="last_admission_date"
                                                        name="last_admission_date" disabled
                                                        class="block py-1 px-0 h-4.5  text-sm text-gray-900 bg-transparent border-0 border-b-2 border-gray-300 appearance-none dark:text-white dark:border-gray-600 dark:focus:border-blue-500 focus:outline-none focus:ring-0 focus:border-blue-600 peer"
                                                        placeholder="" />
                                                </div>
                                                <span>&</span>
                                                <div class="flex flex-row items-center w-52 ">
                                                    <label
                                                        class="w-15 text-sm font-medium text-gray-900 dark:text-gray-300 ">
                                                        Cause:</label>
                                                    <input type="text" id="admission_cause" name="admission_cause"
                                                        disabled
                                                        class="block py-1 px-0 h-4.5 w-35.5 text-sm text-gray-900 bg-transparent border-0 border-b-2 border-gray-300 appearance-none dark:text-white dark:border-gray-600 dark:focus:border-blue-500 focus:outline-none focus:ring-0 focus:border-blue-600 peer"
                                                        placeholder="" />
                                                </div>
                                            </div>
                                        </div>
                                        <div class="grid w-120 grid-cols-2 gap-1 ml-4">
                                            <label for="default-checkbox"
                                                class="ms-2 w-55 text-sm font-medium text-gray-900 dark:text-gray-300">
                                                Surgical (Post-Operative)</label>
                                            <input type="text" id="surgery_details" name="surgery_details" disabled
                                                class="block py-1 px-0 h-4.5 text-sm text-gray-900 bg-transparent border-0 border-b-2 border-gray-300 appearance-none dark:text-white dark:border-gray-600 dark:focus:border-blue-500 focus:outline-none focus:ring-0 focus:border-blue-600 peer"
                                                placeholder=" " />
                                        </div>
                                        <div class="flex w-125 items-center  mb-1">
                                            <input type="checkbox" name="blood_transfusion_flag" value="1"
                                                onchange="toggleInput(this, 'blood_transfusion_date')"
                                                class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded-sm focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600">
                                            <div class="grid grid-cols-2 items-center gap-1">
                                                <label for="default-checkbox"
                                                    class="ms-2 not-last-of-type:w-40 text-sm font-medium text-gray-900 dark:text-gray-300">Blood
                                                    transfusion (Month & Year)</label>
                                                <input type="text" id="blood_transfusion_date"
                                                    name="blood_transfusion_date" disabled
                                                    class="block py-1 h-4.5 px-0 w-59.5 text-sm text-gray-900 bg-transparent border-0 border-b-2 border-gray-300 appearance-none dark:text-white dark:border-gray-600 dark:focus:border-blue-500 focus:outline-none focus:ring-0 focus:border-blue-600 peer"
                                                    placeholder=" " />
                                            </div>
                                        </div>
                                        <div class="flex w-120 items-center mb-1">
                                            <input type="checkbox" name="tattoo" value="1"
                                                class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded-sm focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600">
                                            <label for="default-checkbox"
                                                class="ms-2 text-sm font-medium text-gray-900 dark:text-gray-300">Tattoo</label>
                                        </div>
                                    </div>
                                    <div class="flex w-125  items-center mb-1">
                                        <input type="checkbox" name="other_conditions_flag" value="1"
                                            onchange="toggleInput(this, 'other_conditions')"
                                            class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded-sm focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600">
                                        <div class="grid grid-cols-2 items-center gap-">
                                            <label for="default-checkbox"
                                                class="ms-2 w-40  text-sm font-medium text-gray-900 dark:text-gray-300">Others
                                                (Please specify)</label>
                                            <input type="text" id="other_conditions" name="other_conditions" disabled
                                                class="block py-1 h-4.5 px-0 w-60 text-sm text-gray-900 bg-transparent border-0 border-b-2 border-gray-300 appearance-none dark:text-white dark:border-gray-600 dark:focus:border-blue-500 focus:outline-none focus:ring-0 focus:border-blue-600 peer"
                                                placeholder=" " />
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="flex justify-between w-full">
                                <button type="button" id="Addpatientbtnb" data-modal-target="addpatientModal"
                                    data-modal-toggle="addpatientModal" data-modal-hide="addpatientModal2"
                                    class="text-white justify-center  cursor-pointer inline-flex items-center bg-blue-700 hover:bg-blue-800  focus:outline-none focus:ring-blue-300 font-medium rounded-lg text-sm p-1 w-15 dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800">
                                    Back
                                </button><button type="button" id="Addpatientbtn2" onclick="validateSte(2)"
                                    data-modal-hide="addpatientModal2" data-modal-target="addpatientModal3"
                                    data-modal-toggle="addpatientModal3"
                                    class="text-white justify-center  cursor-pointer inline-flex items-center bg-blue-700 hover:bg-blue-800  focus:outline-none focus:ring-blue-300 font-medium rounded-lg text-sm p-1 w-15 dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800">
                                    Next
                                </button>
                            </div>
                        </div>

                    </div>
                </div>
                <!-- Third Modal -->
                <div id="addpatientModal3" tabindex="-1" aria-hidden="true"
                    class="hidden overflow-y-auto overflow-x-hidden fixed top-0 right-0 left-0 z-50 justify-center items-center w-full md:inset-y-15.5 max-h-152 md:h-152">
                    <div class="relative pt-2.5 w-full max-w-4xl h-full md:h-900px">
                        <!-- Modal content -->
                        <div
                            class="scroll relative p-2 border-gray-900 bg-white rounded-lg shadow dark:bg-gray-800 sm:p-2">
                            <!-- Modal header -->
                            <div
                                class="flex justify-between items-center pb-4 rounded-t border-b sm:mb-2 dark:border-gray-600">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                                    Patient Registration
                                </h3>
                                <button type="button"
                                    class="text-gray-400 bg-transparent  cursor-pointer hover:bg-gray-200 hover:text-gray-900 rounded-lg text-sm p-1.5 ml-auto inline-flex items-center dark:hover:bg-gray-600 dark:hover:text-white"
                                    data-modal-toggle="addpatientModal3">
                                    <svg aria-hidden="true" class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"
                                        xmlns="http://www.w3.org/2000/svg">
                                        <path fill-rule="evenodd"
                                            d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                            clip-rule="evenodd"></path>
                                    </svg>
                                    <span class="sr-only">Close modal</span>
                                </button>
                            </div>
                            <div class=" text-center mb-5 mt-0.5">
                                <p class="text-lg font-semibold mt-5 text-gray-900 dark:text-white">
                                    Individual Patient Treatment Record</p>
                            </div>
                            <div>
                                <p class="text-14 font-semibold text-gray-900 dark:text-white">Dietary Habits / Social
                                    History
                                </p>
                                <div class="grid mb-4 gap-2">
                                    <div>
                                        <div class="flex items-center mb-1">
                                            <input type="checkbox" name="sugar_flag" value="1"
                                                onchange="toggleInput(this, 'sugar_details')"
                                                class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded-sm focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600">
                                            <div class="grid grid-cols-2 ">
                                                <label for="default-checkbox"
                                                    class="ms-2 text-sm w-90 font-medium text-gray-900 dark:text-gray-300">Sugar
                                                    Sweetened Beverages/Food (Amount, Frequency & Duration)</label>
                                                <input type="text" id="sugar_details" name="sugar_details" disabled
                                                    class="block py-1 h-4.5 px-0 w-70 text-sm text-gray-900 bg-transparent border-0 border-b-2 border-gray-300 appearance-none dark:text-white dark:border-gray-600 dark:focus:border-blue-500 focus:outline-none focus:ring-0 focus:border-blue-600 peer"
                                                    placeholder=" " />
                                            </div>
                                        </div>
                                        <div class="flex items-center mb-1">
                                            <input type="checkbox" name="alcohol_flag" value="1"
                                                onchange="toggleInput(this, 'alcohol_details')"
                                                class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded-sm focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600">
                                            <div class="grid grid-cols-2 gap-6.5">
                                                <label for="default-checkbox"
                                                    class="ms-2 text-sm font-medium text-gray-900 dark:text-gray-300">Use
                                                    of
                                                    Alcohol (Amount, Frequency & Duration)</label>
                                                <input type="text" id="alcohol_details" name="alcohol_details" disabled
                                                    class="block py-1 px-0 h-4.5 w-full text-sm text-gray-900 bg-transparent border-0 border-b-2 border-gray-300 appearance-none dark:text-white dark:border-gray-600 dark:focus:border-blue-500 focus:outline-none focus:ring-0 focus:border-blue-600 peer"
                                                    placeholder=" " />
                                            </div>
                                        </div>
                                        <div class="flex items-center mb-1">
                                            <input type="checkbox" name="tobacco_flag" value="1"
                                                onchange="toggleInput(this, 'tobacco_details')"
                                                class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded-sm focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600">
                                            <div class="grid grid-cols-2 gap-5.5">
                                                <label for="default-checkbox"
                                                    class="ms-2 text-sm font-medium text-gray-900 dark:text-gray-300">Use
                                                    of
                                                    Tobacco (Amount, Frequency & Duration)</label>
                                                <input type="text" id="tobacco_details" name="tobacco_details" disabled
                                                    class="block py-1 h-4.5 px-0 w-78 text-sm text-gray-900 bg-transparent border-0 border-b-2 border-gray-300 appearance-none dark:text-white dark:border-gray-600 dark:focus:border-blue-500 focus:outline-none focus:ring-0 focus:border-blue-600 peer"
                                                    placeholder=" " />
                                            </div>
                                        </div>
                                        <div class="flex items-center mb-1">
                                            <input type="checkbox" name="betel_nut_flag" value="1"
                                                onchange="toggleInput(this, 'betel_nut_details')"
                                                class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded-sm focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600">
                                            <div class="grid grid-cols-2 gap-4">
                                                <label for="default-checkbox"
                                                    class="ms-2 text-sm font-medium text-gray-900 dark:text-gray-300">Betel
                                                    Nut Chewing (Amount, Frequency & Duration)</label>
                                                <input type="text" id="betel_nut_details" name="betel_nut_details"
                                                    disabled
                                                    class="block py-1 h-4.5 px-0 w-73.5 text-sm text-gray-900 bg-transparent border-0 border-b-2 border-gray-300 appearance-none dark:text-white dark:border-gray-600 dark:focus:border-blue-500 focus:outline-none focus:ring-0 focus:border-blue-600 peer"
                                                    placeholder=" " />
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div>

                                </div>
                                <div class="flex justify-between w-full">
                                    <button type="button" id="Addpatientbtnb2" data-modal-target="addpatientModal2"
                                        data-modal-toggle="addpatientModal2" data-modal-hide="addpatientModal3"
                                        class="text-white justify-center  cursor-pointer inline-flex items-center bg-blue-700 hover:bg-blue-800  focus:outline-none focus:ring-blue-300 font-medium rounded-lg text-sm p-1 w-15 dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800">
                                        Back
                                    </button>
                                    <button type="submit" name="patient"
                                        class="text-white justify-center  cursor-pointer inline-flex items-center bg-blue-700 hover:bg-blue-800  focus:outline-none focus:ring-blue-300 font-medium rounded-lg text-sm p-1 w-15 dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800">
                                        Submit
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
            <!-- popup -->
            <div id="popupContainer" style="display:none; position: fixed; top:0; left:0; width:100%; height:100%;
                background: rgba(0,0,0,0.2); backdrop-filter: blur(10px); justify-content: center; align-items: center; z-index:9999;">
                <div style="background:#fff; padding:20px 30px; border-radius:12px; text-align:center;
                box-shadow:0 5px 15px rgba(0,0,0,0.3); font-family: Arial, sans-serif;">
                    <p id="popupTitle" style="font-weight:bold; margin-bottom:10px;"></p>
                    <p id="popupMessage" style="margin-bottom:15px;"></p>
                    <button id="popupOkBtn" style="padding:8px 16px; border:none; border-radius:6px; cursor:pointer; color:#fff;">OK</button>
                </div>
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
                window.location.href = "/dentalemr_system/php/login/logout.php";
            }, inactivityTime);
        }

        ["click", "mousemove", "keypress", "scroll", "touchstart"].forEach(evt => {
            document.addEventListener(evt, resetTimer, false);
        });

        resetTimer();
    </script>
    <script>
        function toggleInput(checkbox, inputId) {
            document.getElementById(inputId).disabled = !checkbox.checked;
        }
    </script>

    <script>
        function toggleHospitalization(checkbox) {
            const fields = [
                "last_admission_date",
                "admission_cause",
                "surgery_details"
            ];
            fields.forEach(id => {
                document.getElementById(id).disabled = !checkbox.checked;
            });
        }
        form.onsubmit = function(e) {
            e.preventDefault(); // <-- stops submission
        }
    </script>

    <!-- ShowPregnant Input -->
    <script>
        const ageInput = document.getElementById('age');
        const sexInput = document.getElementById('sex');
        const formContainer = document.getElementById('form-container');
        const pregnantSection = document.getElementById('pregnant-section');
        const pregnantRadios = pregnantSection.querySelectorAll('input[name="pregnant"]');

        function togglePregnantSection() {
            const age = parseInt(ageInput.value, 10);
            const sex = sexInput.value;

            if (sex === 'Female' && age >= 10 && age <= 49) {
                pregnantSection.classList.remove('hidden');
                formContainer.classList.replace('grid-cols-2', 'grid-cols-3'); // 🔑 make 3 columns
                pregnantRadios.forEach(radio => {
                    radio.disabled = false;
                    radio.required = true;
                });
            } else {
                pregnantSection.classList.add('hidden');
                formContainer.classList.replace('grid-cols-3', 'grid-cols-2'); // 🔑 back to 2 columns
                pregnantRadios.forEach(radio => {
                    radio.disabled = true;
                    radio.required = false;
                    radio.checked = radio.value === "no";
                });
            }
        }
        ageInput.addEventListener('input', togglePregnantSection);
        sexInput.addEventListener('change', togglePregnantSection);
    </script>

    <!-- Table  -->
    <script>
        const API_PATH = "../php/register_patient/getPatients.php";
        let currentSearch = "";
        let currentPage = 1;
        let limit = 10;
        let selectedAddresses = [];

        // FIXED debounce utility
        function debounce(fn, delay = 300) {
            let t;
            return (...args) => {
                clearTimeout(t);
                t = setTimeout(() => fn.apply(this, args), delay);
            };
        }

        // render helper
        function showMessageInTable(html) {
            document.getElementById("patientsBody").innerHTML =
                `<tr><td colspan="6" class="text-center py-6">${html}</td></tr>`;
        }

        // escape HTML
        function escapeHtml(str) {
            if (str === null || str === undefined) return "";
            return String(str)
                .replaceAll("&", "&amp;")
                .replaceAll("<", "&lt;")
                .replaceAll(">", "&gt;")
                .replaceAll('"', "&quot;")
                .replaceAll("'", "&#039;");
        }

        // load patients
        async function loadPatients(page = 1) {
            currentPage = page;
            const url = `${API_PATH}?page=${page}&limit=${limit}&search=${encodeURIComponent(currentSearch)}&addresses=${encodeURIComponent(selectedAddresses.join(","))}`;

            try {
                const res = await fetch(url, {
                    cache: "no-store"
                });
                if (!res.ok) {
                    showMessageInTable("Server error: " + res.status);
                    return;
                }
                const data = await res.json();
                if (!data.patients || data.patients.length === 0) {
                    showMessageInTable("No patients found.");
                    document.getElementById("paginationNav").innerHTML = "";
                    return;
                }

                const tbody = document.getElementById("patientsBody");
                tbody.innerHTML = "";
                data.patients.forEach((p, index) => {
                    const displayId = (currentPage - 1) * limit + index + 1;
                    tbody.insertAdjacentHTML("beforeend", `
                <tr class="border-b border-gray-200 dark:border-gray-700">
                    <td class="px-4 py-3 text-center font-medium text-gray-700 whitespace-nowrap dark:text-white">${displayId}</td>
                    <td class="px-4 py-3 text-center font-medium text-gray-700 whitespace-nowrap dark:text-white">${escapeHtml(p.surname)}, ${escapeHtml(p.firstname)} ${p.middlename ? escapeHtml(p.middlename) : ""}</td>
                    <td class="px-4 py-3 text-center font-medium text-gray-700 whitespace-nowrap dark:text-white">${escapeHtml(p.sex)}</td>
                    <td class="px-4 py-3 text-center font-medium text-gray-700 whitespace-nowrap dark:text-white">${escapeHtml(String(p.age))}</td>
                    <td class="px-4 py-3 text-center font-medium text-gray-700 whitespace-nowrap dark:text-white">${escapeHtml(p.address)}</td>
                    <td class="px-4 py-3 text-center font-medium text-gray-700 whitespace-nowrap dark:text-white">
                        <button onclick="window.location.href='viewrecord.php?uid=<?php echo $userId; ?>&id=${encodeURIComponent(p.patient_id)}'"
                            class="text-white cursor-pointer bg-blue-700 hover:bg-blue-800 font-medium rounded-lg text-xs px-3 py-2">
                            View
                        </button>
                    </td>
                </tr>
            `);
                });

                renderPagination(data.total, data.limit, data.page);
                renderFilterAddresses(data.addresses);

            } catch (err) {
                console.error(err);
                showMessageInTable("Error loading data.");
            }
        }

        // pagination renderer
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
            if (page > 1) {
                pagesHTML += `<li><a href="#" onclick="loadPatients(${page - 1}); return false;" class="flex items-center justify-center h-full py-1.5 px-2 ml-0 text-gray-500 bg-white rounded-l-sm border border-gray-300 hover:bg-gray-100 hover:text-gray-700 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white">
            <svg class="w-5 h-5" aria-hidden="true" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
            </svg></a></li>`;
            }
            for (let i = 1; i <= totalPages; i++) {
                pagesHTML += (i === page) ?
                    `<li><span class="flex items-center justify-center text-sm z-10 py-2 px-3 text-blue-600 bg-blue-50 border border-blue-300 hover:bg-blue-100 hover:text-blue-700 dark:border-gray-700 dark:bg-gray-700 dark:text-white">${i}</span></li>` :
                    `<li><a href="#" onclick="loadPatients(${i}); return false;" class="flex items-center justify-center text-sm py-2 px-3 
                 text-gray-500 bg-white border border-gray-300 hover:bg-gray-100 hover:text-gray-700 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white">${i}</a></li>`;
            }
            if (page < totalPages) {
                pagesHTML += `<li><a href="#" onclick="loadPatients(${page + 1}); return false;" class="flex items-center justify-center h-full py-1.5 px-2 leading-tight text-gray-500 bg-white rounded-r-sm border border-gray-300 hover:bg-gray-100 hover:text-gray-700 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white"> 
        <svg class="w-5 h-5" aria-hidden="true" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
            <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
        </svg></a></li>`;
            }

            paginationNav.innerHTML = `${showingText} <ul class="inline-flex -space-x-1px">${pagesHTML}</ul>`;
        }

        // filter addresses dropdown
        function renderFilterAddresses(addresses) {
            const container = document.getElementById("filterAddresses");
            container.innerHTML = "";
            addresses.forEach(addr => {
                const checked = selectedAddresses.includes(addr) ? "checked" : "";
                container.insertAdjacentHTML("beforeend", `
            <li>
                <label class="flex items-center space-x-2">
                <input type="checkbox" value="${escapeHtml(addr)}" ${checked}
                        class="address-filter">
                <span class="text-gray-700 dark:text-gray-200">${escapeHtml(addr)}</span>
                </label>
            </li>
        `);
            });

            document.querySelectorAll(".address-filter").forEach(cb => {
                cb.addEventListener("change", () => {
                    selectedAddresses = Array.from(document.querySelectorAll(".address-filter:checked")).map(x => x.value);
                    loadPatients(1);
                });
            });
        }

        // search input listener
        const searchInput = document.getElementById("simple-search");
        searchInput.addEventListener("input", debounce(() => {
            currentSearch = searchInput.value.trim();
            loadPatients(1);
        }, 300));

        // initial load
        window.addEventListener("DOMContentLoaded", () => loadPatients(1));
    </script>

    <!-- age group  -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const dobField = document.getElementById('dob');
            const ageInput = document.getElementById('age');
            const monthInput = document.getElementById('agemonth');
            const monthContainer = document.getElementById('monthContainer');

            // Hide month container initially
            monthContainer.style.display = 'none';
            monthInput.value = '';

            // Function to calculate and display age/month from DOB
            function updateFromDOB() {
                const dob = new Date(dobField.value);
                const today = new Date();

                if (!dob || dob > today) {
                    ageInput.value = '';
                    monthInput.value = '';
                    monthContainer.style.display = 'none';
                    return;
                }

                let years = today.getFullYear() - dob.getFullYear();
                let months = today.getMonth() - dob.getMonth();
                const days = today.getDate() - dob.getDate();

                // Adjust month/year differences
                if (months < 0 || (months === 0 && days < 0)) {
                    years--;
                    months += 12;
                }

                if (years < 0) years = 0;
                if (months < 0) months = 0;

                ageInput.value = years;

                // Show or hide month container depending on age
                handleMonthVisibility(years, months);
            }

            // Function to show/hide the month field
            function handleMonthVisibility(years, months = 0) {
                if (years < 5) {
                    const totalMonths = (years * 12) + months;
                    if (totalMonths >= 0 && totalMonths <= 59) {
                        monthContainer.style.display = 'block';
                        monthInput.value = totalMonths;
                    } else {
                        monthContainer.style.display = 'none';
                        monthInput.value = '';
                    }
                } else {
                    monthContainer.style.display = 'none';
                    monthInput.value = '';
                }
            }

            // 📅 When user changes DOB → auto-fill age & month
            dobField.addEventListener('change', updateFromDOB);

            // ✍️ When user manually changes age → show/hide month field dynamically
            ageInput.addEventListener('input', function() {
                const years = parseInt(this.value) || 0;
                handleMonthVisibility(years);
            });
        });
    </script>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const form = document.getElementById("patientForm");
            const popupContainer = document.getElementById("popupContainer");
            const popupTitle = document.getElementById("popupTitle");
            const popupMessage = document.getElementById("popupMessage");
            const popupOkBtn = document.getElementById("popupOkBtn");

            let reloadOnClose = false; // Track if we should reload

            function showPopup(title, message, color = "red", reload = false) {
                popupTitle.style.color = color;
                popupTitle.textContent = title;
                popupMessage.innerHTML = message;
                popupOkBtn.style.background = color;
                popupContainer.style.display = "flex";
                reloadOnClose = reload; // Set flag to reload when OK clicked
            }

            popupOkBtn.addEventListener("click", function() {
                popupContainer.style.display = "none";
                if (reloadOnClose) {
                    window.location.reload();
                }
            });

            form.addEventListener("submit", function(e) {
                e.preventDefault();
                let missing = [];

                // Always required fields
                form.querySelectorAll("[data-required]").forEach(input => {
                    if (!input.value.trim()) {
                        missing.push(input.getAttribute("data-label"));
                    }
                });

                // Conditional required fields
                const conditionalMap = {
                    "allergies_flag": ["allergies_details", "Allergies"],
                    "hepatitis_flag": ["hepatitis_details", "Hepatitis"],
                    "malignancy_flag": ["malignancy_details", "Malignancy"],
                    "prev_hospitalization_flag": ["last_admission_date", "Medical Last Admission"],
                    "blood_transfusion_flag": ["blood_transfusion", "Blood Transfusion"],
                    "other_conditions_flag": ["other_conditions", "Other Conditions"],
                    "sugar_flag": ["sugar_details", "Sugar"],
                    "alcohol_flag": ["alcohol_details", "Use of Alcohol"],
                    "tobacco_flag": ["tobacco_details", "Use of tobacco"],
                    "betel_nut_flag": ["betel_nut_details", "Betel Nut Chewing"],
                    "philhealth_flag": ["philhealth_number", "Philhealth Number"],
                    "sss_flag": ["sss_number", "SSS Number"],
                    "gsis_flag": ["gsis_number", "GSIS Number"]
                };

                Object.entries(conditionalMap).forEach(([flagName, [detailsName, label]]) => {
                    const checkbox = form.querySelector("[name='" + flagName + "']");
                    const detailsField = form.querySelector("[name='" + detailsName + "']");
                    if (checkbox && checkbox.checked && detailsField && !detailsField.value.trim()) {
                        missing.push(label);
                    }
                });

                if (missing.length > 0) {
                    showPopup(
                        "⚠ Submission Error",
                        "Please fill in the following required fields:<br><br>" + missing.join("<br>"),
                        "red"
                    );
                    return;
                }

                let formData = new FormData(form);
                formData.append("patient", "1"); // Ensure PHP sees it

                fetch("/dentalemr_system/php/register_patient/addpatient.php", {
                        method: "POST",
                        body: formData
                    })
                    .then(res => res.json())
                    .then(data => {
                        const color = data.status === "success" ? "blue" : "red";
                        showPopup(
                            data.title || "Message",
                            data.message || "No message",
                            color,
                            data.status === "success" // reload only on success
                        );
                        if (data.status === "success") form.reset(); // optional: reset form
                    })
                    .catch(err => {
                        console.error("AJAX error:", err);
                        showPopup("Error", "Error while saving patient. Check console.", "red");
                    });
            });
        });
    </script>


</body>

</html>