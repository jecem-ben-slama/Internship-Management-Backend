<?php
require_once '../../db_connect.php'; // Path to your database connection file
require_once '../../verify_token.php'; // Path to your JWT verification file

// CORS headers - crucial for Flutter Web
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Max-Age: 3600");

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
    // SQL query to fetch all subjects
    $sql = "SELECT sujetID, titre, description FROM sujetsstage ORDER BY sujetID DESC";

    if ($result = $mysqli->query($sql)) {
        $sujets = array();
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
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