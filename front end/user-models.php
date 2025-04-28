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

function create_user(object $pdo, string $username, string $email, string $password, string $timezone, string $user_created) {

    $query = "INSERT INTO users (user_username, user_email, user_pwd, user_timezone, user_created)
              VALUES (:username, :email, :pwd, :timezone, :created);";

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
