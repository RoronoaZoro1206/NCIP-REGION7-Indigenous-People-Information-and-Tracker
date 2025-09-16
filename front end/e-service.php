<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and has appropriate role (not an admin)
if (!isset($_SESSION["user_id"])) {
    header("Location: form-content.php?show=login");
    exit();
} else if (isset($_SESSION["role"]) && strtoupper($_SESSION["role"]) === 'ADMIN' && !isset($_SESSION['temporary_role'])) {
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
if (isset($_GET['switch_role']) && $_GET['switch_role'] === 'USER' && $_SESSION['role'] === 'ADMIN') {
    $_SESSION['temporary_role'] = true;
    $_SESSION['original_role'] = 'ADMIN';
    $_SESSION['temp_role'] = 'USER';
    // Reload page without the parameter
    header("Location: e-service.php");
    exit();
}

// Include database connection
require_once 'db.php';

// Log user activity
try {
    $log_query = "INSERT INTO USER_LOGS (USER_ID, LOG_TYPE, LOG_DETAILS, LOG_TIMESTAMP) 
                 VALUES (:user_id, 'PAGE_ACCESS', :details, CURRENT_TIMESTAMP)";
    $log_stmt = $pdo->prepare($log_query);
    $details = "User accessed e-service page";
    $log_stmt->bindParam(":user_id", $_SESSION["user_id"]);
    $log_stmt->bindParam(":details", $details);
    $log_stmt->execute();
} catch (PDOException $e) {
    // Just log error but continue execution
    error_log("Failed to log user activity: " . $e->getMessage());
}

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
        date_default_timezone_set('Asia/Manila');
        $currentDate = date('Y-m-d');

        $adminStmt->bindParam(':app_number', $_POST['app_number']);
        $adminStmt->bindParam(':dof', $currentDate);
        
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
    // APP_SIGNATURE_DATE is NOT NULL with default CURRENT_DATE; when empty, use today's date
    $signatureDate = !empty($_POST['signature_date']) ? $_POST['signature_date'] : $currentDate;
        
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
                INSERT INTO SIGNATURE_SCAN (
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
        $educationCategories = [
            1 => 'Elementary',
            2 => 'High School', 
            3 => 'Vocational',
            4 => 'College/Undergraduate',
            5 => 'Post-Graduate',
            6 => 'Masteral',
            7 => 'Doctorate'
        ];

        foreach ($educationCategories as $categoryId => $categoryName) {
            $index = $categoryId - 1; // Convert to 0-based index for form fields
            
            // Only insert education attainment record if school name is provided
            if (!empty($_POST['school_name'][$index])) {
                // Insert education attainment record
                $eduStmt = $pdo->prepare("
                    INSERT INTO education_attainment (
                        educ_id, 
                        app_code
                    ) VALUES (
                        :category_id, 
                        :app_code
                    ) RETURNING edua_code
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
                    throw new Exception("Failed to get edua_code after insertion for category: " . $categoryName);
                }
                
                // Insert school details
                $schoolStmt = $pdo->prepare("
                    INSERT INTO school (
                        sch_name, 
                        sch_address, 
                        sch_year_grad, 
                        sch_grade_avg, 
                        sch_rank, 
                        edua_code
                    ) VALUES (
                        :name, 
                        :address, 
                        :year_grad, 
                        :grade_avg, 
                        :rank, 
                        :edua_code
                    )
                ");
                
                // Handle fields properly
                $schoolName = $_POST['school_name'][$index];
                $schoolAddress = !empty($_POST['school_address'][$index]) ? $_POST['school_address'][$index] : null;
                $yearGraduated = !empty($_POST['year_graduated'][$index]) ? (int)$_POST['year_graduated'][$index] : null;
                $gradeAverage = !empty($_POST['grade_average'][$index]) ? (float)$_POST['grade_average'][$index] : null;
                $rank = !empty($_POST['rank'][$index]) ? $_POST['rank'][$index] : null;
                
                $schoolStmt->bindParam(':name', $schoolName);
                $schoolStmt->bindParam(':address', $schoolAddress);
                $schoolStmt->bindParam(':year_grad', $yearGraduated);
                $schoolStmt->bindParam(':grade_avg', $gradeAverage);
                $schoolStmt->bindParam(':rank', $rank);
                $schoolStmt->bindParam(':edua_code', $eduCode);
                
                $schoolStmt->execute();
            }
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
        
        // 8. Insert initial scholarship status as PENDING
        $statusStmt = $pdo->prepare("
            INSERT INTO SCHOLARSHIP_STATUS (
                APP_CODE, 
                STATUS_CODE, 
                STATUS_DATE, 
                STATUS_IS_ACTIVE,
                STATUS_REMARKS
            ) VALUES (
                :app_code, 
                'PENDING', 
                CURRENT_TIMESTAMP, 
                TRUE,
                'Initial application submission'
            )
        ");
        
        $statusStmt->bindParam(':app_code', $appCode);
        $statusStmt->execute();
        
        // Commit the transaction
        $pdo->commit();
        
        // Log successful submission in USER_LOGS
        $submission_log_query = "INSERT INTO USER_LOGS (USER_ID, LOG_TYPE, LOG_DETAILS, LOG_TIMESTAMP) 
                               VALUES (:user_id, 'FORM_SUBMISSION', :details, CURRENT_TIMESTAMP)";
        $submission_log_stmt = $pdo->prepare($submission_log_query);
        $submission_details = "User submitted scholarship application form. Application #: " . $_POST['app_number'] . ", APP_CODE: " . $appCode;
        $submission_log_stmt->bindParam(":user_id", $_SESSION["user_id"]);
        $submission_log_stmt->bindParam(":details", $submission_details);
        $submission_log_stmt->execute();
        
        // Set success message
        $_SESSION['scholarship_success'] = "Your scholarship application has been submitted successfully! Application ID: " . $appCode;
        
        // Redirect to avoid form resubmission
        header("Location: e-service.php?success=1");
        exit();
        
    } catch (Exception $e) {
        // Rollback the transaction if something failed
        $pdo->rollBack();
        
        // Log the error
        $error_log_query = "INSERT INTO USER_LOGS (USER_ID, LOG_TYPE, LOG_DETAILS, LOG_TIMESTAMP) 
                          VALUES (:user_id, 'ERROR', :details, CURRENT_TIMESTAMP)";
        $error_log_stmt = $pdo->prepare($error_log_query);
        $error_details = "Form submission error: " . $e->getMessage();
        $error_log_stmt->bindParam(":user_id", $_SESSION["user_id"]);
        $error_log_stmt->bindParam(":details", $error_details);
        $error_log_stmt->execute();
        
        $_SESSION['scholarship_error'] = "Error: " . $e->getMessage();
        header("Location: e-service.php?error=1");
        exit();
    }
}

try {
    $educStmt = $pdo->query("SELECT * FROM EDUCATION_CATEGORY ORDER BY EDUC_ID");
    $educationCategories = [];
    while ($row = $educStmt->fetch(PDO::FETCH_ASSOC)) {
       
        $educationCategories[] = [
            'EDUC_ID' => $row['educ_id'] ?? $row['EDUC_ID'],
            'EDUC_NAME' => $row['educ_name'] ?? $row['EDUC_NAME']
        ];
    }
} catch (PDOException $e) {
    // If unable to fetch, use hardcoded fallback
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
<!-- REST OF HTML REMAINS THE SAME -->
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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">

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

        #signature_preview {
            animation: fadeIn 0.3s ease-in;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        #signature_preview_img:hover {
            transform: scale(1.05);
            transition: transform 0.2s ease;
        }

        #remove_signature:hover {
            background-color: #c82333 !important;
        }
                
        @media (max-width: 768px) {
            .scholarship-form .form-group {
                flex: 1 1 100%;
            }

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
<body class="home page no_js responsive stretched">

<!-- START BG SHADOW -->
<div class="bg-shadow">

<!-- START WRAPPER -->
    <div id="wrapper" class="container group">

    <!-- SCROLL TO TOP BUTTON -->
    <?php include 'components/scroll-to-top.php'; ?>

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
                            <a href="heatmap.php">IP LOCATION TRACKER</a>
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
                            <a href="../admin/login.php">ADMIN</a>
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
    
    <table style="width: 100%; table-layout: fixed;">         
        <thead>             
            <tr>                 
                <th style="width: 15%;">Category</th>                 
                <th style="width: 25%;">School Name</th>
                <th style="width: 25%;">School Address</th>                 
                <th style="width: 12%;">Year Graduated</th>                 
                <th style="width: 12%;">Grade Average</th>                 
                <th style="width: 12%;">Rank</th>             
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
                <input type="text" name="school_name[<?php echo $index; ?>]"style="width: 100%;" autocomplete="off">
            </td>
            <td>
                <input type="text" name="school_address[<?php echo $index; ?>]" style="width: 100%;" autocomplete="off">                 
            </td>             
            <td>                 
                <input type="number" name="year_graduated[<?php echo $index; ?>]" min="1900" max="<?php echo date('Y'); ?>" style="width: 100%;" autocomplete="off">             
            </td>             
            <td>                 
                <input type="number" name="grade_average[<?php echo $index; ?>]" min="0" max="100" step="0.01" style="width: 100%;" autocomplete="off">              
            </td>             
            <td>                 
                <input type="text" name="rank[<?php echo $index; ?>]" style="width: 100%;" autocomplete="off">             
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
                                <option value="">Select Education Level</option>
                                <?php foreach($educationCategories as $category): ?>
                                    <option value="<?php echo $category['EDUC_NAME']; ?>"><?php echo $category['EDUC_NAME']; ?></option>
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
                                <option value="">Select Education Level</option>
                                <?php foreach($educationCategories as $category): ?>
                                    <option value="<?php echo $category['EDUC_NAME']; ?>"><?php echo $category['EDUC_NAME']; ?></option>
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
                                <option value="">Select Education Level</option>
                                <?php foreach($educationCategories as $category): ?>
                                    <option value="<?php echo $category['EDUC_NAME']; ?>"><?php echo $category['EDUC_NAME']; ?></option>
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
            
            <!-- SIBLINGS WITH SCHOLARSHIPS Section -->
            <div class="form-section">
                <h3>Siblings Enjoying Scholarship</h3>
                
                <div id="siblings-container">
                    <!-- Siblings will be added dynamically via JavaScript -->
                </div>
                
                <input type="hidden" id="sibling_count" name="sibling_count" value="0">
                
                <div class="form-row">
                    <button type="button" class="btn btn-primaryy" id="add-sibling">
                        <i class="bi bi-plus-circle"></i> Add Sibling
                    </button>
                </div>
            </div>
            
            <!-- PREFERRED SCHOOL Section -->
            <div class="form-section">
                <h3>Preferred School</h3>
                
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
                        <textarea id="pref_remarks" name="pref_remarks" rows="3" autocomplete="off"></textarea>
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
                    <div class="checkbox-item">
                        <input type="checkbox" id="form_signed" name="form_signed">
                        <label for="form_signed">Paper form has been signed by applicant</label>
                    </div>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="signature_date">Date signed by applicant:</label>
                    <input type="date" id="signature_date" name="signature_date">
                </div>
                
                <div class="form-group">
                    <label for="signature_scan">Upload scan of e-signature:</label>
                    <input type="file" id="signature_scan" name="signature_scan" accept="image/*">
                    <div id="signature_preview" style="margin-top: 10px; display: none;">
                        <img id="signature_preview_img" src="" alt="Signature Preview" style="max-width: 200px; max-height: 150px; border: 1px solid #ddd; border-radius: 4px; padding: 5px; background: #f9f9f9;">
                        <div style="margin-top: 5px; font-size: 12px; color: #666;">
                            <span id="file_info"></span>
                            <button type="button" id="remove_signature" style="margin-left: 10px; background: #dc3545; color: white; border: none; padding: 2px 8px; border-radius: 3px; font-size: 11px; cursor: pointer;">Remove</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
            
            <!-- FORM SUBMISSION -->
            <div class="text-center mt-4">
                <button type="submit" class="btn-submit">Submit Application</button>
                <a href="index.php" class="btn btn-secondary" style="margin-left: 10px; text-decoration: none; padding: 10px 20px; background-color: #fff ; color: black; border-radius: 4px; display: inline-block;">Cancel</a>
            </div>
        </form>
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
document.addEventListener('DOMContentLoaded', function() {
    let siblingCount = 0;
    
    // Date validation
    const today = new Date();
    const currentYear = today.getFullYear();
    const month = String(today.getMonth() + 1).padStart(2, '0');
    const day = String(today.getDate()).padStart(2, '0');
    const currentDate = `${currentYear}-${month}-${day}`;
    
    // Add sibling function
    function addSibling() {
        siblingCount++;
        const container = document.getElementById('siblings-container');
        
        const siblingDiv = document.createElement('div');
        siblingDiv.className = 'sibling-entry';
        siblingDiv.style.border = '1px solid #ddd';
        siblingDiv.style.padding = '15px';
        siblingDiv.style.marginBottom = '15px';
        siblingDiv.style.borderRadius = '5px';
        siblingDiv.style.backgroundColor = '#f9f9f9';
        
        siblingDiv.innerHTML = `
            <div class="form-row">
                <div class="form-group">
                    <label>Last Name:</label>
                    <input type="text" name="sib_last_name[${siblingCount-1}]" required autocomplete="off">
                </div>
                <div class="form-group">
                    <label>First Name:</label>
                    <input type="text" name="sib_first_name[${siblingCount-1}]" required autocomplete="off">
                </div>
                <div class="form-group" style="flex: 0 0 100px;">
                    <label>M.I.:</label>
                    <input type="text" name="sib_middle_initial[${siblingCount-1}]" maxlength="1" style="width: 100%;" autocomplete="off">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Scholarship Name:</label>
                    <input type="text" name="scholar_name[${siblingCount-1}]" placeholder="Scholarship Name" style="width:95%" autocomplete="off">
                </div>
                <div class="form-group">
                    <label>Course:</label>
                    <input type="text" name="scholar_course[${siblingCount-1}]" placeholder="Course" style="width:95%" autocomplete="off">
                </div>
                <div class="form-group">
                    <label>Year Level:</label>
                    <select name="scholar_year[${siblingCount-1}]" style="width:95%; height: 38px; padding: 8px 10px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box;">
                        <option value="">Select Year Level</option>
                        <option value="1st Year">1st Year</option>
                        <option value="2nd Year">2nd Year</option>
                        <option value="3rd Year">3rd Year</option>
                        <option value="4th Year">4th Year</option>
                        <option value="5th Year">5th Year</option>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <button type="button" class="remove-sibling" style="background-color: #dc3545; color: white; border: none; padding: 5px 10px; border-radius: 4px; cursor: pointer;">Remove Sibling</button>
            </div>
        `;
        
        container.appendChild(siblingDiv);
        document.getElementById('sibling_count').value = siblingCount;
        
        // No need for year validation since we're using a dropdown now
        
        // Add remove functionality
        siblingDiv.querySelector('.remove-sibling').addEventListener('click', function() {
            siblingDiv.remove();
            siblingCount--;
            document.getElementById('sibling_count').value = siblingCount;
        });
    }

    // Add this new function to handle year validation for sibling year inputs
    function addYearValidation(input) {
        input.addEventListener('blur', function() {
            const enteredValue = this.value.trim();
            
            if (enteredValue !== '') {
                const enteredYear = parseInt(enteredValue);
                
                if (!isNaN(enteredYear)) {
                    if (enteredYear > currentYear) {
                        this.value = currentYear;
                    } else if (enteredYear < 1900) {
                        this.value = 1900;
                    }
                }
            }
        });
        
        input.addEventListener('change', function() {
            const enteredValue = this.value.trim();
            
            if (enteredValue !== '') {
                const enteredYear = parseInt(enteredValue);
                
                if (!isNaN(enteredYear)) {
                    if (enteredYear > currentYear) {
                        this.value = currentYear;
                    } else if (enteredYear < 1900) {
                        this.value = 1900;
                    }
                }
            }
        });
        
        // Allow only numeric input
        input.addEventListener('keypress', function(e) {
            if ([8, 9, 27, 13, 46].indexOf(e.keyCode) !== -1 ||
                (e.keyCode === 65 && e.ctrlKey === true) ||
                (e.keyCode === 67 && e.ctrlKey === true) ||
                (e.keyCode === 86 && e.ctrlKey === true) ||
                (e.keyCode === 88 && e.ctrlKey === true)) {
                return;
            }
            if ((e.shiftKey || (e.keyCode < 48 || e.keyCode > 57)) && (e.keyCode < 96 || e.keyCode > 105)) {
                e.preventDefault();
            }
        });
    }
    
    // Add sibling button event listener
    document.getElementById('add-sibling').addEventListener('click', addSibling);
    
    // Set date limits
    const dobInput = document.getElementById('dob');
    if(dobInput) {
        dobInput.setAttribute('max', currentDate);
    }
    
    const signatureDateInput = document.getElementById('signature_date');
    if(signatureDateInput) {
        signatureDateInput.setAttribute('max', currentDate);
    }
    
    // Enhanced year validation for year graduated fields
    const yearGraduatedInputs = document.querySelectorAll('input[name^="year_graduated"]');
    yearGraduatedInputs.forEach(function(input) {
        input.setAttribute('max', currentYear);
        input.setAttribute('min', '1900');
        
        // Only validate when user finishes typing (blur/change events)
        input.addEventListener('blur', function() {
            const enteredValue = this.value.trim();
            
            if (enteredValue !== '') {
                const enteredYear = parseInt(enteredValue);
                
                if (!isNaN(enteredYear)) {
                    if (enteredYear > currentYear) {
                        this.value = currentYear;
                    } else if (enteredYear < 1900) {
                        this.value = 1900;
                    }
                }
            }
        });
        
        input.addEventListener('change', function() {
            const enteredValue = this.value.trim();
            
            if (enteredValue !== '') {
                const enteredYear = parseInt(enteredValue);
                
                if (!isNaN(enteredYear)) {
                    if (enteredYear > currentYear) {
                        this.value = currentYear;
                    } else if (enteredYear < 1900) {
                        this.value = 1900;
                    }
                }
            }
        });
        
        // Allow only numeric input
        input.addEventListener('keypress', function(e) {
            if ([8, 9, 27, 13, 46].indexOf(e.keyCode) !== -1 ||
                (e.keyCode === 65 && e.ctrlKey === true) ||
                (e.keyCode === 67 && e.ctrlKey === true) ||
                (e.keyCode === 86 && e.ctrlKey === true) ||
                (e.keyCode === 88 && e.ctrlKey === true)) {
                return;
            }
            if ((e.shiftKey || (e.keyCode < 48 || e.keyCode > 57)) && (e.keyCode < 96 || e.keyCode > 105)) {
                e.preventDefault();
            }
        });
    });
    
    // Apply same validation to family ITR year fields
    const yearITRInputs = document.querySelectorAll('input[id*="fam_year_itr"]');
    yearITRInputs.forEach(function(input) {
        input.setAttribute('max', currentYear);
        input.setAttribute('min', '1900');
        
        input.addEventListener('blur', function() {
            const enteredValue = this.value.trim();
            
            if (enteredValue !== '') {
                const enteredYear = parseInt(enteredValue);
                
                if (!isNaN(enteredYear)) {
                    if (enteredYear > currentYear) {
                        this.value = currentYear;
                    } else if (enteredYear < 1900) {
                        this.value = 1900;
                    }
                }
            }
        });
        
        input.addEventListener('change', function() {
            const enteredValue = this.value.trim();
            
            if (enteredValue !== '') {
                const enteredYear = parseInt(enteredValue);
                
                if (!isNaN(enteredYear)) {
                    if (enteredYear > currentYear) {
                        this.value = currentYear;
                    } else if (enteredYear < 1900) {
                        this.value = 1900;
                    }
                }
            }
        });
        
        // Allow only numeric input
        input.addEventListener('keypress', function(e) {
            if ([8, 9, 27, 13, 46].indexOf(e.keyCode) !== -1 ||
                (e.keyCode === 65 && e.ctrlKey === true) ||
                (e.keyCode === 67 && e.ctrlKey === true) ||
                (e.keyCode === 86 && e.ctrlKey === true) ||
                (e.keyCode === 88 && e.ctrlKey === true)) {
                return;
            }
            if ((e.shiftKey || (e.keyCode < 48 || e.keyCode > 57)) && (e.keyCode < 96 || e.keyCode > 105)) {
                e.preventDefault();
            }
        });
    });
    
    // Set year limits for all other year inputs
    const yearInputs = document.querySelectorAll('input[type="number"][id*="year"]');
    yearInputs.forEach(function(input) {
        if(input.getAttribute('max') === '<?php echo date("Y"); ?>') {
            input.setAttribute('max', currentYear);
        }
    });
    
    // Form validation
    const form = document.querySelector('form');
    if(form) {
        form.addEventListener('submit', function(event) {
            // Clean up income values before submission
            const incomeFields = document.querySelectorAll('input[id*="annual_income"]');
            incomeFields.forEach(function(input) {
                // Remove commas for form submission
                input.value = input.value.replace(/,/g, '');
            });

            const requiredFields = form.querySelectorAll('[required]');
            let valid = true;
            
            requiredFields.forEach(field => {
                if(!field.value.trim()) {
                    field.classList.add('is-invalid');
                    field.style.borderColor = '#dc3545';
                    valid = false;
                } else {
                    field.classList.remove('is-invalid');
                    field.style.borderColor = '#ddd';
                }
            });
            
            // Additional validation for year fields before submission (UPDATED)
            const allYearInputs = document.querySelectorAll('input[name^="year_graduated"], input[id*="fam_year_itr"], input[name^="scholar_year"]');
            allYearInputs.forEach(input => {
                if (input.value && parseInt(input.value) > currentYear) {
                    input.value = currentYear;
                }
            });
            
            if(!valid) {
                event.preventDefault();
                alert('Please fill in all required fields.');
                return false;
            }
            
            // Validate at least one education level is filled
            const schoolNames = document.querySelectorAll('input[name^="school_name"]');
            let hasEducation = false;
            schoolNames.forEach(input => {
                if(input.value.trim()) {
                    hasEducation = true;
                }
            });
            
            if(!hasEducation) {
                event.preventDefault();
                alert('Please fill in at least one educational attainment.');
                return false;
            }
            
            // Validate assistance type is selected
            const assistanceTypes = document.querySelectorAll('input[name="assistance_type"]');
            let assistanceSelected = false;
            assistanceTypes.forEach(input => {
                if(input.checked) {
                    assistanceSelected = true;
                }
            });
            
            if(!assistanceSelected) {
                event.preventDefault();
                alert('Please select an assistance type.');
                return false;
            }
            
            // Show loading message
            const submitBtn = document.querySelector('.btn-submit');
            if(submitBtn) {
                submitBtn.disabled = true;
                submitBtn.textContent = 'Submitting...';
            }
        });
    }
    
    // Auto-dismiss success/error messages after 10 seconds
    const messages = document.querySelectorAll('.success-message, .error-message');
    messages.forEach(function(message) {
        setTimeout(function() {
            message.style.display = 'none';
        }, 10000);
    });
    
    // Grade average validation (0-100)
    const gradeInputs = document.querySelectorAll('input[name^="grade_average"]');
    gradeInputs.forEach(function(input) {
        input.addEventListener('input', function() {
            const value = parseFloat(this.value);
            if(value < 0) {
                this.value = 0;
            } else if(value > 100) {
                this.value = 100;
            }
        });
    });
    
    // Income formatting with automatic comma formatting
    const incomeInputs = document.querySelectorAll('input[id*="annual_income"]');
    incomeInputs.forEach(function(input) {
        // Allow only numeric input and decimal point while typing
        input.addEventListener('keypress', function(e) {
            // Allow backspace, scan my #sym:Semester  #sym:GetSemester  #sym:GetSemesterName  #sym:GetSemesters  #sym:SaveSemester  #file:Semester.cshtml  #file:SemesterManagement.js and fix that Duplicate Semester. only make use of duplicate semester validation if im adding a NEW semester but if im editing an existing semester then don't give me the duplicate semester validation because sometimes im gonna adjust to make the status current and maintain the 	Start Date and end date and the what kind of semester in edit so dont give me that duplicate semester. unless im adding a new semester via "Add New Semester" button then that's the time you will use the duplicate semester error validation, tab, escape, enter
            if ([8, 9, 27, 13, 46].indexOf(e.keyCode) !== -1 ||
                // Allow Ctrl+A, Ctrl+C, Ctrl+V, Ctrl+X
                (e.keyCode === 65 && e.ctrlKey === true) ||
                (e.keyCode === 67 && e.ctrlKey === true) ||
                (e.keyCode === 86 && e.ctrlKey === true) ||
                (e.keyCode === 88 && e.ctrlKey === true) ||
                // Allow decimal point
                e.keyCode === 46 || e.keyCode === 190) {
                return;
            }
            // Ensure that it is a number and stop the keypress
            if ((e.shiftKey || (e.keyCode < 48 || e.keyCode > 57)) && (e.keyCode < 96 || e.keyCode > 105)) {
                e.preventDefault();
            }
        });
        
        // Clean input while typing (remove non-numeric except decimal)
        input.addEventListener('input', function() {
            // Remove all non-numeric characters except decimal point
            let cleanValue = this.value.replace(/[^0-9.]/g, '');
            
            // Ensure only one decimal point
            const parts = cleanValue.split('.');
            if(parts.length > 2) {
                cleanValue = parts[0] + '.' + parts.slice(1).join('');
            }
            
            // Update without commas while typing
            this.value = cleanValue;
        });
        
        // Format with commas only when field loses focus
        input.addEventListener('blur', function() {
            const enteredValue = this.value.trim();
            
            if (enteredValue !== '') {
                // Remove existing commas first
                let cleanValue = enteredValue.replace(/,/g, '');
                
                // Ensure only one decimal point
                const parts = cleanValue.split('.');
                if(parts.length > 2) {
                    cleanValue = parts[0] + '.' + parts.slice(1).join('');
                }
                
                // Split into integer and decimal parts
                const [integerPart, decimalPart] = cleanValue.split('.');
                
                // Add commas to integer part
                let formattedInteger = '';
                if(integerPart) {
                    formattedInteger = integerPart.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
                }
                
                // Combine integer and decimal parts
                let formattedValue = formattedInteger;
                if(decimalPart !== undefined) {
                    formattedValue += '.' + decimalPart;
                }
                
                // Update the input value with formatting
                this.value = formattedValue;
            }
            
            // Store the clean value for form submission
            const cleanValue = this.value.replace(/,/g, '');
            this.setAttribute('data-clean-value', cleanValue);
        });
    });

    // Image preview functionality for signature upload
    const signatureScanInput = document.getElementById('signature_scan');
    const signaturePreview = document.getElementById('signature_preview');
    const signaturePreviewImg = document.getElementById('signature_preview_img');
    const fileInfo = document.getElementById('file_info');
    const removeSignatureBtn = document.getElementById('remove_signature');

    if (signatureScanInput) {
        signatureScanInput.addEventListener('change', function(event) {
            const file = event.target.files[0];
            
            if (file) {
                // Validate file type
                if (!file.type.startsWith('image/')) {
                    alert('Please select an image file.');
                    this.value = '';
                    return;
                }
                
                // Validate file size (max 5MB)
                if (file.size > 5 * 1024 * 1024) {
                    alert('File size must be less than 5MB.');
                    this.value = '';
                    return;
                }
                
                // Create file reader
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    // Show preview
                    signaturePreviewImg.src = e.target.result;
                    signaturePreview.style.display = 'block';
                    
                    // Show file info
                    const fileSize = (file.size / 1024).toFixed(1);
                    fileInfo.textContent = `${file.name} (${fileSize} KB)`;
                };
                
                reader.readAsDataURL(file);
            } else {
                // Hide preview if no file selected
                signaturePreview.style.display = 'none';
                signaturePreviewImg.src = '';
                fileInfo.textContent = '';
            }
        });
    }

    // Remove signature functionality
    if (removeSignatureBtn) {
        removeSignatureBtn.addEventListener('click', function() {
            // Clear the file input
            signatureScanInput.value = '';
            
            // Hide preview
            signaturePreview.style.display = 'none';
            signaturePreviewImg.src = '';
            fileInfo.textContent = '';
        });
    }
});
</script>

</body>
<!-- END BODY -->
</html>
 