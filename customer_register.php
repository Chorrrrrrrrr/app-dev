<?php
session_start();
include 'db.php'; 

// Include PHPMailer classes
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Assuming PHPMailer files are available in 'src' folder
require 'src/Exception.php'; 
require 'src/PHPMailer.php';
require 'src/SMTP.php';


// --- GMAIL CREDENTIALS AND CONFIGURATION ---
const SMTP_USERNAME = 'bogurtsherwin@gmail.com'; 
const SMTP_PASSWORD = 'rhvtsqdqyhporgqx';      // Your 16-digit App Password
const SENDER_NAME = 'Hosana Cafe Registration';
const SENDER_EMAIL = SMTP_USERNAME;
// ------------------------------------------

$error = "";

// Ensure correct timezone is set for accurate expiry times
date_default_timezone_set('Asia/Manila'); 

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $email = $_POST['email']; // NEW: Must collect email for OTP

    // --- 1. VALIDATION CHECKS ---
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters long.";
    } elseif (!preg_match('/^[a-zA-Z0-9]+$/', $password)) {
        $error = "Password can only contain letters and numbers. No special characters allowed.";
    } elseif ($password !== $confirm_password) {
        $error = "Password and Confirm Password do not match.";
    } 
    
    if (empty($error)) {
        
        // Check 2: Username and Email existence check
        $stmt_check = mysqli_prepare($conn, "SELECT id FROM users WHERE username = ? OR email = ?");
        mysqli_stmt_bind_param($stmt_check, "ss", $username, $email);
        mysqli_stmt_execute($stmt_check);
        mysqli_stmt_store_result($stmt_check);
        
        if (mysqli_stmt_num_rows($stmt_check) > 0) {
            $error = "Username or Email already exists.";
        } else {
            
            // --- 2. OTP AND TEMPORARY USER CREATION ---
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $role = 'customer';
            
            // Generate a 6-digit numeric OTP code
            $otp_code = random_int(100000, 999999); 
            $expires = date("Y-m-d H:i:s", time() + 300); // 5 minutes expiry

            // NOTE: You need a new 'is_verified' TINYINT(1) column in your users table!
            $sql_insert = "INSERT INTO users (username, password, email, role, reset_token, reset_expires_at, is_verified) 
                           VALUES (?, ?, ?, ?, ?, ?, 0)"; 
            
            // Temporary insertion: The user is created but marked as NOT verified (0).
            // We use reset_token for the OTP storage, and reset_expires_at for OTP expiry.
            $stmt_insert = mysqli_prepare($conn, $sql_insert);
            mysqli_stmt_bind_param($stmt_insert, "ssssss", $username, $hashed_password, $email, $role, $otp_code, $expires);
            
            if (mysqli_stmt_execute($stmt_insert)) {
                
                // --- 3. SEND OTP VIA PHPMailer ---
                $mail = new PHPMailer(true);
                
                try {
                    $mail->SMTPDebug = 0;
                    $mail->isSMTP();
                    $mail->Host       = 'smtp.gmail.com';
                    $mail->SMTPAuth   = true;
                    $mail->Username   = SMTP_USERNAME;
                    $mail->Password   = SMTP_PASSWORD;
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                    $mail->Port       = 465; 
                    
                    $mail->setFrom(SENDER_EMAIL, SENDER_NAME);
                    $mail->addAddress($email, $username); 

                    $mail->isHTML(true);
                    $mail->Subject = 'Verify Your Hosana Cafe Account - OTP';
                    $mail->Body    = "
                        <h2>Account Verification Code (OTP)</h2>
                        <p>Hello {$username},</p>
                        <p>Thank you for registering. Please use the 6-digit code below to verify your email address and activate your account:</p>
                        <p style='font-size: 28px; font-weight: bold; color: #8B4513; text-align: center; border: 2px solid #ccc; padding: 10px; display: inline-block; letter-spacing: 5px;'>{$otp_code}</p>
                        <p>This code expires in 5 minutes.</p>
                    ";
                    $mail->AltBody = "Your verification code is: " . $otp_code;

                    $mail->send();
                    
                    // --- 4. REDIRECT TO VERIFICATION PAGE ---
                    header("Location: otp_verification.php?user=" . urlencode($username));
                    exit;

                } catch (Exception $e) {
                    $error = "Registration failed. Could not send verification email. Mailer Error: {$mail->ErrorInfo}";
                    // OPTIONAL: Delete the partially created user here if email fails
                    // mysqli_query($conn, "DELETE FROM users WHERE username='{$username}'");
                }
            } else {
                $error = "Error saving user data: " . mysqli_error($conn);
            }
        }
        mysqli_stmt_close($stmt_check);
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Customer Registration</title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* (Matching styles from your previous registration/login pages) */
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
            cursor: pointer; /* Inherited style to show clickable area */
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
        input[type="password"],
        input[type="email"] { 
            width: 100%;
            padding: 12px;
            border: 1px solid #ccc;
            border-radius: 5px;
            box-sizing: border-box; 
            font-size: 16px;
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

    </style>
</head>
<body>

<div class="container">
    
    <div class="logo-header">
        <a href="customer_pre_login_dashboard.php">
            <img src="hosana.png" alt="Hosana Cafe Logo">
        </a>
    </div>
    
    <h2>Customer Registration</h2>
    
    <form action="" method="post">
        <div class="form-group">
            <label for="username">Username:</label>
            <input type="text" id="username" name="username" required>
        </div>

        <div class="form-group">
            <label for="email">Email:</label>
            <input type="email" id="email" name="email" required>
        </div>
        
        <div class="form-group">
            <label for="password">Password:</label>
            <input type="password" id="password" name="password" required minlength="8">
            <span class="toggle-password" onclick="togglePasswordVisibility('password')">👁️</span>
        </div>
        
        <div class="form-group">
            <label for="confirm_password">Confirm Password:</label>
            <input type="password" id="confirm_password" name="confirm_password" required minlength="8">
            <span class="toggle-password" onclick="togglePasswordVisibility('confirm_password')">👁️</span>
        </div>
        
        <input type="submit" value="Register">
    </form>
    
    <?php if (!empty($error)) { echo "<p style='color:red;'>$error</p>"; } ?>
    <p><a href="customer_login.php">Already have an account? Login here.</a></p>
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