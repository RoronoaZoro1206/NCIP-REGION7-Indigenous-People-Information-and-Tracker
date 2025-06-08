<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include "../config/database.php";

try {
    $user_stmt = $pdo->prepare("SELECT user_username, user_profile_picture FROM users WHERE user_id = :user_id");
    $user_stmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
    $user_stmt->execute();
    $user = $user_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Update session with latest user info
    if ($user) {
        $_SESSION['username'] = $user['user_username'];
        $_SESSION['profile_picture'] = $user['user_profile_picture'] ?? 'default.png';
    }
} catch (PDOException $e) {
    error_log("Failed to fetch user data: " . $e->getMessage());
}

// Check if user is logged in
if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.php");
    exit();
}

// Handle status filter
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$status_condition = "";
if (!empty($status_filter)) {
    $status_condition = "AND ss.status_code = :status_filter";
}

// Handle search query
$search = isset($_GET['search']) ? $_GET['search'] : '';
$search_condition = "";
if (!empty($search)) {
    $search_condition = "AND (a.app_last_name ILIKE :search_term OR a.app_first_name ILIKE :search_term OR 
                        a.app_ethno_group ILIKE :search_term OR a.app_email ILIKE :search_term)";
}

// Count total applications for pagination
$count_query = "SELECT COUNT(DISTINCT a.app_code) as total 
               FROM applicant a 
               LEFT JOIN scholarship_status ss ON a.app_code = ss.app_code AND ss.status_is_active = TRUE
               WHERE 1=1 $status_condition $search_condition";
               
$count_stmt = $pdo->prepare($count_query);
if (!empty($status_filter)) {
    $count_stmt->bindParam(':status_filter', $status_filter);
}
if (!empty($search)) {
    $search_param = "%$search%";
    $count_stmt->bindParam(':search_term', $search_param);
}
$count_stmt->execute();
$total_records = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Pagination setup
$records_per_page = 10;
$total_pages = ceil($total_records / $records_per_page);
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $records_per_page;

// Enhanced comprehensive query - includes ALL fields from e-service.php
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
         
        (SELECT string_agg(CONCAT(
            fb.fam_relation, ':', 
            ' Name: ', fb.fam_name, 
            ', Status: ', CASE WHEN fb.fam_is_alive THEN 'Living' ELSE 'Deceased' END,
            ', Address: ', fb.fam_addr, 
            ', Occupation: ', fb.fam_occup, 
            ', Office Address: ', fb.fam_office_addr, 
            ', Education: ', fb.fam_educ_attain, 
            ', Ethnic Group: ', fb.fam_ethno_group, 
            ', Annual Income: ₱', fb.fam_annual_income::text, 
            ', ITR Year: ', fb.fam_year_itr::text
        ), '||') 
         FROM family_background fb 
         WHERE fb.app_code = a.app_code) as family_detail,
         
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
         WHERE ss.app_code = a.app_code AND ss.status_is_active = TRUE LIMIT 1) as status_date
        
    FROM applicant a
    LEFT JOIN administrator adm ON a.admin_code = adm.admin_code
    LEFT JOIN address addr ON a.app_code = addr.app_code
    LEFT JOIN school_preference pref ON a.app_code = pref.app_code
    LEFT JOIN scholarship_status ss ON a.app_code = ss.app_code AND ss.status_is_active = TRUE
    WHERE 1=1 $status_condition $search_condition
    GROUP BY a.app_code, adm.admin_code, addr.addr_code, pref.pref_code
    ORDER BY a.app_code DESC
    LIMIT :limit OFFSET :offset";

// Prepare and execute the detailed query
$detailed_stmt = $pdo->prepare($detailed_query);
$detailed_stmt->bindParam(':limit', $records_per_page, PDO::PARAM_INT);
$detailed_stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
if (!empty($status_filter)) {
    $detailed_stmt->bindParam(':status_filter', $status_filter);
}
if (!empty($search)) {
    $search_param = "%$search%";
    $detailed_stmt->bindParam(':search_term', $search_param);
}

// Around line 280-290
$detailed_stmt->execute();

// Initialize applications array
$applications = [];

while ($row = $detailed_stmt->fetch(PDO::FETCH_ASSOC)) {
    // Normalize status - treat any unknown status as PENDING
    $status = strtoupper($row['status'] ?? 'PENDING');
    if (!in_array($status, ['PENDING', 'APPROVED', 'REJECTED'])) {
        $status = 'PENDING';
    }
    
    // Set status class for badge display
    switch($status) {
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
            $status_class = 'bg-warning text-dark'; // Default to PENDING styling
    }

    // Split concatenated detailed fields into arrays
    if (!empty($row['education_detail'])) {
        $row['education_array'] = explode('||', $row['education_detail']);
    } else {
        $row['education_array'] = [];
    }
    
    if (!empty($row['family_detail'])) {
        $row['family_array'] = explode('||', $row['family_detail']);
    } else {
        $row['family_array'] = [];
    }
    
    if (!empty($row['sibling_detail'])) {
        $row['sibling_array'] = explode('||', $row['sibling_detail']);
    } else {
        $row['sibling_array'] = [];
    }
    
    $row['status'] = $status;
    $row['status_class'] = $status_class;
    $applications[] = $row;
}

// Also fix the status counts initialization
$status_counts = [
    'all' => 0,
    'pending' => 0,
    'approved' => 0,
    'rejected' => 0
];

