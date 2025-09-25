<?php
ob_start();
// Enable detailed error reporting at the very top
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Register a shutdown function to catch fatal errors
register_shutdown_function(function () {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR])) {
        // Clean any previous output
        if (ob_get_level() > 0) {
            ob_end_clean();
        }
        
        // Display a formatted error message
        echo "<pre style='background-color: #f8d7da; color: #721c24; padding: 15px; border: 1px solid #f5c6cb; border-radius: 5px;'>";
        echo "<strong>Fatal Error Occurred:</strong>\n\n";
        echo "<strong>Type:</strong> " . $error['type'] . "\n";
        echo "<strong>Message:</strong> " . htmlspecialchars($error['message']) . "\n";
        echo "<strong>File:</strong> " . htmlspecialchars($error['file']) . "\n";
        echo "<strong>Line:</strong> " . $error['line'] . "\n";
        echo "</pre>";
        exit;
    }
});

// Start output buffering to catch any errors
ob_start();

try {
    // Debug path information
    $basePath = dirname(__DIR__);
    $configPath = $basePath . '/config/database-config.php';
    $oauthPath = $basePath . '/config/oauth-config.php';

    // Check if config files exist and are readable
    if (!is_readable($configPath)) {
        throw new Exception('Database config file not readable or not found at: ' . $configPath);
    }
    if (!is_readable($oauthPath)) {
        throw new Exception('OAuth config file not readable or not found at: ' . $oauthPath);
    }

    // Include required files with absolute paths
    require_once $configPath;
    
    // Verify database connection
    if (!isset($db)) {
        throw new Exception('Database connection not initialized after including config');
    }
    
    // Test database connection
    try {
        $db->query('SELECT 1');
    } catch (PDOException $e) {
        throw new Exception('Database connection failed: ' . $e->getMessage());
    }
    
    require_once $oauthPath;

} catch (Throwable $e) {
    // Log the error
    error_log("Signup error: " . $e->getMessage());
    
    // In development, show the error
    die('<pre>Error: ' . htmlspecialchars($e->getMessage()) . "\n" . 
        'File: ' . htmlspecialchars($e->getFile()) . "\n" . 
        'Line: ' . $e->getLine() . "\n" . 
        'Trace: ' . htmlspecialchars($e->getTraceAsString()) . '</pre>');
}

$pageTitle = 'Sign Up - ' . SITE_NAME;
$pageDescription = 'Create your LinkedIn automation account';

// Redirect if already logged in
if (isCustomerLoggedIn()) {
    header('Location: dashboard.php');
    exit();
}

