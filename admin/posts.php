<?php
// admin/posts.php
session_start();
require_once '../config/database-config.php';
require_once 'includes/admin-auth.php'; // Ensure admin is logged in

$pageTitle = 'Manage Posts - Admin Panel';

// Fetch all posts from the database, joining with the customers table to get customer name
try {
    $stmt = $db->query("
        SELECT 
            p.id, 
            p.post_content, 
            p.status, 
            p.created_at,
            c.name as customer_name,
            c.id as customer_id
        FROM customer_generated_posts p
        JOIN customers c ON p.customer_id = c.id
        ORDER BY p.created_at DESC
    ");
    $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Failed to fetch posts: " . $e->getMessage());
    $error = "Failed to load post data. Please check the logs.";
    $posts = [];
}

// Include admin header
require_once 'includes/header.php';
?>

<div class="container-fluid">
    <h1 class="h3 mb-4 text-gray-800">Manage Posts</h1>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">All Generated Posts (<?php echo count($posts); ?>)</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered" id="postsTable" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Customer</th>
                            <th>Post Content (Snippet)</th>
                            <th>Status</th>
                            <th>Created Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($posts as $post): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($post['id']); ?></td>
                                <td>
                                    <a href="customer-details.php?id=<?php echo $post['customer_id']; ?>">
                                        <?php echo htmlspecialchars($post['customer_name']); ?>
                                    </a>
                                </td>
                                <td><?php echo htmlspecialchars(substr($post['post_content'], 0, 100)); ?>...</td>
                                <td>
                                    <?php
                                    $statusClass = 'secondary';
                                    if ($post['status'] === 'published') {
                                        $statusClass = 'success';
                                    } elseif ($post['status'] === 'scheduled') {
                                        $statusClass = 'info';
                                    } elseif ($post['status'] === 'failed') {
                                        $statusClass = 'danger';
                                    }
                                    ?>
                                    <span class="badge bg-<?php echo $statusClass; ?>">
                                        <?php echo htmlspecialchars(ucfirst($post['status'])); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M d, Y H:i', strtotime($post['created_at'])); ?></td>
                                <td>
                                    <a href="post-details.php?id=<?php echo $post['id']; ?>" class="btn btn-info btn-sm" title="View Post">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="edit-post.php?id=<?php echo $post['id']; ?>" class="btn btn-warning btn-sm" title="Edit Post">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <button class="btn btn-danger btn-sm" onclick="deletePost(<?php echo $post['id']; ?>)" title="Delete Post">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add DataTables for sorting and searching -->
<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
<script type="text/javascript" src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script type="text/javascript" src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>

<script>
$(document).ready(function() {
    $('#postsTable').DataTable({
        "order": [[ 4, "desc" ]] // Default sort by Created Date descending
    });
});

function deletePost(postId) {
    if (confirm('Are you sure you want to delete this post? This action cannot be undone.')) {
        // AJAX call to delete the post would go here
        console.log('Deleting post with ID:', postId);
        alert('Functionality to delete post ' + postId + ' is not yet implemented.');
        // window.location.href = 'delete-post.php?id=' + postId;
    }
}
</script>

<?php
// Include admin footer
require_once 'includes/footer.php';
?>
