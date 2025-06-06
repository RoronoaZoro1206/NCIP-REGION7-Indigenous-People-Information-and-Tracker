<?php


if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set timezone to Philippines
date_default_timezone_set('Asia/Manila');

// Check if user is logged in
if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.php");
    exit();
}

// Include database connection and RecycleBinManager
require_once "../config/database.php";
require_once "../includes/RecycleBinManager.php";

// Initialize RecycleBinManager
try {
    $recycleBinManager = new RecycleBinManager($pdo);
} catch (Exception $e) {
    die("Error initializing RecycleBinManager: " . $e->getMessage());
}

// Initialize variables
$items = [];
$total_items = 0;
$items_per_page = 20;
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($current_page - 1) * $items_per_page;

// Search and filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter_type = isset($_GET['type']) ? trim($_GET['type']) : '';
$filter_table = isset($_GET['table']) ? trim($_GET['table']) : '';

try {
    // Get all deleted items using RecycleBinManager
    if (!empty($search)) {
        $all_items = $recycleBinManager->searchDeletedItems($search, $filter_table);
    } else {
        $all_items = $recycleBinManager->getAllDeletedItems();
    }
    
    // Apply type filter if specified
    if (!empty($filter_type)) {
        $all_items = array_filter($all_items, function($item) use ($filter_type) {
            return $item['item_type'] === $filter_type || 
                   strtolower($item['item_type']) === strtolower($filter_type);
        });
    }
    
    // Get usernames for deleted_by user IDs
    $user_ids = array_unique(array_filter(array_column($all_items, 'deleted_by')));
    $usernames = [];
    
    if (!empty($user_ids)) {
        $placeholders = str_repeat('?,', count($user_ids) - 1) . '?';
        $stmt = $pdo->prepare("SELECT user_id, user_username FROM users WHERE user_id IN ($placeholders)");
        $stmt->execute($user_ids);
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $usernames[$row['user_id']] = $row['user_username'];
        }
    }
    
    // Add username information to items and convert timezone
    foreach ($all_items as &$item) {
        if ($item['deleted_by']) {
            $item['deleted_by_username'] = $usernames[$item['deleted_by']] ?? 'Unknown User';
        } else {
            $item['deleted_by_username'] = 'Unknown User';
        }
        
        // Convert deleted_at to Philippines timezone if it exists
        if (!empty($item['deleted_at'])) {
            try {
                // Create DateTime object from the stored time (assuming UTC or server timezone)
                $dt = new DateTime($item['deleted_at']);
                // Convert to Philippines timezone
                $dt->setTimezone(new DateTimeZone('Asia/Manila'));
                $item['deleted_at_ph'] = $dt->format('Y-m-d H:i:s');
            } catch (Exception $e) {
                // If conversion fails, use original time
                $item['deleted_at_ph'] = $item['deleted_at'];
            }
        } else {
            $item['deleted_at_ph'] = $item['deleted_at'];
        }
    }
    unset($item); // Break reference
    
    // Get total count
    $total_items = count($all_items);
    
    // Apply pagination
    $items = array_slice($all_items, $offset, $items_per_page);
    
    // Calculate total pages
    $total_pages = ceil($total_items / $items_per_page);
    
    // Get statistics
    $stats = $recycleBinManager->getRecycleBinStats();
    
    // Prepare type and table statistics for filters
    $type_stats = [];
    $table_stats = [];
    
    foreach ($all_items as $item) {
        $type = $item['item_type'] ?? 'Unknown';
        $table = $item['table_key'] ?? 'Unknown';
        
        $type_stats[$type] = ($type_stats[$type] ?? 0) + 1;
        $table_stats[$table] = ($table_stats[$table] ?? 0) + 1;
    }

} catch (Exception $e) {
    $error_message = "Error loading deleted items: " . $e->getMessage();
    $items = [];
    $total_items = 0;
    $total_pages = 0;
    $type_stats = [];
    $table_stats = [];
    $stats = ['total_items' => 0, 'by_table' => [], 'total_tables' => 0];
    
    // Log the error for debugging
    error_log("Recycle bin error: " . $e->getMessage());
}

