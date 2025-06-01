<?php
// Clean output buffer and prevent any unwanted output
if (ob_get_level()) {
    ob_end_clean();
}
ob_start();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set content type to JSON early and clean any previous output
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
$action = isset($_POST['action']) ? sanitize_input($_POST['action']) : '';
$items_json = isset($_POST['items']) ? $_POST['items'] : '';
$confirm = isset($_POST['confirm']) ? (bool)$_POST['confirm'] : false;

// Parse items JSON
$items = [];
if (!empty($items_json)) {
    if (is_string($items_json)) {
        $items = json_decode($items_json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            sendResponse(false, 'Invalid JSON format for items');
        }
    } else {
        $items = $items_json;
    }
}

// Validate action
$allowed_actions = ['restore', 'permanent_delete', 'soft_delete', 'empty_all'];
if (!in_array($action, $allowed_actions)) {
    sendResponse(false, 'Invalid action specified');
}

// Validate items array for non-empty_all actions
if ($action !== 'empty_all' && empty($items)) {
    sendResponse(false, 'No items specified for bulk action');
}

// Require confirmation for destructive operations
if (in_array($action, ['permanent_delete', 'empty_all']) && !$confirm) {
    sendResponse(false, 'Confirmation required for destructive operations');
}

try {
    // Initialize RecycleBinManager
    $recycleBin = new RecycleBinManager($pdo);
    
    // Process the bulk action
    switch ($action) {
        case 'restore':
            processBulkRestore($items, $recycleBin, $pdo);
            break;
            
        case 'permanent_delete':
            processBulkPermanentDelete($items, $recycleBin, $pdo);
            break;
            
        case 'soft_delete':
            processBulkSoftDelete($items, $recycleBin, $pdo);
            break;
            
        case 'empty_all':
            processEmptyAll($recycleBin, $pdo);
            break;
            
        default:
            sendResponse(false, 'Unsupported action');
    }

} catch (Exception $e) {
    // Log the error with detailed information
    error_log("Error in bulk_actions.php: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    sendResponse(false, "Error processing bulk action: " . $e->getMessage(), [
        'action' => $action,
        'error_type' => get_class($e),
        'line' => $e->getLine(),
        'file' => basename($e->getFile())
    ]);
}

/**
 * Process bulk restore operation - FIXED TO HANDLE USERS PROPERLY
 */
function processBulkRestore($items, $recycleBin, $pdo) {
    // Group items by table for processing
    $items_by_table = [];
    foreach ($items as $item) {
        $table_key = $item['table'] ?? null;
        $item_id = $item['id'] ?? null;
        
        if (!$table_key || !$item_id) {
            continue;
        }
        
        if (!isset($items_by_table[$table_key])) {
            $items_by_table[$table_key] = [];
        }
        $items_by_table[$table_key][] = $item_id;
    }
    
    $overall_results = [
        'total_processed' => 0,
        'success_count' => 0,
        'failed_count' => 0,
        'skipped_count' => 0,
        'successful_items' => [],
        'failed_items' => [],
        'skipped_items' => [],
        'tables_processed' => []
    ];
    
    foreach ($items_by_table as $table_key => $table_items) {
        // Validate table key
        if (!$recycleBin->isValidTable($table_key)) {
            foreach ($table_items as $item_id) {
                $overall_results['skipped_items'][] = [
                    'id' => $item_id,
                    'table' => $table_key,
                    'reason' => 'Invalid table type'
                ];
                $overall_results['skipped_count']++;
                $overall_results['total_processed']++;
            }
            continue;
        }
        
        $table_config = $recycleBin->getTableConfig($table_key);
        $results = processItemsBatch($table_items, $table_key, 'restore', $recycleBin, $pdo);
        
        // Merge results
        $overall_results['total_processed'] += $results['total_processed'];
        $overall_results['success_count'] += $results['success_count'];
        $overall_results['failed_count'] += $results['failed_count'];
        $overall_results['skipped_count'] += $results['skipped_count'];
        $overall_results['successful_items'] = array_merge($overall_results['successful_items'], $results['successful_items']);
        $overall_results['failed_items'] = array_merge($overall_results['failed_items'], $results['failed_items']);
        $overall_results['skipped_items'] = array_merge($overall_results['skipped_items'], $results['skipped_items']);
        
        $overall_results['tables_processed'][] = [
            'table_key' => $table_key,
            'display_name' => $table_config['display_name'],
            'results' => $results
        ];
        
        // Log the bulk operation for this table
        if ($results['success_count'] > 0) {
            logBulkActivity('BULK_RESTORE', $table_config['display_name'], $results, $pdo);
        }
    }
    
    $message = buildOverallResultMessage('restore', $overall_results);
    
    $response_data = [
        'action' => 'restore',
        'tables_processed' => count($items_by_table)
    ];
    
    sendResponse($overall_results['success_count'] > 0, $message, array_merge($response_data, $overall_results));
}

