<?php
// filepath: c:\xampp\htdocs\Information Management\IM---Copy\admin\contacts\archived_messages.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include "../config/database.php";

// Check if user is logged in and has admin role
if (!isset($_SESSION["user_id"]) || !isset($_SESSION["role"]) || strtoupper($_SESSION["role"]) !== 'ADMIN') {
    header("Location: ../login.php");
    exit();
}

/**
 * Ensure the contact_message table has soft delete columns
 */
function ensureContactSoftDeleteColumns($pdo) {
    try {
        // Check if soft delete columns exist, if not, add them
        $columns_to_add = [
            'contm_deleted_at' => 'TIMESTAMP NULL',
            'contm_deleted_by' => 'INTEGER'
        ];
        
        foreach ($columns_to_add as $column => $definition) {
            // Check if column exists
            $stmt = $pdo->prepare("SELECT column_name FROM information_schema.columns 
                                  WHERE table_name = 'contact_message' AND column_name = :column_name");
            $stmt->execute([':column_name' => $column]);
            
            if (!$stmt->fetch()) {
                // Column doesn't exist, add it
                $pdo->exec("ALTER TABLE contact_message ADD COLUMN {$column} {$definition}");
                error_log("Added column {$column} to contact_message");
            }
        }
        
        // Create index on deleted_at column for better performance
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_contact_message_deleted_at ON contact_message(contm_deleted_at)");
        
    } catch (PDOException $e) {
        error_log("Error ensuring soft delete columns for contact_message: " . $e->getMessage());
        // Don't throw - this is not critical for basic functionality
    }
}

// Ensure soft delete columns exist
ensureContactSoftDeleteColumns($pdo);

// Pagination setup
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$records_per_page = 10;
$offset = ($current_page - 1) * $records_per_page;

// Search functionality
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$search_condition = '';
$params = [];

if (!empty($search)) {
    $search_condition = " AND (LOWER(contm_name) LIKE LOWER(:search) 
                           OR LOWER(contm_email) LIKE LOWER(:search) 
                           OR LOWER(contm_message) LIKE LOWER(:search))";
    $params[':search'] = "%$search%";
}

// Get archived messages with pagination - EXCLUDE SOFT DELETED MESSAGES (PostgreSQL COMPATIBLE)
try {
    // Count total archived messages for pagination (excluding soft deleted) - FIXED FOR PostgreSQL
    $count_sql = "SELECT COUNT(*) FROM contact_message 
                  WHERE contm_status = 'Archived' 
                  AND contm_deleted_at IS NULL" . $search_condition;
    $count_stmt = $pdo->prepare($count_sql);
    
    if (!empty($search)) {
        $count_stmt->bindValue(':search', "%$search%", PDO::PARAM_STR);
    }
    
    $count_stmt->execute();
    $total_records = $count_stmt->fetchColumn();
    
    // Calculate total pages
    $total_pages = ceil($total_records / $records_per_page);
    
    // Adjust current page if needed
    if ($current_page > $total_pages && $total_pages > 0) {
        $current_page = 1;
    }
    
    // Get messages for current page (excluding soft deleted) - FIXED FOR PostgreSQL
    $sql = "SELECT contm_id, contm_name, contm_email, contm_phone, contm_message, 
                   contm_submission_date, contm_status
            FROM contact_message 
            WHERE contm_status = 'Archived' 
            AND contm_deleted_at IS NULL" . $search_condition . " 
            ORDER BY contm_submission_date ASC, contm_id ASC 
            LIMIT :limit OFFSET :offset";
            
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':limit', $records_per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    
    if (!empty($search)) {
        $stmt->bindValue(':search', "%$search%", PDO::PARAM_STR);
    }
    
    $stmt->execute();
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Error fetching archived messages: " . $e->getMessage());
    $error_message = "Database error: " . $e->getMessage();
    $messages = [];
    $total_pages = 0;
    $total_records = 0;
}

// Helper functions for date formatting
function safe_date_format($date_string, $format = 'M d, Y') {
    if (empty($date_string) || $date_string === null) {
        return 'Unknown';
    }
    
    try {
        $date = new DateTime($date_string);
        return $date->format($format);
    } catch (Exception $e) {
        return 'Invalid date';
    }
}

