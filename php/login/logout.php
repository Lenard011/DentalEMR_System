<?php
session_start();
date_default_timezone_set('Asia/Manila');

// Include the activity logger
require_once '../manageusers/activity_logger.php';

// Check if user ID is provided
if (!isset($_GET['uid'])) {
    header('Location: /DentalEMR_System/html/login/login.html');
    exit;
}

$userId = intval($_GET['uid']);

// Get user info before removing from session
$userName = 'Unknown User';
$userEmail = '';

if (isset($_SESSION['active_sessions'][$userId])) {
    $userSession = $_SESSION['active_sessions'][$userId];

    $userName = $userSession['name'] ??
        $userSession['email'] ??
        'Unknown User';
    $userEmail = $userSession['email'] ?? '';
}

// DATABASE CONNECTION FOR LOGGING
try {
    $host = "localhost";
    $dbUser = "u401132124_dentalclinic";
    $dbPass = "Mho_DentalClinic1st";
    $dbName = "u401132124_mho_dentalemr";

    $pdo = new PDO("mysql:host=$host;dbname=$dbName;charset=utf8", $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Log the logout activity
    logActivity($pdo, $userId, $userName, 'Logout', 'System', 'User logged out successfully');
} catch (PDOException $e) {
    // Continue with logout even if logging fails
    error_log("Logout logging failed: " . $e->getMessage());
}

// Remove ONLY this user session
if (isset($_SESSION['active_sessions'][$userId])) {
    unset($_SESSION['active_sessions'][$userId]);
}

// If no one is logged in anymore, destroy the whole session
if (empty($_SESSION['active_sessions'])) {
    session_unset();
    session_destroy();
} else {
    // Otherwise, just unset the specific user session
    unset($_SESSION['active_sessions'][$userId]);
}

// Redirect back to login
header('Location: /DentalEMR_System/html/login/login.html');
exit;
