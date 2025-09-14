<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if already logged in
if (isset($_SESSION["user_id"])) {
    header("Location: index.php");
    exit();
}

// Initialize variables
$register_error = "";
$register_success = "";
$errors = [];
$pdo = null;

// Form data
$username = "";
$email = "";

// Include shared logger
try { require_once __DIR__ . '/../front end/logger.php'; } catch (Throwable $e) { /* ignore */ }

try {
    // Include the database configuration
    require_once "config/database.php";
} catch (Exception $e) {
    // Fallback connection if include fails
    $host = "localhost";     
    $dbname = "Ncip_Project"; 
    $dbusername = "postgres"; 
    $dbpassword = "!*Lawrence1206"; 

    try {
        $pdo = new PDO("pgsql:host=$host;dbname=$dbname", $dbusername, $dbpassword);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec("SET TIME ZONE INTERVAL '+08:00'");
    } catch (PDOException $dbError) {
        $register_error = "Database connection error. Please contact the administrator.";
        $errors['database'] = $register_error;
    }
}

// Process registration form
if ($_SERVER["REQUEST_METHOD"] == "POST" && $pdo !== null) {
    $username = $_POST["username"] ?? '';
    $email = $_POST["email"] ?? '';
    $password = $_POST["password"] ?? '';
    $confirm_password = $_POST["confirm_password"] ?? '';
    $verification_code = $_POST["verification_code"] ?? '';
    
    try {
        // Include necessary files
        require_once "includes/admin-validator.php";
        require_once "includes/admin-models.php";
        
        // Create validator instance
        $validator = new AdminRegisterValidator($username, $email, $password, $confirm_password, $verification_code, $pdo);
        $errors = $validator->validate_data();
        
        if (empty($errors)) {
            // Create new admin user
            $user_id = create_admin($pdo, $username, $email, $password);
            
            if ($user_id) {
                $register_success = "Registration successful! You can now login with your credentials.";
                $username = "";
                $email = "";
                if (function_exists('log_user_event')) {
                    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
                    log_user_event($pdo, 'REGISTRATION', 'New USER account created successfully', (int)$user_id, $ip);
                }
            } else {
                $register_error = "Registration failed. Please try again.";
                $errors['registration'] = $register_error;
                if (function_exists('log_user_event')) {
                    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
                    $who = $username !== '' ? ("username=" . $username . ($email? ", email=".$email : '')) : '';
                    log_user_event($pdo, 'REGISTRATION_FAILED', 'Registration failed ' . $who, null, $ip);
                }
            }
        } else {
            // Display first error in alert
            $register_error = array_values($errors)[0];
            if (function_exists('log_user_event')) {
                $ip = $_SERVER['REMOTE_ADDR'] ?? null;
                $who = $username !== '' ? ("username=" . $username . ($email? ", email=".$email : '')) : '';
                log_user_event($pdo, 'REGISTRATION_VALIDATION_ERROR', $register_error . ' ' . $who, null, $ip);
            }
        }
    } catch (PDOException $e) {
        $register_error = "Registration failed: Database error occurred.";
        $errors['database'] = $register_error;
        error_log("Registration PDO Error: " . $e->getMessage());
        if (function_exists('log_user_event')) {
            $ip = $_SERVER['REMOTE_ADDR'] ?? null;
            log_user_event($pdo, 'EXCEPTION', 'Registration PDO Error', null, $ip);
        }
    } catch (Exception $e) {
        $register_error = "Registration failed: " . $e->getMessage();
        $errors['general'] = $register_error;
        error_log("Registration Error: " . $e->getMessage());
        if (function_exists('log_user_event')) {
            $ip = $_SERVER['REMOTE_ADDR'] ?? null;
            log_user_event($pdo, 'EXCEPTION', 'Registration Error: ' . $e->getMessage(), null, $ip);
        }
    }
}

