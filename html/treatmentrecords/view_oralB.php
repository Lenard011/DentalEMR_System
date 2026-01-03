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
// Get patient ID from URL
$patientId = isset($_GET['id']) ? intval($_GET['id']) : 0;

?>
<!doctype html>
<html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Treatment Records</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body>
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
                    <a href="#" class="flex items-center justify-between mr-4">
                        <img src="https://th.bing.com/th/id/OIP.zjh8eiLAHY9ybXUCuYiqQwAAAA?r=0&rs=1&pid=ImgDetMain&cb=idpwebp1&o=7&rm=3"
                            class="mr-3 h-8" alt="MHO Logo" />
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
                        <button type="button" id="user-menu-button" aria-expanded="false" data-dropdown-toggle="dropdown" class="flex items-center space-x-2 p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700">
                            <div class="w-8 h-8 rounded-full bg-gray-200 dark:bg-gray-700 flex items-center justify-center overflow-hidden">
                                <?php if (!empty($loggedUser['profile_picture'])): ?>
                                    <img src="<?php echo htmlspecialchars($loggedUser['profile_picture']); ?>" alt="Profile" class="w-full h-full object-cover">
                                <?php else: ?>
                                    <i class="fas fa-user text-gray-600 dark:text-gray-400"></i>
                                <?php endif; ?>
                            </div>
                            <div class="hidden md:block text-left">
                                <div class="text-sm font-medium truncate max-w-[150px]">
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
                                <div class="text-xs text-gray-500 dark:text-gray-400 truncate max-w-[150px]">
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
                                <div class="text-sm font-semibold">
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
                                <div class="text-xs text-gray-600 dark:text-gray-400 mt-1">
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
                                <a href="#"
                                    class="flex items-center px-4 py-2 text-sm hover:bg-gray-100 dark:hover:bg-gray-700">
                                    <i class="fas fa-user-circle mr-3 text-gray-500"></i>
                                    My Profile
                                </a>
                                <a href="/dentalemr_system/html/manageusers/manageuser.php?uid=<?php echo $userId;
                                                                                                echo $isOfflineMode ? '&offline=true' : ''; ?>"
                                    class="flex items-center px-4 py-2 text-sm hover:bg-gray-100 dark:hover:bg-gray-700">
                                    <i class="fas fa-users-cog mr-3 text-gray-500"></i>
                                    Manage Users
                                </a>
                                <a href="/dentalemr_system/html/manageusers/systemlogs.php?uid=<?php echo $userId;
                                                                                                echo $isOfflineMode ? '&offline=true' : ''; ?>"
                                    class="flex items-center px-4 py-2 text-sm hover:bg-gray-100 dark:hover:bg-gray-700">
                                    <i class="fas fa-history mr-3 text-gray-500"></i>
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

        <header class="md:ml-64 pt-20">
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
        <main class="p-2 md:ml-64 h-auto pt-2">
            <section class="bg-white dark:bg-gray-900 p-3 sm:p-4 rounded-lg">
                <!-- Patient Name and Add Button -->
                <div class="items-center justify-between flex flex-col sm:flex-row mb-4 gap-2">
                    <p id="patientName" class="italic text-base sm:text-lg font-medium text-gray-900 dark:text-white">
                        Loading ...
                    </p>
                    <button type="button" id="addSMC"
                        class="text-white cursor-pointer flex flex-row items-center justify-center gap-1 bg-blue-700 hover:bg-blue-800 font-medium rounded-sm text-xs px-3 py-2 w-full sm:w-auto dark:bg-primary-600 dark:hover:bg-primary-700 focus:outline-none dark:focus:ring-primary-800">
                        <svg class="h-3.5 w-3.5" fill="currentColor" viewbox="0 0 20 20"
                            xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                            <path clip-rule="evenodd" fill-rule="evenodd"
                                d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" />
                        </svg>
                        Add Treatment
                    </button>
                </div>

                <!-- Services Monitoring Chart Container -->
                <div id="tables-container"
                    class="mx-auto flex flex-col justify-center items-center p-3 sm:p-4 bg-white rounded-lg shadow dark:border shadow-stone-300 drop-shadow-sm dark:bg-gray-800 dark:border-gray-950">
                    <div class="items-center justify-between flex flex-row w-full mb-4">
                        <p class="text-base font-normal text-gray-950 dark:text-white">B. Services Monitoring Chart</p>
                    </div>

                    <!-- Tables Section -->
                    <div class="w-full space-y-6">
                        <!-- First table -->
                        <div class="w-full">
                            <div class="bg-white dark:bg-gray-800 relative shadow-md rounded-lg overflow-hidden">
                                <div class="overflow-x-auto">
                                    <table id="table-first"
                                        class="w-full text-xs sm:text-sm text-left text-gray-500 dark:text-gray-400 min-w-[800px]">
                                        <thead
                                            class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
                                            <tr>
                                                <th scope="col" class="px-2 sm:px-4 py-2 text-center">Date</th>
                                                <th scope="col" class="px-2 sm:px-4 py-2 text-center">55</th>
                                                <th scope="col" class="px-2 sm:px-4 py-2 text-center">54</th>
                                                <th scope="col" class="px-2 sm:px-4 py-2 text-center">53</th>
                                                <th scope="col" class="px-2 sm:px-4 py-2 text-center">52</th>
                                                <th scope="col" class="px-2 sm:px-4 py-2 text-center">51</th>
                                                <th scope="col" class="px-2 sm:px-4 py-2 text-center">61</th>
                                                <th scope="col" class="px-2 sm:px-4 py-2 text-center">62</th>
                                                <th scope="col" class="px-2 sm:px-4 py-2 text-center">63</th>
                                                <th scope="col" class="px-2 sm:px-4 py-2 text-center">64</th>
                                                <th scope="col" class="px-2 sm:px-4 py-2 text-center">65</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <!-- Table rows will be populated here -->
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Second table -->
                        <div class="w-full">
                            <div class="bg-white dark:bg-gray-800 relative shadow-md rounded-lg overflow-hidden">
                                <div class="overflow-x-auto">
                                    <table id="table-second"
                                        class="w-full text-xs sm:text-sm text-left text-gray-500 dark:text-gray-400 min-w-[800px]">
                                        <thead
                                            class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
                                            <tr>
                                                <th scope="col" class="px-2 sm:px-4 py-2 text-center">Date</th>
                                                <th scope="col" class="px-2 sm:px-4 py-2 text-center">85</th>
                                                <th scope="col" class="px-2 sm:px-4 py-2 text-center">84</th>
                                                <th scope="col" class="px-2 sm:px-4 py-2 text-center">83</th>
                                                <th scope="col" class="px-2 sm:px-4 py-2 text-center">82</th>
                                                <th scope="col" class="px-2 sm:px-4 py-2 text-center">81</th>
                                                <th scope="col" class="px-2 sm:px-4 py-2 text-center">71</th>
                                                <th scope="col" class="px-2 sm:px-4 py-2 text-center">72</th>
                                                <th scope="col" class="px-2 sm:px-4 py-2 text-center">73</th>
                                                <th scope="col" class="px-2 sm:px-4 py-2 text-center">74</th>
                                                <th scope="col" class="px-2 sm:px-4 py-2 text-center">75</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <!-- Table rows will be populated here -->
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Third table -->
                        <div class="w-full">
                            <div class="bg-white dark:bg-gray-800 relative shadow-md rounded-lg overflow-hidden">
                                <div class="overflow-x-auto">
                                    <table id="table-third"
                                        class="w-full text-xs sm:text-sm text-left text-gray-500 dark:text-gray-400 min-w-[1200px]">
                                        <thead
                                            class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
                                            <tr>
                                                <th scope="col" class="px-2 sm:px-4 py-2 text-center">Date</th>
                                                <th scope="col" class="px-2 sm:px-4 py-2 text-center">18</th>
                                                <th scope="col" class="px-2 sm:px-4 py-2 text-center">17</th>
                                                <th scope="col" class="px-2 sm:px-4 py-2 text-center">16</th>
                                                <th scope="col" class="px-2 sm:px-4 py-2 text-center">15</th>
                                                <th scope="col" class="px-2 sm:px-4 py-2 text-center">14</th>
                                                <th scope="col" class="px-2 sm:px-4 py-2 text-center">13</th>
                                                <th scope="col" class="px-2 sm:px-4 py-2 text-center">12</th>
                                                <th scope="col" class="px-2 sm:px-4 py-2 text-center">11</th>
                                                <th scope="col" class="px-2 sm:px-4 py-2 text-center">21</th>
                                                <th scope="col" class="px-2 sm:px-4 py-2 text-center">22</th>
                                                <th scope="col" class="px-2 sm:px-4 py-2 text-center">23</th>
                                                <th scope="col" class="px-2 sm:px-4 py-2 text-center">24</th>
                                                <th scope="col" class="px-2 sm:px-4 py-2 text-center">25</th>
                                                <th scope="col" class="px-2 sm:px-4 py-2 text-center">26</th>
                                                <th scope="col" class="px-2 sm:px-4 py-2 text-center">27</th>
                                                <th scope="col" class="px-2 sm:px-4 py-2 text-center">28</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <!-- Table rows will be populated here -->
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Fourth table -->
                        <div class="w-full">
                            <div class="bg-white dark:bg-gray-800 relative shadow-md rounded-lg overflow-hidden">
                                <div class="overflow-x-auto">
                                    <table id="table-fourth"
                                        class="w-full text-xs sm:text-sm text-left text-gray-500 dark:text-gray-400 min-w-[1200px]">
                                        <thead
                                            class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
                                            <tr>
                                                <th scope="col" class="px-2 sm:px-4 py-2 text-center">Date</th>
                                                <th scope="col" class="px-2 sm:px-4 py-2 text-center">48</th>
                                                <th scope="col" class="px-2 sm:px-4 py-2 text-center">47</th>
                                                <th scope="col" class="px-2 sm:px-4 py-2 text-center">46</th>
                                                <th scope="col" class="px-2 sm:px-4 py-2 text-center">45</th>
                                                <th scope="col" class="px-2 sm:px-4 py-2 text-center">44</th>
                                                <th scope="col" class="px-2 sm:px-4 py-2 text-center">43</th>
                                                <th scope="col" class="px-2 sm:px-4 py-2 text-center">42</th>
                                                <th scope="col" class="px-2 sm:px-4 py-2 text-center">41</th>
                                                <th scope="col" class="px-2 sm:px-4 py-2 text-center">31</th>
                                                <th scope="col" class="px-2 sm:px-4 py-2 text-center">32</th>
                                                <th scope="col" class="px-2 sm:px-4 py-2 text-center">33</th>
                                                <th scope="col" class="px-2 sm:px-4 py-2 text-center">34</th>
                                                <th scope="col" class="px-2 sm:px-4 py-2 text-center">35</th>
                                                <th scope="col" class="px-2 sm:px-4 py-2 text-center">36</th>
                                                <th scope="col" class="px-2 sm:px-4 py-2 text-center">37</th>
                                                <th scope="col" class="px-2 sm:px-4 py-2 text-center">38</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <!-- Table rows will be populated here -->
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Navigation Button -->
                <div class="w-full mt-6">
                    <div class="flex justify-between">
                        <button type="button" onclick="back()"
                            class="text-white cursor-pointer inline-flex items-center justify-center bg-blue-700 hover:bg-blue-800 focus:outline-none focus:ring-blue-300 font-medium rounded-lg text-sm px-4 py-2 w-full sm:w-auto dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800">
                            Back
                        </button>
                    </div>
                </div>
            </section>
        </main>

        <!-- Modal -->
        <div id="SMCModal" tabindex="-1" aria-hidden="true"
            class="fixed inset-0 hidden flex justify-center items-center z-50 bg-gray-600/50">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg w-full max-w-5xl mx-4 max-h-[90vh] overflow-y-auto">
                <div class="p-4 sm:p-6">
                    <div class="flex flex-row justify-between items-center mb-4">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Add Treatment Record</h2>
                        <button type="button" id="cancelMedicalBtn"
                            class="relative cursor-pointer text-gray-500 hover:text-gray-800 dark:hover:text-white text-xl"
                            onclick="closeSMC()">
                            ✕
                        </button>
                    </div>

                    <form id="ohcForm" class="space-y-4">
                        <input type="hidden" name="patient_id" id="patient_id" value="<?php echo $patientId; ?>">

                        <!-- Date Selection -->
                        <div class="mb-4">
                            <label for="treatmentDate" class="block text-sm font-medium text-gray-900 dark:text-white mb-1">
                                Treatment Date
                            </label>
                            <input type="date" id="treatmentDate" name="treatment_date"
                                class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-sm focus:ring-primary-600 focus:border-primary-600 block w-full p-2 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500"
                                value="<?php echo date('Y-m-d'); ?>">
                        </div>

                        <div>
                            <div class="mb-4">
                                <p class="text-sm sm:text-base font-semibold text-gray-900 dark:text-white">
                                    B. Services Monitoring Chart
                                </p>
                            </div>

                            <!-- Top Section - Responsive Layout -->
                            <div class="flex flex-col lg:flex-row gap-4 mb-6">
                                <!-- Teeth Inputs -->
                                <div class="flex-1">
                                    <p class="text-xs sm:text-sm font-normal text-gray-900 dark:text-white p-1 mb-3">
                                        Fluoride Varnish/Fluoride Gel, Pit and fissure Sealant, Permanent Filling, temporary Filling, Extraction
                                    </p>

                                    <!-- Top Teeth Row 1 -->
                                    <div class="grid grid-cols-5 sm:grid-cols-10 gap-2 mb-4">
                                        <!-- Teeth 55-65 -->
                                        <?php
                                        $teeth1 = [55, 54, 53, 52, 51, 61, 62, 63, 64, 65];
                                        foreach ($teeth1 as $tooth): ?>
                                            <div class="flex flex-col items-center gap-2">
                                                <input type="text" data-tooth-id="<?php echo $tooth; ?>" readonly
                                                    class="tooth-input bg-gray-50 border border-gray-300 w-10 text-gray-900 text-xs rounded-sm focus:ring-primary-600 focus:border-primary-600 p-1 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500 text-center cursor-pointer">
                                                <label class="flex text-sm font-medium text-gray-900 dark:text-white"><?php echo $tooth; ?></label>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <!-- Top Teeth Row 2 -->
                                    <div class="grid grid-cols-5 sm:grid-cols-10 gap-2">
                                        <!-- Teeth 85-75 -->
                                        <?php
                                        $teeth2 = [85, 84, 83, 82, 81, 71, 72, 73, 74, 75];
                                        foreach ($teeth2 as $tooth): ?>
                                            <div class="flex flex-col items-center gap-2">
                                                <input type="text" data-tooth-id="<?php echo $tooth; ?>" readonly
                                                    class="tooth-input bg-gray-50 border border-gray-300 w-10 text-gray-900 text-xs rounded-sm focus:ring-primary-600 focus:border-primary-600 p-1 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500 text-center cursor-pointer">
                                                <label class="flex text-sm font-medium text-gray-900 dark:text-white"><?php echo $tooth; ?></label>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>

                                <!-- Legend and Controls -->
                                <div class="lg:w-64 space-y-4">
                                    <!-- Treatment Selector -->
                                    <div>
                                        <select id="selcttreatment"
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

                                    <!-- Legend -->
                                    <div class="bg-gray-50 dark:bg-gray-700 p-3 rounded-sm">
                                        <p class="text-xs font-medium text-gray-900 dark:text-white mb-2">Legend: <span class="font-normal">Treatment</span></p>
                                        <div class="space-y-1 text-xs">
                                            <p class="font-medium text-gray-900 dark:text-white">Topical Fluoride Application:</p>
                                            <p class="ml-3 text-gray-900 dark:text-white">FV - Fluoride Varnish</p>
                                            <p class="ml-3 text-gray-900 dark:text-white">FG - Fluoride Gel</p>
                                            <p class="font-medium text-gray-900 dark:text-white">PFS - Pit and Fissure Sealant</p>
                                            <p class="font-medium text-gray-900 dark:text-white">PF - Permanent Filling (Composite, Am, ART)</p>
                                            <p class="font-medium text-gray-900 dark:text-white">TF - Temporary Filling</p>
                                            <p class="font-medium text-gray-900 dark:text-white">X - Extraction</p>
                                            <p class="font-medium text-gray-900 dark:text-white">O - Others</p>
                                        </div>
                                    </div>

                                    <!-- Instructions -->
                                    <div class="bg-blue-50 dark:bg-blue-900 p-3 rounded-sm">
                                        <p class="text-xs font-medium text-blue-900 dark:text-blue-100 mb-1">Instructions:</p>
                                        <p class="text-xs text-blue-800 dark:text-blue-200">• Click on a tooth to apply selected treatment</p>
                                        <p class="text-xs text-blue-800 dark:text-blue-200">• Double-click to remove treatment</p>
                                        <p class="text-xs text-blue-800 dark:text-blue-200">• Select treatment from dropdown first</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Bottom Teeth Section -->
                            <div class="border-t pt-4">
                                <p class="text-xs sm:text-sm font-normal text-gray-900 dark:text-white p-1 mb-3">
                                    Fluoride Varnish/Fluoride Gel, Pit and fissure Sealant, Permanent Filling, temporary Filling, Extraction
                                </p>

                                <!-- Bottom Teeth Row 1 -->
                                <div class="grid grid-cols-8 sm:grid-cols-16 gap-2 mb-4">
                                    <!-- Teeth 18-28 -->
                                    <?php
                                    $teeth3 = [18, 17, 16, 15, 14, 13, 12, 11, 21, 22, 23, 24, 25, 26, 27, 28];
                                    foreach ($teeth3 as $tooth): ?>
                                        <div class="flex flex-col items-center gap-2">
                                            <input type="text" data-tooth-id="<?php echo $tooth; ?>" readonly
                                                class="tooth-input bg-gray-50 border border-gray-300 w-10 text-gray-900 text-xs rounded-sm focus:ring-primary-600 focus:border-primary-600 p-1 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500 text-center cursor-pointer">
                                            <label class="flex text-sm font-medium text-gray-900 dark:text-white"><?php echo $tooth; ?></label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>

                                <!-- Bottom Teeth Row 2 -->
                                <div class="grid grid-cols-8 sm:grid-cols-16 gap-2">
                                    <!-- Teeth 48-38 -->
                                    <?php
                                    $teeth4 = [48, 47, 46, 45, 44, 43, 42, 41, 31, 32, 33, 34, 35, 36, 37, 38];
                                    foreach ($teeth4 as $tooth): ?>
                                        <div class="flex flex-col items-center gap-2">
                                            <input type="text" data-tooth-id="<?php echo $tooth; ?>" readonly
                                                class="tooth-input bg-gray-50 border border-gray-300 w-10 text-gray-900 text-xs rounded-sm focus:ring-primary-600 focus:border-primary-600 p-1 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500 text-center cursor-pointer">
                                            <label class="flex text-sm font-medium text-gray-900 dark:text-white"><?php echo $tooth; ?></label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Save Button -->
                        <div class="flex justify-end gap-2 pt-4">
                            <button type="button" onclick="closeSMC()"
                                class="text-gray-700 cursor-pointer inline-flex items-center justify-center bg-gray-200 hover:bg-gray-300 focus:outline-none focus:ring-gray-300 font-medium rounded-lg text-sm px-4 py-2 w-full sm:w-auto dark:bg-gray-600 dark:text-white dark:hover:bg-gray-700 dark:focus:ring-gray-800">
                                Cancel
                            </button>
                            <button type="button" onclick="saveSMC()" id="saveSMCBtn"
                                class="text-white cursor-pointer inline-flex items-center justify-center bg-blue-700 hover:bg-blue-800 focus:outline-none focus:ring-blue-300 font-medium rounded-lg text-sm px-4 py-2 w-full sm:w-auto dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800">
                                Save Treatment
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Notification -->
        <div id="notice"
            style="position:fixed; top:14px; right:14px; display:none; padding:12px 16px; border-radius:8px; background:#3b82f6; color:white; z-index:9999; font-weight:500; box-shadow:0 4px 6px -1px rgba(0,0,0,0.1); max-width:350px; word-break:break-word;">
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/flowbite@3.1.2/dist/flowbite.min.js"></script>
    <script src="../js/tailwind.config.js"></script>
    <script>
        // ==================== SESSION MANAGEMENT ====================
        let inactivityTime = 1800000; // 30 minutes in ms
        let logoutTimer;

        function resetTimer() {
            clearTimeout(logoutTimer);
            logoutTimer = setTimeout(() => {
                alert("You've been logged out due to 30 minutes of inactivity.");
                window.location.href = "/dentalemr_system/php/login/logout.php?uid=<?php echo $userId; ?>";
            }, inactivityTime);
        }

        ["click", "mousemove", "keypress", "scroll", "touchstart"].forEach(evt => {
            document.addEventListener(evt, resetTimer, false);
        });

        resetTimer();

        // ==================== NAVIGATION FUNCTIONS ====================
        function backmain() {
            location.href = ("treatmentrecords.php?uid=<?php echo $userId; ?>");
        }

        function back() {
            const patientId = <?php echo $patientId; ?>;

            if (!patientId) {
                alert("Missing patient ID.");
                return;
            }
            window.location.href = `view_oralA.php?uid=<?php echo $userId; ?>&id=${encodeURIComponent(patientId)}`;
        }

        // ==================== NOTIFICATION SYSTEM ====================
        const notice = document.getElementById("notice");

        function showNotice(message, color = "blue") {
            if (!notice) return;

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

            setTimeout(() => {
                notice.style.transition = "opacity 0.6s ease";
                notice.style.opacity = "0";
                setTimeout(() => {
                    notice.style.display = "none";
                    notice.style.transition = "";
                }, 600);
            }, 5000);
        }

        // ==================== PAGE INITIALIZATION ====================
        document.addEventListener("DOMContentLoaded", function() {
            const patientId = <?php echo $patientId; ?>;

            // Setup navigation links
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
                servicesRenderedLink.addEventListener("click", (e) => {
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

            // Load patient data and records
            if (patientId) {
                loadPatientData(patientId);
            } else {
                console.error("No patient ID provided in URL");
                showNotice("No patient selected. Please go back and select a patient.", "red");
            }
        });

        // ==================== DATA LOADING FUNCTIONS ====================
        function loadPatientData(patientId) {
            console.log("Loading patient data for ID:", patientId);

            const nameEl = document.getElementById("patientName");
            if (nameEl) {
                nameEl.textContent = "Loading...";
            }

            // Use relative path that matches your project structure
            fetch(`../../php/treatmentrecords/get_treatment_history.php?patient_id=${patientId}&_=${Date.now()}`)
                .then(response => {
                    console.log("Response status:", response.status);
                    if (!response.ok) {
                        throw new Error(`HTTP error! Status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    console.log("Patient data received:", data);

                    if (data.error) {
                        console.error("API Error:", data.error);
                        showNotice("Error: " + data.error, "red");
                        return;
                    }

                    if (!data.success) {
                        console.error("API returned unsuccessful:", data);
                        showNotice("Failed to load data: " + (data.message || "Unknown error"), "red");
                        return;
                    }

                    // Set patient name
                    const nameEl = document.getElementById("patientName");
                    if (nameEl) {
                        nameEl.textContent = data.patient_name || "Patient Name Not Found";
                        if (data.using_history_table) {
                            nameEl.title = "Using new treatment history system";
                        }
                    }

                    // Populate tables
                    if (data.records && data.records.length > 0) {
                        console.log("Populating tables with", data.records.length, "records");
                        populateTables(data.records);
                        showNotice(`Loaded ${data.records.length} treatment records`, "green");
                    } else {
                        populateTables([]);
                        showNotice("No treatment records found", "yellow");
                    }
                })
                .catch(error => {
                    console.error('Error loading data:', error);
                    console.error('Error details:', error.message);
                    showNotice("Failed to load patient data. Please check if the PHP file exists and the server is running.", "red");

                    const nameEl = document.getElementById("patientName");
                    if (nameEl) {
                        nameEl.textContent = "Error loading data - Check Console";
                    }

                    // Show debug info
                    console.log("Tried to fetch from: ../../php/treatmentrecords/get_treatment_history.php");
                    console.log("Current page URL:", window.location.href);
                });
        }

        // ==================== TABLE POPULATION ====================
        function populateTables(records) {
            console.log("Populating tables with", records.length, "records");

            if (!records || records.length === 0) {
                // Clear all tables
                const tableIds = ['table-first', 'table-second', 'table-third', 'table-fourth'];
                tableIds.forEach(tableId => {
                    const tbody = document.querySelector(`#${tableId} tbody`);
                    if (tbody) {
                        tbody.innerHTML = `
                    <tr>
                        <td colspan="20" class="px-4 py-4 text-center font-medium text-gray-500 dark:text-gray-400">
                            No treatment records found
                        </td>
                    </tr>
                `;
                    }
                });
                return;
            }

            const tableGroups = {
                "table-first": [55, 54, 53, 52, 51, 61, 62, 63, 64, 65],
                "table-second": [85, 84, 83, 82, 81, 71, 72, 73, 74, 75],
                "table-third": [18, 17, 16, 15, 14, 13, 12, 11, 21, 22, 23, 24, 25, 26, 27, 28],
                "table-fourth": [48, 47, 46, 45, 44, 43, 42, 41, 31, 32, 33, 34, 35, 36, 37, 38]
            };

            // Group by date and FDI number
            const groupedByDate = {};

            records.forEach(row => {
                let date;

                if (row.treatment_date) {
                    // New table format
                    date = row.treatment_date;
                } else if (row.created_at) {
                    // Old table format
                    const dateObj = new Date(row.created_at);
                    date = isNaN(dateObj.getTime()) ?
                        row.created_at.split(' ')[0] :
                        dateObj.toISOString().split('T')[0];
                } else {
                    return; // Skip if no date
                }

                const formattedDate = new Date(date).toLocaleDateString('en-PH', {
                    year: 'numeric',
                    month: 'short',
                    day: 'numeric'
                });

                if (!groupedByDate[formattedDate]) {
                    groupedByDate[formattedDate] = {};
                }

                if (row.fdi_number) {
                    groupedByDate[formattedDate][row.fdi_number] = row.treatment_code;
                }
            });

            console.log("Grouped data:", groupedByDate);

            // Populate each table
            for (const [tableId, teeth] of Object.entries(tableGroups)) {
                const tbody = document.querySelector(`#${tableId} tbody`);
                if (!tbody) continue;

                tbody.innerHTML = "";

                const dates = Object.keys(groupedByDate).sort((a, b) => {
                    try {
                        return new Date(b) - new Date(a); // Newest first
                    } catch {
                        return b.localeCompare(a);
                    }
                });

                if (dates.length === 0) {
                    const tr = document.createElement("tr");
                    tr.classList.add("border-b", "border-gray-200", "dark:border-gray-700");

                    const td = document.createElement("td");
                    td.className = "px-4 py-4 text-center font-medium text-gray-500 dark:text-gray-400";
                    td.colSpan = teeth.length + 1;
                    td.textContent = "No treatment records";
                    tr.appendChild(td);
                    tbody.appendChild(tr);
                } else {
                    dates.forEach(date => {
                        const tr = document.createElement("tr");
                        tr.classList.add("border-b", "border-gray-200", "dark:border-gray-700");

                        // Date cell
                        const th = document.createElement("th");
                        th.className = "px-4 py-3 text-center font-medium text-gray-900 dark:text-white whitespace-nowrap";
                        th.textContent = date;
                        tr.appendChild(th);

                        // Tooth cells
                        teeth.forEach(fdi => {
                            const td = document.createElement("td");
                            td.className = "px-4 py-3 text-center font-medium text-gray-900 dark:text-white";
                            const treatmentCode = groupedByDate[date][fdi];
                            td.textContent = treatmentCode || "-";

                            if (treatmentCode) {
                                td.classList.add("font-semibold");
                                // Color coding - FIXED: Split classes properly
                                const colorClasses = {
                                    'FV': ['text-blue-600', 'dark:text-blue-400'],
                                    'FG': ['text-blue-500', 'dark:text-blue-300'],
                                    'PFS': ['text-green-600', 'dark:text-green-400'],
                                    'PF': ['text-purple-600', 'dark:text-purple-400'],
                                    'TF': ['text-yellow-600', 'dark:text-yellow-400'],
                                    'X': ['text-red-600', 'dark:text-red-400'],
                                    'O': ['text-gray-600', 'dark:text-gray-400']
                                };

                                const classes = colorClasses[treatmentCode] || ['text-gray-900'];
                                classes.forEach(cls => td.classList.add(cls));
                            }

                            tr.appendChild(td);
                        });

                        tbody.appendChild(tr);
                    });
                }
            }
        }

        // ==================== MODAL FUNCTIONS ====================
        let selectedTreatmentCode = null;

        // Treatment dropdown selection
        document.getElementById("selcttreatment").addEventListener("change", function() {
            selectedTreatmentCode = this.value;
            if (selectedTreatmentCode) {
                showNotice(`Selected: ${this.options[this.selectedIndex].text}`, "blue");
            }
        });

        function initSMCTreatmentClick() {
            const toothInputs = document.querySelectorAll("#SMCModal .tooth-input");

            toothInputs.forEach(input => {
                // Clear existing event listeners by cloning
                const newInput = input.cloneNode(true);
                input.parentNode.replaceChild(newInput, input);
            });

            document.querySelectorAll("#SMCModal .tooth-input").forEach(input => {
                input.addEventListener("click", () => {
                    if (!selectedTreatmentCode) {
                        showNotice("Please select a treatment first!", "red");
                        return;
                    }

                    const treatmentText = document.querySelector(`#selcttreatment option[value="${selectedTreatmentCode}"]`).text;
                    input.value = selectedTreatmentCode;
                    input.style.backgroundColor = "#e5e7eb";
                    input.title = treatmentText;

                    // Visual feedback
                    input.style.transform = "scale(1.1)";
                    setTimeout(() => {
                        input.style.transform = "scale(1)";
                    }, 200);
                });

                input.addEventListener("dblclick", () => {
                    if (input.value) {
                        input.value = "";
                        input.style.backgroundColor = "";
                        input.title = "";

                        // Visual feedback
                        input.style.transform = "scale(0.95)";
                        setTimeout(() => {
                            input.style.transform = "scale(1)";
                        }, 200);
                    }
                });
            });
        }

        // Open modal and load treatments for selected date
        document.getElementById("addSMC").addEventListener("click", async () => {
            const patientId = <?php echo $patientId; ?>;
            if (!patientId) return showNotice("No patient selected", "red");

            // Get selected date
            const datePicker = document.getElementById("treatmentDate");
            const selectedDate = datePicker ? datePicker.value : new Date().toISOString().split('T')[0];

            try {
                const response = await fetch(`../../php/treatmentrecords/get_today_smc.php?patient_id=${patientId}&date=${selectedDate}`);
                if (!response.ok) {
                    throw new Error(`HTTP error! Status: ${response.status}`);
                }
                const data = await response.json();

                // Clear all previous inputs first
                document.querySelectorAll("#SMCModal .tooth-input").forEach(input => {
                    input.value = "";
                    input.style.backgroundColor = "";
                    input.title = "";
                });

                // Fill modal inputs with records for the selected date
                if (data.records && data.records.length > 0) {
                    data.records.forEach(rec => {
                        const input = document.querySelector(`#SMCModal .tooth-input[data-tooth-id='${rec.fdi_number}']`);
                        if (input) {
                            input.value = rec.treatment_code;
                            input.style.backgroundColor = "#e5e7eb";
                            input.title = getTreatmentName(rec.treatment_code);
                        }
                    });

                    showNotice(`Loaded ${data.records.length} existing treatments for ${selectedDate}`, "blue");
                } else {
                    showNotice(`No existing treatments found for ${selectedDate}. You can add new ones.`, "yellow");
                }

                // Reset treatment selector
                document.getElementById("selcttreatment").selectedIndex = 0;
                selectedTreatmentCode = null;

                document.getElementById("SMCModal").classList.remove("hidden");
                initSMCTreatmentClick();
            } catch (err) {
                console.error("Failed to load treatments", err);
                // If get_today_smc.php doesn't exist, just show empty modal
                showNotice("Ready to add new treatments", "yellow");
                document.getElementById("SMCModal").classList.remove("hidden");
                initSMCTreatmentClick();
            }
        });

        function getTreatmentName(code) {
            const treatments = {
                'FV': 'Fluoride Varnish',
                'FG': 'Fluoride Gel',
                'PFS': 'Pit and Fissure Sealant',
                'PF': 'Permanent Filling',
                'TF': 'Temporary Filling',
                'X': 'Extraction',
                'O': 'Others'
            };
            return treatments[code] || code;
        }

        // ==================== SAVE FUNCTION ====================
        function saveSMC() {
            const patientId = document.getElementById("patient_id").value;
            if (!patientId) return showNotice("Patient ID not set", "red");

            const datePicker = document.getElementById("treatmentDate");
            const selectedDate = datePicker ? datePicker.value : new Date().toISOString().split('T')[0];

            const treatments = [];
            let hasTreatments = false;

            document.querySelectorAll("#SMCModal .tooth-input").forEach(input => {
                const treatmentCode = input.value.trim();
                const toothId = input.dataset.toothId;

                if (treatmentCode && toothId) {
                    treatments.push({
                        tooth_id: toothId,
                        treatment_code: treatmentCode
                    });
                    hasTreatments = true;
                }
            });

            if (!hasTreatments) {
                showNotice("No treatments selected", "yellow");
                return;
            }

            // Disable save button
            const saveBtn = document.getElementById("saveSMCBtn");
            const originalText = saveBtn.textContent;
            saveBtn.textContent = "Saving...";
            saveBtn.disabled = true;

            // Close modal
            closeSMC();

            // Use relative path for save endpoint
            fetch("../../php/treatmentrecords/save_treatment_history.php", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json"
                    },
                    body: JSON.stringify({
                        patient_id: parseInt(patientId),
                        treatments: treatments,
                        date: selectedDate
                    })
                })
                .then(async response => {
                    const text = await response.text();
                    console.log("Server response:", text);

                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        console.error("Failed to parse JSON:", text);
                        throw new Error("Invalid server response");
                    }
                })
                .then(data => {
                    console.log("Parsed data:", data);

                    if (data.success) {
                        showNotice(data.message, "green");

                        // Clear modal inputs
                        document.querySelectorAll("#SMCModal .tooth-input").forEach(input => {
                            input.value = "";
                            input.style.backgroundColor = "";
                            input.title = "";
                        });

                        // Reset treatment selector
                        document.getElementById("selcttreatment").selectedIndex = 0;
                        selectedTreatmentCode = null;

                        // Refresh the table data
                        setTimeout(() => {
                            loadPatientData(patientId);
                        }, 300);

                    } else {
                        showNotice("Failed: " + (data.message || "Unknown error"), "red");

                        // If error is about missing table, create it
                        if (data.message && data.message.includes("table not found")) {
                            if (confirm("Treatment history table not found. Create it now?")) {
                                createTreatmentHistoryTable();
                            }
                        }
                    }

                    saveBtn.textContent = originalText;
                    saveBtn.disabled = false;
                })
                .catch(err => {
                    console.error("Save error:", err);
                    showNotice("Save failed: " + err.message, "red");
                    saveBtn.textContent = originalText;
                    saveBtn.disabled = false;
                });
        }

        function createTreatmentHistoryTable() {
            showNotice("Creating treatment history table...", "blue");

            fetch("../../php/treatmentrecords/create_history_table.php")
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showNotice("Table created successfully! Please try saving again.", "green");
                    } else {
                        showNotice("Failed to create table: " + data.message, "red");
                    }
                })
                .catch(err => {
                    showNotice("Error creating table: " + err.message, "red");
                });
        }

        // Close modal
        function closeSMC() {
            document.getElementById("SMCModal").classList.add("hidden");
            // Reset treatment selector
            document.getElementById("selcttreatment").selectedIndex = 0;
            selectedTreatmentCode = null;
        }

        // Add CSS for better loading states
        const style = document.createElement('style');
        style.textContent = `
            .tooth-input {
                transition: all 0.2s ease;
            }
            
            .tooth-input:disabled {
                opacity: 0.5;
                cursor: not-allowed;
            }
            
            #tables-container {
                transition: opacity 0.3s ease;
            }
            
            .loading-spinner {
                display: none;
                position: fixed;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                z-index: 9999;
            }
            
            #saveSMCBtn:disabled {
                background-color: #93c5fd !important;
                cursor: not-allowed;
            }
        `;
        document.head.appendChild(style);
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