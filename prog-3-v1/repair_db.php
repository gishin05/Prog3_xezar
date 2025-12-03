<?php
/**
 * Database Repair Script
 * This script repairs the database by disabling foreign key checks,
 * clearing problematic data, and re-enabling constraints
 */

require_once 'dbconnection.php';

$conn = getDBConnection();

if (!$conn) {
    die("Failed to connect to database");
}

echo "<h2>Database Repair Tool</h2>";
echo "<p>This tool will help repair foreign key constraint issues.</p>";

try {
    // Disable foreign key checks
    $conn->query("SET FOREIGN_KEY_CHECKS=0");
    echo "✓ Foreign key checks disabled<br>";
    
    // Clear invalid records from purchase_orders
    $conn->query("DELETE FROM purchase_orders WHERE created_by NOT IN (SELECT user_id FROM users)");
    echo "✓ Cleared purchase_orders with invalid created_by references<br>";
    
    // Clear invalid records from customer_orders
    $conn->query("DELETE FROM customer_orders WHERE created_by NOT IN (SELECT user_id FROM users)");
    echo "✓ Cleared customer_orders with invalid created_by references<br>";
    
    // Clear invalid records from product_issues
    $conn->query("DELETE FROM product_issues WHERE reported_by NOT IN (SELECT user_id FROM users)");
    echo "✓ Cleared product_issues with invalid reported_by references<br>";
    
    // Clear invalid records from purchase_orders (received_by)
    $conn->query("DELETE FROM purchase_orders WHERE received_by IS NOT NULL AND received_by NOT IN (SELECT user_id FROM users)");
    echo "✓ Cleared purchase_orders with invalid received_by references<br>";
    
    // Clear invalid records from customer_orders (shipped_by)
    $conn->query("DELETE FROM customer_orders WHERE shipped_by IS NOT NULL AND shipped_by NOT IN (SELECT user_id FROM users)");
    echo "✓ Cleared customer_orders with invalid shipped_by references<br>";
    
    // Re-enable foreign key checks
    $conn->query("SET FOREIGN_KEY_CHECKS=1");
    echo "✓ Foreign key checks re-enabled<br>";
    
    echo "<hr>";
    echo "<h3>Database Status</h3>";
    
    // Show users count
    $result = $conn->query("SELECT COUNT(*) as count FROM users");
    $row = $result->fetch_assoc();
    echo "✓ Users in database: " . $row['count'] . "<br>";
    
    // Show items count
    $result = $conn->query("SELECT COUNT(*) as count FROM items");
    $row = $result->fetch_assoc();
    echo "✓ Items in database: " . $row['count'] . "<br>";
    
    // Show purchase_orders count
    $result = $conn->query("SELECT COUNT(*) as count FROM purchase_orders");
    $row = $result->fetch_assoc();
    echo "✓ Purchase orders in database: " . $row['count'] . "<br>";
    
    // Show customer_orders count
    $result = $conn->query("SELECT COUNT(*) as count FROM customer_orders");
    $row = $result->fetch_assoc();
    echo "✓ Customer orders in database: " . $row['count'] . "<br>";
    
    echo "<hr>";
    echo "<p><strong>Repair complete!</strong> <a href='home.php'>Back to Home</a></p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'><strong>Error:</strong> " . $e->getMessage() . "</p>";
}

closeDBConnection($conn);
?>
