<?php
require_once 'auth.php';
requireLogin();

$user = getCurrentUser();
require_once 'dbconnection.php';

// Get statistics
$conn = getDBConnection();
$stats = [];

// Total items count
$result = $conn->query("SELECT COUNT(*) as total FROM items");
$stats['total_items'] = $result->fetch_assoc()['total'];

// Low stock items (quantity < 10)
$result = $conn->query("SELECT COUNT(*) as total FROM items WHERE quantity < 10");
$stats['low_stock'] = $result->fetch_assoc()['total'];

// Total inventory value
$result = $conn->query("SELECT SUM(price * quantity) as total FROM items");
$stats['total_value'] = $result->fetch_assoc()['total'] ?? 0;

// Order statistics (total purchase and customer orders)
$stats['total_purchase_orders'] = 0;
$stats['total_customer_orders'] = 0;

// Check if order tables exist first
$tables_check = $conn->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = 'store_db' AND TABLE_NAME IN ('purchase_orders','customer_orders')");
$has_order_tables = ($tables_check && $tables_check->num_rows > 0);

if ($has_order_tables) {
    // Get total purchase orders count
    $result = $conn->query("SELECT COUNT(*) as total FROM purchase_orders");
    if ($result) {
        $stats['total_purchase_orders'] = intval($result->fetch_assoc()['total']);
    }

    // Get total customer orders count
    $result = $conn->query("SELECT COUNT(*) as total FROM customer_orders");
    if ($result) {
        $stats['total_customer_orders'] = intval($result->fetch_assoc()['total']);
    }
}

closeDBConnection($conn);
?>
<?php
$page_title = "Dashboard";
require_once 'includes/header.php';
?>

<main class="main-content">
    <div class="page-header">
        <div class="page-title-section">
            <h1 class="page-title">Dashboard</h1>
            <p class="page-subtitle">Welcome back, <?php echo htmlspecialchars($user['full_name']); ?>! Here's your store overview.</p>
        </div>
    </div>

    <?php if (isset($_GET['error']) && $_GET['error'] == 'access_denied'): ?>
        <div class="alert alert-error">
            <strong>Access Denied:</strong> Manager privileges required for this action.
        </div>
    <?php endif; ?>

    <section class="stats-section">
        <h2 class="section-title">Store Statistics</h2>
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-boxes"></i></div>
                <div class="stat-content">
                    <h3 class="stat-label">Total Products</h3>
                    <p class="stat-value"><?php echo number_format($stats['total_items']); ?></p>
                    <span class="stat-description">products in stock</span>
                </div>
            </div>
            
            <div class="stat-card warning">
                <div class="stat-icon"><i class="fas fa-exclamation-triangle"></i></div>
                <div class="stat-content">
                    <h3 class="stat-label">Low Stock Alert</h3>
                    <p class="stat-value"><?php echo number_format($stats['low_stock']); ?></p>
                    <span class="stat-description">products need restocking</span>
                </div>
            </div>
            
            <div class="stat-card success">
                <div class="stat-icon"><i class="fas fa-peso-sign"></i></div>
                <div class="stat-content">
                    <h3 class="stat-label">Inventory Value</h3>
                    <p class="stat-value">&#8369;<?php echo number_format($stats['total_value'], 2); ?></p>
                    <span class="stat-description">total inventory value</span>
                </div>
            </div>
            
            <?php if ($has_order_tables): ?>
            <div class="stat-card info">
                <div class="stat-icon"><i class="fas fa-cart-arrow-down"></i></div>
                <div class="stat-content">
                    <h3 class="stat-label">Purchase Orders</h3>
                    <p class="stat-value"><?php echo number_format($stats['total_purchase_orders']); ?></p>
                    <span class="stat-description">total purchase orders</span>
                </div>
            </div>
            
            <div class="stat-card info">
                <div class="stat-icon"><i class="fas fa-cart-plus"></i></div>
                <div class="stat-content">
                    <h3 class="stat-label">Customer Orders</h3>
                    <p class="stat-value"><?php echo number_format($stats['total_customer_orders']); ?></p>
                    <span class="stat-description">total customer orders</span>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </section>

    <section class="actions-section">
        <h2 class="section-title">Quick Actions</h2>
        <div class="action-cards">
            <a href="items.php" class="action-card">
                <div class="action-icon"><i class="fas fa-warehouse"></i></div>
                <h3>View Inventory</h3>
                <p>Browse and search through all products in the store</p>
                <span class="action-link">View Products →</span>
            </a>

            <a href="order.php" class="action-card">
                <div class="action-icon"><i class="fas fa-clipboard-list"></i></div>
                <h3>View Orders</h3>
                <p>Manage purchase and customer orders in one place</p>
                <span class="action-link">View Orders →</span>
            </a>
            
            <?php if (isManager()): ?>
            <a href="create.php" class="action-card">
                <div class="action-icon"><i class="fas fa-plus-circle"></i></div>
                <h3>Add New Product</h3>
                <p>Add a new product to the store inventory</p>
                <span class="action-link">Add Product →</span>
            </a>
            <?php endif; ?>
            
            <div class="action-card profile-card">
                <div class="action-icon"><i class="fas fa-user-circle"></i></div>
                <h3>Profile Information</h3>
                <div class="profile-details">
                    <div class="profile-item">
                        <span class="profile-label">Username:</span>
                        <span class="profile-value"><?php echo htmlspecialchars($user['username']); ?></span>
                    </div>
                    <div class="profile-item">
                        <span class="profile-label">Full Name:</span>
                        <span class="profile-value"><?php echo htmlspecialchars($user['full_name']); ?></span>
                    </div>
                    <div class="profile-item">
                        <span class="profile-label">Role:</span>
                        <span class="profile-value role-badge"><?php echo ucfirst(htmlspecialchars($user['role'])); ?></span>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<?php require_once 'includes/footer.php'; ?>

