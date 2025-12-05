<?php
session_start();
require_once '../conn.php';
require_once '../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['active_sessions']) || !isset($_GET['uid'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$currentUserId = intval($_GET['uid']);
$staffId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($staffId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid staff ID']);
    exit;
}

try {
    // Get staff details
    $stmt = $conn->prepare("SELECT name, username, email FROM staff WHERE id = ?");
    $stmt->bind_param("i", $staffId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'error' => 'Staff not found']);
        exit;
    }
    
    $staff = $result->fetch_assoc();
    $stmt->close();
    
    // Generate a new temporary password
    $temporaryPassword = bin2hex(random_bytes(8)); // 16 character password
    
    // Hash and update the password (staff will need to change it on first login)
    $salt = bin2hex(random_bytes(16));
    $passwordHash = password_hash($temporaryPassword . $salt, PASSWORD_DEFAULT);
    
    $updateStmt = $conn->prepare("UPDATE staff SET password_hash = ?, salt = ?, updated_at = NOW() WHERE id = ?");
    $updateStmt->bind_param("ssi", $passwordHash, $salt, $staffId);
    $updateStmt->execute();
    $updateStmt->close();
    
    // Send email using the sendCredentialsEmail function (copy from add_staff.php)
    $mail = new PHPMailer(true);
    
    // Server settings (same as in add_staff.php)
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'your-email@gmail.com';
    $mail->Password   = 'your-app-password';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;
    
    // Recipients
    $mail->setFrom('noreply@mhodentalclinic.com', 'MHO Dental Clinic');
    $mail->addAddress($staff['email'], $staff['name']);
    
    // Content
    $mail->isHTML(true);
    $mail->Subject = 'Reset Password - MHO Dental Clinic';
    
    $mail->Body = "
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #2563eb; color: white; padding: 20px; text-align: center; }
            .content { padding: 30px; background: #f9fafb; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>MHO Dental Clinic</h1>
                <h2>Password Reset</h2>
            </div>
            <div class='content'>
                <p>Dear <strong>{$staff['name']}</strong>,</p>
                <p>Your password has been reset by an administrator.</p>
                <p><strong>New Temporary Password:</strong> {$temporaryPassword}</p>
                <p>Please login and change your password immediately.</p>
                <p>Login URL: https://yourdomain.com/dentalemr_system/html/login/login.html</p>
            </div>
        </div>
    </body>
    </html>";
    
    $mail->send();
    
    // Update email sent status
    $conn->query("UPDATE staff SET welcome_email_sent = 1, email_sent_at = NOW() WHERE id = $staffId");
    
    // Log the action
    // log_history($currentUserId, "RESEND_CREDENTIALS", "Resent credentials to: {$staff['email']}");
    
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    error_log("Resend credentials error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Failed to send email']);
}
?>