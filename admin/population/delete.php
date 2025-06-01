<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include necessary files
require_once "../config/database.php";

// Check if request is AJAX
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

// Check if user is logged in and has admin role
if (!isset($_SESSION["user_id"])) {
    if ($isAjax) {
        echo json_encode(['success' => false, 'message' => 'Authentication required']);
        exit;
    } else {
        header("Location: ../login.php");
        exit();
    }
}

// Check admin role - handle different session key possibilities
$user_role = $_SESSION["role"] ?? $_SESSION["user_role"] ?? null;
if (!$user_role || strtoupper($user_role) !== 'ADMIN') {
    if ($isAjax) {
        echo json_encode(['success' => false, 'message' => 'Admin privileges required']);
        exit;
    } else {
        $_SESSION['error_message'] = "Access denied. Admin privileges required.";
        header("Location: ../login.php");
        exit();
    }
}

// Function to validate and sanitize input
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

/**
 * Ensure the population_table has soft delete columns
 */
function ensurePopulationSoftDeleteColumns($pdo) {
    try {
        // Check if soft delete columns exist, if not, add them
        $columns_to_add = [
            'popt_deleted_at' => 'TIMESTAMP NULL',
            'popt_deleted_by' => 'INTEGER'
        ];
        
        foreach ($columns_to_add as $column => $definition) {
            // Check if column exists
            $stmt = $pdo->prepare("SELECT column_name FROM information_schema.columns 
                                  WHERE table_name = 'population_table' AND column_name = :column_name");
            $stmt->execute([':column_name' => $column]);
            
            if (!$stmt->fetch()) {
                // Column doesn't exist, add it
                $pdo->exec("ALTER TABLE population_table ADD COLUMN {$column} {$definition}");
                error_log("Added column {$column} to population_table");
            }
        }
        
        // Create index on deleted_at column for better performance
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_population_table_deleted_at ON population_table(popt_deleted_at)");
        
    } catch (PDOException $e) {
        error_log("Error ensuring soft delete columns for population_table: " . $e->getMessage());
        // Don't throw - this is not critical for basic functionality
    }
}

// Initialize variables
$population_id = 0;
$action = 'soft_delete';
$show_confirmation = false;
$population_data = null;

// Handle different request methods
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['id']) || empty($_POST['id'])) {
        if ($isAjax) {
            echo json_encode(['success' => false, 'message' => 'No population ID specified']);
            exit;
        } else {
            $_SESSION['error_message'] = "No population ID specified.";
            header("Location: index.php");
            exit();
        }
    }
    $population_id = (int)sanitize_input($_POST['id']);
    $action = $_POST['action'] ?? 'soft_delete';
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!isset($_GET['id']) || empty($_GET['id'])) {
        if ($isAjax) {
            echo json_encode(['success' => false, 'message' => 'No population ID specified']);
            exit;
        } else {
            $_SESSION['error_message'] = "No population ID specified.";
            header("Location: index.php");
            exit();
        }
    }
    $population_id = (int)sanitize_input($_GET['id']);
    $action = $_GET['action'] ?? 'soft_delete';
    
    // For GET requests with soft_delete action, show confirmation page
    if ($action === 'soft_delete') {
        $show_confirmation = true;
    }
}

if ($population_id <= 0) {
    if ($isAjax) {
        echo json_encode(['success' => false, 'message' => 'Invalid population ID']);
        exit;
    } else {
        $_SESSION['error_message'] = "Invalid population ID.";
        header("Location: index.php");
        exit();
    }
}

