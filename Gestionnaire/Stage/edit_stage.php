<?php
require_once '../../db_connect.php';
require_once '../../verify_token.php';

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, PUT, OPTIONS"); // Allow POST and PUT for update, OPTIONS for preflightheader("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

header('Content-Type: application/json');

$response = [];

$userData = verifyJwtToken();

$allowedRoles = ['Gestionnaire'];
if (!in_array($userData['role'], $allowedRoles)) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Access denied. Only Gestionnaire can add stages.']);
    $mysqli->close();
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "PUT") {
    $input = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE || empty($input)) {
        $input = $_POST;
    }

    $etudiantID = filter_var($input['etudiantID'] ?? null, FILTER_VALIDATE_INT);
    $sujetID = isset($input['sujetID']) ? filter_var($input['sujetID'], FILTER_VALIDATE_INT) : null;
    $typeStage = trim($input['typeStage'] ?? '');
    $dateDebut = trim($input['dateDebut'] ?? '');
    $dateFin = trim($input['dateFin'] ?? '');
    $statut = "Proposé";
    $estRemunere = filter_var($input['estRemunere'] ?? null, FILTER_VALIDATE_INT);
    $montantRemuneration = isset($input['montantRemuneration']) ? filter_var($input['montantRemuneration'], FILTER_VALIDATE_FLOAT) : null;
    $encadrantProID = isset($input['encadrantProID']) ? filter_var($input['encadrantProID'], FILTER_VALIDATE_INT) : null;
    $encadrantAcademiqueID = isset($input['encadrantAcademiqueID']) ? filter_var($input['encadrantAcademiqueID'], FILTER_VALIDATE_INT) : null; // NEW: Get academic supervisor ID

    if (is_null($etudiantID) || empty($typeStage) || empty($dateDebut) || empty($dateFin) || is_null($estRemunere)) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Missing or invalid required fields.'
        ]);
        $mysqli->close();
        exit();
    }

    if (!preg_match("/^\d{4}-\d{2}-\d{2}$/", $dateDebut) || !preg_match("/^\d{4}-\d{2}-\d{2}$/", $dateFin)) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid date format. Use YYYY-MM-DD.'
        ]);
        $mysqli->close();
        exit();
    }

    $sql = "INSERT INTO stages (
        etudiantID, sujetID, typeStage, dateDebut, dateFin,
        statut, estRemunere, montantRemuneration, encadrantProID, encadrantAcademiqueID
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"; // NEW: Added encadrantAcademiqueID to INSERT statement

    if ($stmt = $mysqli->prepare($sql)) {
        // Default nulls if not provided - bind_param handles nulls if the type is correct
        // For 'i' (integer), passing null for a nullable column works as expected.
        $stmt->bind_param(
            "iissssidii", // NEW: Added 'i' for encadrantAcademiqueID (assuming it's an integer)
            $etudiantID,
            $sujetID,
            $typeStage,
            $dateDebut,
            $dateFin,
            $statut,
            $estRemunere,
            $montantRemuneration,
            $encadrantProID,
            $encadrantAcademiqueID // NEW: Bind encadrantAcademiqueID
        );

        try {
            if ($stmt->execute()) {
                echo json_encode([
                    'status' => 'success',
                    'message' => 'Stage added successfully!',
                    'stageID' => $mysqli->insert_id
                ]);
            } else {
                http_response_code(500);
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Database error: ' . $stmt->error
                ]);
            }
        } catch (mysqli_sql_exception $e) {
            http_response_code(500);
            $errorCode = $e->getCode();
            $errorMessage = $e->getMessage();
            if ($errorCode == 1452) {
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Foreign key constraint failed. Ensure etudiantID, sujetID, encadrantProID, or encadrantAcademiqueID exists.' // NEW: Added encadrantAcademiqueID to message
                ]);
            } else {
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Database error: ' . $errorMessage
                ]);
            }
        }

        $stmt->close();
    } else {
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => 'SQL error: ' . $mysqli->error
        ]);
    }
} else {
    http_response_code(405);
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid request method.'
    ]);
}

$mysqli->close();
?>
