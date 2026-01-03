<?php
session_start();
date_default_timezone_set('Asia/Manila');

// Enhanced security headers
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
// Check if we're in offline mode
$isOfflineMode = isset($_GET['offline']) && $_GET['offline'] === 'true';

// Enhanced session validation with offline support
if ($isOfflineMode) {
    // Offline mode session validation
    $isValidSession = false;

    // Check if we have offline session data
    if (isset($_SESSION['offline_user'])) {
        $loggedUser = $_SESSION['offline_user'];
        $userId = 'offline';
        $isValidSession = true;
    } else {
        // Try to create offline session from localStorage data
        echo "<script>
            document.addEventListener('DOMContentLoaded', function() {
                const checkOfflineSession = () => {
                    try {
                        const sessionData = sessionStorage.getItem('dentalemr_current_user');
                        if (sessionData) {
                            const user = JSON.parse(sessionData);
                            if (user && user.isOffline) {
                                console.log('Valid offline session detected:', user.email);
                                return true;
                            }
                        }
                        
                        const offlineUsers = localStorage.getItem('dentalemr_local_users');
                        if (offlineUsers) {
                            const users = JSON.parse(offlineUsers);
                            if (users && users.length > 0) {
                                console.log('Offline users found in localStorage');
                                return true;
                            }
                        }
                        return false;
                    } catch (error) {
                        console.error('Error checking offline session:', error);
                        return false;
                    }
                };
                
                if (!checkOfflineSession()) {
                    alert('Please log in first for offline access.');
                    window.location.href = '/dentalemr_system/html/login/login.html';
                }
            });
        </script>";

        // Create offline session for this request
        $_SESSION['offline_user'] = [
            'id' => 'offline_user',
            'name' => 'Offline User',
            'email' => 'offline@dentalclinic.com',
            'type' => 'Dentist',
            'isOffline' => true
        ];
        $loggedUser = $_SESSION['offline_user'];
        $userId = 'offline';
        $isValidSession = true;
    }
} else {
    // Online mode - normal session validation
    if (!isset($_GET['uid'])) {
        echo "<script>
            if (!navigator.onLine) {
                // Redirect to same page in offline mode
                window.location.href = '/dentalemr_system/html/treatmentrecords/view_info.php?offline=true&id=" . (isset($_GET['id']) ? $_GET['id'] : '') . "';
            } else {
                alert('Invalid session. Please log in again.');
                window.location.href = '/dentalemr_system/html/login/login.html';
            }
        </script>";
        exit;
    }

    $userId = intval($_GET['uid']);
    $isValidSession = false;

    // CHECK IF THIS USER IS REALLY LOGGED IN
    if (
        isset($_SESSION['active_sessions']) &&
        isset($_SESSION['active_sessions'][$userId])
    ) {
        $userSession = $_SESSION['active_sessions'][$userId];

        // Check basic required fields
        if (isset($userSession['id']) && isset($userSession['type'])) {
            $isValidSession = true;
            // Update last activity
            $_SESSION['active_sessions'][$userId]['last_activity'] = time();
            $loggedUser = $userSession;
        }
    }

    if (!$isValidSession) {
        echo "<script>
            if (!navigator.onLine) {
                // Redirect to same page in offline mode
                window.location.href = '/dentalemr_system/html/treatmentrecords/view_info.php?offline=true&id=" . (isset($_GET['id']) ? $_GET['id'] : '') . "';
            } else {
                alert('Please log in first.');
                window.location.href = '/dentalemr_system/html/login/login.html';
            }
        </script>";
        exit;
    }
}

// PER-USER INACTIVITY TIMEOUT (Online mode only)
if (!$isOfflineMode) {
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

            echo "<script>
                alert('You have been logged out due to inactivity.');
                window.location.href = '/dentalemr_system/html/login/login.html';
            </script>";
            exit;
        }
    }

    // Update last activity timestamp
    $_SESSION['active_sessions'][$userId]['last_activity'] = time();
}