function safe_time_format($date_string, $format = 'g:i A') {
    if (empty($date_string) || $date_string === null) {
        return 'Unknown';
    }
    
    try {
        $date = new DateTime($date_string);
        return $date->format($format);
    } catch (Exception $e) {
        return 'Invalid time';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Archived Messages - NCIP Admin</title>
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
        .pagination {
            justify-content: center;
        }
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }
        .table th {
            border-top: none;
            font-weight: 600;
            color: #495057;
        }
        .btn-group .btn {
            margin-right: 2px;
        }
        .message-preview {
            max-width: 300px;
            word-wrap: break-word;
        }
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
            }
        }
        .modal-content {
            border-radius: 10px;
        }
        .alert {
            border-radius: 10px;
        }
        .status-badge {
            font-size: 0.75rem;
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
                <h1><i class="bi bi-archive me-2"></i>Archived Messages</h1>
                <div class="btn-group">
                    <a href="index.php" class="btn btn-primary">
                        <i class="bi bi-inbox me-1"></i>Back to Inbox
                    </a>
                </div>
            </div>
            
            <!-- Alert Container for dynamic messages -->
            <div id="alert-container">
                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle-fill me-2"></i>
                        <?php echo htmlspecialchars($_SESSION['success_message']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php unset($_SESSION['success_message']); ?>
                <?php endif; ?>

                <?php if (isset($_SESSION['error_message'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        <?php echo htmlspecialchars($_SESSION['error_message']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php unset($_SESSION['error_message']); ?>
                <?php endif; ?>
                
                <?php if (isset($_GET['moved']) && $_GET['moved'] === 'true'): ?>
                    <div class="alert alert-warning alert-dismissible fade show" role="alert">
                        <i class="bi bi-recycle me-2"></i>
                        Message moved to recycle bin successfully.
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($_GET['unarchived']) && $_GET['unarchived'] === 'true'): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle me-2"></i>
                        Message unarchived successfully.
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($_GET['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        <?php 
                            $error = $_GET['error'];
                            if ($error === 'database') {
                                echo "A database error occurred.";
                            } elseif ($error === 'not_found') {
                                echo "Message not found or already processed.";
                            } else {
                                echo "An error occurred while processing your request.";
                            }
                        ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Search Bar -->
            <div class="card mb-4">
                <div class="card-body">
                    <form action="archived_messages.php" method="get" class="row g-2">
                        <div class="col-md-8">
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-search"></i></span>
                                <input type="text" class="form-control" placeholder="Search archived messages by name, email, or content..." name="search" autocomplete="off" value="<?php echo htmlspecialchars($search); ?>">
                                <button class="btn btn-primary" type="submit">
                                    <i class="bi bi-search me-1"></i>Search
                                </button>
                                <?php if (!empty($search)): ?>
                                    <a href="archived_messages.php" class="btn btn-outline-secondary">
                                        <i class="bi bi-x-circle me-1"></i>Clear
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-end">
                                <span class="text-muted">
                                    <i class="bi bi-info-circle me-1"></i>
                                    <?php echo $total_records; ?> archived message<?php echo $total_records !== 1 ? 's' : ''; ?> found
                                </span>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Messages Table -->
            <div class="card">
                <div class="card-header bg-secondary text-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-archive me-2"></i>Archived Messages</span>
                        <?php if (!empty($messages)): ?>
                            <span class="badge bg-light text-dark">
                                <?php echo $total_records; ?> message<?php echo $total_records !== 1 ? 's' : ''; ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (isset($error_message)): ?>
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>
                            <?php echo htmlspecialchars($error_message); ?>
                        </div>
                    <?php endif; ?>

                    <?php if (empty($messages)): ?>
                        <div class="text-center p-5">
                            <i class="bi bi-archive display-1 text-secondary"></i>
                            <h4 class="mt-3 text-muted">No Archived Messages Found</h4>
                            <?php if (!empty($search)): ?>
                                <p class="mb-3">No archived messages match your search criteria.</p>
                                <a href="archived_messages.php" class="btn btn-outline-primary">
                                    <i class="bi bi-arrow-clockwise me-1"></i>View All Archived Messages
                                </a>
                            <?php else: ?>
                                <p class="mb-3">There are currently no archived messages.</p>
                                <a href="index.php" class="btn btn-primary">
                                    <i class="bi bi-inbox me-1"></i>View All Messages
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 80px;">ID</th>
                                        <th style="width: 150px;">Name</th>
                                        <th style="width: 200px;">Email</th>
                                        <th>Message Preview</th>
                                        <th style="width: 150px;">Date Submitted</th>
                                        <th style="width: 200px;" class="text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($messages as $message): ?>
                                        <tr id="msg-row-<?php echo $message['contm_id']; ?>">
                                            <td>
                                                <span class="badge bg-secondary">#<?php echo $message['contm_id']; ?></span>
                                            </td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($message['contm_name'] ?? 'Unknown'); ?></strong>
                                                <br>
                                                <small class="text-muted">
                                                    <span class="badge bg-dark status-badge">
                                                        <i class="bi bi-archive me-1"></i>Archived
                                                    </span>
                                                </small>
                                            </td>
                                            <td>
                                                <a href="mailto:<?php echo htmlspecialchars($message['contm_email'] ?? ''); ?>" class="text-decoration-none">
                                                    <i class="bi bi-envelope me-1"></i>
                                                    <?php echo htmlspecialchars($message['contm_email'] ?? 'No email'); ?>
                                                </a>
                                            </td>
                                            <td class="message-preview">
                                                <?php 
                                                    $preview = $message['contm_message'] ?? '';
                                                    $truncated = mb_substr($preview, 0, 100);
                                                    echo htmlspecialchars($truncated);
                                                    if (mb_strlen($preview) > 100) {
                                                        echo '<span class="text-muted">...</span>';
                                                    }
                                                ?>
                                            </td>
                                            <td>
                                                <small class="text-muted">
                                                    <i class="bi bi-calendar3 me-1"></i>
                                                    <?php echo safe_date_format($message['contm_submission_date']); ?>
                                                    <br>
                                                    <i class="bi bi-clock me-1"></i>
                                                    <?php echo safe_time_format($message['contm_submission_date']); ?>
                                                </small>
                                            </td>
                                            <td class="text-center">
                                                <div class="btn-group" role="group">
                                                    <a href="view.php?id=<?php echo $message['contm_id']; ?>" 
                                                       class="btn btn-sm btn-outline-primary" 
                                                       title="View Message">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                    <button type="button" 
                                                            class="btn btn-sm btn-outline-success" 
                                                            onclick="unarchiveMessage(<?php echo $message['contm_id']; ?>)"
                                                            title="Unarchive Message">
                                                        <i class="bi bi-box-arrow-up"></i>
                                                    </button>
                                                    <a href="delete.php?id=<?php echo $message['contm_id']; ?>&action=soft_delete" 
                                                    class="btn btn-sm btn-outline-danger delete-message-btn" 
                                                    data-message-id="<?php echo $message['contm_id']; ?>"
                                                    data-message-name="<?php echo htmlspecialchars($message['contm_name'] ?? 'this message'); ?>"
                                                    title="Move to Recycle Bin">
                                                        <i class="bi bi-trash"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                            <nav aria-label="Page navigation" class="mt-4">
                                <ul class="pagination justify-content-center">
                                    <li class="page-item <?php echo ($current_page <= 1) ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $current_page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" aria-label="Previous">
                                            <span aria-hidden="true">&laquo;</span>
                                        </a>
                                    </li>
                                    
                                    <?php
                                    $start_page = max(1, $current_page - 2);
                                    $end_page = min($total_pages, $start_page + 4);
                                    if ($end_page - $start_page < 4 && $start_page > 1) {
                                        $start_page = max(1, $end_page - 4);
                                    }
                                    
                                    for ($i = $start_page; $i <= $end_page; $i++): 
                                    ?>
                                        <li class="page-item <?php echo ($i == $current_page) ? 'active' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <li class="page-item <?php echo ($current_page >= $total_pages) ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $current_page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" aria-label="Next">
                                            <span aria-hidden="true">&raquo;</span>
                                        </a>
                                    </li>
                                </ul>
                            </nav>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Move to Recycle Bin Confirmation Modal -->
    <div class="modal fade" id="deleteMessageModal" tabindex="-1" aria-labelledby="deleteMessageModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title" id="deleteMessageModalLabel">
                        <i class="bi bi-recycle me-2"></i>Move Message to Recycle Bin
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center mb-3">
                        <i class="bi bi-exclamation-triangle-fill text-warning" style="font-size: 3rem;"></i>
                    </div>
                    <h6 class="text-center mb-3">Are you sure you want to move this archived message to the recycle bin?</h6>
                    <div class="bg-light p-3 rounded" id="messageDetailsContainer">
                        <!-- Message details will be populated by JavaScript -->
                    </div>
                    <div class="mt-3">
                        <small class="text-muted">
                            <i class="bi bi-info-circle me-1"></i>
                            This action will move the message to the recycle bin where it can be restored later if needed.
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
        // Initialize modal instances
        const deleteMessageModal = new bootstrap.Modal(document.getElementById('deleteMessageModal'), {});
        const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
        
        // Handle delete button clicks
        document.querySelectorAll('.delete-message-btn').forEach(function(button) {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                
                const messageId = this.getAttribute('data-message-id');
                const messageName = this.getAttribute('data-message-name');
                const currentDeleteUrl = this.getAttribute('href');
                
                // Update modal content
                const messageDetailsContainer = document.getElementById('messageDetailsContainer');
                messageDetailsContainer.innerHTML = `
                    <i class="bi bi-person-fill me-1"></i> <strong>From:</strong> ${messageName}<br>
                    <i class="bi bi-hash me-1"></i> <strong>ID:</strong> #${messageId}<br>
                    <i class="bi bi-archive me-1"></i> <strong>Status:</strong> Archived
                `;
                
                // Update the confirm button href
                confirmDeleteBtn.setAttribute('href', currentDeleteUrl);
                
                // Show the modal
                deleteMessageModal.show();
                
                return false;
            });
        });
        
        // Handle confirm delete button click
        if (confirmDeleteBtn) {
            confirmDeleteBtn.addEventListener('click', function(e) {
                e.preventDefault();
                
                // Show loading state
                const originalText = this.innerHTML;
                this.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Moving to Recycle Bin...';
                this.classList.add('disabled');
                
                // Close modal first
                deleteMessageModal.hide();
                
                // Navigate to delete URL after modal is hidden
                setTimeout(() => {
                    window.location.href = this.getAttribute('href');
                }, 300);
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

    function unarchiveMessage(messageId) {
        // Show loading state
        const button = event.target.closest('button');
        const originalContent = button.innerHTML;
        button.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
        button.disabled = true;
        
        fetch('ajax/update_status.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: `message_id=${messageId}&new_status=Read`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Remove the row from archived messages view
                const row = document.getElementById(`msg-row-${messageId}`);
                if (row) {
                    row.style.transition = 'opacity 0.3s ease';
                    row.style.opacity = '0';
                    setTimeout(() => {
                        row.remove();
                        
                        // Check if table is now empty
                        const tableBody = document.querySelector('tbody');
                        if (tableBody && tableBody.children.length === 0) {
                            location.reload(); 
                        }
                    }, 5000);
                }
                
                showAlert('success', 'Message unarchived successfully', 'bi-check-circle-fill');
                
            } else {
                showAlert('danger', 'Error: ' + data.message, 'bi-exclamation-triangle-fill');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showAlert('danger', 'An error occurred while unarchiving the message', 'bi-exclamation-triangle-fill');
        })
        .finally(() => {
            // Reset button state
            button.innerHTML = originalContent;
            button.disabled = false;
        });
    }
    
    function showAlert(type, message, icon = '') {
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
        alertDiv.innerHTML = `
            <i class="bi ${icon} me-2"></i>
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        const alertContainer = document.getElementById('alert-container');
        alertContainer.appendChild(alertDiv);
        
        // Auto-dismiss after 5 seconds
        setTimeout(() => {
            const closeButton = alertDiv.querySelector('.btn-close');
            if (closeButton && alertDiv.parentNode) {
                closeButton.click();
            }
        }, 5000);
    }
    </script>
</body>
</html>