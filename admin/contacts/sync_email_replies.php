<?php
// filepath: c:\xampp\htdocs\Information Management\IM---Copy\admin\contacts\sync_email_replies.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include "../config/database.php";

// 🔧 CONFIGURATION FOR GMAIL ACCOUNT
$email_config = [
    'host' => 'imap.gmail.com',
    'port' => 993,
    'username' => 'lawrenceramirez626@gmail.com',
    'password' => 'hjlp fqfe pjvt nbjd',   
    'encryption' => 'ssl'
];

// Check if IMAP extension is loaded
if (!extension_loaded('imap')) {
    $error_message = "❌ PHP IMAP extension is not installed.";
    $help_message = "💡 Please enable 'extension=imap' in php.ini and restart Apache.";
    
    if (isset($_GET['test']) || isset($_GET['sync']) || isset($_GET['help'])) {
        echo "<pre>$error_message\n$help_message</pre>";
        exit;
    }
    
    // For HTML page
    $imap_error = true;
}

function testEmailConnection($config) {
    try {
        echo "📡 Testing connection to {$config['host']}:{$config['port']}...\n";
        echo "👤 Account: {$config['username']}\n\n";
        
        $connection_string = "{{$config['host']}:{$config['port']}/imap/ssl}INBOX";
        
        // Suppress PHP warnings for cleaner output
        $inbox = @imap_open(
            $connection_string,
            $config['username'], 
            $config['password']
        );
        
        if ($inbox) {
            $mailbox_info = imap_status($inbox, $connection_string, SA_ALL);
            echo "✅ Connection successful!\n";
            echo "📧 Total messages: " . $mailbox_info->messages . "\n";
            echo "📬 Unread messages: " . $mailbox_info->unseen . "\n";
            echo "📅 Recent messages: " . $mailbox_info->recent . "\n";
            
            // Test search functionality
            echo "\n🔍 Testing search functionality...\n";
            $test_search = imap_search($inbox, 'ALL');
            echo "🔎 Searchable messages: " . (is_array($test_search) ? count($test_search) : 0) . "\n";
            
            imap_close($inbox);
            return true;
        } else {
            $error = imap_last_error();
            echo "❌ Connection failed!\n";
            echo "🔍 Error details: $error\n";
            echo "\n💡 Troubleshooting steps:\n";
            echo "1. Verify Gmail App Password is correct\n";
            echo "2. Ensure 2-Factor Authentication is enabled\n";
            echo "3. Check if IMAP is enabled in Gmail settings\n";
            echo "4. Try generating a new App Password\n";
            return false;
        }
        
    } catch (Exception $e) {
        echo "❌ Error: " . $e->getMessage() . "\n";
        return false;
    }
}

