<?php
// customer/forgot-password.php
require_once '../config/database-config.php';

$pageTitle = 'Forgot Password - ' . SITE_NAME;
$pageDescription = 'Reset your password for your LinkedIn automation account';

// Redirect if already logged in
if (isCustomerLoggedIn()) {
    redirectTo('dashboard.php');
}

$error = '';
$success = '';
$step = $_GET['step'] ?? 'request'; // request, verify, reset
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($step === 'request') {
        // Step 1: Request password reset
        $email = sanitizeInput($_POST['email'] ?? '');
        
        if (empty($email)) {
            $error = 'Please enter your email address';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address';
        } else {
            try {
                // Check if email exists
                $stmt = $db->prepare("SELECT id, name FROM customers WHERE email = ? AND status = 'active'");
                $stmt->execute(array($email));
                $customer = $stmt->fetch();
                
                if ($customer) {
                    // Generate reset token
                    $resetToken = generateToken(32);
                    $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));
                    
                    // Store reset token
                    $stmt = $db->prepare("
                        INSERT INTO password_resets (customer_id, token, expires_at, created_at) 
                        VALUES (?, ?, ?, NOW())
                        ON DUPLICATE KEY UPDATE 
                        token = VALUES(token), 
                        expires_at = VALUES(expires_at), 
                        created_at = NOW()
                    ");
                    $stmt->execute(array($customer['id'], $resetToken, $expiresAt));
                    
                    // TODO: Send email with reset link
                    // For now, we'll show a success message with the token (remove in production)
                    $resetLink = SITE_URL . "/customer/forgot-password.php?step=reset&token=" . $resetToken;
                    
                    // Log activity
                    logCustomerActivity($customer['id'], 'password_reset_requested', 'Password reset token generated');
                    
                    $success = "Password reset instructions have been sent to your email address.";
                    // In development, show the reset link
                    if (error_reporting() !== 0) {
                        $success .= "<br><small class='text-muted'>Development Mode - Reset Link: <a href='$resetLink'>Click here</a></small>";
                    }
                } else {
                    // Don't reveal if email exists or not for security
                    $success = "If an account with that email exists, password reset instructions have been sent.";
                }
                
            } catch (Exception $e) {
                logError("Password reset request error: " . $e->getMessage());
                $error = 'An error occurred. Please try again.';
            }
        }
        
    } elseif ($step === 'reset') {
        // Step 2: Reset password with token
        $token = sanitizeInput($_POST['token'] ?? '');
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        if (empty($token) || empty($newPassword) || empty($confirmPassword)) {
            $error = 'Please fill in all fields';
        } elseif (strlen($newPassword) < 8) {
            $error = 'Password must be at least 8 characters long';
        } elseif ($newPassword !== $confirmPassword) {
            $error = 'Passwords do not match';
        } else {
            try {
                // Verify token
                $stmt = $db->prepare("
                    SELECT pr.customer_id, c.email 
                    FROM password_resets pr
                    JOIN customers c ON pr.customer_id = c.id
                    WHERE pr.token = ? AND pr.expires_at > NOW() AND c.status = 'active'
                ");
                $stmt->execute(array($token));
                $resetData = $stmt->fetch();
                
                if ($resetData) {
                    // Update password
                    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                    
                    $stmt = $db->prepare("UPDATE customers SET password = ?, updated_at = NOW() WHERE id = ?");
                    $stmt->execute(array($hashedPassword, $resetData['customer_id']));
                    
                    // Delete used token
                    $stmt = $db->prepare("DELETE FROM password_resets WHERE customer_id = ?");
                    $stmt->execute(array($resetData['customer_id']));
                    
                    // Log activity
                    logCustomerActivity($resetData['customer_id'], 'password_reset_completed', 'Password successfully reset');
                    
                    // Redirect to login with success message
                    redirectTo('login.php?success=password_reset');
                    
                } else {
                    $error = 'Invalid or expired reset token. Please request a new password reset.';
                }
                
            } catch (Exception $e) {
                logError("Password reset completion error: " . $e->getMessage());
                $error = 'An error occurred. Please try again.';
            }
        }
    }
}

