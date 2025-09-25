<?php
// customer/oauth/linkedin-callback.php
// LinkedIn OAuth callback handler

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../../config/database-config.php';
require_once '../../config/oauth-config.php';

// Handle LinkedIn OAuth callback
try {
    // Validate state parameter
    $state = $_GET['state'] ?? '';
    if (!validateState($state)) {
        throw new Exception('Invalid state parameter. Please try again.');
    }
    
    // Check for errors
    if (isset($_GET['error'])) {
        $error = $_GET['error'];
        $errorDescription = $_GET['error_description'] ?? 'Unknown error';
        throw new Exception("LinkedIn OAuth error: $error - $errorDescription");
    }
    
    // Get authorization code
    $code = $_GET['code'] ?? '';
    if (empty($code)) {
        throw new Exception('Authorization code not received from LinkedIn');
    }
    
    // Exchange code for access token
    $tokenData = exchangeLinkedInCodeForToken($code);
    $accessToken = $tokenData['access_token'];
    $expiresIn = $tokenData['expires_in'] ?? 3600;
    
    // Get user info from LinkedIn
    $userInfo = getLinkedInUserInfo($accessToken);
    
    // Create or update customer
    $customer = createOrUpdateOAuthCustomer('linkedin', $userInfo);
    
    // Store LinkedIn access token
    $expiresAt = date('Y-m-d H:i:s', time() + $expiresIn);
    $linkedinUserId = $userInfo['profile']['id'] ?? null;
    
    $stmt = $db->prepare("
        INSERT INTO customer_linkedin_tokens (customer_id, access_token, linkedin_user_id, expires_at)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            access_token = VALUES(access_token),
            linkedin_user_id = VALUES(linkedin_user_id),
            expires_at = VALUES(expires_at),
            updated_at = NOW()
    ");
    $stmt->execute([$customer['id'], $accessToken, $linkedinUserId, $expiresAt]);
    
    // Clear OAuth state
    clearOAuthState();
    
    // Log successful connection
    logCustomerActivity(
        $customer['id'], 
        'linkedin_connected', 
        'LinkedIn account connected successfully',
        $_SERVER['REMOTE_ADDR'] ?? '',
        $_SERVER['HTTP_USER_AGENT'] ?? ''
    );
    
    // Redirect to success page
    header('Location: ' . SITE_URL . '/customer/oauth-success.php?provider=linkedin');
    exit();
    
} catch (Exception $e) {
    error_log("LinkedIn OAuth callback error: " . $e->getMessage());
    
    // Clear OAuth state on error
    clearOAuthState();
    
    // Redirect to error page with message
    $errorMessage = urlencode($e->getMessage());
    header("Location: " . SITE_URL . "/customer/oauth-error.php?error=$errorMessage&provider=linkedin");
    exit();
}
?>

<?php
// customer/oauth/google-callback.php
// Google OAuth callback handler

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../../config/database-config.php';
require_once '../../config/oauth-config.php';

// Handle Google OAuth callback
try {
    // Validate state parameter
    $state = $_GET['state'] ?? '';
    if (!validateState($state)) {
        throw new Exception('Invalid state parameter. Please try again.');
    }
    
    // Check for errors
    if (isset($_GET['error'])) {
        $error = $_GET['error'];
        $errorDescription = $_GET['error_description'] ?? 'Unknown error';
        throw new Exception("Google OAuth error: $error - $errorDescription");
    }
    
    // Get authorization code
    $code = $_GET['code'] ?? '';
    if (empty($code)) {
        throw new Exception('Authorization code not received from Google');
    }
    
    // Exchange code for access token
    $tokenData = exchangeGoogleCodeForToken($code);
    $accessToken = $tokenData['access_token'];
    
    // Get user info from Google
    $userInfo = getGoogleUserInfo($accessToken);
    
    // Create or update customer
    $customer = createOrUpdateOAuthCustomer('google', $userInfo);
    
    // Clear OAuth state
    clearOAuthState();
    
    // Log successful connection
    logCustomerActivity(
        $customer['id'], 
        'google_connected', 
        'Google account connected successfully',
        $_SERVER['REMOTE_ADDR'] ?? '',
        $_SERVER['HTTP_USER_AGENT'] ?? ''
    );
    
    // Redirect to success page
    header('Location: ' . SITE_URL . '/customer/oauth-success.php?provider=google');
    exit();
    
} catch (Exception $e) {
    error_log("Google OAuth callback error: " . $e->getMessage());
    
    // Clear OAuth state on error
    clearOAuthState();
    
    // Redirect to error page with message
    $errorMessage = urlencode($e->getMessage());
    header("Location: " . SITE_URL . "/customer/oauth-error.php?error=$errorMessage&provider=google");
    exit();
}
?>

<?php
// customer/oauth-success.php
// OAuth success page

session_start();
require_once '../config/database-config.php';

$provider = $_GET['provider'] ?? 'OAuth';
$providerName = ucfirst($provider);

// Check if user is logged in
$isLoggedIn = isset($_SESSION['customer_id']);
$customerName = $_SESSION['customer_name'] ?? 'User';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Authentication Successful - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .success-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            padding: 3rem;
            text-align: center;
            max-width: 500px;
            width: 90%;
        }
        .success-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #10b981, #059669);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 2rem;
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        .provider-icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-right: 12px;
        }
        .linkedin-icon { background: #0077b5; }
        .google-icon { background: #4285f4; }
    </style>
</head>
<body>
    <div class="success-card">
        <div class="success-icon">
            <i class="fas fa-check text-white fa-2x"></i>
        </div>
        
        <h2 class="fw-bold mb-3">Successfully Connected!</h2>
        
        <div class="d-flex align-items-center justify-content-center mb-3">
            <?php if ($provider === 'linkedin'): ?>
                <div class="provider-icon linkedin-icon">
                    <i class="fab fa-linkedin text-white"></i>
                </div>
            <?php elseif ($provider === 'google'): ?>
                <div class="provider-icon google-icon">
                    <i class="fab fa-google text-white"></i>
                </div>
            <?php endif; ?>
            <span class="fw-semibold"><?php echo $providerName; ?> Account Connected</span>
        </div>
        
        <?php if ($isLoggedIn): ?>
            <p class="text-muted mb-4">
                Welcome back, <strong><?php echo htmlspecialchars($customerName); ?></strong>! 
                Your <?php echo $providerName; ?> account has been successfully connected.
            </p>
            
            <?php if ($provider === 'linkedin'): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    You can now create LinkedIn automations and schedule posts!
                </div>
            <?php endif; ?>
            
            <div class="d-grid gap-2">
                <a href="<?php echo SITE_URL; ?>/customer/dashboard.php" class="btn btn-primary btn-lg">
                    <i class="fas fa-tachometer-alt me-2"></i>Go to Dashboard
                </a>
                <a href="<?php echo SITE_URL; ?>/customer/create-automation.php" class="btn btn-outline-primary">
                    <i class="fas fa-robot me-2"></i>Create Your First Automation
                </a>
            </div>
        <?php else: ?>
            <p class="text-muted mb-4">
                Your <?php echo $providerName; ?> account has been connected successfully. 
                You can now access all features of our platform.
            </p>
            
            <div class="d-grid gap-2">
                <a href="<?php echo SITE_URL; ?>/customer/register.php" class="btn btn-primary btn-lg">
                    <i class="fas fa-user-plus me-2"></i>Complete Registration
                </a>
                <a href="<?php echo SITE_URL; ?>/" class="btn btn-outline-secondary">
                    <i class="fas fa-home me-2"></i>Back to Home
                </a>
            </div>
        <?php endif; ?>
        
        <div class="mt-4 pt-3 border-top">
            <small class="text-muted">
                <i class="fas fa-shield-alt me-1"></i>
                Your connection is secure and encrypted
            </small>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-redirect after 10 seconds if logged in
        <?php if ($isLoggedIn): ?>
        setTimeout(() => {
            window.location.href = '<?php echo SITE_URL; ?>/customer/dashboard.php';
        }, 10000);
        <?php endif; ?>
    </script>
</body>
</html>

<?php
// customer/oauth-error.php
// OAuth error page

$error = $_GET['error'] ?? 'An unknown error occurred';
$provider = $_GET['provider'] ?? 'OAuth';
$providerName = ucfirst($provider);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Authentication Error - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .error-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            padding: 3rem;
            text-align: center;
            max-width: 500px;
            width: 90%;
        }
        .error-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #ef4444, #dc2626);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 2rem;
        }
    </style>
</head>
<body>
    <div class="error-card">
        <div class="error-icon">
            <i class="fas fa-exclamation-triangle text-white fa-2x"></i>
        </div>
        
        <h2 class="fw-bold mb-3 text-danger">Authentication Failed</h2>
        
        <p class="text-muted mb-4">
            Sorry, we couldn't connect your <?php echo $providerName; ?> account.
        </p>
        
        <div class="alert alert-danger text-start">
            <strong>Error Details:</strong><br>
            <?php echo htmlspecialchars($error); ?>
        </div>
        
        <div class="d-grid gap-2 mt-4">
            <a href="<?php echo SITE_URL; ?>/customer/login.php" class="btn btn-primary">
                <i class="fas fa-redo me-2"></i>Try Again
            </a>
            <a href="<?php echo SITE_URL; ?>/" class="btn btn-outline-secondary">
                <i class="fas fa-home me-2"></i>Back to Home
            </a>
        </div>
        
        <div class="mt-4 pt-3 border-top">
            <small class="text-muted">
                If the problem persists, please contact our support team.
            </small>
        </div>
    </div>
</body>
</html>