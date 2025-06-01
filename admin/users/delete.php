<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$is_admin = isset($_SESSION['user_role']) && strtoupper(trim($_SESSION['user_role'])) === 'ADMIN';
if (!$is_admin) {
    $_SESSION['message'] = "You don't have permission to access this page.";
    header("Location: ../index.php");
    exit();
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['message'] = "Invalid user ID.";
    header("Location: index.php");
    exit();
}

$user_id = (int) $_GET['id'];
$user = [];

try {
    require_once '../config/database.php';

    $stmt = $pdo->prepare("SELECT USER_USERNAME FROM USERS WHERE USER_ID = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        $_SESSION['message'] = "User not found.";
        header("Location: index.php");
        exit();
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $stmt = $pdo->query("SELECT MAX(USER_ID) AS max_id FROM USERS");
        $max_id_row = $stmt->fetch(PDO::FETCH_ASSOC);
        $max_id = $max_id_row ? (int)$max_id_row['max_id'] : 0;

        $stmt = $pdo->prepare("DELETE FROM USERS WHERE USER_ID = ?");
        $stmt->execute([$user_id]);

        if ($user_id === $max_id) {
            $stmt = $pdo->query("SELECT MAX(USER_ID) AS max_id FROM USERS");
            $new_max_id_row = $stmt->fetch(PDO::FETCH_ASSOC);
            $new_max_id = $new_max_id_row && $new_max_id_row['max_id'] !== null ? (int)$new_max_id_row['max_id'] : 0;
            $next_id = $new_max_id + 1;
            $pdo->exec("ALTER SEQUENCE users_user_id_seq RESTART WITH $next_id");
        }

        $_SESSION['message'] = "User deleted successfully.";
        header("Location: index.php");
        exit();
    }
} catch (PDOException $e) {
    $_SESSION['message'] = "Database error: " . $e->getMessage();
    header("Location: index.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Delete User - NCIP Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container py-5">
        <div class="alert alert-danger text-center">
            <h4>Are you sure?</h4>
            <p>Do you really want to delete user <strong><?= htmlspecialchars($user['user_username']) ?></strong>?<br>This process cannot be undone.</p>
            <form method="post">
                <button type="submit" class="btn btn-danger">
                    <i class="bi bi-trash"></i> Delete
                </button>
                <a href="index.php" class="btn btn-secondary">
                    <i class="bi bi-x"></i> Cancel
                </a>
            </form>
        </div>
    </div>
</body>
</html>