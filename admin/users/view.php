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
$db_error = null;

try {
    // Include database connection
    require_once '../config/database.php';
    
    // Get user data
    $stmt = $pdo->prepare("SELECT * FROM USERS WHERE USER_ID = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        $_SESSION['message'] = "User not found.";
        header("Location: index.php");
        exit();
    }
} catch (PDOException $e) {
    $db_error = "Database error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View User - NCIP Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            width: 250px;
            background-color: #212529;
            padding-top: 20px;
            z-index: 100;
        }
        .sidebar-heading {
            padding: 0.875rem 1.25rem;
            font-size: 1.2rem;
            color: white;
        }
        .sidebar-menu {
            list-style: none;
            padding: 0;
        }
        .sidebar-menu li {
            width: 100%;
        }
        .sidebar-menu li a {
            display: block;
            padding: 0.75rem 1.25rem;
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
        }
        .sidebar-menu li a:hover {
            color: #fff;
            background-color: rgba(255, 255, 255, 0.1);
        }
        .sidebar-menu li a i {
            margin-right: 10px;
        }
        .active {
            background-color: #0d6efd;
            color: white !important;
        }
        .main-content {
            margin-left: 250px;
            padding: 20px;
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
        .user-avatar-lg {
            width: 150px;
            height: 150px;
            object-fit: cover;
            border-radius: 50%;
            margin-bottom: 20px;
        }
        .detail-label {
            font-weight: 600;
            color: #555;
        }
        /* Responsive styling */
        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
            }
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
                <li class="breadcrumb-item active" aria-current="page">View User</li>
            </ol>
        </nav>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h2">User Details</h1>
            <div>
                <a href="index.php" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Back to Users
                </a>
            </div>
        </div>

        <?php if (isset($db_error)): ?>
        <div class="alert alert-danger">
            <?php echo $db_error; ?>
        </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body text-center">
                        <?php 
                        $profile_pic = $user['user_profile_picture'] ?? 'default.png';
                        $profile_path = "../assets/uploads/profile_pictures/" . $profile_pic;
                        
                        // Check if file is an image
                        $is_image = preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $profile_pic);
                        ?>
                        
                        <?php if ($is_image): ?>
                            <img src="<?= $profile_path ?>" alt="Profile" class="user-avatar-lg">
                        <?php else: ?>
                            <img src="../assets/uploads/profile_pictures/default.png" alt="Default Profile" class="user-avatar-lg">
                        <?php endif; ?>
                        
                        <h4><?= htmlspecialchars($user['user_username']) ?></h4>
                        <span class="badge bg-<?= strtoupper($user['user_role'] ?? '') === 'ADMIN' ? 'danger' : 'info' ?> mb-3">
                            <?= htmlspecialchars($user['user_role'] ?? 'USER') ?>
                        </span>
                        
                        <?php if ($user['user_id'] == $_SESSION['user_id']): ?>
                            <div class="alert alert-info mb-0">
                                <i class="bi bi-info-circle"></i> This is your account
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Account Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-4 detail-label">User ID</div>
                            <div class="col-md-8"><?= $user['user_id'] ?></div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-4 detail-label">Username</div>
                            <div class="col-md-8"><?= htmlspecialchars($user['user_username']) ?></div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-4 detail-label">Email</div>
                            <div class="col-md-8"><?= htmlspecialchars($user['user_email']) ?></div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-4 detail-label">Role</div>
                            <div class="col-md-8">
                                <span class="badge bg-<?= strtoupper($user['user_role'] ?? '') === 'ADMIN' ? 'danger' : 'info' ?>">
                                    <?= htmlspecialchars($user['user_role'] ?? 'USER') ?>
                                </span>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-4 detail-label">Timezone</div>
                            <div class="col-md-8"><?= htmlspecialchars($user['user_timezone'] ?? 'Asia/Manila') ?></div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-4 detail-label">Created On</div>
                            <div class="col-md-8">
                                <?php 
                                if (isset($user['user_created'])) {
                                    $timezone = new DateTimeZone($user['user_timezone'] ?? 'Asia/Manila');
                                    $created_date = new DateTime($user['user_created']);
                                    $created_date->setTimezone($timezone);
                                    echo $created_date->format('F d, Y h:i A');
                                } else {
                                    echo 'N/A';
                                }
                                ?>
                            </div>
                        </div>
                        
                        <?php if (isset($user['user_last_login'])): ?>
                        <div class="row mb-3">
                            <div class="col-md-4 detail-label">Last Login</div>
                            <div class="col-md-8">
                                <?php 
                                $timezone = new DateTimeZone($user['user_timezone'] ?? 'Asia/Manila');
                                $last_login = new DateTime($user['user_last_login']);
                                $last_login->setTimezone($timezone);
                                echo $last_login->format('F d, Y h:i A');
                                ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Actions</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-flex gap-2">
                            <a href="edit.php?id=<?= $user['user_id'] ?>" class="btn btn-info">
                                <i class="bi bi-pencil"></i> Edit User
                            </a>
                            
                            <?php if ($user['user_id'] != $_SESSION['user_id']): ?>
                            <a href="delete.php?id=<?= $user['user_id'] ?>&action=soft_delete" 
                            class="btn btn-danger delete-user-btn" 
                            data-user-id="<?= $user['user_id'] ?>"
                            data-user-name="<?= htmlspecialchars($user['user_username']) ?>"
                            data-user-email="<?= htmlspecialchars($user['user_email']) ?>"
                            data-user-role="<?= htmlspecialchars($user['user_role'] ?? 'USER') ?>">
                                <i class="bi bi-trash"></i> Delete User
                            </a>
                            <?php else: ?>
                            <button class="btn btn-danger" disabled title="Cannot delete yourself">
                                <i class="bi bi-trash"></i> Delete User
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <!-- Delete User Confirmation Modal -->
    <div class="modal fade" id="deleteUserModal" tabindex="-1" aria-labelledby="deleteUserModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title" id="deleteUserModalLabel">
                        <i class="bi bi-recycle me-2"></i>Move User to Recycle Bin
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center mb-3">
                        <i class="bi bi-exclamation-triangle-fill text-warning" style="font-size: 3rem;"></i>
                    </div>
                    <h6 class="text-center mb-3">Are you sure you want to move this user to the recycle bin?</h6>
                    <div class="bg-light p-3 rounded" id="userDetailsContainer">
                        <!-- User details will be populated by JavaScript -->
                    </div>
                    <div class="mt-3">
                        <small class="text-muted">
                            <i class="bi bi-info-circle me-1"></i>
                            This action will move the user to the recycle bin where they can be restored later if needed.
                        </small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-1"></i>Cancel
                    </button>
                    <a href="#" class="btn btn-warning" id="confirmDeleteBtn">
                        <i class="bi bi-recycle me-1"></i>Yes, Move to Recycle Bin
                    </a>
                </div>
            </div>
        </div>
    </div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle delete user modal
    const deleteUserModal = new bootstrap.Modal(document.getElementById('deleteUserModal'));
    const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
    const userDetailsContainer = document.getElementById('userDetailsContainer');
    let currentDeleteUrl = '';
    
    // Add click event listener to delete button
    const deleteBtn = document.querySelector('.delete-user-btn');
    if (deleteBtn) {
        deleteBtn.addEventListener('click', function(e) {
            e.preventDefault(); // Prevent immediate navigation
            e.stopPropagation(); // Stop event bubbling
            
            console.log('Delete button clicked, preventing default navigation'); // Debug
            
            // Get user data from data attributes
            const userId = this.getAttribute('data-user-id');
            const userName = this.getAttribute('data-user-name');
            const userEmail = this.getAttribute('data-user-email');
            const userRole = this.getAttribute('data-user-role');
            const deleteUrl = this.getAttribute('href');
            
            console.log('User data:', { userId, userName, userEmail, userRole, deleteUrl }); // Debug
            
            // Store the URL for later use
            currentDeleteUrl = deleteUrl;
            
            // Populate user details in modal
            userDetailsContainer.innerHTML = `
                <strong>User Details:</strong><br>
                <i class="bi bi-person-badge-fill me-1"></i> <strong>Username:</strong> ${userName}<br>
                <i class="bi bi-envelope-fill me-1"></i> <strong>Email:</strong> ${userEmail}<br>
                <i class="bi bi-shield-fill me-1"></i> <strong>Role:</strong> ${userRole}<br>
                <i class="bi bi-hash me-1"></i> <strong>ID:</strong> #${userId}
            `;
            
            // Update the confirm button href
            confirmDeleteBtn.setAttribute('href', deleteUrl);
            
            console.log('About to show modal'); // Debug
            
            // Show the modal
            deleteUserModal.show();
            
            // Return false to ensure no navigation
            return false;
        });
    }
    
    // Handle confirm delete button click
    if (confirmDeleteBtn) {
        confirmDeleteBtn.addEventListener('click', function(e) {
            e.preventDefault(); // Prevent the anchor's default behavior
            
            console.log('Confirm button clicked'); // Debug
            
            // Show loading state
            const originalText = this.innerHTML;
            this.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Moving to Recycle Bin...';
            this.classList.add('disabled');
            
            // Close modal first
            deleteUserModal.hide();
            
            // Navigate to delete URL after modal is hidden
            setTimeout(() => {
                console.log('Navigating to:', currentDeleteUrl); // Debug
                window.location.href = currentDeleteUrl;
            }, 300); // Reduced delay to make it feel faster
        });
    }
    
    // Reset button state when modal is hidden
    document.getElementById('deleteUserModal').addEventListener('hidden.bs.modal', function() {
        if (confirmDeleteBtn) {
            confirmDeleteBtn.innerHTML = '<i class="bi bi-recycle me-1"></i>Yes, Move to Recycle Bin';
            confirmDeleteBtn.classList.remove('disabled');
        }
    });
    
    // Add smooth modal animations
    const modal = document.getElementById('deleteUserModal');
    if (modal) {
        modal.addEventListener('show.bs.modal', function () {
            console.log('Modal is showing'); // Debug
            const modalDialog = this.querySelector('.modal-dialog');
            if (modalDialog) {
                modalDialog.style.transform = 'scale(0.8)';
                modalDialog.style.opacity = '0';
                setTimeout(() => {
                    modalDialog.style.transition = 'all 0.3s ease';
                    modalDialog.style.transform = 'scale(1)';
                    modalDialog.style.opacity = '1';
                }, 10);
            }
        });
        
        modal.addEventListener('shown.bs.modal', function () {
            console.log('Modal is now visible'); // Debug
        });
    }
    
    // Additional check - ensure no other click handlers are interfering
    const deleteLinks = document.querySelectorAll('.delete-user-btn');
    deleteLinks.forEach(function(link) {
        // Remove any existing onclick attributes that might interfere
        link.removeAttribute('onclick');
    });
});
</script>
</body>
</html>