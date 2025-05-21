<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check for login
if (!isset($_SESSION["user_id"])) {
    header("Location: " . (dirname($_SERVER['PHP_SELF']) === '/admin' ? 'login.php' : '../login.php'));
    exit();
}

$base_path = '';
$current_dir = str_replace('\\', '/', dirname($_SERVER['PHP_SELF']));

// More reliable path calculation for Windows/XAMPP
if (strpos($current_dir, '/admin/users') !== false) {
    $base_path = '../';
} elseif (strpos($current_dir, '/admin/') !== false) {
    // We're in a subdirectory of admin
    $subpath = substr($current_dir, strpos($current_dir, '/admin/') + 7);
    if (!empty($subpath)) {
        $depth = substr_count($subpath, '/') + 1;
        $base_path = str_repeat('../', $depth);
    }
} elseif (strpos($current_dir, '/admin') !== false) {
    // We're in the main admin directory
    $base_path = '';
}

// Calculate the absolute URL for testing
$absolute_base = '';
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    $absolute_base = 'https://';
} else {
    $absolute_base = 'http://';
}
$absolute_base .= $_SERVER['HTTP_HOST'];


// Check if stats array exists and set defaults if not
if (!isset($stats) || !is_array($stats)) {
    $stats = [
        'pending' => 0,
        'unread_messages' => 0
    ];
}

// Get the current file path for active menu detection
$current_file = str_replace('\\', '/', $_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NCIP Admin Dashboard</title>
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
        .navbar {
            margin-left: 250px;
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
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
                display: none;
            }
            .sidebar.show-mobile {
                display: block;
            }
            .navbar {
                margin-left: 0;
            }
             .main-content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-heading d-flex align-items-center">
            <img src="<?php echo $base_path; ?>assets/img/ncip-logo.png" alt="NCIP Logo" width="40" class="me-2">
            NCIP Admin
        </div>
        <hr class="text-white">
        <ul class="sidebar-menu">
            <li><a href="<?php echo $base_path; ?>index.php" class="<?php echo (basename($_SERVER['PHP_SELF']) === 'index.php' && strpos($current_file, '/admin/index.php') !== false) ? 'active' : ''; ?>">
                <i class="bi bi-speedometer2"></i> Dashboard</a>
            </li>
            <li>
                <a href="<?php echo $base_path; ?>scholarships/index.php" class="<?php echo (strpos($current_file, '/admin/scholarships/') !== false) ? 'active' : ''; ?>">
                    <i class="bi bi-mortarboard-fill"></i> Scholarship Applications
                    <?php if (!empty($stats['pending'])): ?>
                    <span class="badge bg-warning float-end"><?php echo $stats['pending']; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li>
                <a href="<?php echo $base_path; ?>contacts/index.php" class="<?php echo (strpos($current_file, '/admin/contacts/') !== false) ? 'active' : ''; ?>">
                    <i class="bi bi-envelope"></i> Contact Messages
                    <?php if (!empty($stats['unread_messages'])): ?>
                    <span class="badge bg-danger float-end"><?php echo $stats['unread_messages']; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li><a href="<?php echo $base_path; ?>announcements/index.php" class="<?php echo (strpos($current_file, '/admin/announcements/') !== false) ? 'active' : ''; ?>">
                <i class="bi bi-megaphone"></i> Announcements</a>
            </li>
            <li><a href="<?php echo $base_path; ?>population/index.php" class="<?php echo (strpos($current_file, '/admin/population/') !== false) ? 'active' : ''; ?>">
                <i class="bi bi-people"></i> Population Data</a>
            </li>
            <li>
                <a href="<?php echo $base_path; ?>users/index.php" class="<?php echo (strpos($current_file, '/users/') !== false) ? 'active' : ''; ?>" id="usersMenuLink">
                    <i class="bi bi-person-gear"></i> Users
                </a>
            </li>
            <li><a href="<?php echo $base_path; ?>logout.php">
                <i class="bi bi-box-arrow-right"></i> Logout</a>
            </li>
        </ul>
    </div>

    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-light bg-light">
        <div class="container-fluid">
            <button class="navbar-toggler" type="button" id="sidebarToggle">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarSupportedContent">
                <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <?php if (isset($_SESSION['profile_picture']) && $_SESSION['profile_picture'] !== 'default.png'): ?>
                            <img src="<?php echo $base_path; ?>assets/uploads/profile_pictures/<?php echo htmlspecialchars($_SESSION['profile_picture']); ?>?v=<?php echo time(); ?>" 
                                class="rounded-circle me-1" style="width: 24px; height: 24px; object-fit: cover;" alt="Profile">
                            <?php else: ?>
                            <i class="bi bi-person-circle"></i>
                            <?php endif; ?>
                            <?php echo htmlspecialchars($_SESSION["username"] ?? "Admin"); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="<?php echo $base_path; ?>profile.php">Profile</a></li>
                            <li><a class="dropdown-item" href="<?php echo $base_path; ?>settings.php">Settings</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="<?php echo $base_path; ?>logout.php">Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