// Function to convert and format datetime to Philippines timezone
function formatPhilippinesTime($datetime) {
    if (empty($datetime)) return 'N/A';
    
    try {
        $dt = new DateTime($datetime);
        $dt->setTimezone(new DateTimeZone('Asia/Manila'));
        return $dt;
    } catch (Exception $e) {
        return new DateTime($datetime);
    }
}

// Handle AJAX requests
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    switch ($_GET['action']) {
        case 'get_item_details':
            if (isset($_GET['id']) && isset($_GET['table'])) {
                try {
                    $item_id = (int)$_GET['id'];
                    $table_key = $_GET['table'];
                    
                    $deleted_items = $recycleBinManager->getDeletedItemsByTable($table_key);
                    $item = null;
                    
                    foreach ($deleted_items as $deleted_item) {
                        if ($deleted_item['id'] == $item_id) {
                            $item = $deleted_item;
                            break;
                        }
                    }
                    
                    if ($item) {
                        // Get username for this specific item
                        if ($item['deleted_by']) {
                            $stmt = $pdo->prepare("SELECT user_username FROM users WHERE user_id = ?");
                            $stmt->execute([$item['deleted_by']]);
                            $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
                            $item['deleted_by_username'] = $user_data ? $user_data['user_username'] : 'Unknown User';
                        } else {
                            $item['deleted_by_username'] = 'Unknown User';
                        }
                        
                        // Convert deleted_at to Philippines timezone
                        if (!empty($item['deleted_at'])) {
                            $dt = formatPhilippinesTime($item['deleted_at']);
                            $item['deleted_at_formatted'] = $dt->format('F j, Y g:i A');
                        } else {
                            $item['deleted_at_formatted'] = 'N/A';
                        }
                        
                        echo json_encode(['success' => true, 'item' => $item]);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Item not found']);
                    }
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Item ID and table required']);
            }
            exit();
    }
}

