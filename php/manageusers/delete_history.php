<?php
session_start();
header('Content-Type: application/json');

// Get history IDs from POST data
$input = json_decode(file_get_contents('php://input'), true);
$history_ids = $input['history_ids'] ?? [];

if (empty($history_ids)) {
    echo json_encode(['success' => false, 'error' => 'No history logs selected']);
    exit;
}

// Validate history IDs
$valid_history_ids = [];
foreach ($history_ids as $id) {
    if (is_numeric($id) && $id > 0) {
        $valid_history_ids[] = (int)$id;
    }
}

if (empty($valid_history_ids)) {
    echo json_encode(['success' => false, 'error' => 'Invalid history IDs']);
    exit;
}

// Database connection
$host = "localhost";
$dbUser = "root";
$dbPass = "";
$dbName = "dentalemr_system";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Delete the history logs
    $placeholders = str_repeat('?,', count($valid_history_ids) - 1) . '?';
    $stmt = $pdo->prepare("DELETE FROM history_logs WHERE history_id IN ($placeholders)");
    $stmt->execute($valid_history_ids);

    $deletedCount = $stmt->rowCount();

    echo json_encode([
        'success' => true,
        'deleted_count' => $deletedCount,
        'message' => "Successfully deleted $deletedCount history log(s)"
    ]);
} catch (PDOException $e) {
    error_log("Delete history error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
