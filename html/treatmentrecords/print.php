<?php
session_start();
date_default_timezone_set('Asia/Manila');

// REQUIRE userId parameter for each page
// Example usage: dashboard.php?uid=5
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
// Database connection (same as before)
$servername = "localhost";
$username = "root"; // change if needed
$password = "";     // change if needed
$dbname = "dentalemr_system";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$patient_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Fetch patient
$sql = "SELECT * FROM patients WHERE patient_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$patient = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Fetch other patient info
$sql2 = "SELECT * FROM patient_other_info WHERE patient_id = ?";
$stmt2 = $conn->prepare($sql2);
$stmt2->bind_param("i", $patient_id);
$stmt2->execute();
$other_info = $stmt2->get_result()->fetch_assoc();
$stmt2->close();

// Vital Signs
$sql_vital = "SELECT * FROM vital_signs WHERE patient_id = ? ORDER BY recorded_at DESC LIMIT 1";
$stmt_vital = $conn->prepare($sql_vital);
$stmt_vital->bind_param("i", $patient_id);
$stmt_vital->execute();
$vital = $stmt_vital->get_result()->fetch_assoc();
$stmt_vital->close();

// Medical History
$sql_history = "SELECT * FROM medical_history WHERE patient_id = ?";
$stmt_history = $conn->prepare($sql_history);
$stmt_history->bind_param("i", $patient_id);
$stmt_history->execute();
$history = $stmt_history->get_result()->fetch_assoc();
$stmt_history->close();

// Dietary Habits
$sql_diet = "SELECT * FROM dietary_habits WHERE patient_id = ?";
$stmt_diet = $conn->prepare($sql_diet);
$stmt_diet->bind_param("i", $patient_id);
$stmt_diet->execute();
$diet = $stmt_diet->get_result()->fetch_assoc();
$stmt_diet->close();

// Oral Health Condition (latest 5 records)
$sql_oral = "SELECT * FROM oral_health_condition WHERE patient_id = ? ORDER BY created_at ASC LIMIT 5";
$stmt_oral = $conn->prepare($sql_oral);
$stmt_oral->bind_param("i", $patient_id);
$stmt_oral->execute();
$result_oral = $stmt_oral->get_result();

// Store all records in array
$oralHealthRecords = [];
while ($row = $result_oral->fetch_assoc()) {
    $oralHealthRecords[] = $row;
}
$stmt_oral->close();

$conn->close();

// Ensure we always have 5 indexes (to avoid undefined errors)
for ($i = count($oralHealthRecords); $i < 5; $i++) {
    $oralHealthRecords[$i] = [];
}
?>
<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "dentalemr_system";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$patient_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

$treatmentData = [];
$preventiveData = [];
$dates = []; // <-- Add this

if ($patient_id > 0) {

    // ------------------------------
    // 🦷 FETCH TREATMENT & TOOTH DATA
    // ------------------------------
    $sql = "
        SELECT 
            smc.treatment_code,
            smc.created_at,
            t.fdi_number
        FROM services_monitoring_chart smc
        JOIN teeth t ON smc.tooth_id = t.tooth_id
        WHERE smc.patient_id = ?
        ORDER BY smc.created_at ASC
    ";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        die("Prepare failed: " . $conn->error);
    }
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $dateKey = date('Y-m-d', strtotime($row['created_at']));
        $fdi = $row['fdi_number'];

        if (!isset($treatmentData[$dateKey])) {
            $treatmentData[$dateKey] = [];
        }

        $treatmentData[$dateKey][$fdi] = $row['treatment_code'];

        // Collect unique dates for your table
        if (!in_array($dateKey, $dates)) {
            $dates[] = $dateKey;
        }
    }
    $stmt->close();

    // ------------------------------
    // 🧾 FETCH PREVENTIVE TREATMENTS
    // ------------------------------
    $sql_preventive = "
        SELECT 
            oral_prophylaxis,
            fluoride,
            sealant,
            permanent_filling,
            temporary_filling,
            extraction,
            consultation,
            remarks,
            created_at,
            updated_at
        FROM patient_treatment_record
        WHERE patient_id = ?
        ORDER BY created_at ASC
        LIMIT 20
    ";
    $stmt2 = $conn->prepare($sql_preventive);
    if (!$stmt2) {
        die("Prepare failed: " . $conn->error);
    }
    $stmt2->bind_param("i", $patient_id);
    $stmt2->execute();
    $result2 = $stmt2->get_result();

    while ($row2 = $result2->fetch_assoc()) {
        $preventiveData[] = $row2;
    }
    $stmt2->close();
}

$conn->close();
?>

