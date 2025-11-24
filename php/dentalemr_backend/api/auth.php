<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

include_once '../config/database.php';
include_once '../models/Auth.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

$data = json_decode(file_get_contents("php://input"), true);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $data['action'] ?? '';
    
    switch ($action) {
        case 'login':
            $user_type = $data['user_type'] ?? 'dentist';
            $email = $data['email'] ?? '';
            $password = $data['password'] ?? '';
            
            if (empty($email) || empty($password)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Email and password required']);
                break;
            }
            
            if ($user_type == 'dentist') {
                $result = $auth->loginDentist($email, $password);
            } else {
                $result = $auth->loginStaff($email, $password);
            }
            
            echo json_encode($result);
            break;
            
        case 'verify_mfa':
            $user_id = $data['user_id'] ?? '';
            $user_type = $data['user_type'] ?? '';
            $code = $data['code'] ?? '';
            
            if (empty($user_id) || empty($user_type) || empty($code)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'All fields required']);
                break;
            }
            
            if ($auth->verifyMFA($user_id, $user_type, $code)) {
                echo json_encode(['success' => true, 'message' => 'Verification successful']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid verification code']);
            }
            break;
            
        case 'logout':
            $user_id = $data['user_id'] ?? '';
            $user_name = $data['user_name'] ?? '';
            $user_type = $data['user_type'] ?? '';
            
            $result = $auth->logout($user_id, $user_name, $user_type);
            echo json_encode($result);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}
?>