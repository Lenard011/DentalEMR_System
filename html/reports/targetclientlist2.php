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
// ========== NEW: Get parameters from URL ==========
$selectedYear = isset($_GET['year']) && is_numeric($_GET['year']) ? (int)$_GET['year'] : date('Y');
$currentPage = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$addressFilter = isset($_GET['address']) ? trim($_GET['address']) : '';
$startRow = isset($_GET['startRow']) ? (int)$_GET['startRow'] : 0;

// Validate year range
$currentYear = date('Y');
if ($selectedYear < 2000 || $selectedYear > $currentYear + 1) {
    $selectedYear = $currentYear;
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
    } // Fetch dentist name if user is a dentist (only in online mode)
    if ($loggedUser['type'] === 'Dentist') {
        $stmt = $conn->prepare("SELECT name, profile_picture FROM dentist WHERE id = ?");
        $stmt->bind_param("i", $loggedUser['id']);
        $stmt->execute();
        $stmt->bind_result($dentistName, $dentistProfilePicture);
        if ($stmt->fetch()) {
            $loggedUser['name'] = $dentistName;
            $loggedUser['profile_picture'] = $dentistProfilePicture;
        }
        $stmt->close();
    }

    // ========== NEW: Fetch patients data for the table ==========
    // This should match the query from targetclientlist.php to get the same patients
    $limit = 10;
    $offset = ($currentPage - 1) * $limit;

    // Build query similar to targetclientlist.php
    $sql = "SELECT DISTINCT p.* 
            FROM patients p 
            WHERE YEAR(p.created_at) = $selectedYear";

    if ($search !== '') {
        $s = $conn->real_escape_string($search);
        $sql .= " AND (p.surname LIKE '%{$s}%' OR p.firstname LIKE '%{$s}%' OR p.middlename LIKE '%{$s}%')";
    }
    if ($addressFilter !== '') {
        $a = $conn->real_escape_string($addressFilter);
        $sql .= " AND p.address LIKE '%{$a}%'";
    }

    $sql .= " ORDER BY p.created_at DESC LIMIT $limit OFFSET $offset";

    $patients_result = $conn->query($sql);

    // Count total records for pagination info
    $count_sql = "SELECT COUNT(DISTINCT p.patient_id) AS total 
                  FROM patients p 
                  WHERE YEAR(p.created_at) = $selectedYear";

    if ($search !== '') {
        $count_sql .= " AND (p.surname LIKE '%{$s}%' OR p.firstname LIKE '%{$s}%' OR p.middlename LIKE '%{$s}%')";
    }
    if ($addressFilter !== '') {
        $count_sql .= " AND p.address LIKE '%{$a}%'";
    }

    $total_query = $conn->query($count_sql);
    $total_row = $total_query->fetch_assoc();
    $total_records = (int)$total_row['total'];
    $total_pages = ceil($total_records / $limit);

    // Range for display
    $start = ($total_records > 0) ? $offset + 1 : 0;
    $end = min(($offset + $limit), $total_records);

    // Fetch existing target client list data for these patients
    $targetData = [];
    if ($patients_result && $patients_result->num_rows > 0) {
        $patientIds = [];
        $patients_result->data_seek(0); // Reset pointer

        while ($row = $patients_result->fetch_assoc()) {
            $patientIds[] = $row['patient_id'];
        }

        if (!empty($patientIds)) {
            $idsString = implode(',', $patientIds);
            $targetQuery = "SELECT * FROM target_client_list_data 
                           WHERE patient_id IN ($idsString) AND year = $selectedYear";
            $targetResult = $conn->query($targetQuery);

            if ($targetResult) {
                while ($targetRow = $targetResult->fetch_assoc()) {
                    $targetData[$targetRow['patient_id']] = $targetRow;
                }
            }
        }

        // Reset pointer for main display
        $patients_result->data_seek(0);
    }
} else {
    // Offline mode - you'll need to implement similar logic with localStorage
    $patients_result = null;
    $total_records = 0;
    $total_pages = 1;
    $start = 0;
    $end = 0;
    $targetData = [];
}

