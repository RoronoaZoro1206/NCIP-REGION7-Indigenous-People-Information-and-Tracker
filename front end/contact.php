<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
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
    <link rel='stylesheet' id='font-awesome-css'  href='css/font-awesome.css' type='text/css' media='all' />
    <link rel='stylesheet' id='custom-css'  href='css/custom.css' type='text/css' media='all' />

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

    <!-- START SCROLL TO TOP BUTTON -->
    <?php include 'components/scroll-to-top.php'; ?>
    <!-- END SCROLL TO TOP BUTTON -->

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
                        
                        
                        <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin'): ?>
                            <li>
                                <a href="admin_contacts.php">Admin Messages</a>
                            </li>
                            <?php endif; ?>
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
                            <img src="images/ncip-logo.png" title="NCIP" alt="NCIP Header Logo" />
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
                        <div class="icontitle">
                            <img src="images/contact-form/contact_icon.png" alt="title"/>
                        </div>
                        <div class="title-with-icon">
                            <h1>Get in touch</h1>
                        </div>
                    </div>

                    <!-- BREDCRUMB -->
                    <div class="breadcrumbs">
                        <span class="before-text">You are here:</span>

                        <p id="yit-breadcrumb" itemprop="breadcrumb">
                            <a class="home" href="index.php">Home Page</a> |
                            <a class="no-link current" href="https://www.facebook.com/CebuPO">Get in touch</a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- END PAGE META -->

    <!-- START MAP -->
    <div id="map" style="padding-top: 10px;">
            <iframe
            frameborder="0"
            scrolling="no"
            marginheight="0"
            marginwidth="0"
            src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3925.5577323856687!2d123.89858500000001!3d10.2971739!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x33a9995875fc4d21%3A0xca989d306409bf82!2sNational%20Commission%20on%20Indigenous%20Peoples%20-%20Cebu%20Provincial%20Office!5e0!3m2!1sen!2sph!4v1743436547254!5m2!1sen!2sph">
        </iframe>
