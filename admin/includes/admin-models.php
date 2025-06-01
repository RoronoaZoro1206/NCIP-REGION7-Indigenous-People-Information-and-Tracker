<?php


declare(strict_types=1);

function get_admin_by_username(object $pdo, string $username) {
    // First ensure the table has the required columns
    ensureUserSoftDeleteColumns($pdo);
    
    $query = "SELECT * FROM users WHERE LOWER(TRIM(user_username)) = LOWER(TRIM(:username)) 
              AND user_deleted_at IS NULL ORDER BY user_id ASC";
    $statement = $pdo->prepare($query);
    $statement->bindParam(":username", $username);
    $statement->execute();
    return $statement->fetch(PDO::FETCH_ASSOC);
}

function get_admin_by_email(object $pdo, string $email) {
    // First ensure the table has the required columns
    ensureUserSoftDeleteColumns($pdo);
    
    $query = "SELECT * FROM users WHERE LOWER(user_email) = LOWER(:email) 
              AND user_deleted_at IS NULL ORDER BY user_id ASC";
    $statement = $pdo->prepare($query);
    $statement->bindParam(":email", $email);
    $statement->execute();
    return $statement->fetch(PDO::FETCH_ASSOC);
}

function create_admin(object $pdo, string $username, string $email, string $password) {
    $timezone = new DateTimeZone('Asia/Manila');
    $now = new DateTime('now', $timezone);
    $created = $now->format('Y-m-d H:i:s');
    $timezone_name = $timezone->getName(); 

    // Clean the username by trimming and normalizing spaces
    $clean_username = preg_replace('/\s+/', ' ', trim($username));

    $query = "INSERT INTO users (user_username, user_email, user_pwd, user_role, user_created, user_timezone, user_profile_picture) 
              VALUES (:username, :email, :password, 'ADMIN', :created, :timezone, 'default.png')";
    
    $statement = $pdo->prepare($query);

    $options = [
        'cost' => 12
    ];

    $hashed_password = password_hash($password, PASSWORD_BCRYPT, $options);

    $statement->bindParam(":username", $clean_username);
    $statement->bindParam(":email", $email);
    $statement->bindParam(":password", $hashed_password);
    $statement->bindParam(":created", $created);
    $statement->bindParam(":timezone", $timezone_name);

    $statement->execute();
    return $pdo->lastInsertId();
}

function get_admin_by_token(object $pdo, string $token) {
    // First ensure the table has the required columns
    ensureUserSoftDeleteColumns($pdo);
    
    $query = "SELECT * FROM users WHERE user_reset_token = :token AND user_role = 'ADMIN' 
              AND user_token_expiry > NOW() AND user_deleted_at IS NULL ORDER BY user_id ASC";
    $statement = $pdo->prepare($query);
    $statement->bindParam(":token", $token);
    $statement->execute();
    return $statement->fetch(PDO::FETCH_ASSOC);
}

function update_admin_password(object $pdo, int $user_id, string $password) {
    // First ensure the table has the required columns
    ensureUserSoftDeleteColumns($pdo);
    
    $query = "UPDATE users SET user_pwd = :password, user_reset_token = NULL, 
              user_token_expiry = NULL WHERE user_id = :user_id AND user_deleted_at IS NULL";
    
    $statement = $pdo->prepare($query);
    
    $options = [
        'cost' => 12
    ];
    
    $hashed_password = password_hash($password, PASSWORD_BCRYPT, $options);
    
    $statement->bindParam(":password", $hashed_password);
    $statement->bindParam(":user_id", $user_id);
    
    return $statement->execute();
}

function username_exists(object $pdo, string $username): bool {
    // First ensure the table has the required columns
    ensureUserSoftDeleteColumns($pdo);
    
    $query = "SELECT COUNT(*) FROM users WHERE LOWER(TRIM(user_username)) = LOWER(TRIM(:username)) 
              AND user_deleted_at IS NULL";
    $statement = $pdo->prepare($query);
    $statement->bindParam(":username", $username);
    $statement->execute();
    return $statement->fetchColumn() > 0;
}

