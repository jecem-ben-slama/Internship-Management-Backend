<?php
require_once '../db_connect.php';
require_once '../verify_token.php'; 
header('Content-Type: application/json');
$response = array();
$userData = verifyJwtToken(); // $userData will contain ['userID', 'username', 'role'] if token is valid.

$allowedRoles = ['Gestionnaire']; 

if (!in_array($userData['role'], $allowedRoles)) {
    http_response_code(403); // Forbidden
    echo json_encode(['status' => 'error', 'message' => 'Access denied. Only ' . implode('', $allowedRoles) . ' can add encadrants.']);
    $mysqli->close(); // Close DB connection before exiting.
    exit();
}
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $input = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE || empty($input)) {
        $input = $_POST;
    }

    $username = trim($input['username'] ?? '');
    $email = trim($input['email'] ?? '');
    $password = $input['password'] ?? '';
    $lastname=$trim(input['lastname']??'');
    // The role for the new user will be fixed as 'Encadrant'
    $role = 'Encadrant'; 

    // --- Input Validation for the NEW Encadrant's Data ---
    if (empty($username) || empty($email) || empty($password)) {
        $response['status'] = 'error';
        $response['message'] = 'Username, email, and password are required for the new encadrant.';
        echo json_encode($response);
        $mysqli->close();
        exit();
    }

    // Basic email format validation
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $response['status'] = 'error';
        $response['message'] = 'Invalid email format.';
        echo json_encode($response);
        $mysqli->close();
        exit();
    }

    // --- Check if email already exists in the Users table ---
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

    // --- Hash the password before storing it ---
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // --- Prepare SQL INSERT Statement to add the new Encadrant user ---
    // Make sure your 'Users' table has 'username', 'email', 'password', and 'role' columns.
    $sql_insert = "INSERT INTO Users (username, email,lastname, password, role) VALUES (?, ?, ?, ?, ?)";

    if ($stmt_insert = $mysqli->prepare($sql_insert)) {
        // Bind parameters: 'ssss' for four string parameters (username, email, hashed_password, role).
        $stmt_insert->bind_param("sssss", $param_username, $param_email,$param_lastname, $param_password, $param_role);

        $param_username = $username;
        $param_email = $email;
        $param_lastname=$lastname;
        $param_password = $hashed_password;
        $param_role = $role; // This is fixed as 'Encadrant'

        try {
            if ($stmt_insert->execute()) {
                $response['status'] = 'success';
                $response['message'] = 'Encadrant added successfully!';
                $response['userID'] = $mysqli->insert_id; // Get the ID of the newly created user
            } else {
                $response['status'] = 'error';
                $response['message'] = 'Database error adding encadrant: ' . $stmt_insert->error;
            }
        } catch (mysqli_sql_exception $e) {
            $response['status'] = 'error';
            $error_message_from_db = $e->getMessage();
            $error_code_from_db = $e->getCode();

            // Handle specific database errors like duplicate entries if email is unique, etc.
            if ($error_code_from_db == 1062) { 
                $response['message'] = 'A user with this email might already exist (duplicate entry).';
            } else {
                $response['message'] = 'Database error adding encadrant: ' . $error_message_from_db;
            }
        }

        $stmt_insert->close();
    } else {
        $response['status'] = 'error';
        $response['message'] = 'Error preparing SQL statement for encadrant addition: ' . $mysqli->error;
    }
} else {
    $response['status'] = 'error';
    $response['message'] = 'Invalid request method. Only POST requests are allowed.';
}

$mysqli->close();
echo json_encode($response);
?>