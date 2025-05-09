<?php
// verify-images.php
// This is a utility script to verify all image paths

// Define the same image mappings you use in your application
$image_mapping = [
    1 => 'images/blog/advisory-75x75.png',
    2 => 'images/blog/Makasaysayang-75x75.png',
    3 => 'images/blog/kababaihan-75x75.png',
    4 => 'images/blog/Dumalo-75x75.jpg',
    5 => 'images/blog/workshop-75x75.jpg',
    6 => 'images/blog/tagapanayam-75x75.jpg',
    7 => 'images/blog/ginawaran-75x75.jpg',
    8 => 'images/blog/walong-75x75.jpg',
    9 => 'images/blog/pinarangala-75x75.jpg',
    10 => 'images/blog/published-75x75.jpg',
    11 => 'images/blog/oes-75x75.jpg',
    12 => 'images/blog/un-75x75.jpg',
    13 => 'images/blog/katutubo-75x75.jpg',
    14 => 'images/blog/partnership-75x75.jpg',
    15 => 'images/blog/condemns-75x75.jpg',
];

echo "<h2>Image Path Verification</h2>";
echo "<table border='1'><tr><th>ID</th><th>Path</th><th>Status</th><th>All Checks</th></tr>";

foreach ($image_mapping as $id => $path) {
    // Try different path variations
    $paths_to_check = [
        ['path' => $_SERVER['DOCUMENT_ROOT'] . '/' . $path, 'type' => 'Document Root + Path'],
        ['path' => __DIR__ . '/' . $path, 'type' => 'Current Dir + Path'],
        ['path' => dirname(__FILE__) . '/' . $path, 'type' => 'dirname(__FILE__) + Path'],
        ['path' => realpath('./') . '/' . $path, 'type' => 'realpath(./]) + Path'],
        ['path' => './' . $path, 'type' => './ + Path'],
        ['path' => '../' . $path, 'type' => '../ + Path']
    ];

    $exists = false;
    $found_location = "";
    $all_checks = "";

    foreach ($paths_to_check as $check) {
        $check_result = file_exists($check['path']) ? "✅" : "❌";
        $all_checks .= "<div>{$check['type']}: {$check_result} {$check['path']}</div>";
        
        if (file_exists($check['path']) && !$exists) {
            $exists = true;
            $found_location = $check['type'];
        }
    }
    
    // Standard document root check for the table
    $full_path = $_SERVER['DOCUMENT_ROOT'] . '/' . $path;
    $standard_exists = file_exists($full_path);
    $status = $standard_exists ? "✅ Found" : "❌ Missing";
    
    // Add note about where it was found if not in standard location
    if (!$standard_exists && $exists) {
        $status = "⚠️ Found elsewhere: " . $found_location;
    }
    
    echo "<tr>";
    echo "<td>$id</td>";
    echo "<td>$path</td>";
    echo "<td style='color:" . ($standard_exists ? "green" : ($exists ? "orange" : "red")) . "'>$status</td>";
    echo "<td>$all_checks</td>";
    echo "</tr>";
}

echo "</table>";

echo "<h2>Recommended Fix for Image Paths</h2>";
echo "<p>Based on the verification results above, here are the image paths you should use in your code:</p>";
echo "<pre>";
echo "function getAnnouncementImage(\$ann_id) {
    // Define image paths for all announcements
    \$image_mapping = [";

foreach ($image_mapping as $id => $path) {
    $paths_to_check = [
        ['path' => $_SERVER['DOCUMENT_ROOT'] . '/' . $path, 'type' => 'Standard'],
        ['path' => __DIR__ . '/' . $path, 'type' => '__DIR__'],
        ['path' => dirname(__FILE__) . '/' . $path, 'type' => 'dirname(__FILE__)'],
        ['path' => realpath('./') . '/' . $path, 'type' => 'realpath(./)'],
        ['path' => './' . $path, 'type' => './'],
        ['path' => '../' . $path, 'type' => '../']
    ];

    $found = false;
    $found_type = "";

    foreach ($paths_to_check as $check) {
        if (file_exists($check['path'])) {
            $found = true;
            $found_type = $check['type'];
            break;
        }
    }

    $recommended_path = $path;
    if ($found && $found_type != 'Standard') {
        // Adjust path based on where it was found
        switch ($found_type) {
            case '__DIR__':
            case 'dirname(__FILE__)':
            case 'realpath(./])':
                $recommended_path = $path; // Keep as is for now
                break;
            case './':
                $recommended_path = './' . $path;
                break;
            case '../':
                $recommended_path = '../' . $path;
                break;
        }
    }

    echo "
        $id => '$recommended_path',";
}

echo "
    ];
    
    \$image_path = \$image_mapping[\$ann_id] ?? 'images/default-thumbnail.jpg';
    
    // Create a more robust file check
    \$paths_to_check = [
        \$_SERVER['DOCUMENT_ROOT'] . '/' . \$image_path,
        __DIR__ . '/' . \$image_path,
        dirname(__FILE__) . '/' . \$image_path,
        './' . \$image_path,
        '../' . \$image_path
    ];
    
    \$exists = false;
    foreach (\$paths_to_check as \$path) {
        if (file_exists(\$path)) {
            \$exists = true;
            break;
        }
    }
    
    if (!\$exists) {
        return 'images/default-thumbnail.jpg';
    }
    
    return \$image_path;
}";
echo "</pre>";

// Also provide a recommendation to create missing files
echo "<h2>Missing Files Recommendation</h2>";
echo "<p>If files are missing in all locations, you should create thumbnails for these images:</p>";
echo "<ul>";
foreach ($image_mapping as $id => $path) {
    $exists = false;
    
    $paths_to_check = [
        $_SERVER['DOCUMENT_ROOT'] . '/' . $path,
        __DIR__ . '/' . $path,
        dirname(__FILE__) . '/' . $path,
        realpath('./') . '/' . $path,
        './' . $path,
        '../' . $path
    ];
    
    foreach ($paths_to_check as $check_path) {
        if (file_exists($check_path)) {
            $exists = true;
            break;
        }
    }
    
    if (!$exists) {
        echo "<li>Create thumbnail: $path</li>";
    }
}
echo "</ul>";

// Check if the default image exists
$default_exists = file_exists($_SERVER['DOCUMENT_ROOT'] . '/images/default-thumbnail.jpg');
if (!$default_exists) {
    echo "<p style='color: red; font-weight: bold;'>WARNING: Your default fallback image 'images/default-thumbnail.jpg' does not exist! Create this file to prevent broken images.</p>";
}
?>