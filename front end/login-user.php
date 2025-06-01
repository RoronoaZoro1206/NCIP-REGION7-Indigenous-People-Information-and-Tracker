<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST["username"] ?? null;
    $password = $_POST["password"] ?? null;
    $is_admin_login = isset($_POST["admin_login"]) && $_POST["admin_login"] === "true";

    if (!$username || !$password) {
        $_SESSION["error_message"] = "Username and Password are required.";
        if ($is_admin_login) {
            header("Location: ../admin/login.php");
        } else {
            header("Location: form-content.php?show=login");
        }
        exit();
    }

    try {
        require_once 'db.php';
        require_once 'user-models.php';
        require_once 'validator.php';

        // If this is an admin login request, specify ADMIN as the required role
        $required_role = $is_admin_login ? 'ADMIN' : '';
        $redirect_url = $is_admin_login ? '../admin/login.php' : 'form-content.php?show=login';
        
        $validator = new LoginValidator($username, $password, $pdo, $required_role, $redirect_url);
        $user = $validator->validate_data();

        // Store user data in session
        $_SESSION["user"] = $user;
        $_SESSION["user_id"] = $user["user_id"];
        $_SESSION["username"] = $user["user_username"];
        $_SESSION["user_role"] = $user["user_role"];
        $_SESSION["login_time"] = time();
        
        // Get client IP address for logging
        $client_ip = $_SERVER['HTTP_CLIENT_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        
        // Log successful login with IP address
        $log_query = "INSERT INTO USER_LOGS (USER_ID, LOG_TYPE, LOG_DETAILS, LOG_TIMESTAMP, LOG_IP_ADDRESS) 
                     VALUES (:user_id, 'LOGIN', :details, CURRENT_TIMESTAMP, :ip_address)";
        $log_stmt = $pdo->prepare($log_query);
        $details = "User " . $username . " logged in successfully via " . 
                   ($is_admin_login ? "admin login" : "regular login");
        $log_stmt->bindParam(":user_id", $user["user_id"]);
        $log_stmt->bindParam(":details", $details);
        $log_stmt->bindParam(":ip_address", $client_ip);
        $log_stmt->execute();
        
        // Clear any previous error messages
        unset($_SESSION["error_message"]);
        
        // Redirect based on user role
        if (strtoupper($user["user_role"]) === 'ADMIN') {
            // Admin users go to admin dashboard
            $_SESSION["success_message"] = "Welcome back, Administrator!";
            header("Location: ../admin/index.php");
        } else {
            // Regular users go to e-service
            $_SESSION["success_message"] = "Login successful! Welcome to NCIP Services.";
            header("Location: e-service.php");
        }
        exit();
        
    } catch (PDOException $e) {
        // Get client IP address for error logging
        $client_ip = $_SERVER['HTTP_CLIENT_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        
        // Log database error with more details
        error_log("Login PDO error: " . $e->getMessage() . " | IP: " . $client_ip . " | Username: " . $username);
        
        // Log failed login attempt in database if possible
        try {
            if (isset($pdo)) {
                $error_log_query = "INSERT INTO USER_LOGS (USER_ID, LOG_TYPE, LOG_DETAILS, LOG_TIMESTAMP, LOG_IP_ADDRESS) 
                                   VALUES (NULL, 'LOGIN_ERROR', :details, CURRENT_TIMESTAMP, :ip_address)";
                $error_log_stmt = $pdo->prepare($error_log_query);
                $error_details = "Failed login attempt for username: " . $username . " | Error: " . $e->getMessage();
                $error_log_stmt->bindParam(":details", $error_details);
                $error_log_stmt->bindParam(":ip_address", $client_ip);
                $error_log_stmt->execute();
            }
        } catch (Exception $log_error) {
            // If logging fails, just log to error log
            error_log("Failed to log login error to database: " . $log_error->getMessage());
        }
        
        $_SESSION["error_message"] = "An error occurred during login. Please try again.";
        
        if ($is_admin_login) {
            header("Location: ../admin/login.php");
        } else {
            header("Location: form-content.php?show=login");
        }
        exit();
        
    } catch (Exception $e) {
        // Handle other exceptions (from validator, etc.)
        $client_ip = $_SERVER['HTTP_CLIENT_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        
        error_log("Login validation error: " . $e->getMessage() . " | IP: " . $client_ip . " | Username: " . $username);
        
        // Log failed login attempt
        try {
            if (isset($pdo)) {
                $error_log_query = "INSERT INTO USER_LOGS (USER_ID, LOG_TYPE, LOG_DETAILS, LOG_TIMESTAMP, LOG_IP_ADDRESS) 
                                   VALUES (NULL, 'LOGIN_FAILED', :details, CURRENT_TIMESTAMP, :ip_address)";
                $error_log_stmt = $pdo->prepare($error_log_query);
                $error_details = "Login failed for username: " . $username . " | Reason: " . $e->getMessage();
                $error_log_stmt->bindParam(":details", $error_details);
                $error_log_stmt->bindParam(":ip_address", $client_ip);
                $error_log_stmt->execute();
            }
        } catch (Exception $log_error) {
            error_log("Failed to log login failure to database: " . $log_error->getMessage());
        }
        
        $_SESSION["error_message"] = $e->getMessage();
        
        if ($is_admin_login) {
            header("Location: ../admin/login.php");
        } else {
            header("Location: form-content.php?show=login");
        }
        exit();
    }
} else {
    // If not POST request, redirect to appropriate login page
    if (isset($_GET["admin"])) {
        header("Location: ../admin/login.php");
    } else {
        header("Location: form-content.php?show=login");
    }
    exit();
}
?>