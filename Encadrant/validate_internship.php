<?php

require_once '../db_connect.php';
require_once '../verify_token.php';

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Credentials: true");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

header('Content-Type: application/json');
$response = array(); // Initialize response array here

$userData = verifyJwtToken(); // Get user data from JWT
$allowedRoles = ['Encadrant'];

if (!in_array($userData['role'], $allowedRoles)) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Access denied. Only ' . implode(', ', $allowedRoles) . ' can validate internships.']);
    exit();
}

$encadrantID = $userData['userID']; // The ID of the logged-in Encadrant

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $input = json_decode(file_get_contents('php://input'), true);

    if (json_last_error() !== JSON_ERROR_NONE || !is_array($input) || empty($input)) {
        http_response_code(400);
        $response = ['status' => 'error', 'message' => 'Invalid or empty JSON body.'];
        echo json_encode($response);
        $mysqli->close();
        exit();
    }

    $stageID = $input['stageID'] ?? null;

    if (empty($stageID) || !is_numeric($stageID)) {
        http_response_code(400);
        $response = ['status' => 'error', 'message' => 'stageID is required and must be numeric.'];
        echo json_encode($response);
        $mysqli->close();
        exit();
    }
    $stageID = (int)$stageID; // Ensure stageID is an integer

    // Verify that this internship is actually assigned to this Encadrant
    $sql_check_assignment = "SELECT stageID FROM stages WHERE stageID = ? AND encadrantProID = ?";
    $stmt_check = $mysqli->prepare($sql_check_assignment);
    if (!$stmt_check) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Database error checking internship assignment: ' . $mysqli->error]);
        $mysqli->close();
        exit();
    }
    $stmt_check->bind_param("ii", $stageID, $encadrantID);
    $stmt_check->execute();
    $stmt_check->store_result();
    if ($stmt_check->num_rows == 0) {
        http_response_code(403); // Forbidden: Internship not found or not assigned to this encadrant
        echo json_encode(['status' => 'error', 'message' => 'You are not assigned to this internship or it does not exist.']);
        $stmt_check->close();
        $mysqli->close();
        exit();
    }
    $stmt_check->close();

    // Update the internship's 'statut' to 'Validé'
    $newStatus = 'Validé'; // Define the string you want to set for validated internships
    $sql_update = "UPDATE stages SET statut = ? WHERE stageID = ?";
    if ($stmt_update = $mysqli->prepare($sql_update)) {
        $stmt_update->bind_param("si", $newStatus, $stageID); // s: string, i: integer
        try {
            if ($stmt_update->execute()) {
                if ($stmt_update->affected_rows > 0) {
                    $response['status'] = 'success';
                    $response['message'] = 'Internship status updated to "' . $newStatus . '" successfully!';
                } else {
                    $response['status'] = 'info';
                    $response['message'] = 'Internship already has the status "' . $newStatus . '" or no changes made.';
                }
                // *** THIS IS THE MISSING LINE! ***
                echo json_encode($response); // Send response on success/info
            } else {
                http_response_code(500);
                $response['status'] = 'error';
                $response['message'] = 'Database error updating internship status: ' . $stmt_update->error;
                echo json_encode($response); // Send response on error
            }
        } catch (mysqli_sql_exception $e) {
            http_response_code(500);
            $response['status'] = 'error';
            error_log("MySQLi Error (Validate Internship): Code " . $e->getCode() . " - Message: " . $e->getMessage());
            $response['message'] = 'Database error validating internship: An unexpected error occurred.';
            echo json_encode($response); // Send response on exception
        }
        $stmt_update->close();
    } else {
        http_response_code(500);
        $response['status'] = 'error';
        $response['message'] = 'Error preparing SQL statement for internship validation: ' . $mysqli->error;
        echo json_encode($response); // Send response on prepare error
    }
} else {
    http_response_code(405); // Method Not Allowed
    $response['status'] = 'error';
    $response['message'] = 'Invalid request method. Only POST requests are allowed.';
    echo json_encode($response); // Send response for wrong method
}

$mysqli->close();
exit(); // Ensure the script terminates after sending response
?>