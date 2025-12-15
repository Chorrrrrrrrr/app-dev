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

$sql_orders = "SELECT * FROM online_orders WHERE user_id = ? ORDER BY order_date DESC";
$stmt_orders = mysqli_prepare($conn, $sql_orders);
mysqli_stmt_bind_param($stmt_orders, "i", $user_id);
mysqli_stmt_execute($stmt_orders);
$result_orders = mysqli_stmt_get_result($stmt_orders);
mysqli_stmt_close($stmt_orders);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Order History</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container">
    <h2>Your Order History</h2>
    <p><a href="customer_dashboard.php">Back to Menu</a> 
    <?php if (isset($_SESSION['message_success'])): ?>
        <p class="message-success"><?php echo $_SESSION['message_success']; unset($_SESSION['message_success']); ?></p>
    <?php endif; ?>
    <?php if (isset($_SESSION['message_error'])): ?>
        <p class="message-error"><?php echo $_SESSION['message_error']; unset($_SESSION['message_error']); ?></p>
    <?php endif; ?>

    <?php if (mysqli_num_rows($result_orders) == 0): ?>
        <p>You have no orders yet.</p>
        <p><a href="customer_dashboard.php">Start shopping now!</a></p>
    <?php else: ?>
        <table border="1">
            <tr>
                <th>Order ID</th>
                <th>Date</th>
                <th>Total Amount</th>
                <th>Status</th>
                <th>Details</th>
                <th>QR Code</th> <th>Action</th>
            </tr>
            <?php while($order = mysqli_fetch_assoc($result_orders)): ?>
            <tr>
                <td><?php echo $order['id']; ?></td>
                <td><?php echo date('Y-m-d H:i:s', strtotime($order['order_date'])); ?></td>
                <td>₱ <?php echo number_format($order['total_amount'], 2); ?></td>
                <td><?php echo htmlspecialchars($order['status']); ?></td>
                <td><a href="customer_order_details.php?order_id=<?php echo $order['id']; ?>">View Details</a></td>
                <td>
                    <?php if ($order['status'] != 'Canceled'): ?>
                        <a href="generate_qr.php?order_id=<?php echo $order['id']; ?>" target="_blank">View QR</a>
                    <?php else: ?>
                        N/A
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($order['status'] == 'Pending'): ?>
                        <a href="cancel_order.php?order_id=<?php echo $order['id']; ?>" style="color:red;" onclick="return confirm('Are you sure you want to cancel this order?');">Cancel</a>
                    <?php else: ?>
                        N/A
                    <?php endif; ?>
                </td>
            </tr>
            <?php endwhile; ?>
        </table>
    <?php endif; ?>
</div>
</body>
</html>