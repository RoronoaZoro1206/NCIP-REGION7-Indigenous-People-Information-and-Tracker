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
        $userTimezone = $user['user_timezone'] ?? 'Asia/Manila';
        
        // Convert stored UTC time to user's timezone
        $expiryUtc = new DateTime(
            $user['user_token_expiry'], 
            new DateTimeZone('Asia/Manila')
        );
        $expiryUserLocal = $expiryUtc->setTimezone(
            new DateTimeZone($userTimezone)
        );
        
        // Get current time in user's timezone
        $now = new DateTime('now', new DateTimeZone($userTimezone));
        
        // Check expiration
        if ($expiryUserLocal < $now) {
            $_SESSION['reset_error'] = "Reset token has expired.";
            header("Location: forgot-password.php");
            exit;
        }
    } catch (Exception $e) {
        // Fallback to server time if timezone conversion fails
        if (strtotime($user['user_token_expiry']) < time()) {
            $_SESSION['reset_error'] = "Reset token has expired.";
            header("Location: forgot-password.php");
            exit;
        }
    }

    // Hash new password and update
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    if (!updateUserPassword($pdo, $user['user_id'], $hashedPassword)) {
        $_SESSION['reset_error'] = "Failed to update password.";
        header("Location: reset-password.php?token=" . urlencode($token));
        exit;
    }

    $_SESSION['forgot_success'] = "Password reset successful. You can now log in.";
    header("Location: form-content.php?show=login");
    exit;
}
?>