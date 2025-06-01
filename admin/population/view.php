<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include "../config/database.php";

ini_set('display_errors', 1);
error_reporting(E_ALL);

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

if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error_message'] = "No population ID specified.";
    header("Location: index.php");
    exit();
}

$population_id = (int)$_GET['id'];

try {
    $query = "SELECT 
                popt_id,
                popt_name,
                popt_ip_group,
                popt_latitude,
                popt_longitude,
                popt_population
             FROM population_table 
             WHERE popt_id = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$population_id]);
    $population = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$population) {
        $_SESSION['error_message'] = "Population data not found.";
        header("Location: index.php");
        exit();
    }

    $group_query = "SELECT SUM(popt_population) as total_group_population, 
                    COUNT(*) as total_locations 
                    FROM population_table 
                    WHERE popt_ip_group = ?";
    $group_stmt = $pdo->prepare($group_query);
    $group_stmt->execute([$population['popt_ip_group']]);
    $group_stats = $group_stmt->fetch(PDO::FETCH_ASSOC);

    $percentage = 0;
    if ($group_stats['total_group_population'] > 0) {
        $percentage = ($population['popt_population'] / $group_stats['total_group_population']) * 100;
    }

    $nearby_query = "SELECT 
                    popt_id,
                    popt_name,
                    popt_population,
                    popt_latitude,
                    popt_longitude,
                    point(popt_longitude, popt_latitude) <-> point(?, ?) as distance
                FROM population_table
                WHERE popt_ip_group = ? AND popt_id != ?
                ORDER BY distance
                LIMIT 5";
    $nearby_stmt = $pdo->prepare($nearby_query);
    $nearby_stmt->execute([
        $population['popt_longitude'], 
        $population['popt_latitude'],
        $population['popt_ip_group'],
        $population_id
    ]);
    $nearby_locations = $nearby_stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $_SESSION['error_message'] = "Database error: " . $e->getMessage();
    header("Location: index.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Population Data Details - NCIP Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.3/dist/leaflet.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .main-content {
            margin-left: 250px;
            padding: 20px;
        }
        .card-dashboard {
            border-radius: 10px;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            margin-bottom: 20px;
        }
        #map {
            height: 400px !important;
            width: 100% !important;
            border-radius: 10px;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            position: relative;
            z-index: 1;
        }
        .info-box {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            transition: all 0.3s;
            border-left: 5px solid;
        }
        .info-box:hover {
            transform: translateY(-5px);
        }
        .info-box .value {
            font-size: 24px;
            font-weight: 700;
        }
        .info-box .label {
            font-size: 14px;
            color: #6c757d;
        }
        .info-box .icon {
            font-size: 2rem;
            opacity: 0.7;
        }
        .ip-badge {
            font-size: 1.2rem;
            padding: 0.5rem 1rem;
        }
        .legend {
            line-height: 18px;
            color: #555;
            background-color: white;
            padding: 6px 8px;
            border-radius: 5px;
            box-shadow: 0 0 15px rgba(0,0,0,0.2);
        }
        .legend i {
            width: 18px;
            height: 18px;
            float: left;
            margin-right: 8px;
            opacity: 0.7;
            border-radius: 50%;
            border: 1px solid #fff;
        }
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
            }
        }
    </style>
</head>

