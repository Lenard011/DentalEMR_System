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
                            <button type="button" onclick="back()" class="cursor-pointer">
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
                    <button type="button" id="addOHCbtn" onclick="openOHCModal()"
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
                    class="mx-auto mb-3 max-w-screen-xl px-1.5 py-2 lg:px-1.5 bg-white rounded-lg shadow dark:border shadow-stone-300 drop-shadow-sm dark:bg-gray-800 dark:border-gray-950">
                    <div class="items-center justify-between flex flex-row">
                        <p class="text-base font-normal text-gray-950 dark:text-white ">Oral Examination</p>
                    </div>
                    <div class="mb-3">
                        <form class="justify-baseline items-end-safe inline-flex flex-row gap-2">
                            <label for="dataSelect" class="flex  text-base  text-gray-900 dark:text-white">Date:</label>
                            <select id="dataSelect"
                                class="flex bg-gray-50 border border-gray-300 text-gray-900 text-xs rounded-sm   w-30 p-0.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white">
                                <option selected>Loading...</option>
                            </select>
                        </form>
                    </div>
                    <div id="oralDataContainer" class="px-2 mb-2">
                        <div
                            class="mx-auto mb-5 max-w-screen-xl px-1.5 py-2 lg:px-1.5 bg-white rounded-lg shadow dark:border shadow-stone-300 drop-shadow-sm dark:bg-gray-800 dark:border-gray-950">
                            <div class="items-center justify-between flex flex-row mb-3">
                                <p class="text-base font-normal text-gray-950 dark:text-white ">A.</p>
                            </div>
                            <div class="flex flex-col justify-center items-center px-5 gap-5 mb-3">
                                <div class="flex items-center justify-between p-2 gap-1  max-w-5xl w-full">
                                    <div class="grid items-center justify-center grid-flow-col gap-1">
                                        <div>
                                            <ul class="max-w-full space-y-1 text-gray-900 list-disc list-inside dark:text-gray-400"
                                                style="font-size:14.6px;">
                                                <li>
                                                    Orally Fit Child (OFC)
                                                </li>
                                            </ul>
                                            <p id="orally_fit_child"
                                                class="text-xs font-normal text-red-600 dark:text-white ml-5"
                                                style="font-size:13px;">
                                                Absent</p>
                                        </div>
                                    </div>
                                    <div class="grid items-center justify-center grid-flow-col gap-1">
                                        <div>
                                            <ul class="max-w-full space-y-1 text-gray-900 list-disc list-inside dark:text-gray-400"
                                                style="font-size:14.6px;">
                                                <li>
                                                    Dental Caries
                                                </li>
                                            </ul>
                                            <p id="dental_caries"
                                                class="text-xs font-normal text-green-600 dark:text-white ml-5"
                                                style="font-size:13px;">
                                                Present</p>
                                        </div>
                                    </div>
                                    <div class="grid items-center justify-center grid-flow-col gap-1">
                                        <div>
                                            <ul class="max-w-full space-y-1 text-gray-900 list-disc list-inside dark:text-gray-400"
                                                style="font-size:14.6px;">
                                                <li>
                                                    Gingivitis
                                                </li>
                                            </ul>
                                            <p id="gingivitis"
                                                class="text-xs font-normal text-red-600 dark:text-white ml-5"
                                                style="font-size:13px;">
                                                Absent</p>
                                        </div>
                                    </div>
                                    <div class="grid items-center justify-center grid-flow-col gap-1">
                                        <div>
                                            <ul class="max-w-full space-y-1 text-gray-900 list-disc list-inside dark:text-gray-400"
                                                style="font-size:14.6px;">
                                                <li>
                                                    Periodontal Disease
                                                </li>
                                            </ul>
                                            <p id="periodontal_disease"
                                                class="text-xs font-normal text-red-600 dark:text-white ml-5"
                                                style="font-size:13px;">
                                                Absent</p>
                                        </div>
                                    </div>
                                    <div class="grid items-center justify-center grid-flow-col gap-1">
                                        <div>
                                            <ul class="max-w-full space-y-1 text-gray-900 list-disc list-inside dark:text-gray-400"
                                                style="font-size:14.6px;">
                                                <li>
                                                    Debris
                                                </li>
                                            </ul>
                                            <p id="debris" class="text-xs font-normal text-red-600 dark:text-white ml-5"
                                                style="font-size:13px;">
                                                Absent</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="flex items-center justify-center p-2 gap-30  max-w-5xl w-full">
                                <div class="grid items-center justify-center grid-flow-col gap-1">
                                    <div>
                                        <ul class="max-w-full space-y-1 text-gray-900 list-disc list-inside dark:text-gray-400"
                                            style="font-size:14.6px;">
                                            <li>
                                                Calculus
                                            </li>
                                        </ul>
                                        <p id="calculus" class="text-xs font-normal text-red-600 dark:text-white ml-5"
                                            style="font-size:13px;">
                                            Absent</p>
                                    </div>
                                </div>
                                <div class="grid items-center justify-center grid-flow-col gap-1">
                                    <div>
                                        <ul class="max-w-full space-y-1 text-gray-900 list-disc list-inside dark:text-gray-400"
                                            style="font-size:14.6px;">
                                            <li>
                                                Abnormal Growth
                                            </li>
                                        </ul>
                                        <p id="abnormal_growth"
                                            class="text-xs font-normal text-red-600 dark:text-white ml-5"
                                            style="font-size:13px;">
                                            Absent</p>
                                    </div>
                                </div>
                                <div class="grid items-center justify-center grid-flow-col gap-1">
                                    <div>
                                        <ul class="max-w-full space-y-1 text-gray-900 list-disc list-inside dark:text-gray-400"
                                            style="font-size:14.6px;">
                                            <li>
                                                Cleft Lip / Palate
                                            </li>
                                        </ul>
                                        <p id="cleft_palate"
                                            class="text-xs font-normal text-red-600 dark:text-white ml-5"
                                            style="font-size:13px;">
                                            Absent</p>
                                    </div>
                                </div>
                                <div class="grid items-center justify-center grid-flow-col gap-1">
                                    <div>
                                        <ul class="max-w-full space-y-1 text-gray-900 list-disc list-inside dark:text-gray-400"
                                            style="font-size:14.6px;">
                                            <li>
                                                Others
                                            </li>
                                        </ul>
                                        <p id="others" class="text-xs font-normal text-red-600 dark:text-white ml-5"
                                            style="font-size:13px;">
                                            Absent</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div
                            class="mx-auto max-w-screen-xl px-1.5 py-2 lg:px-1.5 bg-white rounded-lg shadow dark:border  shadow-stone-300 drop-shadow-sm dark:bg-gray-950 dark:border-gray-950">
                            <div class="items-center justify-between flex flex-row mb-3">
                                <p class="text-base font-normal text-gray-950 dark:text-white ">B.</p>
                            </div>
                            <div class="px-15">
                                <div class="flex flex-row items-center  gap-20  ">
                                    <div
                                        class="flex items-center justify-center p-2 w-40  bg-white rounded-lg shadow dark:border  shadow-stone-400  dark:bg-gray-950 dark:border-gray-950">
                                        <div class="grid items-center justify-center grid-flow-col ">
                                            <div class="flex items-center justify-center flex-col">
                                                <div class="w-14">
                                                    <div
                                                        class="rounded-full  mb-3 shadow-stone-300 shadow  border-gray-400 dark:bg-blue-300">
                                                        <img src="../img/pngtree-tooth-icon-with-a-light-blue-color-over-white-vector-png-image_12290095.png"
                                                            alt="" srcset="">
                                                    </div>
                                                </div>
                                                <p class="text-sm text-center mb-1 font-medium  dark:text-white ">
                                                    Total DMF Teeth</p>
                                                <p id="perm_total_dmf"
                                                    class="text-lg text-center font-bold  dark:text-white ">
                                                    5</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="flex items-center justify-center flex-col gap-2">
                                        <div
                                            class="flex items-center justify-center p-1 w-40  bg-white rounded-lg shadow dark:border  shadow-stone-400  dark:bg-gray-950 dark:border-gray-950">
                                            <div class="grid items-center justify-center grid-flow-row ">
                                                <div class="flex items-center justify-center flex-row">
                                                    <div class="w-12">
                                                        <div
                                                            class="rounded-full  shadow-stone-300 shadow  border-gray-400 dark:bg-blue-300">
                                                            <img src="../img/pngtree-tooth-icon-with-a-light-blue-color-over-white-vector-png-image_12290095.png"
                                                                alt="" srcset="">
                                                        </div>
                                                    </div>
                                                    <div>
                                                        <p
                                                            class="text-xs text-center mb-1 font-medium  dark:text-white ">
                                                            Perm. Teeth Present</p>
                                                        <p id="perm_teeth_present"
                                                            class="text-sm text-center font-bold  dark:text-white ">
                                                            0</p>
                                                    </div>

                                                </div>
                                            </div>
                                        </div>
                                        <div
                                            class="flex items-center justify-center p-1 w-40  bg-white rounded-lg shadow dark:border  shadow-stone-400  dark:bg-gray-950 dark:border-gray-950">
                                            <div class="grid items-center justify-center grid-flow-row ">
                                                <div class="flex items-center justify-center flex-row">
                                                    <div class="w-12">
                                                        <div
                                                            class="rounded-full  shadow-stone-300 shadow  border-gray-400 dark:bg-blue-300">
                                                            <img src="../img/pngtree-tooth-icon-with-a-light-blue-color-over-white-vector-png-image_12290095.png"
                                                                alt="" srcset="">
                                                        </div>
                                                    </div>
                                                    <div>
                                                        <p
                                                            class="text-xs text-center mb-1 font-medium  dark:text-white ">
                                                            Perm. Sound Teeth</p>
                                                        <p id="perm_sound_teeth"
                                                            class="text-sm text-center font-bold  dark:text-white ">
                                                            0</p>
                                                    </div>

                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="flex items-center justify-center flex-col gap-2">
                                        <div
                                            class="flex items-center justify-center p-1 w-40  bg-white rounded-lg shadow dark:border  shadow-stone-400  dark:bg-gray-950 dark:border-gray-950">
                                            <div class="grid items-center justify-center grid-flow-row ">
                                                <div class="flex items-center justify-center flex-row">
                                                    <div class="w-12">
                                                        <div
                                                            class="rounded-full  shadow-stone-300 shadow  border-gray-400 dark:bg-blue-300">
                                                            <img src="../img/pngtree-tooth-icon-with-a-light-blue-color-over-white-vector-png-image_12290095.png"
                                                                alt="" srcset="">
                                                        </div>
                                                    </div>
                                                    <div>
                                                        <p
                                                            class="text-xs text-center mb-1 font-medium  dark:text-white ">
                                                            Decayed teeth (D)</p>
                                                        <p id="perm_decayed_teeth_d"
                                                            class="text-sm text-center font-bold  dark:text-white ">
                                                            3</p>
                                                    </div>

                                                </div>
                                            </div>
                                        </div>
                                        <div
                                            class="flex items-center justify-center p-1 w-40  bg-white rounded-lg shadow dark:border  shadow-stone-400  dark:bg-gray-950 dark:border-gray-950">
                                            <div class="grid items-center justify-center grid-flow-row ">
                                                <div class="flex items-center justify-center flex-row">
                                                    <div class="w-12">
                                                        <div
                                                            class="rounded-full  shadow-stone-300 shadow  border-gray-400 dark:bg-blue-300">
                                                            <img src="../img/pngtree-tooth-icon-with-a-light-blue-color-over-white-vector-png-image_12290095.png"
                                                                alt="" srcset="">
                                                        </div>
                                                    </div>
                                                    <div>
                                                        <p
                                                            class="text-xs text-center mb-1 font-medium  dark:text-white ">
                                                            Missing teeth (M)</p>
                                                        <p id="perm_missing_teeth_m"
                                                            class="text-sm text-center font-bold  dark:text-white ">
                                                            2</p>
                                                    </div>

                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="flex items-center justify-center flex-col gap-2">
                                        <div
                                            class="flex items-center justify-center p-1 w-40  bg-white rounded-lg shadow dark:border  shadow-stone-400  dark:bg-gray-950 dark:border-gray-950">
                                            <div class="grid items-center justify-center grid-flow-row ">
                                                <div class="flex items-center justify-center flex-row">
                                                    <div class="w-12">
                                                        <div
                                                            class="rounded-full  shadow-stone-300 shadow  border-gray-400 dark:bg-blue-300">
                                                            <img src="../img/pngtree-tooth-icon-with-a-light-blue-color-over-white-vector-png-image_12290095.png"
                                                                alt="" srcset="">
                                                        </div>
                                                    </div>
                                                    <div>
                                                        <p
                                                            class="text-xs text-center mb-1 font-medium  dark:text-white ">
                                                            Filled Teeth</p>
                                                        <p id="perm_filled_teeth_f"
                                                            class="text-sm text-center font-bold  dark:text-white ">
                                                            0</p>
                                                    </div>

                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <hr class="h-px my-8  bg-gray-200 border-0 dark:bg-gray-700">
                                <div class="flex flex-row  items-center gap-20 mb-3 ">
                                    <div
                                        class="flex items-center justify-center p-2 w-40  bg-white rounded-lg shadow dark:border  shadow-stone-400  dark:bg-gray-950 dark:border-gray-950">
                                        <div class="grid items-center justify-center grid-flow-col ">
                                            <div class="flex items-center justify-center flex-col">
                                                <div class="w-14">
                                                    <div
                                                        class="rounded-full  mb-3 shadow-stone-300 shadow  border-gray-400 dark:bg-blue-300">
                                                        <img src="../img/pngtree-tooth-icon-with-a-light-blue-color-over-white-vector-png-image_12290095.png"
                                                            alt="" srcset="">
                                                    </div>
                                                </div>
                                                <p class="text-sm text-center mb-1 font-medium  dark:text-white ">
                                                    Total df Teeth</p>
                                                <p id="temp_total_df"
                                                    class="text-lg text-center font-bold  dark:text-white ">
                                                    0</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="flex items-center justify-center flex-col gap-2">
                                        <div
                                            class="flex items-center justify-center p-1 w-40  bg-white rounded-lg shadow dark:border  shadow-stone-400  dark:bg-gray-950 dark:border-gray-950">
                                            <div class="grid items-center justify-center grid-flow-row ">
                                                <div class="flex items-center justify-center flex-row">
                                                    <div class="w-12">
                                                        <div
                                                            class="rounded-full  shadow-stone-300 shadow  border-gray-400 dark:bg-blue-300">
                                                            <img src="../img/pngtree-tooth-icon-with-a-light-blue-color-over-white-vector-png-image_12290095.png"
                                                                alt="" srcset="">
                                                        </div>
                                                    </div>
                                                    <div>
                                                        <p
                                                            class="text-xs text-center mb-1 font-medium  dark:text-white ">
                                                            Temp. Teeth Present</p>
                                                        <p id="temp_teeth_present"
                                                            class="text-sm text-center font-bold  dark:text-white ">
                                                            0</p>
                                                    </div>

                                                </div>
                                            </div>
                                        </div>
                                        <div
                                            class="flex items-center justify-center p-1 w-40  bg-white rounded-lg shadow dark:border  shadow-stone-400  dark:bg-gray-950 dark:border-gray-950">
                                            <div class="grid items-center justify-center grid-flow-row ">
                                                <div class="flex items-center justify-center flex-row">
                                                    <div class="w-12">
                                                        <div
                                                            class="rounded-full  shadow-stone-300 shadow  border-gray-400 dark:bg-blue-300">
                                                            <img src="../img/pngtree-tooth-icon-with-a-light-blue-color-over-white-vector-png-image_12290095.png"
                                                                alt="" srcset="">
                                                        </div>
                                                    </div>
                                                    <div>
                                                        <p
                                                            class="text-xs text-center mb-1 font-medium  dark:text-white ">
                                                            Temp. Sound Teeth</p>
                                                        <p id="temp_sound_teeth"
                                                            class="text-sm text-center font-bold  dark:text-white ">
                                                            0</p>
                                                    </div>

                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="flex items-center justify-center flex-col gap-2">
                                        <div
                                            class="flex items-center justify-center p-1 w-40  bg-white rounded-lg shadow dark:border  shadow-stone-400  dark:bg-gray-950 dark:border-gray-950">
                                            <div class="grid items-center justify-center grid-flow-row ">
                                                <div class="flex items-center justify-center flex-row">
                                                    <div class="w-12">
                                                        <div
                                                            class="rounded-full  shadow-stone-300 shadow  border-gray-400 dark:bg-blue-300">
                                                            <img src="../img/pngtree-tooth-icon-with-a-light-blue-color-over-white-vector-png-image_12290095.png"
                                                                alt="" srcset="">
                                                        </div>
                                                    </div>
                                                    <div>
                                                        <p
                                                            class="text-xs text-center mb-1 font-medium  dark:text-white ">
                                                            Decayed teeth (d)</p>
                                                        <p id="temp_decayed_teeth_d"
                                                            class="text-sm text-center font-bold  dark:text-white ">
                                                            0</p>
                                                    </div>

                                                </div>
                                            </div>
                                        </div>
                                        <div
                                            class="flex items-center justify-center p-1 w-40  bg-white rounded-lg shadow dark:border  shadow-stone-400  dark:bg-gray-950 dark:border-gray-950">
                                            <div class="grid items-center justify-center grid-flow-row ">
                                                <div class="flex items-center justify-center flex-row">
                                                    <div class="w-12">
                                                        <div
                                                            class="rounded-full  shadow-stone-300 shadow  border-gray-400 dark:bg-blue-300">
                                                            <img src="../img/pngtree-tooth-icon-with-a-light-blue-color-over-white-vector-png-image_12290095.png"
                                                                alt="" srcset="">
                                                        </div>
                                                    </div>
                                                    <div>
                                                        <p
                                                            class="text-xs text-center mb-1 font-medium  dark:text-white ">
                                                            Filled teeth (f)</p>
                                                        <p id="temp_filled_teeth_f"
                                                            class="text-sm text-center font-bold  dark:text-white ">
                                                            0</p>
                                                    </div>

                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="flex justify-end">
                    <button type="button" onclick="next()"
                        class="text-white justify-center  cursor-pointer inline-flex items-center bg-blue-700 hover:bg-blue-800  focus:outline-none focus:ring-blue-300 font-medium rounded-lg text-sm p-1 w-15 dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800">
                        Next
                    </button>
                </div>
            </section>
        </main>

        <div id="ohcModal" tabindex="-1" aria-hidden="true"
            class="fixed inset-0 hidden flex justify-center items-center z-50 bg-gray-600/50">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg w-full max-w-5xl p-6">
                <div class="flex flex-row justify-between items-center mb-4">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Add Oral Health Condition</h2>
                    <button type="button" id="cancelMedicalBtn"
                        class="relative cursor-pointer text-gray-500 hover:text-gray-800 dark:hover:text-white"
                        onclick="closeOHCModal()">
                        ✕
                    </button>
                </div>
                <form id="ohcForm" class="space-y-4">
                    <input type="hidden" name="patient_id" id="patient_id" value="">

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
        function back() {
            location.href = ("treatmentrecords.php");
        }

        function next() {
            location.href = ("view_oralA.php");
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
            window.location.href = `view_oralA.php?id=${encodeURIComponent(patientId)}`;
        }
        const printdLink = document.getElementById("printdLink");
        if (printdLink && patientId) {
            printdLink.href = `print.php?id=${encodeURIComponent(patientId)}`;
        } else {
            // Optional fallback: disable link if no patient selected
            printdLink.addEventListener("click", (e) => {
                e.preventDefault();
                alert("Please select a patient first.");
            });
        }
    </script>

    <script>
        (() => {
            const params = new URLSearchParams(window.location.search);
            const patientId = params.get("id");
            const nameEl = document.getElementById("patientName");
            const dateSelect = document.getElementById("dataSelect");

            if (!patientId) {
                nameEl.textContent = "Unknown Patient";
                return;
            }

            const baseAPI = `/dentalemr_system/php/treatmentrecords/view_oral.php`;

            // --- Utility functions ---

            const setCondition = (id, value) => {
                const el = document.getElementById(id);
                if (!el) return;

                const val = String(value || "").trim().toLowerCase();
                const presentValues = ["✓", "√", "1", "yes", "true", "present", "checked", "on"];
                const absentValues = ["x", "✗", "×", "no", "false", "0", "absent", ""];
                const isPresent = presentValues.includes(val) && !absentValues.includes(val);

                el.textContent = isPresent ? "Present" : "Absent";
                el.classList.toggle("text-green-600", isPresent);
                el.classList.toggle("text-red-600", !isPresent);
            };

            const setValue = (id, value) => {
                const el = document.getElementById(id);
                if (el) el.textContent = value ?? "0";
            };

            const renderOralData = (d) => {
                if (!d) return;

                // Section A
                [
                    "orally_fit_child", "dental_caries", "gingivitis", "periodontal_disease",
                    "debris", "calculus", "abnormal_growth", "cleft_palate", "others"
                ].forEach(key => setCondition(key, d[key]));

                // Section B
                [
                    "perm_total_dmf", "perm_teeth_present", "perm_sound_teeth",
                    "perm_decayed_teeth_d", "perm_missing_teeth_m", "perm_filled_teeth_f",
                    "temp_total_df", "temp_teeth_present", "temp_sound_teeth",
                    "temp_decayed_teeth_d", "temp_filled_teeth_f"
                ].forEach(key => setValue(key, d[key]));
            };

            const loadRecordById = async (recordId) => {
                try {
                    const res = await fetch(`${baseAPI}?record=${encodeURIComponent(recordId)}`);
                    const data = await res.json();

                    if (!data || data.error || !data.id) {
                        console.warn("Record not found for selected date.");
                        return;
                    }
                    renderOralData(data);
                } catch (err) {
                    console.error("Error loading specific record:", err);
                }
            };

            // --- Load all available records for this patient ---
            const loadAllPatientData = async () => {
                try {
                    const res = await fetch(`${baseAPI}?id=${encodeURIComponent(patientId)}`);
                    const data = await res.json();

                    if (!data || data.message) {
                        nameEl.textContent = data?.patient_name ?? "Unknown Patient";
                        if (data?.message) alert(data.message);
                        return;
                    }

                    if (!Array.isArray(data) || data.length === 0) {
                        nameEl.textContent = "Unknown Patient";
                        return;
                    }

                    const patientName = data[0].patient_name || "Unknown Patient";
                    nameEl.textContent = patientName;

                    // Populate dropdown with dates
                    dateSelect.innerHTML = "";
                    data.forEach((rec, i) => {
                        const opt = document.createElement("option");
                        opt.value = rec.id;
                        opt.textContent = new Date(rec.created_at).toLocaleDateString();
                        if (i === 0) opt.selected = true;
                        dateSelect.appendChild(opt);
                    });

                    // Render first record
                    renderOralData(data[0]);

                    // Listen for date change → fetch from backend
                    dateSelect.addEventListener("change", (e) => {
                        const selectedId = e.target.value;
                        if (selectedId) loadRecordById(selectedId);
                    });
                } catch (err) {
                    console.error("Error loading patient data:", err);
                    nameEl.textContent = "Unknown Patient";
                }
            };

            // --- Initialize ---
            loadAllPatientData();
        })();
    </script>

    <script>
        // ✅ Function to open the OHC Modal
        function openOHCModal() {
            const modal = document.getElementById('ohcModal');
            modal.classList.remove('hidden');
            modal.classList.add('flex');

            // ✅ Get patient_id from URL (accept both ?patient_id= or ?id=)
            const urlParams = new URLSearchParams(window.location.search);
            const patientId = urlParams.get('patient_id') || urlParams.get('id');

            const input = document.querySelector('#ohcForm #patient_id');
            if (input && patientId) {
                input.value = patientId;
                console.log("✅ Patient ID set to:", patientId);
            } else {
                console.warn("⚠️ Patient ID not found in URL");
            }
        }

        // ✅ Function to close the OHC Modal
        function closeOHCModal() {
            const modal = document.getElementById('ohcModal');
            if (!modal) return;

            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }

        // ✅ Close modal when clicking outside the modal content
        window.addEventListener('click', function (e) {
            const modal = document.getElementById('ohcModal');
            if (!modal) return;

            const content = modal.querySelector('.bg-white, .dark\\:bg-gray-800');
            if (e.target === modal) {
                closeOHCModal();
            }
        });
    </script>

    <script>
        function getValue(id) {
            const form = document.getElementById("ohcForm");
            const el = form.querySelector(`#${id}`);
            if (!el) return "";

            if (el.type === "checkbox" || el.type === "radio") {
                return el.checked ? "✓" : "✗";
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

            if (!patient_id) {
                alert("⚠️ No patient selected.");
                return;
            }

            const payload = {
                patient_id,
                orally_fit_child: getValue("orally_fit_child"),
                dental_caries: getValue("dental_caries"),
                gingivitis: getValue("gingivitis"),
                periodontal_disease: getValue("periodontal_disease"),
                debris: getValue("debris"),
                calculus: getValue("calculus"),
                abnormal_growth: getValue("abnormal_growth"),
                cleft_palate: getValue("cleft_palate"),
                others: getValue("others"),
                perm_teeth_present: +getValue("perm_teeth_present") || 0,
                perm_sound_teeth: +getValue("perm_sound_teeth") || 0,
                perm_decayed_teeth_d: +getValue("perm_decayed_teeth_d") || 0,
                perm_missing_teeth_m: +getValue("perm_missing_teeth_m") || 0,
                perm_filled_teeth_f: +getValue("perm_filled_teeth_f") || 0,
                perm_total_dmf: +getValue("perm_total_dmf") || 0,
                temp_teeth_present: +getValue("temp_teeth_present") || 0,
                temp_sound_teeth: +getValue("temp_sound_teeth") || 0,
                temp_decayed_teeth_d: +getValue("temp_decayed_teeth_d") || 0,
                temp_filled_teeth_f: +getValue("temp_filled_teeth_f") || 0,
                temp_total_df: +getValue("temp_total_df") || 0,
            };

            console.log("📦 Payload to send:", payload);

            try {
                const response = await fetch(
                    "/dentalemr_system/php/treatmentrecords/save_ohc.php",
                    {
                        method: "POST",
                        headers: { "Content-Type": "application/json" },
                        body: JSON.stringify(payload),
                    }
                );

                const text = await response.text();
                console.log("🌐 Server response:", text);

                const result = JSON.parse(text);
                if (result.success) {
                    alert(result.message);
                    closeOHCModal();
                    location.reload();
                } else {
                    alert("❌ " + (result.message || "Error saving data."));
                }
            } catch (err) {
                console.error("❌ Error:", err);
                alert("❌ Failed to save data.");
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
        });
    </script>




</body>

</html>