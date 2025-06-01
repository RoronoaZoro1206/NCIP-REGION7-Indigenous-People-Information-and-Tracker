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

// Check if ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error_message'] = "No population ID specified";
    header("Location: index.php");
    exit();
}

$id = (int)$_GET['id'];

// Get existing IP groups for the dropdown
try {
    $groups_query = "SELECT DISTINCT POPT_IP_GROUP FROM population_table ORDER BY POPT_IP_GROUP";
    $groups_stmt = $pdo->query($groups_query);
    $existing_ip_groups = $groups_stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Error fetching IP groups: " . $e->getMessage();
    header("Location: index.php");
    exit();
}

// Get the population data to edit
try {
    $query = "SELECT * FROM population_table WHERE POPT_ID = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$id]);
    $population = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$population) {
        $_SESSION['error_message'] = "Population data not found";
        header("Location: index.php");
        exit();
    }
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Error fetching population data: " . $e->getMessage();
    header("Location: index.php");
    exit();
}

// Initialize variables
$error_message = "";
$success = false;

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
    
    // Check for duplicate location (excluding current record)
    try {
        $check_query = "SELECT COUNT(*) FROM population_table WHERE POPT_NAME = ? AND POPT_IP_GROUP = ? AND POPT_ID != ?";
        $check_stmt = $pdo->prepare($check_query);
        $check_stmt->execute([$name, $ip_group, $id]);
        $exists = $check_stmt->fetchColumn();
        
        if ($exists > 0) {
            $errors[] = "Another location with this name and IP group already exists";
        }
    } catch (PDOException $e) {
        $errors[] = "Error checking for duplicates: " . $e->getMessage();
    }
    
    if (empty($errors)) {
        try {
            $update_query = "UPDATE population_table 
                             SET POPT_NAME = ?, POPT_IP_GROUP = ?, POPT_LATITUDE = ?, POPT_LONGITUDE = ?, POPT_POPULATION = ? 
                             WHERE POPT_ID = ?";
            $stmt = $pdo->prepare($update_query);
            $success = $stmt->execute([$name, $ip_group, $latitude, $longitude, $population, $id]);
            
            if ($success) {
                $_SESSION['success_message'] = "Population data updated successfully";
                header("Location: index.php");
                exit();
            } else {
                $error_message = "Failed to update population data";
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
    <title>Edit Population Data - NCIP Admin</title>
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

        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            width: 250px;
            background-color: #212529;
            padding-top: 20px;
            overflow-y: auto;
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
        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
            }
            .main-content {
                margin-left: 0;
            }
            .navbar {
                margin-left: 0;
            }
        }
        /* Legend styles */
        .info.legend {
            background: white;
            padding: 10px;
            border-radius: 5px;
            box-shadow: 0 0 15px rgba(0,0,0,0.2);
        }
        .info.legend i {
            width: 18px;
            height: 18px;
            float: left;
            margin-right: 8px;
            opacity: 0.7;
        }
        .info.legend h6 {
            margin-top: 0;
            font-weight: bold;
        }
    </style>
</head>

<body>
    <!-- Sidebar -->
    <?php include '../includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-0 text-gray-800">Edit Population Data</h1>
                    <p class="mb-0">Update indigenous peoples location and population information</p>
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
                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"] . "?id={$id}"); ?>" method="post" id="populationForm">
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="name" class="form-label">Location Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="name" name="name" required 
                                           value="<?php echo htmlspecialchars($population['popt_name']); ?>">
                                    <div class="form-text">Enter the complete name of the location (e.g., "Sitio Naba Alaska, Brgy. Mambaling, Cebu City")</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="ip_group" class="form-label">IP Group <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="ip_group" name="ip_group" list="ipGroupsList" required
                                            value="<?php echo htmlspecialchars($population['popt_ip_group']); ?>">
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
                                           value="<?php echo intval($population['popt_population']); ?>">
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
                                                       value="<?php echo number_format($population['popt_latitude'], 6); ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="longitude" class="form-label">Longitude <span class="text-danger">*</span></label>
                                                <input type="number" class="form-control" id="longitude" name="longitude" 
                                                       step="0.000001" min="-180" max="180" required
                                                       value="<?php echo number_format($population['popt_longitude'], 6); ?>">
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
                                <i class="bi bi-save"></i> Update Population Data
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
            // Create a small delay to ensure DOM is fully loaded and styled
            setTimeout(function() {
                // Initialize Leaflet map
                var map = L.map('map', {
                    maxBounds: [
                        [4.5, 115],
                        [21.5, 130]
                    ],
                    maxBoundsViscosity: 1.0,
                    minZoom: 5,
                    maxZoom: 18
                }).setView([12.8797, 121.7740], 6); // Center on Philippines

                // Force a resize/redraw after map initialization
                map.invalidateSize(true);

                // Add tile layer
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
                }).addTo(map);

                // Add marker for the population being edited
                var lat = parseFloat(<?php echo (float)$population['popt_latitude']; ?>);
                var lng = parseFloat(<?php echo (float)$population['popt_longitude']; ?>);
                var pop = <?php echo (int)$population['popt_population']; ?>;
                
                // Color based on population range
                var color, radius;
                if (pop <= 100) {
                    color = '#0014FF'; // Blue
                    radius = 10;
                } else if (pop <= 300) {
                    color = '#00FFFF'; // Cyan
                    radius = 16;
                } else if (pop <= 600) {
                    color = '#FF6666'; // Light Red
                    radius = 22;
                } else {
                    color = '#C70039'; // Deep Red
                    radius = 30;
                }
                
                // Create draggable marker
                if (!isNaN(lat) && !isNaN(lng)) {
                    // Center the map on the current location
                    map.setView([lat, lng], 10);
                    
                    // Add a draggable marker
                    var marker = L.marker([lat, lng], {
                        draggable: true
                    }).addTo(map);
                    
                    // Add a circle to represent population - STORE THE REFERENCE
                    var circleMarker = L.circleMarker([lat, lng], {
                        radius: radius,
                        fillColor: color,
                        color: '#fff',
                        weight: 1,
                        opacity: 1,
                        fillOpacity: 0.8
                    }).addTo(map)
                    .bindPopup(
                        "<strong>Area:</strong> <?php echo htmlspecialchars($population['popt_name']); ?><br>" +
                        "<strong>IP Group:</strong> <?php echo htmlspecialchars($population['popt_ip_group']); ?><br>" +
                        "<strong>Population:</strong> <?php echo number_format($population['popt_population']); ?>"
                    );
                    
                    // Update coordinates when marker is dragged
                    marker.on('dragend', function(event) {
                        var position = marker.getLatLng();
                        document.getElementById('latitude').value = position.lat.toFixed(6);
                        document.getElementById('longitude').value = position.lng.toFixed(6);
                        
                        // ALSO UPDATE THE CIRCLE MARKER POSITION
                        circleMarker.setLatLng(position);
                    });
                    
                    // Update marker when clicking on map
                    map.on('click', function(e) {
                        marker.setLatLng(e.latlng);
                        document.getElementById('latitude').value = e.latlng.lat.toFixed(6);
                        document.getElementById('longitude').value = e.latlng.lng.toFixed(6);
                        
                        // ALSO UPDATE THE CIRCLE MARKER POSITION
                        circleMarker.setLatLng(e.latlng);
                    });
                    
                    // Update Map button functionality
                    document.getElementById('updateMapBtn').addEventListener('click', function() {
                        var lat = parseFloat(document.getElementById('latitude').value);
                        var lng = parseFloat(document.getElementById('longitude').value);
                        
                        if (!isNaN(lat) && !isNaN(lng)) {
                            marker.setLatLng([lat, lng]);
                            map.setView([lat, lng], 10);
                            
                            // ALSO UPDATE THE CIRCLE MARKER POSITION
                            circleMarker.setLatLng([lat, lng]);
                        }
                    });
                }

                // Add legend
                const legend = L.control({ position: 'bottomright' });
                legend.onAdd = function() {
                    const div = L.DomUtil.create('div', 'info legend');
                    div.innerHTML = '<h6>Population Legend</h6>' +
                        '<i style="background: #0014FF"></i> ≤ 100<br>' +
                        '<i style="background: #00FFFF"></i> 101 - 300<br>' +
                        '<i style="background: #FF6666"></i> 301 - 600<br>' +
                        '<i style="background: #C70039"></i> > 600';
                    
                    return div;
                };
                legend.addTo(map);

                // Handle window resize to maintain map integrity
                window.addEventListener('resize', function() {
                    map.invalidateSize(true);
                });
            }, 100); // Small delay to ensure container is ready

            // Setup delete modal
            const deleteModal = document.getElementById('deleteModal');
            if (deleteModal) {
                deleteModal.addEventListener('show.bs.modal', function(event) {
                    const button = event.relatedTarget;
                    const id = button.getAttribute('data-id');
                    const name = button.getAttribute('data-name');
                    document.getElementById('deleteId').value = id;
                    document.getElementById('deleteLocationName').textContent = name;
                });
            }
            
            // Sidebar toggle for mobile
            const sidebarToggle = document.getElementById('sidebarToggle');
            if (sidebarToggle) {
                sidebarToggle.addEventListener('click', function() {
                    document.querySelector('.sidebar').classList.toggle('show-mobile');
                });
            }
            
            // Auto-dismiss alerts after 5 seconds
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                setTimeout(function() {
                    const closeButton = alert.querySelector('.btn-close');
                    if (closeButton) {
                        closeButton.click();
                    }
                }, 5000);
            });
        });
    </script>
</body>
</html>