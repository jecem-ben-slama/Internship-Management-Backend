<?php
require_once '../../db_connect.php'; // Path to your database connection file
require_once '../../verify_token.php'; // Path to your JWT verification file

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
$allowedRoles = ['Gestionnaire', 'Encadrant']; // Adjust roles as needed for fetching students

if (!in_array($userData['role'], $allowedRoles)) {
    http_response_code(403); // Forbidden
    echo json_encode(['status' => 'error', 'message' => 'Access denied. You do not have permission to view students.']);
    $mysqli->close();
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "GET") {
    // SQL query to fetch all students
    // Adjust column names according to your 'etudiants' table schema
    $sql = "SELECT etudiantID, username, lastname, email, cin, niveauEtude, nomFaculte, cycle, specialite FROM etudiants";

    if ($result = $mysqli->query($sql)) {
        $etudiants = array();
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $etudiants[] = $row;
            }
            $response['status'] = 'success';
            $response['message'] = 'Students fetched successfully.';
            $response['data'] = $etudiants;
        } else {
            $response['status'] = 'success';
            $response['message'] = 'No students found.';
            $response['data'] = []; // Return empty array if no students
        }
        $result->free(); // Free result set
    } else {
        http_response_code(500); // Internal Server Error
        $response['status'] = 'error';
        $response['message'] = 'Database query failed: ' . $mysqli->error;
    }
} else {
    http_response_code(405); // Method Not Allowed
    $response['status'] = 'error';
    $response['message'] = 'Invalid request method. Only GET requests are allowed.';
}

$mysqli->close(); // Close database connection
echo json_encode($response);
?>