<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS, DELETE, PUT");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

//*
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}
// login.php (Modified to generate JWT)

// Include necessary files. Paths are relative from BACKEND/Auth/
require_once '../db_connect.php'; 
require_once '../vendor/autoload.php'; // Composer's autoloader for JWT library
require_once '../config.php';       // Your JWT secret key config

use Firebase\JWT\JWT; // Import the JWT class

header('Content-Type: application/json');
$response = array();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $input = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE || empty($input)) {
        $input = $_POST;
    }

    $email = trim($input['email'] ?? '');
    $password = $input['password'] ?? '';

    if (empty($email) || empty($password)) {
        $response['status'] = 'error';
        $response['message'] = 'Email and password are required.';
        echo json_encode($response);
        $mysqli->close();
        exit();
    }

    $sql = "SELECT userID, username, password, role FROM Users WHERE email = ?";

    if ($stmt = $mysqli->prepare($sql)) {
        $stmt->bind_param("s", $param_email);
        $param_email = $email;

        if ($stmt->execute()) {
            $stmt->store_result();

            if ($stmt->num_rows == 1) {
                $stmt->bind_result($userID, $db_username, $hashed_password, $role);
                $stmt->fetch();

                if (password_verify($password, $hashed_password)) {
                    // --- Login successful, NOW GENERATE JWT ---
                    $issuedAt = time();
                    $expirationTime = $issuedAt + JWT_EXPIRATION_SECONDS; // Token valid for configured time

                    $payload = array(
                        'iat'  => $issuedAt,       // Issued at: time when the token was generated
                        'exp'  => $expirationTime, // Expiration time
                        'data' => [                // Data specific to the user, included in the token
                            'userID'   => $userID,
                            'username' => $db_username,
                            'role'     => $role
                        ]
                    );

                    // Generate the JWT using your secret key and algorithm (HS256 is common)
                    $jwt = JWT::encode($payload, JWT_SECRET_KEY, 'HS256'); 

                    $response['status'] = 'success';
                    $response['message'] = 'Login successful!';
                    $response['userID'] = $userID;
                    $response['username'] = $db_username;
                    $response['role'] = $role;
                    $response['token'] = $jwt; // <--- Send the JWT back to the client

                } else {
                    $response['status'] = 'error';
                    $response['message'] = 'Invalid email or password.';
                }
            } else {
                $response['status'] = 'error';
                $response['message'] = 'Invalid email or password.';
            }
        } else {
            $response['status'] = 'error';
            $response['message'] = 'Database error during login query: ' . $stmt->error;
        }
        $stmt->close();
    } else {
        $response['status'] = 'error';
        $response['message'] = 'Error preparing SQL statement: ' . $mysqli->error;
    }
} else {
    $response['status'] = 'error';
    $response['message'] = 'Invalid request method. Only POST requests are allowed.';
}

$mysqli->close();
echo json_encode($response);
?>