// Update status counts with normalization
$count_status_query = "SELECT 
    CASE 
        WHEN ss.STATUS_CODE IS NULL OR ss.STATUS_CODE NOT IN ('PENDING', 'APPROVED', 'REJECTED') 
        THEN 'PENDING' 
        ELSE UPPER(ss.STATUS_CODE) 
    END as normalized_status,
    COUNT(DISTINCT a.APP_CODE) as count
    FROM APPLICANT a
    LEFT JOIN SCHOLARSHIP_STATUS ss ON a.APP_CODE = ss.APP_CODE AND ss.STATUS_IS_ACTIVE = TRUE
    GROUP BY 
    CASE 
        WHEN ss.STATUS_CODE IS NULL OR ss.STATUS_CODE NOT IN ('PENDING', 'APPROVED', 'REJECTED') 
        THEN 'PENDING' 
        ELSE UPPER(ss.STATUS_CODE) 
    END";

$count_status_stmt = $pdo->query($count_status_query);
while ($row = $count_status_stmt->fetch(PDO::FETCH_ASSOC)) {
    $normalized_status = strtolower($row['normalized_status']);
    if (array_key_exists($normalized_status, $status_counts)) {
        $status_counts[$normalized_status] = $row['count'];
        $status_counts['all'] += $row['count'];
    }
}

// Handle delete request (fallback for non-JS)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_application'])) {
    $app_code = $_POST['app_code'];
    
    // Begin transaction
    $pdo->beginTransaction();
    
    try {
        // Delete related records first (status records)
        $delete_status = $pdo->prepare("DELETE FROM SCHOLARSHIP_STATUS WHERE APP_CODE = :app_code");
        $delete_status->bindParam(":app_code", $app_code);
        $delete_status->execute();
        
        // Delete the application
        $delete_app = $pdo->prepare("DELETE FROM APPLICANT WHERE APP_CODE = :app_code");
        $delete_app->bindParam(":app_code", $app_code);
        $delete_app->execute();
        
        $pdo->commit();
        $_SESSION['success_message'] = "Application deleted successfully!";
        header("Location: index.php");
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error_message'] = "Error deleting application: " . $e->getMessage();
    }
}

