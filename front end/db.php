<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$host = "localhost";     
$dbname = "Ncip_Project"; 
$dbusername = "postgres"; 
$dbpassword = "!*Lawrence1206"; 

try {
    $pdo = new PDO ("pgsql:host=$host;dbname=$dbname", $dbusername, $dbpassword );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("SET TIME ZONE INTERVAL '+08:00'");
} catch (PDOException $e) {
    die("Connection failed with error: ". $e->getMessage());
}
