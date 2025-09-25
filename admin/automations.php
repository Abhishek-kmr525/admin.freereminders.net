<?php
// admin/automations.php
session_start();
require_once '../config/database-config.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}

$message = '';
$error = '';

// Handle automation actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'toggle_status') {
        $automationId = (int)$_POST['automation_id'];
        $newStatus = $_POST['new_status'];
        
        try {
            $stmt = $db->prepare("UPDATE customer_automations SET status = ? WHERE id = ?");
            $success = $stmt->execute([$newStatus, $automationId]);
            
            if ($success) {
                $message = "Automation status updated successfully!";
            } else {
                $error = "Failed to update automation status.";
            }
        } catch (Exception $e) {
            error_log("Toggle automation status error: " . $e->getMessage());
            $error = "An error occurred while updating automation status.";
        }
    }
    
    if ($action === 'delete_automation') {
        $automationId = (int)$_POST['automation_id'];
        
        try {
            // Delete related scheduled posts first
            $stmt = $db->prepare("DELETE FROM scheduled_posts WHERE automation_id = ?");
            $stmt->execute([$automationId]);
            
            // Delete the automation
            $stmt = $db->prepare("DELETE FROM customer_automations WHERE id = ?");
            $success = $stmt->execute([$automationId]);
            
            if ($success) {
                $message = "Automation deleted successfully!";
            } else {
                $error = "Failed to delete automation.";
            }
        } catch (Exception $e) {
            error_log("Delete automation error: " . $e->getMessage());
            $error = "An error occurred while deleting automation.";
        }
    }
}

// Get filter parameters
$statusFilter = $_GET['status'] ?? 'all';
$customerFilter = $_GET['customer'] ?? '';
$dateFilter = $_GET['date_range'] ?? 'all';

// Build query conditions
$whereConditions = [];
$params = [];

if ($statusFilter !== 'all') {
    $whereConditions[] = "ca.status = ?";
    $params[] = $statusFilter;
}

if (!empty($customerFilter)) {
    $whereConditions[] = "c.name LIKE ?";
    $params[] = "%{$customerFilter}%";
}

