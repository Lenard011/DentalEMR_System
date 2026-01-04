<?php
session_start();
date_default_timezone_set('Asia/Manila');

// Check authentication
if (!isset($_GET['uid']) && !isset($_GET['offline'])) {
    die("Unauthorized access.");
}

// Get parameters
$year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');
$part = isset($_GET['part']) ? (int)$_GET['part'] : 1; // 1 for part 1, 2 for part 2
$exportType = isset($_GET['type']) ? $_GET['type'] : 'excel'; // excel or csv or html

// Database connection
$conn = new mysqli("localhost", "root", "", "dentalemr_system");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
// Check if target_client_list_data table exists, if not create it
$tableCheck = $conn->query("SHOW TABLES LIKE 'target_client_list_data'");
if ($tableCheck->num_rows == 0) {
    // Create the table if it doesn't exist
    $createTableSQL = "CREATE TABLE IF NOT EXISTS target_client_list_data (
        id INT PRIMARY KEY AUTO_INCREMENT,
        patient_id INT NOT NULL,
        year INT NOT NULL,
        oe VARCHAR(10),
        iiohc VARCHAR(10),
        aebf VARCHAR(10),
        tfa VARCHAR(10),
        stb VARCHAR(10),
        ohe VARCHAR(10),
        ecc VARCHAR(10),
        art VARCHAR(10),
        ops VARCHAR(10),
        pfs VARCHAR(10),
        tf VARCHAR(10),
        pf VARCHAR(10),
        gt VARCHAR(10),
        rp VARCHAR(10),
        rut VARCHAR(10),
        ref VARCHAR(10),
        tpec VARCHAR(10),
        dr VARCHAR(10),
        bohc_0_11 VARCHAR(10),
        bohc_1_4 VARCHAR(10),
        bohc_5_9 VARCHAR(10),
        bohc_10_19 VARCHAR(10),
        bohc_20_59 VARCHAR(10),
        bohc_60_plus VARCHAR(10),
        bohc_pregnant VARCHAR(10),
        remarks TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        created_by INT,
        UNIQUE KEY unique_patient_year (patient_id, year)
    )";
    $conn->query($createTableSQL);
}

// Helper functions
function compute_age_from_dob($dob)
{
    if (!$dob || $dob === "0000-00-00") return null;
    $dob_dt = new DateTime($dob);
    $now = new DateTime();
    return $now->diff($dob_dt)->y;
}

function compute_months_from_dob($dob)
{
    if (!$dob || $dob === "0000-00-00") return null;
    $dob_dt = new DateTime($dob);
    $now = new DateTime();
    $diff = $now->diff($dob_dt);
    return ($diff->y * 12) + $diff->m;
}

function is_truthy($v)
{
    if ($v === null) return false;
    $vstr = strtolower((string)$v);
    return in_array($vstr, ['1', 'true', 'y', 'yes', 't']);
}

// Export based on part
if ($part == 1) {
    exportPart1($conn, $year, $exportType);
} elseif ($part == 2) {
    exportPart2($conn, $year, $exportType);
} else {
    die("Invalid part specified.");
}

function exportPart1($conn, $year, $exportType)
{
    // Fetch patients for the year
    $sql = "
    SELECT 
        p.*,
        COALESCE(
            (SELECT o.indigenous_people 
             FROM patient_other_info o 
             WHERE o.patient_id = p.patient_id 
             ORDER BY o.info_id DESC 
             LIMIT 1), 
            ''
        ) AS indigenous_people,
        COALESCE(
            (SELECT oh.orally_fit_child 
             FROM oral_health_condition oh 
             WHERE oh.patient_id = p.patient_id 
             ORDER BY oh.created_at DESC 
             LIMIT 1), 
            ''
        ) AS orally_fit_child,
        (SELECT oh.perm_decayed_teeth_d 
         FROM oral_health_condition oh 
         WHERE oh.patient_id = p.patient_id 
         ORDER BY oh.created_at DESC 
         LIMIT 1) AS perm_decayed_teeth_d,
        (SELECT oh.perm_missing_teeth_m 
         FROM oral_health_condition oh 
         WHERE oh.patient_id = p.patient_id 
         ORDER BY oh.created_at DESC 
         LIMIT 1) AS perm_missing_teeth_m,
        (SELECT oh.perm_filled_teeth_f 
         FROM oral_health_condition oh 
         WHERE oh.patient_id = p.patient_id 
         ORDER BY oh.created_at DESC 
         LIMIT 1) AS perm_filled_teeth_f,
        (SELECT oh.created_at 
         FROM oral_health_condition oh 
         WHERE oh.patient_id = p.patient_id 
         ORDER BY oh.created_at DESC 
         LIMIT 1) AS oral_health_recorded_at
    FROM patients p
    WHERE YEAR(p.created_at) = $year
    ORDER BY p.created_at DESC";

    $result = $conn->query($sql);

    if ($exportType == 'excel') {
        exportToExcelPart1($result, $year);
    } elseif ($exportType == 'csv') {
        exportToCSVPart1($result, $year);
    } else {
        exportToHTMLPart1($result, $year);
    }
}

