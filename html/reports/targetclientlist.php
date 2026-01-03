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

<?php
// patients_table.php
// Improved backend for Target Client List table

// Only create new connection if not already created in online mode
if (!$isOfflineMode && $conn) {
    // Use the existing connection
} else {
    // CONFIGURE DB CONNECTION for offline mode or if connection failed
    $host = "localhost";
    $user = "root";
    $pass = "";
    $dbname = "dentalemr_system";

    $conn = new mysqli($host, $user, $pass, $dbname);
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
}

// Helper: normalize boolean-like fields
function is_truthy($v)
{
    if ($v === null) return false;
    $vstr = strtolower((string)$v);
    return in_array($vstr, ['1', 'true', 'y', 'yes', 't']);
}

// Safe getter to prevent undefined array key warnings
function safe_get($array, $key, $default = null)
{
    return isset($array[$key]) ? $array[$key] : $default;
}

// Helper: compute age in years
function compute_age_from_dob($dob)
{
    if (!$dob || $dob === "0000-00-00") return null;
    $dob_dt = new DateTime($dob);
    $now = new DateTime();
    return $now->diff($dob_dt)->y;
}

// Helper: compute months old
function compute_months_from_dob($dob)
{
    if (!$dob || $dob === "0000-00-00") return null;
    $dob_dt = new DateTime($dob);
    $now = new DateTime();
    $diff = $now->diff($dob_dt);
    return ($diff->y * 12) + $diff->m;
}

// Get current year for default filtering
$currentYear = date('Y');

// Get year filter from GET parameter or use current year
$selectedYear = isset($_GET['year']) && is_numeric($_GET['year']) ? (int)$_GET['year'] : $currentYear;

// Validate year range (adjust as needed)
if ($selectedYear < 2000 || $selectedYear > $currentYear + 1) {
    $selectedYear = $currentYear;
}

// Input from GET
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$addressFilter = isset($_GET['address']) ? trim($_GET['address']) : '';

// Pagination
$limit = 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// FIXED: Get DISTINCT patients with their LATEST related records
// Count total distinct patients with filters including year filter
$count_sql = "SELECT COUNT(DISTINCT p.patient_id) AS total 
              FROM patients p 
              WHERE YEAR(p.created_at) = $selectedYear";

if ($search !== '') {
    $s = $conn->real_escape_string($search);
    $count_sql .= " AND (p.surname LIKE '%{$s}%' OR p.firstname LIKE '%{$s}%' OR p.middlename LIKE '%{$s}%')";
}
if ($addressFilter !== '') {
    $a = $conn->real_escape_string($addressFilter);
    $count_sql .= " AND p.address LIKE '%{$a}%'";
}

$total_query = $conn->query($count_sql);
$total_row = $total_query->fetch_assoc();
$total_records = (int)$total_row['total'];
$total_pages = ceil($total_records / $limit);

// FIXED: Main query - Get DISTINCT patients with their LATEST related records
// Since patient_other_info doesn't have created_at, we'll use info_id (assuming higher ID = newer)
// And oral_health_condition has created_at for ordering
$sql = "
SELECT 
    p.*,
    -- Get the latest patient_other_info record for each patient (using highest info_id)
    COALESCE(
        (SELECT o.indigenous_people 
         FROM patient_other_info o 
         WHERE o.patient_id = p.patient_id 
         ORDER BY o.info_id DESC 
         LIMIT 1), 
        ''
    ) AS indigenous_people,
    -- Get the latest oral_health_condition record for each patient
    COALESCE(
        (SELECT oh.orally_fit_child 
         FROM oral_health_condition oh 
         WHERE oh.patient_id = p.patient_id 
         ORDER BY oh.created_at DESC 
         LIMIT 1), 
        ''
    ) AS orally_fit_child,
    -- Get the latest perm_decayed_teeth_d
    (SELECT oh.perm_decayed_teeth_d 
     FROM oral_health_condition oh 
     WHERE oh.patient_id = p.patient_id 
     ORDER BY oh.created_at DESC 
     LIMIT 1) AS perm_decayed_teeth_d,
    -- Get the latest perm_missing_teeth_m
    (SELECT oh.perm_missing_teeth_m 
     FROM oral_health_condition oh 
     WHERE oh.patient_id = p.patient_id 
     ORDER BY oh.created_at DESC 
     LIMIT 1) AS perm_missing_teeth_m,
    -- Get the latest perm_filled_teeth_f
    (SELECT oh.perm_filled_teeth_f 
     FROM oral_health_condition oh 
     WHERE oh.patient_id = p.patient_id 
     ORDER BY oh.created_at DESC 
     LIMIT 1) AS perm_filled_teeth_f,
    -- Get the latest oral health recorded date
    (SELECT oh.created_at 
     FROM oral_health_condition oh 
     WHERE oh.patient_id = p.patient_id 
     ORDER BY oh.created_at DESC 
     LIMIT 1) AS oral_health_recorded_at
