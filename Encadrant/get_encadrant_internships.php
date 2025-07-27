<?php
ob_start(); // Start output buffering at the very beginning

require_once '../db_connect.php'; // Path to your database connection file (make sure $mysqli is initialized here)
require_once '../verify_token.php'; // Path to your JWT verification file

// CORS headers
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Credentials: true");

// Handle preflight OPTIONS requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    ob_end_clean(); // Clean any buffer output before exiting
    exit(); // Terminate script after sending preflight headers
}

header('Content-Type: application/json'); // Set content type to JSON
$response = array(); // Initialize response array

// Verify JWT token and get user data
$userData = verifyJwtToken(); // Expected to return ['userID', 'username', 'role'] or exit if invalid

// --- DEBUGGING: Log the user data from the token ---
// This will help you confirm which user's data is being processed.
error_log("User Data from Token: " . json_encode($userData));
// --- END DEBUGGING ---

// Define allowed roles for accessing this endpoint
// Added 'Admin' as a common role that might also need to view this data.
$allowedRoles = ['Encadrant', 'Admin'];

if (!in_array($userData['role'], $allowedRoles)) {
    http_response_code(403); // Forbidden
    $response = ['status' => 'error', 'message' => 'Access denied. Only ' . implode(', ', $allowedRoles) . ' can view assigned internships.'];
    echo json_encode($response);
    exit(); // Exit after sending response
}

// Get the logged-in Encadrant's ID from the token
$encadrantID = $userData['userID'];

// --- DEBUGGING: Log the encadrantID being used for the query ---
// This confirms the ID that the SQL query will filter by.
error_log("Encadrant ID from token for query: " . $encadrantID);
// --- END DEBUGGING ---

// Ensure $mysqli is connected
if (!isset($mysqli) || $mysqli->connect_error) {
    http_response_code(500); // Internal Server Error
    $response = ['status' => 'error', 'message' => 'Database connection failed: ' . ($mysqli->connect_error ?? 'Unknown error')];
    echo json_encode($response);
    exit(); // Exit after sending response
}

if ($_SERVER["REQUEST_METHOD"] == "GET") {
    try {
        // 1. Fetch all internships assigned to this Encadrant
        $sql_internships = "
            SELECT
                s.stageID,
                s.typeStage,
                s.dateDebut,
                s.dateFin,
                s.statut,
                s.estRemunere,
                s.montantRemuneration,
                s.etudiantID,
                s.sujetID,
                s.encadrantProID,
                e.username AS studentFirstName, -- Assuming 'username' is first name in etudiants table
                e.lastname AS studentLastName,  -- Assuming 'lastname' is last name in etudiants table
                e.email AS studentEmail,
                subj.titre AS subjectTitle
            FROM
                stages s
            JOIN
                etudiants e ON s.etudiantID = e.etudiantID
            LEFT JOIN  -- *** CRITICAL CHANGE: Changed from INNER JOIN to LEFT JOIN ***
                sujetsstage subj ON s.sujetID = subj.sujetID
            WHERE
                s.encadrantProID = ?
            ORDER BY
                s.dateDebut DESC;
        ";

        $stmt_internships = $mysqli->prepare($sql_internships);
        if (!$stmt_internships) {
            // Log the detailed error for debugging purposes
            error_log("SQL Prepare Error (Internships): " . $mysqli->error);
            throw new Exception("Failed to prepare internship statement: " . $mysqli->error);
        }
        $stmt_internships->bind_param("i", $encadrantID);
        $stmt_internships->execute();
        $result_internships = $stmt_internships->get_result();

        $internships = [];
        $stageIDs = []; // To collect stageIDs for fetching notes
        while ($row = $result_internships->fetch_assoc()) {
            $internships[$row['stageID']] = $row;
            $internships[$row['stageID']]['notes'] = []; // Initialize notes array for each internship
            $stageIDs[] = $row['stageID'];
        }
        $stmt_internships->close();

        // --- DEBUGGING: Log fetched internships count ---
        error_log("Fetched " . count($internships) . " internships for encadrant ID " . $encadrantID);
        // --- END DEBUGGING ---

        // 2. Fetch all notes related to these internships
        if (!empty($stageIDs)) {
            // Create placeholders for the IN clause based on the number of stageIDs
            $placeholders = implode(',', array_fill(0, count($stageIDs), '?'));
            $sql_notes = "
                SELECT
                    noteID,
                    stageID,
                    encadrantID,
                    dateNote,
                    contenuNote
                FROM
                    stagenotes  -- Using 'stagenotes' as per your add_internship_note.php
                WHERE
                    stageID IN ($placeholders)
                ORDER BY
                    dateNote ASC;
            ";
            $stmt_notes = $mysqli->prepare($sql_notes);
            if (!$stmt_notes) {
                error_log("SQL Prepare Error (Notes): " . $mysqli->error);
                throw new Exception("Failed to prepare notes statement: " . $mysqli->error);
            }

            // Dynamically bind parameters for the IN clause
            $types = str_repeat('i', count($stageIDs)); // 'i' for integer for each ID
            $params = [];
            // Use references for bind_param to work correctly with call_user_func_array
            foreach ($stageIDs as &$id) {
                $params[] = &$id;
            }
            array_unshift($params, $types); // Prepend the types string to the parameters array
            call_user_func_array([$stmt_notes, 'bind_param'], $params);

            $stmt_notes->execute();
            $result_notes = $stmt_notes->get_result();

            while ($note = $result_notes->fetch_assoc()) {
                // Attach the note to the correct internship
                if (isset($internships[$note['stageID']])) {
                    $internships[$note['stageID']]['notes'][] = $note;
                }
            }
            $stmt_notes->close();
        }

        $response['status'] = 'success';
        $response['message'] = 'Internships fetched successfully.';
        // Use array_values to convert associative array (keyed by stageID) to a simple numeric array
        $response['data'] = array_values($internships);

    } catch (Exception $e) {
        http_response_code(500);
        $response['status'] = 'error';
        $response['message'] = 'Database error fetching internships: ' . $e->getMessage();
        error_log("Encadrant Internship Fetch Error (Caught): " . $e->getMessage()); // Log detailed error
    }
} else {
    http_response_code(405);
    $response['status'] = 'error';
    $response['message'] = 'Invalid request method. Only GET requests are allowed.';
}

// Final output: This should be the ONLY echo json_encode not followed by an exit()
// within the main logic flow. All error/access denied cases should echo their response and exit() immediately.
// If a non-error path reaches here, it means it completed successfully and sets $response.
echo json_encode($response);

$mysqli->close(); // Close database connection
exit(); // Ensure the script terminates
?>