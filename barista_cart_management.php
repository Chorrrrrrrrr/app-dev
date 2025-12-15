<?php
session_start();
include 'db.php'; // Include your database connection

// === 1. Authorization Check (Optional, but highly recommended) ===
if (!isset($_SESSION['loggedin']) || $_SESSION['role'] != 'barista') {
    // If someone tries to access this directly without being logged in as a barista
    header("location: login.php");
    exit;
}

// === 2. Handle Cart Operations ===

// --- Clear Cart Operation ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['clear_cart'])) {
    
    if (isset($_SESSION['cart'])) {
        unset($_SESSION['cart']);
        $_SESSION['cart'] = []; // Re-initialize as empty array
        $_SESSION['message_success'] = "Cart cleared successfully.";
    } else {
        $_SESSION['message_error'] = "Cart was already empty.";
    }
    
    // Redirect back to the barista dashboard
    header("location: barista_dashboard.php");
    exit;
}

// You can add other cart management functions here later (e.g., updating item quantity).

// Fallback redirect if the file is accessed without a POST request
header("location: barista_dashboard.php");
exit;
?>