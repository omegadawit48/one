<?php
// includes/header.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$current_page = basename($_SERVER['PHP_SELF']);
$role = $_SESSION['role'] ?? 'staff';

function isActive($page, $current_page) {
    return $page === $current_page ? 'active' : '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BarberStudio Management</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <!-- Phosphor Icons for modern vector icons -->
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
</head>
<body>

<div class="app-container">
    <!-- Sidebar -->
    <aside class="sidebar">
        <div style="margin-bottom: 2rem;">
            <h2 class="text-gradient">BarberStudio</h2>
            <div style="font-size: 0.8rem; color: var(--text-secondary); margin-top: 0.5rem; display: flex; align-items: center; gap: 0.5rem;">
                <div style="width: 8px; height: 8px; border-radius: 50%; background: var(--accent-teal); box-shadow: 0 0 10px var(--accent-teal);"></div>
                <?php echo htmlspecialchars($_SESSION['username']); ?> • <span style="text-transform: capitalize;"><?php echo htmlspecialchars($role); ?></span>
            </div>
        </div>

        <ul class="nav-menu">
            <li class="nav-item">
                <a href="index.php" class="nav-link <?php echo isActive('index.php', $current_page); ?>">
                    <i class="ph ph-squares-four"></i> Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a href="transactions.php" class="nav-link <?php echo isActive('transactions.php', $current_page); ?>">
                    <i class="ph ph-receipt"></i> Transactions
                </a>
            </li>
            
            <?php if ($role === 'admin' || $role === 'manager'): ?>
            <li class="nav-item">
                <a href="services.php" class="nav-link <?php echo isActive('services.php', $current_page); ?>">
                    <i class="ph ph-scissors"></i> Services list
                </a>
            </li>
            <li class="nav-item">
                <a href="users.php" class="nav-link <?php echo isActive('users.php', $current_page); ?>">
                    <i class="ph ph-users"></i> Staff Management
                </a>
            </li>
            <?php endif; ?>
        </ul>

        <div style="margin-top: auto;">
            <a href="logout.php" class="nav-link" style="color: var(--accent-rose);">
                <i class="ph ph-sign-out"></i> Logout
            </a>
        </div>
    </aside>

    <!-- Main Content wrapper -->
    <main class="main-content">
        <!-- Top Header -->
        <header class="header">
            <div>
                <h3><?php echo ucfirst(str_replace('.php', '', $current_page)); ?></h3>
            </div>
            <div style="display: flex; gap: 1rem; align-items: center;">
                <button class="btn btn-primary" onclick="window.location.href='new_transaction.php'">
                    <i class="ph ph-plus-circle"></i> New Sale
                </button>
            </div>
        </header>

        <!-- Page Content Area -->
        <div class="page-content animate-fade-in">
