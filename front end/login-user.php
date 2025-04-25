<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {


    $username = $_POST["username"] ?? null;
    $password = $_POST["password"] ?? null;

    if (!$username || !$password) {
        $_SESSION["error_message"] = "Username and Password are required.";
        header("Location: form-content.php?show=login");
        exit();
    }

    try {

        require_once 'db.php';
        require_once 'user-models.php';
        require_once 'validator.php';

        $validate_user = new LoginValidator($username, $password, $pdo);
        $validate_user->validate_data();

        $_SESSION["user"] = $validate_user->get_user_data();

        header("Location: e-service.php");
        die();
        
    } catch (PDOException $e) {
        die("Sql query failed: " . $e->getMessage());
    }

} else {
    header("Location: form-content.php?show=login");
    die();
}
