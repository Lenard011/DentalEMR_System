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

// Primary session (always set by staff_login.php)
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

$alert = "";

/*
|--------------------------------------------------------------------------
| 2. HANDLE MFA CODE SUBMISSION
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

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

                // RECORD DAILY VERIFICATION
                $today = date('Y-m-d');
                $pdo->prepare("
                    INSERT INTO daily_verifications (user_id, user_type, verification_date, last_verification_time) 
                    VALUES (:uid, :utype, :vdate, NOW())
                    ON DUPLICATE KEY UPDATE last_verification_time = NOW()
                ")->execute([
                    'uid' => $userId,
                    'utype' => $userType,
                    'vdate' => $today
                ]);

                // NEW MULTI-SESSION SYSTEM
                if (!isset($_SESSION['active_sessions'])) {
                    $_SESSION['active_sessions'] = [];
                }

                // Store login session per user ID
                $_SESSION['active_sessions'][$userId] = [
                    'id'    => $userId,
                    'email' => $userEmail,
                    'type'  => $userType,
                    'login_time' => time()
                ];


                // Clean up
                unset($_SESSION['pending_user']);
                unset($_SESSION['pending_users']);

                // Redirect based on user role
                $redirect = $userType === 'Dentist'
                    ? "/dentalemr_system/html/index.php?uid={$userId}"
                    : "/dentalemr_system/html/a_staff/addpatient.php?uid={$userId}";


                echo "<script>
                    alert('Verified successfully!');
                    window.location.href='{$redirect}';
                </script>";
                exit;
            }
        }

        // If no match found
        if (!$matched && !$alert) {
            $alert = "Invalid or expired code. Please try again.";
        }
    }

    // Show alert if needed
    if ($alert) {
        echo "<script>
            alert(" . json_encode($alert) . ");
            window.location.href='verify_mfa.php';
        </script>";
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MHO Dental Clinic - Verify MFA</title>
    <link href="../css/style.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>

<body>
    <section class="bg-gray-50 dark:bg-gray-900">
        <div class="absolute top-4 left-4 flex space-x-2 items-center">
            <a href="https://occidentalmindoro.gov.ph/mamburao/">
                <img src="../../img/Ph_seal_occidental_mindoro_mamburao-removebg-preview-1-150x150.png"
                    class="rounded-full w-18 h-18" alt="">
            </a>
            <a href="https://vectorseek.com/vector_logo/province-of-occidental-mindoro-logo-vector/">
                <img src="../../img/Province of Occidental Mindoro Logo Vector.svg .png" class="rounded-full w-18 h-18"
                    alt="">
            </a>
        </div>

        <div class="flex flex-col items-center justify-center px-6 py-8 mx-auto md:h-screen lg:py-0">
            <div class="w-full bg-white rounded-lg shadow dark:border md:mt-0 sm:max-w-md xl:p-0 dark:bg-gray-800 dark:border-gray-700">
                <div class="p-4 space-y-4 md:space-y-6 sm:p-4">
                    <div class="flex items-center justify-between">
                        <a class="rounded-full items-center justify-center flex">
                            <img src="/dentalemr_system/img/DOH Logo.png" class="rounded-full w-18 h-18" alt="">
                        </a>
                        <div class="items-center text-center flex flex-col w-60">
                            <h1 class="text-lg font-bold text-gray-900 dark:text-white">
                                Republic of the Philippines Department of Health Regional Office III
                            </h1>
                            <h3 class="text-sm font-semibold text-gray-900 dark:text-white">
                                Mamburao, Occidental Mindoro
                            </h3>
                        </div>
                        <a class="rounded-full items-center justify-center flex mr-[-35px] ml-[-35px]">
                            <img src="../../img/DOHDentalLogo-removebg-preview.png" class="w-30 h-20" alt="">
                        </a>
                    </div>

                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        We sent a 6-digit verification code to your email:
                        <strong><?php echo htmlspecialchars($userEmail); ?></strong>
                    </p>

                    <form class="space-y-4 md:space-y-6" method="POST">
                        <div>
                            <label for="mfa_code" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">
                                Enter Verification Code
                            </label>
                            <input type="text" name="mfa_code" id="mfa_code"
                                maxlength="6" minlength="6"
                                class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg 
                                focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 
                                dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                                placeholder="123456" required>
                        </div>
                        <button type="submit"
                            class="w-full text-white bg-blue-600 hover:bg-blue-700 
                            focus:ring-2 focus:ring-blue-300 font-medium rounded-lg 
                            text-sm px-5 py-2.5 text-center">
                            Verify
                        </button>
                    </form>

                    <form method="POST" action="resend_mfa.php">
                        <button type="submit" name="resend"
                            class="w-full mt-2 text-sm text-blue-600 hover:underline">
                            Resend Code
                        </button>
                    </form>

                </div>
            </div>
        </div>
    </section>

    <script src="https://cdn.jsdelivr.net/npm/flowbite@3.1.2/dist/flowbite.min.js"></script>
    <script src="../js/tailwind.config.js"></script>
</body>

</html>