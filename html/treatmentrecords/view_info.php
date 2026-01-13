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
                    window.location.href = '/DentalEMR_System/html/login/login.html';
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
                window.location.href = '/DentalEMR_System/html/treatmentrecords/view_info.php?offline=true&id=" . (isset($_GET['id']) ? $_GET['id'] : '') . "';
            } else {
                alert('Invalid session. Please log in again.');
                window.location.href = '/DentalEMR_System/html/login/login.html';
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
                window.location.href = '/DentalEMR_System/html/treatmentrecords/view_info.php?offline=true&id=" . (isset($_GET['id']) ? $_GET['id'] : '') . "';
            } else {
                alert('Please log in first.');
                window.location.href = '/DentalEMR_System/html/login/login.html';
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
                window.location.href = '/DentalEMR_System/html/login/login.html';
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
    $dbUser = "u401132124_dentalclinic";
    $dbPass = "Mho_DentalClinic1st";
    $dbName = "u401132124_mho_dentalemr";

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
                    window.location.href = '/DentalEMR_System/html/treatmentrecords/view_info.php?offline=true&id=" . (isset($_GET['id']) ? $_GET['id'] : '') . "';
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
    <meta name="csrf-token" content="<?php echo bin2hex(random_bytes(32)); ?>">
    <title>Patient Treatment Records</title>
    <link rel="icon" type="image/png" href="/DentalEMR_System/img/1761912137392.png">
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
                                <a href="/DentalEMR_System/html/manageusers/profile.php?uid=<?php echo $userId; ?>"
                                    class="flex items-center px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700">
                                    <i class="fas fa-user-circle mr-3 text-gray-500 dark:text-gray-400"></i>
                                    My Profile
                                </a>
                                <a href="/DentalEMR_System/html/manageusers/manageuser.php?uid=<?php echo $userId;
                                                                                                echo $isOfflineMode ? '&offline=true' : ''; ?>"
                                    class="flex items-center px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700">
                                    <i class="fas fa-users-cog mr-3 text-gray-500 dark:text-gray-400"></i>
                                    Manage Users
                                </a>
                                <a href="/DentalEMR_System/html/manageusers/systemlogs.php?uid=<?php echo $userId;
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
                                <a href="/DentalEMR_System/php/login/logout.php?uid=<?php echo $loggedUser['id']; ?>"
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

        <header class="md:ml-64 pt-20">
            <nav class="bg-white border-gray-200 dark:bg-gray-800 w-full drop-shadow-sm pb-2">
                <div class="flex flex-col justify-between items-center mx-auto px-2 sm:px-4">
                    <!-- Top Section: Back Button, Title, Print Button -->
                    <div class="flex items-center justify-between w-full py-2">
                        <!-- Back Button -->
                        <div class="relative group inline-block">
                            <button type="button" onclick="back()" class="cursor-pointer">
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
                                <a href="#"
                                    class="block py-2 px-3 text-blue-800 border-b-2 font-semibold border-blue-800 hover:bg-gray-50 lg:hover:bg-transparent lg:border-0 lg:hover:text-primary-700 lg:p-0 dark:text-blue-400 lg:dark:hover:text-white dark:hover:bg-gray-700 dark:hover:text-white lg:dark:hover:bg-transparent dark:border-gray-700 text-center sm:text-left">
                                    Patient Information
                                </a>
                            </li>
                            <li class="w-full sm:w-auto">
                                <a href="#" id="oralHealthLink"
                                    class="block py-2 px-3 text-gray-700 border-b font-semibold border-gray-100 hover:bg-gray-50 lg:hover:bg-transparent lg:border-0 lg:hover:text-primary-700 lg:p-0 dark:text-gray-400 lg:dark:hover:text-white dark:hover:bg-gray-700 dark:hover:text-white lg:dark:hover:bg-transparent dark:border-gray-700 text-center sm:text-left">
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
            <section class="relative bg-white dark:bg-gray-900 p-3 sm:p-4 rounded-lg mb-4">
                <p id="patientName" class="italic text-base sm:text-lg font-medium text-gray-900 dark:text-white mb-3">Loading ...</p>

                <!-- Patient Info -->
                <div class="relative mx-auto mb-5 p-3 sm:p-4 bg-white rounded-lg shadow dark:border shadow-stone-300 drop-shadow-sm dark:bg-gray-800 dark:border-gray-950">
                    <div class="items-center justify-between flex flex-col sm:flex-row mb-4 gap-2">
                        <p class="text-base font-normal text-gray-950 dark:text-white">Patient Details</p>
                        <button id="editBtn" type="button"
                            class="text-white cursor-pointer flex flex-row items-center justify-center gap-1 bg-blue-700 hover:bg-blue-800 font-medium rounded-sm text-xs px-3 py-2 w-full sm:w-auto">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor"
                                class="bi bi-pencil-square" viewBox="0 0 16 16">
                                <path
                                    d="M15.502 1.94a.5.5 0 0 1 0 .706L14.459 3.69l-2-2L13.502.646a.5.5 0 0 1 .707 0l1.293 1.293zm-1.75 2.456-2-2L4.939 9.21a.5.5 0 0 0-.121.196l-.805 2.414a.25.25 0 0 0 .316.316l2.414-.805a.5.5 0 0 0 .196-.12l6.813-6.814z" />
                                <path fill-rule="evenodd"
                                    d="M1 13.5A1.5 1.5 0 0 0 2.5 15h11a1.5 1.5 0 0 0 1.5-1.5v-6a.5.5 0 0 0-1 0v6a.5.5 0 0 1-.5.5h-11a.5.5 0 0 1-.5-.5v-11a.5.5 0 0 1 .5-.5H9a.5.5 0 0 0 0-1H2.5A1.5 1.5 0 0 0 1 2.5z" />
                            </svg>
                            Edit
                        </button>
                    </div>

                    <!-- Patient Details Grid -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-4 mb-4">
                        <!-- Name -->
                        <div class="flex items-center gap-3 p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                            <div class="rounded-full p-2 bg-gray-100 dark:bg-blue-300 flex-shrink-0">
                                <svg class="w-5 h-5 text-blue-800 dark:text-white" aria-hidden="true"
                                    xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none"
                                    viewBox="0 0 24 24">
                                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"
                                        stroke-width="1"
                                        d="M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Zm0 0a8.949 8.949 0 0 0 4.951-1.488A3.987 3.987 0 0 0 13 16h-2a3.987 3.987 0 0 0-3.951 3.512A8.948 8.948 0 0 0 12 21Zm3-11a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                </svg>
                            </div>
                            <div class="min-w-0 flex-1">
                                <p id="patientName2" class="text-sm font-medium text-gray-950 dark:text-white truncate">
                                    Loading ...
                                </p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Name</p>
                            </div>
                        </div>

                        <!-- Gender -->
                        <div class="flex items-center gap-3 p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                            <div class="rounded-full p-2 bg-gray-100 dark:bg-blue-300 flex-shrink-0">
                                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="currentColor"
                                    class="w-5 h-5 text-blue-800 dark:text-white" viewBox="0 0 20 16">
                                    <path fill-rule="evenodd"
                                        d="M11.5 1a.5.5 0 0 1 0-1h4a.5.5 0 0 1 .5.5v4a.5.5 0 0 1-1 0V1.707l-3.45 3.45A4 4 0 0 1 8.5 10.97V13H10a.5.5 0 0 1 0 1H8.5v1.5a.5.5 0 0 1-1 0V14H6a.5.5 0 0 1 0-1h1.5v-2.03a4 4 0 1 1 3.471-6.648L14.293 1zm-.997 4.346a3 3 0 1 0-5.006 3.309 3 3 0 0 0 5.006-3.31z" />
                                </svg>
                            </div>
                            <div class="min-w-0 flex-1">
                                <p id="patientSex" class="text-sm font-medium text-gray-950 dark:text-white">
                                    Loading ...
                                </p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Gender</p>
                            </div>
                        </div>

                        <!-- Age -->
                        <div class="flex items-center gap-3 p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                            <div class="rounded-full p-2 bg-gray-100 dark:bg-blue-300 flex-shrink-0">
                                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="currentColor"
                                    class="w-5 h-5 text-blue-800 dark:text-white" viewBox="0 0 18 15">
                                    <path fill-rule="evenodd"
                                        d="M14 2.5a.5.5 0 0 0-.5-.5h-6a.5.5 0 0 0 0 1h4.793L2.146 13.146a.5.5 0 0 0 .708.708L13 3.707V8.5a.5.5 0 0 0 1 0z" />
                                </svg>
                            </div>
                            <div class="min-w-0 flex-1">
                                <p id="patientAge" class="text-sm font-medium text-gray-950 dark:text-white">
                                    Loading ...
                                </p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Age</p>
                            </div>
                        </div>

                        <!-- Date of Birth -->
                        <div class="flex items-center gap-3 p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                            <div class="rounded-full p-2 bg-gray-100 dark:bg-blue-300 flex-shrink-0">
                                <svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" fill="currentColor"
                                    class="w-5 h-5 text-blue-800 dark:text-white" viewBox="-1 -3 16 22">
                                    <path
                                        d="M10.854 7.146a.5.5 0 0 1 0 .708l-3 3a.5.5 0 0 1-.708 0l-1.5-1.5a.5.5 0 1 1 .708-.708L7.5 9.793l2.646-2.647a.5.5 0 0 1 .708 0" />
                                    <path
                                        d="M3.5 0a.5.5 0 0 1 .5.5V1h8V.5a.5.5 0 0 1 1 0V1h1a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V3a2 2 0 0 1 2-2h1V.5a.5.5 0 0 1 .5-.5M1 4v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V4z" />
                                </svg>
                            </div>
                            <div class="min-w-0 flex-1">
                                <p id="patientDob" class="text-sm font-medium text-gray-950 dark:text-white">
                                    Loading ...
                                </p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Date of Birth</p>
                            </div>
                        </div>

                        <!-- Occupation -->
                        <div class="flex items-center gap-3 p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                            <div class="rounded-full p-2 bg-gray-100 dark:bg-blue-300 flex-shrink-0">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor"
                                    class="w-5 h-5 text-blue-800 dark:text-white" viewBox="-1 -3 16 22">
                                    <path
                                        d="M4 16s-1 0-1-1 1-4 5-4 5 3 5 4-1 1-1 1zm4-5.95a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5" />
                                    <path
                                        d="M2 1a2 2 0 0 0-2 2v9.5A1.5 1.5 0 0 0 1.5 14h.653a5.4 5.4 0 0 1 1.066-2H1V3a1 1 0 0 1 1-1h12a1 1 0 0 1 1 1v9h-2.219c.554.654.89 1.373 1.066 2h.653a1.5 1.5 0 0 0 1.5-1.5V3a2 2 0 0 0-2-2z" />
                                </svg>
                            </div>
                            <div class="min-w-0 flex-1">
                                <p id="patientOccupation" class="text-sm font-medium text-gray-950 dark:text-white">
                                    Loading ...
                                </p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Occupation</p>
                            </div>
                        </div>
                    </div>

                    <!-- Second Row: Place of Birth, Address, Guardian -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <!-- Place of Birth -->
                        <div class="flex items-center gap-3 p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                            <div class="rounded-full p-2 bg-gray-100 dark:bg-blue-300 flex-shrink-0">
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor"
                                    class="w-5 h-5 text-blue-800 dark:text-white" viewBox="-2 0 20 16">
                                    <path
                                        d="M12.166 8.94c-.524 1.062-1.234 2.12-1.96 3.07A32 32 0 0 1 8 14.58a32 32 0 0 1-2.206-2.57c-.726-.95-1.436-2.008-1.96-3.07C3.304 7.867 3 6.862 3 6a5 5 0 0 1 10 0c0 .862-.305 1.867-.834 2.94M8 16s6-5.686 6-10A6 6 0 0 0 2 6c0 4.314 6 10 6 10" />
                                    <path
                                        d="M8 8a2 2 0 1 1 0-4 2 2 0 0 1 0 4m0 1a3 3 0 1 0 0-6 3 3 0 0 0 0 6" />
                                </svg>
                            </div>
                            <div class="min-w-0 flex-1">
                                <p id="patientBirthPlace" class="text-sm font-medium text-gray-950 dark:text-white truncate">
                                    Loading ...
                                </p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Place of Birth</p>
                            </div>
                        </div>

                        <!-- Address -->
                        <div class="flex items-center gap-3 p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                            <div class="rounded-full p-2 bg-gray-100 dark:bg-blue-300 flex-shrink-0">
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor"
                                    class="w-5 h-5 text-blue-800 dark:text-white" viewBox="-2 0 20 16">
                                    <path fill-rule="evenodd"
                                        d="M4 4a4 4 0 1 1 4.5 3.969V13.5a.5.5 0 0 1-1 0V7.97A4 4 0 0 1 4 3.999zm2.493 8.574a.5.5 0 0 1-.411.575c-.712.118-1.28.295-1.655.493a1.3 1.3 0 0 0-.37.265.3.3 0 0 0-.057.09V14l.002.008.016.033a.6.6 0 0 0 .145.15c.165.13.435.27.813.395.751.25 1.82.414 3.024.414s2.273-.163 3.024-.414c.378-.126.648-.265.813-.395a.6.6 0 0 0 .146-.15l.015-.033L12 14v-.004a.3.3 0 0 0-.057-.09 1.3 1.3 0 0 0-.37-.264c-.376-.198-.943-.375-1.655-.493a.5.5 0 1 1 .164-.986c.77.127 1.452.328 1.957.594C12.5 13 13 13.4 13 14c0 .426-.26.752-.544.977-.29.228-.68.413-1.116.558-.878.293-2.059.465-3.34.465s-2.462-.172-3.34-.465c-.436-.145-.826-.33-1.116-.558C3.26 14.752 3 14.426 3 14c0-.599.5-1 .961-1.243.505-.266 1.187-.467 1.957-.594a.5.5 0 0 1 .575.411" />
                                </svg>
                            </div>
                            <div class="min-w-0 flex-1">
                                <p id="patientAddress" class="text-sm font-medium text-gray-950 dark:text-white truncate">
                                    Loading ...
                                </p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Address</p>
                            </div>
                        </div>

                        <!-- Parent/Guardian -->
                        <div class="flex items-center gap-3 p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                            <div class="rounded-full p-2 bg-gray-100 dark:bg-blue-300 flex-shrink-0">
                                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="currentColor"
                                    class="w-5 h-5 text-blue-800 dark:text-white" viewBox="-2.5 0 20 16">
                                    <path
                                        d="M15 14s1 0 1-1-1-4-5-4-5 3-5 4 1 1 1 1zm-7.978-1L7 12.996c.001-.264.167-1.03.76-1.72C8.312 10.629 9.282 10 11 10c1.717 0 2.687.63 3.24 1.276.593.69.758 1.457.76 1.72l-.008.002-.014.002zM11 7a2 2 0 1 0 0-4 2 2 0 0 0 0 4m3-2a3 3 0 1 1-6 0 3 3 0 0 1 6 0M6.936 9.28a6 6 0 0 0-1.23-.247A7 7 0 0 0 5 9c-4 0-5 3-5 4q0 1 1 1h4.216A2.24 2.24 0 0 1 5 13c0-1.01.377-2.042 1.09-2.904.243-.294.526-.569.846-.816M4.92 10A5.5 5.5 0 0 0 4 13H1c0-.26.164-1.03.76-1.724.545-.636 1.492-1.256 3.16-1.275ZM1.5 5.5a3 3 0 1 1 6 0 3 3 0 0 1-6 0m3-2a2 2 0 1 0 0 4 2 2 0 0 0 0-4" />
                                </svg>
                            </div>
                            <div class="min-w-0 flex-1">
                                <p id="patientGuardian" class="text-sm font-medium text-gray-950 dark:text-white truncate">
                                    Loading ...
                                </p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Parent/Guardian</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Other sections (Membership, Vital Signs, Medical History, Dietary) remain with similar responsive improvements -->
                <!-- Membership -->
                <div class="mx-auto mb-5 p-3 sm:p-4 bg-white rounded-lg shadow dark:border shadow-stone-300 drop-shadow-sm dark:bg-gray-800 dark:border-gray-950">
                    <div class="items-center justify-between flex flex-col sm:flex-row mb-3 gap-2">
                        <p class="text-base font-normal text-gray-950 dark:text-white">Membership</p>
                        <button id="addBtn" type="button"
                            class="text-white cursor-pointer flex flex-row items-center justify-center gap-1 bg-blue-700 hover:bg-blue-800 font-medium rounded-sm text-xs px-3 py-2 w-full sm:w-auto">
                            <svg class="h-3.5 w-3.5" fill="currentColor" viewBox="0 0 20 20"
                                xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                <path clip-rule="evenodd" fill-rule="evenodd"
                                    d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" />
                            </svg>
                            Add
                        </button>
                    </div>
                    <ul id="membershipList"
                        class="max-w-full space-y-1 text-gray-900 list-disc list-inside dark:text-white text-sm">
                    </ul>
                </div>

                <!-- Vital Signs -->
                <div class="mx-auto mb-5 p-3 sm:p-4 bg-white rounded-lg shadow dark:border shadow-stone-300 drop-shadow-sm dark:bg-gray-800 dark:border-gray-950">
                    <div class="items-center justify-between flex flex-col sm:flex-row mb-3 gap-2">
                        <p class="text-base font-normal text-gray-950 dark:text-white">Vital Signs</p>
                        <button type="button" id="addVitalbtn"
                            class="text-white cursor-pointer flex flex-row items-center justify-center gap-1 bg-blue-700 hover:bg-blue-800 font-medium rounded-sm text-xs px-3 py-2 w-full sm:w-auto">
                            <svg class="h-3.5 w-3.5" fill="currentColor" viewbox="0 0 20 20"
                                xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                <path clip-rule="evenodd" fill-rule="evenodd"
                                    d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" />
                            </svg>
                            Add
                        </button>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 p-1">
                        <!-- Blood Pressure -->
                        <div class="flex flex-col items-center p-4 rounded-lg shadow dar:border shadow-stone-300 dark:bg-gray-800 dark:border-gray-950">
                            <div class="w-full flex items-center justify-center mb-2">
                                <div class="rounded-full flex items-center justify-center p-2 bg-gray-100 dark:bg-blue-300 w-12 h-12">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor"
                                        class="bi bi-capsule w-6 h-6 text-blue-800 dark:text-white"
                                        viewBox="0 0 16 16">
                                        <path
                                            d="M1.828 8.9 8.9 1.827a4 4 0 1 1 5.657 5.657l-7.07 7.071A4 4 0 1 1 1.827 8.9Zm9.128.771 2.893-2.893a3 3 0 1 0-4.243-4.242L6.713 5.429z" />
                                    </svg>
                                </div>
                            </div>
                            <p class="text-sm font-normal text-gray-950 dark:text-white mb-3">Blood Pressure</p>
                            <div class="relative overflow-x-auto w-full">
                                <table class="w-full text-sm text-left rtl:text-right text-gray-900 dark:text-white border dark:bg-gray-800 dark:border-gray-700 border-gray-200 rounded-lg">
                                    <tbody id="bpTableBody"></tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Temperature -->
                        <div class="flex flex-col items-center p-4 rounded-lg shadow dar:border shadow-stone-300 dark:bg-gray-800 dark:border-gray-950">
                            <div class="w-full flex items-center justify-center mb-2">
                                <div class="rounded-full flex items-center justify-center p-2 bg-gray-100 dark:bg-blue-300 w-12 h-12">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor"
                                        class="bi bi-capsule w-6 h-6 text-blue-800 dark:text-white"
                                        viewBox="0 0 16 16">
                                        <path
                                            d="M9.5 12.5a1.5 1.5 0 1 1-2-1.415V6.5a.5.5 0 0 1 1 0v4.585a1.5 1.5 0 0 1 1 1.415" />
                                        <path
                                            d="M5.5 2.5a2.5 2.5 0 0 1 5 0v7.55a3.5 3.5 0 1 1-5 0zM8 1a1.5 1.5 0 0 0-1.5 1.5v7.987l-.167.15a2.5 2.5 0 1 0 3.333 0l-.166-.15V2.5A1.5 1.5 0 0 0 8 1" />
                                    </svg>
                                </div>
                            </div>
                            <p class="text-sm font-normal text-gray-950 dark:text-white mb-3">Temperature</p>
                            <div class="relative overflow-x-auto w-full">
                                <table class="w-full text-sm text-left rtl:text-right text-gray-900 dark:text-white border dark:bg-gray-800 dark:border-gray-700 border-gray-200 rounded-lg">
                                    <tbody id="tempTableBody"></tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Pulse Rate -->
                        <div class="flex flex-col items-center p-4 rounded-lg shadow dar:border shadow-stone-300 dark:bg-gray-800 dark:border-gray-950">
                            <div class="w-full flex items-center justify-center mb-2">
                                <div class="rounded-full flex items-center justify-center p-2 bg-gray-100 dark:bg-blue-300 w-12 h-12">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor"
                                        class="bi bi-capsule w-6 h-6 text-blue-800 dark:text-white"
                                        viewBox="0 0 16 16">
                                        <path
                                            d="m8 2.748-.717-.737C5.6.281 2.514.878 1.4 3.053.918 3.995.78 5.323 1.508 7H.43c-2.128-5.697 4.165-8.83 7.394-5.857q.09.083.176.171a3 3 0 0 1 .176-.17c3.23-2.974 9.522.159 7.394 5.856h-1.078c.728-1.677.59-3.005.108-3.947C13.486.878 10.4.28 8.717 2.01zM2.212 10h1.315C4.593 11.183 6.05 12.458 8 13.795c1.949-1.337 3.407-2.612 4.473-3.795h1.315c-1.265 1.566-3.14 3.25-5.788 5-2.648-1.75-4.523-3.434-5.788-5" />
                                        <path
                                            d="M10.464 3.314a.5.5 0 0 0-.945.049L7.921 8.956 6.464 5.314a.5.5 0 0 0-.88-.091L3.732 8H.5a.5.5 0 0 0 0 1H4a.5.5 0 0 0 .416-.223l1.473-2.209 1.647 4.118a.5.5 0 0 0 .945-.049l1.598-5.593 1.457 3.642A.5.5 0 0 0 12 9h3.5a.5.5 0 0 0 0-1h-3.162z" />
                                    </svg>
                                </div>
                            </div>
                            <p class="text-sm font-normal text-gray-950 dark:text-white mb-3">Pulse Rate</p>
                            <div class="relative overflow-x-auto w-full">
                                <table class="w-full text-sm text-left rtl:text-right text-gray-900 dark:text-white border dark:bg-gray-800 dark:border-gray-700 border-gray-200 rounded-lg">
                                    <tbody id="pulseTableBody"></tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Weight -->
                        <div class="flex flex-col items-center p-4 rounded-lg shadow dar:border shadow-stone-300 dark:bg-gray-800 dark:border-gray-950">
                            <div class="w-full flex items-center justify-center mb-2">
                                <div class="rounded-full flex items-center justify-center p-3 bg-gray-100 dark:bg-blue-300 w-12 h-12">
                                    <img src="../../img/9767079.png" alt="Weight icon" class="w-6 h-6">
                                </div>
                            </div>
                            <p class="text-sm font-normal text-gray-950 dark:text-white mb-3">Weight</p>
                            <div class="relative overflow-x-auto w-full">
                                <table class="w-full text-sm text-left rtl:text-right text-gray-900 border dark:text-white border-gray-200 rounded-lg">
                                    <tbody id="weightTableBody"></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Medical History -->
                <div class="mx-auto mb-5 p-3 sm:p-4 bg-white rounded-lg shadow dark:border shadow-stone-300 drop-shadow-sm dark:bg-gray-800 dark:border-gray-950">
                    <div class="items-center justify-between flex flex-col sm:flex-row mb-3 gap-2">
                        <p class="text-base font-normal text-gray-950 dark:text-white">Medical History</p>
                        <button type="button" id="addMedicalHistoryBtn"
                            class="text-white cursor-pointer flex flex-row items-center justify-center gap-1 bg-blue-700 hover:bg-blue-800 font-medium rounded-sm text-xs px-3 py-2 w-full sm:w-auto">
                            <svg class="h-3.5 w-3.5" fill="currentColor" viewBox="0 0 20 20"
                                xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                <path clip-rule="evenodd" fill-rule="evenodd"
                                    d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" />
                            </svg>
                            Add
                        </button>
                    </div>
                    <ul id="medicalHistoryList"
                        class="max-w-full space-y-1 text-gray-900 list-disc list-inside dark:text-white text-sm">
                    </ul>
                </div>

                <!-- Dietary -->
                <div class="mx-auto mb-5 p-3 sm:p-4 bg-white rounded-lg shadow dark:border shadow-stone-300 drop-shadow-sm dark:bg-gray-800 dark:border-gray-950">
                    <div class="items-center justify-between flex flex-col sm:flex-row mb-3 gap-2">
                        <p class="text-base font-normal text-gray-950 dark:text-white">Dietary Habits / Social History</p>
                        <button type="button" id="addDietaryHistoryBtn"
                            class="text-white cursor-pointer flex flex-row items-center justify-center gap-1 bg-blue-700 hover:bg-blue-800 font-medium rounded-sm text-xs px-3 py-2 w-full sm:w-auto">
                            <svg class="h-3.5 w-3.5" fill="currentColor" viewBox="0 0 20 20"
                                xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                <path clip-rule="evenodd" fill-rule="evenodd"
                                    d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" />
                            </svg>
                            Add
                        </button>
                    </div>
                    <ul id="dietaryHistoryList"
                        class="max-w-full space-y-1 text-gray-900 list-disc list-inside dark:text-white text-sm">
                    </ul>
                </div>

                <!-- Edit Patient Modal -->
                <div id="editPatientModal" tabindex="-1" aria-hidden="true"
                    class="fixed inset-0 hidden flex justify-center items-center z-50 bg-gray-600/50">
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg w-full max-w-md p-4">
                        <div class="flex flex-row justify-between items-center mb-4">
                            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Edit Patient Details</h2>
                            <button type="button" onclick="closeModal()"
                                class="cursor-pointer text-gray-500 hover:text-gray-800 dark:hover:text-white">
                                <svg aria-hidden="true" class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"
                                    xmlns="http://www.w3.org/2000/svg">
                                    <path fill-rule="evenodd"
                                        d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                        clip-rule="evenodd"></path>
                                </svg>
                            </button>
                        </div>

                        <form id="editPatientForm">
                            <input type="hidden" id="editPatientId" name="patient_id" value="">

                            <div class="grid grid-cols-2 gap-4 mb-4">
                                <div>
                                    <label class="block mb-2 text-xs font-medium text-gray-900 dark:text-white">First
                                        Name</label>
                                    <input type="text" id="editFirstname" name="firstname" required
                                        class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-sm focus:ring-primary-600 focus:border-primary-600 block w-full p-2 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500">
                                </div>
                                <div>
                                    <label class="block mb-2 text-xs font-medium text-gray-900 dark:text-white">Surname</label>
                                    <input type="text" id="editSurname" name="surname" required
                                        class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-sm focus:ring-primary-600 focus:border-primary-600 block w-full p-2 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500">
                                </div>
                                <div>
                                    <label class="block mb-2 text-xs font-medium text-gray-900 dark:text-white">Middle
                                        Name</label>
                                    <input type="text" id="editMiddlename" name="middlename"
                                        class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-sm focus:ring-primary-600 focus:border-primary-600 block w-full p-2 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500">
                                </div>
                                <div>
                                    <label class="block mb-2 text-xs font-medium text-gray-900 dark:text-white">Date of
                                        Birth</label>
                                    <input type="date" id="editDob" name="date_of_birth" required
                                        class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-sm focus:ring-primary-600 focus:border-primary-600 block w-full p-2 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500">
                                </div>
                            </div>

                            <!-- Age, Sex, Pregnant Section -->
                            <div id="form-container" class="grid grid-cols-1 sm:grid-cols-2 gap-4 w-full mb-4">
                                <!-- Age -->
                                <div class="flex flex-col sm:flex-row gap-2">
                                    <div class="flex-1">
                                        <label for="editAge" class="block mb-2 text-xs font-medium text-gray-900 dark:text-white">Age (Years)</label>
                                        <input type="number" id="editAge" name="age" min="0" required data-label="Age"
                                            class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-sm focus:ring-primary-600 focus:border-primary-600 block w-full p-2 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500">
                                    </div>
                                    <div id="monthContainer" class="flex-1 hidden">
                                        <div class="flex items-center justify-between mb-2">
                                            <label for="editAgemonth" class="block text-xs font-medium text-gray-900 dark:text-white">Months</label>
                                        </div>
                                        <input type="number" id="editAgemonth" name="agemonth" min="0" max="59"
                                            class="bg-gray-100 border border-gray-300 text-gray-900 text-sm rounded-sm focus:ring-primary-600 focus:border-primary-600 block w-full p-2 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus-border-primary-500 cursor-not-allowed">
                                    </div>
                                </div>

                                <!-- Sex -->
                                <div id="sex-wrapper">
                                    <label for="editSex" class="block mb-2 text-xs font-medium text-gray-900 dark:text-white">Sex</label>
                                    <select id="editSex" name="sex" required data-label="Sex"
                                        class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-sm focus:ring-primary-600 focus:border-primary-600 block w-full p-2 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus-border-primary-500">
                                        <option value="">-- Select --</option>
                                        <option value="Male">Male</option>
                                        <option value="Female">Female</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>

                                <!-- Pregnant (hidden by default) -->
                                <div id="editPregnantSection" class="hidden sm:col-span-2">
                                    <label class="block mb-2 text-xs font-medium text-gray-900 dark:text-white">Pregnant</label>
                                    <div class="flex flex-row gap-4 items-center">
                                        <div class="flex items-center">
                                            <input id="editPregnantYes" type="radio" value="yes" name="pregnant"
                                                class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 focus:ring-blue-500">
                                            <label for="editPregnantYes" class="ms-2 text-sm font-medium text-gray-900 dark:text-gray-300">Yes</label>
                                        </div>
                                        <div class="flex items-center">
                                            <input id="editPregnantNo" type="radio" value="no" name="pregnant" checked
                                                class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 focus:ring-blue-500">
                                            <label for="editPregnantNo" class="ms-2 text-sm font-medium text-gray-900 dark:text-gray-300">No</label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-4">
                                <label class="block mb-2 text-xs font-medium text-gray-900 dark:text-white">Place of
                                    Birth</label>
                                <input type="text" id="editPob" name="place_of_birth"
                                    class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-sm focus:ring-primary-600 focus:border-primary-600 block w-full p-2 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500">
                            </div>

                            <div class="mt-4">
                                <label class="block mb-2 text-xs font-medium text-gray-900 dark:text-white">Address</label>
                                <input type="text" id="editAddress" name="address"
                                    class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-sm focus:ring-primary-600 focus:border-primary-600 block w-full p-2 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500">
                            </div>

                            <div class="mt-4 grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block mb-2 text-xs font-medium text-gray-900 dark:text-white">Occupation</label>
                                    <input type="text" id="editOccupation" name="occupation"
                                        class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-sm focus:ring-primary-600 focus:border-primary-600 block w-full p-2 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500">
                                </div>
                                <div>
                                    <label class="block mb-2 text-xs font-medium text-gray-900 dark:text-white">Guardian</label>
                                    <input type="text" id="editGuardian" name="guardian"
                                        class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-sm focus:ring-primary-600 focus:border-primary-600 block w-full p-2 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500">
                                </div>
                            </div>

                            <div class="mt-6 flex justify-end">
                                <button type="submit" name="submit"
                                    class="text-white bg-blue-700 hover:bg-blue-800 font-medium rounded-sm text-sm px-3 py-2">
                                    Save Changes
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- membership -->
                <div id="membershipModal" tabindex="-1" aria-hidden="true"
                    class="fixed inset-0 hidden flex justify-center items-center z-50 bg-gray-600/50">
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg w-full max-w-md p-4">
                        <div class="flex flex-row justify-between items-center mb-4">
                            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Add Membership</h2>
                            <button type="button" id="cancelBtn"
                                class="relative cursor-pointer text-gray-500 hover:text-gray-800 dark:hover:text-white">
                                ✕
                            </button>
                        </div>

                        <form id="membershipForm" class="space-y-4">
                            <input type="hidden" name="patient_id" id="patient_id" value="">

                            <!-- NHTS -->
                            <div class="flex items-center mb-1">
                                <input type="checkbox" value="1" name="nhts_pr" data-field="nhts_pr"
                                    class="w-4 h-4 text-blue-600 border-gray-300 rounded-sm">
                                <label class="ms-2 text-sm dark:text-white">NHTS-PR</label>
                            </div>

                            <!-- 4Ps -->
                            <div class="flex items-center mb-1">
                                <input type="checkbox" value="1" name="four_ps" data-field="four_ps"
                                    class="w-4 h-4 text-blue-600 border-gray-300 rounded-sm">
                                <label class="ms-2 text-sm dark:text-white">Pantawid Pamilyang Pilipino Program (4Ps)</label>
                            </div>

                            <!-- IP -->
                            <div class="flex items-center mb-1">
                                <input type="checkbox" value="1" name="indigenous_people" data-field="indigenous_people"
                                    class="w-4 h-4 text-blue-600 border-gray-300 rounded-sm">
                                <label class="ms-2 text-sm dark:text-white">Indigenous People (IP)</label>
                            </div>

                            <!-- PWD -->
                            <div class="flex items-center mb-1">
                                <input type="checkbox" value="1" name="pwd" data-field="pwd"
                                    class="w-4 h-4 text-blue-600 border-gray-300 rounded-sm">
                                <label class="ms-2 text-sm dark:text-white">Person With Disabilities (PWDs)</label>
                            </div>

                            <!-- PhilHealth -->
                            <div class="flex items-center mb-1">
                                <input type="checkbox" value="1" name="philhealth_flag" data-field="philhealth_flag"
                                    onchange="toggleInput(this, 'philhealth_number')"
                                    class="w-4 h-4 text-blue-600 border-gray-300 rounded-sm">
                                <div class="grid grid-cols-2 items-center gap-4">
                                    <label class="ms-2 text-sm dark:text-white">PhilHealth (Indicate Number)</label>
                                    <input type="text" id="philhealth_number" name="philhealth_number" disabled
                                        class="block py-1 px-0 w-full text-sm border-b-2 border-gray-300 focus:outline-none focus:border-blue-600" />
                                </div>
                            </div>

                            <!-- SSS -->
                            <div class="flex items-center mb-1">
                                <input type="checkbox" value="1" name="sss_flag" data-field="sss_flag"
                                    onchange="toggleInput(this, 'sss_number')"
                                    class="w-4 h-4 text-blue-600 border-gray-300 rounded-sm">
                                <div class="grid grid-cols-2 items-center gap-4">
                                    <label class="ms-2 text-sm dark:text-white">SSS (Indicate Number)</label>
                                    <input type="text" id="sss_number" name="sss_number" disabled
                                        class="block py-1 px-0 w-full text-sm border-b-2 border-gray-300 focus:outline-none focus:border-blue-600" />
                                </div>
                            </div>

                            <!-- GSIS -->
                            <div class="flex items-center mb-1">
                                <input type="checkbox" value="1" name="gsis_flag" data-field="gsis_flag"
                                    onchange="toggleInput(this, 'gsis_number')"
                                    class="w-4 h-4 text-blue-600 border-gray-300 rounded-sm">
                                <div class="grid grid-cols-2 items-center gap-4">
                                    <label class="ms-2 text-sm dark:text-white">GSIS (Indicate Number)</label>
                                    <input type="text" id="gsis_number" name="gsis_number" disabled
                                        class="block py-1 px-0 w-full text-sm border-b-2 border-gray-300 focus:outline-none focus:border-blue-600" />
                                </div>
                            </div>

                            <!-- Save button -->
                            <div class="flex justify-end gap-2">
                                <button type="submit" name="save_membership"
                                    class="px-3 mt-4 cursor-pointer py-1 rounded bg-blue-700 hover:bg-blue-800 text-white text-sm">
                                    Save
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Medical History Modal -->
                <div id="medicalModal" tabindex="-1" aria-hidden="true"
                    class="fixed inset-0 hidden flex justify-center items-center z-50 bg-gray-600/50">
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg w-full max-w-2xl p-6">
                        <div class="flex flex-row justify-between items-center mb-4">
                            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Add / Edit Medical History
                            </h2>
                            <button type="button" id="cancelMedicalBtn"
                                class="relative cursor-pointer text-gray-500 hover:text-gray-800 dark:hover:text-white">
                                ✕
                            </button>
                        </div>

                        <form id="medicalForm" class="space-y-4">
                            <div>
                                <div class="flex w-125  items-center mb-1">
                                    <input type="checkbox" name="allergies_flag" value="1"
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
                                    <input type="checkbox" name="hypertension_cva" value="1"
                                        class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded-sm focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600">
                                    <label for="default-checkbox"
                                        class="ms-2 text-sm font-medium text-gray-900 dark:text-gray-300">Hypertension
                                        / CVA</label>
                                </div>
                                <div class="flex items-center mb-1">
                                    <input type="checkbox" name="diabetes_mellitus" value="1"
                                        class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded-sm focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600">
                                    <label for="default-checkbox"
                                        class="ms-2 text-sm font-medium text-gray-900 dark:text-gray-300">Diabetes
                                        Mellitus</label>
                                </div>
                                <div class="flex items-center mb-1">
                                    <input type="checkbox" name="blood_disorders" value="1"
                                        class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded-sm focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600">
                                    <label for="default-checkbox"
                                        class="ms-2 text-sm font-medium text-gray-900 dark:text-gray-300">Blood
                                        Disorders</label>
                                </div>
                                <div class="flex items-center mb-1">
                                    <input type="checkbox" name="heart_disease" value="1"
                                        class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded-sm focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600">
                                    <label for="default-checkbox"
                                        class="ms-2 text-sm font-medium text-gray-900 dark:text-gray-300">cardiovarscular
                                        / Heart Diseases</label>
                                </div>
                                <div class="flex items-center mb-1">
                                    <input type="checkbox" name="thyroid_disorders" value="1"
                                        class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded-sm focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600">
                                    <label for="default-checkbox"
                                        class="ms-2 text-sm font-medium text-gray-900 dark:text-gray-300">Thyroid
                                        Disorders</label>
                                </div>
                                <div class="flex w-125  items-center mb-1">
                                    <input type="checkbox" name="hepatitis_flag" value="1"
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
                                    <input type="checkbox" name="malignancy_flag" value="1"
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
                                        onchange="toggleHospitalization(this)"
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
                                            <input type="date" id="last_admission_date" name="last_admission_date"
                                                disabled
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
                                        onchange="toggleInput(this, 'blood_transfusion')"
                                        class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded-sm focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600">
                                    <div class="grid grid-cols-2 items-center gap-1">
                                        <label for="default-checkbox"
                                            class="ms-2 not-last-of-type:w-40 text-sm font-medium text-gray-900 dark:text-gray-300">Blood
                                            transfusion (Month & Year)</label>
                                        <input type="date" id="blood_transfusion" name="blood_transfusion"
                                            disabled
                                            class="block py-1 h-4.5 px-0 w-59.5 text-sm text-gray-900 bg-transparent border-0 border-b-2 border-gray-300 appearance-none dark:text-white dark:border-gray-600 dark:focus:border-blue-500 focus:outline-none focus:ring-0 focus:border-blue-600 peer"
                                            placeholder=" " />
                                    </div>
                                </div>
                                <div class="flex w-120 items-center mb-1">
                                    <input type="checkbox" name="tattoo" value="1"
                                        class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded-sm focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600">
                                    <label for="default-checkbox"
                                        class="ms-2 text-sm font-medium text-gray-900 dark:text-gray-300">Tattoo</label>
                                </div>
                                <div class="flex w-125  items-center mb-1">
                                    <input type="checkbox" name="other_conditions_flag" value="1"
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
                            <!-- Buttons -->
                            <div class="flex justify-end  mt-4">
                                <button type="submit"
                                    class="px-3 cursor-pointer py-1 rounded bg-blue-700 hover:bg-blue-800 text-white text-sm">
                                    Save
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Dietary/Habits Modal -->
                <div id="dietaryModal" tabindex="-1" aria-hidden="true"
                    class="fixed inset-0 hidden flex justify-center items-center z-50 bg-gray-600/50">
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg w-full max-w-2xl p-6">
                        <div class="flex flex-row justify-between items-center mb-4">
                            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Add / Edit Dietary Habits /
                                Social History
                            </h2>
                            <button type="button" id="cancelDietaryBtn"
                                class="relative cursor-pointer text-gray-500 hover:text-gray-800 dark:hover:text-white">
                                ✕
                            </button>
                        </div>
                        <form id="dietaryForm" class="space-y-4">
                            <div>
                                <div class="flex items-center mb-1">
                                    <input type="checkbox" name="sugar_flag" value="1"
                                        onchange="toggleInput(this, 'sugar_details')"
                                        class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded-sm focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600">
                                    <div class="grid grid-cols-2 ">
                                        <label for="default-checkbox"
                                            class="ms-2 text-sm  font-medium text-gray-900 dark:text-gray-300">Sugar
                                            Sweetened Beverages/Food (Amount, Frequency & Duration)</label>
                                        <input type="text" id="sugar_details" name="sugar_details" disabled
                                            class="block py-1 h-4.5 px-0  text-sm text-gray-900 bg-transparent border-0 border-b-2 border-gray-300 appearance-none dark:text-white dark:border-gray-600 dark:focus:border-blue-500 focus:outline-none focus:ring-0 focus:border-blue-600 peer"
                                            placeholder=" " />
                                    </div>
                                </div>
                                <div class="flex items-center mb-1">
                                    <input type="checkbox" name="alcohol_flag" value="1"
                                        onchange="toggleInput(this, 'alcohol_details')"
                                        class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded-sm focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600">
                                    <div class="grid grid-cols-2 ">
                                        <label for="default-checkbox"
                                            class="ms-2 text-sm font-medium text-gray-900 dark:text-gray-300">Use
                                            of
                                            Alcohol (Amount, Frequency & Duration)</label>
                                        <input type="text" id="alcohol_details" name="alcohol_details" disabled
                                            class="block py-1 px-0 h-4.5  text-sm text-gray-900 bg-transparent border-0 border-b-2 border-gray-300 appearance-none dark:text-white dark:border-gray-600 dark:focus:border-blue-500 focus:outline-none focus:ring-0 focus:border-blue-600 peer"
                                            placeholder=" " />
                                    </div>
                                </div>
                                <div class="flex items-center mb-1">
                                    <input type="checkbox" name="tobacco_flag" value="1"
                                        onchange="toggleInput(this, 'tobacco_details')"
                                        class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded-sm focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600">
                                    <div class="grid grid-cols-2 ">
                                        <label for="default-checkbox"
                                            class="ms-2 text-sm font-medium text-gray-900 dark:text-gray-300">Use
                                            of
                                            Tobacco (Amount, Frequency & Duration)</label>
                                        <input type="text" id="tobacco_details" name="tobacco_details" disabled
                                            class="block py-1 h-4.5 px-0  text-sm text-gray-900 bg-transparent border-0 border-b-2 border-gray-300 appearance-none dark:text-white dark:border-gray-600 dark:focus:border-blue-500 focus:outline-none focus:ring-0 focus:border-blue-600 peer"
                                            placeholder=" " />
                                    </div>
                                </div>
                                <div class="flex items-center mb-1">
                                    <input type="checkbox" name="betel_nut_flag" value="1"
                                        onchange="toggleInput(this, 'betel_nut_details')"
                                        class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded-sm focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600">
                                    <div class="grid grid-cols-2 ">
                                        <label for="default-checkbox"
                                            class="ms-2 text-sm font-medium text-gray-900 dark:text-gray-300">Betel
                                            Nut Chewing (Amount, Frequency & Duration)</label>
                                        <input type="text" id="betel_nut_details" name="betel_nut_details" disabled
                                            class="block py-1 h-4.5 px-0  text-sm text-gray-900 bg-transparent border-0 border-b-2 border-gray-300 appearance-none dark:text-white dark:border-gray-600 dark:focus:border-blue-500 focus:outline-none focus:ring-0 focus:border-blue-600 peer"
                                            placeholder=" " />
                                    </div>
                                </div>
                            </div>
                            <!-- Buttons -->
                            <div class="flex justify-end  mt-4">
                                <button type="submit"
                                    class="px-3 cursor-pointer py-1 rounded bg-blue-700 hover:bg-blue-800 text-white text-sm">
                                    Save
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Vital Signs Modal -->
                <div id="addVitalModal" tabindex="-1" aria-hidden="true"
                    class="fixed inset-0 hidden flex justify-center items-center z-50 bg-gray-600/50">
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg w-full max-w-2xl p-6">
                        <div class="flex flex-row justify-between items-center mb-4">
                            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Add Vital Signs
                            </h2>
                            <button type="button" id="cancelVital"
                                class="relative cursor-pointer text-gray-500 hover:text-gray-800 dark:hover:text-white">
                                ✕
                            </button>
                        </div>
                        <form id="vitalform" class="space-y-4">
                            <input type="hidden" id="editPatientId" name="patient_id" value="<?= $patient_id ?>">
                            <div>
                                <div class="w-full">
                                    <label for="name"
                                        class="block mb-2 text-xs font-medium text-gray-900 dark:text-white">Blood
                                        Preassure</label>
                                    <input type="text" name="blood_pressure" data-required data-label="Blood Pressure"
                                        class="bg-gray-50 border border-gray-300 text-gray-900 text-xs rounded-sm focus:ring-primary-600 focus:border-primary-600 block w-full p-1 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500"
                                        placeholder="">
                                </div>
                                <div class="w-full">
                                    <label for="name"
                                        class="block mb-2 text-xs font-medium text-gray-900 dark:text-white">Temperature</label>
                                    <input type="number" step="0.1" name="temperature" data-required
                                        data-label="Temperature"
                                        class="bg-gray-50 border border-gray-300 text-gray-900 text-xs rounded-sm focus:ring-primary-600 focus:border-primary-600 block w-full p-1 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500"
                                        placeholder="">
                                </div>
                                <div class="w-full">
                                    <label for="name"
                                        class="block mb-2 text-xs font-medium text-gray-900 dark:text-white">Pulse
                                        Rate</label>
                                    <input type="number" name="pulse_rate" data-required data-label="Pulse Rate"
                                        class="bg-gray-50 border border-gray-300 text-gray-900 text-xs rounded-sm focus:ring-primary-600 focus:border-primary-600 block w-full p-1 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500"
                                        placeholder="">
                                </div>
                                <div class="w-full">
                                    <label for="name"
                                        class="block mb-2 text-xs font-medium text-gray-900 dark:text-white">Weight</label>
                                    <input type="number" step="0.01" name="weight" data-required data-label="Weight"
                                        class="bg-gray-50 border border-gray-300 text-gray-900 text-xs rounded-sm focus:ring-primary-600 focus:border-primary-600 block w-full p-1 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500"
                                        placeholder="">
                                </div>
                            </div>
                            <!-- Buttons -->
                            <div class="flex justify-end  mt-4">
                                <button type="submit"
                                    class="px-3 cursor-pointer py-1 rounded bg-blue-700 hover:bg-blue-800 text-white text-sm">
                                    Save
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                <!-- small notification container -->
                <div id="notice"
                    style="position:fixed; top:14px; right:14px; display:none; padding:10px 14px; border-radius:6px; background:blue; color:white; z-index:60">
                </div>
            </section>
        </main>
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
        let inactivityTime = 1800000; // 10 minutes in ms
        let logoutTimer;

        function resetTimer() {
            clearTimeout(logoutTimer);
            logoutTimer = setTimeout(() => {
                alert("You've been logged out due to 30 minutes of inactivity.");
                window.location.href = "/DentalEMR_System/php/login/logout.php?uid=<?php echo $userId; ?>&";
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
        //  Set the Oral Health Condition link dynamically
        const oralHealthLink = document.getElementById("oralHealthLink");
        if (oralHealthLink && patientId) {
            oralHealthLink.href = `view_oral.php?uid=<?php echo $userId; ?>&id=${encodeURIComponent(patientId)}`;
        } else {
            // Optional fallback: disable link if no patient selected
            oralHealthLink.addEventListener("click", (e) => {
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

    <script>
        function back() {
            location.href = ("treatmentrecords.php?uid=<?php echo $userId; ?>");
        }

        function toggleInput(checkbox, inputId) {
            const input = document.getElementById(inputId);
            if (!input) return;
            input.disabled = !checkbox.checked;
            if (!checkbox.checked) input.value = "";
        }

        function toggleHospitalization(cb) {
            const last = document.getElementById("last_admission_date");
            const cause = document.getElementById("admission_cause");
            const surgery = document.getElementById("surgery_details");

            if (cb.checked) {
                if (last) last.disabled = false;
                if (cause) cause.disabled = false;
                if (surgery) surgery.disabled = false;
            } else {
                if (last) {
                    last.disabled = true;
                    last.value = "";
                }
                if (cause) {
                    cause.disabled = true;
                    cause.value = "";
                }
                if (surgery) {
                    surgery.disabled = true;
                    surgery.value = "";
                }
            }
        }
    </script>

    <!-- Patient + Membership Script -->
    <script>
        // Universal Browser Compatibility Layer
        (function() {
            // Feature detection and polyfills
            if (!window.Promise) {
                console.warn('Promise not supported - loading polyfill');
                var script = document.createElement('script');
                script.src = 'https://cdn.jsdelivr.net/npm/promise-polyfill@8/dist/polyfill.min.js';
                document.head.appendChild(script);
            }

            if (!window.fetch) {
                console.warn('fetch not supported - loading polyfill');
                var script = document.createElement('script');
                script.src = 'https://cdn.jsdelivr.net/npm/whatwg-fetch@3.6.2/dist/fetch.umd.min.js';
                document.head.appendChild(script);
            }

            // Safe date parsing for all browsers
            window.safeParseDate = function(dateString) {
                if (!dateString) return null;

                const formats = [
                    () => {
                        const date = new Date(dateString);
                        return !isNaN(date.getTime()) ? date : null;
                    },
                    () => {
                        const parts = dateString.split('-');
                        if (parts.length === 3) {
                            const date = new Date(parts[0], parts[1] - 1, parts[2]);
                            return !isNaN(date.getTime()) ? date : null;
                        }
                        return null;
                    },
                    () => {
                        const parts = dateString.split('/');
                        if (parts.length === 3) {
                            const date = new Date(parts[2], parts[1] - 1, parts[0]);
                            return !isNaN(date.getTime()) ? date : null;
                        }
                        return null;
                    }
                ];

                for (let format of formats) {
                    const result = format();
                    if (result) return result;
                }

                return null;
            };

            // Universal fetch wrapper with better error handling
            const originalFetch = window.fetch;
            window.fetch = function(url, options = {}) {
                if (!options.method || options.method.toUpperCase() === 'GET') {
                    const separator = url.includes('?') ? '&' : '?';
                    url = url + separator + '_t=' + Date.now();
                }

                if (!options.headers) {
                    options.headers = {};
                }

                Object.assign(options.headers, {
                    'Cache-Control': 'no-cache, no-store, must-revalidate',
                    'Pragma': 'no-cache',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                });

                if (!options.credentials) {
                    options.credentials = 'include';
                }

                const timeout = 30000;

                return Promise.race([
                    originalFetch.call(window, url, options),
                    new Promise((_, reject) =>
                        setTimeout(() => reject(new Error('Request timeout')), timeout)
                    )
                ]).catch(error => {
                    console.error('Fetch error:', error);
                    throw error;
                });
            };

            // Detect browser for specific fixes
            window.getBrowserInfo = function() {
                const ua = navigator.userAgent;
                let browser = 'unknown';

                if (ua.indexOf("Chrome") > -1) {
                    browser = 'chrome';
                } else if (ua.indexOf("Firefox") > -1) {
                    browser = 'firefox';
                } else if (ua.indexOf("Safari") > -1) {
                    browser = 'safari';
                } else if (ua.indexOf("Edge") > -1 || ua.indexOf("Edg") > -1) {
                    browser = 'edge';
                } else if (ua.indexOf("Trident") > -1) {
                    browser = 'ie';
                }

                return browser;
            };

            // Apply browser-specific fixes
            document.addEventListener('DOMContentLoaded', function() {
                const browser = window.getBrowserInfo();
                console.log('Browser detected:', browser);

                switch (browser) {
                    case 'ie':
                    case 'edge':
                        if (!window.console) window.console = {
                            log: function() {},
                            error: function() {}
                        };
                        break;
                    case 'safari':
                        const dateInputs = document.querySelectorAll('input[type="date"]');
                        dateInputs.forEach(input => {
                            input.addEventListener('focus', function() {
                                this.type = 'text';
                                this.type = 'date';
                            });
                        });
                        break;
                }
            });

            // Fix for Date input in all browsers
            document.addEventListener('DOMContentLoaded', function() {
                const dateInputs = document.querySelectorAll('input[type="date"]');
                dateInputs.forEach(input => {
                    if (input.value) {
                        const date = window.safeParseDate(input.value);
                        if (date) {
                            const yyyy = date.getFullYear();
                            const mm = String(date.getMonth() + 1).padStart(2, '0');
                            const dd = String(date.getDate()).padStart(2, '0');
                            input.value = `${yyyy}-${mm}-${dd}`;
                        }
                    }
                });
            });
        })();

        document.addEventListener("DOMContentLoaded", async () => {
            // ========== AGE CALCULATION FUNCTIONALITY (Universal) ==========
            function initializeAgeCalculator() {
                // For both add and edit modals
                const dobFields = document.querySelectorAll('#dob, #editDob');
                const ageInputs = document.querySelectorAll('#age, #editAge');
                const monthInputs = document.querySelectorAll('#agemonth, #editAgemonth');
                const monthContainers = document.querySelectorAll('#monthContainer');
                const sexInputs = document.querySelectorAll('#sex, #editSex');
                const pregnantSections = document.querySelectorAll('#pregnant-section, #editPregnantSection');
                const pregnantRadios = document.querySelectorAll('input[name="pregnant"]');

                // Hide month containers initially
                monthContainers.forEach(container => {
                    if (container) container.style.display = 'none';
                });

                // Function to calculate age from DOB
                function calculateAge(dobString) {
                    if (!dobString) return {
                        years: 0,
                        months: 0,
                        days: 0
                    };

                    const birthDate = window.safeParseDate(dobString);
                    const today = new Date();

                    if (!birthDate || birthDate > today) {
                        return {
                            years: 0,
                            months: 0,
                            days: 0
                        };
                    }

                    let years = today.getFullYear() - birthDate.getFullYear();
                    let months = today.getMonth() - birthDate.getMonth();
                    let days = today.getDate() - birthDate.getDate();

                    // Adjust for negative months
                    if (months < 0) {
                        years--;
                        months += 12;
                    }

                    // Adjust for negative days
                    if (days < 0) {
                        months--;
                        days += new Date(today.getFullYear(), today.getMonth(), 0).getDate();

                        // If months went negative, adjust years
                        if (months < 0) {
                            years--;
                            months += 12;
                        }
                    }

                    return {
                        years: Math.max(0, years),
                        months: Math.max(0, months),
                        days: Math.max(0, days)
                    };
                }

                // Function to update age from DOB
                function updateFromDOB(dobField, ageInput, monthInput, monthContainer) {
                    if (!dobField || !dobField.value) {
                        if (ageInput) ageInput.value = '';
                        if (monthInput) monthInput.value = '';
                        if (monthContainer) monthContainer.style.display = 'none';
                        return;
                    }

                    const {
                        years,
                        months
                    } = calculateAge(dobField.value);

                    // Update age field
                    if (ageInput) {
                        ageInput.value = years;
                    }

                    // Handle month field
                    if (monthContainer && monthInput) {
                        if (years < 5) {
                            monthContainer.style.display = 'block';
                            const totalMonths = (years * 12) + months;
                            // Limit to 59 months (4 years 11 months)
                            monthInput.value = Math.min(totalMonths, 59);
                        } else {
                            monthContainer.style.display = 'none';
                            monthInput.value = '';
                        }
                    }
                }

                // Function to handle manual age input
                function handleManualAgeInput(ageInput, monthInput, monthContainer) {
                    if (!ageInput) return;

                    const years = parseInt(ageInput.value) || 0;

                    if (monthContainer && monthInput) {
                        if (years < 5) {
                            monthContainer.style.display = 'block';
                            // If month is empty and age < 5, set to 0
                            if (!monthInput.value.trim() && years >= 0) {
                                monthInput.value = 0;
                            }
                        } else {
                            monthContainer.style.display = 'none';
                            monthInput.value = '';
                        }
                    }
                }

                // Function to update pregnant section
                function updatePregnantSection(sexInput, ageInput, pregnantSection) {
                    if (!pregnantSection || !sexInput || !ageInput) return;

                    const age = parseInt(ageInput.value) || 0;
                    const sex = sexInput.value;

                    if (sex === 'Female' && age >= 10 && age <= 49) {
                        pregnantSection.classList.remove('hidden');

                        // Find related pregnant radios
                        const relatedPregnantRadios = pregnantSection.querySelectorAll('input[name="pregnant"]');
                        relatedPregnantRadios.forEach(radio => {
                            radio.disabled = false;
                            radio.required = true;
                        });
                    } else {
                        pregnantSection.classList.add('hidden');

                        const relatedPregnantRadios = pregnantSection.querySelectorAll('input[name="pregnant"]');
                        relatedPregnantRadios.forEach(radio => {
                            radio.disabled = true;
                            radio.required = false;
                            if (radio.value === "No" || radio.value === "no") {
                                radio.checked = true;
                            }
                        });
                    }
                }

                // Set up event listeners for each field set
                dobFields.forEach(dobField => {
                    if (dobField) {
                        const formId = dobField.closest('form')?.id;
                        const isEditForm = formId === 'editPatientForm';

                        const ageInput = document.getElementById(isEditForm ? 'editAge' : 'age');
                        const monthInput = document.getElementById(isEditForm ? 'editAgemonth' : 'agemonth');
                        const monthContainer = document.getElementById('monthContainer');
                        const sexInput = document.getElementById(isEditForm ? 'editSex' : 'sex');
                        const pregnantSection = document.getElementById(isEditForm ? 'editPregnantSection' : 'pregnant-section');

                        dobField.addEventListener('change', () => {
                            updateFromDOB(dobField, ageInput, monthInput, monthContainer);
                            if (sexInput && pregnantSection) {
                                updatePregnantSection(sexInput, ageInput, pregnantSection);
                            }
                        });

                        dobField.addEventListener('input', () => {
                            updateFromDOB(dobField, ageInput, monthInput, monthContainer);
                            if (sexInput && pregnantSection) {
                                updatePregnantSection(sexInput, ageInput, pregnantSection);
                            }
                        });

                        // If DOB already has value, calculate immediately
                        if (dobField.value) {
                            setTimeout(() => {
                                updateFromDOB(dobField, ageInput, monthInput, monthContainer);
                                if (sexInput && pregnantSection) {
                                    updatePregnantSection(sexInput, ageInput, pregnantSection);
                                }
                            }, 100);
                        }
                    }
                });

                ageInputs.forEach(ageInput => {
                    if (ageInput) {
                        const formId = ageInput.closest('form')?.id;
                        const isEditForm = formId === 'editPatientForm';

                        const monthInput = document.getElementById(isEditForm ? 'editAgemonth' : 'agemonth');
                        const monthContainer = document.getElementById('monthContainer');
                        const sexInput = document.getElementById(isEditForm ? 'editSex' : 'sex');
                        const pregnantSection = document.getElementById(isEditForm ? 'editPregnantSection' : 'pregnant-section');

                        ageInput.addEventListener('input', () => {
                            handleManualAgeInput(ageInput, monthInput, monthContainer);
                            if (sexInput && pregnantSection) {
                                updatePregnantSection(sexInput, ageInput, pregnantSection);
                            }
                        });

                        ageInput.addEventListener('change', () => {
                            handleManualAgeInput(ageInput, monthInput, monthContainer);
                            if (sexInput && pregnantSection) {
                                updatePregnantSection(sexInput, ageInput, pregnantSection);
                            }
                        });
                    }
                });

                sexInputs.forEach(sexInput => {
                    if (sexInput) {
                        const formId = sexInput.closest('form')?.id;
                        const isEditForm = formId === 'editPatientForm';

                        const ageInput = document.getElementById(isEditForm ? 'editAge' : 'age');
                        const pregnantSection = document.getElementById(isEditForm ? 'editPregnantSection' : 'pregnant-section');

                        sexInput.addEventListener('change', () => {
                            if (ageInput && pregnantSection) {
                                updatePregnantSection(sexInput, ageInput, pregnantSection);
                            }
                        });
                    }
                });

                monthInputs.forEach(monthInput => {
                    if (monthInput) {
                        monthInput.addEventListener('input', function() {
                            const formId = this.closest('form')?.id;
                            const isEditForm = formId === 'editPatientForm';
                            const ageInput = document.getElementById(isEditForm ? 'editAge' : 'age');
                            const monthContainer = document.getElementById('monthContainer');

                            const age = parseInt(ageInput?.value) || 0;
                            if (age < 5 && this.value && monthContainer) {
                                monthContainer.style.display = 'block';
                            }
                        });
                    }
                });

                // Initial update for pregnant sections
                sexInputs.forEach(sexInput => {
                    if (sexInput) {
                        const formId = sexInput.closest('form')?.id;
                        const isEditForm = formId === 'editPatientForm';
                        const ageInput = document.getElementById(isEditForm ? 'editAge' : 'age');
                        const pregnantSection = document.getElementById(isEditForm ? 'editPregnantSection' : 'pregnant-section');

                        if (ageInput && pregnantSection) {
                            updatePregnantSection(sexInput, ageInput, pregnantSection);
                        }
                    }
                });
            }

            // Initialize age calculator when DOM is loaded
            setTimeout(initializeAgeCalculator, 500);

            // Initialize age calculator when edit modal is opened
            const editBtn = document.getElementById("editBtn");
            if (editBtn) {
                editBtn.addEventListener('click', function() {
                    // Small delay to ensure modal is visible
                    setTimeout(initializeAgeCalculator, 100);
                });
            }

            // ========== MAIN PATIENT + MEMBERSHIP FUNCTIONALITY ==========
            const params = new URLSearchParams(window.location.search);
            const patientId = params.get("id");
            const notice = document.getElementById("notice");

            // Toast Message
            window.showNotice = function(message, color = "blue") {
                const notice = document.getElementById("notice");
                if (!notice) {
                    const newNotice = document.createElement('div');
                    newNotice.id = 'notice';
                    newNotice.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                padding: 12px 20px;
                border-radius: 6px;
                color: white;
                z-index: 9999;
                box-shadow: 0 4px 6px rgba(0,0,0,0.1);
                font-family: sans-serif;
                font-size: 14px;
                max-width: 300px;
                word-wrap: break-word;
                display: none;
            `;
                    document.body.appendChild(newNotice);
                }

                const targetNotice = document.getElementById("notice");

                if (targetNotice.timeoutId) {
                    clearTimeout(targetNotice.timeoutId);
                }

                targetNotice.textContent = message;
                targetNotice.style.background = color;
                targetNotice.style.display = "block";
                targetNotice.style.opacity = "1";

                targetNotice.timeoutId = setTimeout(() => {
                    targetNotice.style.opacity = "0";
                    setTimeout(() => {
                        targetNotice.style.display = "none";
                    }, 300);
                }, 5000);
            };

            if (!patientId) {
                showNotice("No patient selected.", "crimson");
                return;
            }

            // Function to show/hide months container based on age
            function toggleMonthsContainer() {
                const ageInput = document.getElementById("editAge");
                const monthContainer = document.getElementById("monthContainer");

                if (!ageInput || !monthContainer) return;

                const age = parseInt(ageInput.value) || 0;

                if (age <= 4 && age >= 0) {
                    monthContainer.style.display = "block";
                    monthContainer.classList.remove("hidden");
                } else {
                    monthContainer.style.display = "none";
                    monthContainer.classList.add("hidden");
                }
            }

            // Function to calculate age from DOB
            function calculateAgeFromDOB(dobString) {
                if (!dobString) return {
                    years: 0,
                    months: 0
                };

                const dob = window.safeParseDate(dobString);
                if (!dob) return {
                    years: 0,
                    months: 0
                };

                const today = new Date();
                let years = today.getFullYear() - dob.getFullYear();
                let months = today.getMonth() - dob.getMonth();

                // Adjust for day of month
                if (today.getDate() < dob.getDate()) {
                    months--;
                }

                // Adjust for negative months
                if (months < 0) {
                    years--;
                    months += 12;
                }

                return {
                    years: Math.max(0, years),
                    months: Math.max(0, months)
                };
            }

            // LOAD PATIENT INFO
            async function loadPatient() {
                try {
                    const params = new URLSearchParams(window.location.search);
                    const patientId = params.get("id");

                    if (!patientId) {
                        showNotice("No patient selected.", "crimson");
                        return;
                    }

                    let result = null;
                    let success = false;

                    // Approach 1: Standard fetch
                    try {
                        const res = await fetch(`/DentalEMR_System/php/treatmentrecords/view_info.php?action=get_patient&patient_id=${patientId}`, {
                            method: 'GET',
                            headers: {
                                'Accept': 'application/json',
                                'Cache-Control': 'no-cache, no-store, must-revalidate'
                            },
                            credentials: 'include'
                        });

                        if (!res.ok) {
                            throw new Error(`HTTP ${res.status}: ${res.statusText}`);
                        }

                        const responseText = await res.text();

                        try {
                            result = JSON.parse(responseText);
                            success = result.success;
                        } catch (jsonError) {
                            console.error('Invalid JSON response:', responseText.substring(0, 200));
                            throw new Error('Server returned invalid response');
                        }
                    } catch (fetchError) {
                        console.warn('Fetch approach failed:', fetchError.message);

                        // Approach 2: XMLHttpRequest
                        try {
                            result = await new Promise((resolve, reject) => {
                                const xhr = new XMLHttpRequest();
                                xhr.open('GET', `/DentalEMR_System/php/treatmentrecords/view_info.php?action=get_patient&patient_id=${patientId}&_t=${Date.now()}`, true);
                                xhr.setRequestHeader('Accept', 'application/json');
                                xhr.setRequestHeader('Cache-Control', 'no-cache');
                                xhr.withCredentials = true;

                                xhr.onload = function() {
                                    if (xhr.status >= 200 && xhr.status < 300) {
                                        try {
                                            const data = JSON.parse(xhr.responseText);
                                            resolve(data);
                                        } catch (e) {
                                            reject(new Error('Invalid JSON in XHR response'));
                                        }
                                    } else {
                                        reject(new Error(`XHR Error: ${xhr.status}`));
                                    }
                                };

                                xhr.onerror = function() {
                                    reject(new Error('Network error in XHR'));
                                };

                                xhr.ontimeout = function() {
                                    reject(new Error('XHR timeout'));
                                };

                                xhr.timeout = 30000;
                                xhr.send();
                            });

                            success = result.success;
                        } catch (xhrError) {
                            console.warn('XHR approach failed:', xhrError.message);
                            showNotice("Cannot connect to server. Please check your connection.", "crimson");
                            return;
                        }
                    }

                    if (!success || !result.patient) {
                        showNotice(result.error || "Patient not found.", "crimson");
                        return;
                    }

                    const p = result.patient;

                    // SAFE DATA EXTRACTION WITH FALLBACKS
                    const safeGet = (obj, key, fallback = 'N/A') => {
                        const value = obj[key];
                        return (value !== null && value !== undefined && value !== '') ? value : fallback;
                    };

                    // Format name safely
                    const firstname = safeGet(p, 'firstname', '');
                    const middlename = safeGet(p, 'middlename', '');
                    const surname = safeGet(p, 'surname', '');

                    let fullName = '';
                    if (firstname || surname) {
                        const middlePart = middlename ? ` ${middlename} ` : ' ';
                        fullName = `${firstname}${middlePart}${surname}`.trim();
                    } else {
                        fullName = 'N/A';
                    }

                    // Fill Display Info with safe values
                    const patientNameElement1 = document.getElementById("patientName");
                    const patientNameElement2 = document.getElementById("patientName2");

                    if (patientNameElement1) {
                        patientNameElement1.textContent = fullName;
                        patientNameElement1.classList.remove("italic");
                    }

                    if (patientNameElement2) {
                        patientNameElement2.textContent = fullName;
                    }

                    // Display age with months (only for ages 0-4)
                    const ageValue = safeGet(p, 'age', '');
                    const monthsValue = safeGet(p, 'months_old', '0');
                    let ageDisplay = 'N/A';

                    if (ageValue !== 'N/A' && ageValue !== '') {
                        const ageNum = parseInt(ageValue) || 0;
                        const monthsNum = parseInt(monthsValue) || 0;

                        if (ageNum <= 4 && monthsNum > 0) {
                            ageDisplay = `${ageNum}y ${monthsNum}m`;
                        } else {
                            ageDisplay = `${ageNum} years old`;
                        }
                    }

                    document.getElementById("patientAge").textContent = ageDisplay;
                    document.getElementById("patientSex").textContent = safeGet(p, 'sex');

                    // Safe date formatting for all browsers
                    const dateOfBirth = safeGet(p, 'date_of_birth');
                    if (dateOfBirth !== 'N/A') {
                        const date = window.safeParseDate(dateOfBirth);
                        if (date) {
                            const options = {
                                year: 'numeric',
                                month: 'long',
                                day: 'numeric'
                            };
                            try {
                                document.getElementById("patientDob").textContent = date.toLocaleDateString('en-PH', options);
                            } catch (e) {
                                document.getElementById("patientDob").textContent = `${date.getDate()}/${date.getMonth() + 1}/${date.getFullYear()}`;
                            }
                        } else {
                            document.getElementById("patientDob").textContent = dateOfBirth;
                        }
                    } else {
                        document.getElementById("patientDob").textContent = 'N/A';
                    }

                    document.getElementById("patientOccupation").textContent = safeGet(p, 'occupation');
                    document.getElementById("patientBirthPlace").textContent = safeGet(p, 'place_of_birth');
                    document.getElementById("patientAddress").textContent = safeGet(p, 'address');
                    document.getElementById("patientGuardian").textContent = safeGet(p, 'guardian');

                    // Fill Edit Modal with safe values
                    document.getElementById("editPatientId").value = p.patient_id || '';
                    document.getElementById("editFirstname").value = safeGet(p, 'firstname', '');
                    document.getElementById("editSurname").value = safeGet(p, 'surname', '');
                    document.getElementById("editMiddlename").value = safeGet(p, 'middlename', '');

                    // Format date for date input (YYYY-MM-DD)
                    if (dateOfBirth !== 'N/A' && dateOfBirth) {
                        const date = window.safeParseDate(dateOfBirth);
                        if (date) {
                            const yyyy = date.getFullYear();
                            const mm = String(date.getMonth() + 1).padStart(2, '0');
                            const dd = String(date.getDate()).padStart(2, '0');
                            document.getElementById("editDob").value = `${yyyy}-${mm}-${dd}`;
                        } else {
                            document.getElementById("editDob").value = dateOfBirth;
                        }
                    } else {
                        document.getElementById("editDob").value = '';
                    }

                    // Handle age - use stored values
                    const ageNum = parseInt(safeGet(p, 'age', '0')) || 0;
                    const monthsNum = parseInt(safeGet(p, 'months_old', '0')) || 0;

                    document.getElementById("editAge").value = ageNum;
                    document.getElementById("editAgemonth").value = monthsNum;

                    // Show/hide months container based on age
                    toggleMonthsContainer();

                    // Handle sex
                    const sexValue = safeGet(p, 'sex', '');
                    document.getElementById("editSex").value = sexValue;

                    // Handle pregnant status
                    const pregnantValue = safeGet(p, 'pregnant', 'no').toLowerCase();
                    if (sexValue === 'Female') {
                        document.getElementById("editPregnantSection").classList.remove("hidden");
                        if (pregnantValue === 'yes') {
                            document.getElementById("editPregnantYes").checked = true;
                            document.getElementById("editPregnantNo").checked = false;
                        } else {
                            document.getElementById("editPregnantYes").checked = false;
                            document.getElementById("editPregnantNo").checked = true;
                        }
                    } else {
                        document.getElementById("editPregnantSection").classList.add("hidden");
                        document.getElementById("editPregnantNo").checked = true;
                    }

                    document.getElementById("editPob").value = safeGet(p, 'place_of_birth', '');
                    document.getElementById("editAddress").value = safeGet(p, 'address', '');
                    document.getElementById("editOccupation").value = safeGet(p, 'occupation', '');
                    document.getElementById("editGuardian").value = safeGet(p, 'guardian', '');

                } catch (err) {
                    console.error('Critical error loading patient:', err);
                    showNotice("Failed to load patient info. Please refresh the page.", "crimson");

                    const fields = [
                        "patientName", "patientName2", "patientSex", "patientAge",
                        "patientDob", "patientOccupation", "patientBirthPlace",
                        "patientAddress", "patientGuardian"
                    ];

                    fields.forEach(fieldId => {
                        const element = document.getElementById(fieldId);
                        if (element) {
                            element.textContent = "Error loading";
                            element.style.color = "#dc2626";
                        }
                    });
                }
            }

            // Universal AJAX Helper for all browsers
            window.ajaxRequest = function(options) {
                return new Promise((resolve, reject) => {
                    const {
                        url,
                        method = 'GET',
                        data = null,
                        headers = {},
                        timeout = 30000
                    } = options;

                    if (window.fetch) {
                        const fetchOptions = {
                            method: method,
                            headers: {
                                'Accept': 'application/json',
                                'Cache-Control': 'no-cache',
                                ...headers
                            },
                            credentials: 'include'
                        };

                        if (data) {
                            if (method === 'POST' || method === 'PUT' || method === 'PATCH') {
                                if (data instanceof FormData) {
                                    fetchOptions.body = data;
                                } else {
                                    fetchOptions.body = JSON.stringify(data);
                                    fetchOptions.headers['Content-Type'] = 'application/json';
                                }
                            }
                        }

                        const controller = new AbortController();
                        const timeoutId = setTimeout(() => controller.abort(), timeout);
                        fetchOptions.signal = controller.signal;

                        fetch(url, fetchOptions)
                            .then(async response => {
                                clearTimeout(timeoutId);
                                const text = await response.text();

                                try {
                                    const json = JSON.parse(text);
                                    resolve(json);
                                } catch (e) {
                                    if (response.ok) {
                                        resolve({
                                            text: text
                                        });
                                    } else {
                                        reject(new Error(`Server error: ${response.status}`));
                                    }
                                }
                            })
                            .catch(error => {
                                clearTimeout(timeoutId);
                                reject(error);
                            });
                    } else {
                        const xhr = new XMLHttpRequest();
                        xhr.open(method, url, true);

                        xhr.setRequestHeader('Accept', 'application/json');
                        xhr.setRequestHeader('Cache-Control', 'no-cache');
                        Object.keys(headers).forEach(key => {
                            xhr.setRequestHeader(key, headers[key]);
                        });

                        xhr.withCredentials = true;
                        xhr.timeout = timeout;

                        xhr.onload = function() {
                            if (xhr.status >= 200 && xhr.status < 300) {
                                try {
                                    const response = JSON.parse(xhr.responseText);
                                    resolve(response);
                                } catch (e) {
                                    resolve({
                                        text: xhr.responseText
                                    });
                                }
                            } else {
                                reject(new Error(`HTTP ${xhr.status}: ${xhr.statusText}`));
                            }
                        };

                        xhr.onerror = function() {
                            reject(new Error('Network error'));
                        };

                        xhr.ontimeout = function() {
                            reject(new Error('Request timeout'));
                        };

                        if (data && (method === 'POST' || method === 'PUT' || method === 'PATCH')) {
                            if (data instanceof FormData) {
                                xhr.send(data);
                            } else {
                                xhr.setRequestHeader('Content-Type', 'application/json');
                                xhr.send(JSON.stringify(data));
                            }
                        } else {
                            xhr.send();
                        }
                    }
                });
            };

            // LOAD MEMBERSHIP INFO
            async function loadMembership() {
                try {
                    const res = await fetch(`/DentalEMR_System/php/treatmentrecords/view_info.php?action=get_membership&patient_id=${patientId}`);
                    const result = await res.json();

                    if (!result.success) {
                        showNotice(result.error || "No membership info found.", "crimson");
                        return;
                    }

                    const m = result.values || {};
                    const list = document.getElementById("membershipList");
                    list.innerHTML = "";

                    (result.membership || []).forEach(item => {
                        const li = document.createElement("li");
                        li.textContent = item.label;
                        list.appendChild(li);
                    });

                    Object.entries(m).forEach(([k, v]) => {
                        const el = document.querySelector(`[name="${k}"]`);
                        if (!el) return;
                        if (el.type === "checkbox") el.checked = v == 1;
                        else el.value = v || "";
                    });

                } catch (err) {
                    console.error(err);
                    showNotice("Failed to load membership info.", "crimson");
                }
            }

            // Add event listeners for age and DOB changes in edit form
            const editAgeInput = document.getElementById("editAge");
            const editDobInput = document.getElementById("editDob");
            const editSexInput = document.getElementById("editSex");

            if (editAgeInput) {
                editAgeInput.addEventListener("input", function() {
                    toggleMonthsContainer();
                    // Trigger pregnant section update
                    const pregnantSection = document.getElementById("editPregnantSection");
                    if (editSexInput && pregnantSection) {
                        const sex = editSexInput.value;
                        const age = parseInt(this.value) || 0;

                        if (sex === 'Female' && age >= 10 && age <= 49) {
                            pregnantSection.classList.remove("hidden");
                        } else {
                            pregnantSection.classList.add("hidden");
                            document.getElementById("editPregnantNo").checked = true;
                        }
                    }
                });

                editAgeInput.addEventListener("change", function() {
                    toggleMonthsContainer();
                });
            }

            if (editDobInput) {
                editDobInput.addEventListener("change", function() {
                    // Calculate age from DOB when DOB changes
                    const dobValue = this.value;
                    if (dobValue) {
                        const calculatedAge = calculateAgeFromDOB(dobValue);
                        const ageInput = document.getElementById("editAge");
                        const monthsInput = document.getElementById("editAgemonth");

                        if (ageInput) {
                            ageInput.value = calculatedAge.years;
                        }

                        // Update months if age is 4 or below
                        if (calculatedAge.years <= 4 && monthsInput) {
                            monthsInput.value = calculatedAge.months;
                        }

                        toggleMonthsContainer();

                        // Update pregnant section
                        const pregnantSection = document.getElementById("editPregnantSection");
                        if (editSexInput && pregnantSection) {
                            const sex = editSexInput.value;
                            const age = calculatedAge.years;

                            if (sex === 'Female' && age >= 10 && age <= 49) {
                                pregnantSection.classList.remove("hidden");
                            } else {
                                pregnantSection.classList.add("hidden");
                                document.getElementById("editPregnantNo").checked = true;
                            }
                        }
                    }
                });
            }

            // Auto-calculate months when age is changed and months field is visible
            if (editAgeInput) {
                editAgeInput.addEventListener("blur", function() {
                    const age = parseInt(this.value) || 0;
                    const monthsInput = document.getElementById("editAgemonth");
                    const dobInput = document.getElementById("editDob");

                    if (age <= 4 && dobInput && dobInput.value && monthsInput) {
                        // If months is empty or 0, calculate from DOB
                        const currentMonths = parseInt(monthsInput.value) || 0;
                        if (currentMonths === 0) {
                            const calculatedAge = calculateAgeFromDOB(dobInput.value);
                            if (calculatedAge.years === age) {
                                monthsInput.value = calculatedAge.months;
                            }
                        }
                    }
                });
            }

            // Add sex change listener for pregnant section
            if (editSexInput) {
                editSexInput.addEventListener("change", function() {
                    const pregnantSection = document.getElementById("editPregnantSection");
                    const ageInput = document.getElementById("editAge");
                    const age = parseInt(ageInput?.value) || 0;

                    if (this.value === "Female" && age >= 10 && age <= 49) {
                        pregnantSection.classList.remove("hidden");
                    } else {
                        pregnantSection.classList.add("hidden");
                        document.getElementById("editPregnantNo").checked = true;
                    }
                });
            }

            await loadPatient();
            await loadMembership();

            // Patient Modal
            const editModal = document.getElementById("editPatientModal");
            if (editModal && document.getElementById("editBtn")) {
                document.getElementById("editBtn").addEventListener("click", () => {
                    editModal.classList.remove("hidden");
                    editModal.classList.add("flex");
                    // Re-initialize age calculator for edit modal
                    setTimeout(initializeAgeCalculator, 50);
                });
            }

            window.closeModal = () => {
                if (editModal) {
                    editModal.classList.add("hidden");
                    editModal.classList.remove("flex");
                }
            };



            // Save Patient Info
            const editPatientForm = document.getElementById("editPatientForm");
            if (editPatientForm) {
                editPatientForm.addEventListener("submit", async e => {
                    e.preventDefault();

                    const submitBtn = e.target.querySelector('[type="submit"]');
                    const originalText = submitBtn.textContent;
                    submitBtn.textContent = "Saving...";
                    submitBtn.disabled = true;

                    try {
                        const formData = new FormData(e.target);
                        const data = {};
                        formData.forEach((value, key) => {
                            data[key] = value;
                        });

                        // Get age and determine if we need months_old
                        const age = parseInt(data['age']) || 0;
                        let months_old = 0;

                        if (age <= 4) {
                            // Use the months value from the form (editable by user)
                            months_old = parseInt(data['agemonth']) || 0;
                        }

                        data['months_old'] = months_old;

                        // Delete agemonth if it's not a database field
                        delete data['agemonth'];

                        // Add action
                        data.action = 'save_patient';
                        data.patient_id = patientId;

                        data.uid = <?php echo json_encode($userId); ?>;
                        data.user_type = <?php echo json_encode($loggedUser['type'] ?? 'System'); ?>;
                        data.user_name = <?php echo json_encode($loggedUser['name'] ?? 'Unknown'); ?>;

                        const result = await window.ajaxRequest({
                            url: '/DentalEMR_System/php/treatmentrecords/view_info.php',
                            method: 'POST',
                            data: data,
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded'
                            }
                        });

                        console.log('Save patient result:', result);

                        if (result.success) {
                            showNotice("Patient updated successfully!", "green");
                            window.closeModal();

                            // Update display immediately
                            const firstname = data['firstname'] || '';
                            const middlename = data['middlename'] || '';
                            const surname = data['surname'] || '';

                            let updatedFullName = '';
                            if (firstname || surname) {
                                const middlePart = middlename ? ` ${middlename} ` : ' ';
                                updatedFullName = `${firstname}${middlePart}${surname}`.trim();
                            }

                            const name1 = document.getElementById("patientName");
                            const name2 = document.getElementById("patientName2");
                            if (name1) name1.textContent = updatedFullName;
                            if (name2) name2.textContent = updatedFullName;

                            // Update age display
                            const ageNum = parseInt(data['age']) || 0;
                            const monthsNum = parseInt(data['months_old']) || 0;
                            let ageDisplay = 'N/A';

                            if (ageNum > 0) {
                                if (ageNum <= 4 && monthsNum > 0) {
                                    ageDisplay = `${ageNum}y ${monthsNum}m`;
                                } else {
                                    ageDisplay = `${ageNum} years`;
                                }
                            }
                            document.getElementById("patientAge").textContent = ageDisplay;

                            // Reload patient data for other fields
                            await loadPatient();
                        } else {
                            showNotice(result.error || "Update failed.", "crimson");
                        }

                    } catch (error) {
                        console.error('Save error:', error);
                        showNotice("Error: " + error.message, "crimson");
                    } finally {
                        submitBtn.textContent = originalText;
                        submitBtn.disabled = false;
                    }
                });
            }

            // Membership Modal
            const membershipModal = document.getElementById("membershipModal");
            if (membershipModal) {
                const addBtn = document.getElementById("addBtn");
                const cancelBtn = document.getElementById("cancelBtn");

                if (addBtn) {
                    addBtn.addEventListener("click", () => {
                        membershipModal.classList.remove("hidden");
                        membershipModal.classList.add("flex");
                    });
                }

                if (cancelBtn) {
                    cancelBtn.addEventListener("click", () => {
                        membershipModal.classList.add("hidden");
                        membershipModal.classList.remove("flex");
                    });
                }
            }

            // Save Membership Info
            const membershipForm = document.getElementById("membershipForm");
            if (membershipForm) {
                membershipForm.addEventListener("submit", async e => {
                    e.preventDefault();

                    const formData = new FormData(e.target);

                    formData.append("patient_id", patientId);
                    formData.append("action", "save_membership");

                    formData.append("uid", <?php echo json_encode($userId); ?>);
                    formData.append("user_type", <?php echo json_encode($loggedUser['type'] ?? 'System'); ?>);
                    formData.append("user_name", <?php echo json_encode($loggedUser['name'] ?? 'Unknown'); ?>);
                    console.log('Membership FormData:');
                    for (let [key, value] of formData.entries()) {
                        console.log(key, ':', value);
                    }

                    try {
                        const res = await fetch(`/DentalEMR_System/php/treatmentrecords/view_info.php`, {
                            method: "POST",
                            body: formData
                        });

                        console.log('Response status:', res.status);

                        const responseText = await res.text();
                        console.log('Response text:', responseText.substring(0, 500));

                        if (!res.ok) {
                            try {
                                const errorData = JSON.parse(responseText);
                                showNotice("Error: " + (errorData.error || `HTTP ${res.status}`), "crimson");
                            } catch {
                                showNotice(`HTTP error ${res.status}: ${responseText.substring(0, 100)}`, "crimson");
                            }
                            return;
                        }

                        try {
                            const result = JSON.parse(responseText);
                            console.log('Response result:', result);

                            if (result.success) {
                                showNotice("Membership updated successfully!", "blue");
                                if (membershipModal) {
                                    membershipModal.classList.add("hidden");
                                }
                                await loadMembership();
                            } else {
                                showNotice(result.error || "Failed to update membership.", "crimson");
                            }
                        } catch (jsonError) {
                            console.error('JSON parse error:', jsonError);
                            showNotice("Invalid server response. Check console for details.", "crimson");
                        }
                    } catch (error) {
                        console.error('Network error:', error);
                        showNotice("Network error: " + error.message, "crimson");
                    }
                });
            }
        });
    </script>

    <!-- Vital Signs -->
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const addBtn = document.getElementById('addVitalbtn');
            const modal = document.getElementById('addVitalModal');
            const cancelBtn = document.getElementById('cancelVital');
            const vitalForm = document.getElementById('vitalform');

            const bpTable = document.getElementById('bpTableBody');
            const tempTable = document.getElementById('tempTableBody');
            const pulseTable = document.getElementById('pulseTableBody');
            const weightTable = document.getElementById('weightTableBody');
            const patientInput = document.getElementById('editPatientId');
            const notice = document.getElementById('notice');

            // Notification message function
            function showNotice(message, color = "blue") {
                notice.textContent = message;
                notice.style.background = color;
                notice.style.display = "block";
                notice.style.opacity = "1";

                setTimeout(() => {
                    notice.style.transition = "opacity 0.6s";
                    notice.style.opacity = "0";
                    setTimeout(() => {
                        notice.style.display = "none";
                        notice.style.transition = "";
                    }, 1500);
                }, 5000);
            }

            // Ensure patient_id is set from URL if missing
            const urlParams = new URLSearchParams(window.location.search);
            const urlPatientId = urlParams.get('id');
            if (patientInput && (!patientInput.value || patientInput.value.trim() === '')) {
                patientInput.value = urlPatientId;
            }

            // Modal open/close
            addBtn.addEventListener('click', () => modal.classList.remove('hidden'));
            cancelBtn.addEventListener('click', () => {
                modal.classList.add('hidden');
                vitalForm.reset();
            });

            // Save new vital signs
            vitalForm.addEventListener('submit', async (e) => {
                e.preventDefault();

                const formData = new FormData(vitalForm);
                const data = new URLSearchParams();
                data.append('action', 'save_vitals');
                data.append('patient_id', patientInput.value);
                data.append('blood_pressure', formData.get('blood_pressure'));
                data.append('pulse_rate', formData.get('pulse_rate'));
                data.append('temperature', formData.get('temperature'));
                data.append('weight', formData.get('weight'));

                data.append('uid', <?php echo json_encode($userId); ?>);
                data.append('user_type', <?php echo json_encode($loggedUser['type'] ?? 'System'); ?>);
                data.append('user_name', <?php echo json_encode($loggedUser['name'] ?? 'Unknown'); ?>);
                try {
                    const res = await fetch('/DentalEMR_System/php/treatmentrecords/view_info.php', {
                        method: 'POST',
                        body: data
                    });
                    const result = await res.json();

                    if (result.success) {
                        modal.classList.add('hidden');
                        vitalForm.reset();
                        showNotice('Vital signs added successfully!', 'blue');
                        fetchVitals();
                    } else {
                        showNotice('Failed to add vital signs: ' + (result.message || ''), 'red');
                    }
                } catch (err) {
                    console.error('Error saving vitals:', err);
                    showNotice('Error adding vital signs.', 'red');
                }
            });

            // Fetch and display vitals
            async function fetchVitals() {
                if (!patientInput.value) return;
                try {
                    const res = await fetch(`/DentalEMR_System/php/treatmentrecords/view_info.php?action=get_vitals&patient_id=${patientInput.value}`);
                    const data = await res.json();

                    bpTable.innerHTML = tempTable.innerHTML = pulseTable.innerHTML = weightTable.innerHTML = '';

                    if (!data.success || !Array.isArray(data.vitals) || data.vitals.length === 0) {
                        const emptyRow = `<tr><td colspan="2" class="text-center text-gray-400 py-2">No vital signs recorded.</td></tr>`;
                        bpTable.innerHTML = tempTable.innerHTML = pulseTable.innerHTML = weightTable.innerHTML = emptyRow;
                        return;
                    }

                    data.vitals.forEach(v => {
                        const d = new Date(v.recorded_at);
                        const recorded = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;

                        bpTable.innerHTML += `<tr class="flex items-center justify-between border-b w-full dark:bg-gray-800 dark:border-gray-700 border-gray-200">
                    <td class="px-3 py-1">${recorded}</td><td class="px-3 py-1 text-right">${v.blood_pressure}</td></tr>`;

                        tempTable.innerHTML += `<tr class="flex items-center justify-between border-b w-full dark:bg-gray-800 dark:border-gray-700 border-gray-200">
                    <td class="px-3 py-1">${recorded}</td><td class="px-3 py-1 text-right">${v.temperature}</td></tr>`;

                        pulseTable.innerHTML += `<tr class="flex items-center justify-between border-b w-full dark:bg-gray-800 dark:border-gray-700 border-gray-200">
                    <td class="px-3 py-1">${recorded}</td><td class="px-3 py-1 text-right">${v.pulse_rate}</td></tr>`;

                        weightTable.innerHTML += `<tr class="flex items-center justify-between border-b w-full dark:bg-gray-800 dark:border-gray-700 border-gray-200">
                    <td class="px-3 py-1">${recorded}</td><td class="px-3 py-1 text-right">${v.weight}</td></tr>`;
                    });
                } catch (err) {
                    console.error('Failed to fetch vitals:', err);
                    showNotice('Failed to load vital signs.', 'red');
                }
            }

            //  Fetch on page load
            fetchVitals();
        });
    </script>

    <!-- Medical History & dietary Habits -->
    <script>
        // Medical History & dietary Habits - IMPROVED VERSION
        document.addEventListener("DOMContentLoaded", () => {
            const urlParams = new URLSearchParams(window.location.search);
            const patient_id = urlParams.get("id");
            if (!patient_id) return;

            const notice = document.getElementById("notice");

            // Notification Function
            function showNotice(message, color = "blue") {
                notice.textContent = message;
                notice.style.background = color;
                notice.style.display = "block";
                notice.style.opacity = "1";

                setTimeout(() => {
                    notice.style.transition = "opacity 0.6s";
                    notice.style.opacity = "0";
                    setTimeout(() => {
                        notice.style.display = "none";
                        notice.style.transition = "";
                    }, 1500);
                }, 3000);
            }

            // Toggle function for enabling/disabling inputs
            window.toggleInput = (checkbox, targetId) => {
                const target = document.getElementById(targetId);
                if (!target) return;
                target.disabled = !checkbox.checked;
                if (!checkbox.checked) target.value = "";
            };

            window.toggleHospitalization = (cb) => {
                const last = document.getElementById("last_admission_date");
                const cause = document.getElementById("admission_cause");
                const surgery = document.getElementById("surgery_details");

                if (cb.checked) {
                    if (last) last.disabled = false;
                    if (cause) cause.disabled = false;
                    if (surgery) surgery.disabled = false;
                } else {
                    if (last) {
                        last.disabled = true;
                        last.value = "";
                    }
                    if (cause) {
                        cause.disabled = true;
                        cause.value = "";
                    }
                    if (surgery) {
                        surgery.disabled = true;
                        surgery.value = "";
                    }
                }
            };

            // MEDICAL HISTORY SECTION
            const medicalList = document.querySelector("#medicalHistoryList");
            const medicalModal = document.getElementById("medicalModal");
            const addMedicalBtn = document.getElementById("addMedicalHistoryBtn");
            const cancelMedicalBtn = document.getElementById("cancelMedicalBtn");
            const medicalModalForm = document.getElementById("medicalForm");

            function loadMedicalHistory() {
                fetch(`/DentalEMR_System/php/treatmentrecords/view_info.php?action=get_medical&patient_id=${patient_id}`)
                    .then(res => res.json())
                    .then(data => {
                        medicalList.innerHTML = "";
                        if (data.success && data.medical.length > 0) {
                            data.medical.forEach(item => {
                                const li = document.createElement("li");
                                li.textContent = item.label;
                                medicalList.appendChild(li);
                            });
                        } else {
                            medicalList.innerHTML = `<li class="text-gray-500">No medical history recorded.</li>`;
                        }
                    })
                    .catch(err => {
                        console.error("Error loading medical history:", err);
                        showNotice("Error loading medical history.", "red");
                    });
            }

            // Show modal and load existing values
            addMedicalBtn?.addEventListener("click", () => {
                medicalModal.classList.remove("hidden");
                loadMedicalFormValues();
            });

            // Hide modal when clicking cancel or outside
            cancelMedicalBtn?.addEventListener("click", () => medicalModal.classList.add("hidden"));
            medicalModal?.addEventListener("click", (e) => {
                if (e.target === medicalModal) medicalModal.classList.add("hidden");
            });

            // Fetch existing medical history values when opening modal
            function loadMedicalFormValues() {
                fetch(`/DentalEMR_System/php/treatmentrecords/view_info.php?action=get_medical&patient_id=${patient_id}`)
                    .then(res => res.json())
                    .then(data => {
                        if (data.success && data.values) {
                            populateMedicalModalForm(data.values);
                        }
                    })
                    .catch(err => {
                        console.error("Error loading medical form:", err);
                        showNotice("Failed to load medical form.", "red");
                    });
            }

            function populateMedicalModalForm(values) {
                if (!values) return;

                // Handle all checkboxes
                const checkboxes = medicalModalForm.querySelectorAll('input[type="checkbox"]');
                checkboxes.forEach(checkbox => {
                    const fieldName = checkbox.name;
                    if (values[fieldName] !== undefined) {
                        checkbox.checked = values[fieldName] == 1;
                        // Trigger change event to enable/disable associated inputs
                        checkbox.dispatchEvent(new Event('change'));
                    }
                });

                // Handle all text inputs
                const textInputs = medicalModalForm.querySelectorAll('input[type="text"], input[type="date"]');
                textInputs.forEach(input => {
                    const fieldName = input.name;
                    if (values[fieldName] !== undefined) {
                        input.value = values[fieldName] || '';
                    }
                });
            }

            // Save medical history from modal submit
            medicalModalForm?.addEventListener("submit", async (e) => {
                e.preventDefault();

                // Create FormData from the form
                const formData = new FormData(medicalModalForm);

                // Add action and patient_id
                formData.append("action", "save_medical");
                formData.append("patient_id", patient_id);

                formData.append("uid", <?php echo json_encode($userId); ?>);
                formData.append("user_type", <?php echo json_encode($loggedUser['type'] ?? 'System'); ?>);
                formData.append("user_name", <?php echo json_encode($loggedUser['name'] ?? 'Unknown'); ?>);
                // Handle checkbox values properly
                const checkboxes = medicalModalForm.querySelectorAll('input[type="checkbox"]');
                checkboxes.forEach(checkbox => {
                    formData.set(checkbox.name, checkbox.checked ? '1' : '0');
                });

                console.log('Medical FormData:');
                for (let [key, value] of formData.entries()) {
                    console.log(key, ':', value);
                }

                try {
                    const res = await fetch(`/DentalEMR_System/php/treatmentrecords/view_info.php`, {
                        method: "POST",
                        body: formData
                    });

                    console.log('Medical Response status:', res.status);

                    // Get response text first
                    const responseText = await res.text();
                    console.log('Medical Response:', responseText.substring(0, 500));

                    if (!res.ok) {
                        // Try to parse as JSON for error details
                        try {
                            const errorData = JSON.parse(responseText);
                            showNotice("Medical Error: " + (errorData.error || `HTTP ${res.status}`), "red");
                        } catch {
                            showNotice(`Medical HTTP error ${res.status}`, "red");
                        }
                        return;
                    }

                    // Try to parse the successful response as JSON
                    try {
                        const result = JSON.parse(responseText);
                        console.log('Medical Result:', result);

                        if (result.success) {
                            showNotice("Medical history saved successfully!", "blue");
                            medicalModal.classList.add("hidden");
                            loadMedicalHistory();
                        } else {
                            showNotice("Medical Error: " + (result.error || "Failed to save"), "red");
                        }
                    } catch (jsonError) {
                        console.error('Medical JSON parse error:', jsonError);
                        showNotice("Invalid server response for medical history.", "red");
                    }
                } catch (error) {
                    console.error('Medical Network error:', error);
                    showNotice("Network error: " + error.message, "red");
                }
            });

            // DIETARY / SOCIAL HISTORY SECTION
            const dietaryModal = document.getElementById("dietaryModal");
            const addDietaryBtn = document.getElementById("addDietaryHistoryBtn");
            const cancelDietaryBtn = document.getElementById("cancelDietaryBtn");
            const dietaryForm = document.getElementById("dietaryForm");
            const dietaryList = document.getElementById("dietaryHistoryList");

            // Show/Hide Modal
            addDietaryBtn?.addEventListener("click", () => {
                dietaryModal.classList.remove("hidden");
                loadDietaryFormValues();
            });

            cancelDietaryBtn?.addEventListener("click", () => dietaryModal.classList.add("hidden"));
            dietaryModal?.addEventListener("click", (e) => {
                if (e.target === dietaryModal) dietaryModal.classList.add("hidden");
            });

            // Fetch and display dietary/social history
            function loadDietaryHistory() {
                fetch(`/DentalEMR_System/php/treatmentrecords/view_info.php?action=get_dietary&patient_id=${patient_id}`)
                    .then(res => res.json())
                    .then(data => {
                        dietaryList.innerHTML = "";
                        if (data.success && data.dietary.length > 0) {
                            data.dietary.forEach(item => {
                                const li = document.createElement("li");
                                li.textContent = item.label;
                                dietaryList.appendChild(li);
                            });
                        } else {
                            dietaryList.innerHTML = `<li class="text-gray-500">No dietary/social history recorded.</li>`;
                        }
                    })
                    .catch(err => {
                        console.error("Error loading dietary history:", err);
                        showNotice("Error loading dietary/social history.", "red");
                    });
            }

            // Populate form values when opening modal
            function loadDietaryFormValues() {
                fetch(`/DentalEMR_System/php/treatmentrecords/view_info.php?action=get_dietary&patient_id=${patient_id}`)
                    .then(res => res.json())
                    .then(data => {
                        if (data.success && data.values) {
                            populateDietaryForm(data.values);
                        }
                    })
                    .catch(err => {
                        console.error("Error loading dietary form:", err);
                        showNotice("Failed to load dietary/social form.", "red");
                    });
            }

            function populateDietaryForm(values) {
                if (!values) return;

                // Handle all checkboxes
                const checkboxes = dietaryForm.querySelectorAll('input[type="checkbox"]');
                checkboxes.forEach(checkbox => {
                    const fieldName = checkbox.name;
                    if (values[fieldName] !== undefined) {
                        checkbox.checked = values[fieldName] == 1;
                        // Trigger change event to enable/disable associated inputs
                        checkbox.dispatchEvent(new Event('change'));
                    }
                });

                // Handle all text inputs
                const textInputs = dietaryForm.querySelectorAll('input[type="text"]');
                textInputs.forEach(input => {
                    const fieldName = input.name;
                    if (values[fieldName] !== undefined) {
                        input.value = values[fieldName] || '';
                    }
                });
            }

            // Save dietary/social history
            dietaryForm?.addEventListener("submit", async (e) => {
                e.preventDefault();

                // Create FormData from the form
                const formData = new FormData(dietaryForm);

                // Add action and patient_id
                formData.append("action", "save_dietary");
                formData.append("patient_id", patient_id);

                // Get user information from PHP session
                formData.append("uid", <?php echo json_encode($userId); ?>);
                formData.append("user_type", <?php echo json_encode($loggedUser['type'] ?? 'System'); ?>);
                formData.append("user_name", <?php echo json_encode($loggedUser['name'] ?? 'Unknown'); ?>);

                // Handle checkbox values properly
                const checkboxes = dietaryForm.querySelectorAll('input[type="checkbox"]');
                checkboxes.forEach(checkbox => {
                    formData.set(checkbox.name, checkbox.checked ? '1' : '0');
                });

                console.log('Dietary FormData:');
                for (let [key, value] of formData.entries()) {
                    console.log(key, ':', value);
                }

                try {
                    const res = await fetch(`/DentalEMR_System/php/treatmentrecords/view_info.php`, {
                        method: "POST",
                        body: formData
                    });

                    console.log('Dietary Response status:', res.status);

                    // Get response text first
                    const responseText = await res.text();
                    console.log('Dietary Response:', responseText.substring(0, 500));

                    if (!res.ok) {
                        // Try to parse as JSON for error details
                        try {
                            const errorData = JSON.parse(responseText);
                            showNotice("Dietary Error: " + (errorData.error || `HTTP ${res.status}`), "red");
                        } catch {
                            showNotice(`Dietary HTTP error ${res.status}`, "red");
                        }
                        return;
                    }

                    // Try to parse the successful response as JSON
                    try {
                        const result = JSON.parse(responseText);
                        console.log('Dietary Result:', result);

                        if (result.success) {
                            showNotice("Dietary/Social history saved successfully!", "blue");
                            dietaryModal.classList.add("hidden");
                            loadDietaryHistory();
                        } else {
                            showNotice("Dietary Error: " + (result.error || "Failed to save"), "red");
                        }
                    } catch (jsonError) {
                        console.error('Dietary JSON parse error:', jsonError);
                        showNotice("Invalid server response for dietary history.", "red");
                    }
                } catch (error) {
                    console.error('Dietary Network error:', error);
                    showNotice("Network error: " + error.message, "red");
                }
            });

            // INITIAL LOAD
            loadMedicalHistory();
            loadDietaryHistory();
        });
    </script>


</body>

</html>