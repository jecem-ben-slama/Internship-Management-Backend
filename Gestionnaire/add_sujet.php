<?php
// add_sujet.php
// Allows ONLY Gestionnaire users to add new subjects, protected by JWT.
// This version does NOT store the ID of the user who added the subject.

// Include necessary files. Paths are relative from BACKEND/Auth/
require_once '../db_connect.php'; 
require_once '../verify_token.php'; // Your JWT verification function

header('Content-Type: application/json');
$response = array();

// --- Authentication and Authorization Check ---
// Call the verification function. It will exit if the token is invalid or unauthorized.
$userData = verifyJwtToken(); // $userData will contain ['userID', 'username', 'role'] if token is valid.

// Define the ONLY role allowed to add subjects.
$allowedRoles = ['Gestionnaire']; // Only Gestionnaire can add subjects.

// Check if the authenticated user has the allowed role.
if (!in_array($userData['role'], $allowedRoles)) {
    http_response_code(403); // Forbidden
    echo json_encode(['status' => 'error', 'message' => 'Access denied. Only ' . implode('', $allowedRoles) . ' can add subjects.']);
    $mysqli->close(); // Close DB connection before exiting.
    exit();
}

// *** No need for $loggedInUserID here, as it's not being stored in the Sujets table ***

// --- Process Request to Add Sujet (only if authenticated and authorized) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $input = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE || empty($input)) {
        $input = $_POST;
    }

    // Retrieve and sanitize input values for the new Sujet.
    $titre = trim($input['titre'] ?? '');
    $description = trim($input['description'] ?? '');
    // You might also have other fields like 'keywords', 'niveau', etc.

    // --- Input Validation for the NEW Sujet's Data ---
    if (empty($titre) || empty($description)) {
        $response['status'] = 'error';
        $response['message'] = 'All fields (titre, description) are required for the new subject.';
        echo json_encode($response);
        $mysqli->close();
        exit();
    }

    // --- Prepare SQL INSERT Statement to add the new Sujet ---
    // The query now only includes the columns present in your Sujets table (titre, description, domaine).
    $sql = "INSERT INTO Sujetsstage (titre, description) VALUES (?, ?)";

    if ($stmt = $mysqli->prepare($sql)) {
        // Bind parameters: 'sss' for three string parameters (titre, description, domaine).
        $stmt->bind_param("ss", $param_titre, $param_description);

        $param_titre = $titre;
        $param_description = $description;
       
        // No $param_creator_id needed here.

        try {
            if ($stmt->execute()) {
                $response['status'] = 'success';
                $response['message'] = 'Subject added successfully!';
                $response['sujetID'] = $mysqli->insert_id; 
            } else {
                $response['status'] = 'error';
                $response['message'] = 'Database error during subject addition: ' . $stmt->error;
            }
        } catch (mysqli_sql_exception $e) {
            $response['status'] = 'error';
            $error_message_from_db = $e->getMessage();
            $error_code_from_db = $e->getCode();

            // Handle specific database errors like duplicate entries if 'titre' is unique, etc.
            if ($error_code_from_db == 1062) { 
                $response['message'] = 'A subject with this title or similar details might already exist.';
            } else {
                $response['message'] = 'Database error during subject addition: ' . $error_message_from_db;
            }
        }

        $stmt->close();
    } else {
        $response['status'] = 'error';
        $response['message'] = 'Error preparing SQL statement for subject addition: ' . $mysqli->error;
    }
} else {
    $response['status'] = 'error';
    $response['message'] = 'Invalid request method. Only POST requests are allowed.';
}

$mysqli->close();
echo json_encode($response);
?>