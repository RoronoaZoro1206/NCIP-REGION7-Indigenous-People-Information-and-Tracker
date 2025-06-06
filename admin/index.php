<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include "config/database.php";
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Check if user is logged in and has admin role
if (!isset($_SESSION["user_id"]) || !isset($_SESSION["role"]) || strtoupper($_SESSION["role"]) !== 'ADMIN') {
    header("Location: login.php");
    exit();
}

// Fetch user profile info including profile picture
try {
    $user_stmt = $pdo->prepare("SELECT user_username, user_profile_picture FROM users WHERE user_id = :user_id");
    $user_stmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
    $user_stmt->execute();
    $user = $user_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Update session with latest user info if not already set
    if ($user) {
        if (!isset($_SESSION['username']) || $_SESSION['username'] !== $user['user_username']) {
            $_SESSION['username'] = $user['user_username'];
        }
        
        if (!isset($_SESSION['profile_picture']) && !empty($user['user_profile_picture'])) {
            $_SESSION['profile_picture'] = $user['user_profile_picture'];
        }
    }
} catch (PDOException $e) {
    error_log("Failed to fetch user data: " . $e->getMessage());
}

// Initialize variables
$error_message = null;
$recent_applicants = [];
$recent_messages = [];
$ethnic_groups = [];
$population_groups = [];
$population_stats = ['total_locations' => 0, 'grand_total_population' => 0];
$recycle_bin_stats = [];

// Get dashboard statistics
$stats = [
    'applicants' => 0,
    'pending' => 0,
    'approved' => 0,
    'rejected' => 0,
    'total_messages' => 0
];

