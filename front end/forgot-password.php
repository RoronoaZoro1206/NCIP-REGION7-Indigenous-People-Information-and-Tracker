<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Forgot Password</title>
  <link rel="stylesheet" href="authentication.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
</head>
<body>
  <div class="center">
    <h1>Forgot Password</h1>

    <?php if (isset($_GET['error']) && $_GET['error'] === 'expired_token'): ?>
        <div class="error-message animate__animated animate__shakeX">
            ⚠️ <strong>Reset Link Expired:</strong> Please enter your email to request a new one.
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['reset_request_error'])): ?>
      <div class="error-message">
        <?php
          echo $_SESSION['reset_request_error'];
          unset($_SESSION['reset_request_error']);
        ?>
      </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['reset_request_success'])): ?>
      <div class="success-message">
        <?php
          echo $_SESSION['reset_request_success'];
          unset($_SESSION['reset_request_success']);
        ?>
      </div>
    <?php endif; ?>

    <form action="reset-request-handler.php" method="POST" autocomplete="off">
      <div class="txt_field">
        <input type="email" name="email" required>
        <span></span>
        <label>Email</label>
      </div>
      <input type="submit" value="Send Reset Link">

      <div class="signup_link">
        <a href="form-content.php?show=login">← Back to Login</a>
      </div>

    </form>
  </div>
</body>
</html>
