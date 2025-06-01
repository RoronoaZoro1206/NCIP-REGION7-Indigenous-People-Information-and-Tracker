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

// Check if ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: ../index.php");
    exit();
}

$message_id = (int)$_GET['id'];

// Get message details
try {
    $stmt = $pdo->prepare("SELECT * FROM CONTACT_MESSAGE WHERE CONTM_ID = :message_id");
    $stmt->bindParam(':message_id', $message_id, PDO::PARAM_INT);
    $stmt->execute();
    
    // Check if message exists
    if ($stmt->rowCount() === 0) {
        header("Location: index.php");
        exit();
    }
    
    $message = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Debug to see actual keys returned
    // error_log("Message keys: " . print_r(array_keys($message), true));
    
    // Mark as read if currently unread
    if (isset($message['contm_status']) && strtolower($message['contm_status']) == 'unread') {
        $update_stmt = $pdo->prepare("UPDATE CONTACT_MESSAGE SET CONTM_STATUS = 'Read' WHERE CONTM_ID = :message_id");
        $update_stmt->bindParam(':message_id', $message_id, PDO::PARAM_INT);
        $update_stmt->execute();
        
        // Update the status in our current data
        $message['contm_status'] = 'Read';
    }
} catch (PDOException $e) {
    error_log("Error in message view: " . $e->getMessage());
    // Redirect to index with error message
    header("Location: index.php?error=database");
    exit();
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
        }
    </style>
</head>
<body>
    <?php include '../includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1>Message Details</h1>
                <div>
                    <a href="index.php" class="btn btn-secondary me-2"><i class="bi bi-arrow-left"></i> Back to List</a>
                    <a href="#" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal"><i class="bi bi-trash"></i> Delete</a>
                </div>
            </div>
            
            <div class="card mb-4">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">From: <?php echo htmlspecialchars($message['contm_name'] ?? 'Unknown'); ?></h5>
                        <?php if (isset($message['contm_status'])): ?>
                            <?php if (strtolower($message['contm_status']) == 'read'): ?>
                                <span class="badge bg-success">Read</span>
                            <?php elseif (strtolower($message['contm_status']) == 'archived'): ?>
                                <span class="badge bg-secondary">Archived</span>
                            <?php else: ?>
                                <span class="badge bg-danger">Unread</span>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="badge bg-warning">Unknown Status</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <strong><i class="bi bi-envelope"></i> Email:</strong> 
                            <a href="mailto:<?php echo htmlspecialchars($message['contm_email'] ?? ''); ?>"><?php echo htmlspecialchars($message['contm_email'] ?? 'Not provided'); ?></a>
                        </div>
                        <div class="col-md-6">
                            <strong><i class="bi bi-telephone"></i> Phone:</strong> 
                            <?php echo !empty($message['contm_phone']) ? htmlspecialchars($message['contm_phone']) : 'Not provided'; ?>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-12">
                            <strong><i class="bi bi-calendar-event"></i> Date Submitted:</strong> 
                            <?php echo isset($message['contm_submission_date']) ? date('F d, Y g:i A', strtotime($message['contm_submission_date'])) : 'Unknown date'; ?>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <h5>Message:</h5>
                        <div class="message-content">
                            <?php echo nl2br(htmlspecialchars($message['contm_message'] ?? 'No message content')); ?>
                        </div>
                    </div>
                    
                    <div class="mt-4 d-flex">
                        <a href="mailto:<?php echo htmlspecialchars($message['contm_email'] ?? ''); ?>" class="btn btn-primary me-2">
                            <i class="bi bi-reply-fill"></i> Reply via Email
                        </a>
                        
                        <?php if (isset($message['contm_status']) && strtolower($message['contm_status']) != 'archived'): ?>
                            <form action="ajax/update_status.php" method="post" class="d-inline">
                                <input type="hidden" name="message_id" value="<?php echo $message_id; ?>">
                                <input type="hidden" name="new_status" value="Archived">
                                <button type="submit" class="btn btn-secondary">
                                    <i class="bi bi-archive"></i> Archive
                                </button>
                            </form>
                        <?php elseif (isset($message['contm_status']) && strtolower($message['contm_status']) == 'archived'): ?>
                            <form action="ajax/update_status.php" method="post" class="d-inline">
                                <input type="hidden" name="message_id" value="<?php echo $message_id; ?>">
                                <input type="hidden" name="new_status" value="Read">
                                <button type="submit" class="btn btn-info">
                                    <i class="bi bi-arrow-counterclockwise"></i> Unarchive
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
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
                    Are you sure you want to delete this message from <?php echo htmlspecialchars($message['contm_name'] ?? 'Unknown'); ?>?
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <a href="delete.php?id=<?php echo $message_id; ?>" class="btn btn-danger">Delete</a>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Handle delete button in modal
        document.querySelector('#deleteModal .btn-danger').addEventListener('click', function(e) {
            e.preventDefault();
            
            const messageId = <?php echo $message_id; ?>;
            
            // Send AJAX request to delete the message
            fetch('ajax/delete_message.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'message_id=' + messageId
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show success message and redirect
                    alert('Message deleted successfully');
                    window.location.href = 'index.php?deleted=true';
                } else {
                    // Show error message
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while deleting the message');
            });
        });
    });
    </script>
</body>
</html>