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
        a.app_code,
        a.app_last_name,
        a.app_first_name,
        a.app_middle_initial,
        a.app_date_of_birth,
        a.app_place_of_birth,
        a.app_gender,
        a.app_civil_status,
        a.app_ethno_group,
        a.app_phone_number,
        a.app_email,
        a.app_form_signed,
        a.app_signature_date,
        
        adm.admin_code,
        adm.admin_app_number,
        adm.admin_dof,
        adm.admin_action_taken_csc_po,
        adm.admin_action_taken_ro,
        adm.admin_cadt,
        adm.admin_cong_district,
        adm.admin_add_remarks,
        adm.admin_assistance_type,
        
        addr.addr_mail,
        addr.addr_permanent,
        addr.addr_house_and_street,
        addr.addr_barangay,
        addr.addr_municipality,
        addr.addr_province,
        
        (SELECT CONCAT(
            'School: ', COALESCE(s.sch_name, 'N/A'),
            ' Address: ', COALESCE(s.sch_address, 'N/A'), 
            ' Year: ', COALESCE(s.sch_year_grad::text, 'N/A'), 
            ' Grade: ', COALESCE(s.sch_grade_avg::text, 'N/A'), 
            ' Rank: ', COALESCE(s.sch_rank, 'N/A')
        ) 
        FROM education_attainment ea 
        JOIN education_category ec ON ea.educ_id = ec.educ_id 
        LEFT JOIN school s ON ea.edua_code = s.edua_code
        WHERE ea.app_code = a.app_code AND ec.educ_name = 'Elementary' LIMIT 1) as elementary_education,

        (SELECT CONCAT(
            'School: ', COALESCE(s.sch_name, 'N/A'),
            ' Address: ', COALESCE(s.sch_address, 'N/A'), 
            ' Year: ', COALESCE(s.sch_year_grad::text, 'N/A'), 
            ' Grade: ', COALESCE(s.sch_grade_avg::text, 'N/A'), 
            ' Rank: ', COALESCE(s.sch_rank, 'N/A')
        ) 
        FROM education_attainment ea 
        JOIN education_category ec ON ea.educ_id = ec.educ_id 
        LEFT JOIN school s ON ea.edua_code = s.edua_code
        WHERE ea.app_code = a.app_code AND ec.educ_name = 'High School' LIMIT 1) as high_school_education,

        (SELECT CONCAT(
            'School: ', COALESCE(s.sch_name, 'N/A'),
            ' Address: ', COALESCE(s.sch_address, 'N/A'), 
            ' Year: ', COALESCE(s.sch_year_grad::text, 'N/A'), 
            ' Grade: ', COALESCE(s.sch_grade_avg::text, 'N/A'), 
            ' Rank: ', COALESCE(s.sch_rank, 'N/A')
        ) 
        FROM education_attainment ea 
        JOIN education_category ec ON ea.educ_id = ec.educ_id 
        LEFT JOIN school s ON ea.edua_code = s.edua_code
        WHERE ea.app_code = a.app_code AND ec.educ_name = 'Vocational' LIMIT 1) as vocational_education,

        (SELECT CONCAT(
            'School: ', COALESCE(s.sch_name, 'N/A'),
            ' Address: ', COALESCE(s.sch_address, 'N/A'), 
            ' Year: ', COALESCE(s.sch_year_grad::text, 'N/A'), 
            ' Grade: ', COALESCE(s.sch_grade_avg::text, 'N/A'), 
            ' Rank: ', COALESCE(s.sch_rank, 'N/A')
        ) 
        FROM education_attainment ea 
        JOIN education_category ec ON ea.educ_id = ec.educ_id 
        LEFT JOIN school s ON ea.edua_code = s.edua_code
        WHERE ea.app_code = a.app_code AND ec.educ_name = 'College/Undergraduate' LIMIT 1) as college_education,

        (SELECT CONCAT(
            'School: ', COALESCE(s.sch_name, 'N/A'),
            ' Address: ', COALESCE(s.sch_address, 'N/A'), 
            ' Year: ', COALESCE(s.sch_year_grad::text, 'N/A'), 
            ' Grade: ', COALESCE(s.sch_grade_avg::text, 'N/A'), 
            ' Rank: ', COALESCE(s.sch_rank, 'N/A')
        ) 
        FROM education_attainment ea 
        JOIN education_category ec ON ea.educ_id = ec.educ_id 
        LEFT JOIN school s ON ea.edua_code = s.edua_code
        WHERE ea.app_code = a.app_code AND ec.educ_name = 'Post-Graduate' LIMIT 1) as post_graduate_education,

        (SELECT CONCAT(
            'School: ', COALESCE(s.sch_name, 'N/A'),
            ' Address: ', COALESCE(s.sch_address, 'N/A'), 
            ' Year: ', COALESCE(s.sch_year_grad::text, 'N/A'), 
            ' Grade: ', COALESCE(s.sch_grade_avg::text, 'N/A'), 
            ' Rank: ', COALESCE(s.sch_rank, 'N/A')
        ) 
        FROM education_attainment ea 
        JOIN education_category ec ON ea.educ_id = ec.educ_id 
        LEFT JOIN school s ON ea.edua_code = s.edua_code
        WHERE ea.app_code = a.app_code AND ec.educ_name = 'Masteral' LIMIT 1) as masteral_education,

        (SELECT CONCAT(
            'School: ', COALESCE(s.sch_name, 'N/A'),
            ' Address: ', COALESCE(s.sch_address, 'N/A'), 
            ' Year: ', COALESCE(s.sch_year_grad::text, 'N/A'), 
            ' Grade: ', COALESCE(s.sch_grade_avg::text, 'N/A'), 
            ' Rank: ', COALESCE(s.sch_rank, 'N/A')
        ) 
        FROM education_attainment ea 
        JOIN education_category ec ON ea.educ_id = ec.educ_id 
        LEFT JOIN school s ON ea.edua_code = s.edua_code
        WHERE ea.app_code = a.app_code AND ec.educ_name = 'Doctorate' LIMIT 1) as doctorate_education,
         
        /* Father specific fields */
        (SELECT fb.fam_name FROM family_background fb 
         WHERE fb.app_code = a.app_code AND fb.fam_relation = 'Father' LIMIT 1) as father_name,
        
        (SELECT fb.fam_is_alive FROM family_background fb 
         WHERE fb.app_code = a.app_code AND fb.fam_relation = 'Father' LIMIT 1) as father_is_alive,
        
        (SELECT fb.fam_addr FROM family_background fb 
         WHERE fb.app_code = a.app_code AND fb.fam_relation = 'Father' LIMIT 1) as father_address,
        
        (SELECT fb.fam_occup FROM family_background fb 
         WHERE fb.app_code = a.app_code AND fb.fam_relation = 'Father' LIMIT 1) as father_occupation,
        
        (SELECT fb.fam_office_addr FROM family_background fb 
         WHERE fb.app_code = a.app_code AND fb.fam_relation = 'Father' LIMIT 1) as father_office_addr,
        
        (SELECT fb.fam_educ_attain FROM family_background fb 
         WHERE fb.app_code = a.app_code AND fb.fam_relation = 'Father' LIMIT 1) as father_education,
        
        (SELECT fb.fam_ethno_group FROM family_background fb 
         WHERE fb.app_code = a.app_code AND fb.fam_relation = 'Father' LIMIT 1) as father_ethnicity,
        
        (SELECT fb.fam_annual_income FROM family_background fb 
         WHERE fb.app_code = a.app_code AND fb.fam_relation = 'Father' LIMIT 1) as father_income,
        
        (SELECT fb.fam_year_itr FROM family_background fb 
         WHERE fb.app_code = a.app_code AND fb.fam_relation = 'Father' LIMIT 1) as father_itr_year,

        /* Mother specific fields */
        (SELECT fb.fam_name FROM family_background fb 
         WHERE fb.app_code = a.app_code AND fb.fam_relation = 'Mother' LIMIT 1) as mother_name,
        
        (SELECT fb.fam_is_alive FROM family_background fb 
         WHERE fb.app_code = a.app_code AND fb.fam_relation = 'Mother' LIMIT 1) as mother_is_alive,
        
        (SELECT fb.fam_addr FROM family_background fb 
         WHERE fb.app_code = a.app_code AND fb.fam_relation = 'Mother' LIMIT 1) as mother_address,
        
        (SELECT fb.fam_occup FROM family_background fb 
         WHERE fb.app_code = a.app_code AND fb.fam_relation = 'Mother' LIMIT 1) as mother_occupation,
        
        (SELECT fb.fam_office_addr FROM family_background fb 
         WHERE fb.app_code = a.app_code AND fb.fam_relation = 'Mother' LIMIT 1) as mother_office_addr,
        
        (SELECT fb.fam_educ_attain FROM family_background fb 
         WHERE fb.app_code = a.app_code AND fb.fam_relation = 'Mother' LIMIT 1) as mother_education,
        
        (SELECT fb.fam_ethno_group FROM family_background fb 
         WHERE fb.app_code = a.app_code AND fb.fam_relation = 'Mother' LIMIT 1) as mother_ethnicity,
        
        (SELECT fb.fam_annual_income FROM family_background fb 
         WHERE fb.app_code = a.app_code AND fb.fam_relation = 'Mother' LIMIT 1) as mother_income,
        
        (SELECT fb.fam_year_itr FROM family_background fb 
         WHERE fb.app_code = a.app_code AND fb.fam_relation = 'Mother' LIMIT 1) as mother_itr_year,

        /* Guardian specific fields */
        (SELECT fb.fam_name FROM family_background fb 
         WHERE fb.app_code = a.app_code AND fb.fam_relation = 'Guardian' LIMIT 1) as guardian_name,
        
        (SELECT fb.fam_is_alive FROM family_background fb 
         WHERE fb.app_code = a.app_code AND fb.fam_relation = 'Guardian' LIMIT 1) as guardian_is_alive,
        
        (SELECT fb.fam_addr FROM family_background fb 
         WHERE fb.app_code = a.app_code AND fb.fam_relation = 'Guardian' LIMIT 1) as guardian_address,
        
        (SELECT fb.fam_occup FROM family_background fb 
         WHERE fb.app_code = a.app_code AND fb.fam_relation = 'Guardian' LIMIT 1) as guardian_occupation,
        
        (SELECT fb.fam_office_addr FROM family_background fb 
         WHERE fb.app_code = a.app_code AND fb.fam_relation = 'Guardian' LIMIT 1) as guardian_office_addr,
        
        (SELECT fb.fam_educ_attain FROM family_background fb 
         WHERE fb.app_code = a.app_code AND fb.fam_relation = 'Guardian' LIMIT 1) as guardian_education,
        
        (SELECT fb.fam_ethno_group FROM family_background fb 
         WHERE fb.app_code = a.app_code AND fb.fam_relation = 'Guardian' LIMIT 1) as guardian_ethnicity,
        
        (SELECT fb.fam_annual_income FROM family_background fb 
         WHERE fb.app_code = a.app_code AND fb.fam_relation = 'Guardian' LIMIT 1) as guardian_income,
        
        (SELECT fb.fam_year_itr FROM family_background fb 
         WHERE fb.app_code = a.app_code AND fb.fam_relation = 'Guardian' LIMIT 1) as guardian_itr_year,
         
        (SELECT string_agg(CONCAT(
            sib.sib_last_name, ', ', sib.sib_first_name, ' ', sib.sib_middle_initial,
            CASE WHEN sch.scholar_name IS NOT NULL 
                THEN CONCAT(' (Scholarship: ', sch.scholar_name, 
                           ', Course: ', sch.scholar_course, 
                           ', Year: ', sch.scholar_year, ')')
                ELSE ''
            END
        ), '||') 
         FROM sibling sib 
         LEFT JOIN scholarship sch ON sib.sib_code = sch.sib_code 
         WHERE sib.app_code = a.app_code) as sibling_detail,
         
        pref.pref_name,
        pref.pref_addr,
        pref.pref_degree,
        pref.pref_remarks,
        
        (SELECT CASE WHEN COUNT(*) > 0 THEN TRUE ELSE FALSE END 
         FROM signature_scan scan 
         WHERE scan.app_code = a.app_code) as has_signature,
         
        (SELECT scan.scan_filename 
         FROM signature_scan scan 
         WHERE scan.app_code = a.app_code LIMIT 1) as signature_filename,
         
        (SELECT scan.scan_filetype 
         FROM signature_scan scan 
         WHERE scan.app_code = a.app_code LIMIT 1) as signature_filetype,
        
        (SELECT ss.status_code FROM scholarship_status ss 
         WHERE ss.app_code = a.app_code AND ss.status_is_active = TRUE LIMIT 1) as status,
         
        (SELECT ss.status_date FROM scholarship_status ss 
         WHERE ss.app_code = a.app_code AND ss.status_is_active = TRUE LIMIT 1) as status_date,
         
        (SELECT ss.status_remarks FROM scholarship_status ss 
         WHERE ss.app_code = a.app_code AND ss.status_is_active = TRUE LIMIT 1) as status_remarks
        
    FROM applicant a
    LEFT JOIN administrator adm ON a.admin_code = adm.admin_code
    LEFT JOIN address addr ON a.app_code = addr.app_code
    LEFT JOIN school_preference pref ON a.app_code = pref.app_code
    WHERE a.app_code = :app_code";
    
    $stmt = $pdo->prepare($detailed_query);
    $stmt->bindParam(':app_code', $app_code);
    $stmt->execute();
    
    $application = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$application) {
        $_SESSION['error_message'] = "Application not found";
        header("Location: index.php");
        exit();
    }
    
    // Process sibling data for display - This is the key fix
    $application['sibling_array'] = [];
    if (!empty($application['sibling_detail'])) {
        $application['sibling_array'] = explode('||', $application['sibling_detail']);
    }
    
    // Normalize status - treat any unknown status as PENDING
    $application['status'] = strtoupper($application['status'] ?? 'PENDING');
    if (!in_array($application['status'], ['PENDING', 'APPROVED', 'REJECTED'])) {
        $application['status'] = 'PENDING';
    }
    
    // Fetch supporting documents if available
    $doc_query = "SELECT * FROM signature_scan WHERE app_code = :app_code";
    $doc_stmt = $pdo->prepare($doc_query);
    $doc_stmt->bindParam(':app_code', $app_code);
    $doc_stmt->execute();
    $applicant_documents = $doc_stmt->fetchAll(PDO::FETCH_ASSOC);
    
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
        .spin {
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
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
                    <h1><i class="bi bi-file-earmark-text"></i> Application Details</h1>
                </div>
                <div class="btn-group" role="group">
                    <a href="edit.php?id=<?php echo $app_code; ?>" class="btn btn-primary">
                        <i class="bi bi-pencil-square"></i> Edit
                    </a>
                    <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#statusModal">
                        <i class="bi bi-check2-circle"></i> Update Status
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
                    switch($application['status']) {
                        case 'PENDING':
                            $status_class = 'bg-warning text-dark';
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
                        <?php echo htmlspecialchars($application['status']); ?>
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
                                    <p id="admin-remarks-display"><?php echo nl2br(htmlspecialchars($application['admin_add_remarks'] ?? 'No remarks')); ?></p>
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
                                <p><?php 
                                    if (!empty($application['elementary_education']) && $application['elementary_education'] !== 'School: N/A Address: N/A Year: N/A Grade: N/A Rank: N/A') {
                                        echo htmlspecialchars($application['elementary_education']);
                                    } else {
                                        echo '<span class="text-muted">No elementary education data available.</span>';
                                    }
                                ?></p>
                            </div>
                            
                            <!-- High School -->
                            <div class="mb-4">
                                <h6 class="text-primary mb-3">High School Education</h6>
                                <p><?php 
                                    if (!empty($application['high_school_education']) && $application['high_school_education'] !== 'School: N/A Address: N/A Year: N/A Grade: N/A Rank: N/A') {
                                        echo htmlspecialchars($application['high_school_education']);
                                    } else {
                                        echo '<span class="text-muted">No high school education data available.</span>';
                                    }
                                ?></p>
                            </div>
                            
                            <!-- Vocational -->
                            <div class="mb-4">
                                <h6 class="text-primary mb-3">Vocational Education</h6>
                                <p><?php 
                                    if (!empty($application['vocational_education']) && $application['vocational_education'] !== 'School: N/A Address: N/A Year: N/A Grade: N/A Rank: N/A') {
                                        echo htmlspecialchars($application['vocational_education']);
                                    } else {
                                        echo '<span class="text-muted">No vocational education data available.</span>';
                                    }
                                ?></p>
                            </div>
                            
                            <!-- College/Undergraduate -->
                            <div class="mb-4">
                                <h6 class="text-primary mb-3">College/Undergraduate Education</h6>
                                <p><?php 
                                    if (!empty($application['college_education']) && $application['college_education'] !== 'School: N/A Address: N/A Year: N/A Grade: N/A Rank: N/A') {
                                        echo htmlspecialchars($application['college_education']);
                                    } else {
                                        echo '<span class="text-muted">No college education data available.</span>';
                                    }
                                ?></p>
                            </div>
                            
                            <!-- Post-Graduate -->
                            <div class="mb-4">
                                <h6 class="text-primary mb-3">Post-Graduate Education</h6>
                                <p><?php 
                                    if (!empty($application['post_graduate_education']) && $application['post_graduate_education'] !== 'School: N/A Address: N/A Year: N/A Grade: N/A Rank: N/A') {
                                        echo htmlspecialchars($application['post_graduate_education']);
                                    } else {
                                        echo '<span class="text-muted">No post-graduate education data available.</span>';
                                    }
                                ?></p>
                            </div>
                            
                            <!-- Masteral -->
                            <div class="mb-4">
                                <h6 class="text-primary mb-3">Masteral Education</h6>
                                <p><?php 
                                    if (!empty($application['masteral_education']) && $application['masteral_education'] !== 'School: N/A Address: N/A Year: N/A Grade: N/A Rank: N/A') {
                                        echo htmlspecialchars($application['masteral_education']);
                                    } else {
                                        echo '<span class="text-muted">No masteral education data available.</span>';
                                    }
                                ?></p>
                            </div>
                            
                            <!-- Doctorate -->
                            <div>
                                <h6 class="text-primary mb-3">Doctorate Education</h6>
                                <p><?php 
                                    if (!empty($application['doctorate_education']) && $application['doctorate_education'] !== 'School: N/A Address: N/A Year: N/A Grade: N/A Rank: N/A') {
                                        echo htmlspecialchars($application['doctorate_education']);
                                    } else {
                                        echo '<span class="text-muted">No doctorate education data available.</span>';
                                    }
                                ?></p>
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
                            <div class="mb-4 sub-section">
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
                            <div class="sub-section">
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

                    <!-- Siblings Information - FIXED SECTION -->
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
                                                    // Parse the sibling data
                                                    $parts = explode('(Scholarship:', $sibling);
                                                    $name = trim($parts[0]);
                                                    
                                                    $scholarship = '';
                                                    $course = '';
                                                    $year = '';
                                                    
                                                    if (count($parts) > 1) {
                                                        // Extract scholarship details
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
                                    <?php echo htmlspecialchars($application['status']); ?>
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
                                        <img src="get_signature.php?id=<?php echo $app_code; ?>" 
                                             alt="Applicant Signature" 
                                             class="img-fluid" 
                                             onclick="showSignatureModal()"
                                             style="cursor: pointer;">
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
                                        <i class="bi bi-floppy"></i> Save Notes
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
                                <option value="PENDING" <?php echo $application['status'] == 'PENDING' ? 'selected' : ''; ?>>Pending</option>
                                <option value="APPROVED" <?php echo $application['status'] == 'APPROVED' ? 'selected' : ''; ?>>Approved</option>
                                <option value="REJECTED" <?php echo $application['status'] == 'REJECTED' ? 'selected' : ''; ?>>Rejected</option>
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
        
        // Handle saving admin notes via AJAX - UPDATED WITH REAL-TIME
        const saveNotesBtn = document.getElementById('save-notes');
        const notesTextarea = document.getElementById('admin_notes');
        
        if (saveNotesBtn && notesTextarea) {
            saveNotesBtn.addEventListener('click', function(e) {
                e.preventDefault();
                
                const notes = notesTextarea.value.trim();
                const appCode = '<?php echo $app_code; ?>';
                
                // Show loading state
                const originalText = saveNotesBtn.innerHTML;
                saveNotesBtn.innerHTML = '<i class="bi bi-hourglass-split spin"></i> Saving...';
                saveNotesBtn.disabled = true;
                
                // Make AJAX request
                fetch('ajax/update_notes.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `app_code=${encodeURIComponent(appCode)}&notes=${encodeURIComponent(notes)}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Update the display in real-time
                        const remarksDisplay = document.getElementById('admin-remarks-display');
                        if (remarksDisplay) {
                            remarksDisplay.innerHTML = notes ? notes.replace(/\n/g, '<br>') : 'No remarks';
                        }
                        
                        // Show success message
                        showAlert('Notes saved successfully!', 'success');
                        
                        // Reset button
                        saveNotesBtn.innerHTML = '<i class="bi bi-check-circle"></i> Saved!';
                        setTimeout(() => {
                            saveNotesBtn.innerHTML = originalText;
                        }, 2000);
                    } else {
                        throw new Error(data.message || 'Failed to save notes');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showAlert('Error saving notes: ' + error.message, 'danger');
                    saveNotesBtn.innerHTML = originalText;
                })
                .finally(() => {
                    saveNotesBtn.disabled = false;
                });
            });
        }
        
        // Handle status form submission
        const statusForm = document.getElementById('status-form');
        if (statusForm) {
            statusForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const formData = new FormData(statusForm);
                
                fetch('ajax/update_status.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showAlert('Status updated successfully!', 'success');
                        
                        // Close modal
                        const modal = bootstrap.Modal.getInstance(document.getElementById('statusModal'));
                        if (modal) modal.hide();
                        
                        // Reload page to show updated status
                        setTimeout(() => {
                            window.location.reload();
                        }, 1500);
                    } else {
                        throw new Error(data.message || 'Failed to update status');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showAlert('Error updating status: ' + error.message, 'danger');
                });
            });
        }
        
        // Handle delete form submission
        const deleteForm = document.getElementById('delete-form');
        if (deleteForm) {
            deleteForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                if (confirm('Are you absolutely sure you want to delete this application? This action cannot be undone.')) {
                    const formData = new FormData(deleteForm);
                    
                    fetch('ajax/delete_application.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            showAlert('Application deleted successfully!', 'success');
                            
                            // Redirect to index after delay
                            setTimeout(() => {
                                window.location.href = 'index.php';
                            }, 1500);
                        } else {
                            throw new Error(data.message || 'Failed to delete application');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        showAlert('Error deleting application: ' + error.message, 'danger');
                    });
                }
            });
        }
        
        // Utility function to show alerts
        function showAlert(message, type = 'info') {
            const alertContainer = document.getElementById('alert-container');
            if (alertContainer) {
                const alertHtml = `
                    <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                        ${message}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                `;
                alertContainer.insertAdjacentHTML('beforeend', alertHtml);
                
                // Auto-dismiss after 5 seconds
                setTimeout(() => {
                    const newAlert = alertContainer.lastElementChild;
                    if (newAlert && newAlert.classList.contains('alert')) {
                        const closeBtn = newAlert.querySelector('.btn-close');
                        if (closeBtn) closeBtn.click();
                    }
                }, 5000);
            }
        }
        
        // Handle signature modal
        window.showSignatureModal = function() {
            const modalHtml = `
                <div class="modal fade" id="signatureModal" tabindex="-1" aria-labelledby="signatureModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="signatureModalLabel">Applicant Signature</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body text-center">
                                <img src="get_signature.php?id=<?php echo $app_code; ?>" alt="Applicant Signature" class="img-fluid">
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            // Remove existing modal if any
            const existingModal = document.getElementById('signatureModal');
            if (existingModal) {
                existingModal.remove();
            }
            
            // Add new modal
            document.body.insertAdjacentHTML('beforeend', modalHtml);
            
            // Show modal
            const modal = new bootstrap.Modal(document.getElementById('signatureModal'));
            modal.show();
        };
        
        // Print functionality
        const printBtn = document.querySelector('a[href*="print.php"]');
        if (printBtn) {
            printBtn.addEventListener('click', function(e) {
                e.preventDefault();
                const printUrl = this.href;
                const printWindow = window.open(printUrl, '_blank', 'width=800,height=600');
                
                printWindow.onload = function() {
                    setTimeout(() => {
                        printWindow.print();
                    }, 500);
                };
            });
        }
        
        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl/Cmd + E for Edit
            if ((e.ctrlKey || e.metaKey) && e.key === 'e') {
                e.preventDefault();
                const editBtn = document.querySelector('a[href*="edit.php"]');
                if (editBtn) editBtn.click();
            }
            
            // Ctrl/Cmd + P for Print
            if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
                e.preventDefault();
                const printBtn = document.querySelector('a[href*="print.php"]');
                if (printBtn) printBtn.click();
            }
            
            // Escape to go back
            if (e.key === 'Escape') {
                const backBtn = document.querySelector('a[href="index.php"]');
                if (backBtn) backBtn.click();
            }
        });
        
        // Auto-save notes functionality
        let notesTimeout;
        if (notesTextarea) {
            notesTextarea.addEventListener('input', function() {
                clearTimeout(notesTimeout);
                notesTimeout = setTimeout(() => {
                    // Auto-save after 3 seconds of no typing
                    if (saveNotesBtn && !saveNotesBtn.disabled) {
                        saveNotesBtn.click();
                    }
                }, 3000);
            });
        }
        
        // Smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
        
        // Enhanced table functionality for siblings
        const siblingTable = document.querySelector('.table-responsive table');
        if (siblingTable) {
            // Add hover effects
            const rows = siblingTable.querySelectorAll('tbody tr');
            rows.forEach(row => {
                row.addEventListener('mouseenter', function() {
                    this.style.backgroundColor = '#f8f9fa';
                });
                row.addEventListener('mouseleave', function() {
                    this.style.backgroundColor = '';
                });
            });
        }
        
        // Form validation enhancement
        const forms = document.querySelectorAll('form');
        forms.forEach(form => {
            form.addEventListener('submit', function(e) {
                const requiredFields = form.querySelectorAll('[required]');
                let hasErrors = false;
                
                requiredFields.forEach(field => {
                    if (!field.value.trim()) {
                        field.classList.add('is-invalid');
                        hasErrors = true;
                    } else {
                        field.classList.remove('is-invalid');
                    }
                });
                
                if (hasErrors) {
                    e.preventDefault();
                    showAlert('Please fill in all required fields.', 'warning');
                }
            });
        });
        
        // Tooltips initialization
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function(tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
        
        // Popovers initialization
        const popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
        popoverTriggerList.map(function(popoverTriggerEl) {
            return new bootstrap.Popover(popoverTriggerEl);
        });
        
        // Responsive table enhancement
        const tables = document.querySelectorAll('.table-responsive');
        tables.forEach(table => {
            if (table.scrollWidth > table.clientWidth) {
                const scrollIndicator = document.createElement('div');
                scrollIndicator.className = 'alert alert-info';
                scrollIndicator.innerHTML = '<i class="bi bi-arrow-left-right"></i> Scroll horizontally to view all columns';
                table.parentNode.insertBefore(scrollIndicator, table);
            }
        });
    });
    
    // Global functions
    window.refreshPage = function() {
        window.location.reload();
    };
    
    window.goBack = function() {
        window.history.back();
    };
    
    window.exportToPDF = function() {
        window.print();
    };
    
    // Check for unsaved changes
    window.addEventListener('beforeunload', function(e) {
        const notesTextarea = document.getElementById('admin_notes');
        if (notesTextarea && notesTextarea.dataset.original !== notesTextarea.value) {
            e.preventDefault();
            e.returnValue = '';
        }
    });
    
    // Set original value for notes to detect changes
    document.addEventListener('DOMContentLoaded', function() {
        const notesTextarea = document.getElementById('admin_notes');
        if (notesTextarea) {
            notesTextarea.dataset.original = notesTextarea.value;
        }
    });
    </script>
</body>
</html>