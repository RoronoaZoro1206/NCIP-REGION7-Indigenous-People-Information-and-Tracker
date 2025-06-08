<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if(!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once 'config/database.php';
require_once 'includes/functions.php';

// Handle settings update
if(isset($_POST['update_settings'])) {
    $timezone = sanitize_input($_POST['timezone']);
    $current_password = $_POST['current_password'];
    
    // Validate input
    if (empty($timezone)) {
        $_SESSION['error_message'] = "Timezone is required.";
        header("Location: settings.php");
        exit();
    }
    
    if (empty($current_password)) {
        $_SESSION['error_message'] = "Current password is required to save changes.";
        header("Location: settings.php");
        exit();
    }
    
    try {
        // Verify current password - using correct column name
        $stmt = $pdo->prepare("SELECT user_pwd FROM users WHERE user_id = :user_id");
        $stmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if(!$user) {
            $_SESSION['error_message'] = "User not found.";
            header("Location: settings.php");
            exit();
        }
        
        if(!password_verify($current_password, $user['user_pwd'])) {
            $_SESSION['error_message'] = "Current password is incorrect.";
            header("Location: settings.php");
            exit();
        }
        
        // Get current timezone for logging
        $stmt = $pdo->prepare("SELECT user_timezone FROM users WHERE user_id = :user_id");
        $stmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
        $stmt->execute();
        $current_user = $stmt->fetch(PDO::FETCH_ASSOC);
        $old_timezone = $current_user['user_timezone'] ?? 'Asia/Manila';
        
        // Update settings - using correct column name
        $stmt = $pdo->prepare("UPDATE users SET user_timezone = :timezone WHERE user_id = :user_id");
        $stmt->bindParam(':timezone', $timezone, PDO::PARAM_STR);
        $stmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
        
        if ($stmt->execute()) {
            // Log the settings update with details
            try {
                $activity_details = "Settings updated (ID: {$_SESSION['user_id']}): Timezone changed from {$old_timezone} to {$timezone}";
                log_user_activity($pdo, $_SESSION['user_id'], 'SETTINGS_UPDATE', $activity_details);
            } catch (Exception $log_error) {
                // Log error but don't fail the settings update
                error_log("Failed to log settings update activity: " . $log_error->getMessage());
            }
            
            $_SESSION['success_message'] = "Settings updated successfully!";
        } else {
            $_SESSION['error_message'] = "Failed to update settings. Please try again.";
        }
        
    } catch(PDOException $e) {
        error_log("Settings update error: " . $e->getMessage());
        $_SESSION['error_message'] = "Database error: " . $e->getMessage();
    }
    
    header("Location: settings.php");
    exit();
}

// Handle account deletion
if(isset($_POST['delete_account'])) {
    $confirmation = sanitize_input($_POST['delete_confirmation']);
    $password = $_POST['delete_password'];
    
    // Validate input
    if (empty($confirmation) || empty($password)) {
        $_SESSION['error_message'] = "Both confirmation text and password are required.";
        header("Location: settings.php");
        exit();
    }
    
    // Check confirmation text
    if($confirmation !== 'DELETE') {
        $_SESSION['error_message'] = "Confirmation text is incorrect. Please type 'DELETE' exactly. Account not deleted.";
        header("Location: settings.php");
        exit();
    }
    
    try {
        // Verify password - using correct column name
        $stmt = $pdo->prepare("SELECT user_pwd, user_username FROM users WHERE user_id = :user_id");
        $stmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if(!$user) {
            $_SESSION['error_message'] = "User not found.";
            header("Location: settings.php");
            exit();
        }
        
        if(!password_verify($password, $user['user_pwd'])) {
            $_SESSION['error_message'] = "Password is incorrect. Account not deleted.";
            header("Location: settings.php");
            exit();
        }
        
        // Log the account deletion before deleting
        try {
            $activity_details = "Account deletion initiated (ID: {$_SESSION['user_id']}, Username: {$user['user_username']})";
            log_user_activity($pdo, $_SESSION['user_id'], 'ACCOUNT_DELETE', $activity_details);
        } catch (Exception $log_error) {
            // Continue with deletion even if logging fails
            error_log("Failed to log account deletion activity: " . $log_error->getMessage());
        }
        
        // Delete account - using correct table name
        $stmt = $pdo->prepare("DELETE FROM users WHERE user_id = :user_id");
        $stmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
        
        if ($stmt->execute()) {
            // Clear session and redirect to login
            session_destroy();
            header("Location: login.php?msg=account_deleted");
            exit();
        } else {
            $_SESSION['error_message'] = "Failed to delete account. Please try again.";
            header("Location: settings.php");
            exit();
        }
        
    } catch(PDOException $e) {
        error_log("Account deletion error: " . $e->getMessage());
        $_SESSION['error_message'] = "Database error: " . $e->getMessage();
        header("Location: settings.php");
        exit();
    }
}

// If we get here without any form submission, redirect to settings page
header("Location: settings.php");
exit();
?>