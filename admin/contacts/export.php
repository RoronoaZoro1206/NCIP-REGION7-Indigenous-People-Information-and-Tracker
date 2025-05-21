<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include "../config/database.php";

// Check if user is logged in
if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.php");
    exit();
}

// Get filter parameters from URL if present
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Set headers for Excel download
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="contact_messages_' . date('Y-m-d') . '.xls"');
header('Cache-Control: max-age=0');

// Build query conditions
$status_condition = '';
if (!empty($status_filter)) {
    $status_condition = "AND c.contm_status = :status_filter";
}

$search_condition = '';
if (!empty($search)) {
    $search_condition = "AND (c.contm_name LIKE :search_term OR c.contm_email LIKE :search_term OR 
                        c.contm_message LIKE :search_term)";
}

try {
    // First check if reply columns exist
    $check_columns = $pdo->prepare("SELECT column_name FROM information_schema.columns 
                                   WHERE table_name = 'contact_message' 
                                   AND column_name IN ('contm_reply', 'contm_reply_date')");
    $check_columns->execute();
    $existing_columns = $check_columns->fetchAll(PDO::FETCH_COLUMN, 0);
    
    $has_reply_column = in_array('contm_reply', $existing_columns);
    $has_reply_date_column = in_array('contm_reply_date', $existing_columns);
    
    // Query to get all contact messages
    $query = "SELECT 
                c.contm_id,
                c.contm_name,
                c.contm_email,
                c.contm_phone,
                c.contm_message,
                c.contm_submission_date,
                c.contm_status";
    
    // Add reply columns only if they exist
    if ($has_reply_column) {
        $query .= ", c.contm_reply";
    }
    if ($has_reply_date_column) {
        $query .= ", c.contm_reply_date";
    }
    
    $query .= " FROM contact_message c
              WHERE 1=1 " . $status_condition . " " . $search_condition . "
              ORDER BY c.contm_submission_date DESC";
    
    $stmt = $pdo->prepare($query);
    
    if (!empty($status_filter)) {
        $stmt->bindValue(':status_filter', $status_filter);
    }
    
    if (!empty($search)) {
        $search_param = '%' . $search . '%';
        $stmt->bindValue(':search_term', $search_param);
    }
    
    $stmt->execute();
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Start building Excel output with formatting
    echo '<?xml version="1.0"?>' . "\n";
    echo '<?mso-application progid="Excel.Sheet"?>' . "\n";
    echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"
      xmlns:o="urn:schemas-microsoft-com:office:office"
      xmlns:x="urn:schemas-microsoft-com:office:excel"
      xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"
      xmlns:html="http://www.w3.org/TR/REC-html40">' . "\n";

    // Document properties for better Excel compatibility
    echo '<DocumentProperties xmlns="urn:schemas-microsoft-com:office:office">
        <Author>NCIP Admin</Author>
        <Created>' . date('Y-m-d\TH:i:s') . '</Created>
        <LastSaved>' . date('Y-m-d\TH:i:s') . '</LastSaved>
        <Company>National Commission on Indigenous Peoples</Company>
        <Version>16.00</Version>
    </DocumentProperties>' . "\n";
    
    // Add Excel specific properties
    echo '<ExcelWorkbook xmlns="urn:schemas-microsoft-com:office:excel">
        <WindowHeight>7920</WindowHeight>
        <WindowWidth>21600</WindowWidth>
        <WindowTopX>32767</WindowTopX>
        <WindowTopY>32767</WindowTopY>
        <ProtectStructure>False</ProtectStructure>
        <ProtectWindows>False</ProtectWindows>
    </ExcelWorkbook>' . "\n";
    
    // Add styles for better formatting
    echo '<Styles>
        <Style ss:ID="Header">
            <Alignment ss:Horizontal="Center" ss:Vertical="Center" ss:WrapText="1"/>
            <Font ss:Bold="1" ss:Size="12"/>
            <Interior ss:Color="#D9E1F2" ss:Pattern="Solid"/>
            <Borders>
                <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/>
                <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/>
                <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/>
                <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/>
            </Borders>
        </Style>
        <Style ss:ID="SectionHeader">
            <Alignment ss:Horizontal="Center" ss:Vertical="Center" ss:WrapText="1"/>
            <Font ss:Bold="1" ss:Size="12"/>
            <Interior ss:Color="#E6F2FF" ss:Pattern="Solid"/>
            <Borders>
                <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/>
                <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/>
                <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/>
                <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/>
            </Borders>
        </Style>
        <Style ss:ID="Default">
            <Alignment ss:Vertical="Top" ss:WrapText="1"/>
            <Borders>
                <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/>
                <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/>
                <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/>
                <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/>
            </Borders>
        </Style>
        <Style ss:ID="DateFormat">
            <Alignment ss:Vertical="Top" ss:WrapText="1"/>
            <NumberFormat ss:Format="yyyy-mm-dd hh:mm:ss"/>
            <Borders>
                <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/>
                <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/>
                <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/>
                <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/>
            </Borders>
        </Style>
    </Styles>' . "\n";
    
    echo '<Worksheet ss:Name="Contact Messages">' . "\n";
    
    // Calculate column count
    $column_count = 7; // Base columns
    if ($has_reply_column) $column_count++;
    if ($has_reply_date_column) $column_count++;
    
    // Add column widths for better spacing
    echo '<Table ss:ExpandedColumnCount="' . $column_count . '" x:FullColumns="1" x:FullRows="1">' . "\n";
    
    // Set column widths
    echo '<Column ss:Width="60" />';  // ID
    echo '<Column ss:Width="120" />'; // Name
    echo '<Column ss:Width="150" />'; // Email
    echo '<Column ss:Width="100" />'; // Phone
    echo '<Column ss:Width="300" />'; // Message
    echo '<Column ss:Width="120" />'; // Submission Date
    echo '<Column ss:Width="80" />';  // Status
    
    // Add reply columns if they exist
    if ($has_reply_column) {
        echo '<Column ss:Width="300" />'; // Reply Content
    }
    if ($has_reply_date_column) {
        echo '<Column ss:Width="120" />'; // Reply Date
    }
    
    // Title Row
    echo '<Row ss:Height="30">';
    echo '<Cell ss:StyleID="SectionHeader" ss:MergeAcross="' . ($column_count - 1) . '"><Data ss:Type="String">NCIP CONTACT MESSAGES</Data></Cell>';
    echo '</Row>';
    
    // Export Date Row
    echo '<Row>';
    echo '<Cell ss:StyleID="Default" ss:MergeAcross="3"><Data ss:Type="String">Export Date: ' . date('F d, Y h:i:s A') . '</Data></Cell>';
    
    $merge_across = $column_count - 5;
    echo '<Cell ss:StyleID="Default" ss:MergeAcross="' . $merge_across . '">';
    if (!empty($status_filter)) {
        echo '<Data ss:Type="String">Filter: Status = ' . htmlspecialchars($status_filter) . '</Data>';
    } elseif (!empty($search)) {
        echo '<Data ss:Type="String">Search: ' . htmlspecialchars($search) . '</Data>';
    } else {
        echo '<Data ss:Type="String">All Messages</Data>';
    }
    echo '</Cell>';
    echo '</Row>';
    
    // Empty Row
    echo '<Row></Row>';
    
    // Section Headers Row
    echo '<Row>';
    echo '<Cell ss:StyleID="SectionHeader" ss:MergeAcross="3"><Data ss:Type="String">Contact Information</Data></Cell>';
    echo '<Cell ss:StyleID="SectionHeader" ss:MergeAcross="1"><Data ss:Type="String">Content</Data></Cell>';
    echo '<Cell ss:StyleID="SectionHeader" ss:MergeAcross="0"><Data ss:Type="String">Status</Data></Cell>';
    
    if ($has_reply_column || $has_reply_date_column) {
        $reply_merge = ($has_reply_column && $has_reply_date_column) ? 1 : 0;
        echo '<Cell ss:StyleID="SectionHeader" ss:MergeAcross="' . $reply_merge . '"><Data ss:Type="String">Reply</Data></Cell>';
    }
    
    echo '</Row>';
    
    // Column Headers Row
    echo '<Row>';
    echo '<Cell ss:StyleID="Header"><Data ss:Type="String">ID</Data></Cell>';
    echo '<Cell ss:StyleID="Header"><Data ss:Type="String">Name</Data></Cell>';
    echo '<Cell ss:StyleID="Header"><Data ss:Type="String">Email</Data></Cell>';
    echo '<Cell ss:StyleID="Header"><Data ss:Type="String">Phone</Data></Cell>';
    echo '<Cell ss:StyleID="Header"><Data ss:Type="String">Message</Data></Cell>';
    echo '<Cell ss:StyleID="Header"><Data ss:Type="String">Submission Date</Data></Cell>';
    echo '<Cell ss:StyleID="Header"><Data ss:Type="String">Status</Data></Cell>';
    
    // Add reply column headers if needed
    if ($has_reply_column) {
        echo '<Cell ss:StyleID="Header"><Data ss:Type="String">Reply Content</Data></Cell>';
    }
    if ($has_reply_date_column) {
        echo '<Cell ss:StyleID="Header"><Data ss:Type="String">Reply Date</Data></Cell>';
    }
    
    echo '</Row>';
    
    // Data Rows
    if (count($messages) > 0) {
        foreach ($messages as $msg) {
            echo '<Row>';
            
            // Contact Information
            echo '<Cell ss:StyleID="Default"><Data ss:Type="String">' . htmlspecialchars($msg['contm_id']) . '</Data></Cell>';
            echo '<Cell ss:StyleID="Default"><Data ss:Type="String">' . htmlspecialchars($msg['contm_name']) . '</Data></Cell>';
            echo '<Cell ss:StyleID="Default"><Data ss:Type="String">' . htmlspecialchars($msg['contm_email']) . '</Data></Cell>';
            echo '<Cell ss:StyleID="Default"><Data ss:Type="String">' . htmlspecialchars($msg['contm_phone'] ?? 'N/A') . '</Data></Cell>';
            
            // Content
            echo '<Cell ss:StyleID="Default"><Data ss:Type="String">' . htmlspecialchars($msg['contm_message']) . '</Data></Cell>';
            echo '<Cell ss:StyleID="DateFormat"><Data ss:Type="DateTime">' . gmdate('Y-m-d\TH:i:s.000', strtotime($msg['contm_submission_date'])) . '</Data></Cell>';
            
            // Status
            echo '<Cell ss:StyleID="Default"><Data ss:Type="String">' . htmlspecialchars($msg['contm_status']) . '</Data></Cell>';
            
            // Reply (only if columns exist)
            if ($has_reply_column) {
                echo '<Cell ss:StyleID="Default"><Data ss:Type="String">' . htmlspecialchars($msg['contm_reply'] ?? 'No reply yet') . '</Data></Cell>';
            }
            
            if ($has_reply_date_column) {
                if (!empty($msg['contm_reply_date'])) {
                    echo '<Cell ss:StyleID="DateFormat"><Data ss:Type="DateTime">' . gmdate('Y-m-d\TH:i:s.000', strtotime($msg['contm_reply_date'])) . '</Data></Cell>';
                } else {
                    echo '<Cell ss:StyleID="Default"><Data ss:Type="String">N/A</Data></Cell>';
                }
            }
            
            echo '</Row>';
        }
    } else {
        echo '<Row>';
        echo '<Cell ss:StyleID="Default" ss:MergeAcross="' . ($column_count - 1) . '"><Data ss:Type="String">No messages found matching the criteria</Data></Cell>';
        echo '</Row>';
    }
    
    // Summary Row
    echo '<Row></Row>';
    echo '<Row>';
    echo '<Cell ss:StyleID="SectionHeader" ss:MergeAcross="1"><Data ss:Type="String">Total Messages:</Data></Cell>';
    echo '<Cell ss:StyleID="Default"><Data ss:Type="Number">' . count($messages) . '</Data></Cell>';
    echo '</Row>';
    
    echo '</Table>';
    echo '</Worksheet>';
    echo '</Workbook>';
    
} catch (PDOException $e) {
    // Log error and create simple error Excel file
    error_log("Excel export error: " . $e->getMessage());
    
    echo '<?xml version="1.0"?>' . "\n";
    echo '<?mso-application progid="Excel.Sheet"?>' . "\n";
    echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"
      xmlns:o="urn:schemas-microsoft-com:office:office"
      xmlns:x="urn:schemas-microsoft-com:office:excel"
      xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"
      xmlns:html="http://www.w3.org/TR/REC-html40">' . "\n";
    
    // Add styles
    echo '<Styles>
        <Style ss:ID="Error">
            <Font ss:Bold="1" ss:Color="#FF0000" ss:Size="12"/>
        </Style>
    </Styles>' . "\n";
    
    echo '<Worksheet ss:Name="Error">' . "\n";
    echo '<Table>' . "\n";
    echo '<Row><Cell ss:StyleID="Error"><Data ss:Type="String">An error occurred while generating the contact messages export.</Data></Cell></Row>' . "\n";
    echo '<Row><Cell><Data ss:Type="String">Error message: ' . htmlspecialchars($e->getMessage()) . '</Data></Cell></Row>' . "\n";
    echo '<Row><Cell><Data ss:Type="String">Please check the server logs for more information.</Data></Cell></Row>' . "\n";
    echo '</Table>' . "\n";
    echo '</Worksheet>' . "\n";
    echo '</Workbook>';
}
?>