<?php
session_start();
require_once __DIR__ . '/db_connect.php';
require_once 'EmailHelper.php';
date_default_timezone_set('Asia/Manila');

header('Content-Type: application/json');

// Validate pending session
if (!isset($_SESSION['pending_user']) || empty($_SESSION['pending_user']['id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'No pending user session found.'
    ]);
    exit;
}

$user = $_SESSION['pending_user'];
$userId = $user['id'];
$userType = $user['type'];
$userEmail = $user['email'];
$userName = $user['name'];

// Generate new MFA code
$mfaCode = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
$expiresAt = date('Y-m-d H:i:s', time() + MFA_CODE_EXPIRY);

// Delete old codes for this user
$pdo->prepare("DELETE FROM mfa_codes WHERE user_id = :uid AND user_type = :utype")
    ->execute(['uid' => $userId, 'utype' => $userType]);

// Insert new code
$insert = $pdo->prepare("
    INSERT INTO mfa_codes (user_id, user_type, code, expires_at, used, created_at, sent_at)
    VALUES (:uid, :utype, :code, :expires, 0, NOW(), NOW())
");
$insert->execute([
    'uid' => $userId,
    'utype' => $userType,
    'code' => $mfaCode,
    'expires' => $expiresAt
]);

// Store debug code in session
$_SESSION['debug_mfa_code'] = $mfaCode;
$_SESSION['mfa_code_generated_at'] = time();

// Send email
$emailHelper = new EmailHelper(DEBUG_MODE);
$emailResult = $emailHelper->sendMFACode($userEmail, $userName, $mfaCode);

// Update session
$_SESSION['pending_user']['email_sent'] = $emailResult['success'];

if ($emailResult['success']) {
    echo json_encode([
        'success' => true,
        'message' => 'New verification code sent to your email.',
        'email_sent' => true
    ]);
} else {
    echo json_encode([
        'success' => true,
        'message' => 'New verification code generated.',
        'email_sent' => false,
        'debug_mode' => true
    ]);
}
?>