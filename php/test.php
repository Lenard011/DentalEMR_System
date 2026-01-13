<?php
session_start();
echo "<h2>Session Debug</h2>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

// Check specific active sessions
if (isset($_SESSION['active_sessions'])) {
  echo "<h3>Active Sessions:</h3>";
  foreach ($_SESSION['active_sessions'] as $uid => $user) {
    echo "UID: $uid, Type: " . ($user['type'] ?? 'not set') . "<br>";
    echo "Details: " . print_r($user, true) . "<hr>";
  }
}
?>