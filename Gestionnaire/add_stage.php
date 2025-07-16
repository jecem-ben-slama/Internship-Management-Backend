<?php
require_once '../db_connect.php'; // Path to your database connection file
require_once '../verify_token.php'; // Path to your JWT verification file

// CORS headers - crucial for Flutter Web
header("Access-Control-Allow-Origin: *"); // Allow all origins for development
header("Access-Control-Allow-Methods: POST, OPTIONS"); // Allow POST and OPTIONS methods
header("Access-Control-Allow-Headers: Content-Type, Authorization"); // Allow Content-Type and Authorization headers
header("Access-Control-Max-Age: 3600"); // Cache preflight requests for 1 hour

// Handle preflight OPTIONS requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

header('Content-Type: application/json'); // Set content type to JSON

$response = array(); // Initialize response array

// Verify JWT token and get user data
$userData = verifyJwtToken(); // Expected to return ['userID', 'username', 'role'] or exit if invalid

// Define allowed roles for accessing this endpoint
$allowedRoles = ['Gestionnaire']; // Only Gestionnaire can add stages

if (!in_array($userData['role'], $allowedRoles)) {
    http_response_code(403); // Forbidden
    echo json_encode(['status' => 'error', 'message' => 'Access denied. Only ' . implode('', $allowedRoles) . ' can add stages.']);
    $mysqli->close();
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $input = json_decode(file_get_contents('php://input'), true);

    // If json_decode failed or input is empty, try $_POST (for form-data)
    if (json_last_error() !== JSON_ERROR_NONE || empty($input)) {
        $input = $_POST;
    }

    // Extract and sanitize input data
    // Refer to your 'stages' table structure
    $etudiantID = filter_var($input['etudiantID'] ?? null, FILTER_VALIDATE_INT);
    $sujetID = filter_var($input['sujetID'] ?? null, FILTER_VALIDATE_INT);
    $typeStage = trim($input['typeStage'] ?? '');
    $dateDebut = trim($input['dateDebut'] ?? ''); // Assuming 'YYYY-MM-DD' format
    $dateFin = trim($input['dateFin'] ?? '');   // Assuming 'YYYY-MM-DD' format
    $statut = trim($input['statut'] ?? ''); // Should match enum values: 'Proposé', 'Validé', 'Refusé', 'En cours', 'Terminé'
    $estRemunere = filter_var($input['estRemunere'] ?? null, FILTER_VALIDATE_INT); // 0 or 1
    $montantRemuneration = filter_var($input['montantRemuneration'] ?? null, FILTER_VALIDATE_FLOAT); // Can be null
    $encadrantProID = filter_var($input['encadrantProID'] ?? null, FILTER_VALIDATE_INT); // Can be null
    $chefCentreValidationID = filter_var($input['chefCentreValidationID'] ?? null, FILTER_VALIDATE_INT); // Can be null

    // --- Input Validation ---
    if (is_null($etudiantID) || is_null($sujetID) || empty($typeStage) || empty($dateDebut) || empty($dateFin) || empty($statut) || is_null($estRemunere)) {
        http_response_code(400); // Bad Request
        $response['status'] = 'error';
        $response['message'] = 'Required fields (etudiantID, sujetID, typeStage, dateDebut, dateFin, statut, estRemunere) are missing or invalid.';
        echo json_encode($response);
        $mysqli->close();
        exit();
    }

    // Basic date format validation (YYYY-MM-DD)
    if (!preg_match("/^\d{4}-\d{2}-\d{2}$/", $dateDebut) || !preg_match("/^\d{4}-\d{2}-\d{2}$/", $dateFin)) {
        http_response_code(400); // Bad Request
        $response['status'] = 'error';
        $response['message'] = 'Date format must be YYYY-MM-DD.';
        echo json_encode($response);
        $mysqli->close();
        exit();
    }

    // Validate statut against allowed ENUM values if necessary, or rely on DB constraint
    $allowedStatuts = ['Proposé', 'Validé', 'Refusé', 'En cours', 'Terminé'];
    if (!in_array($statut, $allowedStatuts)) {
        http_response_code(400); // Bad Request
        $response['status'] = 'error';
        $response['message'] = 'Invalid value for statut. Allowed values are: ' . implode(', ', $allowedStatuts) . '.';
        echo json_encode($response);
        $mysqli->close();
        exit();
    }


    // Prepare SQL INSERT Statement
    // Columns: stageID (auto), etudiantID, sujetID, typeStage, dateDebut, dateFin, statut, estRemunere, montantRemuneration, encadrantProID, chefCentreValidationID
    // The 's' types should match your database column types
    $sql_insert = "INSERT INTO stages (etudiantID, sujetID, typeStage, dateDebut, dateFin, statut, estRemunere, montantRemuneration, encadrantProID, chefCentreValidationID) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    if ($stmt = $mysqli->prepare($sql_insert)) {
        // 'issssdidis' -> i: int, s: string, d: double (decimal), null uses null
        // etudiantID (int), sujetID (int), typeStage (string), dateDebut (string), dateFin (string), statut (string), estRemunere (int), montantRemuneration (double), encadrantProID (int), chefCentreValidationID (int)
        // For nullable integers/doubles, if they are null, you might bind them as 'NULL' or pass them as null and adjust binding (PDO handles nulls better than mysqli::bind_param for this).
        // With mysqli::bind_param, you have to be careful with nulls. It expects a type.
        // For 'montantRemuneration', 'encadrantProID', 'chefCentreValidationID', pass actual null if they are not provided, and make sure your table columns allow NULL.

        // Correct way to handle potential nulls with mysqli::bind_param
        $param_montantRemuneration = $montantRemuneration;
        $param_encadrantProID = $encadrantProID;
        $param_chefCentreValidationID = $chefCentreValidationID;

        // If a value is null, try to set its type explicitly or handle it outside bind_param if it causes issues.
        // For typical int/float fields that are nullable, simply passing null will work if the column is defined as NULLABLE.
        // The 'd' for float and 'i' for int should be fine if you're passing null directly.

        $stmt->bind_param("iissssisii",
            $etudiantID,
            $sujetID,
            $typeStage,
            $dateDebut,
            $dateFin,
            $statut,
            $estRemunere,
            $param_montantRemuneration,
            $param_encadrantProID,
            $param_chefCentreValidationID
        );

        try {
            if ($stmt->execute()) {
                $response['status'] = 'success';
                $response['message'] = 'Stage added successfully!';
                $response['stageID'] = $mysqli->insert_id; // Get the ID of the newly created stage
            } else {
                http_response_code(500); // Internal Server Error
                $response['status'] = 'error';
                $response['message'] = 'Database error adding stage: ' . $stmt->error;
            }
        } catch (mysqli_sql_exception $e) {
            http_response_code(500); // Internal Server Error
            $response['status'] = 'error';
            $error_message_from_db = $e->getMessage();
            $error_code_from_db = $e->getCode();

            // Handle specific database errors like foreign key constraints, data type mismatches
            if ($error_code_from_db == 1452) { // Foreign key constraint fails
                $response['message'] = 'Foreign key constraint failed. Ensure etudiantID, sujetID, encadrantProID, chefCentreValidationID exist.';
            } else {
                $response['message'] = 'Database error adding stage: ' . $error_message_from_db;
            }
        }

        $stmt->close();
    } else {
        http_response_code(500); // Internal Server Error
        $response['status'] = 'error';
        $response['message'] = 'Error preparing SQL statement for stage addition: ' . $mysqli->error;
    }
} else {
    http_response_code(405); // Method Not Allowed
    $response['status'] = 'error';
    $response['message'] = 'Invalid request method. Only POST requests are allowed.';
}

$mysqli->close(); // Close database connection
echo json_encode($response);
?>