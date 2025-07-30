<?php
require_once '../../db_connect.php';
require_once '../../verify_token.php';

header('Content-Type: application/json');   
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Credentials: true");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$response = [];

// ✅ JWT + role check
$userData = verifyJwtToken();
$allowedRoles = ['Gestionnaire'];
if (!in_array($userData['role'], $allowedRoles)) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Access denied. Only Gestionnaire can edit subjects.']);
    exit();
}

// ✅ Check if method is POST
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method. Only POST allowed.']);
    exit();
}

// ✅ Retrieve inputs
$sujetID = filter_var($_POST['sujetID'] ?? null, FILTER_VALIDATE_INT);
$titre = trim($_POST['titre'] ?? '');
$description = trim($_POST['description'] ?? '');

if (!$sujetID || empty($titre) || empty($description)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'sujetID, titre, and description are required.']);
    exit();
}

// ✅ File Upload
$uploadDir = '../../Gestionnaire/Subjects/';
$baseUrl = 'http://localhost/Backend/Gestionnaire/Subjects/';
$pdfUrl = null;
$updatePdf = false;

if (isset($_FILES['pdfFile']) && $_FILES['pdfFile']['error'] === UPLOAD_ERR_OK) {
    $fileTmpPath = $_FILES['pdfFile']['tmp_name'];
    $fileName = basename($_FILES['pdfFile']['name']);
    $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

    if ($fileExt !== 'pdf') {
        echo json_encode(['status' => 'error', 'message' => 'Only PDF files are allowed.']);
        exit();
    }

    $newFileName = uniqid('pdf_', true) . '.' . $fileExt;
    $destPath = $uploadDir . $newFileName;

    if (!move_uploaded_file($fileTmpPath, $destPath)) {
        echo json_encode(['status' => 'error', 'message' => 'Failed to save uploaded PDF.']);
        exit();
    }

    $pdfUrl = $baseUrl . $newFileName;
    $updatePdf = true;
}

// ✅ Check subject exists
$check_sql = "SELECT sujetID FROM Sujetsstage WHERE sujetID = ?";
$check_stmt = $mysqli->prepare($check_sql);
$check_stmt->bind_param("i", $sujetID);
$check_stmt->execute();
$check_stmt->store_result();

if ($check_stmt->num_rows === 0) {
    http_response_code(404);
    echo json_encode(['status' => 'error', 'message' => "Subject with ID $sujetID not found."]);
    $check_stmt->close();
    exit();
}
$check_stmt->close();

// ✅ Prepare the update SQL
if ($updatePdf) {
    $sql = "UPDATE Sujetsstage SET titre = ?, description = ?, pdfUrl = ? WHERE sujetID = ?";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param("sssi", $titre, $description, $pdfUrl, $sujetID);
} else {
    $sql = "UPDATE Sujetsstage SET titre = ?, description = ? WHERE sujetID = ?";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param("ssi", $titre, $description, $sujetID);
}

// ✅ Execute update
if ($stmt->execute()) {
    $response['status'] = 'success';
    $response['message'] = ($stmt->affected_rows === 0)
        ? 'Subject updated successfully (no changes applied).'
        : 'Subject updated successfully!';
    $response['data'] = [
        'sujetID' => (string)$sujetID,
        'titre' => $titre,
        'description' => $description,
        'pdfUrl' => $updatePdf ? $pdfUrl : null
    ];
} else {
    http_response_code(500);
    $response['status'] = 'error';
    $response['message'] = 'Database error during update: ' . $stmt->error;
}

$stmt->close();
$mysqli->close();
echo json_encode($response);
exit();
?>
