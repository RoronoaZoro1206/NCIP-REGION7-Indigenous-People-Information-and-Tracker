<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include "../config/database.php";

// Check if user is logged in and has admin role
if (!isset($_SESSION["user_id"]) || !isset($_SESSION["role"]) || strtoupper($_SESSION["role"]) !== 'ADMIN') {
    header("Location: ../login.php");
    exit();
}

// Pagination setup
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$records_per_page = 10;
$offset = ($current_page - 1) * $records_per_page;

// Search functionality
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$search_condition = '';
$params = [];

if (!empty($search)) {
    $search_condition = " AND (LOWER(CONTM_NAME) LIKE LOWER(:search) 
                           OR LOWER(CONTM_EMAIL) LIKE LOWER(:search) 
                           OR LOWER(CONTM_MESSAGE) LIKE LOWER(:search))";
    $params[':search'] = "%$search%";
}

// Get archived messages with pagination
try {
    // Count total archived messages for pagination
    $count_sql = "SELECT COUNT(*) FROM CONTACT_MESSAGE WHERE CONTM_STATUS = 'Archived'" . $search_condition;
    $count_stmt = $pdo->prepare($count_sql);
    
    if (!empty($search)) {
        $count_stmt->bindValue(':search', "%$search%", PDO::PARAM_STR);
    }
    
    $count_stmt->execute();
    $total_records = $count_stmt->fetchColumn();
    
    // Calculate total pages
    $total_pages = ceil($total_records / $records_per_page);
    
    // Adjust current page if needed
    if ($current_page > $total_pages) {
        $current_page = 1;
    }
    
    // Get messages for current page
    $sql = "SELECT * FROM CONTACT_MESSAGE 
            WHERE CONTM_STATUS = 'Archived'" . $search_condition . " 
            ORDER BY CONTM_SUBMISSION_DATE DESC 
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
    </style>
</head>
<body>
    <!-- Sidebar -->
    <?php include '../includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1><i class="bi bi-archive"></i> Archived Messages</h1>
                <a href="index.php" class="btn btn-primary">
                    <i class="bi bi-inbox"></i> Back to Inbox
                </a>
            </div>
            
            <?php if (isset($_GET['deleted']) && $_GET['deleted'] === 'true'): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    Message deleted successfully.
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_GET['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php 
                        $error = $_GET['error'];
                        if ($error === 'database') {
                            echo "A database error occurred.";
                        } else {
                            echo "An error occurred while processing your request.";
                        }
                    ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <!-- Search Bar -->
            <div class="card mb-4">
                <div class="card-body">
                    <form action="archived_messages.php" method="get" class="row g-2">
                        <div class="col-md-6">
                            <div class="input-group">
                                <input type="text" class="form-control" placeholder="Search archived messages..." name="search" value="<?php echo htmlspecialchars($search); ?>">
                                <button class="btn btn-primary" type="submit">
                                    <i class="bi bi-search"></i> Search
                                </button>
                                <?php if (!empty($search)): ?>
                                    <a href="archived_messages.php" class="btn btn-secondary">Clear</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Messages Table -->
            <div class="card">
                <div class="card-header bg-secondary text-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <span>Archived Messages</span>
                        <?php if (!empty($messages)): ?>
                            <span class="badge bg-light text-dark"><?php echo $total_records; ?> messages</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (empty($messages)): ?>
                        <div class="text-center p-4">
                            <i class="bi bi-archive display-1 text-secondary"></i>
                            <p class="mt-3">No archived messages found.</p>
                            <a href="index.php" class="btn btn-primary">View All Messages</a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Message Preview</th>
                                        <th>Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($messages as $message): ?>
                                        <tr>
                                            <td><?php echo $message['contm_id'] ?? ''; ?></td>
                                            <td><?php echo htmlspecialchars($message['contm_name'] ?? 'Unknown'); ?></td>
                                            <td>
                                                <a href="mailto:<?php echo htmlspecialchars($message['contm_email'] ?? ''); ?>">
                                                    <?php echo htmlspecialchars($message['contm_email'] ?? 'No email'); ?>
                                                </a>
                                            </td>
                                            <td>
                                                <?php 
                                                    $preview = $message['contm_message'] ?? '';
                                                    echo htmlspecialchars(mb_substr($preview, 0, 50)) . (mb_strlen($preview) > 50 ? '...' : '');
                                                ?>
                                            </td>
                                            <td>
                                                <?php 
                                                    echo isset($message['contm_submission_date']) 
                                                        ? date('M d, Y g:i A', strtotime($message['contm_submission_date'])) 
                                                        : 'Unknown date'; 
                                                ?>
                                            </td>
                                            <td>
                                                <div class="btn-group">
                                                    <a href="view.php?id=<?php echo $message['contm_id'] ?? ''; ?>" class="btn btn-sm btn-primary">
                                                        <i class="bi bi-eye"></i> View
                                                    </a>
                                                    <button type="button" class="btn btn-sm btn-danger" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#deleteModal" 
                                                            data-id="<?php echo $message['contm_id'] ?? ''; ?>"
                                                            data-name="<?php echo htmlspecialchars($message['contm_name'] ?? 'this message'); ?>">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                            <nav aria-label="Page navigation">
                                <ul class="pagination justify-content-center mt-4">
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
    
    <!-- Delete Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    Are you sure you want to delete the message from <span id="deleteMessageName">this sender</span>?
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmDelete">Delete</button>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Setup delete modal
        const deleteModal = document.getElementById('deleteModal');
        if (deleteModal) {
            deleteModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                const id = button.getAttribute('data-id');
                const name = button.getAttribute('data-name');
                
                document.getElementById('deleteMessageName').textContent = name;
                
                // Setup confirmation button
                document.getElementById('confirmDelete').onclick = function() {
                    // Send AJAX request to delete
                    fetch('ajax/delete_message.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'message_id=' + id
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Close modal
                            const modalInstance = bootstrap.Modal.getInstance(deleteModal);
                            modalInstance.hide();
                            
                            // Reload page with success message
                            window.location.href = 'archived_messages.php?deleted=true';
                        } else {
                            alert('Error: ' + data.message);
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('An error occurred while deleting the message');
                    });
                };
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
            document.querySelector(`#msg-row-${messageId}`).remove();
            showAlert('success', 'Message unarchived successfully');
            
            // Optional: Update count of archived messages
            const countBadge = document.querySelector('#archived-count');
            if (countBadge) {
                const currentCount = parseInt(countBadge.textContent);
                countBadge.textContent = currentCount - 1;
            }
        } else {
            showAlert('danger', data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('danger', 'An error occurred while unarchiving the message');
    });
}
    </script>
</body>
</html>