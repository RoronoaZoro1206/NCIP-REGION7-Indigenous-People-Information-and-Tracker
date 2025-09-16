<?php
require_once 'db.php';
// Bring in unified logger
try { require_once __DIR__ . '/logger.php'; } catch (Throwable $e) { /* ignore */ }

try {
    // Determine IP (when run via web, CLI may be missing headers)
    $client_ip = 'SYSTEM';
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $client_ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $parts = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $client_ip = trim($parts[0]);
    } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
        $client_ip = $_SERVER['REMOTE_ADDR'];
    }

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
        
        // Log that cleanup was triggered (single entry only)
        $logged = false;
        if (function_exists('log_user_event')) {
            log_user_event($pdo, 'TOKEN_CLEANUP', 'Expired token cleanup initiated', null, $client_ip);
            $logged = true;
        }
        if (!$logged) {
            $log_stmt = $pdo->prepare("INSERT INTO USER_LOGS (LOG_TYPE, LOG_DETAILS, LOG_TIMESTAMP, LOG_IP_ADDRESS) VALUES ('TOKEN_CLEANUP', 'Expired token cleanup initiated', CURRENT_TIMESTAMP, :ip)");
            $log_stmt->bindValue(':ip', $client_ip, PDO::PARAM_STR);
            $log_stmt->execute();
        }
        
        // Run the cleanup script
        shell_exec('php "' . $cleanup_script . '"');
    }
} catch (PDOException $e) {
    // Log the error
    error_log("Error in token-checker: " . $e->getMessage());
    
    // Add to USER_LOGS table for easier tracking (single entry)
    try {
        $error_message = "Token checker error: " . $e->getMessage();
        $logged = false;
        if (function_exists('log_user_event')) {
            log_user_event($pdo, 'ERROR', $error_message, null, ($client_ip ?? 'SYSTEM'));
            $logged = true;
        }
        if (!$logged) {
            $log_stmt = $pdo->prepare("INSERT INTO USER_LOGS (LOG_TYPE, LOG_DETAILS, LOG_TIMESTAMP, LOG_IP_ADDRESS) VALUES ('ERROR', :details, CURRENT_TIMESTAMP, :ip)");
            $log_stmt->bindParam(":details", $error_message);
            $log_stmt->bindParam(":ip", $client_ip);
            $log_stmt->execute();
        }
    } catch (PDOException $inner_e) {
        // If even logging fails, just write to error log
        error_log("Failed to log token checker error: " . $inner_e->getMessage());
    }
}
?>