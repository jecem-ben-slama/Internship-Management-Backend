<?php
require_once '../db_connect.php';
require_once '../verify_token.php'; 
header('Content-Type: application/json');
$response = array();
$userData = verifyJwtToken(); 
$allowedRoles = ['Gestionnaire']; 


if (!in_array($userData['role'], $allowedRoles)) {
    http_response_code(403); 
    echo json_encode(['status' => 'error', 'message' => 'Access denied. Only ' . implode('', $allowedRoles) . ' can add encadrants.']);
    $mysqli->close(); 
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $input = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE || empty($input)) {
        $input = $_POST;
    }
    // Retrieve and sanitize input values for the new Encadrant user.
    $username = trim($input['username'] ?? '');
    $lastname=trim($input['lastname']??'');
    $email = trim($input['email'] ?? '');
    $cin = $input['cin'] ?? ''; 
    $niveau_etude = $input['niveau_etude'] ?? ''; 
    $faculte = $input['faculte'] ?? ''; 
    $cycle = $input['cycle'] ?? ''; 
   

    // --- Input Validation for the NEW Student's Data ---
    if (empty($username) || empty($email) || empty($cin)) {
        $response['status'] = 'error';
        $response['message'] = 'Username, email, and password are required .';
        echo json_encode($response);
        $mysqli->close();
        exit();
    }

    // Basic email format validation
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $response['status'] = 'error';
        $response['message'] = 'Invalid email format.';
        echo json_encode($response);
        $mysqli->close();
        exit();
    }
//!! unique email
   /*  // --- Check if email already exists in the Users table ---
    $sql_check_email = "SELECT etudiantID FROM etudiant WHERE email = ?";
    if ($stmt_check = $mysqli->prepare($sql_check_email)) {
        $stmt_check->bind_param("s", $param_email_check);
        $param_email_check = $email;
        $stmt_check->execute();
        $stmt_check->store_result();

        if ($stmt_check->num_rows > 0) {
            $response['status'] = 'error';
            $response['message'] = 'This email is already registered.';
            echo json_encode($response);
            $stmt_check->close();
            $mysqli->close();
            exit();
        }
        $stmt_check->close();
    } else {
        $response['status'] = 'error';
        $response['message'] = 'Database error checking existing email: ' . $mysqli->error;
        echo json_encode($response);
        $mysqli->close();
        exit();
    } */

    

    
    $sql_insert = "INSERT INTO etudiants (username,lastname, email, cin, niveauEtude,nomFaculte,cycle) VALUES (?, ?, ?, ?,?,?,?)";

    if ($stmt_insert = $mysqli->prepare($sql_insert)) {
        $stmt_insert->bind_param("sssssss", $param_username,$param_lastname, $param_email, $param_cin, $param_niveauEtude,$param_cycle);

        $param_username = $username;
        $param_lastname=$lastname;
        $param_email = $email;
        $param_cin = $cin; 
        $param_niveauEtude=$niveau_etude;
        $param_cycle=$cycle;

        try {
            if ($stmt_insert->execute()) {
                $response['status'] = 'success';
                $response['message'] = 'Encadrant added successfully!';
                $response['etudiantID'] = $mysqli->insert_id; // Get the ID of the newly created user
            } else {
                $response['status'] = 'error';
                $response['message'] = 'Database error adding Student: ' . $stmt_insert->error;
            }
        } catch (mysqli_sql_exception $e) {
            $response['status'] = 'error';
            $error_message_from_db = $e->getMessage();
            $error_code_from_db = $e->getCode();

             
                $response['message'] = 'Database error adding encadrant: ' . $error_message_from_db;
                $response['error_code'] = $error_code_from_db; 
            
        }

        $stmt_insert->close();
    } else {
        $response['status'] = 'error';
        $response['message'] = 'Error preparing SQL statement for encadrant addition: ' . $mysqli->error;
    }
} else {
    $response['status'] = 'error';
    $response['message'] = 'Invalid request method. Only POST requests are allowed.';
}

$mysqli->close();
echo json_encode($response);
?>