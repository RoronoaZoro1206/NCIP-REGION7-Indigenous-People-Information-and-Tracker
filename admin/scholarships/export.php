<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include "../config/database.php";
require '../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

date_default_timezone_set('Asia/Manila');

if (!isset($_SESSION["user_id"]) || !isset($_SESSION["role"]) || strtoupper($_SESSION["role"]) !== 'ADMIN') {
    header("Location: ../login.php");
    exit();
}

$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';

$status_condition = '';
if (!empty($status_filter)) {
    $status_condition = "AND ss.status_code = :status_filter";
}

$search_condition = '';
if (!empty($search)) {
    $search_condition = "AND (a.app_last_name ILIKE :search_term OR a.app_first_name ILIKE :search_term OR 
                      a.app_ethno_group ILIKE :search_term OR a.app_email ILIKE :search_term)";
}

try {
    // Enhanced comprehensive query matching index.php structure
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
        
        -- All 7 Education Categories
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
         
        -- Family Background with individual member data
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
         
        -- Individual Family Members
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
         
        -- Siblings
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
         
        -- School Preference
        pref.pref_name,
        pref.pref_addr,
        pref.pref_degree,
        pref.pref_remarks,
        
        -- Signature and Status
        (SELECT CASE WHEN COUNT(*) > 0 THEN TRUE ELSE FALSE END 
         FROM signature_scan scan 
         WHERE scan.app_code = a.app_code) as has_signature,
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
    ORDER BY a.app_code DESC";
        
    $stmt = $pdo->prepare($detailed_query);
    if (!empty($status_filter)) {
        $stmt->bindParam(':status_filter', $status_filter);
    }
    if (!empty($search)) {
        $search_param = "%$search%";
        $stmt->bindParam(':search_term', $search_param);
    }
    $stmt->execute();
    $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Scholarship Applications');

    // Updated header groups to include all 7 education categories and enhanced family columns
    $headerGroups = [
        ['Application', 3],
        ['Admin Information', 6], 
        ['Applicant Information', 10], 
        ['Address', 6], 
        ['Education', 7], // Updated to 7 categories
        ['Family Background', 27], // Expanded family columns
        ['Siblings', 3],
        ['Preferred School', 4],
        ['Certification', 3],
        ['Status', 2]
    ];

    $columns = [
        // Application (3)
        'ID', 'App Number', 'Date Filed',
        
        // Admin Information (6)
        'CSC/PO Action', 'RO Action', 'CADT', 'Cong District', 'Remarks', 'Assistance Type',
        
        // Applicant Information (10)
        'Last Name', 'First Name', 'M.I.', 'Date of Birth', 'Place of Birth', 'Gender', 'Civil Status', 'Ethnic Group', 'Phone Number', 'Email',
        
        // Address (6)
        'Mailing', 'Permanent', 'House/Street', 'Barangay', 'Municipality', 'Province',
        
        // Education (7) - All categories
        'Elementary', 'High School', 'Vocational', 'College', 'Post-Graduate', 'Masteral', 'Doctorate',
        
        // Family Background (27) - Detailed breakdown
        'Father Name', 'Father Status', 'Father Address', 'Father Occupation', 'Father Office Address', 'Father Education', 'Father Ethnic Group', 'Father Income', 'Father ITR Year',
        'Mother Name', 'Mother Status', 'Mother Address', 'Mother Occupation', 'Mother Office Address', 'Mother Education', 'Mother Ethnic Group', 'Mother Income', 'Mother ITR Year',
        'Guardian Name', 'Guardian Status', 'Guardian Address', 'Guardian Occupation', 'Guardian Office Address', 'Guardian Education', 'Guardian Ethnic Group', 'Guardian Income', 'Guardian ITR Year',
        
        // Siblings (3)
        'Sibling Name', 'Scholarship', 'Course/Year',
        
        // Preferred School (4)
        'Name', 'Address', 'Degree', 'Remarks',
        
        // Certification (3)
        'Form Signed', 'Signature Date', 'E-Signature',
        
        // Status (2)
        'Status', 'Status Date'
    ];

    // Set header group row (Row 1)
    $col = 1;
    foreach ($headerGroups as $group) {
        $startCol = $col;
        $endCol = $col + $group[1] - 1;
        
        $startCell = Coordinate::stringFromColumnIndex($startCol);
        $endCell = Coordinate::stringFromColumnIndex($endCol);
        $cellRange = $startCell . '1:' . $endCell . '1';
        
        // Set the header group value
        $sheet->setCellValue($startCell . '1', $group[0]);
        
        // Merge cells for the header group
        $sheet->mergeCells($cellRange);
        
        // Style the header group
        $sheet->getStyle($cellRange)->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 14
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'color' => ['rgb' => 'D9E1F2'] // Light blue background
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN
                ],
                'outline' => [
                    'borderStyle' => Border::BORDER_MEDIUM
                ]
            ]
        ]);
        
        // Move to the next group
        $col += $group[1];
    }

    // Set column headers (Row 2)
    $colCount = count($columns);
    for ($i = 1; $i <= $colCount; $i++) {
        $cellCoord = Coordinate::stringFromColumnIndex($i) . '2';
        $sheet->setCellValue($cellCoord, $columns[$i - 1]);
        
        // Style the column headers
        $sheet->getStyle($cellCoord)->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 12,
                'color' => ['rgb' => 'FFFFFF'] // White text
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'color' => ['rgb' => '4472C4'] // Darker blue background
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN
                ]
            ]
        ]);
        
        // Auto-size the column
        $sheet->getColumnDimensionByColumn($i)->setAutoSize(true);
    }

    // Freeze header rows
    $sheet->freezePane('A3');

    // Data rows start at row 3
    $row = 3;
    foreach ($applications as $app) {
        $colIndex = 1;
        
        // Helper function to clean education data
        function cleanEducationData($educationString) {
            if (empty($educationString) || $educationString === 'School: N/A Address: N/A Year: N/A Grade: N/A Rank: N/A') {
                return 'N/A';
            }
            return $educationString;
        }
        
        // Sibling parsing for detailed breakdown
        $sibling_array = !empty($app['sibling_detail']) ? explode('||', $app['sibling_detail']) : [];
        $siblingNames = $scholarships = $courses = '';
        foreach ($sibling_array as $sib) {
            $nameParts = explode('(Scholarship:', $sib);
            $siblingNames .= trim($nameParts[0]) . "\n";
            if (strpos($sib, '(Scholarship:') !== false) {
                if (preg_match('/\(Scholarship: ([^,]+)/', $sib, $matches)) {
                    $scholarships .= $matches[1] . "\n";
                }
                if (preg_match('/Course: ([^,]+), Year: ([^\)]+)/', $sib, $matches)) {
                    $courses .= $matches[1] . ' - ' . $matches[2] . "\n";
                }
            }
        }

        // Data columns - Application
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $row, $app['app_code']);
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $row, $app['admin_app_number'] ?: 'N/A');
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $row, $app['admin_dof'] ? date('Y-m-d', strtotime($app['admin_dof'])) : 'N/A');
        
        // Admin Information
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $row, $app['admin_action_taken_csc_po'] ?: 'N/A');
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $row, $app['admin_action_taken_ro'] ?: 'N/A');
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $row, $app['admin_cadt'] ?: 'N/A');
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $row, $app['admin_cong_district'] ?: 'N/A');
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $row, $app['admin_add_remarks'] ?: 'N/A');
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $row, $app['admin_assistance_type'] ?: 'N/A');
        
        // Applicant Information
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $row, $app['app_last_name']);
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $row, $app['app_first_name']);
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $row, $app['app_middle_initial'] ?: 'N/A');
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $row, $app['app_date_of_birth'] ? date('Y-m-d', strtotime($app['app_date_of_birth'])) : 'N/A');
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $row, $app['app_place_of_birth'] ?: 'N/A');
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $row, $app['app_gender'] ?: 'N/A');
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $row, $app['app_civil_status'] ?: 'N/A');
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $row, $app['app_ethno_group'] ?: 'N/A');
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $row, $app['app_phone_number'] ?: 'N/A');
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $row, $app['app_email'] ?: 'N/A');
        
        // Address
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $row, $app['addr_mail'] ?: 'N/A');
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $row, $app['addr_permanent'] ?: 'N/A');
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $row, $app['addr_house_and_street'] ?: 'N/A');
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $row, $app['addr_barangay'] ?: 'N/A');
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $row, $app['addr_municipality'] ?: 'N/A');
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $row, $app['addr_province'] ?: 'N/A');
        
        // Education - All 7 categories
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $row, cleanEducationData($app['elementary_education']));
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $row, cleanEducationData($app['high_school_education']));
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $row, cleanEducationData($app['vocational_education']));
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $row, cleanEducationData($app['college_education']));
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $row, cleanEducationData($app['post_graduate_education']));
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $row, cleanEducationData($app['masteral_education']));
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $row, cleanEducationData($app['doctorate_education']));
        
        // Family Background - Father (9 columns)
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $row, $app['father_name'] ?: 'N/A');
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $row, isset($app['father_is_alive']) ? ($app['father_is_alive'] ? 'Living' : 'Deceased') : 'N/A');
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $row, $app['father_address'] ?: 'N/A');
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $row, $app['father_occupation'] ?: 'N/A');
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $row, $app['father_office_addr'] ?: 'N/A');
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $row, $app['father_education'] ?: 'N/A');
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $row, $app['father_ethnicity'] ?: 'N/A');
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $row, isset($app['father_income']) ? '₱' . number_format($app['father_income'], 2) : 'N/A');
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $row, $app['father_itr_year'] ?: 'N/A');
        
        // Family Background - Mother (9 columns)
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $row, $app['mother_name'] ?: 'N/A');
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $row, isset($app['mother_is_alive']) ? ($app['mother_is_alive'] ? 'Living' : 'Deceased') : 'N/A');
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $row, $app['mother_address'] ?: 'N/A');
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $row, $app['mother_occupation'] ?: 'N/A');
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $row, $app['mother_office_addr'] ?: 'N/A');
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $row, $app['mother_education'] ?: 'N/A');
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $row, $app['mother_ethnicity'] ?: 'N/A');
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $row, isset($app['mother_income']) ? '₱' . number_format($app['mother_income'], 2) : 'N/A');
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $row, $app['mother_itr_year'] ?: 'N/A');
        
        // Family Background - Guardian (9 columns)
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $row, $app['guardian_name'] ?: 'N/A');
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $row, isset($app['guardian_is_alive']) ? ($app['guardian_is_alive'] ? 'Living' : 'Deceased') : 'N/A');
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $row, $app['guardian_address'] ?: 'N/A');
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $row, $app['guardian_occupation'] ?: 'N/A');
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $row, $app['guardian_office_addr'] ?: 'N/A');
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $row, $app['guardian_education'] ?: 'N/A');
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $row, $app['guardian_ethnicity'] ?: 'N/A');
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $row, isset($app['guardian_income']) ? '₱' . number_format($app['guardian_income'], 2) : 'N/A');
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $row, $app['guardian_itr_year'] ?: 'N/A');
        
        // Siblings
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $row, trim($siblingNames) ?: 'None');
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $row, trim($scholarships) ?: 'N/A');
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $row, trim($courses) ?: 'N/A');
        
        // Preferred School
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $row, $app['pref_name'] ?: 'N/A');
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $row, $app['pref_addr'] ?: 'N/A');
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $row, $app['pref_degree'] ?: 'N/A');
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $row, $app['pref_remarks'] ?: 'N/A');
        
        // Certification
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $row, $app['app_form_signed'] ? 'Yes' : 'No');
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $row, $app['app_signature_date'] ? date('Y-m-d', strtotime($app['app_signature_date'])) : 'N/A');
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $row, $app['has_signature'] ? 'Yes' : 'No');
        
        // Status
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $row, strtoupper($app['status'] ?: 'PENDING'));
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $row, $app['status_date'] ? date('Y-m-d', strtotime($app['status_date'])) : 'N/A');
        
        // Style the data rows
        for ($i = 1; $i < $colIndex; $i++) {
            $cellCoord = Coordinate::stringFromColumnIndex($i) . $row;
            $sheet->getStyle($cellCoord)->applyFromArray([
                'alignment' => [
                    'vertical' => Alignment::VERTICAL_TOP,
                    'wrapText' => true
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN
                    ]
                ]
            ]);
        }
        $row++;
    }

    // Add row height for the header rows
    $sheet->getRowDimension(1)->setRowHeight(30);
    $sheet->getRowDimension(2)->setRowHeight(25);

    // Apply column groupings with vertical borders
    $startColumn = 1;
    foreach ($headerGroups as $group) {
        $endColumn = $startColumn + $group[1] - 1;
        
        // Add thick vertical borders to separate groups
        if ($startColumn > 1) {
            for ($rowNum = 1; $rowNum <= $sheet->getHighestRow(); $rowNum++) {
                $leftBorderCell = Coordinate::stringFromColumnIndex($startColumn - 1) . $rowNum;
                $sheet->getStyle($leftBorderCell)->getBorders()->getRight()->setBorderStyle(Border::BORDER_MEDIUM);
                
                $rightBorderCell = Coordinate::stringFromColumnIndex($startColumn) . $rowNum;
                $sheet->getStyle($rightBorderCell)->getBorders()->getLeft()->setBorderStyle(Border::BORDER_MEDIUM);
            }
        }
        
        $startColumn = $endColumn + 1;
    }

    // Generate filename with current filter status
    $filename = "scholarship_applications_" . date('Y-m-d');
    if (!empty($status_filter)) {
        $filename .= "_" . strtolower($status_filter);
    }
    if (!empty($search)) {
        $filename .= "_search";
    }
    $filename .= ".xlsx";

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
    
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Database error: " . $e->getMessage();
    header("Location: index.php");
    exit();
} catch (Exception $e) {
    $_SESSION['error_message'] = "Error generating Excel file: " . $e->getMessage();
    header("Location: index.php");
    exit();
}
?>