<?php
// Gestionnaire/get_attestation_data.php

require_once '../db_connect.php';
require_once '../verify_token.php';

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS"); // Use POST for sending stageID
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
    echo json_encode(['status' => 'error', 'message' => 'Access denied. Only ' . implode(', ', $allowedRoles) . ' can perform this action.']);
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $input = json_decode(file_get_contents('php://input'), true);

    if (json_last_error() !== JSON_ERROR_NONE || !is_array($input) || empty($input)) {
        http_response_code(400);
        $response = ['status' => 'error', 'message' => 'Invalid or empty JSON body.'];
        echo json_encode($response);
        $mysqli->close();
        exit();
    }

    $stageID = $input['stageID'] ?? null;

    if (empty($stageID) || !is_numeric($stageID)) {
        http_response_code(400);
        $response = ['status' => 'error', 'message' => 'stageID is required and must be a valid number.'];
        echo json_encode($response);
        $mysqli->close();
        exit();
    }
    $stageID = (int)$stageID;

    // SQL query to fetch all necessary data for the attestation
    // Joins stages, etudiants, users (for encadrant), sujets, and evaluations
    $sql = "
        SELECT
            s.stageID, s.typeStage, s.dateDebut, s.dateFin, s.statut, s.estRemunere, s.montantRemuneration,
            e.etudiantID, e.username AS studentFirstName, e.lastname AS studentLastName, e.email AS studentEmail,
            su.sujetID, su.titre AS subjectTitle, su.description AS subjectDescription,
            u.userID AS encadrantID, u.username AS encadrantFirstName, u.lastname AS encadrantLastName, u.email AS encadrantEmail,
            ev.evaluationID, ev.note, ev.commentaires, ev.dateEvaluation
        FROM stages s
        JOIN etudiants e ON s.etudiantID = e.etudiantID
        LEFT JOIN sujetsstage su ON s.sujetID = su.sujetID
        LEFT JOIN users u ON s.encadrantProID = u.userID
        LEFT JOIN evaluations ev ON s.stageID = ev.stageID AND s.encadrantProID = ev.encadrantID
        WHERE s.stageID = ? AND s.statut = 'Validated'
    ";

    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        http_response_code(500);
        $response = ['status' => 'error', 'message' => 'Database error preparing query: ' . $mysqli->error];
        echo json_encode($response);
        $mysqli->close();
        exit();
    }

    $stmt->bind_param("i", $stageID);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();

        // Check if evaluation data exists for this internship
        if ($row['note'] === null || $row['commentaires'] === null) {
            http_response_code(404);
            $response = ['status' => 'error', 'message' => 'Internship is not fully evaluated or not marked as "Terminé".'];
            echo json_encode($response);
            $stmt->close();
            $mysqli->close();
            exit();
        }

        $attestationData = [
            'internship' => [
                'stageID' => $row['stageID'],
                'typeStage' => $row['typeStage'],
                'dateDebut' => $row['dateDebut'],
                'dateFin' => $row['dateFin'],
                'statut' => $row['statut'],
                'estRemunere' => (bool)$row['estRemunere'],
                'montantRemuneration' => $row['montantRemuneration'] !== null ? (float)$row['montantRemuneration'] : null,
            ],
            'student' => [
                'studentID' => $row['etudiantID'],
                'firstName' => $row['studentFirstName'],
                'lastName' => $row['studentLastName'],
                'email' => $row['studentEmail'],
            ],
            'subject' => [
                'subjectID' => $row['sujetID'],
                'title' => $row['subjectTitle'],
                'description' => $row['subjectDescription'],
            ],
            'supervisor' => [
                'supervisorID' => $row['encadrantID'],
                'firstName' => $row['encadrantFirstName'],
                'lastName' => $row['encadrantLastName'],
                'email' => $row['encadrantEmail'],
            ],
            'evaluation' => [
                'evaluationID' => $row['evaluationID'],
                'note' => (float)$row['note'],
                'comments' => $row['commentaires'],
                'dateEvaluation' => $row['dateEvaluation'],
            ]
        ];

        $response = ['status' => 'success', 'message' => 'Attestation data fetched successfully.', 'data' => $attestationData];
        http_response_code(200);
    } else {
        http_response_code(404);
        $response = ['status' => 'error', 'message' => 'Internship not found or not yet terminated/evaluated.'];
    }

    $stmt->close();
} else {
    http_response_code(405);
    $response['status'] = 'error';
    $response['message'] = 'Invalid request method. Only POST requests are allowed.';
}

echo json_encode($response);
$mysqli->close();
exit();
?>