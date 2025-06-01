<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Basic authentication check
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// Make sure user has admin privileges
$is_admin = isset($_SESSION['user_role']) && strtoupper(trim($_SESSION['user_role'])) === 'ADMIN';
if (!$is_admin) {
    $_SESSION['message'] = "You don't have permission to access this page.";
    header("Location: ../index.php");
    exit();
}

// Include necessary files
require_once '../config/database.php';
require_once '../includes/admin-models.php';

// Initialize variables
$username = $email = $role = 'USER';
$timezone = 'Asia/Manila';
$errors = [];
$success = false;

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $role = strtoupper(trim($_POST['role'] ?? 'USER'));
    $timezone = $_POST['timezone'] ?? 'Asia/Manila';
    $verification_code = $_POST['verification_code'] ?? '';

    // Enhanced validation based on admin-validator.php logic
    try {
        // Username validation - enhanced with admin-validator logic
        if (empty($username)) {
            $errors['username'] = 'Username is required';
        } elseif (strlen($username) < 3) {
            $errors['username'] = 'Username must be at least 3 characters';
        } elseif (strlen($username) > 50) {
            $errors['username'] = 'Username cannot exceed 50 characters';
        } elseif (!preg_match('/^[a-zA-Z0-9_\s\-\.]+$/', $username)) {
            $errors['username'] = 'Username can only contain letters, numbers, spaces, underscores, hyphens, and periods';
        } elseif (preg_match('/^\s|\s$/', $username)) {
            $errors['username'] = 'Username cannot start or end with spaces';
        } elseif (preg_match('/\s{2,}/', $username)) {
            $errors['username'] = 'Username cannot contain consecutive spaces';
        } elseif (username_exists($pdo, $username)) {
            $errors['username'] = 'Username already exists';
        }

        // Email validation - enhanced with admin-validator logic
        if (empty($email)) {
            $errors['email'] = 'Email is required';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Please enter a valid email address';
        } elseif (strlen($email) > 100) {
            $errors['email'] = 'Email cannot exceed 100 characters';
        } elseif (email_exists($pdo, $email)) {
            $errors['email'] = 'Email already exists';
        }

        // Password validation - enhanced with admin-validator logic
        if (empty($password)) {
            $errors['password'] = 'Password is required';
        } elseif (strlen($password) < 8) {
            $errors['password'] = 'Password must be at least 8 characters';
        } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/', $password)) {
            $errors['password'] = 'Password must contain at least one uppercase letter, one lowercase letter, one number, and one special character';
        }

        // Confirm password validation
        if ($password !== $confirm_password) {
            $errors['confirm_password'] = 'Passwords do not match';
        }

        // Role validation
        if (!in_array($role, ['ADMIN', 'USER'])) {
            $errors['role'] = 'Invalid role selected';
        }

        // Admin verification code validation (only for ADMIN role)
        if ($role === 'ADMIN') {
            if (empty($verification_code)) {
                $errors['verification_code'] = 'Admin verification code is required';
            } elseif ($verification_code !== 'NcipCebuConnect#2025') {
                $errors['verification_code'] = 'Invalid admin verification code';
            }
        }

        // Timezone validation
        if (!in_array($timezone, DateTimeZone::listIdentifiers())) {
            $errors['timezone'] = 'Invalid timezone selected';
        }

        // If no errors, create the user using admin-models.php logic
        if (empty($errors)) {
            if ($role === 'ADMIN') {
                // Use create_admin function for admin users
                $user_id = create_admin($pdo, $username, $email, $password);
            } else {
                // Use similar logic for regular users
                $timezone_obj = new DateTimeZone($timezone);
                $now = new DateTime('now', $timezone_obj);
                $created = $now->format('Y-m-d H:i:s');
                $timezone_name = $timezone_obj->getName();

                // Clean the username by trimming and normalizing spaces
                $clean_username = preg_replace('/\s+/', ' ', trim($username));

                $query = "INSERT INTO USERS (USER_USERNAME, USER_EMAIL, USER_PWD, USER_ROLE, USER_CREATED, USER_TIMEZONE, USER_PROFILE_PICTURE) 
                          VALUES (:username, :email, :password, :role, :created, :timezone, 'default.png')";
                
                $statement = $pdo->prepare($query);

                $options = ['cost' => 12];
                $hashed_password = password_hash($password, PASSWORD_BCRYPT, $options);

                $statement->bindParam(":username", $clean_username);
                $statement->bindParam(":email", $email);
                $statement->bindParam(":password", $hashed_password);
                $statement->bindParam(":role", $role);
                $statement->bindParam(":created", $created);
                $statement->bindParam(":timezone", $timezone_name);

                $statement->execute();
                $user_id = $pdo->lastInsertId();
            }

            // Log the user creation activity
            try {
                $activity_details = "Created new user account: " . $username . 
                                  " (ID: " . $user_id . ", Email: " . $email . ", Role: " . $role . ")";
                
                $log_query = "INSERT INTO USER_LOGS (USER_ID, LOG_TYPE, LOG_DETAILS, LOG_TIMESTAMP) 
                             VALUES (:user_id, 'CREATE_USER', :details, CURRENT_TIMESTAMP)";
                $log_stmt = $pdo->prepare($log_query);
                $log_stmt->bindParam(":user_id", $_SESSION["user_id"]);
                $log_stmt->bindParam(":details", $activity_details);
                $log_stmt->execute();
            } catch (PDOException $log_error) {
                // Log error but don't fail the user creation
                error_log("Failed to log user creation activity: " . $log_error->getMessage());
            }

            // Success message and redirect
            $_SESSION['message'] = "User created successfully.";
            header("Location: index.php");
            exit();
        }

    } catch (PDOException $e) {
        $errors['database'] = "Database error: " . $e->getMessage();
        error_log("User creation database error: " . $e->getMessage());
    } catch (Exception $e) {
        $errors['system'] = "System error: " . $e->getMessage();
        error_log("User creation system error: " . $e->getMessage());
    }
}

