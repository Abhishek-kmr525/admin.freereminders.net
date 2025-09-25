<?php
// customer/billing.php
require_once '../config/database-config.php';

$pageTitle = 'Billing & Subscription - ' . SITE_NAME;
$pageDescription = 'Manage your subscription and billing information';

// Require customer login
requireCustomerLogin();

$error = '';
$success = '';

// Get customer details
$customer = getCustomerDetails($_SESSION['customer_id']);
$country = getCustomerCountry();
$currencySettings = getCurrencySettings($country);

// Get subscription details
$subscription = null;
$payments = [];

try {
    // Get current subscription
    $stmt = $db->prepare("
        SELECT * FROM subscriptions 
        WHERE customer_id = ? 
        ORDER BY created_at DESC 
        LIMIT 1
    ");
    $stmt->execute([$_SESSION['customer_id']]);
    $subscription = $stmt->fetch();
    
    // Get payment history
    $stmt = $db->prepare("
        SELECT * FROM payments 
        WHERE customer_id = ? 
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    $stmt->execute([$_SESSION['customer_id']]);
    $payments = $stmt->fetchAll();
    
} catch (Exception $e) {
    error_log("Billing page error: " . $e->getMessage());
}

// Handle subscription cancellation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'cancel_subscription') {
        try {
            $stmt = $db->prepare("
                UPDATE subscriptions 
                SET status = 'cancelled', updated_at = NOW() 
                WHERE customer_id = ? AND status = 'active'
            ");
            $stmt->execute([$_SESSION['customer_id']]);
            
            $stmt = $db->prepare("
                UPDATE customers 
                SET subscription_status = 'cancelled', updated_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$_SESSION['customer_id']]);
            
            $_SESSION['subscription_status'] = 'cancelled';
            
            logCustomerActivity($_SESSION['customer_id'], 'subscription_cancelled', 'Subscription cancelled by user');
            $success = 'Your subscription has been cancelled. You can continue using the service until your current billing period ends.';
            
            // Refresh subscription data
            $stmt = $db->prepare("
                SELECT * FROM subscriptions 
                WHERE customer_id = ? 
                ORDER BY created_at DESC 
                LIMIT 1
            ");
            $stmt->execute([$_SESSION['customer_id']]);
            $subscription = $stmt->fetch();
            
        } catch (Exception $e) {
            $error = 'Failed to cancel subscription. Please contact support.';
            error_log("Cancel subscription error: " . $e->getMessage());
        }
    }
}

require_once '../includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="fw-bold">Billing & Subscription</h2>
                <a href="settings.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Back to Settings
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
                <!-- Current Plan -->
                <div class="col-md-8">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-crown me-2"></i>Current Plan
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if ($customer['subscription_status'] === 'trial'): ?>
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <div>
                                        <h4 class="text-primary mb-1">Free Trial</h4>
                                        <p class="text-muted mb-2">
                                            Trial ends: <?php echo date('M j, Y', strtotime($customer['trial_ends_at'])); ?>
                                        </p>
                                        <div class="progress mb-2" style="height: 8px;">
                                            <?php 
                                            $trialDays = getTrialDaysRemaining($_SESSION['customer_id']);
                                            $totalTrialDays = TRIAL_PERIOD_DAYS;
                                            $progressPercent = max(0, ($trialDays / $totalTrialDays) * 100);
                                            ?>
                                            <div class="progress-bar bg-warning" style="width: <?php echo $progressPercent; ?>%"></div>
                                        </div>
                                        <small class="text-muted">
                                            <?php echo $trialDays; ?> days remaining of your free trial
                                        </small>
                                    </div>
                                    <span class="badge bg-warning text-dark fs-6">Trial</span>
                                </div>
                                
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i>
                                    Upgrade to a paid plan to continue using all features after your trial expires.
                                </div>
                                
                                <div class="d-grid gap-2 d-md-flex">
                                    <a href="choose-plan.php" class="btn btn-primary">
                                        <i class="fas fa-crown me-2"></i>Upgrade Now
                                    </a>
                                </div>
                                
                            <?php elseif ($customer['subscription_status'] === 'active'): ?>
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <div>
                                        <h4 class="text-success mb-1">
                                            <?php echo htmlspecialchars($subscription['plan_name'] ?? 'Pro Plan'); ?>
                                        </h4>
                                        <p class="text-muted mb-2">
                                            <?php echo formatPrice($subscription['plan_price'] ?? 0, $country); ?>/month
                                        </p>
                                        <p class="text-muted">
                                            Next billing: <?php echo date('M j, Y', strtotime($subscription['ends_at'] ?? '+1 month')); ?>
                                        </p>
                                    </div>
                                    <span class="badge bg-success fs-6">Active</span>
                                </div>
                                
                                <div class="d-grid gap-2 d-md-flex">
                                    <a href="choose-plan.php" class="btn btn-outline-primary">
                                        <i class="fas fa-exchange-alt me-2"></i>Change Plan
                                    </a>
                                    <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#cancelSubscriptionModal">
                                        <i class="fas fa-times me-2"></i>Cancel Subscription
                                    </button>
                                </div>
                                
                            <?php elseif ($customer['subscription_status'] === 'cancelled'): ?>
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <div>
                                        <h4 class="text-warning mb-1">Cancelled</h4>
                                        <p class="text-muted mb-2">
                                            Service ends: <?php echo date('M j, Y', strtotime($subscription['ends_at'] ?? '+1 month')); ?>
                                        </p>
                                        <p class="text-muted">
                                            You can continue using the service until your current billing period ends.
                                        </p>
                                    </div>
                                    <span class="badge bg-warning text-dark fs-6">Cancelled</span>
                                </div>
                                
                                <div class="d-grid gap-2 d-md-flex">
                                    <a href="choose-plan.php" class="btn btn-success">
                                        <i class="fas fa-undo me-2"></i>Reactivate Subscription
                                    </a>
                                </div>
                                
                            <?php else: ?>
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <div>
                                        <h4 class="text-danger mb-1">Expired</h4>
                                        <p class="text-muted">
                                            Your subscription has expired. Upgrade to continue using all features.
                                        </p>
                                    </div>
                                    <span class="badge bg-danger fs-6">Expired</span>
                                </div>
                                
                                <div class="d-grid gap-2 d-md-flex">
                                    <a href="choose-plan.php" class="btn btn-primary">
                                        <i class="fas fa-crown me-2"></i>Renew Subscription
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Usage Stats -->
                <div class="col-md-4">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-chart-bar me-2"></i>This Month's Usage
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php
                            try {
                                // Get this month's stats
                                $stmt = $db->prepare("
                                    SELECT COUNT(*) as count 
                                    FROM customer_generated_posts 
                                    WHERE customer_id = ? 
                                    AND MONTH(created_at) = MONTH(NOW()) 
                                    AND YEAR(created_at) = YEAR(NOW())
                                ");
                                $stmt->execute([$_SESSION['customer_id']]);
                                $postsThisMonth = $stmt->fetch()['count'];
                                
                                $stmt = $db->prepare("
                                    SELECT COUNT(*) as count 
                                    FROM customer_automations 
                                    WHERE customer_id = ? AND status = 'active'
                                ");
                                $stmt->execute([$_SESSION['customer_id']]);
                                $activeAutomations = $stmt->fetch()['count'];
                                
                            } catch (Exception $e) {
                                $postsThisMonth = 0;
                                $activeAutomations = 0;
                            }
                            ?>
                            
                            <div class="mb-3">
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="text-muted">Posts Generated</span>
                                    <span class="fw-bold"><?php echo $postsThisMonth; ?></span>
                                </div>
                                <div class="progress" style="height: 6px;">
                                    <div class="progress-bar bg-primary" style="width: <?php echo min(100, ($postsThisMonth / 50) * 100); ?>%"></div>
                                </div>
                                <small class="text-muted">of 50 included in trial</small>
                            </div>
                            
                            <div class="mb-3">
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="text-muted">Active Automations</span>
                                    <span class="fw-bold"><?php echo $activeAutomations; ?></span>
                                </div>
                                <div class="progress" style="height: 6px;">
                                    <div class="progress-bar bg-success" style="width: <?php echo min(100, ($activeAutomations / 2) * 100); ?>%"></div>
                                </div>
                                <small class="text-muted">of 2 included in trial</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Payment History -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-receipt me-2"></i>Payment History
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($payments)): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th>Payment ID</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($payments as $payment): ?>
                                        <tr>
                                            <td><?php echo date('M j, Y', strtotime($payment['created_at'])); ?></td>
                                            <td><?php echo formatPrice($payment['amount'], $country); ?></td>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    echo $payment['status'] === 'completed' ? 'success' : 
                                                         ($payment['status'] === 'pending' ? 'warning' : 'danger'); 
                                                ?>">
                                                    <?php echo ucfirst($payment['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <code class="small">
                                                    <?php echo htmlspecialchars(substr($payment['gateway_payment_id'] ?? 'N/A', 0, 20)); ?>
                                                </code>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-primary" onclick="downloadInvoice('<?php echo $payment['id']; ?>')">
                                                    <i class="fas fa-download"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-receipt fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">No payment history</h5>
                            <p class="text-muted">You haven't made any payments yet.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Cancel Subscription Modal -->
<div class="modal fade" id="cancelSubscriptionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title">
                    <i class="fas fa-exclamation-triangle me-2"></i>Cancel Subscription
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="cancel_subscription">
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <strong>Are you sure you want to cancel?</strong>
                    </div>
                    
                    <p>If you cancel your subscription:</p>
                    <ul>
                        <li>You can continue using the service until your current billing period ends</li>
                        <li>Your automations will stop working after the billing period</li>
                        <li>You'll lose access to premium features</li>
                        <li>Your data will be preserved in case you reactivate</li>
                    </ul>
                    
                    <p class="text-muted">You can reactivate your subscription at any time.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Keep Subscription</button>
                    <button type="submit" class="btn btn-warning">
                        <i class="fas fa-times me-2"></i>Cancel Subscription
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function downloadInvoice(paymentId) {
    // In a real implementation, this would generate and download an invoice
    alert('Invoice download feature coming soon! Payment ID: ' + paymentId);
}
</script>

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

.progress {
    border-radius: 10px;
}

.badge {
    font-size: 0.8em;
}

.modal-content {
    border-radius: 15px;
}
</style>

<?php require_once '../includes/footer.php'; ?>