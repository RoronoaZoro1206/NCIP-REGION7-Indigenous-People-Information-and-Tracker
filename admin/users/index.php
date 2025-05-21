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

    // Get total records for pagination
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM USERS");
    $total_data = $stmt->fetch(PDO::FETCH_ASSOC);
    $total_records = $total_data['total'];
    $total_pages = ceil($total_records / $records_per_page);

    // Handle search query if present
    $search = isset($_GET['search']) ? $_GET['search'] : '';
    $search_condition = '';
    $params = [];
    
    if (!empty($search)) {
        $search_condition = " WHERE USER_USERNAME ILIKE :search OR USER_EMAIL ILIKE :search OR USER_ROLE ILIKE :search";
        $params[':search'] = "%$search%";
    }

    // Get users with pagination and search
    $sql = "SELECT * FROM USERS $search_condition ORDER BY USER_ID ASC LIMIT :limit OFFSET :offset";
    $stmt = $pdo->prepare($sql);
    
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
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="../index.php">Dashboard</a></li>
                <li class="breadcrumb-item active" aria-current="page">Users</li>
            </ol>
        </nav>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h2">Manage Users</h1>
            <?php if ($is_admin): ?>
            <a href="add.php" class="btn btn-primary">
                <i class="bi bi-person-plus"></i> Add New User
            </a>
            <?php endif; ?>
        </div>

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
                                            <button type="button"
                                                class="btn btn-sm btn-danger delete-user-btn"
                                                data-user-id="<?= $user['user_id'] ?>"
                                                data-user-name="<?= htmlspecialchars($user['user_username']) ?>"
                                                title="Delete">
                                                <i class="bi bi-trash"></i>
                                            </button>
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

    <!-- Modern Delete Confirmation Modal -->
    <div class="modal fade" id="deleteUserModal" tabindex="-1" aria-labelledby="deleteUserModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <form id="deleteUserForm" method="post">
            <div class="modal-header border-0">
              <div class="icon-box mx-auto">
                <i class="bi bi-exclamation-triangle-fill text-danger" style="font-size: 2.5rem;"></i>
              </div>
            </div>
            <div class="modal-body text-center">
              <h4 class="mb-2">Are you sure?</h4>
              <p class="mb-4">
                Do you really want to delete user <strong id="modalUserName"></strong>?<br>
                This process cannot be undone.
              </p>
            </div>
            <div class="modal-footer justify-content-center border-0">
              <button type="submit" class="btn btn-danger">
                <i class="bi bi-trash"></i> Delete
              </button>
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                <i class="bi bi-x"></i> Cancel
              </button>
            </div>
          </form>
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

        // Modern modal delete
        let deleteUserModal = new bootstrap.Modal(document.getElementById('deleteUserModal'));
        let deleteUserForm = document.getElementById('deleteUserForm');
        let modalUserName = document.getElementById('modalUserName');
        let userIdToDelete = null;

        document.querySelectorAll('.delete-user-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                userIdToDelete = btn.getAttribute('data-user-id');
                modalUserName.textContent = btn.getAttribute('data-user-name');
                deleteUserForm.action = 'delete.php?id=' + encodeURIComponent(userIdToDelete);
                deleteUserModal.show();
            });
        });
    });
    </script>
</body>
</html>