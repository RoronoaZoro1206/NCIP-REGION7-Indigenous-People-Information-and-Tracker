<?php
require_once 'db.php';
// Bring in unified logger
try { require_once __DIR__ . '/logger.php'; } catch (Throwable $e) { /* ignore */ }

try {
    $stmt = $pdo->prepare("
        SELECT * FROM USERS 
        WHERE USER_TOKEN_EXPIRY IS NOT NULL 
        AND USER_TOKEN_EXPIRY < CURRENT_TIMESTAMP 
        LIMIT 1
    ");
    $stmt->execute();

    if ($stmt->fetch()) {
        // If expired tokens found, trigger the cleanup script
        $cleanup_script = 'C:\xampp\htdocs\Information Management\IM---Copy\front end\cleanup-expired-tokens.php';
        
        // Log that cleanup was triggered
        $log_query = "INSERT INTO USER_LOGS (LOG_TYPE, LOG_DETAILS, LOG_TIMESTAMP) 
                     VALUES ('TOKEN_CLEANUP', 'Expired token cleanup initiated', CURRENT_TIMESTAMP)";
        $pdo->exec($log_query);
        if (function_exists('log_user_event')) {
            log_user_event($pdo, 'TOKEN_CLEANUP', 'Expired token cleanup initiated');
        }
        
        // Run the cleanup script
        shell_exec('php "' . $cleanup_script . '"');
    }
} catch (PDOException $e) {
    // Log the error
    error_log("Error in token-checker: " . $e->getMessage());
    
    // Add to USER_LOGS table for easier tracking
    try {
        $error_message = "Token checker error: " . $e->getMessage();
        $log_query = "INSERT INTO USER_LOGS (LOG_TYPE, LOG_DETAILS, LOG_TIMESTAMP) 
                     VALUES ('ERROR', :details, CURRENT_TIMESTAMP)";
        $log_stmt = $pdo->prepare($log_query);
        $log_stmt->bindParam(":details", $error_message);
        $log_stmt->execute();
        if (function_exists('log_user_event')) {
            log_user_event($pdo, 'ERROR', $error_message);
        }
    } catch (PDOException $inner_e) {
        // If even logging fails, just write to error log
        error_log("Failed to log token checker error: " . $inner_e->getMessage());
    }
}
?>