?>
<!doctype html>
<html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Target Client List</title>
    <!-- <link href="../css/style.css" rel="stylesheet"> -->
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .target-input {
            min-height: 24px;
            transition: all 0.2s ease;
        }

        .target-input:focus {
            background-color: rgba(59, 130, 246, 0.05);
            box-shadow: 0 0 0 1px #3b82f6;
        }

        .target-input:hover:not(:focus) {
            background-color: rgba(243, 244, 246, 0.5);
        }

        /* Make the table more readable */
        table input {
            font-size: 11px;
            padding: 2px 4px;
        }

        /* Ensure table cells maintain size */
        td {
            min-width: 50px;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .overflow-x-auto {
                overflow-x: auto;
            }
        }
    </style>
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
                        <ul id="dropdown-pages" class="hidden py-2 space-y-2">
                            <li>
                                <a href="../treatmentrecords/treatmentrecords.php?uid=<?php echo $userId; ?>"
                                    class="flex items-center p-2 pl-11 w-full text-base font-medium text-gray-900 rounded-lg transition duration-75 group hover:bg-gray-100 dark:text-white dark:hover:bg-gray-700">Treatment
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
                        <a href="#" class="flex items-center p-2 text-base font-medium text-blue-600 rounded-lg dark:text-blue bg-blue-100  dark:hover:bg-blue-700 group">
                            <svg aria-hidden="true"
                                class="w-6 h-6 text-blue-600 transition duration-75 dark:text-blue-400  dark:group-hover:text-blue"
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
                        <a href="./mho_ohp.php?uid=<?php echo $userId; ?>"
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
                        <a href="./oralhygienefindings.php?uid=<?php echo $userId; ?>"
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
        <!-- Individual Patient Treatment Record Inforamition -->
        <main class="p-3 md:ml-64 h-auto pt-20" id="patienttreatment" style="display: flex; flex-direction: column;">
            <div class="text-center">
                <p class="text-lg font-semibold text-gray-900 dark:text-white">Target Client List For</p>
                <p class="text-lg font-semibold text-gray-900 dark:text-white" style="margin-top: -5px;">Oral Health Care And Services</p>
                <p class="text-md font-medium text-gray-700 dark:text-gray-300 mt-2">
                    Year: <span class="font-bold"><?php echo $selectedYear; ?></span>
                    <?php if ($search): ?>
                        | Search: <span class="font-bold">"<?php echo htmlspecialchars($search); ?>"</span>
                    <?php endif; ?>
                </p>
            </div>

            <section class="bg-white dark:bg-gray-900 p-3 rounded-lg mb-3 mt-3">
                <div class="w-full justify-between flex flex-row p-1">
                    <div class="flex items-center space-x-3 w-full md:w-auto">
                        <form class="w-80 items-center" method="GET" action="">
                            <input type="hidden" name="year" value="<?php echo $selectedYear; ?>">
                            <input type="hidden" name="uid" value="<?php echo $userId; ?>">
                            <input type="hidden" name="page" value="<?php echo $currentPage; ?>">
                            <?php if ($addressFilter): ?>
                                <input type="hidden" name="address" value="<?php echo htmlspecialchars($addressFilter); ?>">
                            <?php endif; ?>
                            <?php if ($isOfflineMode): ?>
                                <input type="hidden" name="offline" value="true">
                            <?php endif; ?>
                            <div class="relative">
                                <div class="absolute inset-y-0 start-0 flex items-center ps-3 pointer-events-none">
                                    <svg class="w-3 h-3 text-gray-500 dark:text-gray-400" aria-hidden="true"
                                        xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 20 20">
                                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"
                                            stroke-width="2" d="m19 19-4-4m0-7A7 7 0 1 1 1 8a7 7 0 0 1 14 0Z" />
                                    </svg>
                                </div>
                                <input type="search" name="search"
                                    value="<?php echo htmlspecialchars($search); ?>"
                                    class="block w-full p-2 ps-10 text-sm text-gray-900 border border-gray-300 rounded-lg bg-gray-50 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500"
                                    placeholder="Search patient" />
                                <button type="submit" class="text-white absolute end-1.5 bottom-1.5 bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:outline-none focus:ring-blue-300 font-medium rounded-lg text-xs px-2 py-1 dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800">
                                    Search
                                </button>
                            </div>
                        </form>
                    </div>
                    <div class="items-center flex flex-col gap-0 ">
                        <label for="name" class="flex mb-2 text-sm font-medium text-gray-900 dark:text-white">Part
                            1/2</label>
                        <div class="flex flex-row items-center gap-1">
                            <div class="rounded-full bg-gray-200 border border-gray-500 h-5 w-5"></div>
                            <div class="rounded-full  bg-gray-600 border border-gray-500 h-5 w-5"></div>
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
                            <table class="min-w-[1600px] w-full text-xs text-gray-600 dark:text-gray-300 border border-gray-300 border-collapse">
                                <!-- Column widths - FIXED: Added remarks column and adjusted colspan counts -->
                                <colgroup>
                                    <col style="width: 50px;"> <!-- No -->

                                    <!-- Oral Health Services Provided (18 columns) -->
                                    <col style="width: 90px;">
                                    <col style="width: 90px;">
                                    <col style="width: 90px;">
                                    <col style="width: 90px;">
                                    <col style="width: 90px;">
                                    <col style="width: 90px;">
                                    <col style="width: 90px;">
                                    <col style="width: 90px;">
                                    <col style="width: 90px;">
                                    <col style="width: 90px;">
                                    <col style="width: 90px;">
                                    <col style="width: 90px;">
                                    <col style="width: 90px;">
                                    <col style="width: 90px;">
                                    <col style="width: 90px;">
                                    <col style="width: 90px;">
                                    <col style="width: 90px;">
                                    <col style="width: 90px;">

                                    <!-- Provided with Basic Oral Health Care (7 columns) -->
                                    <col style="width: 150px;">
                                    <col style="width: 150px;">
                                    <col style="width: 150px;">
                                    <col style="width: 150px;">
                                    <col style="width: 150px;">
                                    <col style="width: 150px;">
                                    <col style="width: 150px;">

                                    <!-- Remarks column (1 column) -->
                                    <col style="width: 250px;"> <!-- Added this line for remarks -->
                                </colgroup>

                                <thead class="text-xs align-top text-gray-700 bg-gray-50 dark:bg-gray-700 dark:text-gray-300">
                                    <!-- Row 1 -->
                                    <tr class="h-[20px] leading-[1.2]">
                                        <th rowspan="3" class="whitespace-nowrap border border-gray-300 px-1 py-2">No.</th>
                                        <!-- Oral Health Services Provided -->
                                        <th colspan="18" class="whitespace-nowrap border border-gray-300 px-1 py-2 leading-3.5">
                                            Oral Health Services Provided<br>
                                            <span class="text-[14px] font-semibold">(Write data given)<br>(10)</span>
                                        </th>
                                        <!-- Provided with Basic Oral Health Care -->
                                        <th colspan="7" class="whitespace-nowrap border border-gray-300 px-1 py-2 leading-3.5"> <!-- Changed from 25 to 7 -->
                                            Provided with Basic Oral Health Care (BOHC)<br>
                                            <span class="text-[14px] font-semibold">(Input data given)<br>(11)</span>
                                        </th>
                                        <!-- Remarks -->
                                        <th rowspan="3" class="border border-gray-300 px-2 py-2 leading-3.5">
                                            Remarks<br><br>
                                            <span class="font-semibold text-[14px]">(12)</span>
                                        </th>
                                    </tr>

                                    <!-- Row 2 -->
                                    <tr class="h-[20px] leading-[1.2]">
                                        <!-- Empty cells for Oral Health Services Provided (these are sub-columns) -->
                                        <?php for ($i = 0; $i < 18; $i++): ?>
                                            <th class="border border-gray-300 px-1 py-2 border-b-0"></th>
                                        <?php endfor; ?>

                                        <!-- BOHC Age Group Headers -->
                                        <th class="border border-gray-300 px-1 py-2"><span class="text-[11px] font-extrabold">0-11 mos.</span></th>
                                        <th class="border border-gray-300 px-1 py-2"><span class="text-[11px] font-extrabold">1-4 y/o<br>(12-59 mos)</span></th>
                                        <th class="border border-gray-300 px-1 py-2"><span class="text-[11px] font-extrabold">5-9 y/o</span></th>
                                        <th class="border border-gray-300 px-1 py-2"><span class="text-[11px] font-extrabold">10-14 &<br>15-19 y/o</span></th>
                                        <th class="border border-gray-300 px-1 py-2"><span class="text-[11px] font-extrabold">20-59 y/o</span></th>
                                        <th class="border border-gray-300 px-1 py-2"><span class="text-[11px] font-extrabold">&gt; 60 y/o</span></th>
                                        <th class="border border-gray-300 px-1 py-2"><span class="text-[11px] font-extrabold">Pregnant</span></th>
                                    </tr>

                                    <!-- Row 3 -->
                                    <tr class="h-[20px] leading-[1.2]">
                                        <!-- Oral Health Services Provided Service Codes -->
                                        <th class="border border-gray-300 px-1 py-2 border-t-0"><span>OE</span></th>
                                        <th class="border border-gray-300 px-1 py-2 border-t-0"><span>IIOHC</span></th>
                                        <th class="border border-gray-300 px-1 py-2 border-t-0"><span>AEBF</span></th>
                                        <th class="border border-gray-300 px-1 py-2 border-t-0"><span>TFA</span></th>
                                        <th class="border border-gray-300 px-1 py-2 border-t-0"><span>STB</span></th>
                                        <th class="border border-gray-300 px-1 py-2 border-t-0"><span>OHE</span></th>
                                        <th class="border border-gray-300 px-1 py-2 border-t-0"><span>E&CC</span></th>
                                        <th class="border border-gray-300 px-1 py-2 border-t-0"><span>ART</span></th>
                                        <th class="border border-gray-300 px-1 py-2 border-t-0"><span>OPS</span></th>
                                        <th class="border border-gray-300 px-1 py-2 border-t-0"><span>PFS</span></th>
                                        <th class="border border-gray-300 px-1 py-2 border-t-0"><span>TF</span></th>
                                        <th class="border border-gray-300 px-1 py-2 border-t-0"><span>PF</span></th>
                                        <th class="border border-gray-300 px-1 py-2 border-t-0"><span>GT</span></th>
                                        <th class="border border-gray-300 px-1 py-2 border-t-0"><span>RP</span></th>
                                        <th class="border border-gray-300 px-1 py-2 border-t-0"><span>RUT</span></th>
                                        <th class="border border-gray-300 px-1 py-2 border-t-0"><span>Ref</span></th>
                                        <th class="border border-gray-300 px-1 py-2 border-t-0"><span>TPEC</span></th>
                                        <th class="border border-gray-300 px-1 py-2 border-t-0"><span>Dr</span></th>

                                        <!-- BOHC Details -->
                                        <th class="border border-gray-300 px-1 py-2 text-[10px]"><span style="font-weight: bolder;">If given OE,<br>IIOHC, AEBF</span><br>(for 0-8 mos.)<br>plus TFA (for<br>9-11 mos. old)</th>
                                        <th class="border border-gray-300 px-1 py-2 text-[10px]"><span style="font-weight: bolder;">If give OE,<br>TBA, STB<br>and the OHE</span><br>and/or<br>ART, OPS</th>
                                        <th class="border border-gray-300 px-1 py-2 text-[10px]"><span style="font-weight: bolder;">If given OE,<br>STB and<br>OHE</span> and/or<br>PFS, TF, PF</th>
                                        <th class="border border-gray-300 px-1 py-2 text-[10px]"><span style="font-weight: bolder;">If given OE,<br>and E&CC</span><br>and/or<br>PFS, TF, PF<br>OPS, OUT: RP,<br>RUT, Ref</th>
                                        <th class="border border-gray-300 px-1 py-2 text-[10px]"><span style="font-weight: bolder;">If given OE<br>and E&CC</span><br>and/or GT,<br>OPS, RP, RUT,<br>Ref</th>
                                        <th class="border border-gray-300 px-1 py-2 text-[10px]"><span style="font-weight: bolder;">If given OE<br>and E&CC</span><br>and/or<br>OUT: RP, RUT,<br>Ref</th>
                                        <th class="border border-gray-300 px-1 py-2 text-[10px]"><span style="font-weight: bolder;">If given OE<br>and E&CC</span><br>and/or<br>OPS, GT, TF<br>PF</th>
                                    </tr>
                                </thead>

                                <tbody>
                                    <?php if (!$patients_result || $patients_result->num_rows === 0): ?>
                                        <tr>
                                            <td colspan="27" class="text-center border border-gray-300 py-8 text-gray-500"> <!-- Fixed colspan to 27 -->
                                                <i class="fas fa-inbox text-2xl mb-2 block"></i>
                                                No patient records found for <?php echo $selectedYear; ?>.
                                                <?php if ($search): ?>
                                                    <br><span class="text-sm">Try a different search or year.</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php else:
                                        $i = $start;
                                        while ($row = $patients_result->fetch_assoc()):
                                            $patientId = $row['patient_id'];
                                            $patientData = $targetData[$patientId] ?? [];
                                            $fullName = trim($row['surname'] . ', ' . $row['firstname'] . ' ' . $row['middlename']);
                                        ?>
                                            <tr class="h-[20px] leading-[1] text-center" data-patient-id="<?php echo $patientId; ?>">
                                                <td class="border border-gray-300 px-1 py-2"><?php echo $i++; ?>
                                                    <div class="text-[10px] text-center mt-1 text-gray-600">
                                                        <?php echo htmlspecialchars($fullName); ?>
                                                    </div>
                                                </td>

                                                <!-- Oral Health Services Provided columns (18 columns) -->
                                                <td class="border border-gray-300 px-1 py-2">
                                                    <input type="text"
                                                        class="target-input w-full text-center border-0 focus:ring-1 focus:ring-blue-500 focus:outline-none bg-transparent"
                                                        data-field="oe"
                                                        value="<?php echo htmlspecialchars($patientData['oe'] ?? ''); ?>"
                                                        maxlength="10">
                                                </td>
                                                <td class="border border-gray-300 px-1 py-2">
                                                    <input type="text"
                                                        class="target-input w-full text-center border-0 focus:ring-1 focus:ring-blue-500 focus:outline-none bg-transparent"
                                                        data-field="iiohc"
                                                        value="<?php echo htmlspecialchars($patientData['iiohc'] ?? ''); ?>"
                                                        maxlength="10">
                                                </td>
                                                <td class="border border-gray-300 px-1 py-2">
                                                    <input type="text"
                                                        class="target-input w-full text-center border-0 focus:ring-1 focus:ring-blue-500 focus:outline-none bg-transparent"
                                                        data-field="aebf"
                                                        value="<?php echo htmlspecialchars($patientData['aebf'] ?? ''); ?>"
                                                        maxlength="10">
                                                </td>
                                                <td class="border border-gray-300 px-1 py-2">
                                                    <input type="text"
                                                        class="target-input w-full text-center border-0 focus:ring-1 focus:ring-blue-500 focus:outline-none bg-transparent"
                                                        data-field="tfa"
                                                        value="<?php echo htmlspecialchars($patientData['tfa'] ?? ''); ?>"
                                                        maxlength="10">
                                                </td>
                                                <td class="border border-gray-300 px-1 py-2">
                                                    <input type="text"
                                                        class="target-input w-full text-center border-0 focus:ring-1 focus:ring-blue-500 focus:outline-none bg-transparent"
                                                        data-field="stb"
                                                        value="<?php echo htmlspecialchars($patientData['stb'] ?? ''); ?>"
                                                        maxlength="10">
                                                </td>
                                                <td class="border border-gray-300 px-1 py-2">
                                                    <input type="text"
                                                        class="target-input w-full text-center border-0 focus:ring-1 focus:ring-blue-500 focus:outline-none bg-transparent"
                                                        data-field="ohe"
                                                        value="<?php echo htmlspecialchars($patientData['ohe'] ?? ''); ?>"
                                                        maxlength="10">
                                                </td>
                                                <td class="border border-gray-300 px-1 py-2">
                                                    <input type="text"
                                                        class="target-input w-full text-center border-0 focus:ring-1 focus:ring-blue-500 focus:outline-none bg-transparent"
                                                        data-field="ecc"
                                                        value="<?php echo htmlspecialchars($patientData['ecc'] ?? ''); ?>"
                                                        maxlength="10">
                                                </td>
                                                <td class="border border-gray-300 px-1 py-2">
                                                    <input type="text"
                                                        class="target-input w-full text-center border-0 focus:ring-1 focus:ring-blue-500 focus:outline-none bg-transparent"
                                                        data-field="art"
                                                        value="<?php echo htmlspecialchars($patientData['art'] ?? ''); ?>"
                                                        maxlength="10">
                                                </td>
                                                <td class="border border-gray-300 px-1 py-2">
                                                    <input type="text"
                                                        class="target-input w-full text-center border-0 focus:ring-1 focus:ring-blue-500 focus:outline-none bg-transparent"
                                                        data-field="ops"
                                                        value="<?php echo htmlspecialchars($patientData['ops'] ?? ''); ?>"
                                                        maxlength="10">
                                                </td>
                                                <td class="border border-gray-300 px-1 py-2">
                                                    <input type="text"
                                                        class="target-input w-full text-center border-0 focus:ring-1 focus:ring-blue-500 focus:outline-none bg-transparent"
                                                        data-field="pfs"
                                                        value="<?php echo htmlspecialchars($patientData['pfs'] ?? ''); ?>"
                                                        maxlength="10">
                                                </td>
                                                <td class="border border-gray-300 px-1 py-2">
                                                    <input type="text"
                                                        class="target-input w-full text-center border-0 focus:ring-1 focus:ring-blue-500 focus:outline-none bg-transparent"
                                                        data-field="tf"
                                                        value="<?php echo htmlspecialchars($patientData['tf'] ?? ''); ?>"
                                                        maxlength="10">
                                                </td>
                                                <td class="border border-gray-300 px-1 py-2">
                                                    <input type="text"
                                                        class="target-input w-full text-center border-0 focus:ring-1 focus:ring-blue-500 focus:outline-none bg-transparent"
                                                        data-field="pf"
                                                        value="<?php echo htmlspecialchars($patientData['pf'] ?? ''); ?>"
                                                        maxlength="10">
                                                </td>
                                                <td class="border border-gray-300 px-1 py-2">
                                                    <input type="text"
                                                        class="target-input w-full text-center border-0 focus:ring-1 focus:ring-blue-500 focus:outline-none bg-transparent"
                                                        data-field="gt"
                                                        value="<?php echo htmlspecialchars($patientData['gt'] ?? ''); ?>"
                                                        maxlength="10">
                                                </td>
                                                <td class="border border-gray-300 px-1 py-2">
                                                    <input type="text"
                                                        class="target-input w-full text-center border-0 focus:ring-1 focus:ring-blue-500 focus:outline-none bg-transparent"
                                                        data-field="rp"
                                                        value="<?php echo htmlspecialchars($patientData['rp'] ?? ''); ?>"
                                                        maxlength="10">
                                                </td>
                                                <td class="border border-gray-300 px-1 py-2">
                                                    <input type="text"
                                                        class="target-input w-full text-center border-0 focus:ring-1 focus:ring-blue-500 focus:outline-none bg-transparent"
                                                        data-field="rut"
                                                        value="<?php echo htmlspecialchars($patientData['rut'] ?? ''); ?>"
                                                        maxlength="10">
                                                </td>
                                                <td class="border border-gray-300 px-1 py-2">
                                                    <input type="text"
                                                        class="target-input w-full text-center border-0 focus:ring-1 focus:ring-blue-500 focus:outline-none bg-transparent"
                                                        data-field="ref"
                                                        value="<?php echo htmlspecialchars($patientData['ref'] ?? ''); ?>"
                                                        maxlength="10">
                                                </td>
                                                <td class="border border-gray-300 px-1 py-2">
                                                    <input type="text"
                                                        class="target-input w-full text-center border-0 focus:ring-1 focus:ring-blue-500 focus:outline-none bg-transparent"
                                                        data-field="tpec"
                                                        value="<?php echo htmlspecialchars($patientData['tpec'] ?? ''); ?>"
                                                        maxlength="10">
                                                </td>
                                                <td class="border border-gray-300 px-1 py-2">
                                                    <input type="text"
                                                        class="target-input w-full text-center border-0 focus:ring-1 focus:ring-blue-500 focus:outline-none bg-transparent"
                                                        data-field="dr"
                                                        value="<?php echo htmlspecialchars($patientData['dr'] ?? ''); ?>"
                                                        maxlength="10">
                                                </td>

                                                <!-- Provided with Basic Oral Health Care columns (7 columns) -->
                                                <td class="border border-gray-300 px-1 py-2">
                                                    <input type="text"
                                                        class="target-input w-full text-center border-0 focus:ring-1 focus:ring-blue-500 focus:outline-none bg-transparent"
                                                        data-field="bohc_0_11"
                                                        value="<?php echo htmlspecialchars($patientData['bohc_0_11'] ?? ''); ?>"
                                                        maxlength="10">
                                                </td>
                                                <td class="border border-gray-300 px-1 py-2">
                                                    <input type="text"
                                                        class="target-input w-full text-center border-0 focus:ring-1 focus:ring-blue-500 focus:outline-none bg-transparent"
                                                        data-field="bohc_1_4"
                                                        value="<?php echo htmlspecialchars($patientData['bohc_1_4'] ?? ''); ?>"
                                                        maxlength="10">
                                                </td>
                                                <td class="border border-gray-300 px-1 py-2">
                                                    <input type="text"
                                                        class="target-input w-full text-center border-0 focus:ring-1 focus:ring-blue-500 focus:outline-none bg-transparent"
                                                        data-field="bohc_5_9"
                                                        value="<?php echo htmlspecialchars($patientData['bohc_5_9'] ?? ''); ?>"
                                                        maxlength="10">
                                                </td>
                                                <td class="border border-gray-300 px-1 py-2">
                                                    <input type="text"
                                                        class="target-input w-full text-center border-0 focus:ring-1 focus:ring-blue-500 focus:outline-none bg-transparent"
                                                        data-field="bohc_10_19"
                                                        value="<?php echo htmlspecialchars($patientData['bohc_10_19'] ?? ''); ?>"
                                                        maxlength="10">
                                                </td>
                                                <td class="border border-gray-300 px-1 py-2">
                                                    <input type="text"
                                                        class="target-input w-full text-center border-0 focus:ring-1 focus:ring-blue-500 focus:outline-none bg-transparent"
                                                        data-field="bohc_20_59"
                                                        value="<?php echo htmlspecialchars($patientData['bohc_20_59'] ?? ''); ?>"
                                                        maxlength="10">
                                                </td>
                                                <td class="border border-gray-300 px-1 py-2">
                                                    <input type="text"
                                                        class="target-input w-full text-center border-0 focus:ring-1 focus:ring-blue-500 focus:outline-none bg-transparent"
                                                        data-field="bohc_60_plus"
                                                        value="<?php echo htmlspecialchars($patientData['bohc_60_plus'] ?? ''); ?>"
                                                        maxlength="10">
                                                </td>
                                                <td class="border border-gray-300 px-1 py-2">
                                                    <input type="text"
                                                        class="target-input w-full text-center border-0 focus:ring-1 focus:ring-blue-500 focus:outline-none bg-transparent"
                                                        data-field="bohc_pregnant"
                                                        value="<?php echo htmlspecialchars($patientData['bohc_pregnant'] ?? ''); ?>"
                                                        maxlength="10">
                                                </td>

                                                <!-- Remarks column - Now properly aligned -->
                                                <td class="border border-gray-300 px-2 py-2 text-left">
                                                    <input type="text"
                                                        class="target-input w-full border-0 focus:ring-1 focus:ring-blue-500 focus:outline-none bg-transparent"
                                                        data-field="remarks"
                                                        value="<?php echo htmlspecialchars($patientData['remarks'] ?? ''); ?>"
                                                        placeholder="Enter remarks...">
                                                </td>
                                            </tr>
                                    <?php
                                        endwhile;
                                    endif;
                                    ?>
                                </tbody>
                            </table>
                            <!-- Pagination -->
                            <nav class="flex flex-col items-start justify-between p-2 md:flex-row md:items-center md:space-y-0"
                                aria-label="Table navigation">
                                <span class="text-sm font-normal text-gray-500 dark:text-gray-400">
                                    Showing <span class="font-semibold text-gray-900 dark:text-white">
                                        <?= $start ?>-<?= $end ?>
                                    </span> of <span class="font-semibold text-gray-900 dark:text-white">
                                        <?= $total_records ?>
                                    </span> records for <?= $selectedYear ?>
                                </span>

                                <ul class="inline-flex items-stretch -space-x-px">
                                    <!-- Previous Button -->
                                    <li>
                                        <a href="/dentalemr_system/html/reports/targetclientlist2.php?uid=<?php echo $userId; ?>&year=<?php echo $selectedYear; ?>&<?= ($currentPage > 1) ? 'page=' . ($currentPage - 1) : '#' ?><?php
                                                                                                                                                                                                                                    echo $search ? '&search=' . urlencode($search) : '';
                                                                                                                                                                                                                                    echo $addressFilter ? '&address=' . urlencode($addressFilter) : '';
                                                                                                                                                                                                                                    echo $isOfflineMode ? '&offline=true' : '';
                                                                                                                                                                                                                                    ?>"
                                            class="flex items-center justify-center h-full py-1.5 px-3 ml-0 text-gray-500 bg-white rounded-l-[5px] border border-gray-300 hover:bg-gray-100 hover:text-gray-700 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white <?= ($currentPage <= 1) ? 'pointer-events-none opacity-50' : '' ?>">
                                            <span class="sr-only">Previous</span>
                                            <svg class="w-5 h-5" aria-hidden="true" fill="currentColor" viewBox="0 0 20 20"
                                                xmlns="http://www.w3.org/2000/svg">
                                                <path fill-rule="evenodd"
                                                    d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z"
                                                    clip-rule="evenodd"></path>
                                            </svg>
                                        </a>
                                    </li>

                                    <!-- Page Numbers -->
                                    <?php
                                    $range = 3; // how many page links to show around current
                                    for ($i = max(1, $currentPage - $range); $i <= min($total_pages, $currentPage + $range); $i++): ?>
                                        <li>
                                            <a href="/dentalemr_system/html/reports/targetclientlist2.php?uid=<?php echo $userId; ?>&year=<?php echo $selectedYear; ?>&page=<?= $i ?><?php
                                                                                                                                                                                        echo $search ? '&search=' . urlencode($search) : '';
                                                                                                                                                                                        echo $addressFilter ? '&address=' . urlencode($addressFilter) : '';
                                                                                                                                                                                        echo $isOfflineMode ? '&offline=true' : '';
                                                                                                                                                                                        ?>"
                                                class="flex items-center justify-center px-3 py-2 text-sm leading-tight border 
                                        <?= ($i == $currentPage)
                                            ? 'z-10 text-blue-600 bg-blue-50 border-blue-300 hover:bg-blue-100 hover:text-blue-700 dark:border-gray-700 dark:bg-gray-700 dark:text-white'
                                            : 'text-gray-500 bg-white border-gray-300 hover:bg-gray-100 hover:text-gray-700 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white' ?>">
                                                <?= $i ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>

                                    <!-- Ellipsis and Last Page -->
                                    <?php if ($currentPage + $range < $total_pages): ?>
                                        <li>
                                            <span
                                                class="flex items-center justify-center px-3 py-2 text-sm leading-tight text-gray-500 bg-white border border-gray-300 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-400">...</span>
                                        </li>
                                        <li>
                                            <a href="/dentalemr_system/html/reports/targetclientlist2.php?uid=<?php echo $userId; ?>&year=<?php echo $selectedYear; ?>&page=<?= $total_pages ?><?php
                                                                                                                                                                                                echo $search ? '&search=' . urlencode($search) : '';
                                                                                                                                                                                                echo $addressFilter ? '&address=' . urlencode($addressFilter) : '';
                                                                                                                                                                                                echo $isOfflineMode ? '&offline=true' : '';
                                                                                                                                                                                                ?>"
                                                class="flex items-center justify-center px-3 py-2 text-sm leading-tight text-gray-500 bg-white border border-gray-300 hover:bg-gray-100 hover:text-gray-700 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white">
                                                <?= $total_pages ?>
                                            </a>
                                        </li>
                                    <?php endif; ?>

                                    <!-- Next Button -->
                                    <li>
                                        <a href="/dentalemr_system/html/reports/targetclientlist2.php?uid=<?php echo $userId; ?>&year=<?php echo $selectedYear; ?>&<?= ($currentPage < $total_pages) ? 'page=' . ($currentPage + 1) : '#' ?><?php
                                                                                                                                                                                                                                            echo $search ? '&search=' . urlencode($search) : '';
                                                                                                                                                                                                                                            echo $addressFilter ? '&address=' . urlencode($addressFilter) : '';
                                                                                                                                                                                                                                            echo $isOfflineMode ? '&offline=true' : '';
                                                                                                                                                                                                                                            ?>"
                                            class="flex items-center justify-center h-full py-1.5 px-3 leading-tight text-gray-500 bg-white rounded-r-[5px] border border-gray-300 hover:bg-gray-100 hover:text-gray-700 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white <?= ($currentPage >= $total_pages) ? 'pointer-events-none opacity-50' : '' ?>">
                                            <span class="sr-only">Next</span>
                                            <svg class="w-5 h-5" aria-hidden="true" fill="currentColor" viewBox="0 0 20 20"
                                                xmlns="http://www.w3.org/2000/svg">
                                                <path fill-rule="evenodd"
                                                    d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z"
                                                    clip-rule="evenodd"></path>
                                            </svg>
                                        </a>
                                    </li>
                                </ul>
                            </nav>
                        </div>
                    </div>
                </form>
            </section>
            <div class="flex justify-start">
                <button type="button" onclick="back()"
                    class="text-white justify-center  cursor-pointer inline-flex items-center bg-blue-700 hover:bg-blue-800  focus:outline-none focus:ring-blue-300 font-medium rounded-lg text-sm p-1 w-18 dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800">
                    Back
                </button>
            </div>
        </main>
    </div>

    <!-- <script src="../node_modules/flowbite/dist/flowbite.min.js"></script> -->
    <script src="https://cdn.jsdelivr.net/npm/flowbite@3.1.2/dist/flowbite.min.js"></script>
    <script src="../../js/tailwind.config.js"></script>
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
        function back() {
            // Get all current parameters to pass back
            const currentYear = <?php echo $selectedYear; ?>;
            const currentPage = <?php echo $currentPage; ?>;
            const currentSearch = '<?php echo addslashes($search); ?>';
            const currentAddress = '<?php echo addslashes($addressFilter); ?>';
            const userId = <?php echo $userId; ?>;
            const isOffline = <?php echo $isOfflineMode ? 'true' : 'false'; ?>;

            // Build the URL with all current parameters
            let url = `targetclientlist.php?uid=${userId}&year=${currentYear}&page=${currentPage}`;

            if (currentSearch) {
                url += `&search=${encodeURIComponent(currentSearch)}`;
            }

            if (currentAddress) {
                url += `&address=${encodeURIComponent(currentAddress)}`;
            }

            if (isOffline) {
                url += '&offline=true';
            }

            location.href = url;
        }
    </script>

    <!-- Load offline storage -->
    <script src="/dentalemr_system/js/offline-storage.js"></script>

    <script>
        // ========== OFFLINE SUPPORT FOR REPORTS - START ==========

        function setupReportsOffline() {
            const statusElement = document.getElementById('connectionStatus');
            if (!statusElement) {
                const newStatus = document.createElement('div');
                newStatus.id = 'connectionStatus';
                newStatus.className = 'hidden fixed top-4 right-4 z-50';
                document.body.appendChild(newStatus);
            }

            function updateStatus() {
                const indicator = document.getElementById('connectionStatus');
                if (!navigator.onLine) {
                    indicator.innerHTML = `
        <div class="bg-yellow-500 text-white px-4 py-2 rounded-lg shadow-lg flex items-center">
          <i class="fas fa-wifi-slash mr-2"></i>
          <span>Offline Mode - Limited report functionality</span>
        </div>
      `;
                    indicator.classList.remove('hidden');
                } else {
                    indicator.classList.add('hidden');
                }
            }

            window.addEventListener('online', updateStatus);
            window.addEventListener('offline', updateStatus);
            updateStatus();
        }

        document.addEventListener('DOMContentLoaded', function() {
            setupReportsOffline();

            if ('serviceWorker' in navigator) {
                navigator.serviceWorker.register('/dentalemr_system/sw.js')
                    .then(function(registration) {
                        console.log('SW registered for reports');
                    })
                    .catch(function(error) {
                        console.log('SW registration failed:', error);
                    });
            }
        });

        // ========== OFFLINE SUPPORT FOR REPORTS - END ==========
    </script>

    <!-- Target Client List Data Management -->
    <script>
        // Debug function to check if file exists
        function checkSaveFileExists() {
            const baseUrl = window.location.origin;
            const saveUrl = baseUrl + '/dentalemr_system/php/save_targetclient_data.php';

            fetch(saveUrl, {
                    method: 'HEAD'
                })
                .then(response => {
                    if (response.ok) {
                        console.log('Save file exists and is accessible');
                    } else {
                        console.warn('Save file returned status:', response.status);
                    }
                })
                .catch(error => {
                    console.error('Cannot access save file:', error);
                });
        }

        document.addEventListener('DOMContentLoaded', function() {
            checkSaveFileExists();
            const inputs = document.querySelectorAll('.target-input');
            let saveTimeout;
            const saveDelay = 1500; // 1.5 seconds delay after typing

            inputs.forEach(input => {
                // Auto-save on blur (when user clicks out)
                input.addEventListener('blur', function() {
                    saveRowData(this.closest('tr'));
                });

                // Auto-save after typing stops (debounce)
                input.addEventListener('input', function() {
                    clearTimeout(saveTimeout);
                    const row = this.closest('tr');
                    saveTimeout = setTimeout(() => {
                        saveRowData(row);
                    }, saveDelay);
                });

                // Save on Enter key
                input.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        saveRowData(this.closest('tr'));
                        this.blur();
                    }
                });
            });

            function saveRowData(row) {
                const patientId = row.getAttribute('data-patient-id');
                const year = <?php echo $selectedYear; ?>;

                if (!patientId) return;

                // Collect all input values
                const data = {
                    patient_id: patientId,
                    year: year
                };

                // Get all input fields in this row
                const inputs = row.querySelectorAll('.target-input');
                inputs.forEach(input => {
                    const field = input.getAttribute('data-field');
                    data[field] = input.value.trim();
                });

                // Add offline mode check
                const isOfflineMode = <?php echo $isOfflineMode ? 'true' : 'false'; ?>;

                if (isOfflineMode) {
                    // Save to localStorage for offline mode
                    saveToLocalStorage(data);
                } else {
                    // Send to server
                    saveToServer(data);
                }
            }

            function saveToServer(data) {
                const formData = new FormData();

                // Append all data to formData
                for (const key in data) {
                    formData.append(key, data[key]);
                }

                // Add user ID for logging
                formData.append('user_id', <?php echo $userId; ?>);

                console.log('Saving data for patient:', data.patient_id);
                console.log('Data:', data);

                // Try to get the base URL dynamically
                const baseUrl = window.location.origin;
                const saveUrl = baseUrl + '/dentalemr_system/php/save_targetclient_data.php';

                console.log('Saving to URL:', saveUrl);

                fetch(saveUrl, {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'Accept': 'application/json',
                        }
                    })
                    .then(response => {
                        if (!response.ok) {
                            throw new Error(`HTTP error! status: ${response.status}`);
                        }
                        return response.json();
                    })
                    .then(result => {
                        console.log('Save response:', result);
                        if (result.success) {
                            showNotification(result.message || 'Data saved successfully', 'success');
                        } else {
                            const errorMsg = result.error || 'Unknown error';
                            showNotification('Error saving data: ' + errorMsg, 'error');
                            console.error('Save error details:', result);
                        }
                    })
                    .catch(error => {
                        console.error('Network error:', error);

                        // Check if we're offline
                        if (!navigator.onLine) {
                            showNotification('You are offline. Data saved locally.', 'warning');
                            // Save to localStorage as fallback
                            saveToLocalStorage(data);
                        } else {
                            showNotification('Network error. Please check your connection. Error: ' + error.message, 'error');
                        }
                    });
            }

            function saveToLocalStorage(data) {
                const key = `targetclient_${data.patient_id}_${data.year}`;
                try {
                    localStorage.setItem(key, JSON.stringify(data));
                    showNotification('Data saved locally (offline mode)', 'info');

                    // Also store in a pending sync list
                    const pendingSync = JSON.parse(localStorage.getItem('pending_targetclient_sync') || '[]');
                    if (!pendingSync.includes(key)) {
                        pendingSync.push(key);
                        localStorage.setItem('pending_targetclient_sync', JSON.stringify(pendingSync));
                    }
                } catch (error) {
                    console.error('LocalStorage error:', error);
                    showNotification('Error saving locally. Storage might be full.', 'error');
                }
            }

            function showNotification(message, type = 'info') {
                // Remove existing notification
                const existingNotif = document.getElementById('save-notification');
                if (existingNotif) {
                    existingNotif.remove();
                }

                // Create new notification
                const notif = document.createElement('div');
                notif.id = 'save-notification';
                notif.className = `fixed top-20 right-4 z-50 px-4 py-2 rounded-lg shadow-lg text-white font-medium ${
                    type === 'success' ? 'bg-green-500' :
                    type === 'error' ? 'bg-red-500' :
                    type === 'info' ? 'bg-blue-500' : 'bg-gray-500'
                }`;
                notif.innerHTML = `
                    <div class="flex items-center">
                        <i class="fas fa-${type === 'success' ? 'check' : type === 'error' ? 'exclamation' : 'info'}-circle mr-2"></i>
                        <span>${message}</span>
                    </div>
                `;

                document.body.appendChild(notif);

                // Auto-remove after 3 seconds
                setTimeout(() => {
                    if (notif.parentNode) {
                        notif.remove();
                    }
                }, 3000);
            }

            // Sync offline data when online
            window.addEventListener('online', function() {
                const pendingSync = JSON.parse(localStorage.getItem('pending_targetclient_sync') || '[]');
                if (pendingSync.length > 0 && !<?php echo $isOfflineMode ? 'true' : 'false'; ?>) {
                    showNotification('Syncing offline data...', 'info');
                    syncOfflineData();
                }
            });

            function syncOfflineData() {
                const pendingSync = JSON.parse(localStorage.getItem('pending_targetclient_sync') || '[]');

                pendingSync.forEach(key => {
                    const data = JSON.parse(localStorage.getItem(key) || '{}');
                    if (data.patient_id) {
                        saveToServer(data);
                    }
                });

                // Clear pending sync after successful sync
                localStorage.removeItem('pending_targetclient_sync');
                showNotification('Offline data sync initiated', 'info');
            }

            // Add sync function to global scope for the sync button
            window.syncOfflineData = syncOfflineData;
        });
    </script>
</body>

</html>