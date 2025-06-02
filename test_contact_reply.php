<?php
// filepath: c:\xampp\htdocs\Information Management\IM---Copy\test_contact_reply_fixed.php

echo "Testing Contact Reply System (NCIP Contact Sync) - FIXED VERSION...\n";

// Test 1: PHPMailer (should still work)
try {
    require_once 'vendor/autoload.php';
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'lawrenceramirez626@gmail.com';
    $mail->Password = 'hjlp fqfe pjvt nbjd';
    $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;
    
    $mail->setFrom('lawrenceramirez626@gmail.com', 'NCIP Contact Admin');
    $mail->addAddress('lawrenceramirez626@gmail.com');
    $mail->Subject = 'Contact Reply Test [ID: 999] - PHPMailer';
    $mail->Body = 'This is a test from Contact Reply System using PHPMailer';
    
    $mail->send();
    echo "✅ PHPMailer test successful!\n";
    
} catch (Exception $e) {
    echo "❌ PHPMailer test failed: " . $e->getMessage() . "\n";
}

// Test 2: mail() function with FIXED configuration
echo "\nTesting mail() function with FIXED sendmail setup...\n";

$to = 'lawrenceramirez626@gmail.com';
$subject = 'Contact Reply mail() Test - FIXED';
$message = 'This should now work with FIXED NCIP Contact Sync configuration!';
$headers = "From: lawrenceramirez626@gmail.com\r\n";
$headers .= "Reply-To: lawrenceramirez626@gmail.com\r\n";
$headers .= "Content-Type: text/html; charset=UTF-8\r\n";
$headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";

if (mail($to, $subject, $message, $headers)) {
    echo "✅ mail() function test successful!\n";
    echo "📧 Check your email for the test message!\n";
} else {
    echo "❌ mail() function test failed!\n";
    echo "💡 Check error.log in C:\\xampp\\sendmail\\error.log\n";
}

// Test 3: Show current mail configuration
echo "\nCurrent mail configuration:\n";
echo "SMTP: " . ini_get('SMTP') . "\n";
echo "smtp_port: " . ini_get('smtp_port') . "\n";
echo "sendmail_from: " . ini_get('sendmail_from') . "\n";
echo "sendmail_path: " . ini_get('sendmail_path') . "\n";
?>