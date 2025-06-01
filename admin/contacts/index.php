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

// Handle status filter
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$status_condition = "";
if (!empty($status_filter)) {
    $status_condition = "AND c.contm_status = :status_filter";
}

// Handle search query
$search = isset($_GET['search']) ? $_GET['search'] : '';
$search_condition = "";
if (!empty($search)) {
    $search_condition = "AND (c.contm_name LIKE :search_term OR c.contm_email LIKE :search_term OR 
                        c.contm_message LIKE :search_term)";
}

// Count total messages for pagination
try {
    $count_query = "SELECT COUNT(*) as total 
                   FROM contact_message c
                   WHERE 1=1 ";
    
    if (!empty($status_condition) || !empty($search_condition)) {
        $count_stmt = $pdo->prepare($count_query . $status_condition . $search_condition);
        
        if (!empty($status_filter)) {
            $count_stmt->bindValue(':status_filter', $status_filter);
        }
        
        if (!empty($search)) {
            $search_param = '%' . $search . '%';
            $count_stmt->bindValue(':search_term', $search_param);
        }
        
        $count_stmt->execute();
    } else {
        $count_stmt = $pdo->query($count_query);
    }
    
    $row = $count_stmt->fetch(PDO::FETCH_ASSOC);
    $total_records = $row['total'];
    
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
    // PostgreSQL uses LIMIT x OFFSET y instead of LIMIT y, x
    $query = "SELECT * FROM contact_message c
              WHERE 1=1 ";
    
    if ($status_filter !== 'Archived') {
        $query .= " AND c.contm_status != 'Archived' ";
    }
    
    // Add the rest of your conditions
    $query .= $status_condition . " " . $search_condition . "
              ORDER BY c.contm_submission_date DESC
              LIMIT :limit OFFSET :offset";
    
    $stmt = $pdo->prepare($query);
    $stmt->bindValue(':limit', (int)$records_per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
    
    if (!empty($status_filter)) {
        $stmt->bindValue(':status_filter', $status_filter);
    }
    
    if (!empty($search)) {
        $search_param = '%' . $search . '%';
        $stmt->bindValue(':search_term', $search_param);
    }
    
    $stmt->execute();
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Error fetching messages: " . $e->getMessage());
    $_SESSION['error_message'] = "Error retrieving messages: Database error";
}

// Get status counts for filters
try {
    // This should calculate "all" excluding archived
    $count_query_inbox = "SELECT COUNT(*) as total 
                       FROM contact_message 
                       WHERE contm_status != 'Archived'";
    $count_stmt_inbox = $pdo->query($count_query_inbox);
    $inbox_count = $count_stmt_inbox->fetchColumn();
    
    // Set the correct counts
    $status_counts = [
        'all' => $inbox_count, 
        'unread' => 0,
        'read' => 0,
        'replied' => 0,
        'archived' => 0
    ];
    
    // Get detailed counts by status
    $count_status_query = "SELECT contm_status, COUNT(*) as count
                          FROM contact_message
                          GROUP BY contm_status";
    $count_status_stmt = $pdo->query($count_status_query);
    while ($row = $count_status_stmt->fetch(PDO::FETCH_ASSOC)) {
        $status = strtolower($row['contm_status']);
        $status_counts[$status] = $row['count'];
    }
} catch (PDOException $e) {
    error_log("Error fetching status counts: " . $e->getMessage());
}

// Handle delete request (fallback for non-JS)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_message'])) {
    $message_id = $_POST['message_id'];
    
    try {
        $delete_stmt = $pdo->prepare("DELETE FROM contact_message WHERE contm_id = :message_id");
        $delete_stmt->bindParam(":message_id", $message_id, PDO::PARAM_INT);
        $delete_stmt->execute();
        
        $_SESSION['success_message'] = "Message deleted successfully!";
        header("Location: index.php");
        exit();
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Error deleting message: " . $e->getMessage();
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
        
        $_SESSION['success_message'] = "Status updated successfully!";
        header("Location: index.php" . (empty($_GET) ? "" : "?" . http_build_query($_GET)));
        exit();
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Error updating status: " . $e->getMessage();
    }
}

