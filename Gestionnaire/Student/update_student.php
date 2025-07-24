<?php

require_once '../../db_connect.php'; // Path to your database connection file (make sure $mysqli is initialized here)
require_once '../../verify_token.php'; // Path to your JWT verification file

// CORS headers - crucial for Flutter Web
header("Access-Control-Allow-Origin: *"); // Allow all origins for development. In production, specify your app's origin: e.g., "http://localhost:60847"
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS"); // Crucial: Add PUT and OPTIONS methods
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With"); // Allow Content-Type, Authorization, and X-Requested-With headers
header("Access-Control-Max-Age: 3600"); // Cache preflight requests for 1 hour
header("Access-Control-Allow-Credentials: true"); // Allow credentials (e.g., cookies, auth headers)

// Handle preflight OPTIONS requests - THIS IS THE CRITICAL PART FOR CORS
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200); // Send 200 OK for preflight
    ob_end_clean(); // Clean any accidental output buffer
    exit(); // IMPORTANT: Exit immediately after sending preflight headers
}

header('Content-Type: application/json'); // Set content type to JSON for actual requests

$response = array(); // Initialize response array

// Verify JWT token and get user data
$userData = verifyJwtToken(); // $userData will contain ['userID', 'username', 'role'] if token is valid.

// Define the ONLY role allowed to update students.
$allowedRoles = ['Gestionnaire'];

if (!in_array($userData['role'], $allowedRoles)) {
    http_response_code(403); // Forbidden
    echo json_encode(['status' => 'error', 'message' => 'Access denied. Only ' . implode(', ', $allowedRoles) . ' can update students.']);
    ob_end_flush(); // Output buffer and exit
    exit();
}

// Ensure $mysqli is connected before proceeding with database operations
if (!isset($mysqli) || $mysqli->connect_error) {
    http_response_code(500);
    $response = ['status' => 'error', 'message' => 'Database connection failed: ' . ($mysqli->connect_error ?? 'Unknown error')];
    echo json_encode($response);
    ob_end_flush(); // Output buffer and exit
    exit();
}

