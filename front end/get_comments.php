<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'db.php';
require_once 'google_auth.php';
require_once 'comments.php';

$announcementId = isset($_GET['announcement_id']) ? (int)$_GET['announcement_id'] : null;

if ($announcementId) {
    $comments = renderComments($announcementId);

    if ($comments) {
        echo $comments;
    } else {
        echo '<p>No comments yet. Be the first to comment!</p>';
    }
} else {

    http_response_code(400);
    echo '<p>Announcement ID is required</p>';
}

