    <?php
    require_once '../db_connect.php';
    require_once '../verify_token.php';

    // CORS headers
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: PUT, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization");
    header("Access-Control-Max-Age: 3600");
    header("Access-Control-Allow-Credentials: true");

    if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
        http_response_code(200);
        exit();
    }

    header('Content-Type: application/json');

    $response = array();

    // Verify JWT token and get user data
    $userData = verifyJwtToken();

    // Only allow certain roles to edit internships
    $allowedRoles = ['Gestionnaire'];
    if (!in_array($userData['role'], $allowedRoles)) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Access denied.']);
        $mysqli->close();
        exit();
    }

    // Only allow PUT requests
    if ($_SERVER['REQUEST_METHOD'] == 'PUT') {
        $input = json_decode(file_get_contents('php://input'), true);
        // Corrected this based on previous discussion: Flutter sends 'internshipID'
        $stageID = isset($input['stageID']) ? $input['stageID'] : ''; 

        if (empty($stageID)) {
            http_response_code(400);
            $response['status'] = 'error';
            $response['message'] = 'Invalid internshipID or missing.'; // Adjusted message
        } else {
            $fields = [];
            $params = [];
            $types = '';

            // Flag to check if estRemunere was provided in the input
            $estRemunereProvided = false;
            $estRemunereValue = null;

            if (isset($input['typeStage'])) {
                $fields[] = 'typeStage = ?';
                $params[] = $input['typeStage'];
                $types .= 's';
            }
            if (isset($input['dateDebut'])) {
                $fields[] = 'dateDebut = ?';
                $params[] = $input['dateDebut'];
                $types .= 's';
            }
            if (isset($input['dateFin'])) {
                $fields[] = 'dateFin = ?';
                $params[] = $input['dateFin'];
                $types .= 's';
            }
            if (isset($input['statut'])) {
                $validStatutes = ['Validé', 'En attente', 'Refusé', 'Proposé'];
                if (in_array($input['statut'], $validStatutes)) {
                    $fields[] = 'statut = ?';
                    $params[] = $input['statut'];
                    $types .= 's';
                } else {
                    error_log("Received invalid status for internship ID $stageID: " . $input['statut']);
                }
            }
            
            // Handle 'estRemunere' first to apply its logic to 'montantRemuneration'
            if (isset($input['estRemunere'])) {
                $estRemunereProvided = true;
                // Convert boolean true/false from JSON to 1/0 for MySQL TINYINT(1)
                $estRemunereValue = $input['estRemunere'] ? 1 : 0; 
                
                $fields[] = 'estRemunere = ?';
                $params[] = $estRemunereValue;
                $types .= 'i'; // Integer type for boolean
            }

            // Handle 'montantRemuneration' based on 'estRemunere'
            if ($estRemunereProvided && $estRemunereValue === 0) {
                // If estRemunere is explicitly set to false (0), set montant to 0
                $fields[] = 'montantRemuneration = ?';
                $params[] = 0.00; // Force to 0
                $types .= 'd'; // Double type
            } elseif (isset($input['montantRemuneration'])) {
                // Only use the provided montant if estRemunere is true or not provided
                $montant = is_numeric($input['montantRemuneration']) ? (double)$input['montantRemuneration'] : 0.00; // Default to 0 if invalid number
                
                $fields[] = 'montantRemuneration = ?';
                $params[] = $montant;
                $types .= 'd'; // Double type
            }
            // If estRemunere becomes false and montantRemuneration was not in input, it won't be updated.
            // This is fine, as we explicitly update it to 0 if estRemunere changes to false.
            

            if (isset($input['encadrantProID'])) {
                $fields[] = 'encadrantProID = ?';
                $params[] = $input['encadrantProID'];
                $types .= 'i'; // Assuming ID is integer in DB
            }
            if (isset($input['chefCentreValidationID'])) {
                $fields[] = 'chefCentreValidationID = ?';
                $params[] = $input['chefCentreValidationID'];
                $types .= 'i'; // Assuming ID is integer in DB
            }

            if (count($fields) === 0) {
                http_response_code(400);
                $response['status'] = 'error';
                $response['message'] = 'No fields to update.';
            } else {
                $params[] = $stageID; // Add stageID to the parameters
                $types .= 's'; // Type for stageID (assuming it's a string, change to 'i' if integer)

                $sql = "UPDATE stages SET " . implode(', ', $fields) . " WHERE stageID = ?";
                $stmt = $mysqli->prepare($sql);

                if ($stmt === false) {
                    http_response_code(500);
                    $response['status'] = 'error';
                    $response['message'] = 'Prepare failed: ' . $mysqli->error;
                    echo json_encode($response);
                    $mysqli->close();
                    exit();
                }

                // Use call_user_func_array to bind parameters dynamically
                $bind_names[] = $types;
                for ($i = 0; $i < count($params); $i++) {
                    $bind_name = 'bind' . $i;
                    $$bind_name = $params[$i];
                    $bind_names[] = &$$bind_name;
                }
                call_user_func_array([$stmt, 'bind_param'], $bind_names);

                if ($stmt->execute()) {
                    if ($stmt->affected_rows > 0) {
                        $response['status'] = 'success';
                        $response['message'] = 'Stage updated successfully.';
                    } else {
                        // Changed to 200/info for "no changes made" scenarios
                        http_response_code(200); 
                        $response['status'] = 'info'; 
                        $response['message'] = 'Stage found, but no changes were made.';
                    }
                } else {
                    http_response_code(500);
                    $response['status'] = 'error';
                    $response['message'] = 'Database error: ' . $stmt->error;
                }
                $stmt->close();
            }
        }
    } else {
        http_response_code(405);
        $response['status'] = 'error';
        $response['message'] = 'Invalid request method. Only PUT is allowed.';
    }

    $mysqli->close();
    echo json_encode($response);
    ?>