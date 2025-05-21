<?php

declare(strict_types=1);

class RegisterValidator {
    private $username;
    private $email;
    private $password;
    private $confirm_password;
    private $pdo;
    private $role;
    private $redirect_url;

    function __construct(string $username, string $email, string $password, string $confirm_password, object $pdo, string $role = 'USER', string $redirect_url = "form-content.php?show=register"){
        $this->username = $username;
        $this->email = $email;
        $this->password = $password;
        $this->confirm_password = $confirm_password;
        $this->pdo = $pdo;
        $this->role = $role;
        $this->redirect_url = $redirect_url;
    }

    function validate_data(){
        $errors = [];

        require_once "user-models.php";

        if (empty($this->username) || empty($this->email) || empty($this->password) || empty($this->confirm_password)) {
            $errors["incomplete_form"] = "Please fill all the fields";
        }

        if ($this->password !== $this->confirm_password) {
            $errors["password_match_error"] = "Passwords do not match";
        }

        if (strlen($this->password) < 5) {
            $errors["password_too_short"] =  "Password must be greater than or equal to 5 characters";
        }

        if (!filter_var($this->email, FILTER_VALIDATE_EMAIL)) {
            $errors["invalid_email"] = "Please enter a valid email address";
        }

        if(!empty(get_user_username($this->pdo, $this->username))) {
            $errors["username_exists"] = "Username already exists";
        }

        if(!empty(get_user_email($this->pdo, $this->email))) {
            $errors["email_exists"] = "Email already exists";
        }

        // Validate role - ensure only valid roles are used
        if (!in_array(strtoupper($this->role), ['USER', 'ADMIN'])) {
            $errors["invalid_role"] = "Invalid user role specified";
        }

        if (!empty($errors)) {
            $_SESSION["signup_errors"] = $errors;
            header("Location: " . $this->redirect_url);
            die();
        }

        return $this->role; // Return the validated role for use when creating the user
    }
};

class LoginValidator {
    private $username;
    private $password;
    private $pdo;
    private $required_role;
    private $redirect_url;

    function __construct(string $username, string $password, object $pdo, string $required_role = '', string $redirect_url = "form-content.php?show=login") {
        $this->username = $username;
        $this->password = $password;
        $this->pdo = $pdo;
        $this->required_role = $required_role;
        $this->redirect_url = $redirect_url;
    }

    function get_user_data(){
        require_once "user-models.php";
        return get_user_username($this->pdo, $this->username);
    }

    function validate_data() {
        $errors = [];

        require_once "user-models.php";

        if (empty($this->username) || empty($this->password)) {
            $errors["incomplete_form"] = "Please fill all the fields";
        }

        $user = $this->get_user_data();

        if (!$user) {
            $errors["invalid_username"] = "Invalid username entered";
        } else {
            // Check password
            if (!password_verify($this->password, $user["user_pwd"])) {
                $errors["invalid_password"] = "Invalid password entered";
            }
            
            // Check role if a specific role is required
            if (!empty($this->required_role) && strtoupper($user["user_role"]) !== strtoupper($this->required_role)) {
                $errors["insufficient_privileges"] = "You don't have the required permissions to access this area";
                
                // Log unauthorized access attempt
                $log_query = "INSERT INTO USER_LOGS (USER_ID, LOG_TYPE, LOG_DETAILS, LOG_TIMESTAMP) 
                              VALUES (?, 'UNAUTHORIZED_ACCESS', ?, CURRENT_TIMESTAMP)";
                $log_stmt = $this->pdo->prepare($log_query);
                $details = "Attempted to access " . $this->required_role . " area with " . $user["user_role"] . " privileges";
                $log_stmt->execute([$user["user_id"], $details]);
            }
        }

        if (!empty($errors)) {
            $_SESSION["login_errors"] = $errors;
            header("Location: " . $this->redirect_url);
            die();
        }
        
        // Log successful login
        if ($user) {
            $log_query = "INSERT INTO USER_LOGS (USER_ID, LOG_TYPE, LOG_DETAILS, LOG_TIMESTAMP) 
                         VALUES (?, 'LOGIN', ?, CURRENT_TIMESTAMP)";
            $log_stmt = $this->pdo->prepare($log_query);
            $details = "Successful login by " . $user["user_username"] . " with " . $user["user_role"] . " role";
            $log_stmt->execute([$user["user_id"], $details]);
        }
        
        return $user;
    }
};

