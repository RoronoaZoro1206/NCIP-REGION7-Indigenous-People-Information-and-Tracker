<?php
// get_popular_readings.php
require_once 'db.php';
require_once 'popular_readings.php';

// Set JSON header
header('Content-Type: application/json');

// Get the number of items to return (default to 2)
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 2;
if ($limit < 1) $limit = 2;

try {
    // Get the HTML for popular readings
    $popular_html = renderPopularReadings($limit);
    
    echo json_encode([
        'success' => true,
        'html' => $popular_html
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}