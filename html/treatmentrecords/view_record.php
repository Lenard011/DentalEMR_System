<?php
session_start();
date_default_timezone_set('Asia/Manila');

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

    // Fetch dentist name if user is a dentist (only in online mode)
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
?>
<!doctype html>
<html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Treatment Records</title>
    <!-- <link href="../css/style.css" rel="stylesheet"> -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body class="bg-gray-50 dark:bg-gray-900">
    <div class="antialiased bg-gray-50 dark:bg-gray-900">
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
                        <svg aria-hidden="true" class="hidden w-6 h-6" fill="currentColor" viewBox="0 0 20 20"
                            xmlns="http://www.w3.org/2000/svg">
                            <path fill-rule="evenodd"
                                d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                clip-rule="evenodd"></path>
                        </svg>
                        <span class="sr-only">Toggle sidebar</span>
                    </button>
                    <a href="#" class="flex items-center justify-between mr-4 ">
                        <img src="https://th.bing.com/th/id/OIP.zjh8eiLAHY9ybXUCuYiqQwAAAA?r=0&rs=1&pid=ImgDetMain&cb=idpwebp1&o=7&rm=3"
                            class="mr-3 h-8 rounded-full" alt="MHO Logo" />
                        <span class="self-center text-2xl font-semibold whitespace-nowrap dark:text-white">MHO Dental Clinic</span>
                    </a>

                    <?php if ($isOfflineMode): ?>
                        <div class="ml-4 px-3 py-1 bg-orange-100 dark:bg-orange-900/30 border border-orange-200 dark:border-orange-800 rounded-lg flex items-center gap-2">
                            <i class="fas fa-wifi-slash text-orange-600 dark:text-orange-400 text-sm"></i>
                            <span class="text-sm font-medium text-orange-800 dark:text-orange-300">Offline Mode</span>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- User Profile -->
                <div class="flex items-center space-x-3">
                    <?php if ($isOfflineMode): ?>
                        <button onclick="syncOfflineData()"
                            class="bg-orange-500 text-white px-4 py-2 rounded-lg hover:bg-orange-600 transition-colors flex items-center gap-2 text-sm">
                            <i class="fas fa-sync"></i>
                            Sync When Online
                        </button>
                    <?php endif; ?>

                    <!-- User Dropdown -->
                    <div class="relative">
                        <button type="button" id="user-menu-button" aria-expanded="false" data-dropdown-toggle="dropdown"
                            class="flex items-center space-x-2 p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700">
                            <div class="w-8 h-8 rounded-full bg-gray-200 dark:bg-gray-700 flex items-center justify-center overflow-hidden">
                                <?php if (!empty($loggedUser['profile_picture'])): ?>
                                    <img src="<?php echo htmlspecialchars($loggedUser['profile_picture']); ?>" alt="Profile" class="w-full h-full object-cover">
                                <?php else: ?>
                                    <i class="fas fa-user text-gray-600 dark:text-gray-400"></i>
                                <?php endif; ?>
                            </div>
                            <div class="text-left">
                                <div class="text-sm font-medium truncate max-w-[150px] dark:text-white">
                                    <?php
                                    echo htmlspecialchars(
                                        !empty($loggedUser['name'])
                                            ? $loggedUser['name']
                                            : ($loggedUser['email'] ?? 'User')
                                    );
                                    ?>
                                    <?php if ($isOfflineMode): ?>
                                        <span class="text-orange-600 text-xs">(Offline)</span>
                                    <?php endif; ?>
                                </div>
                                <div class="text-xs text-gray-500 dark:text-white truncate max-w-[150px]">
                                    <?php
                                    echo htmlspecialchars(
                                        !empty($loggedUser['email'])
                                            ? $loggedUser['email']
                                            : ($loggedUser['name'] ?? 'User')
                                    );
                                    ?>
                                </div>
                            </div>
                            <i class="fas fa-chevron-down text-xs text-gray-500"></i>
                        </button>

                        <!-- Dropdown Menu -->
                        <div id="dropdown" class="absolute right-0 mt-2 w-64 bg-white dark:bg-gray-800 rounded-lg shadow-lg border border-gray-200 dark:border-gray-700 hidden z-50">
                            <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                                <div class="text-sm font-semibold dark:text-white">
                                    <?php
                                    echo htmlspecialchars(
                                        !empty($loggedUser['name'])
                                            ? $loggedUser['name']
                                            : ($loggedUser['email'] ?? 'User')
                                    );
                                    ?>
                                    <?php if ($isOfflineMode): ?>
                                        <span class="text-orange-600 text-xs">(Offline)</span>
                                    <?php endif; ?>
                                </div>
                                <div class="text-xs text-gray-600 dark:text-white mt-1 ">
                                    <?php
                                    echo htmlspecialchars(
                                        !empty($loggedUser['email'])
                                            ? $loggedUser['email']
                                            : ($loggedUser['name'] ?? 'User')
                                    );
                                    ?>
                                </div>
                            </div>
                            <div class="py-2">
                                <a  href="/dentalemr_system/html/manageusers/profile.php?uid=<?php echo $userId; ?>"
                                    class="flex items-center px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700">
                                    <i class="fas fa-user-circle mr-3 text-gray-500 dark:text-gray-400"></i>
                                    My Profile
                                </a>
                                <a href="/dentalemr_system/html/manageusers/manageuser.php?uid=<?php echo $userId;
                                                                                                echo $isOfflineMode ? '&offline=true' : ''; ?>"
                                    class="flex items-center px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700">
                                    <i class="fas fa-users-cog mr-3 text-gray-500 dark:text-gray-400"></i>
                                    Manage Users
                                </a>
                                <a href="/dentalemr_system/html/manageusers/systemlogs.php?uid=<?php echo $userId;
                                                                                                echo $isOfflineMode ? '&offline=true' : ''; ?>"
                                    class="flex items-center px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700">
                                    <i class="fas fa-history mr-3 text-gray-500 dark:text-gray-400"></i>
                                    System Logs
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

                            <!-- Sign Out -->
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
                                    class="pl-11 flex items-center p-2 text-base font-medium text-blue-600 rounded-lg dark:text-blue bg-blue-100   group">Treatment
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

        <header class="md:ml-64 pt-20 ">
            <nav class="bg-white border-gray-200 dark:bg-gray-800 w-full drop-shadow-sm pb-2">
                <div class="flex flex-col justify-between items-center mx-auto px-2 sm:px-4">
                    <div class="flex items-center justify-between w-full py-2">
                        <!-- Back Btn-->
                        <div lass="relative group inline-block">
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
                    <div class="w-full border-t border-gray-200 dark:border-gray-700 pt-2"
                        id="mobile-menu-2">
                        <ul class="flex flex-col sm:flex-row justify-center font-medium w-full sm:space-x-4 sm:space-y-0 space-y-2">
                            <li>
                                <a href="#" id="patientInfoLink"
                                    class="block py-2 px-3 text-gray-700 border-b font-semibold border-gray-100 hover:bg-gray-50 lg:hover:bg-transparent lg:border-0 lg:hover:text-primary-700 lg:p-0 dark:text-gray-400 lg:dark:hover:text-white dark:hover:bg-gray-700 dark:hover:text-white lg:dark:hover:bg-transparent dark:border-gray-700 text-center sm:text-left">Patient
                                    Information</a>
                            </li>
                            <li>
                                <a href="#" id="oralHealthLink"
                                    class="block py-2 px-3 text-gray-700 border-b font-semibold border-gray-100 hover:bg-gray-50 lg:hover:bg-transparent lg:border-0 lg:hover:text-primary-700 lg:p-0 dark:text-gray-400 lg:dark:hover:text-white dark:hover:bg-gray-700 dark:hover:text-white lg:dark:hover:bg-transparent dark:border-gray-700 text-center sm:text-left">Oral
                                    Health Condition</a>
                            </li>
                            <li>
                                <a href="#"
                                    class="block py-2 px-3 text-blue-800 border-b-2 font-semibold border-blue-800 hover:bg-gray-50 lg:hover:bg-transparent lg:border-0 lg:hover:text-primary-700 lg:p-0 dark:text-blue-400 lg:dark:hover:text-white dark:hover:bg-gray-700 dark:hover:text-white lg:dark:hover:bg-transparent dark:border-gray-700 text-center sm:text-left">Record
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
                <div
                    class="mx-auto flex flex-col justify-center items-center max-w-screen-xl px-1.5 py-2 lg:px-1.5 bg-white rounded-lg shadow dark:border shadow-stone-300 drop-shadow-sm dark:bg-gray-800 dark:border-gray-950">
                    <div class="items-center justify-between flex flex-row w-full">
                        <p class="text-base font-normal text-gray-950 dark:text-white ">Record of Services Rendered</p>
                    </div>
                    <!-- Table -->
                    <div class="mx-auto max-w-screen-xl w-full px-4 lg:px-12 mt-10 mb-10">
                        <!-- Start coding here -->
                        <div class="bg-white dark:bg-gray-800 relative shadow-md sm:rounded-lg overflow-hidden">

                            <!-- Table -->
                            <div class="overflow-x-auto">
                                <table class="w-full text-sm text-left text-gray-500 dark:text-gray-400">
                                    <thead
                                        class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
                                        <tr class="">
                                            <th scope="col" class="px-4 py-3 text-center">Date</th>
                                            <th scope="col" class="px-4 py-3 text-center">Oral Prophylaxis</th>
                                            <th scope="col" class="px-4 py-3 text-center">Flouride Varnish / Flouride
                                                Gel</th>
                                            <th scope="col" class="px-4 py-3 text-center">Pit Fissure Sealant</th>
                                            <th scope="col" class="px-4 py-3 text-center">Permanent Filling</th>
                                            <th scope="col" class="px-4 py-3 text-center">Temporary Filling</th>
                                            <th scope="col" class="px-4 py-3 text-center">Extraction</th>
                                            <th scope="col" class="px-4 py-3 text-center">Consulation</th>
                                            <th scope="col" class="px-4 py-3 text-center">Remarks / Others (Specify)
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody id="treatmentTableBody">
                                        <tr class="border-b dark:border-gray-700 dark:text-white">
                                            <td colspan="9"
                                                class="px-4 py-3 text-center font-medium text-gray-900 whitespace-nowrap dark:text-white">
                                                Loading... </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </main>
        <div id="recordModal" tabindex="-1" aria-hidden="true"
            class="fixed inset-0 hidden flex justify-center items-center z-50 bg-gray-600/50">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg w-full max-w-5xl mx-4 p-2 max-h-[90vh] overflow-y-auto">
                <div class="flex flex-row justify-between items-center ">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Add Oral Health Condition</h2>
                    <button type="button" id="cancelrecordBtn"
                        class="relative cursor-pointer text-gray-500 hover:text-gray-800 dark:hover:text-white"
                        onclick="closeRecord()">
                        ✕
                    </button>
                </div>
                <form id="recordForm">
                    <input type="hidden" name="patient_id" id="patient_id" value="">
                    <section class="bg-white dark:bg-gray-900 p-2 rounded-lg">
                        <div>
                            <div class="mb-3">
                                <p class="text-14 font-semibold text-gray-900 dark:text-white">
                                    Record of Services Oriented
                                </p>
                            </div>
                            <div class="flex flex-col w-full justify-between items-center gap-5">
                                <ul class="w-full space-y-1 text-sm list-disc list-inside ml-5 mb-5">
                                    <li>For Oral Prophylaxis, Fluoride Varnish/Gel - Check (✓) if rendered.</li>
                                    <li>For Permanent & Temporary Filling, Pit and Fissure Sealant and Extraction -
                                        Indicate
                                        number.</li>
                                </ul>
                                <div class="w-full flex flex-col gap-5 p-1">
                                    <div class="w-full flex flex-row justify-between relative gap-5">
                                        <div class="w-full">
                                            <label
                                                class="flex mb-2 text-sm font-medium text-gray-900 dark:text-white">Oral
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
                                            <label
                                                class="flex mb-2 text-sm font-medium text-gray-900 dark:text-white">Pit
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

                                    <div class="w-full flex flex-row justify-between relative gap-5">
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
                    <div class="flex justify-end gap-2">
                        <button type="button" onclick="saveRecord()"
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
        const params = new URLSearchParams(window.location.search);
        const patientId = params.get('id');

        // Set navigation links
        const patientInfoLink = document.getElementById("patientInfoLink");
        if (patientInfoLink && patientId) {
            patientInfoLink.href = `view_info.php?uid=<?php echo $userId; ?>&id=${encodeURIComponent(patientId)}`;
        } else {
            patientInfoLink.addEventListener("click", (e) => {
                e.preventDefault();
                alert("Please select a patient first.");
            });
        }

        const oralHealthLink = document.getElementById("oralHealthLink");
        if (oralHealthLink && patientId) {
            oralHealthLink.href = `view_oral.php?uid=<?php echo $userId; ?>&id=${encodeURIComponent(patientId)}`;
        } else {
            oralHealthLink.addEventListener("click", (e) => {
                e.preventDefault();
                alert("Please select a patient first.");
            });
        }

        const printdLink = document.getElementById("printdLink");
        if (printdLink && patientId) {
            printdLink.href = `print.php?uid=<?php echo $userId; ?>&id=${encodeURIComponent(patientId)}`;
        } else {
            printdLink.addEventListener("click", (e) => {
                e.preventDefault();
                alert("Please select a patient first.");
            });
        }

        function backmain() {
            location.href = "treatmentrecords.php?uid=<?php echo $userId; ?>";
        }

        // -------------------- FETCH RECORDS --------------------
        function loadTreatmentRecords() {
            console.log('Loading records for patient ID:', patientId);

            if (!patientId || patientId <= 0) {
                document.getElementById("patientName").textContent = "⚠️ No patient selected";
                document.getElementById("treatmentTableBody").innerHTML = `
                <tr><td colspan="9" class="text-center py-4 text-gray-500">
                    Please select a patient first.
                </td></tr>`;
                return;
            }

            // Show loading state
            document.getElementById("treatmentTableBody").innerHTML = `
            <tr><td colspan="9" class="text-center py-4 text-gray-500">
                Loading records...
            </td></tr>`;

            // Use a more specific endpoint path and add cache-busting
            fetch(`/dentalemr_system/php/treatmentrecords/view_record.php?patient_id=${patientId}&t=${Date.now()}`)
                .then(res => {
                    console.log('Fetch response status:', res.status);
                    if (!res.ok) {
                        throw new Error(`HTTP error! status: ${res.status}`);
                    }
                    return res.json();
                })
                .then(data => {
                    console.log('Fetched data:', data);

                    if (!data.success) {
                        throw new Error(data.message || "Failed to load records");
                    }

                    // Update patient name
                    const patientNameElement = document.getElementById("patientName");
                    if (patientNameElement && data.patient) {
                        patientNameElement.textContent = data.patient.fullname;
                    }

                    // Update table
                    const tbody = document.getElementById("treatmentTableBody");
                    if (!tbody) {
                        console.error('Table body element not found');
                        return;
                    }

                    tbody.innerHTML = "";

                    if (data.records && data.records.length > 0) {
                        data.records.forEach(rec => {
                            const row = document.createElement("tr");
                            row.className = "border-b border-gray-200 font-medium text-gray-800 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 dark:text-white";

                            row.innerHTML = `
                            <td class="px-4 py-3 text-center whitespace-nowrap">${rec.created_at || 'N/A'}</td>
                            <td class="px-4 py-3 text-center">${rec.oral_prophylaxis || ""}</td>
                            <td class="px-4 py-3 text-center">${rec.fluoride || ""}</td>
                            <td class="px-4 py-3 text-center">${rec.sealant || ""}</td>
                            <td class="px-4 py-3 text-center">${rec.permanent_filling || ""}</td>
                            <td class="px-4 py-3 text-center">${rec.temporary_filling || ""}</td>
                            <td class="px-4 py-3 text-center">${rec.extraction || ""}</td>
                            <td class="px-4 py-3 text-center">${rec.consultation || ""}</td>
                            <td class="px-4 py-3 text-center max-w-xs truncate" title="${rec.remarks || ''}">${rec.remarks || ""}</td>
                        `;

                            tbody.appendChild(row);
                        });

                        console.log(`Loaded ${data.records.length} records`);
                    } else {
                        tbody.innerHTML = `
                        <tr>
                            <td colspan="9" class="text-center py-8 text-gray-500">
                                No treatment records found for this patient.
                            </td>
                        </tr>`;
                    }
                })
                .catch(err => {
                    console.error("Error fetching records:", err);
                    document.getElementById("treatmentTableBody").innerHTML = `
                    <tr>
                        <td colspan="9" class="text-center text-red-500 py-4">
                            ❌ Failed to load records: ${err.message}
                        </td>
                    </tr>`;
                });
        }

        // -------------------- MODAL CONTROL --------------------
        const recordModal = document.getElementById("recordModal");
        const addBtn = document.getElementById("addSMC");
        const cancelBtn = document.getElementById("cancelrecordBtn");
        const recordForm = document.getElementById("recordForm");

        // Fix modal title to match content
        const modalTitle = recordModal.querySelector('h2');
        if (modalTitle) {
            modalTitle.textContent = "Add Treatment Record";
        }

        addBtn.addEventListener("click", () => {
            console.log('Opening modal for patient ID:', patientId);
            document.getElementById("patient_id").value = patientId;
            recordModal.classList.remove("hidden");
            recordModal.classList.add("flex");

            // Focus first input
            setTimeout(() => {
                const firstInput = recordForm.querySelector('input');
                if (firstInput) firstInput.focus();
            }, 100);
        });

        function closeRecord() {
            recordModal.classList.add("hidden");
            recordModal.classList.remove("flex");
            recordForm.reset();
        }

        if (cancelBtn) cancelBtn.addEventListener("click", closeRecord);

        // Close modal when clicking outside
        recordModal.addEventListener('click', (e) => {
            if (e.target === recordModal) {
                closeRecord();
            }
        });

        // -------------------- SAVE NEW RECORD --------------------
        function saveRecord() {
            const form = document.getElementById("recordForm");
            const formData = new FormData(form);

            // Validate patient ID
            const formPatientId = formData.get('patient_id');
            if (!formPatientId || formPatientId <= 0) {
                alert("❌ Invalid patient ID. Please refresh the page and try again.");
                return;
            }

            console.log('Saving record for patient:', formPatientId);
            console.log('Form data:', Object.fromEntries(formData.entries()));

            // Show saving indicator
            const saveBtn = recordForm.querySelector('button[type="button"]');
            const originalText = saveBtn.textContent;
            saveBtn.textContent = 'Saving...';
            saveBtn.disabled = true;

            fetch(`/dentalemr_system/php/treatmentrecords/view_record.php`, {
                    method: "POST",
                    body: formData
                })
                .then(async res => {
                    console.log('Save response status:', res.status);
                    const data = await res.json();
                    console.log('Save response data:', data);
                    return data;
                })
                .then(data => {
                    if (data.success) {
                        console.log('Record saved successfully');
                        alert("✅ Record added successfully!");
                        closeRecord();
                        loadTreatmentRecords(); // Refresh table
                    } else {
                        throw new Error(data.message || "Failed to save record");
                    }
                })
                .catch(err => {
                    console.error("Error saving record:", err);
                    alert(`⚠️ Failed to save record: ${err.message}`);
                })
                .finally(() => {
                    // Restore button state
                    saveBtn.textContent = originalText;
                    saveBtn.disabled = false;
                });
        }

        // Also allow form submission on Enter key in textarea
        recordForm.querySelector('textarea[name="remarks"]').addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && e.ctrlKey) {
                e.preventDefault();
                saveRecord();
            }
        });

        // Initial load when page is ready
        document.addEventListener('DOMContentLoaded', () => {
            console.log('DOM loaded, patient ID:', patientId);
            // Small delay to ensure everything is loaded
            setTimeout(loadTreatmentRecords, 100);
        });

        // Also reload when navigating back to the page
        window.addEventListener('pageshow', (event) => {
            if (event.persisted) {
                console.log('Page restored from cache, reloading records');
                loadTreatmentRecords();
            }
        });
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