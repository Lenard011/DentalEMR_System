<?php
session_start();
require_once 'db_connect.php';
require_once 'EmailHelper.php';
date_default_timezone_set('Asia/Manila');

// Include the activity logger
require_once '../manageusers/activity_logger.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $userType = trim($_POST['user_type'] ?? '');

    // Force user type to be Staff for this endpoint
    $userType = 'Staff';
    $table = 'staff';

    if (empty($email) || empty($password)) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Please fill in all fields.',
            'details' => 'missing_fields'
        ]);
        exit;
    }

    // Check if email exists first
    $stmt = $pdo->prepare("SELECT * FROM {$table} WHERE email = :email LIMIT 1");
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        logActivity($pdo, 0, 'Unknown User', 'Failed Login', 'System', "Failed staff login attempt with email: {$email}", $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']);
        echo json_encode([
            'status' => 'error',
            'message' => 'Email not found. Please check and try again.',
            'details' => 'email_not_found'
        ]);
        exit;
    }

    // Check password
    if (!password_verify($password . $user['salt'], $user['password_hash'])) {
        logActivity($pdo, $user['id'], $user['name'], 'Failed Login', 'System', "Failed password attempt for staff: {$user['name']}", $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']);
        echo json_encode([
            'status' => 'error',
            'message' => 'Incorrect password. Please try again.',
            'details' => 'incorrect_password'
        ]);
        exit;
    }

    // Check if user already verified for current period
    $currentTime = date('H:i:s');
    $resetTime = DAILY_VERIFICATION_RESET;

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

        logActivity($pdo, $user['id'], $user['name'], 'Login', 'System', "Staff daily verification bypass", $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']);

        echo json_encode([
            'status' => 'success',
            'message' => 'Login successful! Welcome back.',
            'user_id' => $user['id'],
            'user_name' => urlencode($userName),
            'user_type' => $userType,
            'redirect' => "/dentalemr_system/html/a_staff/addpatient.php?uid={$user['id']}"
        ]);
        exit;
    }

    // User needs MFA verification
    $pdo->prepare("DELETE FROM mfa_codes WHERE expires_at <= NOW() OR used = 1")->execute();

    $mfaCode = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $expiresAt = date('Y-m-d H:i:s', time() + MFA_CODE_EXPIRY);

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

    // Store in session for fallback
    $_SESSION['debug_mfa_code'] = $mfaCode;
    $_SESSION['mfa_code_generated_at'] = time();

    // Send email with MFA code
    $emailHelper = new EmailHelper(DEBUG_MODE);
    $emailResult = $emailHelper->sendMFACode($user['email'], $user['name'], $mfaCode);

    // Log email attempt
    if ($emailResult['success']) {
        logActivity($pdo, $user['id'], $user['name'], 'Login', 'System', "Staff MFA code sent to {$user['email']}", $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']);
    } else {
        logActivity($pdo, $user['id'], $user['name'], 'Login', 'System', "Staff MFA code generated but email failed", $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']);
        error_log("Staff email sending failed for {$user['email']}: " . $emailResult['error']);
    }

    $_SESSION['pending_user'] = [
        'id'    => $user['id'],
        'type'  => $userType,
        'email' => $user['email'],
        'name'  => $user['name'],
        'email_sent' => $emailResult['success']
    ];

    if ($emailResult['success']) {
        echo json_encode([
            'status' => 'mfa_required',
            'message' => 'Login successful! A verification code has been sent to your email.',
            'user_id' => $user['id'],
            'user_name' => urlencode($user['name']),
            'redirect' => '/dentalemr_system/php/login/verify_mfa.php',
            'email_sent' => true
        ]);
    } else {
        echo json_encode([
            'status' => 'mfa_required',
            'message' => 'Login successful! Please check your email for the verification code.',
            'user_id' => $user['id'],
            'user_name' => urlencode($user['name']),
            'redirect' => '/dentalemr_system/php/login/verify_mfa.php',
            'email_sent' => false
        ]);
    }
    exit;
} else {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid request method.',
        'details' => 'invalid_method'
    ]);
    exit;
}
