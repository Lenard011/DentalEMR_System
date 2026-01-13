<?php
session_start();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type'])) {
    header("Location: /DentalEMR_System/html/login/login.html");
    exit;
}