$error = '';
$success = '';
$formData = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData = [
        'name' => sanitizeInput($_POST['name'] ?? ''),
        'email' => sanitizeInput($_POST['email'] ?? ''),
        'password' => $_POST['password'] ?? '',
        'confirm_password' => $_POST['confirm_password'] ?? '',
        'country' => sanitizeInput($_POST['country'] ?? DEFAULT_COUNTRY),
        'phone' => sanitizeInput($_POST['phone'] ?? ''),
        'terms' => isset($_POST['terms'])
    ];
    
    // Enhanced validation
    if (empty($formData['name']) || empty($formData['email']) || empty($formData['password'])) {
        $error = 'Please fill in all required fields';
    } elseif (strlen($formData['name']) < 2) {
        $error = 'Please enter your full name (at least 2 characters)';
    } elseif (!filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address';
    } elseif (strlen($formData['password']) < 8) {
        $error = 'Password must be at least 8 characters long';
    } elseif ($formData['password'] !== $formData['confirm_password']) {
        $error = 'Passwords do not match';
    } elseif (!$formData['terms']) {
        $error = 'Please accept the Terms of Service';
    } else {
        try {
            // Check if email already exists
            $stmt = $db->prepare("SELECT id FROM customers WHERE email = ?");
            $stmt->execute([$formData['email']]);
            
            if ($stmt->fetch()) {
                $error = 'An account with this email already exists. <a href="login.php">Login here</a>';
            } else {
                // Create new customer account
                $hashedPassword = password_hash($formData['password'], PASSWORD_DEFAULT);
                $trialEndsAt = date('Y-m-d H:i:s', strtotime('+' . TRIAL_PERIOD_DAYS . ' days'));
                
                $stmt = $db->prepare("
                    INSERT INTO customers (
                        name, email, password, country, phone, 
                        subscription_status, trial_ends_at, status, created_at, updated_at
                    ) VALUES (?, ?, ?, ?, ?, 'trial', ?, 'active', NOW(), NOW())
                ");
                
                $result = $stmt->execute([
                    $formData['name'],
                    $formData['email'],
                    $hashedPassword,
                    $formData['country'],
                    $formData['phone'],
                    $trialEndsAt
                ]);
                
                if ($result) {
                    $customerId = $db->lastInsertId();
                    
                    // Log account creation activity
                    logCustomerActivity($customerId, 'account_created', 'New account created via signup form');
                    
                    // Auto-login the user
                    session_regenerate_id(true);
                    
                    $_SESSION['customer_id'] = $customerId;
                    $_SESSION['customer_name'] = $formData['name'];
                    $_SESSION['customer_email'] = $formData['email'];
                    $_SESSION['customer_country'] = $formData['country'];
                    $_SESSION['customer_status'] = 'active';
                    $_SESSION['subscription_status'] = 'trial';
                    $_SESSION['login_time'] = time();
                    
                    $_SESSION['success_message'] = 'Welcome! Your account has been created successfully. Your 14-day free trial has started.';
                    
                    // Redirect to dashboard
                    header('Location: dashboard.php');
                    exit();
                } else {
                    $error = 'Failed to create account. Please try again.';
                }
            }
            
        } catch (Exception $e) {
            error_log("Signup error: " . $e->getMessage());
            $error = 'An error occurred while creating your account. Please try again.';
        }
    }
}

