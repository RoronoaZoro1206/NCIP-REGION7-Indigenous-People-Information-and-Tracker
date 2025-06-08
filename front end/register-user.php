<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER["REQUEST_METHOD"] == "POST"){
    
    $username = $_POST["username"] ?? null;
    $email = $_POST["email"] ?? null;
    $password = $_POST["password"] ?? null;
    $confirm_password = $_POST["confirm_password"] ?? null;
    $timezone = $_POST["timezone"] ?? 'UTC';
    
    // Validate required fields
    if (!$username || !$email || !$password || !$confirm_password) {
        $_SESSION["error_message"] = "All fields are required.";
        header("Location: form-content.php?show=register&error=1");
        exit();
    }
    
    // Front end ONLY creates USER role - never ADMIN
    $role = 'USER';
   
    // Handle timezone and created timestamp
    try {
        $dt = new DateTime("now", new DateTimeZone($timezone));
        $user_created = $dt->format('Y-m-d H:i:sP'); 
    } catch (Exception $e) {
        $user_created = date('Y-m-d H:i:sP');
    }

    // Get client IP address for logging
    $client_ip = $_SERVER['HTTP_CLIENT_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'Unknown';

    try {
        require_once 'db.php';
        require_once 'user-models.php';
        require_once 'validator.php';

        // Only use RegisterValidator for USER registration
        $validator = new RegisterValidator($username, $email, $password, $confirm_password, $pdo, $role);
        $validated_role = $validator->validate_data();

        // Create user with USER role only
        $user_id = create_user($pdo, $username, $email, $password, $timezone, $user_created, 'USER');

        // Log the successful USER registration
        $log_query = "INSERT INTO USER_LOGS (USER_ID, LOG_TYPE, LOG_DETAILS, LOG_TIMESTAMP, LOG_IP_ADDRESS) 
                     VALUES (:user_id, 'REGISTRATION', :details, CURRENT_TIMESTAMP, :ip_address)";
        $log_stmt = $pdo->prepare($log_query);
        $details = "New USER account created successfully | Username: " . $username . " | Email: " . $email . " | Timezone: " . $timezone;
        $log_stmt->bindParam(":user_id", $user_id);
        $log_stmt->bindParam(":details", $details);
        $log_stmt->bindParam(":ip_address", $client_ip);
        $log_stmt->execute();

        // Clear any previous error messages
        unset($_SESSION["error_message"]);
        unset($_SESSION['errors']);
        unset($_SESSION['old']);
        
        // Always redirect to user login - never admin
        header("Location: form-content.php?show=login&success=1");
        exit();
        
    } catch (Exception $ve) {
        // Handle validation exceptions
        error_log("User registration validation error: " . $ve->getMessage() . " | Username: " . $username . " | Email: " . $email . " | IP: " . $client_ip);
        
        // Log failed registration attempt
        try {
            if (isset($pdo)) {
                $error_log_query = "INSERT INTO USER_LOGS (USER_ID, LOG_TYPE, LOG_DETAILS, LOG_TIMESTAMP, LOG_IP_ADDRESS) 
                                   VALUES (NULL, 'REGISTRATION_FAILED', :details, CURRENT_TIMESTAMP, :ip_address)";
                $error_log_stmt = $pdo->prepare($error_log_query);
                $error_details = "User registration failed for username: " . $username . " | Email: " . $email . " | Error: " . $ve->getMessage();
                $error_log_stmt->bindParam(":details", $error_details);
                $error_log_stmt->bindParam(":ip_address", $client_ip);
                $error_log_stmt->execute();
            }
        } catch (Exception $log_error) {
            error_log("Failed to log registration failure: " . $log_error->getMessage());
        }
        
        $_SESSION['errors'] = [$ve->getMessage()];
        $_SESSION['old'] = $_POST;
        
        header("Location: form-content.php?show=register&error=1");
        exit();
        
    } catch (PDOException $e) {
        // Handle database errors
        error_log("User registration PDO error: " . $e->getMessage() . " | Username: " . $username . " | Email: " . $email . " | IP: " . $client_ip);
        
        $_SESSION["error_message"] = "An error occurred during registration. Please try again later.";
        $_SESSION['old'] = $_POST;
        
        header("Location: form-content.php?show=register&error=1");
        exit();
        
    } catch (Exception $e) {
        // Handle any other unexpected errors
        error_log("Unexpected user registration error: " . $e->getMessage() . " | Username: " . $username . " | Email: " . $email . " | IP: " . $client_ip);
        
        $_SESSION["error_message"] = "An unexpected error occurred. Please contact support if the problem persists.";
        $_SESSION['old'] = $_POST;
        
        header("Location: form-content.php?show=register&error=1");
        exit();
    }

} else {
    // If not POST request, redirect to user registration only
    header("Location: form-content.php?show=register");
    exit();
}
?>