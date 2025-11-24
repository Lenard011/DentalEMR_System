<?php
class BaseModel {
    protected $db;
    protected $table;

    public function __construct($db) {
        $this->db = $db;
    }

    protected function logActivity($user_id, $user_name, $action, $target, $details = null) {
        try {
            $query = "INSERT INTO activity_logs SET user_id=:user_id, user_name=:user_name, action=:action, target=:target, details=:details, ip_address=:ip_address, user_agent=:user_agent";
            
            $stmt = $this->db->prepare($query);
            $ip_address = $_SERVER['REMOTE_ADDR'] ?? '::1';
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
            
            $stmt->bindParam(":user_id", $user_id);
            $stmt->bindParam(":user_name", $user_name);
            $stmt->bindParam(":action", $action);
            $stmt->bindParam(":target", $target);
            $stmt->bindParam(":details", $details);
            $stmt->bindParam(":ip_address", $ip_address);
            $stmt->bindParam(":user_agent", $user_agent);
            
            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Activity log error: " . $e->getMessage());
            return false;
        }
    }

    protected function validateRequiredFields($data, $requiredFields) {
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new Exception("Required field missing: " . $field);
            }
        }
    }
}
?>