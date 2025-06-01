<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include necessary files
require_once "../config/database.php";
require_once "../includes/RecycleBinManager.php";

// Check if user is logged in and has admin role
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

// Get application ID and validate
$app_code = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($app_code <= 0) {
    $_SESSION['error_message'] = "Invalid application ID.";
    header("Location: index.php");
    exit();
}

// Get action type (soft delete by default, permanent delete if specified)
$action = isset($_GET['action']) ? $_GET['action'] : 'soft_delete';
$force_permanent = isset($_GET['permanent']) && $_GET['permanent'] === 'true';

try {
    // Initialize RecycleBinManager
    $recycleBin = new RecycleBinManager($pdo);
    
    // Check if application exists and get details
    $stmt = $pdo->prepare("SELECT a.APP_CODE, a.APP_LAST_NAME, a.APP_FIRST_NAME, a.ADMIN_CODE, a.USER_ID,
                                  a.APP_DELETED_AT, a.APP_DELETED_BY
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

    // Determine what action to perform
    if ($action === 'restore') {
        // Restore from recycle bin
        if (empty($applicant['APP_DELETED_AT'])) {
            $_SESSION['error_message'] = "Application is not in recycle bin.";
            header("Location: index.php");
            exit();
        }
        
        $result = $recycleBin->restoreItem('applicant', $app_code);
        
        if ($result) {
            // Log the restoration activity
            $log_query = "INSERT INTO USER_LOGS (USER_ID, LOG_TYPE, LOG_DETAILS, LOG_TIMESTAMP) 
                         VALUES (:user_id, 'RESTORE_APPLICATION', :details, CURRENT_TIMESTAMP)";
            $log_stmt = $pdo->prepare($log_query);
            $details = "Admin restored scholarship application #" . $app_code . " for " . 
                       $applicant['APP_LAST_NAME'] . ", " . $applicant['APP_FIRST_NAME'];
            $log_stmt->bindParam(":user_id", $_SESSION["user_id"]);
            $log_stmt->bindParam(":details", $details);
            $log_stmt->execute();
            
            $_SESSION['success_message'] = "Application restored successfully.";
        } else {
            $_SESSION['error_message'] = "Failed to restore application.";
        }
        
    } elseif ($action === 'permanent_delete' || $force_permanent) {
        // Permanent deletion
        if (empty($applicant['APP_DELETED_AT']) && !$force_permanent) {
            $_SESSION['error_message'] = "Application must be in recycle bin before permanent deletion.";
            header("Location: index.php");
            exit();
        }
        
        // Begin transaction for permanent deletion
        $pdo->beginTransaction();
        
        try {
            // Log the permanent deletion activity
            $log_query = "INSERT INTO USER_LOGS (USER_ID, LOG_TYPE, LOG_DETAILS, LOG_TIMESTAMP) 
                         VALUES (:user_id, 'PERMANENT_DELETE_APPLICATION', :details, CURRENT_TIMESTAMP)";
            $log_stmt = $pdo->prepare($log_query);
            $details = "Admin permanently deleted scholarship application #" . $app_code . " for " . 
                       $applicant['APP_LAST_NAME'] . ", " . $applicant['APP_FIRST_NAME'];
            $log_stmt->bindParam(":user_id", $_SESSION["user_id"]);
            $log_stmt->bindParam(":details", $details);
            $log_stmt->execute();
            
            // Delete related records in correct order (respect foreign key constraints)
            
            // 1. Delete from DOCUMENT_UPLOADS if this table exists
            $stmt = $pdo->prepare("DELETE FROM DOCUMENT_UPLOADS WHERE APP_CODE = :app_code");
            $stmt->execute([':app_code' => $app_code]);
            
            // 2. Delete from SCHOLARSHIP_STATUS
            $stmt = $pdo->prepare("DELETE FROM SCHOLARSHIP_STATUS WHERE APP_CODE = :app_code");
            $stmt->execute([':app_code' => $app_code]);
            
            // 3. Delete from SIGNATURE_SCAN
            $stmt = $pdo->prepare("DELETE FROM SIGNATURE_SCAN WHERE APP_CODE = :app_code");
            $stmt->execute([':app_code' => $app_code]);
            
            // 4. Delete from SCHOOL_PREFERENCE
            $stmt = $pdo->prepare("DELETE FROM SCHOOL_PREFERENCE WHERE APP_CODE = :app_code");
            $stmt->execute([':app_code' => $app_code]);
            
            // 5. Delete from SCHOLARSHIP (via SIBLING)
            $stmt = $pdo->prepare("DELETE FROM SCHOLARSHIP WHERE SIB_CODE IN (SELECT SIB_CODE FROM SIBLING WHERE APP_CODE = :app_code)");
            $stmt->execute([':app_code' => $app_code]);
            
            // 6. Delete from SIBLING
            $stmt = $pdo->prepare("DELETE FROM SIBLING WHERE APP_CODE = :app_code");
            $stmt->execute([':app_code' => $app_code]);
            
            // 7. Delete from FAMILY_BACKGROUND
            $stmt = $pdo->prepare("DELETE FROM FAMILY_BACKGROUND WHERE APP_CODE = :app_code");
            $stmt->execute([':app_code' => $app_code]);
            
            // 8. Delete from SCHOOL (via EDUCATION_ATTAINMENT)
            $stmt = $pdo->prepare("DELETE FROM SCHOOL WHERE EDUC_CODE IN (SELECT EDUA_CODE FROM EDUCATION_ATTAINMENT WHERE APP_CODE = :app_code)");
            $stmt->execute([':app_code' => $app_code]);
            
            // 9. Delete from EDUCATION_ATTAINMENT
            $stmt = $pdo->prepare("DELETE FROM EDUCATION_ATTAINMENT WHERE APP_CODE = :app_code");
            $stmt->execute([':app_code' => $app_code]);
            
            // 10. Delete from ADDRESS
            $stmt = $pdo->prepare("DELETE FROM ADDRESS WHERE APP_CODE = :app_code");
            $stmt->execute([':app_code' => $app_code]);
            
            // 11. Delete from APPLICANT (main record)
            $stmt = $pdo->prepare("DELETE FROM APPLICANT WHERE APP_CODE = :app_code");
            $stmt->execute([':app_code' => $app_code]);
            
            // 12. Clean up ADMINISTRATOR if no more references
            if (isset($applicant['ADMIN_CODE']) && $applicant['ADMIN_CODE']) {
                $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM APPLICANT WHERE ADMIN_CODE = :admin_code");
                $stmt->execute([':admin_code' => $applicant['ADMIN_CODE']]);
                $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
                
                if ($count == 0) {
                    $stmt = $pdo->prepare("DELETE FROM ADMINISTRATOR WHERE ADMIN_CODE = :admin_code");
                    $stmt->execute([':admin_code' => $applicant['ADMIN_CODE']]);
                }
            }
            
            // 13. Clean up user application references
            if (isset($applicant['USER_ID']) && $applicant['USER_ID']) {
                $stmt = $pdo->prepare("UPDATE USERS SET HAS_ACTIVE_APPLICATION = FALSE WHERE USER_ID = :user_id");
                $stmt->execute([':user_id' => $applicant['USER_ID']]);
            }
            
            // Reset PostgreSQL sequences to reuse deleted IDs
            $this->resetSequences($pdo);
            
            $pdo->commit();
            $_SESSION['success_message'] = "Application permanently deleted successfully.";
            
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
        
    } else {
        // Soft delete (move to recycle bin) - DEFAULT ACTION
        if (!empty($applicant['APP_DELETED_AT'])) {
            $_SESSION['error_message'] = "Application is already in recycle bin.";
            header("Location: index.php");
            exit();
        }
        
        // First, ensure the APPLICANT table has the necessary columns for soft delete
        $this->ensureSoftDeleteColumns($pdo);
        
        // Perform soft delete using RecycleBinManager
        $result = $recycleBin->softDeleteItem('applicant', $app_code, $_SESSION["user_id"]);
        
        if ($result) {
            // Log the soft deletion activity
            $log_query = "INSERT INTO USER_LOGS (USER_ID, LOG_TYPE, LOG_DETAILS, LOG_TIMESTAMP) 
                         VALUES (:user_id, 'SOFT_DELETE_APPLICATION', :details, CURRENT_TIMESTAMP)";
            $log_stmt = $pdo->prepare($log_query);
            $details = "Admin moved scholarship application #" . $app_code . " to recycle bin for " . 
                       $applicant['APP_LAST_NAME'] . ", " . $applicant['APP_FIRST_NAME'];
            $log_stmt->bindParam(":user_id", $_SESSION["user_id"]);
            $log_stmt->bindParam(":details", $details);
            $log_stmt->execute();
            
            $_SESSION['success_message'] = "Application moved to recycle bin successfully.";
        } else {
            $_SESSION['error_message'] = "Failed to move application to recycle bin.";
        }
    }
    
    // Handle AJAX requests
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json');
        if (isset($_SESSION['success_message'])) {
            echo json_encode([
                'success' => true, 
                'message' => $_SESSION['success_message'],
                'action' => $action
            ]);
            unset($_SESSION['success_message']);
        } else {
            echo json_encode([
                'success' => false, 
                'message' => $_SESSION['error_message'] ?? 'Unknown error occurred',
                'action' => $action
            ]);
            unset($_SESSION['error_message']);
        }
        exit();
    }
    
    // Determine redirect location based on action
    if ($action === 'restore' || $action === 'permanent_delete') {
        header("Location: ../recycle-bin/index.php");
    } else {
        header("Location: index.php");
    }
    exit();

} catch (Exception $e) {
    // Log the error
    error_log("Error in delete.php: " . $e->getMessage());
    
    $_SESSION['error_message'] = "Error processing request: " . $e->getMessage();
    
    // Handle AJAX requests
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false, 
            'message' => $_SESSION['error_message'],
            'action' => $action ?? 'unknown'
        ]);
        unset($_SESSION['error_message']);
        exit();
    }
    
    // Determine redirect location
    if (isset($action) && ($action === 'restore' || $action === 'permanent_delete')) {
        header("Location: ../recycle-bin/index.php");
    } else {
        header("Location: index.php");
    }
    exit();
}

