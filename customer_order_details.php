<?php
session_start();
include 'db.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['role'] != 'customer') {
    header("location: customer_login.php");
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

$order_id = isset($_GET['order_id']) ? $_GET['order_id'] : 0;
$safe_order_id = (int)$order_id;
$error = $success = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_order'])) {
    $new_address = trim($_POST['delivery_address']);
    $new_notes = trim($_POST['customer_notes']);
    
    $sql_update = "UPDATE online_orders SET delivery_address = ?, customer_notes = ? WHERE id = ? AND user_id = ? AND status = 'Pending'";
    $stmt_update = mysqli_prepare($conn, $sql_update);
    mysqli_stmt_bind_param($stmt_update, "ssii", $new_address, $new_notes, $safe_order_id, $user_id);

    if (mysqli_stmt_execute($stmt_update)) {
        if (mysqli_stmt_affected_rows($stmt_update) > 0) {
            $success = "Order details updated successfully (Only editable when 'Pending').";
        } else {
            $error = "Order details could not be updated. Ensure the order ID is correct and the status is 'Pending'.";
        }
    } else {
        $error = "Database Error: " . mysqli_error($conn);
    }
    mysqli_stmt_close($stmt_update);
}


$sql_order = "SELECT * FROM online_orders WHERE id = ? AND user_id = ?";
$stmt_order = mysqli_prepare($conn, $sql_order);
mysqli_stmt_bind_param($stmt_order, "ii", $safe_order_id, $user_id);
mysqli_stmt_execute($stmt_order);
$result_order = mysqli_stmt_get_result($stmt_order);
$order = mysqli_fetch_assoc($result_order);
mysqli_stmt_close($stmt_order);

if (!$order) {
    die("Order not found or does not belong to your account.");
}

$sql_items = "SELECT ooi.quantity, ooi.price, p.name 
              FROM online_order_items ooi 
              JOIN products p ON ooi.product_id = p.id 
              WHERE ooi.order_id = ?";
$stmt_items = mysqli_prepare($conn, $sql_items);
mysqli_stmt_bind_param($stmt_items, "i", $safe_order_id);
mysqli_stmt_execute($stmt_items);
$result_items = mysqli_stmt_get_result($stmt_items);
mysqli_stmt_close($stmt_items);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Order Details #<?php echo $order['id']; ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container">
    <h2>Details for Order #<?php echo $order['id']; ?></h2>
    <p><a href="customer_order_history.php">Back to Order History</a> | <a href="customer_dashboard.php">Back to Menu</a> | <a href="logout.php">Logout</a></p>

    <?php if (!empty($success)): ?>
        <p class="message-success"><?php echo $success; ?></p>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
        <p class="message-error"><?php echo $error; ?></p>
    <?php endif; ?>

    <h3>Order Summary</h3>
    <p>
        Status: <strong><?php echo htmlspecialchars($order['status']); ?></strong><br>
        Order Date: <?php echo date('Y-m-d H:i:s', strtotime($order['order_date'])); ?><br>
        Total Amount: ₱ <?php echo number_format($order['total_amount'], 2); ?>
    </p>

    <?php if ($order['status'] == 'Pending'): ?>
    <h3>Edit Delivery Details (Only editable when 'Pending')</h3>
    <form action="" method="post">
        Delivery Address: <textarea name="delivery_address" rows="3" required><?php echo htmlspecialchars($order['delivery_address']); ?></textarea><br><br>
        Customer Notes: <textarea name="customer_notes" rows="3"><?php echo htmlspecialchars($order['customer_notes']); ?></textarea><br><br>
        <input type="submit" name="update_order" value="Update Order Details">
    </form>
    <br>
    <a href="cancel_order.php?order_id=<?php echo $order['id']; ?>" style="color:red;" onclick="return confirm('Are you sure you want to cancel this order?');">Cancel Order</a>
    
    <?php else: ?>
    <h3>Delivery Details (Cannot Edit - Status is <?php echo htmlspecialchars($order['status']); ?>)</h3>
    <p>
        Address: <?php echo htmlspecialchars($order['delivery_address']); ?><br>
        Notes:<?php echo htmlspecialchars($order['customer_notes']); ?>
    </p>
    <?php endif; ?>

    <h3>Order Items</h3>
    <table border="1">
        <tr>
            <th>Item</th>
            <th>Quantity</th>
            <th>Unit Price</th>
            <th>Subtotal</th>
        </tr>
        <?php
        $total_check = 0;
        while($item = mysqli_fetch_assoc($result_items)) {
            $subtotal = $item['price'] * $item['quantity'];
            $total_check += $subtotal;
            echo "<tr>";
            echo "<td>" . htmlspecialchars($item['name']) . "</td>";
            echo "<td>" . $item['quantity'] . "</td>";
            echo "<td>₱ " . number_format($item['price'], 2) . "</td>";
            echo "<td>₱ " . number_format($subtotal, 2) . "</td>";
            echo "</tr>";
        }
        ?>
    </table>

    <?php if (abs($total_check - $order['total_amount']) > 0.01): ?>
        <p style='color:red;'>**Warning: Calculated total (₱ <?php echo number_format($total_check, 2); ?>) does not match stored total (₱ <?php echo number_format($order['total_amount'], 2); ?>)!**</p>
    <?php endif; ?>

</div>
</body>
</html>