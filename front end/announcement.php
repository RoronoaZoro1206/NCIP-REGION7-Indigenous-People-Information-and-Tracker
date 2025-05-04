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
                                        <?php echo renderComments(1); ?>
                                    </div>
                                    
                                    <!-- Add comment form for this specific announcement -->
                                    <?php echo renderCommentForm(1); ?>
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
                                            <a href="https://ncip.gov.ph/news/advisory/" target="_blank" class="not-btn more-link" data-announcement-id="1">
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
                                    <div id="comments-container-2" class="comments-container">
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
                                            <a href="https://ncip.gov.ph/news/makasaysayang-araw-para-sa-mga-eskaya/" target="_blank" class="not-btn more-link" data-announcement-id="2">
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
                                    <div id="comments-container-3" class="comments-container">
                                        <?php echo renderComments(3); ?>
                                    </div>
                                    
                                    <!-- Add comment form for this specific announcement -->
                                    <?php echo renderCommentForm(3); ?>
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
                                            <a href="https://ncip.gov.ph/news/ncip-nakiisa-sa-forum-para-sa-kababaihan-mula-sa-ibat-ibang-sektor/" target="_blank" class="not-btn more-link" data-announcement-id="3">
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
                    <a href='announcement.php' class='selected'>1</a>
                    <a href='#'>2</a>
                    <a href='#'>&rsaquo;</a>
                </div>
            </div>
            <!-- END CONTENT -->


            <!-- START SIDEBAR -->
            <div id="sidebar-blog-sidebar" class="span3 sidebar group">
                <div class="widget-first widget recent-posts">
                    <h3>Popular <span class="title-highlight">readings now</span></h3>
                <div class="recent-post group">
                    <?php 
                        require_once 'popular_readings.php';
                        echo renderPopularReadings(2); 
                    ?>
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
// Fix both view count tracking and profile image issues
document.addEventListener('DOMContentLoaded', function() {
    console.log('Document ready - initializing comment system');
    
    // Initial setup - hide extra comments
    setupCommentVisibility();

    // Handle comment submission
    document.querySelectorAll('.comment-form').forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            console.log('Comment form submitted');
            
            const formData = new FormData(this);
            const announcementId = this.dataset.announcementId;
            const commentsContainer = document.querySelector(`#comments-container-${announcementId}`);
            
            fetch('comments.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(response => {
                if (response.success) {
                    console.log('Comment posted successfully, updating comments...');
                    this.querySelector('textarea').value = '';
                    this.querySelector('.parent-id-input').value = '';
                    
                    // Reset form to comment mode if it was in reply mode
                    if (this.classList.contains('replying-mode')) {
                        document.querySelectorAll('.comment-item').forEach(item => {
                            item.classList.remove('being-replied-to');
                        });
                        this.querySelector('.reply-info').style.display = 'none';
                        this.querySelector('textarea').placeholder = 'Write your comment here...';
                        this.querySelector('button[type="submit"]').textContent = 'Post Comment';
                        this.classList.remove('replying-mode');
                    }
                    
                    // Reload comments for this announcement
                    fetch(`get_comments.php?ann_id=${announcementId}`)
                        .then(res => res.text())
                        .then(html => {
                            commentsContainer.innerHTML = html;
                            setupCommentVisibility();
                            // Fix any broken images in the new comments
                            fixProfileImages();
                        });
                    
                    // Also refresh the recent comments sidebar
                    autoRefreshRecentComments();
                    
                } else {
                    alert(response.message || 'An error occurred while posting your comment.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred. Please try again.');
            });
        });
    });
    
    // Handle reply link clicks
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('reply-link')) {
            e.preventDefault();
            const commentId = e.target.dataset.commentId;
            const announcementId = e.target.dataset.announcementId;
            const commentAuthor = e.target.closest('.comment-item').querySelector('.author strong').textContent;
            
            // Remove existing reply status
            document.querySelectorAll('.comment-item').forEach(item => {
                item.classList.remove('being-replied-to');
            });
            e.target.closest('.comment-item').classList.add('being-replied-to');

            const form = document.querySelector(`.comment-form[data-announcement-id="${announcementId}"]`);
            form.querySelector('.comment-mode-text').textContent = 'Replying';
            form.querySelector('.parent-id-input').value = commentId;
            form.querySelector('.reply-info span').textContent = `Replying to ${commentAuthor}`;
            form.querySelector('.reply-info').style.display = 'block';
            form.querySelector('textarea').placeholder = `Write your reply to ${commentAuthor} here...`;
            form.querySelector('button[type="submit"]').textContent = 'Post Reply';
            form.classList.add('replying-mode');

            // Scroll to form
            form.scrollIntoView({ behavior: 'smooth', block: 'start' });
            form.querySelector('textarea').focus();
        }
    });

    // Handle cancel reply
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('cancel-reply')) {
            e.preventDefault();
            document.querySelectorAll('.comment-item').forEach(item => {
                item.classList.remove('being-replied-to');
            });
            const form = e.target.closest('form');
            form.querySelector('.comment-mode-text').textContent = 'Commenting';
            form.querySelector('.parent-id-input').value = '';
            form.querySelector('.reply-info').style.display = 'none';
            form.querySelector('textarea').placeholder = 'Write your comment here...';
            form.querySelector('button[type="submit"]').textContent = 'Post Comment';
            form.classList.remove('replying-mode');
        }
    });
    
    // Add global click handler for show more/less buttons
    document.addEventListener('click', function(e) {
        // Handle "Show more" button clicks
        if (e.target.classList.contains('show-more-comments')) {
            console.log('Show more clicked');
            const container = e.target.parentElement;
            const isRecent = container.id === 'recent-comments-container';
            const comments = container.querySelectorAll(isRecent ? '.recent-comment-item.hidden' : '.comment-item.hidden');
            
            // Show all hidden comments
            comments.forEach(comment => {
                comment.classList.remove('hidden');
                comment.classList.add('visible');
            });
            
            // Change button text and class
            e.target.textContent = 'Show less';
            e.target.classList.remove('show-more-comments');
            e.target.classList.add('show-less-comments');
        }
        
        // Handle "Show less" button clicks
        else if (e.target.classList.contains('show-less-comments')) {
            console.log('Show less clicked');
            const container = e.target.parentElement;
            const isRecent = container.id === 'recent-comments-container';
            const comments = container.querySelectorAll(isRecent ? '.recent-comment-item' : '.comment-item');
            const commentCount = comments.length;
            
            comments.forEach((comment, index) => {
                if (index >= 2) {
                    comment.classList.add('hidden');
                    comment.classList.remove('visible');
                }
            });
            
            // Change button text and class
            e.target.textContent = `Show more (${commentCount - 2} more)`;
            e.target.classList.remove('show-less-comments');
            e.target.classList.add('show-more-comments');
        }
    });
    
    // Track clicks on all "Read more" links
    document.querySelectorAll('.not-btn.more-link').forEach(link => {
        // Add data-announcement-id attributes if missing
        if (!link.hasAttribute('data-announcement-id')) {
            let url = link.getAttribute('href');
            let announcementId = 0;
            
            if (url.includes('advisory')) {
                announcementId = 1;
                link.setAttribute('data-announcement-id', '1');
            } else if (url.includes('makasaysayang-araw-para-sa-mga-eskaya')) {
                announcementId = 2;
                link.setAttribute('data-announcement-id', '2');
            } else if (url.includes('ncip-nakiisa-sa-forum-para-sa-kababaihan-mula-sa-ibat-ibang-sektor')) {
                announcementId = 3;
                link.setAttribute('data-announcement-id', '3');
            }
        }
        
        link.addEventListener('click', function(e) {
            const announcementId = this.getAttribute('data-announcement-id');
            
            if (announcementId) {
                // Send tracking data
                fetch('track_clicks.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'ann_id=' + announcementId
                })
                .then(response => response.json())
                .then(data => {
                    console.log('Click tracked:', data);
                    
                    // Update the view count in the UI without refreshing
                    updateViewCountUI(announcementId);
                    
                    // Update popular readings section
                    updatePopularReadings();
                })
                .catch(error => {
                    console.error('Error tracking click:', error);
                });
            }
        });
    });
    
    // Function to update view count in UI
    function updateViewCountUI(announcementId) {
        // Fetch the updated count from the server
        fetch('get_view_count.php?ann_id=' + announcementId)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Find and update all instances of this announcement's view count
                    const viewElements = document.querySelectorAll(`.view-count[data-announcement-id="${announcementId}"]`);
                    viewElements.forEach(element => {
                        element.innerHTML = `<small>(${data.count} ${data.count <= 1 ? 'view' : 'views'})</small>`;
                    });
                    
                    // Also check in the popular readings sidebar
                    const popularReadings = document.querySelectorAll('.recent-post .hentry-post');
                    popularReadings.forEach(reading => {
                        const link = reading.querySelector('a.title');
                        if (link) {
                            let url = link.getAttribute('href');
                            let readingId = 0;
                            
                            if (url.includes('advisory')) {
                                readingId = 1;
                            } else if (url.includes('makasaysayang-araw-para-sa-mga-eskaya')) {
                                readingId = 2;
                            } else if (url.includes('ncip-nakiisa-sa-forum-para-sa-kababaihan-mula-sa-ibat-ibang-sektor')) {
                                readingId = 3;
                            }
                            
                            if (readingId == announcementId) {
                                const viewCount = reading.querySelector('.view-count');
                                if (viewCount) {
                                    viewCount.innerHTML = `<small>(${data.count} ${data.count <= 1 ? 'view' : 'views'})</small>`;
                                }
                            }
                        }
                    });
                }
            })
            .catch(error => {
                console.error('Error fetching updated view count:', error);
            });
    }
    
    // Function to update popular readings section
    function updatePopularReadings() {
        fetch('get_popular_readings.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const popularReadingsContainer = document.querySelector('.recent-post.group');
                    if (popularReadingsContainer) {
                        popularReadingsContainer.innerHTML = data.html;
                        console.log('Popular readings updated!');
                        
                        // Fix any broken images in the updated section
                        fixProfileImages();
                        
                        // Re-add data attributes to the new elements
                        document.querySelectorAll('.recent-post .hentry-post').forEach(post => {
                            const link = post.querySelector('a.title');
                            const viewCount = post.querySelector('.view-count');
                            
                            if (link && viewCount && !viewCount.hasAttribute('data-announcement-id')) {
                                let url = link.getAttribute('href');
                                let announcementId = 0;
                                
                                if (url.includes('advisory')) {
                                    announcementId = 1;
                                } else if (url.includes('makasaysayang-araw-para-sa-mga-eskaya')) {
                                    announcementId = 2;
                                } else if (url.includes('ncip-nakiisa-sa-forum-para-sa-kababaihan-mula-sa-ibat-ibang-sektor')) {
                                    announcementId = 3;
                                }
                                
                                if (announcementId > 0) {
                                    viewCount.setAttribute('data-announcement-id', announcementId);
                                }
                            }
                        });
                    }
                }
            })
            .catch(error => {
                console.error('Error updating popular readings:', error);
            });
    }
    
    // Fix profile image loading issues
    function fixProfileImages() {
        document.querySelectorAll('img.avatar, img.photo').forEach(img => {
            // Add error handler to all avatar images
            img.onerror = function() {
                // Replace with default avatar if loading fails
                console.log('Image failed to load, replacing with default:', this.src);
                this.src = 'images/gavatar.png';
                this.onerror = null; // Prevent infinite loop
            };
            
            // If image is already broken, fix it now
            if (img.complete && (img.naturalWidth === 0 || img.naturalHeight === 0)) {
                console.log('Fixing already broken image:', img.src);
                img.src = 'images/gavatar.png';
            }
        });
    }
    
    // Fix profile images on page load
    fixProfileImages();
    
    // Add data attributes to view count elements if missing
    document.querySelectorAll('.recent-post .hentry-post').forEach(post => {
        const link = post.querySelector('a.title');
        const viewCount = post.querySelector('.view-count');
        
        if (link && viewCount && !viewCount.hasAttribute('data-announcement-id')) {
            let url = link.getAttribute('href');
            let announcementId = 0;
            
            if (url.includes('advisory')) {
                announcementId = 1;
            } else if (url.includes('makasaysayang-araw-para-sa-mga-eskaya')) {
                announcementId = 2;
            } else if (url.includes('ncip-nakiisa-sa-forum-para-sa-kababaihan-mula-sa-ibat-ibang-sektor')) {
                announcementId = 3;
            }
            
            if (announcementId > 0) {
                viewCount.setAttribute('data-announcement-id', announcementId);
            }
        }
    });
    
    // Set interval to refresh comments every 30 seconds
    setInterval(autoRefreshRecentComments, 30000);
    
    // Also set interval to refresh popular readings
    setInterval(updatePopularReadings, 30000);
});

