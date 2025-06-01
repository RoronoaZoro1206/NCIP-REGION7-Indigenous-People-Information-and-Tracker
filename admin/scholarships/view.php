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

// Check if application ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error_message'] = "No application ID provided";
    header("Location: index.php");
    exit();
}

$app_code = $_GET['id'];
$application = null;

try {
    // Comprehensive query similar to index.php
    $detailed_query = "
    SELECT 
        a.APP_CODE,
        a.APP_LAST_NAME,
        a.APP_FIRST_NAME,
        a.APP_MIDDLE_INITIAL,
        a.APP_DATE_OF_BIRTH,
        a.APP_PLACE_OF_BIRTH,
        a.APP_GENDER,
        a.APP_CIVIL_STATUS,
        a.APP_ETHNO_GROUP,
        a.APP_PHONE_NUMBER,
        a.APP_EMAIL,
        a.APP_FORM_SIGNED,
        a.APP_SIGNATURE_DATE,
        
        adm.ADMIN_CODE,
        adm.ADMIN_APP_NUMBER,
        adm.ADMIN_DOF,
        adm.ADMIN_ACTION_TAKEN_CSC_PO,
        adm.ADMIN_ACTION_TAKEN_RO,
        adm.ADMIN_CADT,
        adm.ADMIN_CONG_DISTRICT,
        adm.ADMIN_ADD_REMARKS,
        adm.ADMIN_ASSISTANCE_TYPE,
        
        addr.ADDR_MAIL,
        addr.ADDR_PERMANENT,
        addr.ADDR_HOUSE_AND_STREET,
        addr.ADDR_BARANGAY,
        addr.ADDR_MUNICIPALITY,
        addr.ADDR_PROVINCE,
        
        (SELECT string_agg(CONCAT(
            ec.EDUC_NAME, ':', 
            ' School: ', s.SCH_NAME,
            ' Address: ', s.SCH_ADDRESS, 
            ' Year: ', s.SCH_YEAR_GRAD::text, 
            ' Grade: ', s.SCH_GRADE_AVG::text, 
            ' Rank: ', s.SCH_RANK
        ), '||') 
         FROM EDUCATION_ATTAINMENT ea 
         JOIN EDUCATION_CATEGORY ec ON ea.EDUC_ID = ec.EDUC_ID 
         JOIN SCHOOL s ON ea.EDUA_CODE = s.EDUC_CODE 
         WHERE ea.APP_CODE = a.APP_CODE) as EDUCATION_DETAIL,
         
        /* Father specific fields */
        (SELECT fb.FAM_NAME FROM FAMILY_BACKGROUND fb 
         WHERE fb.APP_CODE = a.APP_CODE AND fb.FAM_RELATION = 'Father' LIMIT 1) as FATHER_NAME,
        
        (SELECT fb.FAM_IS_ALIVE FROM FAMILY_BACKGROUND fb 
         WHERE fb.APP_CODE = a.APP_CODE AND fb.FAM_RELATION = 'Father' LIMIT 1) as FATHER_IS_ALIVE,
        
        (SELECT fb.FAM_ADDR FROM FAMILY_BACKGROUND fb 
         WHERE fb.APP_CODE = a.APP_CODE AND fb.FAM_RELATION = 'Father' LIMIT 1) as FATHER_ADDRESS,
        
        (SELECT fb.FAM_OCCUP FROM FAMILY_BACKGROUND fb 
         WHERE fb.APP_CODE = a.APP_CODE AND fb.FAM_RELATION = 'Father' LIMIT 1) as FATHER_OCCUPATION,
        
        (SELECT fb.FAM_OFFICE_ADDR FROM FAMILY_BACKGROUND fb 
         WHERE fb.APP_CODE = a.APP_CODE AND fb.FAM_RELATION = 'Father' LIMIT 1) as FATHER_OFFICE_ADDR,
        
        (SELECT fb.FAM_EDUC_ATTAIN FROM FAMILY_BACKGROUND fb 
         WHERE fb.APP_CODE = a.APP_CODE AND fb.FAM_RELATION = 'Father' LIMIT 1) as FATHER_EDUCATION,
        
        (SELECT fb.FAM_ETHNO_GROUP FROM FAMILY_BACKGROUND fb 
         WHERE fb.APP_CODE = a.APP_CODE AND fb.FAM_RELATION = 'Father' LIMIT 1) as FATHER_ETHNICITY,
        
        (SELECT fb.FAM_ANNUAL_INCOME FROM FAMILY_BACKGROUND fb 
         WHERE fb.APP_CODE = a.APP_CODE AND fb.FAM_RELATION = 'Father' LIMIT 1) as FATHER_INCOME,
        
        (SELECT fb.FAM_YEAR_ITR FROM FAMILY_BACKGROUND fb 
         WHERE fb.APP_CODE = a.APP_CODE AND fb.FAM_RELATION = 'Father' LIMIT 1) as FATHER_ITR_YEAR,

        /* Mother specific fields */
        (SELECT fb.FAM_NAME FROM FAMILY_BACKGROUND fb 
         WHERE fb.APP_CODE = a.APP_CODE AND fb.FAM_RELATION = 'Mother' LIMIT 1) as MOTHER_NAME,
        
        (SELECT fb.FAM_IS_ALIVE FROM FAMILY_BACKGROUND fb 
         WHERE fb.APP_CODE = a.APP_CODE AND fb.FAM_RELATION = 'Mother' LIMIT 1) as MOTHER_IS_ALIVE,
        
        (SELECT fb.FAM_ADDR FROM FAMILY_BACKGROUND fb 
         WHERE fb.APP_CODE = a.APP_CODE AND fb.FAM_RELATION = 'Mother' LIMIT 1) as MOTHER_ADDRESS,
        
        (SELECT fb.FAM_OCCUP FROM FAMILY_BACKGROUND fb 
         WHERE fb.APP_CODE = a.APP_CODE AND fb.FAM_RELATION = 'Mother' LIMIT 1) as MOTHER_OCCUPATION,
        
        (SELECT fb.FAM_OFFICE_ADDR FROM FAMILY_BACKGROUND fb 
         WHERE fb.APP_CODE = a.APP_CODE AND fb.FAM_RELATION = 'Mother' LIMIT 1) as MOTHER_OFFICE_ADDR,
        
        (SELECT fb.FAM_EDUC_ATTAIN FROM FAMILY_BACKGROUND fb 
         WHERE fb.APP_CODE = a.APP_CODE AND fb.FAM_RELATION = 'Mother' LIMIT 1) as MOTHER_EDUCATION,
        
        (SELECT fb.FAM_ETHNO_GROUP FROM FAMILY_BACKGROUND fb 
         WHERE fb.APP_CODE = a.APP_CODE AND fb.FAM_RELATION = 'Mother' LIMIT 1) as MOTHER_ETHNICITY,
        
        (SELECT fb.FAM_ANNUAL_INCOME FROM FAMILY_BACKGROUND fb 
         WHERE fb.APP_CODE = a.APP_CODE AND fb.FAM_RELATION = 'Mother' LIMIT 1) as MOTHER_INCOME,
        
        (SELECT fb.FAM_YEAR_ITR FROM FAMILY_BACKGROUND fb 
         WHERE fb.APP_CODE = a.APP_CODE AND fb.FAM_RELATION = 'Mother' LIMIT 1) as MOTHER_ITR_YEAR,

        /* Guardian specific fields */
        (SELECT fb.FAM_NAME FROM FAMILY_BACKGROUND fb 
         WHERE fb.APP_CODE = a.APP_CODE AND fb.FAM_RELATION = 'Guardian' LIMIT 1) as GUARDIAN_NAME,
        
        (SELECT fb.FAM_IS_ALIVE FROM FAMILY_BACKGROUND fb 
         WHERE fb.APP_CODE = a.APP_CODE AND fb.FAM_RELATION = 'Guardian' LIMIT 1) as GUARDIAN_IS_ALIVE,
        
        (SELECT fb.FAM_ADDR FROM FAMILY_BACKGROUND fb 
         WHERE fb.APP_CODE = a.APP_CODE AND fb.FAM_RELATION = 'Guardian' LIMIT 1) as GUARDIAN_ADDRESS,
        
        (SELECT fb.FAM_OCCUP FROM FAMILY_BACKGROUND fb 
         WHERE fb.APP_CODE = a.APP_CODE AND fb.FAM_RELATION = 'Guardian' LIMIT 1) as GUARDIAN_OCCUPATION,
        
        (SELECT fb.FAM_OFFICE_ADDR FROM FAMILY_BACKGROUND fb 
         WHERE fb.APP_CODE = a.APP_CODE AND fb.FAM_RELATION = 'Guardian' LIMIT 1) as GUARDIAN_OFFICE_ADDR,
        
        (SELECT fb.FAM_EDUC_ATTAIN FROM FAMILY_BACKGROUND fb 
         WHERE fb.APP_CODE = a.APP_CODE AND fb.FAM_RELATION = 'Guardian' LIMIT 1) as GUARDIAN_EDUCATION,
        
        (SELECT fb.FAM_ETHNO_GROUP FROM FAMILY_BACKGROUND fb 
         WHERE fb.APP_CODE = a.APP_CODE AND fb.FAM_RELATION = 'Guardian' LIMIT 1) as GUARDIAN_ETHNICITY,
        
        (SELECT fb.FAM_ANNUAL_INCOME FROM FAMILY_BACKGROUND fb 
         WHERE fb.APP_CODE = a.APP_CODE AND fb.FAM_RELATION = 'Guardian' LIMIT 1) as GUARDIAN_INCOME,
        
        (SELECT fb.FAM_YEAR_ITR FROM FAMILY_BACKGROUND fb 
         WHERE fb.APP_CODE = a.APP_CODE AND fb.FAM_RELATION = 'Guardian' LIMIT 1) as GUARDIAN_ITR_YEAR,
         
        (SELECT string_agg(CONCAT(
            sib.SIB_LAST_NAME, ', ', sib.SIB_FIRST_NAME, ' ', sib.SIB_MIDDLE_INITIAL,
            CASE WHEN sch.SCHOLAR_NAME IS NOT NULL 
                THEN CONCAT(' (Scholarship: ', sch.SCHOLAR_NAME, 
                           ', Course: ', sch.SCHOLAR_COURSE, 
                           ', Year: ', sch.SCHOLAR_YEAR, ')')
                ELSE ''
            END
        ), '||') 
         FROM SIBLING sib 
         LEFT JOIN SCHOLARSHIP sch ON sib.SIB_CODE = sch.SIB_CODE 
         WHERE sib.APP_CODE = a.APP_CODE) as SIBLING_DETAIL,
         
        pref.PREF_NAME,
        pref.PREF_ADDR,
        pref.PREF_DEGREE,
        pref.PREF_REMARKS,
        
        (SELECT CASE WHEN COUNT(*) > 0 THEN TRUE ELSE FALSE END 
         FROM SIGNATURE_SCAN scan 
         WHERE scan.APP_CODE = a.APP_CODE) as HAS_SIGNATURE,
         
        (SELECT scan.SCAN_FILENAME 
         FROM SIGNATURE_SCAN scan 
         WHERE scan.APP_CODE = a.APP_CODE LIMIT 1) as SIGNATURE_FILENAME,
         
        (SELECT scan.SCAN_FILETYPE 
         FROM SIGNATURE_SCAN scan 
         WHERE scan.APP_CODE = a.APP_CODE LIMIT 1) as SIGNATURE_FILETYPE,
        
        (SELECT ss.STATUS_CODE FROM SCHOLARSHIP_STATUS ss 
         WHERE ss.APP_CODE = a.APP_CODE AND ss.STATUS_IS_ACTIVE = TRUE LIMIT 1) as STATUS,
         
        (SELECT ss.STATUS_DATE FROM SCHOLARSHIP_STATUS ss 
         WHERE ss.APP_CODE = a.APP_CODE AND ss.STATUS_IS_ACTIVE = TRUE LIMIT 1) as STATUS_DATE,
         
        (SELECT ss.STATUS_REMARKS FROM SCHOLARSHIP_STATUS ss 
         WHERE ss.APP_CODE = a.APP_CODE AND ss.STATUS_IS_ACTIVE = TRUE LIMIT 1) as STATUS_REMARKS
        
    FROM APPLICANT a
    LEFT JOIN ADMINISTRATOR adm ON a.ADMIN_CODE = adm.ADMIN_CODE
    LEFT JOIN ADDRESS addr ON a.APP_CODE = addr.APP_CODE
    LEFT JOIN SCHOOL_PREFERENCE pref ON a.APP_CODE = pref.APP_CODE
    WHERE a.APP_CODE = :app_code";
    
    $stmt = $pdo->prepare($detailed_query);
    $stmt->bindParam(':app_code', $app_code);
    $stmt->execute();
    
    $application = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$application) {
        $_SESSION['error_message'] = "Application not found";
        header("Location: index.php");
        exit();
    }
    
    // Process education detail
    if (!empty($application['education_detail'])) {
        $application['education_array'] = explode('||', $application['education_detail']);
    } else {
        $application['education_array'] = [];
    }
    
    // Process family detail
    if (!empty($application['sibling_detail'])) {
        $application['sibling_array'] = explode('||', $application['sibling_detail']);
    } else {
        $application['sibling_array'] = [];
    }
    
    // Get education by level
    $application['elementary'] = '';
    $application['high_school'] = '';
    $application['vocational'] = '';
    $application['college'] = '';
    
    if (!empty($application['education_array'])) {
        foreach ($application['education_array'] as $edu) {
            if (strpos($edu, 'Elementary:') === 0) {
                $application['elementary'] = str_replace('Elementary:', '', $edu);
            } elseif (strpos($edu, 'High School:') === 0) {
                $application['high_school'] = str_replace('High School:', '', $edu);
            } elseif (strpos($edu, 'Vocational:') === 0) {
                $application['vocational'] = str_replace('Vocational:', '', $edu);
            } elseif (strpos($edu, 'College/Undergraduate:') === 0) {
                $application['college'] = str_replace('College/Undergraduate:', '', $edu);
            }
        }
    }
    
    // Fetch supporting documents if available
    $doc_query = "SELECT * FROM SIGNATURE_SCAN WHERE APP_CODE = :app_code";
    $doc_stmt = $pdo->prepare($doc_query);
    $doc_stmt->bindParam(':app_code', $app_code);
    $doc_stmt->execute();
    $applicant_documents = $doc_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // If application status is pending, mark it as being reviewed
    if (empty($application['status']) || $application['status'] == 'PENDING') {
        $update_stmt = $pdo->prepare("
            UPDATE SCHOLARSHIP_STATUS 
            SET STATUS_IS_ACTIVE = FALSE 
            WHERE APP_CODE = :app_code AND STATUS_IS_ACTIVE = TRUE
        ");
        $update_stmt->bindParam(':app_code', $app_code);
        $update_stmt->execute();
        
        // Insert new status as Under Review
        $insert_stmt = $pdo->prepare("
            INSERT INTO SCHOLARSHIP_STATUS 
            (APP_CODE, STATUS_CODE, STATUS_DATE, STATUS_IS_ACTIVE, STATUS_REMARKS) 
            VALUES (:app_code, 'UNDER REVIEW', CURRENT_TIMESTAMP, TRUE, 'Auto-updated when viewed by admin')
        ");
        $insert_stmt->bindParam(':app_code', $app_code);
        $insert_stmt->execute();
        
        $application['status'] = 'UNDER REVIEW';
        $application['status_date'] = date('Y-m-d H:i:s');
    }
    
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Database error: " . $e->getMessage();
    header("Location: index.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Application - NCIP Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .main-content {
            margin-left: 250px;
            padding: 20px;
        }
        .card-dashboard {
            border-radius: 10px;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            margin-bottom: 20px;
        }
        .application-header {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border-left: 5px solid #0d6efd;
        }
        .status-badge {
            font-size: 1rem;
            padding: 5px 10px;
        }
        .document-card {
            transition: transform 0.2s;
        }
        .document-card:hover {
            transform: translateY(-5px);
        }
        .section-title {
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 10px;
            margin-bottom: 20px;
            color: #333;
        }
        .field-label {
            font-weight: bold;
            color: #555;
        }
        .sub-section {
            border-top: 1px solid #eee;
            margin-top: 15px;
            padding-top: 15px;
        }
        .family-member-header {
            background-color: #f0f7ff;
            padding: 8px 15px;
            font-weight: bold;
            border-left: 4px solid #007bff;
            margin-bottom: 15px;
        }
        .signature-display {
            max-width: 250px;
            border: 1px solid #dee2e6;
            padding: 10px;
            background: white;
        }
    </style>
</head>

<body>
    <!-- Sidebar -->
    <?php include '../includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="../index.php">Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="index.php">Scholarship Applications</a></li>
                            <li class="breadcrumb-item active" aria-current="page">View Application</li>
                        </ol>
                    </nav>
                    <h1>Application Details</h1>
                </div>
                <div class="btn-group" role="group">
                    <a href="edit.php?id=<?php echo $app_code; ?>" class="btn btn-primary">
                        <i class="bi bi-pencil-square"></i> Edit
                    </a>
                    <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#statusModal">
                        <i class="bi bi-check2-circle"></i> Update Status
                    </button>
                    <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal">
                        <i class="bi bi-trash"></i> Delete
                    </button>
                    <a href="index.php" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i> Back to List
                    </a>
                </div>
            </div>

            <!-- Alert Container -->
            <div id="alert-container">
                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $_SESSION['success_message']; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php unset($_SESSION['success_message']); ?>
                <?php endif; ?>

                <?php if (isset($_SESSION['error_message'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $_SESSION['error_message']; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php unset($_SESSION['error_message']); ?>
                <?php endif; ?>
            </div>

            <div class="application-header d-flex justify-content-between align-items-center">
                <div>
                    <h3 class="mb-0"><?php echo htmlspecialchars($application['app_first_name'] . ' ' . 
                        $application['app_middle_initial'] . ' ' . $application['app_last_name']); ?></h3>
                    <p class="mb-0 text-muted">Application #<?php echo $application['app_code']; ?> | 
                        App Number: <?php echo htmlspecialchars($application['admin_app_number'] ?? 'N/A'); ?> |
                        Filed: <?php echo $application['admin_dof'] ? date('F d, Y', strtotime($application['admin_dof'])) : 'N/A'; ?>
                    </p>
                </div>
                <div>
                    <?php
                    $status_class = '';
                    switch(strtoupper($application['status'] ?? 'PENDING')) {
                        case 'PENDING':
                            $status_class = 'bg-warning';
                            break;
                        case 'UNDER REVIEW':
                            $status_class = 'bg-primary';
                            break;
                        case 'APPROVED':
                            $status_class = 'bg-success';
                            break;
                        case 'REJECTED':
                            $status_class = 'bg-danger';
                            break;
                        default:
                            $status_class = 'bg-secondary';
                    }
                    ?>
                    <span class="badge <?php echo $status_class; ?> status-badge">
                        <?php echo htmlspecialchars($application['status'] ?? 'PENDING'); ?>
                    </span>
                </div>
            </div>

            <div class="row">
                <!-- Left Column: Main Application Details -->
                <div class="col-md-8">
                    <!-- Administrative Information -->
                    <div class="card card-dashboard mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">Administrative Information</h5>
                        </div>
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <p class="field-label">Application Number</p>
                                    <p><?php echo htmlspecialchars($application['admin_app_number'] ?? 'N/A'); ?></p>
                                </div>
                                <div class="col-md-6">
                                    <p class="field-label">Date Filed</p>
                                    <p><?php echo $application['admin_dof'] ? date('F d, Y', strtotime($application['admin_dof'])) : 'N/A'; ?></p>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <p class="field-label">CSC/PO Action</p>
                                    <p><?php echo htmlspecialchars($application['admin_action_taken_csc_po'] ?? 'N/A'); ?></p>
                                </div>
                                <div class="col-md-6">
                                    <p class="field-label">RO Action</p>
                                    <p><?php echo htmlspecialchars($application['admin_action_taken_ro'] ?? 'N/A'); ?></p>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <p class="field-label">CADT</p>
                                    <p><?php echo htmlspecialchars($application['admin_cadt'] ?? 'N/A'); ?></p>
                                </div>
                                <div class="col-md-6">
                                    <p class="field-label">Congressional District</p>
                                    <p><?php echo htmlspecialchars($application['admin_cong_district'] ?? 'N/A'); ?></p>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-12">
                                    <p class="field-label">Assistance Type</p>
                                    <p><?php echo htmlspecialchars($application['admin_assistance_type'] ?? 'N/A'); ?></p>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-12">
                                    <p class="field-label">Remarks</p>
                                    <p><?php echo nl2br(htmlspecialchars($application['admin_add_remarks'] ?? 'N/A')); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Applicant Information -->
                    <div class="card card-dashboard mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">Personal Information</h5>
                        </div>
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <p class="field-label">Last Name</p>
                                    <p><?php echo htmlspecialchars($application['app_last_name']); ?></p>
                                </div>
                                <div class="col-md-4">
                                    <p class="field-label">First Name</p>
                                    <p><?php echo htmlspecialchars($application['app_first_name']); ?></p>
                                </div>
                                <div class="col-md-4">
                                    <p class="field-label">Middle Initial</p>
                                    <p><?php echo htmlspecialchars($application['app_middle_initial'] ?? 'N/A'); ?></p>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <p class="field-label">Date of Birth</p>
                                    <p><?php echo date('F d, Y', strtotime($application['app_date_of_birth'])); ?></p>
                                </div>
                                <div class="col-md-6">
                                    <p class="field-label">Place of Birth</p>
                                    <p><?php echo htmlspecialchars($application['app_place_of_birth']); ?></p>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <p class="field-label">Gender</p>
                                    <p><?php echo htmlspecialchars($application['app_gender']); ?></p>
                                </div>
                                <div class="col-md-4">
                                    <p class="field-label">Civil Status</p>
                                    <p><?php echo htmlspecialchars($application['app_civil_status']); ?></p>
                                </div>
                                <div class="col-md-4">
                                    <p class="field-label">Ethnolinguistic Group</p>
                                    <p><?php echo htmlspecialchars($application['app_ethno_group']); ?></p>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <p class="field-label">Phone Number</p>
                                    <p><?php echo htmlspecialchars($application['app_phone_number'] ?? 'N/A'); ?></p>
                                </div>
                                <div class="col-md-6">
                                    <p class="field-label">Email</p>
                                    <p><?php echo htmlspecialchars($application['app_email'] ?? 'N/A'); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Address Information -->
                    <div class="card card-dashboard mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">Address Information</h5>
                        </div>
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <p class="field-label">Mailing Address</p>
                                    <p><?php echo htmlspecialchars($application['addr_mail'] ?? 'N/A'); ?></p>
                                </div>
                                <div class="col-md-6">
                                    <p class="field-label">Permanent Address</p>
                                    <p><?php echo htmlspecialchars($application['addr_permanent'] ?? 'N/A'); ?></p>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-12">
                                    <p class="field-label">House/Street</p>
                                    <p><?php echo htmlspecialchars($application['addr_house_and_street'] ?? 'N/A'); ?></p>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-4">
                                    <p class="field-label">Barangay</p>
                                    <p><?php echo htmlspecialchars($application['addr_barangay'] ?? 'N/A'); ?></p>
                                </div>
                                <div class="col-md-4">
                                    <p class="field-label">Municipality</p>
                                    <p><?php echo htmlspecialchars($application['addr_municipality'] ?? 'N/A'); ?></p>
                                </div>
                                <div class="col-md-4">
                                    <p class="field-label">Province</p>
                                    <p><?php echo htmlspecialchars($application['addr_province'] ?? 'N/A'); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Educational Background -->
                    <div class="card card-dashboard mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">Educational Background</h5>
                        </div>
                        <div class="card-body">
                            <!-- Elementary -->
                            <div class="mb-4">
                                <h6 class="text-primary mb-3">Elementary Education</h6>
                                <p><?php echo !empty($application['elementary']) ? htmlspecialchars($application['elementary']) : 'No elementary education data available.'; ?></p>
                            </div>
                            
                            <!-- High School -->
                            <div class="mb-4">
                                <h6 class="text-primary mb-3">High School Education</h6>
                                <p><?php echo !empty($application['high_school']) ? htmlspecialchars($application['high_school']) : 'No high school education data available.'; ?></p>
                            </div>
                            
                            <!-- Vocational -->
                            <div class="mb-4">
                                <h6 class="text-primary mb-3">Vocational Education</h6>
                                <p><?php echo !empty($application['vocational']) ? htmlspecialchars($application['vocational']) : 'No vocational education data available.'; ?></p>
                            </div>
                            
                            <!-- College -->
                            <div>
                                <h6 class="text-primary mb-3">College/Undergraduate Education</h6>
                                <p><?php echo !empty($application['college']) ? htmlspecialchars($application['college']) : 'No college education data available.'; ?></p>
                            </div>
                        </div>
                    </div>

                    <!-- Family Background -->
                    <div class="card card-dashboard mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">Family Background</h5>
                        </div>
                        <div class="card-body">
                            <!-- Father Information -->
                            <div class="mb-4">
                                <div class="family-member-header">Father's Information</div>
                                <div class="row mb-3">
                                    <div class="col-md-8">
                                        <p class="field-label">Name</p>
                                        <p><?php echo htmlspecialchars($application['father_name'] ?? 'N/A'); ?></p>
                                    </div>
                                    <div class="col-md-4">
                                        <p class="field-label">Status</p>
                                        <p><?php echo isset($application['father_is_alive']) ? ($application['father_is_alive'] ? 'Living' : 'Deceased') : 'N/A'; ?></p>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-md-12">
                                        <p class="field-label">Address</p>
                                        <p><?php echo htmlspecialchars($application['father_address'] ?? 'N/A'); ?></p>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <p class="field-label">Occupation</p>
                                        <p><?php echo htmlspecialchars($application['father_occupation'] ?? 'N/A'); ?></p>
                                    </div>
                                    <div class="col-md-6">
                                        <p class="field-label">Office Address</p>
                                        <p><?php echo htmlspecialchars($application['father_office_addr'] ?? 'N/A'); ?></p>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-4">
                                        <p class="field-label">Education</p>
                                        <p><?php echo htmlspecialchars($application['father_education'] ?? 'N/A'); ?></p>
                                    </div>
                                    <div class="col-md-4">
                                        <p class="field-label">Ethnic Group</p>
                                        <p><?php echo htmlspecialchars($application['father_ethnicity'] ?? 'N/A'); ?></p>
                                    </div>
                                    <div class="col-md-2">
                                        <p class="field-label">Annual Income</p>
                                        <p><?php echo isset($application['father_income']) ? '₱' . number_format($application['father_income'], 2) : 'N/A'; ?></p>
                                    </div>
                                    <div class="col-md-2">
                                        <p class="field-label">ITR Year</p>
                                        <p><?php echo htmlspecialchars($application['father_itr_year'] ?? 'N/A'); ?></p>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Mother Information -->
                            <div class="mb-4">
                                <div class="family-member-header">Mother's Information</div>
                                <div class="row mb-3">
                                    <div class="col-md-8">
                                        <p class="field-label">Name</p>
                                        <p><?php echo htmlspecialchars($application['mother_name'] ?? 'N/A'); ?></p>
                                    </div>
                                    <div class="col-md-4">
                                        <p class="field-label">Status</p>
                                        <p><?php echo isset($application['mother_is_alive']) ? ($application['mother_is_alive'] ? 'Living' : 'Deceased') : 'N/A'; ?></p>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-md-12">
                                        <p class="field-label">Address</p>
                                        <p><?php echo htmlspecialchars($application['mother_address'] ?? 'N/A'); ?></p>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <p class="field-label">Occupation</p>
                                        <p><?php echo htmlspecialchars($application['mother_occupation'] ?? 'N/A'); ?></p>
                                    </div>
                                    <div class="col-md-6">
                                        <p class="field-label">Office Address</p>
                                        <p><?php echo htmlspecialchars($application['mother_office_addr'] ?? 'N/A'); ?></p>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-4">
                                        <p class="field-label">Education</p>
                                        <p><?php echo htmlspecialchars($application['mother_education'] ?? 'N/A'); ?></p>
                                    </div>
                                    <div class="col-md-4">
                                        <p class="field-label">Ethnic Group</p>
                                        <p><?php echo htmlspecialchars($application['mother_ethnicity'] ?? 'N/A'); ?></p>
                                    </div>
                                    <div class="col-md-2">
                                        <p class="field-label">Annual Income</p>
                                        <p><?php echo isset($application['mother_income']) ? '₱' . number_format($application['mother_income'], 2) : 'N/A'; ?></p>
                                    </div>
                                    <div class="col-md-2">
                                        <p class="field-label">ITR Year</p>
                                        <p><?php echo htmlspecialchars($application['mother_itr_year'] ?? 'N/A'); ?></p>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Guardian Information -->
                            <div>
                                <div class="family-member-header">Guardian's Information</div>
                                <div class="row mb-3">
                                    <div class="col-md-8">
                                        <p class="field-label">Name</p>
                                        <p><?php echo htmlspecialchars($application['guardian_name'] ?? 'N/A'); ?></p>
                                    </div>
                                    <div class="col-md-4">
                                        <p class="field-label">Status</p>
                                        <p><?php echo isset($application['guardian_is_alive']) ? ($application['guardian_is_alive'] ? 'Living' : 'Deceased') : 'N/A'; ?></p>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-md-12">
                                        <p class="field-label">Address</p>
                                        <p><?php echo htmlspecialchars($application['guardian_address'] ?? 'N/A'); ?></p>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <p class="field-label">Occupation</p>
                                        <p><?php echo htmlspecialchars($application['guardian_occupation'] ?? 'N/A'); ?></p>
                                    </div>
                                    <div class="col-md-6">
                                        <p class="field-label">Office Address</p>
                                        <p><?php echo htmlspecialchars($application['guardian_office_addr'] ?? 'N/A'); ?></p>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-4">
                                        <p class="field-label">Education</p>
                                        <p><?php echo htmlspecialchars($application['guardian_education'] ?? 'N/A'); ?></p>
                                    </div>
                                    <div class="col-md-4">
                                        <p class="field-label">Ethnic Group</p>
                                        <p><?php echo htmlspecialchars($application['guardian_ethnicity'] ?? 'N/A'); ?></p>
                                    </div>
                                    <div class="col-md-2">
                                        <p class="field-label">Annual Income</p>
                                        <p><?php echo isset($application['guardian_income']) ? '₱' . number_format($application['guardian_income'], 2) : 'N/A'; ?></p>
                                    </div>
                                    <div class="col-md-2">
                                        <p class="field-label">ITR Year</p>
                                        <p><?php echo htmlspecialchars($application['guardian_itr_year'] ?? 'N/A'); ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Siblings Information -->
                    <div class="card card-dashboard mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">Siblings</h5>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($application['sibling_array'])): ?>
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Name</th>
                                                <th>Scholarship</th>
                                                <th>Course</th>
                                                <th>Year</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($application['sibling_array'] as $sibling): ?>
                                                <tr>
                                                    <?php
                                                    $parts = explode('(Scholarship:', $sibling);
                                                    $name = trim($parts[0]);
                                                    
                                                    $scholarship = '';
                                                    $course = '';
                                                    $year = '';
                                                    
                                                    if (count($parts) > 1) {
                                                        preg_match('/Scholarship: ([^,]+)/', $sibling, $scholarship_match);
                                                        preg_match('/Course: ([^,]+)/', $sibling, $course_match);
                                                        preg_match('/Year: ([^\)]+)/', $sibling, $year_match);
                                                        
                                                        $scholarship = $scholarship_match[1] ?? '';
                                                        $course = $course_match[1] ?? '';
                                                        $year = $year_match[1] ?? '';
                                                    }
                                                    ?>
                                                    <td><?php echo htmlspecialchars($name); ?></td>
                                                    <td><?php echo htmlspecialchars($scholarship); ?></td>
                                                    <td><?php echo htmlspecialchars($course); ?></td>
                                                    <td><?php echo htmlspecialchars($year); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <p>No siblings information available.</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- School Preference -->
                    <div class="card card-dashboard mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">School Preference</h5>
                        </div>
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-md-12">
                                    <p class="field-label">School Name</p>
                                    <p><?php echo htmlspecialchars($application['pref_name'] ?? 'N/A'); ?></p>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-12">
                                    <p class="field-label">School Address</p>
                                    <p><?php echo htmlspecialchars($application['pref_addr'] ?? 'N/A'); ?></p>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-12">
                                    <p class="field-label">Degree/Program</p>
                                    <p><?php echo htmlspecialchars($application['pref_degree'] ?? 'N/A'); ?></p>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-12">
                                    <p class="field-label">Remarks</p>
                                    <p><?php echo nl2br(htmlspecialchars($application['pref_remarks'] ?? 'N/A')); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Sidebar Information -->
                <div class="col-md-4">
                    <!-- Status Information -->
                    <div class="card card-dashboard mb-4">
                        <div class="card-header bg-light">
                            <h5 class="mb-0">Status Information</h5>
                        </div>
                        <div class="card-body">
                            <p><strong>Current Status:</strong> 
                                <span class="badge <?php echo $status_class; ?>">
                                    <?php echo htmlspecialchars($application['status'] ?? 'PENDING'); ?>
                                </span>
                            </p>
                            <p><strong>Status Date:</strong> 
                                <?php echo $application['status_date'] ? date('F d, Y h:i A', strtotime($application['status_date'])) : 'N/A'; ?>
                            </p>
                            <?php if (!empty($application['status_remarks'])): ?>
                                <p><strong>Status Remarks:</strong></p>
                                <p><?php echo nl2br(htmlspecialchars($application['status_remarks'])); ?></p>
                            <?php endif; ?>
                            
                            <div class="d-grid gap-2 mt-3">
                                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#statusModal">
                                    <i class="bi bi-pencil-square"></i> Update Status
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Signature -->
                    <div class="card card-dashboard mb-4">
                        <div class="card-header bg-light">
                            <h5 class="mb-0">Certification</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <p><strong>Form Signed:</strong> <?php echo $application['app_form_signed'] ? 'Yes' : 'No'; ?></p>
                                <p><strong>Signature Date:</strong> 
                                    <?php echo $application['app_signature_date'] ? date('F d, Y', strtotime($application['app_signature_date'])) : 'N/A'; ?>
                                </p>
                            </div>
                            
                            <?php if ($application['has_signature']): ?>
                                <p><strong>E-Signature:</strong></p>
                                <div class="text-center mt-3 mb-3">
                                    <div class="signature-display mx-auto">
                                        <img src="get_signature.php?id=<?php echo $app_code; ?>" class="img-fluid" alt="Applicant's Signature">
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-warning">No electronic signature on file.</div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Admin Notes -->
                    <div class="card card-dashboard mb-4">
                        <div class="card-header bg-light">
                            <h5 class="mb-0">Admin Notes</h5>
                        </div>
                        <div class="card-body">
                            <form id="notes-form">
                                <div class="mb-3">
                                    <textarea class="form-control" id="admin_notes" rows="5" placeholder="Add notes about this application here..."><?php echo htmlspecialchars($application['admin_add_remarks'] ?? ''); ?></textarea>
                                </div>
                                <div class="d-grid">
                                    <button type="button" id="save-notes" class="btn btn-primary">
                                        <i class="bi bi-save"></i> Save Notes
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="card card-dashboard mb-4">
                        <div class="card-header bg-light">
                            <h5 class="mb-0">Actions</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <a href="edit.php?id=<?php echo $app_code; ?>" class="btn btn-warning">
                                    <i class="bi bi-pencil-square"></i> Edit Application
                                </a>
                                <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal">
                                    <i class="bi bi-trash"></i> Delete Application
                                </button>
                                <a href="print.php?id=<?php echo $app_code; ?>" class="btn btn-info" target="_blank">
                                    <i class="bi bi-printer"></i> Print Application
                                </a>
                                <a href="index.php" class="btn btn-secondary">
                                    <i class="bi bi-arrow-left"></i> Back to List
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Status Update Modal -->
    <div class="modal fade" id="statusModal" tabindex="-1" aria-labelledby="statusModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="statusModalLabel">Update Application Status</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="ajax/update_status.php" method="post" id="status-form">
                    <div class="modal-body">
                        <input type="hidden" name="app_code" value="<?php echo $app_code; ?>">
                        <div class="mb-3">
                            <label for="new_status" class="form-label">Select New Status</label>
                            <select class="form-select" id="new_status" name="new_status" required>
                                <option value="">Select Status</option>
                                <option value="PENDING" <?php echo ($application['status'] ?? '') == 'PENDING' ? 'selected' : ''; ?>>Pending</option>
                                <option value="UNDER REVIEW" <?php echo ($application['status'] ?? '') == 'UNDER REVIEW' ? 'selected' : ''; ?>>Under Review</option>
                                <option value="APPROVED" <?php echo ($application['status'] ?? '') == 'APPROVED' ? 'selected' : ''; ?>>Approved</option>
                                <option value="REJECTED" <?php echo ($application['status'] ?? '') == 'REJECTED' ? 'selected' : ''; ?>>Rejected</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="status_remarks" class="form-label">Status Remarks (Optional)</label>
                            <textarea class="form-control" id="status_remarks" name="status_remarks" rows="3"><?php echo htmlspecialchars($application['status_remarks'] ?? ''); ?></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Status</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteModalLabel">Confirm Deletion</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete this application from <strong><?php echo htmlspecialchars($application['app_first_name'] . ' ' . $application['app_last_name']); ?></strong>?</p>
                    <p class="text-danger"><strong>Warning:</strong> This action cannot be undone. All associated data will be permanently deleted.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form action="ajax/delete_application.php" method="post" id="delete-form">
                        <input type="hidden" name="app_code" value="<?php echo $app_code; ?>">
                        <button type="submit" class="btn btn-danger">Delete Permanently</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Toggle sidebar on mobile
            const sidebarToggle = document.getElementById('sidebarToggle');
            if (sidebarToggle) {
                sidebarToggle.addEventListener('click', function() {
                    document.querySelector('.sidebar').classList.toggle('show-mobile');
                });
            }
            
            // Auto-dismiss alerts after 5 seconds
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                setTimeout(function() {
                    const closeButton = alert.querySelector('.btn-close');
                    if (closeButton) {
                        closeButton.click();
                    }
                }, 5000);
            });
            
            // Handle saving admin notes via AJAX
            document.getElementById('save-notes').addEventListener('click', function() {
                const notesContent = document.getElementById('admin_notes').value;
                
                fetch('ajax/update_notes.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `app_code=${<?php echo $app_code; ?>}&notes=${encodeURIComponent(notesContent)}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showAlert('success', 'Admin notes saved successfully');
                    } else {
                        showAlert('danger', data.message || 'Failed to save notes');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showAlert('danger', 'An error occurred while saving notes');
                });
            });
            
            // Handle status update form submission via AJAX
            document.getElementById('status-form').addEventListener('submit', function(e) {
                e.preventDefault();
                
                const formData = new FormData(this);
                
                fetch('ajax/update_status.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Close modal
                        bootstrap.Modal.getInstance(document.getElementById('statusModal')).hide();
                        
                        // Update status display
                        const statusBadge = document.querySelector('.status-badge');
                        statusBadge.textContent = data.new_status;
                        
                        // Update badge color
                        statusBadge.className = 'badge status-badge';
                        switch (data.new_status.toUpperCase()) {
                            case 'PENDING':
                                statusBadge.classList.add('bg-warning');
                                break;
                            case 'UNDER REVIEW':
                                statusBadge.classList.add('bg-primary');
                                break;
                            case 'APPROVED':
                                statusBadge.classList.add('bg-success');
                                break;
                            case 'REJECTED':
                                statusBadge.classList.add('bg-danger');
                                break;
                            default:
                                statusBadge.classList.add('bg-secondary');
                        }
                        
                        showAlert('success', 'Application status updated successfully');
                    } else {
                        showAlert('danger', data.message || 'Failed to update status');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showAlert('danger', 'An error occurred while updating status');
                });
            });
            
            // Handle delete form submission via AJAX
            document.getElementById('delete-form').addEventListener('submit', function(e) {
                e.preventDefault();
                
                const formData = new FormData(this);
                
                fetch('ajax/delete_application.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showAlert('success', 'Application deleted successfully');
                        // Redirect back to list after short delay
                        setTimeout(() => {
                            window.location.href = 'index.php';
                        }, 2000);
                    } else {
                        bootstrap.Modal.getInstance(document.getElementById('deleteModal')).hide();
                        showAlert('danger', data.message || 'Failed to delete application');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    bootstrap.Modal.getInstance(document.getElementById('deleteModal')).hide();
                    showAlert('danger', 'An error occurred while deleting application');
                });
            });
            
            // Helper function to show alerts
            function showAlert(type, message) {
                const alertContainer = document.getElementById('alert-container');
                const alertHTML = `
                    <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                        ${message}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                `;
                
                alertContainer.innerHTML = alertHTML + alertContainer.innerHTML;
                
                // Auto dismiss after 5 seconds
                const newAlert = alertContainer.querySelector('.alert');
                setTimeout(() => {
                    if (newAlert) {
                        const bsAlert = new bootstrap.Alert(newAlert);
                        bsAlert.close();
                    }
                }, 5000);
            }
        });
    </script>
</body>
</html>