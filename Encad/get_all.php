<?php
// backend/Gestionnaire/get_all_encadrants.php

// Enable error reporting for debugging (REMOVE IN PRODUCTION)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start output buffering to catch any premature output
ob_start();

require_once '../../db_connect.php'; // Path to your database connection file
require_once '../../verify_token.php'; // Path to your JWT verification file

// CORS headers - crucial for Flutter Web
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS"); // Allow GET for fetching, OPTIONS for preflight
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Max-Age: 3600"); // Cache preflight requests for 1 hour
header("Access-Control-Allow-Credentials: true");

// Handle preflight OPTIONS requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    ob_end_clean(); // Clean any output buffer before sending headers
    http_response_code(200); // Send 200 OK for preflight
    exit(); // IMPORTANT: Exit immediately after sending preflight headers
}

header('Content-Type: application/json'); // Set content type to JSON for actual requests

$response = array(); // Initialize response array

// Verify JWT token and get user data
$userData = verifyJwtToken(); // $userData will contain ['userID', 'username', 'role'] if token is valid.

// Define allowed roles for accessing this endpoint
$allowedRoles = ['Gestionnaire', 'ChefCentreInformatique', 'Etudiant', 'Encadrant']; // Adjust roles as needed

if (!in_array($userData['role'], $allowedRoles)) {
    ob_end_clean(); // Clean any output buffer
    http_response_code(403); // Forbidden
    echo json_encode(['status' => 'error', 'message' => 'Access denied. You do not have permission to view encadrants.']);
    exit();
}

// Ensure $mysqli is connected before proceeding with database operations
if (!isset($mysqli) || $mysqli->connect_error) {
    ob_end_clean(); // Clean any output buffer
    http_response_code(500);
    $response = ['status' => 'error', 'message' => 'Database connection failed: ' . ($mysqli->connect_error ?? 'Unknown error')];
    echo json_encode($response);
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "GET") {
    // Prepare the SELECT statement
    $sql = "SELECT encadrantID, name, lastname, email FROM encadrantacademique ORDER BY lastname, name";
    $stmt = $mysqli->prepare($sql);

    if ($stmt) {
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            $encadrants = [];
            while ($row = $result->fetch_assoc()) {
                $encadrants[] = $row;
            }
            ob_end_clean();
            http_response_code(200);
            $response['status'] = 'success';
            $response['message'] = 'Encadrants fetched successfully.';
            $response['data'] = $encadrants;
        } else {
            ob_end_clean();
            http_response_code(500);
            $response['status'] = 'error';
            $response['message'] = 'Failed to execute statement: ' . $stmt->error;
        }
        $stmt->close();
    } else {
        ob_end_clean();
        http_response_code(500);
        $response['status'] = 'error';
        $response['message'] = 'Failed to prepare statement: ' . $mysqli->error;
    }
} else {
    ob_end_clean();
    http_response_code(405); // Method Not Allowed
    $response['status'] = 'error';
    $response['message'] = 'Invalid request method. Only GET requests are allowed.';
}

$mysqli->close(); // Close database connection
echo json_encode($response);
?>