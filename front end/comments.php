<?php

require_once 'db.php';


// Function to get comments for a specific announcement
function getComments($announcementId, $parentId = null) {
    global $pdo;
    
    $query = "SELECT c.*, 
            (SELECT COUNT(*) FROM comments WHERE coms_parent_id = c.coms_id) as reply_count 
            FROM comments c 
            WHERE c.coms_announcement_id = :announcement_id 
            AND c.coms_parent_id " . ($parentId === null ? "IS NULL" : "= :parent_id") . " 
            ORDER BY c.coms_created_at DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':announcement_id', $announcementId, PDO::PARAM_INT);
    
    if ($parentId !== null) {
        $stmt->bindParam(':parent_id', $parentId, PDO::PARAM_INT);
    }
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Function to add new comment
function addComment($announcementId, $googleName, $googleEmail, $googlePic, $content, $parentId = null) {
    global $pdo;
    
    $query = "INSERT INTO comments (coms_announcement_id, coms_google_name, coms_google_email, coms_google_pic, coms_content, coms_parent_id) 
              VALUES (:announcement_id, :name, :email, :picture, :content, :parent_id)";
    
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':announcement_id', $announcementId, PDO::PARAM_INT);
    $stmt->bindParam(':name', $googleName, PDO::PARAM_STR);
    $stmt->bindParam(':email', $googleEmail, PDO::PARAM_STR);
    $stmt->bindParam(':picture', $googlePic, PDO::PARAM_STR);
    $stmt->bindParam(':content', $content, PDO::PARAM_STR);
    
    if ($parentId === null) {
        $stmt->bindValue(':parent_id', null, PDO::PARAM_NULL);
    } else {
        $stmt->bindParam(':parent_id', $parentId, PDO::PARAM_INT);
    }
    
    return $stmt->execute();
}

