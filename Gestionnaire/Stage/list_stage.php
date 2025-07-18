<?php
require_once '../../db_connect.php'; // Path to your database connection file
require_once '../../verify_token.php'; // Path to your JWT verification file

// CORS headers - crucial for Flutter Web
header("Access-Control-Allow-Origin: *"); // Allow all origins for development
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");header("Access-Control-Allow-Headers: Content-Type, Authorization"); // Allow Content-Type and Authorization headers
header("Access-Control-Max-Age: 3600"); // Cache preflight requests for 1 hour
header("Access-Control-Allow-Headers: Content-Type, Authorization");
// You're missing this line for OPTIONS requests:
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
$allowedRoles = ['Gestionnaire', 'Encadrant']; // Adjust roles as needed for fetching stages

if (!in_array($userData['role'], $allowedRoles)) {
    http_response_code(403); // Forbidden
    echo json_encode(['status' => 'error', 'message' => 'Access denied. You do not have permission to view stages.']);
    $mysqli->close();
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "GET") {
    // SQL query to fetch all stages with joined student, subject, and supervisor names
    // IMPORTANT: Adjust table and column names below to match your actual database schema.
    // I'm making assumptions based on common naming conventions and your previous images.
    // For example:
    // - 'etudiants' table has 'etudiantID', 'username' (for student name), 'lastname'
    // - 'sujets' table has 'sujetID', 'titre' (for subject title)
    // - 'users' table (for supervisors) has 'userID', 'username' (for supervisor name), 'lastname'
    $sql = "
        SELECT
            s.stageID,
            s.etudiantID,
            e.username AS studentUsername,  -- Alias for student's username
            e.lastname AS studentLastname,  -- Alias for student's lastname
            s.sujetID,
            sub.titre AS subjectTitle,      -- Alias for subject's title
            s.typeStage,
            s.dateDebut,
            s.dateFin,
            s.statut,
            s.estRemunere,
            s.montantRemuneration,
            s.encadrantProID,
            sup.username AS supervisorUsername, -- Alias for supervisor's username
            sup.lastname AS supervisorLastname, -- Alias for supervisor's lastname
            s.chefCentreValidationID
        FROM
            stages s
        LEFT JOIN
            etudiants e ON s.etudiantID = e.etudiantID
        LEFT JOIN
            sujetsstage sub ON s.sujetID = sub.sujetID
        LEFT JOIN
            users sup ON s.encadrantProID = sup.userID  -- Assuming supervisors are in the 'users' table
        ORDER BY s.dateDebut DESC
    ";

    if ($result = $mysqli->query($sql)) {
        $stages = array();
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                // Combine first and last names for convenience
                $row['studentName'] = trim($row['studentUsername'] . ' ' . $row['studentLastname']);
                $row['supervisorName'] = trim($row['supervisorUsername'] . ' ' . $row['supervisorLastname']);

                // Remove individual name parts if you only want the combined name in the final JSON
                unset($row['studentUsername']);
                unset($row['studentLastname']);
                unset($row['supervisorUsername']);
                unset($row['supervisorLastname']);

                $stages[] = $row;
            }
            $response['status'] = 'success';
            $response['message'] = 'Stages fetched successfully.';
            $response['data'] = $stages;
        } else {
            $response['status'] = 'success';
            $response['message'] = 'No stages found.';
            $response['data'] = []; // Return empty array if no stages
        }
        $result->free(); // Free result set
    } else {
        http_response_code(500); // Internal Server Error
        $response['status'] = 'error';
        $response['message'] = 'Database query failed: ' . $mysqli->error;
    }
} else {
    http_response_code(405); // Method Not Allowed
    $response['status'] = 'error';
    $response['message'] = 'Invalid request method. Only GET requests are allowed.';
}

$mysqli->close(); // Close database connection
echo json_encode($response);
?>