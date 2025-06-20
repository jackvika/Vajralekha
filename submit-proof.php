<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database configuration
$db_host = 'localhost';
$db_name = 'inferno_complaints';
$db_user = 'your_username';
$db_pass = 'your_password';

// File upload configuration
$maxFileSize = 25 * 1024 * 1024; // 25MB
$allowedTypes = [
    'image/jpeg', 'image/png', 'image/gif',
    'video/mp4', 'application/pdf',
    'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
];

// Load PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require 'vendor/autoload.php';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate inputs
    $complaintId = filter_input(INPUT_POST, 'complaint-id', FILTER_SANITIZE_STRING);
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $proofType = filter_input(INPUT_POST, 'proof-type', FILTER_SANITIZE_STRING);
    $description = filter_input(INPUT_POST, 'description', FILTER_SANITIZE_STRING);

    // Validate required fields
    if (empty($complaintId) || empty($email)) {
        die('Complaint ID and email are required');
    }

    try {
        // Connect to database
        $pdo = new PDO("mysql:host=$db_host;dbname=$db_name", $db_user, $db_pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Verify complaint exists and belongs to this email
        $stmt = $pdo->prepare("SELECT id, first_name, last_name FROM complaints WHERE id = ? AND email = ?");
        $stmt->execute([$complaintId, $email]);
        
        if ($stmt->rowCount() === 0) {
            die('No complaint found with that ID and email combination');
        }

        $complaint = $stmt->fetch();
        $firstName = $complaint['first_name'];
        $lastName = $complaint['last_name'];

        // Handle file uploads
        $attachments = [];
        $totalSize = 0;

        if (!empty($_FILES['evidence']['name'][0])) {
            $uploadDir = 'uploads/proofs/' . $complaintId . '/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            foreach ($_FILES['evidence']['tmp_name'] as $key => $tmpName) {
                $fileSize = $_FILES['evidence']['size'][$key];
                $fileType = $_FILES['evidence']['type'][$key];
                $fileName = basename($_FILES['evidence']['name'][$key]);
                $filePath = $uploadDir . uniqid() . '_' . $fileName;
                
                $totalSize += $fileSize;
                
                if ($totalSize > $maxFileSize) {
                    die('Total file size exceeds 25MB limit');
                }
                
                if (!in_array($fileType, $allowedTypes)) {
                    die("File type not allowed: $fileType");
                }
                
                if (move_uploaded_file($tmpName, $filePath)) {
                    $attachments[] = $filePath;
                }
            }
        }

        // Store proof submission in database
        $stmt = $pdo->prepare("INSERT INTO proof_submissions 
                              (complaint_id, proof_type, description, submission_date) 
                              VALUES (?, ?, ?, NOW())");
        $stmt->execute([$complaintId, $proofType, $description]);
        $submissionId = $pdo->lastInsertId();

        // Store file references in database
        foreach ($attachments as $filePath) {
            $stmt = $pdo->prepare("INSERT INTO proof_files 
                                  (submission_id, file_path) 
                                  VALUES (?, ?)");
            $stmt->execute([$submissionId, $filePath]);
        }

        // Update complaint status
        $stmt = $pdo->prepare("UPDATE complaints 
                              SET status = 'In Progress', 
                                  last_updated = NOW() 
                              WHERE id = ?");
        $stmt->execute([$complaintId]);

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
        $mail->setFrom('earnbyapk@gmail.com', 'Inferno Complaints System');
        $mail->addAddress('vikasrg786@gmail.com');  // Primary recipient
        $mail->addReplyTo($email, "$firstName $lastName");
        $mail->addCC('earnbyapk@gmail.com');        // Your copy

        // Attachments
        foreach ($attachments as $attachment) {
            $mail->addAttachment($attachment);
        }

        // Content
        $mail->isHTML(true);
        $mail->Subject = "New Proof Submitted for Complaint #$complaintId";
        
        $proofTypeText = $proofType ?: 'Not specified';
        $attachmentCount = count($attachments);
        
        $mail->Body = "
            <h2 style='color:#8B0000;'>New Evidence Submitted</h2>
            <p><strong>Complaint ID:</strong> $complaintId</p>
            <p><strong>Submitted by:</strong> $firstName $lastName ($email)</p>
            <p><strong>Proof Type:</strong> $proofTypeText</p>
            <p><strong>Files Attached:</strong> $attachmentCount</p>
            
            <h3 style='color:#8B0000;'>Description:</h3>
            <div style='background:#222; padding:15px; border-left:3px solid #FF4500;'>
                " . nl2br($description ?: 'No description provided') . "
            </div>
            
            <p style='margin-top:20px;'>
                <a href='http://yourdomain.com/admin/complaint-view.php?id=$complaintId' 
                   style='background:#8B0000; color:white; padding:10px 15px; text-decoration:none; border-radius:5px;'>
                   View Complaint in Admin Panel
                </a>
            </p>
            
            <hr style='border-color:#333; margin:20px 0;'>
            <p><small>This is an automated notification. Do not reply directly to this email.</small></p>
        ";

        $mail->AltBody = "New proof submitted for Complaint #$complaintId\n\n"
                       . "Submitted by: $firstName $lastName ($email)\n"
                       . "Proof Type: $proofTypeText\n"
                       . "Files Attached: $attachmentCount\n\n"
                       . "Description:\n" . ($description ?: 'No description provided') . "\n\n"
                       . "View in admin panel: http://yourdomain.com/admin/complaint-view.php?id=$complaintId";

        $mail->send();

        // Send confirmation to complainant
        $mail->clearAddresses();
        $mail->addAddress($email, "$firstName $lastName");
        $mail->Subject = "Your Evidence Has Been Received (Complaint #$complaintId)";
        
        $mail->Body = "
            <h2 style='color:#8B0000;'>Evidence Received</h2>
            <p>Thank you for submitting additional evidence for your complaint <strong>#$complaintId</strong>.</p>
            
            <div style='background:#222; padding:15px; margin:15px 0; border-left:3px solid #FF4500;'>
                <p><strong>Type Submitted:</strong> $proofTypeText</p>
                <p><strong>Files Attached:</strong> $attachmentCount</p>
            </div>
            
            <p>We've forwarded your evidence to the concerned authorities and will notify you of any updates.</p>
            
            <p style='margin-top:20px;'>
                <a href='http://yourdomain.com/track-complaint.html?id=$complaintId' 
                   style='background:#8B0000; color:white; padding:10px 15px; text-decoration:none; border-radius:5px;'>
                   Track Your Complaint Status
                </a>
            </p>
            
            <hr style='border-color:#333; margin:20px 0;'>
            <p><small>This is an automated message. Please do not reply directly to this email.</small></p>
        ";

        $mail->send();

        // Redirect to success page
        header("Location: proof-success.html?id=$submissionId&complaint_id=$complaintId");
        exit();

    } catch (Exception $e) {
        error_log("Proof submission failed: " . $e->getMessage());
        die("Sorry, we encountered an error processing your evidence. Please try again later.");
    }
} else {
    header("Location: proof-guidelines.html");
    exit();
}
