<?php
session_start();
date_default_timezone_set('Asia/Manila');

// REQUIRE userId parameter for each page
// Example usage: dashboard.php?uid=5
if (!isset($_GET['uid'])) {
    header('Location: /dentalemr_system/html/login/login.html?error=invalid_session');
    exit;
}

$userId = intval($_GET['uid']);

// CHECK IF THIS USER IS REALLY LOGGED IN
if (
    !isset($_SESSION['active_sessions']) ||
    !isset($_SESSION['active_sessions'][$userId])
) {
    header('Location: /dentalemr_system/html/login/login.html?error=session_expired');
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

        header('Location: /dentalemr_system/html/login/login.html?error=inactivity');
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
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <meta name="theme-color" content="#3b82f6">
    <title>MHO Dental Clinic - System Logs</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Custom Variables */
        :root {
            --primary-color: #3b82f6;
            --secondary-color: #10b981;
            --danger-color: #ef4444;
            --warning-color: #f59e0b;
            --dark-bg: #111827;
            --light-bg: #f9fafb;
        }

        /* Improved Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }

        ::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 10px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #a8a8a8;
        }

        /* Smooth Transitions */
        * {
            transition: background-color 0.2s ease, border-color 0.2s ease, transform 0.2s ease;
        }

        /* Card Hover Effects */
        .hover-lift:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
        }

        /* Table Responsive */
        @media (max-width: 768px) {
            .table-responsive {
                display: block;
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }

            .mobile-hidden {
                display: none;
            }

            .mobile-show {
                display: table-cell;
            }
        }

        /* Loading Animation */
        @keyframes pulse {

            0%,
            100% {
                opacity: 1;
            }

            50% {
                opacity: 0.5;
            }
        }

        .animate-pulse {
            animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }

        /* Status Indicators */
        .status-online {
            background-color: #10b981;
            box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.2);
        }

        .status-offline {
            background-color: #6b7280;
            box-shadow: 0 0 0 2px rgba(107, 114, 128, 0.2);
        }

        /* Badge Styles */
        .badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            line-height: 1;
        }

        .badge-activity {
            background-color: rgba(59, 130, 246, 0.1);
            color: #3b82f6;
            border: 1px solid rgba(59, 130, 246, 0.2);
        }

        .badge-history {
            background-color: rgba(16, 185, 129, 0.1);
            color: #10b981;
            border: 1px solid rgba(16, 185, 129, 0.2);
        }

        /* Modal Backdrop */
        .modal-backdrop {
            backdrop-filter: blur(4px);
            background-color: rgba(0, 0, 0, 0.5);
        }

        /* Tab Navigation */
        .tab-active {
            position: relative;
            color: var(--primary-color);
        }

        .tab-active::after {
            content: '';
            position: absolute;
            bottom: -1px;
            left: 0;
            right: 0;
            height: 2px;
            background-color: var(--primary-color);
        }

        /* Print Styles */
        @media print {
            .no-print {
                display: none !important;
            }

            body {
                background: white !important;
                color: black !important;
            }

            table {
                break-inside: avoid;
            }
        }

        #pageNumbers .px-3 {
            min-width: 2.5rem;
            text-align: center;
        }

        /* Ensure pagination buttons have consistent spacing */
        #pagination nav>* {
            margin: 0 2px;
        }
    </style>
</head>

