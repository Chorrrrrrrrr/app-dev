<?php
session_start();
include 'db.php';

// 1. Authorization Check
if (!isset($_SESSION['loggedin']) || $_SESSION['role'] != 'admin') {
    header("location: login.php");
    exit;
}

// 2. Get the Product ID from the URL
$product_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($product_id > 0) {
    // 3. Prepare SQL DELETE statement
    $sql = "DELETE FROM products WHERE id = ?";
    
    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $product_id);
        
        // 4. Execute the deletion
        if (mysqli_stmt_execute($stmt)) {
            // Success: Redirect to dashboard with a success flag
            header("location: admin_dashboard.php?success_product_deleted=1");
            exit;
        } else {
            // Error: Redirect to dashboard with an error flag
            header("location: admin_dashboard.php?error=Delete failed: " . urlencode(mysqli_error($conn)));
            exit;
        }
        mysqli_stmt_close($stmt);
    } else {
        header("location: admin_dashboard.php?error=Prepare statement failed.");
        exit;
    }
} else {
    // Invalid ID: Redirect to dashboard with an error flag
    header("location: admin_dashboard.php?error=Invalid product ID.");
    exit;
}

mysqli_close($conn);
?>