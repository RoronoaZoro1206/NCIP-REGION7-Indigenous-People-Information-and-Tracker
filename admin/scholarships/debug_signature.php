<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include "../config/database.php";

$app_code = 1; // Change this to your test app code

echo "<h3>Debug Signature for App Code: $app_code</h3>";

try {
    // Check if signature record exists
    $stmt = $pdo->prepare("SELECT * FROM signature_scan WHERE app_code = :app_code");
    $stmt->bindParam(':app_code', $app_code, PDO::PARAM_INT);
    $stmt->execute();
    
    $signature = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($signature) {
        echo "<p><strong>Record found!</strong></p>";
        echo "<p>Scan ID: " . $signature['scan_id'] . "</p>";
        echo "<p>Filename: " . $signature['scan_filename'] . "</p>";
        echo "<p>File type: " . $signature['scan_filetype'] . "</p>";
        echo "<p>Data length: " . strlen($signature['scan_data']) . " bytes</p>";
        echo "<p>Upload date: " . $signature['scan_upload_date'] . "</p>";
        
        // Try to determine data type
        $data = $signature['scan_data'];
        if (is_resource($data)) {
            echo "<p>Data type: PostgreSQL resource</p>";
            $data = stream_get_contents($data);
            echo "<p>Resource length: " . strlen($data) . " bytes</p>";
        } elseif (strpos($data, '\x') === 0) {
            echo "<p>Data type: PostgreSQL hex string</p>";
        } elseif (base64_decode($data, true) !== false) {
            echo "<p>Data type: Base64 encoded</p>";
        } else {
            echo "<p>Data type: Binary data</p>";
        }
        
        // Show first 100 characters of data
        echo "<p>First 100 chars: " . htmlspecialchars(substr($data, 0, 100)) . "</p>";
        
    } else {
        echo "<p><strong>No signature found for this app code</strong></p>";
        
        // Check if app exists
        $appStmt = $pdo->prepare("SELECT app_code FROM applicant WHERE app_code = :app_code");
        $appStmt->bindParam(':app_code', $app_code, PDO::PARAM_INT);
        $appStmt->execute();
        
        if ($appStmt->fetch()) {
            echo "<p>Application exists but no signature</p>";
        } else {
            echo "<p>Application does not exist</p>";
        }
    }
    
} catch (PDOException $e) {
    echo "<p><strong>Error: " . $e->getMessage() . "</strong></p>";
}
?>