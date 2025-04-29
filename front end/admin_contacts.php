<?php
// Start session and include database connection
session_start();
require_once 'db.php';

// Check if user is logged in as admin (you'll need to implement authentication)

if (!isset($_SESSION["user_role"]) || $_SESSION["user_role"] !== 'admin') {
    $_SESSION["admin_error"] = "Admin access required";
    header('Location: form-content.php?show=login'); 
    exit;
}

// Handle message status updates
if (isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $messageId = isset($_POST['message_id']) ? (int)$_POST['message_id'] : 0;
    $status = isset($_POST['status']) ? $_POST['status'] : '';
    
    if ($messageId > 0 && in_array($status, ['read', 'unread', 'replied', 'spam', 'archived'])) {
        try {
            $stmt = $pdo->prepare("UPDATE contact_messages SET CONTM_STATUS = :status WHERE CONTM_ID = :id");
            $stmt->bindParam(':status', $status);
            $stmt->bindParam(':id', $messageId);
            $stmt->execute();
            
            $_SESSION['admin_message'] = "Message status updated successfully.";
        } catch (PDOException $e) {
            $_SESSION['admin_error'] = "Error updating status: " . $e->getMessage();
        }
    }
    
    // Redirect to refresh the page
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Handle message deletion
if (isset($_POST['action']) && $_POST['action'] === 'delete') {
    $messageId = isset($_POST['message_id']) ? (int)$_POST['message_id'] : 0;
    
    if ($messageId > 0) {
        try {
            $stmt = $pdo->prepare("DELETE FROM contact_messages WHERE CONTM_ID = :id");
            $stmt->bindParam(':id', $messageId);
            $stmt->execute();
            
            $_SESSION['admin_message'] = "Message deleted successfully.";
        } catch (PDOException $e) {
            $_SESSION['admin_error'] = "Error deleting message: " . $e->getMessage();
        }
    }
    
    // Redirect to refresh the page
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Get filter parameters
$status = isset($_GET['status']) ? $_GET['status'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Prepare query with filters
$query = "SELECT * FROM contact_messages WHERE 1=1";
$params = [];

if (!empty($status)) {
    $query .= " AND status = :status";
    $params[':status'] = $status;
}

if (!empty($search)) {
    $query .= " AND (name ILIKE :search OR email ILIKE :search OR message ILIKE :search)";
    $params[':search'] = "%$search%";
}

$query .= " ORDER BY submission_date DESC";

// Execute query
try {
    $stmt = $pdo->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $_SESSION['admin_error'] = "Error retrieving messages: " . $e->getMessage();
    $messages = [];
}

// Get counts for each status
try {
    $countQuery = "SELECT status, COUNT(*) as count FROM contact_messages GROUP BY status";
    $countStmt = $pdo->query($countQuery);
    $counts = [];
    
    while ($row = $countStmt->fetch(PDO::FETCH_ASSOC)) {
        $counts[$row['status']] = $row['count'];
    }
    
    // Get total count
    $totalQuery = "SELECT COUNT(*) as total FROM contact_messages";
    $totalStmt = $pdo->query($totalQuery);
    $totalRow = $totalStmt->fetch(PDO::FETCH_ASSOC);
    $counts['total'] = $totalRow['total'];
} catch (PDOException $e) {
    $_SESSION['admin_error'] = "Error retrieving message counts: " . $e->getMessage();
    $counts = ['total' => 0];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Contact Messages</title>
    <link rel="stylesheet" href="css/bootstrap.css">
    <link rel="stylesheet" href="css/font-awesome.css">
    <style>
        body {
            padding: 20px;
            background-color: #f8f9fa;
        }
        .container {
            max-width: 1200px;
            background-color: #fff;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .message-card {
            border: 1px solid #ddd;
            border-radius: 5px;
            margin-bottom: 15px;
            padding: 15px;
        }
        .message-header {
            display: flex;
            justify-content: space-between;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
            margin-bottom: 10px;
        }
        .message-actions {
            text-align: right;
        }
        .status-label {
            font-size: 0.85em;
            padding: 3px 8px;
            border-radius: 3px;
            margin-left: 10px;
        }
        .status-unread { background-color: #ffc107; }
        .status-read { background-color: #17a2b8; color: white; }
        .status-replied { background-color: #28a745; color: white; }
        .status-spam { background-color: #dc3545; color: white; }
        .status-archived { background-color: #6c757d; color: white; }
        .filters {
            margin-bottom: 20px;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            background-color: #f8f9fa;
        }
        .stats {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        .stat-box {
            flex: 1;
            padding: 10px;
            text-align: center;
            border-radius: 5px;
            box-shadow: 0 0 5px rgba(0,0,0,0.1);
        }
        .stat-box h3 {
            margin: 0;
            font-size: 24px;
        }
        .stat-box p {
            margin: 5px 0 0 0;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Contact Messages</h1>
        
        <?php if (isset($_SESSION['admin_message'])): ?>
            <div class="alert alert-success">
                <?php 
                    echo $_SESSION['admin_message']; 
                    unset($_SESSION['admin_message']);
                ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['admin_error'])): ?>
            <div class="alert alert-danger">
                <?php 
                    echo $_SESSION['admin_error']; 
                    unset($_SESSION['admin_error']);
                ?>
            </div>
        <?php endif; ?>
        
        <!-- Stats Section -->
        <div class="stats">
            <div class="stat-box bg-light">
                <h3><?php echo $counts['total'] ?? 0; ?></h3>
                <p>Total</p>
            </div>
            <div class="stat-box bg-warning text-dark">
                <h3><?php echo $counts['unread'] ?? 0; ?></h3>
                <p>Unread</p>
            </div>
            <div class="stat-box bg-info text-white">
                <h3><?php echo $counts['read'] ?? 0; ?></h3>
                <p>Read</p>
            </div>
            <div class="stat-box bg-success text-white">
                <h3><?php echo $counts['replied'] ?? 0; ?></h3>
                <p>Replied</p>
            </div>
            <div class="stat-box bg-danger text-white">
                <h3><?php echo $counts['spam'] ?? 0; ?></h3>
                <p>Spam</p>
            </div>
            <div class="stat-box bg-secondary text-white">
                <h3><?php echo $counts['archived'] ?? 0; ?></h3>
                <p>Archived</p>
            </div>
        </div>
        
        <!-- Filters Section -->
        <div class="filters">
            <form action="" method="get" class="row">
                <div class="col-md-4">
                    <label for="status">Filter by Status:</label>
                    <select name="status" id="status" class="form-control" onchange="this.form.submit()">
                        <option value="">All Messages</option>
                        <option value="unread" <?php echo $status === 'unread' ? 'selected' : ''; ?>>Unread</option>
                        <option value="read" <?php echo $status === 'read' ? 'selected' : ''; ?>>Read</option>
                        <option value="replied" <?php echo $status === 'replied' ? 'selected' : ''; ?>>Replied</option>
                        <option value="spam" <?php echo $status === 'spam' ? 'selected' : ''; ?>>Spam</option>
                        <option value="archived" <?php echo $status === 'archived' ? 'selected' : ''; ?>>Archived</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="search">Search:</label>
                    <input type="text" name="search" id="search" class="form-control" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search by name, email, or message content">
                </div>
                <div class="col-md-2">
                    <label>&nbsp;</label>
                    <button type="submit" class="btn btn-primary form-control">Filter</button>
                </div>
            </form>
        </div>
                
        <!-- Messages Section -->
        <?php if (empty($messages)): ?>
            <div class="alert alert-info">No messages found.</div>
        <?php else: ?>
            <?php foreach ($messages as $message): ?>
                <div class="message-card">
                    <div class="message-header">
                        <div>
                            <strong><?php echo htmlspecialchars($message['name']); ?></strong>
                            <span class="text-muted">&lt;<?php echo htmlspecialchars($message['email']); ?>&gt;</span>
                            <?php if (!empty($message['phone'])): ?>
                                <span class="text-muted"> | <?php echo htmlspecialchars($message['phone']); ?></span>
                            <?php endif; ?>
                            <span class="status-label status-<?php echo strtolower($message['status']); ?>">
                                <?php echo ucfirst($message['status']); ?>
                            </span>
                        </div>
                        <div class="text-muted">
                            <?php echo date('M j, Y g:i A', strtotime($message['submission_date'])); ?>
                        </div>
                    </div>
                    
                    <div class="message-body">
                        <p><?php echo nl2br(htmlspecialchars($message['message'])); ?></p>
                    </div>
                    
                    <div class="message-actions mt-3">
                        <!-- Update Status Form -->
                        <form action="" method="post" class="d-inline">
                            <input type="hidden" name="action" value="update_status">
                            <input type="hidden" name="message_id" value="<?php echo $message['id']; ?>">
                            <select name="status" class="form-control form-control-sm d-inline" style="width: auto;">
                                <option value="unread" <?php echo $message['status'] === 'unread' ? 'selected' : ''; ?>>Unread</option>
                                <option value="read" <?php echo $message['status'] === 'read' ? 'selected' : ''; ?>>Read</option>
                                <option value="replied" <?php echo $message['status'] === 'replied' ? 'selected' : ''; ?>>Replied</option>
                                <option value="spam" <?php echo $message['status'] === 'spam' ? 'selected' : ''; ?>>Spam</option>
                                <option value="archived" <?php echo $message['status'] === 'archived' ? 'selected' : ''; ?>>Archived</option>
                            </select>
                            <button type="submit" class="btn btn-sm btn-info">Update</button>
                        </form>
                        
                        <!-- Delete Form -->
                        <form action="" method="post" class="d-inline ml-2" onsubmit="return confirm('Are you sure you want to delete this message?');">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="message_id" value="<?php echo $message['id']; ?>">
                            <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                        </form>
                        
                        <!-- Reply Button -->
                        <a href="mailto:<?php echo htmlspecialchars($message['email']); ?>?subject=Re: Contact Form Submission" class="btn btn-sm btn-success ml-2">Reply</a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
    <script src="js/jquery/jquery.js"></script>
    <script src="js/bootstrap.min.js"></script>
</body>
</html>