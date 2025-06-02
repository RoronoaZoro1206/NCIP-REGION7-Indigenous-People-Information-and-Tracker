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

// Initialize status_counts with default values EARLY to prevent undefined index errors
$status_counts = [
    'all' => 0,
    'unread' => 0,
    'read' => 0,
    'archived' => 0
];

// Handle status filter
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

// Handle search query
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Get status counts for filters - EXCLUDE SOFT DELETED MESSAGES (PostgreSQL compatible)
try {
    // PostgreSQL-compatible soft delete check - FIXED
    $count_status_query = "SELECT contm_status, COUNT(*) as count
                          FROM contact_message
                          WHERE contm_deleted_at IS NULL
                          GROUP BY contm_status";
    $count_status_stmt = $pdo->query($count_status_query);
    
    $total_inbox_messages = 0; // All except archived
    
    while ($row = $count_status_stmt->fetch(PDO::FETCH_ASSOC)) {
        $status = strtolower(trim($row['contm_status']));
        $count = (int)$row['count'];
        
        // Set individual status counts
        if (isset($status_counts[$status])) {
            $status_counts[$status] = $count;
        }
        
        // Add to inbox count (everything except archived)
        if ($status !== 'archived') {
            $total_inbox_messages += $count;
        }
    }
    
    // Set the "all" count to show inbox messages (excluding archived)
    $status_counts['all'] = $total_inbox_messages;
    
} catch (PDOException $e) {
    error_log("Error fetching status counts: " . $e->getMessage());
    // Keep default values - already initialized above
}

// Build query conditions - FIXED FOR PostgreSQL
$conditions = ["contm_deleted_at IS NULL"]; // PostgreSQL-compatible - REMOVED EMPTY STRING CHECK
$params = [];

// Add status filter condition
if (!empty($status_filter)) {
    $conditions[] = "contm_status = :status_filter";
    $params[':status_filter'] = $status_filter;
} else {
    // Don't show archived messages in main view unless specifically filtering for them
    $conditions[] = "contm_status != 'Archived'";
}

// Add search condition
if (!empty($search)) {
    $conditions[] = "(contm_name ILIKE :search_term OR contm_email ILIKE :search_term OR contm_message ILIKE :search_term)";
    $params[':search_term'] = '%' . $search . '%';
}

// Count total messages for pagination
try {
    $count_query = "SELECT COUNT(*) as total FROM contact_message WHERE " . implode(' AND ', $conditions);
    $count_stmt = $pdo->prepare($count_query);
    
    foreach ($params as $key => $value) {
        $count_stmt->bindValue($key, $value);
    }
    
    $count_stmt->execute();
    $row = $count_stmt->fetch(PDO::FETCH_ASSOC);
    $total_records = (int)$row['total'];
    
} catch (PDOException $e) {
    error_log("Error counting messages: " . $e->getMessage());
    $total_records = 0;
}

// Pagination setup
$records_per_page = 10;
$total_pages = ceil($total_records / $records_per_page);
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $records_per_page;

