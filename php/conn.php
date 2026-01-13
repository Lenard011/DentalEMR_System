<?php 
    $hostname = "localhost";
    $dbuser = "u401132124_dentalclinic";
    $dbpass = "Mho_DentalClinic1st";
    $dbname = "u401132124_mho_dentalemr";
    $conn = mysqli_connect($hostname, $dbuser, $dbpass, $dbname);
    if (!$conn){
        die("Something went wrong!");
    }
?>