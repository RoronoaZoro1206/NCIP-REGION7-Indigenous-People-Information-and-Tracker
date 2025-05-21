<?php
declare(strict_types=1);

class AdminRegisterValidator {
    private $username;
    private $email;
    private $password;
    private $confirm_password;
    private $verification_code;
    private $pdo;
    private const ADMIN_VERIFICATION_CODE = 'NcipCebuConnect#2025';

    function __construct(string $username, string $email, string $password, string $confirm_password, string $verification_code, object $pdo) {
        $this->username = $username;
        $this->email = $email;
        $this->password = $password;
        $this->confirm_password = $confirm_password;
        $this->verification_code = $verification_code;
        $this->pdo = $pdo;
    }

    function validate_data() {
        $errors = [];

        require_once "admin-models.php";

        // Required fields
        if (empty($this->username) || empty($this->email) || empty($this->password) || empty($this->confirm_password) || empty($this->verification_code)) {
            $errors["incomplete_form"] = "Please fill all the fields";
        }

        // Password validation
        if ($this->password !== $this->confirm_password) {
            $errors["password_match_error"] = "Passwords do not match";
        }

        if (strlen($this->password) < 8) {
            $errors["password_too_short"] = "Password must be at least 8 characters long";
        }

        // Verify password strength
        if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/', $this->password)) {
            $errors["password_complexity"] = "Password must contain at least one uppercase letter, one lowercase letter, and one number";
        }

        // Email validation
        if (!filter_var($this->email, FILTER_VALIDATE_EMAIL)) {
            $errors["invalid_email"] = "Please enter a valid email address";
        }

        // Username and email uniqueness
        if (!empty(get_admin_by_username($this->pdo, $this->username))) {
            $errors["username_exists"] = "Username already exists";
        }

        if (!empty(get_admin_by_email($this->pdo, $this->email))) {
            $errors["email_exists"] = "Email already exists";
        }

        // Verification code check
        if ($this->verification_code !== self::ADMIN_VERIFICATION_CODE) {
            $errors["invalid_verification_code"] = "Invalid verification code";
        }

        return $errors;
    }
}

class AdminLoginValidator {
    private $username;
    private $password;
    private $pdo;

    function __construct(string $username, string $password, object $pdo) {
        $this->username = $username;
        $this->password = $password;
        $this->pdo = $pdo;
    }

    function get_user_data() {
        require_once "admin-models.php";
        return get_admin_by_username($this->pdo, $this->username);
    }

function validate_data() {
    $errors = [];
    
    // Required fields
    if (empty($this->username) || empty($this->password)) {
        $errors["incomplete_form"] = "Please fill all the fields";
        return $errors;
    }

    // User existence and password verification
    $user = $this->get_user_data();

    // Debug the user data
    if (empty($user)) {
        $errors["invalid_credentials"] = "Invalid username or password";
        return $errors;
    }
    
        // Handle case-insensitive column names for database compatibility
        $password_field = null;
        $role_field = null;
        
        // Find the password and role fields regardless of case
        foreach ($user as $key => $value) {
            if (strtoupper($key) === 'USER_PWD') {
                $password_field = $key;
            }
            if (strtoupper($key) === 'USER_ROLE') {
                $role_field = $key;
            }
        }
        
        if (!$password_field) {
            error_log("Password field missing from user data for username: " . $this->username);
            $errors["system_error"] = "Authentication system error. Please contact administrator.";
            return $errors;
        }
        
        if (!password_verify($this->password, $user[$password_field])) {
            $errors["invalid_credentials"] = "Invalid username or password";
            return $errors;
        }
        
        // Check role
        if (!$role_field || strtoupper($user[$role_field]) !== 'ADMIN') {
            $errors["not_admin"] = "You do not have administrator privileges";
            return $errors;
        }

        // If we got here, validation succeeded - return empty errors array
        return [];
    }
}