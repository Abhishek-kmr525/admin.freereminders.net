<?php
// customer/connect-linkedin.php
require_once '../config/database-config.php';
require_once '../config/oauth-config.php';

$pageTitle = 'Connect LinkedIn - ' . SITE_NAME;
$pageDescription = 'Connect your LinkedIn account to enable automated posting';

// Require customer login
requireCustomerLogin();

$error = '';
$success = '';

// Check if LinkedIn is already connected
$linkedinConnected = false;
try {
    $stmt = $db->prepare("SELECT id, linkedin_user_id FROM customer_linkedin_tokens WHERE customer_id = ?");
    $stmt->execute([$_SESSION['customer_id']]);
    $linkedinToken = $stmt->fetch();
    $linkedinConnected = $linkedinToken !== false;
} catch (Exception $e) {
    error_log("Check LinkedIn connection error: " . $e->getMessage());
}

// If already connected, redirect to settings
if ($linkedinConnected) {
    $_SESSION['success_message'] = 'LinkedIn account is already connected!';
    redirectTo('settings.php');
}

require_once '../includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="text-center mb-5">
                <i class="fab fa-linkedin fa-5x text-primary mb-4"></i>
                <h2 class="display-5 fw-bold">Connect Your LinkedIn Account</h2>
                <p class="lead text-muted">
                    Connect LinkedIn to enable automated posting and grow your professional network effortlessly.
                </p>
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

            <div class="card shadow-lg border-0">
                <div class="card-body p-5">
                    <!-- Benefits of Connecting LinkedIn -->
                    <div class="row mb-5">
                        <div class="col-md-6">
                            <div class="benefit-item mb-4">
                                <div class="d-flex align-items-start">
                                    <div class="benefit-icon bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-3">
                                        <i class="fas fa-rocket"></i>
                                    </div>
                                    <div>
                                        <h5 class="fw-bold mb-2">Automated Posting</h5>
                                        <p class="text-muted mb-0">Schedule and publish posts automatically based on your automation settings.</p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="benefit-item mb-4">
                                <div class="d-flex align-items-start">
                                    <div class="benefit-icon bg-success text-white rounded-circle d-flex align-items-center justify-content-center me-3">
                                        <i class="fas fa-chart-line"></i>
                                    </div>
                                    <div>
                                        <h5 class="fw-bold mb-2">Track Performance</h5>
                                        <p class="text-muted mb-0">Monitor engagement, likes, comments, and shares on your automated posts.</p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="benefit-item mb-4">
                                <div class="d-flex align-items-start">
                                    <div class="benefit-icon bg-info text-white rounded-circle d-flex align-items-center justify-content-center me-3">
                                        <i class="fas fa-clock"></i>
                                    </div>
                                    <div>
                                        <h5 class="fw-bold mb-2">Save Time</h5>
                                        <p class="text-muted mb-0">Focus on your business while AI creates and publishes content for you.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="benefit-item mb-4">
                                <div class="d-flex align-items-start">
                                    <div class="benefit-icon bg-warning text-white rounded-circle d-flex align-items-center justify-content-center me-3">
                                        <i class="fas fa-users"></i>
                                    </div>
                                    <div>
                                        <h5 class="fw-bold mb-2">Grow Your Network</h5>
                                        <p class="text-muted mb-0">Consistent posting helps increase your visibility and connection requests.</p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="benefit-item mb-4">
                                <div class="d-flex align-items-start">
                                    <div class="benefit-icon bg-danger text-white rounded-circle d-flex align-items-center justify-content-center me-3">
                                        <i class="fas fa-shield-alt"></i>
                                    </div>
                                    <div>
                                        <h5 class="fw-bold mb-2">Secure & Safe</h5>
                                        <p class="text-muted mb-0">Your LinkedIn credentials are encrypted and stored securely using OAuth 2.0.</p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="benefit-item mb-4">
                                <div class="d-flex align-items-start">
                                    <div class="benefit-icon bg-secondary text-white rounded-circle d-flex align-items-center justify-content-center me-3">
                                        <i class="fas fa-cog"></i>
                                    </div>
                                    <div>
                                        <h5 class="fw-bold mb-2">Full Control</h5>
                                        <p class="text-muted mb-0">Review, edit, or cancel any scheduled posts before they go live.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Connection Steps -->
                    <div class="row mb-5">
                        <div class="col-12">
                            <h4 class="fw-bold text-center mb-4">How It Works</h4>
                            <div class="row g-4">
                                <div class="col-md-4 text-center">
                                    <div class="step-circle bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3">
                                        <span class="fw-bold">1</span>
                                    </div>
                                    <h6 class="fw-bold">Click Connect</h6>
                                    <p class="text-muted small">Click the LinkedIn connect button below to start the secure authorization process.</p>
                                </div>
                                <div class="col-md-4 text-center">
                                    <div class="step-circle bg-success text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3">
                                        <span class="fw-bold">2</span>
                                    </div>
                                    <h6 class="fw-bold">Authorize Access</h6>
                                    <p class="text-muted small">LinkedIn will ask for permission to post on your behalf. This is completely secure.</p>
                                </div>
                                <div class="col-md-4 text-center">
                                    <div class="step-circle bg-info text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3">
                                        <span class="fw-bold">3</span>
                                    </div>
                                    <h6 class="fw-bold">Start Automating</h6>
                                    <p class="text-muted small">Return here and start creating your first LinkedIn automation!</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Connect Button -->
                    <div class="text-center">
                        <div class="mb-4">
                            <a href="<?php echo htmlspecialchars(getLinkedInLoginUrl()); ?>" class="btn btn-primary btn-lg px-5 py-3">
                                <i class="fab fa-linkedin me-3"></i>
                                Connect LinkedIn Account
                            </a>
                        </div>
                        
                        <p class="text-muted small mb-4">
                            <i class="fas fa-lock me-1"></i>
                            Secured by LinkedIn OAuth 2.0 • We never store your LinkedIn password
                        </p>
                        
                        <div class="d-flex justify-content-center gap-3">
                            <a href="settings.php" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left me-2"></i>Back to Settings
                            </a>
                            <a href="dashboard.php" class="btn btn-outline-primary">
                                <i class="fas fa-home me-2"></i>Go to Dashboard
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Privacy & Security Information -->
            <div class="row mt-5">
                <div class="col-12">
                    <div class="card bg-light">
                        <div class="card-body p-4">
                            <h5 class="fw-bold mb-3">
                                <i class="fas fa-shield-alt me-2 text-success"></i>
                                Privacy & Security
                            </h5>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <h6 class="fw-bold">What We Access:</h6>
                                    <ul class="list-unstyled small">
                                        <li><i class="fas fa-check text-success me-2"></i>Permission to post on your behalf</li>
                                        <li><i class="fas fa-check text-success me-2"></i>Basic profile information (name, profile picture)</li>
                                        <li><i class="fas fa-check text-success me-2"></i>Post engagement metrics for analytics</li>
                                    </ul>
                                </div>
                                
                                <div class="col-md-6">
                                    <h6 class="fw-bold">What We DON'T Access:</h6>
                                    <ul class="list-unstyled small">
                                        <li><i class="fas fa-times text-danger me-2"></i>Your LinkedIn password</li>
                                        <li><i class="fas fa-times text-danger me-2"></i>Private messages or connections</li>
                                        <li><i class="fas fa-times text-danger me-2"></i>Personal or sensitive data</li>
                                        <li><i class="fas fa-times text-danger me-2"></i>Ability to delete your content</li>
                                    </ul>
                                </div>
                            </div>
                            
                            <div class="alert alert-info mt-3">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>Note:</strong> You can revoke access anytime from your LinkedIn privacy settings or from our settings page.
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- FAQ -->
            <div class="row mt-5">
                <div class="col-12">
                    <h5 class="fw-bold mb-3">Frequently Asked Questions</h5>
                    
                    <div class="accordion" id="linkedinFAQ">
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#faq1">
                                    Is it safe to connect my LinkedIn account?
                                </button>
                            </h2>
                            <div id="faq1" class="accordion-collapse collapse show" data-bs-parent="#linkedinFAQ">
                                <div class="accordion-body">
                                    Yes, absolutely! We use LinkedIn's official OAuth 2.0 authentication, which means we never see or store your LinkedIn password. You can revoke access at any time from your LinkedIn settings.
                                </div>
                            </div>
                        </div>
                        
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq2">
                                    Can you post without my permission?
                                </button>
                            </h2>
                            <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#linkedinFAQ">
                                <div class="accordion-body">
                                    No, we only post content that you've explicitly scheduled through our automation system. You have full control over what gets posted and when. You can review and edit all scheduled posts before they go live.
                                </div>
                            </div>
                        </div>
                        
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq3">
                                    What if I want to disconnect later?
                                </button>
                            </h2>
                            <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#linkedinFAQ">
                                <div class="accordion-body">
                                    You can disconnect your LinkedIn account anytime from the Settings page. This will immediately stop all automated posting, but your existing posts will remain on LinkedIn.
                                </div>
                            </div>
                        </div>
                        
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq4">
                                    Will my LinkedIn connections know I'm using automation?
                                </button>
                            </h2>
                            <div id="faq4" class="accordion-collapse collapse" data-bs-parent="#linkedinFAQ">
                                <div class="accordion-body">
                                    No, posts created through our platform appear exactly like regular LinkedIn posts. There's no indication that they were created using automation tools.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.benefit-icon {
    width: 50px;
    height: 50px;
    flex-shrink: 0;
}

