<?php
require(__DIR__ . '/../vendor/autoload.php');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once 'db.php';
require_once 'user-models.php';
require_once 'validator.php'; 
require_once 'load-env.php';
loadEnv();
// Unified logger (DB + file)
try { require_once __DIR__ . '/logger.php'; } catch (Throwable $e) { /* ignore */ }

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  // Determine client IP once for consistent logging
  $client_ip = 'Unknown';
  if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
    $client_ip = $_SERVER['HTTP_CLIENT_IP'];
  } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
    $parts = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
    $client_ip = trim($parts[0]);
  } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
    $client_ip = $_SERVER['REMOTE_ADDR'];
  }
    $email = trim($_POST["email"]);

    if (empty($email)) {
        die("Email is required.");
    }

    // Check if user exists - using lowercase table/column names
    $stmt = $pdo->prepare("select * from users where user_email = ? order by user_id asc");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user) {
        die("Email not found.");
    }

    // Get user's timezone, use PHP default if not set
    $userTimezone = $user['user_timezone'] ?? 'Asia/Manila'; 
    
    // Generate expiry in user's timezone (1 hour from now)
    $now = new DateTime('now', new DateTimeZone($userTimezone));
    $expiryLocal = clone $now;
    $expiryLocal->add(new DateInterval('PT1H'));
    
    // Store the expiry time as-is (don't convert timezone again)
    $token = bin2hex(random_bytes(16));
    $expiryForDB = $expiryLocal->format('Y-m-d H:i:s');

    // Update token and expiry in DB - using lowercase table/column names
    $update = $pdo->prepare("update users set user_reset_token = ?, user_token_expiry = ? where user_email = ?");
    $update->execute([$token, $expiryForDB, $email]);

  // Log the password reset request exactly once with IP
  try {
    $details = "Password reset requested for email: " . $email;
    $logged = false;
    if (function_exists('log_user_event')) {
      log_user_event($pdo, 'PASSWORD_RESET_REQUEST', $details, (int)$user['user_id'], $client_ip);
      $logged = true;
    }
    if (!$logged) {
      $log_query = "insert into user_logs (user_id, log_type, log_details, log_timestamp, log_ip_address) 
               values (?, 'PASSWORD_RESET_REQUEST', ?, current_timestamp, ?)";
      $log_stmt = $pdo->prepare($log_query);
      $log_stmt->execute([$user['user_id'], $details, $client_ip]);
    }
  } catch (PDOException $e) {
    // If DB logging fails, at least mirror to file via unified logger without PDO
    error_log("Could not log password reset request: " . $e->getMessage());
    if (function_exists('log_user_event')) { log_user_event(null, 'PASSWORD_RESET_REQUEST', "Password reset requested for email: {$email}", (int)$user['user_id'], $client_ip); }
  }

    // Prepare PHPMailer
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username = getenv('GMAIL_EMAIL');
        $mail->Password = getenv('GMAIL_APP_PASSWORD');      
        $mail->SMTPSecure = 'tls';
        $mail->Port       = 587;

        // Email content
        $mail->setFrom('lawrenceramirez626@gmail.com', 'NCIP CEBU');
        $mail->addReplyTo('lawrenceramirez626@gmail.com', 'NCIP CEBU');
        $mail->addAddress($email, $user['user_username']);

        $resetLink = "http://localhost/Information%20Management/IM---Copy/front%20end/reset-password.php?token=" . $token;

        $mail->isHTML(true);
        $mail->Subject = 'Password Reset Request';
        $mail->Body    = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        </head>
        <body style='margin: 0; padding: 20px; background-color: #f7f9fc; font-family: Arial, sans-serif;'>
            <div style='max-width: 600px; margin: 0 auto; background: white; border-radius: 8px; padding: 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);'>
            <p style='color: #333; font-size: 16px; line-height: 1.6; margin-bottom: 20px;'>
              Dear <strong style='color: #2d3436;'>"
              . htmlspecialchars($user['user_username']) .
              "</strong>,
            </p>
        
                <p style='color: #555; font-size: 15px; line-height: 1.6; margin-bottom: 25px;'>
                    We received a request to reset your account password. Please click the button below to securely update your credentials:
                </p>
        
                <div style='text-align: center; margin: 30px 0;'>
                    <a href='$resetLink' 
                       style='display: inline-block; 
                              padding: 12px 24px; 
                              background-color: #007BFF; 
                              color: #ffffff !important; 
                              text-decoration: none; 
                              border-radius: 4px; 
                              font-weight: 500;
                              transition: background-color 0.3s ease;
                              border: 1px solid #006fe6;'>
                        Reset Password
                    </a>
                </div>
        
                <p style='color: #777; font-size: 14px; line-height: 1.6; margin-bottom: 25px;'>
                    This security link will expire in <strong>1 hour</strong> (your local time: " . $expiryLocal->format('F j, Y g:i A') . ' PHT'. ").
                </p>
        
                <div style='border-top: 1px solid #eee; padding-top: 20px; margin-top: 30px;'>
                    <p style='color: #999; font-size: 12px; line-height: 1.5; text-align: center'>
                        If you didn't request this password reset, please ignore this email.
                    </p>
                </div>
            </div>
        </body>
        </html>
        ";

  if (!$mail->send()) {
            file_put_contents(__DIR__ . '/email-errors.log', "[" . date('Y-m-d H:i:s') . "] Warning: " . $mail->ErrorInfo . "\n", FILE_APPEND);
            
      // Log email failure once
      try {
        $error_details = "Failed to send password reset email: " . $mail->ErrorInfo;
        $logged = false;
        if (function_exists('log_user_event')) {
          log_user_event($pdo, 'EMAIL_ERROR', $error_details, (int)$user['user_id'], $client_ip);
          $logged = true;
        }
        if (!$logged) {
          $error_log_query = "insert into user_logs (user_id, log_type, log_details, log_timestamp, log_ip_address) 
                    values (?, 'EMAIL_ERROR', ?, current_timestamp, ?)";
          $error_log_stmt = $pdo->prepare($error_log_query);
          $error_log_stmt->execute([$user['user_id'], $error_details, $client_ip]);
        }
      } catch (PDOException $e) {
        error_log("Could not log email error: " . $e->getMessage());
        if (function_exists('log_user_event')) { log_user_event(null, 'EMAIL_ERROR', "Failed to send password reset email: " . $mail->ErrorInfo, (int)$user['user_id'], $client_ip); }
      }
            
            echo <<<HTML
            <!DOCTYPE html>
            <html lang="en">
            <head>
              <meta charset="UTF-8" />
              <meta name="viewport" content="width=device-width, initial-scale=1.0" />
              <title>Email Error</title>
              <link rel="stylesheet" href="authentication.css">
              <style>
                body {
                  background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
                  font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                  margin: 0;
                  padding: 0;
                  min-height: 100vh;
                  display: flex;
                  align-items: center;
                  justify-content: center;
                }

                .error-box {
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

                .alert-icon {
                  font-size: 60px;
                  margin-bottom: 20px;
                  animation: shake 1s ease-in-out;
                }

                @keyframes shake {
                  0%, 100% { transform: translateX(0); }
                  25% { transform: translateX(-5px); }
                  75% { transform: translateX(5px); }
                }

                .alert-title2 {
                  font-size: 24px;
                  color: #e74c3c;
                  margin-bottom: 20px;
                  font-weight: 700;
                }

                .error-box p {
                  font-size: 16px;
                  color: #555;
                  line-height: 1.6;
                  margin-bottom: 30px;
                }

                .signup_link a {
                  display: inline-block;
                  background-color: #3498db;
                  color: #fff !important;
                  text-decoration: none;
                  padding: 12px 30px;
                  border-radius: 25px;
                  font-weight: 600;
                  font-size: 14px;
                  transition: all 0.3s ease;
                  box-shadow: 0 4px 15px rgba(52, 152, 219, 0.3);
                }

                .signup_link a:hover {
                  background-color: #2980b9;
                  transform: translateY(-2px);
                  box-shadow: 0 6px 20px rgba(52, 152, 219, 0.4);
                }
              </style>
            </head>
            <body>
              <div class="error-box">
                <div class="alert-icon">⚠️</div>
                <div class="alert-title2">Message could not be sent</div> 
                <p>Mailer Error: {$mail->ErrorInfo}<br><br>
                Please try refreshing your browser!<br><br>
                If still not working, press Ctrl + F5 or try again later</p>
                <div class="signup_link">
                  <a href="form-content.php?show=login">← Back to Login</a>
                </div>
              </div>
            </body>
            </html>
HTML;
            
        } else {
            echo <<<HTML
            <!DOCTYPE html>
            <html lang="en">
            <head>
              <meta charset="UTF-8" />
              <meta name="viewport" content="width=device-width, initial-scale=1.0" />
              <title>Reset Link Sent</title>
              <link rel="stylesheet" href="authentication.css">
              <style>
                body {
                  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
                  margin-bottom: 30px;
                }

                .signup_link a {
                  display: inline-block;
                  background-color: #3498db;
                  color: #fff !important;
                  text-decoration: none;
                  padding: 12px 30px;
                  border-radius: 25px;
                  font-weight: 600;
                  font-size: 14px;
                  transition: all 0.3s ease;
                  box-shadow: 0 4px 15px rgba(52, 152, 219, 0.3);
                }

                .signup_link a:hover {
                  background-color: #2980b9;
                  transform: translateY(-2px);
                  box-shadow: 0 6px 20px rgba(52, 152, 219, 0.4);
                }
              </style>
            </head>
            <body>
              <div class="success-box">
                <div class="success-icon">✅</div>
                <h2 class="success-title">Reset Link Sent!</h2>
                <p>We've sent a secure password reset link to your email. <br><br>
                Check your inbox <br> (and your spam folder just in case)!</p>
                <div class="signup_link">
                  <a href="form-content.php?show=login">← Back to Login</a>
                </div>
              </div>
            </body>
            </html>
HTML;
            
    // Log email success once
      try {
        $success_details = "Password reset email sent successfully to: " . $email;
        $logged = false;
        if (function_exists('log_user_event')) {
          log_user_event($pdo, 'EMAIL_SENT', $success_details, (int)$user['user_id'], $client_ip);
          $logged = true;
        }
        if (!$logged) {
          $success_log_query = "insert into user_logs (user_id, log_type, log_details, log_timestamp, log_ip_address) 
                     values (?, 'EMAIL_SENT', ?, current_timestamp, ?)";
          $success_log_stmt = $pdo->prepare($success_log_query);
          $success_log_stmt->execute([$user['user_id'], $success_details, $client_ip]);
        }
      } catch (PDOException $e) {
        error_log("Could not log email success: " . $e->getMessage());
        if (function_exists('log_user_event')) { log_user_event(null, 'EMAIL_SENT', "Email sent to: {$email}", (int)$user['user_id'], $client_ip); }
      }
        }
        
    } catch (Exception $e) {
        echo <<<HTML
        <!DOCTYPE html>
        <html lang="en">
        <head>
          <meta charset="UTF-8" />
          <meta name="viewport" content="width=device-width, initial-scale=1.0" />
          <title>Email Error</title>
          <link rel="stylesheet" href="authentication.css">
          <style>
            body {
              background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
              font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
              margin: 0;
              padding: 0;
              min-height: 100vh;
              display: flex;
              align-items: center;
              justify-content: center;
            }

            .error-box {
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

            .alert-icon {
              font-size: 60px;
              margin-bottom: 20px;
              animation: shake 1s ease-in-out;
            }

            @keyframes shake {
              0%, 100% { transform: translateX(0); }
              25% { transform: translateX(-5px); }
              75% { transform: translateX(5px); }
            }

            .alert-title2 {
              font-size: 24px;
              color: #e74c3c;
              margin-bottom: 20px;
              font-weight: 700;
            }

            .error-box p {
              font-size: 16px;
              color: #555;
              line-height: 1.6;
              margin-bottom: 30px;
            }

            .signup_link a {
              display: inline-block;
              background-color: #3498db;
              color: #fff !important;
              text-decoration: none;
              padding: 12px 30px;
              border-radius: 25px;
              font-weight: 600;
              font-size: 14px;
              transition: all 0.3s ease;
              box-shadow: 0 4px 15px rgba(52, 152, 219, 0.3);
            }

            .signup_link a:hover {
              background-color: #2980b9;
              transform: translateY(-2px);
              box-shadow: 0 6px 20px rgba(52, 152, 219, 0.4);
            }
          </style>
        </head>
        <body>
          <div class="error-box">
            <div class="alert-icon">⚠️</div>
            <div class="alert-title2">Message could not be sent</div> 
            <p>Mailer Error: {$mail->ErrorInfo}<br><br>
            Please try refreshing your browser!<br><br>
            If still not working, press Ctrl + F5 or try again later</p>
            <div class="signup_link">
              <a href="form-content.php?show=login">← Back to Login</a>
            </div>
          </div>
        </body>
        </html>
HTML;
        
        file_put_contents(
            __DIR__ . '/email-errors.log',
            "[" . date('Y-m-d H:i:s') . " UTC+08:00 (Philippine Time)"."] Mail Error: " . $mail->ErrorInfo . " | Exception: " . $e->getMessage() . "\n",
            FILE_APPEND
        );
        
  // Log exception once
    try {
      $exception_details = "Exception during password reset: " . $e->getMessage();
      $logged = false;
      if (function_exists('log_user_event')) {
        log_user_event($pdo, 'EXCEPTION', $exception_details, (int)$user['user_id'], $client_ip);
        $logged = true;
      }
      if (!$logged) {
        $exception_log_query = "insert into user_logs (user_id, log_type, log_details, log_timestamp, log_ip_address) 
                   values (?, 'EXCEPTION', ?, current_timestamp, ?)";
        $exception_log_stmt = $pdo->prepare($exception_log_query);
        $exception_log_stmt->execute([$user['user_id'], $exception_details, $client_ip]);
      }
    } catch (PDOException $e) {
      error_log("Could not log exception: " . $e->getMessage());
      if (function_exists('log_user_event')) { log_user_event(null, 'EXCEPTION', 'Exception during password reset (DB logging failed).', (int)$user['user_id'], $client_ip); }
    }
    }
} else {
    // If this is accessed directly, show error
    $errorCode = "direct-access";
    
    echo '
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Invalid Access</title>
        <link rel="stylesheet" href="authentication.css">
        <meta http-equiv="refresh" content="5;url=forgot-password.php">
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

            .message-box {
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

            .alert-icon {
                font-size: 60px;
                margin-bottom: 20px;
                animation: bounce 1s ease-in-out infinite;
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

            .alert-title {
                font-size: 24px;
                color: #e74c3c;
                margin-bottom: 20px;
                font-weight: 700;
            }

            .redirect-note {
                font-size: 16px;
                color: #555;
                line-height: 1.6;
                margin-bottom: 20px;
            }
        </style>
    </head>
    <body>
        <div class="message-box">
            <div class="alert-icon">⚠️</div>
            <div class="alert-title">Invalid Access</div>
            <div class="redirect-note">This page cannot be accessed directly.<br><br>Redirecting you to forgot password page in <strong>5 seconds</strong>...</div>
        </div>
        <script>
            setTimeout(function() {
                window.location.href = "forgot-password.php?error=' . urlencode($errorCode) . '";
            }, 5000);
        </script>
    </body>
    </html>';
    exit();
}
?>