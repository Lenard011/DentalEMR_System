<?php
session_start();
require_once 'db_connect.php';
require_once '../../vendor/autoload.php';
date_default_timezone_set('Asia/Manila');


use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (!isset($_SESSION['pending_user'])) {
    header("Location: login.html");
    exit;
}

$user = $_SESSION['pending_user'];

// Generate new MFA
$newCode = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
$expiresAt = date('Y-m-d H:i:s', time() + 300);

$insert = $pdo->prepare("
    INSERT INTO mfa_codes (user_id, user_type, code, expires_at, used)
    VALUES (:uid, :utype, :code, :expires, 0)
");
$insert->execute([
    'uid' => $user['id'],
    'utype' => $user['type'],
    'code' => $newCode,
    'expires' => $expiresAt
]);

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
    $mail->addAddress($user['email'], 'User');
    $mail->isHTML(true);
    $mail->Subject = 'Your New Verification Code';
    $mail->Body = "Your new code is: <b>{$newCode}</b><br>This will expire in 5 minutes.";

    $mail->send();

    echo "<script>alert('New code sent to your email!'); window.location.href='verify_mfa.php';</script>";
} catch (Exception $e) {
    die('Error sending mail: ' . htmlspecialchars($mail->ErrorInfo));
}
