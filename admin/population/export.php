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

// Set Philippine timezone for accurate date/time
date_default_timezone_set('Asia/Manila');

// Check if user is logged in and has admin role
if (!isset($_SESSION["user_id"]) || !isset($_SESSION["role"]) || strtoupper($_SESSION["role"]) !== 'ADMIN') {
    header("Location: ../login.php");
    exit();
}

try {
    // Different export mode based on whether ID is provided
    if (isset($_GET['id']) && !empty($_GET['id'])) {
        // SINGLE RECORD EXPORT - Keeps your existing detailed export functionality
        $population_id = (int)$_GET['id'];

        // Get population data
        $query = "SELECT 
                    p.popt_id,
                    p.popt_name,
                    p.popt_ip_group,
                    p.popt_latitude,
                    p.popt_longitude,
                    p.popt_population
                 FROM population_table p
                 WHERE p.popt_id = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$population_id]);
        $population = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$population) {
            $_SESSION['error_message'] = "Population data not found.";
            header("Location: index.php");
            exit();
        }

        // Get group statistics
        $group_query = "SELECT SUM(popt_population) as total_group_population, 
                        COUNT(*) as total_locations 
                        FROM population_table 
                        WHERE popt_ip_group = ?";
        $group_stmt = $pdo->prepare($group_query);
        $group_stmt->execute([$population['popt_ip_group']]);
        $group_stats = $group_stmt->fetch(PDO::FETCH_ASSOC);

        // Calculate percentage
        $percentage = 0;
        if ($group_stats['total_group_population'] > 0) {
            $percentage = ($population['popt_population'] / $group_stats['total_group_population']) * 100;
        }

        // Get nearby locations (PostgreSQL version)
        $nearby_query = "SELECT 
                            popt_id,
                            popt_name,
                            popt_population,
                            popt_latitude,
                            popt_longitude,
                            (point(popt_longitude, popt_latitude) <-> point(?, ?)) as distance
                        FROM population_table
                        WHERE popt_ip_group = ? AND popt_id != ?
                        ORDER BY distance
                        LIMIT 5";
        $nearby_stmt = $pdo->prepare($nearby_query);
        $nearby_stmt->execute([
            $population['popt_longitude'],
            $population['popt_latitude'],
            $population['popt_ip_group'],
            $population_id
        ]);
        $nearby_locations = $nearby_stmt->fetchAll(PDO::FETCH_ASSOC);

        // Create Spreadsheet
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Population Data');

        // Styles
        $titleStyle = [
            'font' => ['bold' => true, 'size' => 16, 'color' => ['rgb' => 'FFFFFF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4472C4']],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
        ];
        $sectionHeaderStyle = [
            'font' => ['bold' => true, 'size' => 12],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E6F2FF']],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
        ];
        $headerStyle = [
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D9E1F2']],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER]
        ];
        $dataStyle = [
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true]
        ];
        $numberStyle = [
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
            'numberFormat' => ['formatCode' => '#,##0'],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT, 'vertical' => Alignment::VERTICAL_CENTER]
        ];
        $percentStyle = [
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
            'numberFormat' => ['formatCode' => '0.0%'],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT, 'vertical' => Alignment::VERTICAL_CENTER]
        ];

        // Set column widths
        $sheet->getColumnDimension('A')->setWidth(29);
        $sheet->getColumnDimension('B')->setWidth(29);
        $sheet->getColumnDimension('C')->setWidth(4.5);
        $sheet->getColumnDimension('D')->setWidth(29);
        $sheet->getColumnDimension('E')->setWidth(29);
        $sheet->getColumnDimension('F')->setWidth(4.5);
        $sheet->getColumnDimension('G')->setWidth(29);
        $sheet->getColumnDimension('H')->setWidth(29);

        // Title
        $sheet->mergeCells('A1:H1');
        $sheet->setCellValue('A1', 'NCIP INDIGENOUS POPULATION DATA REPORT');
        $sheet->getStyle('A1:H1')->applyFromArray($titleStyle);
        $sheet->getRowDimension(1)->setRowHeight(40.00);

        // Subtitle
        $sheet->mergeCells('A2:H2');
        $sheet->setCellValue('A2', $population['popt_name'] . ' - ' . $population['popt_ip_group'] . ' Indigenous People');
        $sheet->getStyle('A2:H2')->applyFromArray($sectionHeaderStyle);
        $sheet->getRowDimension(2)->setRowHeight(30.00);

        // Empty Row
        $sheet->getRowDimension(3)->setRowHeight(15.00);

        // Section Headers (Row 4)
        $sheet->mergeCells('A4:B4');
        $sheet->setCellValue('A4', 'Location Information');
        $sheet->getStyle('A4:B4')->applyFromArray($sectionHeaderStyle);

        $sheet->mergeCells('C4:E4');
        $sheet->setCellValue('C4', 'Geographic Details');
        $sheet->getStyle('C4:E4')->applyFromArray($sectionHeaderStyle);

        $sheet->mergeCells('F4:H4');
        $sheet->setCellValue('F4', 'Group Statistics');
        $sheet->getStyle('F4:H4')->applyFromArray($sectionHeaderStyle);
        $sheet->getRowDimension(4)->setRowHeight(27.00);

        // Row 5
        $sheet->setCellValue('A5', 'Location Name');
        $sheet->setCellValue('B5', $population['popt_name']);
        $sheet->setCellValue('C5', ''); 
        $sheet->setCellValue('D5', 'Latitude');
        $sheet->setCellValue('E5', $population['popt_latitude']);
        $sheet->setCellValue('F5', ''); 
        $sheet->setCellValue('G5', 'Total IP Group Population');
        $sheet->setCellValue('H5', number_format($group_stats['total_group_population']));
        $sheet->getStyle('A5:H5')->applyFromArray($dataStyle);
        $sheet->getStyle('A5')->applyFromArray($headerStyle);
        $sheet->getStyle('D5')->applyFromArray($headerStyle);
        $sheet->getStyle('G5')->applyFromArray($headerStyle);
        $sheet->getStyle('H5')->applyFromArray($numberStyle);
        $sheet->getRowDimension(5)->setRowHeight(30.00); 

        // Row 6
        $sheet->setCellValue('A6', 'IP Group');
        $sheet->setCellValue('B6', $population['popt_ip_group']);
        $sheet->setCellValue('C6', ''); 
        $sheet->setCellValue('D6', 'Longitude');
        $sheet->setCellValue('E6', $population['popt_longitude']);
        $sheet->setCellValue('F6', ''); 
        $sheet->setCellValue('G6', 'Total Locations');
        $sheet->setCellValue('H6', $group_stats['total_locations']);
        $sheet->getStyle('A6:H6')->applyFromArray($dataStyle);
        $sheet->getStyle('A6')->applyFromArray($headerStyle);
        $sheet->getStyle('D6')->applyFromArray($headerStyle);
        $sheet->getStyle('G6')->applyFromArray($headerStyle);
        $sheet->getRowDimension(6)->setRowHeight(30.00); 

        // Row 7
        $sheet->setCellValue('A7', 'Population');
        $sheet->setCellValue('B7', $population['popt_population']);
        $sheet->setCellValue('C7', '');
        $sheet->setCellValue('D7', 'Map Link');
        $mapLink = 'https://www.google.com/maps?q=' . $population['popt_latitude'] . ',' . $population['popt_longitude'];
        $sheet->setCellValue('E7', $mapLink);
        $sheet->setCellValue('F7', ''); 
        $sheet->setCellValue('G7', 'Percentage of Group Total');
        $sheet->setCellValue('H7', $percentage / 100);
        $sheet->getStyle('A7:H7')->applyFromArray($dataStyle);
        $sheet->getStyle('A7')->applyFromArray($headerStyle);
        $sheet->getStyle('D7')->applyFromArray($headerStyle);
        $sheet->getStyle('G7')->applyFromArray($headerStyle);
        $sheet->getStyle('B7')->applyFromArray($numberStyle);
        $sheet->getStyle('H7')->applyFromArray($percentStyle);
        $sheet->getRowDimension(7)->setRowHeight(30.00); 

        // Empty Row
        $sheet->getRowDimension(8)->setRowHeight(15.00);

        // Nearby Locations Section Header
        $sheet->mergeCells('A9:H9');
        $sheet->setCellValue('A9', 'Nearby ' . $population['popt_ip_group'] . ' Locations');
        $sheet->getStyle('A9:H9')->applyFromArray($sectionHeaderStyle);
        $sheet->getRowDimension(9)->setRowHeight(27.00);

        // Nearby Locations Table Header (Row 10)
        $sheet->setCellValue('A10', 'Location Name');
        $sheet->setCellValue('B10', 'Population');
        $sheet->setCellValue('C10', '');
        $sheet->setCellValue('D10', 'Latitude');
        $sheet->setCellValue('E10', 'Longitude');
        $sheet->setCellValue('F10', '');
        $sheet->setCellValue('G10', 'Distance (km)');
        $sheet->setCellValue('H10', '% of Group Total');
        $sheet->getStyle('A10:H10')->applyFromArray($headerStyle);
        $sheet->getRowDimension(10)->setRowHeight(30.00);

        // Nearby Location Data (Rows 11+)
        $row = 11;
        if (count($nearby_locations) > 0) {
            foreach ($nearby_locations as $location) {
                $loc_percentage = ($group_stats['total_group_population'] > 0)
                    ? ($location['popt_population'] / $group_stats['total_group_population']) * 100
                    : 0;
                $distance_km = $location['distance'] * 111.32; // Approx conversion from degrees to km at equator

                $sheet->setCellValue('A' . $row, $location['popt_name']);
                $sheet->setCellValue('B' . $row, $location['popt_population']);
                $sheet->setCellValue('D' . $row, $location['popt_latitude']);
                $sheet->setCellValue('E' . $row, $location['popt_longitude']);
                $sheet->setCellValue('G' . $row, number_format($distance_km, 2));
                $sheet->setCellValue('H' . $row, $loc_percentage / 100);

                $sheet->getStyle('A' . $row . ':H' . $row)->applyFromArray($dataStyle);
                $sheet->getStyle('B' . $row)->applyFromArray($numberStyle);
                $sheet->getStyle('H' . $row)->applyFromArray($percentStyle);
                $sheet->getRowDimension($row)->setRowHeight(30.00);
                $row++;
            }
        } else {
            $sheet->mergeCells('A11:H11');
            $sheet->setCellValue('A11', 'No nearby locations found');
            $sheet->getStyle('A11')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getRowDimension(11)->setRowHeight(30.00);
            $row++;
        }

        // Empty Row
        $sheet->getRowDimension($row)->setRowHeight(15.00);
        $row++;

        // Summary Section
        $sheet->mergeCells('A' . $row . ':H' . $row);
        $sheet->setCellValue('A' . $row, 'Summary Information');
        $sheet->getStyle('A' . $row . ':H' . $row)->applyFromArray($sectionHeaderStyle);
        $sheet->getRowDimension($row)->setRowHeight(27.00);
        $row++;

        // Summary Data - fill all columns including C and F
        $population_percentage = ($group_stats['total_group_population'] > 0)
            ? ($population['popt_population'] / $group_stats['total_group_population']) * 100
            : 0;
        $sheet->mergeCells('A' . $row . ':H' . $row);
        $sheet->setCellValue('A' . $row, 'This location contains ' . number_format($population['popt_population']) . ' ' . $population['popt_ip_group'] .
            ' indigenous people, which represents ' . number_format($population_percentage, 1) . '% of the total ' .
            $population['popt_ip_group'] . ' population across all recorded locations.');
        $sheet->getStyle('A' . $row . ':H' . $row)->applyFromArray($dataStyle);
        $sheet->getStyle('A' . $row)->getAlignment()->setWrapText(true);
        $sheet->getRowDimension($row)->setRowHeight(30.00);
        $row++;

        // Second summary row - fill all columns including C and F
        $sheet->mergeCells('A' . $row . ':H' . $row);
        $sheet->setCellValue('A' . $row, 'Total ' . $population['popt_ip_group'] . ' population within the system: ' .
            number_format($group_stats['total_group_population']) . ' people across ' .
            $group_stats['total_locations'] . ' locations.');
        $sheet->getStyle('A' . $row . ':H' . $row)->applyFromArray($dataStyle);
        $sheet->getStyle('A' . $row)->getAlignment()->setWrapText(true);
        $sheet->getRowDimension($row)->setRowHeight(30.00);
        $row += 2;

        // Export Information with accurate Philippine date/time format
        $current_date = date('d/m/Y'); 
        $current_time = date('h:i A'); 

        $sheet->mergeCells('A' . $row . ':D' . $row);
        $sheet->setCellValue('A' . $row, 'Export Date: ' . $current_date . ' ' . $current_time);
        $sheet->getStyle('A' . $row . ':D' . $row)->applyFromArray($dataStyle);

        $sheet->mergeCells('E' . $row . ':H' . $row);
        $sheet->setCellValue('E' . $row, 'Generated By: ' . htmlspecialchars($_SESSION["username"] ?? "Lawrence Ramirez"));
        $sheet->getStyle('E' . $row . ':H' . $row)->applyFromArray($dataStyle);
        $sheet->getRowDimension($row)->setRowHeight(30.00);

        // Set filename for Excel download with accurate date format
        $safe_filename = preg_replace('/[^a-z0-9]+/', '_', strtolower($population['popt_name']));
        $filename = "population_data_{$safe_filename}_" . date('d_m_Y') . ".xlsx";
    } 
    else {
        // ALL RECORDS EXPORT - New functionality for index.php
        // Get search and filter parameters
        $search = isset($_GET['search']) ? $_GET['search'] : '';
        $ip_group_filter = isset($_GET['ip_group']) ? $_GET['ip_group'] : '';
        
        // Build query conditions
        $where_conditions = [];
        $params = [];

        if (!empty($search)) {
            $where_conditions[] = "(popt_name ILIKE ? OR popt_ip_group ILIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }

        if (!empty($ip_group_filter)) {
            $where_conditions[] = "popt_ip_group = ?";
            $params[] = $ip_group_filter;
        }

        $where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";
        
        // Get all population data, sorted by ID
        $query = "SELECT 
                    popt_id,
                    popt_name,
                    popt_ip_group,
                    popt_latitude,
                    popt_longitude,
                    popt_population
                  FROM population_table $where_clause 
                  ORDER BY popt_id ASC";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $populations = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get summary statistics
        $stats_query = "SELECT 
                      COUNT(*) as total_locations,
                      COUNT(DISTINCT popt_ip_group) as total_ip_groups,
                      SUM(popt_population) as total_population
                   FROM population_table $where_clause";
        $stats_stmt = $pdo->prepare($stats_query);
        $stats_stmt->execute($params);
        $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get IP group distribution
        $distribution_query = "SELECT 
                            popt_ip_group, 
                            COUNT(*) as location_count, 
                            SUM(popt_population) as group_population 
                          FROM population_table 
                          " . ($where_clause ? $where_clause . " AND " : "WHERE ") . "
                          popt_population > 0
                          GROUP BY popt_ip_group 
                          ORDER BY popt_ip_group ASC";
        $distribution_stmt = $pdo->prepare($distribution_query);
        $distribution_stmt->execute($params);
        $ip_distribution = $distribution_stmt->fetchAll(PDO::FETCH_ASSOC);

        // Create Spreadsheet for all data
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Population Data');
        
        // Styles
        $titleStyle = [
            'font' => ['bold' => true, 'size' => 16, 'color' => ['rgb' => 'FFFFFF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4472C4']],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
        ];
        
        $subtitleStyle = [
            'font' => ['bold' => true, 'size' => 12],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'DCE6F1']],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
        ];
        
        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4472C4']],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
        ];
        
        $dataStyle = [
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER]
        ];
        
        $numberStyle = [
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT, 'vertical' => Alignment::VERTICAL_CENTER],
            'numberFormat' => ['formatCode' => '#,##0']
        ];
        
        $coordStyle = [
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT, 'vertical' => Alignment::VERTICAL_CENTER],
            'numberFormat' => ['formatCode' => '0.000000']
        ];
        
        $percentStyle = [
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT, 'vertical' => Alignment::VERTICAL_CENTER],
            'numberFormat' => ['formatCode' => '0.00%']
        ];
        
        $sectionHeaderStyle = [
            'font' => ['bold' => true, 'size' => 14],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4472C4']],
            'font' => ['color' => ['rgb' => 'FFFFFF']],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
        ];
        
        // Set column widths
        $sheet->getColumnDimension('A')->setWidth(12);      // ID
        $sheet->getColumnDimension('B')->setWidth(40);     // Location Name
        $sheet->getColumnDimension('C')->setWidth(15);     // IP Group
        $sheet->getColumnDimension('D')->setWidth(12);     // Population
        $sheet->getColumnDimension('E')->setWidth(15);     // Latitude
        $sheet->getColumnDimension('F')->setWidth(15);     // Longitude
        
        // Row 1: Title
        $sheet->mergeCells('A1:F1');
        $sheet->setCellValue('A1', 'NCIP INDIGENOUS POPULATION DATA');
        $sheet->getStyle('A1:F1')->applyFromArray($titleStyle);
        $sheet->getRowDimension(1)->setRowHeight(30);
        
        // Row 2: Summary
        $sheet->mergeCells('A2:F2');
        $sheet->setCellValue('A2', 'Total: ' . number_format($stats['total_locations']) . ' locations, ' . 
            number_format($stats['total_ip_groups']) . ' IP groups, ' . 
            number_format($stats['total_population']) . ' people');
        $sheet->getStyle('A2:F2')->applyFromArray($subtitleStyle);
        $sheet->getRowDimension(2)->setRowHeight(25);
        
        // Add filter info if present
        $row = 3;
        if (!empty($search) || !empty($ip_group_filter)) {
            $sheet->mergeCells("A{$row}:F{$row}");
            $filter_text = "Filtered data";
            if (!empty($ip_group_filter)) {
                $filter_text .= " - IP Group: " . $ip_group_filter;
            }
            if (!empty($search)) {
                $filter_text .= " - Search: " . $search;
            }
            $sheet->setCellValue("A{$row}", $filter_text);
            $sheet->getStyle("A{$row}:F{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("A{$row}:F{$row}")->applyFromArray($dataStyle);
            $row++;
        }
        
        // Empty row for spacing
        $row++;
        
        // Row 5: Headers for population data
        $sheet->setCellValue("A{$row}", 'ID');
        $sheet->setCellValue("B{$row}", 'Location Name');
        $sheet->setCellValue("C{$row}", 'IP Group');
        $sheet->setCellValue("D{$row}", 'Population');
        $sheet->setCellValue("E{$row}", 'Latitude');
        $sheet->setCellValue("F{$row}", 'Longitude');
        $sheet->getStyle("A{$row}:F{$row}")->applyFromArray($headerStyle);
        $sheet->getRowDimension($row)->setRowHeight(25);
        $row++;
        
        // Data rows
        foreach ($populations as $pop) {
            $sheet->setCellValue("A{$row}", $pop['popt_id']);
            $sheet->setCellValue("B{$row}", $pop['popt_name']);
            $sheet->setCellValue("C{$row}", $pop['popt_ip_group']);
            $sheet->setCellValue("D{$row}", $pop['popt_population']);
            $sheet->setCellValue("E{$row}", $pop['popt_latitude']);
            $sheet->setCellValue("F{$row}", $pop['popt_longitude']);
            
            $sheet->getStyle("A{$row}")->applyFromArray($dataStyle);
            $sheet->getStyle("B{$row}")->applyFromArray($dataStyle);
            $sheet->getStyle("C{$row}")->applyFromArray($dataStyle);
            $sheet->getStyle("D{$row}")->applyFromArray($numberStyle);
            $sheet->getStyle("E{$row}")->applyFromArray($coordStyle);
            $sheet->getStyle("F{$row}")->applyFromArray($coordStyle);
            $row++;
        }
        
        // Empty row
        $row += 2;
        
        // IP Group Distribution Section
        $sheet->mergeCells("A{$row}:F{$row}");
        $sheet->setCellValue("A{$row}", 'IP Group Distribution');

       
        $ipGroupHeaderStyle = [
            'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => 'FFFFFF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4472C4']],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
        ];
        $sheet->getStyle("A{$row}:F{$row}")->applyFromArray($ipGroupHeaderStyle);
        $sheet->getRowDimension($row)->setRowHeight(25);
        $row++;
        
        // IP Group table headers
        $sheet->setCellValue("A{$row}", 'IP Group');
        $sheet->setCellValue("B{$row}", 'Total Population');
        $sheet->setCellValue("C{$row}", '');
        $sheet->setCellValue("D{$row}", '');
        $sheet->setCellValue("E{$row}", 'Locations');
        $sheet->setCellValue("F{$row}", '% of Total');

        $sheet->getStyle("A{$row}:F{$row}")->applyFromArray($headerStyle);

        $sheet->getRowDimension($row)->setRowHeight(25);
        $row++;
        
        // IP Group data rows
        foreach ($ip_distribution as $group) {
            $percentage = ($stats['total_population'] > 0) ? 
                ($group['group_population'] / $stats['total_population']) : 0;
            
            $sheet->setCellValue("A{$row}", $group['popt_ip_group']);
            $sheet->setCellValue("B{$row}", $group['group_population']);
            $sheet->setCellValue("C{$row}", '');  // Add empty value but explicitly set the cell
            $sheet->setCellValue("D{$row}", '');  // Add empty value but explicitly set the cell
            $sheet->setCellValue("E{$row}", $group['location_count']);
            $sheet->setCellValue("F{$row}", $percentage);
            
            // Apply base style to ENTIRE row first
            $sheet->getStyle("A{$row}:F{$row}")->applyFromArray($dataStyle);
            $sheet->getStyle("B{$row}")->applyFromArray($numberStyle);
            $sheet->getStyle("F{$row}")->applyFromArray($percentStyle);
            $row++;
        }
        
        // Empty row
        $row += 2;
        
        // Export info
        $sheet->mergeCells("A{$row}:B{$row}");  
        $sheet->setCellValue("A{$row}", 'Export Date: ' . date('d/m/Y h:i A'));

        $sheet->mergeCells("C{$row}:F{$row}");  
        $sheet->setCellValue("C{$row}", 'Generated By: ' . htmlspecialchars($_SESSION["username"] ?? "Lawrence Ramirez"));

        $sheet->getStyle("A{$row}:F{$row}")->applyFromArray($dataStyle);
        
        // Set filename for export with all records
        $filename = "population_data_export_" . date('d_m_Y') . ".xlsx";
        
        // If filtering by IP group, add that to the filename
        if (!empty($ip_group_filter)) {
            $safe_group = preg_replace('/[^a-z0-9]+/', '_', strtolower($ip_group_filter));
            $filename = "population_data_{$safe_group}_" . date('d_m_Y') . ".xlsx";
        }
    }
    
    // Text wrapping for all cells
    foreach ($sheet->getRowIterator() as $rowIter) {
        foreach ($rowIter->getCellIterator() as $cell) {
            $sheet->getStyle($cell->getCoordinate())
                ->getAlignment()
                ->setWrapText(true);
        }
    }

    // Freeze panes for better navigation
    $sheet->freezePane('A6');

    // Set headers for download
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');

    // Save to output
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