<?php
// Include necessary files
require_once '../db_connect.php';
require_once '../verify_token.php';

// PHPMailer Autoload (assuming you installed it via Composer in your 'backend' folder)
require '../vendor/autoload.php';

// Include your custom mailer utility
require_once 'send_email.php'; // Adjust path if send_email.php is in a different directory

// Import PHPMailer classes into the global namespace - still needed here if you access PHPMailer constants directly
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// CORS headers - crucial for Flutter Web
header("Access-Control-Allow-Origin: *"); // Allow all origins for development
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization"); // Allow Content-Type and Authorization headers
header("Access-Control-Max-Age: 3600"); // Cache preflight requests for 1 hour
header("Access-Control-Allow-Credentials: true");

// Handle preflight OPTIONS requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

header('Content-Type: application/json'); // Set content type to JSON

$response = array(); // Initialize response array

// Verify JWT token and get user data
$userData = verifyJwtToken(); // Expected to return ['userID', 'username', 'role'] or exit if invalid

// Define allowed roles for accessing this endpoint
$allowedRoles = ['ChefCentreInformatique']; // Only Chef is allowed to update internship status this way

if (!in_array($userData['role'], $allowedRoles)) {
    http_response_code(403); // Forbidden
    echo json_encode(['status' => 'error', 'message' => 'Access denied. You do not have permission to update internship status.']);
    $mysqli->close();
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "PUT") {
    // Get raw PUT data (JSON body)
    $input = file_get_contents('php://input');
    $data = json_decode($input, true); // Decode JSON into an associative array

    $internshipId = isset($data['stageID']) ? (int)$data['stageID'] : null;
    $newStatus = isset($data['statut']) ? $data['statut'] : null;

    if (!$internshipId) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Internship ID (stageID) is required in the request body.']);
        $mysqli->close();
        exit();
    }

    if (!$newStatus) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'New status is required in the request body.']);
        $mysqli->close();
        exit();
    }

    // Prepare the update statement for the database
    $stmt = $mysqli->prepare("UPDATE stages SET statut = ? WHERE stageID = ?");
    if ($stmt === false) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Failed to prepare update statement: ' . $mysqli->error]);
        $mysqli->close();
        exit();
    }
    $stmt->bind_param("si", $newStatus, $internshipId); // 's' for string, 'i' for integer

    // Execute the update
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            // If the status was successfully updated AND the new status is 'ACCEPTED'
            if ($newStatus === 'ACCEPTED') {
                $studentEmail = '';
                $studentName = '';
                $subjectTitle = '';

                // Fetch student's email, full name, and internship subject title
                $getEmailStmt = $mysqli->prepare("
                    SELECT
                        e.email,
                        e.username AS studentUsername,
                        e.lastname AS studentLastname,
                        s.titre AS subjectTitle
                    FROM
                        stages st
                    JOIN
                        etudiants e ON st.etudiantID = e.etudiantID
                    JOIN
                        sujetsstage s ON st.sujetID = s.sujetID
                    WHERE
                        st.stageID = ?
                ");
                if ($getEmailStmt) {
                    $getEmailStmt->bind_param("i", $internshipId);
                    $getEmailStmt->execute();
                    $emailResult = $getEmailStmt->get_result();
                    if ($emailResult->num_rows > 0) {
                        $row = $emailResult->fetch_assoc();
                        $studentEmail = $row['email'];
                        $studentName = trim($row['studentUsername'] . ' ' . $row['studentLastname']);
                        $subjectTitle = $row['subjectTitle'];

                        // Call the new function to send email
                        $emailSendResult = sendInternshipAcceptanceEmail($studentEmail, $studentName, $subjectTitle);
                        $response['email_status'] = $emailSendResult['message'];

                    } else {
                        $response['email_status'] = 'Student email not found for internship ID ' . $internshipId . '.';
                        error_log("Student email not found for internship ID " . $internshipId);
                    }
                    $getEmailStmt->close();
                } else {
                    $response['email_status'] = 'Failed to prepare statement to fetch student email: ' . $mysqli->error;
                    error_log("Failed to prepare statement to fetch student email: " . $mysqli->error);
                }
            }

            // Success response for the API call
            http_response_code(200);
            $response['status'] = 'success';
            $response['message'] = 'Internship status updated successfully.';
            $response['data'] = ['stageID' => $internshipId, 'statut' => $newStatus]; // Return minimal updated data
        } else {
            // No rows affected implies internship ID not found or status was already the same
            http_response_code(404); // Not Found
            $response['status'] = 'error';
            $response['message'] = 'Internship not found or status already matches the provided value.';
        }
    } else {
        // SQL execution failed
        http_response_code(500); // Internal Server Error
        $response['status'] = 'error';
        $response['message'] = 'Failed to execute update statement: ' . $stmt->error;
    }
    $stmt->close(); // Close the prepared statement
} else {
    http_response_code(405); // Method Not Allowed
    $response['status'] = 'error';
    $response['message'] = 'Invalid request method. Only PUT requests are allowed.';
}

$mysqli->close(); // Close database connection
echo json_encode($response); // Output the JSON response
?>