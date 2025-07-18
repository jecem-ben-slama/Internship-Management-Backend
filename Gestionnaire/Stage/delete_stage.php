<?php
require_once '../db_connect.php';
require_once '../verify_token.php';

// CORS headers
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Credentials: true");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

header('Content-Type: application/json');

$response = array();

// Verify JWT token and get user data
$userData = verifyJwtToken();

// Only allow certain roles to delete internships
$allowedRoles = ['Gestionnaire'];
if (!in_array($userData['role'], $allowedRoles)) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Access denied.']);
    $mysqli->close();
    exit();
}

// Only allow DELETE requests
if ($_SERVER['REQUEST_METHOD'] == 'DELETE') {
    // Get stageID from request body (for DELETE, usually sent as JSON)
    $input = json_decode(file_get_contents('php://input'), true);
    $stageID = isset($input['stageID']) ? $input['stageID'] : '';

    // Fallback: check query string if not in JSON
    if (empty($stageID) && isset($_GET['stageID'])) {
        $stageID = $_GET['stageID'];
    }

    if (empty($stageID)) {
        http_response_code(400);
        $response['status'] = 'error';
        $response['message'] = 'Invalid stageID.';
    } else {
        $stmt = $mysqli->prepare("DELETE FROM stages WHERE stageID = ?");
        $stmt->bind_param("s", $stageID); // Use "s" for string

        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                $response['status'] = 'success';
                $response['message'] = 'Stage deleted successfully.';
            } else {
                http_response_code(404);
                $response['status'] = 'error';
                $response['message'] = 'Stage not found.';
            }
        } else {
            http_response_code(500);
            $response['status'] = 'error';
            $response['message'] = 'Database error: ' . $mysqli->error;
        }
        $stmt->close();
    }
} else {
    http_response_code(405);
    $response['status'] = 'error';
    $response['message'] = 'Invalid request method. Only DELETE is allowed.';
}

$mysqli->close();
echo json_encode($response);
?>