function syncEmailReplies($pdo, $config) {
    try {
        $connection_string = "{{$config['host']}:{$config['port']}/imap/ssl}INBOX";
        
        $inbox = @imap_open(
            $connection_string,
            $config['username'], 
            $config['password']
        );
        
        if (!$inbox) {
            throw new Exception('Cannot connect to email server: ' . imap_last_error());
        }
        
        echo "📡 Connected to Gmail successfully!\n";
        
        // Search for recent emails (last 30 days) with "Re:" in subject
        $search_criteria = 'SINCE "' . date('d-M-Y', strtotime('-30 days')) . '" SUBJECT "Re:"';
        echo "🔍 Searching for replies with criteria: $search_criteria\n";
        
        $emails = imap_search($inbox, $search_criteria);
        
        $updated_count = 0;
        $processed_emails = [];
        $skipped_count = 0;
        
        if ($emails) {
            echo "📧 Found " . count($emails) . " potential reply emails\n\n";
            
            foreach ($emails as $email_id) {
                $overview = imap_fetch_overview($inbox, $email_id, 0);
                $message_body = imap_fetchbody($inbox, $email_id, 1);
                
                // If message is HTML, try to get plain text version
                if (empty(trim($message_body))) {
                    $message_body = imap_fetchbody($inbox, $email_id, 1.1);
                }
                
                // Extract message details
                $subject = $overview[0]->subject ?? '';
                $from_email = $overview[0]->from ?? '';
                $reply_date = $overview[0]->date ?? '';
                
                echo "📝 Processing: " . substr($subject, 0, 50) . "...\n";
                
                $processed_emails[] = [
                    'id' => $email_id,
                    'subject' => $subject,
                    'from' => $from_email,
                    'date' => $reply_date
                ];
                
                // Parse message ID from subject (format: "Re: Your message to NCIP [ID: 123]")
                if (preg_match('/\[ID:\s*(\d+)\]/', $subject, $matches)) {
                    $message_id = (int)$matches[1];
                    echo "   🎯 Found message ID: $message_id\n";
                    
                    // Check if reply already exists
                    $check_stmt = $pdo->prepare("SELECT contm_reply FROM contact_message WHERE contm_id = ?");
                    $check_stmt->execute([$message_id]);
                    $existing = $check_stmt->fetch();
                    
                    if (!$existing || empty($existing['contm_reply'])) {
                        // Clean and format the message
                        $clean_message = strip_tags($message_body);
                        $clean_message = trim($clean_message);
                        
                        // Limit message length to prevent database issues
                        if (strlen($clean_message) > 2000) {
                            $clean_message = substr($clean_message, 0, 2000) . "... [Message truncated]";
                        }
                        
                        // Update database with reply
                        $stmt = $pdo->prepare("
                            UPDATE contact_message 
                            SET contm_reply = :reply_content, 
                                contm_reply_date = :reply_date,
                                contm_status = 'Replied'
                            WHERE contm_id = :message_id
                        ");
                        
                        $stmt->execute([
                            ':reply_content' => "Email reply: " . $clean_message,
                            ':reply_date' => date('Y-m-d H:i:s', strtotime($reply_date)),
                            ':message_id' => $message_id
                        ]);
                        
                        if ($stmt->rowCount() > 0) {
                            $updated_count++;
                            echo "   ✅ Updated message ID: $message_id\n";
                        } else {
                            echo "   ⚠️  No rows updated for message ID: $message_id\n";
                        }
                    } else {
                        $skipped_count++;
                        echo "   ⏭️  Skipped (already has reply): $message_id\n";
                    }
                } else {
                    echo "   ⚠️  No message ID found in subject\n";
                }
                echo "\n";
            }
        } else {
            echo "📭 No reply emails found matching the criteria\n";
        }
        
        imap_close($inbox);
        
        return [
            'success' => true, 
            'message' => "Email sync completed successfully!",
            'emails_found' => count($emails ?? []),
            'updated_count' => $updated_count,
            'skipped_count' => $skipped_count,
            'processed_emails' => $processed_emails
        ];
        
    } catch (Exception $e) {
        error_log("Email sync error: " . $e->getMessage());
        return [
            'success' => false, 
            'message' => $e->getMessage()
        ];
    }
}

// 🧪 TEST MODE
if (isset($_GET['test'])) {
    if (isset($imap_error)) {
        echo "<pre>❌ Cannot test: IMAP extension not loaded</pre>";
        exit;
    }
    
    echo "<pre>";
    echo "🧪 Testing Gmail IMAP Connection\n";
    echo "================================\n\n";
    
    if (testEmailConnection($email_config)) {
        echo "\n🎉 SUCCESS! Your Gmail configuration is working!\n";
        echo "\n💡 Next steps:\n";
        echo "1. Create app password named 'NCIP Contact Sync'\n";
        echo "2. Update the password in this file\n";
        echo "3. Include [ID: XXX] in email subject when replying\n";
        echo "4. Test sync with: sync_email_replies.php?sync\n";
    } else {
        echo "\n❌ FAILED! Please check your configuration.\n";
    }
    echo "</pre>";
    exit;
}

// 🔄 SYNC MODE
if (isset($_GET['sync'])) {
    if (isset($imap_error)) {
        echo "<pre>❌ Cannot sync: IMAP extension not loaded</pre>";
        exit;
    }
    
    echo "<pre>";
    echo "🔄 Starting Email Sync Process...\n";
    echo "=================================\n\n";
    
    $result = syncEmailReplies($pdo, $email_config);
    
    if ($result['success']) {
        echo "\n" . str_repeat("=", 40) . "\n";
        echo "📊 SYNC SUMMARY\n";
        echo str_repeat("=", 40) . "\n";
        echo "✅ " . $result['message'] . "\n";
        echo "📧 Emails scanned: " . ($result['emails_found'] ?? 0) . "\n";
        echo "🔄 Messages updated: " . ($result['updated_count'] ?? 0) . "\n";
        echo "⏭️  Messages skipped: " . ($result['skipped_count'] ?? 0) . "\n";
        
        if (!empty($result['processed_emails'])) {
            echo "\n📋 Processed emails:\n";
            foreach ($result['processed_emails'] as $email) {
                echo "  • " . substr($email['subject'], 0, 60) . "...\n";
                echo "    From: " . $email['from'] . "\n";
                echo "    Date: " . $email['date'] . "\n\n";
            }
        }
        
        if ($result['updated_count'] > 0) {
            echo "🎯 Check your admin panel to see the updated replies!\n";
        }
    } else {
        echo "❌ Sync failed: " . $result['message'] . "\n";
    }
    echo "</pre>";
    exit;
}

// 📋 HELP MODE
if (isset($_GET['help'])) {
    echo "<pre>";
    echo "📧 NCIP Email Reply Sync - Help Guide\n";
    echo "=====================================\n\n";
    echo "📌 SETUP STEPS:\n";
    echo "1. Enable IMAP extension in php.ini\n";
    echo "2. Create Gmail App Password named 'NCIP Contact Sync'\n";
    echo "3. Update password in line 12 of this file\n";
    echo "4. Test connection using ?test parameter\n\n";
    
    echo "🔗 AVAILABLE COMMANDS:\n";
    echo "  ?test  - Test Gmail IMAP connection\n";
    echo "  ?sync  - Sync email replies to database\n";
    echo "  ?help  - Show this help guide\n";
    echo "  (none) - Show web interface\n\n";
    
    echo "💡 HOW TO USE:\n";
    echo "1. When replying to contact messages via Gmail:\n";
    echo "   - Include [ID: XXX] in subject line\n";
    echo "   - Example: 'Re: Your inquiry [ID: 123]'\n\n";
    echo "2. Run sync manually or set up automated sync:\n";
    echo "   - Manual: Visit sync_email_replies.php?sync\n";
    echo "   - Auto: Set up cron job or scheduled task\n\n";
    
    echo "🔍 TROUBLESHOOTING:\n";
    echo "- IMAP extension not loaded: Enable in php.ini\n";
    echo "- Connection failed: Check app password\n";
    echo "- No emails found: Verify subject format [ID: XXX]\n";
    echo "- Database errors: Check PostgreSQL connection\n\n";
    
    echo "📧 GMAIL SETUP:\n";
    echo "1. Go to: myaccount.google.com/security\n";
    echo "2. Enable 2-Step Verification\n";
    echo "3. Generate App Password for 'NCIP Contact Sync'\n";
    echo "4. Use 16-character password (not regular password)\n";
    echo "</pre>";
    exit;
}

// API endpoint for AJAX calls
if ($_SERVER['REQUEST_METHOD'] === 'POST' || php_sapi_name() === 'cli') {
    if (isset($imap_error)) {
        $error_result = ['success' => false, 'message' => 'IMAP extension not loaded'];
        echo json_encode($error_result);
        exit;
    }
    
    $result = syncEmailReplies($pdo, $email_config);
    
    if (php_sapi_name() === 'cli') {
        echo $result['message'] . "\n";
        echo "Updated: " . ($result['updated_count'] ?? 0) . " messages\n";
    } else {
        header('Content-Type: application/json');
        echo json_encode($result);
    }
    exit;
}

// Default web interface
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Reply Sync - NCIP Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .status-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 8px;
        }
        .status-success { background-color: #28a745; }
        .status-warning { background-color: #ffc107; }
        .status-error { background-color: #dc3545; }
    </style>
</head>
<body class="bg-light">
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-10">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h4><i class="bi bi-envelope-sync me-2"></i>NCIP Email Reply Sync</h4>
                        <small>Automatically sync Gmail replies to contact message database</small>
                    </div>
                    <div class="card-body">
                        <!-- Status Indicator -->
                        <div class="alert alert-info d-flex align-items-center">
                            <i class="bi bi-info-circle me-2"></i>
                            <div>
                                <strong>Gmail Account:</strong> <?php echo htmlspecialchars($email_config['username']); ?>
                                <br>
                                <small>
                                    <?php if (isset($imap_error)): ?>
                                        <span class="status-indicator status-error"></span>IMAP Extension: Not Loaded
                                    <?php else: ?>
                                        <span class="status-indicator status-success"></span>IMAP Extension: Ready
                                    <?php endif; ?>
                                </small>
                            </div>
                        </div>
                        
                        <?php if (isset($imap_error)): ?>
                            <div class="alert alert-danger">
                                <i class="bi bi-exclamation-triangle me-2"></i>
                                <strong>Setup Required:</strong> PHP IMAP extension is not enabled. Please enable it in php.ini and restart Apache.
                            </div>
                        <?php endif; ?>
                        
                        <!-- Quick Actions -->
                        <h5><i class="bi bi-lightning me-2"></i>Quick Actions</h5>
                        <div class="row mb-4">
                            <div class="col-md-4">
                                <a href="?test" class="btn btn-outline-primary w-100 mb-2">
                                    <i class="bi bi-wifi me-1"></i>Test Connection
                                </a>
                            </div>
                            <div class="col-md-4">
                                <a href="?sync" class="btn btn-outline-success w-100 mb-2" <?php echo isset($imap_error) ? 'onclick="return false;" style="opacity:0.5;"' : ''; ?>>
                                    <i class="bi bi-arrow-clockwise me-1"></i>Sync Emails Now
                                </a>
                            </div>
                            <div class="col-md-4">
                                <a href="?help" class="btn btn-outline-info w-100 mb-2">
                                    <i class="bi bi-question-circle me-1"></i>Help & Guide
                                </a>
                            </div>
                        </div>
                        
                        <!-- Setup Instructions -->
                        <h5><i class="bi bi-gear me-2"></i>Setup Instructions</h5>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card border-primary">
                                    <div class="card-header bg-primary text-white">
                                        <h6><i class="bi bi-google me-1"></i>Gmail Configuration</h6>
                                    </div>
                                    <div class="card-body">
                                        <ol class="mb-0">
                                            <li>Go to <a href="https://myaccount.google.com/security" target="_blank" class="text-decoration-none">Google Account Security</a></li>
                                            <li>Enable <strong>2-Step Verification</strong></li>
                                            <li>Create <strong>App Password</strong> named <code>NCIP Contact Sync</code></li>
                                            <li>Copy the 16-character password</li>
                                            <li>Update line 12 in this PHP file</li>
                                        </ol>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card border-success">
                                    <div class="card-header bg-success text-white">
                                        <h6><i class="bi bi-envelope me-1"></i>How to Reply</h6>
                                    </div>
                                    <div class="card-body">
                                        <ol class="mb-0">
                                            <li>When replying to contact messages via Gmail</li>
                                            <li>Include message ID in subject line:</li>
                                            <li><code>Re: Your inquiry [ID: 123]</code></li>
                                            <li>Send your reply normally</li>
                                            <li>Run sync to update database</li>
                                        </ol>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Advanced Options -->
                        <div class="mt-4">
                            <h5><i class="bi bi-tools me-2"></i>Advanced Options</h5>
                            <div class="alert alert-secondary">
                                <h6>Automated Sync (Optional)</h6>
                                <p class="mb-2">Set up automatic email sync using Windows Task Scheduler:</p>
                                <code>php "C:\xampp\htdocs\Information Management\IM---Copy\admin\contacts\sync_email_replies.php"</code>
                                <br><small class="text-muted">Run every 15-30 minutes for automatic reply detection</small>
                            </div>
                        </div>
                        
                        <!-- Navigation -->
                        <div class="mt-4 d-flex justify-content-between">
                            <a href="index.php" class="btn btn-secondary">
                                <i class="bi bi-arrow-left me-1"></i>Back to Contact Messages
                            </a>
                            <a href="../recycle-bin/index.php" class="btn btn-outline-secondary">
                                <i class="bi bi-recycle me-1"></i>Recycle Bin
                            </a>
                        </div>
                    </div>
                    <div class="card-footer text-muted text-center">
                        <small>
                            <i class="bi bi-shield-check me-1"></i>
                            Secure email sync powered by Gmail IMAP | Last updated: <?php echo date('Y-m-d H:i:s'); ?>
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>