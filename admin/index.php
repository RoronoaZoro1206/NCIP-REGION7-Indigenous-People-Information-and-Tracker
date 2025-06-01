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

// Get dashboard statistics
$stats = [
    'applicants' => 0,
    'pending' => 0,
    'approved' => 0,
    'rejected' => 0,
    'unread_messages' => 0
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

    // Count unread messages
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM CONTACT_MESSAGE WHERE CONTM_STATUS = 'Unread'");
    if ($stmt) {
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $stats['unread_messages'] = $row['total'] ?? 0;
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

    // Get recent messages with better error handling
    try {
        $stmt = $pdo->query("SELECT * FROM CONTACT_MESSAGE ORDER BY CONTM_SUBMISSION_DATE DESC LIMIT 5");
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
                <button class="btn btn-outline-primary" id="refreshDashboard">
                    <i class="bi bi-arrow-clockwise"></i> Refresh Data
                </button>
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
                    <div class="card card-dashboard card-stats bg-danger text-white">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-5">
                                    <div class="icon">
                                        <i class="bi bi-envelope-exclamation-fill"></i>
                                    </div>
                                </div>
                                <div class="col-7">
                                    <p class="card-title">Unread Messages</p>
                                    <p class="stats-number"><?php echo $stats['unread_messages']; ?></p>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer bg-danger border-top-0">
                            <a href="contacts/index.php" class="text-white text-decoration-none">
                                <small>View Details <i class="bi bi-arrow-right"></i></small>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Charts & Analytics -->
            <div class="row mt-4">
                <div class="col-lg-6">
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
                
                <div class="col-lg-6">
                    <div class="card card-dashboard">
                        <div class="card-header bg-white">
                            <h5 class="card-title mb-0">Top Ethnic Groups</h5>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="ethnicChart"></canvas>
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
            type: 'pie',
            data: {
                labels: ['Pending', 'Approved', 'Rejected'],
                datasets: [{
                    data: [
                        <?php echo $stats['pending']; ?>, 
                        <?php echo $stats['approved']; ?>, 
                        <?php echo $stats['rejected']; ?>
                    ],
                    backgroundColor: ['#ffc107', '#28a745', '#dc3545'],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
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
                    backgroundColor: '#0d6efd',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        precision: 0
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