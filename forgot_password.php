<?php
session_start();
date_default_timezone_set('Asia/Manila');
include 'db.php'; 

// Import PHPMailer classes
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// REQUIRED FILES: Use the manual require method you uploaded
// Ensure these paths correctly point to the PHPMailer files 
// (e.g., if 'src' is inside your POS2 folder)
require 'src/Exception.php';
require 'src/PHPMailer.php';
require 'src/SMTP.php';

// --- GMAIL CREDENTIALS AND CONFIGURATION (From your provided mail.php) ---
const SMTP_USERNAME = 'bogurtsherwin@gmail.com'; 
const SMTP_PASSWORD = 'rhvtsqdqyhporgqx';      // MUST be a 16-character Gmail App Password
const SENDER_NAME = 'Hosana Cafe Support';
const SENDER_EMAIL = SMTP_USERNAME;
// ------------------------------------------

$message = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];

    // 1. Fetch user data, including the NEWLY ADDED 'email' column
    $sql_check = "SELECT id, username, email FROM users WHERE username = ? AND role = 'customer'";
    $stmt_check = mysqli_prepare($conn, $sql_check);
    mysqli_stmt_bind_param($stmt_check, "s", $username);
    mysqli_stmt_execute($stmt_check);
    $result_check = mysqli_stmt_get_result($stmt_check);
    $user = mysqli_fetch_assoc($result_check);
    mysqli_stmt_close($stmt_check);

    if ($user && !empty($user['email'])) {
        $user_email = $user['email'];
        
        // 2. Generate unique token and expiry
        $token = bin2hex(random_bytes(32)); 
        $expires = date("Y-m-d H:i:s", time() + 1800); 
        $user_id = $user['id'];

        // 3. Store the token and expiry in the database
        $sql_update = "UPDATE users SET reset_token = ?, reset_expires_at = ? WHERE id = ?";
        $stmt_update = mysqli_prepare($conn, $sql_update);
        mysqli_stmt_bind_param($stmt_update, "ssi", $token, $expires, $user_id);
        
        if (mysqli_stmt_execute($stmt_update)) {
            
            // 4. Initialize and Send the email with PHPMailer
            $mail = new PHPMailer(true);
            
            try {
                // Server settings
                $mail->SMTPDebug = 0;
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com';
                $mail->SMTPAuth   = true;
                $mail->Username   = SMTP_USERNAME;
                $mail->Password   = SMTP_PASSWORD;
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // Implicit SSL on port 465
                $mail->Port       = 465; 
                
                // Recipients
                $mail->setFrom(SENDER_EMAIL, SENDER_NAME);
                $mail->addAddress($user_email, $user['username']); 

                // Content
                $reset_link = "http://localhost/POS2/reset_password.php?token=" . $token . "&user=" . urlencode($username);
                
                $mail->isHTML(true);
                $mail->Subject = 'Hosana Cafe Password Reset Request';
                $mail->Body    = "
                    <h2>Password Reset Request</h2>
                    <p>Hello {$user['username']},</p>
                    <p>Click the link below to set a new password. This link is valid for 30 minutes:</p>
                    <p><a href='{$reset_link}' style='background-color:#8B4513; color:white; padding:10px 15px; border-radius:5px; text-decoration:none; display:inline-block;'>Reset My Password</a></p>
                    <p>If you did not request a password reset, please ignore this email.</p>
                ";
                $mail->AltBody = "Password Reset Link: " . $reset_link;

                $mail->send();
                $message = "A password reset link has been sent to the email address associated with your username. Please check your inbox (and spam folder).";

            } catch (Exception $e) {
                $error = "Message could not be sent. Mailer Error: {$mail->ErrorInfo}. Check your App Password and network connection.";
            }

        } else {
            $error = "Failed to generate reset token. Database write failed.";
        }
        mysqli_stmt_close($stmt_update);

    } else {
        // Generic message for security
        $message = "If an account with that username exists, a password reset link has been sent to the associated email address.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Forgot Password</title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* (CSS styles matching your layout) */
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
        
        input[type="text"] {
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
            margin-top: 10px;
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

        .form-group {
            text-align: left;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }

        /* Message Styles */
        .success-message {
            background-color: #e6ffe6;
            color: #38761d;
            border: 1px solid #38761d;
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
    
    <h2>Forgot Password</h2>

    <?php if (!empty($message)): ?>
        <div class="success-message">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
        <p class="error-message"><?php echo $error; ?></p>
    <?php endif; ?>

    <p>Enter your username below to receive a password reset link.</p>
    
    <form action="" method="post">
        <div class="form-group">
            <label for="username">Username:</label>
            <input type="text" id="username" name="username" required>
        </div>
        
        <input type="submit" value="Send Reset Link">
    </form>
    
    <p style="margin-top: 20px;"><a href="customer_login.php">Back to Login</a></p>
</div>

</body>
</html>