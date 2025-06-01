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
    $status_condition = "AND ss.STATUS_CODE = :status_filter";
}

$search_condition = '';
if (!empty($search)) {
    $search_condition = "AND (a.APP_LAST_NAME ILIKE :search_term OR a.APP_FIRST_NAME ILIKE :search_term OR 
                      a.APP_ETHNO_GROUP ILIKE :search_term OR a.APP_EMAIL ILIKE :search_term)";
}

try {
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
         WHERE ea.APP_CODE = a.APP_CODE) as education_detail,
        (SELECT string_agg(CONCAT(
            fb.FAM_RELATION, ': ',
            fb.FAM_NAME, 
            ' (', CASE WHEN fb.FAM_IS_ALIVE THEN 'Living' ELSE 'Deceased' END, ')', 
            ' Address: ', fb.FAM_ADDR, 
            ' Occupation: ', fb.FAM_OCCUP, 
            ' Office Address: ', fb.FAM_OFFICE_ADDR, 
            ' Education: ', fb.FAM_EDUC_ATTAIN, 
            ' Ethnic Group: ', fb.FAM_ETHNO_GROUP, 
            ' Annual Income: ₱', fb.FAM_ANNUAL_INCOME, 
            ' ITR Year: ', fb.FAM_YEAR_ITR
        ), '||') 
         FROM FAMILY_BACKGROUND fb 
         WHERE fb.APP_CODE = a.APP_CODE) as family_detail,
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
         WHERE sib.APP_CODE = a.APP_CODE) as sibling_detail,
        pref.PREF_NAME,
        pref.PREF_ADDR,
        pref.PREF_DEGREE,
        pref.PREF_REMARKS,
        (SELECT CASE WHEN COUNT(*) > 0 THEN TRUE ELSE FALSE END 
         FROM SIGNATURE_SCAN scan 
         WHERE scan.APP_CODE = a.APP_CODE) as has_signature,
        (SELECT ss.STATUS_CODE FROM SCHOLARSHIP_STATUS ss 
         WHERE ss.APP_CODE = a.APP_CODE AND ss.STATUS_IS_ACTIVE = TRUE LIMIT 1) as status,
        (SELECT ss.STATUS_DATE FROM SCHOLARSHIP_STATUS ss 
         WHERE ss.APP_CODE = a.APP_CODE AND ss.STATUS_IS_ACTIVE = TRUE LIMIT 1) as status_date
    FROM APPLICANT a
    LEFT JOIN ADMINISTRATOR adm ON a.ADMIN_CODE = adm.ADMIN_CODE
    LEFT JOIN ADDRESS addr ON a.APP_CODE = addr.APP_CODE
    LEFT JOIN SCHOOL_PREFERENCE pref ON a.APP_CODE = pref.APP_CODE
    LEFT JOIN SCHOLARSHIP_STATUS ss ON a.APP_CODE = ss.APP_CODE AND ss.STATUS_IS_ACTIVE = TRUE
    WHERE 1=1 $status_condition $search_condition
    GROUP BY a.APP_CODE, adm.ADMIN_CODE, addr.ADDR_CODE, pref.PREF_CODE
    ORDER BY a.APP_CODE DESC";
        
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

    // Header groups and columns to match your old Excel format
    $headerGroups = [
        ['Application', 3],
        ['Admin Information', 6], 
        ['Applicant Information', 10], 
        ['Address', 6], 
        ['Education', 4],
        ['Family Background', 10], 
        ['Siblings', 3],
        ['Preferred School', 4],
        ['Certification', 3],
        ['Status', 2]
    ];

    $columns = [
        'ID', 'App Number', 'Date Filed',
        'CSC/PO Action', 'RO Action', 'CADT', 'Cong District', 'Remarks', 'Assistance Type',
        'Last Name', 'First Name', 'M.I.', 'Date of Birth', 'Place of Birth', 'Gender', 'Civil Status', 'Ethnic Group', 'Phone Number', 'Email',
        'Mailing', 'Permanent', 'House/Street', 'Barangay', 'Municipality', 'Province',
        'Elementary', 'High School', 'Vocational', 'College',
        'Father Name', 'Father Status', 'Father Income', 'Mother Name', 'Mother Status', 'Mother Income', 'Guardian Name', 'Guardian Status', 'Guardian Income', 'Family Details',
        'Sibling Name', 'Scholarship', 'Course/Year',
        'Name', 'Address', 'Degree', 'Remarks',
        'Form Signed', 'Signature Date', 'E-Signature',
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
        
        // Education parsing
        $education_array = !empty($app['education_detail']) ? explode('||', $app['education_detail']) : [];
        $elementary = $highSchool = $vocational = $college = 'N/A';
        foreach ($education_array as $edu) {
            if (strpos($edu, 'Elementary:') === 0) {
                $elementary = trim(str_replace('Elementary:', '', $edu));
            } else if (strpos($edu, 'High School:') === 0) {
                $highSchool = trim(str_replace('High School:', '', $edu));
            } else if (strpos($edu, 'Vocational:') === 0) {
                $vocational = trim(str_replace('Vocational:', '', $edu));
            } else if (strpos($edu, 'College/Undergraduate:') === 0) {
                $college = trim(str_replace('College/Undergraduate:', '', $edu));
            }
        }
        
        // Family parsing
        $family_array = !empty($app['family_detail']) ? explode('||', $app['family_detail']) : [];
        $fatherName = $fatherStatus = $fatherIncome = $motherName = $motherStatus = $motherIncome = $guardianName = $guardianStatus = $guardianIncome = 'N/A';
        $familyDetails = '';
        foreach ($family_array as $fam) {
            if (strpos($fam, 'Father:') === 0) {
                $parts = explode('Address:', $fam);
                $fatherName = trim(substr($parts[0], 8));
                $fatherStatus = (strpos($fam, '(Living)') !== false) ? 'Living' : 'Deceased';
                if (preg_match('/Annual Income: ₱([^,]+)/', $fam, $matches)) {
                    $fatherIncome = "₱" . $matches[1];
                }
                $familyDetails .= $fam . "\n";
            } else if (strpos($fam, 'Mother:') === 0) {
                $parts = explode('Address:', $fam);
                $motherName = trim(substr($parts[0], 8));
                $motherStatus = (strpos($fam, '(Living)') !== false) ? 'Living' : 'Deceased';
                if (preg_match('/Annual Income: ₱([^,]+)/', $fam, $matches)) {
                    $motherIncome = "₱" . $matches[1];
                }
                $familyDetails .= $fam . "\n";
            } else if (strpos($fam, 'Guardian:') === 0) {
                $parts = explode('Address:', $fam);
                $guardianName = trim(substr($parts[0], 10));
                $guardianStatus = (strpos($fam, '(Living)') !== false) ? 'Living' : 'Deceased';
                if (preg_match('/Annual Income: ₱([^,]+)/', $fam, $matches)) {
                    $guardianIncome = "₱" . $matches[1];
                }
                $familyDetails .= $fam . "\n";
            }
        }
        
        // Sibling parsing
        $sibling_array = !empty($app['sibling_detail']) ? explode('||', $app['sibling_detail']) : [];
        $siblingNames = $scholarships = $courses = '';
        foreach ($sibling_array as $sib) {
            $nameParts = explode('(Scholarship:', $sib);
            $siblingNames .= trim($nameParts[0]) . "\n";
            if (strpos($sib, '(Scholarship:') !== false) {
                if (preg_match('/\(Scholarship: ([^,]+)/', $sib, $matches)) {
                    $scholarships .= $matches[1] . "\n";
                }
            }
            if (strpos($sib, 'Course:') !== false) {
                if (preg_match('/Course: ([^,]+), Year: ([^\)]+)/', $sib, $matches)) {
                    $courses .= $matches[1] . ' - ' . $matches[2] . "\n";
                }
            }
        }

        // Data columns
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $row, $app['app_code']);
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $row, $app['admin_app_number']);
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $row, $app['admin_dof'] ? date('Y-m-d', strtotime($app['admin_dof'])) : 'N/A');
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $row, $app['admin_action_taken_csc_po'] ?: 'N/A');
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $row, $app['admin_action_taken_ro'] ?: 'N/A');
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $row, $app['admin_cadt'] ?: 'N/A');
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $row, $app['admin_cong_district'] ?: 'N/A');
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $row, $app['admin_add_remarks'] ?: 'N/A');
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $row, $app['admin_assistance_type'] ?: 'N/A');
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $row, $app['app_last_name']);
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $row, $app['app_first_name']);
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $row, $app['app_middle_initial']);
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $row, $app['app_date_of_birth'] ? date('Y-m-d', strtotime($app['app_date_of_birth'])) : 'N/A');
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $row, $app['app_place_of_birth']);
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $row, $app['app_gender']);
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $row, $app['app_civil_status']);
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $row, $app['app_ethno_group']);
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $row, $app['app_phone_number'] ?: 'N/A');
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $row, $app['app_email'] ?: 'N/A');
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $row, $app['addr_mail'] ?: 'N/A');
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $row, $app['addr_permanent']);
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $row, $app['addr_house_and_street']);
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $row, $app['addr_barangay']);
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $row, $app['addr_municipality']);
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $row, $app['addr_province']);
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $row, $elementary);
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $row, $highSchool);
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $row, $vocational);
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $row, $college);
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $row, $fatherName);
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $row, $fatherStatus);
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $row, $fatherIncome);
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $row, $motherName);
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $row, $motherStatus);
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $row, $motherIncome);
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $row, $guardianName);
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $row, $guardianStatus);
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $row, $guardianIncome);
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $row, $familyDetails);
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $row, $siblingNames ?: 'None');
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $row, $scholarships ?: 'N/A');
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $row, $courses ?: 'N/A');
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $row, $app['pref_name'] ?: 'N/A');
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $row, $app['pref_addr'] ?: 'N/A');
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $row, $app['pref_degree'] ?: 'N/A');
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $row, $app['pref_remarks'] ?: 'N/A');
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $row, $app['app_form_signed'] ? 'Yes' : 'No');
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $row, $app['app_signature_date'] ? date('Y-m-d', strtotime($app['app_signature_date'])) : 'N/A');
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $row, $app['has_signature'] ? 'Yes' : 'No');
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex++) . $row, $app['status'] ?: 'PENDING');
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

    // Add a row height for the header rows
    $sheet->getRowDimension(1)->setRowHeight(30);
    $sheet->getRowDimension(2)->setRowHeight(25);

    // Apply column groupings with vertical borders
    $startColumn = 1;
    foreach ($headerGroups as $group) {
        $endColumn = $startColumn + $group[1] - 1;
        
        // Add thick vertical borders to separate groups
        if ($startColumn > 1) {
            for ($row = 1; $row <= $sheet->getHighestRow(); $row++) {
                $leftBorderCell = Coordinate::stringFromColumnIndex($startColumn - 1) . $row;
                $sheet->getStyle($leftBorderCell)->getBorders()->getRight()->setBorderStyle(Border::BORDER_MEDIUM);
                
                $rightBorderCell = Coordinate::stringFromColumnIndex($startColumn) . $row;
                $sheet->getStyle($rightBorderCell)->getBorders()->getLeft()->setBorderStyle(Border::BORDER_MEDIUM);
            }
        }
        
        $startColumn = $endColumn + 1;
    }

    $filename = "scholarship_applications_" . date('Y-m-d') . ".xlsx";
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