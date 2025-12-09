<?php
session_start();
date_default_timezone_set('Asia/Manila');

// REQUIRE userId parameter for each page
if (!isset($_GET['uid'])) {
    echo "<script>
        alert('Invalid session. Please log in again.');
        window.location.href = '/dentalemr_system/html/login/login.html';
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
        window.location.href = '/dentalemr_system/html/login/login.html';
    </script>";
    exit;
}

// PER-USER INACTIVITY TIMEOUT
$inactiveLimit = 600; // 10 minutes

if (isset($_SESSION['active_sessions'][$userId]['last_activity'])) {
    $lastActivity = $_SESSION['active_sessions'][$userId]['last_activity'];
    if ((time() - $lastActivity) > $inactiveLimit) {
        unset($_SESSION['active_sessions'][$userId]);
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

// GET USER DATA FOR PAGE USE
$loggedUser = $_SESSION['active_sessions'][$userId];

// Store user session info safely
$host = "localhost";
$dbUser = "root";
$dbPass = "";
$dbName = "dentalemr_system";

$conn = new mysqli($host, $dbUser, $dbPass, $dbName);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch dentist name if user is a dentist
if ($loggedUser['type'] === 'Dentist') {
    $stmt = $conn->prepare("SELECT name FROM dentist WHERE id = ?");
    $stmt->bind_param("i", $loggedUser['id']);
    $stmt->execute();
    $stmt->bind_result($dentistName);
    if ($stmt->fetch()) {
        $loggedUser['name'] = $dentistName;
    }
    $stmt->close();
}

?>
<?php
// === DATABASE CONNECTION ===
$host = "localhost";
$user = "root";
$pass = "";
$db = "dentalemr_system";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// === BARANGAYS with mapping for address normalization ===
$barangays = [
    'Balansay' => ['Balansay'],
    'Fatima' => ['Fatima'],
    'Payompon' => ['Payompon'],
    'Pob 1' => ['Pob 1', 'Poblacion 1'],
    'Pob 2' => ['Pob 2', 'Poblacion 2'],
    'Pob 3' => ['Pob 3', 'Poblacion 3'],
    'Pob 4' => ['Pob 4', 'Poblacion 4'],
    'Pob 5' => ['Pob 5', 'Poblacion 5'],
    'Pob 6' => ['Pob 6', 'Poblacion 6'],
    'Pob 7' => ['Pob 7', 'Poblacion 7'],
    'Pob 8' => ['Pob 8', 'Poblacion 8'],
    'San Luis' => ['San Luis'],
    'Talabaan' => ['Talabaan'],
    'Tangkalan' => ['Tangkalan'],
    'Tayamaan' => ['Tayamaan']
];

// === Get selected year from URL parameter or default to current year ===
$selectedYear = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
$currentYear = date('Y');

// === Generate year options for dropdown (current year and previous 5 years) ===
$yearOptions = [];
for ($i = 0; $i <= 5; $i++) {
    $year = $currentYear - $i;
    $yearOptions[$year] = $year;
}

// === Improved Helper Function with better address matching ===
function countPatients($conn, $barangayKey, $barangayValues, $sex, $condition = "1=1", $year = null)
{
    $yearCondition = "";
    if ($year) {
        $yearCondition = " AND YEAR(p.created_at) = $year";
    }

    // Create address condition for all variations of this barangay
    $addressConditions = [];
    foreach ($barangayValues as $address) {
        $addressConditions[] = "LOWER(TRIM(p.address)) = LOWER('" . $conn->real_escape_string(trim($address)) . "')";
    }
    $addressCondition = "(" . implode(" OR ", $addressConditions) . ")";

    // Use DISTINCT to count each patient only once per condition
    $query = "
        SELECT COUNT(DISTINCT p.patient_id) AS total 
        FROM patients p
        WHERE $addressCondition
        AND LOWER(TRIM(p.sex)) = LOWER('" . $conn->real_escape_string($sex) . "')
        AND $condition
        $yearCondition
    ";

    $result = $conn->query($query);
    $row = $result ? $result->fetch_assoc() : ['total' => 0];
    return (int)$row['total'] > 0 ? (int)$row['total'] : '';
}

// === Improved Conditions ===
$conditions = [
    "Orally fit children 12 to 59 months old - upon examination" => "p.age BETWEEN 1 AND 4",
    "Orally fit children 12 to 59 months old - after rehabilitation" => "p.age BETWEEN 1 AND 4 AND p.if_treatment = 1",
    "5 years old and above examined" => "p.age >= 5",
    "5 years old and above with cases of DMFT" => "p.age >= 5 AND p.patient_id IN (SELECT DISTINCT patient_id FROM oral_health_condition WHERE perm_total_dmf > 0)",
    "Infant (0–11 months old)" => "p.months_old BETWEEN 0 AND 11",
    "Pre-schooler (12–59 months old)" => "p.age BETWEEN 1 AND 4",
    "Schoolers (5–9 years old)" => "p.age BETWEEN 5 AND 9",
    "Adolescents (10–14 years old)" => "p.age BETWEEN 10 AND 14",
    "Adolescents (15–19 years old)" => "p.age BETWEEN 15 AND 19",
    "Adult (20–59 years old)" => "p.age BETWEEN 20 AND 59",
    "Senior (60+ years old)" => "p.age >= 60",
    "Pregnant (10–14 years old)" => "p.pregnant = 'yes' AND p.age BETWEEN 10 AND 14",
    "Pregnant (15–19 years old)" => "p.pregnant = 'yes' AND p.age BETWEEN 15 AND 19",
    "Pregnant (20–49 years old)" => "p.pregnant = 'yes' AND p.age BETWEEN 20 AND 49"
];

// === Collect data ===
$data = [];
foreach ($conditions as $key => $cond) {
    foreach ($barangays as $bKey => $bValues) {
        $data[$key][$bKey]['M'] = countPatients($conn, $bKey, $bValues, 'Male', $cond, $selectedYear);
        $data[$key][$bKey]['F'] = countPatients($conn, $bKey, $bValues, 'Female', $cond, $selectedYear);
    }
}

// === Get total unique patients for grand total ===
$totalPatientsByBarangay = [];
foreach ($barangays as $bKey => $bValues) {
    $addressConditions = [];
    foreach ($bValues as $address) {
        $addressConditions[] = "LOWER(TRIM(address)) = LOWER('" . $conn->real_escape_string(trim($address)) . "')";
    }
    $addressCondition = "(" . implode(" OR ", $addressConditions) . ")";

    $yearCondition = $selectedYear ? " AND YEAR(created_at) = $selectedYear" : "";

    // Count males
    $queryM = "SELECT COUNT(DISTINCT patient_id) AS total FROM patients WHERE $addressCondition AND LOWER(TRIM(sex)) = 'male' $yearCondition";
    $resultM = $conn->query($queryM);
    $totalM = $resultM ? $resultM->fetch_assoc()['total'] : 0;

    // Count females
    $queryF = "SELECT COUNT(DISTINCT patient_id) AS total FROM patients WHERE $addressCondition AND LOWER(TRIM(sex)) = 'female' $yearCondition";
    $resultF = $conn->query($queryF);
    $totalF = $resultF ? $resultF->fetch_assoc()['total'] : 0;

    $totalPatientsByBarangay[$bKey] = ['M' => $totalM, 'F' => $totalF];
}

$conn->close();

// Get simple barangay names for display
$barangayNames = array_keys($barangays);
?>
<!doctype html>
<html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Target Client List</title>
    <!-- <link href="../css/style.css" rel="stylesheet"> -->
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <style>

    </style>
</head>

<body>
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
                        <a href="./targetclientlist.php?uid=<?php echo $userId; ?>"
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
                        <a href="#" class="flex items-center p-2 text-base font-medium text-blue-600 rounded-lg dark:text-blue bg-blue-100  dark:hover:bg-blue-700 group">
                            <svg aria-hidden="true"
                                class="w-6 h-6 text-blue-600 transition duration-75 dark:text-blue-400  dark:group-hover:text-blue"
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

        <main class="p-3 md:ml-64 h-auto pt-15">
            <section class="bg-white dark:bg-gray-900 p-3 rounded-lg mb-3 mt-3">
                <div class="w-full flex flex-row p-1 justify-end">
                    <div class="flex flex-row justify-between gap-5">
                        <!-- Year Filter Dropdown -->
                        <div class="flex items-center space-x-3 w-full md:w-auto">
                            <form method="GET" action="" class="flex items-center space-x-2">
                                <input type="hidden" name="uid" value="<?php echo $userId; ?>">
                                <label for="year-select" class="text-sm font-medium text-gray-700 dark:text-gray-300">Year:</label>
                                <select id="year-select" name="year" onchange="this.form.submit()"
                                    class="bg-white border border-gray-300 text-gray-900 text-sm rounded-sm focus:ring-blue-500 focus:border-blue-500 block w-full p-1 cursor-pointer dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500">
                                    <?php foreach ($yearOptions as $year): ?>
                                        <option value="<?php echo $year; ?>" <?php echo $selectedYear == $year ? 'selected' : ''; ?>>
                                            <?php echo $year; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </form>
                        </div>
                        <div class="flex items-center space-x-3 w-full md:w-auto">
                            <button type="button" onclick="printReport()"
                                class="flex items-center justify-center cursor-pointer text-white bg-blue-700 hover:bg-blue-800 font-medium rounded-sm gap-1 text-sm px-4 py-1 dark:bg-blue-600 dark:hover:bg-blue-700">
                                <svg class="w-6 h-6 text-gray-800 dark:text-white" aria-hidden="true" xmlns="http://www.w3.org/2000/svg"
                                    width="24" height="24" fill="none" viewBox="0 0 24 24">
                                    <path stroke="white" stroke-linejoin="round" stroke-width="2"
                                        d="M16.444 18H19a1 1 0 0 0 1-1v-5a1 1 0 0 0-1-1H5a1 1 0 0 0-1 1v5a1 1 0 0 0 1 1h2.556M17 11V5a1 1 0 0 0-1-1H8a1 1 0 0 0-1 1v6h10ZM7 15h10v4a1 1 0 0 1-1 1H8a1 1 0 0 1-1-1v-4Z" />
                                </svg>
                                Generate report
                            </button>
                        </div>
                    </div>
                </div>
                <div class="text-center mb-4">
                    <p class="text-xl font-bold text-gray-900 mb-0">Municipal Health Office - Mamburao</p>
                    <p class="text-lg font-semibold text-gray-900 mt-0">
                        Oral Health Program - <?php echo $selectedYear; ?> (per brgy)
                    </p>
                    <!-- <p class="text-sm text-gray-600 mt-2">Generated on: <?php echo date('F j, Y'); ?></p> -->
                </div>
                <!-- Content for printing -->
                <div id="print-content">
                    <div class="overflow-x-auto">
                        <table class="text-xs text-gray-600 border border-gray-300 border-collapse w-full min-w-[2000px] text-center">
                            <thead class="text-xs text-center align-top text-gray-700 bg-gray-50">
                                <tr>
                                    <th rowspan="2" class="border border-gray-300 px-2 py-1 text-center align-bottom">No.</th>
                                    <th rowspan="2" class="border border-gray-300 px-2 py-1 text-center align-bottom">INDICATORS</th>
                                    <?php foreach ($barangayNames as $b): ?>
                                        <th colspan="2" class="border border-gray-300 px-2 py-1"><?php echo $b; ?></th>
                                    <?php endforeach; ?>
                                    <th colspan="2" class="border border-gray-300 px-2 py-1">SUB TOTAL</th>
                                    <th colspan="2" class="border border-gray-300 px-2 py-1">TOTAL</th>
                                </tr>
                                <tr>
                                    <?php foreach ($barangayNames as $b): ?>
                                        <th class="border border-gray-300 px-2 py-1">M</th>
                                        <th class="border border-gray-300 px-2 py-1">F</th>
                                    <?php endforeach; ?>
                                    <th class="border border-gray-300 px-2 py-1">M</th>
                                    <th class="border border-gray-300 px-2 py-1">F</th>
                                    <th class="border border-gray-300 px-2 py-1"></th>
                                </tr>
                            </thead>

                            <tbody>
                                <?php
                                $rowNum = 1;

                                // Helper function to render M/F cells and calculate subtotal/total
                                function renderRow($indicatorKey, $barangayNames)
                                {
                                    $subtotalM = $subtotalF = 0;
                                    foreach ($barangayNames as $b) {
                                        $m = isset($indicatorKey[$b]['M']) && is_numeric($indicatorKey[$b]['M']) ? (int)$indicatorKey[$b]['M'] : '';
                                        $f = isset($indicatorKey[$b]['F']) && is_numeric($indicatorKey[$b]['F']) ? (int)$indicatorKey[$b]['F'] : '';

                                        // Only add to subtotal if values are numeric and not empty
                                        $subtotalM += ($m !== '' && is_numeric($m)) ? $m : 0;
                                        $subtotalF += ($f !== '' && is_numeric($f)) ? $f : 0;

                                        echo "<td class='border border-gray-300 font-bold'>$m</td><td class='border border-gray-300 font-bold'>$f</td>";
                                    }
                                    $totalAll = $subtotalM + $subtotalF;
                                    echo "<td class='border border-gray-300 font-bold'>{$subtotalM}</td>";
                                    echo "<td class='border border-gray-300 font-bold'>{$subtotalF}</td>";
                                    echo "<td class='border border-gray-300 font-bold'>{$totalAll}</td>";
                                }

                                // List of indicators and sub-rows
                                $rows = [
                                    ["label" => "Orally fit children 12 to 59 months old", "sub" => [
                                        "upon examination" => "Orally fit children 12 to 59 months old - upon examination",
                                        "after rehabilitation" => "Orally fit children 12 to 59 months old - after rehabilitation"
                                    ]],
                                    ["label" => "5 years old and above examined", "sub" => [
                                        "5 years old and above examined" => "5 years old and above examined",
                                        "5 years old and above with cases of DMFT" => "5 years old and above with cases of DMFT"
                                    ]],
                                    ["label" => "Infant (0–11 months old)", "sub" => [
                                        "Infant (0–11 months old)" => "Infant (0–11 months old)"
                                    ]],
                                    ["label" => "Pre-schooler (12–59 months old)", "sub" => [
                                        "Pre-schooler (12–59 months old)" => "Pre-schooler (12–59 months old)"
                                    ]],
                                    ["label" => "Schoolers (5–9 years old)", "sub" => [
                                        "Schoolers (5–9 years old)" => "Schoolers (5–9 years old)"
                                    ]],
                                    ["label" => "Adolescents", "sub" => [
                                        "10–14 years old" => "Adolescents (10–14 years old)",
                                        "15–19 years old" => "Adolescents (15–19 years old)"
                                    ]],
                                    ["label" => "Adult (20–59 years old)", "sub" => [
                                        "Adult (20–59 years old)" => "Adult (20–59 years old)"
                                    ]],
                                    ["label" => "Senior (60+ years old)", "sub" => [
                                        "Senior (60+ years old)" => "Senior (60+ years old)"
                                    ]],
                                    ["label" => "Pregnant women", "sub" => [
                                        "10–14 years old" => "Pregnant (10–14 years old)",
                                        "15–19 years old" => "Pregnant (15–19 years old)",
                                        "20–49 years old" => "Pregnant (20–49 years old)"
                                    ]]
                                ];

                                // Render table rows
                                foreach ($rows as $row) {
                                    // Top-level row (empty but with borders across every column)
                                    echo "<tr>";
                                    echo "<td class='border border-gray-300 px-2 py-1 font-bold'>{$rowNum}</td>";
                                    echo "<td class='border border-gray-300 px-2 py-1 text-left font-bold'>{$row['label']}</td>";

                                    // Render empty bordered cells for M/F columns + subtotal/total
                                    for ($i = 0; $i < count($barangayNames); $i++) {
                                        echo "<td class='border border-gray-300'></td><td class='border border-gray-300'></td>";
                                    }
                                    echo "<td class='border border-gray-300'></td><td class='border border-gray-300'></td><td class='border border-gray-300'></td>";
                                    echo "</tr>";

                                    // Render sub-rows with actual values
                                    foreach ($row['sub'] as $subLabel => $indicatorKey) {
                                        echo "<tr>
                                <td></td>
                                <td class='border border-gray-300 px-2 py-1 text-left font-bold'>$subLabel</td>";
                                        renderRow($data[$indicatorKey], $barangayNames);
                                        echo "</tr>";
                                    }

                                    $rowNum++;
                                }

                                // === GRAND TOTAL ROW - Use pre-calculated totals ===
                                echo "<tr class='font-bold bg-gray-100'><td></td>
                            <td class='border border-gray-300 px-2 py-1 text-left font-bold'>TOTAL</td>";

                                $totalM = $totalF = 0;
                                foreach ($barangayNames as $b) {
                                    $sumM = $totalPatientsByBarangay[$b]['M'] ?? 0;
                                    $sumF = $totalPatientsByBarangay[$b]['F'] ?? 0;

                                    $totalM += $sumM;
                                    $totalF += $sumF;

                                    echo "<td class='border border-gray-300 font-bold'>" . ($sumM > 0 ? $sumM : '') . "</td>";
                                    echo "<td class='border border-gray-300 font-bold'>" . ($sumF > 0 ? $sumF : '') . "</td>";
                                }

                                $totalAll = $totalM + $totalF;
                                echo "<td class='border border-gray-300 font-bold'>{$totalM}</td>";
                                echo "<td class='border border-gray-300 font-bold'>{$totalF}</td>";
                                echo "<td class='border border-gray-300 font-bold'>{$totalAll}</td></tr>";
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        </main>
    </div>

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
        function printReport() {
            // Store original content and body classes
            const originalContent = document.body.innerHTML;
            const originalBodyClasses = document.body.className;

            // Get the print content
            const printContent = document.getElementById('print-content').innerHTML;

            // Create a new window for printing
            const printWindow = window.open('', '_blank', 'width=1200,height=800');

            // Write the print content to the new window
            printWindow.document.write(`
                <!DOCTYPE html>
                <html>
                <head>
                    <title>MHO OHP Report - <?php echo $selectedYear; ?></title>
                    <style>
                        @media print {
                            @page {
                                size: landscape;
                                margin: 10mm;
                            }
                            body {
                                font-family: Arial, sans-serif;
                                margin: 0;
                                padding: 10px;
                                background: white;
                                color: black;
                                line-height: 1.2;
                            }
                            .header {
                                text-align: center;
                                margin-bottom: 10px;
                            }
                            .header p {
                                margin: 2px 0;
                            }
                            .header-title {
                                font-size: 18px;
                                font-weight: bold;
                                margin-bottom: 1px !important;
                            }
                            .header-subtitle {
                                font-size: 16px;
                                font-weight: 600;
                                margin-top: 0 !important;
                                margin-bottom: 2px !important;
                            }
                            .header-date {
                                font-size: 12px;
                                margin-top: 3px !important;
                            }
                            .dentist{
                                margin-top:10px;
                                margin-left:30px;
                                display: flex;
                                flex-direction: row;
                                gap:20px;
                            }
                            .dentist-prep{
                                font-size: 14px;
                                font-weight: 600;
                            }
                            .dentist-name{
                                font-size: 14px;
                                font-weight: 600;
                            }
                            table {
                                width: 100%;
                                border-collapse: collapse;
                                font-size: 10px;
                            }
                            th, td {
                                border: 1px solid #000;
                                padding: 4px 2px;
                                text-align: center;
                            }
                            th {
                                background-color: #f0f0f0;
                                font-weight: bold;
                            }
                            .text-center { text-align: center; }
                            .text-left { text-align: left; }
                            .font-bold { font-weight: bold; }
                            .bg-gray-100 { background-color: #f7fafc; }
                        }
                        @media screen {
                            body { 
                                padding: 20px; 
                                background: white; 
                                color: black; 
                                line-height: 1.2;
                            }
                            .header {
                                text-align: center;
                                margin-bottom: 10px;
                            }
                            .header p {
                                margin: 2px 0;
                            }
                            .header-title {
                                font-size: 18px;
                                font-weight: bold;
                                margin-bottom: 1px !important;
                            }
                            .header-subtitle {
                                font-size: 16px;
                                font-weight: 600;
                                margin-top: 0 !important;
                                margin-bottom: 2px !important;
                            }
                            .header-date {
                                font-size: 12px;
                                margin-top: 3px !important;
                            }

                            .dentist{
                                margin-top:10px;
                                margin-left:30px;
                                display: flex;
                                flex-direction: row;
                                gap:5px;
                            }
                            .dentist-prep{
                                font-size: 14px;
                                font-weight: 600;
                            }
                            .dentist-name{
                                font-size: 14px;
                                font-weight: 600;
                            }
                            table {
                                width: 100%;
                                border-collapse: collapse;
                                font-size: 10px;
                            }
                            th, td {
                                border: 1px solid #000;
                                padding: 4px 2px;
                                text-align: center;
                            }
                            th {
                                background-color: #f0f0f0;
                                font-weight: bold;
                            }
                        }
                    </style>
                </head>
                <body>
                    <div class="header">
                        <p class="header-title">Municipal Health Office - Mamburao</p>
                        <p class="header-subtitle">Oral Health Program - <?php echo $selectedYear; ?> (per brgy)</p>
                    </div>
                    ${printContent.replace('<div class="text-center mb-2">', '<div style="display:none">').replace('</div>', '</div>')}
                    <div class="dentist">
                        <p class="dentist-prep">Prepared by:</p>
                        <p class="dentist-name">BERNADETTE A. SERVANDO / Dentist I</p>
                    </div>
                    <script>
                        window.onload = function() {
                            window.print();
                            setTimeout(function() {
                                window.close();
                            }, 500);
                        }
                    <\/script>
                </body>
                </html>
            `);

            printWindow.document.close();
        }
    </script>
</body>

</html>