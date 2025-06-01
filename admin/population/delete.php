<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include "../config/database.php";

// Check if request is AJAX
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

// Check if user is logged in and has admin role
if (!isset($_SESSION["user_id"]) || !isset($_SESSION["role"]) || strtoupper($_SESSION["role"]) !== 'ADMIN') {
    if ($isAjax) {
        echo json_encode(['success' => false, 'message' => 'Authentication required']);
        exit;
    } else {
        header("Location: ../login.php");
        exit();
    }
}

// Function to validate and sanitize input
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Check if ID is provided
if (!isset($_POST['id']) || empty($_POST['id'])) {
    if ($isAjax) {
        echo json_encode(['success' => false, 'message' => 'No population ID specified']);
        exit;
    } else {
        $_SESSION['error_message'] = "No population ID specified.";
        header("Location: index.php");
        exit();
    }
}

$population_id = (int)sanitize_input($_POST['id']);

try {
    // Begin transaction
    $pdo->beginTransaction();

    // First check if the population exists
    $check_query = "SELECT popt_name, popt_ip_group FROM population_table WHERE popt_id = ?";
    $check_stmt = $pdo->prepare($check_query);
    $check_stmt->execute([$population_id]);
    $population_data = $check_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$population_data) {
        $pdo->rollBack();
        if ($isAjax) {
            echo json_encode(['success' => false, 'message' => 'Population record not found']);
            exit;
        } else {
            $_SESSION['error_message'] = "Population record not found.";
            header("Location: index.php");
            exit();
        }
    }

    // Check if this is the highest ID (last record) before deleting
    $max_id_query = "SELECT MAX(popt_id) as max_id FROM population_table";
    $max_id_stmt = $pdo->query($max_id_query);
    $max_id = $max_id_stmt->fetchColumn();
    $is_last_record = ($population_id == $max_id);

    // Delete the population record
    $delete_query = "DELETE FROM population_table WHERE popt_id = ?";
    $delete_stmt = $pdo->prepare($delete_query);
    $delete_stmt->execute([$population_id]);
    
    // If this was the last record (highest ID), reset the auto-increment counter
    if ($is_last_record) {
        // Get current max ID after deletion
        $new_max_query = "SELECT COALESCE(MAX(popt_id), 0) as current_max_id FROM population_table";
        $new_max_stmt = $pdo->query($new_max_query);
        $new_max_id = $new_max_stmt->fetchColumn();
        
        // Reset auto-increment to the current max ID
        $reset_query = "ALTER SEQUENCE population_table_popt_id_seq RESTART WITH " . ($new_max_id + 1);
        $pdo->exec($reset_query);
    }
    
    // Commit the transaction
    $pdo->commit();
    
    // Try to log the activity (outside the main transaction so it doesn't affect deletion)
    try {
        $activity_details = "Deleted population record: " . $population_data['popt_name'] . 
                          " (ID: " . $population_id . ", IP Group: " . $population_data['popt_ip_group'] . ")";
        $ip_address = $_SERVER['REMOTE_ADDR'];
        
        // Log activity if the table exists
        try {
            $log_query = "INSERT INTO activity_logs (user_id, activity_type, activity_details, ip_address)
                         VALUES (?, 'DELETE', ?, ?)";
            $log_stmt = $pdo->prepare($log_query);
            $log_stmt->execute([$_SESSION["user_id"], $activity_details, $ip_address]);
        } catch (PDOException $log_error) {
            // Just ignore logging errors - deletion was successful
        }
    } catch (PDOException $log_error) {
        // Just ignore logging errors - deletion was successful
    }
    
    if ($isAjax) {
        echo json_encode([
            'success' => true, 
            'message' => 'Population record deleted successfully',
            'population_id' => $population_id,
            'population_name' => $population_data['popt_name'],
            'id_reset' => $is_last_record
        ]);
        exit;
    } else {
        $_SESSION['success_message'] = "Population record deleted successfully.";
        header("Location: index.php");
        exit();
    }

} catch (PDOException $e) {
    // Roll back the transaction if something failed
    $pdo->rollBack();
    
    if ($isAjax) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        exit;
    } else {
        $_SESSION['error_message'] = "Error: " . $e->getMessage();
        header("Location: index.php");
        exit();
    }
}
?>