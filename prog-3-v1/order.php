<?php
require_once 'auth.php';
requireLogin(); // require login for all order operations
require_once 'dbconnection.php';

// Validate user session
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Cast user_id to integer for safety
$current_user_id = intval($_SESSION['user_id']);

// Verify user exists in database
$verify_conn = getDBConnection();
$verify_stmt = $verify_conn->prepare("SELECT user_id FROM users WHERE user_id = ?");
$verify_stmt->bind_param("i", $current_user_id);
$verify_stmt->execute();
$verify_result = $verify_stmt->get_result();
if ($verify_result->num_rows === 0) {
    // User doesn't exist in database, log them out
    logout();
    header("Location: login.php?error=user_not_found");
    exit();
}
$verify_stmt->close();
closeDBConnection($verify_conn);

// Simple role helper
$isManager = isManager();

$error = '';
$success = '';
$action = $_GET['action'] ?? 'dashboard';

// Optional prefill item id for quick-create links (e.g. items.php?item_id=22)
$prefill_item_id = intval($_GET['item_id'] ?? $_GET['prefill_item'] ?? 0);

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $post_action = $_POST['action'] ?? '';
    $conn = getDBConnection();

    if ($post_action === 'create_purchase') {
        // Managers only
        if (!$isManager) {
            $error = 'Access denied';
        } else {
            $supplier_name = trim($_POST['supplier_name'] ?? '');
            $product_mode = $_POST['product_mode'] ?? 'existing'; // 'existing' or 'new'
            $order_quantity = intval($_POST['order_quantity'] ?? 0);
            $notes = trim($_POST['notes'] ?? '');

            if ($supplier_name === '' || $order_quantity <= 0) {
                $error = 'Supplier and quantity required';
            } else {
                // If new product, create it first
                if ($product_mode === 'new') {
                    $new_name = trim($_POST['new_item_name'] ?? '');
                    $new_price = trim($_POST['new_price'] ?? '');

                    if ($new_name === '' || $new_price === '' || !is_numeric($new_price)) {
                        $error = 'Complete product details required for new product';
                    } else {
                        // create item and purchase order in a transaction
                        $conn->begin_transaction();
                        try {
                            $new_quantity = 0;
                            $stmt = $conn->prepare("INSERT INTO items (item_name, price, quantity, isles, shelf_position) VALUES (?, ?, ?, ?, ?)");
                            $stmt->bind_param('sdiss', $new_name, $new_price, $new_quantity, $empty_isles, $empty_shelf);
                            $empty_isles = '';
                            $empty_shelf = '';
                            if (!$stmt->execute()) throw new Exception('Item insert failed: ' . $stmt->error);
                            $new_item_id = $conn->insert_id;
                            $stmt->close();

                            $stmt = $conn->prepare("INSERT INTO purchase_orders (supplier_name, item_id, order_quantity, status, created_by, notes) VALUES (?, ?, ?, 'pending', ?, ?)");
                            $stmt->bind_param('siiis', $supplier_name, $new_item_id, $order_quantity, $current_user_id, $notes);
                            if (!$stmt->execute()) throw new Exception('Purchase insert failed: ' . $stmt->error);
                            $stmt->close();

                            $conn->commit();
                            $success = 'Product added and purchase order created';
                        } catch (Exception $e) {
                            $conn->rollback();
                            $error = 'Error: ' . $e->getMessage();
                        }
                    }
                } else {
                    // existing product
                    $item_id = intval($_POST['item_id'] ?? 0);
                    if ($item_id <= 0) {
                        $error = 'Please select a product';
                    } else {
                        $stmt = $conn->prepare("INSERT INTO purchase_orders (supplier_name, item_id, order_quantity, status, created_by, notes) VALUES (?, ?, ?, 'pending', ?, ?)");
                        if (!$stmt) {
                            $error = 'Statement preparation failed: ' . $conn->error;
                        } else {
                            $stmt->bind_param('siiis', $supplier_name, $item_id, $order_quantity, $current_user_id, $notes);
                            if ($stmt->execute()) {
                                $success = 'Purchase order created';
                            } else {
                                $error = 'DB error: ' . $stmt->error . ' [User ID: ' . $current_user_id . ']';
                            }
                            $stmt->close();
                        }
                    }
                }
            }
        }
    }

    elseif ($post_action === 'update_purchase_status') {
        if (!$isManager) {
            $error = 'Access denied';
        } else {
            $order_id = intval($_POST['order_id'] ?? 0);
            $new_status = $_POST['new_status'] ?? '';
            $received_quantity = intval($_POST['received_quantity'] ?? 0);

            if ($order_id <= 0 || $new_status === '') {
                $error = 'Order and status required';
            } else {
                // Check if order is already received or cancelled — if so, prevent any status change
                $check_conn = getDBConnection();
                $check_stmt = $check_conn->prepare("SELECT status FROM purchase_orders WHERE order_id = ?");
                $check_stmt->bind_param('i', $order_id);
                $check_stmt->execute();
                $check_res = $check_stmt->get_result();
                if ($check_row = $check_res->fetch_assoc()) {
                    if ($check_row['status'] === 'received' || $check_row['status'] === 'cancelled') {
                        $error = 'Cannot modify status of a ' . htmlspecialchars($check_row['status']) . ' order';
                    }
                }
                $check_stmt->close();
                closeDBConnection($check_conn);
            }
            
            if (!$error) {
                if ($new_status === 'received' || $new_status === 'partially_received') {
                    if ($received_quantity <= 0) {
                        $error = 'Received quantity required';
                    } else {
                        // Use transaction and compute delta if the order was partially received before
                        $conn->begin_transaction();
                        try {
                            // Lock the purchase order row and read existing received_quantity and item_id
                            $stmt = $conn->prepare("SELECT item_id, received_quantity FROM purchase_orders WHERE order_id = ? FOR UPDATE");
                            $stmt->bind_param('i', $order_id);
                            $stmt->execute();
                            $res = $stmt->get_result();
                            if ($row = $res->fetch_assoc()) {
                                $item_id = $row['item_id'];
                                $existing_received = intval($row['received_quantity'] ?? 0);
                                $stmt->close();

                                // Calculate how much to add to inventory (only the difference)
                                $delta = $received_quantity - $existing_received;
                                if ($delta > 0) {
                                    // Lock the item row and update quantity
                                    $stmt = $conn->prepare("SELECT quantity FROM items WHERE item_id = ? FOR UPDATE");
                                    $stmt->bind_param('i', $item_id);
                                    $stmt->execute();
                                    $r2 = $stmt->get_result();
                                    if ($rrow = $r2->fetch_assoc()) {
                                        $stmt->close();

                                        $stmt = $conn->prepare("UPDATE items SET quantity = quantity + ? WHERE item_id = ?");
                                        $stmt->bind_param('ii', $delta, $item_id);
                                        $stmt->execute();
                                        $stmt->close();
                                    } else {
                                        // Item not found
                                        $conn->rollback();
                                        $error = 'Associated product not found';
                                        throw new Exception($error);
                                    }
                                }

                                // Update the purchase order record with new received qty and status
                                $stmt = $conn->prepare("UPDATE purchase_orders SET status = ?, received_quantity = ?, received_date = NOW(), received_by = ? WHERE order_id = ?");
                                $stmt->bind_param('siii', $new_status, $received_quantity, $current_user_id, $order_id);
                                $stmt->execute();
                                $stmt->close();

                                $conn->commit();
                                $success = 'Purchase received and inventory updated';
                            } else {
                                $conn->rollback();
                                $error = 'Order not found';
                            }
                        } catch (Exception $e) {
                            if ($conn->errno === 0) $conn->rollback();
                            $error = 'Error: ' . $e->getMessage();
                        }
                    }
                } else {
                    $stmt = $conn->prepare("UPDATE purchase_orders SET status = ? WHERE order_id = ?");
                    $stmt->bind_param('si', $new_status, $order_id);
                    if ($stmt->execute()) {
                        $success = 'Purchase order updated';
                    } else {
                        $error = 'DB error: ' . $stmt->error;
                    }
                    $stmt->close();
                }
            }
        }
    }

    // -------- CUSTOMER SALES ORDERS --------
    elseif ($post_action === 'create_sale') {
        // Any logged-in user can create a customer order (sale)
        $customer_name = trim($_POST['customer_name'] ?? '');
        $item_id = intval($_POST['item_id'] ?? 0);
        $quantity = intval($_POST['quantity'] ?? 0);
        $notes = trim($_POST['notes'] ?? '');

        if ($customer_name === '' || $item_id <= 0 || $quantity <= 0) {
            $error = 'Customer, product and quantity required';
        } else {
            // Check if requested quantity exceeds available inventory
            $check_stmt = $conn->prepare("SELECT item_name, quantity FROM items WHERE item_id = ?");
            $check_stmt->bind_param('i', $item_id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            if ($check_row = $check_result->fetch_assoc()) {
                $available_quantity = intval($check_row['quantity']);
                $item_name = $check_row['item_name'];
                if ($quantity > $available_quantity) {
                    $error = "Insufficient inventory for '{$item_name}'. Requested: {$quantity}, Available: {$available_quantity}";
                } else {
                    $stmt = $conn->prepare("INSERT INTO customer_orders (customer_name, item_id, quantity, status, created_by, notes) VALUES (?, ?, ?, 'placed', ?, ?)");
                    if (!$stmt) {
                        $error = 'Statement preparation failed: ' . $conn->error;
                    } else {
                        $stmt->bind_param('siiis', $customer_name, $item_id, $quantity, $current_user_id, $notes);
                        if ($stmt->execute()) {
                            $success = 'Customer order placed';
                        } else {
                            $error = 'DB error: ' . $stmt->error . ' [User ID: ' . $current_user_id . ']';
                        }
                        $stmt->close();
                    }
                }
            } else {
                $error = 'Product not found';
            }
            $check_stmt->close();
        }
    }

    elseif ($post_action === 'update_sale_status') {
        // Update status of customer order and manage inventory
        $order_id = intval($_POST['order_id'] ?? 0);
        $new_status = $_POST['new_status'] ?? '';

        if ($order_id <= 0 || $new_status === '') {
            $error = 'Order and status required';
        } else {
            // If moving to 'shipped' we should decrement inventory
            if ($new_status === 'shipped' || $new_status === 'delivered') {
                $conn->begin_transaction();
                try {
                    $stmt = $conn->prepare("SELECT item_id, quantity FROM customer_orders WHERE order_id = ? FOR UPDATE");
                    $stmt->bind_param('i', $order_id);
                    $stmt->execute();
                    $res = $stmt->get_result();
                    if ($row = $res->fetch_assoc()) {
                        $item_id = $row['item_id'];
                        $qty = $row['quantity'];
                        $stmt->close();

                        // Check stock
                        $stmt = $conn->prepare("SELECT quantity FROM items WHERE item_id = ? FOR UPDATE");
                        $stmt->bind_param('i', $item_id);
                        $stmt->execute();
                        $r2 = $stmt->get_result();
                        if ($rrow = $r2->fetch_assoc()) {
                            $stock = $rrow['quantity'];
                            if ($stock < $qty) {
                                $conn->rollback();
                                $error = 'Insufficient stock to ship order';
                            } else {
                                // Decrement inventory
                                $stmt->close();
                                $stmt = $conn->prepare("UPDATE items SET quantity = quantity - ? WHERE item_id = ?");
                                $stmt->bind_param('ii', $qty, $item_id);
                                $stmt->execute();
                                $stmt->close();

                                // Update order status
                                $stmt = $conn->prepare("UPDATE customer_orders SET status = ?, shipped_by = ?, shipped_date = NOW() WHERE order_id = ?");
                                $stmt->bind_param('sii', $new_status, $current_user_id, $order_id);
                                $stmt->execute();
                                $stmt->close();

                                $conn->commit();
                                $success = 'Order updated and inventory adjusted';
                            }
                        } else {
                            $conn->rollback();
                            $error = 'Item not found';
                        }
                    } else {
                        $conn->rollback();
                        $error = 'Order not found';
                    }
                } catch (Exception $e) {
                    $conn->rollback();
                    $error = 'Error: ' . $e->getMessage();
                }
            } else {
                // Simple status update
                $stmt = $conn->prepare("UPDATE customer_orders SET status = ? WHERE order_id = ?");
                $stmt->bind_param('si', $new_status, $order_id);
                if ($stmt->execute()) {
                    $success = 'Order status updated';
                } else {
                    $error = 'DB error: ' . $stmt->error;
                }
                $stmt->close();
            }
        }
    }

    elseif ($post_action === 'report_issue') {
        // Report product issue on a purchase order (damaged, qty mismatch, etc)
        $order_id = intval($_POST['order_id'] ?? 0);
        $issue_type = $_POST['issue_type'] ?? '';
        $description = trim($_POST['description'] ?? '');
        $quantity_affected = intval($_POST['quantity_affected'] ?? 0);

        if ($order_id <= 0 || $issue_type === '' || $description === '') {
            $error = 'All fields required';
        } else {
            $conn = getDBConnection();
            // Get item_id from the purchase order
            $stmt = $conn->prepare("SELECT item_id FROM purchase_orders WHERE order_id = ?");
            $stmt->bind_param('i', $order_id);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($row = $res->fetch_assoc()) {
                $item_id = $row['item_id'];
                $stmt->close();
                
                // Create issue report
                $stmt = $conn->prepare("INSERT INTO product_issues (purchase_order_id, item_id, issue_type, description, quantity_affected, reported_by) VALUES (?, ?, ?, ?, ?, ?)");
                if ($stmt) {
                    try {
                        $stmt->bind_param('iissii', $order_id, $item_id, $issue_type, $description, $quantity_affected, $current_user_id);
                        if ($stmt->execute()) {
                            $stmt->close();
                            closeDBConnection($conn);
                            // Redirect back to the purchase detail to avoid blank/refresh issues
                            header('Location: order.php?action=purchase_detail&order_id=' . urlencode($order_id) . '&msg=issue_reported');
                            exit();
                        } else {
                            $error = 'DB error: ' . $stmt->error;
                        }
                    } catch (Exception $e) {
                        error_log('Issue report error: ' . $e->getMessage());
                        $error = 'Error reporting issue';
                    }
                } else {
                    $error = 'Table not found. Please run product_issues_table.sql setup.';
                }
            } else {
                $error = 'Purchase order not found';
            }
            closeDBConnection($conn);
        }
    }

    closeDBConnection($conn);
}

