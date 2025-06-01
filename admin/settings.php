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

// Get user data (column names must match your DB: user_created, user_timezone, etc.)
$stmt = $pdo->prepare("SELECT * FROM USERS WHERE USER_ID = :user_id");
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
        .main-content {
            margin-left: 250px;
            padding: 20px;
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
    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="container mt-4">
            <div class="row">
                <div class="col-md-12 mb-4">
                    <h2>Account Settings</h2>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                            <li class="breadcrumb-item active">Settings</li>
                        </ol>
                    </nav>
                </div>
            </div>

            <?php if ($success_message): ?>
            <div class="alert alert-success"><?php echo $success_message; ?></div>
            <?php endif; ?>

            <?php if ($error_message): ?>
            <div class="alert alert-danger"><?php echo $error_message; ?></div>
            <?php endif; ?>

            <div class="row">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title">General Settings</h5>
                        </div>
                        <div class="card-body">
                            <form action="update_settings.php" method="post">
                                <div class="mb-3">
                                    <label for="timezone" class="form-label">Timezone</label>
                                    <select class="form-control" id="timezone" name="timezone">
                                        <?php foreach($timezones as $value => $label): ?>
                                            <option value="<?php echo $value; ?>" <?php echo (isset($user['user_timezone']) && $user['user_timezone'] == $value) ? 'selected' : ''; ?>>
                                                <?php echo $label; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <?php if(isset($user['user_role']) && $user['user_role'] == 'ADMIN'): ?>
                                <div class="mb-3">
                                    <label class="form-label">Account Type</label>
                                    <div class="form-control bg-light"><?php echo htmlspecialchars($user['user_role']); ?></div>
                                    <div class="form-text">Administrator accounts have additional permissions.</div>
                                </div>
                                <?php endif; ?>

                                <div class="mb-3">
                                    <label for="current_password_settings" class="form-label">Current Password (required to save changes)</label>
                                    <input type="password" class="form-control" id="current_password_settings" name="current_password" required>
                                </div>

                                <button type="submit" name="update_settings" class="btn btn-primary">Save Settings</button>
                            </form>
                        </div>
                    </div>

                    <div class="card mt-4">
                        <div class="card-header bg-danger text-white">
                            <h5 class="card-title mb-0">Danger Zone</h5>
                        </div>
                        <div class="card-body">
                            <h6>Delete Account</h6>
                            <p>Once you delete your account, there is no going back. Please be certain.</p>
                            <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#deleteAccountModal">
                                Delete Account
                            </button>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title">Account Activity</h5>
                        </div>
                        <div class="card-body">
                            <p>
                                <strong>Account Created:</strong>
                                <?php
                                if (!empty($user['user_created']) && $user['user_created'] !== '0000-00-00 00:00:00') {
                                    // Format using user's timezone if available
                                    $tz = !empty($user['user_timezone']) ? $user['user_timezone'] : 'Asia/Manila';
                                    $dt = new DateTime($user['user_created']);
                                    try {
                                        $dt->setTimezone(new DateTimeZone($tz));
                                    } catch (Exception $e) {
                                        $dt->setTimezone(new DateTimeZone('Asia/Manila'));
                                    }
                                    echo $dt->format('F j, Y g:i A');
                                } else {
                                    echo '<span class="text-muted">Unknown</span>';
                                }
                                ?>
                            </p>

                            <?php
                            // Get recent login activity
                            $stmt = $pdo->prepare("SELECT * FROM USER_LOGS WHERE USER_ID = :user_id AND LOG_TYPE = 'LOGIN' ORDER BY LOG_TIMESTAMP DESC LIMIT 5");
                            $stmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
                            $stmt->execute();
                            $logins = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            ?>

                            <?php if ($logins && count($logins) > 0): ?>
                                <h6>Recent Logins</h6>
                                <ul class="list-group">
                                    <?php foreach($logins as $login): ?>
                                    <li class="list-group-item">
                                        <small>
                                            <?php
                                            if (!empty($login['log_timestamp']) && $login['log_timestamp'] !== '0000-00-00 00:00:00') {
                                                // Format using user's timezone if available
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
                                        <small>IP: <?php echo !empty($login['log_ip_address']) ? htmlspecialchars($login['log_ip_address']) : 'Unknown'; ?></small>
                                    </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <p class="text-muted">No recent login activity</p>
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
                    <h5 class="modal-title" id="deleteAccountModalLabel">Delete Account</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete your account? This action cannot be undone.</p>
                    <form action="update_settings.php" method="post" id="delete-account-form">
                        <div class="mb-3">
                            <label for="delete_confirmation" class="form-label">Type "DELETE" to confirm</label>
                            <input type="text" class="form-control" id="delete_confirmation" name="delete_confirmation" required>
                        </div>
                        <div class="mb-3">
                            <label for="delete_password" class="form-label">Enter your password</label>
                            <input type="password" class="form-control" id="delete_password" name="delete_password" required>
                        </div>
                        <input type="hidden" name="delete_account" value="1">
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmDelete">Delete Account</button>
                </div>
            </div>
        </div>
    </div>

    <footer class="text-center mt-4 mb-2 text-muted">
        &copy; 2025 NCIP Admin Dashboard. All rights reserved.
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Confirm delete account modal
        document.getElementById('confirmDelete').addEventListener('click', function() {
            document.getElementById('delete-account-form').submit();
        });
    });
    </script>
</body>
</html>