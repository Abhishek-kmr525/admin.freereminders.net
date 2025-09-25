<?php
// customer/profile.php
require_once '../config/database-config.php';

$pageTitle = 'Edit Profile - ' . SITE_NAME;
$pageDescription = 'Update your profile information';

// Require customer login
requireCustomerLogin();

$error = '';
$success = '';

// Get customer details
$customer = getCustomerDetails($_SESSION['customer_id']);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitizeInput($_POST['name'] ?? '');
    $phone = sanitizeInput($_POST['phone'] ?? '');
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    // Validation
    if (empty($name)) {
        $error = 'Name is required';
    } elseif (strlen($name) < 2) {
        $error = 'Name must be at least 2 characters long';
    } else {
        try {
            $updateFields = [];
            $updateValues = [];
            
            // Always update name and phone
            $updateFields[] = "name = ?";
            $updateValues[] = $name;
            
            $updateFields[] = "phone = ?";
            $updateValues[] = $phone;
            
            // Handle password change
            if (!empty($currentPassword) || !empty($newPassword) || !empty($confirmPassword)) {
                if (empty($currentPassword)) {
                    $error = 'Current password is required to change password';
                } elseif (empty($newPassword)) {
                    $error = 'New password is required';
                } elseif (strlen($newPassword) < 8) {
                    $error = 'New password must be at least 8 characters long';
                } elseif ($newPassword !== $confirmPassword) {
                    $error = 'New passwords do not match';
                } elseif (!password_verify($currentPassword, $customer['password'])) {
                    $error = 'Current password is incorrect';
                } else {
                    $updateFields[] = "password = ?";
                    $updateValues[] = password_hash($newPassword, PASSWORD_DEFAULT);
                }
            }
            
            if (!$error) {
                $updateFields[] = "updated_at = NOW()";
                $updateValues[] = $_SESSION['customer_id'];
                
                $sql = "UPDATE customers SET " . implode(', ', $updateFields) . " WHERE id = ?";
                $stmt = $db->prepare($sql);
                $result = $stmt->execute($updateValues);
                
                if ($result) {
                    // Update session data
                    $_SESSION['customer_name'] = $name;
                    
                    logCustomerActivity($_SESSION['customer_id'], 'profile_updated', 'Profile information updated');
                    $success = 'Profile updated successfully!';
                    
                    // Refresh customer data
                    $customer = getCustomerDetails($_SESSION['customer_id']);
                } else {
                    $error = 'Failed to update profile. Please try again.';
                }
            }
            
        } catch (Exception $e) {
            error_log("Profile update error: " . $e->getMessage());
            $error = 'An error occurred while updating your profile.';
        }
    }
}

require_once '../includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="fw-bold">Edit Profile</h2>
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

            <div class="card shadow-lg border-0">
                <div class="card-header bg-primary text-white py-4">
                    <div class="text-center">
                        <div class="profile-avatar bg-white text-primary rounded-circle d-inline-flex align-items-center justify-content-center mb-2" style="width: 80px; height: 80px;">
                            <i class="fas fa-user fa-2x"></i>
                        </div>
                        <h4 class="mb-0"><?php echo htmlspecialchars($customer['name']); ?></h4>
                        <small class="opacity-75"><?php echo htmlspecialchars($customer['email']); ?></small>
                    </div>
                </div>
                
                <div class="card-body p-5">
                    <form method="POST" id="profileForm">
                        <!-- Personal Information -->
                        <div class="mb-4">
                            <h5 class="text-primary mb-3">
                                <i class="fas fa-user me-2"></i>Personal Information
                            </h5>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Full Name *</label>
                                        <input type="text" name="name" class="form-control" 
                                               value="<?php echo htmlspecialchars($customer['name']); ?>" 
                                               placeholder="Enter your full name" required>
                                    
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Phone Number</label>
                                        <input type="tel" name="phone" class="form-control" 
                                               value="<?php echo htmlspecialchars($customer['phone'] ?? ''); ?>" 
                                               placeholder="Enter your phone number">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Country</label>
                                        <input type="text" class="form-control" 
                                               value="<?php echo $customer['country'] === 'in' ? '🇮🇳 India' : '🇺🇸 United States'; ?>" 
                                               disabled>
                                        <div class="form-text text-muted">
                                            <i class="fas fa-info-circle me-1"></i>
                                            Contact support to change your country.
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Change Password -->
                        <div class="mb-4">
                            <h5 class="text-primary mb-3">
                                <i class="fas fa-lock me-2"></i>Change Password
                            </h5>
                            
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                Leave password fields empty if you don't want to change your password.
                            </div>
                            
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">Current Password</label>
                                        <input type="password" name="current_password" class="form-control" 
                                               placeholder="Enter current password">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">New Password</label>
                                        <input type="password" name="new_password" class="form-control" 
                                               placeholder="Enter new password" id="newPassword">
                                        <div class="form-text">
                                            <small id="passwordStrength" class="text-muted">Password strength: Enter a password</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">Confirm New Password</label>
                                        <input type="password" name="confirm_password" class="form-control" 
                                               placeholder="Confirm new password" id="confirmPassword">
                                        <div id="passwordMatch" class="form-text"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Account Information -->
                        <div class="mb-4">
                            <h5 class="text-primary mb-3">
                                <i class="fas fa-info-circle me-2"></i>Account Information
                            </h5>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Account Status</label>
                                        <div class="form-control-plaintext">
                                            <span class="badge bg-<?php echo $customer['subscription_status'] === 'active' ? 'success' : 'warning'; ?> fs-6">
                                                <?php echo ucfirst($customer['subscription_status']); ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Member Since</label>
                                        <div class="form-control-plaintext">
                                            <?php echo date('F j, Y', strtotime($customer['created_at'])); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Submit Button -->
                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <a href="settings.php" class="btn btn-outline-secondary">
                                <i class="fas fa-times me-2"></i>Cancel
                            </a>
                            <button type="submit" class="btn btn-primary" id="saveBtn">
                                <i class="fas fa-save me-2"></i>Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Password strength checker
