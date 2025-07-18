<?php
require_once '../../db_connect.php';
require_once '../../verify_token.php';
header('Content-Type: application/json');
$response = array();

$userData = verifyJwtToken(); // $userData will contain ['userID', 'username', 'role'] if token is valid.

// Define the ONLY role allowed to update encadrants.
$allowedRoles = ['Gestionnaire'];

if (!in_array($userData['role'], $allowedRoles)) {
    http_response_code(403); // Forbidden
    echo json_encode(['status' => 'error', 'message' => 'Access denied. Only ' . implode('', $allowedRoles) . ' can update encadrants.']);
    $mysqli->close(); // Close DB connection before exiting.
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "PUT") {
    $userID = $_GET['userID'] ?? null;

    $input = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE || empty($input)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid or empty JSON body.']);
        $mysqli->close();
        exit();
    }

    if (empty($userID) || !is_numeric($userID)) {
        http_response_code(400); 
        echo json_encode(['status' => 'error', 'message' => 'User ID is required and must be a valid number in the URL.']);
        $mysqli->close();
        exit();
    }
    $userID = (int)$userID;

    $updateFields = [];
    $bindParams = [];
    $bindTypes = '';

    if (isset($input['username'])) {
        $username = trim($input['username']);
        if (empty($username)) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Username cannot be empty.']);
            $mysqli->close();
            exit();
        }
        $updateFields[] = "username = ?";
        $bindParams[] = $username;
        $bindTypes .= 's';
    }

    if (isset($input['email'])) {
        $email = trim($input['email']);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Invalid email format.']);
            $mysqli->close();
            exit();
        }
        $sql_check_email = "SELECT etudiantID FROM etudiants WHERE email = ? AND userID != ?";
        if ($stmt_check = $mysqli->prepare($sql_check_email)) {
            $stmt_check->bind_param("si", $email, $userID);
            $stmt_check->execute();
            $stmt_check->store_result();
            if ($stmt_check->num_rows > 0) {
                http_response_code(409); // Conflict
                echo json_encode(['status' => 'error', 'message' => 'This email is already registered by another user.']);
                $stmt_check->close();
                $mysqli->close();
                exit();
            }
            $stmt_check->close();
        } else {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Database error checking email uniqueness: ' . $mysqli->error]);
            $mysqli->close();
            exit();
        }

        $updateFields[] = "email = ?";
        $bindParams[] = $email;
        $bindTypes .= 's';
    }

    // Check if password is provided
    if (isset($input['password']) && !empty($input['password'])) {
        $password = $input['password'];
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $updateFields[] = "password = ?";
        $bindParams[] = $hashed_password;
        $bindTypes .= 's';
    }

    // Prevent changing the role via this endpoint (fixed to Encadrant)
   

    

    // Add userID to the end of bindParams
    $bindParams[] = $etudiantID;
    $bindTypes .= 'i';

    if ($stmt = $mysqli->prepare($sql)) {
        // Fix for "Argument #X must be passed by reference" warning
        // Create an array of references for bind_param
        $refs = [];
        foreach ($bindParams as $key => $value) {
            $refs[$key] = &$bindParams[$key]; // Get reference to each value
        }

        // Prepend the $bindTypes string to the array of references
        array_unshift($refs, $bindTypes);

        // Call bind_param with the array of references
        call_user_func_array([$stmt, 'bind_param'], $refs);

        // ... (rest of your try-catch block for execute and result handling) ...
        try {
            if ($stmt->execute()) {
                if ($stmt->affected_rows > 0) {
                    $response['status'] = 'success';
                    $response['message'] = 'Encadrant updated successfully.';
                } else {
                    // ... (your existing logic for 0 affected rows) ...
                    // Check if the encadrant actually exists
                    $check_exist_sql = "SELECT etudiantID FROM etudiants WHERE userID = ? ";
                    if ($check_exist_stmt = $mysqli->prepare($check_exist_sql)) {
                        $check_exist_stmt->bind_param("i", $etudiantID); // This bind_param is fine as it's not dynamic
                        $check_exist_stmt->execute();
                        $check_exist_stmt->store_result();
                        if ($check_exist_stmt->num_rows == 0) {
                             http_response_code(404);
                             $response['status'] = 'error';
                             $response['message'] = 'etudiant not found or specified user is not an Encadrant.';
                        } else {
                             $response['status'] = 'info'; // Changed from success to info as no actual change happened
                             $response['message'] = 'etudiant data is already up-to-date (no changes made).';
                        }
                        $check_exist_stmt->close();
                    } else {
                        http_response_code(500);
                        $response['status'] = 'error';
                        $response['message'] = 'Database error checking etudiant existence: ' . $mysqli->error;
                    }
                }
            } else {
                http_response_code(500);
                $response['status'] = 'error';
                $response['message'] = 'Database error during update: ' . $stmt->error;
            }
        } catch (mysqli_sql_exception $e) {
            http_response_code(500);
            $response['status'] = 'error';
            $response['message'] = 'Database error during update: ' . $e->getMessage();
        }

        $stmt->close();
    } else {
        http_response_code(500);
        $response['status'] = 'error';
        $response['message'] = 'Error preparing SQL statement for etudiant update: ' . $mysqli->error;
    }
} else {
    http_response_code(405); // Method Not Allowed
    $response['status'] = 'error';
    $response['message'] = 'Invalid request method. Only PUT requests are allowed.';
}

$mysqli->close();
echo json_encode($response);
?>