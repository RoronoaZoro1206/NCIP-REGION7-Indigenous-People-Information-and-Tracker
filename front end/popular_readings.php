<?php
// popular_readings.php
require_once 'db.php';

function getPopularReadings($limit = 5) {
    global $pdo;
    
    // Query to get the most viewed announcements
    $query = "
        SELECT a.ann_id, a.ann_title, a.ann_date, COALESCE(av.annv_count, 0) as views
        FROM announcement a
        LEFT JOIN announcement_view av ON a.ann_id = av.ann_id
        ORDER BY views DESC, a.ann_date DESC
        LIMIT ?
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$limit]);
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function renderPopularReadings($limit = 5) {
    $popularReadings = getPopularReadings($limit);
    $output = '';
    
    foreach ($popularReadings as $reading) {
        // Define image paths and URLs based on announcement_id
        $image_mapping = [
            1 => 'images/blog/advisory-75x75.png',
            2 => 'images/blog/Makasaysayang-75x75.png',
            3 => 'images/blog/kababaihan-75x75.png'
        ];
        
        $url_mapping = [
            1 => 'https://ncip.gov.ph/news/advisory/',
            2 => 'https://ncip.gov.ph/news/makasaysayang-araw-para-sa-mga-eskaya/',
            3 => 'https://ncip.gov.ph/news/ncip-nakiisa-sa-forum-para-sa-kababaihan-mula-sa-ibat-ibang-sektor/'
        ];
        
        // Get image path and URL, use defaults if not found
        $image_path = $image_mapping[$reading['ann_id']] ?? 'images/default-thumbnail.jpg';
        $url = $url_mapping[$reading['ann_id']] ?? '#';
        
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
                <p class="view-count"><small>(' . $reading['views'] . ' ' . ($reading['views'] <= 1 ? 'view' : 'views') . ')</small></p>
            </div>
        </div>';
    }
    
    return $output;
}
?>