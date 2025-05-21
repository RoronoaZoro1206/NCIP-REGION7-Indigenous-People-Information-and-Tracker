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

// Get list of timezones for dropdown
$timezones = DateTimeZone::listIdentifiers();

try {
    require_once '../config/database.php';
    $stmt = $pdo->prepare("SELECT * FROM USERS WHERE USER_ID = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        $_SESSION['message'] = "User not found.";
        header("Location: index.php");
        exit();
    }
} catch (PDOException $e) {
    $errors['database'] = "Database error: " . $e->getMessage();
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? $user['user_username']);
    $email = trim($_POST['email'] ?? $user['user_email']);
    $role = strtoupper(trim($_POST['role'] ?? $user['user_role']));
    $timezone = $_POST['timezone'] ?? $user['user_timezone'];
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

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
        // Check if email already exists for another user
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM USERS WHERE USER_EMAIL = ? AND USER_ID != ?");
        $stmt->execute([$email, $user_id]);
        if ($stmt->fetchColumn() > 0) {
            $errors['email'] = "Email already in use by another user.";
        }
    }

    // Role validation
    if (!in_array($role, ['ADMIN', 'USER'])) {
        $errors['role'] = "Invalid role selected.";
    }

    // Password validation (if changing password)
    $change_password = false;
    if (!empty($password) || !empty($confirm_password)) {
        $change_password = true;
        if (strlen($password) < 8) {
            $errors['password'] = "Password must be at least 8 characters.";
        }
        if ($password !== $confirm_password) {
            $errors['confirm_password'] = "Passwords do not match.";
        }
    }

    // If no errors, update the user
    if (empty($errors)) {
        try {
            if ($change_password) {
                $options = ['cost' => 12];
                $hashed_password = password_hash($password, PASSWORD_BCRYPT, $options);
                $stmt = $pdo->prepare("UPDATE USERS SET USER_USERNAME = ?, USER_EMAIL = ?, USER_ROLE = ?, USER_TIMEZONE = ?, USER_PWD = ? WHERE USER_ID = ?");
                $stmt->execute([$username, $email, $role, $timezone, $hashed_password, $user_id]);
            } else {
                $stmt = $pdo->prepare("UPDATE USERS SET USER_USERNAME = ?, USER_EMAIL = ?, USER_ROLE = ?, USER_TIMEZONE = ? WHERE USER_ID = ?");
                $stmt->execute([$username, $email, $role, $timezone, $user_id]);
            }

            $_SESSION['message'] = "User updated successfully.";
            header("Location: index.php");
            exit();
        } catch (PDOException $e) {
            $errors['database'] = "Database error: " . $e->getMessage();
        }
    } else {
        // Repopulate $user for form display
        $user['user_username'] = $username;
        $user['user_email'] = $email;
        $user['user_role'] = $role;
        $user['user_timezone'] = $timezone;
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
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/sidebar.php'; ?>

    <div class="main-content">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="../index.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="index.php">Users</a></li>
                <li class="breadcrumb-item active" aria-current="page">Edit User</li>
            </ol>
        </nav>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h2">Edit User</h1>
            <a href="view.php?id=<?= $user_id ?>" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Back to User
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
                <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']) . '?id=' . $user_id; ?>" method="post" autocomplete="off">
                    <div class="row mb-3">
                        <div class="col-md-6 mb-3 mb-md-0">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" class="form-control <?php echo isset($errors['username']) ? 'is-invalid' : ''; ?>"
                                id="username" name="username" value="<?php echo htmlspecialchars($user['user_username']); ?>" required>
                            <?php if (isset($errors['username'])): ?>
                                <div class="invalid-feedback"><?php echo $errors['username']; ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control <?php echo isset($errors['email']) ? 'is-invalid' : ''; ?>"
                                id="email" name="email" value="<?php echo htmlspecialchars($user['user_email']); ?>" required>
                            <?php if (isset($errors['email'])): ?>
                                <div class="invalid-feedback"><?php echo $errors['email']; ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6 mb-3 mb-md-0">
                            <label for="role" class="form-label">Role</label>
                            <select class="form-select <?php echo isset($errors['role']) ? 'is-invalid' : ''; ?>"
                                id="role" name="role">
                                <option value="USER" <?php if ($user['user_role'] === 'USER') echo 'selected'; ?>>User</option>
                                <option value="ADMIN" <?php if ($user['user_role'] === 'ADMIN') echo 'selected'; ?>>Administrator</option>
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
                                        <?php if ($tz === $user['user_timezone']) echo 'selected'; ?>>
                                        <?php echo htmlspecialchars($tz); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6 mb-3 mb-md-0">
                            <label for="password" class="form-label">New Password</label>
                            <input type="password" class="form-control <?php echo isset($errors['password']) ? 'is-invalid' : ''; ?>"
                                id="password" name="password" autocomplete="new-password" placeholder="Leave blank to keep current password">
                            <?php if (isset($errors['password'])): ?>
                                <div class="invalid-feedback"><?php echo $errors['password']; ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label for="confirm_password" class="form-label">Confirm New Password</label>
                            <input type="password" class="form-control <?php echo isset($errors['confirm_password']) ? 'is-invalid' : ''; ?>"
                                id="confirm_password" name="confirm_password" autocomplete="new-password" placeholder="Leave blank to keep current password">
                            <?php if (isset($errors['confirm_password'])): ?>
                                <div class="invalid-feedback"><?php echo $errors['confirm_password']; ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Save Changes
                        </button>
                        <a href="view.php?id=<?= $user_id ?>" class="btn btn-secondary ms-2">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Move Asia/Manila to the top of the timezone dropdown
        const timezoneSelect = document.getElementById('timezone');
        const asiaManila = Array.from(timezoneSelect.options).find(option => option.value === 'Asia/Manila');
        if (asiaManila) {
            timezoneSelect.insertBefore(asiaManila, timezoneSelect.firstChild);
        }
    });
    </script>
</body>
</html>