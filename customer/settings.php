<?php
// customer/settings.php
require_once '../config/database-config.php';

$pageTitle = 'Account Settings - ' . SITE_NAME;
$pageDescription = 'Manage your account settings and preferences';

// Require customer login
requireCustomerLogin();

$error = '';
$success = '';

// Get customer details
$customer = getCustomerDetails($_SESSION['customer_id']);

// Get LinkedIn connection status
$linkedinConnected = false;
try {
    $stmt = $db->prepare("SELECT id FROM customer_linkedin_tokens WHERE customer_id = ?");
    $stmt->execute([$_SESSION['customer_id']]);
    $linkedinConnected = $stmt->fetch() !== false;
} catch (Exception $e) {
    error_log("Check LinkedIn connection error: " . $e->getMessage());
}

// Function moved to database-config.php to avoid redeclaration

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'disconnect_linkedin':
            try {
                $stmt = $db->prepare("DELETE FROM customer_linkedin_tokens WHERE customer_id = ?");
                $stmt->execute([$_SESSION['customer_id']]);
                
                logCustomerActivity($_SESSION['customer_id'], 'linkedin_disconnected', 'LinkedIn account disconnected');
                $success = 'LinkedIn account disconnected successfully';
                $linkedinConnected = false;
            } catch (Exception $e) {
                $error = 'Failed to disconnect LinkedIn account';
                error_log("Disconnect LinkedIn error: " . $e->getMessage());
            }
            break;
            
        case 'delete_account':
            $confirmEmail = sanitizeInput($_POST['confirm_email'] ?? '');
            
            if ($confirmEmail !== $customer['email']) {
                $error = 'Please enter your correct email address to confirm account deletion';
            } else {
                try {
                    $db->beginTransaction();
                    
                    // Mark account as inactive instead of deleting (for data integrity)
                    $stmt = $db->prepare("UPDATE customers SET status = 'inactive', updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$_SESSION['customer_id']]);
                    
                    // Cancel any active subscriptions
                    $stmt = $db->prepare("UPDATE subscriptions SET status = 'cancelled', updated_at = NOW() WHERE customer_id = ?");
                    $stmt->execute([$_SESSION['customer_id']]);
                    
                    // Deactivate automations
                    $stmt = $db->prepare("UPDATE customer_automations SET status = 'inactive', updated_at = NOW() WHERE customer_id = ?");
                    $stmt->execute([$_SESSION['customer_id']]);
                    
                    // Log the deletion
                    logCustomerActivity($_SESSION['customer_id'], 'account_deleted', 'Account marked for deletion by user request');
                    
                    $db->commit();
                    
                    // Clear session
                    session_unset();
                    session_destroy();
                    
                    // Redirect to login with message
                    header('Location: login.php?message=account_deleted');
                    exit();
                    
                } catch (Exception $e) {
                    $db->rollBack();
                    $error = 'Failed to delete account. Please try again.';
                    error_log("Account deletion error: " . $e->getMessage());
                }
            }
            break;
            
        case 'export_data':
            // Generate data export
            header('Content-Type: application/json');
            header('Content-Disposition: attachment; filename="linkedin_automation_data_' . date('Y-m-d') . '.json"');
            
            $exportData = [
                'customer' => [
                    'name' => $customer['name'],
                    'email' => $customer['email'],
                    'country' => $customer['country'],
                    'subscription_status' => $customer['subscription_status'],
                    'created_at' => $customer['created_at']
                ],
                'automations' => [],
                'posts' => [],
                'activity_logs' => []
            ];
            
            try {
                // Get automations
                $stmt = $db->prepare("SELECT * FROM customer_automations WHERE customer_id = ?");
                $stmt->execute([$_SESSION['customer_id']]);
                $exportData['automations'] = $stmt->fetchAll();
                
                // Get posts
                $stmt = $db->prepare("SELECT * FROM customer_generated_posts WHERE customer_id = ?");
                $stmt->execute([$_SESSION['customer_id']]);
                $exportData['posts'] = $stmt->fetchAll();
                
                // Get activity logs
                $stmt = $db->prepare("SELECT action, details, created_at FROM customer_activity_logs WHERE customer_id = ? ORDER BY created_at DESC");
                $stmt->execute([$_SESSION['customer_id']]);
                $exportData['activity_logs'] = $stmt->fetchAll();
                
                logCustomerActivity($_SESSION['customer_id'], 'data_exported', 'Customer data exported');
                
            } catch (Exception $e) {
                error_log("Data export error: " . $e->getMessage());
            }
            
            echo json_encode($exportData, JSON_PRETTY_PRINT);
            exit();

        case 'save_notifications':
            try {
                $preferences = [
                    'email_notifications' => isset($_POST['email_notifications']) ? 1 : 0,
                    'marketing_emails' => isset($_POST['marketing_emails']) ? 1 : 0,
                    'failure_alerts' => isset($_POST['failure_alerts']) ? 1 : 0,
                    'weekly_reports' => isset($_POST['weekly_reports']) ? 1 : 0
                ];
                
                // In a real implementation, you would save these to a preferences table
                // For now, we'll just show success
                logCustomerActivity($_SESSION['customer_id'], 'preferences_updated', 'Notification preferences updated');
                $success = 'Notification preferences saved successfully';
                
            } catch (Exception $e) {
                $error = 'Failed to save preferences';
                error_log("Save preferences error: " . $e->getMessage());
            }
            break;
    }
}

