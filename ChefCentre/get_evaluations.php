<?php
// backend/ChefCentre/get_evaluations_to_validate.php

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
$allowedRoles = ['ChefCentreInformatique']; // Only Chef Centre can access this

if (!in_array($userData['role'], $allowedRoles)) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Access denied. Only ' . implode(', ', $allowedRoles) . ' can view evaluations to validate.']);
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "GET") {
    $sql = "
        SELECT
            e.evaluationID,
            e.stageID,
            e.encadrantID,
            e.dateEvaluation,
            e.note,
            e.commentaires,
            e.chefCentreValidationID,
            e.dateValidationChef,
            s.sujetID,
            sj.titre AS subjectTitle,
            s.typeStage,
            s.dateDebut,
            s.dateFin,
            s.statut,
            etu.username AS studentFirstName,
            etu.lastName AS studentLastName,
            etu.email AS studentEmail,
            u_encadrant.username AS encadrantUsername,
            u_encadrant.email AS encadrantEmail
        FROM
            evaluations e
        JOIN
            stages s ON e.stageID = s.stageID
        LEFT JOIN
            etudiants etu ON s.etudiantID = etu.etudiantID
        
        LEFT JOIN
            users u_encadrant ON e.encadrantID = u_encadrant.userID
        LEFT JOIN
            sujetsstage sj ON s.sujetID = sj.sujetID
        WHERE
            e.chefCentreValidationID IS NULL OR e.chefCentreValidationID = 0 -- Assuming 0 or NULL means not yet validated
        ORDER BY
            e.dateEvaluation ASC;
    ";

    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Database error preparing statement: ' . $mysqli->error]);
        $mysqli->close();
        exit();
    }

    $stmt->execute();
    $result = $stmt->get_result();

    $evaluations = [];
    while ($row = $result->fetch_assoc()) {
        $evaluation = [
            'evaluationID' => $row['evaluationID'],
            'stageID' => $row['stageID'],
            'encadrantID' => $row['encadrantID'],
            'dateEvaluation' => $row['dateEvaluation'],
            'note' => $row['note'],
            'commentaires' => $row['commentaires'],
            'chefCentreValidationID' => $row['chefCentreValidationID'],
            'dateValidationChef' => $row['dateValidationChef'],

            'internshipDetails' => [
                'subjectTitle' => $row['subjectTitle'],
                'typeStage' => $row['typeStage'],
                'dateDebut' => $row['dateDebut'],
                'dateFin' => $row['dateFin'],
                'statut' => $row['statut'],
            ],
            'studentDetails' => [
                'studentName' => (isset($row['studentFirstName']) && isset($row['studentLastName']))
                                 ? $row['studentFirstName'] . ' ' . $row['studentLastName']
                                 : null,
                'studentEmail' => $row['studentEmail'],
            ],
            'encadrantDetails' => [
                'encadrantUsername' => $row['encadrantUsername'],
                'encadrantEmail' => $row['encadrantEmail'],
            ]
        ];
        $evaluations[] = $evaluation;
    }

    $stmt->close();

    $response['status'] = 'success';
    $response['data'] = $evaluations;
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