<?php
require_once 'db.php';
require_once 'google_auth.php';
require_once 'comments.php';

// Check if there's an auth error message to display
$authError = $_GET['auth_error'] ?? null;
$authSuccess = isset($_GET['auth_success']);
?>

<!DOCTYPE html>
<html lang="en-US">
<!-- START HEAD -->
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

    <!-- START TOP BAR -->
    <div id="topbar">
        <div class="container">
            <div class="row">
                <div id="nav" class="span12 light">

                    <!-- START MAIN NAVIGATION -->
                    <ul id="menu-menu" class="level-1">
                        <li>
                            <a href="index.php" >HOME</a>
                        </li>
                        <li>
                            <a href="about.php">ABOUT</a>
                        </li>
                        <li>
                            <a href="program.php">PROGRAM</a>
                        </li>
                        <li>
                            <a href="portfolio.php">PORTFOLIO</a>
                        </li>
                        <li>
                            <a href="announcement.php">ANNOUNCEMENT</a>
                        </li>
                        <li>
                            <a href="e-service.php">E-SERVICE</a>
                        </li>
                        <li>
                            <a href="contact.php">CONTACT</a>
                        </li>
                    </ul>
                    <!-- END MAIN NAVIGATION -->
                </div>
            </div>
        </div>
    </div>
    <!-- END TOP BAR -->

    <!-- START HEADER -->
    <div id="header" class="group margin-bottom" style="padding-bottom: 30px;">

        <div class="group container">
            <div class="row" id="logo-headersidebar-container">
                <!-- START LOGO -->
                <div id="logo" class="span6 group">
                    <div id="logo-container">
                        <a id="logo-img" href="index.php" title="NCIP">
                            <img src="images/ncip-header-logo.png" title="NCIP" alt="NCIP Header Logo" />
                        </a>
                        <a id="second-logo-img" href="index.php" title="Bagong Pilipinas">
                            <img src="images/bagong-pilipinas-header-logo.png" title="Bagong Pilipinas" alt="Bagong Pilipinas Header Logo" />
                        </a>
                    </div>
                    <div id="textual">
                        <p id='tagline'>
                            REPUBLIC OF THE PHILIPPINES<br>
                            OFFICE OF THE PRESIDENT<br>
                            <strong>NATIONAL COMMISSION ON INDIGENOUS PEOPLES</strong>
                        </p>
                    </div>
                </div>                              
                <!-- END LOGO -->

                <!-- START HEADER SIDEBAR -->
                <div id="header-sidebar" class="span6 group">
                    <div class="widget-first widget header-text-image">
                        <div class="text-image" style="text-align:left">
                            <img src="images/phone1.png" alt="CUSTOMER SUPPORT" />
                        </div>

                        <div class="text-content">
                            <h3>CUSTOMER SUPPORT</h3>
                            <p>+63 &nbsp;998 - 765 - 4321</p>
                        </div>
                    </div>

                    <div class="widget-last widget widget_text">
                        <div class="textwidget">
                        <div class="socials-default-small facebook-small default">
                                <a href="https://www.facebook.com/CebuPO" target="_blank" class="socials-default-small default facebook" >facebook</a>
                            </div>

                            <div class="socials-default-small instagram-small default">
                                <a href="https://www.instagram.com/ncipcebupo/" target="_blank" class="socials-default-small default instagram" >instagram</a>
                            </div>

                            <div class="socials-default-small google-small default">
                            <a href="mailto:region67.cebupo@ncip.gov.ph" target="_blank" class="socials-default-small default google">Gmail</a>
                            </div>

                            <div class="socials-default-small twitter-small default">
                                <a href="#" class="socials-default-small default twitter" >twitter</a>
                            </div>

                            <div class="socials-default-small flickr-small default">
                                <a href="#" class="socials-default-small default flickr" >flickr</a>
                            </div>

                            <div class="socials-default-small rss-small default">
                                <a href="#" class="socials-default-small default rss" >rss</a>
                            </div>

                            <div class="socials-default-small pinterest-small default">
                                <a href="#" class="socials-default-small default pinterest" >pinterest</a>

                            </div>
                         </div>

                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- END HEADER -->

    <!-- SLOGAN -->
    <div class="slogan">
        <h2>OUR ANNOUNCEMENT & NEWS</h2>
        <h3>are you curious, yeah?</h3>
    </div>
    <!-- END SLOGAN  -->

    <!-- START PRIMARY -->
    <div id="primary" class="sidebar-right">
        <div class="container group">
            <div class="row">
            <!-- START CONTENT -->
            <div id="content-blog" class="span9 content group">

                <div class="post type-post status-publish format-standard category-design category-themes tag-corporate tag-minimal group blog-elegant">
                    <!-- post featured & title -->
                    <div class="thumbnail">
                        <div class="row">
                            <!-- post meta -->
                            <div class="meta group span3">
                                <div>
                                    <h2 class="post-title">
                                        <a href="https://ncip.gov.ph/news/advisory/" target="_blank">
                                            Advisory
                                        </a>
                                    </h2>

                                    <p class="author">
                                        <img src="images/icons/author.png" alt="icon-user"/><span>Author:</span>
                                        <a href="https://ncip.gov.ph/news-category/ncip-news/" title="Posts by NCIP Central Office News" target="_blank" rel="author">
                                            NCIP Central Office News
                                        </a>
                                    </p>

                                    <p class="date">
                                        <img src="images/icons/date.png" alt="icon-calendar"/>
                                        <span>Date:</span>
                                        April 15, 2025
                                    </p>
                                </div>
                                
                                <!-- Add comment section at the end of each post -->
                                <div class="comments-section" id="announcement-1">
                                    <h3>Comments</h3>
                                    
                                    <!-- Display comments for this specific announcement -->
                                    <div id="comments-container-1" class="comments-container">
                                        <?php echo renderComments(1); // Pass the announcement ID ?>
                                    </div>
                                    
                                    <!-- Add comment form for this specific announcement -->
                                    <?php echo renderCommentForm(1); // Pass the announcement ID ?>
                                </div>
                                
                                <div>
                                    <div class="socials">
                                        <h2>Share on</h2>

                                        <div class="socials-square-small facebook-small square">
                                            <a href="https://www.facebook.com/CebuPO" class="socials-square-small facebook" original-title="Facebook" target="_blank">facebook</a>
                                        </div>
                                        <div class="socials-square-small instagram-small square">
                                            <a href="https://www.instagram.com/ncipcebupo/" class="socials-square-small instagram" original-title="Instagram" target="_blank">instagram</a>
                                        </div>
                                        <div class="socials-square-small google-small square">
                                            <a href="https://ncip.gov.ph/" class="socials-square-small google" original-title="Google" target="_blank">google</a>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- post title -->
                            <div class="the-content span6">
                                <div>
                                    <img width="539" height="205" src="images/blog/advisory-539x205.jpg" class="thumbnail wp-post-image" alt="advisory"/>
                                    <!-- post content -->
                                    <div class="the-content">
                                        <p>On April 16, 2025, the National Commission on Indigenous Peoples – Central Office and Regional Offices will implement a work-from-home...</p>

                                        <p>
                                            <a href="https://ncip.gov.ph/news/advisory/" target="_blank" class="not-btn more-link">
                                                Read more
                                            </a>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="post type-post status-publish format-standard category-design category-themes tag-corporate tag-minimal group blog-elegant">
                    <!-- post featured & title -->
                    <div class="thumbnail">
                        <div class="row">
                            <!-- post meta -->
                            <div class="meta group span3">
                                <div>
                                    <h2 class="post-title">
                                        <a href="https://ncip.gov.ph/news/makasaysayang-araw-para-sa-mga-eskaya/" target="_blank">
                                            Makasaysayang araw para sa mga Eskaya!
                                        </a>
                                    </h2>

                                    <p class="author">
                                        <img src="images/icons/author.png" alt="icon-user"/><span>Author:</span>
                                        <a href="https://ncip.gov.ph/news-category/ncip-news/" title="Posts by NCIP Central Office News" target="_blank" rel="author">
                                            NCIP Central Office News
                                        </a>
                                    </p>

                                    <p class="date">
                                        <img src="images/icons/date.png" alt="icon-calendar"/>
                                        <span>Date:</span>
                                        March 28, 2025
                                    </p>
                                </div>
                                
                                
                                <!-- Add comment section at the end of each post -->
                                <div class="comments-section" id="announcement-2">
                                    <h3>Comments</h3>
                                    
                                    <!-- Display comments for this specific announcement -->
                                    <div id="comments-container-1" class="comments-container">
                                        <?php echo renderComments(2); ?>
                                    </div>
                                    
                                    <!-- Add comment form for this specific announcement -->
                                    <?php echo renderCommentForm(2); ?>
                                </div>
                                
                                <div>
                                    <div class="socials">
                                        <h2>Share on</h2>

                                        <div class="socials-square-small facebook-small square">
                                            <a href="https://www.facebook.com/CebuPO" class="socials-square-small facebook" original-title="Facebook" target="_blank">facebook</a>
                                        </div>
                                        <div class="socials-square-small instagram-small square">
                                            <a href="https://www.instagram.com/ncipcebupo/" class="socials-square-small instagram" original-title="Instagram" target="_blank">instagram</a>
                                        </div>
                                        <div class="socials-square-small google-small square">
                                            <a href="https://ncip.gov.ph/" class="socials-square-small google" original-title="Google" target="_blank">google</a>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- post title -->
                            <div class="the-content span6">
                                <div>
                                    <img width="539" height="205" src="images/blog/Makasaysayang-509x205.jpg" class="thumbnail wp-post-image" alt="makasaysayang-araw-para-sa-mga-eskaya"/>
                                    <!-- post content -->
                                    <div class="the-content">
                                        <p>Sinaksihan ni National Commission on Indigenous Peoples Chairperson Jennifer Pia Sibug-Las ang pormal na pagkakaloob ng Certificate of Recognition of...</p>

                                        <p>
                                            <a href="https://ncip.gov.ph/news/makasaysayang-araw-para-sa-mga-eskaya/" target="_blank" class="not-btn more-link">
                                                Read more
                                            </a>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="post type-post status-publish format-standard category-design category-themes tag-corporate tag-minimal group blog-elegant">
                    <!-- post featured & title -->
                    <div class="thumbnail">
                        <div class="row">
                            <!-- post meta -->
                            <div class="meta group span3">
                                <div>
                                    <h2 class="post-title">
                                        <a href="https://ncip.gov.ph/news/ncip-nakiisa-sa-forum-para-sa-kababaihan-mula-sa-ibat-ibang-sektor/" target="_blank">
                                            NCIP, nakiisa sa forum para sa kababaihan mula sa iba’t ibang sektor
                                        </a>
                                    </h2>

                                    <p class="author">
                                        <img src="images/icons/author.png" alt="icon-user"/><span>Author:</span>
                                        <a href="https://ncip.gov.ph/news-category/ncip-news/" title="Posts by NCIP Central Office News" target="_blank" rel="author">
                                            NCIP Central Office News
                                        </a>
                                    </p>

                                    <p class="date">
                                        <img src="images/icons/date.png" alt="icon-calendar"/>
                                        <span>Date:</span>
                                        March 27, 2025
                                    </p>
                                </div>
                                
                                <!-- Add comment section at the end of each post -->
                                <div class="comments-section" id="announcement-3">
                                    <h3>Comments</h3>
                                    
                                    <!-- Display comments for this specific announcement -->
                                    <div id="comments-container-1" class="comments-container">
                                        <?php echo renderComments(3); // Pass the announcement ID ?>
                                    </div>
                                    
                                    <!-- Add comment form for this specific announcement -->
                                    <?php echo renderCommentForm(3); // Pass the announcement ID ?>
                                </div>
                                
                                <div>
                                    <div class="socials">
                                        <h2>Share on</h2>

                                        <div class="socials-square-small facebook-small square">
                                            <a href="https://www.facebook.com/CebuPO" class="socials-square-small facebook" original-title="Facebook" target="_blank">facebook</a>
                                        </div>
                                        <div class="socials-square-small instagram-small square">
                                            <a href="https://www.instagram.com/ncipcebupo/" class="socials-square-small instagram" original-title="Instagram" target="_blank">instagram</a>
                                        </div>
                                        <div class="socials-square-small google-small square">
                                            <a href="https://ncip.gov.ph/" class="socials-square-small google" original-title="Google" target="_blank">google</a>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- post title -->
                            <div class="the-content span6">
                                <div>
                                    <img width="539" height="205" src="images/blog/kababaihan-509x205.jpg" class="thumbnail wp-post-image" alt="kababaihan-509x205"/>
                                    <!-- post content -->
                                    <div class="the-content">
                                        <p>Nakiisa ang National Commission on Indigenous Peoples (NCIP) sa 2025 Philippine Open Government Partnership (PH-OGP) National Women’s Month Forum na...</p>

                                        <p>
                                            <a href="https://ncip.gov.ph/news/ncip-nakiisa-sa-forum-para-sa-kababaihan-mula-sa-ibat-ibang-sektor/" target="_blank" class="not-btn more-link">
                                                Read more
                                            </a>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class='general-pagination group'>
                    <a href='#' class='selected'>1</a>
                    <a href='#'>2</a>
                    <a href='#'>&rsaquo;</a>
                </div>
            </div>
            <!-- END CONTENT -->


            <!-- START SIDEBAR -->
            <div id="sidebar-blog-sidebar" class="span3 sidebar group">
                <div class="widget-first widget recent-posts">
                    <h3>Popular <span class="title-highlight">readings</span></h3>
                    <div class="recent-post group">
                        <div class="hentry-post group">
                            <div class="thumb-img">
                                <img width="75" height="75" src="images/blog/advisory-75x75.jpg" class="attachment-blog_thumb wp-post-image" alt="23" />
                            </div>
                            <div class="text">
                                <a href="https://ncip.gov.ph/news/advisory/" target="_blank" title="ADVISORY" class="title">
                                    Advisory
                                </a>
                                <p class="post-date">November 15, 2025</p>
                            </div>
                        </div>
                        <div class="hentry-post group">
                            <div class="thumb-img">
                                <img width="75" height="75" src="images/blog/Makasaysayang-75x75.jpg" class="attachment-blog_thumb wp-post-image" alt="makasaysayang-araw-para-sa-mga-eskaya" />
                            </div>
                            <div class="text">
                                <a href="https://ncip.gov.ph/news/makasaysayang-araw-para-sa-mga-eskaya/" target="_blank" title="Makasaysayang araw para sa mga Eskaya!" class="title">
                                    Makasaysayang araw para sa mga Eskaya!
                                </a>
                                <p class="post-date">March 28, 2025</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="last-tweets-3" class="widget-2 widget last-tweets">
                    <h3></h3>
                    <div class="list-tweets-3"></div>
                    <p id="follow-twitter">
                        <a href="https://twitter.com/intent/user?screen_name=YIW" target="_blank">
                            Follow us on Twitter &rarr;
                        </a>

                    <script type="text/javascript">
                        jQuery(function($){
                            $('#last-tweets-3 .list-tweets-3').tweetable({
                                listClass: 'tweets-widget-3',
                                username: 'YIW',
                                time: false,
                                limit: 1,
                                replies: true
                            });
                        });
                    </script>
                </div>

                <div class="widget recent-comments">
    <h3>Recent <span class="title-highlight">comments</span></h3>
    
    <!-- Comments List -->
    <div id="recent-comments-container" class="recent-comments group">
        <?php echo renderRecentComments(5); ?>
    </div>
    
    <?php if (isUserLoggedIn()): ?>
    <div class="login-info">
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

                <div class="widget-last widget widget_nav_menu">
                    <h3>Follow <span class="title-highlight">us</span></h3>
                    <div class="menu-socialize-container">
                        <ul class="menu">

                            <li class="menu-item menu-item-type-custom">
                                <a href="https://www.facebook.com/CebuPO" target="_blank">Facebook</a>
                            </li>

                            <li class="menu-item menu-item-type-custom">
                                <a href="https://www.instagram.com/ncipcebupo/" target="_blank">Instragram</a>
                        </ul>
                    </div>
                </div>
            </div>
                <!-- END SIDEBAR -->
            </div>
        </div>
    </div>
    <!-- END PRIMARY -->

    <!-- START FOOTER -->
    <div id="footer">
        <div class="container">
            <div class="row">
                <div class="footer-widgets-area with-sidebar-right">
                    <div class="widget-first widget span2 widget_text">
                        <h3>
                            <img src="images/ncip-logo.png" alt="NCIP Logo" class="ncip-logo">
                            About NCIP <div class="underline2"><span></span></div>
                        </h3>
                        <div class="textwidget">
                            The National Commission on Indigenous Peoples (NCIP) is dedicated to the recognition, protection, and promotion of the rights of Indigenous Cultural Communities / Indigenous Peoples (ICCs/IPs) in the Philippines. Learn more about our mission <a href="#">here</a>.
                        </div>
                    </div>
    
                    <div class="widget span2 widget_nav_menu">
                        <h3>Quick Links <div class="underline"><span></span></div></h3>
                        <div class="menu-widget-footer-container">
                            <ul id="menu-widget-footer" class="menu">
                                <li class="menu-item"><a href="program.php">Programs</a></li>
                                <li class="menu-item"><a href="#">Services</a></li>
                                <li class="menu-item"><a href="#">Legal Framework</a></li>
                                <li class="menu-item"><a href="#">Reports</a></li>
                                <li class="menu-item"><a href="contact.php">Contact</a></li>
                            </ul>
                        </div>
                    </div>
    
                    <div class="widget-last widget span2 widget_nav_menu">
                        <h3>Follow Us <div class="underline"><span></span></div></h3>
                        <div class="menu-socialize-container">
                            <ul id="menu-socialize" class="menu">
                                <li class="menu-item"><a href="https://www.facebook.com/CebuPO" target="_blank">Facebook</a></li>
                                <li class="menu-item"><a href="mailto:region67.cebupo@ncip.gov.ph" target="_blank">Gmail</a></li>
                                <li class="menu-item"><a href="https://www.instagram.com/ncipcebupo/" target="_blank">Instagram</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
    
                <div class="footer-widgets-sidebar with-sidebar-right">
                    <div class="widget-first widget span6 yit_quick_contact">
                        <h3>Contact NCIP <div class="underline"><span></span></div></h3>
                        <form class="contact-form row" method="post" action="" enctype="multipart/form-data">
                            <fieldset>
                                <ul>
                                    <li class="text-field with-icon span3">
                                        <label><span class="mainlabel">Name</span></label>
                                        <div class="input-prepend">
                                            <span class="add-on"><img src="images/footer/author-footer.png" alt=""/></span>
                                            <input type="text" name="yit_contact[name]" class="with-icon required" value=""/>
                                        </div>
                                    </li>
    
                                    <li class="text-field with-icon span3">
                                        <label><span class="mainlabel">Email</span></label>
                                        <div class="input-prepend">
                                            <span class="add-on"><img src="images/footer/envelope-footer.png" alt=""/></span>
                                            <input type="text" name="yit_contact[email]" class="with-icon required email-validate" value=""/>
                                        </div>
                                    </li>
    
                                    <li class="textarea-field with-icon span6">
                                        <label><span class="mainlabel">Message</span></label>
                                        <div class="input-prepend">
                                            <span class="add-on"><img src="images/footer/pencil-footer.png" alt=""/></span>
                                            <textarea name="yit_contact[message]" rows="8" cols="30" class="with-icon required"></textarea>
                                        </div>
                                    </li>
    
                                    <li class="submit-button span6">
                                        <input type="submit" name="yit_sendemail" value="SEND" class="sendmail alignright"/>
                                    </li>
                                </ul>
                            </fieldset>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div id="lowfooter">
        <div class="container">
            <div class="row">
                <div class="center span12">
                    <p>&copy; 2025 NCIP Cebu Connect. All rights reserved.</p>
                </div>
            </div>
        </div>
    </div>    
    <!-- END FOOTER -->

    <div class="wrapper-border"></div>

    </div>
