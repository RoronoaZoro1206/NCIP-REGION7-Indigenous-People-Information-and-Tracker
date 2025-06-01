<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include "../config/database.php";

// Check if user is logged in
if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.php");
    exit();
}

// Check admin role - handle different session key possibilities
$user_role = $_SESSION["role"] ?? $_SESSION["user_role"] ?? null;
if (!$user_role || strtoupper($user_role) !== 'ADMIN') {
    $_SESSION['error_message'] = "Access denied. Admin privileges required.";
    header("Location: ../login.php");
    exit();
}

// Check if ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: index.php");
    exit();
}

$message_id = (int)$_GET['id'];

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

// Get message details - EXCLUDE SOFT DELETED MESSAGES (PostgreSQL Compatible)
try {
    $stmt = $pdo->prepare("SELECT * FROM contact_message 
                          WHERE contm_id = :message_id 
                          AND contm_deleted_at IS NULL");
    $stmt->bindParam(':message_id', $message_id, PDO::PARAM_INT);
    $stmt->execute();
    
    // Check if message exists and is not soft deleted
    if ($stmt->rowCount() === 0) {
        $_SESSION['error_message'] = "Message not found or has been moved to recycle bin.";
        header("Location: index.php");
        exit();
    }
    
    $message = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Mark as read if currently unread
    if (isset($message['contm_status']) && strtolower($message['contm_status']) == 'unread') {
        $update_stmt = $pdo->prepare("UPDATE contact_message SET contm_status = 'Read' WHERE contm_id = :message_id");
        $update_stmt->bindParam(':message_id', $message_id, PDO::PARAM_INT);
        $update_stmt->execute();
        
        // Update the status in our current data
        $message['contm_status'] = 'Read';
        
        // Log the status update
        try {
            $log_query = "INSERT INTO user_logs (user_id, log_type, log_details, log_timestamp) 
                         VALUES (:user_id, 'UPDATE_CONTACT_STATUS', :details, CURRENT_TIMESTAMP)";
            $log_stmt = $pdo->prepare($log_query);
            $details = "Marked contact message as read: {$message['contm_name']} (ID: {$message_id})";
            $log_stmt->bindParam(":user_id", $_SESSION["user_id"]);
            $log_stmt->bindParam(":details", $details);
            $log_stmt->execute();
        } catch (Exception $log_error) {
            error_log("Failed to log status update: " . $log_error->getMessage());
        }
    }
} catch (PDOException $e) {
    error_log("Error in message view: " . $e->getMessage());
    $_SESSION['error_message'] = "Database error occurred while loading the message.";
    header("Location: index.php");
    exit();
}

// Helper function to safely format dates (PostgreSQL Compatible)
function safe_date_format($date_string, $format = 'F d, Y') {
    if (empty($date_string) || $date_string === null || $date_string === '') {
        return '<span class="text-muted">Unknown date</span>';
    }
    
    try {
        // Handle PostgreSQL timestamp formats
        $date = new DateTime($date_string);
        return $date->format($format);
    } catch (Exception $e) {
        error_log("Date formatting error: " . $e->getMessage() . " for date: " . $date_string);
        return '<span class="text-muted">Invalid date</span>';
    }
}

// Helper function to safely format time (PostgreSQL Compatible)
function safe_time_format($date_string, $format = 'g:i A') {
    if (empty($date_string) || $date_string === null || $date_string === '') {
        return '<span class="text-muted">Unknown time</span>';
    }
    
    try {
        // Handle PostgreSQL timestamp formats
        $date = new DateTime($date_string);
        return $date->format($format);
    } catch (Exception $e) {
        error_log("Time formatting error: " . $e->getMessage() . " for date: " . $date_string);
        return '<span class="text-muted">Invalid time</span>';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Message - NCIP Admin</title>
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
        .message-content {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            white-space: pre-wrap;
            word-wrap: break-word;
        }
        .status-badge {
            font-size: 0.875rem;
        }
        .action-buttons .btn {
            margin-right: 0.5rem;
            margin-bottom: 0.5rem;
        }
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
            }
            .action-buttons {
                display: flex;
                flex-direction: column;
                gap: 0.5rem;
            }
            .action-buttons .btn {
                margin-right: 0;
                margin-bottom: 0;
            }
        }
        .message-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .info-section {
            background-color: #f8f9fa;
            border-left: 4px solid #0d6efd;
            padding: 15px;
            border-radius: 5px;
        }
    </style>
</head>
<body>
    <?php include '../includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1><i class="bi bi-envelope-open me-2"></i>Message Details</h1>
                <div class="btn-group">
                    <a href="index.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left me-1"></i>Back to Inbox
                    </a>
                    <a href="archived_messages.php" class="btn btn-outline-info">
                        <i class="bi bi-archive me-1"></i>Archived Messages
                    </a>
                </div>
            </div>

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
            
            <div class="card mb-4">
                <div class="card-header message-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="mb-1">
                                <i class="bi bi-person-circle me-2"></i>
                                From: <?php echo htmlspecialchars($message['contm_name'] ?? 'Unknown'); ?>
                            </h5>
                            <small class="opacity-75">
                                <i class="bi bi-key me-1"></i>Message ID: #<?php echo $message_id; ?>
                            </small>
                        </div>
                        <div>
                            <?php if (isset($message['contm_status'])): ?>
                                <?php if (strtolower($message['contm_status']) == 'read'): ?>
                                    <span class="badge bg-success status-badge">
                                        <i class="bi bi-check-circle me-1"></i>Read
                                    </span>
                                <?php elseif (strtolower($message['contm_status']) == 'archived'): ?>
                                    <span class="badge bg-secondary status-badge">
                                        <i class="bi bi-archive me-1"></i>Archived
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-danger status-badge">
                                        <i class="bi bi-exclamation-circle me-1"></i>Unread
                                    </span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="badge bg-warning status-badge">
                                    <i class="bi bi-question-circle me-1"></i>Unknown Status
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Contact Information Section -->
                    <div class="info-section mb-4">
                        <h6 class="mb-3">
                            <i class="bi bi-info-circle-fill me-2"></i>Contact Information
                        </h6>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <strong><i class="bi bi-envelope me-1"></i>Email:</strong><br>
                                <a href="mailto:<?php echo htmlspecialchars($message['contm_email'] ?? ''); ?>" class="text-decoration-none">
                                    <?php echo htmlspecialchars($message['contm_email'] ?? 'Not provided'); ?>
                                </a>
                            </div>
                            <div class="col-md-6 mb-3">
                                <strong><i class="bi bi-telephone me-1"></i>Phone:</strong><br>
                                <?php if (!empty($message['contm_phone'])): ?>
                                    <a href="tel:<?php echo htmlspecialchars($message['contm_phone']); ?>" class="text-decoration-none">
                                        <?php echo htmlspecialchars($message['contm_phone']); ?>
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted">Not provided</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-2">
                                <strong><i class="bi bi-calendar-event me-1"></i>Date Submitted:</strong><br>
                                <?php echo safe_date_format($message['contm_submission_date'] ?? null); ?>
                            </div>
                            <div class="col-md-6 mb-2">
                                <strong><i class="bi bi-clock me-1"></i>Time Submitted:</strong><br>
                                <?php echo safe_time_format($message['contm_submission_date'] ?? null); ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Message Content Section -->
                    <div class="mb-4">
                        <h6 class="mb-3">
                            <i class="bi bi-chat-text-fill me-2"></i>Message Content
                        </h6>
                        <div class="message-content">
                            <?php echo nl2br(htmlspecialchars($message['contm_message'] ?? 'No message content')); ?>
                        </div>
                    </div>
                    
                    <!-- Action Buttons -->
                    <div class="action-buttons mt-4">
                        <div class="d-flex flex-wrap align-items-center">
                            <a href="mailto:<?php echo htmlspecialchars($message['contm_email'] ?? ''); ?>" class="btn btn-primary">
                                <i class="bi bi-reply-fill me-1"></i>Reply via Email
                            </a>
                            
                            <?php if (isset($message['contm_status']) && strtolower($message['contm_status']) != 'archived'): ?>
                                <button type="button" class="btn btn-secondary" onclick="updateStatus(<?php echo $message_id; ?>, 'Archived')">
                                    <i class="bi bi-archive me-1"></i>Archive
                                </button>
                            <?php elseif (isset($message['contm_status']) && strtolower($message['contm_status']) == 'archived'): ?>
                                <button type="button" class="btn btn-info" onclick="updateStatus(<?php echo $message_id; ?>, 'Read')">
                                    <i class="bi bi-arrow-counterclockwise me-1"></i>Unarchive
                                </button>
                            <?php endif; ?>
                            
                            <a href="delete.php?id=<?php echo $message_id; ?>&action=soft_delete" 
                               class="btn btn-warning delete-message-btn" 
                               data-message-id="<?php echo $message_id; ?>"
                               data-message-name="<?php echo htmlspecialchars($message['contm_name'] ?? 'Unknown'); ?>"
                               data-message-email="<?php echo htmlspecialchars($message['contm_email'] ?? 'Not provided'); ?>">
                                <i class="bi bi-recycle me-1"></i>Move to Recycle Bin
                            </a>
                        </div>
                    </div>
                    
                    <!-- Additional Actions -->
                    <div class="mt-3 pt-3 border-top">
                        <small class="text-muted">
                            <i class="bi bi-info-circle me-1"></i>
                            <strong>Note:</strong> Moving to recycle bin will hide this message from the contacts list but allow restoration later if needed.
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Delete Message Confirmation Modal -->
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
                    <h6 class="text-center mb-3">Are you sure you want to move this message to the recycle bin?</h6>
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
        
        // Handle delete message modal
        const deleteMessageModal = new bootstrap.Modal(document.getElementById('deleteMessageModal'));
        const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
        const messageDetailsContainer = document.getElementById('messageDetailsContainer');
        let currentDeleteUrl = '';
        
        // Add click event listener to delete button
        const deleteBtn = document.querySelector('.delete-message-btn');
        if (deleteBtn) {
            deleteBtn.addEventListener('click', function(e) {
                e.preventDefault(); // Prevent immediate navigation
                e.stopPropagation(); // Stop event bubbling
                
                console.log('Delete button clicked, preventing default navigation'); // Debug
                
                // Get message data from data attributes
                const messageId = this.getAttribute('data-message-id');
                const messageName = this.getAttribute('data-message-name');
                const messageEmail = this.getAttribute('data-message-email');
                const deleteUrl = this.getAttribute('href');
                
                console.log('Message data:', { messageId, messageName, messageEmail, deleteUrl }); // Debug
                
                // Store the URL for later use
                currentDeleteUrl = deleteUrl;
                
                // Populate message details in modal
                messageDetailsContainer.innerHTML = `
                    <strong>Message Details:</strong><br>
                    <i class="bi bi-person-fill me-1"></i> <strong>From:</strong> ${messageName}<br>
                    <i class="bi bi-envelope-fill me-1"></i> <strong>Email:</strong> ${messageEmail}<br>
                    <i class="bi bi-hash me-1"></i> <strong>ID:</strong> #${messageId}
                `;
                
                // Update the confirm button href
                confirmDeleteBtn.setAttribute('href', deleteUrl);
                
                console.log('About to show modal'); // Debug
                
                // Show the modal
                deleteMessageModal.show();
                
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
                this.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Moving to Recycle Bin...';
                this.classList.add('disabled');
                
                // Close modal first
                deleteMessageModal.hide();
                
                // Navigate to delete URL after modal is hidden
                setTimeout(() => {
                    console.log('Navigating to:', currentDeleteUrl); // Debug
                    window.location.href = currentDeleteUrl;
                }, 300); // Reduced delay to make it feel faster
            });
        }
        
        // Reset button state when modal is hidden
        document.getElementById('deleteMessageModal').addEventListener('hidden.bs.modal', function() {
            if (confirmDeleteBtn) {
                confirmDeleteBtn.innerHTML = '<i class="bi bi-recycle me-1"></i>Yes, Move to Recycle Bin';
                confirmDeleteBtn.classList.remove('disabled');
            }
        });
        
        // Add smooth modal animations
        const modal = document.getElementById('deleteMessageModal');
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
        const deleteLinks = document.querySelectorAll('.delete-message-btn');
        deleteLinks.forEach(function(link) {
            // Remove any existing onclick attributes that might interfere
            link.removeAttribute('onclick');
        });
    });

    function updateStatus(messageId, newStatus) {
        const button = event.target.closest('button');
        const originalContent = button.innerHTML;
        button.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Updating...';
        button.disabled = true;
        
        fetch('ajax/update_status.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: `message_id=${messageId}&new_status=${newStatus}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAlert('success', `Message ${newStatus.toLowerCase()} successfully`, 'bi-check-circle-fill');
                
                // Update the status badge
                const statusBadge = document.querySelector('.status-badge');
                if (statusBadge) {
                    if (newStatus === 'Archived') {
                        statusBadge.className = 'badge bg-secondary status-badge';
                        statusBadge.innerHTML = '<i class="bi bi-archive me-1"></i>Archived';
                        
                        // Update button to show unarchive option
                        button.className = 'btn btn-info';
                        button.setAttribute('onclick', `updateStatus(${messageId}, 'Read')`);
                        button.innerHTML = '<i class="bi bi-arrow-counterclockwise me-1"></i>Unarchive';
                    } else if (newStatus === 'Read') {
                        statusBadge.className = 'badge bg-success status-badge';
                        statusBadge.innerHTML = '<i class="bi bi-check-circle me-1"></i>Read';
                        
                        // Update button to show archive option
                        button.className = 'btn btn-secondary';
                        button.setAttribute('onclick', `updateStatus(${messageId}, 'Archived')`);
                        button.innerHTML = '<i class="bi bi-archive me-1"></i>Archive';
                    }
                }
            } else {
                showAlert('danger', 'Error: ' + data.message, 'bi-exclamation-triangle-fill');
                button.innerHTML = originalContent;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showAlert('danger', 'An error occurred while updating the message status', 'bi-exclamation-triangle-fill');
            button.innerHTML = originalContent;
        })
        .finally(() => {
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
        
        const container = document.querySelector('.container-fluid');
        const firstCard = container.querySelector('.card');
        container.insertBefore(alertDiv, firstCard);
        
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