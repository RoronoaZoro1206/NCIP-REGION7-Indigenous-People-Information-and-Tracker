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
    <link rel="icon" href="images/ncip-header-logo.png" type="image/png">

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
    <link rel='stylesheet' id='colorbox-css'  href='css/colorbox.css' type='text/css' media='all' />
    <link rel='stylesheet' id='flexislider-css'  href='sliders/flexslider/css/flexslider.css' type='text/css' media='all' />
    <link rel='stylesheet' id='flexislider-slider-css'  href='sliders/flexslider/css/slider.css' type='text/css' media='all' />
    <link rel='stylesheet' id='custom-css'  href='css/custom.css' type='text/css' media='all' />
    <!-- Add Bootstrap Icons for admin buttons -->
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

    <!-- START SCROLL TO TOP BUTTON -->
    <?php include 'components/scroll-to-top.php'; ?>
    <!-- END SCROLL TO TOP BUTTON -->

    <!-- SCROLL TO TOP BUTTON -->
    <?php include 'components/scroll-to-top.php'; ?>
    <!-- HEADER -->
    <?php include 'components/header.php'; ?>

    <!-- START PRIMARY -->
    <div id="primary" class="sidebar-right">
            <div class="container group">
                <div class="row">


                <!-- START CONTENT -->
                <div id="content-page" class="span9 content group">

                    <div class="clear"></div>

                    <div class="posts">
                        <div class="services type-services status-publish hentry hentry-post group blog-big">

                            <!-- post content -->
                            <div class="the-content single group" style="margin-top: 15px;">
                                <div style="position: relative; display: inline-block; width: 100%;">
                                    <h1 class="post-title" style="font-weight: bold; border-bottom: 1px solid #ccc; padding-bottom: 10px; margin: 0; padding-right: 60px;"></h1>
                                    <a href="javascript:history.back()" style="position: absolute; right: 0; top: 20%; transform: translateY(-50%); text-decoration: none; font-size: 40px;" title="Go Back">←</a>
                                </div>
                                
                                <p id="service-description" style="text-align: justify;"> </p>
                            </div>

                            <div class="clear"></div>

                        </div>
                    </div>
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

