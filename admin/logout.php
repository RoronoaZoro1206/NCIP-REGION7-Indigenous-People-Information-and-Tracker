<?php
session_start();

// Include DB and unified logger (safe if missing)
try { require_once __DIR__ . '/config/database.php'; } catch (Throwable $e) { /* ignore */ }
try { require_once __DIR__ . '/../front end/logger.php'; } catch (Throwable $e) { /* ignore */ }

// Capture data needed for logging BEFORE destroying the session
$userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

// Best-effort client IP detection
$client_ip = 'Unknown';
if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
    $client_ip = $_SERVER['HTTP_CLIENT_IP'];
} elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
    // could be a list like "ip1, ip2" — take the first
    $parts = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
    $client_ip = trim($parts[0]);
} elseif (!empty($_SERVER['REMOTE_ADDR'])) {
    $client_ip = $_SERVER['REMOTE_ADDR'];
}

// Write a single LOGOUT entry with IP
try {
    $logged = false;
    if (function_exists('log_user_event')) {
        log_user_event(isset($pdo) ? $pdo : null, 'LOGOUT', 'Admin logged out', $userId, $client_ip);
        $logged = true;
    }
    if (!$logged && isset($pdo) && $pdo instanceof PDO) {
        $stmt = $pdo->prepare("INSERT INTO user_logs (user_id, log_type, log_details, log_timestamp, log_ip_address) VALUES (:user_id, 'LOGOUT', 'Admin logged out', CURRENT_TIMESTAMP, :ip)");
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':ip', $client_ip, PDO::PARAM_STR);
        $stmt->execute();
    }
} catch (Throwable $e) {
    // Swallow logging errors to not block logout
}

// Now clear the session completely
$_SESSION = array();
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 42000, '/');
}
session_destroy();

// Redirect to the front-end landing page as a normal viewer (no admin session)
header('Location: ../front%20end/index.php');
exit();
?>