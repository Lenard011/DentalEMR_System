<?php
// Simple database connector
function connectDB() {
    $host = "localhost";
    $user = "u401132124_dentalclinic";
    $pass = "Mho_DentalClinic1st";
    $db   = "u401132124_mho_dentalemr";

    $conn = new mysqli($host, $user, $pass, $db);
    if ($conn->connect_error) {
        http_response_code(500);
        echo json_encode(["error" => "Database connection failed: " . $conn->connect_error]);
        exit;
    }
    return $conn;
}
?>
