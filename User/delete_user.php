<?php
require_once '../db_connect.php'; // Path to your database connection file
require_once '../verify_token.php'; // Path to your JWT verification file

// CORS headers - crucial for Flutter Web
header("Access-Control-Allow-Origin: *"); // Allow all origins for development
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization"); // Allow Content-Type and Authorization headers
header("Access-Control-Max-Age: 3600"); // Cache preflight requests for 1 hour
header("Access-Control-Allow-Credentials: true"); // Allow credentials (e.g., cookies, auth headers)

// Handle preflight OPTIONS requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

header('Content-Type: application/json'); // Set content type to JSON

$response = array(); // Initialize response array

// Verify JWT token and get user data
$userData = verifyJwtToken(); // Expected to return ['userID', 'username', 'role'] or exit if invalid

// Define allowed roles for accessing this endpoint
$allowedRoles = ['Gestionnaire', 'ChefCentreInformatique']; // Only these roles can delete users

if (!in_array($userData['role'], $allowedRoles)) {
    http_response_code(403); // Forbidden
    echo json_encode(['status' => 'error', 'message' => 'Access denied. You do not have permission to delete users.']);
    $mysqli->close();
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") { // Using POST as per your UserRepository
    $input = json_decode(file_get_contents('php://input'), true);

    $userID = $input['userID'] ?? null;

    if ($userID === null) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'User ID is required for deletion.']);
        $mysqli->close();
        exit();
    }

    // Prevent a user from deleting themselves (optional but recommended)
    if ($userData['userID'] == $userID) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'You cannot delete your own user account through this interface.']);
        $mysqli->close();
        exit();
    }

    $sql = "DELETE FROM users WHERE userID = ?";
    $stmt = $mysqli->prepare($sql);

    if ($stmt) {
        $stmt->bind_param("i", $userID); // 'i' for integer

        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                $response['status'] = 'success';
                $response['message'] = 'User deleted successfully.';
            } else {
                http_response_code(404); // Not Found
                $response['status'] = 'error';
                $response['message'] = 'User with provided ID not found.';
            }
        } else {
            http_response_code(500);
            $response['status'] = 'error';
            $response['message'] = 'Failed to delete user: ' . $stmt->error;
        }
        $stmt->close();
    } else {
        http_response_code(500);
        $response['status'] = 'error';
        $response['message'] = 'Failed to prepare statement: ' . $mysqli->error;
    }
} else {
    http_response_code(405); // Method Not Allowed
    $response['status'] = 'error';
    $response['message'] = 'Invalid request method. Only POST requests are allowed.';
}

$mysqli->close(); // Close database connection
echo json_encode($response);
?>