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
        $sql_check_attestation = "SELECT attestationID, qrCodeData FROM attestation WHERE stageID = ?";
        $stmt_check = $mysqli->prepare($sql_check_attestation);
        if (!$stmt_check) {
            throw new mysqli_sql_exception('Database error preparing attestation check: ' . $mysqli->error);
        }
        $stmt_check->bind_param("i", $stageID);
        $stmt_check->execute();
        $stmt_check->store_result();

        $existingAttestationID = null;
        $existingQrCodeData = null;

        if ($stmt_check->num_rows > 0) {
            $stmt_check->bind_result($existingAttestationID, $existingQrCodeData);
            $stmt_check->fetch();
            // If attestation already exists, return its details
            $response = [
                'status' => 'info',
                'message' => 'Attestation already generated for this internship.',
                'attestationID' => $existingAttestationID,
                'qrCodeData' => $existingQrCodeData
            ];
            http_response_code(200);
            $stmt_check->close();
            $mysqli->commit(); // Commit the transaction even if no new data was inserted
            echo json_encode($response);
            exit();
        }
        $stmt_check->close();

        // 2. Insert new attestation record
        $dateGeneration = date('Y-m-d');
        $sql_insert_attestation = "INSERT INTO attestation (stageID, dateGeneration, qrCodeData) VALUES (?, ?, ?)";
        $stmt_insert = $mysqli->prepare($sql_insert_attestation);
        if (!$stmt_insert) {
            throw new mysqli_sql_exception('Database error preparing attestation insert: ' . $mysqli->error);
        }

        // Placeholder for qrCodeData initially, will be updated after insert_id
        $tempQrCodeData = ''; // Will be replaced
        $stmt_insert->bind_param("iss", $stageID, $dateGeneration, $tempQrCodeData);

        if (!$stmt_insert->execute()) {
            throw new mysqli_sql_exception('Database error inserting attestation: ' . $stmt_insert->error);
        }

        $newAttestationID = $mysqli->insert_id;
        $stmt_insert->close();

        // 3. Generate the QR Code URL
        // IMPORTANT: Replace 'YOUR_APP_BASE_URL' with the actual base URL of your Flutter web app
        // For example: 'https://yourdomain.com/attestation' or 'http://localhost:XXXX/attestation'
        // This URL should point to a public endpoint in your Flutter app that can handle deep links
        // or a simple PHP endpoint that redirects/serves the attestation.
        $qrCodeBaseUrl = "http://localhost/your_flutter_app_base_url_path/attestation_viewer"; // Example: http://localhost:XXXX/#/attestation_viewer
        $qrCodeData = "$qrCodeBaseUrl?attestationID=$newAttestationID";

        // 4. Update the attestation record with the generated QR code data
        $sql_update_qr = "UPDATE attestation SET qrCodeData = ? WHERE attestationID = ?";
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

        $response = [
            'status' => 'success',
            'message' => 'Attestation generated successfully.',
            'attestationID' => $newAttestationID,
            'qrCodeData' => $qrCodeData
        ];
        http_response_code(200);

    } catch (Exception $e) {
        $mysqli->rollback();
        http_response_code(500);
        $response['status'] = 'error';
        error_log("Error (Generate Attestation - Transaction Failed): Message: " . $e->getMessage());
        $response['message'] = 'An unexpected error occurred: ' . $e->getMessage();
    } finally {
        if (isset($stmt_check) && $stmt_check !== null) $stmt_check->close();
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
?>