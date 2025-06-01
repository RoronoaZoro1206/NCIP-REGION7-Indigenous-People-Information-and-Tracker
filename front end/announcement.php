<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'db.php';
require_once 'google_auth.php';
require_once 'comments.php';
require_once 'pagination_functions.php';
require_once 'announcement_data.php';

// Handle Google OAuth logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    // Clear Google session data
    unset($_SESSION['google_user']);
    unset($_SESSION['google_access_token']);
    
    // Log the logout activity if user was logged in
    if (isset($_SESSION['user_id'])) {
        try {
            $client_ip = $_SERVER['HTTP_CLIENT_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
            $log_query = "INSERT INTO USER_LOGS (USER_ID, LOG_TYPE, LOG_DETAILS, LOG_IP_ADDRESS) 
                         VALUES (:user_id, 'GOOGLE_LOGOUT', 'User logged out from Google OAuth', :ip_address)";
            $log_stmt = $pdo->prepare($log_query);
            $log_stmt->bindParam(":user_id", $_SESSION['user_id']);
            $log_stmt->bindParam(":ip_address", $client_ip);
            $log_stmt->execute();
        } catch (Exception $e) {
            error_log("Failed to log Google logout: " . $e->getMessage());
        }
    }
    
    // Redirect to announcement page without logout parameter
    header('Location: announcement.php');
    exit;
}

