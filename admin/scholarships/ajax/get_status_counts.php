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

try {
    // Get status counts with normalization
    $status_counts = [
        'all' => 0,
        'pending' => 0,
        'approved' => 0,
        'rejected' => 0
    ];

    $count_status_query = "SELECT 
        CASE 
            WHEN ss.STATUS_CODE IS NULL OR ss.STATUS_CODE NOT IN ('PENDING', 'APPROVED', 'REJECTED') 
            THEN 'PENDING' 
            ELSE UPPER(ss.STATUS_CODE) 
        END as normalized_status,
        COUNT(DISTINCT a.APP_CODE) as count
        FROM APPLICANT a
        LEFT JOIN SCHOLARSHIP_STATUS ss ON a.APP_CODE = ss.APP_CODE AND ss.STATUS_IS_ACTIVE = TRUE
        GROUP BY 
        CASE 
            WHEN ss.STATUS_CODE IS NULL OR ss.STATUS_CODE NOT IN ('PENDING', 'APPROVED', 'REJECTED') 
            THEN 'PENDING' 
            ELSE UPPER(ss.STATUS_CODE) 
        END";

    $count_status_stmt = $pdo->query($count_status_query);
    while ($row = $count_status_stmt->fetch(PDO::FETCH_ASSOC)) {
        $normalized_status = strtolower($row['normalized_status']);
        if (array_key_exists($normalized_status, $status_counts)) {
            $status_counts[$normalized_status] = $row['count'];
            $status_counts['all'] += $row['count'];
        }
    }

    echo json_encode([
        'success' => true,
        'counts' => $status_counts
    ]);

} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>