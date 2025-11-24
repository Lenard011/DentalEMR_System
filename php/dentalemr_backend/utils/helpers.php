<?php
function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    return htmlspecialchars(strip_tags(trim($data)));
}

function validateDate($date, $format = 'Y-m-d') {
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}

function calculateAge($birthdate) {
    $birthDate = new DateTime($birthdate);
    $today = new DateTime();
    $age = $today->diff($birthDate);
    return $age->y;
}

function calculateMonthsOld($birthdate) {
    $birthDate = new DateTime($birthdate);
    $today = new DateTime();
    $interval = $today->diff($birthDate);
    return ($interval->y * 12) + $interval->m;
}

function generatePatientCode($patient_id) {
    return 'PAT-' . str_pad($patient_id, 6, '0', STR_PAD_LEFT);
}

function formatBloodPressure($systolic, $diastolic) {
    return $systolic . '/' . $diastolic;
}
?>