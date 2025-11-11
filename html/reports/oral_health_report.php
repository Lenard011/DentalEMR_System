<?php
require_once __DIR__ . './conns.php';
header('Content-Type: text/html; charset=utf-8');

try {
    // Query to fetch data from multiple related tables
    $query = "
        SELECT 
            p.patient_id,
            p.surname,
            p.firstname,
            p.middlename,
            p.sex,
            p.address,
            p.date_of_birth,
            p.age,
            p.pregnant,
            po.indigenous_people,
            oh.orally_fit_child,
            oh.perm_decayed_teeth_d,
            oh.perm_missing_teeth_m,
            oh.perm_filled_teeth_f,
            DATE(p.created_at) AS created_at
        FROM patients p
        LEFT JOIN patient_other_info po ON p.patient_id = po.patient_id
        LEFT JOIN oral_health_condition oh ON p.patient_id = oh.patient_id
        ORDER BY p.created_at ASC
    ";
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    die("<p style='color:red;'>Error: " . htmlspecialchars($e->getMessage()) . "</p>");
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Oral Health Report</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-50 dark:bg-gray-900 p-6">

    <form action="#">
        <div class="grid gap-2 mb-4 mt-5">
            <div class="overflow-x-auto">
                <table class="min-w-[1600px] w-full text-xs text-gray-600 dark:text-gray-300 border border-gray-300 border-collapse">
                    <!-- === COLUMN WIDTHS === -->
                    <colgroup>
                        <col style="width: 50px;">
                        <col style="width: 120px;">
                        <col style="width: 100px;">
                        <col style="width: 150px;">
                        <col style="width: 40px;">
                        <col style="width: 40px;">
                        <col style="width: 180px;">
                        <col style="width: 100px;">
                        <col style="width: 90px;">
                        <col style="width: 100px;">
                        <col style="width: 90px;">
                        <col style="width: 100px;">
                        <col style="width: 100px;">
                        <col style="width: 100px;">
                        <col style="width: 100px;">
                        <col style="width: 100px;">
                        <col style="width: 100px;">
                        <col style="width: 100px;">
                        <col style="width: 100px;">
                        <col style="width: 100px;">
                        <col style="width: 90px;">
                        <col style="width: 90px;">
                        <col style="width: 90px;">
                    </colgroup>

                    <!-- === TABLE HEADER === -->
                    <thead class="text-xs align-top text-gray-700 bg-gray-50 dark:bg-gray-700 dark:text-gray-300">
                        <tr>
                            <th rowspan="3" class="border px-1 py-2">No.</th>
                            <th rowspan="3" class="border px-1 py-2">Date of Consultation<br><span class="text-[10px]">(mm/dd/yy)</span></th>
                            <th rowspan="3" class="border px-1 py-2">Family Serial No.</th>
                            <th rowspan="3" class="border px-1 py-2">Name of Client<br><span class="text-[10px]">(LN, FN, MI)</span></th>
                            <th colspan="2" class="border px-1 py-0">Sex</th>
                            <th rowspan="3" class="border px-1 py-2">Complete Address</th>
                            <th rowspan="3" class="border px-1 py-2">Date of Birth<br><span class="text-[10px]">(mm/dd/yy)</span></th>
                            <th colspan="10" class="border px-1 py-2">Age / Risk Group</th>
                            <th rowspan="3" class="border px-1 py-2">Indigenous People<br><span class="font-bold text-[11px]">(✓)</span></th>
                            <th colspan="2" class="border px-1 py-2">Oral Health Status<br>12–59 mos</th>
                            <th colspan="3" class="border px-1 py-2">DMFT (>5 y/o)</th>
                        </tr>

                        <tr>
                            <th class="border px-1 py-2">M</th>
                            <th class="border px-1 py-2">F</th>
                            <th class="border px-1 py-2">0–11 mos.</th>
                            <th class="border px-1 py-2">1–4 y/o</th>
                            <th class="border px-1 py-2">5–9 y/o</th>
                            <th class="border px-1 py-2">10–14 y/o</th>
                            <th class="border px-1 py-2">15–19 y/o</th>
                            <th class="border px-1 py-2">20–59 y/o</th>
                            <th class="border px-1 py-2">≥ 60 y/o</th>
                            <th colspan="3" class="border px-1 py-2">Pregnant</th>
                            <th class="border px-1 py-2">Orally Fit<br>Upon Exam</th>
                            <th class="border px-1 py-2">Orally Fit<br>After Rehab</th>
                            <th class="border px-1 py-2">Decayed</th>
                            <th class="border px-1 py-2">Missing</th>
                            <th class="border px-1 py-2">Filled</th>
                        </tr>

                        <tr>
                            <th colspan="11" class="border-none"></th>
                            <th class="border px-1 py-1 text-[10px]">10–14 y/o</th>
                            <th class="border px-1 py-1 text-[10px]">15–19 y/o</th>
                            <th class="border px-1 py-1 text-[10px]">20–49 y/o</th>
                            <th colspan="5" class="border-none"></th>
                        </tr>
                    </thead>

                    <!-- === TABLE BODY === -->
                    <tbody>
                        <?php
                        $i = 1;
                        foreach ($patients as $p):
                            $fullname = htmlspecialchars($p['surname'] . ', ' . $p['firstname'] . ' ' . substr($p['middlename'], 0, 1) . '.');
                            $dob = date("m/d/Y", strtotime($p['date_of_birth']));
                            $created = date("m/d/Y", strtotime($p['created_at']));
                            $isPregnant = (strtolower(trim($p['pregnant'])) === 'yes');
                        ?>
                            <tr class="text-center">
                                <td class="border px-1 py-2"><?= $i++; ?></td>
                                <td class="border px-1 py-2"><?= $created; ?></td>
                                <td class="border px-1 py-2"><?= $p['patient_id']; ?></td>
                                <td class="border px-1 py-2"><?= $fullname; ?></td>
                                <td class="border px-1 py-2"><?= ($p['sex'] === 'Male') ? '✓' : ''; ?></td>
                                <td class="border px-1 py-2"><?= ($p['sex'] === 'Female') ? '✓' : ''; ?></td>
                                <td class="border px-1 py-2"><?= htmlspecialchars($p['address']); ?></td>
                                <td class="border px-1 py-2"><?= $dob; ?></td>

                                <!-- Age / Risk Group -->
                                <?php
                                $age = intval($p['age']);
                                $ageCols = [
                                    '0-11mos' => ($age < 1) ? '✓' : '',
                                    '1-4' => ($age >= 1 && $age <= 4) ? '✓' : '',
                                    '5-9' => ($age >= 5 && $age <= 9) ? '✓' : '',
                                    '10-14' => ($age >= 10 && $age <= 14) ? '✓' : '',
                                    '15-19' => ($age >= 15 && $age <= 19) ? '✓' : '',
                                    '20-59' => ($age >= 20 && $age <= 59) ? '✓' : '',
                                    '60+' => ($age >= 60) ? '✓' : '',
                                ];
                                foreach ($ageCols as $v) echo "<td class='border px-1 py-2'>$v</td>";
                                ?>

                                <!-- Pregnant -->
                                <td class="border px-1 py-2"><?= ($isPregnant && $age >= 10 && $age <= 14) ? '✓' : ''; ?></td>
                                <td class="border px-1 py-2"><?= ($isPregnant && $age >= 15 && $age <= 19) ? '✓' : ''; ?></td>
                                <td class="border px-1 py-2"><?= ($isPregnant && $age >= 20 && $age <= 49) ? '✓' : ''; ?></td>

                                <!-- Indigenous -->
                                <td class="border px-1 py-2"><?= ($p['indigenous_people']) ? '✓' : ''; ?></td>

                                <!-- Oral Health -->
                                <td class="border px-1 py-2"><?= ($p['orally_fit_child'] === 'yes') ? '✓' : ''; ?></td>
                                <td class="border px-1 py-2"><?= ($p['orally_fit_child'] === 'yes') ? '✓' : ''; ?></td>

                                <!-- DMFT -->
                                <td class="border px-1 py-2"><?= ($p['perm_decayed_teeth_d'] > 0) ? '✓' : ''; ?></td>
                                <td class="border px-1 py-2"><?= ($p['perm_missing_teeth_m'] > 0) ? '✓' : ''; ?></td>
                                <td class="border px-1 py-2"><?= ($p['perm_filled_teeth_f'] > 0) ? '✓' : ''; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </form>

</body>

</html>