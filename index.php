<?php
session_start();
include 'db.php'; 

// Check if the user is ALREADY logged in as a customer.
if (isset($_SESSION['loggedin']) && $_SESSION['role'] == 'customer') {
    // If logged in, send them directly to the main dashboard.
    header("location: customer_dashboard.php");
    exit;
}

// Check if the user is logged in as staff (Admin, Barista, etc.)
if (isset($_SESSION['loggedin']) && $_SESSION['role'] != 'customer') {
    // If staff, send them to the staff login page which will redirect them to their correct dashboard.
    header("location: login.php");
    exit;
}

// If the user is NOT logged in at all, send them to the pre-login page.
// This is the core connection you requested.
header("location: customer_pre_login_dashboard.php");
exit;
?>