// Get all contact messages
$messages = [];
try {
    $query = "SELECT * FROM contact_message WHERE " . implode(' AND ', $conditions) . "
              ORDER BY contm_submission_date DESC
              LIMIT :limit OFFSET :offset";
    
    $stmt = $pdo->prepare($query);
    
    // Bind all parameters
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    
    // Bind pagination parameters
    $stmt->bindValue(':limit', (int)$records_per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
    
    $stmt->execute();
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Error fetching messages: " . $e->getMessage());
    $_SESSION['error_message'] = "Error retrieving messages: " . $e->getMessage();
}

// Handle delete request (fallback for non-JS) - UPDATE TO USE SOFT DELETE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_message'])) {
    $message_id = $_POST['message_id'];
    
    try {
        // Use soft delete instead of permanent delete - PostgreSQL compatible
        $soft_delete_stmt = $pdo->prepare("UPDATE contact_message 
                                          SET contm_deleted_at = CURRENT_TIMESTAMP, contm_deleted_by = ? 
                                          WHERE contm_id = ?");
        $result = $soft_delete_stmt->execute([$_SESSION["user_id"], $message_id]);
        
        if ($result && $soft_delete_stmt->rowCount() > 0) {
            // Log the soft deletion activity
            try {
                $log_query = "INSERT INTO user_logs (user_id, log_type, log_details, log_timestamp) 
                             VALUES (:user_id, 'SOFT_DELETE_CONTACT_MESSAGE', :details, CURRENT_TIMESTAMP)";
                $log_stmt = $pdo->prepare($log_query);
                $details = "Moved contact message to recycle bin (ID: {$message_id})";
                $log_stmt->bindParam(":user_id", $_SESSION["user_id"]);
                $log_stmt->bindParam(":details", $details);
                $log_stmt->execute();
            } catch (Exception $log_error) {
                error_log("Failed to log soft deletion activity: " . $log_error->getMessage());
            }
            
            $_SESSION['success_message'] = "Message moved to recycle bin successfully!";
        } else {
            $_SESSION['error_message'] = "Failed to move message to recycle bin.";
        }
        
        header("Location: index.php");
        exit();
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Error moving message to recycle bin: " . $e->getMessage();
    }
}

// Handle status update (fallback for non-JS)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $message_id = $_POST['message_id'];
    $new_status = $_POST['new_status'];
    
    try {
        $update_stmt = $pdo->prepare("UPDATE contact_message SET contm_status = :new_status WHERE contm_id = :message_id");
        $update_stmt->bindParam(":new_status", $new_status, PDO::PARAM_STR);
        $update_stmt->bindParam(":message_id", $message_id, PDO::PARAM_INT);
        $update_stmt->execute();
        
        // Log the status update activity
        try {
            $log_query = "INSERT INTO user_logs (user_id, log_type, log_details, log_timestamp) 
                         VALUES (:user_id, 'UPDATE_CONTACT_STATUS', :details, CURRENT_TIMESTAMP)";
            $log_stmt = $pdo->prepare($log_query);
            $details = "Updated contact message status to {$new_status} (ID: {$message_id})";
            $log_stmt->bindParam(":user_id", $_SESSION["user_id"]);
            $log_stmt->bindParam(":details", $details);
            $log_stmt->execute();
        } catch (Exception $log_error) {
            error_log("Failed to log status update activity: " . $log_error->getMessage());
        }
        
        $_SESSION['success_message'] = "Status updated successfully!";
        header("Location: index.php" . (empty($_GET) ? "" : "?" . http_build_query($_GET)));
        exit();
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Error updating status: " . $e->getMessage();
    }
}

// Helper function to safely format dates for PostgreSQL
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

