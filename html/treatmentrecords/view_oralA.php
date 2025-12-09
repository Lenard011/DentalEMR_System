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
    <title>Patient Treatment Records</title>
    <!-- <link href="../css/style.css" rel="stylesheet"> -->
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <style>
        .grid1 {
            display: flex;
            gap: 3px;
            justify-content: center;
            align-items: center;
        }

        .gridtop {
            display: flex;
            gap: 3px;
            justify-content: center;
            align-items: center;
            margin-bottom: 4px;
        }

        .tooth-container {
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .tooth {
            width: 39px;
            height: 39px;
            position: relative;
            cursor: pointer;
        }

        .tooth-label {
            font-size: 12px;
            font-weight: bold;
            text-align: center;
        }

        .label-top {
            margin-bottom: 2px;
        }

        .label-bottom {
            margin-top: 2px;
        }

        .part {
            position: absolute;
            box-sizing: border-box;
            background-color: white;
            border: 1px solid #747474;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            font-weight: bold;
            color: #fff;
            user-select: none;
        }

        /* Tooth parts with rotated shapes but straight text */
        .part-top-left {
            top: 0;
            left: 0;
            width: 50%;
            height: 50%;
            border: 1px solid #555;
            border-top-left-radius: 100%;
            transform: translateX(-50%) rotate(-45deg);
            margin-top: 10px;
            margin-left: 7px;
        }

        .part-top-left span {
            transform: rotate(90deg);
            display: block;
        }

        .part-top-right {
            top: 0;
            right: 0;
            width: 50%;
            height: 50%;
            border: 1px solid #555;
            border-top-right-radius: 100%;
            transform: translateX(-50%) rotate(-45deg);
            margin-top: -3px;
        }

        .part-top-right span {
            transform: rotate(90deg);
            display: block;
        }

        .part-bottom-left {
            bottom: 0;
            left: 0;
            width: 50%;
            height: 50%;
            border: 1px solid #555;
            border-bottom-left-radius: 100%;
            transform: translateX(-50%) rotate(-45deg);
            margin-bottom: -3px;
            margin-left: 20px;
        }

        .part-bottom-left span {
            transform: rotate(90deg);
            display: block;
        }

        .part-bottom-right {
            bottom: 0;
            right: 0;
            width: 50%;
            height: 50%;
            border: 1px solid #555;
            border-bottom-right-radius: 100%;
            transform: translateX(-50%) rotate(-45deg);
            margin-bottom: 10px;
            margin-right: -13px;
        }

        .part-bottom-right span {
            transform: rotate(90deg);
            display: block;
        }

        .part-center {
            width: 18px;
            height: 18px;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            border-radius: 50%;
            border: 1px solid #555;
            position: absolute;
            margin-left: 1px;
        }

        /* Center part doesn't need rotation */
        .part-center span {
            display: block;
        }

        .tooltip {
            visibility: hidden;
            background-color: #222;
            color: #fff;
            padding: 4px 6px;
            border-radius: 4px;
            font-size: 12px;
            position: absolute;
            bottom: 120%;
            left: 50%;
            transform: translateX(-50%);
            white-space: nowrap;
            opacity: 0;
            transition: opacity 0.3s;
            pointer-events: none;
            z-index: 10;
        }

        .tooth:hover .tooltip {
            visibility: visible;
            opacity: 1;
        }

        /* containers */
        .treatmentbox,
        .treatmentbox1,
        .conditionbox,
        .conditionbox1 {
            display: flex;
            grid-template-columns: repeat(16, 2.51rem);
            margin: 5px auto;
            justify-content: space-between;
            align-items: center;
        }

        /* individual boxes */
        .treatment-box,
        .treatment1-box,
        .condition-box,
        .condition1-box {
            width: 2rem;
            height: 2rem;
            display: flex;
            text-align: center;
            align-items: center;
            justify-content: center;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 0.9rem;
            font-weight: bold;
            cursor: pointer;
            user-select: none;
            background: #fff;
            color: #000;
        }
    </style>

    <!-- modal style -->
    <style>
        .controls {
            margin-bottom: 20px;
        }

        .grid11,
        .gridtop1 {
            display: flex;
            gap: 3px;
            justify-content: center;
            align-items: center;
        }

        .gridtop1 {
            margin-bottom: 2px;
        }

        .tooth-container {
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .tooth {
            width: 39px;
            height: 39px;
            position: relative;
            cursor: pointer;
        }

        .tooth-label {
            font-size: 12px;
            font-weight: bold;
            text-align: center;
        }

        .label-top {
            margin-bottom: 2px;
        }

        .label-bottom {
            margin-top: 2px;
        }

        .part {
            position: absolute;
            box-sizing: border-box;
            background-color: white;
            border: 1px solid #747474;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            font-weight: bold;
            color: #fff;
            user-select: none;
        }

        /* Modal tooth parts with straight text */
        .part-top-left {
            top: 0;
            left: 0;
            width: 50%;
            height: 50%;
            border: 1px solid #555;
            border-top-left-radius: 100%;
            transform: translateX(-50%) rotate(-45deg);
        }

        .part-top-left span {
            transform: rotate(45deg);
            display: block;
        }

        .part-top-right {
            top: 0;
            right: 0;
            width: 50%;
            height: 50%;
            border: 1px solid #555;
            border-top-right-radius: 100%;
            transform: translateX(-50%) rotate(-45deg);
        }

        .part-top-right span {
            transform: rotate(45deg);
            display: block;
        }

        .part-bottom-left {
            bottom: 0;
            left: 0;
            width: 50%;
            height: 50%;
            border: 1px solid #555;
            border-bottom-left-radius: 100%;
            transform: translateX(-50%) rotate(-45deg);
        }

        .part-bottom-left span {
            transform: rotate(45deg);
            display: block;
        }

        .part-bottom-right {
            bottom: 0;
            right: 0;
            width: 50%;
            height: 50%;
            border: 1px solid #555;
            border-bottom-right-radius: 100%;
            transform: translateX(-50%) rotate(-45deg);
        }

        .part-bottom-right span {
            transform: rotate(45deg);
            display: block;
        }

        .part-center {
            width: 18px;
            height: 18px;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            border-radius: 50%;
            border: 1px solid #555;
            position: absolute;
        }

        .part-center span {
            display: block;
        }

        .tooltip {
            visibility: hidden;
            background-color: #222;
            color: #fff;
            padding: 4px 6px;
            border-radius: 4px;
            font-size: 12px;
            position: absolute;
            bottom: 120%;
            left: 50%;
            transform: translateX(-50%);
            white-space: nowrap;
            opacity: 0;
            transition: opacity 0.3s;
            pointer-events: none;
            z-index: 10;
        }

        .tooth:hover .tooltip {
            visibility: visible;
            opacity: 1;
        }

        .treatmentbox,
        .treatmentbox1,
        .conditionbox,
        .conditionbox1 {
            display: grid;
            grid-template-columns: repeat(16, 2rem);
            gap: 10px;
            margin: 5px auto;
            justify-content: center;
        }

        .treatment-box,
        .treatment1-box,
        .condition-box,
        .condition1-box {
            width: 2rem;
            height: 2rem;
            display: flex;
            text-align: center;
            align-items: center;
            justify-content: center;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 0.9rem;
            font-weight: bold;
            cursor: pointer;
            user-select: none;
            background: #fff;
            color: #000;
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
        <!-- Your existing HTML structure until the modal -->
        <main class="p-1.5 md:ml-64 h-auto pt-1">
            <section class="bg-white dark:bg-gray-900 p-2 sm:p-2 rounded-lg">
                <div class="items-center justify-between flex flex-col sm:flex-row mb-3 gap-2">
                    <p id="patientName" class="italic text-lg font-medium text-gray-900 dark:text-white text-center sm:text-left">
                        Loading ...
                    </p>
                    <button type="button" id="addTooth" onclick="openOHCModalA();"
                        class="text-white cursor-pointer flex flex-row items-center justify-center gap-1 bg-blue-700 hover:bg-blue-800 font-medium rounded-sm text-xs px-1 lg:py-1 dark:bg-primary-600 dark:hover:bg-primary-700 focus:outline-none dark:focus:ring-primary-800">
                        <svg class="h-3.5 w-3.5" fill="currentColor" viewbox="0 0 20 20"
                            xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                            <path clip-rule="evenodd" fill-rule="evenodd"
                                d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" />
                        </svg>
                        Add
                    </button>
                </div>
                <div
                    class="mx-auto flex flex-col justify-center items-center max-w-full px-1.5 py-2 bg-white rounded-lg shadow dark:border shadow-stone-300 drop-shadow-sm dark:bg-gray-800 dark:border-gray-950 overflow-x-auto">
                    <div class="items-center justify-between flex flex-row w-full min-w-max">
                        <p class="text-base font-normal text-gray-950 dark:text-white ">A. Oral Health Condition</p>
                    </div>
                    <div class="w-full overflow-x-auto">
                        <div class="flex justify-center gap-2 p-1 mb-2 min-w-max" id="yearButtons">
                            <button type="button" onclick="year1()" style="font-size: 12px;"
                                class="text-white shadow drop-shadow-sm justify-center cursor-pointer inline-flex items-center bg-blue-700 hover:bg-blue-800 focus:outline-none focus:ring-blue-300 font-normal px-3 py-1 rounded min-w-16">
                                Year I
                            </button>
                            <button type="button" onclick="year2()" style="font-size: 12px;"
                                class="text-black shadow drop-shadow-sm justify-center cursor-pointer inline-flex items-center bg-white hover:bg-blue-800 hover:text-white focus:outline-none focus:ring-blue-300 font-normal px-3 py-1 rounded min-w-16">
                                Year II
                            </button>
                            <button type="button" onclick="year3()" style="font-size: 12px;"
                                class="text-black shadow drop-shadow-sm justify-center cursor-pointer inline-flex items-center bg-white hover:bg-blue-800 hover:text-white focus:outline-none focus:ring-blue-300 font-normal px-3 py-1 rounded min-w-16">
                                Year III
                            </button>
                            <button type="button" onclick="year4()" style="font-size: 12px;"
                                class="text-black shadow drop-shadow-sm justify-center cursor-pointer inline-flex items-center bg-white hover:bg-blue-800 hover:text-white focus:outline-none focus:ring-blue-300 font-normal px-3 py-1 rounded min-w-16">
                                Year IV
                            </button>
                            <button type="button" onclick="year5()" style="font-size: 12px;"
                                class="text-black shadow drop-shadow-sm justify-center cursor-pointer inline-flex items-center bg-white hover:bg-blue-800 hover:text-white focus:outline-none focus:ring-blue-300 font-normal px-3 py-1 rounded min-w-16">
                                Year V
                            </button>
                        </div>
                    </div>
                    <div class="label">
                        <p id="yeardate" class="text-sm font-medium text-gray-950 dark:text-white text-center">Year I - Date</p>
                    </div>
                    <div class="flex flex-col w-full overflow-x-auto">
                        <div class="min-w-max mx-auto">
                            <p style="margin-bottom: -5px; margin-top: -5px;"
                                class="text-sm w-20 ml-2 font-normal text-gray-900 dark:text-white">Treatment
                            </p>
                            <div class="treatmentbox" id="treatRow1"></div>
                            <div class="conditionbox" id="treatRow2"></div>
                            <p style="margin-bottom: -20px; margin-top: -5px;"
                                class="text-sm w-20 ml-2 font-normal text-gray-900 dark:text-white">Condition
                            </p>
                        </div>

                        <div class="min-w-max mx-auto">
                            <div class="gridtop" id="permanentGridtop"></div>
                            <div class="grid1" id="permanentGridbot"></div>
                            <div class="gridtop" id="temporaryGridtop"></div>
                            <div class="grid1" id="temporaryGridbot"></div>
                        </div>

                        <div class="min-w-max mx-auto">
                            <p style="margin-top: -20px; margin-bottom: -5px;"
                                class="text-sm w-20 ml-2 font-normal text-gray-900 dark:text-white">Condition
                            </p>
                            <div class="conditionbox1" id="treatRow3"></div>
                            <div class="treatmentbox1" id="treatRow4"></div>
                            <p style="margin-top: -5px;"
                                class="text-sm w-20 ml-2 font-normal text-gray-900 dark:text-white ">Treatment
                            </p>
                        </div>
                    </div>
                </div>
                <div class="w-full">
                    <div class="flex justify-between mt-5">
                        <button type="button" onclick="back()"
                            class="text-white justify-center cursor-pointer inline-flex items-center bg-blue-700 hover:bg-blue-800 focus:outline-none focus:ring-blue-300 font-medium rounded-lg text-sm px-4 py-2 dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800">
                            Back
                        </button>
                        <button type="button" onclick="next()"
                            class="text-white justify-center cursor-pointer inline-flex items-center bg-blue-700 hover:bg-blue-800 focus:outline-none focus:ring-blue-300 font-medium rounded-lg text-sm px-4 py-2 dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800">
                            Next
                        </button>
                    </div>
                </div>
            </section>
        </main>

        <!-- Modal -->
        <div id="ohcModalA" tabindex="-1" aria-hidden="true"
            class="fixed inset-0 hidden flex justify-center items-center z-50 bg-gray-600/50 p-2 md:p-4 overflow-auto">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg w-full max-w-6xl max-h-[90vh] overflow-auto">
                <div class="flex flex-row justify-between items-center mb-4 p-6 pb-0">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Add Oral Health Condition</h2>
                    <button type="button" id="cancelMedicalBtn"
                        class="relative cursor-pointer text-gray-500 hover:text-gray-800 dark:hover:text-white text-xl"
                        onclick="closeOHCModalA()">
                        ✕
                    </button>
                </div>
                <form id="ohcForm" class="space-y-4 p-6" onsubmit="return false;">
                    <input type="hidden" name="patient_id" id="patient_id" value="">
                    <section class="bg-white dark:bg-gray-900 p-2 rounded-lg mb-3 mt-3">
                        <div>
                            <input type="hidden" id="visit_id" value="0">
                            <div class="mb-3">
                                <p class="text-14 font-semibold text-gray-900 dark:text-white">A. Oral Health Condition</p>
                            </div>
                            <div class="flex flex-col lg:flex-row w-full justify-between items-start gap-4">
                                <!-- Tooth Part -->
                                <div class="flex flex-col gap-2 mb-4 w-full lg:w-5xl overflow-auto">
                                    <!-- A. -->
                                    <div class="flex justify-center items-center flex-wrap gap-2">
                                        <!-- Undo Button -->
                                        <button type="button" id="undoBtn"
                                            class="text-white justify-center cursor-pointer inline-flex items-center gap-1 
                                    bg-blue-700 hover:bg-blue-800 focus:outline-none focus:ring-blue-300 
                                    font-medium rounded-lg text-sm p-2 
                                    dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none"
                                                viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                    d="M3 7v6h6M3 13a9 9 0 0 1 9-9h3a9 9 0 0 1 9 9v1" />
                                            </svg>
                                            Undo
                                        </button>
                                        <!-- Redo Button -->
                                        <button type="button" id="redoBtn"
                                            class="text-white justify-center cursor-pointer inline-flex items-center gap-1 
                                    bg-blue-700 hover:bg-blue-800 focus:outline-none focus:ring-blue-300 
                                    font-medium rounded-lg text-sm p-2 
                                    dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none"
                                                viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                    d="M21 7v6h-6M21 13a9 9 0 0 0-9-9h-3a9 9 0 0 0-9 9v1" />
                                            </svg>
                                            Redo
                                        </button>
                                        <!-- Clear All Button -->
                                        <button type="button" id="clearAll"
                                            class="text-white justify-center cursor-pointer inline-flex items-center gap-1
                                    bg-blue-700 hover:bg-blue-800 focus:outline-none focus:ring-blue-300 
                                    font-medium rounded-lg text-sm p-2 
                                    dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none"
                                                viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                    d="M19 7l-.867 12.142A2 2 0 0 1 16.138 21H7.862a2 2 0 0 1-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22M10 3h4a1 1 0 0 1 1 1v2H9V4a1 1 0 0 1 1-1z" />
                                            </svg>
                                            Clear All
                                        </button>
                                    </div>
                                    <div class="flex flex-col overflow-auto">
                                        <div class="min-w-max">
                                            <p style="margin-bottom: -5px; margin-top: -5px;"
                                                class="text-sm w-20 ml-2 font-normal text-gray-900 dark:text-white">
                                                Treatment
                                            </p>
                                            <div class="treatmentbox" id="treatRow11"></div>
                                            <div class="conditionbox" id="treatRow21"></div>
                                            <p style="margin-bottom: -20px; margin-top: -5px;"
                                                class="text-sm w-20 ml-2 font-normal text-gray-900 dark:text-white">
                                                Condition
                                            </p>
                                        </div>

                                        <div class="min-w-max">
                                            <div class="gridtop1" id="permanentGridtop1"></div>
                                            <div class="grid11" id="permanentGridbot1"></div>
                                            <div class="gridtop1" id="temporaryGridtop1"></div>
                                            <div class="grid11" id="temporaryGridbot1"></div>
                                        </div>

                                        <div class="min-w-max">
                                            <p style="margin-top: -20px; margin-bottom: -5px;"
                                                class="text-sm w-20 ml-2 font-normal text-gray-900 dark:text-white">
                                                Condition
                                            </p>
                                            <div class="conditionbox" id="treatRow31"></div>
                                            <div class="treatmentbox" id="treatRow41"></div>
                                            <p style="margin-top: -5px;"
                                                class="text-sm w-20 ml-2 font-normal text-gray-900 dark:text-white ">
                                                Treatment
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                <!-- Legend Condition -->
                                <div class="flex flex-row md:flex-row gap-4 w-full lg:w-xl overflow-auto">
                                    <!-- Condition -->
                                    <div
                                        class="controls relative w-full p-4 flex rounded-sm flex-col border border-dashed border-gray-400 [border-image:repeating-linear-gradient(45deg,#6b7280_0_10px,transparent_10px_15px)_1] min-w-64">
                                        <div>
                                            <p class="text-sm font-medium text-gray-900 dark:text-white">Legend: <span
                                                    class="font-normal">Condition</span>
                                            </p>
                                            <p class="text-sm font-normal text-gray-900 dark:text-white">Capital
                                                letters
                                                shall
                                                be use for recording the condition of permanent dentition and small
                                                letters
                                                for
                                                the status of temporary dentition.
                                            </p>
                                        </div>
                                        <div class="overflow-auto">
                                            <table class="w-full text-sm text-center border-1 min-w-full">
                                                <thead class="text-sm align-text-top text-gray-700 border">
                                                    <tr>
                                                        <th scope="col" class="border-1">
                                                            Permanent <br> <input type="checkbox" id="upperCaseChk"
                                                                checked>
                                                        </th>
                                                        <th scope="col" class="w-20 border-1">
                                                            Tooth Condition
                                                        </th>
                                                        <th scope="col" class="border-1">
                                                            Temporary <br> <input type="checkbox" id="lowerCaseChk">
                                                        </th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr class="border-1">
                                                        <td class="border-1">
                                                            ✓
                                                        </td>
                                                        <td class="border-1">
                                                            Sound/Sealed
                                                        </td>
                                                        <td class="border-1">
                                                            ✓
                                                        </td>
                                                    </tr>
                                                    <tr class="border-1">
                                                        <td class="border-1">
                                                            D
                                                        </td>
                                                        <td class="border-1">
                                                            Decayed
                                                        </td>
                                                        <td class="border-1">
                                                            d
                                                        </td>
                                                    </tr>
                                                    <tr class="border-1">
                                                        <td class="border-1">
                                                            F
                                                        </td>
                                                        <td class="border-1">
                                                            Filled
                                                        </td>
                                                        <td class="border-1">
                                                            f
                                                        </td>
                                                    </tr>
                                                    <tr class="border-1">
                                                        <td class="border-1">
                                                            M
                                                        </td>
                                                        <td class="border-1">
                                                            Missing
                                                        </td>
                                                        <td class="border-1">
                                                            m
                                                        </td>
                                                    </tr>
                                                    <tr class="border-1">
                                                        <td class="border-1">
                                                            DX
                                                        </td>
                                                        <td class="p-1 border-1">
                                                            Indicated for Extraction
                                                        </td>
                                                        <td class="border-1">
                                                            dx
                                                        </td>
                                                    </tr>
                                                    <tr class="border-1">
                                                        <td class="border-1">
                                                            Un
                                                        </td>
                                                        <td class="border-1">
                                                            Unerupted
                                                        </td>
                                                        <td class="border-1">
                                                            un
                                                        </td>
                                                    </tr>
                                                    <tr class="border-1">
                                                        <td class="border-1">
                                                            S
                                                        </td>
                                                        <td class="border-1">
                                                            Supernumerary Tooth
                                                        </td>
                                                        <td class="border-1">
                                                            s
                                                        </td>
                                                    </tr>
                                                    <tr class="border-1">
                                                        <td class="border-1">
                                                            JC
                                                        </td>
                                                        <td class="border-1">
                                                            jacket Crown
                                                        </td>
                                                        <td class="border-1">
                                                            jc
                                                        </td>
                                                    </tr>
                                                    <tr class="border-1">
                                                        <td class="border-1">
                                                            P
                                                        </td>
                                                        <td class="border-1">
                                                            Pontic
                                                        </td>
                                                        <td class="border-1">
                                                            p
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                        <div class="flex items-center w-full flex-col sm:flex-row gap-2 mt-2">
                                            <label
                                                class="text-sm font-semibold text-gray-900 dark:text-white">Color
                                                Code:</label>
                                            <div class="flex flex-col sm:flex-row gap-2 w-full">
                                                <select id="blueSelect"
                                                    class="text-white justify-center cursor-pointer inline-flex items-center bg-blue-700 hover:bg-blue-800 focus:outline-none focus:ring-blue-300 font-medium rounded-sm text-sm p-2 w-full dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800">
                                                    <option selected value="">Blue</option>
                                                    <option value="✓">Sound/Sealed✓</option>
                                                    <option value="D">Blue D/d</option>
                                                    <option value="F">Blue F/f</option>
                                                    <option value="M">Blue M/m</option>
                                                    <option value="DX">Blue DX/dx</option>
                                                    <option value="Un">Blue Un/un</option>
                                                    <option value="S">Blue S/s</option>
                                                    <option value="JC">Blue JC/jc</option>
                                                    <option value="P">Blue P/p</option>
                                                </select>
                                                <select id="redSelect"
                                                    class="text-white justify-center cursor-pointer inline-flex items-center bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-red-200 font-medium rounded-sm text-sm p-2 w-full dark:bg-red-500 dark:hover:bg-red-700 dark:focus:ring-red-700">
                                                    <option selected value="">Red</option>
                                                    <option value="✓">Sound/Sealed✓</option>
                                                    <option value="D">Red D/d</option>
                                                    <option value="F">Red F/f</option>
                                                    <option value="M">Red M/m</option>
                                                    <option value="DX">Red DX/dx</option>
                                                    <option value="Un">Red Un/un</option>
                                                    <option value="S">Red S/s</option>
                                                    <option value="JC">Red JC/jc</option>
                                                    <option value="P">Red P/p</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- Treatment -->
                                    <div
                                        class="controls p-4 flex rounded-sm flex-col border border-dashed border-gray-400 [border-image:repeating-linear-gradient(45deg,#6b7280_0_10px,transparent_10px_15px)_1] min-w-64">
                                        <div class="flex flex-col justify-center items-center">
                                            <div class="flex flex-col gap-3">
                                                <p class="text-sm font-medium text-gray-900 dark:text-white">Legend:
                                                    <span class="font-normal">Treament</span>
                                                </p>
                                                <div class="flex flex-col gap-3">
                                                    <p class="text-sm font-normal text-gray-900 dark:text-white">
                                                        Topical
                                                        Fluoride
                                                        Application:
                                                    </p>
                                                    <p class="text-sm font-normal ml-5 text-gray-900 dark:text-white">FV
                                                        -
                                                        Fluoride
                                                        Varnish
                                                    <p class="text-sm font-normal ml-5 text-gray-900 dark:text-white">FG
                                                        -
                                                        Fluoride
                                                        Gel
                                                    </p>
                                                </div>
                                                <p class="text-sm font-normal text-gray-900 dark:text-white">PFS - Pit
                                                    and
                                                    Fissure Sealant
                                                </p>
                                                <p class="text-sm font-normal text-gray-900 dark:text-white">PF -
                                                    Permanent
                                                    Filling (Composite, Am, ART)
                                                </p>
                                                <p class="text-sm font-normal text-gray-900 dark:text-white">TF -
                                                    Temporary
                                                    Filling
                                                </p>
                                                <p class="text-sm font-normal text-gray-900 dark:text-white">X -
                                                    Extraction
                                                </p>
                                                <p class="text-sm font-normal text-gray-900 dark:text-white">O - Others
                                                </p>
                                            </div>
                                            <div class="w-full flex flex-col justify-center items-center mt-4">
                                                <select id="treatmentSelect"
                                                    class="bg-gray-50 border border-gray-300 w-full text-gray-900 text-xs rounded-sm focus:ring-primary-600 focus:border-primary-600 p-2 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500">
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
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>
                    <div class="flex justify-end gap-2">
                        <button type="button" onclick="saveOHCA()"
                            class="text-white justify-center cursor-pointer inline-flex items-center bg-blue-700 hover:bg-blue-800 focus:outline-none focus:ring-blue-300 font-medium rounded-lg text-sm px-4 py-2 dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800">
                            Save
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- SCRIPTS SECTION -->
        <script>
            // ==============================================
            // GLOBAL VARIABLES
            // ==============================================
            let modalTeethData = [];
            let modalSelectedColor = '';
            let modalSelectedCondition = '';
            let modalSelectedCase = 'upper';
            let modalHasUnsavedChanges = false;
            const modalHistoryStack = [];
            const modalRedoStack = [];
            const teethParts = ['top-left', 'top-right', 'bottom-left', 'bottom-right', 'center'];
            let currentPatientId = null;
            let currentYear = 1;

            // ==============================================
            // UTILITY FUNCTIONS
            // ==============================================

            function romanNumeral(num) {
                const map = {
                    1: 'I',
                    2: 'II',
                    3: 'III',
                    4: 'IV',
                    5: 'V',
                    6: 'VI',
                    7: 'VII',
                    8: 'VIII',
                    9: 'IX',
                    10: 'X'
                };
                return map[num] || String(num);
            }

            function getCurrentActiveYear() {
                const activeButton = document.querySelector('#yearButtons button[style*="background-color: rgb(29, 78, 216)"]');
                if (activeButton) {
                    const buttonText = activeButton.textContent.trim();
                    const yearMatch = buttonText.match(/Year\s+([IVX]+)/i);
                    if (yearMatch) {
                        const romanNum = yearMatch[1];
                        const romanMap = {
                            I: 1,
                            II: 2,
                            III: 3,
                            IV: 4,
                            V: 5,
                            VI: 6,
                            VII: 7,
                            VIII: 8,
                            IX: 9,
                            X: 10
                        };
                        return romanMap[romanNum] || 1;
                    }
                }
                return currentYear;
            }

            function highlightActiveButton(yearNumber) {
                const buttons = document.querySelectorAll("#yearButtons button");
                buttons.forEach((btn, index) => {
                    if (index + 1 === yearNumber) {
                        btn.style.backgroundColor = "#1d4ed8";
                        btn.style.color = "#ffffff";
                        btn.style.fontWeight = "bold";
                    } else {
                        btn.style.backgroundColor = "#ffffff";
                        btn.style.color = "#000000";
                        btn.style.fontWeight = "normal";
                    }
                });
            }

            // ==============================================
            // DATA LOADING FUNCTIONS
            // ==============================================

            async function loadModalTeethData() {
                try {
                    const response = await fetch('/dentalemr_system/php/treatmentrecords/get_teeth.php');
                    if (response.ok) {
                        const data = await response.json();
                        if (data && !data.error) {
                            modalTeethData = data;
                            console.log('Loaded teeth data:', modalTeethData.length, 'records');
                            return true;
                        }
                    }
                    modalTeethData = [];
                    return false;
                } catch (error) {
                    modalTeethData = [];
                    return false;
                }
            }

            function getToothIdByFDI(fdiNumber) {
                if (!modalTeethData || modalTeethData.length === 0) {
                    return fdiNumber;
                }

                fdiNumber = parseInt(fdiNumber);
                const tooth = modalTeethData.find(t => parseInt(t.fdi_number) === fdiNumber);

                if (tooth) {
                    return parseInt(tooth.tooth_id);
                }

                return fdiNumber;
            }

            // ==============================================
            // MAIN PAGE FUNCTIONS
            // ==============================================

            function createPart(toothId, partName) {
                const part = document.createElement('div');
                part.className = 'part part-' + partName;
                const key = `${toothId}-${partName}`;
                part.dataset.key = key;

                const textSpan = document.createElement('span');
                part.appendChild(textSpan);

                return part;
            }

            function createTooth(id, label, position = 'bottom', tooth_id = null) {
                const container = document.createElement('div');
                container.className = 'tooth-container';

                const toothLabel = document.createElement('div');
                toothLabel.className = `tooth-label label-${position}`;
                toothLabel.textContent = label;

                const tooth = document.createElement('div');
                tooth.className = 'tooth';
                tooth.id = id;
                tooth.dataset.toothId = tooth_id ?? '';

                teethParts.forEach(p => {
                    tooth.appendChild(createPart(id, p));
                });

                const tooltip = document.createElement('div');
                tooltip.className = 'tooltip';
                tooltip.textContent = label;
                tooth.appendChild(tooltip);

                if (position === 'top') {
                    container.appendChild(toothLabel);
                    container.appendChild(tooth);
                } else {
                    container.appendChild(tooth);
                    container.appendChild(toothLabel);
                }

                return container;
            }

            function createBox(id, row, kind) {
                const box = document.createElement('div');
                const key = `R${row}-${id}`;
                box.dataset.key = key;

                if (kind === 'treatment') {
                    box.className = (row === 4) ? 'treatment1-box' : 'treatment-box';
                } else {
                    box.className = (row === 3) ? 'condition1-box' : 'condition-box';
                }

                return box;
            }

            function loadGrid() {
                const permTop = document.getElementById('permanentGridtop');
                const permBot = document.getElementById('permanentGridbot');
                const tempTop = document.getElementById('temporaryGridtop');
                const tempBot = document.getElementById('temporaryGridbot');

                [permTop, permBot, tempTop, tempBot].forEach(container => {
                    if (container) container.innerHTML = '';
                });

                const permT = [55, 54, 53, 52, 51, 61, 62, 63, 64, 65];
                const permB = [18, 17, 16, 15, 14, 13, 12, 11, 21, 22, 23, 24, 25, 26, 27, 28];
                const tempT = [48, 47, 46, 45, 44, 43, 42, 41, 31, 32, 33, 34, 35, 36, 37, 38];
                const tempB = [85, 84, 83, 82, 81, 71, 72, 73, 74, 75];

                permT.forEach(n => permTop.appendChild(createTooth(`P-${n}`, n, 'top', n)));
                permB.forEach(n => permBot.appendChild(createTooth(`P-${n}`, n, 'bottom', n)));
                tempT.forEach(n => tempTop.appendChild(createTooth(`T-${n}`, n, 'top', n)));
                tempB.forEach(n => tempBot.appendChild(createTooth(`T-${n}`, n, 'bottom', n)));
            }

            function loadBoxes() {
                const row1 = document.getElementById('treatRow1');
                const row2 = document.getElementById('treatRow2');
                const row3 = document.getElementById('treatRow3');
                const row4 = document.getElementById('treatRow4');

                for (let i = 0; i < 16; i++) {
                    row1.appendChild(createBox(i, 1, 'treatment'));
                    row2.appendChild(createBox(i, 2, 'condition'));
                    row3.appendChild(createBox(i, 3, 'condition'));
                    row4.appendChild(createBox(i, 4, 'treatment'));
                }
            }

            function clearAllTeeth() {
                document.querySelectorAll(".part, .treatment-box, .treatment1-box, .condition-box, .condition1-box").forEach(el => {
                    el.style.backgroundColor = "";
                    el.style.color = "";
                    el.style.fontWeight = "";

                    const textSpan = el.querySelector('span');
                    if (textSpan) {
                        textSpan.textContent = "";
                        textSpan.style.color = "";
                        textSpan.style.fontWeight = "";
                        textSpan.style.fontSize = "";
                    } else {
                        el.textContent = "";
                    }
                });
            }

            // ==============================================
            // DATA FETCHING AND DISPLAY
            // ==============================================

            async function loadVisitData(patientId, visitNumber) {
                try {
                    console.log(`Loading data for patient ${patientId}, visit ${visitNumber}`);

                    currentPatientId = patientId;
                    currentYear = visitNumber;

                    // Clear and show loading
                    clearAllTeeth();
                    document.getElementById("yeardate").textContent = `Year ${romanNumeral(visitNumber)} - Loading...`;

                    // Fetch with cache busting
                    const timestamp = new Date().getTime();
                    const apiUrl = `/dentalemr_system/php/treatmentrecords/fetch_oral_condition.php?patient_id=${patientId}&_=${timestamp}`;

                    const res = await fetch(apiUrl);

                    if (!res.ok) {
                        throw new Error(`HTTP ${res.status}: ${res.statusText}`);
                    }

                    const data = await res.json();

                    if (!data.success) {
                        throw new Error(data.error || "Failed to load data");
                    }

                    // Update patient name
                    if (data.patient) {
                        const {
                            firstname,
                            middlename,
                            surname
                        } = data.patient;
                        const middleInitial = middlename ? `${middlename.charAt(0).toUpperCase()}.` : "";
                        document.getElementById("patientName").textContent =
                            `${firstname} ${middleInitial} ${surname}`.trim();
                    }

                    if (!data.visits || data.visits.length === 0) {
                        document.getElementById("yeardate").textContent = `Year ${romanNumeral(visitNumber)} - No records`;
                        return;
                    }

                    // Find the specific visit
                    const visit = data.visits.find(v => parseInt(v.visit_number) === parseInt(visitNumber));

                    if (!visit) {
                        document.getElementById("yeardate").textContent = `Year ${romanNumeral(visitNumber)} - No records`;
                        return;
                    }

                    // Update display date
                    const visitDate = visit.visit_date ?
                        new Date(visit.visit_date).toLocaleDateString('en-US', {
                            year: 'numeric',
                            month: 'short',
                            day: 'numeric'
                        }) : 'No date';

                    document.getElementById("yeardate").textContent =
                        `${visit.visit_label || 'Year ' + romanNumeral(visitNumber)} — ${visitDate}`;

                    console.log(`Found visit: ${visit.visit_id}, Conditions: ${visit.conditions?.length || 0}, Treatments: ${visit.treatments?.length || 0}`);

                    // Apply conditions
                    if (visit.conditions && visit.conditions.length > 0) {
                        visit.conditions.forEach(c => {
                            if (!c.box_key) return;

                            const el = document.querySelector(`[data-key='${c.box_key}']`);
                            if (!el) {
                                console.warn(`Element not found for key: ${c.box_key}`);
                                return;
                            }

                            // Set color based on condition
                            let backgroundColor = "#ffffff";
                            if (c.color === 'red') backgroundColor = '#b91c1c';
                            else if (c.color === 'blue') backgroundColor = '#1e40af';
                            else if (c.condition_code?.toLowerCase() === 'm') backgroundColor = '#ef4444';
                            else if (c.condition_code && c.condition_code !== '✓') backgroundColor = '#3b82f6';

                            el.style.backgroundColor = backgroundColor;

                            // Set text
                            const textSpan = el.querySelector('span');
                            const displayText = c.condition_code || '';

                            if (textSpan) {
                                textSpan.textContent = displayText;
                                textSpan.style.color = displayText === '✓' ? '#000000' : '#ffffff';
                                textSpan.style.fontWeight = 'bold';
                                textSpan.style.fontSize = '10px';
                            } else {
                                el.textContent = displayText;
                                el.style.color = displayText === '✓' ? '#000000' : '#ffffff';
                                el.style.fontWeight = 'bold';
                            }
                        });
                    }

                    // Apply treatments
                    if (visit.treatments && visit.treatments.length > 0) {
                        visit.treatments.forEach(t => {
                            if (!t.box_key) return;

                            const el = document.querySelector(`[data-key='${t.box_key}']`);
                            if (!el) {
                                console.warn(`Element not found for key: ${t.box_key}`);
                                return;
                            }

                            const displayText = t.treatment_code?.toUpperCase() || "";
                            el.textContent = displayText;
                            el.style.backgroundColor = "#ffffff";
                            el.style.color = "#000000";
                            el.style.fontWeight = "bold";
                            el.style.fontSize = "12px";
                        });
                    }

                    console.log("Data loading completed successfully");

                } catch (err) {
                    console.error("Error loading visit data:", err);
                    document.getElementById("yeardate").textContent = `Year ${romanNumeral(visitNumber)} - Error`;
                    alert("Failed to load data: " + err.message);
                }
            }

            // ==============================================
            // MODAL FUNCTIONS
            // ==============================================

            function formatCondition(cond, textCase) {
                if (!cond) return '';
                if (cond.toLowerCase() === '✓') return '✓';
                return textCase === 'upper' ? cond.toUpperCase() : cond.toLowerCase();
            }

            function detectCaseType(condCode, datasetCase = '') {
                if (datasetCase === 'temporary' || datasetCase === 'lower') return 'temporary';
                if (datasetCase === 'permanent' || datasetCase === 'upper') return 'permanent';

                if (condCode && condCode !== '✓') {
                    if (condCode === condCode.toLowerCase()) return 'temporary';
                    if (condCode === condCode.toUpperCase()) return 'permanent';
                }
                return 'permanent';
            }

            function applyChange(key, color, cond, textCase, saveHistory = true, isTreatment = false) {
                const modal = document.getElementById('ohcModalA');
                const el = modal.querySelector(`[data-key="${key}"]`);
                if (!el) return;

                const textSpan = el.querySelector('span');

                // Mark as unsaved
                modalHasUnsavedChanges = true;

                // Save history
                if (saveHistory) {
                    modalHistoryStack.push({
                        key: key,
                        isTreatment: isTreatment,
                        prevColor: el.dataset.color || '',
                        prevCondition: el.dataset.condition || '',
                        prevTreatment: el.dataset.treatment || '',
                        prevTextContent: textSpan ? textSpan.textContent : el.textContent,
                        prevCase: el.dataset.case || 'upper',
                        newColor: color,
                        newCondition: isTreatment ? '' : cond,
                        newTreatment: isTreatment ? cond : '',
                        newCase: textCase
                    });
                    modalRedoStack.length = 0;
                    updateModalButtonStates();
                }

                if (isTreatment) {
                    el.dataset.treatment = cond || '';
                    el.dataset.condition = '';
                    el.dataset.color = '';
                    el.dataset.case = '';
                    el.textContent = cond || '';
                    el.style.backgroundColor = '#fff';
                    el.style.color = '#000';
                    el.style.fontWeight = 'bold';
                } else {
                    el.dataset.condition = cond || '';
                    el.dataset.color = color || '';
                    el.dataset.case = textCase || 'upper';
                    el.dataset.treatment = '';

                    const displayText = formatCondition(cond, textCase);

                    if (textSpan) {
                        textSpan.textContent = displayText;
                    } else {
                        el.textContent = displayText;
                    }

                    if (cond && cond.toLowerCase() === '✓') {
                        el.style.backgroundColor = '#fff';
                        if (textSpan) {
                            textSpan.style.color = '#000';
                        } else {
                            el.style.color = '#000';
                        }
                    } else {
                        el.style.backgroundColor = color === 'blue' ? '#1e40af' : color === 'red' ? '#b91c1c' : '#fff';
                        if (textSpan) {
                            textSpan.style.color = '#fff';
                        } else {
                            el.style.color = '#fff';
                        }
                    }

                    if (textSpan) {
                        textSpan.style.fontWeight = 'bold';
                        textSpan.style.fontSize = '10px';
                    } else {
                        el.style.fontWeight = 'bold';
                    }
                }
            }

            function updateModalButtonStates() {
                const modal = document.getElementById('ohcModalA');
                const undoBtn = modal.querySelector('#undoBtn');
                const redoBtn = modal.querySelector('#redoBtn');

                if (undoBtn) {
                    undoBtn.disabled = modalHistoryStack.length === 0;
                    undoBtn.style.opacity = modalHistoryStack.length === 0 ? '0.5' : '1';
                }
                if (redoBtn) {
                    redoBtn.disabled = modalRedoStack.length === 0;
                    redoBtn.style.opacity = modalRedoStack.length === 0 ? '0.5' : '1';
                }
            }

            // ==============================================
            // MODAL INITIALIZATION
            // ==============================================

            function initModalGrid() {
                const modal = document.getElementById('ohcModalA');
                if (!modal) return;

                // Set patient ID
                const patientIdInput = modal.querySelector('#patient_id');
                if (patientIdInput && currentPatientId) {
                    patientIdInput.value = currentPatientId;
                }

                // Get modal elements
                const blueSelect = modal.querySelector('#blueSelect');
                const redSelect = modal.querySelector('#redSelect');
                const upperCaseChk = modal.querySelector('#upperCaseChk');
                const lowerCaseChk = modal.querySelector('#lowerCaseChk');
                const treatmentSelect = modal.querySelector('#treatmentSelect');
                const undoBtn = modal.querySelector('#undoBtn');
                const redoBtn = modal.querySelector('#redoBtn');
                const clearAllBtn = modal.querySelector('#clearAll');

                // Event listeners
                blueSelect?.addEventListener('change', () => {
                    const val = blueSelect.value;
                    if (!val) return;
                    modalSelectedCondition = val;
                    modalSelectedColor = 'blue';
                    if (redSelect) redSelect.value = '';
                });

                redSelect?.addEventListener('change', () => {
                    const val = redSelect.value;
                    if (!val) return;
                    modalSelectedCondition = val;
                    modalSelectedColor = 'red';
                    if (blueSelect) blueSelect.value = '';
                });

                upperCaseChk?.addEventListener('change', () => {
                    modalSelectedCase = 'upper';
                    if (upperCaseChk) upperCaseChk.checked = true;
                    if (lowerCaseChk) lowerCaseChk.checked = false;
                });

                lowerCaseChk?.addEventListener('change', () => {
                    modalSelectedCase = 'lower';
                    if (lowerCaseChk) lowerCaseChk.checked = true;
                    if (upperCaseChk) upperCaseChk.checked = false;
                });

                // Undo/Redo functionality
                undoBtn?.addEventListener('click', () => {
                    if (!modalHistoryStack.length) return;
                    const last = modalHistoryStack.pop();
                    modalRedoStack.push({
                        ...last
                    });

                    const el = modal.querySelector(`[data-key="${last.key}"]`);
                    if (el) {
                        const textSpan = el.querySelector('span');

                        if (last.isTreatment) {
                            el.dataset.treatment = last.prevTreatment || '';
                            el.textContent = last.prevTreatment || '';
                            el.style.backgroundColor = '#fff';
                            el.style.color = '#000';
                        } else {
                            el.dataset.condition = last.prevCondition || '';
                            el.dataset.color = last.prevColor || '';
                            el.dataset.case = last.prevCase || 'upper';

                            if (textSpan) {
                                textSpan.textContent = last.prevTextContent || '';
                            } else {
                                el.textContent = last.prevTextContent || '';
                            }

                            if (last.prevCondition && last.prevCondition.toLowerCase() === '✓') {
                                el.style.backgroundColor = '#fff';
                                if (textSpan) {
                                    textSpan.style.color = '#000';
                                } else {
                                    el.style.color = '#000';
                                }
                            } else {
                                el.style.backgroundColor = last.prevColor === 'blue' ? '#1e40af' : last.prevColor === 'red' ? '#b91c1c' : '#fff';
                                if (textSpan) {
                                    textSpan.style.color = last.prevColor ? '#fff' : '#000';
                                } else {
                                    el.style.color = last.prevColor ? '#fff' : '#000';
                                }
                            }
                        }
                    }
                    updateModalButtonStates();
                });

                redoBtn?.addEventListener('click', () => {
                    if (!modalRedoStack.length) return;
                    const last = modalRedoStack.pop();
                    modalHistoryStack.push({
                        ...last
                    });
                    applyChange(
                        last.key,
                        last.newColor || '',
                        last.isTreatment ? last.newTreatment || '' : last.newCondition || '',
                        last.newCase || 'upper',
                        false,
                        last.isTreatment
                    );
                    updateModalButtonStates();
                });

                clearAllBtn?.addEventListener('click', () => {
                    if (!confirm('Clear all changes?')) return;

                    modal.querySelectorAll('.part, .treatment-box, .treatment1-box, .condition-box, .condition1-box').forEach(el => {
                        el.dataset.color = '';
                        el.dataset.condition = '';
                        el.dataset.treatment = '';
                        el.dataset.case = '';
                        el.textContent = '';
                        el.style.backgroundColor = '#fff';
                        el.style.color = '#000';
                        el.style.fontWeight = 'normal';

                        const textSpan = el.querySelector('span');
                        if (textSpan) {
                            textSpan.textContent = '';
                            textSpan.style.color = '';
                        }
                    });

                    modalHistoryStack.length = 0;
                    modalRedoStack.length = 0;
                    modalHasUnsavedChanges = false;
                    updateModalButtonStates();
                });

                // Create modal elements
                function createModalPart(toothId, partName, fdiNumber) {
                    const part = document.createElement('div');
                    part.className = 'part part-' + partName;
                    const key = `${toothId}-${partName}`;
                    part.dataset.key = key;

                    const toothIdNum = getToothIdByFDI(fdiNumber);
                    part.dataset.toothid = toothIdNum;
                    part.dataset.fdinumber = fdiNumber;

                    const textSpan = document.createElement('span');
                    part.appendChild(textSpan);

                    part.addEventListener('click', () => {
                        if (!modalSelectedCondition) {
                            alert('Select a condition first');
                            return;
                        }
                        applyChange(key, modalSelectedColor, modalSelectedCondition, modalSelectedCase, true, false);
                    });

                    return part;
                }

                function createModalTooth(id, label, position = 'bottom') {
                    const container = document.createElement('div');
                    container.className = 'tooth-container';

                    const toothLabel = document.createElement('div');
                    toothLabel.className = `tooth-label label-${position}`;
                    toothLabel.textContent = label;

                    const tooth = document.createElement('div');
                    tooth.className = 'tooth';
                    tooth.id = id;

                    const toothId = getToothIdByFDI(label);
                    tooth.dataset.toothId = toothId;
                    tooth.dataset.fdinumber = label;

                    teethParts.forEach(p => {
                        tooth.appendChild(createModalPart(id, p, label));
                    });

                    const tooltip = document.createElement('div');
                    tooltip.className = 'tooltip';
                    tooltip.textContent = `Tooth ${label}`;
                    tooth.appendChild(tooltip);

                    if (position === 'top') {
                        container.appendChild(toothLabel);
                        container.appendChild(tooth);
                    } else {
                        container.appendChild(tooth);
                        container.appendChild(toothLabel);
                    }

                    return container;
                }

                function createModalBox(id, row, kind) {
                    const box = document.createElement('div');
                    const key = `R${row}-${id}`;
                    box.dataset.key = key;

                    const fdiMap = [18, 17, 16, 15, 14, 13, 12, 11, 21, 22, 23, 24, 25, 26, 27, 28];
                    const fdiNumber = fdiMap[id];
                    const toothId = getToothIdByFDI(fdiNumber);

                    box.dataset.toothid = toothId;
                    box.dataset.fdinumber = fdiNumber;

                    box.style.display = 'flex';
                    box.style.alignItems = 'center';
                    box.style.justifyContent = 'center';
                    box.style.fontSize = '0.9rem';
                    box.style.fontWeight = 'bold';
                    box.style.cursor = 'pointer';
                    box.style.userSelect = 'none';
                    box.style.border = '1px solid #ccc';
                    box.style.borderRadius = '4px';
                    box.style.width = '2rem';
                    box.style.height = '2rem';
                    box.style.backgroundColor = '#fff';
                    box.style.color = '#000';

                    if (kind === 'treatment') {
                        box.className = (row === 4) ? 'treatment1-box' : 'treatment-box';

                        box.addEventListener('click', () => {
                            const selectedTreat = treatmentSelect?.value || '';
                            if (!selectedTreat) {
                                alert('Select a treatment first');
                                return;
                            }
                            applyChange(key, '', selectedTreat, 'upper', true, true);
                        });
                    } else {
                        box.className = (row === 3) ? 'condition1-box' : 'condition-box';

                        box.addEventListener('click', () => {
                            if (!modalSelectedCondition) {
                                alert('Select a condition first');
                                return;
                            }
                            applyChange(key, modalSelectedColor, modalSelectedCondition, modalSelectedCase, true, false);
                        });
                    }

                    return box;
                }

                // Load modal grid
                function loadModalTeethGrid() {
                    const permTop = modal.querySelector('#permanentGridtop1');
                    const permBot = modal.querySelector('#permanentGridbot1');
                    const tempTop = modal.querySelector('#temporaryGridtop1');
                    const tempBot = modal.querySelector('#temporaryGridbot1');

                    [permTop, permBot, tempTop, tempBot].forEach(container => {
                        if (container) container.innerHTML = '';
                    });

                    const permT = [55, 54, 53, 52, 51, 61, 62, 63, 64, 65];
                    const permB = [18, 17, 16, 15, 14, 13, 12, 11, 21, 22, 23, 24, 25, 26, 27, 28];
                    const tempT = [48, 47, 46, 45, 44, 43, 42, 41, 31, 32, 33, 34, 35, 36, 37, 38];
                    const tempB = [85, 84, 83, 82, 81, 71, 72, 73, 74, 75];

                    permT.forEach(n => permTop.appendChild(createModalTooth(`P-${n}`, n, 'top')));
                    permB.forEach(n => permBot.appendChild(createModalTooth(`P-${n}`, n, 'bottom')));
                    tempT.forEach(n => tempTop.appendChild(createModalTooth(`T-${n}`, n, 'top')));
                    tempB.forEach(n => tempBot.appendChild(createModalTooth(`T-${n}`, n, 'bottom')));
                }

                function loadModalBoxes() {
                    const row1 = modal.querySelector('#treatRow11');
                    const row2 = modal.querySelector('#treatRow21');
                    const row3 = modal.querySelector('#treatRow31');
                    const row4 = modal.querySelector('#treatRow41');

                    [row1, row2, row3, row4].forEach(row => {
                        if (row) row.innerHTML = '';
                    });

                    for (let i = 0; i < 16; i++) {
                        row1.appendChild(createModalBox(i, 1, 'treatment'));
                        row2.appendChild(createModalBox(i, 2, 'condition'));
                        row3.appendChild(createModalBox(i, 3, 'condition'));
                        row4.appendChild(createModalBox(i, 4, 'treatment'));
                    }
                }

                // Initialize modal
                loadModalTeethGrid();
                loadModalBoxes();
                updateModalButtonStates();
                modal.dataset.loaded = 'true';
            }

            // ==============================================
            // MODAL CONTROL FUNCTIONS
            // ==============================================

            window.openOHCModalA = function() {
                const modal = document.getElementById('ohcModalA');
                if (!modal) return;

                // Reset state
                modalHasUnsavedChanges = false;

                // Show modal
                modal.classList.remove('hidden');
                modal.classList.add('flex');

                // Initialize if not loaded
                if (!modal.dataset.loaded) {
                    loadModalTeethData().then(() => {
                        initModalGrid();
                    });
                } else {
                    // Reset selections
                    const blueSelect = modal.querySelector('#blueSelect');
                    const redSelect = modal.querySelector('#redSelect');
                    const treatmentSelect = modal.querySelector('#treatmentSelect');
                    if (blueSelect) blueSelect.value = '';
                    if (redSelect) redSelect.value = '';
                    if (treatmentSelect) treatmentSelect.value = '';

                    modalSelectedColor = '';
                    modalSelectedCondition = '';
                    modalSelectedCase = 'upper';
                }
            };

            window.closeOHCModalA = function() {
                const modal = document.getElementById('ohcModalA');
                if (!modal) return;

                if (modalHasUnsavedChanges) {
                    if (!confirm('You have unsaved changes. Are you sure you want to close?')) {
                        return;
                    }
                }

                modal.classList.add('hidden');
                modal.classList.remove('flex');
            };

            // ==============================================
            // SAVE FUNCTION - COMPLETE FIXED VERSION
            // ==============================================

            window.saveOHCA = async function() {
                const modal = document.getElementById('ohcModalA');
                if (!modal) return;

                const patient_id = modal.querySelector("#patient_id")?.value || '';
                const visit_id = modal.querySelector("#visit_id")?.value || 0;

                if (!patient_id) {
                    alert('Patient ID required');
                    return;
                }

                const items = [];

                // Process parts
                modal.querySelectorAll(".part").forEach(part => {
                    const condCode = part.dataset.condition;
                    if (!condCode) return;

                    const toothElement = part.closest(".tooth");
                    if (!toothElement) return;

                    const tooth_id = toothElement.dataset.toothId;
                    if (!tooth_id) return;

                    const color = part.dataset.color || '';
                    const caseType = detectCaseType(condCode, part.dataset.case);

                    items.push({
                        type: "condition",
                        tooth_id: tooth_id,
                        condition_code: condCode,
                        box_key: part.dataset.key,
                        color: color,
                        case_type: caseType
                    });
                });

                // Process boxes
                modal.querySelectorAll(".condition1-box, .condition-box, .treatment1-box, .treatment-box").forEach(box => {
                    const treatCode = box.dataset.treatment;
                    const condCode = box.dataset.condition;
                    if (!treatCode && !condCode) return;

                    const tooth_id = box.dataset.toothid;
                    if (!tooth_id) return;

                    const color = box.dataset.color || '';

                    if (treatCode) {
                        items.push({
                            type: "treatment",
                            tooth_id: tooth_id,
                            treatment_code: treatCode,
                            box_key: box.dataset.key,
                            color: color,
                            case_type: 'permanent'
                        });
                    } else {
                        const caseType = detectCaseType(condCode, box.dataset.case);
                        items.push({
                            type: "condition",
                            tooth_id: tooth_id,
                            condition_code: condCode,
                            box_key: box.dataset.key,
                            color: color,
                            case_type: caseType
                        });
                    }
                });

                if (items.length === 0) {
                    alert('No data to save');
                    return;
                }

                const payload = {
                    action: "save",
                    patient_id: patient_id,
                    visit_id: visit_id,
                    oral_data: items
                };

                try {
                    // Show loading
                    const saveBtn = modal.querySelector('button[onclick="saveOHCA()"]');
                    const originalText = saveBtn.textContent;
                    saveBtn.textContent = 'Saving...';
                    saveBtn.disabled = true;

                    console.log("Saving data:", payload);

                    const res = await fetch("/dentalemr_system/php/treatmentrecords/oral_condition_api.php", {
                        method: "POST",
                        headers: {
                            "Content-Type": "application/json"
                        },
                        body: JSON.stringify(payload)
                    });

                    const result = await res.json();
                    console.log("Save response:", result);

                    if (result.success) {
                        alert("Oral Health Condition saved successfully!");

                        // Clear modal completely
                        modal.querySelectorAll('.part, .treatment-box, .treatment1-box, .condition-box, .condition1-box').forEach(el => {
                            el.dataset.color = '';
                            el.dataset.condition = '';
                            el.dataset.treatment = '';
                            el.dataset.case = '';
                            el.textContent = '';
                            el.style.backgroundColor = '#fff';
                            el.style.color = '#000';

                            const textSpan = el.querySelector('span');
                            if (textSpan) textSpan.textContent = '';
                        });

                        // Reset all selections
                        const blueSelect = modal.querySelector('#blueSelect');
                        const redSelect = modal.querySelector('#redSelect');
                        const treatmentSelect = modal.querySelector('#treatmentSelect');
                        if (blueSelect) blueSelect.value = '';
                        if (redSelect) redSelect.value = '';
                        if (treatmentSelect) treatmentSelect.value = '';

                        // Reset state
                        modalHistoryStack.length = 0;
                        modalRedoStack.length = 0;
                        modalHasUnsavedChanges = false;
                        modalSelectedColor = '';
                        modalSelectedCondition = '';
                        modalSelectedCase = 'upper';

                        updateModalButtonStates();

                        // Close modal
                        closeOHCModalA();

                        // FORCE REFRESH MAIN PAGE DATA
                        console.log("Refreshing main page data...");

                        // Get current year
                        const activeYear = getCurrentActiveYear();
                        console.log("Active year to refresh:", activeYear);

                        // Clear current display
                        clearAllTeeth();

                        // Short delay to ensure database is updated
                        setTimeout(() => {
                            if (currentPatientId) {
                                console.log(`Loading fresh data for patient ${currentPatientId}, year ${activeYear}`);
                                // Force fresh fetch with cache busting
                                loadVisitData(currentPatientId, activeYear);
                            }
                        }, 300);

                    } else {
                        alert("Save failed: " + (result.error || 'Unknown error'));
                    }
                } catch (error) {
                    console.error('Save error:', error);
                    alert("Save failed: " + error.message);
                } finally {
                    // Restore button
                    const saveBtn = modal.querySelector('button[onclick="saveOHCA()"]');
                    if (saveBtn) {
                        saveBtn.textContent = 'Save';
                        saveBtn.disabled = false;
                    }
                }
            };

            // ==============================================
            // NAVIGATION FUNCTIONS
            // ==============================================

            window.backmain = function() {
                location.href = "treatmentrecords.php?uid=<?php echo $userId; ?>";
            };

            window.next = function() {
                const patientId = new URLSearchParams(window.location.search).get("id");
                if (!patientId) {
                    alert("Missing patient ID.");
                    return;
                }
                window.location.href = `view_oralB.php?uid=<?php echo $userId; ?>&id=${encodeURIComponent(patientId)}`;
            };

            window.back = function() {
                const patientId = new URLSearchParams(window.location.search).get("id");
                if (!patientId) {
                    alert("Missing patient ID.");
                    return;
                }
                window.location.href = `view_oral.php?uid=<?php echo $userId; ?>&id=${encodeURIComponent(patientId)}`;
            };

            // Year button functions
            window.year1 = () => {
                highlightActiveButton(1);
                loadVisitData(currentPatientId, 1);
            };
            window.year2 = () => {
                highlightActiveButton(2);
                loadVisitData(currentPatientId, 2);
            };
            window.year3 = () => {
                highlightActiveButton(3);
                loadVisitData(currentPatientId, 3);
            };
            window.year4 = () => {
                highlightActiveButton(4);
                loadVisitData(currentPatientId, 4);
            };
            window.year5 = () => {
                highlightActiveButton(5);
                loadVisitData(currentPatientId, 5);
            };

            // ==============================================
            // INITIALIZATION
            // ==============================================

            document.addEventListener("DOMContentLoaded", () => {
                // Get patient ID from URL
                const params = new URLSearchParams(window.location.search);
                const patientId = params.get("id");

                if (!patientId) {
                    alert("Missing patient ID");
                    return;
                }

                currentPatientId = patientId;

                // Initialize main page
                loadGrid();
                loadBoxes();

                // Set up year buttons
                highlightActiveButton(1);

                // Load initial data
                loadVisitData(patientId, 1);

                // Set up navigation links
                const patientInfoLink = document.getElementById("patientInfoLink");
                const servicesRenderedLink = document.getElementById("servicesRenderedLink");
                const printdLink = document.getElementById("printdLink");

                if (patientInfoLink) {
                    patientInfoLink.href = `view_info.php?uid=<?php echo $userId; ?>&id=${encodeURIComponent(patientId)}`;
                }
                if (servicesRenderedLink) {
                    servicesRenderedLink.href = `view_record.php?uid=<?php echo $userId; ?>&id=${encodeURIComponent(patientId)}`;
                }
                if (printdLink) {
                    printdLink.href = `print.php?uid=<?php echo $userId; ?>&id=${encodeURIComponent(patientId)}`;
                }

                console.log("Page initialized for patient:", patientId);
            });

            // ==============================================
            // DEBUG HELPER (Optional)
            // ==============================================

            window.debugState = function() {
                console.log("=== DEBUG STATE ===");
                console.log("Current Patient ID:", currentPatientId);
                console.log("Current Year:", currentYear);
                console.log("Modal Has Unsaved Changes:", modalHasUnsavedChanges);
                console.log("Modal History Stack:", modalHistoryStack.length);
                console.log("Modal Redo Stack:", modalRedoStack.length);
                console.log("=== END DEBUG ===");
            };
        </script>
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

        function next() {
            // Get patient ID from URL
            const params = new URLSearchParams(window.location.search);
            const patientId = params.get("id");

            if (!patientId) {
                alert("Missing patient ID.");
                return;
            }

            // Navigate to view_oralA.php while keeping patient ID in the URL
            window.location.href = `view_oralB.php?uid=<?php echo $userId; ?>&id=${encodeURIComponent(patientId)}`;
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
            window.location.href = `view_oral.php?uid=<?php echo $userId; ?>&id=${encodeURIComponent(patientId)}`;
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



    <!-- Load offline storage -->
    <script src="/dentalemr_system/js/offline-storage.js"></script>

    <!-- Offline/Online Sync Handler -->
    <script>
        // Global offline sync manager
        class OfflineSyncManager {
            constructor() {
                this.offlineActions = JSON.parse(localStorage.getItem('offline_actions') || '[]');
                this.isOnline = navigator.onLine;
                this.syncInterval = null;

                this.init();
            }

            init() {
                // Listen for online/offline events
                window.addEventListener('online', () => this.handleOnline());
                window.addEventListener('offline', () => this.handleOffline());

                // Start periodic sync
                this.startSyncInterval();

                // Try to sync immediately if online
                if (this.isOnline) {
                    setTimeout(() => this.syncOfflineActions(), 1000);
                }
            }

            handleOnline() {
                this.isOnline = true;
                console.log('Device is online, syncing...');
                this.syncOfflineActions();
                this.startSyncInterval();
            }

            handleOffline() {
                this.isOnline = false;
                console.log('Device is offline');
                this.stopSyncInterval();
            }

            startSyncInterval() {
                if (this.syncInterval) clearInterval(this.syncInterval);
                this.syncInterval = setInterval(() => this.syncOfflineActions(), 30000); // Every 30 seconds
            }

            stopSyncInterval() {
                if (this.syncInterval) {
                    clearInterval(this.syncInterval);
                    this.syncInterval = null;
                }
            }

            addOfflineAction(action, data) {
                const actionData = {
                    id: Date.now() + '-' + Math.random().toString(36).substr(2, 9),
                    action: action,
                    data: data,
                    timestamp: new Date().toISOString(),
                    patient_id: data.patient_id || data.id || null
                };

                this.offlineActions.push(actionData);
                this.saveToStorage();

                console.log('Action saved for offline sync:', actionData);

                // Try to sync immediately if online
                if (this.isOnline) {
                    setTimeout(() => this.syncOfflineActions(), 500);
                }

                return actionData.id;
            }

            removeOfflineAction(actionId) {
                this.offlineActions = this.offlineActions.filter(action => action.id !== actionId);
                this.saveToStorage();
            }

            saveToStorage() {
                try {
                    localStorage.setItem('offline_actions', JSON.stringify(this.offlineActions));
                } catch (e) {
                    console.error('Failed to save offline actions:', e);
                }
            }

            async syncOfflineActions() {
                if (!this.isOnline || this.offlineActions.length === 0) {
                    return;
                }

                console.log(`Syncing ${this.offlineActions.length} offline actions...`);

                // Group actions by type for batch processing
                const archiveActions = this.offlineActions.filter(a => a.action === 'archive_patient');

                // Process archive actions
                if (archiveActions.length > 0) {
                    await this.syncArchiveActions(archiveActions);
                }

                // Process other action types as needed
            }

            async syncArchiveActions(archiveActions) {
                const patientIds = archiveActions.map(action => action.data.patient_id || action.data.id);

                try {
                    const response = await fetch('/dentalemr_system/php/treatmentrecords/treatment.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: new URLSearchParams({
                            sync_offline_archives: '1',
                            archive_ids: JSON.stringify(patientIds)
                        })
                    });

                    const result = await response.json();

                    if (result.success) {
                        console.log('Offline archive sync successful:', result);

                        // Remove successfully synced actions
                        archiveActions.forEach(action => {
                            this.removeOfflineAction(action.id);
                        });

                        // Show success message
                        if (result.synced_count > 0) {
                            showNotice(`Synced ${result.synced_count} archived patients from offline mode`, 'green');
                        }

                        // Refresh the patient list if on treatment records page
                        if (window.location.pathname.includes('treatmentrecords.php')) {
                            setTimeout(() => {
                                if (typeof window.loadPatients === 'function') {
                                    window.loadPatients();
                                }
                            }, 1000);
                        }
                    } else {
                        console.error('Offline archive sync failed:', result);
                    }
                } catch (error) {
                    console.error('Failed to sync offline archives:', error);
                }
            }

            // Add this to your existing archive function
            async archivePatientWithOfflineSupport(patientId, patientName) {
                if (!this.isOnline) {
                    // Store for offline sync
                    const actionId = this.addOfflineAction('archive_patient', {
                        patient_id: patientId,
                        patient_name: patientName,
                        id: patientId
                    });

                    // Remove from local display immediately for better UX
                    showNotice(`Patient "${patientName}" marked for archive (offline). Will sync when online.`, 'orange');

                    // Return a promise that resolves immediately for offline
                    return Promise.resolve({
                        success: true,
                        offline: true,
                        actionId: actionId,
                        message: 'Patient marked for archive (offline)'
                    });
                }

                // Online: proceed with normal archive
                try {
                    const response = await fetch('/dentalemr_system/php/treatmentrecords/treatment.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: new URLSearchParams({
                            archive_id: patientId
                        })
                    });

                    return await response.json();
                } catch (error) {
                    console.error('Archive request failed:', error);

                    // Fallback to offline mode if request fails
                    const actionId = this.addOfflineAction('archive_patient', {
                        patient_id: patientId,
                        patient_name: patientName,
                        id: patientId
                    });

                    return {
                        success: true,
                        offline: true,
                        actionId: actionId,
                        message: 'Archive failed, saved for offline sync'
                    };
                }
            }
        }

        // Initialize offline sync manager
        const offlineSync = new OfflineSyncManager();

        // Add to window for global access
        window.offlineSync = offlineSync;

        // Enhanced notification function with better styling
        function showNotice(message, color = "blue") {
            const notice = document.getElementById("notice");
            if (!notice) return;

            // Map color names to actual colors
            const colorMap = {
                'blue': '#3b82f6',
                'green': '#10b981',
                'red': '#ef4444',
                'orange': '#f59e0b',
                'yellow': '#fbbf24'
            };

            const bgColor = colorMap[color] || color;

            notice.textContent = message;
            notice.style.background = bgColor;
            notice.style.display = "block";
            notice.style.opacity = "1";
            notice.style.position = "fixed";
            notice.style.top = "14px";
            notice.style.right = "14px";
            notice.style.padding = "12px 16px";
            notice.style.borderRadius = "8px";
            notice.style.color = "white";
            notice.style.fontWeight = "500";
            notice.style.boxShadow = "0 4px 6px -1px rgba(0, 0, 0, 0.1)";
            notice.style.zIndex = "9999";
            notice.style.maxWidth = "350px";
            notice.style.wordBreak = "break-word";

            setTimeout(() => {
                notice.style.transition = "opacity 0.6s ease";
                notice.style.opacity = "0";
                setTimeout(() => {
                    notice.style.display = "none";
                    notice.style.transition = "";
                }, 600);
            }, 5000);
        }

        // Enhanced fetch with offline fallback
        async function fetchWithOfflineFallback(url, options = {}) {
            if (!navigator.onLine) {
                // Check if we have offline data
                const cachedData = localStorage.getItem(`cache_${url}`);
                if (cachedData) {
                    return JSON.parse(cachedData);
                }

                throw new Error('You are offline and no cached data is available');
            }

            try {
                const response = await fetch(url, options);
                const data = await response.json();

                // Cache successful responses
                if (response.ok && options.method === 'GET') {
                    try {
                        localStorage.setItem(`cache_${url}`, JSON.stringify(data));
                    } catch (e) {
                        console.warn('Could not cache data, storage might be full');
                    }
                }

                return data;
            } catch (error) {
                console.error('Fetch failed:', error);

                // Try to return cached data as fallback
                const cachedData = localStorage.getItem(`cache_${url}`);
                if (cachedData) {
                    console.log('Returning cached data as fallback');
                    return JSON.parse(cachedData);
                }

                throw error;
            }
        }

        // Monitor network status with visual indicator
        function setupNetworkStatusIndicator() {
            const indicator = document.createElement('div');
            indicator.id = 'network-status';
            indicator.style.position = 'fixed';
            indicator.style.bottom = '10px';
            indicator.style.right = '10px';
            indicator.style.width = '12px';
            indicator.style.height = '12px';
            indicator.style.borderRadius = '50%';
            indicator.style.zIndex = '9998';
            indicator.style.transition = 'all 0.3s ease';

            document.body.appendChild(indicator);

            function updateIndicator() {
                if (navigator.onLine) {
                    indicator.style.backgroundColor = '#10b981';
                    indicator.style.boxShadow = '0 0 8px rgba(16, 185, 129, 0.5)';
                    indicator.title = 'Online';
                } else {
                    indicator.style.backgroundColor = '#ef4444';
                    indicator.style.boxShadow = '0 0 8px rgba(239, 68, 68, 0.5)';
                    indicator.title = 'Offline';
                }
            }

            updateIndicator();
            window.addEventListener('online', updateIndicator);
            window.addEventListener('offline', updateIndicator);
        }

        // Call setup on DOMContentLoaded
        document.addEventListener('DOMContentLoaded', () => {
            setupNetworkStatusIndicator();

            // Override the original archive function to use offline support
            if (typeof window.archivePatient === 'function') {
                const originalArchive = window.archivePatient;
                window.archivePatient = async function(patientId, patientName) {
                    return await offlineSync.archivePatientWithOfflineSupport(patientId, patientName);
                };
            }

            // Check for pending offline actions on page load
            setTimeout(() => {
                if (offlineSync.offlineActions.length > 0 && navigator.onLine) {
                    showNotice(`You have ${offlineSync.offlineActions.length} pending offline actions. Syncing...`, 'yellow');
                    offlineSync.syncOfflineActions();
                }
            }, 2000);
        });
    </script>
</body>

</html>