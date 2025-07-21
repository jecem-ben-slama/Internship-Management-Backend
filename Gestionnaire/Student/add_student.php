<?php
// PHP error reporting for debugging - REMOVE IN PRODUCTION
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../../db_connect.php'; // Path to your database connection file (make sure $mysqli is initialized here)
require_once '../../verify_token.php'; // Path to your JWT verification file

// CORS headers - crucial for Flutter Web
header("Access-Control-Allow-Origin: *"); // Allow all origins for development. In production, specify your app's origin: e.g., "http://localhost:60847"
header("Access-Control-Allow-Methods: GET, POST, OPTIONS"); // Crucial: Add POST and OPTIONS methods
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With"); // Allow Content-Type, Authorization, and X-Requested-With headers
header("Access-Control-Max-Age: 3600"); // Cache preflight requests for 1 hour
header("Access-Control-Allow-Credentials: true"); // Allow credentials (e.g., cookies, auth headers)

// Start output buffering (recommended for cleaner output control)
if (ob_get_length() === false) { // Only start if not already started
    ob_start();
}


// Handle preflight OPTIONS requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200); // Send 200 OK for preflight
    ob_end_clean(); // Clean any accidental output buffer
    exit(); // IMPORTANT: Exit immediately after sending preflight headers
}

header('Content-Type: application/json'); // Set content type to JSON for actual requests

$response = array(); // Initialize response array

// Verify JWT token and get user data
$userData = verifyJwtToken(); // This function is expected to return an associative array if valid, or handle errors/exit itself

$allowedRoles = ['Gestionnaire']; // Only 'Gestionnaire' can add students

if (!in_array($userData['role'], $allowedRoles)) {
    http_response_code(403); // Forbidden
    echo json_encode(['status' => 'error', 'message' => 'Access denied. Only ' . implode(', ', $allowedRoles) . ' can add students.']);
    // No need for ob_end_flush() here as exit() will handle it
    exit();
}

// Ensure $mysqli is connected before proceeding with database operations
if (!isset($mysqli) || $mysqli->connect_error) {
    http_response_code(500);
    $response = ['status' => 'error', 'message' => 'Database connection failed: ' . ($mysqli->connect_error ?? 'Unknown error')];
    echo json_encode($response);
    exit();
}


