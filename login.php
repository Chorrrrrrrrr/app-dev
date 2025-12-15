<?php
session_start();
include 'db.php';

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $sql = "SELECT id, username, password, role FROM users WHERE username = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "s", $username);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if ($row) {
        if ($row['role'] == 'customer') {
             $error = "This login page is for staff access only. Please use the customer login.";
        } elseif (password_verify($password, $row['password'])) {
            $_SESSION['loggedin'] = true;
            $_SESSION['username'] = $row['username'];
            $_SESSION['role'] = $row['role'];
            $_SESSION['user_id'] = $row['id']; 

            $redirect_url = '';
            
            if ($row['role'] == 'admin') {
                $redirect_url = "admin_dashboard.php";
            } else if ($row['role'] == 'barista') {
                $redirect_url = "barista_dashboard.php";
            } else if ($row['role'] == 'delivery') {
                $redirect_url = "delivery_module.php";
            } else {
                $error = "Your account role is not permitted here.";
            }

            if (empty($error)) {
                // Set the welcome flag and redirect via JS
                echo "<script>localStorage.setItem('showWelcomeAlert', 'true'); window.location.href = '{$redirect_url}';</script>";
                exit;
            }

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
    <title>POS System Login</title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* ==================================
           1. ALTERNATIVE POS LOGIN DESIGN
           ================================== */
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #4E342E; /* Dark Espresso Brown */
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
            background: #F8F4EF; /* Light Tan/Cream */
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.4);
            text-align: center;
            border-top: 5px solid #8B4513;
        }

        /* --- LOGO STYLES: FIXED --- */
        .logo-header {
            /* Changed background to light color so the black logo is visible */
            background-color: #F8F4EF; 
            padding: 15px 0;
            margin: -40px -40px 20px -40px;
            border-radius: 12px 12px 0 0;
        }
        .logo-header img {
            max-width: 60%;
            height: auto;
            display: block;
            margin: 0 auto;
            /* REMOVED: filter: brightness(0) invert(1); to show the original logo colors */
        }
        /* -------------------------- */

        h2 {
            color: #4E342E;
            margin-bottom: 25px;
            padding-bottom: 10px;
            border-bottom: 2px solid #D7CCC8;
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
            border: 1px solid #BCAAA4;
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
            font-weight: bold;
            transition: background-color 0.3s;
        }

        input[type="submit"]:hover {
            background-color: #5D4037;
        }
        
        /* Error/Link styles */
        p a {
            color: #5D4037;
            text-decoration: underline;
            font-weight: bold;
        }

        p a:hover {
            color: #8B4513;
        }

        /* Adjusting form layout to stack labels/inputs */
        .form-group {
            text-align: left;
            position: relative;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }

        /* VIEW PASSWORD TOGGLE STYLES */
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

        /* ==================================
           2. LOADING SCREEN STYLES
           ================================== */
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
    <div id="loading-text">Authenticating...</div>
</div>

<div class="container">
    
    <div class="logo-header">
        <img src="hosana.png" alt="Hosana Cafe Logo">
    </div>
    
    <h2>POS System Login</h2>
    
    <form action="" method="post" id="mainLoginForm">
        <div class="form-group">
            <label for="username">Username:</label>
            <input type="text" id="username" name="username" required>
        </div>
        
        <div class="form-group">
            <label for="password">Password:</label>
            <input type="password" id="password" name="password" required>
            <span class="toggle-password" onclick="togglePasswordVisibility('password')">👁️</span>
        </div>
        
        <input type="submit" value="Login">
    </form>
    
    <?php if (!empty($error)) { echo "<p style='color:red;'>$error</p>"; } ?>
    
    <p style="font-size: 0.9em; margin-top: 20px;">
        <a href="customer_login.php">Are you a Customer? Login here.</a>
    </p>

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
    
    document.getElementById('mainLoginForm').addEventListener('submit', function(event) {
        if (event.target.checkValidity()) {
            document.getElementById('loading-overlay').style.display = 'flex';
        }
    });
</script>
</body>
</html>