<?php
if (!isset($page_title)) {
    $page_title = "Store Management System";
}
$user = isset($user) ? $user : (function_exists('getCurrentUser') ? getCurrentUser() : null);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Store Management System for inventory tracking">
    <title><?php echo htmlspecialchars($page_title); ?> - INCONVINIENCE STORE</title>
    <link rel="stylesheet" href="style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
</head>
<body>
    <?php if ($user): ?>
    <nav class="main-nav">
        <div class="nav-container">
            <div class="nav-brand">
                <h1 class="brand-title">INCONVINIENCE STORE</h1>
            </div>
            <div class="nav-menu">
                <a href="home.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'home.php' ? 'active' : ''; ?>">
                    <span class="nav-icon"><i class="fas fa-chart-line"></i></span>
                    <span>Dashboard</span>
                </a>
                <a href="items.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'items.php' ? 'active' : ''; ?>">
                    <span class="nav-icon"><i class="fas fa-boxes"></i></span>
                    <span>Products</span>
                </a>
                <a href="order.php?action=dashboard" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'order.php' ? 'active' : ''; ?>">
                    <span class="nav-icon"><i class="fas fa-clipboard-list"></i></span>
                    <span>Orders</span>
                </a>
                <a href="create.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'create.php' ? 'active' : ''; ?>">
                    <span class="nav-icon"><i class="fas fa-plus-circle"></i></span>
                    <span>Manage Product</span>
                </a>
            </div>
            <div class="nav-user">
                <div class="user-menu">
                    <button type="button" class="user-avatar profile-toggle" title="View Profile">
                        <?php echo strtoupper(substr($user['full_name'], 0, 1)); ?>
                    </button>
                    <div class="user-details">
                        <span class="user-name"><?php echo htmlspecialchars($user['full_name']); ?></span>
                        <span class="user-role"><?php echo ucfirst(htmlspecialchars($user['role'])); ?></span>
                    </div>
                    <a href="logout.php" class="logout-btn" title="Logout"><i class="fas fa-sign-out-alt"></i></a>
                </div>
            </div>
        </div>
    </nav>
    <!-- Global profile overlay (hidden by default, toggled via JS) -->
    <div id="profileOverlay" class="profile-overlay" style="display:none;">
        <div class="action-card profile-card">
            <div class="action-icon"><i class="fas fa-user-circle"></i></div>
            <h3>Account Details</h3>
            <div class="profile-details">
                <div class="profile-item">
                    <span class="profile-label">Username:</span>
                    <span class="profile-value">
                        <?php echo htmlspecialchars($user['username']); ?>
                    </span>
                </div>
                <div class="profile-item">
                    <span class="profile-label">Full Name:</span>
                    <span class="profile-value">
                        <?php echo htmlspecialchars($user['full_name']); ?>
                    </span>
                </div>
                <div class="profile-item">
                    <span class="profile-label">Role:</span>
                    <span class="profile-value role-badge">
                        <?php echo ucfirst(htmlspecialchars($user['role'])); ?>
                    </span>
                </div>
            </div>
            <div class="form-actions-modern" style="border-top:none; padding-top:16px; margin-top:8px;">
                <button type="button" id="profileOverlayClose" class="btn btn-secondary" style="width:100%; text-align:center;">
                    Close
                </button>
            </div>
        </div>
    </div>
    <?php endif; ?>

