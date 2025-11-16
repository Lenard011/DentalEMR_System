<?php
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
