<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once "../../config/database.php";

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION["user_id"])) {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized access'
    ]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['app_code'], $_POST['new_status'])) {
    $app_code = $_POST['app_code'];
    $new_status = $_POST['new_status'];
    $status_remarks = isset($_POST['status_remarks']) ? $_POST['status_remarks'] : '';
    
    // Validate status
    if (!in_array($new_status, ['PENDING', 'APPROVED', 'REJECTED'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid status provided'
        ]);
        exit();
    }
    
    // Begin transaction
    $pdo->beginTransaction();
    
    try {
        // Get the current status before updating
        $current_status_query = "SELECT ss.status_code 
                               FROM scholarship_status ss 
                               WHERE ss.app_code = :app_code AND ss.status_is_active = TRUE 
                               LIMIT 1";
        $current_status_stmt = $pdo->prepare($current_status_query);
        $current_status_stmt->bindParam(":app_code", $app_code);
        $current_status_stmt->execute();
        $current_status_result = $current_status_stmt->fetch(PDO::FETCH_ASSOC);
        $old_status = $current_status_result ? strtoupper($current_status_result['status_code']) : 'PENDING';
        
        // First, set all current statuses for this applicant to inactive
        $update_old = $pdo->prepare("UPDATE scholarship_status SET status_is_active = FALSE 
                                   WHERE app_code = :app_code");
        $update_old->bindParam(":app_code", $app_code);
        $update_old->execute();
        
        // Insert new status
        $insert = $pdo->prepare("INSERT INTO scholarship_status 
                              (app_code, status_code, status_date, status_is_active, status_remarks) 
                              VALUES (:app_code, :new_status, CURRENT_TIMESTAMP, TRUE, :remarks)");
        $insert->bindParam(":app_code", $app_code);
        $insert->bindParam(":new_status", $new_status);
        $insert->bindParam(":remarks", $status_remarks);
        $insert->execute();
        
        // Get updated status counts with normalization
        $count_status_query = "SELECT 
            CASE 
                WHEN ss.status_code IS NULL OR ss.status_code NOT IN ('PENDING', 'APPROVED', 'REJECTED') 
                THEN 'PENDING' 
                ELSE UPPER(ss.status_code) 
            END as normalized_status,
            COUNT(DISTINCT a.app_code) as count
            FROM applicant a
            LEFT JOIN scholarship_status ss ON a.app_code = ss.app_code AND ss.status_is_active = TRUE
            GROUP BY 
            CASE 
                WHEN ss.status_code IS NULL OR ss.status_code NOT IN ('PENDING', 'APPROVED', 'REJECTED') 
                THEN 'PENDING' 
                ELSE UPPER(ss.status_code) 
            END";

        $count_status_stmt = $pdo->query($count_status_query);
        $status_counts = [
            'all' => 0,
            'pending' => 0,
            'approved' => 0,
            'rejected' => 0
        ];
        
        while ($row = $count_status_stmt->fetch(PDO::FETCH_ASSOC)) {
            $normalized_status = strtolower($row['normalized_status']);
            if (array_key_exists($normalized_status, $status_counts)) {
                $status_counts[$normalized_status] = (int)$row['count'];
                $status_counts['all'] += (int)$row['count'];
            }
        }
        
        // Log the status change
        $log_query = "INSERT INTO user_logs (user_id, log_type, log_details, log_timestamp) 
                     VALUES (:user_id, 'STATUS_UPDATE', :details, CURRENT_TIMESTAMP)";
        $log_stmt = $pdo->prepare($log_query);
        $details = "Updated application #" . $app_code . " status from " . $old_status . " to " . $new_status;
        if (!empty($status_remarks)) {
            $details .= " with remarks: " . $status_remarks;
        }
        $log_stmt->bindParam(":user_id", $_SESSION["user_id"]);
        $log_stmt->bindParam(":details", $details);
        $log_stmt->execute();
        
        $pdo->commit();
        
        // Set badge class based on status
        $badge_class = 'bg-warning text-dark'; // Default (pending)
        if ($new_status === 'APPROVED') {
            $badge_class = 'bg-success';
        } else if ($new_status === 'REJECTED') {
            $badge_class = 'bg-danger';
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Status updated successfully!',
            'old_status' => $old_status,
            'new_status' => $new_status,
            'status' => $new_status,
            'badge_class' => $badge_class,
            'date' => date('M d, Y'),
            'remarks' => $status_remarks,
            'app_code' => $app_code,
            'status_counts' => $status_counts
        ]);
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        echo json_encode([
            'success' => false,
            'message' => 'Database error: ' . $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request parameters'
    ]);
}
?>