<body>
    <!-- Sidebar -->
    <?php include '../includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid">
            <!-- Page header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="mb-1">Population Data Details</h2>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="../index.php">Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="index.php">Population Data</a></li>
                            <li class="breadcrumb-item active" aria-current="page">View Details</li>
                        </ol>
                    </nav>
                </div>
                <div class="btn-group" role="group">
                    <a href="index.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Back to List
                    </a>
                </div>
            </div>

            <div class="row">
                <!-- Population details card -->
                <div class="col-lg-8">
                    <div class="card card-dashboard mb-4">
                        <div class="card-header bg-white">
                            <h5 class="card-title mb-0">Location Information</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-4">
                                <h3><?php echo htmlspecialchars($population['popt_name']); ?></h3>
                                <span class="badge bg-primary ip-badge">
                                    <?php echo htmlspecialchars($population['popt_ip_group']); ?> Indigenous People
                                </span>
                            </div>
                            
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <div class="info-box bg-light border-primary">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <div class="value"><?php echo number_format($population['popt_population']); ?></div>
                                                <div class="label">Total Population</div>
                                            </div>
                                            <div class="icon text-primary">
                                                <i class="bi bi-people-fill"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="info-box bg-light border-success">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <div class="value"><?php echo number_format($percentage, 1); ?>%</div>
                                                <div class="label">of Total <?php echo htmlspecialchars($population['popt_ip_group']); ?> Population</div>
                                            </div>
                                            <div class="icon text-success">
                                                <i class="bi bi-pie-chart-fill"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row mb-4">
                                <div class="col-md-12">
                                    <h5 class="mb-3">Geographic Information</h5>
                                    <table class="table table-bordered">
                                        <tbody>
                                            <tr>
                                                <th class="bg-light" width="30%">Latitude</th>
                                                <td><?php echo htmlspecialchars(number_format($population['popt_latitude'], 6)); ?></td>
                                            </tr>
                                            <tr>
                                                <th class="bg-light">Longitude</th>
                                                <td><?php echo htmlspecialchars(number_format($population['popt_longitude'], 6)); ?></td>
                                            </tr>
                                            <tr>
                                                <th class="bg-light">IP Group</th>
                                                <td><?php echo htmlspecialchars($population['popt_ip_group']); ?></td>
                                            </tr>
                                            <tr>
                                                <th class="bg-light">Group Statistics</th>
                                                <td>
                                                    <strong><?php echo number_format($group_stats['total_group_population']); ?></strong> total population across 
                                                    <strong><?php echo $group_stats['total_locations']; ?></strong> locations
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-12">
                                    <h5 class="mb-3">Location on Map</h5>
                                    <div id="map"></div>
                                    <div class="mt-2">
                                        <small class="text-muted">
                                            <strong>Note:</strong> Circle size and color indicate population size. Click on markers for details.
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Sidebar with nearby locations -->
                <div class="col-lg-4">
                    <div class="card card-dashboard mb-4">
                        <div class="card-header bg-white">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-geo-alt me-2"></i> Nearby <?php echo htmlspecialchars($population['popt_ip_group']); ?> Communities
                            </h5>
                        </div>
                        <div class="card-body p-0">
                            <ul class="list-group list-group-flush">
                                <?php if (count($nearby_locations) > 0): ?>
                                    <?php foreach ($nearby_locations as $location): ?>
                                        <li class="list-group-item">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <h6 class="mb-0"><?php echo htmlspecialchars($location['popt_name']); ?></h6>
                                                    <small class="text-muted">
                                                        Population: <?php echo number_format($location['popt_population']); ?>
                                                    </small>
                                                </div>
                                                <a href="view.php?id=<?php echo $location['popt_id']; ?>" class="btn btn-sm btn-outline-primary">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                            </div>
                                        </li>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <li class="list-group-item text-center text-muted">
                                        No nearby locations found
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </div>
                    
                    <div class="card card-dashboard mb-4">
                        <div class="card-header bg-white">
                            <h5 class="card-title mb-0">Actions</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <a href="edit.php?id=<?php echo $population_id; ?>" class="btn btn-warning">
                                    <i class="bi bi-pencil"></i> Edit Information
                                </a>
                                <a href="delete.php?id=<?php echo $population_id; ?>&action=soft_delete" 
                                class="btn btn-danger delete-population-btn" 
                                data-population-id="<?php echo $population_id; ?>"
                                data-population-name="<?php echo htmlspecialchars($population['popt_name']); ?>"
                                data-population-group="<?php echo htmlspecialchars($population['popt_ip_group']); ?>"
                                data-population-count="<?php echo number_format($population['popt_population']); ?>">
                                    <i class="bi bi-trash"></i> Delete Record
                                </a>
                                <a href="index.php?ip_group=<?php echo urlencode($population['popt_ip_group']); ?>" class="btn btn-primary">
                                    <i class="bi bi-filter"></i> View All <?php echo htmlspecialchars($population['popt_ip_group']); ?>
                                </a>
                                <a href="export.php?id=<?php echo $population_id; ?>" class="btn btn-success">
                                    <i class="bi bi-file-earmark-excel"></i> Export as Excel
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title" id="deleteModalLabel">
                        <i class="bi bi-recycle me-2"></i>Move Population Record to Recycle Bin
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center mb-3">
                        <i class="bi bi-exclamation-triangle-fill text-warning" style="font-size: 3rem;"></i>
                    </div>
                    <h6 class="text-center mb-3">Are you sure you want to move this population record to the recycle bin?</h6>
                    <div class="bg-light p-3 rounded" id="populationDetailsContainer">
                        <!-- Population details will be populated by JavaScript -->
                    </div>
                    <div class="mt-3">
                        <small class="text-muted">
                            <i class="bi bi-info-circle me-1"></i>
                            This action will move the record to the recycle bin where it can be restored later if needed.
                        </small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-1"></i>Cancel
                    </button>
                    <a href="#" class="btn btn-warning" id="confirmDeleteBtn">
                        <i class="bi bi-recycle me-1"></i>Yes, Move to Recycle Bin
                    </a>
                </div>
            </div>
        </div>
    </div>


    <!-- Bootstrap & Leaflet JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/leaflet@1.9.3/dist/leaflet.js"></script>
