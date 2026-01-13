<?php
header('Content-Type: application/json; charset=utf-8');
mysqli_report(MYSQLI_REPORT_OFF);
// Database connection
$mysqli = new mysqli("localhost", "u401132124_dentalclinic", "Mho_DentalClinic1st", "u401132124_mho_dentalemr");
if ($mysqli->connect_errno) {
    echo json_encode(["error" => "Database connection failed"]);
    exit;
}

// Ensure table exists
$check = $mysqli->query("SHOW TABLES LIKE 'archived_patients'");
if (!$check || $check->num_rows === 0) {
    echo json_encode(["error" => "No archived_patients table found"]);
    exit;
}

// Pagination parameters
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$rowsPerPage = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $rowsPerPage;

// Search parameter (optional)
$search = isset($_GET['search']) ? trim($_GET['search']) : "";
$where = "";

if ($search !== "") {
    // Use prepared statement style escaping
    $safeSearch = $mysqli->real_escape_string($search);
    $where = "WHERE CONCAT_WS(' ', firstname, middlename, surname, address, sex, age) LIKE '%$safeSearch%'";
}

// Count total
$countSql = "SELECT COUNT(*) AS total FROM archived_patients $where";
$countResult = $mysqli->query($countSql);
$totalRows = $countResult ? (int)$countResult->fetch_assoc()['total'] : 0;

// Fetch paginated records
$sql = "SELECT archive_id, patient_id, firstname, middlename, surname, sex, age, address, created_at
        FROM archived_patients
        $where
        ORDER BY archive_id DESC
        LIMIT $rowsPerPage OFFSET $offset";

$result = $mysqli->query($sql);
$patients = [];

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $row['fullname'] = trim(($row['firstname'] ?? '') . ' ' . ($row['middlename'] ?? '') . ' ' . ($row['surname'] ?? ''));
        $patients[] = $row;
    }
}

echo json_encode([
    "data" => $patients,
    "total" => $totalRows,
    "page" => $page,
    "limit" => $rowsPerPage
]);

$mysqli->close();
exit;
