<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if(!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

// Handle profile picture upload
if(isset($_FILES['profile_picture']) && !empty($_FILES['profile_picture']['name'])) {
    // Debug what's being received
    error_log("Processing profile picture upload: " . $_FILES['profile_picture']['name']);
    
    $upload_result = upload_profile_picture($_FILES['profile_picture']);
    
    if($upload_result['success']) {
        // Update profile picture in database
        try {
            $stmt = $pdo->prepare("UPDATE users SET user_profile_picture = :filename WHERE user_id = :user_id");
            $stmt->bindParam(':filename', $upload_result['filename'], PDO::PARAM_STR);
            $stmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
            
            if ($stmt->execute()) {
                // Debug output
                error_log("Profile picture updated in database: " . $upload_result['filename']);
                $_SESSION['success_message'] = "Profile picture updated successfully!";
                
                // Store in session for immediate use
                $_SESSION['profile_picture'] = $upload_result['filename'];
                
                // Log the activity
                log_user_activity($pdo, $_SESSION['user_id'], 'PROFILE_PICTURE_UPDATE', 'Profile picture updated');
            } else {
                error_log("Failed to update profile picture in database.");
                $_SESSION['error_message'] = "Failed to update profile picture in database.";
            }
        } catch(PDOException $e) {
            error_log("Database error: " . $e->getMessage());
            $_SESSION['error_message'] = "Database error: " . $e->getMessage();
        }
    } else {
        error_log("Upload failed: " . $upload_result['message']);
        $_SESSION['error_message'] = $upload_result['message'];
    }
    
    header("Location: profile.php");
    exit();
}

// Handle profile information update
if(isset($_POST['update_profile'])) {
    $username = sanitize_input($_POST['username']);
    $email = sanitize_input($_POST['email']);
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    try {
        // Get current user data - using lowercase column names
        $stmt = $pdo->prepare("SELECT user_pwd FROM users WHERE user_id = :user_id");
        $stmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Verify current password - use lowercase key name
        if (!$user || !password_verify($current_password, $user['user_pwd'])) {
            $_SESSION['error_message'] = "Current password is incorrect.";
            header("Location: profile.php");
            exit();
        }
        
        // Check if username already exists - using lowercase table and column names
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE user_username = :username AND user_id != :user_id");
        $stmt->bindParam(':username', $username, PDO::PARAM_STR);
        $stmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
        $stmt->execute();
        if ($stmt->fetchColumn() > 0) {
            $_SESSION['error_message'] = "Username already taken. Please choose a different one.";
            header("Location: profile.php");
            exit();
        }
        
        // Check if email already exists - using lowercase table and column names
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE user_email = :email AND user_id != :user_id");
        $stmt->bindParam(':email', $email, PDO::PARAM_STR);
        $stmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
        $stmt->execute();
        if ($stmt->fetchColumn() > 0) {
            $_SESSION['error_message'] = "Email already in use. Please choose a different one.";
            header("Location: profile.php");
            exit();
        }
        
        // Update profile information
        if (!empty($new_password)) {
            // Check if passwords match
            if ($new_password != $confirm_password) {
                $_SESSION['error_message'] = "New passwords do not match.";
                header("Location: profile.php");
                exit();
            }
            
            // Update with new password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET user_username = :username, user_email = :email, user_pwd = :password WHERE user_id = :user_id");
            $stmt->bindParam(':username', $username, PDO::PARAM_STR);
            $stmt->bindParam(':email', $email, PDO::PARAM_STR);
            $stmt->bindParam(':password', $hashed_password, PDO::PARAM_STR);
            $stmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
            $stmt->execute();
            
            // Log password change
            log_user_activity($pdo, $_SESSION['user_id'], 'PASSWORD_CHANGE', 'Password changed during profile update');
        } else {
            // Update without changing password
            $stmt = $pdo->prepare("UPDATE users SET user_username = :username, user_email = :email WHERE user_id = :user_id");
            $stmt->bindParam(':username', $username, PDO::PARAM_STR);
            $stmt->bindParam(':email', $email, PDO::PARAM_STR);
            $stmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
            $stmt->execute();
        }
        
        // Update session variables
        $_SESSION['username'] = $username;
        $_SESSION['email'] = $email;
        
        // Log the profile update
        log_user_activity($pdo, $_SESSION['user_id'], 'PROFILE_UPDATE', 'Profile information updated');
        
        $_SESSION['success_message'] = "Profile updated successfully!";
        
    } catch(PDOException $e) {
        error_log("Profile update error: " . $e->getMessage());
        $_SESSION['error_message'] = "Database error: " . $e->getMessage();
    }
    
    // Always redirect back to profile page after processing
    header("Location: profile.php");
    exit();
}

// If we get here without any form submission, redirect to profile page
header("Location: profile.php");
exit();
?>