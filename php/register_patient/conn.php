<?php 
    $hostname = "localhost";
    $dbuser = "u401132124_dentalclinic";
    $dbpass = "Mho_DentalClinic1st";
    $dbname = "u401132124_mho_dentalemr";
    
    // Connect to MySQL
    $conn = mysqli_connect($hostname, $dbuser, $dbpass);
    
    if (!$conn) {
        die("Database connection failed: " . mysqli_connect_error());
    }
    
    // Select database
    if (!mysqli_select_db($conn, $dbname)) {
        // If database doesn't exist, you might want to create it
        die("Database selection failed: " . mysqli_error($conn));
    }
    
    // Set charset to UTF-8
    mysqli_set_charset($conn, "utf8mb4");
?>