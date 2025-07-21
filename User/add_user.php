<?php
require_once '../db_connect.php';
require_once '../verify_token.php';

// CORS headers
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Credentials: true");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

header('Content-Type: application/json');

$response = array();

$userData = verifyJwtToken();

$allowedRoles = ['Gestionnaire', 'ChefCentreInformatique'];
if (!in_array($userData['role'], $allowedRoles)) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Access denied. You do not have permission to add users.']);
    $mysqli->close();
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $input = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE || empty($input)) {
        $input = $_POST; // Fallback to $_POST if JSON parsing fails or empty
    }

    $username = trim($input['username'] ?? '');
    $email = trim($input['email'] ?? '');
    $password = $input['password'] ?? '';
    $lastname = trim($input['lastname'] ?? '');
    $role = trim($input['role'] ?? '');

    if (empty($username) || empty($email) || empty($password) || empty($lastname) || empty($role)) {
        $response['status'] = 'error';
        $response['message'] = 'Please Fill all The Fields.';
        echo json_encode($response);
        $mysqli->close();
        exit();
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $response['status'] = 'error';
        $response['message'] = 'Invalid email format.';
        echo json_encode($response);
        $mysqli->close();
        exit();
    }

    $sql_check_email = "SELECT userID FROM Users WHERE email = ?";
    if ($stmt_check = $mysqli->prepare($sql_check_email)) {
        $stmt_check->bind_param("s", $param_email_check);
        $param_email_check = $email;
        $stmt_check->execute();
        $stmt_check->store_result();

        if ($stmt_check->num_rows > 0) {
            $response['status'] = 'error';
            $response['message'] = 'This email is already registered.';
            echo json_encode($response);
            $stmt_check->close();
            $mysqli->close();
            exit();
        }
        $stmt_check->close();
    } else {
        $response['status'] = 'error';
        $response['message'] = 'Database error checking existing email: ' . $mysqli->error;
        echo json_encode($response);
        $mysqli->close();
        exit();
    }

    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    $sql_insert = "INSERT INTO Users (username, email, lastname, password, role) VALUES (?, ?, ?, ?, ?)";

    if ($stmt_insert = $mysqli->prepare($sql_insert)) {
        $stmt_insert->bind_param("sssss", $param_username, $param_email, $param_lastname, $param_password, $param_role);

        $param_username = $username;
        $param_email = $email;
        $param_lastname = $lastname;
        $param_password = $hashed_password;
        $param_role = $role;

        try {
            if ($stmt_insert->execute()) {
                $new_userID = $mysqli->insert_id; // Get the ID of the newly created user

                // --- IMPORTANT CHANGE HERE: NEST THE NEW USER DATA UNDER 'data' KEY ---
                $response['status'] = 'success';
                $response['message'] = 'User added successfully!';
                $response['data'] = [ // Create a 'data' key
                    'userID' => $new_userID,
                    'username' => $username,
                    'email' => $email,
                    'lastname' => $lastname,
                    'role' => $role,
                    // Do NOT include password here for security
                ];
            } else {
                $response['status'] = 'error';
                $response['message'] = 'Database error adding user: ' . $stmt_insert->error;
            }
        } catch (mysqli_sql_exception $e) {
            $response['status'] = 'error';
            $error_message_from_db = $e->getMessage();
            $error_code_from_db = $e->getCode();

            if ($error_code_from_db == 1062) {
                $response['message'] = 'A user with this email might already exist (duplicate entry).';
            } else {
                $response['message'] = 'Database error adding user: ' . $error_message_from_db;
            }
        }

        $stmt_insert->close();
    } else {
        $response['status'] = 'error';
        $response['message'] = 'Error preparing SQL statement for user addition: ' . $mysqli->error;
    }
} else {
    $response['status'] = 'error';
    $response['message'] = 'Invalid request method. Only POST requests are allowed.';
}

$mysqli->close();
echo json_encode($response);
?>