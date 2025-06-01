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

// Process deletion if form submitted
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['confirm_delete'])) {
    try {
        // Start transaction
        $pdo->beginTransaction();
        
        // Delete the message
        $delete_stmt = $pdo->prepare("DELETE FROM CONTACT_MESSAGE WHERE CONTM_ID = :message_id");
        $delete_stmt->bindParam(':message_id', $message_id, PDO::PARAM_INT);
        $result = $delete_stmt->execute();
        
        if ($result) {
            // Log the deletion in the user logs
            $log_query = "INSERT INTO USER_LOGS (USER_ID, LOG_TYPE, LOG_DETAILS, LOG_TIMESTAMP) 
                         VALUES (:user_id, 'DELETE', :details, CURRENT_TIMESTAMP)";
            $log_stmt = $pdo->prepare($log_query);
            $details = "Admin deleted contact message ID: " . $message_id;
            $log_stmt->bindParam(":user_id", $_SESSION["user_id"]);
            $log_stmt->bindParam(":details", $details);
            $log_stmt->execute();
            
            $pdo->commit();
            
            $_SESSION['success_message'] = "Message has been deleted successfully";
            header("Location: index.php");
            exit();
        } else {
            throw new Exception("Failed to delete message");
        }
    } catch (PDOException $e) {
        $pdo->rollBack();
        $_SESSION['error_message'] = "Error deleting message: " . $e->getMessage();
        header("Location: index.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delete Message - NCIP Admin</title>
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
    </style>
</head>
<body>
    <!-- Include sidebar -->
    <?php include '../includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1>Delete Message</h1>
                <a href="index.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Back to Messages
                </a>
            </div>

            <div class="card">
                <div class="card-body">
                    <div class="alert alert-danger">
                        <h4 class="alert-heading"><i class="bi bi-exclamation-triangle-fill"></i> Warning!</h4>
                        <p>You are about to permanently delete this message. This action cannot be undone.</p>
                    </div>

                    <h5>Message Details:</h5>
                    <div class="mb-3">
                        <strong>From:</strong> <?php echo htmlspecialchars($message['contm_name']); ?>
                    </div>
                    <div class="mb-3">
                        <strong>Email:</strong> <?php echo htmlspecialchars($message['contm_email']); ?>
                    </div>
                    <div class="mb-3">
                        <strong>Date:</strong> <?php echo date('F j, Y, g:i a', strtotime($message['contm_submission_date'])); ?>
                    </div>
                    <div class="mb-4">
                        <strong>Message:</strong>
                        <div class="card">
                            <div class="card-body bg-light">
                                <?php echo nl2br(htmlspecialchars($message['contm_message'])); ?>
                            </div>
                        </div>
                    </div>

                    <hr>
                    <form method="post" action="delete.php?id=<?php echo $message_id; ?>" class="mt-4">
                        <div class="text-center">
                            <p class="mb-4">Are you sure you want to delete this message?</p>
                            <a href="index.php" class="btn btn-secondary me-2">Cancel</a>
                            <button type="submit" name="confirm_delete" class="btn btn-danger">
                                <i class="bi bi-trash"></i> Delete Message
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