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
    $role = 'barista'; // Fixed role for barista

    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    $stmt = mysqli_prepare($conn, "INSERT INTO users (username, password, contact_number, role) VALUES (?, ?, ?, ?)");
    mysqli_stmt_bind_param($stmt, "ssss", $username, $hashed_password, $contact_number, $role);
    
    if (mysqli_stmt_execute($stmt)) {
        header("location: admin_dashboard.php?success_barista_added=" . urlencode($username));
    } else {
        echo "Error: " . mysqli_error($conn);
    }
    mysqli_stmt_close($stmt);
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Add Barista Account</title>
    <link rel="stylesheet" href="style.css">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f4f7f6;
            margin: 0;
            padding: 0;
        }

        .container {
            max-width: 600px;
            margin: 50px auto;
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }

        h2 {
            color: #173f5f;
            border-bottom: 3px solid #20639b;
            padding-bottom: 10px;
            margin-bottom: 25px;
        }

        form label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
        }

        form input[type="text"],
        form input[type="password"] {
            width: 100%;
            padding: 10px;
            margin-bottom: 20px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 1em;
            box-sizing: border-box;
        }

        form input[type="submit"] {
            background-color: #20639b;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1em;
            font-weight: bold;
            transition: background-color 0.3s;
        }

        form input[type="submit"]:hover {
            background-color: #173f5f;
        }

        .back-link {
            display: inline-block;
            margin-top: 20px;
            color: #20639b;
            text-decoration: none;
        }

        .back-link:hover {
            text-decoration: underline;
        }

        .role-badge {
            display: inline-block;
            background-color: #e6f0fa;
            color: #173f5f;
            padding: 5px 15px;
            border-radius: 20px;
            font-weight: bold;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
<div class="container">
    <h2>👨‍🍳 Add New Barista Account</h2>
    <div class="role-badge">Role: Barista Staff</div>
    
    <form action="" method="post">
        <label for="username">Username:</label>
        <input type="text" id="username" name="username" required>

        <label for="password">Password:</label>
        <input type="password" id="password" name="password" required>
        
        <label for="contact_number">Contact Number:</label>
        <input type="text" id="contact_number" name="contact_number" required>
        
        <input type="submit" value="Add Barista Account">
    </form>
    
    <a href="admin_dashboard.php" class="back-link">← Back to Dashboard</a>
</div>
</body>
</html>