<?php
header('Content-Type: application/json');
require 'db.php';

try {
    $stmt = $pdo->query("SELECT popt_name, popt_latitude, popt_longitude, popt_population, popt_ip_group FROM population_table");

    $results = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $results[] = [
            'name' => $row['popt_name'],
            'ip_group' => $row['popt_ip_group'],
            'lat' => (float)$row['popt_latitude'],
            'lng' => (float)$row['popt_longitude'],
            'population' => (float)$row['popt_population']
            
        ];
    }

    echo json_encode($results);
} catch (PDOException $e) {
    echo json_encode(['error' => 'Query failed: ' . $e->getMessage()]);
}
?>