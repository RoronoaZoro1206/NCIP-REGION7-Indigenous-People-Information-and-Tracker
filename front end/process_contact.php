<?php
// Start session for message handling
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database connection
require_once 'db.php';

// Initialize response array
$response = [
    'success' => false,
    'message' => ''
];

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data and sanitize inputs
    $name = isset($_POST['yit_contact']['name']) ? trim(htmlspecialchars($_POST['yit_contact']['name'], ENT_QUOTES, 'UTF-8')) : '';
    $email = isset($_POST['yit_contact']['email']) ? trim(filter_var($_POST['yit_contact']['email'], FILTER_SANITIZE_EMAIL)) : '';
    $phone = isset($_POST['yit_contact']['phone']) ? trim(htmlspecialchars($_POST['yit_contact']['phone'], ENT_QUOTES, 'UTF-8')) : '';
    $message = isset($_POST['yit_contact']['message']) ? trim(htmlspecialchars($_POST['yit_contact']['message'], ENT_QUOTES, 'UTF-8')) : '';
    
    // Get client IP address for logging
    $client_ip = $_SERVER['HTTP_CLIENT_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    
    // Validate required fields
    $errors = [];
    
    if (empty($name)) {
        $errors[] = "Name is required";
    } elseif (strlen($name) > 100) {
        $errors[] = "Name must be less than 100 characters";
    }
    
    if (empty($email)) {
        $errors[] = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address";
    } elseif (strlen($email) > 100) {
        $errors[] = "Email must be less than 100 characters";
    }
    
    if (!empty($phone) && strlen($phone) > 20) {
        $errors[] = "Phone number must be less than 20 characters";
    }
    
    if (empty($message)) {
        $errors[] = "Message is required";
    } elseif (strlen($message) > 5000) {
        $errors[] = "Message must be less than 5000 characters";
    }
    
    // Check for honeypot field (anti-spam)
    if (!empty($_POST['yit_bot'])) {
        // This is likely a bot submission - log it but respond normally
        try {
            $spam_log_query = "INSERT INTO USER_LOGS (USER_ID, LOG_TYPE, LOG_DETAILS, LOG_IP_ADDRESS) 
                              VALUES (NULL, 'SPAM_ATTEMPT', :details, :ip_address)";
            $spam_log_stmt = $pdo->prepare($spam_log_query);
            $spam_details = "Bot submission detected from contact form | Name: " . $name . " | Email: " . $email;
            $spam_log_stmt->bindParam(":details", $spam_details);
            $spam_log_stmt->bindParam(":ip_address", $client_ip);
            $spam_log_stmt->execute();
        } catch (Exception $log_error) {
            error_log("Failed to log spam attempt: " . $log_error->getMessage());
        }
        
        // Respond normally to avoid revealing spam detection
        $_SESSION['contact_success'] = "Thank you for your message. We'll get back to you soon.";
        header('Location: contact.php');
        exit;
    }
    
    // Process submission if no errors
    if (empty($errors)) {
        try {
            // Begin transaction for data consistency
            $pdo->beginTransaction();
            
            // Prepare SQL statement with correct column names from your schema
            $query = "INSERT INTO CONTACT_MESSAGE (CONTM_NAME, CONTM_EMAIL, CONTM_PHONE, CONTM_MESSAGE, CONTM_SUBMISSION_DATE, CONTM_STATUS) 
                     VALUES (:name, :email, :phone, :message, CURRENT_TIMESTAMP, 'Unread') RETURNING CONTM_ID";
            
            $stmt = $pdo->prepare($query);
            
            // Bind parameters
            $stmt->bindParam(':name', $name, PDO::PARAM_STR);
            $stmt->bindParam(':email', $email, PDO::PARAM_STR);
            $stmt->bindParam(':phone', $phone, PDO::PARAM_STR);
            $stmt->bindParam(':message', $message, PDO::PARAM_STR);
            
            // Execute the statement
            if ($stmt->execute()) {
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                $contact_id = $result['contm_id'];
                
                // Log successful contact form submission
                try {
                    $log_query = "INSERT INTO USER_LOGS (USER_ID, LOG_TYPE, LOG_DETAILS, LOG_IP_ADDRESS) 
                                 VALUES (NULL, 'CONTACT_FORM_SUBMITTED', :details, :ip_address)";
                    $log_stmt = $pdo->prepare($log_query);
                    $log_details = "Contact form submitted successfully | Contact ID: " . $contact_id . " | Name: " . $name . " | Email: " . $email;
                    $log_stmt->bindParam(":details", $log_details);
                    $log_stmt->bindParam(":ip_address", $client_ip);
                    $log_stmt->execute();
                } catch (Exception $log_error) {
                    error_log("Failed to log contact form submission: " . $log_error->getMessage());
                    // Don't fail the main operation if logging fails
                }
                
                // Commit transaction
                $pdo->commit();
                
                $response['success'] = true;
                $response['message'] = "Thank you for your message, " . htmlspecialchars($name) . ". We'll get back to you soon at " . htmlspecialchars($email) . ".";
                
                // Optional: Send email notification to admin (uncomment if needed)
                /*
                $admin_email = 'region67.cebupo@ncip.gov.ph';
                $subject = 'New Contact Form Submission - NCIP Cebu';
                $email_body = "New contact form submission received:\n\n";
                $email_body .= "Name: " . $name . "\n";
                $email_body .= "Email: " . $email . "\n";
                $email_body .= "Phone: " . ($phone ?: 'Not provided') . "\n";
                $email_body .= "Message: " . $message . "\n";
                $email_body .= "Submission Date: " . date('Y-m-d H:i:s') . "\n";
                $email_body .= "IP Address: " . $client_ip . "\n";
                
                $headers = "From: noreply@ncip.gov.ph\r\n";
                $headers .= "Reply-To: " . $email . "\r\n";
                $headers .= "X-Mailer: PHP/" . phpversion();
                
                mail($admin_email, $subject, $email_body, $headers);
                */
                
            } else {
                $pdo->rollBack();
                $response['message'] = "There was a problem saving your message. Please try again.";
                
                // Log database error
                error_log("Contact form submission failed - statement execution failed");
            }
            
        } catch (PDOException $e) {
            // Rollback transaction on error
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            
            error_log("Contact form PDO error: " . $e->getMessage() . " | Name: " . $name . " | Email: " . $email . " | IP: " . $client_ip);
            
            // Log database error
            try {
                $error_log_query = "INSERT INTO USER_LOGS (USER_ID, LOG_TYPE, LOG_DETAILS, LOG_IP_ADDRESS) 
                                   VALUES (NULL, 'CONTACT_FORM_ERROR', :details, :ip_address)";
                $error_log_stmt = $pdo->prepare($error_log_query);
                $error_details = "Contact form database error | Name: " . $name . " | Email: " . $email . " | Error: " . $e->getMessage();
                $error_log_stmt->bindParam(":details", $error_details);
                $error_log_stmt->bindParam(":ip_address", $client_ip);
                $error_log_stmt->execute();
            } catch (Exception $log_error) {
                error_log("Failed to log contact form database error: " . $log_error->getMessage());
            }
            
            $response['message'] = "We're experiencing technical difficulties. Please try again later or contact us directly at region67.cebupo@ncip.gov.ph";
            
        } catch (Exception $e) {
            // Rollback transaction on error
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            
            error_log("Contact form general error: " . $e->getMessage() . " | Name: " . $name . " | Email: " . $email . " | IP: " . $client_ip);
            
            // Log general error
            try {
                $error_log_query = "INSERT INTO USER_LOGS (USER_ID, LOG_TYPE, LOG_DETAILS, LOG_IP_ADDRESS) 
                                   VALUES (NULL, 'CONTACT_FORM_UNEXPECTED_ERROR', :details, :ip_address)";
                $error_log_stmt = $pdo->prepare($error_log_query);
                $error_details = "Contact form unexpected error | Name: " . $name . " | Email: " . $email . " | Error: " . $e->getMessage();
                $error_log_stmt->bindParam(":details", $error_details);
                $error_log_stmt->bindParam(":ip_address", $client_ip);
                $error_log_stmt->execute();
            } catch (Exception $log_error) {
                error_log("Failed to log contact form unexpected error: " . $log_error->getMessage());
            }
            
            $response['message'] = "An unexpected error occurred. Please contact us directly at region67.cebupo@ncip.gov.ph";
        }
        
    } else {
        // Return validation errors
        $response['message'] = implode('<br>', $errors);
        
        // Log validation errors
        try {
            $validation_log_query = "INSERT INTO USER_LOGS (USER_ID, LOG_TYPE, LOG_DETAILS, LOG_IP_ADDRESS) 
                                    VALUES (NULL, 'CONTACT_FORM_VALIDATION_ERROR', :details, :ip_address)";
            $validation_log_stmt = $pdo->prepare($validation_log_query);
            $validation_details = "Contact form validation failed | Name: " . $name . " | Email: " . $email . " | Errors: " . implode(', ', $errors);
            $validation_log_stmt->bindParam(":details", $validation_details);
            $validation_log_stmt->bindParam(":ip_address", $client_ip);
            $validation_log_stmt->execute();
        } catch (Exception $log_error) {
            error_log("Failed to log contact form validation error: " . $log_error->getMessage());
        }
    }
    
} else {
    $response['message'] = "Invalid request method";
    
    // Log invalid request
    try {
        $invalid_log_query = "INSERT INTO USER_LOGS (USER_ID, LOG_TYPE, LOG_DETAILS, LOG_IP_ADDRESS) 
                             VALUES (NULL, 'CONTACT_FORM_INVALID_REQUEST', :details, :ip_address)";
        $invalid_log_stmt = $pdo->prepare($invalid_log_query);
        $invalid_details = "Invalid request method for contact form | Method: " . $_SERVER['REQUEST_METHOD'];
        $invalid_log_stmt->bindParam(":details", $invalid_details);
        $invalid_log_stmt->bindParam(":ip_address", $client_ip);
        $invalid_log_stmt->execute();
    } catch (Exception $log_error) {
        error_log("Failed to log invalid contact form request: " . $log_error->getMessage());
    }
}

// Handle response based on request type
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    // AJAX request - send JSON response
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
} else {
    // Regular form submission - redirect with session message
    if ($response['success']) {
        $_SESSION['contact_success'] = $response['message'];
        unset($_SESSION['contact_error']); // Clear any previous errors
    } else {
        $_SESSION['contact_error'] = $response['message'];
        unset($_SESSION['contact_success']); // Clear any previous success
    }
    
    // Redirect back to contact page
    header('Location: contact.php');
    exit;
}
?>