// Handle reply to message (fallback for non-JS)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reply_message'])) {
    $message_id = $_POST['message_id'];
    $reply_content = $_POST['reply_content'];
    $recipient_email = $_POST['recipient_email'];
    $subject = $_POST['subject'] ?? 'Re: Your message to NCIP';
    
    // Send email (you may need to configure mail settings)
    $headers = "From: admin@ncip.gov.ph\r\n";
    $headers .= "Reply-To: admin@ncip.gov.ph\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    
    $email_sent = mail($recipient_email, $subject, $reply_content, $headers);
    
    if ($email_sent) {
        // Update message status to replied
        try {
            $update_stmt = $pdo->prepare("UPDATE contact_message SET contm_status = 'Replied', contm_reply = :reply_content, contm_reply_date = CURRENT_TIMESTAMP WHERE contm_id = :message_id");
            $update_stmt->bindParam(":reply_content", $reply_content, PDO::PARAM_STR);
            $update_stmt->bindParam(":message_id", $message_id, PDO::PARAM_INT);
            $update_stmt->execute();
            
            $_SESSION['success_message'] = "Reply sent successfully!";
        } catch (PDOException $e) {
            $_SESSION['error_message'] = "Error updating message: " . $e->getMessage();
        }
    } else {
        $_SESSION['error_message'] = "Error sending email reply. Please check your mail configuration.";
    }
    
    header("Location: index.php" . (empty($_GET) ? "" : "?" . http_build_query($_GET)));
    exit();
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
    </style>
