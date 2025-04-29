<?php
// google_callback.php - Handles the Google OAuth callback

require_once 'db.php';
require_once 'google_auth.php';

// Get the authorization code from Google
if (isset($_GET['code'])) {
    $code = $_GET['code'];
    
    // Exchange the authorization code for access token
    $token_url = 'https://oauth2.googleapis.com/token';
    $token_data = [
        'code' => $code,
        'client_id' => $clientID,
        'client_secret' => $clientSecret,
        'redirect_uri' => $redirectURI,
        'grant_type' => 'authorization_code'
    ];
    
    // Debug log
    error_log('Token request data: ' . print_r($token_data, true));
    
    $options = [
        'http' => [
            'header' => "Content-type: application/x-www-form-urlencoded\r\n",
            'method' => 'POST',
            'content' => http_build_query($token_data)
        ]
    ];
    
    $context = stream_context_create($options);
    $response = file_get_contents($token_url, false, $context);
    
    if ($response === false) {
        // Log error
        error_log('Token request failed: ' . error_get_last()['message']);
        header('Location: announcement.php?auth_error=token_request_failed');
        exit;
    }
    
    $token_info = json_decode($response, true);
    error_log('Token response: ' . print_r($token_info, true));
    
    if (!isset($token_info['access_token'])) {
        // Log error
        error_log('No access token in response');
        header('Location: announcement.php?auth_error=no_access_token');
        exit;
    }
    
    // Store access token in session
    $_SESSION['google_access_token'] = $token_info['access_token'];
    
    // Get user info using access token
    $user_info_url = 'https://www.googleapis.com/oauth2/v2/userinfo';
    $options = [
        'http' => [
            'header' => "Authorization: Bearer " . $token_info['access_token'] . "\r\n",
            'method' => 'GET'
        ]
    ];
    
    $context = stream_context_create($options);
    $user_response = file_get_contents($user_info_url, false, $context);
    
    if ($user_response === false) {
        // Log error
        error_log('User info request failed: ' . error_get_last()['message']);
        header('Location: announcement.php?auth_error=user_info_failed');
        exit;
    }
    
    $user_info = json_decode($user_response, true);
    error_log('Google user info: ' . print_r($user_info, true));
    
    // Store user info in session
    $_SESSION['google_user'] = [
        'id' => $user_info['id'],
        'email' => $user_info['email'],
        'name' => $user_info['name'],
        'picture' => isset($user_info['picture']) ? $user_info['picture'] : 'images/gavatar.png'
    ];
    
    // Redirect back to the announcement page
    header('Location: announcement.php?');
    exit;
} else {
    // No authorization code was received
    error_log('No authorization code received');
    header('Location: announcement.php');
    exit;
}