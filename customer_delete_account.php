<?php
session_start();
include 'db.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['role'] != 'customer') {
    header("location: customer_login.php");
    exit;
}

$username = $_SESSION['username'];
$error = "";

$sql_fetch_user = "SELECT id FROM users WHERE username = ?";
$stmt_fetch_user = mysqli_prepare($conn, $sql_fetch_user);
mysqli_stmt_bind_param($stmt_fetch_user, "s", $username);
mysqli_stmt_execute($stmt_fetch_user);
$result_fetch_user = mysqli_stmt_get_result($stmt_fetch_user);
$user_id_row = mysqli_fetch_assoc($result_fetch_user);
$user_id = $user_id_row['id'] ?? 0;
mysqli_stmt_close($stmt_fetch_user);

if ($user_id > 0 && $_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['confirm_delete'])) {
    
    mysqli_begin_transaction($conn);
    $all_queries_successful = true;

    try {
        $sql_find_orders = "SELECT id FROM online_orders WHERE user_id = ?";
        $stmt_find_orders = mysqli_prepare($conn, $sql_find_orders);
        mysqli_stmt_bind_param($stmt_find_orders, "i", $user_id);
        mysqli_stmt_execute($stmt_find_orders);
        $result_orders = mysqli_stmt_get_result($stmt_find_orders);
        
        $sql_delete_items = "DELETE FROM online_order_items WHERE order_id = ?";
        $stmt_delete_items = mysqli_prepare($conn, $sql_delete_items);
        mysqli_stmt_bind_param($stmt_delete_items, "i", $order_id);
        
        while ($order_row = mysqli_fetch_assoc($result_orders)) {
            $order_id = $order_row['id'];
            if (!mysqli_stmt_execute($stmt_delete_items)) {
                $all_queries_successful = false;
                break;
            }
        }
        mysqli_stmt_close($stmt_find_orders);
        mysqli_stmt_close($stmt_delete_items);
        
        if ($all_queries_successful) {
            $sql_delete_orders = "DELETE FROM online_orders WHERE user_id = ?";
            $stmt_delete_orders = mysqli_prepare($conn, $sql_delete_orders);
            mysqli_stmt_bind_param($stmt_delete_orders, "i", $user_id);
            if (!mysqli_stmt_execute($stmt_delete_orders)) {
                $all_queries_successful = false;
            }
            mysqli_stmt_close($stmt_delete_orders);
        }

        if ($all_queries_successful) {
            $sql_delete_user = "DELETE FROM users WHERE id = ? AND role = 'customer'"; 
            $stmt_delete_user = mysqli_prepare($conn, $sql_delete_user);
            mysqli_stmt_bind_param($stmt_delete_user, "i", $user_id);
            if (!mysqli_stmt_execute($stmt_delete_user)) {
                $all_queries_successful = false;
            }
            mysqli_stmt_close($stmt_delete_user);
        }

        if ($all_queries_successful) {
            mysqli_commit($conn);
            session_destroy();
            header("location: customer_login.php?deleted=1");
            exit;
        } else {
            mysqli_rollback($conn);
            $error = "Error deleting account data: " . mysqli_error($conn);
        }

    } catch (Exception $e) {
        mysqli_rollback($conn);
        $error = "Transaction failed: " . $e->getMessage();
    }
}

if ($user_id == 0) {
    $error = "User not found. Please re-login.";
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Delete Account</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container">
    <h2>Delete Account</h2>
    
    <?php if (!empty($error)): ?>
        <p style='color:red;'><?php echo $error; ?></p>
        <br><a href="customer_dashboard.php">Back to Dashboard</a>
    <?php else: ?>
        <p style='color:red;'>**WARNING: Deleting your account is permanent and will remove all your account data and order history.**</p>
        <p>Are you sure you want to delete your account?</p>
        <form action="" method="post" onsubmit="return confirm('Are you absolutely sure you want to delete your account? This action cannot be undone.');">
            <input type="hidden" name="confirm_delete" value="1">
            <input type="submit" value="Confirm Delete Account" style="background-color: darkred;">
        </form>
        <br><a href="customer_dashboard.php">Cancel</a>
    <?php endif; ?>
</div>
</body>
</html>