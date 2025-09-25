<?php
// admin/create-automation.php
session_start();
require_once '../config/database-config.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}

$message = '';
$error = '';
$customers = [];
$templates = [];

// Get customers and templates for dropdowns
try {
    $stmt = $db->prepare("SELECT id, name, email, subscription_status FROM customers WHERE subscription_status IN ('trial', 'active') ORDER BY name");
    $stmt->execute();
    $customers = $stmt->fetchAll();
    
    $stmt = $db->prepare("SELECT id, name, description, template_content, category, content_style FROM content_templates WHERE is_active = 1 ORDER BY name");
    $stmt->execute();
    $templates = $stmt->fetchAll();
    
} catch (Exception $e) {
    error_log("Get customers/templates error: " . $e->getMessage());
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customerId = (int)$_POST['customer_id'];
    $name = trim($_POST['name']);
    $topic = trim($_POST['topic']);
    $aiProvider = $_POST['ai_provider'];
    $postTime = $_POST['post_time'];
    $startDate = $_POST['start_date'];
    $endDate = $_POST['end_date'];
    $daysOfWeek = implode(',', $_POST['days_of_week'] ?? []);
    $contentTemplate = trim($_POST['content_template']);
    $hashtags = trim($_POST['hashtags']);
    $postFrequency = $_POST['post_frequency'] ?? 'daily';
    $contentStyle = $_POST['content_style'] ?? 'professional';
    $includeImages = isset($_POST['include_images']) ? 1 : 0;
    $autoHashtags = isset($_POST['auto_hashtags']) ? 1 : 0;
    
    // Validation
    if (empty($customerId) || empty($name) || empty($topic) || empty($startDate) || empty($endDate)) {
        $error = 'Please fill in all required fields.';
    } elseif (strtotime($endDate) <= strtotime($startDate)) {
        $error = 'End date must be after start date.';
    } elseif (empty($daysOfWeek) && $postFrequency !== 'custom') {
        $error = 'Please select at least one day of the week.';
    } else {
        try {
            // Insert automation
            $stmt = $db->prepare("
                INSERT INTO customer_automations (
                    customer_id, name, topic, ai_provider, post_time, start_date, end_date, 
                    days_of_week, content_template, hashtags, post_frequency, content_style,
                    include_images, auto_hashtags, status, include_images, auto_hashtags, status, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW())
            ");
            
            $success = $stmt->execute([
                $customerId, $name, $topic, $aiProvider, $postTime, $startDate, $endDate,
                $daysOfWeek, $contentTemplate, $hashtags, $postFrequency, $contentStyle,
                $includeImages, $autoHashtags
            ]);
            
            if ($success) {
                $automationId = $db->lastInsertId();
                
                // Generate initial posts for the next 5 days
                generateInitialPosts($automationId, $db);
                
                $message = 'Automation created successfully! Initial posts have been generated.';
                
                // Log activity
                $stmt = $db->prepare("INSERT INTO admin_activity_log (admin_id, action, description, created_at) VALUES (?, 'create_automation', ?, NOW())");
                $stmt->execute([$_SESSION['admin_id'], "Created automation '{$name}' for customer ID {$customerId}"]);
                
                // Clear form data
                $_POST = [];
                
            } else {
                $error = 'Failed to create automation. Please try again.';
            }
            
        } catch (Exception $e) {
            error_log("Create automation error: " . $e->getMessage());
            $error = 'An error occurred while creating the automation.';
        }
    }
}

// Function to generate initial posts
function generateInitialPosts($automationId, $db) {
    try {
        // Get automation details
        $stmt = $db->prepare("
            SELECT ca.*, c.name as customer_name 
            FROM customer_automations ca 
            JOIN customers c ON ca.customer_id = c.id 
            WHERE ca.id = ?
        ");
        $stmt->execute([$automationId]);
        $automation = $stmt->fetch();
        
        if (!$automation) return;
        
        $daysOfWeek = explode(',', $automation['days_of_week']);
        $startDate = new DateTime($automation['start_date']);
        $endDate = new DateTime($automation['end_date']);
        $postTime = $automation['post_time'];
        
        // Generate posts for next 5 days or until end date, whichever is earlier
        $currentDate = new DateTime();
        $maxDate = min($endDate, (new DateTime())->add(new DateInterval('P5D')));
        
        $postsGenerated = 0;
        while ($currentDate <= $maxDate && $postsGenerated < 10) { // Limit to 10 initial posts
            $dayOfWeek = $currentDate->format('N'); // 1=Monday, 7=Sunday
            
            if (in_array($dayOfWeek, $daysOfWeek)) {
                $scheduledTime = $currentDate->format('Y-m-d') . ' ' . $postTime;
                
                // Generate AI content (placeholder for now)
                $content = generateAIContent($automation['topic'], $automation['ai_provider'], $automation['content_template']);
                
                if ($automation['hashtags']) {
                    $content .= "\n\n" . $automation['hashtags'];
                }
                
                // Insert scheduled post
                $stmt = $db->prepare("
                    INSERT INTO scheduled_posts (
                        automation_id, customer_id, content, scheduled_time, 
                        ai_model_used, status, created_at
                    ) VALUES (?, ?, ?, ?, ?, 'pending', NOW())
                ");
                $stmt->execute([
                    $automationId, 
                    $automation['customer_id'], 
                    $content, 
                    $scheduledTime,
                    $automation['ai_provider']
                ]);
                
                $postsGenerated++;
            }
            
            $currentDate->add(new DateInterval('P1D'));
        }
        
    } catch (Exception $e) {
        error_log("Generate initial posts error: " . $e->getMessage());
    }
}

// Placeholder function for AI content generation
function generateAIContent($topic, $provider, $template = '') {
    // This would integrate with your actual AI APIs
    $sampleContent = "🚀 Excited to share insights about " . $topic . "!\n\n";
    $sampleContent .= "In today's rapidly evolving landscape, understanding " . $topic . " is crucial for success.\n\n";
    $sampleContent .= "Key takeaways:\n";
    $sampleContent .= "✅ Stay informed about industry trends\n";
    $sampleContent .= "✅ Embrace continuous learning\n";
    $sampleContent .= "✅ Network with like-minded professionals\n\n";
    $sampleContent .= "What's your experience with " . $topic . "? I'd love to hear your thoughts! 💭\n\n";
    $sampleContent .= "#" . str_replace(' ', '', ucwords($topic)) . " #LinkedIn #Professional";
    
    return $sampleContent;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Automation - LinkedIn Admin</title>
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
        
        .form-section {
            background: white;
            border-radius: 1rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            border: 1px solid #e2e8f0;
            margin-bottom: 2rem;
            overflow: hidden;
        }
        
        .form-section-header {
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            padding: 1.5rem 2rem;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .form-section-body {
            padding: 2rem;
        }
        
        .section-icon {
            width: 50px;
            height: 50px;
            border-radius: 0.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            color: white;
            margin-right: 1rem;
        }
        
        .form-control, .form-select, .form-check-input {
            border-radius: 0.5rem;
            border: 1px solid #d1d5db;
            transition: all 0.3s ease;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        .form-label {
            font-weight: 500;
            color: #374151;
            margin-bottom: 0.5rem;
        }
        
        .required {
            color: #ef4444;
        }
        
        .day-selector {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            margin-top: 0.5rem;
        }
        
        .day-checkbox {
            display: none;
        }
        
        .day-label {
            padding: 0.75rem 1rem;
            border: 2px solid #e2e8f0;
            border-radius: 0.5rem;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 500;
            text-align: center;
            min-width: 60px;
            user-select: none;
        }
        
        .day-checkbox:checked + .day-label {
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            border-color: #3b82f6;
            color: white;
        }
        
        .day-label:hover {
            border-color: #3b82f6;
            transform: translateY(-1px);
        }
        
        .ai-provider-card {
            border: 2px solid #e2e8f0;
            border-radius: 0.75rem;
            padding: 1.5rem;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
        }
        
        .ai-provider-card:hover {
            border-color: #3b82f6;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
        }
        
        .ai-provider-card.selected {
            border-color: #3b82f6;
            background: linear-gradient(135deg, #f0f7ff, #e6f3ff);
        }
        
        .ai-provider-icon {
            width: 60px;
            height: 60px;
            border-radius: 0.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
            margin: 0 auto 1rem;
        }
        
        .template-preview {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 0.5rem;
            padding: 1rem;
            margin-top: 0.5rem;
            font-size: 0.875rem;
            color: #64748b;
            display: none;
        }
        
        .btn-create {
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            border: none;
            padding: 1rem 3rem;
            border-radius: 0.75rem;
            color: white;
            font-weight: 600;
            font-size: 1.1rem;
            transition: all 0.3s ease;
        }
        
        .btn-create:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(59, 130, 246, 0.4);
            color: white;
        }
        
        .progress-indicator {
            display: flex;
            justify-content: space-between;
            margin-bottom: 2rem;
            position: relative;
        }
        
        .progress-step {
            flex: 1;
            text-align: center;
            position: relative;
            z-index: 2;
        }
        
        .step-circle {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 0.5rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .step-circle.active {
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            color: white;
        }
        
        .step-circle.completed {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
        }
        
        .progress-line {
            position: absolute;
            top: 20px;
            left: 0;
            right: 0;
            height: 2px;
            background: #e2e8f0;
            z-index: 1;
        }
        
        .preview-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 1rem;
            padding: 2rem;
            margin-top: 2rem;
        }
        
        .hashtag-input {
            position: relative;
        }
        
        .hashtag-suggestions {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #d1d5db;
            border-top: none;
            border-radius: 0 0 0.5rem 0.5rem;
            max-height: 200px;
            overflow-y: auto;
            z-index: 1000;
            display: none;
        }
        
        .hashtag-suggestion {
            padding: 0.5rem 1rem;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        
        .hashtag-suggestion:hover {
            background-color: #f8fafc;
        }
        
        @media (max-width: 768px) {
            .main-content {
                padding: 1rem;
            }
            
            .form-section-header, .form-section-body {
                padding: 1.5rem;
            }
            
            .day-selector {
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar (same as other pages) -->
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
                            <a href="automations.php" class="nav-link active">
                                <i class="fas fa-robot"></i>Automations
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="posts.php" class="nav-link">
                                <i class="fas fa-newspaper"></i>Generated Posts
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="api-settings.php" class="nav-link">
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
                            <i class="fas fa-plus-circle me-3 text-primary"></i>Create New Automation
                        </h1>
                        <p class="text-muted mb-0">Set up automated LinkedIn posting for your customers</p>
                    </div>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <a href="automations.php" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left me-2"></i>Back to Automations
                            </a>
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
                
                <!-- Progress Indicator -->
                <div class="progress-indicator">
                    <div class="progress-line"></div>
                    <div class="progress-step">
                        <div class="step-circle active" id="step1">1</div>
                        <div class="small">Basic Info</div>
                    </div>
                    <div class="progress-step">
                        <div class="step-circle" id="step2">2</div>
                        <div class="small">AI & Content</div>
                    </div>
                    <div class="progress-step">
                        <div class="step-circle" id="step3">3</div>
                        <div class="small">Schedule</div>
                    </div>
                    <div class="progress-step">
                        <div class="step-circle" id="step4">4</div>
                        <div class="small">Review & Create</div>
                    </div>
                </div>
                
                <form method="POST" id="automationForm" novalidate>
                    <!-- Step 1: Basic Information -->
                    <div class="form-section" id="section1">
                        <div class="form-section-header">
                            <div class="d-flex align-items-center">
                                <div class="section-icon" style="background: linear-gradient(135deg, #3b82f6, #1d4ed8);">
                                    <i class="fas fa-info-circle"></i>
                                </div>
                                <div>
                                    <h4 class="fw-bold mb-1">Basic Information</h4>
                                    <p class="text-muted mb-0">Set up the fundamental details of your automation</p>
                                </div>
                            </div>
                        </div>
                        <div class="form-section-body">
                            <div class="row">
                                <div class="col-lg-6 mb-4">
                                    <label class="form-label">Customer <span class="required">*</span></label>
                                    <select class="form-select" name="customer_id" required>
                                        <option value="">Select a customer</option>
                                        <?php foreach ($customers as $customer): ?>
                                            <option value="<?php echo $customer['id']; ?>" 
                                                    <?php echo (isset($_POST['customer_id']) && $_POST['customer_id'] == $customer['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($customer['name']); ?> 
                                                (<?php echo htmlspecialchars($customer['email']); ?>)
                                                - <?php echo ucfirst($customer['subscription_status']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="form-text">Choose the customer for whom this automation will run</div>
                                </div>
                                
                                <div class="col-lg-6 mb-4">
                                    <label class="form-label">Automation Name <span class="required">*</span></label>
                                    <input type="text" class="form-control" name="name" 
                                           placeholder="e.g., Tech Insights Weekly"
                                           value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" required>
                                    <div class="form-text">Give your automation a descriptive name</div>
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <label class="form-label">Content Topic <span class="required">*</span></label>
                                <textarea class="form-control" name="topic" rows="3" 
                                          placeholder="Describe the main topic or theme for the posts. Be specific about the industry, expertise area, or subject matter you want to focus on."
                                          required><?php echo htmlspecialchars($_POST['topic'] ?? ''); ?></textarea>
                                <div class="form-text">This topic will guide the AI in generating relevant content. The more specific you are, the better the results.</div>
                            </div>
                            
                            <div class="row">
                                <div class="col-lg-6 mb-4">
                                    <label class="form-label">Content Style</label>
                                    <select class="form-select" name="content_style">
                                        <option value="professional" <?php echo (isset($_POST['content_style']) && $_POST['content_style'] == 'professional') ? 'selected' : ''; ?>>Professional</option>
                                        <option value="casual" <?php echo (isset($_POST['content_style']) && $_POST['content_style'] == 'casual') ? 'selected' : ''; ?>>Casual</option>
                                        <option value="educational" <?php echo (isset($_POST['content_style']) && $_POST['content_style'] == 'educational') ? 'selected' : ''; ?>>Educational</option>
                                        <option value="motivational" <?php echo (isset($_POST['content_style']) && $_POST['content_style'] == 'motivational') ? 'selected' : ''; ?>>Motivational</option>
                                        <option value="storytelling" <?php echo (isset($_POST['content_style']) && $_POST['content_style'] == 'storytelling') ? 'selected' : ''; ?>>Storytelling</option>
                                    </select>
                                </div>
                                
                                <div class="col-lg-6 mb-4">
                                    <label class="form-label">Advanced Options</label>
                                    <div class="mt-2">
                                        <div class="form-check mb-2">
                                            <input class="form-check-input" type="checkbox" name="auto_hashtags" id="autoHashtags" 
                                                   <?php echo (isset($_POST['auto_hashtags'])) ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="autoHashtags">
                                                Auto-generate hashtags
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="include_images" id="includeImages" 
                                                   <?php echo (isset($_POST['include_images'])) ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="includeImages">
                                                Include image suggestions
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Step 2: AI Provider & Content Template -->
                    <div class="form-section" id="section2" style="display: none;">
                        <div class="form-section-header">
                            <div class="d-flex align-items-center">
                                <div class="section-icon" style="background: linear-gradient(135deg, #10b981, #059669);">
                                    <i class="fas fa-robot"></i>
                                </div>
                                <div>
                                    <h4 class="fw-bold mb-1">AI Provider & Content Settings</h4>
                                    <p class="text-muted mb-0">Choose your AI provider and content template</p>
                                </div>
                            </div>
                        </div>
                        <div class="form-section-body">
                            <div class="mb-4">
                                <label class="form-label">AI Provider <span class="required">*</span></label>
                                <div class="row mt-3">
                                    <div class="col-md-6 mb-3">
                                        <div class="ai-provider-card" onclick="selectAI('gemini')">
                                            <input type="radio" name="ai_provider" value="gemini" id="ai_gemini" 
                                                   style="display: none;" <?php echo (isset($_POST['ai_provider']) && $_POST['ai_provider'] == 'gemini') ? 'checked' : ''; ?>>
                                            <div class="ai-provider-icon" style="background: linear-gradient(135deg, #4285f4, #34a853);">
                                                <i class="fas fa-brain"></i>
                                            </div>
                                            <h5 class="fw-bold text-center mb-2">Google Gemini</h5>
                                            <p class="text-center text-muted mb-0">Advanced AI with excellent reasoning capabilities</p>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <div class="ai-provider-card" onclick="selectAI('chatgpt')">
                                            <input type="radio" name="ai_provider" value="chatgpt" id="ai_chatgpt" 
                                                   style="display: none;" <?php echo (isset($_POST['ai_provider']) && $_POST['ai_provider'] == 'chatgpt') ? 'checked' : ''; ?>>
                                            <div class="ai-provider-icon" style="background: linear-gradient(135deg, #10a37f, #1a7f64);">
                                                <i class="fas fa-comments"></i>
                                            </div>
                                            <h5 class="fw-bold text-center mb-2">OpenAI ChatGPT</h5>
                                            <p class="text-center text-muted mb-0">Creative and conversational AI for engaging content</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <label class="form-label">Content Template (Optional)</label>
                                <select class="form-select" name="template_id" id="templateSelect">
                                    <option value="">Use default template</option>
                                    <?php foreach ($templates as $template): ?>
                                        <option value="<?php echo $template['id']; ?>" 
                                                data-content="<?php echo htmlspecialchars($template['template_content']); ?>"
                                                <?php echo (isset($_POST['template_id']) && $_POST['template_id'] == $template['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($template['name']); ?> 
                                            (<?php echo ucfirst($template['content_style']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="template-preview" id="templatePreview"></div>
                                <div class="form-text">Templates help guide the AI to generate content in a specific style</div>
                            </div>
                            
                            <div class="mb-4">
                                <label class="form-label">Custom Content Template</label>
                                <textarea class="form-control" name="content_template" rows="4" 
                                          placeholder="Enter a custom template or instructions for content generation. Use {topic} as placeholder for the automation topic."><?php echo htmlspecialchars($_POST['content_template'] ?? ''); ?></textarea>
                                <div class="form-text">This will override the selected template above. Use {topic} to insert the automation topic.</div>
                            </div>
                            
                            <div class="mb-4">
                                <label class="form-label">Hashtags</label>
                                <div class="hashtag-input">
                                    <input type="text" class="form-control" name="hashtags" id="hashtagInput"
                                           placeholder="#technology #AI #linkedin #professional"
                                           value="<?php echo htmlspecialchars($_POST['hashtags'] ?? ''); ?>">
                                    <div class="hashtag-suggestions" id="hashtagSuggestions"></div>
                                </div>
                                <div class="form-text">Add relevant hashtags to increase post visibility. Separate with spaces.</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Step 3: Schedule Settings -->
                    <div class="form-section" id="section3" style="display: none;">
                        <div class="form-section-header">
                            <div class="d-flex align-items-center">
                                <div class="section-icon" style="background: linear-gradient(135deg, #f59e0b, #d97706);">
                                    <i class="fas fa-calendar-alt"></i>
                                </div>
                                <div>
                                    <h4 class="fw-bold mb-1">Scheduling Settings</h4>
                                    <p class="text-muted mb-0">Define when and how often posts should be published</p>
                                </div>
                            </div>
                        </div>
                        <div class="form-section-body">
                            <div class="row">
                                <div class="col-lg-6 mb-4">
                                    <label class="form-label">Start Date <span class="required">*</span></label>
                                    <input type="date" class="form-control" name="start_date" 
                                           min="<?php echo date('Y-m-d'); ?>"
                                           value="<?php echo $_POST['start_date'] ?? date('Y-m-d'); ?>" required>
                                    <div class="form-text">When should the automation begin?</div>
                                </div>
                                
                                <div class="col-lg-6 mb-4">
                                    <label class="form-label">End Date <span class="required">*</span></label>
                                    <input type="date" class="form-control" name="end_date" 
                                           min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>"
                                           value="<?php echo $_POST['end_date'] ?? date('Y-m-d', strtotime('+30 days')); ?>" required>
                                    <div class="form-text">When should the automation end?</div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-lg-6 mb-4">
                                    <label class="form-label">Post Time <span class="required">*</span></label>
                                    <input type="time" class="form-control" name="post_time" 
                                           value="<?php echo $_POST['post_time'] ?? '09:00'; ?>" required>
                                    <div class="form-text">What time should posts be published? (24-hour format)</div>
                                </div>
                                
                                <div class="col-lg-6 mb-4">
                                    <label class="form-label">Frequency</label>
                                    <select class="form-select" name="post_frequency" id="frequencySelect">
                                        <option value="daily" <?php echo (isset($_POST['post_frequency']) && $_POST['post_frequency'] == 'daily') ? 'selected' : ''; ?>>Daily</option>
                                        <option value="weekdays" <?php echo (isset($_POST['post_frequency']) && $_POST['post_frequency'] == 'weekdays') ? 'selected' : ''; ?>>Weekdays Only</option>
                                        <option value="custom" <?php echo (isset($_POST['post_frequency']) && $_POST['post_frequency'] == 'custom') ? 'selected' : ''; ?>>Custom Schedule</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="mb-4" id="daySelector">
                                <label class="form-label">Days of Week <span class="required">*</span></label>
                                <div class="day-selector">
                                    <?php
                                    $days = [
                                        '1' => 'Mon',
                                        '2' => 'Tue', 
                                        '3' => 'Wed',
                                        '4' => 'Thu',
                                        '5' => 'Fri',
                                        '6' => 'Sat',
                                        '7' => 'Sun'
                                    ];
                                    $selectedDays = $_POST['days_of_week'] ?? ['1','2','3','4','5'];
                                    ?>
                                    <?php foreach ($days as $value => $label): ?>
                                        <input type="checkbox" name="days_of_week[]" value="<?php echo $value; ?>" 
                                               id="day<?php echo $value; ?>" class="day-checkbox"
                                               <?php echo in_array($value, $selectedDays) ? 'checked' : ''; ?>>
                                        <label for="day<?php echo $value; ?>" class="day-label"><?php echo $label; ?></label>
                                    <?php endforeach; ?>
                                </div>
                                <div class="form-text">Select which days of the week posts should be published</div>
                            </div>
                            
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>Tip:</strong> For best engagement, consider posting during business hours (9 AM - 5 PM) on weekdays. 
                                Tuesday to Thursday typically see the highest engagement rates on LinkedIn.
                            </div>
                        </div>
                    </div>
                    
                    <!-- Step 4: Review & Create -->
                    <div class="form-section" id="section4" style="display: none;">
                        <div class="form-section-header">
                            <div class="d-flex align-items-center">
                                <div class="section-icon" style="background: linear-gradient(135deg, #8b5cf6, #7c3aed);">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                                <div>
                                    <h4 class="fw-bold mb-1">Review & Create Automation</h4>
                                    <p class="text-muted mb-0">Review your settings and create the automation</p>
                                </div>
                            </div>
                        </div>
                        <div class="form-section-body">
                            <div class="row">
                                <div class="col-lg-8">
                                    <h5 class="fw-bold mb-3">Automation Summary</h5>
                                    
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <strong>Customer:</strong> <span id="reviewCustomer">-</span>
                                        </div>
                                        <div class="col-md-6">
                                            <strong>Name:</strong> <span id="reviewName">-</span>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <strong>Topic:</strong>
                                        <div class="text-muted" id="reviewTopic">-</div>
                                    </div>
                                    
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <strong>AI Provider:</strong> <span id="reviewAI">-</span>
                                        </div>
                                        <div class="col-md-6">
                                            <strong>Content Style:</strong> <span id="reviewStyle">-</span>
                                        </div>
                                    </div>
                                    
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <strong>Start Date:</strong> <span id="reviewStartDate">-</span>
                                        </div>
                                        <div class="col-md-6">
                                            <strong>End Date:</strong> <span id="reviewEndDate">-</span>
                                        </div>
                                    </div>
                                    
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <strong>Post Time:</strong> <span id="reviewTime">-</span>
                                        </div>
                                        <div class="col-md-6">
                                            <strong>Days:</strong> <span id="reviewDays">-</span>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <strong>Hashtags:</strong>
                                        <div class="text-muted" id="reviewHashtags">-</div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <strong>Estimated Posts:</strong>
                                        <div class="text-primary fw-bold" id="estimatedPosts">Calculating...</div>
                                        <small class="text-muted">Based on your schedule and date range</small>
                                    </div>
                                </div>
                                
                                <div class="col-lg-4">
                                    <div class="preview-card">
                                        <h5 class="fw-bold mb-3">
                                            <i class="fas fa-eye me-2"></i>Content Preview
                                        </h5>
                                        <div class="bg-white bg-opacity-10 rounded p-3 mb-3">
                                            <div class="small mb-2 opacity-75">Sample LinkedIn Post:</div>
                                            <div id="contentPreview" style="font-size: 0.9em; line-height: 1.5;">
                                                🚀 Excited to share insights about your topic!<br><br>
                                                In today's rapidly evolving landscape, understanding your expertise area is crucial for success.<br><br>
                                                Key takeaways:<br>
                                                ✅ Stay informed about industry trends<br>
                                                ✅ Embrace continuous learning<br>
                                                ✅ Network with like-minded professionals<br><br>
                                                What's your experience with this topic? I'd love to hear your thoughts! 💭<br><br>
                                                #YourTopic #LinkedIn #Professional
                                            </div>
                                        </div>
                                        <small class="opacity-75">
                                            <i class="fas fa-info-circle me-1"></i>
                                            This is a preview. Actual content will be generated by AI based on your settings.
                                        </small>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <strong>Important:</strong> Once created, the automation will start generating posts according to your schedule. 
                                Make sure all settings are correct before proceeding.
                            </div>
                        </div>
                    </div>
                    
                    <!-- Navigation Buttons -->
                    <div class="d-flex justify-content-between align-items-center mt-4">
                        <button type="button" class="btn btn-outline-secondary" id="prevBtn" style="display: none;">
                            <i class="fas fa-arrow-left me-2"></i>Previous
                        </button>
                        
                        <div class="ms-auto">
                            <button type="button" class="btn btn-primary" id="nextBtn">
                                Next <i class="fas fa-arrow-right ms-2"></i>
                            </button>
                            <button type="submit" class="btn-create" id="createBtn" style="display: none;">
                                <i class="fas fa-rocket me-2"></i>Create Automation
                            </button>
                        </div>
                    </div>
                </form>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let currentStep = 1;
        const totalSteps = 4;
        
        // Initialize form
        document.addEventListener('DOMContentLoaded', function() {
            updateStepVisibility();
            updateDaySelector();
            updateTemplatePreview();
            
            // Set default AI if none selected
            if (!document.querySelector('input[name="ai_provider"]:checked')) {
                document.getElementById('ai_gemini').checked = true;
                selectAI('gemini');
            }
        });
        
        // Step navigation
        document.getElementById('nextBtn').addEventListener('click', function() {
            if (validateCurrentStep()) {
                currentStep++;
                updateStepVisibility();
                updateReview();
            }
        });
        
        document.getElementById('prevBtn').addEventListener('click', function() {
            currentStep--;
            updateStepVisibility();
        });
        
        function updateStepVisibility() {
            // Hide all sections
            for (let i = 1; i <= totalSteps; i++) {
                document.getElementById(`section${i}`).style.display = 'none';
                document.getElementById(`step${i}`).classList.remove('active', 'completed');
            }
            
            // Show current section
            document.getElementById(`section${currentStep}`).style.display = 'block';
            document.getElementById(`step${currentStep}`).classList.add('active');
            
            // Mark completed steps
            for (let i = 1; i < currentStep; i++) {
                document.getElementById(`step${i}`).classList.add('completed');
            }
            
            // Update navigation buttons
            document.getElementById('prevBtn').style.display = currentStep > 1 ? 'block' : 'none';
            document.getElementById('nextBtn').style.display = currentStep < totalSteps ? 'block' : 'none';
            document.getElementById('createBtn').style.display = currentStep === totalSteps ? 'block' : 'none';
            
            // Scroll to top
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
        
        function validateCurrentStep() {
            const currentSection = document.getElementById(`section${currentStep}`);
            const requiredFields = currentSection.querySelectorAll('[required]');
            let isValid = true;
            
            requiredFields.forEach(field => {
                if (!field.value.trim() && field.type !== 'checkbox') {
                    field.classList.add('is-invalid');
                    isValid = false;
                } else if (field.type === 'radio') {
                    const radioGroup = currentSection.querySelectorAll(`[name="${field.name}"]`);
                    const hasChecked = Array.from(radioGroup).some(radio => radio.checked);
                    if (!hasChecked) {
                        isValid = false;
                        alert(`Please select ${field.name.replace('_', ' ')}`);
                    }
                } else {
                    field.classList.remove('is-invalid');
                }
            });
            
            // Special validation for days of week
            if (currentStep === 3) {
                const checkedDays = document.querySelectorAll('input[name="days_of_week[]"]:checked');
                if (checkedDays.length === 0 && document.getElementById('frequencySelect').value === 'custom') {
                    alert('Please select at least one day of the week');
                    isValid = false;
                }
            }
            
            return isValid;
        }
        
        // AI Provider Selection
        function selectAI(provider) {
            document.querySelectorAll('.ai-provider-card').forEach(card => {
                card.classList.remove('selected');
            });
            
            document.getElementById(`ai_${provider}`).checked = true;
            document.getElementById(`ai_${provider}`).closest('.ai-provider-card').classList.add('selected');
        }
        
        // Frequency selector logic
        document.getElementById('frequencySelect').addEventListener('change', function() {
            updateDaySelector();
        });
        
        function updateDaySelector() {
            const frequency = document.getElementById('frequencySelect').value;
            const dayCheckboxes = document.querySelectorAll('input[name="days_of_week[]"]');
            
            // Clear all selections first
            dayCheckboxes.forEach(checkbox => {
                checkbox.checked = false;
            });
            
            if (frequency === 'daily') {
                // Select all days
                dayCheckboxes.forEach(checkbox => {
                    checkbox.checked = true;
                });
                document.getElementById('daySelector').style.display = 'none';
            } else if (frequency === 'weekdays') {
                // Select Monday to Friday (1-5)
                for (let i = 1; i <= 5; i++) {
                    document.getElementById(`day${i}`).checked = true;
                }
                document.getElementById('daySelector').style.display = 'none';
            } else {
                // Custom - show day selector
                document.getElementById('daySelector').style.display = 'block';
            }
        }
        
        // Template preview
        document.getElementById('templateSelect').addEventListener('change', function() {
            updateTemplatePreview();
        });
        
        function updateTemplatePreview() {
            const select = document.getElementById('templateSelect');
            const preview = document.getElementById('templatePreview');
            
            if (select.value) {
                const selectedOption = select.options[select.selectedIndex];
                const content = selectedOption.getAttribute('data-content');
                preview.innerHTML = content;
                preview.style.display = 'block';
            } else {
                preview.style.display = 'none';
            }
        }
        
        // Hashtag suggestions
        const hashtagSuggestions = [
            '#LinkedIn', '#Professional', '#Business', '#Marketing', '#Technology', 
            '#AI', '#MachineLearning', '#Innovation', '#Leadership', '#Entrepreneurship',
            '#DigitalMarketing', '#SocialMedia', '#Networking', '#CareerDevelopment',
            '#TechTrends', '#Startup', '#Productivity', '#Success', '#Motivation'
        ];
        
        document.getElementById('hashtagInput').addEventListener('input', function() {
            const input = this.value.toLowerCase();
            const suggestions = document.getElementById('hashtagSuggestions');
            
            if (input.length > 1) {
                const matches = hashtagSuggestions.filter(tag => 
                    tag.toLowerCase().includes(input.replace('#', '')) && 
                    !this.value.includes(tag)
                ).slice(0, 5);
                
                if (matches.length > 0) {
                    suggestions.innerHTML = matches.map(tag => 
                        `<div class="hashtag-suggestion" onclick="addHashtag('${tag}')">${tag}</div>`
                    ).join('');
                    suggestions.style.display = 'block';
                } else {
                    suggestions.style.display = 'none';
                }
            } else {
                suggestions.style.display = 'none';
            }
        });
        
        function addHashtag(hashtag) {
            const input = document.getElementById('hashtagInput');
            const currentValue = input.value.trim();
            
            if (!currentValue.includes(hashtag)) {
                input.value = currentValue ? currentValue + ' ' + hashtag : hashtag;
            }
            
            document.getElementById('hashtagSuggestions').style.display = 'none';
        }
        
        // Hide hashtag suggestions when clicking outside
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.hashtag-input')) {
                document.getElementById('hashtagSuggestions').style.display = 'none';
            }
        });
        
        // Review section updates
        function updateReview() {
            if (currentStep === 4) {
                // Update review information
                const customerSelect = document.querySelector('select[name="customer_id"]');
                document.getElementById('reviewCustomer').textContent = 
                    customerSelect.options[customerSelect.selectedIndex].text || '-';
                
                document.getElementById('reviewName').textContent = 
                    document.querySelector('input[name="name"]').value || '-';
                
                document.getElementById('reviewTopic').textContent = 
                    document.querySelector('textarea[name="topic"]').value || '-';
                
                const aiProvider = document.querySelector('input[name="ai_provider"]:checked');
                document.getElementById('reviewAI').textContent = 
                    aiProvider ? (aiProvider.value === 'gemini' ? 'Google Gemini' : 'OpenAI ChatGPT') : '-';
                
                const contentStyle = document.querySelector('select[name="content_style"]');
                document.getElementById('reviewStyle').textContent = 
                    contentStyle.options[contentStyle.selectedIndex].text || '-';
                
                document.getElementById('reviewStartDate').textContent = 
                    document.querySelector('input[name="start_date"]').value || '-';
                
                document.getElementById('reviewEndDate').textContent = 
                    document.querySelector('input[name="end_date"]').value || '-';
                
                const postTime = document.querySelector('input[name="post_time"]').value;
                if (postTime) {
                    const [hours, minutes] = postTime.split(':');
                    const time = new Date();
                    time.setHours(hours, minutes);
                    document.getElementById('reviewTime').textContent = 
                        time.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
                }
                
                const checkedDays = document.querySelectorAll('input[name="days_of_week[]"]:checked');
                const dayNames = { '1': 'Mon', '2': 'Tue', '3': 'Wed', '4': 'Thu', '5': 'Fri', '6': 'Sat', '7': 'Sun' };
                const selectedDays = Array.from(checkedDays).map(cb => dayNames[cb.value]).join(', ');
                document.getElementById('reviewDays').textContent = selectedDays || '-';
                
                document.getElementById('reviewHashtags').textContent = 
                    document.querySelector('input[name="hashtags"]').value || 'None';
                
                // Calculate estimated posts
                calculateEstimatedPosts();
                
                // Update content preview
                updateContentPreview();
            }
        }
        
        function calculateEstimatedPosts() {
            const startDate = new Date(document.querySelector('input[name="start_date"]').value);
            const endDate = new Date(document.querySelector('input[name="end_date"]').value);
            const checkedDays = document.querySelectorAll('input[name="days_of_week[]"]:checked');
            
            if (startDate && endDate && checkedDays.length > 0) {
                let posts = 0;
                const currentDate = new Date(startDate);
                
                while (currentDate <= endDate) {
                    const dayOfWeek = currentDate.getDay() === 0 ? 7 : currentDate.getDay(); // Convert Sunday from 0 to 7
                    
                    const isDaySelected = Array.from(checkedDays).some(cb => cb.value == dayOfWeek);
                    if (isDaySelected) {
                        posts++;
                    }
                    
                    currentDate.setDate(currentDate.getDate() + 1);
                }
                
                document.getElementById('estimatedPosts').textContent = `${posts} posts`;
            } else {
                document.getElementById('estimatedPosts').textContent = 'Unable to calculate';
            }
        }
        
        function updateContentPreview() {
            const topic = document.querySelector('textarea[name="topic"]').value;
            const hashtags = document.querySelector('input[name="hashtags"]').value;
            
            if (topic) {
                let preview = `🚀 Excited to share insights about ${topic}!\n\n`;
                preview += `In today's rapidly evolving landscape, understanding ${topic} is crucial for success.\n\n`;
                preview += `Key takeaways:\n`;
                preview += `✅ Stay informed about industry trends\n`;
                preview += `✅ Embrace continuous learning\n`;
                preview += `✅ Network with like-minded professionals\n\n`;
                preview += `What's your experience with ${topic}? I'd love to hear your thoughts! 💭\n\n`;
                
                if (hashtags) {
                    preview += hashtags;
                } else {
                    preview += `#${topic.replace(/\s+/g, '')} #LinkedIn #Professional`;
                }
                
                document.getElementById('contentPreview').innerHTML = preview.replace(/\n/g, '<br>');
            }
        }
        
        // Form submission
        document.getElementById('automationForm').addEventListener('submit', function(e) {
            const createBtn = document.getElementById('createBtn');
            createBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Creating Automation...';
            createBtn.disabled = true;
        });
        
        // Auto-save to localStorage
        const formInputs = document.querySelectorAll('input, select, textarea');
        formInputs.forEach(input => {
            input.addEventListener('change', function() {
                if (this.type !== 'radio' || this.checked) {
                    localStorage.setItem('automation_' + this.name, this.value);
                }
            });
            
            // Restore saved values
            const saved = localStorage.getItem('automation_' + input.name);
            if (saved && !input.value && input.type !== 'radio') {
                input.value = saved;
            }
        });
        
        // Clear localStorage on successful submission
        if (window.location.search.includes('created=1')) {
            Object.keys(localStorage).forEach(key => {
                if (key.startsWith('automation_')) {
                    localStorage.removeItem(key);
                }
            });
        }
    </script>
</body>
</html>