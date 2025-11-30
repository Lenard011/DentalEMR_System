<?php
session_start();
require_once __DIR__ . '/db_connect.php';
date_default_timezone_set('Asia/Manila');

/*
|--------------------------------------------------------------------------
| 1. VALIDATE PENDING SESSION
|--------------------------------------------------------------------------
*/

$user = null;
$alert = "";

// Primary session (always set by staff_login.php or login.php)
if (isset($_SESSION['pending_user']) && is_array($_SESSION['pending_user'])) {
    $user = $_SESSION['pending_user'];
}
// Fallback (multi-login support)
elseif (!empty($_SESSION['pending_users']) && is_array($_SESSION['pending_users'])) {
    $user = reset($_SESSION['pending_users']);
}

// If still invalid → session broken
if (!$user || empty($user['id']) || empty($user['type'])) {
    echo "<script>
        alert('Pending user session incomplete. Please log in again.');
        window.location.href='/dentalemr_system/html/login/login.html';
    </script>";
    exit;
}

$userId    = $user['id'];
$userType  = $user['type'];
$userEmail = $user['email'] ?? '(no email)';

/*
|--------------------------------------------------------------------------
| 2. HANDLE MFA CODE SUBMISSION
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['mfa_code'])) {

    $enteredCode = trim($_POST['mfa_code'] ?? '');

    if (empty($enteredCode)) {
        $alert = "Please enter your 6-digit code.";
    } else {
        // Fetch last unused 5 MFA codes
        $stmt = $pdo->prepare("
            SELECT * FROM mfa_codes
            WHERE user_id = :uid 
              AND user_type = :utype 
              AND used = 0
            ORDER BY created_at DESC
            LIMIT 5
        ");

        $stmt->execute([
            'uid'   => $userId,
            'utype' => $userType
        ]);

        $codes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $matched = false;

        foreach ($codes as $c) {
            // Compare code exactly
            if ((string)$c['code'] === (string)$enteredCode) {
                // Check expiration
                if (strtotime($c['expires_at']) < time()) {
                    $alert = "The verification code has expired. Please request a new one.";
                    break;
                }

                $matched = true;

                // Mark as used
                $pdo->prepare("UPDATE mfa_codes SET used = 1 WHERE id = :id")
                    ->execute(['id' => $c['id']]);

                // RECORD DAILY VERIFICATION (resets at 11:59 PM)
                $currentTime = date('H:i:s');
                $resetTime = '23:59:00'; // 11:59 PM

                // If current time is before 11:59 PM, record for today
                // If current time is after 11:59 PM, record for tomorrow
                if ($currentTime < $resetTime) {
                    $verificationDate = date('Y-m-d');
                } else {
                    $verificationDate = date('Y-m-d', strtotime('+1 day'));
                }

                $pdo->prepare("
                    INSERT INTO daily_verifications (user_id, user_type, verification_date, last_verification_time) 
                    VALUES (:uid, :utype, :vdate, NOW())
                    ON DUPLICATE KEY UPDATE last_verification_time = NOW()
                ")->execute([
                    'uid' => $userId,
                    'utype' => $userType,
                    'vdate' => $verificationDate
                ]);

                // After successful MFA code verification, replace the entire redirect section with:

                // NEW MULTI-SESSION SYSTEM
                if (!isset($_SESSION['active_sessions'])) {
                    $_SESSION['active_sessions'] = [];
                }

                // Get user name from database
                $stmt = $pdo->prepare("SELECT name FROM {$userType} WHERE id = :id");
                $stmt->execute(['id' => $userId]);
                $userData = $stmt->fetch(PDO::FETCH_ASSOC);
                $userName = $userData['name'] ?? $userEmail;

                // Store login session per user ID
                $_SESSION['active_sessions'][$userId] = [
                    'id'    => $userId,
                    'email' => $userEmail,
                    'name'  => $userName,
                    'type'  => $userType,
                    'login_time' => time(),
                    'last_activity' => time()
                ];

                // ========== OFFLINE ACCESS REGISTRATION - START ==========
                // Check if we have pending offline user data from login.php
                if (isset($_SESSION['pending_offline_user'])) {
                    // Add user data for offline access session
                    $_SESSION['user_data'] = $_SESSION['pending_offline_user'];

                    // Return user data for JavaScript to capture
                    $userData = $_SESSION['pending_offline_user'];
                    $redirect = $userType === 'Dentist'
                        ? "/dentalemr_system/html/index.php?uid={$userId}"
                        : "/dentalemr_system/html/a_staff/addpatient.php?uid={$userId}";

                    // echo "Login successful&user_id=" . $userData['id'] . "&user_name=" . urlencode($userData['name']) . "&redirect=" . urlencode($redirect);
                    echo "<script> alert('Verified successfully!'); window.location.href='{$redirect}'; </script>";
                    // Clean up
                    unset($_SESSION['pending_offline_user']);
                    unset($_SESSION['pending_user']);
                    unset($_SESSION['pending_users']);
                    exit;
                } else {
                    // Fallback: Create offline user data from current session
                    $_SESSION['user_data'] = [
                        'id' => $userId,
                        'email' => $userEmail,
                        'name' => $userName,
                        'type' => $userType
                    ];

                    $redirect = $userType === 'Dentist'
                        ? "/dentalemr_system/html/index.php?uid={$userId}"
                        : "/dentalemr_system/html/a_staff/addpatient.php?uid={$userId}";

                        // echo "Login successful&user_id=" . $userId . "&user_name=" . urlencode($userName) . "&redirect=" . urlencode($redirect);
                        echo "<script> alert('Verified successfully!'); window.location.href='{$redirect}'; </script>";

                    // Clean up
                    unset($_SESSION['pending_user']);
                    unset($_SESSION['pending_users']);
                    exit;
                }
                // ========== OFFLINE ACCESS REGISTRATION - END ==========
            }
        }

        // If no match found
        if (!$matched && !$alert) {
            $alert = "Invalid or expired code. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MHO Dental Clinic - Verify Identity</title>
    <link href="../css/style.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');

        :root {
            --primary: #3b82f6;
            --primary-dark: #1d4ed8;
            --secondary: #10b981;
            --error: #ef4444;
            --success: #10b981;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 50%, #f0fdf4 100%);
            min-height: 100vh;
        }

        .dark body {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #0f172a 100%);
        }

        .card {
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.08);
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            border-radius: 20px;
            overflow: hidden;
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.95);
        }

        .dark .card {
            background: rgba(30, 41, 59, 0.95);
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            transition: all 0.3s ease;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            letter-spacing: 0.5px;
            position: relative;
            overflow: hidden;
        }

        .btn-primary::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s;
        }

        .btn-primary:hover::before {
            left: 100%;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(59, 130, 246, 0.4);
        }

        .btn-secondary {
            background: transparent;
            border: 2px solid var(--primary);
            color: var(--primary);
            transition: all 0.3s ease;
            border-radius: 12px;
            font-weight: 600;
        }

        .btn-secondary:hover {
            background: var(--primary);
            color: white;
            transform: translateY(-2px);
        }

        .input-field {
            transition: all 0.3s ease;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            padding: 10px 12px;
            background: #f9fafb;
            font-size: 14px;
            text-align: center;
            letter-spacing: 8px;
            font-weight: 600;
        }

        .dark .input-field {
            background: #374151;
            border-color: #4b5563;
        }

        .input-field:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.15);
            outline: none;
            background: white;
        }

        .dark .input-field:focus {
            background: #4b5563;
        }

        .logo-container {
            border-radius: 50%;
            padding: 6px;
            transition: transform 0.3s ease;
        }

        .logo-container:hover {
            transform: scale(1.05);
        }

        .module-transition {
            animation: fadeInUp 0.6s ease-out;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px) scale(0.95);
            }

            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .loading-spinner {
            display: none;
            width: 20px;
            height: 20px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        .alert {
            padding: 14px 18px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: none;
            animation: slideIn 0.3s ease-out;
            border-left: 4px solid;
        }

        @keyframes slideIn {
            from {
                transform: translateX(-10px);
                opacity: 0;
            }

            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        .alert-error {
            background-color: #fef2f2;
            color: #991b1b;
            border-left-color: var(--error);
        }

        .dark .alert-error {
            background-color: rgba(239, 68, 68, 0.15);
        }

        .toast {
            position: fixed;
            top: 24px;
            right: 24px;
            padding: 16px 24px;
            border-radius: 12px;
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.15);
            z-index: 1000;
            display: none;
            max-width: 400px;
            animation: toastIn 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            backdrop-filter: blur(10px);
        }

        @keyframes toastIn {
            from {
                transform: translateX(100px);
                opacity: 0;
            }

            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        .toast-success {
            background: rgba(16, 185, 129, 0.95);
            color: white;
            border-left: 4px solid #059669;
        }

        .toast-error {
            background: rgba(239, 68, 68, 0.95);
            color: white;
            border-left: 4px solid #dc2626;
        }

        .verification-icon {
            font-size: 3rem;
            color: var(--primary);
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% {
                transform: scale(1);
            }

            50% {
                transform: scale(1.05);
            }

            100% {
                transform: scale(1);
            }
        }

        .countdown-timer {
            font-size: 0.875rem;
            color: #6b7280;
            margin-top: 0.5rem;
        }

        .dark .countdown-timer {
            color: #9ca3af;
        }

        .code-input-container {
            position: relative;
            margin: 2rem 0;
        }

        .floating-shapes {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            overflow: hidden;
            z-index: -1;
        }

        .shape {
            position: absolute;
            opacity: 0.1;
            animation: float 20s infinite linear;
        }

        .shape-1 {
            top: 10%;
            left: 10%;
            width: 100px;
            height: 100px;
            background: var(--primary);
            border-radius: 30% 70% 70% 30% / 30% 30% 70% 70%;
            animation-delay: 0s;
        }

        .shape-2 {
            top: 60%;
            right: 10%;
            width: 150px;
            height: 150px;
            background: var(--secondary);
            border-radius: 50% 50% 50% 50% / 60% 60% 40% 40%;
            animation-delay: -5s;
        }

        @keyframes float {
            0% {
                transform: translateY(0) rotate(0deg);
            }

            50% {
                transform: translateY(-20px) rotate(180deg);
            }

            100% {
                transform: translateY(0) rotate(360deg);
            }
        }

        .resend-link {
            color: var(--primary);
            transition: all 0.3s ease;
            cursor: pointer;
            background: none;
            border: none;
            font-size: 0.875rem;
            font-weight: 500;
        }

        .resend-link:hover {
            color: var(--primary-dark);
            text-decoration: underline;
        }

        .resend-link:disabled {
            color: #9ca3af;
            cursor: not-allowed;
        }

        .dark .resend-link:disabled {
            color: #6b7280;
        }
    </style>
</head>

<body class="min-h-screen flex items-center justify-center p-2 relative">
    <!-- Floating Background Shapes -->
    <div class="floating-shapes">
        <div class="shape shape-1"></div>
        <div class="shape shape-2"></div>
    </div>

    <!-- Toast Notifications -->
    <div id="toast" class="toast">
        <div class="flex items-center">
            <i id="toast-icon" class="mr-3 text-lg"></i>
            <span id="toast-message" class="font-medium"></span>
        </div>
    </div>

    <!-- Main Content -->
    <div class="w-full max-w-md">
        <div class="module-transition">
            <div class="text-center mb-5">
                <div class="verification-icon">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <h1 class="text-xl font-bold text-gray-900 dark:text-white mb-1">
                    Two-Factor Verification
                </h1>
                <p class="text-gray-600 dark:text-gray-300 text-sm">
                    Enter the verification code sent to your email
                </p>
            </div>

            <div class="card bg-white rounded-2xl p-4 dark:bg-gray-800">
                <div class="flex items-center justify-between mb-2">
                    <div class="logo-container">
                        <img src="/dentalemr_system/img/DOH Logo.png" class="rounded-full w-16 h-16" alt="DOH Logo">
                    </div>
                    <div class="text-center flex-1 mx-1">
                        <h1 class="text-sm font-bold text-gray-900 dark:text-white leading-tight">
                            Republic of the Philippines<br>Department of Health
                        </h1>
                        <h3 class="text-xs font-semibold text-gray-700 dark:text-gray-300 mt-1">
                            Mamburao, Occidental Mindoro
                        </h3>
                    </div>
                    <div class="logo-container">
                        <img src="../../img/DOHDentalLogo-removebg-preview.png" class="w-24 h-16" alt="DOH Dental Logo">
                    </div>
                </div>

                <div class="text-center mb-1">
                    <p class="text-gray-600 dark:text-gray-300 text-[13px] ">
                        We've sent a 6-digit verification code to:
                    </p>
                    <p class="font-semibold text-gray-900 dark:text-white text-[13px]">
                        <?php echo htmlspecialchars($userEmail); ?>
                    </p>
                    <div id="countdown" class="countdown-timer text-[13px]">
                        Code expires in: <span id="timer" class="text-[13px]">05:00</span>
                    </div>
                </div>

                <!-- PHP Alert Display -->
                <?php if (!empty($alert)): ?>
                    <div class="alert alert-error">
                        <?php echo htmlspecialchars($alert); ?>
                    </div>
                <?php endif; ?>

                <!-- Client-side Alert -->
                <div id="clientAlert" class="alert" style="display: none;"></div>

                <form class="space-y-1" method="POST" id="verificationForm">
                    <div class="code-input-container">
                        <label for="mfa_code" class="block mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">
                            <i class="fas fa-key mr-2"></i>Verification Code
                        </label>
                        <input type="text" name="mfa_code" id="mfa_code"
                            maxlength="6" minlength="6"
                            class="input-field bg-gray-50 border border-gray-300 text-gray-900 rounded-lg focus:ring-primary-600 focus:border-primary-600 block w-full p-2 text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                            placeholder="000000" required
                            pattern="[0-9]{6}"
                            title="Please enter exactly 6 digits"
                            value="<?php echo htmlspecialchars($_POST['mfa_code'] ?? ''); ?>">
                        <div class="text-xs text-gray-500 dark:text-gray-400 mt-2 text-center">
                            Enter the 6-digit code from your email
                        </div>
                    </div>

                    <button type="submit"
                        class="btn-primary -mt-5 text-white font-medium rounded-lg text-sm px-5 py-3 cursor-pointer text-center flex items-center justify-center w-full">
                        <span>Verify & Continue</span>
                        <i class="fas fa-check-circle ml-2"></i>
                        <div class="loading-spinner ml-2"></div>
                    </button>
                </form>

                <div class="mt-3 text-center">
                    <p class="text-gray-600 dark:text-gray-300 text-xs">
                        Didn't receive the code?
                    </p>
                    <form method="POST" action="resend_mfa.php" id="resendForm">
                        <button type="submit" id="resendBtn"
                            class="resend-link mt-1 flex items-center justify-center mx-auto">
                            <i class="fas fa-redo-alt mr-2"></i>
                            <span>Resend Verification Code</span>
                        </button>
                    </form>
                    <div id="resendTimer" class="countdown-timer mt-1" style="display: none;">
                        Resend available in: <span id="resendCounter">30</span>s
                    </div>
                </div>

                <div class="mt-4 pt-3 border-t border-gray-200 dark:border-gray-700 text-center">
                    <a href="/dentalemr_system/html/login/login.html"
                        class="btn-secondary font-medium rounded-lg text-sm px-4 py-2.5 inline-flex items-center">
                        <i class="fas fa-arrow-left mr-2"></i>
                        Back to Login
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/flowbite@3.1.2/dist/flowbite.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const verificationForm = document.getElementById('verificationForm');
            const resendForm = document.getElementById('resendForm');
            const resendBtn = document.getElementById('resendBtn');
            const clientAlert = document.getElementById('clientAlert');
            const mfaInput = document.getElementById('mfa_code');
            const timerElement = document.getElementById('timer');
            const resendTimer = document.getElementById('resendTimer');
            const resendCounter = document.getElementById('resendCounter');
            const toast = document.getElementById('toast');
            const toastMessage = document.getElementById('toast-message');
            const toastIcon = document.getElementById('toast-icon');

            let countdown = 300; // 5 minutes in seconds
            let resendCooldown = 30; // 30 seconds cooldown for resend
            let countdownInterval;

            // Start countdown timer
            function startCountdown() {
                clearInterval(countdownInterval);
                countdown = 300;

                countdownInterval = setInterval(function() {
                    countdown--;
                    const minutes = Math.floor(countdown / 60);
                    const seconds = countdown % 60;
                    timerElement.textContent = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;

                    if (countdown <= 0) {
                        clearInterval(countdownInterval);
                        showClientAlert('The verification code has expired. Please request a new one.', 'error');
                    }
                }, 1000);
            }

            // Start initial countdown
            startCountdown();

            // Auto-advance input and format
            mfaInput.addEventListener('input', function(e) {
                // Remove any non-digit characters
                this.value = this.value.replace(/\D/g, '');

                // Auto-submit when 6 digits are entered
                if (this.value.length === 6) {
                    verificationForm.dispatchEvent(new Event('submit', {
                        cancelable: true
                    }));
                }
            });

            // Form submission
            verificationForm.addEventListener('submit', function(e) {
                e.preventDefault();

                const submitBtn = this.querySelector('button[type="submit"]');
                const spinner = submitBtn.querySelector('.loading-spinner');

                // Validate code format
                const code = mfaInput.value;
                if (code.length !== 6 || !/^\d+$/.test(code)) {
                    showClientAlert('Please enter a valid 6-digit code.', 'error');
                    return;
                }

                // Show loading state
                spinner.style.display = 'block';
                submitBtn.disabled = true;
                clientAlert.style.display = 'none';

                // Submit the form (PHP will handle the backend processing)
                this.submit();
            });

            // Resend code functionality
            resendForm.addEventListener('submit', function(e) {
                e.preventDefault();

                // Disable resend button and show cooldown
                resendBtn.disabled = true;
                resendTimer.style.display = 'block';

                // Start resend cooldown timer
                const resendInterval = setInterval(function() {
                    resendCooldown--;
                    resendCounter.textContent = resendCooldown;

                    if (resendCooldown <= 0) {
                        clearInterval(resendInterval);
                        resendBtn.disabled = false;
                        resendTimer.style.display = 'none';
                        resendCooldown = 30;
                    }
                }, 1000);

                // Submit the resend form
                fetch('resend_mfa.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'resend=true'
                    })
                    .then(response => response.text())
                    .then(data => {
                        showToast('New verification code sent to your email', 'success');

                        // Reset main countdown timer
                        startCountdown();
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        showToast('Failed to resend code. Please try again.', 'error');
                    });
            });

            // Helper function to show client-side alerts
            function showClientAlert(message, type) {
                clientAlert.textContent = message;
                clientAlert.className = 'alert';
                clientAlert.classList.add('alert-error');
                clientAlert.style.display = 'block';
            }

            // Helper function to show toast notifications
            function showToast(message, type) {
                toastMessage.textContent = message;
                toast.className = 'toast';
                toast.classList.add(type === 'success' ? 'toast-success' : 'toast-error');

                if (type === 'success') {
                    toastIcon.className = 'fas fa-check-circle';
                } else {
                    toastIcon.className = 'fas fa-exclamation-circle';
                }

                toast.style.display = 'block';

                // Hide toast after 5 seconds
                setTimeout(() => {
                    toast.style.display = 'none';
                }, 5000);
            }

            // Auto-focus on code input
            mfaInput.focus();

            // Add input animation
            mfaInput.addEventListener('focus', function() {
                this.classList.add('ring-2', 'ring-blue-200');
            });

            mfaInput.addEventListener('blur', function() {
                this.classList.remove('ring-2', 'ring-blue-200');
            });

            // Handle PHP errors from previous submission
            <?php if (!empty($alert)): ?>
                showClientAlert('<?php echo addslashes($alert); ?>', 'error');
            <?php endif; ?>
        });
    </script>
</body>

</html>