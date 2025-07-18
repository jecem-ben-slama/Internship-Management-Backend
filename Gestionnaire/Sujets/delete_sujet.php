<?php
// delete_encadrant.php
// Allows ONLY Gestionnaire sujets to delete an Encadrant sujet from the sujets table, protected by JWT.

// Include necessary files. Paths are relative from BACKEND/Auth/
require_once '../db_connect.php';
require_once '../verify_token.php'; // Your JWT verification function

header('Content-Type: application/json');
$response = array();

// --- Authentication and Authorization Check ---
// Call the verification function. It will exit if the token is invalid or unauthorized.
$sujetData = verifyJwtToken(); // $sujetData will contain ['sujetID', 'sujetname', 'role'] if token is valid.

// Define the ONLY role allowed to delete encadrants.
$allowedRoles = ['Gestionnaire']; // Only Gestionnaire can delete encadrants.

// Check if the authenticated sujet has the allowed role.
if (!in_array($sujetData['role'], $allowedRoles)) {
    http_response_code(403); // Forbidden
    echo json_encode(['status' => 'error', 'message' => 'Access denied. Only ' . implode('', $allowedRoles) . ' can delete encadrants.']);
    $mysqli->close(); // Close DB connection before exiting.
    exit();
}

// --- Process Request to Delete Encadrant (only if authenticated and authorized) ---
if ($_SERVER["REQUEST_METHOD"] == "DELETE") {

    // Get the sujetID from the URL query parameters (e.g., delete_encadrant.php?sujetID=123)
    $sujetID = $_GET['sujetID'] ?? null;

    // --- Input Validation ---
    if (empty($sujetID) || !is_numeric($sujetID)) {
        http_response_code(400); // Bad Request
        $response['status'] = 'error';
        $response['message'] = 'sujet ID is required and must be a valid number for deletion.';
        echo json_encode($response);
        $mysqli->close();
        exit();
    }

    $sujetID = (int)$sujetID; // Cast to integer to ensure correct type

    // --- Prepare SQL DELETE Statement ---
    // We specifically target sujets with the 'Encadrant' role to prevent accidental deletion
    // of other types of sujets (like other Gestionnaires, or Etudiants if they exist).
    $sql = "DELETE FROM sujetsstage WHERE sujetID = ? ";

    if ($stmt = $mysqli->prepare($sql)) {
        // Bind parameter: 'i' for integer (sujetID).
        $stmt->bind_param("i", $sujetID);

        try {
            if ($stmt->execute()) {
                if ($stmt->affected_rows > 0) {
                    $response['status'] = 'success';
                    $response['message'] = 'sujet deleted successfully.';
                } else {
                    // If 0 rows were affected, it means either:
                    // 1. No sujet with that sujetID was found.
                    // 2. A sujet with that sujetID was found, but their role was NOT 'Encadrant'.
                    http_response_code(404); // Not Found
                    $response['status'] = 'error';
                    $response['message'] = 'sujet not found or the specified sujet is not an Encadrant.';
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