FROM patients p
WHERE YEAR(p.created_at) = $selectedYear";

// Apply search filter
if ($search !== '') {
    $sql .= " AND (p.surname LIKE '%{$s}%' OR p.firstname LIKE '%{$s}%' OR p.middlename LIKE '%{$s}%')";
}
// Apply address filter
if ($addressFilter !== '') {
    $sql .= " AND p.address LIKE '%{$a}%'";
}

// Group by patient to ensure uniqueness
$sql .= " GROUP BY p.patient_id";

// Order and limit
$sql .= " ORDER BY p.created_at DESC LIMIT $limit OFFSET $offset";

$res = $conn->query($sql);
if (!$res) {
    die("Query error: " . $conn->error);
}

// Get available years from database for dropdown
$years_sql = "SELECT DISTINCT YEAR(created_at) as year FROM patients ORDER BY year DESC";
$years_result = $conn->query($years_sql);
$availableYears = [];
if ($years_result) {
    while ($row = $years_result->fetch_assoc()) {
        $availableYears[] = $row['year'];
    }
}

// If no years found, add current year
if (empty($availableYears)) {
    $availableYears[] = $currentYear;
}

// Range for display
$start = ($total_records > 0) ? $offset + 1 : 0;
$end = min(($offset + $limit), $total_records);
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
        <!-- Individual Patient Treatment Record Inforamtion -->
        <main class="p-3 md:ml-64 h-auto pt-20" id="patienttreatment" style="display: flex; flex-direction: column;">
            <div class="text-center">
                <p class="text-lg font-semibold text-gray-900 dark:text-white">Target Client List For
                </p>
                <p class="text-lg font-semibold text-gray-900 dark:text-white" style="margin-top: -5px;">Oral Health
                    Care And Services
                </p>
                <p class="text-md font-medium text-gray-700 dark:text-gray-300 mt-2">
                    Year: <span class="font-bold"><?php echo $selectedYear; ?></span>
                </p>
            </div>

            <section class="bg-white dark:bg-gray-900 pt-3 px-3 rounded-lg mb-3 mt-3">
                <div class="w-full justify-between flex flex-row p-1">
                    <div class="flex items-center space-x-3 w-full md:w-auto">
                        <!-- Year Filter Dropdown -->
                        <div class="relative">
                            <button id="yearDropdownButton" data-dropdown-toggle="yearDropdown"
                                class="flex items-center justify-center py-2 px-4 text-sm font-medium text-gray-900 focus:outline-none bg-white rounded-lg border border-gray-200 hover:bg-gray-100 hover:text-primary-700 focus:z-10 dark:bg-gray-800 dark:text-gray-400 dark:border-gray-600 dark:hover:text-white dark:hover:bg-gray-700"
                                type="button">
                                <i class="fas fa-calendar-alt mr-2 text-gray-500"></i>
                                Year: <?php echo $selectedYear; ?>
                                <svg class="-mr-1 ml-1.5 w-5 h-5" fill="currentColor" viewbox="0 0 20 20"
                                    xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                    <path clip-rule="evenodd" fill-rule="evenodd"
                                        d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" />
                                </svg>
                            </button>
                            <div id="yearDropdown"
                                class="z-10 hidden w-32 p-3 bg-white rounded-lg shadow dark:bg-gray-700">
                                <h6 class="mb-3 text-sm font-medium text-gray-900 dark:text-white">Select Year</h6>
                                <ul class="space-y-2 text-sm" aria-labelledby="yearDropdownButton">
                                    <?php foreach ($availableYears as $year): ?>
                                        <li>
                                            <a href="?year=<?php echo $year; ?>&uid=<?php echo $userId; ?>"
                                                class="block px-2 py-1 rounded hover:bg-gray-100 dark:hover:bg-gray-600 <?php echo $year == $selectedYear ? 'bg-blue-100 dark:bg-blue-900 text-blue-700 dark:text-blue-300' : ''; ?>">
                                                <?php echo $year; ?>
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>

                        <form class="w-80 items-center" method="GET" action="">
                            <input type="hidden" name="year" value="<?php echo $selectedYear; ?>">
                            <input type="hidden" name="uid" value="<?php echo $userId; ?>">
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
                            </div>
                        </form>
                    </div>

                    <div class="items-center flex flex-col gap-0 ">
                        <label for="name" class="flex mb-2 text-sm font-medium text-gray-900 dark:text-white">Part 1/2</label>
                        <div class="flex flex-row items-center gap-1">
                            <div class="rounded-full bg-gray-600 border border-gray-500 h-5 w-5"></div>
                            <div class="rounded-full  bg-gray-200 border border-gray-500 h-5 w-5"></div>
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
                                        d="M3 3a1 1 0 011-1h12a1 1 0 011 1v3a1 1 0 01-.293.707L12 11.414V15a1 1 0 01-.293.707l-2 2A1 1 0 088 17v-5.586L3.293 6.707A1 1 0 013 6V3z"
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
                                        <input id="apple" type="checkbox" value="Balansay" name="address"
                                            onclick="document.location = '?address=Balansay';"
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

                <!-- Info Box showing year statistics -->
                <div class="mt-4 mb-4 p-3 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg">
                    <div class="flex items-center justify-between">
                        <div>
                            <h4 class="text-sm font-semibold text-blue-800 dark:text-blue-300">
                                <i class="fas fa-info-circle mr-2"></i>
                                Year <?php echo $selectedYear; ?> - Patient Records
                            </h4>
                            <p class="text-xs text-blue-700 dark:text-blue-400 mt-1">
                                Showing only patients registered in <?php echo $selectedYear; ?>.
                                <?php if ($total_records > 0): ?>
                                    Total: <?php echo $total_records; ?> patient<?php echo $total_records != 1 ? 's' : ''; ?>
                                <?php else: ?>
                                    No patient records found for this year.
                                <?php endif; ?>
                            </p>
                        </div>
                        <?php if ($total_records > 0): ?>
                            <button onclick="exportYearReport(<?php echo $selectedYear; ?>)"
                                class="text-sm bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded-lg flex items-center gap-2">
                                <i class="fas fa-download"></i>
                                Export <?php echo $selectedYear; ?> Report
                            </button>
                        <?php endif; ?>
                    </div>
                </div>

                <form action="#">
                    <div class="grid gap-2 mb-4 mt-5">
                        <div class="overflow-x-auto">
                            <table class="min-w-[1600px] w-full text-xs text-gray-600 dark:text-gray-300 border border-gray-300 border-collapse">
                                <!-- Column widths -->
                                <colgroup>
                                    <col style="width: 50px;"> <!-- No -->
                                    <col style="width: 120px;"> <!-- Date of Consultation -->
                                    <col style="width: 100px;"> <!-- Family Serial No -->
                                    <col style="width: 150px;"> <!-- Name of Client -->
                                    <col style="width: 40px;"> <!-- Sex M -->
                                    <col style="width: 40px;"> <!-- Sex F -->
                                    <col style="width: 180px;"> <!-- Complete Address -->
                                    <col style="width: 100px;"> <!-- Date of Birth -->

                                    <!-- Age / Risk Group -->
                                    <col style="width: 90px;">
                                    <col style="width: 100px;">
                                    <col style="width: 90px;">
                                    <col style="width: 100px;">
                                    <col style="width: 100px;">
                                    <col style="width: 100px;">
                                    <col style="width: 100px;">
                                    <col style="width: 100px;">
                                    <col style="width: 100px;">
                                    <col style="width: 100px;">

                                    <col style="width: 100px;"> <!-- Indigenous People -->
                                    <col style="width: 100px;"> <!-- Oral Health -->
                                    <col style="width: 100px;"> <!-- Oral Health -->
                                    <col style="width: 90px;"> <!-- DMFT -->
                                    <col style="width: 90px;"> <!-- DMFT -->
                                    <col style="width: 90px;"> <!-- DMFT -->
                                </colgroup>

                                <thead
                                    class="text-xs align-top  text-gray-700 bg-gray-50 dark:bg-gray-700 dark:text-gray-300">
                                    <!-- Row 1 -->
                                    <tr class="h-[20px] leading-[1.2]">
                                        <th rowspan="3" class="whitespace-nowrap border border-gray-300 px-1 py-2">No.
                                        </th>
                                        <th rowspan="3" class="whitespace-nowrap border border-gray-300 px-1 py-2">
                                            Date<br>of<br>Consultation<br><br>
                                            <span class="text-[10px] font-normal">(mm/dd/yy)</span><br><br><br>
                                            <span class="font-bold text-[11px]">(1)</span>
                                        </th>
                                        <th rowspan="3" class="whitespace-nowrap border border-gray-300 px-1 py-2">
                                            Family<br>Serial<br>No.<br><br><br><br><br>
                                            <span class="font-bold text-[11px]">(2)</span>
                                        </th>
                                        <th rowspan="3" class="whitespace-nowrap border border-gray-300 px-1 py-2">
                                            Name of Client <br>
                                            <span class="text-[10px]">(LN, FN, MI)</span><br><br><br><br><br><br>
                                            <span class="font-bold text-[11px]">(3)</span>
                                        </th>
                                        <!-- Sex -->
                                        <th colspan="2" class="border border-gray-300 px-1 py-0">Sex</th>
                                        <th rowspan="3" class="whitespace-nowrap border border-gray-300 px-1 py-2">
                                            Complete Address <br><br><br><br><br><br><br>
                                            <span class="font-bold text-[11px]">(4)</span>
                                        </th>
                                        <th rowspan="3" class="whitespace-nowrap border border-gray-300 px-1 py-2">
                                            Date<br>of<br>Birth <br><br>
                                            <span class="text-[10px]">(mm/dd/yy)</span><br><br><br>
                                            <span class="font-bold text-[11px]">(5)</span>
                                        </th>
                                        <!-- Age / Risk Group -->
                                        <th colspan="10" class="whitespace-nowrap border border-gray-300 px-1 py-2">
                                            Age / Risk Group <br>
                                            <span class="text-[10px]">(6) Write the age under the appropriate
                                                sub-column</span>
                                        </th>
                                        <th rowspan="3" class="whitespace-nowrap border border-gray-300 px-1 py-2">
                                            Indigenous People <br>
                                            <span class="font-bold text-[11px]">(7)<br>(Place a ✓)</span>
                                        </th>
                                        <th colspan="2" class="border border-gray-300 px-1 py-2">
                                            Oral Health Status<br>for Children<br>
                                            <span class="font-bold text-[10px]">12–59 months old</span><br>
                                            <span class="text-[9px]">(8) (Input the date)</span>
                                        </th>
                                        <th colspan="3" class="border border-gray-300 px-1 py-2">
                                            Clients &gt; 5 y/o with Decayed<br>Missing Filled Teeth (DMFT)<br>
                                            <span class="font-bold text-[11px]">(9) (Place a ✓)</span>
                                        </th>
                                    </tr>

                                    <!-- Row 2 -->
                                    <tr class="h-[20px] leading-[1.2]">
                                        <!-- Sex -->
                                        <th class="border border-gray-300 px-1 py-2 border-b-0">M</th>
                                        <th class="border border-gray-300 px-1 py-2 border-b-0">F</th>
                                        <!-- Age / Risk Group -->
                                        <th class="border border-gray-300 px-1 py-2 border-b-0 text-[10px]">0–11 mos.
                                        </th>
                                        <th class="border border-gray-300 px-1 py-2 border-b-0 text-[10px]">1–4 y/o
                                            (12–59 mos.)</th>
                                        <th class="border border-gray-300 px-1 py-2 border-b-0 text-[10px]">5–9 y/o</th>
                                        <th class="border border-gray-300 px-1 py-2 border-b-0 text-[10px]">10–14 y/o
                                        </th>
                                        <th class="border border-gray-300 px-1 py-2 border-b-0 text-[10px]">15–19 y/o
                                        </th>
                                        <th class="border border-gray-300 px-1 py-2 border-b-0 text-[10px]">20–59 y/o
                                        </th>
                                        <th class="border border-gray-300 px-1 py-2 border-b-0 text-[10px]">&gt;= 60 y/o
                                        </th>
                                        <th colspan="3" class="border border-gray-300 px-1 py-2">Pregnant</th>
                                        <!-- Oral Health -->
                                        <th class="border border-gray-300 px-1 py-2 border-b-0 text-[10px]">Orally
                                            Fit<br>Upon<br>Examination</th>
                                        <th class="border border-gray-300 px-1 py-2 border-b-0 text-[10px]">Orally
                                            Fit<br>After Rehab</th>
                                        <!-- DMFT -->
                                        <th class="border border-gray-300 px-1 py-2 border-b-0 text-[10px]">Decayed
                                            Tooth</th>
                                        <th class="border border-gray-300 px-1 py-2 border-b-0 text-[10px]">Missing
                                            Tooth</th>
                                        <th class="border border-gray-300 px-1 py-2 border-b-0 text-[10px]">Filled Tooth
                                        </th>
                                    </tr>

                                    <!-- Row 3 (Pregnant subcolumns) -->
                                    <tr class="h-[20px] leading-[1]">
                                        <th class="border border-gray-300 px-1 py-0 border-t-0"></th>
                                        <th class="border border-gray-300 px-1 py-0 border-t-0"></th>
                                        <th class="border border-gray-300 px-1 py-0 border-t-0"></th>
                                        <th class="border border-gray-300 px-1 py-0 border-t-0"></th>
                                        <th class="border border-gray-300 px-1 py-0 border-t-0"></th>
                                        <th class="border border-gray-300 px-1 py-0 border-t-0"></th>
                                        <th class="border border-gray-300 px-1 py-0 border-t-0"></th>
                                        <th class="border border-gray-300 px-1 py-0 border-t-0"></th>
                                        <th class="border border-gray-300 px-1 py-0 border-t-0"></th>
                                        <th class="border border-gray-300 px-1 py-0  text-[10px]">10–14 y/o</th>
                                        <th class="border border-gray-300 px-1 py-0  text-[10px]">15–19 y/o</th>
                                        <th class="border border-gray-300 px-1 py-0  text-[10px]">20–49 y/o</th>
                                        <th class="border border-gray-300 px-1 py-0  border-t-0"></th>
                                        <th class="border border-gray-300 px-1 py-0  border-t-0"></th>
                                        <th class="border border-gray-300 px-1 py-0  border-t-0"></th>
                                        <th class="border border-gray-300 px-1 py-0  border-t-0"></th>
                                        <th class="border border-gray-300 px-1 py-0  border-t-0"></th>
                                        <th colspan="5" class="border-none p-0"></th>
                                    </tr>
                                </thead>

                                <tbody>
                                    <?php if ($res->num_rows === 0): ?>
                                        <tr>
                                            <td colspan="26" class="text-center border border-gray-300 py-8 text-gray-500">
                                                <i class="fas fa-inbox text-2xl mb-2 block"></i>
                                                No patient records found for <?php echo $selectedYear; ?>.
                                                <?php if ($search): ?>
                                                    <br><span class="text-sm">Try a different search or year.</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php
                                    else:
                                        $i = 1;
                                        while ($row = $res->fetch_assoc()):
                                            // Determine age and months
                                            $age = null;
                                            if (!empty($row['age']) && is_numeric($row['age'])) {
                                                $age = (int)$row['age'];
                                            } else {
                                                $age = compute_age_from_dob($row['date_of_birth']);
                                            }
                                            $months_old = null;
                                            if (!empty($row['months_old']) && is_numeric($row['months_old'])) {
                                                $months_old = (int)$row['months_old'];
                                            } else {
                                                $months_old = compute_months_from_dob($row['date_of_birth']);
                                            }

                                            // Age group booleans
                                            $is_0_11 = ($age === 0 && $months_old !== null && $months_old <= 11) || ($age === 0 && $months_old === null && $age < 1);
                                            $is_1_4  = ($age !== null && $age >= 1 && $age <= 4);
                                            $is_5_9  = ($age !== null && $age >= 5 && $age <= 9);
                                            $is_10_14 = ($age !== null && $age >= 10 && $age <= 14);
                                            $is_15_19 = ($age !== null && $age >= 15 && $age <= 19);
                                            $is_20_59 = ($age !== null && $age >= 20 && $age <= 59);
                                            $is_ge_60 = ($age !== null && $age >= 60);

                                            // Pregnant subcolumns: only if pregnant true and age in the right bracket
                                            $preg_flag = is_truthy($row['pregnant']);
                                            $preg_10_14 = $preg_flag && $age !== null && $age >= 10 && $age <= 14;
                                            $preg_15_19 = $preg_flag && $age !== null && $age >= 15 && $age <= 19;
                                            $preg_20_49 = $preg_flag && $age !== null && $age >= 20 && $age <= 49;

                                            // Indigenous
                                            $indigenous = is_truthy($row['indigenous_people']);

                                            // Oral health date for children 12-59 months (1-4 y/o)
                                            $oral_health_date = '';
                                            if ($is_1_4 && !empty($row['oral_health_recorded_at'])) {
                                                $oral_health_date = date('m/d/Y', strtotime($row['oral_health_recorded_at']));
                                            }

                                            // Orally Fit Upon Examination
                                            $orally_fit_upon = $row['orally_fit_child']; // may be boolean or string
                                            // Orally Fit After Rehab: no column in provided schema -> leave blank for now
                                            $orally_fit_after = ''; // change if you have a column for this

                                            // DMFT checks (perm_... fields)
                                            $decayed_present = (!empty($row['perm_decayed_teeth_d']) && $row['perm_decayed_teeth_d'] > 0);
                                            $missing_present = (!empty($row['perm_missing_teeth_m']) && $row['perm_missing_teeth_m'] > 0);
                                            $filled_present  = (!empty($row['perm_filled_teeth_f']) && $row['perm_filled_teeth_f'] > 0);

                                        ?>
                                            <tr class="h-[20px] leading-[1]  text-center">
                                                <td class="border border-gray-300 px-1 py-2"><?php echo $i++; ?></td>

                                                <!-- Date of Consultation (using patient's created_at as proxy) -->
                                                <td class="border border-gray-300 px-1 py-2">
                                                    <?php echo !empty($row['created_at']) ? date('m/d/y', strtotime($row['created_at'])) : ''; ?>
                                                </td>

                                                <!-- Family Serial No -->
                                                <td class="border border-gray-300 px-1 py-2"><?php echo htmlspecialchars($row['patient_id']); ?></td>

                                                <!-- Name of Client -->
                                                <td class="border border-gray-300 px-1 py-2 text-left">
                                                    <?php
                                                    $full = trim($row['surname'] . ', ' . $row['firstname'] . ' ' . $row['middlename']);
                                                    echo htmlspecialchars($full);
                                                    ?>
                                                </td>

                                                <!-- Sex M -->
                                                <td class="border border-gray-300 px-1 py-2">
                                                    <?php
                                                    $sex = strtoupper(trim($row['sex'] ?? ''));
                                                    echo (in_array($sex, ['M', 'MALE'])) ? '✓' : '';
                                                    ?>
                                                </td>

                                                <!-- Sex F -->
                                                <td class="border border-gray-300 px-1 py-2">
                                                    <?php
                                                    echo (in_array($sex, ['F', 'FEMALE'])) ? '✓' : '';
                                                    ?>
                                                </td>


                                                <!-- Complete Address -->
                                                <td class="border border-gray-300 px-1 py-2 text-left"><?php echo htmlspecialchars($row['address']); ?></td>

                                                <!-- Date of Birth -->
                                                <td class="border border-gray-300 px-1 py-2">
                                                    <?php echo !empty($row['date_of_birth']) && $row['date_of_birth'] !== '0000-00-00' ? date('m/d/Y', strtotime($row['date_of_birth'])) : ''; ?>
                                                </td>

                                                <!-- Age buckets: print the age number inside the bucket cell where it belongs -->
                                                <td class="border border-gray-300 px-1 py-2"><?php echo $is_0_11 ? ($months_old !== null ? $months_old . ' mo' : '') : ''; ?></td>
                                                <td class="border border-gray-300 px-1 py-2"><?php echo $is_1_4 ? htmlspecialchars((string)$age) : ''; ?></td>
                                                <td class="border border-gray-300 px-1 py-2"><?php echo $is_5_9 ? htmlspecialchars((string)$age) : ''; ?></td>
                                                <td class="border border-gray-300 px-1 py-2"><?php echo $is_10_14 ? htmlspecialchars((string)$age) : ''; ?></td>
                                                <td class="border border-gray-300 px-1 py-2"><?php echo $is_15_19 ? htmlspecialchars((string)$age) : ''; ?></td>
                                                <td class="border border-gray-300 px-1 py-2"><?php echo $is_20_59 ? htmlspecialchars((string)$age) : ''; ?></td>
                                                <td class="border border-gray-300 px-1 py-2"><?php echo $is_ge_60 ? htmlspecialchars((string)$age) : ''; ?></td>

                                                <!-- Pregnant sub-columns -->
                                                <td class="border border-gray-300 px-1 py-2"><?php echo $preg_10_14 ? '✓' : ''; ?></td>
                                                <td class="border border-gray-300 px-1 py-2"><?php echo $preg_15_19 ? '✓' : ''; ?></td>
                                                <td class="border border-gray-300 px-1 py-2"><?php echo $preg_20_49 ? '✓' : ''; ?></td>

                                                <!-- Indigenous People -->
                                                <td class="border border-gray-300 px-1 py-2"><?php echo $indigenous ? '✓' : ''; ?></td>

                                                <!-- Oral Health Status for Children (12-59 mos) -->
                                                <td class="border border-gray-300 px-1 py-2"><?php echo htmlspecialchars($orally_fit_upon); ?></td>
                                                <td class="border border-gray-300 px-1 py-2"><?php echo htmlspecialchars($orally_fit_after); ?></td>

                                                <!-- DMFT -->
                                                <td class="border border-gray-300 px-1 py-2"><?php echo $decayed_present ? '✓' : ''; ?></td>
                                                <td class="border border-gray-300 px-1 py-2"><?php echo $missing_present ? '✓' : ''; ?></td>
                                                <td class="border border-gray-300 px-1 py-2"><?php echo $filled_present ? '✓' : ''; ?></td>
                                            </tr>
                                    <?php
                                        endwhile; // while rows
                                    endif; // rows exist
                                    ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination (already includes year in links) -->
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
                                    <a href="/dentalemr_system/html/reports/targetclientlist.php?uid=<?php echo $userId; ?>&year=<?php echo $selectedYear; ?>&<?= ($page > 1) ? 'page=' . ($page - 1) : '#' ?>"
                                        class="flex items-center justify-center h-full py-1.5 px-3 ml-0 text-gray-500 bg-white rounded-l-[5px] border border-gray-300 hover:bg-gray-100 hover:text-gray-700 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white <?= ($page <= 1) ? 'pointer-events-none opacity-50' : '' ?>">
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
                                for ($i = max(1, $page - $range); $i <= min($total_pages, $page + $range); $i++): ?>
                                    <li>
                                        <a href="/dentalemr_system/html/reports/targetclientlist.php?uid=<?php echo $userId; ?>&year=<?php echo $selectedYear; ?>&page=<?= $i ?>"
                                            class="flex items-center justify-center px-3 py-2 text-sm leading-tight border 
                                <?= ($i == $page)
                                        ? 'z-10 text-blue-600 bg-blue-50 border-blue-300 hover:bg-blue-100 hover:text-blue-700 dark:border-gray-700 dark:bg-gray-700 dark:text-white'
                                        : 'text-gray-500 bg-white border-gray-300 hover:bg-gray-100 hover:text-gray-700 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white' ?>">
                                            <?= $i ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>

                                <!-- Ellipsis and Last Page -->
                                <?php if ($page + $range < $total_pages): ?>
                                    <li>
                                        <span
                                            class="flex items-center justify-center px-3 py-2 text-sm leading-tight text-gray-500 bg-white border border-gray-300 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-400">...</span>
                                    </li>
                                    <li>
                                        <a href="/dentalemr_system/html/reports/targetclientlist.php?uid=<?php echo $userId; ?>&year=<?php echo $selectedYear; ?>&page=<?= $total_pages ?>"
                                            class="flex items-center justify-center px-3 py-2 text-sm leading-tight text-gray-500 bg-white border border-gray-300 hover:bg-gray-100 hover:text-gray-700 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white">
                                            <?= $total_pages ?>
                                        </a>
                                    </li>
                                <?php endif; ?>

                                <!-- Next Button -->
                                <li>
                                    <a href="/dentalemr_system/html/reports/targetclientlist.php?uid=<?php echo $userId; ?>&year=<?php echo $selectedYear; ?>&<?= ($page < $total_pages) ? 'page=' . ($page + 1) : '#' ?>"
                                        class="flex items-center justify-center h-full py-1.5 px-3 leading-tight text-gray-500 bg-white rounded-r-[5px] border border-gray-300 hover:bg-gray-100 hover:text-gray-700 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white <?= ($page >= $total_pages) ? 'pointer-events-none opacity-50' : '' ?>">
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
                </form>
            </section>

            <?php
            $res->free();
            $conn->close();
            ?>

            <div class="flex justify-between mt-4">
                <a href="?year=<?php echo ($selectedYear - 1); ?>&uid=<?php echo $userId; ?>"
                    class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 flex items-center gap-2 <?php echo ($selectedYear <= min($availableYears)) ? 'opacity-50 pointer-events-none' : ''; ?>">
                    <i class="fas fa-arrow-left"></i>
                    Previous Year (<?php echo $selectedYear - 1; ?>)
                </a>

                <button type="button" onclick="next()"
                    class="text-white justify-center cursor-pointer inline-flex items-center bg-blue-700 hover:bg-blue-800 focus:outline-none focus:ring-blue-300 font-medium rounded-lg text-sm p-1 w-18 dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800">
                    Next
                </button>

                <a href="?year=<?php echo ($selectedYear + 1); ?>&uid=<?php echo $userId; ?><?php
                                                                                            echo $search ? '&search=' . urlencode($search) : '';
                                                                                            echo $addressFilter ? '&address=' . urlencode($addressFilter) : '';
                                                                                            echo $isOfflineMode ? '&offline=true' : '';
                                                                                            ?>" class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 flex items-center gap-2 <?php echo ($selectedYear >= $currentYear) ? 'opacity-50 pointer-events-none' : ''; ?>">
                    Next Year (<?php echo $selectedYear + 1; ?>)
                    <i class="fas fa-arrow-right"></i>
                </a>
            </div>
        </main>
    </div>

    <!-- <script src="../node_modules/flowbite/dist/flowbite.min.js"></script> -->
    <script src="https://cdn.jsdelivr.net/npm/flowbite@3.1.2/dist/flowbite.min.js"></script>
    <script src="../js/tailwind.config.js"></script>
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
        function next() {
            // Get current parameters
            const currentYear = <?php echo $selectedYear; ?>;
            const currentPage = <?php echo $page; ?>;
            const currentSearch = '<?php echo addslashes($search); ?>';
            const currentAddress = '<?php echo addslashes($addressFilter); ?>';
            const userId = <?php echo $userId; ?>;
            const isOffline = <?php echo $isOfflineMode ? 'true' : 'false'; ?>;

            // Build the URL with all current parameters
            let url = `targetclientlist2.php?uid=${userId}&year=${currentYear}&page=${currentPage}`;

            if (currentSearch) {
                url += `&search=${encodeURIComponent(currentSearch)}`;
            }

            if (currentAddress) {
                url += `&address=${encodeURIComponent(currentAddress)}`;
            }

            if (isOffline) {
                url += '&offline=true';
            }

            // Also pass the starting row number for continuity
            url += `&startRow=${<?php echo $start - 1; ?>}`;

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
</body>

</html>