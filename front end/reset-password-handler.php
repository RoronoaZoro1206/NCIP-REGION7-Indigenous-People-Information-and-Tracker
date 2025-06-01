<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'db.php';
require_once 'user-models.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $token = $_POST['token'];
    $password = $_POST['password'];
    $confirm = $_POST['confirm'];

    // Basic validation
    if (empty($token) || empty($password) || empty($confirm)) {
        $_SESSION['reset_error'] = "All fields are required.";
        header("Location: reset-password.php?token=" . urlencode($token));
        exit;
    }

    if ($password !== $confirm) {
        $_SESSION['reset_error'] = "Passwords do not match.";
        header("Location: reset-password.php?token=" . urlencode($token));
        exit;
    }

    // Validate token
    $user = getUserByToken($pdo, $token);
    if (!$user) {
        $_SESSION['reset_error'] = "Invalid or expired reset token.";
        header("Location: forgot-password.php");
        exit;
    }

    try {
        // Get user's timezone (fallback to UTC if not set)
        $userTimezone = $user['USER_TIMEZONE'] ?? 'Asia/Manila';
        
        // Convert stored UTC time to user's timezone
        $expiryUtc = new DateTime(
            $user['USER_TOKEN_EXPIRY'], 
            new DateTimeZone('Asia/Manila')
        );
        $expiryUserLocal = $expiryUtc->setTimezone(
            new DateTimeZone($userTimezone)
        );
        
        // Get current time in user's timezone
        $now = new DateTime('now', new DateTimeZone($userTimezone));
        
        // Check expiration
        if ($expiryUserLocal < $now) {
            // Log expired token attempt
            $log_query = "INSERT INTO USER_LOGS (USER_ID, LOG_TYPE, LOG_DETAILS, LOG_TIMESTAMP) 
                         VALUES (?, 'PASSWORD_RESET_EXPIRED', ?, CURRENT_TIMESTAMP)";
            $log_stmt = $pdo->prepare($log_query);
            $log_details = "Attempted to use expired password reset token";
            $log_stmt->execute([$user['USER_ID'], $log_details]);
            
            $_SESSION['reset_error'] = "Reset token has expired.";
            header("Location: forgot-password.php");
            exit;
        }
    } catch (Exception $e) {
        // Fallback to server time if timezone conversion fails
        if (strtotime($user['USER_TOKEN_EXPIRY']) < time()) {
            // Log expired token attempt
            $log_query = "INSERT INTO USER_LOGS (USER_ID, LOG_TYPE, LOG_DETAILS, LOG_TIMESTAMP) 
                         VALUES (?, 'PASSWORD_RESET_EXPIRED', ?, CURRENT_TIMESTAMP)";
            $log_stmt = $pdo->prepare($log_query);
            $log_details = "Attempted to use expired password reset token (fallback check)";
            $log_stmt->execute([$user['USER_ID'], $log_details]);
            
            $_SESSION['reset_error'] = "Reset token has expired.";
            header("Location: forgot-password.php");
            exit;
        }
    }

    // Validate password strength
    if (strlen($password) < 8) {
        $_SESSION['reset_error'] = "Password must be at least 8 characters long.";
        header("Location: reset-password.php?token=" . urlencode($token));
        exit;
    }

    // Hash new password and update
    $options = [
        'cost' => 12
    ];
    $hashedPassword = password_hash($password, PASSWORD_BCRYPT, $options);
    
    if (!updateUserPassword($pdo, $user['USER_ID'], $hashedPassword)) {
        // Log password update failure
        $log_query = "INSERT INTO USER_LOGS (USER_ID, LOG_TYPE, LOG_DETAILS, LOG_TIMESTAMP) 
                     VALUES (?, 'PASSWORD_RESET_FAILED', ?, CURRENT_TIMESTAMP)";
        $log_stmt = $pdo->prepare($log_query);
        $log_details = "Failed to update password during reset";
        $log_stmt->execute([$user['USER_ID'], $log_details]);
        
        $_SESSION['reset_error'] = "Failed to update password.";
        header("Location: reset-password.php?token=" . urlencode($token));
        exit;
    }

    // Log successful password reset
    $log_query = "INSERT INTO USER_LOGS (USER_ID, LOG_TYPE, LOG_DETAILS, LOG_TIMESTAMP) 
                 VALUES (?, 'PASSWORD_RESET_SUCCESS', ?, CURRENT_TIMESTAMP)";
    $log_stmt = $pdo->prepare($log_query);
    $log_details = "Password reset completed successfully";
    $log_stmt->execute([$user['USER_ID'], $log_details]);

    // Check if this is an admin user and redirect appropriately
    $is_admin = strtoupper($user['USER_ROLE']) === 'ADMIN';
    
    $_SESSION['forgot_success'] = "Password reset successful. You can now log in.";
    
    if ($is_admin) {
        header("Location: ../admin/login.php");
    } else {
        header("Location: form-content.php?show=login");
    }
    exit;
}
?>