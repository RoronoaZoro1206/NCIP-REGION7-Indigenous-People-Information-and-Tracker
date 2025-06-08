<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if(!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once 'config/database.php';
require_once 'includes/functions.php';

// Get user data
$stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = :user_id");
$stmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Timezone options
$timezones = [
    'UTC' => 'UTC (Coordinated Universal Time)',
    'Asia/Manila' => 'Philippines Time (Asia/Manila)',
    'Asia/Singapore' => 'Singapore Time',
    'America/New_York' => 'Eastern Time (US & Canada)',
    'America/Chicago' => 'Central Time (US & Canada)',
    'America/Denver' => 'Mountain Time (US & Canada)',
    'America/Los_Angeles' => 'Pacific Time (US & Canada)',
    'Europe/London' => 'London, Edinburgh (GMT)',
    'Europe/Paris' => 'Paris, Berlin, Rome, Madrid',
    'Australia/Sydney' => 'Sydney, Melbourne'
];

// Check for messages
$success_message = $_SESSION['success_message'] ?? null;
$error_message = $_SESSION['error_message'] ?? null;

// Clear messages after displaying once
unset($_SESSION['success_message']);
unset($_SESSION['error_message']);

// Define current page for sidebar highlighting
$currentPage = 'settings';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Settings - NCIP Admin</title>
    <link rel="icon" href="../front end/images/ncip-logo.png" type="image/png">
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
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            margin-bottom: 20px;
        }
        .card-header {
            background-color: #f8f9fa;
            border-bottom: 1px solid rgba(0,0,0,.125);
        }
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
            }
        }
        
        /* Enhanced modal styling */
        .modal-content.border-danger {
            border: 2px solid #dc3545;
        }
        
        .modal-dialog-centered {
            min-height: calc(100vh - 3rem);
        }
        
        #finalConfirmationModal .modal-body {
            padding: 2rem;
        }
        
        #finalConfirmationModal .alert {
            margin-bottom: 0;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h2"><i class="bi bi-gear me-2"></i>Account Settings</h1>
            </div>

            <?php if ($success_message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle me-2"></i><?php echo htmlspecialchars($success_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>

            <div class="row">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0"><i class="bi bi-sliders me-2"></i>General Settings</h5>
                        </div>
                        <div class="card-body">
                            <form action="update_settings.php" method="post">
                                <div class="mb-3">
                                    <label for="timezone" class="form-label">Timezone <span class="text-danger">*</span></label>
                                    <select class="form-select" id="timezone" name="timezone" required>
                                        <?php foreach($timezones as $value => $label): ?>
                                            <option value="<?php echo htmlspecialchars($value); ?>" 
                                                <?php echo (isset($user['user_timezone']) && $user['user_timezone'] == $value) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($label); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="form-text">Select your preferred timezone for displaying dates and times.</div>
                                </div>

                                <?php if(isset($user['user_role']) && $user['user_role'] == 'ADMIN'): ?>
                                <div class="mb-3">
                                    <label class="form-label">Account Type</label>
                                    <div class="form-control bg-light"><?php echo htmlspecialchars($user['user_role']); ?></div>
                                    <div class="form-text">Administrator accounts have additional permissions and access to all system features.</div>
                                </div>
                                <?php endif; ?>

                                <div class="mb-3">
                                    <label for="current_password_settings" class="form-label">Current Password <span class="text-danger">*</span></label>
                                    <input type="password" class="form-control" id="current_password_settings" name="current_password" required>
                                    <div class="form-text">Enter your current password to confirm changes.</div>
                                </div>

                                <div class="d-flex gap-2">
                                    <button type="submit" name="update_settings" class="btn btn-primary">
                                        <i class="bi bi-save"></i> Save Settings
                                    </button>
                                    <a href="index.php" class="btn btn-secondary">
                                        <i class="bi bi-arrow-left"></i> Back to Dashboard
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div class="card border-danger">
                        <div class="card-header bg-danger text-white">
                            <h5 class="card-title mb-0"><i class="bi bi-exclamation-triangle me-2"></i>Danger Zone</h5>
                        </div>
                        <div class="card-body">
                            <h6 class="text-danger">Delete Account</h6>
                            <p class="mb-3">Once you delete your account, there is no going back. Please be certain.</p>
                            <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#deleteAccountModal">
                                <i class="bi bi-trash"></i> Delete Account
                            </button>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0"><i class="bi bi-activity me-2"></i>Account Activity</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <strong>Account Created:</strong><br>
                                <span class="text-muted">
                                    <?php
                                    if (!empty($user['user_created']) && $user['user_created'] !== '0000-00-00 00:00:00') {
                                        $tz = !empty($user['user_timezone']) ? $user['user_timezone'] : 'Asia/Manila';
                                        $dt = new DateTime($user['user_created']);
                                        try {
                                            $dt->setTimezone(new DateTimeZone($tz));
                                        } catch (Exception $e) {
                                            $dt->setTimezone(new DateTimeZone('Asia/Manila'));
                                        }
                                        echo $dt->format('F j, Y g:i A');
                                    } else {
                                        echo 'Unknown';
                                    }
                                    ?>
                                </span>
                            </div>

                            <div class="mb-3">
                                <strong>Last Login:</strong><br>
                                <span class="text-muted">
                                    <?php
                                    if (!empty($user['user_last_login']) && $user['user_last_login'] !== '0000-00-00 00:00:00') {
                                        $tz = !empty($user['user_timezone']) ? $user['user_timezone'] : 'Asia/Manila';
                                        $dt = new DateTime($user['user_last_login']);
                                        try {
                                            $dt->setTimezone(new DateTimeZone($tz));
                                        } catch (Exception $e) {
                                            $dt->setTimezone(new DateTimeZone('Asia/Manila'));
                                        }
                                        echo $dt->format('F j, Y g:i A');
                                    } else {
                                        echo 'Current Session';
                                    }
                                    ?>
                                </span>
                            </div>

                            <?php
                            // Get recent login activity
                            try {
                                $stmt = $pdo->prepare("SELECT * FROM user_logs WHERE user_id = :user_id AND log_type = 'LOGIN' ORDER BY log_timestamp DESC LIMIT 5");
                                $stmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
                                $stmt->execute();
                                $logins = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            } catch (PDOException $e) {
                                $logins = [];
                            }
                            ?>

                            <?php if ($logins && count($logins) > 0): ?>
                                <h6 class="mt-3">Recent Logins</h6>
                                <div class="list-group list-group-flush">
                                    <?php foreach(array_slice($logins, 0, 5) as $login): ?>
                                    <div class="list-group-item px-0">
                                        <small class="text-muted">
                                            <?php
                                            if (!empty($login['log_timestamp']) && $login['log_timestamp'] !== '0000-00-00 00:00:00') {
                                                $tz = !empty($user['user_timezone']) ? $user['user_timezone'] : 'Asia/Manila';
                                                $dt = new DateTime($login['log_timestamp']);
                                                try {
                                                    $dt->setTimezone(new DateTimeZone($tz));
                                                } catch (Exception $e) {
                                                    $dt->setTimezone(new DateTimeZone('Asia/Manila'));
                                                }
                                                echo $dt->format('M j, Y g:i A');
                                            } else {
                                                echo 'Unknown date';
                                            }
                                            ?>
                                        </small><br>
                                        <small class="text-secondary">
                                            IP: <?php echo !empty($login['log_ip_address']) ? htmlspecialchars($login['log_ip_address']) : 'Unknown'; ?>
                                        </small>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info">
                                    <small>No recent login activity recorded</small>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Account Modal -->
    <div class="modal fade" id="deleteAccountModal" tabindex="-1" aria-labelledby="deleteAccountModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="deleteAccountModalLabel">
                        <i class="bi bi-exclamation-triangle me-2"></i>Delete Account
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger">
                        <strong>Warning:</strong> This action cannot be undone. All your data will be permanently deleted.
                    </div>
                    <p>Are you sure you want to delete your account? This will:</p>
                    <ul>
                        <li>Permanently delete your user account</li>
                        <li>Remove all your personal data</li>
                        <li>Revoke access to all system features</li>
                    </ul>
                    
                    <form action="update_settings.php" method="post" id="delete-account-form">
                        <div class="mb-3">
                            <label for="delete_confirmation" class="form-label">
                                Type <strong>"DELETE"</strong> to confirm <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control" id="delete_confirmation" name="delete_confirmation" 
                                placeholder="Type DELETE here" required autocomplete="off">
                        </div>
                        <div class="mb-3">
                            <label for="delete_password" class="form-label">Enter your password <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" id="delete_password" name="delete_password" 
                                placeholder="Your current password" required>
                        </div>
                        <input type="hidden" name="delete_account" value="1">
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle"></i> Cancel
                    </button>
                    <button type="button" class="btn btn-danger" id="confirmDelete" disabled>
                        <i class="bi bi-trash"></i> Delete Account
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Final Confirmation Modal -->
    <div class="modal fade" id="finalConfirmationModal" tabindex="-1" aria-labelledby="finalConfirmationModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-danger">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="finalConfirmationModalLabel">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>Final Confirmation
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <div class="mb-3">
                        <i class="bi bi-exclamation-triangle-fill text-danger" style="font-size: 3rem;"></i>
                    </div>
                    <h5 class="text-danger mb-3">Are you absolutely sure?</h5>
                    <p class="mb-3">
                        This action <strong>cannot be undone</strong>. Your account and all associated data will be permanently deleted from our servers.
                    </p>
                    <div class="alert alert-danger">
                        <strong>This action is irreversible!</strong>
                    </div>
                </div>
                <div class="modal-footer justify-content-center">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-2"></i>Cancel
                    </button>
                    <button type="button" class="btn btn-danger" id="finalDeleteConfirm">
                        <i class="bi bi-trash-fill me-2"></i>Yes, Delete My Account
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
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

        // Handle delete account confirmation
        const confirmDeleteBtn = document.getElementById('confirmDelete');
        const deleteForm = document.getElementById('delete-account-form');
        const deleteConfirmationInput = document.getElementById('delete_confirmation');
        const deletePasswordInput = document.getElementById('delete_password');

        confirmDeleteBtn.addEventListener('click', function() {
            // Validate confirmation text
            if (deleteConfirmationInput.value.trim() !== 'DELETE') {
                alert('Please type "DELETE" exactly to confirm account deletion.');
                deleteConfirmationInput.focus();
                return;
            }

            // Validate password is entered
            if (deletePasswordInput.value.trim() === '') {
                alert('Please enter your password to confirm account deletion.');
                deletePasswordInput.focus();
                return;
            }

            // Show final confirmation modal instead of browser confirm
            const finalConfirmModal = new bootstrap.Modal(document.getElementById('finalConfirmationModal'));
            finalConfirmModal.show();
        });

        // Handle final confirmation
        document.getElementById('finalDeleteConfirm').addEventListener('click', function() {
            deleteForm.submit();
        });

        // Real-time validation for delete confirmation
        deleteConfirmationInput.addEventListener('input', function() {
            const confirmBtn = document.getElementById('confirmDelete');
            if (this.value.trim() === 'DELETE') {
                confirmBtn.disabled = false;
                this.classList.remove('is-invalid');
                this.classList.add('is-valid');
            } else {
                confirmBtn.disabled = true;
                this.classList.remove('is-valid');
                if (this.value.trim() !== '') {
                    this.classList.add('is-invalid');
                }
            }
        });

        // Form validation for settings update
        const settingsForm = document.querySelector('form[action="update_settings.php"]');
        if (settingsForm) {
            settingsForm.addEventListener('submit', function(e) {
                const password = document.getElementById('current_password_settings').value.trim();
                
                if (password === '') {
                    e.preventDefault();
                    alert('Please enter your current password to save changes.');
                    document.getElementById('current_password_settings').focus();
                    return;
                }
            });
        }
    });
    </script>
</body>
</html>