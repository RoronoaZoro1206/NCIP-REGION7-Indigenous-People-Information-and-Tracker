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

// Include database connection for displaying contact statistics or recent messages
require_once 'db.php';

// Get contact message statistics (excluding soft-deleted messages)
try {
    $stats_query = "SELECT 
                        COUNT(*) as total_messages,
                        COUNT(CASE WHEN CONTM_STATUS = 'Unread' THEN 1 END) as unread_messages,
                        COUNT(CASE WHEN CONTM_STATUS = 'Read' THEN 1 END) as read_messages,
                        COUNT(CASE WHEN CONTM_STATUS = 'Replied' THEN 1 END) as replied_messages
                    FROM CONTACT_MESSAGE 
                    WHERE CONTM_DELETED_AT IS NULL";
    
    $stats_stmt = $pdo->prepare($stats_query);
    $stats_stmt->execute();
    $contact_stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Contact stats error: " . $e->getMessage());
    $contact_stats = [
        'total_messages' => 0,
        'unread_messages' => 0,
        'read_messages' => 0,
        'replied_messages' => 0
    ];
}

// Check for success/error messages from form submission
$success_message = $_SESSION['contact_success'] ?? null;
$error_message = $_SESSION['contact_error'] ?? null;

// Clear session messages after displaying
unset($_SESSION['contact_success']);
unset($_SESSION['contact_error']);
?>

