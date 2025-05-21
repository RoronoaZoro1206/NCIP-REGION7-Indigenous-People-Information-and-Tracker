<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER["REQUEST_METHOD"] == "POST"){
    
    $username = $_POST["username"];
    $email = $_POST["email"];
    $password = $_POST["password"];
    $confirm_password = $_POST["confirm_password"];
    $timezone = $_POST["timezone"] ?? 'UTC';
    $is_admin_register = isset($_POST["admin_register"]) && $_POST["admin_register"] === "true";
    
    // Default role is USER unless specifically registering as admin
    $role = $is_admin_register ? 'ADMIN' : 'USER';
   
    try {
        $dt = new DateTime("now", new DateTimeZone($timezone));
        $user_created = $dt->format('Y-m-d H:i:sP'); 
    } catch (Exception $e) {
        $user_created = date('Y-m-d H:i:sP');
    }

    try {
        require_once 'db.php';
        require_once 'user-models.php';
        require_once 'validator.php';

        // Use appropriate validator based on registration type
        if ($is_admin_register) {
            $verification_code = $_POST["verification_code"] ?? '';
            $validator = new AdminRegisterValidator($username, $email, $password, $confirm_password, $verification_code, $pdo);
            // AdminRegisterValidator will return "ADMIN" or errors
            $validated_role = $validator->validate_data();
            
            // If errors were returned instead of a role, handle them
            if (is_array($validated_role)) {
                $_SESSION['errors'] = $validated_role;
                $_SESSION['old'] = $_POST;
                header("Location: ../admin/register.php?error=1");
                exit();
            }
            
            $role = $validated_role; // Will be "ADMIN" if validation passed
        } else {
            // For regular users
            $validator = new RegisterValidator($username, $email, $password, $confirm_password, $pdo, $role);
            $validated_role = $validator->validate_data();
            // If validation successful, validated_role will contain the role
            $role = $validated_role;
        }

        // Create user with the validated role
        create_user($pdo, $username, $email, $password, $timezone, $user_created, $role);

        // Log the registration
        $log_query = "INSERT INTO USER_LOGS (LOG_TYPE, LOG_DETAILS, LOG_TIMESTAMP) 
                     VALUES ('REGISTRATION', :details, CURRENT_TIMESTAMP)";
        $log_stmt = $pdo->prepare($log_query);
        $details = "New " . $role . " account created: " . $username;
        $log_stmt->bindParam(":details", $details);
        $log_stmt->execute();

        $_SESSION["signup_success"] = "Signup successful. Please proceed to login";
        
        // Redirect to appropriate login page based on role
        if ($role === 'ADMIN') {
            header("Location: ../admin/login.php?success=1");
        } else {
            header("Location: form-content.php?show=login&success=1");
        }
        exit();
        
    } catch (Exception $ve) {
        $_SESSION['errors'] = [$ve->getMessage()];
        $_SESSION['old'] = $_POST;
        
        if ($is_admin_register) {
            header("Location: ../admin/register.php?error=1");
        } else {
            header("Location: form-content.php?show=register&error=1");
        }
        exit();
        
    } catch (PDOException $e) {
        // Log database error
        error_log("Registration error: " . $e->getMessage());
        $_SESSION["error_message"] = "An error occurred during registration. Please try again.";
        
        if ($is_admin_register) {
            header("Location: ../admin/register.php?error=1");
        } else {
            header("Location: form-content.php?show=register&error=1");
        }
        exit();
    }

} else {
    // If not POST request, redirect to appropriate registration page
    if (isset($_GET["admin"])) {
        header("Location: ../admin/register.php");
    } else {
        header("Location: form-content.php?show=register");
    }
    exit();
}