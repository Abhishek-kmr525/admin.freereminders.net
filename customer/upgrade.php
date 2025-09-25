<?php
// customer/upgrade.php
require_once '../config/database-config.php';
require_once '../config/payment-config.php';

$pageTitle = 'Upgrade Your Plan - ' . SITE_NAME;
$pageDescription = 'Upgrade to unlock all features and grow your LinkedIn presence';

// Require customer login
requireCustomerLogin();

$error = '';
$success = '';
$country = getCustomerCountry();
$currencySettings = getCurrencySettings($country);

// Get customer details
$customer = getCustomerDetails($_SESSION['customer_id']);

// Get current usage stats
$stats = [
    'posts_this_month' => 0,
    'active_automations' => 0,
    'total_posts' => 0
];

try {
    // Get this month's posts
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM customer_generated_posts 
        WHERE customer_id = ? 
        AND MONTH(created_at) = MONTH(NOW()) 
        AND YEAR(created_at) = YEAR(NOW())
    ");
    $stmt->execute([$_SESSION['customer_id']]);
    $stats['posts_this_month'] = $stmt->fetch()['count'];
    
    // Get active automations
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM customer_automations 
        WHERE customer_id = ? AND status = 'active'
    ");
    $stmt->execute([$_SESSION['customer_id']]);
    $stats['active_automations'] = $stmt->fetch()['count'];
    
    // Get total posts
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM customer_generated_posts 
        WHERE customer_id = ?
    ");
    $stmt->execute([$_SESSION['customer_id']]);
    $stats['total_posts'] = $stmt->fetch()['count'];
    
} catch (Exception $e) {
    error_log("Upgrade page stats error: " . $e->getMessage());
}

