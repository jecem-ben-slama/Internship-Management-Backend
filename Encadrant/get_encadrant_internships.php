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

// Define allowed roles for accessing this endpoint
$allowedRoles = ['Encadrant'];

if (!in_array($userData['role'], $allowedRoles)) {
    http_response_code(403); // Forbidden
    $response = ['status' => 'error', 'message' => 'Access denied. Only ' . implode(', ', $allowedRoles) . ' can view assigned internships.'];
    echo json_encode($response);
    exit(); // Exit after sending response
}

// Get the logged-in Encadrant's ID from the token
$encadrantID = $userData['userID'];

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
            JOIN
                sujetsstage subj ON s.sujetID = subj.sujetID
            WHERE
                s.encadrantProID = ?
            ORDER BY
                s.dateDebut DESC;
        ";

        $stmt_internships = $mysqli->prepare($sql_internships);
        if (!$stmt_internships) {
            // Log the error for debugging purposes
            error_log("SQL Prepare Error (Internships): " . $mysqli->error);
            throw new Exception("Failed to prepare internship statement.");
        }
        $stmt_internships->bind_param("i", $encadrantID);
        $stmt_internships->execute();
        $result_internships = $stmt_internships->get_result();

        $internships = [];
        $stageIDs = [];
        while ($row = $result_internships->fetch_assoc()) {
            $internships[$row['stageID']] = $row;
            $internships[$row['stageID']]['notes'] = [];
            $stageIDs[] = $row['stageID'];
        }
        $stmt_internships->close();

        // 2. Fetch all notes related to these internships
        if (!empty($stageIDs)) {
            $placeholders = implode(',', array_fill(0, count($stageIDs), '?'));
            // Check if 'notes' table exists, if not, it might be 'stagenotes' as in your add_internship_note.php
            // Using 'stagenotes' for consistency based on your add_internship_note.php
            $sql_notes = "
                SELECT
                    noteID,
                    stageID,
                    encadrantID,
                    dateNote,
                    contenuNote
                FROM
                    stagenotes  -- Changed from 'notes' to 'stagenotes'
                WHERE
                    stageID IN ($placeholders)
                ORDER BY
                    dateNote ASC;
            ";
            $stmt_notes = $mysqli->prepare($sql_notes);
            if (!$stmt_notes) {
                error_log("SQL Prepare Error (Notes): " . $mysqli->error);
                throw new Exception("Failed to prepare notes statement.");
            }

            $types = str_repeat('i', count($stageIDs));
            $params = [];
            foreach ($stageIDs as &$id) {
                $params[] = &$id;
            }
            array_unshift($params, $types);
            call_user_func_array([$stmt_notes, 'bind_param'], $params);

            $stmt_notes->execute();
            $result_notes = $stmt_notes->get_result();

            while ($note = $result_notes->fetch_assoc()) {
                if (isset($internships[$note['stageID']])) {
                    $internships[$note['stageID']]['notes'][] = $note;
                }
            }
            $stmt_notes->close();
        }

        $response['status'] = 'success';
        $response['message'] = 'Internships fetched successfully.';
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

// Final output: this should be the ONLY echo json_encode not followed by an exit()
// within the main logic flow.
// All error/access denied cases should echo their response and exit() immediately.
// If a non-error path reaches here, it means it completed successfully and sets $response.
echo json_encode($response);

$mysqli->close(); // Close database connection
exit(); // Ensure the script terminates
?>