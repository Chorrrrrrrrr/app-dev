<?php
session_start();
include 'db.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['role'] != 'barista') {
    header("location: login.php");
    exit;
}

$sql_orders = "SELECT o.*, u.username, u.contact_number 
               FROM online_orders o 
               JOIN users u ON o.user_id = u.id 
               ORDER BY o.order_date DESC";
$result_orders = mysqli_query($conn, $sql_orders);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Barista Online Order History</title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* ==================================
           1. GLOBAL & LAYOUT STYLES (Matching Dashboard)
           ================================== */
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #F8F4EF; /* Very light tan background */
            color: #4E342E; /* Dark espresso brown text */
            margin: 0;
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
        }

        h2 {
            color: #4E342E; 
            border-bottom: 2px solid #E0E0E0;
            padding-bottom: 10px;
            margin-top: 0;
            margin-bottom: 25px;
        }

        h3 {
            color: #8B4513; /* Brown Accent */
            border-bottom: 1px solid #F0F0F0;
            padding-bottom: 5px;
            margin-top: 25px;
            margin-bottom: 15px;
        }

        a {
            color: #8B4513; 
            text-decoration: none;
            font-weight: bold;
            transition: color 0.2s;
        }

        a:hover {
            color: #5D4037;
            text-decoration: underline;
        }
        
        /* ==================================
           2. MAIN TABLE STYLES
           ================================== */
        .order-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            border-radius: 8px;
            overflow: hidden; /* Ensures rounded corners */
        }

        .order-table thead th {
            background-color: #4E342E; /* Dark Brown Header */
            color: white;
            padding: 12px 15px;
            text-align: left;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.9em;
        }

        .order-table tbody tr {
            background-color: #FFFFFF;
            cursor: pointer;
            transition: background-color 0.1s;
        }

        .order-table tbody tr:hover {
            background-color: #F8F4EF; /* Light tan hover */
        }

        .order-table td {
            padding: 10px 15px;
            border-bottom: 1px solid #E0E0E0;
            font-size: 0.95em;
        }
        
        /* Status Badges */
        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-weight: bold;
            font-size: 0.8em;
            text-transform: uppercase;
            white-space: nowrap;
        }

        /* Example Status Colors (You can customize these based on status logic) */
        .status-Pending {
            background-color: #FFF3E0; color: #FF9800;
        }
        .status-Prepared {
            background-color: #E8F5E9; color: #4CAF50;
        }
        .status-Delivered {
            background-color: #E3F2FD; color: #2196F3;
        }
        .status-Canceled {
            background-color: #FFEBEE; color: #F44336;
        }


        /* ==================================
           3. COLLAPSIBLE ITEM DETAILS (Nested Row)
           ================================== */
        .item-details-row {
            background-color: #FDFDFD;
        }

        .details-content {
            padding: 15px 30px;
            background-color: #FDFDFD;
            border-bottom: 3px solid #ccc;
        }

        .details-content strong {
            color: #4E342E;
        }

        .nested-table {
            width: 100%;
            margin-top: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            overflow: hidden;
        }
        
        .nested-table th {
            background-color: #F5F5F5;
            color: #555;
            padding: 8px 10px;
            font-size: 0.85em;
            text-transform: capitalize;
        }
        .nested-table td {
            padding: 8px 10px;
            font-size: 0.9em;
        }
        
        .nested-table tr:hover {
            background-color: #fcfcfc;
        }
    </style>
</head>
<body>
<div class="container">
    <h2>Online Order History (Barista View)</h2>
    <p><a href="barista_dashboard.php">Back to POS</a> | <a href="logout.php">Logout</a></p>

    <h3>All Online Orders</h3>
    <?php if (mysqli_num_rows($result_orders) == 0): ?>
        <p>No online orders found.</p>
    <?php else: ?>
        <table class="order-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Customer</th>
                    <th>Date</th>
                    <th>Total</th>
                    <th>Status</th>
                    <th>Details</th>
                </tr>
            </thead>
            <tbody>
                <?php while($order = mysqli_fetch_assoc($result_orders)): 
                    // Determine CSS status class
                    $status_class = str_replace(' ', '', htmlspecialchars($order['status']));
                ?>
                <tr onclick="toggleItems(<?php echo $order['id']; ?>)" style="cursor: pointer;">
                    <td><?php echo $order['id']; ?></td>
                    <td><?php echo htmlspecialchars($order['username']); ?></td>
                    <td><?php echo date('Y-m-d H:i', strtotime($order['order_date'])); ?></td>
                    <td>₱ <?php echo number_format($order['total_amount'], 2); ?></td>
                    <td>
                        <span class="status-badge status-<?php echo $status_class; ?>">
                            <?php echo htmlspecialchars($order['status']); ?>
                        </span>
                    </td>
                    <td>
                        <a href="barista_order_details.php?order_id=<?php echo $order['id']; ?>">View/Update</a>
                    </td>
                </tr>
                
                <tr id="items-<?php echo $order['id']; ?>" style="display: none;" class="item-details-row">
                    <td colspan="6">
                        <div class="details-content">
                            <p><strong>Customer Contact:</strong> <?php echo htmlspecialchars($order['contact_number'] ?? 'N/A'); ?></p>
                            <p><strong>Delivery Address:</strong> <?php echo htmlspecialchars($order['delivery_address'] ?? 'N/A'); ?></p>
                            <p><strong>Notes:</strong> <?php echo htmlspecialchars($order['customer_notes'] ?? 'N/A'); ?></p>
                            
                            <h4 style="color: #4E342E; margin: 15px 0 5px 0; border-bottom: none;">Items Ordered:</h4>
                            <table class="nested-table">
                                <thead>
                                    <tr>
                                        <th>Item</th>
                                        <th>Quantity</th>
                                        <th>Unit Price</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $order_id = $order['id'];
                                    $sql_items = "SELECT p.name, ooi.quantity, ooi.price 
                                                  FROM online_order_items ooi 
                                                  JOIN products p ON ooi.product_id = p.id 
                                                  WHERE ooi.order_id = ?";
                                    $stmt_items = mysqli_prepare($conn, $sql_items);
                                    mysqli_stmt_bind_param($stmt_items, "i", $order_id);
                                    mysqli_stmt_execute($stmt_items);
                                    $result_items = mysqli_stmt_get_result($stmt_items);
                                    
                                    while($item = mysqli_fetch_assoc($result_items)) {
                                        echo "<tr>";
                                        echo "<td>" . htmlspecialchars($item['name']) . "</td>";
                                        echo "<td>" . $item['quantity'] . "</td>";
                                        echo "<td>₱ " . number_format($item['price'], 2) . "</td>";
                                        echo "</tr>";
                                    }
                                    mysqli_stmt_close($stmt_items);
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<script>
    // Toggle function for collapsible rows
    function toggleItems(orderId) {
        var element = document.getElementById('items-' + orderId);
        // Toggle the display property
        if (element.style.display === "none") {
            element.style.display = "table-row";
        } else {
            element.style.display = "none";
        }
    }
</script>
</body>
</html>