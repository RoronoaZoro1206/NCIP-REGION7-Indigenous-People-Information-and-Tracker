<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Check if user is logged in
if(!isset($_SESSION['user_id'])) {
    // Store the requested page to redirect after login
    $_SESSION['redirect_after_login'] = 'profile.php';
    
    // Redirect to login with error message
    header("Location: login.php?error=login_required");
    exit();
}

// Use direct include paths to avoid directory confusion
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

// Get user data
try {
    $stmt = $pdo->prepare("SELECT * FROM USERS WHERE user_id = :user_id");
    $stmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        // User ID in session doesn't match any user in database
        session_destroy();
        header("Location: login.php?error=invalid_user");
        exit();
    }
} catch (PDOException $e) {
    // Log the error
    error_log("Database error in profile.php: " . $e->getMessage());
    $error_message = "Database connection error. Please try again later.";
}

// Set username in session to prevent undefined array key error
if (!isset($_SESSION['username']) && isset($user['user_username'])) {
    $_SESSION['username'] = $user['user_username'];
}

// Default profile picture
$profile_pic = "assets/uploads/profile_pictures/default.png";

// Check if user has a custom profile picture
if (!empty($user['user_profile_picture']) && $user['user_profile_picture'] != 'default.png') {
    $profile_pic = "assets/uploads/profile_pictures/" . $user['user_profile_picture'] . "?v=" . time();
}

$_SESSION['profile_picture'] = $user['user_profile_picture'] ?? 'default.png';

// Check for messages
$success_message = $_SESSION['success_message'] ?? null;
$error_message = $_SESSION['error_message'] ?? null;

// Clear messages after displaying once
unset($_SESSION['success_message']);
unset($_SESSION['error_message']);

// Define a variable to help with active menu highlighting
$currentPage = 'profile';

// Use simple HTML structure directly in profile.php instead of including header
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - NCIP Admin Dashboard</title>
    <link rel="icon" href="../front end/images/ncip-logo.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .main-content {
            margin-left: 250px;
            padding: 20px;
        }
        .profile-img {
            width: 150px;
            height: 150px;
            object-fit: cover;
            border: 3px solid #fff;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid">
            <?php if($success_message): ?>
            <div class="alert alert-success"><?php echo $success_message; ?></div>
            <?php endif; ?>
            
            <?php if($error_message): ?>
            <div class="alert alert-danger"><?php echo $error_message; ?></div>
            <?php endif; ?>

            <div class="row mb-4">
                <div class="col-md-12">
                    <h1>My Profile</h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                            <li class="breadcrumb-item active">Profile</li>
                        </ol>
                    </nav>
                </div>
            </div>

            <div class="row">
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title">Profile Picture</h5>
                        </div>
                        <div class="card-body text-center">
                            <div class="profile-picture-container mb-3">
                            <img src="<?php echo htmlspecialchars($profile_pic); ?>?v=<?php echo time(); ?>" 
                                alt="Profile Picture" class="img-fluid rounded-circle profile-img">
                                <?php if (isset($debug_info)): ?>
                                <small class="text-muted d-block mt-2">
                                    <!-- Debug info - remove in production -->
                                    <?php foreach($debug_info as $key => $value): ?>
                                        <?php echo $key; ?>: <?php echo htmlspecialchars($value); ?><br>
                                    <?php endforeach; ?>
                                </small>
                                <?php endif; ?>
                            </div>        
                            <form action="update_profile.php" method="post" enctype="multipart/form-data">
                                <div class="mb-3">
                                    <label for="user_profile_picture" class="form-label">Change Profile Picture</label>
                                    <input class="form-control" type="file" id="user_profile_picture" name="profile_picture" accept="image/*">
                                    <small class="text-muted">Recommended size: 300x300 pixels</small>
                                </div>
                                <button type="submit" name="update_picture" class="btn btn-primary">Update Picture</button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title">Profile Information</h5>
                        </div>
                        <div class="card-body">
                            <form action="update_profile.php" method="post">
                            <div class="mb-3">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" class="form-control" id="username" name="username" 
                                    value="<?php echo htmlspecialchars($user['user_username'] ?? ''); ?>" required>
                            </div>
                                
                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email" 
                                    value="<?php echo htmlspecialchars($user['user_email'] ?? ''); ?>" required>
                            </div>
                                
                                <div class="mb-3">
                                    <label for="current_password" class="form-label">Current Password (required to save changes)</label>
                                    <input type="password" class="form-control" id="current_password" name="current_password" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="new_password" class="form-label">New Password (leave blank to keep current)</label>
                                    <input type="password" class="form-control" id="new_password" name="new_password">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="confirm_password" class="form-label">Confirm New Password</label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                                </div>
                                
                                <button type="submit" name="update_profile" class="btn btn-primary">Save Changes</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/script.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Toggle sidebar on mobile
            const sidebarToggle = document.getElementById('sidebarToggle');
            if (sidebarToggle) {
                sidebarToggle.addEventListener('click', function() {
                    document.querySelector('.sidebar').classList.toggle('show-mobile');
                });
            }
            
            // Profile picture preview
            const profilePicInput = document.getElementById('user_profile_picture');
            if (profilePicInput) {
                profilePicInput.addEventListener('change', function() {
                    const file = this.files[0];
                    if (file) {
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            document.querySelector('.profile-img').src = e.target.result;
                        };
                        reader.readAsDataURL(file);
                    }
                });
            }
            
            // Password confirmation validation
            const newPassword = document.getElementById('new_password');
            const confirmPassword = document.getElementById('confirm_password');
            
            if (newPassword && confirmPassword) {
                function validatePassword() {
                    if (newPassword.value != confirmPassword.value) {
                        confirmPassword.setCustomValidity("Passwords don't match");
                    } else {
                        confirmPassword.setCustomValidity('');
                    }
                }
                
                newPassword.addEventListener('change', validatePassword);
                confirmPassword.addEventListener('keyup', validatePassword);
            }
        });
    </script>
</body>
</html>