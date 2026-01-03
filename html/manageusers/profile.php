<?php
// profile.php
session_start();
date_default_timezone_set('Asia/Manila');

// REQUIRE userId parameter
if (!isset($_GET['uid'])) {
    header('Location: /dentalemr_system/html/login/login.html?error=invalid_session');
    exit;
}

$userId = intval($_GET['uid']);

// CHECK IF THIS USER IS REALLY LOGGED IN
if (
    !isset($_SESSION['active_sessions']) ||
    !isset($_SESSION['active_sessions'][$userId])
) {
    header('Location: /dentalemr_system/html/login/login.html?error=session_expired');
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

        header('Location: /dentalemr_system/html/login/login.html?error=inactivity');
        exit;
    }
}

// Update last activity timestamp
$_SESSION['active_sessions'][$userId]['last_activity'] = time();

// GET USER DATA FOR PAGE USE
$loggedUser = $_SESSION['active_sessions'][$userId];

// Database connection
$host = "localhost";
$dbUser = "root";
$dbPass = "";
$dbName = "dentalemr_system";

$conn = new mysqli($host, $dbUser, $dbPass, $dbName);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Initialize variables
$userData = [];
$successMsg = '';
$errorMsg = '';
$isDentist = false;
$profilePicture = null;

// Fetch user data based on type - WITH profile_picture column
if ($loggedUser['type'] === 'Dentist') {
    $isDentist = true;
    $query = "SELECT id, name, username, email, profile_picture, created_at, updated_at FROM dentist WHERE id = ?";
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        die("Prepare failed for dentist: " . $conn->error);
    }
    $stmt->bind_param("i", $loggedUser['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) {
        $userData = $result->fetch_assoc();
        $userData['last_login'] = null;
        $profilePicture = $userData['profile_picture'] ?? null;
    }
    $stmt->close();
} elseif ($loggedUser['type'] === 'Staff') {
    $query = "SELECT id, name, email, username, profile_picture, created_at, updated_at, last_login FROM staff WHERE id = ?";
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        die("Prepare failed for staff: " . $conn->error);
    }
    $stmt->bind_param("i", $loggedUser['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) {
        $userData = $result->fetch_assoc();
        $profilePicture = $userData['profile_picture'] ?? null;
    }
    $stmt->close();
} elseif ($loggedUser['type'] === 'Admin') {
    $query = "SELECT id, name, email, username, profile_picture, created_at, updated_at, last_login FROM staff WHERE id = ?";
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        die("Prepare failed for admin: " . $conn->error);
    }
    $stmt->bind_param("i", $loggedUser['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) {
        $userData = $result->fetch_assoc();
        $profilePicture = $userData['profile_picture'] ?? null;
    }
    $stmt->close();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        // Update profile information
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $username = trim($_POST['username']);

        // Handle profile picture upload
        $profilePictureUpdated = false;
        $newProfilePicture = $profilePicture;

        if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == 0) {
            $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
            $fileType = $_FILES['profile_picture']['type'];
            $fileSize = $_FILES['profile_picture']['size'];

            if (in_array($fileType, $allowedTypes)) {
                if ($fileSize < 5242880) { // 5MB limit
                    $uploadDir = '/dentalemr_system/uploads/profile_pictures/';
                    $uploadPath = $_SERVER['DOCUMENT_ROOT'] . $uploadDir;

                    // Create directory if it doesn't exist
                    if (!file_exists($uploadPath)) {
                        mkdir($uploadPath, 0777, true);
                    }

                    $fileExt = strtolower(pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION));
                    $fileName = 'user_' . $userData['id'] . '_' . time() . '.' . $fileExt;
                    $fullPath = $uploadPath . $fileName;

                    if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $fullPath)) {
                        // Delete old profile picture if exists and not default
                        if ($profilePicture && !str_contains($profilePicture, 'ui-avatars.com')) {
                            $oldPicturePath = $_SERVER['DOCUMENT_ROOT'] . parse_url($profilePicture, PHP_URL_PATH);
                            if (file_exists($oldPicturePath)) {
                                unlink($oldPicturePath);
                            }
                        }

                        $newProfilePicture = $uploadDir . $fileName;
                        $profilePictureUpdated = true;
                    } else {
                        $errorMsg = "Failed to upload profile picture. Please try again.";
                    }
                } else {
                    $errorMsg = "Profile picture size must be less than 5MB.";
                }
            } else {
                $errorMsg = "Only JPG, PNG, GIF, and WebP images are allowed.";
            }
        }

        // Validate inputs
        if (empty($name) || empty($email) || empty($username)) {
            $errorMsg = "All fields are required.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errorMsg = "Invalid email format.";
        } else {
            // Check if email already exists (excluding current user)
            if ($isDentist) {
                $checkStmt = $conn->prepare("SELECT id FROM dentist WHERE email = ? AND id != ?");
            } else {
                $checkStmt = $conn->prepare("SELECT id FROM staff WHERE email = ? AND id != ?");
            }

            if ($checkStmt) {
                $checkStmt->bind_param("si", $email, $userData['id']);
                $checkStmt->execute();
                $checkStmt->store_result();

                if ($checkStmt->num_rows > 0) {
                    $errorMsg = "Email already exists.";
                } else {
                    // Check if username already exists (excluding current user)
                    if ($isDentist) {
                        $checkUserStmt = $conn->prepare("SELECT id FROM dentist WHERE username = ? AND id != ?");
                    } else {
                        $checkUserStmt = $conn->prepare("SELECT id FROM staff WHERE username = ? AND id != ?");
                    }

                    if ($checkUserStmt) {
                        $checkUserStmt->bind_param("si", $username, $userData['id']);
                        $checkUserStmt->execute();
                        $checkUserStmt->store_result();

                        if ($checkUserStmt->num_rows > 0) {
                            $errorMsg = "Username already exists.";
                        } else {
                            // Update user data with profile picture
                            if ($isDentist) {
                                if ($profilePictureUpdated) {
                                    $updateStmt = $conn->prepare("UPDATE dentist SET name = ?, email = ?, username = ?, profile_picture = ?, updated_at = NOW() WHERE id = ?");
                                    $updateStmt->bind_param("ssssi", $name, $email, $username, $newProfilePicture, $userData['id']);
                                } else {
                                    $updateStmt = $conn->prepare("UPDATE dentist SET name = ?, email = ?, username = ?, updated_at = NOW() WHERE id = ?");
                                    $updateStmt->bind_param("sssi", $name, $email, $username, $userData['id']);
                                }
                            } else {
                                if ($profilePictureUpdated) {
                                    $updateStmt = $conn->prepare("UPDATE staff SET name = ?, email = ?, username = ?, profile_picture = ?, updated_at = NOW() WHERE id = ?");
                                    $updateStmt->bind_param("ssssi", $name, $email, $username, $newProfilePicture, $userData['id']);
                                } else {
                                    $updateStmt = $conn->prepare("UPDATE staff SET name = ?, email = ?, username = ?, updated_at = NOW() WHERE id = ?");
                                    $updateStmt->bind_param("sssi", $name, $email, $username, $userData['id']);
                                }
                            }

                            if ($updateStmt) {
                                if ($updateStmt->execute()) {
                                    $successMsg = "Profile updated successfully!" . ($profilePictureUpdated ? " Profile picture uploaded." : "");
                                    // Update session data
                                    $_SESSION['active_sessions'][$userId]['name'] = $name;
                                    $_SESSION['active_sessions'][$userId]['email'] = $email;
                                    if ($profilePictureUpdated) {
                                        $_SESSION['active_sessions'][$userId]['profile_picture'] = $newProfilePicture;
                                    }
                                    $loggedUser['name'] = $name;
                                    $loggedUser['email'] = $email;

                                    // Update local variables
                                    $profilePicture = $newProfilePicture;
                                    $userData['name'] = $name;
                                    $userData['email'] = $email;
                                    $userData['username'] = $username;
                                    // Refresh the displayPicture
                                    $displayPicture = $profilePicture;
                                } else {
                                    $errorMsg = "Failed to update profile. Please try again.";
                                }
                                $updateStmt->close();
                            } else {
                                $errorMsg = "Database error: Could not prepare update statement.";
                            }
                        }
                        $checkUserStmt->close();
                    } else {
                        $errorMsg = "Database error: Could not prepare username check.";
                    }
                }
                $checkStmt->close();
            } else {
                $errorMsg = "Database error: Could not prepare email check.";
            }
        }
    } elseif (isset($_POST['change_password'])) {
        // Change password (same as original)
        $currentPassword = $_POST['current_password'];
        $newPassword = $_POST['new_password'];
        $confirmPassword = $_POST['confirm_password'];

        // Validate inputs
        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            $errorMsg = "All password fields are required.";
        } elseif ($newPassword !== $confirmPassword) {
            $errorMsg = "New passwords do not match.";
        } elseif (strlen($newPassword) < 8) {
            $errorMsg = "Password must be at least 8 characters long.";
        } else {
            // Get current password hash
            if ($isDentist) {
                $passStmt = $conn->prepare("SELECT password_hash FROM dentist WHERE id = ?");
            } else {
                $passStmt = $conn->prepare("SELECT password_hash FROM staff WHERE id = ?");
            }

            if ($passStmt) {
                $passStmt->bind_param("i", $userData['id']);
                $passStmt->execute();
                $passStmt->bind_result($storedHash);
                $passStmt->fetch();
                $passStmt->close();

                if (password_verify($currentPassword, $storedHash)) {
                    $newHash = password_hash($newPassword, PASSWORD_DEFAULT);

                    if ($isDentist) {
                        $updatePassStmt = $conn->prepare("UPDATE dentist SET password_hash = ?, updated_at = NOW() WHERE id = ?");
                    } else {
                        $updatePassStmt = $conn->prepare("UPDATE staff SET password_hash = ?, updated_at = NOW() WHERE id = ?");
                    }

                    if ($updatePassStmt) {
                        $updatePassStmt->bind_param("si", $newHash, $userData['id']);
                        if ($updatePassStmt->execute()) {
                            $successMsg = "Password changed successfully!";
                        } else {
                            $errorMsg = "Failed to change password. Please try again.";
                        }
                        $updatePassStmt->close();
                    }
                } else {
                    $errorMsg = "Current password is incorrect.";
                }
            } else {
                $errorMsg = "Database error: Could not prepare password check.";
            }
        }
    }
}

