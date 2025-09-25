<?php
// customer/payment.php
require_once '../config/database-config.php';
require_once '../config/payment-config.php';

$pageTitle = 'Payment - ' . SITE_NAME;
$pageDescription = 'Complete your payment to start your subscription';

// Require customer login
requireCustomerLogin();

$error = '';
$country = getCustomerCountry();
$currencySettings = getCurrencySettings($country);

// Check if customer already has active subscription
if (hasActiveSubscription($_SESSION['customer_id'])) {
    $_SESSION['success_message'] = 'You already have an active subscription!';
    redirectTo('dashboard.php');
}

// Get plan details from POST
$planName = sanitizeInput($_POST['plan_name'] ?? '');
$amount = (float)($_POST['amount'] ?? 0);
$currency = sanitizeInput($_POST['currency'] ?? $currencySettings['currency']);

if (empty($planName) || $amount <= 0) {
    $_SESSION['error_message'] = 'Invalid plan selection. Please try again.';
    redirectTo('choose-plan.php');
}

// Calculate pricing with tax
$pricing = calculateTotal($amount, $country);

// Create Razorpay order
$razorpayOrder = null;
try {
    $razorpayOrder = createRazorpayOrder(
        $amount, 
        $currency, 
        $_SESSION['customer_id'], 
        $planName, 
        $country
    );
} catch (Exception $e) {
    $error = 'Unable to create payment order. Please try again.';
    logError("Payment order creation failed: " . $e->getMessage());
}

require_once '../includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card shadow-lg border-0">
                <div class="card-header bg-primary text-white py-4">
                    <div class="text-center">
                        <h2 class="fw-bold mb-1">Complete Your Payment</h2>
                        <p class="mb-0 opacity-75">Secure payment powered by Razorpay</p>
                    </div>
                </div>
                
                <div class="card-body p-5">
                    <?php if ($error): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
                            <div class="mt-3">
                                <a href="choose-plan.php" class="btn btn-outline-primary">
                                    <i class="fas fa-arrow-left me-2"></i>Back to Plans
                                </a>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="row">
                            <!-- Order Summary -->
                            <div class="col-md-6">
                                <h4 class="fw-bold mb-4">Order Summary</h4>
                                
                                <div class="bg-light rounded-3 p-4 mb-4">
                                    <div class="d-flex justify-content-between mb-3">
                                        <span class="fw-bold"><?php echo htmlspecialchars($planName); ?> Plan</span>
                                        <span class="fw-bold"><?php echo formatPrice($pricing['subtotal'], $country); ?></span>
                                    </div>
                                    
                                    <?php if ($pricing['tax'] > 0): ?>
                                        <div class="d-flex justify-content-between mb-3 text-muted">
                                            <span>Tax (<?php echo ($pricing['tax_rate'] * 100); ?>%)</span>
                                            <span><?php echo formatPrice($pricing['tax'], $country); ?></span>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <hr>
                                    
                                    <div class="d-flex justify-content-between">
                                        <span class="fw-bold h5">Total</span>
                                        <span class="fw-bold h5 text-primary">
                                            <?php echo formatPrice($pricing['total'], $country); ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i>
                                    <strong>Billing Cycle:</strong> Monthly subscription<br>
                                    <strong>Next billing:</strong> <?php echo date('M j, Y', strtotime('+1 month')); ?>
                                </div>
                            </div>
                            
                            <!-- Payment Form -->
                            <div class="col-md-6">
                                <h4 class="fw-bold mb-4">Payment Details</h4>
                                
                                <div class="payment-container">
                                    <button id="payButton" class="btn btn-primary btn-lg w-100 mb-4">
                                        <i class="fas fa-credit-card me-2"></i>
                                        Pay <?php echo formatPrice($pricing['total'], $country); ?>
                                    </button>
                                    
                                    <div class="text-center mb-4">
                                        <img src="https://razorpay.com/assets/razorpay-glyph.svg" alt="Razorpay" height="24" class="me-2">
                                        <small class="text-muted">Secured by Razorpay</small>
                                    </div>
                                    
                                    <div class="payment-methods">
                                        <h6 class="fw-bold mb-3">Accepted Payment Methods:</h6>
                                        <div class="row g-2 text-center">
                                            <div class="col-3">
                                                <div class="border rounded p-2">
                                                    <i class="fab fa-cc-visa fa-2x text-primary"></i>
                                                    <div class="small">Visa</div>
                                                </div>
                                            </div>
                                            <div class="col-3">
                                                <div class="border rounded p-2">
                                                    <i class="fab fa-cc-mastercard fa-2x text-warning"></i>
                                                    <div class="small">Mastercard</div>
                                                </div>
                                            </div>
                                            <div class="col-3">
                                                <div class="border rounded p-2">
                                                    <i class="fab fa-cc-amex fa-2x text-info"></i>
                                                    <div class="small">Amex</div>
                                                </div>
                                            </div>
                                            <div class="col-3">
                                                <div class="border rounded p-2">
                                                    <i class="fas fa-university fa-2x text-success"></i>
                                                    <div class="small">NetBanking</div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <?php if ($country === 'in'): ?>
                                            <div class="row g-2 text-center mt-2">
                                                <div class="col-4">
                                                    <div class="border rounded p-2">
                                                        <i class="fas fa-qrcode fa-2x text-primary"></i>
                                                        <div class="small">UPI</div>
                                                    </div>
                                                </div>
                                                <div class="col-4">
                                                    <div class="border rounded p-2">
                                                        <i class="fas fa-wallet fa-2x text-warning"></i>
                                                        <div class="small">Wallets</div>
                                                    </div>
                                                </div>
                                                <div class="col-4">
                                                    <div class="border rounded p-2">
                                                        <i class="fas fa-mobile-alt fa-2x text-info"></i>
                                                        <div class="small">EMI</div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Security Info -->
                        <div class="row mt-4">
                            <div class="col-12">
                                <div class="text-center">
                                    <div class="d-inline-flex align-items-center text-muted small">
                                        <i class="fas fa-shield-alt me-2 text-success"></i>
                                        256-bit SSL encryption • PCI DSS compliant • Your data is secure
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Customer Support -->
                        <div class="row mt-4">
                            <div class="col-12">
                                <div class="card bg-light">
                                    <div class="card-body text-center py-3">
                                        <h6 class="fw-bold mb-2">Need Help?</h6>
                                        <p class="mb-2 small">Our support team is here to help you with any payment issues.</p>
                                        <a href="mailto:support@nexloadtrucking.com" class="btn btn-sm btn-outline-primary me-2">
                                            <i class="fas fa-envelope me-1"></i>Email Support
                                        </a>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="goBack()">
                                            <i class="fas fa-arrow-left me-1"></i>Back to Plans
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Razorpay Checkout Script -->
<script src="https://checkout.razorpay.com/v1/checkout.js"></script>

