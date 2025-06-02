<?php
// filepath: c:\xampp\htdocs\Information Management\IM---Copy\admin\contacts\export.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../../vendor/autoload.php';
include "../config/database.php";

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;

// Set Philippine timezone for accurate date/time
date_default_timezone_set('Asia/Manila');

// Check if user is logged in
if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.php");
    exit();
}

// Check admin role - handle different session key possibilities
$user_role = $_SESSION["role"] ?? $_SESSION["user_role"] ?? null;
if (!$user_role || strtoupper($user_role) !== 'ADMIN') {
    $_SESSION['error_message'] = "Access denied. Admin privileges required.";
    header("Location: ../login.php");
    exit();
}

/**
 * Ensure the contact_message table has all required columns
 */
function ensureContactMessageColumns($pdo) {
    try {
        // Check if columns exist, if not, add them
        $columns_to_add = [
            'contm_deleted_at' => 'TIMESTAMP NULL',
            'contm_deleted_by' => 'INTEGER'
        ];
        
        foreach ($columns_to_add as $column => $definition) {
            // Check if column exists
            $stmt = $pdo->prepare("SELECT column_name FROM information_schema.columns 
                                  WHERE table_name = 'contact_message' AND column_name = :column_name");
            $stmt->execute([':column_name' => $column]);
            
            if (!$stmt->fetch()) {
                // Column doesn't exist, add it
                $pdo->exec("ALTER TABLE contact_message ADD COLUMN {$column} {$definition}");
                error_log("Added column {$column} to contact_message");
            }
        }
        
        // Create index on deleted_at column for better performance
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_contact_message_deleted_at ON contact_message(contm_deleted_at)");
        
    } catch (PDOException $e) {
        error_log("Error ensuring contact message columns: " . $e->getMessage());
        // Don't throw - this is not critical for basic functionality
    }
}

/**
 * Format datetime to match EXACTLY what index.php displays
 */
function formatDateTimeForExcel($datetime) {
    if (empty($datetime) || $datetime === null) {
        return 'N/A';
    }
    
    try {
        // Handle both string and DateTime objects
        if ($datetime instanceof DateTime) {
            $date = $datetime;
        } else {
            $date = new DateTime($datetime);
        }
        
        // Convert to Manila timezone (matching admin panel)
        $date->setTimezone(new DateTimeZone('Asia/Manila'));
        
        // Format exactly like admin panel: "Jun 02, 2025 12:53 AM"
        return $date->format('M d, Y g:i A');
        
    } catch (Exception $e) {
        error_log("Date formatting error: " . $e->getMessage());
        return $datetime; // Return original if formatting fails
    }
}

// Ensure columns exist
ensureContactMessageColumns($pdo);

// Get filter parameters from URL if present
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';
$export_type = isset($_GET['type']) ? $_GET['type'] : 'all'; // all, active, archived

try {
    // Build query conditions - PostgreSQL compatible
    $conditions = ["c.contm_deleted_at IS NULL"]; // Only non-deleted messages
    $params = [];
    
    // Add status filter condition
    if (!empty($status_filter)) {
        $conditions[] = "c.contm_status = :status_filter";
        $params[':status_filter'] = $status_filter;
    } elseif ($export_type === 'active') {
        $conditions[] = "c.contm_status != 'Archived'";
    } elseif ($export_type === 'archived') {
        $conditions[] = "c.contm_status = 'Archived'";
    }
    
    // Add search condition
    if (!empty($search)) {
        $conditions[] = "(c.contm_name ILIKE :search_term OR c.contm_email ILIKE :search_term OR c.contm_message ILIKE :search_term)";
        $params[':search_term'] = '%' . $search . '%';
    }
    
    // Build WHERE clause
    $where_clause = "WHERE " . implode(' AND ', $conditions);
    
    // Query to get contact messages - PostgreSQL compatible (NO REPLY COLUMNS)
    $query = "SELECT 
                c.contm_id,
                c.contm_name,
                c.contm_email,
                c.contm_phone,
                c.contm_message,
                c.contm_submission_date,
                c.contm_status
              FROM contact_message c {$where_clause} 
              ORDER BY c.contm_submission_date ASC, c.contm_id ASC";
    
    $stmt = $pdo->prepare($query);
    
    // Bind parameters
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    
    $stmt->execute();
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Create new Spreadsheet object
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Contact Messages');
    
    // Set document properties
    $spreadsheet->getProperties()
        ->setCreator("NCIP Admin")
        ->setLastModifiedBy("NCIP Admin")
        ->setTitle("NCIP Contact Messages Export")
        ->setSubject("Contact Messages")
        ->setDescription("Export of contact messages from NCIP website")
        ->setKeywords("NCIP contact messages export")
        ->setCompany("National Commission on Indigenous Peoples");

    // Define column letters (ONLY 7 COLUMNS - NO REPLY COLUMNS)
    $columns = ['A', 'B', 'C', 'D', 'E', 'F', 'G'];
    $last_column = 'G'; // Status is the last column
    
    // Set column widths (ADJUSTED FOR BETTER SPACING)
    $sheet->getColumnDimension('A')->setWidth(8);   // ID
    $sheet->getColumnDimension('B')->setWidth(25);  // Name (increased)
    $sheet->getColumnDimension('C')->setWidth(30);  // Email (increased)
    $sheet->getColumnDimension('D')->setWidth(18);  // Phone (increased)
    $sheet->getColumnDimension('E')->setWidth(50);  // Message (increased)
    $sheet->getColumnDimension('F')->setWidth(25);  // Submission Date (increased)
    $sheet->getColumnDimension('G')->setWidth(15);  // Status (increased)
    
    $current_row = 1;
    
    // TITLE ROW
    $sheet->setCellValue("A{$current_row}", 'NCIP CONTACT MESSAGES EXPORT');
    $sheet->mergeCells("A{$current_row}:{$last_column}{$current_row}");
    $sheet->getStyle("A{$current_row}:{$last_column}{$current_row}")->applyFromArray([
        'font' => [
            'bold' => true,
            'size' => 16,
            'color' => ['rgb' => 'FFFFFF']
        ],
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['rgb' => '2E5984']
        ],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
            'vertical' => Alignment::VERTICAL_CENTER
        ],
        'borders' => [
            'allBorders' => ['borderStyle' => Border::BORDER_THIN]
        ]
    ]);
    $sheet->getRowDimension($current_row)->setRowHeight(25);
    
    $current_row++;
    
    // EXPORT INFO ROW
    $export_info = 'Export Date: ' . date('F d, Y h:i:s A');
    if (!empty($status_filter)) {
        $export_info .= ' | Filter: Status = ' . $status_filter;
    } elseif ($export_type === 'active') {
        $export_info .= ' | Filter: Active Messages Only';
    } elseif ($export_type === 'archived') {
        $export_info .= ' | Filter: Archived Messages Only';
    } elseif (!empty($search)) {
        $export_info .= ' | Search: ' . $search;
    } else {
        $export_info .= ' | All Active Messages';
    }
    
    $sheet->setCellValue("A{$current_row}", $export_info);
    $sheet->mergeCells("A{$current_row}:{$last_column}{$current_row}");
    $sheet->getStyle("A{$current_row}:{$last_column}{$current_row}")->applyFromArray([
        'font' => ['size' => 10, 'italic' => true],
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['rgb' => 'F8F9FA']
        ],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        'borders' => [
            'allBorders' => ['borderStyle' => Border::BORDER_THIN]
        ]
    ]);
    
    $current_row++;
    
    // SUMMARY ROW
    $sheet->setCellValue("A{$current_row}", 'Total Messages: ' . count($messages));
    $sheet->mergeCells("A{$current_row}:{$last_column}{$current_row}");
    $sheet->getStyle("A{$current_row}:{$last_column}{$current_row}")->applyFromArray([
        'font' => ['bold' => true, 'size' => 11],
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['rgb' => 'E8F5E8']
        ],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        'borders' => [
            'allBorders' => ['borderStyle' => Border::BORDER_THIN]
        ]
    ]);
    
    $current_row += 2; // Add empty row
    
    // SECTION HEADERS ROW (UPDATED - NO REPLY SECTION)
    $sheet->setCellValue("A{$current_row}", 'Contact Information');
    $sheet->mergeCells("A{$current_row}:D{$current_row}");
    
    $sheet->setCellValue("E{$current_row}", 'Content');
    $sheet->mergeCells("E{$current_row}:F{$current_row}");
    
    $sheet->setCellValue("G{$current_row}", 'Status');
    
    $sheet->getStyle("A{$current_row}:{$last_column}{$current_row}")->applyFromArray([
        'font' => [
            'bold' => true,
            'size' => 11,
            'color' => ['rgb' => '2E5984']
        ],
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['rgb' => 'E3F2FD']
        ],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
            'vertical' => Alignment::VERTICAL_CENTER
        ],
        'borders' => [
            'allBorders' => ['borderStyle' => Border::BORDER_THIN]
        ]
    ]);
    
    $current_row++;
    
    // COLUMN HEADERS ROW (NO REPLY COLUMNS)
    $headers = ['ID', 'Name', 'Email', 'Phone', 'Message', 'Submission Date', 'Status'];
    
    foreach ($headers as $index => $header) {
        $col = $columns[$index];
        $sheet->setCellValue("{$col}{$current_row}", $header);
    }
    
    $sheet->getStyle("A{$current_row}:{$last_column}{$current_row}")->applyFromArray([
        'font' => [
            'bold' => true,
            'size' => 10,
            'color' => ['rgb' => 'FFFFFF']
        ],
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['rgb' => '4A90E2']
        ],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
            'vertical' => Alignment::VERTICAL_CENTER
        ],
        'borders' => [
            'allBorders' => ['borderStyle' => Border::BORDER_THIN]
        ]
    ]);
    
    $current_row++;
    $data_start_row = $current_row;
    
    // DATA ROWS (NO REPLY DATA)
    if (count($messages) > 0) {
        foreach ($messages as $msg) {
            // ID
            $sheet->setCellValue('A' . $current_row, $msg['contm_id']);
            
            // Name
            $sheet->setCellValue('B' . $current_row, $msg['contm_name']);
            
            // Email
            $sheet->setCellValue('C' . $current_row, $msg['contm_email']);
            
            // Phone
            $sheet->setCellValue('D' . $current_row, $msg['contm_phone'] ?? 'N/A');
            
            // Message
            $sheet->setCellValue('E' . $current_row, $msg['contm_message']);
            $sheet->getStyle('E' . $current_row)->getAlignment()->setWrapText(true);
            
            // Submission Date - EXACT FORMAT MATCHING ADMIN PANEL
            $formatted_submission_date = formatDateTimeForExcel($msg['contm_submission_date']);
            $sheet->setCellValue('F' . $current_row, $formatted_submission_date);
            
            // Status
            $sheet->setCellValue('G' . $current_row, $msg['contm_status']);
            
            $current_row++;
        }
    } else {
        // No messages found
        $sheet->setCellValue("A{$current_row}", 'No messages found matching the criteria');
        $sheet->mergeCells("A{$current_row}:{$last_column}{$current_row}");
        $sheet->getStyle("A{$current_row}:{$last_column}{$current_row}")->applyFromArray([
            'font' => ['italic' => true, 'color' => ['rgb' => '666666']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN]
            ]
        ]);
        $current_row++;
    }
    
    // Apply borders and styling to data rows
    if (count($messages) > 0) {
        $data_end_row = $current_row - 1;
        $sheet->getStyle("A{$data_start_row}:{$last_column}{$data_end_row}")->applyFromArray([
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN]
            ],
            'alignment' => [
                'vertical' => Alignment::VERTICAL_TOP
            ]
        ]);
        
        // Zebra striping for data rows
        for ($row = $data_start_row; $row <= $data_end_row; $row++) {
            if (($row - $data_start_row) % 2 == 1) {
                $sheet->getStyle("A{$row}:{$last_column}{$row}")->applyFromArray([
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'F8F9FA']
                    ]
                ]);
            }
        }
    }
    
    $current_row += 2; // Add empty row
    
    // FOOTER NOTE
    $footer_text = "Note: This export excludes messages that have been moved to the recycle bin. " .
                   "Generated on " . date('F d, Y \a\t h:i:s A') . " by " . 
                   ($_SESSION['username'] ?? 'Admin User');
    
    $sheet->setCellValue("A{$current_row}", $footer_text);
    $sheet->mergeCells("A{$current_row}:{$last_column}{$current_row}");
    $sheet->getStyle("A{$current_row}:{$last_column}{$current_row}")->applyFromArray([
        'font' => ['size' => 8, 'italic' => true, 'color' => ['rgb' => '666666']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
    ]);
    
    // Auto-fit row heights for rows with wrapped text
    foreach ($sheet->getRowIterator() as $row) {
        $sheet->getRowDimension($row->getRowIndex())->setRowHeight(-1);
    }
    
    // Log the export activity
    try {
        $log_query = "INSERT INTO user_logs (user_id, log_type, log_details, log_timestamp) 
                     VALUES (:user_id, 'EXPORT_CONTACT_MESSAGES', :details, CURRENT_TIMESTAMP)";
        $log_stmt = $pdo->prepare($log_query);
        
        $filter_info = [];
        if (!empty($status_filter)) $filter_info[] = "Status: {$status_filter}";
        if (!empty($search)) $filter_info[] = "Search: {$search}";
        if ($export_type !== 'all') $filter_info[] = "Type: {$export_type}";
        
        $details = "Exported " . count($messages) . " contact messages" . 
                  (!empty($filter_info) ? " (Filters: " . implode(', ', $filter_info) . ")" : "");
        
        $log_stmt->bindParam(":user_id", $_SESSION["user_id"]);
        $log_stmt->bindParam(":details", $details);
        $log_stmt->execute();
    } catch (Exception $log_error) {
        error_log("Failed to log export activity: " . $log_error->getMessage());
    }
    
    // Set headers for download
    $filename = 'NCIP_Contact_Messages_' . date('Y-m-d_H-i-s');
    if (!empty($status_filter)) {
        $filename .= '_' . str_replace(' ', '_', $status_filter);
    } elseif ($export_type !== 'all') {
        $filename .= '_' . ucfirst($export_type);
    }
    $filename .= '.xlsx';
    
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    header('Cache-Control: max-age=1');
    header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
    header('Cache-Control: cache, must-revalidate');
    header('Pragma: public');

    // Write file to output
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    
    // Clean up
    $spreadsheet->disconnectWorksheets();
    unset($spreadsheet);
    
} catch (PDOException $e) {
    // Log database error
    error_log("Excel export database error: " . $e->getMessage());
    
    // Create error spreadsheet
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Export Error');
    
    $sheet->setCellValue('A1', 'Export Error');
    $sheet->setCellValue('A3', 'An error occurred while generating the contact messages export.');
    $sheet->setCellValue('A4', 'Error: ' . $e->getMessage());
    $sheet->setCellValue('A5', 'Please contact your system administrator.');
    $sheet->setCellValue('A6', 'Debug Info: Check that all database columns exist and PostgreSQL datetime format is correct.');
    
    $sheet->getStyle('A1')->applyFromArray([
        'font' => ['bold' => true, 'size' => 16, 'color' => ['rgb' => 'FF0000']]
    ]);
    
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="Contact_Messages_Export_Error.xlsx"');
    header('Cache-Control: max-age=0');
    
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    
    $spreadsheet->disconnectWorksheets();
    unset($spreadsheet);
    
} catch (Exception $e) {
    // Log general error
    error_log("Excel export general error: " . $e->getMessage());
    
    // Fallback to simple error message
    header('Content-Type: text/plain');
    echo "Error generating export: " . $e->getMessage();
}

exit();
?>