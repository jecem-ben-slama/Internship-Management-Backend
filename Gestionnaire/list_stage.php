<?php
require_once '../db_connect.php'; // Path to your database connection file
require_once '../verify_token.php'; // Path to your JWT verification file

// CORS headers - crucial for Flutter Web
header("Access-Control-Allow-Origin: *"); // Allow all origins for development
header("Access-Control-Allow-Methods: GET, OPTIONS"); // Allow GET and OPTIONS methods
header("Access-Control-Allow-Headers: Content-Type, Authorization"); // Allow Content-Type and Authorization headers
header("Access-Control-Max-Age: 3600"); // Cache preflight requests for 1 hour

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
$allowedRoles = ['Gestionnaire', 'Encadrant']; // Adjust roles as needed for fetching stages

if (!in_array($userData['role'], $allowedRoles)) {
    http_response_code(403); // Forbidden
    echo json_encode(['status' => 'error', 'message' => 'Access denied. You do not have permission to view stages.']);
    $mysqli->close();
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "GET") {
    $sql = "SELECT stageID, etudiantID, sujetID, typeStage, dateDebut, dateFin, statut, estRemunere, montantRemuneration, encadrantProID, chefCentreValidationID FROM stages";

    if ($result = $mysqli->query($sql)) {
        $stages = array();
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $stages[] = $row;
            }
            $response['status'] = 'success';
            $response['message'] = 'Stages fetched successfully.';
            $response['data'] = $stages;
        } else {
            $response['status'] = 'success';
            $response['message'] = 'No stages found.';
            $response['data'] = [];
        }
        $result->free(); 
    } else {
        http_response_code(500); 
        $response['status'] = 'error';
        $response['message'] = 'Database query failed: ' . $mysqli->error;
    }
} else {
    http_response_code(405); 
    $response['status'] = 'error';
    $response['message'] = 'Invalid request method. Only GET requests are allowed.';
}

$mysqli->close(); 
echo json_encode($response);
?>