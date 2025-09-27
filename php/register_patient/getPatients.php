<?php
header("Content-Type: application/json; charset=utf-8");

$DB_HOST = "localhost";
$DB_USER = "root";
$DB_PASS = "";
$DB_NAME = "dentalemr_system";

$mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($mysqli->connect_errno) {
    echo json_encode(["error" => "DB connection failed: " . $mysqli->connect_error]);
    exit;
}
$mysqli->set_charset("utf8mb4");

$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
if ($limit < 1) $limit = 10;
if ($limit > 200) $limit = 200;

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

$search = isset($_GET['search']) ? trim($_GET['search']) : "";
$filterAddresses = isset($_GET['addresses']) ? $_GET['addresses'] : "";

try {
    $whereParts = [];
    $params = [];
    $types = "";

    if ($search !== "") {
        $like = "%{$search}%";
        $whereParts[] = "(surname LIKE ? OR firstname LIKE ? OR middlename LIKE ? OR address LIKE ?)";
        $params = array_merge($params, [$like, $like, $like, $like]);
        $types .= "ssss";
    }

    if ($filterAddresses !== "") {
        $addrArr = array_map("trim", explode(",", $filterAddresses));
        $addrArr = array_filter($addrArr, fn($a) => $a !== "");
        if (count($addrArr) > 0) {
            $placeholders = implode(",", array_fill(0, count($addrArr), "?"));
            $whereParts[] = "address IN ($placeholders)";
            $params = array_merge($params, $addrArr);
            $types .= str_repeat("s", count($addrArr));
        }
    }

    $whereSql = count($whereParts) > 0 ? "WHERE " . implode(" AND ", $whereParts) : "";

    // main query
    $sql = "SELECT patient_id, surname, firstname, middlename, sex, age, address
            FROM patients
            $whereSql
            ORDER BY patient_id ASC
            LIMIT ? OFFSET ?";
    $stmt = $mysqli->prepare($sql);
    $params[] = $limit;
    $params[] = $offset;
    $types .= "ii";
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
    $patients = $res->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // count query
    $countSql = "SELECT COUNT(*) as total FROM patients $whereSql";
    $cstmt = $mysqli->prepare($countSql);
    if ($types !== "") {
        $ctypes = substr($types, 0, -2);
        $cparams = array_slice($params, 0, -2);
        if ($ctypes !== "") {
            $cstmt->bind_param($ctypes, ...$cparams);
        }
    }
    $cstmt->execute();
    $cres = $cstmt->get_result();
    $countRow = $cres->fetch_assoc();
    $total = (int)$countRow['total'];
    $cstmt->close();

    // distinct addresses
    $addrRes = $mysqli->query("SELECT DISTINCT address FROM patients WHERE address IS NOT NULL AND address <> '' ORDER BY address ASC");
    $addresses = [];
    while ($row = $addrRes->fetch_assoc()) {
        $addresses[] = $row['address'];
    }

    echo json_encode([
        "patients"  => $patients,
        "total"     => $total,
        "limit"     => $limit,
        "page"      => $page,
        "addresses" => $addresses
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    echo json_encode(["error" => $e->getMessage()]);
}

$mysqli->close();
