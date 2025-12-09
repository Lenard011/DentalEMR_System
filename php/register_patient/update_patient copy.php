<?php
session_start();
header("Content-Type: application/json; charset=UTF-8");
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// REQUIRE userId parameter for each page
// Example usage: dashboard.php?uid=5
if (!isset($_GET['uid'])) {
    echo "<script>
        alert('Invalid session. Please log in again.');
        window.location.href = '/dentalemr_system/html/login/login.html';
    </script>";
    exit;
}

$userId = intval($_GET['uid']);

// CHECK IF THIS USER IS REALLY LOGGED IN
if (
    !isset($_SESSION['active_sessions']) ||
    !isset($_SESSION['active_sessions'][$userId])
) {
    echo "<script>
        alert('Please log in first.');
        window.location.href = '/dentalemr_system/html/login/login.html';
    </script>";
    exit;
}

// PER-USER INACTIVITY TIMEOUT
$inactiveLimit = 600; // 10 minutes

if (isset($_SESSION['active_sessions'][$userId]['last_activity'])) {
    $lastActivity = $_SESSION['active_sessions'][$userId]['last_activity'];

    if ((time() - $lastActivity) > $inactiveLimit) {

        // Log out ONLY this user (not everyone)
        unset($_SESSION['active_sessions'][$userId]);

        // If no one else is logged in, end session entirely
        if (empty($_SESSION['active_sessions'])) {
            session_unset();
            session_destroy();
        }

        echo "<script>
            alert('You have been logged out due to inactivity.');
            window.location.href = '/dentalemr_system/html/login/login.html';
        </script>";
        exit;
    }
}

// Update last activity timestamp
$_SESSION['active_sessions'][$userId]['last_activity'] = time();

// GET USER DATA FOR PAGE USE
$loggedUser = $_SESSION['active_sessions'][$userId];


$conn = new mysqli("localhost", "root", "", "dentalemr_system");
if ($conn->connect_error) die("DB connection failed: " . $conn->connect_error);
function addHistoryLog($conn, $tableName, $recordId, $action, $changedByType, $changedById, $oldValues = null, $newValues = null, $description = null)
{
    $sql = "INSERT INTO history_logs 
            (table_name, record_id, action, changed_by_type, changed_by_id, old_values, new_values, description, ip_address) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);

    $oldJSON = $oldValues ? json_encode($oldValues) : null;
    $newJSON = $newValues ? json_encode($newValues) : null;

    $ip = $_SERVER['REMOTE_ADDR'] ?? null;

    $stmt->bind_param(
        "sississss",
        $tableName,
        $recordId,
        $action,
        $changedByType,
        $changedById,
        $oldJSON,
        $newJSON,
        $description,
        $ip
    );

    return $stmt->execute();
}


if (isset($_POST['update_patient'])) {
    // Get POST data safely
    $patient_id = $_POST['patient_id'] ?? null;
    // Fetch old values *HERE*, after $patient_id exists
    $oldQuery = $conn->prepare("SELECT * FROM patients WHERE patient_id = ?");
    $oldQuery->bind_param("i", $patient_id);
    $oldQuery->execute();
    $oldData = $oldQuery->get_result()->fetch_assoc() ?: [];

    $firstname  = trim($_POST['firstname'] ?? '');
    $surname    = trim($_POST['surname'] ?? '');
    $middlename = trim($_POST['middlename'] ?? '');
    $dob        = $_POST['date_of_birth'] ?? null;
    $sex        = ucfirst(strtolower(trim($_POST['sex'] ?? '')));
    $age        = $_POST['age'] ?? null;
    $pob        = trim($_POST['place_of_birth'] ?? '');
    $occupation = trim($_POST['occupation'] ?? '');
    $address    = trim($_POST['address'] ?? '');
    $guardian   = trim($_POST['guardian'] ?? '');
    $pregnant   = strtolower(trim($_POST['pregnant'] ?? 'no'));

    // Validate patient ID
    if (!$patient_id) {
        header("Location: ../../html/viewrecord.html?error=nopid");
        exit();
    }

    //  FIXED: ensure 'sex' field is always valid ENUM value
    if (!in_array($sex, ['Male', 'Female'])) {
        $sex = 'Male'; // or use NULL if you want to allow no value
    }

    // Calculate age and months_old from DOB (optional safeguard)
    $months_old = null;
    if ($dob) {
        try {
            $dobDate = new DateTime($dob);
            $today = new DateTime();
            $interval = $dobDate->diff($today);
            $age = $interval->y;
            $months_old = $interval->y * 12 + $interval->m;
        } catch (Exception $e) {
            $age = 0;
            $months_old = 0;
        }
    }

    // Normalize pregnant field (only valid for females)
    if ($sex !== 'Female') {
        $pregnant = 'no';
    } else {
        if (!in_array($pregnant, ['yes', 'no'])) $pregnant = 'no';
    }

    // Prepare and execute the update
    $stmt = $conn->prepare("
        UPDATE patients
        SET firstname=?, surname=?, middlename=?, date_of_birth=?, sex=?, age=?, months_old=?,
            place_of_birth=?, occupation=?, address=?, guardian=?, pregnant=?, updated_at=NOW()
        WHERE patient_id=?
    ");

    if (!$stmt) die("Prepare failed: " . $conn->error);

    $stmt->bind_param(
        "sssssiisssssi",
        $firstname,
        $surname,
        $middlename,
        $dob,
        $sex,
        $age,
        $months_old,
        $pob,
        $occupation,
        $address,
        $guardian,
        $pregnant,
        $patient_id
    );

    if ($stmt->execute()) {

        $newData = [
            "firstname"     => $firstname,
            "surname"       => $surname,
            "middlename"    => $middlename,
            "date_of_birth" => $dob,
            "sex"           => $sex,
            "age"           => $age,
            "months_old"    => $months_old,
            "place_of_birth" => $pob,
            "occupation"    => $occupation,
            "address"       => $address,
            "guardian"      => $guardian,
            "pregnant"      => $pregnant
        ];

        $changedOld = [];
        $changedNew = [];
        $justification = [];

        foreach ($newData as $column => $newValue) {

            $oldValue = $oldData[$column] ?? null;

            // Normalize both sides
            $oldNorm = ($oldValue === "" ? null : $oldValue);
            $newNorm = ($newValue === "" ? null : $newValue);

            // Compare safely (handles numeric, NULL, and string)
            if ((string)$oldNorm !== (string)$newNorm) {

                $changedOld[$column] = $oldValue;
                $changedNew[$column] = $newValue;

                $nice = ucfirst(str_replace("_", " ", $column));

                $justification[] = "$nice changed from \"$oldValue\" to \"$newValue\"";
            }
        }

        // Prevent logging if nothing changed
        if (!empty($changedOld)) {
            addHistoryLog(
                $conn,
                "patients",
                $patient_id,
                "UPDATE",
                $loggedUser['type'],
                $loggedUser['id'],
                $changedOld,
                $changedNew,
                implode("; ", $justification)
            );
        } else {
            addHistoryLog(
                $conn,
                "patients",
                $patient_id,
                "update",
                $loggedUser['type'],
                $loggedUser['id'],
                $changedOld,
                $changedNew,
                "Updated: " . implode(", ", $justification)
            );
        }


        header("Location: ../../html/viewrecord.php?uid={$userId}&id=" . urlencode($patient_id) . "&updated=1");
        exit();
    } else {
        header("Location: ../../html/viewrecord.php?uid={$userId}&id=" . urlencode($patient_id) . "&updated=0&error=db");
        exit();
    }
}

$conn->close();