try {
    // Count total applicants
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM APPLICANT");
    if ($stmt) {
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $stats['applicants'] = $row['total'] ?? 0;
    }

    // Count pending applications - Fix status code case sensitivity
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM SCHOLARSHIP_STATUS WHERE UPPER(STATUS_CODE) = 'PENDING' AND STATUS_IS_ACTIVE = TRUE");
    if ($stmt) {
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $stats['pending'] = $row['total'] ?? 0;
    }

    // Count approved applications
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM SCHOLARSHIP_STATUS WHERE UPPER(STATUS_CODE) = 'APPROVED' AND STATUS_IS_ACTIVE = TRUE");
    if ($stmt) {
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $stats['approved'] = $row['total'] ?? 0;
    }

    // Count rejected applications
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM SCHOLARSHIP_STATUS WHERE UPPER(STATUS_CODE) = 'REJECTED' AND STATUS_IS_ACTIVE = TRUE");
    if ($stmt) {
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $stats['rejected'] = $row['total'] ?? 0;
    }

    // Count total messages (excluding soft deleted ones)
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM CONTACT_MESSAGE WHERE contm_deleted_at IS NULL");
    if ($stmt) {
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $stats['total_messages'] = $row['total'] ?? 0;
    }

    // Get recycle bin statistics for bar chart (excluding announcements)
    try {
        // Users - deleted vs not deleted
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE user_deleted_at IS NOT NULL");
        $deleted_users = $stmt ? ($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0) : 0;
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE user_deleted_at IS NULL");
        $active_users = $stmt ? ($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0) : 0;
        
        // Messages - deleted vs not deleted
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM contact_message WHERE contm_deleted_at IS NOT NULL");
        $deleted_messages = $stmt ? ($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0) : 0;
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM contact_message WHERE contm_deleted_at IS NULL");
        $active_messages = $stmt ? ($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0) : 0;
        
        // Population records - deleted vs not deleted
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM population_table WHERE popt_deleted_at IS NOT NULL");
        $deleted_population = $stmt ? ($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0) : 0;
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM population_table WHERE popt_deleted_at IS NULL");
        $active_population = $stmt ? ($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0) : 0;
        
        // Applicants - deleted vs not deleted (if soft delete exists)
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM applicant WHERE app_deleted_at IS NOT NULL");
        $deleted_applicants = $stmt ? ($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0) : 0;
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM applicant WHERE app_deleted_at IS NULL");
        $active_applicants = $stmt ? ($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0) : 0;
        
        // Recycle bin table records vs total records
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM recycle_bin");
        $recycle_bin_items = $stmt ? ($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0) : 0;
        
        // Scholarships - deleted vs not deleted (if exists)
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM scholarship WHERE scholar_deleted_at IS NOT NULL");
        $deleted_scholarships = $stmt ? ($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0) : 0;
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM scholarship WHERE scholar_deleted_at IS NULL");
        $active_scholarships = $stmt ? ($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0) : 0;
        
        $recycle_bin_stats = [
            'deleted' => [
                'users' => $deleted_users,
                'messages' => $deleted_messages,
                'population' => $deleted_population,
                'applicants' => $deleted_applicants,
                'recycle_items' => $recycle_bin_items,
                'scholarships' => $deleted_scholarships
            ],
            'active' => [
                'users' => $active_users,
                'messages' => $active_messages,
                'population' => $active_population,
                'applicants' => $active_applicants,
                'recycle_items' => 0, // No "active" recycle bin items
                'scholarships' => $active_scholarships
            ]
        ];
        
    } catch (PDOException $e) {
        error_log("Recycle bin stats error: " . $e->getMessage());
        $recycle_bin_stats = [
            'deleted' => [
                'users' => 0,
                'messages' => 0,
                'population' => 0,
                'applicants' => 0,
                'recycle_items' => 0,
                'scholarships' => 0
            ],
            'active' => [
                'users' => 0,
                'messages' => 0,
                'population' => 0,
                'applicants' => 0,
                'recycle_items' => 0,
                'scholarships' => 0
            ]
        ];
    }

    // Get recent applicants with more robust error handling
    try {
        $stmt = $pdo->query("SELECT a.APP_CODE, a.APP_LAST_NAME, a.APP_FIRST_NAME, a.APP_EMAIL, a.APP_ETHNO_GROUP, a.APP_DATE_OF_BIRTH,
                            (SELECT ss.STATUS_CODE FROM SCHOLARSHIP_STATUS ss WHERE ss.APP_CODE = a.APP_CODE AND ss.STATUS_IS_ACTIVE = TRUE LIMIT 1) as STATUS 
                           FROM APPLICANT a 
                           ORDER BY a.APP_CODE DESC LIMIT 5");
        if ($stmt) {
            $recent_applicants = $stmt->fetchAll(PDO::FETCH_ASSOC);
            // Set a default status for applicants with no status
            foreach ($recent_applicants as &$applicant) {
                if (empty($applicant['STATUS'])) {
                    $applicant['STATUS'] = 'PENDING';
                }
            }
        }
    } catch (PDOException $e) {
        error_log("Recent applicants query error: " . $e->getMessage());
        $recent_applicants = [];
    }

    // Get recent messages with better error handling (excluding soft deleted ones)
    try {
        $stmt = $pdo->query("SELECT * FROM CONTACT_MESSAGE WHERE contm_deleted_at IS NULL ORDER BY CONTM_SUBMISSION_DATE DESC LIMIT 5");
        if ($stmt) {
            $recent_messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        error_log("Recent messages query error: " . $e->getMessage());
        $recent_messages = [];
    }

    // Get ethnic group distribution for chart with improved error handling
    try {
        $stmt = $pdo->query("SELECT APP_ETHNO_GROUP, COUNT(*) as count FROM APPLICANT GROUP BY APP_ETHNO_GROUP ORDER BY COUNT(*) DESC LIMIT 5");
        if ($stmt) {
            $ethnic_groups = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        error_log("Ethnic group query error: " . $e->getMessage());
        $ethnic_groups = [];
    }

    // Get population distribution by IP group from population_table
    try {
        $pop_stmt = $pdo->query("SELECT 
                                    popt_ip_group, 
                                    COUNT(*) as location_count,
                                    SUM(popt_population) as total_population 
                                FROM population_table 
                                WHERE popt_deleted_at IS NULL 
                                GROUP BY popt_ip_group 
                                ORDER BY SUM(popt_population) DESC");
        if ($pop_stmt) {
            $population_groups = $pop_stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $population_groups = [];
        }
        
        // Get total population statistics
        $total_pop_stmt = $pdo->query("SELECT 
                                        COUNT(*) as total_locations,
                                        SUM(popt_population) as grand_total_population 
                                      FROM population_table 
                                      WHERE popt_deleted_at IS NULL");
        if ($total_pop_stmt) {
            $population_stats = $total_pop_stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            $population_stats = ['total_locations' => 0, 'grand_total_population' => 0];
        }

    } catch (PDOException $e) {
        error_log("Population data query error: " . $e->getMessage());
        $population_groups = [];
        $population_stats = ['total_locations' => 0, 'grand_total_population' => 0];
    }

} catch (PDOException $e) {
    // Improved error logging
    error_log("Dashboard error: " . $e->getMessage());
    $error_message = "There was an error loading the dashboard data: " . $e->getMessage();
}

// Log dashboard access
try {
    $log_query = "INSERT INTO USER_LOGS (USER_ID, LOG_TYPE, LOG_DETAILS, LOG_TIMESTAMP) 
                 VALUES (:user_id, 'DASHBOARD_ACCESS', 'Admin accessed dashboard', CURRENT_TIMESTAMP)";
    $log_stmt = $pdo->prepare($log_query);
    $log_stmt->bindParam(":user_id", $_SESSION["user_id"]);
    $log_stmt->execute();
} catch (PDOException $e) {
    // Just log but don't interrupt
    error_log("Could not log dashboard access: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NCIP Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .main-content {
            margin-left: 250px;
            padding: 20px;
        }
        .card-dashboard {
            border: none;
            border-radius: 10px;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            margin-bottom: 20px;
            transition: transform 0.2s ease-in-out;
        }
        .card-dashboard:hover {
            transform: translateY(-5px);
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }
        .card-stats {
            min-height: 120px;
        }
        .card-stats .icon {
            font-size: 36px;
        }
        .stats-number {
            font-size: 28px;
            font-weight: bold;
        }
        .table-hover tbody tr:hover {
            background-color: rgba(0, 0, 0, 0.04);
        }
        .chart-container {
            position: relative;
            height: 250px;
        }
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid">
            <?php if(isset($error_message)): ?>
            <div class="alert alert-danger"><?php echo $error_message; ?></div>
            <?php endif; ?>
            
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1>Dashboard</h1>
                <div>
                    <button class="btn btn-outline-primary" id="refreshDashboard">
                        <i class="bi bi-arrow-clockwise"></i> Refresh Data
                    </button>
                </div>
            </div>
            
            <!-- Statistics -->
            <div class="row">
                <div class="col-xl-3 col-md-6">
                    <div class="card card-dashboard card-stats bg-primary text-white">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-5">
                                    <div class="icon">
                                        <i class="bi bi-people-fill"></i>
                                    </div>
                                </div>
                                <div class="col-7">
                                    <p class="card-title">Total Applicants</p>
                                    <p class="stats-number"><?php echo $stats['applicants']; ?></p>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer bg-primary border-top-0">
                            <a href="scholarships/index.php" class="text-white text-decoration-none">
                                <small>View Details <i class="bi bi-arrow-right"></i></small>
                            </a>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6">
                    <div class="card card-dashboard card-stats bg-warning text-white">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-5">
                                    <div class="icon">
                                        <i class="bi bi-hourglass-split"></i>
                                    </div>
                                </div>
                                <div class="col-7">
                                    <p class="card-title">Pending Applications</p>
                                    <p class="stats-number"><?php echo $stats['pending']; ?></p>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer bg-warning border-top-0">
                            <a href="scholarships/pending.php" class="text-white text-decoration-none">
                                <small>View Details <i class="bi bi-arrow-right"></i></small>
                            </a>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6">
                    <div class="card card-dashboard card-stats bg-success text-white">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-5">
                                    <div class="icon">
                                        <i class="bi bi-check-circle-fill"></i>
                                    </div>
                                </div>
                                <div class="col-7">
                                    <p class="card-title">Approved Applications</p>
                                    <p class="stats-number"><?php echo $stats['approved']; ?></p>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer bg-success border-top-0">
                            <a href="scholarships/approved.php" class="text-white text-decoration-none">
                                <small>View Details <i class="bi bi-arrow-right"></i></small>
                            </a>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6">
                    <div class="card card-dashboard card-stats bg-info text-white">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-5">
                                    <div class="icon">
                                        <i class="bi bi-envelope-fill"></i>
                                    </div>
                                </div>
                                <div class="col-7">
                                    <p class="card-title">Total Messages</p>
                                    <p class="stats-number"><?php echo $stats['total_messages']; ?></p>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer bg-info border-top-0">
                            <a href="contacts/index.php" class="text-white text-decoration-none">
                                <small>View Details <i class="bi bi-arrow-right"></i></small>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Charts & Analytics -->
            <div class="row mt-4">
                <div class="col-lg-3">
                    <div class="card card-dashboard">
                        <div class="card-header bg-white">
                            <h5 class="card-title mb-0">Application Status Distribution</h5>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="statusChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-3">
                    <div class="card card-dashboard">
                        <div class="card-header bg-white">
                            <h5 class="card-title mb-0">IP Population by Group</h5>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="populationDoughnutChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-3">
                    <div class="card card-dashboard">
                        <div class="card-header bg-white">
                            <h5 class="card-title mb-0">Applicant Ethnic Groups</h5>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="ethnicChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-3">
                    <div class="card card-dashboard">
                        <div class="card-header bg-white d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">Recycle Bin Overview</h5>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="recycleBinBarChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Population Analytics -->
            <div class="row mt-4">
                <div class="col-lg-8">
                    <div class="card card-dashboard">
                        <div class="card-header bg-white">
                            <h5 class="card-title mb-0">Indigenous People Population Comparison</h5>
                        </div>
                        <div class="card-body">
                            <div class="chart-container" style="height: 350px;">
                                <canvas id="populationBarChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4">
                    <div class="card card-dashboard">
                        <div class="card-header bg-white">
                            <h5 class="card-title mb-0">Population Summary</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <h4 class="text-primary"><?php echo number_format($population_stats['grand_total_population']); ?></h4>
                                <small class="text-muted">Total Indigenous Population</small>
                            </div>
                            <div class="mb-3">
                                <h5 class="text-success"><?php echo $population_stats['total_locations']; ?></h5>
                                <small class="text-muted">Total Recorded Locations</small>
                            </div>
                            <div class="mb-3">
                                <h5 class="text-info"><?php echo count($population_groups); ?></h5>
                                <small class="text-muted">Indigenous Groups</small>
                            </div>
                            <hr>
                            <div class="small">
                                <?php if(!empty($population_groups)): ?>
                                    <?php foreach(array_slice($population_groups, 0, 5) as $group): ?>
                                        <div class="d-flex justify-content-between mb-1">
                                            <span><?php echo htmlspecialchars($group['popt_ip_group']); ?></span>
                                            <strong><?php echo number_format($group['total_population']); ?></strong>
                                        </div>
                                    <?php endforeach; ?>
                                    <?php if(count($population_groups) > 5): ?>
                                        <div class="text-center mt-2">
                                            <a href="population/index.php" class="btn btn-sm btn-outline-primary">View All Groups</a>
                                        </div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <p class="text-muted small">No population data available</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Recent Data -->
            <div class="row mt-4">
                <!-- Recent Applicants -->
                <div class="col-lg-6">
                    <div class="card card-dashboard">
                        <div class="card-header bg-white">
                            <h5 class="card-title mb-0">Recent Scholarship Applicants</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Ethnic Group</th>
                                            <th>Status</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if(!empty($recent_applicants)): ?>
                                            <?php foreach ($recent_applicants as $applicant): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($applicant['APP_LAST_NAME'] . ', ' . $applicant['APP_FIRST_NAME']); ?></td>
                                                <td><?php echo htmlspecialchars($applicant['APP_ETHNO_GROUP']); ?></td>
                                                <td>
                                                    <?php if ($applicant['STATUS'] == 'APPROVED'): ?>
                                                        <span class="badge bg-success">Approved</span>
                                                    <?php elseif ($applicant['STATUS'] == 'REJECTED'): ?>
                                                        <span class="badge bg-danger">Rejected</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-warning">Pending</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <a href="scholarships/view.php?id=<?php echo $applicant['APP_CODE']; ?>" class="btn btn-sm btn-primary">View</a>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="4" class="text-center">No recent applicants</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                            <a href="scholarships/index.php" class="btn btn-outline-primary btn-sm">View All Applicants</a>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Messages -->
                <div class="col-lg-6">
                    <div class="card card-dashboard">
                        <div class="card-header bg-white">
                            <h5 class="card-title mb-0">Recent Contact Messages</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Date</th>
                                            <th>Status</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if(!empty($recent_messages)): ?>
                                            <?php foreach ($recent_messages as $message): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($message['contm_name'] ?? 'Unknown'); ?></td>
                                                <td><?php echo isset($message['contm_submission_date']) ? date('M d, Y', strtotime($message['contm_submission_date'])) : 'No date'; ?></td>
                                                <td>
                                                    <?php if (isset($message['contm_status'])): ?>
                                                        <?php if ($message['contm_status'] == 'Unread'): ?>
                                                            <span class="badge bg-danger">Unread</span>
                                                        <?php elseif ($message['contm_status'] == 'Archived'): ?>
                                                            <span class="badge bg-secondary">Archived</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-success">Read</span>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <span class="badge bg-warning">Unknown</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <a href="contacts/view.php?id=<?php echo $message['contm_id'] ?? 0; ?>" class="btn btn-sm btn-primary">View</a>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="4" class="text-center">No recent messages</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                            <a href="contacts/index.php" class="btn btn-outline-primary btn-sm">View All Messages</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Define IP Group colors for consistency
        const ipGroupColors = {
            'BADJAO': '#FF6384',
            'MAMANWA': '#36A2EB', 
            'ATI': '#FFCE56',
            'SUBANON': '#4BC0C0',
            'HIGAONON': '#9966FF',
            'MANOBO': '#FF9F40',
            'B\'LAAN': '#FF6B6B',
            'T\'BOLI': '#4ECDC4',
            'TAUSUG': '#45B7D1',
            'YAKAN': '#96CEB4'
        };
        
        // Default colors for unknown groups
        const defaultColors = ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF', '#FF9F40', '#FF6B6B', '#4ECDC4'];
        
        // Toggle sidebar on mobile
        const sidebarToggle = document.getElementById('sidebarToggle');
        if (sidebarToggle) {
            sidebarToggle.addEventListener('click', function() {
                document.querySelector('.sidebar').classList.toggle('show-mobile');
            });
        }
        
        // Status distribution chart
        const statusCtx = document.getElementById('statusChart').getContext('2d');
        const statusChart = new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: ['Pending', 'Approved', 'Rejected'],
                datasets: [{
                    data: [
                        <?php echo $stats['pending']; ?>, 
                        <?php echo $stats['approved']; ?>, 
                        <?php echo $stats['rejected']; ?>
                    ],
                    backgroundColor: ['#ffc107', '#28a745', '#dc3545'],
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 10,
                            usePointStyle: true,
                            font: {
                                size: 9
                            }
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = total > 0 ? ((context.raw / total) * 100).toFixed(1) : 0;
                                return context.label + ': ' + context.raw + ' (' + percentage + '%)';
                            }
                        }
                    }
                }
            }
        });

        // Population distribution doughnut chart
        <?php
        $pop_labels = [];
        $pop_data = [];
        
        if(!empty($population_groups)) {
            foreach ($population_groups as $group) {
                $pop_labels[] = $group['popt_ip_group'];
                $pop_data[] = (int)$group['total_population'];
            }
        }
        ?>
        
        const populationDoughnutCtx = document.getElementById('populationDoughnutChart').getContext('2d');
        const populationDoughnutChart = new Chart(populationDoughnutCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($pop_labels); ?>,
                datasets: [{
                    data: <?php echo json_encode($pop_data); ?>,
                    backgroundColor: <?php echo json_encode($pop_labels); ?>.map((group, index) => 
                        ipGroupColors[group] || defaultColors[index % defaultColors.length]
                    ),
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 8,
                            usePointStyle: true,
                            font: {
                                size: 8
                            }
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = total > 0 ? ((context.raw / total) * 100).toFixed(1) : 0;
                                return context.label + ': ' + context.raw.toLocaleString() + ' people (' + percentage + '%)';
                            }
                        }
                    }
                }
            }
        });

        // Recycle Bin Bar Chart - Deleted vs Active Items
        const recycleBinBarCtx = document.getElementById('recycleBinBarChart').getContext('2d');
        const recycleBinBarChart = new Chart(recycleBinBarCtx, {
            type: 'bar',
            data: {
                labels: ['Users', 'Messages', 'Population', 'Applicants', 'Scholarships'],
                datasets: [{
                    label: 'Deleted Items',
                    data: [
                        <?php echo $recycle_bin_stats['deleted']['users']; ?>,
                        <?php echo $recycle_bin_stats['deleted']['messages']; ?>,
                        <?php echo $recycle_bin_stats['deleted']['population']; ?>,
                        <?php echo $recycle_bin_stats['deleted']['applicants']; ?>,
                        <?php echo $recycle_bin_stats['deleted']['scholarships']; ?>
                    ],
                    backgroundColor: [
                        'rgba(255, 99, 132, 0.2)',
                        'rgba(255, 159, 64, 0.2)',
                        'rgba(255, 205, 86, 0.2)',
                        'rgba(75, 192, 192, 0.2)',
                        'rgba(153, 102, 255, 0.2)'
                    ],
                    borderColor: [
                        'rgb(255, 99, 132)',
                        'rgb(255, 159, 64)',
                        'rgb(255, 205, 86)',
                        'rgb(75, 192, 192)',
                        'rgb(153, 102, 255)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return 'Deleted: ' + context.raw + ' items';
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            font: {
                                size: 8
                            }
                        }
                    },
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Number of Items',
                            font: {
                                size: 10
                            }
                        },
                        ticks: {
                            font: {
                                size: 8
                            }
                        }
                    }
                }
            }
        });

        // Population comparison bar chart
        const populationBarCtx = document.getElementById('populationBarChart').getContext('2d');
        const populationBarChart = new Chart(populationBarCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($pop_labels); ?>,
                datasets: [
                    {
                        label: 'Population Count',
                        data: <?php echo json_encode($pop_data); ?>,
                        backgroundColor: <?php echo json_encode($pop_labels); ?>.map((group, index) => 
                            ipGroupColors[group] || defaultColors[index % defaultColors.length]
                        ),
                        borderWidth: 1,
                        borderRadius: 5,
                        borderSkipped: false,
                    },
                    {
                        label: 'Location Count',
                        data: <?php echo json_encode(array_column($population_groups, 'location_count')); ?>,
                        type: 'line',
                        borderColor: '#ff6b6b',
                        backgroundColor: 'rgba(255, 107, 107, 0.1)',
                        borderWidth: 2,
                        fill: false,
                        yAxisID: 'y1',
                        tension: 0.4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            usePointStyle: true,
                            padding: 20
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                if (context.datasetIndex === 0) {
                                    return 'Population: ' + context.raw.toLocaleString() + ' people';
                                } else {
                                    return 'Locations: ' + context.raw + ' sites';
                                }
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: false
                        }
                    },
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Population Count'
                        },
                        ticks: {
                            callback: function(value) {
                                return value.toLocaleString();
                            }
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Location Count'
                        },
                        grid: {
                            drawOnChartArea: false,
                        },
                    }
                }
            }
        });

        // Applicant ethnic groups chart
        <?php
        $ethnic_labels = [];
        $ethnic_data = [];
        if(!empty($ethnic_groups)) {
            foreach ($ethnic_groups as $group) {
                // Add null checks to prevent undefined index errors
                $ethnic_labels[] = $group['APP_ETHNO_GROUP'] ?? 'Unknown';
                $ethnic_data[] = isset($group['count']) ? (int)$group['count'] : 0;
            }
        } else {
            $ethnic_labels = ['No Data'];
            $ethnic_data = [0];
        }
        ?>
        
        const ethnicCtx = document.getElementById('ethnicChart').getContext('2d');
        const ethnicChart = new Chart(ethnicCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($ethnic_labels); ?>,
                datasets: [{
                    label: 'Number of Applicants',
                    data: <?php echo json_encode($ethnic_data); ?>,
                    backgroundColor: <?php echo json_encode($ethnic_labels); ?>.map((group, index) => 
                        ipGroupColors[group] || defaultColors[index % defaultColors.length]
                    ),
                    borderWidth: 1,
                    borderRadius: 3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return 'Applicants: ' + context.raw;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            font: {
                                size: 8
                            }
                        }
                    },
                    y: {
                        beginAtZero: true,
                        precision: 0,
                        title: {
                            display: true,
                            text: 'Number of Applicants',
                            font: {
                                size: 10
                            }
                        },
                        ticks: {
                            font: {
                                size: 8
                            }
                        }
                    }
                }
            }
        });
        
        // Refresh dashboard
        document.getElementById('refreshDashboard').addEventListener('click', function() {
            location.reload();
        });
    });
    </script>
</body>
</html>