if ($dateFilter === 'today') {
    $whereConditions[] = "DATE(ca.created_at) = CURDATE()";
} elseif ($dateFilter === 'week') {
    $whereConditions[] = "ca.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
} elseif ($dateFilter === 'month') {
    $whereConditions[] = "ca.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Get automations with stats
try {
    $stmt = $db->prepare("
        SELECT 
            ca.*,
            c.name as customer_name,
            c.email as customer_email,
            c.subscription_status,
            (SELECT COUNT(*) FROM scheduled_posts WHERE automation_id = ca.id) as total_posts,
            (SELECT COUNT(*) FROM scheduled_posts WHERE automation_id = ca.id AND status = 'published') as published_posts,
            (SELECT COUNT(*) FROM scheduled_posts WHERE automation_id = ca.id AND status = 'pending') as pending_posts,
            (SELECT COUNT(*) FROM scheduled_posts WHERE automation_id = ca.id AND status = 'failed') as failed_posts,
            (SELECT MIN(scheduled_time) FROM scheduled_posts WHERE automation_id = ca.id AND status = 'pending') as next_post_time
        FROM customer_automations ca
        JOIN customers c ON ca.customer_id = c.id
        {$whereClause}
        ORDER BY ca.created_at DESC
        LIMIT 50
    ");
    
    $stmt->execute($params);
    $automations = $stmt->fetchAll();
    
    // Get summary stats
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total_automations,
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_count,
            SUM(CASE WHEN status = 'paused' THEN 1 ELSE 0 END) as paused_count,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_count
        FROM customer_automations
    ");
    $stmt->execute();
    $stats = $stmt->fetch();
    
    // Get customers for filter dropdown
    $stmt = $db->prepare("SELECT DISTINCT name FROM customers ORDER BY name");
    $stmt->execute();
    $customers = $stmt->fetchAll();
    
} catch (Exception $e) {
    error_log("Automations page error: " . $e->getMessage());
    $automations = [];
    $stats = ['total_automations' => 0, 'active_count' => 0, 'paused_count' => 0, 'completed_count' => 0];
    $customers = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Automation Management - LinkedIn Admin</title>
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
            padding: 1.5rem;
        }
        
        .stat-number {
            font-size: 2rem;
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
        
        .automation-card {
            background: white;
            border-radius: 1rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            border: 1px solid #e2e8f0;
            transition: all 0.3s ease;
            margin-bottom: 1.5rem;
        }
        
        .automation-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.15);
        }
        
        .automation-header {
            padding: 1.5rem 2rem 1rem;
            border-bottom: 1px solid #f1f5f9;
        }
        
        .automation-body {
            padding: 1.5rem 2rem;
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
        .status-paused { background-color: #fef3c7; color: #92400e; }
        .status-completed { background-color: #dbeafe; color: #1e40af; }
        .status-cancelled { background-color: #fee2e2; color: #991b1b; }
        
        .ai-provider-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 0.375rem;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .provider-gemini { background-color: #e0f2fe; color: #0277bd; }
        .provider-chatgpt { background-color: #f3e8ff; color: #7c3aed; }
        
        .progress-bar-custom {
            height: 8px;
            border-radius: 4px;
            background-color: #f1f5f9;
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            transition: width 0.3s ease;
        }
        
        .filter-card {
            background: white;
            border-radius: 1rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            border: 1px solid #e2e8f0;
            margin-bottom: 2rem;
        }
        
        .action-btn {
            border: none;
            border-radius: 0.5rem;
            padding: 0.5rem 1rem;
            font-weight: 500;
            transition: all 0.3s ease;
            font-size: 0.875rem;
        }
        
        .btn-outline-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(59, 130, 246, 0.3);
        }
        
        .btn-outline-warning:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(245, 158, 11, 0.3);
        }
        
        .btn-outline-danger:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(239, 68, 68, 0.3);
        }
        
        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .metric-item {
            text-align: center;
            padding: 0.75rem;
            background: #f8fafc;
            border-radius: 0.5rem;
        }
        
        .metric-number {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
        }
        
        .metric-label {
            font-size: 0.75rem;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .topic-preview {
            background: #f8fafc;
            padding: 0.75rem;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            color: #475569;
            border-left: 4px solid #3b82f6;
            margin: 0.75rem 0;
        }
        
        .customer-info {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1rem;
        }
        
        .customer-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
        }
        
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: #64748b;
        }
        
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }
        
        @media (max-width: 768px) {
            .main-content {
                padding: 1rem;
            }
            
            .automation-header, .automation-body {
                padding: 1rem;
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
                            <i class="fas fa-robot me-3 text-primary"></i>Automation Management
                        </h1>
                        <p class="text-muted mb-0">Monitor and manage LinkedIn posting automations</p>
                    </div>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <a href="create-automation.php" class="btn btn-primary">
                                <i class="fas fa-plus me-2"></i>Create Automation
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
                
                <!-- Stats Cards -->
                <div class="row g-4 mb-4">
                    <div class="col-xl-3 col-md-6">
                        <div class="stat-card">
                            <div class="card-body d-flex align-items-center">
                                <div style="width: 50px; height: 50px; background: linear-gradient(135deg, #3b82f6, #1d4ed8); border-radius: 0.75rem; display: flex; align-items: center; justify-content: center; margin-right: 1rem;">
                                    <i class="fas fa-robot text-white"></i>
                                </div>
                                <div>
                                    <div class="stat-number text-primary"><?php echo number_format($stats['total_automations']); ?></div>
                                    <div class="stat-label">Total Automations</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6">
                        <div class="stat-card">
                            <div class="card-body d-flex align-items-center">
                                <div style="width: 50px; height: 50px; background: linear-gradient(135deg, #10b981, #059669); border-radius: 0.75rem; display: flex; align-items: center; justify-content: center; margin-right: 1rem;">
                                    <i class="fas fa-play text-white"></i>
                                </div>
                                <div>
                                    <div class="stat-number text-success"><?php echo number_format($stats['active_count']); ?></div>
                                    <div class="stat-label">Active</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6">
                        <div class="stat-card">
                            <div class="card-body d-flex align-items-center">
                                <div style="width: 50px; height: 50px; background: linear-gradient(135deg, #f59e0b, #d97706); border-radius: 0.75rem; display: flex; align-items: center; justify-content: center; margin-right: 1rem;">
                                    <i class="fas fa-pause text-white"></i>
                                </div>
                                <div>
                                    <div class="stat-number text-warning"><?php echo number_format($stats['paused_count']); ?></div>
                                    <div class="stat-label">Paused</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6">
                        <div class="stat-card">
                            <div class="card-body d-flex align-items-center">
                                <div style="width: 50px; height: 50px; background: linear-gradient(135deg, #6366f1, #4f46e5); border-radius: 0.75rem; display: flex; align-items: center; justify-content: center; margin-right: 1rem;">
                                    <i class="fas fa-check text-white"></i>
                                </div>
                                <div>
                                    <div class="stat-number text-info"><?php echo number_format($stats['completed_count']); ?></div>
                                    <div class="stat-label">Completed</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Filters -->
                <div class="filter-card">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">Status Filter</label>
                                <select name="status" class="form-select">
                                    <option value="all" <?php echo $statusFilter === 'all' ? 'selected' : ''; ?>>All Status</option>
                                    <option value="active" <?php echo $statusFilter === 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="paused" <?php echo $statusFilter === 'paused' ? 'selected' : ''; ?>>Paused</option>
                                    <option value="completed" <?php echo $statusFilter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                    <option value="cancelled" <?php echo $statusFilter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                </select>
                            </div>
                            
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">Customer</label>
                                <input type="text" name="customer" class="form-control" placeholder="Search customer name" 
                                       value="<?php echo htmlspecialchars($customerFilter); ?>">
                            </div>
                            
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">Date Range</label>
                                <select name="date_range" class="form-select">
                                    <option value="all" <?php echo $dateFilter === 'all' ? 'selected' : ''; ?>>All Time</option>
                                    <option value="today" <?php echo $dateFilter === 'today' ? 'selected' : ''; ?>>Today</option>
                                    <option value="week" <?php echo $dateFilter === 'week' ? 'selected' : ''; ?>>Last 7 Days</option>
                                    <option value="month" <?php echo $dateFilter === 'month' ? 'selected' : ''; ?>>Last 30 Days</option>
                                </select>
                            </div>
                            
                            <div class="col-md-3 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary me-2">
                                    <i class="fas fa-search me-1"></i>Filter
                                </button>
                                <a href="automations.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-times me-1"></i>Clear
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Automations List -->
                <div id="automations-container">
                    <?php if (empty($automations)): ?>
                        <div class="empty-state">
                            <i class="fas fa-robot"></i>
                            <h4 class="fw-bold mb-3">No Automations Found</h4>
                            <p class="mb-4">No automations match your current filters, or none have been created yet.</p>
                            <a href="create-automation.php" class="btn btn-primary">
                                <i class="fas fa-plus me-2"></i>Create Your First Automation
                            </a>
                        </div>
                    <?php else: ?>
                        <?php foreach ($automations as $automation): ?>
                            <div class="automation-card">
                                <div class="automation-header">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div class="flex-grow-1">
                                            <div class="d-flex align-items-center mb-2">
                                                <h5 class="fw-bold mb-0 me-3"><?php echo htmlspecialchars($automation['name']); ?></h5>
                                                <span class="status-badge status-<?php echo $automation['status']; ?>">
                                                    <?php echo ucfirst($automation['status']); ?>
                                                </span>
                                                <span class="ai-provider-badge provider-<?php echo $automation['ai_provider']; ?> ms-2">
                                                    <?php echo $automation['ai_provider'] === 'gemini' ? 'Gemini' : 'ChatGPT'; ?>
                                                </span>
                                            </div>
                                            
                                            <div class="customer-info">
                                                <div class="customer-avatar">
                                                    <?php echo strtoupper(substr($automation['customer_name'], 0, 1)); ?>
                                                </div>
                                                <div>
                                                    <div class="fw-semibold"><?php echo htmlspecialchars($automation['customer_name']); ?></div>
                                                    <small class="text-muted"><?php echo htmlspecialchars($automation['customer_email']); ?></small>
                                                </div>
                                                <div class="ms-auto">
                                                    <span class="status-badge status-<?php echo $automation['subscription_status']; ?>">
                                                        <?php echo ucfirst($automation['subscription_status']); ?>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="automation-body">
                                    <div class="row">
                                        <div class="col-lg-8">
                                            <div class="topic-preview">
                                                <strong>Topic:</strong> <?php echo htmlspecialchars($automation['topic']); ?>
                                            </div>
                                            
                                            <div class="row text-center mt-3">
                                                <div class="col-6 col-md-3 mb-2">
                                                    <div class="small text-muted">Schedule</div>
                                                    <div class="fw-semibold"><?php echo date('g:i A', strtotime($automation['post_time'])); ?></div>
                                                </div>
                                                <div class="col-6 col-md-3 mb-2">
                                                    <div class="small text-muted">Duration</div>
                                                    <div class="fw-semibold">
                                                        <?php 
                                                        $start = new DateTime($automation['start_date']);
                                                        $end = new DateTime($automation['end_date']);
                                                        echo $start->diff($end)->days . ' days'; 
                                                        ?>
                                                    </div>
                                                </div>
                                                <div class="col-6 col-md-3 mb-2">
                                                    <div class="small text-muted">Days</div>
                                                    <div class="fw-semibold">
                                                        <?php
                                                        $days = explode(',', $automation['days_of_week']);
                                                        $dayNames = ['1' => 'M', '2' => 'T', '3' => 'W', '4' => 'T', '5' => 'F', '6' => 'S', '7' => 'S'];
                                                        echo implode('', array_map(fn($d) => $dayNames[$d] ?? '', $days));
                                                        ?>
                                                    </div>
                                                </div>
                                                <div class="col-6 col-md-3 mb-2">
                                                    <div class="small text-muted">Next Post</div>
                                                    <div class="fw-semibold">
                                                        <?php 
                                                        if ($automation['next_post_time']) {
                                                            echo date('M j, g:i A', strtotime($automation['next_post_time']));
                                                        } else {
                                                            echo 'None';
                                                        }
                                                        ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="col-lg-4">
                                            <div class="metrics-grid">
                                                <div class="metric-item">
                                                    <div class="metric-number text-primary"><?php echo $automation['total_posts']; ?></div>
                                                    <div class="metric-label">Total Posts</div>
                                                </div>
                                                <div class="metric-item">
                                                    <div class="metric-number text-success"><?php echo $automation['published_posts']; ?></div>
                                                    <div class="metric-label">Published</div>
                                                </div>
                                                <div class="metric-item">
                                                    <div class="metric-number text-warning"><?php echo $automation['pending_posts']; ?></div>
                                                    <div class="metric-label">Pending</div>
                                                </div>
                                                <div class="metric-item">
                                                    <div class="metric-number text-danger"><?php echo $automation['failed_posts']; ?></div>
                                                    <div class="metric-label">Failed</div>
                                                </div>
                                            </div>
                                            
                                            <?php if ($automation['total_posts'] > 0): ?>
                                                <div class="progress-bar-custom mb-3">
                                                    <div class="progress-fill bg-success" 
                                                         style="width: <?php echo ($automation['published_posts'] / $automation['total_posts']) * 100; ?>%;">
                                                    </div>
                                                </div>
                                                <small class="text-muted">
                                                    <?php echo round(($automation['published_posts'] / $automation['total_posts']) * 100); ?>% completed
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <div class="d-flex flex-wrap gap-2 mt-3 pt-3 border-top">
                                        <a href="view-automation.php?id=<?php echo $automation['id']; ?>" 
                                           class="action-btn btn-outline-primary">
                                            <i class="fas fa-eye me-1"></i>View Details
                                        </a>
                                        
                                        <?php if ($automation['status'] === 'active'): ?>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="action" value="toggle_status">
                                                <input type="hidden" name="automation_id" value="<?php echo $automation['id']; ?>">
                                                <input type="hidden" name="new_status" value="paused">
                                                <button type="submit" class="action-btn btn-outline-warning" 
                                                        onclick="return confirm('Are you sure you want to pause this automation?')">
                                                    <i class="fas fa-pause me-1"></i>Pause
                                                </button>
                                            </form>
                                        <?php elseif ($automation['status'] === 'paused'): ?>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="action" value="toggle_status">
                                                <input type="hidden" name="automation_id" value="<?php echo $automation['id']; ?>">
                                                <input type="hidden" name="new_status" value="active">
                                                <button type="submit" class="action-btn btn-outline-success">
                                                    <i class="fas fa-play me-1"></i>Resume
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        
                                        <a href="edit-automation.php?id=<?php echo $automation['id']; ?>" 
                                           class="action-btn btn-outline-primary">
                                            <i class="fas fa-edit me-1"></i>Edit
                                        </a>
                                        
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="action" value="delete_automation">
                                            <input type="hidden" name="automation_id" value="<?php echo $automation['id']; ?>">
                                            <button type="submit" class="action-btn btn-outline-danger" 
                                                    onclick="return confirm('Are you sure you want to delete this automation? This action cannot be undone.')">
                                                <i class="fas fa-trash me-1"></i>Delete
                                            </button>
                                        </form>
                                        
                                        <div class="ms-auto text-muted small">
                                            Created <?php echo date('M j, Y', strtotime($automation['created_at'])); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-refresh automation data every 30 seconds
        setInterval(() => {
            if (document.hidden) return; // Don't refresh if tab is not visible
            
            fetch(window.location.href)
                .then(response => response.text())
                .then(html => {
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');
                    
                    // Update automation cards
                    const newContainer = doc.getElementById('automations-container');
                    if (newContainer) {
                        document.getElementById('automations-container').innerHTML = newContainer.innerHTML;
                    }
                    
                    // Update stats
                    const statNumbers = document.querySelectorAll('.stat-number');
                    const newStatNumbers = doc.querySelectorAll('.stat-number');
                    
                    statNumbers.forEach((stat, index) => {
                        if (newStatNumbers[index] && stat.textContent !== newStatNumbers[index].textContent) {
                            stat.style.transform = 'scale(1.1)';
                            stat.textContent = newStatNumbers[index].textContent;
                            setTimeout(() => {
                                stat.style.transform = 'scale(1)';
                            }, 200);
                        }
                    });
                })
                .catch(console.error);
        }, 30000);

        // Smooth animations for cards
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.automation-card, .stat-card');
            
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.style.opacity = '1';
                        entry.target.style.transform = 'translateY(0)';
                    }
                });
            });
            
            cards.forEach(card => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                card.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                observer.observe(card);
            });
        });

        // Add loading states to action buttons
        document.querySelectorAll('form button[type="submit"]').forEach(button => {
            button.addEventListener('click', function() {
                this.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Processing...';
                this.disabled = true;
            });
        });
    </script>
</body>
</html>