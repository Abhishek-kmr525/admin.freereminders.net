<?php
// admin/dashboard.php
session_start();
require_once '../config/database-config.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}

// Get dashboard statistics
try {
    // Total customers
    $stmt = $db->query("SELECT COUNT(*) as total FROM customers");
    $totalCustomers = $stmt->fetch()['total'] ?? 0;
    
    // Active automations
    $stmt = $db->query("SELECT COUNT(*) as total FROM customer_automations WHERE status = 'active'");
    $activeAutomations = $stmt->fetch()['total'] ?? 0;
    
    // Total posts generated
    $stmt = $db->query("SELECT COUNT(*) as total FROM customer_generated_posts");
    $totalPosts = $stmt->fetch()['total'] ?? 0;
    
    // Posts published today
    $stmt = $db->query("SELECT COUNT(*) as total FROM customer_generated_posts WHERE DATE(posted_at) = CURDATE() AND status = 'posted'");
    $postsToday = $stmt->fetch()['total'] ?? 0;
    
    // Recent customers (last 5)
    $stmt = $db->query("SELECT name, email, created_at, subscription_status FROM customers ORDER BY created_at DESC LIMIT 5");
    $recentCustomers = $stmt->fetchAll();
    
    // Recent automations (last 5)
    $stmt = $db->query("
        SELECT ca.name, ca.topic, ca.status, ca.created_at, c.name as customer_name 
        FROM customer_automations ca 
        JOIN customers c ON ca.customer_id = c.id 
        ORDER BY ca.created_at DESC LIMIT 5
    ");
    $recentAutomations = $stmt->fetchAll();
    
    // System status
    $stmt = $db->query("SELECT * FROM api_settings WHERE id = 1");
    $apiSettings = $stmt->fetch();
    
    $geminiConfigured = !empty($apiSettings['gemini_api_key']);
    $chatgptConfigured = !empty($apiSettings['chatgpt_api_key']);
    $linkedinConfigured = !empty($apiSettings['linkedin_client_id']) && !empty($apiSettings['linkedin_client_secret']);
    
} catch (Exception $e) {
    error_log("Dashboard error: " . $e->getMessage());
    $totalCustomers = $activeAutomations = $totalPosts = $postsToday = 0;
    $recentCustomers = $recentAutomations = [];
    $geminiConfigured = $chatgptConfigured = $linkedinConfigured = false;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - LinkedIn Automation</title>
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
        
        .stat-card {
            background: white;
            border-radius: 1rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            border: 1px solid #e2e8f0;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.15);
        }
        
        .stat-card .card-body {
            padding: 2rem;
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            line-height: 1;
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            font-size: 0.875rem;
            font-weight: 500;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 0.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
        }
        
        .content-card {
            background: white;
            border-radius: 1rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            border: 1px solid #e2e8f0;
        }
        
        .content-card .card-header {
            background: transparent;
            border-bottom: 1px solid #e2e8f0;
            padding: 1.5rem 1.5rem 1rem;
            font-weight: 600;
            color: #1e293b;
        }
        
        .content-card .card-body {
            padding: 1.5rem;
        }
        
        .status-badge {
            padding: 0.375rem 0.75rem;
            border-radius: 0.5rem;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .status-active { background-color: #dcfce7; color: #166534; }
        .status-trial { background-color: #fef3c7; color: #92400e; }
        .status-cancelled { background-color: #fee2e2; color: #991b1b; }
        .status-paused { background-color: #f3f4f6; color: #374151; }
        .status-completed { background-color: #dbeafe; color: #1e40af; }
        
        .quick-action-btn {
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            border: none;
            border-radius: 0.75rem;
            padding: 0.75rem 1.5rem;
            color: white;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .quick-action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(59, 130, 246, 0.4);
            color: white;
        }
        
        .system-status {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }
        
        .status-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
        }
        
        .status-online { background-color: #10b981; }
        .status-offline { background-color: #ef4444; }
        
        .welcome-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 1rem;
            color: white;
            padding: 2rem;
            margin-bottom: 2rem;
        }
        
        .table-modern {
            border: none;
        }
        
        .table-modern th {
            border-top: none;
            border-bottom: 2px solid #e2e8f0;
            color: #64748b;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.05em;
            padding: 1rem 0.75rem;
        }
        
        .table-modern td {
            border-top: 1px solid #f1f5f9;
            padding: 1rem 0.75rem;
            vertical-align: middle;
        }
        
        .table-modern tr:hover {
            background-color: #f8fafc;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                display: none;
            }
            
            .main-content {
                padding: 1rem;
            }
            
            .stat-card .card-body {
                padding: 1.5rem;
            }
            
            .stat-number {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Enhanced Sidebar -->
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
                            <a href="dashboard.php" class="nav-link active">
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
                <!-- Welcome Header -->
                <div class="welcome-header">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h1 class="fw-bold mb-2">Welcome back, <?php echo htmlspecialchars($_SESSION['admin_username']); ?>! 👋</h1>
                            <p class="mb-0 opacity-90">Here's what's happening with your LinkedIn automation platform today.</p>
                        </div>
                        <div class="col-md-4 text-md-end">
                            <div class="d-flex flex-column align-items-md-end">
                                <div class="text-white-50 small">Today is</div>
                                <div class="fw-semibold"><?php echo date('F j, Y'); ?></div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Stats Cards -->
                <div class="row g-4 mb-4">
                    <div class="col-xl-3 col-md-6">
                        <div class="stat-card">
                            <div class="card-body d-flex align-items-center">
                                <div class="stat-icon bg-primary me-3">
                                    <i class="fas fa-users"></i>
                                </div>
                                <div>
                                    <div class="stat-number text-primary"><?php echo number_format($totalCustomers); ?></div>
                                    <div class="stat-label">Total Customers</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6">
                        <div class="stat-card">
                            <div class="card-body d-flex align-items-center">
                                <div class="stat-icon bg-success me-3">
                                    <i class="fas fa-robot"></i>
                                </div>
                                <div>
                                    <div class="stat-number text-success"><?php echo number_format($activeAutomations); ?></div>
                                    <div class="stat-label">Active Automations</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6">
                        <div class="stat-card">
                            <div class="card-body d-flex align-items-center">
                                <div class="stat-icon bg-info me-3">
                                    <i class="fas fa-newspaper"></i>
                                </div>
                                <div>
                                    <div class="stat-number text-info"><?php echo number_format($totalPosts); ?></div>
                                    <div class="stat-label">Total Posts Generated</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6">
                        <div class="stat-card">
                            <div class="card-body d-flex align-items-center">
                                <div class="stat-icon bg-warning me-3">
                                    <i class="fas fa-calendar-day"></i>
                                </div>
                                <div>
                                    <div class="stat-number text-warning"><?php echo number_format($postsToday); ?></div>
                                    <div class="stat-label">Posts Published Today</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row g-4">
                    <!-- System Status -->
                    <div class="col-lg-4">
                        <div class="content-card">
                            <div class="card-header">
                                <i class="fas fa-heartbeat me-2"></i>System Status
                            </div>
                            <div class="card-body">
                                <div class="system-status">
                                    <div class="status-dot <?php echo $geminiConfigured ? 'status-online' : 'status-offline'; ?>"></div>
                                    <span>Google Gemini AI</span>
                                    <?php if ($geminiConfigured): ?>
                                        <small class="text-success ms-auto">Connected</small>
                                    <?php else: ?>
                                        <small class="text-danger ms-auto">Not configured</small>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="system-status">
                                    <div class="status-dot <?php echo $chatgptConfigured ? 'status-online' : 'status-offline'; ?>"></div>
                                    <span>OpenAI ChatGPT</span>
                                    <?php if ($chatgptConfigured): ?>
                                        <small class="text-success ms-auto">Connected</small>
                                    <?php else: ?>
                                        <small class="text-danger ms-auto">Not configured</small>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="system-status">
                                    <div class="status-dot <?php echo $linkedinConfigured ? 'status-online' : 'status-offline'; ?>"></div>
                                    <span>LinkedIn OAuth</span>
                                    <?php if ($linkedinConfigured): ?>
                                        <small class="text-success ms-auto">Configured</small>
                                    <?php else: ?>
                                        <small class="text-danger ms-auto">Not configured</small>
                                    <?php endif; ?>
                                </div>
                                
                                <?php if (!$geminiConfigured || !$chatgptConfigured || !$linkedinConfigured): ?>
                                <div class="mt-3">
                                    <a href="api-settings.php" class="btn btn-outline-primary btn-sm w-100">
                                        <i class="fas fa-cog me-2"></i>Configure APIs
                                    </a>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Quick Actions -->
                        <div class="content-card mt-4">
                            <div class="card-header">
                                <i class="fas fa-bolt me-2"></i>Quick Actions
                            </div>
                            <div class="card-body">
                                <div class="d-grid gap-2">
                                    <a href="customers.php?action=add" class="quick-action-btn">
                                        <i class="fas fa-user-plus"></i>Add New Customer
                                    </a>
                                    <a href="templates.php?action=add" class="quick-action-btn" style="background: linear-gradient(135deg, #10b981, #059669);">
                                        <i class="fas fa-file-plus"></i>Create Template
                                    </a>
                                    <a href="analytics.php" class="quick-action-btn" style="background: linear-gradient(135deg, #f59e0b, #d97706);">
                                        <i class="fas fa-chart-line"></i>View Analytics
                                    </a>
                                    <a href="posts.php" class="quick-action-btn" style="background: linear-gradient(135deg, #8b5cf6, #7c3aed);">
                                        <i class="fas fa-newspaper"></i>Manage Posts
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Recent Customers -->
                    <div class="col-lg-4">
                        <div class="content-card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <span><i class="fas fa-users me-2"></i>Recent Customers</span>
                                <a href="customers.php" class="text-decoration-none small">View All</a>
                            </div>
                            <div class="card-body p-0">
                                <?php if (!empty($recentCustomers)): ?>
                                    <div class="list-group list-group-flush">
                                        <?php foreach ($recentCustomers as $customer): ?>
                                        <div class="list-group-item border-0 py-3">
                                            <div class="d-flex align-items-center">
                                                <div class="bg-primary rounded-circle p-2 me-3">
                                                    <i class="fas fa-user text-white"></i>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <h6 class="mb-1"><?php echo htmlspecialchars($customer['name']); ?></h6>
                                                    <p class="mb-0 small text-muted"><?php echo htmlspecialchars($customer['email']); ?></p>
                                                    <small class="text-muted"><?php echo date('M j, Y', strtotime($customer['created_at'])); ?></small>
                                                </div>
                                                <span class="status-badge status-<?php echo $customer['subscription_status']; ?>">
                                                    <?php echo ucfirst($customer['subscription_status']); ?>
                                                </span>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center py-4 text-muted">
                                        <i class="fas fa-users fa-2x mb-2"></i>
                                        <p>No customers yet</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Recent Automations -->
                    <div class="col-lg-4">
                        <div class="content-card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <span><i class="fas fa-robot me-2"></i>Recent Automations</span>
                                <a href="automations.php" class="text-decoration-none small">View All</a>
                            </div>
                            <div class="card-body p-0">
                                <?php if (!empty($recentAutomations)): ?>
                                    <div class="list-group list-group-flush">
                                        <?php foreach ($recentAutomations as $automation): ?>
                                        <div class="list-group-item border-0 py-3">
                                            <div class="d-flex align-items-center">
                                                <div class="bg-success rounded-circle p-2 me-3">
                                                    <i class="fas fa-robot text-white"></i>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <h6 class="mb-1"><?php echo htmlspecialchars($automation['name']); ?></h6>
                                                    <p class="mb-0 small text-muted">by <?php echo htmlspecialchars($automation['customer_name']); ?></p>
                                                    <small class="text-muted"><?php echo date('M j, Y', strtotime($automation['created_at'])); ?></small>
                                                </div>
                                                <span class="status-badge status-<?php echo $automation['status']; ?>">
                                                    <?php echo ucfirst($automation['status']); ?>
                                                </span>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center py-4 text-muted">
                                        <i class="fas fa-robot fa-2x mb-2"></i>
                                        <p>No automations yet</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Real-time clock
        function updateClock() {
            const now = new Date();
            const timeString = now.toLocaleTimeString('en-US', {
                hour12: false,
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            });
            const clockElement = document.getElementById('real-time-clock');
            if (clockElement) {
                clockElement.textContent = timeString;
            }
        }

        // Update clock every second
        setInterval(updateClock, 1000);
        updateClock(); // Initial call

        // Auto-refresh stats every 30 seconds
        setInterval(() => {
            fetch('api/dashboard-stats.php')
                .then(response => response.json())
                .then(data => {
                    // Update stat cards with fresh data
                    document.querySelector('.stat-number.text-primary').textContent = data.totalCustomers || 0;
                    document.querySelector('.stat-number.text-success').textContent = data.activeAutomations || 0;
                    document.querySelector('.stat-number.text-info').textContent = data.totalPosts || 0;
                    document.querySelector('.stat-number.text-warning').textContent = data.postsToday || 0;
                })
                .catch(console.error);
        }, 30000);

        // Smooth hover animations
        document.querySelectorAll('.stat-card, .quick-action-btn').forEach(element => {
            element.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-4px)';
            });
            
            element.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
            });
        });
    </script>
</body>
</html>