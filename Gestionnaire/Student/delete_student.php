<?php

require_once '../../db_connect.php'; // Path to your database connection file (make sure $mysqli is initialized here)
require_once '../../verify_token.php'; // Path to your JWT verification file
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Ensure no output before headers
ob_start(); 
// CORS headers - crucial for Flutter Web
header("Access-Control-Allow-Origin: *"); // Allow all origins for development. In production, specify your app's origin: e.g., "http://localhost:60847"
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS"); // Crucial: Add DELETE and OPTIONS methods
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With"); // Allow Content-Type, Authorization, and X-Requested-With headers
header("Access-Control-Max-Age: 3600"); // Cache preflight requests for 1 hour
header("Access-Control-Allow-Credentials: true"); // Allow credentials (e.g., cookies, auth headers)

// Handle preflight OPTIONS requests - THIS IS THE CRITICAL PART FOR CORS
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200); // Send 200 OK for preflight
    ob_end_clean(); // Clean any accidental output buffer
    exit(); // IMPORTANT: Exit immediately after sending preflight headers
}

header('Content-Type: application/json'); // Set content type to JSON for actual requests

$response = array(); // Initialize response array

// Verify JWT token and get user data
$userData = verifyJwtToken(); // $userData will contain ['userID', 'username', 'role'] if token is valid.

// Define the ONLY role allowed to delete students.
$allowedRoles = ['Gestionnaire'];

if (!in_array($userData['role'], $allowedRoles)) {
    http_response_code(403); // Forbidden
    echo json_encode(['status' => 'error', 'message' => 'Access denied. Only ' . implode(', ', $allowedRoles) . ' can delete students.']);
    ob_end_flush(); // Output buffer and exit
    exit();
}

// Ensure $mysqli is connected before proceeding with database operations
if (!isset($mysqli) || $mysqli->connect_error) {
    http_response_code(500);
    $response = ['status' => 'error', 'message' => 'Database connection failed: ' . ($mysqli->connect_error ?? 'Unknown error')];
    echo json_encode($response);
    ob_end_flush(); // Output buffer and exit
    exit();
}

// Expecting a DELETE request, but as per your previous interactions, Flutter often sends POST for various operations.
// I will set this to handle DELETE, but if your Flutter client sends POST for deletion, you will need to change this line.
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $input = json_decode(file_get_contents('php://input'), true);

    if (json_last_error() !== JSON_ERROR_NONE || !is_array($input) || empty($input)) {
        http_response_code(400);
        $response = ['status' => 'error', 'message' => 'Invalid or empty JSON body.'];
        echo json_encode($response);
        $mysqli->close();
        ob_end_flush();
        exit();
    }

    // Get the student ID from the JSON body
    $etudiantID = $input['etudiantID'] ?? null; // Assuming the ID field is named 'etudiantID' in the JSON

    if (empty($etudiantID) || !is_numeric($etudiantID)) {
        http_response_code(400);
        $response = ['status' => 'error', 'message' => 'Student ID (etudiantID) is required in the JSON body and must be a valid number.'];
        echo json_encode($response);
        $mysqli->close();
        ob_end_flush();
        exit();
    }
    $etudiantID = (int)$etudiantID; // Cast to integer for security and type consistency

    // SQL to delete the student
    $sql = "DELETE FROM etudiants WHERE etudiantID = ?";

    if ($stmt = $mysqli->prepare($sql)) {
        $stmt->bind_param("i", $etudiantID);

        try {
            if ($stmt->execute()) {
                if ($stmt->affected_rows > 0) {
                    $response['status'] = 'success';
                    $response['message'] = 'Student deleted successfully.';
                } else {
                    http_response_code(404); // Not Found
                    $response['status'] = 'error';
                    $response['message'] = 'Student not found or already deleted.';
                }
            } else {
                http_response_code(500);
                $response['status'] = 'error';
                $response['message'] = 'Database error during deletion: ' . $stmt->error;
            }
        } catch (mysqli_sql_exception $e) {
            http_response_code(500);
            $response['status'] = 'error';
            error_log("MySQLi Error (Delete Student): Code " . $e->getCode() . " - Message: " . $e->getMessage()); // Log for debugging
            $response['message'] = 'Database error during deletion: An unexpected error occurred.';
            // For development, you might include: $response['debug_info'] = $e->getMessage();
        }

        $stmt->close();
    } else {
        http_response_code(500);
        $response['status'] = 'error';
        $response['message'] = 'Error preparing SQL statement for student deletion: ' . $mysqli->error;
    }
} else {
    http_response_code(405); // Method Not Allowed
    $response['status'] = 'error';
    // This message clearly states what the script expects.
    $response['message'] = 'Invalid request method. Only DELETE requests are allowed.';
}

$mysqli->close(); // Close DB connection
echo json_encode($response);
ob_end_flush(); // Final flush of the output buffer
?>