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

// The verification code that admin registrants must know
define('ADMIN_VERIFICATION_CODE', 'NcipCebuConnect#2025');

// Form data
$username = "";
$email = "";

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
    $username = trim($_POST["username"]);
    $email = trim($_POST["email"]);
    $password = $_POST["password"];
    $confirm_password = $_POST["confirm_password"];
    $verification_code = $_POST["verification_code"];
    
    try {
        // Include validator
        require_once "includes/admin-validator.php";
        
        // Create validator instance
        $validator = new AdminRegisterValidator($username, $email, $password, $confirm_password, $verification_code, $pdo);
        $errors = $validator->validate_data();
        
        if (empty($errors)) {
            // Include models
            require_once "includes/admin-models.php";
            
            // Create new admin user
            create_admin($pdo, $username, $email, $password);
            
            $register_success = "Registration successful! You can now login.";
            $username = "";
            $email = "";
        } else {
            // Display first error in alert
            $register_error = array_values($errors)[0];
        }
    } catch (PDOException $e) {
        $register_error = "Registration failed: " . $e->getMessage();
        $errors['database'] = $register_error;
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
        }
        .register-container {
            max-width: 500px;
            margin: 50px auto;
        }
        .register-logo {
            text-align: center;
            margin-bottom: 30px;
        }
        .register-logo img {
            max-width: 150px;
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
            padding: 15px;
        }
        .card-body {
            padding: 25px;
        }
        .btn-primary {
            background-color: #0d6efd;
            border-color: #0d6efd;
            width: 100%;
            padding: 10px;
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
            margin-top: 20px;
        }
        /* Error Toast Styling */
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
    </style>
</head>
<body>
    <div class="container register-container">
        <div class="register-logo">
            <img src="assets/img/ncip-logo.png" alt="NCIP Logo">
        </div>
        <div class="card">
            <div class="card-header text-center">
                NCIP Admin Registration
            </div>
            <div class="card-body">
                <?php if($register_error): ?>
                <div class="alert alert-danger"><?php echo $register_error; ?></div>
                <?php endif; ?>
                
                <?php if($register_success): ?>
                <div class="alert alert-success"><?php echo $register_success; ?></div>
                <?php endif; ?>
                
                <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" id="registerForm">
                    <div class="mb-3">
                        <label for="username" class="form-label">Username</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-person-fill"></i></span>
                            <input type="text" class="form-control" id="username" name="username" value="<?php echo $username; ?>" required autocomplete="off">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">Email Address</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-envelope-fill"></i></span>
                            <input type="email" class="form-control" id="email" name="email" value="<?php echo $email; ?>" required autocomplete="off">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
                            <input type="password" class="form-control" id="password" name="password" required autocomplete="off">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Confirm Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required autocomplete="off">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="verification_code" class="form-label">Admin Verification Code</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-shield-lock-fill"></i></span>
                            <input type="password" class="form-control" id="verification_code" name="verification_code" required autocomplete="off">
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
                delay: 5000
            });
            toast.show();
        }
        
        // Client-side validation
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            const username = document.getElementById('username').value.trim();
            const email = document.getElementById('email').value.trim();
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const verificationCode = document.getElementById('verification_code').value;
            
            const clientErrors = {};
            
            // Username validation
            if (username === '') {
                clientErrors.username = 'Username is required';
            } else if (username.length < 3) {
                clientErrors.username = 'Username must be at least 3 characters';
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
    </script>
</body>
</html>