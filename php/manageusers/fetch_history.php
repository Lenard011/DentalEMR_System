<?php
header("Content-Type: application/json");

// DB connection
$conn = new mysqli("localhost", "root", "", "dentalemr_system");
if ($conn->connect_error) {
    die(json_encode(["error" => "Database connection failed"]));
}

// Pagination parameters
$page  = isset($_GET["page"]) ? (int)$_GET["page"] : 1;
$limit = isset($_GET["limit"]) ? (int)$_GET["limit"] : 10;
$start = ($page - 1) * $limit;

// Search
$search = isset($_GET["search"]) ? $conn->real_escape_string($_GET["search"]) : "";

// Base WHERE clause
$where = "
    table_name LIKE '%$search%' OR
    action LIKE '%$search%' OR
    description LIKE '%$search%' OR
    changed_by_type LIKE '%$search%' OR
    changed_by_id LIKE '%$search%'
";

// Get total number of rows for pagination
$totalQuery = "
    SELECT COUNT(*) AS total 
    FROM history_logs
    WHERE $where
";

$totalResult = $conn->query($totalQuery);
$totalRow = $totalResult->fetch_assoc();
$total = (int)$totalRow["total"];

// Get paginated results
$query = "
    SELECT *
    FROM history_logs
    WHERE $where
    ORDER BY history_id ASC
    LIMIT $start, $limit
";

$result = $conn->query($query);

$history = [];
while ($row = $result->fetch_assoc()) {
    $history[] = $row;
}

echo json_encode([
    "history" => $history,
    "page" => $page,
    "limit" => $limit,
    "total" => $total
]);
