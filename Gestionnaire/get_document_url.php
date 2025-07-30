<?php
// backend/Gestionnaire/get_document_url.php

// Enable error reporting for debugging (CRITICAL FOR DEVELOPMENT, REMOVE/DISABLE IN PRODUCTION)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start output buffering to catch any premature output (e.g., warnings, notices, whitespace)
ob_start();

// Include necessary files
// Adjust paths if your db_connect.php or verify_token.php are in a different location relative to this script
require_once '../db_connect.php'; // Contains $mysqli database connection
require_once '../verify_token.php'; // Contains verifyJwtToken() function

// CORS headers - Essential for Flutter web and cross-origin requests
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS"); // Allow GET for fetching, OPTIONS for preflight
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Max-Age: 3600"); // Cache preflight results for 1 hour
header("Access-Control-Allow-Credentials: true");

// Handle preflight OPTIONS requests first
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    // Clear any buffered output before sending preflight headers
    ob_end_clean();
    http_response_code(200); // Send OK status for preflight
    exit(); // Terminate script after preflight
}

// Set Content-Type header to ensure the response is treated as JSON
header('Content-Type: application/json');

// Verify JWT token and get user data
// This function is expected to return an array like ['userID', 'username', 'role'] or terminate the script on failure
$userData = verifyJwtToken();

// Define allowed roles for accessing this endpoint
// Ensure 'Gestionnaire' is included, and add any other roles like 'Etudiant', 'Encadrant' if they should also view documents.
$allowedRoles = ['Gestionnaire', 'Etudiant', 'Encadrant', 'ChefCentreInformatique']; // Example roles

if (!in_array($userData['role'], $allowedRoles)) {
    ob_end_clean(); // Clear buffer before sending unauthorized response
    http_response_code(403); // Forbidden
    echo json_encode(['status' => 'error', 'message' => 'Access denied. You do not have permission to view document URLs.']);
    $mysqli->close(); // Close DB connection
    exit(); // Exit
}

// Check if database connection is successful
if (!isset($mysqli) || $mysqli->connect_error) {
    ob_end_clean(); // Clear buffer
    http_response_code(500); // Internal Server Error
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed: ' . ($mysqli->connect_error ?? 'Unknown error')]);
    exit(); // Exit
}

// Process GET requests
if ($_SERVER["REQUEST_METHOD"] == "GET") {
    // Sanitize and validate input parameters from the URL query string
    $stageId = isset($_GET['stageID']) ? (int)$_GET['stageID'] : null;
    $pdfType = isset($_GET['pdfType']) ? htmlspecialchars($_GET['pdfType']) : null;

    // Check for missing parameters
    if ($stageId === null || $pdfType === null) {
        ob_end_clean(); // Clear buffer
        http_response_code(400); // Bad Request
        echo json_encode(['status' => 'error', 'message' => 'Missing stageID or pdfType parameter.']);
        $mysqli->close();
        exit();
    }

    // Validate pdfType against a whitelist to prevent arbitrary access or SQL injection issues
    $allowedPdfTypes = ['attestation', 'paie']; // Ensure these match the values in your 'document_type' column
    if (!in_array($pdfType, $allowedPdfTypes)) {
        ob_end_clean(); // Clear buffer
        http_response_code(400); // Bad Request
        echo json_encode(['status' => 'error', 'message' => 'Invalid pdfType provided.']);
        $mysqli->close();
        exit();
    }

    // Prepare and execute the SQL query to fetch the document URL from the 'documents' table
    // Using 'document_url' column as per the provided table structure
    $stmt = $mysqli->prepare("SELECT document_url FROM documents WHERE stage_id = ? AND document_type = ?");

    if ($stmt === false) {
        ob_end_clean(); // Clear buffer
        error_log("Failed to prepare statement for fetching document URL: " . $mysqli->error); // Log to PHP error log
        http_response_code(500); // Internal Server Error
        echo json_encode(['status' => 'error', 'message' => 'Database query preparation failed.']);
        $mysqli->close();
        exit();
    }

    // Bind parameters: 'i' for integer (stageId), 's' for string (pdfType)
    $stmt->bind_param("is", $stageId, $pdfType);
    $stmt->execute();
    $stmt->store_result(); // Store results to check num_rows
    $stmt->bind_result($documentUrl); // Bind the fetched result to $documentUrl

    if ($stmt->num_rows > 0) {
        $stmt->fetch(); // Fetch the single row
        ob_end_clean(); // Clear buffer before sending success response
        http_response_code(200); // OK
        echo json_encode([
            'status' => 'success',
            'message' => 'URL fetched successfully.',
            'url' => $documentUrl // The actual URL from the database
        ]);
    } else {
        ob_end_clean(); // Clear buffer before sending not found response
        http_response_code(404); // Not Found
        echo json_encode(['status' => 'error', 'message' => 'Document not found for the specified stage and type.']);
    }

    $stmt->close(); // Close the prepared statement
} else {
    // Handle unsupported request methods
    ob_end_clean(); // Clear buffer
    http_response_code(405); // Method Not Allowed
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method. Only GET requests are allowed.']);
}

$mysqli->close(); // Close the main database connection