function email_exists(object $pdo, string $email): bool {
    // First ensure the table has the required columns
    ensureUserSoftDeleteColumns($pdo);
    
    $query = "SELECT COUNT(*) FROM users WHERE LOWER(user_email) = LOWER(:email) 
              AND user_deleted_at IS NULL";
    $statement = $pdo->prepare($query);
    $statement->bindParam(":email", $email);
    $statement->execute();
    return $statement->fetchColumn() > 0;
}

// Function to check if account is deleted
function is_account_deleted(object $pdo, string $username): bool {
    // First ensure the table has the required columns
    ensureUserSoftDeleteColumns($pdo);
    
    try {
        $query = "SELECT user_deleted_at FROM users WHERE LOWER(TRIM(user_username)) = LOWER(TRIM(:username))";
        $statement = $pdo->prepare($query);
        $statement->bindParam(":username", $username);
        $statement->execute();
        $result = $statement->fetch(PDO::FETCH_ASSOC);
        
        if (!$result) {
            return false; // User doesn't exist
        }
        
        // Check if user_deleted_at exists in result and is not null
        return isset($result['user_deleted_at']) && !is_null($result['user_deleted_at']);
    } catch (PDOException $e) {
        error_log("Error checking if account is deleted: " . $e->getMessage());
        return false;
    }
}

// Function to get deleted account info
function get_deleted_account_info(object $pdo, string $username): ?array {
    // First ensure the table has the required columns
    ensureUserSoftDeleteColumns($pdo);
    
    try {
        $query = "SELECT u.user_username, u.user_deleted_at, u.user_deleted_by, 
                         deleter.user_username as deleted_by_username
                  FROM users u
                  LEFT JOIN users deleter ON u.user_deleted_by = deleter.user_id
                  WHERE LOWER(TRIM(u.user_username)) = LOWER(TRIM(:username)) 
                  AND u.user_deleted_at IS NOT NULL";
        $statement = $pdo->prepare($query);
        $statement->bindParam(":username", $username);
        $statement->execute();
        $result = $statement->fetch(PDO::FETCH_ASSOC);
        
        return $result ?: null;
    } catch (PDOException $e) {
        error_log("Error getting deleted account info: " . $e->getMessage());
        return null;
    }
}

// Function to ensure soft delete columns exist
function ensureUserSoftDeleteColumns(object $pdo): void {
    try {
        // Check if user_deleted_at column exists (using lowercase table name)
        $checkQuery = "SELECT column_name FROM information_schema.columns 
                      WHERE table_name = 'users' AND column_name IN ('user_deleted_at', 'user_deleted_by')";
        $stmt = $pdo->query($checkQuery);
        $existingColumns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // The columns should already exist based on your SQL schema
        if (count($existingColumns) < 2) {
            error_log("Warning: user_deleted_at or user_deleted_by columns missing from users table");
        }
    } catch (PDOException $e) {
        error_log("Error checking soft delete columns: " . $e->getMessage());
    }
}

// Function to check if soft delete columns exist
function hasSoftDeleteColumns(object $pdo): bool {
    try {
        $checkQuery = "SELECT column_name FROM information_schema.columns 
                      WHERE table_name = 'users' AND column_name IN ('user_deleted_at', 'user_deleted_by')";
        $stmt = $pdo->query($checkQuery);
        $existingColumns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        return count($existingColumns) >= 2;
    } catch (PDOException $e) {
        return false;
    }
}

// Function to soft delete a user
function soft_delete_user(object $pdo, int $user_id, int $deleted_by_id): bool {
    ensureUserSoftDeleteColumns($pdo);
    
    try {
        $query = "UPDATE users SET user_deleted_at = CURRENT_TIMESTAMP, user_deleted_by = :deleted_by 
                  WHERE user_id = :user_id AND user_deleted_at IS NULL";
        $statement = $pdo->prepare($query);
        $statement->bindParam(":user_id", $user_id);
        $statement->bindParam(":deleted_by", $deleted_by_id);
        return $statement->execute();
    } catch (PDOException $e) {
        error_log("Error soft deleting user: " . $e->getMessage());
        return false;
    }
}

