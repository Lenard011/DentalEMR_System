<?php
// db_connection.php
$host = "localhost";
$dbUser = "u401132124_dentalclinic";
$dbPass = "Mho_DentalClinic1st";
$dbName = "u401132124_mho_dentalemr";

$conn = new mysqli($host, $dbUser, $dbPass, $dbName);

if ($conn->connect_error) {
    // Don't die, just log the error
    error_log("Database connection failed: " . $conn->connect_error);
    // Return null connection to be handled by calling code
    $conn = null;
}
?>