// Helper function to safely format time for PostgreSQL
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
    <title>Contact Messages - NCIP Admin</title>
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
        .card-dashboard {
            border: none;
            border-radius: 10px;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }
        .message-preview {
            max-height: 80px;
            overflow: hidden;
            text-overflow: ellipsis;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            line-clamp: 3;
            -webkit-box-orient: vertical;
        }
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
            }
        }
        .btn-group .btn {
            margin-right: 2px;
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
                <h1><i class="bi bi-envelope me-2"></i>Contact Messages</h1>
                <div class="btn-group">
                    <a href="archived_messages.php" class="btn btn-secondary">
                        <i class="bi bi-archive me-1"></i>Archived Messages 
                        <span class="badge bg-light text-dark"><?php echo isset($status_counts['archived']) ? $status_counts['archived'] : 0; ?></span>
                    </a>
                    <a href="export.php" class="btn btn-success">
                        <i class="bi bi-file-earmark-excel me-1"></i>Export to Excel
                    </a>
                </div>
            </div>

            <!-- Alert Container for AJAX responses -->
            <div id="alert-container">
                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle-fill me-2"></i>
                        <?php echo $_SESSION['success_message']; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php unset($_SESSION['success_message']); ?>
                <?php endif; ?>

                <?php if (isset($_SESSION['error_message'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        <?php echo $_SESSION['error_message']; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
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
            </div>
            
            <div class="card card-dashboard mb-4">
                <div class="card-body">
                    <div class="row mb-3">
                        <!-- Status filter buttons -->
                        <div class="col-md-8">
                            <div class="btn-group" role="group">
                                <a href="index.php" class="btn btn-outline-secondary <?php echo empty($status_filter) ? 'active' : ''; ?>">
                                    All <span class="badge bg-secondary"><?php echo isset($status_counts['all']) ? $status_counts['all'] : 0; ?></span>
                                </a>
                                <a href="index.php?status=Unread<?php echo !empty($search) ? '&search='.$search : ''; ?>" 
                                   class="btn btn-outline-danger <?php echo $status_filter === 'Unread' ? 'active' : ''; ?>">
                                    Unread <span class="badge bg-danger"><?php echo isset($status_counts['unread']) ? $status_counts['unread'] : 0; ?></span>
                                </a>
                                <a href="index.php?status=Read<?php echo !empty($search) ? '&search='.$search : ''; ?>" 
                                   class="btn btn-outline-primary <?php echo $status_filter === 'Read' ? 'active' : ''; ?>">
                                    Read <span class="badge bg-primary"><?php echo isset($status_counts['read']) ? $status_counts['read'] : 0; ?></span>
                                </a>
                            </div>
                        </div>

                        <!-- Search box -->
                        <div class="col-md-4">
                            <form class="d-flex">
                                <?php if (!empty($status_filter)): ?>
                                    <input type="hidden" name="status" value="<?php echo htmlspecialchars($status_filter); ?>">
                                <?php endif; ?>
                                <input class="form-control me-2" type="search" name="search" autocomplete="off" placeholder="Search by name, email, or content" 
                                       value="<?php echo htmlspecialchars($search); ?>">
                                <button class="btn btn-outline-primary" type="submit">
                                    <i class="bi bi-search"></i>
                                </button>
                                <?php if (!empty($search)): ?>
                                    <a href="<?php echo empty($status_filter) ? 'index.php' : "index.php?status=".urlencode($status_filter); ?>" class="btn btn-outline-secondary ms-2">
                                        <i class="bi bi-x-circle"></i>
                                    </a>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 80px;">ID</th>
                                    <th style="width: 150px;">Sender</th>
                                    <th style="width: 200px;">Email</th>
                                    <th style="width: 120px;">Phone</th>
                                    <th>Message</th>
                                    <th style="width: 150px;">Date</th>
                                    <th style="width: 100px;">Status</th>
                                    <th style="width: 200px;" class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($messages) > 0): ?>
                                    <?php foreach ($messages as $msg): ?>
                                    <tr id="msg-row-<?php echo $msg['contm_id']; ?>" class="<?php echo $msg['contm_status'] === 'Unread' ? 'table-active' : ''; ?>">
                                        <td>
                                            <span class="badge bg-secondary">#<?php echo $msg['contm_id']; ?></span>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($msg['contm_name']); ?></strong>
                                        </td>
                                        <td>
                                            <a href="mailto:<?php echo htmlspecialchars($msg['contm_email']); ?>" class="text-decoration-none">
                                                <i class="bi bi-envelope me-1"></i>
                                                <?php echo htmlspecialchars($msg['contm_email']); ?>
                                            </a>
                                        </td>
                                        <td>
                                            <?php if (!empty($msg['contm_phone'])): ?>
                                                <a href="tel:<?php echo htmlspecialchars($msg['contm_phone']); ?>" class="text-decoration-none">
                                                    <i class="bi bi-telephone me-1"></i>
                                                    <?php echo htmlspecialchars($msg['contm_phone']); ?>
                                                </a>
                                            <?php else: ?>
                                                <span class="text-muted">N/A</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="message-preview">
                                                <?php echo htmlspecialchars($msg['contm_message']); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <small class="text-muted">
                                                <i class="bi bi-calendar3 me-1"></i>
                                                <?php echo safe_date_format($msg['contm_submission_date']); ?>
                                                <br>
                                                <i class="bi bi-clock me-1"></i>
                                                <?php echo safe_time_format($msg['contm_submission_date']); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <?php 
                                            $badge_class = '';
                                            $status = $msg['contm_status'];
                                            switch ($status) {
                                                case 'Unread':
                                                    $badge_class = 'bg-danger';
                                                    break;
                                                case 'Read':
                                                    $badge_class = 'bg-primary';
                                                    break;
                                                case 'Archived':
                                                    $badge_class = 'bg-dark';
                                                    break;
                                                default:
                                                    $badge_class = 'bg-secondary';
                                            }
                                            ?>
                                            <span id="status-badge-<?php echo $msg['contm_id']; ?>" class="badge <?php echo $badge_class; ?>">
                                                <?php echo htmlspecialchars($msg['contm_status']); ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <div class="btn-group" role="group">
                                                <a href="view.php?id=<?php echo $msg['contm_id']; ?>" 
                                                   class="btn btn-sm btn-outline-info" 
                                                   title="View Message">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                                <button type="button" 
                                                        class="btn btn-sm btn-outline-success" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#replyModal<?php echo $msg['contm_id']; ?>"
                                                        title="Reply">
                                                    <i class="bi bi-reply"></i>
                                                </button>
                                                <button type="button" 
                                                        class="btn btn-sm btn-outline-primary" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#statusModal<?php echo $msg['contm_id']; ?>"
                                                        title="Change Status">
                                                    <i class="bi bi-check2-circle"></i>
                                                </button>
                                                <button type="button" 
                                                        class="btn btn-sm btn-outline-danger delete-message-btn" 
                                                        data-message-id="<?php echo $msg['contm_id']; ?>"
                                                        data-message-name="<?php echo htmlspecialchars($msg['contm_name']); ?>"
                                                        data-message-email="<?php echo htmlspecialchars($msg['contm_email']); ?>"
                                                        title="Move to Recycle Bin">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </div>
                                            
                                            <!-- Reply Modal -->
                                            <div class="modal fade" id="replyModal<?php echo $msg['contm_id']; ?>" tabindex="-1" aria-labelledby="replyModalLabel<?php echo $msg['contm_id']; ?>" aria-hidden="true">
                                                <div class="modal-dialog modal-lg">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title" id="replyModalLabel<?php echo $msg['contm_id']; ?>">
                                                                <i class="bi bi-reply me-2"></i>Reply to: <?php echo htmlspecialchars($msg['contm_name']); ?>
                                                            </h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <div class="mb-3">
                                                                <label class="form-label"><strong>From:</strong></label>
                                                                <p><?php echo htmlspecialchars($msg['contm_name']); ?> (<?php echo htmlspecialchars($msg['contm_email']); ?>)</p>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label class="form-label"><strong>Original Message:</strong></label>
                                                                <div class="card card-body bg-light mb-3">
                                                                    <?php echo nl2br(htmlspecialchars($msg['contm_message'])); ?>
                                                                </div>
                                                            </div>
                                                            <div class="d-flex justify-content-center mt-4">
                                                                <a href="mailto:<?php echo htmlspecialchars($msg['contm_email']); ?>" class="btn btn-primary">
                                                                    <i class="bi bi-reply-fill me-1"></i>Reply via Email
                                                                </a>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <!-- Status Change Modal -->
                                            <div class="modal fade" id="statusModal<?php echo $msg['contm_id']; ?>" tabindex="-1" aria-labelledby="statusModalLabel<?php echo $msg['contm_id']; ?>" aria-hidden="true">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title" id="statusModalLabel<?php echo $msg['contm_id']; ?>">
                                                                <i class="bi bi-check2-circle me-2"></i>Change Message Status
                                                            </h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                        </div>
                                                        <form class="update-status-form">
                                                            <div class="modal-body">
                                                                <input type="hidden" name="message_id" value="<?php echo $msg['contm_id']; ?>">
                                                                <div class="mb-3">
                                                                    <label for="new_status<?php echo $msg['contm_id']; ?>" class="form-label">New Status</label>
                                                                    <select class="form-select" id="new_status<?php echo $msg['contm_id']; ?>" name="new_status" required>
                                                                        <option value="">Select Status</option>
                                                                        <option value="Unread" <?php echo $msg['contm_status'] == 'Unread' ? 'selected' : ''; ?>>Unread</option>
                                                                        <option value="Read" <?php echo $msg['contm_status'] == 'Read' ? 'selected' : ''; ?>>Read</option>
                                                                        <option value="Archived" <?php echo $msg['contm_status'] == 'Archived' ? 'selected' : ''; ?>>Archived</option>
                                                                    </select>
                                                                </div>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                                <button type="submit" class="btn btn-primary">Update Status</button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="8" class="text-center p-5">
                                            <i class="bi bi-inbox display-1 text-secondary"></i>
                                            <h4 class="mt-3 text-muted">No messages found</h4>
                                            <?php if (!empty($search)): ?>
                                                <p class="mb-3">No messages match your search criteria.</p>
                                                <a href="index.php" class="btn btn-outline-primary">
                                                    <i class="bi bi-arrow-clockwise me-1"></i>Clear Search
                                                </a>
                                            <?php else: ?>
                                                <p class="mb-3">No contact messages available.</p>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                    <nav aria-label="Page navigation" class="mt-4">
                        <ul class="pagination justify-content-center">
                            <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                <a class="page-link" href="<?php echo ($page <= 1) ? '#' : '?page='.($page-1).(empty($status_filter) ? '' : '&status='.urlencode($status_filter)).(empty($search) ? '' : '&search='.urlencode($search)); ?>">
                                    <span aria-hidden="true">&laquo;</span> Previous
                                </a>
                            </li>
                            
                            <?php
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $start_page + 4);
                            if ($end_page - $start_page < 4 && $start_page > 1) {
                                $start_page = max(1, $end_page - 4);
                            }
                            
                            for($i = $start_page; $i <= $end_page; $i++): 
                            ?>
                                <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?><?php echo empty($status_filter) ? '' : '&status='.urlencode($status_filter); ?><?php echo empty($search) ? '' : '&search='.urlencode($search); ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                            
                            <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                                <a class="page-link" href="<?php echo ($page >= $total_pages) ? '#' : '?page='.($page+1).(empty($status_filter) ? '' : '&status='.urlencode($status_filter)).(empty($search) ? '' : '&search='.urlencode($search)); ?>">
                                    Next <span aria-hidden="true">&raquo;</span>
                                </a>
                            </li>
                        </ul>
                    </nav>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Global Delete Message Confirmation Modal (Similar to view.php) -->
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

            // AJAX for status updates
            document.querySelectorAll('.update-status-form').forEach(form => {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    const formData = new FormData(this);
                    const messageId = formData.get('message_id');
                    const newStatus = formData.get('new_status');
                    
                    // Show loading state
                    const submitBtn = this.querySelector('button[type="submit"]');
                    const originalText = submitBtn.innerHTML;
                    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Updating...';
                    submitBtn.disabled = true;
                    
                    fetch('ajax/update_status.php', {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Update the status badge
                            const statusBadge = document.querySelector(`#status-badge-${messageId}`);
                            statusBadge.textContent = newStatus;
                            
                            // Update badge class
                            let badgeClass = 'bg-secondary';
                            switch (newStatus) {
                                case 'Unread': badgeClass = 'bg-danger'; break;
                                case 'Read': badgeClass = 'bg-primary'; break;
                                case 'Archived': badgeClass = 'bg-dark'; break;
                            }
                            statusBadge.className = `badge ${badgeClass}`;
                            
                            // Update row highlighting
                            const row = document.querySelector(`#msg-row-${messageId}`);
                            if (newStatus === 'Unread') {
                                row.classList.add('table-active');
                            } else {
                                row.classList.remove('table-active');
                            }
                            
                            // Close the modal
                            const modal = bootstrap.Modal.getInstance(document.querySelector(`#statusModal${messageId}`));
                            modal.hide();
                            
                            // Show success message
                            showAlert('success', data.message, 'bi-check-circle-fill');
                        } else {
                            showAlert('danger', 'Error: ' + data.message, 'bi-exclamation-triangle-fill');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        showAlert('danger', 'An error occurred while updating status', 'bi-exclamation-triangle-fill');
                    })
                    .finally(() => {
                        // Reset button state
                        submitBtn.innerHTML = originalText;
                        submitBtn.disabled = false;
                    });
                });
            });
            
            // Handle delete message modal (Similar to view.php)
            const deleteMessageModal = new bootstrap.Modal(document.getElementById('deleteMessageModal'));
            const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
            const messageDetailsContainer = document.getElementById('messageDetailsContainer');
            let currentDeleteUrl = '';
            
            // Add click event listener to all delete buttons
            document.querySelectorAll('.delete-message-btn').forEach(function(btn) {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    // Get message data from data attributes
                    const messageId = this.getAttribute('data-message-id');
                    const messageName = this.getAttribute('data-message-name');
                    const messageEmail = this.getAttribute('data-message-email');
                    
                    // Build delete URL
                    currentDeleteUrl = `delete.php?id=${messageId}&action=soft_delete`;
                    
                    // Populate message details in modal
                    messageDetailsContainer.innerHTML = `
                        <strong>Message Details:</strong><br>
                        <i class="bi bi-person-fill me-1"></i> <strong>From:</strong> ${messageName}<br>
                        <i class="bi bi-envelope-fill me-1"></i> <strong>Email:</strong> ${messageEmail}<br>
                        <i class="bi bi-hash me-1"></i> <strong>ID:</strong> #${messageId}
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
                        window.location.href = currentDeleteUrl;
                    }, 300);
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
            }
            
            // Helper function to show alerts
            function showAlert(type, message, icon = '') {
                const alertDiv = document.createElement('div');
                alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
                alertDiv.innerHTML = `
                    <i class="bi ${icon} me-2"></i>
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                `;
                
                const alertContainer = document.querySelector('#alert-container');
                alertContainer.appendChild(alertDiv);
                
                // Auto dismiss after 5 seconds
                setTimeout(() => {
                    const closeButton = alertDiv.querySelector('.btn-close');
                    if (closeButton && alertDiv.parentNode) {
                        closeButton.click();
                    }
                }, 5000);
            }
        });
    </script>
</body>
</html>