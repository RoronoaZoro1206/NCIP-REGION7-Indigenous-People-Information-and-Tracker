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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['app_code'], $_POST['new_status'])) {
    $app_code = $_POST['app_code'];
    $new_status = $_POST['new_status'];
    
    // Begin transaction
    $pdo->beginTransaction();
    
    try {
        // First, set all current statuses for this applicant to inactive
        $update_old = $pdo->prepare("UPDATE SCHOLARSHIP_STATUS SET STATUS_IS_ACTIVE = FALSE 
                                   WHERE APP_CODE = :app_code");
        $update_old->bindParam(":app_code", $app_code);
        $update_old->execute();
        
        // Insert new status
        $insert = $pdo->prepare("INSERT INTO SCHOLARSHIP_STATUS 
                              (APP_CODE, STATUS_CODE, STATUS_DATE, STATUS_IS_ACTIVE) 
                              VALUES (:app_code, :new_status, CURRENT_TIMESTAMP, TRUE)");
        $insert->bindParam(":app_code", $app_code);
        $insert->bindParam(":new_status", $new_status);
        $insert->execute();
        
        $pdo->commit();
        
        // Set badge class based on status
        $badge_class = 'bg-warning'; // Default (pending)
        if ($new_status === 'APPROVED') {
            $badge_class = 'bg-success';
        } else if ($new_status === 'REJECTED') {
            $badge_class = 'bg-danger';
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Status updated successfully!',
            'status' => $new_status,
            'badge_class' => $badge_class,
            'date' => date('M d, Y')
        ]);
    } catch (PDOException $e) {
        $pdo->rollBack();
        echo json_encode([
            'success' => false,
            'message' => 'Error updating status: ' . $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request'
    ]);
}