require_once '../includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="card shadow-lg border-0">
                <div class="card-body p-5">
                    <div class="text-center mb-4">
                        <i class="fab fa-linkedin fa-3x text-primary mb-3"></i>
                        <h2 class="fw-bold">Create Your Account</h2>
                        <p class="text-muted">Start your 14-day free trial today</p>
                    </div>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success); ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" id="signupForm">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="name" class="form-label">Full Name *</label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="fas fa-user"></i>
                                        </span>
                                        <input type="text" class="form-control" id="name" name="name" 
                                               value="<?php echo htmlspecialchars($formData['name'] ?? ''); ?>" 
                                               placeholder="Enter your full name" required>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email Address *</label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="fas fa-envelope"></i>
                                        </span>
                                        <input type="email" class="form-control" id="email" name="email" 
                                               value="<?php echo htmlspecialchars($formData['email'] ?? ''); ?>" 
                                               placeholder="Enter your email" required>
                                    </div>
                                    <div id="emailFeedback" class="form-text"></div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="password" class="form-label">Password *</label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="fas fa-lock"></i>
                                        </span>
                                        <input type="password" class="form-control" id="password" name="password" 
                                               placeholder="Minimum 8 characters" required>
                                        <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('password', 'toggleIcon1')">
                                            <i class="fas fa-eye" id="toggleIcon1"></i>
                                        </button>
                                    </div>
                                    <div class="form-text">
                                        <small id="passwordStrength" class="text-muted">Password strength: Enter a password</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="confirm_password" class="form-label">Confirm Password *</label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="fas fa-lock"></i>
                                        </span>
                                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                                               placeholder="Confirm your password" required>
                                        <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('confirm_password', 'toggleIcon2')">
                                            <i class="fas fa-eye" id="toggleIcon2"></i>
                                        </button>
                                    </div>
                                    <div id="passwordMatch" class="form-text"></div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="country" class="form-label">Country *</label>
                                    <select class="form-select" id="country" name="country" required>
                                        <option value="us" <?php echo (($formData['country'] ?? getCustomerCountry()) === 'us') ? 'selected' : ''; ?>>🇺🇸 United States</option>
                                        <option value="in" <?php echo (($formData['country'] ?? getCustomerCountry()) === 'in') ? 'selected' : ''; ?>>🇮🇳 India</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="phone" class="form-label">Phone Number</label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="fas fa-phone"></i>
                                        </span>
                                        <input type="tel" class="form-control" id="phone" name="phone" 
                                               value="<?php echo htmlspecialchars($formData['phone'] ?? ''); ?>" 
                                               placeholder="Optional">
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="terms" name="terms" required>
                            <label class="form-check-label" for="terms">
                                I agree to the <a href="#" target="_blank" class="text-primary">Terms of Service</a> and 
                                <a href="#" target="_blank" class="text-primary">Privacy Policy</a> *
                            </label>
                        </div>
                        
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="newsletter" name="newsletter" checked>
                            <label class="form-check-label" for="newsletter">
                                Subscribe to our newsletter for LinkedIn automation tips and updates
                            </label>
                        </div>
                        
                        <div class="d-grid mb-3">
                            <button type="submit" class="btn btn-primary btn-lg" id="signupBtn">
                                <i class="fas fa-rocket me-2"></i>Create Account & Start Free Trial
                            </button>
                        </div>
                        
                        <div class="text-center">
                            <small class="text-muted">
                                <i class="fas fa-shield-alt me-1"></i>
                                14-day free trial • No credit card required • Cancel anytime
                            </small>
                        </div>
                    </form>
                    
                    <hr class="my-4">
                    
                    <!-- OAuth Signup Options -->
                    <div class="text-center mb-3">
                        <p class="text-muted mb-3">Or sign up with:</p>
                        
                        <div class="d-grid gap-2">
                            <a href="<?php echo htmlspecialchars(getGoogleLoginUrl()); ?>" class="btn btn-outline-danger btn-lg">
                                <i class="fab fa-google me-2"></i>Sign up with Google
                            </a>
                            
                            <a href="<?php echo htmlspecialchars(getLinkedInLoginUrl()); ?>" class="btn btn-outline-primary btn-lg">
                                <i class="fab fa-linkedin me-2"></i>Sign up with LinkedIn
                            </a>
                        </div>
                    </div>
                    
                    <hr class="my-4">
                    
                    <div class="text-center">
                        <p class="mb-0">Already have an account?</p>
                        <a href="login.php" class="btn btn-outline-primary mt-2">
                            <i class="fas fa-sign-in-alt me-2"></i>Sign In
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function togglePassword(inputId, iconId) {
    const passwordInput = document.getElementById(inputId);
    const toggleIcon = document.getElementById(iconId);
    
    if (passwordInput && toggleIcon) {
        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            toggleIcon.className = 'fas fa-eye-slash';
        } else {
            passwordInput.type = 'password';
            toggleIcon.className = 'fas fa-eye';
        }
    }
}