/**
 * Process bulk permanent delete operation
 */
function processBulkPermanentDelete($items, $recycleBin, $pdo) {
    // Group items by table for processing
    $items_by_table = [];
    foreach ($items as $item) {
        $table_key = $item['table'] ?? null;
        $item_id = $item['id'] ?? null;
        
        if (!$table_key || !$item_id) {
            continue;
        }
        
        if (!isset($items_by_table[$table_key])) {
            $items_by_table[$table_key] = [];
        }
        $items_by_table[$table_key][] = $item_id;
    }
    
    $overall_results = [
        'total_processed' => 0,
        'success_count' => 0,
        'failed_count' => 0,
        'skipped_count' => 0,
        'successful_items' => [],
        'failed_items' => [],
        'skipped_items' => [],
        'tables_processed' => []
    ];
    
    foreach ($items_by_table as $table_key => $table_items) {
        // Validate table key
        if (!$recycleBin->isValidTable($table_key)) {
            foreach ($table_items as $item_id) {
                $overall_results['skipped_items'][] = [
                    'id' => $item_id,
                    'table' => $table_key,
                    'reason' => 'Invalid table type'
                ];
                $overall_results['skipped_count']++;
                $overall_results['total_processed']++;
            }
            continue;
        }
        
        $table_config = $recycleBin->getTableConfig($table_key);
        $results = processItemsBatch($table_items, $table_key, 'permanent_delete', $recycleBin, $pdo);
        
        // Merge results
        $overall_results['total_processed'] += $results['total_processed'];
        $overall_results['success_count'] += $results['success_count'];
        $overall_results['failed_count'] += $results['failed_count'];
        $overall_results['skipped_count'] += $results['skipped_count'];
        $overall_results['successful_items'] = array_merge($overall_results['successful_items'], $results['successful_items']);
        $overall_results['failed_items'] = array_merge($overall_results['failed_items'], $results['failed_items']);
        $overall_results['skipped_items'] = array_merge($overall_results['skipped_items'], $results['skipped_items']);
        
        $overall_results['tables_processed'][] = [
            'table_key' => $table_key,
            'display_name' => $table_config['display_name'],
            'results' => $results
        ];
        
        // Log the bulk operation for this table
        if ($results['success_count'] > 0) {
            logBulkActivity('BULK_PERMANENT_DELETE', $table_config['display_name'], $results, $pdo);
        }
    }
    
    $message = buildOverallResultMessage('permanent_delete', $overall_results);
    
    $response_data = [
        'action' => 'permanent_delete',
        'tables_processed' => count($items_by_table)
    ];
    
    sendResponse($overall_results['success_count'] > 0, $message, array_merge($response_data, $overall_results));
}

/**
 * Process bulk soft delete operation
 */
