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
    $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = :user_id");
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
    <!-- Sidebar -->
    <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid">
            <?php if($success_message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle me-2"></i><?php echo htmlspecialchars($success_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>
            
            <?php if($error_message): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>

            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h2"><i class="bi bi-person-circle me-2"></i>My Profile</h1>
                <div class="d-flex gap-2">
                    <a href="users/edit.php?id=<?php echo $_SESSION['user_id']; ?>" class="btn btn-outline-primary">
                        <i class="bi bi-pencil"></i> Edit Profile
                    </a>
                    <a href="users/edit.php?id=<?php echo $_SESSION['user_id']; ?>" class="btn btn-outline-secondary">
                        <i class="bi bi-key"></i> Change Password
                    </a>
                </div>
            </div>

            <div class="row">
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0"><i class="bi bi-image me-2"></i>Profile Picture</h5>
                        </div>
                        <div class="card-body text-center">
                            <div class="profile-picture-container mb-3">
                                <img src="<?php echo htmlspecialchars($profile_pic); ?>?v=<?php echo time(); ?>" 
                                    alt="Profile Picture" class="img-fluid rounded-circle profile-img">
                            </div>        
                            <form action="update_profile.php" method="post" enctype="multipart/form-data">
                                <div class="mb-3">
                                    <label for="user_profile_picture" class="form-label">Change Profile Picture</label>
                                    <input class="form-control" type="file" id="user_profile_picture" name="profile_picture" accept="image/*" required>
                                    <small class="text-muted">Recommended size: 300x300 pixels. Max file size: 5MB</small>
                                </div>
                                <button type="submit" name="update_picture" class="btn btn-primary">
                                    <i class="bi bi-upload"></i> Update Picture
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0"><i class="bi bi-person-badge me-2"></i>Profile Information</h5>
                        </div>
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="username" class="form-label">Username</label>
                                    <input type="text" class="form-control" id="username" 
                                        value="<?php echo htmlspecialchars($user['user_username'] ?? ''); ?>" readonly>
                                </div>
                                <div class="col-md-6">
                                    <label for="email" class="form-label">Email</label>
                                    <input type="email" class="form-control" id="email" 
                                        value="<?php echo htmlspecialchars($user['user_email'] ?? ''); ?>" readonly>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="role" class="form-label">Role</label>
                                    <input type="text" class="form-control" id="role" value="<?php echo htmlspecialchars($user['user_role'] ?? 'USER'); ?>" readonly>
                                </div>
                                <div class="col-md-6">
                                    <label for="timezone" class="form-label">Timezone</label>
                                    <input type="text" class="form-control" id="timezone" value="<?php echo htmlspecialchars($user['user_timezone'] ?? 'Asia/Manila'); ?>" readonly>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="created" class="form-label">Member Since</label>
                                    <input type="text" class="form-control" id="created" 
                                        value="<?php 
                                            if (isset($user['user_created'])) {
                                                $timezone = new DateTimeZone($user['user_timezone'] ?? 'Asia/Manila');
                                                $created_date = new DateTime($user['user_created']);
                                                $created_date->setTimezone($timezone);
                                                echo $created_date->format('F d, Y');
                                            } else {
                                                echo 'N/A';
                                            }
                                        ?>" readonly>
                                </div>
                                <div class="col-md-6">
                                    <label for="last_login" class="form-label">Last Login</label>
                                    <input type="text" class="form-control" id="last_login" 
                                        value="<?php 
                                            if (isset($user['user_last_login']) && !empty($user['user_last_login'])) {
                                                $timezone = new DateTimeZone($user['user_timezone'] ?? 'Asia/Manila');
                                                $last_login = new DateTime($user['user_last_login']);
                                                $last_login->setTimezone($timezone);
                                                echo $last_login->format('M d, Y g:i A');
                                            } else {
                                                // Show current session login time if no previous login recorded
                                                $timezone = new DateTimeZone($user['user_timezone'] ?? 'Asia/Manila');
                                                $current_time = new DateTime();
                                                $current_time->setTimezone($timezone);
                                                echo $current_time->format('M d, Y g:i A') . ' (Current Session)';
                                            }
                                        ?>" readonly>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
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
                        // Validate file size (5MB limit)
                        if (file.size > 5 * 1024 * 1024) {
                            alert('File size must be less than 5MB');
                            this.value = '';
                            return;
                        }
                        
                        // Validate file type
                        const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
                        if (!allowedTypes.includes(file.type)) {
                            alert('Please select a valid image file (JPEG, PNG, GIF, WebP)');
                            this.value = '';
                            return;
                        }
                        
                        // Show preview
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            document.querySelector('.profile-img').src = e.target.result;
                        };
                        reader.readAsDataURL(file);
                    }
                });
            }
            
            // Auto-dismiss alerts after 5 seconds
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                setTimeout(function() {
                    const closeButton = alert.querySelector('.btn-close');
                    if (closeButton) {
                        closeButton.click();
                    }
                }, 5000);
            });
        });
    </script>
</body>
</html>