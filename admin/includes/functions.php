<?php
// Function to sanitize input
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Function to log user activity - updated to use named parameters for PostgreSQL
function log_user_activity($pdo, $user_id, $log_type, $log_details = null) {
    $stmt = $pdo->prepare("INSERT INTO USER_LOGS (USER_ID, LOG_TYPE, LOG_DETAILS, LOG_IP_ADDRESS) VALUES (:user_id, :log_type, :log_details, :ip_address)");
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
    
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->bindParam(':log_type', $log_type, PDO::PARAM_STR);
    $stmt->bindParam(':log_details', $log_details, PDO::PARAM_STR);
    $stmt->bindParam(':ip_address', $ip_address, PDO::PARAM_STR);
    $stmt->execute();
}

// Function to upload profile picture
function upload_profile_picture($file) {
    // Correct path with slash after __DIR__
    $target_dir = __DIR__ . "/../assets/uploads/profile_pictures/";
    
    // Debug to see actual path
    error_log("Upload target directory: " . $target_dir);
    
    // Create directory if it doesn't exist
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }
    
    // Keep original filename with some cleaning
    $original_name = basename($file["name"]);
    $file_extension = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
    
    // Sanitize the filename (remove spaces and special chars)
    $clean_name = preg_replace("/[^a-zA-Z0-9.-]/", "_", pathinfo($original_name, PATHINFO_FILENAME));
    
    // Use original filename without the random generation
    $new_filename = $clean_name . '.' . $file_extension;
    $target_file = $target_dir . $new_filename;
    
    // Check if image file is a actual image
    $check = getimagesize($file["tmp_name"]);
    if($check === false) {
        return ["success" => false, "message" => "File is not an image."];
    }
    
    // Check file size (limit to 5MB)
    if ($file["size"] > 5000000) {
        return ["success" => false, "message" => "Sorry, your file is too large. Maximum size is 5MB."];
    }
    
    // Allow only specific file formats
    if($file_extension != "jpg" && $file_extension != "png" && $file_extension != "jpeg" && $file_extension != "gif") {
        return ["success" => false, "message" => "Sorry, only JPG, JPEG, PNG & GIF files are allowed."];
    }
    
    // Upload file - using absolute path for reliable file operations
    if (move_uploaded_file($file["tmp_name"], $target_file)) {
        // Return just the filename, not the full path
        return ["success" => true, "filename" => $new_filename];
    } else {
        error_log("Upload failed. Error: " . print_r(error_get_last(), true));
        return ["success" => false, "message" => "Sorry, there was an error uploading your file. Check PHP error log for details."];
    }
}

/**
 * Format date and time based on user's timezone preference
 * 
 * @param string $datetime The datetime string to format
 * @param string $timezone The user's timezone preference
 * @param string $format The format to use (PHP date format)
 * @return string The formatted date and time
 */
function format_datetime($datetime, $timezone = 'UTC', $format = 'F j, Y g:i A') {
    $dt = new DateTime($datetime, new DateTimeZone('UTC'));
    $dt->setTimezone(new DateTimeZone($timezone));
    return $dt->format($format);
}

/**
 * Get user information by ID
 * 
 * @param PDO $pdo Database connection
 * @param int $user_id User ID
 * @return array|false User data or false if not found
 */
function get_user_by_id($pdo, $user_id) {
    $stmt = $pdo->prepare("SELECT * FROM USERS WHERE USER_ID = :user_id");
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Check if a user has a specific permission
 * 
 * @param PDO $pdo Database connection
 * @param int $user_id User ID
 * @param string $permission Permission to check
 * @return bool True if user has permission, false otherwise
 */
function user_has_permission($pdo, $user_id, $permission) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM USER_PERMISSIONS up
        JOIN PERMISSIONS p ON up.PERM_ID = p.PERM_ID
        WHERE up.USER_ID = :user_id AND p.PERM_NAME = :permission
    ");
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->bindParam(':permission', $permission, PDO::PARAM_STR);
    $stmt->execute();
    return $stmt->fetchColumn() > 0;
}
?>