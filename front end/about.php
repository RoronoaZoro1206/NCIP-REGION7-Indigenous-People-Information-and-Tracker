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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel='stylesheet' id='responsive-css'  href='css/responsive.css' type='text/css' media='all' />
    <link rel='stylesheet' id='colorbox-css'  href='css/colorbox.css' type='text/css' media='all' />
    <link rel='stylesheet' id='flex-slider-css'  href='sliders/flexslider/css/flexslider.css' type='text/css' media='all' />
    <link rel='stylesheet' id='flexsliderslider-css'  href='sliders/flexslider/css/slider.css' type='text/css' media='all' />
    <link rel='stylesheet' id='flex-elegant-slider-css'  href='sliders/flexslider-elegant/css/flexslider.css' type='text/css' media='all' />
    <link rel='stylesheet' id='polaroid-slider-css'  href='sliders/polaroid/css/polaroid.css' type='text/css' media='all' />
    <link rel='stylesheet' id='ahortcodes-css'  href='css/shortcodes.css' type='text/css' media='all' />
    <link rel='stylesheet' id='contact-form-css'  href='css/contact_form.css' type='text/css' media='all' />
    <link rel='stylesheet' id='custom-css'  href='css/custom.css' type='text/css' media='all' />
    <link rel='stylesheet' id='featurestab-css'  href='css/featurestab.css' type='text/css' media='all' />
    <link rel='stylesheet' id='fontawesome-css'  href='css/font-awesome.css' type='text/css' media='all' />
    <link rel='stylesheet' id='usquare-css' href='sliders/usquare/css/frontend/usquare_style.css' type='text/css' media='all' />
    <link rel='stylesheet' id='rotating-css' href='css/usquare.css' type='text/css' media='all' />
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

    <!-- SLOGAN -->
    <div class="slogan">
        <h2>WELCOME TO NCIP CEBU CONNECT.</h2>
        <h3>are you thrilled to explore?</h3>
    </div>

    <!-- START PRIMARY -->
    <div id="primary" class="sidebar-right">
        <div class="container group">
            <div class="row">
                <!-- START CONTENT -->
                <div id="content-home" class="span9 content group">
                    <div class="page type-page status-publish group">
                        <div class="row">
                            <div id="features-tab-libra" class="features-tab-container  group span9 margin-bottom">
                                <div class="row">

                                    <ul class="features-tab-labels span3">
                                        <li class="features-tab-0 current-feature withicon">
                                            <img src="images/hi.png" title="WelcomeNCIP" alt="Welcome to NCIP" />
                                            Welcome to NCIP
                                        </li>

                                        <li class="features-tab-1  withicon">
                                            <img src="images/seo.png" title=" Mandate, Vision & Mission" alt=" Mandate, Vision & Mission" />
                                            Mandate, Vision &amp; Mission
                                        </li>
                                        
                                        <li class="features-tab-2  withicon">
                                            <img src="images/sample.png" title="Power & Functions" alt="Power & Functions" />
                                            Power &amp; Functions
                                        </li>

                                        <li class="features-tab-3  withicon">
                                            <img src="images/shortcodes.png" title="14 Major Thematic Concerns" alt="14 Major Thematic Concerns" />
                                            14 Major Thematic Concerns
                                        </li>

                                        <li class="features-tab-4  withicon">
                                            <img src="images/republic-of-the-philippines.png" title="Republic Act. 8371" alt="Republic Act. 8371" />
                                            Republic Act. 8371
                                        </li>

                                        <li class="features-tab-5  withicon">
                                            <img src="images/organizational-structure.png" title="NCIP Organizational Stucture" alt="NCIP Organizational Stucture" />
                                            NCIP Organizational Stucture
                                        </li>

                                        <li class="features-tab-6  withicon">
                                            <img src="images/transparency-seal.png" title="Transparency Seal" alt="Transparency Seal" />
                                            Transparency Seal
                                        </li>

                                        <li class="features-tab-7  withicon">
                                            <img src="images/citizen-charter.png" title="Citizen's Charter" alt="Citizen's Charter" />
                                            Citizen's Charter
                                        </li>

                                        <li class="features-tab-8  withicon">
                                            <img src="images/bids.png" title="Bids & Awards" alt="Bids & Awards" />
                                            Bids &amp; Awards
                                        </li>
                                    </ul>

                                    <div class="features-tab-wrapper span6">

                                        <div class="features-tab-content features-tab-0 current-feature">
                                            <p>
                                                <img src="images/panitikan.png" alt="Panitikan" width="568" height="385" style="display:block;margin:auto;cursor:pointer;" onclick="document.getElementById('imgModal').style.display='flex';" />

                                                <div id="imgModal" style="display:none;position:fixed;z-index:9999;left:0;top:0;width:100%;height:100%;background:rgba(0,0,0,0.8);justify-content:center;align-items:center;flex-direction:column;">
                                                    <span onclick="document.getElementById('imgModal').style.display='none';" style="position:absolute;top:20px;right:30px;font-size:40px;font-weight:bold;color:white;cursor:pointer;">&times;</span>
                                                    <img src="images/panitikan.png" alt="Panitikan Full" style="max-width:90%;max-height:80%;" />
                                                </div>

                                                <p style="text-align: justify; text-indent: 1rem;">
                                                    We are honored to have you visit our provincial website. The NCIP is committed to upholding, protecting, and promoting the rights and welfare of Indigenous Cultural Communities/Indigenous Peoples (ICCs/IPs) across the Philippines. Through this platform, we aim to provide accessible information, resources, and services that reflect our dedication to inclusivity, cultural integrity, and sustainable development. Whether you are a member of an indigenous community, a researcher, a partner organization, or simply someone who wants to learn more, this site is designed to guide and inform you.<br>
                                                </p>
                                                <p style="text-align: justify; text-indent: 1rem;">
                                                    Here at NCIP, we believe in empowering indigenous communities by recognizing their rights to ancestral domains, self-governance, social justice, and cultural integrity. Our website serves as a gateway to understanding the programs, projects, and policies we implement in collaboration with stakeholders nationwide. We invite you to explore, engage, and connect with us as we continue our mission to build a society that respects and celebrates the rich diversity of the Filipino indigenous peoples.
                                                </p>
                                            </p>
                                        </div>
                                        <div class="features-tab-content features-tab-1">
                                            <h4 style="color: #425262;">MANDATE</h4>
                                            <p style="text-align: justify;">
                                                The NCIP shall protect and promote the interest and well-being of the Indigenous Cultural Communities/Indigenous Peoples with due regard to their beliefs, customs, traditions, and institutions.
                                            </p>
                                        
                                            <h4 style="color: #425262;">VISION</h4>
                                            <p style="text-align: justify;">
                                                By 2040, all Philippine Indigenous Cultural Communities/Indigenous Peoples will be fully empowered, their rights genuinely fulfilled and realized, their cultural heritage observed, respected, and preserved, and their ancestral domains and land sustainably protected and developed, ensuring active participation and contribution to nation-building with their identity remaining intact as they adapt to evolving times, and thus securing a lasting legacy for future generations.
                                            </p>
                                        
                                            <h4 style="color: #425262;">MISSION</h4>
                                            <p style="text-align: justify;">
                                                A trusted partner and lead advocate of ICCs/IPs in upholding their rights and well-being as enshrined in the Indigenous Peoples’ Rights Act.
                                            </p>
                                        </div>                                        
                                        <div class="features-tab-content features-tab-2 ">
                                            <p>
                                                To accomplish its mandate, the NCIP shall have the following powers, jurisdiction and function:
                                            </p>
                                            <ol type="a" style="text-align: justify;">
                                                <li>To serve as the primary government agency through which ICCs/IPs can seek government assistance and as the medium, through which such assistance may be extended;</li>
                                                <li>To review and assess the condition of ICCs/IPs including existing laws and policies pertinent thereto and to propose relevant laws and policies to address role in national development;</li>
                                                <li>To formulate and implement policies, plans, programs and projects for the economic, social and cultural development of the ICCs/IPs and to monitor the implementation thereof;</li>
                                                <li>To request and engage the services and support of experts from other agencies of government employ private experts and consultants as may be required in the pursuit of its objectives;</li>
                                                <li>To issue certificate of ancestral land/domain title;</li>
                                                <li>Subject to existing laws, to enter into contracts, agreements, or arrangement, with government or private agencies or entities as may be necessary to attain the objectives of this Act, and subject to the approval of the President, to obtain loans from government lending institutions and other lending institutions to finance its programs;</li>
                                            </ol>
                                        </div>
                                        <div class="features-tab-content features-tab-3 ">
                                            <ol>
                                                <li>Formal recognition of Ancestral Domains</li>
                                                <li>Control and management of Ancestral Domains</li>
                                                <li>NCIP’s capacity to deliver its mandate</li>
                                                <li>Destruction of the ecosystems within Ancestral Domains</li>
                                                <li>Non-compliance and violation of FPIC</li>
                                                <li>Pagkawala ng kultura ng IPs</li>
                                                <li>IP governace is weak</li>
                                                <li>Impact of government services are not felt</li>
                                                <li>Overlapping claims over Ancestral Domains</li>
                                                <li>Di pagkilala ng mga aghensya sa mga IP leaders</li>
                                                <li>Displacement of IPs form their Ancestral Domains</li>
                                                <li>Kakulangan sa kaalaman sa karapatan</li>
                                                <li>Discrimination of IPs</li>
                                                <li>Peace and Security</li>
                                            </ol>
                                        </div>
                                        <div class="features-tab-content features-tab-4 ">
                                            <a class="button" href="docs/IPRA-LAW.pdf" target="_blank" rel="noopener noreferrer">DOWNLOAD FILE</a>
                                            <div style="width: 100%; background-color: #1e1e1e; padding: 0; margin: 0;">
                                                    <iframe 
                                                    src="docs/IPRA-LAW.pdf#toolbar=0&navpanes=0" 
                                                    width="100%" 
                                                    height="850px" 
                                                    style="border: none;">
                                                    </iframe>
                                            </div>
                                        </div>
                                        <div class="features-tab-content features-tab-5 ">
                                            <p>
                                                <img src="images/NCIP-Org-Structure.png" alt="NCIP-Org-Structure" width="568" height="385" style="display:block;margin:auto;cursor:pointer;" onclick="document.getElementById('imgOrgModal').style.display='flex';" />

                                                <div id="imgOrgModal" style="display:none;position:fixed;z-index:9999;left:0;top:0;width:100%;height:100%;background:rgba(0,0,0,0.8);justify-content:center;align-items:center;flex-direction:column;">
                                                    <span onclick="document.getElementById('imgOrgModal').style.display='none';" style="position:absolute;top:20px;right:30px;font-size:40px;font-weight:bold;color:white;cursor:pointer;">&times;</span>
                                                    <img src="images/NCIP-Org-Structure.png" alt="NCIP-Org-Structure" style="max-width:90%;max-height:80%;" />
                                                </div>

                                                <p style="text-align: justify; text-indent: 1rem;">
                                                    The organizational structure of the National Commission on Indigenous Peoples (NCIP) is designed to effectively uphold and protect the rights of Indigenous Cultural Communities/Indigenous Peoples (ICCs/IPs) in the Philippines. It follows a centralized yet regionally represented system, headed by a Chairperson and supported by six Commissioners representing different ethnolinguistic regions. The NCIP operates through its central office, regional offices, provincial offices, and service centers, ensuring grassroots-level accessibility and implementation of programs. This structure enables the agency to efficiently carry out its mandate under the Indigenous Peoples' Rights Act (IPRA), focusing on ancestral domain recognition, cultural preservation, and the promotion of IP welfare.
                                                </p>
                                            </p>
                                        </div>
                                        <div class="features-tab-content features-tab-6 " style="text-align: justify;">
                                            <div class="toggle">
                                                <h4 class="tab-index tab-opened"><a href="#" title="Close" style="font-size: 12px;"><span class="icon-minus-sign"></span><b> NATIONAL BUDGET CIRCULAR 542</b></a></h4>

                                                <div class="content-tab opened">
                                                    <p>National Budget Circular 542, issued by the Department of Budget and Management on August 29, 2012, reiterates compliance with Section 93 of the General Appropriations Act of FY 2012. Section 93 is the Transparency Seal provision, to wit:<br><br>Sec. 93. Transparency Seal. To enhance transparency and enforce accountability, all national government agencies shall maintain a transparency seal on their official websites. The transparency seal shall contain the following information: (i) the agency’s mandates and functions, names of its officials with their position and designation, and contact information; (ii) annual reports, as required under National Budget Circular Nos. 507 and 507-A dated January 31, 2007 and June 12, 2007, respectively, for the last three (3) years; (iii) their respective approved budgets and corresponding targets immediately upon approval of this Act; (iv) major programs and projects categorized in accordance with the five key results areas under E.O. No. 43, s. 2011; (v) the program/projects beneficiaries as identified in the applicable special provisions; (vi) status of implementation and program/project evaluation and/or assessment reports; and (vii) annual procurement plan, contracts awarded and the name of contractors/suppliers/consultants. The respective heads of the agencies shall be responsible for ensuring compliance with this section.<br><br>A Transparency Seal, prominently displayed on the main page of the website of a particular government agency, is a certificate that it has complied with the requirements of Section 93. This Seal links to a page within the agency’s website which contains an index of downloadable items of each of the above-mentioned documents.</p>
                                                </div>
                                            </div>
                                            <div class="toggle">
                                                <h4 class="tab-index tab-closed"><a href="#" title="Close" style="font-size: 12px;"><span class="icon-plus-sign"></span><b> Symbolism</b></a></h4>

                                                <div class="content-tab closed">
                                                    <p>A pearl buried inside a tightly-shut shell is practically worthless. Government information is a pearl, meant to be shared with the public in order to maximize its inherent value.<br><br>The Transparency Seal, depicted by a pearl shining out of an open shell, is a symbol of a policy shift towards openness in access to government information. On the one hand, it hopes to inspire Filipinos in the civil service to be more open to citizen engagement; on the other, to invite the Filipino citizenry to exercise their right to participate in governance.<br><br>This initiative is envisioned as a step in the right direction towards solidifying the position of the Philippines as the Pearl of the Orient – a shining example for democratic virtue in the region.</p>
                                                </div>
                                            </div>
                                            <div class="toggle">
                                                <h4 class="tab-index tab-closed"><a href="#" title="Close" style="font-size: 12px;"><span class="icon-plus-sign"></span><b> National Commission on Indigenous Cultural Communities/lndigenous Peoples (NCIP) Compliance with Section 93 (Transparency Seal)</b></a></h4>

                                                <div class="content-tab closed">
                                                    <ol type="I" style="margin-left: 30px; padding-left: 0; list-style-position: inside;">
                                                    <li style="color: #0c243d;"><b>Agency’s Mandate, Vision, Mission and List of Officials</b></li>
                                                    <ul>
                                                        <li><a href="https://ncip.gov.ph/?page_id=89" target="_blank">NCIP’s Mandate, Vision & Mission and Functions</a></li>
                                                        <li><a href="https://ncip.gov.ph/ncip-officials/" target="_blank">NCIP Officials</a></li>
                                                    </ul>

                                                    <li style="color: #0c243d;"><b>Annual Financial Reports</b></li>
                                                    <ul>
                                                        <li>Annual Physical Report of Operations/Physical Plan</li>
                                                        <ul>
                                                            <li><a href="https://ncip.gov.ph/wp-content/uploads/2024/03/2023-NCIP-BAR-1-as-of-Dec-31-2023.pdf" target="_blank">2023 BAR I</a></li>
                                                            <li><a href="https://ncip.gov.ph/wp-content/uploads/2023/03/2022-BAR-1-Summary-Transparency-Seal.pdf" target="_blank">2022 BAR I</a></li>
                                                            <li><a href="https://ncip.gov.ph/wp-content/uploads/2023/03/SUMMARY-BAR-1-URS-4th-Quarter-2021-with-comments-merged.pdf" target="_blank">2021 BAR I</a></li>
                                                            <li><a href="https://ncip.gov.ph/wp-content/uploads/2021/06/bar-2020.pdf" target="_blank">2020 BAR I</a></li>
                                                            <li><a href="https://ncip.gov.ph/wp-content/uploads/2020/08/Consolidated-BAR-1-Report-FY-2019.pdf" target="_blank">2019 BAR I</a></li>
                                                            <li><a href="https://ncip.gov.ph/wp-content/uploads/2024/03/2018-Consolidated-BAR-1-Report.pdf" target="_blank">2018 BAR I</a></li>
                                                            <li><a href="https://ncip.gov.ph/wp-content/uploads/2020/08/Consolidated-BAR-1-Report-as-of-December-31-2017-Current.pdf" target="_blank">2017 BAR I</a></li>
                                                            <li><a href="https://ncip.gov.ph/wp-content/uploads/2020/08/Updated-Continuing-Appropriation.pdf" target="_blank">2016 BAR I</a></li>
                                                            <li><a href="https://ncip.gov.ph/wp-content/uploads/2020/08/NCIP-CONSOLIDATED-BAR-1-REPORT-2015-.pdf" target="_blank">2015 BAR I</a></li>
                                                        </ul>
                                                        <li>Statement of Appropriations, Allotments, Obligations, Disbursements and Balances (SAAOBDB)</li>
                                                        <ul>
                                                            <li>2023 FAR No. 1 | <a href="https://ncip.gov.ph/wp-content/uploads/2024/03/Consolidated-FAR-1-Continuing-for-Quarter-Ending-Dec-31-2024.pdf" target="_blank">Continuing as of December 2023</a> | <a href="https://ncip.gov.ph/wp-content/uploads/2024/03/Consolidated-FAR-1-Current-for-Quarter-Ending-Dec-31-2023.pdf" target="_blank">Current as of December 2023</a></li>
                                                            <li>2022 FAR No. 1 | <a href="https://ncip.gov.ph/wp-content/uploads/2022/08/FAR-1-1st-Quarter-2022.pdf" target="_blank">1st Quarter</a> | <a href="https://ncip.gov.ph/wp-content/uploads/2022/08/FAR-1-2nd-QTR.pdf" target="_blank">2nd Quarter</a> | <a href="https://ncip.gov.ph/wp-content/uploads/2023/03/FAR-1-4th-Quarter.pdf" target="_blank">4th Quarter</a></li>
                                                            <li>2021 FAR No. 1 | <a href="https://ncip.gov.ph/wp-content/uploads/2021/06/FAR-1-1st-quarter.pdf" target="_blank">1st Quarter</a> | <a href="https://ncip.gov.ph/wp-content/uploads/2022/08/FAR-1-2nd-QTR.pdf" target="_blank">2nd Quarter</a></li>
                                                            <li><a href="https://ncip.gov.ph/wp-content/uploads/2021/06/FAR-12020.pdf" target="_blank">2020 FAR No. 1</a></li>
                                                            <li><a href="https://ncip.gov.ph/wp-content/uploads/2020/08/FAR-1-Dec-2019.pdf" target="_blank">2019 FAR No. 1</a></li>
                                                            <li><a href="https://ncip.gov.ph/wp-content/uploads/2020/08/FAR-No.-1-Authorization-01-December-31-2018.pdf" target="_blank">2018 FAR No. 1</a></li>
                                                            <li><a href="https://ncip.gov.ph/wp-content/uploads/2020/08/Far-1-Dec-31-2017-Authorization-01.pdf" target="_blank">2017 FAR No. 1</a></li>
                                                            <li><a href="https://ncip.gov.ph/wp-content/uploads/2020/08/FAR-No.-1-Dec-31-2016.pdf" target="_blank">2016 FAR No. 1</a></li>
                                                        </ul>
                                                        <li>Aging of Unpaid Obligations</li>
                                                        <ul>
                                                            <li>2023 FAR No. 3 | <a href="https://ncip.gov.ph/wp-content/uploads/2024/03/Consolidated-FAR-3-as-of-December-31-2023.pdf" target="_blank">4th Quarter</a></li>
                                                            <li>2022 FAR No. 3 | <a href="https://ncip.gov.ph/wp-content/uploads/2023/03/FAR-3-December-31-2022.pdf" target="_blank">4th Quarter</a></li>
                                                        </ul>
                                                        <li>Monthly Report of Disbursements</li>
                                                        <ul>
                                                            <li>2023 FAR No. 4 | (Regular, Trust Fund as of <a href="https://ncip.gov.ph/wp-content/uploads/2024/03/Consolidated-FAR-4-Regular-Fund-October-2023.pdf" target="_blank">October</a> / <a href="https://ncip.gov.ph/wp-content/uploads/2024/03/Consolidated-FAR-4-Regular-November-2023.pdf" target="_blank">November</a> / <a href="https://ncip.gov.ph/wp-content/uploads/2024/03/Consolidated-FAR-4-Trust-Fund-December-2023.pdf" target="_blank">December</a> 2023)</li>
                                                            <li>2022 FAR No. 4 | <a href="https://ncip.gov.ph/wp-content/uploads/2022/08/FAR-1-1st-Quarter-2022.pdf" target="_blank">1st Quarter</a> | <a href="https://ncip.gov.ph/wp-content/uploads/2022/08/FAR-4-2nd-QTR.pdf" target="_blank">2nd Quarter</a> | <a href="https://ncip.gov.ph/wp-content/uploads/2023/03/FAR-4-4th-Quarter.pdf" target="_blank">4th Quarter</a></li>
                                                            <li>2021 FAR No. 4 | January | February | March | <a href="https://ncip.gov.ph/wp-content/uploads/2021/08/FAR4_MRD_Apr2021.pdf" target="_blank">April</a> | <a href="https://ncip.gov.ph/wp-content/uploads/2021/08/FAR4_MRD_May2021.pdf" target="_blank">May</a> | <a href="https://ncip.gov.ph/wp-content/uploads/2021/08/FAR4_MRD_June2021.pdf" target="_blank">June</a></li>
                                                            <li><a href="https://ncip.gov.ph/wp-content/uploads/2021/06/FAR-4-2020.pdf" target="_blank">2020 FAR No. 4</a></li>
                                                            <li><a href="https://ncip.gov.ph/wp-content/uploads/2020/08/FAR-4-Dec-2019.pdf" target="_blank">2019 FAR No. 4</a></li>
                                                            <li><a href="https://ncip.gov.ph/wp-content/uploads/2020/08/FAR-No.-4.pdf" target="_blank">2018 FAR No. 4</a></li>
                                                            <li><a href="https://ncip.gov.ph/wp-content/uploads/2020/08/FAR-NO.-4.pdf" target="_blank">2017 FAR No. 4</a></li>
                                                            <li><a href="https://ncip.gov.ph/wp-content/uploads/2020/08/FAR-No.-4-Dec-31-2016.pdf" target="_blank">2016 FAR No. 4</a></li>
                                                        </ul>
                                                        <li>Quarterly Report on Revenue and Other Receipts</li>
                                                        <ul>
                                                            <li>2023 FAR No. 5 | (<a href="https://ncip.gov.ph/wp-content/uploads/2024/03/Consolidated-FAR-5-Trust-Fund-for-the-Quarter-Ending-December-31-2023.pdf" target="_blank">Regular, Trust</a> Fund as of December 2023)</li>
                                                            <li>2022 FAR No. 5 | 1st Quarter | <a href="https://ncip.gov.ph/wp-content/uploads/2022/08/FAR-5-2nd-QTR.pdf" target="_blank">2nd Quarter</a> | <a href="https://ncip.gov.ph/wp-content/uploads/2023/03/FAR-5-4th-Quarter.pdf" target="_blank">4th Quarter</a></li>
                                                            <li>2021 FAR No. 5 | 1st Quarter | <a href="https://ncip.gov.ph/wp-content/uploads/2021/08/FAR5_2ndQtr2021.pdf" target="_blank">2nd Quarter</a></li>
                                                            <li>2020 FAR No. 5 | <a href="https://ncip.gov.ph/wp-content/uploads/2020/11/FAR-5-as-of-MAr-312-020-1.pdf" target="_blank">1st Quarter</a> | <a href="https://ncip.gov.ph/wp-content/uploads/2020/11/FAR-5-as-of-June-30-2020-1.pdf" target="_blank">2nd Quarter</a> | <a href="https://ncip.gov.ph/wp-content/uploads/2020/11/FAR-5.pdf" target="_blank">3rd Quarter</a></li>
                                                            <li>2019 FAR No. 5</li>
                                                            <li><a href="https://ncip.gov.ph/wp-content/uploads/2020/08/FAR-No.-5-Dec-31-2018.pdf" target="_blank">2018 FAR No. 5</a></li>
                                                            <li><a href="https://ncip.gov.ph/wp-content/uploads/2020/08/FAR-NO.-5.pdf" target="_blank">2017 FAR No. 5</a></li>
                                                            <li><a href="https://ncip.gov.ph/wp-content/uploads/2020/08/FAR-No.-5-Dec-31-2016.pdf" target="_blank">2016 FAR No. 5</a></li>
                                                        </ul>
                                                        <li>Financial Plan (Detailed Statement of Current Year’s Obligations, Disbursements and Unpaid Obligations)</li>
                                                        <ul>
                                                            <li>2023 FAR No. 1-A | <a href="https://ncip.gov.ph/wp-content/uploads/2024/03/Consolidated-FAR-1A-Continuing-for-Quarter-Ending-Dec-31-2023.pdf" target="_blank">Continuing as of December 2023</a> | <a href="https://ncip.gov.ph/wp-content/uploads/2024/03/Consolidated-FAR-1A-Current-for-Quarter-Ending-Dec-31-2023.pdf" target="_blank">Current as of December 2023</a></li>
                                                            <li>2022 FAR No. 1-A | <a href="https://ncip.gov.ph/wp-content/uploads/2022/08/FAR-1-1st-Quarter-2022.pdf" target="_blank">1st Quarter</a> | <a href="https://ncip.gov.ph/wp-content/uploads/2022/08/FAR-1a-2nd-QTR.pdf" target="_blank">2nd Quarter</a> | <a href="https://ncip.gov.ph/wp-content/uploads/2023/03/FAR-1a-4th-Quarter.pdf" target="_blank">4th QuarterA</a></li>
                                                            <li>2021 FAR No. 1-A | 1ST Quarter | <a href="https://ncip.gov.ph/wp-content/uploads/2021/08/FAR1A_2ndQtr2021.pdf" target="_blank">2nd Quarter</a></li>
                                                            <li>2020 FAR No. 1-A | <a href="https://ncip.gov.ph/wp-content/uploads/2020/11/FAR-1a-as-of-MAr-312-020.pdf" target="_blank">1st Quarter</a> | <a href="https://ncip.gov.ph/wp-content/uploads/2020/11/FAR1a_Current-as-of-June-30-2020.pdf" target="_blank">2nd Quarter</a> | <a href="https://ncip.gov.ph/wp-content/uploads/2020/11/FAR1A.pdf" target="_blank">3rd Quarter</a></li>
                                                            <li>2019 FAR No. 1-A</li>
                                                            <li><a href="https://ncip.gov.ph/wp-content/uploads/2020/08/FAR-No.-5-Dec-31-2018.pdf" target="_blank">2018 FAR No. 1-A</a></li>
                                                            <li><a href="https://ncip.gov.ph/wp-content/uploads/2020/08/Far-1-A-Dec-31-2017-Authorization-01.pdf" target="_blank">2017 FAR No. 1-A</a></li>
                                                            <li><a href="https://ncip.gov.ph/wp-content/uploads/2020/08/FAR-No.-1-A-Dec-31-2016.pdf" target="_blank">2016 FAR No. 1-A</a></li>
                                                        </ul>
                                                        <li>List of Allotments and Sub-allotments</li>
                                                        <ul>
                                                            <li>2022 FAR No. 1B | <a href="https://ncip.gov.ph/wp-content/uploads/2023/03/FAR-1B-December-31-2022.pdf" target="_blank">4th Quarter</a></li>
                                                        </ul>
                                                    </ul>

                                                    <li style="color: #0c243d;"><b>DBM Approved Budget and Corresponding Targets</b></li>
                                                    <ul>
                                                        <li><a href="https://ncip.gov.ph/wp-content/uploads/2023/10/NCIP-GAA-2023-Targets_merged.pdf" target="_blank">2023</a></li>
                                                        <li><a href="https://ncip.gov.ph/wp-content/uploads/2023/10/NCIP-GAA-2022_merged.pdf" target="_blank">2022</a></li>
                                                        <li><a href="https://ncip.gov.ph/wp-content/uploads/2022/08/2021-GAA.pdf" target="_blank">2021</a></li>
                                                        <li><a href="https://ncip.gov.ph/wp-content/uploads/2020/08/2020.pdf" target="_blank">2020</a></li>
                                                        <li><a href="https://ncip.gov.ph/wp-content/uploads/2020/08/2019.pdf" target="_blank">2019</a></li>
                                                        <li><a href="https://ncip.gov.ph/wp-content/uploads/2020/08/2018.pdf" target="_blank">2018</a></li>
                                                        <li><a href="https://ncip.gov.ph/wp-content/uploads/2020/08/2017.pdf" target="_blank">2017</a></li>
                                                        <li><a href="https://ncip.gov.ph/wp-content/uploads/2020/08/2016.pdf" target="_blank">2016</a></li>
                                                    </ul>

                                                    <li style="color: #0c243d;"><b>Projects, Programs and Activities, Beneficiaries, and Status of Implementation</b></li>
                                                    <ul>
                                                        <li><a href="https://ncip.gov.ph/wp-content/uploads/2025/04/PPBER-Transparency-Seal-2024.pdf" target="_blank">2024</a></li>
                                                        <li><a href="https://ncip.gov.ph/wp-content/uploads/2025/04/PPBER-Transparency-Seal-2023.pdf" target="_blank">2023</a></li>
                                                        <li><a href="https://ncip.gov.ph/wp-content/uploads/2023/08/PPBER-Transparency-Seal-2022.pdf" target="_blank">2022</a></li>
                                                        <li><a href="https://ncip.gov.ph/wp-content/uploads/2022/08/Projects-Programs-and-Activities-Beneficiaries-and-Status-of-Implementation-2021.pdf" target="_blank">2021</a></li>
                                                        <li><a href="https://ncip.gov.ph/wp-content/uploads/2022/08/Projects-Programs-and-Activities-Beneficiaries-and-Status-of-Implementation-2020.pdf" target="_blank">2020</a></li>
                                                        <li><a href="https://ncip.gov.ph/wp-content/uploads/2022/08/Projects-Programs-and-Activities-Beneficiaries-and-Status-of-Implementation-2019.pdfhttps://ncip.gov.ph/wp-content/uploads/2022/08/Projects-Programs-and-Activities-Beneficiaries-and-Status-of-Implementation-2019.pdf" target="_blank">2019</a></li>
                                                        <li><a href="https://ncip.gov.ph/wp-content/uploads/2022/08/Projects-Programs-and-Activities-Beneficiaries-and-Status-of-Implementation-2018.pdf" target="_blank">2018</a></li>
                                                        <li><a href="https://ncip.gov.ph/wp-content/uploads/2022/08/Projects-Programs-and-Activities-Beneficiaries-and-Status-of-Implementation-2017.pdf" target="_blank">2017</a></li>
                                                        <li><a href="https://ncip.gov.ph/wp-content/uploads/2022/08/Projects-Programs-and-Activities-Beneficiaries-and-Status-of-Implementation-2016.pdf" target="_blank">2016</a></li>
                                                    </ul>

                                                    <li style="color: #0c243d;"><b>Agency Procurement Plan</b></li>
                                                    <ul>
                                                        <li>Annual Procurement Plan</li>
                                                        <ul>
                                                        <li>2025 <a href="https://ncip.gov.ph/wp-content/uploads/2024/11/Indicative-APP-FY-2025-Non-CSE.pdf" target="_blank">Indicative APP Non-CSE</a> | <a href="https://ncip.gov.ph/wp-content/uploads/2025/02/Annual-Procurement-Plan-APP-NON-CSE-FY-2025.pdf" target="_blank">APP 2025</a> | <a href="https://ncip.gov.ph/wp-content/uploads/2025/03/SAPP-No.-1-FY-2025.pdf" target="_blank">SAPP no 1</a></li>
                                                        <li>2024 <a href="https://ncip.gov.ph/wp-content/uploads/2023/09/Indicative-APP-non-CSE-FY-2024.pdf" target="_blank">Indicative APP NON-CSE</a> | <a href="https://ncip.gov.ph/wp-content/uploads/2024/01/APP-2024.pdf" target="_blank">APP 2024</a> | <a href="https://ncip.gov.ph/wp-content/uploads/2024/07/SAPP-NO.-1-12-FY-2024.pdf" target="_blank">SAPP no. 1-12</a> | <a href="https://ncip.gov.ph/wp-content/uploads/2025/02/SAPP-No.-12-26-FY-2024.pdf" target="_blank">SAPP no. 12-26</a></li>
                                                        <li>2023 <a href="https://ncip.gov.ph/wp-content/uploads/2022/09/indicative-APP-FY-2023.pdf" target="_blank">Indicative APP</a> | <a href="https://ncip.gov.ph/wp-content/uploads/2022/11/SIAPP-1-FY-2023.pdf" target="_blank">Supplemental Indicative APP no.1</a> | <a href="https://ncip.gov.ph/wp-content/uploads/2023/01/APP-2023.pdf" target="_blank">APP 2023</a> | <a href="https://ncip.gov.ph/wp-content/uploads/2023/07/SAPP-2023-12st-Semester.pdf" target="_blank">SAPP no. 1-16</a> | <a href="https://ncip.gov.ph/wp-content/uploads/2024/02/SAPP-17-26.pdf" target="_blank">SAPP No. 17-26</a></li>
                                                        <li>2022 <a href="https://ncip.gov.ph/wp-content/uploads/2021/10/Indicative-APP-non-CSE-FY-2022.pdf" target="_blank">Indicative APP</a> | <a href="https://ncip.gov.ph/wp-content/uploads/2022/01/APP-FY-2022.pdf" target="_blank">APP 2022</a> | <a href="https://ncip.gov.ph/wp-content/uploads/2022/07/SAPP-NO.-1-11-FY-2022.pdf" target="_blank">SAPP 1-11</a> | <a href="https://ncip.gov.ph/wp-content/uploads/2023/02/SAPP-12-28.pdf" target="_blank">SAPP 12-28</a></li>
                                                        <li>2021 <a href="https://ncip.gov.ph/wp-content/uploads/2021/08/APP_2021.pdf" target="_blank">APP CSE</a> | <a href="https://ncip.gov.ph/wp-content/uploads/2021/03/APP-Non-CSE-FY-2021.pdf" target="_blank">APP non-CSE</a> | <a href="https://ncip.gov.ph/wp-content/uploads/2020/11/Indicative-APP-Non-CSE-FY-2021.pdf" target="_blank">APP non-CSE</a> (Indicative) | <a href="https://ncip.gov.ph/wp-content/uploads/2021/08/SAPP2021_1-10.pdf" target="_blank">SAPP 1-10</a> | <a href="https://ncip.gov.ph/wp-content/uploads/2022/01/SAPP-10-22-FY-2021.pdf" target="_blank">SAPP 10-22</a></li>
                                                        <li>2020 <a href="https://ncip.gov.ph/wp-content/uploads/2020/10/APP2020.pdf" target="_blank">APP CSE</a> | <a href="https://ncip.gov.ph/wp-content/uploads/2020/09/APP-NON-CSE-2019.pdf" target="_blank">APP non-CSE</a> | <a href="https://ncip.gov.ph/wp-content/uploads/2020/10/SAPP1-10-FY2020.pdf" target="_blank">SAPP 1-10</a></li>
                                                        <li>2019 <a href="https://ncip.gov.ph/wp-content/uploads/2020/10/APP2020.pdf" target="_blank">APP CSE</a> | <a href="https://ncip.gov.ph/wp-content/uploads/2020/09/APP-NON-CSE-2019.pdf" target="_blank">APP non-CSE</a></li>
                                                        <li>2018 <a href="https://ncip.gov.ph/wp-content/uploads/2020/09/2018-APP.pdf" target="_blank">APP CSE</a> | <a href="https://ncip.gov.ph/wp-content/uploads/2020/09/NONAPP-2018.pdf" target="_blank">APP non-CSE</a></li>
                                                        <li>2017 APP CSE | APP non-CSE</li>
                                                        <li>2016 APP CSE | APP non-CSE</li>
                                                        </ul>

                                                        <li>Agency Procurement Compliance and Performance Indicators (APCPI)</li>
                                                        <ul>
                                                        <li>2024 APCPI</li>
                                                        <ul>
                                                            <li><a href="https://ncip.gov.ph/wp-content/uploads/2024/07/1st-Semester-FY-2024-NCIP-CO-Procurement-Monitoring-Report.pdf" target="_blank">2024 Procurement Monitoring Report (1st Semester)</a></li>
                                                            <li><a href="https://ncip.gov.ph/wp-content/uploads/2025/02/FY-2024-PMR-for-2nd-Semester.pdf" target="_blank">2024 Procurement Monitoring Report (2nd Semester)</a></li>
                                                            <li><a href="https://ncip.gov.ph/wp-content/uploads/2025/03/Annexes_APCPI-FY-2024.pdf" target="_blank">Annexes</a></li>
                                                        </ul>
                                                        <li><a href="https://ncip.gov.ph/wp-content/uploads/2024/04/APCPI-2023.pdf" target="_blank">2023 APCPI</a></li>
                                                        <ul>
                                                            <li><a href="https://ncip.gov.ph/wp-content/uploads/2024/03/PMR-2nd-Sem-FY-2023.pdf" target="_blank">2023 Procurement Monitoring Report (2nd Semester)</a></li>
                                                            <li><a href="https://ncip.gov.ph/wp-content/uploads/2023/07/NCIP-PMR-FY-2023-1st-Sem.pdf" target="_blank">2023 Procurement Monitoring Report (1st Semester)</a></li>
                                                        </ul>
                                                        <li><a href="https://ncip.gov.ph/wp-content/uploads/2023/03/APCPI-FY-2023.pdf" target="_blank">2022 APCPI</a></li>
                                                        <ul>
                                                            <li><a href="https://ncip.gov.ph/wp-content/uploads/2023/01/2-PMR-2nd-Semester-FY-2022.pdf" target="_blank">2022 Procurement Monitoring Report (2nd Semester)</a></li>
                                                            <li><a href="https://ncip.gov.ph/wp-content/uploads/2022/07/PMR-1st-Semester-FY-2022.pdf" target="_blank">2022 Procurement Monitoring Report (1st Semester)</a></li>
                                                        </ul>
                                                        <li><a href="https://ncip.gov.ph/wp-content/uploads/2022/02/APCPI-2021.pdf" target="_blank">2021 APCPI</a></li>
                                                        <ul>
                                                            <li><a href="https://ncip.gov.ph/wp-content/uploads/2022/01/PMR_2nd-Sem-2021-final.pdf" target="_blank">2021 Procurement Monitoring Report (2nd Sem)</a></li>
                                                        </ul>
                                                        </ul>
                                                    </ul>

                                                    <li style="color: #0c243d;"><b>Quality Management System Certification</b></li>
                                                    <li style="color: #0c243d;"><b>System of Agency Ranking Delivery Units</b></li>

                                                    <li style="color: #0c243d;"><b>The Agency Review and Compliance Procedure of Statements and Financial Disclosure</b></li>
                                                    <ul>
                                                        <li style="color: #0c243d;"><b>Procedures on the Review and Compliance of SALN</b></li>
                                                        <ul>
                                                        <li><a href="https://ncip.gov.ph/wp-content/uploads/2021/08/SALN-Compliance.pdf" target="_blank">Agency Review and Compliance Procedure (ARCP)</a> (no changes)</li>
                                                        <li><a href="https://ncip.gov.ph/wp-content/uploads/2022/02/Review-and-Compliance-Committee-RCC.pdf" target="_blank">Review and Compliance Committee (RCC)</a> (no changes)</li>
                                                        </ul>
                                                    </ul>

                                                    <li style="color: #0c243d;"><b>Freedom of Information (FOI) Manual signed by Head of Agency</b></li>
                                                    <ul>
                                                        <li><a href="https://ncip.gov.ph/wp-content/uploads/2021/02/07-16.2017-FOI-Manual.pdf" target="_blank">Peoples FOI Manual</a></li>
                                                        <li><a href="https://ncip.gov.ph/wp-content/uploads/2025/01/NCIP-One-page-FOI-Manual-English-1.png" target="_blank">NCIP One page FOI Manual (English)</a></li>
                                                        <li><a href="https://ncip.gov.ph/wp-content/uploads/2025/01/NCIP-One-page-FOI-Manual-Filipino-1.png" target="_blank">NCIP One page FOI Manual (Filipino)</a></li>
                                                        <li><a href="https://docs.google.com/spreadsheets/d/1Fn3ZQmT2H2KkvQO0Vo1yYFdAw9NKB8scGAWsAZohjcc" target="_blank">FOI Reports</a></li>
                                                        <li><a href="https://ncip.gov.ph/wp-content/uploads/2024/01/2022-COC_NCIP.pdf" target="_blank">Certificate of Compliance</a></li>
                                                    </ul>

                                                    <li style="color: #0c243d;"><b>PBB Compliance</b></li>
                                                    <ul>
                                                        <li>Scorecards</li>
                                                        <ul>
                                                        <li><a href="https://ncip.gov.ph/wp-content/uploads/2025/04/NCIP_2023-Scorecards.pdf" target="_blank">2023</a></li>
                                                        <li><a href="https://ncip.gov.ph/wp-content/uploads/2024/03/NCIP_2022-Scorecard.pdf" target="_blank">2022</a></li>
                                                        <li><a href="https://ncip.gov.ph/wp-content/uploads/2023/06/NCIP_2021-Scorecard.pdf" target="_blank">2021</a></li>
                                                        <li><a href="https://ncip.gov.ph/wp-content/uploads/2023/06/NCIP_2020_Scorecard.pdf" target="_blank">2020</a></li>
                                                        <li><a href="https://ncip.gov.ph/wp-content/uploads/2023/06/NCIP_2019_Scorecard.pdf" target="_blank">2019</a></li>
                                                        <li><a href="https://ncip.gov.ph/wp-content/uploads/2023/06/NCIP_2018_Scorecard.pdf" target="_blank">2018</a></li>
                                                        </ul>
                                                    </ul>

                                                    <li style="color: #0c243d; list-style-type: none;"><b>NCIP Annual Accomplishment Reports (AAR)</b></li>
                                                    <ul>
                                                        <li>2023 AAR</li>
                                                        <li><a href="https://ncip.gov.ph/wp-content/uploads/2024/03/2022-AAR.pdf" target="_blank">2022 AAR</a></li>
                                                        <li><a href="https://ncip.gov.ph/wp-content/uploads/2024/03/2021-AARv2.pdf" target="_blank">2021 AAR</a></li>
                                                        <li><a href="https://ncip.gov.ph/wp-content/uploads/2024/03/2020-AAR.pdf" target="_blank">2020 AAR</a></li>
                                                        <li><a href="https://ncip.gov.ph/wp-content/uploads/2024/03/2019-AAR.pdf" target="_blank">2019 AAR</a></li>
                                                    </ul>
                                                    </ol>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="features-tab-content features-tab-7 ">
                                            <div class="toggle">
                                                <h4 class="tab-index tab-opened"><a href="#" title="Close" style="font-size: 12px;"><span class="icon-minus-sign"></span><b> NCIP CITIZEN'S CHARTER</b></a></h4>

                                                <div class="content-tab opened" style="border-left: 0;">
                                                    <a class="button" href="docs/1.-NCIP-Citizens-Charter-2024" target="_blank" rel="noopener noreferrer">DOWNLOAD FILE</a>
                                                    <div style="width: 100%; background-color: #1e1e1e; padding: 0; margin: 0;">
                                                        <iframe 
                                                        src="docs/1.-NCIP-Citizens-Charter-2024.pdf#toolbar=0&navpanes=0" 
                                                        width="100%" 
                                                        height="775px" 
                                                        style="border: none;">
                                                        </iframe>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="toggle">
                                                <h4 class="tab-index tab-closed"><a href="#" title="Close" style="font-size: 12px;"><span class="icon-plus-sign"></span><b> CERTIFICATE OF COMPLIANCE</b></a></h4>

                                                <div class="content-tab closed"  style="border-left: 0;">
                                                    <a class="button" href="docs/NCIP_CoC_03262024" target="_blank" rel="noopener noreferrer">DOWNLOAD FILE</a>
                                                    <div style="width: 100%; background-color: #1e1e1e; padding: 0; margin: 0;">
                                                        <iframe 
                                                        src="docs/NCIP_CoC_03262024.pdf#toolbar=0&navpanes=0" 
                                                        width="100%" 
                                                        height="775px" 
                                                        style="border: none;">
                                                        </iframe>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="toggle">
                                                <h4 class="tab-index tab-closed"><a href="#" title="Close" style="font-size: 12px;"><span class="icon-plus-sign"></span><b> INTERNAL AND EXTERNAL SERVICES FORMS</b></a></h4>

                                                <div class="content-tab closed" style="border-left: 0;">
                                                    <div class="short-table white">
                                                        <table style="width: 100%" cellspacing="0" cellpadding="0">
                                                            <thead>
                                                                <tr>
                                                                    <th><b>Services Form</b></th>
                                                                    <th><b>Form Handler</b></th>
                                                                    <th><b>File</b></th>
                                                                </tr>
                                                            </thead>

                                                            <tbody>
                                                                <tr>
                                                                    <td class="align-left">Certificate of No Pending Case Request form</td>
                                                                    <td>LAO</td>
                                                                    <td><a href="https://ncip.gov.ph/wp-content/uploads/2024/03/2F6.-CNPC-Request-form.pdf" target="_blank">Certificate of No Pending Case Request form</a></td>
                                                                </tr>
                                                                <tr>
                                                                    <td class="align-left">Form for the distribution of Communication/ Advocacy Materials</td>
                                                                    <td>FARIPO</td>
                                                                    <td><a href="https://ncip.gov.ph/wp-content/uploads/2024/03/2F7.-Form-for-the-distribution-of-Communication-Advocacy-Materials.pdf" target="_blank">Form for the distribution of Communication/ Advocacy Materials</a></td>
                                                                </tr>
                                                                <tr>
                                                                    <td class="align-left">IEC Production Request form</td>
                                                                    <td>OPPR</td>
                                                                    <td><a href="https://ncip.gov.ph/wp-content/uploads/2024/06/2F8.-IEC-Production-Form_2.pdf" target="_blank">IEC Production Request form</a></td>
                                                                </tr>
                                                                <tr>
                                                                    <td class="align-left">Technical Assistance Request (TAR) Slip v.01</td>
                                                                    <td>OECH</td>
                                                                    <td><a href="https://ncip.gov.ph/wp-content/uploads/2024/03/2F9.-Technical-Assistance-Request-Form.pdf" target="_blank">Technical Assistance Request (TAR) Slip v.01</a></td>
                                                                </tr>
                                                                <tr>
                                                                    <td class="align-left">Request Form for Processing of Request for BIR Form 2316 of Former NCIP Employees</td>
                                                                    <td>OECH</td>
                                                                    <td><a href="https://ncip.gov.ph/wp-content/uploads/2024/03/2F10.-Request-Form-for-Processing-of-Request-for-BIR-Form-2316-of-Former-NCIP-Employees.pdf" target="_blank">Request Form for Processing of Request for BIR Form 2316 of Former NCIP Employees</a></td>
                                                                </tr>
                                                                <tr>
                                                                    <td class="align-left">HR Records Request Form v.01</td>
                                                                    <td>HRMD</td>
                                                                    <td><a href="https://ncip.gov.ph/wp-content/uploads/2024/03/2F11.-HR-Records-Request-Form.pdf" target="_blank">HR Records Request Form v.01</a></td>
                                                                </tr>
                                                                <tr>
                                                                    <td class="align-left">Travel Arranger Form</td>
                                                                    <td>GSD</td>
                                                                    <td><a href="https://ncip.gov.ph/wp-content/uploads/2024/03/2F12.-Travel-Arranger-Form.pdf" target="_blank">Travel Arranger Form</a></td>
                                                                </tr>
                                                                <tr>
                                                                    <td class="align-left">Request Form for issuance of Certification of Properties Issued</td>
                                                                    <td>GSD</td>
                                                                    <td><a href="https://ncip.gov.ph/wp-content/uploads/2024/03/2F13.-Request-Form-for-issuance-of-Certification-of-Properties-Issued.pdf" target="_blank">Request Form for issuance of Certification of Properties Issued</a></td>
                                                                </tr>
                                                                <tr>
                                                                    <td class="align-left">Gate Pass Form</td>
                                                                    <td>GSD</td>
                                                                    <td><a href="https://ncip.gov.ph/wp-content/uploads/2024/03/2F14.-Gate-Pass-Form.pdf" target="_blank">Gate Pass Form</a></td>
                                                                </tr>
                                                                <tr>
                                                                    <td class="align-left">Request Form for issuance of Property Borrow and Return Slip</td>
                                                                    <td>GSD</td>
                                                                    <td><a href="https://ncip.gov.ph/wp-content/uploads/2024/03/2F15.-Request-Form-for-issuance-of-Property-Borrow-and-Return-Slip.pdf" target="_blank">Request Form for issuance of Property Borrow and Return Slip</a></td>
                                                                </tr>
                                                                <tr>
                                                                    <td class="align-left">Request Form for ID replacement</td>
                                                                    <td>GSD</td>
                                                                    <td><a href="https://ncip.gov.ph/wp-content/uploads/2024/03/2F16.-Request-Form-for-ID-replacement.pdf" target="_blank">Request Form for ID replacement</a></td>
                                                                </tr>
                                                                <tr>
                                                                    <td class="align-left">Vehicle Request Form</td>
                                                                    <td>GSD</td>
                                                                    <td><a href="https://ncip.gov.ph/wp-content/uploads/2024/03/2F17.-Vehicle-Request-Form.pdf" target="_blank">Vehicle Request Form</a></td>
                                                                </tr>
                                                                <tr>
                                                                    <td class="align-left">Request Form for the issuance of Certified True Copies/Plain Copies of Case Records</td>
                                                                    <td>OCC</td>
                                                                    <td><a href="https://ncip.gov.ph/wp-content/uploads/2024/03/2F18-and-19.-Request-Form-for-CTC-and-Cert-of-Finality.pdf" target="_blank">Request Form for the issuance of Certified True Copies/Plain Copies of Case Records</a></td>
                                                                </tr>
                                                                <tr>
                                                                    <td class="align-left">Request Form for the issuance of Certificate of Finality of Judgment</td>
                                                                    <td>OCC</td>
                                                                    <td><a href="https://ncip.gov.ph/wp-content/uploads/2024/03/2F18-and-19.-Request-Form-for-CTC-and-Cert-of-Finality.pdf" target="_blank">Request Form for the issuance of Certificate of Finality of Judgment</a></td>
                                                                </tr>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="toggle">
                                                <h4 class="tab-index tab-closed"><a href="#" title="Close" style="font-size: 12px;"><span class="icon-plus-sign"></span><b> FEEDBACK MECHANISM</b></a></h4>

                                                <div class="content-tab closed" style="border-left: 0;">
                                                    <p>For our Clients and Partners:<br><br>Your opinions and feedback on the services availed at the National Commission on Indigenous Peoples – Central Office is very important. It helps us improve our work to offer better quality service.</p>

                                                    <div class="short-table white">
                                                        <table style="width: 100%" cellspacing="0" cellpadding="0">
                                                            <tbody>
                                                                <tr>
                                                                    <td>How to send feedback</td>
                                                                    <td style="text-align: justify;">For Walk-ins: Client may answer the feedback form in the office lobby and put it in the feedback and complaints drop box.<br><br>For online: Feedback Form link can be accessed through <a href="https://forms.gle/1JuFTNJD6AaX97rL8" target="_blank">https://forms.gle/1JuFTNJD6AaX97rL8</a> or client may email at ncipfeedback@gmail.com<br><br>Clients may also call the Central Office Trunkline at (02) 8575-1200 or Hotline at 898-NCIP.</td>
                                                                </tr>
                                                                <tr>
                                                                    <td>How feedbacks are processed</td>
                                                                    <td style="text-align: justify;">Every Friday, the Office of the Executive Director (OED) collects, organizes, and summarizes feedback received from drop box, website, and email.<br><br>Feedbacks are forwarded to the concerned office for action.</td>
                                                                </tr>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="toggle">
                                                <h4 class="tab-index tab-closed"><a href="#" title="Close" style="font-size: 12px;"><span class="icon-plus-sign"></span><b> COMPLAINT MECHANISM</b></a></h4>

                                                <div class="content-tab closed" style="border-left: 0; text-align: justify;">
                                                    <p>
                                                        For our Clients and Partners: <br><br>

                                                        Here at NCIP, we gladly welcome constructive criticism, it serves as a mechanism to keep in check that the respective tasks and functions of NCIP personnel are accordingly performed. Thus, anyone may file a complaint against any personnel who has been discourteous/unhelpful in the course of his/her transaction with the NCIP.<br><br><br>

                                                        <b>How to file a complaint?</b><br><br>

                                                        1. Get a copy of the Complaint Form from NCIP Guest Information Desk Officer (GIDO) or download from NCIP website<br><br>
                                                        2. Fill out the <a href="https://ncip.gov.ph/wp-content/uploads/2024/03/2F3.-Complaint-Form.pdf" target="_blank">Complaint Form</a>. Complaint Form should contain the following:<br><br>
                                                        a. Name of complainant (optional)<br>
                                                        b. Contact number of complainants<br>
                                                        c. Address or email address of complainant (if walk-in) - for purpose of sending reply by the agency<br>
                                                        d. Name of person being complained<br>
                                                        e. Incident or alleged violation of the Citizen’s Charter committed by the person being complained<br>
                                                        f. Evidence, if any<br>
                                                        g. Service availed<br>
                                                        h. Date when service availed<br><br>

                                                        3. For walk-in: Drop 1 original copy of the duly accomplished Complaints Form in any Designated Drop-boxes located at the Guest Information Desks (GIDs) at the 6th and 7th Floor of Sunnymede IT Center, 1614 Quezon Avenue, Quezon City.<br><br>

                                                        If through registered mail or private courier: mail 1 original copy of the duly accomplished Complaint Form and address to NCIP GIDO. Once received by agency, Records Officer to drop at any designated drop box.<br><br>

                                                        If thru email: send duly accomplished Complaint Form to ncipcccomplaints@gmail.com
                                                        For inquiries and follow-ups, clients may call the Central Office Trunkline at (02) 8575-1200 and ask to be redirected to HRMD or email ncipcccomplaints@gmail.com<br><br>

                                                        Walk-in follow ups and inquires will also be entertained by the NCIP GIDO.
                                                        How complaints are processed
                                                        Designated cut-off time for opening<br><br><br>

                                                        <b>How complaints are processed?</b><br><br>

                                                        Designated cut-off time for opening of the drop box: 3pm<br><br>

                                                        ***Complaints received beyond the cut off time shall be considered as complaints for the next working day***<br>
                                                        The designated HR Officer shall open the physical and virtual complaint box to be accompanied by the Guest Information Desk Officer (GIDO), record (with control no.) and evaluate each complaint.<br><br>

                                                        After evaluation, the HRMO shall channel the said complaint to the concerned Bureau Director/Head of concerned Offices which in turn will forward to the concerned personnel.The concerned personnel shall submit his/her response, through the head of office, to the HRMD within five (5) working days from the date of receipt.<br><br>

                                                        The HRMD will then forward the response to the complainant. The HRMD shall then record the actions taken regarding the complaint.<br><br>

                                                        The concerned personnel shall submit his/her response, through the head of office, to the HRMD within five working days from the date of receipt. The HRMD will then forward theresponse to the complainant. The HRMD shall then record the actions taken regarding the complaint.<br>
                                                        Should the complainant, upon receipt of the response, submit a reply thereto, the NCIP Executive Director, upon the recommendation of the NCIP CART shall determine whether the complaint should be considered closed.<br><br>

                                                        If the complainant fails to submit any reply within 15 days, from the date the response was transmitted, the complaint is considered closed.<br><br>

                                                        ***The claim is considered closed if there is no receipt of feedback from the complainant within 15 days from the date the response was transmitted to the complainant***
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="features-tab-content features-tab-8 ">
                                            <div class="toggle">
                                                <h4 class="tab-index tab-opened"><a href="#" title="Close" style="font-size: 12px;"><span class="icon-minus-sign"></span><b>PUBLIC BIDDING</b></a></h4>

                                                    <div class="tabs-container">
                                                        <ul class="tabs">
                                                            <li><h4><a href="#tab1" title="Services" style="color: #676768; font-size: 12px"><strong>INVITATION TO BID</strong></a></h4></li>
                                                            <li><h4><a href="#tab2" title="Customers" style="color: #676768; font-size: 12px"><strong>AWARDS</strong></a></h4></li> 
                                                        </ul>
                                                        <div class="border-box group">
                                                            <div id="tab1" class="panel group">
                                                                <ul style="padding-left: 0;">
                                                                    <li>Procurement of Firewall and Anti-Virus Licenses | Date posted: 4/4/2025 | <a href="https://ncip.gov.ph/wp-content/uploads/2025/04/Bid-Docs_Firewall-and-Antivirus.pdf" target="_blank">Bid Documents</a> | <a href="https://ncip.gov.ph/wp-content/uploads/2025/04/Supplemental-Bulletin-No-1-e-signed.pdf" target="_blank">Supplemental/Bid Bulletin No. 1</a></li>
                                                                    <li>Procurement of Printing and Delivery of NCIP 2024 Annual Accomplishment Report | Date posted: 03/11/2025 | <a href="https://ncip.gov.ph/wp-content/uploads/2025/03/Bid-Docs-Printing-of-AAR_CY-2024-03.10.25.pdf" target="_blank">Bid Documents</a></li>
                                                                    <li>Procurement of Internet Subscription of NCIP Central Office | Date posted: 11/15/2024 | <a href="https://ncip.gov.ph/wp-content/uploads/2024/11/Bid-Docs-Internet-FY-2025.pdf" target="_blank">Bid Documents</a> | <a href="https://ncip.gov.ph/wp-content/uploads/2024/11/bid-bul-internet-2025.pdf" target="_blank">Supplemental Bid Bulletin No. 1</a> | <a href="https://ncip.gov.ph/wp-content/uploads/2024/12/INTERNET-SUBSCRIPTION.pdf" target="_blank">Bid Bulletin No. 2</a></li>
                                                                    <li>Procurement of Security Services for FY 2025 | Date posted: 11/15/2024 | <a href="https://ncip.gov.ph/wp-content/uploads/2024/11/Bid-Docs-Security-FY-2025.pdf" target="_blank">Bid Documents</a> | <a href="https://ncip.gov.ph/wp-content/uploads/2024/12/SUPP-SECURITY.pdf" target="_blank">Supplemental Bid Bulletin No. 1</a></li>
                                                                    <li>Procurement of Janitorial Services for FY 2025 | Date posted: 11/15/2024 | <a href="https://ncip.gov.ph/wp-content/uploads/2024/11/Bid-Docs-Janitorial-FY-2025.pdf" target="_blank">Bid Documents</a> | <a href="https://ncip.gov.ph/wp-content/uploads/2024/12/SUPP-JANITORIAL.pdf" target="_blank">Supplemental Bid Bulletin No. 1</a></li>
                                                                    <li>Procurement of Printing and Delivery of NCIP Annual Accomplishment Report (AAR) | Date posted: 09/20/2024 | <a href="https://ncip.gov.ph/wp-content/uploads/2024/09/Printing-and-Delivery-of-NCIP-AAR.pdf" target="_blank">Bid Documents</a></li>
                                                                    <li>Procurement of Server | Date posted: 05/18/2024 | <a href="https://ncipictd-my.sharepoint.com/:b:/g/personal/oppr_ird_ncip_gov_ph/EcsSRDEAycpNrRvXFlRYhUIB6_crW81aw2yBs33FC65bQA?e=U1sFSX" target="_blank">Bid Documents</a> | <a href="https://ncip.gov.ph/wp-content/uploads/2024/08/Supplemental-BId-Bulletin-Server.pdf" target="_blank">Supplemental Bid Bulletin No. 1</a></li>
                                                                    <li>Procurement of Motor Vehicle | Date posted: 05/18/2024 | <a href="https://ncipictd-my.sharepoint.com/:b:/g/personal/oppr_ird_ncip_gov_ph/EQvKo_q_coZGo7mzlFivPBkB536rVVYCKg6uBs1iRnff1g?e=CmqKOW" target="_blank">Bid Documents</a> | <a href="https://ncip.gov.ph/wp-content/uploads/2024/08/Supplemental-BId-Motor-Vehicle_08.07.24.pdf" target="_blank">Supplemental Bid Bulletin No. 1</a></li>
                                                                    <li>Procurement of Accounting and Budget Information System | Date posted: 05/18/2024 | <a href="https://ncipictd-my.sharepoint.com/:b:/g/personal/oppr_ird_ncip_gov_ph/Eb47xzwCVSJMpWv91mJ1j3wBvaLRVySD9TsR4YJ0w_IcJA?e=MRkkhv" target="_blank">Bid Documents</a> | <a href="https://ncip.gov.ph/wp-content/uploads/2024/08/Supplemental-BId-Bulletin-ABIS.pdf" target="_blank">Supplemental Bid Bulletin No. 1</a> | <a href="https://ncip.gov.ph/wp-content/uploads/2024/08/TOR-and-Annex-A-ABIS.pdf" target="_blank">TOR</a></li>
                                                                    <li>Procurement of Documents Management System and Services for the Digitization of NCIP Vital Records and Documents | Date Posted: 05/28/2024 | <a href="https://ncip.gov.ph/wp-content/uploads/2024/05/Bid-Docs_Digitization.pdf" target="_blank">Bid Documents</a> | <a href="https://ncip.gov.ph/wp-content/uploads/2024/05/TOR-Signed.pdf" target="_blank">TOR</a></li>
                                                                    <li>Procurement of Motor Vehicle | Date posted: 05/28/2024 | <a href="https://ncip.gov.ph/wp-content/uploads/2024/05/Bid-Docs_Motor-Vehicle.pdf" target="_blank">Bid Documents</a></li>
                                                                    <li>Supply and Delivery of ICT Network and Multimedia Equipment, and Licenses | Date Posted: 04/30/2024 | <a href="https://ncip.gov.ph/wp-content/uploads/2024/04/Bids-Docs-for-the-Supply-and-Delivery-of-ICT-Network-and-Multimedia-Equipment-and-Licenses.pdf" target="_blank">Bid Documents</a> | <a href="https://ncip.gov.ph/wp-content/uploads/2024/05/Supplemental-Bid-Bulletin-No.-1-supply-delivery-of-NCIP-Network-Multimedia-Equipment-and-Licenses.pdf" target="_blank">Supplemental/Bid Bulletin No. 1</a></li>
                                                                    <li>Procurement of Multimedia Editing Software | Date Posted: 04/19/2024 | <a href="https://ncip.gov.ph/wp-content/uploads/2024/04/Bid-Docs_Procurement-of-Multimedia-Editing-Software.pdf" target="_blank">Bid Documents</a> | <a href="https://ncip.gov.ph/wp-content/uploads/2024/05/BId-Buletin-Multi-Media_1.pdf" target="_blank">Supplemental/Bid Bulletin No. 1</a></li>
                                                                    <li>Disposal of Used/Unserviceable Properties and Equipment | Date Posted: 03/21/2024 | <a href="https://ncip.gov.ph/wp-content/uploads/2024/03/Invitation-to-Bid.pdf" target="_blank">Invitation to Bid</a></li>
                                                                    <li>Procurement of Security Services | Date Posted: 02/21/2024 | <a href="https://ncip.gov.ph/wp-content/uploads/2024/02/02.20.2024-Bid-Docs-for-Security-Services-pdf.pdf" target="_blank">Bid Documents</a> | <a href="https://ncip.gov.ph/wp-content/uploads/2024/03/Supplemental-Bid_Security.pdf" target="_blank">Supplemental/Bid Bulletin No. 1</a></li>
                                                                    <li>Supply and Delivery of ICT Equipment Support to the Establishment of Ancestral Domain Management Offices (ADMOS) | Date Posted: 02/21/2024 | <a href="https://ncip.gov.ph/wp-content/uploads/2024/02/2024.20.02-BID-DOCUMENTS-ICT-Equipment-support-to-ADMOs-with-TOR-and-Annex_1.pdf" target="_blank">Bid Documents</a> | <a href="https://ncip.gov.ph/wp-content/uploads/2024/03/Procurement_of_ICT_Equipment_ADMOs-with-Annex.pdf" target="_blank">Supplemental/Bid Bulletin No. 1</a></li>
                                                                    <li>Procurement of Human Resources Information System | Date Posted: 02/21/2024 | <a href="https://ncip.gov.ph/wp-content/uploads/2024/02/2024.20.02-BID-DOCUMENTS-Procurement-of-Human-Resources-Information-System-with-TOR.pdf" target="_blank">Bid Documents</a> | <a href="https://ncip.gov.ph/wp-content/uploads/2024/03/Supplemental-Bid_HRIS-with-Annex.pdf" target="_blank">Supplemental/Bid Bulletin No. 1</a></li>
                                                                    <li>Procurement of ICT Equipment | Date Posted: 02/21/2024 | <a href="https://ncip.gov.ph/wp-content/uploads/2024/02/2024.20.02-BID-DOCUMENTS-Procurement-of-ICT-Equipment-with-TOR-and.pdf" target="_blank">Bid Documents</a> | <a href="https://ncip.gov.ph/wp-content/uploads/2024/03/Supplemental-Bid_ICT-Equipment.pdf" target="_blank">Supplemental/Bid Bulletin No. 1</a></li>
                                                                </ul>
                                                            </div>
                                                            <div id="tab2" class="panel group">
                                                            </div>
                                                        </div>
                                                    </div>
                                            </div>
                                            <div class="toggle">
                                                <h4 class="tab-index tab-closed"><a href="#" title="Close" style="font-size: 12px;"><span class="icon-plus-sign"></span><b> ALTERNATIVE MODE OF PROCUREMENT</b></a></h4>

                                                <div class="content-tab closed"  style="border-left: 0;">
                                                    <div class="tabs-container">
                                                        <ul class="tabs">
                                                            <li><h4><a href="#tab3" title="Services" style="color: #676768; font-size: 12px"><strong>ADVERTISEMENT</strong></a></h4></li>
                                                            <li><h4><a href="#tab4" title="Customers" style="color: #676768; font-size: 12px"><strong>AWARDS</strong></a></h4></li> 
                                                        </ul>
                                                        <div class="border-box group">
                                                            <div id="tab3" class="panel group">
                                                                <ul style="padding-left: 0;">
                                                                    <li>Procurement of Office Supplies | Date Posted: 04.24.2025 | <a href="https://ncip.gov.ph/wp-content/uploads/2025/04/Procurement-of-Office-Supplies.pdf" target="_blank">RFQ</a></li>
                                                                    <li>Procurement of Janitorial Supplies | Date Posted: 04.07.2025 | <a href="https://ncip.gov.ph/wp-content/uploads/2025/04/RFQ-Janitorial-Supplies.pdf" target="_blank">RFQ</a></li>
                                                                    <li>Procurement of Services of Certifying Body for NCIP Central Office QMS Initial ISO 9001:2015 Certification | Date Posted: 04.04.2025 | <a href="https://ncip.gov.ph/wp-content/uploads/2025/04/RFQ-Certifying-Body_QMS.pdf" target="_blank">RFQ</a></li>
                                                                    <li>Procurement of Office Supplies | Date Posted: 04.03.2025 | <a href="https://ncip.gov.ph/wp-content/uploads/2025/04/RFQ-Office-Supplies-Various-Offices-outside-PS-DBM.pdf" target="_blank">RFQ</a></li>
                                                                    <li>Procurement of Multimedia Editing Software | Date Posted: 04.03.2025 | <a href="https://ncip.gov.ph/wp-content/uploads/2025/04/RFQ-Multimedia-Editing-Software.pdf" target="_blank">RFQ</a></li>
                                                                    <li>Procurement of Secure Socket Layer (SSL) | Date Posted: 04.03.2025 | <a href="https://ncip.gov.ph/wp-content/uploads/2025/04/RFQ-SSL.pdf" target="_blank">RFQ</a></li>
                                                                    <li>Procurement of Medical Supplies and Medicines | Date Posted: 04.03.2025 | <a href="https://ncip.gov.ph/wp-content/uploads/2025/04/RFQ-Medical-Supplies-Medicine.pdf" target="_blank">RFQ</a></li>
                                                                </ul>
                                                            </div>
                                                            <div id="tab4" class="panel group">
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- END CONTENT -->

                <!-- START SIDEBAR -->
                <div id="sidebar-features" class="span3 sidebar group">
                    <div class="widget-1 widget-first widget widget-icon-text group">
                        <img class="imgicon" src="images/emotion_smile.png" alt="" />
                        <h3>We serve Indigenous Peoples</h3>
                        <p>Committed to cultural preservation</p>
                    </div>
                
                    <div class="widget-2 widget widget-icon-text group">
                        <a href="https://ncip.gov.ph/" title="Visit NCIP Official Website">
                            <img class="imgicon" src="images/themes.png" alt="" />
                            <h3>Explore Our Mandates</h3>
                            <p>Learn about our mission and programs</p>
                        </a>
                    </div>
                    <div class="widget-3 widget widget-icon-text group">
                        <a href="https://ncip.gov.ph/downloadables/" title="NCIP Resources">
                            <img class="imgicon" src="images/free.png" alt="" />
                            <h3>Download Resources</h3>
                            <p>Access guidelines, forms, and publications</p>
                        </a>
                    </div>
                    <div class="widget-4 widget-last widget widget_text">
                        <h3>Testimonial:</h3>
                        <div class="textwidget">
                            The NCIP continues to support Indigenous Cultural Communities/Indigenous Peoples across the country. 
                            Their dedication to upholding the rights and welfare of IPs is truly inspiring and essential to nation-building.
                            <strong>[ by IP Advocate ]</strong>
                        </div>
                    </div>
                </div>                
                <!-- END SIDEBAR -->
            </div>
        </div>
    </div>
    <!-- END PRIMARY -->

    <!-- START PAGE META -->
    <div id="page-meta" class="group">
        <div class="container">
            <div class="row">
                <div class="span12">
                    <!-- TITLE -->
                    <div class="title">
                        <div class="icontitle">
                            <img src="images/pages/about.png" alt="title"/>
                        </div>
                        <div class="title-with-icon">
                            <h1>
                                Meet the <span class="title-highlight">NCIP Officials!</span>
                            </h1>
                        </div>
                    </div>

                    <!-- BREDCRUMB -->
                    <div class="breadcrumbs">
                        <span class="before-text">You are here:</span>

                        <p id="yit-breadcrumb" itemprop="breadcrumb">
                            <a class="home" href="index.php">Home Page</a> |
                            <a class="no-link current" href="#">
                                Meet the <span class="title-highlight">NCIP Officials!</span>
                            </a>
                        </p>
                    </div>

                </div>
            </div>
        </div>
    </div>
    <!-- END PAGE META -->
    
    <!-- START SLOGAN -->
    <div class="slogan" style="margin-bottom: 20px;">
        <h2>CHAIRPERSON</h2>
    </div>
    <!-- END SLOGAN  -->

    <!-- START PRIMARY -->
    <div style="display: flex; justify-content: center; align-items: center; flex-direction: column; width: 100%;"> 
        <div id="primary" style="width: 100%; max-width: 960px;">
            <div class="container group">
                <div class="row">
                    <!-- START CONTENT -->
                    <div id="content-page" class="span9 content group">
                        <div class="page type-page status-publish group">

                            <div class="accordion-container">

                                <div class="accordion-wrapper row">
                                    <div class="accordion-title span9">
                                        <div class="plus">+</div>
                                        <h4>Jennifer Pia Sibug-Las</h4>
                                    </div>
                                    <div class="accordion-item span9">
                                        <div class="row">
                                            <div class="span2">
                                                <div class="accordion-item-thumb">
                                                    <img src="images/pages/Jennifer Pia Sibug-Las.jpg" alt="Jennifer Pia Sibug-Las"/>
                                                </div>
                                            </div>
                                            <!-- end span3 -->
                                            <div class="span7">
                                                <div class="accordion-item-content">
                                                    <p style="line-height: 1.8;">
                                                        <i class="fas fa-map" style="color: #6c757d; margin-right: 8px;"></i>
                                                        Central Mindanao<br>

                                                        <i class="fas fa-map-marker-alt" style="color: #d9534f; margin-right: 8px;"></i>
                                                        6th and 7th flr. Sunnymede IT Center, 1614 Quezon Avenue, Brgy. South Triangle, Quezon City<br>
                                                    
                                                        <i class="fas fa-phone" style="color: #5cb85c; margin-right: 8px;"></i>
                                                        (02) 8575-1200<br>
                                                    
                                                        <i class="fas fa-envelope" style="color: #0275d8; margin-right: 8px;"></i>
                                                        <a href="mailto:chairperson@ncip.gov.ph" style="color: #0275d8; text-decoration: none;">
                                                            chairperson@ncip.gov.ph
                                                        </a>
                                                    </p>

                                                    <div class="meta">
                                                        <p>
                                                            <img class="icon" src="images/icons/role.png" alt="role_icon"/>
                                                            Role: Chairperson &amp; Ethnographic Commissioner
                                                        </p>

                                                        <div>
                                                            <div class="social_title">
                                                                <p>
                                                                    <img class="icon" src="images/icons/social-meta.png" alt="social_icon"/>
                                                                    Get in touch:
                                                                </p>
                                                            </div>
                                                            <div class="fade-socials-small facebook-small">
                                                                <a href="#" class="fade-socials-small facebook-small" title="Facebook" style="display: none;"></a>
                                                            </div>
                                                            <div class="fade-socials-small rss-small">
                                                                <a href="#" class="fade-socials-small rss-small" title="Rss" style="display: none;"></a>
                                                            </div>
                                                            <div class="fade-socials-small twitter-small">
                                                                <a href="#" class="fade-socials-small twitter-small" title="Twitter" style="display: none;"></a>
                                                            </div>
                                                            <div class="fade-socials-small google-small">
                                                                <a href="#" class="fade-socials-small google-small" title="Google" style="display: none;"></a>
                                                            </div>
                                                            <div class="fade-socials-small linkedin-small">
                                                                <a href="#" class="fade-socials-small linkedin-small" title="Linkedin" style="display: none;"></a>
                                                            </div>
                                                            <div class="socials-default  default">
                                                                <a href="#" class="socials-default default "></a>
                                                            </div>

                                                        </div>

                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                            </div>

                        <script>
                            jQuery(document).ready(function ($) {
                                $('.accordion-title').click(function () {
                                    if (!$(this).hasClass('active')) {
                                        $('.accordion-title').removeClass('active').find(':first-child').addClass('plus').text("+").removeClass('minus');
                                        $('.accordion-item').slideUp();

                                        $(this).toggleClass('active')
                                                .find(':first-child').removeClass('plus').addClass('minus').text("-").parent().next().slideToggle();
                                    }
                                }).filter(':first').click();
                            });
                        </script>

                        </div>
                    </div>
                    <!-- END CONTENT -->
                </div>
            </div>
        </div>
    </div>
    <!-- END PRIMARY -->
    
    <!-- START SLOGAN -->
    <div class="slogan" style="margin-bottom: 20px;">
        <h2>COMMISSIONERS</h2>
    </div>
    <!-- END SLOGAN  -->

    <!-- START PRIMARY -->
    <div style="display: flex; justify-content: center; align-items: center; flex-direction: column; width: 100%;"> 
        <div id="primary" style="width: 100%; max-width: 960px;">
            <div class="container group">
                <div class="row">
                    <!-- START CONTENT -->
                    <div id="content-page" class="span9 content group">
                        <div class="page type-page status-publish group">

                            <div class="accordion-container">
                                
                                <div class="accordion-wrapper row">
                                    <div class="accordion-title span9">
                                        <div class="plus">+</div>
                                        <h4>Dr. Marie Grace T. Pascua</h4>
                                    </div>
                                    <div class="accordion-item span9">
                                        <div class="row">
                                            <div class="span2">
                                                <div class="accordion-item-thumb">
                                                    <img src="images/pages/Dr. Marie Grace T. Pascua.jpg" alt="Dr. Marie Grace T. Pascua"/>
                                                </div>
                                            </div>
                                            <!-- end span3 -->
                                            <div class="span7">
                                                <div class="accordion-item-content">
                                                    <p style="line-height: 1.8;">
                                                        <i class="fas fa-map" style="color: #6c757d; margin-right: 8px;"></i>
                                                        CAR and Region I<br>

                                                        <i class="fas fa-map-marker-alt" style="color: #d9534f; margin-right: 8px;"></i>
                                                        6th and 7th flr. Sunnymede IT Center, 1614 Quezon Avenue, Brgy. South Triangle, Quezon City<br>
                                                    
                                                        <i class="fas fa-phone" style="color: #5cb85c; margin-right: 8px;"></i>
                                                        (02) 8575-1200<br>
                                                    
                                                        <i class="fas fa-envelope" style="color: #0275d8; margin-right: 8px;"></i>
                                                        <a href="mailto:comm.carand1@ncip.gov.ph" style="color: #0275d8; text-decoration: none;">
                                                            comm.carand1@ncip.gov.ph
                                                        </a>
                                                    </p>

                                                    <div class="meta">
                                                        <p>
                                                            <img class="icon" src="images/icons/role.png" alt="role_icon"/>
                                                            Role: Ethnographic Commissioner
                                                        </p>

                                                        <div>
                                                            <div class="social_title">
                                                                <p>
                                                                    <img class="icon" src="images/icons/social-meta.png" alt="social_icon"/>
                                                                    Get in touch:
                                                                </p>
                                                            </div>
                                                            <div class="fade-socials-small facebook-small">
                                                                <a href="#" class="fade-socials-small facebook-small" title="Facebook" style="display: none;"></a>
                                                            </div>
                                                            <div class="fade-socials-small rss-small">
                                                                <a href="#" class="fade-socials-small rss-small" title="Rss" style="display: none;"></a>
                                                            </div>
                                                            <div class="fade-socials-small twitter-small">
                                                                <a href="#" class="fade-socials-small twitter-small" title="Twitter" style="display: none;"></a>
                                                            </div>
                                                            <div class="fade-socials-small google-small">
                                                                <a href="#" class="fade-socials-small google-small" title="Google" style="display: none;"></a>
                                                            </div>
                                                            <div class="fade-socials-small linkedin-small">
                                                                <a href="#" class="fade-socials-small linkedin-small" title="Linkedin" style="display: none;"></a>
                                                            </div>
                                                            <div class="socials-default  default">
                                                                <a href="#" class="socials-default default "></a>
                                                            </div>

                                                        </div>

                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="accordion-wrapper row">
                                    <div class="accordion-title span9">
                                        <div class="plus">+</div>
                                        <h4>Simplicia P. Hagada</h4>
                                    </div>
                                    <div class="accordion-item span9">
                                        <div class="row">
                                            <div class="span2">
                                                <div class="accordion-item-thumb">
                                                    <img src="images/pages/Simplicia P. Hagada.jpg" alt="Simplicia P. Hagada"/>
                                                </div>
                                            </div>
                                            <!-- end span3 -->
                                            <div class="span7">
                                                <div class="accordion-item-content">
                                                    <p style="line-height: 1.8;">
                                                        <i class="fas fa-map" style="color: #6c757d; margin-right: 8px;"></i>
                                                        Region II<br>

                                                        <i class="fas fa-map-marker-alt" style="color: #d9534f; margin-right: 8px;"></i>
                                                        6th and 7th flr. Sunnymede IT Center, 1614 Quezon Avenue, Brgy. South Triangle, Quezon City<br>
                                                    
                                                        <i class="fas fa-phone" style="color: #5cb85c; margin-right: 8px;"></i>
                                                        (02) 8575-1200<br>
                                                    
                                                        <i class="fas fa-envelope" style="color: #0275d8; margin-right: 8px;"></i>
                                                        <a href="mailto:comm.region2@ncip.gov.ph" style="color: #0275d8; text-decoration: none;">
                                                            comm.region2@ncip.gov.ph
                                                        </a>
                                                    </p>

                                                    <div class="meta">
                                                        <p>
                                                            <img class="icon" src="images/icons/role.png" alt="role_icon"/>
                                                            Role: Ethnographic Commissioner
                                                        </p>

                                                        <div>
                                                            <div class="social_title">
                                                                <p>
                                                                    <img class="icon" src="images/icons/social-meta.png" alt="social_icon"/>
                                                                    Get in touch:
                                                                </p>
                                                            </div>
                                                            <div class="fade-socials-small facebook-small">
                                                                <a href="#" class="fade-socials-small facebook-small" title="Facebook" style="display: none;"></a>
                                                            </div>
                                                            <div class="fade-socials-small rss-small">
                                                                <a href="#" class="fade-socials-small rss-small" title="Rss" style="display: none;"></a>
                                                            </div>
                                                            <div class="fade-socials-small twitter-small">
                                                                <a href="#" class="fade-socials-small twitter-small" title="Twitter" style="display: none;"></a>
                                                            </div>
                                                            <div class="fade-socials-small google-small">
                                                                <a href="#" class="fade-socials-small google-small" title="Google" style="display: none;"></a>
                                                            </div>
                                                            <div class="fade-socials-small linkedin-small">
                                                                <a href="#" class="fade-socials-small linkedin-small" title="Linkedin" style="display: none;"></a>
                                                            </div>
                                                            <div class="socials-default  default">
                                                                <a href="#" class="socials-default default "></a>
                                                            </div>

                                                        </div>

                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="accordion-wrapper row">
                                    <div class="accordion-title span9">
                                        <div class="plus">+</div>
                                        <h4>Jeorge M. Largado</h4>
                                    </div>
                                    <div class="accordion-item span9">
                                        <div class="row">
                                            <div class="span2">
                                                <div class="accordion-item-thumb">
                                                    <img src="images/pages/Jeorge M. Largado.jpg" alt="Jeorge M. Largado"/>
                                                </div>
                                            </div>
                                            <!-- end span3 -->
                                            <div class="span7">
                                                <div class="accordion-item-content">
                                                    <p style="line-height: 1.8;">
                                                        <i class="fas fa-map" style="color: #6c757d; margin-right: 8px;"></i>
                                                        Island Groups and Rest of Visayas<br>

                                                        <i class="fas fa-map-marker-alt" style="color: #d9534f; margin-right: 8px;"></i>
                                                        6th and 7th flr. Sunnymede IT Center, 1614 Quezon Avenue, Brgy. South Triangle, Quezon City<br>
                                                    
                                                        <i class="fas fa-phone" style="color: #5cb85c; margin-right: 8px;"></i>
                                                        (02) 8575-1200<br>
                                                    
                                                        <i class="fas fa-envelope" style="color: #0275d8; margin-right: 8px;"></i>
                                                        <a href="mailto:comm.islandandrestofvisayas@ncip.gov.ph" style="color: #0275d8; text-decoration: none;">
                                                            comm.islandandrestofvisayas@ncip.gov.ph
                                                        </a>
                                                    </p>

                                                    <div class="meta">
                                                        <p>
                                                            <img class="icon" src="images/icons/role.png" alt="role_icon"/>
                                                            Role: Ethnographic Commissioner
                                                        </p>

                                                        <div>
                                                            <div class="social_title">
                                                                <p>
                                                                    <img class="icon" src="images/icons/social-meta.png" alt="social_icon"/>
                                                                    Get in touch:
                                                                </p>
                                                            </div>
                                                            <div class="fade-socials-small facebook-small">
                                                                <a href="#" class="fade-socials-small facebook-small" title="Facebook" style="display: none;"></a>
                                                            </div>
                                                            <div class="fade-socials-small rss-small">
                                                                <a href="#" class="fade-socials-small rss-small" title="Rss" style="display: none;"></a>
                                                            </div>
                                                            <div class="fade-socials-small twitter-small">
                                                                <a href="#" class="fade-socials-small twitter-small" title="Twitter" style="display: none;"></a>
                                                            </div>
                                                            <div class="fade-socials-small google-small">
                                                                <a href="#" class="fade-socials-small google-small" title="Google" style="display: none;"></a>
                                                            </div>
                                                            <div class="fade-socials-small linkedin-small">
                                                                <a href="#" class="fade-socials-small linkedin-small" title="Linkedin" style="display: none;"></a>
                                                            </div>
                                                            <div class="socials-default  default">
                                                                <a href="#" class="socials-default default "></a>
                                                            </div>

                                                        </div>

                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="accordion-wrapper row">
                                    <div class="accordion-title span9">
                                        <div class="plus">+</div>
                                        <h4>Atty. Rhodex P. Valenciano</h4>
                                    </div>
                                    <div class="accordion-item span9">
                                        <div class="row">
                                            <div class="span2">
                                                <div class="accordion-item-thumb">
                                                    <img src="images/pages/Atty. Rhodex P. Valenciano.jpg" alt="Atty. Rhodex P. Valenciano"/>
                                                </div>
                                            </div>
                                            <!-- end span3 -->
                                            <div class="span7">
                                                <div class="accordion-item-content">
                                                    <p style="line-height: 1.8;">
                                                        <i class="fas fa-map" style="color: #6c757d; margin-right: 8px;"></i>
                                                        Region III and Rest of Luzon<br>

                                                        <i class="fas fa-map-marker-alt" style="color: #d9534f; margin-right: 8px;"></i>
                                                        6th and 7th flr. Sunnymede IT Center, 1614 Quezon Avenue, Brgy. South Triangle, Quezon City<br>
                                                    
                                                        <i class="fas fa-phone" style="color: #5cb85c; margin-right: 8px;"></i>
                                                        (02) 8575-1200<br>
                                                    
                                                        <i class="fas fa-envelope" style="color: #0275d8; margin-right: 8px;"></i>
                                                        <a href="mailto:comm.region3andrestluzon@ncip.gov.ph" style="color: #0275d8; text-decoration: none;">
                                                            comm.region3andrestluzon@ncip.gov.ph
                                                        </a>
                                                    </p>

                                                    <div class="meta">
                                                        <p>
                                                            <img class="icon" src="images/icons/role.png" alt="role_icon"/>
                                                            Role: Ethnographic Commissioner
                                                        </p>

                                                        <div>
                                                            <div class="social_title">
                                                                <p>
                                                                    <img class="icon" src="images/icons/social-meta.png" alt="social_icon"/>
                                                                    Get in touch:
                                                                </p>
                                                            </div>
                                                            <div class="fade-socials-small facebook-small">
                                                                <a href="#" class="fade-socials-small facebook-small" title="Facebook" style="display: none;"></a>
                                                            </div>
                                                            <div class="fade-socials-small rss-small">
                                                                <a href="#" class="fade-socials-small rss-small" title="Rss" style="display: none;"></a>
                                                            </div>
                                                            <div class="fade-socials-small twitter-small">
                                                                <a href="#" class="fade-socials-small twitter-small" title="Twitter" style="display: none;"></a>
                                                            </div>
                                                            <div class="fade-socials-small google-small">
                                                                <a href="#" class="fade-socials-small google-small" title="Google" style="display: none;"></a>
                                                            </div>
                                                            <div class="fade-socials-small linkedin-small">
                                                                <a href="#" class="fade-socials-small linkedin-small" title="Linkedin" style="display: none;"></a>
                                                            </div>
                                                            <div class="socials-default  default">
                                                                <a href="#" class="socials-default default "></a>
                                                            </div>

                                                        </div>

                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="accordion-wrapper row">
                                    <div class="accordion-title span9">
                                        <div class="plus">+</div>
                                        <h4>Atty. Pinky Grace P. Pabelic</h4>
                                    </div>
                                    <div class="accordion-item span9">
                                        <div class="row">
                                            <div class="span2">
                                                <div class="accordion-item-thumb">
                                                    <img src="images/pages/Atty. Pinky Grace P. Pabelic.jpg" alt="Atty. Pinky Grace P. Pabelic"/>
                                                </div>
                                            </div>
                                            <!-- end span3 -->
                                            <div class="span7">
                                                <div class="accordion-item-content">
                                                    <p style="line-height: 1.8;">
                                                        <i class="fas fa-map" style="color: #6c757d; margin-right: 8px;"></i>
                                                        Northern and Western Mindanao<br>

                                                        <i class="fas fa-map-marker-alt" style="color: #d9534f; margin-right: 8px;"></i>
                                                        6th and 7th flr. Sunnymede IT Center, 1614 Quezon Avenue, Brgy. South Triangle, Quezon City<br>
                                                    
                                                        <i class="fas fa-phone" style="color: #5cb85c; margin-right: 8px;"></i>
                                                        (02) 8575-1200<br>
                                                    
                                                        <i class="fas fa-envelope" style="color: #0275d8; margin-right: 8px;"></i>
                                                        <a href="mailto:comm.northernandwesternmindanao@ncip.gov.ph" style="color: #0275d8; text-decoration: none;">
                                                            comm.northernandwesternmindanao@ncip.gov.ph
                                                        </a>
                                                    </p>

                                                    <div class="meta">
                                                        <p>
                                                            <img class="icon" src="images/icons/role.png" alt="role_icon"/>
                                                            Role: Ethnographic Commissioner
                                                        </p>

                                                        <div>
                                                            <div class="social_title">
                                                                <p>
                                                                    <img class="icon" src="images/icons/social-meta.png" alt="social_icon"/>
                                                                    Get in touch:
                                                                </p>
                                                            </div>
                                                            <div class="fade-socials-small facebook-small">
                                                                <a href="#" class="fade-socials-small facebook-small" title="Facebook" style="display: none;"></a>
                                                            </div>
                                                            <div class="fade-socials-small rss-small">
                                                                <a href="#" class="fade-socials-small rss-small" title="Rss" style="display: none;"></a>
                                                            </div>
                                                            <div class="fade-socials-small twitter-small">
                                                                <a href="#" class="fade-socials-small twitter-small" title="Twitter" style="display: none;"></a>
                                                            </div>
                                                            <div class="fade-socials-small google-small">
                                                                <a href="#" class="fade-socials-small google-small" title="Google" style="display: none;"></a>
                                                            </div>
                                                            <div class="fade-socials-small linkedin-small">
                                                                <a href="#" class="fade-socials-small linkedin-small" title="Linkedin" style="display: none;"></a>
                                                            </div>
                                                            <div class="socials-default  default">
                                                                <a href="#" class="socials-default default "></a>
                                                            </div>

                                                        </div>

                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        <script>
                            jQuery(document).ready(function ($) {
                                $('.accordion-title').click(function () {
                                    if (!$(this).hasClass('active')) {
                                        $('.accordion-title').removeClass('active').find(':first-child').addClass('plus').text("+").removeClass('minus');
                                        $('.accordion-item').slideUp();

                                        $(this).toggleClass('active')
                                                .find(':first-child').removeClass('plus').addClass('minus').text("-").parent().next().slideToggle();
                                    }
                                }).filter(':first').click();
                            });
                        </script>

                        </div>
                    </div>
                    <!-- END CONTENT -->
                </div>
            </div>
        </div>
    </div>
    <!-- END PRIMARY -->

    <!-- START SLOGAN -->
    <div class="slogan" style="margin-bottom: 20px; margin-top: -30px;">
        <h2>REGION 6/7 REGIONAL DIRECTOR</h2>
    </div>
    <!-- END SLOGAN  -->  
     
    <!-- START PRIMARY -->
    <div style="display: flex; justify-content: center; align-items: center; flex-direction: column; width: 100%;"> 
        <div id="primary" style="width: 100%; max-width: 960px;">
            <div class="container group">
                <div class="row">
                    <!-- START CONTENT -->
                    <div id="content-page" class="span9 content group">
                        <div class="page type-page status-publish group">

                            <div class="accordion-container">

                                <div class="accordion-wrapper row">
                                    <div class="accordion-title span9">
                                        <div class="plus">+</div>
                                        <h4>Atty. Princess May A. Oral</h4>
                                    </div>
                                    <div class="accordion-item span9">
                                        <div class="row">
                                            <div class="span2">
                                                <div class="accordion-item-thumb">
                                                    <img src="images/pages/Atty. Princess May A. Oral.jpg" alt="Atty. Princess May A. Oral"/>
                                                </div>
                                            </div>
                                            <!-- end span3 -->
                                            <div class="span7">
                                                <div class="accordion-item-content">
                                                    <p style="line-height: 1.8;">
                                                        <i class="fas fa-map" style="color: #6c757d; margin-right: 8px;"></i>
                                                        Region VI/VII<br>

                                                        <i class="fas fa-map-marker-alt" style="color: #d9534f; margin-right: 8px;"></i>
                                                        Sen. Benigno Aquino Avenue, Bankers Village VI, Dungon B. Jaro, Iloilo City<br>
                                                    
                                                        <i class="fas fa-phone" style="color: #5cb85c; margin-right: 8px;"></i>
                                                        (033) 321-01-47/ Fax: (033) 321-01-47<br>
                                                    
                                                        <i class="fas fa-envelope" style="color: #0275d8; margin-right: 8px;"></i>
                                                        <a href="mailto:region67@ncip.gov.ph" style="color: #0275d8; text-decoration: none;">
                                                            region67@ncip.gov.ph
                                                        </a>
                                                    </p>

                                                    <div class="meta">
                                                        <p>
                                                            <img class="icon" src="images/icons/role.png" alt="role_icon"/>
                                                            Role: Director IV
                                                        </p>

                                                        <div>
                                                            <div class="social_title">
                                                                <p>
                                                                    <img class="icon" src="images/icons/social-meta.png" alt="social_icon"/>
                                                                    Get in touch:
                                                                </p>
                                                            </div>
                                                            <div class="fade-socials-small facebook-small">
                                                                <a href="#" class="fade-socials-small facebook-small" title="Facebook" style="display: none;"></a>
                                                            </div>
                                                            <div class="fade-socials-small rss-small">
                                                                <a href="#" class="fade-socials-small rss-small" title="Rss" style="display: none;"></a>
                                                            </div>
                                                            <div class="fade-socials-small twitter-small">
                                                                <a href="#" class="fade-socials-small twitter-small" title="Twitter" style="display: none;"></a>
                                                            </div>
                                                            <div class="fade-socials-small google-small">
                                                                <a href="#" class="fade-socials-small google-small" title="Google" style="display: none;"></a>
                                                            </div>
                                                            <div class="fade-socials-small linkedin-small">
                                                                <a href="#" class="fade-socials-small linkedin-small" title="Linkedin" style="display: none;"></a>
                                                            </div>
                                                            <div class="socials-default  default">
                                                                <a href="#" class="socials-default default "></a>
                                                            </div>

                                                        </div>

                                                    </div>
                                                </div>
                                            </div>
                                     </div>
                                    </div>
                                </div>

                            </div>

                        <script>
                            jQuery(document).ready(function ($) {
                                $('.accordion-title').click(function () {
                                    if (!$(this).hasClass('active')) {
                                        $('.accordion-title').removeClass('active').find(':first-child').addClass('plus').text("+").removeClass('minus');
                                        $('.accordion-item').slideUp();

                                        $(this).toggleClass('active')
                                                .find(':first-child').removeClass('plus').addClass('minus').text("-").parent().next().slideToggle();
                                    }
                                }).filter(':first').click();
                            });
                        </script>

                        </div>
                    </div>
                    <!-- END CONTENT -->
                </div>
            </div>
        </div>
    </div>
    <!-- END PRIMARY -->

    <!-- START SLOGAN -->
    <div class="slogan" style="margin-bottom: 20px; margin-top: -30px;">
        <h2>PROVINCIAL OFFICE EMPLOYEES</h2>
    </div>
    <!-- END SLOGAN  -->
     
    <!-- START PRIMARY -->
    <div id="primary" class="sidebar-no" style="margin-top: -20px;">
            <div class="container group">
                <div class="row">
                                <!-- START CONTENT -->
                    <div id="content-page" class="span12 content group">
                            <div class="page type-page status-publish  group">

                                <div id="2" class="slider slider-266 usquare">

                                    <div class="usquare_module_wrapper usquare_items_3">
                                        <div class="usquare_module_shade"></div>
                                        <div class="usquare_block">
                                            <img src="images/slider/usquare/00110.jpg" class="usquare_square" alt="" style="background-color: transparent; width: 195px; height: 195px;"/>

                                            <div class="usquare_square" style="background-color: transparent; width: 195px; height: 195px;">
                                                <div class="usquare_square_text_wrapper">
                                                    <img src="sliders/usquare/images/arrow-dark.png" class="usquare_arrow" alt="arrow">

                                                    <div class="clear"></div>
                                                    <h2>JOHN DOE</h2>
                                                    <span>graphic designer / illustrator</span>

                                                    <div class="clear"></div>
                                                </div>
                                                <!-- usquare_square_wrapper -->
                                            </div>
                                            <!--div usquare_square -->
                                            <div class="usquare_block_extended" style="background-color: #fff">
                                                <a href="#" class="close">
                                                    <img src="sliders/usquare/images/close.png" alt="close"/>
                                                </a>
                                                <ul class="social_background"></ul>
                                                <div class="clear"></div>
                                                <!--span class="bold">info:</span-->
                                                <div class="usquare_about">
                                                    Lorem ipsum dolor sit amet, consectetur adipiscing elit. In nec magna lacus.
                                                    Sed sed mi est. Nulla in tristique tortor. Curabitur ipsum orci, bibendum in
                                                    interdum et, congue
                                                    viverra nisl. In sit amet dui mauris, vel hendrerit eros. Nunc scelerisque
                                                    pellentesque magna, et
                                                    tempus massa sagittis at.
                                                </div>
                                            </div>
                                            <!-- usquare_block_extended -->
                                        </div>

                                        <div class="usquare_block">
                                            <img src="images/slider/usquare/00213.jpg" class="usquare_square" alt="" style="background-color: transparent; width: 195px; height: 195px;"/>

                                            <div class="usquare_square" style="background-color: transparent; width: 195px; height: 195px;">
                                                <div class="usquare_square_text_wrapper">
                                                    <img src="sliders/usquare/images/arrow-dark.png" class="usquare_arrow" alt="arrow">

                                                    <div class="clear"></div>
                                                    <h2>JANE DOE</h2> <span>web designer / illustrator</span>

                                                    <div class="clear"></div>
                                                </div>
                                                <!-- usquare_square_wrapper -->
                                            </div>
                                            <!--div usquare_square -->
                                            <div class="usquare_block_extended" style="background-color: #fff">
                                                <a href="#" class="close">
                                                    <img src="sliders/usquare/images/close.png" alt="close"/>
                                                </a>
                                                <ul class="social_background"></ul>
                                                <div class="clear"></div>
                                                <!--span class="bold">info:</span-->
                                                <div class="usquare_about">
                                                    Lorem ipsum dolor sit amet, consectetur adipiscing elit. In nec magna lacus.
                                                    Sed sed mi est. Nulla in tristique tortor. Curabitur ipsum orci, bibendum in
                                                    interdum et, congue
                                                    viverra nisl. In sit amet dui mauris, vel hendrerit eros. Nunc scelerisque
                                                    pellentesque magna, et
                                                    tempus massa sagittis at.
                                                </div>
                                            </div>
                                            <!-- usquare_block_extended -->
                                        </div>
                                        <div class="usquare_block">
                                            <img src="images/slider/usquare/00312.jpg" class="usquare_square" alt="" style="background-color: transparent; width: 195px; height: 195px;"/>

                                            <div class="usquare_square" style="background-color: transparent; width: 195px; height: 195px;">
                                                <div class="usquare_square_text_wrapper">
                                                    <img src="sliders/usquare/images/arrow-dark.png" class="usquare_arrow" alt="arrow">

                                                    <div class="clear"></div>
                                                    <h2>JOHN DOE</h2>
                                                    <span>SEO & SEM Specialist</span>

                                                    <div class="clear"></div>
                                                </div>
                                                <!-- usquare_square_wrapper -->
                                            </div>
                                            <!--div usquare_square -->
                                            <div class="usquare_block_extended" style="background-color: #fff">
                                                <a href="#" class="close"><img src="sliders/usquare/images/close.png" alt="close"/></a>
                                                <ul class="social_background"></ul>
                                                <div class="clear"></div>
                                                <!--span class="bold">info:</span-->
                                                <div class="usquare_about">
                                                    Lorem ipsum dolor sit amet, consectetur adipiscing elit. In nec magna lacus.
                                                    Sed sed mi est. Nulla in tristique tortor. Curabitur ipsum orci, bibendum in
                                                    interdum et, congue
                                                    viverra nisl. In sit amet dui mauris, vel hendrerit eros. Nunc scelerisque
                                                    pellentesque magna, et
                                                    tempus massa sagittis at.
                                                </div>
                                            </div>
                                            <!-- usquare_block_extended -->
                                        </div>
                                        <div class="usquare_block">
                                            <div class="usquare_square" style="background-color: transparent; width: 195px; height: 195px;">
                                                <div class="usquare_square_text_wrapper" style="right: 0; text-align: right;">
                                                    <img src="sliders/usquare/images/arrow_r-dark.png" class="usquare_arrow" alt="arrow" style="float: right; padding: 0 0 5px;">

                                                    <div class="clear"></div>
                                                    <h2>JOHN DOE</h2>
                                                    <span>graphic designer</span>

                                                    <div class="clear"></div>
                                                </div>
                                                <!-- usquare_square_wrapper -->
                                            </div>
                                            <!--div usquare_square -->
                                            <img src="images/slider/usquare/0049.jpg" class="usquare_square" alt="" style="background-color: transparent; width: 195px; height: 195px;"/>

                                            <div class="usquare_block_extended" style="background-color: #fff">
                                                <a href="#" class="close">
                                                    <img src="sliders/usquare/images/close.png" alt="close"/>
                                                </a>
                                                <ul class="social_background"></ul>
                                                <div class="clear"></div>
                                                <!--span class="bold">info:</span-->
                                                <div class="usquare_about">
                                                    Lorem ipsum dolor sit amet, consectetur adipiscing elit. In nec magna lacus.
                                                    Sed sed mi est. Nulla in tristique tortor. Curabitur ipsum orci, bibendum in
                                                    interdum et, congue
                                                    viverra nisl. In sit amet dui mauris, vel hendrerit eros. Nunc scelerisque
                                                    pellentesque magna, et
                                                    tempus massa sagittis at.
                                                </div>
                                            </div>
                                            <!-- usquare_block_extended -->
                                        </div>
                                        <div class="usquare_block">
                                            <div class="usquare_square" style="background-color: transparent; width: 195px; height: 195px;">
                                                <div class="usquare_square_text_wrapper" style="right: 0; text-align: right;">
                                                    <img src="sliders/usquare/images/arrow_r-dark.png" class="usquare_arrow" alt="arrow" style="float: right; padding: 0 0 5px;">

                                                    <div class="clear"></div>
                                                    <h2>MOLLY SMITH</h2>
                                                    <span>web developer</span>

                                                    <div class="clear"></div>
                                                </div>
                                                <!-- usquare_square_wrapper -->
                                            </div>
                                            <!--div usquare_square -->
                                            <img src="images/slider/usquare/0058.jpg" class="usquare_square" alt="" style="background-color: transparent; width: 195px; height: 195px;"/>

                                            <div class="usquare_block_extended" style="background-color: #fff">
                                                <a href="#" class="close">
                                                    <img src="sliders/usquare/images/close.png" alt="close"/>
                                                </a>
                                                <ul class="social_background"></ul>
                                                <div class="clear"></div>
                                                <!--span class="bold">info:</span-->
                                                <div class="usquare_about">Lorem ipsum dolor sit amet, consectetur adipiscing
                                                    elit. In nec magna lacus.
                                                    Sed sed mi est. Nulla in tristique tortor. Curabitur ipsum orci, bibendum in
                                                    interdum et, congue
                                                    viverra nisl. In sit amet dui mauris, vel hendrerit eros. Nunc scelerisque
                                                    pellentesque magna, et
                                                    tempus massa sagittis at.
                                                </div>
                                            </div>
                                            <!-- usquare_block_extended -->
                                        </div>
                                        <div class="usquare_block">
                                            <div class="usquare_square" style="background-color: transparent; width: 195px; height: 195px;">
                                                <div class="usquare_square_text_wrapper" style="right: 0; text-align: right;">
                                                    <img src="sliders/usquare/images/arrow_r-dark.png" class="usquare_arrow" alt="arrow" style="float: right; padding: 0 0 5px;">

                                                    <div class="clear"></div>
                                                    <h2>JOHN DOE</h2>
                                                    <span>CEO / Founder</span>

                                                    <div class="clear"></div>
                                                </div>
                                                <!-- usquare_square_wrapper -->
                                            </div>
                                            <!--div usquare_square -->
                                            <img src="images/slider/usquare/0066.jpg" class="usquare_square" alt="" style="background-color: transparent; width: 195px; height: 195px;"/>

                                            <div class="usquare_block_extended" style="background-color: #fff">
                                                <a href="#" class="close">
                                                    <img src="sliders/usquare/images/close.png" alt="close"/>
                                                </a>
                                                <ul class="social_background"></ul>
                                                <div class="clear"></div>
                                                <!--span class="bold">info:</span-->
                                                <div class="usquare_about">
                                                    Lorem ipsum dolor sit amet, consectetur adipiscing elit. In nec magna lacus.
                                                    Sed sed mi est. Nulla in tristique tortor. Curabitur ipsum orci, bibendum in
                                                    interdum et, congue
                                                    viverra nisl. In sit amet dui mauris, vel hendrerit eros. Nunc scelerisque
                                                    pellentesque magna, et
                                                    tempus massa sagittis at.
                                                </div>
                                            </div>
                                            <!-- usquare_block_extended -->
                                        </div>
                                    </div>
                                    <script> (function ($) {
                                        $(document).ready(function () {
                                            $(".usquare_module_wrapper").uSquare({ opening_speed: 300, closing_speed: 500, easing: "swing" });
                                        });
                                    })(jQuery); </script>
                                </div>

                            </div>
                        <!-- START COMMENTS -->
                        <div id="comments">
                        </div>
                        <!-- END COMMENTS -->
                    </div>
                                <!-- END CONTENT -->


                                <!-- START EXTRA CONTENT -->
                                <!-- END EXTRA CONTENT -->
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
<script type='text/javascript' src='js/jquery.easing.js'></script>
<script type='text/javascript' src='js/hoverIntent.min.js'></script>
<script type='text/javascript' src='js/media-upload.min.js'></script>
<script type='text/javascript' src='js/jquery.flexslider-min.js'></script>
<script type='text/javascript' src='js/jquery.clickout.min.js'></script>
<script type='text/javascript' src='js/jquery.colorbox-min.js'></script>
<script type='text/javascript' src='js/responsive.js'></script>
<script type='text/javascript' src='js/mobilemenu.js'></script>
<script type='text/javascript' src='sliders/usquare/js/frontend/jquery.usquare.js'></script>
<script type='text/javascript' src='sliders/usquare/js/frontend/jquery.mCustomScrollbar.min.js'></script>
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