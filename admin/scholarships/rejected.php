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

// Check if this is an AJAX request for getting rejected applications data
if (isset($_GET['ajax']) && $_GET['ajax'] === 'true') {
    header('Content-Type: application/json');
    
    try {
        // Get rejected applications count
        $rejected_count_query = "SELECT COUNT(DISTINCT a.APP_CODE) as count 
                               FROM APPLICANT a 
                               LEFT JOIN SCHOLARSHIP_STATUS ss ON a.APP_CODE = ss.APP_CODE AND ss.STATUS_IS_ACTIVE = TRUE
                               WHERE ss.STATUS_CODE = 'REJECTED'";
        
        $rejected_count_stmt = $pdo->query($rejected_count_query);
        $rejected_count = $rejected_count_stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        echo json_encode([
            'success' => true,
            'rejected_count' => $rejected_count
        ]);
        exit();
        
    } catch (PDOException $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Database error: ' . $e->getMessage()
        ]);
        exit();
    }
}

// Regular redirect behavior for non-AJAX requests
header("Location: index.php?status=REJECTED" . 
       (isset($_GET['search']) ? "&search=".$_GET['search'] : "") .
       (isset($_GET['page']) ? "&page=".$_GET['page'] : ""));
exit();
?>