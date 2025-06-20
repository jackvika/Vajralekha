<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Load PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require 'vendor/autoload.php';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate inputs
    $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $subject = filter_input(INPUT_POST, 'subject', FILTER_SANITIZE_STRING);
    $message = filter_input(INPUT_POST, 'message', FILTER_SANITIZE_STRING);

    // Validate required fields
    if (empty($name) || empty($email) || empty($subject) || empty($message)) {
        die('Please fill all required fields');
    }

    try {
        // Configure PHPMailer with Gmail
        $mail = new PHPMailer(true);
        
        // Server settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'earnbyapk@gmail.com'; // Your Gmail
        $mail->Password   = 'your_app_password';    // Gmail App Password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = 465;

        // Recipients
        $mail->setFrom('earnbyapk@gmail.com', 'Inferno Complaints Support');
        $mail->addAddress('vikasrg786@gmail.com');  // Primary recipient
        $mail->addReplyTo($email, $name);
        $mail->addCC('earnbyapk@gmail.com');        // Your copy

        // Content
        $mail->isHTML(true);
        $mail->Subject = "Support Request: " . ucfirst($subject);
        
        $mail->Body = "
            <h2 style='color:#8B0000;'>New Support Request</h2>
            <p><strong>From:</strong> $name ($email)</p>
            <p><strong>Subject:</strong> " . ucfirst($subject) . "</p>
            
            <div style='background:#222; padding:15px; border-left:3px solid #FF4500; margin:15px 0;'>
                " . nl2br($message) . "
            </div>
            
            <hr style='border-color:#333; margin:20px 0;'>
            <p><small>This is an automated message. Please reply directly to this email to respond.</small></p>
        ";

        $mail->AltBody = "Support Request\n\n"
                       . "From: $name ($email)\n"
                       . "Subject: " . ucfirst($subject) . "\n\n"
                       . $message . "\n\n"
                       . "Please reply to this email to respond.";

        $mail->send();

        // Send confirmation to user
        $mail->clearAddresses();
        $mail->addAddress($email, $name);
        $mail->Subject = "We've Received Your Support Request";
        
        $mail->Body = "
            <h2 style='color:#8B0000;'>Support Request Received</h2>
            <p>Thank you for contacting Inferno Complaints support. We've received your message and will respond within 48 hours.</p>
            
            <div style='background:#222; padding:15px; border-left:3px solid #FF4500; margin:15px 0;'>
                <p><strong>Subject:</strong> " . ucfirst($subject) . "</p>
                <p>" . nl2br($message) . "</p>
            </div>
            
            <p>If you need immediate assistance, please check our <a href='http://yourdomain.com/help.html' style='color:#FF4500;'>Help Center</a> for answers to common questions.</p>
            
            <hr style='border-color:#333; margin:20px 0;'>
            <p><small>This is an automated message. Please do not reply directly to this email.</small></p>
        ";

        $mail->send();

        // Redirect to success page
        header("Location: contact-success.html");
        exit();

    } catch (Exception $e) {
        error_log("Support request failed: " . $e->getMessage());
        die("Sorry, we encountered an error processing your request. Please try again later.");
    }
} else {
    header("Location: help.html");
    exit();
}