function checkPasswordStrength(password) {
    let strength = 0;
    let feedback = [];
    
    if (password.length >= 8) {
        strength++;
    } else {
        feedback.push('at least 8 characters');
    }
    
    if (/[a-z]/.test(password)) {
        strength++;
    } else {
        feedback.push('a lowercase letter');
    }
    
    if (/[A-Z]/.test(password)) {
        strength++;
    } else {
        feedback.push('an uppercase letter');
    }
    
    if (/\d/.test(password)) {
        strength++;
    } else {
        feedback.push('a number');
    }
    
    if (/[^a-zA-Z\d]/.test(password)) {
        strength++;
    } else {
        feedback.push('a special character');
    }
    
    return {
        score: strength,
        feedback: feedback,
        level: strength <= 2 ? 'weak' : (strength <= 3 ? 'medium' : 'strong')
    };
}

document.addEventListener('DOMContentLoaded', function() {
    const profileForm = document.getElementById('profileForm');
    const saveBtn = document.getElementById('saveBtn');
    const newPasswordInput = document.getElementById('newPassword');
    const confirmPasswordInput = document.getElementById('confirmPassword');
    
    // Password strength indicator
    if (newPasswordInput) {
        newPasswordInput.addEventListener('input', function() {
            const password = this.value;
            const strengthIndicator = document.getElementById('passwordStrength');
            
            if (password.length === 0) {
                strengthIndicator.textContent = 'Password strength: Enter a password';
                strengthIndicator.className = 'text-muted';
                return;
            }
            
            const result = checkPasswordStrength(password);
            
            let strengthText = `Password strength: ${result.level.charAt(0).toUpperCase() + result.level.slice(1)}`;
            let strengthClass = '';
            
            if (result.level === 'weak') {
                strengthClass = 'text-danger';
                if (result.feedback.length > 0) {
                    strengthText += ` (needs: ${result.feedback.slice(0, 2).join(', ')})`;
                }
            } else if (result.level === 'medium') {
                strengthClass = 'text-warning';
            } else {
                strengthClass = 'text-success';
            }
            
            strengthIndicator.textContent = strengthText;
            strengthIndicator.className = strengthClass;
        });
    }
    
    // Password match indicator
    if (confirmPasswordInput) {
        confirmPasswordInput.addEventListener('input', function() {
            const password = newPasswordInput ? newPasswordInput.value : '';
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
    
    // Form submission
    if (profileForm) {
        profileForm.addEventListener('submit', function(e) {
            const currentPassword = document.querySelector('input[name="current_password"]').value;
            const newPassword = newPasswordInput ? newPasswordInput.value : '';
            const confirmPassword = confirmPasswordInput ? confirmPasswordInput.value : '';
            
            // If any password field is filled, validate all
            if (currentPassword || newPassword || confirmPassword) {
                if (!currentPassword) {
                    e.preventDefault();
                    alert('Please enter your current password to change it');
                    return;
                }
                
                if (!newPassword || newPassword.length < 8) {
                    e.preventDefault();
                    alert('New password must be at least 8 characters long');
                    return;
                }
                
                if (newPassword !== confirmPassword) {
                    e.preventDefault();
                    alert('New passwords do not match');
                    return;
                }
            }
            
            // Show loading state
            if (saveBtn) {
                saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Saving...';
                saveBtn.disabled = true;
            }
        });
    }
});
</script>

<style>
.card {
    border-radius: 15px;
    border: 0;
    box-shadow: 0 5px 15px rgba(0,0,0,0.08);
}

.profile-avatar {
    width: 80px;
    height: 80px;
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

.btn-primary {
    background: linear-gradient(135deg, #0077b5 0%, #00a0dc 100%);
    border: none;
    border-radius: 8px;
}

.btn-primary:hover {
    background: linear-gradient(135deg, #005885 0%, #0077b5 100%);
    transform: translateY(-2px);
}

.alert {
    border-radius: 12px;
    border: 0;
}

.badge {
    font-size: 0.8em;
}
</style>

<?php require_once '../includes/footer.php'; ?> 