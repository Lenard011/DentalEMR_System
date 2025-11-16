<?php
session_start();
require_once '../conn.php';
date_default_timezone_set('Asia/Manila');
require_once '../../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /dentalemr_system/html/login/login.html');
    exit;
}

// Get arrays from POST; ensure even single entries are treated as arrays
$usernames = isset($_POST['staffusername']) ? (array)$_POST['staffusername'] : [];
$passwords = isset($_POST['staffpassword']) ? (array)$_POST['staffpassword'] : [];
$userTypes = isset($_POST['userType']) ? (array)$_POST['userType'] : [];

if (!count($usernames)) {
    echo "<script>
        alert('No login data submitted.');
        window.location.href='/dentalemr_system/html/login/login.html';
    </script>";
    exit;
}

$firstSuccess = false; // Track first successful login

for ($i = 0; $i < count($usernames); $i++) {
    $username = trim($usernames[$i] ?? '');
    $password = trim($passwords[$i] ?? '');
    $userType = trim($userTypes[$i] ?? '');

    // Basic validation
    if (empty($username) || empty($password) || $userType !== 'Staff') {
        continue;
    }

    // Fetch staff user
    $stmt = mysqli_prepare($conn, "SELECT * FROM staff WHERE username = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, "s", $username);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user = mysqli_fetch_assoc($result);

    if (!$user) continue;

    // Verify password with salt
    if (!password_verify($password . $user['salt'], $user['password_hash'])) continue;

    // Generate MFA code
    $mfaCode = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $expiresAt = date('Y-m-d H:i:s', time() + 300);

    $insert = mysqli_prepare($conn, "
        INSERT INTO mfa_codes (user_id, user_type, code, expires_at, used, created_at)
        VALUES (?, ?, ?, ?, 0, NOW())
    ");
    mysqli_stmt_bind_param($insert, "isss", $user['id'], $userType, $mfaCode, $expiresAt);
    mysqli_stmt_execute($insert);

    // Send MFA code to email associated with username
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'jayjaypanganiban40@gmail.com';
        $mail->Password = 'arwvbgsldrlvetaf';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        $mail->setFrom('jayjaypanganiban40@gmail.com', 'Dental EMR');
        $mail->addAddress($user['email'], $user['name']);
        $mail->isHTML(true);
        $mail->Subject = 'Your Staff MFA Code';
        $mail->Body = "
            <p>Dear <strong>{$user['name']}</strong>,</p>
            <p>Your verification code is:</p>
            <h2 style='letter-spacing:3px;color:#2563EB;'>{$mfaCode}</h2>
            <p>This code will expire in 5 minutes.</p>
            <br><p>Best regards,<br>Dental EMR System</p>
        ";
        $mail->send();

        // Store FIRST user here (primary pending MFA)
        $_SESSION['pending_user'] = [
            'id'    => $user['id'],
            'type'  => $userType,
            'email' => $user['email']
        ];

        // Ensure multi-login pending buffer exists
        if (!isset($_SESSION['pending_users'])) {
            $_SESSION['pending_users'] = [];
        }

        // Store all pending MFA users
        $_SESSION['pending_users'][$user['id']] = [
            'id'    => $user['id'],
            'type'  => $userType,
            'email' => $user['email']
        ];


        // Redirect after the first successful login
        if (!$firstSuccess) {
            $firstSuccess = true;
            echo "<script>
                alert('Login successful! A verification code has been sent to your email.');
                window.location.href='/dentalemr_system/php/login/verify_mfa.php';
            </script>";
            exit;
        }
    } catch (Exception $e) {
        error_log("Mail error for {$user['email']}: " . $mail->ErrorInfo);
        continue;
    }
}

// If no successful login
if (!$firstSuccess) {
    echo "<script>
        alert('Login failed. Please check your credentials or contact support.');
        window.location.href='/dentalemr_system/html/login/login.html';
    </script>";
    exit;
}
