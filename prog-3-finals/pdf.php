<?php
/**
 * PDF Generation for Reports
 * Generates PDF files for Inventory, Purchase Orders, and Sales Reports
 */

require_once 'auth.php';
requireLogin();

require_once 'dbconnection.php';

// Set timezone to Philippine Time
date_default_timezone_set('Asia/Manila');

$report_type = $_GET['report_type'] ?? 'inventory';
$user = getCurrentUser();

// Initialize variables
$report_title = '';
$items = [];
$total_value = 0;
$total_items = 0;
$total_products = 0;
$all_purchase_orders = [];
$all_customer_orders = [];

if ($report_type === 'po_report') {
    // Fetch all purchase orders
    $conn = getDBConnection();
    $result = $conn->query(
        "SELECT po.order_id, po.supplier_name, po.order_quantity, po.received_quantity, po.status, 
                po.created_at, po.received_date, i.item_name 
         FROM purchase_orders po 
         LEFT JOIN items i ON po.item_id = i.item_id 
         ORDER BY po.created_at DESC"
    );
    $all_purchase_orders = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    closeDBConnection($conn);
    $report_title = 'PURCHASE ORDERS REPORT';
} elseif ($report_type === 'sales_report') {
    // Fetch all customer orders
    $conn = getDBConnection();
    $result = $conn->query(
        "SELECT co.order_id, co.customer_name, co.quantity, co.status, 
                co.created_at, i.item_name, i.price 
         FROM customer_orders co 
         LEFT JOIN items i ON co.item_id = i.item_id 
         ORDER BY co.created_at DESC"
    );
    $all_customer_orders = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    closeDBConnection($conn);
    $report_title = 'SALES ORDERS REPORT';
} else {
    // Default: Inventory report
    // Get search parameter if any
    $search = $_GET['search'] ?? '';
    $search_param = '';

    // Build query
    if (!empty($search)) {
        $search_param = "%" . $search . "%";
        $conn = getDBConnection();
        $stmt = $conn->prepare("SELECT * FROM items WHERE item_name LIKE ? OR isles LIKE ? OR shelf_position LIKE ? ORDER BY item_id DESC");
        $stmt->bind_param("sss", $search_param, $search_param, $search_param);
        $stmt->execute();
        $result = $stmt->get_result();
        $items = [];
        while ($row = $result->fetch_assoc()) {
            $items[] = $row;
        }
        $stmt->close();
    } else {
        $conn = getDBConnection();
        $sql = "SELECT * FROM items ORDER BY item_id DESC";
        $result = $conn->query($sql);
        $items = [];
        while ($row = $result->fetch_assoc()) {
            $items[] = $row;
        }
    }
    closeDBConnection($conn);
    $report_title = 'INVENTORY REPORT';
}

// Generate PDF using HTML to PDF approach (browser will handle conversion)
// This creates a print-optimized HTML page that can be saved as PDF
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Inventory Report - <?php echo date('Y-m-d'); ?></title>
    <style>
        @media print {
            @page { 
                margin: 15mm;
                size: A4;
            }
            body { margin: 0; }
            .no-print { display: none; }
        }
        body { 
            font-family: Arial, sans-serif; 
            font-size: 10pt; 
            margin: 20px;
            color: #333;
        }
        .header { 
            text-align: center; 
            margin-bottom: 30px;
            border-bottom: 3px solidrgb(24, 129, 227);
            padding-bottom: 15px;
        }
        h1 { 
            color:rgb(24, 197, 227); 
            margin: 0;
            font-size: 24pt;
            font-weight: bold;
        }
        h2 { 
            color:rgb(0, 22, 82); 
            border-bottom: 2px solidrgb(0, 36, 82); 
            padding-bottom: 5px;
            margin-top: 20px;
            font-size: 14pt;
        }
        .info {
            margin: 10px 0;
            font-size: 9pt;
        }
        table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-top: 20px;
            font-size: 9pt;
        }
        th { 
            background-color:rgb(24, 146, 227); 
            color: white; 
            padding: 8px 5px; 
            text-align: left;
            font-weight: bold;
        }
        td { 
            padding: 6px 5px; 
            border-bottom: 1px solid #ddd;
        }
        tr:nth-child(even) { 
            background-color: #f9f9f9; 
        }
        .total-row { 
            font-weight: bold; 
            background-color: #f0f0f0;
            border-top: 2px solid #333;
        }
        .footer { 
            margin-top: 30px; 
            text-align: center; 
            font-size: 8pt; 
            color: #666;
            border-top: 1px solid #ddd;
            padding-top: 10px;
        }
        .print-btn {
            background: linear-gradient(135deg,rgb(24, 105, 227) 0%,rgb(20, 95, 192) 100%);
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            font-weight: bold;
            margin-bottom: 20px;
        }
        .print-btn:hover {
            background: linear-gradient(135deg,rgb(20, 143, 192) 0%,rgb(16, 83, 160) 100%);
        }
    </style>
    <script>
        function printPDF() {
            window.print();
        }
        
        // Auto-print on load (optional - comment out if you don't want auto-print)
        // window.onload = function() { setTimeout(printPDF, 500); }
    </script>
