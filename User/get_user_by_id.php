<?php
// PHP error reporting for debugging - REMOVE IN PRODUCTION
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Ensure no output before headers
ob_start();

require_once '../db_connect.php'; // Path to your database connection file (make sure $mysqli is initialized here)
require_once '../verify_token.php'; // Path to your JWT verification file

// CORS headers - crucial for Flutter Web
header("Access-Control-Allow-Origin: *"); // Allow all origins for development. In production, specify your app's origin: e.g., "http://localhost:54515"
header("Access-Control-Allow-Methods: POST, GET, OPTIONS"); // Added POST as it's the expected method
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With"); // Allow Content-Type and Authorization headers
header("Access-Control-Max-Age: 3600"); // Cache preflight requests for 1 hour
header("Access-Control-Allow-Credentials: true"); // Allow credentials (e.g., cookies, auth headers)

// Handle preflight OPTIONS requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    ob_end_clean(); // Clean any accidental output buffer
    exit();
}

header('Content-Type: application/json'); // Set content type to JSON

$response = array(); // Initialize response array

// Verify JWT token and get user data
// This function is expected to return an associative array if valid, or handle errors/exit itself
$requesterUserData = verifyJwtToken();

// Define allowed roles for accessing this specific endpoint (fetching a user's own profile or another user's profile)
// For fetching own profile, any logged-in user can access. For fetching others, specific roles.
// Let's assume for a profile screen, any authenticated user can get *their own* profile,
// and specific roles can get *other* users' profiles.
$allowedRolesForFetchingOthers = ['Gestionnaire', 'ChefCentreInformatique']; // Example: Only managers can fetch any user's profile

$targetUserID = null; // The ID of the user whose profile we want to fetch

// Determine the method and extract userID
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $json_data = file_get_contents('php://input');
    $data = json_decode($json_data, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400); // Bad Request
        $response = ['status' => 'error', 'message' => 'Invalid JSON in request body: ' . json_last_error_msg()];
        echo json_encode($response);
        ob_end_flush(); // Output buffer and exit
        exit;
    }

    if (isset($data['userID']) && !empty($data['userID'])) {
        $targetUserID = intval($data['userID']);
    } else {
        http_response_code(400); // Bad Request
        $response = ['status' => 'error', 'message' => 'userID is required in the request body.'];
        echo json_encode($response);
        ob_end_flush(); // Output buffer and exit
        exit;
    }
} else if ($_SERVER["REQUEST_METHOD"] == "GET") {
    // If you still want to support GET (e.g., for direct browser testing), get userID from query param
    if (isset($_GET['userID']) && !empty($_GET['userID'])) {
        $targetUserID = intval($_GET['userID']);
    } else {
        http_response_code(400); // Bad Request
        $response = ['status' => 'error', 'message' => 'userID is required as a query parameter for GET requests, or in the JSON body for POST.'];
        echo json_encode($response);
        ob_end_flush(); // Output buffer and exit
        exit;
    }
} else {
    http_response_code(405); // Method Not Allowed
    $response = ['status' => 'error', 'message' => 'Invalid request method. Only POST or GET requests are allowed.'];
    echo json_encode($response);
    ob_end_flush(); // Output buffer and exit
    exit;
}

// Access Control Logic:
// 1. A user can always fetch their own profile.
// 2. Specific roles can fetch other users' profiles.
if ($targetUserID !== $requesterUserData['userID'] && !in_array($requesterUserData['role'], $allowedRolesForFetchingOthers)) {
    http_response_code(403); // Forbidden
    $response = ['status' => 'error', 'message' => 'Access denied. You do not have permission to view this user\'s profile.'];
    echo json_encode($response);
    $mysqli->close();
    ob_end_flush(); // Output buffer and exit
    exit();
}


// Check if $mysqli object is available from db_connect.php
if (!isset($mysqli) || $mysqli->connect_error) {
    http_response_code(500);
    $response = ['status' => 'error', 'message' => 'Database connection failed: ' . ($mysqli->connect_error ?? 'Unknown error')];
    echo json_encode($response);
    ob_end_flush(); // Output buffer and exit
    exit();
}

// Prepare SQL statement to fetch a single user
$sql = "SELECT userID, username, email, lastname, role FROM users WHERE userID = ?";
$stmt = $mysqli->prepare($sql);

if ($stmt) {
    // Bind the userID parameter
    $stmt->bind_param('i', $targetUserID); // 'i' for integer

    if ($stmt->execute()) {
        $result = $stmt->get_result();
        $user = $result->fetch_assoc(); // Fetch a single row

        if ($user) {
            $response['status'] = 'success';
            $response['message'] = 'User fetched successfully.';
            // IMPORTANT: Do NOT include sensitive information like password hashes in the response
            $response['data'] = $user;
        } else {
            http_response_code(404); // Not Found
            $response['status'] = 'error';
            $response['message'] = 'User not found.';
        }
        $result->free();
    } else {
        http_response_code(500); // Internal Server Error
        $response['status'] = 'error';
        $response['message'] = 'Database query failed: ' . $stmt->error;
    }
    $stmt->close();
} else {
    http_response_code(500); // Internal Server Error
    $response['status'] = 'error';
    $response['message'] = 'Failed to prepare statement: ' . $mysqli->error;
}

$mysqli->close(); // Close database connection
echo json_encode($response);
ob_end_flush(); // Final flush of the output buffer
?>