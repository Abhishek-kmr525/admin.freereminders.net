<?php
// admin/settings.php
session_start();
require_once '../config/database-config.php';
require_once 'includes/admin-auth.php';

$pageTitle = 'General Settings - Admin Panel';

$successMessage = '';
$errorMessage = '';

// This is a placeholder. In a real application, you would fetch these from a settings table.
$settings = [
    'site_name' => 'Post Automator',
    'admin_email' => 'admin@postautomator.com',
    'maintenance_mode' => 'off',
    'trial_days' => 14
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // This is where you would handle the form submission to save the settings.
    // For now, we'll just show a success message.
    $successMessage = "Settings saved successfully! (This is a demo)";
    
    // You would update the settings array from $_POST data here
    // $settings['site_name'] = $_POST['site_name'] ?? $settings['site_name'];
    // ... and so on
}

require_once 'includes/header.php';
?>

<div class="container-fluid">
    <h1 class="h3 mb-4 text-gray-800">General Settings</h1>

    <?php if ($successMessage): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($successMessage); ?></div>
    <?php endif; ?>
    <?php if ($errorMessage): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($errorMessage); ?></div>
    <?php endif; ?>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Site Configuration</h6>
        </div>
        <div class="card-body">
            <form method="POST">
                <div class="mb-3">
                    <label for="site_name" class="form-label">Site Name</label>
                    <input type="text" class="form-control" id="site_name" name="site_name" value="<?php echo htmlspecialchars($settings['site_name']); ?>">
                </div>
                <div class="mb-3">
                    <label for="admin_email" class="form-label">Admin Email</label>
                    <input type="email" class="form-control" id="admin_email" name="admin_email" value="<?php echo htmlspecialchars($settings['admin_email']); ?>">
                </div>
                <div class="mb-3">
                    <label for="trial_days" class="form-label">Free Trial Period (Days)</label>
                    <input type="number" class="form-control" id="trial_days" name="trial_days" value="<?php echo htmlspecialchars($settings['trial_days']); ?>">
                </div>
                <div class="mb-3">
                    <label for="maintenance_mode" class="form-label">Maintenance Mode</label>
                    <select class="form-select" id="maintenance_mode" name="maintenance_mode">
                        <option value="off" <?php echo ($settings['maintenance_mode'] === 'off') ? 'selected' : ''; ?>>Off</option>
                        <option value="on" <?php echo ($settings['maintenance_mode'] === 'on') ? 'selected' : ''; ?>>On</option>
                    </select>
                    <div class="form-text">When turned on, only admins will be able to access the site.</div>
                </div>

                <button type="submit" class="btn btn-primary">Save Settings</button>
            </form>
        </div>
    </div>
</div>

<?php
require_once 'includes/footer.php';
?>
