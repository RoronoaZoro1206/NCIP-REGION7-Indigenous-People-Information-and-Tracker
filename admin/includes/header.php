<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.php");
    exit();
}

// Fix path to database.php (use absolute path)
require_once __DIR__ . '/../config/database.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NCIP Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
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
        .table {
            margin-bottom: 0;
        }
        .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.76rem;
        }
        .badge {
            font-weight: 500;
        }
        
        /* Add profile picture styles */
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
    <!-- Sidebar - using include_once with correct path -->
    <?php 
    // Check if sidebar.php exists before including
    if (file_exists(__DIR__ . '/sidebar.php')) {
        include_once __DIR__ . '/sidebar.php';
    } else {
        // Fallback navigation if sidebar doesn't exist
        echo '<div class="sidebar">
                <div class="sidebar-heading">NCIP Admin</div>
                <ul class="sidebar-menu">
                    <li><a href="../index.php"><i class="bi bi-speedometer2"></i> Dashboard</a></li>
                    <li><a href="../profile.php"><i class="bi bi-person"></i> Profile</a></li>
                </ul>
              </div>';
    }
    ?>

    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-light bg-light">
        <div class="container-fluid">
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarSupportedContent">
                <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <?php if (isset($_SESSION['profile_picture']) && $_SESSION['profile_picture'] !== 'default.png'): ?>
                            <img src="<?php echo dirname($_SERVER['PHP_SELF']) === '/' ? '' : dirname($_SERVER['PHP_SELF']); ?>/assets/uploads/profile_pictures/<?php echo htmlspecialchars($_SESSION['profile_picture']); ?>?v=<?php echo time(); ?>" 
                                class="rounded-circle me-1" style="width: 24px; height: 24px; object-fit: cover;" alt="Profile">
                            <?php else: ?>
                            <i class="bi bi-person-circle"></i>
                            <?php endif; ?>
                            <?php echo htmlspecialchars($_SESSION["username"] ?? "Admin"); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="../profile.php">Profile</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../logout.php">Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Content Container -->
    <div class="main-content">
        <div class="container-fluid">