// Function to restore a user from soft delete
function restore_user(object $pdo, int $user_id): bool {
    ensureUserSoftDeleteColumns($pdo);
    
    try {
        $query = "UPDATE users SET user_deleted_at = NULL, user_deleted_by = NULL 
                  WHERE user_id = :user_id";
        $statement = $pdo->prepare($query);
        $statement->bindParam(":user_id", $user_id);
        return $statement->execute();
    } catch (PDOException $e) {
        error_log("Error restoring user: " . $e->getMessage());
        return false;
    }
}

// Function to permanently delete a user
function permanent_delete_user(object $pdo, int $user_id): bool {
    try {
        $query = "DELETE FROM users WHERE user_id = :user_id";
        $statement = $pdo->prepare($query);
        $statement->bindParam(":user_id", $user_id);
        return $statement->execute();
    } catch (PDOException $e) {
        error_log("Error permanently deleting user: " . $e->getMessage());
        return false;
    }
}

// Function to get user by ID (including deleted users)
function get_user_by_id(object $pdo, int $user_id, bool $include_deleted = false): ?array {
    ensureUserSoftDeleteColumns($pdo);
    
    try {
        if ($include_deleted) {
            $query = "SELECT * FROM users WHERE user_id = :user_id ORDER BY user_id ASC";
        } else {
            $query = "SELECT * FROM users WHERE user_id = :user_id 
                      AND user_deleted_at IS NULL ORDER BY user_id ASC";
        }
        
        $statement = $pdo->prepare($query);
        $statement->bindParam(":user_id", $user_id);
        $statement->execute();
        $result = $statement->fetch(PDO::FETCH_ASSOC);
        
        return $result ?: null;
    } catch (PDOException $e) {
        error_log("Error getting user by ID: " . $e->getMessage());
        return null;
    }
}

// Function to get all active admins
function get_all_active_admins(object $pdo): array {
    ensureUserSoftDeleteColumns($pdo);
    
    try {
        $query = "SELECT * FROM users WHERE user_role = 'ADMIN' 
                  AND user_deleted_at IS NULL 
                  ORDER BY user_created DESC";
        $statement = $pdo->prepare($query);
        $statement->execute();
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting active admins: " . $e->getMessage());
        return [];
    }
}

// Function to get all deleted users
function get_all_deleted_users(object $pdo): array {
    ensureUserSoftDeleteColumns($pdo);
    
    try {
        $query = "SELECT u.*, deleter.user_username as deleted_by_username 
                  FROM users u
                  LEFT JOIN users deleter ON u.user_deleted_by = deleter.user_id
                  WHERE u.user_deleted_at IS NOT NULL
                  ORDER BY u.user_deleted_at DESC";
        $statement = $pdo->prepare($query);
        $statement->execute();
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting deleted users: " . $e->getMessage());
        return [];
    }
}

// Function to check if user can be deleted (prevent self-deletion)
function can_delete_user(int $target_user_id, int $current_user_id): bool {
    return $target_user_id !== $current_user_id;
}

// Function to log user activity
function log_user_activity(object $pdo, int $user_id, string $log_type, string $log_details, ?string $ip_address = null): bool {
    try {
        $query = "INSERT INTO user_logs (user_id, log_type, log_details, log_timestamp, log_ip_address) 
                  VALUES (:user_id, :log_type, :log_details, CURRENT_TIMESTAMP, :ip_address)";
        $statement = $pdo->prepare($query);
        $statement->bindParam(":user_id", $user_id);
        $statement->bindParam(":log_type", $log_type);
        $statement->bindParam(":log_details", $log_details);
        $statement->bindParam(":ip_address", $ip_address);
        return $statement->execute();
    } catch (PDOException $e) {
        error_log("Error logging user activity: " . $e->getMessage());
        return false;
    }
}