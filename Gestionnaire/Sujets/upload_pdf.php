<?php
// --- CORS Headers (Crucial for Flutter Web) ---
// For development, allow all origins. For production, restrict to your Flutter app's domain.
header("Access-Control-Allow-Origin: http://localhost:51469"); // Or "*" for development ease
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization"); // Ensure Authorization is allowed if you're sending tokens
header("Access-Control-Max-Age: 3600"); // Cache preflight response for 1 hour

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// --- Your Token Validation/Authentication Logic (if any) ---
// Example: Validate JWT token (replace with your actual validation logic)
// if (!isset($_SERVER['HTTP_AUTHORIZATION'])) {
//     http_response_code(401);
//     echo json_encode(["status" => "error", "message" => "Authorization header missing."]);
//     exit();
// }
// $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
// list($type, $token) = explode(' ', $authHeader, 2);
// if (strcasecmp($type, 'Bearer') == 0) {
//     // Validate the $token here, e.g., with Firebase JWT library or similar
//     // If token is invalid, return 401/403
//     // Example: if (!isValidJwt($token)) { http_response_code(401); exit(); }
// } else {
//     http_response_code(401);
//     echo json_encode(["status" => "error", "message" => "Invalid Authorization type."]);
//     exit();
// }

// --- File Upload Logic ---
header('Content-Type: application/json'); // Set content type for JSON response

$uploadDir = '../../uploads/pdfs/'; // Define your upload directory relative to this script
                                    // Make sure this directory exists and is writable by the web server!
                                    // This path assumes 'backend' is at the same level as 'uploads'

// Create the directory if it doesn't exist
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true); // 0755 permissions, recursive creation
}

if (isset($_FILES['pdfFile'])) { // 'pdfFile' is the field name sent by Flutter (from MultipartFile.fromBytes)
    $file = $_FILES['pdfFile'];

    // Basic validation
    if ($file['error'] !== UPLOAD_ERR_OK) {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "File upload error: " . $file['error']]);
        exit();
    }

    // You might want more robust validation like:
    // Check file type (MIME type)
    if ($file['type'] != 'application/pdf') {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Invalid file type. Only PDF files are allowed."]);
        exit();
    }

    // Check file size (e.g., max 10MB)
    // if ($file['size'] > 10 * 1024 * 1024) { // 10MB
    //     http_response_code(400);
    //     echo json_encode(["status" => "error", "message" => "File size exceeds limit (10MB)."]);
    //     exit();
    // }

    $fileName = basename($file['name']); // Get original filename
    // Sanitize filename to prevent directory traversal or other issues
    $fileName = preg_replace("/[^A-Za-z0-9_.-]/", '', $fileName);
    // Add a unique prefix to the filename to prevent overwrites and make URLs unique
    $uniqueFileName = uniqid() . '_' . $fileName;
    $targetFilePath = $uploadDir . $uniqueFileName;

    if (move_uploaded_file($file['tmp_name'], $targetFilePath)) {
        // Successfully moved the file
        // Construct the public URL for the file
        // IMPORTANT: Adjust this URL based on how your web server serves files from the 'uploads' directory
        $publicBaseUrl = 'http://localhost/backend/Gestionnaire/Subjects/'; // Assuming 'uploads' is publicly accessible
        $pdfUrl = $publicBaseUrl . $uniqueFileName;

        http_response_code(200);
        echo json_encode([
            "status" => "success",
            "message" => "PDF uploaded successfully!",
            "pdfUrl" => $pdfUrl,
        ]);
    } else {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Failed to move uploaded file."]);
    }
} else {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "No file uploaded or invalid input field name."]);
}
?>