// *** IMPORTANT: The Flutter client is sending a POST request as per your logs.
// *** So, we must handle POST here, not PUT, unless you change the Flutter client.
// *** Your previous log: "method: POST"
// *** Your previous error: "Invalid request method. Only PUT requests are allowed."
// *** I will change this to POST to match your Flutter client's request.
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $input = json_decode(file_get_contents('php://input'), true);

    if (json_last_error() !== JSON_ERROR_NONE || !is_array($input) || empty($input)) {
        http_response_code(400);
        $response = ['status' => 'error', 'message' => 'Invalid or empty JSON body.'];
        echo json_encode($response);
        $mysqli->close();
        ob_end_flush();
        exit();
    }

    // Get the student ID from the JSON body
    $etudiantID = $input['etudiantID'] ?? null; // Assuming the ID field is named 'etudiantID' in the JSON

    if (empty($etudiantID) || !is_numeric($etudiantID)) {
        http_response_code(400);
        $response = ['status' => 'error', 'message' => 'Student ID (etudiantID) is required in the JSON body and must be a valid number.'];
        echo json_encode($response);
        $mysqli->close();
        ob_end_flush();
        exit();
    }
    $etudiantID = (int)$etudiantID; // Cast to integer for security and type consistency

    $updateFields = [];
    $bindParams = [];
    $bindTypes = '';

    // Dynamically build the UPDATE query based on provided fields in JSON
    // Only add fields to update if they are present in the input
    if (isset($input['username'])) {
        $username = trim($input['username']);
        if (empty($username)) {
            http_response_code(400);
            $response = ['status' => 'error', 'message' => 'Username cannot be empty.'];
            echo json_encode($response);
            $mysqli->close();
            ob_end_flush();
            exit();
        }
        $updateFields[] = "username = ?";
        $bindParams[] = $username;
        $bindTypes .= 's';
    }
    if (isset($input['lastName'])) { // Changed to 'lastName' as per your Flutter log
        $lastname = trim($input['lastName']);
        if (empty($lastname)) {
             http_response_code(400);
             $response = ['status' => 'error', 'message' => 'Lastname cannot be empty.'];
             echo json_encode($response);
             $mysqli->close();
             ob_end_flush();
             exit();
        }
        $updateFields[] = "lastname = ?";
        $bindParams[] = $lastname;
        $bindTypes .= 's';
    }

    if (isset($input['email'])) {
        $email = trim($input['email']);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            $response = ['status' => 'error', 'message' => 'Invalid email format.'];
            echo json_encode($response);
            $mysqli->close();
            ob_end_flush();
            exit();
        }
        // Check if email already exists for another student (excluding the current one being updated)
        $sql_check_email = "SELECT etudiantID FROM etudiants WHERE email = ? AND etudiantID != ?"; // Corrected table and ID column
        if ($stmt_check = $mysqli->prepare($sql_check_email)) {
            $stmt_check->bind_param("si", $email, $etudiantID);
            $stmt_check->execute();
            $stmt_check->store_result();
            if ($stmt_check->num_rows > 0) {
                http_response_code(409); // Conflict
                $response = ['status' => 'error', 'message' => 'This email is already registered by another student.'];
                echo json_encode($response);
                $stmt_check->close();
                $mysqli->close();
                ob_end_flush();
                exit();
            }
            $stmt_check->close();
        } else {
            http_response_code(500);
            $response = ['status' => 'error', 'message' => 'Database error checking email uniqueness: ' . $mysqli->error];
            echo json_encode($response);
            $mysqli->close();
            ob_end_flush();
            exit();
        }

        $updateFields[] = "email = ?";
        $bindParams[] = $email;
        $bindTypes .= 's';
    }
    
    // Additional student-specific fields
    if (isset($input['cin'])) {
        $cin = trim($input['cin']);
        if (empty($cin)) {
            http_response_code(400);
            $response = ['status' => 'error', 'message' => 'CIN cannot be empty.'];
            echo json_encode($response);
            $mysqli->close();
            ob_end_flush();
            exit();
        }
        // Optional: Check CIN uniqueness here if it must be unique globally
        $sql_check_cin = "SELECT etudiantID FROM etudiants WHERE cin = ? AND etudiantID != ?";
        if ($stmt_check = $mysqli->prepare($sql_check_cin)) {
            $stmt_check->bind_param("si", $cin, $etudiantID);
            $stmt_check->execute();
            $stmt_check->store_result();
            if ($stmt_check->num_rows > 0) {
                http_response_code(409); // Conflict
                $response = ['status' => 'error', 'message' => 'This CIN is already registered by another student.'];
                echo json_encode($response);
                $stmt_check->close();
                $mysqli->close();
                ob_end_flush();
                exit();
            }
            $stmt_check->close();
        } else {
            http_response_code(500);
            $response = ['status' => 'error', 'message' => 'Database error checking CIN uniqueness: ' . $mysqli->error];
            echo json_encode($response);
            $mysqli->close();
            ob_end_flush();
            exit();
        }
        $updateFields[] = "cin = ?";
        $bindParams[] = $cin;
        $bindTypes .= 's';
    }
    if (isset($input['niveauEtude'])) { // Assuming 'niveauEtude' from previous 'niveau_etude'
        $niveauEtude = trim($input['niveauEtude']);
        $updateFields[] = "niveauEtude = ?";
        $bindParams[] = $niveauEtude;
        $bindTypes .= 's';
    }
    if (isset($input['nomFaculte'])) { // Assuming 'nomFaculte' from previous 'faculte'
        $nomFaculte = trim($input['nomFaculte']);
        $updateFields[] = "nomFaculte = ?";
        $bindParams[] = $nomFaculte;
        $bindTypes .= 's';
    }
    if (isset($input['cycle'])) {
        $cycle = trim($input['cycle']);
        $updateFields[] = "cycle = ?";
        $bindParams[] = $cycle;
        $bindTypes .= 's';
    }
    if (isset($input['specialite'])) {
        $specialite = trim($input['specialite']);
        $updateFields[] = "specialite = ?";
        $bindParams[] = $specialite;
        $bindTypes .= 's';
    }


    // Do NOT allow password updates via this student endpoint if password field is for 'users' table.
    // Students typically update their own password or it's managed separately.
    // If students have passwords in the `etudiants` table, then you can add it here.
    // Assuming 'etudiants' table does NOT have a password field.

    if (empty($updateFields)) {
        http_response_code(400); // Bad Request
        $response = ['status' => 'error', 'message' => 'No valid fields provided for update.'];
        echo json_encode($response);
        $mysqli->close();
        ob_end_flush();
        exit();
    }

    // Construct the SQL UPDATE query for the 'etudiants' table
    $sql = "UPDATE etudiants SET " . implode(', ', $updateFields) . " WHERE etudiantID = ?";

    // Add etudiantID to the end of bindParams for the WHERE clause
    $bindParams[] = $etudiantID;
    $bindTypes .= 'i';

    if ($stmt = $mysqli->prepare($sql)) {
        // Fix for "Argument #X must be passed by reference" warning for bind_param
        // Create an array of references for dynamic binding
        $refs = [];
        foreach ($bindParams as $key => $value) {
            $refs[$key] = &$bindParams[$key]; // Get reference to each item
        }
        // Prepend the bind_types string to the references array
        array_unshift($refs, $bindTypes);

        // Use call_user_func_array with the array of references
        call_user_func_array([$stmt, 'bind_param'], $refs);

        try {
            if ($stmt->execute()) {
                if ($stmt->affected_rows > 0) {
                    http_response_code(200); // OK
                    $response['status'] = 'success';
                    $response['message'] = 'Student updated successfully.';
                } else {
                    // Check if the student exists but no rows were affected (data was identical)
                    $check_exist_sql = "SELECT etudiantID FROM etudiants WHERE etudiantID = ?";
                    if ($check_exist_stmt = $mysqli->prepare($check_exist_sql)) {
                        $check_exist_stmt->bind_param("i", $etudiantID);
                        $check_exist_stmt->execute();
                        $check_exist_stmt->store_result();
                        if ($check_exist_stmt->num_rows == 0) {
                            http_response_code(404); // Not Found
                            $response['status'] = 'error';
                            $response['message'] = 'Student not found.';
                        } else {
                            // Student exists, but no changes were made (redundant update)
                            http_response_code(200); // Still 200 OK, as the request was processed
                            $response['status'] = 'info'; // Use 'info' status
                            $response['message'] = 'Student data is already up-to-date (no changes made).';
                        }
                        $check_exist_stmt->close();
                    } else {
                        http_response_code(500);
                        $response['status'] = 'error';
                        $response['message'] = 'Database error checking student existence: ' . $mysqli->error;
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
            error_log("MySQLi Error (Update Student): Code " . $e->getCode() . " - Message: " . $e->getMessage()); // Log for debugging
            $response['message'] = 'Database error during update: An unexpected error occurred.';
            // For development, you might include: $response['debug_info'] = $e->getMessage();
        }

        $stmt->close();
    } else {
        http_response_code(500);
        $response['status'] = 'error';
        $response['message'] = 'Error preparing SQL statement for student update: ' . $mysqli->error;
    }
} else {
    http_response_code(405); // Method Not Allowed
    $response['status'] = 'error';
    // This message clearly states what the script expects.
    $response['message'] = 'Invalid request method. Only POST requests are allowed.';
}

$mysqli->close(); // Close DB connection
echo json_encode($response);
ob_end_flush(); // Final flush of the output buffer
?>