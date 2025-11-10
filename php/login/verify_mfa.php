<?php
session_start();
require_once __DIR__ . '/db_connect.php';
date_default_timezone_set('Asia/Manila');

// Redirect if no pending user session
if (!isset($_SESSION['pending_user'])) {
    echo "<script>alert('Session expired. Please log in again.'); window.location.href='login.html';</script>";
    exit;
}

$user = $_SESSION['pending_user'];
$alert = "";

// Process form submission
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $enteredCode = trim($_POST['mfa_code']);

    if (empty($enteredCode)) {
        $alert = "Please enter your 6-digit code.";
    } else {
        // Get valid MFA codes
        $stmt = $pdo->prepare("
            SELECT * FROM mfa_codes
            WHERE user_id = :uid AND user_type = :utype AND used = 0
            ORDER BY created_at DESC LIMIT 5
        ");
        $stmt->execute([
            'uid' => $user['id'],
            'utype' => $user['type']
        ]);
        $codes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $matched = false;
        foreach ($codes as $c) {
            if ((string)$c['code'] === (string)$enteredCode) {
                // Check expiration
                if (strtotime($c['expires_at']) < time()) {
                    $alert = "The verification code has expired. Please request a new one.";
                    break;
                }

                $matched = true;
                $pdo->prepare("UPDATE mfa_codes SET used = 1 WHERE id = :id")->execute(['id' => $c['id']]);

                // Set login session
                $_SESSION['logged_user'] = [
                    'id' => $user['id'],
                    'email' => $user['email'],
                    'type' => $user['type']
                ];
                unset($_SESSION['pending_user']);

                // Redirect with success alert
                $redirect = ($user['type'] === 'Dentist')
                    ? "/dentalemr_system/html/index.php"
                    : "/dentalemr_system/html/staff_dashboard.php";

                echo "<script>alert('Verified successfully!'); window.location.href='{$redirect}';</script>";
                exit;
            }
        }

        if (!$matched && !$alert) {
            $alert = "Invalid or expired code. Please try again.";
        }
    }

    // Show alert if any error or info
    if ($alert) {
        echo "<script>alert(" . json_encode($alert) . "); window.location.href='verify_mfa.php';</script>";
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MHO Dental Clinic</title>
    <link href="../css/style.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>

<body>
    <section class="bg-gray-50 dark:bg-gray-900 ">
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
            <div
                class="w-full bg-white rounded-lg shadow dark:border md:mt-0 sm:max-w-md xl:p-0 dark:bg-gray-800 dark:border-gray-700">
                <div class="p-4 space-y-4 md:space-y-6 sm:p-4">
                    <div class="flex  items-center  justify-between">
                        <a href="#" class="rounded-full  items-center justify-center flex">
                            <img src="/dentalemr_system/img/DOH Logo.png" class="rounded-full w-18 h-18" alt="">
                        </a>
                        <div class="items-center text-center flex flex-col w-60 ">
                            <h1 class="text-lg font-bold leading-tight tracking-tight text-gray-900  dark:text-white">
                                Republic of the Philippines Department of Health Regional Office III
                            </h1>
                            <h3
                                class="text-sm font-semibold leading-tight tracking-tight text-gray-900  dark:text-white">
                                Mamburao, Occidental Mindoro
                            </h3>
                        </div>
                        <a href="#" class=" rounded-full  items-center justify-center flex  mr-[-35px] ml-[-35px]">
                            <img src="../../img/DOHDentalLogo-removebg-preview.png" class="w-30 h-20" alt="">
                        </a>
                    </div>
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        We sent a 6-digit verification code to your email:
                        <strong><?php echo htmlspecialchars($user['email']); ?></strong>
                    </p>
                    <form class="space-y-4 md:space-y-6" method="POST">
                        <div>
                            <label for="mfa_code" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">
                                Enter Verification Code
                            </label>
                            <input type="text" name="mfa_code" id="mfa_code" maxlength="6" minlength="6"
                                class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                                placeholder="123456" required>
                        </div>
                        <button type="submit"
                            class="w-full text-white bg-blue-600 hover:bg-blue-700 focus:ring-2 focus:outline-none focus:ring-blue-300 font-medium rounded-lg text-sm px-5 py-2.5 text-center">
                            Verify
                        </button>
                    </form>
                    <form method="POST" action="resend_mfa.php">
                        <button type="submit" name="resend"
                            class="w-full mt-2 text-sm text-blue-600 hover:underline focus:outline-none">
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