// Fetch lists for display
$conn = getDBConnection();
$items = [];
$purchase_orders = [];
$customer_orders = [];
$has_order_tables = false;

$r = $conn->query("SELECT item_id, item_name, quantity, isles, shelf_position FROM items ORDER BY item_name");
if ($r) $items = $r->fetch_all(MYSQLI_ASSOC);

// Check if order tables exist
$tables_check = $conn->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('purchase_orders','customer_orders') LIMIT 1");
if ($tables_check && $tables_check->num_rows > 0) {
    $has_order_tables = true;
    $r = $conn->query("SELECT po.*, i.item_name, u.full_name FROM purchase_orders po LEFT JOIN items i ON po.item_id = i.item_id LEFT JOIN users u ON po.created_by = u.user_id ORDER BY po.created_at DESC");
    if ($r) $purchase_orders = $r->fetch_all(MYSQLI_ASSOC);

    $r = $conn->query("SELECT co.*, i.item_name, u.full_name FROM customer_orders co LEFT JOIN items i ON co.item_id = i.item_id LEFT JOIN users u ON co.created_by = u.user_id ORDER BY co.created_at DESC");
    if ($r) $customer_orders = $r->fetch_all(MYSQLI_ASSOC);
}

closeDBConnection($conn);

$page_title = "Orders";
require_once 'includes/header.php';
?>