function exportPart2($conn, $year, $exportType)
{
    // Fetch patients and their target client data
    $sql = "SELECT p.*, t.* 
            FROM patients p
            LEFT JOIN target_client_list_data t ON p.patient_id = t.patient_id AND t.year = $year
            WHERE YEAR(p.created_at) = $year
            ORDER BY p.created_at DESC";

    $result = $conn->query($sql);

    if ($exportType == 'excel') {
        exportToExcelPart2($result, $year);
    } elseif ($exportType == 'csv') {
        exportToCSVPart2($result, $year);
    } else {
        exportToHTMLPart2($result, $year);
    }
}

function exportToExcelPart1($result, $year)
{
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment;filename="Target_Client_List_Part1_' . $year . '_' . date('Ymd_His') . '.xls"');
    header('Cache-Control: max-age=0');

    echo "<!DOCTYPE html>";
    echo "<html>";
    echo "<head>";
    echo "<meta charset=\"UTF-8\">";
    echo "<style>";
    echo "table { border-collapse: collapse; width: 100%; }";
    echo "th, td { border: 1px solid #000; padding: 4px; text-align: center; font-size: 11px; }";
    echo "th { background-color: #4F81BD; color: white; font-weight: bold; }";
    echo ".header-bg { background-color: #D9E1F2; }";
    echo "</style>";
    echo "</head>";
    echo "<body>";

    echo "<h2 style='text-align:center;'>TARGET CLIENT LIST FOR ORAL HEALTH CARE AND SERVICES</h2>";
    echo "<h3 style='text-align:center;'>Year: " . $year . "</h3>";
    echo "<p style='text-align:center;font-size:10px;'>Generated on: " . date('F d, Y h:i A') . "</p>";

    echo "<table>";

    // Table headers
    echo "<tr class='header-bg'>";
    echo "<th rowspan='3'>No.</th>";
    echo "<th rowspan='3'>Date of<br>Consultation</th>";
    echo "<th rowspan='3'>Family<br>Serial No.</th>";
    echo "<th rowspan='3'>Name of Client</th>";
    echo "<th colspan='2'>Sex</th>";
    echo "<th rowspan='3'>Complete<br>Address</th>";
    echo "<th rowspan='3'>Date<br>of Birth</th>";
    echo "<th colspan='10'>Age / Risk Group</th>";
    echo "<th rowspan='3'>Indigenous<br>People</th>";
    echo "<th colspan='2'>Oral Health Status<br>for Children</th>";
    echo "<th colspan='3'>DMFT</th>";
    echo "</tr>";

    echo "<tr class='header-bg'>";
    echo "<th>M</th><th>F</th>";
    echo "<th>0-11<br>mos.</th>";
    echo "<th>1-4<br>y/o</th>";
    echo "<th>5-9<br>y/o</th>";
    echo "<th>10-14<br>y/o</th>";
    echo "<th>15-19<br>y/o</th>";
    echo "<th>20-59<br>y/o</th>";
    echo "<th>>=60<br>y/o</th>";
    echo "<th colspan='3'>Pregnant</th>";
    echo "<th>Orally Fit<br>Upon<br>Examination</th>";
    echo "<th>Orally Fit<br>After Rehab</th>";
    echo "<th>Decayed<br>Tooth</th>";
    echo "<th>Missing<br>Tooth</th>";
    echo "<th>Filled<br>Tooth</th>";
    echo "</tr>";

    echo "<tr class='header-bg'>";
    echo "<th></th><th></th><th></th><th></th><th></th><th></th><th></th><th></th>";
    echo "<th>10-14<br>y/o</th>";
    echo "<th>15-19<br>y/o</th>";
    echo "<th>20-49<br>y/o</th>";
    echo "<th></th><th></th><th></th><th></th><th></th>";
    echo "</tr>";

    // Data rows
    $i = 1;
    while ($row = $result->fetch_assoc()) {
        $age = compute_age_from_dob($row['date_of_birth']);
        $months_old = compute_months_from_dob($row['date_of_birth']);

        echo "<tr>";
        echo "<td>" . $i++ . "</td>";
        echo "<td>" . (!empty($row['created_at']) ? date('m/d/y', strtotime($row['created_at'])) : '') . "</td>";
        echo "<td>" . $row['patient_id'] . "</td>";
        echo "<td style='text-align:left;'>" . htmlspecialchars($row['surname'] . ', ' . $row['firstname'] . ' ' . $row['middlename']) . "</td>";

        $sex = strtoupper(trim($row['sex'] ?? ''));
        echo "<td>" . (in_array($sex, ['M', 'MALE']) ? '✓' : '') . "</td>";
        echo "<td>" . (in_array($sex, ['F', 'FEMALE']) ? '✓' : '') . "</td>";

        echo "<td style='text-align:left;'>" . htmlspecialchars($row['address']) . "</td>";
        echo "<td>" . (!empty($row['date_of_birth']) && $row['date_of_birth'] !== '0000-00-00' ? date('m/d/Y', strtotime($row['date_of_birth'])) : '') . "</td>";

        // Age buckets
        $is_0_11 = ($age === 0 && $months_old !== null && $months_old <= 11);
        $is_1_4 = ($age !== null && $age >= 1 && $age <= 4);
        $is_5_9 = ($age !== null && $age >= 5 && $age <= 9);
        $is_10_14 = ($age !== null && $age >= 10 && $age <= 14);
        $is_15_19 = ($age !== null && $age >= 15 && $age <= 19);
        $is_20_59 = ($age !== null && $age >= 20 && $age <= 59);
        $is_ge_60 = ($age !== null && $age >= 60);

        echo "<td>" . ($is_0_11 ? ($months_old !== null ? $months_old . ' mo' : '') : '') . "</td>";
        echo "<td>" . ($is_1_4 ? $age : '') . "</td>";
        echo "<td>" . ($is_5_9 ? $age : '') . "</td>";
        echo "<td>" . ($is_10_14 ? $age : '') . "</td>";
        echo "<td>" . ($is_15_19 ? $age : '') . "</td>";
        echo "<td>" . ($is_20_59 ? $age : '') . "</td>";
        echo "<td>" . ($is_ge_60 ? $age : '') . "</td>";

        // Pregnant
        $preg_flag = is_truthy($row['pregnant']);
        $preg_10_14 = $preg_flag && $age !== null && $age >= 10 && $age <= 14;
        $preg_15_19 = $preg_flag && $age !== null && $age >= 15 && $age <= 19;
        $preg_20_49 = $preg_flag && $age !== null && $age >= 20 && $age <= 49;

        echo "<td>" . ($preg_10_14 ? '✓' : '') . "</td>";
        echo "<td>" . ($preg_15_19 ? '✓' : '') . "</td>";
        echo "<td>" . ($preg_20_49 ? '✓' : '') . "</td>";

        // Indigenous
        echo "<td>" . (is_truthy($row['indigenous_people']) ? '✓' : '') . "</td>";

        // Oral health
        echo "<td>" . htmlspecialchars($row['orally_fit_child']) . "</td>";
        echo "<td></td>"; // Orally fit after rehab

        // DMFT
        $decayed_present = (!empty($row['perm_decayed_teeth_d']) && $row['perm_decayed_teeth_d'] > 0);
        $missing_present = (!empty($row['perm_missing_teeth_m']) && $row['perm_missing_teeth_m'] > 0);
        $filled_present = (!empty($row['perm_filled_teeth_f']) && $row['perm_filled_teeth_f'] > 0);

        echo "<td>" . ($decayed_present ? '✓' : '') . "</td>";
        echo "<td>" . ($missing_present ? '✓' : '') . "</td>";
        echo "<td>" . ($filled_present ? '✓' : '') . "</td>";
        echo "</tr>";
    }

    echo "</table>";

    // Add summary
    echo "<div style='margin-top:20px;font-size:10px;'>";
    echo "<p>Total Records: " . $result->num_rows . "</p>";
    echo "<p>Generated by: MHO Dental Clinic System</p>";
    echo "</div>";

    echo "</body></html>";
}

