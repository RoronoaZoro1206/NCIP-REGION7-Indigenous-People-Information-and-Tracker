<?php
session_start();
include "../../config/database.php";

// Check if request is AJAX or form submission
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
          strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

if ($isAjax) {
    // Set JSON header for AJAX requests
    header('Content-Type: application/json');
}

// Check if user is logged in
if (!isset($_SESSION["user_id"])) {
    if ($isAjax) {
        echo json_encode(["success" => false, "message" => "Unauthorized access"]);
    } else {
        header("Location: ../../login.php");
    }
    exit();
}

// Validate request
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['message_id']) || !isset($_POST['new_status'])) {
    if ($isAjax) {
        echo json_encode(["success" => false, "message" => "Invalid request"]);
    } else {
        header("Location: ../index.php?error=invalid_request");
    }
    exit();
}

$message_id = (int)$_POST['message_id'];
$new_status = $_POST['new_status'];

// Validate status - PostgreSQL is case sensitive so use proper case
if (!in_array($new_status, ['Unread', 'Read', 'Replied', 'Archived'])) {
    if ($isAjax) {
        echo json_encode(["success" => false, "message" => "Invalid status"]);
    } else {
        header("Location: ../index.php?error=invalid_status");
    }
    exit();
}

try {
    // Update message status using PDO prepared statement
    $stmt = $pdo->prepare("UPDATE CONTACT_MESSAGE SET CONTM_STATUS = :status WHERE CONTM_ID = :message_id");
    $stmt->bindParam(':status', $new_status, PDO::PARAM_STR);
    $stmt->bindParam(':message_id', $message_id, PDO::PARAM_INT);
    $result = $stmt->execute();
    
    if ($result) {
        if ($isAjax) {
            echo json_encode([
                "success" => true, 
                "message" => "Status updated successfully", 
                "status" => $new_status
            ]);
        } else {
            // For form submissions, redirect back to appropriate page
            $_SESSION['success_message'] = "Message status updated to " . $new_status;
            
            // Redirect based on the new status
            if ($new_status == 'Archived') {
                header("Location: ../archived_messages.php");
            } else {
                header("Location: ../index.php");
            }
        }
    } else {
        if ($isAjax) {
            echo json_encode(["success" => false, "message" => "Failed to update status"]);
        } else {
            $_SESSION['error_message'] = "Failed to update message status";
            header("Location: ../index.php");
        }
    }
} catch (PDOException $e) {
    error_log("Error updating status: " . $e->getMessage());
    
    if ($isAjax) {
        echo json_encode(["success" => false, "message" => "Database error: " . $e->getMessage()]);
    } else {
        $_SESSION['error_message'] = "Database error while updating status";
        header("Location: ../index.php");
    }
}
?>