<?php
require_once 'auth.php';
requireLogin(); // All logged-in users can view items

require_once 'dbconnection.php';

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
closeDBConnection($conn);

if (!$item) {
    header("Location: items.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product Details - INCONVINIENCE STORE</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
</head>
<body>
    <div class="container">
        <h1>Product Details</h1>
        
        <div class="view-container">
            <table class="detail-table">
                <tr>
                    <th>Item ID</th>
                    <td><?php echo htmlspecialchars($item['item_id']); ?></td>
                </tr>
                <tr>
                    <th>Item Name</th>
                    <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                </tr>
                <tr>
                    <th>Category</th>
                    <td><?php echo htmlspecialchars($item['category'] ?? 'N/A'); ?></td>
                </tr>
                <tr>
                    <th>Price</th>
                    <td>&#8369;<?php echo number_format($item['price'], 2); ?></td>
                </tr>
                <tr>
                    <th>Quantity</th>
                    <td><?php echo htmlspecialchars($item['quantity']); ?></td>
                </tr>
                <tr>
                    <th>Aisle</th>
                    <td><?php echo htmlspecialchars($item['isles']); ?></td>
                </tr>
                <tr>
                    <th>Shelf Position</th>
                    <td><?php echo htmlspecialchars($item['shelf_position']); ?></td>
                </tr>
                <?php if (isset($item['created_at'])): ?>
                <tr>
                    <th>Created At</th>
                    <td><?php echo date('Y-m-d H:i:s', strtotime($item['created_at'])); ?></td>
                </tr>
                <?php endif; ?>
                <?php if (isset($item['updated_at'])): ?>
                <tr>
                    <th>Updated At</th>
                    <td><?php echo date('Y-m-d H:i:s', strtotime($item['updated_at'])); ?></td>
                </tr>
                <?php endif; ?>
            </table>
            
            <div class="view-actions">
                <?php if (isManager()): ?>
                    <a href="update.php?id=<?php echo $item['item_id']; ?>" class="btn btn-edit">Edit Product</a>
                    <a href="delete.php?id=<?php echo $item['item_id']; ?>" 
                       class="btn btn-delete" 
                       onclick="return confirm('Are you sure you want to delete this product?');">Delete Product</a>
                <?php endif; ?>
                <a href="items.php" class="btn btn-secondary">Back to Inventory</a>
            </div>
        </div>
    </div>
</body>
</html>


