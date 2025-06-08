<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include "../config/database.php";

// Check if user is logged in
if (!isset($_SESSION["user_id"])) {
    header("HTTP/1.0 403 Forbidden");
    exit();
}

// Check if ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("HTTP/1.0 400 Bad Request");
    exit();
}

$app_code = $_GET['id'];

try {
    // Use lowercase column names to match PostgreSQL schema
    $stmt = $pdo->prepare("SELECT scan_data, scan_filetype, scan_filename FROM signature_scan WHERE app_code = :app_code LIMIT 1");
    $stmt->bindParam(':app_code', $app_code, PDO::PARAM_INT);
    $stmt->execute();
    
    $signature = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Debug logging - FIX: Handle resource properly
    error_log("Debug - App Code: " . $app_code);
    error_log("Debug - Signature found: " . ($signature ? 'Yes' : 'No'));
    if ($signature) {
        $data = $signature['scan_data'];
        if (is_resource($data)) {
            // Convert resource to string first for length calculation
            $dataString = stream_get_contents($data);
            rewind($data); // Reset the stream pointer for later use
            error_log("Debug - Data type: PostgreSQL resource");
            error_log("Debug - Data length: " . strlen($dataString) . " bytes");
        } else {
            error_log("Debug - Data type: " . gettype($data));
            error_log("Debug - Data length: " . strlen($data) . " bytes");
        }
        error_log("Debug - File type: " . $signature['scan_filetype']);
        error_log("Debug - Filename: " . $signature['scan_filename']);
    }
    
    if ($signature && !empty($signature['scan_data'])) {
        // Clear any previous output
        if (ob_get_level()) {
            ob_clean();
        }
        
        // Set appropriate headers
        $contentType = $signature['scan_filetype'] ?: 'image/jpeg';
        header('Content-Type: ' . $contentType);
        header('Content-Disposition: inline; filename="' . ($signature['scan_filename'] ?: 'signature.jpg') . '"');
        header('Cache-Control: private, max-age=86400');
        
        // Handle PostgreSQL bytea data - FIX: Handle resource properly
        $imageData = $signature['scan_data'];
        
        if (is_resource($imageData)) {
            // It's a PostgreSQL bytea resource, read the stream
            echo stream_get_contents($imageData);
        } elseif (is_string($imageData) && strpos($imageData, '\x') === 0) {
            // It's a PostgreSQL hex-encoded bytea string
            echo hex2bin(substr($imageData, 2)); // Remove \x prefix and convert
        } elseif (is_string($imageData) && base64_decode($imageData, true) !== false) {
            // It's base64 encoded
            echo base64_decode($imageData);
        } else {
            // It's already binary data
            echo $imageData;
        }
    } else {
        // Create a simple placeholder image
        header('Content-Type: image/svg+xml');
        echo '<?xml version="1.0" encoding="UTF-8"?>
        <svg width="200" height="100" xmlns="http://www.w3.org/2000/svg">
            <rect width="200" height="100" fill="#f8f9fa" stroke="#dee2e6"/>
            <text x="100" y="50" text-anchor="middle" dy="0.3em" font-family="Arial" font-size="14" fill="#6c757d">No Signature</text>
        </svg>';
    }
} catch (PDOException $e) {
    error_log("Error retrieving signature: " . $e->getMessage());
    header("HTTP/1.0 500 Internal Server Error");
    
    // Create error placeholder image
    header('Content-Type: image/svg+xml');
    echo '<?xml version="1.0" encoding="UTF-8"?>
    <svg width="200" height="100" xmlns="http://www.w3.org/2000/svg">
        <rect width="200" height="100" fill="#ffe6e6" stroke="#ff9999"/>
        <text x="100" y="50" text-anchor="middle" dy="0.3em" font-family="Arial" font-size="12" fill="#cc0000">Error Loading</text>
    </svg>';
}
?>