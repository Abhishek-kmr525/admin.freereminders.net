<?php
// customer/payment-success.php
require_once '../config/database-config.php';
require_once '../config/payment-config.php';

// Require customer login
requireCustomerLogin();

$pageTitle = 'Payment Successful - ' . SITE_NAME;
$pageDescription = 'Your payment has been processed successfully';

// Handle payment verification (AJAX request)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['CONTENT_TYPE']) && 
    strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) {
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    try {
        $razorpayOrderId = $input['razorpay_order_id'] ?? '';
        $razorpayPaymentId = $input['razorpay_payment_id'] ?? '';
        $razorpaySignature = $input['razorpay_signature'] ?? '';
        $planName = $input['plan_name'] ?? '';
        
        if (empty($razorpayOrderId) || empty($razorpayPaymentId) || empty($razorpaySignature)) {
            throw new Exception('Missing payment verification data');
        }
        
        // Verify payment signature
        if (!verifyRazorpayPayment($razorpayOrderId, $razorpayPaymentId, $razorpaySignature)) {
            throw new Exception('Payment signature verification failed');
        }
        
        // Process successful payment
        $result = processSuccessfulPayment($razorpayOrderId, $razorpayPaymentId, $_SESSION['customer_id']);
        
        if ($result) {
            // Update session
            $_SESSION['subscription_status'] = 'active';
            $_SESSION['success_message'] = 'Payment successful! Welcome to ' . $planName . ' plan.';
            
            sendJsonResponse(array('success' => true));
        } else {
            throw new Exception('Failed to process payment');
        }
        
    } catch (Exception $e) {
        logError("Payment verification error: " . $e->getMessage());
        sendJsonResponse(array('success' => false, 'error' => $e->getMessage()), 400);
    }
    
    exit();
}

// Display success page
$status = $_GET['status'] ?? '';