// Get pricing plans
$pricingPlans = [];
try {
    $stmt = $db->prepare("
        SELECT * FROM pricing_plans 
        WHERE country = ? AND is_active = 1 
        ORDER BY plan_price ASC
    ");
    $stmt->execute([$country]);
    $pricingPlans = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Get pricing plans error: " . $e->getMessage());
}

require_once '../includes/header.php';
?>

<div class="hero-gradient">
    <div class="container py-5">
        <div class="row align-items-center min-vh-50">
            <div class="col-lg-6">
                <div class="text-white">
                    <h1 class="display-5 fw-bold mb-4">
                        Ready to Supercharge Your LinkedIn?
                    </h1>
                    <p class="lead mb-4 opacity-90">
                        Upgrade now and unlock unlimited posts, advanced AI features, and premium analytics to grow your professional network faster.
                    </p>
                    
                    <?php if ($customer['subscription_status'] === 'trial'): ?>
                        <div class="alert alert-warning bg-warning bg-opacity-25 border-warning text-white">
                            <i class="fas fa-clock me-2"></i>
                            <strong>Trial Ending Soon!</strong> 
                            <?php echo getTrialDaysRemaining($_SESSION['customer_id']); ?> days left in your free trial.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="text-center">
                    <div class="upgrade-stats bg-white rounded-4 shadow-lg p-4 mx-auto" style="max-width: 400px;">
                        <h5 class="text-dark fw-bold mb-3">Your Current Usage</h5>
                        <div class="row g-3">
                            <div class="col-4">
                                <div class="text-center">
                                    <div class="h3 text-primary fw-bold mb-0"><?php echo $stats['posts_this_month']; ?></div>
                                    <small class="text-muted">Posts This Month</small>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="text-center">
                                    <div class="h3 text-success fw-bold mb-0"><?php echo $stats['active_automations']; ?></div>
                                    <small class="text-muted">Active Automations</small>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="text-center">
                                    <div class="h3 text-info fw-bold mb-0"><?php echo $stats['total_posts']; ?></div>
                                    <small class="text-muted">Total Posts</small>
                                </div>
                            </div>
                        </div>
                        
                        <?php if ($customer['subscription_status'] === 'trial'): ?>
                            <div class="mt-3">
                                <div class="progress mb-2" style="height: 8px;">
                                    <?php 
                                    $trialDays = getTrialDaysRemaining($_SESSION['customer_id']);
                                    $progressPercent = max(0, ($trialDays / TRIAL_PERIOD_DAYS) * 100);
                                    ?>
                                    <div class="progress-bar bg-warning" style="width: <?php echo $progressPercent; ?>%"></div>
                                </div>
                                <small class="text-muted"><?php echo $trialDays; ?> days left in trial</small>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="container py-5">
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

    <!-- Comparison Table -->
    <div class="row mb-5">
        <div class="col-12">
            <div class="text-center mb-5">
                <h2 class="display-6 fw-bold">Choose Your Perfect Plan</h2>
                <p class="lead text-muted">
                    Compare features and pick the plan that fits your LinkedIn growth goals
                </p>
            </div>
            
            <div class="table-responsive">
                <table class="table table-borderless comparison-table">
                    <thead>
                        <tr>
                            <th class="border-0" style="width: 25%;"></th>
                            <th class="text-center border-0" style="width: 25%;">
                                <div class="plan-header trial">
                                    <div class="plan-badge">Current</div>
                                    <h4 class="fw-bold">Free Trial</h4>
                                    <div class="h5 text-muted">$0</div>
                                    <small>14 days only</small>
                                </div>
                            </th>
                            <?php if (!empty($pricingPlans)): ?>
                                <?php foreach (array_slice($pricingPlans, 0, 2) as $index => $plan): ?>
                                    <th class="text-center border-0" style="width: 25%;">
                                        <div class="plan-header <?php echo $index === 1 ? 'featured' : ''; ?>">
                                            <?php if ($index === 1): ?>
                                                <div class="plan-badge featured">Most Popular</div>
                                            <?php endif; ?>
                                            <h4 class="fw-bold"><?php echo htmlspecialchars($plan['plan_name']); ?></h4>
                                            <div class="h5 text-primary"><?php echo formatPrice($plan['plan_price'], $country); ?></div>
                                            <small>per month</small>
                                        </div>
                                    </th>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="fw-bold">Posts per month</td>
                            <td class="text-center">50</td>
                            <td class="text-center">200</td>
                            <td class="text-center">Unlimited</td>
                        </tr>
                        <tr>
                            <td class="fw-bold">Automations</td>
                            <td class="text-center">2</td>
                            <td class="text-center">5</td>
                            <td class="text-center">Unlimited</td>
                        </tr>
                        <tr>
                            <td class="fw-bold">AI Providers</td>
                            <td class="text-center">
                                <i class="fas fa-check text-success"></i>
                                <small class="d-block text-muted">Gemini</small>
                            </td>
                            <td class="text-center">
                                <i class="fas fa-check text-success"></i>
                                <small class="d-block text-muted">Gemini + ChatGPT</small>
                            </td>
                            <td class="text-center">
                                <i class="fas fa-check text-success"></i>
                                <small class="d-block text-muted">All AI Models</small>
                            </td>
                        </tr>
                        <tr>
                            <td class="fw-bold">Analytics</td>
                            <td class="text-center">
                                <i class="fas fa-check text-success"></i>
                                <small class="d-block text-muted">Basic</small>
                            </td>
                            <td class="text-center">
                                <i class="fas fa-check text-success"></i>
                                <small class="d-block text-muted">Advanced</small>
                            </td>
                            <td class="text-center">
                                <i class="fas fa-check text-success"></i>
                                <small class="d-block text-muted">Premium</small>
                            </td>
                        </tr>
                        <tr>
                            <td class="fw-bold">Support</td>
                            <td class="text-center">Email</td>
                            <td class="text-center">Priority Email</td>
                            <td class="text-center">24/7 Phone & Email</td>
                        </tr>
                        <tr>
                            <td class="fw-bold">Content Templates</td>
                            <td class="text-center">
                                <i class="fas fa-check text-success"></i>
                                <small class="d-block text-muted">50 templates</small>
                            </td>
                            <td class="text-center">
                                <i class="fas fa-check text-success"></i>
                                <small class="d-block text-muted">200+ templates</small>
                            </td>
                            <td class="text-center">
                                <i class="fas fa-check text-success"></i>
                                <small class="d-block text-muted">500+ templates</small>
                            </td>
                        </tr>
                        <tr>
                            <td class="fw-bold">API Access</td>
                            <td class="text-center"><i class="fas fa-times text-danger"></i></td>
                            <td class="text-center"><i class="fas fa-times text-danger"></i></td>
                            <td class="text-center"><i class="fas fa-check text-success"></i></td>
                        </tr>
                        <tr>
                            <td class="fw-bold">White-label</td>
                            <td class="text-center"><i class="fas fa-times text-danger"></i></td>
                            <td class="text-center"><i class="fas fa-times text-danger"></i></td>
                            <td class="text-center"><i class="fas fa-check text-success"></i></td>
                        </tr>
                        <tr>
                            <td></td>
                            <td class="text-center">
                                <div class="current-plan">
                                    <span class="badge bg-secondary">Current Plan</span>
                                </div>
                            </td>
                            <?php if (!empty($pricingPlans)): ?>
                                <?php foreach (array_slice($pricingPlans, 0, 2) as $index => $plan): ?>
                                    <td class="text-center">
                                        <form method="POST" action="payment.php">
                                            <input type="hidden" name="plan_id" value="<?php echo $plan['id']; ?>">
                                            <input type="hidden" name="plan_name" value="<?php echo htmlspecialchars($plan['plan_name']); ?>">
                                            <input type="hidden" name="amount" value="<?php echo $plan['plan_price']; ?>">
                                            <input type="hidden" name="currency" value="<?php echo $plan['currency']; ?>">
                                            
                                            <button type="submit" class="btn <?php echo $index === 1 ? 'btn-primary' : 'btn-outline-primary'; ?> btn-lg w-100">
                                                <i class="fas fa-crown me-2"></i>
                                                Upgrade Now
                                            </button>
                                        </form>
                                    </td>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Feature Highlights -->
    <div class="row mb-5">
        <div class="col-12">
            <div class="text-center mb-5">
                <h2 class="display-6 fw-bold">Why Upgrade?</h2>
                <p class="lead text-muted">Unlock powerful features to accelerate your LinkedIn growth</p>
            </div>
            
            <div class="row g-4">
                <div class="col-lg-4 col-md-6">
                    <div class="feature-highlight text-center p-4">
                        <div class="feature-icon bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3">
                            <i class="fas fa-infinity fa-2x"></i>
                        </div>
                        <h5 class="fw-bold">Unlimited Posts</h5>
                        <p class="text-muted">Generate and schedule as many posts as you need without any monthly limits.</p>
                    </div>
                </div>
                
                <div class="col-lg-4 col-md-6">
                    <div class="feature-highlight text-center p-4">
                        <div class="feature-icon bg-success text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3">
                            <i class="fas fa-robot fa-2x"></i>
                        </div>
                        <h5 class="fw-bold">Advanced AI</h5>
                        <p class="text-muted">Access both ChatGPT and Gemini AI for more diverse and engaging content creation.</p>
                    </div>
                </div>
                
                <div class="col-lg-4 col-md-6">
                    <div class="feature-highlight text-center p-4">
                        <div class="feature-icon bg-info text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3">
                            <i class="fas fa-chart-line fa-2x"></i>
                        </div>
                        <h5 class="fw-bold">Premium Analytics</h5>
                        <p class="text-muted">Deep insights into your post performance, engagement rates, and growth metrics.</p>
                    </div>
                </div>
                
                <div class="col-lg-4 col-md-6">
                    <div class="feature-highlight text-center p-4">
                        <div class="feature-icon bg-warning text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3">
                            <i class="fas fa-headset fa-2x"></i>
                        </div>
                        <h5 class="fw-bold">Priority Support</h5>
                        <p class="text-muted">Get faster response times and dedicated support for your LinkedIn automation needs.</p>
                    </div>
                </div>
                
                <div class="col-lg-4 col-md-6">
                    <div class="feature-highlight text-center p-4">
                        <div class="feature-icon bg-danger text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3">
                            <i class="fas fa-templates fa-2x"></i>
                        </div>
                        <h5 class="fw-bold">500+ Templates</h5>
                        <p class="text-muted">Access hundreds of proven LinkedIn post templates for every industry and occasion.</p>
                    </div>
                </div>
                
                <div class="col-lg-4 col-md-6">
                    <div class="feature-highlight text-center p-4">
                        <div class="feature-icon bg-secondary text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3">
                            <i class="fas fa-code fa-2x"></i>
                        </div>
                        <h5 class="fw-bold">API Access</h5>
                        <p class="text-muted">Integrate with your existing tools and workflows using our powerful API.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Testimonials -->
    <div class="row mb-5">
        <div class="col-12">
            <div class="text-center mb-5">
                <h2 class="display-6 fw-bold">What Our Pro Users Say</h2>
            </div>
            
            <div class="row g-4">
                <div class="col-lg-4">
                    <div class="testimonial-card bg-white rounded-4 p-4 h-100 shadow-sm">
                        <div class="d-flex align-items-center mb-3">
                            <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 50px; height: 50px;">
                                <span class="text-white fw-bold">SM</span>
                            </div>
                            <div>
                                <h6 class="mb-0">Sarah Mitchell</h6>
                                <small class="text-muted">Marketing Director</small>
                            </div>
                        </div>
                        <p class="text-muted mb-3">"The unlimited posts feature changed everything for me. I went from 50 posts to 300+ posts per month and my LinkedIn engagement increased by 500%!"</p>
                        <div class="text-warning">
                            ★★★★★
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4">
                    <div class="testimonial-card bg-white rounded-4 p-4 h-100 shadow-sm">
                        <div class="d-flex align-items-center mb-3">
                            <div class="bg-success rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 50px; height: 50px;">
                                <span class="text-white fw-bold">RK</span>
                            </div>
                            <div>
                                <h6 class="mb-0">Raj Kumar</h6>
                                <small class="text-muted">Business Coach</small>
                            </div>
                        </div>
                        <p class="text-muted mb-3">"The advanced AI features help me create content that really resonates with my Indian audience. The ROI has been incredible!"</p>
                        <div class="text-warning">
                            ★★★★★
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4">
                    <div class="testimonial-card bg-white rounded-4 p-4 h-100 shadow-sm">
                        <div class="d-flex align-items-center mb-3">
                            <div class="bg-info rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 50px; height: 50px;">
                                <span class="text-white fw-bold">JD</span>
                            </div>
                            <div>
                                <h6 class="mb-0">James Davidson</h6>
                                <small class="text-muted">Tech Entrepreneur</small>
                            </div>
                        </div>
                        <p class="text-muted mb-3">"The premium analytics helped me understand what content works best. My connection requests increased by 200% in just 2 months."</p>
                        <div class="text-warning">
                            ★★★★★
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- FAQ -->
    <div class="row">
        <div class="col-lg-8 mx-auto">
            <div class="text-center mb-5">
                <h2 class="display-6 fw-bold">Frequently Asked Questions</h2>
            </div>
            
            <div class="accordion" id="upgradeAccordion">
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#faq1">
                            Can I change my plan later?
                        </button>
                    </h2>
                    <div id="faq1" class="accordion-collapse collapse show" data-bs-parent="#upgradeAccordion">
                        <div class="accordion-body">
                            Yes! You can upgrade or downgrade your plan at any time. Changes will be prorated and reflected in your next billing cycle.
                        </div>
                    </div>
                </div>
                
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq2">
                            What happens to my existing automations?
                        </button>
                    </h2>
                    <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#upgradeAccordion">
                        <div class="accordion-body">
                            All your existing automations will continue to work seamlessly. When you upgrade, you'll get access to more automation slots and advanced features.
                        </div>
                    </div>
                </div>
                
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq3">
                            Is there a long-term commitment?
                        </button>
                    </h2>
                    <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#upgradeAccordion">
                        <div class="accordion-body">
                            No, all our plans are month-to-month. You can cancel anytime and you'll have access to your paid features until your current billing period ends.
                        </div>
                    </div>
                </div>
                
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq4">
                            Do you offer refunds?
                        </button>
                    </h2>
                    <div id="faq4" class="accordion-collapse collapse" data-bs-parent="#upgradeAccordion">
                        <div class="accordion-body">
                            We offer a 30-day money-back guarantee. If you're not satisfied with your upgrade, contact our support team for a full refund.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- CTA Section -->
<div class="bg-primary text-white py-5">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-8">
                <h2 class="display-6 fw-bold mb-3">Ready to Unlock Your LinkedIn Potential?</h2>
                <p class="lead mb-0">Join thousands of professionals who have transformed their LinkedIn presence with our premium features.</p>
            </div>
            <div class="col-lg-4 text-lg-end">
                <a href="#" onclick="scrollToPricing()" class="btn btn-light btn-lg px-4 py-3">
                    <i class="fas fa-crown me-2"></i>Choose Your Plan
                </a>
            </div>
        </div>
    </div>
</div>

<style>
.hero-gradient {
    background: linear-gradient(135deg, #0077b5 0%, #00a0dc 100%);
    position: relative;
}

.hero-gradient::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="25" cy="25" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="75" cy="75" r="1" fill="rgba(255,255,255,0.1)"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
}

.upgrade-stats {
    position: relative;
    z-index: 2;
}

.comparison-table {
    background: white;
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
}

.plan-header {
    padding: 20px;
    border-radius: 15px;
    margin-bottom: 10px;
    position: relative;
}

.plan-header.trial {
    background: #f8f9fa;
    border: 2px solid #e9ecef;
}

.plan-header.featured {
    background: linear-gradient(135deg, #0077b5, #00a0dc);
    color: white;
    transform: scale(1.05);
}

.plan-badge {
    position: absolute;
    top: -10px;
    left: 50%;
    transform: translateX(-50%);
    background: #28a745;
    color: white;
    padding: 5px 15px;
    border-radius: 20px;
    font-size: 0.8em;
    font-weight: bold;
}

.plan-badge.featured {
    background: #ffc107;
    color: #000;
}

.comparison-table tbody tr {
    border-bottom: 1px solid #f0f0f0;
}

.comparison-table tbody tr:hover {
    background: #f8f9fa;
}

.feature-highlight {
    background: white;
    border-radius: 15px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.08);
    transition: all 0.3s ease;
    border: 1px solid #f0f0f0;
}

.feature-highlight:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 35px rgba(0,0,0,0.15);
}

