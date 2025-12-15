<?php
session_start();
include 'db.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['role'] != 'customer') {
    header("location: customer_login.php");
    exit;
}

$username = $_SESSION['username'];
$error = $success = "";

// Securely fetch user data including ID
$sql_fetch_user = "SELECT id, username, password, contact_number FROM users WHERE username = ?";
$stmt_fetch_user = mysqli_prepare($conn, $sql_fetch_user);
mysqli_stmt_bind_param($stmt_fetch_user, "s", $username);
mysqli_stmt_execute($stmt_fetch_user);
$result_fetch_user = mysqli_stmt_get_result($stmt_fetch_user);
$user_data = mysqli_fetch_assoc($result_fetch_user);
$user_id = $user_data['id'] ?? 0;
mysqli_stmt_close($stmt_fetch_user);

if ($user_id == 0) {
    die("User not found. Please re-login.");
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $new_username = trim($_POST['username']);
    $new_password = $_POST['password'];
    $new_contact_number = $_POST['contact_number'];

    // 1. Check for duplicate username (Securely)
    $sql_check = "SELECT id FROM users WHERE username = ? AND id != ?";
    $stmt_check = mysqli_prepare($conn, $sql_check);
    mysqli_stmt_bind_param($stmt_check, "si", $new_username, $user_id);
    mysqli_stmt_execute($stmt_check);
    mysqli_stmt_store_result($stmt_check);
    
    if (mysqli_stmt_num_rows($stmt_check) > 0) {
        $error = "Error: Username already taken.";
    } else {
        
        $update_successful = false;

        if (!empty($new_password)) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            // 2. Update with password (Securely)
            $sql_update = "UPDATE users SET username = ?, password = ?, contact_number = ? WHERE id = ?";
            $stmt_update = mysqli_prepare($conn, $sql_update);
            mysqli_stmt_bind_param($stmt_update, "sssi", $new_username, $hashed_password, $new_contact_number, $user_id);
        } else {
            // 3. Update without password (Securely)
            $sql_update = "UPDATE users SET username = ?, contact_number = ? WHERE id = ?";
            $stmt_update = mysqli_prepare($conn, $sql_update);
            mysqli_stmt_bind_param($stmt_update, "ssi", $new_username, $new_contact_number, $user_id);
        }
        
        if (mysqli_stmt_execute($stmt_update)) {
            $update_successful = true;
            $_SESSION['username'] = $new_username;
            $user_data['username'] = $new_username;
            $user_data['contact_number'] = $new_contact_number;
            $success = "Account updated successfully!";
        } else {
            $error = "Error updating record: " . mysqli_error($conn);
        }
        
        mysqli_stmt_close($stmt_update);
    }
    mysqli_stmt_close($stmt_check);
}

// We rely on the $user_data fetched securely at the top for displaying the form.

?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit Account</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container">
    <h2>Update Account</h2>
    <p>You can update your username, password, and contact number here.</p>
    
    <?php if (isset($success)): ?>
        <p style='color:green;'><?php echo $success; ?></p>
    <?php endif; ?>
    <?php if (isset($error)): ?>
        <p style='color:red;'><?php echo $error; ?></p>
    <?php endif; ?>

    <form action="" method="post">
        Username: <input type="text" name="username" value="<?php echo htmlspecialchars($user_data['username']); ?>" required><br><br>
        Contact Number: <input type="text" name="contact_number" value="<?php echo htmlspecialchars($user_data['contact_number'] ?? ''); ?>"><br><br>
        New Password (Leave blank to keep current): <input type="password" name="password"><br><br>
        <input type="submit" value="Update Account">
    </form>
    
    <p><a href="customer_dashboard.php">Back to Dashboard</a> | <a href="logout_customer.php">Logout</a> | <a href="customer_delete_account.php" style="color:red;">Delete Account</a></p>
</div>
</body>
</html>