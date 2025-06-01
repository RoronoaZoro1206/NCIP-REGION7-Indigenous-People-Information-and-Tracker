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
    $dbpassword = "!*Lawrence1206"; 

    try {
        $pdo = new PDO("pgsql:host=$host;dbname=$dbname", $dbusername, $dbpassword);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec("SET TIME ZONE INTERVAL '+08:00'");
    } catch (PDOException $dbError) {
        $login_error = "Database connection error. Please contact the administrator.";
        $errors['database'] = $login_error;
    }
}

// Process login form
if ($_SERVER["REQUEST_METHOD"] == "POST" && $pdo !== null) {
    $username = trim($_POST["username"]);
    $password = $_POST["password"];
    
    try {
        // Include validator
        require_once "includes/admin-validator.php";
        require_once "includes/admin-models.php";
        
        // Get user directly from database
        $user = get_admin_by_username($pdo, $username);
        
        if (!$user) {
            $errors["invalid_credentials"] = "Invalid username or password";
        } else {
            // Find password field regardless of case
            $pwd_field = null;
            $role_field = null;
            $user_id_field = null;
            $username_field = null;
            
            foreach ($user as $key => $value) {
                if (strtoupper($key) === 'USER_PWD') $pwd_field = $key;
                if (strtoupper($key) === 'USER_ROLE') $role_field = $key;
                if (strtoupper($key) === 'USER_ID') $user_id_field = $key;
                if (strtoupper($key) === 'USER_USERNAME') $username_field = $key;
            }
            
            // Verify password
            if ($pwd_field && password_verify($password, $user[$pwd_field])) {
                // Check role
                if ($role_field && strtoupper($user[$role_field]) === 'ADMIN') {
                    // Login successful
                    $_SESSION["user_id"] = $user[$user_id_field];
                    $_SESSION["username"] = $user[$username_field];
                    $_SESSION["role"] = $user[$role_field];
                    
                    // Log the successful login
                    $user_ip = getUserIP();

                    $log_query = "INSERT INTO USER_LOGS (USER_ID, LOG_TYPE, LOG_DETAILS, LOG_TIMESTAMP, LOG_IP_ADDRESS) 
                                VALUES (:user_id, 'LOGIN', 'Admin login successful', CURRENT_TIMESTAMP, :ip)";
                    $log_stmt = $pdo->prepare($log_query);
                    $log_stmt->bindParam(":user_id", $user[$user_id_field]);
                    $log_stmt->bindParam(":ip", $user_ip);
                    $log_stmt->execute();
                    
                    header("Location: index.php");
                    exit();
                } else {
                    $errors["not_admin"] = "You do not have administrator privileges";
                }
            } else {
                $errors["invalid_credentials"] = "Invalid username or password";
            }
        }
        
        // Log failed login attempt
        if (isset($errors["invalid_credentials"])) {
            $log_query = "INSERT INTO USER_LOGS (LOG_TYPE, LOG_DETAILS, LOG_TIMESTAMP) 
                         VALUES ('LOGIN_ATTEMPT', 'Failed login attempt for username: " . $username . "', CURRENT_TIMESTAMP)";
            $pdo->exec($log_query);
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
        }
        .login-container {
            max-width: 400px;
            margin: 100px auto;
        }
        .login-logo {
            text-align: center;
            margin-bottom: 30px;
        }
        .login-logo img {
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
        .register-link {
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
    <div class="container login-container">
        <div class="login-logo">
            <img src="assets/img/ncip-logo.png" alt="NCIP Logo">
        </div>
        <div class="card">
            <div class="card-header text-center">
                NCIP Admin Login
            </div>
            <div class="card-body">
                <?php if($login_error): ?>
                <div class="alert alert-danger"><?php echo $login_error; ?></div>
                <?php endif; ?>
                
                <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" id="loginForm">
                    <div class="mb-3">
                        <label for="username" class="form-label">Username</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-person-fill"></i></span>
                            <input type="text" class="form-control" id="username" name="username" required autocomplete="off">
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