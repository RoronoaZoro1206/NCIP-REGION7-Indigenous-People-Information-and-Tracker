<?php
// get_view_count.php
require_once 'db.php';

// Set proper JSON header
header('Content-Type: application/json');

if (isset($_GET['ann_id'])) {
    $announcement_id = intval($_GET['ann_id']);
    
    // Get the current view count for this announcement
    $query = "SELECT COALESCE(annv_count, 0) as view_count 
              FROM announcement_view 
              WHERE ann_id = :ann_id";
    
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':ann_id', $announcement_id, PDO::PARAM_INT);
    $stmt->execute();
    
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result) {
        echo json_encode([
            'success' => true,
            'count' => intval($result['view_count']),
            'ann_id' => $announcement_id
        ]);
    } else {
        // If no record found, the count is 0
        echo json_encode([
            'success' => true,
            'count' => 0,
            'ann_id' => $announcement_id
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'No announcement ID provided'
    ]);
}