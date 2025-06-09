<?php

/**
 * Helper functions for handling Google profile images
 */

function processGoogleProfileImage($imageUrl) {
    if (!$imageUrl) {
        return 'images/gavatar.png';
    }
    
    // Remove size restrictions and add size parameter for better quality
    $imageUrl = str_replace('=s96-c', '=s200-c', $imageUrl);
    $imageUrl = str_replace('?sz=50', '?sz=200', $imageUrl);
    
    // If no size parameter exists, add one
    if (strpos($imageUrl, '=s') === false && strpos($imageUrl, '?sz=') === false) {
        $separator = strpos($imageUrl, '?') !== false ? '&' : '?';
        $imageUrl .= $separator . 'sz=200';
    }
    
    // Add cache-busting parameter to force refresh
    $separator = strpos($imageUrl, '?') !== false ? '&' : '?';
    $imageUrl .= $separator . 'v=' . time();
    
    return $imageUrl;
}

function getProfileImageHtml($user, $size = 33, $cssClass = 'avatar photo google-profile-pic') {
    $profilePic = processGoogleProfileImage($user['picture'] ?? null);
    $userName = htmlspecialchars($user['name'] ?? 'Unknown User');
    
    return '<img src="' . htmlspecialchars($profilePic) . '" 
                 class="' . htmlspecialchars($cssClass) . '" 
                 height="' . $size . '" width="' . $size . '" 
                 style="border-radius: 50%; object-fit: cover;" 
                 alt="' . $userName . ' Avatar"
                 onerror="this.src=\'images/gavatar.png\'; this.onerror=null;"
                 crossorigin="anonymous">';
}
?>