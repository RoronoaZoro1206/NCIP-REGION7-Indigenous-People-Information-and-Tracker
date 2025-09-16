<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'db.php';
require_once 'user-models.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $token = $_POST['token'];
    $password = $_POST['password'];
    $confirm = $_POST['confirm'];

    // Basic validation
    if (empty($token) || empty($password) || empty($confirm)) {
        $_SESSION['reset_error'] = "All fields are required.";
        header("Location: reset-password.php?token=" . urlencode($token));
        exit;
    }

    if ($password !== $confirm) {
        $_SESSION['reset_error'] = "Passwords do not match.";
        header("Location: reset-password.php?token=" . urlencode($token));
        exit;
    }

    // Validate token using lowercase column names
    $user = getUserByToken($pdo, $token);
    if (!$user) {
        $_SESSION['reset_error'] = "Invalid or expired reset token.";
        header("Location: forgot-password.php");
        exit;
    }

    try {
        // Get user's timezone (fallback to Asia/Manila if not set) - using lowercase
        $userTimezone = $user['user_timezone'] ?? 'Asia/Manila';
        
        // Convert stored time to user's timezone - using lowercase
        $expiryUtc = new DateTime(
            $user['user_token_expiry'], 
            new DateTimeZone('Asia/Manila')
        );
        $expiryUserLocal = $expiryUtc->setTimezone(
            new DateTimeZone($userTimezone)
        );
        
        // Get current time in user's timezone
        $now = new DateTime('now', new DateTimeZone($userTimezone));
        
        // Check expiration
        if ($expiryUserLocal < $now) {
            // Log expired token attempt using lowercase table/column names
            try {
                $log_query = "insert into user_logs (user_id, log_type, log_details, log_timestamp) 
                             values (?, 'PASSWORD_RESET_EXPIRED', ?, current_timestamp)";
                $log_stmt = $pdo->prepare($log_query);
                $log_details = "Attempted to use expired password reset token";
                $log_stmt->execute([$user['user_id'], $log_details]);
            } catch (PDOException $e) {
                error_log("Could not log expired token attempt: " . $e->getMessage());
            }
            
            $_SESSION['reset_error'] = "Reset token has expired.";
            header("Location: forgot-password.php");
            exit;
        }
    } catch (Exception $e) {
        // Fallback to server time if timezone conversion fails
        if (strtotime($user['user_token_expiry']) < time()) {
            // Log expired token attempt using lowercase table/column names
            try {
                $log_query = "insert into user_logs (user_id, log_type, log_details, log_timestamp) 
                             values (?, 'PASSWORD_RESET_EXPIRED', ?, current_timestamp)";
                $log_stmt = $pdo->prepare($log_query);
                $log_details = "Attempted to use expired password reset token (fallback check)";
                $log_stmt->execute([$user['user_id'], $log_details]);
            } catch (PDOException $e) {
                error_log("Could not log expired token attempt: " . $e->getMessage());
            }
            
            $_SESSION['reset_error'] = "Reset token has expired.";
            header("Location: forgot-password.php");
            exit;
        }
    }

    // Validate password strength - changed from 8 to 5 to match your registration validation
    if (strlen($password) < 5) {
        $_SESSION['reset_error'] = "Password must be at least 5 characters long.";
        header("Location: reset-password.php?token=" . urlencode($token));
        exit;
    }

    // Hash new password and update
    $options = [
        'cost' => 12
    ];
    $hashedPassword = password_hash($password, PASSWORD_BCRYPT, $options);
    
    // Use lowercase column name
    if (!updateUserPassword($pdo, $user['user_id'], $hashedPassword)) {
        // Log password update failure
        try {
            $log_query = "insert into user_logs (user_id, log_type, log_details, log_timestamp) 
                         values (?, 'PASSWORD_RESET_FAILED', ?, current_timestamp)";
            $log_stmt = $pdo->prepare($log_query);
            $log_details = "Failed to update password during reset";
            $log_stmt->execute([$user['user_id'], $log_details]);
        } catch (PDOException $e) {
            error_log("Could not log password update failure: " . $e->getMessage());
        }
        
        $_SESSION['reset_error'] = "Failed to update password.";
        header("Location: reset-password.php?token=" . urlencode($token));
        exit;
    }

    // Log successful password reset
    try {
        $log_query = "insert into user_logs (user_id, log_type, log_details, log_timestamp) 
                     values (?, 'PASSWORD_RESET_SUCCESS', ?, current_timestamp)";
        $log_stmt = $pdo->prepare($log_query);
        $log_details = "Password reset completed successfully";
        $log_stmt->execute([$user['user_id'], $log_details]);
    } catch (PDOException $e) {
        error_log("Could not log successful password reset: " . $e->getMessage());
    }

    // Check if this is an admin user and redirect appropriately - using lowercase
    $is_admin = strtoupper($user['user_role']) === 'ADMIN';
    
    // Set success message in session
    $_SESSION['password_reset_success'] = "Password reset successful! You can now log in with your new password.";
    $_SESSION['success_message'] = "Your password has been changed successfully!";
    
    // Determine redirect URL
    $redirectUrl = $is_admin ? '../admin/login.php' : 'form-content.php?show=login';
    $loginText = $is_admin ? 'Admin Login' : 'User Login';
    
    // Show success page with 5-second countdown
    echo <<<HTML
    <!DOCTYPE html>
    <html lang="en">
    <head>
      <meta charset="UTF-8" />
      <meta name="viewport" content="width=device-width, initial-scale=1.0" />
      <title>Password Reset Successful</title>
      <link rel="stylesheet" href="authentication.css">
      <style>
        body {
          background: linear-gradient(135deg, #2ecc71 0%, #27ae60 100%);
          font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
          margin: 0;
          padding: 0;
          min-height: 100vh;
          display: flex;
          align-items: center;
          justify-content: center;
        }

        .success-box {
          max-width: 450px;
          margin: auto;
          padding: 40px 30px;
          border-radius: 15px;
          background: #ffffff;
          box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
          text-align: center;
          animation: slideIn 0.5s ease-out;
        }

        @keyframes slideIn {
          from {
            opacity: 0;
            transform: translateY(-30px);
          }
          to {
            opacity: 1;
            transform: translateY(0);
          }
        }

        .success-icon {
          font-size: 60px;
          margin-bottom: 20px;
          animation: bounce 1s ease-in-out;
        }

        @keyframes bounce {
          0%, 20%, 50%, 80%, 100% {
            transform: translateY(0);
          }
          40% {
            transform: translateY(-10px);
          }
          60% {
            transform: translateY(-5px);
          }
        }

        .success-title {
          font-size: 28px;
          color: #2ecc71;
          margin-bottom: 20px;
          font-weight: 700;
        }

        .success-box p {
          font-size: 16px;
          color: #555;
          line-height: 1.6;
          margin-bottom: 20px;
        }

        .user-info {
          background: #f8f9fa;
          padding: 15px;
          border-radius: 8px;
          margin: 20px 0;
          border-left: 4px solid #2ecc71;
        }

        .user-info strong {
          color: #2ecc71;
        }

        .countdown-container {
          background: #e8f5e8;
          padding: 15px;
          border-radius: 8px;
          margin: 20px 0;
          border: 1px solid #2ecc71;
        }

        .countdown-text {
          font-size: 16px;
          color: #27ae60;
          font-weight: 600;
          margin: 0;
        }

        .countdown-number {
          font-size: 24px;
          font-weight: bold;
          color: #2ecc71;
          animation: pulse 1s infinite;
        }

        @keyframes pulse {
          0% { transform: scale(1); }
          50% { transform: scale(1.1); }
          100% { transform: scale(1); }
        }

        .button-container {
          margin-top: 25px;
        }

        .btn {
          display: inline-block;
          text-decoration: none;
          padding: 12px 25px;
          border-radius: 25px;
          font-weight: 600;
          font-size: 14px;
          transition: all 0.3s ease;
          margin: 0 5px;
          cursor: pointer;
        }

        .btn-primary {
          background-color: #2ecc71;
          color: #fff !important;
          box-shadow: 0 4px 15px rgba(46, 204, 113, 0.3);
        }

        .btn-primary:hover {
          background-color: #27ae60;
          transform: translateY(-2px);
          box-shadow: 0 6px 20px rgba(46, 204, 113, 0.4);
        }

        .btn-secondary {
          background-color: #3498db;
          color: #fff !important;
          box-shadow: 0 4px 15px rgba(52, 152, 219, 0.3);
        }

        .btn-secondary:hover {
          background-color: #2980b9;
          transform: translateY(-2px);
          box-shadow: 0 6px 20px rgba(52, 152, 219, 0.4);
        }

        .progress-bar {
          width: 100%;
          height: 4px;
          background: #e0e0e0;
          border-radius: 2px;
          margin-top: 15px;
          overflow: hidden;
        }

        .progress-fill {
          height: 100%;
          background: #2ecc71;
          border-radius: 2px;
          /* Ensure the bar progresses and stays at 0 when finished */
          animation: countdown-progress 10s linear forwards;
        }

        @keyframes countdown-progress {
          from { width: 100%; }
          to { width: 0%; }
        }
      </style>
    </head>
    <body>
      <div class="success-box">
        <div class="success-icon">🎉</div>
        <h2 class="success-title">Password Reset Successful!</h2>
        
        <div class="user-info">
          <p><strong>Welcome back, {$user['user_username']}!</strong></p>
          <p>Your password has been successfully changed and is now active.</p>
        </div>
        
        <p>You can now log in to your account using your new password. All previous sessions have been invalidated for security.</p>
        
        <div class="countdown-container">
          <p class="countdown-text">
            Redirecting to {$loginText} in 
            <span class="countdown-number" id="countdown">10</span> 
            seconds...
          </p>
          <div class="progress-bar">
            <div class="progress-fill"></div>
          </div>
        </div>
        
        <div class="button-container">
          <a href="{$redirectUrl}" class="btn btn-primary" onclick="clearCountdown()">
            Go to {$loginText} Now
          </a>
        </div>
      </div>

      <script>
        const TOTAL_SECONDS = 10; // keep this in sync with the CSS animation duration
        let countdown = TOTAL_SECONDS;
        let interval;
        const countdownEl = document.getElementById('countdown');
        // Sync displayed number and ensure the progress animation duration matches
        countdownEl.textContent = TOTAL_SECONDS;
        const progressEl = document.querySelector('.progress-fill');
        if (progressEl) {
          // Override any stylesheet value to avoid mismatch if TOTAL_SECONDS changes
          progressEl.style.animation = 'countdown-progress ' + TOTAL_SECONDS + 's linear forwards';
        }
        
        function startCountdown() {
          interval = setInterval(() => {
            countdown--;
            countdownEl.textContent = countdown;
            
            if (countdown <= 0) {
              clearInterval(interval);
              countdownEl.textContent = '0';
              
              // Add loading text
              document.querySelector('.countdown-text').innerHTML = 
                'Redirecting now... <span class="countdown-number">⏳</span>';
              
              // Redirect after a brief moment
              setTimeout(() => {
                window.location.href = '{$redirectUrl}';
              }, 500);
            }
          }, 1000);
        }
        
        function clearCountdown() {
          if (interval) {
            clearInterval(interval);
          }
        }
        
        // Start countdown when page loads
        startCountdown();
        
        // Clear countdown if user clicks any link
        document.addEventListener('click', function(e) {
          if (e.target.tagName === 'A') {
            clearCountdown();
          }
        });
        
        // Prevent accidental navigation away
        window.addEventListener('beforeunload', function(e) {
          if (countdown > 0) {
            clearCountdown();
          }
        });
      </script>
    </body>
    </html>
HTML;
    exit;
} else {
    // If accessed directly, redirect to forgot password page
    header("Location: forgot-password.php");
    exit;
}
?>