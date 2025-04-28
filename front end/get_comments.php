<?php
require_once 'db.php';
require_once 'google_auth.php';
require_once 'comments.php';

// Get the announcement ID from the URL
$announcementId = isset($_GET['announcement_id']) ? (int)$_GET['announcement_id'] : null;

if ($announcementId) {
    // Output the rendered comments for this announcement
    echo renderComments($announcementId);
} else {
    echo "Announcement ID is required";
}