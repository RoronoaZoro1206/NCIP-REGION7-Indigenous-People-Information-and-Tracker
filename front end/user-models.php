<?php

declare(strict_types=1);

function get_user_username(object $pdo, string $username) {
    $query = "SELECT * FROM users WHERE user_username = :username AND user_deleted_at IS NULL ORDER BY user_id ASC";
    $statement = $pdo->prepare($query);

    $statement->bindParam(":username", $username);
    $statement->execute();

    $result = $statement->fetch(PDO::FETCH_ASSOC); 
    
    return $result;
}

function get_user_email(object $pdo, string $email) {
    $query = "SELECT * FROM users WHERE user_email = :email AND user_deleted_at IS NULL ORDER BY user_id ASC";
    $statement = $pdo->prepare($query);

    $statement->bindParam(":email", $email);
    $statement->execute();

    $result = $statement->fetch(PDO::FETCH_ASSOC); 
    
    return $result;
}

function create_user(object $pdo, string $username, string $email, string $password, string $timezone, string $user_created, string $role = 'USER') {
    $query = "INSERT INTO users (user_username, user_email, user_pwd, user_timezone, user_created, user_role)
              VALUES (:username, :email, :pwd, :timezone, :created, :role) RETURNING user_id;";

    $statement = $pdo->prepare($query);

    $options = [
        'cost' => 12
    ];

    $hashed_password = password_hash($password, PASSWORD_BCRYPT, $options);

    $statement->bindParam(":username", $username);
    $statement->bindParam(":email", $email);
    $statement->bindParam(":pwd", $hashed_password);
    $statement->bindParam(":timezone", $timezone);
    $statement->bindParam(":created", $user_created);
    $statement->bindParam(":role", $role);

    try {
        $statement->execute();
        $result = $statement->fetch(PDO::FETCH_ASSOC);
        return $result['user_id'];
    } catch (PDOException $e) {
        error_log("Failed to create user: " . $e->getMessage());
        throw new Exception("Failed to create user account. Please try again.");
    }
}

