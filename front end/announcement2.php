<?php
require_once 'db.php';
require_once 'google_auth.php';
require_once 'comments.php';
require_once 'pagination_functions.php';
require_once 'announcement_data.php';

// Get current page number
$current_page = getCurrentPageNumber();

// Check if there's an auth error message to display
$authError = $_GET['auth_error'] ?? null;
$authSuccess = isset($_GET['auth_success']);
?>

<!DOCTYPE html>
<html lang="en-US">
<!-- Head section remains the same as in announcement.php -->
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.2, user-scalable=yes" />

    <title>NCIP Cebu Connect</title>
    <link rel="icon" href="images/ncip-logo.png" type="image/png">

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
        body { background-color: #ffffff; background-image: url('images/bg-pattern.png'); background-repeat: repeat; background-position: top left; background-attachment: scroll; }
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

    <!-- SLOGAN -->
    <div class="slogan">
        <h2>OUR ANNOUNCEMENT & NEWS</h2>
        <h3>are you curious, yeah?</h3>
    </div>
    <!-- END SLOGAN  -->
<!-- END SLOGAN  -->

<!-- START PRIMARY -->
<div id="primary" class="sidebar-right">
    <div class="container group">
        <div class="row">
        <!-- START CONTENT -->
            <div id="content-blog" class="span9 content group">
                <?php echo renderAnnouncementsForPage($current_page); ?>

                <!-- Pagination -->
                <?php echo renderPagination($current_page); ?>
            </div>
            <!-- END CONTENT -->

        <!-- START SIDEBAR - Include the same sidebar as in announcement.php -->
        <div id="sidebar-blog-sidebar" class="span3 sidebar group">
            <!-- Popular readings widget -->
            <div class="widget-first widget recent-posts">
                <h3>Popular <span class="title-highlight">readings now</span></h3>
                <div class="recent-post group">
                    <?php 
                        require_once 'popular_readings.php';
                        echo renderPopularReadings(2); 
                    ?>
                </div>
            </div>
            
            <!-- Twitter widget -->
            <div id="last-tweets-3" class="widget-2 widget last-tweets">
                <!-- Same content as in announcement.php -->
            </div>

            <!-- Recent comments widget -->
            <div class="widget recent-comments">
                <h3>Recent <span class="title-highlight">comments</span></h3>
                
                <!-- Comments List -->
                <div id="recent-comments-container" class="recent-comments group">
                    <?php echo renderRecentComments(5); ?>
                </div>
                
                <!-- Login/logout section -->
                <?php if (isUserLoggedIn()): ?>
                    <div class="login-info">
                        <img src="<?php echo htmlspecialchars($_SESSION['google_user']['picture'] ?? 'images/gavatar.png'); ?>" 
                        class="avatar photo" height="33" width="33" style="border-radius: 50%;">
                        <p>Signed in as <?php echo htmlspecialchars($_SESSION['google_user']['name']); ?></p>
                        <a href="?action=logout" class="logout-link">Logout</a>
                    </div>
                <?php else: ?>
                    <div class="login-prompt">
                        <p>Sign in with Google to leave comments:</p>
                        <a href="<?php echo getGoogleLoginUrl(); ?>" class="google-signin-btn">
                            <img src="images/google-signin-button.svg" alt="Sign in with Google">
                        </a>
                    </div>
                <?php endif; ?>
            </div>                  

            <!-- Follow us widget -->
            <div class="widget-last widget widget_nav_menu">
                <!-- Same content as in announcement.php -->
            </div>
        </div>
        <!-- END SIDEBAR -->
        </div>
    </div>
</div>
<!-- END PRIMARY -->

    <?php include 'components/footer.php'; ?>   

    <div class="wrapper-border"></div>

    </div>
<!-- END WRAPPER -->

</div>
<!-- END BG SHADOW -->

<!-- ANNOUNCEMENT JS -->
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
<script type='text/javascript' src='js/jquery.tipsy.js'></script>
<script type='text/javascript' src='js/jquery.cycle.min.js'></script>
<script type='text/javascript' src='js/shortcodes.js'></script>
<script type='text/javascript' src='js/jquery.custom.js'></script>
<script src="https://accounts.google.com/gsi/client"></script>


</body>
<!-- END BODY -->
</html>