// If showing confirmation page, get population data for display
if ($show_confirmation) {
    try {
        // First ensure the columns exist
        ensurePopulationSoftDeleteColumns($pdo);
        
        $stmt = $pdo->prepare("SELECT popt_id, popt_name, popt_ip_group, popt_population 
                               FROM population_table 
                               WHERE popt_id = ? AND popt_deleted_at IS NULL");
        $stmt->execute([$population_id]);
        $population_data = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$population_data) {
            $_SESSION['error_message'] = "Population record not found or already deleted.";
            header("Location: index.php");
            exit();
        }
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Error loading population data: " . $e->getMessage();
        header("Location: index.php");
        exit();
    }
} else {
    // Process the action (not showing confirmation) - redirect to recycle bin AJAX endpoints
    try {
        // First ensure the columns exist
        ensurePopulationSoftDeleteColumns($pdo);
        
        if ($action === 'restore') {
            // Redirect to recycle bin restore endpoint
            if ($isAjax) {
                // For AJAX requests, forward to the recycle bin AJAX endpoint
                $_POST['id'] = $population_id;
                $_POST['table'] = 'population_data';
                $_POST['action'] = 'restore';
                include '../recycle-bin/ajax/restore_item.php';
                exit;
            } else {
                $_SESSION['error_message'] = "Use the recycle bin to restore population records.";
                header("Location: ../recycle-bin/index.php");
                exit();
            }
            
        } elseif ($action === 'permanent_delete') {
            // Redirect to recycle bin delete endpoint
            if ($isAjax) {
                // For AJAX requests, forward to the recycle bin AJAX endpoint
                $_POST['id'] = $population_id;
                $_POST['table'] = 'population_data';
                $_POST['action'] = 'permanent_delete';
                include '../recycle-bin/ajax/delete_item.php';
                exit;
            } else {
                $_SESSION['error_message'] = "Use the recycle bin to permanently delete population records.";
                header("Location: ../recycle-bin/index.php");
                exit();
            }
            
        } else {
            // Soft delete (move to recycle bin) - POST request
            
            // Check if population exists and is not already deleted
            $stmt = $pdo->prepare("SELECT popt_id, popt_name, popt_ip_group, popt_population 
                                   FROM population_table 
                                   WHERE popt_id = ? AND popt_deleted_at IS NULL");
            $stmt->execute([$population_id]);
            $population_data = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$population_data) {
                if ($isAjax) {
                    echo json_encode(['success' => false, 'message' => 'Population record not found or already deleted']);
                    exit;
                } else {
                    $_SESSION['error_message'] = "Population record not found or already deleted.";
                    header("Location: index.php");
                    exit();
                }
            }
            
            // Perform soft delete using direct SQL (since RecycleBinManager might not have population_data configured)
            $soft_delete_stmt = $pdo->prepare("UPDATE population_table SET popt_deleted_at = NOW(), popt_deleted_by = ? WHERE popt_id = ?");
            $result = $soft_delete_stmt->execute([$_SESSION["user_id"], $population_id]);
            
            if ($result) {
                // Log the soft deletion activity
                try {
                    $activity_details = "Moved population record to recycle bin: " . $population_data['popt_name'] . 
                                      " (ID: " . $population_id . ", IP Group: " . $population_data['popt_ip_group'] . ")";
                    
                    $log_query = "INSERT INTO user_logs (user_id, log_type, log_details, log_timestamp) 
                                 VALUES (?, 'SOFT_DELETE_POPULATION', ?, NOW())";
                    $log_stmt = $pdo->prepare($log_query);
                    $log_stmt->execute([$_SESSION["user_id"], $activity_details]);
                } catch (Exception $log_error) {
                    error_log("Failed to log soft deletion activity: " . $log_error->getMessage());
                }
                
                $message = "Population record moved to recycle bin successfully.";
                if ($isAjax) {
                    echo json_encode([
                        'success' => true, 
                        'message' => $message,
                        'action' => 'soft_delete',
                        'population_id' => $population_id,
                        'population_name' => $population_data['popt_name'],
                        'redirect_url' => 'index.php'
                    ]);
                    exit;
                } else {
                    $_SESSION['success_message'] = $message;
                    header("Location: index.php");
                    exit();
                }
            } else {
                $message = "Failed to move population record to recycle bin.";
                if ($isAjax) {
                    echo json_encode(['success' => false, 'message' => $message]);
                    exit;
                } else {
                    $_SESSION['error_message'] = $message;
                    header("Location: index.php");
                    exit();
                }
            }
        }

    } catch (Exception $e) {
        // Log the error
        error_log("Error in population/delete.php: " . $e->getMessage());
        
        $error_message = "Error processing request: " . $e->getMessage();
        
        if ($isAjax) {
            echo json_encode([
                'success' => false, 
                'message' => $error_message,
                'action' => $action ?? 'unknown'
            ]);
            exit;
        } else {
            $_SESSION['error_message'] = $error_message;
            
            // Determine redirect location based on action
            if (isset($action) && ($action === 'restore' || $action === 'permanent_delete')) {
                header("Location: ../recycle-bin/index.php");
            } else {
                header("Location: index.php");
            }
            exit();
        }
    }
}

