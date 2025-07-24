<?php

// 1. Include the CORS header file FIRST.
// Adjust this path if your cors.php is in a different directory.
// require_once '../../cors.php'; // Assuming cors.php handles all CORS headers

// 2. Include other necessary files
require_once '../../db_connect.php';
require_once '../../verify_token.php';
header('Content-Type: application/json');   

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: PUT, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Credentials: true");

// Handle OPTIONS requests (pre-flight checks)
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Initialize an empty response array
$response = [];

// Get user data from JWT token
$userData = verifyJwtToken(); // $userData = ['userID', 'username', 'role']
$allowedRoles = ['Gestionnaire']; // Only Gestionnaire can edit subjects.

// Check if the user has the allowed role
if (!in_array($userData['role'], $allowedRoles)) {
    http_response_code(403); // Forbidden
    $response['status'] = 'error';
    $response['message'] = 'Access denied. Only ' . implode('', $allowedRoles) . ' can edit subjects.';
    echo json_encode($response);
    $mysqli->close();
    exit();
}

// Check if the request method is POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Decode JSON input from the request body (Flutter sends JSON in the body)
    $input = json_decode(file_get_contents('php://input'), true);

    // Fallback for form-urlencoded or if JSON decoding fails (less likely with Dio)
    if (json_last_error() !== JSON_ERROR_NONE || empty($input)) {
        $input = $_POST; // Use $_POST for form-urlencoded data
    }

    // Sanitize and get input values
    $sujetID = filter_var($input['sujetID'] ?? null, FILTER_VALIDATE_INT); // Get subject ID for update
    $titre = trim($input['titre'] ?? '');
    $description = trim($input['description'] ?? '');

    // Validate required fields for an update
    if ($sujetID === false || $sujetID === null) {
        http_response_code(400); // Bad Request
        $response['status'] = 'error';
        $response['message'] = 'Subject ID is required for updating a subject.';
        echo json_encode($response);
        $mysqli->close();
        exit();
    }

    if (empty($titre) || empty($description)) {
        http_response_code(400); // Bad Request
        $response['status'] = 'error';
        $response['message'] = 'All fields (titre, description) are required for subject update.';
        echo json_encode($response);
        $mysqli->close();
        exit();
    }

    // Prepare the SQL UPDATE statement
    $sql = "UPDATE Sujetsstage SET titre = ?, description = ? WHERE sujetID = ?";

    if ($stmt = $mysqli->prepare($sql)) {
        // Bind parameters: 'ssi' for two strings (titre, description) and one integer (sujetID)
        $stmt->bind_param("ssi", $param_titre, $param_description, $param_sujetID);
        $param_titre = $titre;
        $param_description = $description;
        $param_sujetID = $sujetID;

        try {
            // --- NEW LOGIC START ---
            // First, check if the subject exists. This helps differentiate between
            // "ID not found" and "ID found but no changes applied".
            $check_sql = "SELECT sujetID FROM Sujetsstage WHERE sujetID = ?";
            if ($check_stmt = $mysqli->prepare($check_sql)) {
                $check_stmt->bind_param("i", $param_sujetID);
                $check_stmt->execute();
                $check_stmt->store_result();

                if ($check_stmt->num_rows === 0) {
                    http_response_code(404); // Not Found
                    $response['status'] = 'error';
                    $response['message'] = 'Subject with ID ' . $sujetID . ' not found.';
                    $check_stmt->close();
                    $stmt->close(); // Close the update statement as well
                    $mysqli->close();
                    exit();
                }
                $check_stmt->close();
            } else {
                http_response_code(500);
                $response['status'] = 'error';
                $response['message'] = 'Error preparing subject existence check: ' . $mysqli->error;
                $stmt->close();
                $mysqli->close();
                exit();
            }
            // --- NEW LOGIC END ---

            // Now, attempt to execute the update
            if ($stmt->execute()) {
                // If affected_rows is 0, it means the record was found but the values were the same.
                // Since we already confirmed the subject exists, we treat this as a success.
                $response['status'] = 'success';
                $response['message'] = 'Subject updated successfully!';
                if ($stmt->affected_rows === 0) {
                     $response['message'] = 'Subject updated successfully (no changes applied as values were identical).';
                }
                // Return the full updated subject data under the 'data' key
                $response['data'] = [
                    'sujetID' => (string)$sujetID, // Convert to string to match Flutter's fromJson handling
                    'titre' => $titre,
                    'description' => $description
                ];
            } else {
                // If execution fails
                http_response_code(500); // Internal Server Error
                $response['status'] = 'error';
                $response['message'] = 'Database error during subject update: ' . $stmt->error;
            }
        } catch (mysqli_sql_exception $e) {
            http_response_code(500); // Internal Server Error
            $response['status'] = 'error';
            $error_message_from_db = $e->getMessage();
            $error_code_from_db = $e->getCode();
            if ($error_code_from_db == 1062) { // MySQL error code for duplicate entry (e.g., if 'titre' is unique)
                $response['message'] = 'A subject with this title might already exist.';
            } else {
                $response['message'] = 'Database error during subject update: ' . $error_message_from_db;
            }
        } finally {
            $stmt->close(); // Always close the statement
        }
    } else {
        // If preparing the statement fails
        http_response_code(500); // Internal Server Error
        $response['status'] = 'error';
        $response['message'] = 'Error preparing SQL statement for subject update: ' . $mysqli->error;
    }
} else {
    // If the request method is not POST
    http_response_code(405); // Method Not Allowed
    $response['status'] = 'error';
    $response['message'] = 'Invalid request method. Only POST requests are allowed.';
}

// Close the database connection and send the JSON response
$mysqli->close();
echo json_encode($response);
exit(); // Ensure no extra output

?>