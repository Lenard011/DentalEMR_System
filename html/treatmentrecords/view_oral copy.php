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

// Verify patient exists
if ($patientId > 0) {
    $stmt = $conn->prepare("SELECT patient_id, firstname, middlename, surname FROM patients WHERE patient_id = ?");
    $stmt->bind_param("i", $patientId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        $patientId = 0; // Reset if patient doesn't exist
    }
    $stmt->close();
}

$conn->close();
?>
<!doctype html>
<html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Treatment Records - Oral Health</title>
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
                    <div class="flex flex-row items-center gap-2 w-full sm:w-auto">
                        <!-- Refresh Button -->
                        <button type="button" id="refreshBtn" onclick="refreshRecords()"
                            class="text-gray-700 cursor-pointer flex flex-row items-center justify-center gap-1 bg-gray-100 hover:bg-gray-200 font-medium rounded-sm text-xs px-3 py-2 w-full sm:w-auto dark:bg-gray-700 dark:text-white dark:hover:bg-gray-600">
                            <svg class="h-3.5 w-3.5" fill="currentColor" viewbox="0 0 20 20" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                <path fill-rule="evenodd" d="M4 2a1 1 0 011 1v2.101a7.002 7.002 0 0111.601 2.566 1 1 0 11-1.885.666A5.002 5.002 0 005.999 7H9a1 1 0 010 2H4a1 1 0 01-1-1V3a1 1 0 011-1zm.008 9.057a1 1 0 011.276.61A5.002 5.002 0 0014.001 13H11a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0v-2.101a7.002 7.002 0 01-11.601-2.566 1 1 0 01.61-1.276z" clip-rule="evenodd" />
                            </svg>
                            Refresh Records
                        </button>

                        <button type="button" id="addOHCbtn" onclick="openOHCModal()"
                            class="text-white cursor-pointer flex flex-row items-center justify-center gap-1 bg-blue-700 hover:bg-blue-800 font-medium rounded-sm text-xs px-3 py-2 w-full sm:w-auto">
                            <svg class="h-3.5 w-3.5" fill="currentColor" viewbox="0 0 20 20" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                <path clip-rule="evenodd" fill-rule="evenodd"
                                    d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" />
                            </svg>
                            Add Oral Health Record
                        </button>
                    </div>
                </div>

                <!-- Oral Examination Section -->
                <div class="mx-auto mb-4 p-3 sm:p-4 bg-white rounded-lg shadow dark:border shadow-stone-300 drop-shadow-sm dark:bg-gray-800 dark:border-gray-950">
                    <div class="items-center justify-between flex flex-row mb-3">
                        <p class="text-base font-normal text-gray-950 dark:text-white">Oral Examination</p>
                    </div>

                    <!-- Date Selector -->
                    <div class="mb-4">
                        <div class="flex flex-col sm:flex-row items-start sm:items-center gap-2">
                            <label for="dataSelect" class="text-sm sm:text-base text-gray-900 dark:text-white">Select Examination Date:</label>
                            <div class="flex items-center gap-2">
                                <select id="dataSelect"
                                    class="bg-gray-50 border border-gray-300 text-gray-900 text-xs sm:text-sm rounded-sm w-full sm:w-64 p-2 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white"
                                    onchange="loadSelectedRecord()">
                                    <option value="" selected disabled>Loading records...</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Status Messages -->
                    <div id="loadingStatus" class="mb-4 text-center text-sm text-gray-600 dark:text-gray-400 hidden">
                        <div class="flex items-center justify-center space-x-2">
                            <div class="w-4 h-4 border-2 border-blue-600 border-t-transparent rounded-full animate-spin"></div>
                            <span class="text-blue-600 dark:text-blue-400">Loading oral health records...</span>
                        </div>
                    </div>

                    <div id="saveSuccessMessage" class="mb-4 p-3 text-sm text-green-800 rounded-lg bg-green-50 dark:bg-green-900/20 dark:text-green-400 hidden" role="alert">
                        <div class="flex items-center">
                            <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                            </svg>
                            <span id="successMessageText"></span>
                        </div>
                    </div>

                    <div id="noRecordsMessage" class="mb-4 text-center text-gray-600 dark:text-gray-400 hidden">
                        <div class="text-center py-8">
                            <div class="inline-flex items-center justify-center w-16 h-16 mb-4 rounded-full bg-blue-100 dark:bg-blue-900">
                                <svg class="w-8 h-8 text-blue-600 dark:text-blue-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                            </div>
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">No Oral Health Records</h3>
                            <p class="text-gray-600 dark:text-gray-400 mb-6 max-w-md mx-auto">
                                No oral health examination records have been created for <span id="patientNamePlaceholder">this patient</span> yet.
                            </p>
                            <button onclick="openOHCModal()"
                                class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition duration-200">
                                <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd"></path>
                                </svg>
                                Create First Oral Health Record
                            </button>
                        </div>
                    </div>

                    <!-- Oral Data Container -->
                    <div id="oralDataContainer" class="space-y-4">
                        <!-- Data will be loaded here -->
                    </div>
                </div>

                <!-- Next Button -->
                <div class="flex justify-end mt-4">
                    <button type="button" onclick="next()"
                        class="text-white cursor-pointer inline-flex items-center justify-center bg-blue-700 hover:bg-blue-800 focus:outline-none focus:ring-blue-300 font-medium rounded-lg text-sm px-4 py-2 w-full sm:w-auto dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800">
                        Next
                    </button>
                </div>
            </section>
        </main>

        <!-- Modal  -->
        <div id="ohcModal" tabindex="-1" aria-hidden="true"
            class="fixed inset-0 hidden flex justify-center items-center z-50 bg-gray-600/50">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg w-full max-w-5xl p-4 m-2 max-h-[90vh] overflow-y-auto">
                <div class="flex flex-row justify-between items-center mb-4">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Add Oral Health Condition</h2>
                    <button type="button" id="cancelMedicalBtn"
                        class="relative cursor-pointer text-gray-500 hover:text-gray-800 dark:hover:text-white"
                        onclick="closeOHCModal()">
                        ✕
                    </button>
                </div>
                <form id="ohcForm" class="space-y-4">
                    <input type="hidden" name="patient_id" id="patient_id" value="<?php echo $patientId; ?>">

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

    <!-- Notice Element for Offline Sync -->
    <div id="notice" style="display: none;"></div>

    <!-- fetch records -->
    <script>
        // Global variables
        let oralRecords = [];
        let currentPatientId = <?php echo $patientId; ?>;
        let currentUserId = <?php echo $userId; ?>;
        let patientName = '';
        let isSaving = false;

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM Content Loaded - Starting initialization');
            try {
                initializePage();
            } catch (error) {
                console.error('Error during initialization:', error);
                showAlert('Failed to initialize page. Please refresh.', 'error');
            }
        });

        function initializePage() {
            console.log('Initializing page with Patient ID:', currentPatientId, 'User ID:', currentUserId);

            if (!currentPatientId || currentPatientId <= 0) {
                console.error('Invalid patient ID:', currentPatientId);
                showAlert('Missing patient ID. Please select a patient first.', 'error');
                if (currentUserId) {
                    setTimeout(() => {
                        window.location.href = `treatmentrecords.php?uid=${currentUserId}`;
                    }, 2000);
                }
                return;
            }

            // Set navigation links
            updateNavigationLinks();

            // Load patient name first
            loadPatientInfo();

            // Load oral records
            loadPatientOralRecords();
        }

        function updateNavigationLinks() {
            try {
                // Set patient info link
                const patientInfoLink = document.getElementById("patientInfoLink");
                if (patientInfoLink && currentPatientId && currentUserId) {
                    patientInfoLink.href = `view_info.php?uid=${currentUserId}&id=${currentPatientId}`;
                }

                // Set services rendered link
                const servicesRenderedLink = document.getElementById("servicesRenderedLink");
                if (servicesRenderedLink && currentPatientId && currentUserId) {
                    servicesRenderedLink.href = `view_record.php?uid=${currentUserId}&id=${currentPatientId}`;
                }

                // Set print link
                const printdLink = document.getElementById("printdLink");
                if (printdLink && currentPatientId && currentUserId) {
                    printdLink.href = `print.php?uid=${currentUserId}&id=${currentPatientId}`;
                }
            } catch (error) {
                console.error('Error updating navigation links:', error);
            }
        }

        async function loadPatientInfo() {
            try {
                console.log('Loading patient info for ID:', currentPatientId);
                const response = await fetch(`/dentalemr_system/php/patients/get_patient.php?id=${currentPatientId}&cache=${Date.now()}`);

                if (response.ok) {
                    const result = await response.json();
                    console.log('Patient info response:', result);

                    if (result.success && result.data) {
                        const patient = result.data;
                        patientName = `${patient.firstname} ${patient.middlename ? patient.middlename + '. ' : ''}${patient.surname}`;
                        console.log('Patient name loaded:', patientName);

                        // Update patient name display
                        const patientNameElement = document.getElementById("patientName");
                        if (patientNameElement) {
                            patientNameElement.textContent = patientName;
                            patientNameElement.classList.remove('italic');
                        }

                        // Update placeholder in no records message
                        const patientNamePlaceholder = document.getElementById("patientNamePlaceholder");
                        if (patientNamePlaceholder) {
                            patientNamePlaceholder.textContent = patientName;
                        }
                    } else {
                        console.warn('Patient info not found or error:', result.message);
                    }
                } else {
                    console.warn('Failed to fetch patient info:', response.status);
                }
            } catch (error) {
                console.warn('Could not load patient info:', error);
                const patientNameElement = document.getElementById("patientName");
                if (patientNameElement) {
                    patientNameElement.textContent = 'Patient ID: ' + currentPatientId;
                    patientNameElement.classList.remove('italic');
                }
            }
        }

        function refreshRecords() {
            console.log('Refreshing records...');
            showLoading(true);

            // Clear any existing records
            oralRecords = [];

            // Clear dropdown
            const dateSelect = document.getElementById("dataSelect");
            if (dateSelect) {
                dateSelect.innerHTML = '<option value="" selected disabled>Loading...</option>';
                dateSelect.disabled = true;
            }

            // Clear container
            const oralDataContainer = document.getElementById("oralDataContainer");
            if (oralDataContainer) {
                oralDataContainer.innerHTML = '';
            }

            // Hide no records message
            const noRecordsMessage = document.getElementById("noRecordsMessage");
            if (noRecordsMessage) {
                noRecordsMessage.classList.add('hidden');
            }

            // Load fresh data
            loadPatientOralRecords();

            // Show success message
            setTimeout(() => {
                showSaveSuccess('Records refreshed successfully!');
            }, 500);
        }

        async function loadPatientOralRecords() {
            const dateSelect = document.getElementById("dataSelect");
            const loadingStatus = document.getElementById("loadingStatus");
            const noRecordsMessage = document.getElementById("noRecordsMessage");

            console.log('loadPatientOralRecords called with patient ID:', currentPatientId);

            if (!currentPatientId || currentPatientId <= 0) {
                console.error('Invalid patient ID in loadPatientOralRecords');
                showNoRecordsMessage();
                return;
            }

            try {
                // Show loading state
                showLoading(true);

                if (dateSelect) {
                    dateSelect.innerHTML = '<option value="" selected disabled>Loading records...</option>';
                    dateSelect.disabled = true;
                }

                // Hide no records message while loading
                if (noRecordsMessage) {
                    noRecordsMessage.classList.add('hidden');
                }

                // Fetch data from API with cache busting
                const apiUrl = `/dentalemr_system/php/treatmentrecords/view_oral_api.php?id=${currentPatientId}&t=${Date.now()}`;
                console.log('Fetching from API:', apiUrl);

                const response = await fetch(apiUrl, {
                    headers: {
                        'Cache-Control': 'no-cache, no-store, must-revalidate',
                        'Pragma': 'no-cache',
                        'Expires': '0'
                    },
                    cache: 'no-store'
                });

                console.log('API Response status:', response.status);

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status} - ${response.statusText}`);
                }

                const result = await response.json();
                console.log('API Response data:', result);

                // Hide loading
                showLoading(false);

                // Check if we have data
                if (!result.success) {
                    console.log('API returned unsuccessful:', result.message);

                    // If it's a "no records found" message
                    if (result.message && result.message.includes('No records')) {
                        showNoRecordsMessage();
                    } else {
                        showAlert('Error loading records: ' + (result.message || 'Unknown error'), 'error');
                        showNoRecordsMessage();
                    }
                    return;
                }

                const data = result.data || [];
                console.log('Data received:', data);

                if (!Array.isArray(data)) {
                    console.error('Invalid data format:', data);
                    throw new Error('Invalid data format received from server');
                }

                if (data.length === 0) {
                    console.log('No records found for patient');
                    showNoRecordsMessage();
                    return;
                }

                console.log('Records found:', data.length);

                // Store records globally
                oralRecords = data;

                // Populate date dropdown
                populateDateDropdown(data);

                // Load first record by default (newest first)
                if (data.length > 0 && data[0].id) {
                    console.log('Loading first record with ID:', data[0].id);
                    await loadOralRecord(data[0].id);
                } else {
                    console.warn('First record has no ID');
                }

            } catch (error) {
                console.error('Error loading oral records:', error);
                showLoading(false);
                showAlert('Failed to load records: ' + error.message, 'error');
                showNoRecordsMessage();

                // Try to show cached data if available
                try {
                    const cachedData = localStorage.getItem(`oral_records_${currentPatientId}`);
                    if (cachedData) {
                        const records = JSON.parse(cachedData);
                        console.log('Using cached data:', records);
                        populateDateDropdown(records);
                    }
                } catch (cacheError) {
                    console.error('Cache error:', cacheError);
                }
            }
        }

        function populateDateDropdown(records) {
            const dateSelect = document.getElementById("dataSelect");
            const noRecordsMessage = document.getElementById("noRecordsMessage");

            console.log('populateDateDropdown called with', records.length, 'records');

            if (!dateSelect) {
                console.error('dateSelect element not found');
                return;
            }

            // Clear existing options
            dateSelect.innerHTML = '';

            if (!Array.isArray(records) || records.length === 0) {
                console.log('No records to populate');
                if (noRecordsMessage) noRecordsMessage.classList.remove('hidden');
                dateSelect.disabled = true;
                dateSelect.innerHTML = '<option value="" selected disabled>No records available</option>';
                return;
            }

            console.log('Populating dropdown with', records.length, 'records');

            // Hide no records message
            if (noRecordsMessage) noRecordsMessage.classList.add('hidden');
            dateSelect.disabled = false;

            // Add options for each record (newest first)
            records.forEach((record, index) => {
                const option = document.createElement('option');
                option.value = record.id;

                // Format date nicely
                let formattedDate = 'Date not available';
                try {
                    const date = record.created_at ? new Date(record.created_at) : new Date();
                    formattedDate = date.toLocaleDateString('en-PH', {
                        year: 'numeric',
                        month: 'short',
                        day: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit'
                    });
                } catch (e) {
                    console.warn('Error formatting date:', e);
                }

                // Add record number for clarity (Record #1 is newest)
                const recordNumber = index + 1;
                option.textContent = `Record #${recordNumber} - ${formattedDate}`;

                // Select first item by default (newest)
                if (index === 0) {
                    option.selected = true;
                }

                dateSelect.appendChild(option);
            });

            console.log('Dropdown populated successfully');
        }

        async function loadOralRecord(recordId) {
            const loadingStatus = document.getElementById("loadingStatus");
            const oralDataContainer = document.getElementById("oralDataContainer");

            console.log('loadOralRecord called with ID:', recordId);

            try {
                if (loadingStatus) loadingStatus.classList.remove('hidden');

                // Clear previous data
                if (oralDataContainer) oralDataContainer.innerHTML = '';

                const response = await fetch(`/dentalemr_system/php/treatmentrecords/view_oral_api.php?record=${recordId}&t=${Date.now()}`, {
                    cache: 'no-store'
                });

                console.log('Record fetch response status:', response.status);

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const result = await response.json();
                console.log('Record fetch result:', result);

                if (!result.success) {
                    throw new Error(result.message || 'Failed to load record');
                }

                const recordData = result.data;
                console.log('Record data loaded:', recordData);

                displayOralRecord(recordData);

                if (loadingStatus) loadingStatus.classList.add('hidden');

            } catch (error) {
                console.error('Error loading oral record:', error);
                if (loadingStatus) loadingStatus.classList.add('hidden');

                // Show error to user
                if (oralDataContainer) {
                    oralDataContainer.innerHTML = `
                <div class="p-4 mb-4 text-sm text-red-800 rounded-lg bg-red-50 dark:bg-gray-800 dark:text-red-400" role="alert">
                    <span class="font-medium">Error!</span> Unable to load oral health record: ${error.message}
                </div>
                `;
                }
            }
        }

        function displayOralRecord(record) {
            const oralDataContainer = document.getElementById("oralDataContainer");

            console.log('displayOralRecord called with:', record);

            if (!record || !oralDataContainer) {
                console.error('No record or container found');
                return;
            }

            // Format date for display
            let displayDate = 'Date not available';
            try {
                const date = record.created_at ? new Date(record.created_at) : new Date();
                displayDate = date.toLocaleDateString('en-PH', {
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit'
                });
            } catch (e) {
                console.warn('Error formatting display date:', e);
            }

            // Function to check if a condition is present
            const isPresent = (value) => {
                if (value === null || value === undefined) return false;

                // Try boolean field first (ending with _bool)
                if (typeof value === 'boolean') return value;

                // Try regular field
                const strValue = String(value).trim().toLowerCase();
                return ['✓', '√', '1', 'true', 'yes', 'present', 'checked', 'on'].includes(strValue);
            };

            // Function to get display text and color
            const getConditionDisplay = (value, boolValue = null) => {
                const present = boolValue !== null ? boolValue : isPresent(value);
                return {
                    text: present ? 'Present ✓' : 'Absent ✗',
                    colorClass: present ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400',
                    bgColor: present ? 'bg-green-50 dark:bg-green-900/20' : 'bg-red-50 dark:bg-red-900/20',
                    borderColor: present ? 'border-green-200 dark:border-green-800' : 'border-red-200 dark:border-red-800'
                };
            };

            // Handle "others" field specially
            const othersValue = record.others || '';
            const othersPresent = othersValue && othersValue.trim() !== '';

            // Create HTML for oral conditions - FULL TEMPLATE
            const conditionsHTML = `
            <div class="p-4 bg-white rounded-lg shadow dark:border dark:bg-gray-800 dark:border-gray-700 mb-4">
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-4 gap-2">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Oral Health Examination</h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400">${displayDate}</p>
                    </div>
                    <div class="flex flex-col items-end gap-1">
                        <span class="text-xs text-gray-500 dark:text-gray-400 bg-gray-100 dark:bg-gray-700 px-2 py-1 rounded">
                            Record ID: ${record.id || ''}
                        </span>
                    </div>
                </div>

                <div class="mb-6">
                    <h4 class="text-md font-medium text-gray-900 dark:text-white mb-3">A. Oral Conditions</h4>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                        ${[
                            { key: 'orally_fit_child', label: 'Orally Fit Child (OFC)' },
                            { key: 'dental_caries', label: 'Dental Caries' },
                            { key: 'gingivitis', label: 'Gingivitis' },
                            { key: 'periodontal_disease', label: 'Periodontal Disease' },
                            { key: 'debris', label: 'Debris' },
                            { key: 'calculus', label: 'Calculus' },
                            { key: 'abnormal_growth', label: 'Abnormal Growth' },
                            { key: 'cleft_palate', label: 'Cleft Lip/Palate' }
                        ].map(item => {
                            const display = getConditionDisplay(
                                record[item.key], 
                                record[item.key + '_bool']
                            );
                            return `<div class="flex items-center justify-between p-3 ${display.bgColor} rounded-lg border ${display.borderColor}">
                                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">${item.label}</span>
                                <span class="text-sm font-semibold ${display.colorClass}">${display.text}</span>
                            </div>`;
                        }).join('')}
                        
                        <!-- Special handling for Others field -->
                        <div class="flex flex-col p-3 ${othersPresent ? 'bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800' : 'bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800'} rounded-lg">
                            <div class="flex justify-between items-center mb-2">
                                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Other Conditions</span>
                                <span class="text-sm font-semibold ${othersPresent ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'}">
                                    ${othersPresent ? 'Present ✓' : 'Absent ✗'}
                                </span>
                            </div>
                            ${othersPresent ? `
                                <div class="mt-2 p-2 bg-white dark:bg-gray-800 rounded border">
                                    <p class="text-sm text-gray-600 dark:text-gray-300">${othersValue}</p>
                                </div>
                            ` : ''}
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Permanent Teeth -->
                    <div class="p-4 bg-blue-50 dark:bg-blue-900/10 rounded-lg border border-blue-200 dark:border-blue-800">
                        <h4 class="text-md font-medium text-blue-800 dark:text-blue-300 mb-4">Permanent Teeth</h4>
                        <div class="space-y-3">
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-gray-700 dark:text-gray-300">Teeth Present:</span>
                                <span class="text-lg font-bold text-blue-700 dark:text-blue-300">${record.perm_teeth_present || 0}</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-gray-700 dark:text-gray-300">Sound Teeth:</span>
                                <span class="text-lg font-bold text-blue-700 dark:text-blue-300">${record.perm_sound_teeth || 0}</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-gray-700 dark:text-gray-300">Decayed (D):</span>
                                <span class="text-lg font-bold text-blue-700 dark:text-blue-300">${record.perm_decayed_teeth_d || 0}</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-gray-700 dark:text-gray-300">Missing (M):</span>
                                <span class="text-lg font-bold text-blue-700 dark:text-blue-300">${record.perm_missing_teeth_m || 0}</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-gray-700 dark:text-gray-300">Filled (F):</span>
                                <span class="text-lg font-bold text-blue-700 dark:text-blue-300">${record.perm_filled_teeth_f || 0}</span>
                            </div>
                            <div class="flex justify-between items-center pt-3 border-t border-blue-200 dark:border-blue-700">
                                <span class="text-sm font-semibold text-gray-800 dark:text-gray-200">Total DMF:</span>
                                <span class="text-xl font-bold text-blue-800 dark:text-blue-400">${record.perm_total_dmf || 0}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Temporary Teeth -->
                    <div class="p-4 bg-green-50 dark:bg-green-900/10 rounded-lg border border-green-200 dark:border-green-800">
                        <h4 class="text-md font-medium text-green-800 dark:text-green-300 mb-4">Temporary Teeth</h4>
                        <div class="space-y-3">
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-gray-700 dark:text-gray-300">Teeth Present:</span>
                                <span class="text-lg font-bold text-green-700 dark:text-green-300">${record.temp_teeth_present || 0}</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-gray-700 dark:text-gray-300">Sound Teeth:</span>
                                <span class="text-lg font-bold text-green-700 dark:text-green-300">${record.temp_sound_teeth || 0}</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-gray-700 dark:text-gray-300">Decayed (d):</span>
                                <span class="text-lg font-bold text-green-700 dark:text-green-300">${record.temp_decayed_teeth_d || 0}</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-gray-700 dark:text-gray-300">Filled (f):</span>
                                <span class="text-lg font-bold text-green-700 dark:text-green-300">${record.temp_filled_teeth_f || 0}</span>
                            </div>
                            <div class="flex justify-between items-center pt-3 border-t border-green-200 dark:border-green-700">
                                <span class="text-sm font-semibold text-gray-800 dark:text-gray-200">Total df:</span>
                                <span class="text-xl font-bold text-green-800 dark:text-green-400">${record.temp_total_df || 0}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Summary -->
                <div class="mt-6 p-4 bg-gray-50 dark:bg-gray-800/50 rounded-lg border border-gray-200 dark:border-gray-700">
                    <h4 class="text-md font-medium text-gray-900 dark:text-white mb-2">Summary</h4>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div class="text-center p-3 bg-white dark:bg-gray-800 rounded border">
                            <p class="text-sm text-gray-600 dark:text-gray-400">Total Permanent DMF</p>
                            <p class="text-2xl font-bold text-blue-600 dark:text-blue-400">${record.perm_total_dmf || 0}</p>
                        </div>
                        <div class="text-center p-3 bg-white dark:bg-gray-800 rounded border">
                            <p class="text-sm text-gray-600 dark:text-gray-400">Total Temporary df</p>
                            <p class="text-2xl font-bold text-green-600 dark:text-green-400">${record.temp_total_df || 0}</p>
                        </div>
                    </div>
                </div>
            </div>
            `;

            // Display the record
            oralDataContainer.innerHTML = conditionsHTML;
            console.log('Record displayed successfully');
        }

        function showNoRecordsMessage() {
            const noRecordsMessage = document.getElementById("noRecordsMessage");
            const oralDataContainer = document.getElementById("oralDataContainer");
            const dateSelect = document.getElementById("dataSelect");

            console.log('showNoRecordsMessage called');

            if (noRecordsMessage) {
                noRecordsMessage.classList.remove('hidden');

                // Set patient name if available
                const patientNameSpan = noRecordsMessage.querySelector('#patientNamePlaceholder') ||
                    noRecordsMessage.querySelector('.patient-name-placeholder');
                if (patientNameSpan && patientName) {
                    patientNameSpan.textContent = patientName;
                }
            }

            if (oralDataContainer) oralDataContainer.innerHTML = '';
            if (dateSelect) {
                dateSelect.innerHTML = '<option value="" selected disabled>No records available</option>';
                dateSelect.disabled = true;
            }
        }

        function showLoading(show) {
            const loadingStatus = document.getElementById("loadingStatus");
            console.log('showLoading called with:', show);

            if (loadingStatus) {
                if (show) {
                    loadingStatus.classList.remove('hidden');
                    loadingStatus.innerHTML = `
                <div class="flex items-center justify-center space-x-2">
                    <div class="w-4 h-4 border-2 border-blue-600 border-t-transparent rounded-full animate-spin"></div>
                    <span class="text-blue-600 dark:text-blue-400">Loading oral health records...</span>
                </div>
                `;
                } else {
                    loadingStatus.classList.add('hidden');
                }
            }
        }

        function showAlert(message, type = 'error') {
            console.log('showAlert:', message, type);

            const alertDiv = document.createElement('div');
            alertDiv.className = `fixed top-4 right-4 z-50 p-4 rounded-lg shadow-lg ${type === 'error' ? 'bg-red-100 border border-red-300 text-red-800' : 'bg-blue-100 border border-blue-300 text-blue-800'}`;
            alertDiv.innerHTML = `
            <div class="flex items-center">
                <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                </svg>
                <span>${message}</span>
            </div>
        `;
            document.body.appendChild(alertDiv);

            setTimeout(() => {
                alertDiv.remove();
            }, 5000);
        }

        function showSaveSuccess(message) {
            const saveSuccessMessage = document.getElementById("saveSuccessMessage");
            const successMessageText = document.getElementById("successMessageText");

            if (saveSuccessMessage && successMessageText) {
                successMessageText.textContent = message;
                saveSuccessMessage.classList.remove('hidden');

                setTimeout(() => {
                    saveSuccessMessage.classList.add('hidden');
                }, 5000);
            }
        }

        // Make functions globally available
        window.refreshRecords = refreshRecords;
        window.loadSelectedRecord = loadSelectedRecord;
        window.openOHCModal = openOHCModal;
        window.closeOHCModal = closeOHCModal;
        window.next = navigateToNext;
        window.backmain = backmain;
        window.saveOHC = saveOHC;

        // Add these placeholder functions
        function loadSelectedRecord() {
            const dateSelect = document.getElementById("dataSelect");
            const selectedValue = dateSelect.value;

            if (selectedValue) {
                console.log('Loading selected record:', selectedValue);
                loadOralRecord(selectedValue);
            }
        }

        function navigateToNext() {
            if (currentPatientId && currentUserId) {
                window.location.href = `view_oralA.php?uid=${currentUserId}&id=${currentPatientId}`;
            }
        }
        
        function backmain() {
            if (currentUserId) {
                window.location.href = `treatmentrecords.php?uid=${currentUserId}`;
            }
        }

        function openOHCModal() {
            const modal = document.getElementById('ohcModal');
            if (modal) {
                modal.classList.remove('hidden');
                modal.classList.add('flex');
            }
        }

        function closeOHCModal() {
            const modal = document.getElementById('ohcModal');
            if (modal) {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
            }
        }

        // Inactivity timer (keep your existing code)
        let inactivityTime = 600000;
        let logoutTimer;

        function resetTimer() {
            clearTimeout(logoutTimer);
            logoutTimer = setTimeout(() => {
                alert("You've been logged out due to 10 minutes of inactivity.");
                window.location.href = "/dentalemr_system/php/login/logout.php?uid=" + currentUserId;
            }, inactivityTime);
        }

        ["click", "mousemove", "keypress", "scroll", "touchstart"].forEach(evt => {
            document.addEventListener(evt, resetTimer, false);
        });

        resetTimer();
    </script>

    <script>
        function getValue(id) {
            const form = document.getElementById("ohcForm");
            const el = form.querySelector(`#${id}`);
            if (!el) return "";

            // For check fields (text inputs that toggle ✓/✗)
            if (el.type === "text" && el.hasAttribute('readonly') && el.onclick) {
                return el.value?.trim() || "";
            }

            // For number fields
            if (el.type === "number") {
                return el.value || "0";
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
            console.log('Saving OHC data...');

            const form = document.getElementById("ohcForm");
            const patient_id = form.querySelector("#patient_id")?.value;

            if (!patient_id || patient_id <= 0) {
                alert("No patient selected or invalid patient ID.");
                return;
            }

            // Prepare form data
            const formData = new FormData();
            formData.append('patient_id', patient_id);

            // Collect all form values
            const fields = [
                'orally_fit_child', 'dental_caries', 'gingivitis', 'periodontal_disease',
                'debris', 'calculus', 'abnormal_growth', 'cleft_palate', 'others',
                'perm_teeth_present', 'perm_sound_teeth', 'perm_decayed_teeth_d',
                'perm_missing_teeth_m', 'perm_filled_teeth_f', 'perm_total_dmf',
                'temp_teeth_present', 'temp_sound_teeth', 'temp_decayed_teeth_d',
                'temp_filled_teeth_f', 'temp_total_df'
            ];

            fields.forEach(field => {
                const value = getValue(field);
                formData.append(field, value);
            });

            console.log("Saving oral health data for patient:", patient_id);

            try {
                // Use FormData instead of JSON for better compatibility
                const response = await fetch("/dentalemr_system/php/treatmentrecords/save_ohc.php", {
                    method: "POST",
                    body: formData
                });

                const text = await response.text();
                console.log("Server response:", text);

                // Try to parse JSON
                let result;
                try {
                    result = JSON.parse(text);
                } catch (parseError) {
                    console.error("JSON Parse Error:", parseError);
                    console.error("Raw response text:", text);

                    // Check if the response contains HTML or PHP errors
                    if (text.includes('<') || text.includes('PHP') || text.includes('Error')) {
                        alert("Server returned an error page. Please check server logs.");
                    } else {
                        alert("Server returned invalid response. Please try again.");
                    }
                    return;
                }

                if (result.success) {
                    alert(result.message);
                    closeOHCModal();
                    // Force a complete page reload to show new records
                    setTimeout(() => {
                        location.reload(true); // true forces reload from server
                    }, 300);
                } else {
                    alert(result.message || "Error saving data.");
                }
            } catch (err) {
                console.error("Network Error:", err);
                alert("Failed to save data. Please check your connection and try again.");
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

            // Make saveOHC globally available
            window.saveOHC = saveOHC;
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