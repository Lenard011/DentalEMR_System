<?php
session_start();
date_default_timezone_set('Asia/Manila');

// Database configuration
$host = "localhost";
$dbUser = "u401132124_dentalclinic";
$dbPass = "Mho_DentalClinic1st";
$dbName = "u401132124_mho_dentalemr";

// Get patient ID from URL
$patientId = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Check if we're in offline mode
$isOfflineMode = isset($_GET['offline']) && $_GET['offline'] === 'true';

// Session validation
if ($isOfflineMode) {
    // Offline mode
    if (!isset($_SESSION['offline_user'])) {
        // Create offline session
        $_SESSION['offline_user'] = [
            'id' => 'offline_user',
            'name' => 'Offline User',
            'email' => 'offline@dentalclinic.com',
            'type' => 'Dentist',
            'isOffline' => true
        ];
    }
    $loggedUser = $_SESSION['offline_user'];
    $userId = 'offline';
} else {
    // Online mode
    if (!isset($_GET['uid'])) {
        header('Location: /DentalEMR_System/html/login/login.html');
        exit;
    }

    $userId = intval($_GET['uid']);

    // Validate session
    if (!isset($_SESSION['active_sessions'][$userId])) {
        header('Location: /DentalEMR_System/html/login/login.html');
        exit;
    }

    $loggedUser = $_SESSION['active_sessions'][$userId];
    $_SESSION['active_sessions'][$userId]['last_activity'] = time();
}

// Database connection (only for online mode)
$conn = null;
$patientName = "Unknown Patient";

