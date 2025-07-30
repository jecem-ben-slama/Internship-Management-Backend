<?php
require_once '../../db_connect.php';
require_once '../../verify_token.php';

// Set CORS headers at the very beginning
header("Access-Control-Allow-Origin: *"); // Allows requests from any origin. For production, specify your Flutter app's origin(s).
header("Access-Control-Allow-Methods: POST, OPTIONS"); // Crucially, include OPTIONS here
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With"); // Allow these headers from the client
header("Access-Control-Max-Age: 3600"); // Cache preflight response for 1 hour
header("Access-Control-Allow-Credentials: true"); // Important if you use cookies/sessions/credentials

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    // Respond with 200 OK and exit for preflight requests
    http_response_code(200);
    exit(); // IMPORTANT: Exit here so the rest of the script (which expects POST data) doesn't run
}

// Ensure Content-Type is set for actual responses (for POST requests)
header('Content-Type: application/json');

// Now, handle the actual POST request
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['status' => 'error', 'message' => 'Only POST requests are allowed.']);
    exit();
}

// Verify token
$userData = verifyJwtToken();
$allowedRoles = ['Gestionnaire'];
if (!in_array($userData['role'], $allowedRoles)) {
    http_response_code(403); // Forbidden
    echo json_encode(['status' => 'error', 'message' => 'Access denied. Only Gestionnaire can add subjects.']);
    exit();
}

// Sanitize input
// For multipart/form-data, $_POST and $_FILES are usually populated automatically.
$titre = trim($_POST['titre'] ?? '');
$description = trim($_POST['description'] ?? '');

// Validate input
if (empty($titre) || empty($description)) {
    http_response_code(400); // Bad Request
    echo json_encode(['status' => 'error', 'message' => 'Fields titre and description are required.']);
    exit();
}

// Handle PDF upload
// Make sure the directory exists and is writable by the web server
$uploadDir = '../../Gestionnaire/Subjects/';
// Check if upload directory exists, if not, try to create it
if (!is_dir($uploadDir)) {
    if (!mkdir($uploadDir, 0777, true)) { // Recursive and readable/writable by all (adjust permissions for production)
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Failed to create upload directory.']);
        exit();
    }
}

$baseUrl = 'http://localhost/Backend/Gestionnaire/Subjects/';
$pdfUrl = null;

if (isset($_FILES['pdfFile']) && $_FILES['pdfFile']['error'] === UPLOAD_ERR_OK) {
    $fileTmpPath = $_FILES['pdfFile']['tmp_name'];
    $fileName = basename($_FILES['pdfFile']['name']);
    $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

    if ($fileExt !== 'pdf') {
        http_response_code(400); // Bad Request
        echo json_encode(['status' => 'error', 'message' => 'Only PDF files are allowed.']);
        exit();
    }

    // Create a unique file name to avoid overwriting
    $newFileName = uniqid('pdf_', true) . '.' . $fileExt;
    $destPath = $uploadDir . $newFileName;

    if (!move_uploaded_file($fileTmpPath, $destPath)) {
        http_response_code(500); // Internal Server Error
        echo json_encode(['status' => 'error', 'message' => 'Failed to move uploaded file. Check directory permissions.']);
        exit();
    }

    $pdfUrl = $baseUrl . $newFileName;
} else if (isset($_FILES['pdfFile']) && $_FILES['pdfFile']['error'] !== UPLOAD_ERR_NO_FILE) {
    // Handle other upload errors (e.g., UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE)
    http_response_code(400); // Bad Request
    echo json_encode(['status' => 'error', 'message' => 'File upload error: ' . $_FILES['pdfFile']['error']]);
    exit();
}


// Insert into DB
$sql = "INSERT INTO Sujetsstage (titre, description, pdfUrl) VALUES (?, ?, ?)";
$stmt = $mysqli->prepare($sql);
if ($stmt === false) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Prepare failed: ' . $mysqli->error]);
    exit();
}
$stmt->bind_param("sss", $titre, $description, $pdfUrl);

if ($stmt->execute()) {
    echo json_encode([
        'status' => 'success',
        'message' => 'Subject added successfully!',
        'data' => [
            'sujetID' => (string)$mysqli->insert_id,
            'titre' => $titre,
            'description' => $description,
            'pdfUrl' => $pdfUrl
        ]
    ]);
} else {
    http_response_code(500); // Internal Server Error
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $stmt->error]);
}

$stmt->close();
$mysqli->close();
?>