<?php
class Auth extends BaseModel {
    public function __construct($db) {
        parent::__construct($db);
    }

    public function loginDentist($email, $password) {
        $query = "SELECT * FROM dentist WHERE email = :email";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(":email", $email);
        $stmt->execute();

        if ($stmt->rowCount() == 1) {
            $dentist = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (password_verify($password, $dentist['password_hash'])) {
                $mfa_code = $this->generateMFACode($dentist['id'], 'Dentist');
                $this->logActivity($dentist['id'], $dentist['name'], 'Login', 'System', "MFA code sent to {$dentist['email']}");
                
                return [
                    'success' => true,
                    'user' => [
                        'id' => $dentist['id'],
                        'name' => $dentist['name'],
                        'email' => $dentist['email'],
                        'user_type' => 'Dentist'
                    ],
                    'requires_mfa' => true
                ];
            } else {
                $this->logActivity($dentist['id'], $dentist['name'], 'Failed Login', 'System', "Failed password attempt for user: {$dentist['name']}");
            }
        }
        
        $this->logActivity(0, 'Unknown User', 'Failed Login', 'System', "Failed login attempt with email: $email");
        return ['success' => false, 'message' => 'Invalid credentials'];
    }

    public function loginStaff($email, $password) {
        $query = "SELECT * FROM staff WHERE email = :email";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(":email", $email);
        $stmt->execute();

        if ($stmt->rowCount() == 1) {
            $staff = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (password_verify($password, $staff['password_hash'])) {
                $mfa_code = $this->generateMFACode($staff['id'], 'Staff');
                $this->logActivity($staff['id'], $staff['name'], 'Login', 'System', "MFA code sent to staff: {$staff['email']}");
                
                return [
                    'success' => true,
                    'user' => [
                        'id' => $staff['id'],
                        'name' => $staff['name'],
                        'email' => $staff['email'],
                        'user_type' => 'Staff'
                    ],
                    'requires_mfa' => true
                ];
            } else {
                $this->logActivity($staff['id'], $staff['name'], 'Failed Login', 'System', "Failed password attempt for staff: {$staff['name']}");
            }
        }
        
        $this->logActivity(0, 'Unknown User', 'Failed Login', 'System', "Failed login attempt with email: $email");
        return ['success' => false, 'message' => 'Invalid credentials'];
    }

    private function generateMFACode($user_id, $user_type) {
        $code = sprintf("%06d", mt_rand(1, 999999));
        $expires_at = date('Y-m-d H:i:s', strtotime('+10 minutes'));
        
        $query = "INSERT INTO mfa_codes SET user_id=:user_id, user_type=:user_type, code=:code, expires_at=:expires_at";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(":user_id", $user_id);
        $stmt->bindParam(":user_type", $user_type);
        $stmt->bindParam(":code", $code);
        $stmt->bindParam(":expires_at", $expires_at);
        $stmt->execute();
        
        return $code;
    }

    public function verifyMFA($user_id, $user_type, $code) {
        $query = "SELECT * FROM mfa_codes WHERE user_id=:user_id AND user_type=:user_type AND code=:code AND used=0 AND expires_at > NOW() ORDER BY created_at DESC LIMIT 1";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(":user_id", $user_id);
        $stmt->bindParam(":user_type", $user_type);
        $stmt->bindParam(":code", $code);
        $stmt->execute();
        
        if ($stmt->rowCount() == 1) {
            $mfa = $stmt->fetch(PDO::FETCH_ASSOC);
            $update_query = "UPDATE mfa_codes SET used=1 WHERE id=:id";
            $update_stmt = $this->db->prepare($update_query);
            $update_stmt->bindParam(":id", $mfa['id']);
            $update_stmt->execute();
            
            $this->createDailyVerification($user_id, $user_type);
            return true;
        }
        
        return false;
    }

    private function createDailyVerification($user_id, $user_type) {
        $today = date('Y-m-d');
        $current_time = date('Y-m-d H:i:s');
        
        $query = "INSERT INTO daily_verifications (user_id, user_type, verification_date, last_verification_time) 
                 VALUES (:user_id, :user_type, :verification_date, :last_verification_time) 
                 ON DUPLICATE KEY UPDATE last_verification_time=:last_verification_time";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(":user_id", $user_id);
        $stmt->bindParam(":user_type", $user_type);
        $stmt->bindParam(":verification_date", $today);
        $stmt->bindParam(":last_verification_time", $current_time);
        $stmt->execute();
    }

    public function checkDailyVerification($user_id, $user_type) {
        $today = date('Y-m-d');
        $query = "SELECT * FROM daily_verifications WHERE user_id=:user_id AND user_type=:user_type AND verification_date=:verification_date";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(":user_id", $user_id);
        $stmt->bindParam(":user_type", $user_type);
        $stmt->bindParam(":verification_date", $today);
        $stmt->execute();
        
        return $stmt->rowCount() > 0;
    }

    public function logout($user_id, $user_name, $user_type) {
        $this->logActivity($user_id, $user_name, 'Logout', 'System', "User logged out successfully");
        return ['success' => true, 'message' => 'Logged out successfully'];
    }
}
?>