<body class="bg-gray-50 dark:bg-gray-900">
    <div class="antialiased bg-gray-50 dark:bg-gray-900">
        <!-- Connection Status Indicator -->
        <div id="connectionStatus" class="fixed top-4 right-4 z-50 hidden">
            <div class="flex items-center gap-2 px-4 py-2 rounded-lg shadow-lg bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
                <div class="w-2 h-2 rounded-full status-online"></div>
                <span class="text-sm font-medium">Online</span>
            </div>
        </div>

        <!-- Navigation -->
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
                <!-- User Profile -->
                <div class="flex items-center space-x-3">

                    <!-- User Dropdown -->
                    <div class="relative">
                        <button type="button" id="userDropdownButton" class="flex items-center space-x-2 p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700">
                            <div class="w-8 h-8 rounded-full bg-gray-200 dark:bg-gray-700 flex items-center justify-center overflow-hidden">
                                <?php if (!empty($loggedUser['photo'])): ?>
                                    <img src="<?php echo htmlspecialchars($loggedUser['photo']); ?>" alt="User" class="w-full h-full object-cover">
                                <?php else: ?>
                                    <i class="fas fa-user text-gray-600 dark:text-gray-400"></i>
                                <?php endif; ?>
                            </div>
                            <div class="hidden md:block text-left">
                                <div class="text-sm font-medium truncate max-w-[150px]">
                                    <?php echo htmlspecialchars($loggedUser['name'] ?? 'User'); ?>
                                </div>
                                <div class="text-xs text-gray-500 dark:text-gray-400 truncate max-w-[150px]">
                                    <?php echo htmlspecialchars($loggedUser['type'] ?? 'User Type'); ?>
                                </div>
                            </div>
                            <i class="fas fa-chevron-down text-xs text-gray-500"></i>
                        </button>

                        <!-- Dropdown Menu -->
                        <div id="userDropdown" class="absolute right-0 mt-2 w-64 bg-white dark:bg-gray-800 rounded-lg shadow-lg border border-gray-200 dark:border-gray-700 hidden">
                            <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                                <div class="text-sm font-semibold">
                                    <?php echo htmlspecialchars($loggedUser['name'] ?? 'User'); ?>
                                </div>
                                <div class="text-xs text-gray-600 dark:text-gray-400 mt-1">
                                    <?php echo htmlspecialchars($loggedUser['email'] ?? 'user@example.com'); ?>
                                </div>
                            </div>
                            <div class="py-2">
                                <a href="/dentalemr_system/html/profile.php?uid=<?php echo $userId; ?>"
                                    class="flex items-center px-4 py-2 text-sm hover:bg-gray-100 dark:hover:bg-gray-700">
                                    <i class="fas fa-user-circle mr-3 text-gray-500"></i>
                                    My Profile
                                </a>
                                <a href="/dentalemr_system/html/manageusers/manageuser.php?uid=<?php echo $userId; ?>"
                                    class="flex items-center px-4 py-2 text-sm hover:bg-gray-100 dark:hover:bg-gray-700">
                                    <i class="fas fa-users-cog mr-3 text-gray-500"></i>
                                    Manage Users
                                </a>
                                <a href="/dentalemr_system/html/manageusers/systemlogs.php?uid=<?php echo $userId; ?>"
                                    class="flex items-center px-4 py-2 text-sm hover:bg-gray-100 dark:hover:bg-gray-700 bg-blue-50 dark:bg-blue-900/20">
                                    <i class="fas fa-history mr-3 text-blue-500"></i>
                                    System Logs
                                </a>
                            </div>
                            <div class="border-t border-gray-200 dark:border-gray-700 py-2">
                                <a href="/dentalemr_system/php/login/logout.php?uid=<?php echo $loggedUser['id']; ?>"
                                    class="flex items-center px-4 py-2 text-sm text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20">
                                    <i class="fas fa-sign-out-alt mr-3"></i>
                                    Sign Out
                                </a>
                            </div>
                        </div>
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

        <!-- Main Content -->
        <main class="p-4 md:ml-64 h-auto pt-20">
            <!-- Page Header -->
            <div class="bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 px-4 py-6">
                <div class="max-w-7xl mx-auto">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                        <div>
                            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">System Logs</h1>
                            <p class="text-gray-600 dark:text-gray-400 mt-1">Monitor and manage all system activities</p>
                        </div>
                        <div class="flex items-center space-x-2">
                            <div class="text-sm text-gray-500 dark:text-gray-400">
                                <i class="fas fa-clock mr-1"></i>
                                Last updated: <span id="lastUpdated">Just now</span>
                            </div>
                            <button onclick="refreshLogs()" class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700" title="Refresh">
                                <i class="fas fa-sync-alt"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters and Actions -->
            <div class="bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 px-4 py-4">
                <div class="max-w-7xl mx-auto">
                    <div class="flex flex-col lg:flex-row gap-4">
                        <!-- Search -->
                        <div class="flex-1">
                            <div class="relative">
                                <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                                <input type="text"
                                    id="searchInput"
                                    placeholder="Search logs by user, action, or details..."
                                    class="w-full pl-10 pr-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                    oninput="debouncedSearch()">
                                <button onclick="clearSearch()" class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-600">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Filter Buttons -->
                        <div class="flex flex-wrap gap-2">
                            <button onclick="filterLogs('all')" id="filterAll" class="px-4 py-2.5 rounded-lg bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 font-medium">
                                All Logs
                            </button>
                            <button onclick="filterLogs('activity')" id="filterActivity" class="px-4 py-2.5 rounded-lg bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600">
                                Activities
                            </button>
                            <button onclick="filterLogs('history')" id="filterHistory" class="px-4 py-2.5 rounded-lg bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600">
                                History
                            </button>
                        </div>
                    </div>

                    <!-- Advanced Filters (Collapsible) -->
                    <div class="mt-4 hidden" id="advancedFilters">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 p-4 bg-gray-50 dark:bg-gray-900 rounded-lg">
                            <div>
                                <label class="block text-sm font-medium mb-2">Date Range</label>
                                <div class="flex gap-2">
                                    <input type="date" id="dateFrom" class="flex-1 p-2 border rounded-lg">
                                    <input type="date" id="dateTo" class="flex-1 p-2 border rounded-lg">
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium mb-2">User Type</label>
                                <select id="userTypeFilter" class="w-full p-2 border rounded-lg">
                                    <option value="">All Users</option>
                                    <option value="Dentist">Dentist</option>
                                    <option value="Staff">Staff</option>
                                    <option value="Admin">Admin</option>
                                </select>
                            </div>
                            <div class="flex items-end">
                                <button onclick="applyAdvancedFilters()" class="w-full px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                                    Apply Filters
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Toggle Advanced Filters -->
                    <div class="mt-3 text-center">
                        <button onclick="toggleAdvancedFilters()" class="text-sm text-blue-600 dark:text-blue-400 hover:underline">
                            <i class="fas fa-filter mr-1"></i>
                            Advanced Filters
                        </button>
                    </div>
                </div>
            </div>

            <!-- Main Content Area -->
            <div class="p-4">
                <div class="max-w-7xl mx-auto">
                    <!-- Stats Cards -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                            <div class="flex items-center">
                                <div class="p-3 rounded-lg bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400">
                                    <i class="fas fa-history text-lg"></i>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm text-gray-600 dark:text-gray-400">Total Logs</p>
                                    <p class="text-2xl font-semibold" id="totalLogs">0</p>
                                </div>
                            </div>
                        </div>
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                            <div class="flex items-center">
                                <div class="p-3 rounded-lg bg-green-100 dark:bg-green-900/30 text-green-600 dark:text-green-400">
                                    <i class="fas fa-user-clock text-lg"></i>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm text-gray-600 dark:text-gray-400">Activities Today</p>
                                    <p class="text-2xl font-semibold" id="todayLogs">0</p>
                                </div>
                            </div>
                        </div>
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                            <div class="flex items-center">
                                <div class="p-3 rounded-lg bg-orange-100 dark:bg-orange-900/30 text-orange-600 dark:text-orange-400">
                                    <i class="fas fa-users text-lg"></i>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm text-gray-600 dark:text-gray-400">Active Users</p>
                                    <p class="text-2xl font-semibold" id="activeUsers">0</p>
                                </div>
                            </div>
                        </div>
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                            <div class="flex items-center">
                                <div class="p-3 rounded-lg bg-purple-100 dark:bg-purple-900/30 text-purple-600 dark:text-purple-400">
                                    <i class="fas fa-database text-lg"></i>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm text-gray-600 dark:text-gray-400">Database Size</p>
                                    <p class="text-2xl font-semibold" id="dbSize">0 MB</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Bulk Actions Bar -->
                    <div id="bulkActionsBar" class="hidden bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-3 mb-4">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                            <div class="flex items-center gap-3">
                                <div class="flex items-center">
                                    <input type="checkbox" id="selectAllCheckbox" onchange="toggleSelectAll(this)" class="w-4 h-4 text-blue-600 rounded">
                                    <label for="selectAllCheckbox" class="ml-2 font-medium">
                                        <span id="selectedCount">0</span> logs selected
                                    </label>
                                </div>
                                <button onclick="clearSelection()" class="text-sm text-blue-600 dark:text-blue-400 hover:underline">
                                    Clear all
                                </button>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <button onclick="exportSelected()" class="px-3 py-1.5 bg-green-600 text-white rounded-lg hover:bg-green-700 text-sm">
                                    <i class="fas fa-download mr-1"></i>
                                    Export Selected
                                </button>
                                <button onclick="confirmBulkDelete()" class="px-3 py-1.5 bg-red-600 text-white rounded-lg hover:bg-red-700 text-sm">
                                    <i class="fas fa-trash mr-1"></i>
                                    Delete Selected
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Logs Table Container -->
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                        <!-- Table Header -->
                        <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center">
                            <h3 class="font-semibold">System Activities</h3>
                            <div class="flex items-center space-x-2">
                                <button onclick="systemLogs.showExportModal()" class="px-3 py-1.5 bg-gray-100 dark:bg-gray-700 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 text-sm">
                                    <i class="fas fa-file-export mr-1"></i>
                                    Export
                                </button>
                                <div class="relative">
                                    <select onchange="changeLimit(this.value)" class="bg-transparent border-none text-sm focus:ring-0">
                                        <option value="10">10 per page</option>
                                        <option value="25">25 per page</option>
                                        <option value="50">50 per page</option>
                                        <option value="100">100 per page</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Table (Responsive) -->
                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead class="bg-gray-50 dark:bg-gray-900">
                                    <tr>
                                        <th class="px-4 py-3 text-left w-12">
                                            <input type="checkbox" id="selectAllMain" onchange="toggleSelectAll(this)" class="w-4 h-4">
                                        </th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider">
                                            <button onclick="sortTable('type')" class="flex items-center">
                                                Type
                                                <i class="fas fa-sort ml-1"></i>
                                            </button>
                                        </th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider mobile-hidden">
                                            <button onclick="sortTable('user')" class="flex items-center">
                                                User
                                                <i class="fas fa-sort ml-1"></i>
                                            </button>
                                        </th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider">
                                            <button onclick="sortTable('action')" class="flex items-center">
                                                Action
                                                <i class="fas fa-sort ml-1"></i>
                                            </button>
                                        </th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider mobile-hidden">
                                            Details
                                        </th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider">
                                            <button onclick="sortTable('date')" class="flex items-center">
                                                Date
                                                <i class="fas fa-sort ml-1"></i>
                                            </button>
                                        </th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody id="logsBody" class="divide-y divide-gray-200 dark:divide-gray-700">
                                    <!-- Logs will be loaded here -->
                                    <tr>
                                        <td colspan="7" class="px-4 py-8 text-center">
                                            <div class="flex flex-col items-center justify-center">
                                                <i class="fas fa-spinner fa-spin text-2xl text-blue-500 mb-2"></i>
                                                <p class="text-gray-600 dark:text-gray-400">Loading system logs...</p>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Empty State -->
                        <div id="emptyState" class="hidden px-4 py-12 text-center">
                            <div class="max-w-sm mx-auto">
                                <i class="fas fa-clipboard-list text-4xl text-gray-400 mb-4"></i>
                                <h3 class="text-lg font-medium mb-2">No logs found</h3>
                                <p class="text-gray-600 dark:text-gray-400 mb-4">
                                    Try adjusting your search or filter to find what you're looking for.
                                </p>
                                <button onclick="clearFilters()" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                                    Clear all filters
                                </button>
                            </div>
                        </div>

                        <!-- Loading State -->
                        <div id="loadingState" class="hidden px-4 py-8 text-center">
                            <div class="flex items-center justify-center space-x-2">
                                <div class="w-4 h-4 bg-blue-500 rounded-full animate-pulse"></div>
                                <div class="w-4 h-4 bg-blue-500 rounded-full animate-pulse delay-150"></div>
                                <div class="w-4 h-4 bg-blue-500 rounded-full animate-pulse delay-300"></div>
                            </div>
                            <p class="mt-3 text-gray-600 dark:text-gray-400">Loading logs...</p>
                        </div>

                        <!-- Pagination -->
                        <div id="pagination" class="px-4 py-3 border-t border-gray-200 dark:border-gray-700 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                            <div class="text-sm text-gray-700 dark:text-gray-400">
                                Showing <span id="showingFrom">0</span> to <span id="showingTo">0</span> of <span id="totalItems">0</span> entries
                            </div>
                            <nav class="flex items-center space-x-1">
                                <button onclick="window.changePage(systemLogs?.currentPage - 1 || 1)" id="prevPage" disabled class="px-3 py-1.5 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 disabled:opacity-50 disabled:cursor-not-allowed">
                                    <i class="fas fa-chevron-left"></i>
                                </button>
                                <div id="pageNumbers" class="flex items-center space-x-1">
                                    <!-- Page numbers will be inserted here -->
                                </div>
                                <button onclick="window.changePage(systemLogs?.currentPage + 1 || 1)" id="nextPage" disabled class="px-3 py-1.5 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 disabled:opacity-50 disabled:cursor-not-allowed">
                                    <i class="fas fa-chevron-right"></i>
                                </button>
                            </nav>
                        </div>
                    </div>

                    <!-- Quick Stats -->
                    <div class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="bg-white dark:bg-gray-800 rounded-lg p-4 border border-gray-200 dark:border-gray-700">
                            <h4 class="font-medium mb-3">Most Active Users</h4>
                            <div id="topUsers" class="space-y-2">
                                <!-- Will be populated by JavaScript -->
                            </div>
                        </div>
                        <div class="bg-white dark:bg-gray-800 rounded-lg p-4 border border-gray-200 dark:border-gray-700">
                            <h4 class="font-medium mb-3">Recent Activity Types</h4>
                            <div id="activityTypes" class="space-y-2">
                                <!-- Will be populated by JavaScript -->
                            </div>
                        </div>
                        <div class="bg-white dark:bg-gray-800 rounded-lg p-4 border border-gray-200 dark:border-gray-700">
                            <h4 class="font-medium mb-3">System Health</h4>
                            <div class="space-y-3">
                                <div>
                                    <div class="flex justify-between text-sm mb-1">
                                        <span>Storage</span>
                                        <span>45%</span>
                                    </div>
                                    <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                                        <div class="bg-green-500 h-2 rounded-full" style="width: 45%"></div>
                                    </div>
                                </div>
                                <div>
                                    <div class="flex justify-between text-sm mb-1">
                                        <span>Performance</span>
                                        <span>92%</span>
                                    </div>
                                    <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                                        <div class="bg-blue-500 h-2 rounded-full" style="width: 92%"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>

        <!-- Modals (Keep your existing modal structure but with improved styling) -->
        <!-- View Details Modal -->
        <div id="detailsModal" class="modal-backdrop hidden fixed inset-0 z-50 overflow-y-auto">
            <div class="min-h-screen px-4 flex items-center justify-center">
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-4xl w-full max-h-[90vh] overflow-hidden">
                    <div class="p-6 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center">
                        <h3 class="text-xl font-semibold">Log Details</h3>
                        <button onclick="closeDetailsModal()" class="p-2 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <div class="p-6 overflow-y-auto max-h-[calc(90vh-80px)]">
                        <div id="modalContent"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Delete Confirmation Modal -->
        <div id="deleteModal" class="modal-backdrop hidden fixed inset-0 z-50">
            <div class="min-h-screen px-4 flex items-center justify-center">
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-md w-full">
                    <div class="p-6">
                        <div class="text-center">
                            <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100 dark:bg-red-900/30 mb-4">
                                <i class="fas fa-exclamation-triangle text-red-600 dark:text-red-400 text-xl"></i>
                            </div>
                            <h3 id="deleteModalTitle" class="text-lg font-medium mb-2">Delete Log</h3>
                            <p id="deleteModalMessage" class="text-gray-600 dark:text-gray-400 mb-6">
                                Are you sure you want to delete this log? This action cannot be undone.
                            </p>
                            <div class="flex justify-center space-x-3">
                                <button onclick="closeDeleteModal()" class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700">
                                    Cancel
                                </button>
                                <button onclick="confirmDelete()" id="confirmDeleteBtn" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700">
                                    Delete
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Export Modal -->
        <div id="exportModal" class="modal-backdrop hidden fixed inset-0 z-50">
            <div class="min-h-screen px-4 flex items-center justify-center">
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-md w-full">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold mb-4">Export Options</h3>
                        <div class="space-y-3 mb-4">
                            <label class="flex items-center p-3 border rounded-lg cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700">
                                <input type="radio" name="exportFormat" value="csv" checked class="mr-3">
                                <div>
                                    <div class="font-medium">CSV Format</div>
                                    <div class="text-sm text-gray-600 dark:text-gray-400">Best for Excel and spreadsheet programs</div>
                                </div>
                            </label>
                            <label class="flex items-center p-3 border rounded-lg cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700">
                                <input type="radio" name="exportFormat" value="json" class="mr-3">
                                <div>
                                    <div class="font-medium">JSON Format</div>
                                    <div class="text-sm text-gray-600 dark:text-gray-400">Best for developers and APIs</div>
                                </div>
                            </label>
                            <label class="flex items-center p-3 border rounded-lg cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700 opacity-50 cursor-not-allowed">
                                <input type="radio" name="exportFormat" value="pdf" disabled class="mr-3">
                                <div>
                                    <div class="font-medium">PDF Format</div>
                                    <div class="text-sm text-gray-600 dark:text-gray-400">Coming soon</div>
                                </div>
                            </label>
                        </div>
                        <div class="text-xs text-gray-500 mb-4">
                            <i class="fas fa-info-circle mr-1"></i>
                            PDF export requires additional setup. Currently available: CSV and JSON.
                        </div>
                        <div class="mt-6 flex justify-end space-x-3">
                            <button onclick="closeExportModal()" class="px-4 py-2 border rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700">
                                Cancel
                            </button>
                            <button onclick="proceedExport()" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                                Export
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Toast Notifications Container -->
        <div id="toastContainer" class="fixed bottom-4 right-4 z-50 space-y-2"></div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/flowbite@3.1.2/dist/flowbite.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <script>
        // Improved JavaScript with better organization and performance

        class SystemLogsManager {
            constructor() {
                this.currentPage = 1;
                this.limit = 10;
                this.totalPages = 1;
                this.totalItems = 0;
                this.searchTerm = '';
                this.currentFilter = 'all';
                this.sortField = 'created_at'; // Changed from 'date' to match database field
                this.sortOrder = 'desc';
                this.selectedLogs = new Set();
                this.allLogs = [];
                this.filteredLogs = [];
                this.searchTimeout = null;
                this.isLoading = false;
                this.logToDelete = null;

                // Advanced filter properties
                this.dateFrom = '';
                this.dateTo = '';
                this.userType = '';
            }

            init() {
                // Set initial active state for "All Logs" button
                this.updateFilterButtons();

                this.setupEventListeners();
                this.setupConnectionMonitoring();

                // Set default dates for date range filters
                this.setDefaultDateRange();

                // Load initial data with a small delay to ensure DOM is ready
                setTimeout(() => {
                    this.loadInitialData();
                }, 100);
            }

            setDefaultDateRange() {
                // Set dateFrom to 30 days ago
                const thirtyDaysAgo = new Date();
                thirtyDaysAgo.setDate(thirtyDaysAgo.getDate() - 30);

                // Set dateTo to today
                const today = new Date();

                // Format dates as YYYY-MM-DD for input fields
                const formatDate = (date) => {
                    return date.toISOString().split('T')[0];
                };

                // Set the input field values
                const dateFromInput = document.getElementById('dateFrom');
                const dateToInput = document.getElementById('dateTo');

                if (dateFromInput) {
                    dateFromInput.value = formatDate(thirtyDaysAgo);
                    this.dateFrom = formatDate(thirtyDaysAgo);
                }

                if (dateToInput) {
                    dateToInput.value = formatDate(today);
                    this.dateTo = formatDate(today);
                }
            }

            updateFilterButtons() {
                const allButton = document.getElementById('filterAll');
                if (allButton) {
                    allButton.classList.remove('bg-gray-100', 'dark:bg-gray-700', 'text-gray-700', 'dark:text-gray-300');
                    allButton.classList.add('bg-blue-100', 'dark:bg-blue-900/30', 'text-blue-700', 'dark:text-blue-300', 'font-medium');
                }
            }

            setupEventListeners() {
                // Mobile menu
                const mobileMenuButton = document.getElementById('mobileMenuButton');
                if (mobileMenuButton) {
                    mobileMenuButton.addEventListener('click', () => this.toggleMobileSidebar());
                }

                const userDropdownButton = document.getElementById('userDropdownButton');
                if (userDropdownButton) {
                    userDropdownButton.addEventListener('click', (e) => {
                        e.stopPropagation();
                        const dropdown = document.getElementById('userDropdown');
                        if (dropdown) dropdown.classList.toggle('hidden');
                    });
                }

                // Close dropdowns when clicking outside
                document.addEventListener('click', (e) => {
                    const dropdown = document.getElementById('userDropdown');
                    if (dropdown && !e.target.closest('#userDropdown') && !e.target.closest('#userDropdownButton')) {
                        dropdown.classList.add('hidden');
                    }
                });

                // Keyboard shortcuts
                document.addEventListener('keydown', (e) => {
                    if (e.ctrlKey && e.key === 'f') {
                        e.preventDefault();
                        const searchInput = document.getElementById('searchInput');
                        if (searchInput) searchInput.focus();
                    }
                    if (e.key === 'Escape') {
                        this.closeAllModals();
                    }
                });

                // Date range input listeners
                const dateFromInput = document.getElementById('dateFrom');
                const dateToInput = document.getElementById('dateTo');

                if (dateFromInput) {
                    dateFromInput.addEventListener('change', (e) => {
                        this.dateFrom = e.target.value;
                        // Validate date range
                        this.validateDateRange();
                    });
                }

                if (dateToInput) {
                    dateToInput.addEventListener('change', (e) => {
                        this.dateTo = e.target.value;
                        // Validate date range
                        this.validateDateRange();
                    });
                }

                // User type filter listener
                const userTypeFilter = document.getElementById('userTypeFilter');
                if (userTypeFilter) {
                    userTypeFilter.addEventListener('change', (e) => {
                        this.userType = e.target.value;
                    });
                }
            }

            validateDateRange() {
                const dateFromInput = document.getElementById('dateFrom');
                const dateToInput = document.getElementById('dateTo');

                if (this.dateFrom && this.dateTo && this.dateFrom > this.dateTo) {
                    this.showToast('End date cannot be earlier than start date', 'warning');
                    // Swap dates if they're in wrong order
                    [this.dateFrom, this.dateTo] = [this.dateTo, this.dateFrom];

                    if (dateFromInput) dateFromInput.value = this.dateFrom;
                    if (dateToInput) dateToInput.value = this.dateTo;
                }
            }


            setupConnectionMonitoring() {
                const updateConnectionStatus = () => {
                    const status = document.getElementById('connectionStatus');
                    if (!status) return;

                    const indicator = status.querySelector('.rounded-full');
                    const text = status.querySelector('span');

                    if (navigator.onLine) {
                        indicator.className = 'w-2 h-2 rounded-full status-online';
                        text.textContent = 'Online';
                        status.classList.remove('hidden');
                        setTimeout(() => status.classList.add('hidden'), 3000);
                    } else {
                        indicator.className = 'w-2 h-2 rounded-full status-offline';
                        text.textContent = 'Offline';
                        status.classList.remove('hidden');
                    }
                };

                window.addEventListener('online', updateConnectionStatus);
                window.addEventListener('offline', updateConnectionStatus);
                updateConnectionStatus();
            }

            setupServiceWorker() {
                if ('serviceWorker' in navigator) {
                    navigator.serviceWorker.register('/dentalemr_system/sw.js')
                        .then(reg => console.log('Service Worker registered'))
                        .catch(err => console.log('Service Worker registration failed:', err));
                }
            }

            async loadInitialData() {
                try {
                    // Load all data in sequence to avoid race conditions
                    await this.loadStats();
                    await this.loadQuickStats();
                    await this.loadLogs();
                    this.updateLastUpdated();
                } catch (error) {
                    console.error('Error loading initial data:', error);
                    // Try to load at least the logs
                    await this.loadLogs();
                }
            }

            async loadLogs() {
                if (this.isLoading) return;

                this.isLoading = true;
                this.showLoadingState();

                try {
                    // Get the user ID from the URL parameter
                    const urlParams = new URLSearchParams(window.location.search);
                    const userId = urlParams.get('uid');

                    if (!userId) {
                        console.error('User ID is missing from URL');
                        throw new Error('User ID is missing from URL');
                    }

                    const params = new URLSearchParams({
                        uid: userId,
                        page: this.currentPage,
                        limit: this.limit,
                        search: this.searchTerm,
                        filter: this.currentFilter,
                        sort: this.sortField,
                        order: this.sortOrder
                    });
                    // Add advanced filter parameters if they exist
                    if (this.dateFrom) {
                        params.append('date_from', this.dateFrom);
                    }

                    if (this.dateTo) {
                        params.append('date_to', this.dateTo);
                    }

                    if (this.userType) {
                        params.append('user_type', this.userType);
                    }
                    console.log('Fetching logs with params:', params.toString());

                    const response = await fetch(`/dentalemr_system/php/manageusers/fetch_system_logs.php?${params}`, {
                        cache: 'no-store', // Prevent caching
                        headers: {
                            'Cache-Control': 'no-cache'
                        }
                    });

                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }

                    const data = await response.json();
                    console.log('Server response:', data);

                    if (data.success) {
                        this.allLogs = data.logs || [];
                        this.totalItems = data.total || 0;
                        this.totalPages = Math.ceil(this.totalItems / this.limit);

                        // If we have data, render it
                        if (this.allLogs.length > 0) {
                            this.renderLogs();
                        } else {
                            this.showEmptyState();
                        }
                    } else {
                        console.error('Server returned error:', data);
                        this.showEmptyState();
                        throw new Error(data.message || 'Failed to load logs');
                    }
                } catch (error) {
                    console.error('Error loading logs:', error);
                    this.showEmptyState();

                    // Only show error toast if it's not the initial load
                    if (this.currentPage > 1 || this.searchTerm || this.currentFilter !== 'all') {
                        this.showError('Failed to load system logs. Please try again.');
                    }
                } finally {
                    this.isLoading = false;
                    this.hideLoadingState();
                }
            }
            applyAdvancedFilters() {
                // Get date range values
                const dateFromInput = document.getElementById('dateFrom');
                const dateToInput = document.getElementById('dateTo');
                const userTypeFilter = document.getElementById('userTypeFilter');

                if (dateFromInput) this.dateFrom = dateFromInput.value;
                if (dateToInput) this.dateTo = dateToInput.value;
                if (userTypeFilter) this.userType = userTypeFilter.value;

                // Validate date range
                this.validateDateRange();

                // Reset to first page and load logs
                this.currentPage = 1;
                this.loadLogs();

                // Show feedback
                const filterCount = [this.dateFrom, this.dateTo, this.userType].filter(Boolean).length;
                if (filterCount > 0) {
                    this.showToast(`Applied ${filterCount} filter(s)`, 'info');
                }
            }

            clearAdvancedFilters() {
                // Reset date range to default
                this.setDefaultDateRange();

                // Reset user type filter
                const userTypeFilter = document.getElementById('userTypeFilter');
                if (userTypeFilter) {
                    userTypeFilter.value = '';
                    this.userType = '';
                }

                // Apply the cleared filters
                this.applyAdvancedFilters();
            }

            toggleAdvancedFilters() {
                const filters = document.getElementById('advancedFilters');
                const toggleBtn = document.querySelector('button[onclick*="toggleAdvancedFilters"]');

                if (filters) {
                    filters.classList.toggle('hidden');

                    // Update button text
                    if (toggleBtn) {
                        const isHidden = filters.classList.contains('hidden');
                        const icon = isHidden ? '<i class="fas fa-filter mr-1"></i>' : '<i class="fas fa-times mr-1"></i>';
                        const text = isHidden ? 'Advanced Filters' : 'Close Filters';
                        toggleBtn.innerHTML = `${icon}${text}`;
                    }
                }
            }

            renderLogs() {
                const tbody = document.getElementById('logsBody');
                if (!tbody) {
                    console.error('Logs body element not found');
                    return;
                }

                if (this.allLogs.length === 0) {
                    this.showEmptyState();
                    return;
                }

                this.hideEmptyState();

                tbody.innerHTML = this.allLogs.map((log, index) => `
            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer transition-colors" 
                onclick="window.showLogDetails(${JSON.stringify(log).replace(/"/g, '&quot;')})">
                <td class="px-4 py-3" onclick="event.stopPropagation()">
                    <input type="checkbox" 
                           value="${log.id}"
                           onchange="window.toggleLogSelection(${log.id})"
                           ${this.selectedLogs.has(log.id) ? 'checked' : ''}
                           class="w-4 h-4 text-blue-600 rounded focus:ring-blue-500">
                </td>
                <td class="px-4 py-3">
                    <span class="badge ${log.type === 'activity' ? 'badge-activity' : 'badge-history'}">
                        ${log.type === 'activity' ? 'Activity' : 'History'}
                    </span>
                </td>
                <td class="px-4 py-3 mobile-hidden">
                    <div class="flex items-center">
                        <div class="w-8 h-8 rounded-full bg-gray-200 dark:bg-gray-700 flex items-center justify-center mr-3">
                            <i class="fas fa-user text-sm"></i>
                        </div>
                        <div>
                            <div class="font-medium">${log.user_name}</div>
                            <div class="text-xs text-gray-500">${log.user_type || 'User'}</div>
                        </div>
                    </div>
                </td>
                <td class="px-4 py-3">
                    <div class="font-medium">${log.action}</div>
                    <div class="text-xs text-gray-500 truncate max-w-xs">${log.details}</div>
                </td>
                <td class="px-4 py-3 mobile-hidden">
                    <div class="text-sm truncate max-w-xs" title="${log.description || ''}">
                        ${log.description || 'No description'}
                    </div>
                </td>
                <td class="px-4 py-3">
                    <div class="text-sm">${this.formatDate(log.created_at)}</div>
                    <div class="text-xs text-gray-500">${this.formatTime(log.created_at)}</div>
                </td>
                <td class="px-4 py-3">
                    <div class="flex items-center space-x-2">
                        <button onclick="event.stopPropagation(); window.showLogDetails(${JSON.stringify(log).replace(/"/g, '&quot;')})" 
                                class="p-1.5 hover:bg-gray-100 dark:hover:bg-gray-700 rounded"
                                title="View details">
                            <i class="fas fa-eye text-blue-500"></i>
                        </button>
                        <button onclick="event.stopPropagation(); window.confirmDeleteLog(${log.id})" 
                                class="p-1.5 hover:bg-gray-100 dark:hover:bg-gray-700 rounded"
                                title="Delete log">
                            <i class="fas fa-trash text-red-500"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `).join('');

                tbody.classList.remove('hidden');
                this.updatePagination();
                this.updateBulkActions();
            }

            formatDate(dateString) {
                try {
                    const date = new Date(dateString);
                    if (isNaN(date.getTime())) return 'Invalid date';
                    return date.toLocaleDateString('en-PH', {
                        year: 'numeric',
                        month: 'short',
                        day: 'numeric'
                    });
                } catch (e) {
                    return 'Invalid date';
                }
            }

            formatTime(dateString) {
                try {
                    const date = new Date(dateString);
                    if (isNaN(date.getTime())) return 'Invalid time';
                    return date.toLocaleTimeString('en-PH', {
                        hour: '2-digit',
                        minute: '2-digit'
                    });
                } catch (e) {
                    return 'Invalid time';
                }
            }

            updatePagination() {
                const showingFrom = ((this.currentPage - 1) * this.limit) + 1;
                const showingTo = Math.min(this.currentPage * this.limit, this.totalItems);

                const showingFromEl = document.getElementById('showingFrom');
                const showingToEl = document.getElementById('showingTo');
                const totalItemsEl = document.getElementById('totalItems');

                if (showingFromEl) showingFromEl.textContent = showingFrom;
                if (showingToEl) showingToEl.textContent = showingTo;
                if (totalItemsEl) totalItemsEl.textContent = this.totalItems;

                // Update page numbers
                const pageNumbers = document.getElementById('pageNumbers');
                if (!pageNumbers) return;

                let pagesHTML = '';

                // Always show first page
                if (this.totalPages > 1) {
                    if (this.currentPage === 1) {
                        pagesHTML += `<button class="px-3 py-1.5 bg-blue-600 text-white rounded-lg">1</button>`;
                    } else {
                        pagesHTML += `<button onclick="window.changePage(1)" class="px-3 py-1.5 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700">1</button>`;
                    }
                }

                // Show ellipsis if needed
                if (this.currentPage > 3) {
                    pagesHTML += `<span class="px-2">...</span>`;
                }

                // Show pages around current page
                const startPage = Math.max(2, this.currentPage - 1);
                const endPage = Math.min(this.totalPages - 1, this.currentPage + 1);

                for (let i = startPage; i <= endPage; i++) {
                    if (i === this.currentPage) {
                        pagesHTML += `<button class="px-3 py-1.5 bg-blue-600 text-white rounded-lg">${i}</button>`;
                    } else {
                        pagesHTML += `<button onclick="window.changePage(${i})" class="px-3 py-1.5 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700">${i}</button>`;
                    }
                }

                // Show ellipsis if needed
                if (this.currentPage < this.totalPages - 2) {
                    pagesHTML += `<span class="px-2">...</span>`;
                }

                // Always show last page if there is one
                if (this.totalPages > 1) {
                    if (this.currentPage === this.totalPages) {
                        pagesHTML += `<button class="px-3 py-1.5 bg-blue-600 text-white rounded-lg">${this.totalPages}</button>`;
                    } else {
                        pagesHTML += `<button onclick="window.changePage(${this.totalPages})" class="px-3 py-1.5 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700">${this.totalPages}</button>`;
                    }
                }

                pageNumbers.innerHTML = pagesHTML;

                // Update button states
                const prevPageBtn = document.getElementById('prevPage');
                const nextPageBtn = document.getElementById('nextPage');
                if (prevPageBtn) prevPageBtn.disabled = this.currentPage === 1;
                if (nextPageBtn) nextPageBtn.disabled = this.currentPage === this.totalPages || this.totalPages === 0;
            }

            toggleSelectAll(checkbox) {
                const checkboxes = document.querySelectorAll('input[type="checkbox"]:not(#selectAllMain)');
                checkboxes.forEach(cb => {
                    cb.checked = checkbox.checked;
                    const logId = parseInt(cb.value);
                    if (checkbox.checked) {
                        this.selectedLogs.add(logId);
                    } else {
                        this.selectedLogs.delete(logId);
                    }
                });
                this.updateBulkActions();
            }

            toggleLogSelection(logId) {
                if (this.selectedLogs.has(logId)) {
                    this.selectedLogs.delete(logId);
                } else {
                    this.selectedLogs.add(logId);
                }
                this.updateBulkActions();
            }

            updateBulkActions() {
                const selectedCount = this.selectedLogs.size;
                const bulkActionsBar = document.getElementById('bulkActionsBar');
                const selectedCountElement = document.getElementById('selectedCount');

                if (selectedCountElement) {
                    selectedCountElement.textContent = selectedCount;
                }

                if (bulkActionsBar) {
                    if (selectedCount > 0) {
                        bulkActionsBar.classList.remove('hidden');
                    } else {
                        bulkActionsBar.classList.add('hidden');
                    }
                }

                // Update select all checkbox
                const totalCheckboxes = document.querySelectorAll('input[type="checkbox"]:not(#selectAllMain)').length;
                const selectAllCheckbox = document.getElementById('selectAllMain');
                if (selectAllCheckbox) {
                    selectAllCheckbox.checked = selectedCount > 0 && selectedCount === totalCheckboxes;
                    selectAllCheckbox.indeterminate = selectedCount > 0 && selectedCount < totalCheckboxes;
                }
            }

            clearSelection() {
                this.selectedLogs.clear();
                const checkboxes = document.querySelectorAll('input[type="checkbox"]');
                checkboxes.forEach(cb => cb.checked = false);
                this.updateBulkActions();
            }

            changePage(page) {
                if (page < 1 || page > this.totalPages) return;
                this.currentPage = page;
                this.loadLogs();
            }

            changeLimit(newLimit) {
                this.limit = parseInt(newLimit);
                this.currentPage = 1;
                this.loadLogs();
            }

            debouncedSearch() {
                clearTimeout(this.searchTimeout);
                this.searchTimeout = setTimeout(() => {
                    const searchInput = document.getElementById('searchInput');
                    if (searchInput) {
                        this.searchTerm = searchInput.value;
                        this.currentPage = 1;
                        this.loadLogs();
                    }
                }, 500);
            }

            filterLogs(filter) {
                this.currentFilter = filter;
                this.currentPage = 1;

                // Update active filter button
                const filterButtons = ['All', 'Activity', 'History'];
                filterButtons.forEach(btn => {
                    const element = document.getElementById(`filter${btn}`);
                    if (element) {
                        element.classList.remove('bg-blue-100', 'dark:bg-blue-900/30', 'text-blue-700', 'dark:text-blue-300', 'font-medium');
                        element.classList.add('bg-gray-100', 'dark:bg-gray-700', 'text-gray-700', 'dark:text-gray-300');
                    }
                });

                const activeButton = document.getElementById(`filter${filter.charAt(0).toUpperCase() + filter.slice(1)}`);
                if (activeButton) {
                    activeButton.className = 'px-4 py-2.5 rounded-lg bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 font-medium';
                }

                this.loadLogs();
            }

            clearSearch() {
                const searchInput = document.getElementById('searchInput');
                if (searchInput) {
                    searchInput.value = '';
                    this.searchTerm = '';
                    this.loadLogs();
                }
            }

            clearFilters() {
                this.clearSearch();
                this.filterLogs('all');
                this.clearSelection();
            }

            clearFilters() {
                this.clearSearch();
                this.filterLogs('all');
                this.clearAdvancedFilters();
                this.clearSelection();
                this.showToast('All filters cleared', 'info');
            }
            toggleAdvancedFilters() {
                const filters = document.getElementById('advancedFilters');
                if (filters) {
                    filters.classList.toggle('hidden');
                }
            }

            applyAdvancedFilters() {
                // Implement advanced filtering logic here
                this.loadLogs();
            }

            sortTable(field) {
                if (this.sortField === field) {
                    this.sortOrder = this.sortOrder === 'asc' ? 'desc' : 'asc';
                } else {
                    this.sortField = field;
                    this.sortOrder = 'desc';
                }
                this.loadLogs();
            }

            showLogDetails(log) {
                const modal = document.getElementById('detailsModal');
                const content = document.getElementById('modalContent');

                if (!modal || !content) return;

                content.innerHTML = `
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="space-y-4">
                    <div>
                        <label class="text-sm text-gray-500">Log ID</label>
                        <p class="font-medium">${log.id}</p>
                    </div>
                    <div>
                        <label class="text-sm text-gray-500">Type</label>
                        <span class="badge ${log.type === 'activity' ? 'badge-activity' : 'badge-history'}">
                            ${log.type === 'activity' ? 'Activity' : 'History'}
                        </span>
                    </div>
                    <div>
                        <label class="text-sm text-gray-500">User</label>
                        <p class="font-medium">${log.user_name}</p>
                        <p class="text-sm text-gray-500">${log.user_type || 'N/A'}</p>
                    </div>
                    <div>
                        <label class="text-sm text-gray-500">Action</label>
                        <p class="font-medium">${log.action}</p>
                    </div>
                </div>
                <div class="space-y-4">
                    <div>
                        <label class="text-sm text-gray-500">Date & Time</label>
                        <p class="font-medium">${this.formatDate(log.created_at)} ${this.formatTime(log.created_at)}</p>
                    </div>
                    <div>
                        <label class="text-sm text-gray-500">IP Address</label>
                        <p class="font-medium font-mono">${log.ip_address || 'N/A'}</p>
                    </div>
                    <div>
                        <label class="text-sm text-gray-500">User Agent</label>
                        <p class="text-sm truncate" title="${log.user_agent || 'N/A'}">${log.user_agent || 'N/A'}</p>
                    </div>
                </div>
            </div>
            <div class="mt-6 space-y-4">
                <div>
                    <label class="text-sm text-gray-500">Details</label>
                    <div class="mt-1 p-3 bg-gray-50 dark:bg-gray-700 rounded">
                        <p>${log.details}</p>
                    </div>
                </div>
                ${log.description ? `
                <div>
                    <label class="text-sm text-gray-500">Description</label>
                    <div class="mt-1 p-3 bg-gray-50 dark:bg-gray-700 rounded">
                        <p>${log.description}</p>
                    </div>
                </div>
                ` : ''}
            </div>
        `;

                modal.classList.remove('hidden');
            }

            closeDetailsModal() {
                const modal = document.getElementById('detailsModal');
                if (modal) modal.classList.add('hidden');
            }

            confirmDeleteLog(logId = null) {
                this.logToDelete = logId;
                const modal = document.getElementById('deleteModal');
                if (!modal) return;

                const title = document.getElementById('deleteModalTitle');
                const message = document.getElementById('deleteModalMessage');

                if (title && message) {
                    if (logId) {
                        title.textContent = 'Delete Log';
                        message.textContent = 'Are you sure you want to delete this log? This action cannot be undone.';
                    } else {
                        title.textContent = 'Delete Selected Logs';
                        message.textContent = `Are you sure you want to delete ${this.selectedLogs.size} selected logs? This action cannot be undone.`;
                    }
                }

                modal.classList.remove('hidden');
            }

            async confirmDelete() {
                const logIds = this.logToDelete ? [this.logToDelete] : Array.from(this.selectedLogs);

                try {
                    const response = await fetch('/dentalemr_system/php/manageusers/delete_logs.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            log_ids: logIds,
                            user_id: <?php echo $userId; ?>
                        })
                    });

                    const result = await response.json();

                    if (result.success) {
                        this.showToast(`Successfully deleted ${logIds.length} log(s)`, 'success');
                        this.closeDeleteModal();
                        this.clearSelection();
                        this.loadLogs();
                        this.loadStats();
                    } else {
                        throw new Error(result.message);
                    }
                } catch (error) {
                    console.error('Error deleting logs:', error);
                    this.showToast('Failed to delete logs', 'error');
                }
            }

            closeDeleteModal() {
                const modal = document.getElementById('deleteModal');
                if (modal) modal.classList.add('hidden');
                this.logToDelete = null;
            }

            showExportModal() {
                const modal = document.getElementById('exportModal');
                if (modal) modal.classList.remove('hidden');
            }

            closeExportModal() {
                const modal = document.getElementById('exportModal');
                if (modal) modal.classList.add('hidden');
            }

            proceedExport() {
                const format = document.querySelector('input[name="exportFormat"]:checked');
                if (!format) return;

                const formatValue = format.value;
                const logIds = this.selectedLogs.size > 0 ? Array.from(this.selectedLogs) : null;

                // Build query parameters
                const params = new URLSearchParams({
                    format: formatValue,
                    user_id: <?php echo $userId; ?>
                });

                if (logIds && logIds.length > 0) {
                    params.append('log_ids', logIds.join(','));
                }

                this.closeExportModal();
                this.showToast('Preparing export...', 'info');

                // Create download link
                const link = document.createElement('a');
                link.href = `/dentalemr_system/php/manageusers/export_logs.php?${params}`;
                link.target = '_blank';
                link.style.display = 'none';

                // For JSON format, we need to handle it differently
                if (formatValue === 'json') {
                    // Fetch JSON data first to handle errors
                    fetch(link.href)
                        .then(response => {
                            if (!response.ok) {
                                throw new Error('Export failed');
                            }
                            return response.json();
                        })
                        .then(data => {
                            if (data.success === false) {
                                throw new Error(data.message || 'Export failed');
                            }
                            // Create downloadable JSON file
                            const blob = new Blob([JSON.stringify(data, null, 2)], {
                                type: 'application/json'
                            });
                            const url = URL.createObjectURL(blob);
                            link.href = url;
                            link.download = `system_logs_${new Date().toISOString().split('T')[0]}.json`;
                            document.body.appendChild(link);
                            link.click();
                            document.body.removeChild(link);
                            URL.revokeObjectURL(url);
                            this.showToast('Export completed successfully', 'success');
                        })
                        .catch(error => {
                            console.error('Export error:', error);
                            this.showToast(`Export failed: ${error.message}`, 'error');
                        });
                } else {
                    // For CSV and PDF, use direct download
                    link.download = `system_logs_${new Date().toISOString().split('T')[0]}.${formatValue}`;
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                    this.showToast('Export started', 'success');
                }
            }

            exportSelected() {
                if (this.selectedLogs.size === 0) {
                    this.showToast('Please select logs to export', 'warning');
                    return;
                }
                this.showExportModal();
            }

            refreshLogs() {
                this.clearSelection();
                this.loadLogs();
                this.loadStats();
                this.updateLastUpdated();
                this.showToast('Logs refreshed', 'success');
            }

            async loadStats() {
                try {
                    const urlParams = new URLSearchParams(window.location.search);
                    const userId = urlParams.get('uid');

                    if (!userId) {
                        console.error('User ID missing for stats');
                        return;
                    }

                    const response = await fetch(`/dentalemr_system/php/manageusers/get_stats.php?uid=${userId}`, {
                        cache: 'no-store'
                    });
                    const data = await response.json();

                    if (data.success) {
                        const totalLogsEl = document.getElementById('totalLogs');
                        const todayLogsEl = document.getElementById('todayLogs');
                        const activeUsersEl = document.getElementById('activeUsers');
                        const dbSizeEl = document.getElementById('dbSize');

                        if (totalLogsEl) totalLogsEl.textContent = data.total_logs.toLocaleString();
                        if (todayLogsEl) todayLogsEl.textContent = data.today_logs.toLocaleString();
                        if (activeUsersEl) activeUsersEl.textContent = data.active_users.toLocaleString();
                        if (dbSizeEl) dbSizeEl.textContent = `${data.db_size.toFixed(2)} MB`;
                    }
                } catch (error) {
                    console.error('Error loading stats:', error);
                }
            }

            async loadQuickStats() {
                try {
                    const urlParams = new URLSearchParams(window.location.search);
                    const userId = urlParams.get('uid');

                    if (!userId) {
                        console.error('User ID missing for quick stats');
                        return;
                    }

                    const response = await fetch(`/dentalemr_system/php/manageusers/get_quick_stats.php?uid=${userId}`, {
                        cache: 'no-store'
                    });
                    const data = await response.json();

                    if (data.success) {
                        this.renderTopUsers(data.top_users);
                        this.renderActivityTypes(data.activity_types);
                    }
                } catch (error) {
                    console.error('Error loading quick stats:', error);
                }
            }

            renderTopUsers(users) {
                const container = document.getElementById('topUsers');
                if (!container) return;

                container.innerHTML = users.map(user => `
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <div class="w-8 h-8 rounded-full bg-gray-200 dark:bg-gray-700 flex items-center justify-center mr-3">
                        <i class="fas fa-user text-sm"></i>
                    </div>
                    <span class="text-sm truncate max-w-[120px]">${user.name}</span>
                </div>
                <span class="text-sm font-medium">${user.count}</span>
            </div>
        `).join('');
            }

            renderActivityTypes(activities) {
                const container = document.getElementById('activityTypes');
                if (!container) return;

                container.innerHTML = activities.map(activity => `
            <div class="flex items-center justify-between">
                <span class="text-sm truncate max-w-[120px]">${activity.type}</span>
                <span class="text-sm font-medium">${activity.count}</span>
            </div>
        `).join('');
            }

            updateLastUpdated() {
                const now = new Date();
                const timeString = now.toLocaleTimeString('en-PH', {
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit'
                });
                const lastUpdatedEl = document.getElementById('lastUpdated');
                if (lastUpdatedEl) {
                    lastUpdatedEl.textContent = timeString;
                }
            }

            toggleMobileSidebar() {
                const sidebar = document.getElementById('mobileSidebar');
                if (sidebar) {
                    sidebar.classList.toggle('hidden');
                }
            }

            showLoadingState() {
                const logsBody = document.getElementById('logsBody');
                const emptyState = document.getElementById('emptyState');
                const loadingState = document.getElementById('loadingState');

                if (logsBody) logsBody.classList.add('hidden');
                if (emptyState) emptyState.classList.add('hidden');
                if (loadingState) loadingState.classList.remove('hidden');
            }

            hideLoadingState() {
                const loadingState = document.getElementById('loadingState');
                if (loadingState) loadingState.classList.add('hidden');
            }

            showEmptyState() {
                const logsBody = document.getElementById('logsBody');
                const emptyState = document.getElementById('emptyState');
                const loadingState = document.getElementById('loadingState');

                if (logsBody) logsBody.classList.add('hidden');
                if (loadingState) loadingState.classList.add('hidden');
                if (emptyState) emptyState.classList.remove('hidden');
            }

            hideEmptyState() {
                const emptyState = document.getElementById('emptyState');
                if (emptyState) emptyState.classList.add('hidden');
            }

            closeAllModals() {
                this.closeDetailsModal();
                this.closeDeleteModal();
                this.closeExportModal();
            }

            showToast(message, type = 'info') {
                const container = document.getElementById('toastContainer');
                if (!container) return;

                const id = Date.now();

                const toast = document.createElement('div');
                toast.id = `toast-${id}`;
                toast.className = `
            ${type === 'success' ? 'bg-green-500' : 
              type === 'error' ? 'bg-red-500' : 
              type === 'warning' ? 'bg-yellow-500' : 
              'bg-blue-500'}
            text-white px-4 py-3 rounded-lg shadow-lg max-w-sm transform transition-all duration-300 ease-in-out
        `;

                toast.innerHTML = `
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <i class="fas fa-${type === 'success' ? 'check-circle' : 
                                  type === 'error' ? 'exclamation-circle' : 
                                  type === 'warning' ? 'exclamation-triangle' : 
                                  'info-circle'} mr-2"></i>
                    <span>${message}</span>
                </div>
                <button onclick="document.getElementById('toast-${id}').remove()" class="ml-4">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;

                container.appendChild(toast);

                // Auto remove after 5 seconds
                setTimeout(() => {
                    if (toast.parentElement) {
                        toast.style.opacity = '0';
                        toast.style.transform = 'translateX(100%)';
                        setTimeout(() => toast.remove(), 300);
                    }
                }, 5000);
            }

            showError(message) {
                this.showToast(message, 'error');
            }
        }

        // Wait for DOM to be fully loaded before initializing
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize the system logs manager
            window.systemLogs = new SystemLogsManager();
            window.systemLogs.init();

            // Test API endpoint
            const urlParams = new URLSearchParams(window.location.search);
            const userId = urlParams.get('uid');

            console.log('System Logs Manager initialized for user:', userId);
        });

        // Global functions for inline event handlers
        window.toggleMobileSidebar = () => window.systemLogs?.toggleMobileSidebar();
        window.refreshLogs = () => window.systemLogs?.refreshLogs();
        window.debouncedSearch = () => window.systemLogs?.debouncedSearch();
        window.clearSearch = () => window.systemLogs?.clearSearch();
        window.filterLogs = (filter) => {
            if (window.systemLogs) {
                window.systemLogs.currentPage = 1;
                window.systemLogs.filterLogs(filter);
            }
        };
        window.toggleAdvancedFilters = () => window.systemLogs?.toggleAdvancedFilters();
        window.applyAdvancedFilters = () => window.systemLogs?.applyAdvancedFilters();
        window.clearFilters = () => window.systemLogs?.clearFilters();
        window.sortTable = (field) => window.systemLogs?.sortTable(field);
        window.changePage = (page) => window.systemLogs?.changePage(page);
        window.changeLimit = (limit) => window.systemLogs?.changeLimit(limit);
        window.toggleSelectAll = (checkbox) => window.systemLogs?.toggleSelectAll(checkbox);
        window.toggleLogSelection = (logId) => window.systemLogs?.toggleLogSelection(logId);
        window.clearSelection = () => window.systemLogs?.clearSelection();
        window.showLogDetails = (log) => window.systemLogs?.showLogDetails(log);
        window.closeDetailsModal = () => window.systemLogs?.closeDetailsModal();
        window.confirmDeleteLog = (logId) => window.systemLogs?.confirmDeleteLog(logId);
        window.confirmBulkDelete = () => window.systemLogs?.confirmDeleteLog();
        window.confirmDelete = () => window.systemLogs?.confirmDelete();
        window.closeDeleteModal = () => window.systemLogs?.closeDeleteModal();
        window.showExportModal = () => window.systemLogs?.showExportModal();
        window.closeExportModal = () => window.systemLogs?.closeExportModal();
        window.proceedExport = () => window.systemLogs?.proceedExport();
        window.exportSelected = () => window.systemLogs?.exportSelected();
    </script>

    <!-- Inactivity Timer -->
    <script>
        let inactivityTimer;
        const inactivityLimit = 1800000; // 10 minutes

        function resetInactivityTimer() {
            clearTimeout(inactivityTimer);
            inactivityTimer = setTimeout(() => {
                window.location.href = '/dentalemr_system/php/login/logout.php?uid=<?php echo $userId; ?>&reason=inactivity';
            }, inactivityLimit);
        }

        // Reset timer on user activity
        ['click', 'mousemove', 'keypress', 'scroll', 'touchstart'].forEach(event => {
            document.addEventListener(event, resetInactivityTimer);
        });

        // Start timer
        resetInactivityTimer();
    </script>
</body>

</html>