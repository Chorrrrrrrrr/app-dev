<?php
session_start();
include 'db.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['role'] != 'barista') {
    header("location: login.php");
    exit;
}

$barista_username = $_SESSION['username'];
$stmt_barista = mysqli_prepare($conn, "SELECT id FROM users WHERE username = ?");
mysqli_stmt_bind_param($stmt_barista, "s", $barista_username);
mysqli_stmt_execute($stmt_barista);
$result_barista = mysqli_stmt_get_result($stmt_barista);
$barista_user_id_row = mysqli_fetch_assoc($result_barista);
$barista_user_id = $barista_user_id_row['id'] ?? 0;
mysqli_stmt_close($stmt_barista);

$order_id = isset($_GET['order_id']) ? $_GET['order_id'] : 0;
$safe_order_id = (int)$order_id;
$error = $success = "";

if ($barista_user_id === 0) {
    die("Barista user ID not found. Please log in again.");
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_status'])) {
    $new_status = $_POST['new_status'];
    
    $valid_statuses = ['Pending', 'Prepared', 'Out for Delivery', 'Ready for Pickup', 'Canceled'];
    if (!in_array($new_status, $valid_statuses)) {
        $error = "Invalid status selected.";
    } else {
        // Set prepared_by when moving beyond 'Pending'
        if ($new_status == 'Prepared' || $new_status == 'Out for Delivery' || $new_status == 'Ready for Pickup') {
            $sql_update = "UPDATE online_orders SET status = ?, prepared_by = ? WHERE id = ? AND (prepared_by IS NULL OR prepared_by = ?)";
            $stmt_update = mysqli_prepare($conn, $sql_update);
            
            if ($stmt_update) {
                // Binding 4 parameters (siii) to 4 placeholders in the SQL
                mysqli_stmt_bind_param($stmt_update, "siii", $new_status, $barista_user_id, $safe_order_id, $barista_user_id);
                
                if (mysqli_stmt_execute($stmt_update)) {
                    if (mysqli_stmt_affected_rows($stmt_update) > 0) {
                        $success = "Order #$safe_order_id status updated to **$new_status**.";
                    } else {
                        $error = "Status is already $new_status or order not found.";
                    }
                } else {
                    $error = "Database error: " . mysqli_error($conn);
                }
                mysqli_stmt_close($stmt_update);
            } else {
                $error = "Failed to prepare statement (Prepared status block): " . mysqli_error($conn);
            }
        } else {
            // Block for 'Pending' and 'Canceled' status updates
          $sql_update = "UPDATE online_orders SET status = ?, prepared_by = ? WHERE id = ? AND (prepared_by IS NULL OR prepared_by = ?)";
            $stmt_update = mysqli_prepare($conn, $sql_update);
            
            // 🐛 FIX: Ensure $stmt_update is not false before calling bind_param
            if ($stmt_update) { 
                // Line 87 (was the location of the fatal error)
                mysqli_stmt_bind_param($stmt_update, "si", $new_status, $safe_order_id);
                
                if (mysqli_stmt_execute($stmt_update)) {
                    if (mysqli_stmt_affected_rows($stmt_update) > 0) {
                        $success = "Order #$safe_order_id status updated to **$new_status**.";
                    } else {
                        $error = "Status is already $new_status or order not found.";
                    }
                } else {
                    $error = "Database error: " . mysqli_error($conn);
                }
                mysqli_stmt_close($stmt_update);
            } else {
                $error = "Failed to prepare statement (Pending/Canceled block): " . mysqli_error($conn);
            }
        }
    }
}
$sql_order = "SELECT o.*, u.username, b.username AS prepared_by_user 
              FROM online_orders o 
              JOIN users u ON o.user_id = u.id 
              LEFT JOIN users b ON o.prepared_by = b.id 
              WHERE o.id = ?";
$stmt_order = mysqli_prepare($conn, $sql_order);
if ($stmt_order) {
    mysqli_stmt_bind_param($stmt_order, "i", $safe_order_id);
    mysqli_stmt_execute($stmt_order);
    $result_order = mysqli_stmt_get_result($stmt_order);
    $order = mysqli_fetch_assoc($result_order);
    mysqli_stmt_close($stmt_order);
} else {
    // If this prepare fails, it's a critical schema problem
    die("Failed to prepare order query: " . mysqli_error($conn));
}
if (!$order) {
    die("Order not found.");
}
// --- Order Items Fetching Logic ---
$sql_items = "SELECT ooi.quantity, ooi.price, p.name 
              FROM online_order_items ooi 
              JOIN products p ON ooi.product_id = p.id 
              WHERE ooi.order_id = ?";
$stmt_items = mysqli_prepare($conn, $sql_items);

if ($stmt_items) {
    mysqli_stmt_bind_param($stmt_items, "i", $safe_order_id);
    mysqli_stmt_execute($stmt_items);
    $result_items = mysqli_stmt_get_result($stmt_items);
} else {
    // If this prepare fails, it's a critical schema problem
    die("Failed to prepare items query: " . mysqli_error($conn));
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Order #<?php echo $safe_order_id; ?> Details</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container">
    <h2>Order #<?php echo $safe_order_id; ?> Details</h2>
    <p><a href="barista_OOH.php">Back to Order History</a> | <a href="logout.php">Logout</a></p>

    <?php if (!empty($success)): ?>
        <p style='color:green;'><?php echo $success; ?></p>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
        <p style='color:red;'><?php echo $error; ?></p>
    <?php endif; ?>

    <h3>Order Summary</h3>
    <p>
        **Customer:** <?php echo htmlspecialchars($order['username']); ?><br>
        **Date:** <?php echo date('Y-m-d H:i:s', strtotime($order['order_date'])); ?><br>
        **Total Amount:** ₱ <?php echo number_format($order['total_amount'], 2); ?><br>
        **Current Status:** **<?php echo htmlspecialchars($order['status']); ?>**<br>
        **Prepared By:** <?php echo htmlspecialchars($order['prepared_by_user'] ?? 'Pending'); ?>
    </p>

    <form action="" method="post">
        <label for="new_status">Update Status:</label>
        <select name="new_status" id="new_status">
            <option value="Pending" <?php echo ($order['status'] == 'Pending') ? 'selected' : ''; ?>>Pending</option>
            <option value="Prepared" <?php echo ($order['status'] == 'Prepared') ? 'selected' : ''; ?>>Prepared</option>
            <option value="Out for Delivery" <?php echo ($order['status'] == 'Out for Delivery') ? 'selected' : ''; ?>>Out for Delivery</option>
            <option value="Ready for Pickup" <?php echo ($order['status'] == 'Ready for Pickup') ? 'selected' : ''; ?>>Ready for Pickup</option>
            <option value="Canceled" <?php echo ($order['status'] == 'Canceled') ? 'selected' : ''; ?>>Canceled</option>
        </select>
        <input type="submit" name="update_status" value="Update Status">
    </form>

    <h3>Delivery Information</h3>
    <p>
        Address: <?php echo htmlspecialchars($order['delivery_address'] ?? 'N/A'); ?><br>
        Notes: <?php echo htmlspecialchars($order['customer_notes'] ?? 'N/A'); ?>
    </p>

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
        // Check if $result_items is a valid result object before fetching
        if ($result_items) {
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
        }
        ?>
    </table>
    
    <?php if (abs($total_check - $order['total_amount']) > 0.01): ?>
        <p style='color:red;'>Warning: Item total (₱ <?php echo number_format($total_check, 2); ?>) does not match order total (₱ <?php echo number_format($order['total_amount'], 2); ?>).</p>
    <?php endif; ?>

</div>
</body>
</html>