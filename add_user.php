<?php
session_start();
include 'db.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['role'] != 'admin') {
    header("location: login.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];
    $contact_number = $_POST['contact_number'];
    $role = $_POST['role'];

    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // ✅ UPDATE: Added contact_number to the INSERT query
    $stmt = mysqli_prepare($conn, "INSERT INTO users (username, password, contact_number, role) VALUES (?, ?, ?, ?)");
    mysqli_stmt_bind_param($stmt, "ssss", $username, $hashed_password, $contact_number, $role);
    
    if (mysqli_stmt_execute($stmt)) {
        header("location: admin_dashboard.php");
    } else {
        echo "Error: " . mysqli_error($conn);
    }
    mysqli_stmt_close($stmt);
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Add Staff Account</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container">
    <h2>Add New Staff Account</h2>
    <form action="" method="post">
        Username: <input type="text" name="username" required><br><br>
        Password: <input type="password" name="password" required><br><br>
        
        Contact Number: <input type="text" name="contact_number" required><br><br>
        
        Role: 
        <select name="role">
            <option value="admin">Admin</option>
            <option value="barista">Barista</option>
            </select><br><br>
        <input type="submit" value="Add User">
    </form>
    <p><a href="admin_dashboard.php">Back to Dashboard</a></p>
</div>
</body>
</html>