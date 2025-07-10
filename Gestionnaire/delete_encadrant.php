<?php
// delete_encadrant.php
// Allows ONLY Gestionnaire users to delete an Encadrant user from the Users table, protected by JWT.

// Include necessary files. Paths are relative from BACKEND/Auth/
require_once '../db_connect.php';
require_once '../verify_token.php'; // Your JWT verification function

header('Content-Type: application/json');
$response = array();

// --- Authentication and Authorization Check ---
// Call the verification function. It will exit if the token is invalid or unauthorized.
$userData = verifyJwtToken(); // $userData will contain ['userID', 'username', 'role'] if token is valid.

// Define the ONLY role allowed to delete encadrants.
$allowedRoles = ['Gestionnaire']; // Only Gestionnaire can delete encadrants.

// Check if the authenticated user has the allowed role.
if (!in_array($userData['role'], $allowedRoles)) {
    http_response_code(403); // Forbidden
    echo json_encode(['status' => 'error', 'message' => 'Access denied. Only ' . implode('', $allowedRoles) . ' can delete encadrants.']);
    $mysqli->close(); // Close DB connection before exiting.
    exit();
}

// --- Process Request to Delete Encadrant (only if authenticated and authorized) ---
if ($_SERVER["REQUEST_METHOD"] == "DELETE") {

    // Get the userID from the URL query parameters (e.g., delete_encadrant.php?userID=123)
    $userID = $_GET['userID'] ?? null;

    // --- Input Validation ---
    if (empty($userID) || !is_numeric($userID)) {
        http_response_code(400); // Bad Request
        $response['status'] = 'error';
        $response['message'] = 'User ID is required and must be a valid number for deletion.';
        echo json_encode($response);
        $mysqli->close();
        exit();
    }

    $userID = (int)$userID; // Cast to integer to ensure correct type

    // --- Prepare SQL DELETE Statement ---
    // We specifically target users with the 'Encadrant' role to prevent accidental deletion
    // of other types of users (like other Gestionnaires, or Etudiants if they exist).
    $sql = "DELETE FROM Users WHERE userID = ? AND role = 'Encadrant'";

    if ($stmt = $mysqli->prepare($sql)) {
        // Bind parameter: 'i' for integer (userID).
        $stmt->bind_param("i", $userID);

        try {
            if ($stmt->execute()) {
                if ($stmt->affected_rows > 0) {
                    $response['status'] = 'success';
                    $response['message'] = 'Encadrant deleted successfully.';
                } else {
                    // If 0 rows were affected, it means either:
                    // 1. No user with that userID was found.
                    // 2. A user with that userID was found, but their role was NOT 'Encadrant'.
                    http_response_code(404); // Not Found
                    $response['status'] = 'error';
                    $response['message'] = 'Encadrant not found or the specified user is not an Encadrant.';
                }
            } else {
                http_response_code(500); // Internal Server Error
                $response['status'] = 'error';
                $response['message'] = 'Database error during deletion: ' . $stmt->error;
            }
        } catch (mysqli_sql_exception $e) {
            http_response_code(500); // Internal Server Error
            $response['status'] = 'error';
            $response['message'] = 'Database error during deletion: ' . $e->getMessage();
        }

        $stmt->close();
    } else {
        http_response_code(500); // Internal Server Error
        $response['status'] = 'error';
        $response['message'] = 'Error preparing SQL statement for encadrant deletion: ' . $mysqli->error;
    }
} else {
    http_response_code(405); // Method Not Allowed
    $response['status'] = 'error';
    $response['message'] = 'Invalid request method. Only DELETE requests are allowed.';
}

$mysqli->close();
echo json_encode($response);
?>