<script>
// Razorpay payment configuration
const paymentOptions = {
    "key": "<?php echo RAZORPAY_KEY_ID; ?>",
    <?php if ($razorpayOrder): ?>
    "amount": <?php echo $razorpayOrder['amount']; ?>,
    "currency": "<?php echo $razorpayOrder['currency']; ?>",
    "order_id": "<?php echo $razorpayOrder['order_id']; ?>",
    <?php endif; ?>
    "name": "<?php echo SITE_NAME; ?>",
    "description": "<?php echo htmlspecialchars($planName); ?> Plan Subscription",
    "image": "<?php echo SITE_URL; ?>/assets/logo.png",
    "prefill": {
        "name": "<?php echo htmlspecialchars($_SESSION['customer_name']); ?>",
        "email": "<?php echo htmlspecialchars($_SESSION['customer_email']); ?>",
        "contact": ""
    },
    "notes": {
        "customer_id": "<?php echo $_SESSION['customer_id']; ?>",
        "plan_name": "<?php echo htmlspecialchars($planName); ?>"
    },
    "theme": {
        "color": "#0077b5"
    },
    "modal": {
        "ondismiss": function() {
            console.log('Payment modal closed');
        }
    },
    "handler": function(response) {
        // Payment successful
        console.log('Payment successful:', response);
        
        // Show loading
        document.getElementById('payButton').innerHTML = 
            '<i class="fas fa-spinner fa-spin me-2"></i>Processing...';
        document.getElementById('payButton').disabled = true;
        
        // Send payment details to server for verification
        fetch('payment-success.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                razorpay_payment_id: response.razorpay_payment_id,
                razorpay_order_id: response.razorpay_order_id,
                razorpay_signature: response.razorpay_signature,
                plan_name: "<?php echo htmlspecialchars($planName); ?>"
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Redirect to success page
                window.location.href = 'payment-success.php?status=success';
            } else {
                alert('Payment verification failed. Please contact support.');
                location.reload();
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred. Please contact support.');
            location.reload();
        });
    }
};

// Initialize payment
document.getElementById('payButton').addEventListener('click', function(e) {
    e.preventDefault();
    
    <?php if ($razorpayOrder): ?>
        const rzp = new Razorpay(paymentOptions);
        
        rzp.on('payment.failed', function(response) {
            console.log('Payment failed:', response);
            alert('Payment failed: ' + response.error.description);
        });
        
        rzp.open();
    <?php else: ?>
        alert('Unable to process payment. Please try again.');
    <?php endif; ?>
});

function goBack() {
    window.location.href = 'choose-plan.php';
}

// Prevent back button during payment
window.addEventListener('beforeunload', function(e) {
    if (document.getElementById('payButton').disabled) {
        e.preventDefault();
        e.returnValue = '';
    }
});
</script>

<style>
.payment-container {
    background: #f8f9fa;
    border-radius: 10px;
    padding: 20px;
}

.payment-methods .border {
    transition: all 0.3s ease;
}

.payment-methods .border:hover {
    background: #f0f0f0;
    transform: translateY(-2px);
}

.card-header {
    background: linear-gradient(135deg, #0077b5, #00a0dc) !important;
}

#payButton:disabled {
    opacity: 0.7;
    cursor: not-allowed;
}

.alert-info {
    background-color: #e3f2fd;
    border-color: #90caf9;
    color: #1565c0;
}
</style>

<?php require_once '../includes/footer.php'; ?>