<?php
require_once 'auth.php';
requireManager(); // Only managers can create items

require_once 'dbconnection.php';

$error = '';
$success = '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $item_name = trim($_POST['item_name'] ?? '');
    $price = trim($_POST['price'] ?? '');
    $quantity = trim($_POST['quantity'] ?? '');
    $isles = trim($_POST['isles'] ?? '');
    $shelf_position = trim($_POST['shelf_position'] ?? '');
    
    // Validation
    if (empty($item_name) || empty($price) || empty($quantity) || empty($isles) || empty($shelf_position)) {
        $error = "All fields are required!";
    } elseif (!is_numeric($price) || $price <= 0) {
        $error = "Price must be a positive number!";
    } elseif (!is_numeric($quantity) || $quantity < 0) {
        $error = "Quantity must be a non-negative number!";
    } else {
        $conn = getDBConnection();
        $stmt = $conn->prepare("INSERT INTO items (item_name, price, quantity, isles, shelf_position) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sdiss", $item_name, $price, $quantity, $isles, $shelf_position);
        
        if ($stmt->execute()) {
            $stmt->close();
            closeDBConnection($conn);
            header("Location: items.php?success=created");
            exit();
        } else {
            $error = "Error: " . $stmt->error;
        }
        $stmt->close();
        closeDBConnection($conn);
    }
}
?>
<?php
$page_title = "Add New Product";
require_once 'includes/header.php';
?>

<main class="main-content">
    <div class="page-header">
        <div class="page-title-section">
            <h1 class="page-title">Add New Product</h1>
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
                        <label for="item_name" class="form-label">
                            <span class="label-icon"><i class="fas fa-tag"></i></span>
                            Product Name
                            <span class="required">*</span>
                        </label>
                        <input type="text" 
                               id="item_name" 
                               name="item_name" 
                               class="form-input-modern"
                               placeholder="Enter product name"
                               required 
                               value="<?php echo htmlspecialchars($_POST['item_name'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group-modern">
                        <label for="price" class="form-label">
                            <span class="label-icon"><i class="fas fa-peso-sign"></i></span>
                            Price
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
                                   value="<?php echo htmlspecialchars($_POST['price'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <div class="form-group-modern">
                        <label for="quantity" class="form-label">
                            <span class="label-icon"><i class="fas fa-boxes"></i></span>
                            Quantity
                            <span class="required">*</span>
                        </label>
                        <input type="number" 
                               id="quantity" 
                               name="quantity" 
                               class="form-input-modern"
                               min="0" 
                               placeholder="0"
                               required 
                               value="<?php echo htmlspecialchars($_POST['quantity'] ?? ''); ?>">
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
                               value="<?php echo htmlspecialchars($_POST['isles'] ?? ''); ?>">
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
                               value="<?php echo htmlspecialchars($_POST['shelf_position'] ?? ''); ?>">
                    </div>
                </div>
                
                <div class="form-actions-modern">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Add Product
                    </button>
                    <a href="items.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </section>
</main>

<?php require_once 'includes/footer.php'; ?>


