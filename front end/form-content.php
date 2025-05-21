<?php
session_start();

// Check user session (optional redirect if logged in)
if (isset($_SESSION['user'])) {
    // Redirect based on role
    if (isset($_SESSION['user_role']) && strtoupper($_SESSION['user_role']) === 'ADMIN') {
        header("Location: ../admin/index.php");
    } else {
        header("Location: e-service.php");
    }
    exit();
}

// Default to login if not specified
$show = $_GET['show'] ?? 'login';
$error = $_GET['error'] ?? null;
$success = $_GET['success'] ?? null;
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
      
      <?php if($success): ?>
      <div class="success-message">
        <i class='bx bx-check-circle'></i>
        <?php echo $_SESSION['forgot_success'] ?? "Account created successfully!"; ?>
      </div>
      <?php endif; ?>
      
      <div class="input-box">
        <input type="text" placeholder="Username" required name="username">
        <i class='bx bxs-user'></i>
      </div>
      <div class="input-box">
        <input type="password" placeholder="Password" required name="password">
        <i class='bx bxs-lock-alt'></i>
      </div>
      <div class="forgot-link">
        <a href="forgot-password.php">Forgot Password?</a>
      </div>
      <!-- Hidden field for regular user login -->
      <input type="hidden" name="admin_login" value="false">
      <button type="submit" class="btn">Login</button>
    </form>
  </div>

  <!-- Registration Form -->
  <div class="form-box register">
    <form method="POST" action="register-user.php" autocomplete="off">
      <h1>Registration</h1>
      <?php require_once 'register-views.php'; render_errors(); ?>
      <div class="input-box">
        <input type="text" placeholder="Username" required name="username" 
               value="<?php echo isset($_SESSION['old']['username']) ? htmlspecialchars($_SESSION['old']['username']) : ''; ?>">
        <i class='bx bxs-user'></i>
      </div>
      <div class="input-box">
        <input type="text" placeholder="Email" required name="email"
               value="<?php echo isset($_SESSION['old']['email']) ? htmlspecialchars($_SESSION['old']['email']) : ''; ?>">
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
      <!-- Hidden fields -->
      <input type="hidden" name="timezone" id="timezone">
      <input type="hidden" name="role" value="USER">
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
  
  // Handle form display based on URL parameter
  const urlParams = new URLSearchParams(window.location.search);
  const showParam = urlParams.get('show');
  
  if (showParam === 'register') {
    document.querySelector('.container').classList.add('active');
  }
  
  // Clear session data after it's been used
  <?php 
  if (isset($_SESSION['old'])) {
      echo "console.log('Clearing old form data');";
      unset($_SESSION['old']);
  }
  ?>
});
</script>
<script type='text/javascript' src='js/Authentication.js'></script>
</body>
</html>