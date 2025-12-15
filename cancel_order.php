<?php
session_start();
include 'db.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['role'] != 'customer') {
    header("location: customer_login.php");
    exit;
}

$order_id = isset($_GET['order_id']) ? $_GET['order_id'] : 0;
$safe_order_id = (int)$order_id;

$username = $_SESSION['username'];
$stmt_user = mysqli_prepare($conn, "SELECT id FROM users WHERE username = ?");
mysqli_stmt_bind_param($stmt_user, "s", $username);
mysqli_stmt_execute($stmt_user);
$result_user = mysqli_stmt_get_result($stmt_user);
$user_row = mysqli_fetch_assoc($result_user);
$user_id = $user_row['id'];
mysqli_stmt_close($stmt_user);

if ($safe_order_id > 0 && $user_id > 0) {
    $new_status = 'Canceled';
    
    $sql_update = "UPDATE online_orders SET status = ? WHERE id = ? AND user_id = ? AND status = 'Pending'";
    $stmt_update = mysqli_prepare($conn, $sql_update);
    mysqli_stmt_bind_param($stmt_update, "sii", $new_status, $safe_order_id, $user_id);

    if (mysqli_stmt_execute($stmt_update)) {
        if (mysqli_stmt_affected_rows($stmt_update) > 0) {
            $_SESSION['message_success'] = "Order #$safe_order_id was successfully canceled.";
        } else {
            $_SESSION['message_error'] = "Order #$safe_order_id cannot be canceled (already in progress or non-existent).";
        }
    } else {
        $_SESSION['message_error'] = "Error canceling order: " . mysqli_error($conn);
    }
    mysqli_stmt_close($stmt_update);
} else {
    $_SESSION['message_error'] = "Invalid order ID or user session.";
}

header("location: customer_order_history.php");
exit;
?>