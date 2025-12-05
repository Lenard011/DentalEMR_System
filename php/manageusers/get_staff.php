<?php
require_once "../conn.php";

// ADD THESE CACHE-CONTROL HEADERS FIRST
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");
header("Content-Type: application/json");
header("X-Content-Type-Options: nosniff");

try {
    // Verify database connection
    if (!$conn) {
        throw new Exception("Database connection failed");
    }

    // Test connection with a simple query
    if (!mysqli_ping($conn)) {
        throw new Exception("Database connection lost");
    }

    // Query to get staff with proper error handling
    $query = "SELECT 
                id, 
                name, 
                username, 
                email, 
                created_at, 
                updated_at,
                COALESCE(welcome_email_sent, 0) as welcome_email_sent,
                email_sent_at
              FROM staff 
              ORDER BY created_at DESC";

    // Log the query for debugging
    error_log("Staff Query Executed: " . date('Y-m-d H:i:s'));

    $result = mysqli_query($conn, $query);

    if (!$result) {
        throw new Exception("Database query failed: " . mysqli_error($conn));
    }

    $staffList = [];
    $rowCount = 0;

    while ($row = mysqli_fetch_assoc($result)) {
        $rowCount++;

        // Validate and sanitize each field
        $id = isset($row['id']) ? (int)$row['id'] : 0;
        $name = isset($row['name']) ? htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8') : '';
        $username = isset($row['username']) ? htmlspecialchars($row['username'], ENT_QUOTES, 'UTF-8') : '';
        $email = isset($row['email']) ? htmlspecialchars($row['email'], ENT_QUOTES, 'UTF-8') : '';
        $created_at = isset($row['created_at']) ? htmlspecialchars($row['created_at'], ENT_QUOTES, 'UTF-8') : '';
        $updated_at = isset($row['updated_at']) ? htmlspecialchars($row['updated_at'], ENT_QUOTES, 'UTF-8') : '';
        $welcome_email_sent = isset($row['welcome_email_sent']) ? (int)$row['welcome_email_sent'] : 0;
        $email_sent_at = isset($row['email_sent_at']) && !empty($row['email_sent_at'])
            ? htmlspecialchars($row['email_sent_at'], ENT_QUOTES, 'UTF-8')
            : null;

        // Log each row for debugging (optional - remove in production)
        error_log("Row $rowCount: ID=$id, Email=$email, Name=$name");

        $staffList[] = [
            'id' => $id,
            'name' => $name,
            'username' => $username,
            'email' => $email,
            'created_at' => $created_at,
            'updated_at' => $updated_at,
            'welcome_email_sent' => $welcome_email_sent,
            'email_sent_at' => $email_sent_at
        ];
    }

    mysqli_free_result($result);

    // Add debug info to response (remove debug in production)
    $response = [
        'success' => true,
        'data' => $staffList,
        'meta' => [
            'timestamp' => date('Y-m-d H:i:s'),
            'row_count' => $rowCount,
            'server_time' => time()
        ]
    ];

    echo json_encode($response, JSON_NUMERIC_CHECK | JSON_PRETTY_PRINT);
} catch (Exception $e) {
    error_log("Get staff error: " . $e->getMessage());
    http_response_code(500);

    $errorResponse = [
        'success' => false,
        'error' => 'Unable to fetch staff list',
        'message' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ];

    echo json_encode($errorResponse, JSON_PRETTY_PRINT);
}

// Close connection
if (isset($conn)) {
    mysqli_close($conn);
}
