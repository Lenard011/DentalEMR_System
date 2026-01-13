<?php
session_start();
date_default_timezone_set('Asia/Manila');

// REQUIRE userId parameter for each page
// Example usage: dashboard.php?uid=5
if (!isset($_GET['uid'])) {
    header('Location: /DentalEMR_System/html/login/login.html?error=invalid_session');
    exit;
}

$userId = intval($_GET['uid']);

// CHECK IF THIS USER IS REALLY LOGGED IN
if (
    !isset($_SESSION['active_sessions']) ||
    !isset($_SESSION['active_sessions'][$userId])
) {
    header('Location: /DentalEMR_System/html/login/login.html?error=session_expired');
    exit;
}

// PER-USER INACTIVITY TIMEOUT
$inactiveLimit = 1800; // 10 minutes

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

        header('Location: /DentalEMR_System/html/login/login.html?error=inactivity');
        exit;
    }
}

// Update last activity timestamp
$_SESSION['active_sessions'][$userId]['last_activity'] = time();

// GET USER DATA FOR PAGE USE
$loggedUser = $_SESSION['active_sessions'][$userId];

// Store user session info safely
$host = "localhost";
$dbUser = "u401132124_dentalclinic";
$dbPass = "Mho_DentalClinic1st";
$dbName = "u401132124_mho_dentalemr";

// At the top of PHP section, after connection


