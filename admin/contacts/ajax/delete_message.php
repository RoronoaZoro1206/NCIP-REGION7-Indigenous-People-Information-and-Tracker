<?php
// filepath: c:\xampp\htdocs\Information Management\IM---Copy\admin\contacts\ajax\delete_message.php
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
$action = filter_input(INPUT_POST, 'action', FILTER_SANITIZE_STRING);

if (!$message_id || $message_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid message ID']);
    exit;
}

if ($action !== 'soft_delete') {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
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
            
            // Create index on deleted_at column for better performance
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_contact_message_deleted_at ON contact_message(contm_deleted_at)");
            
        } catch (PDOException $e) {
            error_log("Error ensuring soft delete columns for contact_message: " . $e->getMessage());
            // Don't throw - this is not critical for basic functionality
        }
    }
    
    // Ensure soft delete columns exist
    ensureContactSoftDeleteColumns($pdo);
    
    // First check if message exists and is not already deleted - FIXED for PostgreSQL
    $check_stmt = $pdo->prepare("SELECT contm_id, contm_name, contm_email FROM contact_message 
                                WHERE contm_id = :message_id AND contm_deleted_at IS NULL");
    $check_stmt->bindParam(':message_id', $message_id, PDO::PARAM_INT);
    $check_stmt->execute();
    
    if ($check_stmt->rowCount() === 0) {
        echo json_encode(['success' => false, 'message' => 'Message not found or already deleted']);
        exit;
    }
    
    $message_data = $check_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Perform soft delete - PostgreSQL compatible (same pattern as users/population)
    $soft_delete_stmt = $pdo->prepare("UPDATE contact_message 
                                      SET contm_deleted_at = CURRENT_TIMESTAMP, contm_deleted_by = :user_id 
                                      WHERE contm_id = :message_id AND contm_deleted_at IS NULL");
    $soft_delete_stmt->bindParam(':user_id', $_SESSION["user_id"], PDO::PARAM_INT);
    $soft_delete_stmt->bindParam(':message_id', $message_id, PDO::PARAM_INT);
    
    $result = $soft_delete_stmt->execute();
    
    if ($result && $soft_delete_stmt->rowCount() > 0) {
        // Log the soft deletion activity
        try {
            $log_query = "INSERT INTO user_logs (user_id, log_type, log_details, log_timestamp) 
                         VALUES (:user_id, 'SOFT_DELETE_CONTACT_MESSAGE', :details, CURRENT_TIMESTAMP)";
            $log_stmt = $pdo->prepare($log_query);
            $details = "Moved contact message to recycle bin: {$message_data['contm_name']} ({$message_data['contm_email']}) - ID: {$message_id}";
            $log_stmt->bindParam(":user_id", $_SESSION["user_id"], PDO::PARAM_INT);
            $log_stmt->bindParam(":details", $details, PDO::PARAM_STR);
            $log_stmt->execute();
        } catch (Exception $log_error) {
            error_log("Failed to log soft deletion activity: " . $log_error->getMessage());
            // Don't fail the request for logging errors
        }
        
        echo json_encode([
            'success' => true, 
            'message' => 'Message moved to recycle bin successfully',
            'message_name' => $message_data['contm_name']
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to move message to recycle bin']);
    }
    
} catch (PDOException $e) {
    error_log("Database error in delete_message.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
} catch (Exception $e) {
    error_log("General error in delete_message.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An unexpected error occurred']);
}
?>