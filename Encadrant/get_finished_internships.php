<?php
// Encadrant/get_finished_internships.php

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

$userData = verifyJwtToken();
$allowedRoles = ['Encadrant'];

if (!in_array($userData['role'], $allowedRoles)) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Access denied. Only ' . implode(', ', $allowedRoles) . ' can view finished internships.']);
    exit();
}

$encadrantID = $userData['userID'];
$currentDate = date('Y-m-d');

if ($_SERVER["REQUEST_METHOD"] == "GET") {
    $sql = "
        SELECT
            s.stageID,
            s.etudiantID,
            s.sujetID,
            sj.titre AS subjectTitle,
            s.typeStage,
            s.dateDebut,
            s.dateFin,
            s.statut,
            s.estRemunere,
            s.montantRemuneration,
            s.encadrantProID,
            s.encadrantAcademiqueID AS stageChefCentreValidationID,

            etu.username AS studentFirstName,
            etu.lastName AS studentLastName,

            u_encadrant_pro.username AS supervisorName,
            u_encadrant_pro.username AS encadrantProName,

            e.evaluationID,
            e.dateEvaluation,
            e.note,
            e.commentaires,
            e.chefCentreValidationID AS evaluationChefCentreValidationID,
            e.dateValidationChef,
            
            -- --- START OF MODIFICATION ---
            -- Add the new evaluation fields to the SELECT statement
            e.displine,
            e.interest,
            e.presence
            -- --- END OF MODIFICATION ---
        FROM
            stages s
        LEFT JOIN
            etudiants etu ON s.etudiantID = etu.etudiantID
        
        LEFT JOIN
            users u_encadrant_pro ON s.encadrantProID = u_encadrant_pro.userID
        LEFT JOIN
            sujetsstage sj ON s.sujetID = sj.sujetID
        LEFT JOIN
            evaluations e ON s.stageID = e.stageID AND e.encadrantID = ?
        WHERE
            s.encadrantProID = ? AND s.dateFin <= ?
        ORDER BY
            s.dateFin DESC;
    ";

    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Database error preparing statement: ' . $mysqli->error]);
        $mysqli->close();
        exit();
    }

    $stmt->bind_param("iis", $encadrantID, $encadrantID, $currentDate);
    $stmt->execute();
    $result = $stmt->get_result();

    $internships = [];
    while ($row = $result->fetch_assoc()) {
        $internship = [
            'internshipID' => $row['stageID'],
            'etudiantID' => $row['etudiantID'],
            'sujetID' => $row['sujetID'],
            'subjectTitle' => $row['subjectTitle'],
            'typeStage' => $row['typeStage'],
            'dateDebut' => $row['dateDebut'],
            'dateFin' => $row['dateFin'],
            'statut' => $row['statut'],
            'estRemunere' => (bool)$row['estRemunere'],
            'montantRemuneration' => (double)$row['montantRemuneration'],
            'encadrantProID' => $row['encadrantProID'],
            'chefCentreValidationID' => $row['stageChefCentreValidationID'],
            'studentName' => (isset($row['studentFirstName']) && isset($row['studentLastName']))
                               ? $row['studentFirstName'] . ' ' . $row['studentLastName']
                               : null,
            'supervisorName' => $row['supervisorName'],
            'encadrantProName' => $row['encadrantProName'],
            'encadrantPedaName' => null,
            'encadrantEvaluation' => null
        ];

        if ($row['evaluationID'] !== null) {
            $internship['encadrantEvaluation'] = [
                'evaluationID' => $row['evaluationID'],
                'stageID' => $row['stageID'],
                'encadrantID' => $encadrantID,
                'dateEvaluation' => $row['dateEvaluation'],
                'note' => $row['note'] !== null ? (double)$row['note'] : null, // Cast note to double
                'commentaires' => $row['commentaires'],
                'chefCentreValidationID' => $row['evaluationChefCentreValidationID'],
                'dateValidationChef' => $row['dateValidationChef'],
                
                // --- START OF MODIFICATION ---
                // Add the new fields to the evaluation array in the response
                'displine' => $row['displine'],
                'interest' => $row['interest'],
                'presence' => $row['presence']
                // --- END OF MODIFICATION ---
            ];
        }
        $internships[] = $internship;
    }

    $stmt->close();

    $response['status'] = 'success';
    $response['data'] = $internships;
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