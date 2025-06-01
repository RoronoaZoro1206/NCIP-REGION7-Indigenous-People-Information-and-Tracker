<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include "../config/database.php";

// Enable error display for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Check if user is logged in and has admin role
if (!isset($_SESSION["user_id"]) || !isset($_SESSION["role"]) || strtoupper($_SESSION["role"]) !== 'ADMIN') {
    header("Location: ../login.php");
    exit();
}

// Fetch user profile info including profile picture
try {
    $user_stmt = $pdo->prepare("SELECT user_username, user_profile_picture FROM users WHERE user_id = :user_id");
    $user_stmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
    $user_stmt->execute();
    $user = $user_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Update session with latest user info
    if ($user) {
        $_SESSION['username'] = $user['user_username'];
        $_SESSION['profile_picture'] = $user['user_profile_picture'] ?? 'default.png';
    }
} catch (PDOException $e) {
    error_log("Failed to fetch user data: " . $e->getMessage());
}

// Initialize variables
$search = isset($_GET['search']) ? $_GET['search'] : '';
$ip_group_filter = isset($_GET['ip_group']) ? $_GET['ip_group'] : '';
$success_message = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : '';
$error_message = isset($_SESSION['error_message']) ? $_SESSION['error_message'] : '';
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$records_per_page = 10;

// Clear session messages
unset($_SESSION['success_message']);
unset($_SESSION['error_message']);

try {
    // Get unique IP groups for filter dropdown - EXCLUDE SOFT-DELETED RECORDS
    $groups_query = "SELECT DISTINCT popt_ip_group FROM population_table WHERE popt_deleted_at IS NULL ORDER BY popt_ip_group";
    $groups_stmt = $pdo->query($groups_query);
    $ip_groups = $groups_stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Build query conditions - ALWAYS EXCLUDE SOFT-DELETED RECORDS
    $where_conditions = ["popt_deleted_at IS NULL"];
    $params = [];

    if (!empty($search)) {
        $where_conditions[] = "(popt_name ilike ? OR popt_ip_group ilike ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    if (!empty($ip_group_filter)) {
        $where_conditions[] = "popt_ip_group = ?";
        $params[] = $ip_group_filter;
    }

    $where_clause = "WHERE " . implode(" AND ", $where_conditions);
    
    // Count total records for pagination - EXCLUDE SOFT-DELETED
    $count_query = "SELECT COUNT(*) FROM population_table $where_clause";
    $count_stmt = $pdo->prepare($count_query);
    $count_stmt->execute($params);
    $total_records = $count_stmt->fetchColumn();
    
    $total_pages = ceil($total_records / $records_per_page);
    $offset = ($current_page - 1) * $records_per_page;
    
    // Get population data with pagination - EXCLUDE SOFT-DELETED RECORDS
    $query = "SELECT 
                popt_id,
                popt_name,
                popt_ip_group,
                popt_latitude,
                popt_longitude,
                popt_population
            FROM population_table $where_clause 
            ORDER BY popt_id ASC 
            LIMIT $records_per_page OFFSET $offset";
            
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $populations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get summary stats - EXCLUDE SOFT-DELETED RECORDS
    $stats_query = "SELECT 
                      COUNT(*) as total_locations,
                      COUNT(DISTINCT popt_ip_group) as total_ip_groups,
                      SUM(popt_population) as total_population
                   FROM population_table 
                   WHERE popt_deleted_at IS NULL";
    $stats_stmt = $pdo->query($stats_query);
    $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get IP group distribution - EXCLUDE SOFT-DELETED RECORDS
    $distribution_query = "SELECT 
                            popt_ip_group, 
                            COUNT(*) as location_count, 
                            SUM(popt_population) as group_population 
                          FROM population_table 
                          WHERE popt_deleted_at IS NULL
                          GROUP BY popt_ip_group 
                          ORDER BY SUM(popt_population) DESC";
    $distribution_stmt = $pdo->query($distribution_query);
    $ip_distribution = $distribution_stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error_message = "Database error: " . $e->getMessage();
    $populations = [];
    $stats = ['total_locations' => 0, 'total_ip_groups' => 0, 'total_population' => 0];
    $ip_distribution = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Population Data Management - NCIP Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <!-- Add Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.3/dist/leaflet.css"
     integrity="sha384-o/2yZuJZWGJ4s/and21x29lfjZoq1CuZOGjAmwFU4lFFD+1vvUcwjN5gJwCYVxhk"
     crossorigin="anonymous">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .main-content {
            margin-left: 250px;
            padding: 20px;
        }
        .card-dashboard {
            border-radius: 10px;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            margin-bottom: 20px;
        }
        .stats-card {
            transition: all 0.3s ease;
        }
        .stats-card:hover {
            transform: translateY(-5px);
        }
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
            }
        }
        .leaflet-popup-content {
            font-size: 14px;
            line-height: 1.5;
        }
        .distribution-item {
            margin-bottom: 10px;
        }
        .distribution-bar {
            height: 25px;
            margin-top: 5px;
            border-radius: 4px;
        }
        .show-mobile {
            display: block !important;
        }
    </style>
