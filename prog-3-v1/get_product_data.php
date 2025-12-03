<?php
require_once 'auth.php';
requireLogin();
require_once 'dbconnection.php';

header('Content-Type: application/json');

$item_id = intval($_GET['item_id'] ?? 0);

if ($item_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid product ID']);
    exit();
}

$conn = getDBConnection();
$stmt = $conn->prepare("SELECT item_id, item_name, category, price, quantity, isles, shelf_position FROM items WHERE item_id = ?");
$stmt->bind_param("i", $item_id);
$stmt->execute();
$result = $stmt->get_result();
$product = $result->fetch_assoc();
$stmt->close();
closeDBConnection($conn);

if ($product) {
    echo json_encode([
        'success' => true,
        'item_id' => $product['item_id'],
        'item_name' => $product['item_name'],
        'category' => $product['category'],
        'price' => $product['price'],
        'quantity' => $product['quantity'],
        'isles' => $product['isles'],
        'shelf_position' => $product['shelf_position']
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Product not found']);
}
?>
