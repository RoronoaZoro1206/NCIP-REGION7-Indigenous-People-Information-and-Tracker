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

try {
    // First, let's check and fix any applications without proper status
    $fix_status_query = "
        INSERT INTO SCHOLARSHIP_STATUS (APP_CODE, STATUS_CODE, STATUS_DATE, STATUS_IS_ACTIVE, STATUS_REMARKS)
        SELECT 
            a.APP_CODE, 
            'PENDING', 
            CURRENT_TIMESTAMP, 
            TRUE, 
            'Auto-assigned pending status'
        FROM APPLICANT a
        WHERE NOT EXISTS (
            SELECT 1 FROM SCHOLARSHIP_STATUS ss 
            WHERE ss.APP_CODE = a.APP_CODE AND ss.STATUS_IS_ACTIVE = TRUE
        )";
    
    $pdo->exec($fix_status_query);
    
    // Update any invalid status codes to PENDING
    $normalize_status_query = "
        UPDATE SCHOLARSHIP_STATUS 
        SET STATUS_CODE = 'PENDING', STATUS_REMARKS = 'Status normalized from invalid value'
        WHERE STATUS_IS_ACTIVE = TRUE 
        AND STATUS_CODE NOT IN ('PENDING', 'APPROVED', 'REJECTED')";
    
    $pdo->exec($normalize_status_query);
    
} catch (PDOException $e) {
    error_log("Error fixing application statuses: " . $e->getMessage());
}

// Handle search query
$search = isset($_GET['search']) ? $_GET['search'] : '';
$search_condition = "";
$search_param = null;

if (!empty($search)) {
    $search_condition = "AND (a.APP_LAST_NAME ILIKE :search_term OR a.APP_FIRST_NAME ILIKE :search_term OR 
                        a.APP_ETHNO_GROUP ILIKE :search_term OR a.APP_EMAIL ILIKE :search_term)";
    $search_param = "%$search%";
}

// Count total pending applications
$count_query = "SELECT COUNT(DISTINCT a.APP_CODE) as total 
               FROM APPLICANT a 
               LEFT JOIN SCHOLARSHIP_STATUS ss ON a.APP_CODE = ss.APP_CODE AND ss.STATUS_IS_ACTIVE = TRUE
               WHERE (ss.STATUS_CODE = 'PENDING' OR ss.STATUS_CODE IS NULL) $search_condition";

$count_stmt = $pdo->prepare($count_query);
if (!empty($search)) {
    $count_stmt->bindParam(':search_term', $search_param);
}
$count_stmt->execute();
$total_records = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Pagination setup
$records_per_page = 10;
$total_pages = ceil($total_records / $records_per_page);
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $records_per_page;

// Get pending applications
$detailed_query = "
    SELECT 
        a.APP_CODE,
        a.APP_LAST_NAME,
        a.APP_FIRST_NAME,
        a.APP_MIDDLE_INITIAL,
        a.APP_DATE_OF_BIRTH,
        a.APP_PLACE_OF_BIRTH,
        a.APP_GENDER,
        a.APP_CIVIL_STATUS,
        a.APP_ETHNO_GROUP,
        a.APP_PHONE_NUMBER,
        a.APP_EMAIL,
        a.APP_FORM_SIGNED,
        a.APP_SIGNATURE_DATE,
        
        adm.ADMIN_APP_NUMBER,
        adm.ADMIN_DOF,
        adm.ADMIN_ACTION_TAKEN_CSC_PO,
        adm.ADMIN_ACTION_TAKEN_RO,
        adm.ADMIN_CADT,
        adm.ADMIN_CONG_DISTRICT,
        adm.ADMIN_ADD_REMARKS,
        adm.ADMIN_ASSISTANCE_TYPE,
        
        addr.ADDR_MAIL,
        addr.ADDR_PERMANENT,
        addr.ADDR_HOUSE_AND_STREET,
        addr.ADDR_BARANGAY,
        addr.ADDR_MUNICIPALITY,
        addr.ADDR_PROVINCE,
        
        pref.PREF_NAME,
        pref.PREF_ADDR,
        pref.PREF_DEGREE,
        pref.PREF_REMARKS,
        
        COALESCE(ss.STATUS_CODE, 'PENDING') as STATUS,
        COALESCE(ss.STATUS_DATE, CURRENT_TIMESTAMP) as STATUS_DATE
        
    FROM APPLICANT a
    LEFT JOIN ADMINISTRATOR adm ON a.ADMIN_CODE = adm.ADMIN_CODE
    LEFT JOIN ADDRESS addr ON a.APP_CODE = addr.APP_CODE
    LEFT JOIN SCHOOL_PREFERENCE pref ON a.APP_CODE = pref.APP_CODE
    LEFT JOIN SCHOLARSHIP_STATUS ss ON a.APP_CODE = ss.APP_CODE AND ss.STATUS_IS_ACTIVE = TRUE
    WHERE (ss.STATUS_CODE = 'PENDING' OR ss.STATUS_CODE IS NULL) $search_condition
    ORDER BY a.APP_CODE DESC
    LIMIT :limit OFFSET :offset";

