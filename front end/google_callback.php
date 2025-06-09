<?php


require_once 'db.php';
require_once 'google_auth.php';

// Get the authorization code from Google
if (isset($_GET['code'])) {
    $code = $_GET['code'];
    
    // Exchange the authorization code for access token
    $token_url = 'https://oauth2.googleapis.com/token';
    $token_data = [
        'client_id' => $clientID,
        'client_secret' => $clientSecret,
        'code' => $code,
        'grant_type' => 'authorization_code',
        'redirect_uri' => $redirectURI
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
        error_log('Failed to get access token from Google');
        header('Location: announcement.php?auth_error=token_failed');
        exit;
    }
    
    $token_info = json_decode($response, true);
    error_log('Token response: ' . print_r($token_info, true));
    
    if (!isset($token_info['access_token'])) {
        error_log('No access token in response: ' . print_r($token_info, true));
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
        error_log('Failed to get user info from Google');
        header('Location: announcement.php?auth_error=user_info_failed');
        exit;
    }
    
    $user_info = json_decode($user_response, true);
    error_log('User info response: ' . print_r($user_info, true));
    
    if (!isset($user_info['email'])) {
        error_log('No email in user info response');
        header('Location: announcement.php?auth_error=no_email');
        exit;
    }
    
    // Process the profile picture URL to ensure it's high quality and cacheable
    $profile_picture = $user_info['picture'] ?? null;
    if ($profile_picture) {
        // Remove size restrictions and add size parameter for better quality
        $profile_picture = str_replace('=s96-c', '=s200-c', $profile_picture);
        $profile_picture = str_replace('?sz=50', '?sz=200', $profile_picture);
        
        // If no size parameter exists, add one
        if (strpos($profile_picture, '=s') === false && strpos($profile_picture, '?sz=') === false) {
            $separator = strpos($profile_picture, '?') !== false ? '&' : '?';
            $profile_picture .= $separator . 'sz=200';
        }
        
        // Add cache-busting parameter to force refresh
        $separator = strpos($profile_picture, '?') !== false ? '&' : '?';
        $profile_picture .= $separator . 'v=' . time();
    }
    
    // Store user information in session with processed picture URL
    $_SESSION['google_user'] = [
        'id' => $user_info['id'],
        'email' => $user_info['email'],
        'name' => $user_info['name'],
        'picture' => $profile_picture,
        'verified_email' => $user_info['verified_email'] ?? false,
        'login_time' => time()
    ];
    
    error_log('Stored user session: ' . print_r($_SESSION['google_user'], true));
    
    // Redirect to announcement page with success parameter
    header('Location: announcement.php?auth_success=1');
    exit;
    
} else {
    error_log('No authorization code received from Google');
    header('Location: announcement.php?auth_error=no_code');
    exit;
}
?>