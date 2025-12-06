<?php
session_start();
require_once 'db_connect.php';
date_default_timezone_set('Asia/Manila');
require_once '../../vendor/autoload.php';

// Include the activity logger
require_once '../manageusers/activity_logger.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $userType = trim($_POST['user_type'] ?? '');

    if (empty($email) || empty($password) || empty($userType)) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Please fill in all fields.',
            'details' => 'missing_fields'
        ]);
        exit;
    }

    $table = match ($userType) {
        'Dentist' => 'dentist',
        'Staff'   => 'staff',
        default   => null
    };

    if (!$table) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid user type.',
            'details' => 'invalid_user_type'
        ]);
        exit;
    }

    // Check if email exists first
    $stmt = $pdo->prepare("SELECT * FROM {$table} WHERE email = :email LIMIT 1");
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        // Log failed login attempt
        logActivity($pdo, 0, 'Unknown User', 'Failed Login', 'System', "Failed login attempt with email: {$email}", $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']);

        echo json_encode([
            'status' => 'error',
            'message' => 'Email not found. Please check and try again.',
            'details' => 'email_not_found'
        ]);
        exit;
    }

    // Check password
    if (!password_verify($password . $user['salt'], $user['password_hash'])) {
        // Log failed login attempt
        logActivity($pdo, $user['id'], $user['name'], 'Failed Login', 'System', "Failed password attempt for user: {$user['name']}", $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']);

        echo json_encode([
            'status' => 'error',
            'message' => 'Incorrect password. Please try again.',
            'details' => 'incorrect_password'
        ]);
        exit;
    }

    // Check if user already verified for current period (resets at 11:59 PM)
    $currentTime = date('H:i:s');
    $resetTime = '23:59:00';

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
        if (!isset($_SESSION['active_sessions'])) {
            $_SESSION['active_sessions'] = [];
        }

        $userName = $user['name'] ?? $user['email'] ?? 'Unknown User';

        $_SESSION['active_sessions'][$user['id']] = [
            'id'    => $user['id'],
            'email' => $user['email'],
            'name'  => $userName,
            'type'  => $userType,
            'login_time' => time(),
            'last_activity' => time()
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

        echo json_encode([
            'status' => 'success',
            'message' => 'Login successful! Welcome back.',
            'user_id' => $user['id'],
            'user_name' => urlencode($userName),
            'user_type' => $userType,
            'redirect' => $userType === 'Dentist'
                ? "/dentalemr_system/html/index.php?uid={$user['id']}"
                : "/dentalemr_system/html/a_staff/addpatient.php?uid={$user['id']}"
        ]);
        exit;
    }

    // User needs MFA verification
    $pdo->prepare("DELETE FROM mfa_codes WHERE expires_at <= NOW() OR used = 1")->execute();

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
        // Server settings
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'lenardovinci64@gmail.com';
        $mail->Password = 'gvce aguf zgwa mgpp'; // Your app password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        // Enable debugging (0 = off, 1 = client messages, 2 = client and server messages)
        $mail->SMTPDebug = 0;

        // Timeout settings
        $mail->Timeout = 30;

        // SSL verification (sometimes needed for localhost)
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );

        // Recipients
        $mail->setFrom('lenardovinci64@gmail.com', 'MHO Dental Clinic System');
        $mail->addAddress($user['email'], $user['name']);

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Your Daily Verification Code - MHO Dental Clinic';

        $mailBody = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e0e0e0; border-radius: 10px;'>
                <div style='text-align: center; margin-bottom: 30px;'>
                    <h2 style='color: #2563EB;'>MHO Dental Clinic</h2>
                    <p style='color: #666;'>Daily Verification Code</p>
                </div>
                
                <div style='background-color: #f8fafc; padding: 20px; border-radius: 8px; text-align: center; margin: 20px 0;'>
                    <p style='margin-bottom: 10px; color: #475569;'>Hello <strong>{$user['name']}</strong>,</p>
                    <p style='color: #475569; margin-bottom: 20px;'>Your verification code is:</p>
                    <div style='font-size: 32px; font-weight: bold; letter-spacing: 8px; color: #2563EB; background: white; padding: 15px; border-radius: 8px; border: 2px dashed #dbeafe; margin: 20px auto; display: inline-block;'>
                        {$mfaCode}
                    </div>
                    <p style='color: #dc2626; font-size: 14px; margin-top: 15px;'>
                        ⏰ This code will expire in 5 minutes
                    </p>
                </div>
                
                <div style='margin-top: 30px; padding: 15px; background-color: #f0f9ff; border-radius: 8px; border-left: 4px solid #3b82f6;'>
                    <p style='color: #1e40af; margin: 0; font-size: 14px;'>
                        <strong>Note:</strong> You only need to verify once daily. The verification resets at 11:59 PM.
                    </p>
                </div>
                
                <div style='margin-top: 30px; padding-top: 20px; border-top: 1px solid #e5e7eb; text-align: center;'>
                    <p style='color: #6b7280; font-size: 12px;'>
                        This is an automated message from MHO Dental Clinic System.<br>
                        If you didn't request this code, please ignore this email.
                    </p>
                </div>
            </div>
        ";

        $mail->Body = $mailBody;

        // Plain text alternative
        $mail->AltBody = "Hello {$user['name']},\n\nYour MHO Dental Clinic verification code is: {$mfaCode}\n\nThis code will expire in 5 minutes.\n\nNote: You only need to verify once daily (resets at 11:59 PM).\n\nIf you didn't request this code, please ignore this message.";

        $mail->send();

        logActivity($pdo, $user['id'], $user['name'], 'Login', 'System', "Daily MFA code sent to {$user['email']}", $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']);

        $_SESSION['pending_user'] = [
            'id'    => $user['id'],
            'type'  => $userType,
            'email' => $user['email'],
            'name'  => $user['name']
        ];

        echo json_encode([
            'status' => 'mfa_required',
            'message' => 'Login successful! A verification code has been sent to your email.',
            'user_id' => $user['id'],
            'user_name' => urlencode($user['name']),
            'redirect' => '/dentalemr_system/php/login/verify_mfa.php'
        ]);
        exit;
    } catch (Exception $e) {
        // Log email sending failure with detailed error
        $errorMessage = "Failed to send MFA code to {$user['email']}. Error: " . $e->getMessage();
        logActivity($pdo, $user['id'], $user['name'], 'Email Failed', 'System', $errorMessage, $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']);

        error_log('PHPMailer Error: ' . $e->getMessage());
        error_log('SMTP Debug: ' . $mail->ErrorInfo);

        // Instead of failing, allow direct login for now (for testing)
        // TODO: Remove this fallback in production
        $_SESSION['pending_user'] = [
            'id'    => $user['id'],
            'type'  => $userType,
            'email' => $user['email'],
            'name'  => $user['name']
        ];

        echo json_encode([
            'status' => 'mfa_required',
            'message' => 'Login successful! Proceeding to verification.',
            'user_id' => $user['id'],
            'user_name' => urlencode($user['name']),
            'redirect' => '/dentalemr_system/php/login/verify_mfa.php',
            'debug_code' => $mfaCode // For testing only - remove in production
        ]);
        exit;

        // Original error response (comment out the above fallback for production)
        /*
        echo json_encode([
            'status' => 'error',
            'message' => 'Could not send verification code. Please contact support.',
            'details' => 'email_failed',
            'debug' => 'Check server logs for email error details'
        ]);
        exit;
        */
    }
} else {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid request method.',
        'details' => 'invalid_method'
    ]);
    exit;
}