function exportToExcelPart2($result, $year)
{
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment;filename="Target_Client_List_Part2_' . $year . '_' . date('Ymd_His') . '.xls"');
    header('Cache-Control: max-age=0');

    echo "<!DOCTYPE html>";
    echo "<html>";
    echo "<head>";
    echo "<meta charset=\"UTF-8\">";
    echo "<style>";
    echo "table { border-collapse: collapse; width: 100%; }";
    echo "th, td { border: 1px solid #000; padding: 3px; text-align: center; font-size: 10px; }";
    echo "th { background-color: #4F81BD; color: white; font-weight: bold; }";
    echo ".header-bg { background-color: #D9E1F2; }";
    echo ".name-cell { font-size: 9px; line-height: 1.2; }";
    echo "</style>";
    echo "</head>";
    echo "<body>";

    echo "<h2 style='text-align:center;'>TARGET CLIENT LIST FOR ORAL HEALTH CARE AND SERVICES - PART 2</h2>";
    echo "<h3 style='text-align:center;'>Year: " . $year . "</h3>";
    echo "<p style='text-align:center;font-size:10px;'>Generated on: " . date('F d, Y h:i A') . "</p>";

    echo "<table>";

    // Table headers
    echo "<tr class='header-bg'>";
    echo "<th rowspan='3'>No.</th>";
    echo "<th colspan='18'>Oral Health Services Provided<br><span style='font-size:9px;'>(Write data given)</span></th>";
    echo "<th colspan='7'>Provided with Basic Oral Health Care (BOHC)<br><span style='font-size:9px;'>(Input data given)</span></th>";
    echo "<th rowspan='3'>Remarks</th>";
    echo "</tr>";

    echo "<tr class='header-bg'>";
    for ($i = 0; $i < 18; $i++) echo "<th></th>";
    echo "<th>0-11 mos.</th>";
    echo "<th>1-4 y/o<br>(12-59 mos)</th>";
    echo "<th>5-9 y/o</th>";
    echo "<th>10-19 y/o</th>";
    echo "<th>20-59 y/o</th>";
    echo "<th>>60 y/o</th>";
    echo "<th>Pregnant</th>";
    echo "</tr>";

    echo "<tr class='header-bg'>";
    echo "<th>OE</th><th>IIOHC</th><th>AEBF</th><th>TFA</th><th>STB</th><th>OHE</th><th>E&CC</th><th>ART</th><th>OPS</th><th>PFS</th><th>TF</th><th>PF</th><th>GT</th><th>RP</th><th>RUT</th><th>Ref</th><th>TPEC</th><th>Dr</th>";
    echo "<th></th><th></th><th></th><th></th><th></th><th></th><th></th>";
    echo "</tr>";

    // Data rows
    $i = 1;
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td><span class='name-cell'>" . $i++ . "<br>" . htmlspecialchars($row['surname'] . ', ' . $row['firstname']) . "</span></td>";

        // Oral Health Services
        echo "<td>" . (!empty($row['oe']) ? htmlspecialchars($row['oe']) : '') . "</td>";
        echo "<td>" . (!empty($row['iiohc']) ? htmlspecialchars($row['iiohc']) : '') . "</td>";
        echo "<td>" . (!empty($row['aebf']) ? htmlspecialchars($row['aebf']) : '') . "</td>";
        echo "<td>" . (!empty($row['tfa']) ? htmlspecialchars($row['tfa']) : '') . "</td>";
        echo "<td>" . (!empty($row['stb']) ? htmlspecialchars($row['stb']) : '') . "</td>";
        echo "<td>" . (!empty($row['ohe']) ? htmlspecialchars($row['ohe']) : '') . "</td>";
        echo "<td>" . (!empty($row['ecc']) ? htmlspecialchars($row['ecc']) : '') . "</td>";
        echo "<td>" . (!empty($row['art']) ? htmlspecialchars($row['art']) : '') . "</td>";
        echo "<td>" . (!empty($row['ops']) ? htmlspecialchars($row['ops']) : '') . "</td>";
        echo "<td>" . (!empty($row['pfs']) ? htmlspecialchars($row['pfs']) : '') . "</td>";
        echo "<td>" . (!empty($row['tf']) ? htmlspecialchars($row['tf']) : '') . "</td>";
        echo "<td>" . (!empty($row['pf']) ? htmlspecialchars($row['pf']) : '') . "</td>";
        echo "<td>" . (!empty($row['gt']) ? htmlspecialchars($row['gt']) : '') . "</td>";
        echo "<td>" . (!empty($row['rp']) ? htmlspecialchars($row['rp']) : '') . "</td>";
        echo "<td>" . (!empty($row['rut']) ? htmlspecialchars($row['rut']) : '') . "</td>";
        echo "<td>" . (!empty($row['ref']) ? htmlspecialchars($row['ref']) : '') . "</td>";
        echo "<td>" . (!empty($row['tpec']) ? htmlspecialchars($row['tpec']) : '') . "</td>";
        echo "<td>" . (!empty($row['dr']) ? htmlspecialchars($row['dr']) : '') . "</td>";

        // BOHC
        echo "<td>" . (!empty($row['bohc_0_11']) ? htmlspecialchars($row['bohc_0_11']) : '') . "</td>";
        echo "<td>" . (!empty($row['bohc_1_4']) ? htmlspecialchars($row['bohc_1_4']) : '') . "</td>";
        echo "<td>" . (!empty($row['bohc_5_9']) ? htmlspecialchars($row['bohc_5_9']) : '') . "</td>";
        echo "<td>" . (!empty($row['bohc_10_19']) ? htmlspecialchars($row['bohc_10_19']) : '') . "</td>";
        echo "<td>" . (!empty($row['bohc_20_59']) ? htmlspecialchars($row['bohc_20_59']) : '') . "</td>";
        echo "<td>" . (!empty($row['bohc_60_plus']) ? htmlspecialchars($row['bohc_60_plus']) : '') . "</td>";
        echo "<td>" . (!empty($row['bohc_pregnant']) ? htmlspecialchars($row['bohc_pregnant']) : '') . "</td>";

        // Remarks
        echo "<td style='text-align:left;font-size:9px;'>" . (!empty($row['remarks']) ? htmlspecialchars($row['remarks']) : '') . "</td>";
        echo "</tr>";
    }

    echo "</table>";

    // Add summary
    echo "<div style='margin-top:20px;font-size:10px;'>";
    echo "<p>Total Records: " . $result->num_rows . "</p>";
    echo "<p>Generated by: MHO Dental Clinic System</p>";
    echo "</div>";

    echo "</body></html>";
}

