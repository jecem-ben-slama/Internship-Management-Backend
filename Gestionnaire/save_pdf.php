<?php
// save_pdf.php
// Include necessary files
require_once '../db_connect.php'; // Contains $mysqli database connection
require_once '../verify_token.php'; // Contains verifyJwtToken() function

// CORS headers
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS"); // Allow POST for file upload
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Credentials: true");

// Handle preflight OPTIONS requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

header('Content-Type: application/json');

$response = array();

// Verify JWT token and get user data
$userData = verifyJwtToken(); // Expected to return ['userID', 'username', 'role'] or exit if invalid

// Define allowed roles for accessing this endpoint
$allowedRoles = ['Gestionnaire']; // Adjust as needed

if (!in_array($userData['role'], $allowedRoles)) {
    http_response_code(403); // Forbidden
    echo json_encode(['status' => 'error', 'message' => 'Access denied. You do not have permission to upload attestation/paie PDFs.']);
    $mysqli->close();
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'JSON Decode Error: ' . json_last_error_msg(),
        ]);
        exit();
    }

    $stageId = isset($data['stageID']) ? (int)$data['stageID'] : null;
    $pdfBase64 = isset($data['pdfBase64']) ? $data['pdfBase64'] : null;
    $pdfType = isset($data['pdfType']) ? $data['pdfType'] : null;

    // Validate pdfType to prevent path traversal or unexpected file types
    $allowedPdfTypes = ['attestation', 'paie']; // Add any other valid types
    if (!in_array($pdfType, $allowedPdfTypes)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid pdfType provided.']);
        $mysqli->close();
        exit();
    }

    if (!$stageId || !$pdfBase64 || !$pdfType) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Missing required parameters (stageID, pdfBase64, or pdfType).']);
        $mysqli->close();
        exit();
    }

    $pdfContent = base64_decode($pdfBase64);
    if ($pdfContent === false) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid Base64 PDF content.']);
        $mysqli->close();
        exit();
    }

    $outputDirectory = __DIR__ . '/../Files/'; // Relative to where this script is, e.g., Backend/Gestionnaire/../Files/
    // Ensure this path is correct and accessible for web (e.g., http://localhost/Backend/Files/)

    if (!is_dir($outputDirectory)) {
        if (!mkdir($outputDirectory, 0777, true)) { // 0777 is for development, adjust for production
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Failed to create PDF directory. Check permissions.']);
            $mysqli->close();
            exit();
        }
    }

    $dateTime = new DateTime('now', new DateTimeZone('Africa/Tunis'));
    $dateGeneration = $dateTime->format('Y-m-d');

    // --- Start DB Operations for 'documents' table ---
    $tableName = 'documents'; // Changed to your new generic table

    // Check if a PDF of this type already exists for this stageID in the 'documents' table
    $selectStmt = $mysqli->prepare("SELECT document_url FROM {$tableName} WHERE stage_id = ? AND document_type = ?");
    if ($selectStmt === false) {
        error_log("Failed to prepare SELECT statement for existing PDF in 'documents': " . $mysqli->error);
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Failed to check for existing document due to statement preparation error.']);
        $mysqli->close();
        exit();
    }
    $selectStmt->bind_param("is", $stageId, $pdfType);
    $selectStmt->execute();
    $selectStmt->store_result();
    $selectStmt->bind_result($existingDocumentUrl);

    $oldFilePath = null;
    // We need to know if a row existed *before* the execute, so we fetch num_rows
    $recordExists = $selectStmt->num_rows > 0;

    if ($recordExists) {
        $selectStmt->fetch(); // Fetch the existing URL
        // Convert URL path back to local file system path for deletion
        // Assumes your web root for files is 'http://localhost/Backend/Files/'
        $oldFilePath = str_replace("http://localhost/Backend/Files/", $outputDirectory, $existingDocumentUrl);
    }
    $selectStmt->close();

    // Generate new filename and paths
    $filename = $pdfType . '_' . $stageId . '_' . uniqid() . '.pdf';
    $filePath = $outputDirectory . $filename;
    $documentUrl = "http://localhost/Backend/Files/" . $filename; // This is the public URL

    if (file_put_contents($filePath, $pdfContent)) {
        // If an old file exists, delete it
        if ($oldFilePath && file_exists($oldFilePath)) {
            unlink($oldFilePath); // Delete the old PDF file
            error_log("Deleted old file: " . $oldFilePath); // Log for debugging
        }

        if ($recordExists) { // Record existed, so update it
            $updateStmt = $mysqli->prepare(
                "UPDATE {$tableName} SET dateGeneration = ?, document_url = ?
                 WHERE stage_id = ? AND document_type = ?"
            );
            if ($updateStmt === false) {
                error_log("Failed to prepare UPDATE statement for 'documents': " . $mysqli->error);
                unlink($filePath); // Delete new file if DB update prep fails
                http_response_code(500);
                echo json_encode(['status' => 'error', 'message' => 'PDF saved, but failed to update document record due to statement preparation error.']);
                $mysqli->close();
                exit();
            }
            $updateStmt->bind_param("ssis", $dateGeneration, $documentUrl, $stageId, $pdfType);

            if ($updateStmt->execute()) {
                http_response_code(200); // 200 OK for update
                echo json_encode([
                    'status' => 'success',
                    'message' => ucfirst($pdfType) . ' PDF updated and record modified.',
                    'url' => $documentUrl,
                    'stageID' => $stageId,
                    'pdfType' => $pdfType
                ]);
            } else {
                error_log("Failed to execute UPDATE statement for 'documents': " . $updateStmt->error);
                unlink($filePath); // Delete new file if DB update fails
                http_response_code(500);
                echo json_encode(['status' => 'error', 'message' => 'PDF saved, but failed to update document record. Error: ' . $updateStmt->error]);
            }
            $updateStmt->close();
        } else { // No existing record, so insert a new one
            $insertStmt = $mysqli->prepare(
                "INSERT INTO {$tableName} (stage_id, dateGeneration, document_url, document_type)
                 VALUES (?, ?, ?, ?)"
            );
            if ($insertStmt === false) {
                error_log("Failed to prepare INSERT statement for 'documents': " . $mysqli->error);
                unlink($filePath); // Delete new file if DB insert prep fails
                http_response_code(500);
                echo json_encode(['status' => 'error', 'message' => 'PDF saved, but failed to record in database due to statement preparation error.']);
                $mysqli->close();
                exit();
            }
            $insertStmt->bind_param("isss", $stageId, $dateGeneration, $documentUrl, $pdfType);

            if ($insertStmt->execute()) {
                $documentId = $mysqli->insert_id; // Get the ID of the newly inserted document
                http_response_code(201); // 201 Created for new resource
                echo json_encode([
                    'status' => 'success',
                    'message' => ucfirst($pdfType) . ' PDF saved and new record created.',
                    'documentId' => $documentId,
                    'url' => $documentUrl,
                    'stageID' => $stageId,
                    'pdfType' => $pdfType
                ]);
            } else {
                error_log("Failed to execute INSERT statement for 'documents': " . $insertStmt->error);
                unlink($filePath); // Delete new file if DB insert fails
                http_response_code(500);
                echo json_encode(['status' => 'error', 'message' => 'PDF saved, but failed to record in database. Error: ' . $insertStmt->error]);
            }
            $insertStmt->close();
        }
    } else {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Failed to save PDF file to server. Check directory permissions: ' . $outputDirectory]);
    }
} else {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method. Only POST requests are allowed.']);
}

$mysqli->close();