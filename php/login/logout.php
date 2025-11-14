<?php
session_start();

if (!isset($_GET['id'])) {
    // If no user ID provided, do NOT destroy everyone
    header("Location: /dentalemr_system/html/login/login.html");
    exit;
}

$userId = intval($_GET['id']);

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
header("Location: /dentalemr_system/html/login/login.html");
exit;
