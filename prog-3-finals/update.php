<?php
require_once 'auth.php';
requireManager(); // Only managers can update items

require_once 'dbconnection.php';

$error = '';
$success = '';
$item = null;

// Get item ID
$item_id = $_GET['id'] ?? null;

if (!$item_id) {
    header("Location: items.php");
    exit();
}

$conn = getDBConnection();

// Get item data
$stmt = $conn->prepare("SELECT * FROM items WHERE item_id = ?");
$stmt->bind_param("i", $item_id);
$stmt->execute();
$result = $stmt->get_result();
$item = $result->fetch_assoc();
$stmt->close();

if (!$item) {
    closeDBConnection($conn);
    header("Location: items.php");
    exit();
}

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
        $stmt = $conn->prepare("UPDATE items SET item_name = ?, price = ?, quantity = ?, isles = ?, shelf_position = ? WHERE item_id = ?");
        $stmt->bind_param("sdissi", $item_name, $price, $quantity, $isles, $shelf_position, $item_id);
        
        if ($stmt->execute()) {
            $stmt->close();
            closeDBConnection($conn);
            header("Location: items.php?success=updated");
            exit();
        } else {
            $error = "Error: " . $stmt->error;
        }
        $stmt->close();
    }
}

closeDBConnection($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Product - INCONVINIENCE STORE</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
</head>
<body>
    <div class="container">
        <h1>Edit Product (ID: <?php echo htmlspecialchars($item['item_id']); ?>)</h1>
        
        <div class="form-container">
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="item_name">Item Name <span class="required">*</span></label>
                    <input type="text" id="item_name" name="item_name" required 
                           value="<?php echo htmlspecialchars($item['item_name']); ?>">
                </div>
                
                <div class="form-group">
                    <label for="price">Price <span class="required">*</span></label>
                    <input type="number" id="price" name="price" step="0.01" min="0.01" required 
                           value="<?php echo htmlspecialchars($item['price']); ?>">
                </div>
                
                <div class="form-group">
                    <label for="quantity">Quantity <span class="required">*</span></label>
                    <input type="number" id="quantity" name="quantity" min="0" required 
                           value="<?php echo htmlspecialchars($item['quantity']); ?>">
                </div>
                
                <div class="form-group">
                    <label for="isles">Aisle <span class="required">*</span></label>
                    <input type="text" id="isles" name="isles" required 
                           value="<?php echo htmlspecialchars($item['isles']); ?>">
                </div>
                
                <div class="form-group">
                    <label for="shelf_position">Shelf Position <span class="required">*</span></label>
                    <input type="text" id="shelf_position" name="shelf_position" required 
                           value="<?php echo htmlspecialchars($item['shelf_position']); ?>">
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Update Product</button>
                    <a href="items.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>


