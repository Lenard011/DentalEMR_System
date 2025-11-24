<?php
session_start();
require_once '../conn.php';
date_default_timezone_set('Asia/Manila');
require_once '../../vendor/autoload.php';

// Include the activity logger (PDO version)
require_once '..//manageusers/activity_logger.php';

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

// Create PDO connection for activity logging (in addition to your existing mysqli connection)
try {
    $pdo = new PDO("mysql:host=localhost;dbname=dentalemr_system", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    // If PDO fails, we'll continue without logging but main functionality remains
    $pdo = null;
}

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

    if (!$user) {
        // Log failed login attempt
        if ($pdo) {
            logActivity($pdo, 0, 'Unknown Staff', 'Failed Login', 'System', "Failed login attempt with username: {$username}", $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']);
        }
        continue;
    }

    // Verify password with salt
    if (!password_verify($password . $user['salt'], $user['password_hash'])) {
        // Log failed password attempt
        if ($pdo) {
            logActivity($pdo, $user['id'], $user['name'], 'Failed Login', 'System', "Failed password attempt for staff: {$user['name']}", $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']);
        }
        continue;
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

    $checkStmt = mysqli_prepare($conn, "
        SELECT * FROM daily_verifications 
        WHERE user_id = ? 
        AND user_type = ? 
        AND verification_date = ?
        LIMIT 1
    ");
    mysqli_stmt_bind_param($checkStmt, "iss", $user['id'], $userType, $checkDate);
    mysqli_stmt_execute($checkStmt);
    $result = mysqli_stmt_get_result($checkStmt);
    $alreadyVerified = mysqli_fetch_assoc($result);

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
        $updateStmt = mysqli_prepare($conn, "
            UPDATE daily_verifications 
            SET last_verification_time = NOW() 
            WHERE user_id = ? AND user_type = ? AND verification_date = ?
        ");
        mysqli_stmt_bind_param($updateStmt, "iss", $user['id'], $userType, $checkDate);
        mysqli_stmt_execute($updateStmt);

        // Log the login
        if ($pdo) {
            logActivity($pdo, $user['id'], $user['name'], 'Login', 'System', "Daily verification bypass - already verified for current period", $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']);
        }

        // Store session for multi-login support
        $_SESSION['pending_user'] = [
            'id'    => $user['id'],
            'type'  => $userType,
            'email' => $user['email']
        ];

        if (!isset($_SESSION['pending_users'])) {
            $_SESSION['pending_users'] = [];
        }
        $_SESSION['pending_users'][$user['id']] = [
            'id'    => $user['id'],
            'type'  => $userType,
            'email' => $user['email']
        ];

        // Redirect directly to dashboard
        if (!$firstSuccess) {
            $firstSuccess = true;
            echo "<script>
                alert('Login successful! Welcome back.');
                window.location.href='/dentalemr_system/html/a_staff/addpatient.php?uid={$user['id']}';
            </script>";
            exit;
        }
        continue;
    }

    // User needs MFA verification (first login of the period)
    // Clean expired MFA codes
    mysqli_query($conn, "DELETE FROM mfa_codes WHERE expires_at <= NOW() OR used = 1");

    // Generate new MFA code
    $mfaCode = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $expiresAt = date('Y-m-d H:i:s', time() + 300);

    $insert = mysqli_prepare($conn, "
        INSERT INTO mfa_codes (user_id, user_type, code, expires_at, used, created_at, sent_at)
        VALUES (?, ?, ?, ?, 0, NOW(), NOW())
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
            <p><em>Note: You only need to verify once daily (resets at 11:59 PM).</em></p>
            <br><p>Best regards,<br>Dental EMR System</p>
        ";
        $mail->send();

        // Log successful login and MFA sent
        if ($pdo) {
            logActivity($pdo, $user['id'], $user['name'], 'Login', 'System', "Daily MFA code sent to staff: {$user['email']}", $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']);
        }
    } catch (Exception $e) {
        // Log email sending failure
        if ($pdo) {
            logActivity($pdo, $user['id'], $user['name'], 'Email Failed', 'System', "Failed to send MFA code to staff: {$user['email']}", $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']);
        }

        error_log("Mail error for {$user['email']}: " . $mail->ErrorInfo);
        continue;
    }

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
}

// If no successful login
if (!$firstSuccess) {
    echo "<script>
        alert('Login failed. Please check your credentials or contact support.');
        window.location.href='/dentalemr_system/html/login/login.html';
    </script>";
    exit;
}
