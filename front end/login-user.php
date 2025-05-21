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
        
        // Log successful login
        $log_query = "INSERT INTO USER_LOGS (USER_ID, LOG_TYPE, LOG_DETAILS, LOG_TIMESTAMP) 
                     VALUES (:user_id, 'LOGIN', :details, CURRENT_TIMESTAMP)";
        $log_stmt = $pdo->prepare($log_query);
        $details = "User " . $username . " logged in successfully via " . 
                   ($is_admin_login ? "admin login" : "regular login");
        $log_stmt->bindParam(":user_id", $user["user_id"]);
        $log_stmt->bindParam(":details", $details);
        $log_stmt->execute();
        
        // Redirect based on user role
        if (strtoupper($user["user_role"]) === 'ADMIN') {
            // Admin users go to admin dashboard
            header("Location: ../admin/index.php");
        } else {
            // Regular users go to e-service
            header("Location: e-service.php");
        }
        exit();
        
    } catch (PDOException $e) {
        // Log database error
        error_log("Login error: " . $e->getMessage());
        $_SESSION["error_message"] = "An error occurred during login. Please try again.";
        
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