// Handle status update (fallback for non-JS)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $app_code = $_POST['app_code'];
    $new_status = $_POST['new_status'];
    
    // Begin transaction
    $pdo->beginTransaction();
    
    try {
        // First, set all current statuses for this applicant to inactive
        $update_old = $pdo->prepare("UPDATE SCHOLARSHIP_STATUS SET STATUS_IS_ACTIVE = FALSE 
                                     WHERE APP_CODE = :app_code");
        $update_old->bindParam(":app_code", $app_code);
        $update_old->execute();
        
        // Insert new status
        $insert = $pdo->prepare("INSERT INTO SCHOLARSHIP_STATUS 
                                (APP_CODE, STATUS_CODE, STATUS_DATE, STATUS_IS_ACTIVE) 
                                VALUES (:app_code, :new_status, CURRENT_TIMESTAMP, TRUE)");
        $insert->bindParam(":app_code", $app_code);
        $insert->bindParam(":new_status", $new_status);
        $insert->execute();
        
        $pdo->commit();
        $_SESSION['success_message'] = "Status updated successfully!";
        header("Location: index.php" . (empty($_GET) ? "" : "?" . http_build_query($_GET)));
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error_message'] = "Error updating status: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scholarship Applications - NCIP Admin</title>
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
            border: none;
            border-radius: 10px;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
            }
        }
        .mobile-view {
            display: none;
        }
        
        /* Enhanced horizontal scrollable table styles */
        .horizontal-scroll-wrapper {
            overflow-x: auto;
            margin-bottom: 20px;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            background-color: #fff;
            max-height: 70vh; /* Limit vertical height too */
            overflow-y: auto; /* Enable vertical scrolling */
        }
        .comprehensive-table {
            min-width: 5200px; 
            margin-bottom: 0;
        }
        .comprehensive-table th {
            background-color: #f8f9fa;
            position: sticky;
            top: 0;
            z-index: 1;
            white-space: nowrap;
            font-weight: 600;
        }
        .comprehensive-table td {
            white-space: nowrap;
            max-width: 250px;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .detailed-cell {
            max-width: 300px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .detailed-cell:hover {
            white-space: normal;
            overflow: visible;
            background-color: #f8f9fa;
            position: relative;
            z-index: 2;
        }
        .signature-preview {
            max-width: 100px;
            max-height: 60px;
            cursor: pointer;
        }
        /* Modal signature display */
        .signature-modal-content img {
            max-width: 100%;
            max-height: 80vh;
        }
        
        /* Section headers in table */
        .section-header {
            background-color: #e6f2ff !important;
            font-weight: bold;
        }
        
        /* Hide scroll arrows in Chrome */
        .comprehensive-table::-webkit-scrollbar {
            width: 12px;
        }
        .comprehensive-table::-webkit-scrollbar-track {
            background: #f1f1f1;
        }
        .comprehensive-table::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 6px;
        }
        .comprehensive-table::-webkit-scrollbar-thumb:hover {
            background: #555;
        }
            .family-details-section {
        border-top: 1px solid #dee2e6;
        padding-top: 15px;
        margin-top: 15px;
    }
    
    .family-member-card {
        margin-bottom: 15px;
        border-left: 4px solid #007bff;
    }
    
    .family-member-header {
        font-weight: bold;
        color: #007bff;
    }
    
    .detailed-cell.expanded {
        white-space: normal !important;
        max-width: none !important;
        overflow: visible !important;
        background-color: #f8f9fa;
        position: relative;
        z-index: 5;
        box-shadow: 0 0 10px rgba(0,0,0,0.1);
        padding: 10px !important;
    }
    

    .comprehensive-table th[class*="section-header"] {
        background-color: #e6f2ff !important;
        font-weight: bold;
        text-align: center;
    }
    

    @media (max-width: 768px) {
        .mobile-family-details {
            display: none;
        }
        
        .mobile-family-details.show {
            display: block;
        }
        
        .toggle-family-details {
            width: 100%;
            margin-bottom: 10px;
        }
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
            <h1>Scholarship Applications</h1>
            <div>
                <a href="export.php" class="btn btn-success me-2">
                    <i class="bi bi-file-earmark-excel"></i> Export to Excel
                </a>
            </div>
        </div>

        <!-- Alert Container for AJAX responses -->
        <div id="alert-container">
            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($_SESSION['success_message']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['success_message']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($_SESSION['error_message']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['error_message']); ?>
            <?php endif; ?>
        </div>
        
        <div class="card card-dashboard mb-4">
            <div class="card-body">
                <div class="row mb-3">
                    <!-- Status filter buttons -->
                    <div class="col-md-6">
                        <div class="btn-group" role="group">
                            <a href="index.php" class="btn btn-outline-secondary <?php echo empty($status_filter) ? 'active' : ''; ?>">
                                All <span class="badge bg-secondary"><?php echo $status_counts['all']; ?></span>
                            </a>
                            <a href="index.php?status=PENDING<?php echo !empty($search) ? '&search='.$search : ''; ?>" 
                               class="btn btn-outline-warning <?php echo $status_filter === 'PENDING' ? 'active' : ''; ?>">
                                Pending <span class="badge bg-warning"><?php echo $status_counts['pending']; ?></span>
                            </a>
                            <a href="index.php?status=APPROVED<?php echo !empty($search) ? '&search='.$search : ''; ?>" 
                               class="btn btn-outline-success <?php echo $status_filter === 'APPROVED' ? 'active' : ''; ?>">
                                Approved <span class="badge bg-success"><?php echo $status_counts['approved']; ?></span>
                            </a>
                            <a href="index.php?status=REJECTED<?php echo !empty($search) ? '&search='.$search : ''; ?>" 
                               class="btn btn-outline-danger <?php echo $status_filter === 'REJECTED' ? 'active' : ''; ?>">
                                Rejected <span class="badge bg-danger"><?php echo $status_counts['rejected']; ?></span>
                            </a>
                        </div>
                    </div>

                    <!-- Search box -->
                    <div class="col-md-6">
                        <form class="d-flex">
                            <?php if (!empty($status_filter)): ?>
                                <input type="hidden" name="status" value="<?php echo htmlspecialchars($status_filter); ?>">
                            <?php endif; ?>
                            <input class="form-control me-2" type="search" name="search" autocomplete="off" placeholder="Search by name, email, or ethnic group" 
                                   value="<?php echo htmlspecialchars($search); ?>">
                            <button class="btn btn-outline-primary" type="submit">Search</button>
                            <?php if (!empty($search)): ?>
                                <a href="<?php echo empty($status_filter) ? 'index.php' : "index.php?status=".htmlspecialchars($status_filter); ?>" class="btn btn-outline-secondary ms-2">Clear</a>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>

                <!-- Comprehensive Table with Horizontal Scrolling - ALL FIELDS -->
                <div class="horizontal-scroll-wrapper">
                    <table class="table table-hover table-striped table-bordered comprehensive-table">
                        <thead class="table-light">
                            <tr>
                                <th class="section-header" colspan="3">Application</th>
                                <th class="section-header" colspan="6">Admin Information</th>
                                <th class="section-header" colspan="10">Applicant Information</th>
                                <th class="section-header" colspan="6">Address</th>
                                <th class="section-header" colspan="7">Education</th>
                                <th class="section-header" colspan="27">Family Background</th>
                                <th class="section-header" colspan="3">Siblings</th>
                                <th class="section-header" colspan="4">Preferred School</th>
                                <th class="section-header" colspan="3">Certification</th>
                                <th class="section-header" colspan="2">Status</th>
                                <th class="section-header" colspan="1">Actions</th>
                            </tr>
                            <tr>
                                <!-- Application -->
                                <th>ID</th>
                                <th>App Number</th>
                                <th>Date Filed</th>
                                
                                <!-- Admin Information -->
                                <th>CSC/PO Action</th>
                                <th>RO Action</th>
                                <th>CADT</th>
                                <th>Cong District</th>
                                <th>Remarks</th>
                                <th>Assistance Type</th>
                                
                                <!-- Applicant Information -->
                                <th>Last Name</th>
                                <th>First Name</th>
                                <th>M.I.</th>
                                <th>Date of Birth</th>
                                <th>Place of Birth</th>
                                <th>Gender</th>
                                <th>Civil Status</th>
                                <th>Ethnic Group</th>
                                <th>Phone Number</th>
                                <th>Email</th>
                                
                                <!-- Address -->
                                <th>Mailing</th>
                                <th>Permanent</th>
                                <th>House/Street</th>
                                <th>Barangay</th>
                                <th>Municipality</th>
                                <th>Province</th>
                                
                                <!-- Education -->
                                <th>Elementary</th>
                                <th>High School</th>
                                <th>Vocational</th>
                                <th>College</th>
                                <th>Post-Graduate</th>
                                <th>Masteral</th>
                                <th>Doctorate</th>
                                
                                <!-- Family Background - Father -->
                                <th>Father Name</th>
                                <th>Father Status</th>
                                <th>Father Address</th>
                                <th>Father Occupation</th>
                                <th>Father Office Address</th>
                                <th>Father Education</th>
                                <th>Father Ethnic Group</th>
                                <th>Father Income</th>
                                <th>Father ITR Year</th>
                                
                                <!-- Family Background - Mother -->
                                <th>Mother Name</th>
                                <th>Mother Status</th>
                                <th>Mother Address</th>
                                <th>Mother Occupation</th>
                                <th>Mother Office Address</th>
                                <th>Mother Education</th>
                                <th>Mother Ethnic Group</th>
                                <th>Mother Income</th>
                                <th>Mother ITR Year</th>
                                
                                <!-- Family Background - Guardian -->
                                <th>Guardian Name</th>
                                <th>Guardian Status</th>
                                <th>Guardian Address</th>
                                <th>Guardian Occupation</th>
                                <th>Guardian Office Address</th>
                                <th>Guardian Education</th>
                                <th>Guardian Ethnic Group</th>
                                <th>Guardian Income</th>
                                <th>Guardian ITR Year</th>
                                
                                <!-- Siblings -->
                                <th>Sibling Name</th>
                                <th>Scholarship</th>
                                <th>Course/Year</th>
                                
                                <!-- Preferred School -->
                                <th>Name</th>
                                <th>Address</th>
                                <th>Degree</th>
                                <th>Remarks</th>
                                
                                <!-- Certification -->
                                <th>Form Signed</th>
                                <th>Signature Date</th>
                                <th>E-Signature</th>
                                
                                <!-- Status -->
                                <th>Status</th>
                                <th>Status Date</th>
                                
                                <!-- Actions -->
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($applications) > 0): ?>
                                <?php foreach ($applications as $app): ?>
                                <tr id="app-row-<?php echo $app['app_code']; ?>">
                                    <!-- Application -->
                                    <td><?php echo htmlspecialchars($app['app_code']); ?></td>
                                    <td><?php echo htmlspecialchars($app['admin_app_number']); ?></td>
                                    <td><?php echo $app['admin_dof'] ? date('M d, Y', strtotime($app['admin_dof'])) : 'N/A'; ?></td>
                                    
                                    <!-- Admin Information -->
                                    <td class="detailed-cell"><?php echo htmlspecialchars($app['admin_action_taken_csc_po'] ?: 'N/A'); ?></td>
                                    <td class="detailed-cell"><?php echo htmlspecialchars($app['admin_action_taken_ro'] ?: 'N/A'); ?></td>
                                    <td class="detailed-cell"><?php echo htmlspecialchars($app['admin_cadt'] ?: 'N/A'); ?></td>
                                    <td class="detailed-cell"><?php echo htmlspecialchars($app['admin_cong_district'] ?: 'N/A'); ?></td>
                                    <td class="detailed-cell"><?php echo htmlspecialchars($app['admin_add_remarks'] ?: 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($app['admin_assistance_type']); ?></td>
                                    
                                    <!-- Applicant Information -->
                                    <td><?php echo htmlspecialchars($app['app_last_name']); ?></td>
                                    <td><?php echo htmlspecialchars($app['app_first_name']); ?></td>
                                    <td><?php echo htmlspecialchars($app['app_middle_initial']); ?></td>
                                    <td><?php echo date('m/d/Y', strtotime($app['app_date_of_birth'])); ?></td>
                                    <td class="detailed-cell"><?php echo htmlspecialchars($app['app_place_of_birth']); ?></td>
                                    <td><?php echo htmlspecialchars($app['app_gender']); ?></td>
                                    <td><?php echo htmlspecialchars($app['app_civil_status']); ?></td>
                                    <td><?php echo htmlspecialchars($app['app_ethno_group']); ?></td>
                                    <td><?php echo htmlspecialchars($app['app_phone_number'] ?: 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($app['app_email'] ?: 'N/A'); ?></td>
                                    
                                    <!-- Address -->
                                    <td class="detailed-cell"><?php echo htmlspecialchars($app['addr_mail'] ?: 'N/A'); ?></td>
                                    <td class="detailed-cell"><?php echo htmlspecialchars($app['addr_permanent']); ?></td>
                                    <td class="detailed-cell"><?php echo htmlspecialchars($app['addr_house_and_street']); ?></td>
                                    <td><?php echo htmlspecialchars($app['addr_barangay']); ?></td>
                                    <td><?php echo htmlspecialchars($app['addr_municipality']); ?></td>
                                    <td><?php echo htmlspecialchars($app['addr_province']); ?></td>
                                    
                                    <!-- Education - Display each education level in separate cells -->
                                    <td class="detailed-cell">
                                        <?php
                                        $found = false;
                                        if (!empty($app['education_array'])) {
                                            foreach ($app['education_array'] as $edu) {
                                                if (strpos($edu, 'Elementary:') === 0) {
                                                    echo htmlspecialchars(str_replace('Elementary:', '', $edu));
                                                    $found = true;
                                                    break;
                                                }
                                            }
                                        }
                                        if (!$found) {
                                            echo htmlspecialchars($app['elementary_education'] ?? 'N/A');
                                        }
                                        ?>
                                    </td>
                                    <td class="detailed-cell">
                                        <?php
                                        $found = false;
                                        if (!empty($app['education_array'])) {
                                            foreach ($app['education_array'] as $edu) {
                                                if (strpos($edu, 'High School:') === 0) {
                                                    echo htmlspecialchars(str_replace('High School:', '', $edu));
                                                    $found = true;
                                                    break;
                                                }
                                            }
                                        }
                                        if (!$found) {
                                            echo htmlspecialchars($app['high_school_education'] ?? 'N/A');
                                        }
                                        ?>
                                    </td>
                                    <td class="detailed-cell">
                                        <?php
                                        $found = false;
                                        if (!empty($app['education_array'])) {
                                            foreach ($app['education_array'] as $edu) {
                                                if (strpos($edu, 'Vocational:') === 0) {
                                                    echo htmlspecialchars(str_replace('Vocational:', '', $edu));
                                                    $found = true;
                                                    break;
                                                }
                                            }
                                        }
                                        if (!$found) {
                                            echo htmlspecialchars($app['vocational_education'] ?? 'N/A');
                                        }
                                        ?>
                                    </td>
                                    <td class="detailed-cell">
                                        <?php
                                        $found = false;
                                        if (!empty($app['education_array'])) {
                                            foreach ($app['education_array'] as $edu) {
                                                if (strpos($edu, 'College/Undergraduate:') === 0) {
                                                    echo htmlspecialchars(str_replace('College/Undergraduate:', '', $edu));
                                                    $found = true;
                                                    break;
                                                }
                                            }
                                        }
                                        if (!$found) {
                                            echo htmlspecialchars($app['college_education'] ?? 'N/A');
                                        }
                                        ?>
                                    </td>
                                    <td class="detailed-cell">
                                        <?php
                                        $found = false;
                                        if (!empty($app['education_array'])) {
                                            foreach ($app['education_array'] as $edu) {
                                                if (strpos($edu, 'Post-Graduate:') === 0) {
                                                    echo htmlspecialchars(str_replace('Post-Graduate:', '', $edu));
                                                    $found = true;
                                                    break;
                                                }
                                            }
                                        }
                                        if (!$found) {
                                            echo htmlspecialchars($app['post_graduate_education'] ?? 'N/A');
                                        }
                                        ?>
                                    </td>
                                    <td class="detailed-cell">
                                        <?php
                                        $found = false;
                                        if (!empty($app['education_array'])) {
                                            foreach ($app['education_array'] as $edu) {
                                                if (strpos($edu, 'Masteral:') === 0) {
                                                    echo htmlspecialchars(str_replace('Masteral:', '', $edu));
                                                    $found = true;
                                                    break;
                                                }
                                            }
                                        }
                                        if (!$found) {
                                            echo htmlspecialchars($app['masteral_education'] ?? 'N/A');
                                        }
                                        ?>
                                    </td>
                                    <td class="detailed-cell">
                                        <?php
                                        $found = false;
                                        if (!empty($app['education_array'])) {
                                            foreach ($app['education_array'] as $edu) {
                                                if (strpos($edu, 'Doctorate:') === 0) {
                                                    echo htmlspecialchars(str_replace('Doctorate:', '', $edu));
                                                    $found = true;
                                                    break;
                                                }
                                            }
                                        }
                                        if (!$found) {
                                            echo htmlspecialchars($app['doctorate_education'] ?? 'N/A');
                                        }
                                        ?>
                                    </td>
                                    
                                    <!-- Family Background - Father -->
                                    <td><?php echo htmlspecialchars($app['father_name'] ?: 'N/A'); ?></td>
                                    <td><?php echo $app['father_is_alive'] ? 'Living' : 'Deceased'; ?></td>
                                    <td class="detailed-cell"><?php echo htmlspecialchars($app['father_address'] ?: 'N/A'); ?></td>
                                    <td class="detailed-cell"><?php echo htmlspecialchars($app['father_occupation'] ?: 'N/A'); ?></td>
                                    <td class="detailed-cell"><?php echo htmlspecialchars($app['father_office_addr'] ?: 'N/A'); ?></td>
                                    <td class="detailed-cell"><?php echo htmlspecialchars($app['father_education'] ?: 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($app['father_ethnicity'] ?: 'N/A'); ?></td>
                                    <td><?php echo isset($app['father_income']) ? '₱' . number_format($app['father_income'], 2) : 'N/A'; ?></td>
                                    <td><?php echo htmlspecialchars($app['father_itr_year'] ?: 'N/A'); ?></td>
                                    
                                    <!-- Family Background - Mother -->
                                    <td><?php echo htmlspecialchars($app['mother_name'] ?: 'N/A'); ?></td>
                                    <td><?php echo $app['mother_is_alive'] ? 'Living' : 'Deceased'; ?></td>
                                    <td class="detailed-cell"><?php echo htmlspecialchars($app['mother_address'] ?: 'N/A'); ?></td>
                                    <td class="detailed-cell"><?php echo htmlspecialchars($app['mother_occupation'] ?: 'N/A'); ?></td>
                                    <td class="detailed-cell"><?php echo htmlspecialchars($app['mother_office_addr'] ?: 'N/A'); ?></td>
                                    <td class="detailed-cell"><?php echo htmlspecialchars($app['mother_education'] ?: 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($app['mother_ethnicity'] ?: 'N/A'); ?></td>
                                    <td><?php echo isset($app['mother_income']) ? '₱' . number_format($app['mother_income'], 2) : 'N/A'; ?></td>
                                    <td><?php echo htmlspecialchars($app['mother_itr_year'] ?: 'N/A'); ?></td>
                                    
                                    <!-- Family Background - Guardian -->
                                    <td><?php echo htmlspecialchars($app['guardian_name'] ?: 'N/A'); ?></td>
                                    <td><?php echo $app['guardian_is_alive'] ? 'Living' : 'Deceased'; ?></td>
                                    <td class="detailed-cell"><?php echo htmlspecialchars($app['guardian_address'] ?: 'N/A'); ?></td>
                                    <td class="detailed-cell"><?php echo htmlspecialchars($app['guardian_occupation'] ?: 'N/A'); ?></td>
                                    <td class="detailed-cell"><?php echo htmlspecialchars($app['guardian_office_addr'] ?: 'N/A'); ?></td>
                                    <td class="detailed-cell"><?php echo htmlspecialchars($app['guardian_education'] ?: 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($app['guardian_ethnicity'] ?: 'N/A'); ?></td>
                                    <td><?php echo isset($app['guardian_income']) ? '₱' . number_format($app['guardian_income'], 2) : 'N/A'; ?></td>
                                    <td><?php echo htmlspecialchars($app['guardian_itr_year'] ?: 'N/A'); ?></td>
                                    
                                    <!-- Siblings -->
                                    <td class="detailed-cell">
                                        <?php
                                        if (!empty($app['sibling_array'])) {
                                            $siblings = [];
                                            foreach ($app['sibling_array'] as $sib) {
                                                $parts = explode('(Scholarship:', $sib);
                                                $siblings[] = htmlspecialchars(trim($parts[0]));
                                            }
                                            echo implode('<br>', $siblings);
                                        } else {
                                            echo 'None';
                                        }
                                        ?>
                                    </td>
                                    <td class="detailed-cell">
                                        <?php
                                        $scholarships = [];
                                        if (!empty($app['sibling_array'])) {
                                            foreach ($app['sibling_array'] as $sib) {
                                                if (strpos($sib, '(Scholarship:') !== false) {
                                                    if (preg_match('/\(Scholarship: ([^,]+)/', $sib, $matches)) {
                                                        $scholarships[] = htmlspecialchars($matches[1]);
                                                    }
                                                }
                                            }
                                        }
                                        echo !empty($scholarships) ? implode('<br>', $scholarships) : 'N/A';
                                        ?>
                                    </td>
                                    <td class="detailed-cell">
                                        <?php
                                        $courses = [];
                                        if (!empty($app['sibling_array'])) {
                                            foreach ($app['sibling_array'] as $sib) {
                                                if (strpos($sib, 'Course:') !== false) {
                                                    if (preg_match('/Course: ([^,]+), Year: ([^\)]+)/', $sib, $matches)) {
                                                        $courses[] = htmlspecialchars($matches[1] . ' - ' . $matches[2]);
                                                    }
                                                }
                                            }
                                        }
                                        echo !empty($courses) ? implode('<br>', $courses) : 'N/A';
                                        ?>
                                    </td>
                                    
                                    <!-- Preferred School -->
                                    <td class="detailed-cell"><?php echo htmlspecialchars($app['pref_name'] ?: 'N/A'); ?></td>
                                    <td class="detailed-cell"><?php echo htmlspecialchars($app['pref_addr'] ?: 'N/A'); ?></td>
                                    <td class="detailed-cell"><?php echo htmlspecialchars($app['pref_degree'] ?: 'N/A'); ?></td>
                                    <td class="detailed-cell"><?php echo htmlspecialchars($app['pref_remarks'] ?: 'N/A'); ?></td>
                                    
                                    <!-- Certification -->
                                    <td><?php echo $app['app_form_signed'] ? 'Yes' : 'No'; ?></td>
                                    <td><?php echo $app['app_signature_date'] ? date('M d, Y', strtotime($app['app_signature_date'])) : 'N/A'; ?></td>
                                    <td>
                                        <?php if ($app['has_signature']): ?>
                                            <button class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#signatureModal<?php echo $app['app_code']; ?>">
                                                View Signature
                                            </button>
                                            
                                            <!-- Signature Modal -->
                                            <div class="modal fade" id="signatureModal<?php echo $app['app_code']; ?>" tabindex="-1" aria-hidden="true">
                                                <div class="modal-dialog">
                                                    <div class="modal-content signature-modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">E-Signature</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                        </div>
                                                        <div class="modal-body text-center">
                                                        <img src="get_signature.php?id=<?php echo $app['app_code']; ?>" class="img-fluid" alt="Signature" style="max-width: 100%; max-height: 400px;">
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">No Signature</span>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <!-- Status -->
                                    <td>
                                        <span id="status-badge-<?php echo $app['app_code']; ?>" class="badge <?php echo $app['status_class']; ?>"><?php echo htmlspecialchars($app['status']); ?></span>
                                    </td>
                                    <td id="status-date-<?php echo $app['app_code']; ?>"><?php echo $app['status_date'] ? date('M d, Y', strtotime($app['status_date'])) : 'N/A'; ?></td>
                                    
                                    <!-- Actions -->
                                    <td class="table-actions">
                                        <div class="btn-group">
                                            <a href="view.php?id=<?php echo $app['app_code']; ?>" class="btn btn-sm btn-info" title="View Details">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <a href="edit.php?id=<?php echo $app['app_code']; ?>" class="btn btn-sm btn-primary" title="Edit">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#statusModal<?php echo $app['app_code']; ?>" title="Change Status">
                                                <i class="bi bi-check2-circle"></i>
                                            </button>
                                        </div>
                                        
                                        <!-- Status Change Modal -->
                                        <div class="modal fade" id="statusModal<?php echo $app['app_code']; ?>" tabindex="-1" aria-hidden="true">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Change Application Status</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>
                                                    <form action="index.php" method="post" class="update-status-form">
                                                        <div class="modal-body">
                                                            <input type="hidden" name="app_code" value="<?php echo $app['app_code']; ?>">
                                                            <div class="mb-3">
                                                                <label for="new_status<?php echo $app['app_code']; ?>" class="form-label">Select New Status:</label>
                                                                <select class="form-select" id="new_status<?php echo $app['app_code']; ?>" name="new_status" required>
                                                                    <option value="">Select Status</option>
                                                                    <option value="PENDING" <?php echo strtoupper($app['status']) == 'PENDING' ? 'selected' : ''; ?>>Pending</option>
                                                                    <option value="APPROVED" <?php echo strtoupper($app['status']) == 'APPROVED' ? 'selected' : ''; ?>>Approved</option>
                                                                    <option value="REJECTED" <?php echo strtoupper($app['status']) == 'REJECTED' ? 'selected' : ''; ?>>Rejected</option>
                                                                </select>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label for="status_remarks<?php echo $app['app_code']; ?>" class="form-label">Remarks (Optional):</label>
                                                                <textarea class="form-control" id="status_remarks<?php echo $app['app_code']; ?>" name="status_remarks" rows="3"></textarea>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                            <button type="submit" name="update_status" class="btn btn-primary">Update Status</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Delete Confirmation Modal -->
                                        <div class="modal fade" id="deleteModal<?php echo $app['app_code']; ?>" tabindex="-1" aria-hidden="true">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Confirm Deletion</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <p>Are you sure you want to delete this application?</p>
                                                        <p><strong>Applicant:</strong> <?php echo htmlspecialchars($app['app_last_name'] . ', ' . $app['app_first_name']); ?></p>
                                                        <p><strong>ID:</strong> <?php echo htmlspecialchars($app['app_code']); ?></p>
                                                        <p class="text-danger"><strong>This action cannot be undone!</strong></p>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        <form action="index.php" method="post" class="delete-app-form">
                                                            <input type="hidden" name="app_code" value="<?php echo $app['app_code']; ?>">
                                                            <button type="submit" name="delete_application" class="btn btn-danger">Delete</button>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="69" class="text-center">No applications found</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <nav aria-label="Page navigation" class="mt-4">
                    <ul class="pagination justify-content-center">
                        <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                            <a class="page-link" href="<?php echo ($page <= 1) ? '#' : '?page='.($page-1).(empty($status_filter) ? '' : '&status='.urlencode($status_filter)).(empty($search) ? '' : '&search='.urlencode($search)); ?>">Previous</a>
                        </li>
                        <?php for($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?><?php echo empty($status_filter) ? '' : '&status='.urlencode($status_filter); ?><?php echo empty($search) ? '' : '&search='.urlencode($search); ?>"><?php echo $i; ?></a>
                        </li>
                        <?php endfor; ?>
                        <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                            <a class="page-link" href="<?php echo ($page >= $total_pages) ? '#' : '?page='.($page+1).(empty($status_filter) ? '' : '&status='.urlencode($status_filter)).(empty($search) ? '' : '&search='.urlencode($search)); ?>">Next</a>
                        </li>
                    </ul>
                </nav>
                <?php endif; ?>
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

        document.querySelectorAll('[id^="signatureModal"] img').forEach(img => {
            img.addEventListener('error', function() {
                console.error('Failed to load signature image:', this.src);
                this.src = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAwIiBoZWlnaHQ9IjEwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KICA8cmVjdCB3aWR0aD0iMjAwIiBoZWlnaHQ9IjEwMCIgZmlsbD0iI2Y4ZjlmYSIgc3Ryb2tlPSIjZGVlMmU2Ii8+CiAgPHRleHQgeD0iMTAwIiB5PSI1MCIgdGV4dC1hbmNob3I9Im1pZGRsZSIgZHk9IjAuM2VtIiBmb250LWZhbWlseT0iQXJpYWwiIGZvbnQtc2l6ZT0iMTQiIGZpbGw9IiM2Yzc1N2QiPk5vIFNpZ25hdHVyZTwvdGV4dD4KICA8L3N2Zz4K';
                this.alt = "Signature not available";
                this.style.border = "2px dashed #dc3545";
            });
            
            img.addEventListener('load', function() {
                console.log('Signature loaded successfully for:', this.src);
            });
            
            // Add loading indicator
            img.addEventListener('loadstart', function() {
                this.style.opacity = '0.5';
                console.log('Starting to load signature from:', this.src);
            });
            
            img.addEventListener('loadend', function() {
                this.style.opacity = '1';
            });
        });

        document.querySelectorAll('.update-status-form').forEach(form => {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                const appCode = formData.get('app_code');
                const newStatus = formData.get('new_status');
                
                // Get current URL parameters to check if we're in a filtered view
                const urlParams = new URLSearchParams(window.location.search);
                const currentStatusFilter = urlParams.get('status');
                
                fetch('ajax/update_status.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Update the status badge
                        const statusBadge = document.querySelector(`#status-badge-${appCode}`);
                        const statusDate = document.querySelector(`#status-date-${appCode}`);
                        
                        if (statusBadge && statusDate) {
                            statusBadge.textContent = data.new_status;
                            statusBadge.className = `badge ${data.badge_class}`;
                            statusDate.textContent = data.date;
                        }
                        
                        // Update status counts in navigation buttons
                        updateStatusCounts(data.status_counts);
                        
                        // Handle row visibility based on current filter
                        const appRow = document.querySelector(`#app-row-${appCode}`);
                        if (appRow) {
                            // If we're in a filtered view and the new status doesn't match the filter, hide the row
                            if (currentStatusFilter && currentStatusFilter.toUpperCase() !== data.new_status.toUpperCase()) {
                                // Add fade out animation
                                appRow.style.transition = 'opacity 0.5s ease-out';
                                appRow.style.opacity = '0';
                                
                                setTimeout(() => {
                                    appRow.style.display = 'none';
                                   
                                }, 500);
                            } else {
                                // If we're not in a filtered view or the status matches, just update the row
                                appRow.style.transition = 'background-color 0.3s ease';
                                appRow.style.backgroundColor = '#d4edda'; // Light green highlight
                                
                                setTimeout(() => {
                                    appRow.style.backgroundColor = '';
                                }, 2000);
                            }
                        }
                        
                        // Close the modal
                        const modal = document.querySelector(`#statusModal${appCode}`);
                        if (modal) {
                            bootstrap.Modal.getInstance(modal).hide();
                        }
                        
                        // Show success message with more details
                        let successMessage = `Status updated from ${data.old_status} to ${data.new_status}`;
                        if (currentStatusFilter && currentStatusFilter.toUpperCase() !== data.new_status.toUpperCase()) {
                            successMessage += `. Application moved to ${data.new_status.toLowerCase()} section.`;
                        }
                        showAlert('success', successMessage);
                        
                    } else {
                        showAlert('danger', data.message || 'Failed to update status');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showAlert('danger', 'An error occurred while processing your request.');
                });
            });
        });

        // Function to update status counts in navigation buttons
        function updateStatusCounts(counts) {
            // Update count badges in navigation buttons
            const buttons = {
                'all': document.querySelector('a[href="index.php"] .badge'),
                'pending': document.querySelector('a[href*="status=PENDING"] .badge'),
                'approved': document.querySelector('a[href*="status=APPROVED"] .badge'),
                'rejected': document.querySelector('a[href*="status=REJECTED"] .badge')
            };
            
            Object.keys(buttons).forEach(status => {
                const badge = buttons[status];
                if (badge && counts[status] !== undefined) {
                    badge.textContent = counts[status];
                    
                    // Add pulse animation to show the count has changed
                    badge.style.animation = 'pulse 0.6s ease-in-out';
                    setTimeout(() => {
                        badge.style.animation = '';
                    }, 600);
                }
            });
            
            // If we're on a filtered page and the count for that filter is 0, show a message
            const urlParams = new URLSearchParams(window.location.search);
            const currentStatusFilter = urlParams.get('status');
            
            if (currentStatusFilter) {
                const filterStatus = currentStatusFilter.toLowerCase();
                if (counts[filterStatus] === 0) {
                    // Check if there are any visible rows left
                    const visibleRows = document.querySelectorAll('tbody tr[id^="app-row-"]:not([style*="display: none"])');
                    if (visibleRows.length === 0) {
                        // Replace table content with "no applications" message
                        const tbody = document.querySelector('.comprehensive-table tbody');
                        if (tbody) {
                            tbody.innerHTML = `
                                <tr>
                                    <td colspan="69" class="text-center py-5">
                                        <i class="bi bi-inbox display-1 text-muted"></i>
                                        <h3 class="mt-3">No ${currentStatusFilter.charAt(0).toUpperCase() + currentStatusFilter.slice(1).toLowerCase()} Applications</h3>
                                        <p class="text-muted">All applications have been processed or moved to other statuses.</p>
                                        <a href="index.php" class="btn btn-primary">View All Applications</a>
                                    </td>
                                </tr>
                            `;
                        }
                    }
                }
            }
        }
        
        // AJAX for application deletion
        document.querySelectorAll('.delete-app-form').forEach(form => {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                const appCode = formData.get('app_code');
                
                fetch('ajax/delete_application.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Remove the row from the table
                        document.querySelector(`#app-row-${appCode}`).remove();
                        
                        // Close the modal
                        bootstrap.Modal.getInstance(document.querySelector(`#deleteModal${appCode}`)).hide();
                        
                        // Show success message
                        showAlert('success', data.message);
                    } else {
                        // Show error message
                        showAlert('danger', data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showAlert('danger', 'An error occurred while processing your request.');
                });
            });
        });
        
        // Function to display alerts
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
        
        // Handle clicks on family detail cells for better visibility
        document.querySelectorAll('.detailed-cell').forEach(cell => {
            cell.addEventListener('click', function() {
                this.classList.toggle('expanded');
            });
        });
        
        // Export functionality
        document.getElementById('exportBtn').addEventListener('click', function() {
            window.location.href = 'export.php';
        });
        
        // Advanced search handling
        const advancedSearchForm = document.getElementById('advancedSearchForm');
        if (advancedSearchForm) {
            advancedSearchForm.addEventListener('submit', function(e) {
                const emptyFields = Array.from(this.querySelectorAll('input[type="text"], select')).every(
                    field => field.value.trim() === '' || field.value === 'all'
                );
                
                if (emptyFields) {
                    e.preventDefault();
                    showAlert('warning', 'Please enter at least one search criteria.');
                }
            });
        }
        
        // Initialize tooltips for better UI experience
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
        
        // Handle family details toggling in mobile view
        document.querySelectorAll('.toggle-family-details').forEach(button => {
            button.addEventListener('click', function() {
                const targetId = this.getAttribute('data-target');
                const detailsContainer = document.getElementById(targetId);
                if (detailsContainer) {
                    detailsContainer.classList.toggle('show');
                    this.textContent = detailsContainer.classList.contains('show') ? 'Hide Details' : 'Show Details';
                }
            });
        });
    });
</script>
</body>
</html>