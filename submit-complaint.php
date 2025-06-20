<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database configuration (optional)
$db_host = 'localhost';
$db_name = 'inferno_complaints';
$db_user = 'your_username';
$db_pass = 'your_password';

// Load PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

require 'vendor/autoload.php'; // Path to PHPMailer autoload

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate and sanitize input
    $firstName = filter_input(INPUT_POST, 'first-name', FILTER_SANITIZE_STRING);
    $lastName = filter_input(INPUT_POST, 'last-name', FILTER_SANITIZE_STRING);
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $phone = filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_STRING);
    $complaintType = filter_input(INPUT_POST, 'complaint-type', FILTER_SANITIZE_STRING);
    $date = filter_input(INPUT_POST, 'date', FILTER_SANITIZE_STRING);
    $location = filter_input(INPUT_POST, 'location', FILTER_SANITIZE_STRING);
    $subject = filter_input(INPUT_POST, 'subject', FILTER_SANITIZE_STRING);
    $description = filter_input(INPUT_POST, 'description', FILTER_SANITIZE_STRING);
    $authority = filter_input(INPUT_POST, 'authority', FILTER_SANITIZE_STRING);

    // Validate required fields
    $required = ['first-name', 'last-name', 'email', 'complaint-type', 'date', 'location', 'subject', 'description'];
    foreach ($required as $field) {
        if (empty($_POST[$field])) {
            die('Please fill all required fields');
        }
    }

    try {
        // Connect to database (if using)
        $pdo = new PDO("mysql:host=$db_host;dbname=$db_name", $db_user, $db_pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Store complaint in database
        $stmt = $pdo->prepare("INSERT INTO complaints 
                              (first_name, last_name, email, phone, complaint_type, 
                               incident_date, location, subject, description, authority, 
                               submission_date) 
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$firstName, $lastName, $email, $phone, $complaintType, 
                       $date, $location, $subject, $description, $authority]);
        $complaintId = $pdo->lastInsertId();

        // Handle file uploads
        $attachments = [];
        if (!empty($_FILES['evidence']['name'][0])) {
            $uploadDir = 'uploads/complaints/' . $complaintId . '/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            foreach ($_FILES['evidence']['tmp_name'] as $key => $tmpName) {
                $fileName = basename($_FILES['evidence']['name'][$key]);
                $filePath = $uploadDir . $fileName;
                
                if (move_uploaded_file($tmpName, $filePath)) {
                    $attachments[] = $filePath;
                }
            }
        }

        // Configure Gmail SMTP
        $mail = new PHPMailer(true);
        
        // Server settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'earnbyapk@gmail.com'; // Your Gmail address
        $mail->Password   = 'your_app_password';   // Gmail App Password (see note below)
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // SSL/TLS
        $mail->Port       = 465;

        // Sender and recipient
        $mail->setFrom('earnbyapk@gmail.com', 'Inferno Complaints System');
        $mail->addAddress('authorities@example.com'); // Official recipient
        $mail->addReplyTo($email, "$firstName $lastName");
        $mail->addCC('earnbyapk@gmail.com'); // Your archive copy

        // Attachments
        foreach ($attachments as $attachment) {
            $mail->addAttachment($attachment);
        }

        // Content
        $mail->isHTML(true);
        $mail->Subject = "New Complaint: $subject (ID: $complaintId)";
        
        $mail->Body = "
            <h2>New Complaint Submitted</h2>
            <p><strong>Complaint ID:</strong> $complaintId</p>
            <p><strong>Name:</strong> $firstName $lastName</p>
            <p><strong>Email:</strong> $email</p>
            <p><strong>Phone:</strong> " . ($phone ?: 'Not provided') . "</p>
            <p><strong>Type:</strong> $complaintType</p>
            <p><strong>Date of Incident:</strong> $date</p>
            <p><strong>Location:</strong> $location</p>
            <p><strong>Concerned Authority:</strong> " . ($authority ?: 'Not specified') . "</p>
            <h3>Complaint Details:</h3>
            <p>" . nl2br($description) . "</p>
            <p>" . (count($attachments) ? "Attachments: " . count($attachments) : 'No attachments') . "</p>
            <hr>
            <p>This complaint has been logged in our system. Reference ID: $complaintId</p>
        ";

        $mail->AltBody = strip_tags(str_replace("<br>", "\n", $mail->Body));

        $mail->send();

        // Send confirmation to complainant
        $mail->clearAddresses();
        $mail->addAddress($email, "$firstName $lastName");
        $mail->Subject = "Your Complaint Has Been Received (ID: $complaintId)";
        $mail->Body = "
            <h2>Thank You for Your Complaint</h2>
            <p>We've received your complaint and assigned it reference ID: <strong>$complaintId</strong></p>
            <h3>Complaint Summary:</h3>
            <p><strong>Subject:</strong> $subject</p>
            <p><strong>Type:</strong> $complaintType</p>
            <p><strong>Location:</strong> $location</p>
            <p><strong>Date of Incident:</strong> $date</p>
            <p>We'll process your complaint and follow up with the concerned authorities.</p>
            <p>You can track the status of your complaint using your reference ID.</p>
            <hr>
            <p><small>This is an automated message. Please do not reply directly to this email.</small></p>
        ";

        $mail->send();

        // Redirect to success page
        header("Location: complaint-success.html?id=$complaintId");
        exit();

    } catch (Exception $e) {
        error_log("Complaint submission failed: " . $e->getMessage());
        die("Sorry, we encountered an error processing your complaint. Please try again later.");
    }
} else {
    header("Location: submit-complaint.html");
    exit();
}