<!-- END WRAPPER -->

</div>
<!-- END BG SHADOW -->
<script type="text/javascript">
$(document).ready(function() {
    console.log('Document ready - initializing comment system');
    
    // Handle comment submission
    $('.comment-form').on('submit', function(e) {
        e.preventDefault();
        console.log('Comment form submitted');
        
        const form = $(this);
        const formData = form.serialize();
        const announcementId = form.data('announcement-id');
        const commentsContainer = $(`#comments-container-${announcementId}`);
        
        console.log('Form data:', formData);
        console.log('Announcement ID:', announcementId);
        
        $.ajax({
            type: 'POST',
            url: 'comments.php',
            data: formData,
            dataType: 'json',
            success: function(response) {
                console.log('Comment submission response:', response);
                
                if (response.success) {
                    // Clear the form
                    form.find('textarea').val('');
                    form.find('.parent-id-input').val('');
                    form.find('.reply-info').hide();
                    
                    // Reload the comments for this announcement
                    $.ajax({
                        type: 'GET',
                        url: `get_comments.php?announcement_id=${announcementId}`,
                        success: function(html) {
                            commentsContainer.html(html);
                            console.log('Comments reloaded');
                            
                            // Also reload recent comments in the sidebar
                            $.ajax({
                                type: 'GET',
                                url: 'get_recent_comments.php',
                                success: function(html) {
                                    $('#recent-comments-container').html(html);
                                    console.log('Recent comments reloaded');
                                },
                                error: function(xhr, status, error) {
                                    console.error('Error reloading recent comments:', error);
                                }
                            });
                        },
                        error: function(xhr, status, error) {
                            console.error('Error reloading comments:', error);
                        }
                    });
                } else {
                    alert(response.message || 'An error occurred while posting your comment.');
                }
            },
            error: function(xhr, status, error) {
                console.error('Error submitting comment:', error);
                alert('An error occurred while posting your comment. Please try again.');
            }
        });
    });
    
    // Handle reply links with delegated events (for dynamically added content)
    $(document).on('click', '.reply-link', function(e) {
        e.preventDefault();
        console.log('Reply link clicked');
        
        const commentId = $(this).data('comment-id');
        const announcementId = $(this).data('announcement-id');
        console.log('Comment ID:', commentId, 'Announcement ID:', announcementId);
        
        const form = $(`.comment-form[data-announcement-id="${announcementId}"]`);
        
        // Set the parent comment ID
        form.find('.parent-id-input').val(commentId);
        form.find('.reply-info').show();
        
        // Scroll to the comment form
        $('html, body').animate({
            scrollTop: form.offset().top - 100
        }, 500);
        
        form.find('textarea').focus();
    });
    
    // Cancel reply
    $(document).on('click', '.cancel-reply', function(e) {
        e.preventDefault();
        console.log('Cancel reply clicked');
        
        const form = $(this).closest('form');
        form.find('.parent-id-input').val('');
        form.find('.reply-info').hide();
    });
    
    // Add an error handler for images
    $('img.avatar').on('error', function() {
        $(this).attr('src', 'images/gavatar.png');
    });
    
    <?php if ($authError): ?>
    alert('Authentication error: <?php echo htmlspecialchars($authError); ?>');
    <?php endif; ?>
    
    <?php if ($authSuccess): ?>
    alert('Successfully signed in!');
    <?php endif; ?>
});
</script>

<script type='text/javascript' src='js/comment-reply.min.js'></script>
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