$detailed_stmt = $pdo->prepare($detailed_query);
$detailed_stmt->bindParam(':limit', $records_per_page, PDO::PARAM_INT);
$detailed_stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
if (!empty($search)) {
    $detailed_stmt->bindParam(':search_term', $search_param);
}
$detailed_stmt->execute();

// Process applications
$applications = [];
while ($row = $detailed_stmt->fetch(PDO::FETCH_ASSOC)) {
    // Ensure status is PENDING
    $row['status'] = 'PENDING';
    $row['status_class'] = 'bg-warning text-dark';
    $applications[] = $row;
}

// Get status counts for navigation
$status_counts = [
    'all' => 0,
    'pending' => $total_records,
    'approved' => 0,
    'rejected' => 0
];

// Get counts for other statuses
$other_counts_query = "SELECT 
    UPPER(COALESCE(ss.STATUS_CODE, 'PENDING')) as status_code,
    COUNT(DISTINCT a.APP_CODE) as count
    FROM APPLICANT a
    LEFT JOIN SCHOLARSHIP_STATUS ss ON a.APP_CODE = ss.APP_CODE AND ss.STATUS_IS_ACTIVE = TRUE
    GROUP BY UPPER(COALESCE(ss.STATUS_CODE, 'PENDING'))";

$other_counts_stmt = $pdo->query($other_counts_query);
while ($row = $other_counts_stmt->fetch(PDO::FETCH_ASSOC)) {
    $status = strtolower($row['status_code']);
    if (array_key_exists($status, $status_counts)) {
        $status_counts[$status] = $row['count'];
        $status_counts['all'] += $row['count'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pending Applications - NCIP Admin</title>
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
        }
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
            }
        }
        .pending-highlight {
            background-color: #fff3cd;
            border-left: 4px solid #ffc107;
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
                <h1><i class="bi bi-clock-history text-warning"></i> Pending Applications</h1>
                <div>
                    <a href="index.php" class="btn btn-secondary me-2">
                        <i class="bi bi-arrow-left"></i> Back to All Applications
                    </a>
                    <a href="add.php" class="btn btn-primary">
                        <i class="bi bi-plus-circle"></i> Add New Application
                    </a>
                </div>
            </div>

            <!-- Status Summary -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card bg-warning text-dark">
                        <div class="card-body text-center">
                            <h3><?php echo $status_counts['pending']; ?></h3>
                            <p class="mb-0">Pending Applications</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-secondary text-white">
                        <div class="card-body text-center">
                            <h3><?php echo $status_counts['all']; ?></h3>
                            <p class="mb-0">Total Applications</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-success text-white">
                        <div class="card-body text-center">
                            <h3><?php echo $status_counts['approved']; ?></h3>
                            <p class="mb-0">Approved</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-danger text-white">
                        <div class="card-body text-center">
                            <h3><?php echo $status_counts['rejected']; ?></h3>
                            <p class="mb-0">Rejected</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Alert Container -->
            <div id="alert-container">
                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($_SESSION['success_message']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php unset($_SESSION['success_message']); ?>
                <?php endif; ?>
            </div>

            <div class="card card-dashboard">
                <div class="card-body">
                    <!-- Search and Navigation -->
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <div class="btn-group" role="group">
                                <a href="index.php" class="btn btn-outline-secondary">
                                    All <span class="badge bg-secondary"><?php echo $status_counts['all']; ?></span>
                                </a>
                                <a href="pending.php" class="btn btn-warning active">
                                    Pending <span class="badge bg-dark"><?php echo $status_counts['pending']; ?></span>
                                </a>
                                <a href="index.php?status=APPROVED" class="btn btn-outline-success">
                                    Approved <span class="badge bg-success"><?php echo $status_counts['approved']; ?></span>
                                </a>
                                <a href="index.php?status=REJECTED" class="btn btn-outline-danger">
                                    Rejected <span class="badge bg-danger"><?php echo $status_counts['rejected']; ?></span>
                                </a>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <form class="d-flex">
                                <input class="form-control me-2" type="search" name="search" autocomplete="off" 
                                       placeholder="Search pending applications..." 
                                       value="<?php echo htmlspecialchars($search); ?>">
                                <button class="btn btn-outline-primary" type="submit">Search</button>
                                <?php if (!empty($search)): ?>
                                    <a href="pending.php" class="btn btn-outline-secondary ms-2">Clear</a>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>

                    <!-- Applications Table -->
                    <?php if (count($applications) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>App Number</th>
                                        <th>Applicant Name</th>
                                        <th>Date Filed</th>
                                        <th>Ethnic Group</th>
                                        <th>Contact</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($applications as $app): ?>
                                    <tr class="pending-highlight">
                                        <td><?php echo htmlspecialchars($app['app_code']); ?></td>
                                        <td><?php echo htmlspecialchars($app['admin_app_number'] ?: 'N/A'); ?></td>
                                        <td>
                                            <strong>
                                                <?php echo htmlspecialchars($app['app_first_name'] . ' ' . 
                                                    $app['app_middle_initial'] . ' ' . $app['app_last_name']); ?>
                                            </strong>
                                        </td>
                                        <td><?php echo $app['admin_dof'] ? date('M d, Y', strtotime($app['admin_dof'])) : 'N/A'; ?></td>
                                        <td><?php echo htmlspecialchars($app['app_ethno_group']); ?></td>
                                        <td>
                                            <?php if ($app['app_email']): ?>
                                                <small><?php echo htmlspecialchars($app['app_email']); ?></small><br>
                                            <?php endif; ?>
                                            <?php if ($app['app_phone_number']): ?>
                                                <small><?php echo htmlspecialchars($app['app_phone_number']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-warning text-dark">
                                                <i class="bi bi-clock-history"></i> PENDING
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm" role="group">
                                                <a href="view.php?id=<?php echo $app['app_code']; ?>" 
                                                   class="btn btn-outline-primary" 
                                                   data-bs-toggle="tooltip" title="View Details">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                                <a href="edit.php?id=<?php echo $app['app_code']; ?>" 
                                                   class="btn btn-outline-warning"
                                                   data-bs-toggle="tooltip" title="Edit">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <button type="button" class="btn btn-outline-success" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#approveModal<?php echo $app['app_code']; ?>"
                                                        data-bs-toggle="tooltip" title="Approve">
                                                    <i class="bi bi-check-circle"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>

                                    <!-- Quick Approve Modal -->
                                    <div class="modal fade" id="approveModal<?php echo $app['app_code']; ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Approve Application</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <p>Approve application for <strong><?php echo htmlspecialchars($app['app_first_name'] . ' ' . $app['app_last_name']); ?></strong>?</p>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                    <form method="POST" action="ajax/update_status.php" style="display: inline;">
                                                        <input type="hidden" name="app_code" value="<?php echo $app['app_code']; ?>">
                                                        <input type="hidden" name="new_status" value="APPROVED">
                                                        <button type="submit" class="btn btn-success">
                                                            <i class="bi bi-check-circle"></i> Approve
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                        <nav aria-label="Page navigation" class="mt-4">
                            <ul class="pagination justify-content-center">
                                <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="<?php echo ($page <= 1) ? '#' : 'pending.php?page='.($page-1).(empty($search) ? '' : '&search='.urlencode($search)); ?>">Previous</a>
                                </li>
                                <?php for($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                                    <a class="page-link" href="pending.php?page=<?php echo $i; ?><?php echo empty($search) ? '' : '&search='.urlencode($search); ?>"><?php echo $i; ?></a>
                                </li>
                                <?php endfor; ?>
                                <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="<?php echo ($page >= $total_pages) ? '#' : 'pending.php?page='.($page+1).(empty($search) ? '' : '&search='.urlencode($search)); ?>">Next</a>
                                </li>
                            </ul>
                        </nav>
                        <?php endif; ?>

                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="bi bi-inbox display-1 text-muted"></i>
                            <h3 class="mt-3">No Pending Applications</h3>
                            <p class="text-muted">
                                <?php if (!empty($search)): ?>
                                    No pending applications found matching "<?php echo htmlspecialchars($search); ?>".
                                    <a href="pending.php">Clear search</a> to see all pending applications.
                                <?php else: ?>
                                    All applications have been processed!
                                <?php endif; ?>
                            </p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize tooltips
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
            
            // Auto-dismiss alerts
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