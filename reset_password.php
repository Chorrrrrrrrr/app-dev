<?php
session_start();
date_default_timezone_set('Asia/Manila');
include 'db.php'; 

$error = "";
$message = "";
$valid_token = false;
$username = '';
$token = '';

// Check for the token and username in the URL
if (isset($_GET['token']) && isset($_GET['user'])) {
    $token = $_GET['token'];
    $username = $_GET['user'];

    // 1. Validate the token and expiry date
    $sql_check = "SELECT id FROM users 
                  WHERE username = ? 
                  AND reset_token = ? 
                  AND reset_expires_at > NOW() 
                  AND role = 'customer'";
    $stmt_check = mysqli_prepare($conn, $sql_check);
    mysqli_stmt_bind_param($stmt_check, "ss", $username, $token);
    mysqli_stmt_execute($stmt_check);
    mysqli_stmt_store_result($stmt_check);

    if (mysqli_stmt_num_rows($stmt_check) == 1) {
        $valid_token = true;
    } else {
        $error = "The password reset link is invalid or has expired.";
    }
    mysqli_stmt_close($stmt_check);
} else {
    $error = "Access denied. Please request a password reset link first.";
}


// Handle the form submission (when the user sets a new password)
if ($_SERVER["REQUEST_METHOD"] == "POST" && $valid_token) {
    // Re-read token and user from hidden form fields
    $username = $_POST['user'];
    $token = $_POST['token'];
    
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // --- 2. NEW PASSWORD VALIDATION CHECKS ---
    if (strlen($new_password) < 8) {
        $error = "New password must be at least 8 characters long.";
    } 
    else if (!preg_match('/^[a-zA-Z0-9]+$/', $new_password)) {
        $error = "New password can only contain letters (A-Z) and numbers (0-9). No special characters are allowed.";
    } 
    else if ($new_password !== $confirm_password) {
        $error = "New password and Confirm Password do not match.";
    } 
    
    if (empty($error)) {
        // 3. Hash the new password
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

        // 4. Update the user's password and clear the reset token/expiry
        $sql_update = "UPDATE users 
                       SET password = ?, reset_token = NULL, reset_expires_at = NULL 
                       WHERE username = ? AND reset_token = ?";
        $stmt_update = mysqli_prepare($conn, $sql_update);
        mysqli_stmt_bind_param($stmt_update, "sss", $hashed_password, $username, $token);
        
        if (mysqli_stmt_execute($stmt_update)) {
            $message = "Your password has been successfully updated. You can now <a href='customer_login.php'>log in</a>.";
            $valid_token = false; 
        } else {
            $error = "An error occurred while updating your password.";
        }
        mysqli_stmt_close($stmt_update);
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Reset Password</title>
    <link rel="stylesheet" href="style.css">
    <style>
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
            max-width: 450px; 
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
            background-color: #66BB6A;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            transition: background-color 0.3s;
            margin-top: 10px;
        }

        input[type="submit"]:hover {
            background-color: #4CAF50;
        }
        
        p a {
            color: #8B4513;
            text-decoration: none;
            font-weight: bold;
        }

        p a:hover {
            text-decoration: underline;
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

        .info-message {
            background-color: #e6f7ff;
            color: #007bff;
            border: 1px solid #007bff;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: left;
        }
        .error-message {
            color: red;
            margin-bottom: 15px;
        }
        
    </style>
</head>
<body>

<div class="container">
    
    <div class="logo-header">
        <img src="hosana.png" alt="Hosana Cafe Logo">
    </div>
    
    <h2>Reset Password</h2>

    <?php if (!empty($message)): ?>
        <p class="info-message"><?php echo $message; ?></p>
        
    <?php elseif (!empty($error)): ?>
        <p class="error-message"><?php echo $error; ?></p>
        <p><a href="forgot_password.php">Request a new reset link</a></p>

    <?php elseif ($valid_token): ?>
        <p>Please enter your new password for user **<?php echo htmlspecialchars($username); ?>**.</p>
        
        <form action="" method="post">
            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
            <input type="hidden" name="user" value="<?php echo htmlspecialchars($username); ?>">

            <div class="form-group">
                <label for="new_password">New Password (Min 8 chars, only letters/numbers):</label>
                <input type="password" id="new_password" name="new_password" required minlength="8">
                <span class="toggle-password" onclick="togglePasswordVisibility('new_password')">👁️</span>
            </div>
            
            <div class="form-group">
                <label for="confirm_password">Confirm New Password:</label>
                <input type="password" id="confirm_password" name="confirm_password" required minlength="8">
                <span class="toggle-password" onclick="togglePasswordVisibility('confirm_password')">👁️</span>
            </div>
            
            <input type="submit" value="Change Password">
        </form>
    
    <?php endif; ?>

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
</script>
</body>
</html>