<main class="main-content">
    <div class="page-header">
        <div class="page-title-section">
            <h1 class="page-title">Orders</h1>
            <p class="page-subtitle">Manage incoming purchase orders and customer orders</p>
        </div>
        <div class="page-actions">
            <a href="order.php?action=dashboard" class="btn btn-secondary">Overview</a>
            <a href="order.php?action=purchase" class="btn btn-secondary">Purchase Orders</a>
            <a href="order.php?action=sales" class="btn btn-secondary">Customer Orders</a>
            <a href="items.php" class="btn btn-secondary">← Back to Products</a>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <?php if (!$has_order_tables): ?>
        <div class="alert alert-error">
            <strong>Setup Required:</strong> Order tables not found. Please run the SQL setup scripts:
            <br>• Run <code>purchase_orders_table.sql</code> to create purchase orders table
            <br>• Run <code>customer_orders_table.sql</code> to create customer orders table
            <br>Check the README or setup guide for instructions.
        </div>
    <?php endif; ?>

    <?php if ($action === 'dashboard'): ?>
        <?php if (!$has_order_tables): ?>
            <div class="alert alert-error" style="margin-top:20px;">
                <strong>Orders management is not available.</strong> Please complete the setup first.
            </div>
        <?php else: ?>
        <?php
            // Compute open counts
            $open_po = 0;
            $open_co = 0;
            $recent_po = array_slice($purchase_orders, 0, 5);
            $recent_co = array_slice($customer_orders, 0, 5);
            foreach ($purchase_orders as $pitem) {
                if ($pitem['status'] !== 'received' && $pitem['status'] !== 'cancelled') $open_po++;
            }
            foreach ($customer_orders as $citem) {
                if ($citem['status'] !== 'delivered' && $citem['status'] !== 'cancelled') $open_co++;
            }
        ?>
        <section class="table-section">
            <div class="table-container">
                <h2>Orders Overview</h2>
                <div class="overview-cards" style="display:flex; gap:12px; margin-bottom:16px;">
                    <div class="card">
                        <h3>Open Purchase Orders</h3>
                        <p class="big-number"><?php echo intval($open_po); ?></p>
                        <?php if ($isManager): ?>
                            <a href="order.php?action=purchase" class="btn btn-secondary">Manage</a>
                        <?php else: ?>
                            <a href="order.php?action=purchase" class="btn btn-secondary">View</a>
                        <?php endif; ?>
                    </div>
                    <div class="card">
                        <h3>Open Customer Orders</h3>
                        <p class="big-number"><?php echo intval($open_co); ?></p>
                        <a href="order.php?action=sales" class="btn btn-secondary">Manage</a>
                    </div>
                    <div class="card">
                        <h3>Quick Actions</h3>
                        <div style="display:flex; gap:8px; flex-wrap:wrap;">
                            <?php if ($isManager): ?>
                                <a href="order.php?action=purchase#create" class="btn btn-primary"><i class="fas fa-file-invoice"></i> New Purchase</a>
                            <?php endif; ?>
                            <a href="order.php?action=sales#create" class="btn btn-primary"><i class="fas fa-cart-plus"></i> New Sale</a>
                            <a href="order.php?action=purchase_report" class="btn btn-secondary"><i class="fas fa-file-alt"></i> PO Report</a>
                            <a href="order.php?action=sales_report" class="btn btn-secondary"><i class="fas fa-file-alt"></i> Sales Report</a>
                        </div>
                    </div>
                </div>

                <div style="display:flex; gap:20px;">
                    <div style="flex:1;">
                        <div class="table-section">
                            <div class="table-wrapper">
                                <h4 style="margin-bottom: 15px;">Recent Purchase Orders</h4>
                                <?php if (empty($recent_po)): ?>
                                    <p class="text-center">No recent purchase orders.</p>
                                <?php else: ?>
                                    <table class="data-table-modern">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Supplier</th>
                                                <th>Item</th>
                                                <th>Quantity</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                        <?php foreach ($recent_po as $rpo): ?>
                                            <tr>
                                                <td class="id-cell">#<?php echo htmlspecialchars($rpo['order_id']); ?></td>
                                                <td class="supplier-cell"><?php echo htmlspecialchars($rpo['supplier_name']); ?></td>
                                                <td class="item-cell"><strong><?php echo htmlspecialchars($rpo['item_name'] ?? 'N/A'); ?></strong></td>
                                                <td class="quantity-cell"><?php echo intval($rpo['order_quantity']); ?></td>
                                                <td class="status-cell"><span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $rpo['status'])); ?>"><?php echo htmlspecialchars($rpo['status']); ?></span></td>
                                                <td class="actions-cell">
                                                    <div class="action-buttons">
                                                        <a href="order.php?action=purchase&edit=<?php echo $rpo['order_id']; ?>" class="btn btn-view" title="View/Edit"><i class="fas fa-eye"></i></a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div style="flex:1;">
                        <div class="table-section">
                            <div class="table-wrapper">
                                <h4 style="margin-bottom: 15px;">Recent Customer Orders</h4>
                                <?php if (empty($recent_co)): ?>
                                    <p class="text-center">No recent customer orders.</p>
                                <?php else: ?>
                                    <table class="data-table-modern">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Customer</th>
                                                <th>Item</th>
                                                <th>Quantity</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                        <?php foreach ($recent_co as $rco): ?>
                                            <tr>
                                                <td class="id-cell">#<?php echo htmlspecialchars($rco['order_id']); ?></td>
                                                <td class="customer-cell"><?php echo htmlspecialchars($rco['customer_name']); ?></td>
                                                <td class="item-cell"><strong><?php echo htmlspecialchars($rco['item_name'] ?? 'N/A'); ?></strong></td>
                                                <td class="quantity-cell"><?php echo intval($rco['quantity']); ?></td>
                                                <td class="status-cell"><span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $rco['status'])); ?>"><?php echo htmlspecialchars($rco['status']); ?></span></td>
                                                <td class="actions-cell">
                                                    <div class="action-buttons">
                                                        <a href="order.php?action=sales&edit=<?php echo $rco['order_id']; ?>" class="btn btn-view" title="View/Edit"><i class="fas fa-eye"></i></a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        <?php endif; ?>

    <?php elseif ($action === 'purchase_report'): 
        $conn = getDBConnection();
        // Fetch all purchase orders with item and user info
        $result = $conn->query(
            "SELECT po.order_id, po.supplier_name, po.order_quantity, po.received_quantity, po.status, 
                    po.created_at, po.received_date, i.item_name, u.full_name 
             FROM purchase_orders po 
             LEFT JOIN items i ON po.item_id = i.item_id 
             LEFT JOIN users u ON po.created_by = u.user_id 
             ORDER BY po.created_at DESC"
        );
        $all_purchase_orders = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
        closeDBConnection($conn);
    ?>
        <section class="form-section">
            <div class="form-container-modern">
                <h2>Purchase Orders Report</h2>
                <p>Complete list of all purchase orders</p>
                <div style="margin-top: 15px;">
                    <a href="pdf.php?report_type=po_report" class="btn btn-primary" target="_blank"><i class="fas fa-file-pdf"></i> Print / Export as PDF</a>
                </div>
            </div>
        </section>

        <section class="table-section">
            <div class="table-wrapper">
                <?php if (empty($all_purchase_orders)): ?>
                    <p class="text-center">No purchase orders found.</p>
                <?php else: ?>
                    <table class="data-table-modern">
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Supplier</th>
                                <th>Product</th>
                                <th>Order Qty</th>
                                <th>Received Qty</th>
                                <th>Status</th>
                                <th>Created Date</th>
                                <th>Received Date</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($all_purchase_orders as $po): ?>
                            <tr>
                                <td class="id-cell">#<?php echo htmlspecialchars($po['order_id']); ?></td>
                                <td class="supplier-cell"><?php echo htmlspecialchars($po['supplier_name']); ?></td>
                                <td class="item-cell"><strong><?php echo htmlspecialchars($po['item_name'] ?? 'N/A'); ?></strong></td>
                                <td class="quantity-cell"><?php echo intval($po['order_quantity']); ?></td>
                                <td class="quantity-cell"><?php echo intval($po['received_quantity'] ?? 0); ?></td>
                                <td class="status-cell"><span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $po['status'])); ?>"><?php echo htmlspecialchars($po['status']); ?></span></td>
                                <td class="date-cell"><?php echo htmlspecialchars($po['created_at']); ?></td>
                                <td class="date-cell"><?php echo htmlspecialchars($po['received_date'] ?? '-'); ?></td>
                                <td class="actions-cell">
                                    <div class="action-buttons">
                                        <a href="order.php?action=purchase_detail&order_id=<?php echo $po['order_id']; ?>" class="btn btn-view" title="View Details"><i class="fas fa-eye"></i></a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </section>

    <?php elseif ($action === 'sales_report'): 
        $conn = getDBConnection();
        // Fetch all customer orders with item and user info
        $result = $conn->query(
            "SELECT co.order_id, co.customer_name, co.quantity, co.status, 
                    co.created_at, i.item_name, i.price, u.full_name 
             FROM customer_orders co 
             LEFT JOIN items i ON co.item_id = i.item_id 
             LEFT JOIN users u ON co.created_by = u.user_id 
             ORDER BY co.created_at DESC"
        );
        $all_customer_orders = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
        closeDBConnection($conn);
    ?>
        <section class="form-section">
            <div class="form-container-modern">
                <h2>Sales Orders Report</h2>
                <p>Complete list of all customer orders</p>
                <div style="margin-top: 15px;">
                    <a href="pdf.php?report_type=sales_report" class="btn btn-primary" target="_blank"><i class="fas fa-file-pdf"></i> Print / Export as PDF</a>
                </div>
            </div>
        </section>

        <section class="table-section">
            <div class="table-wrapper">
                <?php if (empty($all_customer_orders)): ?>
                    <p class="text-center">No customer orders found.</p>
                <?php else: ?>
                    <table class="data-table-modern">
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Customer</th>
                                <th>Product</th>
                                <th>Quantity</th>
                                <th>Price</th>
                                <th>Total</th>
                                <th>Status</th>
                                <th>Created Date</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($all_customer_orders as $co): 
                            $total = ($co['price'] ?? 0) * $co['quantity'];
                        ?>
                            <tr>
                                <td class="id-cell">#<?php echo htmlspecialchars($co['order_id']); ?></td>
                                <td class="customer-cell"><?php echo htmlspecialchars($co['customer_name']); ?></td>
                                <td class="item-cell"><strong><?php echo htmlspecialchars($co['item_name'] ?? 'N/A'); ?></strong></td>
                                <td class="quantity-cell"><?php echo intval($co['quantity']); ?></td>
                                <td class="price-cell">$<?php echo htmlspecialchars(number_format($co['price'] ?? 0, 2)); ?></td>
                                <td class="price-cell"><strong>$<?php echo htmlspecialchars(number_format($total, 2)); ?></strong></td>
                                <td class="status-cell"><span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $co['status'])); ?>"><?php echo htmlspecialchars($co['status']); ?></span></td>
                                <td class="date-cell"><?php echo htmlspecialchars($co['created_at']); ?></td>
                                <td class="actions-cell">
                                    <div class="action-buttons">
                                        <a href="order.php?action=sale_detail&order_id=<?php echo $co['order_id']; ?>" class="btn btn-view" title="View Details"><i class="fas fa-eye"></i></a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </section>

    <?php elseif ($action === 'purchase'): ?>
        <section class="form-section">
            <div class="form-container-modern">
                <h2>Create Purchase Order</h2>
                <?php if (!$isManager): ?>
                    <div class="alert alert-error">Only managers can create purchase orders.</div>
                <?php else: ?>
                <form method="POST" action="" class="product-form">
                    <input type="hidden" name="action" value="create_purchase">
                    <div class="form-grid">
                        <div class="form-group-modern">
                            <label>Supplier Name</label>
                            <input name="supplier_name" required class="form-input-modern">
                        </div>
                        <div class="form-group-modern">
                            <label>Quantity</label>
                            <input type="number" name="order_quantity" min="1" required class="form-input-modern">
                        </div>
                    </div>
                    
                    <div style="margin-top:20px; padding-top:20px; border-top:1px solid #e0e0e0;">
                        <div class="form-group-modern">
                            <label>Product</label>
                            <div style="display:flex; gap:12px; align-items:center; margin-bottom:8px;">
                                <label style="display:flex; align-items:center; gap:6px;"><input type="radio" name="product_mode" value="existing" checked> Existing</label>
                                <label style="display:flex; align-items:center; gap:6px;"><input type="radio" name="product_mode" value="new"> New Product</label>
                            </div>

                            <div id="existing_product_wrap">
                                <select name="item_id" class="form-input-modern">
                                    <option value="">-- Select --</option>
                                    <?php foreach ($items as $it): ?>
                                    <option value="<?php echo $it['item_id']; ?>" <?php echo ($prefill_item_id && $prefill_item_id == $it['item_id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($it['item_name']); ?> (Stock: <?php echo $it['quantity']; ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div id="new_product_wrap" style="display:none; margin-top:12px;">
                                <div class="form-group-modern">
                                    <label>Product Name</label>
                                    <input type="text" name="new_item_name" class="form-input-modern" placeholder="New product name">
                                </div>
                                <div class="form-group-modern">
                                    <label>Price</label>
                                    <input type="number" step="0.01" min="0.01" name="new_price" class="form-input-modern" placeholder="0.00">
                                </div>
                                <p style="font-size: 0.9em; color: #666; margin-top: 8px;"><i class="fas fa-info-circle"></i> Aisle, Shelf Position, and Initial Quantity can be set later in the product management area.</p>
                            </div>

                            <script>
                            document.querySelectorAll('input[name="product_mode"]').forEach(function(el){
                                el.addEventListener('change', function(){
                                    if (this.value === 'new') {
                                        document.getElementById('new_product_wrap').style.display = 'block';
                                        document.getElementById('existing_product_wrap').style.display = 'none';
                                    } else {
                                        document.getElementById('new_product_wrap').style.display = 'none';
                                        document.getElementById('existing_product_wrap').style.display = 'block';
                                    }
                                });
                            });
                            </script>
                        </div>
                    </div>
                    <div class="form-actions"><button class="btn btn-primary">Create</button></div>
                </form>
                <?php endif; ?>
            </div>
        </section>

        <section class="table-section">
            <div class="table-container">
                <h2>Purchase Orders</h2>
                <table class="data-table">
                    <thead><tr><th>ID</th><th>Supplier</th><th>Product</th><th>Qty</th><th>Status</th><th>Created</th><th>Action</th></tr></thead>
                    <tbody>
                    <?php if (empty($purchase_orders)): ?>
                        <tr><td colspan="7" class="text-center">No purchase orders</td></tr>
                    <?php else: foreach ($purchase_orders as $po): ?>
                        <tr>
                            <td><?php echo $po['order_id']; ?></td>
                            <td><?php echo htmlspecialchars($po['supplier_name']); ?></td>
                            <td><?php echo htmlspecialchars($po['item_name'] ?? 'N/A'); ?></td>
                            <td><?php echo $po['order_quantity']; ?></td>
                            <td><?php echo htmlspecialchars($po['status']); ?></td>
                            <td><?php echo htmlspecialchars($po['created_at']); ?></td>
                            <td><a href="order.php?action=purchase_detail&order_id=<?php echo $po['order_id']; ?>" class="btn btn-small btn-primary">View</a></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

    <?php elseif ($action === 'purchase_detail' && isset($_GET['order_id'])): 
        $order_id = intval($_GET['order_id']);
        $conn = getDBConnection();
        $stmt = $conn->prepare("SELECT po.*, i.item_name, i.isles, i.shelf_position, u.full_name FROM purchase_orders po LEFT JOIN items i ON po.item_id = i.item_id LEFT JOIN users u ON po.created_by = u.user_id WHERE po.order_id = ?");
        $stmt->bind_param('i', $order_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $po = $res->fetch_assoc();
        $stmt->close();
        closeDBConnection($conn);
    ?>
        <section class="form-section">
            <div class="form-container-modern">
                <h2>Purchase Order #<?php echo $po['order_id']; ?></h2>
                <p>Supplier: <?php echo htmlspecialchars($po['supplier_name']); ?></p>
                <p>Product: <?php echo htmlspecialchars($po['item_name'] ?? 'N/A'); ?></p>
                <p>Quantity: <?php echo $po['order_quantity']; ?></p>
                <p>Status: <?php echo htmlspecialchars($po['status']); ?></p>

                <?php if ($isManager && $po['status'] !== 'received' && $po['status'] !== 'cancelled'): ?>
                <h3>Update Status</h3>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="update_purchase_status">
                    <input type="hidden" name="order_id" value="<?php echo $po['order_id']; ?>">
                    <select name="new_status" class="form-input-modern">
                        <option value="ordered">Ordered</option>
                        <option value="confirmed">Confirmed</option>
                        <option value="processing">Processing</option>
                        <option value="in_transit">In Transit</option>
                        <option value="delayed">Delayed</option>
                        <option value="received">Received</option>
                        <option value="partially_received">Partially Received</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                    <div id="received_group" style="display:none;">
                        <label>Received Quantity</label>
                        <input type="number" name="received_quantity" min="1" class="form-input-modern">
                    </div>
                    <div class="form-actions"><button class="btn btn-primary">Update</button></div>
                </form>
                <script>
                document.querySelector('select[name="new_status"]').addEventListener('change', function(e){
                    document.getElementById('received_group').style.display = (this.value === 'received' || this.value === 'partially_received') ? 'block' : 'none';
                });
                </script>
                <?php elseif ($isManager && ($po['status'] === 'received' || $po['status'] === 'cancelled')): ?>
                <div class="alert alert-info">
                    <strong>Order Finalized:</strong> This purchase order is <?php echo htmlspecialchars($po['status']); ?> and cannot be modified.
                </div>
                <?php endif; ?>
                
                <hr style="margin: 20px 0;">
                <h3>Report Product Issues</h3>
                <?php if ($isManager): ?>
                <form method="POST" action="" class="product-form">
                    <input type="hidden" name="action" value="report_issue">
                    <input type="hidden" name="order_id" value="<?php echo $po['order_id']; ?>">
                    <div class="form-grid">
                        <div class="form-group-modern">
                            <label>Issue Type</label>
                            <select name="issue_type" required class="form-input-modern">
                                <option value="">-- Select --</option>
                                <option value="damaged">Damaged</option>
                                <option value="quantity_mismatch">Quantity Mismatch</option>
                                <option value="defective">Defective</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="form-group-modern">
                            <label>Quantity Affected</label>
                            <input type="number" name="quantity_affected" min="1" required class="form-input-modern">
                        </div>
                    </div>
                    <div class="form-group-modern">
                        <label>Description</label>
                        <textarea name="description" required rows="3" class="form-input-modern"></textarea>
                    </div>
                    <div class="form-actions"><button type="submit" class="btn btn-primary">Report Issue</button></div>
                </form>
                <?php else: ?>
                    <p class="text-center">Only managers can report product issues.</p>
                <?php endif; ?>
                
                <hr style="margin: 20px 0;">
                <h3>Reported Issues</h3>
                <?php 
                    // Fetch issues for this order
                    $conn = getDBConnection();
                    $issue_stmt = $conn->prepare("SELECT pi.*, i.item_name, u.full_name FROM product_issues pi LEFT JOIN items i ON pi.item_id = i.item_id LEFT JOIN users u ON pi.reported_by = u.user_id WHERE pi.purchase_order_id = ? ORDER BY pi.reported_at DESC");
                    $issues = [];
                    if ($issue_stmt) {
                        $issue_stmt->bind_param('i', $order_id);
                        $issue_stmt->execute();
                        $issue_result = $issue_stmt->get_result();
                        $issues = $issue_result->fetch_all(MYSQLI_ASSOC);
                        $issue_stmt->close();
                    }
                    closeDBConnection($conn);
                ?>
                <?php if (empty($issues)): ?>
                    <p class="text-center">No issues reported for this order.</p>
                <?php else: ?>
                    <table class="data-table-modern" style="margin-top: 12px;">
                        <thead>
                            <tr>
                                <th>Issue Type</th>
                                <th>Product</th>
                                <th>Quantity</th>
                                <th>Description</th>
                                <th>Reported By</th>
                                <th>Reported Date</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($issues as $issue): ?>
                            <tr>
                                <td><span class="status-badge status-<?php echo $issue['issue_type']; ?>"><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $issue['issue_type']))); ?></span></td>
                                <td class="item-cell"><strong><?php echo htmlspecialchars($issue['item_name'] ?? 'N/A'); ?></strong></td>
                                <td class="quantity-cell"><?php echo intval($issue['quantity_affected']); ?></td>
                                <td><?php echo htmlspecialchars($issue['description']); ?></td>
                                <td><?php echo htmlspecialchars($issue['full_name'] ?? 'N/A'); ?></td>
                                <td class="date-cell"><?php echo htmlspecialchars($issue['reported_at']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </section>

    <?php elseif ($action === 'sales'): ?>
        <section class="form-section">
            <div class="form-container-modern">
                <h2>Create Customer Order</h2>
                <form method="POST" action="" class="product-form">
                    <input type="hidden" name="action" value="create_sale">
                    <div class="form-grid">
                        <div class="form-group-modern">
                            <label>Customer Name</label>
                            <input name="customer_name" required class="form-input-modern">
                        </div>
                        <div class="form-group-modern">
                            <label>Product</label>
                            <select name="item_id" required class="form-input-modern">
                                <option value="">-- Select --</option>
                                <?php foreach ($items as $it): ?>
                                <option value="<?php echo $it['item_id']; ?>"><?php echo htmlspecialchars($it['item_name']); ?> (Stock: <?php echo $it['quantity']; ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group-modern">
                            <label>Quantity</label>
                            <input type="number" name="quantity" min="1" required class="form-input-modern">
                        </div>
                    </div>
                    <div class="form-actions"><button class="btn btn-primary">Place Order</button></div>
                </form>
            </div>
        </section>

        <section class="table-section">
            <div class="table-container">
                <h2>Customer Orders</h2>
                <table class="data-table">
                    <thead><tr><th>ID</th><th>Customer</th><th>Product</th><th>Qty</th><th>Status</th><th>Created</th><th>Action</th></tr></thead>
                    <tbody>
                    <?php if (empty($customer_orders)): ?>
                        <tr><td colspan="7" class="text-center">No customer orders</td></tr>
                    <?php else: foreach ($customer_orders as $co): ?>
                        <tr>
                            <td><?php echo $co['order_id']; ?></td>
                            <td><?php echo htmlspecialchars($co['customer_name']); ?></td>
                            <td><?php echo htmlspecialchars($co['item_name'] ?? 'N/A'); ?></td>
                            <td><?php echo $co['quantity']; ?></td>
                            <td><?php echo htmlspecialchars($co['status']); ?></td>
                            <td><?php echo htmlspecialchars($co['created_at']); ?></td>
                            <td><a href="order.php?action=sale_detail&order_id=<?php echo $co['order_id']; ?>" class="btn btn-small btn-primary">View</a></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

    <?php elseif ($action === 'sale_detail' && isset($_GET['order_id'])):
        $order_id = intval($_GET['order_id']);
        $conn = getDBConnection();
        $stmt = $conn->prepare("SELECT co.*, i.item_name, i.isles, i.shelf_position, u.full_name FROM customer_orders co LEFT JOIN items i ON co.item_id = i.item_id LEFT JOIN users u ON co.created_by = u.user_id WHERE co.order_id = ?");
        $stmt->bind_param('i', $order_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $co = $res->fetch_assoc();
        $stmt->close();
        closeDBConnection($conn);
    ?>
        <section class="form-section">
            <div class="form-container-modern">
                <h2>Customer Order #<?php echo $co['order_id']; ?></h2>
                <p>Customer: <?php echo htmlspecialchars($co['customer_name']); ?></p>
                <p>Product: <?php echo htmlspecialchars($co['item_name'] ?? 'N/A'); ?></p>
                <p>Quantity: <?php echo $co['quantity']; ?></p>
                <p>Status: <?php echo htmlspecialchars($co['status']); ?></p>

                <h3>Update Status</h3>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="update_sale_status">
                    <input type="hidden" name="order_id" value="<?php echo $co['order_id']; ?>">
                    <select name="new_status" class="form-input-modern">
                        <option value="placed">Placed</option>
                        <option value="confirmed">Confirmed</option>
                        <option value="picking">Picking</option>
                        <option value="packed">Packed</option>
                        <option value="shipped">Shipped</option>
                        <option value="delivered">Delivered</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                    <div class="form-actions"><button class="btn btn-primary">Update</button></div>
                </form>
            </div>
        </section>

    <?php endif; ?>
</main>

<style>
.btn i { margin-right:8px; }
</style>

<?php require_once 'includes/footer.php'; ?>
