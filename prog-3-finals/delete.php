<?php
require_once 'auth.php';
requireManager(); // Only managers can delete items

require_once 'dbconnection.php';

// Get item ID
$item_id = $_GET['id'] ?? null;

if (!$item_id) {
    header("Location: items.php");
    exit();
}

$conn = getDBConnection();

// Delete item
$stmt = $conn->prepare("DELETE FROM items WHERE item_id = ?");
$stmt->bind_param("i", $item_id);

if ($stmt->execute()) {
    $stmt->close();
    closeDBConnection($conn);
    header("Location: items.php?success=deleted");
    exit();
} else {
    $stmt->close();
    closeDBConnection($conn);
    header("Location: items.php?error=delete_failed");
    exit();
}
?>


