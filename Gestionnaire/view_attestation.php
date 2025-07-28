<?php
// Student/view_attestation.php

require_once '../db_connect.php';
// No JWT verification needed here, as it's a public endpoint for QR code scanning

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, X-Requested-With"); // No Authorization header needed
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Credentials: true");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

header('Content-Type: application/json');
$response = array();

if ($_SERVER["REQUEST_METHOD"] == "GET") {
    $attestationID = $_GET['attestationID'] ?? null;

    if (empty($attestationID) || !is_numeric($attestationID)) {
        http_response_code(400);
        $response = ['status' => 'error', 'message' => 'attestationID is required and must be valid.'];
        echo json_encode($response);
        $mysqli->close();
        exit();
    }
    $attestationID = (int)$attestationID;

    // SQL query to fetch all necessary data for the attestation using attestationID
    $sql = "
        SELECT
            at.attestationID, at.dateGeneration, at.qrCodeData,
            s.stageID, s.typeStage, s.dateDebut, s.dateFin, s.statut, s.estRemunere, s.montantRemuneration,
            e.etudiantID, e.username AS studentFirstName, e.lastname AS studentLastName, e.email AS studentEmail,
            su.sujetID, su.titre AS subjectTitle, su.description AS subjectDescription,
            u.userID AS encadrantID, u.username AS encadrantFirstName, u.lastname AS encadrantLastName, u.email AS encadrantEmail,
            ev.evaluationID, ev.note, ev.commentaires, ev.dateEvaluation
        FROM attestation at
        JOIN stages s ON at.stageID = s.stageID
        JOIN etudiants e ON s.etudiantID = e.etudiantID
        LEFT JOIN sujets su ON s.sujetID = su.sujetID
        LEFT JOIN users u ON s.encadrantProID = u.userID
        JOIN evaluations ev ON s.stageID = ev.stageID AND s.encadrantProID = ev.encadrantID
        WHERE at.attestationID = ? AND s.statut = 'Terminé' AND ev.note IS NOT NULL
    ";

    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        http_response_code(500);
        $response = ['status' => 'error', 'message' => 'Database error preparing query: ' . $mysqli->error];
        echo json_encode($response);
        $mysqli->close();
        exit();
    }

    $stmt->bind_param("i", $attestationID);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();

        // Null coalescing for potentially null fields (from LEFT JOINs or nullable columns)
        $studentFirstName = $row['studentFirstName'] ?? null;
        $studentLastName = $row['studentLastName'] ?? null;
        $encadrantFirstName = $row['encadrantFirstName'] ?? null;
        $encadrantLastName = $row['encadrantLastName'] ?? null;
        $subjectTitle = $row['subjectTitle'] ?? null;
        $subjectDescription = $row['subjectDescription'] ?? null;
        $encadrantEmail = $row['encadrantEmail'] ?? null;
        $montantRemuneration = $row['montantRemuneration'] !== null ? (float)$row['montantRemuneration'] : null;


        $attestationData = [
            'attestationID' => $row['attestationID'],
            'dateGeneration' => $row['dateGeneration'],
            'qrCodeData' => $row['qrCodeData'], // The URL itself

            'internship' => [
                'stageID' => $row['stageID'],
                'typeStage' => $row['typeStage'],
                'dateDebut' => $row['dateDebut'],
                'dateFin' => $row['dateFin'],
                'statut' => $row['statut'],
                'estRemunere' => (bool)$row['estRemunere'],
                'montantRemuneration' => $montantRemuneration,
            ],
            'student' => [
                'studentID' => $row['etudiantID'],
                'firstName' => $studentFirstName,
                'lastName' => $studentLastName,
                'email' => $row['studentEmail'],
            ],
            'subject' => [
                'subjectID' => $row['sujetID'],
                'title' => $subjectTitle,
                'description' => $subjectDescription,
            ],
            'supervisor' => [
                'supervisorID' => $row['encadrantID'],
                'firstName' => $encadrantFirstName,
                'lastName' => $encadrantLastName,
                'email' => $encadrantEmail,
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
        $response = ['status' => 'error', 'message' => 'Attestation not found or invalid ID.'];
    }

    $stmt->close();
} else {
    http_response_code(405);
    $response['status'] = 'error';
    $response['message'] = 'Invalid request method. Only GET requests are allowed.';
}

echo json_encode($response);
$mysqli->close();
exit();
?>