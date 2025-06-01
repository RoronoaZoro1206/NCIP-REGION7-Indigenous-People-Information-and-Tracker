<?php
declare(strict_types=1);

class RecycleBinManager {
    private $pdo;
    private $allowed_tables;
    private $table_configs;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        
        // Define allowed tables and their configurations - UPDATED WITH CORRECT COLUMN NAMES
        $this->table_configs = [
            'users' => [
                'table_name' => 'users',
                'id_column' => 'user_id',
                'name_column' => 'user_username',
                'deleted_at_column' => 'user_deleted_at',
                'deleted_by_column' => 'user_deleted_by',
                'display_name' => 'User Account',
                'display_name_plural' => 'User Accounts',
                'restore_columns' => ['user_username', 'user_email', 'user_role']
            ],
            'applicant' => [
                'table_name' => 'applicant',
                'id_column' => 'app_code',
                'name_column' => 'app_last_name',
                'deleted_at_column' => 'app_deleted_at',
                'deleted_by_column' => 'app_deleted_by',
                'display_name' => 'Scholarship Application',
                'display_name_plural' => 'Scholarship Applications',
                'restore_columns' => ['app_last_name', 'app_first_name', 'app_email']
            ],
            'announcements' => [
                'table_name' => 'announcement',
                'id_column' => 'ann_id',
                'name_column' => 'ann_title',
                'deleted_at_column' => 'ann_deleted_at',
                'deleted_by_column' => 'ann_deleted_by',
                'display_name' => 'Announcement',
                'display_name_plural' => 'Announcements',
                'restore_columns' => ['ann_title', 'ann_content']
            ],
            'contact_messages' => [
                'table_name' => 'contact_message',
                'id_column' => 'contm_id',
                'name_column' => 'contm_name',
                'deleted_at_column' => 'contm_deleted_at',
                'deleted_by_column' => 'contm_deleted_by',
                'display_name' => 'Contact Message',
                'display_name_plural' => 'Contact Messages',
                'restore_columns' => ['contm_name', 'contm_email', 'contm_message']
            ],
            'population_data' => [
                'table_name' => 'population_table',
                'id_column' => 'popt_id',
                'name_column' => 'popt_name',
                'deleted_at_column' => 'popt_deleted_at',
                'deleted_by_column' => 'popt_deleted_by',
                'display_name' => 'Population Record',
                'display_name_plural' => 'Population Records',
                'restore_columns' => ['popt_name', 'popt_ip_group', 'popt_population']
            ],
            'scholarship' => [
                'table_name' => 'scholarship',
                'id_column' => 'scholar_code',
                'name_column' => 'scholar_name',
                'deleted_at_column' => 'scholar_deleted_at',
                'deleted_by_column' => 'scholar_deleted_by',
                'display_name' => 'Scholarship Program',
                'display_name_plural' => 'Scholarship Programs',
                'restore_columns' => ['scholar_name', 'scholar_course', 'scholar_year']
            ],
            'scholarship_status' => [
                'table_name' => 'scholarship_status',
                'id_column' => 'status_id',
                'name_column' => 'status_code',
                'deleted_at_column' => 'status_deleted_at',
                'deleted_by_column' => 'status_deleted_by',
                'display_name' => 'Scholarship Status',
                'display_name_plural' => 'Scholarship Statuses',
                'restore_columns' => ['status_code', 'status_remarks']
            ]
        ];
        
