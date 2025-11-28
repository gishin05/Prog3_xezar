<?php
/**
 * Database Setup Script
 * Run this file once to create the database and tables
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'store_db');

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Setup</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .success {
            color: #28a745;
            background: #d4edda;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
            border: 1px solid #c3e6cb;
        }
        .error {
            color: #dc3545;
            background: #f8d7da;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
            border: 1px solid #f5c6cb;
        }
        .info {
            color: #004085;
            background: #cce5ff;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
            border: 1px solid #b3d7ff;
        }
        h1 {
            color: #333;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 10px 5px;
        }
        .btn:hover {
            background: #0056b3;
        }
        code {
            background: #f4f4f4;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: monospace;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Database Setup</h1>
        
        <?php
        $errors = [];
        $success = [];
        
        try {
            // Connect to MySQL server (without database)
            $conn = new mysqli(DB_HOST, DB_USER, DB_PASS);
            
            if ($conn->connect_error) {
                throw new Exception("Connection failed: " . $conn->connect_error);
            }
            
            $success[] = "Connected to MySQL server successfully.";
            
            // Create database if it doesn't exist
            $sql = "CREATE DATABASE IF NOT EXISTS " . DB_NAME;
            if ($conn->query($sql) === TRUE) {
                $success[] = "Database '" . DB_NAME . "' created or already exists.";
            } else {
                throw new Exception("Error creating database: " . $conn->error);
            }
            
            // Select the database
            $conn->select_db(DB_NAME);
            $success[] = "Selected database '" . DB_NAME . "'.";
            
            // Create items table
            $sql = "CREATE TABLE IF NOT EXISTS `items` (
                item_id INT(11) AUTO_INCREMENT PRIMARY KEY,
                item_name VARCHAR(255) NOT NULL,
                price DECIMAL(10, 2) NOT NULL,
                quantity INT(11) NOT NULL DEFAULT 0,
                isles VARCHAR(100) NOT NULL,
                shelf_position VARCHAR(100) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
            
            if ($conn->query($sql) === TRUE) {
                $success[] = "Table 'items' created or already exists.";
            } else {
                throw new Exception("Error creating items table: " . $conn->error);
            }
            
            // Create users table
            $sql = "CREATE TABLE IF NOT EXISTS `users` (
                user_id INT(11) AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(100) NOT NULL UNIQUE,
                password VARCHAR(255) NOT NULL,
                full_name VARCHAR(255) NOT NULL,
                role ENUM('employee', 'manager') NOT NULL DEFAULT 'employee',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
            
            if ($conn->query($sql) === TRUE) {
                $success[] = "Table 'users' created or already exists.";
            } else {
                throw new Exception("Error creating users table: " . $conn->error);
            }
            
            // Create purchase_orders table
            $sql = "CREATE TABLE IF NOT EXISTS `purchase_orders` (
                order_id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                supplier_name VARCHAR(255) NOT NULL,
                item_id INT(11) NOT NULL,
                order_quantity INT(11) NOT NULL,
                received_quantity INT(11) DEFAULT NULL,
                status ENUM('pending','confirmed','picking','picked','packing','packed','shipped','received','cancelled') NOT NULL DEFAULT 'pending',
                notes TEXT,
                created_by INT(11) NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                received_by INT(11) DEFAULT NULL,
                received_date TIMESTAMP NULL,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (item_id) REFERENCES items(item_id) ON DELETE CASCADE,
                FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE RESTRICT,
                FOREIGN KEY (received_by) REFERENCES users(user_id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
            
            if ($conn->query($sql) === TRUE) {
                $success[] = "Table 'purchase_orders' created or already exists.";
            } else {
                throw new Exception("Error creating purchase_orders table: " . $conn->error);
            }
            
            // Create customer_orders table
            $sql = "CREATE TABLE IF NOT EXISTS `customer_orders` (
                order_id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                customer_name VARCHAR(255) NOT NULL,
                item_id INT(11) NOT NULL,
                quantity INT(11) NOT NULL,
                status ENUM('placed','confirmed','picking','packed','shipped','delivered','cancelled') NOT NULL DEFAULT 'placed',
                notes TEXT,
                created_by INT(11) NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                shipped_by INT(11) DEFAULT NULL,
                shipped_date TIMESTAMP NULL,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (item_id) REFERENCES items(item_id) ON DELETE RESTRICT,
                FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE RESTRICT,
                FOREIGN KEY (shipped_by) REFERENCES users(user_id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
            
            if ($conn->query($sql) === TRUE) {
                $success[] = "Table 'customer_orders' created or already exists.";
            } else {
                throw new Exception("Error creating customer_orders table: " . $conn->error);
            }
            
            // Create product_issues table
            $sql = "CREATE TABLE IF NOT EXISTS `product_issues` (
                issue_id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                purchase_order_id INT(11) NOT NULL,
                item_id INT(11) NOT NULL,
                issue_type ENUM('damaged','quantity_mismatch','defective','other') NOT NULL,
                description TEXT,
                quantity_affected INT(11),
                reported_by INT(11) NOT NULL,
                reported_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                resolved BOOLEAN DEFAULT FALSE,
                resolution_notes TEXT,
                resolved_at TIMESTAMP NULL,
                resolved_by INT(11),
                FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders(order_id) ON DELETE CASCADE,
                FOREIGN KEY (item_id) REFERENCES items(item_id) ON DELETE CASCADE,
                FOREIGN KEY (reported_by) REFERENCES users(user_id) ON DELETE RESTRICT,
                FOREIGN KEY (resolved_by) REFERENCES users(user_id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
            
            if ($conn->query($sql) === TRUE) {
                $success[] = "Table 'product_issues' created or already exists.";
            } else {
                throw new Exception("Error creating product_issues table: " . $conn->error);
            }
            
            // Create indexes for better query performance
            $indexes = [
                "ALTER TABLE purchase_orders ADD INDEX IF NOT EXISTS idx_po_status (status)",
                "ALTER TABLE purchase_orders ADD INDEX IF NOT EXISTS idx_po_item (item_id)",
                "ALTER TABLE purchase_orders ADD INDEX IF NOT EXISTS idx_po_created (created_at)",
                "ALTER TABLE customer_orders ADD INDEX IF NOT EXISTS idx_co_status (status)",
                "ALTER TABLE customer_orders ADD INDEX IF NOT EXISTS idx_co_item (item_id)",
                "ALTER TABLE customer_orders ADD INDEX IF NOT EXISTS idx_co_created (created_at)",
                "ALTER TABLE product_issues ADD INDEX IF NOT EXISTS idx_pi_order (purchase_order_id)",
                "ALTER TABLE product_issues ADD INDEX IF NOT EXISTS idx_pi_item (item_id)",
                "ALTER TABLE product_issues ADD INDEX IF NOT EXISTS idx_pi_resolved (resolved)",
                "ALTER TABLE product_issues ADD INDEX IF NOT EXISTS idx_pi_reported (reported_at)"
            ];
            
            foreach ($indexes as $index_sql) {
                if ($conn->query($index_sql) === TRUE) {
                    // Index created or already exists
                } else {
                    // Log but don't fail on index creation
                    error_log("Index creation note: " . $conn->error);
                }
            }
            
            // Check if users table is empty and insert sample data
            $result = $conn->query("SELECT COUNT(*) as count FROM `users`");
            $row = $result->fetch_assoc();
            
            if ($row['count'] == 0) {
                // Generate password hashes
                $manager_password_hash = password_hash('password123', PASSWORD_DEFAULT);
                $employee_password_hash = password_hash('employee123', PASSWORD_DEFAULT);
                
                // Insert sample users with different passwords for manager and employees
                $stmt = $conn->prepare("INSERT INTO `users` (username, password, full_name, role) VALUES (?, ?, ?, ?)");
                
                $users = [
                    ['manager', $manager_password_hash, 'Store Manager', 'manager'],
                    ['employee1', $employee_password_hash, 'John Employee', 'employee'],
                    ['employee2', $employee_password_hash, 'Jane Employee', 'employee']
                ];
                
                $inserted = 0;
                foreach ($users as $user) {
                    $stmt->bind_param("ssss", $user[0], $user[1], $user[2], $user[3]);
                    if ($stmt->execute()) {
                        $inserted++;
                    } else {
                        $errors[] = "Error inserting user {$user[0]}: " . $stmt->error;
                    }
                }
                $stmt->close();
                
                if ($inserted === count($users)) {
                    $success[] = "Sample users inserted successfully.";
                }
            } else {
                $success[] = "Users table already contains data (" . $row['count'] . " users).";
                
                // Update existing users' passwords
                $old_hash = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';
                $manager_hash = password_hash('password123', PASSWORD_DEFAULT);
                $employee_hash = password_hash('employee123', PASSWORD_DEFAULT);
                
                // Update manager password
                $update_stmt = $conn->prepare("UPDATE `users` SET password = ? WHERE role = 'manager' AND (password = ? OR username = 'manager')");
                $update_stmt->bind_param("ss", $manager_hash, $old_hash);
                if ($update_stmt->execute()) {
                    if ($update_stmt->affected_rows > 0) {
                        $success[] = "Updated manager password to 'password123'.";
                    }
                }
                $update_stmt->close();
                
                // Update employee passwords
                $update_stmt = $conn->prepare("UPDATE `users` SET password = ? WHERE role = 'employee' AND (password = ? OR username LIKE 'employee%')");
                $update_stmt->bind_param("ss", $employee_hash, $old_hash);
                if ($update_stmt->execute()) {
                    if ($update_stmt->affected_rows > 0) {
                        $success[] = "Updated " . $update_stmt->affected_rows . " employee password(s) to 'employee123'.";
                    }
                }
                $update_stmt->close();
            }
            
            // Check if items table is empty and insert sample data
            $result = $conn->query("SELECT COUNT(*) as count FROM `items`");
            $row = $result->fetch_assoc();
            
            if ($row['count'] == 0) {
                // Insert sample items
                $sql = "INSERT INTO `items` (item_name, price, quantity, isles, shelf_position) VALUES
                    ('Sample Item 1', 29.99, 50, 'Aisle 1', 'Shelf A-3'),
                    ('Sample Item 2', 15.50, 100, 'Aisle 2', 'Shelf B-5'),
                    ('Sample Item 3', 45.00, 25, 'Aisle 1', 'Shelf A-1')";
                
                if ($conn->query($sql) === TRUE) {
                    $success[] = "Sample items inserted successfully.";
                } else {
                    $errors[] = "Error inserting items: " . $conn->error;
                }
            } else {
                $success[] = "Items table already contains data (" . $row['count'] . " items).";
            }
            
            $conn->close();
            
        } catch (Exception $e) {
            $errors[] = $e->getMessage();
        }
        
        // Display results
        foreach ($success as $msg) {
            echo "<div class='success'>✓ $msg</div>";
        }
        
        foreach ($errors as $msg) {
            echo "<div class='error'>✗ $msg</div>";
        }
        
        if (empty($errors)) {
            echo "<div class='info'>";
            echo "<h2>Setup Complete!</h2>";
            echo "<p>Your database has been set up successfully. You can now use the login page.</p>";
            echo "<p><strong>Sample Login Credentials:</strong></p>";
            echo "<ul>";
            echo "<li><strong>Manager:</strong> Username: <code>manager</code> | Password: <code>password123</code></li>";
            echo "<li><strong>Employee:</strong> Username: <code>employee1</code> | Password: <code>employee123</code></li>";
            echo "<li><strong>Employee:</strong> Username: <code>employee2</code> | Password: <code>employee123</code></li>";
            echo "</ul>";
            echo "<a href='login.php' class='btn'>Go to Login Page</a>";
            echo "</div>";
        } else {
            echo "<div class='error'>";
            echo "<h2>Setup Failed</h2>";
            echo "<p>Please check the errors above and try again.</p>";
            echo "<p>Make sure MySQL is running and the credentials in <code>dbconnection.php</code> are correct.</p>";
            echo "</div>";
        }
        ?>
    </div>
</body>
</html>

