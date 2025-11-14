<?php
session_start();
require_once __DIR__ . '/db_connect.php';
date_default_timezone_set('Asia/Manila');

// Determine if using single or multi-pending user session
if (isset($_SESSION['pending_user'])) {
    $user = $_SESSION['pending_user'];
} elseif (isset($_SESSION['pending_users']) && !empty($_SESSION['pending_users'])) {
    // Pick the first user in multi-login scenario
    $user = reset($_SESSION['pending_users']);
} else {
    die('⚠️ No pending user session found. Please log in again.');
}

// Safely get user data
$userId = $user['id'] ?? null;
$userType = $user['type'] ?? null;
$userEmail = $user['email'] ?? '(no email)';

if (!$userId || !$userType) {
    die('⚠️ Pending user session incomplete. Cannot fetch MFA codes.');
}

echo "<h2>MFA Debug Info</h2>";
echo "<p>User ID: <strong>{$userId}</strong> | Type: <strong>{$userType}</strong> | Email: <strong>{$userEmail}</strong></p>";
echo "<p>Time now: <strong>" . date('Y-m-d H:i:s') . "</strong></p><hr>";

// Fetch last 10 MFA codes for this user
$stmt = $pdo->prepare("
    SELECT id, code, expires_at, used, created_at
    FROM mfa_codes
    WHERE user_id = :uid AND user_type = :utype
    ORDER BY created_at DESC
    LIMIT 10
");
$stmt->execute([
    'uid' => $userId,
    'utype' => $userType
]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$rows) {
    echo "<p>❌ No MFA codes found for this user.</p>";
} else {
    echo "<table border='1' cellpadding='6' cellspacing='0'>
            <tr><th>ID</th><th>Code</th><th>Used</th><th>Expires</th><th>Created</th></tr>";
    foreach ($rows as $r) {
        $expired = (strtotime($r['expires_at']) < time()) ? 'Expired' : 'Active';
        echo "<tr>
            <td>{$r['id']}</td>
            <td><strong>{$r['code']}</strong></td>
            <td>{$r['used']}</td>
            <td>{$r['expires_at']} ({$expired})</td>
            <td>{$r['created_at']}</td>
        </tr>";
    }
    echo "</table>";
}

echo "<hr><p>👉 Compare the <strong>Code</strong> above with what you typed in the MFA form.</p>";