        $this->allowed_tables = array_keys($this->table_configs);
    }
    
    /**
     * Generate item title for display
     */
    private function generateItemTitle(string $table_key, array $item_data): string {
        $config = $this->table_configs[$table_key];
        
        switch ($table_key) {
            case 'users':
                return $item_data['user_username'] ?? 'Unknown User';
            case 'population_data':
                return $item_data['popt_name'] ?? 'Unknown Location';
            case 'contact_messages':
                return $item_data['contm_name'] ?? 'Unknown Contact';
            case 'applicant':
                return ($item_data['app_first_name'] ?? '') . ' ' . ($item_data['app_last_name'] ?? '');
            case 'announcements':
                return $item_data['ann_title'] ?? 'Unknown Announcement';
            case 'scholarship':
                return $item_data['scholar_name'] ?? 'Unknown Scholarship';
            case 'scholarship_status':
                return $item_data['status_code'] ?? 'Unknown Status';
            default:
                return $item_data[$config['name_column']] ?? 'Unknown Item';
        }
    }
    
    /**
     * Get all deleted items from recycle_bin table (unified approach)
     */
    public function getAllDeletedItems(): array {
        $all_items = [];
        
        try {
            // First get items from recycle_bin table
            $recycle_stmt = $this->pdo->prepare("
                SELECT rec_id, rec_original_table, rec_original_id, rec_item_title, rec_deleted_by, rec_deleted_at
                FROM recycle_bin 
                ORDER BY rec_deleted_at DESC
            ");
            $recycle_stmt->execute();
            $recycle_items = $recycle_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($recycle_items as $item) {
                // Map table names to our keys
                $table_map = [
                    'users' => 'users',
                    'population_table' => 'population_data',
                    'contact_message' => 'contact_messages',
                    'applicant' => 'applicant',
                    'announcement' => 'announcements',
                    'scholarship' => 'scholarship',
                    'scholarship_status' => 'scholarship_status'
                ];
                
                $table_key = $table_map[$item['rec_original_table']] ?? $item['rec_original_table'];
                $config = $this->table_configs[$table_key] ?? null;
                
                if ($config) {
                    $all_items[] = [
                        'id' => $item['rec_id'],
                        'name' => $item['rec_item_title'],
                        'table' => $table_key,
                        'table_display_name' => $config['display_name'],
                        'deleted_at' => $item['rec_deleted_at'],
                        'deleted_by' => $item['rec_deleted_by'],
                        'item_id' => $item['rec_id'],
                        'item_name' => $item['rec_item_title'],
                        'table_key' => $table_key,
                        'item_type' => $config['display_name'],
                        'original_id' => $item['rec_original_id']
                    ];
                }
            }
            
            // Also get items with soft delete columns (legacy support)
            foreach ($this->table_configs as $table_key => $config) {
                $legacy_items = $this->getDeletedItemsByTableLegacy($table_key);
                $all_items = array_merge($all_items, $legacy_items);
            }
            
        } catch (PDOException $e) {
            error_log("Error fetching all deleted items: " . $e->getMessage());
        }
        
        // Sort by deletion date (newest first)
        usort($all_items, function($a, $b) {
            return strtotime($b['deleted_at']) - strtotime($a['deleted_at']);
        });
        
        return $all_items;
    }
    
    /**
     * Get deleted items by specific table (unified approach)
     */
    public function getDeletedItemsByTable(string $table_key, int $limit = 50, int $offset = 0): array {
        if (!isset($this->table_configs[$table_key])) {
            return [];
        }
        
        $config = $this->table_configs[$table_key];
        $items = [];
        
        try {
            // First get items from recycle_bin table
            $recycle_stmt = $this->pdo->prepare("
                SELECT rec_id, rec_original_table, rec_original_id, rec_item_title, rec_deleted_by, rec_deleted_at
                FROM recycle_bin 
                WHERE rec_original_table = ?
                ORDER BY rec_deleted_at DESC
                LIMIT ? OFFSET ?
            ");
            $recycle_stmt->execute([$config['table_name'], $limit, $offset]);
            $recycle_items = $recycle_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($recycle_items as $item) {
                $items[] = [
                    'id' => $item['rec_id'],
                    'name' => $item['rec_item_title'],
                    'table' => $table_key,
                    'table_display_name' => $config['display_name'],
                    'deleted_at' => $item['rec_deleted_at'],
                    'deleted_by' => $item['rec_deleted_by'],
                    'item_id' => $item['rec_id'],
                    'item_name' => $item['rec_item_title'],
                    'table_key' => $table_key,
                    'item_type' => $config['display_name'],
                    'original_id' => $item['rec_original_id']
                ];
            }
            
            // Also get items with soft delete columns (for legacy support)
            $legacy_items = $this->getDeletedItemsByTableLegacy($table_key, $limit, $offset);
            $items = array_merge($items, $legacy_items);
            
        } catch (PDOException $e) {
            error_log("Error fetching deleted items from {$config['table_name']}: " . $e->getMessage());
        }
        
        return $items;
    }
    
    /**
     * Get deleted items using legacy soft delete columns
     */
    private function getDeletedItemsByTableLegacy(string $table_key, int $limit = 50, int $offset = 0): array {
        if (!isset($this->table_configs[$table_key])) {
            return [];
        }
        
        $config = $this->table_configs[$table_key];
        $items = [];
        
        try {
            // Check if the deleted_at column exists
            if (!$this->columnExists($config['table_name'], $config['deleted_at_column'])) {
                return [];
            }
            
            // Check if item exists in recycle_bin to avoid duplicates
            $sql = "SELECT *, ? as source_table, ? as table_display_name 
                    FROM {$config['table_name']} 
                    WHERE {$config['deleted_at_column']} IS NOT NULL 
                    AND {$config['id_column']} NOT IN (
                        SELECT rec_original_id FROM recycle_bin 
                        WHERE rec_original_table = ?
                    )
                    ORDER BY {$config['deleted_at_column']} DESC";
            
            if ($limit > 0) {
                $sql .= " LIMIT ? OFFSET ?";
            }
            
            $stmt = $this->pdo->prepare($sql);
            $table_key_param = $table_key;
            $display_name_param = $config['display_name'];
            $table_name_param = $config['table_name'];
            $stmt->bindParam(1, $table_key_param, PDO::PARAM_STR);
            $stmt->bindParam(2, $display_name_param, PDO::PARAM_STR);
            $stmt->bindParam(3, $table_name_param, PDO::PARAM_STR);
            
            if ($limit > 0) {
                $stmt->bindParam(4, $limit, PDO::PARAM_INT);
                $stmt->bindParam(5, $offset, PDO::PARAM_INT);
            }
            
            $stmt->execute();
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($results as $row) {
                $items[] = [
                    'id' => $row[$config['id_column']],
                    'name' => $row[$config['name_column']] ?? 'N/A',
                    'table' => $table_key,
                    'table_display_name' => $config['display_name'],
                    'deleted_at' => $row[$config['deleted_at_column']],
                    'deleted_by' => $row[$config['deleted_by_column']] ?? null,
                    'raw_data' => $row,
                    'item_id' => $row[$config['id_column']],
                    'item_name' => $row[$config['name_column']] ?? 'N/A',
                    'table_key' => $table_key,
                    'item_type' => $config['display_name'],
                    'original_id' => $row[$config['id_column']],
                    'is_legacy' => true
                ];
            }
            
        } catch (PDOException $e) {
            error_log("Error fetching legacy deleted items from {$config['table_name']}: " . $e->getMessage());
        }
        
        return $items;
    }
    
    /**
     * FIXED: Restore an item from recycle bin (handles both unified and legacy approaches correctly)
     */
    public function restoreItem(string $table_key, int $recycle_id): bool {
        if (!$this->isValidTable($table_key)) {
            throw new InvalidArgumentException("Invalid table key: {$table_key}");
        }

        $config = $this->table_configs[$table_key];
        
        try {
            $this->pdo->beginTransaction();
            
            // PRIORITY 1: Check if this is from recycle_bin table (unified approach)
            $recycle_stmt = $this->pdo->prepare("
                SELECT rec_original_id, rec_item_data, rec_original_table
                FROM recycle_bin 
                WHERE rec_id = ? AND rec_original_table = ?
            ");
            $recycle_stmt->execute([$recycle_id, $config['table_name']]);
            $recycle_data = $recycle_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($recycle_data) {
                // Restore from recycle_bin data
                $original_id = $recycle_data['rec_original_id'];
                $item_data = json_decode($recycle_data['rec_item_data'], true);
                
                if (!$item_data) {
                    throw new Exception("Invalid item data in recycle bin");
                }
                
                // For users, check if user still exists with soft delete
                if ($table_key === 'users') {
                    $check_stmt = $this->pdo->prepare("SELECT user_id FROM users WHERE user_id = ?");
                    $check_stmt->execute([$original_id]);
                    
                    if ($check_stmt->fetch()) {
                        // User exists, just restore soft delete columns
                        $restore_stmt = $this->pdo->prepare("UPDATE users SET user_deleted_at = NULL, user_deleted_by = NULL WHERE user_id = ?");
                        $restore_stmt->execute([$original_id]);
                    } else {
                        // User doesn't exist, recreate from backup data
                        // Remove any auto-generated columns that might cause conflicts
                        unset($item_data['user_deleted_at']);
                        unset($item_data['user_deleted_by']);
                        
                        $columns = array_keys($item_data);
                        $placeholders = array_fill(0, count($columns), '?');
                        $values = array_values($item_data);
                        
                        $insert_sql = "INSERT INTO users (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";
                        $insert_stmt = $this->pdo->prepare($insert_sql);
                        $insert_stmt->execute($values);
                    }
                } else {
                    // For other tables, recreate from backup data
                    // Remove any soft delete columns that might cause conflicts
                    unset($item_data[$config['deleted_at_column']]);
                    unset($item_data[$config['deleted_by_column']]);
                    
                    $columns = array_keys($item_data);
                    $placeholders = array_fill(0, count($columns), '?');
                    $values = array_values($item_data);
                    
                    $insert_sql = "INSERT INTO {$config['table_name']} (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";
                    $insert_stmt = $this->pdo->prepare($insert_sql);
                    $insert_stmt->execute($values);
                }
                
                // Remove from recycle bin
                $delete_stmt = $this->pdo->prepare("DELETE FROM recycle_bin WHERE rec_id = ?");
                $delete_stmt->execute([$recycle_id]);
                
                $this->pdo->commit();
                return true;
            }
            
            // PRIORITY 2: Check if it's a legacy soft delete (using recycle_id as actual user_id)
            // This handles the case where the recycle bin is calling with the user's actual ID
            if ($this->columnExists($config['table_name'], $config['deleted_at_column'])) {
                $checkSql = "SELECT COUNT(*) FROM {$config['table_name']} 
                            WHERE {$config['id_column']} = ? 
                            AND {$config['deleted_at_column']} IS NOT NULL";
                $checkStmt = $this->pdo->prepare($checkSql);
                $checkStmt->execute([$recycle_id]);
                
                if ($checkStmt->fetchColumn() > 0) {
                    // Restore the item using soft delete columns
                    $restoreSql = "UPDATE {$config['table_name']} 
                                  SET {$config['deleted_at_column']} = NULL, 
                                      {$config['deleted_by_column']} = NULL 
                                  WHERE {$config['id_column']} = ?";
                    $restoreStmt = $this->pdo->prepare($restoreSql);
                    $result = $restoreStmt->execute([$recycle_id]);
                    
                    if (!$result) {
                        throw new Exception("Failed to restore item using soft delete");
                    }
                    
                    $this->pdo->commit();
                    return true;
                }
            }
            
            // If we reach here, item was not found in either approach
            $this->pdo->rollBack();
            throw new Exception("Item not found in recycle bin");
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("Error restoring item (Table: {$table_key}, ID: {$recycle_id}): " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * FIXED: Permanently delete an item (handles both unified and legacy approaches correctly)
     */
    public function permanentlyDeleteItem(string $table_key, int $recycle_id): bool {
        if (!$this->isValidTable($table_key)) {
            throw new InvalidArgumentException("Invalid table key: {$table_key}");
        }

        $config = $this->table_configs[$table_key];
        
        try {
            $this->pdo->beginTransaction();
            
            // PRIORITY 1: Check if this is from recycle_bin table
            $recycle_stmt = $this->pdo->prepare("
                SELECT rec_original_id, rec_original_table
                FROM recycle_bin 
                WHERE rec_id = ? AND rec_original_table = ?
            ");
            $recycle_stmt->execute([$recycle_id, $config['table_name']]);
            $recycle_data = $recycle_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($recycle_data) {
                $original_id = $recycle_data['rec_original_id'];
                
                // Handle cascading deletes for specific tables
                if ($table_key === 'applicant') {
                    $this->permanentlyDeleteApplicantCascade($original_id);
                } elseif ($table_key === 'scholarship') {
                    $this->permanentlyDeleteScholarshipCascade($original_id);
                } else {
                    // For users and other tables, just delete from original table if exists
                    $deleteSql = "DELETE FROM {$config['table_name']} WHERE {$config['id_column']} = ?";
                    $deleteStmt = $this->pdo->prepare($deleteSql);
                    $deleteStmt->execute([$original_id]);
                }
                
                // Remove from recycle bin
                $delete_recycle_stmt = $this->pdo->prepare("DELETE FROM recycle_bin WHERE rec_id = ?");
                $delete_recycle_stmt->execute([$recycle_id]);
                
                $this->pdo->commit();
                return true;
            }
            
            // PRIORITY 2: Check if it's a legacy soft delete
            if ($this->columnExists($config['table_name'], $config['deleted_at_column'])) {
                $checkSql = "SELECT COUNT(*) FROM {$config['table_name']} 
                            WHERE {$config['id_column']} = ? 
                            AND {$config['deleted_at_column']} IS NOT NULL";
                $checkStmt = $this->pdo->prepare($checkSql);
                $checkStmt->execute([$recycle_id]);
                
                if ($checkStmt->fetchColumn() > 0) {
                    // Handle cascading deletes for specific tables
                    if ($table_key === 'applicant') {
                        $this->permanentlyDeleteApplicantCascade($recycle_id);
                    } elseif ($table_key === 'scholarship') {
                        $this->permanentlyDeleteScholarshipCascade($recycle_id);
                    } else {
                        // For users and other tables, just delete the record
                        $deleteSql = "DELETE FROM {$config['table_name']} WHERE {$config['id_column']} = ?";
                        $deleteStmt = $this->pdo->prepare($deleteSql);
                        $result = $deleteStmt->execute([$recycle_id]);
                        
                        if (!$result) {
                            throw new Exception("Failed to permanently delete item");
                        }
                    }
                    
                    $this->pdo->commit();
                    return true;
                }
            }
            
            // If we reach here, item was not found
            $this->pdo->rollBack();
            throw new Exception("Item not found in recycle bin");
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("Error permanently deleting item (Table: {$table_key}, ID: {$recycle_id}): " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Handle cascading delete for APPLICANT table
     */
    private function permanentlyDeleteApplicantCascade(int $app_code): void {
        // Delete in correct order to respect foreign key constraints
        $cascade_tables = [
            "DELETE FROM signature_scan WHERE app_code = ?",
            "DELETE FROM scholarship_status WHERE app_code = ?",
            "DELETE FROM school_preference WHERE app_code = ?",
            "DELETE FROM scholarship WHERE sib_code IN (SELECT sib_code FROM sibling WHERE app_code = ?)",
            "DELETE FROM sibling WHERE app_code = ?",
            "DELETE FROM school WHERE sch_code IN (SELECT sch_code FROM education_attainment WHERE app_code = ?)",
            "DELETE FROM education_attainment WHERE app_code = ?",
            "DELETE FROM family_background WHERE app_code = ?",
            "DELETE FROM address WHERE app_code = ?",
            "DELETE FROM applicant WHERE app_code = ?"
        ];
        
        foreach ($cascade_tables as $sql) {
            try {
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([$app_code]);
            } catch (PDOException $e) {
                // Some tables might not exist or have different structures, continue with others
                error_log("Error in cascade delete: " . $e->getMessage());
            }
        }
    }
    
    /**
     * Handle cascading delete for SCHOLARSHIP table
     */
    private function permanentlyDeleteScholarshipCascade(int $scholar_code): void {
        try {
            $sql = "DELETE FROM scholarship WHERE scholar_code = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$scholar_code]);
        } catch (PDOException $e) {
            error_log("Error in scholarship cascade delete: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Soft delete an item (move to recycle bin) - UNIFIED APPROACH FOR ALL TABLES
     */
    public function softDeleteItem(string $table_key, int $item_id, int $deleted_by_user_id): bool {
        if (!$this->isValidTable($table_key)) {
            throw new InvalidArgumentException("Invalid table key: {$table_key}");
        }

        $config = $this->table_configs[$table_key];
        
        try {
            $this->pdo->beginTransaction();
            
            // Get the item data before deletion
            $select_sql = "SELECT * FROM {$config['table_name']} WHERE {$config['id_column']} = ?";
            $select_stmt = $this->pdo->prepare($select_sql);
            $select_stmt->execute([$item_id]);
            $item_data = $select_stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$item_data) {
                $this->pdo->rollBack();
                return false;
            }
            
            // Create recycle bin entry for ALL tables (including users)
            $insert_stmt = $this->pdo->prepare("
                INSERT INTO recycle_bin (rec_original_table, rec_original_id, rec_item_data, rec_item_title, rec_deleted_by, rec_deleted_at) 
                VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
            ");
            
            $item_title = $this->generateItemTitle($table_key, $item_data);
            $insert_stmt->execute([
                $config['table_name'],
                $item_id,
                json_encode($item_data),
                $item_title,
                $deleted_by_user_id
            ]);
            
            // For users table, ONLY update soft delete columns (don't delete the record)
            if ($table_key === 'users') {
                // Ensure soft delete columns exist
                $this->ensureSoftDeleteColumns($config['table_name'], $config['deleted_at_column'], $config['deleted_by_column']);
                
                // Update soft delete columns
                $update_sql = "UPDATE {$config['table_name']} 
                              SET {$config['deleted_at_column']} = CURRENT_TIMESTAMP, 
                                  {$config['deleted_by_column']} = ? 
                              WHERE {$config['id_column']} = ?";
                $update_stmt = $this->pdo->prepare($update_sql);
                $update_stmt->execute([$deleted_by_user_id, $item_id]);
            } else {
                // For other tables, delete the record (it's backed up in recycle_bin)
                $delete_sql = "DELETE FROM {$config['table_name']} WHERE {$config['id_column']} = ?";
                $delete_stmt = $this->pdo->prepare($delete_sql);
                $delete_stmt->execute([$item_id]);
            }
            
            $this->pdo->commit();
            return true;
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("Error in softDeleteItem: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Ensure soft delete columns exist for a table
     */
    private function ensureSoftDeleteColumns(string $table_name, string $deleted_at_column, string $deleted_by_column): void {
        try {
            // Check and add deleted_at column
            if (!$this->columnExists($table_name, $deleted_at_column)) {
                $this->pdo->exec("ALTER TABLE {$table_name} ADD COLUMN {$deleted_at_column} TIMESTAMP NULL");
            }
            
            // Check and add deleted_by column
            if (!$this->columnExists($table_name, $deleted_by_column)) {
                $this->pdo->exec("ALTER TABLE {$table_name} ADD COLUMN {$deleted_by_column} INTEGER");
            }
            
            // Create index for better performance
            $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_{$table_name}_{$deleted_at_column} ON {$table_name}({$deleted_at_column})");
            
        } catch (PDOException $e) {
            error_log("Error ensuring soft delete columns for {$table_name}: " . $e->getMessage());
        }
    }
    
    /**
     * Empty entire recycle bin (unified approach)
     */
    public function emptyRecycleBin(): int {
        $deleted_count = 0;
        
        try {
            $this->pdo->beginTransaction();
            
            // First, permanently delete all items from recycle_bin
            $recycle_stmt = $this->pdo->prepare("
                SELECT rec_id, rec_original_table, rec_original_id
                FROM recycle_bin
            ");
            $recycle_stmt->execute();
            $recycle_items = $recycle_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($recycle_items as $item) {
                $table_map = [
                    'users' => 'users',
                    'population_table' => 'population_data',
                    'contact_message' => 'contact_messages',
                    'applicant' => 'applicant',
                    'announcement' => 'announcements',
                    'scholarship' => 'scholarship',
                    'scholarship_status' => 'scholarship_status'
                ];
                
                $table_key = $table_map[$item['rec_original_table']] ?? $item['rec_original_table'];
                
                try {
                    $this->permanentlyDeleteItem($table_key, $item['rec_id']);
                    $deleted_count++;
                } catch (Exception $e) {
                    error_log("Error deleting item from recycle bin: " . $e->getMessage());
                }
            }
            
            // Clear remaining items in recycle_bin table
            $this->pdo->exec("DELETE FROM recycle_bin");
            
            // Handle legacy soft delete items
            foreach ($this->table_configs as $table_key => $config) {
                if (!$this->columnExists($config['table_name'], $config['deleted_at_column'])) {
                    continue;
                }
                
                // Get count before deletion
                $countSql = "SELECT COUNT(*) FROM {$config['table_name']} 
                            WHERE {$config['deleted_at_column']} IS NOT NULL";
                $countStmt = $this->pdo->prepare($countSql);
                $countStmt->execute();
                $count = $countStmt->fetchColumn();
                
                if ($count > 0) {
                    if ($table_key === 'applicant') {
                        // Handle cascade delete for applicants
                        $itemsSql = "SELECT {$config['id_column']} FROM {$config['table_name']} 
                                    WHERE {$config['deleted_at_column']} IS NOT NULL";
                        $itemsStmt = $this->pdo->prepare($itemsSql);
                        $itemsStmt->execute();
                        $items = $itemsStmt->fetchAll(PDO::FETCH_COLUMN);
                        
                        foreach ($items as $item_id) {
                            $this->permanentlyDeleteApplicantCascade($item_id);
                        }
                    } else {
                        // Regular delete for other tables
                        $deleteSql = "DELETE FROM {$config['table_name']} 
                                     WHERE {$config['deleted_at_column']} IS NOT NULL";
                        $deleteStmt = $this->pdo->prepare($deleteSql);
                        $deleteStmt->execute();
                    }
                    
                    $deleted_count += $count;
                }
            }
            
            $this->pdo->commit();
            return $deleted_count;
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("Error emptying recycle bin: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Get recycle bin statistics
     */
    public function getRecycleBinStats(): array {
        $stats = [
            'total_items' => 0,
            'by_table' => [],
            'total_tables' => 0
        ];
        
        foreach ($this->table_configs as $table_key => $config) {
            $count = $this->getDeletedItemsCount($table_key);
            
            if ($count > 0) {
                $stats['by_table'][$table_key] = [
                    'display_name' => $config['display_name_plural'],
                    'count' => $count
                ];
                $stats['total_items'] += $count;
                $stats['total_tables']++;
            }
        }
        
        return $stats;
    }
    
    /**
     * Get count of deleted items by table (unified approach)
     */
    public function getDeletedItemsCount(string $table_key): int {
        if (!isset($this->table_configs[$table_key])) {
            return 0;
        }
        
        $config = $this->table_configs[$table_key];
        $total_count = 0;
        
        try {
            // Count items in recycle_bin table
            $recycle_stmt = $this->pdo->prepare("
                SELECT COUNT(*) FROM recycle_bin 
                WHERE rec_original_table = ?
            ");
            $recycle_stmt->execute([$config['table_name']]);
            $total_count += (int) $recycle_stmt->fetchColumn();
            
            // Count legacy soft delete items
            if ($this->columnExists($config['table_name'], $config['deleted_at_column'])) {
                $legacy_stmt = $this->pdo->prepare("
                    SELECT COUNT(*) FROM {$config['table_name']} 
                    WHERE {$config['deleted_at_column']} IS NOT NULL
                    AND {$config['id_column']} NOT IN (
                        SELECT rec_original_id FROM recycle_bin 
                        WHERE rec_original_table = ?
                    )
                ");
                $legacy_stmt->execute([$config['table_name']]);
                $total_count += (int) $legacy_stmt->fetchColumn();
            }
            
        } catch (PDOException $e) {
            error_log("Error counting deleted items in {$config['table_name']}: " . $e->getMessage());
        }
        
        return $total_count;
    }
    
    /**
     * Search deleted items (unified approach)
     */
    public function searchDeletedItems(string $search_term, ?string $table_filter = null): array {
        $items = [];
        $search_term_param = "%{$search_term}%";
        
        $tables_to_search = $table_filter && isset($this->table_configs[$table_filter]) 
            ? [$table_filter => $this->table_configs[$table_filter]]
            : $this->table_configs;
        
        try {
            // Search in recycle_bin table
            foreach ($tables_to_search as $table_key => $config) {
                $recycle_stmt = $this->pdo->prepare("
                    SELECT rec_id, rec_original_table, rec_original_id, rec_item_title, rec_deleted_by, rec_deleted_at
                    FROM recycle_bin 
                    WHERE rec_original_table = ? AND rec_item_title LIKE ?
                    ORDER BY rec_deleted_at DESC
                ");
                $recycle_stmt->execute([$config['table_name'], $search_term_param]);
                $recycle_results = $recycle_stmt->fetchAll(PDO::FETCH_ASSOC);
                
                foreach ($recycle_results as $row) {
                    $items[] = [
                        'id' => $row['rec_id'],
                        'name' => $row['rec_item_title'],
                        'table' => $table_key,
                        'table_display_name' => $config['display_name'],
                        'deleted_at' => $row['rec_deleted_at'],
                        'deleted_by' => $row['rec_deleted_by'],
                        'item_id' => $row['rec_id'],
                        'item_name' => $row['rec_item_title'],
                        'table_key' => $table_key,
                        'item_type' => $config['display_name'],
                        'original_id' => $row['rec_original_id']
                    ];
                }
                
                // Search legacy soft delete items
                if ($this->columnExists($config['table_name'], $config['deleted_at_column'])) {
                    $legacy_stmt = $this->pdo->prepare("
                        SELECT *, ? as source_table, ? as table_display_name 
                        FROM {$config['table_name']} 
                        WHERE {$config['deleted_at_column']} IS NOT NULL 
                        AND CAST({$config['name_column']} AS TEXT) LIKE ? 
                        AND {$config['id_column']} NOT IN (
                            SELECT rec_original_id FROM recycle_bin 
                            WHERE rec_original_table = ?
                        )
                        ORDER BY {$config['deleted_at_column']} DESC
                    ");
                    
                    $table_key_param = $table_key;
                    $display_name_param = $config['display_name'];
                    $table_name_param = $config['table_name'];
                    $legacy_stmt->execute([$table_key_param, $display_name_param, $search_term_param, $table_name_param]);
                    $legacy_results = $legacy_stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    foreach ($legacy_results as $row) {
                        $items[] = [
                            'id' => $row[$config['id_column']],
                            'name' => $row[$config['name_column']] ?? 'N/A',
                            'table' => $table_key,
                            'table_display_name' => $config['display_name'],
                            'deleted_at' => $row[$config['deleted_at_column']],
                            'deleted_by' => $row[$config['deleted_by_column']] ?? null,
                            'raw_data' => $row,
                            'item_id' => $row[$config['id_column']],
                            'item_name' => $row[$config['name_column']] ?? 'N/A',
                            'table_key' => $table_key,
                            'item_type' => $config['display_name'],
                            'original_id' => $row[$config['id_column']],
                            'is_legacy' => true
                        ];
                    }
                }
            }
            
        } catch (PDOException $e) {
            error_log("Error searching deleted items: " . $e->getMessage());
        }
        
        // Sort by deletion date (newest first)
        usort($items, function($a, $b) {
            return strtotime($b['deleted_at']) - strtotime($a['deleted_at']);
        });
        
        return $items;
    }
    
    /**
     * Check if a column exists in a table
     */
    private function columnExists(string $table_name, string $column_name): bool {
        try {
            $sql = "SELECT column_name FROM information_schema.columns 
                    WHERE table_name = ? AND column_name = ?";
            $stmt = $this->pdo->prepare($sql);
            $table_name_lower = strtolower($table_name);
            $column_name_lower = strtolower($column_name);
            $stmt->execute([$table_name_lower, $column_name_lower]);
            return $stmt->fetchColumn() !== false;
        } catch (PDOException $e) {
            error_log("Error checking column existence: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check if table key is valid
     */
    public function isValidTable(string $table_key): bool {
        return in_array($table_key, $this->allowed_tables) && isset($this->table_configs[$table_key]);
    }
    
    /**
     * Get table configuration
     */
    public function getTableConfig(string $table_key): ?array {
        return $this->table_configs[$table_key] ?? null;
    }
    
    /**
     * Get all table configurations
     */
    public function getAllTableConfigs(): array {
        return $this->table_configs;
    }
    
    /**
     * Get all available table types
     */
    public function getAvailableTableTypes(): array {
        $types = [];
        foreach ($this->table_configs as $key => $config) {
            $types[$key] = $config['display_name'];
        }
        return $types;
    }
    
    /**
     * Auto-cleanup old deleted items (older than specified days)
     */
    public function autoCleanup(int $days_old = 30): int {
        $deleted_count = 0;
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$days_old} days"));
        
        try {
            $this->pdo->beginTransaction();
            
            // Clean recycle_bin table
            $recycle_cleanup_stmt = $this->pdo->prepare("
                SELECT rec_id, rec_original_table 
                FROM recycle_bin 
                WHERE rec_deleted_at < ?
            ");
            $recycle_cleanup_stmt->execute([$cutoff_date]);
            $old_items = $recycle_cleanup_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($old_items as $item) {
                $table_map = [
                    'users' => 'users',
                    'population_table' => 'population_data',
                    'contact_message' => 'contact_messages',
                    'applicant' => 'applicant',
                    'announcement' => 'announcements',
                    'scholarship' => 'scholarship',
                    'scholarship_status' => 'scholarship_status'
                ];
                
                $table_key = $table_map[$item['rec_original_table']] ?? $item['rec_original_table'];
                
                try {
                    $this->permanentlyDeleteItem($table_key, $item['rec_id']);
                    $deleted_count++;
                } catch (Exception $e) {
                    error_log("Error in auto cleanup: " . $e->getMessage());
                }
            }
            
            // Clean legacy soft delete items
            foreach ($this->table_configs as $table_key => $config) {
                if (!$this->columnExists($config['table_name'], $config['deleted_at_column'])) {
                    continue;
                }
                
                $deleteSql = "DELETE FROM {$config['table_name']} 
                             WHERE {$config['deleted_at_column']} IS NOT NULL 
                             AND {$config['deleted_at_column']} < ?";
                $deleteStmt = $this->pdo->prepare($deleteSql);
                $deleteStmt->execute([$cutoff_date]);
                $deleted_count += $deleteStmt->rowCount();
            }
            
            $this->pdo->commit();
            return $deleted_count;
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("Error during auto-cleanup: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Restore multiple items at once (unified approach)
     */
    public function restoreMultipleItems(array $items): array {
        $results = [];
        
        foreach ($items as $item) {
            try {
                $this->restoreItem($item['table'], $item['id']);
                $results[] = [
                    'success' => true,
                    'table' => $item['table'],
                    'id' => $item['id']
                ];
            } catch (Exception $e) {
                $results[] = [
                    'success' => false,
                    'table' => $item['table'],
                    'id' => $item['id'],
                    'error' => $e->getMessage()
                ];
            }
        }
        
        return $results;
    }
    
    /**
     * Delete multiple items permanently at once (unified approach)
     */
    public function deleteMultipleItemsPermanently(array $items): array {
        $results = [];
        
        foreach ($items as $item) {
            try {
                $this->permanentlyDeleteItem($item['table'], $item['id']);
                $results[] = [
                    'success' => true,
                    'table' => $item['table'],
                    'id' => $item['id']
                ];
            } catch (Exception $e) {
                $results[] = [
                    'success' => false,
                    'table' => $item['table'],
                    'id' => $item['id'],
                    'error' => $e->getMessage()
                ];
            }
        }
        
        return $results;
    }
}
?>