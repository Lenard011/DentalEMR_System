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
                        // Check sessionStorage first
                        const sessionData = sessionStorage.getItem('dentalemr_current_user');
                        if (sessionData) {
                            const user = JSON.parse(sessionData);
                            if (user && user.isOffline) {
                                console.log('Valid offline session detected:', user.email);
                                return true;
                            }
                        }
                        
                        // Fallback: check localStorage for offline users
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
                window.location.href = '/dentalemr_system/html/treatmentrecords/treatmentrecords.php?offline=true';
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
                window.location.href = '/dentalemr_system/html/treatmentrecords/treatmentrecords.php?offline=true';
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
                    window.location.href = '/dentalemr_system/html/treatmentrecords/treatmentrecords.php?offline=true';
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
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .offline-indicator {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: white;
            padding: 4px 12px;
            border-radius: 6px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            animation: pulse 2s infinite;
            font-size: 0.875rem;
        }

        @keyframes pulse {

            0%,
            100% {
                opacity: 1;
            }

            50% {
                opacity: 0.8;
            }
        }

        .offline-card {
            border-left: 4px solid #f59e0b;
            background: #fffbeb;
        }

        #syncToast {
            transition: all 0.3s ease;
        }

        /* Sync notification styles */
        .sync-notification {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            padding: 12px 20px;
            border-radius: 8px;
            color: white;
            font-weight: 500;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            display: flex;
            align-items: center;
            gap: 8px;
            max-width: 350px;
        }

        .sync-success {
            background: linear-gradient(135deg, #10b981, #059669);
        }

        .sync-warning {
            background: linear-gradient(135deg, #f59e0b, #d97706);
        }

        .sync-error {
            background: linear-gradient(135deg, #ef4444, #dc2626);
        }

        .sync-info {
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
        }
    </style>
</head>

<body>
    <!-- Add connection status indicator -->
    <div id="connectionStatus" class="hidden fixed top-4 right-4 z-60"></div>

    <!-- Sync toast notification -->
    <div id="syncToast" class="hidden fixed top-4 right-4 z-60"></div>

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
                        <a href="../index.php?uid=<?php echo $isOfflineMode ? 'offline' : $userId;
                                                    echo $isOfflineMode ? '&offline=true' : ''; ?>"
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
                        <a href="../addpatient.php?uid=<?php echo $isOfflineMode ? 'offline' : $userId;
                                                        echo $isOfflineMode ? '&offline=true' : ''; ?>"
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
                                <a href="../addpatienttreatment/patienttreatment.php?uid=<?php echo $isOfflineMode ? 'offline' : $userId;
                                                                                            echo $isOfflineMode ? '&offline=true' : ''; ?>"
                                    class="flex items-center p-2 pl-11 w-full text-base font-medium text-gray-900 rounded-lg transition duration-75 group hover:bg-gray-100 dark:text-white dark:hover:bg-gray-700">Add
                                    Patient Treatment</a>
                            </li>
                        </ul>
                    </li>
                </ul>
                <ul class="pt-5 mt-5 space-y-2 border-t border-gray-200 dark:border-gray-700">
                    <li>
                        <a href="../reports/targetclientlist.php?uid=<?php echo $isOfflineMode ? 'offline' : $userId;
                                                                        echo $isOfflineMode ? '&offline=true' : ''; ?>"
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
                        <a href="../reports/mho_ohp.php?uid=<?php echo $isOfflineMode ? 'offline' : $userId;
                                                            echo $isOfflineMode ? '&offline=true' : ''; ?>"
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
                        <a href="../reports/oralhygienefindings.php?uid=<?php echo $isOfflineMode ? 'offline' : $userId;
                                                                        echo $isOfflineMode ? '&offline=true' : ''; ?>"
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
                        <a href="../archived.php?uid=<?php echo $isOfflineMode ? 'offline' : $userId;
                                                        echo $isOfflineMode ? '&offline=true' : ''; ?>"
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
            <section class="bg-gray-50 dark:bg-gray-900 p-3 sm:p-5 ">
                <div class="mx-auto max-w-screen-xl px-4 lg:px-12">
                    <div class="bg-white dark:bg-gray-800 relative shadow-md sm:rounded-lg overflow">
                        <div>
                            <p class="text-2xl font-semibold px-5 mt-5 text-gray-900 dark:text-white">Patient Treatment
                                Record List</p>
                        </div>
                        <div
                            class="flex flex-col md:flex-row items-center justify-between space-y-3 md:space-y-0 md:space-x-4 p-4">
                            <div class="w-full md:w-1/2">
                                <form class="flex items-center">
                                    <label for="simple-search" class="sr-only">Search</label>
                                    <div class="relative w-full">
                                        <div
                                            class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                            <svg aria-hidden="true" class="w-5 h-5 text-gray-500 dark:text-gray-400"
                                                fill="currentColor" viewbox="0 0 20 20"
                                                xmlns="http://www.w3.org/2000/svg">
                                                <path fill-rule="evenodd"
                                                    d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z"
                                                    clip-rule="evenodd" />
                                            </svg>
                                        </div>
                                        <input type="text" id="simple-search"
                                            class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block w-full pl-10 p-2 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500"
                                            placeholder="Search" required="">
                                    </div>
                                </form>
                            </div>
                            <div
                                class="w-full md:w-auto flex flex-col md:flex-row space-y-2 md:space-y-0 items-stretch md:items-center justify-end md:space-x-3 flex-shrink-0">
                                <!-- Filter -->
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
                                            <li class="flex items-center">
                                                <input id="fitbit" type="checkbox" value=""
                                                    class="w-4 h-4 bg-gray-100 border-gray-300 rounded text-primary-600  dark:bg-gray-600 dark:border-gray-500">
                                                <label for="fitbit"
                                                    class="ml-2 text-sm font-medium text-gray-900 dark:text-gray-100">Payompon</label>
                                            </li>
                                            <li class="flex items-center">
                                                <input id="razor" type="checkbox" value=""
                                                    class="w-4 h-4 bg-gray-100 border-gray-300 rounded text-primary-600  dark:bg-gray-600 dark:border-gray-500">
                                                <label for="razor"
                                                    class="ml-2 text-sm font-medium text-gray-900 dark:text-gray-100">Poblacion
                                                    1</label>
                                            </li>
                                            <li class="flex items-center">
                                                <input id="nikon" type="checkbox" value=""
                                                    class="w-4 h-4 bg-gray-100 border-gray-300 rounded text-primary-600  dark:bg-gray-600 dark:border-gray-500">
                                                <label for="nikon"
                                                    class="ml-2 text-sm font-medium text-gray-900 dark:text-gray-100">Poblacion
                                                    2</label>
                                            </li>
                                            <li class="flex items-center">
                                                <input id="benq" type="checkbox" value=""
                                                    class="w-4 h-4 bg-gray-100 border-gray-300 rounded text-primary-600  dark:bg-gray-600 dark:border-gray-500">
                                                <label for="benq"
                                                    class="ml-2 text-sm font-medium text-gray-900 dark:text-gray-100">Poblacion
                                                    3</label>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- Table -->
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm text-left text-gray-700 dark:text-gray-400">
                                <thead
                                    class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
                                    <tr class="">
                                        <th scope="col" class="px-4 py-3 text-center">ID</th>
                                        <th scope="col" class="px-4 py-3 text-center">Name</th>
                                        <th scope="col" class="px-4 py-3 text-center">Sex</th>
                                        <th scope="col" class="px-4 py-3 text-center">Age</th>
                                        <th scope="col" class="px-4 py-3 text-center">Address</th>
                                        <th scope="col" class="py-3 text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="patients-body">
                                    <tr class="border-b dark:border-gray-700">
                                        <td
                                            class="px-4 py-3 text-center font-medium text-gray-900 whitespace-nowrap dark:text-white">
                                            Loading ...
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <nav id="paginationNav"
                            class="flex flex-col md:flex-row justify-between items-start md:items-center space-y-3 md:space-y-0 p-3"
                            aria-label="Table navigation">
                        </nav>
                    </div>
                </div>
            </section>
        </main>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/flowbite@3.1.2/dist/flowbite.min.js"></script>
    <script src="../js/tailwind.config.js"></script>
    <!-- Load offline storage -->
    <script src="/dentalemr_system/js/offline-storage.js"></script>
    </script>

    <script>
        // ========== ULTRA-FAST TABLE SYSTEM ==========
        let allPatients = [];
        let currentPage = 1;
        const rowsPerPage = 20; // Increased for better UX
        let isTableLoading = false;
        let lastFetchTime = 0;
        const CACHE_TTL = 15000; // 15 seconds cache (reduced)

        // Initialize with immediate cached render
        document.addEventListener("DOMContentLoaded", () => {
            // 1. Try to load from cache immediately (0ms delay)
            const cached = localStorage.getItem('dentalemr_cached_patients');
            if (cached) {
                try {
                    const data = JSON.parse(cached);
                    if (Array.isArray(data) && data.length > 0) {
                        allPatients = data.map(p => ({
                            ...p,
                            isOffline: false
                        }));
                        renderPatients(); // Render immediately from cache
                        console.log('Rendered from cache:', allPatients.length, 'patients');
                    }
                } catch (e) {
                    console.warn('Cache parse error:', e);
                }
            }

            // 2. Start async fetch in background
            setTimeout(() => loadPatients(1, true), 10); // 10ms delay

            // 3. Setup search
            const searchInput = document.getElementById("simple-search");
            if (searchInput) {
                searchInput.addEventListener('input', (e) => {
                    setTimeout(() => {
                        currentPage = 1;
                        renderPatients();
                    }, 200);
                });
            }

            // 4. Setup filters
            setupAddressFilters();

            // 5. Setup connection monitoring (non-blocking)
            setTimeout(setupConnectionMonitoring, 100);
        });

        // Optimized patient loading
        async function loadPatients(page = 1, force = false) {
            if (isTableLoading && !force) return;

            currentPage = page;
            isTableLoading = true;

            const now = Date.now();

            // Only fetch from server if cache is stale or force refresh
            if (force || (now - lastFetchTime > CACHE_TTL) || allPatients.length === 0) {
                try {
                    // Show minimal loading indicator
                    const tbody = document.getElementById("patients-body");
                    if (tbody && tbody.children.length <= 1) {
                        tbody.innerHTML = `
                <tr>
                    <td colspan="6" class="text-center py-3">
                        <div class="inline-block h-4 w-4 animate-spin rounded-full border-2 border-solid border-blue-600 border-r-transparent"></div>
                        <span class="ml-2 text-sm">Loading...</span>
                    </div>
                </td>
                </tr>`;
                    }

                    // Fetch with timeout
                    const controller = new AbortController();
                    const timeoutId = setTimeout(() => controller.abort(), 5000);

                    const url = `/DentalEMR_System/php/treatmentrecords/treatment.php?page=${page}&limit=100&_=${Date.now()}`;
                    const response = await fetch(url, {
                        signal: controller.signal,
                        headers: {
                            'Cache-Control': 'no-cache, no-store, must-revalidate',
                            'Pragma': 'no-cache'
                        }
                    });

                    clearTimeout(timeoutId);

                    if (!response.ok) throw new Error(`HTTP ${response.status}`);

                    const data = await response.json();

                    if (data.patients && Array.isArray(data.patients)) {
                        // Update cache
                        allPatients = data.patients.map(p => ({
                            ...p,
                            isOffline: false
                        }));
                        lastFetchTime = Date.now();

                        // Store in localStorage for offline use
                        localStorage.setItem('dentalemr_cached_patients', JSON.stringify(data.patients));

                        // Update filters if needed
                        updateAddressFilters();

                        // Render immediately
                        renderPatients();

                        console.log('Loaded from server:', allPatients.length, 'patients');
                    }

                } catch (error) {
                    console.error('Load error:', error);
                    // If we have cached data, keep showing it
                    if (allPatients.length === 0) {
                        const tbody = document.getElementById("patients-body");
                        if (tbody) {
                            tbody.innerHTML = `
                    <tr>
                        <td colspan="6" class="text-center py-6 text-gray-500">
                            Unable to load data. ${navigator.onLine ? 'Please try again.' : 'You are offline.'}
                        </td>
                    </tr>`;
                        }
                    }
                } finally {
                    isTableLoading = false;
                }
            } else {
                // Use cached data
                renderPatients();
                isTableLoading = false;
            }
        }

        // Optimized render function
        function renderPatients() {
            const tbody = document.getElementById("patients-body");
            if (!tbody || allPatients.length === 0) return;

            // Get search term
            const searchInput = document.getElementById("simple-search");
            const searchTerm = (searchInput?.value || "").toLowerCase().trim();

            // Get active filters
            const addressFilter = document.querySelectorAll('#filterDropdown input[type="checkbox"]:checked');
            const selectedAddresses = Array.from(addressFilter).map(cb => cb.value);

            // Fast filtering
            const filtered = allPatients.filter(patient => {
                // Search filter
                if (searchTerm) {
                    const fullname = (patient.fullname || "").toLowerCase();
                    const address = (patient.address || "").toLowerCase();
                    if (!fullname.includes(searchTerm) && !address.includes(searchTerm)) {
                        return false;
                    }
                }

                // Address filter
                if (selectedAddresses.length > 0) {
                    const patientAddress = patient.address || "";
                    if (!selectedAddresses.includes(patientAddress)) {
                        return false;
                    }
                }

                return true;
            });

            // Calculate pagination
            const total = filtered.length;
            const totalPages = Math.max(1, Math.ceil(total / rowsPerPage));
            const startIndex = (currentPage - 1) * rowsPerPage;
            const endIndex = Math.min(startIndex + rowsPerPage, total);
            const paginated = filtered.slice(startIndex, endIndex);

            // Fast DOM update using document fragment
            if (paginated.length === 0) {
                tbody.innerHTML = `
        <tr>
            <td colspan="6" class="text-center py-6 text-gray-500">
                No patients found
            </td>
        </tr>`;
            } else {
                const fragment = document.createDocumentFragment();

                paginated.forEach((patient, index) => {
                    const rowNum = startIndex + index + 1;
                    const uid = <?php echo $isOfflineMode ? "'offline'" : "$userId"; ?>;
                    const offlineParam = <?php echo $isOfflineMode ? "'&offline=true'" : "''"; ?>;

                    const tr = document.createElement('tr');
                    tr.className = 'border-b border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700';
                    tr.innerHTML = `
                <td class="px-4 py-3 text-center">${rowNum}</td>
                <td class="px-4 py-3 text-center">${escapeHtml(patient.fullname || "—")}</td>
                <td class="px-4 py-3 text-center">${escapeHtml(patient.sex || "—")}</td>
                <td class="px-4 py-3 text-center">${escapeHtml(patient.age || "—")}</td>
                <td class="px-4 py-3 text-center">${escapeHtml(patient.address || "—")}</td>
                <td class="py-3 text-center">
                    <button onclick="window.location.href='view_info.php?uid=${uid}&id=${patient.patient_id}<?php echo $isOfflineMode ? '&offline=true' : ''; ?>'"
                        class="text-white bg-blue-600 cursor-pointer hover:bg-blue-700 font-medium rounded-lg text-xs px-3 py-1.5 mr-2 transition-colors duration-200">
                        View
                    </button>
                    <button onclick="archivePatient(${patient.patient_id})"
                        class="text-white bg-red-600 cursor-pointer hover:bg-red-700 font-medium rounded-lg text-xs px-3 py-1.5 transition-colors duration-200">
                        Archive
                    </button>
                </td>
            `;
                    fragment.appendChild(tr);
                });

                tbody.innerHTML = '';
                tbody.appendChild(fragment);
            }

            // Update pagination
            renderPagination(total, totalPages);
        }

        // Simplified pagination
        function renderPagination(total, totalPages) {
            const nav = document.getElementById("paginationNav");
            if (!nav) return;

            const start = Math.max(1, (currentPage - 1) * rowsPerPage + 1);
            const end = Math.min(currentPage * rowsPerPage, total);

            let pagesHtml = '';
            const maxVisiblePages = 5;
            let startPage = Math.max(1, currentPage - Math.floor(maxVisiblePages / 2));
            let endPage = Math.min(totalPages, startPage + maxVisiblePages - 1);

            if (endPage - startPage + 1 < maxVisiblePages) {
                startPage = Math.max(1, endPage - maxVisiblePages + 1);
            }

            // Previous button
            if (currentPage > 1) {
                pagesHtml += `<li><a href="#" onclick="loadPatients(${currentPage - 1}); return false;" class="flex items-center justify-center text-sm py-2 px-3 text-gray-500 bg-white border border-gray-300 hover:bg-gray-100">Previous</a></li>`;
            }

            // Page numbers
            for (let i = startPage; i <= endPage; i++) {
                if (i === currentPage) {
                    pagesHtml += `<li><span class="flex items-center justify-center text-sm py-2 px-3 text-blue-600 bg-blue-50 border border-blue-300">${i}</span></li>`;
                } else {
                    pagesHtml += `<li><a href="#" onclick="loadPatients(${i}); return false;" class="flex items-center justify-center text-sm py-2 px-3 text-gray-500 bg-white border border-gray-300 hover:bg-gray-100">${i}</a></li>`;
                }
            }

            // Next button
            if (currentPage < totalPages) {
                pagesHtml += `<li><a href="#" onclick="loadPatients(${currentPage + 1}); return false;" class="flex items-center justify-center text-sm py-2 px-3 text-gray-500 bg-white border border-gray-300 hover:bg-gray-100">Next</a></li>`;
            }

            nav.innerHTML = `
    <div class="flex flex-col md:flex-row justify-between items-center w-full">
        <span class="text-sm text-gray-500 mb-2 md:mb-0">
            Showing <span class="font-semibold">${start}-${end}</span> of <span class="font-semibold">${total}</span>
        </span>
        <ul class="inline-flex -space-x-px">${pagesHtml}</ul>
    </div>`;
        }

        // Setup address filters
        function setupAddressFilters() {
            const filterList = document.querySelector('#filterDropdown ul');
            if (!filterList) return;

            // Use event delegation
            filterList.addEventListener('change', (e) => {
                if (e.target.type === 'checkbox') {
                    currentPage = 1;
                    renderPatients();
                }
            });
        }

        // Update address filters
        function updateAddressFilters() {
            const filterList = document.querySelector('#filterDropdown ul');
            if (!filterList || allPatients.length === 0) return;

            const addresses = [...new Set(allPatients.map(p => p.address).filter(Boolean))].sort();

            let html = '';
            addresses.forEach(addr => {
                const safeId = 'addr-' + (addr || 'unknown').replace(/\s+/g, '-').toLowerCase();
                html += `
        <li class="flex items-center">
            <input id="${safeId}" type="checkbox" value="${escapeHtml(addr)}"
                class="w-4 h-4 bg-gray-100 border-gray-300 rounded focus:ring-blue-500">
            <label for="${safeId}" class="ml-2 text-sm font-medium text-gray-900 dark:text-gray-300 cursor-pointer">${escapeHtml(addr)}</label>
        </li>`;
            });

            filterList.innerHTML = html;
        }

        // Optimized archive function
        async function archivePatient(patientId) {
            if (!confirm("Archive this patient and all related records?")) return;

            const patient = allPatients.find(p => p.patient_id == patientId);
            const patientData = patient || {
                patient_id: patientId,
                fullname: 'Unknown',
                address: 'Unknown'
            };

            const isOnline = navigator.onLine && !<?php echo $isOfflineMode ? 'true' : 'false'; ?>;

            try {
                if (isOnline) {
                    // Fast online archive with timeout
                    const controller = new AbortController();
                    const timeoutId = setTimeout(() => controller.abort(), 3000);

                    const formData = new FormData();
                    formData.append("archive_id", patientId);

                    const response = await fetch("/dentalemr_system/php/treatmentrecords/treatment.php", {
                        method: "POST",
                        body: formData,
                        signal: controller.signal
                    });

                    clearTimeout(timeoutId);

                    if (!response.ok) throw new Error(`HTTP ${response.status}`);

                    const result = await response.json();

                    if (result.success) {
                        showNotification("Archived successfully", 'success');
                        // Remove from local data immediately
                        allPatients = allPatients.filter(p => p.patient_id != patientId);
                        localStorage.setItem('dentalemr_cached_patients', JSON.stringify(allPatients));
                        renderPatients();
                    } else {
                        throw new Error(result.message || 'Archive failed');
                    }
                } else {
                    // Save for offline sync
                    const archives = JSON.parse(localStorage.getItem('dentalemr_offline_archives') || '[]');
                    archives.push({
                        id: 'archive_' + Date.now(),
                        patientId: patientId,
                        patientData: patientData,
                        timestamp: Date.now(),
                        synced: false,
                        attempts: 0
                    });
                    localStorage.setItem('dentalemr_offline_archives', JSON.stringify(archives));

                    showNotification("Saved for sync when online", 'info');
                    allPatients = allPatients.filter(p => p.patient_id != patientId);
                    renderPatients();
                }
            } catch (error) {
                console.error('Archive error:', error);
                showNotification("Archive failed: " + error.message, 'error');
            }
        }

        // Connection monitoring
        function setupConnectionMonitoring() {
            window.addEventListener('online', () => {
                const indicator = document.getElementById('connectionStatus');
                if (indicator) {
                    indicator.innerHTML = `
            <div class="bg-green-500 text-white px-4 py-2 rounded-lg shadow-lg flex items-center animate-pulse">
                <i class="fas fa-wifi mr-2"></i>
                <span>Online - Syncing...</span>
            </div>`;
                    indicator.classList.remove('hidden');
                    setTimeout(() => indicator.classList.add('hidden'), 2000);
                }
                // Trigger background sync
                setTimeout(() => loadPatients(currentPage, true), 1000);
            });

            window.addEventListener('offline', () => {
                const indicator = document.getElementById('connectionStatus');
                if (indicator) {
                    indicator.innerHTML = `
            <div class="bg-yellow-500 text-white px-4 py-2 rounded-lg shadow-lg flex items-center">
                <i class="fas fa-wifi-slash mr-2"></i>
                <span>Offline Mode</span>
            </div>`;
                    indicator.classList.remove('hidden');
                }
            });

            // Initial state
            if (!navigator.onLine) {
                window.dispatchEvent(new Event('offline'));
            }
        }

        // Notification helper
        function showNotification(message, type = 'info') {
            const toast = document.getElementById('syncToast');
            if (!toast) return;

            const colors = {
                success: 'bg-green-500',
                error: 'bg-red-500',
                warning: 'bg-yellow-500',
                info: 'bg-blue-500'
            };

            toast.innerHTML = `
    <div class="${colors[type] || 'bg-blue-500'} text-white px-4 py-2 rounded-lg shadow-lg flex items-center animate-fade-in">
        <span>${message}</span>
    </div>`;
            toast.classList.remove('hidden');

            setTimeout(() => {
                toast.classList.add('hidden');
            }, 3000);
        }

        // Utility function
        function escapeHtml(str) {
            if (!str) return '';
            const div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        }

        // Global functions
        window.archivePatient = archivePatient;
        window.loadPatients = loadPatients;

        // Add CSS for animations
        const style = document.createElement('style');
        style.textContent = `
@keyframes fade-in {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}
.animate-fade-in {
    animation: fade-in 0.3s ease-out;
}
`;
        document.head.appendChild(style);
    </script>

</body>

</html>