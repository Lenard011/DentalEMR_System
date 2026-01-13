<?php
session_start();
require_once "../conn.php";

// Get current user ID for redirect and logging
$currentUserId = null;
if (isset($_GET['uid']) && is_numeric($_GET['uid'])) {
  $currentUserId = intval($_GET['uid']);
} elseif (isset($_SESSION['active_sessions']) && !empty($_SESSION['active_sessions'])) {
  $sessions = array_keys($_SESSION['active_sessions']);
  $currentUserId = $sessions[0];
}

// Build redirect URL with user ID
$redirectUrl = $currentUserId
  ? "/DentalEMR_System/html/manageusers/manageuser.php?uid=" . $currentUserId
  : "/DentalEMR_System/html/manageusers/manageuser.php";

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
  echo "<script>
            alert('Invalid request: No valid ID provided.');
            window.location.href='{$redirectUrl}';
          </script>";
  exit;
}

$staffId = intval($_GET['id']);

// Validate staff exists and get details for logging
$checkQuery = "SELECT id, name, email FROM staff WHERE id = ?";
$checkStmt = mysqli_prepare($conn, $checkQuery);
mysqli_stmt_bind_param($checkStmt, "i", $staffId);
mysqli_stmt_execute($checkStmt);
$result = mysqli_stmt_get_result($checkStmt);
$staffData = mysqli_fetch_assoc($result);
mysqli_stmt_close($checkStmt);

if (!$staffData) {
  echo "<script>
            alert('Staff account not found.');
            window.location.href='{$redirectUrl}';
          </script>";
  exit;
}

// Start transaction for data consistency
mysqli_begin_transaction($conn);

try {
  // Delete the staff record
  $deleteQuery = "DELETE FROM staff WHERE id = ?";
  $deleteStmt = mysqli_prepare($conn, $deleteQuery);
  mysqli_stmt_bind_param($deleteStmt, "i", $staffId);

  $deleteSuccess = mysqli_stmt_execute($deleteStmt);
  mysqli_stmt_close($deleteStmt);

  if ($deleteSuccess) {
    // Log the deletion activity
    if ($currentUserId) {
      $logAction = "DELETE_STAFF";
      $logDetails = "Deleted staff account: " . ($staffData['name'] ?? 'Unknown') . " (ID: $staffId)";

      // You would call your log_history function here
      // log_history($currentUserId, $logAction, $logDetails);
    }

    mysqli_commit($conn);
    echo "<script>
                alert('Staff account deleted successfully.');
                window.location.href='{$redirectUrl}';
              </script>";
  } else {
    throw new Exception("Failed to delete staff account");
  }
} catch (Exception $e) {
  mysqli_rollback($conn);
  error_log("Delete staff error: " . $e->getMessage());
  echo "<script>
            alert('Error deleting staff account. Please try again.');
            window.location.href='{$redirectUrl}';
          </script>";
}
