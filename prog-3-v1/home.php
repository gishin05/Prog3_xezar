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

// Get inventory replenishment data (items at or below 50% of some threshold, for demo using 20 as threshold)
$replenishment_data = [];
$result = $conn->query("SELECT item_name, quantity FROM items WHERE quantity <= 20 ORDER BY quantity ASC LIMIT 10");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $replenishment_data[] = $row;
    }
}

// Get demand forecast data (simulated from customer orders)
$demand_data = [];
$forecast_data = [];
if ($has_order_tables) {
    $result = $conn->query("
        SELECT i.item_name, COUNT(co.order_id) as demand_count 
        FROM customer_orders co 
        LEFT JOIN items i ON co.item_id = i.item_id 
        WHERE i.item_name IS NOT NULL
        GROUP BY co.item_id, i.item_name 
        ORDER BY demand_count DESC 
        LIMIT 8
    ");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $demand_data[] = $row;
            $forecast_data[] = [
                'name' => $row['item_name'],
                'actual' => $row['demand_count'],
                'forecast' => $row['demand_count'] + rand(-2, 5)
            ];
        }
    }
}

closeDBConnection($conn);
?>
<?php
$page_title = "Dashboard";
require_once 'includes/header.php';
?>

<!-- Chart.js for visualizations -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js"></script>

<style>
.dashboard-container {
    display: flex;
    gap: 20px;
    max-width: 1400px;
    margin: 20px auto;
}

.sidebar-panel {
    flex: 0 0 280px;
    background: #f8f9fa;
    border-radius: 8px;
    padding: 20px;
    border: 1px solid #e0e0e0;
    height: fit-content;
    position: sticky;
    top: 80px;
}

.main-content-area {
    flex: 1;
    min-width: 0;
}

.sidebar-section {
    margin-bottom: 24px;
}

.sidebar-section:not(:last-child) {
    padding-bottom: 20px;
    border-bottom: 1px solid #ddd;
}

.sidebar-stat {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 12px;
    padding: 10px;
    background: white;
    border-radius: 6px;
    border-left: 4px solid #007bff;
}

.sidebar-stat.warning {
    border-left-color: #ff9800;
}

.sidebar-stat.success {
    border-left-color: #4caf50;
}

.sidebar-stat.info {
    border-left-color: #2196f3;
}

.sidebar-stat-icon {
    font-size: 20px;
    color: #666;
}

.sidebar-stat-content {
    flex: 1;
}

.sidebar-stat-label {
    font-size: 0.75em;
    color: #666;
    text-transform: uppercase;
    margin: 0;
}

.sidebar-stat-value {
    font-size: 1.3em;
    font-weight: bold;
    margin: 2px 0 0 0;
    color: #333;
}

.action-cards-compact {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.action-card-compact {
    padding: 12px;
    background: white;
    border: 1px solid #ddd;
    border-radius: 6px;
    text-decoration: none;
    color: #333;
    font-size: 0.9em;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    gap: 10px;
}

.action-card-compact:hover {
    background: #f0f0f0;
    border-color: #007bff;
}

.action-card-compact i {
    color: #007bff;
    font-size: 16px;
}

.chart-container {
    position: relative;
    height: 300px;
    margin-bottom: 30px;
}

.chart-container.small {
    height: 250px;
}

.forecast-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 15px;
}

.forecast-table th,
.forecast-table td {
    padding: 10px;
    text-align: left;
    border-bottom: 1px solid #ddd;
}

.forecast-table th {
    background: #f5f5f5;
    font-weight: 600;
    color: #333;
}

.forecast-table tr:hover {
    background: #f9f9f9;
}

