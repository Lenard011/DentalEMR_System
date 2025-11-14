<?php
require_once "../conn.php";

$query = "SELECT * FROM staff ORDER BY created_at DESC";
$result = mysqli_query($conn, $query);

$staffList = [];

while ($row = mysqli_fetch_assoc($result)) {
    $staffList[] = $row;
}

header('Content-Type: application/json');
echo json_encode($staffList);
?>
