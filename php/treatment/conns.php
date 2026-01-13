<?php
// conns.php - Database connection for treatment APIs
try {
    $host = "localhost";
    $dbname = "u401132124_mho_dentalemr";
    $username = "u401132124_dentalclinic";
    $password = "Mho_DentalClinic1st";

    $db = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    $db = null;
}
?>