<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and is an admin
$is_admin = false;
$show_admin_bar = false;

if (isset($_SESSION["user_id"]) && isset($_SESSION["role"])) {
    $is_admin = strtoupper($_SESSION["role"]) === 'ADMIN';
    $show_admin_bar = $is_admin && !isset($_SESSION['temporary_role']);
}

if (isset($_SESSION["role"]) && strtoupper($_SESSION["role"]) === 'ADMIN' && !isset($_SESSION['temporary_role'])) {
    // Show an admin-specific option
    ?>
    <div class="admin-actions">
        <div class="admin-info">
            <h3>Admin Mode</h3>
            <p>You are viewing this page as an administrator</p>
        </div>
        <div class="admin-buttons">
            <a href="../admin/index.php" class="btn">
                <i class="bi bi-gear"></i> Return to Admin Panel
            </a>
        </div>
    </div>
    <?php
}

// Handle role switching if requested
if (isset($_GET['switch_role']) && $_GET['switch_role'] === 'USER' && $is_admin) {
    $_SESSION['temporary_role'] = true;
    $_SESSION['original_role'] = 'ADMIN';
    $_SESSION['temp_role'] = 'USER';
    // Reload page without the parameter
    header("Location: index.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
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
    <link rel='stylesheet' id='usquare-css-css'  href='sliders/usquare/css/frontend/usquare_style.css' type='text/css' media='all' />
    <link rel='stylesheet' id='google-fonts-css'  href='http://fonts.googleapis.com/css?family=Playfair+Display%7COpen+Sans+Condensed%3A300%7COpen+Sans%7CShadows+Into+Light%7CMuli%7CDroid+Sans%7CArbutus+Slab%7CAbel&#038;ver=3.5.1' type='text/css' media='all' />
    <link rel='stylesheet' id='responsive-css'  href='css/responsive.css' type='text/css' media='all' />
    <link rel='stylesheet' id='polaroid-slider-css'  href='sliders/polaroid/css/polaroid.css' type='text/css' media='all' />
    <link rel='stylesheet' id='ahortcodes-css'  href='css/shortcodes.css' type='text/css' media='all' />
    <link rel='stylesheet' id='contact-form-css'  href='css/contact_form.css' type='text/css' media='all' />
    <link rel='stylesheet' id='custom-css'  href='css/custom.css' type='text/css' media='all' />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style type="text/css">
        body { background-color: #ffffff; background-image: url('images/bg-pattern.png'); background-repeat: repeat; background-position: top left; background-attachment: scroll; }
        
        /* Add these admin styles */
        .admin-actions {
            background: #152f49;
            color: #fff;
            padding: 15px 20px;
            margin-bottom: 25px;
            position: relative;
            border-bottom: 3px solid #f8c301;
            display: flex;
            justify-content: space-between;
            align-items: center;
            z-index: 1000;
        }

        .admin-info {
            display: flex;
            flex-direction: column;
            margin-right: 30px;
        }

        .admin-info h3 {
            margin: 0 0 8px 0;
            font-size: 20px;
            font-weight: 600;
            color: #f8c301;
        }

        .admin-info p {
            margin: 0;
            font-size: 15px;
            color: #ffffff;
        }

        .admin-actions .admin-buttons {
            display: flex;
            gap: 15px;
        }

        .admin-actions .btn {
            background-color: #ffffff;
            color: #152f49;
            padding: 10px 20px;
            border-radius: 4px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.2s ease;
            border: none;
            display: flex;
            align-items: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            font-size: 15px;
            white-space: nowrap;
        }

        .admin-actions .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.25);
        }

        .admin-actions .btn-primary {
            background-color: #f8c301;
            color: #ffffff;
        }

        .admin-actions .btn-primary:hover {
            background-color: #e6b000 !important;
            color: #ffffff !important;
        }
        
        .admin-actions .btn i {
            margin-right: 8px;
            font-size: 16px;
        }

        @media (max-width: 768px) {
            .admin-actions {
                flex-direction: column;
                align-items: flex-start;
                padding: 15px;
            }
            
            .admin-info {
                margin-bottom: 15px;
                margin-right: 0;
            }
            
            .admin-buttons {
                width: 100%;
                justify-content: space-between;
            }
        }
    </style>

    <script type='text/javascript' src='js/jquery/jquery.js'></script>
</head>
<!-- END HEAD -->
<!-- START BODY -->
<body class="home page no_js responsive stretched">

<!-- START BG SHADOW -->
<div class="bg-shadow">

<!-- START WRAPPER -->
    <div id="wrapper" class="container group">

    <!-- SCROLL TO TOP BUTTON -->
    <?php include 'components/scroll-to-top.php'; ?>
    <!-- HEADER -->
    <?php include 'components/header.php'; ?>

        <!-- BEGIN FLEXSLIDER SLIDER -->
        <div id="slider-polaroid-0" class="slider slider-polaroid polaroid no-responsive" style="height:400px; margin-top: -20px; margin-bottom: 20px;">
            <div class="thumbs  container">
                <div class="thumb">
                    <img src="images/slider/flexslider/1-150x150.png" alt="images/slider/flexslider/1-150x150.png" />
                    <div class="slide-content container" style="width: 100%; height: 60vh; background-image: url('images/slider/flexslider/1.png'); background-size: cover; background-repeat: no-repeat; background-position: center; display: flex; justify-content: center; align-items: center;"></div>
                </div>

                <div class="thumb">
                    <img src="images/slider/flexslider/3-150x150.png" alt="images/slider/flexslider/3-150x150.png" />
                    <div class="slide-content container align-right" style="background-image:url('images/slider/flexslider/3.png');">
                        <div class="text" style="margin-left: auto; margin-top: -50px;">
                            <h2 style="font-size: 2rem; font-weight: 600; line-height: 1.5; color: black;">
                                <span style="font-size: 2.5rem; color: #ffffff;">“</span>
                                We do not inherit the Earth from our ancestors, we borrow it from our children
                                <span style="font-size: 2.5rem; color: #ffffff;">”</span>
                            </h2>
                            <p style="font-size: 15px; font-style: italic; color: #e5f99a; margin-top: 1rem;">
                                — Native American Proverb
                            </p>
                        </div>                
                    </div>
                </div>

                <div class="thumb">
                    <img src="images/slider/flexslider/ncip 150x150.png" alt="images/slider/flexslider/0052.jpg" />
                    <div class="slide-content container align-right full" style="background-image:url('images/slider/flexslider/ncip.png');">
                        <div class="container">
                            <div class="text">
                                <h2 style="font-family: 'Arial', sans-serif; font-size: 24px; color: #FFF; text-align: center;">
                                    Join Us in Celebrating Indigenous Peoples' Heritage!
                                </h2>
                                <p style="font-family: 'Georgia', serif; font-size: 16px; color: #FFF; line-height: 1.6; padding: 0 10px; ">
                                    Unite with Us for the 26th Annual Indigenous Peoples Month Celebration! Experience a vibrant showcase of culture, resilience, and community spirit.
                                </p>
                                <ul style="font-family: 'Georgia', serif; font-size: 16px; color: #FFF; line-height: 1.6; padding: 0 10px;">
                                    <li style="color: #FFF">Engage: Participate in a variety of activities that highlight the rich traditions, histories and continuing journey of Indigenous communities.</li>
                                    <li style="color: #FFF">Celebrate: Enjoy music, art, and food that honor the diverse cultures.</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="thumb">
                    <img src="images/slider/flexslider/4-150x150.png" alt="images/slider/flexslider/4-150x150.png" />
                    <div class="slide-content container align-left" style="background-image:url('images/slider/flexslider/4.png');">
                        <div class="text" style="margin-right: auto; margin-top: -50px;">
                            <h2 style="font-size: 1.5rem; font-weight: 600; line-height: 1.5; color: black;">
                                <span style="font-size: 2rem; color: #ffffff;">“</span>
                                Everything we need, we do and everything of our culture and tradition is connected into it. It serves as our market, hospital, church, and school. If we do not have land, we’ll lose everything
                                <span style="font-size: 2rem; color: #ffffff;">”</span>
                            </h2>
                            <p style="font-size: 15px; font-style: italic; color: #e5f99a; margin-top: 1rem;">
                                — Maniguon Hermino Opao
                            </p>
                        </div>                
                    </div>
                </div>
                
                <div class="thumb">
                    <img src="images/slider/flexslider/2-150x150.png" alt="images/slider/flexslider/2-150x150.png" />
                    <div class="slide-content container" style="width: 100%; height: 67vh; background-image: url('images/slider/flexslider/2.png'); background-size: cover; background-repeat: no-repeat; background-position: center; display: flex; justify-content: center; align-items: center;"></div>
                </div>

            </div>
        </div>

        <script type="text/javascript">
            jQuery(document).ready(function($){
                $('#slider-polaroid-0').polaroid({
                    animation: '',
                    pause: 8000,
                    animationSpeed: 800			    });
            });
        </script>

        <div class="mobile-slider">
            <div class="slider fixed-image container">
                <img src="images/slider/flexslider/fixed-polaroid.jpg" alt="" />
            </div>
        </div>
    </div>
    <!-- END HEADER -->

    <!-- START PRIMARY -->
    <div id="primary" class="sidebar-no">
        <div class="container group">
            <div class="row">
            <!-- START CONTENT -->
                <div id="content-home" class="span12 content group">
                    <div class="page type-page status-publish hentry group">
                        <div class="box-sections numbers-sections margin-bottom ">
                            <div class="number number-left number-zero"></div>
                            <div class="number number-right number-1"></div>
                            <h4>
                                Indigenous <span class="title-highlight">Rights</span>
                            </h4>
                            <p>
                                NCIP upholds the rights of Indigenous Cultural Communities/Indigenous Peoples (ICCs/IPs) through legal support and policy implementation.
                            </p>
                        </div>
                        
                        <div class="box-sections numbers-sections margin-bottom ">
                            <div class="number number-left number-zero"></div>
                            <div class="number number-right number-2"></div>
                            <h4>
                                Cultural <span class="title-highlight">Preservation</span>
                            </h4>
                            <p>
                                Programs dedicated to preserving ancestral traditions, languages, and indigenous knowledge systems across the regions.
                            </p>
                        </div>
                        
                        <div class="box-sections numbers-sections margin-bottom ">
                            <div class="number number-left number-zero"></div>
                            <div class="number number-right number-3"></div>
                            <h4>
                                Ancestral <span class="title-highlight">Land Recognition</span>
                            </h4>
                            <p>
                                Assisting IP communities in the titling and recognition of ancestral domains through legal processes and documentation.
                            </p>
                        </div>
                        
                        <div class="box-sections numbers-sections margin-bottom last">
                            <div class="number number-left number-zero"></div>
                            <div class="number number-right number-4"></div>
                            <h4>
                                Community <span class="title-highlight">Empowerment</span>
                            </h4>
                            <p>
                                Livelihood, education, and health programs aimed at uplifting Indigenous Peoples while respecting their culture and values.
                            </p>
                        </div>
                        
                        <div class="clear"></div>
                        <div class="row">
                            <!-- START SECTION BLOG -->
                            <div class="section blog margin-bottom span12">
                                <h2 class="title">
                                    From the <span class="title-highlight">NCIP Blog</span>
                                </h2>
                                <div class="row">
                                    <div class="post type-post status-publish format-video category-design span6 sticky">
                                        <div class="row">
                                            <div class="thumbnail span3">
                                                <img width="263" height="293" src="images/3-263x243.jpg" class="attachment-section_blog wp-post-image" alt="NCIP Cultural Event" />
                                                <div class="date span1">
                                                    <p>
                                                        <span class="month">Mar</span>
                                                        <span class="day">12</span>
                                                    </p>
                                                </div>
                                            </div>
                            
                                            <div class="the-content span3">
                                                <h4>
                                                    <a href="http://mirror.pia.gov.ph/news/2022/11/18/ncip-hosts-national-summit-for-welfare-of-ips-iccs" target="_blank" title="NCIP Hosts Cultural Empowerment Summit">
                                                        NCIP Hosts Cultural Empowerment Summit
                                                    </a>
                                                </h4>
                            
                                                <p>
                                                    Over 200 delegates from indigenous communities gathered to discuss cultural preservation, land rights, and policy advocacy.
                                                </p>
                            
                                                <p>
                                                    The summit highlighted ongoing challenges and celebrated successes in maintaining ancestral heritage and empowering IP youth.
                                                </p>
                            
                                                <p>
                                                    <a href="http://mirror.pia.gov.ph/news/2022/11/18/ncip-hosts-national-summit-for-welfare-of-ips-iccs" target="_blank" class="more-link">|| read the article</a>
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                            
                                    <div class="post type-post status-publish format-standard category-news span3">
                                        <div class="row">
                                            <div class="date span1">
                                                <p>
                                                    <span class="month">Feb</span>
                                                    <span class="day">28</span>
                                                </p>
                                            </div>
                            
                                            <div class="meta span2">
                                                <h4>
                                                    <a href="#" title="NCIP Partners with LGUs for IP Livelihood Programs">
                                                        NCIP Partners with LGUs for IP Livelihood Programs
                                                    </a>
                                                </h4>
                                                <p class="author">by <strong>NCIP Region VII</strong></p>
                                                <!-- <p class="comments">
                                                    <a href="#respond" title="Comment on NCIP Livelihood Programs">
                                                        <strong>Comments:</strong> 3
                                                    </a>
                                                </p> -->
                                            </div>
                                        </div>
                                    </div>
                            
                                    <div class="post type-post status-publish format-audio category-themes span3">
                                        <div class="row">
                                            <div class="date span1">
                                                <p>
                                                    <span class="month">Jan</span>
                                                    <span class="day">22</span>
                                                </p>
                                            </div>
                            
                                            <div class="meta span2">
                                                <h4>
                                                    <a href="#" title="Voices of the Cordillera: Indigenous Stories on Air">
                                                        Voices of the Cordillera: Indigenous Stories on Air
                                                    </a>
                                                </h4>
                                                <p class="author">by <strong>NCIP Media Team</strong></p>
                                                <!-- <p class="comments">
                                                    <a href="#respond" title="Comment on Voices of the Cordillera">
                                                        <strong>Comments:</strong> 2
                                                    </a>
                                                </p> -->
                                            </div>
                                        </div>
                                    </div>
                            
                                    <div class="post type-post status-publish format-quote category-uncategorized span3">
                                        <div class="row">
                                            <div class="date span1">
                                                <p>
                                                    <span class="month">Dec</span>
                                                    <span class="day">15</span>
                                                </p>
                                            </div>
                            
                                            <div class="meta span2">
                                                <h4>
                                                    <a href="#" title="“Our culture is our strength.” - Tribal Elder">
                                                        “Our culture is our strength.” – Tribal Elder
                                                    </a>
                                                </h4>
                                                <p class="author">by <strong>NCIP Quote Archive</strong></p>
                                                <!-- <p class="comments">
                                                    <a href="#respond" title="Comment on Tribal Elder Quote">
                                                        <strong>Comments:</strong> 0
                                                    </a>
                                                </p> -->
                                            </div>
                                        </div>
                                    </div>
                            
                                    <div class="post type-post status-publish format-gallery category-design category-themes tag-ip tag-culture span3">
                                        <div class="row">
                                            <div class="date span1">
                                                <p>
                                                    <span class="month">Nov</span>
                                                    <span class="day">03</span>
                                                </p>
                                            </div>
                            
                                            <div class="meta span2">
                                                <h4>
                                                    <a href="#" title="Gallery: Indigenous Day Celebration Highlights">
                                                        Gallery: Indigenous Day Celebration Highlights
                                                    </a>
                                                </h4>
                                                <p class="author">by <strong>NCIP Documentation Team</strong></p>
                                                <!-- <p class="comments">
                                                    <a href="#comments" title="Comment on Indigenous Day Celebration">
                                                        <strong>Comments:</strong> 5
                                                    </a>
                                                </p> -->
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- END SECTION BLOG -->    

                            <div class="clear"></div>
                        </div>

                        <div class="clear"></div>

                        <div class="margin-bottom" id="partnerships">
                            <div class="logos-slider wrapper">
                                <h2>
                                    Our <span class="title-highlight">partners</span>
                                </h2>
                                <div class="list_carousel">
                                    <ul class="logos-slides">

                                        <li style="height: 70px ;">
                                            <a href="https://cnu.edu.ph/" target="_blank" class="bwWrapper" >
                                                <img src="images/slider/cnu.png" title="Cebu Normal University" style="max-height: 70px;" class="logo" />
                                            </a>
                                        </li>

                                        <li style="height: 70px ;">
                                            <a href="https://www.ctu.edu.ph/" target="_blank" class="bwWrapper" >
                                                <img src="images/slider/ctu.png" title="Cebu Technological University" style="max-height: 70px;" class="logo" />
                                            </a>
                                        </li>

                                        <li style="height: 70px ;">
                                            <a href="https://usc.edu.ph/" target="_blank" class="bwWrapper" >
                                                <img src="images/slider/usc.png" title="University of San Carlos" style="max-height: 70px;" class="logo" />
                                            </a>
                                        </li>

                                        <li style="height: 70px ;">
                                            <a href="https://www.dilg.gov.ph/" target="_blank" class="bwWrapper" >
                                                <img src="images/slider/dilg.png" title="Department of the Interior and Local Government" style="max-height: 70px;" class="logo" />
                                            </a>
                                        </li>

                                        <li style="height: 70px;">
                                            <a href="https://beta.tourism.gov.ph/" target="_blank" class="bwWrapper" >
                                                <img src="images/slider/dot.png" title="Department of Tourism" style="max-height: 70px;" class="logo" />
                                            </a>
                                        </li>

                                        <li style="height: 70px;">
                                            <a href="https://chr.gov.ph/" target="_blank" class="bwWrapper" >
                                                <img src="images/slider/chr.png" title="Commission on Human Rights" style="max-height: 70px;" class="logo" />
                                            </a>
                                        </li>

                                        <li style="height: 70px;">
                                            <a href="https://ibp.ph/" target="_blank" class="bwWrapper" >
                                                <img src="images/slider/ibp.png" title="Integrated Bar of the Philippines" style="max-height: 70px;" class="logo" />
                                            </a>
                                        </li>

                                        <li style="height: 70px;">
                                            <a href="https://nda.da.gov.ph/da-nda-celebrates-28-years-of-significant-strides-in-ph-dairy-industry/" target="_blank" class="bwWrapper" >
                                                <img src="images/slider/nda.png" title="National Diary Authority" style="max-height: 70px;" class="logo" />
                                            </a>
                                        </li>

                                        <li style="height: 70px;">
                                            <a href="https://www.undp.org/philippines" target="_blank" class="bwWrapper" >
                                                <img src="images/slider/undp.png" title="United Nations Development Programme" style="max-height: 70px;" class="logo" />
                                            </a>
                                        </li>

                                        <li style="height: 70px;">
                                            <a href="https://www.dswd.gov.ph/" target="_blank" class="bwWrapper" >
                                               <img src="images/slider/dswd.png" title="Department of Social Welfare and Development" style="max-height: 70px;" class="logo" />
                                            </a>
                                        </li>

                                        <li style="height: 70px;">
                                            <a href="https://www.pids.gov.ph/" target="_blank" class="bwWrapper" >
                                                <img src="images/slider/pids.png" title="Philippine Institute for Development Studies" style="max-height: 70px;" class="logo" />
                                            </a>
                                        </li>

                                        <li style="height: 70px;">
                                            <a href="https://www.universityofcebu.net/" target="_blank" class="bwWrapper" >
                                                <img src="images/slider/uc.png" title="University of Cebu" style="max-height: 70px;" class="logo" />
                                            </a>
                                        </li>

                                    </ul>
                                </div>
                                <div class="clear"></div>
                                <div class="nav">
                                    <a class="prev" href="#"></a>
                                    <a class="next" href="#"></a>
                                </div>
                                <div class="clear"></div>
                            </div>
                            <div class="clear"></div>
                        </div>
                        <div class="clear"></div>

                        <script type="text/javascript">
                            jQuery(function($){

                                $('.logos-slides').imagesLoaded(function(){
                                    $('.logos-slides').carouFredSel({
                                        auto: true,
                                        width: '100%',
                                        prev: '.logos-slider .prev',
                                        next: '.logos-slider .next',
                                        swipe: {
                                            onTouch: true
                                        },
                                        scroll : {
                                            items     : 1,
                                            duration  : 500
                                        }
                                    });
                                });

                                $('.bwWrapper').BlackAndWhite({
                                    hoverEffect : true,
                                    webworkerPath : false,
                                    responsive: true,
                                    speed: {
                                        fadeIn: 200,
                                        fadeOut: 300
                                    }
                                });

                                // Prevent default click on placeholder links
                                $("a.bwWrapper[href='#']").click(function(){ return false });

                                // Add cursor pointer on hover
                                $('.bwWrapper').css('cursor', 'pointer');
                            });
                        </script>

                    </div>
                    <!-- START COMMENTS -->
                    <div id="comments"></div>
                    <!-- END COMMENTS -->
                </div>
            <!-- END CONTENT -->
            </div>
        </div>
    </div>
    <!-- END PRIMARY -->

    <!-- FOOTER -->
    <?php include 'components/footer.php'; ?>

    <div class="wrapper-border"></div>

    </div>
<!-- END WRAPPER -->

</div>
<!-- END BG SHADOW -->

<script type='text/javascript' src='js/comment-reply.min.js'></script>
<script type='text/javascript' src='js/underscore.min.js'></script>
<script type='text/javascript' src='js/jquery/jquery.masonry.min.js'></script>
<script type='text/javascript' src='sliders/polaroid/js/jquery.polaroid.js'></script>
<script type='text/javascript' src='js/jquery.colorbox-min.js'></script>
<script type='text/javascript' src='js/jquery.easing.js'></script>
<script type='text/javascript' src='js/jquery.carouFredSel-6.1.0-packed.js'></script>
<script type='text/javascript' src='js/jQuery.BlackAndWhite.js'></script>
<script type='text/javascript' src='js/jquery.touchSwipe.min.js'></script>
<script type='text/javascript' src='sliders/polaroid/js/jquery.transform-0.8.0.min.js'></script>
<script type='text/javascript' src='sliders/polaroid/js/jquery.preloader.js'></script>
<script type='text/javascript' src='js/responsive.js'></script>
<script type='text/javascript' src='js/mobilemenu.js'></script>
<script type='text/javascript' src='js/jquery.superfish.js'></script>
<script type='text/javascript' src='js/jquery.placeholder.js'></script>
<script type='text/javascript' src='js/contact.js'></script>
<script type='text/javascript' src='js/jquery.tipsy.js'></script>
<script type='text/javascript' src='js/jquery.cycle.min.js'></script>
<script type='text/javascript' src='js/shortcodes.js'></script>
<script type='text/javascript' src='js/jquery.custom.js'></script>

</body>
<!-- END BODY -->
</html>