// Include sidebar
include '../includes/sidebar.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recycle Bin - NCIP Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .main-content {
            margin-left: 250px;
            padding: 20px;
            min-height: 100vh;
            background-color: #f8f9fa;
        }
        
        .page-header {
            background: linear-gradient(135deg, #dc3545, #c82333);
            color: white;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .page-header h1 {
            margin: 0;
            font-size: 2.5rem;
            font-weight: 600;
        }
        
        .page-header p {
            margin: 10px 0 0 0;
            opacity: 0.9;
            font-size: 1.1rem;
        }
        
        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            border-left: 4px solid #dc3545;
        }
        
        .stat-card h3 {
            color: #dc3545;
            font-size: 2rem;
            font-weight: bold;
            margin: 0;
        }
        
        .stat-card p {
            color: #6c757d;
            margin: 5px 0 0 0;
            font-size: 0.9rem;
        }
        
        .controls-section {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .search-filters {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr auto auto;
            gap: 15px;
            align-items: end;
        }
        
        .items-table {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .table {
            margin: 0;
        }
        
        .table th {
            background-color: #f8f9fa;
            border-top: none;
            font-weight: 600;
            color: #495057;
            padding: 15px;
        }
        
        .table td {
            padding: 15px;
            vertical-align: middle;
        }
        
        .item-type-badge {
            font-size: 0.75rem;
            padding: 4px 8px;
        }
        
        .item-actions {
            display: flex;
            gap: 5px;
        }
        
        .btn-sm {
            padding: 5px 10px;
            font-size: 0.8rem;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #6c757d;
        }
        
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.5;
        }
        
        .pagination-wrapper {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-top: 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .bulk-actions {
            display: none;
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        .bulk-actions.show {
            display: block;
        }
        
        .checkbox-column {
            width: 40px;
        }
        
        .actions-column {
            width: 120px;
        }
        
        .table-hover tbody tr:hover {
            background-color: rgba(0, 0, 0, 0.02);
        }
        
        .timezone-indicator {
            font-size: 0.7rem;
            color: #6c757d;
            font-style: italic;
        }
        
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 10px;
            }
            
            .search-filters {
                grid-template-columns: 1fr;
                gap: 10px;
            }
            
            .page-header h1 {
                font-size: 2rem;
            }
            
            .stats-cards {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1><i class="bi bi-trash me-3"></i>Recycle Bin</h1>
                    <p>Manage deleted items - restore items to their original locations</p>
                    <small class="timezone-indicator">All times shown in Philippines timezone (GMT+8)</small>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-cards">
            <div class="stat-card">
                <h3><?php echo $total_items; ?></h3>
                <p>Total Items in Bin</p>
            </div>
            <div class="stat-card">
                <h3><?php echo count($type_stats); ?></h3>
                <p>Different Item Types</p>
            </div>
            <div class="stat-card">
                <h3><?php echo count($table_stats); ?></h3>
                <p>Source Tables</p>
            </div>
            <div class="stat-card">
                <h3><?php echo $total_pages; ?></h3>
                <p>Total Pages</p>
            </div>
        </div>

        <!-- Search and Filter Controls -->
        <div class="controls-section">
            <form method="GET" action="" class="search-filters">
                <div>
                    <label for="search" class="form-label">Search Items</label>
                    <input type="text" class="form-control" id="search" name="search" 
                           value="<?php echo htmlspecialchars($search); ?>" 
                           placeholder="Search by title, type, or table..." autocomplete="off">
                </div>
                <div>
                    <label for="type" class="form-label">Filter by Type</label>
                    <select class="form-select" id="type" name="type">
                        <option value="">All Types</option>
                        <?php foreach ($type_stats as $type => $count): ?>
                        <option value="<?php echo htmlspecialchars($type); ?>" 
                                <?php echo $filter_type === $type ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars(ucfirst($type)); ?> (<?php echo $count; ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="table" class="form-label">Filter by Source</label>
                    <select class="form-select" id="table" name="table">
                        <option value="">All Sources</option>
                        <?php foreach ($table_stats as $table => $count): ?>
                        <option value="<?php echo htmlspecialchars($table); ?>" 
                                <?php echo $filter_table === $table ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $table))); ?> (<?php echo $count; ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-search me-1"></i>Search
                    </button>
                </div>
                <div>
                    <a href="?" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-clockwise me-1"></i>Reset
                    </a>
                </div>
            </form>
        </div>

        <!-- Bulk Actions Bar -->
        <div class="bulk-actions" id="bulkActions">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <span id="selectedCount">0</span> items selected
                </div>
                <div>
                    <button type="button" class="btn btn-success btn-sm me-2" onclick="bulkRestore()">
                        <i class="bi bi-arrow-clockwise me-1"></i>Restore Selected
                    </button>
                </div>
            </div>
        </div>

        <?php if (empty($items)): ?>
        <!-- Empty State -->
        <div class="items-table">
            <div class="empty-state">
                <i class="bi bi-trash"></i>
                <h3>Recycle Bin is Empty</h3>
                <p>No deleted items found. When you delete items from other sections, they'll appear here.</p>
                <?php if (!empty($search) || !empty($filter_type) || !empty($filter_table)): ?>
                <a href="?" class="btn btn-outline-primary mt-3">
                    <i class="bi bi-arrow-clockwise me-2"></i>Clear Filters
                </a>
                <?php endif; ?>
            </div>
        </div>
        <?php else: ?>
        <!-- Items Table -->
        <div class="items-table">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th class="checkbox-column">
                            <input type="checkbox" class="form-check-input" id="selectAll" onchange="toggleSelectAll()">
                        </th>
                        <th>Item Name</th>
                        <th>Type</th>
                        <th>Source</th>
                        <th>Deleted By</th>
                        <th>Deleted At <small class="timezone-indicator">(PH Time)</small></th>
                        <th class="actions-column">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                    <tr>
                        <td class="checkbox-column">
                            <input type="checkbox" class="form-check-input item-checkbox" 
                                   value="<?php echo htmlspecialchars($item['id']); ?>" 
                                   data-table="<?php echo htmlspecialchars($item['table']); ?>"
                                   onchange="updateBulkActions()">
                        </td>
                        <td>
                            <div class="fw-bold"><?php echo htmlspecialchars($item['name'] ?: 'Untitled'); ?></div>
                            <small class="text-muted">ID: <?php echo htmlspecialchars($item['id']); ?></small>
                        </td>
                        <td>
                            <span class="badge item-type-badge 
                                <?php 
                                    $typeColors = [
                                        'User Account' => 'bg-primary',
                                        'Population Record' => 'bg-success',
                                        'Scholarship Application' => 'bg-info',
                                        'Announcement' => 'bg-warning',
                                        'Contact Message' => 'bg-secondary'
                                    ];
                                    echo $typeColors[$item['table_display_name']] ?? 'bg-dark';
                                ?>">
                                <?php echo htmlspecialchars($item['table_display_name']); ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge bg-dark text-white">
                                <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $item['table']))); ?>
                            </span>
                        </td>
                        <td>
                            <div class="fw-bold"><?php echo htmlspecialchars($item['deleted_by_username'] ?: 'Unknown User'); ?></div>
                            <small class="text-muted">User ID: <?php echo htmlspecialchars($item['deleted_by'] ?: 'N/A'); ?></small>
                        </td>
                        <td>
                            <?php 
                            $deletedTime = formatPhilippinesTime($item['deleted_at_ph'] ?? $item['deleted_at']);
                            ?>
                            <div class="fw-bold"><?php echo $deletedTime->format('M j, Y'); ?></div>
                            <small class="text-muted"><?php echo $deletedTime->format('g:i A'); ?></small>
                        </td>
                        <td class="actions-column">
                            <div class="item-actions">
                                <button type="button" class="btn btn-info btn-sm" 
                                        onclick="viewItemDetails('<?php echo htmlspecialchars($item['id']); ?>', '<?php echo htmlspecialchars($item['table']); ?>')"
                                        data-bs-toggle="tooltip" title="View Details">
                                    <i class="bi bi-eye"></i>
                                </button>
                                <button type="button" class="btn btn-success btn-sm" 
                                        onclick="restoreItem('<?php echo htmlspecialchars($item['id']); ?>', '<?php echo htmlspecialchars($item['table']); ?>')"
                                        data-bs-toggle="tooltip" title="Restore">
                                    <i class="bi bi-arrow-clockwise"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="pagination-wrapper">
            <nav aria-label="Recycle bin pagination">
                <ul class="pagination justify-content-center mb-0">
                    <!-- Previous Page -->
                    <?php if ($current_page > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $current_page - 1])); ?>">
                            <i class="bi bi-chevron-left"></i> Previous
                        </a>
                    </li>
                    <?php endif; ?>
                    
                    <!-- Page Numbers -->
                    <?php
                    $start_page = max(1, $current_page - 2);
                    $end_page = min($total_pages, $current_page + 2);
                    
                    if ($start_page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>">1</a>
                        </li>
                        <?php if ($start_page > 2): ?>
                        <li class="page-item disabled"><span class="page-link">...</span></li>
                        <?php endif; ?>
                    <?php endif; ?>
                    
                    <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                    <li class="page-item <?php echo $i === $current_page ? 'active' : ''; ?>">
                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
                    </li>
                    <?php endfor; ?>
                    
                    <?php if ($end_page < $total_pages): ?>
                        <?php if ($end_page < $total_pages - 1): ?>
                        <li class="page-item disabled"><span class="page-link">...</span></li>
                        <?php endif; ?>
                        <li class="page-item">
                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $total_pages])); ?>"><?php echo $total_pages; ?></a>
                        </li>
                    <?php endif; ?>
                    
                    <!-- Next Page -->
                    <?php if ($current_page < $total_pages): ?>
                    <li class="page-item">
                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $current_page + 1])); ?>">
                            Next <i class="bi bi-chevron-right"></i>
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </nav>
            
            <div class="text-center mt-3 text-muted">
                Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $items_per_page, $total_items); ?> 
                of <?php echo $total_items; ?> items
            </div>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- Item Details Modal -->
    <div class="modal fade" id="itemDetailsModal" tabindex="-1" aria-labelledby="itemDetailsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="itemDetailsModalLabel">Item Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="itemDetailsBody">
                    <div class="text-center">
                        <div class="spinner-border" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-success" id="modalRestoreBtn" onclick="restoreItemFromModal()">
                        <i class="bi bi-arrow-clockwise me-2"></i>Restore Item
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Confirmation Modals -->
    <div class="modal fade" id="confirmModal" tabindex="-1" aria-labelledby="confirmModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="confirmModalLabel">Confirm Action</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="confirmModalBody">
                    <!-- Dynamic content -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success" id="confirmActionBtn">Restore</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast Container -->
    <div class="toast-container position-fixed bottom-0 end-0 p-3" id="toastContainer">
        <!-- Dynamic toasts will be added here -->
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let currentItemId = null;
        let currentTableKey = null;
        let confirmCallback = null;

        // Initialize tooltips
        document.addEventListener('DOMContentLoaded', function() {
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });

        // Toggle select all checkbox
        function toggleSelectAll() {
            const selectAll = document.getElementById('selectAll');
            const checkboxes = document.querySelectorAll('.item-checkbox');
            
            checkboxes.forEach(checkbox => {
                checkbox.checked = selectAll.checked;
            });
            
            updateBulkActions();
        }

        // Update bulk actions visibility
        function updateBulkActions() {
            const checkboxes = document.querySelectorAll('.item-checkbox:checked');
            const bulkActions = document.getElementById('bulkActions');
            const selectedCount = document.getElementById('selectedCount');
            
            if (checkboxes.length > 0) {
                bulkActions.classList.add('show');
                selectedCount.textContent = checkboxes.length;
            } else {
                bulkActions.classList.remove('show');
            }
            
            // Update select all checkbox state
            const allCheckboxes = document.querySelectorAll('.item-checkbox');
            const selectAll = document.getElementById('selectAll');
            selectAll.checked = allCheckboxes.length > 0 && checkboxes.length === allCheckboxes.length;
            selectAll.indeterminate = checkboxes.length > 0 && checkboxes.length < allCheckboxes.length;
        }

        // View item details
        function viewItemDetails(itemId, tableKey) {
            currentItemId = itemId;
            currentTableKey = tableKey;
            const modal = new bootstrap.Modal(document.getElementById('itemDetailsModal'));
            const modalBody = document.getElementById('itemDetailsBody');
            const restoreBtn = document.getElementById('modalRestoreBtn');
            
            // Show loading
            modalBody.innerHTML = `
                <div class="text-center">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">Loading item details...</p>
                </div>
            `;
            
            modal.show();
            
            // Fetch item details
            fetch(`?action=get_item_details&id=${encodeURIComponent(itemId)}&table=${encodeURIComponent(tableKey)}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const item = data.item;
                        
                        let detailsHtml = `
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <strong>Item Name:</strong><br>
                                    ${item.name || 'N/A'}
                                </div>
                                <div class="col-md-6">
                                    <strong>Type:</strong><br>
                                    ${item.table_display_name || 'N/A'}
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <strong>Deleted At (PH Time):</strong><br>
                                    ${item.deleted_at_formatted || 'N/A'}
                                </div>
                                <div class="col-md-6">
                                    <strong>Deleted By:</strong><br>
                                    ${item.deleted_by_username || 'Unknown User'}<br>
                                    <small class="text-muted">User ID: ${item.deleted_by || 'N/A'}</small>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-12">
                                    <strong>Source Table:</strong><br>
                                    ${item.table || 'N/A'}
                                </div>
                            </div>
                            <div class="alert alert-info mt-3">
                                <i class="bi bi-info-circle me-2"></i>
                                <strong>Note:</strong> Restoring this item will move it back to its original location and make it visible again.
                            </div>
                        `;
                        
                        modalBody.innerHTML = detailsHtml;
                        restoreBtn.style.display = 'inline-block';
                    } else {
                        modalBody.innerHTML = `
                            <div class="alert alert-danger">
                                <i class="bi bi-exclamation-triangle me-2"></i>
                                ${data.message}
                            </div>
                        `;
                        restoreBtn.style.display = 'none';
                    }
                })
                .catch(error => {
                    modalBody.innerHTML = `
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            Error loading item details: ${error.message}
                        </div>
                    `;
                    restoreBtn.style.display = 'none';
                });
        }

        // Restore item from modal
        function restoreItemFromModal() {
            if (currentItemId && currentTableKey) {
                restoreItem(currentItemId, currentTableKey);
                bootstrap.Modal.getInstance(document.getElementById('itemDetailsModal')).hide();
            }
        }

        // Restore single item
        function restoreItem(itemId, tableKey) {
            fetch('ajax/restore_item.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `id=${encodeURIComponent(itemId)}&table=${encodeURIComponent(tableKey)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Success', data.message, 'success');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showToast('Error', data.message, 'error');
                }
            })
            .catch(error => {
                showToast('Error', 'Network error occurred', 'error');
            });
        }

        // Bulk restore
        function bulkRestore() {
            const selectedCheckboxes = document.querySelectorAll('.item-checkbox:checked');
            
            if (selectedCheckboxes.length === 0) {
                showToast('Warning', 'No items selected', 'warning');
                return;
            }
            
            const items = Array.from(selectedCheckboxes).map(cb => ({
                id: cb.value,
                table: cb.dataset.table
            }));
            
            showConfirmModal(
                'Restore Selected Items',
                `Are you sure you want to restore ${items.length} selected item(s) to their original locations?`,
                'success',
                () => {
                    fetch('ajax/bulk_actions.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `action=restore&items=${encodeURIComponent(JSON.stringify(items))}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            showToast('Success', data.message, 'success');
                            setTimeout(() => location.reload(), 1500);
                        } else {
                            showToast('Error', data.message, 'error');
                        }
                    })
                    .catch(error => {
                        showToast('Error', 'Network error occurred', 'error');
                    });
                }
            );
        }

        // Show confirmation modal
        function showConfirmModal(title, message, type, callback) {
            const modal = new bootstrap.Modal(document.getElementById('confirmModal'));
            const modalLabel = document.getElementById('confirmModalLabel');
            const modalBody = document.getElementById('confirmModalBody');
            const confirmBtn = document.getElementById('confirmActionBtn');
            
            modalLabel.textContent = title;
            modalBody.innerHTML = `<p>${message}</p>`;
            
            // Set button style based on type
            confirmBtn.className = `btn btn-${type}`;
            confirmBtn.textContent = 'Restore';
            
            confirmCallback = callback;
            
            // Remove any existing event listeners
            const newBtn = confirmBtn.cloneNode(true);
            confirmBtn.parentNode.replaceChild(newBtn, confirmBtn);
            
            // Add new event listener
            newBtn.addEventListener('click', () => {
                if (confirmCallback) {
                    confirmCallback();
                    confirmCallback = null;
                }
                modal.hide();
            });
            
            modal.show();
        }

        // Show toast notification
        function showToast(title, message, type) {
            const toastContainer = document.getElementById('toastContainer');
            const toastId = 'toast_' + Date.now();
            
            const typeClasses = {
                'success': 'text-bg-success',
                'error': 'text-bg-danger',
                'warning': 'text-bg-warning',
                'info': 'text-bg-info'
            };
            
            const icons = {
                'success': 'bi-check-circle',
                'error': 'bi-exclamation-triangle',
                'warning': 'bi-exclamation-triangle',
                'info': 'bi-info-circle'
            };
            
            const toastHtml = `
                <div class="toast ${typeClasses[type] || 'text-bg-primary'}" role="alert" aria-live="assertive" aria-atomic="true" id="${toastId}">
                    <div class="toast-header">
                        <i class="bi ${icons[type] || 'bi-info-circle'} me-2"></i>
                        <strong class="me-auto">${title}</strong>
                        <small>Just now</small>
                        <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
                    </div>
                    <div class="toast-body">
                        ${message}
                    </div>
                </div>
            `;
            
            toastContainer.insertAdjacentHTML('beforeend', toastHtml);
            
            const toast = new bootstrap.Toast(document.getElementById(toastId), {
                autohide: true,
                delay: 5000
            });
            
            toast.show();
            
            // Remove toast element after it's hidden
            document.getElementById(toastId).addEventListener('hidden.bs.toast', function() {
                this.remove();
            });
        }
    </script>
</body>
</html>