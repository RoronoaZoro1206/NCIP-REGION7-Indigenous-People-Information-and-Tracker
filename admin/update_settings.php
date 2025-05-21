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
    
    try {
        // Verify current password
        $stmt = $pdo->prepare("SELECT USER_PWD FROM USERS WHERE USER_ID = :user_id");
        $stmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if(!$user || !password_verify($current_password, $user['USER_PWD'])) {
            $_SESSION['error_message'] = "Current password is incorrect.";
            header("Location: settings.php");
            exit();
        }
        
        // Update settings
        $stmt = $pdo->prepare("UPDATE USERS SET USER_TIMEZONE = :timezone WHERE USER_ID = :user_id");
        $stmt->bindParam(':timezone', $timezone, PDO::PARAM_STR);
        $stmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
        $stmt->execute();
        
        // Log the settings update
        log_user_activity($pdo, $_SESSION['user_id'], 'SETTINGS_UPDATE', 'User settings updated');
        
        $_SESSION['success_message'] = "Settings updated successfully!";
    } catch(PDOException $e) {
        $_SESSION['error_message'] = "Database error: " . $e->getMessage();
    }
    
    header("Location: settings.php");
    exit();
}

// Handle account deletion
if(isset($_POST['delete_account'])) {
    $confirmation = sanitize_input($_POST['delete_confirmation']);
    $password = $_POST['delete_password'];
    
    // Check confirmation text
    if($confirmation !== 'DELETE') {
        $_SESSION['error_message'] = "Confirmation text is incorrect. Account not deleted.";
        header("Location: settings.php");
        exit();
    }
    
    try {
        // Verify password
        $stmt = $pdo->prepare("SELECT USER_PWD FROM USERS WHERE USER_ID = :user_id");
        $stmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if(!$user || !password_verify($password, $user['USER_PWD'])) {
            $_SESSION['error_message'] = "Password is incorrect. Account not deleted.";
            header("Location: settings.php");
            exit();
        }
        
        // Log the account deletion
        log_user_activity($pdo, $_SESSION['user_id'], 'ACCOUNT_DELETE', 'User account deleted');
        
        // Delete account
        $stmt = $pdo->prepare("DELETE FROM USERS WHERE USER_ID = :user_id");
        $stmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
        $stmt->execute();
        
        // Clear session and redirect to login
        session_destroy();
        header("Location: login.php?msg=account_deleted");
        exit();
    } catch(PDOException $e) {
        $_SESSION['error_message'] = "Database error: " . $e->getMessage();
        header("Location: settings.php");
        exit();
    }
}

// If we get here, redirect to settings page
header("Location: settings.php");
exit();