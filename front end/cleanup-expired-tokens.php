<?php
require 'db.php';

$dt = new DateTime("now", new DateTimeZone("Asia/Manila"));
$currentTime = $dt->format("Y-m-d H:i:s") . " UTC+08:00 (Philippine Time)";
$currentTimeSQL = $dt->format("Y-m-d H:i:s");  
$cleared = 0;
$error = null;

try {
$stmt = $pdo->prepare("
    UPDATE users 
    SET user_reset_token = NULL, user_token_expiry = NULL 
    WHERE user_token_expiry IS NOT NULL AND user_token_expiry < :now
");

$stmt->execute(['now' => $currentTimeSQL]);

$cleared = $stmt->rowCount(); 


$logMessage = "[" . $currentTime . "] Cleaned $cleared expired token(s)\n";
file_put_contents(__DIR__ . '/cleanup-log.txt', $logMessage, FILE_APPEND);

} catch (PDOException $e) {
  $error = $e->getMessage();
  $errorLog = "[" . date('Y-m-d H:i:s') . "] ERROR: " . $error . "\n";
  file_put_contents(__DIR__ . '/cleanup-log.txt', $errorLog, FILE_APPEND);
}

return ['cleared' => $cleared, 'error' => $error, 'currentTime' => $currentTime];

?>
