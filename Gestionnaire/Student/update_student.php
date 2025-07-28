<?php
// backend/Gestionnaire/update_student.php

// Enable error reporting for debugging (REMOVE IN PRODUCTION)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start output buffering to catch any premature output
ob_start();

require_once '../../db_connect.php'; // Path to your database connection file
require_once '../../verify_token.php'; // Path to your JWT verification file

// CORS headers - crucial for Flutter Web
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS"); // Only POST for update, OPTIONS for preflight
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Credentials: true");

// Handle preflight OPTIONS requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    ob_end_clean(); // Clean any output buffer before sending headers
    http_response_code(200); // Send 200 OK for preflight
    exit(); // IMPORTANT: Exit immediately after sending preflight headers
}

header('Content-Type: application/json'); // Set content type to JSON for actual requests

$response = array(); // Initialize response array

// Verify JWT token and get user data
$userData = verifyJwtToken(); // Expected to return ['userID', 'username', 'role'] or exit if invalid

// Define the ONLY role allowed to update students.
$allowedRoles = ['Gestionnaire'];

if (!in_array($userData['role'], $allowedRoles)) {
    ob_end_clean(); // Clean any output buffer
    http_response_code(403); // Forbidden
    echo json_encode(['status' => 'error', 'message' => 'Access denied. Only ' . implode(', ', $allowedRoles) . ' can update students.']);
    exit();
}

// Ensure $mysqli is connected before proceeding with database operations
if (!isset($mysqli) || $mysqli->connect_error) {
    ob_end_clean(); // Clean any output buffer
    http_response_code(500);
    $response = ['status' => 'error', 'message' => 'Database connection failed: ' . ($mysqli->connect_error ?? 'Unknown error')];
    echo json_encode($response);
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $input = json_decode(file_get_contents('php://input'), true);

    if (json_last_error() !== JSON_ERROR_NONE || !is_array($input) || empty($input)) {
        ob_end_clean();
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid or empty JSON body.']);
        $mysqli->close();
        exit();
    }

    // Get the student ID from the JSON body
    $etudiantID = $input['etudiantID'] ?? null;

    if (empty($etudiantID) || !is_numeric($etudiantID)) {
        ob_end_clean();
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Student ID (etudiantID) is required and must be a valid number.']);
        $mysqli->close();
        exit();
    }

    $etudiantID = (int)$etudiantID;

    // Start transaction for atomicity
    $mysqli->begin_transaction();

    try {
        $etudiant_set_clauses = [];
        $etudiant_bind_types = '';
        $etudiant_bind_params = [];

        // Map incoming JSON keys to database column names
        $field_map = [
            'username' => 'username',
            'lastname' => 'lastname',
            'email' => 'email',
            'cin' => 'cin', // Assuming DB column is 'cin'
            'niveau_etude' => 'niveauEtude', // Map from Flutter's 'niveau_etude' to DB's 'niveauEtude'
            'faculte' => 'nomFaculte',       // Map from Flutter's 'faculte' to DB's 'nomFaculte'
            'cycle' => 'cycle',
            'specialite' => 'specialite',
        ];

        foreach ($field_map as $json_key => $db_column) {
            if (isset($input[$json_key])) {
                $value = trim($input[$json_key]);

                // Specific validations
                if ($json_key === 'email') {
                    if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                        throw new Exception('Invalid email format.');
                    }
                    $stmt_check_email = $mysqli->prepare("SELECT etudiantID FROM etudiants WHERE email = ? AND etudiantID != ?");
                    if (!$stmt_check_email) {
                        throw new Exception('Database error preparing email check: ' . $mysqli->error);
                    }
                    $stmt_check_email->bind_param("si", $value, $etudiantID);
                    $stmt_check_email->execute();
                    $stmt_check_email->store_result();
                    if ($stmt_check_email->num_rows > 0) {
                        throw new Exception('This email is already registered by another student.', 409);
                    }
                    $stmt_check_email->close();
                } elseif ($json_key === 'cin') {
                    $stmt_check_cin = $mysqli->prepare("SELECT etudiantID FROM etudiants WHERE cin = ? AND etudiantID != ?");
                    if (!$stmt_check_cin) {
                        throw new Exception('Database error preparing CIN check: ' . $mysqli->error);
                    }
                    $stmt_check_cin->bind_param("si", $value, $etudiantID);
                    $stmt_check_cin->execute();
                    $stmt_check_cin->store_result();
                    if ($stmt_check_cin->num_rows > 0) {
                        throw new Exception('This CIN is already registered by another student.', 409);
                    }
                    $stmt_check_cin->close();
                }

                // Add to update clauses
                $etudiant_set_clauses[] = "$db_column = ?";
                $etudiant_bind_types .= 's'; // Assuming all these fields are strings
                $etudiant_bind_params[] = $value;
            }
        }

        // Check if any fields were provided for update
        if (empty($etudiant_set_clauses)) {
             throw new Exception('No valid fields provided for update.');
        }

        // Construct and execute the UPDATE query
        $sql_etudiant = "UPDATE etudiants SET " . implode(', ', $etudiant_set_clauses) . " WHERE etudiantID = ?";
        $etudiant_bind_types .= 'i'; // Add type for etudiantID
        $etudiant_bind_params[] = $etudiantID; // Add etudiantID to parameters

        $stmt_etudiant = $mysqli->prepare($sql_etudiant);
        if (!$stmt_etudiant) {
            throw new Exception('Failed to prepare student update statement: ' . $mysqli->error);
        }

        // Dynamic binding for bind_param
        $refs = [];
        foreach ($etudiant_bind_params as $key => $value) {
            $refs[$key] = &$etudiant_bind_params[$key];
        }
        array_unshift($refs, $etudiant_bind_types);
        call_user_func_array([$stmt_etudiant, 'bind_param'], $refs);

        if (!$stmt_etudiant->execute()) {
            throw new Exception('Failed to update student: ' . $stmt_etudiant->error);
        }
        $affected_rows_total = $stmt_etudiant->affected_rows;
        $stmt_etudiant->close();

        // Commit transaction
        $mysqli->commit();

        // Prepare success response
        ob_end_clean();
        http_response_code(200);
        $response['status'] = 'success';
        $response['message'] = 'Student updated successfully.';
        if ($affected_rows_total == 0) {
            error_log("Info: Student ID $etudiantID data was already up-to-date (no changes made).");
        }

    } catch (Exception $e) {
        $mysqli->rollback(); // Rollback on any error
        ob_end_clean(); // Clean any output buffer
        $statusCode = $e->getCode() ?: 500; // Use custom code if provided, else 500
        http_response_code($statusCode);
        $response['status'] = 'error';
        $response['message'] = 'Failed to update student: ' . $e->getMessage();
        error_log("Error updating student: " . $e->getMessage());
    }

} else {
    ob_end_clean();
    http_response_code(405); // Method Not Allowed
    $response['status'] = 'error';
    $response['message'] = 'Invalid request method. Only POST requests are allowed.';
}

$mysqli->close(); // Close DB connection
echo json_encode($response);
?>
