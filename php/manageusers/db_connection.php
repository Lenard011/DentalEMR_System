<?php
// db_connection.php
$host = "localhost";
$dbUser = "root";
$dbPass = "";
$dbName = "dentalemr_system";

$conn = new mysqli($host, $dbUser, $dbPass, $dbName);

if ($conn->connect_error) {
    // Don't die, just log the error
    error_log("Database connection failed: " . $conn->connect_error);
    // Return null connection to be handled by calling code
    $conn = null;
}
?>