</head>
<body>
    <div class="no-print" style="text-align: center; margin-bottom: 20px;">
        <button onclick="printPDF()" class="print-btn">Print / Save as PDF</button>
        <p style="font-size: 12px; color: #666;">Click the button above, then use your browser's print dialog to save as PDF</p>
    </div>
    
    <div class="header">
        <h1>INCONVENIENCE STORE</h1>
        <h2><?php echo htmlspecialchars($report_title); ?></h2>
        <div class="info">
            <strong>Generated:</strong> <?php echo date('F j, Y g:i A') . ''; ?><br>
            <strong>Generated by:</strong> <?php echo htmlspecialchars($user['full_name']); ?> (<?php echo htmlspecialchars($user['role']); ?>)<br>
        </div>
    </div>
    
    <?php if ($report_type === 'po_report'): ?>
    <!-- Purchase Orders Report Table -->
    <table>
        <thead>
            <tr>
                <th style="width: 8%;">Order ID</th>
                <th style="width: 20%;">Supplier</th>
                <th style="width: 22%;">Product</th>
                <th style="width: 8%;">Order Qty</th>
                <th style="width: 8%;">Received Qty</th>
                <th style="width: 12%;">Status</th>
                <th style="width: 14%;">Created Date</th>
                <th style="width: 8%;">Received Date</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            if (count($all_purchase_orders) > 0):
                foreach ($all_purchase_orders as $po): 
            ?>
            <tr>
                <td>#<?php echo htmlspecialchars($po['order_id']); ?></td>
                <td><?php echo htmlspecialchars($po['supplier_name']); ?></td>
                <td><?php echo htmlspecialchars($po['item_name'] ?? 'N/A'); ?></td>
                <td><?php echo intval($po['order_quantity']); ?></td>
                <td><?php echo intval($po['received_quantity'] ?? 0); ?></td>
                <td><?php echo htmlspecialchars($po['status']); ?></td>
                <td><?php echo htmlspecialchars($po['created_at']); ?></td>
                <td><?php echo htmlspecialchars($po['received_date'] ?? '-'); ?></td>
            </tr>
            <?php 
                endforeach;
            else:
            ?>
            <tr>
                <td colspan="8" style="text-align: center; padding: 20px;">No purchase orders found.</td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <?php elseif ($report_type === 'sales_report'): ?>
    <!-- Sales Orders Report Table -->
    <table>
        <thead>
            <tr>
                <th style="width: 8%;">Order ID</th>
                <th style="width: 18%;">Customer</th>
                <th style="width: 20%;">Product</th>
                <th style="width: 8%;">Quantity</th>
                <th style="width: 10%;">Price</th>
                <th style="width: 10%;">Total</th>
                <th style="width: 12%;">Status</th>
                <th style="width: 14%;">Created Date</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $total_sales = 0;
            if (count($all_customer_orders) > 0):
                foreach ($all_customer_orders as $co): 
                    $total = ($co['price'] ?? 0) * $co['quantity'];
                    $total_sales += $total;
            ?>
            <tr>
                <td>#<?php echo htmlspecialchars($co['order_id']); ?></td>
                <td><?php echo htmlspecialchars($co['customer_name']); ?></td>
                <td><?php echo htmlspecialchars($co['item_name'] ?? 'N/A'); ?></td>
                <td><?php echo intval($co['quantity']); ?></td>
                <td>₱<?php echo number_format($co['price'] ?? 0, 2); ?></td>
                <td>₱<?php echo number_format($total, 2); ?></td>
                <td><?php echo htmlspecialchars($co['status']); ?></td>
                <td><?php echo htmlspecialchars($co['created_at']); ?></td>
            </tr>
            <?php 
                endforeach;
            else:
            ?>
            <tr>
                <td colspan="8" style="text-align: center; padding: 20px;">No customer orders found.</td>
            </tr>
            <?php endif; ?>
            <?php if ($report_type === 'sales_report' && count($all_customer_orders) > 0): ?>
            <tr class="total-row">
                <td colspan="5"><strong>TOTAL SALES</strong></td>
                <td><strong>₱<?php echo number_format($total_sales, 2); ?></strong></td>
                <td colspan="2"></td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <?php else: ?>
    <!-- Inventory Report Table -->
    <table>
        <thead>
            <tr>
                <th style="width: 5%;">ID</th>
                <th style="width: 25%;">Product Name</th>
                <th style="width: 10%;">Price</th>
                <th style="width: 10%;">Quantity</th>
                <th style="width: 15%;">Aisle</th>
                <th style="width: 15%;">Shelf Position</th>
                <th style="width: 20%;">Total Value</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $total_value = 0;
            $total_items = 0;
            $total_products = count($items);
            
            if (count($items) > 0):
                foreach ($items as $item): 
                    $item_value = $item['price'] * $item['quantity'];
                    $total_value += $item_value;
                    $total_items += $item['quantity'];
            ?>
            <tr>
                <td><?php echo htmlspecialchars($item['item_id']); ?></td>
                <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                <td>₱<?php echo number_format($item['price'], 2); ?></td>
                <td><?php echo htmlspecialchars($item['quantity']); ?></td>
                <td><?php echo htmlspecialchars($item['isles']); ?></td>
                <td><?php echo htmlspecialchars($item['shelf_position']); ?></td>
                <td>₱<?php echo number_format($item_value, 2); ?></td>
            </tr>
            <?php 
                endforeach;
            else:
            ?>
            <tr>
                <td colspan="7" style="text-align: center; padding: 20px;">No products found in inventory.</td>
            </tr>
            <?php endif; ?>
            
            <tr class="total-row">
                <td colspan="2"><strong>SUMMARY TOTALS</strong></td>
                <td></td>
                <td><strong><?php echo number_format($total_items); ?></strong></td>
                <td colspan="2"></td>
                <td><strong>₱<?php echo number_format($total_value, 2); ?></strong></td>
            </tr>
        </tbody>
    </table>
    <?php endif; ?>
    
    <div class="footer">
        <p><strong>Report Summary</strong></p>
        <?php if ($report_type === 'po_report'): ?>
            <p>Total Purchase Orders: <?php echo count($all_purchase_orders); ?></p>
        <?php elseif ($report_type === 'sales_report'): ?>
            <p>Total Sales Orders: <?php echo count($all_customer_orders); ?> | Total Sales Value: ₱<?php echo number_format($total_sales ?? 0, 2); ?></p>
        <?php else: ?>
            <p>Total Products: <?php echo $total_products; ?> | Total Quantity: <?php echo number_format($total_items); ?> | Total Inventory Value: ₱<?php echo number_format($total_value, 2); ?></p>
        <?php endif; ?>
        <p style="margin-top: 10px;">This is an automated report generated by the Store Management System.</p>
        <p>Page generated on <?php echo date('F j, Y \a\t g:i A') . ''; ?></p>
    </div>
</body>
</html>
