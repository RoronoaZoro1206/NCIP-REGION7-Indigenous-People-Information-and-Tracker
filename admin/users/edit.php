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

// Check if ID parameter exists
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['message'] = "Invalid user ID.";
    header("Location: index.php");
    exit();
}

$user_id = (int) $_GET['id'];
$user = [];
$errors = [];
$success = false;

// Include necessary files
require_once '../config/database.php';
require_once '../includes/admin-models.php';

// Get list of timezones for dropdown
$timezones = DateTimeZone::listIdentifiers();

try {
    $stmt = $pdo->prepare("SELECT * FROM USERS WHERE USER_ID = ? AND USER_DELETED_AT IS NULL");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        $_SESSION['message'] = "User not found or has been deleted.";
        header("Location: index.php");
        exit();
    }
} catch (PDOException $e) {
    $errors['database'] = "Database error: " . $e->getMessage();
    error_log("Edit user database error: " . $e->getMessage());
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? $user['user_username']);
    $email = trim($_POST['email'] ?? $user['user_email']);
    $role = strtoupper(trim($_POST['role'] ?? $user['user_role']));
    $timezone = $_POST['timezone'] ?? $user['user_timezone'];
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $verification_code = $_POST['verification_code'] ?? '';

    try {
        // Enhanced validation based on admin-validator.php logic
        
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
        } else {
            // Check if username exists for another user
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM USERS WHERE LOWER(TRIM(USER_USERNAME)) = LOWER(TRIM(?)) AND USER_ID != ? AND USER_DELETED_AT IS NULL");
            $stmt->execute([$username, $user_id]);
            if ($stmt->fetchColumn() > 0) {
                $errors['username'] = 'Username already exists';
            }
        }

        // Email validation - enhanced with admin-validator logic
        if (empty($email)) {
            $errors['email'] = 'Email is required';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Please enter a valid email address';
        } elseif (strlen($email) > 100) {
            $errors['email'] = 'Email cannot exceed 100 characters';
        } else {
            // Check if email exists for another user
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM USERS WHERE LOWER(USER_EMAIL) = LOWER(?) AND USER_ID != ? AND USER_DELETED_AT IS NULL");
            $stmt->execute([$email, $user_id]);
            if ($stmt->fetchColumn() > 0) {
                $errors['email'] = 'Email already exists';
            }
        }

        // Role validation
        if (!in_array($role, ['ADMIN', 'USER'])) {
            $errors['role'] = 'Invalid role selected';
        }

        // Role change validation - if changing TO admin, require verification code
        $role_changed_to_admin = false;
        if ($role === 'ADMIN' && strtoupper($user['user_role']) !== 'ADMIN') {
            $role_changed_to_admin = true;
            if (empty($verification_code)) {
                $errors['verification_code'] = 'Admin verification code is required when promoting to admin';
            } elseif ($verification_code !== 'NcipCebuConnect#2025') {
                $errors['verification_code'] = 'Invalid admin verification code';
            }
        }

        // Password validation (if changing password) - enhanced with admin-validator logic
        $change_password = false;
        if (!empty($password) || !empty($confirm_password)) {
            $change_password = true;
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
        }

        // Timezone validation
        if (!in_array($timezone, DateTimeZone::listIdentifiers())) {
            $errors['timezone'] = 'Invalid timezone selected';
        }

        // If no errors, update the user using admin-models.php logic
        if (empty($errors)) {
            // Clean the username by trimming and normalizing spaces (from admin-models.php)
            $clean_username = preg_replace('/\s+/', ' ', trim($username));

            if ($change_password) {
                // Update with password change
                $options = ['cost' => 12];
                $hashed_password = password_hash($password, PASSWORD_BCRYPT, $options);
                $stmt = $pdo->prepare("UPDATE USERS SET USER_USERNAME = ?, USER_EMAIL = ?, USER_ROLE = ?, USER_TIMEZONE = ?, USER_PWD = ? WHERE USER_ID = ?");
                $stmt->execute([$clean_username, $email, $role, $timezone, $hashed_password, $user_id]);
            } else {
                // Update without password change
                $stmt = $pdo->prepare("UPDATE USERS SET USER_USERNAME = ?, USER_EMAIL = ?, USER_ROLE = ?, USER_TIMEZONE = ? WHERE USER_ID = ?");
                $stmt->execute([$clean_username, $email, $role, $timezone, $user_id]);
            }

            // Log the user update activity
            try {
                $changes = [];
                if ($clean_username !== $user['user_username']) $changes[] = "Username: {$user['user_username']} → {$clean_username}";
                if ($email !== $user['user_email']) $changes[] = "Email: {$user['user_email']} → {$email}";
                if ($role !== $user['user_role']) $changes[] = "Role: {$user['user_role']} → {$role}";
                if ($timezone !== $user['user_timezone']) $changes[] = "Timezone: {$user['user_timezone']} → {$timezone}";
                if ($change_password) $changes[] = "Password changed";

                if (!empty($changes)) {
                    $activity_details = "Updated user account (ID: {$user_id}): " . implode(", ", $changes);
                    
                    $log_query = "INSERT INTO USER_LOGS (USER_ID, LOG_TYPE, LOG_DETAILS, LOG_TIMESTAMP) 
                                 VALUES (:user_id, 'UPDATE_USER', :details, CURRENT_TIMESTAMP)";
                    $log_stmt = $pdo->prepare($log_query);
                    $log_stmt->bindParam(":user_id", $_SESSION["user_id"]);
                    $log_stmt->bindParam(":details", $activity_details);
                    $log_stmt->execute();
                }
            } catch (PDOException $log_error) {
                // Log error but don't fail the user update
                error_log("Failed to log user update activity: " . $log_error->getMessage());
            }

            $_SESSION['message'] = "User updated successfully.";
            header("Location: view.php?id=" . $user_id);
            exit();
        } else {
            // Repopulate $user for form display
            $user['user_username'] = $username;
            $user['user_email'] = $email;
            $user['user_role'] = $role;
            $user['user_timezone'] = $timezone;
        }

    } catch (PDOException $e) {
        $errors['database'] = "Database error: " . $e->getMessage();
        error_log("User update database error: " . $e->getMessage());
    } catch (Exception $e) {
        $errors['system'] = "System error: " . $e->getMessage();
        error_log("User update system error: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit User - NCIP Admin</title>
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
        .role-change-warning {
            background-color: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 5px;
            padding: 15px;
            margin-top: 10px;
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
                <li class="breadcrumb-item"><a href="view.php?id=<?= $user_id ?>"><?= htmlspecialchars($user['user_username'] ?? 'User') ?></a></li>
                <li class="breadcrumb-item active" aria-current="page">Edit</li>
            </ol>
        </nav>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h2"><i class="bi bi-person-gear me-2"></i>Edit User</h1>
            <a href="view.php?id=<?= $user_id ?>" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Back to User Details
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
                <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']) . '?id=' . $user_id; ?>" method="post" autocomplete="off">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="username" class="form-label">Username <span class="text-danger">*</span></label>
                            <input type="text" class="form-control <?php echo isset($errors['username']) ? 'is-invalid' : ''; ?>"
                                id="username" name="username" value="<?php echo htmlspecialchars($user['user_username']); ?>" required autocomplete="off">
                            <div class="form-text">3-50 characters. Letters, numbers, spaces, underscores, hyphens, and periods allowed.</div>
                            <?php if (isset($errors['username'])): ?>
                                <div class="invalid-feedback"><?php echo htmlspecialchars($errors['username']); ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control <?php echo isset($errors['email']) ? 'is-invalid' : ''; ?>"
                                id="email" name="email" value="<?php echo htmlspecialchars($user['user_email']); ?>" required autocomplete="off">
                            <div class="form-text">Must be a valid email address.</div>
                            <?php if (isset($errors['email'])): ?>
                                <div class="invalid-feedback"><?php echo htmlspecialchars($errors['email']); ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="role" class="form-label">Role <span class="text-danger">*</span></label>
                            <select class="form-select <?php echo isset($errors['role']) ? 'is-invalid' : ''; ?>"
                                id="role" name="role" onchange="toggleVerificationCode()">
                                <option value="USER" <?php if ($user['user_role'] === 'USER') echo 'selected'; ?>>User</option>
                                <option value="ADMIN" <?php if ($user['user_role'] === 'ADMIN') echo 'selected'; ?>>Administrator</option>
                            </select>
                            <div class="role-change-warning" id="role-warning" style="display: none;">
                                <i class="bi bi-exclamation-triangle me-2"></i>
                                <strong>Warning:</strong> Promoting a user to Administrator requires verification and grants full system access.
                            </div>
                            <?php if (isset($errors['role'])): ?>
                                <div class="invalid-feedback"><?php echo htmlspecialchars($errors['role']); ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label for="timezone" class="form-label">Timezone</label>
                            <select class="form-select" id="timezone" name="timezone">
                                <?php foreach ($timezones as $tz): ?>
                                    <option value="<?php echo htmlspecialchars($tz); ?>"
                                        <?php if ($tz === $user['user_timezone']) echo 'selected'; ?>>
                                        <?php echo htmlspecialchars($tz); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Admin Verification Code Section -->
                    <div id="admin-verification" class="row mb-3" style="display: none;">
                        <div class="col-12">
                            <label for="verification_code" class="form-label">Admin Verification Code <span class="text-danger">*</span></label>
                            <input type="password" class="form-control <?php echo isset($errors['verification_code']) ? 'is-invalid' : ''; ?>" 
                                id="verification_code" name="verification_code" value="<?php echo htmlspecialchars($_POST['verification_code'] ?? ''); ?>" autocomplete="off">
                            <div class="verification-info">
                                <i class="bi bi-info-circle me-2"></i>
                                <strong>Admin Promotion Required:</strong> A special verification code is required to promote users to administrator status. 
                                This ensures only authorized personnel can grant admin privileges.
                            </div>
                            <?php if (isset($errors['verification_code'])): ?>
                                <div class="invalid-feedback"><?php echo htmlspecialchars($errors['verification_code']); ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="password" class="form-label">New Password</label>
                            <input type="password" class="form-control <?php echo isset($errors['password']) ? 'is-invalid' : ''; ?>"
                                id="password" name="password" autocomplete="new-password" placeholder="Leave blank to keep current password">
                            <div class="password-requirements" id="password-requirements" style="display: none;">
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
                            <label for="confirm_password" class="form-label">Confirm New Password</label>
                            <input type="password" class="form-control <?php echo isset($errors['confirm_password']) ? 'is-invalid' : ''; ?>"
                                id="confirm_password" name="confirm_password" autocomplete="new-password" placeholder="Leave blank to keep current password">
                            <div class="form-text">Must match the password above.</div>
                            <?php if (isset($errors['confirm_password'])): ?>
                                <div class="invalid-feedback"><?php echo htmlspecialchars($errors['confirm_password']); ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Save Changes
                        </button>
                        <a href="view.php?id=<?= $user_id ?>" class="btn btn-secondary ms-2">
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

        // Store original role for comparison
        const originalRole = '<?php echo $user['user_role']; ?>';

        // Password strength indicator
        const passwordInput = document.getElementById('password');
        const confirmPasswordInput = document.getElementById('confirm_password');
        const passwordRequirements = document.getElementById('password-requirements');

        passwordInput.addEventListener('focus', function() {
            if (this.value.length > 0 || document.activeElement === this) {
                passwordRequirements.style.display = 'block';
            }
        });

        passwordInput.addEventListener('input', function() {
            if (this.value.length > 0) {
                passwordRequirements.style.display = 'block';
                validatePasswordStrength(this.value);
            } else {
                passwordRequirements.style.display = 'none';
                this.classList.remove('is-valid', 'is-invalid');
            }
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

            const allMet = Object.values(requirements).every(req => req);
            
            if (allMet) {
                passwordInput.classList.remove('is-invalid');
                passwordInput.classList.add('is-valid');
            } else {
                passwordInput.classList.remove('is-valid');
                passwordInput.classList.add('is-invalid');
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

        // Initialize verification code visibility on page load
        toggleVerificationCode();
    });

    function toggleVerificationCode() {
        const roleSelect = document.getElementById('role');
        const verificationDiv = document.getElementById('admin-verification');
        const verificationInput = document.getElementById('verification_code');
        const roleWarning = document.getElementById('role-warning');
        const originalRole = '<?php echo $user['user_role']; ?>';
        
        // Show verification code if changing TO admin from non-admin
        if (roleSelect.value === 'ADMIN' && originalRole !== 'ADMIN') {
            verificationDiv.style.display = 'block';
            verificationInput.required = true;
            roleWarning.style.display = 'block';
        } else {
            verificationDiv.style.display = 'none';
            verificationInput.required = false;
            verificationInput.value = '';
            
            // Show warning if changing role to admin (even if already admin)
            if (roleSelect.value === 'ADMIN') {
                roleWarning.style.display = 'block';
            } else {
                roleWarning.style.display = 'none';
            }
        }
    }
    </script>
</body>
</html>