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
$allowedRolesEndpoint = ['Gestionnaire', 'ChefCentreInformation', 'Encadrant']; // For example, an Encadrant might need to see other Encadrants or Gestionnaires. Adjust as needed.

if (!in_array($userData['role'], $allowedRolesEndpoint)) {
    http_response_code(403); // Forbidden
    echo json_encode(['status' => 'error', 'message' => 'Access denied. You do not have permission to list users based on roles.']);
    $mysqli->close();
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "GET") {
    // Get roles from query parameter. Expecting a comma-separated string, e.g., ?roles=Encadrant,Gestionnaire
    $requestedRolesString = $_GET['roles'] ?? '';
    $requestedRoles = [];

    if (!empty($requestedRolesString)) {
        // Sanitize and explode the roles string into an array
        $requestedRoles = array_map(function($role) use ($mysqli) {
            return $mysqli->real_escape_string(trim($role));
        }, explode(',', $requestedRolesString));
    }

    $sql = "SELECT userID, username, email, lastname, role FROM users";
    $where_clauses = [];
    $bind_params = [];
    $bind_types = '';

    if (!empty($requestedRoles)) {
        // Create placeholders for the IN clause
        $placeholders = implode(',', array_fill(0, count($requestedRoles), '?'));
        $where_clauses[] = "role IN ($placeholders)";
        $bind_types .= str_repeat('s', count($requestedRoles)); // 's' for string
        $bind_params = array_merge($bind_params, $requestedRoles);
    }

    if (!empty($where_clauses)) {
        $sql .= " WHERE " . implode(' AND ', $where_clauses);
    }

    $sql .= " ORDER BY username ASC";

    $stmt = $mysqli->prepare($sql);

    if ($stmt) {
        if (!empty($bind_params)) {
            // --- START OF FIX FOR bind_param WARNING ---
            // Create an array of references for dynamic binding
            $refs = [];
            foreach ($bind_params as $key => $value) {
                $refs[$key] = &$bind_params[$key]; // Get reference to each item
            }
            // Prepend the bind_types string to the references array
            array_unshift($refs, $bind_types);

            // Use call_user_func_array with the array of references
            call_user_func_array([$stmt, 'bind_param'], $refs);
            // --- END OF FIX ---
        }

        if ($stmt->execute()) {
            $result = $stmt->get_result();
            $users = array();
            if ($result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    $users[] = $row;
                }
                $response['status'] = 'success';
                $response['message'] = 'Users fetched successfully.';
                $response['data'] = $users;
            } else {
                $response['status'] = 'success';
                $response['message'] = 'No users found for the specified roles.';
                $response['data'] = [];
            }
            $result->free();
        } else {
            http_response_code(500); // Internal Server Error
            $response['status'] = 'error';
            $response['message'] = 'Database query failed: ' . $stmt->error;
        }
        $stmt->close();
    } else {
        http_response_code(500); // Internal Server Error
        $response['status'] = 'error';
        $response['message'] = 'Failed to prepare statement: ' . $mysqli->error;
    }
} else {
    http_response_code(405); // Method Not Allowed
    $response['status'] = 'error';
    $response['message'] = 'Invalid request method. Only GET requests are allowed.';
}

$mysqli->close(); // Close database connection
echo json_encode($response);
?>