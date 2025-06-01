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

    public function __construct(string $username, string $email, string $password, string $confirm_password, string $verification_code, object $pdo) {
        $this->username = trim($username);
        $this->email = trim($email);
        $this->password = $password;
        $this->confirm_password = $confirm_password;
        $this->verification_code = $verification_code;
        $this->pdo = $pdo;
    }

    public function validate_data(): array {
        $errors = [];

        require_once "admin-models.php";

        // Username validation - updated to allow spaces and more characters
        if (empty($this->username)) {
            $errors['username'] = 'Username is required';
        } elseif (strlen($this->username) < 3) {
            $errors['username'] = 'Username must be at least 3 characters';
        } elseif (strlen($this->username) > 50) {
            $errors['username'] = 'Username cannot exceed 50 characters';
        } elseif (!preg_match('/^[a-zA-Z0-9_\s\-\.]+$/', $this->username)) {
            $errors['username'] = 'Username can only contain letters, numbers, spaces, underscores, hyphens, and periods';
        } elseif (preg_match('/^\s|\s$/', $this->username)) {
            $errors['username'] = 'Username cannot start or end with spaces';
        } elseif (preg_match('/\s{2,}/', $this->username)) {
            $errors['username'] = 'Username cannot contain consecutive spaces';
        }

        // Email validation
        if (empty($this->email)) {
            $errors['email'] = 'Email is required';
        } elseif (!filter_var($this->email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Please enter a valid email address';
        } elseif (strlen($this->email) > 100) {
            $errors['email'] = 'Email cannot exceed 100 characters';
        }

        // Password validation
        if (empty($this->password)) {
            $errors['password'] = 'Password is required';
        } elseif (strlen($this->password) < 8) {
            $errors['password_too_short'] = 'Password must be at least 8 characters';
        } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/', $this->password)) {
            $errors['password_complexity'] = 'Password must contain at least one uppercase letter, one lowercase letter, one number, and one special character';
        }

        // Confirm password validation
        if ($this->password !== $this->confirm_password) {
            $errors['password_match_error'] = 'Passwords do not match';
        }

        // Verification code validation
        if (empty($this->verification_code)) {
            $errors['verification_code'] = 'Admin verification code is required';
        } elseif ($this->verification_code !== self::ADMIN_VERIFICATION_CODE) {
            $errors['invalid_verification_code'] = 'Invalid admin verification code';
        }

        // Database validations (only if basic validations pass)
        if (empty($errors) && $this->pdo) {
            try {
                // Check if account is deleted (allow re-registration of deleted usernames)
                if (is_account_deleted($this->pdo, $this->username)) {
                    $deleted_info = get_deleted_account_info($this->pdo, $this->username);
                    if ($deleted_info && isset($deleted_info['user_deleted_at'])) {
                        $deleted_date = date('M j, Y', strtotime($deleted_info['user_deleted_at']));
                        $errors['username_deleted'] = "This username belongs to a deleted account (deleted on {$deleted_date}). Please choose a different username.";
                    }
                }
                // Check if active username exists
                elseif (username_exists($this->pdo, $this->username)) {
                    $errors['username_exists'] = 'Username already exists';
                }

                // Check if email exists (both active and deleted accounts)
                $email_check_query = "SELECT COUNT(*) FROM users WHERE LOWER(user_email) = LOWER(:email)";
                $email_stmt = $this->pdo->prepare($email_check_query);
                $email_stmt->bindParam(":email", $this->email);
                $email_stmt->execute();
                if ($email_stmt->fetchColumn() > 0) {
                    $errors['email_exists'] = 'Email already exists';
                }
            } catch (PDOException $e) {
                $errors['database'] = 'Database validation error: ' . $e->getMessage();
            }
        }

        return $errors;
    }
}

class AdminLoginValidator {
    private $username;
    private $password;
    private $pdo;

    public function __construct(string $username, string $password, object $pdo) {
        $this->username = $username;
        $this->password = $password;
        $this->pdo = $pdo;
    }

    public function get_user_data() {
        require_once "admin-models.php";
        return get_admin_by_username($this->pdo, $this->username);
    }