function processBulkSoftDelete($items, $recycleBin, $pdo) {
    // Group items by table for processing
    $items_by_table = [];
    foreach ($items as $item) {
        $table_key = $item['table'] ?? null;
        $item_id = $item['id'] ?? null;
        
        if (!$table_key || !$item_id) {
            continue;
        }
        
        if (!isset($items_by_table[$table_key])) {
            $items_by_table[$table_key] = [];
        }
        $items_by_table[$table_key][] = $item_id;
    }
    
    $overall_results = [
        'total_processed' => 0,
        'success_count' => 0,
        'failed_count' => 0,
        'skipped_count' => 0,
        'successful_items' => [],
        'failed_items' => [],
        'skipped_items' => [],
        'tables_processed' => []
    ];
    
    foreach ($items_by_table as $table_key => $table_items) {
        // Validate table key
        if (!$recycleBin->isValidTable($table_key)) {
            foreach ($table_items as $item_id) {
                $overall_results['skipped_items'][] = [
                    'id' => $item_id,
                    'table' => $table_key,
                    'reason' => 'Invalid table type'
                ];
                $overall_results['skipped_count']++;
                $overall_results['total_processed']++;
            }
            continue;
        }
        
        $table_config = $recycleBin->getTableConfig($table_key);
        $results = processItemsBatch($table_items, $table_key, 'soft_delete', $recycleBin, $pdo);
        
        // Merge results
        $overall_results['total_processed'] += $results['total_processed'];
        $overall_results['success_count'] += $results['success_count'];
        $overall_results['failed_count'] += $results['failed_count'];
        $overall_results['skipped_count'] += $results['skipped_count'];
        $overall_results['successful_items'] = array_merge($overall_results['successful_items'], $results['successful_items']);
        $overall_results['failed_items'] = array_merge($overall_results['failed_items'], $results['failed_items']);
        $overall_results['skipped_items'] = array_merge($overall_results['skipped_items'], $results['skipped_items']);
        
        $overall_results['tables_processed'][] = [
            'table_key' => $table_key,
            'display_name' => $table_config['display_name'],
            'results' => $results
        ];
        
        // Log the bulk operation for this table
        if ($results['success_count'] > 0) {
            logBulkActivity('BULK_SOFT_DELETE', $table_config['display_name'], $results, $pdo);
        }
    }
    
    $message = buildOverallResultMessage('soft_delete', $overall_results);
    
    $response_data = [
        'action' => 'soft_delete',
        'tables_processed' => count($items_by_table)
    ];
    
    sendResponse($overall_results['success_count'] > 0, $message, array_merge($response_data, $overall_results));
}

/**
 * Process empty all recycle bin operation
 */
function processEmptyAll($recycleBin, $pdo) {
    // Get current statistics before emptying
    $stats = $recycleBin->getRecycleBinStats();
    
    if ($stats['total_items'] === 0) {
        sendResponse(false, 'Recycle bin is already empty');
    }
    
    // Empty the recycle bin
    $deleted_count = $recycleBin->emptyRecycleBin();
    
    if ($deleted_count > 0) {
        // Log the empty operation
        try {
            $activity_details = "Emptied entire recycle bin - permanently deleted {$deleted_count} items across {$stats['total_tables']} tables";
            
            $log_query = "INSERT INTO user_logs (user_id, log_type, log_details, log_timestamp) 
                         VALUES (:user_id, 'EMPTY_RECYCLE_BIN', :details, CURRENT_TIMESTAMP)";
            $log_stmt = $pdo->prepare($log_query);
            $log_stmt->bindParam(":user_id", $_SESSION["user_id"]);
            $log_stmt->bindParam(":details", $activity_details);
            $log_stmt->execute();
        } catch (Exception $log_error) {
            error_log("Failed to log empty all activity: " . $log_error->getMessage());
        }
        
        $message = "Recycle bin emptied successfully. {$deleted_count} items permanently deleted across {$stats['total_tables']} tables.";
        
        $response_data = [
            'action' => 'empty_all',
            'deleted_count' => $deleted_count,
            'previous_stats' => $stats,
            'tables_affected' => $stats['total_tables']
        ];
        
        sendResponse(true, $message, $response_data);
        
    } else {
        sendResponse(false, 'No items were deleted. Recycle bin may already be empty.');
    }
}

