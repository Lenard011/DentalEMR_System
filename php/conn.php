<?php 
    $hostname = "localhost";
    $dbuser = "root";
    $dbpass = "";
    $dbname = "dentalemr_system";
    $conn = mysqli_connect($hostname, $dbuser, $dbpass, $dbname);
    if (!$conn){
        die("Something went wrong!");
    }
?>