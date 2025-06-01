<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include "../config/database.php";

// Check if user is logged in and has admin role
if (!isset($_SESSION["user_id"]) || !isset($_SESSION["role"]) || strtoupper($_SESSION["role"]) !== 'ADMIN') {
    header("Location: ../login.php");
    exit();
}

// Fetch user profile info including profile picture
try {
    $user_stmt = $pdo->prepare("SELECT user_username, user_profile_picture FROM users WHERE user_id = :user_id");
    $user_stmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
    $user_stmt->execute();
    $user = $user_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Update session with latest user info
    if ($user) {
        $_SESSION['username'] = $user['user_username'];
        $_SESSION['profile_picture'] = $user['user_profile_picture'] ?? 'default.png';
    }
} catch (PDOException $e) {
    error_log("Failed to fetch user data: " . $e->getMessage());
}

// Initialize variables
$error_message = "";
$success = false;

// Get existing IP groups for the dropdown
try {
    $groups_query = "SELECT DISTINCT POPT_IP_GROUP FROM population_table ORDER BY POPT_IP_GROUP";
    $groups_stmt = $pdo->query($groups_query);
    $existing_ip_groups = $groups_stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $error_message = "Error fetching IP groups: " . $e->getMessage();
    $existing_ip_groups = [];
}

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Collect and validate form data
    $name = trim($_POST['name'] ?? '');
    $ip_group = strtoupper(trim($_POST['ip_group'] ?? ''));
    $latitude = floatval($_POST['latitude'] ?? 0);
    $longitude = floatval($_POST['longitude'] ?? 0);
    $population = intval($_POST['population'] ?? 0);
    
    // Validate inputs
    $errors = [];
    
    if (empty($name)) {
        $errors[] = "Location name is required";
    }
    
    if (empty($ip_group)) {
        $errors[] = "IP Group is required";
    }
    
    if ($latitude < -90 || $latitude > 90) {
        $errors[] = "Latitude must be between -90 and 90";
    }
    
    if ($longitude < -180 || $longitude > 180) {
        $errors[] = "Longitude must be between -180 and 180";
    }
    
    if ($population <= 0) {
        $errors[] = "Population must be greater than 0";
    }
    
    // Check for duplicate location
    try {
        $check_query = "SELECT COUNT(*) FROM population_table WHERE POPT_NAME = ? AND POPT_IP_GROUP = ?";
        $check_stmt = $pdo->prepare($check_query);
        $check_stmt->execute([$name, $ip_group]);
        $exists = $check_stmt->fetchColumn();
        
        if ($exists > 0) {
            $errors[] = "A location with this name and IP group already exists";
        }
    } catch (PDOException $e) {
        $errors[] = "Error checking for duplicates: " . $e->getMessage();
    }
    
    if (empty($errors)) {
        try {
            $insert_query = "INSERT INTO population_table (POPT_NAME, POPT_IP_GROUP, POPT_LATITUDE, POPT_LONGITUDE, POPT_POPULATION) 
                            VALUES (?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($insert_query);
            $success = $stmt->execute([$name, $ip_group, $latitude, $longitude, $population]);
            
            if ($success) {
                $_SESSION['success_message'] = "Population data added successfully";
                header("Location: index.php");
                exit();
            } else {
                $error_message = "Failed to add population data";
            }
        } catch (PDOException $e) {
            $error_message = "Database error: " . $e->getMessage();
        }
    } else {
        $error_message = implode("<br>", $errors);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Population Data - NCIP Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
    <style>
        body {
            background-color: #f8f9fa;
        }
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            width: 250px;
            background-color: #212529;
            padding-top: 20px;
            z-index: 100;
        }
        .sidebar-heading {
            padding: 0.875rem 1.25rem;
            font-size: 1.2rem;
            color: white;
        }
        .sidebar-menu {
            list-style: none;
            padding: 0;
        }
        .sidebar-menu li {
            width: 100%;
        }
        .sidebar-menu li a {
            display: block;
            padding: 0.75rem 1.25rem;
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
        }
        .sidebar-menu li a:hover {
            color: #fff;
            background-color: rgba(255, 255, 255, 0.1);
        }
        .sidebar-menu li a i {
            margin-right: 10px;
        }
        .active {
            background-color: #0d6efd;
            color: white !important;
        }
        .main-content {
            margin-left: 250px;
            padding: 20px;
        }
        .navbar {
            margin-left: 250px;
        }
        #map {
            height: 400px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .coordinate-section {
            background-color: #f9f9f9;
            padding: 15px;
            border-radius: 8px;
            border: 1px solid #ddd;
        }
        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
                display: none;
            }
            .sidebar.show-mobile {
                display: block;
            }
            .main-content {
                margin-left: 0;
            }
            .navbar {
                margin-left: 0;
            }
        }
    </style>
</head>