/**
 * Ensure the APPLICANT table has soft delete columns
 */
function ensureSoftDeleteColumns($pdo) {
    try {
        // Check if soft delete columns exist, if not, add them
        $columns_to_add = [
            'APP_DELETED_AT' => 'TIMESTAMPTZ',
            'APP_DELETED_BY' => 'INTEGER REFERENCES USERS(USER_ID)'
        ];
        
        foreach ($columns_to_add as $column => $definition) {
            // Check if column exists
            $stmt = $pdo->prepare("SELECT column_name FROM information_schema.columns 
                                  WHERE table_name = 'applicant' AND column_name = :column_name");
            $stmt->execute([':column_name' => strtolower($column)]);
            
            if (!$stmt->fetch()) {
                // Column doesn't exist, add it
                $pdo->exec("ALTER TABLE APPLICANT ADD COLUMN {$column} {$definition}");
                error_log("Added column {$column} to APPLICANT table");
            }
        }
        
        // Create index on deleted_at column for better performance
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_applicant_deleted_at ON APPLICANT(APP_DELETED_AT)");
        
    } catch (PDOException $e) {
        error_log("Error ensuring soft delete columns: " . $e->getMessage());
        // Don't throw - this is not critical for basic functionality
    }
}

/**
 * Reset PostgreSQL sequences after permanent deletion
 */
function resetSequences($pdo) {
    try {
        $tables_sequences = [
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
            
            // Get the maximum ID currently in the table
            $check_stmt = $pdo->query("SELECT MAX({$column}) as max_id FROM {$table}");
            $max_result = $check_stmt->fetch(PDO::FETCH_ASSOC);
            $next_value = ($max_result['max_id'] !== null) ? intval($max_result['max_id']) + 1 : 1;
            
            // Reset sequence
            $pdo->query("SELECT setval('{$sequence}', {$next_value}, false)");
        }
    } catch (Exception $e) {
        error_log('Error resetting sequences: ' . $e->getMessage());
        // Don't throw - this is not critical
    }
}
?>