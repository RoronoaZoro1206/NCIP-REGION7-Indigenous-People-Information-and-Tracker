<?php

echo "Testing PHPMailer system...\n";
try {
    require_once 'vendor/autoload.php';
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'lawrenceramirez626@gmail.com';
    $mail->Password = 'aexebedsuqhlrfra'; 
    $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;
    
    $mail->setFrom('lawrenceramirez626@gmail.com', 'NCIP System');
    $mail->addAddress('lawrenceramirez626@gmail.com');
    $mail->Subject = 'PHPMailer Test';
    $mail->Body = 'This is a test from PHPMailer system (forgot-password style)';
    
    $mail->send();
    echo "✅ PHPMailer test successful!\n";
    
} catch (Exception $e) {
    echo "❌ PHPMailer test failed: " . $e->getMessage() . "\n";
}

// Test 2: mail() function (reply_message fallback style)
echo "\nTesting mail() function system...\n";
$to = 'lawrenceramirez626@gmail.com';
$subject = 'mail() Function Test';
$message = 'This is a test from mail() function system (reply_message fallback)';
$headers = "From: lawrenceramirez626@gmail.com\r\n";
$headers .= "Content-Type: text/html; charset=UTF-8\r\n";

if (mail($to, $subject, $message, $headers)) {
    echo "✅ mail() function test successful!\n";
} else {
    echo "❌ mail() function test failed!\n";
}
?>