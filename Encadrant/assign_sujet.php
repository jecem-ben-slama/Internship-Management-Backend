<?php
ob_start(); // Start output buffering at the very beginning

require_once '../db_connect.php'; // Path to your database connection file (make sure $mysqli is initialized here)
require_once '../verify_token.php'; // Path to your JWT verification file

// CORS headers
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS"); // Allow POST method for this endpoint
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Credentials: true");

// Handle preflight OPTIONS requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    ob_end_clean(); // Clean any buffer output before exiting
    exit(); // Terminate script after sending preflight headers
}

header('Content-Type: application/json'); // Set content type to JSON
$response = array(); // Initialize response array

// Verify JWT token and get user data
$userData = verifyJwtToken(); // Expected to return ['userID', 'username', 'role'] or exit if invalid

// Define allowed roles for accessing this endpoint
$allowedRoles = ['Encadrant', 'Admin']; // Admins might also need this capability

if (!in_array($userData['role'], $allowedRoles)) {
    http_response_code(403); // Forbidden
    $response = ['status' => 'error', 'message' => 'Access denied. Only ' . implode(', ', $allowedRoles) . ' can assign subjects.'];
    echo json_encode($response);
    exit(); // Exit after sending response
}

// Get the logged-in Encadrant's ID from the token
$encadrantID = $userData['userID'];

// Ensure $mysqli is connected
if (!isset($mysqli) || $mysqli->connect_error) {
    http_response_code(500); // Internal Server Error
    $response = ['status' => 'error', 'message' => 'Database connection failed: ' . ($mysqli->connect_error ?? 'Unknown error')];
    echo json_encode($response);
    exit(); // Exit after sending response
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get raw POST data
    $input = file_get_contents('php://input');
    $data = json_decode($input, true); // Decode JSON into associative array

    // Validate input data
    $stageID = filter_var($data['stageID'] ?? null, FILTER_VALIDATE_INT);
    $sujetID = filter_var($data['sujetID'] ?? null, FILTER_VALIDATE_INT);

    if ($stageID === false || $stageID === null || $sujetID === false || $sujetID === null) {
        http_response_code(400); // Bad Request
        $response = ['status' => 'error', 'message' => 'Invalid or missing stageID or sujetID.'];
        echo json_encode($response);
        exit();
    }

    try {
        // SQL to update the internship:
        // - Set sujetID
        // - Set encadrantProID (to the logged-in encadrant, taking ownership)
        // - Set statut to 'en cours'
        // - WHERE clause ensures:
        //   1. The stageID matches
        //   2. The internship is either NOT assigned to a professional supervisor (NULL)
        //      OR it's already assigned to the current logged-in encadrant.
        //      This prevents an encadrant from assigning a subject to another encadrant's internship.
        $sql = "
            UPDATE stages
            SET
                sujetID = ?,
                encadrantProID = ?,
                statut = 'In Progress'
            WHERE
                stageID = ?
                AND (encadrantProID IS NULL OR encadrantProID = ?);
        ";

        $stmt = $mysqli->prepare($sql);
        if (!$stmt) {
            error_log("SQL Prepare Error (Assign Subject): " . $mysqli->error);
            throw new Exception("Failed to prepare statement: " . $mysqli->error);
        }

        // Bind parameters: 'iiii' for 4 integers (sujetID, encadrantID, stageID, encadrantID)
        $stmt->bind_param("iiii", $sujetID, $encadrantID, $stageID, $encadrantID);
        $stmt->execute();

        if ($stmt->affected_rows > 0) {
            $response['status'] = 'success';
            $response['message'] = 'Subject assigned to internship and status updated to "en cours" successfully.';
        } else {
            // This could mean the stageID was not found, or it was already assigned to a different encadrant
            // (and not the current one).
            $response['status'] = 'error';
            $response['message'] = 'Failed to assign subject. Internship not found or already assigned to another supervisor.';
            http_response_code(404); // Not Found or Conflict
        }

        $stmt->close();

    } catch (Exception $e) {
        http_response_code(500);
        $response['status'] = 'error';
        $response['message'] = 'Database error: ' . $e->getMessage();
        error_log("Assign Subject Error: " . $e->getMessage()); // Log detailed error
    }
} else {
    http_response_code(405); // Method Not Allowed
    $response['status'] = 'error';
    $response['message'] = 'Invalid request method. Only POST requests are allowed.';
}

echo json_encode($response);

$mysqli->close(); // Close database connection
exit(); // Ensure the script terminates
?>