<!doctype html>
<html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Treatment Records</title>
    <!-- <link href="../css/style.css" rel="stylesheet"> -->
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <style>
        .grid1 {
            display: flex;
            gap: 1.5px;
            justify-content: center;
            /* border: solid black 1px; */
            align-items: center;
        }

        .gridtop {
            display: flex;
            gap: 1.5px;
            justify-content: center;
            align-items: center;
            margin-bottom: 1px;
        }

        .tooth-container {
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .tooth {
            width: 20px;
            height: 20px;
            position: relative;
            cursor: pointer;
        }

        .tooth-label {
            font-size: 8px;
            font-weight: bold;
            text-align: center;
        }

        .label-top {
            margin-bottom: -1px;

        }

        .label-bottom {
            margin-top: -1px;
        }

        .part {
            position: absolute;
            box-sizing: border-box;
            background-color: white;
            border: 1px solid #747474;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 7px;
            font-weight: bold;
            color: #fff;
            user-select: none;
            text-align: center;
        }

        /* left */
        .part-top-left {
            top: 0;
            left: 0;
            width: 50%;
            height: 50%;
            border: 1px solid #555;
            border-top-left-radius: 100%;
            transform: translate(50, 50);
            transform: translateX(-50%) rotate(-45deg);
            margin-top: 4.5px;
            margin-left: 4px;
            font-size: 7px;
            align-items: center;
            text-align: center;
        }

        /* top */
        .part-top-right {
            top: 0;
            right: 0;
            width: 50%;
            height: 50%;
            border: 1px solid #555;
            border-top-right-radius: 100%;
            transform: translateX(-50%) rotate(-45deg);
            margin-top: -1.5px;
            margin-right: -0.9px;
            font-size: 7px;
            align-items: center;
            text-align: center;
        }

        /* bot */
        .part-bottom-left {
            bottom: 0;
            left: 0;
            width: 50%;
            height: 50%;
            border: 1px solid #555;
            border-bottom-left-radius: 100%;
            transform: translateX(-50%) rotate(-45deg);
            margin-bottom: -1px;
            margin-left: 11px;
            font-size: 7px;
            align-items: center;
            text-align: center;
        }

        /* right */
        .part-bottom-right {
            bottom: 0;
            right: 0;
            width: 50%;
            height: 50%;
            border: 1px solid #555;
            border-bottom-right-radius: 100%;
            transform: translateX(-50%) rotate(-45deg);
            margin-bottom: 5.5px;
            margin-right: -7.5px;
            font-size: 7px;
            align-items: center;
            text-align: center;
        }

        .part-center {
            width: 8px;
            height: 8px;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            border-radius: 50%;
            border: 1px solid #555;
            position: absolute;
            margin-left: 1.5px;
            font-size: 7px;
            align-items: center;
            text-align: center;
        }

        .tooth::before {
            transform: rotate(-90deg);
        }

        .tooth::after {
            transform: rotate(-45deg);
        }


        .tooltip {
            visibility: hidden;
            background-color: #222;
            color: #fff;
            padding: 4px 6px;
            border-radius: 4px;
            font-size: 12px;
            position: absolute;
            bottom: 120%;
            left: 50%;
            transform: translateX(-50%);
            white-space: nowrap;
            opacity: 0;
            transition: opacity 0.3s;
            pointer-events: none;
            z-index: 10;
        }

        .tooth:hover .tooltip {
            visibility: visible;
            opacity: 1;
        }

        /* containers (your existing class names) */
        .treatmentbox,
        .treatmentbox1,
        .conditionbox,
        .conditionbox1 {
            display: flex;
            grid-template-columns: repeat(16, 1rem);
            margin: 1.2px;
            /* justify-content: space-between; */
            gap: 2.5px;
            align-items: center;
            /* border: solid 1px black; */
        }

        /* individual boxes (clickable) */
        .treatment-box,
        .treatment1-box,
        .condition-box,
        .condition1-box {
            width: 1.2rem;
            height: 1.2rem;
            display: flex;
            text-align: center;
            align-items: center;
            justify-content: center;
            border: 1px solid black;
            font-size: 1rem;
            font-weight: bold;
            cursor: pointer;
            user-select: none;
            background: #fff;
            color: #000;
        }

        /* for condition boxes we will allow JS to set colors on the element */
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
        <main class="p-4  h-auto pt-13">
            <div class="flex items-center justify-between   w-full sticky top-13 bg-gray-50  z-50">
                <!-- Back Btn-->
                <div class="relative group inline-block ">
                    <a href="" id="viewinfoLink" class="cursor-pointer">
                        <svg class="w-[35px] h-[35px] text-blue-800 dark:text-white" aria-hidden="true"
                            xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"
                                stroke-width="2.5" d="M5 12h14M5 12l4-4m-4 4 4 4" />
                        </svg>
                    </a>
                    <!-- Tooltip -->
                    <span class="absolute left-1/2 -translate-x-1/2  hidden group-hover:block 
                             bg-gray-100/50 text-gray-900 text-sm px-2 py-1 rounded-sm shadow-sm whitespace-nowrap">
                        Go back
                    </span>
                </div>
                <!-- Print Btn -->
                <div class="">
                    <button id="printBtn" onclick="printAllPages()"
                        class="text-white items-center cursor-pointer flex flex-row justify-center gap bg-blue-700 hover:bg-blue-800 font-medium rounded-sm text-xs px-1.5 py-1  dark:bg-primary-600 dark:hover:bg-primary-700 focus:outline-none dark:focus:ring-primary-800">
                        <svg class="w-5 h-4 text-primary-800 dark:text-white" aria-hidden="true"
                            xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor"
                            viewBox="0 0 24 24">
                            <path fill-rule="evenodd"
                                d="M8 3a2 2 0 0 0-2 2v3h12V5a2 2 0 0 0-2-2H8Zm-3 7a2 2 0 0 0-2 2v5a2 2 0 0 0 2 2h1v-4a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v4h1a2 2 0 0 0 2-2v-5a2 2 0 0 0-2-2H5Zm4 11a1 1 0 0 1-1-1v-4h8v4a1 1 0 0 1-1 1H9Z"
                                clip-rule="evenodd" />
                        </svg>
                        Print
                    </button>
                </div>
            </div>
            <div id="shouldprint" class="grid grid-cols-2 items-center w-full">
                <!-- left -->
                <div class="flex flex-col w-full pr-20 ">
                    <!-- Medical History -->
                    <div class="flex flex-col w-full mt-2">
                        <div class="flex flex-row w-full items-end gap-1">
                            <input type="text" value="<?= ($history['prev_hospitalization_flag'] ?? 0) == 1 ? '✓' : '' ?>" readonly class="block text-xs text-center text-gray-900 bg-transparent appearance-none !w-5 h-5 
                                    !border-0 !border-b !border-gray-900 focus:!outline-none focus:!ring-0 focus:!border-b-2 focus:!border-gray-700">
                            <p class="text-[12px]">History of Previous Hospitalization</p>
                        </div>
                        <div class="flex flex-row w-[95%] items-end ml-6">
                            <p class="w-[640px] text-[12px]">Medical (Last Admission & Cause)</p>
                            <input type="text" value="<?= htmlspecialchars($history['last_admission_date'] ?? '') ?>" readonly class="block text-xs text-center text-gray-900 bg-transparent appearance-none w-full h-5 
                                    !border-0 !border-b !border-gray-900 focus:!outline-none focus:!ring-0 focus:!border-b-2 focus:!border-gray-700">
                            <input type="text" value="<?= htmlspecialchars($history['admission_cause'] ?? '') ?>" readonly class="block text-xs text-center text-gray-900 bg-transparent appearance-none w-full h-5 
                                    !border-0 !border-b !border-gray-900 focus:!outline-none focus:!ring-0 focus:!border-b-2 focus:!border-gray-700">
                        </div>
                        <div class="flex flex-row w-[95%] items-end ml-6">
                            <p class="w-[200px] text-[12px]">Surgical (Post-Operative)</p>
                            <input type="text" value="<?= htmlspecialchars($history['surgery_details'] ?? '') ?>" readonly class="block text-xs text-center text-gray-900 bg-transparent appearance-none w-full h-5 
                                    !border-0 !border-b !border-gray-900 focus:!outline-none focus:!ring-0 focus:!border-b-2 focus:!border-gray-700">
                        </div>
                        <div class="flex flex-row w-full items-end gap-1">
                            <input type="text" value="<?= ($history['blood_transfusion_flag'] ?? 0) == 1 ? '✓' : '' ?>" readonly class="block text-xs text-center text-gray-900 bg-transparent appearance-none !w-5 h-5 
                                    !border-0 !border-b !border-gray-900 focus:!outline-none focus:!ring-0 focus:!border-b-2 focus:!border-gray-700">
                            <p class="w-[370px] text-[12px]">Blood transfusion (Month & Year)</p>
                            <input type="text" value="<?= htmlspecialchars($history['blood_transfusion'] ?? '') ?>" readonly class="block text-xs text-center text-gray-900 bg-transparent appearance-none w-full h-5 
                                    !border-0 !border-b !border-gray-900 focus:!outline-none focus:!ring-0 focus:!border-b-2 focus:!border-gray-700">
                        </div>
                        <div class="flex flex-row w-full items-end gap-1">
                            <input type="text" value="<?= ($history['tattoo'] ?? 0) == 1 ? '✓' : '' ?>" readonly class="block text-xs text-center text-gray-900 bg-transparent appearance-none !w-5 h-5 
                                    !border-0 !border-b !border-gray-900 focus:!outline-none focus:!ring-0 focus:!border-b-2 focus:!border-gray-700">
                            <p class="w-[260px] text-[12px]">Tattoo</p>
                        </div>
                        <div class="flex flex-row w-fullitems-end gap-1">
                            <input type="text" value="<?= ($history['other_conditions_flag'] ?? 0) == 1 ? '✓' : '' ?>" readonly class="block text-xs text-center text-gray-900 bg-transparent appearance-none !w-5 h-5 
                                    !border-0 !border-b !border-gray-900 focus:!outline-none focus:!ring-0 focus:!border-b-2 focus:!border-gray-700">
                            <p class="w-[190px] text-[12px]">Others (Please specify)</p>
                            <input type="text" value="<?= htmlspecialchars($history['other_conditions'] ?? '') ?>" readonly class="block text-xs text-center text-gray-900 bg-transparent appearance-none w-full h-5 
                                    !border-0 !border-b !border-gray-900 focus:!outline-none focus:!ring-0 focus:!border-b-2 focus:!border-gray-700">
                        </div>
                    </div>
                    <!-- Dieatray Habits -->
                    <div class="flex flex-col  mt-2">
                        <h4 class="text-[15px] font-bold mt-1">Dietary Habits / Social History</h4>
                        <div class="flex flex-row w-full items-end gap-1">
                            <input type="text" value="<?= ($diet['sugar_flag'] ?? 0) == 1 ? '✓' : '' ?>" readonly class="block text-xs text-center text-gray-900 bg-transparent appearance-none !w-5 h-5 
                                    !border-0 !border-b !border-gray-900 focus:!outline-none focus:!ring-0 focus:!border-b-2 focus:!border-gray-700">
                            <p class="w-[100%] text-[12px]">Sugar Sweetened Beverages/Food (Amount, frequency & Duration)</p>
                            <input type="text" value="<?= htmlspecialchars($diet['sugar_details'] ?? '') ?>" readonly class="block text-xs text-center text-gray-900 bg-transparent appearance-none w-[120px] h-5 
                                    !border-0 !border-b !border-gray-900 focus:!outline-none focus:!ring-0 focus:!border-b-2 focus:!border-gray-700">
                        </div>
                        <div class="flex flex-row w-full items-end gap-1">
                            <input type="text" value="<?= ($diet['alcohol_flag'] ?? 0) == 1 ? '✓' : '' ?>" readonly class="block text-xs text-center text-gray-900 bg-transparent appearance-none !w-5 h-5 
                                    !border-0 !border-b !border-gray-900 focus:!outline-none focus:!ring-0 focus:!border-b-2 focus:!border-gray-700">
                            <p class="w-[90%] text-[12px]">Use of Alcohol (Amount, Frequency & Duration)</p>
                            <input type="text" value="<?= htmlspecialchars($diet['alcohol_details'] ?? '') ?>" readonly class="block text-xs text-center text-gray-900 bg-transparent appearance-none w-[400px] h-5 
                                    !border-0 !border-b !border-gray-900 focus:!outline-none focus:!ring-0 focus:!border-b-2 focus:!border-gray-700">
                        </div>
                        <div class="flex flex-row w-full items-end gap-1">
                            <input type="text" value="<?= ($diet['tobacco_flag'] ?? 0) == 1 ? '✓' : '' ?>" readonly class="block text-xs text-center text-gray-900 bg-transparent appearance-none !w-5 h-5 
                                    !border-0 !border-b !border-gray-900 focus:!outline-none focus:!ring-0 focus:!border-b-2 focus:!border-gray-700">
                            <p class="w-[90%] text-[12px]">Use of Tobacco (Amount, Frequency & Duration)</p>
                            <input type="text" value="<?= htmlspecialchars($diet['tobacco_details'] ?? '') ?>" readonly class="block text-xs text-center text-gray-900 bg-transparent appearance-none !w-[370px] h-5 
                                    !border-0 !border-b !border-gray-900 focus:!outline-none focus:!ring-0 focus:!border-b-2 focus:!border-gray-700">
                        </div>
                        <div class="flex flex-row w-full items-end gap-1">
                            <input type="text" value="<?= ($diet['betel_nut_flag'] ?? 0) == 1 ? '✓' : '' ?>" readonly class="block text-xs text-center text-gray-900 bg-transparent appearance-none !w-5 h-5 
                                    !border-0 !border-b !border-gray-900 focus:!outline-none focus:!ring-0 focus:!border-b-2 focus:!border-gray-700">
                            <p class="w-[90%] text-[12px]">Betel Nut Chewing (Amount, Frequency & Duration)</p>
                            <input type="text" value="<?= htmlspecialchars($diet['betel_nut_details'] ?? '') ?>" readonly class="block text-xs text-center text-gray-900 bg-transparent appearance-none !w-[320px] h-5 
                                    !border-0 !border-b !border-gray-900 focus:!outline-none focus:!ring-0 focus:!border-b-2 focus:!border-gray-700">
                        </div>
                    </div>
                    <!-- Conforme -->
                    <div class="flex flex-col mt-2">
                        <div class="flex flex-row items-end w-full">
                            <h4 class="text-[10px] font-bold italic mt-1 mr-8">Conforme:</h4>
                            <div class="flex flex-row w-90 items-end gap-1">
                                <input type="text" class="block text-xs text-center text-gray-900 bg-transparent appearance-none !w-[70%] h-5 
                                    !border-0 !border-b !border-gray-9  00 focus:!outline-none focus:!ring-0 focus:!border-b-2 focus:!border-gray-700">
                            </div>
                        </div>
                        <p class="ml-24 text-[12px]">Patient's / Guardian's Name and Signature</p>
                    </div>
                    <!-- Oral Healt Condition -->
                    <div class="flex flex-col mt-2">
                        <h4 class="text-[15px] font-bold mt-2 text-center">Oral Health Condition</h4>
                        <h4 class="text-[12px] ml-1">A. Check (✓) if present (✗) if absent</h4>
                        <div class="flex flex-col w-full">

                            <!-- Date of Oral Examination -->
                            <div class="flex flex-row w-full items-center justify-between border">
                                <p class="px-1 text-[11px] w-[80%]">Date of Oral Examination</p>
                                <?php for ($i = 0; $i < 5; $i++): ?>
                                    <input type="text" class="h-5 w-[30%] border-0 border-l-1 text-[12px] text-center"
                                        value="<?php echo !empty($oralHealthRecords[$i]['created_at']) ? date('m/d/Y', strtotime($oralHealthRecords[$i]['created_at'])) : ''; ?>">
                                <?php endfor; ?>
                            </div>

                            <!-- Orally Fit Child (OFC) -->
                            <div class="flex flex-row w-full items-center justify-between border border-t-0">
                                <p class="px-1 text-[11px] w-[80%]">Orally Fit Child (OFC)</p>
                                <?php for ($i = 0; $i < 5; $i++): ?>
                                    <input type="text" class="h-5 w-[30%] border-0 border-l-1 text-[12px] text-center"
                                        value="<?php echo !empty($oralHealthRecords[$i]) ? (!empty($oralHealthRecords[$i]['orally_fit_child']) ? '✓' : '✗') : ''; ?>">
                                <?php endfor; ?>
                            </div>

                            <!-- Other Fields -->
                            <?php
                            $fields = [
                                'Dental Caries' => 'dental_caries',
                                'Gingivitis' => 'gingivitis',
                                'Periodontal Disease' => 'periodontal_disease',
                                'Debris' => 'debris',
                                'Calculus' => 'calculus',
                                'Abnormal Growth' => 'abnormal_growth',
                                'Cleft Lip/ Palate' => 'cleft_palate',
                                'Others' => 'others',
                            ];
                            ?>
                            <?php foreach ($fields as $label => $field): ?>
                                <div class="flex flex-row w-full items-center justify-between border border-t-0">
                                    <p class="px-1 text-[11px] w-[80%] <?php echo $field === 'others' ? 'flex flex-col' : ''; ?>">
                                        <?php if ($field === 'others'): ?>
                                            <span class="text-[11px]">Others</span>
                                            <span class="text-[10px]">(sernumerary/mesiodens,</span>
                                            <span class="text-[10px]">malocclusions, etc.)</span>
                                        <?php else: ?>
                                            <?php echo $label; ?>
                                        <?php endif; ?>
                                    </p>

                                    <?php for ($i = 0; $i < 5; $i++): ?>
                                        <input type="text"
                                            class="<?php echo $field === 'others' ? 'h-[47px]' : 'h-5'; ?> text-[12px] text-center w-[30%] border-0 border-l-1"
                                            value="<?php echo !empty($oralHealthRecords[$i][$field]) ? $oralHealthRecords[$i][$field] : ''; ?>">
                                    <?php endfor; ?>
                                </div>
                            <?php endforeach; ?>

                        </div>

                        <!-- Set B -->
                        <h4 class="text-[12px] ml-1 mt-2">B. Indicate Number</h4>
                        <div class="flex flex-col w-full">

                            <?php
                            // Fields for permanent and temporary teeth
                            $numberFields = [
                                'No. of Perm. Teeth Present' => 'perm_teeth_present',
                                'No. of Perm. Sound Teeth' => 'perm_sound_teeth',
                                'No. of Decayed Teeth (D)' => 'perm_decayed_teeth_d',
                                'No. of Missing Teeth (M)' => 'perm_missing_teeth_m',
                                'No. of Filled Teeth (F)' => 'perm_filled_teeth_f',
                                'Total DMF Teeth' => 'perm_total_dmf',
                                'No. of Temp. Teeth Present' => 'temp_teeth_present',
                                'No. of Temp. Sound teeth' => 'temp_sound_teeth',
                                'No. of Decayed Teeth (d)' => 'temp_decayed_teeth_d',
                                'No. of Filled Teeth (f)' => 'temp_filled_teeth_f',
                                'Total df Teeth' => 'temp_total_df',
                            ];
                            ?>

                            <?php foreach ($numberFields as $label => $field): ?>
                                <div class="flex flex-row w-full items-center justify-between border <?php echo $label !== 'No. of Perm. Teeth Present' ? 'border-t-0' : ''; ?>">
                                    <p class="px-1 text-[11px] w-[80%]"><?php echo $label; ?></p>
                                    <?php for ($i = 0; $i < 5; $i++): ?>
                                        <input type="text" class="h-5 w-[30%] text-[12px] text-center border-0 border-l-1"
                                            value="<?php echo !empty($oralHealthRecords[$i][$field]) ? $oralHealthRecords[$i][$field] : ''; ?>">
                                    <?php endfor; ?>
                                </div>
                            <?php endforeach; ?>

                        </div>

                    </div>
                </div>
                <!-- right -->
                <div class="flex flex-col w-full  pl-10">
                    <!-- Header Section -->
                    <div class="flex flex-row w-fulljustify-between gap-10">
                        <div class="flex flex-col items-center w-full">
                            <div class="flex flex-row w-full justify-end mb-3">
                                <div class="flex flex-row items-end w-15">
                                    <p class="w-[65%] text-[10px] font-medium">File No.</p>
                                    <input type="text" class="block text-xs text-center text-gray-900 bg-transparent appearance-none w-[35%] h-5 
                                        !border-0 !border-b !border-gray-900 focus:!outline-none focus:!ring-0 focus:!border-b-2 focus:!border-gray-700">
                                </div>
                            </div>
                            <div class="flex items-start justify-between w-full">
                                <img src="../../img/DOH Logo.png" alt="DOH Logo" class="h-15 rounded-full mr-5">
                                <div class="flex flex-col items-center text-center mt-1">
                                    <h1 class="text-[13px] font-bold ">Republic of the Philippines</h1>
                                    <h2 class="text-[13px] font-bold -mt-1">Department of Health</h2>
                                    <h2 class="text-[13px] font-bold -mt-1">Regional Office III</h2>
                                    <input type="text" readonly class="block text-xs text-center text-gray-900 bg-transparent appearance-none !w-[70%] h-3 
                                        !border-0 !border-b !border-gray-900 focus:!outline-none focus:!ring-0 focus:!border-b-2 focus:!border-gray-700">
                                    <h3 class="text-[8px]">(Municipality/City/Province)</h3>
                                </div>
                                <img src="../../img/DOHDentalLogo-removebg-preview.png" alt="Dental Logo" class="w-30 -mr-6 -ml-6 h-15">
                            </div>
                            <h4 class="text-[15px] font-bold mt-1">Individual Patient Treatment Record</h4>

                        </div>
                        <div class="border h-10 w-35 mt-4"></div>
                    </div>
                    <div class="w-[88%]">
                        <!-- Patient Info -->
                        <div class="flex flex-col mt-2">
                            <div class="flex flex-col">
                                <div class="flex flex-row  w-full items-end justify-between">
                                    <p class="w-[120px] text-[12px]">Name</p>
                                    <div class="flex flex-col">
                                        <div class="flex flex-row">
                                            <input type="text" value="<?= htmlspecialchars($patient['surname'] ?? '') ?>" class="block text-xs text-center text-gray-900 bg-transparent appearance-none w-full h-5 
                                                !border-0 !border-b !border-gray-900 focus:!outline-none focus:!ring-0 focus:!border-b-2 focus:!border-gray-700">
                                            <input type="text" value="<?= htmlspecialchars($patient['firstname'] ?? '') ?>" class="block text-xs text-center text-gray-900 bg-transparent appearance-none w-full h-5 
                                                !border-0 !border-b !border-gray-900 focus:!outline-none focus:!ring-0 focus:!border-b-2 focus:!border-gray-700">
                                            <input type="text" value="<?= htmlspecialchars($patient['middlename'] ?? '') ?>" class="block text-xs text-center text-gray-900 bg-transparent appearance-none w-full h-5 
                                                !border-0 !border-b !border-gray-900 focus:!outline-none focus:!ring-0 focus:!border-b-2 focus:!border-gray-700">
                                        </div>
                                    </div>
                                </div>
                                <div class="flex flex-row ml-[97px] justify-between mt-[-1px]">
                                    <p class="w-full text-center text-[10px] font-medium">Surname</p>
                                    <p class="w-full text-center text-[10px] font-medium">First Name</p>
                                    <p class="w-full text-center text-[10px] font-medium">Middle Initial</p>
                                </div>
                            </div>
                            <div class="flex flex-row w-full items-end ">
                                <p class="w-[124px] text-[12px]">Date of Birth</p>
                                <input type="text" value="<?= htmlspecialchars($patient['date_of_birth'] ?? '') ?>" class="block text-xs text-center text-gray-900 bg-transparent appearance-none w-full h-5 
                                    !border-0 !border-b !border-gray-900 focus:!outline-none focus:!ring-0 focus:!border-b-2 focus:!border-gray-700">
                            </div>
                            <div class="flex flex-row w-full items-end ">
                                <p class="w-[124px] text-[12px]">Place of Birth</p>
                                <input type="text" value="<?= htmlspecialchars($patient['place_of_birth'] ?? '') ?>" class="block text-xs text-center text-gray-900 bg-transparent appearance-none w-full h-5 
                                    !border-0 !border-b !border-gray-900 focus:!outline-none focus:!ring-0 focus:!border-b-2 focus:!border-gray-700">
                            </div>
                            <div class="flex flex-row w-full items-end ">
                                <div class="flex flex-row w-full items-end mr-1">
                                    <p class="w-[126px] text-[12px] mr-2">Address</p>
                                    <input type="text" value="<?= htmlspecialchars($patient['address'] ?? '') ?>" class="block text-xs text-center text-gray-900 bg-transparent appearance-none w-full h-5 
                                    !border-0 !border-b !border-gray-900 focus:!outline-none focus:!ring-0 focus:!border-b-2 focus:!border-gray-700">
                                </div>
                                <div class="flex flex-row w-20 gap-1 items-end ">
                                    <p class=" text-[12px]">Age</p>
                                    <input type="text" value="<?= htmlspecialchars($patient['age'] ?? '') ?>" class="block text-xs text-center text-gray-900 bg-transparent appearance-none w-10 h-5 
                                    !border-0 !border-b !border-gray-900 focus:!outline-none focus:!ring-0 focus:!border-b-2 focus:!border-gray-700">
                                </div>
                                <div class="flex flex-row w-20 gap-1 items-end ">
                                    <p class=" text-[12px]">Sex</p>
                                    <input type="text" value="<?= htmlspecialchars($patient['sex'] ?? '') ?>" class="block text-xs text-center text-gray-900 bg-transparent appearance-none w-10 h-5 
                                    !border-0 !border-b !border-gray-900 focus:!outline-none focus:!ring-0 focus:!border-b-2 focus:!border-gray-700">
                                </div>
                            </div>
                            <div class="flex flex-row w-full items-end ">
                                <p class="w-[124px] text-[12px]">Occupation</p>
                                <input type="text" value="<?= htmlspecialchars($patient['occupation'] ?? '') ?>" class="block text-xs text-center text-gray-900 bg-transparent appearance-none w-full h-5 
                                    !border-0 !border-b !border-gray-900 focus:!outline-none focus:!ring-0 focus:!border-b-2 focus:!border-gray-700">
                            </div>
                            <div class="flex flex-row w-full gap-2 items-end ">
                                <p class="w-24 text-[12px]">Parent/Guardian</p>
                                <input type="text" value="<?= htmlspecialchars($patient['guardian'] ?? '') ?>" class="block text-xs text-center text-gray-900 bg-transparent appearance-none w-full h-5 
                                    !border-0 !border-b !border-gray-900 focus:!outline-none focus:!ring-0 focus:!border-b-2 focus:!border-gray-700">
                            </div>
                        </div>

                        <!-- Other Patient Information -->
                        <div class="flex flex-col mt-2">
                            <h4 class="text-[15px] font-bold mt-1">Other Patient Information (Membership)</h4>
                            <div class="flex flex-row w-full items-end gap-1">
                                <input type="text" value="<?= ($other_info['nhts_pr'] ?? 0) == 1 ? '✓' : '' ?>" readonly class="block text-xs text-center text-gray-900 bg-transparent appearance-none !w-5 h-5 
                                    !border-0 !border-b !border-gray-900 focus:!outline-none focus:!ring-0 focus:!border-b-2 focus:!border-gray-700">
                                <p class="text-[12px]">National household Targeting System - Poverty Reduction (NHTS-PR)</p>
                            </div>
                            <div class="flex flex-row w-full items-end gap-1">
                                <input type="text" value="<?= ($other_info['four_ps'] ?? 0) == 1 ? '✓' : '' ?>" readonly class="block text-xs text-center text-gray-900 bg-transparent appearance-none !w-5 h-5 
                                    !border-0 !border-b !border-gray-900 focus:!outline-none focus:!ring-0 focus:!border-b-2 focus:!border-gray-700">
                                <p class="text-[12px]">Pantawid Pamilyang Pilipino Program (4Ps)</p>
                            </div>
                            <div class="flex flex-row w-full items-end gap-1">
                                <input type="text" value="<?= ($other_info['indigenous_people'] ?? 0) == 1 ? '✓' : '' ?>" readonly class="block text-xs text-center text-gray-900 bg-transparent appearance-none !w-5 h-5 
                                    !border-0 !border-b !border-gray-900 focus:!outline-none focus:!ring-0 focus:!border-b-2 focus:!border-gray-700">
                                <p class="text-[12px]">Indigenous People (IP)</p>
                            </div>
                            <div class="flex flex-row w-full items-end gap-1">
                                <input type="text" value="<?= ($other_info['pwd'] ?? 0) == 1 ? '✓' : '' ?>" readonly class="block text-xs text-center text-gray-900 bg-transparent appearance-none !w-5 h-5 
                                    !border-0 !border-b !border-gray-900 focus:!outline-none focus:!ring-0 focus:!border-b-2 focus:!border-gray-700">
                                <p class="text-[12px]">Person With Disabilities (PWDs)</p>
                            </div>
                            <div class="flex flex-row w-full items-end gap-1">
                                <input type="text" value="<?= ($other_info['philhealth_flag'] ?? 0) == 1 ? '✓' : '' ?>" readonly class="block text-xs text-center text-gray-900 bg-transparent appearance-none !w-5 h-5 
                                    !border-0 !border-b !border-gray-900 focus:!outline-none focus:!ring-0 focus:!border-b-2 focus:!border-gray-700">
                                <p class="w-[300px] text-[12px]">PhiliHealth (Indicate Number)</p>
                                <input type="text" value="<?= htmlspecialchars($other_info['philhealth_number'] ?? '') ?>" readonly class="block text-xs text-center text-gray-900 bg-transparent appearance-none w-full h-5 
                                    !border-0 !border-b !border-gray-900 focus:!outline-none focus:!ring-0 focus:!border-b-2 focus:!border-gray-700">
                            </div>
                            <div class="flex flex-row w-full items-end gap-1">
                                <input type="text" value="<?= ($other_info['sss_flag'] ?? 0) == 1 ? '✓' : '' ?>" readonly class="block text-xs text-center text-gray-900 bg-transparent appearance-none !w-5 h-5 
                                    !border-0 !border-b !border-gray-900 focus:!outline-none focus:!ring-0 focus:!border-b-2 focus:!border-gray-700">
                                <p class="w-[300px] text-[12px]">SSS (Indicate Number)</p>
                                <input type="text" value="<?= htmlspecialchars($other_info['sss_number'] ?? '') ?>" readonly class="block text-xs text-center text-gray-900 bg-transparent appearance-none w-full h-5 
                                    !border-0 !border-b !border-gray-900 focus:!outline-none focus:!ring-0 focus:!border-b-2 focus:!border-gray-700">
                            </div>
                            <div class="flex flex-row w-full items-end gap-1">
                                <input type="text" value="<?= ($other_info['gsis_flag'] ?? 0) == 1 ? '✓' : '' ?>" readonly class="block text-xs text-center text-gray-900 bg-transparent appearance-none !w-5 h-5 
                                    !border-0 !border-b !border-gray-900 focus:!outline-none focus:!ring-0 focus:!border-b-2 focus:!border-gray-700">
                                <p class="w-[300px] text-[12px]">GSIS (Indicate Number)</p>
                                <input type="text" value="<?= htmlspecialchars($other_info['gsis_number'] ?? '') ?>" readonly class="block text-xs text-center text-gray-900 bg-transparent appearance-none w-full h-5 
                                    !border-0 !border-b !border-gray-900 focus:!outline-none focus:!ring-0 focus:!border-b-2 focus:!border-gray-700">
                            </div>
                        </div>
                        <!-- Vital Signs -->
                        <div class="flex flex-col mt-2">
                            <h4 class="text-[15px] font-bold mt-1">Vital Signs</h4>
                            <div class="flex flex- w-full justify-between">
                                <div class="flex flex-row w-full items-end ">
                                    <p class="w-[95px] text-[12px]">Blood Presseure:</p>
                                    <input type="text" value="<?= htmlspecialchars($vital['blood_pressure'] ?? '') ?>" readonly class="block text-xs text-center text-gray-900 bg-transparent appearance-none !w-23 h-5 
                                    !border-0 !border-b !border-gray-900 focus:!outline-none focus:!ring-0 focus:!border-b-2 focus:!border-gray-700">
                                </div>
                                <div class="flex flex-row  items-end">
                                    <p class="w-[70px] text-[12px]">Pulse Rate:</p>
                                    <input type="text" value="<?= htmlspecialchars($vital['pulse_rate'] ?? '') ?>" readonly class="block text-xs text-center text-gray-900 bg-transparent appearance-none !w-30 h-5 
                                    !border-0 !border-b !border-gray-900 focus:!outline-none focus:!ring-0 focus:!border-b-2 focus:!border-gray-700">
                                </div>
                            </div>
                            <div class="flex flex-row w-full items-end ">
                                <p class="w-[75px] text-[12px]">Temperature:</p>
                                <input type="text" value="<?= htmlspecialchars($vital['temperature'] ?? '') ?>" readonly class="block text-xs text-center text-gray-900 bg-transparent appearance-none !w-28 h-5 
                                    !border-0 !border-b !border-gray-900 focus:!outline-none focus:!ring-0 focus:!border-b-2 focus:!border-gray-700">
                            </div>
                        </div>

                        <!-- Medical History -->
                        <div class="flex flex-col mt-2">
                            <h4 class="text-[15px] font-bold mt-1">Medical History</h4>
                            <div class="flex flex-row w-full items-end gap-1">
                                <input type="text" value="<?= ($history['allergies_flag'] ?? 0) == 1 ? '✓' : '' ?>" readonly class="block text-xs text-center text-gray-900 bg-transparent appearance-none !w-5 h-5 
                                    !border-0 !border-b !border-gray-900 focus:!outline-none focus:!ring-0 focus:!border-b-2 focus:!border-gray-700">
                                <p class="w-[220px] text-[12px]">Allergies (Please specify)</p>
                                <input type="text" value="<?= htmlspecialchars($history['allergies_details'] ?? '') ?>" readonly class="block text-xs text-center text-gray-900 bg-transparent appearance-none w-full h-5 
                                    !border-0 !border-b !border-gray-900 focus:!outline-none focus:!ring-0 focus:!border-b-2 focus:!border-gray-700">
                            </div>
                            <div class="flex flex-row w-full items-end gap-1">
                                <input type="text" value="<?= ($history['hypertension_cva'] ?? 0) == 1 ? '✓' : '' ?>" readonly class="block text-xs text-center text-gray-900 bg-transparent appearance-none !w-5 h-5 
                                    !border-0 !border-b !border-gray-900 focus:!outline-none focus:!ring-0 focus:!border-b-2 focus:!border-gray-700">
                                <p class="text-[12px]">Hypertension / CVA</p>
                            </div>
                            <div class="flex flex-row w-full items-end gap-1">
                                <input type="text" value="<?= ($history['diabetes_mellitus'] ?? 0) == 1 ? '✓' : '' ?>" readonly class="block text-xs text-center text-gray-900 bg-transparent appearance-none !w-5 h-5 
                                    !border-0 !border-b !border-gray-900 focus:!outline-none focus:!ring-0 focus:!border-b-2 focus:!border-gray-700">
                                <p class="text-[12px]">Diabetes Mellitus</p>
                            </div>
                            <div class="flex flex-row w-full items-end gap-1">
                                <input type="text" value="<?= ($history['blood_disorders'] ?? 0) == 1 ? '✓' : '' ?>" readonly class="block text-xs text-center text-gray-900 bg-transparent appearance-none !w-5 h-5 
                                    !border-0 !border-b !border-gray-900 focus:!outline-none focus:!ring-0 focus:!border-b-2 focus:!border-gray-700">
                                <p class="text-[12px]">Blood Siaorders</p>
                            </div>
                            <div class="flex flex-row w-full items-end gap-1">
                                <input type="text" value="<?= ($history['heart_disease'] ?? 0) == 1 ? '✓' : '' ?>" readonly class="block text-xs text-center text-gray-900 bg-transparent appearance-none !w-5 h-5 
                                    !border-0 !border-b !border-gray-900 focus:!outline-none focus:!ring-0 focus:!border-b-2 focus:!border-gray-700">
                                <p class="text-[12px]">Cardiovascular / Heart Diseases</p>
                            </div>
                            <div class="flex flex-row w-full items-end gap-1">
                                <input type="text" value="<?= ($history['thyroid_disorders'] ?? 0) == 1 ? '✓' : '' ?>" readonly class="block text-xs text-center text-gray-900 bg-transparent appearance-none !w-5 h-5 
                                    !border-0 !border-b !border-gray-900 focus:!outline-none focus:!ring-0 focus:!border-b-2 focus:!border-gray-700">
                                <p class="text-[12px]">Thyroid Disorders</p>
                            </div>
                            <div class="flex flex-row w-full items-end gap-1">
                                <input type="text" value="<?= ($history['hepatitis_flag'] ?? 0) == 1 ? '✓' : '' ?>" readonly class="block text-xs text-center text-gray-900 bg-transparent appearance-none !w-5 h-5 
                                    !border-0 !border-b !border-gray-900 focus:!outline-none focus:!ring-0 focus:!border-b-2 focus:!border-gray-700">
                                <p class="w-[300px] text-[12px]">Hepatitis (Please specify type)</p>
                                <input type="text" value="<?= htmlspecialchars($history['hepatitis_details'] ?? '') ?>" readonly class="block text-xs text-center text-gray-900 bg-transparent appearance-none w-full h-5 
                                    !border-0 !border-b !border-gray-900 focus:!outline-none focus:!ring-0 focus:!border-b-2 focus:!border-gray-700">
                            </div>
                            <div class="flex flex-row w-full items-end gap-1">
                                <input type="text" value="<?= ($history['malignancy_flag'] ?? 0) == 1 ? '✓' : '' ?>" readonly class="block text-xs text-center text-gray-900 bg-transparent appearance-none !w-5 h-5 
                                    !border-0 !border-b !border-gray-900 focus:!outline-none focus:!ring-0 focus:!border-b-2 focus:!border-gray-700">
                                <p class="w-[260px] text-[12px]">Malignancy (Please specify)</p>
                                <input type="text" value="<?= htmlspecialchars($history['malignancy_details'] ?? '') ?>" readonly class="block text-xs text-center text-gray-900 bg-transparent appearance-none w-full h-5 
                                    !border-0 !border-b !border-gray-900 focus:!outline-none focus:!ring-0 focus:!border-b-2 focus:!border-gray-700">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div id="shouldprint2" class="flex flex-col mt-5 py-10 justify-between w-full">
                <h4 class="text-[15px] font-bold mt-1">A. Oral Health Condition</h4>
                <div class="flex flex-row mt-2 gap-5  justify-between w-full">
                    <!-- left -->
                    <div class="grid grid-cols-2 gap-10 mr-5 w-full  px-1">
                        <!-- YEAR 1 -->
                        <div id="year1" class="flex flex-col">
                            <p
                                class="text-[12px] -ml-1 w-full font-semibold text-gray-900 dark:text-white">Year I - Date</p>
                            <div class="w-80">
                                <p
                                    class="text-[10px] mb-[-1.5px] w-20 font-normal text-gray-900 dark:text-white">Treatment</p>
                                <div class="treatmentbox" id="treatRow1"></div>
                                <div class="conditionbox" id="treatRow2"></div>
                                <p style="margin-bottom: -10px; margin-top: -3.5px;"
                                    class="text-[10px] w-20 font-normal text-gray-900 dark:text-white">Condition</p>
                            </div>

                            <div class="w-80">
                                <div class="gridtop" id="permanentGridtop"></div>
                                <div class="grid1" id="permanentGridbot"></div>
                                <div class="gridtop" id="temporaryGridtop"></div>
                                <div class="grid1" id="temporaryGridbot"></div>
                            </div>

                            <div class="w-80">
                                <p style="margin-top: -10px; margin-bottom: -3.3px;"
                                    class="text-[10px] w-20 font-normal text-gray-900 dark:text-white">Condition</p>
                                <div class="conditionbox1" id="treatRow3"></div>
                                <div class="treatmentbox1" id="treatRow4"></div>
                                <p style="margin-top: -3px;"
                                    class="text-[10px] w-20 font-normal text-gray-900 dark:text-white">Treatment</p>
                            </div>
                        </div>

                        <!-- YEAR 2 -->
                        <div id="year2" class="flex flex-col">
                            <p
                                class="text-[12px] -ml-1 w-full font-semibold text-gray-900 dark:text-white">Year II - Date</p>
                            <div class="w-80">
                                <p
                                    class="text-[10px] mb-[-1.5px] w-20 font-normal text-gray-900 dark:text-white">Treatment</p>
                                <div class="treatmentbox" id="treatRow1_y2"></div>
                                <div class="conditionbox" id="treatRow2_y2"></div>
                                <p style="margin-bottom: -10px; margin-top: -3.5px;"
                                    class="text-[10px] w-20 font-normal text-gray-900 dark:text-white">Condition</p>
                            </div>

                            <div class="w-80">
                                <div class="gridtop" id="permanentGridtop_y2"></div>
                                <div class="grid1" id="permanentGridbot_y2"></div>
                                <div class="gridtop" id="temporaryGridtop_y2"></div>
                                <div class="grid1" id="temporaryGridbot_y2"></div>
                            </div>

                            <div class="w-80">
                                <p style="margin-top: -10px; margin-bottom: -3.3px;"
                                    class="text-[10px] w-20 font-normal text-gray-900 dark:text-white">Condition</p>
                                <div class="conditionbox1" id="treatRow3_y2"></div>
                                <div class="treatmentbox1" id="treatRow4_y2"></div>
                                <p style="margin-top: -3px;"
                                    class="text-[10px] w-20 font-normal text-gray-900 dark:text-white">Treatment</p>
                            </div>
                        </div>

                        <!-- YEAR 4 -->
                        <div id="year4" class="flex flex-col">
                            <p
                                class="text-[12px] -ml-1 w-full font-semibold text-gray-900 dark:text-white">Year IV - Date</p>
                            <div class="w-80">
                                <p
                                    class="text-[10px] mb-[-1.5px] w-20 font-normal text-gray-900 dark:text-white">Treatment</p>
                                <div class="treatmentbox" id="treatRow1_y3"></div>
                                <div class="conditionbox" id="treatRow2_y3"></div>
                                <p style="margin-bottom: -10px; margin-top: -3.5px;"
                                    class="text-[10px] w-20 font-normal text-gray-900 dark:text-white">Condition</p>
                            </div>

                            <div class="w-80">
                                <div class="gridtop" id="permanentGridtop_y3"></div>
                                <div class="grid1" id="permanentGridbot_y3"></div>
                                <div class="gridtop" id="temporaryGridtop_y3"></div>
                                <div class="grid1" id="temporaryGridbot_y3"></div>
                            </div>

                            <div class="w-80">
                                <p style="margin-top: -10px; margin-bottom: -3.3px;"
                                    class="text-[10px] w-20 font-normal text-gray-900 dark:text-white">Condition</p>
                                <div class="conditionbox1" id="treatRow3_y3"></div>
                                <div class="treatmentbox1" id="treatRow4_y3"></div>
                                <p style="margin-top: -3px;"
                                    class="text-[10px] w-20 font-normal text-gray-900 dark:text-white">Treatment</p>
                            </div>
                        </div>
                        <!-- YEAR 5 -->
                        <div id="year5" class="flex flex-col">
                            <p
                                class="text-[12px] -ml-1 w-full font-semibold text-gray-900 dark:text-white">Year V - Date</p>
                            <div class="w-80">
                                <p
                                    class="text-[10px] mb-[-1.5px] w-20 font-normal text-gray-900 dark:text-white">Treatment</p>
                                <div class="treatmentbox" id="treatRow1_y4"></div>
                                <div class="conditionbox" id="treatRow2_y4"></div>
                                <p style="margin-bottom: -10px; margin-top: -3.5px;"
                                    class="text-[10px] w-20 font-normal text-gray-900 dark:text-white">Condition</p>
                            </div>

                            <div class="w-80">
                                <div class="gridtop" id="permanentGridtop_y4"></div>
                                <div class="grid1" id="permanentGridbot_y4"></div>
                                <div class="gridtop" id="temporaryGridtop_y4"></div>
                                <div class="grid1" id="temporaryGridbot_y4"></div>
                            </div>

                            <div class="w-80">
                                <p style="margin-top: -10px; margin-bottom: -3.3px;"
                                    class="text-[10px] w-20 font-normal text-gray-900 dark:text-white">Condition</p>
                                <div class="conditionbox1" id="treatRow3_y4"></div>
                                <div class="treatmentbox1" id="treatRow4_y4"></div>
                                <p style="margin-top: -3px;"
                                    class="text-[10px] w-20 font-normal text-gray-900 dark:text-white">Treatment</p>
                            </div>
                        </div>
                    </div>
                    <!-- right -->
                    <div class="flex flex-col gap-15  justify-between ">
                        <!-- YEAR 3 -->
                        <div id="year3" class="flex flex-col">
                            <p
                                class="text-[12px] -ml-1 w-full font-semibold text-gray-900 dark:text-white">Year III - Date</p>
                            <div class="w-80">
                                <p
                                    class="text-[10px] mb-[-1.5px] w-20 font-normal text-gray-900 dark:text-white">Treatment</p>
                                <div class="treatmentbox" id="treatRow1_y5"></div>
                                <div class="conditionbox" id="treatRow2_y5"></div>
                                <p style="margin-bottom: -10px; margin-top: -3.5px;"
                                    class="text-[10px] w-20 font-normal text-gray-900 dark:text-white">Condition</p>
                            </div>

                            <div class="w-80">
                                <div class="gridtop" id="permanentGridtop_y5"></div>
                                <div class="grid1" id="permanentGridbot_y5"></div>
                                <div class="gridtop" id="temporaryGridtop_y5"></div>
                                <div class="grid1" id="temporaryGridbot_y5"></div>
                            </div>

                            <div class="w-80">
                                <p style="margin-top: -10px; margin-bottom: -3.3px;"
                                    class="text-[10px] w-20 font-normal text-gray-900 dark:text-white">Condition</p>
                                <div class="conditionbox1" id="treatRow3_y5"></div>
                                <div class="treatmentbox1" id="treatRow4_y5"></div>
                                <p style="margin-top: -3px;"
                                    class="text-[10px] w-20 font-normal text-gray-900 dark:text-white">Treatment</p>
                            </div>
                        </div>
                        <!-- Legend Condition -->
                        <div class="flex flex-row gap-1 px-1  w-110   overflow-auto [scrollbar-width:none] [-ms-overflow-style:none] 
                                    [&::-webkit-scrollbar]:hidden">
                            <!-- Condition -->
                            <div
                                class="controls relative w-full p-1 flex rounded-sm flex-col border border-dashed border-gray-400 [border-image:repeating-linear-gradient(45deg,#6b7280_0_10px,transparent_10px_15px)_1]">
                                <div class="p-2">
                                    <p class="text-[12px] font-medium  text-gray-900 dark:text-white">Legend: <span
                                            class="font-normal">Condition</span>
                                    </p>
                                    <p class="text-[11px] font-normal mt-1 text-gray-900 dark:text-white">Capital
                                        letters
                                        shall
                                        be use for recording <br>the condition of permanent dentition and <br> small
                                        letters
                                        for
                                        the status of temporary <br>dentition.
                                    </p>
                                </div>
                                <div class=" ">
                                    <table class="w-full text-sm text-center border-1">
                                        <thead class="text-sm align-text-top text-gray-700 border">
                                            <tr>
                                                <th scope="col" class="border-1 text-[12px]">
                                                    Permanent <br> <input type="hidden" id="upperCaseChk"
                                                        checked>
                                                </th>
                                                <th scope="col" class=" w-20 border-1 text-[12px]">
                                                    Tooth Condition
                                                </th>
                                                <th scope="col" class="border-1 text-[12px]">
                                                    Temporary <br> <input type="hidden" id="lowerCaseChk">
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr class="border-1">
                                                <td class=" border-1">
                                                    ✓
                                                </td>
                                                <td class="border-1 text-[12px]">
                                                    Sound/Sealed
                                                </td>
                                                <td class="border-1">
                                                    ✓
                                                </td>
                                            </tr>
                                            <tr class="border-1">
                                                <td class="border-1 text-[12px]">
                                                    D
                                                </td>
                                                <td class="border-1 text-[12px]">
                                                    Decayed
                                                </td>
                                                <td class="border-1 text-[12px]">
                                                    d
                                                </td>
                                            </tr>
                                            <tr class="border-1">
                                                <td class="border-1 text-[12px]">
                                                    F
                                                </td>
                                                <td class="border-1 text-[12px]">
                                                    Filled
                                                </td>
                                                <td class="border-1 text-[12px]">
                                                    f
                                                </td>
                                            </tr>
                                            <tr class="border-1">
                                                <td class="border-1 text-[12px]">
                                                    M
                                                </td>
                                                <td class="border-1 text-[12px]">
                                                    Missing
                                                </td>
                                                <td class="border-1 text-[12px]">
                                                    m
                                                </td>
                                            </tr>
                                            <tr class="border-1">
                                                <td class="border-1 text-[12px]">
                                                    DX
                                                </td>
                                                <td class="p-1 border-1 text-[12px]">
                                                    Indicated for Extraction
                                                </td>
                                                <td class="border-1 text-[12px]">
                                                    dx
                                                </td>
                                            </tr>
                                            <tr class="border-1">
                                                <td class="border-1 text-[12px]">
                                                    Un
                                                </td>
                                                <td class="border-1 text-[12px]">
                                                    Unerupted
                                                </td>
                                                <td class="border-1 text-[12px]">
                                                    un
                                                </td>
                                            </tr>
                                            <tr class="border-1">
                                                <td class="border-1 text-[12px]">
                                                    S
                                                </td>
                                                <td class="border-1 text-[12px]">
                                                    Supernumerary Tooth
                                                </td>
                                                <td class="border-1 text-[12px]">
                                                    s
                                                </td>
                                            </tr>
                                            <tr class="border-1">
                                                <td class="border-1 text-[12px]">
                                                    JC
                                                </td>
                                                <td class="border-1 text-[12px]">
                                                    jacket Crown
                                                </td>
                                                <td class="border-1 text-[12px]">
                                                    jc
                                                </td>
                                            </tr>
                                            <tr class="border-1 text-[12px]">
                                                <td class="border-1">
                                                    P
                                                </td>
                                                <td class="border-1 text-[12px]">
                                                    Pontic
                                                </td>
                                                <td class="border-1 text-[12px]">
                                                    p
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="flex items-start w-full flex-row gap-1 mt-1 ml-2">
                                    <label
                                        class="text-[12px] font-bold w-17  text-gray-900 dark:text-white">Color
                                        Code:</label>
                                    <div type="text" id="blueSelect"
                                        class="bg-blue-600 border px-2 py-1 justify-center  text-[12px] ">
                                        <label
                                            class="text-[12px] font-semibold w-12  text-white dark:text-white">Blue for f/F</label>
                                    </div>
                                    <div type="text" id="redSelect"
                                        class="bg-red-600 border px-2 py-1 justify-center  text-[12px] ">
                                        <label
                                            class="text-[12px] font-semibold w-12  text-white dark:text-white">Red for d/D</label>
                                    </div>
                                </div>
                            </div>
                            <!-- Treatment -->
                            <div
                                class="controls  p-1 flex rounded-sm flex-col border border-dashed border-gray-400 [border-image:repeating-linear-gradient(45deg,#6b7280_0_10px,transparent_10px_15px)_1]">
                                <div class="w-38 flex flex-col justify-center items-center p-2">
                                    <div class="flex flex-col gap-3">
                                        <p class="text-[12px] font-medium  text-gray-900 dark:text-white">Legend:
                                            <span class="font-normal">Treament</span>
                                        </p>
                                        <div class="flex flex-col gap-3">
                                            <p class="text-[12px] font-normal  text-gray-900 dark:text-white">
                                                Topical
                                                Fluoride
                                                Application:
                                            </p>
                                            <p class="text-[12px] font-normal ml-5 text-gray-900 dark:text-white">FV
                                                -
                                                Fluoride
                                                Varnish
                                            <p class="text-[12px] font-normal ml-5 text-gray-900 dark:text-white">FG
                                                -
                                                Fluoride
                                                Gel
                                            </p>
                                        </div>
                                        <p class="text-[12px] font-normal  text-gray-900 dark:text-white">PFS - Pit
                                            and
                                            Fissure Sealant
                                        </p>
                                        <p class="text-[12px] font-normal  text-gray-900 dark:text-white">PF -
                                            Permanent
                                            Filling (Composite, Am, ART)
                                        </p>
                                        <p class="text-[12px] font-normal  text-gray-900 dark:text-white">TF -
                                            Temporary
                                            Filling
                                        </p>
                                        <p class="text-[12px] font-normal  text-gray-900 dark:text-white">X -
                                            Extraction
                                        </p>
                                        <p class="text-[12px] font-normal  text-gray-900 dark:text-white">O - Others
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
            <div id="shouldprint3" class="flex flex-col items-start py-10 pt-30 w-full">
                <h4 class="text-[15px] font-bold mt-1 mb-1">B. Services Monitoring chart</h4>
                <!-- 12 slots -->
                <div class="flex flex-row items-start">
                    <div class="flex w-full flex-col">
                        <div class="flex flex-row">
                            <p class="text-[12px] font-medium ml-2">Date</p>
                            <p class="text-[12px] font-medium ml-30">Fluoride Varnish/Fluoride Gel, Pit and Fissure Sealant, Permanent Filling, Temporary Filling, Extraction</p>
                        </div>
                        <div class="flex flex-col mb-5 gap-5 w-full">
                            <div id="55-65" class="flex flex-row w-full">
                                <!-- Column 1: DATES -->
                                <div class="flex flex-col w-full items-center">
                                    <?php for ($i = 0; $i < 5; $i++): ?>
                                        <input
                                            type="text"
                                            class="h-5 w-full border text-center text-[12px] px-1 <?php echo $i > 0 ? 'border-t-0' : ''; ?>"
                                            value="<?php echo isset($dates[$i]) ? date('m/d/Y', strtotime($dates[$i])) : ''; ?>">
                                    <?php endfor; ?>
                                </div>

                                <!-- Column 2: EMPTY -->
                                <div class="flex flex-col w-full items-center">
                                    <?php for ($i = 0; $i < 5; $i++): ?>
                                        <input
                                            type="text"
                                            class="h-5 w-full border border-l-0 text-center text-[12px] px-1 <?php echo $i > 0 ? 'border-t-0' : ''; ?>"
                                            value="">
                                    <?php endfor; ?>
                                </div>

                                <!-- Columns 3–12: Teeth 55–65 -->
                                <?php
                                $teeth_numbers = [55, 54, 53, 52, 51, 61, 62, 63, 64, 65];
                                foreach ($teeth_numbers as $num):
                                ?>
                                    <div class="flex flex-col w-full items-center">
                                        <?php for ($i = 0; $i < 5; $i++):
                                            $date = $dates[$i] ?? null;
                                            $value = '';
                                            if ($date && isset($treatmentData[$date][$num])) {
                                                $value = htmlspecialchars($treatmentData[$date][$num]);
                                            }
                                        ?>
                                            <input
                                                type="text"
                                                class="h-5 w-full border border-l-0 text-center text-[12px] px-1 <?php echo $i > 0 ? 'border-t-0' : ''; ?>"
                                                value="<?php echo $value; ?>">
                                        <?php endfor; ?>
                                        <p class="text-[12px]"><?php echo $num; ?></p>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div id="85-75" class="flex flex-row w-full">
                                <!-- Column 1: DATES -->
                                <div class="flex flex-col w-full items-center">
                                    <?php for ($i = 0; $i < 5; $i++): ?>
                                        <input
                                            type="text"
                                            class="h-5 w-full border text-center text-[12px] px-1 <?php echo $i > 0 ? 'border-t-0' : ''; ?>"
                                            value="<?php echo isset($dates[$i]) ? date('m/d/Y', strtotime($dates[$i])) : ''; ?>">
                                    <?php endfor; ?>
                                </div>

                                <!-- Column 2: EMPTY -->
                                <div class="flex flex-col w-full items-center">
                                    <?php for ($i = 0; $i < 5; $i++): ?>
                                        <input
                                            type="text"
                                            class="h-5 w-full border border-l-0 text-center text-[12px] px-1 <?php echo $i > 0 ? 'border-t-0' : ''; ?>"
                                            value="">
                                    <?php endfor; ?>
                                </div>

                                <!-- Columns 3–12: Teeth 55–65 -->
                                <?php
                                $teeth_numbers = [85, 84, 83, 82, 81, 71, 72, 73, 74, 75];
                                foreach ($teeth_numbers as $num):
                                ?>
                                    <div class="flex flex-col w-full items-center">
                                        <?php for ($i = 0; $i < 5; $i++):
                                            $date = $dates[$i] ?? null;
                                            $value = '';
                                            if ($date && isset($treatmentData[$date][$num])) {
                                                $value = htmlspecialchars($treatmentData[$date][$num]);
                                            }
                                        ?>
                                            <input
                                                type="text"
                                                class="h-5 w-full border border-l-0 text-center text-[12px] px-1 <?php echo $i > 0 ? 'border-t-0' : ''; ?>"
                                                value="<?php echo $value; ?>">
                                        <?php endfor; ?>
                                        <p class="text-[12px]"><?php echo $num; ?></p>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <!-- Treatment -->
                    <div class="w-[45%] flex flex-col px-8">
                        <div class="flex flex-col gap-3">
                            <p class="text-[13px] font-bold text-gray-900 dark:text-white">Legend:
                            </p>
                            <div class="flex flex-col gap-3 ml-2">
                                <p class="text-[13px] font-normal  text-gray-900 dark:text-white">
                                    Topical
                                    Fluoride
                                    Application:
                                </p>
                                <p class="text-[13px] font-normal ml-5 text-gray-900 dark:text-white">FV
                                    -
                                    Fluoride
                                    Varnish
                                <p class="text-[13px] font-normal ml-5 text-gray-900 dark:text-white">FG
                                    -
                                    Fluoride
                                    Gel
                                </p>
                            </div>
                            <div class="flex flex-col gap-3 ml-2">
                                <p class="text-[13px] font-normal  text-gray-900 dark:text-white">PFS - Pit
                                    and
                                    Fissure Sealant
                                </p>
                                <p class="text-[13px] font-normal  text-gray-900 dark:text-white">PF -
                                    Permanent
                                    Filling (Composite, Am, ART)
                                </p>
                                <p class="text-[13px] font-normal  text-gray-900 dark:text-white">TF -
                                    Temporary
                                    Filling
                                </p>
                                <p class="text-[13px] font-normal  text-gray-900 dark:text-white">X -
                                    Extraction
                                </p>
                                <p class="text-[13px] font-normal  text-gray-900 dark:text-white">O - Others
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- 18 slots -->
                <div class="flex flex-col">
                    <div class="flex flex-row">
                        <p class="text-[12px] font-medium ml-2">Date</p>
                        <p class="text-[12px] font-medium ml-30">Fluoride Varnish/Fluoride Gel, Pit and Fissure Sealant, Permanent Filling, Temporary Filling, Extraction</p>
                    </div>
                    <div class="flex flex-col  gap-5">
                        <div id="18-28" class="flex flex-row w-full">
                            <!-- Column 1: DATES -->
                            <div class="flex flex-col w-full items-center">
                                <?php for ($i = 0; $i < 5; $i++): ?>
                                    <input
                                        type="text"
                                        class="h-5 w-full border text-center text-[12px] px-1 <?php echo $i > 0 ? 'border-t-0' : ''; ?>"
                                        value="<?php echo isset($dates[$i]) ? date('m/d/Y', strtotime($dates[$i])) : ''; ?>">
                                <?php endfor; ?>
                            </div>

                            <!-- Column 2: EMPTY -->
                            <div class="flex flex-col w-full items-center">
                                <?php for ($i = 0; $i < 5; $i++): ?>
                                    <input
                                        type="text"
                                        class="h-5 w-full border text-center text-[12px] px-1 border-l-0 <?php echo $i > 0 ? 'border-t-0' : ''; ?>"
                                        value="">
                                <?php endfor; ?>
                            </div>

                            <!-- Columns 3–12: Teeth 55–65 -->
                            <?php
                            $teeth_numbers = [18, 17, 16, 15, 14, 13, 12, 11, 21, 22, 23, 24, 25, 26, 27, 28];
                            foreach ($teeth_numbers as $num):
                            ?>
                                <div class="flex flex-col w-full items-center">
                                    <?php for ($i = 0; $i < 5; $i++):
                                        $date = $dates[$i] ?? null;
                                        $value = '';
                                        if ($date && isset($treatmentData[$date][$num])) {
                                            $value = htmlspecialchars($treatmentData[$date][$num]);
                                        }
                                    ?>
                                        <input
                                            type="text"
                                            class="h-5 w-full border text-center text-[12px] px-1 border-l-0 <?php echo $i > 0 ? 'border-t-0' : ''; ?>"
                                            value="<?php echo $value; ?>">
                                    <?php endfor; ?>
                                    <p class="text-[12px]"><?php echo $num; ?></p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div id="48-38" class="flex flex-row w-full">
                            <!-- Column 1: DATES -->
                            <div class="flex flex-col w-full items-center">
                                <?php for ($i = 0; $i < 5; $i++): ?>
                                    <input
                                        type="text" readonly
                                        class="h-5 w-full border text-center text-[12px] px-1 <?php echo $i > 0 ? 'border-t-0' : ''; ?>"
                                        value="<?php echo isset($dates[$i]) ? date('m/d/Y', strtotime($dates[$i])) : ''; ?>">
                                <?php endfor; ?>
                            </div>

                            <!-- Column 2: EMPTY -->
                            <div class="flex flex-col  w-full items-center">
                                <?php for ($i = 0; $i < 5; $i++): ?>
                                    <input
                                        type="text"
                                        class="h-5 w-full border border-l-0 <?php echo $i > 0 ? 'border-t-0' : ''; ?>"
                                        value="">
                                <?php endfor; ?>
                            </div>

                            <!-- Columns 3–12: Teeth 55–65 -->
                            <?php
                            $teeth_numbers = [48, 47, 46, 45, 44, 43, 42, 41, 31, 32, 33, 34, 35, 36, 37, 38];
                            foreach ($teeth_numbers as $num):
                            ?>
                                <div class="flex w-full flex-col items-center">
                                    <?php for ($i = 0; $i < 5; $i++):
                                        $date = $dates[$i] ?? null;
                                        $value = '';
                                        if ($date && isset($treatmentData[$date][$num])) {
                                            $value = htmlspecialchars($treatmentData[$date][$num]);
                                        }
                                    ?>
                                        <input
                                            type="text"
                                            class="h-5 w-full text-center text-[12px] border border-l-0 <?php echo $i > 0 ? 'border-t-0' : ''; ?>"
                                            value="<?php echo $value; ?>">
                                    <?php endfor; ?>
                                    <p class="text-[12px]"><?php echo $num; ?></p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            <div id="shouldprint4" class="flex flex-col items-start w-full pt-30 py-10">
                <h4 class="text-[15px] font-bold text-center w-full mb-1">Individual Patient Treatment</h4>
                <div class="flex flex-col mb-1">
                    <h4 class="text-[15px] font-bold mt-1">Record of Services Rendered</h4>
                    <div class="flex flex-col ml-2">
                        <h4 class="text-[14px] font-normal flex flex-row items-center"><img src="/dentalemr_system/img/Screenshot__503_-removebg-preview.png" alt="" class="h-4">For Oral Prophylaxis, Fluoride Varnish/Gel - Check (✓) id rendered</h4>
                        <h4 class="text-[14px] font-normal flex flex-row items-center"><img src="/dentalemr_system/img/Screenshot__503_-removebg-preview.png" alt="" class="h-4">For Permanent & Temporary Filling, Pit and Fissure Sealant And Extraction - Indicate Number</h4>
                    </div>
                </div>
                <div class="flex flex-row w-full">

                    <!-- Column 1: Date -->
                    <div class="flex flex-col text-center items-center w-40">
                        <p class="text-[12px] border border-b-0 font-medium h-14 px-2 items-center flex justify-center w-full">Date</p>
                        <?php for ($i = 0; $i < 20; $i++): ?>
                            <input
                                type="text"
                                class="h-5 w-full text-[12px] border text-center font-normal px-1 <?php echo $i > 0 ? 'border-t-0' : ''; ?>"
                                value="<?php echo isset($preventiveData[$i]['created_at']) ? date('m/d/Y', strtotime($preventiveData[$i]['created_at'])) : ''; ?>">
                        <?php endfor; ?>
                    </div>

                    <!-- Column 2: Oral Prophylaxis -->
                    <div class="flex flex-col text-center items-center w-100">
                        <p class="text-[12px] border border-b-0 font-medium h-14 px-2 border-l-0 flex items-center justify-center w-full">Oral Prophylaxis</p>
                        <?php for ($i = 0; $i < 20; $i++): ?>
                            <input
                                type="text"
                                class="h-5 w-full text-[12px] font-normal border border-l-0 text-center px-1 <?php echo $i > 0 ? 'border-t-0' : ''; ?>"
                                value="<?php
                                        echo (isset($preventiveData[$i]['oral_prophylaxis']) && strtolower($preventiveData[$i]['oral_prophylaxis']) === 'yes')
                                            ? '✓'
                                            : '';
                                        ?>">
                        <?php endfor; ?>
                    </div>

                    <!-- Column 3: Fluoride Varnish/Gel -->
                    <div class="flex flex-col text-center items-center w-70">
                        <p class="text-[12px] border border-b-0 font-medium h-14 px-2 border-l-0 flex items-center justify-center w-full">Fluoride<br>Varnish/<br>Fluoride Gel</p>
                        <?php for ($i = 0; $i < 20; $i++): ?>
                            <input
                                type="text"
                                class="h-5 w-full text-[12px] font-normal border border-l-0 text-center px-1 <?php echo $i > 0 ? 'border-t-0' : ''; ?>"
                                value="<?php
                                        echo (isset($preventiveData[$i]['fluoride']) && strtolower($preventiveData[$i]['fluoride']) === 'yes')
                                            ? '✓'
                                            : '';
                                        ?>">
                        <?php endfor; ?>
                    </div>

                    <!-- Column 4: Pit and Fissure Sealant -->
                    <div class="flex flex-col text-center items-center w-70">
                        <p class="text-[12px] border border-b-0 font-medium h-14 px-2 border-l-0 flex items-center justify-center w-full">Pit and Fissure<br>Sealant</p>
                        <?php for ($i = 0; $i < 20; $i++): ?>
                            <input
                                type="text"
                                class="h-5 w-full text-[12px] font-normal border border-l-0 text-center px-1 <?php echo $i > 0 ? 'border-t-0' : ''; ?>"
                                value="<?php echo isset($preventiveData[$i]['sealant']) ? htmlspecialchars($preventiveData[$i]['sealant']) : ''; ?>">
                        <?php endfor; ?>
                    </div>

                    <!-- Column 5: Temporary Filling -->
                    <div class="flex flex-col text-center items-center w-70">
                        <p class="text-[12px] border border-b-0 font-medium h-14 px-2 border-l-0 flex items-center justify-center w-full">Temporary<br>Filling</p>
                        <?php for ($i = 0; $i < 20; $i++): ?>
                            <input
                                type="text"
                                class="h-5 w-full text-[12px] font-normal border border-l-0 text-center px-1 <?php echo $i > 0 ? 'border-t-0' : ''; ?>"
                                value="<?php echo isset($preventiveData[$i]['temporary_filling']) ? htmlspecialchars($preventiveData[$i]['temporary_filling']) : ''; ?>">
                        <?php endfor; ?>
                    </div>

                    <!-- Column 6: Extraction -->
                    <div class="flex flex-col text-center items-center w-70">
                        <p class="text-[12px] border border-b-0 font-medium h-14 px-2 border-l-0 flex items-center justify-center w-full">Extraction</p>
                        <?php for ($i = 0; $i < 20; $i++): ?>
                            <input
                                type="text"
                                class="h-5 w-full text-[12px] font-normal border border-l-0 text-center px-1 <?php echo $i > 0 ? 'border-t-0' : ''; ?>"
                                value="<?php echo isset($preventiveData[$i]['extraction']) ? htmlspecialchars($preventiveData[$i]['extraction']) : ''; ?>">
                        <?php endfor; ?>
                    </div>

                    <!-- Column 7: Consultation -->
                    <div class="flex flex-col text-center items-center w-50">
                        <p class="text-[12px] border border-b-0 font-medium h-14 px-2 border-l-0 flex items-center justify-center w-full">Consultation</p>
                        <?php for ($i = 0; $i < 20; $i++): ?>
                            <input
                                type="text"
                                class="h-5 w-full text-[12px] font-normal border border-l-0 text-center px-1 <?php echo $i > 0 ? 'border-t-0' : ''; ?>"
                                value="<?php
                                        echo (isset($preventiveData[$i]['consultation']) && strtolower($preventiveData[$i]['consultation']) === 'yes')
                                            ? '✓'
                                            : '';
                                        ?>">
                        <?php endfor; ?>
                    </div>

                    <!-- Column 8: Remarks -->
                    <div class="flex flex-col text-center items-center w-100">
                        <p class="text-[12px] border border-b-0 font-medium h-14 px-2 border-l-0 flex items-center justify-center w-full">Remarks / Others (Specify)</p>
                        <?php for ($i = 0; $i < 20; $i++): ?>
                            <input
                                type="text"
                                class="h-5 w-full text-[12px] font-normal border border-l-0 text-center px-1 <?php echo $i > 0 ? 'border-t-0' : ''; ?>"
                                value="<?php echo isset($preventiveData[$i]['remarks']) ? htmlspecialchars($preventiveData[$i]['remarks']) : ''; ?>">
                        <?php endfor; ?>
                    </div>

                    <!-- Column 9: Dentist’s Signature -->
                    <div class="flex flex-col text-center items-center w-70">
                        <p class="text-[12px] border border-b-0 font-medium h-14 px-2 border-l-0 flex items-center justify-center w-full">Dentist's Signature</p>
                        <?php for ($i = 0; $i < 20; $i++): ?>
                            <input
                                type="text"
                                class="h-5 w-full text-[12px] font-normal border border-l-0 text-center px-1 <?php echo $i > 0 ? 'border-t-0' : ''; ?>"
                                value="">
                        <?php endfor; ?>
                    </div>

                </div>


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
                window.location.href = "/dentalemr_system/php/login/logout.php?uid=<?php echo $userId; ?>";
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
        const viewinfoLink = document.getElementById("viewinfoLink");
        if (viewinfoLink && patientId) {
            viewinfoLink.href = `view_info.php?uid=<?php echo $userId; ?>&id=${encodeURIComponent(patientId)}`;
        } else {
            // Optional fallback: disable link if no patient selected
            viewinfoLink.addEventListener("click", (e) => {
                e.preventDefault();
                alert("Please select a patient first.");
            });
        }
    </script>
    <!-- PRINT FUNCTION -->
    <script>
        function printAllPages() {
            const sectionIds = ['shouldprint', 'shouldprint2', 'shouldprint3', 'shouldprint4'];
            const sections = sectionIds
                .map(id => document.getElementById(id))
                .filter(Boolean);

            if (sections.length === 0) {
                alert("No printable sections found.");
                return;
            }

            const printWindow = window.open('', '_blank');
            const headContent = document.querySelector('head').innerHTML;

            // Clone sections and apply inline background colors
            const contentHTML = sections.map((section, i) => {
                const clone = section.cloneNode(true);

                // Inline computed styles (for correct color & border printing)
                clone.querySelectorAll('*').forEach(el => {
                    const style = window.getComputedStyle(el);
                    const bg = style.backgroundColor;
                    const color = style.color;
                    const border = style.border;
                    if (bg && bg !== 'rgba(0, 0, 0, 0)') el.style.backgroundColor = bg;
                    el.style.color = color;
                    el.style.border = border;
                    el.style.webkitPrintColorAdjust = 'exact';
                    el.style.printColorAdjust = 'exact';
                });

                return `<div class="print-page">${clone.outerHTML}</div>`;
            }).join('');

            printWindow.document.write(`
        <html>
        <head>
            ${headContent}
            <style>
                @page {
                    size: A4 landscape;
                    margin: 0;
                }

                html, body {
                    margin: 0;
                    padding: 0;
                    background: white;
                    width: 100%;
                }

                body {
                    display: block;
                }

                .print-page {
                    width: 297mm;
                    height: 210mm;
                    margin: 0;
                    padding: 0;
                    box-sizing: border-box;
                    page-break-after: always;
                    display: flex;
                    justify-content: center;
                    align-items: flex-start;
                }

                /* Each content block should scale down slightly to fit cleanly */
                .print-page > * {
                    width: 95%;
                    height: auto;
                    max-height: 210mm;
                    zoom: 0.9;
                    transform-origin: top center;
                }
                    /* Force print all colors and lines exactly as on screen */
            * {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                color-adjust: exact !important;
            }

            /* Ensure borders and grids appear sharp */
            table, th, td, div, .tooth, .part, .conditionbox, .treatmentbox,
            .conditionbox1, .treatmentbox1 {
                border-collapse: collapse !important;
                border-color: #000 !important;
            }

            /* Preserve grid layout for Oral Health Condition */
            #shouldprint2 {
                zoom: 0.9; /* Fit within page width */
                transform-origin: top center;
            }

            /* Fix for small text in boxes */
            .treatmentbox div, .treatmentbox1 div {
                font-size: 10px !important;
                font-weight: bold !important;
                text-align: center !important;
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
                color: black !important;
            }
                .conditionbox div, .conditionbox1 div {
                font-size: 10px !important;
                font-weight: bold !important;
                text-align: center !important;
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
                color:white !important;
            }
            .gridtop div, .grid1 div{
                font-size: 8px !important;
                font-weight: medium !important;
                text-align: center !important;
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
            }

                @media print {
                    html, body {
                        width: 297mm;
                        height: auto;
                        overflow: visible;
                    }

                    #blueSelect, #redSelect {
                        -webkit-print-color-adjust: exact;
                        print-color-adjust: exact;
                    }
                }
            </style>
        </head>
        <body>
            ${contentHTML}
        </body>
        </html>
    `);

            printWindow.document.close();

            printWindow.onload = () => {
                setTimeout(() => {
                    printWindow.focus();
                    printWindow.print();
                    printWindow.close();
                }, 500);
            };
        }
    </script>
    <!-- teeth Structure -->
    <script>
        const teethParts = ['top-left', 'top-right', 'bottom-left', 'bottom-right', 'center'];

        // --- Tooth & Box Creation ---
        function createPart(toothId, partName, yearSuffix = '') {
            const part = document.createElement('div');
            part.className = 'part part-' + partName;
            const key = `${toothId}-${partName}${yearSuffix}`;
            part.dataset.key = key;
            part.addEventListener('click', () => {
                if (!selectedCondition) return alert('Select a condition from the Blue/Red selector');
                applyChange(key, selectedColor, selectedCondition, selectedCase, true, false);
            });
            return part;
        }

        function createTooth(id, label, position = 'bottom', tooth_id = null, yearSuffix = '') {
            const container = document.createElement('div');
            container.className = 'tooth-container';

            const toothLabel = document.createElement('div');
            toothLabel.className = `tooth-label label-${position}`;
            toothLabel.textContent = label;

            const tooth = document.createElement('div');
            tooth.className = 'tooth';
            tooth.id = id + yearSuffix;
            tooth.dataset.toothId = tooth_id ?? '';

            teethParts.forEach(p => tooth.appendChild(createPart(id, p, yearSuffix)));

            const tooltip = document.createElement('div');
            tooltip.className = 'tooltip';
            tooltip.textContent = label;
            tooth.appendChild(tooltip);

            container.append(position === 'top' ? toothLabel : tooth, position === 'top' ? tooth : toothLabel);
            return container;
        }

        function createBox(id, row, kind, yearSuffix = '') {
            const box = document.createElement('div');
            const key = `R${row}-${id}${yearSuffix}`;
            box.dataset.key = key;

            if (kind === 'treatment') {
                box.className = (row === 4) ? 'treatment1-box' : 'treatment-box';
                box.addEventListener('click', () => {
                    const selectedTreat = treatmentSelect?.value || '';
                    if (!selectedTreat) return alert('Select a treatment from dropdown');
                    applyChange(key, '', selectedTreat, 'upper', true, true);
                });
            } else {
                box.className = (row === 3) ? 'condition1-box' : 'condition-box';
                box.addEventListener('click', () => {
                    if (!selectedCondition) return alert('Select a condition from the Blue/Red selector');
                    applyChange(key, selectedColor, selectedCondition, selectedCase, true, false);
                });
            }

            return box;
        }

        // --- Load Grids and Boxes ---
        async function loadGridForYear(suffix = '') {
            const permTop = document.getElementById(`permanentGridtop${suffix}`);
            const permBot = document.getElementById(`permanentGridbot${suffix}`);
            const tempTop = document.getElementById(`temporaryGridtop${suffix}`);
            const tempBot = document.getElementById(`temporaryGridbot${suffix}`);
            if (!permTop || !permBot || !tempTop || !tempBot) return;

            let teethData = [];
            try {
                const r = await fetch('/dentalemr_system/php/treatment/get_teeth.php');
                if (r.ok) teethData = await r.json();
            } catch (e) {
                console.warn('Could not load teeth mapping', e);
            }

            const permT = [55, 54, 53, 52, 51, 61, 62, 63, 64, 65];
            const permB = [18, 17, 16, 15, 14, 13, 12, 11, 21, 22, 23, 24, 25, 26, 27, 28];
            const tempT = [48, 47, 46, 45, 44, 43, 42, 41, 31, 32, 33, 34, 35, 36, 37, 38];
            const tempB = [85, 84, 83, 82, 81, 71, 72, 73, 74, 75];

            permT.forEach(n => {
                const t = teethData.find(t => parseInt(t.fdi_number) === n);
                permTop.appendChild(createTooth(`P-${n}`, n, 'top', t ? t.tooth_id : n, suffix));
            });
            permB.forEach(n => {
                const t = teethData.find(t => parseInt(t.fdi_number) === n);
                permBot.appendChild(createTooth(`P-${n}`, n, 'bottom', t ? t.tooth_id : n, suffix));
            });
            tempT.forEach(n => {
                const t = teethData.find(t => parseInt(t.fdi_number) === n);
                tempTop.appendChild(createTooth(`T-${n}`, n, 'top', t ? t.tooth_id : n, suffix));
            });
            tempB.forEach(n => {
                const t = teethData.find(t => parseInt(t.fdi_number) === n);
                tempBot.appendChild(createTooth(`T-${n}`, n, 'bottom', t ? t.tooth_id : n, suffix));
            });
        }

        function loadBoxesForYear(suffix = '') {
            const rows = [1, 2, 3, 4].map(i => document.getElementById(`treatRow${i}${suffix}`));
            if (rows.some(r => !r)) return;

            for (let i = 0; i < 16; i++) {
                rows[0].appendChild(createBox(i, 1, 'treatment', suffix));
                rows[1].appendChild(createBox(i, 2, 'condition', suffix));
                rows[2].appendChild(createBox(i, 3, 'condition', suffix));
                rows[3].appendChild(createBox(i, 4, 'treatment', suffix));
            }
        }

        async function initAllYears() {
            const years = ['', '_y2', '_y5', '_y3', '_y4']; // order matches your year mapping
            for (const s of years) {
                await loadGridForYear(s);
                loadBoxesForYear(s);
            }
        }

        // --- Clear Teeth and Boxes ---
        function clearAllTeeth() {
            document.querySelectorAll(".part,[data-key^='R']").forEach(el => {
                el.style.backgroundColor = '';
                el.style.color = '';
                el.style.border = '';
                el.textContent = '';
            });
        }

        // --- Load Patient Visit Data ---
        async function loadVisitData(patientId) {
            try {
                clearAllTeeth();

                const res = await fetch(`/dentalemr_system/php/treatmentrecords/view_oralA.php?patient_id=${patientId}`);
                const data = await res.json();
                if (!data.success) throw new Error(data.error || "Failed to load data");

                // Map visit indices to year suffix
                const yearMapping = [{
                        suffix: '',
                        visitIndex: 0,
                        roman: 'I'
                    },
                    {
                        suffix: '_y2',
                        visitIndex: 1,
                        roman: 'II'
                    },
                    {
                        suffix: '_y5',
                        visitIndex: 2,
                        roman: 'III'
                    },
                    {
                        suffix: '_y3',
                        visitIndex: 3,
                        roman: 'IV'
                    },
                    {
                        suffix: '_y4',
                        visitIndex: 4,
                        roman: 'V'
                    },
                ];

                yearMapping.forEach((year, i) => {
                    const visit = data.visits[year.visitIndex];
                    const yearEl = document.querySelector(`#year${i+1} > p`);
                    if (yearEl) {
                        yearEl.textContent = visit ? `Year ${year.roman} - ${new Date(visit.visit_date).toLocaleDateString()}` : `Year ${year.roman} - Date`;
                    }
                    if (!visit) return;

                    // Populate conditions
                    visit.conditions.forEach(c => {
                        const el = document.querySelector(`[data-key='${c.box_key}${year.suffix}']`);
                        if (!el) return;
                        const fallbackColor = c.condition_code?.toLowerCase() === 'm' ? '#ef4444' : '#3b82f6';
                        const fillColor = c.color?.trim() || fallbackColor;

                        el.textContent = c.condition_code || '';
                        Object.assign(el.style, {
                            backgroundColor: fillColor,
                            color: '#fff',
                            fontWeight: 'bold',
                            fontSize: '10px',
                            display: 'flex',
                            alignItems: 'center',
                            justifyContent: 'center',
                            border: '1px solid rgba(0,0,0,0.1)'
                        });
                    });

                    // Populate treatments
                    visit.treatments.forEach(t => {
                        const el = document.querySelector(`[data-key='${t.box_key}${year.suffix}']`);
                        if (!el) return;
                        el.textContent = t.treatment_code?.toUpperCase() || '';
                        Object.assign(el.style, {
                            backgroundColor: '#fff',
                            border: '1px solid #00000080',
                            color: '#000',
                            fontSize: '10px',
                            fontWeight: 'bold',
                            display: 'flex',
                            alignItems: 'center',
                            justifyContent: 'center'
                        });
                    });
                });

            } catch (err) {
                console.error("Failed to load visit data:", err);
                alert("Failed to load visit data: " + err.message);
            }
        }

        // --- Highlight Year Buttons ---
        function highlightActiveButton(yearNumber) {
            document.querySelectorAll("#yearButtons button").forEach((btn, index) => {
                Object.assign(btn.style, {
                    backgroundColor: index + 1 === yearNumber ? '#1d4ed8' : '#fff',
                    color: index + 1 === yearNumber ? '#fff' : '#000',
                    fontWeight: index + 1 === yearNumber ? 'bold' : 'normal'
                });
            });
        }

        // --- Initialize Everything ---
        initAllYears().then(() => {
            const params = new URLSearchParams(window.location.search);
            const patientId = params.get("id");
            if (!patientId) return alert("Missing patient ID");

            loadVisitData(patientId);

            // Attach year switching functions
            for (let i = 1; i <= 5; i++) {
                window['year' + i] = () => highlightActiveButton(i);
            }
        });
    </script>
</body>

</html>