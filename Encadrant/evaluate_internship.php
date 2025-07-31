<?php
// Encadrant/validate_internship.php

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
$allowedRoles = ['Encadrant'];

if (!in_array($userData['role'], $allowedRoles)) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Access denied. Only ' . implode(', ', $allowedRoles) . ' can perform this action.']);
    exit();
}

$encadrantID = $userData['userID']; // The ID of the logged-in Encadrant

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
    $actionType = $input['actionType'] ?? 'validate'; // 'validate' or 'unvalidate'
    $note = $input['note'] ?? null; // Can be null based on table structure
    $commentaires = trim($input['commentaires'] ?? ''); // Can be null

    if (empty($stageID) || !is_numeric($stageID)) {
        http_response_code(400);
        $response = ['status' => 'error', 'message' => 'stageID is required and must be valid.'];
        echo json_encode($response);
        $mysqli->close();
        exit();
    }
    $stageID = (int)$stageID;

    // Validate 'note' only if actionType is 'validate' and note is provided
    if ($actionType === 'validate' && $note !== null) {
        if (!is_numeric($note) || $note < 0 || $note > 20) { // Assuming a 0-10 scale for 'note'
            http_response_code(400);
            $response = ['status' => 'error', 'message' => 'Note must be a number between 0 and 20 for validation.'];
            echo json_encode($response);
            $mysqli->close();
            exit();
        }
        $note = (float)$note; // Ensure it's a float
    }

    // Verify that this internship is actually assigned to this Encadrant
    $sql_check_assignment = "SELECT stageID FROM stages WHERE stageID = ? AND encadrantProID = ?";
    $stmt_check = $mysqli->prepare($sql_check_assignment);
    if (!$stmt_check) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Database error checking internship assignment: ' . $mysqli->error]);
        $mysqli->close();
        exit();
    }
    $stmt_check->bind_param("ii", $stageID, $encadrantID);
    $stmt_check->execute();
    $stmt_check->store_result();
    if ($stmt_check->num_rows == 0) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'You are not assigned to this internship or it does not exist.']);
        $stmt_check->close();
        $mysqli->close();
        exit();
    }
    $stmt_check->close();

    // Initialize statements to null so finally block can safely check them
    $stmt_eval = null;
    $stmt_update_stage = null;
    $retrieved_evaluationID = null; // Variable to store existing evaluation ID

    // Start a transaction
    $mysqli->begin_transaction();
    $message = '';
    $newStageStatus = '';

    try {
        // Handle Evaluation (Insert/Update/Delete based on actionType)
        $sql_check_eval = "SELECT evaluationID FROM evaluations WHERE stageID = ? AND encadrantID = ?";
        $stmt_check_eval = $mysqli->prepare($sql_check_eval);
        if (!$stmt_check_eval) {
            throw new mysqli_sql_exception('Database error preparing evaluation check: ' . $mysqli->error);
        }
        $stmt_check_eval->bind_param("ii", $stageID, $encadrantID);
        $stmt_check_eval->execute();
        $stmt_check_eval->store_result();

        if ($stmt_check_eval->num_rows > 0) {
            $stmt_check_eval->bind_result($retrieved_evaluationID);
            $stmt_check_eval->fetch();
            $evaluationExists = true;
        } else {
            $evaluationExists = false;
        }
        $stmt_check_eval->close();

        $dateEvaluation = date('Y-m-d'); // Current date for evaluation

        if ($actionType === 'validate') {
            $newStageStatus = 'Finished';
            if ($evaluationExists) {
                // Update existing evaluation
                $sql_eval = "UPDATE evaluations SET dateEvaluation = ?, note = ?, commentaires = ? WHERE stageID = ? AND encadrantID = ?";
                $stmt_eval = $mysqli->prepare($sql_eval);
                if (!$stmt_eval) throw new mysqli_sql_exception('Database error preparing update evaluation: ' . $mysqli->error);
                // Correct bind_param for UPDATE: s (date), d (note), s (commentaires), i (stageID), i (encadrantID)
                $stmt_eval->bind_param("sdsii", $dateEvaluation, $note, $commentaires, $stageID, $encadrantID);
                $message = "Internship evaluation updated to Validé!";
            } else {
                // Insert new evaluation
                $sql_eval = "INSERT INTO evaluations (stageID, encadrantID, dateEvaluation, note, commentaires) VALUES (?, ?, ?, ?, ?)";
                $stmt_eval = $mysqli->prepare($sql_eval);
                if (!$stmt_eval) throw new mysqli_sql_exception('Database error preparing insert evaluation: ' . $mysqli->error);
                // FIX: Correct bind_param for INSERT: i (stageID), i (encadrantID), s (date), d (note), s (commentaires)
                $stmt_eval->bind_param("iisds", $stageID, $encadrantID, $dateEvaluation, $note, $commentaires);
                $message = "Internship evaluated and Validated!";
            }
        } else if ($actionType === 'unvalidate') {
            $newStageStatus = 'Refused'; // Or 'Non Validé' as per your system
            if ($evaluationExists) {
                // Option 1: Clear evaluation data (keeps record, but clears content)
                $sql_eval = "UPDATE evaluations SET note = NULL, commentaires = NULL, dateEvaluation = ? WHERE stageID = ? AND encadrantID = ?";
                $stmt_eval = $mysqli->prepare($sql_eval);
                if (!$stmt_eval) throw new mysqli_sql_exception('Database error preparing unvalidate update: ' . $mysqli->error);
                $stmt_eval->bind_param("sii", $dateEvaluation, $stageID, $encadrantID);

                // Option 2: Delete the evaluation record completely (if unvalidation means no record of evaluation)
                // $sql_eval = "DELETE FROM evaluations WHERE stageID = ? AND encadrantID = ?";
                // $stmt_eval = $mysqli->prepare($sql_eval);
                // if (!$stmt_eval) throw new mysqli_sql_exception('Database error preparing unvalidate delete: ' . $mysqli->error);
                // $stmt_eval->bind_param("ii", $stageID, $encadrantID);
            } else {
                // If no evaluation exists, but we unvalidate, we still need to set the status
                // No evaluation specific SQL needed here if it doesn't exist
            }
            $message = "Internship marked as Refusé!";
        } else {
            throw new Exception("Invalid actionType provided.");
        }

        // Execute evaluation statement if it was prepared
        if (isset($stmt_eval) && $stmt_eval !== false) {
            if (!$stmt_eval->execute()) {
                throw new mysqli_sql_exception('Database error performing evaluation action: ' . $stmt_eval->error);
            }
            // Logic for setting evaluationID in response
            if ($actionType === 'validate') {
                if (!$evaluationExists) {
                    $response['evaluationID'] = $stmt_eval->insert_id; // For new inserts
                } else {
                    $response['evaluationID'] = $retrieved_evaluationID; // Use the ID fetched earlier for updates
                }
            } else if ($actionType === 'unvalidate') {
                // If unvalidating, and an evaluation existed, return its ID or null if it was deleted
                $response['evaluationID'] = $retrieved_evaluationID; // For updates/clears
            }
        } else {
            // If $stmt_eval was never prepared (e.g., unvalidate when no evaluation exists)
            $response['evaluationID'] = $retrieved_evaluationID; // Still use the previously found ID if available
        }


        // Update the internship status in the 'stages' table
        $sql_update_stage_status = "UPDATE stages SET statut = ? WHERE stageID = ?";
        $stmt_update_stage = $mysqli->prepare($sql_update_stage_status);
        if (!$stmt_update_stage) {
            throw new mysqli_sql_exception('Database error preparing status update: ' . $mysqli->error);
        }
        $stmt_update_stage->bind_param("si", $newStageStatus, $stageID);

        if (!$stmt_update_stage->execute()) {
            throw new mysqli_sql_exception('Database error updating internship status: ' . $stmt_update_stage->error);
        }

        // If all operations successful, commit the transaction
        $mysqli->commit();

        $response['status'] = 'success';
        $response['message'] = $message . " Status updated to '$newStageStatus'.";
        http_response_code(200);
        echo json_encode($response);

    } catch (Exception $e) { // Catch generic Exception now for actionType check
        $mysqli->rollback();
        http_response_code(500);
        $response['status'] = 'error';
        error_log("Error (Validate Internship - Transaction Failed): Message: " . $e->getMessage());
        $response['message'] = 'An unexpected error occurred: ' . $e->getMessage();
        echo json_encode($response);
    } finally {
        // Ensure statements are closed ONLY once here
        if (isset($stmt_eval) && $stmt_eval !== null) {
            $stmt_eval->close();
        }
        if (isset($stmt_update_stage) && $stmt_update_stage !== null) {
            $stmt_update_stage->close();
        }
    }

} else {
    http_response_code(405);
    $response['status'] = 'error';
    $response['message'] = 'Invalid request method. Only POST requests are allowed.';
    echo json_encode($response);
}

$mysqli->close();
exit();