<?php
session_start();
include "../../config/database.php";

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION["user_id"])) {
    echo json_encode(["success" => false, "message" => "Unauthorized access"]);
    exit();
}

// Validate request
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['message_id'])) {
    echo json_encode(["success" => false, "message" => "Invalid request"]);
    exit();
}

$message_id = (int)$_POST['message_id'];

try {
    // Start transaction to ensure we can reset sequence
    $pdo->beginTransaction();
    
    // Delete the message
    $delete_stmt = $pdo->prepare("DELETE FROM CONTACT_MESSAGE WHERE CONTM_ID = :message_id");
    $delete_stmt->bindParam(':message_id', $message_id, PDO::PARAM_INT);
    $result = $delete_stmt->execute();
    
    if ($result) {
        // Reset the sequence to use deleted IDs
        // First get current max ID or 0 if table is empty
        $max_stmt = $pdo->query("SELECT COALESCE(MAX(CONTM_ID), 0) AS max_id FROM CONTACT_MESSAGE");
        $max_id = $max_stmt->fetch(PDO::FETCH_ASSOC)['max_id'];
        
        // Reset sequence to max_id + 1 (or 1 if table is empty)
        $pdo->exec("ALTER SEQUENCE contact_message_contm_id_seq RESTART WITH " . ($max_id + 1));
        
        $pdo->commit();
        
        echo json_encode([
            "success" => true,
            "message" => "Message deleted successfully. ID will be reused for new messages."
        ]);
    } else {
        $pdo->rollBack();
        echo json_encode([
            "success" => false,
            "message" => "Failed to delete message"
        ]);
    }
    
} catch (PDOException $e) {
    // Roll back transaction on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Error deleting message: " . $e->getMessage());
    echo json_encode([
        "success" => false, 
        "message" => "Database error: " . $e->getMessage()
    ]);
}
?>