// GET USER DATA FOR PAGE USE
if ($isOfflineMode) {
    $loggedUser = $_SESSION['offline_user'];
} else {
    $loggedUser = $_SESSION['active_sessions'][$userId];
}

// Database connection only for online mode
$conn = null;
if (!$isOfflineMode) {
    $host = "localhost";
    $dbUser = "root";
    $dbPass = "";
    $dbName = "dentalemr_system";

    $conn = new mysqli($host, $dbUser, $dbPass, $dbName);
    if ($conn->connect_error) {
        // If database fails but browser is online, show error
        if (!isset($_GET['offline'])) {
            echo "<script>
                if (navigator.onLine) {
                    alert('Database connection failed. Please try again.');
                    console.error('Database error: " . addslashes($conn->connect_error) . "');
                } else {
                    // Switch to offline mode automatically
                    window.location.href = '/dentalemr_system/html/treatmentrecords/view_info.php?offline=true&id=" . (isset($_GET['id']) ? $_GET['id'] : '') . "';
                }
            </script>";
            exit;
        }
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
}

// Sanitize user data for display
$displayName = htmlspecialchars(
    !empty($loggedUser['name'])
        ? $loggedUser['name']
        : ($loggedUser['email'] ?? 'User'),
    ENT_QUOTES,
    'UTF-8'
);

$displayEmail = htmlspecialchars(
    !empty($loggedUser['email'])
        ? $loggedUser['email']
        : ($loggedUser['name'] ?? 'User'),
    ENT_QUOTES,
    'UTF-8'
);

?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>MHO Dental Clinic - Manage Users</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body class="bg-gray-50 dark:bg-gray-900">
    <div class="antialiased bg-gray-50 dark:bg-gray-900">
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
                    <a href="/dentalemr_system/html/index.php?uid=<?php echo $userId; ?>" class="flex items-center justify-between mr-4">
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
                            <!-- Profile Picture or Icon - UPDATED to use $displayPicture -->
                            <div class="w-8 h-8 rounded-full bg-gray-200 dark:bg-gray-700 flex items-center justify-center overflow-hidden">
                                <?php if (!empty($displayPicture)): ?>
                                    <img src="<?php echo htmlspecialchars($displayPicture); ?>"
                                        alt="Profile"
                                        class="w-full h-full object-cover"
                                        id="navProfilePicture">
                                <?php else: ?>
                                    <i class="fas fa-user text-gray-600 dark:text-gray-400" id="navProfileIcon"></i>
                                <?php endif; ?>
                            </div>

                            <!-- User Info (hidden on mobile) -->
                            <div class="hidden md:block text-left">
                                <div class="text-sm font-medium truncate max-w-[150px]" id="navUserName">
                                    <?php echo htmlspecialchars($loggedUser['name'] ?? 'User'); ?>
                                </div>
                                <div class="text-xs text-gray-500 dark:text-gray-400 truncate max-w-[150px]">
                                    <?php echo htmlspecialchars($loggedUser['type'] ?? 'User Type'); ?>
                                </div>
                            </div>

                            <!-- Dropdown Arrow -->
                            <i class="fas fa-chevron-down text-xs text-gray-500 transition-transform duration-200" id="dropdownArrow"></i>
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
                                <a href="/dentalemr_system/html/manageusers/profile.php?uid=<?php echo $userId; ?>"
                                    class="flex items-center px-4 py-2.5 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700   transition-colors duration-150">
                                    <i class="fas fa-user-circle mr-3 text-gray-500 w-4 text-center"></i>
                                    <span>My Profile</span>
                                </a>
                                <a href="#"
                                    class="flex items-center px-4 py-2.5 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 dark:bg-blue-900/20 transition-colors duration-150">
                                    <i class="fas fa-users-cog mr-3 text-blue-500 w-4 text-center"></i>
                                    <span>Manage Users</span>
                                </a>
                                <a href="/dentalemr_system/html/manageusers/systemlogs.php?uid=<?php echo $userId; ?>"
                                    class="flex items-center px-4 py-2.5 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700  transition-colors duration-150">
                                    <i class="fas fa-history mr-3 text-gray-500 w-4 text-center"></i>
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
                                <a href="/dentalemr_system/php/login/logout.php?uid=<?php echo $loggedUser['id']; ?>"
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
            <h1 class="text-xl text-center w-full font-bold dark:text-white">Manage Users</h1>
            <section id="dentist" class="bg-gray-50 dark:bg-gray-900 p-3 sm:p-5">
                <div class="mx-auto max-w-screen-xl px-4 lg:px-12">
                    <div class="bg-white dark:bg-gray-800 relative shadow-md sm:rounded-lg">
                        <div>
                            <p class="text-2xl py-2 font-semibold px-5 mt-5 text-gray-900 dark:text-white">Staff list</p>
                        </div>
                        <div class="flex flex-col md:flex-row items-center justify-between space-y-3 md:space-y-0 md:space-x-4 p-4">
                            <div class="w-full md:w-1/2">
                                <form class="flex items-center">
                                    <label for="simple-search" class="sr-only">Search</label>
                                    <div class="relative w-full">
                                        <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                            <svg aria-hidden="true" class="w-5 h-5 text-gray-500 dark:text-gray-400" fill="currentColor" viewbox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd" />
                                            </svg>
                                        </div>
                                        <input type="text" id="simple-search" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full pl-10 p-2 dark:bg-gray-700 dark:border-blue-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500" placeholder="Search" required>
                                    </div>
                                </form>
                            </div>
                            <div class="w-full md:w-auto flex flex-col md:flex-row space-y-2 md:space-y-0 items-stretch md:items-center justify-end md:space-x-3 flex-shrink-0">
                                <button type="button" id="Adddentistbtn" data-modal-target="addDentistModal" data-modal-toggle="addDentistModal" class="flex items-center justify-center cursor-pointer text-white bg-blue-700 hover:bg-blue-800 font-medium rounded-lg text-sm px-4 py-2 dark:bg-blue-600 dark:hover:bg-blue-700">
                                    <svg class="h-3.5 w-3.5 mr-2" fill="currentColor" viewbox="0 0 20 20" aria-hidden="true">
                                        <path clip-rule="evenodd" fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" />
                                    </svg>
                                    Add Staff
                                </button>
                            </div>
                        </div>
                        <!-- Table -->
                        <div class="overflow-x-auto">
                            <table id="patientsTable" class="w-full text-sm text-left text-gray-500 dark:text-gray-400">
                                <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
                                    <tr>
                                        <th class="px-4 py-3 text-center">Name</th>
                                        <th class="px-4 py-3 text-center">Username</th>
                                        <th class="px-4 py-3 text-center">Email</th>
                                        <th class="px-4 py-3 text-center">Created At</th>
                                        <th class="px-4 py-3 text-center">Updated At</th>
                                        <th class="px-4 py-3 text-center">Email Status</th>
                                        <th class="px-4 py-3 text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="staffBody">
                                    <tr class="border-b dark:border-gray-700 border-gray-200">
                                        <td class="px-4 py-3 text-center font-medium text-gray-900 whitespace-nowrap dark:text-white">Loading ...</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Add Staff Modal -->
            <div id="addDentistModal" tabindex="-1" aria-hidden="true" class="hidden overflow-y-auto overflow-x-hidden fixed top-0 right-0 left-0 z-50 justify-center items-center w-full md:inset-0 h-modal md:h-full">
                <div class="relative p-4 w-full max-w-2xl h-full md:h-auto">
                    <div class="relative p-4 bg-white rounded-lg shadow dark:bg-gray-800 sm:p-5">
                        <div class="flex justify-between items-center pb-4 mb-4 rounded-t border-b sm:mb-5 dark:border-gray-600">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Add Staff</h3>
                            <button type="button" class="text-gray-400 bg-transparent cursor-pointer hover:bg-gray-200 hover:text-gray-900 rounded-lg text-sm p-1.5 ml-auto inline-flex items-center dark:hover:bg-gray-600 dark:hover:text-white" data-modal-toggle="addDentistModal">
                                <svg aria-hidden="true" class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                                </svg>
                                <span class="sr-only">Close modal</span>
                            </button>
                        </div>
                        <form id="addStaffForm" action="/dentalemr_system/php/manageusers/add_staff.php?uid=<?php echo $userId; ?>" method="POST">
                            <input type="hidden" name="userType" value="Staff">
                            <div class="grid gap-4 mb-4 sm:grid-cols-2">
                                <div class="sm:col-span-2">
                                    <label for="staffname" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Name</label>
                                    <input type="text" name="fullname" id="staffname" required class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-600 focus:border-primary-600 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500">
                                </div>
                                <div>
                                    <label for="staffusername" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Username</label>
                                    <input type="text" name="username" id="staffusername" required class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-600 focus:border-primary-600 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500">
                                </div>
                                <div>
                                    <label for="staffemail" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Email</label>
                                    <input type="email" name="email" id="staffemail" required class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-600 focus:border-primary-600 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500">
                                </div>
                                <div>
                                    <label for="staffpassword" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Password</label>
                                    <input type="password" name="password" id="staffpassword" required minlength="6" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-600 focus:border-primary-600 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500">
                                </div>
                                <div>
                                    <label for="staffconfirm" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Confirm Password</label>
                                    <input type="password" name="confirm_password" id="staffconfirm" required minlength="6" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-600 focus:border-primary-600 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500">
                                </div>
                            </div>
                            <div id="formErrors" class="hidden mb-4 p-3 text-sm text-red-800 rounded-lg bg-red-50 dark:bg-gray-800 dark:text-red-400"></div>
                            <button type="submit" class="text-white inline-flex items-center cursor-pointer bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:outline-none focus:ring-blue-300 font-medium rounded-lg text-sm px-5 py-2.5 text-center dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800">
                                <svg class="mr-1 -ml-1 w-6 h-6" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 5a1 1 0 011 1v3h3a1 1 0 110 2h-3v3a1 1 0 11-2 0v-3H6a1 1 0 110-2h3V6a1 1 0 011-1z" clip-rule="evenodd"></path>
                                </svg>
                                Add Account
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/flowbite@3.1.2/dist/flowbite.min.js"></script>
    <script src="../../js/tailwind.config.js"></script>
    <!-- Theme Toggle Script -->
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

        // Initialize theme when DOM is loaded
        document.addEventListener('DOMContentLoaded', function() {
            initTheme();

            // Also update dropdown visibility based on theme
            const dropdown = document.getElementById('dropdown');
            const userMenuButton = document.getElementById('user-menu-button');

            if (userMenuButton && dropdown) {
                userMenuButton.addEventListener('click', function() {
                    dropdown.classList.toggle('hidden');
                });

                // Close dropdown when clicking outside
                document.addEventListener('click', function(event) {
                    if (!userMenuButton.contains(event.target) && !dropdown.contains(event.target)) {
                        dropdown.classList.add('hidden');
                    }
                });
            }
        });
    </script>
    <!-- Enhanced Client-side inactivity logout -->
    <script>
        let inactivityTime = 1800000; // 10 minutes in ms
        let logoutTimer;

        function resetTimer() {
            clearTimeout(logoutTimer);
            logoutTimer = setTimeout(() => {
                if (confirm("You've been inactive for 30 minutes. Would you like to stay logged in?")) {
                    resetTimer();
                } else {
                    window.location.href = "/dentalemr_system/php/login/logout.php?uid=<?php echo $userId; ?>";
                }
            }, inactivityTime);
        }

        ["click", "mousemove", "keypress", "scroll", "touchstart"].forEach(evt => {
            document.addEventListener(evt, resetTimer, false);
        });

        resetTimer();
    </script>
    <script>
        // Toggle user dropdown menu
        document.addEventListener('DOMContentLoaded', function() {
            const dropdownButton = document.getElementById('userDropdownButton');
            const dropdownMenu = document.getElementById('userDropdown');
            const dropdownArrow = document.getElementById('dropdownArrow');

            if (dropdownButton && dropdownMenu) {
                // Toggle dropdown on button click
                dropdownButton.addEventListener('click', function(e) {
                    e.stopPropagation();
                    dropdownMenu.classList.toggle('hidden');
                    dropdownMenu.classList.toggle('opacity-0');
                    dropdownMenu.classList.toggle('opacity-100');
                    dropdownMenu.classList.toggle('scale-95');
                    dropdownMenu.classList.toggle('scale-100');

                    // Rotate arrow
                    dropdownArrow.classList.toggle('rotate-180');
                });

                // Close dropdown when clicking outside
                document.addEventListener('click', function(e) {
                    if (!dropdownButton.contains(e.target) && !dropdownMenu.contains(e.target)) {
                        dropdownMenu.classList.add('hidden', 'opacity-0', 'scale-95');
                        dropdownMenu.classList.remove('opacity-100', 'scale-100');
                        dropdownArrow.classList.remove('rotate-180');
                    }
                });

                // Close dropdown on Escape key
                document.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape' && !dropdownMenu.classList.contains('hidden')) {
                        dropdownMenu.classList.add('hidden', 'opacity-0', 'scale-95');
                        dropdownMenu.classList.remove('opacity-100', 'scale-100');
                        dropdownArrow.classList.remove('rotate-180');
                    }
                });
            }
        });

        // Toggle password visibility
        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const icon = input.parentElement.querySelector('button i');

            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.replace('fa-eye-slash', 'fa-eye');
            }
        }

        // Preview profile picture and update navigation immediately
        function previewProfilePicture(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const preview = document.getElementById('profilePicturePreview');
                    preview.src = e.target.result;

                    // Update navigation bar profile picture
                    const navProfileImg = document.getElementById('navProfilePicture');
                    const navProfileIcon = document.getElementById('navProfileIcon');

                    if (navProfileImg) {
                        navProfileImg.src = e.target.result;
                    } else if (navProfileIcon) {
                        // Replace icon with image
                        navProfileIcon.style.display = 'none';
                        const img = document.createElement('img');
                        img.src = e.target.result;
                        img.alt = 'Profile';
                        img.className = 'w-full h-full object-cover';
                        img.id = 'navProfilePicture';
                        navProfileIcon.parentElement.appendChild(img);
                    }

                    showNotification('Profile picture preview updated. Click Save Changes to apply.', 'success');
                }
                reader.readAsDataURL(input.files[0]);
            }
        }

        // Function to update navigation after successful profile update
        function updateNavigationAfterProfileUpdate(newName, newEmail, newProfilePicture = null) {
            // Update user name in navigation
            const navUserName = document.getElementById('navUserName');
            const dropdownUserName = document.getElementById('dropdownUserName');

            if (navUserName) navUserName.textContent = newName;
            if (dropdownUserName) dropdownUserName.textContent = newName;

            // Update profile picture in navigation if changed
            if (newProfilePicture) {
                const navProfileImg = document.getElementById('navProfilePicture');
                const navProfileIcon = document.getElementById('navProfileIcon');

                if (navProfileImg) {
                    navProfileImg.src = newProfilePicture;
                } else if (navProfileIcon) {
                    // Replace icon with image
                    navProfileIcon.style.display = 'none';
                    const img = document.createElement('img');
                    img.src = newProfilePicture;
                    img.alt = 'Profile';
                    img.className = 'w-full h-full object-cover';
                    img.id = 'navProfilePicture';
                    navProfileIcon.parentElement.appendChild(img);
                }
            }

            // Update main profile picture to ensure consistency
            const mainProfilePic = document.getElementById('profilePicturePreview');
            if (mainProfilePic && newProfilePicture) {
                mainProfilePic.src = newProfilePicture;
            }
        }

        // Reset form
        function resetForm() {
            if (confirm('Are you sure you want to reset all changes?')) {
                document.querySelector('form').reset();
                showNotification('Form has been reset to original values.', 'info');
            }
        }

        // Show/Hide help modal
        function showHelp() {
            document.getElementById('helpModal').classList.remove('hidden');
        }

        function hideHelp() {
            document.getElementById('helpModal').classList.add('hidden');
        }

        // Inactivity timer
        let inactivityTimer;
        const inactivityLimit = 600000; // 10 minutes

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

        // Notification function
        function showNotification(message, type = 'info') {
            // Remove existing notification
            const existing = document.querySelector('.notification-toast');
            if (existing) existing.remove();

            const notification = document.createElement('div');
            notification.className = `notification-toast fixed top-4 right-4 px-6 py-3 rounded-xl shadow-lg z-50 transform translate-x-full opacity-0 transition-all duration-300 ${
                type === 'error' ? 'bg-red-500 text-white' : 
                type === 'success' ? 'bg-green-500 text-white' : 
                'bg-blue-500 text-white'
            }`;
            notification.innerHTML = `
                <div class="flex items-center">
                    <i class="fas ${type === 'error' ? 'fa-exclamation-circle' : type === 'success' ? 'fa-check-circle' : 'fa-info-circle'} mr-2"></i>
                    <span>${message}</span>
                </div>
            `;
            document.body.appendChild(notification);

            setTimeout(() => {
                notification.classList.remove('translate-x-full', 'opacity-0');
                notification.classList.add('translate-x-0', 'opacity-100');
            }, 10);

            setTimeout(() => {
                notification.classList.remove('translate-x-0', 'opacity-100');
                notification.classList.add('translate-x-full', 'opacity-0');
                setTimeout(() => notification.remove(), 300);
            }, 3000);
        }

        // Form validation
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function(e) {
                const inputs = this.querySelectorAll('input[required]');
                let valid = true;

                inputs.forEach(input => {
                    if (!input.value.trim()) {
                        input.classList.add('border-red-500', 'bg-red-50', 'dark:bg-red-900/20');
                        valid = false;
                    } else {
                        input.classList.remove('border-red-500', 'bg-red-50', 'dark:bg-red-900/20');
                    }
                });

                if (!valid) {
                    e.preventDefault();
                    showNotification('Please fill in all required fields marked with *.', 'error');
                }
            });
        });

        // Add responsive classes on resize
        window.addEventListener('resize', function() {
            const width = window.innerWidth;
            const cards = document.querySelectorAll('.card-hover');

            if (width < 768) {
                cards.forEach(card => {
                    card.classList.remove('card-hover');
                });
            } else {
                cards.forEach(card => {
                    card.classList.add('card-hover');
                });
            }
        });

        // Initialize
        window.addEventListener('load', function() {
            // Check screen size on load
            window.dispatchEvent(new Event('resize'));

            // Smooth scroll to top
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });

        // Close help modal on ESC key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                hideHelp();
            }
        });

        // Close help modal when clicking outside
        document.getElementById('helpModal').addEventListener('click', function(e) {
            if (e.target === this) {
                hideHelp();
            }
        });

        // Auto-update navigation when page loads (in case of form submission)
        window.addEventListener('load', function() {
            // Check if we have success message (meaning form was submitted)
            const successMsg = document.querySelector('.text-green-700, .text-green-300');
            if (successMsg && successMsg.textContent.includes('Profile updated')) {
                // The page will reload with new data, but we can force a small delay to ensure DOM is ready
                setTimeout(() => {
                    // Update navigation with current data
                    const userName = document.querySelector('input[name="name"]').value;
                    const navUserName = document.getElementById('navUserName');
                    const dropdownUserName = document.getElementById('dropdownUserName');

                    if (navUserName) navUserName.textContent = userName;
                    if (dropdownUserName) dropdownUserName.textContent = userName;
                }, 100);
            }
        });
    </script>
    <!-- Enhanced Staff Management Script -->
    <script>
        let staffData = [];
        const currentUserId = <?php echo $userId; ?>;

        function loadStaffList() {
            // ADD CACHE BUSTER TO URL
            const cacheBuster = '?_=' + new Date().getTime();
            fetch("/dentalemr_system/php/manageusers/get_staff.php" + cacheBuster)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok: ' + response.status);
                    }
                    return response.json();
                })
                .then(responseData => {
                    console.log('Full API Response:', responseData);

                    // Check if we have the new response structure
                    let data = responseData;
                    if (responseData.data) {
                        data = responseData.data;
                        console.log('Debug Info:', responseData.debug);
                    }

                    if (responseData.error) {
                        throw new Error(responseData.error);
                    }

                    if (!Array.isArray(data)) {
                        console.error('Expected array but got:', typeof data);
                        data = [];
                    }

                    // FILTER OUT THE GHOST RECORD
                    const filteredData = data.filter(row => {
                        // Filter out the specific ghost record
                        const isGhostRecord = row.email === 'jayjaypanganiban40@gmail.com' ||
                            (row.name === 'Jay Jay Panganiban' && row.username === 'jayjay');

                        if (isGhostRecord) {
                            console.warn('Filtered out ghost record:', row);
                            return false;
                        }
                        return true;
                    });

                    staffData = filteredData;

                    // Also log what's in the database for comparison
                    console.log('Database Records:', data);
                    console.log('Filtered Records:', staffData);

                    renderStaffTable(staffData);
                })
                .catch(error => {
                    console.error('Error loading staff list:', error);
                    document.getElementById("staffBody").innerHTML = `
                <tr>
                    <td colspan="7" class="p-4 text-center text-red-600">
                        Error loading staff list. Please refresh the page.<br>
                        <small>${error.message}</small>
                    </td>
                </tr>`;
                });
        }

        function renderStaffTable(data) {
            const tbody = document.getElementById("staffBody");
            if (!tbody) {
                console.error('Staff body element not found');
                return;
            }

            tbody.innerHTML = "";

            if (!Array.isArray(data) || data.length === 0) {
                tbody.innerHTML = `
            <tr>
                <td colspan="7" class="p-4 text-center text-gray-500 dark:text-gray-300">
                    No staff accounts found.
                </td>
            </tr>`;
                return;
            }

            const fragment = document.createDocumentFragment();

            data.forEach((row, index) => {
                // Log each row being rendered
                console.log(`Rendering row ${index}:`, row);

                const tr = document.createElement("tr");
                tr.className = "border-b border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700";

                // Sanitize data for display
                const name = escapeHtml(row.name || 'N/A');
                const username = escapeHtml(row.username || 'N/A');
                const email = escapeHtml(row.email || 'N/A');
                const created = escapeHtml(formatDateTime(row.created_at) || 'N/A');
                const updated = escapeHtml(formatDateTime(row.updated_at) || 'N/A');

                // Email status indicator
                let emailStatus = '';
                const emailSent = parseInt(row.welcome_email_sent) || 0;
                const emailSentAt = row.email_sent_at;

                if (emailSent === 1) {
                    emailStatus = `<span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300">
                    <i class="fas fa-check-circle"></i> Sent
                </span>`;
                } else if (emailSentAt) {
                    emailStatus = `<span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300">
                    <i class="fas fa-exclamation-triangle"></i> Failed
                </span>`;
                } else {
                    emailStatus = `<span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300">
                    <i class="fas fa-info-circle"></i> Pending
                </span>`;
                }

                tr.innerHTML = `
            <td class="px-4 py-3 text-center text-gray-900 dark:text-white">${name}</td>
            <td class="px-4 py-3 text-center">${username}</td>
            <td class="px-4 py-3 text-center">${email}</td>
            <td class="px-4 py-3 text-center">${created}</td>
            <td class="px-4 py-3 text-center">${updated}</td>
            <td class="px-4 py-3 text-center">${emailStatus}</td>
            <td class="px-4 py-3 text-center">
                <div class="flex justify-center space-x-2">
                    <button onclick="deleteStaff(${row.id}, '${escapeSingleQuotes(name)}')" 
                        class="text-white bg-red-600 hover:bg-red-700 px-3 py-1 rounded-sm cursor-pointer text-sm transition-colors duration-200">
                        <i class="fas fa-trash"></i> Delete
                    </button>
                </div>
            </td>`;

                fragment.appendChild(tr);
            });

            tbody.appendChild(fragment);
        }

        // Delete function
        function deleteStaff(staffId, staffName) {
            if (!confirm(`Are you sure you want to delete "${staffName}"? This action cannot be undone.`)) {
                return;
            }

            // Add cache buster to delete URL too
            const cacheBuster = '&_=' + new Date().getTime();
            window.location.href = `/dentalemr_system/php/manageusers/delete_staff.php?id=${staffId}&uid=${currentUserId}${cacheBuster}`;
        }

        // Add resend credentials function
        function resendCredentials(staffId, email) {
            if (!confirm(`Resend credentials email to ${email}?`)) {
                return;
            }

            // Show loading state
            const button = event.target.closest('button');
            const originalHtml = button.innerHTML;
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
            button.disabled = true;

            fetch(`/dentalemr_system/php/manageusers/resend_credentials.php?id=${staffId}&uid=${currentUserId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('✓ Credentials email resent successfully!');
                        loadStaffList(); // Reload to update status
                    } else {
                        alert('✗ Failed to resend email: ' + (data.error || 'Unknown error'));
                        button.innerHTML = originalHtml;
                        button.disabled = false;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('✗ Failed to resend email. Please try again.');
                    button.innerHTML = originalHtml;
                    button.disabled = false;
                });
        }

        // Form validation
        document.getElementById('addStaffForm')?.addEventListener('submit', function(e) {
            const password = document.getElementById('staffpassword').value;
            const confirmPassword = document.getElementById('staffconfirm').value;
            const errorDiv = document.getElementById('formErrors');

            if (password !== confirmPassword) {
                e.preventDefault();
                errorDiv.textContent = 'Passwords do not match.';
                errorDiv.classList.remove('hidden');
                return;
            }

            if (password.length < 6) {
                e.preventDefault();
                errorDiv.textContent = 'Password must be at least 6 characters long.';
                errorDiv.classList.remove('hidden');
                return;
            }

            errorDiv.classList.add('hidden');
        });

        // Live search with debounce
        let searchTimeout;
        const searchInput = document.getElementById("simple-search");
        if (searchInput) {
            searchInput.addEventListener("input", function(e) {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    const term = e.target.value.toLowerCase().trim();
                    const filtered = staffData.filter(row =>
                        (row.name?.toLowerCase().includes(term) ||
                            row.username?.toLowerCase().includes(term) ||
                            row.email?.toLowerCase().includes(term))
                    );
                    renderStaffTable(filtered);
                }, 300);
            });
        }

        // Utility function to escape HTML
        function escapeHtml(unsafe) {
            if (unsafe === null || unsafe === undefined) return '';
            return String(unsafe)
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }

        // Utility to escape single quotes for JavaScript strings
        function escapeSingleQuotes(str) {
            return String(str).replace(/'/g, "\\'");
        }

        // Format date-time for display
        function formatDateTime(dateTimeStr) {
            if (!dateTimeStr) return '';
            try {
                const date = new Date(dateTimeStr);
                return date.toLocaleString('en-PH', {
                    year: 'numeric',
                    month: 'short',
                    day: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit'
                });
            } catch (e) {
                return dateTimeStr;
            }
        }

        // Initialize on load
        document.addEventListener("DOMContentLoaded", () => {
            console.log('Current User ID:', currentUserId); // Debug log
            loadStaffList();

            // Refresh staff list every 30 seconds to catch updates
            setInterval(loadStaffList, 30000);
        });
    </script>
</body>

</html>