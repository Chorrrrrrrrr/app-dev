<?php
session_start();
include 'db.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['role'] != 'delivery') {
    header("location: login.php");
    exit;
}

$username = $_SESSION['username'];
$stmt_user = mysqli_prepare($conn, "SELECT id FROM users WHERE username = ?");
mysqli_stmt_bind_param($stmt_user, "s", $username);
mysqli_stmt_execute($stmt_user);
$result_user = mysqli_stmt_get_result($stmt_user);
$user_row = mysqli_fetch_assoc($result_user);
$user_id = $user_row['id'];
mysqli_stmt_close($stmt_user);

$sql_orders = "SELECT o.*, u.username AS customer_username, u.contact_number
               FROM online_orders o
               JOIN users u ON o.user_id = u.id
               WHERE o.status = 'Delivered'
               ORDER BY o.order_date DESC";
$result_orders = mysqli_query($conn, $sql_orders);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Delivery History</title>
    <link rel="stylesheet" href="style.css">
    <script>
    function toggleItems(orderId) {
        var element = document.getElementById('items-' + orderId);
        if (element.style.display === "none") {
            element.style.display = "table-row";
        } else {
            element.style.display = "none";
        }
    }
    </script>
</head>
<body>
<div class="container">
    <h2>Delivery History</h2>
    <p><a href="delivery_module.php">Back to Active Deliveries</a> | <a href="logout.php">Logout</a></p>

    <h3>Completed Deliveries</h3>
    <?php if (mysqli_num_rows($result_orders) == 0): ?>
        <p>No completed deliveries found.</p>
    <?php else: ?>
        <table border="1" width="100%">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Customer</th>
                    <th>Date</th>
                    <th>Total</th>
                    <th>Address</th>
                    <th>Payment</th>
                    <th>Details</th>
                </tr>
            </thead>
            <tbody>
                <?php while($order = mysqli_fetch_assoc($result_orders)): ?>
                <tr onclick="toggleItems(<?php echo $order['id']; ?>)" style="cursor: pointer;">
                    <td><?php echo $order['id']; ?></td>
                    <td><?php echo htmlspecialchars($order['customer_username']); ?></td>
                    <td><?php echo date('Y-m-d H:i:s', strtotime($order['order_date'])); ?></td>
                    <td>₱ <?php echo number_format($order['total_amount'], 2); ?></td>
                    <td><?php echo htmlspecialchars($order['delivery_address']); ?></td>
                    <td><?php echo htmlspecialchars($order['payment_type']); ?></td>
                    <td>Click for Items</td>
                </tr>
                <tr id="items-<?php echo $order['id']; ?>" style="display: none;">
                    <td colspan="7">
                        <div style="padding: 10px; border-left: 3px solid #007bff; margin: 5px;">
                            <strong>Customer Contact:</strong> <?php echo htmlspecialchars($order['contact_number'] ?? 'N/A'); ?><br>
                            <h4>Items:</h4>
                            <table border="1" width="90%" style="margin: 5px auto;">
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
                            <p>Customer Notes: <?php echo htmlspecialchars($order['customer_notes']); ?></p>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
</body>
</html>