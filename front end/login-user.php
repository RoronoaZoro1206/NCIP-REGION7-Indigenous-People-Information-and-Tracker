<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST["username"] ?? null;
    $password = $_POST["password"] ?? null;
    $is_admin_login = isset($_POST["admin_login"]) && $_POST["admin_login"] === "true";

    if (!$username || !$password) {
        // Attempt logging for missing credentials
        try {
            require_once 'logger.php';
            if (function_exists('log_user_event')) {
                $client_ip = $_SERVER['HTTP_CLIENT_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
                // No DB yet; we'll try to load PDO below if needed. This is best-effort.
                log_user_event(null, 'LOGIN_ATTEMPT_FAILED', 'Missing username and/or password', null, $client_ip);
            }
        } catch (Throwable $e) { /* ignore */ }
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
    // Unified logger
    try { require_once 'logger.php'; } catch (Throwable $e) { /* ignore */ }

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
        
    // Successful login logging is performed inside validator.php to centralize behavior.
        
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
        
        // Log failed login attempt via unified logger (DB if available)
        try {
            if (function_exists('log_user_event')) {
                $error_details = "Failed login attempt for username: " . $username . " | Error: " . $e->getMessage();
                log_user_event(isset($pdo) ? $pdo : null, 'LOGIN_ERROR', $error_details, null, $client_ip);
            }
        } catch (Throwable $log_error) {
            error_log("Failed to log login error: " . $log_error->getMessage());
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
        
        // Log failed login attempt via unified logger
        try {
            if (function_exists('log_user_event')) {
                $error_details = "Login failed for username: " . $username . " | Reason: " . $e->getMessage();
                log_user_event(isset($pdo) ? $pdo : null, 'LOGIN_ATTEMPT_FAILED', $error_details, null, $client_ip);
            }
        } catch (Throwable $log_error) {
            error_log("Failed to log login failure: " . $log_error->getMessage());
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