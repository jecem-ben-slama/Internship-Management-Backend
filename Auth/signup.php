<?php
// signup.php (Complete and Updated with try-catch)

// Include the database connection file.
// Make sure this path is correct based on your folder structure:
// If db_connect.php is in 'BACKEND/' and signup.php is in 'BACKEND/Auth/', use '../db_connect.php'.
require_once '../db_connect.php'; 

// Set the content type header to indicate that the response will be in JSON format.
header('Content-Type: application/json');

// Initialize an array to hold the response data.
$response = array();

// Check if the request method is POST.
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Attempt to get input data.
    // First, try decoding JSON input (common with Postman Raw-JSON body).
    $input = json_decode(file_get_contents('php://input'), true);

    // If JSON input is not valid or empty, fall back to $_POST (for form-data or x-www-form-urlencoded).
    if (json_last_error() !== JSON_ERROR_NONE || empty($input)) {
        $input = $_POST;
    }

    // Retrieve and sanitize input values.
    $username = trim($input['username'] ?? '');
    $password = $input['password'] ?? ''; // Keep password raw for hashing.
    $email = trim($input['email'] ?? '');
    $lastname = trim($input['lastname'] ?? ''); 
    $role = trim($input['role'] ?? '');

    // --- Basic Input Validation ---
    if (empty($username) || empty($password) || empty($email) || empty($lastname) || empty($role)) {
        $response['status'] = 'error';
        $response['message'] = 'All fields (username, password, email, lastname, role) are required.';
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

    if (strlen($password) < 6) { 
        $response['status'] = 'error';
        $response['message'] = 'Password must be at least 6 characters long.';
        echo json_encode($response);
        $mysqli->close();
        exit();
    }

    $allowed_roles = ['Gestionnaire', 'Encadrant', 'ChefCentreInformatique'];
    if (!in_array($role, $allowed_roles)) {
        $response['status'] = 'error';
        $response['message'] = 'Invalid role specified. Allowed roles are: ' . implode(', ', $allowed_roles) . '.';
        echo json_encode($response);
        $mysqli->close();
        exit();
    }

    // --- Password Hashing ---
    $password_hashed = password_hash($password, PASSWORD_DEFAULT);

    // --- Prepare SQL INSERT Statement ---
    $sql = "INSERT INTO Users (username, password, email, lastname, role) VALUES (?, ?, ?, ?, ?)";

    // Use a prepared statement to prevent SQL injection.
    if ($stmt = $mysqli->prepare($sql)) {
        $stmt->bind_param("sssss", $param_username, $param_password_hashed, $param_email, $param_lastname, $param_role);

        $param_username = $username;
        $param_password_hashed = $password_hashed;
        $param_email = $email;
        $param_lastname = $lastname;
        $param_role = $role;

        // --- Attempt to execute the prepared statement with try-catch ---
        try {
            if ($stmt->execute()) {
                $response['status'] = 'success';
                $response['message'] = 'User registered successfully!';
                $response['userID'] = $mysqli->insert_id;
            } else {
                // This 'else' block would typically catch non-exception errors (less common with proper mysqli config)
                $response['status'] = 'error';
                $response['message'] = 'General database execution error: ' . $stmt->error;
            }
        } catch (mysqli_sql_exception $e) {
            // Explicitly catch the MySQLi SQL exception
            $response['status'] = 'error';
            $error_message_from_db = $e->getMessage(); // Get the detailed error message from the exception
            $error_code_from_db = $e->getCode();   // Get the MySQL error code (e.g., 1062)

            if ($error_code_from_db == 1062) { // MySQL error code for duplicate entry
                // Check specifically for duplicate 'email' key
                if (strpos($error_message_from_db, 'for key \'email\'') !== false || strpos($error_message_from_db, 'email_UNIQUE') !== false) {
                    $response['message'] = 'Email already registered. Please use a different email.';
                }
                // This block will catch duplicate 'username' if 'username' IS STILL UNIQUE in your DB.
                // If 'username' is NOT UNIQUE (as we discussed removing the constraint), this part won't trigger for username duplicates.
                elseif (strpos($error_message_from_db, 'for key \'username\'') !== false || strpos($error_message_from_db, 'username_UNIQUE') !== false) {
                    $response['message'] = 'Username already exists. Please choose a different one.';
                }
                else {
                    // Fallback for other potential duplicate errors if any other unique constraints exist
                    $response['message'] = 'A duplicate entry error occurred. Error: ' . $error_message_from_db;
                }
            } else {
                // Handle other database errors (e.g., connection issues, syntax errors, etc.)
                $response['message'] = 'Database error during registration: ' . $error_message_from_db;
            }
        }

        // Close the statement to free up resources.
        $stmt->close();
    } else {
        // Handle errors if the SQL statement cannot be prepared (e.g., syntax error in $sql query).
        $response['status'] = 'error';
        $response['message'] = 'Error preparing SQL statement: ' . $mysqli->error;
    }
} else {
    // If the request method is not POST, return an error.
    $response['status'] = 'error';
    $response['message'] = 'Invalid request method. Only POST requests are allowed.';
}

// Close the database connection.
$mysqli->close();

// Output the JSON response.
echo json_encode($response);
?>