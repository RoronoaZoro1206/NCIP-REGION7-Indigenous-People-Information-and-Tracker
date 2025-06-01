<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once "../../config/database.php";

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION["user_id"])) {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized access'
    ]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['app_code']) && isset($_POST['delete_application'])) {
    $app_code = $_POST['app_code'];
    
    // Begin transaction
    $pdo->beginTransaction();
    
    try {
        // Delete related records first (status records)
        $delete_status = $pdo->prepare("DELETE FROM SCHOLARSHIP_STATUS WHERE APP_CODE = :app_code");
        $delete_status->bindParam(":app_code", $app_code);
        $delete_status->execute();
        
        // Delete the application
        $delete_app = $pdo->prepare("DELETE FROM APPLICANT WHERE APP_CODE = :app_code");
        $delete_app->bindParam(":app_code", $app_code);
        $delete_app->execute();
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Application deleted successfully!'
        ]);
    } catch (PDOException $e) {
        $pdo->rollBack();
        echo json_encode([
            'success' => false,
            'message' => 'Error deleting application: ' . $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request'
    ]);
}