.step-circle {
    width: 60px;
    height: 60px;
    font-size: 1.5rem;
}

.card {
    border-radius: 15px;
    border: 0;
    box-shadow: 0 5px 15px rgba(0,0,0,0.08);
}

.btn-primary {
    background: linear-gradient(135deg, #0077b5 0%, #00a0dc 100%);
    border: none;
    border-radius: 10px;
    font-weight: 500;
}

.btn-primary:hover {
    background: linear-gradient(135deg, #005885 0%, #0077b5 100%);
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(0, 119, 181, 0.4);
}

.accordion-item {
    border: 0;
    margin-bottom: 10px;
    border-radius: 10px;
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

.alert {
    border-radius: 10px;
    border: 0;
}

@media (max-width: 768px) {
    .btn-lg {
        padding: 12px 24px;
        font-size: 1rem;
    }
    
    .step-circle {
        width: 50px;
        height: 50px;
        font-size: 1.2rem;
    }
}
</style>

<script>
// Add some interactive effects
document.addEventListener('DOMContentLoaded', function() {
    // Animate benefit items on scroll
    const observerOptions = {
        threshold: 0.3,
        rootMargin: '0px 0px -50px 0px'
    };
    
    const observer = new IntersectionObserver(function(entries) {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
            }
        });
    }, observerOptions);
    
    document.querySelectorAll('.benefit-item').forEach((el, index) => {
        el.style.opacity = '0';
        el.style.transform = 'translateY(20px)';
        el.style.transition = `all 0.6s ease ${index * 0.1}s`;
        observer.observe(el);
    });
    
    // Add hover effect to connect button
    const connectBtn = document.querySelector('.btn-primary');
    if (connectBtn) {
        connectBtn.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-3px) scale(1.02)';
        });
        
        connectBtn.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0) scale(1)';
        });
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>