<script>
    const params = new URLSearchParams(window.location.search);
    const service = params.get('service');

    const services = {
        'ancestral': {
            title: "Ancestral Domain / Land Recognition",
            content: `Through the Indigenous Peoples Rights Act (IPRA), Certificate of Ancestral Domain Titles (CADTs) are issued to formally recognize the rights of possession and ownership of Indigenous Cultural Communities/Indigenous Peoples (ICCs/IPs) over their ancestral domains as identified and delineated in accordance with this law, while Certificate of Ancestral Land Titles (CALTs) formally recognize the rights of ICCs/IPs over their ancestral lands.`
        },
        'adsdpp': {
            title: "Assistance to Ancestral Domain Sustainable Development and Protection Plan (ADSDPP) Formulation",
            content: `
                Provision of technical and/or financial assistance to the Indigenous Cultural Communities/Indigenous Peoples (ICCs/IPs) in the formulation of their Ancestral Domain Sustainable Development and Protection Plan (ADSDPP). This holistic, comprehensive, and integrated plan shall promote a culture and rights-based approach to development. It reflects the present and future desired conditions of the ICCs/IPs and contains the types of programs/projects that they will adopt for the sustainable management and development of their domain and community.

                The management plan shall include, but not be limited to, the following basic information.<br><br>

                <ol>
                    <li>The manner by which the concerned ICCs/IPs shall protect their ancestral domain;</li>
                    <li>The development programs related to livelihood, education, infrastructure, self-governance, environment, natural resources, culture, and other practical development aspects, that are decided and adopted by the ICCs/IPs;</li>
                    <li>Community policies covering the implementation of all forms of development activities in the area;</li>
                    <li>Management System, including the sharing of benefits and responsibilities among members of the concerned ICCs/IPs.</li>
                </ol>

                The program aims to promote all the rights of ICCs/IPs within the framework of national unity and development and shall protect the rights of Indigenous Peoples to their ancestral domains to ensure their economic, social, and cultural well-being; and to recognize the inherent rights of ICCs/IPs to self-governance and self-determination, and respect the integrity of their values, practices, and institutions as well as guarantee their right to freely pursue their development and equally enjoy the full measure of human rights and freedom without distinction or discrimination.
            `
        },
        'culturally': {
            title: "Culturally Appropriate Responsive and Gender-Sensitive Socio-Economic And Ecology Development Protection Services",
            content: `The program provides for policy support and extension of assistance to ICCs/IPs through funding under the MOOE of the Commission apart from coordination with pertinent government agencies especially charged with the implementation of various socio-economic services, policies, and programs affecting the ICCs/IPs to ensure that the ICCs/IPs are directly benefited.`
        },
        'education': {
            title: "IP Education and Advocacy Services",
            content: `The Educational Assistance Program. It is the program that aims to provide limited financial assistance to qualified ICCs/IPs students/pupils based on the criteria set forth in NCIP Administrative Order No. 5, series of 2012, otherwise known as NCIP Guidelines of 2012 on the Merit-Based Scholarship (NCIP-MBS) and Educational Assistance (NCIP-EA) and its amendments by virtue of Commission En Banc Resolution No. 06-099-2014, series of 2014.<br><br>

            The Merit-Based Scholarship Program. It is a program that aimed of providing meaningful scholarship to qualified/deserving IP students based on screening criteria and benefits or privileges set forth in the Guidelines.
            The Support and Advocacy Program. This collective term refers to education-related projects and activities that complement the EAP and MBSP and Advocate holistic development to include initiatives other than educational assistance and scholarship, such as but not limited to Licensure Examination for Teachers (LET) and other Review programs; Bridging Programs, School and Community-related Health Programs; Appropriate Social Infrastructures; and Trainings and Research Programs for Culturally Appropriate IP Education.`
        },
        'culture': {
            title: "IP Culture Services",
            content: `Considered as a mechanism of assisting the cultural communities preserve their cultural and historical heritage and at the same time evoking public awareness and respect for the IPs and their rights, is the extension of support to them in the practice of their rituals and ceremonies whenever these are necessary held. The performance of cultural manifestations as in rites, songs dances chants, and games, and the presentation of their native life ways, literature and arts, fabric and architectural designs, artifacts and instruments, in their original versions or in a manner in which they have been held through the years, without romanticism or simply aesthetic motivation, is essential to the IPs authentic flow of life and inherent world views at work. Stereotyping, false representation and commercialization of indigenous cultures are current fads that must be reckoned with and corrected. In here, time is of the essence`
        },
        'health': {
            title: "IP Health Services",
            content: `Republic Act No. 8371 known as The Indigenous Peoples Rights Act of 1997 declares that the State shall recognize and promote all the rights of Indigenous Cultural Communities/Indigenous Peoples (ICCs/IPs) to governments basic services health included. The Universal Health Care (UHC)/Kalusugan Pangkalahatan (KP) (AO 2010-0036) addresses inequities in health outcomes ensuring that all Filipinos have equitable access to health care. The UN Declaration on the Rights of Indigenous Peoples 2007 (UNDRIP) states that Indigenous Peoples have the right to improvement of their economic and social conditions without discrimination; develop priorities and strategies for exercising their right to development; right to traditional medicines maintain their health practices; conserve their vital medicinal resources and access health and social services without discrimination; enjoyment of the highest attainable standard of physical and mental health; maintain, control, protect and develop their cultural heritage, traditional knowledge and cultural expressions and intellectual property over them.`
        },
        'gender': {
            title: "Gender and Rights-based Services",
            content: `This sub-program covers strategic efforts to protect the rights of the Indigenous Peoples to self-governance and self-determination ensuring that, but not limited to the following:</br></br>

            <ol>
                <li>The socio-political structures, systems and institutions of ICCs/IPs are strengthened;</li>
                <li>The Indigenous structures, systems and institutions are not supplanted by other forms of non-indigenous governance; and/or</li>
                <li>The established mechanisms that allow the interfacing of Indigenous systems of governance with the national systems are established; and</li>
                <li>The ICC/IP representation in policy and decision-making bodies are institutionalized.</li>
            <ol/>`
        },
        'rights': {
            title: "IP Rights Advocacy and Monitoring of Treaty Obligations",
            content: `In context, Indigenous Peoples human rights and violations of these rights in hinged on the ICCs/IPs existence or societies of their own even before foreign colonization of the Philippines. Having their own people, government and territories have been the foundation of their cultural identity, rights to self-determination and ancestral domains. To ICCs/IPs therefore, human rights is the respect of their collectiveness as groups of peoples and at the same time, recognizing their rights as citizens of a bigger society of the Philippine Republic. Their rights are human rights too.
            The IP Human Rights Program, with full participation and consultation with ICCs/IPs, aims to contribute to the organizational outcome of the Commission. It seeks to promote the IPRA as an ICCs/IPs national framework of their human rights, to advocate ancestral domains as territories of peace and IPs self-determined development, human security and well- being.<br><br>

            The program strategy shall focus on the IPRA, international rights and other instruments from legal frameworks into a well-defined call for action involving various sectors such as international and national government, non-government organizations (NGOs), civil society groups (CSOs), ICCs/IPs leadership structures and IP organizations and the private sector towards the full enjoyment of ICCs/IPs human rights. A close networks built from ancestral domains, municipality, provincial, regional and national levels should be strengthened. Moreover, coordination and monitoring systems and mechanisms or the promotion and protection of IP rights at all levels shall also be enhanced through the program.<br><br>

            The overall objectives of the program are the following:
            General: To ensure that the basic human rights of indigenous peoples/indigenous cultural communities (IPs/ICCs) basic social, political, cultural and economic rights are respected, recognized, protected and promoted, to promote ancestral domains as territories of peace and advocate the principle that Indigenous Peoples (IPs) rights are human rights and the respect of which will realize both the self-determined development and security of IPs as human being and ancestral domains/communities as a whole.`
        },
        'legal': {
            title: "Legal Services",
            content: `
            There are three components of the Legal Services. These are:<br><br>

            1. Indigenous Peoples Legal Assistance (IPLA)<br>
            <ul style="list-style-type: disc; padding-left: 20px;">
                <li>The IPRA mandates the NCIP to protect and promote the interest and well-being of the ICCs/IPs with due regard to their beliefs, customs, traditions and institutions. The same law likewise considers the NCIP as the primary government agency through which ICCs/IPs can seek government assistance and as the medium through which such assistance may be extended.</li>
                <li>With the aforementioned mandates, the NCIP, through its Central, Regional, and Provincial lawyers and legal officers, provides legal assistance and services to the ICCs/IPs by representing them before the regular courts and quasi-judicial bodies, conducting investigations on the basis of complaints filed by the ICCs/IPs, preparing pleadings and other legal documents for said clients or simply rendering legal opinion or advisory.</li>
            </ul><br>

            2. Paralegal Training<br>
            <ul style="list-style-type: disc; padding-left: 20px;">
                <li>The National Commission on Indigenous Peoples (NCIP), through its lawyers and legal officers, renders legal assistance to IP clients as part of the general mandate of the Office to recognize, protect and promote the rights of our ICCs/IPs.</li>
                <li>The paralegal training program seeks to educate and inform ICCs/IPs of their rights, the various applicable remedies they have in case these rights are violated and abused, and the different jurisdictions (courts, prosecution offices, quasi-judicial and administrative bodies, and other government agencies) where they may lodge their complaints for such violations.</li>
                <li>IPs from communities need to be trained in order that they may assist other members of their communities in reporting violations of their rights and gathering pieces of evidence, as well as assist NCIP or their private lawyers on legal concerns brought to them for proper action. Adequate and appropriate training is a must for paralegals in order that they may be able to assist other members of the community in accessing justice. Paralegals must not only be aware of the basic provisions of laws relating to IPs but must also be aware of their roles and limitations in assisting people. They must also be trained on keeping records and be provided exposure to various government agencies so that they are able to help others approach these agencies.</li>
            </ul><br>

            3. Documentation of Customary Laws<br>
            <ul style="list-style-type: disc; padding-left: 20px;">
                <li>The Indigenous Peoples Rights Act of 1997 or Republic Act No. 8371 provides for the primacy of customary laws and practices in resolving disputes. Section 65 of IPRA provides that, when disputes involve ICCs/IPs, customary laws and practices shall be used to resolve the dispute.</li>
                <li>Furthermore, Section 63 of the same law states that customary laws, traditions and practices of the ICCs/IPs of the land where the conflict arises shall be applied first with respect to property rights, claims and ownership, hereditary succession and settlement of land disputes.</li>
                <li>Several administrative orders and guidelines of the NCIP also emphasize the importance and primacy of customary laws in resolving disputes involving ICCs/IPs.</li>
                <li>It is essential to undertake the documentation of customary laws not only for the sake of documentation and preservation, but in order to have a full understanding of these laws and practices, and to have ready and available resources in resolving disputes brought before the NCIP. Thus, considering the importance of customary laws, the NCIP is tasked to undertake the documentation of customary laws.</li>
            </ul>
            `
        },
        'adjudication': {
            title: "Adjudication Services",
            content: `The IPRA mandates the NCIP, acting through its Regional Hearing Offices (RHOs) and the Commission En Banc (CEB) and in the exercise of its quasi-judicial powers, to resolve all claims and disputes involving rights of ICCs/IPs, subject to the provisions of the IPRA and its implementing Rules, and other regulations, as well as, pertinent jurisprudence. With the aforementioned mandate, the NCIP, through its RHOs and CEB reviews cases submitted before it for adjudication, as a quasi-judicial tribunal, and after due proceedings and hearings, resolves the issues raised for adjudication.`
        }
    };

    if (services[service]) {
        document.querySelector(".post-title").innerText = services[service].title;
        document.getElementById("service-description").innerHTML = services[service].content;
        document.querySelector('.the-content h1').innerText = services[service].title;
        document.querySelector('.the-content p').innerHTML = services[service].content;
    } else {
        document.querySelector(".post-title").innerText = "Service Not Found";
        document.getElementById("service-description").innerText = "Sorry, the service you are looking for does not exist.";
    }


</script>
  

</body>
<!-- END BODY -->
</html>