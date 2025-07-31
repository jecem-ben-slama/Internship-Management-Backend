<?php

// Ensure these paths are correct relative to this file's location
require_once '../db_connect.php'; // For mysqli connection
require_once '../verify_token.php'; // For JWT token verification

// Set headers for CORS
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS"); // Allow GET and OPTIONS methods
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Max-Age: 3600"); // Cache preflight requests for 1 hour
header("Access-Control-Allow-Credentials: true"); // Allow credentials if your frontend sends them

// Handle preflight OPTIONS requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Set Content-Type for JSON response
header('Content-Type: application/json');
$response = array(); // Initialize response array

// Verify JWT token and get user data
$userData = verifyJwtToken(); // This function should be defined in verify_token.php

// Define allowed roles for this specific endpoint (e.g., 'ChefCentreInformatique')
$allowedRoles = ['ChefCentreInformatique']; 

if (!in_array($userData['role'], $allowedRoles)) {
    http_response_code(403); // Forbidden
    echo json_encode(['status' => 'error', 'message' => 'Access denied. Only ' . implode(', ', $allowedRoles) . ' can view proposed internships.']);
    exit();
}

// Check if the request method is GET
if ($_SERVER["REQUEST_METHOD"] == "GET") {
    // Hardcode the status to 'Proposé' as this endpoint is specifically for proposed stages
    $targetStatus = 'Proposed';

    // Base SQL query
    $sql = "
        SELECT
            s.stageID,
            s.etudiantID,
            e.username AS studentUsername,
            e.lastname AS studentLastname,
            e.email AS studentEmail,
            s.sujetID,
            sub.titre AS subjectTitle,
            s.typeStage,
            s.dateDebut,
            s.dateFin,
            s.statut,
            s.estRemunere,
            s.montantRemuneration,
            s.encadrantProID,
            sup.username AS supervisorUsername,
            sup.lastname AS supervisorLastname,
            s.encadrantAcademiqueID
        FROM
            stages s
        LEFT JOIN
            etudiants e ON s.etudiantID = e.etudiantID
        LEFT JOIN
            sujetsstage sub ON s.sujetID = sub.sujetID
        LEFT JOIN
            users sup ON s.encadrantProID = sup.userID
        WHERE
            s.statut = ?  -- Filter directly by 'Proposed'
        ORDER BY s.dateDebut DESC
    ";

    // Prepare the statement with the hardcoded status
    $stmt = $mysqli->prepare($sql);
    if ($stmt === false) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Failed to prepare statement for proposed stages: ' . $mysqli->error]);
        $mysqli->close();
        exit();
    }

    // Bind the parameter for 'Proposé' status
    $stmt->bind_param('s', $targetStatus); // 's' for string

    // Execute the statement
    $stmt->execute();
    $result = $stmt->get_result();

    $stages = array();
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            // Combine student and supervisor names for the frontend
            $row['studentName'] = trim($row['studentUsername'] . ' ' . $row['studentLastname']);
            $row['supervisorName'] = trim($row['supervisorUsername'] . ' ' . $row['supervisorLastname']);
            $row['estRemunere'] = (bool)$row['estRemunere']; // Ensure boolean type for Dart

            // Remove redundant username/lastname fields if not needed in final response
            unset($row['studentUsername']);
            unset($row['studentLastname']);
            unset($row['supervisorUsername']);
            unset($row['supervisorLastname']);

            $stages[] = $row;
        }
        $response['status'] = 'success';
        $response['message'] = 'Proposed stages fetched successfully.';
        $response['data'] = $stages;
    } else {
        $response['status'] = 'info'; // Use 'info' for no content found, or 'success' with empty data
        $response['message'] = 'No proposed stages found.';
        $response['data'] = []; // Return empty array if no stages
    }

    $stmt->close(); // Close the prepared statement

} else {
    // Invalid request method
    http_response_code(405); // Method Not Allowed
    $response['status'] = 'error';
    $response['message'] = 'Invalid request method. Only GET requests are allowed.';
}

// Close the database connection
$mysqli->close();

// Output the JSON response
echo json_encode($response);
exit();

?>