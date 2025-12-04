<?php
require_once 'auth.php';
requireLogin(); // All logged-in users (managers and employees) can create items

require_once 'dbconnection.php';

$error = '';
$success = '';

// Get list of existing products
$conn = getDBConnection();
// Ensure items table has a cost_price column (migrate existing price -> cost_price)
function ensure_cost_price_column($conn)
{
    $col = $conn->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'items' AND COLUMN_NAME = 'cost_price' LIMIT 1");
    if ($col && $col->num_rows > 0) return true;
    // Add column and populate from existing price
    if ($conn->query("ALTER TABLE items ADD COLUMN cost_price DECIMAL(10,2) NOT NULL DEFAULT 0") === TRUE) {
        $conn->query("UPDATE items SET cost_price = price");
        return true;
    }
    return false;
}
ensure_cost_price_column($conn);
$existing_products = [];
$result = $conn->query("SELECT item_id, item_name, category FROM items ORDER BY item_name");
if ($result) {
    $existing_products = $result->fetch_all(MYSQLI_ASSOC);
}

// Get selected product data
$selected_product = null;
$selected_item_id = $_POST['item_id'] ?? $_GET['item_id'] ?? null;
if ($selected_item_id) {
    $stmt = $conn->prepare("SELECT * FROM items WHERE item_id = ?");
    $stmt->bind_param("i", $selected_item_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $selected_product = $result->fetch_assoc();
    $stmt->close();
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $item_id = trim($_POST['item_id'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $price = trim($_POST['price'] ?? '');
    $quantity = trim($_POST['quantity'] ?? '');
    $isles = trim($_POST['isles'] ?? '');
    $shelf_position = trim($_POST['shelf_position'] ?? '');
    
    // Validation
            $cost_price = trim($_POST['cost_price'] ?? '');
            if (empty($item_id) || empty($category) || empty($price) || empty($quantity) || empty($isles) || empty($shelf_position)) {
        $error = "All fields are required!";
    } elseif (!is_numeric($price) || $price <= 0) {
        $error = "Price must be a positive number!";
    } elseif (!is_numeric($quantity) || $quantity < 0) {
        $error = "Quantity must be a non-negative number!";
    } else {
        // Get product name from selected item
        $stmt = $conn->prepare("SELECT item_name FROM items WHERE item_id = ?");
        $stmt->bind_param("i", $item_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $item = $result->fetch_assoc();
        $stmt->close();
        
        if (!$item) {
            $error = "Invalid product selected!";
        } else {
            $item_name = $item['item_name'];
            // Update price (sales) and optionally cost_price (product value) if provided
            if ($cost_price !== '') {
                $stmt = $conn->prepare("UPDATE items SET category = ?, price = ?, cost_price = ?, quantity = ?, isles = ?, shelf_position = ? WHERE item_id = ?");
                $stmt->bind_param("sddissi", $category, $price, $cost_price, $quantity, $isles, $shelf_position, $item_id);
            } else {
                $stmt = $conn->prepare("UPDATE items SET category = ?, price = ?, quantity = ?, isles = ?, shelf_position = ? WHERE item_id = ?");
                $stmt->bind_param("sdissi", $category, $price, $quantity, $isles, $shelf_position, $item_id);
            }
            
            if ($stmt->execute()) {
                $stmt->close();
                closeDBConnection($conn);
                header("Location: items.php?success=created");
                exit();
            } else {
                $error = "Error: " . $stmt->error;
            }
            $stmt->close();
        }
    }
}

closeDBConnection($conn);
?>
<?php
$page_title = "Manage Product";
require_once 'includes/header.php';
?>

<main class="main-content">
    <div class="page-header">
        <div class="page-title-section">
            <h1 class="page-title">Manage Product</h1>
            <p class="page-subtitle">Create a new product entry in the inventory</p>
        </div>
        <div class="page-actions">
            <a href="items.php" class="btn btn-secondary">← Back to Products</a>
        </div>
    </div>

    <section class="form-section">
        <div class="form-container-modern">
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <span class="alert-icon"><i class="fas fa-exclamation-triangle"></i></span>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" class="product-form">
                <div class="form-grid">
                    <div class="form-group-modern">
                        <label for="item_id" class="form-label">
                            <span class="label-icon"><i class="fas fa-tag"></i></span>
                            Product Name
                            <span class="required">*</span>
                        </label>
                        <select id="item_id" 
                                name="item_id" 
                                class="form-input-modern"
                                required
                                onchange="loadProductData()">
                            <option value="">-- Select a product --</option>
                            <?php foreach ($existing_products as $prod): ?>
                                <option value="<?php echo $prod['item_id']; ?>" <?php echo ($selected_product && $selected_product['item_id'] == $prod['item_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($prod['item_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group-modern">
                        <label for="category" class="form-label">
                            <span class="label-icon"><i class="fas fa-list"></i></span>
                            Category
                            <span class="required">*</span>
                        </label>
                        <input type="text" 
                               id="category" 
                               name="category" 
                               class="form-input-modern"
                               placeholder="Enter product category (e.g., Groceries, Beverages)"
                               required 
                               value="<?php echo $selected_product ? htmlspecialchars($selected_product['category'] ?? '') : htmlspecialchars($_POST['category'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group-modern">
                        <label for="price" class="form-label">
                            <span class="label-icon"><i class="fas fa-tag"></i></span>
                            Sales Price
                            <span class="required">*</span>
                        </label>
                        <div class="input-with-prefix">
                            <span class="input-prefix">&#8369;</span>
                            <input type="number" 
                                   id="price" 
                                   name="price" 
                                   class="form-input-modern"
                                   step="0.01" 
                                   min="0.01" 
                                   placeholder="0.00"
                                   required 
                                   value="<?php echo $selected_product ? htmlspecialchars($selected_product['price']) : htmlspecialchars($_POST['price'] ?? ''); ?>">
                        </div>
                    </div>

                    <div class="form-group-modern">
                        <label for="cost_price" class="form-label">
                            <span class="label-icon"><i class="fas fa-dollar-sign"></i></span>
                            Product Cost (Value)
                        </label>
                        <div class="input-with-prefix">
                            <span class="input-prefix">&#8369;</span>
                            <input type="number" 
                                   id="cost_price" 
                                   name="cost_price" 
                                   class="form-input-modern"
                                   step="0.01" 
                                   min="0.00" 
                                   placeholder="0.00"
                                   value="<?php echo $selected_product ? htmlspecialchars($selected_product['cost_price'] ?? $selected_product['price']) : htmlspecialchars($_POST['cost_price'] ?? ''); ?>">
                        </div>
                        <p style="font-size:0.85em; color:#666; margin-top:6px;">Keep product cost separate from sales price. Leave blank to keep current cost.</p>
                    </div>
                    
                    <div class="form-group-modern">
                        <label for="quantity" class="form-label">
                            <span class="label-icon"><i class="fas fa-boxes"></i></span>
                            Max Quantity
                            <span class="required">*</span>
                        </label>
                        <input type="number" 
                               id="quantity" 
                               name="quantity" 
                               class="form-input-modern"
                               min="0" 
                               placeholder="0"
                               required 
                               value="<?php echo $selected_product ? htmlspecialchars($selected_product['quantity']) : htmlspecialchars($_POST['quantity'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group-modern">
                        <label for="isles" class="form-label">
                            <span class="label-icon"><i class="fas fa-map-marker-alt"></i></span>
                            Aisle
                            <span class="required">*</span>
                        </label>
                        <input type="text" 
                               id="isles" 
                               name="isles" 
                               class="form-input-modern"
                               placeholder="e.g., Aisle 1"
                               required 
                               value="<?php echo $selected_product ? htmlspecialchars($selected_product['isles'] ?? '') : htmlspecialchars($_POST['isles'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group-modern">
                        <label for="shelf_position" class="form-label">
                            <span class="label-icon"><i class="fas fa-layer-group"></i></span>
                            Shelf Position
                            <span class="required">*</span>
                        </label>
                        <input type="text" 
                               id="shelf_position" 
                               name="shelf_position" 
                               class="form-input-modern"
                               placeholder="e.g., Shelf A-3"
                               required 
                               value="<?php echo $selected_product ? htmlspecialchars($selected_product['shelf_position'] ?? '') : htmlspecialchars($_POST['shelf_position'] ?? ''); ?>">
                    </div>
                </div>
                
                <div class="form-actions-modern">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-check"></i> Update Product
                    </button>
                    <a href="items.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </section>

    <hr style="margin: 40px 0;">

    <section class="form-section">
        <div class="form-container-modern">
            <h2 style="margin-bottom: 20px;">
                <i class="fas fa-exclamation-circle"></i> Products Needing Attention
            </h2>
            <?php
                $conn = getDBConnection();
                
                // Get products without aisle/shelf designation
                $undesignated = [];
                $result = $conn->query("SELECT item_id, item_name, price, quantity, category FROM items WHERE (isles = '' OR isles IS NULL OR shelf_position = '' OR shelf_position IS NULL) AND (category IS NOT NULL AND category != '') ORDER BY item_name");
                if ($result) {
                    $undesignated = $result->fetch_all(MYSQLI_ASSOC);
                }
                
                // Get products without category
                $uncategorized = [];
                $result = $conn->query("SELECT item_id, item_name, price, quantity FROM items WHERE category = '' OR category IS NULL ORDER BY item_name");
                if ($result) {
                    $uncategorized = $result->fetch_all(MYSQLI_ASSOC);
                }
                
                closeDBConnection($conn);
            ?>
            
            <?php if (empty($undesignated) && empty($uncategorized)): ?>
                <p style="color: #666; text-align: center; padding: 20px;"><i class="fas fa-check-circle"></i> All products are properly configured. ✓</p>
            <?php else: ?>
                <?php if (!empty($uncategorized)): ?>
                    <div style="margin-bottom: 30px;">
                        <h3 style="padding: 15px 0; border-bottom: 2px solid #ffc107; margin-bottom: 15px; color: #d39e00;">
                            <i class="fas fa-tag"></i> Products Without Category
                            <span style="font-size: 0.85em; color: #999; font-weight: normal; margin-left: 10px;">(<?php echo count($uncategorized); ?> product<?php echo count($uncategorized) != 1 ? 's' : ''; ?>)</span>
                        </h3>
                        <div style="overflow-x: auto;">
                            <table class="data-table-modern">
                                <thead>
                                    <tr>
                                        <th>Product ID</th>
                                        <th>Product Name</th>
                                        <th>Price</th>
                                        <th>Quantity</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($uncategorized as $product): ?>
                                    <tr style="background-color: #fffbf0;">
                                        <td class="id-cell">#<?php echo htmlspecialchars($product['item_id']); ?></td>
                                        <td class="name-cell"><strong><?php echo htmlspecialchars($product['item_name']); ?></strong></td>
                                        <td class="price-cell">&#8369;<?php echo number_format($product['price'], 2); ?></td>
                                        <td class="quantity-cell"><?php echo htmlspecialchars($product['quantity']); ?></td>
                                        <td class="actions-cell">
                                            <div class="action-buttons">
                                                <a href="create.php?item_id=<?php echo $product['item_id']; ?>" class="btn btn-edit" title="Manage & Set Category"><i class="fas fa-edit"></i> Manage</a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($undesignated)): ?>
                    <div>
                        <h3 style="padding: 15px 0; border-bottom: 2px solid #dc3545; margin-bottom: 15px; color: #721c24;">
                            <i class="fas fa-map-marker-alt"></i> Products Without Aisle/Shelf Designation
                            <span style="font-size: 0.85em; color: #999; font-weight: normal; margin-left: 10px;">(<?php echo count($undesignated); ?> product<?php echo count($undesignated) != 1 ? 's' : ''; ?>)</span>
                        </h3>
                        <div style="overflow-x: auto;">
                            <table class="data-table-modern">
                                <thead>
                                    <tr>
                                        <th>Product ID</th>
                                        <th>Product Name</th>
                                        <th>Category</th>
                                        <th>Price</th>
                                        <th>Quantity</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($undesignated as $product): ?>
                                    <tr style="background-color: #fff5f5;">
                                        <td class="id-cell">#<?php echo htmlspecialchars($product['item_id']); ?></td>
                                        <td class="name-cell"><strong><?php echo htmlspecialchars($product['item_name']); ?></strong></td>
                                        <td class="category-cell"><?php echo htmlspecialchars($product['category']); ?></td>
                                        <td class="price-cell">&#8369;<?php echo number_format($product['price'], 2); ?></td>
                                        <td class="quantity-cell"><?php echo htmlspecialchars($product['quantity']); ?></td>
                                        <td class="actions-cell">
                                            <div class="action-buttons">
                                                <a href="create.php?item_id=<?php echo $product['item_id']; ?>" class="btn btn-edit" title="Manage & Set Designation"><i class="fas fa-edit"></i> Manage</a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </section>
</main>

<?php require_once 'includes/footer.php'; ?>

<script>
function loadProductData() {
    const itemId = document.getElementById('item_id').value;
    if (itemId) {
        // Send AJAX request to fetch product data
        fetch('get_product_data.php?item_id=' + itemId)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('category').value = data.category || '';
                    document.getElementById('price').value = data.price || '';
                    document.getElementById('quantity').value = data.quantity || '';
                    document.getElementById('isles').value = data.isles || '';
                    document.getElementById('shelf_position').value = data.shelf_position || '';
                }
            })
            .catch(error => console.error('Error:', error));
    } else {
        // Clear all fields if no product selected
        document.getElementById('category').value = '';
        document.getElementById('price').value = '';
        document.getElementById('quantity').value = '';
        document.getElementById('isles').value = '';
        document.getElementById('shelf_position').value = '';
    }
}
</script>