// Function to get recent comments across all announcements
function getRecentComments($limit = 5) {
    global $pdo;
    
    $query = "SELECT c.*, a.announcement_title 
              FROM comments c
              LEFT JOIN announcements a ON c.coms_announcement_id = a.announcement_id
              ORDER BY c.coms_created_at DESC
              LIMIT :limit";
    
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Function to render comments with replies for a specific announcement
function renderComments($announcementId) {
    $comments = getComments($announcementId);
    $html = '';
    
    foreach ($comments as $comment) {
        $html .= renderSingleComment($comment, $announcementId);
        
        // Get and render replies if any exist
        if ($comment['reply_count'] > 0) {
            $replies = getComments($announcementId, $comment['coms_id']);
            $html .= '<div class="comment-replies">';
            foreach ($replies as $reply) {
                $html .= renderSingleComment($reply, $announcementId, true);
            }
            $html .= '</div>';
        }
    }
    
    return $html;
}

// Function to render a single comment
function renderSingleComment($comment, $announcementId, $isReply = false) {
    $profilePic = !empty($comment['coms_google_pic']) ? $comment['coms_google_pic'] : 'images/gavatar.png';
    $replyClass = $isReply ? 'comment-reply' : '';
    $dateFormatted = date('F j, Y \a\t g:i a', strtotime($comment['coms_created_at']));
    
    $html = '
    <div class="the-post group comment-item ' . $replyClass . '" data-comment-id="' . $comment['coms_id'] . '">
        <div class="avatar">
            <img alt="" src="' . $profilePic . '" class="avatar photo" height="33" width="33"/>
        </div>
        
        <div class="comment-content">
            <span class="author">
                <strong>' . htmlspecialchars($comment['coms_google_name']) . '</strong>
            </span>
            <span class="comment-date">' . $dateFormatted . '</span>
            
            <p class="comment">' . htmlspecialchars($comment['coms_content']) . '</p>
            
            ' . (!$isReply ? '<a href="#" class="reply-link" data-comment-id="' . $comment['coms_id'] . '" data-announcement-id="' . $announcementId . '">Reply</a>' : '') . '
        </div>
    </div>';
    
    return $html;
}

// Function to render the comment form for a specific announcement
function renderCommentForm($announcementId) {
    $html = '';
    
    if (isUserLoggedIn()) {
        $html .= '
        <div class="comment-form-container">
            <form class="comment-form" method="post" data-announcement-id="' . $announcementId . '">
                <input type="hidden" name="action" value="add_comment">
                <input type="hidden" name="announcement_id" value="' . $announcementId . '">
                <input type="hidden" name="parent_id" class="parent-id-input" value="">
                
                <div class="user-info">
                    <img src="' . htmlspecialchars($_SESSION['google_user']['picture'] ?? 'images/gavatar.png') . '" 
                         class="avatar photo" height="33" width="33">
                    <span>Commenting as <strong>' . htmlspecialchars($_SESSION['google_user']['name']) . '</strong></span>
                    <a href="?action=logout" class="logout-link">(Logout)</a>
                </div>
                
                <textarea name="comment_content" class="comment-content" rows="4" placeholder="Write your comment here..." required></textarea>
                
                <div class="reply-info" style="display: none;">
                    <span>Replying to comment</span>
                    <a href="#" class="cancel-reply">Cancel reply</a>
                </div>
                
                <button type="submit" class="btn">Post Comment</button>
            </form>
        </div>';
    } else {
        $html .= '
        <div class="login-prompt">
            <p>Sign in with Google to leave a comment:</p>
            <a href="' . getGoogleLoginUrl() . '" class="google-signin-btn">
                <img src="images/google-signin-button.svg" alt="Sign in with Google">
            </a>
        </div>';
    }
    
    return $html;
}

// Function to render recent comments for the sidebar
function renderRecentComments($limit = 5) {
    $recentComments = getRecentComments($limit);
    $html = '';
    
    foreach ($recentComments as $comment) {
        $profilePic = !empty($comment['coms_google_pic']) ? $comment['coms_google_pic'] : 'images/gavatar.png';
        $announcementTitle = !empty($comment['announcement_title']) ? $comment['announcement_title'] : 'Announcement';
        $dateFormatted = date('F j, Y \a\t g:i a', strtotime($comment['coms_created_at']));
        $shortContent = strlen($comment['coms_content']) > 50 ? substr($comment['coms_content'], 0, 50) . '...' : $comment['coms_content'];
        
        $html .= '
        <div class="recent-comment-item">
            <div class="comment-avatar">
                <img src="' . $profilePic . '" class="avatar photo" height="33" width="33"/>
            </div>
            <div class="comment-details">
                <span class="comment-author"><strong>' . htmlspecialchars($comment['coms_google_name']) . '</strong></span>
                <span class="comment-on">on <a href="#announcement-' . $comment['coms_announcement_id'] . '">' . htmlspecialchars($announcementTitle) . '</a></span>
                <p class="comment-excerpt">' . htmlspecialchars($shortContent) . '</p>
                <span class="comment-date">' . $dateFormatted . '</span>
            </div>
        </div>';
    }
    
    return $html;
}

// Handle comment submission via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_comment') {
    header('Content-Type: application/json');
    
    // Validate user is logged in
    if (!isset($_SESSION['google_user'])) {
        echo json_encode(['success' => false, 'message' => 'You must be logged in to comment']);
        exit;
    }

    // Get user data from session
    $googleUser = $_SESSION['google_user'];
    $googleName = $googleUser['name'];
    $googleEmail = $googleUser['email'];
    $googlePic = $googleUser['picture'] ?? null;
    
    // Get comment data
    $announcementId = isset($_POST['announcement_id']) ? (int)$_POST['announcement_id'] : null;
    $content = $_POST['comment_content'] ?? '';
    $parentId = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
    
    if (empty($content)) {
        echo json_encode(['success' => false, 'message' => 'Comment cannot be empty']);
        exit;
    }
    
    if (empty($announcementId)) {
        echo json_encode(['success' => false, 'message' => 'Announcement ID is required']);
        exit;
    }
    
    // Add comment to database
    $result = addComment($announcementId, $googleName, $googleEmail, $googlePic, $content, $parentId);
    
    if ($result) {
        header('Location: announcement.php?auth_success=1');
    } else {
        header('Location: announcement.php?auth_error=no_code');
    }
    exit;
}