// Get list of timezones for dropdown
$timezones = DateTimeZone::listIdentifiers();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add User - NCIP Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        .main-content {
            margin-left: 250px;
            padding: 20px;
        }
        .form-label {
            font-weight: 500;
        }
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            margin-bottom: 20px;
        }
        .card-header {
            background-color: #f8f9fa;
            border-bottom: 1px solid rgba(0,0,0,.125);
        }
        .verification-info {
            background-color: #e3f2fd;
            border: 1px solid #2196f3;
            border-radius: 5px;
            padding: 15px;
            margin-top: 10px;
        }
        .password-requirements {
            font-size: 0.875rem;
            color: #6c757d;
            margin-top: 5px;
        }
        .password-requirements ul {
            margin: 5px 0 0 20px;
            padding: 0;
        }
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <?php include '../includes/sidebar.php'; ?>

    <!-- Main content -->
    <div class="main-content">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="../index.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="index.php">Users</a></li>
                <li class="breadcrumb-item active" aria-current="page">Add User</li>
            </ol>
        </nav>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h2"><i class="bi bi-person-plus me-2"></i>Add New User</h1>
            <a href="index.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Back to Users
            </a>
        </div>

        <!-- Display errors -->
        <?php if (!empty($errors)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <h6><i class="bi bi-exclamation-triangle-fill me-2"></i>Please correct the following errors:</h6>
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-person-badge me-2"></i>User Information</h5>
            </div>
            <div class="card-body">
                <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post" autocomplete="off">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="username" class="form-label">Username <span class="text-danger">*</span></label>
                            <input type="text" class="form-control <?php echo isset($errors['username']) ? 'is-invalid' : ''; ?>" 
                                id="username" name="username" value="<?php echo htmlspecialchars($username); ?>" required autocomplete="off">
                            <div class="form-text">3-50 characters. Letters, numbers, spaces, underscores, hyphens, and periods allowed.</div>
                            <?php if (isset($errors['username'])): ?>
                                <div class="invalid-feedback"><?php echo htmlspecialchars($errors['username']); ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control <?php echo isset($errors['email']) ? 'is-invalid' : ''; ?>" 
                                id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required autocomplete="off">
                            <div class="form-text">Must be a valid email address.</div>
                            <?php if (isset($errors['email'])): ?>
                                <div class="invalid-feedback"><?php echo htmlspecialchars($errors['email']); ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="password" class="form-label">Password <span class="text-danger">*</span></label>
                            <input type="password" class="form-control <?php echo isset($errors['password']) ? 'is-invalid' : ''; ?>" 
                                id="password" name="password" required autocomplete="new-password">
                            <div class="password-requirements">
                                <strong>Password requirements:</strong>
                                <ul>
                                    <li>At least 8 characters long</li>
                                    <li>At least one uppercase letter (A-Z)</li>
                                    <li>At least one lowercase letter (a-z)</li>
                                    <li>At least one number (0-9)</li>
                                    <li>At least one special character (@$!%*?&)</li>
                                </ul>
                            </div>
                            <?php if (isset($errors['password'])): ?>
                                <div class="invalid-feedback"><?php echo htmlspecialchars($errors['password']); ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label for="confirm_password" class="form-label">Confirm Password <span class="text-danger">*</span></label>
                            <input type="password" class="form-control <?php echo isset($errors['confirm_password']) ? 'is-invalid' : ''; ?>" 
                                id="confirm_password" name="confirm_password" required autocomplete="new-password">
                            <div class="form-text">Must match the password above.</div>
                            <?php if (isset($errors['confirm_password'])): ?>
                                <div class="invalid-feedback"><?php echo htmlspecialchars($errors['confirm_password']); ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="role" class="form-label">Role <span class="text-danger">*</span></label>
                            <select class="form-select <?php echo isset($errors['role']) ? 'is-invalid' : ''; ?>" 
                                id="role" name="role" onchange="toggleVerificationCode()">
                                <option value="USER" <?php if ($role === 'USER') echo 'selected'; ?>>User</option>
                                <option value="ADMIN" <?php if ($role === 'ADMIN') echo 'selected'; ?>>Administrator</option>
                            </select>
                            <?php if (isset($errors['role'])): ?>
                                <div class="invalid-feedback"><?php echo htmlspecialchars($errors['role']); ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label for="timezone" class="form-label">Timezone</label>
                            <select class="form-select" id="timezone" name="timezone">
                                <?php foreach ($timezones as $tz): ?>
                                    <option value="<?php echo htmlspecialchars($tz); ?>" 
                                        <?php if ($tz === $timezone) echo 'selected'; ?>>
                                        <?php echo htmlspecialchars($tz); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Admin Verification Code Section -->
                    <div id="admin-verification" class="row mb-3" style="<?php echo $role !== 'ADMIN' ? 'display: none;' : ''; ?>">
                        <div class="col-12">
                            <label for="verification_code" class="form-label">Admin Verification Code <span class="text-danger">*</span></label>
                            <input type="password" class="form-control <?php echo isset($errors['verification_code']) ? 'is-invalid' : ''; ?>" 
                                id="verification_code" name="verification_code" value="<?php echo htmlspecialchars($_POST['verification_code'] ?? ''); ?>" autocomplete="off">
                            <div class="verification-info">
                                <i class="bi bi-info-circle me-2"></i>
                                <strong>Admin Access Required:</strong> A special verification code is required to create administrator accounts. 
                                This code ensures only authorized personnel can create admin users.
                            </div>
                            <?php if (isset($errors['verification_code'])): ?>
                                <div class="invalid-feedback"><?php echo htmlspecialchars($errors['verification_code']); ?></div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-person-plus"></i> Create User
                        </button>
                        <a href="index.php" class="btn btn-secondary ms-2">
                            <i class="bi bi-x-circle"></i> Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Move Asia/Manila to the top of timezone dropdown
        const timezoneSelect = document.getElementById('timezone');
        const asiaManila = Array.from(timezoneSelect.options).find(option => option.value === 'Asia/Manila');
        
        if (asiaManila) {
            timezoneSelect.insertBefore(asiaManila, timezoneSelect.firstChild);
        }

        // Password strength indicator
        const passwordInput = document.getElementById('password');
        const confirmPasswordInput = document.getElementById('confirm_password');

        passwordInput.addEventListener('input', function() {
            validatePasswordStrength(this.value);
        });

        confirmPasswordInput.addEventListener('input', function() {
            validatePasswordMatch();
        });

        function validatePasswordStrength(password) {
            const requirements = {
                length: password.length >= 8,
                uppercase: /[A-Z]/.test(password),
                lowercase: /[a-z]/.test(password),
                number: /\d/.test(password),
                special: /[@$!%*?&]/.test(password)
            };

            // Update visual feedback
            const passwordField = document.getElementById('password');
            const allMet = Object.values(requirements).every(req => req);
            
            if (password.length > 0) {
                if (allMet) {
                    passwordField.classList.remove('is-invalid');
                    passwordField.classList.add('is-valid');
                } else {
                    passwordField.classList.remove('is-valid');
                    passwordField.classList.add('is-invalid');
                }
            } else {
                passwordField.classList.remove('is-valid', 'is-invalid');
            }
        }

        function validatePasswordMatch() {
            const password = passwordInput.value;
            const confirmPassword = confirmPasswordInput.value;
            
            if (confirmPassword.length > 0) {
                if (password === confirmPassword) {
                    confirmPasswordInput.classList.remove('is-invalid');
                    confirmPasswordInput.classList.add('is-valid');
                } else {
                    confirmPasswordInput.classList.remove('is-valid');
                    confirmPasswordInput.classList.add('is-invalid');
                }
            } else {
                confirmPasswordInput.classList.remove('is-valid', 'is-invalid');
            }
        }
    });

    function toggleVerificationCode() {
        const roleSelect = document.getElementById('role');
        const verificationDiv = document.getElementById('admin-verification');
        const verificationInput = document.getElementById('verification_code');
        
        if (roleSelect.value === 'ADMIN') {
            verificationDiv.style.display = 'block';
            verificationInput.required = true;
        } else {
            verificationDiv.style.display = 'none';
            verificationInput.required = false;
            verificationInput.value = '';
        }
    }
    </script>
</body>
</html>