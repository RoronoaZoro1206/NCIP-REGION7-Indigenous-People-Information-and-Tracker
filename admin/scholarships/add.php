<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include "../config/database.php";

// Check if user is logged in
if (!isset($_SESSION["user_id"]) || !isset($_SESSION["role"]) || strtoupper($_SESSION["role"]) !== 'ADMIN') {
    header("Location: ../login.php");
    exit();
}

// Get education categories
try {
    $educStmt = $pdo->query("SELECT * FROM EDUCATION_CATEGORY ORDER BY EDUC_ID");
    $educationCategories = $educStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Normalize array keys to ensure consistent capitalization
    $normalizedCategories = [];
    foreach ($educationCategories as $category) {
        $normalizedCategories[] = [
            'EDUC_ID' => isset($category['EDUC_ID']) ? $category['EDUC_ID'] : (isset($category['educ_id']) ? $category['educ_id'] : null),
            'EDUC_NAME' => isset($category['EDUC_NAME']) ? $category['EDUC_NAME'] : (isset($category['educ_name']) ? $category['educ_name'] : null)
        ];
    }
    $educationCategories = $normalizedCategories;
} catch (PDOException $e) {
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

// Process form submission
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
        
        // Get current date and time
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
        
        // Form signed verification
        $formSigned = isset($_POST['form_signed']) ? true : false;
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
        foreach ($educationCategories as $category) {
            $index = $category['EDUC_ID'] - 1;
            
            // Skip if school name is empty
            if (empty($_POST['school_name'][$index])) {
                continue;
            }
            
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
            
            $eduStmt->bindParam(':category_id', $category['EDUC_ID']);
            $eduStmt->bindParam(':app_code', $appCode);
            $eduStmt->execute();
            
            $eduCode = null;
            if ($eduStmt->rowCount() > 0) {
                $result = $eduStmt->fetch(PDO::FETCH_ASSOC);
                $eduCode = $result['edua_code'];
            }
            
            if (!$eduCode) {
                throw new Exception("Failed to get EDUA_CODE after insertion for category: " . $category['EDUC_NAME']);
            }
            
            // Insert school details
            $schoolStmt = $pdo->prepare("
                INSERT INTO SCHOOL (
                    SCH_NAME, 
                    SCH_ADDRESS, 
                    SCH_YEAR_GRAD, 
                    SCH_GRADE_AVG, 
                    SCH_RANK, 
                    EDUA_CODE
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
            $yearGraduated = !empty($_POST['year_graduated'][$index]) ? $_POST['year_graduated'][$index] : null;
            $schoolStmt->bindParam(':year_grad', $yearGraduated);
            $gradeAverage = !empty($_POST['grade_average'][$index]) ? floatval($_POST['grade_average'][$index]) : null;
            $schoolStmt->bindParam(':grade_avg', $gradeAverage);
            $rank = $_POST['rank'][$index] ?? '';
            $schoolStmt->bindParam(':rank', $rank);
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
            
            $isAlive = isset($_POST['fam_alive_' . strtolower($relation)]) && $_POST['fam_alive_' . strtolower($relation)] == 1;
            $relationLower = strtolower($relation);
            
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
            
            $famStmt->bindParam(':relation', $relation);
            $famStmt->bindParam(':is_alive', $isAlive, PDO::PARAM_BOOL);
            $famStmt->bindParam(':name', $_POST['fam_name_' . $relationLower]);
            $famStmt->bindParam(':addr', $_POST['fam_addr_' . $relationLower]);
            $famStmt->bindParam(':occup', $_POST['fam_occup_' . $relationLower]);
            $famStmt->bindParam(':office_addr', $_POST['fam_office_addr_' . $relationLower]);
            $famStmt->bindParam(':educ_attain', $_POST['fam_educ_attain_' . $relationLower]);
            $famStmt->bindParam(':ethno_group', $_POST['fam_ethno_group_' . $relationLower]);
            
            $annualIncome = !empty($_POST['fam_annual_income_' . $relationLower]) ? 
                floatval(str_replace(',', '', $_POST['fam_annual_income_' . $relationLower])) : 0;
            $famStmt->bindParam(':annual_income', $annualIncome);
            
            $yearItr = !empty($_POST['fam_year_itr_' . $relationLower]) ? 
                intval($_POST['fam_year_itr_' . $relationLower]) : date('Y');
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
                $scholarStmt->bindParam(':year', $_POST['scholar_year'][$i]);
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
        
        // 8. Insert initial status
        $statusStmt = $pdo->prepare("
            INSERT INTO SCHOLARSHIP_STATUS (
                APP_CODE, 
                STATUS_CODE, 
                STATUS_DATE, 
                STATUS_IS_ACTIVE
            ) VALUES (
                :app_code, 
                :status_code, 
                CURRENT_TIMESTAMP, 
                TRUE
            )
        ");
        
        $statusCode = $_POST['initial_status'] ?? 'PENDING';
        $statusStmt->bindParam(':app_code', $appCode);
        $statusStmt->bindParam(':status_code', $statusCode);
        $statusStmt->execute();
        
        // Commit transaction
        $pdo->commit();
        
        // Log the successful addition
        $log_query = "INSERT INTO USER_LOGS (USER_ID, LOG_TYPE, LOG_DETAILS, LOG_TIMESTAMP) 
                     VALUES (:user_id, 'ADD_APPLICATION', :details, CURRENT_TIMESTAMP)";
        $log_stmt = $pdo->prepare($log_query);
        $log_details = "Admin added new scholarship application for " . $_POST['first_name'] . " " . $_POST['last_name'];
        $log_stmt->bindParam(":user_id", $_SESSION["user_id"]);
        $log_stmt->bindParam(":details", $log_details);
        $log_stmt->execute();
        
        $_SESSION['success_message'] = "Scholarship application added successfully!";
        header("Location: index.php");
        exit();
        
    } catch (Exception $e) {
        // Rollback transaction on error
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        
        // Log the error
        $error_log_query = "INSERT INTO USER_LOGS (USER_ID, LOG_TYPE, LOG_DETAILS, LOG_TIMESTAMP) 
                          VALUES (:user_id, 'ERROR', :details, CURRENT_TIMESTAMP)";
        $error_log_stmt = $pdo->prepare($error_log_query);
        $error_details = "Error adding scholarship application: " . $e->getMessage();
        $error_log_stmt->bindParam(":user_id", $_SESSION["user_id"]);
        $error_log_stmt->bindParam(":details", $error_details);
        $error_log_stmt->execute();
        
        $_SESSION['error_message'] = "Error: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en-US">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.2, user-scalable=yes" />

    <title>Add Scholarship Application - NCIP Admin</title>
    <link rel="icon" href="../assets/img/ncip-logo.png" type="image/png">

    <!-- Bootstrap and Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    
    <!-- For iPad -->
    <link rel="apple-touch-icon" sizes="180x180" href="../assets/img/ncip-logo.png">
    
    <style>
        body { 
            background-color: #f8f9fa; 
        }
        
        .main-content {
            margin-left: 250px;
            padding: 20px;
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
        
        .form-section {
            margin-bottom: 30px;
            border: 1px solid #ddd;
            padding: 15px;
            border-radius: 5px;
        }
        
        .form-section h3 {
            margin-top: 0;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
            font-size: 1.2rem;
        }
        
        .form-row {
            display: flex;
            flex-wrap: wrap;
            margin: 0 -10px;
        }
        
        .form-group {
            padding: 0 10px;
            margin-bottom: 15px;
            flex: 1 1 250px;
        }
        
        label {
            display: block;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        input[type="text"],
        input[type="email"],
        input[type="date"],
        input[type="number"],
        select,
        textarea {
            width: 100%;
            padding: 8px 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
            height: 38px;
        }
        
        textarea {
            height: auto;
            min-height: 38px;
        }
        
        .checkbox-group {
            display: flex;
            gap: 15px;
        }
        
        .checkbox-item {
            display: flex;
            align-items: center;
        }
        
        .checkbox-item input {
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

        .scholarship-form table th:first-child {
            width: 18%;
            word-wrap: break-word;
            white-space: normal;
        }

        .scholarship-form table td:first-child {
            word-break: break-word;
            white-space: normal;
        }
                
        .scholarship-form table th:nth-child(3),
        .scholarship-form table th:nth-child(4),
        .scholarship-form table th:nth-child(5) {
            width: 120px;
        }
        
        .scholarship-form table td:nth-child(3) input,
        .scholarship-form table td:nth-child(4) input,
        .scholarship-form table td:nth-child(5) input {
            width: 100%;
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
        
        .sibling-item {
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 15px;
            background-color: #f9f9f9;
        }
        
        @media (max-width: 768px) {

            
            .main-content {
                margin-left: 0;
            }
            
            .form-group {
                flex: 1 1 100%;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1>Add New Scholarship Application</h1>
                <a href="index.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Back to List
                </a>
            </div>

            <!-- Alert Messages -->
            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($_SESSION['error_message']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['error_message']); ?>
            <?php endif; ?>

            <div class="scholarship-form">
                <div class="header-logo">
                    <img src="../assets/img/ncip-logo.png" alt="NCIP Logo">
                </div>
                
                <h1>Republic of the Philippines</h1>
                <h1>NATIONAL COMMISSION ON INDIGENOUS PEOPLES</h1>
                <h2>EDUCATIONAL ASSISTANCE/SCHOLARSHIP PROGRAM APPLICATION FORM</h2>
                
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
                                <textarea id="add_remarks" name="add_remarks" rows="2" autocomplete="off"></textarea>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>Assistance applied for:</label>
                                <div class="checkbox-group">
                                    <div class="checkbox-item">
                                        <input type="radio" id="regular" name="assistance_type" value="Regular">
                                        <label for="regular">Regular</label>
                                    </div>
                                    <div class="checkbox-item">
                                        <input type="radio" id="merit_based" name="assistance_type" value="Merit-Based" checked>
                                        <label for="merit_based">Merit-Based</label>
                                    </div>
                                    <div class="checkbox-item">
                                        <input type="radio" id="pdaf" name="assistance_type" value="PDAF">
                                        <label for="pdaf">PDAF</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- BASIC INFORMATION Section -->
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
                            
                            <div class="form-group" style="flex: 0 0 100px;">
                                <label for="middle_initial">Middle Initial:</label>
                                <input type="text" id="middle_initial" name="middle_initial" maxlength="1" style="width: 100%;" autocomplete="off">
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
                                <select id="civil_status" name="civil_status" required>
                                    <option value="">Select Civil Status</option>
                                    <option value="Single">Single</option>
                                    <option value="Married">Married</option>
                                    <option value="Widowed">Widowed</option>
                                    <option value="Divorced">Divorced</option>
                                    <option value="Separated">Separated</option>
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
                                <input type="text" id="addr_permanent" name="addr_permanent" autocomplete="off">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="addr_house_street">House No. and Street/Sitio:</label>
                                <input type="text" id="addr_house_street" name="addr_house_street" autocomplete="off">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="addr_barangay">Barangay:</label>
                                <input type="text" id="addr_barangay" name="addr_barangay" autocomplete="off">
                            </div>
                            
                            <div class="form-group">
                                <label for="addr_municipality">Municipality:</label>
                                <input type="text" id="addr_municipality" name="addr_municipality" autocomplete="off">
                            </div>
                            
                            <div class="form-group">
                                <label for="addr_province">Province:</label>
                                <input type="text" id="addr_province" name="addr_province" autocomplete="off">
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
                                    <th>School Name</th>
                                    <th>School Address</th>
                                    <th>Year Graduated</th>
                                    <th>Grade Average</th>
                                    <th>Rank</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($educationCategories as $index => $category): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($category['EDUC_NAME']); ?></td>
                                    <td>
                                        <input type="text" name="school_name[<?php echo $index; ?>]" style="width: 100%;" autocomplete="off">
                                    </td>
                                    <td>
                                        <input type="text" name="school_address[<?php echo $index; ?>]" style="width: 100%;" autocomplete="off">
                                    </td>
                                    <td>
                                        <input type="number" name="year_graduated[<?php echo $index; ?>]" min="1900" max="<?php echo date('Y'); ?>" style="width: 100%;" autocomplete="off">
                                    </td>
                                    <td>
                                        <input type="number" name="grade_average[<?php echo $index; ?>]" min="0" max="100" step="0.01" style="width: 100%;" autocomplete="off" title="Enter a grade between 0 and 100">
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
                                
                                <div style="margin-bottom: 10px;">
                                    <input type="radio" id="father_living" name="fam_alive_father" value="1" checked>
                                    <label for="father_living" style="display: inline;">Living</label>
                                    
                                    <input type="radio" id="father_deceased" name="fam_alive_father" value="0" style="margin-left: 15px;">
                                    <label for="father_deceased" style="display: inline;">Deceased</label>
                                </div>
                                
                                <label for="fam_name_father">Name:</label>
                                <input type="text" id="fam_name_father" name="fam_name_father" autocomplete="off">
                                
                                <label for="fam_addr_father">Address:</label>
                                <input type="text" id="fam_addr_father" name="fam_addr_father" autocomplete="off">
                                
                                <label for="fam_occup_father">Occupation:</label>
                                <input type="text" id="fam_occup_father" name="fam_occup_father" autocomplete="off">
                                
                                <label for="fam_office_addr_father">Office Address:</label>
                                <input type="text" id="fam_office_addr_father" name="fam_office_addr_father" autocomplete="off">
                                
                                <label for="fam_educ_attain_father">Educational Attainment:</label>
                                <select id="fam_educ_attain_father" name="fam_educ_attain_father">
                                    <option value="">Select Education Level</option>
                                    <?php foreach ($educationCategories as $category): ?>
                                    <option value="<?php echo htmlspecialchars($category['EDUC_NAME']); ?>"><?php echo htmlspecialchars($category['EDUC_NAME']); ?></option>
                                    <?php endforeach; ?>
                                    <option value="None">None</option>
                                </select>
                                
                                <label for="fam_ethno_group_father">Ethnolinguistic Group:</label>
                                <input type="text" id="fam_ethno_group_father" name="fam_ethno_group_father" autocomplete="off">
                                
                                <label for="fam_annual_income_father">Annual Income:</label>
                                <input type="text" id="fam_annual_income_father" name="fam_annual_income_father" autocomplete="off">
                                
                                <label for="fam_year_itr_father">Year of ITR Attached:</label>
                                <input type="number" id="fam_year_itr_father" name="fam_year_itr_father" min="1900" max="<?php echo date('Y'); ?>" autocomplete="off">
                            </div>
                            
                            <div class="form-group" style="flex: 1;">
                                <h4>Mother</h4>
                                
                                <div style="margin-bottom: 10px;">
                                    <input type="radio" id="mother_living" name="fam_alive_mother" value="1" checked>
                                    <label for="mother_living" style="display: inline;">Living</label>
                                    
                                    <input type="radio" id="mother_deceased" name="fam_alive_mother" value="0" style="margin-left: 15px;">
                                    <label for="mother_deceased" style="display: inline;">Deceased</label>
                                </div>
                                
                                <label for="fam_name_mother">Name:</label>
                                <input type="text" id="fam_name_mother" name="fam_name_mother" autocomplete="off">
                                
                                <label for="fam_addr_mother">Address:</label>
                                <input type="text" id="fam_addr_mother" name="fam_addr_mother" autocomplete="off">
                                
                                <label for="fam_occup_mother">Occupation:</label>
                                <input type="text" id="fam_occup_mother" name="fam_occup_mother" autocomplete="off">
                                
                                <label for="fam_office_addr_mother">Office Address:</label>
                                <input type="text" id="fam_office_addr_mother" name="fam_office_addr_mother" autocomplete="off">
                                
                                <label for="fam_educ_attain_mother">Educational Attainment:</label>
                                <select id="fam_educ_attain_mother" name="fam_educ_attain_mother">
                                    <option value="">Select Education Level</option>
                                    <?php foreach ($educationCategories as $category): ?>
                                    <option value="<?php echo htmlspecialchars($category['EDUC_NAME']); ?>"><?php echo htmlspecialchars($category['EDUC_NAME']); ?></option>
                                    <?php endforeach; ?>
                                    <option value="None">None</option>
                                </select>
                                
                                <label for="fam_ethno_group_mother">Ethnolinguistic Group:</label>
                                <input type="text" id="fam_ethno_group_mother" name="fam_ethno_group_mother" autocomplete="off">
                                
                                <label for="fam_annual_income_mother">Annual Income:</label>
                                <input type="text" id="fam_annual_income_mother" name="fam_annual_income_mother" autocomplete="off">
                                
                                <label for="fam_year_itr_mother">Year of ITR Attached:</label>
                                <input type="number" id="fam_year_itr_mother" name="fam_year_itr_mother" min="1900" max="<?php echo date('Y'); ?>" autocomplete="off">
                            </div>
                            
                            <div class="form-group" style="flex: 1;">
                                <h4>Guardian</h4>
                                
                                <div style="margin-bottom: 10px;">
                                    <input type="radio" id="guardian_living" name="fam_alive_guardian" value="1" checked>
                                    <label for="guardian_living" style="display: inline;">Living</label>
                                    
                                    <input type="radio" id="guardian_deceased" name="fam_alive_guardian" value="0" style="margin-left: 15px;">
                                    <label for="guardian_deceased" style="display: inline;">Deceased</label>
                                </div>
                                
                                <label for="fam_name_guardian">Name:</label>
                                <input type="text" id="fam_name_guardian" name="fam_name_guardian" autocomplete="off">
                                
                                <label for="fam_addr_guardian">Address:</label>
                                <input type="text" id="fam_addr_guardian" name="fam_addr_guardian" autocomplete="off">
                                
                                <label for="fam_occup_guardian">Occupation:</label>
                                <input type="text" id="fam_occup_guardian" name="fam_occup_guardian" autocomplete="off">
                                
                                <label for="fam_office_addr_guardian">Office Address:</label>
                                <input type="text" id="fam_office_addr_guardian" name="fam_office_addr_guardian" autocomplete="off"> 
                                
                                <label for="fam_educ_attain_guardian">Educational Attainment:</label>
                                <select id="fam_educ_attain_guardian" name="fam_educ_attain_guardian">
                                    <option value="">Select Education Level</option>
                                    <?php foreach ($educationCategories as $category): ?>
                                    <option value="<?php echo htmlspecialchars($category['EDUC_NAME']); ?>"><?php echo htmlspecialchars($category['EDUC_NAME']); ?></option>
                                    <?php endforeach; ?>
                                    <option value="None">None</option>
                                </select>
                                
                                <label for="fam_ethno_group_guardian">Ethnolinguistic Group:</label>
                                <input type="text" id="fam_ethno_group_guardian" name="fam_ethno_group_guardian"autocomplete="off">
                                
                                <label for="fam_annual_income_guardian">Annual Income:</label>
                                <input type="text" id="fam_annual_income_guardian" name="fam_annual_income_guardian"autocomplete="off">
                                
                                <label for="fam_year_itr_guardian">Year of ITR Attached:</label>
                                <input type="number" id="fam_year_itr_guardian" name="fam_year_itr_guardian" min="1900" max="<?php echo date('Y'); ?>" autocomplete="off">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Siblings with Scholarships Section -->
                    <div class="form-section">
                        <h3>Siblings Enjoying Scholarship</h3>
                        
                        <div id="siblings-container">
                            <!-- Siblings will be added dynamically via JavaScript -->
                        </div>
                        
                        <input type="hidden" id="sibling_count" name="sibling_count" value="0">
                        <button type="button" class="btn btn-primary" id="add-sibling">
                            <i class="bi bi-plus-circle"></i> Add Sibling
                        </button>
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
                                <textarea id="pref_remarks" name="pref_remarks" rows="3"></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <!-- CERTIFICATION Section -->
                    <div class="form-section">
                        <h3>Certification</h3>
                        
                        <p>This is to certify that I am not availing of any other government scholarship and all data supplied in this application are true and correct. Any material misinterpretation or alteration made hereof shall be sufficient ground for this application to be denied. If chosen, I will abide by the rules and regulations set forth by the National Commission on Indigenous Peoples.</p>
                        
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
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="initial_status">Initial Application Status:</label>
                                <select id="initial_status" name="initial_status">
                                    <option value="PENDING">Pending</option>
                                    <option value="APPROVED">Approved</option>
                                    <option value="REJECTED">Rejected</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <!-- FORM SUBMISSION -->
                    <div class="text-center mt-4">
                        <button type="submit" class="btn btn-primary btn-lg">Submit Application</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Define global functions that need to be accessible from HTML
        function addSibling() {
            // Get current sibling count and increment it
            const siblingCountInput = document.getElementById('sibling_count');
            const siblingCount = parseInt(siblingCountInput.value || '0') + 1;
            siblingCountInput.value = siblingCount;
            
            // Create new sibling element
            const siblingsContainer = document.getElementById('siblings-container');
            const siblingItem = document.createElement('div');
            siblingItem.className = 'sibling-item';
            siblingItem.id = `sibling-${siblingCount}`;
            siblingItem.innerHTML = `
                <div class="form-row">
                    <div class="form-group">
                        <label>Last Name:</label>
                        <input type="text" name="sib_last_name[${siblingCount-1}]" required>
                    </div>
                    <div class="form-group">
                        <label>First Name:</label>
                        <input type="text" name="sib_first_name[${siblingCount-1}]" required>
                    </div>
                    <div class="form-group" style="flex: 0 0 100px;">
                        <label>M.I.:</label>
                        <input type="text" name="sib_middle_initial[${siblingCount-1}]" maxlength="1" style="width: 100%;">
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
                        <label>Year:</label>
                        <select name="scholar_year[${siblingCount-1}]" style="width:100%; padding: 8px 5px;">
                            <option value="">Select Year</option>
                            <option value="1st Year">1st Year</option>
                            <option value="2nd Year">2nd Year</option>
                            <option value="3rd Year">3rd Year</option>
                            <option value="4th Year">4th Year</option>
                            <option value="5th Year">5th Year</option>
                        </select>
                    </div>
                </div>
                
                <button type="button" class="btn btn-danger btn-sm mt-2" onclick="removeSibling(${siblingCount})">
                    <i class="bi bi-trash"></i> Remove
                </button>
            `;
            
            siblingsContainer.appendChild(siblingItem);
        }

        function removeSibling(index) {
            const siblingElement = document.getElementById(`sibling-${index}`);
            if (siblingElement) {
                siblingElement.remove();
                // Update the count
                const currentCount = parseInt(document.getElementById('sibling_count').value);
                if (currentCount > 0) {
                    document.getElementById('sibling_count').value = currentCount - 1;
                }
            }
        }

        // Main initialization when DOM is loaded
        document.addEventListener('DOMContentLoaded', function() {
            // Toggle sidebar on mobile
            const sidebarToggle = document.getElementById('sidebarToggle');
            if (sidebarToggle) {
                sidebarToggle.addEventListener('click', function() {
                    document.querySelector('.sidebar').classList.toggle('show-mobile');
                });
            }
            
            // Get current date values for validation
            const today = new Date();
            const currentYear = today.getFullYear();
            const month = String(today.getMonth() + 1).padStart(2, '0');
            const day = String(today.getDate()).padStart(2, '0');
            const currentDate = `${currentYear}-${month}-${day}`;
            
            // Set date limits and validation
            const dobInput = document.getElementById('dob');
            if(dobInput) {
                dobInput.setAttribute('max', currentDate);
            }
            
            const signatureDateInput = document.getElementById('signature_date');
            if(signatureDateInput) {
                signatureDateInput.setAttribute('max', currentDate);
            }
            
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
                            const value = parseFloat(this.value.replace(/,/g, ''));
                            if(!isNaN(value)) {
                                this.value = value.toLocaleString('en-US', {
                                    minimumFractionDigits: 2,
                                    maximumFractionDigits: 2
                                });
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
            
            // Year validation for graduated year fields
            const yearGraduatedInputs = document.querySelectorAll('input[name^="year_graduated"]');
            yearGraduatedInputs.forEach(input => {
                if(input) {
                    input.setAttribute('max', currentYear);
                    input.addEventListener('change', function() {
                        if(parseInt(this.value) > currentYear) {
                            this.value = currentYear;
                        }
                    });
                }
            });

            // Year ITR validation
            const yearITRInputs = document.querySelectorAll('input[id^="fam_year_itr_"]');
            yearITRInputs.forEach(input => {
                if(input) {
                    input.setAttribute('max', currentYear);
                    input.addEventListener('change', function() {
                        if(parseInt(this.value) > currentYear) {
                            this.value = currentYear;
                        }
                    });
                }
            });

            // Year validation for all other year fields 
            const yearInputs = document.querySelectorAll('input[type="number"][min="1900"]');
            yearInputs.forEach(input => {
                if(input) {
                    input.setAttribute('max', currentYear);
                    input.addEventListener('change', function() {
                        if(parseInt(this.value) > currentYear) {
                            this.value = currentYear;
                        }
                    });
                }
            });
            
            // Add sibling button click handler
            const addSiblingButton = document.getElementById('add-sibling');
            if(addSiblingButton) {
                addSiblingButton.addEventListener('click', addSibling);
            }
            
            // Form validation
            const form = document.querySelector('form');
            if(form) {
                form.addEventListener('submit', function(event) {
                    const requiredFields = form.querySelectorAll('[required]');
                    let valid = true;
                    
                    requiredFields.forEach(field => {
                        if(!field.value.trim()) {
                            field.classList.add('is-invalid');
                            valid = false;
                        } else {
                            field.classList.remove('is-invalid');
                        }
                    });
                    
                    if(!valid) {
                        event.preventDefault();
                        alert('Please fill in all required fields.');
                    }
                });
            }
        });
    </script>
</body>
</html>
?>