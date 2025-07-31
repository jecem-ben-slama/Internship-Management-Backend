<?php
// Include necessary files
// These files contain crucial external logic and cannot be directly merged without their content.
require_once '../db_connect.php';    // Contains $mysqli database connection
require_once '../verify_token.php';  // Contains verifyJwtToken() function

// PHPMailer Autoload (assuming you installed it via Composer in your 'backend' folder)
require '../vendor/autoload.php';

// Import PHPMailer and Dompdf classes into the global namespace
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Dompdf\Dompdf;
use Dompdf\Options;

// -----------------------------------------------------------------------------
// CORS headers - crucial for Flutter Web
// -----------------------------------------------------------------------------
header("Access-Control-Allow-Origin: *"); // Allow all origins for development
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization"); // Allow Content-Type and Authorization headers
header("Access-Control-Max-Age: 3600"); // Cache preflight requests for 1 hour
header("Access-Control-Allow-Credentials: true");

// Handle preflight OPTIONS requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

header('Content-Type: application/json'); // Set content type to JSON

$response = array(); // Initialize response array

// -----------------------------------------------------------------------------
// Security: Verify JWT token and get user data
// -----------------------------------------------------------------------------
$userData = verifyJwtToken(); // Expected to return ['userID', 'username', 'role'] or exit if invalid

// Define allowed roles for accessing this endpoint
$allowedRoles = ['ChefCentreInformatique']; // Only Chef is allowed to update internship status this way

if (!in_array($userData['role'], $allowedRoles)) {
    http_response_code(403); // Forbidden
    echo json_encode(['status' => 'error', 'message' => 'Access denied. You do not have permission to update internship status.']);
    $mysqli->close();
    exit();
}

// -----------------------------------------------------------------------------
// Utility Functions (formerly in send_email.php and the PDF generation block)
// -----------------------------------------------------------------------------

/**
 * Sends an internship acceptance email to the student with an optional PDF attachment link.
 * This function was formerly in send_email.php
 *
 * @param string $recipientEmail The student's email address.
 * @param string $recipientName The student's full name.
 * @param string $subjectTitle The title of the accepted internship subject.
 * @param string $pdfUrl (Optional) The URL to the generated PDF acceptance letter.
 * @return array An associative array with 'status' (success/error) and 'message'.
 */
function sendInternshipAcceptanceEmail($recipientEmail, $recipientName, $subjectTitle, $pdfUrl = '') {
    $mail = new PHPMailer(true); // Enable exceptions

    try {
        // Server settings (replace with your actual SMTP settings)
     
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com'; // Your SMTP host (e.g., smtp.gmail.com for Gmail)
        $mail->SMTPAuth   = true;
        // IMPORTANT: Replace with your actual email and App Password
        $mail->Username   = 'benslemajecem@gmail.com'; // Your SMTP username (e.g., your Gmail address)
        $mail->Password   = 'igwb bzxt cyqr smxp';   // Your Gmail App Password (NOT your regular password)
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // Use TLS encryption
        $mail->Port       = 587; // TLS port

        // Recipients
        $mail->setFrom('benslemajecem@gmail.com', 'Steg Internship Office'); // Your sender email and name
        $mail->addAddress($recipientEmail, $recipientName); // Add a recipient

        // Content
        $mail->isHTML(true); // Set email format to HTML
        $mail->Subject = 'Internship Acceptance: ' . htmlspecialchars($subjectTitle);

        $body = '
            <p>Dear ' . htmlspecialchars($recipientName) . ',</p>
            <p>We are thrilled to inform you that your internship application  has been officially **Accepted** </p>
            <p>This is a fantastic opportunity, and we are excited to have you join us.</p>';

        if (!empty($pdfUrl)) {
            $body .= '<p>You can download your official acceptance letter here: <a href="' . htmlspecialchars($pdfUrl) . '">Download Acceptance Letter</a></p>';
        } else {
            $body .= '<p>An official acceptance letter will be provided to you soon.</p>';
        }

        $body .= '
            <p>Further details regarding your internship will be communicated to you soon by the internship office.</p>
            <p>Congratulations and welcome aboard!</p>
            <p>Sincerely,</p>
            <p>The Internship Management Team</p>';

        $mail->Body = $body;
        $mail->AltBody = 'Dear ' . htmlspecialchars($recipientName) . ', Your internship application has been accepted. Download your acceptance letter here: ' . htmlspecialchars($pdfUrl) . ' Regards.';

        $mail->send();
        return ['status' => 'success', 'message' => 'Acceptance email sent successfully.'];
    } catch (Exception $e) {
        // Log the error for debugging, but don't expose too much detail to the client
        error_log("Message could not be sent. Mailer Error: {$mail->ErrorInfo}");
        return ['status' => 'error', 'message' => 'Failed to send acceptance email. Please try again later.'];
    }
}

