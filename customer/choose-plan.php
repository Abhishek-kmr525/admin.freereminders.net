<?php
// customer/choose-plan.php
require_once '../config/database-config.php';
require_once '../config/payment-config.php';

$pageTitle = 'Choose Your Plan - ' . SITE_NAME;
$pageDescription = 'Select the perfect plan for your LinkedIn automation needs';

// Require customer login
requireCustomerLogin();

// Check if customer already has active subscription
if (hasActiveSubscription($_SESSION['customer_id'])) {
    $_SESSION['success_message'] = 'You already have an active subscription!';
    redirectTo('dashboard.php');
}

$error = '';
$country = getCustomerCountry();
$currencySettings = getCurrencySettings($country);

// Get pricing plans
$pricingPlans = getCustomerPricingPlans($country);

require_once '../includes/header.php';
?>

<div class="container py-5">
    <div class="row">
        <div class="col-lg-8 mx-auto">
            <!-- Header -->
            <div class="text-center mb-5">
                <h2 class="display-6 fw-bold">Choose Your Plan</h2>
                <p class="lead text-muted">
                    Select the perfect plan for your LinkedIn automation needs in <?php echo $currencySettings['name']; ?>
                </p>
                <div class="alert alert-info">
                    <i class="fas fa-clock me-2"></i>
                    Your free trial is active until <?php echo date('M j, Y', strtotime($_SESSION['customer']['trial_ends_at'] ?? '+14 days')); ?>
                </div>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <!-- Pricing Plans -->
            <div class="row g-4">
                <?php if (!empty($pricingPlans)): ?>
                    <?php foreach ($pricingPlans as $index => $plan): ?>
                        <div class="col-md-4">
                            <div class="card pricing-card h-100 <?php echo $index === 1 ? 'border-primary featured' : ''; ?>">
                                <?php if ($index === 1): ?>
                                    <div class="ribbon">
                                        <span class="badge bg-primary">Most Popular</span>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="card-header text-center py-4">
                                    <h3 class="fw-bold"><?php echo htmlspecialchars($plan['plan_name']); ?></h3>
                                    <div class="display-6 fw-bold text-primary">
                                        <?php echo formatPrice($plan['plan_price'], $country); ?>
                                    </div>
                                    <div class="text-muted">per month</div>
                                </div>
                                
                                <div class="card-body">
                                    <ul class="list-unstyled">
                                        <?php 
                                        $features = explode(',', $plan['features']);
                                        foreach ($features as $feature): 
                                        ?>
                                            <li class="mb-3">
                                                <i class="fas fa-check text-success me-2"></i>
                                                <?php echo htmlspecialchars(trim($feature)); ?>
                                            </li>
                                        <?php endforeach; ?>
                                        
                                        <?php if ($plan['max_posts_per_month'] > 0): ?>
                                            <li class="mb-3">
                                                <i class="fas fa-check text-success me-2"></i>
                                                <?php echo number_format($plan['max_posts_per_month']); ?> posts per month
                                            </li>
                                        <?php else: ?>
                                            <li class="mb-3">
                                                <i class="fas fa-check text-success me-2"></i>
                                                Unlimited posts
                                            </li>
                                        <?php endif; ?>
                                        
                                        <?php if ($plan['max_automations'] > 0): ?>
                                            <li class="mb-3">
                                                <i class="fas fa-check text-success me-2"></i>
                                                <?php echo $plan['max_automations']; ?> automation<?php echo $plan['max_automations'] > 1 ? 's' : ''; ?>
                                            </li>
                                        <?php else: ?>
                                            <li class="mb-3">
                                                <i class="fas fa-check text-success me-2"></i>
                                                Unlimited automations
                                            </li>
                                        <?php endif; ?>
                                    </ul>
                                </div>
                                
                                <div class="card-footer text-center">
                                    <form method="POST" action="payment.php">
                                        <input type="hidden" name="plan_id" value="<?php echo $plan['id']; ?>">
                                        <input type="hidden" name="plan_name" value="<?php echo htmlspecialchars($plan['plan_name']); ?>">
                                        <input type="hidden" name="amount" value="<?php echo $plan['plan_price']; ?>">
                                        <input type="hidden" name="currency" value="<?php echo $plan['currency']; ?>">
                                        
                                        <button type="submit" class="btn <?php echo $index === 1 ? 'btn-primary' : 'btn-outline-primary'; ?> w-100 btn-lg">
                                            <i class="fas fa-credit-card me-2"></i>
                                            Choose This Plan
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <!-- Fallback plans if database not set up -->
                    <div class="col-md-4">
                        <div class="card pricing-card h-100">
                            <div class="card-header text-center py-4">
                                <h3 class="fw-bold">Basic</h3>
                                <div class="display-6 fw-bold text-primary">
                                    <?php echo $country === 'in' ? '₹1,499' : '$19'; ?>
                                </div>
                                <div class="text-muted">per month</div>
                            </div>
                            <div class="card-body">
                                <ul class="list-unstyled">
                                    <li class="mb-3"><i class="fas fa-check text-success me-2"></i>50 posts per month</li>
                                    <li class="mb-3"><i class="fas fa-check text-success me-2"></i>AI Content Generation</li>
                                    <li class="mb-3"><i class="fas fa-check text-success me-2"></i>Email Support</li>
                                    <li class="mb-3"><i class="fas fa-check text-success me-2"></i>2 Automations</li>
                                </ul>
                            </div>
                            <div class="card-footer text-center">
                                <form method="POST" action="payment.php">
                                    <input type="hidden" name="plan_name" value="Basic">
                                    <input type="hidden" name="amount" value="<?php echo $country === 'in' ? '1499' : '19'; ?>">
                                    <input type="hidden" name="currency" value="<?php echo $country === 'in' ? 'INR' : 'USD'; ?>">
                                    <button type="submit" class="btn btn-outline-primary w-100 btn-lg">
                                        <i class="fas fa-credit-card me-2"></i>Choose This Plan
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="card pricing-card h-100 border-primary featured">
                            <div class="ribbon">
                                <span class="badge bg-primary">Most Popular</span>
                            </div>
                            <div class="card-header text-center py-4">
                                <h3 class="fw-bold">Pro</h3>
                                <div class="display-6 fw-bold text-primary">
                                    <?php echo $country === 'in' ? '₹3,999' : '$49'; ?>
                                </div>
                                <div class="text-muted">per month</div>
                            </div>
                            <div class="card-body">
                                <ul class="list-unstyled">
                                    <li class="mb-3"><i class="fas fa-check text-success me-2"></i>200 posts per month</li>
                                    <li class="mb-3"><i class="fas fa-check text-success me-2"></i>Advanced AI (GPT-4 & Gemini)</li>
                                    <li class="mb-3"><i class="fas fa-check text-success me-2"></i>Priority Support</li>
                                    <li class="mb-3"><i class="fas fa-check text-success me-2"></i>5 Automations</li>
                                    <li class="mb-3"><i class="fas fa-check text-success me-2"></i>Analytics Dashboard</li>
                                </ul>
                            </div>
                            <div class="card-footer text-center">
                                <form method="POST" action="payment.php">
                                    <input type="hidden" name="plan_name" value="Pro">
                                    <input type="hidden" name="amount" value="<?php echo $country === 'in' ? '3999' : '49'; ?>">
                                    <input type="hidden" name="currency" value="<?php echo $country === 'in' ? 'INR' : 'USD'; ?>">
                                    <button type="submit" class="btn btn-primary w-100 btn-lg">
                                        <i class="fas fa-credit-card me-2"></i>Choose This Plan
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="card pricing-card h-100">
                            <div class="card-header text-center py-4">
                                <h3 class="fw-bold">Enterprise</h3>
                                <div class="display-6 fw-bold text-primary">
                                    <?php echo $country === 'in' ? '₹7,999' : '$99'; ?>
                                </div>
                                <div class="text-muted">per month</div>
                            </div>
                            <div class="card-body">
                                <ul class="list-unstyled">
                                    <li class="mb-3"><i class="fas fa-check text-success me-2"></i>Unlimited posts</li>
                                    <li class="mb-3"><i class="fas fa-check text-success me-2"></i>All AI Models</li>
                                    <li class="mb-3"><i class="fas fa-check text-success me-2"></i>24/7 Phone Support</li>
                                    <li class="mb-3"><i class="fas fa-check text-success me-2"></i>Unlimited Automations</li>
                                    <li class="mb-3"><i class="fas fa-check text-success me-2"></i>API Access</li>
                                    <li class="mb-3"><i class="fas fa-check text-success me-2"></i>White-label Options</li>
                                </ul>
                            </div>
                            <div class="card-footer text-center">
                                <form method="POST" action="payment.php">
                                    <input type="hidden" name="plan_name" value="Enterprise">
                                    <input type="hidden" name="amount" value="<?php echo $country === 'in' ? '7999' : '99'; ?>">
                                    <input type="hidden" name="currency" value="<?php echo $country === 'in' ? 'INR' : 'USD'; ?>">
                                    <button type="submit" class="btn btn-outline-primary w-100 btn-lg">
                                        <i class="fas fa-credit-card me-2"></i>Choose This Plan
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Trial Extension Option -->
            <div class="text-center mt-5">
                <div class="card bg-light">
                    <div class="card-body py-4">
                        <h5 class="fw-bold mb-3">Need More Time to Decide?</h5>
                        <p class="text-muted mb-3">Continue with your free trial and upgrade when you're ready.</p>
                        <a href="dashboard.php" class="btn btn-outline-secondary me-3">
                            <i class="fas fa-arrow-left me-2"></i>Continue Trial
                        </a>
                        <small class="text-muted">
                            <i class="fas fa-shield-alt me-1"></i>
                            No credit card required • Cancel anytime • Secure payments by Razorpay
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.pricing-card {
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.pricing-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.15);
}

.pricing-card.featured {
    transform: scale(1.02);
}

.pricing-card.featured:hover {
    transform: scale(1.02) translateY(-5px);
}

.ribbon {
    position: absolute;
    top: 15px;
    right: -8px;
    z-index: 1;
}

.ribbon .badge {
    padding: 8px 20px;
    border-radius: 0;
    font-size: 0.75rem;
    letter-spacing: 0.5px;
}

.card-header {
    border-bottom: 1px solid #eee;
}

.card-footer {
    border-top: 1px solid #eee;
    background: transparent;
}
</style>

<?php require_once '../includes/footer.php'; ?>