function exportToCSVPart1($result, $year)
{
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment;filename="Target_Client_List_Part1_' . $year . '_' . date('Ymd_His') . '.csv"');

    $output = fopen('php://output', 'w');

    // Headers
    $headers = [
        'No.',
        'Date of Consultation',
        'Family Serial No.',
        'Name of Client',
        'Sex (M)',
        'Sex (F)',
        'Complete Address',
        'Date of Birth',
        '0-11 mos.',
        '1-4 y/o',
        '5-9 y/o',
        '10-14 y/o',
        '15-19 y/o',
        '20-59 y/o',
        '>=60 y/o',
        'Pregnant 10-14',
        'Pregnant 15-19',
        'Pregnant 20-49',
        'Indigenous People',
        'Orally Fit Upon Examination',
        'Orally Fit After Rehab',
        'Decayed Tooth',
        'Missing Tooth',
        'Filled Tooth'
    ];

    fputcsv($output, $headers);

    // Data rows
    $i = 1;
    while ($row = $result->fetch_assoc()) {
        $age = compute_age_from_dob($row['date_of_birth']);
        $months_old = compute_months_from_dob($row['date_of_birth']);

        $sex = strtoupper(trim($row['sex'] ?? ''));
        $is_male = in_array($sex, ['M', 'MALE']) ? '✓' : '';
        $is_female = in_array($sex, ['F', 'FEMALE']) ? '✓' : '';

        // Age buckets
        $is_0_11 = ($age === 0 && $months_old !== null && $months_old <= 11);
        $is_1_4 = ($age !== null && $age >= 1 && $age <= 4);
        $is_5_9 = ($age !== null && $age >= 5 && $age <= 9);
        $is_10_14 = ($age !== null && $age >= 10 && $age <= 14);
        $is_15_19 = ($age !== null && $age >= 15 && $age <= 19);
        $is_20_59 = ($age !== null && $age >= 20 && $age <= 59);
        $is_ge_60 = ($age !== null && $age >= 60);

        // Pregnant
        $preg_flag = is_truthy($row['pregnant']);
        $preg_10_14 = $preg_flag && $age !== null && $age >= 10 && $age <= 14;
        $preg_15_19 = $preg_flag && $age !== null && $age >= 15 && $age <= 19;
        $preg_20_49 = $preg_flag && $age !== null && $age >= 20 && $age <= 49;

        // DMFT
        $decayed_present = (!empty($row['perm_decayed_teeth_d']) && $row['perm_decayed_teeth_d'] > 0);
        $missing_present = (!empty($row['perm_missing_teeth_m']) && $row['perm_missing_teeth_m'] > 0);
        $filled_present = (!empty($row['perm_filled_teeth_f']) && $row['perm_filled_teeth_f'] > 0);

        $data = [
            $i++,
            !empty($row['created_at']) ? date('m/d/y', strtotime($row['created_at'])) : '',
            $row['patient_id'],
            $row['surname'] . ', ' . $row['firstname'] . ' ' . $row['middlename'],
            $is_male,
            $is_female,
            $row['address'],
            !empty($row['date_of_birth']) && $row['date_of_birth'] !== '0000-00-00' ? date('m/d/Y', strtotime($row['date_of_birth'])) : '',
            $is_0_11 ? ($months_old !== null ? $months_old . ' mo' : '') : '',
            $is_1_4 ? $age : '',
            $is_5_9 ? $age : '',
            $is_10_14 ? $age : '',
            $is_15_19 ? $age : '',
            $is_20_59 ? $age : '',
            $is_ge_60 ? $age : '',
            $preg_10_14 ? '✓' : '',
            $preg_15_19 ? '✓' : '',
            $preg_20_49 ? '✓' : '',
            is_truthy($row['indigenous_people']) ? '✓' : '',
            $row['orally_fit_child'],
            '',
            $decayed_present ? '✓' : '',
            $missing_present ? '✓' : '',
            $filled_present ? '✓' : ''
        ];

        fputcsv($output, $data);
    }

    fclose($output);
}

