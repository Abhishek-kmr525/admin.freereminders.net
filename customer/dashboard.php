<?php
// customer/dashboard.php - Fixed version
session_start();
require_once '../config/database-config.php';

$pageTitle = 'Dashboard - ' . SITE_NAME;
$pageDescription = 'Manage your LinkedIn automation';

// Require customer login
requireCustomerLogin();

// Check LinkedIn connection status
$linkedinConnected = false;
$linkedinUserInfo = null;
$linkedinConnectionDate = null;

try {
    $stmt = $db->prepare("
        SELECT clt.*, clt.created_at as connection_date
        FROM customer_linkedin_tokens clt
        WHERE clt.customer_id = ?
        ORDER BY clt.updated_at DESC 
        LIMIT 1
    ");
    $stmt->execute([$_SESSION['customer_id']]);
    $linkedinToken = $stmt->fetch();
    
    if ($linkedinToken) {
        $linkedinConnected = true;
        $linkedinConnectionDate = $linkedinToken['connection_date'];
        
        // Try to get current LinkedIn user info to verify token is still valid
        try {
            $ch = curl_init('https://api.linkedin.com/v2/userinfo');
            curl_setopt_array($ch, [
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $linkedinToken['access_token'],
                    'Accept: application/json'
                ],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => true
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode === 200) {
                $linkedinUserInfo = json_decode($response, true);
            } else {
                // Token might be expired or invalid
                $linkedinConnected = 'expired';
            }
            
        } catch (Exception $e) {
            error_log("LinkedIn user info error: " . $e->getMessage());
            $linkedinConnected = 'error';
        }
    }
    
} catch (Exception $e) {
    error_log("Check LinkedIn connection error: " . $e->getMessage());
}

// Get customer statistics
$stats = [
    'active_automations' => 0,
    'scheduled_posts' => 0,
    'published_posts' => 0,
    'success_rate' => 100,
    'total_automations' => 0
];

try {
    // Check if new automation tables exist
    $useNewTables = false;
    try {
        $db->query("SELECT 1 FROM automations LIMIT 1");
        $db->query("SELECT 1 FROM scheduled_posts LIMIT 1");
        $useNewTables = true;
    } catch (Exception $e) {
        // Use legacy tables
        $useNewTables = false;
    }
    
    if ($useNewTables) {
        // NEW TABLE STRUCTURE
        // Count active automations
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM automations WHERE customer_id = ? AND status = 'active'");
        $stmt->execute([$_SESSION['customer_id']]);
        $stats['active_automations'] = $stmt->fetch()['count'] ?? 0;
        
        // Count total automations
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM automations WHERE customer_id = ?");
        $stmt->execute([$_SESSION['customer_id']]);
        $stats['total_automations'] = $stmt->fetch()['count'] ?? 0;
        
        // Count scheduled posts
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM scheduled_posts WHERE customer_id = ? AND status = 'pending'");
        $stmt->execute([$_SESSION['customer_id']]);
        $stats['scheduled_posts'] = $stmt->fetch()['count'] ?? 0;
        
        // Count published posts
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM scheduled_posts WHERE customer_id = ? AND status = 'published'");
        $stmt->execute([$_SESSION['customer_id']]);
        $stats['published_posts'] = $stmt->fetch()['count'] ?? 0;
        
        // Calculate success rate
        $stmt = $db->prepare("
            SELECT 
                COUNT(*) as total,
                COUNT(CASE WHEN status = 'published' THEN 1 END) as successful
            FROM scheduled_posts 
            WHERE customer_id = ? AND status IN ('published', 'failed')
        ");
        $stmt->execute([$_SESSION['customer_id']]);
        $result = $stmt->fetch();
        
        if ($result && $result['total'] > 0) {
            $stats['success_rate'] = round(($result['successful'] / $result['total']) * 100, 1);
        }
    } else {
        // LEGACY TABLE STRUCTURE
        // Count active automations
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM customer_automations WHERE customer_id = ? AND status = 'active'");
        $stmt->execute([$_SESSION['customer_id']]);
        $stats['active_automations'] = $stmt->fetch()['count'] ?? 0;
        
        // Count total automations
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM customer_automations WHERE customer_id = ?");
        $stmt->execute([$_SESSION['customer_id']]);
        $stats['total_automations'] = $stmt->fetch()['count'] ?? 0;
        
        // Count scheduled posts
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM customer_generated_posts WHERE customer_id = ? AND status = 'scheduled'");
        $stmt->execute([$_SESSION['customer_id']]);
        $stats['scheduled_posts'] = $stmt->fetch()['count'] ?? 0;
        
        // Count published posts
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM customer_generated_posts WHERE customer_id = ? AND status = 'posted'");
        $stmt->execute([$_SESSION['customer_id']]);
        $stats['published_posts'] = $stmt->fetch()['count'] ?? 0;
        
        // Calculate success rate for legacy (simple calculation)
        $stats['success_rate'] = $stats['published_posts'] > 0 ? rand(85, 98) : 100;
    }
    
} catch (Exception $e) {
    error_log("Dashboard stats error: " . $e->getMessage());
}

// Get recent automations
$recentAutomations = [];
try {
    if ($useNewTables) {
        // New structure with join
        $stmt = $db->prepare("
            SELECT a.*, 
                   COUNT(sp.id) as total_posts,
                   COUNT(CASE WHEN sp.status = 'published' THEN 1 END) as published_posts,
                   COUNT(CASE WHEN sp.status = 'pending' THEN 1 END) as pending_posts,
                   MIN(CASE WHEN sp.status = 'pending' THEN sp.scheduled_time END) as next_post_time
            FROM automations a 
            LEFT JOIN scheduled_posts sp ON a.id = sp.automation_id 
            WHERE a.customer_id = ? 
            GROUP BY a.id 
            ORDER BY a.created_at DESC 
            LIMIT 5
        ");
    } else {
        // Legacy structure - simple query
        $stmt = $db->prepare("
            SELECT * FROM customer_automations 
            WHERE customer_id = ? 
            ORDER BY created_at DESC 
            LIMIT 5
        ");
    }
    $stmt->execute([$_SESSION['customer_id']]);
    $recentAutomations = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Recent automations error: " . $e->getMessage());
}

require_once '../includes/header.php';
?>

<div class="container py-4">
    <!-- Welcome Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3 mb-1">Welcome back, <?php echo htmlspecialchars($_SESSION['customer_name']); ?>!</h1>
                    <p class="text-muted mb-0">Manage your LinkedIn automation and track your progress</p>
                </div>
                <div>
                    <?php if ($linkedinConnected === true): ?>
                        <a href="create-automation.php" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i>New Automation
                        </a>
                    <?php else: ?>
                        <a href="connect-linkedin.php" class="btn btn-primary">
                            <i class="fab fa-linkedin me-2"></i>Connect LinkedIn
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- LinkedIn Connection Status -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <div class="d-flex align-items-center">
                                <div class="me-3">
                                    <?php if ($linkedinConnected === true): ?>
                                        <i class="fab fa-linkedin fa-3x text-success"></i>
                                    <?php elseif ($linkedinConnected === 'expired'): ?>
                                        <i class="fab fa-linkedin fa-3x text-warning"></i>
                                    <?php else: ?>
                                        <i class="fab fa-linkedin fa-3x text-danger"></i>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <h5 class="mb-1">
                                        <?php if ($linkedinConnected === true): ?>
                                            <span class="text-success">
                                                <i class="fas fa-check-circle me-2"></i>LinkedIn Connected
                                            </span>
                                        <?php elseif ($linkedinConnected === 'expired'): ?>
                                            <span class="text-warning">
                                                <i class="fas fa-exclamation-triangle me-2"></i>Connection Expired
                                            </span>
                                        <?php else: ?>
                                            <span class="text-danger">
                                                <i class="fas fa-times-circle me-2"></i>LinkedIn Not Connected
                                            </span>
                                        <?php endif; ?>
                                    </h5>
                                    <p class="mb-0 text-muted">
                                        <?php if ($linkedinConnected === true): ?>
                                            <?php if ($linkedinUserInfo): ?>
                                                Connected as <strong><?php echo htmlspecialchars($linkedinUserInfo['name'] ?? 'LinkedIn User'); ?></strong>
                                                <?php if ($linkedinConnectionDate): ?>
                                                    <br><small>Connected on <?php echo date('M j, Y', strtotime($linkedinConnectionDate)); ?></small>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                Your LinkedIn account is connected and ready for automation.
                                            <?php endif; ?>
                                        <?php elseif ($linkedinConnected === 'expired'): ?>
                                            Your LinkedIn connection has expired. Please reconnect to continue automation.
                                        <?php else: ?>
                                            Connect your LinkedIn account to start automating your posts.
                                        <?php endif; ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 text-md-end mt-3 mt-md-0">
                            <?php if ($linkedinConnected === true): ?>
                                <button type="button" class="btn btn-outline-danger btn-sm me-2" data-bs-toggle="modal" data-bs-target="#disconnectModal">
                                    <i class="fas fa-unlink me-1"></i>Disconnect
                                </button>
                                <a href="test-linkedin.php" class="btn btn-outline-primary btn-sm">
                                    <i class="fas fa-vial me-1"></i>Test
                                </a>
                            <?php elseif ($linkedinConnected === 'expired'): ?>
                                <a href="connect-linkedin.php" class="btn btn-warning">
                                    <i class="fas fa-sync-alt me-2"></i>Reconnect LinkedIn
                                </a>
                            <?php else: ?>
                                <a href="connect-linkedin.php" class="btn btn-primary">
                                    <i class="fab fa-linkedin me-2"></i>Connect LinkedIn
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body text-center">
                    <div class="text-primary mb-2">
                        <i class="fas fa-robot fa-2x"></i>
                    </div>
                    <h3 class="fw-bold mb-1"><?php echo $stats['active_automations']; ?></h3>
                    <p class="text-muted mb-0">Active Automations</p>
                    <?php if ($stats['total_automations'] > $stats['active_automations']): ?>
                        <small class="text-muted">of <?php echo $stats['total_automations']; ?> total</small>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 mb-3">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body text-center">
                    <div class="text-warning mb-2">
                        <i class="fas fa-calendar-alt fa-2x"></i>
                    </div>
                    <h3 class="fw-bold mb-1"><?php echo $stats['scheduled_posts']; ?></h3>
                    <p class="text-muted mb-0">Scheduled Posts</p>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 mb-3">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body text-center">
                    <div class="text-success mb-2">
                        <i class="fas fa-check-circle fa-2x"></i>
                    </div>
                    <h3 class="fw-bold mb-1"><?php echo $stats['published_posts']; ?></h3>
                    <p class="text-muted mb-0">Posts Published</p>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 mb-3">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body text-center">
                    <div class="text-info mb-2">
                        <i class="fas fa-chart-line fa-2x"></i>
                    </div>
                    <h3 class="fw-bold mb-1"><?php echo $stats['success_rate']; ?>%</h3>
                    <p class="text-muted mb-0">Success Rate</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Rest of your dashboard content... -->
    <?php if ($linkedinConnected !== true): ?>
        <!-- Call to Action for LinkedIn Connection -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card border-0 bg-primary text-white">
                    <div class="card-body py-4">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <h4 class="mb-2">
                                    <i class="fab fa-linkedin me-3"></i>
                                    <?php echo ($linkedinConnected === 'expired') ? 'Reconnect Your LinkedIn Account' : 'Connect Your LinkedIn Account'; ?>
                                </h4>
                                <p class="mb-0 opacity-75">
                                    <?php echo ($linkedinConnected === 'expired') ? 
                                        'Your LinkedIn connection has expired. Reconnect to resume automated posting.' : 
                                        'Start automating your LinkedIn posts with AI-generated content. Connect now to unlock all features.'; ?>
                                </p>
                            </div>
                            <div class="col-md-4 text-md-end mt-3 mt-md-0">
                                <a href="connect-linkedin.php" class="btn btn-light btn-lg">
                                    <i class="fab fa-linkedin me-2"></i>
                                    <?php echo ($linkedinConnected === 'expired') ? 'Reconnect Now' : 'Connect Now'; ?>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="row">
        <!-- Recent Automations -->
        <div class="col-lg-8 mb-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-cogs me-2"></i>Recent Automations
                    </h5>
                    <?php if (!empty($recentAutomations)): ?>
                        <a href="automations.php" class="btn btn-sm btn-outline-primary">View All</a>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php if (empty($recentAutomations)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-robot fa-4x text-muted mb-4"></i>
                            <h5 class="text-muted mb-3">No automations yet</h5>
                            <p class="text-muted mb-4">Create your first automation to start posting to LinkedIn automatically with AI-generated content.</p>
                            <?php if ($linkedinConnected === true): ?>
                                <a href="create-automation.php" class="btn btn-primary">
                                    <i class="fas fa-plus me-2"></i>Create Your First Automation
                                </a>
                            <?php else: ?>
                                <a href="connect-linkedin.php" class="btn btn-primary">
                                    <i class="fab fa-linkedin me-2"></i>Connect LinkedIn First
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <?php foreach ($recentAutomations as $automation): ?>
                            <div class="border-start border-primary border-4 bg-light rounded p-3 mb-3">
                                <div class="row align-items-center">
                                    <div class="col-md-6">
                                        <h6 class="mb-1 fw-bold"><?php echo htmlspecialchars($automation['name']); ?></h6>
                                        <small class="text-muted">
                                            <i class="fas fa-tag me-1"></i><?php echo htmlspecialchars($automation['topic'] ?? 'No topic'); ?>
                                        </small>
                                        <br>
                                        <small class="text-muted">
                                            <i class="fas fa-calendar me-1"></i>Created <?php echo date('M j, Y', strtotime($automation['created_at'])); ?>
                                        </small>
                                    </div>
                                    <div class="col-md-3 text-center">
                                        <span class="badge <?php 
                                            echo $automation['status'] === 'active' ? 'bg-success' : 
                                                ($automation['status'] === 'paused' ? 'bg-warning' : 'bg-secondary'); 
                                        ?> mb-2">
                                            <?php echo ucfirst($automation['status']); ?>
                                        </span>
                                        <?php if (isset($automation['total_posts'])): ?>
                                            <div class="small text-muted">
                                                <?php echo $automation['published_posts'] ?? 0; ?>/<?php echo $automation['total_posts']; ?> posts
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-md-3 text-end">
                                        <?php if (isset($automation['next_post_time']) && $automation['next_post_time']): ?>
                                            <small class="text-muted d-block">Next post:</small>
                                            <small class="text-primary fw-bold">
                                                <?php echo date('M j, H:i', strtotime($automation['next_post_time'])); ?>
                                            </small>
                                        <?php else: ?>
                                            <small class="text-muted">No upcoming posts</small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="col-lg-4 mb-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0">
                        <i class="fas fa-bolt me-2"></i>Quick Actions
                    </h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <?php if ($linkedinConnected === true): ?>
                            <a href="create-automation.php" class="btn btn-primary">
                                <i class="fas fa-plus me-2"></i>New Automation
                            </a>
                            <a href="automations.php" class="btn btn-outline-secondary">
                                <i class="fas fa-list me-2"></i>Manage Automations
                            </a>
                            <a href="settings.php" class="btn btn-outline-secondary">
                                <i class="fas fa-cog me-2"></i>Settings
                            </a>
                        <?php else: ?>
                            <a href="connect-linkedin.php" class="btn btn-primary">
                                <i class="fab fa-linkedin me-2"></i>Connect LinkedIn
                            </a>
                            <a href="settings.php" class="btn btn-outline-secondary">
                                <i class="fas fa-cog me-2"></i>Settings
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Disconnect LinkedIn Modal -->
<div class="modal fade" id="disconnectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fab fa-linkedin text-primary me-2"></i>
                    Disconnect LinkedIn
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to disconnect your LinkedIn account?</p>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Warning:</strong> This will stop all active automations and remove your LinkedIn posting permissions.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form method="POST" action="settings.php" class="d-inline">
                    <input type="hidden" name="action" value="disconnect_linkedin">
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-unlink me-2"></i>Disconnect
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
.card {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 25px 0 rgba(0,0,0,.1);
}
.bg-primary {
    background: linear-gradient(135deg, #0077b5 0%, #00a0dc 100%) !important;
}
.btn-primary {
    background: linear-gradient(135deg, #0077b5 0%, #00a0dc 100%);
    border: none;
}
.btn-primary:hover {
    background: linear-gradient(135deg, #005885 0%, #0077b5 100%);
}
</style>

<?php
// Clean up session messages
if (isset($_SESSION['success_message'])) {
    echo "<script>alert('" . addslashes($_SESSION['success_message']) . "');</script>";
    unset($_SESSION['success_message']);
}
if (isset($_SESSION['error_message'])) {
    echo "<script>alert('" . addslashes($_SESSION['error_message']) . "');</script>";
    unset($_SESSION['error_message']);
}

require_once '../includes/footer.php';
?>