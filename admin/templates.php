<?php
// admin/templates.php
session_start();
require_once '../config/database-config.php';
require_once 'includes/admin-auth.php';

$pageTitle = 'Manage Post Templates - Admin Panel';

// For now, we'll use a placeholder array. Later, this will come from the database.
$templates = [
    ['id' => 1, 'name' => 'Tech News Update', 'category' => 'Technology', 'content' => 'Here is the latest in tech: {news_item}. What are your thoughts? #TechNews #{topic}', 'created_at' => '2023-10-26 10:00:00'],
    ['id' => 2, 'name' => 'Marketing Tip of the Day', 'category' => 'Marketing', 'content' => 'Today\'s marketing tip: {tip_of_the_day}. #MarketingTips #DigitalMarketing', 'created_at' => '2023-10-25 14:30:00'],
    ['id' => 3, 'name' => 'Motivational Monday', 'category' => 'Inspiration', 'content' => 'Start your week with this powerful thought: {quote}. #MotivationalMonday #Inspiration', 'created_at' => '2023-10-23 09:00:00'],
];

require_once 'includes/header.php';
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Post Templates</h1>
        <a href="#" class="btn btn-primary shadow-sm">
            <i class="fas fa-plus fa-sm text-white-50"></i> Create New Template
        </a>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">All Templates</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered" id="templatesTable" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Category</th>
                            <th>Content Snippet</th>
                            <th>Created Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($templates as $template): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($template['id']); ?></td>
                                <td><?php echo htmlspecialchars($template['name']); ?></td>
                                <td><span class="badge bg-info"><?php echo htmlspecialchars($template['category']); ?></span></td>
                                <td><?php echo htmlspecialchars(substr($template['content'], 0, 70)); ?>...</td>
                                <td><?php echo date('M d, Y', strtotime($template['created_at'])); ?></td>
                                <td>
                                    <a href="#" class="btn btn-warning btn-sm"><i class="fas fa-edit"></i></a>
                                    <a href="#" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
<script type="text/javascript" src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script type="text/javascript" src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>

<script>
$(document).ready(function() {
    $('#templatesTable').DataTable({
        "order": [[ 4, "desc" ]]
    });
});
</script>

<?php
require_once 'includes/footer.php';
?>