<body>
    <?php include '../includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-0 text-gray-800">Add Population Data</h1>
                    <p class="mb-0">Add new indigenous peoples location and population information</p>
                </div>
                <div>
                    <a href="index.php" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i> Back to List
                    </a>
                </div>
            </div>

            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $error_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <div class="card shadow">
                <div class="card-body">
                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" id="populationForm">
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="name" class="form-label">Location Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="name" name="name" required 
                                           value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>">
                                    <div class="form-text">Enter the complete name of the location (e.g., "Sitio Naba Alaska, Brgy. Mambaling, Cebu City")</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="ip_group" class="form-label">IP Group <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="ip_group" name="ip_group" list="ipGroupsList" required
                                           value="<?php echo isset($_POST['ip_group']) ? htmlspecialchars($_POST['ip_group']) : ''; ?>">
                                    <datalist id="ipGroupsList">
                                        <?php foreach ($existing_ip_groups as $group): ?>
                                            <option value="<?php echo htmlspecialchars($group); ?>">
                                        <?php endforeach; ?>
                                    </datalist>
                                    <div class="form-text">Select an existing IP group or enter a new one</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="population" class="form-label">Population <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" id="population" name="population" min="1" required
                                           value="<?php echo isset($_POST['population']) ? intval($_POST['population']) : ''; ?>">
                                    <div class="form-text">Enter the total number of individuals in this location</div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <h5>Select Location on Map</h5>
                                <p class="text-muted small">Click on the map to set the coordinates or enter them manually below.</p>
                                
                                <div id="map"></div>
                                
                                <div class="coordinate-section">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="latitude" class="form-label">Latitude <span class="text-danger">*</span></label>
                                                <input type="number" class="form-control" id="latitude" name="latitude" 
                                                       step="0.000001" min="-90" max="90" required
                                                       value="<?php echo isset($_POST['latitude']) ? floatval($_POST['latitude']) : '10.3157'; ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="longitude" class="form-label">Longitude <span class="text-danger">*</span></label>
                                                <input type="number" class="form-control" id="longitude" name="longitude" 
                                                       step="0.000001" min="-180" max="180" required
                                                       value="<?php echo isset($_POST['longitude']) ? floatval($_POST['longitude']) : '123.8854'; ?>">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="text-end">
                                        <button type="button" id="updateMapBtn" class="btn btn-sm btn-outline-primary">
                                            Update Map Marker
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <a href="index.php" class="btn btn-light me-md-2">Cancel</a>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save"></i> Save Population Data
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap & Leaflet JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Sidebar toggle functionality
            const sidebarToggle = document.getElementById('sidebarToggle');
            if (sidebarToggle) {
                sidebarToggle.addEventListener('click', function() {
                    document.querySelector('.sidebar').classList.toggle('show-mobile');
                });
            }

            // Initialize the map
            const map = L.map('map', {
                maxBounds: [
                    [4.5, 115],
                    [21.5, 130]
                ],
                maxBoundsViscosity: 1.0,
                minZoom: 5,
                maxZoom: 18
            }).setView([
                parseFloat(document.getElementById('latitude').value) || 10.3157,
                parseFloat(document.getElementById('longitude').value) || 123.8854
            ], 10);
            
            // Add the base map layer
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
            }).addTo(map);
            
            // Add a marker at the initial coordinates
            let marker = L.marker([
                parseFloat(document.getElementById('latitude').value) || 10.3157,
                parseFloat(document.getElementById('longitude').value) || 123.8854
            ], {
                draggable: true
            }).addTo(map);

            // Update coordinates when marker is dragged
            marker.on('dragend', function(event) {
                const position = marker.getLatLng();
                document.getElementById('latitude').value = position.lat.toFixed(6);
                document.getElementById('longitude').value = position.lng.toFixed(6);
            });

            // Update marker position when coordinates are manually entered
            document.getElementById('updateMapBtn').addEventListener('click', function() {
                const lat = parseFloat(document.getElementById('latitude').value);
                const lng = parseFloat(document.getElementById('longitude').value);
                
                if (!isNaN(lat) && !isNaN(lng)) {
                    marker.setLatLng([lat, lng]);
                    map.setView([lat, lng], map.getZoom());
                }
            });

            // Add click event to the map to set the marker position
            map.on('click', function(e) {
                marker.setLatLng(e.latlng);
                document.getElementById('latitude').value = e.latlng.lat.toFixed(6);
                document.getElementById('longitude').value = e.latlng.lng.toFixed(6);
            });

            // Form validation
            document.getElementById('populationForm').addEventListener('submit', function(event) {
                const lat = parseFloat(document.getElementById('latitude').value);
                const lng = parseFloat(document.getElementById('longitude').value);
                const pop = parseInt(document.getElementById('population').value);
                
                let isValid = true;
                let errorMsg = '';
                
                if (isNaN(lat) || lat < -90 || lat > 90) {
                    errorMsg += "Latitude must be between -90 and 90\n";
                    isValid = false;
                }
                
                if (isNaN(lng) || lng < -180 || lng > 180) {
                    errorMsg += "Longitude must be between -180 and 180\n";
                    isValid = false;
                }
                
                if (isNaN(pop) || pop <= 0) {
                    errorMsg += "Population must be a positive number\n";
                    isValid = false;
                }
                
                if (!isValid) {
                    alert(errorMsg);
                    event.preventDefault();
                }
            });

            // Ensure map is properly rendered even after sidebar toggle
            setTimeout(function() {
                map.invalidateSize();
            }, 100);

            // Handle window resize to maintain map integrity
            window.addEventListener('resize', function() {
                map.invalidateSize();
            });
        });
    </script>
</body>
</html>