<!DOCTYPE html>
<html lang="en-US">
<!-- START HEAD -->
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.2, user-scalable=yes" />

    <title>Contact Us - NCIP Cebu Connect</title>
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
    <!-- Add Bootstrap Icons for admin buttons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">

    <style type="text/css">
        body { 
            background-color: #ffffff; 
            background-image: url('images/bg-pattern.png'); 
            background-repeat: repeat; 
            background-position: top left; 
            background-attachment: scroll; 
        }

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
        
        .contact-success {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
            padding: 12px 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            font-weight: 500;
        }
        
        .contact-error {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            padding: 12px 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            font-weight: 500;
        }
        
        .contact-stats {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #007bff;
        }
        
        .contact-stats h4 {
            margin: 0 0 10px 0;
            color: #333;
            font-size: 16px;
        }
        
        .contact-stats p {
            margin: 5px 0;
            color: #666;
            font-size: 14px;
        }
        
        .form-messages {
            margin-bottom: 20px;
        }
        
        .response-time-notice {
            background: #e7f3ff;
            border: 1px solid #b3d9ff;
            color: #0056b3;
            padding: 10px 15px;
            border-radius: 4px;
            margin-top: 15px;
            font-size: 14px;
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

                    <!-- BREADCRUMB -->
                    <div class="breadcrumbs">
                        <span class="before-text">You are here:</span>

                        <p id="yit-breadcrumb" itemprop="breadcrumb">
                            <a class="home" href="index.php">Home Page</a> |
                            <a class="no-link current" href="contact.php">Get in touch</a>
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
            src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3925.5577323856687!2d123.89858500000001!3d10.2971739!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x33a9995875fc4d21%3A0xca989d306409bf82!2sNational%20Commission%20on%20Indigenous%20Peoples%20-%20Cebu%20Provincial%20Office!5e0!3m2!1sen!2sph!4v1743436547254!5m2!1sen!2sph"
            width="100%" 
            height="350">
        </iframe>
        
        <div class="map-overlay-top"></div>
        <div class="map-overlay-bottom"></div>
    </div>
    <!-- END MAP -->

    <!-- START SLOGAN -->
    <div class="slogan" style="margin-top: -20px;">
        <div class="container">
            <div class="header">
                <h1>NATIONAL COMMISSION ON INDIGENOUS PEOPLES REGION 6/7</h1>
                <div class="central-office">
                    <p class="office-title">Cebu Provincial Office</p>
                
                    <div style="display: flex; align-items: center; margin-bottom: 5px;">
                        <i class="icon-map-marker" style="color:#979797; font-size: 20px; margin-right: 10px;"></i>
                        <span>Room 17 2/F Osmeña Building Pelaez Street, Cebu City 6000 Philippines</span>
                    </div>
                
                    <div style="display: flex; align-items: center; margin-bottom: 5px;">
                        <i class="icon-info-sign" style="color:#979797; font-size: 20px; margin-right: 10px;"></i>
                        <span>Contact Number: (032) 254 3630 / (+63) 906 588 1698</span>
                    </div>
                
                    <div style="display: flex; align-items: center;">
                        <i class="icon-envelope" style="color:#979797; font-size: 20px; margin-right: 10px;"></i>
                        <span>Email Address: region67.cebupo@ncip.gov.ph</span>
                    </div>
                </div> 
            </div>
            <hr>
            <div class="regional-offices">
                <h1 style="padding-bottom: 10px;">OTHER REGIONAL OFFICES</h1>
                <div class="toggle">
                    <h4 class="tab-index tab-closed"><a href="#" title="Close" style="font-weight: bold; font-size: 1.1rem; color: #676768;"><span class="icon-plus-sign"></span> Click here to view other regional offices</a></h4>
    
                    <div class="content-tab closed" style="padding-left: 20px;">
                        <div class="toggle">
                            <h4 class="tab-index tab-closed"><a href="#" title="Close" style="font-weight: bold; font-size: 1.1rem; color: #676768;"><span class="icon-plus-sign"></span> NCIP Central Office</a></h4>
        
                            <div class="content-tab closed">
                                <p>
                                    <i class="icon-map-marker" style="color:#979797; font-size:20px;"></i>&nbsp;&nbsp;
                                    6th and 7th Floors, Sunnymede IT Center, 1614 Quezon Avenue, South Triangle, Quezon City 1103, Philippines<br>
                                    <i class="icon-info-sign" style="color:#979797; font-size:20px;"></i>&nbsp;&nbsp;
                                    Tel Number: (02) 8575 – 1200<br>
                                    <i class="icon-envelope" style="color:#979797; font-size:20px;"></i>&nbsp;&nbsp;
                                    Email Address: chairperson@ncip.gov.ph
                                </p>                              
                            </div>
                        </div> 
                        <div class="toggle">
                            <h4 class="tab-index tab-closed"><a href="#" title="Close" style="font-weight: bold; font-size: 1.1rem; color: #676768;"><span class="icon-plus-sign"></span> NCIP Cordillera Administrative Region (CAR)</a></h4>
        
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
                            <h4 class="tab-index tab-closed"><a href="#" title="Close" style="font-weight: bold; font-size: 1.1rem; color: #676768;"><span class="icon-plus-sign"></span> NCIP Regional Office I</a></h4>
        
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
                            <h4 class="tab-index tab-closed"><a href="#" title="Close" style="font-weight: bold; font-size: 1.1rem; color: #676768;"><span class="icon-plus-sign"></span> NCIP Regional Office II</a></h4>
        
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
                            <h4 class="tab-index tab-closed"><a href="#" title="Close" style="font-weight: bold; font-size: 1.1rem; color: #676768;"><span class="icon-plus-sign"></span> NCIP Regional Office III</a></h4>
        
                            <div class="content-tab closed">
                                <p>
                                    <i class="icon-map-marker" style="color:#979797; font-size:20px;"></i>&nbsp;&nbsp;
                                    3rd Flr. KL Building, Consunji St., Sto. Rosario, City of San Fernando, Pampanga<br>
                                    <i class="icon-info-sign" style="color:#979797; font-size:20px;"></i>&nbsp;&nbsp;
                                    Tel Number: (045) 961- 59- 56<br>
                                    <i class="icon-envelope" style="color:#979797; font-size:20px;"></i>&nbsp;&nbsp;
                                    Email Address: <a href="mailto:region3@ncip.gov.ph" style="color:#979797; text-decoration:none;">region3@ncip.gov.ph</a><br>
                                    <i class="icon-globe" style="color:#979797; font-size:20px;"></i>&nbsp;&nbsp;
                                    Website: <a href="https://www.ncipr3.com" target="_blank" style="color:#979797;">www.ncipr3.com</a>
                                </p>                                                           
                            </div>
                        </div>
                        <div class="toggle">
                            <h4 class="tab-index tab-closed"><a href="#" title="Close" style="font-weight: bold; font-size: 1.1rem; color: #676768;"><span class="icon-plus-sign"></span> NCIP Regional Office IV-A</a></h4>
        
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
                            <h4 class="tab-index tab-closed"><a href="#" title="Close" style="font-weight: bold; font-size: 1.1rem; color: #676768;"><span class="icon-plus-sign"></span> NCIP Regional Office IV-B</a></h4>
        
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
                            <h4 class="tab-index tab-closed"><a href="#" title="Close" style="font-weight: bold; font-size: 1.1rem; color: #676768;"><span class="icon-plus-sign"></span> NCIP Regional Office V</a></h4>
        
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
                            <h4 class="tab-index tab-closed"><a href="#" title="Close" style="font-weight: bold; font-size: 1.1rem; color: #676768;"><span class="icon-plus-sign"></span> NCIP Regional Office VI & VII</a></h4>
        
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
                            <h4 class="tab-index tab-closed"><a href="#" title="Close" style="font-weight: bold; font-size: 1.1rem; color: #676768;"><span class="icon-plus-sign"></span> NCIP Regional Office IX</a></h4>
        
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
                            <h4 class="tab-index tab-closed"><a href="#" title="Close" style="font-weight: bold; font-size: 1.1rem; color: #676768;"><span class="icon-plus-sign"></span> NCIP Regional Office X</a></h4>
        
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
                            <h4 class="tab-index tab-closed"><a href="#" title="Close" style="font-weight: bold; font-size: 1.1rem; color: #676768;"><span class="icon-plus-sign"></span> NCIP Regional Office XI</a></h4>
        
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
                            <h4 class="tab-index tab-closed"><a href="#" title="Close" style="font-weight: bold; font-size: 1.1rem; color: #676768;"><span class="icon-plus-sign"></span> NCIP Regional Office XII</a></h4>
        
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
                            <h4 class="tab-index tab-closed"><a href="#" title="Close" style="font-weight: bold; font-size: 1.1rem; color: #676768;"><span class="icon-plus-sign"></span> NCIP Regional Office XIII</a></h4>
        
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
                        
                        <!-- Display Success/Error Messages -->
                        <div class="form-messages">
                            <?php if ($success_message): ?>
                                <div class="contact-success">
                                    <i class="icon-ok-sign"></i> <?php echo htmlspecialchars($success_message); ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($error_message): ?>
                                <div class="contact-error">
                                    <i class="icon-exclamation-sign"></i> <?php echo htmlspecialchars($error_message); ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Contact Form -->
                        <form class="contact-form row" method="post" enctype="multipart/form-data" action="process_contact.php" autocomplete="off">
            
                            <div class="usermessagea"></div>
                            <fieldset>
                        
                                <ul>
                                    <li class="text-field with-icon span3">
                                        <label for="name-contact-form">
                                            <span class="mainlabel">Name <span style="color: red;">*</span></span>
                                        </label>

                                        <div class="input-prepend">
                                            <span class="add-on">
                                                <img src="images/contact-form/user.png" alt="" title="" />
                                            </span>
                                            <input type="text" name="yit_contact[name]" id="name-contact-form" 
                                                   class="with-icon required" value="" maxlength="100"  />
                                        </div>

                                        <div class="msg-error"></div>
                                        <div class="clear"></div>
                                    </li>

                                    <li class="text-field with-icon span3">
                                        <label for="email-contact-form">
                                            <span class="mainlabel">Email <span style="color: red;">*</span></span>
                                        </label>

                                        <div class="input-prepend">
                                            <span class="add-on">
                                                <img src="images/contact-form/envelope.png" alt="" title="" />
                                            </span>
                                            <input type="email" name="yit_contact[email]" id="email-contact-form" 
                                                   class="with-icon required email-validate" value="" maxlength="100"/>
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
                                            <input type="tel" name="yit_contact[phone]" id="phone-contact-form" 
                                                   class="with-icon" value="" maxlength="20"/>
                                        </div>

                                        <div class="msg-error"></div>
                                        <div class="clear"></div>
                                    </li>

                                    <li class="textarea-field with-icon span9">
                                        <label for="message-contact-form">
                                            <span class="mainlabel">Message <span style="color: red;">*</span></span>
                                        </label>

                                        <div class="input-prepend">
                                            <span class="add-on">
                                                <img src="images/contact-form/pencil.png" alt="" title="" />
                                            </span>
                                            <textarea name="yit_contact[message]" id="message-contact-form" 
                                                      rows="8" cols="30" class="with-icon required"></textarea>
                                        </div>

                                        <div class="msg-error"></div>
                                        <div class="clear"></div>
                                    </li>

                                    <li class="submit-button span9">
                                        <!-- Honeypot field for spam protection -->
                                        <input type="text" name="yit_bot" id="yit_bot" style="display: none;" />
                                        <input type="hidden" name="yit_action" value="sendemail"/>
                                        <input type="hidden" name="yit_referer" value="contact.php" />
                                        <input type="hidden" name="id_form" value="4" />
                                        <input type="submit" name="yit_sendemail" value="Send Message" class="sendmail alignright" />
                                        <div class="clear"></div>
                                    </li>
                                </ul>
                            </fieldset>
                        </form>

                        <script type="text/javascript">
                            var messages_form_4 = {
                                name: "Please enter your name",
                                email: "Please enter a valid email address", 
                                phone: "",
                                message: "Please enter your message"
                            };
                        </script>
                    </div>
                </div>
                <!-- END CONTENT -->
                                
                <!-- START SIDEBAR -->
                <div id="sidebar-contact" class="span3 sidebar group">
                    
                    <!-- Contact Information Widget -->
                    <div class="widget-first widget contact-info">
                        <h3>Contact Information</h3>

                        <div class="sidebar-nav">
                            <ul>
                                <li>
                                    <i class="icon-map-marker" style="color:#000;font-size:12px;width:12px;height:12px"></i>
                                    <span>Address:</span> Room 17 2/F Osmeña Building, Pelaez Street, Cebu City 6000 Philippines
                                </li>

                                <li>
                                    <i class="icon-phone" style="color:#000;font-size:12px;width:12px;height:12px"></i>
                                    <span>Telephone:</span> (032) 254 3630
                                </li>

                                <li>
                                    <i class="icon-phone" style="color:#000;font-size:12px;width:12px;height:12px"></i>
                                    <span>Mobile:</span> (+63) 906 588 1698
                                </li>

                                <li>
                                    <i class="icon-envelope" style="color:#000;font-size:12px;width:12px;height:12px"></i>
                                    <span>Email:</span> <a href="mailto:region67.cebupo@ncip.gov.ph">region67.cebupo@ncip.gov.ph</a>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <!-- Office Hours Widget -->
                    <div class="widget office-hours">
                        <h3>Office Hours</h3>
                        <div class="sidebar-nav">
                            <ul>
                                <li>
                                    <i class="icon-time" style="color:#000;font-size:12px;width:12px;height:12px"></i>
                                    <span>Monday - Friday:</span> 8:00 AM - 5:00 PM
                                </li>
                                <li>
                                    <i class="icon-time" style="color:#000;font-size:12px;width:12px;height:12px"></i>
                                    <span>Saturday - Sunday:</span> Closed
                                </li>
                                <li style="margin-top: 10px; font-size: 12px; color: #666;">
                                    <strong>Note:</strong> We observe Philippine holidays
                                </li>
                            </ul>
                        </div>
                    </div>

                    <!-- Contact Statistics Widget (Optional - shows activity) -->
                    <?php if ($contact_stats['total_messages'] > 0): ?>
                    <div class="widget contact-stats">
                        <h4><i class="icon-bar-chart"></i> Contact Activity</h4>
                        <p><strong>Total Messages:</strong> <?php echo number_format($contact_stats['total_messages']); ?></p>
                        <p><strong>Recent Activity:</strong> Active community engagement</p>
                        <p style="font-size: 12px; color: #666; margin-top: 10px;">
                            We appreciate all the messages from our community and strive to respond promptly.
                        </p>
                    </div>
                    <?php endif; ?>
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
<script type="text/javascript">
// Add this before the closing </body> tag
jQuery(document).ready(function($) {
    // Handle label visibility for input fields
    $('.contact-form input, .contact-form textarea').on('input focus blur', function() {
        var $this = $(this);
        var $li = $this.closest('li');
        
        if ($this.val().length > 0) {
            $li.addClass('has-value');
        } else {
            $li.removeClass('has-value');
        }
    });
    
    // Check initial state on page load
    $('.contact-form input, .contact-form textarea').each(function() {
        var $this = $(this);
        var $li = $this.closest('li');
        
        if ($this.val().length > 0) {
            $li.addClass('has-value');
        }
    });
});
</script>

</body>
<!-- END BODY -->
</html>