<?php
session_start();

// Unset all session variables
$_SESSION = array();

// If a session cookie is used, destroy it
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 42000, '/');
}

// Destroy the session
session_destroy();

// Add a success message to query string for login page (optional)
$message = urlencode("You have successfully logged out.");

// Redirect to login page
header("Location: login.php");
exit();
?>