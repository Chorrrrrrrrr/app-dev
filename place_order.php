<?php
session_start();
include 'db.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['role'] != 'customer' || empty($_SESSION['online_cart'])) {
    header("location: customer_dashboard.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['place_order'])) {
    $total_amount = filter_var($_POST['total_amount'], FILTER_VALIDATE_FLOAT);
    
    if ($total_amount === false || $total_amount <= 0) {
        $_SESSION['message_error'] = "Invalid total amount.";
        header("location: customer_dashboard.php");
        exit;
    }

    $username = $_SESSION['username'];
    $stmt_user = mysqli_prepare($conn, "SELECT id FROM users WHERE username = ?");
    mysqli_stmt_bind_param($stmt_user, "s", $username);
    mysqli_stmt_execute($stmt_user);
    $user_id_result = mysqli_stmt_get_result($stmt_user);
    $user_id_row = mysqli_fetch_assoc($user_id_result);
    $user_id = $user_id_row['id'];
    mysqli_stmt_close($stmt_user);

    $status = 'Pending';
    $sql_order = "INSERT INTO online_orders (user_id, total_amount, status) VALUES (?, ?, ?)";
    $stmt_order = mysqli_prepare($conn, $sql_order);
    
    mysqli_stmt_bind_param($stmt_order, "ids", $user_id, $total_amount, $status); 
    
    if (mysqli_stmt_execute($stmt_order)) {
        $order_id = mysqli_insert_id($conn);
        mysqli_stmt_close($stmt_order);
        
        $success_items = true;

        $sql_item = "INSERT INTO online_order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)";
        $stmt_item = mysqli_prepare($conn, $sql_item);

        foreach ($_SESSION['online_cart'] as $product_id => $item) {
            
            $product_id_safe = (int)$product_id;
            $quantity_safe = (int)$item['quantity'];
            $price_safe = filter_var($item['price'], FILTER_VALIDATE_FLOAT);
            
            if ($price_safe === false) {
                $success_items = false;
                break;
            }

            mysqli_stmt_bind_param($stmt_item, "iiid", $order_id, $product_id_safe, $quantity_safe, $price_safe);
            
            if (!mysqli_stmt_execute($stmt_item)) {
                $success_items = false;
                break;
            }
        }
        
        mysqli_stmt_close($stmt_item);

        if ($success_items) {
            unset($_SESSION['online_cart']);
            $_SESSION['message_success'] = "Order #$order_id placed successfully! You can view the details in your order history.";
            $_SESSION['new_order_id'] = $order_id; 
            header("location: customer_order_history.php");
        } else {
            mysqli_query($conn, "DELETE FROM online_orders WHERE id = $order_id");
            $_SESSION['message_error'] = "Error placing order: Failed to save some items. Cart is cleared.";
            unset($_SESSION['online_cart']);
            header("location: customer_dashboard.php");
        }

    } else {
        $_SESSION['message_error'] = "Error placing order: " . mysqli_error($conn);
        header("location: customer_dashboard.php");
    }
    exit;
}
?>