// Convert errors to JSON for JavaScript
$errors_json = json_encode($errors);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NCIP Admin - Registration</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
<style>
    body {
        background-color: #f8f9fa;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        min-height: 100vh;
        margin: 0;
        padding: 0;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .register-container {
        max-width: 500px;
        margin: 20px auto;
        width: 100%;
        padding: 0 20px;
        box-sizing: border-box;
    }
    .register-logo {
        text-align: center;
        margin-bottom: 20px;
    }
    .register-logo img {
        max-width: 120px;
        height: auto;
    }
    .card {
        border: none;
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        border-radius: 10px;
    }
    .card-header {
        background-color: #0d6efd;
        color: white;
        font-weight: bold;
        border-radius: 10px 10px 0 0 !important;
        padding: 12px 15px;
    }
    .card-body {
        padding: 20px;
    }
    .btn-primary {
        background-color: #0d6efd;
        border-color: #0d6efd;
        width: 100%;
        padding: 8px;
        font-weight: 500;
    }
    .input-group-text {
        background-color: #e9ecef;
        border-right: none;
    }
    .form-control {
        border-left: none;
    }
    .form-control:focus {
        border-color: #0d6efd;
        box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
    }
    .login-link {
        text-align: center;
        margin-top: 15px;
    }
    .login-link p {
        margin-bottom: 0;
        font-size: 14px;
    }
    .toast-container {
        z-index: 1060;
    }
    .toast {
        min-width: 300px;
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        border: none;
        border-radius: 10px;
        opacity: 1;
    }
    .toast-header {
        border-radius: 10px 10px 0 0;
        padding: 0.75rem 1rem;
    }
    .error-item {
        padding: 8px 0;
        border-bottom: 1px solid rgba(0,0,0,.05);
    }
    .error-item:last-child {
        border-bottom: none;
    }
    .error-field {
        font-weight: 500;
        color: #dc3545;
    }
    .password-requirements {
        font-size: 0.875rem;
        color: #6c757d;
        margin-top: 0.25rem;
    }
    .verification-info {
        font-size: 0.875rem;
        color: #6c757d;
        margin-top: 0.25rem;
    }
    .username-hint {
        font-size: 0.875rem;
        color: #6c757d;
        margin-top: 0.25rem;
    }
    .mb-3 {
        margin-bottom: 1rem !important;
    }

    /* Responsive adjustments for shorter screens */
    @media (max-height: 800px) {
        .register-container {
            margin: 15px auto;
        }
        .register-logo {
            margin-bottom: 15px;
        }
        .register-logo img {
            max-width: 100px;
        }
        .card-body {
            padding: 15px;
        }
        .mb-3 {
            margin-bottom: 0.75rem !important;
        }
        .password-requirements,
        .verification-info,
        .username-hint {
            font-size: 0.8rem;
        }
    }

    @media (max-height: 700px) {
        .register-container {
            margin: 10px auto;
        }
        .register-logo {
            margin-bottom: 10px;
        }
        .register-logo img {
            max-width: 80px;
        }
        .card-header {
            padding: 10px 15px;
        }
        .card-body {
            padding: 12px;
        }
        .btn-primary {
            padding: 6px;
        }
        .mb-3 {
            margin-bottom: 0.5rem !important;
        }
    }

    @media (max-width: 480px) {
        .register-container {
            max-width: 450px;
            padding: 0 15px;
        }
        .card-body {
            padding: 15px;
        }
    }

    @media (max-width: 400px) {
        .register-container {
            max-width: 380px;
            padding: 0 10px;
        }
    }

    /* Additional height optimization for very short screens */
    @media (max-height: 600px) {
        .register-container {
            margin: 5px auto;
        }
        .register-logo {
            display: none; /* Hide logo on very short screens */
        }
        .card-header {
            padding: 8px 15px;
            font-size: 14px;
        }
        .card-body {
            padding: 10px;
        }
        .form-label {
            font-size: 14px;
            margin-bottom: 0.25rem;
        }
        .password-requirements,
        .verification-info,
        .username-hint {
            font-size: 0.75rem;
            margin-top: 0.15rem;
        }
        .login-link {
            margin-top: 10px;
        }
        .login-link p {
            font-size: 12px;
        }
    }
</style>
</head>
<body>
    <div class="container register-container">
        <div class="register-logo">
            <img src="assets/img/ncip-logo.png" alt="NCIP Logo" onerror="this.style.display='none'">
        </div>
        <div class="card">
            <div class="card-header text-center">
                NCIP Admin Registration
            </div>
            <div class="card-body">
                <?php if($register_error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($register_error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>
                
                <?php if($register_success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($register_success); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>
                
                <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" id="registerForm">
                    <div class="mb-3">
                        <label for="username" class="form-label">Full Name / Username</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-person-fill"></i></span>
                            <input type="text" class="form-control" id="username" name="username" 
                                   value="<?php echo htmlspecialchars($username); ?>" required 
                                   autocomplete="off" maxlength="50" placeholder="e.g. Lawrence Ramirez">
                        </div>
                        <div class="username-hint">3-50 characters, letters, numbers, spaces, underscores, hyphens, and periods allowed</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">Email Address</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-envelope-fill"></i></span>
                            <input type="email" class="form-control" id="email" name="email" 
                                   value="<?php echo htmlspecialchars($email); ?>" required 
                                   autocomplete="off" maxlength="100" placeholder="your.email@example.com">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
                            <input type="password" class="form-control" id="password" name="password" 
                                   required autocomplete="off" placeholder="Enter secure password">
                            <button type="button" class="btn btn-outline-secondary" id="togglePassword">
                                <i class="bi bi-eye" id="togglePasswordIcon"></i>
                            </button>
                        </div>
                        <div class="password-requirements">
                            Password must contain: uppercase letter, lowercase letter, number, and special character (@$!%*?&)
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Confirm Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                                   required autocomplete="off" placeholder="Confirm your password">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="verification_code" class="form-label">Admin Verification Code</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-shield-lock-fill"></i></span>
                            <input type="password" class="form-control" id="verification_code" name="verification_code" 
                                   required autocomplete="off" placeholder="Enter admin verification code">
                        </div>
                        <div class="verification-info">
                            Contact your system administrator for the verification code
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-person-plus-fill me-2"></i>Register Admin Account
                    </button>
                </form>
                
                <div class="login-link">
                    <p>Already have an account? <a href="login.php">Login here</a></p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Error Toast -->
    <div class="toast-container position-fixed bottom-0 end-0 p-3">
        <div id="errorToast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="toast-header bg-danger text-white">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <strong class="me-auto">Registration Error</strong>
                <small>Just now</small>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
            <div class="toast-body">
                <div id="errorList" class="error-messages" style="max-height: 150px; overflow-y: auto;"></div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Password toggle functionality
        document.getElementById('togglePassword').addEventListener('click', function() {
            const passwordField = document.getElementById('password');
            const toggleIcon = document.getElementById('togglePasswordIcon');
            
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                toggleIcon.className = 'bi bi-eye-slash';
            } else {
                passwordField.type = 'password';
                toggleIcon.className = 'bi bi-eye';
            }
        });
        
        // Function to display errors in toast
        function showErrorToast(errors) {
            const errorList = document.getElementById('errorList');
            errorList.innerHTML = '';
            
            Object.entries(errors).forEach(([field, message]) => {
                const errorItem = document.createElement('div');
                errorItem.className = 'error-item';
                
                // Format field name for display
                let fieldName = field
                    .replace(/_/g, ' ')
                    .replace(/\b\w/g, c => c.toUpperCase());
                
                switch (field) {
                    case 'username_exists':
                        fieldName = 'Username';
                        break;
                    case 'email_exists':
                        fieldName = 'Email';
                        break;
                    case 'password_match_error':
                        fieldName = 'Password Match';
                        break;
                    case 'password_too_short':
                        fieldName = 'Password Length';
                        break;
                    case 'password_complexity':
                        fieldName = 'Password Strength';
                        break;
                    case 'invalid_verification_code':
                        fieldName = 'Verification Code';
                        break;
                }
                
                errorItem.innerHTML = `<span class="error-field">${fieldName}:</span> ${message}`;
                errorList.appendChild(errorItem);
            });
            
            // Show toast
            const toast = new bootstrap.Toast(document.getElementById('errorToast'), {
                autohide: true,
                delay: 8000
            });
            toast.show();
        }
        
        // Real-time username validation
        document.getElementById('username').addEventListener('input', function() {
            const username = this.value;
            const hint = document.querySelector('.username-hint');
            
            // Check for valid characters
            const validChars = /^[a-zA-Z0-9_\s\-\.]*$/;
            const startsOrEndsWithSpace = /^\s|\s$/;
            const consecutiveSpaces = /\s{2,}/;
            
            if (username === '') {
                hint.style.color = '#6c757d';
                hint.innerHTML = '3-50 characters, letters, numbers, spaces, underscores, hyphens, and periods allowed';
            } else if (!validChars.test(username)) {
                hint.style.color = '#dc3545';
                hint.innerHTML = '❌ Invalid characters detected. Only letters, numbers, spaces, underscores, hyphens, and periods allowed';
            } else if (startsOrEndsWithSpace.test(username)) {
                hint.style.color = '#dc3545';
                hint.innerHTML = '❌ Username cannot start or end with spaces';
            } else if (consecutiveSpaces.test(username)) {
                hint.style.color = '#dc3545';
                hint.innerHTML = '❌ Username cannot contain consecutive spaces';
            } else if (username.length < 3) {
                hint.style.color = '#ffc107';
                hint.innerHTML = '⚠️ Username must be at least 3 characters';
            } else if (username.length > 50) {
                hint.style.color = '#dc3545';
                hint.innerHTML = '❌ Username cannot exceed 50 characters';
            } else {
                hint.style.color = '#198754';
                hint.innerHTML = '✓ Username format is valid';
            }
        });
        
        // Real-time password validation
        document.getElementById('password').addEventListener('input', function() {
            const password = this.value;
            const requirements = document.querySelector('.password-requirements');
            
            const hasUpper = /[A-Z]/.test(password);
            const hasLower = /[a-z]/.test(password);
            const hasNumber = /\d/.test(password);
            const hasSpecial = /[@$!%*?&]/.test(password);
            const hasLength = password.length >= 8;
            
            if (password === '') {
                requirements.style.color = '#6c757d';
                requirements.innerHTML = 'Password must contain: uppercase letter, lowercase letter, number, and special character (@$!%*?&)';
            } else if (hasUpper && hasLower && hasNumber && hasSpecial && hasLength) {
                requirements.style.color = '#198754';
                requirements.innerHTML = '✓ Password meets all requirements';
            } else {
                requirements.style.color = '#dc3545';
                let missing = [];
                if (!hasLength) missing.push('8+ characters');
                if (!hasUpper) missing.push('uppercase letter');
                if (!hasLower) missing.push('lowercase letter');
                if (!hasNumber) missing.push('number');
                if (!hasSpecial) missing.push('special character');
                requirements.innerHTML = `Missing: ${missing.join(', ')}`;
            }
        });
        
        // Real-time password match validation
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;
            
            if (confirmPassword === '') {
                this.style.borderColor = '';
            } else if (password === confirmPassword) {
                this.style.borderColor = '#198754';
            } else {
                this.style.borderColor = '#dc3545';
            }
        });
        
        // Client-side validation with updated username validation
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            const username = document.getElementById('username').value.trim();
            const email = document.getElementById('email').value.trim();
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const verificationCode = document.getElementById('verification_code').value;
            
            const clientErrors = {};
            
            // Username validation - updated to allow spaces
            if (username === '') {
                clientErrors.username = 'Username is required';
            } else if (username.length < 3) {
                clientErrors.username = 'Username must be at least 3 characters';
            } else if (username.length > 50) {
                clientErrors.username = 'Username cannot exceed 50 characters';
            } else if (!/^[a-zA-Z0-9_\s\-\.]+$/.test(username)) {
                clientErrors.username = 'Username can only contain letters, numbers, spaces, underscores, hyphens, and periods';
            } else if (/^\s|\s$/.test(username)) {
                clientErrors.username = 'Username cannot start or end with spaces';
            } else if (/\s{2,}/.test(username)) {
                clientErrors.username = 'Username cannot contain consecutive spaces';
            }
            
            // Email validation
            if (email === '') {
                clientErrors.email = 'Email is required';
            } else if (!/^\S+@\S+\.\S+$/.test(email)) {
                clientErrors.email = 'Please enter a valid email address';
            }
            
            // Password validation
            if (password === '') {
                clientErrors.password = 'Password is required';
            } else if (password.length < 8) {
                clientErrors.password_too_short = 'Password must be at least 8 characters';
            } else if (!/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/.test(password)) {
                clientErrors.password_complexity = 'Password must contain uppercase, lowercase, number, and special character';
            }
            
            // Password match validation
            if (password !== confirmPassword) {
                clientErrors.password_match_error = 'Passwords do not match';
            }
            
            // Verification code validation
            if (verificationCode === '') {
                clientErrors.verification_code = 'Verification code is required';
            }
            
            if (Object.keys(clientErrors).length > 0) {
                e.preventDefault();
                showErrorToast(clientErrors);
            }
        });
        
        // Show server-side errors in toast if any
        <?php if(!empty($errors)): ?>
        document.addEventListener('DOMContentLoaded', function() {
            showErrorToast(<?php echo $errors_json; ?>);
        });
        <?php endif; ?>
        
        // Prevent multiple consecutive spaces while typing
        document.getElementById('username').addEventListener('keypress', function(e) {
            const currentValue = this.value;
            const cursorPosition = this.selectionStart;
            
            // If user is typing a space and the previous character is also a space, prevent it
            if (e.key === ' ' && cursorPosition > 0 && currentValue[cursorPosition - 1] === ' ') {
                e.preventDefault();
            }
        });
        
        // Clean up username on blur (remove extra spaces)
        document.getElementById('username').addEventListener('blur', function() {
            // Replace multiple spaces with single space and trim
            this.value = this.value.replace(/\s+/g, ' ').trim();
        });
    </script>
</body>
</html>