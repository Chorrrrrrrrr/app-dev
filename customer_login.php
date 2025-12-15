<?php
session_start();
include 'db.php';

$error = ""; 

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $sql = "SELECT id, username, password, role, is_verified FROM users WHERE username = ? AND role = 'customer'";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "s", $username);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if ($row) {
        if ($row['is_verified'] == 0) {
            $error = "Account is not verified. Please check your email for the verification code.";
        } elseif (password_verify($password, $row['password'])) {
            $_SESSION['loggedin'] = true;
            $_SESSION['username'] = $row['username'];
            $_SESSION['role'] = $row['role'];
            
            // Sets flag for welcome modal on dashboard
            $_SESSION['showWelcomeAlert'] = true; 
            header("location: customer_dashboard.php");
            exit;
        } else {
            $error = "Invalid username or password.";
        }
    } else {
        $error = "Invalid username or password.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Customer Login</title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* ==================================
           LOGIN DESIGN & CSS
           ================================== */
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #F8F4EF; 
            color: #4E342E; 
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            padding: 0;
        }

        .container {
            width: 90%;
            max-width: 400px;
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
            text-align: center;
        }

        .logo-header {
            margin-bottom: 20px;
            padding-bottom: 10px;
        }
        .logo-header img {
            max-width: 70%;
            height: auto;
            display: block;
            margin: 0 auto;
            cursor: pointer;
        }

        h2 {
            color: #8B4513;
            margin-bottom: 25px;
            padding-bottom: 10px;
        }
        
        form {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        input[type="text"], 
        input[type="password"] {
            width: 100%;
            padding: 12px;
            border: 1px solid #ccc;
            border-radius: 5px;
            box-sizing: border-box; 
            font-size: 16px;
        }

        input[type="submit"] {
            width: 100%;
            padding: 12px;
            background-color: #8B4513; 
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            transition: background-color 0.3s;
        }

        input[type="submit"]:hover {
            background-color: #5D4037;
        }
        
        p a {
            color: #8B4513;
            text-decoration: none;
            font-weight: bold;
        }

        p a:hover {
            text-decoration: underline;
        }
        
        /* New styling for back link to match existing link styles */
        .back-link-container {
             margin-top: 15px;
             font-size: 0.95em;
        }


        .forgot-password-link {
            text-align: right;
            font-size: 0.9em;
            margin-bottom: 10px;
        }

        .form-group {
            text-align: left;
            position: relative;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }

        .toggle-password {
            position: absolute;
            top: 60%; 
            right: 10px;
            transform: translateY(-50%);
            cursor: pointer;
            color: #8B4513; 
            font-size: 1.2em;
            user-select: none; 
        }

        #loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(255, 255, 255, 0.95);
            display: none; 
            justify-content: center;
            align-items: center;
            flex-direction: column;
            z-index: 1000; 
        }

        .spinner {
            border: 8px solid #f3f3f3; 
            border-top: 8px solid #8B4513; 
            border-radius: 50%;
            width: 60px;
            height: 60px;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        #loading-text {
            margin-top: 20px;
            font-size: 1.2em;
            color: #4E342E;
        }
        
    </style>
</head>
<body>

<div id="loading-overlay">
    <div class="spinner"></div>
    <div id="loading-text">Logging in...</div>
</div>

<div class="container">
    
    <div class="logo-header">
        <a href="customer_pre_login_dashboard.php">
            <img src="hosana.png" alt="Hosana Cafe Logo">
        </a>
    </div>
    <h2>Customer Login</h2>
    
    <form action="" method="post" id="loginForm">
        <div class="form-group">
            <label for="username">Username:</label>
            <input type="text" id="username" name="username" required>
        </div>
        
        <div class="form-group">
            <label for="password">Password:</label>
            <input type="password" id="password" name="password" required>
            <span class="toggle-password" onclick="togglePasswordVisibility('password')">👁️</span>
        </div>
        
        <div class="forgot-password-link">
            <a href="forgot_password.php">Forgot Password?</a>
        </div>

        <input type="submit" value="Login">
    </form>
    
    <?php if (!empty($error)) { echo "<p style='color:red;'>$error</p>"; } ?>
    
    <p class="back-link-container">
        <a href="customer_pre_login_dashboard.php">← Back to Home</a>
    </p>
    
    <p>Don't have an account? <a href="customer_register.php">Register here</a></p>
</div>

<script>
    function togglePasswordVisibility(fieldId) {
        const field = document.getElementById(fieldId);
        const icon = field.nextElementSibling;

        if (field.type === 'password') {
            field.type = 'text';
            icon.textContent = '🔒';
        } else {
            field.type = 'password';
            icon.textContent = '👁️';
        }
    }
    
    document.getElementById('loginForm').addEventListener('submit', function(event) {
        if (event.target.checkValidity()) {
            document.getElementById('loading-overlay').style.display = 'flex';
        }
    });
</script>
</body>
</html>