// Enhanced form validation
document.addEventListener('DOMContentLoaded', function() {
    const signupForm = document.getElementById('signupForm');
    const signupBtn = document.getElementById('signupBtn');
    const passwordInput = document.getElementById('password');
    const confirmPasswordInput = document.getElementById('confirm_password');
    const emailInput = document.getElementById('email');
    const nameInput = document.getElementById('name');
    
    // Password strength indicator
    if (passwordInput) {
        passwordInput.addEventListener('input', function() {
            const password = this.value;
            const strengthIndicator = document.getElementById('passwordStrength');
            
            if (password.length === 0) {
                strengthIndicator.textContent = 'Password strength: Enter a password';
                strengthIndicator.className = 'text-muted';
                return;
            }
            
            let strength = 0;
            if (password.length >= 8) strength++;
            if (/[a-z]/.test(password)) strength++;
            if (/[A-Z]/.test(password)) strength++;
            if (/\d/.test(password)) strength++;
            if (/[^a-zA-Z\d]/.test(password)) strength++;
            
            let strengthText = 'Password strength: ';
            let strengthClass = '';
            
            if (strength <= 2) {
                strengthText += 'Weak';
                strengthClass = 'text-danger';
            } else if (strength <= 3) {
                strengthText += 'Medium';
                strengthClass = 'text-warning';
            } else {
                strengthText += 'Strong';
                strengthClass = 'text-success';
            }
            
            strengthIndicator.textContent = strengthText;
            strengthIndicator.className = strengthClass;
        });
    }
    
    // Password match indicator
    if (confirmPasswordInput) {
        confirmPasswordInput.addEventListener('input', function() {
            const password = passwordInput ? passwordInput.value : '';
            const confirmPassword = this.value;
            const matchIndicator = document.getElementById('passwordMatch');
            
            if (confirmPassword.length === 0) {
                matchIndicator.textContent = '';
                return;
            }
            
            if (password === confirmPassword) {
                matchIndicator.textContent = 'Passwords match ✓';
                matchIndicator.className = 'form-text text-success';
            } else {
                matchIndicator.textContent = 'Passwords do not match';
                matchIndicator.className = 'form-text text-danger';
            }
        });
    }
    
    // Form submission validation
    if (signupForm) {
        signupForm.addEventListener('submit', function(e) {
            const name = nameInput ? nameInput.value.trim() : '';
            const email = emailInput ? emailInput.value.trim() : '';
            const password = passwordInput ? passwordInput.value : '';
            const confirmPassword = confirmPasswordInput ? confirmPasswordInput.value : '';
            const terms = document.getElementById('terms');
            
            let isValid = true;
            let errorMessage = '';
            
            if (name.length < 2) {
                isValid = false;
                errorMessage = 'Please enter your full name (at least 2 characters)';
            } else if (!email.includes('@') || !email.includes('.')) {
                isValid = false;
                errorMessage = 'Please enter a valid email address';
            } else if (password.length < 8) {
                isValid = false;
                errorMessage = 'Password must be at least 8 characters long';
            } else if (password !== confirmPassword) {
                isValid = false;
                errorMessage = 'Passwords do not match';
            } else if (terms && !terms.checked) {
                isValid = false;
                errorMessage = 'Please accept the Terms of Service to continue';
            }
            
            if (!isValid) {
                e.preventDefault();
                alert(errorMessage);
                return false;
            }
            
            // Show loading state
            if (signupBtn) {
                signupBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Creating Account...';
                signupBtn.disabled = true;
            }
        });
    }
    
    // Auto-focus name field
    if (nameInput) {
        nameInput.focus();
    }
});
</script>

<style>
.card {
    border-radius: 15px;
    border: 0;
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
}

.form-control {
    border-radius: 8px;
    border: 2px solid #e9ecef;
    transition: all 0.3s ease;
}

.form-control:focus {
    border-color: #0077b5;
    box-shadow: 0 0 0 0.2rem rgba(0, 119, 181, 0.25);
}

.btn {
    border-radius: 8px;
    font-weight: 500;
    transition: all 0.3s ease;
}

.btn-primary {
    background: linear-gradient(135deg, #0077b5 0%, #00a0dc 100%);
    border: none;
}

.btn-primary:hover:not(:disabled) {
    background: linear-gradient(135deg, #005885 0%, #0077b5 100%);
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(0, 119, 181, 0.4);
}

.btn-primary:disabled {
    background: #6c757d;
    cursor: not-allowed;
}

.alert {
    border-radius: 12px;
    border: 0;
}

.form-check-input:checked {
    background-color: #0077b5;
    border-color: #0077b5;
}

.text-primary {
    color: #0077b5 !important;
}
</style>

<?php require_once '../includes/footer.php'; 
ob_end_flush();?>