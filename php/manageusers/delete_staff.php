<?php
session_start();
require_once "../conn.php"; // adjust the path if needed
include_once "/dentalemr_system/php/manageusers/log_history.php";
if (!isset($_GET['id'])) {
    echo "<script>
            alert('Invalid request: No ID provided.');
            window.location.href='/dentalemr_system/html/manageusers/manageuser.php';
          </script>";
    exit;
}

$staffId = intval($_GET['id']); // sanitize ID

// Check if staff exists
$checkQuery = "SELECT id FROM staff WHERE id = ?";
$checkStmt = mysqli_prepare($conn, $checkQuery);
mysqli_stmt_bind_param($checkStmt, "i", $staffId);
mysqli_stmt_execute($checkStmt);
mysqli_stmt_store_result($checkStmt);

if (mysqli_stmt_num_rows($checkStmt) === 0) {
    echo "<script>
            alert('Staff account not found.');
            window.location.href='/dentalemr_system/html/manageusers/manageuser.php';
          </script>";
    exit;
}

// Delete the staff record
$deleteQuery = "DELETE FROM staff WHERE id = ?";
$deleteStmt = mysqli_prepare($conn, $deleteQuery);
mysqli_stmt_bind_param($deleteStmt, "i", $staffId);

if (mysqli_stmt_execute($deleteStmt)) {
    echo "<script>
            alert('Staff account deleted successfully.');
            window.location.href='/dentalemr_system/html/manageusers/manageuser.php';
          </script>";
} else {
    echo "<script>
            alert('Error deleting staff account.');
            window.location.href='/dentalemr_system/html/manageusers/manageuser.php';
          </script>";
}
