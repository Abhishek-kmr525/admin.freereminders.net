<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// includes/header.php
require_once __DIR__ . '/debug.php';
if (!isset($pageTitle)) $pageTitle = 'LinkedIn Post Automation';
if (!isset($pageDescription)) $pageDescription = 'Automate your LinkedIn presence with AI';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($pageDescription); ?>">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <style>
        :root {
            --primary-color: #0077b5;
            --secondary-color: #00a0dc;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
        }
        
        .navbar-brand {
            font-weight: 700;
            color: var(--primary-color) !important;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .btn-primary:hover {
            background-color: var(--secondary-color);
            border-color: var(--secondary-color);
        }
        
        .text-primary {
            color: var(--primary-color) !important;
        }
        
        .alert-success {
            background-color: #d4edda;
            border-color: #c3e6cb;
            color: #155724;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            border-color: #f5c6cb;
            color: #721c24;
        }
        
        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(0, 119, 181, 0.25);
        }
        
        .country-selector {
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
        <div class="container">
           <a class="navbar-brand" href="<?php echo SITE_URL; ?>">
   <img src="../includes/logo.png" alt="Logo" height="30" class="d-inline-block align-text-top" style="
    width: 80px;
    height: 80px;
">
</a>

            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <?php if (isCustomerLoggedIn()): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="/customer/dashboard.php">Dashboard</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/customer/create-automation.php">Create Automation</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/customer/analytics.php">Analytics</a>
                        </li>
                    <?php endif; ?>
                </ul>
                
                <div class="d-flex align-items-center gap-2">
                    <!-- Country Selector -->
                    <div class="dropdown country-selector">
                        <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            <?php 
                            $currentCountry = getCustomerCountry();
                            $countryFlag = $currentCountry === 'in' ? '🇮🇳' : '🇺🇸';
                            $countryName = $currentCountry === 'in' ? 'India' : 'USA';
                            echo "$countryFlag $countryName";
                            ?>
                        </button>
                        <ul class="dropdown-menu">
                            <li>
                                <a class="dropdown-item" href="#" onclick="changeCountry('us')">
                                    🇺🇸 USA
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="#" onclick="changeCountry('in')">
                                    🇮🇳 India
                                </a>
                            </li>
                        </ul>
                    </div>
                    
                    <?php if (isCustomerLoggedIn()): ?>
                        <!-- Logged in user menu -->
                        <div class="dropdown">
                            <button class="btn btn-outline-primary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                <i class="fas fa-user me-1"></i>
                                <?php echo htmlspecialchars($_SESSION['customer_name'] ?? 'Account'); ?>
                            </button>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="/customer/profile.php"><i class="fas fa-user me-2"></i>Profile</a></li>
                                <li><a class="dropdown-item" href="/customer/billing.php"><i class="fas fa-credit-card me-2"></i>Billing</a></li>
                                <li><a class="dropdown-item" href="/customer/settings.php"><i class="fas fa-cog me-2"></i>Settings</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="/customer/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                            </ul>
                        </div>
                    <?php else: ?>
                        <!-- Guest user buttons -->
                        <a href="/customer/login.php" class="btn btn-outline-primary btn-sm">Login</a>
                        <a href="/customer/signup.php" class="btn btn-primary btn-sm">Sign Up</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>
    
    <!-- Alert Messages -->
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success alert-dismissible fade show m-3" role="alert">
            <i class="fas fa-check-circle me-2"></i>
            <?php 
            echo htmlspecialchars($_SESSION['success_message']); 
            unset($_SESSION['success_message']);
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger alert-dismissible fade show m-3" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i>
            <?php 
            echo htmlspecialchars($_SESSION['error_message']); 
            unset($_SESSION['error_message']);
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <script>
        // Country change function
        function changeCountry(country) {
            fetch('change-country.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ country: country })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.reload();
                } else {
                    alert('Error changing country. Please try again.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error changing country. Please try again.');
            });
        }
    </script>