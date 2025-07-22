<?php

require_once '../db_connect.php';
require_once '../verify_token.php';

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Credentials: true");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

header('Content-Type: application/json');
$response = array();

$userData = verifyJwtToken(); // Get user data from JWT
$allowedRoles = ['Gestionnaire']; // Only Gestionnaire can access this

if (!in_array($userData['role'], $allowedRoles)) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Access denied. Only ' . implode(', ', $allowedRoles) . ' can view KPI data.']);
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "GET") {
    $kpiData = [];

    // 1. Number of Active/Ongoing Internships
    // Assuming 'En Cours' is the status for ongoing internships.
    // You might want to include other statuses like 'Accepté' if they are also considered active before 'En Cours'.
    $sql_active_internships = "SELECT COUNT(*) AS activeInternshipsCount FROM stages WHERE statut = 'En Cours'";
    $stmt_active = $mysqli->prepare($sql_active_internships);
    if (!$stmt_active) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Database error (active internships): ' . $mysqli->error]);
        $mysqli->close();
        exit();
    }
    $stmt_active->execute();
    $result_active = $stmt_active->get_result();
    $row_active = $result_active->fetch_assoc();
    $kpiData['activeInternshipsCount'] = $row_active['activeInternshipsCount'];
    $stmt_active->close();

    // 2. Total Number of Encadrants Involved
    // Counts distinct users with role 'Encadrant'
    $sql_encadrants_count = "SELECT COUNT(DISTINCT userID) AS encadrantsCount FROM users WHERE role = 'Encadrant'";
    $stmt_encadrants = $mysqli->prepare($sql_encadrants_count);
    if (!$stmt_encadrants) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Database error (encadrants count): ' . $mysqli->error]);
        $mysqli->close();
        exit();
    }
    $stmt_encadrants->execute();
    $result_encadrants = $stmt_encadrants->get_result();
    $row_encadrants = $result_encadrants->fetch_assoc();
    $kpiData['encadrantsCount'] = $row_encadrants['encadrantsCount'];
    $stmt_encadrants->close();

    // 3. Number of Evaluations Pending Chef Centre Validation
    // Assuming NULL or 0 in chefCentreValidationID means pending validation.
    $sql_pending_evaluations = "SELECT COUNT(*) AS pendingEvaluationsCount FROM evaluations WHERE chefCentreValidationID IS NULL OR chefCentreValidationID = 0";
    $stmt_pending = $mysqli->prepare($sql_pending_evaluations);
    if (!$stmt_pending) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Database error (pending evaluations): ' . $mysqli->error]);
        $mysqli->close();
        exit();
    }
    $stmt_pending->execute();
    $result_pending = $stmt_pending->get_result();
    $row_pending = $result_pending->fetch_assoc();
    $kpiData['pendingEvaluationsCount'] = $row_pending['pendingEvaluationsCount'];
    $stmt_pending->close();

    $response['status'] = 'success';
    $response['data'] = $kpiData;
    echo json_encode($response);

} else {
    http_response_code(405);
    $response['status'] = 'error';
    $response['message'] = 'Invalid request method. Only GET requests are allowed.';
    echo json_encode($response);
}

$mysqli->close();
exit();
?>