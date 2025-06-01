<?php
// Clean output buffer and prevent any unwanted output
if (ob_get_level()) {
    ob_end_clean();
}
ob_start();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Clean any accumulated output and set content type to JSON early
ob_clean();
header('Content-Type: application/json');

// Include necessary files
require_once "../../config/database.php";
require_once "../../includes/RecycleBinManager.php";

// Function to send JSON response and exit cleanly
function sendResponse($success, $message, $data = []) {
    // Clean any accumulated output before sending JSON
    if (ob_get_level()) {
        ob_clean();
    }
    
    echo json_encode(array_merge([
        'success' => $success,
        'message' => $message,
        'timestamp' => date('Y-m-d H:i:s')
    ], $data));
    exit;
}

// Check if user is logged in and has admin role
if (!isset($_SESSION["user_id"])) {
    sendResponse(false, 'Authentication required');
}

// Check admin role - handle different session key possibilities
$user_role = $_SESSION["role"] ?? $_SESSION["user_role"] ?? null;
if (!$user_role || strtoupper($user_role) !== 'ADMIN') {
    sendResponse(false, 'Admin privileges required');
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Only POST requests are allowed');
}

// Function to validate and sanitize input
function sanitize_input($data) {
    if (is_array($data)) {
        return array_map('sanitize_input', $data);
    }
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Get and validate required parameters
$item_id = isset($_POST['id']) ? (int)sanitize_input($_POST['id']) : 0;
$table_key = isset($_POST['table']) ? sanitize_input($_POST['table']) : '';
$bulk_restore = isset($_POST['bulk']) ? (bool)$_POST['bulk'] : false;
$item_ids = isset($_POST['ids']) ? $_POST['ids'] : [];

// Handle bulk restore
if ($bulk_restore && !empty($item_ids)) {
    // Validate bulk IDs
    $validated_ids = [];
    foreach ($item_ids as $id) {
        $clean_id = (int)sanitize_input($id);
        if ($clean_id > 0) {
            $validated_ids[] = $clean_id;
        }
    }
    
    if (empty($validated_ids)) {
        sendResponse(false, 'No valid item IDs provided for bulk restore');
    }
    
    if (empty($table_key)) {
        sendResponse(false, 'Table key is required for bulk restore');
    }
    
    // Process bulk restore
    processBulkRestore($validated_ids, $table_key, $pdo);
    
} else {
    // Handle single item restore
    if ($item_id <= 0) {
        sendResponse(false, 'Invalid item ID');
    }
    
    if (empty($table_key)) {
        sendResponse(false, 'Table key is required');
    }
    
    // Process single item restore
    processSingleRestore($item_id, $table_key, $pdo);
}

/**
 * FIXED: Process single item restoration - handles both unified recycle_bin AND legacy soft delete properly
 */
function processSingleRestore($item_id, $table_key, $pdo) {
    try {
        // Initialize RecycleBinManager
        $recycleBin = new RecycleBinManager($pdo);
        
        // Validate table key
        if (!$recycleBin->isValidTable($table_key)) {
            sendResponse(false, 'Invalid table type');
        }
        
        // Get table configuration
        $table_config = $recycleBin->getTableConfig($table_key);
        $item_name = null;
        $found_source = null;
        $item_data = null;
        
        // UNIFIED STRATEGY: Check both recycle_bin table AND legacy soft delete columns
        
        // Strategy 1: Check unified recycle_bin table first
        try {
            $recycle_stmt = $pdo->prepare("
                SELECT rec_id, rec_original_id, rec_item_title, rec_item_data, rec_original_table
                FROM recycle_bin 
                WHERE rec_id = :item_id AND rec_original_table = :table_name
            ");
            $recycle_stmt->bindParam(':item_id', $item_id, PDO::PARAM_INT);
            $recycle_stmt->bindParam(':table_name', $table_config['table_name'], PDO::PARAM_STR);
            $recycle_stmt->execute();
            $recycle_data = $recycle_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($recycle_data) {
                $item_name = $recycle_data['rec_item_title'];
                $found_source = 'recycle_bin';
                $item_data = json_decode($recycle_data['rec_item_data'], true);
            }
        } catch (PDOException $e) {
            error_log("Error checking recycle_bin table: " . $e->getMessage());
        }
        
        // Strategy 2: Check legacy soft delete columns (especially important for users)
        if (!$found_source && $table_config) {
            try {
                // Check if soft delete columns exist first
                $column_check = $pdo->prepare("SELECT column_name FROM information_schema.columns 
                                              WHERE table_name = ? AND column_name = ?");
                $column_check->execute([strtolower($table_config['table_name']), strtolower($table_config['deleted_at_column'])]);
                
                if ($column_check->fetch()) {
                    $legacy_stmt = $pdo->prepare("SELECT * FROM {$table_config['table_name']} 
                                                 WHERE {$table_config['id_column']} = :item_id 
                                                 AND {$table_config['deleted_at_column']} IS NOT NULL");
                    $legacy_stmt->bindParam(':item_id', $item_id, PDO::PARAM_INT);
                    $legacy_stmt->execute();
                    $item_data = $legacy_stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($item_data) {
                        $item_name = $item_data[$table_config['name_column']] ?? "ID: {$item_id}";
                        $found_source = 'legacy_soft_delete';
                    }
                }
            } catch (PDOException $e) {
                error_log("Error checking legacy soft delete for {$table_config['table_name']}: " . $e->getMessage());
            }
        }
        
        // If item not found in either location
        if (!$found_source || !$item_data) {
            sendResponse(false, 'Item not found in recycle bin', [
                'item_id' => $item_id,
                'table_key' => $table_key,
                'searched_sources' => ['recycle_bin', 'legacy_soft_delete'],
                'debug_info' => [
                    'table_name' => $table_config['table_name'],
                    'id_column' => $table_config['id_column'],
                    'deleted_at_column' => $table_config['deleted_at_column']
                ]
            ]);
        }
        
        // Additional validation for user accounts
        if ($table_key === 'users') {
            $user_id_field = isset($item_data['user_id']) ? $item_data['user_id'] : 
                           (isset($item_data['USER_ID']) ? $item_data['USER_ID'] : null);
            
            if ($user_id_field && $user_id_field == $_SESSION['user_id']) {
                sendResponse(false, 'Cannot restore your own account while logged in', [
                    'item_id' => $item_id,
                    'table_key' => $table_key,
                    'reason' => 'self_restoration_blocked'
                ]);
            }
        }

        // Perform the restoration using RecycleBinManager
        $result = false;
        try {
            $result = $recycleBin->restoreItem($table_key, $item_id);
        } catch (Exception $restore_error) {
            error_log("RecycleBinManager restore error: " . $restore_error->getMessage());
            sendResponse(false, "Restoration failed: " . $restore_error->getMessage(), [
                'item_id' => $item_id,
                'table_key' => $table_key,
                'source' => $found_source,
                'error_type' => get_class($restore_error),
                'line' => $restore_error->getLine()
            ]);
        }
        
        if ($result) {
            // Log the restoration activity with proper table name handling
            try {
                $activity_details = "Restored {$table_config['display_name']} item: {$item_name} " .
                                  "(ID: {$item_id}, Table: {$table_key}, Source: {$found_source})";
                
                // Use lowercase table name for logging (consistent with working population code)
                $log_query = "INSERT INTO user_logs (user_id, log_type, log_details, log_timestamp) 
                             VALUES (:user_id, 'RESTORE_ITEM', :details, CURRENT_TIMESTAMP)";
                $log_stmt = $pdo->prepare($log_query);
                $log_stmt->bindParam(":user_id", $_SESSION["user_id"]);
                $log_stmt->bindParam(":details", $activity_details);
                $log_stmt->execute();
            } catch (Exception $log_error) {
                // Log error but don't fail the operation
                error_log("Failed to log restoration activity: " . $log_error->getMessage());
            }
            
            $success_message = "{$table_config['display_name']} '{$item_name}' restored successfully.";
            
            // Prepare response data
            $response_data = [
                'item_id' => $item_id,
                'table_key' => $table_key,
                'item_name' => $item_name,
                'table_display_name' => $table_config['display_name'],
                'action' => 'restore',
                'restored' => true,
                'source' => $found_source,
                'redirect_url' => getRedirectUrl($table_key),
                'restore_location' => getRestoreLocationDescription($table_key)
            ];
            
            sendResponse(true, $success_message, $response_data);
            
        } else {
            sendResponse(false, "Failed to restore {$table_config['display_name']} '{$item_name}'. The restoration operation did not complete successfully.", [
                'item_id' => $item_id,
                'table_key' => $table_key,
                'item_name' => $item_name,
                'source' => $found_source,
                'action' => 'restore',
                'debug_info' => [
                    'recycleBinManager_result' => $result,
                    'found_source' => $found_source
                ]
            ]);
        }

    } catch (Exception $e) {
        // Log the error with detailed information
        error_log("Error in restore_item.php (single): " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        
        sendResponse(false, "Error processing restoration request: " . $e->getMessage(), [
            'item_id' => $item_id,
            'table_key' => $table_key,
            'error_type' => get_class($e),
            'line' => $e->getLine(),
            'file' => basename($e->getFile())
        ]);
    }
}

/**
 * FIXED: Process bulk restoration - handles both unified recycle_bin AND legacy soft delete properly
 */
function processBulkRestore($item_ids, $table_key, $pdo) {
    try {
        // Initialize RecycleBinManager
        $recycleBin = new RecycleBinManager($pdo);
        
        // Validate table key
        if (!$recycleBin->isValidTable($table_key)) {
            sendResponse(false, 'Invalid table type for bulk restore');
        }
        
        // Get table configuration
        $table_config = $recycleBin->getTableConfig($table_key);
        
        $restored_items = [];
        $failed_items = [];
        $skipped_items = [];
        
        // Process each item
        foreach ($item_ids as $item_id) {
            $item_name = null;
            $found_source = null;
            $item_data = null;
            
            try {
                // UNIFIED STRATEGY: Check both recycle_bin table AND legacy soft delete columns
                
                // Strategy 1: Check unified recycle_bin table first
                try {
                    $recycle_stmt = $pdo->prepare("
                        SELECT rec_id, rec_original_id, rec_item_title, rec_item_data, rec_original_table
                        FROM recycle_bin 
                        WHERE rec_id = :item_id AND rec_original_table = :table_name
                    ");
                    $recycle_stmt->bindParam(':item_id', $item_id, PDO::PARAM_INT);
                    $recycle_stmt->bindParam(':table_name', $table_config['table_name'], PDO::PARAM_STR);
                    $recycle_stmt->execute();
                    $recycle_data = $recycle_stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($recycle_data) {
                        $item_name = $recycle_data['rec_item_title'];
                        $found_source = 'recycle_bin';
                        $item_data = json_decode($recycle_data['rec_item_data'], true);
                    }
                } catch (PDOException $e) {
                    error_log("Error checking recycle_bin table for bulk item {$item_id}: " . $e->getMessage());
                }
                
                // Strategy 2: Check legacy soft delete columns
                if (!$found_source) {
                    try {
                        // Check if soft delete columns exist first
                        $column_check = $pdo->prepare("SELECT column_name FROM information_schema.columns 
                                                      WHERE table_name = ? AND column_name = ?");
                        $column_check->execute([strtolower($table_config['table_name']), strtolower($table_config['deleted_at_column'])]);
                        
                        if ($column_check->fetch()) {
                            $legacy_stmt = $pdo->prepare("SELECT * FROM {$table_config['table_name']} 
                                                         WHERE {$table_config['id_column']} = :item_id 
                                                         AND {$table_config['deleted_at_column']} IS NOT NULL");
                            $legacy_stmt->bindParam(':item_id', $item_id, PDO::PARAM_INT);
                            $legacy_stmt->execute();
                            $item_data = $legacy_stmt->fetch(PDO::FETCH_ASSOC);
                            
                            if ($item_data) {
                                $item_name = $item_data[$table_config['name_column']] ?? "ID: {$item_id}";
                                $found_source = 'legacy_soft_delete';
                            }
                        }
                    } catch (PDOException $e) {
                        error_log("Error checking legacy soft delete for bulk item {$item_id}: " . $e->getMessage());
                    }
                }

                if (!$found_source || !$item_data) {
                    $skipped_items[] = [
                        'id' => $item_id,
                        'reason' => 'Not found in recycle bin'
                    ];
                    continue;
                }

                // Get item name for display
                if (!$item_name) {
                    $item_name = "ID: {$item_id}";
                }
                
                // Skip if trying to restore own account
                if ($table_key === 'users') {
                    $user_id_field = isset($item_data['user_id']) ? $item_data['user_id'] : 
                                   (isset($item_data['USER_ID']) ? $item_data['USER_ID'] : null);
                    
                    if ($user_id_field && $user_id_field == $_SESSION['user_id']) {
                        $skipped_items[] = [
                            'id' => $item_id,
                            'name' => $item_name,
                            'reason' => 'Cannot restore your own account'
                        ];
                        continue;
                    }
                }

                // Attempt restoration
                $result = $recycleBin->restoreItem($table_key, $item_id);
                
                if ($result) {
                    $restored_items[] = [
                        'id' => $item_id,
                        'name' => $item_name,
                        'source' => $found_source
                    ];
                } else {
                    $failed_items[] = [
                        'id' => $item_id,
                        'name' => $item_name,
                        'source' => $found_source,
                        'reason' => 'Restoration failed'
                    ];
                }
                
            } catch (Exception $item_error) {
                $failed_items[] = [
                    'id' => $item_id,
                    'name' => $item_name ?? "ID: {$item_id}",
                    'reason' => $item_error->getMessage()
                ];
            }
        }
        
        // Log the bulk restoration activity
        if (!empty($restored_items)) {
            try {
                $restored_count = count($restored_items);
                $activity_details = "Bulk restored {$restored_count} {$table_config['display_name']} items " .
                                  "(Table: {$table_key})";
                
                // Use lowercase table name for logging (consistent with working population code)
                $log_query = "INSERT INTO user_logs (user_id, log_type, log_details, log_timestamp) 
                             VALUES (:user_id, 'BULK_RESTORE_ITEMS', :details, CURRENT_TIMESTAMP)";
                $log_stmt = $pdo->prepare($log_query);
                $log_stmt->bindParam(":user_id", $_SESSION["user_id"]);
                $log_stmt->bindParam(":details", $activity_details);
                $log_stmt->execute();
            } catch (Exception $log_error) {
                error_log("Failed to log bulk restoration activity: " . $log_error->getMessage());
            }
        }
        
        // Prepare response message
        $total_processed = count($item_ids);
        $restored_count = count($restored_items);
        $failed_count = count($failed_items);
        $skipped_count = count($skipped_items);
        
        $message_parts = [];
        if ($restored_count > 0) {
            $message_parts[] = "{$restored_count} items restored successfully";
        }
        if ($failed_count > 0) {
            $message_parts[] = "{$failed_count} items failed to restore";
        }
        if ($skipped_count > 0) {
            $message_parts[] = "{$skipped_count} items skipped";
        }
        
        $message = "Bulk restore completed: " . implode(', ', $message_parts);
        
        $response_data = [
            'bulk_restore' => true,
            'table_key' => $table_key,
            'table_display_name' => $table_config['display_name'],
            'total_processed' => $total_processed,
            'restored_count' => $restored_count,
            'failed_count' => $failed_count,
            'skipped_count' => $skipped_count,
            'restored_items' => $restored_items,
            'failed_items' => $failed_items,
            'skipped_items' => $skipped_items,
            'redirect_url' => getRedirectUrl($table_key),
            'restore_location' => getRestoreLocationDescription($table_key)
        ];
        
        // Determine success based on results
        $success = $restored_count > 0;
        
        sendResponse($success, $message, $response_data);
        
    } catch (Exception $e) {
        // Log the error
        error_log("Error in restore_item.php (bulk): " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        
        sendResponse(false, "Error processing bulk restoration request: " . $e->getMessage(), [
            'bulk_restore' => true,
            'table_key' => $table_key,
            'error_type' => get_class($e),
            'line' => $e->getLine(),
            'file' => basename($e->getFile())
        ]);
    }
}

/**
 * Get appropriate redirect URL based on table type
 */
function getRedirectUrl($table_key) {
    $redirect_urls = [
        'users' => '../../users/index.php',
        'applicant' => '../../scholarships/index.php',
        'announcements' => '../../announcements/index.php',
        'contact_messages' => '../../contacts/index.php',
        'population_data' => '../../population/index.php',
        'scholarship' => '../../scholarships/scholarship.php',
        'scholarship_status' => '../../scholarships/status.php'
    ];
    
    return $redirect_urls[$table_key] ?? '../index.php';
}

/**
 * Get description of where the item will be restored
 */
function getRestoreLocationDescription($table_key) {
    $descriptions = [
        'users' => 'Users Management section',
        'applicant' => 'Scholarships Applications list',
        'announcements' => 'Announcements section',
        'contact_messages' => 'Contact Messages list',
        'population_data' => 'Population Data list',
        'scholarship' => 'Scholarships Management',
        'scholarship_status' => 'Scholarship Status Records'
    ];
    
    return $descriptions[$table_key] ?? 'its original location';
}
?>