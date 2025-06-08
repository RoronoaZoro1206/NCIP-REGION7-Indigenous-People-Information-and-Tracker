<?php

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include "../../config/database.php";

// Check if user is logged in and is admin
if (!isset($_SESSION["user_id"]) || !isset($_SESSION["role"]) || strtoupper($_SESSION["role"]) !== 'ADMIN') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Check if required parameters are provided
if (!isset($_POST['app_code']) || !isset($_POST['notes'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit();
}

$app_code = $_POST['app_code'];
$notes = $_POST['notes'];

try {
    // Update the admin remarks in the administrator table using lowercase queries
    $query = "UPDATE administrator 
              SET admin_add_remarks = :notes 
              WHERE admin_code = (
                  SELECT admin_code 
                  FROM applicant 
                  WHERE app_code = :app_code
              )";
    
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':notes', $notes, PDO::PARAM_STR);
    $stmt->bindParam(':app_code', $app_code, PDO::PARAM_STR);
    
    if ($stmt->execute()) {
        // Check if any rows were actually updated
        if ($stmt->rowCount() > 0) {
            echo json_encode([
                'success' => true, 
                'message' => 'Admin notes updated successfully',
                'updated_notes' => $notes, // Send back the updated notes for real-time display
                'app_code' => $app_code
            ]);
        } else {
            // No rows updated - this could mean the app_code doesn't exist or admin_code is null
            echo json_encode([
                'success' => false, 
                'message' => 'No records were updated. Please verify the application exists.'
            ]);
        }
    } else {
        echo json_encode([
            'success' => false, 
            'message' => 'Failed to execute update query'
        ]);
    }
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>