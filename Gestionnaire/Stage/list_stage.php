<?php
require_once '../../db_connect.php'; // Path to your database connection file
require_once '../../verify_token.php'; // Path to your JWT verification file

// CORS headers - crucial for Flutter Web
header("Access-Control-Allow-Origin: *"); // Allow all origins for development
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization"); // Allow Content-Type and Authorization headers
header("Access-Control-Max-Age: 3600"); // Cache preflight requests for 1 hour
// You're missing this line for OPTIONS requests (already in your original, just confirming):
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
// ChefCentreInformatique should be added here to allow them to fetch stages (e.g., pending)
$allowedRoles = ['Gestionnaire', 'Encadrant', 'ChefCentreInformatique']; // ADD ChefCentreInformatique

if (!in_array($userData['role'], $allowedRoles)) {
    http_response_code(403); // Forbidden
    echo json_encode(['status' => 'error', 'message' => 'Access denied. You do not have permission to view stages.']);
    $mysqli->close();
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "GET") {
    // Check if 'statut' query parameter is provided
    $filterStatus = isset($_GET['statut']) ? $_GET['statut'] : null;

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
            s.chefCentreValidationID
        FROM
            stages s
        LEFT JOIN
            etudiants e ON s.etudiantID = e.etudiantID
        LEFT JOIN
            sujetsstage sub ON s.sujetID = sub.sujetID
        LEFT JOIN
            users sup ON s.encadrantProID = sup.userID
    ";

    $whereClauses = [];
    $params = [];
    $paramTypes = '';

    // Add filtering by status if provided
    if ($filterStatus !== null && $filterStatus !== '') {
        $whereClauses[] = "s.statut = ?";
        $params[] = $filterStatus;
        $paramTypes .= 's'; // 's' for string
    }

    // Add WHERE clause if there are any conditions
    if (!empty($whereClauses)) {
        $sql .= " WHERE " . implode(" AND ", $whereClauses);
    }

    $sql .= " ORDER BY s.dateDebut DESC";

    // Prepare and execute the statement
    if (!empty($params)) {
        $stmt = $mysqli->prepare($sql);
        if ($stmt === false) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Failed to prepare statement: ' . $mysqli->error]);
            $mysqli->close();
            exit();
        }
        $stmt->bind_param($paramTypes, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        // No parameters, just execute the query
        $result = $mysqli->query($sql);
        if ($result === false) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Database query failed: ' . $mysqli->error]);
            $mysqli->close();
            exit();
        }
    }


    $stages = array();
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $row['studentName'] = trim($row['studentUsername'] . ' ' . $row['studentLastname']);
            $row['supervisorName'] = trim($row['supervisorUsername'] . ' ' . $row['supervisorLastname']);

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

    if (!empty($params)) {
        $stmt->close(); // Close statement if prepared
    } else {
        $result->free(); // Free result set if not prepared
    }

} else {
    http_response_code(405); // Method Not Allowed
    $response['status'] = 'error';
    $response['message'] = 'Invalid request method. Only GET requests are allowed.';
}

$mysqli->close(); // Close database connection
echo json_encode($response);
?>