<?php
// Gestionnaire/generate_attestation.php

require_once '../db_connect.php';
require_once '../verify_token.php';

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
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
$allowedRoles = ['Gestionnaire'];

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
        $response = ['status' => 'error', 'message' => 'stageID is required and must be valid.'];
        echo json_encode($response);
        $mysqli->close();
        exit();
    }
    $stageID = (int)$stageID;

    // Start a transaction
    $mysqli->begin_transaction();

    try {
        // 1. Check if an attestation already exists for this stageID
        $sql_check_attestation = "SELECT attestationID FROM attestationsstage WHERE stageID = ?"; // Only need attestationID to check existence
        $stmt_check = $mysqli->prepare($sql_check_attestation);
        if (!$stmt_check) {
            throw new mysqli_sql_exception('Database error preparing attestation check: ' . $mysqli->error);
        }
        $stmt_check->bind_param("i", $stageID);
        $stmt_check->execute();
        $stmt_check->store_result();

        $existingAttestationID = null;

        if ($stmt_check->num_rows > 0) {
            $stmt_check->bind_result($existingAttestationID); // Bind only attestationID
            $stmt_check->fetch();
            $stmt_check->close(); // Close stmt_check here

            // Attestation already exists, fetch its full details
            $fullAttestationData = fetchAttestationDataForStageID($mysqli, $stageID);

            if ($fullAttestationData) {
                 $response = [
                    'status' => 'info',
                    'message' => 'Attestation already generated for this internship.',
                    'data' => $fullAttestationData // **THIS IS THE CRUCIAL CHANGE for 'info' status**
                ];
                http_response_code(200);
            } else {
                 http_response_code(500);
                 $response = [
                    'status' => 'error',
                    'message' => 'Attestation exists but failed to retrieve full data for existing stageID: ' . $stageID
                ];
            }
            $mysqli->commit();
            echo json_encode($response);
            exit();
        }
        $stmt_check->close(); // Ensure it's closed if num_rows is 0 too.

        // 2. Insert new attestation record
        $dateGeneration = date('Y-m-d');
        $sql_insert_attestation = "INSERT INTO attestationsstage (stageID, dateGeneration, qrCodeData) VALUES (?, ?, ?)";
        $stmt_insert = $mysqli->prepare($sql_insert_attestation);
        if (!$stmt_insert) {
            throw new mysqli_sql_exception('Database error preparing attestation insert: ' . $mysqli->error);
        }

        $tempQrCodeData = '';
        $stmt_insert->bind_param("iss", $stageID, $dateGeneration, $tempQrCodeData);

        if (!$stmt_insert->execute()) {
            throw new mysqli_sql_exception('Database error inserting attestation: ' . $stmt_insert->error);
        }

        $newAttestationID = $mysqli->insert_id;
        $stmt_insert->close();

        // 3. Generate the QR Code URL
        $qrCodeBaseUrl = "http://localhost:51891/#/attestation_viewer"; // Confirm this port and hash
        $qrCodeData = "$qrCodeBaseUrl?attestationID=$newAttestationID";

        // 4. Update the attestation record with the generated QR code data
        $sql_update_qr = "UPDATE attestationsstage SET qrCodeData = ? WHERE attestationID = ?";
        $stmt_update = $mysqli->prepare($sql_update_qr);
        if (!$stmt_update) {
            throw new mysqli_sql_exception('Database error preparing QR update: ' . $mysqli->error);
        }
        $stmt_update->bind_param("si", $qrCodeData, $newAttestationID);
        if (!$stmt_update->execute()) {
            throw new mysqli_sql_exception('Database error updating QR code data: ' . $stmt_update->error);
        }
        $stmt_update->close();

        $mysqli->commit();

        // Fetch the complete attestation data after successful generation and QR update
        $fullAttestationData = fetchAttestationDataForStageID($mysqli, $stageID);

        if ($fullAttestationData) {
            $response = [
                'status' => 'success',
                'message' => 'Attestation generated successfully.',
                'data' => $fullAttestationData // Consistently return full data under 'data' key
            ];
            http_response_code(200);
        } else {
            http_response_code(500);
            $response = ['status' => 'error', 'message' => 'Attestation generated but failed to retrieve full data.'];
        }

    } catch (Exception $e) {
        $mysqli->rollback();
        http_response_code(500);
        $response['status'] = 'error';
        error_log("Error (Generate Attestation - Transaction Failed): Message: " . $e->getMessage());
        $response['message'] = 'An unexpected error occurred: ' . $e->getMessage();
    } finally {
        // Ensure all statements are closed
        if (isset($stmt_check) && $stmt_check !== null && $stmt_check->num_rows > 0) $stmt_check->close(); // Only close if it was opened and used
        if (isset($stmt_insert) && $stmt_insert !== null) $stmt_insert->close();
        if (isset($stmt_update) && $stmt_update !== null) $stmt_update->close();
    }

} else {
    http_response_code(405);
    $response['status'] = 'error';
    $response['message'] = 'Invalid request method. Only POST requests are allowed.';
}

echo json_encode($response);
$mysqli->close();
exit();

// Helper function to fetch full attestation data based on stageID
function fetchAttestationDataForStageID($mysqli, $stageID) {
    $sql = "
        SELECT
            at.attestationID, at.dateGeneration, at.qrCodeData,
            s.stageID, s.typeStage, s.dateDebut, s.dateFin, s.statut, s.estRemunere, s.montantRemuneration,
            e.etudiantID, e.username AS studentFirstName, e.lastname AS studentLastName, e.email AS studentEmail,
            su.sujetID, su.titre AS subjectTitle, su.description AS subjectDescription,
            u.userID AS encadrantID, u.username AS encadrantFirstName, u.lastname AS encadrantLastName, u.email AS encadrantEmail,
            ev.evaluationID, ev.note, ev.commentaires, ev.dateEvaluation
        FROM attestationsstage at
        JOIN stages s ON at.stageID = s.stageID
        JOIN etudiants e ON s.etudiantID = e.etudiantID
        LEFT JOIN sujetsstage su ON s.sujetID = su.sujetID
        LEFT JOIN users u ON s.encadrantProID = u.userID
        JOIN evaluations ev ON s.stageID = ev.stageID AND s.encadrantProID = ev.encadrantID
        WHERE at.stageID = ? AND s.statut = 'Terminé' AND ev.note IS NOT NULL
    ";

    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        error_log('Database error preparing full attestation data fetch: ' . $mysqli->error);
        return null;
    }

    $stmt->bind_param("i", $stageID);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();

        $studentFirstName = $row['studentFirstName'] ?? null;
        $studentLastName = $row['studentLastName'] ?? null;
        $encadrantFirstName = $row['encadrantFirstName'] ?? null;
        $encadrantLastName = $row['encadrantLastName'] ?? null;
        $subjectTitle = $row['subjectTitle'] ?? null;
        $subjectDescription = $row['subjectDescription'] ?? null;
        $encadrantEmail = $row['encadrantEmail'] ?? null;
        $montantRemuneration = $row['montantRemuneration'] !== null ? (float)$row['montantRemuneration'] : null;


        return [
            'attestationID' => $row['attestationID'],
            'dateGeneration' => $row['dateGeneration'],
            'qrCodeData' => $row['qrCodeData'],

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
    }
    return null;
}
?>