<?php
// track_clicks.php
require_once 'db.php';

// Set proper JSON header
header('Content-Type: application/json');

if (isset($_POST['ann_id'])) {
    $announcement_id = intval($_POST['ann_id']);
    
    // Check if this announcement exists in the views table
    $check_stmt = $pdo->prepare("SELECT * FROM announcement_view WHERE ann_id = ?");
    $check_stmt->execute([$announcement_id]);
    
    if ($check_stmt->rowCount() > 0) {
        // Update existing count
        $update_stmt = $pdo->prepare("UPDATE announcement_view SET annv_count = annv_count + 1, annv_last_updated = CURRENT_TIMESTAMP WHERE ann_id = ?");
        $update_stmt->execute([$announcement_id]);
    } else {
        // Insert new record
        $insert_stmt = $pdo->prepare("INSERT INTO announcement_view (ann_id) VALUES (?)");
        $insert_stmt->execute([$announcement_id]);
    }
    
    // Get updated count to return
    $count_stmt = $pdo->prepare("SELECT COALESCE(annv_count, 0) as view_count FROM announcement_view WHERE ann_id = ?");
    $count_stmt->execute([$announcement_id]);
    $result = $count_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get the top articles by view count to update popular readings section
    $popular_query = "
        SELECT a.ann_id, a.ann_title, a.ann_date, COALESCE(av.annv_count, 0) as views
        FROM announcement a
        LEFT JOIN announcement_view av ON a.ann_id = av.ann_id
        ORDER BY views DESC, a.ann_date DESC
        LIMIT 2
    ";
    $popular_stmt = $pdo->prepare($popular_query);
    $popular_stmt->execute();
    $popular_articles = $popular_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Prepare HTML for popular readings section
    $popular_html = '';
    foreach ($popular_articles as $article) {
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
        $image_path = $image_mapping[$article['ann_id']] ?? 'images/default-thumbnail.jpg';
        $url = $url_mapping[$article['ann_id']] ?? '#';
        
        // Format the date
        $date = new DateTime($article['ann_date']);
        $formatted_date = $date->format('F j, Y');
        
        // Create shortened title if too long (over 40 chars)
        $title = $article['ann_title'];
        $short_title = (strlen($title) > 40) ? substr($title, 0, 37) . '...' : $title;
        
        $popular_html .= '
        <div class="hentry-post group">
            <div class="thumb-img">
                <img width="75" height="75" src="' . htmlspecialchars($image_path) . '" class="attachment-blog_thumb wp-post-image" alt="thumbnail" />
            </div>
            <div class="text">
                <a href="' . htmlspecialchars($url) . '" target="_blank" title="' . htmlspecialchars($title) . '" class="title">
                    ' . htmlspecialchars($short_title) . '
                </a>
                <p class="post-date">' . $formatted_date . '</p>
                <p class="view-count" data-announcement-id="' . $article['ann_id'] . '"><small>(' . $article['views'] . ' ' . ($article['views'] <= 1 ? 'view' : 'views') . ')</small></p>
            </div>
        </div>';
    }
    
    echo json_encode([
        'success' => true, 
        'count' => intval($result['view_count'] ?? 1),
        'ann_id' => $announcement_id,
        'popular_html' => $popular_html
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'No announcement ID provided']);
}