// Only reach here if $show_confirmation is true
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delete Population Record - NCIP Admin</title>
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
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
            }
        }
        .delete-warning {
            background: linear-gradient(135deg, #ff6b6b, #feca57);
            color: white;
            border: none;
        }
        .population-info {
            background-color: #f8f9fa;
            border-left: 4px solid #dc3545;
            padding: 15px;
            border-radius: 5px;
        }
    </style>
</head>
<body>
    <!-- Include sidebar -->
    <?php include '../includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1><i class="bi bi-recycle me-2"></i>Move Population Record to Recycle Bin</h1>
                <a href="index.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Back to Population Data
                </a>
            </div>

            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    <?php echo htmlspecialchars($_SESSION['error_message']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['error_message']); ?>
            <?php endif; ?>

            <div class="card">
                <div class="card-body">
                    <div class="alert delete-warning">
                        <h4 class="alert-heading"><i class="bi bi-recycle me-2"></i>Move to Recycle Bin</h4>
                        <p class="mb-0">This population record will be moved to the recycle bin. You can choose to restore it later or keep it there for future reference</p>
                    </div>

                    <h5><i class="bi bi-info-circle-fill me-2"></i>Population Record Details:</h5>
                    <div class="population-info">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <strong><i class="bi bi-tag-fill me-1"></i>Name:</strong> 
                                    <?php echo htmlspecialchars($population_data['popt_name']); ?>
                                </div>
                                <div class="mb-3">
                                    <strong><i class="bi bi-people-fill me-1"></i>IP Group:</strong> 
                                    <?php echo htmlspecialchars($population_data['popt_ip_group']); ?>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <strong><i class="bi bi-hash me-1"></i>Population Count:</strong> 
                                    <?php echo number_format($population_data['popt_population']); ?>
                                </div>
                                <div class="mb-3">
                                    <strong><i class="bi bi-key-fill me-1"></i>Record ID:</strong> 
                                    #<?php echo $population_data['popt_id']; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <hr>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card border-warning">
                                <div class="card-body text-center">
                                    <i class="bi bi-recycle display-4 text-warning mb-3"></i>
                                    <h5 class="card-title">Move to Recycle Bin</h5>
                                    <p class="card-text">The record will be hidden from the population data list but can be restored later.</p>
                                    <form method="post" action="delete.php" class="mt-3" id="deleteForm">
                                        <input type="hidden" name="id" value="<?php echo $population_id; ?>">
                                        <input type="hidden" name="action" value="soft_delete">
                                        <button type="button" class="btn btn-warning btn-lg" data-bs-toggle="modal" data-bs-target="#confirmDeleteModal">
                                            <i class="bi bi-recycle me-2"></i>Move to Recycle Bin
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="card border-secondary">
                                <div class="card-body text-center">
                                    <i class="bi bi-arrow-left-circle display-4 text-secondary mb-3"></i>
                                    <h5 class="card-title">Cancel</h5>
                                    <p class="card-text">Return to the population data list without making any changes.</p>
                                    <a href="index.php" class="btn btn-secondary btn-lg mt-3">
                                        <i class="bi bi-arrow-left me-2"></i>Cancel
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 p-3 bg-light rounded">
                        <h6><i class="bi bi-info-circle-fill me-2"></i>What happens next?</h6>
                        <ul class="mb-0">
                            <li>The population record will be moved to the Recycle Bin</li>
                            <li>It will no longer appear in the population data list</li>
                            <li>It will no longer appear on the heatmap</li>
                            <li>You can restore it from the Recycle Bin if needed</li>
                            <li>All data will be preserved for potential restoration</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Confirmation Modal -->
<div class="modal fade" id="confirmDeleteModal" tabindex="-1" aria-labelledby="confirmDeleteModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title" id="confirmDeleteModalLabel">
                    <i class="bi bi-recycle me-2"></i>Confirm Move to Recycle Bin
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="text-center mb-3">
                    <i class="bi bi-exclamation-triangle-fill text-warning" style="font-size: 3rem;"></i>
                </div>
                <h6 class="text-center mb-3">Are you sure you want to move this population record to the recycle bin?</h6>
                <div class="bg-light p-3 rounded">
                    <strong>Record Details:</strong><br>
                    <i class="bi bi-tag-fill me-1"></i> <strong>Name:</strong> <?php echo htmlspecialchars($population_data['popt_name']); ?><br>
                    <i class="bi bi-people-fill me-1"></i> <strong>IP Group:</strong> <?php echo htmlspecialchars($population_data['popt_ip_group']); ?><br>
                    <i class="bi bi-hash me-1"></i> <strong>Population:</strong> <?php echo number_format($population_data['popt_population']); ?>
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
                <button type="button" class="btn btn-warning" onclick="confirmMoveToRecycleBin()">
                    <i class="bi bi-recycle me-1"></i>Yes, Move to Recycle Bin
                </button>
            </div>
        </div>
    </div>
</div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Function to confirm and submit the form
    function confirmMoveToRecycleBin() {
        // Close the modal
        const modal = bootstrap.Modal.getInstance(document.getElementById('confirmDeleteModal'));
        modal.hide();
        
        // Show loading state
        const btn = document.querySelector('[data-bs-target="#confirmDeleteModal"]');
        const originalText = btn.innerHTML;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Moving to Recycle Bin...';
        btn.disabled = true;
        
        // Submit the form
        document.getElementById('deleteForm').submit();
    }
    
    // Auto-redirect if deletion was successful
    <?php if (isset($_SESSION['success_message'])): ?>
        setTimeout(function() {
            window.location.href = 'index.php';
        }, 2000);
    <?php endif; ?>
    
    // Add smooth modal animations
    document.getElementById('confirmDeleteModal').addEventListener('show.bs.modal', function () {
        this.querySelector('.modal-dialog').style.transform = 'scale(0.8)';
        this.querySelector('.modal-dialog').style.opacity = '0';
        setTimeout(() => {
            this.querySelector('.modal-dialog').style.transition = 'all 0.3s ease';
            this.querySelector('.modal-dialog').style.transform = 'scale(1)';
            this.querySelector('.modal-dialog').style.opacity = '1';
        }, 10);
    });
    
    // Reset modal state when hidden
    document.getElementById('confirmDeleteModal').addEventListener('hidden.bs.modal', function () {
        // Reset button state if form submission was cancelled
        const btn = document.querySelector('[data-bs-target="#confirmDeleteModal"]');
        if (btn && btn.disabled) {
            btn.innerHTML = '<i class="bi bi-recycle me-2"></i>Move to Recycle Bin';
            btn.disabled = false;
        }
    });
    
    // Handle form submission with better UX
    document.getElementById('deleteForm').addEventListener('submit', function(e) {
        // Add a small delay to show the loading state
        setTimeout(() => {
            // Show page-level loading overlay
            document.body.style.cursor = 'wait';
            const buttons = document.querySelectorAll('button, .btn');
            buttons.forEach(btn => btn.disabled = true);
        }, 100);
    });
    
    // Initialize tooltips if any
    document.addEventListener('DOMContentLoaded', function() {
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    });
</script>
</body>
</html>