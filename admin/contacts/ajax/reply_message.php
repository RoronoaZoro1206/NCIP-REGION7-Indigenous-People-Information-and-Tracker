<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include "../../config/database.php";

header('Content-Type: application/json');

// Set Philippine timezone for accurate timestamps
date_default_timezone_set('Asia/Manila');

// Check if user is logged in
if (!isset($_SESSION["user_id"])) {
    echo json_encode(["success" => false, "message" => "Unauthorized access"]);
    exit();
}

// Check admin role
$user_role = $_SESSION["role"] ?? $_SESSION["user_role"] ?? null;
if (!$user_role || strtoupper($user_role) !== 'ADMIN') {
    echo json_encode(["success" => false, "message" => "Access denied. Admin privileges required."]);
    exit();
}

/**
 * ENSURE REPLY COLUMNS EXIST BEFORE ANY OPERATIONS
 */
function ensureReplyColumnsExist($pdo) {
    try {
        // Check if columns exist, if not, add them
        $columns_to_add = [
            'contm_reply' => 'TEXT',
            'contm_reply_date' => 'TIMESTAMP NULL'
        ];
        
        foreach ($columns_to_add as $column => $definition) {
            // Check if column exists
            $stmt = $pdo->prepare("SELECT column_name FROM information_schema.columns 
                                  WHERE table_name = 'contact_message' AND column_name = :column_name");
            $stmt->execute([':column_name' => $column]);
            
            if (!$stmt->fetch()) {
                // Column doesn't exist, add it
                $pdo->exec("ALTER TABLE contact_message ADD COLUMN {$column} {$definition}");
                error_log("Added column {$column} to contact_message");
            }
        }
        
    } catch (PDOException $e) {
        error_log("Error ensuring reply columns: " . $e->getMessage());
        return false;
    }
    return true;
}

// ENSURE COLUMNS EXIST FIRST
if (!ensureReplyColumnsExist($pdo)) {
    echo json_encode(["success" => false, "message" => "Database setup error. Please contact administrator."]);
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

// Validate email
if (!filter_var($recipient_email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(["success" => false, "message" => "Invalid email address"]);
    exit();
}

// Include message ID in subject for tracking (SAME FORMAT AS sync_email_replies.php)
$subject = $_POST['subject'] ?? "Re: Your message to NCIP [ID: {$message_id}]";

// Make sure the subject always includes the ID format for sync compatibility
if (!preg_match('/\[ID:\s*\d+\]/', $subject)) {
    $subject = $subject . " [ID: {$message_id}]";
}

// EMAIL CONFIGURATION - SAME AS sync_email_replies.php
$email_config = [
    'smtp_host' => 'smtp.gmail.com',
    'smtp_port' => 587,
    'username' => 'lawrenceramirez626@gmail.com',
    'password' => 'hjlp fqfe pjvt nbjd', // SAME PASSWORD AS sync_email_replies.php
    'encryption' => 'tls'
];

$email_sent = false;
$email_error = "";

// ENHANCED EMAIL SENDING WITH PROPER GMAIL SMTP
try {
    // Check if PHPMailer is available
    if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        require_once '../../vendor/autoload.php';
    }
    
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    
    // SMTP Configuration - ENHANCED FOR GMAIL
    $mail->isSMTP();
    $mail->Host = $email_config['smtp_host'];
    $mail->SMTPAuth = true;
    $mail->Username = $email_config['username'];
    $mail->Password = $email_config['password'];
    $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = $email_config['smtp_port'];
    
    // ENHANCED SMTP OPTIONS TO PREVENT DRAFT ISSUES
    $mail->SMTPOptions = array(
        'ssl' => array(
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
        )
    );
    
    // DEBUGGING (remove in production)
    $mail->SMTPDebug = 0; // Set to 2 for detailed debugging
    $mail->Debugoutput = function($str, $level) {
        error_log("PHPMailer Debug: $str");
    };
    
    // Email headers and settings
    $mail->setFrom($email_config['username'], 'NCIP Admin');
    $mail->addAddress($recipient_email);
    $mail->addReplyTo($email_config['username'], 'NCIP Admin');
    
    // IMPORTANT: Set proper encoding and content type
    $mail->CharSet = 'UTF-8';
    $mail->Encoding = 'base64';
    $mail->isHTML(true);
    
    // Subject and body
    $mail->Subject = $subject;
    
    // FORMAT REPLY CONTENT WITH PROPER HTML STRUCTURE
    $formatted_body = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .header { background: #2E5984; color: white; padding: 15px; margin-bottom: 20px; }
            .content { padding: 15px; }
            .footer { background: #f8f9fa; padding: 10px; margin-top: 20px; font-size: 12px; color: #666; }
        </style>
    </head>
    <body>
        <div class='header'>
            <h2>National Commission on Indigenous Peoples</h2>
            <p>Official Response to Your Message</p>
        </div>
        <div class='content'>
            " . nl2br(htmlspecialchars($reply_content)) . "
        </div>
        <div class='footer'>
            <p>This is an official response from NCIP Admin. Message ID: {$message_id}</p>
            <p>Generated on: " . date('F d, Y \a\t g:i A') . "</p>
        </div>
    </body>
    </html>";
    
    $mail->Body = $formatted_body;
    
    // Alternative plain text body for non-HTML email clients
    $mail->AltBody = strip_tags($reply_content) . "\n\n---\nNCIP Admin Response\nMessage ID: {$message_id}\nGenerated on: " . date('F d, Y \a\t g:i A');
    
    // Add custom headers for better email tracking and threading
    $mail->addCustomHeader('X-Priority', '3');
    $mail->addCustomHeader('Message-ID', "<ncip-msg-{$message_id}-" . time() . "@ncip.gov.ph>");
    $mail->addCustomHeader('References', "<contact-{$message_id}@ncip.gov.ph>");
    $mail->addCustomHeader('In-Reply-To', "<contact-{$message_id}@ncip.gov.ph>");
    
    // SEND THE EMAIL
    $result = $mail->send();
    
    if ($result) {
        $email_sent = true;
        error_log("Email sent successfully to: $recipient_email with subject: $subject");
    } else {
        $email_error = "Failed to send email via SMTP";
        error_log("Email send failed: " . $mail->ErrorInfo);
    }
    
} catch (PHPMailer\PHPMailer\Exception $e) {
    $email_error = "PHPMailer Exception: " . $e->getMessage();
    error_log("PHPMailer Exception: " . $e->getMessage());
} catch (Exception $e) {
    $email_error = "General Exception: " . $e->getMessage();
    error_log("General Exception: " . $e->getMessage());
}

// FALLBACK METHOD - Try basic mail() if PHPMailer fails
if (!$email_sent) {
    try {
        // Configure mail headers
        $headers = array();
        $headers[] = "From: NCIP Admin <{$email_config['username']}>";
        $headers[] = "Reply-To: {$email_config['username']}";
        $headers[] = "Return-Path: {$email_config['username']}";
        $headers[] = "Content-Type: text/html; charset=UTF-8";
        $headers[] = "Content-Transfer-Encoding: 8bit";
        $headers[] = "Message-ID: <ncip-msg-{$message_id}-" . time() . "@ncip.gov.ph>";
        $headers[] = "X-Mailer: NCIP Admin System";
        $headers[] = "X-Priority: 3";
        
        $plain_body = nl2br(htmlspecialchars($reply_content)) . 
                     "<br><br>---<br>NCIP Admin Response<br>Message ID: {$message_id}<br>Generated on: " . 
                     date('F d, Y \a\t g:i A');
        
        $email_sent = mail($recipient_email, $subject, $plain_body, implode("\r\n", $headers));
        
        if ($email_sent) {
            error_log("Email sent via mail() function to: $recipient_email");
        } else {
            $email_error = "Both PHPMailer and mail() function failed";
            error_log("Both email methods failed");
        }
        
    } catch (Exception $e) {
        $email_error = "Fallback mail error: " . $e->getMessage();
        error_log("Fallback mail error: " . $e->getMessage());
    }
}

// *** CRITICAL FIX: UPDATE DATABASE UNCONDITIONALLY ***
try {
    error_log("STARTING DATABASE UPDATE for message ID: $message_id");
    
    // SIMPLE, DIRECT UPDATE - NO CONDITIONAL LOGIC
    $reply_timestamp = date('Y-m-d H:i:s');
    
    $update_stmt = $pdo->prepare("UPDATE contact_message 
                                 SET contm_reply = :reply_content, 
                                     contm_reply_date = :reply_date,
                                     contm_status = 'Replied'
                                 WHERE contm_id = :message_id");
    
    $update_stmt->bindParam(':reply_content', $reply_content, PDO::PARAM_STR);
    $update_stmt->bindParam(':reply_date', $reply_timestamp, PDO::PARAM_STR);
    $update_stmt->bindParam(':message_id', $message_id, PDO::PARAM_INT);
    
    $update_result = $update_stmt->execute();
    $rows_affected = $update_stmt->rowCount();
    
    error_log("DATABASE UPDATE RESULT: Success=" . ($update_result ? 'YES' : 'NO') . ", Rows=" . $rows_affected);
    
    if ($update_result && $rows_affected > 0) {
        error_log("SUCCESS: Database updated for message ID {$message_id}");
        
        // VERIFICATION QUERY
        $verify_stmt = $pdo->prepare("SELECT contm_status, contm_reply, contm_reply_date FROM contact_message WHERE contm_id = ?");
        $verify_stmt->execute([$message_id]);
        $verify_result = $verify_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($verify_result) {
            error_log("VERIFICATION: Status=" . $verify_result['contm_status'] . 
                     ", Reply=" . (!empty($verify_result['contm_reply']) ? 'YES' : 'NO') . 
                     ", Date=" . $verify_result['contm_reply_date']);
        }
        
        // Log the activity
        try {
            $log_query = "INSERT INTO user_logs (user_id, log_type, log_details, log_timestamp) 
                         VALUES (:user_id, 'REPLY_CONTACT_MESSAGE', :details, :log_timestamp)";
            $log_stmt = $pdo->prepare($log_query);
            
            $details = "Replied to contact message ID: {$message_id}";
            if ($email_sent) {
                $details .= " (Email sent successfully to: {$recipient_email})";
            } else {
                $details .= " (Email failed - saved to database only. Error: {$email_error})";
            }
            
            $log_stmt->bindParam(":user_id", $_SESSION["user_id"]);
            $log_stmt->bindParam(":details", $details);
            $log_stmt->bindParam(":log_timestamp", $reply_timestamp);
            $log_stmt->execute();
            
        } catch (Exception $log_error) {
            error_log("Failed to log reply activity: " . $log_error->getMessage());
        }
        
        // RESPONSE BASED ON EMAIL STATUS
        if ($email_sent) {
            echo json_encode([
                "success" => true,
                "message" => "✅ Reply sent successfully! Email delivered to {$recipient_email} and saved to database. Refresh the page to see the reply information in export.",
                "debug_info" => [
                    "database_updated" => true,
                    "email_sent" => true,
                    "reply_timestamp" => $reply_timestamp,
                    "verification" => $verify_result
                ]
            ]);
        } else {
            echo json_encode([
                "success" => true,
                "message" => "⚠️ Reply saved to database successfully but email delivery failed. Error: {$email_error}. The reply will appear in your export. Check email settings.",
                "debug_info" => [
                    "database_updated" => true,
                    "email_sent" => false,
                    "email_error" => $email_error,
                    "reply_timestamp" => $reply_timestamp,
                    "verification" => $verify_result
                ]
            ]);
        }
        
    } else {
        error_log("ERROR: Database update failed for message ID {$message_id}");
        echo json_encode([
            "success" => false, 
            "message" => "❌ Failed to update message status in database. SQL Error: " . implode(", ", $update_stmt->errorInfo())
        ]);
    }
    
} catch (PDOException $e) {
    error_log("Database error in reply_message.php: " . $e->getMessage());
    echo json_encode([
        "success" => false, 
        "message" => "❌ Database error: " . $e->getMessage()
    ]);
}
?>