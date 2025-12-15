<?php
session_start();
include 'db.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['role'] != 'customer') {
    header("location: customer_login.php");
    exit;
}

$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;

if ($order_id <= 0) {
    die("Invalid Order ID.");
}

$username = $_SESSION['username'];

$stmt_user = mysqli_prepare($conn, "SELECT id FROM users WHERE username = ?");
mysqli_stmt_bind_param($stmt_user, "s", $username);
mysqli_stmt_execute($stmt_user);
$result_user = mysqli_stmt_get_result($stmt_user);
$user_row = mysqli_fetch_assoc($result_user);
$user_id = $user_row['id'] ?? 0;
mysqli_stmt_close($stmt_user);

if ($user_id === 0) {
    die("User not found.");
}

$sql_order = "SELECT id, status FROM online_orders WHERE id = ? AND user_id = ?";
$stmt_order = mysqli_prepare($conn, $sql_order);
mysqli_stmt_bind_param($stmt_order, "ii", $order_id, $user_id);
mysqli_stmt_execute($stmt_order);
$result_order = mysqli_stmt_get_result($stmt_order);
$order = mysqli_fetch_assoc($result_order);
mysqli_stmt_close($stmt_order);

if (!$order) {
    die("Order not found or does not belong to your account.");
}

$qr_content_url = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/delivery_scan.php?order_id=" . $order_id;
$qr_content_url = str_replace("generate_qr.php/", "", $qr_content_url);

$qr_size = "200x200";
$qr_data = urlencode($qr_content_url);

$qr_image_url = "https://chart.googleapis.com/chart?chs={$qr_size}&cht=qr&chl={$qr_data}";

?>
<!DOCTYPE html>
<html>  
<head>
    <title>QR Code for Order #<?php echo $order_id; ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container" style="text-align: center;">
    <h2>QR Code for Order #<?php echo $order_id; ?></h2>
    <p>Please present this QR code to the barista when picking up your order.</p>

    <?php if ($order['status'] == 'Canceled'): ?>
        <p style="color:red; font-weight: bold;">Order is Canceled. QR Code is not valid.</p>
    <?php else: ?>
        <img src="<?php echo $qr_image_url; ?>" alt="QR Code for Order #<?php echo $order_id; ?>">
        <p>Scan this code to view the order details.</p>
    <?php endif; ?>

    <p><a href="customer_order_history.php">Back to Order History</a></p>
</div>
</body>
</html>