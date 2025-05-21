<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include "../config/database.php";

// Check if user is logged in and has admin role
if (!isset($_SESSION["user_id"]) || !isset($_SESSION["role"]) || strtoupper($_SESSION["role"]) !== 'ADMIN') {
    header("Location: ../login.php");
    exit();
}

// Get application ID and validate
$app_code = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($app_code <= 0) {
    $_SESSION['error_message'] = "Invalid application ID.";
    header("Location: index.php");
    exit();
}

// Check if application exists
$stmt = $pdo->prepare("SELECT a.APP_CODE, a.APP_LAST_NAME, a.APP_FIRST_NAME, a.ADMIN_CODE, a.USER_ID
                       FROM APPLICANT a 
                       WHERE a.APP_CODE = :app_code");
$stmt->bindParam(':app_code', $app_code, PDO::PARAM_INT);
$stmt->execute();
$applicant = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$applicant) {
    $_SESSION['error_message'] = "Application not found.";
    header("Location: index.php");
    exit();
}

try {
    // Begin transaction
    $pdo->beginTransaction();
    
    // Log the deletion activity
    $log_query = "INSERT INTO USER_LOGS (USER_ID, LOG_TYPE, LOG_DETAILS, LOG_TIMESTAMP) 
                 VALUES (:user_id, 'DELETE_APPLICATION', :details, CURRENT_TIMESTAMP)";
    $log_stmt = $pdo->prepare($log_query);
    $details = "Admin deleted scholarship application #" . $app_code . " for " . 
               $applicant['APP_LAST_NAME'] . ", " . $applicant['APP_FIRST_NAME'];
    $log_stmt->bindParam(":user_id", $_SESSION["user_id"]);
    $log_stmt->bindParam(":details", $details);
    $log_stmt->execute();
    
    // Delete from DOCUMENT_UPLOADS if this table exists (for uploaded files)
    $stmt = $pdo->prepare("DELETE FROM DOCUMENT_UPLOADS WHERE APP_CODE = :app_code");
    $stmt->execute([':app_code' => $app_code]);
    
    // Delete from SCHOLARSHIP_STATUS
    $stmt = $pdo->prepare("DELETE FROM SCHOLARSHIP_STATUS WHERE APP_CODE = :app_code");
    $stmt->execute([':app_code' => $app_code]);
    
    // Delete from SIGNATURE_SCAN
    $stmt = $pdo->prepare("DELETE FROM SIGNATURE_SCAN WHERE APP_CODE = :app_code");
    $stmt->execute([':app_code' => $app_code]);
    
    // Delete from SCHOOL_PREFERENCE
    $stmt = $pdo->prepare("DELETE FROM SCHOOL_PREFERENCE WHERE APP_CODE = :app_code");
    $stmt->execute([':app_code' => $app_code]);
    
    // Delete from SCHOLARSHIP (via SIBLING)
    $stmt = $pdo->prepare("DELETE FROM SCHOLARSHIP WHERE SIB_CODE IN (SELECT SIB_CODE FROM SIBLING WHERE APP_CODE = :app_code)");
    $stmt->execute([':app_code' => $app_code]);
    
    // Delete from SIBLING
    $stmt = $pdo->prepare("DELETE FROM SIBLING WHERE APP_CODE = :app_code");
    $stmt->execute([':app_code' => $app_code]);
    
    // Delete from FAMILY_BACKGROUND
    $stmt = $pdo->prepare("DELETE FROM FAMILY_BACKGROUND WHERE APP_CODE = :app_code");
    $stmt->execute([':app_code' => $app_code]);
    
    // Delete from SCHOOL (via EDUCATION_ATTAINMENT)
    $stmt = $pdo->prepare("DELETE FROM SCHOOL WHERE EDUC_CODE IN (SELECT EDUA_CODE FROM EDUCATION_ATTAINMENT WHERE APP_CODE = :app_code)");
    $stmt->execute([':app_code' => $app_code]);
    
    // Delete from EDUCATION_ATTAINMENT
    $stmt = $pdo->prepare("DELETE FROM EDUCATION_ATTAINMENT WHERE APP_CODE = :app_code");
    $stmt->execute([':app_code' => $app_code]);
    
    // Delete from ADDRESS
    $stmt = $pdo->prepare("DELETE FROM ADDRESS WHERE APP_CODE = :app_code");
    $stmt->execute([':app_code' => $app_code]);
    
    // Delete from APPLICANT
    $stmt = $pdo->prepare("DELETE FROM APPLICANT WHERE APP_CODE = :app_code");
    $stmt->execute([':app_code' => $app_code]);
    
    // Delete from ADMINISTRATOR if no more references
    if (isset($applicant['ADMIN_CODE']) && $applicant['ADMIN_CODE']) {
        // Check if this ADMIN_CODE is used by other applications
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM APPLICANT WHERE ADMIN_CODE = :admin_code");
        $stmt->execute([':admin_code' => $applicant['ADMIN_CODE']]);
        $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        // If this was the only application using this ADMIN_CODE, delete the ADMINISTRATOR record
        if ($count <= 1) {
            $stmt = $pdo->prepare("DELETE FROM ADMINISTRATOR WHERE ADMIN_CODE = :admin_code");
            $stmt->execute([':admin_code' => $applicant['ADMIN_CODE']]);
        }
    }
    
    // Clean up any user application references in e-service tables
    if (isset($applicant['USER_ID']) && $applicant['USER_ID']) {
        // Reset the application status flags for the user
        $stmt = $pdo->prepare("UPDATE USERS SET HAS_ACTIVE_APPLICATION = FALSE WHERE USER_ID = :user_id");
        $stmt->execute([':user_id' => $applicant['USER_ID']]);
    }
    
    // Commit the transaction
    $pdo->commit();
    
    // Reset PostgreSQL sequences to reuse deleted IDs
    try {
        // Get the next value that should be used for each table's sequence
        $tables_sequences = [
            // Table name => [column name, sequence name]
            'APPLICANT' => ['APP_CODE', 'applicant_app_code_seq'],
            'FAMILY_BACKGROUND' => ['FAM_CODE', 'family_background_fam_code_seq'],
            'SIBLING' => ['SIB_CODE', 'sibling_sib_code_seq'],
            'EDUCATION_ATTAINMENT' => ['EDUA_CODE', 'education_attainment_edua_code_seq'],
            'SCHOOL' => ['SCHOOL_CODE', 'school_school_code_seq'],
            'ADDRESS' => ['ADDR_CODE', 'address_addr_code_seq'],
            'DOCUMENT_UPLOADS' => ['DOC_CODE', 'document_uploads_doc_code_seq'],
            'SCHOLARSHIP_STATUS' => ['STATUS_ID', 'scholarship_status_status_id_seq'],
            'SCHOOL_PREFERENCE' => ['PREF_CODE', 'school_preference_pref_code_seq'],
            'SIGNATURE_SCAN' => ['SIG_CODE', 'signature_scan_sig_code_seq']
        ];

        foreach ($tables_sequences as $table => $info) {
            $column = $info[0];
            $sequence = $info[1];
            
            // Get the maximum ID currently in the table, or 0 if empty
            $check_stmt = $pdo->query("SELECT MAX($column) as max_id FROM $table");
            $max_result = $check_stmt->fetch(PDO::FETCH_ASSOC);
            $next_value = ($max_result['max_id'] !== null) ? intval($max_result['max_id']) + 1 : 1;
            
            $pdo->query("SELECT setval('$sequence', $next_value, false)");
        }
    } catch (Exception $seq_error) {
        // Log sequence reset error but don't fail the whole operation
        error_log('Error resetting sequences: ' . $seq_error->getMessage());
    }
    
    // Set success message and redirect
    $_SESSION['success_message'] = "Application deleted successfully.";
    
    // If AJAX request, return JSON response instead of redirecting
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'Application deleted successfully']);
        exit();
    }
    
    header("Location: index.php");
    exit();

} catch (Exception $e) {
    // Rollback the transaction
    $pdo->rollBack();
    
    // Set error message and redirect
    $_SESSION['error_message'] = "Error deleting application: " . $e->getMessage();
    header("Location: index.php");
    exit();
}