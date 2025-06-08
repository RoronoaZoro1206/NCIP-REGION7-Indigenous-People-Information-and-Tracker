<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Basic authentication check
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// Debug - Force role from database if needed
if (isset($_SESSION['user_id'])) {
    require_once '../config/database.php';
    // Get user role directly from database to ensure it's up to date
    $stmt = $pdo->prepare("SELECT user_role FROM USERS WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $db_user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($db_user) {
        // Update session with database role
        $_SESSION['user_role'] = $db_user['user_role'];
    }
}

// Make sure user has admin privileges - use case insensitive comparison
$is_admin = isset($_SESSION['user_role']) && strtoupper(trim($_SESSION['user_role'])) === 'ADMIN';

// Only try database connection if user is admin
$users = [];
$total_records = 0;
$total_pages = 1;
$page = 1;
$search = '';
$db_error = null;

// We'll get users regardless of admin status for demonstration, but hide UI elements
try {
    // Include database connection if not already included
    if (!isset($pdo)) {
        require_once '../config/database.php';
    }
    
    // Pagination settings
    $records_per_page = 10;
    $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
    $offset = ($page - 1) * $records_per_page;


// Handle search query if present
$search = isset($_GET['search']) ? $_GET['search'] : '';
$search_condition = '';
$params = [];

// Updated to exclude soft-deleted users
if (!empty($search)) {
    $search_condition = " WHERE USER_DELETED_AT IS NULL AND (LOWER(USER_USERNAME) LIKE LOWER(:search) OR LOWER(USER_EMAIL) LIKE LOWER(:search) OR LOWER(USER_ROLE) LIKE LOWER(:search))";
    $params[':search'] = "%$search%";
} else {
    $search_condition = " WHERE USER_DELETED_AT IS NULL";
}

// Get total records for pagination (exclude soft-deleted users)
$count_query = "SELECT COUNT(*) as total FROM USERS WHERE USER_DELETED_AT IS NULL";
if (!empty($search)) {
    $count_query .= " AND (LOWER(USER_USERNAME) LIKE LOWER(:search) OR LOWER(USER_EMAIL) LIKE LOWER(:search))";
}

$count_stmt = $pdo->prepare($count_query);
if (!empty($search)) {
    $count_stmt->bindValue(':search', "%$search%", PDO::PARAM_STR);
}
$count_stmt->execute();
$total_data = $count_stmt->fetch(PDO::FETCH_ASSOC);
$total_records = $total_data['total'];
$total_pages = ceil($total_records / $records_per_page);


// Get users with pagination and search (exclude soft-deleted users)
$query = "SELECT USER_ID, USER_USERNAME, USER_EMAIL, USER_ROLE, USER_CREATED, USER_TIMEZONE, USER_PROFILE_PICTURE 
          FROM USERS 
          WHERE USER_DELETED_AT IS NULL";

if (!empty($search)) {
    $query .= " AND (LOWER(USER_USERNAME) LIKE LOWER(:search) OR LOWER(USER_EMAIL) LIKE LOWER(:search))";
}

$query .= " ORDER BY USER_CREATED DESC LIMIT :limit OFFSET :offset";

$stmt = $pdo->prepare($query);

if (!empty($search)) {
    $stmt->bindValue(':search', "%$search%", PDO::PARAM_STR);
}

$stmt->bindValue(':limit', $records_per_page, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $db_error = "Database error: " . $e->getMessage();
}

// Process action messages
$message = '';
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - NCIP Admin</title>
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
        .user-avatar {
            width: 40px;
            height: 40px;
            object-fit: cover;
            border-radius: 50%;
        }
        .email-cell {
            max-width: 220px;
            min-width: 180px;
        }
        .email-text {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            display: block;
            width: 100%;
        }
        .btn-group .btn {
            position: relative;
            z-index: 1;
            cursor: pointer;
            pointer-events: all;
        }
        .btn i {
            pointer-events: none;
        }
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
        /* Modern Modal */
        .icon-box {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 80px;
            height: 80px;
            margin: 0 auto 20px;
            border-radius: 50%;
            background: #f27474;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <?php include '../includes/sidebar.php'; ?>

    <!-- Main content -->
    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h2"><i class="bi bi-people me-2"></i>User Management</h1>
            <?php if ($is_admin): ?>
            <a href="add.php" class="btn btn-primary">
                <i class="bi bi-person-plus"></i> Add New User
            </a>
            <?php endif; ?>
        </div>

        <!-- Success Message Display -->
        <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i>
            <?php echo $_SESSION['success_message']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php 
        unset($_SESSION['success_message']); 
        endif; ?>

        <?php if (isset($db_error)): ?>
        <div class="alert alert-danger">
            <?php echo $db_error; ?>
        </div>
        <?php endif; ?>
        
        <?php if (!$is_admin): ?>
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle"></i> 
            You need administrator privileges to manage users.
            <small class="d-block mt-1">Current role: <?= htmlspecialchars($_SESSION['user_role'] ?? 'Not set') ?></small>
        </div>
        <?php else: ?>

            <?php if (!empty($message)): ?>
            <div class="alert alert-<?= strpos($message, 'success') !== false ? 'success' : 'danger' ?> alert-dismissible fade show">
                <?= $message ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>
            
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <i class="bi bi-people-fill"></i> All Users
                    </div>
                    <form class="d-flex" method="GET">
                        <div class="input-group">
                            <input type="text" class="form-control" placeholder="Search users..." name="search" value="<?= htmlspecialchars($search) ?>" autocomplete="off">
                            <button class="btn btn-outline-secondary" type="submit">
                                <i class="bi bi-search"></i>
                            </button>
                        </div>
                    </form>
                </div>
                <div class="card-body">
                    <p><span class="badge bg-primary">&nbsp;&nbsp;</span> You (current admin)</p>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Profile</th>
                                    <th>Username</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Timezone</th>
                                    <th>Created On</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($users)): ?>
                                <tr>
                                    <td colspan="8" class="text-center">No users found.</td>
                                </tr>
                                <?php else: ?>
                                <?php foreach ($users as $user): ?>
                                <tr<?php if ($user['user_id'] == $_SESSION['user_id']) echo ' class="table-primary"'; ?>>
                                    <td><?= $user['user_id'] ?></td>
                                    <td>
                                        <?php 
                                        $profile_pic = $user['user_profile_picture'] ?? 'default.png';
                                        $profile_path = "../assets/uploads/profile_pictures/" . $profile_pic;
                                        $is_image = preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $profile_pic);
                                        ?>
                                        <?php if ($is_image): ?>
                                            <img src="<?= $profile_path ?>" alt="Profile" class="user-avatar">
                                        <?php else: ?>
                                            <img src="../assets/uploads/profile_pictures/default.png" alt="Default Profile" class="user-avatar">
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($user['user_username']) ?></td>
                                    <td class="email-cell">
                                        <span class="email-text" title="<?= htmlspecialchars($user['user_email']) ?>">
                                            <?= htmlspecialchars($user['user_email']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= strtoupper($user['user_role'] ?? '') === 'ADMIN' ? 'danger' : 'info' ?>">
                                            <?= htmlspecialchars($user['user_role'] ?? 'USER') ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($user['user_timezone'] ?? 'Asia/Manila') ?></td>
                                    <td>
                                        <?php 
                                        if (isset($user['user_created'])) {
                                            $timezone = new DateTimeZone($user['user_timezone'] ?? 'Asia/Manila');
                                            $created_date = new DateTime($user['user_created']);
                                            $created_date->setTimezone($timezone);
                                            echo $created_date->format('M d, Y H:i');
                                        } else {
                                            echo 'N/A';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <a href="view.php?id=<?= $user['user_id'] ?>" class="btn btn-sm btn-secondary" title="View">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <a href="edit.php?id=<?= $user['user_id'] ?>" class="btn btn-sm btn-info" title="Edit">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                                <?php if ($user['user_id'] != $_SESSION['user_id']): ?>
                                                <a href="delete.php?id=<?= $user['user_id'] ?>&action=soft_delete" 
                                                class="btn btn-sm btn-danger delete-user-link" 
                                                title="Delete"
                                                data-user-id="<?= $user['user_id'] ?>"
                                                data-user-name="<?= htmlspecialchars($user['user_username']) ?>"
                                                data-user-email="<?= htmlspecialchars($user['user_email']) ?>"
                                                data-user-role="<?= htmlspecialchars($user['user_role']) ?>">
                                                    <i class="bi bi-trash"></i>
                                                </a>
                                                <?php else: ?>
                                                <button class="btn btn-sm btn-danger" disabled title="Cannot delete yourself">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                                <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php if ($total_pages > 1): ?>
                <div class="card-footer">
                    <nav>
                        <ul class="pagination justify-content-center mb-0">
                            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                <a class="page-link" href="?page=<?= $page - 1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>">Previous</a>
                            </li>
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <?php if ($i == 1 || $i == $total_pages || ($i >= $page - 2 && $i <= $page + 2)): ?>
                                <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                    <a class="page-link" href="?page=<?= $i ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>"><?= $i ?></a>
                                </li>
                                <?php elseif ($i == $page - 3 || $i == $page + 3): ?>
                                <li class="page-item disabled">
                                    <span class="page-link">...</span>
                                </li>
                                <?php endif; ?>
                            <?php endfor; ?>
                            <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                                <a class="page-link" href="?page=<?= $page + 1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>">Next</a>
                            </li>
                        </ul>
                    </nav>
                </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
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
        // Fix to prevent sidebar issues - ensure no duplicates
        const sidebars = document.querySelectorAll('.sidebar');
        if (sidebars.length > 1) {
            for (let i = 1; i < sidebars.length; i++) {
                sidebars[i].remove();
            }
        }
        
        // Initialize Bootstrap tooltips for email display
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'));
        tooltipTriggerList.forEach(function(tooltipTriggerEl) {
            new bootstrap.Tooltip(tooltipTriggerEl);
        });
        
        // Handle delete user modal - FIXED VERSION
        const deleteUserModal = new bootstrap.Modal(document.getElementById('deleteUserModal'));
        const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
        const userDetailsContainer = document.getElementById('userDetailsContainer');
        let currentDeleteUrl = '';
        
        // Add click event listeners to all delete links - PROPERLY PREVENT DEFAULT
        document.querySelectorAll('.delete-user-link').forEach(function(link) {
            link.addEventListener('click', function(e) {
                e.preventDefault(); // Prevent immediate navigation
                e.stopPropagation(); // Stop event bubbling
                
                console.log('Delete link clicked, preventing default navigation'); // Debug
                
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
        });
        
        // Handle confirm delete button click
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
        
        // Reset button state when modal is hidden
        document.getElementById('deleteUserModal').addEventListener('hidden.bs.modal', function() {
            confirmDeleteBtn.innerHTML = '<i class="bi bi-recycle me-1"></i>Yes, Move to Recycle Bin';
            confirmDeleteBtn.classList.remove('disabled');
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
        document.querySelectorAll('.delete-user-link').forEach(function(link) {
            // Remove any existing onclick attributes that might interfere
            link.removeAttribute('onclick');
        });
    });
    </script>
</body>
</html>