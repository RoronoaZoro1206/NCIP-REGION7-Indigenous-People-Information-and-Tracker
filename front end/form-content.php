<?php
session_start();

// Check user session (optional redirect if logged in)
if (isset($_SESSION['user'])) {
    header("Location: e-service.php");
    exit();
}


// Default to login if not specified
$show = $_GET['show'] ?? 'login';
$error = $_GET['error'] ?? null;
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login/Signup Form</title>
  <link rel="stylesheet" href="Authentication.css">
  <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
</head>
<body>
<div class="container">
  <!-- Login Form -->
  <div class="form-box login">
    <form method="POST" action="login-user.php" autocomplete="off">
      <h1>Login</h1>
      <?php require_once 'login-views.php'; render_message(); ?>
      <div class="input-box">
        <input type="text" placeholder="Username" required name="username" >
        <i class='bx bxs-user'></i>
      </div>
      <div class="input-box">
        <input type="password" placeholder="Password" required name="password">
        <i class='bx bxs-lock-alt'></i>
      </div>
      <div class="forgot-link">
        <a href="forgot-password.php">Forgot Password?</a>
      </div>
      <button type="submit" class="btn">Login</button>
    </form>
  </div>

  <!-- Registration Form -->
  <div class="form-box register">
    <form method="POST" action="register-user.php" autocomplete="off">
      <h1>Registration</h1>
      <?php require_once 'register-views.php'; render_errors(); ?>
      <div class="input-box">
        <input type="text" placeholder="Username" required name="username">
        <i class='bx bxs-user'></i>
      </div>
      <div class="input-box">
        <input type="text" placeholder="Email" required name="email">
        <i class='bx bxs-envelope'></i>
      </div>
      <div class="input-box">
        <input type="password" placeholder="Password" required name="password">
        <i class='bx bxs-lock-alt'></i>
      </div>
      <div class="input-box">
        <input type="password" placeholder="Confirm Password" required name="confirm_password">
        <i class='bx bxs-lock-alt'></i>
      </div>
      <input type="hidden" name="timezone" id="timezone">
      <button type="submit" class="btn">Register</button>
    </form>
  </div>

  <!-- Toggle Panel -->
  <div class="toggle-box">
    <div class="toggle-panel toggle-left">
      <h1>Hello, Welcome!</h1>
      <p>Don't have an account?</p>
      <button class="btn register-btn">Register</button>
    </div>
    <div class="toggle-panel toggle-right">
      <h1>Welcome Back!</h1>
      <p>Already have an account?</p>
      <button class="btn login-btn">Login</button>
    </div>
  </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function () {
  // Set timezone
  const tzInput = document.getElementById('timezone');
  if (tzInput) {
    tzInput.value = Intl.DateTimeFormat().resolvedOptions().timeZone;
  }
});
</script>
<script src="Authentication.js"></script>
</body>
</html>
