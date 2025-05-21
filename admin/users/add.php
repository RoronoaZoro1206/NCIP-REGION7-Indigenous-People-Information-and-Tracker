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

// Initialize variables
$username = $email = $role = '';
$errors = [];
$success = false;

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Include database connection
    require_once '../config/database.php';
    
    // Validate input
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $role = strtoupper(trim($_POST['role'] ?? 'USER'));
    $timezone = $_POST['timezone'] ?? 'Asia/Manila';
    
    // Username validation
    if (empty($username)) {
        $errors['username'] = "Username is required.";
    } elseif (strlen($username) < 3 || strlen($username) > 50) {
        $errors['username'] = "Username must be between 3 and 50 characters.";
    }

    // Email validation
    if (empty($email)) {
        $errors['email'] = "Email is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = "Invalid email format.";
    } else {
        // Check if email already exists
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM USERS WHERE USER_EMAIL = ?");
        $stmt->execute([$email]);
        if ($stmt->fetchColumn() > 0) {
            $errors['email'] = "Email already in use.";
        }
    }
    
    // Password validation
    if (empty($password)) {
        $errors['password'] = "Password is required.";
    } elseif (strlen($password) < 8) {
        $errors['password'] = "Password must be at least 8 characters.";
    }
    
    // Confirm password validation
    if ($password !== $confirm_password) {
        $errors['confirm_password'] = "Passwords do not match.";
    }
    
    // Role validation
    if (!in_array($role, ['ADMIN', 'USER'])) {
        $errors['role'] = "Invalid role selected.";
    }
    
    // If no errors, proceed with creating the user
    if (empty($errors)) {
        try {
            // Set timezone to Asia/Manila and get accurate current time
            $timezone_obj = new DateTimeZone($timezone);
            $now = new DateTime('now', $timezone_obj);
            $created = $now->format('Y-m-d H:i:s');
            $timezone_name = $timezone_obj->getName();

            // Hash the password
            $options = [
                'cost' => 12
            ];
            $hashed_password = password_hash($password, PASSWORD_BCRYPT, $options);
            
            // Insert the user
            $stmt = $pdo->prepare("INSERT INTO USERS (USER_USERNAME, USER_EMAIL, USER_PWD, USER_ROLE, USER_CREATED, USER_TIMEZONE) 
                                  VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$username, $email, $hashed_password, $role, $created, $timezone_name]);
            
            // Success message
            $_SESSION['message'] = "User created successfully.";
            header("Location: index.php");
            exit();
        } catch (PDOException $e) {
            $errors['database'] = "Database error: " . $e->getMessage();
        }
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
        /* Form styling */
        .form-label {
            font-weight: 500;
        }
        /* Responsive styling */
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
            <h1 class="h2">Add New User</h1>
            <a href="index.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Back to Users
            </a>
        </div>

        <?php if (!empty($errors['database'])): ?>
        <div class="alert alert-danger">
            <?php echo $errors['database']; ?>
        </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">User Information</h5>
            </div>
            <div class="card-body">
                <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" class="form-control <?php echo isset($errors['username']) ? 'is-invalid' : ''; ?>" 
                                id="username" name="username" value="<?php echo htmlspecialchars($username); ?>" required autocomplete="off">
                            <?php if (isset($errors['username'])): ?>
                                <div class="invalid-feedback"><?php echo $errors['username']; ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control <?php echo isset($errors['email']) ? 'is-invalid' : ''; ?>" 
                                id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required autocomplete="off">
                            <?php if (isset($errors['email'])): ?>
                                <div class="invalid-feedback"><?php echo $errors['email']; ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control <?php echo isset($errors['password']) ? 'is-invalid' : ''; ?>" 
                                id="password" name="password" required autocomplete="off">
                            <?php if (isset($errors['password'])): ?>
                                <div class="invalid-feedback"><?php echo $errors['password']; ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label for="confirm_password" class="form-label">Confirm Password</label>
                            <input type="password" class="form-control <?php echo isset($errors['confirm_password']) ? 'is-invalid' : ''; ?>" 
                                id="confirm_password" name="confirm_password" required autocomplete="off">
                            <?php if (isset($errors['confirm_password'])): ?>
                                <div class="invalid-feedback"><?php echo $errors['confirm_password']; ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="role" class="form-label">Role</label>
                            <select class="form-select <?php echo isset($errors['role']) ? 'is-invalid' : ''; ?>" 
                                id="role" name="role">
                                <option value="USER" <?php if ($role === 'USER') echo 'selected'; ?>>User</option>
                                <option value="ADMIN" <?php if ($role === 'ADMIN') echo 'selected'; ?>>Administrator</option>
                            </select>
                            <?php if (isset($errors['role'])): ?>
                                <div class="invalid-feedback"><?php echo $errors['role']; ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label for="timezone" class="form-label">Timezone</label>
                            <select class="form-select" id="timezone" name="timezone">
                                <?php foreach ($timezones as $tz): ?>
                                    <option value="<?php echo htmlspecialchars($tz); ?>" 
                                        <?php if ($tz === 'Asia/Manila') echo 'selected'; ?>>
                                        <?php echo htmlspecialchars($tz); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-person-plus"></i> Create User
                        </button>
                        <a href="index.php" class="btn btn-secondary ms-2">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Filter timezone dropdown when typing
        const timezoneSelect = document.getElementById('timezone');
        const asiaManila = Array.from(timezoneSelect.options).find(option => option.value === 'Asia/Manila');
        
        if (asiaManila) {
            // Move Asia/Manila to the top of the list
            timezoneSelect.insertBefore(asiaManila, timezoneSelect.firstChild);
        }
    });
    </script>
</body>
</html>