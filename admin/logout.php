<?php
// admin/logout.php
session_start();
session_destroy();
header('Location: login.php?message=logged_out');
exit();
?>

<?php
// admin/posts.php - View generated posts
session_start();
require_once '../config/database-config.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}

// Get posts with filters
$automationId = $_GET['automation_id'] ?? '';
$status = $_GET['status'] ?? '';
$customerId = $_GET['customer_id'] ?? '';

$whereConditions = [];
$params = [];

if ($automationId) {
    $whereConditions[] = "cgp.automation_id = ?";
    $params[] = $automationId;
}

if ($status) {
    $whereConditions[] = "cgp.status = ?";
    $params[] = $status;
}

if ($customerId) {
    $whereConditions[] = "cgp.customer_id = ?";
    $params[] = $customerId;
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

try {
    $stmt = $db->prepare("
        SELECT cgp.*, ca.name as automation_name, c.name as customer_name
        FROM customer_generated_posts cgp
        JOIN customer_automations ca ON cgp.automation_id = ca.id
        JOIN customers c ON cgp.customer_id = c.id
        $whereClause
        ORDER BY cgp.created_at DESC
        LIMIT 50
    ");
    $stmt->execute($params);
    $posts = $stmt->fetchAll();
    
    // Get customers for filter
    $stmt = $db->prepare("SELECT id, name FROM customers ORDER BY name");
    $stmt->execute();
    $customers = $stmt->fetchAll();
    
} catch (Exception $e) {
    error_log("Posts page error: " . $e->getMessage());
    $posts = [];
    $customers = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generated Posts - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .nav-link {
            color: rgba(255,255,255,0.8) !important;
            padding: 12px 20px;
        }
        .nav-link:hover, .nav-link.active {
            color: white !important;
            background: rgba(255,255,255,0.1);
        }
        .post-content {
            max-height: 100px;
            overflow-y: auto;
            font-size: 0.9em;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 d-md-block sidebar collapse">
                <div class="position-sticky pt-3">
                    <div class="text-center mb-4">
                        <h5 class="text-white fw-bold">
                            <i class="fas fa-shield-alt me-2"></i>Admin Panel
                        </h5>
                        <small class="text-white-50">LinkedIn Automation</small>
                    </div>
                    
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="dashboard.php">
                                <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="automations.php">
                                <i class="fas fa-robot me-2"></i>Automations
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="customers.php">
                                <i class="fas fa-users me-2"></i>Customers
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="api-settings.php">
                                <i class="fas fa-cog me-2"></i>API Settings
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="posts.php">
                                <i class="fas fa-newspaper me-2"></i>Generated Posts
                            </a>
                        </li>
                    </ul>
                    
                    <hr class="text-white-50">
                    
                    <div class="dropdown">
                        <a href="#" class="nav-link dropdown-toggle" data-bs-toggle="dropdown">
                            <i class="fas fa-user me-2"></i><?php echo htmlspecialchars($_SESSION['admin_username']); ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="profile.php">Profile</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php">Logout</a></li>
                        </ul>
                    </div>
                </div>
            </nav>
            
            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Generated Posts</h1>
                </div>
                
                <!-- Filters -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">
                                <form method="GET" class="row g-3">
                                    <div class="col-md-3">
                                        <label class="form-label">Customer</label>
                                        <select name="customer_id" class="form-select">
                                            <option value="">All Customers</option>
                                            <?php foreach ($customers as $customer): ?>
                                                <option value="<?php echo $customer['id']; ?>" <?php echo $customerId == $customer['id'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($customer['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Status</label>
                                        <select name="status" class="form-select">
                                            <option value="">All Statuses</option>
                                            <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                            <option value="posted" <?php echo $status === 'posted' ? 'selected' : ''; ?>>Posted</option>
                                            <option value="failed" <?php echo $status === 'failed' ? 'selected' : ''; ?>>Failed</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">&nbsp;</label>
                                        <div class="d-grid">
                                            <button type="submit" class="btn btn-primary">
                                                <i class="fas fa-filter me-1"></i>Filter
                                            </button>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">&nbsp;</label>
                                        <div class="d-grid">
                                            <a href="posts.php" class="btn btn-outline-secondary">
                                                <i class="fas fa-times me-1"></i>Clear
                                            </a>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Posts Table -->
                <div class="card">
                    <div class="card-body">
                        <?php if (!empty($posts)): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Customer</th>
                                            <th>Automation</th>
                                            <th>Content Preview</th>
                                            <th>Scheduled</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($posts as $post): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($post['customer_name']); ?></td>
                                                <td><?php echo htmlspecialchars($post['automation_name']); ?></td>
                                                <td>
                                                    <div class="post-content">
                                                        <?php echo nl2br(htmlspecialchars(substr($post['content'], 0, 200))); ?>
                                                        <?php if (strlen($post['content']) > 200): ?>...<?php endif; ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <small><?php echo date('M j, Y H:i', strtotime($post['scheduled_time'])); ?></small>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php 
                                                        echo $post['status'] === 'posted' ? 'success' : 
                                                             ($post['status'] === 'pending' ? 'warning' : 'danger'); 
                                                    ?>">
                                                        <?php echo ucfirst($post['status']); ?>
                                                    </span>
                                                    <?php if ($post['status'] === 'failed' && $post['error_message']): ?>
                                                        <br><small class="text-danger"><?php echo htmlspecialchars($post['error_message']); ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <button class="btn btn-sm btn-outline-primary" onclick="viewPost(<?php echo $post['id']; ?>)">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <?php if ($post['status'] === 'pending'): ?>
                                                        <button class="btn btn-sm btn-outline-success" onclick="publishNow(<?php echo $post['id']; ?>)">
                                                            <i class="fas fa-share"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="fas fa-newspaper fa-4x text-muted mb-3"></i>
                                <h5 class="text-muted">No posts found</h5>
                                <p class="text-muted">Create some automations to generate posts</p>
                                <a href="automations.php" class="btn btn-primary">Create Automation</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function viewPost(postId) {
            // You can implement a modal to view full post content
            alert('View post functionality - implement modal here');
        }
        
        function publishNow(postId) {
            if (confirm('Publish this post immediately?')) {
                // Implement immediate publishing
                window.location.href = 'publish-post.php?id=' + postId;
            }
        }
    </script>
</body>
</html>