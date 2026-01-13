<?php
session_start();
date_default_timezone_set('Asia/Manila');
// Check if we're in offline mode
$isOfflineMode = isset($_GET['offline']) && $_GET['offline'] === 'true';

// Enhanced session validation with offline support
if ($isOfflineMode) {
    // Offline mode session validation
    if (isset($_SESSION['offline_user'])) {
        $loggedUser = $_SESSION['offline_user'];
        $userId = 'offline';
    } else {
        // Create offline session for this request
        $_SESSION['offline_user'] = [
            'id' => 'offline_user',
            'name' => 'Offline User',
            'email' => 'offline@dentalclinic.com',
            'type' => 'Staff',
            'isOffline' => true
        ];
        $loggedUser = $_SESSION['offline_user'];
        $userId = 'offline';
    }
} else {

    // REQUIRE userId parameter for each page
    // Example usage: dashboard.php?uid=5
    if (!isset($_GET['uid'])) {
        echo "<script>
        alert('Invalid session. Please log in again.');
        window.location.href = '/DentalEMR_System/html/login/login.html';
    </script>";
        exit;
    }

    $userId = intval($_GET['uid']);

    // CHECK IF THIS USER IS REALLY LOGGED IN
    if (
        !isset($_SESSION['active_sessions']) ||
        !isset($_SESSION['active_sessions'][$userId])
    ) {
        echo "<script>
        alert('Please log in first.');
        window.location.href = '/DentalEMR_System/html/login/login.html';
    </script>";
        exit;
    }

    // PER-USER INACTIVITY TIMEOUT
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

    // GET USER DATA FOR PAGE USE
    $loggedUser = $_SESSION['active_sessions'][$userId];

    // Store user session info safely
    $host = "localhost";
    $dbUser = "u401132124_dentalclinic";
    $dbPass = "Mho_DentalClinic1st";
    $dbName = "u401132124_mho_dentalemr";

    $conn = new mysqli($host, $dbUser, $dbPass, $dbName);
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    // Fetch dentist name if user is a dentist
    if ($loggedUser['type'] === 'Staff') {
        $stmt = $conn->prepare("SELECT name FROM staff WHERE id = ?");
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
    <title>Staff Dashboard</title>
    <link rel="icon" type="image/png" href="/DentalEMR_System/img/1761912137392.png">
    <!-- <link href="../css/style.css" rel="stylesheet"> -->
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
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
                    <a href="./staff_dashboard.php?uid=<?php echo $userId; ?>" class="flex items-center justify-between mr-4">
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
                                <img class="w-full h-full object-cover"
                                    src="https://spng.pngfind.com/pngs/s/378-3780189_member-icon-png-transparent-png.png"
                                    alt="user photo" />
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
                                <div class="text-xs text-gray-600 dark:text-white mt-1">
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
                                <a href="/DentalEMR_System/html/a_staff/profile.php?uid=<?php echo $userId; ?>"
                                    class="flex items-center px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700">
                                    <i class="fas fa-user-circle mr-3 text-gray-500 dark:text-gray-400"></i>
                                    My Profile
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
                        <a href="#"
                            class="flex items-center p-2 text-base font-medium text-blue-600 rounded-lg dark:text-blue bg-blue-100   group">
                            <svg aria-hidden="true"
                                class="w-6 h-6 text-blue-600 transition duration-75 dark:text-blue-400  dark:group-hover:text-blue"
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
                        <a href="./staff_addpatient.php?uid=<?php echo $userId; ?>"
                            class="flex items-center p-2 text-base font-medium text-gray-900 rounded-lg transition duration-75 hover:bg-gray-100 dark:hover:bg-gray-700 dark:text-white group">
                            <svg aria-hidden="true"
                                class="flex-shrink-0 w-6 h-6 text-gray-500 transition duration-75 dark:text-gray-400 group-hover:text-gray-900 dark:group-hover:text-white"
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
                        <a href="./treatmentrecords/staff_treatmentrecords.php?uid=<?php echo $userId; ?>"
                            class="flex items-center p-2 text-base font-medium text-gray-900 rounded-lg transition duration-75 hover:bg-gray-100 dark:hover:bg-gray-700 dark:text-white group">
                            <svg aria-hidden="true"
                                class="flex-shrink-0 w-6 h-6 text-gray-500 transition duration-75 dark:text-gray-400 group-hover:text-gray-900 dark:group-hover:text-white"
                                fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                                <path d="M7 3a1 1 0 000 2h6a1 1 0 100-2H7zM4 7a1 1 0 011-1h10a1 1 0 110 2H5a1 1 0 01-1-1zM2 11a2 2 0 012-2h12a2 2 0 012 2v4a2 2 0 01-2 2H4a2 2 0 01-2-2v-4z" />
                            </svg>
                            <span class="ml-3">Treatment Records</span>
                        </a>
                    </li>
                </ul>
                <ul class="pt-5 mt-5 space-y-2 border-t border-gray-200 dark:border-gray-700">
                    <li>
                        <a href="./staff_targetclientlist.php?uid=<?php echo $userId; ?>"
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
                        <a href="./staff_mho_ohp.php?uid=<?php echo $userId; ?>"
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
                        <a href="./staff_oralhygienefindings.php?uid=<?php echo $userId; ?>" class="flex items-center p-2 text-base font-medium text-gray-900 rounded-lg dark:text-white hover:bg-gray-100 dark:hover:bg-gray-700 group">
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
            </div>
        </aside>
        <main class="p-4 md:ml-64 h-auto pt-20">
        </main>
    </div>

    <!-- <script src="../node_modules/flowbite/dist/flowbite.min.js"></script> -->
    <script src="https://cdn.jsdelivr.net/npm/flowbite@3.1.2/dist/flowbite.min.js"></script>
    <script src="/DentalEMR_SYSTEM/js/tailwind.config.js"></script>
    <!-- Client-side 10-minute inactivity logout -->
    <script>
        let inactivityTime = 1800000; // 10 minutes in ms
        let logoutTimer;

        function resetTimer() {
            clearTimeout(logoutTimer);
            logoutTimer = setTimeout(() => {
                alert("You've been logged out due to 10 minutes of inactivity.");
                window.location.href = "/DentalEMR_System/php/login/logout.php";
            }, inactivityTime);
        }

        ["click", "mousemove", "keypress", "scroll", "touchstart"].forEach(evt => {
            document.addEventListener(evt, resetTimer, false);
        });

        resetTimer();
    </script>
</body>

</html>