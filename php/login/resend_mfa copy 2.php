<?php
session_start();

// In resend_mfa.php, after session_start()
// Preserve pending_offline_user if it exists
if (isset($_SESSION['pending_offline_user'])) {
    $preserved_offline_user = $_SESSION['pending_offline_user'];
}

// After successful resend, restore it
if (isset($preserved_offline_user)) {
    $_SESSION['pending_offline_user'] = $preserved_offline_user;
}


require_once 'db_connect.php';
require_once '../../vendor/autoload.php';
date_default_timezone_set('Asia/Manila');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Check if pending user exists
if (!isset($_SESSION['pending_user'])) {
    echo "<script>alert('Session expired. Please log in again.'); window.location.href='login.html';</script>";
    exit;
}

$user = $_SESSION['pending_user'];

// Clean expired/used MFA codes for this user
$pdo->prepare("
    DELETE FROM mfa_codes 
    WHERE (expires_at <= NOW() OR used = 1) AND user_id = :uid AND user_type = :utype
")->execute([
    'uid' => $user['id'],
    'utype' => $user['type']
]);

// Generate new MFA code
$newCode = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
$expiresAt = date('Y-m-d H:i:s', time() + 300);

$insert = $pdo->prepare("
    INSERT INTO mfa_codes (user_id, user_type, code, expires_at, used, created_at, sent_at)
    VALUES (:uid, :utype, :code, :expires, 0, NOW(), NOW())
");
$insert->execute([
    'uid' => $user['id'],
    'utype' => $user['type'],
    'code' => $newCode,
    'expires' => $expiresAt
]);

// Send MFA code via email
$mail = new PHPMailer(true);
try {
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'lenardovinci64@gmail.com';
    $mail->Password = 'gvceagufzgwamgpp';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;

    $mail->setFrom('lenardovinci64@gmail.com', 'Dental EMR');
    $mail->addAddress($user['email'], $user['type'] === 'Dentist' ? 'Dentist' : 'Staff');
    $mail->isHTML(true);
    $mail->Subject = 'Your New Verification Code';
    $mail->Body = "
        <p>Dear <strong>" . ($user['type'] ?? 'User') . "</strong>,</p>
        <p>Your new verification code is:</p>
        <h2 style='letter-spacing:3px;color:#2563EB;'>{$newCode}</h2>
        <p>This code will expire in 5 minutes.</p>
        <br><p>Best regards,<br>Dental EMR System</p>
    ";

    $mail->send();

    echo "<script>
        alert('A new verification code has been sent to your email.');
        window.location.href='verify_mfa.php';
    </script>";
} catch (Exception $e) {
    error_log('Mail error: ' . $mail->ErrorInfo);
    echo "<script>alert('Failed to send new code. Please contact support.'); window.location.href='verify_mfa.php';</script>";
    exit;
}
