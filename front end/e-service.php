<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION["user"])) {
    header("Location: form-content.php?show=login");
    exit();
}

// Include database connection
require_once 'db.php';

$educationLevels = [
    '' => 'Select Education Level',
    'No Formal Education' => 'No Formal Education',
    'Elementary (Incomplete)' => 'Elementary (Incomplete)',
    'Elementary Graduate' => 'Elementary Graduate',
    'High School (Incomplete)' => 'High School (Incomplete)',
    'High School Graduate' => 'High School Graduate',
    'Vocational/Technical Course' => 'Vocational/Technical Course',
    'College (Incomplete)' => 'College (Incomplete)',
    'College Graduate' => 'College Graduate',
    'Post Graduate' => 'Post Graduate',
    'Masteral' => 'Masteral',
    'Doctorate' => 'Doctorate'
];

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        // Begin transaction
        $pdo->beginTransaction();
        
        // 1. First insert into ADMINISTRATOR table
        $adminStmt = $pdo->prepare("
            INSERT INTO ADMINISTRATOR (
                ADMIN_APP_NUMBER, 
                ADMIN_DOF, 
                ADMIN_ACTION_TAKEN_CSC_PO, 
                ADMIN_ACTION_TAKEN_RO, 
                ADMIN_CADT, 
                ADMIN_CONG_DISTRICT, 
                ADMIN_ADD_REMARKS, 
                ADMIN_ASSISTANCE_TYPE
            ) VALUES (
                :app_number, 
                :dof, 
                :action_csc_po, 
                :action_ro, 
                :cadt, 
                :cong_district, 
                :remarks, 
                :assistance_type
            ) RETURNING ADMIN_CODE
        ");
        
        // Set current date for date of filing
        $currentDate = date('Y-m-d');
        
        $adminStmt->bindParam(':app_number', $_POST['app_number']);
        $adminStmt->bindParam(':dof', $currentDate);
        $adminStmt->bindParam(':action_csc_po', $_POST['action_csc_po']);
        $adminStmt->bindParam(':action_ro', $_POST['action_ro']);
        $adminStmt->bindParam(':cadt', $_POST['cadt']);
        $adminStmt->bindParam(':cong_district', $_POST['cong_district']);
        $adminStmt->bindParam(':remarks', $_POST['add_remarks']);
        $adminStmt->bindParam(':assistance_type', $_POST['assistance_type']);
        
        $adminStmt->execute();

        $adminCode = null;
        if ($adminStmt->rowCount() > 0) {
            $result = $adminStmt->fetch(PDO::FETCH_ASSOC);
            $adminCode = $result['admin_code'];
        }
        
        if (!$adminCode) {
            throw new Exception("Failed to get ADMIN_CODE after insertion");
        }
        
        // 2. Insert into APPLICANT table
        $applicantStmt = $pdo->prepare("
            INSERT INTO APPLICANT (
                APP_LAST_NAME, 
                APP_FIRST_NAME, 
                APP_MIDDLE_INITIAL, 
                APP_DATE_OF_BIRTH, 
                APP_PLACE_OF_BIRTH, 
                APP_GENDER, 
                APP_CIVIL_STATUS, 
                APP_ETHNO_GROUP, 
                APP_PHONE_NUMBER, 
                APP_EMAIL, 
                APP_FORM_SIGNED,
                APP_SIGNATURE_DATE,
                ADMIN_CODE
            ) VALUES (
                :last_name, 
                :first_name, 
                :middle_initial, 
                :dob, 
                :pob, 
                :gender, 
                :civil_status, 
                :ethno_group, 
                :phone, 
                :email,
                :form_signed,
                :signature_date,
                :admin_code
            ) RETURNING APP_CODE
        ");
        
        // Process signature verification
        $formSigned = isset($_POST['form_signed']) ? true : false;
        $signatureDate = !empty($_POST['signature_date']) ? $_POST['signature_date'] : null;
        $encoderId = $_SESSION['user_id'] ?? null;
        
        $applicantStmt->bindParam(':last_name', $_POST['last_name']);
        $applicantStmt->bindParam(':first_name', $_POST['first_name']);
        $applicantStmt->bindParam(':middle_initial', $_POST['middle_initial']);
        $applicantStmt->bindParam(':dob', $_POST['dob']);
        $applicantStmt->bindParam(':pob', $_POST['pob']);
        $applicantStmt->bindParam(':gender', $_POST['gender']);
        $applicantStmt->bindParam(':civil_status', $_POST['civil_status']);
        $applicantStmt->bindParam(':ethno_group', $_POST['ethno_group']);
        $applicantStmt->bindParam(':phone', $_POST['phone_number']);
        $applicantStmt->bindParam(':email', $_POST['email']);
        $applicantStmt->bindParam(':form_signed', $formSigned, PDO::PARAM_BOOL);
        $applicantStmt->bindParam(':signature_date', $signatureDate);
        $applicantStmt->bindParam(':admin_code', $adminCode);
        
        $applicantStmt->execute();

        $appCode = null;
        if ($applicantStmt->rowCount() > 0) {
            $result = $applicantStmt->fetch(PDO::FETCH_ASSOC);
            $appCode = $result['app_code'];
        }
        
        if (!$appCode) {
            throw new Exception("Failed to get APP_CODE after insertion");
        }
        
        // Handle signature scan upload if included
        if(isset($_FILES['signature_scan']) && $_FILES['signature_scan']['error'] == 0) {
            $fileName = $_FILES['signature_scan']['name'];
            $fileType = $_FILES['signature_scan']['type'];
            $fileContent = file_get_contents($_FILES['signature_scan']['tmp_name']);
            
            $scanStmt = $pdo->prepare("
                INSERT INTO SIGNATURE_SCANS (
                    SCAN_FILENAME,
                    SCAN_FILETYPE,
                    SCAN_DATA,
                    APP_CODE
                ) VALUES (
                    :filename,
                    :filetype,
                    :filedata,
                    :app_code
                )
            ");
            
            $scanStmt->bindParam(':filename', $fileName);
            $scanStmt->bindParam(':filetype', $fileType);
            $scanStmt->bindParam(':filedata', $fileContent, PDO::PARAM_LOB);
            $scanStmt->bindParam(':app_code', $appCode);
            $scanStmt->execute();
        }
        
        // 3. Insert into ADDRESS table
        $addressStmt = $pdo->prepare("
            INSERT INTO ADDRESS (
                ADDR_MAIL, 
                ADDR_PERMANENT, 
                ADDR_HOUSE_AND_STREET, 
                ADDR_BARANGAY, 
                ADDR_MUNICIPALITY, 
                ADDR_PROVINCE, 
                APP_CODE
            ) VALUES (
                :mailing, 
                :permanent, 
                :house_street, 
                :barangay, 
                :municipality, 
                :province, 
                :app_code
            )
        ");
        
        $addressStmt->bindParam(':mailing', $_POST['addr_mail']);
        $addressStmt->bindParam(':permanent', $_POST['addr_permanent']);
        $addressStmt->bindParam(':house_street', $_POST['addr_house_street']);
        $addressStmt->bindParam(':barangay', $_POST['addr_barangay']);
        $addressStmt->bindParam(':municipality', $_POST['addr_municipality']);
        $addressStmt->bindParam(':province', $_POST['addr_province']);
        $addressStmt->bindParam(':app_code', $appCode);
        
        $addressStmt->execute();
        
        // 4. Insert educational attainment records
        $educationCategories = ['Elementary', 'High School', 'Vocational', 'College/Undergraduate', 'Post-Graduate', 'Masteral', 'Doctorate'];
        
        foreach ($educationCategories as $index => $category) {
            // Skip if school name is empty (means no education at this level)
            if (empty($_POST['school_name'][$index])) {
                continue;
            }
            
            // Get category ID
            $categoryId = $index + 1; 
            
            // Insert education attainment
            $eduStmt = $pdo->prepare("
                INSERT INTO EDUCATION_ATTAINMENT (
                    EDUC_ID, 
                    APP_CODE
                ) VALUES (
                    :category_id, 
                    :app_code
                ) RETURNING EDUA_CODE
            ");
            
            $eduStmt->bindParam(':category_id', $categoryId);
            $eduStmt->bindParam(':app_code', $appCode);
            $eduStmt->execute();
            
            $eduCode = null;

            if ($eduStmt->rowCount() > 0) {
                $result = $eduStmt->fetch(PDO::FETCH_ASSOC);
                $eduCode = $result['edua_code'];
            }
            
            if (!$eduCode) {
                throw new Exception("Failed to get EDUA_CODE after insertion for category: " . $category);
            }
            
            
            // Insert school details
            $schoolStmt = $pdo->prepare("
                INSERT INTO SCHOOL (
                    SCH_NAME, 
                    SCH_ADDRESS, 
                    SCH_YEAR_GRAD, 
                    SCH_GRADE_AVG, 
                    SCH_RANK, 
                    EDUC_CODE
                ) VALUES (
                    :name, 
                    :address, 
                    :year_grad, 
                    :grade_avg, 
                    :rank, 
                    :educ_code
                )
            ");
            
            $schoolStmt->bindParam(':name', $_POST['school_name'][$index]);
            $schoolAddress = $_POST['school_address'][$index] ?? '';
            $schoolStmt->bindParam(':address', $schoolAddress);
            $yearGraduated = !empty($_POST['year_graduated'][$index]) ? (int)$_POST['year_graduated'][$index] : null;
            $schoolStmt->bindParam(':year_grad', $yearGraduated);
            $gradeAverage = !empty($_POST['grade_average'][$index]) ? (float)$_POST['grade_average'][$index] : null;
            $schoolStmt->bindParam(':grade_avg', $gradeAverage);
            $schoolStmt->bindParam(':rank', $_POST['rank'][$index]);
            $schoolStmt->bindParam(':educ_code', $eduCode);
            
            $schoolStmt->execute();
        }
        
        // 5. Insert family background
        $familyRelations = ['Father', 'Mother', 'Guardian'];
        
        foreach ($familyRelations as $relation) {
            // Skip if name is empty
            if (empty($_POST['fam_name_' . strtolower($relation)])) {
                continue;
            }
            
            $isAlive = isset($_POST['fam_alive_' . strtolower($relation)]) && $_POST['fam_alive_' . strtolower($relation)] == 1 ? true : false;
            
            $famStmt = $pdo->prepare("
                INSERT INTO FAMILY_BACKGROUND (
                    FAM_RELATION, 
                    FAM_IS_ALIVE, 
                    FAM_NAME, 
                    FAM_ADDR, 
                    FAM_OCCUP, 
                    FAM_OFFICE_ADDR, 
                    FAM_EDUC_ATTAIN, 
                    FAM_ETHNO_GROUP, 
                    FAM_ANNUAL_INCOME, 
                    FAM_YEAR_ITR, 
                    APP_CODE
                ) VALUES (
                    :relation, 
                    :is_alive, 
                    :name, 
                    :addr, 
                    :occup, 
                    :office_addr, 
                    :educ_attain, 
                    :ethno_group, 
                    :annual_income, 
                    :year_itr, 
                    :app_code
                )
            ");
            
            $relationLower = strtolower($relation);
            $famStmt->bindParam(':relation', $relation);
            $famStmt->bindParam(':is_alive', $isAlive, PDO::PARAM_BOOL);
            $famStmt->bindParam(':name', $_POST['fam_name_' . $relationLower]);
            $famStmt->bindParam(':addr', $_POST['fam_addr_' . $relationLower]);
            $famStmt->bindParam(':occup', $_POST['fam_occup_' . $relationLower]);
            $famStmt->bindParam(':office_addr', $_POST['fam_office_addr_' . $relationLower]);
            $famStmt->bindParam(':educ_attain', $_POST['fam_educ_attain_' . $relationLower]);
            $famStmt->bindParam(':ethno_group', $_POST['fam_ethno_group_' . $relationLower]);
            $annualIncome = !empty($_POST['fam_annual_income_' . $relationLower]) ? 
                (float)str_replace(',', '', $_POST['fam_annual_income_' . $relationLower]) : 0;
            $famStmt->bindParam(':annual_income', $annualIncome);
            $yearItr = !empty($_POST['fam_year_itr_' . $relationLower]) ? (int)$_POST['fam_year_itr_' . $relationLower] : date('Y');
            $famStmt->bindParam(':year_itr', $yearItr);
            $famStmt->bindParam(':app_code', $appCode);
            
            $famStmt->execute();
        }
        
        // 6. Insert siblings with scholarships
        $siblingCount = intval($_POST['sibling_count'] ?? 0);
        
        for ($i = 0; $i < $siblingCount; $i++) {
            // Skip if sibling name is empty
            if (empty($_POST['sib_first_name'][$i]) || empty($_POST['sib_last_name'][$i])) {
                continue;
            }
            
            $sibStmt = $pdo->prepare("
                INSERT INTO SIBLING (
                    SIB_LAST_NAME, 
                    SIB_FIRST_NAME, 
                    SIB_MIDDLE_INITIAL, 
                    APP_CODE
                ) VALUES (
                    :last_name, 
                    :first_name, 
                    :middle_initial, 
                    :app_code
                ) RETURNING SIB_CODE
            ");
            
            $sibStmt->bindParam(':last_name', $_POST['sib_last_name'][$i]);
            $sibStmt->bindParam(':first_name', $_POST['sib_first_name'][$i]);
            $sibStmt->bindParam(':middle_initial', $_POST['sib_middle_initial'][$i]);
            $sibStmt->bindParam(':app_code', $appCode);
            
            $sibStmt->execute();
            
            $sibCode = null;

            if ($sibStmt->rowCount() > 0) {
                $result = $sibStmt->fetch(PDO::FETCH_ASSOC);
                $sibCode = $result['sib_code'];
            }
            
            if (!$sibCode) {
                throw new Exception("Failed to get SIB_CODE after insertion for sibling: " . $_POST['sib_first_name'][$i]);
            }
            
            // Insert scholarship details if applicable
            if (!empty($_POST['scholar_name'][$i])) {
                $scholarStmt = $pdo->prepare("
                    INSERT INTO SCHOLARSHIP (
                        SCHOLAR_NAME, 
                        SCHOLAR_COURSE, 
                        SCHOLAR_YEAR, 
                        SIB_CODE
                    ) VALUES (
                        :name, 
                        :course, 
                        :year, 
                        :sib_code
                    )
                ");
                
                $scholarStmt->bindParam(':name', $_POST['scholar_name'][$i]);
                $scholarStmt->bindParam(':course', $_POST['scholar_course'][$i]);
        $scholarYear = !empty($_POST['scholar_year'][$i]) ? $_POST['scholar_year'][$i] : null;
                $scholarStmt->bindParam(':year', $scholarYear);
                $scholarStmt->bindParam(':sib_code', $sibCode);
                
                $scholarStmt->execute();
            }
        }
        
        // 7. Insert school preferences
        if (!empty($_POST['pref_name']) && !empty($_POST['pref_addr']) && !empty($_POST['pref_degree'])) {
            $prefStmt = $pdo->prepare("
                INSERT INTO SCHOOL_PREFERENCE (
                    PREF_NAME, 
                    PREF_ADDR, 
                    PREF_DEGREE, 
                    PREF_REMARKS, 
                    APP_CODE
                ) VALUES (
                    :name, 
                    :addr, 
                    :degree, 
                    :remarks, 
                    :app_code
                )
            ");
            
            $prefStmt->bindParam(':name', $_POST['pref_name']);
            $prefStmt->bindParam(':addr', $_POST['pref_addr']);
            $prefStmt->bindParam(':degree', $_POST['pref_degree']);
            $prefStmt->bindParam(':remarks', $_POST['pref_remarks']);
            $prefStmt->bindParam(':app_code', $appCode);
            
            $prefStmt->execute();
        }
        
        // Commit the transaction
        $pdo->commit();
        
        // Set success message
        $_SESSION['scholarship_success'] = "Your scholarship application has been submitted successfully!";
        
        // Redirect to avoid form resubmission
        header("Location: e-service.php?success=1");
        exit();
        
    } catch (PDOException $e) {
        // Rollback the transaction if something failed
        $pdo->rollBack();
        $_SESSION['scholarship_error'] = "Error: " . $e->getMessage();
        header("Location: e-service.php?error=1");
        exit();
    }
}

// Fetch education categories from database
try {
    $stmt = $pdo->query("SELECT * FROM EDUCATION_CATEGORY ORDER BY EDUC_ID");
    $educationCategories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // If unable to fetch from database, use hardcoded values
    $educationCategories = [
        ['EDUC_ID' => 1, 'EDUC_NAME' => 'Elementary'],
        ['EDUC_ID' => 2, 'EDUC_NAME' => 'High School'],
        ['EDUC_ID' => 3, 'EDUC_NAME' => 'Vocational'],
        ['EDUC_ID' => 4, 'EDUC_NAME' => 'College/Undergraduate'],
        ['EDUC_ID' => 5, 'EDUC_NAME' => 'Post-Graduate'],
        ['EDUC_ID' => 6, 'EDUC_NAME' => 'Masteral'],
        ['EDUC_ID' => 7, 'EDUC_NAME' => 'Doctorate']
    ];
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
        body { 
            background-color: #ffffff; 
            background-image: url('images/bg-pattern.png'); 
            background-repeat: repeat; 
            background-position: top left; 
            background-attachment: scroll; 
        }
        

        #gender, 
        #civil_status, 
        #fam_educ_attain_father, 
        #fam_educ_attain_mother, 
        #fam_educ_attain_guardian {
            width: 100%;
            max-width: none;
        }
        .scholarship-form {
            max-width: 1000px;
            margin: 20px auto;
            padding: 20px;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

 
        .scholarship-form header {
            text-align: center;
            margin-bottom: 20px;
        }
        
        .scholarship-form .header-logo {
            text-align: center;
            margin-bottom: 20px;
        }
        
        .scholarship-form .header-logo img {
            height: 80px;
            margin: 0 10px;
        }
        
        .scholarship-form h1 {
            font-size: 24px;
            margin-bottom: 5px;
            text-align: center;
        }
        
        .scholarship-form h2 {
            font-size: 18px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .scholarship-form .form-section {
            margin-bottom: 30px;
            border: 1px solid #ddd;
            padding: 15px;
            border-radius: 5px;
        }
        
        .scholarship-form .form-section h3 {
            margin-top: 0;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .scholarship-form .form-row {
            display: flex;
            flex-wrap: wrap;
            margin: 0 -10px;
        }
        
        .scholarship-form .form-group {
            padding: 0 10px;
            margin-bottom: 15px;
            flex: 1 1 250px;
        }
        
        .scholarship-form label {
            display: block;
            font-weight: bold;
        }
        
        .scholarship-form input[type="text"],
        .scholarship-form input[type="email"],
        .scholarship-form input[type="date"],
        .scholarship-form input[type="number"],
        .scholarship-form select,
        .scholarship-form textarea {
            width: 100%;
            padding: 8px 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
            height: 38px; 
        }

.school-name-address-container {
    display: flex;
    gap: 10px;
}

.school-name-address-container input {
    flex: 1;
}

/* Make year, grade, and rank columns have consistent widths */
.scholarship-form table th:nth-child(3), /* Year Graduated column */
.scholarship-form table th:nth-child(4), /* Grade Average column */
.scholarship-form table th:nth-child(5) { /* Rank column */
    width: 120px;
}

.scholarship-form table td:nth-child(3) input, /* Year input field */
.scholarship-form table td:nth-child(4) input, /* Grade input field */
.scholarship-form table td:nth-child(5) input { /* Rank input field */
    width: 100%;
}

/* Ensure the name/address column is wider */
.scholarship-form table th:nth-child(2) { /* Name/Address column */
    width: auto;
}

        .scholarship-form .checkbox-group {
            display: flex;
            gap: 15px;
        }
        
        .scholarship-form .checkbox-item {
            display: flex;
            align-items: center;
        }
        
        .scholarship-form .checkbox-item input {
            margin-right: 5px;
        }
        
        .scholarship-form table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        
        .scholarship-form th, 
        .scholarship-form td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        
        .scholarship-form th {
            background-color: #f2f2f2;
        }
        
        .scholarship-form .add-row-btn {
            margin-bottom: 15px;
            background-color: #152f49;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 4px;
            cursor: pointer;
        }
        
        .scholarship-form .certification {
            font-size: 14px;
            line-height: 1.5;
            margin-bottom: 20px;
        }
        
        .scholarship-form .btn-submit {
            display: block;
            width: 200px;
            margin: 0 auto;
            padding: 10px;
            background-color: #152f49;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }
        
        .scholarship-form .btn-submit:hover {
            background-color: #0e1e30;
        }
        
        .success-message {
            background-color: #d4edda;
            color: #155724;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
        
        .error-message {
            background-color: #f8d7da;
            color: #721c24;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }

        .scholarship-form select {
            appearance: auto;
            -webkit-appearance: menulist;
            -moz-appearance: menulist;
            background-color: #fff;
        }

        .scholarship-form select:focus {
            border-color: #76a9e0;
            outline: none;
            box-shadow: 0 0 5px rgba(82, 168, 236, 0.5);
        }

        .scholarship-form select option {
            background-color: #fff;
            color: #333;
        }

        .scholarship-form select option:hover,
        .scholarship-form select option:focus,
        .scholarship-form select option:active,
        .scholarship-form select option:checked {
            background-color: #f0f0f0;
            color: #333;
        }
                
        @media (max-width: 768px) {
            .scholarship-form .form-group {
                flex: 1 1 100%;
            }
        }
    </style>

    <script type='text/javascript' src='js/jquery/jquery.js'></script>
</head>
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
                            <a href="index.php">HOME</a>
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
                                <a href="https://www.facebook.com/CebuPO" target="_blank" class="socials-default-small default facebook">facebook</a>
                            </div>

                            <div class="socials-default-small instagram-small default">
                                <a href="https://www.instagram.com/ncipcebupo/" target="_blank" class="socials-default-small default instagram">instagram</a>
                            </div>

                            <div class="socials-default-small google-small default">
                                <a href="mailto:region67.cebupo@ncip.gov.ph" target="_blank" class="socials-default-small default google">Gmail</a>
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
                
                    <!-- BREADCRUMB -->          
                    <div class="breadcrumbs">
                        <span class="before-text">You are here:</span>
                        <p id="yit-breadcrumb" itemprop="breadcrumb">
                            <a class="home" href="index.php">Home Page</a> | 
                            <a href="e-service.php">E-Service</a> | 
                            <a class="no-link current" href="#">Scholarship Application</a>
                        </p>        
                    </div>  
                </div>
            </div>
        </div>
    </div>
    <!-- END PAGE META -->
        
    <!-- START PRIMARY -->
    <div class="scholarship-form" style="min-height: auto; padding-bottom: 50px;">
        <div class="header-logo">
            <img src="images/ncip-logo.png" alt="NCIP Logo">
        </div>
        
        <h1>Republic of the Philippines</h1>
        <h1>NATIONAL COMMISSION ON INDIGENOUS PEOPLES</h1>
        <h2>EDUCATIONAL ASSISTANCE/SCHOLARSHIP PROGRAM APPLICATION FORM</h2>
        
        <?php if (isset($_SESSION['scholarship_success'])): ?>
            <div class="success-message">
                <?php 
                    echo $_SESSION['scholarship_success']; 
                    unset($_SESSION['scholarship_success']);
                ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['scholarship_error'])): ?>
            <div class="error-message">
                <?php 
                    echo $_SESSION['scholarship_error']; 
                    unset($_SESSION['scholarship_error']);
                ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" enctype="multipart/form-data">
            
            <!-- ADMINISTRATOR Section (To be filled by EAP Focal Person) -->
            <div class="form-section">
                <h3>To be filled by EAP Focal Person (affix your initial)</h3>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="app_number">Application no:</label>
                        <input type="text" id="app_number" name="app_number" required autocomplete="off">
                    </div>
                    
                    <div class="form-group">
                        <label for="action_csc_po">Action Taken by CSC/PO:</label>
                        <input type="text" id="action_csc_po" name="action_csc_po" autocomplete="off">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="action_ro">Action Taken by RO:</label>
                        <input type="text" id="action_ro" name="action_ro" autocomplete="off">
                    </div>
                    
                    <div class="form-group">
                        <label for="cadt">CADT of Applicant:</label>
                        <input type="text" id="cadt" name="cadt" autocomplete="off">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="cong_district">Congressional District:</label>
                        <input type="text" id="cong_district" name="cong_district" autocomplete="off">
                    </div>
                    
                    <div class="form-group">
                        <label for="add_remarks">Additional Remarks:</label>
                        <textarea id="add_remarks" name="add_remarks" rows="2"></textarea>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Assistance applied for:</label>
                        <div class="checkbox-group">
                            <div class="checkbox-item">
                                <input type="radio" id="regular" name="assistance_type" value="Regular" required >
                                <label for="regular">Regular</label>
                            </div>
                            <div class="checkbox-item">
                                <input type="radio" id="merit-based" name="assistance_type" value="Merit-Based">
                                <label for="merit-based">Merit-Based</label>
                            </div>
                            <div class="checkbox-item">
                                <input type="radio" id="pdaf" name="assistance_type" value="PDAF">
                                <label for="pdaf">PDAF</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- APPLICANT Basic Information Section -->
            <div class="form-section">
                <h3>Basic Information</h3>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="last_name">Last Name:</label>
                        <input type="text" id="last_name" name="last_name" required autocomplete="off">
                    </div>
                    
                    <div class="form-group">
                        <label for="first_name">First Name:</label>
                        <input type="text" id="first_name" name="first_name" required autocomplete="off">
                    </div>
                    
                    <div class="form-group">
                        <label for="middle_initial">Middle Initial:</label>
                        <input type="text" id="middle_initial" name="middle_initial" maxlength="1" autocomplete="off">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="dob">Date of Birth:</label>
                        <input type="date" id="dob" name="dob" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="pob">Place of Birth:</label>
                        <input type="text" id="pob" name="pob" required autocomplete="off">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="gender">Gender:</label>
                        <select id="gender" name="gender" required>
                            <option value="">Select Gender</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="civil_status">Civil Status:</label>
                        <select id="civil_status" name="civil_status">
                            <option value="">Select Status</option>
                            <option value="Single">Single</option>
                            <option value="Married">Married</option>
                            <option value="Divorced">Divorced</option>
                            <option value="Widowed">Widowed</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="ethno_group">Ethnolinguistic Group:</label>
                        <input type="text" id="ethno_group" name="ethno_group" required autocomplete="off">
                    </div>
                    
                    <div class="form-group">
                        <label for="phone_number">Contact Number:</label>
                        <input type="text" id="phone_number" name="phone_number" autocomplete="off">
                    </div>
                    
                    <div class="form-group">
                        <label for="email">E-mail Address:</label>
                        <input type="email" id="email" name="email" autocomplete="off">
                    </div>
                </div>
            </div>
            
            <!-- ADDRESS Section -->
            <div class="form-section">
                <h3>Address</h3>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="addr_mail">Mailing:</label>
                        <input type="text" id="addr_mail" name="addr_mail" autocomplete="off">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="addr_permanent">Permanent:</label>
                        <input type="text" id="addr_permanent" name="addr_permanent" required autocomplete="off">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="addr_house_street">House No. and Street/Sitio:</label>
                        <input type="text" id="addr_house_street" name="addr_house_street" required autocomplete="off">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="addr_barangay">Barangay:</label>
                        <input type="text" id="addr_barangay" name="addr_barangay" required autocomplete="off">
                    </div>
                    
                    <div class="form-group">
                        <label for="addr_municipality">Municipality:</label>
                        <input type="text" id="addr_municipality" name="addr_municipality" required autocomplete="off">
                    </div>
                    
                    <div class="form-group">
                        <label for="addr_province">Province:</label>
                        <input type="text" id="addr_province" name="addr_province" required autocomplete="off">
                    </div>
                </div>
            </div>
            
<!-- EDUCATIONAL ATTAINMENT Section -->
<div class="form-section">
    <h3>Educational Attainment</h3>
    
    <table>
        <thead>
            <tr>
                <th>Category</th>
                <th>School Information</th>
                <th>Year Graduated</th>
                <th>Grade Average</th>
                <th>Rank</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($educationCategories as $index => $category): ?>
        <tr>
            <td><?php 
            if (isset($category['EDUC_NAME'])) {
                echo $category['EDUC_NAME'];
            } elseif (isset($category['educ_name'])) {
                echo $category['educ_name']; 
            } else {
                echo "Category: " . print_r($category, true);
            }
            ?></td>
            <td>
                <div class="school-name-address-container">
                    <input type="text" name="school_name[<?php echo $index; ?>]" placeholder="School Name" autocomplete="off">
                    <input type="text" name="school_address[<?php echo $index; ?>]" placeholder="School Address" autocomplete="off">
                </div>
            </td>
            <td>
                <input type="number" name="year_graduated[<?php echo $index; ?>]" min="1900" max="<?php echo date('Y'); ?>" placeholder="Year" autocomplete="off">
            </td>
            <td>
                <input type="number" name="grade_average[<?php echo $index; ?>]" step="0.01" min="0" max="100" placeholder="Average" autocomplete="off">
            </td>
            <td>
                <input type="text" name="rank[<?php echo $index; ?>]" placeholder="Rank" autocomplete="off">
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
            
            <!-- FAMILY BACKGROUND Section -->
            <div class="form-section">
                <h3>Family Background</h3>
                
                <div class="form-row">
                    <div class="form-group" style="flex: 1;">
                        <h4>Father</h4>
                        <div class="checkbox-group">
                            <div class="checkbox-item">
                                <input type="radio" id="father_alive" name="fam_alive_father" value="1" checked>
                                <label for="father_alive">Living</label>
                            </div>
                            <div class="checkbox-item">
                                <input type="radio" id="father_deceased" name="fam_alive_father" value="0">
                                <label for="father_deceased">Deceased</label>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="fam_name_father">Name:</label>
                            <input type="text" id="fam_name_father" name="fam_name_father" autocomplete="off"> 
                        </div>
                        
                        <div class="form-group">
                            <label for="fam_addr_father">Address:</label>
                            <input type="text" id="fam_addr_father" name="fam_addr_father" autocomplete="off">
                        </div>
                        
                        <div class="form-group">
                            <label for="fam_occup_father">Occupation:</label>
                            <input type="text" id="fam_occup_father" name="fam_occup_father" autocomplete="off">
                        </div>
                        
                        <div class="form-group">
                            <label for="fam_office_addr_father">Office Address:</label>
                            <input type="text" id="fam_office_addr_father" name="fam_office_addr_father" autocomplete="off">
                        </div>
                        
                        <div class="form-group">
                            <label for="fam_educ_attain_father">Educational Attainment:</label>
                            <select id="fam_educ_attain_father" name="fam_educ_attain_father">
                                <?php foreach($educationLevels as $value => $label): ?>
                                    <option value="<?php echo $value; ?>"><?php echo $label; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                                                
                        <div class="form-group">
                            <label for="fam_ethno_group_father">Ethnolinguistic Group:</label>
                            <input type="text" id="fam_ethno_group_father" name="fam_ethno_group_father" autocomplete="off">
                        </div>
                        
                        <div class="form-group">
                            <label for="fam_annual_income_father">Annual Income:</label>
                            <input type="text" id="fam_annual_income_father" name="fam_annual_income_father" step="0.01" min="0" autocomplete="off">
                        </div>
                        
                        <div class="form-group">
                            <label for="fam_year_itr_father">Year of ITR Attached:</label>
                            <input type="number" id="fam_year_itr_father" name="fam_year_itr_father" min="1900" max="<?php echo date('Y'); ?>" autocomplete="off">
                        </div>
                    </div>
                    
                    <div class="form-group" style="flex: 1;">
                        <h4>Mother</h4>
                        <div class="checkbox-group">
                            <div class="checkbox-item">
                                <input type="radio" id="mother_alive" name="fam_alive_mother" value="1" checked>
                                <label for="mother_alive">Living</label>
                            </div>
                            <div class="checkbox-item">
                                <input type="radio" id="mother_deceased" name="fam_alive_mother" value="0">
                                <label for="mother_deceased">Deceased</label>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="fam_name_mother">Name:</label>
                            <input type="text" id="fam_name_mother" name="fam_name_mother" autocomplete="off">
                        </div>
                        
                        <div class="form-group">
                            <label for="fam_addr_mother">Address:</label>
                            <input type="text" id="fam_addr_mother" name="fam_addr_mother" autocomplete="off">
                        </div>
                        
                        <div class="form-group">
                            <label for="fam_occup_mother">Occupation:</label>
                            <input type="text" id="fam_occup_mother" name="fam_occup_mother" autocomplete="off">
                        </div>
                        
                        <div class="form-group">
                            <label for="fam_office_addr_mother">Office Address:</label>
                            <input type="text" id="fam_office_addr_mother" name="fam_office_addr_mother" autocomplete="off">
                        </div>
                        
                        <div class="form-group">
                            <label for="fam_educ_attain_mother">Educational Attainment:</label>
                            <select id="fam_educ_attain_mother" name="fam_educ_attain_mother">
                                <?php foreach($educationLevels as $value => $label): ?>
                                    <option value="<?php echo $value; ?>"><?php echo $label; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                                                
                        <div class="form-group">
                            <label for="fam_ethno_group_mother">Ethnolinguistic Group:</label>
                            <input type="text" id="fam_ethno_group_mother" name="fam_ethno_group_mother" autocomplete="off">
                        </div>
                        
                        <div class="form-group">
                            <label for="fam_annual_income_mother">Annual Income:</label>
                            <input type="text" id="fam_annual_income_mother" name="fam_annual_income_mother" step="0.01" min="0" autocomplete="off">
                        </div>
                        
                        <div class="form-group">
                            <label for="fam_year_itr_mother">Year of ITR Attached:</label>
                            <input type="number" id="fam_year_itr_mother" name="fam_year_itr_mother" min="1900" max="<?php echo date('Y'); ?>" autocomplete="off">
                        </div>
                    </div>
                    
                    <div class="form-group" style="flex: 1;">
                        <h4>Guardian</h4>
                        <div class="checkbox-group">
                            <div class="checkbox-item">
                                <input type="radio" id="guardian_alive" name="fam_alive_guardian" value="1" checked>
                                <label for="guardian_alive">Living</label>
                            </div>
                            <div class="checkbox-item">
                                <input type="radio" id="guardian_deceased" name="fam_alive_guardian" value="0">
                                <label for="guardian_deceased">Deceased</label>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="fam_name_guardian">Name:</label>
                            <input type="text" id="fam_name_guardian" name="fam_name_guardian" autocomplete="off">
                        </div>
                        
                        <div class="form-group">
                            <label for="fam_addr_guardian">Address:</label>
                            <input type="text" id="fam_addr_guardian" name="fam_addr_guardian" autocomplete="off">
                        </div>
                        
                        <div class="form-group">
                            <label for="fam_occup_guardian">Occupation:</label>
                            <input type="text" id="fam_occup_guardian" name="fam_occup_guardian" autocomplete="off">
                        </div>
                        
                        <div class="form-group">
                            <label for="fam_office_addr_guardian">Office Address:</label>
                            <input type="text" id="fam_office_addr_guardian" name="fam_office_addr_guardian" autocomplete="off">
                        </div>
                        
                        <div class="form-group">
                            <label for="fam_educ_attain_guardian">Educational Attainment:</label>
                            <select id="fam_educ_attain_guardian" name="fam_educ_attain_guardian">
                                <?php foreach($educationLevels as $value => $label): ?>
                                    <option value="<?php echo $value; ?>"><?php echo $label; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                                                
                        <div class="form-group">
                            <label for="fam_ethno_group_guardian">Ethnolinguistic Group:</label>
                            <input type="text" id="fam_ethno_group_guardian" name="fam_ethno_group_guardian" autocomplete="off">
                        </div>
                        
                        <div class="form-group">
                            <label for="fam_annual_income_guardian">Annual Income:</label>
                            <input type="text" id="fam_annual_income_guardian" name="fam_annual_income_guardian" step="0.01" min="0" autocomplete="off">
                        </div>
                        
                        <div class="form-group">
                            <label for="fam_year_itr_guardian">Year of ITR Attached:</label>
                            <input type="number" id="fam_year_itr_guardian" name="fam_year_itr_guardian" min="1900" max="<?php echo date('Y'); ?>" autocomplete="off">
                        </div>
                    </div>
                </div>
            </div>
            
        <!-- Siblings Enjoying Scholarship section -->
        <div class="form-section">
            <h3>Siblings Enjoying Scholarship</h3>
            
            <div id="siblings-container">
                <table>
                    <thead>
                        <tr>
                            <th style="width: 45%;">Name</th>
                            <th style="width: 35%;">Scholarship</th>
                            <th style="width: 20%;">Course and Year</th>
                        </tr>
                    </thead>
                    <tbody id="siblings-table-body">
                        <tr class="sibling-row">
                            <td>
                                <div class="form-row" style="margin:0">
                                    <input type="text" name="sib_last_name[0]" placeholder="Last Name" style="width:40%; margin-right:5px;">
                                    <input type="text" name="sib_first_name[0]" placeholder="First Name" style="width:40%; margin-right:5px;">
                                    <input type="text" name="sib_middle_initial[0]" placeholder="M.I." style="width:40px; min-width:40px; max-width:40px; text-align:center;" maxlength="1">
                                </div>
                            </td>
                            <td>
                                <input type="text" name="scholar_name[0]" placeholder="Scholarship Name" style="width:95%">
                            </td>
                            <td>
                                <div class="form-row" style="margin:0">
                                    <input type="text" name="scholar_course[0]" placeholder="Course" style="width:60%; margin-right:5px;">
                                    <select name="scholar_year[0]" style="width:80px; padding: 8px 5px;">
                                        <option value="">Year</option>
                                        <option value="1st Year">1st Year</option>
                                        <option value="2nd Year">2nd Year</option>
                                        <option value="3rd Year">3rd Year</option>
                                        <option value="4th Year">4th Year</option>
                                        <option value="5th Year">5th Year</option>
                                    </select>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <input type="hidden" id="sibling_count" name="sibling_count" value="1">
            <button type="button" class="add-row-btn" onclick="addSiblingRow()">Add Sibling</button>
        </div>
            
            <!-- SCHOOL PREFERENCES Section -->
            <div class="form-section">
                <h3>School Preference</h3>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="pref_name">School intended to enroll (First Preference):</label>
                        <input type="text" id="pref_name" name="pref_name" autocomplete="off">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="pref_addr">Address:</label>
                        <input type="text" id="pref_addr" name="pref_addr" autocomplete="off"> 
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="pref_degree">Degree Program/Course:</label>
                        <input type="text" id="pref_degree" name="pref_degree" autocomplete="off">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="pref_remarks">What are your plans after graduation?</label>
                        <textarea id="pref_remarks" name="pref_remarks" rows="3"></textarea>
                    </div>
                </div>
            </div>
            
            <!-- CERTIFICATION Section -->
            <div class="form-section">
                <h3>Certification</h3>
                
                <div class="certification">
                    <p>This is to certify that I am not availing of any other government scholarship and all data supplied in this application are true and correct. Any material misinterpretation or alteration made hereof shall be sufficient ground for this application to be denied. If chosen, I will abide by the rules and regulations set forth by the National Commission on Indigenous Peoples.</p>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <div class="checkbox-group">
                            <div class="checkbox-item">
                                <input type="checkbox" id="form_signed" name="form_signed" value="1" required>
                                <label for="form_signed">Paper form has been signed by applicant</label>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="signature_date">Date signed by applicant:</label>
                        <input type="date" id="signature_date" name="signature_date" max="<?php echo date('Y-m-d'); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="signature_scan">Upload scan of e-signature:</label>
                        <input type="file" id="signature_scan" name="signature_scan" accept="image/jpeg,image/png,application/pdf">
                    </div>
                </div>
            </div>
                        
            <button type="submit" class="btn-submit">Submit Application</button>
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

<script>
    let siblingCount = 1;
    
    function addSiblingRow() {
    siblingCount++;
    document.getElementById('sibling_count').value = siblingCount;
    
    const tbody = document.getElementById('siblings-table-body');
    const newRow = document.createElement('tr');
    newRow.className = 'sibling-row';
    
    const currentYear = new Date().getFullYear();
    
    newRow.innerHTML = `
        <td>
            <div class="form-row" style="margin:0">
                <input type="text" name="sib_last_name[${siblingCount-1}]" placeholder="Last Name" style="width:40%; margin-right:5px;">
                <input type="text" name="sib_first_name[${siblingCount-1}]" placeholder="First Name" style="width:40%; margin-right:5px;">
                <input type="text" name="sib_middle_initial[${siblingCount-1}]" placeholder="M.I." style="width:40px; min-width:40px; max-width:40px; text-align:center;" maxlength="1">
            </div>
        </td>
        <td>
            <input type="text" name="scholar_name[${siblingCount-1}]" placeholder="Scholarship Name" style="width:95%">
        </td>
        <td>
            <div class="form-row" style="margin:0">
                <input type="text" name="scholar_course[${siblingCount-1}]" placeholder="Course" style="width:60%; margin-right:5px;">
                <select name="scholar_year[${siblingCount-1}]" style="width:80px; padding: 8px 5px;">
                    <option value="">Year</option>
                    <option value="1st Year">1st Year</option>
                    <option value="2nd Year">2nd Year</option>
                    <option value="3rd Year">3rd Year</option>
                    <option value="4th Year">4th Year</option>
                    <option value="5th Year">5th Year</option>
                </select>
            </div>
        </td>
    `;
    
    tbody.appendChild(newRow);
    
    // Apply validation to the new middle initial field
    const newMiddleInitial = newRow.querySelector(`input[name="sib_middle_initial[${siblingCount-1}]"]`);
    if (newMiddleInitial) {
        newMiddleInitial.addEventListener('input', function() {
            if (this.value.length > 1) {
                this.value = this.value.slice(0, 1);
            }
        });
    }
    
    // Apply validation to the new year field
    const newYearField = newRow.querySelector(`input[name="scholar_year[${siblingCount-1}]"]`);
    if (newYearField) {
        newYearField.addEventListener('input', function() {
            if (this.value > currentYear) {
                this.value = currentYear;
            }
        });
        
        newYearField.addEventListener('blur', function() {
            if (this.value > currentYear) {
                this.value = currentYear;
            }
        });
        
        newYearField.addEventListener('keydown', function(e) {
            if (e.key === 'ArrowUp' && parseInt(this.value) >= currentYear) {
                this.value = currentYear;
                e.preventDefault();
            }
        });
    }
}

    document.addEventListener('DOMContentLoaded', function() {
    // Format income inputs to show decimals and commas
    const incomeInputs = [
        document.getElementById('fam_annual_income_father'),
        document.getElementById('fam_annual_income_mother'),
        document.getElementById('fam_annual_income_guardian')
    ];
    
        incomeInputs.forEach(input => {
            if(input) {
                // Format on blur
                input.addEventListener('blur', function() {
                    if(this.value) {
                        // Parse as float and format with 2 decimal places
                        const value = parseFloat(this.value.replace(/,/g, ''));
                        if(!isNaN(value)) {
                            // Format with commas and 2 decimal places
                            this.value = new Intl.NumberFormat('en-US', {
                                minimumFractionDigits: 2,
                                maximumFractionDigits: 2
                            }).format(value);
                        }
                    }
                });
                
                // Remove formatting on focus for editing
                input.addEventListener('focus', function() {
                    const value = this.value.replace(/,/g, '');
                    this.value = value;
                });
            }
        });


    // Set max date for date of birth input to today
    const today = new Date();
    const year = today.getFullYear();
    const month = String(today.getMonth() + 1).padStart(2, '0');
    const day = String(today.getDate()).padStart(2, '0');
    const currentDate = `${year}-${month}-${day}`;
    
    // Apply the max date to the date of birth input
    const dobInput = document.getElementById('dob');
    if(dobInput) {
        dobInput.setAttribute('max', currentDate);
    }
});

// This JavaScript will improve the validation for all year fields and fix the M.I. field width
document.addEventListener('DOMContentLoaded', function() {
    // Current year for validation
    const currentYear = new Date().getFullYear();
    
    // 1. Fix validation for all year fields in Educational Attainment section
    const yearGraduatedInputs = document.querySelectorAll('input[name^="year_graduated"]');
    
    yearGraduatedInputs.forEach(input => {
        // Set attributes
        input.setAttribute('max', currentYear);
        input.setAttribute('type', 'number');
        input.setAttribute('min', '1900');
        
        // Add validation on input
        input.addEventListener('input', function() {
            if (parseInt(this.value) > currentYear) {
                this.value = currentYear;
            }
        });
        
        // Add validation on blur
        input.addEventListener('blur', function() {
            if (parseInt(this.value) > currentYear) {
                this.value = currentYear;
            }
        });
        
        // Prevent arrow up from exceeding current year
        input.addEventListener('keydown', function(e) {
            if (e.key === 'ArrowUp' && parseInt(this.value) >= currentYear) {
                this.value = currentYear;
                e.preventDefault();
            }
        });
    });
    
    // 2. Fix validation for all year fields in Siblings Enjoying Scholarship section
    const scholarYearInputs = document.querySelectorAll('input[name^="scholar_year"]');
    
    scholarYearInputs.forEach(input => {
        // Set attributes
        input.setAttribute('max', currentYear);
        input.setAttribute('type', 'number');
        input.setAttribute('min', '1900');
        
        // Add validation on input
        input.addEventListener('input', function() {
            if (parseInt(this.value) > currentYear) {
                this.value = currentYear;
            }
        });
        
        // Add validation on blur
        input.addEventListener('blur', function() {
            if (parseInt(this.value) > currentYear) {
                this.value = currentYear;
            }
        });
        
        // Prevent arrow up from exceeding current year
        input.addEventListener('keydown', function(e) {
            if (e.key === 'ArrowUp' && parseInt(this.value) >= currentYear) {
                this.value = currentYear;
                e.preventDefault();
            }
        });
    });
    
    // 3. Fix width and styling for middle initial inputs in Siblings section
    const middleInitialInputs = document.querySelectorAll('input[name^="sib_middle_initial"]');
    
    middleInitialInputs.forEach(input => {
        // Improve width and styling for better placeholder visibility
        input.style.width = '40px';
        input.style.minWidth = '40px';
        input.setAttribute('placeholder', 'M.I.');
        
        // Ensure maxlength is set
        input.setAttribute('maxlength', '1');
        
        // Add validation to restrict to 1 character
        input.addEventListener('input', function() {
            if (this.value.length > 1) {
                this.value = this.value.slice(0, 1);
            }
        });
    });
    
    // 4. Update the addSiblingRow function to apply these validations to new rows
    // Get the original function
    const originalAddSiblingRow = window.addSiblingRow;
    
    // Replace it with an enhanced version that includes our validations
    window.addSiblingRow = function() {
        // Call the original function first
        originalAddSiblingRow();
        
        // Get the latest index
        const index = siblingCount - 1;
        
        // Apply validations to new middle initial field
        const newMiddleInitial = document.querySelector(`input[name="sib_middle_initial[${index}]"]`);
        if (newMiddleInitial) {
            newMiddleInitial.style.width = '40px';
            newMiddleInitial.style.minWidth = '40px';
            newMiddleInitial.setAttribute('placeholder', 'M.I.');
            newMiddleInitial.setAttribute('maxlength', '1');
            
            newMiddleInitial.addEventListener('input', function() {
                if (this.value.length > 1) {
                    this.value = this.value.slice(0, 1);
                }
            });
        }
        
        // Apply validations to new year field
        const newYearField = document.querySelector(`input[name="scholar_year[${index}]"]`);
        if (newYearField) {
            newYearField.setAttribute('max', currentYear);
            newYearField.setAttribute('type', 'number');
            newYearField.setAttribute('min', '1900');
            
            newYearField.addEventListener('input', function() {
                if (parseInt(this.value) > currentYear) {
                    this.value = currentYear;
                }
            });
            
            newYearField.addEventListener('blur', function() {
                if (parseInt(this.value) > currentYear) {
                    this.value = currentYear;
                }
            });
            
            newYearField.addEventListener('keydown', function(e) {
                if (e.key === 'ArrowUp' && parseInt(this.value) >= currentYear) {
                    this.value = currentYear;
                    e.preventDefault();
                }
            });
        }
    };
});

document.addEventListener('DOMContentLoaded', function() {
    // Current year constant - this will be 2025 based on the system date
    const currentYear = new Date().getFullYear();
    
    // Target specifically the Year of ITR Attached input fields
    const itrYearFields = document.querySelectorAll('input[name^="fam_year_itr_"]');
    
    itrYearFields.forEach(field => {
        // Ensure max attribute is set correctly
        field.setAttribute('max', currentYear);
        field.setAttribute('type', 'number');
        field.setAttribute('min', '1900');
        
        // Stronger input validation - prevent typing values beyond current year
        field.addEventListener('input', function() {
            if (this.value && parseInt(this.value) > currentYear) {
                this.value = currentYear;
            }
        });
        
        // Additional validation when field loses focus
        field.addEventListener('blur', function() {
            if (this.value && parseInt(this.value) > currentYear) {
                this.value = currentYear;
            }
        });
        
        // Prevent up arrow key from exceeding current year
        field.addEventListener('keydown', function(e) {
            if (e.key === 'ArrowUp' && parseInt(this.value) >= currentYear) {
                e.preventDefault();
                this.value = currentYear;
            }
        });
        
        // Intercept mouse wheel events for spinner (additional browser compatibility)
        field.addEventListener('wheel', function(e) {
            const value = parseInt(this.value || 0);
            if (value >= currentYear) {
                e.preventDefault();
                this.value = currentYear;
            }
        }, { passive: false });
    });
});


</script>

</body>
</html>