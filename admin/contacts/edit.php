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

// Fetch user profile info
try {
    $user_stmt = $pdo->prepare("SELECT user_username, user_profile_picture FROM users WHERE user_id = :user_id");
    $user_stmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
    $user_stmt->execute();
    $user = $user_stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        $_SESSION['username'] = $user['user_username'];
        $_SESSION['profile_picture'] = $user['user_profile_picture'] ?? 'default.png';
    }
} catch (PDOException $e) {
    error_log("Failed to fetch user data: " . $e->getMessage());
}

// Check if message_id is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error_message'] = "No message ID provided";
    header("Location: index.php");
    exit();
}

$message_id = (int)$_GET['id'];
$message = null;

// Get message details
try {
    $stmt = $pdo->prepare("SELECT * FROM CONTACT_MESSAGE WHERE CONTM_ID = :message_id");
    $stmt->bindParam(':message_id', $message_id, PDO::PARAM_INT);
    $stmt->execute();
    
    if ($stmt->rowCount() === 0) {
        $_SESSION['error_message'] = "Message not found";
        header("Location: index.php");
        exit();
    }
    
    $message = $stmt->fetch(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Error retrieving message: " . $e->getMessage();
    header("Location: index.php");
    exit();
}

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        // Validate inputs
        $name = trim($_POST['name']);
        $email = filter_var(trim($_POST['email']), FILTER_VALIDATE_EMAIL);
        $phone = trim($_POST['phone']);
        $content = trim($_POST['message']);
        $status = trim($_POST['status']);
        $reply = trim($_POST['reply']);
        
        // Basic validation
        if (empty($name) || empty($content) || $email === false) {
            throw new Exception("Please fill in all required fields with valid information");
        }
        
        // Update message
        $update_stmt = $pdo->prepare("UPDATE CONTACT_MESSAGE SET 
            CONTM_NAME = :name,
            CONTM_EMAIL = :email,
            CONTM_PHONE = :phone,
            CONTM_MESSAGE = :message,
            CONTM_STATUS = :status,
            CONTM_REPLY = :reply" .
            // Only update reply date if reply is provided
            (!empty($reply) ? ", CONTM_REPLY_DATE = CURRENT_TIMESTAMP" : "") . 
            " WHERE CONTM_ID = :message_id");
        
        $update_stmt->bindParam(':name', $name);
        $update_stmt->bindParam(':email', $email);
        $update_stmt->bindParam(':phone', $phone);
        $update_stmt->bindParam(':message', $content);
        $update_stmt->bindParam(':status', $status);
        $update_stmt->bindParam(':reply', $reply);
        $update_stmt->bindParam(':message_id', $message_id, PDO::PARAM_INT);
        
        if ($update_stmt->execute()) {
            // Log the update
            $log_query = "INSERT INTO USER_LOGS (USER_ID, LOG_TYPE, LOG_DETAILS, LOG_TIMESTAMP) 
                         VALUES (:user_id, 'UPDATE', :details, CURRENT_TIMESTAMP)";
            $log_stmt = $pdo->prepare($log_query);
            $details = "Admin edited contact message ID: " . $message_id;
            $log_stmt->bindParam(":user_id", $_SESSION["user_id"]);
            $log_stmt->bindParam(":details", $details);
            $log_stmt->execute();
            
            $_SESSION['success_message'] = "Message updated successfully";
            header("Location: view.php?id=" . $message_id);
            exit();
        } else {
            throw new Exception("Failed to update message");
        }
    } catch (Exception $e) {
        $_SESSION['error_message'] = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Message - NCIP Admin</title>
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
        .form-label {
            font-weight: 500;
        }
        .required::after {
            content: "*";
            color: red;
            margin-left: 4px;
        }
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
            }
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
                <h1>Edit Message</h1>
                <div>
                    <a href="view.php?id=<?php echo $message_id; ?>" class="btn btn-outline-secondary me-2">
                        <i class="bi bi-eye"></i> View
                    </a>
                    <a href="index.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Back to Messages
                    </a>
                </div>
            </div>
            
            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php 
                        echo $_SESSION['error_message'];
                        unset($_SESSION['error_message']);
                    ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-body">
                    <form method="post" action="edit.php?id=<?php echo $message_id; ?>">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="name" class="form-label required">Name</label>
                                <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($message['contm_name']); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="email" class="form-label required">Email</label>
                                <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($message['contm_email']); ?>" required>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="phone" class="form-label">Phone Number</label>
                                <input type="text" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($message['contm_phone'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6">
                                <label for="status" class="form-label required">Status</label>
                                <select class="form-select" id="status" name="status" required>
                                    <option value="Unread" <?php echo (strtolower($message['contm_status']) === 'unread') ? 'selected' : ''; ?>>Unread</option>
                                    <option value="Read" <?php echo (strtolower($message['contm_status']) === 'read') ? 'selected' : ''; ?>>Read</option>
                                    <option value="Replied" <?php echo (strtolower($message['contm_status']) === 'replied') ? 'selected' : ''; ?>>Replied</option>
                                    <option value="Archived" <?php echo (strtolower($message['contm_status']) === 'archived') ? 'selected' : ''; ?>>Archived</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="message" class="form-label required">Message</label>
                            <textarea class="form-control" id="message" name="message" rows="5" required><?php echo htmlspecialchars($message['contm_message']); ?></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="reply" class="form-label">Reply</label>
                            <textarea class="form-control" id="reply" name="reply" rows="5"><?php echo htmlspecialchars($message['contm_reply'] ?? ''); ?></textarea>
                            <div class="form-text">
                                <?php if (!empty($message['contm_reply_date'])): ?>
                                    Last replied on: <?php echo date('F j, Y, g:i a', strtotime($message['contm_reply_date'])); ?>
                                <?php else: ?>
                                    No reply has been sent yet.
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-end">
                            <a href="index.php" class="btn btn-secondary me-2">Cancel</a>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save"></i> Update Message
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>