if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $input = json_decode(file_get_contents('php://input'), true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("JSON Decode Error: " . json_last_error_msg());
        http_response_code(400); // Bad Request
        $response = ['status' => 'error', 'message' => 'Invalid JSON in request body.'];
        echo json_encode($response);
        $mysqli->close();
        exit();
    }
    
    if (empty($input)) {
        http_response_code(400); // Bad Request
        $response = ['status' => 'error', 'message' => 'Request body is empty or malformed.'];
        echo json_encode($response);
        $mysqli->close();
        exit();
    }

    // Retrieve and sanitize input values for the new student.
    $username = trim($input['username'] ?? '');
    $lastname = trim($input['lastname'] ?? ''); 
    $email = trim($input['email'] ?? '');
    $cin = $input['cin'] ?? '';
    $niveau_etude = $input['niveau_etude'] ?? '';
    $faculte = $input['faculte'] ?? '';
    $cycle = $input['cycle'] ?? '';
    $specialite = $input['specialite'] ?? '';


    // --- Input Validation for the NEW Student's Data ---
    if (empty($username) || empty($email) || empty($cin) || empty($lastname)) { 
        http_response_code(400); // Bad Request
        $response['status'] = 'error';
        $response['message'] = 'Username, last name, email, and CIN are required.'; 
        echo json_encode($response);
        $mysqli->close();
        exit();
    }

    // Basic email format validation
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400); // Bad Request
        $response['status'] = 'error';
        $response['message'] = 'Invalid email format.';
        echo json_encode($response);
        $mysqli->close();
        exit();
    }

    // --- Check if email already exists in the Students table ---
    $sql_check_email = "SELECT etudiantID FROM etudiants WHERE email = ?";
    if ($stmt_check = $mysqli->prepare($sql_check_email)) {
        $stmt_check->bind_param("s", $param_email_check);
        $param_email_check = $email;
        $stmt_check->execute();
        $stmt_check->store_result();

        if ($stmt_check->num_rows > 0) {
            http_response_code(409); // Conflict
            $response['status'] = 'error';
            $response['message'] = 'This email is already registered for a student.';
            echo json_encode($response);
            $stmt_check->close();
            $mysqli->close();
            exit();
        }
        $stmt_check->close();
    } else {
        http_response_code(500); // Internal Server Error
        $response['status'] = 'error';
        $response['message'] = 'Database error checking existing email: ' . $mysqli->error;
        echo json_encode($response);
        $mysqli->close();
        exit();
    }

    // --- Check if CIN already exists in the Students table ---
    $sql_check_cin = "SELECT etudiantID FROM etudiants WHERE cin = ?";
    if ($stmt_check = $mysqli->prepare($sql_check_cin)) {
        $stmt_check->bind_param("s", $param_cin_check); 
        $param_cin_check = $cin;
        $stmt_check->execute();
        $stmt_check->store_result();

        if ($stmt_check->num_rows > 0) {
            http_response_code(409); // Conflict
            $response['status'] = 'error';
            $response['message'] = 'This CIN is already registered for a student.';
            echo json_encode($response);
            $stmt_check->close();
            $mysqli->close();
            exit();
        }
        $stmt_check->close();
    } else {
        http_response_code(500); // Internal Server Error
        $response['status'] = 'error';
        $response['message'] = 'Database error checking existing CIN: ' . $mysqli->error;
        echo json_encode($response);
        $mysqli->close();
        exit();
    }


    $sql_insert = "INSERT INTO etudiants (username, lastname, email, cin, niveauEtude, nomFaculte, cycle, specialite) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

    if ($stmt_insert = $mysqli->prepare($sql_insert)) {
        $stmt_insert->bind_param("ssssssss", $param_username, $param_lastname, $param_email, $param_cin, $param_niveauEtude, $param_nomFaculte, $param_cycle, $param_specialite);

        $param_username = $username;
        $param_lastname = $lastname; 
        $param_email = $email;
        $param_cin = $cin;
        $param_niveauEtude = $niveau_etude;
        $param_nomFaculte = $faculte;
        $param_cycle = $cycle;
        $param_specialite = $specialite;

        try {
            if ($stmt_insert->execute()) {
                $response['status'] = 'success';
                $response['message'] = 'Student added successfully!';
                $response['etudiantID'] = $mysqli->insert_id; 
                echo json_encode($response);
                $stmt_insert->close(); // Close statement before closing connection
                $mysqli->close();
                exit(); // Exit after successful response
            } else {
                http_response_code(500); // Internal Server Error
                $response['status'] = 'error';
                $response['message'] = 'Database error adding Student: ' . $stmt_insert->error;
                echo json_encode($response);
                $stmt_insert->close();
                $mysqli->close();
                exit();
            }
        } catch (mysqli_sql_exception $e) {
            http_response_code(500); // Internal Server Error
            $response['status'] = 'error';
            $error_message_from_db = $e->getMessage();
            $error_code_from_db = $e->getCode();

            error_log("MySQLi Error: Code " . $error_code_from_db . " - Message: " . $error_message_from_db);
            $response['message'] = 'Database operation failed unexpectedly.';
            echo json_encode($response);
            $stmt_insert->close();
            $mysqli->close();
            exit();
        }

    } else {
        http_response_code(500); // Internal Server Error
        $response['status'] = 'error';
        $response['message'] = 'Error preparing SQL statement for student addition: ' . $mysqli->error;
        echo json_encode($response);
        $mysqli->close();
        exit();
    }
} else {
    http_response_code(405); // Method Not Allowed
    $response['status'] = 'error';
    $response['message'] = 'Invalid request method. Only POST requests are allowed.';
    echo json_encode($response);
    $mysqli->close();
    exit();
}

// If for some reason the script reaches here without exiting (should not happen with proper exits)
// Ensure the buffer is flushed and connection closed.
if (ob_get_length() > 0) {
    ob_end_flush();
}
if (isset($mysqli) && !$mysqli->connect_error) {
    $mysqli->close();
}
?>