function exportToCSVPart2($result, $year)
{
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment;filename="Target_Client_List_Part2_' . $year . '_' . date('Ymd_His') . '.csv"');

    $output = fopen('php://output', 'w');

    // Headers
    $headers = [
        'No.',
        'Patient Name',
        'Patient ID',
        'OE',
        'IIOHC',
        'AEBF',
        'TFA',
        'STB',
        'OHE',
        'E&CC',
        'ART',
        'OPS',
        'PFS',
        'TF',
        'PF',
        'GT',
        'RP',
        'RUT',
        'Ref',
        'TPEC',
        'Dr',
        'BOHC 0-11',
        'BOHC 1-4',
        'BOHC 5-9',
        'BOHC 10-19',
        'BOHC 20-59',
        'BOHC 60+',
        'BOHC Pregnant',
        'Remarks'
    ];

    fputcsv($output, $headers);

    // Data rows
    $i = 1;
    while ($row = $result->fetch_assoc()) {
        $data = [
            $i++,
            $row['surname'] . ', ' . $row['firstname'],
            $row['patient_id'],
            $row['oe'] ?? '',
            $row['iiohc'] ?? '',
            $row['aebf'] ?? '',
            $row['tfa'] ?? '',
            $row['stb'] ?? '',
            $row['ohe'] ?? '',
            $row['ecc'] ?? '',
            $row['art'] ?? '',
            $row['ops'] ?? '',
            $row['pfs'] ?? '',
            $row['tf'] ?? '',
            $row['pf'] ?? '',
            $row['gt'] ?? '',
            $row['rp'] ?? '',
            $row['rut'] ?? '',
            $row['ref'] ?? '',
            $row['tpec'] ?? '',
            $row['dr'] ?? '',
            $row['bohc_0_11'] ?? '',
            $row['bohc_1_4'] ?? '',
            $row['bohc_5_9'] ?? '',
            $row['bohc_10_19'] ?? '',
            $row['bohc_20_59'] ?? '',
            $row['bohc_60_plus'] ?? '',
            $row['bohc_pregnant'] ?? '',
            $row['remarks'] ?? ''
        ];

        fputcsv($output, $data);
    }

    fclose($output);
}

