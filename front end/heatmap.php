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
    <link rel='stylesheet' id='colorbox-css'  href='css/colorbox.css' type='text/css' media='all' />
    <link rel='stylesheet' id='elastislide-css'  href='css/elastislide.css' type='text/css' media='all' />
    <link rel='stylesheet' id='polaroid-slider-css'  href='portfolios/columns/css/style.css' type='text/css' media='all' />
    <link rel='stylesheet' id='ahortcodes-css'  href='css/shortcodes.css' type='text/css' media='all' />
    <link rel='stylesheet' id='contact-form-css'  href='css/contact_form.css' type='text/css' media='all' />
    <link rel='stylesheet' id='custom-css'  href='css/custom.css' type='text/css' media='all' />
    <link rel='stylesheet' id='usquare-slider-css'  href='sliders/usquare/css/frontend/usquare_style.css' type='text/css' media='all' />
    <link rel='stylesheet' id='flexslider-elegant-css'  href='sliders/flexslider-elegant/css/flexslider.css' type='text/css' media='all' />
    <link rel='stylesheet' id='flexslider-css'  href='sliders/flexslider/css/slider.css' type='text/css' media='all' />       
    <!-- Add Bootstrap Icons for admin buttons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">

    <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
    <style type="text/css">
        body { background-color: #ffffff; background-image: url('images/bg-pattern.png'); background-repeat: repeat; background-position: top left; background-attachment: scroll; }
    
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

        #map {
            height: 100vh;
            width: 100%;
            position: relative;
        }
        
        #data-display-section {
            margin-top: 20px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        
        #selected-data-table {
            width: 60%;
            max-width: 900px;
            min-width: 700px;
            border-collapse: collapse;
            margin-top: 15px;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        #selected-data-table th,
        #selected-data-table td {
            padding: 12px 20px;
            text-align: left;
            border-bottom: 2px solid #e9ecef;
            width: 50%;
        }
        
        #selected-data-table th {
            background-color: #476a90;
            color: white;
            font-weight: 600;
        }
        
        #selected-data-table tr:hover {
            background-color: #f1f3f4;
        }

        #selected-data-table tbody tr:not(:last-child) td {
            border-bottom: 1px solid #dee2e6;

        }

        #selected-data-table th:first-child,
        #selected-data-table td:first-child {
            border-right: 2px solid #e9ecef;
            text-align: center;
        }
        
        #selected-data-table tbody tr:last-child td {
            border-bottom: none;
        }

        #close-table-btn {
            background-color: #476a90 !important;
            color: white;
            border: 1px solid #3d5a7a;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s ease;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            display: none;
        }

        #close-table-btn:hover {
            background-color: #3d5a7a !important;
            border-color: #334e6b;
            transform: translateY(-1px);
            box-shadow: 0 2px 6px rgba(0,0,0,0.15);
            color: white;
        }

        #close-table-btn:active {
            transform: translateY(0);
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        #close-table-btn i {
            margin-right: 5px;
        }

        .table-header-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .table-header-container h3 {
            margin: 0;
            color: #333;
        }
        
        .no-data-message {
            text-align: center;
            padding: 30px;
            color: #6c757d;
            font-style: italic;
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
            #data-display-section h3 {
                font-size: 18px;
                padding-right: 80px; 
            }
            
            #close-table-btn {
                padding: 6px 10px !important;
                font-size: 12px !important;
            }
        }
        @media (max-width: 480px) {
            #data-display-section h3 {
                font-size: 16px;
            }
            
            #close-table-btn {
                padding: 5px 8px !important;
                font-size: 11px !important;
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

    <!-- SLOGAN -->
    <div class="slogan">
        <h2>IP Location Tracker</h2>
        <h3>discover about their locations and populations.</h3>
    </div>

    <!-- START PRIMARY -->
    <div id="map"></div>

    <!-- Data Display Section -->
    <div id="data-display-section">
        <div style="display: flex; justify-content: center; align-items: center; margin-bottom: 15px; position: relative; width: 100%; min-height: 40px;">
            <h3 style="margin: 0; color: #333; text-align: center; flex-grow: 1;">Selected Location Details</h3>
            <button id="close-table-btn" style="display: none; position: absolute; right: 0; background-color: #476a90; color: white; border: 1px solid #3d5a7a; padding: 8px 15px; border-radius: 4px; cursor: pointer; font-size: 14px; font-weight: 500; transition: all 0.2s ease; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                <i class="bi bi-x-circle"></i> Close
            </button>
        </div>
        <div id="table-container">
            <div class="no-data-message">
                Click on any location marker on the map to view detailed information here.
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

<!-- Leaflet JS -->
<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
<!-- Your custom JS -->
<script src="js/heatmap.js?v=<?php echo time(); ?>"></script>

<script type='text/javascript' src='js/comment-reply.min.js'></script>
<script type='text/javascript' src='js/underscore.min.js'></script>
<script type='text/javascript' src='js/jquery/jquery.masonry.min.js'></script>
<script type='text/javascript' src='js/jquery.easing.js'></script>
<script type='text/javascript' src='js/jquery.colorbox-min.js'></script>
<script type='text/javascript' src='js/jquery.elastislide.js'></script>
<script type='text/javascript' src='js/hoverIntent.min.js'></script>
<script type='text/javascript' src='js/media-upload.min.js'></script>
<script type='text/javascript' src='js/jquery.clickout.min.js'></script>
<script type='text/javascript' src='js/jquery.flexslider-min.js'></script>
<script type='text/javascript' src='sliders/usquare/js/frontend/jquery.mCustomScrollbar.min.js'></script>
<script type='text/javascript' src='sliders/usquare/js/frontend/jquery.usquare.js'></script>
<script type='text/javascript' src='js/responsive.js'></script>
<script type='text/javascript' src='js/mobilemenu.js'></script>
<script type='text/javascript' src='js/jquery.superfish.js'></script>
<script type='text/javascript' src='js/jquery.placeholder.js'></script>
<script type='text/javascript' src='js/contact.js'></script>
<script type='text/javascript' src='js/jquery.tipsy.js'></script>
<script type='text/javascript' src='js/jquery.cycle.min.js'></script>
<script type='text/javascript' src='js/shortcodes.js'></script>
<script type='text/javascript' src='js/jquery.custom.js'></script>
<script>
// Add functionality for the close table button
document.addEventListener('DOMContentLoaded', function() {
    const closeTableBtn = document.getElementById('close-table-btn');
    const tableContainer = document.getElementById('table-container');
    
    // Ensure button is hidden initially
    closeTableBtn.style.display = 'none';
    
    // Close table button functionality
    closeTableBtn.addEventListener('click', function() {
        // Hide the close button
        closeTableBtn.style.display = 'none';
        
        // Reset table container to show default message
        tableContainer.innerHTML = `
            <div class="no-data-message">
                Click on any location marker on the map to view detailed information here.
            </div>
        `;
        
        // Smooth scroll back to top of the data section
        document.getElementById('data-display-section').scrollIntoView({ 
            behavior: 'smooth',
            block: 'start'
        });
    });
});
</script>
</body>
<!-- END BODY -->
</html>