<?php
require_once 'db.php';
require_once 'google_auth.php';
require_once 'comments.php';

// Output recent comments for the sidebar
echo renderRecentComments(5);