// Function to set up initial comment visibility
function setupCommentVisibility(specificContainer = null) {
    const containers = specificContainer ? 
        [specificContainer] : 
        document.querySelectorAll('.comments-container, #recent-comments-container');

    containers.forEach(container => {
        const isRecent = container.id === 'recent-comments-container';
        const comments = container.querySelectorAll(isRecent ? '.recent-comment-item' : '.comment-item');
        const commentCount = comments.length;

        if (commentCount > 2) {
            
            comments.forEach((comment, index) => {
                if (index >= 2) {
                    comment.classList.add('hidden');
                    comment.classList.remove('visible');
                } else {
                    comment.classList.remove('hidden');
                    comment.classList.add('visible');
                }
            });

            // Remove existing button if any
            const existingButton = container.querySelector('.show-more-comments, .show-less-comments');
            if (existingButton) {
                existingButton.remove();
            }
            
            // Add new "Show more" button
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'show-more-comments';
            button.textContent = `Show more (${commentCount - 2} more)`;
            container.appendChild(button);
        } else {
            // Remove any show more/less buttons if not needed
            const buttons = container.querySelectorAll('.show-more-comments, .show-less-comments');
            buttons.forEach(btn => btn.remove());
        }
    });
}

function autoRefreshRecentComments() {
    console.log('Fetching recent comments...');
    
    fetch('get_recent_comments.php')
        .then(response => response.text())
        .then(html => {
            const recentCommentsContainer = document.getElementById('recent-comments-container');
            if (recentCommentsContainer) {
                recentCommentsContainer.innerHTML = html;
                setupCommentVisibility(recentCommentsContainer); 
                console.log('Recent comments updated successfully');
                // Fix any broken images in the new comments
                document.querySelectorAll('img.avatar, img.photo').forEach(img => {
                    img.onerror = function() {
                        this.src = 'images/gavatar.png';
                        this.onerror = null;
                    };
                });
            }
        })
        .catch(error => {
            console.error('Error fetching recent comments:', error);
        });
}
</script>

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