<?php
// PHPMailer Autoload (assuming it's already included via Composer in your main script)
// require 'vendor/autoload.php'; // No need to include here if already in the calling script

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Sends an internship acceptance email to the student with an optional PDF attachment link.
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
        $mail->Username   = 'your_email@gmail.com'; // Your SMTP username (e.g., your Gmail address)
        $mail->Password   = 'your_app_password';   // Your Gmail App Password (NOT your regular password)
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // Use TLS encryption
        $mail->Port       = 587; // TLS port

        // Recipients
        $mail->setFrom('your_email@gmail.com', 'ISIMM Internship Office'); // Your sender email and name
        $mail->addAddress($recipientEmail, $recipientName); // Add a recipient

        // Content
        $mail->isHTML(true); // Set email format to HTML
        $mail->Subject = 'Internship Acceptance: ' . htmlspecialchars($subjectTitle);
        
        $body = '
            <p>Dear ' . htmlspecialchars($recipientName) . ',</p>
            <p>We are thrilled to inform you that your internship application for the subject: <strong>"' . htmlspecialchars($subjectTitle) . '"</strong> has been officially **accepted** at the Higher Institute of Computer Science and Mathematics of Monastir (ISIMM)!</p>
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
            <p>The Internship Management Team<br>Higher Institute of Computer Science and Mathematics of Monastir (ISIMM)</p>';

        $mail->Body = $body;
        $mail->AltBody = 'Dear ' . htmlspecialchars($recipientName) . ', Your internship application for "' . htmlspecialchars($subjectTitle) . '" has been accepted. Download your acceptance letter here: ' . htmlspecialchars($pdfUrl) . ' Regards, ISIMM Internship Office.';

        $mail->send();
        return ['status' => 'success', 'message' => 'Acceptance email sent successfully.'];
    } catch (Exception $e) {
        // Log the error for debugging, but don't expose too much detail to the client
        error_log("Message could not be sent. Mailer Error: {$mail->ErrorInfo}");
        return ['status' => 'error', 'message' => 'Failed to send acceptance email. Please try again later.'];
    }
}
?>