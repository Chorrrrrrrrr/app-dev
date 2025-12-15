<?php
session_start();
include 'db.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['role'] != 'delivery') {
    header("location: login.php");
    exit;
}

$order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
$error = $success = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['confirm_delivery'])) {
    $payment_type = $_POST['payment_type'];
    $order_id_post = intval($_POST['order_id']);
    
    if (!in_array($payment_type, ['Cash', 'Online'])) {
        $error = "Invalid payment type selected.";
    } elseif ($order_id_post <= 0) {
        $error = "Invalid Order ID.";
    } else {
        $new_status = 'Delivered';
        
        $sql_update = "UPDATE online_orders SET status = ?, payment_type = ? WHERE id = ? AND status IN ('Prepared', 'Out for Delivery')";
        $stmt_update = mysqli_prepare($conn, $sql_update);
        mysqli_stmt_bind_param($stmt_update, "ssi", $new_status, $payment_type, $order_id_post);

        if (mysqli_stmt_execute($stmt_update)) {
            if (mysqli_stmt_affected_rows($stmt_update) > 0) {
                $success = "Order #$order_id_post marked as Delivered successfully!";
                $order_id = 0;
            } else {
                $error = "Order #$order_id_post could not be updated (status might not be 'Prepared' or 'Out for Delivery').";
            }
        } else {
            $error = "Error updating status: " . mysqli_error($conn);
        }
        mysqli_stmt_close($stmt_update);
    }
}

if ($order_id > 0) {
    
    $sql_order = "SELECT o.*, u.username, u.contact_number 
                  FROM online_orders o 
                  JOIN users u ON o.user_id = u.id 
                  WHERE o.id = ?";
    $stmt_order = mysqli_prepare($conn, $sql_order);
    mysqli_stmt_bind_param($stmt_order, "i", $order_id);
    mysqli_stmt_execute($stmt_order);
    $result_order = mysqli_stmt_get_result($stmt_order);
    $order = mysqli_fetch_assoc($result_order);
    mysqli_stmt_close($stmt_order);

    if (!$order) {
        $error = "Order ID not found.";
        $order_id = 0;
    } elseif ($order['status'] == 'Canceled') {
        $error = "This order was canceled.";
        $order_id = 0;
    } elseif ($order['status'] == 'Delivered') {
        $error = "This order has already been delivered on " . date('Y-m-d H:i:s', strtotime($order['order_date']));
        $order_id = 0;
    } elseif ($order['status'] == 'Pending' || $order['status'] == 'Ready for Pickup') {
        $error = "This order is not yet prepared for delivery. Current status: " . $order['status'];
        $order_id = 0;
    } else {
        $sql_items = "SELECT p.name, ooi.quantity, ooi.price 
                      FROM online_order_items ooi 
                      JOIN products p ON ooi.product_id = p.id 
                      WHERE ooi.order_id = ?";
        $stmt_items = mysqli_prepare($conn, $sql_items);
        mysqli_stmt_bind_param($stmt_items, "i", $order_id);
        mysqli_stmt_execute($stmt_items);
        $result_items = mysqli_stmt_get_result($stmt_items);
        $items = mysqli_fetch_all($result_items, MYSQLI_ASSOC);
        mysqli_stmt_close($stmt_items);
    }
} else {
    $error = "Invalid Order ID provided.";
}

?>

<!DOCTYPE html>
<html>
<head>
    <title>Delivery Scan</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container">
    <h2>Delivery Scan / Completion</h2>
    <p><a href="delivery_module.php">Back to Delivery List</a></p>

    <?php if ($success): ?><p style='color:green;'><?php echo $success; ?></p><?php endif; ?>
    <?php if ($error): ?><p style='color:red;'><?php echo $error; ?></p><?php endif; ?>
    
    <?php if ($order_id > 0 && empty($error)): ?>
        <h3>Order #<?php echo $order['id']; ?> Details (Status: <?php echo htmlspecialchars($order['status']); ?>)</h3>

        <h4>Customer Information</h4>
        <p>
            Customer: **<?php echo htmlspecialchars($order['username']); ?>**<br>
            Contact Number: **<?php echo htmlspecialchars($order['contact_number'] ?? 'N/A'); ?>**<br>
            Delivery Address: <?php echo htmlspecialchars($order['delivery_address']); ?><br>
            Total Amount: ₱ **<?php echo number_format($order['total_amount'], 2); ?>**<br>
            Notes: <?php echo htmlspecialchars($order['customer_notes']); ?>
        </p>

        <h4>Items Ordered</h4>
        <table border="1">
            <tr>
                <th>Item</th>
                <th>Qty</th>
                <th>Price</th>
            </tr>
            <?php foreach ($items as $item): ?>
            <tr>
                <td><?php echo htmlspecialchars($item['name']); ?></td>
                <td><?php echo $item['quantity']; ?></td>
                <td>₱ <?php echo number_format($item['price'], 2); ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
        
        <hr>

        <h3>Confirm Delivery & Payment Type</h3>
        <form action="" method="post">
            <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
            
            <label for="payment_type">Payment Method Used:</label>
            <select name="payment_type" id="payment_type" required>
                <option value="">-- Select Payment Type --</option>
                <option value="Cash">Cash Payment</option>
                <option value="Online">Online Payment</option>
            </select>
            <br><br>
            <input type="submit" name="confirm_delivery" value="Confirm Delivery" onclick="return confirm('Are you sure the order has been successfully delivered and paid for?');">
        </form>

    <?php elseif ($order_id > 0 && empty($error)): ?>
        <p>Loading order...</p>
    <?php endif; ?>

</div>
</body>
</html>