function exportToHTMLPart1($result, $year)
{
    header('Content-Type: text/html');
    header('Content-Disposition: attachment;filename="Target_Client_List_Part1_' . $year . '_' . date('Ymd_His') . '.html"');

    echo "<!DOCTYPE html>";
    echo "<html>";
    echo "<head>";
    echo "<meta charset=\"UTF-8\">";
    echo "<title>Target Client List Part 1 - {$year}</title>";
    echo "<style>";
    echo "body { font-family: Arial, sans-serif; margin: 20px; }";
    echo "h1, h2, h3 { text-align: center; }";
    echo "table { border-collapse: collapse; width: 100%; margin: 20px 0; font-size: 12px; }";
    echo "th, td { border: 1px solid #000; padding: 5px; text-align: center; }";
    echo "th { background-color: #f2f2f2; font-weight: bold; }";
    echo ".summary { margin-top: 30px; font-size: 11px; }";
    echo ".footer { text-align: center; font-size: 10px; margin-top: 50px; }";
    echo "</style>";
    echo "</head>";
    echo "<body>";

    echo "<h1>TARGET CLIENT LIST FOR ORAL HEALTH CARE AND SERVICES</h1>";
    echo "<h2>Year: {$year}</h2>";
    echo "<h3>Part 1: Basic Information and Assessment</h3>";
    echo "<p style='text-align:center;'>Generated on: " . date('F d, Y h:i A') . "</p>";

    echo "<table>";
    // ... (same table structure as Excel export but simplified)
    // You can copy the table structure from exportToExcelPart1 function
    echo "</table>";

    echo "<div class='summary'>";
    echo "<p><strong>Summary:</strong></p>";
    echo "<p>Total Records: " . $result->num_rows . "</p>";
    echo "<p>Generated by: MHO Dental Clinic System</p>";
    echo "</div>";

    echo "<div class='footer'>";
    echo "<p>*** END OF REPORT ***</p>";
    echo "</div>";

    echo "</body></html>";
}

