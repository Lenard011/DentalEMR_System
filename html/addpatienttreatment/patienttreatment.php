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
    <title>Patient Information</title>
    <!-- <link href="../css/style.css" rel="stylesheet"> -->
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <style>
        .controls {
            margin-bottom: 20px;
        }

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

        /* containers (your existing class names) */
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
                                <a href="../treatmentrecords/treatmentrecords.php"
                                    class="flex items-center p-2 pl-11 w-full text-base font-medium text-gray-900 rounded-lg transition duration-75 group hover:bg-gray-100 dark:text-white dark:hover:bg-gray-700">Treatment
                                    Records</a>
                            </li>
                            <li>
                                <a href="#" style="color: blue;"
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
        <form action="">
            <input type="hidden" id="patient_id">
            <input type="hidden" id="visit_id" value="0">
            <!-- Individual Patient Treatment Record Inforamtion -->
            <main class="p-3 md:ml-64 h-auto pt-15" id="patienttreatment"
                style="display: flex; flex-direction: column;">
                <div class="text-center">
                    <p class="text-lg font-semibold  text-gray-900 dark:text-white">Individual Patient Treatment Record
                    </p>
                </div>
                <!-- Search Form -->
                <form class=" mb-5" id="searchForm" autocomplete="off">
                    <div class="relative w-100">
                        <div class="absolute inset-y-0 start-0 flex items-center ps-3 pointer-events-none">
                            <svg class="w-3 h-3 text-gray-500 dark:text-gray-400" aria-hidden="true"
                                xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 20 20">
                                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"
                                    stroke-width="2" d="m19 19-4-4m0-7A7 7 0 1 1 1 8a7 7 0 0 1 14 0Z" />
                            </svg>
                        </div>
                        <input type="search" id="default-search"
                            class="block w-full p-2 ps-10 text-sm text-gray-900 border border-gray-300 rounded-lg bg-white focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500"
                            placeholder="Search patient" required />

                        <div id="suggestions"
                            class="absolute z-50 w-full bg-white border border-gray-300 rounded-lg shadow-md mt-1 hidden max-h-60 overflow-y-auto">
                        </div>
                    </div>
                </form>
                <section class="bg-white dark:bg-gray-900 p-3 rounded-lg mb-3 mt-3">
                    <div class="flex flex-row items-center justify-between  w-full gap-2 mb-4">
                        <!-- First Col -->
                        <div class="flex items-center flex-col w-full gap-2 ">
                            <!-- Name -->
                            <div>
                                <label for="name"
                                    class="block mb-2 text-xs font-medium text-gray-900 dark:text-white">Name</label>
                                <div class="gap-1 sm:grid-cols-3  w-130 flex justify-between items-center">
                                    <input type="text" name="surname" id="surname" data-required data-label="Surname"
                                        class="bg-gray-50 border border-gray-300 text-gray-900 text-xs rounded-sm focus:ring-primary-600 focus:border-primary-600 block w-full p-1 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500"
                                        placeholder="Surname">
                                    <input type="text" name="firstname" id="firstname" data-required
                                        data-label="Firstname"
                                        class="bg-gray-50 border border-gray-300 text-gray-900 text-xs rounded-sm focus:ring-primary-600 focus:border-primary-600 block w-full p-1 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500"
                                        placeholder="First name">
                                    <input type="text" name="middlename" id="middlename" data-required
                                        data-label="Middlename"
                                        class="bg-gray-50 border border-gray-300 text-gray-900 text-xs rounded-sm focus:ring-primary-600 focus:border-primary-600 block w-26 p-1 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500"
                                        placeholder="Middle initial">
                                </div>
                            </div>
                            <!-- PlaceofBirth&Address -->
                            <div class="w-full">
                                <label for="name"
                                    class="block mb-2 text-xs font-medium text-gray-900 dark:text-white">Place
                                    of Birth</label>
                                <input type="text" name="pob" id="pob" data-required data-label="Place of Birth"
                                    class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-sm focus:ring-primary-600 focus:border-primary-600 block w-full p-1 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500">
                            </div>
                            <div class="w-full">
                                <label for="name"
                                    class="block mb-2 text-xs font-medium text-gray-900 dark:text-white">Address</label>
                                <input type="text" name="address" id="address" data-required data-label="Address"
                                    class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-sm focus:ring-primary-600 focus:border-primary-600 block w-full p-1 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500">
                            </div>
                        </div>
                        <!-- Second Col -->
                        <div class="flex items-center flex-col w-full gap-2 ">
                            <!-- DateofBirth -->
                            <div class="w-full">
                                <label for="date"
                                    class="block mb-2 text-xs font-medium text-gray-900 dark:text-white">Date of
                                    Birth</label>
                                <input type="date" name="dob" id="dob" data-required data-label="date of Birth"
                                    class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-600 focus:border-primary-600 block w-full p-1 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500">
                            </div>
                            <!-- Age,Sex,Pregnant -->
                            <div id="form-container" class="grid grid-cols-2 gap-4 w-full">
                                <!-- Age -->
                                <div class="flex flex-row items-center justify-between gap-2 ">
                                    <div class="age-wrapper w-full ">
                                        <label for="age"
                                            class="block mb-2 text-xs font-medium   text-gray-900 dark:text-white">Age</label>
                                        <input type="number" id="age" name="age" min="0" data-required data-label="Age"
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
                                            <input id="pregnant-yes" type="radio" value="Yes" name="pregnant" disabled
                                                class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 focus:ring-blue-500">
                                            <label for="pregnant-yes"
                                                class="ms-2 text-sm font-medium text-gray-900 dark:text-gray-300">Yes</label>
                                        </div>
                                        <div class="flex items-center">
                                            <input id="pregnant-no" type="radio" value="No" name="pregnant" checked
                                                disabled
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
                                    <input type="text" name="occupation" id="occupation" data-required
                                        data-label="Occupation"
                                        class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-sm focus:ring-primary-600 focus:border-primary-600 block w-full p-1 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500"
                                        placeholder="">
                                </div>
                                <!-- Parent/guardian -->
                                <div class="">
                                    <label for="name"
                                        class="block mb-2 text-xs font-medium text-gray-900 dark:text-white">Parent/Guardian</label>
                                    <input type="text" name="guardian" id="guardian" data-required
                                        data-label="Parent/Guardian"
                                        class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-sm focus:ring-primary-600 focus:border-primary-600 block w-full p-1 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500"
                                        placeholder="">
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Other Patient Info -->
                    <div class="grid  gap-2">
                        <p class="flex text-lg font-medium text-gray-900 dark:text-white">Other Patient Information
                            (Membership)
                        </p>
                        <div>
                            <div class="flex items-center mb-1">
                                <input id="nhts" type="checkbox" value=""
                                    class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded-sm focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600">
                                <label for="default-checkbox"
                                    class="ms-2 text-sm font-medium text-gray-900 dark:text-gray-300">National
                                    Household Targeting System - Poverty Reduction (NHTS-PR)</label>
                            </div>
                            <div class="flex items-center mb-1">
                                <input id="fourps" type="checkbox" value=""
                                    class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded-sm focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600">
                                <label for="default-checkbox"
                                    class="ms-2 text-sm font-medium text-gray-900 dark:text-gray-300">Pantawid
                                    Pamilyang Pilipino Program (4Ps)</label>
                            </div>
                            <div class="flex items-center mb-1">
                                <input id="ip" type="checkbox" value=""
                                    class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded-sm focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600">
                                <label for="default-checkbox"
                                    class="ms-2 text-sm font-medium text-gray-900 dark:text-gray-300">Indigenous
                                    People (IP)</label>
                            </div>
                            <div class="flex items-center mb-1">
                                <input id="pwd" type="checkbox" value=""
                                    class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded-sm focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600">
                                <label for="default-checkbox"
                                    class="ms-2 text-sm font-medium text-gray-900 dark:text-gray-300">Person
                                    With Disabilities (PWDs)</label>
                            </div>
                            <div class="flex items-center mb-1">
                                <input id="philhealth" type="checkbox" value=""
                                    class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded-sm focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600">
                                <div class="grid grid-cols-2 items-center w-100">
                                    <label for="default-checkbox"
                                        class="ms-2 text-sm font-medium text-gray-900 dark:text-gray-300">PhilHealth
                                        (Indicate Number)</label>
                                    <input type="text" name="philhealth_number" id="philhealth_number"
                                        class="block py-1 h-4.5 px-0 w-49.5 text-sm text-gray-900 bg-transparent border-0 border-b-2 border-gray-300 appearance-none dark:text-white dark:border-gray-600 dark:focus:border-blue-500 focus:outline-none focus:ring-0 focus:border-blue-600 peer"
                                        placeholder=" " />
                                </div>
                            </div>
                            <div class="flex items-center mb-1">
                                <input id="sss" type="checkbox" value=""
                                    class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded-sm focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600">
                                <div class="grid grid-cols-2 items-center w-100">
                                    <label for="default-checkbox"
                                        class="ms-2 text-sm font-medium text-gray-900 dark:text-gray-300">SSS
                                        (Indicate Number)</label>
                                    <input type="text" name="sss_number" id="sss_number"
                                        class="block py-1 h-4.5 px-0 w-49.5 text-sm text-gray-900 bg-transparent border-0 border-b-2 border-gray-300 appearance-none dark:text-white dark:border-gray-600 dark:focus:border-blue-500 focus:outline-none focus:ring-0 focus:border-blue-600 peer"
                                        placeholder=" " />
                                </div>
                            </div>
                            <div class="flex items-center mb-1">
                                <input id="gsis" type="checkbox" value=""
                                    class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded-sm focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600">
                                <div class="grid grid-cols-2 items-center w-100">
                                    <label for="default-checkbox"
                                        class="ms-2 text-sm font-medium text-gray-900 dark:text-gray-300">GSIS
                                        (Indicate Number)</label>
                                    <input type="text" name="gsis_number" id="gsis_number"
                                        class="block py-1 px-0 h-4.5 w-49.5 text-sm text-gray-900 bg-transparent border-0 border-b-2 border-gray-300 appearance-none dark:text-white dark:border-gray-600 dark:focus:border-blue-500 focus:outline-none focus:ring-0 focus:border-blue-600 peer"
                                        placeholder=" " />
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
                <div class="flex justify-end">
                    <button type="button" onclick="next()"
                        class="text-white justify-center  cursor-pointer inline-flex items-center bg-blue-700 hover:bg-blue-800  focus:outline-none focus:ring-blue-300 font-medium rounded-lg text-sm p-1 w-18 dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800">
                        Next
                    </button>
                </div>
            </main>
            <!-- Individual Patient Treatment Record Vital Signs, Medical history & dietary Habits/Social history -->
            <main class="p-3 md:ml-64 h-auto pt-15" id="patienttreatmentvital"
                style="display: none; flex-direction: column;">
                <div class="text-center">
                    <p class="text-lg font-semibold  text-gray-900 dark:text-white">Individual Patient Treatment Record
                    </p>
                </div>

                <section class="bg-white dark:bg-gray-900 p-3 rounded-lg mb-3 mt-3">
                    <div class="mb-3">
                        <p class="text-14 font-semibold  text-gray-900 dark:text-white">Vital Signs</p>
                    </div>
                    <!-- Vital Signs -->
                    <div class="grid gap-2 mb-4 w-full">
                        <div class="flex items-center justify-between 1 gap-2 w-full">
                            <div class="w-full">
                                <label for="name"
                                    class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Blood
                                    Preassure:</label>
                                <input type="text" id="blood_pressure" name="blood_pressure"
                                    class="bg-gray-50 border border-gray-300 text-gray-900 text-xs rounded-sm focus:ring-primary-600 focus:border-primary-600 block w-full p-1 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500"
                                    placeholder="" required="">
                            </div>
                            <div class="w-full">
                                <label for="name"
                                    class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Temperature:</label>
                                <input type="text" id="temperature" name="temperature"
                                    class="bg-gray-50 border border-gray-300 text-gray-900 text-xs rounded-sm focus:ring-primary-600 focus:border-primary-600 block w-full p-1 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500"
                                    placeholder="" required="">
                            </div>
                            <div class="w-full">
                                <label for="name"
                                    class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Pulse
                                    Rate:</label>
                                <input type="text" id="pulse_rate" name="pulse_rate"
                                    class="bg-gray-50 border border-gray-300 text-gray-900 text-xs rounded-sm focus:ring-primary-600 focus:border-primary-600 block w-full p-1 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500"
                                    placeholder="" required="">
                            </div>
                            <div class="w-full">
                                <label for="name"
                                    class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Weight:</label>
                                <input type="text" id="weight" name="weight"
                                    class="bg-gray-50 border border-gray-300 text-gray-900 text-xs rounded-sm focus:ring-primary-600 focus:border-primary-600 block w-full p-1 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500"
                                    placeholder="" required="">
                            </div>
                        </div>
                    </div>
                    <!-- Medical History -->
                    <div class="grid mb-4 gap-2 ">
                        <p class="text-14 font-semibold text-gray-900 dark:text-white">Medical History
                        </p>
                        <div>
                            <div class="flex w-125  items-center mb-1">
                                <input type="checkbox" name="allergies_flag" id="allergies_flag" value="1"
                                    onchange="toggleInput(this, 'allergies_details')"
                                    class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded-sm focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600">
                                <div class="grid grid-cols-2 items-center gap-1">
                                    <label for="default-checkbox"
                                        class="ms-2 text-sm font-medium text-gray-900 dark:text-gray-300">Allergies
                                        (Please specify)</label>
                                    <input type="text" id="allergies_details" name="allergies_details" disabled
                                        class="block py-1 h-4.5 px-0 w-59 text-sm text-gray-900 bg-transparent border-0 border-b-2 border-gray-300 appearance-none dark:text-white dark:border-gray-600 dark:focus:border-blue-500 focus:outline-none focus:ring-0 focus:border-blue-600 peer"
                                        placeholder=" " />
                                </div>
                            </div>
                            <div class="flex items-center mb-1">
                                <input type="checkbox" name="hypertension_cva" id="hypertension_cva" value="1"
                                    class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded-sm focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600">
                                <label for="default-checkbox"
                                    class="ms-2 text-sm font-medium text-gray-900 dark:text-gray-300">Hypertension
                                    / CVA</label>
                            </div>
                            <div class="flex items-center mb-1">
                                <input type="checkbox" name="diabetes_mellitus" value="1" id="diabetes_mellitus"
                                    class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded-sm focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600">
                                <label for="default-checkbox"
                                    class="ms-2 text-sm font-medium text-gray-900 dark:text-gray-300">Diabetes
                                    Mellitus</label>
                            </div>
                            <div class="flex items-center mb-1">
                                <input type="checkbox" name="blood_disorders" value="1" id="blood_disorders"
                                    class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded-sm focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600">
                                <label for="default-checkbox"
                                    class="ms-2 text-sm font-medium text-gray-900 dark:text-gray-300">Blood
                                    Disorders</label>
                            </div>
                            <div class="flex items-center mb-1">
                                <input type="checkbox" name="heart_disease" value="1" id="heart_disease"
                                    class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded-sm focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600">
                                <label for="default-checkbox"
                                    class="ms-2 text-sm font-medium text-gray-900 dark:text-gray-300">cardiovarscular
                                    / Heart Diseases</label>
                            </div>
                            <div class="flex items-center mb-1">
                                <input type="checkbox" name="thyroid_disorders" value="1" id="thyroid_disorders"
                                    class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded-sm focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600">
                                <label for="default-checkbox"
                                    class="ms-2 text-sm font-medium text-gray-900 dark:text-gray-300">Thyroid
                                    Disorders</label>
                            </div>
                            <div class="flex w-125  items-center mb-1">
                                <input type="checkbox" name="hepatitis_flag" value="1" id="hepatitis_flag"
                                    onchange="toggleInput(this, 'hepatitis_details')"
                                    class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded-sm focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600">
                                <div class="grid grid-cols-2 items-center gap-1">
                                    <label for="default-checkbox"
                                        class="ms-2 text-sm w-50  font-medium text-gray-900 dark:text-gray-300">Hepatitis
                                        (Please specify type)</label>
                                    <input type="text" id="hepatitis_details" name="hepatitis_details" disabled
                                        class="block py-1 h-4.5 px-0 w-59 text-sm text-gray-900 bg-transparent border-0 border-b-2 border-gray-300 appearance-none dark:text-white dark:border-gray-600 dark:focus:border-blue-500 focus:outline-none focus:ring-0 focus:border-blue-600 peer"
                                        placeholder=" " />
                                </div>
                            </div>
                            <div class="flex w-125  items-center mb-1">
                                <input type="checkbox" name="malignancy_flag" value="1" id="malignancy_flag"
                                    onchange="toggleInput(this, 'malignancy_details')"
                                    class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded-sm focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600">
                                <div class="grid grid-cols-2 items-center gap-1">
                                    <label for="default-checkbox"
                                        class="ms-2 w-50 text-sm  font-medium text-gray-900 dark:text-gray-300">Malignancy
                                        (Please specify)</label>
                                    <input type="text" id="malignancy_details" name="malignancy_details" disabled
                                        class="block py-1 h-4.5 px-0 w-59 text-sm text-gray-900 bg-transparent border-0 border-b-2 border-gray-300 appearance-none dark:text-white dark:border-gray-600 dark:focus:border-blue-500 focus:outline-none focus:ring-0 focus:border-blue-600 peer"
                                        placeholder=" " />
                                </div>
                            </div>
                            <div class="flex items-center mb-1">
                                <input type="checkbox" name="prev_hospitalization_flag" value="1"
                                    id="prev_hospitalization_flag" onchange="toggleHospitalization(this)"
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
                                        <label class="w-27 text-sm font-medium text-gray-900 dark:text-gray-300 ">
                                            Last Admission:</label>
                                        <input type="date" id="last_admission_date" name="last_admission_date" disabled
                                            class="block py-1 px-0 h-4.5  text-sm text-gray-900 bg-transparent border-0 border-b-2 border-gray-300 appearance-none dark:text-white dark:border-gray-600 dark:focus:border-blue-500 focus:outline-none focus:ring-0 focus:border-blue-600 peer"
                                            placeholder="" />
                                    </div>
                                    <span>&</span>
                                    <div class="flex flex-row items-center w-52 ">
                                        <label class="w-15 text-sm font-medium text-gray-900 dark:text-gray-300 ">
                                            Cause:</label>
                                        <input type="text" id="admission_cause" name="admission_cause" disabled
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
                                    id="blood_transfusion_flag" onchange="toggleInput(this, 'blood_transfusion_date')"
                                    class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded-sm focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600">
                                <div class="grid grid-cols-2 items-center gap-1">
                                    <label for="default-checkbox"
                                        class="ms-2 not-last-of-type:w-40 text-sm font-medium text-gray-900 dark:text-gray-300">Blood
                                        transfusion (Month & Year)</label>
                                    <input type="text" id="blood_transfusion" name="blood_transfusion_date" disabled
                                        class="block py-1 h-4.5 px-0 w-59.5 text-sm text-gray-900 bg-transparent border-0 border-b-2 border-gray-300 appearance-none dark:text-white dark:border-gray-600 dark:focus:border-blue-500 focus:outline-none focus:ring-0 focus:border-blue-600 peer"
                                        placeholder=" " />
                                </div>
                            </div>
                            <div class="flex w-120 items-center mb-1">
                                <input type="checkbox" name="tattoo" value="1" id="tattoo"
                                    class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded-sm focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600">
                                <label for="default-checkbox"
                                    class="ms-2 text-sm font-medium text-gray-900 dark:text-gray-300">Tattoo</label>
                            </div>
                        </div>
                        <div class="flex w-125  items-center mb-1">
                            <input type="checkbox" name="other_conditions_flag" value="1" id="other_conditions_flag"
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
                    <!-- Dietary Habits / social History -->
                    <div>
                        <p class="text-14 font-semibold text-gray-900 dark:text-white">Dietary Habits / Social
                            History
                        </p>
                        <div class="grid mb-4 gap-2">
                            <div>
                                <div class="flex items-center mb-1">
                                    <input type="checkbox" name="sugar_flag" value="1" id="sugar_flag"
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
                                    <input type="checkbox" name="alcohol_flag" value="1" id="alcohol_flag"
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
                                    <input type="checkbox" name="tobacco_flag" value="1" id="tobacco_flag"
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
                                    <input type="checkbox" name="betel_nut_flag" value="1" id="betel_nut_flag"
                                        onchange="toggleInput(this, 'betel_nut_details')"
                                        class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded-sm focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600">
                                    <div class="grid grid-cols-2 gap-4">
                                        <label for="default-checkbox"
                                            class="ms-2 text-sm font-medium text-gray-900 dark:text-gray-300">Betel
                                            Nut Chewing (Amount, Frequency & Duration)</label>
                                        <input type="text" id="betel_nut_details" name="betel_nut_details" disabled
                                            class="block py-1 h-4.5 px-0 w-73.5 text-sm text-gray-900 bg-transparent border-0 border-b-2 border-gray-300 appearance-none dark:text-white dark:border-gray-600 dark:focus:border-blue-500 focus:outline-none focus:ring-0 focus:border-blue-600 peer"
                                            placeholder=" " />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
                <div class="flex justify-between w-full">
                    <button type="button" onclick="back()"
                        class="text-white justify-center  cursor-pointer inline-flex items-center bg-blue-700 hover:bg-blue-800  focus:outline-none focus:ring-blue-300 font-medium rounded-lg text-sm p-1 w-18 dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800">
                        Back
                    </button>
                    <button id="next1Btn" type="button" onclick="saveAndNext1()"
                        class="text-white justify-center  cursor-pointer inline-flex items-center bg-blue-700 hover:bg-blue-800  focus:outline-none focus:ring-blue-300 font-medium rounded-lg text-sm p-1 w-18 dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800">
                        Next
                    </button>
                </div>
            </main>
            <!-- Oral Health Condition A&B -->
            <main class="p-3 md:ml-64 h-auto pt-15" id="oralhealth" style="display: none; flex-direction: column;">
                <div class="text-center">
                    <p class="text-lg font-semibold  text-gray-900 dark:text-white">Oral Health Condition</p>
                </div>

                <section class="bg-white dark:bg-gray-900 p-3 rounded-lg mb-3 mt-3">
                    <div class="grid gap-2 mb-4">
                        <!-- A. -->
                        <div class="mb-3">
                            <p class="text-14 font-semibold  text-gray-900 dark:text-white">
                                A. Check (✓) if present (✗) if absent
                            </p>
                        </div>
                        <div class="flex justify-between col-span-2 ">
                            <div class=" flex flex-col gap-2">
                                <div class="flex flex-row justify-between w-120 items-center ">
                                    <label for="name"
                                        class="flex text-sm font-medium text-gray-900 dark:text-white">Orally Fit Child
                                        (OFC):</label>
                                    <input type="text" name="ofc" id="ofc"
                                        class="bg-gray-50 border border-gray-300 text-gray-900 text-center text-xs rounded-sm focus:ring-primary-600 focus:border-primary-600 block w-50 p-1 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500"
                                        readonly onclick="toggleCheck(this)" placeholder="Click" />
                                </div>
                                <div class="flex flex-row justify-between w-120 items-center ">
                                    <label for="name"
                                        class="flex text-sm font-medium text-gray-900 dark:text-white">Dental
                                        Caries:</label>
                                    <input type="text" name="dental_caries" id="dental_caries"
                                        class="bg-gray-50 border border-gray-300 text-center text-gray-900 text-xs rounded-sm focus:ring-primary-600 focus:border-primary-600 block w-50 p-1 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500"
                                        readonly onclick="toggleCheck(this)" placeholder="Click" />
                                </div>
                                <div class="flex flex-row justify-between w-120 items-center ">
                                    <label for="name"
                                        class="flex text-sm font-medium text-gray-900 dark:text-white">Gingivitis:</label>
                                    <input type="text" name="gingivitis" id="gingivitis"
                                        class="bg-gray-50 border border-gray-300 text-center text-gray-900 text-xs rounded-sm focus:ring-primary-600 focus:border-primary-600 block w-50 p-1 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500"
                                        readonly onclick="toggleCheck(this)" placeholder="Click" />
                                </div>
                                <div class="flex flex-row justify-between w-120 items-center ">
                                    <label for="name"
                                        class="flex text-sm font-medium text-gray-900 dark:text-white">Periodontal
                                        Disease:</label>
                                    <input type="text" name="periodontal_disease" id="periodontal_disease"
                                        class="bg-gray-50 border border-gray-300 text-center text-gray-900 text-xs rounded-sm focus:ring-primary-600 focus:border-primary-600 block w-50 p-1 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500"
                                        readonly onclick="toggleCheck(this)" placeholder="Click" />
                                </div>
                                <div class="flex flex-row justify-between w-120 ">
                                    <label for="name"
                                        class="flex  text-sm font-medium text-gray-900 dark:text-white">Others
                                        (supernumerary/mesiodens, <br>malocclusions, etc.):</label>
                                    <input type="text" name="others" id="others"
                                        class="bg-gray-50 border border-gray-300 text-center text-gray-900 text-xs rounded-sm focus:ring-primary-600 focus:border-primary-600 block w-50 p-1 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500"
                                        readonly onclick="toggleCheck(this)" placeholder="Click" />
                                </div>
                            </div>
                            <div class=" flex flex-col gap-2">
                                <div class="flex flex-row justify-between w-120 items-center ">
                                    <label for="name"
                                        class="flex text-sm font-medium text-gray-900 dark:text-white">Debris:</label>
                                    <input type="text" name="debris" id="debris"
                                        class="bg-gray-50 border border-gray-300 text-center text-gray-900 text-xs rounded-sm focus:ring-primary-600 focus:border-primary-600 block w-50 p-1 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500"
                                        readonly onclick="toggleCheck(this)" placeholder="Click" />
                                </div>
                                <div class="flex flex-row justify-between w-120 items-center ">
                                    <label for="name"
                                        class="flex text-sm font-medium text-gray-900 dark:text-white">Calculus:</label>
                                    <input type="text" name="calculus" id="calculus"
                                        class="bg-gray-50 border border-gray-300 text-center text-gray-900 text-xs rounded-sm focus:ring-primary-600 focus:border-primary-600 block w-50 p-1 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500"
                                        readonly onclick="toggleCheck(this)" placeholder="Click" />
                                </div>
                                <div class="flex flex-row justify-between w-120 items-center ">
                                    <label for="name"
                                        class="flex text-sm font-medium text-gray-900 dark:text-white">Abnormal
                                        Growth:</label>
                                    <input type="text" name="abnormal_growth" id="abnormal_growth"
                                        class="bg-gray-50 border border-gray-300 text-center text-gray-900 text-xs rounded-sm focus:ring-primary-600 focus:border-primary-600 block w-50 p-1 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500"
                                        readonly onclick="toggleCheck(this)" placeholder="Click" />
                                </div>
                                <div class="flex flex-row justify-between w-120 items-center ">
                                    <label for="name"
                                        class="flex text-sm font-medium text-gray-900 dark:text-white">Cleft Lip /
                                        Palate:</label>
                                    <input type="text" name="cleft_palate" id="cleft_palate"
                                        class="bg-gray-50 border border-gray-300 text-center text-gray-900 text-xs rounded-sm focus:ring-primary-600 focus:border-primary-600 block w-50 p-1 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500"
                                        readonly onclick="toggleCheck(this)" placeholder="Click" />
                                </div>
                            </div>
                        </div>

                        <!-- B. -->
                        <div class="mb-3">
                            <p class="text-14 font-semibold  text-gray-900 dark:text-white">B. Indicae Number</p>
                        </div>
                        <div class="flex justify-between col-span-2 ">
                            <div class=" flex flex-col gap-2">
                                <div class="flex flex-row justify-between w-120 items-center ">
                                    <label for="name" class="flex text-sm font-medium text-gray-900 dark:text-white">No.
                                        of Perm. Teeth Present:</label>
                                    <input type="text" name="perm_teeth_present" id="perm_teeth_present"
                                        class="bg-gray-50 border border-gray-300 text-gray-900 text-xs rounded-sm focus:ring-primary-600 focus:border-primary-600 block w-50 p-1 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500"
                                        placeholder="" required="">
                                </div>
                                <div class="flex flex-row justify-between w-120 items-center ">
                                    <label for="name" class="flex text-sm font-medium text-gray-900 dark:text-white">No.
                                        of Perm. Sound Teeth:</label>
                                    <input type="text" name="perm_sound_teeth" id="perm_sound_teeth"
                                        class="bg-gray-50 border border-gray-300 text-gray-900 text-xs rounded-sm focus:ring-primary-600 focus:border-primary-600 block w-50 p-1 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500"
                                        placeholder="" required="">
                                </div>
                                <div class="flex flex-row justify-between w-120 items-center ">
                                    <label for="name" class="flex text-sm font-medium text-gray-900 dark:text-white">No.
                                        of Decayed Teeth(D):</label>
                                    <input type="text" name="perm_decayed_teeth_d" id="perm_decayed_teeth_d"
                                        oninput="calcTotals()"
                                        class="bg-gray-50 border border-gray-300 text-gray-900 text-xs rounded-sm focus:ring-primary-600 focus:border-primary-600 block w-50 p-1 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500"
                                        placeholder="" required="">
                                </div>
                                <div class="flex flex-row justify-between w-120 items-center ">
                                    <label for="name" class="flex text-sm font-medium text-gray-900 dark:text-white">No.
                                        of Missing Teeth(M):</label>
                                    <input type="text" name="perm_missing_teeth_m" id="perm_missing_teeth_m"
                                        oninput="calcTotals()"
                                        class="bg-gray-50 border border-gray-300 text-gray-900 text-xs rounded-sm focus:ring-primary-600 focus:border-primary-600 block w-50 p-1 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500"
                                        placeholder="" required="">
                                </div>
                                <div class="flex flex-row justify-between w-120 items-center ">
                                    <label for="name" class="flex text-sm font-medium text-gray-900 dark:text-white">No.
                                        of Filled Teeth (F):</label>
                                    <input type="text" name="perm_filled_teeth_f" id="perm_filled_teeth_f"
                                        oninput="calcTotals()"
                                        class="bg-gray-50 border border-gray-300 text-gray-900 text-xs rounded-sm focus:ring-primary-600 focus:border-primary-600 block w-50 p-1 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500"
                                        placeholder="" required="">
                                </div>
                                <div class="flex flex-row justify-between w-120 items-center ">
                                    <label for="name"
                                        class="flex text-sm font-medium text-gray-900 dark:text-white">Total DMF
                                        Teeth:</label>
                                    <input type="text" name="perm_total_dmf" id="perm_total_dmf"
                                        placeholder="Total DMF Teeth" disabled
                                        class="bg-gray-50 border border-gray-300 text-gray-900 text-xs rounded-sm focus:ring-primary-600 focus:border-primary-600 block w-50 p-1 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500">
                                </div>
                            </div>

                            <div class=" flex flex-col gap-2">
                                <div class="flex flex-row justify-between w-120 items-center ">
                                    <label for="name" class="flex text-sm font-medium text-gray-900 dark:text-white">No.
                                        of Temp. Teeth Present:</label>
                                    <input type="text" name="temp_teeth_present" id="temp_teeth_present"
                                        class="bg-gray-50 border border-gray-300 text-gray-900 text-xs rounded-sm focus:ring-primary-600 focus:border-primary-600 block w-50 p-1 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500"
                                        placeholder="" required="">
                                </div>
                                <div class="flex flex-row justify-between w-120 items-center ">
                                    <label for="name" class="flex text-sm font-medium text-gray-900 dark:text-white">No.
                                        of Perm. Sound Teeth:</label>
                                    <input type="text" name="temp_sound_teeth" id="temp_sound_teeth"
                                        class="bg-gray-50 border border-gray-300 text-gray-900 text-xs rounded-sm focus:ring-primary-600 focus:border-primary-600 block w-50 p-1 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500"
                                        placeholder="" required="">
                                </div>
                                <div class="flex flex-row justify-between w-120 items-center ">
                                    <label for="name" class="flex text-sm font-medium text-gray-900 dark:text-white">No.
                                        of Decayed Teeth (d):</label>
                                    <input type="text" name="temp_decayed_teeth_d" id="temp_decayed_teeth_d"
                                        oninput="calcTotals()"
                                        class="bg-gray-50 border border-gray-300 text-gray-900 text-xs rounded-sm focus:ring-primary-600 focus:border-primary-600 block w-50 p-1 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500"
                                        placeholder="" required="">
                                </div>
                                <div class="flex flex-row justify-between w-120 items-center ">
                                    <label for="name" class="flex text-sm font-medium text-gray-900 dark:text-white">No.
                                        of Filled Teeth (f):</label>
                                    <input type="text" name="temp_filled_teeth_f" id="temp_filled_teeth_f"
                                        oninput="calcTotals()"
                                        class="bg-gray-50 border border-gray-300 text-gray-900 text-xs rounded-sm focus:ring-primary-600 focus:border-primary-600 block w-50 p-1 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500"
                                        placeholder="" required="">
                                </div>
                                <div class="flex flex-row justify-between w-120 items-center ">
                                    <label for="name"
                                        class="flex text-sm font-medium text-gray-900 dark:text-white">Total df
                                        Teeth:</label>
                                    <input type="text" name="temp_total_df" id="temp_total_df"
                                        placeholder="Total df Teeth" disabled
                                        class="bg-gray-50 border border-gray-300 text-gray-900 text-xs rounded-sm focus:ring-primary-600 focus:border-primary-600 block w-50 p-1 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500">
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <div class="flex justify-between w-full">
                    <button type="button" onclick="back1()"
                        class="text-white justify-center  cursor-pointer inline-flex items-center bg-blue-700 hover:bg-blue-800  focus:outline-none focus:ring-blue-300 font-medium rounded-lg text-sm p-1 w-18 dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800">
                        Back
                    </button>
                    <button type="button" onclick="saveOralHealthAndNext()"
                        class="text-white justify-center  cursor-pointer inline-flex items-center bg-blue-700 hover:bg-blue-800  focus:outline-none focus:ring-blue-300 font-medium rounded-lg text-sm p-1 w-18 dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800">
                        Next
                    </button>
                </div>
            </main>
            <!-- Oral Health Condition A -->
            <main class="p-3 md:ml-64 h-auto pt-15" id="oralhealthA&B" style="display: none; flex-direction: column;">
                <div class="text-center">
                    <p class="text-lg font-semibold  text-gray-900 dark:text-white">Oral Health Condition
                    </p>
                </div>

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
                                <p style="margin-bottom: -10px; margin-top: -10px;"
                                    class="text-sm w-20 ml-2 font-normal text-gray-900 dark:text-white">Treatment
                                </p>
                                <div class="w-170">
                                    <div class="treatmentbox" id="treatRow1"></div>
                                    <div class="conditionbox" id="treatRow2"></div>
                                </div>
                                <p style="margin-bottom: -30px; margin-top: -10px;"
                                    class="text-sm w-20 ml-2 font-normal text-gray-900 dark:text-white">Condition
                                </p>
                                <div class="w-170">
                                    <div class="gridtop" id="permanentGridtop"></div>
                                    <div class="grid1" id="permanentGridbot"></div>
                                    <div class="gridtop" id="temporaryGridtop"></div>
                                    <div class="grid1" id="temporaryGridbot"></div>
                                </div>
                                <p style="margin-top: -30px; margin-bottom: -10px;"
                                    class="text-sm w-20 ml-2 font-normal text-gray-900 dark:text-white">Condition
                                </p>
                                <div class="w-170">
                                    <div class="conditionbox1" id="treatRow3"></div>
                                    <div class="treatmentbox1" id="treatRow4"></div>
                                </div>
                                <p style="margin-top: -10px;"
                                    class="text-sm w-20 ml-2 font-normal text-gray-900 dark:text-white ">Treatment
                                </p>
                            </div>
                            <!-- Legend Condition & Treatment-->
                            <div class="flex flex-row gap-1 px-1  w-full  overflow-auto [scrollbar-width:none] [-ms-overflow-style:none] 
                                [&::-webkit-scrollbar]:hidden">
                                <!-- Condition -->
                                <div
                                    class="controls relative w-full p-1 flex rounded-sm flex-col border border-dashed border-gray-400 [border-image:repeating-linear-gradient(45deg,#6b7280_0_10px,transparent_10px_15px)_1]">
                                    <div>
                                        <p class="text-sm font-medium  text-gray-900 dark:text-white">Legend: <span
                                                class="font-normal">Condition</span>
                                        </p>
                                        <p class="text-sm font-normal  text-gray-900 dark:text-white">Capital letters
                                            shall
                                            be use for recording the condition of permanent dentition and small letters
                                            for
                                            the status of temporary dentition.
                                        </p>
                                    </div>
                                    <div class=" ">
                                        <table class="w-full text-sm text-center border-1">
                                            <thead class="text-sm align-text-top text-gray-700 border">
                                                <tr>
                                                    <th scope="col" class="border-1">
                                                        Permanent <br> <input type="checkbox" id="upperCaseChk" checked>
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
                                        <label class="text-sm font-semibold w-12  text-gray-900 dark:text-white">Color
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
                                            <p class="text-sm font-medium  text-gray-900 dark:text-white">Legend: <span
                                                    class="font-normal">Treament</span>
                                            </p>
                                            <div class="flex flex-col gap-3">
                                                <p class="text-sm font-normal  text-gray-900 dark:text-white">Topical
                                                    Fluoride
                                                    Application:
                                                </p>
                                                <p class="text-sm font-normal ml-5 text-gray-900 dark:text-white">FV -
                                                    Fluoride
                                                    Varnish
                                                <p class="text-sm font-normal ml-5 text-gray-900 dark:text-white">FG -
                                                    Fluoride
                                                    Gel
                                                </p>
                                            </div>
                                            <p class="text-sm font-normal  text-gray-900 dark:text-white">PFS - Pit and
                                                Fissure Sealant
                                            </p>
                                            <p class="text-sm font-normal  text-gray-900 dark:text-white">PF - Permanent
                                                Filling (Composite, Am, ART)
                                            </p>
                                            <p class="text-sm font-normal  text-gray-900 dark:text-white">TF - Temporary
                                                Filling
                                            </p>
                                            <p class="text-sm font-normal  text-gray-900 dark:text-white">X - Extraction
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
                    <!-- Set B -->
                    <div>
                        <div class="mb-3">
                            <p class="text-14 font-semibold  text-gray-900 dark:text-white">B. Services Monitoring
                                Chart
                            </p>
                        </div>
                        <div class="flex flex-col w-full justify-between items-center">
                            <!-- Top Teeth Section -->
                            <div class="w-full flex flex-row gap-10">
                                <div class="w-160 flex flex-col">
                                    <p style="font-size: 14.2px;"
                                        class="font-normal text-gray-900 dark:text-white p-1 mb-2">
                                        Fluoride Varnish/Fluoride Gel, Pit and fissure Sealant, Permanent Filling,
                                        temporary Filling, Extraction
                                    </p>
                                    <div class="flex flex-row justify-between items-center w-full px-1 mb-5"
                                        id="top-teeth-row1">
                                        <!-- Upper teeth 55-51 + 61-65 dynamically inserted here -->
                                    </div>
                                    <div class="flex flex-row justify-between items-center w-full px-1"
                                        id="top-teeth-row2">
                                        <!-- Upper teeth 85-81 + 71-75 dynamically inserted here -->
                                    </div>
                                </div>

                                <!-- Legend -->
                                <div class="controls flex rounded-sm flex-col">
                                    <div class="w-full flex flex-col justify-center items-center">
                                        <div class="flex flex-col gap-0.5">
                                            <p style="font-size: 14.2px;"
                                                class="text-sm font-medium text-gray-900 dark:text-white">
                                                Legend: <span class="font-normal">Treatment</span>
                                            </p>
                                            <div class="flex flex-col gap-0.5 ml-5">
                                                <p class="text-sm font-normal text-gray-900 dark:text-white">FV -
                                                    Fluoride Varnish</p>
                                                <p class="text-sm font-normal text-gray-900 dark:text-white">FG -
                                                    Fluoride Gel</p>
                                                <p class="text-sm font-normal text-gray-900 dark:text-white">PFS - Pit
                                                    and Fissure Sealant</p>
                                                <p class="text-sm font-normal text-gray-900 dark:text-white">PF -
                                                    Permanent Filling (Composite, Am, ART)</p>
                                                <p class="text-sm font-normal text-gray-900 dark:text-white">TF -
                                                    Temporary Filling</p>
                                                <p class="text-sm font-normal text-gray-900 dark:text-white">X -
                                                    Extraction</p>
                                                <p class="text-sm font-normal text-gray-900 dark:text-white">O - Others
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Treatment Selection -->
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

                            <!-- Bottom Teeth Section -->
                            <div style="margin-top: -25px;" class="w-full flex flex-row gap-5">
                                <div class="w-full flex flex-col">
                                    <p style="font-size: 14.2px;"
                                        class="font-normal text-gray-900 dark:text-white p-1 mb-2">
                                        Fluoride Varnish/Fluoride Gel, Pit and fissure Sealant, Permanent Filling,
                                        temporary Filling, Extraction
                                    </p>
                                    <div class="flex flex-row justify-between items-center w-full px-1 mb-5"
                                        id="bottom-teeth-row1">
                                        <!-- Lower teeth 18-11 dynamically inserted here -->
                                    </div>
                                    <div class="flex flex-row justify-between items-center w-full px-1"
                                        id="bottom-teeth-row2">
                                        <!-- Lower teeth 48-41 + 31-38 dynamically inserted here -->
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </section>
                <div class="flex justify-between w-full">
                    <button type="button" onclick="back2()"
                        class="text-white justify-center  cursor-pointer inline-flex items-center bg-blue-700 hover:bg-blue-800  focus:outline-none focus:ring-blue-300 font-medium rounded-lg text-sm p-1 w-18 dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800">
                        Back
                    </button>
                    <button id="saveBtn" type="button" onclick="ohcABandNext()"
                        class="text-white justify-center  cursor-pointer inline-flex items-center bg-blue-700 hover:bg-blue-800  focus:outline-none focus:ring-blue-300 font-medium rounded-lg text-sm p-1 w-18 dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800">
                        Next
                    </button>
                </div>
            </main>
            <!-- Record of Services Oriented -->
            <main class="p-3 md:ml-64 h-auto pt-15" id="recofservoriented"
                style="display: none; flex-direction: column;">
                <div class="text-center">
                    <p class="text-lg font-semibold  text-gray-900 dark:text-white">Individual Patient Treatment Record
                    </p>
                </div>

                <section class="bg-white dark:bg-gray-900 p-2 rounded-lg mb-3 mt-3">
                    <div>
                        <div class="mb-3">
                            <p class="text-14 font-semibold text-gray-900 dark:text-white">
                                Record of Services Oriented
                            </p>
                        </div>
                        <div class="flex flex-col w-full justify-between items-center gap-5">
                            <ul class="w-full space-y-1 text-sm list-disc list-inside ml-5 mb-5">
                                <li>For Oral Prophylaxis, Fluoride Varnish/Gel - Check (✓) if rendered.</li>
                                <li>For Permanent & Temporary Filling, Pit and Fissure Sealant and Extraction - Indicate
                                    number.</li>
                            </ul>
                            <div class="w-full flex flex-col gap-5 p-1">
                                <div class="w-full flex flex-row justify-between relative gap-5">
                                    <div class="w-full">
                                        <label class="flex mb-2 text-sm font-medium text-gray-900 dark:text-white">Oral
                                            Prophylaxis</label>
                                        <input type="text" name="oral_prophylaxis"
                                            class="bg-gray-50 border border-gray-300 text-gray-900 text-xs rounded-sm block w-full p-1 dark:bg-gray-700 dark:border-gray-600"
                                            required>
                                    </div>
                                    <div class="w-full">
                                        <label
                                            class="flex mb-2 text-sm font-medium text-gray-900 dark:text-white">Fluoride
                                            Varnish / Fluoride Gel</label>
                                        <input type="text" name="fluoride"
                                            class="bg-gray-50 border border-gray-300 text-gray-900 text-xs rounded-sm block w-full p-1 dark:bg-gray-700 dark:border-gray-600"
                                            required>
                                    </div>
                                    <div class="w-full">
                                        <label class="flex mb-2 text-sm font-medium text-gray-900 dark:text-white">Pit
                                            and Fissure Sealant</label>
                                        <input type="text" name="sealant"
                                            class="bg-gray-50 border border-gray-300 text-gray-900 text-xs rounded-sm block w-full p-1 dark:bg-gray-700 dark:border-gray-600"
                                            required>
                                    </div>
                                </div>

                                <div class="w-full flex flex-row justify-between relative gap-5">
                                    <div class="w-full">
                                        <label
                                            class="flex mb-2 text-sm font-medium text-gray-900 dark:text-white">Permanent
                                            Filling</label>
                                        <input type="text" name="permanent_filling"
                                            class="bg-gray-50 border border-gray-300 text-gray-900 text-xs rounded-sm block w-full p-1 dark:bg-gray-700 dark:border-gray-600"
                                            required>
                                    </div>
                                    <div class="w-full">
                                        <label
                                            class="flex mb-2 text-sm font-medium text-gray-900 dark:text-white">Temporary
                                            Filling</label>
                                        <input type="text" name="temporary_filling"
                                            class="bg-gray-50 border border-gray-300 text-gray-900 text-xs rounded-sm block w-full p-1 dark:bg-gray-700 dark:border-gray-600"
                                            required>
                                    </div>
                                    <div class="w-full">
                                        <label
                                            class="flex mb-2 text-sm font-medium text-gray-900 dark:text-white">Extraction</label>
                                        <input type="text" name="extraction"
                                            class="bg-gray-50 border border-gray-300 text-gray-900 text-xs rounded-sm block w-full p-1 dark:bg-gray-700 dark:border-gray-600"
                                            required>
                                    </div>
                                </div>

                                <div class="w-full flex flex-row justify-between relative gap-5 mb-14">
                                    <div class="w-129">
                                        <label
                                            class="flex mb-2 text-sm font-medium text-gray-900 dark:text-white">Consultation</label>
                                        <input type="text" name="consultation"
                                            class="bg-gray-50 border border-gray-300 text-gray-900 text-xs rounded-sm block w-full p-1 dark:bg-gray-700 dark:border-gray-600"
                                            required>
                                    </div>
                                    <div class="w-full">
                                        <label
                                            class="flex mb-2 text-sm font-medium text-gray-900 dark:text-white">Remarks
                                            / Others (Specify)</label>
                                        <textarea rows="4" name="remarks"
                                            class="bg-gray-50 border border-gray-300 h-30 text-gray-900 text-xs rounded-sm block w-full p-1 dark:bg-gray-700 dark:border-gray-600"
                                            required></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
                <div class="flex justify-between w-full">
                    <button type="button" onclick="back3()"
                        class="text-white justify-center  cursor-pointer inline-flex items-center bg-blue-700 hover:bg-blue-800  focus:outline-none focus:ring-blue-300 font-medium rounded-lg text-sm p-1 w-18 dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800">
                        Back
                    </button>
                    <button type="button" onclick="submitTreatmentRecord()"
                        class="text-white justify-center cursor-pointer inline-flex items-center bg-blue-700 hover:bg-blue-800 focus:outline-none focus:ring-blue-300 font-medium rounded-lg text-sm p-1 w-18 dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800">
                        Submit
                    </button>
                </div>
            </main>
        </form>
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

        function submit() {

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

    <!-- Next&Back Button Function  -->
    <script>
        function next() {
            document.getElementById("patienttreatment").style.display = "none";
            document.getElementById("patienttreatmentvital").style.display = "flex";
            document.getElementById("oralhealth").style.display = "none";
        }

        function back() {
            document.getElementById("patienttreatment").style.display = "flex";
            document.getElementById("patienttreatmentvital").style.display = "none";
            document.getElementById("oralhealth").style.display = "none";
            document.getElementById("oralhealthA&B").style.display = "none";
        }

        function next1() {
            document.getElementById("patienttreatment").style.display = "none";
            document.getElementById("patienttreatmentvital").style.display = "none";
            document.getElementById("oralhealth").style.display = "flex";
            document.getElementById("oralhealthA&B").style.display = "none";
        }

        function back1() {
            document.getElementById("patienttreatment").style.display = "none";
            document.getElementById("patienttreatmentvital").style.display = "flex";
            document.getElementById("oralhealth").style.display = "none";
            document.getElementById("oralhealthA&B").style.display = "none";
        }

        function next2() {
            document.getElementById("patienttreatment").style.display = "none";
            document.getElementById("patienttreatmentvital").style.display = "none";
            document.getElementById("oralhealth").style.display = "none";
            document.getElementById("oralhealthA&B").style.display = "flex";
        }

        function back2() {
            document.getElementById("patienttreatment").style.display = "none";
            document.getElementById("patienttreatmentvital").style.display = "none";
            document.getElementById("oralhealth").style.display = "flex";
            document.getElementById("oralhealthA&B").style.display = "none";
        }

        function next3() {
            document.getElementById("patienttreatment").style.display = "none";
            document.getElementById("patienttreatmentvital").style.display = "none";
            document.getElementById("oralhealth").style.display = "none";
            document.getElementById("oralhealthA&B").style.display = "none";
            document.getElementById("recofservoriented").style.display = "flex";
        }

        function back3() {
            document.getElementById("patienttreatment").style.display = "none";
            document.getElementById("patienttreatmentvital").style.display = "none";
            document.getElementById("oralhealth").style.display = "none";
            document.getElementById("oralhealthA&B").style.display = "flex";
            document.getElementById("recofservoriented").style.display = "none";
        }

        function submit() {
            alert("Patient Treatment Record Added Successfully!");
            location.href = "../treatmentrecords.html";
        }
    </script>

    <!-- fetch patient -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // ==============================
            // AGE & MONTH CALCULATOR SECTION
            // ==============================
            const dobField = document.getElementById('dob');
            const ageInput = document.getElementById('age');
            const monthInput = document.getElementById('agemonth');
            const monthContainer = document.getElementById('monthContainer');

            monthContainer.style.display = 'none';
            monthInput.value = '';

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

                if (months < 0 || (months === 0 && days < 0)) {
                    years--;
                    months += 12;
                }

                if (years < 0) years = 0;
                if (months < 0) months = 0;

                ageInput.value = years;
                handleMonthVisibility(years, months);
            }

            function handleMonthVisibility(years = 0, months = 0) {
                const monthContainer = document.getElementById('monthContainer');
                const monthInput = document.getElementById('agemonth');

                // Safety check
                if (!monthContainer || !monthInput) return;

                // 🧠 Logic:
                // - Show months if the patient is younger than 5 years
                // - OR if age is 0 and months > 0 (e.g., 5 months old)
                // - Hide otherwise
                if (years < 5 || (years === 0 && months > 0)) {
                    const totalMonths = (years * 12) + months;

                    // Clamp between 0–59 months (5 years max)
                    const validMonths = Math.max(0, Math.min(totalMonths, 59));

                    monthContainer.style.display = 'block';
                    monthInput.value = validMonths;
                } else {
                    monthContainer.style.display = 'none';
                    monthInput.value = '';
                }
            }


            dobField.addEventListener('change', updateFromDOB);
            ageInput.addEventListener('input', function() {
                const years = parseInt(this.value) || 0;
                handleMonthVisibility(years);
            });

            // =================================
            // PATIENT FETCH / SAVE SECTION
            // =================================
            const searchInput = document.getElementById("default-search");
            const suggestionsBox = document.getElementById("suggestions");

            // 🔍 Live search
            searchInput.addEventListener("input", async function() {
                const query = this.value.trim();
                if (query.length < 2) {
                    suggestionsBox.innerHTML = "";
                    suggestionsBox.classList.add("hidden");
                    return;
                }

                try {
                    const res = await fetch(`../../php/treatment/patient_api.php?q=${encodeURIComponent(query)}`);
                    const data = await res.json();

                    suggestionsBox.innerHTML = "";
                    if (!Array.isArray(data) || data.length === 0) {
                        suggestionsBox.classList.add("hidden");
                        return;
                    }

                    data.forEach(p => {
                        const item = document.createElement("div");
                        item.className = "p-2 text-sm";

                        // Already treated?
                        if (p.if_treatment == 1) {
                            item.classList.add("text-red-600", "italic");
                            item.textContent = `${p.surname}, ${p.firstname} has already been treated`;
                        } else {
                            item.classList.add("hover:bg-gray-100", "cursor-pointer");
                            item.innerHTML = `
                                <strong>${p.surname}, ${p.firstname} ${p.middlename ?? ""}</strong>
                                <br><span class="text-xs text-gray-600">Address: ${p.address ?? "-"}</span>
                                <br><span class="text-xs text-gray-600">Guardian: ${p.guardian ?? "-"}</span>
                            `;
                            item.addEventListener("click", () => {
                                fillPatientForm(p.patient_id);
                                suggestionsBox.innerHTML = "";
                                suggestionsBox.classList.add("hidden");
                                searchInput.value = `${p.surname}, ${p.firstname}`;
                            });
                        }

                        suggestionsBox.appendChild(item);
                    });

                    // If no data
                    if (!Array.isArray(data) || data.length === 0) {
                        const item = document.createElement("div");
                        item.className = "p-2 text-sm italic text-gray-500";
                        item.textContent = "⚠️ No patient found";
                        suggestionsBox.appendChild(item);
                    }

                    suggestionsBox.classList.remove("hidden");


                    suggestionsBox.classList.remove("hidden");
                } catch (err) {
                    console.error("Suggestion fetch failed", err);
                }
            });

            // Helper: apply object values to form inputs
            function applyObjectToForm(obj) {
                if (!obj) return;
                Object.entries(obj).forEach(([key, value]) => {
                    const el = document.getElementById(key);
                    if (!el) return;
                    if (el.type === "checkbox") {
                        el.checked = value == 1 || value === true || value === "1";
                    } else {
                        el.value = value ?? "";
                    }
                });
            }

            // Fill patient form
            async function fillPatientForm(patientId) {
                try {
                    const res = await fetch(`../../php/treatment/patient_api.php?id=${patientId}`);
                    const data = await res.json();

                    if (!data.success) return console.warn("No data for patient", data);

                    const p = data.patient || {};
                    const i = data.info || {};
                    const m = data.medical_history || {};
                    const d = data.dietary_habits || {};
                    const v = data.vital_signs || {};

                    // Basic info
                    ["surname", "firstname", "middlename", "pob", "address", "occupation", "guardian"].forEach(id => {
                        const el = document.getElementById(id);
                        if (el) el.value = p[id] ?? "";
                    });
                    // ✅ Fix for mismatched DB keys
                    if (p.place_of_birth && document.getElementById('pob')) {
                        document.getElementById('pob').value = p.place_of_birth;
                    }
                    if (p.months_old && document.getElementById('agemonth')) {
                        document.getElementById('agemonth').value = p.months_old;
                    }


                    if (document.getElementById("dob")) document.getElementById("dob").value = p.date_of_birth ?? "";
                    if (document.getElementById("age")) document.getElementById("age").value = p.age ?? "";
                    if (document.getElementById("agemonth")) document.getElementById("agemonth").value = p.months_old ?? "";
                    if (document.getElementById("sex")) document.getElementById("sex").value = p.sex ?? "";
                    if (document.getElementById("patient_id")) document.getElementById("patient_id").value = p.patient_id ?? "";

                    // Trigger month visibility
                    handleMonthVisibility(parseInt(p.age || 0), parseInt(p.months_old || 0));

                    // Vital signs and other info
                    applyObjectToForm(v);
                    applyObjectToForm(m);
                    applyObjectToForm(d);

                    // Memberships
                    const flags = {
                        nhts: i.nhts_pr,
                        fourps: i.four_ps,
                        ip: i.indigenous_people,
                        pwd: i.pwd,
                        philhealth: i.philhealth_flag,
                        sss: i.sss_flag,
                        gsis: i.gsis_flag
                    };
                    Object.entries(flags).forEach(([id, val]) => {
                        const el = document.getElementById(id);
                        if (el) el.checked = val == 1;
                    });
                    ["philhealth_number", "sss_number", "gsis_number"].forEach(id => {
                        const el = document.getElementById(id);
                        if (el) el.value = i[id] ?? "";
                    });

                    // Pregnant section logic
                    const pregnantSection = document.getElementById('pregnant-section');
                    const pregnantRadios = pregnantSection.querySelectorAll('input[name="pregnant"]');
                    if (p.sex === 'Female' && p.age >= 10 && p.age <= 49) {
                        pregnantSection.classList.remove('hidden');
                        document.getElementById('form-container').classList.replace('grid-cols-2', 'grid-cols-3');
                        pregnantRadios.forEach(r => {
                            r.disabled = false;
                            r.required = true;
                            r.checked = (p.pregnant ?? "No").toLowerCase() === r.value.toLowerCase();
                        });
                    } else {
                        pregnantSection.classList.add('hidden');
                        document.getElementById('form-container').classList.replace('grid-cols-3', 'grid-cols-2');
                        pregnantRadios.forEach(r => {
                            r.disabled = true;
                            r.required = false;
                            r.checked = r.value === "No";
                        });
                    }

                } catch (err) {
                    console.error("Fetch patient failed", err);
                }
            }

            // Save patient
            async function savePatient() {
                const pidEl = document.getElementById("patient_id");
                const pid = pidEl ? pidEl.value : null;
                if (!pid) return alert("No patient selected");

                // Pregnant value
                let pregnantValue = "No";
                document.querySelectorAll('input[name="pregnant"]').forEach(r => {
                    if (r.checked) pregnantValue = r.value;
                });

                // 🧠 Helper for field values
                const val = id => {
                    const el = document.getElementById(id);
                    if (!el) return "";
                    if (el.type === "checkbox") return el.checked ? 1 : 0;
                    return el.value.trim();
                };

                // ✅ Collect all form data
                const payload = {
                    // --- Patient Info ---
                    patient_id: pid,
                    surname: val("surname"),
                    firstname: val("firstname"),
                    middlename: val("middlename"),
                    date_of_birth: val("dob"),
                    place_of_birth: val("pob"),
                    age: val("age"),
                    months_old: val("agemonth"),
                    sex: val("sex"),
                    address: val("address"),
                    occupation: val("occupation"),
                    guardian: val("guardian"),
                    pregnant: pregnantValue,
                    if_treatment: 1,

                    // --- Membership / Other Info ---
                    nhts_pr: val("nhts"),
                    four_ps: val("fourps"),
                    indigenous_people: val("ip"),
                    pwd: val("pwd"),
                    philhealth_flag: val("philhealth"),
                    philhealth_number: val("philhealth_number"),
                    sss_flag: val("sss"),
                    sss_number: val("sss_number"),
                    gsis_flag: val("gsis"),
                    gsis_number: val("gsis_number"),

                    // --- Medical History ---
                    allergies_flag: val("allergies_flag"),
                    allergies_details: val("allergies_details"),
                    hypertension_cva: val("hypertension_cva"),
                    diabetes_mellitus: val("diabetes_mellitus"),
                    blood_disorders: val("blood_disorders"),
                    heart_disease: val("heart_disease"),
                    thyroid_disorders: val("thyroid_disorders"),
                    hepatitis_flag: val("hepatitis_flag"),
                    hepatitis_details: val("hepatitis_details"),
                    malignancy_flag: val("malignancy_flag"),
                    malignancy_details: val("malignancy_details"),
                    prev_hospitalization_flag: val("prev_hospitalization_flag"),
                    last_admission_date: val("last_admission_date"),
                    admission_cause: val("admission_cause"),
                    surgery_details: val("surgery_details"),
                    blood_transfusion_flag: val("blood_transfusion_flag"),
                    blood_transfusion_date: val("blood_transfusion_date"),
                    tattoo: val("tattoo"),
                    other_conditions_flag: val("other_conditions_flag"),
                    other_conditions: val("other_conditions"),

                    // --- Dietary Habits ---
                    sugar_flag: val("sugar_flag"),
                    sugar_details: val("sugar_details"),
                    alcohol_flag: val("alcohol_flag"),
                    alcohol_details: val("alcohol_details"),
                    tobacco_flag: val("tobacco_flag"),
                    tobacco_details: val("tobacco_details"),
                    betel_nut_flag: val("betel_nut_flag"),
                    betel_nut_details: val("betel_nut_details"),

                    // --- Vital Signs ---
                    blood_pressure: val("blood_pressure"),
                    pulse_rate: val("pulse_rate"),
                    temperature: val("temperature"),
                    weight: val("weight")
                };

                try {
                    const res = await fetch("../../php/treatment/patient_api.php", {
                        method: "POST",
                        headers: {
                            "Content-Type": "application/json"
                        },
                        body: JSON.stringify(payload)
                    });
                    const resp = await res.json();

                    if (resp.success) {
                        alert("✅ Saved successfully");
                        fillPatientForm(pid);
                    } else {
                        alert("❌ Save error: " + (resp.error || "unknown"));
                    }
                } catch (err) {
                    console.error("Save failed", err);
                    alert("❌ Save failed (see console)");
                }
            }


            // Save + next button
            window.saveAndNext1 = function() {
                savePatient();
                next1(); // if your step navigation function exists
            };
        });
    </script><!-- oralhealthcondition  -->
    <script>
        function toggleCheck(input) {
            if (input.value === "") input.value = "✓";
            else if (input.value === "✓") input.value = "✗";
            else input.value = "";
        }

        async function saveOralHealthAndNext() {
            // Directly read patient_id from hidden input or URL param
            const pid = document.getElementById("patient_id")?.value;
            console.log("DEBUG: patient_id resolved ->", pid);

            if (!pid) {
                alert("⚠️ No patient selected.");
                return;
            }

            const payload = {
                patient_id: pid,
                orally_fit_child: document.getElementById("ofc")?.value || "",
                dental_caries: document.getElementById("dental_caries")?.value || "",
                gingivitis: document.getElementById("gingivitis")?.value || "",
                periodontal_disease: document.getElementById("periodontal_disease")?.value || "",
                others: document.getElementById("others")?.value || "",
                debris: document.getElementById("debris")?.value || "",
                calculus: document.getElementById("calculus")?.value || "",
                abnormal_growth: document.getElementById("abnormal_growth")?.value || "",
                cleft_palate: document.getElementById("cleft_palate")?.value || "",
                perm_teeth_present: document.getElementById("perm_teeth_present")?.value || 0,
                perm_sound_teeth: document.getElementById("perm_sound_teeth")?.value || 0,
                perm_decayed_teeth_d: document.getElementById("perm_decayed_teeth_d")?.value || 0,
                perm_missing_teeth_m: document.getElementById("perm_missing_teeth_m")?.value || 0,
                perm_filled_teeth_f: document.getElementById("perm_filled_teeth_f")?.value || 0,
                perm_total_dmf: document.getElementById("perm_total_dmf")?.value || 0,
                temp_teeth_present: document.getElementById("temp_teeth_present")?.value || 0,
                temp_sound_teeth: document.getElementById("temp_sound_teeth")?.value || 0,
                temp_decayed_teeth_d: document.getElementById("temp_decayed_teeth_d")?.value || 0,
                temp_filled_teeth_f: document.getElementById("temp_filled_teeth_f")?.value || 0,
                temp_total_df: document.getElementById("temp_total_df")?.value || 0
            };

            try {
                const res = await fetch("/dentalemr_system/php/treatment/oral_health_api.php", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json"
                    },
                    body: JSON.stringify(payload)
                });

                const text = await res.text();
                console.log("📡 Raw response from server:", text);

                let data;
                try {
                    data = JSON.parse(text);
                } catch (e) {
                    // alert("⚠️ Server returned invalid JSON. Check console.");
                    return;
                }

                if (data.success) {
                    // alert("✅ Oral health record saved!");
                    if (typeof next2 === "function") next2();
                } else {
                    alert("❌ Failed: " + (data.message || "Unknown error"));
                }
            } catch (err) {
                console.error("❌ Fetch error:", err);
                alert("⚠️ Error saving data. See console/network.");
            }
        }

        function calcTotals() {
            const D = parseInt(document.getElementById("perm_decayed_teeth_d")?.value) || 0;
            const M = parseInt(document.getElementById("perm_missing_teeth_m")?.value) || 0;
            const F = parseInt(document.getElementById("perm_filled_teeth_f")?.value) || 0;
            const d = parseInt(document.getElementById("temp_decayed_teeth_d")?.value) || 0;
            const f = parseInt(document.getElementById("temp_filled_teeth_f")?.value) || 0;

            document.getElementById("perm_total_dmf").value = D + M + F;
            document.getElementById("temp_total_df").value = d + f;
        }
    </script>
    <!-- Tooth Function and Api for Backend -->
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const teethParts = ['top-left', 'top-right', 'bottom-left', 'bottom-right', 'center'];
            let selectedColor = '',
                selectedCondition = '',
                selectedCase = 'upper';
            const historyStack = [],
                redoStack = [];

            const blueSelect = document.getElementById('blueSelect');
            const redSelect = document.getElementById('redSelect');
            const upperCaseChk = document.getElementById('upperCaseChk');
            const lowerCaseChk = document.getElementById('lowerCaseChk');
            const treatmentSelect = document.getElementById('treatmentSelect');

            blueSelect?.addEventListener('change', () => {
                selectedCondition = blueSelect.value;
                selectedColor = 'blue';
                if (redSelect) redSelect.value = '';
            });
            redSelect?.addEventListener('change', () => {
                selectedCondition = redSelect.value;
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

            function applyChange(key, color, cond, textCase, saveHistory = true, isTreatment = false) {
                const el = document.querySelector(`[data-key="${key}"]`);
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
                        isTreatment: !!isTreatment
                    });
                    redoStack.length = 0;
                }

                if (isTreatment) {
                    el.dataset.treatment = cond || '';
                    el.textContent = cond || '';
                    el.style.backgroundColor = '#fff';
                    el.style.color = '#000';
                    el.dataset.color = '';
                    el.dataset.condition = '';
                    el.dataset.case = '';
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

            // Undo / Redo / ClearAll
            document.getElementById('undoBtn')?.addEventListener('click', () => {
                if (!historyStack.length) return;
                const last = historyStack.pop();
                redoStack.push({
                    ...last
                });
                const el = document.querySelector(`[data-key="${last.key}"]`);
                const wasTreatment = last.isTreatment || (el && (el.classList.contains('treatment-box') || el.classList.contains('treatment1-box')));
                applyChange(last.key, last.prevColor || '', wasTreatment ? last.prevTreatment || '' : last.prevCondition || '', last.prevCase || 'upper', false, !!wasTreatment);
            });

            document.getElementById('redoBtn')?.addEventListener('click', () => {
                if (!redoStack.length) return;
                const last = redoStack.pop();
                historyStack.push({
                    ...last
                });
                const wasTreatment = last.isTreatment;
                applyChange(last.key, last.newColor || '', wasTreatment ? last.newTreatment || '' : last.newCondition || '', last.newCase || 'upper', false, !!wasTreatment);
            });

            document.getElementById('clearAll')?.addEventListener('click', () => {
                document.querySelectorAll('.part, .treatment-box, .treatment1-box, .condition-box, .condition1-box').forEach(el => {
                    el.dataset.color = '';
                    el.dataset.condition = '';
                    el.dataset.treatment = '';
                    el.dataset.case = 'upper';
                    el.textContent = '';
                    el.style.backgroundColor = '#fff';
                    el.style.color = '#000';
                });
                historyStack.length = 0;
                redoStack.length = 0;
            });

            // Tooth & Part creation
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

                teethParts.forEach(p => tooth.appendChild(createPart(id, p)));

                const tooltip = document.createElement('div');
                tooltip.className = 'tooltip';
                tooltip.textContent = label;
                tooth.appendChild(tooltip);

                container.append(position === 'top' ? toothLabel : tooth, position === 'top' ? tooth : toothLabel);
                return container;
            }

            // Load teeth grid
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

            // Create top/bottom boxes with FDI tooth mapping
            function createBox(id, row, kind, toothId) {
                const box = document.createElement('div');
                const key = `R${row}-${id}`;
                box.dataset.key = key;
                box.dataset.toothid = toothId; // ✅ Attach tooth_id for saving

                if (kind === 'treatment') {
                    box.className = (row === 4) ? 'treatment1-box' : 'treatment-box';
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
                const fdiMap = [18, 17, 16, 15, 14, 13, 12, 11, 21, 22, 23, 24, 25, 26, 27, 28]; // FDI for top/bottom 16
                const row1 = document.getElementById('treatRow1');
                for (let i = 0; i < 16; i++) row1.appendChild(createBox(i, 1, 'treatment', fdiMap[i]));
                const row2 = document.getElementById('treatRow2');
                for (let i = 0; i < 16; i++) row2.appendChild(createBox(i, 2, 'condition', fdiMap[i]));
                const row3 = document.getElementById('treatRow3');
                for (let i = 0; i < 16; i++) row3.appendChild(createBox(i, 3, 'condition', fdiMap[i]));
                const row4 = document.getElementById('treatRow4');
                for (let i = 0; i < 16; i++) row4.appendChild(createBox(i, 4, 'treatment', fdiMap[i]));
            }

            loadGrid();
            loadBoxes();

            // Save oral condition
            async function saveOralCondition() {
                const patient_id = document.querySelector("#patient_id").value;
                const visit_id = document.querySelector("#visit_id").value || 0;
                const items = [];

                function detectCaseType(condCode, datasetCase = '') {
                    if (datasetCase === 'temporary' || datasetCase === 'lower') return 'temporary';
                    if (datasetCase === 'permanent' || datasetCase === 'upper') return 'permanent';
                    if (condCode && condCode !== '✓') {
                        if (condCode === condCode.toLowerCase()) return 'temporary';
                        if (condCode === condCode.toUpperCase()) return 'permanent';
                    }
                    return 'permanent';
                }

                // Parts (tooth conditions)
                document.querySelectorAll(".part").forEach(part => {
                    const condCode = part.dataset.condition;
                    if (!condCode) return;

                    const toothDiv = part.closest(".tooth");
                    if (!toothDiv) return;
                    const tooth_id = toothDiv.dataset.toothId;
                    if (!tooth_id) return;

                    const color = part.dataset.color || '';
                    const caseType = detectCaseType(condCode, part.dataset.case);

                    items.push({
                        type: "condition",
                        tooth_id,
                        condition_code: condCode,
                        box_key: part.dataset.key,
                        color,
                        case_type: caseType
                    });
                });

                // Condition/treatment boxes
                document.querySelectorAll(".condition1-box, .condition-box, .treatment1-box, .treatment-box").forEach(box => {
                    const treatCode = box.dataset.treatment;
                    const condCode = box.dataset.condition;
                    const tooth_id = box.dataset.toothid; // ✅ direct from dataset
                    if (!tooth_id) return;

                    const color = box.dataset.color || '';
                    const key = box.dataset.key || '';
                    let caseType = 'permanent';

                    if (treatCode) {
                        caseType = (treatCode.toUpperCase() === 'TF') ? 'temporary' : 'permanent';
                        items.push({
                            type: "treatment",
                            tooth_id,
                            treatment_code: treatCode,
                            box_key: key,
                            color,
                            case_type: caseType
                        });
                    } else if (condCode) {
                        caseType = detectCaseType(condCode, box.dataset.case);
                        items.push({
                            type: "condition",
                            tooth_id,
                            condition_code: condCode,
                            box_key: key,
                            color,
                            case_type: caseType
                        });
                    }
                });

                const payload = {
                    action: "save",
                    patient_id,
                    visit_id,
                    oral_data: items
                };
                console.log("🟦 Sending payload:", payload);

                const response = await fetch("/dentalemr_system/php/treatment/oral_condition_api.php", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json"
                    },
                    body: JSON.stringify(payload)
                });

                const result = await response.json();
                console.log("🟥 Server response:", result);

                if (!result.success) alert("Error saving: " + result.error);
            }

            window.saveOralCondition = saveOralCondition;
        });

        function ohcABandNext() {
            saveOralCondition();
            saveServicesChart();
            next3();
        }
    </script>

    <!-- services monitoring chart -->
    <script>
        document.addEventListener("DOMContentLoaded", () => {
            const upperRow1 = [55, 54, 53, 52, 51, 61, 62, 63, 64, 65];
            const upperRow2 = [85, 84, 83, 82, 81, 71, 72, 73, 74, 75];
            const lowerRow1 = [18, 17, 16, 15, 14, 13, 12, 11, 21, 22, 23, 24, 25, 26, 27, 28];
            const lowerRow2 = [48, 47, 46, 45, 44, 43, 42, 41, 31, 32, 33, 34, 35, 36, 37, 38];

            const debugMode = true; // Set to false to disable debug highlights

            const createTeethInputs = (arr, containerId) => {
                const container = document.getElementById(containerId);
                arr.forEach(fdi => {
                    const div = document.createElement("div");
                    div.className = "flex flex-col items-center gap-2";
                    div.innerHTML = `
                <input type="text" id="${fdi}" name="${fdi}" readonly data-tooth-id="${fdi}"
                    class="bg-gray-50 border border-gray-300 w-10 text-gray-900 text-xs rounded-sm p-1 cursor-pointer dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white">
                <label class="flex text-sm font-medium text-gray-900 dark:text-white">${fdi}</label>
            `;
                    container.appendChild(div);

                });
            };

            createTeethInputs(upperRow1, "top-teeth-row1");
            createTeethInputs(upperRow2, "top-teeth-row2");
            createTeethInputs(lowerRow1, "bottom-teeth-row1");
            createTeethInputs(lowerRow2, "bottom-teeth-row2");

            let selectedTreatment = null;
            const treatmentSelect = document.getElementById("selcttreatment");
            treatmentSelect.addEventListener("change", () => {
                selectedTreatment = treatmentSelect.value || null;
            });

            const allInputs = document.querySelectorAll("input[data-tooth-id]");
            allInputs.forEach(input => {
                input.addEventListener("click", e => {
                    e.stopPropagation();
                    if (!selectedTreatment) {
                        alert("⚠️ Please select a treatment first!");
                        return;
                    }
                    input.value = selectedTreatment;
                    input.setAttribute("data-treatment-id", selectedTreatment);
                    input.style.backgroundColor = "#e5e7eb";
                });

                input.addEventListener("dblclick", () => {
                    input.value = "";
                    input.removeAttribute("data-treatment-id");
                    input.style.backgroundColor = "white";
                });

                // Debug log for every tooth
                if (debugMode) {
                    console.log(`Tooth ID ${input.dataset.toothId} ready. Current value: '${input.value}'`);
                }
            });
        });

        // Save function with debug logging
        async function saveServicesChart() {
            const patientId = document.getElementById("patient_id")?.value;
            if (!patientId) {
                alert("⚠️ Please select a patient first.");
                return;
            }

            const inputs = Array.from(document.querySelectorAll("input[data-tooth-id]"));
            const treatments = inputs.map(input => ({
                tooth_id: input.getAttribute("data-tooth-id"),
                treatment_id: input.getAttribute("data-treatment-id") || input.value.trim()
            })).filter(item => item.treatment_id);

            if (treatments.length === 0) {
                alert("⚠️ No treatments selected to save.");
                return;
            }

            // --- Pre-check: Validate teeth exist in DB ---
            const fdiList = treatments.map(t => t.tooth_id);
            try {
                const resCheck = await fetch("/dentalemr_system/php/treatment/check_teeth.php", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json"
                    },
                    body: JSON.stringify({
                        fdi_list: fdiList
                    })
                });
                const checkData = await resCheck.json();

                if (!checkData.success) {
                    alert("❌ " + checkData.message);
                    return;
                }

                const missingFDI = checkData.missing || [];
                if (missingFDI.length) {
                    alert("⚠️ The following teeth are missing in database: " + missingFDI.join(", "));
                    return;
                }
            } catch (err) {
                console.error("Teeth check error:", err);
                alert("❌ Could not verify teeth in database. Please try again.");
                return;
            }

            // --- Continue with normal save ---
            const payload = {
                patient_id: patientId,
                treatments
            };

            try {
                const res = await fetch("/dentalemr_system/php/treatment/services_monitoring.php", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json"
                    },
                    body: JSON.stringify(payload)
                });
                const data = await res.json();
                alert(data.success ? (data.message || "Chart saved successfully.") : "❌ " + data.message);
            } catch (err) {
                console.error("Fetch error:", err);
                alert("❌ Network or server error. Please try again.");
            }
        }
    </script>

    <!-- record od services oriented -->
    <script>
        async function submitTreatmentRecord() {
            const pid = document.getElementById("patient_id")?.value;
            if (!pid) {
                alert("⚠️ No patient selected.");
                return;
            }

            const payload = {
                patient_id: pid,
                oral_prophylaxis: document.querySelector("[name='oral_prophylaxis']").value.trim(),
                fluoride: document.querySelector("[name='fluoride']").value.trim(),
                sealant: document.querySelector("[name='sealant']").value.trim(),
                permanent_filling: document.querySelector("[name='permanent_filling']").value.trim(),
                temporary_filling: document.querySelector("[name='temporary_filling']").value.trim(),
                extraction: document.querySelector("[name='extraction']").value.trim(),
                consultation: document.querySelector("[name='consultation']").value.trim(),
                remarks: document.querySelector("[name='remarks']").value.trim(),
            };

            try {
                const res = await fetch("/dentalemr_system/php/treatment/treatment_record_api.php", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json"
                    },
                    body: JSON.stringify(payload)
                });

                const text = await res.text();
                console.log("📡 Raw server response:", text);

                let data;
                try {
                    data = JSON.parse(text);
                } catch (e) {
                    console.error("❌ Invalid JSON:", e, text);
                    alert("⚠️ Server returned invalid JSON. Check console.");
                    return;
                }

                if (data.success) {
                    alert("✅ Treatment record saved successfully!");
                    window.location.href = "/dentalemr_system/html/treatmentrecords/treatmentrecords.php";
                } else {
                    alert("❌ Failed to save: " + (data.message || "Unknown error"));
                }
            } catch (err) {
                console.error("Fetch error:", err);
                alert("⚠️ Error saving data. See console for details.");
            }
        }
    </script>

</body>

</html>