/**
 * FIXED: Process a batch of items for a specific action - handles users properly like population
 */
function processItemsBatch($items, $table_key, $action, $recycleBin, $pdo) {
    $table_config = $recycleBin->getTableConfig($table_key);
    
    $successful_items = [];
    $failed_items = [];
    $skipped_items = [];
    
    foreach ($items as $item_id) {
        $item_id = (int)$item_id;
        $item_name = null;
        
        if ($item_id <= 0) {
            $skipped_items[] = [
                'id' => $item_id,
                'table' => $table_key,
                'reason' => 'Invalid item ID'
            ];
            continue;
        }
        
        try {
            // UNIFIED APPROACH: Check both recycle_bin table AND legacy soft delete
            $item_info = null;
            $found_source = null;
            
            // Strategy 1: Check recycle_bin table first (unified approach)
            if (in_array($action, ['restore', 'permanent_delete'])) {
                $recycle_stmt = $pdo->prepare("
                    SELECT rec_id, rec_original_id, rec_item_title, rec_item_data
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
                }
            }
            
            // Strategy 2: Check legacy soft delete columns (especially for users)
            if (!$found_source && $table_config) {
                $condition = '';
                if ($action === 'soft_delete') {
                    $condition = "AND {$table_config['deleted_at_column']} IS NULL";
                } else {
                    $condition = "AND {$table_config['deleted_at_column']} IS NOT NULL";
                }
                
                try {
                    $stmt = $pdo->prepare("SELECT * FROM {$table_config['table_name']} 
                                           WHERE {$table_config['id_column']} = :item_id {$condition}");
                    $stmt->bindParam(':item_id', $item_id, PDO::PARAM_INT);
                    $stmt->execute();
                    $item_info = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($item_info) {
                        $item_name = $item_info[$table_config['name_column']] ?? "ID: {$item_id}";
                        $found_source = 'legacy_soft_delete';
                    }
                } catch (PDOException $e) {
                    // Column might not exist, continue with other strategies
                    error_log("Column check failed for {$table_config['table_name']}: " . $e->getMessage());
                }
            }
            
            // If item not found, skip it
            if (!$found_source) {
                $skipped_items[] = [
                    'id' => $item_id,
                    'table' => $table_key,
                    'reason' => $action === 'soft_delete' ? 'Item not found or already in recycle bin' : 'Item not found in recycle bin'
                ];
                continue;
            }
            
            // Skip self-operations for user accounts
            if ($table_key === 'users' && $found_source === 'legacy_soft_delete' && 
                isset($item_info['user_id']) && $item_info['user_id'] == $_SESSION['user_id']) {
                $skipped_items[] = [
                    'id' => $item_id,
                    'name' => $item_name,
                    'table' => $table_key,
                    'reason' => 'Cannot perform action on your own account'
                ];
                continue;
            }
            
            // Perform the action using RecycleBinManager
            $result = false;
            try {
                switch ($action) {
                    case 'restore':
                        $result = $recycleBin->restoreItem($table_key, $item_id);
                        break;
                    case 'permanent_delete':
                        $result = $recycleBin->permanentlyDeleteItem($table_key, $item_id);
                        break;
                    case 'soft_delete':
                        $result = $recycleBin->softDeleteItem($table_key, $item_id, $_SESSION["user_id"]);
                        break;
                }
            } catch (Exception $manager_error) {
                error_log("RecycleBinManager error for {$table_key} item {$item_id}: " . $manager_error->getMessage());
                throw $manager_error;
            }
            
            if ($result) {
                $successful_items[] = [
                    'id' => $item_id,
                    'name' => $item_name,
                    'table' => $table_key,
                    'source' => $found_source
                ];
            } else {
                $failed_items[] = [
                    'id' => $item_id,
                    'name' => $item_name,
                    'table' => $table_key,
                    'reason' => ucfirst(str_replace('_', ' ', $action)) . ' operation failed',
                    'source' => $found_source
                ];
            }
            
        } catch (Exception $item_error) {
            error_log("Error processing {$table_key} item {$item_id}: " . $item_error->getMessage());
            $failed_items[] = [
                'id' => $item_id,
                'name' => $item_name ?? "ID: {$item_id}",
                'table' => $table_key,
                'reason' => $item_error->getMessage()
            ];
        }
    }
    
    return [
        'total_processed' => count($items),
        'success_count' => count($successful_items),
        'failed_count' => count($failed_items),
        'skipped_count' => count($skipped_items),
        'successful_items' => $successful_items,
        'failed_items' => $failed_items,
        'skipped_items' => $skipped_items
    ];
}

/**
 * Log bulk activity - FIXED TO USE CORRECT TABLE NAME
 */
function logBulkActivity($log_type, $table_display_name, $results, $pdo) {
    try {
        $activity_details = "Bulk operation: {$log_type} on {$table_display_name} - " .
                          "Processed: {$results['total_processed']}, " .
                          "Successful: {$results['success_count']}, " .
                          "Failed: {$results['failed_count']}, " .
                          "Skipped: {$results['skipped_count']}";
        
        $log_query = "INSERT INTO user_logs (user_id, log_type, log_details, log_timestamp) 
                     VALUES (:user_id, :log_type, :details, CURRENT_TIMESTAMP)";
        $log_stmt = $pdo->prepare($log_query);
        $log_stmt->bindParam(":user_id", $_SESSION["user_id"]);
        $log_stmt->bindParam(":log_type", $log_type);
        $log_stmt->bindParam(":details", $activity_details);
        $log_stmt->execute();
    } catch (Exception $log_error) {
        error_log("Failed to log bulk activity: " . $log_error->getMessage());
    }
}

/**
 * Build result message for single table operations
 */
function buildResultMessage($action, $results, $table_display_name) {
    $action_past_tense = [
        'restore' => 'restored',
        'permanent_delete' => 'permanently deleted',
        'soft_delete' => 'moved to recycle bin'
    ];
    
    $message_parts = [];
    
    if ($results['success_count'] > 0) {
        $message_parts[] = "{$results['success_count']} {$table_display_name} items {$action_past_tense[$action]} successfully";
    }
    
    if ($results['failed_count'] > 0) {
        $message_parts[] = "{$results['failed_count']} items failed";
    }
    
    if ($results['skipped_count'] > 0) {
        $message_parts[] = "{$results['skipped_count']} items skipped";
    }
    
    return "Bulk " . str_replace('_', ' ', $action) . " completed: " . implode(', ', $message_parts);
}

/**
 * Build result message for multi-table operations
 */
function buildOverallResultMessage($action, $results) {
    $action_past_tense = [
        'restore' => 'restored',
        'permanent_delete' => 'permanently deleted',
        'soft_delete' => 'moved to recycle bin'
    ];
    
    $message_parts = [];
    
    if ($results['success_count'] > 0) {
        $message_parts[] = "{$results['success_count']} items {$action_past_tense[$action]} successfully";
    }
    
    if ($results['failed_count'] > 0) {
        $message_parts[] = "{$results['failed_count']} items failed";
    }
    
    if ($results['skipped_count'] > 0) {
        $message_parts[] = "{$results['skipped_count']} items skipped";
    }
    
    $tables_count = count($results['tables_processed']);
    $table_text = $tables_count === 1 ? 'table' : 'tables';
    
    return "Bulk " . str_replace('_', ' ', $action) . " completed across {$tables_count} {$table_text}: " . implode(', ', $message_parts);
}

/**
 * Get redirect URL based on table type
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
?>