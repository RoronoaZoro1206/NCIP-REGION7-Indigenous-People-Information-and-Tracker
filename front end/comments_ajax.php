<?php
// comments_ajax.php

// Turn off error display (important for clean JSON)
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Output buffer handling
while (ob_get_level()) {
    ob_end_clean();
}

// Force JSON output header
header('Content-Type: application/json');

// Start session if needed
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include necessary files
require_once 'db.php';          // Database connection
require_once 'google_auth.php'; // Google auth session
require_once 'comments.php';    // This has addComment() function!

// Main handler for AJAX request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_comment') {

    // Check if user is logged in
    if (!isset($_SESSION['google_user'])) {
        echo json_encode([
            'success' => false,
            'message' => 'You must be logged in to comment.'
        ]);
        exit;
    }

    // Collect user and form data
    $googleUser = $_SESSION['google_user'];
    $googleName = $googleUser['name'] ?? '';
    $googleEmail = $googleUser['email'] ?? '';
    $googlePic = $googleUser['picture'] ?? null;

    $announcementId = isset($_POST['announcement_id']) ? (int)$_POST['announcement_id'] : null;
    $content = trim($_POST['content'] ?? '');
    $parentId = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;

    // Validation
    if (empty($announcementId)) {
        echo json_encode([
            'success' => false,
            'message' => 'Announcement ID is required.'
        ]);
        exit;
    }

    if (empty($content)) {
        echo json_encode([
            'success' => false,
            'message' => 'Comment cannot be empty.'
        ]);
        exit;
    }

    // Insert into database
    $result = addComment($announcementId, $googleName, $googleEmail, $googlePic, $content, $parentId);

    if ($result) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to post comment. Please try again later.'
        ]);
    }
    exit;
} else {
    // If someone accesses this file directly, deny access
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request.'
    ]);
    exit;
}
?>