/
        <div class="map-overlay-top"></div>
        <div class="map-overlay-bottom"></div>

    </div>
    <!-- END MAP -->

    <!-- START SLOGAN -->
    <div class="slogan" style="margin-top: -20px;">
        <div class="container">
            <div class="header">
                <h1>NATIONAL COMMISSION ON INDIGENOUS PEOPLES</h1>
                <div class="central-office">
                    <p class="office-title">NCIP Central Office</p>
                
                    <div style="display: flex; align-items: center; margin-bottom: 5px;">
                        <i class="icon-map-marker" style="color:#979797; font-size: 20px; margin-right: 10px;"></i>
                        <span>6th and 7th Floors, Sunnymede IT Center, 1614 Quezon Avenue, South Triangle, Quezon City 1103, Philippines.</span>
                    </div>
                
                    <div style="display: flex; align-items: center; margin-bottom: 5px;">
                        <i class="icon-info-sign" style="color:#979797; font-size: 20px; margin-right: 10px;"></i>
                        <span>Tel Number: (02) 8575 – 1200</span>
                    </div>
                
                    <div style="display: flex; align-items: center;">
                        <i class="icon-envelope" style="color:#979797; font-size: 20px; margin-right: 10px;"></i>
                        <span>Email Address: chairperson@ncip.gov.ph</span>
                    </div>
                </div> 
            </div>
            <hr>
            <div class="regional-offices">
                <h1 style="padding-bottom: 10px;">REGIONAL OFFICES</h2>
                <div class="toggle">
                    <h4 class="tab-index tab-closed"><a href="#" title="Close" style="font-weight: bold; font-size: 1.1 rem; color: #676768;"><span class="icon-plus-sign"></span> NCIP Cordillera Administrative Region (CAR)</a></h4>
    
                    <div class="content-tab closed">
                        <p>
                            <i class="icon-map-marker" style="color:#979797; font-size:20px;"></i>&nbsp;&nbsp;
                            3rd Floor, Lyman Ogilby Centrum, 358 Magsaysay Ave. Baguio City<br>
                            <i class="icon-info-sign" style="color:#979797; font-size:20px;"></i>&nbsp;&nbsp;
                            Tel Number: (02) 875 1200 Local 1400<br>
                            <i class="icon-envelope" style="color:#979797; font-size:20px;"></i>&nbsp;&nbsp;
                            Email Address: car@ncip.gov.ph
                        </p>                              
                    </div>
                </div> 
                <div class="toggle">
                    <h4 class="tab-index tab-closed"><a href="#" title="Close" style="font-weight: bold; font-size: 1.1 rem; color: #676768;"><span class="icon-plus-sign"></span> NCIP Regional Office I</a></h4>
    
                    <div class="content-tab closed">
                        <p>
                            <i class="icon-map-marker" style="color:#979797; font-size:20px;"></i>&nbsp;&nbsp;
                            2nd & 3rd Floor, Braganza Building, Quezon Ave., Sevilla, San Fernando City<br>
                            <i class="icon-info-sign" style="color:#979797; font-size:20px;"></i>&nbsp;&nbsp;
                            Tel Number: (072) 609-09-94<br>
                            <i class="icon-envelope" style="color:#979797; font-size:20px;"></i>&nbsp;&nbsp;
                            Email Address: region1@ncip.gov.ph
                        </p>                              
                    </div>
                </div>
                <div class="toggle">
                    <h4 class="tab-index tab-closed"><a href="#" title="Close" style="font-weight: bold; font-size: 1.1 rem; color: #676768;"><span class="icon-plus-sign"></span> NCIP Regional Office II</a></h4>
    
                    <div class="content-tab closed">
                        <p>
                            <i class="icon-map-marker" style="color:#979797; font-size:20px;"></i>&nbsp;&nbsp;
                            Dalan na Paccorofun, Carig Sur, Tuguegarao City<br>
                            <i class="icon-info-sign" style="color:#979797; font-size:20px;"></i>&nbsp;&nbsp;
                            Tel Number: (02) 875 1200 Local 020<br>
                            <i class="icon-envelope" style="color:#979797; font-size:20px;"></i>&nbsp;&nbsp;
                            Email Address: region2@ncip.gov.ph
                        </p>                              
                    </div>
                </div>
                <div class="toggle">
                    <h4 class="tab-index tab-closed"><a href="#" title="Close" style="font-weight: bold; font-size: 1.1 rem; color: #676768;"><span class="icon-plus-sign"></span> NCIP Regional Office III</a></h4>
    
                    <div class="content-tab closed">
                        <p>
                            <i class="icon-map-marker" style="color:#979797; font-size:20px;"></i>&nbsp;&nbsp;
                            3rd Flr. KL Building, Consunji St., Sto. Rosario, City of San Fernando, Pampanga<br>
                            <i class="icon-info-sign" style="color:#979797; font-size:20px;"></i>&nbsp;&nbsp;
                            Tel Number: (045) 961- 59- 56<br>
                            <i class="icon-envelope" style="color:#979797; font-size:20px;"></i>&nbsp;&nbsp;
                            Email Address: <a href="mailto:region3@ncip.gov.ph" style="color:#979797; text-decoration:none;">region3@ncip.gov.ph</a><br>
                            <i class="icon-globe" style="color:#979797; font-size:20px;"></i>&nbsp;&nbsp;
                            Website: <a href="https://www.ncipr3.com" target="_blank">www.ncipr3.com</a>
                        </p>                                                           
                    </div>
                </div>
                <div class="toggle">
                    <h4 class="tab-index tab-closed"><a href="#" title="Close" style="font-weight: bold; font-size: 1.1 rem; color: #676768;"><span class="icon-plus-sign"></span> NCIP Regional Office IV-A</a></h4>
    
                    <div class="content-tab closed">
                        <p>
                            <i class="icon-map-marker" style="color:#979797; font-size:20px;"></i>&nbsp;&nbsp;
                            3rd Floor 574 Argo Bldg. EDSA cor. P. Tuazon Ave., Cubao, Quezon City<br>
                            <i class="icon-info-sign" style="color:#979797; font-size:20px;"></i>&nbsp;&nbsp;
                            Tel Number: (045) 961- 59- 56<br>
                            <i class="icon-envelope" style="color:#979797; font-size:20px;"></i>&nbsp;&nbsp;
                            Email Address: region4a@ncip.gov.ph
                        </p>                              
                    </div>
                </div>
                <div class="toggle">
                    <h4 class="tab-index tab-closed"><a href="#" title="Close" style="font-weight: bold; font-size: 1.1 rem; color: #676768;"><span class="icon-plus-sign"></span> NCIP Regional Office IV-B</a></h4>
    
                    <div class="content-tab closed">
                        <p>
                            <i class="icon-map-marker" style="color:#979797; font-size:20px;"></i>&nbsp;&nbsp;
                            3rd Floor 574 Argo Bldg. EDSA cor. P. Tuazon Ave., Cubao, Quezon City<br>
                            <i class="icon-info-sign" style="color:#979797; font-size:20px;"></i>&nbsp;&nbsp;
                            Tel Number: (02) 8575 1200 Local 0450 | 84039198<br>
                            <i class="icon-envelope" style="color:#979797; font-size:20px;"></i>&nbsp;&nbsp;
                            Email Address: region4b@ncip.gov.ph
                        </p>                              
                    </div>
                </div>
                <div class="toggle">
                    <h4 class="tab-index tab-closed"><a href="#" title="Close" style="font-weight: bold; font-size: 1.1 rem; color: #676768;"><span class="icon-plus-sign"></span> NCIP Regional Office V</a></h4>
    
                    <div class="content-tab closed">
                        <p>
                            <i class="icon-map-marker" style="color:#979797; font-size:20px;"></i>&nbsp;&nbsp;
                            Highway 1 San Nicolas, Iriga City<br>
                            <i class="icon-info-sign" style="color:#979797; font-size:20px;"></i>&nbsp;&nbsp;
                            Tel Number: (054) 299-2752 / (054) 299-6167<br>
                            <i class="icon-envelope" style="color:#979797; font-size:20px;"></i>&nbsp;&nbsp;
                            Email Address: region5@ncip.gov.ph
                        </p>                              
                    </div>
                </div>
                <div class="toggle">
                    <h4 class="tab-index tab-closed"><a href="#" title="Close" style="font-weight: bold; font-size: 1.1 rem; color: #676768;"><span class="icon-plus-sign"></span> NCIP Regional Office VI & VII</a></h4>
    
                    <div class="content-tab closed">
                        <p>
                            <i class="icon-map-marker" style="color:#979797; font-size:20px;"></i>&nbsp;&nbsp;
                            3rd Floor, CPBC Centennial Bldg., Fajardo St., Jaro, Ilo-ilo City<br>
                            <i class="icon-info-sign" style="color:#979797; font-size:20px;"></i>&nbsp;&nbsp;
                            Tel Number: (033) 321-01-47<br>
                            <i class="icon-envelope" style="color:#979797; font-size:20px;"></i>&nbsp;&nbsp;
                            Email Address: region67@ncip.gov.ph
                        </p>                              
                    </div>
                </div>
                <div class="toggle">
                    <h4 class="tab-index tab-closed"><a href="#" title="Close" style="font-weight: bold; font-size: 1.1 rem; color: #676768;"><span class="icon-plus-sign"></span> NCIP Regional Office IX</a></h4>
    
                    <div class="content-tab closed">
                        <p>
                            <i class="icon-map-marker" style="color:#979797; font-size:20px;"></i>&nbsp;&nbsp;
                            Raiza Bldg., Rizal Avenue, Lumbia District, Pagadian City, Zamboanga del Sur<br>
                            <i class="icon-info-sign" style="color:#979797; font-size:20px;"></i>&nbsp;&nbsp;
                            Tel Number: (062) 353- 10- 72<br>
                            <i class="icon-envelope" style="color:#979797; font-size:20px;"></i>&nbsp;&nbsp;
                            Email Address: region9@ncip.gov.ph
                        </p>                              
                    </div>
                </div>
                <div class="toggle">
                    <h4 class="tab-index tab-closed"><a href="#" title="Close" style="font-weight: bold; font-size: 1.1 rem; color: #676768;"><span class="icon-plus-sign"></span> NCIP Regional Office X</a></h4>
    
                    <div class="content-tab closed">
                        <p>
                            <i class="icon-map-marker" style="color:#979797; font-size:20px;"></i>&nbsp;&nbsp;
                            3rd Floor Halasan Bldg. cor. Tiano Del Pilar St., Cagayan de Oro City<br>
                            <i class="icon-info-sign" style="color:#979797; font-size:20px;"></i>&nbsp;&nbsp;
                            Tel Number: (08822) 71-15-00<br>
                            <i class="icon-envelope" style="color:#979797; font-size:20px;"></i>&nbsp;&nbsp;
                            Email Address: region10@ncip.gov.ph
                        </p>                              
                    </div>
                </div>
                <div class="toggle">
                    <h4 class="tab-index tab-closed"><a href="#" title="Close" style="font-weight: bold; font-size: 1.1 rem; color: #676768;"><span class="icon-plus-sign"></span> NCIP Regional Office XI</a></h4>
    
                    <div class="content-tab closed">
                        <p>
                            <i class="icon-map-marker" style="color:#979797; font-size:20px;"></i>&nbsp;&nbsp;
                            E. Valeroso Bldg., Sandawa Plaza, New Matina, Davao City<br>
                            <i class="icon-info-sign" style="color:#979797; font-size:20px;"></i>&nbsp;&nbsp;
                            Tel Number: (02) 8575 1200 Local 1100<br>
                            <i class="icon-envelope" style="color:#979797; font-size:20px;"></i>&nbsp;&nbsp;
                            Email Address: region11@ncip.gov.ph
                        </p>                              
                    </div>
                </div>
                <div class="toggle">
                    <h4 class="tab-index tab-closed"><a href="#" title="Close" style="font-weight: bold; font-size: 1.1 rem; color: #676768;"><span class="icon-plus-sign"></span> NCIP Regional Office XII</a></h4>
    
                    <div class="content-tab closed">
                        <p>
                            <i class="icon-map-marker" style="color:#979797; font-size:20px;"></i>&nbsp;&nbsp;
                            2nd Floor, Jayven Bldg., General Santos Drive, cor. Posadas St., Koronadal City, South Cotabato<br>
                            <i class="icon-info-sign" style="color:#979797; font-size:20px;"></i>&nbsp;&nbsp;
                            Tel Number: (02) 8575 1200 Local 1200<br>
                            <i class="icon-envelope" style="color:#979797; font-size:20px;"></i>&nbsp;&nbsp;
                            Email Address: region12@ncip.gov.ph
                        </p>                              
                    </div>
                </div>
                <div class="toggle">
                    <h4 class="tab-index tab-closed"><a href="#" title="Close" style="font-weight: bold; font-size: 1.1 rem; color: #676768;"><span class="icon-plus-sign"></span> NCIP Regional Office XIII</a></h4>
    
                    <div class="content-tab closed">
                        <p>
                            <i class="icon-map-marker" style="color:#979797; font-size:20px;"></i>&nbsp;&nbsp;
                            1st Floor Samping Building., J.C. Aquino, Butuan City<br>
                            <i class="icon-info-sign" style="color:#979797; font-size:20px;"></i>&nbsp;&nbsp;
                            Tel Number: (085) 226 5116<br>
                            <i class="icon-envelope" style="color:#979797; font-size:20px;"></i>&nbsp;&nbsp;
                            Email Address: region13@ncip.gov.ph
                        </p>                              
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- END SLOGAN -->

    <!-- START PRIMARY -->
    <div id="primary" class="sidebar-right">
        <div class="container group">
            <div class="row">
                <!-- START CONTENT -->
                <div id="content-page" class="span9 content group">
                        <div class="page type-page status-publish hentry group">
                <form class="contact-form row" method="post" enctype="multipart/form-data" action="process_contact.php" autocomplete="off">
    
                    <div class="usermessagea"></div>
                    <fieldset>
                
                        <ul>
                
                            <li class="text-field with-icon span3">

                                <label for="name-contact-form">
                                    <span class="mainlabel">Name</span>
                                </label>

                                <div class="input-prepend"><span class="add-on">
                                    <img src="images/contact-form/user.png" alt="" title="" /></span><input type="text" name="yit_contact[name]" id="name-contact-form" class="with-icon required" value="" />
                                </div>

                                <div class="msg-error"></div>

                                <div class="clear"></div>
                            </li>

                            <li class="text-field with-icon span3">

                                <label for="email-contact-form">
                                    <span class="mainlabel">Email</span>
                                </label>

                                <div class="input-prepend">
                                    <span class="add-on">
                                        <img src="images/contact-form/envelope.png" alt="" title="" />
                                    </span>
                                    <input type="text" name="yit_contact[email]" id="email-contact-form" class="with-icon required email-validate" value="" />
                                </div>

                                <div class="msg-error"></div>

                                <div class="clear"></div>
                            </li>

                            <li class="text-field with-icon span3">

                                <label for="phone-contact-form">
                                    <span class="mainlabel">Phone</span>
                                </label>

                                <div class="input-prepend">
                                    <span class="add-on">
                                        <img src="images/contact-form/phone.png" alt="" title="" />
                                    </span>
                                    <input type="text" name="yit_contact[phone]" id="phone-contact-form" class="with-icon" value="" />
                                </div>

                                <div class="msg-error"></div>

                                <div class="clear"></div>
                            </li>

                            <li class="textarea-field with-icon span9">

                                <label for="message-contact-form">
                                    <span class="mainlabel">Message</span>
                                </label>

                                <div class="input-prepend">
                                    <span class="add-on">
                                        <img src="images/contact-form/pencil.png" alt="" title="" />
                                    </span>
                                    <textarea name="yit_contact[message]" id="message-contact-form" rows="8" cols="30" class="with-icon required"></textarea>
                                </div>

                                <div class="msg-error"></div>

                                <div class="clear"></div>
                            </li>

                            <li class="submit-button span9">
                                <input type="text" name="yit_bot" id="yit_bot" />
                                <input type="hidden" name="yit_action" value="sendemail"/>
                                <input type="hidden" name="yit_referer" value="get-in-touch.html" />
                                <input type="hidden" name="id_form" value="4" />
                                <input type="submit" name="yit_sendemail" value="Send Message" class="sendmail alignright" />
                                <div class="clear"></div>
                            </li>
                        </ul>
                    </fieldset>
                </form>
    
                <script type="text/javascript">
                    var messages_form_4 = {
                        name: "Insert the name",
                        email: "Insert a valid email",
                        phone: "",
                        message: "Insert a message"
                    };
                </script>
            </div>
        </div>
        <!-- END CONTENT -->
                            
        <!-- START SIDEBAR -->
                <div id="sidebar-contact" class="span3 sidebar group">
                    <div class="widget-first widget contact-info"><h3>Contacts</h3>

                        <div class="sidebar-nav">
                            <ul>

                                <li>
                                    <i class="icon-map-marker" style="color:#000;font-size:12px;width:12px;height:12px"></i>
                                    <span>Address:</span> 2nd Floor Room 17 Osmeña Bldg. corner Colon and Pelaez sts., Cebu City, Philippines
                                </li>

                                <li>
                                    <i class="icon-phone" style="color:#000;font-size:12px;width:12px;height:12px"></i>
                                    <span>Telephone:</span> (032) 254 3630
                                </li>

                                <li>
                                    <i class="icon-print" style="color:#000;font-size:12px;width:12px;height:12px"></i>
                                    <span>Fax:</span> +39 0035 356 765
                                </li>

                                <li>
                                    <i class="icon-envelope" style="color:#000;font-size:12px;width:12px;height:12px"></i>
                                    <span>Email:</span> <a href="mailto:region67.cebupo@ncip.gov.ph">region67.cebupo@ncip.gov.ph</a>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <div class="widget-last widget last-tweets">

                        <div class="list-tweets-2"></div>
                        <p id="follow-facebook">
                            <a href="https://www.facebook.com/CebuPO" target="_blank">
                                Follow us on Facebook &rarr;
                            </a>
                        </p>
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

<script type='text/javascript' src='js/comment-reply.min.js'></script>
<script type='text/javascript' src='js/underscore.min.js'></script>
<script type='text/javascript' src='js/jquery/jquery.masonry.min.js'></script>
<script type='text/javascript' src='js/jquery.colorbox-min.js'></script>
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
<script type='text/javascript' src='js/shortcodes.js'></script>
<script type='text/javascript' src='js/jquery.custom.js'></script>

</body>
<!-- END BODY -->
</html>