// Handle Google OAuth callback success
if (isset($_GET['auth_success'])) {
    // Log successful Google authentication
    try {
        $client_ip = $_SERVER['HTTP_CLIENT_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        $user_id = $_SESSION['user_id'] ?? null;
        $google_name = $_SESSION['google_user']['name'] ?? 'Unknown';
        $google_email = $_SESSION['google_user']['email'] ?? 'Unknown';
        
        $log_query = "INSERT INTO USER_LOGS (USER_ID, LOG_TYPE, LOG_DETAILS, LOG_IP_ADDRESS) 
                     VALUES (:user_id, 'GOOGLE_LOGIN_SUCCESS', :details, :ip_address)";
        $log_stmt = $pdo->prepare($log_query);
        $log_details = "Google OAuth login successful | Name: " . $google_name . " | Email: " . $google_email;
        $log_stmt->bindParam(":user_id", $user_id);
        $log_stmt->bindParam(":details", $log_details);
        $log_stmt->bindParam(":ip_address", $client_ip);
        $log_stmt->execute();
    } catch (Exception $e) {
        error_log("Failed to log Google login success: " . $e->getMessage());
    }
}

// Get current page number
$current_page = getCurrentPageNumber();

// Check for auth error/success messages
$authError = $_GET['auth_error'] ?? null;
$authSuccess = isset($_GET['auth_success']);

// Log auth errors if they exist
if ($authError) {
    try {
        $client_ip = $_SERVER['HTTP_CLIENT_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        $log_query = "INSERT INTO USER_LOGS (USER_ID, LOG_TYPE, LOG_DETAILS, LOG_IP_ADDRESS) 
                     VALUES (NULL, 'GOOGLE_LOGIN_ERROR', :details, :ip_address)";
        $log_stmt = $pdo->prepare($log_query);
        $log_details = "Google OAuth login error: " . $authError;
        $log_stmt->bindParam(":details", $log_details);
        $log_stmt->bindParam(":ip_address", $client_ip);
        $log_stmt->execute();
    } catch (Exception $e) {
        error_log("Failed to log Google auth error: " . $e->getMessage());
    }
}

// Get announcement statistics for admin monitoring (exclude soft-deleted)
try {
    $stats_query = "SELECT 
                        COUNT(*) as total_announcements,
                        COUNT(CASE WHEN ANN_DATE >= CURRENT_DATE - INTERVAL '30 days' THEN 1 END) as recent_announcements,
                        COUNT(CASE WHEN ANN_DATE >= CURRENT_DATE - INTERVAL '7 days' THEN 1 END) as weekly_announcements
                    FROM ANNOUNCEMENT 
                    WHERE ANN_DELETED_AT IS NULL";
    
    $stats_stmt = $pdo->prepare($stats_query);
    $stats_stmt->execute();
    $announcement_stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Announcement stats error: " . $e->getMessage());
    $announcement_stats = [
        'total_announcements' => 0,
        'recent_announcements' => 0,
        'weekly_announcements' => 0
    ];
}

// Track page view for analytics
try {
    $client_ip = $_SERVER['HTTP_CLIENT_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    $user_id = $_SESSION['user_id'] ?? null;
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    
    $view_log_query = "INSERT INTO USER_LOGS (USER_ID, LOG_TYPE, LOG_DETAILS, LOG_IP_ADDRESS) 
                      VALUES (:user_id, 'PAGE_VIEW', :details, :ip_address)";
    $view_log_stmt = $pdo->prepare($view_log_query);
    $view_details = "Viewed announcements page | Page: " . $current_page . " | User Agent: " . substr($user_agent, 0, 100);
    $view_log_stmt->bindParam(":user_id", $user_id);
    $view_log_stmt->bindParam(":details", $view_details);
    $view_log_stmt->bindParam(":ip_address", $client_ip);
    $view_log_stmt->execute();
} catch (Exception $e) {
    error_log("Failed to log page view: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en-US">
<!-- START HEAD -->
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.2, user-scalable=yes" />

    <title>Announcements & News - NCIP Cebu Connect</title>
    <link rel="icon" href="images/ncip-logo.png" type="image/png">
    
    <!-- SEO Meta Tags -->
    <meta name="description" content="Stay updated with the latest announcements and news from the National Commission on Indigenous Peoples - Cebu Provincial Office. Get information about programs, services, and community updates.">
    <meta name="keywords" content="NCIP, announcements, news, indigenous peoples, Cebu, Philippines, updates, programs">
    <meta name="author" content="NCIP Cebu Provincial Office">
    
    <!-- Open Graph Meta Tags for Social Media -->
    <meta property="og:title" content="Announcements & News - NCIP Cebu Connect">
    <meta property="og:description" content="Latest announcements and news from NCIP Cebu Provincial Office">
    <meta property="og:image" content="images/ncip-logo.png">
    <meta property="og:url" content="announcement.php">
    <meta property="og:type" content="website">

    <!-- RESET STYLESHEET -->
    <link rel="stylesheet" type="text/css" media="all" href="css/reset.css" />
    <!-- BOOTSTRAP STYLESHEET -->
    <link rel="stylesheet" type="text/css" media="all" href="css/bootstrap.css" />
    <!-- MAIN THEME STYLESHEET -->
    <link rel="stylesheet" type="text/css" media="all" href="style.css" />
    <!-- For iPad -->
    <link rel="apple-touch-icon" sizes="180x180" href="images/ncip-logo.png">

    <link rel='stylesheet' id='thickbox-css'  href='js/thickbox/thickbox.css' type='text/css' media='all' />
    <link rel='stylesheet' id='google-fonts-css'  href='http://fonts.googleapis.com/css?family=Playfair+Display%7COpen+Sans+Condensed%3A300%7COpen+Sans%7CShadows+Into+Light%7CMuli%7CDroid+Sans%7CArbutus+Slab%7CAbel&#038;ver=3.5.1' type='text/css' media='all' />
    <link rel='stylesheet' id='responsive-css'  href='css/responsive.css' type='text/css' media='all' />
    <link rel='stylesheet' id='polaroid-slider-css'  href='sliders/polaroid/css/polaroid.css' type='text/css' media='all' />
    <link rel='stylesheet' id='ahortcodes-css'  href='css/shortcodes.css' type='text/css' media='all' />
    <link rel='stylesheet' id='contact-form-css'  href='css/contact_form.css' type='text/css' media='all' />
    <link rel='stylesheet' id='tipsy-form-css'  href='css/tipsy.css.css' type='text/css' media='all' />
    <link rel='stylesheet' id='blog-libra-big-css'  href='blog/elegant/css/style.css' type='text/css' media='all' />
    <link rel='stylesheet' id='custom-css'  href='css/custom.css' type='text/css' media='all' />
    <link rel="stylesheet" type="text/css" media="all" href="css/comment-styles.css" />

    <style type="text/css">
        body { 
            background-color: #ffffff; 
            background-image: url('images/bg-pattern.png'); 
            background-repeat: repeat; 
            background-position: top left; 
            background-attachment: scroll; 
        }
        
        /* Enhanced styles for announcement page */
        .announcement-stats {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #007bff;
        }
        
        .announcement-stats h4 {
            margin: 0 0 10px 0;
            color: #333;
            font-size: 16px;
        }
        
        .announcement-stats p {
            margin: 5px 0;
            color: #666;
            font-size: 14px;
        }
        
        .auth-message {
            padding: 10px 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            font-weight: 500;
        }
        
        .auth-success {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        
        .auth-error {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        
        .google-signin-btn {
            display: inline-block;
            transition: transform 0.2s ease;
        }
        
        .google-signin-btn:hover {
            transform: scale(1.05);
        }
        
        .login-info {
            background: #e7f3ff;
            padding: 10px;
            border-radius: 6px;
            margin-bottom: 15px;
        }
        
        .login-info .avatar {
            margin-right: 8px;
            vertical-align: middle;
        }
        
        .logout-link {
            color: #dc3545;
            text-decoration: none;
            font-size: 12px;
        }
        
        .logout-link:hover {
            text-decoration: underline;
        }
        
        .login-prompt {
            text-align: center;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 6px;
        }
        
        .recent-comments .loading {
            text-align: center;
            padding: 20px;
            color: #666;
        }
        
        /* Responsive improvements */
        @media (max-width: 768px) {
            .announcement-stats {
                margin: 10px 0;
                padding: 10px;
            }
            
            .login-info {
                padding: 8px;
            }
            
            .google-signin-btn img {
                max-width: 200px;
                height: auto;
            }
        }
    </style>

    <script type='text/javascript' src='js/jquery/jquery.js'></script>
</head>
<!-- END HEAD -->

<!-- START BODY -->
<body class="page no_js responsive stretched">

<!-- START BG SHADOW -->
<div class="bg-shadow">

<!-- START WRAPPER -->
    <div id="wrapper" class="container group">

    <!-- START SCROLL TO TOP BUTTON -->
    <?php include 'components/scroll-to-top.php'; ?>
    <!-- START HEADER -->
    <?php include 'components/header.php'; ?>

    <!-- Display Auth Messages -->
    <?php if ($authSuccess): ?>
        <div class="auth-message auth-success">
            <i class="icon-ok-sign"></i> Successfully signed in with Google! You can now leave comments on announcements.
        </div>
    <?php endif; ?>
    
    <?php if ($authError): ?>
        <div class="auth-message auth-error">
            <i class="icon-exclamation-sign"></i> Authentication error: <?php echo htmlspecialchars($authError); ?>. Please try signing in again.
        </div>
    <?php endif; ?>

    <!-- SLOGAN -->
    <div class="slogan">
        <h2>OUR ANNOUNCEMENTS & NEWS</h2>
        <h3>Stay informed with the latest updates</h3>
        <?php if ($announcement_stats['total_announcements'] > 0): ?>
            <p style="margin-top: 10px; text-align:center; font-size: 14px; color: #666;">
                Showing the latest updates from NCIP Cebu Provincial Office. 
                <?php echo $announcement_stats['weekly_announcements']; ?> new this week.
            </p>
        <?php endif; ?>
    </div>
    <!-- END SLOGAN  -->

    <!-- START PRIMARY -->
    <div id="primary" class="sidebar-right">
        <div class="container group">
            <div class="row">
            <!-- START CONTENT -->
            <div id="content-blog" class="span9 content group">
                <?php 
                try {
                    echo renderAnnouncementsForPage($current_page); 
                } catch (Exception $e) {
                    error_log("Error rendering announcements: " . $e->getMessage());
                    echo '<div class="alert alert-error">';
                    echo '<h4>Unable to load announcements</h4>';
                    echo '<p>We\'re experiencing technical difficulties. Please try again later or contact us at region67.cebupo@ncip.gov.ph</p>';
                    echo '</div>';
                }
                ?>

                <!-- Pagination -->
                <?php 
                try {
                    echo renderPagination($current_page); 
                } catch (Exception $e) {
                    error_log("Error rendering pagination: " . $e->getMessage());
                    // Don't show error for pagination, just log it
                }
                ?>
            </div>
            <!-- END CONTENT -->

            <!-- START SIDEBAR -->
            <div id="sidebar-blog-sidebar" class="span3 sidebar group">
                
                <!-- Popular Readings Widget -->
                <div class="widget-first widget recent-posts">
                    <h3>Popular <span class="title-highlight">readings now</span></h3>
                    <div class="recent-post group">
                        <?php 
                        try {
                            require_once 'popular_readings.php';
                            echo renderPopularReadings(2); 
                        } catch (Exception $e) {
                            error_log("Error loading popular readings: " . $e->getMessage());
                            echo '<p style="color: #666; font-style: italic;">Popular readings temporarily unavailable.</p>';
                        }
                        ?>
                    </div>
                </div>

                <!-- Announcement Statistics Widget -->
                <?php if ($announcement_stats['total_announcements'] > 0): ?>
                <div class="widget announcement-stats">
                    <h4><i class="icon-bar-chart"></i> Announcement Statistics</h4>
                    <p><strong>Total Announcements:</strong> <?php echo number_format($announcement_stats['total_announcements']); ?></p>
                    <p><strong>This Month:</strong> <?php echo number_format($announcement_stats['recent_announcements']); ?></p>
                    <p><strong>This Week:</strong> <?php echo number_format($announcement_stats['weekly_announcements']); ?></p>
                    <p style="font-size: 12px; color: #666; margin-top: 10px;">
                        Stay connected for the latest updates from our community.
                    </p>
                </div>
                <?php endif; ?>

                <!-- Twitter Widget -->
                <div id="last-tweets-3" class="widget-2 widget last-tweets">
                    <h3>Latest <span class="title-highlight">updates</span></h3>
                    <div class="list-tweets-3"></div>
                    <p id="follow-twitter">
                        <a href="https://www.facebook.com/CebuPO" target="_blank">
                            <i class="icon-facebook"></i> Follow us on Facebook &rarr;
                        </a>
                    </p>

                    <script type="text/javascript">
                        jQuery(function($){
                            // Only try to load tweets if the tweetable plugin is available
                            if (typeof $.fn.tweetable === 'function') {
                                $('#last-tweets-3 .list-tweets-3').tweetable({
                                    listClass: 'tweets-widget-3',
                                    username: 'YIW',
                                    time: false,
                                    limit: 1,
                                    replies: true
                                });
                            }
                        });
                    </script>
                </div>

                <!-- Recent Comments Widget -->
                <div class="widget recent-comments">
                    <h3>Recent <span class="title-highlight">comments</span></h3>
                    
                    <!-- Comments List -->
                    <div id="recent-comments-container" class="recent-comments group">
                        <?php 
                        try {
                            echo renderRecentComments(5); 
                        } catch (Exception $e) {
                            error_log("Error loading recent comments: " . $e->getMessage());
                            echo '<div class="loading">Recent comments temporarily unavailable.</div>';
                        }
                        ?>
                    </div>
                    
                    <!-- Google Authentication Section -->
                    <?php if (isUserLoggedIn()): ?>
                        <div class="login-info">
                            <img src="<?php echo htmlspecialchars($_SESSION['google_user']['picture'] ?? 'images/gavatar.png'); ?>" 
                            class="avatar photo" height="33" width="33" style="border-radius: 50%;" 
                            alt="User Avatar">
                            <p>Signed in as <strong><?php echo htmlspecialchars($_SESSION['google_user']['name']); ?></strong></p>
                            <a href="?action=logout" class="logout-link">
                                <i class="icon-signout"></i> Logout
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="login-prompt">
                            <p><strong>Join the conversation!</strong></p>
                            <p>Sign in with Google to leave comments:</p>
                            <a href="<?php echo getGoogleLoginUrl(); ?>" class="google-signin-btn">
                                <img src="images/google-signin-button.svg" alt="Sign in with Google" 
                                     style="max-width: 200px; height: auto;">
                            </a>
                            <p style="font-size: 12px; color: #666; margin-top: 10px;">
                                Your information is secure and only used for commenting.
                            </p>
                        </div>
                    <?php endif; ?>
                </div>                  

                <!-- Social Media Widget -->
                <div class="widget-last widget widget_nav_menu">
                    <h3>Follow <span class="title-highlight">us</span></h3>
                    <div class="menu-socialize-container">
                        <ul class="menu">
                            <li class="menu-item menu-item-type-custom">
                                <a href="https://www.facebook.com/CebuPO" target="_blank" rel="noopener">
                                    <i class="icon-facebook"></i> Facebook
                                </a>
                            </li>
                            <li class="menu-item menu-item-type-custom">
                                <a href="https://www.instagram.com/ncipcebupo/" target="_blank" rel="noopener">
                                    <i class="icon-instagram"></i> Instagram
                                </a>
                            </li>
                            <li class="menu-item menu-item-type-custom">
                                <a href="mailto:region67.cebupo@ncip.gov.ph">
                                    <i class="icon-envelope"></i> Email Us
                                </a>
                            </li>
                        </ul>
                    </div>
                    
                    <!-- Newsletter Signup -->
                    <div style="margin-top: 15px; padding: 10px; background: #f8f9fa; border-radius: 6px;">
                        <p style="font-size: 12px; margin-bottom: 8px;"><strong>Stay Updated</strong></p>
                        <p style="font-size: 11px; color: #666;">
                            Follow us on social media for real-time updates and community news.
                        </p>
                    </div>
                </div>
            </div>
            <!-- END SIDEBAR -->
            </div>
        </div>
    </div>
    <!-- END PRIMARY -->

    <!-- START FOOTER -->
    <?php include 'components/footer.php'; ?>   
    <!-- END FOOTER -->

    <div class="wrapper-border"></div>

    </div>
<!-- END WRAPPER -->

</div>
<!-- END BG SHADOW -->

<!-- JavaScript Files -->
<script type='text/javascript' src='components/announcement.js'></script>
<script type='text/javascript' src='js/underscore.min.js'></script>
<script type='text/javascript' src='js/jquery/jquery.masonry.min.js'></script>
<script type='text/javascript' src='js/jquery.easing.js'></script>
<script type='text/javascript' src='js/hoverIntent.min.js'></script>
<script type='text/javascript' src='js/media-upload.min.js'></script>
<script type='text/javascript' src='js/jquery.clickout.min.js'></script>
<script type='text/javascript' src='js/responsive.js'></script>
<script type='text/javascript' src='js/mobilemenu.js'></script>
<script type='text/javascript' src='js/jquery.superfish.js'></script>
<script type='text/javascript' src='js/jquery.colorbox-min.js'></script>
<script type='text/javascript' src='js/jquery.placeholder.js'></script>
<script type='text/javascript' src='js/jquery.tipsy.js'></script>
<script type='text/javascript' src='js/contact.js'></script>
<script type='text/javascript' src='js/jquery.tweetable.js'></script>
<script type='text/javascript' src='js/jquery.cycle.min.js'></script>
<script type='text/javascript' src='js/shortcodes.js'></script>
<script type='text/javascript' src='js/jquery.custom.js'></script>

<!-- Google Sign-In -->
<script src="https://accounts.google.com/gsi/client"></script>

<!-- Enhanced JavaScript for Announcements -->
<script type="text/javascript">
jQuery(document).ready(function($) {
    // Enhanced error handling for page functionality
    try {
        // Auto-refresh comments every 30 seconds if user is logged in
        <?php if (isUserLoggedIn()): ?>
        setInterval(function() {
            $('#recent-comments-container').load('comments.php?action=refresh', function(response, status, xhr) {
                if (status === "error") {
                    console.log("Failed to refresh comments: " + xhr.status + " " + xhr.statusText);
                }
            });
        }, 30000);
        <?php endif; ?>
        
        // Track user engagement
        $('a[href^="announcement-detail.php"]').on('click', function() {
            var announcementId = $(this).attr('href').match(/id=(\d+)/);
            if (announcementId && announcementId[1]) {
                // Track announcement clicks for analytics
                $.post('track_clicks.php', {
                    type: 'announcement_click',
                    announcement_id: announcementId[1],
                    page: 'announcement_list'
                });
            }
        });
        
        // Auto-hide auth messages after 5 seconds
        setTimeout(function() {
            $('.auth-message').fadeOut('slow');
        }, 5000);
        
    } catch (error) {
        console.log("JavaScript enhancement error:", error);
    }
});
</script>

</body>
<!-- END BODY -->
</html>