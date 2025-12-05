<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once '../conn.php';
date_default_timezone_set('Asia/Manila');

// Enhanced input validation
function validateEmail($email)
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function validatePassword($password)
{
    return strlen($password) >= 6;
}

function sanitizeInput($input)
{
    return trim(htmlspecialchars($input, ENT_QUOTES, 'UTF-8'));
}

// Improved response function
function response($message, $userId = null, $type = 'info')
{
    if (!$userId && isset($_SESSION['active_sessions']) && !empty($_SESSION['active_sessions'])) {
        $sessions = array_keys($_SESSION['active_sessions']);
        $userId = $sessions[0];
    }

    $redirectUrl = $userId
        ? "/dentalemr_system/html/manageusers/manageuser.php?uid=" . $userId
        : "/dentalemr_system/html/manageusers/manageuser.php";

    $alertType = $type === 'error' ? 'error' : 'info';

    echo "<script>
        alert('" . addslashes($message) . "');
        window.location.href='{$redirectUrl}';
    </script>";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get current user ID for redirect
    $currentUserId = null;
    if (isset($_GET['uid']) && is_numeric($_GET['uid'])) {
        $currentUserId = intval($_GET['uid']);
    } elseif (isset($_SESSION['active_sessions']) && !empty($_SESSION['active_sessions'])) {
        $sessions = array_keys($_SESSION['active_sessions']);
        $currentUserId = $sessions[0];
    }

    // Ensure single entries are converted to arrays
    $fullnames = isset($_POST['fullname']) ? (array)$_POST['fullname'] : [];
    $usernames = isset($_POST['username']) ? (array)$_POST['username'] : [];
    $emails = isset($_POST['email']) ? (array)$_POST['email'] : [];
    $passwords = isset($_POST['password']) ? (array)$_POST['password'] : [];
    $confirmPasswords = isset($_POST['confirm_password']) ? (array)$_POST['confirm_password'] : [];

    if (empty($fullnames) || empty($fullnames[0])) {
        response("No staff data submitted.", $currentUserId, 'error');
    }

    $successCount = 0;
    $failedEntries = [];

    // Start transaction for data consistency
    mysqli_begin_transaction($conn);

    try {
        for ($i = 0; $i < count($fullnames); $i++) {
            $fullname = sanitizeInput($fullnames[$i] ?? '');
            $username = sanitizeInput($usernames[$i] ?? '');
            $email = sanitizeInput($emails[$i] ?? '');
            $password = $passwords[$i] ?? '';
            $confirmPassword = $confirmPasswords[$i] ?? '';

            // Validate required fields
            if (empty($fullname) || empty($username) || empty($email) || empty($password)) {
                $failedEntries[] = $email ?: "(missing email) - Required fields missing";
                continue;
            }

            // Validate email format
            if (!validateEmail($email)) {
                $failedEntries[] = $email . " - Invalid email format";
                continue;
            }

            // Validate password strength
            if (!validatePassword($password)) {
                $failedEntries[] = $email . " - Password must be at least 6 characters";
                continue;
            }

            if ($password !== $confirmPassword) {
                $failedEntries[] = $email . " - Passwords do not match";
                continue;
            }

            // Check duplicate email
            $check = mysqli_prepare($conn, "SELECT id FROM staff WHERE email = ? OR username = ?");
            mysqli_stmt_bind_param($check, "ss", $email, $username);
            mysqli_stmt_execute($check);
            mysqli_stmt_store_result($check);

            if (mysqli_stmt_num_rows($check) > 0) {
                $failedEntries[] = $email . " - Email or username already exists";
                mysqli_stmt_close($check);
                continue;
            }
            mysqli_stmt_close($check);

            // Hash password with salt
            $salt = bin2hex(random_bytes(16));
            $passwordHash = password_hash($password . $salt, PASSWORD_DEFAULT);

            // Insert staff
            $stmt = mysqli_prepare($conn, "
                INSERT INTO staff (name, username, email, password_hash, salt, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, NOW(), NOW())
            ");

            if (!$stmt) {
                $failedEntries[] = $email . " - Database error";
                continue;
            }

            mysqli_stmt_bind_param($stmt, "sssss", $fullname, $username, $email, $passwordHash, $salt);
            $inserted = mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            if ($inserted) {
                $successCount++;

                // Log successful creation
                if ($currentUserId) {
                    // log_history($currentUserId, "CREATE_STAFF", "Created staff account: $email");
                }
            } else {
                $failedEntries[] = $email . " - Insert failed";
            }
        }

        // Commit transaction
        mysqli_commit($conn);

        $message = "Staff accounts processed. Successfully added: {$successCount}.";
        if (!empty($failedEntries)) {
            $message .= " Failed: " . implode("; ", array_slice($failedEntries, 0, 5));
            if (count($failedEntries) > 5) {
                $message .= " and " . (count($failedEntries) - 5) . " more";
            }
        }

        response($message, $currentUserId, $successCount > 0 ? 'info' : 'error');
    } catch (Exception $e) {
        // Rollback on error
        mysqli_rollback($conn);
        error_log("Add staff transaction error: " . $e->getMessage());
        response("Error processing staff accounts. Please try again.", $currentUserId, 'error');
    }
} else {
    // If not POST request, redirect back
    $currentUserId = isset($_GET['uid']) && is_numeric($_GET['uid']) ? intval($_GET['uid']) : null;
    $redirectUrl = "/dentalemr_system/html/manageusers/manageuser.php" . ($currentUserId ? "?uid=" . $currentUserId : "");
    header("Location: " . $redirectUrl);
    exit;
}
