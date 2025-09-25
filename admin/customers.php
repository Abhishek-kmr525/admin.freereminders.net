<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// admin/customers.php
session_start();
require_once '../config/database-config.php';
require_once 'includes/admin-auth.php'; // Ensure admin is logged in

$pageTitle = 'Manage Customers - Admin Panel';

// Fetch all customers from the database
try {
    $stmt = $db->query("
        SELECT id, name, email, country, status, subscription_status, trial_ends_at, created_at 
        FROM customers 
        ORDER BY created_at DESC
    ");
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Failed to fetch customers: " . $e->getMessage());
    $error = "Failed to load customer data. Please check the logs.";
    $customers = [];
}

// Include admin header
require_once 'includes/header.php';
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Manage Customers</h1>
        <a href="#" class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm">
            <i class="fas fa-plus fa-sm text-white-50"></i> Add New Customer
        </a>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">All Customers (<?php echo count($customers); ?>)</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered" id="customersTable" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Country</th>
                            <th>Status</th>
                            <th>Subscription</th>
                            <th>Joined Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($customers as $customer): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($customer['id']); ?></td>
                                <td><?php echo htmlspecialchars($customer['name']); ?></td>
                                <td><?php echo htmlspecialchars($customer['email']); ?></td>
                                <td>
                                    <span class="badge bg-secondary">
                                        <?php echo htmlspecialchars(strtoupper($customer['country'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php
                                    $statusClass = 'secondary';
                                    if ($customer['status'] === 'active') {
                                        $statusClass = 'success';
                                    } elseif ($customer['status'] === 'inactive') {
                                        $statusClass = 'warning';
                                    }
                                    ?>
                                    <span class="badge bg-<?php echo $statusClass; ?>">
                                        <?php echo htmlspecialchars(ucfirst($customer['status'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php
                                    $subStatusClass = 'info';
                                    if ($customer['subscription_status'] === 'active') {
                                        $subStatusClass = 'success';
                                    } elseif (in_array($customer['subscription_status'], ['expired', 'cancelled'])) {
                                        $subStatusClass = 'danger';
                                    }
                                    ?>
                                    <span class="badge bg-<?php echo $subStatusClass; ?>">
                                        <?php echo htmlspecialchars(ucfirst($customer['subscription_status'])); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($customer['created_at'])); ?></td>
                                <td>
                                    <a href="customer-details.php?id=<?php echo $customer['id']; ?>" class="btn btn-info btn-sm" title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="edit-customer.php?id=<?php echo $customer['id']; ?>" class="btn btn-warning btn-sm" title="Edit Customer">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <button class="btn btn-danger btn-sm" onclick="deleteCustomer(<?php echo $customer['id']; ?>)" title="Delete Customer">
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
    $('#customersTable').DataTable({
        "order": [[ 6, "desc" ]] // Default sort by Joined Date descending
    });
});

function deleteCustomer(customerId) {
    if (confirm('Are you sure you want to delete this customer? This action cannot be undone.')) {
        // Here you would typically make an AJAX call to a delete script
        // For now, we'll just log it and show an alert.
        console.log('Deleting customer with ID:', customerId);
        alert('Functionality to delete customer ' + customerId + ' is not yet implemented.');
        // window.location.href = 'delete-customer.php?id=' + customerId;
    }
}
</script>

<?php
// Include admin footer
require_once 'includes/footer.php';
?>
