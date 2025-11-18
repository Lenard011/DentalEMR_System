<?php
session_start();
date_default_timezone_set('Asia/Manila');

// Include the activity logger
require_once '..///manageusers/activity_logger.php';

// Check if user ID is provided
if (!isset($_GET['uid'])) {
    header('Location: /dentalemr_system/html/login/login.html');
    exit;
}

$userId = intval($_GET['uid']);

// Get user info before removing from session
$userName = '';
$userEmail = '';

if (isset($_SESSION['active_sessions'][$userId])) {
    $userSession = $_SESSION['active_sessions'][$userId];

    // Debug: Check what's in the session
    error_log("User Session Data: " . print_r($userSession, true));

    // Try different possible keys for the name
    $userName = $userSession['name'] ??
        $userSession['username'] ??
        $userSession['user_name'] ??
        $userSession['full_name'] ??
        'Unknown User';

    $userEmail = $userSession['email'] ?? '';

    // If we still don't have a name, try to get it from the database
    if (empty($userName) || $userName === 'Unknown User') {
        $userName = getUserNameFromDB($userId);
    }
}

// DATABASE CONNECTION FOR LOGGING (PDO for activity_logger.php)
try {
    $host = "localhost";
    $dbUser = "root";
    $dbPass = "";
    $dbName = "dentalemr_system";

    $pdo = new PDO("mysql:host=$host;dbname=$dbName;charset=utf8", $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Log the logout activity using your existing function
    logActivity($pdo, $userId, $userName, 'Logout', 'System', 'User logged out successfully');
} catch (PDOException $e) {
    // Continue with logout even if logging fails
    error_log("Database connection failed: " . $e->getMessage());
}

// Remove ONLY this user session
if (isset($_SESSION['active_sessions'][$userId])) {
    unset($_SESSION['active_sessions'][$userId]);
}

// If no one is logged in anymore, destroy the whole session
if (empty($_SESSION['active_sessions'])) {
    session_unset();
    session_destroy();
}

// Redirect back to login
header('Location: /dentalemr_system/html/login/login.html');
exit;

// Function to get user name from database if not in session
function getUserNameFromDB($userId)
{
    $host = "localhost";
    $dbUser = "root";
    $dbPass = "";
    $dbName = "dentalemr_system";

    try {
        $conn = new mysqli($host, $dbUser, $dbPass, $dbName);

        if ($conn->connect_error) {
            return 'Unknown User';
        }

        // Try different tables that might contain user names
        $tablesToCheck = [
            "SELECT name FROM dentist WHERE id = ?",
            "SELECT name FROM staff WHERE id = ?",
            "SELECT name FROM users WHERE id = ?",
            "SELECT username FROM users WHERE id = ?",
            "SELECT CONCAT(first_name, ' ', last_name) as name FROM users WHERE id = ?"
        ];

        foreach ($tablesToCheck as $sql) {
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param("i", $userId);
                $stmt->execute();
                $stmt->bind_result($name);
                if ($stmt->fetch() && !empty($name)) {
                    $stmt->close();
                    $conn->close();
                    return $name;
                }
                $stmt->close();
            }
        }

        $conn->close();
        return 'Unknown User';
    } catch (Exception $e) {
        error_log("Failed to get user name from DB: " . $e->getMessage());
        return 'Unknown User';
    }
}
