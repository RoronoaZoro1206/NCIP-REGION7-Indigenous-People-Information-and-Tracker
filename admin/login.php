<?php

session_start();

// Check if already logged in
if (isset($_SESSION["user_id"]) && isset($_SESSION["role"]) && strtoupper($_SESSION["role"]) === 'ADMIN') {
    header("Location: index.php");
    exit();
}

function getUserIP() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) return $_SERVER['HTTP_CLIENT_IP'];
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) return $_SERVER['HTTP_X_FORWARDED_FOR'];
    return $_SERVER['REMOTE_ADDR'];
}

// Initialize variables
$login_error = "";
$errors = [];
$pdo = null;

// Try to include the database configuration
try {
    require_once "config/database.php";
} catch (Exception $e) {
    // Fallback connection if include fails
    $host = "localhost";     
    $dbname = "Ncip_Project"; 
    $dbusername = "postgres"; 
    $dbpassword = "RaffyD964"; 

    try {
        $pdo = new PDO("pgsql:host=$host;dbname=$dbname", $dbusername, $dbpassword);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec("SET TIME ZONE INTERVAL '+08:00'");
    } catch (PDOException $dbError) {
        $login_error = "Database connection error. Please contact the administrator.";
        $errors['database'] = $login_error;
    }
}

// Include shared logger
try { require_once __DIR__ . '/../front end/logger.php'; } catch (Throwable $e) { /* ignore */ }

// Process login form
if ($_SERVER["REQUEST_METHOD"] == "POST" && $pdo !== null) {
    $username = trim($_POST["username"]);
    $password = $_POST["password"];
    
    try {
        // Include validator and models
        require_once "includes/admin-validator.php";
        require_once "includes/admin-models.php";
        
        // Use validator to check credentials
        $validator = new AdminLoginValidator($username, $password, $pdo);
        $validation_errors = $validator->validate_data();
        
        if (!empty($validation_errors)) {
            $errors = array_merge($errors, $validation_errors);
        } else {
            // Get user data (only active accounts)
            $user = $validator->get_user_data();
            
            if ($user) {
                // Find required fields
                $user_id_field = null;
                $username_field = null;
                $role_field = null;
                
                foreach ($user as $key => $value) {
                    if (strtoupper($key) === 'USER_ID') $user_id_field = $key;
                    if (strtoupper($key) === 'USER_USERNAME') $username_field = $key;
                    if (strtoupper($key) === 'USER_ROLE') $role_field = $key;
                }
                
                // Login successful
                $_SESSION["user_id"] = $user[$user_id_field];
                $_SESSION["username"] = $user[$username_field];
                $_SESSION["role"] = $user[$role_field];
                
                // Log the successful login (DB + file)
                $user_ip = getUserIP();
                if (function_exists('log_user_event')) {
                    log_user_event($pdo, 'LOGIN', 'Admin login successful', (int)$user[$user_id_field], $user_ip);
                }
                
                header("Location: index.php");
                exit();
            }
        }
        
        // Log failed login attempt with more detail
        if (!empty($errors)) {
            $user_ip = getUserIP();
            $error_type = isset($errors["account_deleted"]) ? 'LOGIN_ATTEMPT_DELETED' : 'LOGIN_ATTEMPT_FAILED';
            $error_detail = isset($errors["account_deleted"]) ? ('Login attempt on deleted account: ' . $username) : ('Failed login attempt for username: ' . $username);
            if (function_exists('log_user_event')) {
                // no user id on failure
                log_user_event($pdo, $error_type, $error_detail, null, $user_ip);
            }
        }
        
        // Display first error
        if (!empty($errors)) {
            $login_error = array_values($errors)[0];
        }
        
    } catch (PDOException $e) {
        $login_error = "Login failed: " . $e->getMessage();
        $errors['database'] = $login_error;
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
    <title>NCIP Admin - Login</title>
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
        .login-container {
            max-width: 400px;
            margin: 20px auto;
            width: 100%;
            padding: 0 20px;
            box-sizing: border-box;
        }
        .login-logo {
            text-align: center;
            margin-bottom: 20px;
        }
        .login-logo img {
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
        .register-link {
            text-align: center;
            margin-top: 15px;
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
        .deleted-account-alert {
            background-color: #f8d7da;
            border-color: #f5c2c7;
            color: #842029;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid;
        }
        .deleted-account-alert .alert-icon {
            font-size: 1.2em;
            margin-right: 8px;
        }



        /* Responsive adjustments */
        @media (max-height: 700px) {
            .login-container {
                margin: 10px auto;
            }
            .login-logo {
                margin-bottom: 15px;
            }
            .login-logo img {
                max-width: 100px;
            }
            .card-body {
                padding: 15px;
            }
        }

        @media (max-width: 480px) {
            .login-container {
                max-width: 350px;
                padding: 0 15px;
            }
        }
    </style>
</head>
<body>
    <div class="container login-container">
        <div class="login-logo">
            <img src="assets/img/ncip-logo.png" alt="NCIP Logo">
        </div>
        <div class="card">
            <div class="card-header text-center">
                NCIP Admin Login
            </div>
            <div class="card-body">
                <?php if($login_error && isset($errors["account_deleted"])): ?>
                <div class="deleted-account-alert">
                    <i class="bi bi-exclamation-triangle-fill alert-icon"></i>
                    <strong>Account Deleted:</strong><br>
                    <?php echo $login_error; ?>
                </div>
                <?php elseif($login_error): ?>
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    <?php echo $login_error; ?>
                </div>
                <?php endif; ?>
                
                <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" id="loginForm">
                    <div class="mb-3">
                        <label for="username" class="form-label">Username</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-person-fill"></i></span>
                            <input type="text" class="form-control" id="username" name="username" 
                                   value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>"
                                   required autocomplete="off">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
                            <input type="password" class="form-control" id="password" name="password" required autocomplete="off">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-box-arrow-in-right me-2"></i>Login
                    </button>
                </form>
                
                <div class="register-link">
                    <p>Need an account? <a href="register.php">Register here</a></p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Error Toast -->
    <div class="toast-container position-fixed bottom-0 end-0 p-3">
        <div id="errorToast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="toast-header bg-danger text-white">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <strong class="me-auto">Error Alert</strong>
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
                
                if (field === 'invalid_credentials') fieldName = 'Login';
                if (field === 'password_match_error') fieldName = 'Password Match';
                if (field === 'not_admin') fieldName = 'Access';
                if (field === 'account_deleted') fieldName = 'Account Status';
                if (field === 'username_deleted') fieldName = 'Username';
                
                errorItem.innerHTML = `<span class="error-field">${fieldName}:</span> ${message}`;
                errorList.appendChild(errorItem);
            });
            
            // Show toast
            const toast = new bootstrap.Toast(document.getElementById('errorToast'), {
                autohide: true,
                delay: 8000 // Longer delay for deleted account messages
            });
            toast.show();
        }
        
        // Client-side validation
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value;
            const clientErrors = {};
            
            if (username === '') {
                clientErrors.username = 'Username is required';
            }
            
            if (password === '') {
                clientErrors.password = 'Password is required';
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