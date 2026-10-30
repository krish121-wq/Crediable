<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php'; // Install via composer

function sendEmailViaSMTP($to, $subject, $body, $fromEmail, $fromName = 'GrowBiller') {
    $mail = new PHPMailer(true);
    try {
        // Use your SMTP credentials (Gmail or hosting SMTP)
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com'; // or your hosting SMTP
        $mail->SMTPAuth   = true;
        $mail->Username   = 'your-email@gmail.com'; // Your email
        $mail->Password   = 'your-app-password'; // Use app password for Gmail
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        
        $mail->setFrom($fromEmail, $fromName);
        $mail->addAddress($to);
        $mail->isHTML(false);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        
        return $mail->send();
    } catch (Exception $e) {
        return false;
    }
}
?>