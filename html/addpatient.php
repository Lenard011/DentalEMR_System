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
        // Try to create offline session from localStorage data (via JavaScript)
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
                window.location.href = '/dentalemr_system/html/addpatient.php?offline=true';
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
                window.location.href = '/dentalemr_system/html/addpatient.php?offline=true';
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
                    window.location.href = '/dentalemr_system/html/addpatient.php?offline=true';
                }
            </script>";
            exit;
        }
    }

    // Fetch dentist name if user is a dentist (only in online mode)
    if ($conn && !$conn->connect_error && $loggedUser['type'] === 'Dentist') {
        $stmt = $conn->prepare("SELECT name FROM dentist WHERE id = ?");
        $stmt->bind_param("i", $loggedUser['id']);
        $stmt->execute();
        $stmt->bind_result($dentistName);
        if ($stmt->fetch()) {
            $loggedUser['name'] = $dentistName;
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
    <title>Add patient</title>
    <!-- <link href="../css/style.css" rel="stylesheet"> -->
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
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

        /* Popup overlay */
        #validationPopup {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.3);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 9999;
        }

        /* Popup box */
        .popup-content {
            background: #fff;
            padding: 20px 30px;
            border-radius: 12px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
            font-family: Arial, sans-serif;
            max-width: 600px;
            width: 90%;
        }

        .popup-content p {
            font-size: 14px;
            margin-bottom: 15px;
            color: #222;
        }

        .popup-content button {
            padding: 8px 16px;
            background: red;
            border: none;
            color: white;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
        }

        .popup-content button:hover {
            background: darkred;
        }
    </style>
</head>

<body>
    <!-- Add connection status indicator -->
    <div id="connectionStatus" class="hidden fixed top-4 right-4 z-60"></div>

    <!-- Add sync toast -->
    <div id="syncToast" class="hidden fixed top-4 right-4 z-60 transition-all duration-300"></div>
    </div>
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
                    <?php if ($isOfflineMode): ?>
                        <div class="offline-indicator ml-3">
                            <i class="fas fa-wifi-slash"></i>
                            <span>Offline Mode</span>
                        </div>
                    <?php endif; ?>
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
                            <span class="block text-sm font-semibold text-gray-900 dark:text-white">
                                <?php
                                echo htmlspecialchars(
                                    !empty($loggedUser['name'])
                                        ? $loggedUser['name']
                                        : ($loggedUser['email'] ?? 'User')
                                );
                                ?>
                            </span>
                            <span class="block text-sm text-gray-900 truncate dark:text-white">
                                <?php
                                echo htmlspecialchars(
                                    !empty($loggedUser['email'])
                                        ? $loggedUser['email']
                                        : ($loggedUser['name'] ?? 'User')
                                );
                                ?>
                            </span>
                        </div>
                        <ul class="py-1 text-gray-700 dark:text-gray-300" aria-labelledby="dropdown">
                            <li>
                                <a href="#"
                                    class="block py-2 px-4 text-sm hover:bg-gray-100 dark:hover:bg-gray-600 dark:text-gray-400 dark:hover:text-white">My
                                    profile</a>
                            </li>
                            <li>
                                <a href="/dentalemr_system/html/manageusers/manageuser.php?uid=<?php echo $userId; ?>"
                                    class="block py-2 px-4 text-sm hover:bg-gray-100 dark:hover:bg-gray-600 dark:text-gray-400 dark:hover:text-white">Manage users</a>
                            </li>
                        </ul>
                        <ul class="py-1 text-gray-700 dark:text-gray-300" aria-labelledby="dropdown">
                            <li>
                                <a href="/dentalemr_system/html/manageusers/historylogs.php?uid=<?php echo $userId; ?>"
                                    class="block py-2 px-4 text-sm hover:bg-gray-100 dark:hover:bg-gray-600 dark:text-gray-400 dark:hover:text-white">History logs</a>
                            </li>
                            <li>
                                <a href="/dentalemr_system/html/manageusers/activitylogs.php?uid=<?php echo $userId; ?>"
                                    class="block py-2 px-4 text-sm hover:bg-gray-100 dark:hover:bg-gray-600 dark:text-gray-400 dark:hover:text-white">Activity logs</a>
                            </li>
                        </ul>
                        <ul class="py-1 text-gray-700 dark:text-gray-300" aria-labelledby="dropdown">
                            <li>
                                <a href="/dentalemr_system/php/login/logout.php?uid=<?php echo $loggedUser['id']; ?>"
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
                        <a href="index.php?uid=<?php echo $isOfflineMode ? 'offline' : $userId;
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
                        <a href="#"
                            class="flex items-center p-2 text-base font-medium text-blue-600 rounded-lg dark:text-blue bg-blue-100  dark:hover:bg-blue-700 group">
                            <svg aria-hidden="true"
                                class="w-6 h-6 text-blue-600 transition duration-75 dark:text-blue-400  dark:group-hover:text-blue"
                                fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                                <path
                                    d="M12.5 16a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7m.5-5v1h1a.5.5 0 0 1 0 1h-1v1a.5.5 0 0 1-1 0v-1h-1a.5.5 0 0 1 0-1h1v-1a.5.5 0 0 1 1 0m-2-6a3 3 0 1 1-6 0 3 3 0 0 1 6 0" />
                                <path
                                    d="M2 13c0 1 1 1 1 1h5.256A4.5 4.5 0 0 1 8 12.5a4.5 4.5 0 0 1 1.544-3.393Q8.844 9.002 8 9c-5 0-6 3-6 4" />
                            </svg>

                            <span class="ml-3"><?php echo $isOfflineMode ? 'Add Patient (Offline)' : 'Add patient'; ?></span>
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
                                <a href="./treatmentrecords/treatmentrecords.php?uid=<?php echo $userId;
                                                                                        echo $isOfflineMode ? '&offline=true' : ''; ?>"
                                    class="flex items-center p-2 pl-11 w-full text-base font-medium text-gray-900 rounded-lg transition duration-75 group hover:bg-gray-100 dark:text-white dark:hover:bg-gray-700">Treatment
                                    Records</a>
                            </li>
                            <li>
                                <a href="./addpatienttreatment/patienttreatment.php?uid=<?php echo $userId;
                                                                                        echo $isOfflineMode ? '&offline=true' : ''; ?>"
                                    class="flex items-center p-2 pl-11 w-full text-base font-medium text-gray-900 rounded-lg transition duration-75 group hover:bg-gray-100 dark:text-white dark:hover:bg-gray-700">Add
                                    Patient Treatment</a>
                            </li>
                        </ul>
                    </li>
                </ul>
                <ul class="pt-5 mt-5 space-y-2 border-t border-gray-200 dark:border-gray-700">
                    <li>
                        <a href="./reports/targetclientlist.php?uid=<?php echo $userId; ?>"
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
                        <a href="./reports/mho_ohp.php?uid=<?php echo $userId; ?>"
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
                        <a href="./reports/oralhygienefindings.php?uid=<?php echo $userId; ?>"
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
                        <a href="./archived.php?uid=<?php echo $userId; ?>"
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

        <main class="p-2 sm:p-4 md:ml-64 h-auto pt-16 sm:pt-20">
            <section class="bg-gray-50 dark:bg-gray-900 p-2 sm:p-3 lg:p-5">
                <div class="mx-auto max-w-screen-xl px-2 sm:px-4 lg:px-12">
                    <div class="bg-white dark:bg-gray-800 relative shadow-md rounded-lg sm:rounded-lg">
                        <div class="px-3 sm:px-5 pt-3 sm:pt-5">
                            <p class="text-xl sm:text-2xl font-semibold text-gray-900 dark:text-white">Patient List</p>
                        </div>
                        <div class="flex flex-col md:flex-row items-center justify-between space-y-3 md:space-y-0 md:space-x-4 p-3 sm:p-4">
                            <div class="w-full md:w-1/2">
                                <form class="flex items-center">
                                    <label for="simple-search" class="sr-only">Search</label>
                                    <div class="relative w-full">
                                        <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                            <svg aria-hidden="true" class="w-5 h-5 text-gray-500 dark:text-gray-400"
                                                fill="currentColor" viewbox="0 0 20 20"
                                                xmlns="http://www.w3.org/2000/svg">
                                                <path fill-rule="evenodd"
                                                    d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z"
                                                    clip-rule="evenodd" />
                                            </svg>
                                        </div>
                                        <input type="text" id="simple-search"
                                            class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full pl-10 p-2 dark:bg-gray-700 dark:border-blue-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500"
                                            placeholder="Search" required="">
                                    </div>
                                </form>
                            </div>
                            <div class="w-full md:w-auto flex flex-col md:flex-row space-y-2 md:space-y-0 items-stretch md:items-center justify-end md:space-x-3 flex-shrink-0">
                                <button type="button" id="Addpatientbtn" data-modal-target="addpatientModal"
                                    data-modal-toggle="addpatientModal" class="flex items-center justify-center cursor-pointer text-white bg-blue-700 hover:bg-blue-800 font-medium rounded-lg text-sm px-3 sm:px-4 py-2 dark:bg-blue-600 dark:hover:bg-blue-700">
                                    <svg class="h-3.5 w-3.5 mr-2" fill="currentColor" viewbox="0 0 20 20"
                                        xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                        <path clip-rule="evenodd" fill-rule="evenodd"
                                            d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" />
                                    </svg>
                                    <span class="hidden xs:inline">Add Patient</span>
                                    <span class="xs:hidden">Add Patient</span>
                                </button>
                                <!-- Filter -->
                                <div class="relative flex items-center space-x-3 w-full md:w-auto">
                                    <button id="filterDropdownButton" data-dropdown-toggle="filterDropdown"
                                        class="w-full md:w-auto cursor-pointer flex items-center justify-center py-2 px-3 sm:px-4 text-sm font-medium text-gray-900 focus:outline-none bg-white rounded-lg border border-gray-200 hover:bg-gray-100 hover:text-primary-700 focus:z-10 dark:bg-gray-800 dark:text-gray-400 dark:border-gray-600 dark:hover:text-white dark:hover:bg-gray-700"
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
                                        class="absolute z-9999 hidden w-48 p-3 bg-white rounded-lg shadow dark:bg-gray-700 right-0">
                                        <h6 class="mb-3 text-sm font-medium text-gray-900 dark:text-white">Filter by address
                                        </h6>
                                        <ul class="space-y-2 text-sm" id="filterAddresses"
                                            aria-labelledby="filterDropdownButton">
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- Table -->
                        <div class="overflow-x-auto">
                            <table id="patientsTable" class="w-full text-sm text-left text-gray-500 dark:text-gray-400">
                                <thead
                                    class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
                                    <tr>
                                        <th class="px-2 sm:px-4 py-3 text-center">ID</th>
                                        <th class="px-2 sm:px-4 py-3 text-center">Name</th>
                                        <th class="px-2 sm:px-4 py-3 text-center">Sex</th>
                                        <th class="px-2 sm:px-4 py-3 text-center">Age</th>
                                        <th class="px-2 sm:px-4 py-3 text-center">Address</th>
                                        <th class="px-2 sm:px-4 py-3 text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="patientsBody">
                                    <tr class="border-b text-gray-500 dark:border-gray-700">
                                        <td class="px-2 sm:px-4 py-3 text-center text-gray-500 whitespace-nowrap dark:text-white">
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

            <!-- Add patient Modal -->
            <form id="patientForm" method="POST">
                <!-- FirstModal - Made responsive -->
                <div id="addpatientModal" tabindex="-1" aria-hidden="true"
                    class="hidden fixed top-0 right-0 left-0 z-50 justify-center items-center w-full h-full overflow-y-auto overflow-x-hidden md:inset-0 max-h-full">
                    <div class="relative w-full max-w-4xl h-full md:h-auto p-2 sm:p-4">
                        <!-- Modal content -->
                        <div class="relative bg-white rounded-lg shadow dark:bg-gray-800 max-h-[90vh] overflow-y-auto">
                            <!-- Modal header -->
                            <div class="flex justify-between items-center p-4 md:p-5 rounded-t border-b dark:border-gray-600">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                                    Patient Registration
                                </h3>
                                <button type="button"
                                    class="text-gray-400 bg-transparent cursor-pointer hover:bg-gray-200 hover:text-gray-900 rounded-lg text-sm p-1.5 ml-auto inline-flex items-center dark:hover:bg-gray-600 dark:hover:text-white"
                                    data-modal-toggle="addpatientModal">
                                    <svg aria-hidden="true" class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"
                                        xmlns="http://www.w3.org/2000/svg">
                                        <path fill-rule="evenodd"
                                            d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                            clip-rule="evenodd"></path>
                                    </svg>
                                    <span class="sr-only">Close modal</span>
                                </button>
                            </div>
                            <!-- Modal body -->
                            <div class="p-4 md:p-5 space-y-4">
                                <div class="text-center">
                                    <p class="text-lg font-semibold text-gray-900 dark:text-white">
                                        Individual Patient Treatment Record</p>
                                </div>

                                <div class="flex flex-col lg:flex-row gap-4 w-full">
                                    <!-- First Col -->
                                    <div class="flex-1 space-y-4">
                                        <!-- Name -->
                                        <div>
                                            <label for="name"
                                                class="block mb-2 text-xs font-medium text-gray-900 dark:text-white">Name</label>
                                            <div class="flex flex-col sm:flex-row gap-2 w-full">
                                                <input type="text" name="surname" data-required data-label="Surname"
                                                    class="bg-gray-50 border border-gray-300 text-gray-900 text-xs rounded-sm focus:ring-primary-600 focus:border-primary-600 block w-full p-2 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500"
                                                    placeholder="Surname">
                                                <input type="text" name="firstname" data-required data-label="Firstname"
                                                    class="bg-gray-50 border border-gray-300 text-gray-900 text-xs rounded-sm focus:ring-primary-600 focus:border-primary-600 block w-full p-2 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500"
                                                    placeholder="First name">
                                                <input type="text" name="middlename" data-required data-label="Middlename"
                                                    class="bg-gray-50 border border-gray-300 text-gray-900 text-xs rounded-sm focus:ring-primary-600 focus:border-primary-600 block w-full p-2 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500"
                                                    placeholder="Middle initial">
                                            </div>
                                        </div>

                                        <!-- Place of Birth & Address -->
                                        <div>
                                            <label for="pob"
                                                class="block mb-2 text-xs font-medium text-gray-900 dark:text-white">Place of Birth</label>
                                            <input type="text" name="pob" data-required data-label="Place of Birth"
                                                class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-sm focus:ring-primary-600 focus:border-primary-600 block w-full p-2 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500">
                                        </div>

                                        <div>
                                            <label for="address"
                                                class="block mb-2 text-xs font-medium text-gray-900 dark:text-white">Address</label>
                                            <select name="address" data-required data-label="Address"
                                                class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-sm focus:ring-primary-600 focus:border-primary-600 block w-full p-2 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500">
                                                <option selected>-- Select Address --</option>
                                                <option value="Balansay">Balansay</option>
                                                <option value="Fatima">Fatima</option>
                                                <option value="Payompon">Payompon</option>
                                                <option value="Poblacion 1">Poblacion 1</option>
                                                <option value="Poblacion 2">Poblacion 2</option>
                                                <option value="Poblacion 3">Poblacion 3</option>
                                                <option value="Poblacion 4">Poblacion 4</option>
                                                <option value="Poblacion 5">Poblacion 5</option>
                                                <option value="Poblacion 6">Poblacion 6</option>
                                                <option value="Poblacion 7">Poblacion 7</option>
                                                <option value="Poblacion 8">Poblacion 8</option>
                                                <option value="San Luis">San Luis</option>
                                                <option value="Talabaan">Talabaan</option>
                                                <option value="Tangkalan">Tangkalan</option>
                                                <option value="Tayamaan">Tayamaan</option>
                                            </select>
                                        </div>
                                    </div>

                                    <!-- Second Col -->
                                    <div class="flex-1 space-y-4">
                                        <!-- Date of Birth -->
                                        <div>
                                            <label for="dob"
                                                class="block mb-2 text-xs font-medium text-gray-900 dark:text-white">Date of Birth</label>
                                            <input type="date" id="dob" name="dob" data-required data-label="Date of Birth"
                                                class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-600 focus:border-primary-600 block w-full p-2 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500">
                                        </div>

                                        <!-- Age, Sex, Pregnant -->
                                        <div id="form-container" class="grid grid-cols-1 sm:grid-cols-2 gap-4 w-full">
                                            <!-- Age -->
                                            <div class="flex flex-col sm:flex-row gap-2">
                                                <div class="flex-1">
                                                    <label for="age"
                                                        class="block mb-2 text-xs font-medium text-gray-900 dark:text-white">Age</label>
                                                    <input type="number" id="age" name="age" min="0" data-required data-label="Age"
                                                        class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-sm focus:ring-primary-600 focus:border-primary-600 block w-full p-2 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500">
                                                </div>
                                                <div id="monthContainer" class="flex-1">
                                                    <label for="agemonth"
                                                        class="block mb-2 text-xs font-medium text-gray-900 dark:text-white">Month</label>
                                                    <input type="number" id="agemonth" name="agemonth" min="0" max="59" data-label="AgeMonth"
                                                        class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-sm focus:ring-primary-600 focus:border-primary-600 block w-full p-2 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus-border-primary-500">
                                                </div>
                                            </div>

                                            <!-- Sex -->
                                            <div id="sex-wrapper">
                                                <label for="sex"
                                                    class="block mb-2 text-xs font-medium text-gray-900 dark:text-white">Sex</label>
                                                <select id="sex" name="sex" data-required data-label="Sex"
                                                    class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-sm focus:ring-primary-600 focus:border-primary-600 block w-full p-2 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus-border-primary-500">
                                                    <option value="">-- Select --</option>
                                                    <option value="Male">Male</option>
                                                    <option value="Female">Female</option>
                                                    <option value="Other">Other</option>
                                                </select>
                                            </div>

                                            <!-- Pregnant (hidden by default) - This will become part of the second column when visible -->
                                            <div id="pregnant-section" class="hidden sm:col-span-2 ">
                                                <label class="block mb-2 text-xs font-medium text-gray-900 dark:text-white">Pregnant</label>
                                                <div class="flex flex-row gap-4 items-center">
                                                    <div class="flex items-center">
                                                        <input id="pregnant-yes" type="radio" value="Yes" name="pregnant" disabled
                                                            class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 focus:ring-blue-500">
                                                        <label for="pregnant-yes" class="ms-2 text-sm font-medium text-gray-900 dark:text-gray-300">Yes</label>
                                                    </div>
                                                    <div class="flex items-center">
                                                        <input id="pregnant-no" type="radio" value="No" name="pregnant" checked disabled
                                                            class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 focus:ring-blue-500">
                                                        <label for="pregnant-no" class="ms-2 text-sm font-medium text-gray-900 dark:text-gray-300">No</label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Occupation & Parent/guardian -->
                                        <div class="flex flex-col sm:flex-row gap-2 w-full">
                                            <div class="flex-1">
                                                <label for="occupation"
                                                    class="block mb-2 text-xs font-medium text-gray-900 dark:text-white">Occupation</label>
                                                <input type="text" name="occupation" data-required data-label="Occupation"
                                                    class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-sm focus:ring-primary-600 focus:border-primary-600 block w-full p-2 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500"
                                                    placeholder="">
                                            </div>
                                            <div class="flex-1">
                                                <label for="guardian"
                                                    class="block mb-2 text-xs font-medium text-gray-900 dark:text-white">Parent/Guardian</label>
                                                <input type="text" name="guardian" data-required data-label="Parent/Guardian"
                                                    class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-sm focus:ring-primary-600 focus:border-primary-600 block w-full p-2 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500"
                                                    placeholder="">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Other Patient Info -->
                                <div class="space-y-4">
                                    <p class="text-sm font-semibold text-gray-900 dark:text-white">Other Patient Information (Membership)</p>
                                    <div class="space-y-3">
                                        <!-- Membership checkboxes remain the same but with responsive padding -->
                                        <div class="flex items-center mb-1">
                                            <input type="checkbox" value="1" name="nhts_pr"
                                                class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded-sm focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600">
                                            <label for="default-checkbox"
                                                class="ms-2 text-sm font-medium text-gray-900 dark:text-gray-300">National Household Targeting System - Poverty Reduction (NHTS-PR)</label>
                                        </div>
                                        <div class="flex items-center mb-1">
                                            <input type="checkbox" value="1" name="four_ps"
                                                class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded-sm focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600">
                                            <label for="default-checkbox"
                                                class="ms-2 text-sm font-medium text-gray-900 dark:text-gray-300">Pantawid
                                                Pamilyang Pilipino Program (4Ps)</label>
                                        </div>
                                        <div class="flex items-center mb-1">
                                            <input type="checkbox" value="1" name="indigenous_people"
                                                class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded-sm focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600">
                                            <label for="default-checkbox"
                                                class="ms-2 text-sm font-medium text-gray-900 dark:text-gray-300">Indigenous
                                                People (IP)</label>
                                        </div>
                                        <div class="flex items-center mb-1">
                                            <input type="checkbox" value="1" name="pwd"
                                                class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded-sm focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600">
                                            <label for="default-checkbox"
                                                class="ms-2 text-sm font-medium text-gray-900 dark:text-gray-300">Person
                                                With Disabilities (PWDs)</label>
                                        </div>
                                        <div class="flex items-center mb-1">
                                            <input type="checkbox" value="1" name="philhealth_flag"
                                                onchange="toggleInput(this, 'philhealth_number')"
                                                class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded-sm focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600">
                                            <div class="grid grid-cols-2 items-center gap-4">
                                                <label for="default-checkbox"
                                                    class="ms-2 text-sm font-medium text-gray-900 dark:text-gray-300">PhilHealth
                                                    (Indicate Number)</label>
                                                <input type="text" id="philhealth_number" name="philhealth_number" disabled
                                                    class="block py-1 h-4.5 px-0 w-full text-sm text-gray-900 bg-transparent border-0 border-b-2 border-gray-300 appearance-none dark:text-white dark:border-gray-600 dark:focus:border-blue-500 focus:outline-none focus:ring-0 focus:border-blue-600 peer"
                                                    placeholder="" />
                                            </div>
                                        </div>
                                        <div class="flex items-center mb-1">
                                            <input type="checkbox" name="sss_flag" value="1"
                                                onchange="toggleInput(this, 'sss_number')"
                                                class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded-sm focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600">
                                            <div class="grid grid-cols-2 items-center gap-1">
                                                <label for="default-checkbox"
                                                    class="ms-2 not-last-of-type:w-40 text-sm font-medium text-gray-900 dark:text-gray-300">SSS
                                                    (Indicate Number)</label>
                                                <input type="text" id="sss_number" name="sss_number" disabled
                                                    class="block py-1 h-4.5 px-0 w-50 text-sm text-gray-900 bg-transparent border-0 border-b-2 border-gray-300 appearance-none dark:text-white dark:border-gray-600 dark:focus:border-blue-500 focus:outline-none focus:ring-0 focus:border-blue-600 peer"
                                                    placeholder="" />
                                            </div>
                                        </div>
                                        <div class="flex items-center mb-1">
                                            <input type="checkbox" name="gsis_flag" value="1"
                                                onchange="toggleInput(this, 'gsis_number')"
                                                class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded-sm focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600">
                                            <div class="grid grid-cols-2 items-center gap-1">
                                                <label for="default-checkbox"
                                                    class="ms-2 w-40 text-sm font-medium text-gray-900 dark:text-gray-300">GSIS
                                                    (Indicate Number)</label>
                                                <input type="text" id="gsis_number" name="gsis_number" disabled
                                                    class="block py-1 px-0 h-4.5 w-50 text-sm text-gray-900 bg-transparent border-0 border-b-2 border-gray-300 appearance-none dark:text-white dark:border-gray-600 dark:focus:border-blue-500 focus:outline-none focus:ring-0 focus:border-blue-600 peer"
                                                    placeholder="" />
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="flex justify-end pt-4">
                                    <button type="button" id="Addpatientbtn2" onclick="validateStep(1)"
                                        data-modal-hide="addpatientModal" data-modal-target="addpatientModal2"
                                        data-modal-toggle="addpatientModal2"
                                        class="text-white cursor-pointer inline-flex items-center bg-blue-700 hover:bg-blue-800 focus:outline-none focus:ring-blue-300 font-medium rounded-lg text-sm px-4 py-2 dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800">
                                        Next
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Second Modal -->
                <div id="addpatientModal2" tabindex="-1" aria-hidden="true"
                    class="hidden fixed top-0 right-0 left-0 z-50 justify-center items-center w-full h-full overflow-y-auto overflow-x-hidden md:inset-0 max-h-full">
                    <div class="relative w-full max-w-4xl h-full md:h-auto p-2 sm:p-4">
                        <div class="relative bg-white rounded-lg shadow dark:bg-gray-800 p-2 max-h-[90vh] overflow-y-auto">
                            <div
                                class="flex justify-between items-center pb-4 rounded-t border-b sm:mb-2 dark:border-gray-600">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                                    Patient Registration
                                </h3>
                                <button type="button"
                                    class="text-gray-400 bg-transparent  cursor-pointer hover:bg-gray-200 hover:text-gray-900 rounded-lg text-sm p-1.5 ml-auto inline-flex items-center dark:hover:bg-gray-600 dark:hover:text-white"
                                    data-modal-toggle="addpatientModal2">
                                    <svg aria-hidden="true" class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"
                                        xmlns="http://www.w3.org/2000/svg">
                                        <path fill-rule="evenodd"
                                            d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                            clip-rule="evenodd"></path>
                                    </svg>
                                    <span class="sr-only">Close modal</span>
                                </button>
                            </div>
                            <!-- Modal body -->
                            <div class=" text-center mb-5 mt-0.5">
                                <p class="text-lg font-semibold mt-5 text-gray-900 dark:text-white">
                                    Individual Patient Treatment Record</p>
                            </div>

                            <p class="text-14 font-semibold text-gray-900 dark:text-white">Vital Signs
                            </p>
                            <div class="grid gap-2 mb-4 w-full">
                                <div class="flex items-center justify-between 1 gap-2 w-full">
                                    <div class="w-full">
                                        <label for="name"
                                            class="block mb-2 text-xs font-medium text-gray-900 dark:text-white">Blood
                                            Preassure</label>
                                        <input type="text" name="blood_pressure" data-required
                                            data-label="Blood Pressure"
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
                            </div>
                            <div class=" overflow-auto h-[310px] mb-3">
                                <!-- Medical  History -->
                                <div class="grid mb-4 gap-2 ">
                                    <p class="text-14 font-semibold text-gray-900 dark:text-white">Medical History
                                    </p>
                                    <div>
                                        <div class="flex w-125  items-center mb-1">
                                            <input type="checkbox" name="allergies_flag" value="1"
                                                onchange="toggleInput(this, 'allergies_details')"
                                                class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded-sm focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600">
                                            <div class="grid grid-cols-2 items-center gap-1">
                                                <label for="default-checkbox"
                                                    class="ms-2 text-sm font-medium text-gray-900 dark:text-gray-300">Allergies
                                                    (Please specify)</label>
                                                <input type="text" id="allergies_details" name="allergies_details"
                                                    disabled
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
                                                <input type="text" id="hepatitis_details" name="hepatitis_details"
                                                    disabled
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
                                                <input type="text" id="malignancy_details" name="malignancy_details"
                                                    disabled
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
                                                    <label
                                                        class="w-27 text-sm font-medium text-gray-900 dark:text-gray-300 ">
                                                        Last Admission:</label>
                                                    <input type="date" id="last_admission_date"
                                                        name="last_admission_date" disabled
                                                        class="block py-1 px-0 h-4.5  text-sm text-gray-900 bg-transparent border-0 border-b-2 border-gray-300 appearance-none dark:text-white dark:border-gray-600 dark:focus:border-blue-500 focus:outline-none focus:ring-0 focus:border-blue-600 peer"
                                                        placeholder="" />
                                                </div>
                                                <span>&</span>
                                                <div class="flex flex-row items-center w-52 ">
                                                    <label
                                                        class="w-15 text-sm font-medium text-gray-900 dark:text-gray-300 ">
                                                        Cause:</label>
                                                    <input type="text" id="admission_cause" name="admission_cause"
                                                        disabled
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
                                                onchange="toggleInput(this, 'blood_transfusion_date')"
                                                class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded-sm focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600">
                                            <div class="grid grid-cols-2 items-center gap-1">
                                                <label for="default-checkbox"
                                                    class="ms-2 not-last-of-type:w-40 text-sm font-medium text-gray-900 dark:text-gray-300">Blood
                                                    transfusion (Month & Year)</label>
                                                <input type="text" id="blood_transfusion_date"
                                                    name="blood_transfusion_date" disabled
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
                            </div>
                            <div class="flex justify-between w-full">
                                <button type="button" id="Addpatientbtnb" data-modal-target="addpatientModal"
                                    data-modal-toggle="addpatientModal" data-modal-hide="addpatientModal2"
                                    class="text-white justify-center  cursor-pointer inline-flex items-center bg-blue-700 hover:bg-blue-800  focus:outline-none focus:ring-blue-300 font-medium rounded-lg text-sm p-1 w-15 dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800">
                                    Back
                                </button><button type="button" id="Addpatientbtn2" onclick="validateSte(2)"
                                    data-modal-hide="addpatientModal2" data-modal-target="addpatientModal3"
                                    data-modal-toggle="addpatientModal3"
                                    class="text-white justify-center  cursor-pointer inline-flex items-center bg-blue-700 hover:bg-blue-800  focus:outline-none focus:ring-blue-300 font-medium rounded-lg text-sm p-1 w-15 dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800">
                                    Next
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Third Modal -->
                <div id="addpatientModal3" tabindex="-1" aria-hidden="true"
                    class="hidden fixed top-0 right-0 left-0 z-50 justify-center items-center w-full h-full overflow-y-auto overflow-x-hidden md:inset-0 max-h-full">
                    <div class="relative w-full max-w-4xl h-full md:h-auto p-2 sm:p-4">
                        <!-- Modal content with responsive layout -->
                        <div class="relative bg-white rounded-lg shadow p-2 dark:bg-gray-800 max-h-[90vh] overflow-y-auto">
                            <!-- Modal header -->
                            <div
                                class="flex justify-between items-center pb-4 rounded-t border-b sm:mb-2 dark:border-gray-600">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                                    Patient Registration
                                </h3>
                                <button type="button"
                                    class="text-gray-400 bg-transparent  cursor-pointer hover:bg-gray-200 hover:text-gray-900 rounded-lg text-sm p-1.5 ml-auto inline-flex items-center dark:hover:bg-gray-600 dark:hover:text-white"
                                    data-modal-toggle="addpatientModal3">
                                    <svg aria-hidden="true" class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"
                                        xmlns="http://www.w3.org/2000/svg">
                                        <path fill-rule="evenodd"
                                            d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                            clip-rule="evenodd"></path>
                                    </svg>
                                    <span class="sr-only">Close modal</span>
                                </button>
                            </div>
                            <div class=" text-center mb-5 mt-0.5">
                                <p class="text-lg font-semibold mt-5 text-gray-900 dark:text-white">
                                    Individual Patient Treatment Record</p>
                            </div>
                            <div>
                                <p class="text-14 font-semibold text-gray-900 dark:text-white">Dietary Habits / Social
                                    History
                                </p>
                                <div class="grid mb-4 gap-2">
                                    <div>
                                        <div class="flex items-center mb-1">
                                            <input type="checkbox" name="sugar_flag" value="1"
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
                                            <input type="checkbox" name="alcohol_flag" value="1"
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
                                            <input type="checkbox" name="tobacco_flag" value="1"
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
                                            <input type="checkbox" name="betel_nut_flag" value="1"
                                                onchange="toggleInput(this, 'betel_nut_details')"
                                                class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded-sm focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600">
                                            <div class="grid grid-cols-2 gap-4">
                                                <label for="default-checkbox"
                                                    class="ms-2 text-sm font-medium text-gray-900 dark:text-gray-300">Betel
                                                    Nut Chewing (Amount, Frequency & Duration)</label>
                                                <input type="text" id="betel_nut_details" name="betel_nut_details"
                                                    disabled
                                                    class="block py-1 h-4.5 px-0 w-73.5 text-sm text-gray-900 bg-transparent border-0 border-b-2 border-gray-300 appearance-none dark:text-white dark:border-gray-600 dark:focus:border-blue-500 focus:outline-none focus:ring-0 focus:border-blue-600 peer"
                                                    placeholder=" " />
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div>

                                </div>
                                <div class="flex justify-between w-full">
                                    <button type="button" id="Addpatientbtnb2" data-modal-target="addpatientModal2"
                                        data-modal-toggle="addpatientModal2" data-modal-hide="addpatientModal3"
                                        class="text-white justify-center  cursor-pointer inline-flex items-center bg-blue-700 hover:bg-blue-800  focus:outline-none focus:ring-blue-300 font-medium rounded-lg text-sm p-1 w-15 dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800">
                                        Back
                                    </button>
                                    <button type="submit" name="patient"
                                        class="text-white justify-center  cursor-pointer inline-flex items-center bg-blue-700 hover:bg-blue-800  focus:outline-none focus:ring-blue-300 font-medium rounded-lg text-sm p-1 w-15 dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800">
                                        Submit
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>

            <!-- popup -->
            <div id="popupContainer" style="display:none; position: fixed; top:0; left:0; width:100%; height:100%; background: rgba(0,0,0,0.2); backdrop-filter: blur(10px); justify-content: center; align-items: center; z-index:9999;">
                <div style="background:#fff; padding:20px; border-radius:12px; text-align:center; box-shadow:0 5px 15px rgba(0,0,0,0.3); font-family: Arial, sans-serif; max-width: 90%; margin: 0 1rem;">
                    <p id="popupTitle" style="font-weight:bold; margin-bottom:10px; font-size: 1.1rem;"></p>
                    <p id="popupMessage" style="margin-bottom:15px; font-size: 0.9rem;"></p>
                    <button id="popupOkBtn" style="padding:8px 16px; border:none; border-radius:6px; cursor:pointer; color:#fff; background: red;">OK</button>
                </div>
            </div>
        </main>
    </div>

    <!-- <script src="../node_modules/flowbite/dist/flowbite.min.js"></script> -->
    <script src="https://cdn.jsdelivr.net/npm/flowbite@3.1.2/dist/flowbite.min.js"></script>
    <script src="../js/tailwind.config.js"></script>
    <!-- Client-side 10-minute inactivity logout -->
    <script>
        // Client-side 10-minute inactivity logout (only for online mode)
        let inactivityTime = 1800000; // 10 minutes in ms
        let logoutTimer;

        function resetTimer() {
            clearTimeout(logoutTimer);

            // Only set timer for online mode
            if (!<?php echo $isOfflineMode ? 'true' : 'false'; ?>) {
                logoutTimer = setTimeout(() => {
                    alert("You've been logged out due to 30 minutes of inactivity.");
                    window.location.href = "/dentalemr_system/php/login/logout.php";
                }, inactivityTime);
            }
        }

        ["click", "mousemove", "keypress", "scroll", "touchstart"].forEach(evt => {
            document.addEventListener(evt, resetTimer, false);
        });

        // Only start timer if not in offline mode
        if (!<?php echo $isOfflineMode ? 'true' : 'false'; ?>) {
            resetTimer();
        }


        // Enhanced connection monitoring for add patient
        function setupAddPatientConnectionMonitoring() {
            window.addEventListener('online', handleAddPatientOnlineStatus);
            window.addEventListener('offline', handleAddPatientOfflineStatus);

            // Initial status check
            if (navigator.onLine === false) {
                handleAddPatientOfflineStatus();
            } else {
                handleAddPatientOnlineStatus();
            }
        }

        function handleAddPatientOnlineStatus() {
            const indicator = document.getElementById('connectionStatus');
            if (indicator) {
                indicator.innerHTML = `
            <div class="bg-green-500 text-white px-4 py-2 rounded-lg shadow-lg flex items-center">
                <i class="fas fa-wifi mr-2"></i>
                <span>Back Online - Syncing data...</span>
            </div>
        `;
                indicator.classList.remove('hidden');

                // Sync any offline data
                if (typeof offlineStorage !== 'undefined' && offlineStorage.syncOfflineData) {
                    setTimeout(() => {
                        offlineStorage.syncOfflineData();
                    }, 1000);
                }

                setTimeout(() => {
                    indicator.classList.add('hidden');
                }, 3000);
            }
        }

        function handleAddPatientOfflineStatus() {
            const indicator = document.getElementById('connectionStatus');
            if (indicator) {
                indicator.innerHTML = `
            <div class="bg-yellow-500 text-white px-4 py-2 rounded-lg shadow-lg flex items-center">
                <i class="fas fa-wifi-slash mr-2"></i>
                <span>Offline Mode - Data will be saved locally</span>
            </div>
        `;
                indicator.classList.remove('hidden');
            }
        }

        // Initialize when page loads
        document.addEventListener('DOMContentLoaded', function() {
            setupAddPatientConnectionMonitoring();

            // Show appropriate connection status based on PHP offline mode
            const isOfflineMode = <?php echo $isOfflineMode ? 'true' : 'false'; ?>;
            if (isOfflineMode) {
                handleAddPatientOfflineStatus();
            }
        });
    </script>
    <script>
        function toggleInput(checkbox, inputId) {
            document.getElementById(inputId).disabled = !checkbox.checked;
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
    <script></script>

    <!-- Table  -->
    <script>
        const API_PATH = "../php/register_patient/getPatients.php";
        // Table functionality - make loadPatients globally accessible
        let currentSearch = "";
        let currentPage = 1;
        let limit = 10;
        let selectedAddresses = [];
        let isTableLoading = false;

        // FIXED debounce utility
        function debounce(fn, delay = 300) {
            let t;
            return (...args) => {
                clearTimeout(t);
                t = setTimeout(() => fn.apply(this, args), delay);
            };
        }

        // render helper
        function showMessageInTable(html) {
            const tbody = document.getElementById("patientsBody");
            if (tbody) {
                tbody.innerHTML = `<tr><td colspan="6" class="text-center py-6">${html}</td></tr>`;
            }
        }

        // escape HTML
        function escapeHtml(str) {
            if (str === null || str === undefined) return "";
            return String(str)
                .replaceAll("&", "&amp;")
                .replaceAll("<", "&lt;")
                .replaceAll(">", "&gt;")
                .replaceAll('"', "&quot;")
                .replaceAll("'", "&#039;");
        }

        // load patients - MAKE THIS GLOBALLY ACCESSIBLE
        async function loadPatients(page = 1, forceRefresh = false) {
            if (isTableLoading) return;

            isTableLoading = true;
            currentPage = page;

            // Add cache busting for force refresh
            const cacheBuster = forceRefresh ? `&_t=${Date.now()}` : '';
            const url = `${API_PATH}?page=${page}&limit=${limit}&search=${encodeURIComponent(currentSearch)}&addresses=${encodeURIComponent(selectedAddresses.join(","))}${cacheBuster}`;

            try {
                // Show loading state
                showMessageInTable('<div class="flex justify-center items-center"><div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-700"></div><span class="ml-2">Loading patients...</span></div>');

                const res = await fetch(url, {
                    cache: "no-store",
                    headers: {
                        'Cache-Control': 'no-cache'
                    }
                });

                if (!res.ok) {
                    showMessageInTable("Server error: " + res.status);
                    isTableLoading = false;
                    return;
                }

                const data = await res.json();
                const tbody = document.getElementById("patientsBody");
                const paginationNav = document.getElementById("paginationNav");

                if (!tbody) {
                    isTableLoading = false;
                    return;
                }

                if (!data.patients || data.patients.length === 0) {
                    showMessageInTable("No patients found.");
                    if (paginationNav) paginationNav.innerHTML = "";
                    isTableLoading = false;
                    return;
                }

                tbody.innerHTML = "";
                data.patients.forEach((p, index) => {
                    const displayId = (currentPage - 1) * limit + index + 1;
                    tbody.insertAdjacentHTML("beforeend", `
            <tr class="border-b text-gray-500 border-gray-200 dark:border-gray-700">
                <td class="px-4 py-3 text-center font-medium text-gray-700 whitespace-nowrap dark:text-white">${displayId}</td>
                <td class="px-4 py-3 text-center font-medium text-gray-700 whitespace-nowrap dark:text-white">${escapeHtml(p.surname)}, ${escapeHtml(p.firstname)} ${p.middlename ? escapeHtml(p.middlename) : ""}</td>
                <td class="px-4 py-3 text-center font-medium text-gray-700 whitespace-nowrap dark:text-white">${escapeHtml(p.sex)}</td>
                <td class="px-4 py-3 text-center font-medium text-gray-700 whitespace-nowrap dark:text-white">${escapeHtml(String(p.age))}</td>
                <td class="px-4 py-3 text-center font-medium text-gray-700 whitespace-nowrap dark:text-white">${escapeHtml(p.address)}</td>
                <td class="px-4 py-3 text-center font-medium text-gray-700 whitespace-nowrap dark:text-white">
                    <button onclick="window.location.href='viewrecord.php?uid=<?php echo $isOfflineMode ? 'offline' : $userId;
                                                                                echo $isOfflineMode ? '&offline=true' : ''; ?>&id=${encodeURIComponent(p.patient_id)}'"
                        class="text-white cursor-pointer bg-blue-700 hover:bg-blue-800 font-medium rounded-lg text-xs px-3 py-2">
                        View
                    </button>
                </td>
            </tr>
        `);
                });

                renderPagination(data.total, data.limit, data.page);
                renderFilterAddresses(data.addresses);

            } catch (err) {
                console.error("Error loading patients:", err);
                showMessageInTable("Error loading data. Please try again.");
            } finally {
                isTableLoading = false;
            }
        }

        // pagination renderer
        function renderPagination(total, limitVal, page) {
            const paginationNav = document.getElementById("paginationNav");
            if (!paginationNav) return;

            const totalPages = Math.max(1, Math.ceil(total / limitVal));
            const start = (page - 1) * limitVal + 1;
            const end = Math.min(page * limitVal, total);

            const showingText = `
    <span class="text-sm font-normal text-gray-500 dark:text-gray-400">
    Showing <span class="font-semibold text-gray-700 dark:text-white">${start}-${end}</span>
    of <span class="font-semibold text-gray-700 dark:text-white">${total}</span>
    </span>
`;

            let pagesHTML = "";
            if (page > 1) {
                pagesHTML += `<li><a href="#" onclick="loadPatients(${page - 1}); return false;" class="flex items-center justify-center h-full py-1.5 px-2 ml-0 text-gray-500 bg-white rounded-l-sm border border-gray-300 hover:bg-gray-100 hover:text-gray-700 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white">
        <svg class="w-5 h-5" aria-hidden="true" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
            <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
        </svg></a></li>`;
            }
            for (let i = 1; i <= totalPages; i++) {
                pagesHTML += (i === page) ?
                    `<li><span class="flex items-center justify-center text-sm z-10 py-2 px-3 text-blue-600 bg-blue-50 border border-blue-300 hover:bg-blue-100 hover:text-blue-700 dark:border-gray-700 dark:bg-gray-700 dark:text-white">${i}</span></li>` :
                    `<li><a href="#" onclick="loadPatients(${i}); return false;" class="flex items-center justify-center text-sm py-2 px-3 
             text-gray-500 bg-white border border-gray-300 hover:bg-gray-100 hover:text-gray-700 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white">${i}</a></li>`;
            }
            if (page < totalPages) {
                pagesHTML += `<li><a href="#" onclick="loadPatients(${page + 1}); return false;" class="flex items-center justify-center h-full py-1.5 px-2 leading-tight text-gray-500 bg-white rounded-r-sm border border-gray-300 hover:bg-gray-100 hover:text-gray-700 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white"> 
    <svg class="w-5 h-5" aria-hidden="true" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
        <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
    </svg></a></li>`;
            }

            paginationNav.innerHTML = `${showingText} <ul class="inline-flex -space-x-1px">${pagesHTML}</ul>`;
        }

        // filter addresses dropdown
        function renderFilterAddresses(addresses) {
            const container = document.getElementById("filterAddresses");
            if (!container) return;

            container.innerHTML = "";
            addresses.forEach(addr => {
                const checked = selectedAddresses.includes(addr) ? "checked" : "";
                container.insertAdjacentHTML("beforeend", `
        <li>
            <label class="flex items-center space-x-2">
            <input type="checkbox" value="${escapeHtml(addr)}" ${checked}
                    class="address-filter">
            <span class="text-gray-700 dark:text-gray-200">${escapeHtml(addr)}</span>
            </label>
        </li>
    `);
            });

            document.querySelectorAll(".address-filter").forEach(cb => {
                cb.addEventListener("change", () => {
                    selectedAddresses = Array.from(document.querySelectorAll(".address-filter:checked")).map(x => x.value);
                    loadPatients(1);
                });
            });
        }

        // Enhanced sync function that properly refreshes the table
        function syncOfflineDataAndRefresh() {
            if (!navigator.onLine) {
                console.log('Still offline, cannot sync data');
                return;
            }

            const offlinePatients = offlineStorage.getOfflinePatients();
            const unsyncedPatients = offlinePatients.filter(patient => !patient.synced);

            if (unsyncedPatients.length === 0) {
                console.log('No unsynced patients found');
                // Even if no unsynced patients, refresh the table to get latest data
                loadPatients(1, true);
                return;
            }

            console.log(`Syncing ${unsyncedPatients.length} offline patients...`);

            let successCount = 0;
            let errorCount = 0;

            // Process sync sequentially to avoid race conditions
            const syncNextPatient = async (index) => {
                if (index >= unsyncedPatients.length) {
                    // All patients processed
                    if (successCount > 0) {
                        offlineStorage.showSyncNotification(`Successfully synced ${successCount} patient(s)`);
                        // Force refresh the table after successful sync
                        setTimeout(() => {
                            loadPatients(1, true);
                        }, 500);
                    }
                    if (errorCount > 0) {
                        offlineStorage.showSyncNotification(`Failed to sync ${errorCount} patient(s)`, 'error');
                    }
                    return;
                }

                const patient = unsyncedPatients[index];
                try {
                    const formData = new FormData();

                    Object.entries(patient.data).forEach(([key, value]) => {
                        if (value !== null && value !== undefined) {
                            formData.append(key, value);
                        }
                    });

                    formData.append("patient", "1");
                    formData.append("offline_sync", "true");

                    const response = await fetch("/dentalemr_system/php/register_patient/addpatient.php", {
                        method: "POST",
                        body: formData
                    });

                    const result = await response.json();

                    if (result.status === "success") {
                        patient.synced = true;
                        offlineStorage.removePatient(patient.id);
                        successCount++;
                        console.log('Successfully synced patient:', patient.id);
                    } else {
                        console.error('Failed to sync patient:', patient.id, result);
                        errorCount++;
                    }
                } catch (error) {
                    console.error('Error syncing patient:', patient.id, error);
                    errorCount++;
                }

                // Process next patient
                syncNextPatient(index + 1);
            };

            // Start syncing from the first patient
            syncNextPatient(0);
        }

        // Replace the existing syncOfflineData function in offlineStorage
        if (typeof offlineStorage !== 'undefined') {
            offlineStorage.syncOfflineData = syncOfflineDataAndRefresh;
        }

        // Initialize table when page loads
        document.addEventListener("DOMContentLoaded", function() {
            // Set up search functionality
            const searchInput = document.getElementById("simple-search");
            if (searchInput) {
                searchInput.addEventListener("input", debounce(() => {
                    currentSearch = searchInput.value.trim();
                    loadPatients(1);
                }, 300));
            }

            // Initial load
            loadPatients(1);

            // Set up periodic refresh when online (every 30 seconds)
            setInterval(() => {
                if (navigator.onLine && !isTableLoading) {
                    loadPatients(currentPage, true);
                }
            }, 30000);

            // Listen for online/offline events to refresh table
            window.addEventListener('online', function() {
                console.log('Online - refreshing table data');
                // Small delay to ensure connection is stable
                setTimeout(() => {
                    loadPatients(currentPage, true);
                }, 1000);
            });

            window.addEventListener('offline', function() {
                console.log('Offline mode');
                // You could show offline patients here if needed
            });
        });

        // Make loadPatients available globally for other scripts
        window.loadPatients = loadPatients;
    </script>


    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const form = document.getElementById("patientForm");
            const popupContainer = document.getElementById("popupContainer");
            const popupTitle = document.getElementById("popupTitle");
            const popupMessage = document.getElementById("popupMessage");
            const popupOkBtn = document.getElementById("popupOkBtn");

            let reloadOnClose = false;

            function showPopup(title, message, color = "red", reload = false) {
                popupTitle.style.color = color;
                popupTitle.textContent = title;
                popupMessage.innerHTML = message;
                popupOkBtn.style.background = color;
                popupContainer.style.display = "flex";
                reloadOnClose = reload;
            }

            popupOkBtn.addEventListener("click", function() {
                popupContainer.style.display = "none";
                if (reloadOnClose) {
                    window.location.reload();
                }
            });

            // ========== AGE CALCULATION FUNCTIONALITY ==========
            function initializeAgeCalculator() {
                const dobField = document.getElementById('dob');
                const ageInput = document.getElementById('age');
                const monthInput = document.getElementById('agemonth');
                const monthContainer = document.getElementById('monthContainer');
                const sexInput = document.getElementById('sex');
                const formContainer = document.getElementById('form-container');
                const pregnantSection = document.getElementById('pregnant-section');
                const pregnantRadios = pregnantSection.querySelectorAll('input[name="pregnant"]');

                // Hide month container initially
                if (monthContainer) {
                    monthContainer.style.display = 'none';
                }
                if (monthInput) {
                    monthInput.value = '';
                }

                // Function to calculate age from DOB
                function calculateAge(dobString) {
                    if (!dobString) return {
                        years: 0,
                        months: 0,
                        days: 0
                    };

                    const birthDate = new Date(dobString);
                    const today = new Date();

                    if (isNaN(birthDate.getTime()) || birthDate > today) {
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

                // Update from DOB
                function updateFromDOB() {
                    if (!dobField || !dobField.value) {
                        if (ageInput) ageInput.value = '';
                        if (monthInput) monthInput.value = '';
                        if (monthContainer) monthContainer.style.display = 'none';
                        updatePregnantSection();
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

                    updatePregnantSection();
                }

                // Handle manual age input
                function handleManualAgeInput() {
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

                    updatePregnantSection();
                }

                // Update pregnant section
                function updatePregnantSection() {
                    if (!pregnantSection || !sexInput || !ageInput || !formContainer) return;

                    const age = parseInt(ageInput.value) || 0;
                    const sex = sexInput.value;

                    if (sex === 'Female' && age >= 10 && age <= 49) {
                        pregnantSection.classList.remove('hidden');
                        // On small screens, make it span both columns
                        formContainer.classList.remove('sm:grid-cols-2');
                        formContainer.classList.add('sm:grid-cols-3', 'sm:grid-rows-1');

                        // Add specific styling for the pregnant section
                        pregnantSection.classList.remove('sm:col-span-2');
                        pregnantSection.classList.add('col-span-1');

                        pregnantRadios.forEach(radio => {
                            radio.disabled = false;
                            radio.required = true;
                        });
                    } else {
                        pregnantSection.classList.add('hidden');
                        // Reset to original layout
                        formContainer.classList.remove('sm:grid-rows-2');
                        pregnantSection.classList.remove('col-span-2', 'sm:col-span-2');
                        pregnantSection.classList.add('sm:col-span-2');

                        pregnantRadios.forEach(radio => {
                            radio.disabled = true;
                            radio.required = false;
                            if (radio.value === "No") {
                                radio.checked = true;
                            }
                        });
                    }
                }

                // Set up event listeners
                if (dobField) {
                    dobField.addEventListener('change', updateFromDOB);
                    dobField.addEventListener('input', updateFromDOB);

                    // If DOB already has value, calculate immediately
                    if (dobField.value) {
                        setTimeout(updateFromDOB, 100);
                    }
                }

                if (ageInput) {
                    ageInput.addEventListener('input', handleManualAgeInput);
                    ageInput.addEventListener('change', handleManualAgeInput);
                }

                if (sexInput) {
                    sexInput.addEventListener('change', updatePregnantSection);
                }

                if (monthInput) {
                    monthInput.addEventListener('input', function() {
                        const age = parseInt(ageInput.value) || 0;
                        if (age < 5 && this.value && monthContainer) {
                            monthContainer.style.display = 'block';
                        }
                    });
                }

                // Initial update
                updatePregnantSection();
            }

            // Initialize age calculator when modal is opened
            const modalToggleButton = document.querySelector('[data-modal-target="addpatientModal"]');
            if (modalToggleButton) {
                modalToggleButton.addEventListener('click', function() {
                    // Small delay to ensure modal is visible
                    setTimeout(initializeAgeCalculator, 300);
                });
            }

            // Also try to initialize on page load (in case modal is already open)
            setTimeout(initializeAgeCalculator, 500);

            // ========== OFFLINE STORAGE IMPLEMENTATION ==========
            const offlineStorage = {
                async savePatient(patientData) {
                    return new Promise((resolve, reject) => {
                        try {
                            const offlinePatients = JSON.parse(localStorage.getItem('dentalemr_offline_patients') || '[]');

                            const offlinePatient = {
                                id: 'offline_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9),
                                data: patientData,
                                timestamp: new Date().toISOString(),
                                synced: false,
                                if_treatment: 0
                            };

                            offlinePatients.push(offlinePatient);
                            localStorage.setItem('dentalemr_offline_patients', JSON.stringify(offlinePatients));

                            console.log('Patient saved offline:', offlinePatient.id);

                            // Immediately refresh table to show offline patient
                            if (typeof loadPatients === 'function') {
                                setTimeout(() => loadPatients(1, true), 100);
                            }

                            resolve(offlinePatient.id);
                        } catch (error) {
                            console.error('Error saving patient offline:', error);
                            reject(error);
                        }
                    });
                },

                getOfflinePatients() {
                    try {
                        return JSON.parse(localStorage.getItem('dentalemr_offline_patients') || '[]');
                    } catch (error) {
                        console.error('Error getting offline patients:', error);
                        return [];
                    }
                },

                removePatient(patientId) {
                    try {
                        const offlinePatients = this.getOfflinePatients();
                        const updatedPatients = offlinePatients.filter(patient => patient.id !== patientId);
                        localStorage.setItem('dentalemr_offline_patients', JSON.stringify(updatedPatients));
                        console.log('Patient removed from offline storage:', patientId);
                    } catch (error) {
                        console.error('Error removing offline patient:', error);
                    }
                },

                async syncOfflineData() {
                    if (!navigator.onLine) {
                        console.log('Still offline, cannot sync data');
                        return {
                            success: 0,
                            error: 0
                        };
                    }

                    const offlinePatients = this.getOfflinePatients();
                    const unsyncedPatients = offlinePatients.filter(patient => !patient.synced);

                    if (unsyncedPatients.length === 0) {
                        console.log('No unsynced patients found');
                        // Force refresh table anyway to get latest data
                        if (typeof loadPatients === 'function') {
                            setTimeout(() => loadPatients(1, true), 500);
                        }
                        return {
                            success: 0,
                            error: 0
                        };
                    }

                    console.log(`Syncing ${unsyncedPatients.length} offline patients...`);

                    let successCount = 0;
                    let errorCount = 0;

                    for (const patient of unsyncedPatients) {
                        try {
                            const formData = new FormData();
                            Object.entries(patient.data).forEach(([key, value]) => {
                                if (value !== null && value !== undefined) {
                                    formData.append(key, value);
                                }
                            });

                            formData.append("patient", "1");
                            formData.append("offline_sync", "true");

                            const response = await fetch("/dentalemr_system/php/register_patient/addpatient.php", {
                                method: "POST",
                                body: formData
                            });

                            const result = await response.json();

                            if (result.status === "success") {
                                patient.synced = true;
                                this.removePatient(patient.id);
                                successCount++;
                                console.log('Successfully synced patient:', patient.id);
                            } else {
                                console.error('Failed to sync patient:', patient.id, result);
                                errorCount++;
                            }
                        } catch (error) {
                            console.error('Error syncing patient:', patient.id, error);
                            errorCount++;
                        }

                        // Small delay between syncs to avoid overwhelming the server
                        await new Promise(resolve => setTimeout(resolve, 500));
                    }

                    // Refresh table after all syncs are done
                    if (successCount > 0) {
                        console.log(`Sync completed: ${successCount} successful, ${errorCount} failed`);
                        this.showSyncNotification(`Successfully synced ${successCount} patient(s)`);

                        // Force refresh table
                        if (typeof loadPatients === 'function') {
                            setTimeout(() => {
                                loadPatients(1, true);
                            }, 1000);
                        }
                    }

                    if (errorCount > 0) {
                        this.showSyncNotification(`Failed to sync ${errorCount} patient(s)`, 'error');
                    }

                    return {
                        success: successCount,
                        error: errorCount
                    };
                },

                showSyncNotification(message, type = 'success') {
                    const toast = document.getElementById('syncToast');
                    if (toast) {
                        const bgColor = type === 'success' ? 'bg-green-500' : 'bg-red-500';
                        toast.innerHTML = `
                        <div class="${bgColor} text-white px-4 py-2 rounded-lg shadow-lg flex items-center">
                            <span>${message}</span>
                        </div>
                    `;
                        toast.classList.remove('hidden');

                        setTimeout(() => {
                            toast.classList.add('hidden');
                        }, 5000);
                    }
                },

                hasUnsyncedPatients() {
                    const offlinePatients = this.getOfflinePatients();
                    return offlinePatients.some(patient => !patient.synced);
                },

                getUnsyncedCount() {
                    const offlinePatients = this.getOfflinePatients();
                    return offlinePatients.filter(patient => !patient.synced).length;
                },

                // Enhanced: Display offline patients in the table
                displayOfflinePatients() {
                    const offlinePatients = this.getOfflinePatients();
                    const unsyncedPatients = offlinePatients.filter(patient => !patient.synced);

                    if (unsyncedPatients.length > 0) {
                        console.log('Displaying offline patients:', unsyncedPatients.length);

                        // You can implement a visual indicator for offline patients
                        unsyncedPatients.forEach(patient => {
                            console.log('Offline patient:', patient.data.surname, patient.data.firstname);
                        });

                        // Refresh table to potentially show offline indicator
                        if (typeof loadPatients === 'function') {
                            loadPatients(currentPage, true);
                        }
                    }
                }
            };

            // ========== CONNECTION MONITORING ==========
            function setupConnectionMonitoring() {
                window.addEventListener('online', handleOnlineStatus);
                window.addEventListener('offline', handleOfflineStatus);

                // Initial status check
                if (navigator.onLine === false) {
                    handleOfflineStatus();
                } else {
                    handleOnlineStatus();
                }
            }

            async function handleOnlineStatus() {
                const indicator = document.getElementById('connectionStatus');
                if (indicator) {
                    indicator.innerHTML = `
                    <div class="bg-green-500 text-white px-4 py-2 rounded-lg shadow-lg flex items-center">
                        <span>Back Online - Syncing data...</span>
                    </div>
                `;
                    indicator.classList.remove('hidden');

                    // Wait for connection to stabilize
                    await new Promise(resolve => setTimeout(resolve, 1500));

                    // Sync offline data and reload table
                    try {
                        console.log('Starting offline data sync...');
                        await offlineStorage.syncOfflineData();

                        // Force refresh table after sync
                        if (typeof loadPatients === 'function') {
                            console.log('Refreshing table after sync...');
                            setTimeout(() => {
                                loadPatients(1, true);
                                // Hide indicator after table refresh
                                setTimeout(() => {
                                    indicator.classList.add('hidden');
                                }, 2000);
                            }, 1000);
                        } else {
                            console.error('loadPatients function not found');
                            indicator.classList.add('hidden');
                        }
                    } catch (error) {
                        console.error('Error during sync:', error);
                        indicator.classList.add('hidden');
                    }
                }
            }

            function handleOfflineStatus() {
                const indicator = document.getElementById('connectionStatus');
                if (indicator) {
                    indicator.innerHTML = `
                    <div class="bg-yellow-500 text-white px-4 py-2 rounded-lg shadow-lg flex items-center">
                        <span>Offline Mode - Data will be saved locally</span>
                    </div>
                `;
                    indicator.classList.remove('hidden');

                    // Show offline patients count
                    const unsyncedCount = offlineStorage.getUnsyncedCount();
                    if (unsyncedCount > 0) {
                        const offlineToast = document.getElementById('syncToast');
                        if (offlineToast) {
                            offlineToast.innerHTML = `
                            <div class="bg-blue-500 text-white px-4 py-2 rounded-lg shadow-lg flex items-center">
                                <span>${unsyncedCount} patient(s) waiting to sync</span>
                            </div>
                        `;
                            offlineToast.classList.remove('hidden');
                        }
                    }

                    // Show offline patients in the table
                    offlineStorage.displayOfflinePatients();
                }
            }

            // ========== CHECK FOR OFFLINE PATIENTS ON LOAD ==========
            function checkOfflinePatientsOnLoad() {
                const offlinePatients = offlineStorage.getOfflinePatients();
                const unsyncedPatients = offlinePatients.filter(patient => !patient.synced);

                if (unsyncedPatients.length > 0 && navigator.onLine) {
                    console.log(`Found ${unsyncedPatients.length} unsynced offline patients, syncing...`);
                    offlineStorage.syncOfflineData();
                }
            }

            // ========== FORM SUBMISSION HANDLER ==========
            function setupFormSubmission() {
                const form = document.getElementById('patientForm');

                // Remove any existing event listeners to prevent duplicates
                const newForm = form.cloneNode(true);
                form.parentNode.replaceChild(newForm, form);

                newForm.addEventListener('submit', handleFormSubmit);

                async function handleFormSubmit(e) {
                    e.preventDefault();
                    console.log('Form submission started...');

                    // Validation code remains the same...
                    let missing = [];
                    newForm.querySelectorAll("[data-required]").forEach(input => {
                        if (!input.value.trim()) {
                            missing.push(input.getAttribute("data-label"));
                        }
                    });

                    const conditionalMap = {
                        "allergies_flag": ["allergies_details", "Allergies"],
                        "hepatitis_flag": ["hepatitis_details", "Hepatitis"],
                        "malignancy_flag": ["malignancy_details", "Malignancy"],
                        "prev_hospitalization_flag": ["last_admission_date", "Medical Last Admission"],
                        "blood_transfusion_flag": ["blood_transfusion", "Blood Transfusion"],
                        "other_conditions_flag": ["other_conditions", "Other Conditions"],
                        "sugar_flag": ["sugar_details", "Sugar"],
                        "alcohol_flag": ["alcohol_details", "Use of Alcohol"],
                        "tobacco_flag": ["tobacco_details", "Use of tobacco"],
                        "betel_nut_flag": ["betel_nut_details", "Betel Nut Chewing"],
                        "philhealth_flag": ["philhealth_number", "Philhealth Number"],
                        "sss_flag": ["sss_number", "SSS Number"],
                        "gsis_flag": ["gsis_number", "GSIS Number"]
                    };

                    Object.entries(conditionalMap).forEach(([flagName, [detailsName, label]]) => {
                        const checkbox = newForm.querySelector("[name='" + flagName + "']");
                        const detailsField = newForm.querySelector("[name='" + detailsName + "']");
                        if (checkbox && checkbox.checked && detailsField && !detailsField.value.trim()) {
                            missing.push(label);
                        }
                    });

                    if (missing.length > 0) {
                        showPopup(
                            "⚠ Submission Error",
                            "Please fill in the following required fields:<br><br>" + missing.join("<br>"),
                            "red"
                        );
                        return;
                    }

                    // Check if offline and handle accordingly
                    if (!navigator.onLine) {
                        console.log('Offline mode detected, saving locally...');
                        const formData = new FormData(newForm);
                        const patientData = Object.fromEntries(formData.entries());

                        try {
                            await offlineStorage.savePatient(patientData);

                            showPopup(
                                '✅ Saved Offline',
                                'Patient data has been saved locally. It will be automatically synced when you are back online.',
                                'blue',
                                true
                            );

                            newForm.reset();

                            // Close all modals
                            const modals = ['addpatientModal', 'addpatientModal2', 'addpatientModal3'];
                            modals.forEach(modalId => {
                                const modal = document.getElementById(modalId);
                                if (modal) {
                                    modal.classList.add('hidden');
                                }
                            });

                        } catch (error) {
                            console.error('Error saving offline:', error);
                            showPopup('Error', 'Failed to save patient data offline.', 'red');
                        }
                    } else {
                        // Online submission code remains the same...
                        console.log('Online mode detected, submitting to server...');
                        let formData = new FormData(newForm);
                        formData.append("patient", "1");

                        try {
                            const response = await fetch("/dentalemr_system/php/register_patient/addpatient.php", {
                                method: "POST",
                                body: formData
                            });

                            const data = await response.json();
                            console.log('Server response:', data);

                            const color = data.status === "success" ? "blue" : "red";
                            showPopup(
                                data.title || "Message",
                                data.message || "No message",
                                color,
                                data.status === "success"
                            );

                            if (data.status === "success") {
                                newForm.reset();

                                // Close all modals
                                const modals = ['addpatientModal', 'addpatientModal2', 'addpatientModal3'];
                                modals.forEach(modalId => {
                                    const modal = document.getElementById(modalId);
                                    if (modal) {
                                        modal.classList.add('hidden');
                                    }
                                });

                                // Refresh the table data
                                setTimeout(() => {
                                    if (typeof loadPatients === 'function') {
                                        loadPatients(1, true);
                                    }
                                }, 1000);
                            }
                        } catch (err) {
                            console.error("AJAX error:", err);
                            showPopup("Error", "Error while saving patient. Check console.", "red");
                        }
                    }
                }
            }

            // ========== INITIALIZATION ==========
            setupConnectionMonitoring();
            setupFormSubmission();

            // Check for unsynced patients on load and reload table after sync
            if (navigator.onLine && offlineStorage.hasUnsyncedPatients()) {
                console.log('Found unsynced patients on page load, syncing...');
                setTimeout(() => {
                    offlineStorage.syncOfflineData().then(() => {
                        // Reload table after sync completes
                        setTimeout(() => {
                            if (typeof loadPatients === 'function') {
                                loadPatients(1, true);
                            }
                        }, 1500);
                    });
                }, 1000);
            }

            // Add CSS for better offline indicator styling
            const style = document.createElement('style');
            style.textContent = `
            .hidden {
                display: none !important;
            }
            #connectionStatus, #syncToast {
                z-index: 9999;
            }
        `;
            document.head.appendChild(style);
        });
    </script>


</body>

</html>