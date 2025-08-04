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

$userData = verifyJwtToken();
$allowedRoles = ['Encadrant'];

if (!in_array($userData['role'], $allowedRoles)) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Access denied. Only ' . implode(', ', $allowedRoles) . ' can perform this action.']);
    exit();
}

$encadrantID = $userData['userID'];

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
    $actionType = $input['actionType'] ?? 'validate';
    $commentaires = trim($input['commentaires'] ?? '');
    
    // --- START OF CRITICAL MODIFICATION ---
    // New input fields for criteria and missed days
    $displine = $input['displine'] ?? null;
    $interest = $input['interest'] ?? null;
    $presence = $input['presence'] ?? null;
    $missedDays = $input['note'] ?? null; // 'note' now represents missed days
    
    // Allowed values for the ratings
    $allowedRatings = ['Excellent', 'Average', 'Poor'];
    // --- END OF CRITICAL MODIFICATION ---

    if (empty($stageID) || !is_numeric($stageID)) {
        http_response_code(400);
        $response = ['status' => 'error', 'message' => 'stageID is required and must be valid.'];
        echo json_encode($response);
        $mysqli->close();
        exit();
    }
    $stageID = (int)$stageID;

    if ($actionType === 'validate') {
        // Validate all required fields for validation
        if (empty($displine) || empty($interest) || empty($presence)) {
            http_response_code(400);
            $response = ['status' => 'error', 'message' => 'Discipline, interest, and presence ratings are required for validation.'];
            echo json_encode($response);
            $mysqli->close();
            exit();
        }
        
        // Validate that the ratings are one of the allowed values
        if (!in_array($displine, $allowedRatings) || !in_array($interest, $allowedRatings) || !in_array($presence, $allowedRatings)) {
             http_response_code(400);
            $response = ['status' => 'error', 'message' => 'Invalid rating provided. Ratings must be Excellent, Average, or Poor.'];
            echo json_encode($response);
            $mysqli->close();
            exit();
        }
        
        // Validate 'missedDays' only if 'presence' is 'Poor'
        if ($presence === 'Poor') {
            if ($missedDays !== null && (!is_numeric($missedDays) || $missedDays < 0)) {
                http_response_code(400);
                $response = ['status' => 'error', 'message' => 'Number of missed days must be a non-negative number for "Poor" presence.'];
                echo json_encode($response);
                $mysqli->close();
                exit();
            }
        }
    }
    
    // The value to be stored in the 'note' column
    $noteToStore = $missedDays !== null ? (float)$missedDays : null;
    // ... (rest of the code is largely the same, but the SQL queries are updated)

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

    $stmt_eval = null;
    $stmt_update_stage = null;
    $retrieved_evaluationID = null;

    $mysqli->begin_transaction();
    $message = '';
    $newStageStatus = '';

    try {
        $sql_check_eval = "SELECT evaluationID FROM evaluations WHERE stageID = ? AND encadrantID = ?";
        $stmt_check_eval = $mysqli->prepare($sql_check_eval);
        if (!$stmt_check_eval) {
            throw new mysqli_sql_exception('Database error preparing evaluation check: ' . $mysqli->error);
        }
        $stmt_check_eval->bind_param("ii", $stageID, $encadrantID);
        $stmt_check_eval->execute();
        $stmt_check_eval->store_result();
        $evaluationExists = $stmt_check_eval->num_rows > 0;
        if ($evaluationExists) {
            $stmt_check_eval->bind_result($retrieved_evaluationID);
            $stmt_check_eval->fetch();
        }
        $stmt_check_eval->close();

        $dateEvaluation = date('Y-m-d');

        if ($actionType === 'validate') {
            $newStageStatus = 'Finished';
            if ($evaluationExists) {
                // Update existing evaluation
                $sql_eval = "UPDATE evaluations SET dateEvaluation = ?, note = ?, commentaires = ?, displine = ?, interest = ?, presence = ? WHERE stageID = ? AND encadrantID = ?";
                $stmt_eval = $mysqli->prepare($sql_eval);
                if (!$stmt_eval) throw new mysqli_sql_exception('Database error preparing update evaluation: ' . $mysqli->error);
                $stmt_eval->bind_param("sdssssii", $dateEvaluation, $noteToStore, $commentaires, $displine, $interest, $presence, $stageID, $encadrantID);
                $message = "Internship evaluation updated to Validé!";
            } else {
                // Insert new evaluation
                $sql_eval = "INSERT INTO evaluations (stageID, encadrantID, dateEvaluation, note, commentaires, displine, interest, presence) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt_eval = $mysqli->prepare($sql_eval);
                if (!$stmt_eval) throw new mysqli_sql_exception('Database error preparing insert evaluation: ' . $mysqli->error);
                $stmt_eval->bind_param("iisdssss", $stageID, $encadrantID, $dateEvaluation, $noteToStore, $commentaires, $displine, $interest, $presence);
                $message = "Internship evaluated and Validated!";
            }
        } else if ($actionType === 'unvalidate') {
            $newStageStatus = 'Refused';
            if ($evaluationExists) {
                // Clear evaluation data
                $sql_eval = "UPDATE evaluations SET note = NULL, commentaires = NULL, dateEvaluation = ?, displine = NULL, interest = NULL, presence = NULL WHERE stageID = ? AND encadrantID = ?";
                $stmt_eval = $mysqli->prepare($sql_eval);
                if (!$stmt_eval) throw new mysqli_sql_exception('Database error preparing unvalidate update: ' . $mysqli->error);
                $stmt_eval->bind_param("sii", $dateEvaluation, $stageID, $encadrantID);
            }
            $message = "Internship marked as Refusé!";
        } else {
            throw new Exception("Invalid actionType provided.");
        }

        if (isset($stmt_eval) && $stmt_eval !== false) {
            if (!$stmt_eval->execute()) {
                throw new mysqli_sql_exception('Database error performing evaluation action: ' . $stmt_eval->error);
            }
            if ($actionType === 'validate') {
                if (!$evaluationExists) {
                    $response['evaluationID'] = $stmt_eval->insert_id;
                } else {
                    $response['evaluationID'] = $retrieved_evaluationID;
                }
            } else if ($actionType === 'unvalidate') {
                $response['evaluationID'] = $retrieved_evaluationID;
            }
        } else {
            $response['evaluationID'] = $retrieved_evaluationID;
        }

        $sql_update_stage_status = "UPDATE stages SET statut = ? WHERE stageID = ?";
        $stmt_update_stage = $mysqli->prepare($sql_update_stage_status);
        if (!$stmt_update_stage) {
            throw new mysqli_sql_exception('Database error preparing status update: ' . $mysqli->error);
        }
        $stmt_update_stage->bind_param("si", $newStageStatus, $stageID);

        if (!$stmt_update_stage->execute()) {
            throw new mysqli_sql_exception('Database error updating internship status: ' . $stmt_update_stage->error);
        }

        $mysqli->commit();

        $response['status'] = 'success';
        $response['message'] = $message . " Status updated to '$newStageStatus'.";
        $response['note'] = $noteToStore;
        $response['displine'] = $displine;
        $response['interest'] = $interest;
        $response['presence'] = $presence;
        http_response_code(200);
        echo json_encode($response);

    } catch (Exception $e) {
        $mysqli->rollback();
        http_response_code(500);
        $response['status'] = 'error';
        error_log("Error (Validate Internship - Transaction Failed): Message: " . $e->getMessage());
        $response['message'] = 'An unexpected error occurred: ' . $e->getMessage();
        echo json_encode($response);
    } finally {
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
?>