<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);



// index.php
// Add path debugging for development
if (!file_exists(__DIR__ . '/config/database-config.php')) {
    die('Database config file not found at: ' . __DIR__ . '/config/database-config.php');
}

require_once __DIR__ . '/config/database-config.php';

$pageTitle = 'LinkedIn Automation Tool - Automate Your LinkedIn Presence with AI';
$pageDescription = 'Generate and schedule LinkedIn posts automatically using ChatGPT and Gemini AI. Available in USA and India with localized pricing.';

// Get current country for pricing
$currentCountry = getCustomerCountry();
$currencySettings = getCurrencySettings($currentCountry);

// Get pricing plans for current country
try {
    $stmt = $db->prepare("
        SELECT plan_name, plan_price, currency, features, max_posts_per_month, max_automations 
        FROM pricing_plans 
        WHERE country = ? AND is_active = 1 
        ORDER BY plan_price ASC
    ");
    $stmt->execute([$currentCountry]);
    $pricingPlans = $stmt->fetchAll();
} catch (Exception $e) {
    logError("Error fetching pricing plans: " . $e->getMessage());
    $pricingPlans = [];
}

require_once 'includes/header.php';
?>

<!-- Hero Section -->
<section class="hero-section">
    <div class="container py-5">
        <div class="row align-items-center min-vh-100">
            <div class="col-lg-6">
                <div class="hero-content">
                    <h1 class="display-4 fw-bold text-white mb-4">
                        Automate Your LinkedIn Success with AI
                    </h1>
                    <p class="lead text-white-75 mb-4">
                        Generate engaging LinkedIn posts automatically using ChatGPT and Google Gemini. 
                        Schedule content, grow your network, and boost your professional presence effortlessly.
                    </p>
                    
                    <div class="d-flex flex-wrap gap-3 mb-4">
                        <a href="customer/signup.php" class="btn btn-light btn-lg px-4 py-3">
                            <i class="fas fa-rocket me-2"></i>Start Free Trial
                        </a>
                        <a href="#features" class="btn btn-outline-light btn-lg px-4 py-3">
                            <i class="fas fa-play me-2"></i>Learn More
                        </a>
                    </div>
                    
                    <div class="d-flex align-items-center text-white-75">
                        <i class="fas fa-check-circle me-2 text-success"></i>
                        <span>14-day free trial • No credit card required • Cancel anytime</span>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="text-center">
                    <div class="hero-image">
                        <div class="dashboard-preview bg-white rounded-3 shadow-lg p-4">
                            <div class="d-flex align-items-center mb-3">
                                <div class="bg-primary rounded-circle p-2 me-3">
                                    <i class="fab fa-linkedin text-white"></i>
                                </div>
                                <div>
                                    <h6 class="mb-0">LinkedIn Automation Dashboard</h6>
                                    <small class="text-muted">AI-Powered Content Generation</small>
                                </div>
                            </div>
                            <div class="row g-3 mb-3">
                                <div class="col-4">
                                    <div class="text-center p-2 bg-light rounded">
                                        <div class="h5 text-primary mb-0">150</div>
                                        <small class="text-muted">Posts Generated</small>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="text-center p-2 bg-light rounded">
                                        <div class="h5 text-success mb-0">95%</div>
                                        <small class="text-muted">Engagement Rate</small>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="text-center p-2 bg-light rounded">
                                        <div class="h5 text-warning mb-0">5</div>
                                        <small class="text-muted">Active Automations</small>
                                    </div>
                                </div>
                            </div>
                            <div class="bg-light rounded p-3">
                                <div class="d-flex align-items-center mb-2">
                                    <i class="fas fa-robot text-primary me-2"></i>
                                    <small class="text-muted">Next AI-generated post:</small>
                                </div>
                                <div class="small">"🚀 Exciting developments in AI technology are reshaping how we work..."</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Stats Section -->
<section class="stats-section py-5 bg-light">
    <div class="container">
        <div class="row text-center">
            <div class="col-md-3 col-sm-6 mb-4">
                <div class="stat-item">
                    <div class="h2 text-primary fw-bold mb-0">10,000+</div>
                    <div class="text-muted">Active Users</div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6 mb-4">
                <div class="stat-item">
                    <div class="h2 text-primary fw-bold mb-0">500K+</div>
                    <div class="text-muted">Posts Generated</div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6 mb-4">
                <div class="stat-item">
                    <div class="h2 text-primary fw-bold mb-0">25</div>
                    <div class="text-muted">Countries</div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6 mb-4">
                <div class="stat-item">
                    <div class="h2 text-primary fw-bold mb-0">98%</div>
                    <div class="text-muted">Satisfaction</div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Features Section -->
<section id="features" class="py-5">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="display-5 fw-bold mb-4">Powerful Features for LinkedIn Growth</h2>
            <p class="lead text-muted">Everything you need to automate and optimize your LinkedIn presence</p>
        </div>
        
        <div class="row g-4">
            <div class="col-lg-4 col-md-6">
                <div class="feature-card text-center h-100">
                    <div class="feature-icon bg-primary text-white rounded-3 d-inline-flex align-items-center justify-content-center mb-3">
                        <i class="fas fa-robot fa-2x"></i>
                    </div>
                    <h4 class="fw-bold mb-3">AI Content Generation</h4>
                    <p class="text-muted">Generate engaging, professional LinkedIn posts using ChatGPT and Google Gemini. Create content that resonates with your audience.</p>
                </div>
            </div>
            
            <div class="col-lg-4 col-md-6">
                <div class="feature-card text-center h-100">
                    <div class="feature-icon bg-success text-white rounded-3 d-inline-flex align-items-center justify-content-center mb-3">
                        <i class="fas fa-calendar-alt fa-2x"></i>
                    </div>
                    <h4 class="fw-bold mb-3">Smart Scheduling</h4>
                    <p class="text-muted">Schedule posts at optimal times for maximum engagement. Set up automated posting for specific days, times, and frequencies.</p>
                </div>
            </div>
            
            <div class="col-lg-4 col-md-6">
                <div class="feature-card text-center h-100">
                    <div class="feature-icon bg-warning text-white rounded-3 d-inline-flex align-items-center justify-content-center mb-3">
                        <i class="fas fa-chart-line fa-2x"></i>
                    </div>
                    <h4 class="fw-bold mb-3">Analytics & Insights</h4>
                    <p class="text-muted">Track engagement, analyze performance, and optimize your content strategy with detailed analytics and insights.</p>
                </div>
            </div>
            
            <div class="col-lg-4 col-md-6">
                <div class="feature-card text-center h-100">
                    <div class="feature-icon bg-info text-white rounded-3 d-inline-flex align-items-center justify-content-center mb-3">
                        <i class="fas fa-globe fa-2x"></i>
                    </div>
                    <h4 class="fw-bold mb-3">Multi-Country Support</h4>
                    <p class="text-muted">Localized pricing and support for multiple countries. Currently available in USA and India with more coming soon.</p>
                </div>
            </div>
            
            <div class="col-lg-4 col-md-6">
                <div class="feature-card text-center h-100">
                    <div class="feature-icon bg-danger text-white rounded-3 d-inline-flex align-items-center justify-content-center mb-3">
                        <i class="fas fa-shield-alt fa-2x"></i>
                    </div>
                    <h4 class="fw-bold mb-3">Secure & Reliable</h4>
                    <p class="text-muted">Enterprise-grade security with 99.9% uptime. Your LinkedIn account and data are always safe and protected.</p>
                </div>
            </div>
            
            <div class="col-lg-4 col-md-6">
                <div class="feature-card text-center h-100">
                    <div class="feature-icon bg-secondary text-white rounded-3 d-inline-flex align-items-center justify-content-center mb-3">
                        <i class="fas fa-magic fa-2x"></i>
                    </div>
                    <h4 class="fw-bold mb-3">Content Templates</h4>
                    <p class="text-muted">Access hundreds of proven LinkedIn post templates for different industries and content types.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Pricing Section -->
<section id="pricing" class="py-5 bg-light">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="display-5 fw-bold mb-4">Simple, Transparent Pricing</h2>
            <p class="lead text-muted">
                Choose the perfect plan for your LinkedIn automation needs in 
                <?php echo $currencySettings['name']; ?>
            </p>
        </div>
        
        <div class="row g-4 justify-content-center">
            <?php if (!empty($pricingPlans)): ?>
                <?php foreach ($pricingPlans as $index => $plan): ?>
                    <div class="col-lg-4 col-md-6">
                        <div class="pricing-card h-100 <?php echo $index === 1 ? 'featured' : ''; ?>">
                            <?php if ($index === 1): ?>
                                <div class="badge bg-primary position-absolute top-0 start-50 translate-middle px-3 py-2">
                                    Most Popular
                                </div>
                            <?php endif; ?>
                            
                            <div class="text-center mb-4">
                                <h3 class="fw-bold"><?php echo htmlspecialchars($plan['plan_name']); ?></h3>
                                <div class="display-6 fw-bold text-primary mb-1">
                                    <?php echo formatPrice($plan['plan_price'], $currentCountry); ?>
                                </div>
                                <div class="text-muted">per month</div>
                            </div>
                            
                            <ul class="list-unstyled mb-4">
                                <?php 
                                $features = explode(',', $plan['features']);
                                foreach ($features as $feature): 
                                ?>
                                    <li class="mb-2">
                                        <i class="fas fa-check text-success me-2"></i>
                                        <?php echo htmlspecialchars(trim($feature)); ?>
                                    </li>
                                <?php endforeach; ?>
                                
                                <?php if ($plan['max_posts_per_month'] > 0): ?>
                                    <li class="mb-2">
                                        <i class="fas fa-check text-success me-2"></i>
                                        <?php echo number_format($plan['max_posts_per_month']); ?> posts per month
                                    </li>
                                <?php else: ?>
                                    <li class="mb-2">
                                        <i class="fas fa-check text-success me-2"></i>
                                        Unlimited posts
                                    </li>
                                <?php endif; ?>
                                
                                <?php if ($plan['max_automations'] > 0): ?>
                                    <li class="mb-2">
                                        <i class="fas fa-check text-success me-2"></i>
                                        <?php echo $plan['max_automations']; ?> automation<?php echo $plan['max_automations'] > 1 ? 's' : ''; ?>
                                    </li>
                                <?php else: ?>
                                    <li class="mb-2">
                                        <i class="fas fa-check text-success me-2"></i>
                                        Unlimited automations
                                    </li>
                                <?php endif; ?>
                            </ul>
                            
                            <div class="d-grid">
                                <a href="customer/signup.php" class="btn <?php echo $index === 1 ? 'btn-primary' : 'btn-outline-primary'; ?> btn-lg">
                                    Start Free Trial
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <!-- Fallback pricing if database is not set up -->
                <div class="col-lg-4 col-md-6">
                    <div class="pricing-card h-100">
                        <div class="text-center mb-4">
                            <h3 class="fw-bold">Basic</h3>
                            <div class="display-6 fw-bold text-primary mb-1">
                                <?php echo $currentCountry === 'in' ? '₹1,499' : '$19'; ?>
                            </div>
                            <div class="text-muted">per month</div>
                        </div>
                        <ul class="list-unstyled mb-4">
                            <li class="mb-2"><i class="fas fa-check text-success me-2"></i>50 posts per month</li>
                            <li class="mb-2"><i class="fas fa-check text-success me-2"></i>AI Generation</li>
                            <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Email Support</li>
                        </ul>
                        <div class="d-grid">
                            <a href="customer/signup.php" class="btn btn-outline-primary btn-lg">Start Free Trial</a>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4 col-md-6">
                    <div class="pricing-card h-100 featured">
                        <div class="badge bg-primary position-absolute top-0 start-50 translate-middle px-3 py-2">
                            Most Popular
                        </div>
                        <div class="text-center mb-4">
                            <h3 class="fw-bold">Pro</h3>
                            <div class="display-6 fw-bold text-primary mb-1">
                                <?php echo $currentCountry === 'in' ? '₹3,999' : '$49'; ?>
                            </div>
                            <div class="text-muted">per month</div>
                        </div>
                        <ul class="list-unstyled mb-4">
                            <li class="mb-2"><i class="fas fa-check text-success me-2"></i>200 posts per month</li>
                            <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Advanced AI</li>
                            <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Priority Support</li>
                        </ul>
                        <div class="d-grid">
                            <a href="customer/signup.php" class="btn btn-primary btn-lg">Start Free Trial</a>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4 col-md-6">
                    <div class="pricing-card h-100">
                        <div class="text-center mb-4">
                            <h3 class="fw-bold">Enterprise</h3>
                            <div class="display-6 fw-bold text-primary mb-1">
                                <?php echo $currentCountry === 'in' ? '₹7,999' : '$99'; ?>
                            </div>
                            <div class="text-muted">per month</div>
                        </div>
                        <ul class="list-unstyled mb-4">
                            <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Unlimited posts</li>
                            <li class="mb-2"><i class="fas fa-check text-success me-2"></i>All Features</li>
                            <li class="mb-2"><i class="fas fa-check text-success me-2"></i>24/7 Support</li>
                        </ul>
                        <div class="d-grid">
                            <a href="customer/signup.php" class="btn btn-outline-primary btn-lg">Start Free Trial</a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- Testimonials Section -->
<section id="testimonials" class="py-5">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="display-5 fw-bold mb-4">What Our Users Say</h2>
            <p class="lead text-muted">Join thousands of professionals who are growing their LinkedIn presence</p>
        </div>
        
        <div class="row g-4">
            <div class="col-lg-4">
                <div class="testimonial-card bg-white rounded-3 p-4 h-100 shadow-sm">
                    <div class="d-flex align-items-center mb-3">
                        <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 50px; height: 50px;">
                            <span class="text-white fw-bold">JS</span>
                        </div>
                        <div>
                            <h6 class="mb-0">John Smith</h6>
                            <small class="text-muted">Marketing Director, USA</small>
                        </div>
                    </div>
                    <p class="text-muted mb-3">"This tool has completely transformed my LinkedIn presence. The AI-generated content is so relevant and engaging. I've seen a 300% increase in engagement!"</p>
                    <div class="text-warning">
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4">
                <div class="testimonial-card bg-white rounded-3 p-4 h-100 shadow-sm">
                    <div class="d-flex align-items-center mb-3">
                        <div class="bg-success rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 50px; height: 50px;">
                            <span class="text-white fw-bold">SK</span>
                        </div>
                        <div>
                            <h6 class="mb-0">Sneha Kumar</h6>
                            <small class="text-muted">Entrepreneur, India</small>
                        </div>
                    </div>
                    <p class="text-muted mb-3">"The scheduling feature is amazing! I can plan my entire month's content in advance. The pricing for India is very reasonable and the support is excellent."</p>
                    <div class="text-warning">
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4">
                <div class="testimonial-card bg-white rounded-3 p-4 h-100 shadow-sm">
                    <div class="d-flex align-items-center mb-3">
                        <div class="bg-info rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 50px; height: 50px;">
                            <span class="text-white fw-bold">MJ</span>
                        </div>
                        <div>
                            <h6 class="mb-0">Michael Johnson</h6>
                            <small class="text-muted">Sales Manager, USA</small>
                        </div>
                    </div>
                    <p class="text-muted mb-3">"Best investment for my personal brand. The automation saves me hours every week while maintaining high-quality, professional content."</p>
                    <div class="text-warning">
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- CTA Section -->
<section class="py-5 bg-primary text-white">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-8">
                <h2 class="display-6 fw-bold mb-3">Ready to Transform Your LinkedIn Presence?</h2>
                <p class="lead mb-0">Join thousands of professionals who are already growing their networks with AI-powered automation.</p>
            </div>
            <div class="col-lg-4 text-lg-end">
                <a href="customer/signup.php" class="btn btn-light btn-lg px-4 py-3">
                    <i class="fas fa-rocket me-2"></i>Start Free Trial
                </a>
            </div>
        </div>
    </div>
</section>

<style>
.hero-section {
    background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
    position: relative;
}

.hero-section::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="25" cy="25" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="75" cy="75" r="1" fill="rgba(255,255,255,0.1)"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
}

.text-white-75 {
    color: rgba(255, 255, 255, 0.75) !important;
}

.feature-card {
    background: white;
    border-radius: 15px;
    padding: 2rem;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
    transition: all 0.3s ease;
    border: 1px solid #f0f0f0;
}

.feature-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.15);
}

.feature-icon {
    width: 80px;
    height: 80px;
}

.pricing-card {
    background: white;
    border-radius: 15px;
    padding: 2rem;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
    transition: all 0.3s ease;
    border: 1px solid #f0f0f0;
    position: relative;
}

.pricing-card.featured {
    border: 2px solid var(--primary-color);
    transform: scale(1.02);
}

.pricing-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.15);
}

.pricing-card.featured:hover {
    transform: scale(1.02) translateY(-5px);
}

.testimonial-card {
    transition: all 0.3s ease;
}

.testimonial-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1) !important;
}

.dashboard-preview {
    max-width: 400px;
    margin: 0 auto;
}

@media (max-width: 768px) {
    .hero-section {
        padding: 50px 0;
    }
    
    .pricing-card.featured {
        transform: none;
        margin-top: 1rem;
    }
    
    .display-4 {
        font-size: 2.5rem;
    }
}
</style>

<?php require_once 'includes/footer.php'; ?>