<?php

declare(strict_types=1);

function get_user_username(object $pdo, string $username) {
    $query = "SELECT * FROM users WHERE user_username = :username ORDER BY user_id ASC";
    $statement = $pdo->prepare($query);

    $statement->bindParam(":username", $username);
    $statement->execute();

    $result = $statement->fetch(PDO::FETCH_ASSOC); 
    
    return $result;
}

function get_user_email(object $pdo, string $email) {
    $query = "SELECT * FROM users WHERE user_email = :email ORDER BY user_id ASC";
    $statement = $pdo->prepare($query);

    $statement->bindParam(":email", $email);
    $statement->execute();

    $result = $statement->fetch(PDO::FETCH_ASSOC); 
    
    return $result;
}

function create_user(object $pdo, string $username, string $email, string $password, string $timezone, string $user_created, string $role = 'USER') {
    $query = "INSERT INTO users (user_username, user_email, user_pwd, user_timezone, user_created, user_role)
              VALUES (:username, :email, :pwd, :timezone, :created, :role);";

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
    } catch (PDOException $e) {
        die("Failed to insert user: " . $e->getMessage());
    }
}

function getUserByEmail($pdo, $email) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE user_email = ? ORDER BY user_id ASC");
    $stmt->execute([$email]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function saveResetToken($pdo, $email, $token, $expiry) {
    $stmt = $pdo->prepare("UPDATE users SET user_reset_token = ?, user_token_expiry = ? WHERE user_email = ?");
    return $stmt->execute([$token, $expiry, $email]);
}

function getUserByToken($pdo, $token) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE user_reset_token = ? ORDER BY user_id ASC");
    $stmt->execute([$token]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function updateUserPassword($pdo, $userId, $hashedPassword) {
    $stmt = $pdo->prepare("UPDATE users SET user_pwd = ?, user_reset_token = NULL, user_token_expiry = NULL WHERE user_id = ?");
    return $stmt->execute([$hashedPassword, $userId]);
}

// New helper functions for role management

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
 * Get all users with specified role
 * 
 * @param object $pdo PDO database connection
 * @param string $role Role to filter by (default: all roles)
 * @return array Array of user records
 */
function get_users_by_role($pdo, $role = null) {
    if ($role) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE user_role = ? ORDER BY user_id ASC");
        $stmt->execute([$role]);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users ORDER BY user_id ASC");
        $stmt->execute();
    }
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Update a user's role
 * 
 * @param object $pdo PDO database connection
 * @param int $userId User ID
 * @param string $role New role for the user
 * @return bool Success status
 */
function update_user_role($pdo, $userId, $role) {
    $stmt = $pdo->prepare("UPDATE users SET user_role = ? WHERE user_id = ?");
    return $stmt->execute([$role, $userId]);
}