function getUserByEmail($pdo, $email) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE user_email = ? AND user_deleted_at IS NULL ORDER BY user_id ASC");
    $stmt->execute([$email]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getUserById($pdo, $userId) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ? AND user_deleted_at IS NULL");
    $stmt->execute([$userId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function saveResetToken($pdo, $email, $token, $expiry) {
    $stmt = $pdo->prepare("UPDATE users SET user_reset_token = ?, user_token_expiry = ? WHERE user_email = ? AND user_deleted_at IS NULL");
    return $stmt->execute([$token, $expiry, $email]);
}

function getUserByToken($pdo, $token) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE user_reset_token = ? AND user_deleted_at IS NULL ORDER BY user_id ASC");
    $stmt->execute([$token]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function updateUserPassword($pdo, $userId, $hashedPassword) {
    $stmt = $pdo->prepare("UPDATE users SET user_pwd = ?, user_reset_token = NULL, user_token_expiry = NULL WHERE user_id = ? AND user_deleted_at IS NULL");
    return $stmt->execute([$hashedPassword, $userId]);
}

function updateUserProfile($pdo, $userId, $username, $email, $timezone) {
    $stmt = $pdo->prepare("UPDATE users SET user_username = ?, user_email = ?, user_timezone = ? WHERE user_id = ? AND user_deleted_at IS NULL");
    return $stmt->execute([$username, $email, $timezone, $userId]);
}

function updateUserProfilePicture($pdo, $userId, $profilePicture) {
    $stmt = $pdo->prepare("UPDATE users SET user_profile_picture = ? WHERE user_id = ? AND user_deleted_at IS NULL");
    return $stmt->execute([$profilePicture, $userId]);
}

function checkUsernameExists($pdo, $username, $excludeUserId = null) {
    if ($excludeUserId) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE user_username = ? AND user_id != ? AND user_deleted_at IS NULL");
        $stmt->execute([$username, $excludeUserId]);
    } else {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE user_username = ? AND user_deleted_at IS NULL");
        $stmt->execute([$username]);
    }
    return $stmt->fetchColumn() > 0;
}

function checkEmailExists($pdo, $email, $excludeUserId = null) {
    if ($excludeUserId) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE user_email = ? AND user_id != ? AND user_deleted_at IS NULL");
        $stmt->execute([$email, $excludeUserId]);
    } else {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE user_email = ? AND user_deleted_at IS NULL");
        $stmt->execute([$email]);
    }
    return $stmt->fetchColumn() > 0;
}

function verifyUserPassword($pdo, $userId, $password) {
    $stmt = $pdo->prepare("SELECT user_pwd FROM users WHERE user_id = ? AND user_deleted_at IS NULL");
    $stmt->execute([$userId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result) {
        return password_verify($password, $result['user_pwd']);
    }
    return false;
}

function getUserStats($pdo) {
    $stats = [];
    
    // Total active users
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE user_deleted_at IS NULL");
    $stmt->execute();
    $stats['total_users'] = $stmt->fetchColumn();
    
    // Users by role
    $stmt = $pdo->prepare("SELECT user_role, COUNT(*) as count FROM users WHERE user_deleted_at IS NULL GROUP BY user_role");
    $stmt->execute();
    $stats['by_role'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Recent registrations (last 30 days)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE user_created >= NOW() - INTERVAL '30 days' AND user_deleted_at IS NULL");
    $stmt->execute();
    $stats['recent_registrations'] = $stmt->fetchColumn();
    
    return $stats;
}

function logUserActivity($pdo, $userId, $logType, $logDetails, $ipAddress = null) {
    $stmt = $pdo->prepare("INSERT INTO user_logs (user_id, log_type, log_details, log_ip_address) VALUES (?, ?, ?, ?)");
    return $stmt->execute([$userId, $logType, $logDetails, $ipAddress]);
}

function getUserActivityLogs($pdo, $userId, $limit = 10) {
    $stmt = $pdo->prepare("SELECT * FROM user_logs WHERE user_id = ? ORDER BY log_timestamp DESC LIMIT ?");
    $stmt->execute([$userId, $limit]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getRecentUserActivity($pdo, $limit = 20) {
    $stmt = $pdo->prepare("
        SELECT ul.*, u.user_username, u.user_email 
        FROM user_logs ul 
        LEFT JOIN users u ON ul.user_id = u.user_id 
        WHERE u.user_deleted_at IS NULL OR ul.user_id IS NULL
        ORDER BY ul.log_timestamp DESC 
        LIMIT ?
    ");
    $stmt->execute([$limit]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function clearUserResetToken($pdo, $userId) {
    $stmt = $pdo->prepare("UPDATE users SET user_reset_token = NULL, user_token_expiry = NULL WHERE user_id = ? AND user_deleted_at IS NULL");
    return $stmt->execute([$userId]);
}

function getUsersByTimezone($pdo, $timezone) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE user_timezone = ? AND user_deleted_at IS NULL ORDER BY user_created DESC");
    $stmt->execute([$timezone]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function updateUserLastLogin($pdo, $userId, $ipAddress = null) {
    // This would require adding a last_login column to your users table
    // For now, we'll just log the login activity
    return logUserActivity($pdo, $userId, 'LOGIN', 'User logged in successfully', $ipAddress);
}

// Role management functions for USER side (no admin creation)

/**
 * Check if a user is an admin
 * 
 * @param array|null $user User data array from database
 * @return bool True if user is admin, false otherwise
 */
function is_admin($user) {
    return isset($user) && isset($user['user_role']) && strtoupper($user['user_role']) === 'ADMIN';
}

/**
 * Check if a user is a regular user
 * 
 * @param array|null $user User data array from database
 * @return bool True if user is regular user, false otherwise
 */
function is_user($user) {
    return isset($user) && isset($user['user_role']) && strtoupper($user['user_role']) === 'USER';
}

/**
 * Get all active users (excluding soft-deleted ones)
 * 
 * @param object $pdo PDO database connection
 * @param string $role Role to filter by (optional)
 * @return array Array of user records
 */
function get_users_by_role($pdo, $role = null) {
    if ($role) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE user_role = ? AND user_deleted_at IS NULL ORDER BY user_created DESC");
        $stmt->execute([$role]);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE user_deleted_at IS NULL ORDER BY user_created DESC");
        $stmt->execute();
    }
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get all regular users (front end should only see regular users)
 * 
 * @param object $pdo PDO database connection
 * @return array Array of regular user records
 */
function get_regular_users($pdo) {
    return get_users_by_role($pdo, 'USER');
}

/**
 * Search users by username or email (front end function)
 * 
 * @param object $pdo PDO database connection
 * @param string $searchTerm Search term for username or email
 * @return array Array of matching user records
 */
function search_users($pdo, $searchTerm) {
    $searchTerm = '%' . $searchTerm . '%';
    $stmt = $pdo->prepare("
        SELECT * FROM users 
        WHERE (user_username ILIKE ? OR user_email ILIKE ?) 
        AND user_deleted_at IS NULL 
        AND user_role = 'USER'
        ORDER BY user_created DESC
    ");
    $stmt->execute([$searchTerm, $searchTerm]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Validate user session and return user data
 * 
 * @param object $pdo PDO database connection
 * @param int $userId User ID from session
 * @return array|false User data if valid, false otherwise
 */
function validate_user_session($pdo, $userId) {
    if (!$userId) {
        return false;
    }
    
    $user = getUserById($pdo, $userId);
    if (!$user) {
        return false;
    }
    
    return $user;
}

/**
 * Check if user account is active (not soft deleted)
 * 
 * @param array $user User data array
 * @return bool True if active, false if soft deleted
 */
function is_user_active($user) {
    return isset($user) && is_null($user['user_deleted_at']);
}

/**
 * Get user count by registration date range
 * 
 * @param object $pdo PDO database connection
 * @param string $startDate Start date (YYYY-MM-DD)
 * @param string $endDate End date (YYYY-MM-DD)
 * @return int Count of users registered in date range
 */
function getUserCountByDateRange($pdo, $startDate, $endDate) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM users 
        WHERE user_created::date BETWEEN ? AND ? 
        AND user_deleted_at IS NULL 
        AND user_role = 'USER'
    ");
    $stmt->execute([$startDate, $endDate]);
    return $stmt->fetchColumn();
}

/**
 * Get users with expired reset tokens
 * 
 * @param object $pdo PDO database connection
 * @return array Array of users with expired tokens
 */
function getUsersWithExpiredTokens($pdo) {
    $stmt = $pdo->prepare("
        SELECT * FROM users 
        WHERE user_token_expiry < NOW() 
        AND user_reset_token IS NOT NULL 
        AND user_deleted_at IS NULL
    ");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Clean up expired reset tokens
 * 
 * @param object $pdo PDO database connection
 * @return int Number of tokens cleared
 */
function cleanupExpiredTokens($pdo) {
    $stmt = $pdo->prepare("
        UPDATE users 
        SET user_reset_token = NULL, user_token_expiry = NULL 
        WHERE user_token_expiry < NOW() 
        AND user_reset_token IS NOT NULL 
        AND user_deleted_at IS NULL
    ");
    $stmt->execute();
    return $stmt->rowCount();
}