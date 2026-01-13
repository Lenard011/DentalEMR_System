<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once '../conn.php';
date_default_timezone_set('Asia/Manila');

// Include PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../../vendor/autoload.php'; // Or adjust path to PHPMailer

// Email configuration (you can also move this to a separate config file)
define('CLINIC_NAME', 'MHO Dental Clinic');
define('CLINIC_EMAIL', 'noreply@mhodentalclinic.com');
define('SUPPORT_EMAIL', 'support@mhodentalclinic.com');

// Enhanced input validation
function validateEmail($email)
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function validatePassword($password)
{
    return strlen($password) >= 6;
}

function sanitizeInput($input)
{
    return trim(htmlspecialchars($input, ENT_QUOTES, 'UTF-8'));
}

// Send credentials email function
function sendCredentialsEmail($staffData, $password)
{
    $mail = new PHPMailer(true);

    try {
        // Server settings for Gmail
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'lenardovinci64@gmail.com'; // Your Gmail address
        $mail->Password   = 'gvce aguf zgwa mgpp'; // Your Gmail App Password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        // Recipients
        $mail->setFrom('lenardovinci64@gmail.com', CLINIC_NAME);
        $mail->addAddress($staffData['email'], $staffData['name']);

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Your Staff Account Credentials - ' . CLINIC_NAME;

        // Email template
        $mail->Body = "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #2563eb; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
                .content { background: #f9fafb; padding: 30px; border: 1px solid #e5e7eb; }
                .credentials { background: #fff; border: 2px solid #d1d5db; border-radius: 5px; padding: 20px; margin: 20px 0; }
                .footer { background: #f3f4f6; padding: 15px; text-align: center; font-size: 12px; color: #6b7280; }
                .important { background: #fef3c7; border-left: 4px solid #f59e0b; padding: 15px; margin: 15px 0; }
                .login-btn { display: flex; background: #2563eb; color: #fff; padding: 12px 24px; text-decoration: none; border-radius: 5px; margin: 10px 0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>" . CLINIC_NAME . "</h1>
                    <h2>Staff Account Credentials</h2>
                </div>
                
                <div class='content'>
                    <p>Dear <strong>{$staffData['name']}</strong>,</p>
                    
                    <p>A staff account has been created for you at <strong>" . CLINIC_NAME . "</strong>.</p>
                    
                    <div class='credentials'>
                        <h3>Your Login Details:</h3>
                        <p><strong>Username:</strong> {$staffData['username']}</p>
                        <p><strong>Email:</strong> {$staffData['email']}</p>
                        <p><strong>Temporary Password:</strong> {$password}</p>
                        <p><strong>Login URL:</strong> http://localhost/DentalEMR_System/html/login/login.html</p>
                    </div>
                    
                    <div class='important'>
                        <p><strong>⚠️ Important Security Notice:</strong></p>
                        <ul>
                            <li>Please login immediately and change your password</li>
                            <li>Do not share your credentials with anyone</li>
                            <li>Use the provided temporary password only once</li>
                        </ul>
                    </div>
                    
                    <p style='text-align: center;'>
                        <a href='http://localhost/DentalEMR_System/html/login/login.html' style=' display: inline-block; text-align: center; background:blue; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; margin: 10px 0;'>
                            Click here to Login
                        </a>
                    </p>
                    
                    <p>If you have any issues logging in, please contact support at " . SUPPORT_EMAIL . "</p>
                </div>
                
                <div class='footer'>
                    <p>© " . date('Y') . " " . CLINIC_NAME . ". All rights reserved.</p>
                    <p>This is an automated message, please do not reply to this email.</p>
                </div>
            </div>
        </body>
        </html>";

        // Plain text version for non-HTML email clients
        $mail->AltBody = "
        Staff Account Credentials - " . CLINIC_NAME . "
        
        Dear {$staffData['name']},
        
        A staff account has been created for you at " . CLINIC_NAME . ".
        
        Your Login Details:
        Username: {$staffData['username']}
        Email: {$staffData['email']}
        Temporary Password: {$password}
        Login URL: https://yourdomain.com/DentalEMR_System/html/login/login.html
        
        Important Security Notice:
        - Please login immediately and change your password
        - Do not share your credentials with anyone
        - Use the provided temporary password only once
        
        Click here to login: https://yourdomain.com/DentalEMR_System/html/login/login.html
        
        If you have any issues logging in, please contact support at " . SUPPORT_EMAIL . "
        
        © " . date('Y') . " " . CLINIC_NAME . ". All rights reserved.
        This is an automated message, please do not reply to this email.";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email sending failed: " . $mail->ErrorInfo);
        return false;
    }
}

// Improved response function
function response($message, $userId = null, $type = 'info', $emailStatus = null)
{
    if (!$userId && isset($_SESSION['active_sessions']) && !empty($_SESSION['active_sessions'])) {
        $sessions = array_keys($_SESSION['active_sessions']);
        $userId = $sessions[0];
    }

    $redirectUrl = $userId
        ? "/DentalEMR_System/html/manageusers/manageuser.php?uid=" . $userId
        : "/DentalEMR_System/html/manageusers/manageuser.php";

    $alertType = $type === 'error' ? 'error' : 'info';

    // Add email status to message if provided
    if ($emailStatus !== null) {
        if ($emailStatus === 'success') {
            $message .= " Email sent successfully.";
        } elseif ($emailStatus === 'partial') {
            $message .= " Note: Some emails failed to send.";
        } elseif ($emailStatus === 'failed') {
            $message .= " Warning: Email notifications failed to send.";
        }
    }

    echo "<script>
        alert('" . addslashes($message) . "');
        window.location.href='{$redirectUrl}';
    </script>";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get current user ID for redirect
    $currentUserId = null;
    if (isset($_GET['uid']) && is_numeric($_GET['uid'])) {
        $currentUserId = intval($_GET['uid']);
    } elseif (isset($_SESSION['active_sessions']) && !empty($_SESSION['active_sessions'])) {
        $sessions = array_keys($_SESSION['active_sessions']);
        $currentUserId = $sessions[0];
    }

    // Ensure single entries are converted to arrays
    $fullnames = isset($_POST['fullname']) ? (array)$_POST['fullname'] : [];
    $usernames = isset($_POST['username']) ? (array)$_POST['username'] : [];
    $emails = isset($_POST['email']) ? (array)$_POST['email'] : [];
    $passwords = isset($_POST['password']) ? (array)$_POST['password'] : [];
    $confirmPasswords = isset($_POST['confirm_password']) ? (array)$_POST['confirm_password'] : [];

    if (empty($fullnames) || empty($fullnames[0])) {
        response("No staff data submitted.", $currentUserId, 'error');
    }

    $successCount = 0;
    $failedEntries = [];
    $emailSuccessCount = 0;
    $emailFailedEntries = [];

    // Start transaction for data consistency
    mysqli_begin_transaction($conn);

    try {
        for ($i = 0; $i < count($fullnames); $i++) {
            $fullname = sanitizeInput($fullnames[$i] ?? '');
            $username = sanitizeInput($usernames[$i] ?? '');
            $email = sanitizeInput($emails[$i] ?? '');
            $password = $passwords[$i] ?? '';
            $confirmPassword = $confirmPasswords[$i] ?? '';
            $originalPassword = $password; // Store for email

            // Validate required fields
            if (empty($fullname) || empty($username) || empty($email) || empty($password)) {
                $failedEntries[] = $email ?: "(missing email) - Required fields missing";
                continue;
            }

            // Validate email format
            if (!validateEmail($email)) {
                $failedEntries[] = $email . " - Invalid email format";
                continue;
            }

            // Validate password strength
            if (!validatePassword($password)) {
                $failedEntries[] = $email . " - Password must be at least 6 characters";
                continue;
            }

            if ($password !== $confirmPassword) {
                $failedEntries[] = $email . " - Passwords do not match";
                continue;
            }

            // Check duplicate email
            $check = mysqli_prepare($conn, "SELECT id FROM staff WHERE email = ? OR username = ?");
            mysqli_stmt_bind_param($check, "ss", $email, $username);
            mysqli_stmt_execute($check);
            mysqli_stmt_store_result($check);

            if (mysqli_stmt_num_rows($check) > 0) {
                $failedEntries[] = $email . " - Email or username already exists";
                mysqli_stmt_close($check);
                continue;
            }
            mysqli_stmt_close($check);

            // Hash password with salt
            $salt = bin2hex(random_bytes(16));
            $passwordHash = password_hash($password . $salt, PASSWORD_DEFAULT);

            // Insert staff
            $stmt = mysqli_prepare($conn, "
                INSERT INTO staff (name, username, email, password_hash, salt, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, NOW(), NOW())
            ");

            if (!$stmt) {
                $failedEntries[] = $email . " - Database error";
                continue;
            }

            mysqli_stmt_bind_param($stmt, "sssss", $fullname, $username, $email, $passwordHash, $salt);
            $inserted = mysqli_stmt_execute($stmt);

            if ($inserted) {
                $staffId = mysqli_insert_id($conn);
                $successCount++;

                // Prepare staff data for email
                $staffData = [
                    'id' => $staffId,
                    'name' => $fullname,
                    'username' => $username,
                    'email' => $email
                ];

                // Send email with credentials
                $emailSent = sendCredentialsEmail($staffData, $originalPassword);

                if ($emailSent) {
                    $emailSuccessCount++;

                    // Update database to indicate email was sent
                    $updateStmt = mysqli_prepare($conn, "
                        UPDATE staff SET welcome_email_sent = 1, email_sent_at = NOW() WHERE id = ?
                    ");
                    if ($updateStmt) {
                        mysqli_stmt_bind_param($updateStmt, "i", $staffId);
                        mysqli_stmt_execute($updateStmt);
                        mysqli_stmt_close($updateStmt);
                    }
                } else {
                    $emailFailedEntries[] = $email;
                }

                // Log successful creation
                if ($currentUserId) {
                    $logMessage = "Created staff account: $email" .
                        ($emailSent ? " (Email sent)" : " (Email failed)");
                    // log_history($currentUserId, "CREATE_STAFF", $logMessage);
                }
            } else {
                $failedEntries[] = $email . " - Insert failed";
            }

            mysqli_stmt_close($stmt);
        }

        // Commit transaction
        mysqli_commit($conn);

        $message = "Staff accounts processed. Successfully added: {$successCount}.";

        // Add email status to message
        if ($emailSuccessCount > 0) {
            $message .= " Emails sent: {$emailSuccessCount}.";
        }

        if (!empty($failedEntries)) {
            $message .= " Failed entries: " . implode("; ", array_slice($failedEntries, 0, 5));
            if (count($failedEntries) > 5) {
                $message .= " and " . (count($failedEntries) - 5) . " more";
            }
        }

        if (!empty($emailFailedEntries)) {
            $message .= " Email failed for: " . implode(", ", array_slice($emailFailedEntries, 0, 3));
            if (count($emailFailedEntries) > 3) {
                $message .= " and " . (count($emailFailedEntries) - 3) . " more";
            }
        }

        // Determine email status for response
        $emailStatus = null;
        if ($emailSuccessCount > 0 && empty($emailFailedEntries)) {
            $emailStatus = 'success';
        } elseif ($emailSuccessCount > 0 && !empty($emailFailedEntries)) {
            $emailStatus = 'partial';
        } elseif ($emailSuccessCount === 0 && !empty($emailFailedEntries)) {
            $emailStatus = 'failed';
        }

        response($message, $currentUserId, $successCount > 0 ? 'info' : 'error', $emailStatus);
    } catch (Exception $e) {
        // Rollback on error
        mysqli_rollback($conn);
        error_log("Add staff transaction error: " . $e->getMessage());
        response("Error processing staff accounts. Please try again.", $currentUserId, 'error');
    }
} else {
    // If not POST request, redirect back
    $currentUserId = isset($_GET['uid']) && is_numeric($_GET['uid']) ? intval($_GET['uid']) : null;
    $redirectUrl = "/DentalEMR_System/html/manageusers/manageuser.php" . ($currentUserId ? "?uid=" . $currentUserId : "");
    header("Location: " . $redirectUrl);
    exit;
}
