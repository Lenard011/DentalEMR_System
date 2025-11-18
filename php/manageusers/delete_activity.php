<?php
session_start();
header('Content-Type: application/json');

// Get user ID from POST data
$input = json_decode(file_get_contents('php://input'), true);
$activity_ids = $input['activity_ids'] ?? [];
$user_id = $input['user_id'] ?? null;

// Check if user is authorized to delete activities
if (!$user_id) {
    echo json_encode(['success' => false, 'error' => 'User ID is required']);
    exit;
}

if (!isset($_SESSION['active_sessions'][$user_id])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized - Please log in again']);
    exit;
}

if (empty($activity_ids)) {
    echo json_encode(['success' => false, 'error' => 'No activities selected']);
    exit;
}

// Validate activity IDs
$valid_activity_ids = [];
foreach ($activity_ids as $id) {
    if (is_numeric($id) && $id > 0) {
        $valid_activity_ids[] = (int)$id;
    }
}

if (empty($valid_activity_ids)) {
    echo json_encode(['success' => false, 'error' => 'Invalid activity IDs']);
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

    // Start transaction
    $pdo->beginTransaction();

    // Get user information - FIXED: Check both dentist and staff tables
    $user_name = 'Unknown User';
    $user_type = 'Unknown';

    // First try dentist table
    $dentist_stmt = $pdo->prepare("SELECT id, name, email FROM dentist WHERE id = ?");
    $dentist_stmt->execute([$user_id]);
    $dentist_data = $dentist_stmt->fetch(PDO::FETCH_ASSOC);

    if ($dentist_data) {
        $user_name = $dentist_data['name'] ?? 'Unknown Dentist';
        $user_type = 'Dentist';
    } else {
        // If not found in dentist table, try staff table
        $staff_stmt = $pdo->prepare("SELECT id, name, email FROM staff WHERE id = ?");
        $staff_stmt->execute([$user_id]);
        $staff_data = $staff_stmt->fetch(PDO::FETCH_ASSOC);

        if ($staff_data) {
            $user_name = $staff_data['name'] ?? 'Unknown Staff';
            $user_type = 'Staff';
        } else {
            // Final fallback: check activity logs for this user's recent activity
            $fallback_stmt = $pdo->prepare("
                SELECT user_name 
                FROM activity_logs 
                WHERE user_id = ? 
                ORDER BY created_at DESC 
                LIMIT 1
            ");
            $fallback_stmt->execute([$user_id]);
            $fallback_data = $fallback_stmt->fetch(PDO::FETCH_ASSOC);

            if ($fallback_data && !empty($fallback_data['user_name'])) {
                $user_name = $fallback_data['user_name'];
            }
        }
    }

    // Get IP address properly
    $ip_address = 'Unknown';
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip_address = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip_address = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
        $ip_address = $_SERVER['REMOTE_ADDR'];
    }

    // Log the deletion activity for audit
    $user_agent = substr($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown', 0, 255);

    $log_stmt = $pdo->prepare("
        INSERT INTO activity_logs 
        (user_id, user_name, action, target, details, ip_address, user_agent, created_at) 
        VALUES (?, ?, 'DELETE', 'Activity Logs', ?, ?, ?, NOW())
    ");

    $log_details = "Deleted " . count($valid_activity_ids) . " activity log(s): " . implode(', ', $valid_activity_ids);
    $log_stmt->execute([$user_id, $user_name, $log_details, $ip_address, $user_agent]);

    // Delete the activities
    $placeholders = str_repeat('?,', count($valid_activity_ids) - 1) . '?';
    $stmt = $pdo->prepare("DELETE FROM activity_logs WHERE id IN ($placeholders)");
    $stmt->execute($valid_activity_ids);

    $deletedCount = $stmt->rowCount();

    // Commit transaction
    $pdo->commit();

    echo json_encode([
        'success' => true,
        'deleted_count' => $deletedCount,
        'message' => "Successfully deleted $deletedCount activity log(s)",
        'user_info' => $user_name // For debugging
    ]);
} catch (PDOException $e) {
    // Rollback on error
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log("Delete activity error - User ID: {$user_id}, Error: " . $e->getMessage());

    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage() // Show detailed error for debugging
    ]);
}
