<?php
session_start();
include "../../config/database.php";

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION["user_id"])) {
    echo json_encode(["success" => false, "message" => "Unauthorized access"]);
    exit();
}

// Validate request
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['message_id']) || !isset($_POST['reply_content']) || !isset($_POST['recipient_email'])) {
    echo json_encode(["success" => false, "message" => "Invalid request"]);
    exit();
}

$message_id = (int)$_POST['message_id'];
$reply_content = $_POST['reply_content'];
$recipient_email = $_POST['recipient_email'];
$subject = $_POST['subject'] ?? 'Re: Your message to NCIP';

// Send email (you may need to configure mail settings)
$headers = "From: admin@ncip.gov.ph\r\n";
$headers .= "Reply-To: admin@ncip.gov.ph\r\n";
$headers .= "Content-Type: text/html; charset=UTF-8\r\n";

$email_sent = mail($recipient_email, $subject, $reply_content, $headers);

if ($email_sent) {
    try {
        // Update message status to replied
        $update_stmt = $pdo->prepare("UPDATE CONTACT_MESSAGE SET CONTM_STATUS = :status, CONTM_REPLY = :reply_content, CONTM_REPLY_DATE = CURRENT_TIMESTAMP WHERE CONTM_ID = :message_id");
        
        // Bind parameters with proper types
        $status = 'Replied'; // Note the case sensitivity for PostgreSQL
        $update_stmt->bindParam(':status', $status, PDO::PARAM_STR);
        $update_stmt->bindParam(':reply_content', $reply_content, PDO::PARAM_STR);
        $update_stmt->bindParam(':message_id', $message_id, PDO::PARAM_INT);
        
        $result = $update_stmt->execute();
        
        if ($result) {
            echo json_encode([
                "success" => true,
                "message" => "Reply sent successfully"
            ]);
        } else {
            echo json_encode([
                "success" => false, 
                "message" => "Failed to update message status"
            ]);
        }
    } catch (PDOException $e) {
        error_log("Error updating message status: " . $e->getMessage());
        echo json_encode([
            "success" => false, 
            "message" => "Database error: " . $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        "success" => false, 
        "message" => "Error sending email. Please check your mail configuration."
    ]);
}
?>