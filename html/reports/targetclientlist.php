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
    <title>Target Client List</title>
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
                        <ul id="dropdown-pages" class="hidden py-2 space-y-2">
                            <li>
                                <a href="../treatmentrecords/treatmentrecords.php"
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
                        <a href="#" style="color: blue;"
                            class="flex items-center p-2 text-base font-medium text-gray-900 rounded-lg dark:text-white hover:bg-gray-100 dark:hover:bg-gray-700 group">
                            <svg aria-hidden="true"
                                class="flex-shrink-0 w-6 h-6 text-blue-500 transition duration-75 dark:text-gray-400 group-hover:text-gray-900 dark:group-hover:text-white"
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
                        <a href="./mho_ohp.php"
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
                        <a href="./oralhygienefindings.php"
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
        <!-- Individual Patient Treatment Record Inforamtion -->
        <main class="p-3 md:ml-64 h-auto pt-15" id="patienttreatment" style="display: flex; flex-direction: column;">
            <div class="text-center">
                <p class="text-lg font-semibold  text-gray-900 dark:text-white">Target Client List For
                </p>
                <p class="text-lg font-semibold  text-gray-900 dark:text-white" style="margin-top:  -5px;;">Oral Health
                    Care And Services
                </p>
            </div>

            <section class="bg-white dark:bg-gray-900 p-3 rounded-lg mb-3 mt-3">
                <div class="w-full justify-between flex flex-row p-1">
                    <div class="flex items-center space-x-3 w-full md:w-auto">
                        <form class="w-80 items-center">
                            <div class="relative ">
                                <div class="absolute inset-y-0 start-0 flex items-center ps-3 pointer-events-none">
                                    <svg class="w-3 h-3 text-gray-500 dark:text-gray-400" aria-hidden="true"
                                        xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 20 20">
                                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"
                                            stroke-width="2" d="m19 19-4-4m0-7A7 7 0 1 1 1 8a7 7 0 0 1 14 0Z" />
                                    </svg>
                                </div>
                                <input type="search" id="default-search"
                                    class="block w-full p-2 ps-10 text-sm text-gray-900 border border-gray-300 rounded-lg bg-gray-50 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500"
                                    placeholder="Search patient" required />
                                <button type="submit"
                                    class="text-white absolute end-1.5 bottom-1.5 bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:outline-none focus:ring-blue-300 font-medium rounded-lg text-xs px-2 py-1 dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800">Search</button>
                            </div>
                        </form>
                    </div>
                    <div class="items-center flex flex-col gap-0 ">
                        <label for="name" class="flex mb-2 text-sm font-medium text-gray-900 dark:text-white">Part
                            1/2</label>
                        <div class="flex flex-row items-center gap-1">
                            <div class="rounded-full bg-gray-600 border border-gray-500 h-5 w-5"></div>
                            <div class="rounded-full  bg-gray-200 border border-gray-500 h-5 w-5"></div>
                        </div>
                    </div>
                    <div class="flex flex-row justify-between ">
                        <div class="flex items-center space-x-3 w-full md:w-auto">
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
                                class="z-10 hidden w-48 p-3 bg-white rounded-lg shadow dark:bg-gray-700">
                                <h6 class="mb-3 text-sm font-medium text-gray-900 dark:text-white">Choose
                                    address
                                </h6>
                                <ul class="space-y-2 text-sm" aria-labelledby="filterDropdownButton">
                                    <li class="flex items-center">
                                        <input id="apple" type="checkbox" value=""
                                            class="w-4 h-4 bg-gray-100 border-gray-300 rounded text-primary-600  dark:bg-gray-600 dark:border-gray-500">
                                        <label for="apple"
                                            class="ml-2 text-sm font-medium text-gray-900 dark:text-gray-100">Balansay</label>
                                    </li>
                                </ul>
                            </div>
                        </div>
                        <div class="flex items-center space-x-3 w-full md:w-auto">
                            <button type="button" class="flex items-center justify-center cursor-pointer text-white bg-blue-700
                                    hover:bg-blue-800 font-medium rounded-lg gap-1 text-sm px-4 py-2 dark:bg-blue-600
                                    dark:hover:bg-blue-700">
                                <svg class="w-6 h-6 text-gray-800 dark:text-white" aria-hidden="true"
                                    xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none"
                                    viewBox="0 0 24 24">
                                    <path stroke="white" stroke-linejoin="round" stroke-width="2"
                                        d="M16.444 18H19a1 1 0 0 0 1-1v-5a1 1 0 0 0-1-1H5a1 1 0 0 0-1 1v5a1 1 0 0 0 1 1h2.556M17 11V5a1 1 0 0 0-1-1H8a1 1 0 0 0-1 1v6h10ZM7 15h10v4a1 1 0 0 1-1 1H8a1 1 0 0 1-1-1v-4Z" />
                                </svg>
                                Generate report
                            </button>
                        </div>
                    </div>

                </div>

                <form action="#">
                    <div class="grid gap-2 mb-4 mt-5">
                        <div class="overflow-x-auto ">
                            <table
                                class="min-w-[1600px] w-full text-xs text-gray-600 dark:text-gray-300 border border-gray-300 border-collapse">
                                <!-- Column widths -->
                                <colgroup>
                                    <col style="width: 50px;"> <!-- No -->
                                    <col style="width: 120px;"> <!-- Date of Consultation -->
                                    <col style="width: 100px;"> <!-- Family Serial No -->
                                    <col style="width: 150px;"> <!-- Name of Client -->
                                    <col style="width: 40px;"> <!-- Sex M -->
                                    <col style="width: 40px;"> <!-- Sex F -->
                                    <col style="width: 180px;"> <!-- Complete Address -->
                                    <col style="width: 100px;"> <!-- Date of Birth -->

                                    <!-- Age / Risk Group -->
                                    <col style="width: 90px;">
                                    <col style="width: 100px;">
                                    <col style="width: 90px;">
                                    <col style="width: 100px;">
                                    <col style="width: 100px;">
                                    <col style="width: 100px;">
                                    <col style="width: 100px;">
                                    <col style="width: 100px;">
                                    <col style="width: 100px;">
                                    <col style="width: 100px;">

                                    <col style="width: 100px;"> <!-- Indigenous People -->
                                    <col style="width: 100px;"> <!-- Oral Health -->
                                    <col style="width: 100px;"> <!-- Oral Health -->
                                    <col style="width: 90px;"> <!-- DMFT -->
                                    <col style="width: 90px;"> <!-- DMFT -->
                                    <col style="width: 90px;"> <!-- DMFT -->
                                </colgroup>

                                <thead
                                    class="text-xs align-top  text-gray-700 bg-gray-50 dark:bg-gray-700 dark:text-gray-300">
                                    <!-- Row 1 -->
                                    <tr class="h-[20px] leading-[1.2]">
                                        <th rowspan="3" class="whitespace-nowrap border border-gray-300 px-1 py-2">No.
                                        </th>
                                        <th rowspan="3" class="whitespace-nowrap border border-gray-300 px-1 py-2">
                                            Date<br>of<br>Consultation<br><br>
                                            <span class="text-[10px] font-normal">(mm/dd/yy)</span><br><br><br>
                                            <span class="font-bold text-[11px]">(1)</span>
                                        </th>
                                        <th rowspan="3" class="whitespace-nowrap border border-gray-300 px-1 py-2">
                                            Family<br>Serial<br>No.<br><br><br><br><br>
                                            <span class="font-bold text-[11px]">(2)</span>
                                        </th>
                                        <th rowspan="3" class="whitespace-nowrap border border-gray-300 px-1 py-2">
                                            Name of Client <br>
                                            <span class="text-[10px]">(LN, FN, MI)</span><br><br><br><br><br><br>
                                            <span class="font-bold text-[11px]">(3)</span>
                                        </th>
                                        <!-- Sex -->
                                        <th colspan="2" class="border border-gray-300 px-1 py-0">Sex</th>
                                        <th rowspan="3" class="whitespace-nowrap border border-gray-300 px-1 py-2">
                                            Complete Address <br><br><br><br><br><br><br>
                                            <span class="font-bold text-[11px]">(4)</span>
                                        </th>
                                        <th rowspan="3" class="whitespace-nowrap border border-gray-300 px-1 py-2">
                                            Date<br>of<br>Birth <br><br>
                                            <span class="text-[10px]">(mm/dd/yy)</span><br><br><br>
                                            <span class="font-bold text-[11px]">(5)</span>
                                        </th>
                                        <!-- Age / Risk Group -->
                                        <th colspan="10" class="whitespace-nowrap border border-gray-300 px-1 py-2">
                                            Age / Risk Group <br>
                                            <span class="text-[10px]">(6) Write the age under the appropriate
                                                sub-column</span>
                                        </th>
                                        <th rowspan="3" class="whitespace-nowrap border border-gray-300 px-1 py-2">
                                            Indigenous People <br>
                                            <span class="font-bold text-[11px]">(7)<br>(Place a ✓)</span>
                                        </th>
                                        <th colspan="2" class="border border-gray-300 px-1 py-2">
                                            Oral Health Status<br>for Children<br>
                                            <span class="font-bold text-[10px]">12–59 months old</span><br>
                                            <span class="text-[9px]">(8) (Input the date)</span>
                                        </th>
                                        <th colspan="3" class="border border-gray-300 px-1 py-2">
                                            Clients &gt; 5 y/o with Decayed<br>Missing Filled Teeth (DMFT)<br>
                                            <span class="font-bold text-[11px]">(9) (Place a ✓)</span>
                                        </th>
                                    </tr>

                                    <!-- Row 2 -->
                                    <tr class="h-[20px] leading-[1.2]">
                                        <!-- Sex -->
                                        <th class="border border-gray-300 px-1 py-2 border-b-0">M</th>
                                        <th class="border border-gray-300 px-1 py-2 border-b-0">F</th>
                                        <!-- Age / Risk Group -->
                                        <th class="border border-gray-300 px-1 py-2 border-b-0 text-[10px]">0–11 mos.
                                        </th>
                                        <th class="border border-gray-300 px-1 py-2 border-b-0 text-[10px]">1–4 y/o
                                            (12–59 mos.)</th>
                                        <th class="border border-gray-300 px-1 py-2 border-b-0 text-[10px]">5–9 y/o</th>
                                        <th class="border border-gray-300 px-1 py-2 border-b-0 text-[10px]">10–14 y/o
                                        </th>
                                        <th class="border border-gray-300 px-1 py-2 border-b-0 text-[10px]">15–19 y/o
                                        </th>
                                        <th class="border border-gray-300 px-1 py-2 border-b-0 text-[10px]">20–59 y/o
                                        </th>
                                        <th class="border border-gray-300 px-1 py-2 border-b-0 text-[10px]">&gt;= 60 y/o
                                        </th>
                                        <th colspan="3" class="border border-gray-300 px-1 py-2">Pregnant</th>
                                        <!-- Oral Health -->
                                        <th class="border border-gray-300 px-1 py-2 border-b-0 text-[10px]">Orally
                                            Fit<br>Upon<br>Examination</th>
                                        <th class="border border-gray-300 px-1 py-2 border-b-0 text-[10px]">Orally
                                            Fit<br>After Rehab</th>
                                        <!-- DMFT -->
                                        <th class="border border-gray-300 px-1 py-2 border-b-0 text-[10px]">Decayed
                                            Tooth</th>
                                        <th class="border border-gray-300 px-1 py-2 border-b-0 text-[10px]">Missing
                                            Tooth</th>
                                        <th class="border border-gray-300 px-1 py-2 border-b-0 text-[10px]">Filled Tooth
                                        </th>
                                    </tr>

                                    <!-- Row 3 (Pregnant subcolumns) -->
                                    <tr class="h-[20px] leading-[1]">
                                        <th class="border border-gray-300 px-1 py-0 border-t-0"></th>
                                        <th class="border border-gray-300 px-1 py-0 border-t-0"></th>
                                        <th class="border border-gray-300 px-1 py-0 border-t-0"></th>
                                        <th class="border border-gray-300 px-1 py-0 border-t-0"></th>
                                        <th class="border border-gray-300 px-1 py-0 border-t-0"></th>
                                        <th class="border border-gray-300 px-1 py-0 border-t-0"></th>
                                        <th class="border border-gray-300 px-1 py-0 border-t-0"></th>
                                        <th class="border border-gray-300 px-1 py-0 border-t-0"></th>
                                        <th class="border border-gray-300 px-1 py-0 border-t-0"></th>
                                        <th class="border border-gray-300 px-1 py-0  text-[10px]">10–14 y/o</th>
                                        <th class="border border-gray-300 px-1 py-0  text-[10px]">15–19 y/o</th>
                                        <th class="border border-gray-300 px-1 py-0  text-[10px]">20–49 y/o</th>
                                        <th class="border border-gray-300 px-1 py-0  border-t-0"></th>
                                        <th class="border border-gray-300 px-1 py-0  border-t-0"></th>
                                        <th class="border border-gray-300 px-1 py-0  border-t-0"></th>
                                        <th class="border border-gray-300 px-1 py-0  border-t-0"></th>
                                        <th class="border border-gray-300 px-1 py-0  border-t-0"></th>
                                        <th colspan="5" class="border-none p-0"></th>
                                    </tr>
                                </thead>

                                <tbody>
                                    <tr class="h-[20px] leading-[1]  text-center">
                                        <td class="border border-gray-300 px-1 py-2"></td>
                                        <td class="border border-gray-300 px-1 py-2"></td>
                                        <td class="border border-gray-300 px-1 py-2"></td>
                                        <td class="border border-gray-300 px-1 py-2"></td>
                                        <td class="border border-gray-300 px-1 py-2"></td>
                                        <td class="border border-gray-300 px-1 py-2"></td>
                                        <td class="border border-gray-300 px-1 py-2"></td>
                                        <td class="border border-gray-300 px-1 py-2"></td>
                                        <td class="border border-gray-300 px-1 py-2"></td>
                                        <td class="border border-gray-300 px-1 py-2"></td>
                                        <td class="border border-gray-300 px-1 py-2"></td>
                                        <td class="border border-gray-300 px-1 py-2"></td>
                                        <td class="border border-gray-300 px-1 py-2"></td>
                                        <td class="border border-gray-300 px-1 py-2"></td>
                                        <td class="border border-gray-300 px-1 py-2"></td>
                                        <td class="border border-gray-300 px-1 py-2"></td>
                                        <td class="border border-gray-300 px-1 py-2"></td>
                                        <td class="border border-gray-300 px-1 py-2"></td>
                                        <td class="border border-gray-300 px-1 py-2"></td>
                                        <td class="border border-gray-300 px-1 py-2"></td>
                                        <td class="border border-gray-300 px-1 py-2"></td>
                                        <td class="border border-gray-300 px-1 py-2"></td>
                                        <td class="border border-gray-300 px-1 py-2"></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </form>
            </section>
            <div class="flex justify-end">
                <button type="button" onclick="next()"
                    class="text-white justify-center  cursor-pointer inline-flex items-center bg-blue-700 hover:bg-blue-800  focus:outline-none focus:ring-blue-300 font-medium rounded-lg text-sm p-1 w-18 dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800">
                    Next
                </button>
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
        function next(){
            location.href=("targetclientlist2.html");
        }
    </script>
</body>

</html>