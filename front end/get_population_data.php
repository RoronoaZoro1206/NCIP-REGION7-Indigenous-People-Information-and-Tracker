<?php
header('Content-Type: application/json');
require 'db.php';

try {
    // Modified query to exclude soft-deleted records
    // Only show records where both deleted_by and deleted_at are NULL
    $stmt = $pdo->query("
        SELECT popt_name, popt_latitude, popt_longitude, popt_population, popt_ip_group 
        FROM population_table 
        WHERE (popt_deleted_by IS NULL AND popt_deleted_at IS NULL)
        ORDER BY popt_name ASC
    ");

    $results = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // Additional validation to ensure we have valid data
        if (!empty($row['popt_name']) && 
            is_numeric($row['popt_latitude']) && 
            is_numeric($row['popt_longitude']) && 
            is_numeric($row['popt_population']) &&
            !empty($row['popt_ip_group'])) {
            
            $results[] = [
                'name' => trim($row['popt_name']),
                'ip_group' => trim($row['popt_ip_group']),
                'lat' => (float)$row['popt_latitude'],
                'lng' => (float)$row['popt_longitude'],
                'population' => (int)$row['popt_population']
            ];
        }
    }

    echo json_encode($results);
} catch (PDOException $e) {
    // Log the error for debugging
    error_log("Database error in get_population_data.php: " . $e->getMessage());
    
    // Return error response
    echo json_encode([
        'error' => 'Failed to retrieve population data',
        'message' => 'Please try again later'
    ]);
}
?>