<?php
session_start();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type'])) {
    header("Location: /dentalemr_system/html/login/login.html");
    exit;
}
