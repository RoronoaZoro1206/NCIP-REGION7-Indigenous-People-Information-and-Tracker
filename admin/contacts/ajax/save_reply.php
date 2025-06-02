<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set content type for JSON response
header('Content-Type: application/json');

// Check if user is logged in and is admin
if (!isset($_SESSION["user_id"])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

$user_role = $_SESSION["role"] ?? $_SESSION["user_role"] ?? null;
if (!$user_role || strtoupper($user_role) !== 'ADMIN') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied. Admin privileges required.']);
    exit;
}

// Validate request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get and validate input
$message_id = filter_input(INPUT_POST, 'message_id', FILTER_VALIDATE_INT);
$reply_content = filter_input(INPUT_POST, 'reply_content', FILTER_SANITIZE_STRING);

if (!$message_id || $message_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid message ID']);
    exit;
}

if (empty($reply_content)) {
    echo json_encode(['success' => false, 'message' => 'Reply content is required']);
    exit;
}

try {
    // Include database connection
    include "../../config/database.php";
    
    // Update message with reply content and date
    $update_stmt = $pdo->prepare("UPDATE contact_message 
                                 SET contm_reply = :reply_content, 
                                     contm_reply_date = CURRENT_TIMESTAMP,
                                     contm_status = 'Replied'
                                 WHERE contm_id = :message_id AND contm_deleted_at IS NULL");
    
    $update_stmt->bindParam(':reply_content', $reply_content, PDO::PARAM_STR);
    $update_stmt->bindParam(':message_id', $message_id, PDO::PARAM_INT);
    $result = $update_stmt->execute();
    
    if ($result && $update_stmt->rowCount() > 0) {
        // Log the reply activity
        try {
            $log_query = "INSERT INTO user_logs (user_id, log_type, log_details, log_timestamp) 
                         VALUES (:user_id, 'REPLY_CONTACT_MESSAGE', :details, CURRENT_TIMESTAMP)";
            $log_stmt = $pdo->prepare($log_query);
            $details = "Replied to contact message ID: {$message_id}";
            $log_stmt->bindParam(":user_id", $_SESSION["user_id"]);
            $log_stmt->bindParam(":details", $details);
            $log_stmt->execute();
        } catch (Exception $log_error) {
            error_log("Failed to log reply activity: " . $log_error->getMessage());
        }
        
        echo json_encode(['success' => true, 'message' => 'Reply saved successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to save reply or message not found']);
    }
    
} catch (PDOException $e) {
    error_log("Database error in save_reply.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
} catch (Exception $e) {
    error_log("General error in save_reply.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An unexpected error occurred']);
}
?>