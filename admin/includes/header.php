<?php
// admin/includes/header.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) : 'Admin Panel'; ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css" rel="stylesheet">
    
    <!-- Custom Admin CSS -->
    <style>
        body {
            display: flex;
            background-color: #f8f9fa;
        }
        #sidebar {
            min-width: 250px;
            max-width: 250px;
            background: #343a40;
            color: #fff;
            transition: all 0.3s;
        }
        #sidebar.toggled {
            margin-left: -250px;
        }
        #sidebar .nav-link {
            color: #adb5bd;
            padding: 10px 20px;
        }
        #sidebar .nav-link:hover, #sidebar .nav-link.active {
            color: #fff;
            background: #495057;
        }
        #sidebar .sidebar-header {
            padding: 20px;
            background: #212529;
        }
        #content-wrapper {
            width: 100%;
            padding: 20px;
            min-height: 100vh;
            transition: all 0.3s;
        }
    </style>
</head>
<body>
    <div id="sidebar">
        <div class="sidebar-header text-center">
            <h3>Admin Panel</h3>
        </div>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link" href="dashboard.php">
                    <i class="fas fa-fw fa-tachometer-alt"></i> Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link active" href="customers.php">
                    <i class="fas fa-fw fa-users"></i> Customers
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="automations.php">
                    <i class="fas fa-fw fa-robot"></i> Automations
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="api-settings.php">
                    <i class="fas fa-fw fa-cogs"></i> API Settings
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="credentials.php">
                    <i class="fas fa-fw fa-key"></i> Credentials
                </a>
            </li>
        </ul>
    </div>

    <div id="content-wrapper">
        <nav class="navbar navbar-expand-lg navbar-light bg-light mb-4">
            <div class="container-fluid">
                <button type="button" id="sidebarCollapse" class="btn btn-info">
                    <i class="fas fa-align-left"></i>
                </button>
                <div class="collapse navbar-collapse">
                    <ul class="navbar-nav ms-auto">
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-user fa-fw"></i> <?php echo htmlspecialchars($_SESSION['admin_username'] ?? 'Admin'); ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                                <li><a class="dropdown-item" href="profile.php">Profile</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="logout.php">Logout</a></li>
                            </ul>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>
        
        <!-- Start of Page Content -->
        <div class="container-fluid">
