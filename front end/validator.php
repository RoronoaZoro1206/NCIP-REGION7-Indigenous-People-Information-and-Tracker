<?php
// filepath: c:\xampp\htdocs\Information Management\IM---Copy\front end\validator.php

declare(strict_types=1);

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

class RegisterValidator {
    protected $username;    // Changed from private to protected
    protected $email;       // Changed from private to protected
    protected $password;    // Changed from private to protected
    protected $confirm_password; // Changed from private to protected
    protected $pdo;         // Changed from private to protected
    protected $role;        // Changed from private to protected
    protected $redirect_url; // Changed from private to protected

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
}

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
    // Bring in unified logger for consistent DB logging of auth events
    // This is safe even if logger is missing; we'll suppress any issues.
    try { require_once "logger.php"; } catch (Throwable $e) { /* ignore */ }
    $client_ip = $_SERVER['HTTP_CLIENT_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'Unknown';

        if (empty($this->username) || empty($this->password)) {
            $errors["incomplete_form"] = "Please fill all the fields";
            // Log incomplete form submission as a failed attempt
            if (function_exists('log_user_event')) {
                $details = "Missing username and/or password for username: " . ($this->username ?: '');
                log_user_event($this->pdo, 'LOGIN_ATTEMPT_FAILED', $details, null, $client_ip);
            }
        }

        $user = $this->get_user_data();

        if (!$user) {
            $errors["invalid_username"] = "Invalid username entered";
            // Log invalid username attempt
            if (function_exists('log_user_event')) {
                $details = "Invalid username entered: " . $this->username;
                log_user_event($this->pdo, 'LOGIN_ATTEMPT_FAILED', $details, null, $client_ip);
            }
        } else {
            // Check password
            if (!password_verify($this->password, $user["user_pwd"])) {
                $errors["invalid_password"] = "Invalid password entered";
                // Log invalid password attempt, associate with user if we have ID
                if (function_exists('log_user_event')) {
                    $details = "Invalid password for username: " . $this->username;
                    $uid = isset($user['user_id']) ? (int)$user['user_id'] : null;
                    log_user_event($this->pdo, 'LOGIN_ATTEMPT_FAILED', $details, $uid, $client_ip);
                }
            }
            
            // Check role if a specific role is required
            if (!empty($this->required_role) && strtoupper($user["user_role"]) !== strtoupper($this->required_role)) {
                $errors["insufficient_privileges"] = "You don't have the required permissions to access this area";
                
                // Log unauthorized access attempt via unified logger
                if (function_exists('log_user_event')) {
                    $uid = isset($user['user_id']) ? (int)$user['user_id'] : null;
                    $details = "Attempted to access " . $this->required_role . " area with " . $user["user_role"] . " privileges";
                    log_user_event($this->pdo, 'UNAUTHORIZED_ACCESS', $details, $uid, $client_ip);
                }
            }
        }

        if (!empty($errors)) {
            $_SESSION["login_errors"] = $errors;
            header("Location: " . $this->redirect_url);
            die();
        }
        
        // Log successful login (keep for backward compatibility; note that callers may also log)
        if ($user && function_exists('log_user_event')) {
            $uid = isset($user['user_id']) ? (int)$user['user_id'] : null;
            $details = "Successful login by " . $user["user_username"] . " with " . $user["user_role"] . " role";
            log_user_event($this->pdo, 'LOGIN', $details, $uid, $client_ip);
        }
        
        return $user;
    }
}

/**
 * Admin validator class for admin-specific registrations
 * Extends RegisterValidator with additional admin-specific validations
 */
if (!class_exists('AdminRegisterValidator')) {
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
}

/**
 * Check if current logged-in user has admin privileges
 * Uses flexible session structure to accommodate different session formats
 * 
 * @return bool True if user is admin, false otherwise
 */
function is_admin_user() {
    // Check both session structures for compatibility
    if (isset($_SESSION["user"]) && isset($_SESSION["user"]["user_role"])) {
        return strtoupper($_SESSION["user"]["user_role"]) === 'ADMIN';
    }
    
    // Alternative session structure
    if (isset($_SESSION["role"])) {
        return strtoupper($_SESSION["role"]) === 'ADMIN';
    }
    
    return false;
}

/**
 * Get current user ID from session
 * Handles different session structures
 * 
 * @return int|null User ID or null if not found
 */
function get_current_user_id() {
    if (isset($_SESSION["user"]) && isset($_SESSION["user"]["user_id"])) {
        return $_SESSION["user"]["user_id"];
    }
    
    if (isset($_SESSION["user_id"])) {
        return $_SESSION["user_id"];
    }
    
    return null;
}

/**
 * Require admin privileges to access a page
 * If user is not admin, redirects to unauthorized page
 * 
 * @param object|null $pdo Optional PDO connection for logging
 */
function require_admin($pdo = null) {
    if (!is_admin_user()) {
        // Log unauthorized access if user is logged in but not admin
        $user_id = get_current_user_id();
        if ($user_id && $pdo) {
            try {
                $log_query = "insert into user_logs (user_id, log_type, log_details, log_timestamp) 
                             values (?, 'UNAUTHORIZED_ACCESS', ?, current_timestamp)";
                $log_stmt = $pdo->prepare($log_query);
                $details = "Non-admin user attempted to access admin area: " . $_SERVER['REQUEST_URI'];
                $log_stmt->execute([$user_id, $details]);
            } catch (PDOException $e) {
                error_log("Failed to log unauthorized access: " . $e->getMessage());
            }
        }
        
        // Check if unauthorized.php exists, otherwise redirect to login
        if (file_exists("unauthorized.php")) {
            header("Location: unauthorized.php");
        } else if (file_exists("../unauthorized.php")) {
            header("Location: ../unauthorized.php");
        } else {
            header("Location: login.php");
        }
        exit();
    }
}

/**
 * Safely get user role from session
 * 
 * @return string|null Current user role or null
 */
function get_user_role() {
    if (isset($_SESSION["user"]) && isset($_SESSION["user"]["user_role"])) {
        return $_SESSION["user"]["user_role"];
    }
    
    if (isset($_SESSION["role"])) {
        return $_SESSION["role"];
    }
    
    return null;
}

/**
 * Check if user is logged in
 * 
 * @return bool True if user is logged in, false otherwise
 */
function is_logged_in() {
    return get_current_user_id() !== null;
}
?>