<?php

require_once 'db.php';

if (isset($_POST['announcement_id'])) {
    $announcement_id = intval($_POST['announcement_id']);
    
    // Check if this announcement exists in the views table
    $check_stmt = $pdo->prepare("SELECT * FROM announcement_views WHERE announcement_id = ?");
    $check_stmt->execute([$announcement_id]);
    
    if ($check_stmt->rowCount() > 0) {
        // Update existing count
        $update_stmt = $pdo->prepare("UPDATE announcement_views SET view_count = view_count + 1, last_updated = CURRENT_TIMESTAMP WHERE announcement_id = ?");
        $update_stmt->execute([$announcement_id]);
    } else {
        // Insert new record
        $insert_stmt = $pdo->prepare("INSERT INTO announcement_views (announcement_id) VALUES (?)");
        $insert_stmt->execute([$announcement_id]);
    }
    
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'No announcement ID provided']);
}
?>