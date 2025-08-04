<?php
// Gestionnaire/get_terminated_evaluated_internships.php (or get_validated_internships.php as per your warning)

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
    echo json_encode(['status' => 'error', 'message' => 'Access denied. Only ' . implode(', ', $allowedRoles) . ' can perform this action.']);
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "GET") {
    // SQL query to fetch internships that are 'Terminé' AND have a non-NULL note and comments in evaluations
    // We use JOIN evaluations to ensure only evaluated internships are returned.
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
        JOIN evaluations ev ON s.stageID = ev.stageID AND s.encadrantProID = ev.encadrantID
        WHERE s.statut = 'Validated' 
        ORDER BY s.dateFin DESC
    ";

    $result = $mysqli->query($sql);

    if ($result) {
        $internships = [];
        while ($row = $result->fetch_assoc()) {
            // Use null coalescing operator (??) to handle potential NULL values gracefully
            $studentFirstName = $row['studentFirstName'] ?? null;
            $studentLastName = $row['studentLastName'] ?? null;
            $encadrantFirstName = $row['encadrantFirstName'] ?? null;
            $encadrantLastName = $row['encadrantLastName'] ?? null;
            $subjectTitle = $row['subjectTitle'] ?? null;
            $subjectDescription = $row['subjectDescription'] ?? null;

            $internships[] = [
                'stageID' => $row['stageID'],
                'typeStage' => $row['typeStage'],
                'dateDebut' => $row['dateDebut'],
                'dateFin' => $row['dateFin'],
                'statut' => $row['statut'],
                'estRemunere' => (bool)$row['estRemunere'],
                'montantRemuneration' => $row['montantRemuneration'] !== null ? (float)$row['montantRemuneration'] : null,
                'etudiantID' => $row['etudiantID'],
                'studentName' => trim(($studentFirstName ?? '') . ' ' . ($studentLastName ?? '')), // Combine for convenience, handle nulls
                'studentFirstName' => $studentFirstName, // Keep separate for detail screen
                'studentLastName' => $studentLastName, // Keep separate for detail screen
                'studentEmail' => $row['studentEmail'] ?? null,
                'sujetID' => $row['sujetID'] ?? null, // Subject can be null if LEFT JOIN
                'subjectTitle' => $subjectTitle,
                'subjectDescription' => $subjectDescription,
                'encadrantID' => $row['encadrantID'] ?? null, // Encadrant can be null if LEFT JOIN
                'encadrantFirstName' => $encadrantFirstName,
                'encadrantLastName' => $encadrantLastName,
                'encadrantEmail' => $row['encadrantEmail'] ?? null,
                'evaluationID' => $row['evaluationID'],
                'note' => (float)$row['note'],
                'commentaires' => $row['commentaires'],
                'dateEvaluation' => $row['dateEvaluation'],
            ];
        }
        $response = ['status' => 'success', 'message' => 'Terminated and evaluated internships fetched successfully.', 'data' => $internships];
        http_response_code(200);
    } else {
        http_response_code(500);
        $response = ['status' => 'error', 'message' => 'Database query error: ' . $mysqli->error];
    }
} else {
    http_response_code(405);
    $response['status'] = 'error';
    $response['message'] = 'Invalid request method. Only GET requests are allowed.';
}

echo json_encode($response);
$mysqli->close();
exit();
?>