</head>
<body>
    <!-- Sidebar -->
    <?php include '../includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1>Contact Messages</h1>
                <div>
                    <a href="archived_messages.php" class="btn btn-secondary me-2">
                        <i class="bi bi-archive"></i> Archived Messages 
                        <span class="badge bg-light text-dark"><?php echo $status_counts['archived']; ?></span>
                    </a>
                    <a href="export.php" class="btn btn-success">
                        <i class="bi bi-file-earmark-excel"></i> Export to Excel
                    </a>
                </div>
            </div>

            <!-- Alert Container for AJAX responses -->
            <div id="alert-container">
                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $_SESSION['success_message']; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php unset($_SESSION['success_message']); ?>
                <?php endif; ?>

                <?php if (isset($_SESSION['error_message'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $_SESSION['error_message']; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php unset($_SESSION['error_message']); ?>
                <?php endif; ?>
            </div>
            
            <div class="card card-dashboard mb-4">
                <div class="card-body">
                    <div class="row mb-3">
                        <!-- Status filter buttons -->
                        <div class="col-md-6">
                            <div class="btn-group" role="group">
                                <a href="index.php" class="btn btn-outline-secondary <?php echo empty($status_filter) ? 'active' : ''; ?>">
                                    All <span class="badge bg-secondary"><?php echo $status_counts['all']; ?></span>
                                </a>
                                <a href="index.php?status=Unread<?php echo !empty($search) ? '&search='.$search : ''; ?>" 
                                   class="btn btn-outline-danger <?php echo $status_filter === 'Unread' ? 'active' : ''; ?>">
                                    Unread <span class="badge bg-danger"><?php echo $status_counts['unread']; ?></span>
                                </a>
                                <a href="index.php?status=Read<?php echo !empty($search) ? '&search='.$search : ''; ?>" 
                                   class="btn btn-outline-primary <?php echo $status_filter === 'Read' ? 'active' : ''; ?>">
                                    Read <span class="badge bg-primary"><?php echo $status_counts['read']; ?></span>
                                </a>
                                <a href="index.php?status=Replied<?php echo !empty($search) ? '&search='.$search : ''; ?>" 
                                   class="btn btn-outline-success <?php echo $status_filter === 'Replied' ? 'active' : ''; ?>">
                                    Replied <span class="badge bg-success"><?php echo $status_counts['replied']; ?></span>
                                </a>
                            </div>
                        </div>

                        <!-- Search box -->
                        <div class="col-md-6">
                            <form class="d-flex">
                                <?php if (!empty($status_filter)): ?>
                                    <input type="hidden" name="status" value="<?php echo $status_filter; ?>">
                                <?php endif; ?>
                                <input class="form-control me-2" type="search" name="search" autocomplete="off" placeholder="Search by name, email, or content" 
                                       value="<?php echo htmlspecialchars($search); ?>">
                                <button class="btn btn-outline-primary" type="submit">Search</button>
                                <?php if (!empty($search)): ?>
                                    <a href="<?php echo empty($status_filter) ? 'index.php' : "index.php?status=$status_filter"; ?>" class="btn btn-outline-secondary ms-2">Clear</a>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>ID</th>
                                    <th>Sender</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>Message</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($messages) > 0): ?>
                                    <?php foreach ($messages as $msg): ?>
                                    <tr id="msg-row-<?php echo $msg['contm_id']; ?>" class="<?php echo $msg['contm_status'] === 'Unread' ? 'table-active' : ''; ?>">
                                        <td><?php echo $msg['contm_id']; ?></td>
                                        <td><?php echo htmlspecialchars($msg['contm_name']); ?></td>
                                        <td><?php echo htmlspecialchars($msg['contm_email']); ?></td>
                                        <td><?php echo htmlspecialchars($msg['contm_phone'] ?? 'N/A'); ?></td>
                                        <td>
                                            <div class="message-preview">
                                                <?php echo htmlspecialchars($msg['contm_message']); ?>
                                            </div>
                                        </td>
                                        <td><?php echo date('M d, Y g:i A', strtotime($msg['contm_submission_date'])); ?></td>
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
                                                case 'Replied':
                                                    $badge_class = 'bg-success';
                                                    break;
                                                case 'Archived':
                                                    $badge_class = 'bg-dark';
                                                    break;
                                                default:
                                                    $badge_class = 'bg-secondary';
                                            }
                                            ?>
                                            <span id="status-badge-<?php echo $msg['contm_id']; ?>" class="badge <?php echo $badge_class; ?>">
                                                <?php echo $msg['contm_status']; ?>
                                            </span>
                                        </td>
                                        <td class="table-actions">
                                            <div class="btn-group">
                                                <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#viewModal<?php echo $msg['contm_id']; ?>">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#replyModal<?php echo $msg['contm_id']; ?>">
                                                    <i class="bi bi-reply"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#statusModal<?php echo $msg['contm_id']; ?>">
                                                    <i class="bi bi-check2-circle"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo $msg['contm_id']; ?>">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </div>
                                            
                                            <!-- View Message Modal -->
                                            <div class="modal fade" id="viewModal<?php echo $msg['contm_id']; ?>" tabindex="-1" aria-labelledby="viewModalLabel<?php echo $msg['contm_id']; ?>" aria-hidden="true">
                                                <div class="modal-dialog modal-lg">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title" id="viewModalLabel<?php echo $msg['contm_id']; ?>">
                                                                Message from <?php echo htmlspecialchars($msg['contm_name']); ?>
                                                            </h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <div class="mb-3">
                                                                <p><strong>From:</strong> <?php echo htmlspecialchars($msg['contm_name']); ?> (<?php echo htmlspecialchars($msg['contm_email']); ?>)</p>
                                                                <p><strong>Phone:</strong> <?php echo htmlspecialchars($msg['contm_phone'] ?? 'N/A'); ?></p>
                                                                <p><strong>Date:</strong> <?php echo date('M d, Y g:i A', strtotime($msg['contm_submission_date'])); ?></p>
                                                                <hr>
                                                                <div>
                                                                    <?php echo nl2br(htmlspecialchars($msg['contm_message'])); ?>
                                                                </div>
                                                                
                                                                <?php if (!empty($msg['contm_reply']) && $msg['contm_status'] === 'Replied'): ?>
                                                                <hr>
                                                                <h6>Your Reply (<?php echo date('M d, Y g:i A', strtotime($msg['contm_reply_date'])); ?>):</h6>
                                                                <div>
                                                                    <?php echo nl2br(htmlspecialchars($msg['contm_reply'])); ?>
                                                                </div>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                            <button type="button" class="btn btn-success" data-bs-dismiss="modal" data-bs-toggle="modal" data-bs-target="#replyModal<?php echo $msg['contm_id']; ?>">
                                                                Reply
                                                            </button>
                                                            <?php if ($msg['contm_status'] === 'Unread'): ?>
                                                            <form action="ajax/update_status.php" method="post" class="update-status-form d-inline">
                                                                <input type="hidden" name="message_id" value="<?php echo $msg['contm_id']; ?>">
                                                                <input type="hidden" name="new_status" value="Read">
                                                                <button type="submit" class="btn btn-primary">Mark as Read</button>
                                                            </form>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            

                                            <!-- Reply Modal -->
                                            <div class="modal fade" id="replyModal<?php echo $msg['contm_id']; ?>" tabindex="-1" aria-labelledby="replyModalLabel<?php echo $msg['contm_id']; ?>" aria-hidden="true">
                                                <div class="modal-dialog modal-lg">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title" id="replyModalLabel<?php echo $msg['contm_id']; ?>">
                                                                Reply to: <?php echo htmlspecialchars($msg['contm_name']); ?>
                                                            </h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <div class="mb-3">
                                                                <label class="form-label">From:</label>
                                                                <p><?php echo htmlspecialchars($msg['contm_name']); ?> (<?php echo htmlspecialchars($msg['contm_email']); ?>)</p>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label class="form-label">Message:</label>
                                                                <div class="card card-body bg-light mb-3">
                                                                    <?php echo nl2br(htmlspecialchars($msg['contm_message'])); ?>
                                                                </div>
                                                            </div>
                                                            <div class="d-flex justify-content-center mt-4">
                                                                <a href="mailto:<?php echo htmlspecialchars($msg['contm_email']); ?>" class="btn btn-primary">
                                                                    <i class="bi bi-reply-fill"></i> Reply via Email
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
                                                            <h5 class="modal-title" id="statusModalLabel<?php echo $msg['contm_id']; ?>">Change Message Status</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                        </div>
                                                        <form action="index.php" method="post" class="update-status-form">
                                                            <div class="modal-body">
                                                                <input type="hidden" name="message_id" value="<?php echo $msg['contm_id']; ?>">
                                                                <input type="hidden" name="update_status" value="1">
                                                                <div class="mb-3">
                                                                    <label for="new_status<?php echo $msg['contm_id']; ?>" class="form-label">New Status</label>
                                                                    <select class="form-select" id="new_status<?php echo $msg['contm_id']; ?>" name="new_status" required>
                                                                        <option value="">Select Status</option>
                                                                        <option value="Unread" <?php echo $msg['contm_status'] == 'Unread' ? 'selected' : ''; ?>>Unread</option>
                                                                        <option value="Read" <?php echo $msg['contm_status'] == 'Read' ? 'selected' : ''; ?>>Read</option>
                                                                        <option value="Replied" <?php echo $msg['contm_status'] == 'Replied' ? 'selected' : ''; ?>>Replied</option>
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
                                            
                                            <!-- Delete Confirmation Modal -->
                                            <div class="modal fade" id="deleteModal<?php echo $msg['contm_id']; ?>" tabindex="-1" aria-labelledby="deleteModalLabel<?php echo $msg['contm_id']; ?>" aria-hidden="true">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title" id="deleteModalLabel<?php echo $msg['contm_id']; ?>">Confirm Deletion</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <p>Are you sure you want to delete this message from <strong><?php echo htmlspecialchars($msg['contm_name']); ?></strong>?</p>
                                                            <p class="text-danger"><strong>Warning:</strong> This action cannot be undone.</p>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                            <form action="index.php" method="post" class="delete-msg-form">
                                                                <input type="hidden" name="message_id" value="<?php echo $msg['contm_id']; ?>">
                                                                <input type="hidden" name="delete_message" value="1">
                                                                <button type="submit" class="btn btn-danger">Delete</button>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="8" class="text-center">No messages found</td>
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
                                <a class="page-link" href="<?php echo ($page <= 1) ? '#' : '?page='.($page-1).(empty($status_filter) ? '' : '&status='.$status_filter).(empty($search) ? '' : '&search='.$search); ?>">Previous</a>
                            </li>
                            <?php for($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?><?php echo empty($status_filter) ? '' : '&status='.$status_filter; ?><?php echo empty($search) ? '' : '&search='.$search; ?>"><?php echo $i; ?></a>
                            </li>
                            <?php endfor; ?>
                            <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                                <a class="page-link" href="<?php echo ($page >= $total_pages) ? '#' : '?page='.($page+1).(empty($status_filter) ? '' : '&status='.$status_filter).(empty($search) ? '' : '&search='.$search); ?>">Next</a>
                            </li>
                        </ul>
                    </nav>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Toggle sidebar on mobile
            const sidebarToggle = document.getElementById('sidebarToggle');
            if (sidebarToggle) {
                sidebarToggle.addEventListener('click', function() {
                    document.querySelector('.sidebar').classList.toggle('show-mobile');
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

            // Mark as read when viewing a message
            const viewModals = document.querySelectorAll('[id^="viewModal"]');
            viewModals.forEach(modal => {
                modal.addEventListener('shown.bs.modal', function() {
                    const messageId = this.id.replace('viewModal', '');
                    const rowElement = document.querySelector(`#msg-row-${messageId}`);
                    const statusBadge = document.querySelector(`#status-badge-${messageId}`);
                    
                    if (statusBadge && statusBadge.textContent === 'Unread') {
                        fetch('ajax/update_status.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: `message_id=${messageId}&new_status=Read`
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                statusBadge.textContent = 'Read';
                                statusBadge.className = 'badge bg-primary';
                                rowElement.classList.remove('table-active');
                            }
                        });
                    }
                });
            });

            // AJAX for status updates
            document.querySelectorAll('.update-status-form').forEach(form => {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    const formData = new FormData(this);
                    const messageId = formData.get('message_id');
                    const newStatus = formData.get('new_status');
                    
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
                            document.querySelector(`#status-badge-${messageId}`).textContent = newStatus;
                            
                            // Update badge class
                            let badgeClass = 'bg-secondary';
                            switch (newStatus) {
                                case 'Unread': badgeClass = 'bg-danger'; break;
                                case 'Read': badgeClass = 'bg-primary'; break;
                                case 'Replied': badgeClass = 'bg-success'; break;
                                case 'Archived': badgeClass = 'bg-dark'; break;
                            }
                            document.querySelector(`#status-badge-${messageId}`).className = `badge ${badgeClass}`;
                            
                            // Update row highlighting
                            if (newStatus === 'Unread') {
                                document.querySelector(`#msg-row-${messageId}`).classList.add('table-active');
                            } else {
                                document.querySelector(`#msg-row-${messageId}`).classList.remove('table-active');
                            }
                            
                            // Close the modal
                            bootstrap.Modal.getInstance(document.querySelector(`#statusModal${messageId}`)).hide();
                            
                            // Show success message
                            showAlert('success', data.message);
                        } else {
                            showAlert('danger', data.message);
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        showAlert('danger', 'An error occurred while processing your request.');
                    });
                });
            });
            
            // AJAX for message deletion
            document.querySelectorAll('.delete-msg-form').forEach(form => {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    const formData = new FormData(this);
                    const messageId = formData.get('message_id');
                    
                    fetch('ajax/delete_message.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Remove the row from the table
                            document.querySelector(`#msg-row-${messageId}`).remove();
                            
                            // Close the modal
                            bootstrap.Modal.getInstance(document.querySelector(`#deleteModal${messageId}`)).hide();
                            
                            // Show success message
                            showAlert('success', data.message);
                            
                            // Update counts (you might want to reload the page or update counts via AJAX)
                            setTimeout(() => location.reload(), 2000);
                        } else {
                            showAlert('danger', data.message);
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        showAlert('danger', 'An error occurred while processing your request.');
                    });
                });
            });
            
            // AJAX for reply form
            document.querySelectorAll('.reply-form').forEach(form => {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    const formData = new FormData(this);
                    const messageId = formData.get('message_id');
                    
                    fetch('ajax/reply_message.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Update status to REPLIED
                            document.querySelector(`#status-badge-${messageId}`).textContent = 'Replied';
                            document.querySelector(`#status-badge-${messageId}`).className = 'badge bg-success';
                            
                            // Close the modal
                            bootstrap.Modal.getInstance(document.querySelector(`#replyModal${messageId}`)).hide();
                            
                            // Show success message
                            showAlert('success', data.message);
                            
                            // Reload page after a delay to update the reply content display
                            setTimeout(() => location.reload(), 2000);
                        } else {
                            showAlert('danger', data.message);
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        showAlert('danger', 'An error occurred while sending your reply.');
                    });
                });
            });
            
            // Helper function to show alerts
            function showAlert(type, message) {
                const alertDiv = document.createElement('div');
                alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
                alertDiv.innerHTML = `
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                `;
                
                const alertContainer = document.querySelector('#alert-container');
                alertContainer.appendChild(alertDiv);
                
                // Auto dismiss after 5 seconds
                setTimeout(() => {
                    const closeButton = alertDiv.querySelector('.btn-close');
                    if (closeButton) {
                        closeButton.click();
                    }
                }, 5000);
            }
        });
    </script>
</body>
</html>