<script>
// Destroy previous map instance if exists (fixes shattered map)
let map;
function createMap() {
    // Remove previous map instance if exists
    if (map) {
        map.remove();
        map = null;
    }
    var lat = <?php echo floatval($population['popt_latitude']); ?>;
    var lng = <?php echo floatval($population['popt_longitude']); ?>;
    var pop = <?php echo intval($population['popt_population']); ?>;

    map = L.map('map').setView([lat, lng], 13);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors'
    }).addTo(map);

    // Define colors for different population sizes
    var color = '#C70039', radius = 30;
    if (pop <= 100) { color = '#0014FF'; radius = 15; }
    else if (pop <= 300) { color = '#00FFFF'; radius = 20; }
    else if (pop <= 600) { color = '#FF6666'; radius = 25; }

    // Add legend
    var legend = L.control({position: 'bottomright'});
    legend.onAdd = function(map) {
        var div = L.DomUtil.create('div', 'legend');
        div.innerHTML = '<h6>Population Legend</h6>' +
            '<i style="background:#0014FF"></i> ≤ 100<br>' +
            '<i style="background:#00FFFF"></i> 101 - 300<br>' +
            '<i style="background:#FF6666"></i> 301 - 600<br>' +
            '<i style="background:#C70039"></i> > 600<br>' +
            '<i style="background:#6c757d"></i> Nearby Location';
        return div;
    };
    legend.addTo(map);

    var mainMarker = L.circleMarker([lat, lng], {
        color: '#fff',
        fillColor: color,
        fillOpacity: 0.8,
        weight: 2,
        radius: radius
    }).addTo(map);

    mainMarker.bindPopup(
        "<strong><?php echo addslashes(htmlspecialchars($population['popt_name'])); ?></strong><br>" +
        "IP Group: <?php echo addslashes(htmlspecialchars($population['popt_ip_group'])); ?><br>" +
        "Population: <?php echo number_format($population['popt_population']); ?>"
    ).openPopup();

    <?php foreach ($nearby_locations as $location): ?>
        var nearbyPop = <?php echo intval($location['popt_population']); ?>;
        var nearbyLat = <?php echo floatval($location['popt_latitude']); ?>;
        var nearbyLng = <?php echo floatval($location['popt_longitude']); ?>;
        var nearbyColor = '#6c757d';
        if (nearbyPop <= 100) { nearbyColor = '#0014FF'; }
        else if (nearbyPop <= 300) { nearbyColor = '#00FFFF'; }
        else if (nearbyPop <= 600) { nearbyColor = '#FF6666'; }
        else { nearbyColor = '#C70039'; }
        L.circleMarker([nearbyLat, nearbyLng], {
            color: '#fff',
            fillColor: nearbyColor,
            fillOpacity: 0.6,
            weight: 1,
            radius: 10
        }).addTo(map).bindPopup(
            "<strong><?php echo addslashes(htmlspecialchars($location['popt_name'])); ?></strong><br>" +
            "Population: <?php echo number_format($location['popt_population']); ?><br>" +
            "<a href='view.php?id=<?php echo $location['popt_id']; ?>'>View Details</a>"
        );
    <?php endforeach; ?>

    // Force map to redraw
    setTimeout(function() { map.invalidateSize(true); }, 100);
    setTimeout(function() { map.invalidateSize(true); }, 500);
}

// Always re-create the map on page load
window.addEventListener('load', createMap);

