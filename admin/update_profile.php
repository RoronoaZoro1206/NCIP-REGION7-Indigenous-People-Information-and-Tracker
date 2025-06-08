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

// Handle profile information update (username and email only)
if(isset($_POST['update_profile'])) {
    $username = sanitize_input($_POST['username']);
    $email = sanitize_input($_POST['email']);
    
    // Validate input
    $errors = [];
    
    // Username validation
    if (empty($username)) {
        $errors[] = 'Username is required';
    } elseif (strlen($username) < 3) {
        $errors[] = 'Username must be at least 3 characters';
    } elseif (strlen($username) > 50) {
        $errors[] = 'Username cannot exceed 50 characters';
    } elseif (!preg_match('/^[a-zA-Z0-9_\s\-\.]+$/', $username)) {
        $errors[] = 'Username can only contain letters, numbers, spaces, underscores, hyphens, and periods';
    } elseif (preg_match('/^\s|\s$/', $username)) {
        $errors[] = 'Username cannot start or end with spaces';
    } elseif (preg_match('/\s{2,}/', $username)) {
        $errors[] = 'Username cannot contain consecutive spaces';
    }

    // Email validation
    if (empty($email)) {
        $errors[] = 'Email is required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address';
    } elseif (strlen($email) > 100) {
        $errors[] = 'Email cannot exceed 100 characters';
    }

    if (empty($errors)) {
        try {
            // Check if username already exists (excluding current user)
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE user_username = :username AND user_id != :user_id");
            $stmt->bindParam(':username', $username, PDO::PARAM_STR);
            $stmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
            $stmt->execute();
            if ($stmt->fetchColumn() > 0) {
                $_SESSION['error_message'] = "Username already taken. Please choose a different one.";
                header("Location: profile.php");
                exit();
            }
            
            // Check if email already exists (excluding current user)
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE user_email = :email AND user_id != :user_id");
            $stmt->bindParam(':email', $email, PDO::PARAM_STR);
            $stmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
            $stmt->execute();
            if ($stmt->fetchColumn() > 0) {
                $_SESSION['error_message'] = "Email already in use. Please choose a different one.";
                header("Location: profile.php");
                exit();
            }
            
            // Get current user data for logging changes
            $stmt = $pdo->prepare("SELECT user_username, user_email FROM users WHERE user_id = :user_id");
            $stmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
            $stmt->execute();
            $current_user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Clean the username by trimming and normalizing spaces
            $clean_username = preg_replace('/\s+/', ' ', trim($username));
            
            // Update profile information (username and email only)
            $stmt = $pdo->prepare("UPDATE users SET user_username = :username, user_email = :email WHERE user_id = :user_id");
            $stmt->bindParam(':username', $clean_username, PDO::PARAM_STR);
            $stmt->bindParam(':email', $email, PDO::PARAM_STR);
            $stmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
            $stmt->execute();
            
            // Update session variables
            $_SESSION['username'] = $clean_username;
            $_SESSION['email'] = $email;
            
            // Log the profile update activity
            try {
                $changes = [];
                if ($clean_username !== $current_user['user_username']) {
                    $changes[] = "Username: {$current_user['user_username']} → {$clean_username}";
                }
                if ($email !== $current_user['user_email']) {
                    $changes[] = "Email: {$current_user['user_email']} → {$email}";
                }

                if (!empty($changes)) {
                    $activity_details = "Updated profile information (ID: {$_SESSION['user_id']}): " . implode(", ", $changes);
                    log_user_activity($pdo, $_SESSION['user_id'], 'PROFILE_UPDATE', $activity_details);
                }
            } catch (Exception $log_error) {
                // Log error but don't fail the profile update
                error_log("Failed to log profile update activity: " . $log_error->getMessage());
            }
            
            $_SESSION['success_message'] = "Profile information updated successfully!";
            
        } catch(PDOException $e) {
            error_log("Profile update error: " . $e->getMessage());
            $_SESSION['error_message'] = "Database error: " . $e->getMessage();
        }
    } else {
        $_SESSION['error_message'] = implode('<br>', $errors);
    }
    
    // Always redirect back to profile page after processing
    header("Location: profile.php");
    exit();
}

// If we get here without any form submission, redirect to profile page
header("Location: profile.php");
exit();
?>