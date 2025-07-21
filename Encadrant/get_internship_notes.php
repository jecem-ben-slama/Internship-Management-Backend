<?php
// Encadrant/get_internship_notes.php

require_once '../db_connect.php'; // Adjust path as necessary
require_once '../verify_token.php'; // Adjust path as necessary

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS"); // Allow GET method for fetching data
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Credentials: true");

// Handle OPTIONS request for CORS preflight
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

header('Content-Type: application/json');
$response = array();

$userData = verifyJwtToken(); // Get user data from JWT
$allowedRoles = ['Encadrant'];

if (!in_array($userData['role'], $allowedRoles)) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Access denied. Only ' . implode(', ', $allowedRoles) . ' can view notes.']);
    $mysqli->close();
    exit();
}

$encadrantID = $userData['userID']; // The ID of the logged-in Encadrant

if ($_SERVER["REQUEST_METHOD"] == "GET") {
    $stageID = $_GET['stageID'] ?? null;

    if (empty($stageID) || !is_numeric($stageID)) {
        http_response_code(400);
        $response = ['status' => 'error', 'message' => 'stageID is required and must be a valid integer.'];
        echo json_encode($response);
        $mysqli->close();
        exit();
    }
    $stageID = (int)$stageID; // Ensure stageID is an integer

    // Optional but recommended: Verify that this internship is actually assigned to this Encadrant
    // Using 'stages' table (as per your example) and 'encadrantProID'
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
    $stmt_check->store_result(); // Store result to check num_rows
    if ($stmt_check->num_rows == 0) {
        http_response_code(403); // Forbidden: Internship not found or not assigned to this encadrant
        echo json_encode(['status' => 'error', 'message' => 'You are not assigned to this internship or it does not exist.']);
        $stmt_check->close();
        $mysqli->close();
        exit();
    }
    $stmt_check->close();


    // Fetch the notes from 'stagenotes' table (as per your example)
    // and join with 'utilisateur' to get the encadrant's name
    $sql_fetch_notes = "SELECT sn.noteID, sn.stageID, sn.encadrantID, sn.dateNote, sn.contenuNote, u.username AS encadrantName
                        FROM stagenotes sn
                        JOIN users u ON sn.encadrantID = u.userID
                        WHERE sn.stageID = ?
                        ORDER BY sn.dateNote DESC"; // Order by newest notes first

    if ($stmt_fetch = $mysqli->prepare($sql_fetch_notes)) {
        $stmt_fetch->bind_param("i", $stageID);

        try {
            if ($stmt_fetch->execute()) {
                $result = $stmt_fetch->get_result();
                $notes = [];
                while ($row = $result->fetch_assoc()) {
                    $notes[] = $row;
                }
                $response['status'] = 'success';
                $response['message'] = 'Notes retrieved successfully!';
                $response['data'] = $notes;
                echo json_encode($response);
            } else {
                http_response_code(500);
                $response['status'] = 'error';
                $response['message'] = 'Database error fetching notes: ' . $stmt_fetch->error;
                echo json_encode($response);
            }
        } catch (mysqli_sql_exception $e) {
            http_response_code(500);
            $response['status'] = 'error';
            error_log("MySQLi Error (Get Notes): Code " . $e->getCode() . " - Message: " . $e->getMessage());
            $response['message'] = 'Database error fetching notes: An unexpected error occurred.';
            echo json_encode($response);
        }
        $stmt_fetch->close();
    } else {
        http_response_code(500);
        $response['status'] = 'error';
        $response['message'] = 'Error preparing SQL statement for notes retrieval: ' . $mysqli->error;
        echo json_encode($response);
    }
} else {
    http_response_code(405); // Method Not Allowed
    $response['status'] = 'error';
    $response['message'] = 'Invalid request method. Only GET requests are allowed.';
    echo json_encode($response);
}

$mysqli->close();
exit(); // Ensure the script terminates after sending response
?>