/**
 * Generates an acceptance PDF for an internship and saves it to the Files directory.
 *
 * @param string $studentName The full name of the student.
 * @param string $subjectTitle The title of the internship subject.
 * @param int $internshipId The ID of the internship.
 * @return array An array with 'success' (boolean) and 'filePath' (string) or 'error' (string).
 */
function generateAndSaveAcceptancePDF($studentName, $subjectTitle, $internshipId) {
    // PDF options
    $options = new Options();
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isRemoteEnabled', true); // Enable remote access for images, if any
    $options->set('defaultFont', 'DejaVu Sans'); // Set a default font for better Unicode support

    // Instantiate Dompdf
    $dompdf = new Dompdf($options);

    // Get current date in Tunisia timezone for the letter
    // Using current time as of query date: Tuesday, July 29, 2025 at 10:14:42 AM CET.
    $dateTime = new DateTime('2025-07-29 10:14:42', new DateTimeZone('Africa/Tunis'));
    $currentDate = $dateTime->format('F j, Y'); // e.g., July 29, 2025

    // HTML content for the PDF
    // Using htmlspecialchars to prevent XSS if these values came from user input directly (though here they are from DB)
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="utf-8">
        <title>Internship Acceptance Letter - ISIMM</title>
        <style>
            body { font-family: DejaVu Sans, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; }
            .container { width: 90%; margin: 20px auto; padding: 30px; border: 1px solid #ddd; border-radius: 8px; background-color: #fdfdfd; box-shadow: 0 0 10px rgba(0,0,0,0.05); }
            h1 { color: #0056b3; text-align: center; margin-bottom: 25px; border-bottom: 2px solid #0056b3; padding-bottom: 10px; }
            p { margin-bottom: 15px; font-size: 1.1em; }
            .date { text-align: right; margin-bottom: 30px; font-style: italic; color: #555; }
            .signature { margin-top: 60px; text-align: right; }
            .signature p { margin: 5px 0; }
            .footer { text-align: center; margin-top: 40px; font-size: 0.9em; color: #666; border-top: 1px solid #eee; padding-top: 15px; }
            strong { color: #000; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="date">Monastir, ' . $currentDate . '</div>
            <h1>Internship Acceptance Letter</h1>
            <p>Dear ' . htmlspecialchars($studentName) . ',</p>
            <p>We are pleased to inform you that your internship application :</p>
            <p style="font-size: 1.2em; text-align: center; margin: 20px 0;"><strong>"' . htmlspecialchars($subjectTitle) . '"</strong></p>
            <p>has been officially **accepted** at Steg CTI.</p>
            <p>We believe this internship will provide you with valuable experience and an excellent opportunity to enhance your skills and contribute to our projects.</p>
            <p>Further details regarding the start date, duration, your supervisor, and other arrangements will be communicated to you shortly by the internship office.</p>
            <p>We look forward to welcoming you to our team.</p>
            <p>Sincerely,</p>
            <div class="signature">
                <p><strong>The Director of Internships</strong></p>
                <p>Steg</p>
                <p>Sfax, Tunisia</p>
            </div>
            <div class="footer">
                <p>STEG CTI</p>
            </div>
        </div>
    </body>
    </html>';

    $dompdf->loadHtml($html);

    // (Optional) Set paper size and orientation
    $dompdf->setPaper('A4', 'portrait');

    // Render the HTML as PDF
    $dompdf->render();

    // Define the path to save the PDF
    // Based on "http://localhost/Backend/Files", your 'Files' folder should be directly inside 'Backend'.
    // So, if update_internship_status.php is in "Backend/ChefCentre/", then "../Files/" means "Backend/Files/".
    // Using __DIR__ to make path relative to the current script's directory.
    // Assuming this script is in 'backend/ChefCentre/', and 'Files' is in 'backend/Files/'
    $outputDirectory = __DIR__ . '/../Files/'; // This means 'backend/ChefCentre/../Files/' -> 'backend/Files/'
    
    // Make sure the 'Files' directory exists inside your 'backend' directory
    if (!is_dir($outputDirectory)) {
        // Attempt to create the directory with full permissions
        if (!mkdir($outputDirectory, 0777, true)) {
            return ['success' => false, 'error' => 'Failed to create directory: ' . $outputDirectory];
        }
    }

    // Generate a unique filename to avoid overwrites
    $filename = 'Internship_Acceptance_' . $internshipId . '_' . uniqid() . '.pdf';
    $filePath = $outputDirectory . $filename;

    // Save the PDF to a file
    if (file_put_contents($filePath, $dompdf->output())) {
        // Construct the accessible URL for the PDF
        // This assumes your web server serves files directly from the Backend directory's Files subfolder
        $pdfBaseUrl = "http://localhost/Backend/Files/";
        $pdfUrl = $pdfBaseUrl . $filename;
        return ['success' => true, 'filePath' => $filePath, 'fileUrl' => $pdfUrl];
    } else {
        return ['success' => false, 'error' => 'Could not write PDF to file. Check folder permissions: ' . $filePath];
    }
}


// -----------------------------------------------------------------------------
// Main Request Handling Logic
// -----------------------------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] == "PUT") {
    // Get raw PUT data (JSON body)
    $input = file_get_contents('php://input');
    $data = json_decode($input, true); // Decode JSON into an associative array

    $internshipId = isset($data['stageID']) ? (int)$data['stageID'] : null;
    $newStatus = isset($data['statut']) ? $data['statut'] : null;

    if (!$internshipId) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Internship ID (stageID) is required in the request body.']);
        $mysqli->close();
        exit();
    }

    if (!$newStatus) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'New status is required in the request body.']);
        $mysqli->close();
        exit();
    }

    // Prepare the update statement for the database
    $stmt = $mysqli->prepare("UPDATE stages SET statut = ? WHERE stageID = ?");
    if ($stmt === false) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Failed to prepare update statement: ' . $mysqli->error]);
        $mysqli->close();
        exit();
    }
    $stmt->bind_param("si", $newStatus, $internshipId); // 's' for string, 'i' for integer

    // Execute the update
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            // If the status was successfully updated AND the new status is 'Accepté'
            if ($newStatus === 'Accepted') {
                $studentEmail = '';
                $studentName = '';
                $subjectTitle = '';
                $pdfUrl = ''; // Initialize PDF URL

                // Fetch student's email, full name, and internship subject title
                $getEmailStmt = $mysqli->prepare("
                    SELECT
                        e.email,
                        e.username AS studentUsername,
                        e.lastname AS studentLastname,
                        s.titre AS subjectTitle
                    FROM
                        stages st
                    JOIN
                        etudiants e ON st.etudiantID = e.etudiantID
                    LEFT JOIN                     -- Use LEFT JOIN to ensure all stages are returned, even if sujetID is NULL
                        sujetsstage s ON st.sujetID = s.sujetID
                    WHERE
                        st.stageID = ?
                ");
                if ($getEmailStmt) {
                    $getEmailStmt->bind_param("i", $internshipId);
                    $getEmailStmt->execute();
                    $emailResult = $getEmailStmt->get_result();
                    if ($emailResult->num_rows > 0) {
                        $row = $emailResult->fetch_assoc();
                        $studentEmail = $row['email'];
                        $studentName = trim($row['studentUsername'] . ' ' . $row['studentLastname']);
                        $subjectTitle = $row['subjectTitle'] ?? 'N/A'; // Use null coalescing for subjectTitle if it's NULL

                        // --- Generate and Save PDF ---
                        $pdfResult = generateAndSaveAcceptancePDF($studentName, $subjectTitle, $internshipId);
                        if ($pdfResult['success']) {
                            $pdfUrl = $pdfResult['fileUrl'];
                            $response['pdf_status'] = 'PDF generated and saved successfully.';
                            $response['pdf_url_generated'] = $pdfUrl; // Add URL to response for debugging/info
                        } else {
                            $response['pdf_status'] = 'Error generating PDF: ' . $pdfResult['error'];
                            error_log("Error generating PDF for internship ID " . $internshipId . ": " . $pdfResult['error']);
                        }
                        // --- End PDF Generation ---

                        // Call the function to send email, passing the PDF URL
                        $emailSendResult = sendInternshipAcceptanceEmail($studentEmail, $studentName, $subjectTitle, $pdfUrl);
                        $response['email_status'] = $emailSendResult['message'];

                    } else {
                        // This block should ideally not be hit if data is consistently linked.
                        // However, it handles cases where the JOINs fail to find student/subject data.
                        $response['email_status'] = 'Student email or subject title not found for internship ID ' . $internshipId . '. The database query for fetching student/subject data returned no rows.';
                        error_log("Student email or subject title not found for internship ID " . $internshipId);
                    }
                    $getEmailStmt->close();
                } else {
                    $response['email_status'] = 'Failed to prepare statement to fetch student email: ' . $mysqli->error;
                    error_log("Failed to prepare statement to fetch student email: " . $mysqli->error);
                }
            }

            // Success response for the API call
            http_response_code(200);
            $response['status'] = 'success';
            $response['message'] = 'Internship status updated successfully.';
            $response['data'] = ['stageID' => $internshipId, 'statut' => $newStatus];
            // If PDF was generated, add its URL to the main response
            if (!empty($pdfUrl)) {
                $response['data']['pdf_url'] = $pdfUrl;
            }
        } else {
            // No rows affected implies internship ID not found or status was already the same
            http_response_code(404); // Not Found
            $response['status'] = 'error';
            $response['message'] = 'Internship not found or status already matches the provided value.';
        }
    } else {
        // SQL execution failed
        http_response_code(500); // Internal Server Error
        $response['status'] = 'error';
        $response['message'] = 'Failed to execute update statement: ' . $stmt->error;
    }
    $stmt->close(); // Close the prepared statement
} else {
    http_response_code(405); // Method Not Allowed
    $response['status'] = 'error';
    $response['message'] = 'Invalid request method. Only PUT requests are allowed.';
}

$mysqli->close(); // Close database connection
echo json_encode($response); // Output the JSON response
?>