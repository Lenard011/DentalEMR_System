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
<!doctype html>
<html>

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Patient Information</title>
  <!-- <link href="../css/style.css" rel="stylesheet"> -->
  <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>

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

    <main class="relative p-1.5 md:ml-64 h-auto pt-1">
      <div class="relative flex items-center w-full  mt-13 p-2">
        <!-- Back Btn -->
        <button type="button" onclick="back()" class="cursor-pointer absolute left-2">
          <svg class="w-[35px] h-[35px] text-blue-800 dark:blue-white" aria-hidden="true"
            xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
              d="M5 12h14M    5 12l4-4m-4 4 4 4" />
          </svg>
        </button>
        <p class="mx-auto text-xl text-center font-semibold text-gray-900 dark:text-white">
          Patient Information
        </p>
      </div>
      <section class="relative bg-white dark:bg-gray-900 p-2 sm:p-2 rounded-lg mb-2">
        <p id="patientName" class="italic text-lg font-medium text-gray-900 dark:text-white mb-2">Loading ...</p>
        <!-- Patient Info -->
        <div
          class="relative mx-auto mb-5 max-w-screen-xl px-1.5 py-2 lg:px-1.5 bg-white rounded-lg shadow dark:border shadow-stone-300 drop-shadow-sm dark:bg-gray-800 dark:border-gray-950">
          <div class="items-center justify-between flex flex-row mb-3">
            <p class="text-base font-normal text-gray-950 dark:text-white ">Patient Detatils</p>
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
          <div class="relative flex flex-col justify-center items-center px-5 gap-5 mb-3">
            <div class="flex items-center justify-between p-2  max-w-5xl w-full">
              <div class="grid items-center justify-center grid-flow-col gap-1">
                <div class="flex items-center w-12 ">
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
                    style="font-size:14.6px;">
                    Loading ...</p>
                  <p class="text-xs font-normal text-gray-950 dark:text-white" style="font-size:13px;">
                    Name</p>
                </div>
              </div>
              <div class="relative grid items-center justify-center grid-flow-col gap-1">
                <div class="flex items-center w-12 ">
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
                    style="font-size:14.6px;">
                    Loading ...</p>
                  <p class="text-xs font-normal text-gray-950 dark:text-white" style="font-size:13px;">
                    Gender</p>
                </div>
              </div>
              <div class="relative grid items-center justify-center grid-flow-col gap-1">
                <div class="flex items-center w-12 ">
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
                    style="font-size:14.6px;">
                    Loading ...</p>
                  <p class="text-xs font-normal text-gray-950 dark:text-white" style="font-size:13px;">
                    Age</p>
                </div>
              </div>
              <div class="relative grid items-center justify-center grid-flow-col gap-1">
                <div class="flex items-center w-12 ">
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
                    style="font-size:14.6px;">
                    Loading ...</p>
                  <p class="text-xs font-normal text-gray-950 dark:text-white" style="font-size:13px;">
                    Date of Birth</p>
                </div>
              </div>
              <div class="relative grid items-center justify-center grid-flow-col gap-1">
                <div class="flex items-center w-12 ">
                  <div class="rounded-full p-2.5 bg-gray-100 dark:bg-blue-300">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor"
                      class="w-6 h-6 text-blue-800 dark:text-white" viewBox="-1 -3 16 22">
                      <path d="M4 16s-1 0-1-1 1-4 5-4 5 3 5 4-1 1-1 1zm4-5.95a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5" />
                      <path
                        d="M2 1a2 2 0 0 0-2 2v9.5A1.5 1.5 0 0 0 1.5 14h.653a5.4 5.4 0 0 1 1.066-2H1V3a1 1 0 0 1 1-1h12a1 1 0 0 1 1 1v9h-2.219c.554.654.89 1.373 1.066 2h.653a1.5 1.5 0 0 0 1.5-1.5V3a2 2 0 0 0-2-2z" />
                    </svg>
                  </div>
                </div>
                <div>
                  <p id="patientOccupation" class="text-sm font-normal text-gray-950 dark:text-white"
                    style="font-size:14.6px;">
                    Loading ...</p>
                  <p class="text-xs font-normal text-gray-950 dark:text-white" style="font-size:13px;">
                    Occupation</p>
                </div>
              </div>
            </div>
            <div class="relative flex items-center justify-between  p-2   max-w-5xl w-full">
              <div class="grid items-center justify-center grid-flow-col gap-1">
                <div class="flex items-center w-12 ">
                  <div class="rounded-full p-2.5 bg-gray-100 dark:bg-blue-300">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor"
                      class="w-6 h-6 text-blue-800 dark:text-white" viewBox="-2 0 20 16">
                      <path
                        d="M12.166 8.94c-.524 1.062-1.234 2.12-1.96 3.07A32 32 0 0 1 8 14.58a32 32 0 0 1-2.206-2.57c-.726-.95-1.436-2.008-1.96-3.07C3.304 7.867 3 6.862 3 6a5 5 0 0 1 10 0c0 .862-.305 1.867-.834 2.94M8 16s6-5.686 6-10A6 6 0 0 0 2 6c0 4.314 6 10 6 10" />
                      <path d="M8 8a2 2 0 1 1 0-4 2 2 0 0 1 0 4m0 1a3 3 0 1 0 0-6 3 3 0 0 0 0 6" />
                    </svg>
                  </div>
                </div>
                <div>
                  <p id="patientBirthPlace" class="text-sm font-normal text-gray-950 dark:text-white"
                    style="font-size:14.6px;">
                    Loading ...</p>
                  <p class="text-xs font-normal text-gray-950 dark:text-white" style="font-size:13px;">
                    Place of Birth</p>
                </div>
              </div>
              <div class="relative grid items-center justify-center grid-flow-col gap-1">
                <div class="flex items-center w-12 ">
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
                    style="font-size:14.6px;">
                    Loading ...</p>
                  <p class="text-xs font-normal text-gray-950 dark:text-white" style="font-size:13px;">
                    Address</p>
                </div>
              </div>
              <div class="relative grid items-center justify-center grid-flow-col gap-1">
                <div class="flex items-center w-12 ">
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
                    style="font-size:14.6px;">
                    Loading ...</p>
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
                <input type="checkbox" value="1" name="nhts_pr" data-field="nhts_pr"
                  class="w-4 h-4 text-blue-600 border-gray-300 rounded-sm">
                <label class="ms-2 text-sm">NHTS-PR</label>
              </div>

              <!-- 4Ps -->
              <div class="flex items-center mb-1">
                <input type="checkbox" value="1" name="four_ps" data-field="four_ps"
                  class="w-4 h-4 text-blue-600 border-gray-300 rounded-sm">
                <label class="ms-2 text-sm">Pantawid Pamilyang Pilipino Program (4Ps)</label>
              </div>

              <!-- IP -->
              <div class="flex items-center mb-1">
                <input type="checkbox" value="1" name="indigenous_people" data-field="indigenous_people"
                  class="w-4 h-4 text-blue-600 border-gray-300 rounded-sm">
                <label class="ms-2 text-sm">Indigenous People (IP)</label>
              </div>

              <!-- PWD -->
              <div class="flex items-center mb-1">
                <input type="checkbox" value="1" name="pwd" data-field="pwd"
                  class="w-4 h-4 text-blue-600 border-gray-300 rounded-sm">
                <label class="ms-2 text-sm">Person With Disabilities (PWDs)</label>
              </div>

              <!-- PhilHealth -->
              <div class="flex items-center mb-1">
                <input type="checkbox" value="1" name="philhealth_flag" data-field="philhealth_flag"
                  onchange="toggleInput(this, 'philhealth_number')"
                  class="w-4 h-4 text-blue-600 border-gray-300 rounded-sm">
                <div class="grid grid-cols-2 items-center gap-4">
                  <label class="ms-2 text-sm">PhilHealth (Indicate Number)</label>
                  <input type="text" id="philhealth_number" name="philhealth_number" disabled
                    class="block py-1 px-0 w-full text-sm border-b-2 border-gray-300 focus:outline-none focus:border-blue-600" />
                </div>
              </div>

              <!-- SSS -->
              <div class="flex items-center mb-1">
                <input type="checkbox" value="1" name="sss_flag" data-field="sss_flag"
                  onchange="toggleInput(this, 'sss_number')" class="w-4 h-4 text-blue-600 border-gray-300 rounded-sm">
                <div class="grid grid-cols-2 items-center gap-4">
                  <label class="ms-2 text-sm">SSS (Indicate Number)</label>
                  <input type="text" id="sss_number" name="sss_number" disabled
                    class="block py-1 px-0 w-full text-sm border-b-2 border-gray-300 focus:outline-none focus:border-blue-600" />
                </div>
              </div>

              <!-- GSIS -->
              <div class="flex items-center mb-1">
                <input type="checkbox" value="1" name="gsis_flag" data-field="gsis_flag"
                  onchange="toggleInput(this, 'gsis_number')" class="w-4 h-4 text-blue-600 border-gray-300 rounded-sm">
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
        <!-- small notification container -->
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


  <!-- edit/update patient details -->
  <script>
    document.addEventListener('DOMContentLoaded', () => {
      const urlParams = new URLSearchParams(window.location.search);
      const patientId = urlParams.get('id');
      const wasUpdated = urlParams.get('updated');

      const notice = document.getElementById('notice');
      const editBtn = document.getElementById('editBtn');
      const modal = document.getElementById('editPatientModal');
      const form = document.getElementById('editPatientForm');

      const dobField = document.getElementById('editDob');
      const ageInput = document.getElementById('age');
      const sexInput = document.getElementById('editSex');
      const monthInput = document.getElementById('agemonth');
      const monthContainer = document.getElementById('monthContainer');
      const formContainer = document.getElementById('form-container');
      const pregnantSection = document.getElementById('pregnant-section');
      const pregnantRadios = pregnantSection ? pregnantSection.querySelectorAll('input[name="pregnant"]') : [];

      let currentPatient = null;

      function showNotice(msg, color = 'blue') {
        if (!notice) return;
        notice.textContent = msg;
        notice.style.background = color;
        notice.style.display = 'block';
        notice.style.opacity = '1';
        notice.style.transition = '';
        setTimeout(() => {
          notice.style.transition = 'opacity 0.6s';
          notice.style.opacity = '0';
          setTimeout(() => notice.style.display = 'none', 800);
        }, 4000);
      }

      async function loadPatient() {
        if (!patientId) return;
        try {
          const res = await fetch(`../php/register_patient/get_patient.php?id=${encodeURIComponent(patientId)}`);
          const data = await res.json();
          if (!data.success) throw new Error(data.error || 'Failed to fetch patient data.');
          currentPatient = data.patient;

          if (editBtn) editBtn.onclick = () => openModal(currentPatient);
          if (wasUpdated === '1') showNotice('Patient updated successfully', 'blue');

          updateDisplay(currentPatient);
        } catch (err) {
          console.error('Error loading patient:', err);
          alert('Error loading patient details. Please try again.');
        }
      }

      function updateDisplay(patient) {
        const name = `${patient.surname}, ${patient.firstname} ${patient.middlename || ''}`.trim();
        const ageText = patient.display_age || `${patient.age} years old`;

        const fields = {
          patientName: `${name}.`,
          patientName2: `${name}.`,
          patientDob: patient.date_of_birth || '',
          patientSex: patient.sex || '',
          patientAge: ageText,
          patientBirthPlace: patient.place_of_birth || '',
          patientOccupation: patient.occupation || '',
          patientAddress: patient.address || '',
          patientGuardian: patient.guardian || ''
        };

        for (const [id, value] of Object.entries(fields)) {
          const el = document.getElementById(id);
          if (el) el.textContent = value;
        }
      }

      // --- Robust function to set Sex field ---
      function setSexField(value) {
        if (!sexInput) return;
        const validValues = ['Male', 'Female'];
        value = (value || '').trim();
        value = value.charAt(0).toUpperCase() + value.slice(1).toLowerCase();
        sexInput.value = validValues.includes(value) ? value : '';
      }

      function openModal(patient) {
        if (!patient || !form) return;

        form.editPatientId.value = patient.patient_id ?? '';
        form.editFirstname.value = patient.firstname ?? '';
        form.editSurname.value = patient.surname ?? '';
        form.editMiddlename.value = patient.middlename ?? '';
        form.editDob.value = patient.date_of_birth ?? '';
        form.editPob.value = patient.place_of_birth ?? '';
        form.editOccupation.value = patient.occupation ?? '';
        form.editAddress.value = patient.address ?? '';
        form.editGuardian.value = patient.guardian ?? '';
        ageInput.value = patient.age ?? '';

        setSexField(patient.sex); // <-- ensures select matches stored DB value

        handleMonthVisibility(parseInt(patient.age) || 0, parseInt(patient.agemonth) || 0);
        togglePregnantSection();

        if (patient.pregnant) {
          pregnantRadios.forEach(r => r.checked = r.value.toLowerCase() === patient.pregnant.toLowerCase());
        } else {
          pregnantRadios.forEach(r => r.checked = r.value.toLowerCase() === 'no');
        }

        modal.classList.remove('hidden');
        modal.classList.add('flex');
      }

      window.closeModal = () => {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
      };

      function handleMonthVisibility(years, months = 0) {
        if (years < 5) {
          monthContainer.style.display = 'block';
          monthInput.value = years * 12 + months;
        } else {
          monthContainer.style.display = 'none';
          monthInput.value = '';
        }
      }

      function togglePregnantSection() {
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

      function updateFromDOB() {
        const dob = new Date(dobField.value);
        const today = new Date();
        if (!dobField.value || isNaN(dob.getTime()) || dob > today) return;

        let years = today.getFullYear() - dob.getFullYear();
        let months = today.getMonth() - dob.getMonth();
        if (today.getDate() < dob.getDate()) months--;
        if (months < 0) {
          years--;
          months += 12;
        }

        ageInput.value = Math.max(0, years);
        handleMonthVisibility(Math.max(0, years), Math.max(0, months));
        togglePregnantSection();
      }

      // --- Ensure sex always submits a valid value ---
      form.addEventListener('submit', e => {
        if (!sexInput.value) {
          alert('Please select Male or Female.');
          e.preventDefault();
        }
      });

      dobField?.addEventListener('change', updateFromDOB);
      ageInput?.addEventListener('input', () => {
        togglePregnantSection();
        handleMonthVisibility(parseInt(ageInput.value) || 0);
      });
      sexInput?.addEventListener('change', togglePregnantSection);

      loadPatient();
    });
  </script>

  <!-- membership, medical and dietary  -->
  <script>
    /* -----------------------------------------------------------
   Inject logged-in UID from PHP (required for history logging)
------------------------------------------------------------*/
    const loggedInUid = <?= json_encode($_GET['uid'] ?? 0) ?>;

    /* -----------------------------------------------------------
       Wrapper: ALWAYS attach ?uid= to any fetch request
    ------------------------------------------------------------*/
    function apiFetch(url, options = {}) {
      const connector = url.includes("?") ? "&" : "?";
      const finalUrl = `${url}${connector}uid=${loggedInUid}`;
      return fetch(finalUrl, options);
    }

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

    function setPatientIdOnForms() {
      const urlParams = new URLSearchParams(window.location.search);
      const pid = urlParams.get('id');
      if (!pid) return;
      document.querySelectorAll("input[name=patient_id]").forEach(i => i.value = pid);

      loadMemberships(pid);
      loadMedicalHistory(pid);
      loadDietaryHistory(pid);
    }

    function showModal(id) {
      document.getElementById(id).classList.remove("hidden");
    }

    function hideModal(id) {
      document.getElementById(id).classList.add("hidden");
    }

    /* ---------- Unified showNotice (matches vitals style) ---------- */
    function showNotice(message, success = true) {
      const notice = document.getElementById('notice');
      if (!notice) return;

      notice.textContent = message;
      notice.style.background = success ? '#2563eb' : '#dc2626'; // blue for success, red for error
      notice.style.color = 'white';
      notice.style.display = 'block';
      notice.style.opacity = '1';
      notice.style.transition = 'opacity 0.6s';

      // Fade out after 5 seconds
      setTimeout(() => {
        notice.style.opacity = '0';
        setTimeout(() => {
          notice.style.display = 'none';
        }, 1500);
      }, 3000);
    }

    /* ---------- Load membership ---------- */
    async function loadMemberships(patientId) {
      try {
        const res = await apiFetch(`../php/register_patient/patient_info.php?action=get_membership&patient_id=${patientId}`);
        const json = await res.json();
        const membershipList = document.getElementById("membershipList");
        membershipList.innerHTML = "";

        document.querySelectorAll("#membershipForm input[type=checkbox]").forEach(cb => cb.checked = false);
        document.querySelectorAll("#membershipForm input[type=text]").forEach(inp => {
          inp.value = "";
          inp.disabled = true;
        });

        if (json.success && json.values) {
          const v = json.values;
          document.querySelectorAll("#membershipForm input[type=checkbox]").forEach(cb => {
            const name = cb.name;
            const flag = v[name] ?? v[cb.getAttribute('data-field')] ?? 0;
            cb.checked = (flag == 1 || flag === "1");
          });

          if (v.philhealth_flag == 1) {
            const el = document.getElementById("philhealth_number");
            if (el) {
              el.disabled = false;
              el.value = v.philhealth_number || "";
            }
          }
          if (v.sss_flag == 1) {
            const el = document.getElementById("sss_number");
            if (el) {
              el.disabled = false;
              el.value = v.sss_number || "";
            }
          }
          if (v.gsis_flag == 1) {
            const el = document.getElementById("gsis_number");
            if (el) {
              el.disabled = false;
              el.value = v.gsis_number || "";
            }
          }

          (json.memberships || []).forEach(m => {
            const li = document.createElement("li");
            li.textContent = m.label;
            membershipList.appendChild(li);
          });
        }
      } catch (err) {
        console.error("loadMemberships", err);
      }
    }

    /* ---------- Load medical ---------- */
    async function loadMedicalHistory(patientId) {
      try {
        const res = await apiFetch(`../php/register_patient/patient_info.php?action=get_medical&patient_id=${patientId}`);
        const json = await res.json();
        const medicalList = document.getElementById("medicalHistoryList");
        medicalList.innerHTML = "";

        document.querySelectorAll("#medicalForm input[type=checkbox]").forEach(cb => cb.checked = false);
        document.querySelectorAll("#medicalForm input[type=text], #medicalForm input[type=date], #medicalForm textarea")
          .forEach(inp => {
            inp.value = "";
            inp.disabled = true;
          });

        if (json.success) {
          const v = json.values || {};
          document.querySelectorAll("#medicalForm input[type=checkbox]").forEach(cb => {
            const val = v[cb.name] ?? 0;
            cb.checked = (val == 1 || val === "1");
          });

          if (v.allergies_flag == 1) {
            const el = document.getElementById("allergies_details");
            if (el) {
              el.disabled = false;
              el.value = v.allergies_details || "";
            }
          }
          if (v.hepatitis_flag == 1) {
            const el = document.getElementById("hepatitis_details");
            if (el) {
              el.disabled = false;
              el.value = v.hepatitis_details || "";
            }
          }
          if (v.malignancy_flag == 1) {
            const el = document.getElementById("malignancy_details");
            if (el) {
              el.disabled = false;
              el.value = v.malignancy_details || "";
            }
          }
          if (v.prev_hospitalization_flag == 1) {
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
          }
          if (v.blood_transfusion_flag == 1) {
            const b = document.getElementById("blood_transfusion_date");
            if (b) {
              b.disabled = false;
              b.value = v.blood_transfusion || "";
            }
          }
          if (v.other_conditions_flag == 1) {
            const o = document.getElementById("other_conditions");
            if (o) {
              o.disabled = false;
              o.value = v.other_conditions || "";
            }
          }

          (json.medical || []).forEach(m => {
            const li = document.createElement("li");
            li.textContent = m.label;
            medicalList.appendChild(li);
          });
        }
      } catch (err) {
        console.error("loadMedicalHistory", err);
      }
    }

    /* ---------- Load dietary ---------- */
    async function loadDietaryHistory(patientId) {
      try {
        const res = await apiFetch(`../php/register_patient/patient_info.php?action=get_dietary&patient_id=${patientId}`);
        const json = await res.json();
        const dietaryList = document.getElementById("dietaryHistoryList");
        dietaryList.innerHTML = "";

        document.querySelectorAll("#dietaryForm input[type=checkbox]").forEach(cb => cb.checked = false);
        document.querySelectorAll("#dietaryForm input[type=text]").forEach(inp => {
          inp.value = "";
          inp.disabled = true;
        });

        if (json.success) {
          const v = json.values || {};
          document.querySelectorAll("#dietaryForm input[type=checkbox]").forEach(cb => {
            const val = v[cb.name] ?? 0;
            cb.checked = (val == 1 || val === "1");
          });

          if (v.sugar_flag == 1) {
            const el = document.getElementById("sugar_details");
            if (el) {
              el.disabled = false;
              el.value = v.sugar_details || "";
            }
          }
          if (v.alcohol_flag == 1) {
            const el = document.getElementById("alcohol_details");
            if (el) {
              el.disabled = false;
              el.value = v.alcohol_details || "";
            }
          }
          if (v.tobacco_flag == 1) {
            const el = document.getElementById("tobacco_details");
            if (el) {
              el.disabled = false;
              el.value = v.tobacco_details || "";
            }
          }
          if (v.betel_nut_flag == 1) {
            const el = document.getElementById("betel_nut_details");
            if (el) {
              el.disabled = false;
              el.value = v.betel_nut_details || "";
            }
          }

          (json.dietary || []).forEach(m => {
            const li = document.createElement("li");
            li.textContent = m.label;
            dietaryList.appendChild(li);
          });
        }
      } catch (err) {
        console.error("loadDietaryHistory", err);
      }
    }

    /* ---------- Submit handlers ---------- */
    document.addEventListener("DOMContentLoaded", () => {
      setPatientIdOnForms();

      document.getElementById("addBtn")?.addEventListener("click", () => showModal("membershipModal"));
      document.getElementById("cancelBtn")?.addEventListener("click", () => hideModal("membershipModal"));
      document.getElementById("addMedicalHistoryBtn")?.addEventListener("click", () => showModal("medicalModal"));
      document.getElementById("cancelMedicalBtn")?.addEventListener("click", () => hideModal("medicalModal"));
      document.getElementById("addDietaryHistoryBtn")?.addEventListener("click", () => showModal("dietaryModal"));
      document.getElementById("cancelDietaryBtn")?.addEventListener("click", () => hideModal("dietaryModal"));

      /* --- Membership --- */
      const membershipForm = document.getElementById("membershipForm");
      membershipForm?.addEventListener("submit", async (e) => {
        e.preventDefault();
        const form = e.target;
        const fd = new FormData(form);

        form.querySelectorAll("input[type=checkbox]").forEach(cb => fd.set(cb.name, cb.checked ? "1" : "0"));
        form.querySelectorAll("input[type=text]").forEach(inp => fd.set(inp.name, inp.disabled ? "" : inp.value));

        const pidEl = document.getElementById("patient_id");
        if (!pidEl || !pidEl.value) {
          showNotice("No patient specified", false);
          return;
        }
        fd.set("patient_id", pidEl.value);
        fd.set("action", "save_membership");

        try {
          const r = await apiFetch("../php/register_patient/patient_info.php", {
            method: "POST",
            body: fd
          });
          const text = await r.text();
          console.log("membership raw:", text);
          const json = JSON.parse(text);
          if (json.success) {
            hideModal("membershipModal");
            showNotice("Membership saved successfully!", true);
            loadMemberships(pidEl.value);
          } else showNotice("Error: " + (json.message || "Unknown"), false);
        } catch (err) {
          console.error(err);
          showNotice("Request failed", false);
        }
      });

      /* --- Medical --- */
      const medicalForm = document.getElementById("medicalForm");
      medicalForm?.addEventListener("submit", async (e) => {
        e.preventDefault();
        const form = e.target;
        const fd = new FormData(form);

        form.querySelectorAll("input[type=checkbox]").forEach(cb => fd.set(cb.name, cb.checked ? "1" : "0"));
        form.querySelectorAll("input[type=text], input[type=date], textarea").forEach(inp => fd.set(inp.name, inp.disabled ? "" : inp.value));

        const btd = document.getElementById("blood_transfusion_date");
        if (btd) fd.set("blood_transfusion", btd.disabled ? "" : btd.value);

        const pidEl = document.getElementById("patient_id");
        if (!pidEl || !pidEl.value) {
          showNotice("No patient specified", false);
          return;
        }
        fd.set("patient_id", pidEl.value);
        fd.set("action", "save_medical");

        try {
          const r = await apiFetch("../php/register_patient/patient_info.php", {
            method: "POST",
            body: fd
          });
          const text = await r.text();
          console.log("medical raw:", text);
          const json = JSON.parse(text);
          if (json.success) {
            hideModal("medicalModal");
            showNotice("Medical history saved successfully!", true);
            loadMedicalHistory(pidEl.value);
          } else showNotice("Error: " + (json.message || "Unknown"), false);
        } catch (err) {
          console.error(err);
          showNotice("Request failed", false);
        }
      });

      /* --- Dietary --- */
      const dietaryForm = document.getElementById("dietaryForm");
      dietaryForm?.addEventListener("submit", async (e) => {
        e.preventDefault();
        const form = e.target;
        const fd = new FormData(form);

        form.querySelectorAll("input[type=checkbox]").forEach(cb => fd.set(cb.name, cb.checked ? "1" : "0"));
        form.querySelectorAll("input[type=text]").forEach(inp => fd.set(inp.name, inp.disabled ? "" : inp.value));

        const pidEl = document.getElementById("patient_id");
        if (!pidEl || !pidEl.value) {
          showNotice("No patient specified", false);
          return;
        }
        fd.set("patient_id", pidEl.value);
        fd.set("action", "save_dietary");

        try {
          const r = await apiFetch("../php/register_patient/patient_info.php", {
            method: "POST",
            body: fd
          });
          const text = await r.text();
          console.log("dietary raw:", text);
          const json = JSON.parse(text);
          if (json.success) {
            hideModal("dietaryModal");
            showNotice("Dietary history saved successfully!", true);
            loadDietaryHistory(pidEl.value);
          } else showNotice("Error: " + (json.message || "Unknown"), false);
        } catch (err) {
          console.error(err);
          showNotice("Request failed", false);
        }
      });
    });
  </script>
  
  <!-- vital signs  -->
  <script>
    document.addEventListener("DOMContentLoaded", () => {
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

      addBtn.addEventListener('click', () => modal.classList.remove('hidden'));
      cancelBtn.addEventListener('click', () => {
        modal.classList.add('hidden');
        vitalForm.reset();
      });

      vitalForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = new FormData(vitalForm);
        const data = new URLSearchParams();
        data.append('action', 'save_vitals');
        data.append('patient_id', patientInput.value); // read from hidden input
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
            showNotice('Vital signs added successfully!', true);
            modal.classList.add('hidden');
            vitalForm.reset();
            fetchVitals(); // refresh table
          } else {
            showNotice('Failed to add vital signs: ' + result.message, false);
          }
        } catch (err) {
          console.error(err);
          showNotice('Error adding vital signs.', false);
        }
      });

      async function fetchVitals() {
        try {
          const res = await fetch(`../php/register_patient/patient_info.php?action=get_vitals&patient_id=${patientInput.value}`);
          const data = await res.json();
          if (!data.success) return;

          bpTable.innerHTML = '';
          tempTable.innerHTML = '';
          pulseTable.innerHTML = '';
          weightTable.innerHTML = '';

          data.vitals.forEach(v => {
            const d = new Date(v.recorded_at);
            const recorded = d.getFullYear() + '-' +
              String(d.getMonth() + 1).padStart(2, '0') + '-' +
              String(d.getDate()).padStart(2, '0');

            bpTable.innerHTML += `<tr class="flex  items-center justify-between  border-b  w-full dark:bg-gray-800 dark:border-gray-700 border-gray-200"><td class="px-3 py-1 ">${recorded}</td><td class="px-3 py-1 text-right">${v.blood_pressure}</td></tr>`;
            tempTable.innerHTML += `<tr class="flex  items-center justify-between  border-b  w-full dark:bg-gray-800 dark:border-gray-700 border-gray-200"><td class="px-3 py-1 ">${recorded}</td><td class="px-3 py-1 text-right">${v.temperature}</td></tr>`;
            pulseTable.innerHTML += `<tr class="flex  items-center justify-between  border-b  w-full dark:bg-gray-800 dark:border-gray-700 border-gray-200"><td class="px-3 py-1 ">${recorded}</td><td class="px-3 py-1 text-right">${v.pulse_rate}</td></tr>`;
            weightTable.innerHTML += `<tr class="flex  items-center justify-between  border-b  w-full dark:bg-gray-800 dark:border-gray-700 border-gray-200"><td class="px-3 py-1 ">${recorded}</td><td class="px-3 py-1 text-right">${v.weight}</td></tr>`;
          });
        } catch (err) {
          console.error('Failed to fetch vitals:', err);
        }
      }

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