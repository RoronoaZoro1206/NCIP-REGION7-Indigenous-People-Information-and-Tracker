<?php
// Clean output buffer and prevent any unwanted output
if (ob_get_level()) {
    ob_end_clean();
}
ob_start();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include necessary files
require_once "../config/database.php";
require_once "../includes/RecycleBinManager.php";

// Check if request is AJAX
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

// Check if user is logged in and has admin role
if (!isset($_SESSION["user_id"])) {
    if ($isAjax) {
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Authentication required']);
        exit;
    } else {
        header("Location: ../login.php");
        exit();
    }
}

// Check admin role
$user_role = $_SESSION["role"] ?? $_SESSION["user_role"] ?? null;
if (!$user_role || strtoupper($user_role) !== 'ADMIN') {
    if ($isAjax) {
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Admin privileges required']);
        exit;
    } else {
        $_SESSION['error_message'] = "Access denied. Admin privileges required.";
        header("Location: ../login.php");
        exit();
    }
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

// Function to send JSON response and exit cleanly
function sendJsonResponse($response) {
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Function to log restoration activity
function logRestorationActivity($pdo, $user_id, $item_type, $item_name, $item_id) {
    try {
        $log_stmt = $pdo->prepare("INSERT INTO user_logs (user_id, log_type, log_details, log_timestamp) 
                                  VALUES (:user_id, 'RESTORE_ITEM', :details, CURRENT_TIMESTAMP)");
        $log_details = "Restored {$item_type}: {$item_name} (ID: {$item_id})";
        $log_stmt->execute([
            ':user_id' => $user_id,
            ':details' => $log_details
        ]);
    } catch (Exception $log_error) {
        error_log("Failed to log restoration activity: " . $log_error->getMessage());
    }
}

// Handle POST request for restoration
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate required parameters
    if (!isset($_POST['item_id'])) {
        sendJsonResponse(['success' => false, 'message' => 'Missing item ID']);
    }
    
    $item_id = sanitize_input($_POST['item_id']);
    $table_key = isset($_POST['table_key']) ? sanitize_input($_POST['table_key']) : null;
    
    // Validate item_id is numeric
    if (!is_numeric($item_id) || $item_id <= 0) {
        sendJsonResponse(['success' => false, 'message' => 'Invalid item ID']);
    }
    
    $item_id = (int)$item_id;
    
    try {
        // Initialize RecycleBinManager
        $recycleBin = new RecycleBinManager($pdo);
        $pdo->beginTransaction();
        
        // Strategy 1: Check if item exists in unified recycle_bin table
        $recycle_stmt = $pdo->prepare("
            SELECT rec_id, rec_original_table, rec_original_id, rec_item_data, rec_item_title
            FROM recycle_bin 
            WHERE rec_id = :item_id
        ");
        $recycle_stmt->execute([':item_id' => $item_id]);
        $recycle_data = $recycle_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($recycle_data) {
            // Found in recycle_bin table - use RecycleBinManager
            $original_table = $recycle_data['rec_original_table'];
            
            // Map table names to RecycleBinManager keys
            $table_map = [
                'users' => 'users',
                'contact_message' => 'contact_messages',
                'contact_messages' => 'contact_messages',
                'population_table' => 'population_data',
                'population_data' => 'population_data',
                'scholarship' => 'scholarship',
                'announcements' => 'announcements',
                'announcement' => 'announcements',
                'applicant' => 'applicant',
                'scholarship_status' => 'scholarship_status'
            ];
            
            $mapped_table_key = $table_map[$original_table] ?? $original_table;
            
            // Validate table key
            if (!$recycleBin->isValidTable($mapped_table_key)) {
                $pdo->rollBack();
                sendJsonResponse(['success' => false, 'message' => "Invalid table type: {$mapped_table_key}"]);
            }
            
            // Use RecycleBinManager to restore
            $result = $recycleBin->restoreItem($mapped_table_key, $item_id);
            
            if ($result) {
                // Get table config for display name
                $table_config = $recycleBin->getTableConfig($mapped_table_key);
                $display_name = $table_config['display_name'] ?? 'Item';
                
                // Log the restoration activity
                logRestorationActivity(
                    $pdo, 
                    $_SESSION["user_id"], 
                    $display_name, 
                    $recycle_data['rec_item_title'], 
                    $recycle_data['rec_original_id']
                );
                
                $pdo->commit();
                sendJsonResponse([
                    'success' => true, 
                    'message' => "{$display_name} '{$recycle_data['rec_item_title']}' restored successfully",
                    'item_type' => $display_name,
                    'item_name' => $recycle_data['rec_item_title'],
                    'original_id' => $recycle_data['rec_original_id']
                ]);
            } else {
                $pdo->rollBack();
                sendJsonResponse(['success' => false, 'message' => 'Failed to restore item from recycle bin']);
            }
        }
        
        // Strategy 2: Check for legacy soft delete in users table (highest priority for users)
        $user_check = $pdo->prepare("
            SELECT user_id, user_username, user_email, user_role, user_deleted_at 
            FROM users 
            WHERE user_id = :user_id AND user_deleted_at IS NOT NULL
        ");
        $user_check->execute([':user_id' => $item_id]);
        $user_data = $user_check->fetch(PDO::FETCH_ASSOC);
        
        if ($user_data) {
            // Found user with soft delete - restore directly using EXACT same approach as population
            $restore_stmt = $pdo->prepare("
                UPDATE users 
                SET user_deleted_at = NULL, user_deleted_by = NULL 
                WHERE user_id = :user_id
            ");
            $result = $restore_stmt->execute([':user_id' => $item_id]);
            
            if ($result && $restore_stmt->rowCount() > 0) {
                // Log the restoration
                logRestorationActivity(
                    $pdo, 
                    $_SESSION["user_id"], 
                    'User Account', 
                    $user_data['user_username'], 
                    $item_id
                );
                
                $pdo->commit();
                sendJsonResponse([
                    'success' => true, 
                    'message' => "User '{$user_data['user_username']}' restored successfully",
                    'item_type' => 'User Account',
                    'item_name' => $user_data['user_username'],
                    'original_id' => $item_id
                ]);
            } else {
                $pdo->rollBack();
                sendJsonResponse(['success' => false, 'message' => 'Failed to restore user (no rows affected)']);
            }
        }
        
        // Strategy 3: Check for legacy soft delete in population_table (for population items)
        $population_check = $pdo->prepare("
            SELECT popt_id, popt_name, popt_deleted_at 
            FROM population_table 
            WHERE popt_id = :popt_id AND popt_deleted_at IS NOT NULL
        ");
        $population_check->execute([':popt_id' => $item_id]);
        $population_data = $population_check->fetch(PDO::FETCH_ASSOC);
        
        if ($population_data) {
            // Found population with soft delete - restore directly
            $restore_stmt = $pdo->prepare("
                UPDATE population_table 
                SET popt_deleted_at = NULL, popt_deleted_by = NULL 
                WHERE popt_id = :popt_id
            ");
            $result = $restore_stmt->execute([':popt_id' => $item_id]);
            
            if ($result && $restore_stmt->rowCount() > 0) {
                // Log the restoration
                logRestorationActivity(
                    $pdo, 
                    $_SESSION["user_id"], 
                    'Population Record', 
                    $population_data['popt_name'], 
                    $item_id
                );
                
                $pdo->commit();
                sendJsonResponse([
                    'success' => true, 
                    'message' => "Population record '{$population_data['popt_name']}' restored successfully",
                    'item_type' => 'Population Record',
                    'item_name' => $population_data['popt_name'],
                    'original_id' => $item_id
                ]);
            } else {
                $pdo->rollBack();
                sendJsonResponse(['success' => false, 'message' => 'Failed to restore population record']);
            }
        }
        
        // Strategy 4: Check for legacy soft delete in other tables if table_key is provided
        if ($table_key) {
            $table_configs = [
                'contact_messages' => [
                    'table_name' => 'contact_message',
                    'id_column' => 'contm_id',
                    'name_column' => 'contm_name',
                    'deleted_at_column' => 'contm_deleted_at',
                    'deleted_by_column' => 'contm_deleted_by',
                    'display_name' => 'Contact Message'
                ],
                'announcements' => [
                    'table_name' => 'announcement',
                    'id_column' => 'ann_id',
                    'name_column' => 'ann_title',
                    'deleted_at_column' => 'ann_deleted_at',
                    'deleted_by_column' => 'ann_deleted_by',
                    'display_name' => 'Announcement'
                ],
                'applicant' => [
                    'table_name' => 'applicant',
                    'id_column' => 'app_code',
                    'name_column' => 'app_last_name',
                    'deleted_at_column' => 'app_deleted_at',
                    'deleted_by_column' => 'app_deleted_by',
                    'display_name' => 'Scholarship Application'
                ],
                'scholarship' => [
                    'table_name' => 'scholarship',
                    'id_column' => 'scholar_code',
                    'name_column' => 'scholar_name',
                    'deleted_at_column' => 'scholar_deleted_at',
                    'deleted_by_column' => 'scholar_deleted_by',
                    'display_name' => 'Scholarship Program'
                ]
            ];
            
            if (isset($table_configs[$table_key])) {
                $config = $table_configs[$table_key];
                
                // Check if item exists with soft delete
                $check_stmt = $pdo->prepare("
                    SELECT {$config['id_column']}, {$config['name_column']}, {$config['deleted_at_column']} 
                    FROM {$config['table_name']} 
                    WHERE {$config['id_column']} = :item_id AND {$config['deleted_at_column']} IS NOT NULL
                ");
                $check_stmt->execute([':item_id' => $item_id]);
                $item_data = $check_stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($item_data) {
                    // Restore the item
                    $restore_stmt = $pdo->prepare("
                        UPDATE {$config['table_name']} 
                        SET {$config['deleted_at_column']} = NULL, {$config['deleted_by_column']} = NULL 
                        WHERE {$config['id_column']} = :item_id
                    ");
                    $result = $restore_stmt->execute([':item_id' => $item_id]);
                    
                    if ($result && $restore_stmt->rowCount() > 0) {
                        // Log the restoration
                        logRestorationActivity(
                            $pdo, 
                            $_SESSION["user_id"], 
                            $config['display_name'], 
                            $item_data[$config['name_column']], 
                            $item_id
                        );
                        
                        $pdo->commit();
                        sendJsonResponse([
                            'success' => true, 
                            'message' => "{$config['display_name']} '{$item_data[$config['name_column']]}' restored successfully",
                            'item_type' => $config['display_name'],
                            'item_name' => $item_data[$config['name_column']],
                            'original_id' => $item_id
                        ]);
                    } else {
                        $pdo->rollBack();
                        sendJsonResponse(['success' => false, 'message' => "Failed to restore {$config['display_name']}"]);
                    }
                }
            }
        }
        
        // If we reach here, item was not found in any approach
        $pdo->rollBack();
        sendJsonResponse(['success' => false, 'message' => 'Item not found in recycle bin or already restored']);
        
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Database error in restore.php: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        sendJsonResponse(['success' => false, 'message' => 'Database error occurred while restoring item']);
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("General error in restore.php: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        sendJsonResponse(['success' => false, 'message' => 'System error occurred: ' . $e->getMessage()]);
    }
}

// Handle GET request - redirect to recycle bin index
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if ($isAjax) {
        sendJsonResponse(['success' => false, 'message' => 'GET method not supported for restoration']);
    } else {
        $_SESSION['error_message'] = "Invalid request method. Use the restore buttons in the recycle bin.";
        header("Location: index.php");
        exit();
    }
}

// Handle other request methods
if (!in_array($_SERVER['REQUEST_METHOD'], ['POST', 'GET'])) {
    if ($isAjax) {
        sendJsonResponse(['success' => false, 'message' => 'Unsupported request method']);
    } else {
        $_SESSION['error_message'] = "Unsupported request method.";
        header("Location: index.php");
        exit();
    }
}

// Clean up output buffer before ending
ob_end_clean();
?>