function exportToHTMLPart2($result, $year)
{
    header('Content-Type: text/html');
    header('Content-Disposition: attachment;filename="Target_Client_List_Part2_' . $year . '_' . date('Ymd_His') . '.html"');

    echo "<!DOCTYPE html>";
    echo "<html>";
    echo "<head>";
    echo "<meta charset=\"UTF-8\">";
    echo "<title>Target Client List Part 2 - {$year}</title>";
    echo "<style>";
    echo "body { font-family: Arial, sans-serif; margin: 20px; }";
    echo "h1, h2, h3 { text-align: center; }";
    echo "table { border-collapse: collapse; width: 100%; margin: 20px 0; font-size: 11px; }";
    echo "th, td { border: 1px solid #000; padding: 4px; text-align: center; }";
    echo "th { background-color: #f2f2f2; font-weight: bold; }";
    echo ".patient-name { font-size: 10px; }";
    echo ".summary { margin-top: 30px; font-size: 11px; }";
    echo ".footer { text-align: center; font-size: 10px; margin-top: 50px; }";
    echo "</style>";
    echo "</head>";
    echo "<body>";

    echo "<h1>TARGET CLIENT LIST FOR ORAL HEALTH CARE AND SERVICES</h1>";
    echo "<h2>Year: {$year}</h2>";
    echo "<h3>Part 2: Services Provided and BOHC</h3>";
    echo "<p style='text-align:center;'>Generated on: " . date('F d, Y h:i A') . "</p>";

    echo "<table>";
    // ... (same table structure as Excel export but simplified)
    echo "</table>";

    echo "<div class='summary'>";
    echo "<p><strong>Summary:</strong></p>";
    echo "<p>Total Records: " . $result->num_rows . "</p>";
    echo "<p>Generated by: MHO Dental Clinic System</p>";
    echo "</div>";

    echo "<div class='footer'>";
    echo "<p>*** END OF REPORT ***</p>";
    echo "</div>";

    echo "</body></html>";
}

$conn->close();
