<?php
$host = "localhost";
$dbUser = "u401132124_dentalclinic";
$dbPass = "Mho_DentalClinic1st";
$dbName = "u401132124_mho_dentalemr";

$conn = new mysqli($host, $dbUser, $dbPass, $dbName);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "<h2>Checking activity_logs table structure:</h2>";
$result = $conn->query("DESCRIBE activity_logs");
if ($result) {
    echo "<table border='1'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['Field'] . "</td>";
        echo "<td>" . $row['Type'] . "</td>";
        echo "<td>" . $row['Null'] . "</td>";
        echo "<td>" . $row['Key'] . "</td>";
        echo "<td>" . ($row['Default'] ?: 'NULL') . "</td>";
        echo "<td>" . $row['Extra'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "Error describing table: " . $conn->error;
}

echo "<h2>First 5 activity logs:</h2>";
$result = $conn->query("SELECT * FROM activity_logs LIMIT 5");
if ($result) {
    echo "<table border='1'>";
    if ($result->num_rows > 0) {
        // Header
        $row = $result->fetch_assoc();
        echo "<tr>";
        foreach (array_keys($row) as $key) {
            echo "<th>$key</th>";
        }
        echo "</tr>";

        // First row
        echo "<tr>";
        foreach ($row as $value) {
            echo "<td>" . htmlspecialchars($value) . "</td>";
        }
        echo "</tr>";

        // Rest of rows
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            foreach ($row as $value) {
                echo "<td>" . htmlspecialchars($value) . "</td>";
            }
            echo "</tr>";
        }
    }
    echo "</table>";
} else {
    echo "Error fetching logs: " . $conn->error;
}

echo "<h2>Checking history_logs table structure:</h2>";
$result = $conn->query("DESCRIBE history_logs");
if ($result) {
    echo "<table border='1'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['Field'] . "</td>";
        echo "<td>" . $row['Type'] . "</td>";
        echo "<td>" . $row['Null'] . "</td>";
        echo "<td>" . $row['Key'] . "</td>";
        echo "<td>" . ($row['Default'] ?: 'NULL') . "</td>";
        echo "<td>" . $row['Extra'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "Error describing table: " . $conn->error;
}

$conn->close();
