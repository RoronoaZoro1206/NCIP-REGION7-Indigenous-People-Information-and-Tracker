<?php

require_once 'load-env.php';
loadEnv();

// Google API client credentials
$clientID = getenv('CLIENT_ID'); 
$clientSecret = getenv('CLIENT_SECRET');
$redirectURI = 'http://localhost/Information%20Management/IM---Copy/front%20end/google_callback.php'; 

// Initialize the session if not started already
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Function to create Google auth URL
function getGoogleLoginUrl() {
    global $clientID, $redirectURI;
    
    $params = [
        'client_id' => $clientID,
        'redirect_uri' => $redirectURI,
        'response_type' => 'code',
        'scope' => 'email profile',
        'access_type' => 'online',
        'prompt' => 'select_account'
    ];
    
    return 'https://accounts.google.com/o/oauth2/auth?' . http_build_query($params);
}

// Check if user is logged in
function isUserLoggedIn() {
    return isset($_SESSION['google_user']);
}

// Logout function
function logoutUser() {
    if (isset($_SESSION['google_user'])) {
        unset($_SESSION['google_user']);
        unset($_SESSION['google_access_token']);
    }
}

// If logout action is requested
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    logoutUser();
    header('Location: ' . $_SERVER['HTTP_REFERER']);
    exit;
}
?>