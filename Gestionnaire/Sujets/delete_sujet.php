<?php

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

// Set content type to JSON for actual requests
header('Content-Type: application/json');

// --- From this point onwards, the script handles actual requests ---

// Include necessary files. (Moved here)
require_once '../../db_connect.php'; // Database connection
require_once '../../verify_token.php'; // Your JWT verification function

$response = array(); // Initialize response array

// Verify JWT token and get user data
try {
    $userData = verifyJwtToken(); // $userData will contain ['userID', 'username', 'role'] if token is valid.
} catch (Exception $e) {
    http_response_code(401); // Unauthorized
    $response = ['status' => 'error', 'message' => 'Authentication failed: ' . $e->getMessage()];
    echo json_encode($response);
    ob_end_flush(); // Output buffer and exit
    exit();
}

// Define the ONLY role allowed to delete encadrants.
$allowedRoles = ['Gestionnaire']; // Only Gestionnaire can delete encadrants.

// Check if the authenticated user has the allowed role.
if (!in_array($userData['role'], $allowedRoles)) {
    http_response_code(403); // Forbidden
    $response = ['status' => 'error', 'message' => 'Access denied. Only ' . implode(', ', $allowedRoles) . ' can delete encadrants.'];
    echo json_encode($response);
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

// Process Request to Delete Encadrant (only if authenticated and authorized)
if ($_SERVER["REQUEST_METHOD"] == "DELETE") {

    // Get the sujetID from the URL query parameters (e.g., delete_encadrant.php?sujetID=123)
    $sujetID = $_GET['sujetID'] ?? null;

    // Input Validation
    if (empty($sujetID) || !is_numeric($sujetID)) {
        http_response_code(400); // Bad Request
        $response['status'] = 'error';
        $response['message'] = 'Subject ID is required and must be a valid number for deletion.';
        echo json_encode($response);
        $mysqli->close(); // Close DB connection before exiting.
        ob_end_flush();
        exit();
    }

    $sujetID = (int)$sujetID; // Cast to integer to ensure correct type

    // Prepare SQL DELETE Statement
    // Note: The SQL targets 'sujetsstage' table. If this script is truly for 'encadrants' (users),
    // the table and column name should be adjusted (e.g., 'users' table, 'userID' or 'encadrantID').
    // Assuming 'sujetID' refers to a subject in 'sujetsstage' for now.
    $sql = "DELETE FROM sujetsstage WHERE sujetID = ? ";

    if ($stmt = $mysqli->prepare($sql)) {
        // Bind parameter: 'i' for integer (sujetID).
        $stmt->bind_param("i", $sujetID);

        try {
            if ($stmt->execute()) {
                if ($stmt->affected_rows > 0) {
                    $response['status'] = 'success';
                    $response['message'] = 'Subject deleted successfully.'; // Message adjusted for 'subject'
                    http_response_code(200); // OK
                } else {
                    // If 0 rows were affected, it means no subject with that sujetID was found.
                    http_response_code(404); // Not Found
                    $response['status'] = 'error';
                    $response['message'] = 'Subject not found or already deleted.'; // Message adjusted for 'subject'
                }
            } else {
                http_response_code(500); // Internal Server Error
                $response['status'] = 'error';
                $response['message'] = 'Database error during deletion: ' . $stmt->error;
            }
        } catch (mysqli_sql_exception $e) {
            http_response_code(500); // Internal Server Error
            $response['status'] = 'error';
            $response['message'] = 'Database error during deletion: ' . $e->getMessage();
        }

        $stmt->close();
    } else {
        http_response_code(500); // Internal Server Error
        $response['status'] = 'error';
        $response['message'] = 'Error preparing SQL statement for subject deletion: ' . $mysqli->error; // Message adjusted for 'subject'
    }
} else {
    http_response_code(405); // Method Not Allowed
    $response['status'] = 'error';
    $response['message'] = 'Invalid request method. Only DELETE requests are allowed.';
}

$mysqli->close(); // Close DB connection
echo json_encode($response);
ob_end_flush(); // Final flush of the output buffer
?>