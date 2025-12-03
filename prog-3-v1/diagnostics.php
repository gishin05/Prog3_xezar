<?php
/**
 * Database Diagnostics Script
 * Check the current state of the database and user sessions
 */

require_once 'auth.php';
require_once 'dbconnection.php';

// Check if logged in
$logged_in = isLoggedIn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Diagnostics</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .diagnostic-container {
            max-width: 800px;
            margin: 30px auto;
            padding: 20px;
            background: #f9f9f9;
            border-radius: 8px;
            font-family: Arial, sans-serif;
        }
        .diagnostic-section {
            margin-bottom: 30px;
            padding: 15px;
            background: white;
            border-radius: 5px;
            border-left: 4px solid #007bff;
        }
        .diagnostic-section h3 {
            margin-top: 0;
            color: #007bff;
        }
        .diagnostic-item {
            margin: 10px 0;
            padding: 8px;
            background: #f0f0f0;
            border-radius: 3px;
        }
        .success { color: green; }
        .error { color: red; }
        .info { color: #0066cc; }
        code {
            background: #f0f0f0;
            padding: 2px 5px;
            border-radius: 3px;
            font-family: monospace;
        }
    </style>
</head>
<body>
    <div class="diagnostic-container">
        <h1>🔍 Database Diagnostics</h1>
        
        <!-- Session Information -->
        <div class="diagnostic-section">
            <h3>Session Information</h3>
            <?php if ($logged_in): ?>
                <div class="diagnostic-item success">
                    ✓ User is logged in
                </div>
                <div class="diagnostic-item">
                    <strong>Session User ID:</strong> <code><?php echo htmlspecialchars($_SESSION['user_id']); ?></code>
                </div>
                <div class="diagnostic-item">
                    <strong>Session Username:</strong> <code><?php echo htmlspecialchars($_SESSION['username']); ?></code>
                </div>
                <div class="diagnostic-item">
                    <strong>Session Full Name:</strong> <code><?php echo htmlspecialchars($_SESSION['full_name']); ?></code>
                </div>
                <div class="diagnostic-item">
                    <strong>Session Role:</strong> <code><?php echo htmlspecialchars($_SESSION['role']); ?></code>
                </div>
            <?php else: ?>
                <div class="diagnostic-item error">
                    ✗ User is NOT logged in
                </div>
                <p>Please <a href="login.php">login</a> first.</p>
            <?php endif; ?>
        </div>

        <!-- Database Connection -->
        <div class="diagnostic-section">
            <h3>Database Connection</h3>
            <?php 
            $conn = getDBConnection();
            if ($conn): 
            ?>
                <div class="diagnostic-item success">
                    ✓ Database connection successful
                </div>
                <div class="diagnostic-item">
                    <strong>Database:</strong> <code><?php echo DB_NAME; ?></code>
                </div>
                <div class="diagnostic-item">
                    <strong>Host:</strong> <code><?php echo DB_HOST; ?></code>
                </div>
            <?php else: ?>
                <div class="diagnostic-item error">
                    ✗ Database connection failed
                </div>
            <?php endif; ?>
        </div>

        <!-- Users Table -->
        <div class="diagnostic-section">
            <h3>Users Table</h3>
            <?php if ($conn): 
                $result = $conn->query("SELECT COUNT(*) as count FROM users");
                $row = $result->fetch_assoc();
            ?>
                <div class="diagnostic-item">
                    <strong>Total Users:</strong> <code><?php echo $row['count']; ?></code>
                </div>
                <table style="width: 100%; border-collapse: collapse; margin-top: 10px;">
                    <thead>
                        <tr style="background: #f0f0f0;">
                            <th style="border: 1px solid #ddd; padding: 8px; text-align: left;">ID</th>
                            <th style="border: 1px solid #ddd; padding: 8px; text-align: left;">Username</th>
                            <th style="border: 1px solid #ddd; padding: 8px; text-align: left;">Full Name</th>
                            <th style="border: 1px solid #ddd; padding: 8px; text-align: left;">Role</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $result = $conn->query("SELECT user_id, username, full_name, role FROM users ORDER BY user_id");
                        while ($row = $result->fetch_assoc()):
                        ?>
                            <tr>
                                <td style="border: 1px solid #ddd; padding: 8px;"><code><?php echo $row['user_id']; ?></code></td>
                                <td style="border: 1px solid #ddd; padding: 8px;"><code><?php echo htmlspecialchars($row['username']); ?></code></td>
                                <td style="border: 1px solid #ddd; padding: 8px;"><?php echo htmlspecialchars($row['full_name']); ?></td>
                                <td style="border: 1px solid #ddd; padding: 8px;"><span style="padding: 2px 8px; background: #e0e0e0; border-radius: 3px;"><?php echo htmlspecialchars($row['role']); ?></span></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- Foreign Key Constraints -->
        <div class="diagnostic-section">
            <h3>Foreign Key Constraint Check</h3>
            <?php if ($conn): 
                // Check for orphaned records
                $orphaned_po = $conn->query("SELECT COUNT(*) as count FROM purchase_orders WHERE created_by NOT IN (SELECT user_id FROM users)");
                $orphaned_po_row = $orphaned_po->fetch_assoc();
                
                $orphaned_co = $conn->query("SELECT COUNT(*) as count FROM customer_orders WHERE created_by NOT IN (SELECT user_id FROM users)");
                $orphaned_co_row = $orphaned_co->fetch_assoc();
                
                $orphaned_pi = $conn->query("SELECT COUNT(*) as count FROM product_issues WHERE reported_by NOT IN (SELECT user_id FROM users)");
                $orphaned_pi_row = $orphaned_pi->fetch_assoc();
            ?>
                <div class="diagnostic-item <?php echo $orphaned_po_row['count'] > 0 ? 'error' : 'success'; ?>">
                    Orphaned purchase_orders (created_by): 
                    <code><?php echo $orphaned_po_row['count']; ?></code>
                </div>
                <div class="diagnostic-item <?php echo $orphaned_co_row['count'] > 0 ? 'error' : 'success'; ?>">
                    Orphaned customer_orders (created_by): 
                    <code><?php echo $orphaned_co_row['count']; ?></code>
                </div>
                <div class="diagnostic-item <?php echo $orphaned_pi_row['count'] > 0 ? 'error' : 'success'; ?>">
                    Orphaned product_issues (reported_by): 
                    <code><?php echo $orphaned_pi_row['count']; ?></code>
                </div>
            <?php endif; ?>
        </div>

        <!-- Actions -->
        <div class="diagnostic-section">
            <h3>Database Maintenance</h3>
            <p>If you see errors in the Foreign Key Constraint Check above, run the repair tool:</p>
            <a href="repair_db.php" style="display: inline-block; padding: 10px 20px; background: #ff6b6b; color: white; text-decoration: none; border-radius: 5px; cursor: pointer;">Run Database Repair</a>
        </div>

        <?php if ($logged_in): ?>
        <div style="margin-top: 30px; text-align: center;">
            <a href="home.php" style="display: inline-block; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 5px;">Back to Home</a>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
<?php
if ($conn) {
    closeDBConnection($conn);
}
?>
