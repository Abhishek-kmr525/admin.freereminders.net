<?php
// admin/analytics.php
session_start();
require_once '../config/database-config.php';
require_once 'includes/admin-auth.php';

$pageTitle = 'Analytics - Admin Panel';

// Fetch analytics data (placeholders for now)
try {
    $totalCustomers = $db->query("SELECT COUNT(*) FROM customers")->fetchColumn();
    $totalPosts = $db->query("SELECT COUNT(*) FROM customer_generated_posts")->fetchColumn();
    $totalAutomations = $db->query("SELECT COUNT(*) FROM customer_automations")->fetchColumn();
    $activeSubscriptions = $db->query("SELECT COUNT(*) FROM customers WHERE subscription_status = 'active'")->fetchColumn();
} catch (Exception $e) {
    error_log("Analytics query failed: " . $e->getMessage());
    $totalCustomers = $totalPosts = $totalAutomations = $activeSubscriptions = 'N/A';
}


require_once 'includes/header.php';
?>

<div class="container-fluid">
    <h1 class="h3 mb-4 text-gray-800">Analytics Dashboard</h1>

    <!-- Content Row -->
    <div class="row">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Customers</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $totalCustomers; ?></div>
                        </div>
                        <div class="col-auto"><i class="fas fa-users fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Total Posts</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $totalPosts; ?></div>
                        </div>
                        <div class="col-auto"><i class="fas fa-clipboard-list fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Active Automations</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $totalAutomations; ?></div>
                        </div>
                        <div class="col-auto"><i class="fas fa-robot fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Active Subscriptions</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $activeSubscriptions; ?></div>
                        </div>
                        <div class="col-auto"><i class="fas fa-dollar-sign fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Chart Row -->
    <div class="row">
        <div class="col-xl-8 col-lg-7">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">User Signups (Last 30 Days)</h6>
                </div>
                <div class="card-body">
                    <div class="chart-area">
                        <canvas id="userSignupsChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    // Placeholder data for the chart
    const labels = ["Week 1", "Week 2", "Week 3", "Week 4"];
    const data = {
        labels: labels,
        datasets: [{
            label: 'New Users',
            backgroundColor: 'rgba(78, 115, 223, 0.2)',
            borderColor: 'rgba(78, 115, 223, 1)',
            data: [5, 10, 15, 25], // Replace with dynamic data
            tension: 0.3
        }]
    };

    const config = {
        type: 'line',
        data: data,
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    };

    var myChart = new Chart(
        document.getElementById('userSignupsChart'),
        config
    );
});
</script>

<?php
require_once 'includes/footer.php';
?>
