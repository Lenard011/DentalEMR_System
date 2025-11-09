<?php
session_start();
require_once 'db_connect.php';
date_default_timezone_set('Asia/Manila');
require_once '../../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $userType = trim($_POST['user_type'] ?? '');

    if (empty($email) || empty($password) || empty($userType)) {
        echo "<script>alert('Please fill in all fields.'); window.location.href='/dentalemr_system/html/login/login.html';</script>";
        exit;
    }

    $table = match ($userType) {
        'Dentist' => 'dentist',
        'Staff'   => 'staff',
        default   => null
    };

    if (!$table) {
        echo "<script>alert('Invalid user type.'); window.location.href='/dentalemr_system/html/login/login.html';</script>";
        exit;
    }

    // Check if email exists first
    $stmt = $pdo->prepare("SELECT * FROM {$table} WHERE email = :email LIMIT 1");
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo "<script>alert('Email not found. Please check and try again.'); window.location.href='/dentalemr_system/html/login/login.html';</script>";
        exit;
    }

    // Check password
    if (!password_verify($password . $user['salt'], $user['password_hash'])) {
        echo "<script>alert('Incorrect password. Please try again.'); window.location.href='/dentalemr_system/html/login/login.html';</script>";
        exit;
    }

    // Clean expired MFA codes
    $pdo->prepare("DELETE FROM mfa_codes WHERE (expires_at <= NOW()) OR (used = 1)")->execute();

    // Generate new MFA code
    $mfaCode = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $expiresAt = date('Y-m-d H:i:s', time() + 300);

    $insert = $pdo->prepare("
        INSERT INTO mfa_codes (user_id, user_type, code, expires_at, used, created_at)
        VALUES (:uid, :utype, :code, :expires, 0, NOW())
    ");
    $insert->execute([
        'uid' => $user['id'],
        'utype' => $userType,
        'code' => $mfaCode,
        'expires' => $expiresAt
    ]);

    // Send MFA code via email
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'lenardovinci64@gmail.com'; // Your Gmail
        $mail->Password = 'gvce aguf zgwa mgpp'; // Gmail app password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        $mail->setFrom('lenardovinci64@gmail.com', 'Dental Record System');
        $mail->addAddress($user['email'], $user['name']);
        $mail->isHTML(true);
        $mail->Subject = 'Your Multi-Factor Authentication Code';
        $mail->Body = "
            <p>Dear <strong>{$user['name']}</strong>,</p>
            <p>Your verification code is:</p>
            <h2 style='letter-spacing:3px;color:#2563EB;'>{$mfaCode}</h2>
            <p>This code will expire in 5 minutes.</p>
            <br><p>Best regards,<br>Dental Record System</p>
        ";
        $mail->send();

        $_SESSION['pending_user'] = [
            'id' => $user['id'],
            'type' => $userType,
            'email' => $user['email']
        ];

        echo "<script>alert('Login successful! A verification code sent to your email.'); window.location.href='verify_mfa.php';</script>";
        exit;
    } catch (Exception $e) {
        error_log('Mail error: ' . $mail->ErrorInfo);
        echo "<script>alert('Could not send verification code. Please contact support.'); window.location.href='/dentalemr_system/html/login/login.html';</script>";
        exit;
    }
} else {
    header('Location: /dentalemr_system/html/login/login.html');
    exit;
}