if ($status === 'success') {
    require_once '../includes/header.php';
    ?>
    
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-6">
                <div class="card shadow-lg border-0">
                    <div class="card-body text-center p-5">
                        <!-- Success Icon -->
                        <div class="success-icon mb-4">
                            <i class="fas fa-check-circle fa-5x text-success"></i>
                        </div>
                        
                        <!-- Success Message -->
                        <h2 class="fw-bold text-success mb-3">Payment Successful!</h2>
                        <p class="lead text-muted mb-4">
                            Thank you for your subscription. Your account has been upgraded successfully.
                        </p>
                        
                        <!-- Plan Details -->
                        <div class="bg-light rounded-3 p-4 mb-4">
                            <h5 class="fw-bold mb-3">Subscription Details</h5>
                            <div class="row text-start">
                                <div class="col-6">
                                    <strong>Plan:</strong>
                                </div>
                                <div class="col-6">
                                    <?php echo htmlspecialchars($_SESSION['customer']['subscription_plan'] ?? 'Premium Plan'); ?>
                                </div>
                            </div>
                            <div class="row text-start">
                                <div class="col-6">
                                    <strong>Status:</strong>
                                </div>
                                <div class="col-6">
                                    <span class="badge bg-success">Active</span>
                                </div>
                            </div>
                            <div class="row text-start">
                                <div class="col-6">
                                    <strong>Next Billing:</strong>
                                </div>
                                <div class="col-6">
                                    <?php echo date('M j, Y', strtotime('+1 month')); ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- What's Next -->
                        <div class="alert alert-info text-start">
                            <h6 class="fw-bold mb-2">
                                <i class="fas fa-lightbulb me-2"></i>What's Next?
                            </h6>
                            <ul class="mb-0 small">
                                <li>Access your dashboard to create automations</li>
                                <li>Connect your LinkedIn account</li>
                                <li>Set up your first AI-powered post schedule</li>
                                <li>Track your engagement analytics</li>
                            </ul>
                        </div>
                        
                        <!-- Action Buttons -->
                        <div class="d-grid gap-2 d-md-flex justify-content-md-center">
                            <a href="dashboard.php" class="btn btn-primary btn-lg">
                                <i class="fas fa-tachometer-alt me-2"></i>Go to Dashboard
                            </a>
                            <a href="create-automation.php" class="btn btn-outline-primary btn-lg">
                                <i class="fas fa-plus me-2"></i>Create First Automation
                            </a>
                        </div>
                        
                        <!-- Support Info -->
                        <div class="mt-4 text-muted small">
                            <p class="mb-1">
                                <i class="fas fa-envelope me-1"></i>
                                Receipt sent to <?php echo htmlspecialchars($_SESSION['customer_email']); ?>
                            </p>
                            <p class="mb-0">
                                Need help? <a href="mailto:support@nexloadtrucking.com">Contact Support</a>
                            </p>
                        </div>
                    </div>
                </div>
                
                <!-- Onboarding Tips -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-rocket me-2"></i>Quick Start Guide
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-4">
                            <div class="col-md-4 text-center">
                                <div class="bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 60px; height: 60px;">
                                    <span class="fw-bold">1</span>
                                </div>
                                <h6 class="fw-bold">Connect LinkedIn</h6>
                                <p class="small text-muted">Link your LinkedIn account to start posting automatically</p>
                            </div>
                            <div class="col-md-4 text-center">
                                <div class="bg-success text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 60px; height: 60px;">
                                    <span class="fw-bold">2</span>
                                </div>
                                <h6 class="fw-bold">Create Automation</h6>
                                <p class="small text-muted">Set up your first AI-powered posting schedule</p>
                            </div>
                            <div class="col-md-4 text-center">
                                <div class="bg-info text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 60px; height: 60px;">
                                    <span class="fw-bold">3</span>
                                </div>
                                <h6 class="fw-bold">Track Results</h6>
                                <p class="small text-muted">Monitor your engagement and optimize your strategy</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <style>
    .success-icon {
        animation: successPulse 2s ease-in-out infinite;
    }
    
    @keyframes successPulse {
        0% { transform: scale(1); }
        50% { transform: scale(1.05); }
        100% { transform: scale(1); }
    }
    
    .card {
        animation: slideUp 0.6s ease-out;
    }
    
    @keyframes slideUp {
        from {
            opacity: 0;
            transform: translateY(30px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    </style>
    
    <!-- Confetti Animation (Optional) -->
    <script>
    // Simple confetti effect
    function createConfetti() {
        const colors = ['#0077b5', '#00a0dc', '#28a745', '#ffc107', '#dc3545'];
        const confettiCount = 50;
        
        for (let i = 0; i < confettiCount; i++) {
            const confetti = document.createElement('div');
            confetti.style.position = 'fixed';
            confetti.style.width = '10px';
            confetti.style.height = '10px';
            confetti.style.backgroundColor = colors[Math.floor(Math.random() * colors.length)];
            confetti.style.left = Math.random() * 100 + 'vw';
            confetti.style.top = '-10px';
            confetti.style.zIndex = '9999';
            confetti.style.pointerEvents = 'none';
            confetti.style.borderRadius = '50%';
            
            document.body.appendChild(confetti);
            
            // Animate
            confetti.animate([
                { transform: 'translateY(0) rotateZ(0deg)', opacity: 1 },
                { transform: 'translateY(100vh) rotateZ(360deg)', opacity: 0 }
            ], {
                duration: Math.random() * 2000 + 1000,
                easing: 'cubic-bezier(0.4, 0.0, 0.2, 1)'
            }).onfinish = () => confetti.remove();
        }
    }
    
    // Trigger confetti on page load
    setTimeout(createConfetti, 500);
    </script>
    
    <?php
    require_once '../includes/footer.php';
} else {
    // Redirect to dashboard if no success status
    redirectTo('dashboard.php');
}
?>