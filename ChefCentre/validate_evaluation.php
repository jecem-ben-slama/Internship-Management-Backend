<?php
// backend/ChefCentre/validate_or_reject_evaluation.php

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

$userData = verifyJwtToken();
$allowedRoles = ['ChefCentreInformatique'];

if (!in_array($userData['role'], $allowedRoles)) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Access denied. Only ' . implode(', ', $allowedRoles) . ' can validate/reject evaluations.']);
    exit();
}

$chefCentreID = $userData['userID']; // The ID of the logged-in Chef Centre

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $data = json_decode(file_get_contents("php://input"), true);

    $evaluationID = $data['evaluationID'] ?? null;
    $actionType = $data['actionType'] ?? null; // 'validate' or 'reject'

    if (empty($evaluationID) || !in_array($actionType, ['validate', 'reject'])) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Missing or invalid evaluation ID or action type.']);
        exit();
    }

    // Start a transaction to ensure atomicity
    $mysqli->begin_transaction();

    try {
        // Step 1: Update the evaluation table
        if ($actionType === 'validate') {
            $stmt_eval = $mysqli->prepare("
                UPDATE evaluations
                SET chefCentreValidationID = ?, dateValidationChef = NOW()
                WHERE evaluationID = ?
            ");
            if (!$stmt_eval) {
                throw new Exception("Failed to prepare validation statement: " . $mysqli->error);
            }
            $stmt_eval->bind_param("ii", $chefCentreID, $evaluationID);
            $statusMessage = "Evaluation validated successfully.";
            $newInternshipStatus = 'Validated';
        } else { // actionType === 'reject'
            $stmt_eval = $mysqli->prepare("
                UPDATE evaluations
                SET chefCentreValidationID = NULL, dateValidationChef = NULL
                WHERE evaluationID = ?
            ");
            if (!$stmt_eval) {
                throw new Exception("Failed to prepare rejection statement: " . $mysqli->error);
            }
            $stmt_eval->bind_param("i", $evaluationID);
            $statusMessage = "Evaluation rejected successfully.";
            $newInternshipStatus = 'Rejected'; // Or 'Non validé'
        }

        $stmt_eval->execute();
        if ($stmt_eval->affected_rows === 0) {
            throw new Exception("Evaluation not found or no changes made (it might already be processed).");
        }
        $stmt_eval->close();
        $stmt_eval = null; // Prevent double close

        // Step 2: Update the internship (stages) status
        // First, get the stageID associated with the evaluationID
        $stmt_get_stage_id = $mysqli->prepare("SELECT stageID FROM evaluations WHERE evaluationID = ?");
        if (!$stmt_get_stage_id) {
            throw new Exception("Failed to prepare get stage ID statement: " . $mysqli->error);
        }
        $stmt_get_stage_id->bind_param("i", $evaluationID);
        $stmt_get_stage_id->execute();
        $result_stage_id = $stmt_get_stage_id->get_result();
        $stage_row = $result_stage_id->fetch_assoc();
        $stmt_get_stage_id->close();
        $stmt_get_stage_id = null; // Prevent double close

        if ($stage_row === null) {
             throw new Exception("Associated internship (stageID) not found for evaluation.");
        }
        $stageID = $stage_row['stageID'];

        $stmt_stage = $mysqli->prepare("
            UPDATE stages
            SET statut = ?
            WHERE stageID = ?
        ");
        if (!$stmt_stage) {
            throw new Exception("Failed to prepare stage status update statement: " . $mysqli->error);
        }
        $stmt_stage->bind_param("si", $newInternshipStatus, $stageID);
        $stmt_stage->execute();
        $stmt_stage->close();
        $stmt_stage = null; // Prevent double close

        $mysqli->commit(); // Commit the transaction
        http_response_code(200);
        echo json_encode(['status' => 'success', 'message' => $statusMessage]);

    } catch (Exception $e) {
        $mysqli->rollback(); // Rollback on error
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Transaction failed: ' . $e->getMessage()]);
    } finally {
        // Close statements if they were prepared and not already closed
        if (isset($stmt_eval) && $stmt_eval) $stmt_eval->close();
        if (isset($stmt_get_stage_id) && $stmt_get_stage_id) $stmt_get_stage_id->close();
        if (isset($stmt_stage) && $stmt_stage) $stmt_stage->close();
    }

} else {
    http_response_code(405);
    $response['status'] = 'error';
    $response['message'] = 'Invalid request method. Only POST requests are allowed.';
    echo json_encode($response);
}

$mysqli->close();
exit();
?>