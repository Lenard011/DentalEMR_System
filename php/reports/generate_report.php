<?php
session_start();
date_default_timezone_set('Asia/Manila');

// REQUIRE userId parameter
if (!isset($_GET['uid'])) {
    echo json_encode(['error' => 'Invalid session']);
    exit;
}

$userId = intval($_GET['uid']);

// CHECK IF THIS USER IS REALLY LOGGED IN
if (!isset($_SESSION['active_sessions']) || !isset($_SESSION['active_sessions'][$userId])) {
    echo json_encode(['error' => 'Please log in first']);
    exit;
}

// Include the report generation logic from the main file
require_once('../../oralhygienefindings.php');

// But we need to modify it to return JSON instead of HTML
// Since the main file has a lot of logic, we'll create a simplified version here
// Actually, let's just include the logic but capture the output

ob_start(); // Start output buffering

// Copy the essential logic from oralhygienefindings.php but return JSON
// For simplicity, I'll show a different approach

// Get parameters
$reportType = isset($_GET['report_type']) ? $_GET['report_type'] : 'main';
$periodType = isset($_GET['period']) ? $_GET['period'] : 'monthly';
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
$month = isset($_GET['month']) ? intval($_GET['month']) : date('n');
$quarter = isset($_GET['quarter']) ? intval($_GET['quarter']) : 1;
$semiAnnual = isset($_GET['semi_annual']) ? intval($_GET['semi_annual']) : 1;
$fhisPeriod = isset($_GET['fhis_period']) ? $_GET['fhis_period'] : 'quarterly';
$fhisQuarter = isset($_GET['fhis_quarter']) ? intval($_GET['fhis_quarter']) : 1;
$fhisSemiAnnual = isset($_GET['fhis_semi_annual']) ? intval($_GET['fhis_semi_annual']) : 1;

// For now, we'll return a simplified response
// In a real implementation, you would generate the full HTML table here

$response = [
    'status' => 'success',
    'report_type' => $reportType,
    'period' => $periodType,
    'year' => $year,
    'html' => generateTableHTML($reportType, $periodType, $year, $month, $quarter, $semiAnnual, $fhisPeriod, $fhisQuarter, $fhisSemiAnnual)
];

echo json_encode($response);

// Function to generate table HTML (simplified - you need to implement the full logic)
function generateTableHTML($reportType, $periodType, $year, $month, $quarter, $semiAnnual, $fhisPeriod, $fhisQuarter, $fhisSemiAnnual)
{
    // This is a simplified version - you need to implement the full table generation logic here
    // Based on your oralhygienefindings.php logic

    if ($reportType === 'fhis') {
        return generateFHISTable($fhisPeriod, $year, $fhisQuarter, $fhisSemiAnnual);
    } else {
        return generateMainTable($periodType, $year, $month, $quarter, $semiAnnual);
    }
}

function generateMainTable($periodType, $year, $month, $quarter, $semiAnnual)
{
    // Implement your main table generation logic here
    // This should return the HTML table exactly as in your main file
    return "<table class='age-table border-collapse border border-gray-400 w-full bg-white'>...</table>";
}

function generateFHISTable($fhisPeriod, $year, $fhisQuarter, $fhisSemiAnnual)
{
    // Implement your FHSIS table generation logic here
    return "<table class='fhis-table border-collapse border border-gray-400 w-full bg-white'>...</table>";
}
