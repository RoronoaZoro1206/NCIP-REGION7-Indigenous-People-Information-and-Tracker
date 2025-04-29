<?php
require_once 'db.php';
require_once 'google_auth.php';
require_once 'comments.php';

// Output recent comments for the sidebar
$recentComments = renderRecentComments(5);

// If there are no recent comments, show a friendly message
if ($recentComments) {
    echo $recentComments;
} else {
    echo '<p>No recent comments to display.</p>';
}
