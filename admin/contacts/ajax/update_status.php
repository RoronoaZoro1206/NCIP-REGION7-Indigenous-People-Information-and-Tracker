<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set content type for JSON response
header('Content-Type: application/json');

// Check if this is an AJAX request
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || $_SERVER['HTTP_X_REQUESTED_WITH'] !== 'XMLHttpRequest') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

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
$new_status = filter_input(INPUT_POST, 'new_status', FILTER_SANITIZE_STRING);

if (!$message_id || $message_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid message ID']);
    exit;
}

$allowed_statuses = ['Unread', 'Read', 'Replied', 'Archived'];
if (!in_array($new_status, $allowed_statuses)) {
    echo json_encode(['success' => false, 'message' => 'Invalid status']);
    exit;
}

try {
    // Include database connection
    include "../../config/database.php";
    
    /**
     * Ensure the contact_message table has soft delete columns
     */
    function ensureContactSoftDeleteColumns($pdo) {
        try {
            // Check if soft delete columns exist, if not, add them
            $columns_to_add = [
                'contm_deleted_at' => 'TIMESTAMP NULL',
                'contm_deleted_by' => 'INTEGER'
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
            error_log("Error ensuring soft delete columns for contact_message: " . $e->getMessage());
        }
    }
    
    // Ensure soft delete columns exist
    ensureContactSoftDeleteColumns($pdo);
    
    // Check if message exists and is not deleted - FIXED for PostgreSQL
    $check_stmt = $pdo->prepare("SELECT contm_id, contm_name, contm_email FROM contact_message 
                                WHERE contm_id = :message_id AND contm_deleted_at IS NULL");
    $check_stmt->bindParam(':message_id', $message_id, PDO::PARAM_INT);
    $check_stmt->execute();
    
    if ($check_stmt->rowCount() === 0) {
        echo json_encode(['success' => false, 'message' => 'Message not found or deleted']);
        exit;
    }
    
    $message_data = $check_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Update status - PostgreSQL compatible (proper table/column names)
    $update_stmt = $pdo->prepare("UPDATE contact_message SET contm_status = :new_status WHERE contm_id = :message_id AND contm_deleted_at IS NULL");
    $update_stmt->bindParam(":new_status", $new_status, PDO::PARAM_STR);
    $update_stmt->bindParam(":message_id", $message_id, PDO::PARAM_INT);
    $result = $update_stmt->execute();
    
    if ($result && $update_stmt->rowCount() > 0) {
        // Log the status update activity
        try {
            $log_query = "INSERT INTO user_logs (user_id, log_type, log_details, log_timestamp) 
                         VALUES (:user_id, 'UPDATE_CONTACT_STATUS', :details, CURRENT_TIMESTAMP)";
            $log_stmt = $pdo->prepare($log_query);
            $details = "Updated contact message status to {$new_status}: {$message_data['contm_name']} ({$message_data['contm_email']}) - ID: {$message_id}";
            $log_stmt->bindParam(":user_id", $_SESSION["user_id"], PDO::PARAM_INT);
            $log_stmt->bindParam(":details", $details, PDO::PARAM_STR);
            $log_stmt->execute();
        } catch (Exception $log_error) {
            error_log("Failed to log status update: " . $log_error->getMessage());
            // Don't fail the request for logging errors
        }
        
        echo json_encode([
            'success' => true, 
            'message' => "Status updated to {$new_status} successfully",
            'new_status' => $new_status,
            'message_name' => $message_data['contm_name']
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update status or no changes made']);
    }
    
} catch (PDOException $e) {
    error_log("Database error in update_status.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
} catch (Exception $e) {
    error_log("General error in update_status.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An unexpected error occurred']);
}
?>