</head>

<body>
    <!-- Sidebar -->
    <?php include '../includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-0 text-gray-800"><i class="bi bi-bar-chart-fill me-2"></i>Population Data Management</h1>
                    <p class="mb-0">Manage indigenous peoples population data across different locations</p>
                </div>
                <div class="btn-group" role="group">
                    <a href="add.php" class="btn btn-primary">
                        <i class="bi bi-plus-lg"></i> Add New Location
                    </a>
                    <a href="export.php<?php echo !empty($search) || !empty($ip_group_filter) ? '?search=' . urlencode($search) . '&ip_group=' . urlencode($ip_group_filter) : ''; ?>" class="btn btn-success">
                        <i class="bi bi-file-earmark-excel"></i> Export to Excel
                    </a>
                    <a href="../index.php" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i> Back to Dashboard
                    </a>
                </div>
            </div>

            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo $success_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $error_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="card stats-card card-dashboard bg-primary text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-white-50">Total Locations</h6>
                                    <h2 class="mb-0"><?php echo number_format($stats['total_locations'] ?? 0); ?></h2>
                                </div>
                                <i class="bi bi-geo-alt-fill" style="font-size: 2.5rem; opacity: 0.7;"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card stats-card card-dashboard bg-success text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-white-50">Total IP Groups</h6>
                                    <h2 class="mb-0"><?php echo number_format($stats['total_ip_groups'] ?? 0); ?></h2>
                                </div>
                                <i class="bi bi-people-fill" style="font-size: 2.5rem; opacity: 0.7;"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card stats-card card-dashboard bg-info text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-white-50">Total Population</h6>
                                    <h2 class="mb-0"><?php echo number_format($stats['total_population'] ?? 0); ?></h2>
                                </div>
                                <i class="bi bi-graph-up" style="font-size: 2.5rem; opacity: 0.7;"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-md-12">
                    <div class="card card-dashboard">
                        <div class="card-header bg-white d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">Population Data</h5>
                            <form class="d-flex" action="" method="get">
                                <select name="ip_group" class="form-select me-2" style="width: 200px;" onchange="this.form.submit()">
                                    <option value="">All IP Groups</option>
                                    <?php foreach ($ip_groups as $group): ?>
                                        <option value="<?php echo htmlspecialchars($group); ?>" <?php echo $ip_group_filter === $group ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($group); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="input-group">
                                    <input type="text" class="form-control" placeholder="Search" name="search" autocomplete="off" value="<?php echo htmlspecialchars($search); ?>">
                                    <button class="btn btn-outline-secondary" type="submit">
                                        <i class="bi bi-search"></i>
                                    </button>
                                    <?php if (!empty($search) || !empty($ip_group_filter)): ?>
                                        <a href="index.php" class="btn btn-outline-secondary">
                                            <i class="bi bi-x-lg"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </form>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>ID</th>
                                            <th>Location</th>
                                            <th>IP Group</th>
                                            <th>Population</th>
                                            <th>Coordinates</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (count($populations) > 0): ?>
                                            <?php foreach ($populations as $pop): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($pop['popt_id']); ?></td>
                                                    <td><?php echo htmlspecialchars($pop['popt_name']); ?></td>
                                                    <td>
                                                        <span class="badge bg-primary"><?php echo htmlspecialchars($pop['popt_ip_group']); ?></span>
                                                    </td>
                                                    <td><?php echo number_format($pop['popt_population']); ?></td>
                                                    <td>
                                                        <small>
                                                            <strong>Lat:</strong> <?php echo htmlspecialchars(number_format($pop['popt_latitude'], 6)); ?><br>
                                                            <strong>Lng:</strong> <?php echo htmlspecialchars(number_format($pop['popt_longitude'], 6)); ?>
                                                        </small>
                                                    </td>
                                                    <td>
                                                        <div class="btn-group btn-group-sm" role="group">
                                                            <a href="view.php?id=<?php echo $pop['popt_id']; ?>" class="btn btn-info" title="View">
                                                                <i class="bi bi-eye"></i>
                                                            </a>
                                                            <a href="edit.php?id=<?php echo $pop['popt_id']; ?>" class="btn btn-warning" title="Edit">
                                                                <i class="bi bi-pencil"></i>
                                                            </a>
                                                            <a href="delete.php?id=<?php echo $pop['popt_id']; ?>" 
                                                                class="btn btn-danger delete-population-link" 
                                                                title="Move to Recycle Bin"
                                                                data-population-id="<?php echo $pop['popt_id']; ?>"
                                                                data-population-name="<?php echo htmlspecialchars($pop['popt_name']); ?>"
                                                                data-population-group="<?php echo htmlspecialchars($pop['popt_ip_group']); ?>"
                                                                data-population-count="<?php echo number_format($pop['popt_population']); ?>">
                                                                <i class="bi bi-trash"></i>
                                                            </a>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="6" class="text-center">
                                                    <p class="my-3 text-muted">No population data found<?php echo !empty($search) ? ' matching your search' : ''; ?></p>
                                                    <a href="add.php" class="btn btn-sm btn-primary">
                                                        <i class="bi bi-plus-circle"></i> Add Population Data
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <?php if ($total_pages > 1): ?>
                            <div class="card-footer bg-white">
                                <nav>
                                    <ul class="pagination justify-content-center mb-0">
                                        <li class="page-item <?php echo $current_page <= 1 ? 'disabled' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $current_page - 1; ?>&search=<?php echo urlencode($search); ?>&ip_group=<?php echo urlencode($ip_group_filter); ?>">Previous</a>
                                        </li>
                                        
                                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                            <li class="page-item <?php echo $current_page == $i ? 'active' : ''; ?>">
                                                <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&ip_group=<?php echo urlencode($ip_group_filter); ?>">
                                                    <?php echo $i; ?>
                                                </a>
                                            </li>
                                        <?php endfor; ?>
                                        
                                        <li class="page-item <?php echo $current_page >= $total_pages ? 'disabled' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $current_page + 1; ?>&search=<?php echo urlencode($search); ?>&ip_group=<?php echo urlencode($ip_group_filter); ?>">Next</a>
                                        </li>
                                    </ul>
                                </nav>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-md-12">
                    <div class="card card-dashboard">
                        <div class="card-header bg-white">
                            <h5 class="card-title mb-0">IP Group Distribution</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <?php foreach ($ip_distribution as $group): ?>
                                    <div class="col-md-6 mb-3">
                                        <div class="distribution-item">
                                            <div class="d-flex justify-content-between">
                                                <h6><?php echo htmlspecialchars($group['popt_ip_group']); ?></h6>
                                                <span class="text-muted"><?php echo number_format($group['group_population']); ?> people / <?php echo $group['location_count']; ?> locations</span>
                                            </div>
                                            <?php 
                                            $percentage = ($group['group_population'] / ($stats['total_population'] ?: 1)) * 100;
                                            $color = 'bg-primary';
                                            if ($percentage > 35) $color = 'bg-danger';
                                            else if ($percentage > 20) $color = 'bg-warning';
                                            else if ($percentage > 10) $color = 'bg-success';
                                            else if ($percentage > 5) $color = 'bg-info';
                                            ?>
                                            <div class="progress" style="height: 25px;">
                                                <div class="progress-bar <?php echo $color; ?>" role="progressbar" style="width: <?php echo $percentage; ?>%" 
                                                    aria-valuenow="<?php echo $percentage; ?>" aria-valuemin="0" aria-valuemax="100">
                                                    <?php echo round($percentage, 1); ?>%
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Population Confirmation Modal -->
    <div class="modal fade" id="deletePopulationModal" tabindex="-1" aria-labelledby="deletePopulationModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title" id="deletePopulationModalLabel">
                        <i class="bi bi-recycle me-2"></i>Move Population Record to Recycle Bin
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center mb-3">
                        <i class="bi bi-exclamation-triangle-fill text-warning" style="font-size: 3rem;"></i>
                    </div>
                    <h6 class="text-center mb-3">Are you sure you want to move this population record to the recycle bin?</h6>
                    <div class="bg-light p-3 rounded" id="populationDetailsContainer">
                        <!-- Population details will be populated by JavaScript -->
                    </div>
                    <div class="mt-3">
                        <small class="text-muted">
                            <i class="bi bi-info-circle me-1"></i>
                            This action will move the record to the recycle bin where it can be restored later if needed.
                        </small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-1"></i>Cancel
                    </button>
                    <a href="#" class="btn btn-warning" id="confirmDeletePopulationBtn">
                        <i class="bi bi-recycle me-1"></i>Yes, Move to Recycle Bin
                    </a>
                </div>
            </div>
        </div>
    </div>