@media (max-width: 1024px) {
    .dashboard-container {
        flex-direction: column;
    }
    
    .sidebar-panel {
        flex: none;
        position: static;
    }
}
</style>

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

    <div class="dashboard-container">
        <!-- Left Sidebar with Stats and Quick Actions -->
        <div class="sidebar-panel">
            <div class="sidebar-section">
                <h3 style="margin: 0 0 12px 0; font-size: 0.9em; text-transform: uppercase; color: #666;">Store Overview</h3>
                
                <div class="sidebar-stat">
                    <div class="sidebar-stat-icon"><i class="fas fa-boxes"></i></div>
                    <div class="sidebar-stat-content">
                        <p class="sidebar-stat-label">Total Products</p>
                        <p class="sidebar-stat-value"><?php echo number_format($stats['total_items']); ?></p>
                    </div>
                </div>
                
                <div class="sidebar-stat warning">
                    <div class="sidebar-stat-icon"><i class="fas fa-exclamation-triangle"></i></div>
                    <div class="sidebar-stat-content">
                        <p class="sidebar-stat-label">Low Stock</p>
                        <p class="sidebar-stat-value"><?php echo number_format($stats['low_stock']); ?></p>
                    </div>
                </div>
                
                <div class="sidebar-stat success">
                    <div class="sidebar-stat-icon"><i class="fas fa-peso-sign"></i></div>
                    <div class="sidebar-stat-content">
                        <p class="sidebar-stat-label">Inventory Value</p>
                        <p class="sidebar-stat-value">₱<?php echo number_format($stats['total_value'], 0); ?></p>
                    </div>
                </div>
                
                <?php if ($has_order_tables): ?>
                <div class="sidebar-stat info">
                    <div class="sidebar-stat-icon"><i class="fas fa-cart-arrow-down"></i></div>
                    <div class="sidebar-stat-content">
                        <p class="sidebar-stat-label">P.O. Orders</p>
                        <p class="sidebar-stat-value"><?php echo number_format($stats['total_purchase_orders']); ?></p>
                    </div>
                </div>
                
                <div class="sidebar-stat info">
                    <div class="sidebar-stat-icon"><i class="fas fa-shopping-cart"></i></div>
                    <div class="sidebar-stat-content">
                        <p class="sidebar-stat-label">C.O. Orders</p>
                        <p class="sidebar-stat-value"><?php echo number_format($stats['total_customer_orders']); ?></p>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="sidebar-section">
                <h3 style="margin: 0 0 12px 0; font-size: 0.9em; text-transform: uppercase; color: #666;">Quick Actions</h3>
                <div class="action-cards-compact">
                    <a href="items.php" class="action-card-compact">
                        <i class="fas fa-warehouse"></i>
                        <span>View Inventory</span>
                    </a>
                    <a href="order.php" class="action-card-compact">
                        <i class="fas fa-clipboard-list"></i>
                        <span>View Orders</span>
                    </a>
                    <a href="create.php" class="action-card-compact">
                        <i class="fas fa-plus-circle"></i>
                        <span>Manage Products</span>
                    </a>
                </div>
            </div>
        </div>

        <!-- Main Content Area -->
        <div class="main-content-area">
            <!-- Inventory Replenishment Section -->
            <section style="background: white; padding: 20px; border-radius: 8px; margin-bottom: 24px; border: 1px solid #e0e0e0;">
                <h2 style="margin-top: 0;">Inventory Replenishment Level</h2>
                <p style="color: #666; margin-bottom: 20px;">Products at or below 20 units (sorted by urgency)</p>
                
                <?php if (!empty($replenishment_data)): ?>
                <div class="chart-container">
                    <canvas id="replenishmentChart"></canvas>
                </div>
                <script>
                const replenishmentCtx = document.getElementById('replenishmentChart').getContext('2d');
                new Chart(replenishmentCtx, {
                    type: 'bar',
                    data: {
                        labels: <?php echo json_encode(array_map(function($item) { return substr($item['item_name'], 0, 15); }, $replenishment_data)); ?>,
                        datasets: [{
                            label: 'Current Stock',
                            data: <?php echo json_encode(array_map(function($item) { return $item['quantity']; }, $replenishment_data)); ?>,
                            backgroundColor: [
                                '#ff6b6b', '#ee5a6f', '#ff8c42', '#ffa502', '#ffb347',
                                '#ffc857', '#e9c46a', '#2a9d8f', '#264653', '#e76f51'
                            ],
                            borderRadius: 6,
                            borderSkipped: false,
                        }]
                    },
                    options: {
                        indexAxis: 'y',
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false }
                        },
                        scales: {
                            x: {
                                beginAtZero: true,
                                ticks: { stepSize: 5 }
                            }
                        }
                    }
                });
                </script>
                <?php else: ?>
                <div style="padding: 30px; text-align: center; color: #999;">
                    <i class="fas fa-check-circle" style="font-size: 2em; margin-bottom: 10px;"></i>
                    <p>All products are well stocked (above 20 units)</p>
                </div>
                <?php endif; ?>
            </section>

            <!-- Demand Forecast Section -->
            <section style="background: white; padding: 20px; border-radius: 8px; border: 1px solid #e0e0e0;">
                <h2 style="margin-top: 0;">Demand Forecast & Analytics</h2>
                
                <?php if (!empty($demand_data)): ?>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                    <!-- Demand Distribution Pie Chart -->
                    <div class="chart-container small">
                        <canvas id="demandPieChart"></canvas>
                    </div>
                    
                    <!-- Forecast Bar Chart -->
                    <div class="chart-container small">
                        <canvas id="forecastBarChart"></canvas>
                    </div>
                </div>

                <script>
                // Pie Chart - Demand Distribution
                const pieCtx = document.getElementById('demandPieChart').getContext('2d');
                new Chart(pieCtx, {
                    type: 'doughnut',
                    data: {
                        labels: <?php echo json_encode(array_map(function($item) { return substr($item['item_name'], 0, 12); }, $demand_data)); ?>,
                        datasets: [{
                            data: <?php echo json_encode(array_map(function($item) { return $item['demand_count']; }, $demand_data)); ?>,
                            backgroundColor: [
                                '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF',
                                '#FF9F40', '#FF6384', '#C9CBCF'
                            ],
                            borderColor: '#fff',
                            borderWidth: 2
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: { font: { size: 11 }, padding: 10 }
                            },
                            tooltip: { callbacks: { label: function(context) { return context.parsed + ' orders'; } } }
                        }
                    }
                });

                // Bar Chart - Actual vs Forecast
                const barCtx = document.getElementById('forecastBarChart').getContext('2d');
                new Chart(barCtx, {
                    type: 'bar',
                    data: {
                        labels: <?php echo json_encode(array_map(function($item) { return substr($item['name'], 0, 12); }, $forecast_data)); ?>,
                        datasets: [
                            {
                                label: 'Actual Demand',
                                data: <?php echo json_encode(array_map(function($item) { return $item['actual']; }, $forecast_data)); ?>,
                                backgroundColor: '#4CAF50',
                                borderRadius: 4
                            },
                            {
                                label: 'Forecast',
                                data: <?php echo json_encode(array_map(function($item) { return $item['forecast']; }, $forecast_data)); ?>,
                                backgroundColor: '#2196F3',
                                borderRadius: 4
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { position: 'bottom', labels: { font: { size: 11 }, padding: 10 } }
                        },
                        scales: {
                            y: { beginAtZero: true }
                        }
                    }
                });
                </script>

                <!-- Forecast Table -->
                <h3 style="margin-top: 30px;">Demand Forecast Details</h3>
                <table class="forecast-table">
                    <thead>
                        <tr>
                            <th>Product Name</th>
                            <th>Actual Demand</th>
                            <th>Forecasted Demand</th>
                            <th>Change</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($forecast_data as $item): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($item['name']); ?></strong></td>
                            <td><?php echo $item['actual']; ?> orders</td>
                            <td><?php echo $item['forecast']; ?> orders</td>
                            <td style="color: <?php echo ($item['forecast'] > $item['actual']) ? '#4CAF50' : '#FF9800'; ?>; font-weight: bold;">
                                <?php echo ($item['forecast'] > $item['actual']) ? '↑' : '↓'; ?>
                                <?php echo abs($item['forecast'] - $item['actual']); ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div style="padding: 30px; text-align: center; color: #999;">
                    <i class="fas fa-chart-bar" style="font-size: 2em; margin-bottom: 10px;"></i>
                    <p>No demand data available yet. Create some customer orders to see analytics.</p>
                </div>
                <?php endif; ?>
            </section>
        </div>
    </div>
</main>

<?php require_once 'includes/footer.php'; ?>

