<?php
require_once 'auth.php';
requireLogin(); // All logged-in users can view items

require_once 'dbconnection.php';

$user = getCurrentUser();

// Get search parameter
$search = $_GET['search'] ?? '';
$sort_by = $_GET['sort'] ?? 'category'; // Default sort by category
$search_param = '';

// Build query with search
if (!empty($search)) {
    $search_param = "%" . $search . "%";
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT * FROM items WHERE item_name LIKE ? OR isles LIKE ? OR shelf_position LIKE ? OR category LIKE ? ORDER BY item_name ASC");
    $stmt->bind_param("ssss", $search_param, $search_param, $search_param, $search_param);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $conn = getDBConnection();
    // Determine sort order
    $order_by = match($sort_by) {
        'name' => 'item_name ASC',
        'price_asc' => 'price ASC',
        'price_desc' => 'price DESC',
        'quantity_asc' => 'quantity ASC',
        'quantity_desc' => 'quantity DESC',
        'category' => 'category ASC, item_name ASC',
        default => 'category ASC, item_name ASC'
    };
    $sql = "SELECT * FROM items ORDER BY " . $order_by;
    $result = $conn->query($sql);
}

// For non-search results, flatten the array instead of grouping by category
$all_items = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $all_items[] = $row;
    }
}
?>
<?php
$page_title = "Products";
require_once 'includes/header.php';
?>

