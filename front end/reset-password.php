<?php

require_once 'db.php';

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

$token = $_GET['token'] ?? '';
$cleanup = ['cleared' => 0, 'currentTime' => 'unknown'];

$logFile = __DIR__ . '/cleanup-log.txt';
if (file_exists($logFile)) {
    $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $lines = array_reverse($lines);
    foreach ($lines as $line) {
        if (strpos($line, 'Cleaned') !== false && 
        preg_match('/\[(.*?)\]\s+Cleaned\s+(\d+)\s+expired token\(s\)/', $line, $matches)
        && intval($matches[2]) > 0) {
            $cleanup['currentTime'] = $matches[1];
            $cleanup['cleared'] = $matches[2];
            break;
        }
    }
}

function showTokenErrorPage($message, $errorCode = 'expired_token') {
  global $cleanup;

  header("Refresh: 10; URL=forgot-password.php?error=" . urlencode($errorCode));
  header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
  header("Pragma: no-cache");

  echo '
  <!DOCTYPE html>
  <html lang="en">
  <head>
      <meta charset="UTF-8">
      <title>' . htmlspecialchars($message) . '</title>
      <link rel="stylesheet" href="authentication.css">
      <meta http-equiv="refresh" content="10;url=forgot-password.php?error=' . urlencode($errorCode) . '">
      <style>
          body {
              background: linear-gradient(135deg, #2980b9, #8e44ad, #2c3e50);
              display: flex;
              align-items: center;
              justify-content: center;
              height: 100vh;
              margin: 0;
              font-family: "Poppins", sans-serif;
          }
      </style>
      
  </head>
  <body>
      <div class="message-box">
          <div class="alert-icon">⚠️</div>
          <div class="alert-title">' . htmlspecialchars($message) . '</div>
          <div class="success-message">
            ✅ <strong>' . $cleanup['cleared'] . '</strong> expired reset token(s) were successfully cleared at <strong>' . $cleanup['currentTime'] . '</strong>.
          </div>
          <div class="redirect-note" role="alert" aria-live="polite">
            <span class="spinner"></span>
            Redirecting you to request a new one in 
            <span id="countdown-number">10</span> second(s)...
          </div>
      </div>
  <script>
    let countdown = 10;
    const numberEl = document.getElementById("countdown-number");

    const interval = setInterval(() => {
      countdown--;
      if (countdown <= 0) {
        clearInterval(interval);
        window.location.href = "forgot-password.php?error=" + "<?php echo urlencode($errorCode); ?>";
      } else {
        numberEl.textContent = countdown;
        numberEl.style.animation = "none";
        numberEl.offsetHeight; 
        numberEl.style.animation = "scaleDown 0.3s ease-in-out";
      }
    }, 1000);
  </script>

  </body>
  </html>
  ';
  exit();
}

if (!isset($_GET['token'])) {
  showTokenErrorPage('Reset Link Expired – Token Removed', 'missing_token');

}

$token = $_GET['token'];

$stmt = $pdo->prepare("SELECT * FROM users WHERE user_reset_token = :token ORDER BY user_id ASC");
$stmt->execute(['token' => $token]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
  showTokenErrorPage('Reset Link Expired – Token Removed', 'invalid_token');

}

$currentTime = date('Y-m-d H:i:s');
if (empty($user['user_token_expiry']) || $user['user_token_expiry'] < $currentTime) {
    $update = $pdo->prepare("UPDATE users SET user_reset_token = NULL, user_token_expiry = NULL WHERE user_id = :id");
    $update->execute(['id' => $user['user_id']]);
    showTokenErrorPage('Reset Link Expired – Token Removed', 'expired_token');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Reset Password</title>
  <link rel="stylesheet" type="text/css" href="authentication.css">
</head>
<body>
  <div class="center">
    <h1>Reset Password</h1>

    <?php if (isset($_SESSION['reset_error'])): ?>
      <div class="error-message"><?php echo $_SESSION['reset_error']; unset($_SESSION['reset_error']); ?></div>
    <?php endif; ?>

    <form action="reset-password-handler.php" method="POST" autocomplete="off">
      <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">

      <div class="txt_field">
        <input type="password" name="password" required>
        <span></span>
        <label>New Password</label>
      </div>

      <div class="txt_field">
        <input type="password" name="confirm" required>
        <span></span>
        <label>Confirm Password</label>
      </div>

      <input type="submit" value="Reset Password">
      
      <div class="signup_link">
          <a href="form-content.php?show=login">← Back to Login</a>
      </div>
    </form>
  </div>
</body>
</html>