$conn->close();

// Determine display picture
if (!empty($profilePicture)) {
    // If profile picture exists in database
    $displayPicture = $profilePicture;

    // Check if it's a local file
    if (strpos($profilePicture, '/dentalemr_system/uploads/') === 0) {
        if (!file_exists($_SERVER['DOCUMENT_ROOT'] . $profilePicture)) {
            // File doesn't exist, fall back to avatar
            $displayPicture = 'https://ui-avatars.com/api/?name=' . urlencode($userData['name'] ?? 'User') . '&background=3b82f6&color=fff&size=256&bold=true';
        }
    }
} else {
    // Use UI Avatars as default
    $displayPicture = 'https://ui-avatars.com/api/?name=' . urlencode($userData['name'] ?? 'User') . '&background=3b82f6&color=fff&size=256&bold=true';
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MHO Dental Clinic - My Profile</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script> -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');

        * {
            font-family: 'Inter', sans-serif;
            transition: background-color 0.3s ease, border-color 0.3s ease;
        }

        /* Dropdown animation styles */
        #userDropdown {
            opacity: 0;
            transform: scale(0.95);
            transform-origin: top right;
        }

        #userDropdown:not(.hidden) {
            opacity: 1;
            transform: scale(1);
        }

        #dropdownArrow {
            transition: transform 0.2s ease;
        }

        .rotate-180 {
            transform: rotate(180deg);
        }

        /* Improved dropdown hover effects */
        #userDropdown a {
            position: relative;
            overflow: hidden;
        }

        #userDropdown a::after {
            content: '';
            position: absolute;
            left: 0;
            bottom: 0;
            width: 100%;
            height: 1px;
            background: linear-gradient(90deg, transparent, rgba(59, 130, 246, 0.1), transparent);
        }

        /* Mobile optimizations */
        @media (max-width: 768px) {
            #userDropdown {
                position: fixed;
                top: 60px;
                right: 16px;
                left: 16px;
                width: auto;
                max-width: 300px;
                margin-left: auto;
                margin-right: auto;
            }
        }

        .profile-picture-container {
            position: relative;
            display: inline-block;
        }

        .profile-picture-overlay {
            position: absolute;
            inset: 0;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s ease;
            border-radius: 50%;
            cursor: pointer;
        }

        .profile-picture-container:hover .profile-picture-overlay {
            opacity: 1;
        }

        .card-hover {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .card-hover:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }

        .input-focus:focus {
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .fade-in {
            animation: fadeIn 0.5s ease-out;
        }

        @media (max-width: 768px) {
            .profile-section {
                flex-direction: column;
                text-align: center;
            }

            .profile-info {
                margin-top: 1rem;
            }
        }

        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
        }

        ::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #555;
        }

        .dark ::-webkit-scrollbar-track {
            background: #374151;
        }

        .dark ::-webkit-scrollbar-thumb {
            background: #6b7280;
        }

        .dark ::-webkit-scrollbar-thumb:hover {
            background: #9ca3af;
        }
    </style>
