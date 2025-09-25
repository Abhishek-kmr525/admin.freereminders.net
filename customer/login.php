<?php
ob_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Debug path information
$basePath = dirname(__DIR__);
$configPath = $basePath . '/config/database-config.php';
$oauthPath = $basePath . '/config/oauth-config.php';

if (!file_exists($configPath)) {
    die('Database config file not found. Looking in: ' . $configPath);
}
if (!file_exists($oauthPath)) {
    die('OAuth config file not found. Looking in: ' . $oauthPath);
}

// customer/login.php - Fixed version
require_once $configPath;
require_once $oauthPath;

$pageTitle = 'Login - ' . SITE_NAME;
$pageDescription = 'Login to your LinkedIn automation account';

// Redirect if already logged in
if (isCustomerLoggedIn()) {
    header('Location: dashboard.php');
    exit();
}

$error = '';
$success = '';
$email = '';

// Check for success messages
if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 'password_reset':
            $success = 'Password reset successful! Please login with your new password.';
            break;
        case 'account_verified':
            $success = 'Account verified successfully! You can now login.';
            break;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitizeInput($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);
    
    // Validation
    if (empty($email) || empty($password)) {
        $error = 'Please fill in all fields';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address';
    } else {
        try {
            // Check user credentials
            $stmt = $db->prepare("
                SELECT id, name, email, password, country, status, subscription_status, trial_ends_at 
                FROM customers 
                WHERE email = ? AND status = 'active'
            ");
            $stmt->execute([$email]);
            $customer = $stmt->fetch();
            
            if ($customer && password_verify($password, $customer['password'])) {
                // Login successful - regenerate session ID for security
                session_regenerate_id(true);
                
                $_SESSION['customer_id'] = $customer['id'];
                $_SESSION['customer_name'] = $customer['name'];
                $_SESSION['customer_email'] = $customer['email'];
                $_SESSION['customer_country'] = $customer['country'];
                $_SESSION['customer_status'] = $customer['status'];
                $_SESSION['subscription_status'] = $customer['subscription_status'];
                $_SESSION['login_time'] = time();
                
                // Handle remember me functionality
                if ($remember) {
                    $token = generateToken(32);
                    $expires = date('Y-m-d H:i:s', strtotime('+30 days'));
                    
                    try {
                        $stmt = $db->prepare("
                            INSERT INTO customer_sessions (customer_id, session_token, expires_at) 
                            VALUES (?, ?, ?)
                            ON DUPLICATE KEY UPDATE 
                            session_token = VALUES(session_token),
                            expires_at = VALUES(expires_at)
                        ");
                        $stmt->execute([$customer['id'], $token, $expires]);
                        
                        // Set secure cookie
                        $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
                        setcookie('remember_token', $token, strtotime('+30 days'), '/', '', $secure, true);
                    } catch (Exception $e) {
                        error_log("Remember me token creation failed: " . $e->getMessage());
                    }
                }
                
                // Log successful login activity
                logCustomerActivity($customer['id'], 'login', 'User logged in successfully');
                
                // Check if trial has expired
                if ($customer['subscription_status'] === 'trial' && 
                    $customer['trial_ends_at'] && 
                    strtotime($customer['trial_ends_at']) < time()) {
                    
                    $stmt = $db->prepare("UPDATE customers SET subscription_status = 'expired' WHERE id = ?");
                    $stmt->execute([$customer['id']]);
                    $_SESSION['subscription_status'] = 'expired';
                }
                
                // Determine redirect URL
                $redirectUrl = $_GET['redirect'] ?? 'dashboard.php';
                
                // If subscription expired, redirect to plans
                if ($_SESSION['subscription_status'] === 'expired') {
                    $_SESSION['warning_message'] = 'Your free trial has expired. Please select a plan to continue.';
                    $redirectUrl = 'choose-plan.php';
                }
                
                // Redirect to appropriate page
                header("Location: $redirectUrl");
                exit();
                
            } else {
                $error = 'Invalid email or password';
                error_log("Failed login attempt for email: $email from IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
            }
            
        } catch (Exception $e) {
            error_log("Login error: " . $e->getMessage());
            $error = 'An error occurred. Please try again.';
        }
    }
}

require_once '../includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="card shadow-lg border-0">
                <div class="card-body p-5">
                    <div class="text-center mb-4">
                        <i class="fab fa-linkedin fa-3x text-primary mb-3"></i>
                        <h2 class="fw-bold">Welcome Back</h2>
                        <p class="text-muted">Sign in to your account</p>
                    </div>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success); ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" id="loginForm">
                        <div class="mb-3">
                            <label for="email" class="form-label">Email Address</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-envelope"></i>
                                </span>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?php echo htmlspecialchars($email); ?>" 
                                       placeholder="Enter your email" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-lock"></i>
                                </span>
                                <input type="password" class="form-control" id="password" name="password" 
                                       placeholder="Enter your password" required>
                                <button class="btn btn-outline-secondary" type="button" onclick="togglePassword()">
                                    <i class="fas fa-eye" id="toggleIcon"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-6">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="remember" name="remember">
                                    <label class="form-check-label" for="remember">
                                        Remember me
                                    </label>
                                </div>
                            </div>
                            <div class="col-6 text-end">
                                <a href="forgot-password.php" class="text-decoration-none small">
                                    Forgot password?
                                </a>
                            </div>
                        </div>
                        
                        <div class="d-grid mb-3">
                            <button type="submit" class="btn btn-primary btn-lg" id="loginBtn">
                                <i class="fas fa-sign-in-alt me-2"></i>Sign In
                            </button>
                        </div>
                    </form>
                    
                   
                    
                    <hr class="my-4">
                    
                    <!-- OAuth Login Options -->
                    <div class="text-center mb-3">
                        <p class="text-muted mb-3">Or continue with:</p>
                        
                        <div class="d-grid gap-2">
                            <a href="<?php echo htmlspecialchars(getGoogleLoginUrl()); ?>" class="btn btn-outline-danger btn-lg">
                                <i class="fab fa-google me-2"></i>Continue with Google
                            </a>
                            
                            <a href="<?php echo htmlspecialchars(getLinkedInLoginUrl()); ?>" class="btn btn-outline-primary btn-lg">
                                <i class="fab fa-linkedin me-2"></i>Continue with LinkedIn
                            </a>
                        </div>
                    </div>
                    
                    <hr class="my-4">
                    
                    <div class="text-center">
                        <p class="mb-0">Don't have an account?</p>
                        <a href="signup.php" class="btn btn-outline-primary mt-2">
                            <i class="fas fa-user-plus me-2"></i>Create Account
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function togglePassword() {
    const passwordInput = document.getElementById('password');
    const toggleIcon = document.getElementById('toggleIcon');
    
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

// Enhanced form validation and loading state
document.addEventListener('DOMContentLoaded', function() {
    const loginForm = document.getElementById('loginForm');
    const loginBtn = document.getElementById('loginBtn');
    const emailInput = document.getElementById('email');
    const passwordInput = document.getElementById('password');
    
    if (loginForm) {
        loginForm.addEventListener('submit', function(e) {
            const email = emailInput ? emailInput.value.trim() : '';
            const password = passwordInput ? passwordInput.value : '';
            
            // Client-side validation
            if (!email || !password) {
                e.preventDefault();
                alert('Please fill in all fields');
                return;
            }
            
            if (!email.includes('@') || !email.includes('.')) {
                e.preventDefault();
                alert('Please enter a valid email address');
                return;
            }
            
            if (password.length < 6) {
                e.preventDefault();
                alert('Password must be at least 6 characters long');
                return;
            }
            
            // Show loading state
            if (loginBtn) {
                loginBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Signing In...';
                loginBtn.disabled = true;
            }
        });
    }
    
    // Auto-focus email field
    if (emailInput) {
        emailInput.focus();
    }
    
    // Handle enter key on password field
    if (passwordInput) {
        passwordInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter' && loginForm) {
                loginForm.submit();
            }
        });
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

.input-group-text {
    background-color: #f8f9fa;
    border: 2px solid #e9ecef;
    border-right: none;
}

.input-group .form-control {
    border-left: none;
}

.input-group .form-control:focus {
    border-left: none;
}
</style>

<?php require_once '../includes/footer.php'; 

ob_end_flush();?>