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
    // Destroy session and redirect
    session_unset();
    session_destroy();
    echo "<script>
        alert('You have been logged out due to inactivity.');
        window.location.href = '/dentalemr_system/html/login/login.html';
    </script>";
    exit;
}

// Update activity timestamp
$_SESSION['last_activity'] = time();

// Get logged-in user details
$user = $_SESSION['logged_user'];
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
            /* border: solid black 1px; */
            align-items: center;
        }

        .gridtop {
            display: flex;
            gap: 3px;
            justify-content: center;
            align-items: center;
            margin-bottom: 4px;
            /* border: solid black 1px; */
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

        /* left */
        .part-top-left {
            top: 0;
            left: 0;
            width: 50%;
            height: 50%;
            border: 1px solid #555;
            border-top-left-radius: 100%;
            transform: translate(50, 50);
            transform: translateX(-50%) rotate(-45deg);
            margin-top: 10px;
            margin-left: 7px;
        }

        /* top */
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

        /* bot */
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

        /* right */
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

        .tooth::before {
            transform: rotate(-90deg);
        }

        .tooth::after {
            transform: rotate(-45deg);
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

        /* containers (your existing class names) */
        .treatmentbox,
        .treatmentbox1,
        .conditionbox,
        .conditionbox1 {
            display: flex;
            grid-template-columns: repeat(16, 2.51rem);
            margin: 5px auto;
            justify-content: space-between;
            align-items: center;
            /* border: solid 1px black; */
        }

        /* individual boxes (clickable) */
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

        /* for condition boxes we will allow JS to set colors on the element */
    </style>
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

        .part-top-left {
            top: 0;
            left: 0;
            width: 50%;
            height: 50%;
            border: 1px solid #555;
            border-top-left-radius: 100%;
        }

        .part-top-right {
            top: 0;
            right: 0;
            width: 50%;
            height: 50%;
            border: 1px solid #555;
            border-top-right-radius: 100%;
        }

        .part-bottom-left {
            bottom: 0;
            left: 0;
            width: 50%;
            height: 50%;
            border: 1px solid #555;
            border-bottom-left-radius: 100%;
        }

        .part-bottom-right {
            bottom: 0;
            right: 0;
            width: 50%;
            height: 50%;
            border: 1px solid #555;
            border-bottom-right-radius: 100%;
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
                                <a href="#"
                                    class="block py-2 px-4 text-sm hover:bg-gray-100 dark:hover:bg-gray-600 dark:text-gray-400 dark:hover:text-white">Accounts</a>
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
                        <a href="../index.php"
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
                        <a href="../addpatient.php"
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
                                <a href="#" style="color: blue;"
                                    class="flex items-center p-2 pl-11 w-full text-base font-medium text-gray-900 rounded-lg transition duration-75 group hover:bg-gray-100 dark:text-white dark:hover:bg-gray-700">Treatment
                                    Records</a>
                            </li>
                            <li>
                                <a href="../addpatienttreatment/patienttreatment.php"
                                    class="flex items-center p-2 pl-11 w-full text-base font-medium text-gray-900 rounded-lg transition duration-75 group hover:bg-gray-100 dark:text-white dark:hover:bg-gray-700">Add
                                    Patient Treatment</a>
                            </li>
                        </ul>
                    </li>
                </ul>
                <ul class="pt-5 mt-5 space-y-2 border-t border-gray-200 dark:border-gray-700">
                    <li>
                        <a href="../reports/targetclientlist.php"
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
                        <a href="../reports/mho_ohp.php"
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
                        <a href="../reports/oralhygienefindings.php"
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
                        <a href="../archived.php"
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
                        <button type="button"
                            class="text-white cursor-pointer flex flex-row items-center justify-center gap-1 bg-blue-700 hover:bg-blue-800 font-medium rounded-sm text-xs px-1 lg:py-1 mr-2 mt-1 dark:bg-primary-600 dark:hover:bg-primary-700 focus:outline-none dark:focus:ring-primary-800">
                            <svg class="w-5 h-4 text-primary-800 dark:text-white" aria-hidden="true"
                                xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor"
                                viewBox="0 0 24 24">
                                <path fill-rule="evenodd"
                                    d="M8 3a2 2 0 0 0-2 2v3h12V5a2 2 0 0 0-2-2H8Zm-3 7a2 2 0 0 0-2 2v5a2 2 0 0 0 2 2h1v-4a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v4h1a2 2 0 0 0 2-2v-5a2 2 0 0 0-2-2H5Zm4 11a1 1 0 0 1-1-1v-4h8v4a1 1 0 0 1-1 1H9Z"
                                    clip-rule="evenodd" />
                            </svg>
                            Print
                        </button>
                    </div>
                    <div class="hidden justify-between items-center w-full lg:flex lg:w-auto lg:order-1"
                        id="mobile-menu-2">
                        <ul class="flex flex-col mt-4 font-medium lg:flex-row lg:space-x-8 lg:mt-0">
                            <li>
                                <a href="#" id="patientInfoLink"
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
                    <button type="button" id="addTooth" onclick="openOHCModalA();"
                        class="text-white cursor-pointer flex flex-row items-center justify-center gap-1 bg-blue-700 hover:bg-blue-800 font-medium rounded-sm text-xs px-1 lg:py-1 mr-2 dark:bg-primary-600 dark:hover:bg-primary-700 focus:outline-none dark:focus:ring-primary-800">
                        <svg class="h-3.5 w-3.5" fill="currentColor" viewbox="0 0 20 20"
                            xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                            <path clip-rule="evenodd" fill-rule="evenodd"
                                d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" />
                        </svg>
                        Add
                    </button>
                </div>
                <div
                    class="mx-auto flex flex-col justify-center items-center max-w-screen-xl px-1.5 py-2 lg:px-1.5 bg-white rounded-lg shadow dark:border shadow-stone-300 drop-shadow-sm dark:bg-gray-800 dark:border-gray-950">
                    <div class="items-center justify-between flex flex-row w-full">
                        <p class="text-base font-normal text-gray-950 dark:text-white ">A. Oral Health Condition</p>
                    </div>
                    <div>
                        <div class="flex justify-between gap-4 p-1 mb-2" id="yearButtons">
                            <button type="button" onclick="year1()" style="font-size: 12px;"
                                class="text-white shadow drop-shadow-sm justify-center cursor-pointer inline-flex items-center bg-blue-700 hover:bg-blue-800 focus:outline-none focus:ring-blue-300 font-normal px-3 py-1 rounded">
                                Year I
                            </button>
                            <button type="button" onclick="year2()" style="font-size: 12px;"
                                class="text-black shadow drop-shadow-sm justify-center cursor-pointer inline-flex items-center bg-white hover:bg-blue-800 hover:text-white focus:outline-none focus:ring-blue-300 font-normal px-3 py-1 rounded">
                                Year II
                            </button>
                            <button type="button" onclick="year3()" style="font-size: 12px;"
                                class="text-black shadow drop-shadow-sm justify-center cursor-pointer inline-flex items-center bg-white hover:bg-blue-800 hover:text-white focus:outline-none focus:ring-blue-300 font-normal px-3 py-1 rounded">
                                Year III
                            </button>
                            <button type="button" onclick="year4()" style="font-size: 12px;"
                                class="text-black shadow drop-shadow-sm justify-center cursor-pointer inline-flex items-center bg-white hover:bg-blue-800 hover:text-white focus:outline-none focus:ring-blue-300 font-normal px-3 py-1 rounded">
                                Year IV
                            </button>
                            <button type="button" onclick="year5()" style="font-size: 12px;"
                                class="text-black shadow drop-shadow-sm justify-center cursor-pointer inline-flex items-center bg-white hover:bg-blue-800 hover:text-white focus:outline-none focus:ring-blue-300 font-normal px-3 py-1 rounded">
                                Year V
                            </button>
                        </div>
                    </div>
                    <div class="label ">
                        <p id="yeardate" class="text-sm font-medium text-gray-950 dark:text-white ">Year I - Date</p>
                    </div>
                    <div class=" flex flex-col">
                        <div class="w-170">
                            <p style="margin-bottom: -5px; margin-top: -10px;"
                                class="text-sm w-20 ml-2 font-normal text-gray-900 dark:text-white">Treatment
                            </p>
                            <div class="treatmentbox" id="treatRow1"></div>
                            <div class="conditionbox" id="treatRow2"></div>
                            <p style="margin-bottom: -20px; margin-top: -5px;"
                                class="text-sm w-20 ml-2 font-normal text-gray-900 dark:text-white">Condition
                            </p>
                        </div>

                        <div class="w-170">
                            <div class="gridtop" id="permanentGridtop"></div>
                            <div class="grid1" id="permanentGridbot"></div>
                            <div class="gridtop" id="temporaryGridtop"></div>
                            <div class="grid1" id="temporaryGridbot"></div>
                        </div>

                        <div class="w-170">
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
                            class="text-white justify-center  cursor-pointer inline-flex items-center bg-blue-700 hover:bg-blue-800  focus:outline-none focus:ring-blue-300 font-medium rounded-lg text-sm p-1 w-15 dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800">
                            Back
                        </button>
                        <button type="button" onclick="next()"
                            class="text-white justify-center  cursor-pointer inline-flex items-center bg-blue-700 hover:bg-blue-800  focus:outline-none focus:ring-blue-300 font-medium rounded-lg text-sm p-1 w-15 dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800">
                            Next
                        </button>
                    </div>
                </div>
            </section>
        </main>

        <div id="ohcModalA" tabindex="-1" aria-hidden="true"
            class="fixed inset-0 hidden flex justify-center items-center z-50 bg-gray-600/50">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg w-full max-w-5xl p-6">
                <div class="flex flex-row justify-between items-center mb-4">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Add Oral Health Condition</h2>
                    <button type="button" id="cancelMedicalBtn"
                        class="relative cursor-pointer text-gray-500 hover:text-gray-800 dark:hover:text-white"
                        onclick="closeOHCModalA()">
                        ✕
                    </button>
                </div>
                <form id="ohcForm" class="space-y-4">
                    <input type="hidden" name="patient_id" id="patient_id" value="">
                    <section class="bg-white dark:bg-gray-900 p-2 rounded-lg mb-3 mt-3">
                        <!-- Set A -->
                        <div>
                            <input type="hidden" id="visit_id" value="0">
                            <div class="mb-3">
                                <p class="text-14 font-semibold  text-gray-900 dark:text-white">A. Oral Health Condition
                                </p>
                            </div>
                            <div class="flex flex-row w-full justify-between items-center">
                                <!-- Tooth Part -->
                                <div class="flex flex-col  gap-2 mb-4  w-170 ">
                                    <!-- A. -->
                                    <div class="w-170 flex justify-center items-center flex-row gap-5">
                                        <!-- Undo Button -->
                                        <button type="button" class="text-white justify-center cursor-pointer inline-flex items-center gap-1 
                                    bg-blue-700 hover:bg-blue-800 focus:outline-none focus:ring-blue-300 
                                    font-medium rounded-lg text-sm p-1 w-20 
                                    dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800" id="undoBtn">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none"
                                                viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                    d="M3 7v6h6M3 13a9 9 0 0 1 9-9h3a9 9 0 0 1 9 9v1" />
                                            </svg>
                                            Undo
                                        </button>
                                        <!-- Redo Button -->
                                        <button type="button" class="text-white justify-center cursor-pointer inline-flex items-center gap-1 
                                    bg-blue-700 hover:bg-blue-800 focus:outline-none focus:ring-blue-300 
                                    font-medium rounded-lg text-sm p-1 w-20 
                                    dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800" id="redoBtn">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none"
                                                viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                    d="M21 7v6h-6M21 13a9 9 0 0 0-9-9h-3a9 9 0 0 0-9 9v1" />
                                            </svg>
                                            Redo
                                        </button>
                                        <!-- Clear All Button -->
                                        <button type="button" class="text-white justify-center cursor-pointer inline-flex items-center gap-1
                                    bg-blue-700 hover:bg-blue-800 focus:outline-none focus:ring-blue-300 
                                    font-medium rounded-lg text-sm p-1 w-24 
                                    dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800" id="clearAll">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none"
                                                viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                    d="M19 7l-.867 12.142A2 2 0 0 1 16.138 21H7.862a2 2 0 0 1-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22M10 3h4a1 1 0 0 1 1 1v2H9V4a1 1 0 0 1 1-1z" />
                                            </svg>
                                            Clear All
                                        </button>
                                    </div>
                                    <div class=" flex flex-col">
                                        <div class="w-170">
                                            <p style="margin-bottom: -5px; margin-top: -10px;"
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

                                        <div class="w-170">
                                            <div class="gridtop1" id="permanentGridtop1"></div>
                                            <div class="grid11" id="permanentGridbot1"></div>
                                            <div class="gridtop1" id="temporaryGridtop1"></div>
                                            <div class="grid11" id="temporaryGridbot1"></div>
                                        </div>

                                        <div class="w-170">
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
                                <div class="flex flex-row gap-1 px-1  w-full  overflow-auto [scrollbar-width:none] [-ms-overflow-style:none] 
                                    [&::-webkit-scrollbar]:hidden">
                                    <!-- Condition -->
                                    <div
                                        class="controls relative w-full p-1 flex rounded-sm flex-col border border-dashed border-gray-400 [border-image:repeating-linear-gradient(45deg,#6b7280_0_10px,transparent_10px_15px)_1]">
                                        <div>
                                            <p class="text-sm font-medium  text-gray-900 dark:text-white">Legend: <span
                                                    class="font-normal">Condition</span>
                                            </p>
                                            <p class="text-sm font-normal  text-gray-900 dark:text-white">Capital
                                                letters
                                                shall
                                                be use for recording the condition of permanent dentition and small
                                                letters
                                                for
                                                the status of temporary dentition.
                                            </p>
                                        </div>
                                        <div class=" ">
                                            <table class="w-full text-sm text-center border-1">
                                                <thead class="text-sm align-text-top text-gray-700 border">
                                                    <tr>
                                                        <th scope="col" class="border-1">
                                                            Permanent <br> <input type="checkbox" id="upperCaseChk"
                                                                checked>
                                                        </th>
                                                        <th scope="col" class=" w-20 border-1">
                                                            Tooth Condition
                                                        </th>
                                                        <th scope="col" class="border-1">
                                                            Temporary <br> <input type="checkbox" id="lowerCaseChk">
                                                        </th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr class="border-1">
                                                        <td class=" border-1">
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
                                        <div class="flex items-center w-full flex-row gap-1 ">
                                            <label
                                                class="text-sm font-semibold w-12  text-gray-900 dark:text-white">Color
                                                Code:</label>
                                            <select id="blueSelect"
                                                class="text-white justify-center  cursor-pointer inline-flex items-center bg-blue-700 hover:bg-blue-800  focus:outline-none focus:ring-blue-300 font-medium rounded-sm text-sm p-1.5 w-31.5 dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800">
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
                                                class="text-white justify-center  cursor-pointer inline-flex items-center bg-red-600 hover:bg-red-700  focus:outline-none focus:ring-red-200 font-medium rounded-sm text-sm p-1.5 w-31.5 dark:bg-red-500 dark:hover:bg-red-700 dark:focus:ring-red-700">
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
                                    <!-- Treatment -->
                                    <div
                                        class="controls  p-1 flex rounded-sm flex-col border border-dashed border-gray-400 [border-image:repeating-linear-gradient(45deg,#6b7280_0_10px,transparent_10px_15px)_1]">
                                        <div class="w-48 flex flex-col justify-center items-center p-2">
                                            <div class="flex flex-col gap-3">
                                                <p class="text-sm font-medium  text-gray-900 dark:text-white">Legend:
                                                    <span class="font-normal">Treament</span>
                                                </p>
                                                <div class="flex flex-col gap-3">
                                                    <p class="text-sm font-normal  text-gray-900 dark:text-white">
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
                                                <p class="text-sm font-normal  text-gray-900 dark:text-white">PFS - Pit
                                                    and
                                                    Fissure Sealant
                                                </p>
                                                <p class="text-sm font-normal  text-gray-900 dark:text-white">PF -
                                                    Permanent
                                                    Filling (Composite, Am, ART)
                                                </p>
                                                <p class="text-sm font-normal  text-gray-900 dark:text-white">TF -
                                                    Temporary
                                                    Filling
                                                </p>
                                                <p class="text-sm font-normal  text-gray-900 dark:text-white">X -
                                                    Extraction
                                                </p>
                                                <p class="text-sm font-normal  text-gray-900 dark:text-white">O - Others
                                                </p>
                                            </div>
                                            <div class="w-48 flex flex-col justify-center items-center p-2">
                                                <select id="treatmentSelect"
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
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>
                    <div class="flex justify-end gap-2">
                        <button type="button" onclick="saveOHCA()"
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
    <!-- Client-side 10-minute inactivity logout -->
    <script>
        let inactivityTime = 600000; // 10 minutes in ms
        let logoutTimer;

        function resetTimer() {
            clearTimeout(logoutTimer);
            logoutTimer = setTimeout(() => {
                alert("You've been logged out due to 10 minutes of inactivity.");
                window.location.href = "../php/logout.php";
            }, inactivityTime);
        }

        ["click", "mousemove", "keypress", "scroll", "touchstart"].forEach(evt => {
            document.addEventListener(evt, resetTimer, false);
        });

        resetTimer();
    </script>

    <script>
        function backmain() {
            location.href = ("treatmentrecords.php");
        }
    </script>
    <script>
        const params = new URLSearchParams(window.location.search);
        const patientId = params.get('id');

        const patientInfoLink = document.getElementById("patientInfoLink");
        if (patientInfoLink && patientId) {
            patientInfoLink.href = `view_info.php?id=${encodeURIComponent(patientId)}`;
        } else {
            patientInfoLink.addEventListener("click", (e) => {
                e.preventDefault();
                alert("Please select a patient first.");
            });
        }

        const servicesRenderedLink = document.getElementById("servicesRenderedLink");
        if (servicesRenderedLink && patientId) {
            servicesRenderedLink.href = `view_record.php?id=${encodeURIComponent(patientId)}`;
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
            window.location.href = `view_oralB.php?id=${encodeURIComponent(patientId)}`;
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
            window.location.href = `view_oral.php?id=${encodeURIComponent(patientId)}`;
        }
    </script>

    <!-- teeth Structure -->
    <script>
        const teethParts = ['top-left', 'top-right', 'bottom-left', 'bottom-right', 'center'];
        // Create tooth part
        function createPart(toothId, partName) {
            const part = document.createElement('div');
            part.className = 'part part-' + partName;
            const key = `${toothId}-${partName}`;
            part.dataset.key = key;
            part.addEventListener('click', () => {
                if (!selectedCondition) {
                    alert('Select a condition from the Blue/Red selector');
                    return;
                }
                applyChange(key, selectedColor, selectedCondition, selectedCase, true, false);
            });
            return part;
        }

        // create tooth container; tooth_id must be the DB tooth_id (or FDI fallback)
        function createTooth(id, label, position = 'bottom', tooth_id = null) {
            const container = document.createElement('div');
            container.className = 'tooth-container';

            const toothLabel = document.createElement('div');
            toothLabel.className = `tooth-label label-${position}`;
            toothLabel.textContent = label;

            const tooth = document.createElement('div');
            tooth.className = 'tooth';
            tooth.id = id;
            // store actual tooth_id (DB) if available; otherwise store FDI number as fallback
            tooth.dataset.toothId = tooth_id ?? '';

            teethParts.forEach(p => tooth.appendChild(createPart(id, p)));

            // tooltip
            const tooltip = document.createElement('div');
            tooltip.className = 'tooltip';
            tooltip.textContent = label;
            tooth.appendChild(tooltip);

            container.append(position === 'top' ? toothLabel : tooth, position === 'top' ? tooth : toothLabel);
            return container;
        }

        // Load teeth mapping from server (if available) so dataset.toothId is DB tooth_id
        async function loadGrid() {
            const permTop = document.getElementById('permanentGridtop');
            const permBot = document.getElementById('permanentGridbot');
            const tempTop = document.getElementById('temporaryGridtop');
            const tempBot = document.getElementById('temporaryGridbot');

            let teethData = [];
            try {
                const r = await fetch('/dentalemr_system/php/treatment/get_teeth.php');
                if (r.ok) teethData = await r.json();
            } catch (e) {
                console.warn('Could not load teeth mapping, will fallback to FDI numbers', e);
            }

            const permT = [55, 54, 53, 52, 51, 61, 62, 63, 64, 65];
            const permB = [18, 17, 16, 15, 14, 13, 12, 11, 21, 22, 23, 24, 25, 26, 27, 28];
            const tempT = [48, 47, 46, 45, 44, 43, 42, 41, 31, 32, 33, 34, 35, 36, 37, 38];
            const tempB = [85, 84, 83, 82, 81, 71, 72, 73, 74, 75];

            permT.forEach(n => {
                const tooth = teethData.find(t => parseInt(t.fdi_number) === n);
                permTop.appendChild(createTooth(`P-${n}`, n, 'top', tooth ? tooth.tooth_id : n));
            });
            permB.forEach(n => {
                const tooth = teethData.find(t => parseInt(t.fdi_number) === n);
                permBot.appendChild(createTooth(`P-${n}`, n, 'bottom', tooth ? tooth.tooth_id : n));
            });
            tempT.forEach(n => {
                const tooth = teethData.find(t => parseInt(t.fdi_number) === n);
                tempTop.appendChild(createTooth(`T-${n}`, n, 'top', tooth ? tooth.tooth_id : n));
            });
            tempB.forEach(n => {
                const tooth = teethData.find(t => parseInt(t.fdi_number) === n);
                tempBot.appendChild(createTooth(`T-${n}`, n, 'bottom', tooth ? tooth.tooth_id : n));
            });
        }

        // Create boxes for treatment/condition rows
        function createBox(id, row, kind) {
            const box = document.createElement('div');
            const key = `R${row}-${id}`;
            box.dataset.key = key;

            if (kind === 'treatment') {
                box.className = (row === 4) ? 'treatment1-box' : 'treatment-box';
                // no background color required for treatment boxes - they should be plain
                box.addEventListener('click', () => {
                    const selectedTreat = treatmentSelect?.value || '';
                    if (!selectedTreat) {
                        alert('Select a treatment from dropdown');
                        return;
                    }
                    applyChange(key, '', selectedTreat, 'upper', true, true);
                });
            } else {
                box.className = (row === 3) ? 'condition1-box' : 'condition-box';
                box.addEventListener('click', () => {
                    if (!selectedCondition) {
                        alert('Select a condition from the Blue/Red selector');
                        return;
                    }
                    applyChange(key, selectedColor, selectedCondition, selectedCase, true, false);
                });
            }

            return box;
        }

        function loadBoxes() {
            const row1 = document.getElementById('treatRow1');
            for (let i = 0; i < 16; i++) row1.appendChild(createBox(i, 1, 'treatment'));

            const row2 = document.getElementById('treatRow2');
            for (let i = 0; i < 16; i++) row2.appendChild(createBox(i, 2, 'condition'));

            const row3 = document.getElementById('treatRow3');
            for (let i = 0; i < 16; i++) row3.appendChild(createBox(i, 3, 'condition'));

            const row4 = document.getElementById('treatRow4');
            for (let i = 0; i < 16; i++) row4.appendChild(createBox(i, 4, 'treatment'));
        }

        // initialize UI
        loadGrid();
        loadBoxes();
    </script>

    <!-- Fetch  -->
    <script>
        document.addEventListener("DOMContentLoaded", () => {
            const params = new URLSearchParams(window.location.search);
            const patientId = params.get("id");
            if (!patientId) return alert("Missing patient ID");

            // Default to Year I
            highlightActiveButton(1);
            loadVisitData(patientId, 1);

            // Year button functions
            window.year1 = () => {
                highlightActiveButton(1);
                loadVisitData(patientId, 1);
            };
            window.year2 = () => {
                highlightActiveButton(2);
                loadVisitData(patientId, 2);
            };
            window.year3 = () => {
                highlightActiveButton(3);
                loadVisitData(patientId, 3);
            };
            window.year4 = () => {
                highlightActiveButton(4);
                loadVisitData(patientId, 4);
            };
            window.year5 = () => {
                highlightActiveButton(5);
                loadVisitData(patientId, 5);
            };
        });

        /**
         * Highlight the selected year button
         */
        function highlightActiveButton(yearNumber) {
            const buttons = document.querySelectorAll("#yearButtons button");
            buttons.forEach((btn, index) => {
                if (index + 1 === yearNumber) {
                    btn.style.backgroundColor = "#1d4ed8"; // Active: Blue 700
                    btn.style.color = "#ffffff";
                    btn.style.fontWeight = "bold";
                } else {
                    btn.style.backgroundColor = "#ffffff"; // Inactive
                    btn.style.color = "#000000";
                    btn.style.fontWeight = "normal";
                }
            });
        }

        /**
         * Fetch and render all tooth data for the selected year
         */
        async function loadVisitData(patientId, visitNumber) {
            try {
                clearAllTeeth();

                const res = await fetch(`/dentalemr_system/php/treatmentrecords/view_oralA.php?patient_id=${patientId}`);
                const data = await res.json();
                if (!data.success) throw new Error(data.error || "Failed to load data");

                // 🧍 Display patient full name
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


                // 🦷 Find correct visit
                const visit = data.visits.find(v => v.visit_number === visitNumber);
                if (!visit) {
                    document.getElementById("yeardate").textContent = "No records found for this year.";
                    console.warn("⚠️ No visit data found for Year " + visitNumber);
                    return;
                }

                // 🗓️ Display year/date
                document.getElementById("yeardate").textContent =
                    `${visit.visit_label} — ${new Date(visit.visit_date).toLocaleDateString()}`;

                // Apply conditions
                visit.conditions.forEach(c => {
                    const el = document.querySelector(`[data-key='${c.box_key}']`);
                    if (!el) return;

                    const dbColor = c.color?.trim() || "";
                    const fallbackColor = c.condition_code?.toLowerCase() === "m" ? "#ef4444" : "#3b82f6";
                    const fillColor = dbColor !== "" ? dbColor : fallbackColor;

                    el.style.backgroundColor = fillColor;
                    el.style.color = "#ffffff";
                    el.style.fontWeight = "bold";
                    el.style.fontSize = "10px";
                    el.style.display = "flex";
                    el.style.alignItems = "center";
                    el.style.justifyContent = "center";
                    el.style.border = "1px solid rgba(0,0,0,0.1)";

                    // ✅ keep the correct case from PHP
                    el.textContent = c.condition_code || "";
                });

                // Apply treatments
                visit.treatments.forEach(t => {
                    const treatEl = document.querySelector(`[data-key='${t.box_key}']`);
                    if (!treatEl) return;
                    treatEl.textContent = t.treatment_code?.toUpperCase() || "";
                    treatEl.style.backgroundColor = "#ffffff";
                    treatEl.style.border = "1px solid #00000080";
                    treatEl.style.color = "#000000";
                    treatEl.style.fontSize = "10px";
                    treatEl.style.fontWeight = "bold";
                    treatEl.style.display = "flex";
                    treatEl.style.alignItems = "center";
                    treatEl.style.justifyContent = "center";
                });

                console.log(`✅ Loaded Year ${visitNumber}`, visit);
            } catch (err) {
                console.error("❌ Error loading visit data:", err);
                alert("Failed to load visit data: " + err.message);
            }
        }

        /**
         * Clears all tooth colors and labels
         */
        function clearAllTeeth() {
            document.querySelectorAll(".part, [data-key^='R']").forEach(el => {
                el.style.backgroundColor = "";
                el.style.color = "";
                el.style.border = "";
                el.textContent = "";
            });
        }
    </script>

    <!-- Modal Teeth  -->
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // Extract ?id= from URL
            const urlParams = new URLSearchParams(window.location.search);
            const patientId = urlParams.get('id');
            if (patientId) {
                document.querySelector('#patient_id').value = patientId;
            }
        });

        // ------------------- MODAL SETUP -------------------
        function initOHCModalAGrid() {
            const modal = document.getElementById('ohcModalA');
            if (!modal) return;

            const teethParts = ['top-left', 'top-right', 'bottom-left', 'bottom-right', 'center'];
            let selectedColor = '',
                selectedCondition = '',
                selectedCase = 'upper';
            const historyStack = [],
                redoStack = [];

            // Scoped selectors inside the modal
            const blueSelect = modal.querySelector('#blueSelect');
            const redSelect = modal.querySelector('#redSelect');
            const upperCaseChk = modal.querySelector('#upperCaseChk');
            const lowerCaseChk = modal.querySelector('#lowerCaseChk');
            const treatmentSelect = modal.querySelector('#treatmentSelect');

            // ---------------- COLOR & CONDITION SELECTION ----------------
            blueSelect?.addEventListener('change', () => {
                const val = blueSelect.value;
                if (!val) return;
                selectedCondition = val;
                selectedColor = 'blue';
                if (redSelect) redSelect.value = '';
            });

            redSelect?.addEventListener('change', () => {
                const val = redSelect.value;
                if (!val) return;
                selectedCondition = val;
                selectedColor = 'red';
                if (blueSelect) blueSelect.value = '';
            });

            upperCaseChk?.addEventListener('change', () => {
                selectedCase = 'upper';
                upperCaseChk.checked = true;
                lowerCaseChk.checked = false;
            });

            lowerCaseChk?.addEventListener('change', () => {
                selectedCase = 'lower';
                lowerCaseChk.checked = true;
                upperCaseChk.checked = false;
            });

            function formatCondition(cond, textCase) {
                if (!cond) return '';
                if (cond.toLowerCase() === '✓') return '✓';
                return textCase === 'upper' ? cond.toUpperCase() : cond.toLowerCase();
            }

            // ---------------- APPLY CHANGES ----------------
            function applyChange(key, color, cond, textCase, saveHistory = true, isTreatment = false) {
                const el = modal.querySelector(`[data-key="${key}"]`);
                if (!el) return;

                if (saveHistory) {
                    historyStack.push({
                        key: el.dataset.key,
                        prevColor: el.dataset.color,
                        prevCondition: el.dataset.condition,
                        prevTreatment: el.dataset.treatment,
                        prevCase: el.dataset.case,
                        newColor: color,
                        newCondition: cond,
                        newTreatment: el.dataset.treatment,
                        newCase: textCase,
                        isTreatment
                    });
                    redoStack.length = 0;
                }

                if (isTreatment) {
                    el.dataset.treatment = cond || '';
                    el.textContent = cond || '';
                    el.style.backgroundColor = '#fff';
                    el.style.color = '#000';
                } else {
                    el.dataset.condition = cond || '';
                    el.dataset.color = color || '';
                    el.dataset.case = textCase || 'upper';
                    el.textContent = formatCondition(cond, textCase);
                    if (cond && cond.toLowerCase() === '✓') {
                        el.style.backgroundColor = '#fff';
                        el.style.color = '#000';
                    } else {
                        el.style.backgroundColor = color === 'blue' ? '#1e40af' : color === 'red' ? '#b91c1c' : '#fff';
                        el.style.color = '#fff';
                    }
                }
            }

            // ---------------- UNDO / REDO / CLEAR ----------------
            modal.querySelector('#undoBtn')?.addEventListener('click', () => {
                if (!historyStack.length) return;
                const last = historyStack.pop();
                redoStack.push({
                    ...last
                });
                const wasTreatment = last.isTreatment;
                applyChange(last.key, last.prevColor || '', wasTreatment ? last.prevTreatment || '' : last.prevCondition || '', last.prevCase || 'upper', false, wasTreatment);
            });

            modal.querySelector('#redoBtn')?.addEventListener('click', () => {
                if (!redoStack.length) return;
                const last = redoStack.pop();
                historyStack.push({
                    ...last
                });
                applyChange(last.key, last.newColor || '', last.isTreatment ? last.newTreatment || '' : last.newCondition || '', last.newCase || 'upper', false, last.isTreatment);
            });

            modal.querySelector('#clearAll')?.addEventListener('click', () => {
                modal.querySelectorAll('.part, .treatment-box, .treatment1-box, .condition-box, .condition1-box').forEach(el => {
                    el.dataset.color = '';
                    el.dataset.condition = '';
                    el.dataset.treatment = '';
                    el.textContent = '';
                    el.style.backgroundColor = '#fff';
                    el.style.color = '#000';
                });
                historyStack.length = 0;
                redoStack.length = 0;
            });

            // ---------------- TEETH CREATION ----------------
            function createPart(toothId, partName) {
                const part = document.createElement('div');
                part.className = 'part part-' + partName;
                const key = `${toothId}-${partName}`;
                part.dataset.key = key;
                part.addEventListener('click', () => {
                    if (!selectedCondition) return alert('Select a condition first');
                    applyChange(key, selectedColor, selectedCondition, selectedCase, true, false);
                });
                return part;
            }

            function createTooth(id, label, position = 'bottom') {
                const container = document.createElement('div');
                container.className = 'tooth-container';
                const toothLabel = document.createElement('div');
                toothLabel.className = `tooth-label label-${position}`;
                toothLabel.textContent = label;

                const tooth = document.createElement('div');
                tooth.className = 'tooth';
                tooth.id = id;
                tooth.dataset.toothId = id;

                teethParts.forEach(p => tooth.appendChild(createPart(id, p)));

                const tooltip = document.createElement('div');
                tooltip.className = 'tooltip';
                tooltip.textContent = label;
                tooth.appendChild(tooltip);

                container.append(position === 'top' ? toothLabel : tooth, position === 'top' ? tooth : toothLabel);
                return container;
            }

            function loadGrid() {
                const permTop = modal.querySelector('#permanentGridtop1');
                const permBot = modal.querySelector('#permanentGridbot1');
                const tempTop = modal.querySelector('#temporaryGridtop1');
                const tempBot = modal.querySelector('#temporaryGridbot1');

                const permT = [55, 54, 53, 52, 51, 61, 62, 63, 64, 65];
                const permB = [18, 17, 16, 15, 14, 13, 12, 11, 21, 22, 23, 24, 25, 26, 27, 28];
                const tempT = [48, 47, 46, 45, 44, 43, 42, 41, 31, 32, 33, 34, 35, 36, 37, 38];
                const tempB = [85, 84, 83, 82, 81, 71, 72, 73, 74, 75];

                permT.forEach(n => permTop.appendChild(createTooth(`P-${n}`, n, 'top')));
                permB.forEach(n => permBot.appendChild(createTooth(`P-${n}`, n, 'bottom')));
                tempT.forEach(n => tempTop.appendChild(createTooth(`T-${n}`, n, 'top')));
                tempB.forEach(n => tempBot.appendChild(createTooth(`T-${n}`, n, 'bottom')));
            }

            function createBox(id, row, kind) {
                const box = document.createElement('div');
                const key = `R${row}-${id}`;
                box.dataset.key = key;

                if (kind === 'treatment') {
                    box.className = (row === 4) ? 'treatment1-box' : 'treatment-box';
                    box.addEventListener('click', () => {
                        const selectedTreat = treatmentSelect?.value || '';
                        if (!selectedTreat) return alert('Select a treatment first');
                        applyChange(key, '', selectedTreat, 'upper', true, true);
                    });
                } else {
                    box.className = (row === 3) ? 'condition1-box' : 'condition-box';
                    box.addEventListener('click', () => {
                        if (!selectedCondition) return alert('Select a condition first');
                        applyChange(key, selectedColor, selectedCondition, selectedCase, true, false);
                    });
                }
                return box;
            }

            function loadBoxes() {
                const row1 = modal.querySelector('#treatRow11');
                const row2 = modal.querySelector('#treatRow21');
                const row3 = modal.querySelector('#treatRow31');
                const row4 = modal.querySelector('#treatRow41');

                for (let i = 0; i < 16; i++) {
                    row1.appendChild(createBox(i, 1, 'treatment'));
                    row2.appendChild(createBox(i, 2, 'condition'));
                    row3.appendChild(createBox(i, 3, 'condition'));
                    row4.appendChild(createBox(i, 4, 'treatment'));
                }
            }

            loadGrid();
            loadBoxes();

            // ---------------- SAVE FUNCTION ----------------
            window.saveOHCA = async function() {
                const patient_id = modal.querySelector("#patient_id")?.value || '';
                const visit_id = modal.querySelector("#visit_id")?.value || 0;
                const items = [];

                modal.querySelectorAll(".part").forEach(part => {
                    const condCode = part.dataset.condition;
                    if (!condCode) return;
                    const tooth_id = part.closest(".tooth")?.dataset.toothId;
                    if (!tooth_id) return;
                    const color = part.dataset.color || '';
                    const caseType = part.dataset.case === 'lower' ? 'temporary' : 'permanent';
                    items.push({
                        type: "condition",
                        tooth_id,
                        condition_code: condCode,
                        box_key: part.dataset.key,
                        color,
                        case_type: caseType
                    });
                });

                modal.querySelectorAll(".condition1-box, .condition-box, .treatment1-box, .treatment-box").forEach(box => {
                    const treatCode = box.dataset.treatment;
                    const condCode = box.dataset.condition;
                    if (!treatCode && !condCode) return;
                    items.push({
                        type: treatCode ? "treatment" : "condition",
                        tooth_id: null,
                        treatment_code: treatCode || '',
                        condition_code: condCode || '',
                        box_key: box.dataset.key,
                        color: box.dataset.color || '',
                        case_type: "permanent"
                    });
                });

                const payload = {
                    action: "save", // ✅ FIXED — must match PHP expectation
                    patient_id,
                    visit_id,
                    oral_data: items
                };

                console.log("🟩 Sending new record payload:", payload);

                const res = await fetch("/dentalemr_system/php/treatment/oral_condition_api.php", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json"
                    },
                    body: JSON.stringify(payload)
                });

                const result = await res.json();
                console.log("🟥 Add record response:", result);
                if (result.success) {
                    alert("✅ Oral Health Condition added successfully!");
                    closeOHCModalA();
                } else {
                    alert("❌ Error: " + result.error);
                }
            };

        }

        // ---------------- MODAL HANDLERS ----------------
        function openOHCModalA() {
            const modal = document.getElementById('ohcModalA');
            if (!modal) return;
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            if (!modal.dataset.loaded) {
                initOHCModalAGrid();
                modal.dataset.loaded = 'true';
            }
        }

        function closeOHCModalA() {
            const modal = document.getElementById('ohcModalA');
            if (!modal) return;
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }
    </script>
</body>

</html>