// Handle delete population modal and other functionality
document.addEventListener('DOMContentLoaded', function() {
    // Handle delete population modal
    const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
    const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
    const populationDetailsContainer = document.getElementById('populationDetailsContainer');
    let currentDeleteUrl = '';
    
    // Add click event listener to delete button
    const deleteBtn = document.querySelector('.delete-population-btn');
    if (deleteBtn) {
        deleteBtn.addEventListener('click', function(e) {
            e.preventDefault(); // Prevent immediate navigation
            e.stopPropagation(); // Stop event bubbling
            
            console.log('Delete button clicked, preventing default navigation'); // Debug
            
            // Get population data from data attributes
            const populationId = this.getAttribute('data-population-id');
            const populationName = this.getAttribute('data-population-name');
            const populationGroup = this.getAttribute('data-population-group');
            const populationCount = this.getAttribute('data-population-count');
            const deleteUrl = this.getAttribute('href');
            
            console.log('Population data:', { populationId, populationName, populationGroup, populationCount, deleteUrl }); // Debug
            
            // Store the URL for later use
            currentDeleteUrl = deleteUrl;
            
            // Populate population details in modal
            populationDetailsContainer.innerHTML = `
                <strong>Population Record Details:</strong><br>
                <i class="bi bi-tag-fill me-1"></i> <strong>Name:</strong> ${populationName}<br>
                <i class="bi bi-people-fill me-1"></i> <strong>IP Group:</strong> ${populationGroup}<br>
                <i class="bi bi-hash me-1"></i> <strong>Population:</strong> ${populationCount}<br>
                <i class="bi bi-key-fill me-1"></i> <strong>ID:</strong> #${populationId}
            `;
            
            // Update the confirm button href
            confirmDeleteBtn.setAttribute('href', deleteUrl);
            
            console.log('About to show modal'); // Debug
            
            // Show the modal
            deleteModal.show();
            
            // Return false to ensure no navigation
            return false;
        });
    }
    
    // Handle confirm delete button click
    if (confirmDeleteBtn) {
        confirmDeleteBtn.addEventListener('click', function(e) {
            e.preventDefault(); // Prevent the anchor's default behavior
            
            console.log('Confirm button clicked'); // Debug
            
            // Show loading state
            const originalText = this.innerHTML;
            this.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Moving to Recycle Bin...';
            this.classList.add('disabled');
            
            // Close modal first
            deleteModal.hide();
            
            // Navigate to delete URL after modal is hidden
            setTimeout(() => {
                console.log('Navigating to:', currentDeleteUrl); // Debug
                window.location.href = currentDeleteUrl;
            }, 300); // Reduced delay to make it feel faster
        });
    }
    
    // Reset button state when modal is hidden
    document.getElementById('deleteModal').addEventListener('hidden.bs.modal', function() {
        if (confirmDeleteBtn) {
            confirmDeleteBtn.innerHTML = '<i class="bi bi-recycle me-1"></i>Yes, Move to Recycle Bin';
            confirmDeleteBtn.classList.remove('disabled');
        }
    });
    
    // Add smooth modal animations
    const modal = document.getElementById('deleteModal');
    if (modal) {
        modal.addEventListener('show.bs.modal', function () {
            console.log('Modal is showing'); // Debug
            const modalDialog = this.querySelector('.modal-dialog');
            if (modalDialog) {
                modalDialog.style.transform = 'scale(0.8)';
                modalDialog.style.opacity = '0';
                setTimeout(() => {
                    modalDialog.style.transition = 'all 0.3s ease';
                    modalDialog.style.transform = 'scale(1)';
                    modalDialog.style.opacity = '1';
                }, 10);
            }
        });
        
        modal.addEventListener('shown.bs.modal', function () {
            console.log('Modal is now visible'); // Debug
        });
    }
    
    // Mobile sidebar toggle
    const sidebarToggle = document.getElementById('sidebarToggle');
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('show-mobile');
            setTimeout(function() { if (map) map.invalidateSize(true); }, 300);
        });
    }
    
    // Additional check - ensure no other click handlers are interfering
    const deleteLinks = document.querySelectorAll('.delete-population-btn');
    deleteLinks.forEach(function(link) {
        // Remove any existing onclick attributes that might interfere
        link.removeAttribute('onclick');
    });
});
</script>
</body>
</html>