<?php
// popular_readings.php
require_once 'db.php';

function getPopularReadings($limit = 5) {
    global $pdo;
    
    // Query to get the most viewed announcements from all pages
    $query = "
        SELECT a.ann_id, a.ann_title, a.ann_date, COALESCE(av.annv_count, 0) as views
        FROM announcement a
        LEFT JOIN announcement_view av ON a.ann_id = av.ann_id
        ORDER BY views DESC, a.ann_date DESC
        LIMIT :limit
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


function renderPopularReadings($limit = 5) {
    $popularReadings = getPopularReadings($limit);
    $output = '';
    
    foreach ($popularReadings as $reading) {
        // Get the appropriate image and URL for each announcement
        // This needs to be extended to handle all your announcements
        $image_path = getAnnouncementImage($reading['ann_id']);
        $url = getAnnouncementUrl($reading['ann_id']);
        
        // Format the date
        $date = new DateTime($reading['ann_date']);
        $formatted_date = $date->format('F j, Y');
        
        // Create shortened title if too long (over 40 chars)
        $title = $reading['ann_title'];
        $short_title = (strlen($title) > 40) ? substr($title, 0, 37) . '...' : $title;
        
        $output .= '
        <div class="hentry-post group">
            <div class="thumb-img">
                <img width="75" height="75" src="' . htmlspecialchars($image_path) . '" class="attachment-blog_thumb wp-post-image" alt="thumbnail" />
            </div>
            <div class="text">
                <a href="' . htmlspecialchars($url) . '" target="_blank" title="' . htmlspecialchars($title) . '" class="title">
                    ' . htmlspecialchars($short_title) . '
                </a>
                <p class="post-date">' . $formatted_date . '</p>
                <p class="view-count" data-announcement-id="' . $reading['ann_id'] . '"><small>(' . $reading['views'] . ' ' . ($reading['views'] <= 1 ? 'view' : 'views') . ')</small></p>
            </div>
        </div>';
    }
    
    return $output;
}

// Helper functions to get image and URL for any announcement
function getAnnouncementImage($ann_id) {
    // Define image paths for all announcements
    $image_mapping = [
        1 => 'images/blog/advisory-75x75.png',
        2 => 'images/blog/Makasaysayang-75x75.png',
        3 => 'images/blog/kababaihan-75x75.png',
        4 => 'images/blog/Dumalo-75x75.jpg',
        5 => 'images/blog/workshop-75x75.jpg',
        6 => 'images/blog/tagapanayam-75x75.jpg',
        7 => 'images/blog/ginawaran-75x75.jpg',
        8 => 'images/blog/walong-75x75.jpg',
        9 => 'images/blog/pinarangala-75x75.jpg',
        10 => 'images/blog/published-75x75.jpg',
        11 => 'images/blog/oes-75x75.jpg',
        12 => 'images/blog/un-75x75.jpg',
        13 => 'images/blog/katutubo-75x75.jpg',
        14 => 'images/blog/partnership-75x75.jpg',
        15 => 'images/blog/condemns-75x75.jpg',
    ];
    
    $image_path = $image_mapping[$ann_id] ?? 'images/default-thumbnail.jpg';
    
    // Create a more robust file check
    $paths_to_check = [
        $_SERVER['DOCUMENT_ROOT'] . '/' . $image_path,
        __DIR__ . '/' . $image_path,
        dirname(__FILE__) . '/' . $image_path,
        './' . $image_path,
        '../' . $image_path
    ];
    
    $exists = false;
    foreach ($paths_to_check as $path) {
        if (file_exists($path)) {
            $exists = true;
            break;
        }
    }
    
    if (!$exists) {
        return 'images/default-thumbnail.jpg';
    }
    
    return $image_path;
}

function getAnnouncementUrl($ann_id) {
    // Define URLs for all announcements
    $url_mapping = [
        1 => 'https://ncip.gov.ph/news/advisory/',
        2 => 'https://ncip.gov.ph/news/makasaysayang-araw-para-sa-mga-eskaya/',
        3 => 'https://ncip.gov.ph/news/ncip-nakiisa-sa-forum-para-sa-kababaihan-mula-sa-ibat-ibang-sektor/',
        4 => 'https://ncip.gov.ph/news/ncip-dumalo-sa-national-women-leaders-summit-on-halal-industry-advancement/',
        5 => 'https://ncip.gov.ph/news/workshop-on-the-identification-of-partnership-entry-points-between-the-commission-on-population-and-development-cpd-and-the-national-commission-on-indigenous-peoples/',
        6 => 'https://ncip.gov.ph/news/ncip-chairperson-sibug-las-tagapanayam-sa-women-in-extractives-forum-2025/',
        7 => 'https://ncip.gov.ph/news/ncip-chairperson-jennifer-pia-sibug-las-ginawaran-bilang-natatanging-lingkod-bayan-sa-gawad-pilipino-awards-2025/',
        8 => 'https://ncip.gov.ph/news/walong-katutubong-biktima-ng-human-trafficking-niligtas-ng-ncip-dmw-dfa-embahada-ng-pilipinas-sa-kuala-lumpur-at-dswd/',
        9 => 'https://ncip.gov.ph/news/ncip-pinarangalan-ng-gawad-bayanuhan-sa-pamumuhunan/',
        10 => 'https://ncip.gov.ph/news/ncip-published-its-3rd-volume-of-ncip-indigenews/',
        11 => 'https://ncip.gov.ph/news/ncip-oes-nagpulong-para-sa-kapakanan-ng-mga-katutubo/',
        12 => 'https://ncip.gov.ph/news/philippines-united-nations-un-dialogue-2025-driving-transitions-for-impact-scale-and-synergy/',
        13 => 'https://ncip.gov.ph/news/kababaihang-katutubo-bida-sa-womens-month-exhibit-ng-ncip/',
        14 => 'https://ncip.gov.ph/news/partnerships-sealed-to-uphold-indigenous-rights-and-environmental-stewardship/',
        15 => 'https://ncip.gov.ph/news/ncip-condemns-non-moro-indigenous-peoples-killings-in-maguindanao-del-sur/',
     
    ];
    
    return $url_mapping[$ann_id] ?? '#';
}