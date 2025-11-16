<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once '../conn.php';
date_default_timezone_set('Asia/Manila');

require_once '../../vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Helper function
function response($message)
{
    echo "<script>
        alert('{$message}');
        window.location.href='/dentalemr_system/html/manageusers/manageuser.php';
    </script>";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Ensure single entries are converted to arrays
    $fullnames = isset($_POST['fullname']) ? (array)$_POST['fullname'] : [];
    $usernames = isset($_POST['username']) ? (array)$_POST['username'] : [];
    $emails = isset($_POST['email']) ? (array)$_POST['email'] : [];
    $passwords = isset($_POST['password']) ? (array)$_POST['password'] : [];
    $confirmPasswords = isset($_POST['confirm_password']) ? (array)$_POST['confirm_password'] : [];

    if (!count($fullnames)) {
        response("No staff data submitted.");
    }

    $successCount = 0;
    $failedEmails = [];

    for ($i = 0; $i < count($fullnames); $i++) {
        $fullname = trim($fullnames[$i] ?? '');
        $username = trim($usernames[$i] ?? '');
        $email = trim($emails[$i] ?? '');
        $password = trim($passwords[$i] ?? '');
        $confirmPassword = trim($confirmPasswords[$i] ?? '');

        if (empty($fullname) || empty($username) || empty($email) || empty($password)) {
            $failedEmails[] = $email ?: "(missing email)";
            continue;
        }

        if ($password !== $confirmPassword) {
            $failedEmails[] = $email;
            continue;
        }

        // Check duplicate email
        $check = mysqli_prepare($conn, "SELECT id FROM staff WHERE email = ?");
        mysqli_stmt_bind_param($check, "s", $email);
        mysqli_stmt_execute($check);
        mysqli_stmt_store_result($check);
        if (mysqli_stmt_num_rows($check) > 0) {
            $failedEmails[] = $email;
            continue;
        }

        // Hash password
        $salt = bin2hex(random_bytes(16));
        $passwordHash = password_hash($password . $salt, PASSWORD_DEFAULT);

        // Insert staff
        $stmt = mysqli_prepare($conn, "
            INSERT INTO staff (name, username, email, password_hash, salt, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, NOW(), NOW())
        ");
        mysqli_stmt_bind_param($stmt, "sssss", $fullname, $username, $email, $passwordHash, $salt);
        $inserted = mysqli_stmt_execute($stmt);

        if ($inserted) {
            // Send credentials email
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
                $mail->addAddress($email, $fullname);

                $mail->isHTML(true);
                $mail->Subject = "Your Staff Account Credentials";
                $mail->Body = "
                    <h3>Welcome, $fullname!</h3>
                    <p>Your staff account has been created.</p>
                    <b>Login Credentials:</b><br>
                    Username: $username <br>
                    Email: $email <br>
                    Password: $password <br><br>
                    <p>Please login and change your password immediately.</p>
                    <small>This is an automated message from Dental EMR System.</small>
                ";

                $mail->send();
                $successCount++;
            } catch (Exception $e) {
                error_log("Mail error for {$email}: " . $mail->ErrorInfo);
                $failedEmails[] = $email;
            }
        } else {
            $failedEmails[] = $email;
        }
    }

    $message = "Staff accounts processed. Successfully added: {$successCount}.";
    if (!empty($failedEmails)) {
        $message .= " Failed for emails: " . implode(", ", $failedEmails);
    }
    response($message);
}
