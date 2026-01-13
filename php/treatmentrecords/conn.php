<?php
// conn.php - Database connection
$host = "localhost";
$username = "u401132124_dentalclinic";
$password = "Mho_DentalClinic1st";
$database = "u401132124_mho_dentalemr";

$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");
?>