<main class="main-content">
    <div class="page-header">
        <div class="page-title-section">
            <h1 class="page-title">Products</h1>
            <p class="page-subtitle">Manage your store inventory</p>
        </div>
        <div class="page-actions">
            <a href="create.php" class="btn btn-primary">
                <i class="fas fa-plus"></i> Manage Product
            </a>
            <a href="order.php?action=sales" class="btn btn-primary">
                <i class="fas fa-cart-plus"></i> New Sale
            </a>
            <?php if (isManager()): ?>
                <a href="order.php?action=purchase" class="btn btn-secondary">
                    <i class="fas fa-file-invoice"></i> New Purchase
                </a>
            <?php endif; ?>
            <a href="pdf.php<?php echo !empty($search) ? '?search=' . urlencode($search) : ''; ?>" class="btn btn-secondary" target="_blank">
                <i class="fas fa-file-pdf"></i> Generate PDF
            </a>
            <!-- Sort Dropdown -->
            <div style="display: inline-block; position: relative;">
                <form method="GET" action="items.php" style="display: inline;">
                    <?php if (!empty($search)): ?>
                        <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
                    <?php endif; ?>
                    <select name="sort" onchange="this.form.submit()" class="form-input-modern" style="padding: 8px 12px; margin-left: 10px;">
                        <option value="category" <?php echo $sort_by === 'category' ? 'selected' : ''; ?>>Sort by Category</option>
                        <option value="name" <?php echo $sort_by === 'name' ? 'selected' : ''; ?>>Sort by Name (A-Z)</option>
                        <option value="price_asc" <?php echo $sort_by === 'price_asc' ? 'selected' : ''; ?>>Sort by Price (Low to High)</option>
                        <option value="price_desc" <?php echo $sort_by === 'price_desc' ? 'selected' : ''; ?>>Sort by Price (High to Low)</option>
                        <option value="quantity_asc" <?php echo $sort_by === 'quantity_asc' ? 'selected' : ''; ?>>Sort by Quantity (Low to High)</option>
                        <option value="quantity_desc" <?php echo $sort_by === 'quantity_desc' ? 'selected' : ''; ?>>Sort by Quantity (High to Low)</option>
                    </select>
                </form>
            </div>
        </div>
    </div>

    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success">
            <span class="alert-icon"><i class="fas fa-check-circle"></i></span>
            <span>
            <?php 
            if ($_GET['success'] == 'created') echo "Product added successfully!";
            elseif ($_GET['success'] == 'updated') echo "Product updated successfully!";
            elseif ($_GET['success'] == 'deleted') echo "Product deleted successfully!";
            ?>
            </span>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_GET['error']) && $_GET['error'] == 'delete_failed'): ?>
        <div class="alert alert-error">
            <span class="alert-icon"><i class="fas fa-times-circle"></i></span>
            <span>Failed to delete product. Please try again.</span>
        </div>
    <?php endif; ?>

    <section class="search-section">
        <form method="GET" action="items.php" class="search-form-modern">
            <div class="search-input-wrapper">
                <span class="search-icon"><i class="fas fa-search"></i></span>
                <input type="text" name="search" class="search-input-modern" 
                       placeholder="Search products by name, aisle, or shelf position..." 
                       value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit" class="btn btn-primary search-btn">Search</button>
                <?php if (!empty($search)): ?>
                    <a href="items.php" class="btn btn-secondary clear-btn">Clear</a>
                <?php endif; ?>
            </div>
            <?php if (!empty($search)): ?>
                <div class="search-results">
                    <p>Found <strong><?php echo $result->num_rows; ?></strong> result<?php echo $result->num_rows != 1 ? 's' : ''; ?> for "<strong><?php echo htmlspecialchars($search); ?></strong>"</p>
                </div>
            <?php endif; ?>
        </form>
    </section>

    <section class="table-section">
        <div class="table-wrapper">
            <table class="data-table-modern">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Category</th>
                        <th>Product Name</th>
                        <th>Price</th>
                        <th>Quantity</th>
                        <th>Aisle</th>
                        <th>Shelf</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($all_items)): ?>
                        <tr>
                            <td colspan="8" class="empty-state">
                                <div class="empty-message">
                                    <span class="empty-icon"><i class="fas fa-box-open"></i></span>
                                    <p>
                                        <?php if (!empty($search)): ?>
                                            No products found matching your search.
                                            <a href="items.php">View all products</a>
                                        <?php else: ?>
                                            No products found.
                                            <a href="create.php">Add your first product</a>
                                        <?php endif; ?>
                                    </p>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($all_items as $row): ?>
                            <tr>
                                <td class="id-cell">#<?php echo htmlspecialchars($row['item_id']); ?></td>
                                <td class="category-cell">
                                    <span style="display: inline-block; padding: 4px 10px; background: #e8f4f8; border-radius: 12px; font-size: 0.85em;">
                                        <?php echo htmlspecialchars($row['category'] ?: 'Uncategorized'); ?>
                                    </span>
                                </td>
                                <td class="name-cell">
                                    <strong><?php echo htmlspecialchars($row['item_name']); ?></strong>
                                </td>
                                <td class="price-cell">&#8369;<?php echo number_format($row['price'], 2); ?></td>
                                <td class="quantity-cell">
                                    <span class="quantity-badge <?php echo $row['quantity'] < 10 ? 'low-stock' : ''; ?>">
                                        <?php echo htmlspecialchars($row['quantity']); ?>
                                    </span>
                                </td>
                                <td class="aisle-cell"><?php echo !empty($row['isles']) ? htmlspecialchars($row['isles']) : '<span style="color: #999;">N/A</span>'; ?></td>
                                <td class="shelf-cell"><?php echo !empty($row['shelf_position']) ? htmlspecialchars($row['shelf_position']) : '<span style="color: #999;">N/A</span>'; ?></td>
                                <td class="actions-cell">
                                    <div class="action-buttons">
                                        <a href="view.php?id=<?php echo $row['item_id']; ?>" class="btn btn-view" title="View Details"><i class="fas fa-eye"></i></a>
                                        <a href="order.php?action=sales&item_id=<?php echo $row['item_id']; ?>" class="btn btn-sale" title="Create Sale"><i class="fas fa-cart-plus"></i></a>
                                        <?php if (isManager()): ?>
                                            <a href="update.php?id=<?php echo $row['item_id']; ?>" class="btn btn-edit" title="Edit Product"><i class="fas fa-edit"></i></a>
                                            <a href="delete.php?id=<?php echo $row['item_id']; ?>" 
                                               class="btn btn-delete" 
                                               onclick="return confirm('Are you sure you want to delete this product?');"
                                               title="Delete Product"><i class="fas fa-trash-alt"></i></a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>

<?php 
if (isset($stmt)) {
    $stmt->close();
}
closeDBConnection($conn); 
require_once 'includes/footer.php'; 
?>