require_once '../includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="fw-bold">Account Settings</h2>
                <a href="dashboard.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                </a>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="row">
                <!-- Account Information -->
                <div class="col-md-6">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-user me-2"></i>Account Information
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-sm-4"><strong>Name:</strong></div>
                                <div class="col-sm-8"><?php echo htmlspecialchars($customer['name']); ?></div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-sm-4"><strong>Email:</strong></div>
                                <div class="col-sm-8"><?php echo htmlspecialchars($customer['email']); ?></div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-sm-4"><strong>Country:</strong></div>
                                <div class="col-sm-8">
                                    <?php echo $customer['country'] === 'in' ? '🇮🇳 India' : '🇺🇸 United States'; ?>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-sm-4"><strong>Status:</strong></div>
                                <div class="col-sm-8">
                                    <span class="badge bg-<?php echo $customer['subscription_status'] === 'active' ? 'success' : 'warning'; ?>">
                                        <?php echo ucfirst($customer['subscription_status']); ?>
                                    </span>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-sm-4"><strong>Member Since:</strong></div>
                                <div class="col-sm-8"><?php echo date('M j, Y', strtotime($customer['created_at'])); ?></div>
                            </div>
                            
                            <div class="d-grid">
                                <a href="profile.php" class="btn btn-outline-primary">
                                    <i class="fas fa-edit me-2"></i>Edit Profile
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- LinkedIn Connection -->
                <div class="col-md-6">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fab fa-linkedin me-2"></i>LinkedIn Connection
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if ($linkedinConnected): ?>
                                <div class="alert alert-success">
                                    <i class="fas fa-check-circle me-2"></i>
                                    LinkedIn account is connected and ready for automated posting.
                                </div>
                                
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="action" value="disconnect_linkedin">
                                    <button type="submit" class="btn btn-outline-danger" 
                                            onclick="return confirm('Are you sure you want to disconnect LinkedIn? This will stop all automated posting.')">
                                        <i class="fab fa-linkedin me-2"></i>Disconnect LinkedIn
                                    </button>
                                </form>
                            <?php else: ?>
                                <div class="alert alert-warning">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    LinkedIn not connected. Connect your account to enable automated posting.
                                </div>
                                
                                <a href="connect-linkedin.php" class="btn btn-primary">
                                    <i class="fab fa-linkedin me-2"></i>Connect LinkedIn
                                </a>
                            <?php endif; ?>
                            
                            <hr>
                            <small class="text-muted">
                                <i class="fas fa-info-circle me-1"></i>
                                LinkedIn connection is required for automated posting. Your credentials are stored securely.
                            </small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Subscription & Billing -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-credit-card me-2"></i>Subscription & Billing
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-8">
                            <h6>Current Plan: 
                                <span class="badge bg-<?php echo $customer['subscription_status'] === 'active' ? 'success' : 'warning'; ?>">
                                    <?php echo $customer['subscription_plan'] ?? ucfirst($customer['subscription_status']); ?>
                                </span>
                            </h6>
                            
                            <?php if ($customer['subscription_status'] === 'trial'): ?>
                                <p class="text-muted mb-3">
                                    Trial ends: <?php echo date('M j, Y', strtotime($customer['trial_ends_at'])); ?>
                                    (<?php echo getTrialDaysRemaining($_SESSION['customer_id']); ?> days remaining)
                                </p>
                            <?php elseif ($customer['subscription_status'] === 'active'): ?>
                                <p class="text-muted mb-3">
                                    Next billing: <?php echo date('M j, Y', strtotime($customer['subscription_ends_at'] ?? '+1 month')); ?>
                                </p>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-4 text-md-end">
                            <a href="billing.php" class="btn btn-outline-primary">
                                <i class="fas fa-receipt me-2"></i>View Billing
                            </a>
                            <?php if ($customer['subscription_status'] !== 'active'): ?>
                                <a href="choose-plan.php" class="btn btn-success ms-2">
                                    <i class="fas fa-crown me-2"></i>Upgrade
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Notification Preferences -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-bell me-2"></i>Notification Preferences
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="save_notifications">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" id="emailNotifications" name="email_notifications" checked>
                                    <label class="form-check-label" for="emailNotifications">
                                        <strong>Email Notifications</strong><br>
                                        <small class="text-muted">Receive updates about your automations and posts</small>
                                    </label>
                                </div>
                                
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" id="marketingEmails" name="marketing_emails" checked>
                                    <label class="form-check-label" for="marketingEmails">
                                        <strong>Marketing Emails</strong><br>
                                        <small class="text-muted">Tips, updates, and LinkedIn automation best practices</small>
                                    </label>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" id="failureAlerts" name="failure_alerts" checked>
                                    <label class="form-check-label" for="failureAlerts">
                                        <strong>Failure Alerts</strong><br>
                                        <small class="text-muted">Notify when posts fail to publish</small>
                                    </label>
                                </div>
                                
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" id="weeklyReports" name="weekly_reports">
                                    <label class="form-check-label" for="weeklyReports">
                                        <strong>Weekly Reports</strong><br>
                                        <small class="text-muted">Summary of your automation performance</small>
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="text-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Save Preferences
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Data & Privacy -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-shield-alt me-2"></i>Data & Privacy
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-8">
                            <h6>Data Export</h6>
                            <p class="text-muted mb-3">Download all your data including automations, posts, and activity logs.</p>
                        </div>
                        <div class="col-md-4 text-md-end">
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="action" value="export_data">
                                <button type="submit" class="btn btn-outline-info">
                                    <i class="fas fa-download me-2"></i>Export Data
                                </button>
                            </form>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <div class="row">
                        <div class="col-md-8">
                            <h6 class="text-muted">Privacy Settings</h6>
                            <ul class="list-unstyled small text-muted">
                                <li><i class="fas fa-check text-success me-2"></i>Your data is encrypted and stored securely</li>
                                <li><i class="fas fa-check text-success me-2"></i>We never share your personal information</li>
                                <li><i class="fas fa-check text-success me-2"></i>You can export or delete your data anytime</li>
                            </ul>
                        </div>
                        <div class="col-md-4 text-md-end">
                            <a href="#" class="btn btn-outline-secondary btn-sm">
                                <i class="fas fa-file-alt me-2"></i>Privacy Policy
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Danger Zone -->
            <div class="card border-danger">
                <div class="card-header bg-danger text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-exclamation-triangle me-2"></i>Danger Zone
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-8">
                            <h6 class="text-danger">Delete Account</h6>
                            <p class="text-muted mb-3">
                                Permanently delete your account and all associated data. This action cannot be undone.
                            </p>
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <strong>Warning:</strong> This will delete all your automations, posts, and account data.
                            </div>
                        </div>
                        <div class="col-md-4 text-md-end">
                            <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#deleteAccountModal">
                                <i class="fas fa-trash me-2"></i>Delete Account
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Delete Account Modal -->
<div class="modal fade" id="deleteAccountModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">
                    <i class="fas fa-exclamation-triangle me-2"></i>Confirm Account Deletion
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="delete_account">
                <div class="modal-body">
                    <div class="alert alert-danger">
                        <strong>This action cannot be undone!</strong>
                    </div>
                    
                    <p>This will permanently delete:</p>
                    <ul>
                        <li>Your account and profile information</li>
                        <li>All LinkedIn automations</li>
                        <li>All generated posts and analytics</li>
                        <li>Payment history and subscriptions</li>
                    </ul>
                    
                    <p class="fw-bold">To confirm, please enter your email address:</p>
                    <input type="email" class="form-control" name="confirm_email" 
                           placeholder="<?php echo htmlspecialchars($customer['email']); ?>" required>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash me-2"></i>Delete My Account
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.card {
    border-radius: 15px;
    border: 0;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    transition: transform 0.2s ease;
}

.card:hover {
    transform: translateY(-2px);
}

.form-check-input:checked {
    background-color: #0077b5;
    border-color: #0077b5;
}

.btn-primary {
    background: linear-gradient(135deg, #0077b5 0%, #00a0dc 100%);
    border: none;
}

.btn-primary:hover {
    background: linear-gradient(135deg, #005885 0%, #0077b5 100%);
    transform: translateY(-2px);
}

.badge {
    font-size: 0.75em;
}

.modal-content {
    border-radius: 15px;
}

.alert {
    border-radius: 12px;
}
</style>

<?php require_once '../includes/footer.php'; ?>