<?php
// admin/api-settings.php
session_start();
require_once '../config/database-config.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}

$message = '';
$error = '';

// Get current API settings
$settings = [];
try {
    $stmt = $db->prepare("SELECT * FROM api_settings WHERE id = 1");
    $stmt->execute();
    $settings = $stmt->fetch() ?: [];
} catch (Exception $e) {
    error_log("Get API settings error: " . $e->getMessage());
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $geminiApiKey = trim($_POST['gemini_api_key'] ?? '');
    $chatgptApiKey = trim($_POST['chatgpt_api_key'] ?? '');
    $linkedinClientId = trim($_POST['linkedin_client_id'] ?? '');
    $linkedinClientSecret = trim($_POST['linkedin_client_secret'] ?? '');
    $googleClientId = trim($_POST['google_client_id'] ?? '');
    $googleClientSecret = trim($_POST['google_client_secret'] ?? '');
    $razorpayKeyId = trim($_POST['razorpay_key_id'] ?? '');
    $razorpayKeySecret = trim($_POST['razorpay_key_secret'] ?? '');
    $webhookSecret = trim($_POST['webhook_secret'] ?? '');
    $smtpHost = trim($_POST['smtp_host'] ?? '');
    $smtpPort = trim($_POST['smtp_port'] ?? '');
    $smtpUsername = trim($_POST['smtp_username'] ?? '');
    $smtpPassword = trim($_POST['smtp_password'] ?? '');
    
    try {
        // Update or insert API settings
        $stmt = $db->prepare("
            INSERT INTO api_settings (
                id, gemini_api_key, chatgpt_api_key, linkedin_client_id, 
                linkedin_client_secret, google_client_id, google_client_secret, 
                razorpay_key_id, razorpay_key_secret,
                webhook_secret, smtp_host, smtp_port, smtp_username, smtp_password, updated_at
            ) VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
                gemini_api_key = VALUES(gemini_api_key),
                chatgpt_api_key = VALUES(chatgpt_api_key),
                linkedin_client_id = VALUES(linkedin_client_id),
                linkedin_client_secret = VALUES(linkedin_client_secret),
                google_client_id = VALUES(google_client_id),
                google_client_secret = VALUES(google_client_secret),
                razorpay_key_id = VALUES(razorpay_key_id),
                razorpay_key_secret = VALUES(razorpay_key_secret),
                webhook_secret = VALUES(webhook_secret),
                smtp_host = VALUES(smtp_host),
                smtp_port = VALUES(smtp_port),
                smtp_username = VALUES(smtp_username),
                smtp_password = VALUES(smtp_password),
                updated_at = NOW()
        ");
        
        $success = $stmt->execute([
            $geminiApiKey, $chatgptApiKey, $linkedinClientId,
            $linkedinClientSecret, $googleClientId, $googleClientSecret, 
            $razorpayKeyId, $razorpayKeySecret,
            $webhookSecret, $smtpHost, $smtpPort, $smtpUsername, $smtpPassword
        ]);
        
        if ($success) {
            $message = 'API settings updated successfully!';
            
            // Refresh settings
            $stmt = $db->prepare("SELECT * FROM api_settings WHERE id = 1");
            $stmt->execute();
            $settings = $stmt->fetch() ?: [];
        } else {
            $error = 'Failed to update API settings';
        }
    } catch (Exception $e) {
        error_log("Update API settings error: " . $e->getMessage());
        $error = 'An error occurred while updating settings';
    }
}

// Test API connection functions
function testGeminiAPI($apiKey) {
    if (empty($apiKey)) return ['status' => false, 'message' => 'API key not set'];
    
    $data = [
        'contents' => [
            ['parts' => [['text' => 'Test connection. Reply with "OK"']]]
        ]
    ];
    
    $ch = curl_init("https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent?key=" . $apiKey);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        return ['status' => true, 'message' => 'Connection successful'];
    }
    
    return ['status' => false, 'message' => 'Connection failed. HTTP Code: ' . $httpCode];
}

