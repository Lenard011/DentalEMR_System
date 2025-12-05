<?php
require_once "../conn.php";

header("Content-Type: application/json");
header("X-Content-Type-Options: nosniff");

// Set proper JSON header
header('Content-Type: application/json');

try {
    $query = "SELECT id, name, username, email, created_at, updated_at FROM staff ORDER BY created_at DESC";
    $result = mysqli_query($conn, $query);

    if (!$result) {
        throw new Exception("Database query failed: " . mysqli_error($conn));
    }

    $staffList = [];

    while ($row = mysqli_fetch_assoc($result)) {
        // Sanitize data before output
        $staffList[] = [
            'id' => (int)$row['id'],
            'name' => htmlspecialchars($row['name'] ?? '', ENT_QUOTES, 'UTF-8'),
            'username' => htmlspecialchars($row['username'] ?? '', ENT_QUOTES, 'UTF-8'),
            'email' => htmlspecialchars($row['email'] ?? '', ENT_QUOTES, 'UTF-8'),
            'created_at' => htmlspecialchars($row['created_at'] ?? '', ENT_QUOTES, 'UTF-8'),
            'updated_at' => htmlspecialchars($row['updated_at'] ?? '', ENT_QUOTES, 'UTF-8')
        ];
    }

    echo json_encode($staffList);
} catch (Exception $e) {
    error_log("Get staff error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Unable to fetch staff list']);
}

mysqli_close($conn);
