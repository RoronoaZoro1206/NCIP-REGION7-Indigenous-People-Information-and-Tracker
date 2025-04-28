<?php
require_once 'db.php';

try {
    // Check for any expired tokens
    $stmt = $pdo->prepare("SELECT * FROM users WHERE user_token_expiry IS NOT NULL AND user_token_expiry < NOW() LIMIT 1");
    $stmt->execute();

    if ($stmt->fetch()) {
        
        shell_exec('php "C:\xampp\htdocs\Php-Project\cleanup-expired-tokens.php"');
    }
} catch (PDOException $e) {
    error_log("Error in token-checker: " . $e->getMessage());
}
?>