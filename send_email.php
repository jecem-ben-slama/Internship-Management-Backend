<?php
// send_email.php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Load Composer's autoloader for PHPMailer
require_once __DIR__ . '/vendor/autoload.php';

// Include the sensitive mail configuration file
// IMPORTANT: Ensure mail_config.php is in your .gitignore!
// Corrected path: Added a directory separator '/'
require_once __DIR__ . '/mail_config.php'; 

function sendInternshipAcceptanceEmail($recipientEmail, $recipientName, $subjectTitle) {
    $mail = new PHPMailer(true); // true enables exceptions

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_SECURE;
        $mail->Port       = SMTP_PORT;

        // Recipients
        $mail->setFrom(SENDER_EMAIL, SENDER_NAME);
        $mail->addAddress($recipientEmail, $recipientName);

        // Content
        $mail->isHTML(false); // Set to true if you want to send HTML emails
        $mail->Subject = "Your Internship Application for '$subjectTitle' has been ACCEPTED!";
        $message_body = "Dear $recipientName,\n\n";
        $message_body .= "We are pleased to inform you that your internship application for the subject: '$subjectTitle' has been ACCEPTED.\n\n";
        $message_body .= "Congratulations!\n\n";
        $message_body .= "Sincerely,\nYour Internship Management Team";
        $mail->Body    = $message_body;
        $mail->AltBody = $message_body; // Plain text version for non-HTML mail clients

        $mail->send();
        return ['status' => 'success', 'message' => 'Email sent to student via SMTP.'];
    } catch (Exception $e) {
        // Log the error for debugging purposes
        error_log("PHPMailer Error sending acceptance email to $recipientEmail: {$mail->ErrorInfo}");
        return ['status' => 'error', 'message' => "Email could not be sent. Mailer Error: {$mail->ErrorInfo}"];
    }
}
?>