$conn = new mysqli($host, $dbUser, $dbPass, $dbName);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch dentist name and profile picture if user is a dentist
if ($loggedUser['type'] === 'Dentist') {
    $stmt = $conn->prepare("SELECT name, profile_picture FROM dentist WHERE id = ?");
    $stmt->bind_param("i", $loggedUser['id']);
    $stmt->execute();
    $stmt->bind_result($dentistName, $dentistProfilePicture);
    if ($stmt->fetch()) {
        $loggedUser['name'] = $dentistName;
        $loggedUser['profile_picture'] = $dentistProfilePicture; // Add this line
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
    <link rel="icon" type="image/png" href="/DentalEMR_System/img/1761912137392.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Improved Table Styles */
        .log-table-container {
            max-height: calc(100vh - 300px);
            overflow-y: auto;
        }

        .log-table th {
            position: sticky;
            top: 0;
            background-color: #f9fafb;
            z-index: 10;
        }

        .dark .log-table th {
            background-color: #374151;
        }

        /* Action badge styles */
        .action-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 0.375rem;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        /* Loading animation */
        .spinner {
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            from {
                transform: rotate(0deg);
            }

            to {
                transform: rotate(360deg);
            }
        }

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
    <!-- Add this CSS to your <style> section -->
    <style>
        /* Loading Animation Improvements */
        .loading-spinner {
            display: inline-block;
            width: 50px;
            height: 50px;
            border: 3px solid rgba(59, 130, 246, 0.3);
            border-radius: 50%;
            border-top-color: #3b82f6;
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        /* Fade in animation for table */
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .fade-in {
            animation: fadeIn 0.3s ease-out;
        }

        /* Skeleton loading */
        .skeleton {
            background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
            background-size: 200% 100%;
            animation: loading 1.5s infinite;
        }

        .dark .skeleton {
            background: linear-gradient(90deg, #374151 25%, #4b5563 50%, #374151 75%);
            background-size: 200% 100%;
        }

        @keyframes loading {
            0% {
                background-position: 200% 0;
            }

            100% {
                background-position: -200% 0;
            }
        }

        /* Loading dots */
        .loading-dots {
            display: inline-block;
        }

        .loading-dots:after {
            content: '.';
            animation: dots 1.5s steps(5, end) infinite;
        }

        @keyframes dots {

            0%,
            20% {
                content: '.';
            }

            40% {
                content: '..';
            }

            60% {
                content: '...';
            }

            80%,
            100% {
                content: '';
            }
        }

        /* Modal improvements */
        .modal-backdrop {
            backdrop-filter: blur(5px);
            animation: fadeIn 0.2s ease-out;
        }

        .modal-content {
            animation: slideUp 0.3s ease-out;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* JSON display styling */
        pre {
            font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
            line-height: 1.4;
        }

        /* Scrollable content */
        .max-h-40 {
            max-height: 10rem;
        }

        /* Improved hover states for action buttons */
        button:hover {
            transform: translateY(-1px);
            transition: transform 0.2s ease;
        }

        /* Button spacing */
        .flex.space-x-2>*+* {
            margin-left: 0.5rem;
        }

        /* Button hover effects */
        .view-log-btn:hover,
        .delete-log-btn:hover {
            transform: translateY(-1px);
            transition: transform 0.2s ease;
        }

        /* Disabled button style */
        button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
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
        <nav class="bg-white border-b border-gray-200 px-4 py-2.5 dark:bg-gray-800 dark:border-gray-700 fixed left-0 right-0 top-0 z-50">
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
                        <span class="sr-only">Toggle sidebar</span>
                    </button>
                    <a href="/DentalEMR_System/html/index.php?uid=<?php echo $userId; ?>" class="flex items-center justify-between mr-4">
                        <img src="https://th.bing.com/th/id/OIP.zjh8eiLAHY9ybXUCuYiqQwAAAA?r=0&rs=1&pid=ImgDetMain&cb=idpwebp1&o=7&rm=3"
                            class="mr-3 h-8 rounded-full" alt="MHO Dental Clinic Logo" />
                        <span class="self-center text-2xl font-semibold whitespace-nowrap dark:text-white">MHO Dental Clinic</span>
                    </a>
                </div>

                <!-- User Profile -->
                <div class="flex items-center space-x-3">
                    <div class="relative">
                        <button type="button" id="userDropdownButton"
                            class="flex items-center space-x-2 p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 dark:text-white transition-colors duration-200">
                            <!-- Profile Picture -->
                            <div class="w-8 h-8 rounded-full bg-gray-200 dark:bg-gray-700 overflow-hidden">
                                <?php if (!empty($loggedUser['profile_picture'])): ?>
                                    <img src="<?php echo htmlspecialchars($loggedUser['profile_picture']); ?>"
                                        alt="Profile" class="w-full h-full object-cover">
                                <?php else: ?>
                                    <div class="w-full h-full flex items-center justify-center">
                                        <i class="fas fa-user text-gray-600 dark:text-gray-400"></i>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- User Info -->
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
                        <div id="userDropdown"
                            class="absolute right-0 mt-2 w-64 bg-white dark:bg-gray-800 rounded-lg shadow-lg border border-gray-200 dark:border-gray-700 hidden z-50 transform transition-all duration-200 origin-top-right">
                            <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                                <div class="text-sm font-semibold text-gray-900 dark:text-white truncate" id="dropdownUserName">
                                    <?php echo htmlspecialchars($loggedUser['name'] ?? 'User'); ?>
                                </div>
                                <div class="text-xs text-gray-600 dark:text-gray-400 mt-1 truncate">
                                    <?php echo htmlspecialchars($loggedUser['email'] ?? 'user@example.com'); ?>
                                </div>
                            </div>
                            <div class="py-2">
                                <a href="/DentalEMR_System/html/manageusers/profile.php?uid=<?php echo $userId; ?>"
                                    class="flex items-center px-4 py-2.5 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700   transition-colors duration-150">
                                    <i class="fas fa-user-circle mr-3 text-gray-500 w-4 text-center"></i>
                                    <span>My Profile</span>
                                </a>
                                <a href="/DentalEMR_System/html/manageusers/manageuser.php?uid=<?php echo $userId; ?>"
                                    class="flex items-center px-4 py-2.5 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors duration-150">
                                    <i class="fas fa-users-cog mr-3 text-gray-500 w-4 text-center"></i>
                                    <span>Manage Users</span>
                                </a>
                                <a href="#"
                                    class="flex items-center px-4 py-2.5 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 dark:bg-blue-900/20 transition-colors duration-150">
                                    <i class="fas fa-history mr-3 text-blue-500 w-4 text-center"></i>
                                    <span>System Logs</span>
                                </a>
                            </div>
                            <!-- Theme Toggle -->
                            <div class="border-t border-gray-200 dark:border-gray-700 py-2">
                                <button type="button" id="theme-toggle"
                                    class="flex items-center w-full px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700">
                                    <svg id="theme-toggle-dark-icon" class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z"></path>
                                    </svg>
                                    <svg id="theme-toggle-light-icon" class="w-4 h-4 mr-2 hidden" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                                        <path fill-rule="evenodd" d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4 8a4 4 0 11-8 0 4 4 0 018 0zm-.464 4.95l.707.707a1 1 0 001.414-1.414l-.707-.707a1 1 0 00-1.414 1.414zm2.12-10.607a1 1 0 010 1.414l-.706.707a1 1 0 11-1.414-1.414l.707-.707a1 1 0 011.414 0zM17 11a1 1 0 100-2h-1a1 1 0 100 2h1zm-7 4a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zM5.05 6.464A1 1 0 106.465 5.05l-.708-.707a1 1 0 00-1.414 1.414l.707.707zm1.414 8.486l-.707.707a1 1 0 01-1.414-1.414l.707-.707a1 1 0 011.414 1.414zM4 11a1 1 0 100-2H3a1 1 0 000 2h1z" clip-rule="evenodd"></path>
                                    </svg>
                                    <span id="theme-toggle-text">Toggle theme</span>
                                </button>
                            </div>
                            <div class="border-t border-gray-200 dark:border-gray-700 py-2">
                                <a href="/DentalEMR_System/php/login/logout.php?uid=<?php echo $loggedUser['id']; ?>"
                                    class="flex items-center px-4 py-2.5 text-sm text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors duration-150">
                                    <i class="fas fa-sign-out-alt mr-3 w-4 text-center"></i>
                                    <span>Sign Out</span>
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
                        <a href="/DentalEMR_System/html/index.php?uid=<?php echo $userId; ?>"
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
                        <a href="/DentalEMR_System/html/addpatient.php?uid=<?php echo $userId; ?>"
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
                                <a href="/DentalEMR_System/html/treatmentrecords/treatmentrecords.php?uid=<?php echo $userId; ?>"
                                    class="flex items-center p-2 pl-11 w-full text-base font-medium text-gray-900 rounded-lg transition duration-75 group hover:bg-gray-100 dark:text-white dark:hover:bg-gray-700">Treatment
                                    Records</a>
                            </li>
                            <li>
                                <a href="/DentalEMR_System/html/addpatienttreatment/patienttreatment.php?uid=<?php echo $userId; ?>"
                                    class="flex items-center p-2 pl-11 w-full text-base font-medium text-gray-900 rounded-lg transition duration-75 group hover:bg-gray-100 dark:text-white dark:hover:bg-gray-700">Add
                                    Patient Treatment</a>
                            </li>
                        </ul>
                    </li>
                </ul>
                <ul class="pt-5 mt-5 space-y-2 border-t border-gray-200 dark:border-gray-700">
                    <li>
                        <a href="/DentalEMR_System/html/reports/targetclientlist.php?uid=<?php echo $userId; ?>"
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
                        <a href="/DentalEMR_System/html/reports/mho_ohp.php?uid=<?php echo $userId; ?>"
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
                        <a href="/DentalEMR_System/html/reports/oralhygienefindings.php?uid=<?php echo $userId; ?>"
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
                        <a href="/DentalEMR_System/html/archived.php?uid=<?php echo $userId; ?>"
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
            <div class="mb-6">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">System Logs</h1>
                <p class="text-gray-600 dark:text-gray-400">Monitor all system activities and user actions</p>
            </div>

            <!-- Tabs -->
            <div class="mb-6 border-b border-gray-200 dark:border-gray-700">
                <ul class="flex flex-wrap -mb-px text-sm font-medium text-center" id="logsTab" role="tablist">
                    <li class="me-2" role="presentation">
                        <button class="inline-block p-4 border-b-2 rounded-t-lg border-blue-600 text-blue-600 dark:text-blue-500 dark:border-blue-500"
                            id="activity-tab" data-tab="activity" type="button" role="tab">Activity Logs</button>
                    </li>
                    <li class="me-2" role="presentation">
                        <button class="inline-block p-4 border-b-2 border-transparent rounded-t-lg hover:text-gray-600 hover:border-gray-300 dark:hover:text-gray-300"
                            id="history-tab" data-tab="history" type="button" role="tab">History Logs</button>
                    </li>
                </ul>
            </div>

            <!-- Filters -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 mb-6">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-sm font-medium mb-1">Date From</label>
                        <input type="date" id="dateFrom" class="w-full p-2 border rounded-lg dark:bg-gray-700">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Date To</label>
                        <input type="date" id="dateTo" class="w-full p-2 border rounded-lg dark:bg-gray-700">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">User Type</label>
                        <select id="userFilter" class="w-full p-2 border rounded-lg dark:bg-gray-700">
                            <option value="">All Users</option>
                            <option value="dentist">Dentist</option>
                            <option value="staff">Staff</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Action Type</label>
                        <select id="actionFilter" class="w-full p-2 border rounded-lg dark:bg-gray-700">
                            <option value="">All Actions</option>

                            <!-- Session Actions (from activity_logs) -->
                            <option value="Login">Login</option>
                            <option value="Logout">Logout</option>
                            <option value="Failed Login">Failed Login</option>

                            <!-- CRUD Actions (for both tables) -->
                            <option value="INSERT">Create/Insert</option>
                            <option value="UPDATE">Update/Edit</option>
                            <option value="DELETE">Delete/Remove</option>

                            <!-- System Actions -->
                            <option value="Deleted">Deleted</option>
                            <option value="Email Failed">Email Failed</option>

                            <!-- Export/Import -->
                            <option value="Export">Export</option>
                            <!-- <option value="Import">Import</option> -->
                        </select>
                    </div>
                </div>
                <div class="flex justify-end mt-4 space-x-2">
                    <button id="applyFilters" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                        Apply Filters
                    </button>
                    <button id="clearFilters" class="px-4 py-2 border rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                        Clear
                    </button>
                </div>
            </div>

            <!-- Logs Container -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
                <div class="p-4 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center">
                    <div>
                        <h3 class="text-lg font-semibold">Log Entries</h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400" id="entriesCount">Select filters to view logs</p>
                    </div>
                    <div class="flex space-x-2">
                        <button id="exportBtn" class="px-4 py-2 border rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                            <i class="fas fa-download mr-2"></i>Export
                        </button>
                        <button id="refreshBtn" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                            <i class="fas fa-sync-alt mr-2"></i>Refresh
                        </button>
                    </div>
                </div>

                <!-- Initial State (shown by default) -->
                <div id="initialState" class="p-8 text-center">
                    <div class="inline-block text-blue-600 mb-4">
                        <i class="fas fa-history text-4xl"></i>
                    </div>
                    <h4 class="text-lg font-medium mb-2">Welcome to System Logs</h4>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">Configure your filters and click "Apply Filters" to view logs</p>
                    <div class="inline-flex items-center text-sm text-blue-600">
                        <i class="fas fa-lightbulb mr-2"></i>
                        <span>Tip: Try filtering by date range for better results</span>
                    </div>
                </div>

                <!-- Loading State -->
                <div id="loading" class="hidden p-8 text-center">
                    <div class="inline-block loading-spinner mb-4"></div>
                    <h4 class="text-lg font-medium mb-2">Loading logs<span class="loading-dots"></span></h4>
                    <p class="text-gray-600 dark:text-gray-400">Fetching your data, please wait</p>
                </div>

                <!-- Skeleton Loading (for better UX) -->
                <div id="skeletonLoading" class="hidden p-4">
                    <div class="space-y-3">
                        <!-- Skeleton rows -->
                        <div class="skeleton h-12 rounded-lg"></div>
                        <div class="skeleton h-12 rounded-lg"></div>
                        <div class="skeleton h-12 rounded-lg"></div>
                        <div class="skeleton h-12 rounded-lg"></div>
                        <div class="skeleton h-12 rounded-lg"></div>
                    </div>
                </div>

                <!-- Logs Table -->
                <div id="logsTable" class="hidden fade-in">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm text-left text-gray-700 dark:text-gray-300">
                            <thead class="text-xs uppercase bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-6 py-3">Timestamp</th>
                                    <th class="px-6 py-3">User</th>
                                    <th class="px-6 py-3">Action</th>
                                    <th class="px-6 py-3">Target</th>
                                    <th class="px-6 py-3">Details</th>
                                    <th class="px-6 py-3">IP Address</th>
                                    <th class="px-6 py-3">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="logsBody">
                                <!-- Logs will be loaded here -->
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Pagination -->
                <div id="pagination" class="hidden p-4 border-t border-gray-200 dark:border-gray-700">
                    <nav class="flex items-center justify-between">
                        <div class="text-sm text-gray-700 dark:text-gray-400">
                            Showing <span id="startRow">1</span> to <span id="endRow">10</span> of <span id="totalRows">0</span> entries
                        </div>
                        <ul class="flex items-center space-x-2">
                            <li><button id="prevPage" class="px-3 py-1 rounded border hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">Previous</button></li>
                            <li>
                                <div id="pageNumbers" class="flex space-x-1"></div>
                            </li>
                            <li><button id="nextPage" class="px-3 py-1 rounded border hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">Next</button></li>
                        </ul>
                    </nav>
                </div>

                <!-- No Results -->
                <div id="noResults" class="hidden p-8 text-center">
                    <i class="fas fa-search text-4xl text-gray-400 mb-4"></i>
                    <h4 class="text-lg font-medium mb-2">No logs found</h4>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">Try adjusting your filters or check back later</p>
                    <button onclick="clearFilters()" class="px-4 py-2 text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300">
                        <i class="fas fa-filter mr-2"></i>Clear all filters
                    </button>
                </div>

                <!-- Error State -->
                <div id="errorState" class="hidden p-8 text-center">
                    <i class="fas fa-exclamation-triangle text-4xl text-red-400 mb-4"></i>
                    <h4 class="text-lg font-medium mb-2">Unable to load logs</h4>
                    <p class="text-gray-600 dark:text-gray-400 mb-4" id="errorMessage">Please try again later</p>
                    <button onclick="loadLogs()" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                        <i class="fas fa-redo mr-2"></i>Retry
                    </button>
                </div>
            </div>
        </main>

        <!-- Modals (Keep your existing modal structure but with improved styling) -->
        <!-- View Details Modal -->
        <div id="detailsModal" class="modal-backdrop hidden fixed inset-0 z-50 overflow-y-auto">
            <div class="min-h-screen px-4 flex items-center justify-center">
                <div class="modal-content bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-4xl w-full max-h-[90vh] overflow-hidden">
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
                                <button onclick="closeDeleteModal()" class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                                    Cancel
                                </button>
                                <button onclick="confirmDelete()" id="confirmDeleteBtn" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors">
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
    <script src="../../js/tailwind.config.js"></script>
    <script>
        // ========== THEME MANAGEMENT ==========
        function initTheme() {
            const themeToggle = document.getElementById('theme-toggle');
            const themeToggleLightIcon = document.getElementById('theme-toggle-light-icon');
            const themeToggleDarkIcon = document.getElementById('theme-toggle-dark-icon');
            const themeToggleText = document.getElementById('theme-toggle-text');

            // Get current theme
            const currentTheme = localStorage.getItem('theme') || 'light';

            // Set initial theme
            if (currentTheme === 'dark') {
                document.documentElement.classList.add('dark');
                if (themeToggleLightIcon) themeToggleLightIcon.classList.add('hidden');
                if (themeToggleDarkIcon) themeToggleDarkIcon.classList.remove('hidden');
                if (themeToggleText) themeToggleText.textContent = 'Light Mode';
            } else {
                document.documentElement.classList.remove('dark');
                if (themeToggleLightIcon) themeToggleLightIcon.classList.remove('hidden');
                if (themeToggleDarkIcon) themeToggleDarkIcon.classList.add('hidden');
                if (themeToggleText) themeToggleText.textContent = 'Dark Mode';
            }

            // Add click event to theme toggle
            if (themeToggle) {
                themeToggle.addEventListener('click', function() {
                    toggleTheme();
                });
            }
        }

        function toggleTheme() {
            const isDark = document.documentElement.classList.contains('dark');
            const themeToggleLightIcon = document.getElementById('theme-toggle-light-icon');
            const themeToggleDarkIcon = document.getElementById('theme-toggle-dark-icon');
            const themeToggleText = document.getElementById('theme-toggle-text');

            if (isDark) {
                document.documentElement.classList.remove('dark');
                localStorage.setItem('theme', 'light');
                if (themeToggleLightIcon) themeToggleLightIcon.classList.remove('hidden');
                if (themeToggleDarkIcon) themeToggleDarkIcon.classList.add('hidden');
                if (themeToggleText) themeToggleText.textContent = 'Dark Mode';
            } else {
                document.documentElement.classList.add('dark');
                localStorage.setItem('theme', 'dark');
                if (themeToggleLightIcon) themeToggleLightIcon.classList.add('hidden');
                if (themeToggleDarkIcon) themeToggleDarkIcon.classList.remove('hidden');
                if (themeToggleText) themeToggleText.textContent = 'Light Mode';
            }
        }

        // ========== INACTIVITY TIMER ==========
        let inactivityTimer;
        const inactivityLimit = 1800000; // 30 minutes

        function resetInactivityTimer() {
            clearTimeout(inactivityTimer);
            inactivityTimer = setTimeout(() => {
                window.location.href = '/DentalEMR_System/php/login/logout.php?uid=<?php echo $userId; ?>&reason=inactivity';
            }, inactivityLimit);
        }

        // Reset timer on user activity
        ['click', 'mousemove', 'keypress', 'scroll', 'touchstart'].forEach(event => {
            document.addEventListener(event, resetInactivityTimer);
        });

        // ========== USER DROPDOWN ==========
        document.getElementById('userDropdownButton').addEventListener('click', function(e) {
            e.stopPropagation();
            const dropdown = document.getElementById('userDropdown');
            dropdown.classList.toggle('hidden');
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (!e.target.closest('#userDropdownButton') && !e.target.closest('#userDropdown')) {
                document.getElementById('userDropdown').classList.add('hidden');
            }
        });

        // ========== SYSTEM LOGS FUNCTIONALITY ==========
        // Global variables
        let currentTab = 'activity';
        let currentPage = 1;
        let totalPages = 1;
        const itemsPerPage = 20;
        let isLoading = false;

        // Initialize when DOM is loaded
        document.addEventListener('DOMContentLoaded', function() {
            initTheme();
            resetInactivityTimer();
            initLogsPage();
        });

        function initLogsPage() {
            console.log('Initializing logs page...');

            // Set date range to show ALL records by default (empty = no filter)
            document.getElementById('dateFrom').value = '';
            document.getElementById('dateTo').value = '';

            // No need to populate filter options - they are static now

            // Set up tab switching
            document.querySelectorAll('[data-tab]').forEach(tab => {
                tab.addEventListener('click', function() {
                    const tabId = this.dataset.tab;
                    switchTab(tabId);
                });
            });

            // Set up filter buttons
            document.getElementById('applyFilters').addEventListener('click', function() {
                showSkeletonLoading();
                setTimeout(() => loadLogs(), 100);
            });

            document.getElementById('clearFilters').addEventListener('click', clearFilters);
            document.getElementById('exportBtn').addEventListener('click', showExportModal);
            document.getElementById('refreshBtn').addEventListener('click', function() {
                showSkeletonLoading();
                setTimeout(() => loadLogs(), 100);
            });

            // Set up pagination
            document.getElementById('prevPage').addEventListener('click', () => changePage(currentPage - 1));
            document.getElementById('nextPage').addEventListener('click', () => changePage(currentPage + 1));

            // Load logs automatically on page load
            setTimeout(() => {
                showSkeletonLoading();
                loadLogs();
            }, 500);
        }

        function switchTab(tabId) {
            if (isLoading) return;

            currentTab = tabId;
            currentPage = 1;

            // Update tab styles
            document.querySelectorAll('[data-tab]').forEach(tab => {
                if (tab.dataset.tab === tabId) {
                    tab.classList.add('border-blue-600', 'text-blue-600', 'dark:text-blue-500', 'dark:border-blue-500');
                    tab.classList.remove('border-transparent');
                } else {
                    tab.classList.remove('border-blue-600', 'text-blue-600', 'dark:text-blue-500', 'dark:border-blue-500');
                    tab.classList.add('border-transparent');
                }
            });

            // Load logs for the new tab
            showSkeletonLoading();
            setTimeout(() => loadLogs(), 100);
        }

        async function populateFilterOptions() {
            try {
                console.log('Populating action filter options...');

                // Only need to populate action types now
                const actionResponse = await fetch('/DentalEMR_System/php/logs/get_actions.php');

                if (actionResponse.ok) {
                    const actionData = await actionResponse.json();
                    const actionSelect = document.getElementById('actionFilter');

                    if (actionData.success && actionData.actions) {
                        // Clear existing options except "All Actions"
                        while (actionSelect.options.length > 1) {
                            actionSelect.remove(1);
                        }

                        actionData.actions.forEach(action => {
                            const option = document.createElement('option');
                            option.value = action;
                            option.textContent = action;
                            actionSelect.appendChild(option);
                        });
                    }
                }
            } catch (error) {
                console.error('Error loading filter options:', error);
            }
        }

        async function loadLogs() {
            if (isLoading) return;

            console.log('Loading logs...');
            isLoading = true;

            const filters = {
                dateFrom: document.getElementById('dateFrom').value,
                dateTo: document.getElementById('dateTo').value,
                userId: document.getElementById('userFilter').value, // Will be '', 'dentist', or 'staff'
                action: document.getElementById('actionFilter').value,
                page: currentPage,
                limit: itemsPerPage,
                logType: currentTab
            };

            console.log('Filters:', filters);

            try {
                const response = await fetch('/DentalEMR_System/php/logs/fetch_logs.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        uid: <?php echo $userId; ?>,
                        filters: filters
                    })
                });

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const data = await response.json();
                console.log('API Response:', data);

                hideSkeletonLoading();

                if (data.success) {
                    if (data.logs.length === 0) {
                        showNoResults();
                    } else {
                        renderLogs(data);
                        showToast('Logs loaded successfully', 'success');
                    }
                } else {
                    showError(data.message || 'Failed to load logs');
                }
            } catch (error) {
                console.error('Error loading logs:', error);
                hideSkeletonLoading();
                showError('Failed to load logs. Please try again.');
            } finally {
                isLoading = false;
            }
        }

        // Helper functions to show/hide states
        function showInitialState() {
            hideAllStates();
            document.getElementById('initialState').classList.remove('hidden');
            document.getElementById('entriesCount').textContent = 'Select filters and click "Apply Filters"';
        }

        function showSkeletonLoading() {
            hideAllStates();
            document.getElementById('skeletonLoading').classList.remove('hidden');
            document.getElementById('entriesCount').textContent = 'Loading logs...';
        }

        function hideSkeletonLoading() {
            document.getElementById('skeletonLoading').classList.add('hidden');
        }

        function showTable() {
            hideAllStates();
            document.getElementById('logsTable').classList.remove('hidden');
            document.getElementById('pagination').classList.remove('hidden');
        }

        function showNoResults() {
            hideAllStates();
            document.getElementById('noResults').classList.remove('hidden');
            document.getElementById('entriesCount').textContent = 'No logs found';
        }

        function showError(message) {
            hideAllStates();
            document.getElementById('errorState').classList.remove('hidden');
            document.getElementById('errorMessage').textContent = message;
            document.getElementById('entriesCount').textContent = 'Error loading logs';
        }

        function hideAllStates() {
            document.getElementById('initialState').classList.add('hidden');
            document.getElementById('loading').classList.add('hidden');
            document.getElementById('skeletonLoading').classList.add('hidden');
            document.getElementById('logsTable').classList.add('hidden');
            document.getElementById('noResults').classList.add('hidden');
            document.getElementById('errorState').classList.add('hidden');
            document.getElementById('pagination').classList.add('hidden');
        }

        function renderLogs(data) {
            const tbody = document.getElementById('logsBody');

            // Use DocumentFragment for better performance
            const fragment = document.createDocumentFragment();

            data.logs.forEach(log => {
                const row = document.createElement('tr');
                row.className = 'bg-white border-b dark:bg-gray-800 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors';
                row.setAttribute('data-log-id', log.id);

                if (currentTab === 'activity') {
                    row.innerHTML = `
                    <td class="px-6 py-4">
                        <div class="font-medium whitespace-nowrap">${formatDateTime(log.timestamp)}</div>
                        <div class="text-xs text-gray-500">${timeAgo(log.timestamp)}</div>
                    </td>
                    <td class="px-6 py-4">
                        <div class="font-medium">${escapeHtml(log.user_name || 'System')}</div>
                        <div class="text-xs text-gray-500">
                            ${log.user_type ? `(${log.user_type})` : ''}
                        </div>
                    </td>
                    <td class="px-6 py-4">
                        <span class="px-2 py-1 text-xs rounded-full ${getActionColor(log.action)}">
                            ${escapeHtml(log.action)}
                        </span>
                    </td>
                    <td class="px-6 py-4">${escapeHtml(log.target || 'N/A')}</td>
                    <td class="px-6 py-4 max-w-xs" title="${escapeHtml(log.details)}">
                        <div class="truncate">${escapeHtml(truncateText(log.details, 50))}</div>
                    </td>
                    <td class="px-6 py-4">
                        <div class="font-mono text-sm">${log.ip_address}</div>
                        <div class="text-xs text-gray-500 truncate max-w-xs" title="${escapeHtml(log.user_agent)}">
                            ${getBrowserInfo(log.user_agent)}
                        </div>
                    </td>
                    <td class="px-6 py-4">
                        <div class="flex space-x-2">
                            <button data-log-id="${log.id}" 
                                    class="view-log-btn px-3 py-1 text-sm bg-blue-100 text-blue-700 rounded hover:bg-blue-200 dark:bg-blue-900 dark:text-blue-300 transition-colors">
                                <i class="fas fa-eye mr-1"></i>View
                            </button>
                            <button data-log-id="${log.id}" 
                                    class="delete-log-btn px-3 py-1 text-sm bg-red-100 text-red-700 rounded hover:bg-red-200 dark:bg-red-900 dark:text-red-300 transition-colors">
                                <i class="fas fa-trash mr-1"></i>Delete
                            </button>
                        </div>
                    </td>
                `;
                } else {
                    // History logs format - show action with appropriate color
                    row.innerHTML = `
                        <td class="px-6 py-4">
                            <div class="font-medium whitespace-nowrap">${formatDateTime(log.timestamp)}</div>
                            <div class="text-xs text-gray-500">${timeAgo(log.timestamp)}</div>
                        </td>
                        <td class="px-6 py-4">
                            <div class="font-medium">${escapeHtml(log.changed_by || 'System')}</div>
                        </td>
                        <td class="px-6 py-4">
                            <span class="px-2 py-1 text-xs rounded-full ${getActionColor(log.action)}">
                                ${escapeHtml(log.action)}
                            </span>
                        </td>
                        <td class="px-6 py-4">
                            <div class="font-medium">${escapeHtml(log.table_name)}</div>
                            <div class="text-xs text-gray-500">Record ID: ${log.record_id}</div>
                        </td>
                        <td class="px-6 py-4 max-w-xs" title="${escapeHtml(log.description)}">
                            <div class="truncate">${escapeHtml(truncateText(log.description, 50))}</div>
                        </td>
                        <td class="px-6 py-4">
                            <div class="font-mono text-sm">${log.ip_address}</div>
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex space-x-2">
                                <button data-log-id="${log.id}" 
                                        class="view-log-btn px-3 py-1 text-sm bg-blue-100 text-blue-700 rounded hover:bg-blue-200 dark:bg-blue-900 dark:text-blue-300 transition-colors">
                                    <i class="fas fa-eye mr-1"></i>View
                                </button>
                                <button data-log-id="${log.id}" 
                                        class="delete-log-btn px-3 py-1 text-sm bg-red-100 text-red-700 rounded hover:bg-red-200 dark:bg-red-900 dark:text-red-300 transition-colors">
                                    <i class="fas fa-trash mr-1"></i>Delete
                                </button>
                            </div>
                        </td>
                    `;
                }

                fragment.appendChild(row);
            });

            // Clear and append in one operation
            tbody.innerHTML = '';
            tbody.appendChild(fragment);

            updatePagination(data);
            showTable();

            // Update entries count
            document.getElementById('entriesCount').textContent = `${data.total} log entries found`;

            // Attach event listeners to the new buttons
            attachEventListenersToButtons();
        }
        async function populateSmartActionFilter() {
            try {
                const response = await fetch('/DentalEMR_System/php/logs/get_all_actions.php');

                if (response.ok) {
                    const data = await response.json();
                    const actionSelect = document.getElementById('actionFilter');

                    if (data.success && data.actions && data.actions.length > 0) {
                        // Clear existing options except "All Actions"
                        while (actionSelect.options.length > 1) {
                            actionSelect.remove(1);
                        }

                        // Group actions by type
                        const actionGroups = {
                            'Session': [],
                            'CRUD': [],
                            'System': [],
                            'Other': []
                        };

                        data.actions.forEach(action => {
                            const actionLower = action.toLowerCase();

                            if (actionLower.includes('login') || actionLower.includes('logout') || actionLower.includes('session')) {
                                actionGroups['Session'].push(action);
                            } else if (actionLower.includes('insert') || actionLower.includes('update') ||
                                actionLower.includes('delete') || actionLower.includes('create') ||
                                actionLower.includes('edit') || actionLower.includes('remove')) {
                                actionGroups['CRUD'].push(action);
                            } else if (actionLower.includes('export') || actionLower.includes('import') ||
                                actionLower.includes('email') || actionLower.includes('deleted')) {
                                actionGroups['System'].push(action);
                            } else {
                                actionGroups['Other'].push(action);
                            }
                        });

                        // Add grouped options
                        Object.keys(actionGroups).forEach(group => {
                            if (actionGroups[group].length > 0) {
                                // Add group label (disabled option)
                                const groupOption = document.createElement('option');
                                groupOption.disabled = true;
                                groupOption.textContent = `── ${group} ──`;
                                actionSelect.appendChild(groupOption);

                                // Add actions in this group
                                actionGroups[group].forEach(action => {
                                    const option = document.createElement('option');
                                    option.value = action;
                                    option.textContent = action;
                                    actionSelect.appendChild(option);
                                });
                            }
                        });

                        console.log('Loaded grouped actions:', actionGroups);
                    }
                }
            } catch (error) {
                console.error('Error loading actions:', error);
            }
        }

        function attachEventListenersToButtons() {
            // View buttons
            document.querySelectorAll('.view-log-btn').forEach(button => {
                button.addEventListener('click', function(e) {
                    e.stopPropagation();
                    const logId = this.getAttribute('data-log-id');
                    if (logId) {
                        viewLogDetails(parseInt(logId));
                    }
                });
            });

            // Delete buttons
            document.querySelectorAll('.delete-log-btn').forEach(button => {
                button.addEventListener('click', function(e) {
                    e.stopPropagation();
                    const logId = this.getAttribute('data-log-id');
                    if (logId) {
                        showDeleteConfirmation(parseInt(logId));
                    }
                });
            });
        }

        // Delete confirmation functions
        let deleteLogId = null;
        let deleteLogType = null;

        function showDeleteConfirmation(logId) {
            deleteLogId = logId;
            deleteLogType = currentTab;

            const row = document.querySelector(`tr[data-log-id="${logId}"]`);
            let logDetails = '';

            if (currentTab === 'activity') {
                const user = row.querySelector('.px-6.py-4:nth-child(2) .font-medium').textContent;
                const action = row.querySelector('.px-6.py-4:nth-child(3) span').textContent;
                const timestamp = row.querySelector('.px-6.py-4:nth-child(1) .font-medium').textContent;
                logDetails = `${action} by ${user} at ${timestamp}`;
            } else {
                const changedBy = row.querySelector('.px-6.py-4:nth-child(2) .font-medium').textContent;
                const action = row.querySelector('.px-6.py-4:nth-child(3) span').textContent;
                const tableName = row.querySelector('.px-6.py-4:nth-child(4) .font-medium').textContent;
                const timestamp = row.querySelector('.px-6.py-4:nth-child(1) .font-medium').textContent;
                logDetails = `${action} on ${tableName} by ${changedBy} at ${timestamp}`;
            }

            document.getElementById('deleteModalTitle').textContent = 'Delete Log Entry';
            document.getElementById('deleteModalMessage').innerHTML = `
            Are you sure you want to delete this log entry?<br><br>
            <strong>${logDetails}</strong><br><br>
            This action cannot be undone.
        `;

            document.getElementById('deleteModal').classList.remove('hidden');
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.add('hidden');
            deleteLogId = null;
            deleteLogType = null;
        }

        async function confirmDelete() {
            if (!deleteLogId || !deleteLogType) return;

            const confirmBtn = document.getElementById('confirmDeleteBtn');
            const originalText = confirmBtn.innerHTML;

            try {
                confirmBtn.disabled = true;
                confirmBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Deleting...';

                const response = await fetch('/DentalEMR_System/php/logs/delete_log.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        uid: <?php echo $userId; ?>,
                        logId: deleteLogId,
                        logType: deleteLogType
                    })
                });

                const data = await response.json();

                if (data.success) {
                    showToast('Log entry deleted successfully', 'success');

                    // Remove the row from the table
                    const row = document.querySelector(`tr[data-log-id="${deleteLogId}"]`);
                    if (row) {
                        row.style.opacity = '0.5';
                        setTimeout(() => {
                            row.remove();
                            updateRowCountAfterDelete();
                        }, 300);
                    }

                    closeDeleteModal();
                } else {
                    showToast(data.message || 'Failed to delete log entry', 'error');
                    confirmBtn.disabled = false;
                    confirmBtn.innerHTML = originalText;
                }
            } catch (error) {
                console.error('Error deleting log:', error);
                showToast('Failed to delete log entry. Please try again.', 'error');
                confirmBtn.disabled = false;
                confirmBtn.innerHTML = originalText;
            }
        }

        function updateRowCountAfterDelete() {
            const totalRows = document.querySelectorAll('#logsBody tr').length;
            const currentTotal = parseInt(document.getElementById('totalRows').textContent) || 0;

            if (totalRows > 0) {
                const newTotal = currentTotal - 1;
                document.getElementById('totalRows').textContent = newTotal;
                document.getElementById('entriesCount').textContent = `${newTotal} log entries found`;

                // Update end row if needed
                const endRow = parseInt(document.getElementById('endRow').textContent) || 0;
                if (endRow > newTotal) {
                    document.getElementById('endRow').textContent = newTotal;
                }

                // If no more rows on current page, go to previous page
                if (totalRows === 0 && currentPage > 1) {
                    currentPage--;
                    loadLogs();
                }
            } else {
                showNoResults();
            }
        }

        // Helper functions
        function getActionColor(action) {
            if (!action) return 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-300';

            const actionLower = action.toLowerCase();

            const colors = {
                // Session Actions
                'login': 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
                'logout': 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300',
                'failed login': 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300',

                // CRUD Actions
                'insert': 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
                'create': 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',

                'update': 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300',
                'edit': 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300',

                'delete': 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300',
                'deleted': 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300',
                'remove': 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300',

                // Email Actions
                'email': 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-300',
                'email failed': 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-300',

                // File Operations
                'export': 'bg-teal-100 text-teal-800 dark:bg-teal-900 dark:text-teal-300',
                'import': 'bg-cyan-100 text-cyan-800 dark:bg-cyan-900 dark:text-cyan-300',

                // Default
                'default': 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-300'
            };

            // Check for exact match
            if (colors[actionLower]) {
                return colors[actionLower];
            }

            // Check for partial matches
            if (actionLower.includes('login')) {
                if (actionLower.includes('failed')) {
                    return colors['failed login'];
                }
                return colors['login'];
            }

            if (actionLower.includes('logout')) {
                return colors['logout'];
            }

            if (actionLower.includes('insert') || actionLower.includes('create')) {
                return colors['insert'];
            }

            if (actionLower.includes('update') || actionLower.includes('edit')) {
                return colors['update'];
            }

            if (actionLower.includes('delete') || actionLower.includes('remove')) {
                return colors['delete'];
            }

            if (actionLower.includes('email')) {
                if (actionLower.includes('failed')) {
                    return colors['email failed'];
                }
                return colors['email'];
            }

            if (actionLower.includes('export')) {
                return colors['export'];
            }

            if (actionLower.includes('import')) {
                return colors['import'];
            }

            return colors['default'];
        }

        function formatDateTime(dateString) {
            const date = new Date(dateString);
            return date.toLocaleString('en-PH', {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        }

        function timeAgo(dateString) {
            const date = new Date(dateString);
            const now = new Date();
            const seconds = Math.floor((now - date) / 1000);

            const intervals = {
                year: 31536000,
                month: 2592000,
                week: 604800,
                day: 86400,
                hour: 3600,
                minute: 60
            };

            for (const [unit, secondsInUnit] of Object.entries(intervals)) {
                const interval = Math.floor(seconds / secondsInUnit);
                if (interval >= 1) {
                    return `${interval} ${unit}${interval === 1 ? '' : 's'} ago`;
                }
            }

            return 'Just now';
        }

        function truncateText(text, maxLength) {
            if (!text) return 'N/A';
            return text.length > maxLength ? text.substring(0, maxLength) + '...' : text;
        }

        function getBrowserInfo(userAgent) {
            if (!userAgent) return 'Unknown';

            if (userAgent.includes('Chrome')) return 'Chrome';
            if (userAgent.includes('Firefox')) return 'Firefox';
            if (userAgent.includes('Safari')) return 'Safari';
            if (userAgent.includes('Edge')) return 'Edge';

            return 'Other';
        }

        async function viewLogDetails(logId) {
            if (isLoading) {
                showToast('Please wait, currently loading...', 'info');
                return;
            }

            try {
                showToast('Loading details...', 'info');

                const response = await fetch('/DentalEMR_System/php/logs/get_log_details.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        uid: <?php echo $userId; ?>,
                        logId: logId,
                        logType: currentTab
                    })
                });

                const data = await response.json();

                if (data.success) {
                    showLogDetailsModal(data.log);
                } else {
                    showToast(data.message || 'Failed to load log details', 'error');
                }
            } catch (error) {
                console.error('Error loading log details:', error);
                showToast('Failed to load log details. Please try again.', 'error');
            }
        }

        function showLogDetailsModal(log) {
            const modalContent = document.getElementById('modalContent');

            if (currentTab === 'activity') {
                modalContent.innerHTML = `
                <div class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400">Timestamp</h4>
                            <p class="font-medium">${formatDateTime(log.timestamp)}</p>
                            <p class="text-sm text-gray-600 dark:text-gray-400">${timeAgo(log.timestamp)}</p>
                        </div>
                        
                        <div class="space-y-2">
                            <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400">User</h4>
                            <p class="font-medium">${escapeHtml(log.user_name || 'System')}</p>
                            <p class="text-sm text-gray-600 dark:text-gray-400">User ID: ${log.user_id || 'N/A'}</p>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400">Action</h4>
                            <span class="px-2 py-1 text-sm rounded-full ${getActionColor(log.action)}">
                                ${escapeHtml(log.action)}
                            </span>
                        </div>
                        
                        <div class="space-y-2">
                            <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400">Target</h4>
                            <p class="font-medium">${escapeHtml(log.target || 'N/A')}</p>
                        </div>
                    </div>
                    
                    <div class="space-y-2">
                        <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400">Details</h4>
                        <div class="bg-gray-50 dark:bg-gray-700 p-3 rounded-lg">
                            <pre class="text-sm whitespace-pre-wrap">${escapeHtml(log.details || 'No details available')}</pre>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400">IP Address</h4>
                            <code class="block font-mono text-sm bg-gray-100 dark:bg-gray-700 p-2 rounded">${log.ip_address || 'N/A'}</code>
                        </div>
                        
                        <div class="space-y-2">
                            <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400">Browser/Device</h4>
                            <div class="bg-gray-50 dark:bg-gray-700 p-2 rounded text-sm">
                                ${escapeHtml(getBrowserInfo(log.user_agent))}
                            </div>
                        </div>
                    </div>
                    
                    ${log.user_agent ? `
                    <div class="space-y-2">
                        <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400">User Agent</h4>
                        <div class="bg-gray-50 dark:bg-gray-700 p-2 rounded text-xs font-mono overflow-x-auto">
                            ${escapeHtml(log.user_agent)}
                        </div>
                    </div>
                    ` : ''}
                </div>
            `;
            } else {
                // History logs modal
                modalContent.innerHTML = `
                <div class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400">Timestamp</h4>
                            <p class="font-medium">${formatDateTime(log.timestamp)}</p>
                            <p class="text-sm text-gray-600 dark:text-gray-400">${timeAgo(log.timestamp)}</p>
                        </div>
                        
                        <div class="space-y-2">
                            <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400">Changed By</h4>
                            <p class="font-medium">${escapeHtml(log.changed_by || 'System')}</p>
                            <p class="text-sm text-gray-600 dark:text-gray-400">Type: ${log.changed_by_type || 'system'}</p>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400">Action</h4>
                            <span class="px-2 py-1 text-sm rounded-full ${getActionColor(log.action)}">
                                ${escapeHtml(log.action)}
                            </span>
                        </div>
                        
                        <div class="space-y-2">
                            <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400">Table & Record</h4>
                            <p class="font-medium">${escapeHtml(log.table_name || 'N/A')}</p>
                            <p class="text-sm text-gray-600 dark:text-gray-400">Record ID: ${log.record_id || 'N/A'}</p>
                        </div>
                    </div>
                    
                    ${log.description ? `
                    <div class="space-y-2">
                        <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400">Description</h4>
                        <div class="bg-gray-50 dark:bg-gray-700 p-3 rounded-lg">
                            <pre class="text-sm whitespace-pre-wrap">${escapeHtml(log.description)}</pre>
                        </div>
                    </div>
                    ` : ''}
                    
                    ${log.old_values || log.new_values ? `
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        ${log.old_values ? `
                        <div class="space-y-2">
                            <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400">Old Values</h4>
                            <div class="bg-gray-50 dark:bg-gray-700 p-3 rounded-lg">
                                <pre class="text-sm whitespace-pre-wrap max-h-40 overflow-y-auto">${formatJSONForDisplay(log.old_values)}</pre>
                            </div>
                        </div>
                        ` : ''}
                        
                        ${log.new_values ? `
                        <div class="space-y-2">
                            <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400">New Values</h4>
                            <div class="bg-gray-50 dark:bg-gray-700 p-3 rounded-lg">
                                <pre class="text-sm whitespace-pre-wrap max-h-40 overflow-y-auto">${formatJSONForDisplay(log.new_values)}</pre>
                            </div>
                        </div>
                        ` : ''}
                    </div>
                    ` : ''}
                    
                    <div class="space-y-2">
                        <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400">IP Address</h4>
                        <code class="block font-mono text-sm bg-gray-100 dark:bg-gray-700 p-2 rounded">${log.ip_address || 'N/A'}</code>
                    </div>
                </div>
            `;
            }

            document.getElementById('detailsModal').classList.remove('hidden');
        }

        function formatJSONForDisplay(jsonString) {
            try {
                if (!jsonString) return 'N/A';
                const parsed = JSON.parse(jsonString);
                return JSON.stringify(parsed, null, 2);
            } catch (e) {
                return jsonString;
            }
        }

        function closeDetailsModal() {
            document.getElementById('detailsModal').classList.add('hidden');
        }

        // Add click handler to close modal when clicking on backdrop
        document.getElementById('detailsModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeDetailsModal();
            }
        });

        // Add click handler for delete modal backdrop
        document.getElementById('deleteModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeDeleteModal();
            }
        });

        // Add Escape key to close modals
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                if (!document.getElementById('detailsModal').classList.contains('hidden')) {
                    closeDetailsModal();
                }
                if (!document.getElementById('deleteModal').classList.contains('hidden')) {
                    closeDeleteModal();
                }
                if (!document.getElementById('exportModal').classList.contains('hidden')) {
                    closeExportModal();
                }
            }
        });

        function escapeHtml(text) {
            if (text === null || text === undefined) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function updatePagination(data) {
            totalPages = Math.ceil(data.total / itemsPerPage);

            document.getElementById('startRow').textContent = ((currentPage - 1) * itemsPerPage) + 1;
            document.getElementById('endRow').textContent = Math.min(currentPage * itemsPerPage, data.total);
            document.getElementById('totalRows').textContent = data.total;

            document.getElementById('entriesCount').textContent = `${data.total} log entries found`;

            // Update page numbers
            const pageNumbers = document.getElementById('pageNumbers');
            pageNumbers.innerHTML = '';

            for (let i = 1; i <= totalPages; i++) {
                const button = document.createElement('button');
                button.className = `px-3 py-1 rounded ${i === currentPage ? 'bg-blue-600 text-white' : 'border hover:bg-gray-50 dark:hover:bg-gray-700'}`;
                button.textContent = i;
                button.addEventListener('click', () => changePage(i));
                pageNumbers.appendChild(button);
            }

            // Update button states
            document.getElementById('prevPage').disabled = currentPage === 1;
            document.getElementById('nextPage').disabled = currentPage === totalPages;

            document.getElementById('pagination').classList.remove('hidden');
        }

        function changePage(page) {
            if (page < 1 || page > totalPages) return;
            currentPage = page;
            showSkeletonLoading();
            setTimeout(() => loadLogs(), 100);
        }

        function clearFilters() {
            // Clear date filters (empty = show all records)
            document.getElementById('dateFrom').value = '';
            document.getElementById('dateTo').value = '';
            document.getElementById('userFilter').value = '';
            document.getElementById('actionFilter').value = '';
            currentPage = 1;

            // Reload logs with cleared filters
            showSkeletonLoading();
            setTimeout(() => loadLogs(), 100);
        }

        function showExportModal() {
            document.getElementById('exportModal').classList.remove('hidden');
        }

        function closeExportModal() {
            document.getElementById('exportModal').classList.add('hidden');
        }

        function proceedExport() {
            const format = document.querySelector('input[name="exportFormat"]:checked').value;
            const filters = {
                dateFrom: document.getElementById('dateFrom').value,
                dateTo: document.getElementById('dateTo').value,
                userId: document.getElementById('userFilter').value,
                action: document.getElementById('actionFilter').value,
                logType: currentTab
            };

            // Trigger download
            const params = new URLSearchParams({
                uid: <?php echo $userId; ?>,
                format: format,
                ...filters
            });

            window.open(`/DentalEMR_System/php/logs/export_logs.php?${params}`, '_blank');
            closeExportModal();
            showToast('Export started', 'success');
        }

        // Toast notification function
        function showToast(message, type = 'info') {
            const toast = document.createElement('div');
            toast.className = `flex items-center p-4 rounded-lg shadow-lg transform transition-all duration-300 translate-x-full ${type === 'error' ? 'bg-red-50 text-red-800 dark:bg-red-900/30 dark:text-red-300' : 'bg-green-50 text-green-800 dark:bg-green-900/30 dark:text-green-300'}`;
            toast.innerHTML = `
            <i class="fas fa-${type === 'error' ? 'exclamation-circle' : 'check-circle'} mr-3"></i>
            <span>${message}</span>
        `;

            const container = document.getElementById('toastContainer');
            container.appendChild(toast);

            // Animate in
            setTimeout(() => {
                toast.classList.remove('translate-x-full');
            }, 10);

            // Remove after delay
            setTimeout(() => {
                toast.classList.add('translate-x-full');
                setTimeout(() => {
                    if (toast.parentNode) {
                        toast.remove();
                    }
                }, 300);
            }, 5000);
        }
    </script>
</body>

</html>