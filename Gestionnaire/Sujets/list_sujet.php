<?php
require_once '../../db_connect.php'; // Path to your database connection file
require_once '../../verify_token.php'; // Path to your JWT verification file

// CORS headers - crucial for Flutter Web
header("Access-Control-Allow-Origin: *"); // Allow all origins for development. In production, specify your app's origin: e.g., "http://localhost:60847"
header("Access-Control-Allow-Methods: GET, POST, OPTIONS"); // Crucial: Add POST and OPTIONS methods
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With"); // Allow Content-Type, Authorization, and X-Requested-With headers
header("Access-Control-Max-Age: 3600"); // Cache preflight requests for 1 hour
header("Access-Control-Allow-Credentials: true");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

header('Content-Type: application/json');

$response = array();

// Verify JWT token and get user data
$userData = verifyJwtToken(); // Should return ['userID', 'username', 'role']

// Define allowed roles for accessing this endpoint
$allowedRoles = ['Gestionnaire', 'Encadrant'];

if (!in_array($userData['role'], $allowedRoles)) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Access denied. You do not have permission to view subjects.']);
    $mysqli->close();
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "GET") {
    // SQL query to fetch all subjects, NOW INCLUDING pdfUrl
    $sql = "SELECT sujetID, titre, description, pdfUrl FROM sujetsstage ORDER BY sujetID DESC";

    if ($result = $mysqli->query($sql)) {
        $sujets = array();
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                // Ensure pdfUrl is always treated as a string, even if NULL in DB
                // PHP's fetch_assoc() will return NULL if the DB value is NULL
                $row['pdfUrl'] = $row['pdfUrl'] ?? null;
                $sujets[] = $row;
            }
            $response['status'] = 'success';
            $response['message'] = 'Subjects fetched successfully.';
            $response['data'] = $sujets;
        } else {
            $response['status'] = 'success';
            $response['message'] = 'No subjects found.';
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