if (!$isOfflineMode && $patientId > 0) {
    try {
        $conn = new mysqli($host, $dbUser, $dbPass, $dbName);
        if ($conn->connect_error) {
            throw new Exception("Connection failed: " . $conn->connect_error);
        }

        // Fetch patient name
        $stmt = $conn->prepare("SELECT firstname, surname, middlename FROM patients WHERE patient_id = ?");
        $stmt->bind_param("i", $patientId);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($patient = $result->fetch_assoc()) {
            $middle = !empty($patient['middlename']) ? " " . $patient['middlename'][0] . "." : "";
            $patientName = $patient['firstname'] . $middle . " " . $patient['surname'];
        }
        $stmt->close();

        // Fetch dentist profile if applicable
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
    } catch (Exception $e) {
        error_log("Database error: " . $e->getMessage());
    }
}
?>
<!doctype html>
<html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Treatment Records - Oral Health Condition</title>
    <link rel="icon" type="image/png" href="/DentalEMR_System/img/1761912137392.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .tooth-cell {
            min-width: 40px;
            text-align: center;
            padding: 4px;
            font-weight: 600;
            border-radius: 4px;
            transition: all 0.2s ease;
        }

        .treatment-fv {
            background-color: #dbeafe;
            color: #1e40af;
        }

        .treatment-fg {
            background-color: #e0f2fe;
            color: #0369a1;
        }

        .treatment-pfs {
            background-color: #dcfce7;
            color: #166534;
        }

        .treatment-pf {
            background-color: #f3e8ff;
            color: #7e22ce;
        }

        .treatment-tf {
            background-color: #fef9c3;
            color: #854d0e;
        }

        .treatment-x {
            background-color: #fee2e2;
            color: #991b1b;
        }

        .treatment-o {
            background-color: #f3f4f6;
            color: #374151;
        }

        .tooth-input {
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .tooth-input:hover {
            transform: scale(1.05);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        #notice {
            transition: opacity 0.3s ease;
        }

        @media print {
            .no-print {
                display: none !important;
            }

            body {
                background-color: white !important;
                color: black !important;
            }

            table {
                break-inside: avoid;
            }
        }
    </style>
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
                        <a href="./staff_dashboard.php?uid=<?php echo $userId; ?>"
                            class="flex items-center p-2 text-base font-medium text-gray-900 rounded-lg transition duration-75 hover:bg-gray-100 dark:hover:bg-gray-700 dark:text-white group">
                            <svg aria-hidden="true"
                                class="flex-shrink-0 w-6 h-6  text-gray-500 transition duration-75 dark:text-gray-400 group-hover:text-gray-900 dark:group-hover:text-white"
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
                        <a href="/DentalEMR_System/html/a_staff/staff_addpatient.php?uid=<?php echo $userId; ?>"
                            class="flex items-center p-2 text-base font-medium text-gray-900 rounded-lg dark:text-white hover:bg-gray-100 dark:hover:bg-gray-700 group">
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
                        <a href="#"
                            class="flex items-center p-2 text-base font-medium text-blue-600 rounded-lg dark:text-blue bg-blue-100   group">
                            <svg aria-hidden="true"
                                class="w-6 h-6 text-blue-600 transition duration-75 dark:text-blue-400  dark:group-hover:text-blue"
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
                        <?php echo htmlspecialchars($patientName); ?>
                    </p>
                    <!-- <button type="button" id="addSMC"
                        class="text-white cursor-pointer flex flex-row items-center justify-center gap-1 bg-blue-700 hover:bg-blue-800 font-medium rounded-sm text-xs px-3 py-2 w-full sm:w-auto dark:bg-primary-600 dark:hover:bg-primary-700 focus:outline-none dark:focus:ring-primary-800 no-print">
                        <svg class="h-3.5 w-3.5" fill="currentColor" viewbox="0 0 20 20" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                            <path clip-rule="evenodd" fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" />
                        </svg>
                        Add Treatment
                    </button> -->
                </div>

                <!-- Services Monitoring Chart Container -->
                <div id="tables-container" class="mx-auto flex flex-col justify-center items-center p-3 sm:p-4 bg-white rounded-lg shadow dark:border shadow-stone-300 drop-shadow-sm dark:bg-gray-800 dark:border-gray-950">
                    <div class="items-center justify-between flex flex-row w-full mb-4">
                        <p class="text-base font-normal text-gray-950 dark:text-white">B. Services Monitoring Chart</p>
                    </div>

                    <!-- Tables Section -->
                    <div class="w-full space-y-6">
                        <!-- First table (55-65) -->
                        <div class="w-full overflow-x-auto">
                            <table id="table-first" class="w-full text-xs sm:text-sm text-left text-gray-500 dark:text-gray-400">
                                <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
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
                                <tbody id="table-first-body">
                                    <!-- Will be populated by JavaScript -->
                                </tbody>
                            </table>
                        </div>

                        <!-- Second table (85-75) -->
                        <div class="w-full overflow-x-auto">
                            <table id="table-second" class="w-full text-xs sm:text-sm text-left text-gray-500 dark:text-gray-400">
                                <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
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
                                <tbody id="table-second-body">
                                    <!-- Will be populated by JavaScript -->
                                </tbody>
                            </table>
                        </div>

                        <!-- Third table (18-28) -->
                        <div class="w-full overflow-x-auto">
                            <table id="table-third" class="w-full text-xs sm:text-sm text-left text-gray-500 dark:text-gray-400">
                                <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
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
                                <tbody id="table-third-body">
                                    <!-- Will be populated by JavaScript -->
                                </tbody>
                            </table>
                        </div>

                        <!-- Fourth table (48-38) -->
                        <div class="w-full overflow-x-auto">
                            <table id="table-fourth" class="w-full text-xs sm:text-sm text-left text-gray-500 dark:text-gray-400">
                                <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
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
                                <tbody id="table-fourth-body">
                                    <!-- Will be populated by JavaScript -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Navigation Buttons -->
                <div class="w-full mt-6 flex justify-between no-print">
                    <button type="button" onclick="back()"
                        class="text-white cursor-pointer inline-flex items-center justify-center bg-blue-700 hover:bg-blue-800 focus:outline-none focus:ring-blue-300 font-medium rounded-lg text-sm px-4 py-2 w-full sm:w-auto dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800">
                        Back
                    </button>
                </div>
            </section>
        </main>

        <!-- Add Treatment Modal -->
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
        <div id="notice" class="hidden fixed top-14 right-14 p-3 rounded-lg text-white font-medium shadow-lg z-50 max-w-sm"></div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/flowbite@3.1.2/dist/flowbite.min.js"></script>
    <script src="/DentalEMR_System/js/tailwind.config.js"></script>
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
    <script>
        // ==================== GLOBAL VARIABLES ====================
        const patientId = <?php echo $patientId; ?>;
        const userId = <?php echo $isOfflineMode ? "'offline'" : $userId; ?>;
        const isOfflineMode = <?php echo $isOfflineMode ? 'true' : 'false'; ?>;
        let selectedTreatmentCode = null;

        // ==================== UTILITY FUNCTIONS ====================
        function showNotice(message, type = 'info') {
            const notice = document.getElementById('notice');
            if (!notice) return;

            const colors = {
                info: 'bg-blue-600',
                success: 'bg-green-600',
                warning: 'bg-yellow-600',
                error: 'bg-red-600'
            };

            notice.className = `${colors[type] || colors.info} fixed top-14 right-14 p-3 rounded-lg text-white font-medium shadow-lg z-50 max-w-sm`;
            notice.textContent = message;
            notice.classList.remove('hidden');

            setTimeout(() => {
                notice.classList.add('hidden');
            }, 5000);
        }

        function formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('en-PH', {
                year: 'numeric',
                month: 'short',
                day: 'numeric'
            });
        }

        function getTreatmentClass(code) {
            const classes = {
                'FV': 'treatment-fv',
                'FG': 'treatment-fg',
                'PFS': 'treatment-pfs',
                'PF': 'treatment-pf',
                'TF': 'treatment-tf',
                'X': 'treatment-x',
                'O': 'treatment-o'
            };
            return classes[code] || '';
        }

        function getTreatmentName(code) {
            const names = {
                'FV': 'Fluoride Varnish',
                'FG': 'Fluoride Gel',
                'PFS': 'Pit and Fissure Sealant',
                'PF': 'Permanent Filling',
                'TF': 'Temporary Filling',
                'X': 'Extraction',
                'O': 'Others'
            };
            return names[code] || code;
        }

        // ==================== NAVIGATION FUNCTIONS ====================
        function back() {
            window.location.href = `staff_view_oralA.php?uid=${userId}&id=${patientId}`;
        }

        function backmain() {
            window.location.href = `staff_treatmentrecords.php?uid=${userId}`;
        }

        // ==================== DATA LOADING ====================
        async function loadTreatmentHistory() {
            if (!patientId) {
                showNotice('No patient selected', 'error');
                return;
            }

            try {
                const url = `/DentalEMR_System/php/treatmentrecords/get_smc_data.php?patient_id=${patientId}&_=${Date.now()}`;
                console.log('Fetching from:', url);

                const response = await fetch(url);

                console.log('Response status:', response.status);

                if (!response.ok) {
                    throw new Error(`HTTP error! Status: ${response.status}`);
                }

                const data = await response.json();
                console.log('Data received:', data);

                if (data.success) {
                    populateTables(data.records);
                    showNotice(`Loaded ${data.records.length} treatment records`, 'success');
                } else {
                    showNotice(data.error || 'Failed to load data', 'error');
                    populateTables([]);
                }
            } catch (error) {
                console.error('Error loading treatment history:', error);
                console.error('Error details:', error.message);
                showNotice('Failed to load treatment data. Please check console for details.', 'error');
                populateTables([]);
            }
        }

        function populateTables(records) {
            const tableGroups = {
                'table-first-body': [55, 54, 53, 52, 51, 61, 62, 63, 64, 65],
                'table-second-body': [85, 84, 83, 82, 81, 71, 72, 73, 74, 75],
                'table-third-body': [18, 17, 16, 15, 14, 13, 12, 11, 21, 22, 23, 24, 25, 26, 27, 28],
                'table-fourth-body': [48, 47, 46, 45, 44, 43, 42, 41, 31, 32, 33, 34, 35, 36, 37, 38]
            };

            // Group records by date
            const groupedByDate = {};
            records.forEach(record => {
                const date = record.created_at ? record.created_at.split(' ')[0] : '';
                if (!date) return;

                if (!groupedByDate[date]) {
                    groupedByDate[date] = {};
                }

                if (record.fdi_number) {
                    groupedByDate[date][record.fdi_number] = record.treatment_code;
                }
            });

            // Clear all tables
            Object.keys(tableGroups).forEach(tableId => {
                const tbody = document.getElementById(tableId);
                if (tbody) tbody.innerHTML = '';
            });

            // Create rows for each date
            const dates = Object.keys(groupedByDate).sort().reverse(); // Newest first

            if (dates.length === 0) {
                // Show "no data" message in first table
                const tbody = document.getElementById('table-first-body');
                if (tbody) {
                    const tr = document.createElement('tr');
                    tr.className = 'border-b border-gray-200 dark:border-gray-700';
                    const td = document.createElement('td');
                    td.className = 'px-4 py-3 text-center text-gray-500 dark:text-gray-400';
                    td.colSpan = 11;
                    td.textContent = 'No treatment records found';
                    tr.appendChild(td);
                    tbody.appendChild(tr);
                }
                return;
            }

            // Populate each table
            dates.forEach(date => {
                Object.entries(tableGroups).forEach(([tableId, teeth]) => {
                    const tbody = document.getElementById(tableId);
                    if (!tbody) return;

                    const tr = document.createElement('tr');
                    tr.className = 'border-b border-gray-200 dark:border-gray-700';

                    // Date cell
                    const dateCell = document.createElement('td');
                    dateCell.className = 'px-2 sm:px-4 py-2 text-center font-medium text-gray-900 dark:text-white whitespace-nowrap';
                    dateCell.textContent = formatDate(date);
                    tr.appendChild(dateCell);

                    // Tooth cells
                    teeth.forEach(tooth => {
                        const td = document.createElement('td');
                        td.className = 'px-2 sm:px-4 py-2 text-center font-medium';

                        const treatmentCode = groupedByDate[date][tooth];
                        if (treatmentCode) {
                            td.textContent = treatmentCode;
                            td.className += ' ' + getTreatmentClass(treatmentCode);
                            td.title = getTreatmentName(treatmentCode);
                        } else {
                            td.textContent = '-';
                            td.className += ' text-gray-400 dark:text-gray-500';
                        }

                        tr.appendChild(td);
                    });

                    tbody.appendChild(tr);
                });
            });
        }

        // ==================== MODAL FUNCTIONS ====================
        function initToothInputs() {
            const toothInputs = document.querySelectorAll('.tooth-input');
            const treatmentSelect = document.getElementById('selcttreatment');

            // Clear existing event listeners
            toothInputs.forEach(input => {
                input.removeEventListener('click', handleToothClick);
                input.removeEventListener('dblclick', handleToothDblClick);
            });

            // Add new event listeners
            toothInputs.forEach(input => {
                input.addEventListener('click', handleToothClick);
                input.addEventListener('dblclick', handleToothDblClick);
            });

            // Treatment selector change
            if (treatmentSelect) {
                treatmentSelect.addEventListener('change', function() {
                    selectedTreatmentCode = this.value;
                    if (selectedTreatmentCode) {
                        const optionText = this.options[this.selectedIndex].text;
                        showNotice(`Selected: ${optionText}`, 'info');
                    }
                });
            }
        }

        function handleToothClick(event) {
            if (!selectedTreatmentCode) {
                showNotice('Please select a treatment first!', 'warning');
                return;
            }

            const input = event.target;
            input.value = selectedTreatmentCode;
            input.style.backgroundColor = '#e5e7eb';
            input.title = getTreatmentName(selectedTreatmentCode);

            // Visual feedback
            input.style.transform = 'scale(1.05)';
            setTimeout(() => {
                input.style.transform = 'scale(1)';
            }, 200);
        }

        function handleToothDblClick(event) {
            const input = event.target;
            input.value = '';
            input.style.backgroundColor = '';
            input.title = '';

            // Visual feedback
            input.style.transform = 'scale(0.95)';
            setTimeout(() => {
                input.style.transform = 'scale(1)';
            }, 200);
        }

        async function loadExistingTreatments(date) {
            if (!patientId) return;

            try {
                const response = await fetch(`/DentalEMR_System/php/treatmentrecords/get_smc_data.php?patient_id=${patientId}&date=${date}`);

                if (!response.ok) return;

                const data = await response.json();

                if (data.success && data.records.length > 0) {
                    data.records.forEach(record => {
                        const input = document.querySelector(`.tooth-input[data-tooth-id="${record.fdi_number}"]`);
                        if (input) {
                            input.value = record.treatment_code;
                            input.style.backgroundColor = '#e5e7eb';
                            input.title = getTreatmentName(record.treatment_code);
                        }
                    });

                    showNotice(`Loaded ${data.records.length} existing treatments for ${date}`, 'info');
                }
            } catch (error) {
                // Silently fail - it's okay if we can't load existing treatments
                console.log('No existing treatments found for this date');
            }
        }

        async function openAddModal() {
            if (!patientId) {
                showNotice('No patient selected', 'error');
                return;
            }

            // Set today's date as default
            const today = new Date().toISOString().split('T')[0];
            const dateInput = document.getElementById('treatmentDate');
            if (dateInput) {
                dateInput.value = today;
            }

            // Clear all inputs
            document.querySelectorAll('.tooth-input').forEach(input => {
                input.value = '';
                input.style.backgroundColor = '';
                input.title = '';
            });

            // Reset treatment selector
            const treatmentSelect = document.getElementById('selcttreatment');
            if (treatmentSelect) {
                treatmentSelect.selectedIndex = 0;
                selectedTreatmentCode = null;
            }

            // Load existing treatments for today
            await loadExistingTreatments(today);

            // Show modal
            document.getElementById('SMCModal').classList.remove('hidden');
            initToothInputs();
        }

        function closeSMC() {
            document.getElementById('SMCModal').classList.add('hidden');
            selectedTreatmentCode = null;
        }

        // ==================== SAVE FUNCTION ====================
        async function saveSMC() {
            if (!patientId) {
                showNotice('Patient ID not set', 'error');
                return;
            }

            const dateInput = document.getElementById('treatmentDate');
            if (!dateInput || !dateInput.value) {
                showNotice('Please select a date', 'warning');
                return;
            }

            const selectedDate = dateInput.value;
            const treatments = [];

            // Collect all treatments
            document.querySelectorAll('.tooth-input').forEach(input => {
                const treatmentCode = input.value.trim();
                const toothId = input.dataset.toothId;

                if (treatmentCode && toothId) {
                    treatments.push({
                        tooth_id: toothId,
                        treatment_code: treatmentCode
                    });
                }
            });

            if (treatments.length === 0) {
                showNotice('No treatments selected', 'warning');
                return;
            }

            // Disable save button
            const saveBtn = document.getElementById('saveSMCBtn');
            const originalText = saveBtn.textContent;
            saveBtn.textContent = 'Saving...';
            saveBtn.disabled = true;

            try {
                const response = await fetch('/DentalEMR_System/php/treatmentrecords/save_smc.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        patient_id: patientId,
                        treatments: treatments,
                        date: selectedDate
                    })
                });

                const data = await response.json();

                if (data.success) {
                    showNotice(data.message, 'success');
                    closeSMC();

                    // Refresh the table
                    setTimeout(() => {
                        loadTreatmentHistory();
                    }, 500);
                } else {
                    showNotice(data.message || 'Failed to save treatments', 'error');
                }
            } catch (error) {
                console.error('Save error:', error);
                showNotice('Failed to save treatments. Please try again.', 'error');
            } finally {
                saveBtn.textContent = originalText;
                saveBtn.disabled = false;
            }
        }

        // ==================== EVENT LISTENERS ====================
        document.addEventListener('DOMContentLoaded', function() {
            // Set up navigation links
            const patientInfoLink = document.getElementById('patientInfoLink');
            const servicesRenderedLink = document.getElementById('servicesRenderedLink');
            const printdLink = document.getElementById('printdLink');

            if (patientId) {
                patientInfoLink.href = `staff_view_info.php?uid=${userId}&id=${patientId}`;
                servicesRenderedLink.href = `staff_view_record.php?uid=${userId}&id=${patientId}`;
                printdLink.href = `print.php?uid=${userId}&id=${patientId}`;
            }

            // Add button
            const addBtn = document.getElementById('addSMC');
            if (addBtn) {
                addBtn.addEventListener('click', openAddModal);
            }

            // Cancel button
            const cancelBtn = document.getElementById('cancelMedicalBtn');
            if (cancelBtn) {
                cancelBtn.addEventListener('click', closeSMC);
            }

            // Modal close on background click
            const modal = document.getElementById('SMCModal');
            if (modal) {
                modal.addEventListener('click', function(event) {
                    if (event.target === modal) {
                        closeSMC();
                    }
                });
            }

            // Load initial data
            if (patientId) {
                loadTreatmentHistory();
            }

            // Setup theme toggle
            initTheme();
        });

        // Theme management (keep from original)
        function initTheme() {
            const currentTheme = localStorage.getItem('theme') || 'light';
            if (currentTheme === 'dark') {
                document.documentElement.classList.add('dark');
            }

            const themeToggle = document.getElementById('theme-toggle');
            if (themeToggle) {
                themeToggle.addEventListener('click', toggleTheme);
            }
        }

        function toggleTheme() {
            const isDark = document.documentElement.classList.contains('dark');
            if (isDark) {
                document.documentElement.classList.remove('dark');
                localStorage.setItem('theme', 'light');
            } else {
                document.documentElement.classList.add('dark');
                localStorage.setItem('theme', 'dark');
            }
        }
    </script>
</body>

</html>