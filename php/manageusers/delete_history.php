<?php
require_once "../conn.php"; // adjust if needed

header("Content-Type: text/plain");

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo "Invalid request.";
    exit;
}

$history_id = intval($_GET['id']);

try {

    // Step 1: Delete the row
    $delete = $conn->prepare("DELETE FROM history_logs WHERE history_id = ?");
    $delete->bind_param("i", $history_id);

    if (!$delete->execute()) {
        echo "Failed to delete entry.";
        exit;
    }

    // Step 2: Renumber all rows starting from 1
    $conn->query("SET @num := 0");
    $conn->query("UPDATE history_logs SET history_id = (@num := @num + 1) ORDER BY history_id");

    // Step 3: Reset AUTO_INCREMENT back to 1
    $conn->query("ALTER TABLE history_logs AUTO_INCREMENT = 1");

    echo "History log deleted and IDs renumbered.";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}

$conn->close();
?>
