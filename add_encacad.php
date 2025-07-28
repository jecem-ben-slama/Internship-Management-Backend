<?php
// backend/Gestionnaire/add_encadrant.php

// Enable error reporting for debugging (REMOVE IN PRODUCTION)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start output buffering to catch any premature output
ob_start();

require_once 'db_connect.php'; // Path to your database connection file
require_once 'verify_token.php'; // Path to your JWT verification file

// CORS headers - crucial for Flutter Web
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS"); // Allow POST for adding, OPTIONS for preflight
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

// Define allowed roles for adding encadrants
$allowedRoles = ['Gestionnaire', 'ChefCentreInformatique'];

if (!in_array($userData['role'], $allowedRoles)) {
    ob_end_clean(); // Clean any output buffer
    http_response_code(403); // Forbidden
    echo json_encode(['status' => 'error', 'message' => 'Access denied. Only ' . implode(', ', $allowedRoles) . ' can add encadrants.']);
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

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $input = json_decode(file_get_contents('php://input'), true);

    if (json_last_error() !== JSON_ERROR_NONE || !is_array($input) || empty($input)) {
        ob_end_clean();
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid or empty JSON body.']);
        $mysqli->close();
        exit();
    }

    // Extract data from input
    $name = trim($input['name'] ?? '');
    $lastname = trim($input['lastname'] ?? '');
    $email = trim($input['email'] ?? ''); // Email is nullable in DB, but we'll validate if provided

    // Validate required fields
    if (empty($name)) {
        ob_end_clean();
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Name is required.']);
        $mysqli->close();
        exit();
    }
    if (empty($lastname)) {
        ob_end_clean();
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Lastname is required.']);
        $mysqli->close();
        exit();
    }

    // Validate email if provided, and check for uniqueness
    if (!empty($email)) {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            ob_end_clean();
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Invalid email format.']);
            $mysqli->close();
            exit();
        }

        // Check if email already exists
        $stmt_check_email = $mysqli->prepare("SELECT encadrantID FROM encadrantacademique WHERE email = ?");
        if (!$stmt_check_email) {
            ob_end_clean();
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Database error preparing email check: ' . $mysqli->error]);
            $mysqli->close();
            exit();
        }
        $stmt_check_email->bind_param("s", $email);
        $stmt_check_email->execute();
        $stmt_check_email->store_result();
        if ($stmt_check_email->num_rows > 0) {
            ob_end_clean();
            http_response_code(409); // Conflict
            echo json_encode(['status' => 'error', 'message' => 'This email is already registered for another encadrant.']);
            $stmt_check_email->close();
            $mysqli->close();
            exit();
        }
        $stmt_check_email->close();
    } else {
        // If email is empty, set it to NULL for the database insertion
        $email = null;
    }

    // Prepare the INSERT statement
    $sql = "INSERT INTO encadrantacademique (name, lastname, email) VALUES (?, ?, ?)";
    $stmt = $mysqli->prepare($sql);

    if ($stmt) {
        // Bind parameters
        $stmt->bind_param("sss", $name, $lastname, $email);

        try {
            if ($stmt->execute()) {
                $new_encadrant_id = $mysqli->insert_id; // Get the ID of the newly inserted record
                ob_end_clean();
                http_response_code(201); // 201 Created
                $response['status'] = 'success';
                $response['message'] = 'Encadrant added successfully.';
                $response['data'] = [
                    'encadrantID' => $new_encadrant_id,
                    'name' => $name,
                    'lastname' => $lastname,
                    'email' => $email
                ];
            } else {
                ob_end_clean();
                http_response_code(500);
                $response['status'] = 'error';
                $response['message'] = 'Failed to add encadrant: ' . $stmt->error;
            }
        } catch (mysqli_sql_exception $e) {
            ob_end_clean();
            http_response_code(500);
            $response['status'] = 'error';
            error_log("MySQLi Error (Add Encadrant): Code " . $e->getCode() . " - Message: " . $e->getMessage());
            $response['message'] = 'Database error during insertion: An unexpected error occurred.';
        } finally {
            $stmt->close();
        }
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
    $response['message'] = 'Invalid request method. Only POST requests are allowed.';
}

$mysqli->close(); // Close database connection
echo json_encode($response);
// No ob_end_flush() here, as ob_start() is at the very top and exit() handles flushing.
?>