.feature-icon {
    width: 80px;
    height: 80px;
}

.testimonial-card {
    transition: all 0.3s ease;
    border: 1px solid #f0f0f0;
}

.testimonial-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 25px rgba(0,0,0,0.1) !important;
}

.current-plan {
    padding: 10px;
}

.accordion-item {
    border: 0;
    margin-bottom: 10px;
    border-radius: 10px !important;
    overflow: hidden;
}

.accordion-button {
    background: #f8f9fa;
    border: 0;
    font-weight: 500;
}

.accordion-button:not(.collapsed) {
    background: #0077b5;
    color: white;
}

@media (max-width: 768px) {
    .plan-header.featured {
        transform: none;
        margin-top: 20px;
    }
    
    .comparison-table {
        font-size: 0.9em;
    }
}
</style>

<script>
function scrollToPricing() {
    document.querySelector('.comparison-table').scrollIntoView({
        behavior: 'smooth'
    });
}

// Add some interaction effects
document.addEventListener('DOMContentLoaded', function() {
    // Animate counters on scroll
    const observerOptions = {
        threshold: 0.5,
        rootMargin: '0px 0px -100px 0px'
    };
    
    const observer = new IntersectionObserver(function(entries) {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('animate-in');
            }
        });
    }, observerOptions);
    
    document.querySelectorAll('.feature-highlight').forEach(el => {
        observer.observe(el);
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>