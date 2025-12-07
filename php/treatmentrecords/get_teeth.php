<?php
// get_teeth.php
header('Content-Type: application/json; charset=utf-8');

// Debug
error_log("get_teeth.php accessed");

// Try to include conns.php with multiple possible paths
$conns_path = __DIR__ . '/conns.php';
if (!file_exists($conns_path)) {
    $conns_path = __DIR__ . '/../conns.php';
}

if (!file_exists($conns_path)) {
    error_log("conns.php not found at: " . $conns_path);
    echo json_encode(['error' => 'Database configuration file not found']);
    exit;
}

require_once $conns_path;

// Try different database connection variable names
$db = null;

if (isset($pdo) && $pdo instanceof PDO) {
    $db = $pdo;
    error_log("Using PDO connection");
} elseif (isset($conn) && $conn instanceof PDO) {
    $db = $conn;
    error_log("Using conn connection");
} elseif (isset($db) && $db instanceof PDO) {
    error_log("Using db connection");
    // $db is already set
} else {
    error_log("No valid database connection found");
    echo json_encode(['error' => 'No database connection']);
    exit;
}

try {
    error_log("Executing query: SELECT tooth_id, fdi_number, type, location FROM teeth ORDER BY tooth_id");
    $stmt = $db->query("SELECT tooth_id, fdi_number, type, location FROM teeth ORDER BY tooth_id");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    error_log("Found " . count($rows) . " teeth records");

    if (empty($rows)) {
        // Return some fallback data if table is empty
        error_log("No teeth found in database, creating fallback data");
        $rows = [];

        // Create mapping for FDI numbers to tooth_ids
        $fdiToId = [
            // Permanent teeth (1-32)
            11 => 1,
            12 => 2,
            13 => 3,
            14 => 4,
            15 => 5,
            16 => 6,
            17 => 7,
            18 => 8,
            21 => 9,
            22 => 10,
            23 => 11,
            24 => 12,
            25 => 13,
            26 => 14,
            27 => 15,
            28 => 16,
            31 => 17,
            32 => 18,
            33 => 19,
            34 => 20,
            35 => 21,
            36 => 22,
            37 => 23,
            38 => 24,
            41 => 25,
            42 => 26,
            43 => 27,
            44 => 28,
            45 => 29,
            46 => 30,
            47 => 31,
            48 => 32,

            // Temporary teeth (33-52)
            51 => 33,
            52 => 34,
            53 => 35,
            54 => 36,
            55 => 37,
            61 => 38,
            62 => 39,
            63 => 40,
            64 => 41,
            65 => 42,
            71 => 43,
            72 => 44,
            73 => 45,
            74 => 46,
            75 => 47,
            81 => 48,
            82 => 49,
            83 => 50,
            84 => 51,
            85 => 52
        ];

        foreach ($fdiToId as $fdi => $tooth_id) {
            $rows[] = [
                'tooth_id' => $tooth_id,
                'fdi_number' => $fdi,
                'type' => ($fdi <= 48) ? 'permanent' : 'temporary',
                'location' => ($fdi <= 28) ? 'upper' : (($fdi <= 48) ? 'lower' : 'temporary')
            ];
        }
    }

    echo json_encode($rows);
} catch (Exception $e) {
    error_log("Error in get_teeth.php: " . $e->getMessage());
    echo json_encode(['error' => $e->getMessage()]);
}
