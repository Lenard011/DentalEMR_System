<?php
session_start();
require_once 'db_connect.php';
date_default_timezone_set('Asia/Manila');
require_once '../../vendor/autoload.php';

// Include the activity logger
require_once '..//manageusers/activity_logger.php';

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
        // Log failed login attempt
        logActivity($pdo, 0, 'Unknown User', 'Failed Login', 'System', "Failed login attempt with email: {$email}", $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']);
        echo "<script>alert('Email not found. Please check and try again.'); window.location.href='/dentalemr_system/html/login/login.html';</script>";
        exit;
    }

    // Check password
    if (!password_verify($password . $user['salt'], $user['password_hash'])) {
        // Log failed login attempt
        logActivity($pdo, $user['id'], $user['name'], 'Failed Login', 'System', "Failed password attempt for user: {$user['name']}", $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']);
        echo "<script>alert('Incorrect password. Please try again.'); window.location.href='/dentalemr_system/html/login/login.html';</script>";
        exit;
    }

    // Check if user already verified for current period (resets at 11:59 PM)
    $currentTime = date('H:i:s');
    $resetTime = '23:59:00'; // 11:59 PM

    // If current time is before 11:59 PM, check for today's verification
    // If current time is after 11:59 PM, consider it as next day
    if ($currentTime < $resetTime) {
        $checkDate = date('Y-m-d');
    } else {
        $checkDate = date('Y-m-d', strtotime('+1 day'));
    }

    $stmt = $pdo->prepare("
        SELECT * FROM daily_verifications 
        WHERE user_id = :uid 
        AND user_type = :utype 
        AND verification_date = :check_date
        LIMIT 1
    ");
    $stmt->execute([
        'uid' => $user['id'],
        'utype' => $userType,
        'check_date' => $checkDate
    ]);
    $alreadyVerified = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($alreadyVerified) {
        // User already verified for current period - skip MFA and log them in directly
        if (!isset($_SESSION['active_sessions'])) {
            $_SESSION['active_sessions'] = [];
        }

        $_SESSION['active_sessions'][$user['id']] = [
            'id'    => $user['id'],
            'email' => $user['email'],
            'type'  => $userType,
            'login_time' => time()
        ];

        // Update last verification time
        $pdo->prepare("
            UPDATE daily_verifications 
            SET last_verification_time = NOW() 
            WHERE user_id = :uid AND user_type = :utype AND verification_date = :check_date
        ")->execute([
            'uid' => $user['id'],
            'utype' => $userType,
            'check_date' => $checkDate
        ]);

        // Log the login
        logActivity($pdo, $user['id'], $user['name'], 'Login', 'System', "Daily verification bypass - already verified for current period", $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']);

        // Redirect directly to dashboard
        $redirect = $userType === 'Dentist'
            ? "/dentalemr_system/html/index.php?uid={$user['id']}"
            : "/dentalemr_system/html/a_staff/addpatient.php?uid={$user['id']}";

        echo "<script>
            alert('Login successful! Welcome back.');
            window.location.href='{$redirect}';
        </script>";
        exit;
    }

    // User needs MFA verification (first login of the period)
    // Clean expired MFA codes
    $pdo->prepare("DELETE FROM mfa_codes WHERE expires_at <= NOW() OR used = 1")->execute();

    // Generate new MFA code
    $mfaCode = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $expiresAt = date('Y-m-d H:i:s', time() + 300);

    $insert = $pdo->prepare("
        INSERT INTO mfa_codes (user_id, user_type, code, expires_at, used, created_at, sent_at)
        VALUES (:uid, :utype, :code, :expires, 0, NOW(), NOW())
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
        $mail->Username = 'lenardovinci64@gmail.com';
        $mail->Password = 'gvce aguf zgwa mgpp';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        $mail->setFrom('lenardovinci64@gmail.com', 'Dental Record System');
        $mail->addAddress($user['email'], $user['name']);
        $mail->isHTML(true);
        $mail->Subject = 'Your Daily Verification Code';
        $mail->Body = "
            <p>Dear <strong>{$user['name']}</strong>,</p>
            <p>Your verification code is:</p>
            <h2 style='letter-spacing:3px;color:#2563EB;'>{$mfaCode}</h2>
            <p>This code will expire in 5 minutes.</p>
            <p><em>Note: You only need to verify once daily (resets at 11:59 PM).</em></p>
            <br><p>Best regards,<br>Dental Record System</p>
        ";
        $mail->send();

        // Log successful login attempt and MFA sent
        logActivity($pdo, $user['id'], $user['name'], 'Login', 'System', "Daily MFA code sent to {$user['email']}", $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']);
    } catch (Exception $e) {
        // Log email sending failure
        logActivity($pdo, $user['id'], $user['name'], 'Email Failed', 'System', "Failed to send MFA code to {$user['email']}", $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']);

        error_log('Mail error: ' . $mail->ErrorInfo);
        echo "<script>alert('Could not send verification code. Please contact support.'); window.location.href='/dentalemr_system/html/login/login.html';</script>";
        exit;
    }

    $_SESSION['pending_user'] = [
        'id'    => $user['id'],
        'type'  => $userType,
        'email' => $user['email']
    ];

    echo "<script>alert('Login successful! A verification code has been sent to your email.'); window.location.href='verify_mfa.php';</script>";
    exit;
} else {
    header('Location: /dentalemr_system/html/login/login.html');
    exit;
}
