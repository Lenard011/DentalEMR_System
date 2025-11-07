<?php
session_start();
require_once __DIR__ . '/db_connect.php';
date_default_timezone_set('Asia/Manila');

if (!isset($_SESSION['pending_user'])) {
    die('⚠️ No pending user session found. Log in again.');
}

$user = $_SESSION['pending_user'];

echo "<h2>MFA Debug Info</h2>";
echo "<p>User ID: <strong>{$user['id']}</strong> | Type: <strong>{$user['type']}</strong></p>";
echo "<p>Time now: <strong>" . date('Y-m-d H:i:s') . "</strong></p><hr>";

$stmt = $pdo->prepare("
    SELECT id, code, expires_at, used, created_at
    FROM mfa_codes
    WHERE user_id = :uid AND user_type = :utype
    ORDER BY created_at DESC
    LIMIT 10
");
$stmt->execute([
    'uid' => $user['id'],
    'utype' => $user['type']
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
?>
