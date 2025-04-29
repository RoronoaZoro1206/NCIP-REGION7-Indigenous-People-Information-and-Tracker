<?php
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
    $name = isset($_POST['yit_contact']['name']) ? trim(htmlspecialchars($_POST['yit_contact']['name'])) : '';
    $email = isset($_POST['yit_contact']['email']) ? trim(filter_var($_POST['yit_contact']['email'], FILTER_SANITIZE_EMAIL)) : '';
    $phone = isset($_POST['yit_contact']['phone']) ? trim(htmlspecialchars($_POST['yit_contact']['phone'])) : '';
    $message = isset($_POST['yit_contact']['message']) ? trim(htmlspecialchars($_POST['yit_contact']['message'])) : '';
    
    // Validate required fields
    $errors = [];
    
    if (empty($name)) {
        $errors[] = "Name is required";
    }
    
    if (empty($email)) {
        $errors[] = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address";
    }
    
    if (empty($message)) {
        $errors[] = "Message is required";
    }
    
    // Check for honeypot field (anti-spam)
    if (!empty($_POST['yit_bot'])) {
        // This is likely a bot submission
        $response['message'] = "Thank you for your message. We'll get back to you soon.";
        echo json_encode($response);
        exit;
    }
    
    // Process submission if no errors
    if (empty($errors)) {
        try {
            // Prepare SQL statement
            $query = "INSERT INTO contact_messages (CONTM_NAME, CONTM_EMAIL, CONTM_PHONE, CONTM_MESSAGE) 
          VALUES (:name, :email, :phone, :message)";
            
            $stmt = $pdo->prepare($query);
            
            // Bind parameters
            $stmt->bindParam(':name', $name);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':phone', $phone);
            $stmt->bindParam(':message', $message);
            
            // Execute the statement
            if ($stmt->execute()) {
                $response['success'] = true;
                $response['message'] = "Thank you for your message. We'll get back to you soon.";
                
                // Optional: Send email notification to admin
                // mail('admin@example.com', 'New Contact Form Submission', "Name: $name\nEmail: $email\nPhone: $phone\nMessage: $message");
            } else {
                $response['message'] = "There was a problem saving your message. Please try again.";
            }
        } catch (PDOException $e) {
            $response['message'] = "Database error: " . $e->getMessage();
        }
    } else {
        // Return validation errors
        $response['message'] = implode('<br>', $errors);
    }
} else {
    $response['message'] = "Invalid request method";
}

// Send JSON response if it's an AJAX request
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
} else {
    // Redirect if it's a regular form submission
    if ($response['success']) {
        $_SESSION['contact_message'] = $response['message'];
        $_SESSION['contact_success'] = true;
    } else {
        $_SESSION['contact_message'] = $response['message'];
        $_SESSION['contact_success'] = false;
    }
    
    // Redirect back to contact page
    header('Location: contact.php');
    exit;
}