<?php

require_once '../../db_connect.php'; 
require_once '../../verify_token.php'; 
header('Content-Type: application/json');
$userData = verifyJwtToken(); // $userData = ['userID', 'username', 'role'] 
$allowedRoles = ['Gestionnaire']; // Only Gestionnaire can add subjects.

if (!in_array($userData['role'], $allowedRoles)) {
    http_response_code(403); 
    echo json_encode(['status' => 'error', 'message' => 'Access denied. Only ' . implode('', $allowedRoles) . ' can add subjects.']);
    $mysqli->close();
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $input = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE || empty($input)) {
        $input = $_POST;
    }
    $titre = trim($input['titre'] ?? '');
    $description = trim($input['description'] ?? '');

    if (empty($titre) || empty($description)) {
        $response['status'] = 'error';
        $response['message'] = 'All fields (titre, description) are required for the new subject.';
        echo json_encode($response);
        $mysqli->close();
        exit();
    }

    $sql = "INSERT INTO Sujetsstage (titre, description) VALUES (?, ?)";

    if ($stmt = $mysqli->prepare($sql)) {
        $stmt->bind_param("ss", $param_titre, $param_description);
        $param_titre = $titre;
        $param_description = $description;
       
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