</head>

<body class="bg-gray-50 dark:bg-gray-900 min-h-screen">
    <div class="antialiased">
        <!-- Navigation -->
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
                        <span class="sr-only">Toggle sidebar</span>
                    </button>
                    <a href="/dentalemr_system/html/index.php?uid=<?php echo $userId; ?>" class="flex items-center justify-between mr-4">
                        <img src="https://th.bing.com/th/id/OIP.zjh8eiLAHY9ybXUCuYiqQwAAAA?r=0&rs=1&pid=ImgDetMain&cb=idpwebp1&o=7&rm=3"
                            class="mr-3 h-8 rounded-full" alt="MHO Dental Clinic Logo" />
                        <span class="self-center text-2xl font-semibold whitespace-nowrap dark:text-white">MHO Dental Clinic</span>
                    </a>
                </div>

                <!-- User Profile -->
                <div class="flex items-center space-x-3">
                    <div class="relative">
                        <button type="button" id="userDropdownButton"
                            class="flex items-center space-x-2 p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 dark:text-white transition-colors duration-200">
                            <!-- Profile Picture or Icon - UPDATED to use $displayPicture -->
                            <div class="w-8 h-8 rounded-full bg-gray-200 dark:bg-gray-700 flex items-center justify-center overflow-hidden">
                                <?php if (!empty($displayPicture)): ?>
                                    <img src="<?php echo htmlspecialchars($displayPicture); ?>"
                                        alt="Profile"
                                        class="w-full h-full object-cover"
                                        id="navProfilePicture">
                                <?php else: ?>
                                    <i class="fas fa-user text-gray-600 dark:text-gray-400" id="navProfileIcon"></i>
                                <?php endif; ?>
                            </div>

                            <!-- User Info (hidden on mobile) -->
                            <div class="hidden md:block text-left">
                                <div class="text-sm font-medium truncate max-w-[150px]" id="navUserName">
                                    <?php echo htmlspecialchars($loggedUser['name'] ?? 'User'); ?>
                                </div>
                                <div class="text-xs text-gray-500 dark:text-gray-400 truncate max-w-[150px]">
                                    <?php echo htmlspecialchars($loggedUser['type'] ?? 'User Type'); ?>
                                </div>
                            </div>

                            <!-- Dropdown Arrow -->
                            <i class="fas fa-chevron-down text-xs text-gray-500 transition-transform duration-200" id="dropdownArrow"></i>
                        </button>

                        <!-- Dropdown Menu -->
                        <div id="userDropdown"
                            class="absolute right-0 mt-2 w-64 bg-white dark:bg-gray-800 rounded-lg shadow-lg border border-gray-200 dark:border-gray-700 hidden z-50 transform transition-all duration-200 origin-top-right">
                            <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                                <div class="text-sm font-semibold text-gray-900 dark:text-white truncate" id="dropdownUserName">
                                    <?php echo htmlspecialchars($loggedUser['name'] ?? 'User'); ?>
                                </div>
                                <div class="text-xs text-gray-600 dark:text-gray-400 mt-1 truncate">
                                    <?php echo htmlspecialchars($loggedUser['email'] ?? 'user@example.com'); ?>
                                </div>
                            </div>
                            <div class="py-2">
                                <a href="#"
                                    class="flex items-center px-4 py-2.5 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 bg-blue-50 dark:bg-blue-900/20 transition-colors duration-150">
                                    <i class="fas fa-user-circle mr-3 text-blue-500 w-4 text-center"></i>
                                    <span>My Profile</span>
                                </a>
                                <a href="/dentalemr_system/html/manageusers/manageuser.php?uid=<?php echo $userId; ?>"
                                    class="flex items-center px-4 py-2.5 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors duration-150">
                                    <i class="fas fa-users-cog mr-3 text-gray-500 w-4 text-center"></i>
                                    <span>Manage Users</span>
                                </a>
                                <a href="/dentalemr_system/html/manageusers/systemlogs.php?uid=<?php echo $userId; ?>"
                                    class="flex items-center px-4 py-2.5 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors duration-150">
                                    <i class="fas fa-history mr-3 text-gray-500 w-4 text-center"></i>
                                    <span>System Logs</span>
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
                            <div class="border-t border-gray-200 dark:border-gray-700 py-2">
                                <a href="/dentalemr_system/php/login/logout.php?uid=<?php echo $loggedUser['id']; ?>"
                                    class="flex items-center px-4 py-2.5 text-sm text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors duration-150">
                                    <i class="fas fa-sign-out-alt mr-3 w-4 text-center"></i>
                                    <span>Sign Out</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Main Content -->
        <main class="pt-20 px-4 md:px-6 lg:px-8 pb-8 max-w-7xl mx-auto">
            <!-- Header -->
            <div class="mb-6 md:mb-8 fade-in">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-2xl md:text-3xl font-bold text-gray-900 dark:text-white">My Profile</h1>
                        <p class="text-gray-600 dark:text-gray-400 mt-1">Manage your account settings and profile information</p>
                    </div>
                    <div class="flex items-center space-x-2 text-sm text-gray-500 dark:text-gray-400">
                        <i class="fas fa-user-circle"></i>
                        <span>User ID: <?php echo htmlspecialchars($userData['id'] ?? 'N/A'); ?></span>
                    </div>
                </div>
            </div>

            <!-- Messages -->
            <?php if ($successMsg): ?>
                <div class="mb-6 p-4 bg-green-50 dark:bg-green-900/30 border border-green-200 dark:border-green-800 rounded-xl flex items-center fade-in">
                    <i class="fas fa-check-circle text-green-500 mr-3 text-lg"></i>
                    <span class="text-green-700 dark:text-green-300"><?php echo htmlspecialchars($successMsg); ?></span>
                </div>
            <?php endif; ?>

            <?php if ($errorMsg): ?>
                <div class="mb-6 p-4 bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-800 rounded-xl flex items-center fade-in">
                    <i class="fas fa-exclamation-circle text-red-500 mr-3 text-lg"></i>
                    <span class="text-red-700 dark:text-red-300"><?php echo htmlspecialchars($errorMsg); ?></span>
                </div>
            <?php endif; ?>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Left Column - Profile Picture & Basic Info -->
                <div class="lg:col-span-1 space-y-6">
                    <!-- Profile Card -->
                    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 card-hover">
                        <div class="p-6">
                            <!-- Profile Picture -->
                            <div class="flex flex-col items-center">
                                <div class="profile-picture-container mb-4">
                                    <img src="<?php echo htmlspecialchars($displayPicture); ?>"
                                        alt="Profile Picture"
                                        class="w-32 h-32 rounded-full object-cover border-4 border-white dark:border-gray-800 shadow-lg"
                                        id="profilePicturePreview">
                                    <label for="profilePictureInput" class="profile-picture-overlay">
                                        <div class="text-center">
                                            <i class="fas fa-camera text-white text-2xl mb-1"></i>
                                            <p class="text-white text-xs">Change Photo</p>
                                        </div>
                                    </label>
                                </div>

                                <h2 class="text-xl font-bold text-gray-900 dark:text-white text-center">
                                    <?php echo htmlspecialchars($userData['name'] ?? 'User'); ?>
                                </h2>
                                <div class="flex items-center mt-1 mb-4">
                                    <span class="px-3 py-1 bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 text-xs font-medium rounded-full">
                                        <i class="fas fa-user-tag mr-1"></i>
                                        <?php echo htmlspecialchars($loggedUser['type']); ?>
                                    </span>
                                </div>

                                <p class="text-gray-600 dark:text-gray-400 text-center text-sm mb-6">
                                    <i class="fas fa-envelope mr-1"></i>
                                    <?php echo htmlspecialchars($userData['email'] ?? 'No email provided'); ?>
                                </p>

                                <!-- Quick Stats -->
                                <div class="grid grid-cols-3 gap-3 w-full">
                                    <div class="text-center p-3 bg-blue-50 dark:bg-blue-900/20 rounded-xl">
                                        <div class="text-lg font-bold text-blue-600 dark:text-blue-400">
                                            <?php echo htmlspecialchars($userData['id'] ?? 'N/A'); ?>
                                        </div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">ID</div>
                                    </div>
                                    <div class="text-center p-3 bg-green-50 dark:bg-green-900/20 rounded-xl">
                                        <div class="text-lg font-bold text-green-600 dark:text-green-400">
                                            <?php
                                            $daysAgo = isset($userData['created_at']) ?
                                                floor((time() - strtotime($userData['created_at'])) / (60 * 60 * 24)) : 0;
                                            echo $daysAgo;
                                            ?>
                                        </div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">Days</div>
                                    </div>
                                    <div class="text-center p-3 bg-purple-50 dark:bg-purple-900/20 rounded-xl">
                                        <div class="text-sm font-bold text-purple-600 dark:text-purple-400">
                                            <?php echo htmlspecialchars($userData['username'] ?? 'N/A'); ?>
                                        </div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">Username</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="border-t border-gray-200 dark:border-gray-700 p-4">
                            <div class="text-sm text-gray-600 dark:text-gray-400">
                                <div class="flex items-center justify-between mb-2">
                                    <span><i class="fas fa-calendar-plus mr-2"></i> Account Created</span>
                                    <span class="font-medium text-gray-900 dark:text-white">
                                        <?php echo htmlspecialchars(date('M j, Y', strtotime($userData['created_at'] ?? 'now'))); ?>
                                    </span>
                                </div>
                                <?php if (isset($userData['last_login']) && $userData['last_login']): ?>
                                    <div class="flex items-center justify-between mb-2">
                                        <span><i class="fas fa-sign-in-alt mr-2"></i> Last Login</span>
                                        <span class="font-medium text-gray-900 dark:text-white">
                                            <?php echo htmlspecialchars(date('M j', strtotime($userData['last_login']))); ?>
                                        </span>
                                    </div>
                                <?php endif; ?>
                                <div class="flex items-center justify-between">
                                    <span><i class="fas fa-sync-alt mr-2"></i> Last Updated</span>
                                    <span class="font-medium text-gray-900 dark:text-white">
                                        <?php
                                        if (isset($userData['updated_at']) && $userData['updated_at']) {
                                            echo htmlspecialchars(date('M j, Y', strtotime($userData['updated_at'])));
                                        } else {
                                            echo 'Never';
                                        }
                                        ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4 flex items-center">
                            <i class="fas fa-bolt mr-2 text-yellow-500"></i>
                            Quick Actions
                        </h3>
                        <div class="space-y-3">
                            <a href="/dentalemr_system/html/index.php?uid=<?php echo $userId; ?>"
                                class="flex items-center p-3 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300">
                                <i class="fas fa-tachometer-alt mr-3 text-blue-500"></i>
                                <span>Dashboard</span>
                            </a>
                            <a href="/dentalemr_system/html/manageusers/manageuser.php?uid=<?php echo $userId; ?>"
                                class="flex items-center p-3 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300">
                                <i class="fas fa-users mr-3 text-green-500"></i>
                                <span>Manage Users</span>
                            </a>
                            <button onclick="showHelp()"
                                class="w-full flex items-center p-3 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300">
                                <i class="fas fa-question-circle mr-3 text-purple-500"></i>
                                <span>Help & Support</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Right Column - Forms -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- Profile Information Form -->
                    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 card-hover">
                        <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                            <h2 class="text-xl font-semibold text-gray-900 dark:text-white flex items-center">
                                <i class="fas fa-user-edit mr-2 text-blue-500"></i>
                                Edit Profile Information
                            </h2>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Update your personal details</p>
                        </div>

                        <form method="POST" action="" enctype="multipart/form-data" class="p-6">
                            <input type="file" id="profilePictureInput" name="profile_picture" accept="image/*" class="hidden" onchange="previewProfilePicture(this)">

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                                <div>
                                    <label class="block text-sm font-medium mb-2 text-gray-700 dark:text-gray-300">
                                        <i class="fas fa-user mr-1 text-gray-400"></i>
                                        Full Name *
                                    </label>
                                    <input type="text" name="name" value="<?php echo htmlspecialchars($userData['name'] ?? ''); ?>"
                                        class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-xl dark:text-gray-300 bg-white dark:bg-gray-700 input-focus focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200"
                                        placeholder="Enter your full name" required>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium mb-2 text-gray-700 dark:text-gray-300">
                                        <i class="fas fa-envelope mr-1 text-gray-400"></i>
                                        Email Address *
                                    </label>
                                    <input type="email" name="email" value="<?php echo htmlspecialchars($userData['email'] ?? ''); ?>"
                                        class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-xl dark:text-gray-300 bg-white dark:bg-gray-700 input-focus focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200"
                                        placeholder="your.email@example.com" required>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium mb-2 text-gray-700 dark:text-gray-300">
                                        <i class="fas fa-at mr-1 text-gray-400"></i>
                                        Username *
                                    </label>
                                    <input type="text" name="username" value="<?php echo htmlspecialchars($userData['username'] ?? ''); ?>"
                                        class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-xl dark:text-gray-300 bg-white dark:bg-gray-700 input-focus focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200"
                                        placeholder="Choose a username" required>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium mb-2 text-gray-700 dark:text-gray-300">
                                        <i class="fas fa-user-tag mr-1 text-gray-400"></i>
                                        User Role
                                    </label>
                                    <div class="px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-xl  bg-gray-50 dark:bg-gray-700 text-gray-700 dark:text-gray-300 flex items-center">
                                        <i class="fas fa-shield-alt mr-2 text-blue-500"></i>
                                        <?php echo htmlspecialchars($loggedUser['type']); ?>
                                    </div>
                                </div>

                                <div class="md:col-span-2">
                                    <label class="block text-sm font-medium mb-2 text-gray-700 dark:text-gray-300">
                                        <i class="fas fa-camera mr-1 text-gray-400"></i>
                                        Profile Picture
                                    </label>
                                    <div class="flex flex-col md:flex-row md:items-center space-y-3 md:space-y-0 md:space-x-4">
                                        <label for="profilePictureInput" class="cursor-pointer inline-flex">
                                            <div class="px-4 py-2.5 bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400 rounded-lg hover:bg-blue-100 dark:hover:bg-blue-900/30 transition-colors duration-200 flex items-center">
                                                <i class="fas fa-upload mr-2"></i>
                                                Upload New Photo
                                            </div>
                                        </label>
                                        <div class="text-sm text-gray-500 dark:text-gray-400">
                                            <p>Max 5MB • JPG, PNG, GIF, WebP</p>
                                            <p class="text-xs mt-1">Click on your profile picture above to preview</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-8 pt-6 border-t border-gray-200 dark:border-gray-700 flex flex-col sm:flex-row sm:items-center sm:justify-between">
                                <div class="text-sm text-gray-500 dark:text-gray-400 mb-4 sm:mb-0">
                                    <i class="fas fa-info-circle mr-1"></i>
                                    Fields marked with * are required
                                </div>
                                <div class="flex space-x-3">
                                    <button type="button" onclick="resetForm()"
                                        class="px-5 py-2.5 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 font-medium rounded-xl hover:bg-gray-50 dark:hover:bg-gray-700 transition-all duration-200">
                                        <i class="fas fa-undo mr-2"></i>
                                        Reset
                                    </button>
                                    <button type="submit" name="update_profile"
                                        class="px-5 py-2.5 bg-gradient-to-r from-blue-500 to-blue-600 text-white font-medium rounded-xl hover:from-blue-600 hover:to-blue-700 focus:ring-4 focus:ring-blue-300 dark:focus:ring-blue-800 transition-all duration-200 transform hover:-translate-y-0.5 flex items-center shadow-lg">
                                        <i class="fas fa-save mr-2"></i>
                                        Save Changes
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>

                    <!-- Change Password Form -->
                    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 card-hover">
                        <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                            <h2 class="text-xl font-semibold text-gray-900 dark:text-white flex items-center">
                                <i class="fas fa-lock mr-2 text-green-500"></i>
                                Security Settings
                            </h2>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Change your password to keep your account secure</p>
                        </div>

                        <form method="POST" action="" class="p-6">
                            <div class="space-y-5">
                                <div>
                                    <label class="block text-sm font-medium mb-2 text-gray-700 dark:text-gray-300">
                                        <i class="fas fa-key mr-1 text-gray-400"></i>
                                        Current Password *
                                    </label>
                                    <div class="relative">
                                        <input type="password" name="current_password" id="currentPassword"
                                            class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-xl bg-white dark:bg-gray-700 input-focus focus:ring-2 focus:ring-green-500 focus:border-transparent pr-10 transition-all duration-200"
                                            placeholder="Enter current password" required>
                                        <button type="button" onclick="togglePassword('currentPassword')"
                                            class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium mb-2 text-gray-700 dark:text-gray-300">
                                        <i class="fas fa-key mr-1 text-gray-400"></i>
                                        New Password *
                                    </label>
                                    <div class="relative">
                                        <input type="password" name="new_password" id="newPassword"
                                            class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-xl bg-white dark:bg-gray-700 input-focus focus:ring-2 focus:ring-green-500 focus:border-transparent pr-10 transition-all duration-200"
                                            placeholder="Enter new password" required>
                                        <button type="button" onclick="togglePassword('newPassword')"
                                            class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                    <div class="mt-2 grid grid-cols-2 gap-2 text-xs">
                                        <div class="flex items-center text-gray-500 dark:text-gray-400">
                                            <i class="fas fa-check-circle mr-1 text-green-500"></i>
                                            <span>8+ characters</span>
                                        </div>
                                        <div class="flex items-center text-gray-500 dark:text-gray-400">
                                            <i class="fas fa-check-circle mr-1 text-green-500"></i>
                                            <span>Letters & numbers</span>
                                        </div>
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium mb-2 text-gray-700 dark:text-gray-300">
                                        <i class="fas fa-key mr-1 text-gray-400"></i>
                                        Confirm New Password *
                                    </label>
                                    <div class="relative">
                                        <input type="password" name="confirm_password" id="confirmPassword"
                                            class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-xl bg-white dark:bg-gray-700 input-focus focus:ring-2 focus:ring-green-500 focus:border-transparent pr-10 transition-all duration-200"
                                            placeholder="Confirm new password" required>
                                        <button type="button" onclick="togglePassword('confirmPassword')"
                                            class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-8 pt-6 border-t border-gray-200 dark:border-gray-700">
                                <button type="submit" name="change_password"
                                    class="w-full px-6 py-3 bg-gradient-to-r from-green-500 to-green-600 text-white font-medium rounded-xl hover:from-green-600 hover:to-green-700 focus:ring-4 focus:ring-green-300 dark:focus:ring-green-800 transition-all duration-200 transform hover:-translate-y-0.5 flex items-center justify-center shadow-lg">
                                    <i class="fas fa-key mr-2"></i>
                                    Update Password
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </main>

        <!-- Help Modal -->
        <div id="helpModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center p-4">
            <div class="bg-white dark:bg-gray-800 rounded-2xl max-w-md w-full">
                <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white flex items-center">
                        <i class="fas fa-question-circle mr-2 text-blue-500"></i>
                        Help & Support
                    </h3>
                </div>
                <div class="p-6">
                    <p class="text-gray-600 dark:text-gray-400 mb-4">
                        Need help with your profile? Here are some tips:
                    </p>
                    <ul class="space-y-3 text-sm text-gray-600 dark:text-gray-400">
                        <li class="flex items-start">
                            <i class="fas fa-check text-green-500 mr-2 mt-1"></i>
                            <span>Profile pictures should be square images for best results</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check text-green-500 mr-2 mt-1"></i>
                            <span>Use a strong password with mixed characters</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check text-green-500 mr-2 mt-1"></i>
                            <span>Keep your email updated for important notifications</span>
                        </li>
                    </ul>
                </div>
                <div class="p-6 border-t border-gray-200 dark:border-gray-700 flex justify-end">
                    <button onclick="hideHelp()"
                        class="px-4 py-2 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>
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

    <script>
        // Toggle user dropdown menu
        document.addEventListener('DOMContentLoaded', function() {
            const dropdownButton = document.getElementById('userDropdownButton');
            const dropdownMenu = document.getElementById('userDropdown');
            const dropdownArrow = document.getElementById('dropdownArrow');

            if (dropdownButton && dropdownMenu) {
                // Toggle dropdown on button click
                dropdownButton.addEventListener('click', function(e) {
                    e.stopPropagation();
                    dropdownMenu.classList.toggle('hidden');
                    dropdownMenu.classList.toggle('opacity-0');
                    dropdownMenu.classList.toggle('opacity-100');
                    dropdownMenu.classList.toggle('scale-95');
                    dropdownMenu.classList.toggle('scale-100');

                    // Rotate arrow
                    dropdownArrow.classList.toggle('rotate-180');
                });

                // Close dropdown when clicking outside
                document.addEventListener('click', function(e) {
                    if (!dropdownButton.contains(e.target) && !dropdownMenu.contains(e.target)) {
                        dropdownMenu.classList.add('hidden', 'opacity-0', 'scale-95');
                        dropdownMenu.classList.remove('opacity-100', 'scale-100');
                        dropdownArrow.classList.remove('rotate-180');
                    }
                });

                // Close dropdown on Escape key
                document.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape' && !dropdownMenu.classList.contains('hidden')) {
                        dropdownMenu.classList.add('hidden', 'opacity-0', 'scale-95');
                        dropdownMenu.classList.remove('opacity-100', 'scale-100');
                        dropdownArrow.classList.remove('rotate-180');
                    }
                });
            }
        });

        // Toggle password visibility
        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const icon = input.parentElement.querySelector('button i');

            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.replace('fa-eye-slash', 'fa-eye');
            }
        }

        // Preview profile picture and update navigation immediately
        function previewProfilePicture(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const preview = document.getElementById('profilePicturePreview');
                    preview.src = e.target.result;

                    // Update navigation bar profile picture
                    const navProfileImg = document.getElementById('navProfilePicture');
                    const navProfileIcon = document.getElementById('navProfileIcon');

                    if (navProfileImg) {
                        navProfileImg.src = e.target.result;
                    } else if (navProfileIcon) {
                        // Replace icon with image
                        navProfileIcon.style.display = 'none';
                        const img = document.createElement('img');
                        img.src = e.target.result;
                        img.alt = 'Profile';
                        img.className = 'w-full h-full object-cover';
                        img.id = 'navProfilePicture';
                        navProfileIcon.parentElement.appendChild(img);
                    }

                    showNotification('Profile picture preview updated. Click Save Changes to apply.', 'success');
                }
                reader.readAsDataURL(input.files[0]);
            }
        }

        // Function to update navigation after successful profile update
        function updateNavigationAfterProfileUpdate(newName, newEmail, newProfilePicture = null) {
            // Update user name in navigation
            const navUserName = document.getElementById('navUserName');
            const dropdownUserName = document.getElementById('dropdownUserName');

            if (navUserName) navUserName.textContent = newName;
            if (dropdownUserName) dropdownUserName.textContent = newName;

            // Update profile picture in navigation if changed
            if (newProfilePicture) {
                const navProfileImg = document.getElementById('navProfilePicture');
                const navProfileIcon = document.getElementById('navProfileIcon');

                if (navProfileImg) {
                    navProfileImg.src = newProfilePicture;
                } else if (navProfileIcon) {
                    // Replace icon with image
                    navProfileIcon.style.display = 'none';
                    const img = document.createElement('img');
                    img.src = newProfilePicture;
                    img.alt = 'Profile';
                    img.className = 'w-full h-full object-cover';
                    img.id = 'navProfilePicture';
                    navProfileIcon.parentElement.appendChild(img);
                }
            }

            // Update main profile picture to ensure consistency
            const mainProfilePic = document.getElementById('profilePicturePreview');
            if (mainProfilePic && newProfilePicture) {
                mainProfilePic.src = newProfilePicture;
            }
        }

        // Reset form
        function resetForm() {
            if (confirm('Are you sure you want to reset all changes?')) {
                document.querySelector('form').reset();
                showNotification('Form has been reset to original values.', 'info');
            }
        }

        // Show/Hide help modal
        function showHelp() {
            document.getElementById('helpModal').classList.remove('hidden');
        }

        function hideHelp() {
            document.getElementById('helpModal').classList.add('hidden');
        }

        // Inactivity timer
        let inactivityTimer;
        const inactivityLimit = 600000; // 10 minutes

        function resetInactivityTimer() {
            clearTimeout(inactivityTimer);
            inactivityTimer = setTimeout(() => {
                window.location.href = '/dentalemr_system/php/login/logout.php?uid=<?php echo $userId; ?>&reason=inactivity';
            }, inactivityLimit);
        }

        // Reset timer on user activity
        ['click', 'mousemove', 'keypress', 'scroll', 'touchstart'].forEach(event => {
            document.addEventListener(event, resetInactivityTimer);
        });

        // Start timer
        resetInactivityTimer();

        // Notification function
        function showNotification(message, type = 'info') {
            // Remove existing notification
            const existing = document.querySelector('.notification-toast');
            if (existing) existing.remove();

            const notification = document.createElement('div');
            notification.className = `notification-toast fixed top-4 right-4 px-6 py-3 rounded-xl shadow-lg z-50 transform translate-x-full opacity-0 transition-all duration-300 ${
                type === 'error' ? 'bg-red-500 text-white' : 
                type === 'success' ? 'bg-green-500 text-white' : 
                'bg-blue-500 text-white'
            }`;
            notification.innerHTML = `
                <div class="flex items-center">
                    <i class="fas ${type === 'error' ? 'fa-exclamation-circle' : type === 'success' ? 'fa-check-circle' : 'fa-info-circle'} mr-2"></i>
                    <span>${message}</span>
                </div>
            `;
            document.body.appendChild(notification);

            setTimeout(() => {
                notification.classList.remove('translate-x-full', 'opacity-0');
                notification.classList.add('translate-x-0', 'opacity-100');
            }, 10);

            setTimeout(() => {
                notification.classList.remove('translate-x-0', 'opacity-100');
                notification.classList.add('translate-x-full', 'opacity-0');
                setTimeout(() => notification.remove(), 300);
            }, 3000);
        }

        // Form validation
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function(e) {
                const inputs = this.querySelectorAll('input[required]');
                let valid = true;

                inputs.forEach(input => {
                    if (!input.value.trim()) {
                        input.classList.add('border-red-500', 'bg-red-50', 'dark:bg-red-900/20');
                        valid = false;
                    } else {
                        input.classList.remove('border-red-500', 'bg-red-50', 'dark:bg-red-900/20');
                    }
                });

                if (!valid) {
                    e.preventDefault();
                    showNotification('Please fill in all required fields marked with *.', 'error');
                }
            });
        });

        // Add responsive classes on resize
        window.addEventListener('resize', function() {
            const width = window.innerWidth;
            const cards = document.querySelectorAll('.card-hover');

            if (width < 768) {
                cards.forEach(card => {
                    card.classList.remove('card-hover');
                });
            } else {
                cards.forEach(card => {
                    card.classList.add('card-hover');
                });
            }
        });

        // Initialize
        window.addEventListener('load', function() {
            // Check screen size on load
            window.dispatchEvent(new Event('resize'));

            // Smooth scroll to top
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });

        // Close help modal on ESC key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                hideHelp();
            }
        });

        // Close help modal when clicking outside
        document.getElementById('helpModal').addEventListener('click', function(e) {
            if (e.target === this) {
                hideHelp();
            }
        });

        // Auto-update navigation when page loads (in case of form submission)
        window.addEventListener('load', function() {
            // Check if we have success message (meaning form was submitted)
            const successMsg = document.querySelector('.text-green-700, .text-green-300');
            if (successMsg && successMsg.textContent.includes('Profile updated')) {
                // The page will reload with new data, but we can force a small delay to ensure DOM is ready
                setTimeout(() => {
                    // Update navigation with current data
                    const userName = document.querySelector('input[name="name"]').value;
                    const navUserName = document.getElementById('navUserName');
                    const dropdownUserName = document.getElementById('dropdownUserName');

                    if (navUserName) navUserName.textContent = userName;
                    if (dropdownUserName) dropdownUserName.textContent = userName;
                }, 100);
            }
        });
    </script>
</body>

</html>