    public function validate_data(): array {
        $errors = [];
        
        // Required fields
        if (empty($this->username) || empty($this->password)) {
            $errors["incomplete_form"] = "Please fill all the fields";
            return $errors;
        }

        // Check if account is deleted first
        require_once "admin-models.php";
        if (is_account_deleted($this->pdo, $this->username)) {
            $deleted_info = get_deleted_account_info($this->pdo, $this->username);
            if ($deleted_info) {
                $deleted_date = date('M j, Y g:i A', strtotime($deleted_info['user_deleted_at']));
                $deleted_by = $deleted_info['deleted_by_username'] ?: 'Administrator';
                $errors["account_deleted"] = "This account has been deleted on {$deleted_date} by {$deleted_by}. Please contact the administrator for assistance.";
                
                // Log the failed login attempt on deleted account
                try {
                    $log_query = "INSERT INTO user_logs (log_type, log_details, log_timestamp) 
                                 VALUES ('LOGIN_ATTEMPT_DELETED', 'Login attempt on deleted account: " . $this->username . "', CURRENT_TIMESTAMP)";
                    $this->pdo->exec($log_query);
                } catch (PDOException $e) {
                    // Silently handle logging error
                    error_log("Failed to log deleted account login attempt: " . $e->getMessage());
                }
                
                return $errors;
            }
        }

        // User existence and password verification (only for active accounts)
        $user = $this->get_user_data();

        if (empty($user)) {
            $errors["invalid_credentials"] = "Invalid username or password";
            return $errors;
        }
        
        // Handle lowercase column names consistently
        $password_field = null;
        $role_field = null;
        $deleted_at_field = null;
        
        // Find the fields (all should be lowercase now)
        foreach ($user as $key => $value) {
            if (strtolower($key) === 'user_pwd') {
                $password_field = $key;
            }
            if (strtolower($key) === 'user_role') {
                $role_field = $key;
            }
            if (strtolower($key) === 'user_deleted_at') {
                $deleted_at_field = $key;
            }
        }
        
        // Double-check if account is deleted (should not happen due to query filter)
        if ($deleted_at_field && !is_null($user[$deleted_at_field])) {
            $errors["account_deleted"] = "This account has been deleted. Please contact the administrator for assistance.";
            return $errors;
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

class AdminPasswordResetValidator {
    private $token;
    private $password;
    private $confirm_password;
    private $pdo;

    public function __construct(string $token, string $password, string $confirm_password, object $pdo) {
        $this->token = trim($token);
        $this->password = $password;
        $this->confirm_password = $confirm_password;
        $this->pdo = $pdo;
    }

    public function validate_data(): array {
        $errors = [];

        require_once "admin-models.php";

        // Token validation
        if (empty($this->token)) {
            $errors['token'] = 'Reset token is required';
            return $errors;
        }

        // Check if token is valid and not expired
        $user = get_admin_by_token($this->pdo, $this->token);
        if (!$user) {
            $errors['invalid_token'] = 'Invalid or expired reset token';
            return $errors;
        }

        // Password validation
        if (empty($this->password)) {
            $errors['password'] = 'Password is required';
        } elseif (strlen($this->password) < 8) {
            $errors['password_too_short'] = 'Password must be at least 8 characters';
        } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/', $this->password)) {
            $errors['password_complexity'] = 'Password must contain at least one uppercase letter, one lowercase letter, one number, and one special character';
        }

        // Confirm password validation
        if ($this->password !== $this->confirm_password) {
            $errors['password_match_error'] = 'Passwords do not match';
        }

        return $errors;
    }

    public function get_user_by_token() {
        require_once "admin-models.php";
        return get_admin_by_token($this->pdo, $this->token);
    }
}

class AdminProfileUpdateValidator {
    private $user_id;
    private $username;
    private $email;
    private $current_password;
    private $new_password;
    private $confirm_password;
    private $pdo;

    public function __construct(int $user_id, string $username, string $email, string $current_password = '', string $new_password = '', string $confirm_password = '', object $pdo) {
        $this->user_id = $user_id;
        $this->username = trim($username);
        $this->email = trim($email);
        $this->current_password = $current_password;
        $this->new_password = $new_password;
        $this->confirm_password = $confirm_password;
        $this->pdo = $pdo;
    }

    public function validate_data(): array {
        $errors = [];

        require_once "admin-models.php";

        // Get current user data
        $current_user = get_user_by_id($this->pdo, $this->user_id);
        if (!$current_user) {
            $errors['user'] = 'User not found';
            return $errors;
        }

        // Username validation
        if (empty($this->username)) {
            $errors['username'] = 'Username is required';
        } elseif (strlen($this->username) < 3) {
            $errors['username'] = 'Username must be at least 3 characters';
        } elseif (strlen($this->username) > 50) {
            $errors['username'] = 'Username cannot exceed 50 characters';
        } elseif (!preg_match('/^[a-zA-Z0-9_\s\-\.]+$/', $this->username)) {
            $errors['username'] = 'Username can only contain letters, numbers, spaces, underscores, hyphens, and periods';
        } elseif (preg_match('/^\s|\s$/', $this->username)) {
            $errors['username'] = 'Username cannot start or end with spaces';
        } elseif (preg_match('/\s{2,}/', $this->username)) {
            $errors['username'] = 'Username cannot contain consecutive spaces';
        } else {
            // Check if username is taken by another user
            $username_check = get_admin_by_username($this->pdo, $this->username);
            if ($username_check && $username_check['user_id'] != $this->user_id) {
                $errors['username_exists'] = 'Username already exists';
            }
        }

        // Email validation
        if (empty($this->email)) {
            $errors['email'] = 'Email is required';
        } elseif (!filter_var($this->email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Please enter a valid email address';
        } elseif (strlen($this->email) > 100) {
            $errors['email'] = 'Email cannot exceed 100 characters';
        } else {
            // Check if email is taken by another user
            $email_check = get_admin_by_email($this->pdo, $this->email);
            if ($email_check && $email_check['user_id'] != $this->user_id) {
                $errors['email_exists'] = 'Email already exists';
            }
        }

        // Password change validation (optional)
        if (!empty($this->new_password)) {
            // Current password is required when changing password
            if (empty($this->current_password)) {
                $errors['current_password'] = 'Current password is required to change password';
            } else {
                // Verify current password
                $password_field = 'user_pwd';
                if (!password_verify($this->current_password, $current_user[$password_field])) {
                    $errors['current_password_invalid'] = 'Current password is incorrect';
                }
            }

            // New password validation
            if (strlen($this->new_password) < 8) {
                $errors['new_password_too_short'] = 'New password must be at least 8 characters';
            } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/', $this->new_password)) {
                $errors['new_password_complexity'] = 'New password must contain at least one uppercase letter, one lowercase letter, one number, and one special character';
            }

            // Confirm new password
            if ($this->new_password !== $this->confirm_password) {
                $errors['password_match_error'] = 'New passwords do not match';
            }
        }

        return $errors;
    }
}