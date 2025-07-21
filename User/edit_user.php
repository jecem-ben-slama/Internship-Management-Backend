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
// Admins can edit any user; users can edit their own profile.
$allowedRoles = ['Gestionnaire', 'ChefCentreInformatique']; 

if ($_SERVER["REQUEST_METHOD"] == "POST") { // Using POST as per your UserRepository
    $input = json_decode(file_get_contents('php://input'), true);

    $userID = $input['userID'] ?? null;
    $username = $input['username'] ?? null;
    $password = $input['password'] ?? null; // Optional: only if updating password
    $email = $input['email'] ?? null;
    $lastname = $input['lastname'] ?? null;
    $role = $input['role'] ?? null;

    if ($userID === null) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'User ID is required for update.']);
        $mysqli->close();
        exit();
    }

    // Authorization check: Only admins or the user themselves can update
    if (!in_array($userData['role'], $allowedRoles) && $userData['userID'] != $userID) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Access denied. You do not have permission to update this user.']);
        $mysqli->close();
        exit();
    }

    // Build the UPDATE query dynamically based on provided fields
    $set_clauses = [];
    $bind_types = '';
    $bind_params = [];

    if ($username !== null) {
        $set_clauses[] = 'username = ?';
        $bind_types .= 's';
        $bind_params[] = $username;
    }
    // Only update password if provided and not empty
    if ($password !== null && $password !== '') {
        $set_clauses[] = 'password = ?';
        $bind_types .= 's';
        $bind_params[] = password_hash($password, PASSWORD_DEFAULT); // Hash new password
    }
    if ($email !== null) {
        // Check for duplicate email if changing and it's not the current user's email
        $stmt_check_email = $mysqli->prepare("SELECT userID FROM users WHERE email = ? AND userID != ?");
        if (!$stmt_check_email) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Failed to prepare email check statement: ' . $mysqli->error]);
            $mysqli->close();
            exit();
        }
        $stmt_check_email->bind_param("si", $email, $userID);
        $stmt_check_email->execute();
        $stmt_check_email->store_result();
        if ($stmt_check_email->num_rows > 0) {
            http_response_code(409); // Conflict
            echo json_encode(['status' => 'error', 'message' => 'Email already in use by another user.']);
            $stmt_check_email->close();
            $mysqli->close();
            exit();
        }
        $stmt_check_email->close();

        $set_clauses[] = 'email = ?';
        $bind_types .= 's';
        $bind_params[] = $email;
    }
    if ($lastname !== null) {
        $set_clauses[] = 'lastname = ?';
        $bind_types .= 's';
        $bind_params[] = $lastname;
    }
    // Only allow role change by specific admin roles, not by the user themselves
    if ($role !== null && in_array($userData['role'], ['Gestionnaire', 'ChefCentreInformatique'])) {
        $set_clauses[] = 'role = ?';
        $bind_types .= 's';
        $bind_params[] = $role;
    } elseif ($role !== null && $userData['role'] != $role) {
        // If a non-admin tries to change their own role, deny it
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Access denied. You cannot change your own role.']);
        $mysqli->close();
        exit();
    }


    if (empty($set_clauses)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'No fields provided for update.']);
        $mysqli->close();
        exit();
    }

    $sql = "UPDATE users SET " . implode(', ', $set_clauses) . " WHERE userID = ?";
    $bind_types .= 'i'; // Add type for userID
    $bind_params[] = $userID; // Add userID to parameters

    $stmt = $mysqli->prepare($sql);

    if ($stmt) {
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

        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                // Fetch the updated user (without password) to return it
                $stmt_fetch = $mysqli->prepare("SELECT userID, username, email, lastname, role FROM users WHERE userID = ?");
                $stmt_fetch->bind_param("i", $userID);
                $stmt_fetch->execute();
                $result = $stmt_fetch->get_result();
                $updatedUser = $result->fetch_assoc();

                $response['status'] = 'success';
                $response['message'] = 'User updated successfully.';
                $response['data'] = $updatedUser;
                $stmt_fetch->close();
            } else {
                http_response_code(200); // Changed from 404 to 200 for 'no changes made'
                $response['status'] = 'info'; // Use 'info' status
                $response['message'] = 'User found, but no changes were made.';
            }
        } else {
            http_response_code(500);
            $response['status'] = 'error';
            $response['message'] = 'Failed to update user: ' . $stmt->error;
        }
        $stmt->close();
    } else {
        http_response_code(500);
        $response['status'] = 'error';
        $response['message'] = 'Failed to prepare statement: ' . $mysqli->error;
    }
} else {
    http_response_code(405); // Method Not Allowed
    $response['status'] = 'error';
    $response['message'] = 'Invalid request method. Only POST requests are allowed.';
}

$mysqli->close(); // Close database connection
echo json_encode($response);
?>