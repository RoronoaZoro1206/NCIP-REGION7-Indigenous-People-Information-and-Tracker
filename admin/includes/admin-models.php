<?php
declare(strict_types=1);

function get_admin_by_username(object $pdo, string $username) {
    $query = "SELECT * FROM USERS WHERE USER_USERNAME = :username ORDER BY USER_ID ASC";
    $statement = $pdo->prepare($query);
    $statement->bindParam(":username", $username);
    $statement->execute();
    return $statement->fetch(PDO::FETCH_ASSOC);
}

function get_admin_by_email(object $pdo, string $email) {
    $query = "SELECT * FROM USERS WHERE USER_EMAIL = :email ORDER BY USER_ID ASC";
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

    $query = "INSERT INTO USERS (USER_USERNAME, USER_EMAIL, USER_PWD, USER_ROLE, USER_CREATED, USER_TIMEZONE) 
              VALUES (:username, :email, :password, 'ADMIN', :created, :timezone)";
    
    $statement = $pdo->prepare($query);

    $options = [
        'cost' => 12
    ];

    $hashed_password = password_hash($password, PASSWORD_BCRYPT, $options);

    $statement->bindParam(":username", $username);
    $statement->bindParam(":email", $email);
    $statement->bindParam(":password", $hashed_password);
    $statement->bindParam(":created", $created);
    $statement->bindParam(":timezone", $timezone_name);

    $statement->execute();
    return $pdo->lastInsertId();
}

function get_admin_by_token(object $pdo, string $token) {
    $query = "SELECT * FROM USERS WHERE USER_RESET_TOKEN = :token AND USER_ROLE = 'ADMIN' 
              AND USER_TOKEN_EXPIRY > NOW() ORDER BY USER_ID ASC";
    $statement = $pdo->prepare($query);
    $statement->bindParam(":token", $token);
    $statement->execute();
    return $statement->fetch(PDO::FETCH_ASSOC);
}

function update_admin_password(object $pdo, int $user_id, string $password) {
    $query = "UPDATE USERS SET USER_PWD = :password, USER_RESET_TOKEN = NULL, 
              USER_TOKEN_EXPIRY = NULL WHERE USER_ID = :user_id";
    
    $statement = $pdo->prepare($query);
    
    $options = [
        'cost' => 12
    ];
    
    $hashed_password = password_hash($password, PASSWORD_BCRYPT, $options);
    
    $statement->bindParam(":password", $hashed_password);
    $statement->bindParam(":user_id", $user_id);
    
    return $statement->execute();
}