<!-- Bootstrap & Leaflet JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/leaflet@1.9.3/dist/leaflet.js"
 integrity="sha384-okbbMvvx/qfQkmiQKfd5VifbKZ/W8p1qIsWvE1ROPUfHWsDcC8/BnHohF7vPg2T6"
 crossorigin="anonymous"></script>

    <!-- Bootstrap & Leaflet JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/leaflet@1.9.3/dist/leaflet.js"
     integrity="sha384-okbbMvvx/qfQkmiQKfd5VifbKZ/W8p1qIsWvE1ROPUfHWsDcC8/BnHohF7vPg2T6"
     crossorigin="anonymous"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
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
    
    // Handle delete population modal
    const deletePopulationModal = new bootstrap.Modal(document.getElementById('deletePopulationModal'));
    const confirmDeletePopulationBtn = document.getElementById('confirmDeletePopulationBtn');
    const populationDetailsContainer = document.getElementById('populationDetailsContainer');
    let currentDeleteUrl = '';
    
    // Add click event listeners to all delete links
    document.querySelectorAll('.delete-population-link').forEach(function(link) {
        link.addEventListener('click', function(e) {
            e.preventDefault(); // Prevent immediate navigation
            e.stopPropagation(); // Stop event bubbling
            
            console.log('Delete link clicked, preventing default navigation'); // Debug
            
            // Get population data from data attributes
            const populationId = this.getAttribute('data-population-id');
            const populationName = this.getAttribute('data-population-name');
            const populationGroup = this.getAttribute('data-population-group');
            const populationCount = this.getAttribute('data-population-count');
            const deleteUrl = this.getAttribute('href');
            
            console.log('Population data:', { populationId, populationName, populationGroup, populationCount, deleteUrl }); // Debug
            
            // Store the URL for later use
            currentDeleteUrl = deleteUrl;
            
            // Populate population details in modal
            populationDetailsContainer.innerHTML = `
                <strong>Population Record Details:</strong><br>
                <i class="bi bi-tag-fill me-1"></i> <strong>Name:</strong> ${populationName}<br>
                <i class="bi bi-people-fill me-1"></i> <strong>IP Group:</strong> ${populationGroup}<br>
                <i class="bi bi-hash me-1"></i> <strong>Population:</strong> ${populationCount}<br>
                <i class="bi bi-key-fill me-1"></i> <strong>ID:</strong> #${populationId}
            `;
            
            // Update the confirm button href
            confirmDeletePopulationBtn.setAttribute('href', deleteUrl);
            
            console.log('About to show modal'); // Debug
            
            // Show the modal
            deletePopulationModal.show();
            
            // Return false to ensure no navigation
            return false;
        });
    });
    
    // Handle confirm delete button click
    confirmDeletePopulationBtn.addEventListener('click', function(e) {
        e.preventDefault(); // Prevent the anchor's default behavior
        
        console.log('Confirm button clicked'); // Debug
        
        // Show loading state
        const originalText = this.innerHTML;
        this.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Moving to Recycle Bin...';
        this.classList.add('disabled');
        
        // Close modal first
        deletePopulationModal.hide();
        
        // Navigate to delete URL after modal is hidden
        setTimeout(() => {
            console.log('Navigating to:', currentDeleteUrl); // Debug
            window.location.href = currentDeleteUrl;
        }, 300); // Reduced delay to make it feel faster
    });
    
    // Reset button state when modal is hidden
    document.getElementById('deletePopulationModal').addEventListener('hidden.bs.modal', function() {
        confirmDeletePopulationBtn.innerHTML = '<i class="bi bi-recycle me-1"></i>Yes, Move to Recycle Bin';
        confirmDeletePopulationBtn.classList.remove('disabled');
    });
    
    // Add smooth modal animations
    const modal = document.getElementById('deletePopulationModal');
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
    document.querySelectorAll('.delete-population-link').forEach(function(link) {
        // Remove any existing onclick attributes that might interfere
        link.removeAttribute('onclick');
    });
});
</script>
</body>
</html>