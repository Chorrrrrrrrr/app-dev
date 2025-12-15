<?php
session_start();
include 'db.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['role'] != 'admin') {
    header("location: login.php");
    exit;
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id > 0) {
    $sql = "DELETE FROM users WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $id);

    if (mysqli_stmt_execute($stmt)) {
        header("location: admin_dashboard.php");
        exit;
    } else {
        echo "Error: " . mysqli_error($conn);
    }
    mysqli_stmt_close($stmt);
} else {
    header("location: admin_dashboard.php");
    exit;
}
?>