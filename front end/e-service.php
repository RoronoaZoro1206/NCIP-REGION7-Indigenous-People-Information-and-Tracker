<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION["user"])) {
    header("Location: form-content.php?show=login");
    exit();
}
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
    <link rel='stylesheet' id='custom-css'  href='css/custom.css' type='text/css' media='all' />
    <link rel="stylesheet" id="form-container" href="css/form.css">

    <style type="text/css">
        body { background-color: #ffffff; background-image: url('images/bg-pattern.png'); background-repeat: repeat; background-position: top left; background-attachment: scroll; }
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
                        <li>
                            <a href="logout.php">LOG-OUT</a>
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
        
    <!-- START PAGE META -->
    <div id="page-meta" class="group">
        <div class="container">
            <div class="row">
                <div class="span12">
                    <!-- TITLE -->
                    <div class="title">
                        <h1>E-Service</h1>
                    </div>
                
                    <!-- BREDCRUMB -->          
                    <div class="breadcrumbs">
                        <span class="before-text">You are here:</span>
                        <p id="yit-breadcrumb" itemprop="breadcrumb">
                        <a class="home" href="index.php">Home Page</a> |  
                            <a class="no-link current" href="#">E-Service</a>
                        </p>        
                    </div>  
                </div>
            </div>
        </div>
    </div>
    <!-- END PAGE META -->
        
    <!-- START PRIMARY -->
    <div class="form-container">
        <header>Scholarship Application Form</header>
        <form action="#" class="form">
            <div class="input-box">
                <label>Full Name</label>
                <input type="text" placeholder="Enter full name" required />
            </div>
            <div class="input-box">
                <label>Email Address</label>
                <input type="text" placeholder="Enter email address" required />
            </div>
            <div class="column">
                <div class="input-box">
                    <label>Phone Number</label>
                    <input type="number" placeholder="Enter phone number" required />
                </div>
                <div class="input-box">
                    <label>Birth Date</label>
                    <input type="date" placeholder="Enter birth date" required />
                </div>
            </div>
            <div class="gender-box">
                <h3>Gender</h3>
                <div class="gender-option">
                    <div class="gender">
                        <input type="radio" id="check-male" name="gender" checked />
                        <label for="check-male">Male</label>
                    </div>
                    <div class="gender">
                        <input type="radio" id="check-female" name="gender" />
                        <label for="check-female">Female</label>
                    </div>
                    <div class="gender">
                        <input type="radio" id="check-other" name="gender" />
                        <label for="check-other">Prefer not to say</label>
                    </div>
                </div>
            </div>
            <div class="input-box address">
                <label>Address</label>
                <input type="text" placeholder="Enter street address" required />
                <input type="text" placeholder="Enter street address line 2" required />
                <div class="column">
                    <div class="select-box">
                        <select>
                            <option hidden>Country</option>
                            <option>America</option>
                            <option>Japan</option>
                            <option>India</option>
                            <option>Nepal</option>
                        </select>
                    </div>
                    <input type="text" placeholder="Enter your city" required />
                </div>
                <div class="column">
                    <input type="text" placeholder="Enter your region" required />
                    <input type="number" placeholder="Enter postal code" required />
                </div>
            </div>
            <button>Submit</button>
        </form>
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
<script type='text/javascript' src='js/jquery.placeholder.js'></script>
<script type='text/javascript' src='js/contact.js'></script>
<script type='text/javascript' src='js/jquery.tipsy.js'></script>
<script type='text/javascript' src='js/jquery.cycle.min.js'></script>
<script type='text/javascript' src='js/jquery.colorbox-min.js'></script>
<script type='text/javascript' src='js/shortcodes.js'></script>
<script type='text/javascript' src='js/jquery.custom.js'></script>

</body>
<!-- END BODY -->
</html>