function testChatGPTAPI($apiKey) {
    if (empty($apiKey)) return ['status' => false, 'message' => 'API key not set'];
    
    $data = [
        'model' => 'gpt-3.5-turbo',
        'messages' => [
            ['role' => 'user', 'content' => 'Test connection. Reply with "OK"']
        ],
        'max_tokens' => 10
    ];
    
    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        return ['status' => true, 'message' => 'Connection successful'];
    }
    
    return ['status' => false, 'message' => 'Connection failed. HTTP Code: ' . $httpCode];
}

// Handle AJAX test requests
if (isset($_POST['test_api'])) {
    $provider = $_POST['test_api'];
    $apiKey = $_POST['api_key'] ?? '';
    
    if ($provider === 'gemini') {
        $result = testGeminiAPI($apiKey);
    } elseif ($provider === 'chatgpt') {
        $result = testChatGPTAPI($apiKey);
    } else {
        $result = ['status' => false, 'message' => 'Unknown provider'];
    }
    
    header('Content-Type: application/json');
    echo json_encode($result);
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>API Settings - LinkedIn Automation Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            font-family: 'Inter', sans-serif;
        }
        
        body {
            background-color: #f8fafc;
            color: #334155;
        }
        
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(180deg, #1e293b 0%, #0f172a 100%);
            box-shadow: 4px 0 12px rgba(0, 0, 0, 0.15);
            position: sticky;
            top: 0;
        }
        
        .sidebar .nav-link {
            color: #cbd5e1;
            padding: 0.875rem 1.5rem;
            border-radius: 0.5rem;
            margin: 0.25rem 0.75rem;
            transition: all 0.3s ease;
            font-weight: 500;
        }
        
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            color: white;
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            box-shadow: 0 4px 8px rgba(59, 130, 246, 0.3);
        }
        
        .sidebar .nav-link i {
            width: 20px;
            margin-right: 0.75rem;
        }
        
        .main-content {
            padding: 2rem;
        }
        
        .api-section {
            background: white;
            border-radius: 1rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            border: 1px solid #e2e8f0;
            margin-bottom: 2rem;
            overflow: hidden;
            transition: all 0.3s ease;
        }
        
        .api-section:hover {
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.15);
            transform: translateY(-2px);
        }
        
        .api-section-header {
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            padding: 1.5rem 2rem;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .api-section-body {
            padding: 2rem;
        }
        
        .api-icon {
            width: 60px;
            height: 60px;
            border-radius: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
            margin-right: 1rem;
        }
        
        .form-control, .form-select {
            border-radius: 0.5rem;
            border: 1px solid #d1d5db;
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        .btn-test {
            background: linear-gradient(135deg, #10b981, #059669);
            border: none;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .btn-test:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);
            color: white;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            border: none;
            padding: 0.75rem 2rem;
            border-radius: 0.75rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(59, 130, 246, 0.4);
        }
        
        .status-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: inline-block;
            margin-left: 0.5rem;
        }
        
        .status-success { background-color: #10b981; }
        .status-error { background-color: #ef4444; }
        .status-unknown { background-color: #6b7280; }
        .status-testing { 
            background-color: #f59e0b; 
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        
        .guide-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 1rem;
            padding: 2rem;
            margin-top: 2rem;
        }
        
        .guide-step {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 0.5rem;
            padding: 1rem;
            margin-bottom: 1rem;
            backdrop-filter: blur(10px);
        }
        
        .password-toggle {
            cursor: pointer;
            color: #6b7280;
            transition: color 0.3s ease;
        }
        
        .password-toggle:hover {
            color: #374151;
        }
        
        .alert {
            border-radius: 0.75rem;
            border: none;
            padding: 1rem 1.5rem;
            font-weight: 500;
        }
        
        .alert-success {
            background: linear-gradient(135deg, #dcfce7, #bbf7d0);
            color: #166534;
        }
        
        .alert-danger {
            background: linear-gradient(135deg, #fee2e2, #fecaca);
            color: #991b1b;
        }
        
        .nav-tabs {
            border: none;
            margin-bottom: 2rem;
        }
        
        .nav-tabs .nav-link {
            border: none;
            background: transparent;
            color: #64748b;
            font-weight: 500;
            padding: 0.75rem 1.5rem;
            margin-right: 0.5rem;
            border-radius: 0.5rem;
            transition: all 0.3s ease;
        }
        
        .nav-tabs .nav-link.active {
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            color: white;
        }
        
        @media (max-width: 768px) {
            .main-content {
                padding: 1rem;
            }
            
            .api-section-header, .api-section-body {
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar (same as dashboard) -->
            <nav class="col-md-3 col-lg-2 sidebar">
                <div class="position-sticky pt-4">
                    <!-- Logo Section -->
                    <div class="text-center mb-4 px-3">
                        <div class="d-flex align-items-center justify-content-center">
                            <div class="bg-primary rounded-circle p-2 me-2">
                                <i class="fas fa-shield-alt text-white"></i>
                            </div>
                            <div>
                                <h6 class="text-white fw-bold mb-0">Admin Panel</h6>
                                <small class="text-muted">LinkedIn Automation</small>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Navigation -->
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a href="dashboard.php" class="nav-link">
                                <i class="fas fa-home"></i>Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="customers.php" class="nav-link">
                                <i class="fas fa-users"></i>Customers
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="automations.php" class="nav-link">
                                <i class="fas fa-robot"></i>Automations
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="posts.php" class="nav-link">
                                <i class="fas fa-newspaper"></i>Generated Posts
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="api-settings.php" class="nav-link active">
                                <i class="fas fa-key"></i>API Settings
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="templates.php" class="nav-link">
                                <i class="fas fa-file-alt"></i>Content Templates
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="analytics.php" class="nav-link">
                                <i class="fas fa-chart-bar"></i>Analytics
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="settings.php" class="nav-link">
                                <i class="fas fa-cog"></i>System Settings
                            </a>
                        </li>
                    </ul>
                    
                    <!-- User Menu -->
                    <div class="mt-auto px-3 py-3">
                        <hr class="text-white-50">
                        <div class="dropdown">
                            <a href="#" class="nav-link dropdown-toggle d-flex align-items-center" data-bs-toggle="dropdown">
                                <div class="bg-primary rounded-circle p-2 me-2">
                                    <i class="fas fa-user text-white"></i>
                                </div>
                                <div>
                                    <div class="text-white fw-semibold"><?php echo htmlspecialchars($_SESSION['admin_username']); ?></div>
                                    <small class="text-muted">Administrator</small>
                                </div>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-dark">
                                <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user me-2"></i>Profile</a></li>
                                <li><a class="dropdown-item" href="settings.php"><i class="fas fa-cog me-2"></i>Settings</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </nav>
            
            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 main-content">
                <!-- Page Header -->
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-4 border-bottom">
                    <div>
                        <h1 class="h2 fw-bold">
                            <i class="fas fa-key me-3 text-primary"></i>API Settings & Credentials
                        </h1>
                        <p class="text-muted mb-0">Configure your API keys and external service credentials</p>
                    </div>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="location.reload()">
                                <i class="fas fa-sync-alt me-1"></i>Refresh
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Status Messages -->
                <?php if ($message): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <form method="POST" id="apiSettingsForm">
                    <!-- AI Services Section -->
                    <div class="api-section">
                        <div class="api-section-header">
                            <div class="d-flex align-items-center">
                                <div class="api-icon" style="background: linear-gradient(135deg, #3b82f6, #1d4ed8);">
                                    <i class="fas fa-robot"></i>
                                </div>
                                <div>
                                    <h4 class="fw-bold mb-1">AI Content Generation Services</h4>
                                    <p class="text-muted mb-0">Configure AI providers for automated content generation</p>
                                </div>
                            </div>
                        </div>
                        <div class="api-section-body">
                            <div class="row">
                                <!-- Google Gemini -->
                                <div class="col-lg-6 mb-4">
                                    <div class="card h-100 border-0 shadow-sm">
                                        <div class="card-body">
                                            <div class="d-flex align-items-center mb-3">
                                                <div style="width: 40px; height: 40px; background: linear-gradient(135deg, #4285f4, #34a853); border-radius: 8px; display: flex; align-items: center; justify-content: center; margin-right: 12px;">
                                                    <i class="fas fa-brain text-white"></i>
                                                </div>
                                                <div>
                                                    <h5 class="fw-bold mb-1">Google Gemini</h5>
                                                    <small class="text-muted">Google's AI language model</small>
                                                </div>
                                                <div class="ms-auto">
                                                    <span class="status-indicator" id="gemini-status"></span>
                                                </div>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label class="form-label fw-semibold">API Key</label>
                                                <div class="input-group">
                                                    <input type="password" class="form-control" name="gemini_api_key" 
                                                           id="gemini_api_key" placeholder="Enter your Gemini API key"
                                                           value="<?php echo htmlspecialchars($settings['gemini_api_key'] ?? ''); ?>">
                                                    <button class="btn btn-outline-secondary password-toggle" type="button" 
                                                            onclick="togglePassword('gemini_api_key')">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                </div>
                                            </div>
                                            
                                            <div class="d-flex gap-2">
                                                <button type="button" class="btn btn-test btn-sm flex-fill" 
                                                        onclick="testAPI('gemini', 'gemini_api_key')">
                                                    <i class="fas fa-plug me-1"></i>Test Connection
                                                </button>
                                                <a href="https://makersuite.google.com/app/apikey" target="_blank" 
                                                   class="btn btn-outline-primary btn-sm">
                                                    <i class="fas fa-external-link-alt me-1"></i>Get Key
                                                </a>
                                            </div>
                                            
                                            <div id="gemini-test-result" class="mt-2"></div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- OpenAI ChatGPT -->
                                <div class="col-lg-6 mb-4">
                                    <div class="card h-100 border-0 shadow-sm">
                                        <div class="card-body">
                                            <div class="d-flex align-items-center mb-3">
                                                <div style="width: 40px; height: 40px; background: linear-gradient(135deg, #10a37f, #1a7f64); border-radius: 8px; display: flex; align-items: center; justify-content: center; margin-right: 12px;">
                                                    <i class="fas fa-comments text-white"></i>
                                                </div>
                                                <div>
                                                    <h5 class="fw-bold mb-1">OpenAI ChatGPT</h5>
                                                    <small class="text-muted">OpenAI's GPT language model</small>
                                                </div>
                                                <div class="ms-auto">
                                                    <span class="status-indicator" id="chatgpt-status"></span>
                                                </div>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label class="form-label fw-semibold">API Key</label>
                                                <div class="input-group">
                                                    <input type="password" class="form-control" name="chatgpt_api_key" 
                                                           id="chatgpt_api_key" placeholder="Enter your OpenAI API key"
                                                           value="<?php echo htmlspecialchars($settings['chatgpt_api_key'] ?? ''); ?>">
                                                    <button class="btn btn-outline-secondary password-toggle" type="button" 
                                                            onclick="togglePassword('chatgpt_api_key')">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                </div>
                                            </div>
                                            
                                            <div class="d-flex gap-2">
                                                <button type="button" class="btn btn-test btn-sm flex-fill" 
                                                        onclick="testAPI('chatgpt', 'chatgpt_api_key')">
                                                    <i class="fas fa-plug me-1"></i>Test Connection
                                                </button>
                                                <a href="https://platform.openai.com/api-keys" target="_blank" 
                                                   class="btn btn-outline-primary btn-sm">
                                                    <i class="fas fa-external-link-alt me-1"></i>Get Key
                                                </a>
                                            </div>
                                            
                                            <div id="chatgpt-test-result" class="mt-2"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- LinkedIn OAuth Section -->
                    <div class="api-section">
                        <div class="api-section-header">
                            <div class="d-flex align-items-center">
                                <div class="api-icon" style="background: linear-gradient(135deg, #0077b5, #005885);">
                                    <i class="fab fa-linkedin"></i>
                                </div>
                                <div>
                                    <h4 class="fw-bold mb-1">LinkedIn OAuth Integration</h4>
                                    <p class="text-muted mb-0">Configure LinkedIn API for posting automation</p>
                                </div>
                            </div>
                        </div>
                        <div class="api-section-body">
                            <div class="row">
                                <div class="col-lg-6 mb-3">
                                    <label class="form-label fw-semibold">Client ID</label>
                                    <input type="text" class="form-control" name="linkedin_client_id" 
                                           placeholder="Enter LinkedIn Client ID"
                                           value="<?php echo htmlspecialchars($settings['linkedin_client_id'] ?? ''); ?>">
                                </div>
                                <div class="col-lg-6 mb-3">
                                    <label class="form-label fw-semibold">Client Secret</label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" name="linkedin_client_secret" 
                                               id="linkedin_client_secret" placeholder="Enter LinkedIn Client Secret"
                                               value="<?php echo htmlspecialchars($settings['linkedin_client_secret'] ?? ''); ?>">
                                        <button class="btn btn-outline-secondary password-toggle" type="button" 
                                                onclick="togglePassword('linkedin_client_secret')">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>Setup Instructions:</strong> Create a LinkedIn app at 
                                <a href="https://developer.linkedin.com/my-apps" target="_blank" class="text-decoration-none">
                                    LinkedIn Developers <i class="fas fa-external-link-alt"></i>
                                </a> and configure OAuth redirect URIs.
                            </div>
                        </div>
                    </div>

                    <!-- Google OAuth Section -->
                    <div class="api-section">
                        <div class="api-section-header">
                            <div class="d-flex align-items-center">
                                <div class="api-icon" style="background: linear-gradient(135deg, #4285F4, #DB4437, #F4B400, #0F9D58);">
                                    <i class="fab fa-google"></i>
                                </div>
                                <div>
                                    <h4 class="fw-bold mb-1">Google OAuth Integration</h4>
                                    <p class="text-muted mb-0">Configure Google API for authentication & services</p>
                                </div>
                            </div>
                        </div>
                        <div class="api-section-body">
                            <div class="row">
                                <div class="col-lg-6 mb-3">
                                    <label class="form-label fw-semibold">Client ID</label>
                                    <input type="text" class="form-control" name="google_client_id" 
                                           placeholder="Enter Google Client ID"
                                           value="<?php echo htmlspecialchars($settings['google_client_id'] ?? ''); ?>">
                                </div>
                                <div class="col-lg-6 mb-3">
                                    <label class="form-label fw-semibold">Client Secret</label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" name="google_client_secret" 
                                               id="google_client_secret" placeholder="Enter Google Client Secret"
                                               value="<?php echo htmlspecialchars($settings['google_client_secret'] ?? ''); ?>">
                                        <button class="btn btn-outline-secondary password-toggle" type="button" 
                                                onclick="togglePassword('google_client_secret')">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>Setup Instructions:</strong> Create a project at 
                                <a href="https://console.developers.google.com/" target="_blank" class="text-decoration-none">
                                    Google Cloud Console <i class="fas fa-external-link-alt"></i>
                                </a> and configure OAuth 2.0 credentials.
                            </div>
                        </div>
                    </div>
                    
                    <!-- Payment Integration Section -->
                    <div class="api-section">
                        <div class="api-section-header">
                            <div class="d-flex align-items-center">
                                <div class="api-icon" style="background: linear-gradient(135deg, #3395ff, #1976d2);">
                                    <i class="fas fa-credit-card"></i>
                                </div>
                                <div>
                                    <h4 class="fw-bold mb-1">Payment Integration</h4>
                                    <p class="text-muted mb-0">Configure payment gateway for subscription billing</p>
                                </div>
                            </div>
                        </div>
                        <div class="api-section-body">
                            <div class="row">
                                <div class="col-lg-6 mb-3">
                                    <label class="form-label fw-semibold">Razorpay Key ID</label>
                                    <input type="text" class="form-control" name="razorpay_key_id" 
                                           placeholder="Enter Razorpay Key ID"
                                           value="<?php echo htmlspecialchars($settings['razorpay_key_id'] ?? ''); ?>">
                                </div>
                                <div class="col-lg-6 mb-3">
                                    <label class="form-label fw-semibold">Razorpay Key Secret</label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" name="razorpay_key_secret" 
                                               id="razorpay_key_secret" placeholder="Enter Razorpay Key Secret"
                                               value="<?php echo htmlspecialchars($settings['razorpay_key_secret'] ?? ''); ?>">
                                        <button class="btn btn-outline-secondary password-toggle" type="button" 
                                                onclick="togglePassword('razorpay_key_secret')">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Webhook Secret</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" name="webhook_secret" 
                                           id="webhook_secret" placeholder="Enter webhook secret for payment verification"
                                           value="<?php echo htmlspecialchars($settings['webhook_secret'] ?? ''); ?>">
                                    <button class="btn btn-outline-secondary password-toggle" type="button" 
                                            onclick="togglePassword('webhook_secret')">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <strong>Important:</strong> Configure webhook endpoints in your Razorpay dashboard to handle payment notifications.
                            </div>
                        </div>
                    </div>
                    
                    <!-- Email Configuration Section -->
                    <div class="api-section">
                        <div class="api-section-header">
                            <div class="d-flex align-items-center">
                                <div class="api-icon" style="background: linear-gradient(135deg, #f59e0b, #d97706);">
                                    <i class="fas fa-envelope"></i>
                                </div>
                                <div>
                                    <h4 class="fw-bold mb-1">Email Configuration (SMTP)</h4>
                                    <p class="text-muted mb-0">Configure SMTP settings for automated emails</p>
                                </div>
                            </div>
                        </div>
                        <div class="api-section-body">
                            <div class="row">
                                <div class="col-lg-6 mb-3">
                                    <label class="form-label fw-semibold">SMTP Host</label>
                                    <input type="text" class="form-control" name="smtp_host" 
                                           placeholder="smtp.gmail.com"
                                           value="<?php echo htmlspecialchars($settings['smtp_host'] ?? ''); ?>">
                                </div>
                                <div class="col-lg-6 mb-3">
                                    <label class="form-label fw-semibold">SMTP Port</label>
                                    <select class="form-select" name="smtp_port">
                                        <option value="587" <?php echo ($settings['smtp_port'] ?? '') == '587' ? 'selected' : ''; ?>>587 (TLS)</option>
                                        <option value="465" <?php echo ($settings['smtp_port'] ?? '') == '465' ? 'selected' : ''; ?>>465 (SSL)</option>
                                        <option value="25" <?php echo ($settings['smtp_port'] ?? '') == '25' ? 'selected' : ''; ?>>25 (Non-encrypted)</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-lg-6 mb-3">
                                    <label class="form-label fw-semibold">SMTP Username</label>
                                    <input type="email" class="form-control" name="smtp_username" 
                                           placeholder="your-email@gmail.com"
                                           value="<?php echo htmlspecialchars($settings['smtp_username'] ?? ''); ?>">
                                </div>
                                <div class="col-lg-6 mb-3">
                                    <label class="form-label fw-semibold">SMTP Password</label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" name="smtp_password" 
                                               id="smtp_password" placeholder="Enter SMTP password"
                                               value="<?php echo htmlspecialchars($settings['smtp_password'] ?? ''); ?>">
                                        <button class="btn btn-outline-secondary password-toggle" type="button" 
                                                onclick="togglePassword('smtp_password')">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-test btn-sm" onclick="testSMTP()">
                                    <i class="fas fa-envelope me-1"></i>Test Email
                                </button>
                                <div class="alert alert-info flex-fill mb-0">
                                    <i class="fas fa-info-circle me-2"></i>
                                    <small>For Gmail, use App Password instead of regular password. Enable 2FA first.</small>
                                </div>
                            </div>
                            
                            <div id="smtp-test-result" class="mt-2"></div>
                        </div>
                    </div>
                    
                    <!-- Save Button -->
                    <div class="text-center">
                        <button type="submit" class="btn btn-primary btn-lg px-5">
                            <i class="fas fa-save me-2"></i>Save All Settings
                        </button>
                    </div>
                </form>
                
                <!-- Setup Guide -->
                <div class="guide-card">
                    <div class="row">
                        <div class="col-lg-6">
                            <h4 class="fw-bold mb-4">
                                <i class="fas fa-graduation-cap me-2"></i>Quick Setup Guide
                            </h4>
                            
                            <div class="guide-step">
                                <h6 class="fw-bold mb-2">1. AI Services Setup</h6>
                                <ul class="small mb-0">
                                    <li>Get Google Gemini API key from <a href="https://makersuite.google.com/app/apikey" target="_blank" class="text-white">Google AI Studio</a></li>
                                    <li>Create OpenAI account and generate API key from <a href="https://platform.openai.com/api-keys" target="_blank" class="text-white">OpenAI Platform</a></li>
                                    <li>Test both connections to ensure they work</li>
                                </ul>
                            </div>
                            
                            <div class="guide-step">
                                <h6 class="fw-bold mb-2">2. LinkedIn Integration</h6>
                                <ul class="small mb-0">
                                    <li>Create LinkedIn app at <a href="https://developer.linkedin.com/my-apps" target="_blank" class="text-white">LinkedIn Developer Console</a></li>
                                    <li>Add OAuth redirect URIs for your domain</li>
                                    <li>Request necessary permissions for posting</li>
                                </ul>
                            </div>
                            
                            <div class="guide-step">
                                <h6 class="fw-bold mb-2">3. Payment Gateway</h6>
                                <ul class="small mb-0">
                                    <li>Sign up for Razorpay account</li>
                                    <li>Generate API keys from dashboard</li>
                                    <li>Configure webhook endpoints for payment notifications</li>
                                </ul>
                            </div>
                        </div>
                        
                        <div class="col-lg-6">
                            <h4 class="fw-bold mb-4">
                                <i class="fas fa-shield-alt me-2"></i>Security Best Practices
                            </h4>
                            
                            <div class="guide-step">
                                <h6 class="fw-bold mb-2">API Key Security</h6>
                                <ul class="small mb-0">
                                    <li>Never share API keys publicly</li>
                                    <li>Rotate keys regularly</li>
                                    <li>Use environment variables in production</li>
                                    <li>Monitor API usage for anomalies</li>
                                </ul>
                            </div>
                            
                            <div class="guide-step">
                                <h6 class="fw-bold mb-2">Access Control</h6>
                                <ul class="small mb-0">
                                    <li>Limit API permissions to minimum required</li>
                                    <li>Use webhook secrets for verification</li>
                                    <li>Enable rate limiting where possible</li>
                                    <li>Log all API calls for auditing</li>
                                </ul>
                            </div>
                            
                            <div class="guide-step">
                                <h6 class="fw-bold mb-2">Backup & Recovery</h6>
                                <ul class="small mb-0">
                                    <li>Keep secure backup of all credentials</li>
                                    <li>Test backup restoration process</li>
                                    <li>Have fallback APIs configured</li>
                                    <li>Monitor service health regularly</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle password visibility
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const icon = event.target.closest('button').querySelector('i');
            
            if (field.type === 'password') {
                field.type = 'text';
                icon.className = 'fas fa-eye-slash';
            } else {
                field.type = 'password';
                icon.className = 'fas fa-eye';
            }
        }
        
        // Test API connection
        async function testAPI(provider, keyFieldId) {
            const apiKey = document.getElementById(keyFieldId).value;
            const statusIndicator = document.getElementById(`${provider}-status`);
            const resultDiv = document.getElementById(`${provider}-test-result`);
            
            if (!apiKey.trim()) {
                showTestResult(resultDiv, false, 'Please enter an API key first');
                return;
            }
            
            // Show testing state
            statusIndicator.className = 'status-indicator status-testing';
            showTestResult(resultDiv, null, 'Testing connection...', 'info');
            
            try {
                const formData = new FormData();
                formData.append('test_api', provider);
                formData.append('api_key', apiKey);
                
                const response = await fetch('', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                // Update status indicator
                statusIndicator.className = `status-indicator ${result.status ? 'status-success' : 'status-error'}`;
                
                // Show result
                showTestResult(resultDiv, result.status, result.message);
                
            } catch (error) {
                statusIndicator.className = 'status-indicator status-error';
                showTestResult(resultDiv, false, 'Connection test failed: ' + error.message);
            }
        }
        
        // Test SMTP connection
        async function testSMTP() {
            const host = document.querySelector('input[name="smtp_host"]').value;
            const username = document.querySelector('input[name="smtp_username"]').value;
            const password = document.querySelector('input[name="smtp_password"]').value;
            const resultDiv = document.getElementById('smtp-test-result');
            
            if (!host || !username || !password) {
                showTestResult(resultDiv, false, 'Please fill in all SMTP fields first');
                return;
            }
            
            showTestResult(resultDiv, null, 'Testing SMTP connection...', 'info');
            
            try {
                const formData = new FormData();
                formData.append('test_smtp', '1');
                formData.append('smtp_host', host);
                formData.append('smtp_username', username);
                formData.append('smtp_password', password);
                formData.append('smtp_port', document.querySelector('select[name="smtp_port"]').value);
                
                const response = await fetch('test-smtp.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                showTestResult(resultDiv, result.status, result.message);
                
            } catch (error) {
                showTestResult(resultDiv, false, 'SMTP test failed: ' + error.message);
            }
        }
        
        // Show test result
        function showTestResult(container, success, message, type = null) {
            let alertClass = 'alert-info';
            let icon = 'fas fa-info-circle';
            
            if (type === null) {
                if (success === true) {
                    alertClass = 'alert-success';
                    icon = 'fas fa-check-circle';
                } else if (success === false) {
                    alertClass = 'alert-danger';
                    icon = 'fas fa-exclamation-circle';
                }
            } else if (type === 'info') {
                alertClass = 'alert-info';
                icon = 'fas fa-info-circle';
            }
            
            container.innerHTML = `
                <div class="alert ${alertClass} alert-dismissible fade show">
                    <i class="${icon} me-2"></i>${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `;
        }
        
        // Initialize status indicators on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Set initial status based on whether keys exist
            const geminiKey = document.getElementById('gemini_api_key').value;
            const chatgptKey = document.getElementById('chatgpt_api_key').value;
            
            document.getElementById('gemini-status').className = 
                `status-indicator ${geminiKey ? 'status-unknown' : 'status-error'}`;
            document.getElementById('chatgpt-status').className = 
                `status-indicator ${chatgptKey ? 'status-unknown' : 'status-error'}`;
            
            // Auto-save form data to localStorage (for user convenience)
            const form = document.getElementById('apiSettingsForm');
            const inputs = form.querySelectorAll('input, select, textarea');
            
            inputs.forEach(input => {
                input.addEventListener('change', function() {
                    if (this.type !== 'password') {
                        localStorage.setItem('api_settings_' + this.name, this.value);
                    }
                });
                
                // Restore saved values (except passwords)
                if (input.type !== 'password') {
                    const saved = localStorage.getItem('api_settings_' + input.name);
                    if (saved && !input.value) {
                        input.value = saved;
                    }
                }
            });
        });
        
        // Form validation
        document.getElementById('apiSettingsForm').addEventListener('submit', function(e) {
            const geminiKey = document.querySelector('[name="gemini_api_key"]').value.trim();
            const chatgptKey = document.querySelector('[name="chatgpt_api_key"]').value.trim();
            
            if (!geminiKey && !chatgptKey) {
                e.preventDefault();
                document.querySelector('[name="gemini_api_key"]').classList.add('is-invalid');
                document.querySelector('[name="chatgpt_api_key"]').classList.add('is-invalid');
                alert('Please fill in at least one AI service API key before saving.');
            } else {
                document.querySelector('[name="gemini_api_key"]').classList.remove('is-invalid');
                document.querySelector('[name="chatgpt_api_key"]').classList.remove('is-invalid');
            }
        });
        
        // Auto-hide alerts after 5 seconds
        setTimeout(() => {
            document.querySelectorAll('.alert').forEach(alert => {
                if (alert.querySelector('.btn-close')) {
                    alert.classList.remove('show');
                }
            });
        }, 5000);
    </script>
</body>
</html>