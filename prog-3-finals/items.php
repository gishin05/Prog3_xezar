<?php
require_once 'auth.php';
requireLogin(); // All logged-in users can view items

require_once 'dbconnection.php';

$user = getCurrentUser();

// Get search parameter
$search = $_GET['search'] ?? '';
$search_param = '';

// Build query with search
if (!empty($search)) {
    $search_param = "%" . $search . "%";
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT * FROM items WHERE item_name LIKE ? OR isles LIKE ? OR shelf_position LIKE ? ORDER BY item_id DESC");
    $stmt->bind_param("sss", $search_param, $search_param, $search_param);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $conn = getDBConnection();
    $sql = "SELECT * FROM items ORDER BY item_id DESC";
    $result = $conn->query($sql);
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
            <?php if (isManager()): ?>
                <a href="create.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Add New Product
                </a>
            <?php endif; ?>
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
                        <th>Product Name</th>
                        <th>Price</th>
                        <th>Quantity</th>
                        <th>Aisle</th>
                        <th>Shelf</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result->num_rows > 0): ?>
                        <?php while($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td class="id-cell">#<?php echo htmlspecialchars($row['item_id']); ?></td>
                                <td class="name-cell">
                                    <strong><?php echo htmlspecialchars($row['item_name']); ?></strong>
                                </td>
                                <td class="price-cell">&#8369;<?php echo number_format($row['price'], 2); ?></td>
                                <td class="quantity-cell">
                                    <span class="quantity-badge <?php echo $row['quantity'] < 10 ? 'low-stock' : ''; ?>">
                                        <?php echo htmlspecialchars($row['quantity']); ?>
                                    </span>
                                </td>
                                <td class="aisle-cell"><?php echo htmlspecialchars($row['isles']); ?></td>
                                <td class="shelf-cell"><?php echo htmlspecialchars($row['shelf_position']); ?></td>
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
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="empty-state">
                                <div class="empty-message">
                                    <span class="empty-icon"><i class="fas fa-box-open"></i></span>
                                    <p>
                                        <?php if (!empty($search)): ?>
                                            No products found matching your search.
                                            <a href="items.php">View all products</a>
                                        <?php else: ?>
                                            No products found.
                                            <?php if (isManager()): ?>
                                                <a href="create.php">Add your first product</a>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </p>
                                </div>
                            </td>
                        </tr>
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

