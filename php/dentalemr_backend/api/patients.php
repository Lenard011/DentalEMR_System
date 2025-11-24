<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

include_once '../config/database.php';
include_once '../models/Patient.php';

$database = new Database();
$db = $database->getConnection();
$patient = new Patient($db);

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        if (isset($_GET['id'])) {
            // Get single patient
            $patient_data = $patient->read($_GET['id']);
            if ($patient_data) {
                echo json_encode(['success' => true, 'data' => $patient_data]);
            } else {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Patient not found']);
            }
        } else {
            // Get all patients with pagination and search
            $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
            $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
            $search = isset($_GET['search']) ? $_GET['search'] : '';
            
            $patients = $patient->getAll($page, $limit, $search);
            $total = $patient->getTotalCount($search);
            
            echo json_encode([
                'success' => true, 
                'data' => $patients,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'pages' => ceil($total / $limit)
                ]
            ]);
        }
        break;

    case 'POST':
        $data = json_decode(file_get_contents("php://input"), true);
        
        try {
            $patient_id = $patient->create($data);
            http_response_code(201);
            echo json_encode(['success' => true, 'message' => 'Patient created successfully', 'patient_id' => $patient_id]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'PUT':
        if (!isset($_GET['id'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Patient ID required']);
            break;
        }
        
        $data = json_decode(file_get_contents("php://input"), true);
        
        try {
            if ($patient->update($_GET['id'], $data)) {
                echo json_encode(['success' => true, 'message' => 'Patient updated successfully']);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Unable to update patient']);
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        break;
}
?>