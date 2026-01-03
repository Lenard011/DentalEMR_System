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
  <title>Patient Information</title>
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
              <svg class="w-5 h-5 text-gray-500 dark:text-gray-400" fill="currentColor" viewBox="0 0 20 20"
                xmlns="http://www.w3.org/2000/svg">
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
            <a href="index.php?uid=<?php echo $userId; ?>"
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
                <a href="./treatmentrecords/treatmentrecords.php?uid=<?php echo $userId; ?>"
                  class="flex items-center p-2 pl-11 w-full text-base font-medium text-gray-900 rounded-lg transition duration-75 group hover:bg-gray-100 dark:text-white dark:hover:bg-gray-700">Treatment
                  Records</a>
              </li>
              <li>
                <a href="./addpatienttreatment/patienttreatment.php?uid=<?php echo $userId; ?>"
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
              <svg
                class="flex-shrink-0 w-6 h-6 text-gray-500 transition duration-75 dark:text-gray-400 group-hover:text-gray-900 dark:group-hover:text-white"
                aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none"
                viewBox="0 0 24 24">
                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M11 9h6m-6 3h6m-6 3h6M6.996 9h.01m-.01 3h.01m-.01 3h.01M4 5h16a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1Z" />
              </svg>
              <span class="ml-3">MHO - OHP</span>
            </a>
          </li>
          <li>
            <a href="./reports/oralhygienefindings.php?uid=<?php echo $userId; ?>"
              class="flex items-center p-2 text-base font-medium text-gray-900 rounded-lg dark:text-white hover:bg-gray-100 dark:hover:bg-gray-700 group">
              <svg
                class="flex-shrink-0 w-6 h-6 text-gray-500 transition duration-75 dark:text-gray-400 group-hover:text-gray-900 dark:group-hover:text-white"
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
              <svg
                class="flex-shrink-0 w-6 h-6 text-gray-500 transition duration-75 dark:text-gray-400 group-hover:text-gray-900 dark:group-hover:text-white"
                aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor"
                viewBox="0 0 24 24">
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

    <main class="relative p-1.5 md:ml-64 h-auto pt-5">
      <div class="relative flex items-center w-full mt-13 p-2">
        <!-- Back Btn -->
        <button type="button" onclick="back()" class="cursor-pointer absolute left-2">
          <svg class="w-[35px] h-[35px] text-blue-800 dark:blue-white" aria-hidden="true"
            xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
              d="M5 12h14M5 12l4-4m-4 4 4 4" />
          </svg>
        </button>
        <p class="mx-auto text-xl text-center font-semibold text-gray-900 dark:text-white">
          Patient Information
        </p>
      </div>

      <!-- Patient Information Section -->
      <section class="relative bg-white dark:bg-gray-900 p-2 sm:p-2 rounded-lg mb-2">
        <!-- Patient Name Display -->
        <p id="patientName" class="italic text-lg font-medium text-gray-900 dark:text-white mb-2">Loading ...</p>

        <!-- Patient Info Card -->
        <div class="relative mx-auto mb-5 max-w-screen-xl px-1.5 py-2 lg:px-1.5 bg-white rounded-lg shadow dark:border shadow-stone-300 drop-shadow-sm dark:bg-gray-800 dark:border-gray-950">
          <div class="items-center justify-between flex flex-row mb-3">
            <p class="text-base font-normal text-gray-950 dark:text-white ">Patient Details</p>
            <button id="editBtn" type="button"
              class="text-white cursor-pointer flex flex-row items-center justify-center gap-1 bg-blue-700 hover:bg-blue-800 font-medium rounded-sm text-xs px-1 lg:py-1 mr-2 dark:bg-primary-600 dark:hover:bg-primary-700 focus:outline-none dark:focus:ring-primary-800">
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

          <!-- Patient Information Grid -->
          <div class="relative flex flex-col justify-center items-center px-5 gap-5 mb-3">
            <!-- First Row -->
            <div class="flex items-center justify-between p-2 max-w-5xl w-full">
              <!-- Name -->
              <div class="grid items-center justify-center grid-flow-col gap-1">
                <div class="flex items-center w-12">
                  <div class="rounded-full p-2.5 bg-gray-100 dark:bg-blue-300">
                    <svg class="w-6 h-6 text-blue-800 dark:text-white" aria-hidden="true"
                      xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                      <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1"
                        d="M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Zm0 0a8.949 8.949 0 0 0 4.951-1.488A3.987 3.987 0 0 0 13 16h-2a3.987 3.987 0 0 0-3.951 3.512A8.948 8.948 0 0 0 12 21Zm3-11a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                    </svg>
                  </div>
                </div>
                <div>
                  <p id="patientName2" class="text-sm font-normal text-gray-950 dark:text-white"
                    style="font-size:14.6px;">Loading ...</p>
                  <p class="text-xs font-normal text-gray-950 dark:text-white" style="font-size:13px;">
                    Name</p>
                </div>
              </div>

              <!-- Gender -->
              <div class="relative grid items-center justify-center grid-flow-col gap-1">
                <div class="flex items-center w-12">
                  <div class="rounded-full p-2.5 bg-gray-100 dark:bg-blue-300">
                    <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="currentColor"
                      class="w-6 h-6 text-blue-800 dark:text-white" viewBox="0 0 20 16">
                      <path fill-rule="evenodd"
                        d="M11.5 1a.5.5 0 0 1 0-1h4a.5.5 0 0 1 .5.5v4a.5.5 0 0 1-1 0V1.707l-3.45 3.45A4 4 0 0 1 8.5 10.97V13H10a.5.5 0 0 1 0 1H8.5v1.5a.5.5 0 0 1-1 0V14H6a.5.5 0 0 1 0-1h1.5v-2.03a4 4 0 1 1 3.471-6.648L14.293 1zm-.997 4.346a3 3 0 1 0-5.006 3.309 3 3 0 0 0 5.006-3.31z" />
                    </svg>
                  </div>
                </div>
                <div>
                  <p id="patientSex" class="text-sm font-normal text-gray-950 dark:text-white"
                    style="font-size:14.6px;">Loading ...</p>
                  <p class="text-xs font-normal text-gray-950 dark:text-white" style="font-size:13px;">
                    Gender</p>
                </div>
              </div>

              <!-- Age -->
              <div class="relative grid items-center justify-center grid-flow-col gap-1">
                <div class="flex items-center w-12">
                  <div class="rounded-full p-2.5 bg-gray-100 dark:bg-blue-300">
                    <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="currentColor"
                      class="w-6 h-6 text-blue-800 dark:text-white" viewBox="0 0 18 15">
                      <path fill-rule="evenodd"
                        d="M14 2.5a.5.5 0 0 0-.5-.5h-6a.5.5 0 0 0 0 1h4.793L2.146 13.146a.5.5 0 0 0 .708.708L13 3.707V8.5a.5.5 0 0 0 1 0z" />
                    </svg>
                  </div>
                </div>
                <div>
                  <p id="patientAge" class="text-sm font-normal text-gray-950 dark:text-white"
                    style="font-size:14.6px;">Loading ...</p>
                  <p class="text-xs font-normal text-gray-950 dark:text-white" style="font-size:13px;">
                    Age</p>
                </div>
              </div>

              <!-- Date of Birth -->
              <div class="relative grid items-center justify-center grid-flow-col gap-1">
                <div class="flex items-center w-12">
                  <div class="rounded-full p-2.5 bg-gray-100 dark:bg-blue-300">
                    <svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" fill="currentColor"
                      class="w-6 h-6 text-blue-800 dark:text-white" viewBox="-1 -3 16 22">
                      <path
                        d="M10.854 7.146a.5.5 0 0 1 0 .708l-3 3a.5.5 0 0 1-.708 0l-1.5-1.5a.5.5 0 1 1 .708-.708L7.5 9.793l2.646-2.647a.5.5 0 0 1 .708 0" />
                      <path
                        d="M3.5 0a.5.5 0 0 1 .5.5V1h8V.5a.5.5 0 0 1 1 0V1h1a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V3a2 2 0 0 1 2-2h1V.5a.5.5 0 0 1 .5-.5M1 4v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V4z" />
                    </svg>
                  </div>
                </div>
                <div>
                  <p id="patientDob" class="text-sm font-normal text-gray-950 dark:text-white"
                    style="font-size:14.6px;">Loading ...</p>
                  <p class="text-xs font-normal text-gray-950 dark:text-white" style="font-size:13px;">
                    Date of Birth</p>
                </div>
              </div>

              <!-- Occupation -->
              <div class="relative grid items-center justify-center grid-flow-col gap-1">
                <div class="flex items-center w-12">
                  <div class="rounded-full p-2.5 bg-gray-100 dark:bg-blue-300">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor"
                      class="w-6 h-6 text-blue-800 dark:text-white" viewBox="-1 -3 16 22">
                      <path
                        d="M4 16s-1 0-1-1 1-4 5-4 5 3 5 4-1 1-1 1zm4-5.95a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5" />
                      <path
                        d="M2 1a2 2 0 0 0-2 2v9.5A1.5 1.5 0 0 0 1.5 14h.653a5.4 5.4 0 0 1 1.066-2H1V3a1 1 0 0 1 1-1h12a1 1 0 0 1 1 1v9h-2.219c.554.654.89 1.373 1.066 2h.653a1.5 1.5 0 0 0 1.5-1.5V3a2 2 0 0 0-2-2z" />
                    </svg>
                  </div>
                </div>
                <div>
                  <p id="patientOccupation" class="text-sm font-normal text-gray-950 dark:text-white"
                    style="font-size:14.6px;">Loading ...</p>
                  <p class="text-xs font-normal text-gray-950 dark:text-white" style="font-size:13px;">
                    Occupation</p>
                </div>
              </div>
            </div>

            <!-- Second Row -->
            <div class="relative flex items-center justify-between p-2 max-w-5xl w-full">
              <!-- Place of Birth -->
              <div class="grid items-center justify-center grid-flow-col gap-1">
                <div class="flex items-center w-12">
                  <div class="rounded-full p-2.5 bg-gray-100 dark:bg-blue-300">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor"
                      class="w-6 h-6 text-blue-800 dark:text-white" viewBox="-2 0 20 16">
                      <path
                        d="M12.166 8.94c-.524 1.062-1.234 2.12-1.96 3.07A32 32 0 0 1 8 14.58a32 32 0 0 1-2.206-2.57c-.726-.95-1.436-2.008-1.96-3.07C3.304 7.867 3 6.862 3 6a5 5 0 0 1 10 0c0 .862-.305 1.867-.834 2.94M8 16s6-5.686 6-10A6 6 0 0 0 2 6c0 4.314 6 10 6 10" />
                      <path
                        d="M8 8a2 2 0 1 1 0-4 2 2 0 0 1 0 4m0 1a3 3 0 1 0 0-6 3 3 0 0 0 0 6" />
                    </svg>
                  </div>
                </div>
                <div>
                  <p id="patientBirthPlace" class="text-sm font-normal text-gray-950 dark:text-white"
                    style="font-size:14.6px;">Loading ...</p>
                  <p class="text-xs font-normal text-gray-950 dark:text-white" style="font-size:13px;">
                    Place of Birth</p>
                </div>
              </div>

              <!-- Address -->
              <div class="relative grid items-center justify-center grid-flow-col gap-1">
                <div class="flex items-center w-12">
                  <div class="rounded-full p-2.5 bg-gray-100 dark:bg-blue-300">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor"
                      class="w-6 h-6 text-blue-800 dark:text-white" viewBox="-2 0 20 16">
                      <path fill-rule="evenodd"
                        d="M4 4a4 4 0 1 1 4.5 3.969V13.5a.5.5 0 0 1-1 0V7.97A4 4 0 0 1 4 3.999zm2.493 8.574a.5.5 0 0 1-.411.575c-.712.118-1.28.295-1.655.493a1.3 1.3 0 0 0-.37.265.3.3 0 0 0-.057.09V14l.002.008.016.033a.6.6 0 0 0 .145.15c.165.13.435.27.813.395.751.25 1.82.414 3.024.414s2.273-.163 3.024-.414c.378-.126.648-.265.813-.395a.6.6 0 0 0 .146-.15l.015-.033L12 14v-.004a.3.3 0 0 0-.057-.09 1.3 1.3 0 0 0-.37-.264c-.376-.198-.943-.375-1.655-.493a.5.5 0 1 1 .164-.986c.77.127 1.452.328 1.957.594C12.5 13 13 13.4 13 14c0 .426-.26.752-.544.977-.29.228-.68.413-1.116.558-.878.293-2.059.465-3.34.465s-2.462-.172-3.34-.465c-.436-.145-.826-.33-1.116-.558C3.26 14.752 3 14.426 3 14c0-.599.5-1 .961-1.243.505-.266 1.187-.467 1.957-.594a.5.5 0 0 1 .575.411" />
                    </svg>
                  </div>
                </div>
                <div>
                  <p id="patientAddress" class="text-sm font-normal text-gray-950 dark:text-white"
                    style="font-size:14.6px;">Loading ...</p>
                  <p class="text-xs font-normal text-gray-950 dark:text-white" style="font-size:13px;">
                    Address</p>
                </div>
              </div>

              <!-- Parent/Guardian -->
              <div class="relative grid items-center justify-center grid-flow-col gap-1">
                <div class="flex items-center w-12">
                  <div class="rounded-full p-2.5 bg-gray-100 dark:bg-blue-300">
                    <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="currentColor"
                      class="w-6 h-6 text-blue-800 dark:text-white" viewBox="-2.5 0 20 16">
                      <path
                        d="M15 14s1 0 1-1-1-4-5-4-5 3-5 4 1 1 1 1zm-7.978-1L7 12.996c.001-.264.167-1.03.76-1.72C8.312 10.629 9.282 10 11 10c1.717 0 2.687.63 3.24 1.276.593.69.758 1.457.76 1.72l-.008.002-.014.002zM11 7a2 2 0 1 0 0-4 2 2 0 0 0 0 4m3-2a3 3 0 1 1-6 0 3 3 0 0 1 6 0M6.936 9.28a6 6 0 0 0-1.23-.247A7 7 0 0 0 5 9c-4 0-5 3-5 4q0 1 1 1h4.216A2.24 2.24 0 0 1 5 13c0-1.01.377-2.042 1.09-2.904.243-.294.526-.569.846-.816M4.92 10A5.5 5.5 0 0 0 4 13H1c0-.26.164-1.03.76-1.724.545-.636 1.492-1.256 3.16-1.275ZM1.5 5.5a3 3 0 1 1 6 0 3 3 0 0 1-6 0m3-2a2 2 0 1 0 0 4 2 2 0 0 0 0-4" />
                    </svg>
                  </div>
                </div>
                <div>
                  <p id="patientGuardian" class="relative text-sm font-normal text-gray-950 dark:text-white"
                    style="font-size:14.6px;">Loading ...</p>
                  <p class="relative text-xs font-normal text-gray-950 dark:text-white" style="font-size:13px;">
                    Parent/Guardian</p>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Others Info (Membersip)-->
        <div
          class="mx-auto mb-5 max-w-screen-xl px-1.5 py-2 lg:px-1.5 bg-white rounded-lg shadow dark:border shadow-stone-300 drop-shadow-sm dark:bg-gray-800 dark:border-gray-950">
          <div class="items-center justify-between flex flex-row mb-3">
            <p class="text-base font-normal text-gray-950 dark:text-white">Membership</p>
            <button id="addBtn" type="button"
              class="text-white cursor-pointer flex flex-row items-center justify-center gap-1 bg-blue-700 hover:bg-blue-800 font-medium rounded-sm text-xs px-1 lg:py-1 mr-2 dark:bg-primary-600 dark:hover:bg-primary-700 focus:outline-none dark:focus:ring-primary-800">
              <svg class="h-3.5 w-3.5" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"
                aria-hidden="true">
                <path clip-rule="evenodd" fill-rule="evenodd"
                  d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" />
              </svg>
              Add
            </button>
          </div>
          <ul id="membershipList" class="max-w-full space-y-1 text-gray-900 list-disc list-inside dark:text-gray-400"
            style="font-size:14.6px;">
          </ul>
        </div>
        <!-- Vital Signs -->
        <div
          class="mx-auto mb-5 max-w-screen-xl px-1.5 py-2 pb-4 lg:px-1.5 bg-white rounded-lg shadow dark:border shadow-stone-300 drop-shadow-sm dark:bg-gray-800 dark:border-gray-950">
          <div class="items-center justify-between flex flex-row mb-3">
            <p class="text-base font-normal text-gray-950 dark:text-white ">Vital Signs</p>
            <button type="button" id="addVitalbtn"
              class="text-white cursor-pointer flex flex-row items-center justify-center gap-1 bg-blue-700 hover:bg-blue-800 font-medium rounded-sm text-xs px-1 lg:py-1 mr-2 dark:bg-primary-600 dark:hover:bg-primary-700 focus:outline-none dark:focus:ring-primary-800">
              <svg class="h-3.5 w-3.5" fill="currentColor" viewbox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"
                aria-hidden="true">
                <path clip-rule="evenodd" fill-rule="evenodd"
                  d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" />
              </svg>
              Add
            </button>
          </div>
          <div class="grid grid-cols-2 gap-x-2 gap-y-4 p-1">
            <!-- Blood Pressure -->
            <div
              class="flex-row items-center justify-center p-5 rounded-lg shadow dar:border shadow-stone-300 dark:bg-gray-800 dark:border-gray-950">
              <div class="w-full flex items-center justify-center">
                <div class="flex rounded-full items-center justify-center p-1.5 bg-gray-100 dark:bg-blue-300 w-10 h-10">
                  <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor"
                    class="bi bi-capsule w-6 h-6 p-0.5 text-blue-800 dark:text-white" viewBox="0 0 16 16">
                    <path
                      d="M1.828 8.9 8.9 1.827a4 4 0 1 1 5.657 5.657l-7.07 7.071A4 4 0 1 1 1.827 8.9Zm9.128.771 2.893-2.893a3 3 0 1 0-4.243-4.242L6.713 5.429z" />
                  </svg>
                </div>
              </div>
              <div class="w-full flex items-center justify-center mt-1">
                <p class="text-sm font-normal text-gray-950 dark:text-white">Blood Pressure</p>
              </div>
              <div class="relative overflow-x-auto mt-5">
                <table
                  class="w-full block text-sm text-left rtl:text-right text-gray-900 dark:text-gray-400 border dark:bg-gray-800 dark:border-gray-700 border-gray-200 rounded-lg">
                  <tbody id="bpTableBody" class="block"></tbody>
                </table>
              </div>
            </div>

            <!-- Temperature -->
            <div
              class="flex-row items-center justify-center p-5 rounded-lg shadow dar:border shadow-stone-300 dark:bg-gray-800 dark:border-gray-950">
              <div class="w-full flex items-center justify-center">
                <div class="rounded-full flex items-center justify-center p-1.5 bg-gray-100 dark:bg-blue-300 w-10 h-10">
                  <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor"
                    class="bi bi-capsule w-6 h-6 p-0.5 text-blue-800 dark:text-white" viewBox="0 0 16 16">
                    <path d="M9.5 12.5a1.5 1.5 0 1 1-2-1.415V6.5a.5.5 0 0 1 1 0v4.585a1.5 1.5 0 0 1 1 1.415" />
                    <path
                      d="M5.5 2.5a2.5 2.5 0 0 1 5 0v7.55a3.5 3.5 0 1 1-5 0zM8 1a1.5 1.5 0 0 0-1.5 1.5v7.987l-.167.15a2.5 2.5 0 1 0 3.333 0l-.166-.15V2.5A1.5 1.5 0 0 0 8 1" />
                  </svg>
                </div>
              </div>
              <div class="w-full flex items-center justify-center">
                <p class="text-sm font-normal text-gray-950 dark:text-white">Temperature</p>
              </div>
              <div class="relative overflow-x-auto mt-5">
                <table
                  class="w-full block text-sm text-left rtl:text-right text-gray-900 dark:text-gray-400 border dark:bg-gray-800 dark:border-gray-700 border-gray-200 rounded-lg">
                  <tbody id="tempTableBody" class="block"></tbody>
                </table>
              </div>
            </div>

            <!-- Pulse Rate -->
            <div
              class="flex-row items-center justify-center p-5 rounded-lg shadow dar:border shadow-stone-300 dark:bg-gray-800 dark:border-gray-950">
              <div class="w-full flex items-center justify-center">
                <div class="rounded-full flex items-center justify-center p-1.5 bg-gray-100 dark:bg-blue-300 w-10 h-10">
                  <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor"
                    class="bi bi-capsule w-6 h-6 p-0.5 text-blue-800 dark:text-white" viewBox="0 0 16 16">
                    <path
                      d="m8 2.748-.717-.737C5.6.281 2.514.878 1.4 3.053.918 3.995.78 5.323 1.508 7H.43c-2.128-5.697 4.165-8.83 7.394-5.857q.09.083.176.171a3 3 0 0 1 .176-.17c3.23-2.974 9.522.159 7.394 5.856h-1.078c.728-1.677.59-3.005.108-3.947C13.486.878 10.4.28 8.717 2.01zM2.212 10h1.315C4.593 11.183 6.05 12.458 8 13.795c1.949-1.337 3.407-2.612 4.473-3.795h1.315c-1.265 1.566-3.14 3.25-5.788 5-2.648-1.75-4.523-3.434-5.788-5" />
                    <path
                      d="M10.464 3.314a.5.5 0 0 0-.945.049L7.921 8.956 6.464 5.314a.5.5 0 0 0-.88-.091L3.732 8H.5a.5.5 0 0 0 0 1H4a.5.5 0 0 0 .416-.223l1.473-2.209 1.647 4.118a.5.5 0 0 0 .945-.049l1.598-5.593 1.457 3.642A.5.5 0 0 0 12 9h3.5a.5.5 0 0 0 0-1h-3.162z" />
                  </svg>
                </div>
              </div>
              <div class="w-full flex items-center justify-center">
                <p class="text-sm font-normal text-gray-950 dark:text-white">Pulse Rate</p>
              </div>
              <div class="relative overflow-x-auto mt-5">
                <table
                  class="w-full block text-sm text-left rtl:text-right text-gray-900 dark:text-gray-400 border dark:bg-gray-800 dark:border-gray-700 border-gray-200 rounded-lg">
                  <tbody id="pulseTableBody" class="block"></tbody>
                </table>
              </div>
            </div>

            <!-- Weight -->
            <div
              class="flex-row items-center justify-center p-5 rounded-lg shadow dar:border shadow-stone-300 dark:bg-gray-800 dark:border-gray-950">
              <div class="w-full flex items-center justify-center">
                <div class="rounded-full flex items-center justify-center p-2.5 bg-gray-100 dark:bg-blue-300 w-10 h-10">
                  <img src="../img/9767079.png" alt="">
                </div>
              </div>
              <div class="w-full flex items-center justify-center">
                <p class="text-sm font-normal text-gray-950 dark:text-white">Weight</p>
              </div>
              <div class="relative overflow-x-auto mt-5">
                <table
                  class="w-full block text-sm text-left rtl:text-right text-gray-900 border border-gray-200 rounded-lg">
                  <tbody id="weightTableBody" class="block"></tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
        <!-- medical -->
        <div
          class="mx-auto mb-5 max-w-screen-xl px-1.5 py-2 lg:px-1.5 bg-white rounded-lg shadow dark:border shadow-stone-300 drop-shadow-sm dark:bg-gray-800 dark:border-gray-950">
          <div class="items-center justify-between flex flex-row mb-3">
            <p class="text-base font-normal text-gray-950 dark:text-white ">Medical History</p>
            <button type="button" id="addMedicalHistoryBtn"
              class="text-white cursor-pointer flex flex-row items-center justify-center gap-1 bg-blue-700 hover:bg-blue-800 font-medium rounded-sm text-xs px-1 lg:py-1 mr-2 dark:bg-primary-600 dark:hover:bg-primary-700 focus:outline-none dark:focus:ring-primary-800">
              <svg class="h-3.5 w-3.5" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"
                aria-hidden="true">
                <path clip-rule="evenodd" fill-rule="evenodd"
                  d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" />
              </svg>
              Add
            </button>
          </div>
          <ul id="medicalHistoryList"
            class="max-w-full space-y-1 text-gray-900 list-disc list-inside dark:text-gray-400"
            style="font-size:14.6px;">
          </ul>
        </div>
        <!-- Dietary -->
        <div
          class="mx-auto mb-5 max-w-screen-xl px-1.5 py-2 lg:px-1.5 bg-white rounded-lg shadow dark:border shadow-stone-300 drop-shadow-sm dark:bg-gray-800 dark:border-gray-950">
          <div class="items-center justify-between flex flex-row mb-3">
            <p class="text-base font-normal text-gray-950 dark:text-white">Dietary Habits / Social History</p>
            <button type="button" id="addDietaryHistoryBtn"
              class="text-white cursor-pointer flex flex-row items-center justify-center gap-1 bg-blue-700 hover:bg-blue-800 font-medium rounded-sm text-xs px-1 lg:py-1 mr-2 dark:bg-primary-600 dark:hover:bg-primary-700 focus:outline-none dark:focus:ring-primary-800">
              <svg class="h-3.5 w-3.5" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"
                aria-hidden="true">
                <path clip-rule="evenodd" fill-rule="evenodd"
                  d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" />
              </svg>
              Add
            </button>
          </div>
          <ul id="dietaryHistoryList"
            class="max-w-full space-y-1 text-gray-900 list-disc list-inside dark:text-gray-400"
            style="font-size:14.6px;">
          </ul>
        </div>

        <!-- Edit Patient Modal -->
        <div id="editPatientModal" tabindex="-1" aria-hidden="true"
          class="fixed inset-0 hidden justify-center items-center z-50 bg-gray-600/50">
          <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg w-full max-w-lg p-6 relative">
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

            <form id="editPatientForm" action="../php/register_patient/update_patient.php?uid=<?php echo $loggedUser['id']; ?>" method="POST">
              <input type="hidden" id="editPatientId" name="patient_id">

              <div class="grid grid-cols-2 gap-4">
                <div>
                  <label for="editFirstname" class="block mb-2 text-xs font-medium text-gray-900 dark:text-white">First
                    Name</label>
                  <input type="text" id="editFirstname" name="firstname"
                    class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-sm block w-full p-1">
                </div>
                <div>
                  <label for="editSurname"
                    class="block mb-2 text-xs font-medium text-gray-900 dark:text-white">Surname</label>
                  <input type="text" id="editSurname" name="surname"
                    class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-sm block w-full p-1">
                </div>
                <div>
                  <label for="editMiddlename"
                    class="block mb-2 text-xs font-medium text-gray-900 dark:text-white">Middle Name</label>
                  <input type="text" id="editMiddlename" name="middlename"
                    class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-sm block w-full p-1">
                </div>
                <div>
                  <label for="editDob" class="block mb-2 text-xs font-medium text-gray-900 dark:text-white">Date of
                    Birth</label>
                  <input type="date" id="editDob" name="date_of_birth"
                    class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-sm block w-full p-1">
                </div>
              </div>

              <div id="form-container" class="grid sm:grid-cols-2 mt-4 gap-4 w-full ">
                <!-- Age -->
                <div class="flex flex-row items-center justify-between gap-2">
                  <div class="age-wrapper w-full">
                    <label for="age" class="block mb-2 text-xs font-medium text-gray-900 dark:text-white">Age</label>
                    <input type="number" id="age" name="age" min="0"
                      class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-sm w-full p-1">
                  </div>
                  <div id="monthContainer" class="age-wrapper w-full">
                    <label for="agemonth"
                      class="block mb-2 text-xs font-medium text-gray-900 dark:text-white">Month</label>
                    <input type="number" id="agemonth" name="agemonth" min="0" max="59"
                      class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-sm w-full p-1">
                  </div>
                </div>

                <!-- Sex -->
                <div class="flex flex-row items-center justify-center gap-5 w-full">
                  <div class="w-full">
                    <label for="editSex"
                      class="block mb-2 text-xs font-medium text-gray-900 dark:text-white">Sex</label>
                    <select id="editSex" name="sex" required
                      class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-sm block w-full p-1 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                      <option value="" disabled selected>-- Select Sex --</option>
                      <option value="Male">Male</option>
                      <option value="Female">Female</option>
                    </select>
                  </div>

                  <!-- Pregnant (hidden by default) -->
                  <div id="pregnant-section" class="hidden">
                    <label class="block mb-2 text-xs font-medium text-gray-900 dark:text-white">Pregnant</label>
                    <div class="flex flex-row gap-2  items-center ">
                      <div class="flex items-center">
                        <input id="pregnant-yes" type="radio" value="yes" name="pregnant" disabled
                          class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300">
                        <label for="pregnant-yes"
                          class="ms-2 text-sm font-medium text-gray-900 dark:text-gray-300">Yes</label>
                      </div>
                      <div class="flex items-center">
                        <input id="pregnant-no" type="radio" value="no" name="pregnant" checked disabled
                          class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300">
                        <label for="pregnant-no"
                          class="ms-2 text-sm font-medium text-gray-900 dark:text-gray-300">No</label>
                      </div>
                    </div>
                  </div>
                </div>

              </div>

              <div class="mt-4">
                <label for="editPob" class="block mb-2 text-xs font-medium text-gray-900 dark:text-white">Place of
                  Birth</label>
                <input type="text" id="editPob" name="place_of_birth"
                  class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-sm block w-full p-1">
              </div>

              <div class="mt-4">
                <label for="editAddress"
                  class="block mb-2 text-xs font-medium text-gray-900 dark:text-white">Address</label>
                <select id="editAddress" name="address"
                  class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-sm block w-full p-1 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
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

              <div class="mt-4 grid grid-cols-2 gap-4">
                <div>
                  <label for="editOccupation"
                    class="block mb-2 text-xs font-medium text-gray-900 dark:text-white">Occupation</label>
                  <input type="text" id="editOccupation" name="occupation"
                    class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-sm block w-full p-1">
                </div>
                <div>
                  <label for="editGuardian"
                    class="block mb-2 text-xs font-medium text-gray-900 dark:text-white">Guardian</label>
                  <input type="text" id="editGuardian" name="guardian"
                    class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-sm block w-full p-1">
                </div>
              </div>

              <div class="mt-6 flex justify-end">
                <button type="submit" name="update_patient"
                  class="text-white cursor-pointer bg-blue-700 hover:bg-blue-800 font-medium rounded-sm text-sm px-3 py-2">
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
                <input type="checkbox" value="1" name="nhts_pr" id="nhts_pr" data-field="nhts_pr"
                  class="w-4 h-4 text-blue-600 border-gray-300 rounded-sm">
                <label class="ms-2 text-sm">NHTS-PR</label>
              </div>

              <!-- 4Ps -->
              <div class="flex items-center mb-1">
                <input type="checkbox" value="1" name="four_ps" id="four_ps" data-field="four_ps"
                  class="w-4 h-4 text-blue-600 border-gray-300 rounded-sm">
                <label class="ms-2 text-sm">Pantawid Pamilyang Pilipino Program (4Ps)</label>
              </div>

              <!-- IP -->
              <div class="flex items-center mb-1">
                <input type="checkbox" value="1" name="indigenous_people" id="indigenous_people" data-field="indigenous_people"
                  class="w-4 h-4 text-blue-600 border-gray-300 rounded-sm">
                <label class="ms-2 text-sm">Indigenous People (IP)</label>
              </div>

              <!-- PWD -->
              <div class="flex items-center mb-1">
                <input type="checkbox" value="1" name="pwd" id="pwd" data-field="pwd"
                  class="w-4 h-4 text-blue-600 border-gray-300 rounded-sm">
                <label class="ms-2 text-sm">Person With Disabilities (PWDs)</label>
              </div>

              <!-- PhilHealth -->
              <div class="flex items-center mb-1">
                <input type="checkbox" value="1" name="philhealth_flag" id="philhealth_flag" data-field="philhealth_flag"
                  class="w-4 h-4 text-blue-600 border-gray-300 rounded-sm">
                <div class="grid grid-cols-2 items-center gap-4">
                  <label class="ms-2 text-sm">PhilHealth (Indicate Number)</label>
                  <input type="text" id="philhealth_number" name="philhealth_number" disabled
                    class="block py-1 px-0 w-full text-sm border-b-2 border-gray-300 focus:outline-none focus:border-blue-600" />
                </div>
              </div>

              <!-- SSS -->
              <div class="flex items-center mb-1">
                <input type="checkbox" value="1" name="sss_flag" id="sss_flag" data-field="sss_flag"
                  class="w-4 h-4 text-blue-600 border-gray-300 rounded-sm">
                <div class="grid grid-cols-2 items-center gap-4">
                  <label class="ms-2 text-sm">SSS (Indicate Number)</label>
                  <input type="text" id="sss_number" name="sss_number" disabled
                    class="block py-1 px-0 w-full text-sm border-b-2 border-gray-300 focus:outline-none focus:border-blue-600" />
                </div>
              </div>

              <!-- GSIS -->
              <div class="flex items-center mb-1">
                <input type="checkbox" value="1" name="gsis_flag" id="gsis_flag" data-field="gsis_flag"
                  class="w-4 h-4 text-blue-600 border-gray-300 rounded-sm">
                <div class="grid grid-cols-2 items-center gap-4">
                  <label class="ms-2 text-sm">GSIS (Indicate Number)</label>
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
              <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Add / Edit Medical History</h2>
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
                  <label for="default-checkbox" class="ms-2 text-sm font-medium text-gray-900 dark:text-gray-300">Blood
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
                  <label for="default-checkbox" class="ms-2 w-55 text-sm font-medium text-gray-900 dark:text-gray-300">
                    Medical </label>
                  <div class="ms-4 flex flex-row items-center w-full gap-2">
                    <div class="flex flex-row items-center  gap-1 ">
                      <label class="w-27 text-sm font-medium text-gray-900 dark:text-gray-300 ">
                        Last Admission:</label>
                      <input type="date" id="last_admission_date" name="last_admission_date" disabled
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
                  <label for="default-checkbox" class="ms-2 w-55 text-sm font-medium text-gray-900 dark:text-gray-300">
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
                    <input type="text" id="blood_transfusion_date" name="blood_transfusion_date" disabled
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
              <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Add / Edit Dietary Habits / Social History
              </h2>
              <button type="button" id="cancelDietaryBtn"
                class="relative cursor-pointer text-gray-500 hover:text-gray-800 dark:hover:text-white">
                ✕
              </button>
            </div>
            <form id="dietaryForm" class="space-y-4">
              <div>
                <div class="flex items-center mb-1">
                  <input type="checkbox" name="sugar_flag" value="1" onchange="toggleInput(this, 'sugar_details')"
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
                  <input type="checkbox" name="alcohol_flag" value="1" onchange="toggleInput(this, 'alcohol_details')"
                    class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded-sm focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600">
                  <div class="grid grid-cols-2 gap-6.5">
                    <label for="default-checkbox" class="ms-2 text-sm font-medium text-gray-900 dark:text-gray-300">Use
                      of
                      Alcohol (Amount, Frequency & Duration)</label>
                    <input type="text" id="alcohol_details" name="alcohol_details" disabled
                      class="block py-1 px-0 h-4.5 w-full text-sm text-gray-900 bg-transparent border-0 border-b-2 border-gray-300 appearance-none dark:text-white dark:border-gray-600 dark:focus:border-blue-500 focus:outline-none focus:ring-0 focus:border-blue-600 peer"
                      placeholder=" " />
                  </div>
                </div>
                <div class="flex items-center mb-1">
                  <input type="checkbox" name="tobacco_flag" value="1" onchange="toggleInput(this, 'tobacco_details')"
                    class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded-sm focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600">
                  <div class="grid grid-cols-2 gap-5.5">
                    <label for="default-checkbox" class="ms-2 text-sm font-medium text-gray-900 dark:text-gray-300">Use
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
                    <input type="text" id="betel_nut_details" name="betel_nut_details" disabled
                      class="block py-1 h-4.5 px-0 w-73.5 text-sm text-gray-900 bg-transparent border-0 border-b-2 border-gray-300 appearance-none dark:text-white dark:border-gray-600 dark:focus:border-blue-500 focus:outline-none focus:ring-0 focus:border-blue-600 peer"
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
                  <label for="name" class="block mb-2 text-xs font-medium text-gray-900 dark:text-white">Blood
                    Preassure</label>
                  <input type="text" name="blood_pressure" data-required data-label="Blood Pressure"
                    class="bg-gray-50 border border-gray-300 text-gray-900 text-xs rounded-sm focus:ring-primary-600 focus:border-primary-600 block w-full p-1 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500"
                    placeholder="">
                </div>
                <div class="w-full">
                  <label for="name"
                    class="block mb-2 text-xs font-medium text-gray-900 dark:text-white">Temperature</label>
                  <input type="number" step="0.1" name="temperature" data-required data-label="Temperature"
                    class="bg-gray-50 border border-gray-300 text-gray-900 text-xs rounded-sm focus:ring-primary-600 focus:border-primary-600 block w-full p-1 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500"
                    placeholder="">
                </div>
                <div class="w-full">
                  <label for="name" class="block mb-2 text-xs font-medium text-gray-900 dark:text-white">Pulse
                    Rate</label>
                  <input type="number" name="pulse_rate" data-required data-label="Pulse Rate"
                    class="bg-gray-50 border border-gray-300 text-gray-900 text-xs rounded-sm focus:ring-primary-600 focus:border-primary-600 block w-full p-1 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500"
                    placeholder="">
                </div>
                <div class="w-full">
                  <label for="name" class="block mb-2 text-xs font-medium text-gray-900 dark:text-white">Weight</label>
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

        <!-- Notification Container -->
        <div id="notice"
          style="position:fixed; top:14px; right:14px; display:none; padding:10px 14px; border-radius:6px; background:blue; color:white; z-index:60">
        </div>
      </section>
    </main>
  </div>

  <!-- <script src="../node_modules/flowbite/dist/flowbite.min.js"></script> -->
  <script src="https://cdn.jsdelivr.net/npm/flowbite@3.1.2/dist/flowbite.min.js"></script>
  <script src="../js/tailwind.config.js"></script>
  <!-- Client-side 10-minute inactivity logout -->
  <script>
    let inactivityTime = 1800000; // 10 minutes in ms
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
  </script>

  <!-- Backbtn -->
  <script>
    function back() {
      location.href = ("addpatient.php?uid=<?php echo $userId; ?>");
    }
  </script>

  <!-- COMBINED & IMPROVED PATIENT MANAGEMENT SCRIPT -->
  <script>
    // ============================================================================
    // UNIVERSAL BROWSER COMPATIBILITY LAYER (from first script)
    // ============================================================================
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

    // ============================================================================
    // MAIN PATIENT MANAGEMENT SCRIPT (COMPREHENSIVE FIX)
    // ============================================================================
    document.addEventListener('DOMContentLoaded', () => {
      const urlParams = new URLSearchParams(window.location.search);
      const patientId = urlParams.get('id');
      const userId = urlParams.get('uid');
      const wasUpdated = urlParams.get('updated');

      console.log('URL Parameters:', {
        patientId,
        userId,
        wasUpdated
      });

      // DOM Elements
      const notice = document.getElementById('notice');
      const editBtn = document.getElementById('editBtn');
      const modal = document.getElementById('editPatientModal');
      const form = document.getElementById('editPatientForm');

      // Form fields
      const dobField = document.getElementById('editDob');
      const ageInput = document.getElementById('age');
      const sexInput = document.getElementById('editSex');
      const monthInput = document.getElementById('agemonth');
      const monthContainer = document.getElementById('monthContainer');
      const formContainer = document.getElementById('form-container');
      const pregnantSection = document.getElementById('pregnant-section');
      const pregnantRadios = pregnantSection ? pregnantSection.querySelectorAll('input[name="pregnant"]') : [];

      let currentPatient = null;

      // ============================================================================
      // NOTIFICATION SYSTEM
      // ============================================================================
      function showNotice(msg, color = 'blue') {
        console.log('Notice:', msg, color);

        let targetNotice = document.getElementById("notice");
        if (!targetNotice) {
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
            background: ${color};
          `;
          document.body.appendChild(newNotice);
          targetNotice = newNotice;
        }

        targetNotice.textContent = msg;
        targetNotice.style.background = color;
        targetNotice.style.display = 'block';
        targetNotice.style.opacity = '1';

        // Clear any existing timeout
        if (targetNotice.timeoutId) {
          clearTimeout(targetNotice.timeoutId);
        }

        targetNotice.timeoutId = setTimeout(() => {
          targetNotice.style.opacity = '0';
          setTimeout(() => {
            targetNotice.style.display = 'none';
          }, 300);
        }, 3000);
      }

      // ============================================================================
      // PATIENT DATA FUNCTIONS
      // ============================================================================
      function handleMonthVisibility(years, months = 0) {
        if (monthContainer && monthInput) {
          if (years < 5) {
            monthContainer.style.display = 'block';
            monthContainer.classList.remove('hidden');
            monthInput.value = years * 12 + months;
          } else {
            monthContainer.style.display = 'none';
            monthContainer.classList.add('hidden');
            monthInput.value = '';
          }
        }
      }

      function togglePregnantSection() {
        if (!sexInput || !ageInput || !pregnantSection || !formContainer) return;

        const age = parseInt(ageInput.value, 10) || 0;
        const sex = sexInput.value;
        const showPregnant = sex === 'Female' && age >= 10 && age <= 49;

        pregnantSection.classList.toggle('hidden', !showPregnant);
        formContainer.classList.toggle('grid-cols-3', showPregnant);
        formContainer.classList.toggle('grid-cols-2', !showPregnant);

        pregnantRadios.forEach(r => {
          r.disabled = !showPregnant;
          r.required = showPregnant;
          if (!showPregnant && r.value.toLowerCase() === 'no') r.checked = true;
        });
      }

      // ============================================================================
      // LOAD PATIENT INFO - SINGLE RELIABLE ENDPOINT
      // ============================================================================
      async function loadPatient() {
        try {
          if (!patientId) {
            showNotice("No patient selected.", "crimson");
            return;
          }

          console.log("Loading patient with ID:", patientId);

          // SINGLE RELIABLE ENDPOINT - Use get_patient.php which you have
          const endpoint = `/dentalemr_system/php/register_patient/get_patient.php?id=${patientId}`;
          console.log("Using endpoint:", endpoint);

          const res = await fetch(endpoint, {
            method: 'GET',
            headers: {
              'Accept': 'application/json',
              'Cache-Control': 'no-cache'
            }
          });

          console.log("Response status:", res.status, res.statusText);

          if (!res.ok) {
            throw new Error(`HTTP ${res.status}: ${res.statusText}`);
          }

          const responseText = await res.text();
          console.log("Raw response:", responseText);

          let result;
          try {
            result = JSON.parse(responseText);
          } catch (jsonError) {
            console.error('Invalid JSON response:', responseText.substring(0, 200));
            throw new Error('Server returned invalid JSON');
          }

          if (!result.success || !result.patient) {
            console.error('API returned error:', result.error || 'No patient data');
            throw new Error(result.error || 'Patient not found');
          }

          const p = result.patient;
          console.log("Patient data loaded:", p);
          currentPatient = p;

          if (editBtn) {
            editBtn.onclick = () => openModal(currentPatient);
            editBtn.disabled = false;
          }

          if (wasUpdated === '1') {
            showNotice('Patient updated successfully', 'blue');
          }

          // Update display
          updateDisplay(p);

        } catch (err) {
          console.error('Error loading patient:', err);

          let errorMessage = "Failed to load patient info";
          if (err.message.includes('NetworkError') || err.message.includes('Failed to fetch')) {
            errorMessage = "Cannot connect to server. Please check your connection.";
          } else if (err.message.includes('Patient not found')) {
            errorMessage = "Patient not found. Please check the patient ID.";
          }

          showNotice(errorMessage, "crimson");

          // Update display with error state
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

          // Disable edit button if no patient loaded
          if (editBtn) {
            editBtn.disabled = true;
          }
        }
      }

      function updateDisplay(patient) {
        if (!patient) return;

        console.log("Updating display with:", patient);

        // Format name
        let name = '';
        if (patient.surname && patient.firstname) {
          name = `${patient.surname}, ${patient.firstname} ${patient.middlename || ''}`.trim();
        } else if (patient.firstname) {
          name = patient.firstname;
        } else {
          name = 'Unknown Patient';
        }

        // Format age
        let ageText = 'N/A';
        if (patient.display_age) {
          ageText = patient.display_age;
        } else if (patient.age !== undefined && patient.age !== null) {
          if (parseInt(patient.age) === 0 && patient.agemonth) {
            ageText = `${patient.agemonth} months old`;
          } else {
            ageText = `${patient.age} years old`;
          }
        }

        // Update all fields
        const fields = {
          patientName: `${name}.`,
          patientName2: `${name}.`,
          patientDob: patient.date_of_birth || 'N/A',
          patientSex: patient.sex || 'N/A',
          patientAge: ageText,
          patientBirthPlace: patient.place_of_birth || 'N/A',
          patientOccupation: patient.occupation || 'N/A',
          patientAddress: patient.address || 'N/A',
          patientGuardian: patient.guardian || 'N/A'
        };

        for (const [id, value] of Object.entries(fields)) {
          const el = document.getElementById(id);
          if (el) {
            el.textContent = value;
            // Remove error styling if it was there
            el.style.color = '';
            el.style.fontStyle = '';
          }
        }

        console.log("Display updated successfully");
      }

      // ============================================================================
      // MODAL FUNCTIONS
      // ============================================================================
      function openModal(patient) {
        if (!patient || !form) {
          showNotice("Cannot open edit form. Patient data missing.", "crimson");
          return;
        }

        try {
          console.log("Opening modal for patient:", patient);

          // Fill form with patient data
          const patientIdInput = form.querySelector('input[name="patient_id"]') || document.getElementById('editPatientId');
          if (patientIdInput) {
            patientIdInput.value = patient.patient_id || patientId || '';
            console.log("Patient ID set to:", patientIdInput.value);
          }

          // Set form field values
          const setFieldValue = (name, value) => {
            const field = form.querySelector(`[name="${name}"]`);
            if (field) {
              field.value = value || '';
              console.log(`Set ${name} to:`, value);
            }
          };

          setFieldValue("firstname", patient.firstname);
          setFieldValue("surname", patient.surname);
          setFieldValue("middlename", patient.middlename);
          setFieldValue("place_of_birth", patient.place_of_birth);
          setFieldValue("occupation", patient.occupation);
          setFieldValue("address", patient.address);
          setFieldValue("guardian", patient.guardian);

          // Set date of birth
          if (dobField && patient.date_of_birth) {
            // Format date for input[type="date"]
            const dob = new Date(patient.date_of_birth);
            if (!isNaN(dob.getTime())) {
              const year = dob.getFullYear();
              const month = String(dob.getMonth() + 1).padStart(2, '0');
              const day = String(dob.getDate()).padStart(2, '0');
              dobField.value = `${year}-${month}-${day}`;
              console.log("DOB set to:", dobField.value);
            }
          }

          // Set age
          if (ageInput) {
            ageInput.value = patient.age || '';
            console.log("Age set to:", ageInput.value);
          }

          // Set sex
          if (sexInput) {
            const sexValue = patient.sex || '';
            if (sexValue === 'Male' || sexValue === 'Female') {
              sexInput.value = sexValue;
              console.log("Sex set to:", sexInput.value);
            } else {
              sexInput.value = '';
            }
          }

          // Handle month visibility
          const years = parseInt(patient.age) || 0;
          const months = parseInt(patient.agemonth) || 0;
          handleMonthVisibility(years, months);
          console.log("Month visibility handled. Years:", years, "Months:", months);

          // Update pregnant section
          togglePregnantSection();
          console.log("Pregnant section toggled");

          // Set pregnant status
          if (patient.pregnant) {
            pregnantRadios.forEach(r => {
              if (r.value.toLowerCase() === patient.pregnant.toLowerCase()) {
                r.checked = true;
                console.log("Pregnant set to:", r.value);
              }
            });
          } else {
            pregnantRadios.forEach(r => {
              if (r.value.toLowerCase() === 'no') {
                r.checked = true;
                console.log("Pregnant defaulted to: no");
              }
            });
          }

          // Show modal
          modal.classList.remove('hidden');
          modal.classList.add('flex');
          console.log("Modal opened successfully");

        } catch (error) {
          console.error("Error opening modal:", error);
          showNotice("Error opening edit form. Please try again.", "crimson");
        }
      }

      window.closeModal = () => {
        if (modal) {
          modal.classList.add('hidden');
          modal.classList.remove('flex');
          console.log("Modal closed");
        }
      };

      // ============================================================================
      // FORM SUBMISSION HANDLER - FIXED VERSION
      // ============================================================================
      if (form) {
        form.addEventListener('submit', async e => {
          e.preventDefault();

          const submitBtn = e.target.querySelector('[type="submit"]');
          const originalText = submitBtn.textContent;
          submitBtn.textContent = "Saving...";
          submitBtn.disabled = true;

          console.log("Form submission started");

          try {
            // Get form data
            const formData = new FormData(e.target);
            const data = {};
            formData.forEach((value, key) => {
              // Skip empty values except for specific fields
              if (value !== '' || key === 'age' || key === 'patient_id') {
                data[key] = value;
              }
            });

            console.log("Form data to be sent:", data);

            // Get patient ID - check multiple sources
            let pid = data['patient_id'] || patientId;

            // Check for editPatientId field (from hidden input)
            const editPatientIdField = document.getElementById('editPatientId');
            if (!pid && editPatientIdField && editPatientIdField.value) {
              pid = editPatientIdField.value;
              data['patient_id'] = pid; // Ensure it's in the data
            }

            if (!pid) {
              showNotice("No patient ID specified", "crimson");
              submitBtn.textContent = originalText;
              submitBtn.disabled = false;
              return;
            }

            // Ensure patient_id is in the data
            data['patient_id'] = pid;

            // Add update_patient flag
            data['update_patient'] = '1';

            // Prepare URL with UID
            const uidParam = userId ? `?uid=${userId}` : '';
            const url = `/dentalemr_system/php/register_patient/update_patient.php${uidParam}`;

            console.log("Submitting to:", url);
            console.log("Data being sent:", JSON.stringify(data));

            // Convert data to URLSearchParams
            const params = new URLSearchParams();
            for (const key in data) {
              if (data[key] !== undefined && data[key] !== null) {
                params.append(key, data[key]);
              }
            }

            // Send the request with explicit JSON header
            const result = await fetch(url, {
              method: 'POST',
              headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json' // Explicitly ask for JSON
              },
              body: params
            });

            console.log("Response status:", result.status, result.statusText);
            console.log("Response headers:", Object.fromEntries(result.headers.entries()));

            // Get response text first
            const responseText = await result.text();
            console.log("Raw response (first 1000 chars):", responseText.substring(0, 1000));

            // Try to parse as JSON
            let jsonResult;
            try {
              jsonResult = JSON.parse(responseText);
              console.log("Parsed JSON response:", jsonResult);
            } catch (parseError) {
              console.error("Failed to parse response as JSON:", parseError);

              // Check if it's an HTML error page
              if (responseText.includes('<html') || responseText.includes('<!DOCTYPE')) {
                // Extract error message from HTML if possible
                let errorMsg = "Server returned HTML instead of JSON. Check PHP errors.";

                // Try to extract PHP error
                const errorMatch = responseText.match(/<b>([^<]+)<\/b>/);
                if (errorMatch) {
                  errorMsg += " PHP Error: " + errorMatch[1];
                }

                showNotice(errorMsg, "crimson");
              } else if (responseText.includes('success') || responseText.includes('Patient updated')) {
                // It's not JSON but contains success text
                showNotice("Patient updated successfully!", "green");
                window.closeModal();
                setTimeout(() => loadPatient(), 500);

                // Update URL
                const newUrl = new URL(window.location);
                newUrl.searchParams.set('updated', '1');
                window.history.replaceState({}, '', newUrl);
              } else if (responseText.includes('Location:') || responseText.includes('window.location')) {
                // It's a redirect
                showNotice("Patient updated! Redirecting...", "blue");
                setTimeout(() => {
                  window.location.href = `/dentalemr_system/html/viewrecord.php?uid=${userId}&id=${pid}&updated=1`;
                }, 1000);
              } else {
                // Unknown response
                showNotice("Server returned unexpected response format", "orange");
                console.error("Full response:", responseText);
              }

              submitBtn.textContent = originalText;
              submitBtn.disabled = false;
              return;
            }

            // Handle JSON response
            if (jsonResult.success) {
              showNotice(jsonResult.message || "Patient updated successfully!", "blue");

              // Close modal
              window.closeModal();

              // Reload patient data
              setTimeout(() => {
                loadPatient();

                // Also reload other sections if they exist
                if (window.reloadPatientAdditionalInfo) {
                  window.reloadPatientAdditionalInfo();
                }
              }, 500);

              // Update URL to show success
              const newUrl = new URL(window.location);
              newUrl.searchParams.set('updated', '1');
              window.history.replaceState({}, '', newUrl);

              // If there's a redirect URL, follow it
              if (jsonResult.redirect) {
                setTimeout(() => {
                  window.location.href = jsonResult.redirect;
                }, 1500);
              }
            } else {
              showNotice("Error: " + (jsonResult.error || jsonResult.message || "Update failed"), "crimson");
            }

          } catch (error) {
            console.error('Save error:', error);
            showNotice("Network error: " + error.message, "crimson");
          } finally {
            submitBtn.textContent = originalText;
            submitBtn.disabled = false;
          }
        });
      }

      // ============================================================================
      // EVENT LISTENERS
      // ============================================================================

      // Age and DOB event listeners
      if (dobField) {
        dobField.addEventListener('change', function() {
          console.log("DOB changed to:", this.value);
          // We'll handle age calculation differently
        });
      }

      if (ageInput) {
        ageInput.addEventListener('input', () => {
          console.log("Age changed to:", ageInput.value);
          togglePregnantSection();
          const years = parseInt(ageInput.value) || 0;
          handleMonthVisibility(years);
        });
      }

      if (sexInput) {
        sexInput.addEventListener('change', function() {
          console.log("Sex changed to:", this.value);
          togglePregnantSection();
        });
      }

      // Edit button event listener
      if (editBtn) {
        editBtn.addEventListener('click', function() {
          console.log("Edit button clicked, currentPatient:", currentPatient);
          if (currentPatient) {
            openModal(currentPatient);
          } else {
            showNotice("Patient data not loaded yet. Please wait or refresh.", "orange");
          }
        });
      }

      // Ensure sex always submits a valid value
      if (form) {
        form.addEventListener('submit', e => {
          if (sexInput && !sexInput.value) {
            showNotice('Please select Male or Female.', "crimson");
            e.preventDefault();
          }
        });
      }

      // ============================================================================
      // INITIALIZATION
      // ============================================================================

      // Load patient data
      if (patientId) {
        console.log("Page initialized with patient ID:", patientId);

        // Show loading state
        const fields = [
          "patientName", "patientName2", "patientSex", "patientAge",
          "patientDob", "patientOccupation", "patientBirthPlace",
          "patientAddress", "patientGuardian"
        ];

        fields.forEach(fieldId => {
          const element = document.getElementById(fieldId);
          if (element) {
            element.textContent = "Loading...";
            element.style.color = "";
            element.style.fontStyle = "italic";
          }
        });

        // Disable edit button while loading
        if (editBtn) {
          editBtn.disabled = true;
        }

        // Load patient after a short delay to ensure DOM is ready
        setTimeout(() => {
          loadPatient();
        }, 100);

      } else {
        console.error("No patient ID found in URL");
        showNotice("No patient selected. Please go back and select a patient.", "crimson");

        // Update all fields to show error
        const fields = [
          "patientName", "patientName2", "patientSex", "patientAge",
          "patientDob", "patientOccupation", "patientBirthPlace",
          "patientAddress", "patientGuardian"
        ];

        fields.forEach(fieldId => {
          const element = document.getElementById(fieldId);
          if (element) {
            element.textContent = "No patient selected";
            element.style.color = "#dc2626";
          }
        });

        // Disable edit button
        if (editBtn) {
          editBtn.disabled = true;
        }
      }

      // Add offline detection
      window.addEventListener('online', () => {
        console.log("Browser is online");
        if (patientId && !currentPatient) {
          showNotice("Reconnecting...", "blue");
          // Try to reload if we're online and haven't loaded patient
          setTimeout(() => loadPatient(), 1000);
        }
      });

      window.addEventListener('offline', () => {
        console.log("Browser is offline");
        showNotice("You are offline. Patient data cannot be loaded.", "orange");
      });

      // Log when DOM is fully loaded
      console.log("Patient management script initialized");
    });
  </script>


  <!-- MEMBERSHIP, MEDICAL & DIETARY SCRIPT (FIXED VERSION) -->
  <script>
    // Global functions for membership, medical, and dietary sections
    let patientDataLoaded = false;

    /* -----------------------------------------------------------
       Helper functions for membership/medical/dietary
    ------------------------------------------------------------*/
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

    function showModal(id) {
      const modal = document.getElementById(id);
      if (modal) {
        modal.classList.remove("hidden");
        modal.classList.add("flex");

        // Ensure patient ID is set in the form when modal opens
        const urlParams = new URLSearchParams(window.location.search);
        const patientId = urlParams.get('id');
        if (patientId) {
          const form = modal.querySelector('form');
          if (form) {
            const patientIdInput = form.querySelector('input[name="patient_id"]');
            if (patientIdInput) {
              patientIdInput.value = patientId;
            } else {
              // Create hidden input if it doesn't exist
              const hiddenInput = document.createElement('input');
              hiddenInput.type = 'hidden';
              hiddenInput.name = 'patient_id';
              hiddenInput.value = patientId;
              form.appendChild(hiddenInput);
            }
          }
        }
      }
    }

    function hideModal(id) {
      const modal = document.getElementById(id);
      if (modal) {
        modal.classList.add("hidden");
        modal.classList.remove("flex");
      }
    }

    /* -----------------------------------------------------------
       Wrapper: ALWAYS attach ?uid= to any fetch request
    ------------------------------------------------------------*/
    function apiFetch(url, options = {}) {
      const loggedInUid = <?= json_encode($_GET['uid'] ?? 0) ?>;
      const connector = url.includes("?") ? "&" : "?";
      const finalUrl = `${url}${connector}uid=${loggedInUid}`;
      return fetch(finalUrl, options);
    }

    /* -----------------------------------------------------------
       Unified showNotice function
    ------------------------------------------------------------*/
    function showNotice(message, success = true) {
      const notice = document.getElementById('notice');
      if (!notice) {
        // Create notice element if it doesn't exist
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
      targetNotice.style.background = success ? '#2563eb' : '#dc2626';
      targetNotice.style.display = "block";
      targetNotice.style.opacity = "1";

      targetNotice.timeoutId = setTimeout(() => {
        targetNotice.style.opacity = "0";
        setTimeout(() => {
          targetNotice.style.display = "none";
        }, 300);
      }, 3000);
    }

    /* -----------------------------------------------------------
       Get patient ID from URL - robust function
    ------------------------------------------------------------*/
    function getPatientId() {
      const urlParams = new URLSearchParams(window.location.search);
      const patientId = urlParams.get('id');

      // Also try to get from hidden input in edit form
      if (!patientId || patientId === 'null' || patientId === 'undefined') {
        const editFormPatientId = document.querySelector('#editPatientForm input[name="editPatientId"]')?.value;
        if (editFormPatientId) return editFormPatientId;

        const hiddenPatientId = document.querySelector('input[name="patient_id"]')?.value;
        if (hiddenPatientId) return hiddenPatientId;
      }

      return patientId;
    }

    /* -----------------------------------------------------------
       Load membership data
    ------------------------------------------------------------*/
    async function loadMemberships(patientId) {
      try {
        if (!patientId || patientId === '0' || patientId === 'null') {
          console.warn("No valid patient ID for membership:", patientId);
          return;
        }

        console.log("Loading memberships for patient ID:", patientId);
        const res = await apiFetch(`../php/register_patient/patient_info.php?action=get_membership&patient_id=${patientId}&_t=${Date.now()}`);
        if (!res.ok) throw new Error(`HTTP ${res.status}`);

        const json = await res.json();
        const membershipList = document.getElementById("membershipList");

        if (!membershipList) {
          console.error("membershipList element not found");
          return;
        }

        membershipList.innerHTML = "";

        // Reset form
        const membershipForm = document.getElementById("membershipForm");
        if (membershipForm) {
          // Set patient ID in form
          const patientIdInput = membershipForm.querySelector('input[name="patient_id"]');
          if (!patientIdInput) {
            const hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden';
            hiddenInput.name = 'patient_id';
            hiddenInput.value = patientId;
            membershipForm.appendChild(hiddenInput);
          } else {
            patientIdInput.value = patientId;
          }

          membershipForm.querySelectorAll("input[type=checkbox]").forEach(cb => cb.checked = false);
          membershipForm.querySelectorAll("input[type=text]").forEach(inp => {
            inp.value = "";
            inp.disabled = true;
          });
        }

        if (json.success && json.values) {
          const v = json.values;

          // Update checkboxes
          if (membershipForm) {
            document.querySelectorAll("#membershipForm input[type=checkbox]").forEach(cb => {
              const name = cb.name;
              const flag = v[name] ?? v[cb.getAttribute('data-field')] ?? 0;
              cb.checked = (flag == 1 || flag === "1");

              // Enable/disable corresponding inputs
              if (cb.id === "philhealth_flag" && cb.checked) {
                const el = document.getElementById("philhealth_number");
                if (el) {
                  el.disabled = false;
                  el.value = v.philhealth_number || "";
                }
              }
              if (cb.id === "sss_flag" && cb.checked) {
                const el = document.getElementById("sss_number");
                if (el) {
                  el.disabled = false;
                  el.value = v.sss_number || "";
                }
              }
              if (cb.id === "gsis_flag" && cb.checked) {
                const el = document.getElementById("gsis_number");
                if (el) {
                  el.disabled = false;
                  el.value = v.gsis_number || "";
                }
              }
            });
          }

          // Update list display
          (json.memberships || []).forEach(m => {
            const li = document.createElement("li");
            li.className = "text-sm text-gray-700 dark:text-gray-300 mb-1";
            li.textContent = "• " + m.label;
            membershipList.appendChild(li);
          });

          // If no memberships, show message
          if (json.memberships.length === 0) {
            const li = document.createElement("li");
            li.className = "text-sm text-gray-500 italic";
            li.textContent = "No membership information recorded";
            membershipList.appendChild(li);
          }

          console.log("Memberships loaded:", json.memberships.length);
        } else {
          const li = document.createElement("li");
          li.className = "text-sm text-gray-500 italic";
          li.textContent = "No membership information recorded";
          membershipList.appendChild(li);
        }
      } catch (err) {
        console.error("loadMemberships error:", err);
        const membershipList = document.getElementById("membershipList");
        if (membershipList) {
          membershipList.innerHTML = '<li class="text-sm text-red-500">Failed to load memberships</li>';
        }
      }
    }

    /* -----------------------------------------------------------
       Load medical history
    ------------------------------------------------------------*/
    async function loadMedicalHistory(patientId) {
      try {
        if (!patientId || patientId === '0' || patientId === 'null') {
          console.warn("No valid patient ID for medical history:", patientId);
          return;
        }

        console.log("Loading medical history for patient ID:", patientId);
        const res = await apiFetch(`../php/register_patient/patient_info.php?action=get_medical&patient_id=${patientId}&_t=${Date.now()}`);
        if (!res.ok) throw new Error(`HTTP ${res.status}`);

        const json = await res.json();
        const medicalList = document.getElementById("medicalHistoryList");

        if (!medicalList) {
          console.error("medicalHistoryList element not found");
          return;
        }

        medicalList.innerHTML = "";

        // Reset form
        const medicalForm = document.getElementById("medicalForm");
        if (medicalForm) {
          // Set patient ID in form
          const patientIdInput = medicalForm.querySelector('input[name="patient_id"]');
          if (!patientIdInput) {
            const hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden';
            hiddenInput.name = 'patient_id';
            hiddenInput.value = patientId;
            medicalForm.appendChild(hiddenInput);
          } else {
            patientIdInput.value = patientId;
          }

          medicalForm.querySelectorAll("input[type=checkbox]").forEach(cb => cb.checked = false);
          medicalForm.querySelectorAll("input[type=text], input[type=date], textarea").forEach(inp => {
            inp.value = "";
            inp.disabled = true;
          });
        }

        if (json.success && json.values) {
          const v = json.values;

          // Update checkboxes and enable corresponding inputs
          if (medicalForm) {
            medicalForm.querySelectorAll("input[type=checkbox]").forEach(cb => {
              const val = v[cb.name] ?? 0;
              cb.checked = (val == 1 || val === "1");

              // Enable corresponding inputs if checked
              if (cb.checked) {
                switch (cb.name) {
                  case 'allergies_flag':
                    const allergiesDetails = document.getElementById("allergies_details");
                    if (allergiesDetails) {
                      allergiesDetails.disabled = false;
                      allergiesDetails.value = v.allergies_details || "";
                    }
                    break;
                  case 'hepatitis_flag':
                    const hepatitisDetails = document.getElementById("hepatitis_details");
                    if (hepatitisDetails) {
                      hepatitisDetails.disabled = false;
                      hepatitisDetails.value = v.hepatitis_details || "";
                    }
                    break;
                  case 'malignancy_flag':
                    const malignancyDetails = document.getElementById("malignancy_details");
                    if (malignancyDetails) {
                      malignancyDetails.disabled = false;
                      malignancyDetails.value = v.malignancy_details || "";
                    }
                    break;
                  case 'prev_hospitalization_flag':
                    const d = document.getElementById("last_admission_date");
                    const c = document.getElementById("admission_cause");
                    const s = document.getElementById("surgery_details");
                    if (d) {
                      d.disabled = false;
                      d.value = v.last_admission_date || "";
                    }
                    if (c) {
                      c.disabled = false;
                      c.value = v.admission_cause || "";
                    }
                    if (s) {
                      s.disabled = false;
                      s.value = v.surgery_details || "";
                    }
                    break;
                  case 'blood_transfusion_flag':
                    const bloodTransfusion = document.getElementById("blood_transfusion_date");
                    if (bloodTransfusion) {
                      bloodTransfusion.disabled = false;
                      bloodTransfusion.value = v.blood_transfusion || "";
                    }
                    break;
                  case 'other_conditions_flag':
                    const otherConditions = document.getElementById("other_conditions");
                    if (otherConditions) {
                      otherConditions.disabled = false;
                      otherConditions.value = v.other_conditions || "";
                    }
                    break;
                }
              }
            });
          }

          // Update list display
          (json.medical || []).forEach(m => {
            const li = document.createElement("li");
            li.className = "text-sm text-gray-700 dark:text-gray-300 mb-1";
            li.textContent = "• " + m.label;
            medicalList.appendChild(li);
          });

          // If no medical history, show message
          if (json.medical.length === 0) {
            const li = document.createElement("li");
            li.className = "text-sm text-gray-500 italic";
            li.textContent = "No medical history recorded";
            medicalList.appendChild(li);
          }

          console.log("Medical history loaded:", json.medical.length);
        } else {
          const li = document.createElement("li");
          li.className = "text-sm text-gray-500 italic";
          li.textContent = "No medical history recorded";
          medicalList.appendChild(li);
        }
      } catch (err) {
        console.error("loadMedicalHistory error:", err);
        const medicalList = document.getElementById("medicalHistoryList");
        if (medicalList) {
          medicalList.innerHTML = '<li class="text-sm text-red-500">Failed to load medical history</li>';
        }
      }
    }

    /* -----------------------------------------------------------
       Load dietary history
    ------------------------------------------------------------*/
    async function loadDietaryHistory(patientId) {
      try {
        if (!patientId || patientId === '0' || patientId === 'null') {
          console.warn("No valid patient ID for dietary history:", patientId);
          return;
        }

        console.log("Loading dietary history for patient ID:", patientId);
        const res = await apiFetch(`../php/register_patient/patient_info.php?action=get_dietary&patient_id=${patientId}&_t=${Date.now()}`);
        if (!res.ok) throw new Error(`HTTP ${res.status}`);

        const json = await res.json();
        const dietaryList = document.getElementById("dietaryHistoryList");

        if (!dietaryList) {
          console.error("dietaryHistoryList element not found");
          return;
        }

        dietaryList.innerHTML = "";

        // Reset form
        const dietaryForm = document.getElementById("dietaryForm");
        if (dietaryForm) {
          // Set patient ID in form
          const patientIdInput = dietaryForm.querySelector('input[name="patient_id"]');
          if (!patientIdInput) {
            const hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden';
            hiddenInput.name = 'patient_id';
            hiddenInput.value = patientId;
            dietaryForm.appendChild(hiddenInput);
          } else {
            patientIdInput.value = patientId;
          }

          dietaryForm.querySelectorAll("input[type=checkbox]").forEach(cb => cb.checked = false);
          dietaryForm.querySelectorAll("input[type=text]").forEach(inp => {
            inp.value = "";
            inp.disabled = true;
          });
        }

        if (json.success && json.values) {
          const v = json.values;

          // Update checkboxes and enable corresponding inputs
          if (dietaryForm) {
            dietaryForm.querySelectorAll("input[type=checkbox]").forEach(cb => {
              const val = v[cb.name] ?? 0;
              cb.checked = (val == 1 || val === "1");

              // Enable corresponding inputs if checked
              if (cb.checked) {
                switch (cb.name) {
                  case 'sugar_flag':
                    const sugarDetails = document.getElementById("sugar_details");
                    if (sugarDetails) {
                      sugarDetails.disabled = false;
                      sugarDetails.value = v.sugar_details || "";
                    }
                    break;
                  case 'alcohol_flag':
                    const alcoholDetails = document.getElementById("alcohol_details");
                    if (alcoholDetails) {
                      alcoholDetails.disabled = false;
                      alcoholDetails.value = v.alcohol_details || "";
                    }
                    break;
                  case 'tobacco_flag':
                    const tobaccoDetails = document.getElementById("tobacco_details");
                    if (tobaccoDetails) {
                      tobaccoDetails.disabled = false;
                      tobaccoDetails.value = v.tobacco_details || "";
                    }
                    break;
                  case 'betel_nut_flag':
                    const betelNutDetails = document.getElementById("betel_nut_details");
                    if (betelNutDetails) {
                      betelNutDetails.disabled = false;
                      betelNutDetails.value = v.betel_nut_details || "";
                    }
                    break;
                }
              }
            });
          }

          // Update list display
          (json.dietary || []).forEach(m => {
            const li = document.createElement("li");
            li.className = "text-sm text-gray-700 dark:text-gray-300 mb-1";
            li.textContent = "• " + m.label;
            dietaryList.appendChild(li);
          });

          // If no dietary history, show message
          if (json.dietary.length === 0) {
            const li = document.createElement("li");
            li.className = "text-sm text-gray-500 italic";
            li.textContent = "No dietary habits recorded";
            dietaryList.appendChild(li);
          }

          console.log("Dietary history loaded:", json.dietary.length);
        } else {
          const li = document.createElement("li");
          li.className = "text-sm text-gray-500 italic";
          li.textContent = "No dietary habits recorded";
          dietaryList.appendChild(li);
        }
      } catch (err) {
        console.error("loadDietaryHistory error:", err);
        const dietaryList = document.getElementById("dietaryHistoryList");
        if (dietaryList) {
          dietaryList.innerHTML = '<li class="text-sm text-red-500">Failed to load dietary history</li>';
        }
      }
    }

    /* -----------------------------------------------------------
       Load all patient additional info
    ------------------------------------------------------------*/
    function loadPatientAdditionalInfo() {
      const patientId = getPatientId();

      if (!patientId) {
        console.warn("No patient ID found in URL or forms");
        return;
      }

      // Set patient ID on all forms
      document.querySelectorAll("input[name='patient_id']").forEach(input => {
        input.value = patientId;
      });

      // Also create hidden inputs in modal forms if they don't exist
      ['membershipForm', 'medicalForm', 'dietaryForm'].forEach(formId => {
        const form = document.getElementById(formId);
        if (form) {
          let patientIdInput = form.querySelector('input[name="patient_id"]');
          if (!patientIdInput) {
            patientIdInput = document.createElement('input');
            patientIdInput.type = 'hidden';
            patientIdInput.name = 'patient_id';
            patientIdInput.value = patientId;
            form.appendChild(patientIdInput);
          } else {
            patientIdInput.value = patientId;
          }
        }
      });

      // Load all sections
      loadMemberships(patientId);
      loadMedicalHistory(patientId);
      loadDietaryHistory(patientId);

      patientDataLoaded = true;
      console.log("Patient additional info loaded for ID:", patientId);
    }

    /* -----------------------------------------------------------
       Initialize when DOM is ready
    ------------------------------------------------------------*/
    document.addEventListener('DOMContentLoaded', function() {
      console.log("DOM loaded, initializing patient additional info...");

      // Set up modal event listeners
      document.getElementById("addBtn")?.addEventListener("click", () => showModal("membershipModal"));
      document.getElementById("cancelBtn")?.addEventListener("click", () => hideModal("membershipModal"));

      document.getElementById("addMedicalHistoryBtn")?.addEventListener("click", () => showModal("medicalModal"));
      document.getElementById("cancelMedicalBtn")?.addEventListener("click", () => hideModal("medicalModal"));

      document.getElementById("addDietaryHistoryBtn")?.addEventListener("click", () => showModal("dietaryModal"));
      document.getElementById("cancelDietaryBtn")?.addEventListener("click", () => hideModal("dietaryModal"));

      // Set up checkbox event listeners
      document.getElementById("philhealth_flag")?.addEventListener("change", function() {
        toggleInput(this, "philhealth_number");
      });

      document.getElementById("sss_flag")?.addEventListener("change", function() {
        toggleInput(this, "sss_number");
      });

      document.getElementById("gsis_flag")?.addEventListener("change", function() {
        toggleInput(this, "gsis_number");
      });

      document.getElementById("allergies_flag")?.addEventListener("change", function() {
        toggleInput(this, "allergies_details");
      });

      document.getElementById("hepatitis_flag")?.addEventListener("change", function() {
        toggleInput(this, "hepatitis_details");
      });

      document.getElementById("malignancy_flag")?.addEventListener("change", function() {
        toggleInput(this, "malignancy_details");
      });

      document.getElementById("prev_hospitalization_flag")?.addEventListener("change", function() {
        toggleHospitalization(this);
      });

      document.getElementById("blood_transfusion_flag")?.addEventListener("change", function() {
        toggleInput(this, "blood_transfusion_date");
      });

      document.getElementById("other_conditions_flag")?.addEventListener("change", function() {
        toggleInput(this, "other_conditions");
      });

      document.getElementById("sugar_flag")?.addEventListener("change", function() {
        toggleInput(this, "sugar_details");
      });

      document.getElementById("alcohol_flag")?.addEventListener("change", function() {
        toggleInput(this, "alcohol_details");
      });

      document.getElementById("tobacco_flag")?.addEventListener("change", function() {
        toggleInput(this, "tobacco_details");
      });

      document.getElementById("betel_nut_flag")?.addEventListener("change", function() {
        toggleInput(this, "betel_nut_details");
      });

      // Form submission handlers - FIXED VERSION
      const membershipForm = document.getElementById("membershipForm");
      if (membershipForm) {
        membershipForm.addEventListener("submit", async function(e) {
          e.preventDefault();

          const submitBtn = this.querySelector('button[type="submit"]');
          const originalText = submitBtn.textContent;
          submitBtn.textContent = "Saving...";
          submitBtn.disabled = true;

          try {
            const patientId = getPatientId();

            if (!patientId) {
              showNotice("No patient specified", false);
              submitBtn.textContent = originalText;
              submitBtn.disabled = false;
              return;
            }

            // Ensure patient ID is in form data
            let formData = new FormData(this);

            // Check if patient_id is in formData
            if (!formData.get('patient_id')) {
              formData.append('patient_id', patientId);
            }

            // Ensure checkbox values are '1' or '0'
            this.querySelectorAll("input[type=checkbox]").forEach(cb => {
              formData.set(cb.name, cb.checked ? "1" : "0");
            });

            // Ensure text inputs that are disabled are not sent
            this.querySelectorAll("input[type=text][disabled]").forEach(inp => {
              formData.set(inp.name, "");
            });

            formData.set("action", "save_membership");

            console.log("Submitting membership form for patient:", patientId);
            const response = await apiFetch("../php/register_patient/patient_info.php", {
              method: "POST",
              body: formData
            });

            const result = await response.json();

            if (result.success) {
              hideModal("membershipModal");
              showNotice("Membership information saved successfully!", true);
              loadMemberships(patientId);
            } else {
              showNotice("Error: " + (result.message || "Unknown error"), false);
            }
          } catch (error) {
            console.error("Membership save error:", error);
            showNotice("Failed to save membership information", false);
          } finally {
            submitBtn.textContent = originalText;
            submitBtn.disabled = false;
          }
        });
      }

      const medicalForm = document.getElementById("medicalForm");
      if (medicalForm) {
        medicalForm.addEventListener("submit", async function(e) {
          e.preventDefault();

          const submitBtn = this.querySelector('button[type="submit"]');
          const originalText = submitBtn.textContent;
          submitBtn.textContent = "Saving...";
          submitBtn.disabled = true;

          try {
            const patientId = getPatientId();

            if (!patientId) {
              showNotice("No patient specified", false);
              submitBtn.textContent = originalText;
              submitBtn.disabled = false;
              return;
            }

            // Ensure patient ID is in form data
            let formData = new FormData(this);

            // Check if patient_id is in formData
            if (!formData.get('patient_id')) {
              formData.append('patient_id', patientId);
            }

            // Ensure checkbox values are '1' or '0'
            this.querySelectorAll("input[type=checkbox]").forEach(cb => {
              formData.set(cb.name, cb.checked ? "1" : "0");
            });

            // Ensure disabled inputs are not sent
            this.querySelectorAll("input[disabled], textarea[disabled]").forEach(inp => {
              formData.set(inp.name, "");
            });

            formData.set("action", "save_medical");

            console.log("Submitting medical form for patient:", patientId);
            const response = await apiFetch("../php/register_patient/patient_info.php", {
              method: "POST",
              body: formData
            });

            const result = await response.json();

            if (result.success) {
              hideModal("medicalModal");
              showNotice("Medical history saved successfully!", true);
              loadMedicalHistory(patientId);
            } else {
              showNotice("Error: " + (result.message || "Unknown error"), false);
            }
          } catch (error) {
            console.error("Medical save error:", error);
            showNotice("Failed to save medical history", false);
          } finally {
            submitBtn.textContent = originalText;
            submitBtn.disabled = false;
          }
        });
      }

      const dietaryForm = document.getElementById("dietaryForm");
      if (dietaryForm) {
        dietaryForm.addEventListener("submit", async function(e) {
          e.preventDefault();

          const submitBtn = this.querySelector('button[type="submit"]');
          const originalText = submitBtn.textContent;
          submitBtn.textContent = "Saving...";
          submitBtn.disabled = true;

          try {
            const patientId = getPatientId();

            if (!patientId) {
              showNotice("No patient specified", false);
              submitBtn.textContent = originalText;
              submitBtn.disabled = false;
              return;
            }

            // Ensure patient ID is in form data
            let formData = new FormData(this);

            // Check if patient_id is in formData
            if (!formData.get('patient_id')) {
              formData.append('patient_id', patientId);
            }

            // Ensure checkbox values are '1' or '0'
            this.querySelectorAll("input[type=checkbox]").forEach(cb => {
              formData.set(cb.name, cb.checked ? "1" : "0");
            });

            // Ensure disabled inputs are not sent
            this.querySelectorAll("input[type=text][disabled]").forEach(inp => {
              formData.set(inp.name, "");
            });

            formData.set("action", "save_dietary");

            console.log("Submitting dietary form for patient:", patientId);
            const response = await apiFetch("../php/register_patient/patient_info.php", {
              method: "POST",
              body: formData
            });

            const result = await response.json();

            if (result.success) {
              hideModal("dietaryModal");
              showNotice("Dietary habits saved successfully!", true);
              loadDietaryHistory(patientId);
            } else {
              showNotice("Error: " + (result.message || "Unknown error"), false);
            }
          } catch (error) {
            console.error("Dietary save error:", error);
            showNotice("Failed to save dietary habits", false);
          } finally {
            submitBtn.textContent = originalText;
            submitBtn.disabled = false;
          }
        });
      }

      // Load patient data after a short delay to ensure DOM is fully ready
      setTimeout(loadPatientAdditionalInfo, 500);

      // Also load when patient ID changes in URL (for SPA-like behavior)
      window.addEventListener('popstate', loadPatientAdditionalInfo);
    });

    // Global function to reload all patient data
    window.reloadPatientAdditionalInfo = function() {
      if (!patientDataLoaded) {
        loadPatientAdditionalInfo();
      }
    };
  </script>

  <!-- VITAL SIGNS SCRIPT (Enhanced from second script) -->
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
      const notice = document.getElementById('notice');
      const patientInput = document.getElementById('editPatientId'); // hidden input

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

        try {
          const res = await fetch('../php/register_patient/patient_info.php', {
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
            showNotice('Failed to add vital signs: ' + result.message, 'red');
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
          const res = await fetch(`../php/register_patient/patient_info.php?action=get_vitals&patient_id=${patientInput.value}`);
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

      // Fetch on page load
      fetchVitals();
    });
  </script>

  <!-- Load offline storage -->
  <script src="/dentalemr_system/js/offline-storage.js"></script>

  <script>
    // ========== OFFLINE SUPPORT FOR VIEW RECORD - START ==========

    function setupViewRecordOffline() {
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
          <span>Offline Mode - Viewing cached patient record</span>
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
      setupViewRecordOffline();

      if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('/dentalemr_system/sw.js')
          .then(function(registration) {
            console.log('SW registered for view record');
          })
          .catch(function(error) {
            console.log('SW registration failed:', error);
          });
      }
    });

    // ========== OFFLINE SUPPORT FOR VIEW RECORD - END ==========
  </script>

</body>
</body>

</html>