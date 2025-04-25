<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require 'PHPMailer-master/src/Exception.php';
require 'PHPMailer-master/src/PHPMailer.php';
require 'PHPMailer-master/src/SMTP.php';

require_once 'db.php';
require_once 'user-models.php';
require_once 'validator.php'; 
require_once 'load-env.php';
loadEnv();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST["email"]);

    if (empty($email)) {
        die("Email is required.");
    }

    // Check if user exists
    $stmt = $pdo->prepare("SELECT * FROM users WHERE user_email = ? ORDER BY user_id ASC");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user) {
        die("Email not found.");
    }

    $userTimezone = $user['user_timezone'] ?? 'Asia/Manila'; 
    
    // Generate expiry in user's timezone
    $now = new DateTime('now', new DateTimeZone($userTimezone));
    $expiryLocal = $now->add(new DateInterval('PT1H'));
    
    // Convert to UTC for database storage
    $expiryUTC = (clone $expiryLocal)->setTimezone(new DateTimeZone('Asia/Manila'));
    
    $token = bin2hex(random_bytes(16));
    $expiryForDB = $expiryUTC->format('Y-m-d H:i:s');

    // Update token and expiry in DB
    $update = $pdo->prepare("UPDATE users SET user_reset_token = ?, user_token_expiry = ? WHERE user_email = ?");
    $update->execute([$token, $expiryForDB, $email]);

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

        $resetLink = "http://localhost/Php-Project/reset-password.php?token=" . $token;

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
            .center {
              max-width: 420px;
              margin: auto;
              padding: 40px 30px;
              border-radius: 20px;
              background: #f9f9f9;
              box-shadow: 0 8px 30px rgba(0, 0, 0, 0.1);
              text-align: center;
            }

            .center h1 {
              font-size: 26px;
              color: #2ecc71;
              margin-bottom: 16px;
            }
        
            .center p {
              font-size: 15px;
              color: #555;
              line-height: 1.6;
              margin-bottom: 25px;
            }
        
            .center a input[type="submit"] {
              background-color: #3498db;
              color: #fff;
              border: none;
              padding: 12px 30px;
              border-radius: 30px;
              font-weight: bold;
              font-size: 14px;
              transition: 0.3s;
            }
        
            .center a input[type="submit"]:hover {
              background-color: #2980b9;
            }
          </style>
        </head>
        <body>
          <div class="center success-box">
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
      }
        
    } catch (Exception $e) {
      echo <<<HTML
      <!DOCTYPE html>
      <html lang="en">
      <head>
        <meta charset="UTF-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <title>Reset Link Sent</title>
        <link rel="stylesheet" href="authentication.css">
        <style>
          .center {
            max-width: 420px;
            margin: auto;
            padding: 40px 30px;
            border-radius: 20px;
            background: #f9f9f9;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.1);
            text-align: center;
          }

          .center h1 {
            font-size: 26px;
            color: #2ecc71;
            margin-bottom: 16px;
          }
      
          .center p {
            font-size: 15px;
            color: #555;
            line-height: 1.6;
            margin-bottom: 25px;
          }
      
          .center a input[type="submit"] {
            background-color: #3498db;
            color: #fff;
            border: none;
            padding: 12px 30px;
            border-radius: 30px;
            font-weight: bold;
            font-size: 14px;
            transition: 0.3s;
          }
      
          .center a input[type="submit"]:hover {
            background-color: #2980b9;
          }
        </style>
      </head>
      <body>
        <div class="center success-box">
        <div class="alert-icon">⚠️</div>
          <div class="alert-title2">Message could not be sent. Mailer Error: {$mail->ErrorInfo}</div> 
          <p>Please try refreshing your browser!<br><br>
          If still not working <br> (Press ctrl + f5 or try again later)</p>
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
    }
  } else {
    echo '
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Link Expired</title>
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
        </style>
    </head>
    <body>
        <div class="message-box">
            <div class="alert-icon">⚠️</div>
            <div class="alert-title">Link Expired or Invalid</div>
            <div class="success-message">
              ✅ <strong>' . $cleanup['cleared'] . '</strong> expired reset token(s) were successfully cleared at <strong>' . $cleanup['currentTime'] . '</strong>.
            </div>
          <div class="redirect-note">Redirecting you to request a new one in <strong>5 seconds</strong>...</div>
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
?>