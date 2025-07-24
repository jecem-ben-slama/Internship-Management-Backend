<?php

// 1. Include the CORS header file FIRST.
//    Adjust this path if your cors.php is in a different directory.

// 2. Include other necessary files
require_once '../../db_connect.php';
require_once '../../verify_token.php';

// 3. Set the Content-Type header for the response
header("Access-Control-Allow-Origin: *"); // Allow all origins for development. In production, specify your app's origin: e.g., "http://localhost:60847"
header("Access-Control-Allow-Methods:  POST, OPTIONS"); // Crucial: Add POST and OPTIONS methods
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With"); // Allow Content-Type, Authorization, and X-Requested-With headers
header("Access-Control-Max-Age: 3600"); // Cache preflight requests for 1 hour
header("Access-Control-Allow-Credentials: true");


// Initialize an empty response array
$response = [];

// Get user data from JWT token
$userData = verifyJwtToken(); // $userData = ['userID', 'username', 'role']
$allowedRoles = ['Gestionnaire']; // Only Gestionnaire can add subjects.

// Check if the user has the allowed role
if (!in_array($userData['role'], $allowedRoles)) {
    http_response_code(403); // Forbidden
    $response['status'] = 'error';
    $response['message'] = 'Access denied. Only ' . implode('', $allowedRoles) . ' can add subjects.';
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
    $titre = trim($input['titre'] ?? '');
    $description = trim($input['description'] ?? '');

    // Validate required fields
    if (empty($titre) || empty($description)) {
        $response['status'] = 'error';
        $response['message'] = 'All fields (titre, description) are required for the new subject.';
        echo json_encode($response);
        $mysqli->close();
        exit();
    }

    // Prepare the SQL INSERT statement
    $sql = "INSERT INTO Sujetsstage (titre, description) VALUES (?, ?)";

    if ($stmt = $mysqli->prepare($sql)) {
        // Bind parameters
        $stmt->bind_param("ss", $param_titre, $param_description);
        $param_titre = $titre;
        $param_description = $description;

        try {
            // Execute the statement
            if ($stmt->execute()) {
                $newSujetID = $mysqli->insert_id; // Get the ID of the newly inserted subject

                $response['status'] = 'success';
                $response['message'] = 'Subject added successfully!';
                // Crucial: Return the full subject data under the 'data' key
                $response['data'] = [
                    'sujetID' => (string)$newSujetID, // Convert to string to match Flutter's fromJson handling
                    'titre' => $titre,
                    'description' => $description
                ];
            } else {
                // If execution fails
                $response['status'] = 'error';
                $response['message'] = 'Database error during subject addition: ' . $stmt->error;
            }
        } catch (mysqli_sql_exception $e) {
            // Catch specific database exceptions (e.g., duplicate entry)
            $response['status'] = 'error';
            $error_message_from_db = $e->getMessage();
            $error_code_from_db = $e->getCode();
            if ($error_code_from_db == 1062) { // MySQL error code for duplicate entry
                $response['message'] = 'A subject with this title or similar details might already exist.';
            } else {
                $response['message'] = 'Database error during subject addition: ' . $error_message_from_db;
            }
        } finally {
            $stmt->close(); // Always close the statement
        }
    } else {
        // If preparing the statement fails
        $response['status'] = 'error';
        $response['message'] = 'Error preparing SQL statement for subject addition: ' . $mysqli->error;
    }
} else {
    // If the request method is not POST
    $response['status'] = 'error';
    $response['message'] = 'Invalid request method. Only POST requests are allowed.';
}

// Close the database connection and send the JSON response
$mysqli->close();
echo json_encode($response);
exit(); // Ensure no extra output
?>