// Handle reset token verification for display
if ($step === 'reset') {
    $token = sanitizeInput($_GET['token'] ?? '');
    if (!empty($token)) {
        try {
            $stmt = $db->prepare("
                SELECT pr.customer_id, c.email 
                FROM password_resets pr
                JOIN customers c ON pr.customer_id = c.id
                WHERE pr.token = ? AND pr.expires_at > NOW() AND c.status = 'active'
            ");
            $stmt->execute(array($token));
            $resetData = $stmt->fetch();
            
            if (!$resetData) {
                $error = 'Invalid or expired reset token. Please request a new password reset.';
                $step = 'request';
            }
        } catch (Exception $e) {
            $error = 'Invalid reset token.';
            $step = 'request';
        }
    } else {
        $error = 'Missing reset token.';
        $step = 'request';
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
                        <i class="fas fa-key fa-3x text-primary mb-3"></i>
                        <h2 class="fw-bold">
                            <?php echo $step === 'reset' ? 'Reset Your Password' : 'Forgot Password?'; ?>
                        </h2>
                        <p class="text-muted">
                            <?php echo $step === 'reset' ? 'Enter your new password below' : 'No worries! Enter your email and we\'ll send you reset instructions.'; ?>
                        </p>
                    </div>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($step === 'request'): ?>
                        <!-- Step 1: Request Reset -->
                        <form method="POST" id="resetRequestForm">
                            <div class="mb-4">
                                <label for="email" class="form-label">Email Address</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-envelope"></i>
                                    </span>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?php echo htmlspecialchars($email); ?>" 
                                           placeholder="Enter your registered email" required>
                                </div>
                                <div class="form-text">
                                    We'll send password reset instructions to this email address.
                                </div>
                            </div>
                            
                            <div class="d-grid mb-3">
                                <button type="submit" class="btn btn-primary btn-lg" onclick="showLoading(this)">
                                    <i class="fas fa-paper-plane me-2"></i>Send Reset Instructions
                                </button>
                            </div>
                        </form>
                        
                    <?php elseif ($step === 'reset' && !$error): ?>
                        <!-- Step 2: Reset Password -->
                        <form method="POST" id="resetPasswordForm">
                            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                            
                            <div class="mb-3">
                                <label for="new_password" class="form-label">New Password</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-lock"></i>
                                    </span>
                                    <input type="password" class="form-control" id="new_password" name="new_password" 
                                           placeholder="Enter new password (min 8 characters)" required>
                                    <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('new_password', 'toggleIcon1')">
                                        <i class="fas fa-eye" id="toggleIcon1"></i>
                                    </button>
                                </div>
                                <div class="form-text">
                                    <small id="passwordStrength" class="text-muted">Password strength: </small>
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <label for="confirm_password" class="form-label">Confirm New Password</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-lock"></i>
                                    </span>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password"
                                           placeholder="Re-enter new password" required>
                                    <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('confirm_password','toggleIcon2')">
                                        <i class="fas fa-eye" id="toggleIcon2"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="d-grid mb-3">
                                <button type="submit" class="btn btn-primary btn-lg" onclick="showLoading(this)">
                                    <i class="fas fa-check me-2"></i>Reset Password
                                </button>
                            </div>

                            <div class="text-center">
                                <a href="login.php">Back to Login</a>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Toggle password visibility
    function togglePassword(inputId, iconId) {
        var input = document.getElementById(inputId);
        var icon = document.getElementById(iconId);
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    }

    // Simple password strength estimation
    function estimatePasswordStrength(password) {
        var score = 0;
        if (!password) return {score: 0, text: 'Too short'};
        if (password.length >= 8) score++;
        if (/[A-Z]/.test(password)) score++;
        if (/[0-9]/.test(password)) score++;
        if (/[^A-Za-z0-9]/.test(password)) score++;

        var text = 'Weak';
        if (score >= 4) text = 'Very strong';
        else if (score === 3) text = 'Strong';
        else if (score === 2) text = 'Medium';

        return {score: score, text: text};
    }

    document.addEventListener('DOMContentLoaded', function() {
        var newPassword = document.getElementById('new_password');
        var strengthEl = document.getElementById('passwordStrength');
        var confirmPassword = document.getElementById('confirm_password');

        if (newPassword) {
            newPassword.addEventListener('input', function() {
                var res = estimatePasswordStrength(newPassword.value);
                strengthEl.innerText = 'Password strength: ' + res.text;
                strengthEl.className = 'text-muted';
                if (res.score <= 1) strengthEl.classList.add('text-danger');
                else if (res.score === 2) strengthEl.classList.add('text-warning');
                else strengthEl.classList.add('text-success');
            });
        }

        if (confirmPassword && newPassword) {
            confirmPassword.addEventListener('input', function() {
                if (confirmPassword.value === '') {
                    confirmPassword.classList.remove('is-valid','is-invalid');
                } else if (confirmPassword.value === newPassword.value) {
                    confirmPassword.classList.add('is-valid');
                    confirmPassword.classList.remove('is-invalid');
                } else {
                    confirmPassword.classList.add('is-invalid');
                    confirmPassword.classList.remove('is-valid');
                }
            });
        }

        // Ensure reset form posts to ?step=reset so server-side $step remains correct
        var resetForm = document.getElementById('resetPasswordForm');
        if (resetForm) {
            // If current URL doesn't include step=reset, append it to action
            if (!resetForm.action || resetForm.action.indexOf('step=reset') === -1) {
                var tokenParam = '';
                try {
                    var params = new URLSearchParams(window.location.search);
                    if (params.get('token')) tokenParam = '&token=' + encodeURIComponent(params.get('token'));
                } catch (e) { /* ignore */ }
                resetForm.action = window.location.pathname + '?step=reset' + tokenParam;
            }

            resetForm.addEventListener('submit', function(e) {
                var pw = newPassword.value || '';
                var cpw = confirmPassword.value || '';
                if (pw.length < 8) {
                    e.preventDefault();
                    alert('Password must be at least 8 characters long');
                } else if (pw !== cpw) {
                    e.preventDefault();
                    alert('Passwords do not match');
                }
            });
        }

        // Ensure request form posts to ?step=request
        var reqForm = document.getElementById('resetRequestForm');
        if (reqForm) {
            if (!reqForm.action || reqForm.action.indexOf('step=request') === -1) {
                reqForm.action = window.location.pathname + '?step=request';
            }

            reqForm.addEventListener('submit', function(e) {
                var emailInput = document.getElementById('email');
                if (!emailInput || !validateEmail(emailInput.value)) {
                    e.preventDefault();
                    alert('Please enter a valid email address');
                }
            });
        }
    });
</script>

<?php require_once '../includes/footer.php'; ?>