/**
 * Admin validator class for admin-specific registrations
 * Extends RegisterValidator with additional admin-specific validations
 */
class AdminRegisterValidator extends RegisterValidator {
    private $verification_code;
    private const ADMIN_VERIFICATION_CODE = 'NcipCebuConnect#2025'; // Secret admin verification code
    
    function __construct(string $username, string $email, string $password, string $confirm_password, string $verification_code, object $pdo) {
        parent::__construct($username, $email, $password, $confirm_password, $pdo, 'ADMIN', 'admin/register.php');
        $this->verification_code = $verification_code;
    }
    
    function validate_data() {
        $errors = [];
        
        // First do standard validation
        require_once "user-models.php";
        
        if (empty($this->username) || empty($this->email) || empty($this->password) || empty($this->confirm_password) || empty($this->verification_code)) {
            $errors["incomplete_form"] = "Please fill all the fields";
        }
        
        if ($this->password !== $this->confirm_password) {
            $errors["password_match_error"] = "Passwords do not match";
        }
        
        // Stronger password requirements for admins
        if (strlen($this->password) < 8) {
            $errors["password_too_short"] = "Password must be at least 8 characters long";
        }
        
        if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/', $this->password)) {
            $errors["password_complexity"] = "Password must contain at least one uppercase letter, one lowercase letter, and one number";
        }
        
        if (!filter_var($this->email, FILTER_VALIDATE_EMAIL)) {
            $errors["invalid_email"] = "Please enter a valid email address";
        }
        
        if(!empty(get_user_username($this->pdo, $this->username))) {
            $errors["username_exists"] = "Username already exists";
        }
        
        if(!empty(get_user_email($this->pdo, $this->email))) {
            $errors["email_exists"] = "Email already exists";
        }
        
        // Verification code validation
        if ($this->verification_code !== self::ADMIN_VERIFICATION_CODE) {
            $errors["invalid_verification_code"] = "Invalid verification code";
        }
        
        if (!empty($errors)) {
            $_SESSION["signup_errors"] = $errors;
            return $errors; // For admin, return errors instead of redirecting
        }
        
        return "ADMIN"; // Return the admin role
    }
}

/**
 * Check if current logged-in user has admin privileges
 * 
 * @return bool True if user is admin, false otherwise
 */
function is_admin_user() {
    if (!isset($_SESSION["user"]) || !isset($_SESSION["user"]["user_role"])) {
        return false;
    }
    
    return strtoupper($_SESSION["user"]["user_role"]) === 'ADMIN';
}

/**
 * Require admin privileges to access a page
 * If user is not admin, redirects to unauthorized page
 */
function require_admin() {
    if (!is_admin_user()) {
        // Log unauthorized access if user is logged in but not admin
        if (isset($_SESSION["user"]) && isset($_SESSION["user"]["user_id"])) {
            global $pdo;
            if ($pdo) {
                $log_query = "INSERT INTO USER_LOGS (USER_ID, LOG_TYPE, LOG_DETAILS, LOG_TIMESTAMP) 
                             VALUES (?, 'UNAUTHORIZED_ACCESS', ?, CURRENT_TIMESTAMP)";
                $log_stmt = $pdo->prepare($log_query);
                $details = "Non-admin user attempted to access admin area: " . $_SERVER['REQUEST_URI'];
                $log_stmt->execute([$_SESSION["user"]["user_id"], $details]);
            }
        }
        
        header("Location: unauthorized.php");
        exit();
    }
}