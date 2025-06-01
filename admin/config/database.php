<?php
// Database connection settings for PostgreSQL
$host = "localhost";     
$dbname = "Ncip_Project"; 
$dbusername = "postgres"; 
$dbpassword = "!*Lawrence1206"; 

try {
    $pdo = new PDO("pgsql:host=$host;dbname=$dbname", $dbusername, $dbpassword);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec("SET TIME ZONE INTERVAL '+08:00'");
    
    // Create a config.php symlink/reference to avoid errors in existing code
    if (!file_exists(__DIR__ . '/config.php')) {
        file_put_contents(__DIR__ . '/config.php', '<?php
// This file redirects to database.php to maintain compatibility
require_once __DIR__ . \'/database.php\';
?>');
    }
    
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
?>