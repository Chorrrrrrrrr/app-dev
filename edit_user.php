<?php
session_start();
include 'db.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['role'] != 'admin') {
    header("location: login.php");
    exit;
}

$user_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$row = null;

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['id'])) {
    $id = intval($_POST['id']);
    $username = $_POST['username'];
    $password = $_POST['password'];
    $contact_number = $_POST['contact_number'];

    if (!empty($password)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        // UPDATE: Added contact_number to the UPDATE query (with password)
        $sql = "UPDATE users SET username = ?, password = ?, contact_number = ? WHERE id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "sssi", $username, $hashed_password, $contact_number, $id);
    } else {
        // UPDATE: Added contact_number to the UPDATE query (without password)
        $sql = "UPDATE users SET username = ?, contact_number = ? WHERE id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "ssi", $username, $contact_number, $id);
    }

    if (mysqli_stmt_execute($stmt)) {
        header("location: admin_dashboard.php");
        exit;
    } else {
        echo "Error: " . mysqli_error($conn);
    }
    mysqli_stmt_close($stmt);
}

// Fetched user data
$sql = "SELECT id, username, role, contact_number FROM users WHERE id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$row = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$row) {
    die("User not found.");
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit User</title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* ==================================
           1. CORE LAYOUT
           ================================== */
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #F0F2F5; /* Light admin background */
            color: #333;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
        }

        .container {
            width: 90%;
            max-width: 500px;
            background: white;
            padding: 30px 40px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            border-top: 4px solid #1A237E; /* Deep Blue Admin Accent */
        }

        h2 {
            color: #1A237E; /* Deep Blue */
            border-bottom: 1px solid #E0E0E0;
            padding-bottom: 10px;
            margin-bottom: 30px;
            text-align: center;
        }

        /* ==================================
           2. FORM STYLES
           ================================== */
        form {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .form-group {
            text-align: left;
            position: relative; /* For password toggle */
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #4E342E; /* Dark brown for labels */
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

        /* VIEW PASSWORD TOGGLE STYLES */
        .toggle-password {
            position: absolute;
            top: 50%; /* Adjusted for label being outside form-group */
            right: 15px;
            transform: translateY(20%); 
            cursor: pointer;
            color: #1A237E; /* Blue icon */
            font-size: 1.2em;
            user-select: none;
            z-index: 10;
        }

        /* Role Display */
        .role-display {
            background-color: #E8EAF6; /* Very light blue background */
            color: #1A237E;
            padding: 10px;
            border-radius: 5px;
            font-weight: bold;
            text-align: center;
            margin-bottom: 20px;
        }

        /* Save Button */
        input[type="submit"] {
            padding: 12px;
            background-color: #4CAF50; /* Green Save Button */
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
            transition: background-color 0.2s;
            margin-top: 15px;
        }

        input[type="submit"]:hover {
            background-color: #388E3C;
        }

        a {
            color: #1A237E;
            text-decoration: none;
            display: block;
            margin-top: 20px;
            font-weight: 600;
            text-align: center;
        }

        a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
<div class="container">
    <h2>Edit User: <?php echo htmlspecialchars(ucfirst($row['username'])); ?></h2>
    
    <div class="role-display">
        User Role: **<?php echo htmlspecialchars(ucfirst($row['role'])); ?>**
    </div>
    
    <form action="" method="post">
        <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
        
        <div class="form-group">
            <label for="username">Username:</label>
            <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($row['username']); ?>" required>
        </div>
        
        <div class="form-group">
            <label for="contact_number">Contact Number:</label>
            <input type="text" id="contact_number" name="contact_number" value="<?php echo htmlspecialchars($row['contact_number'] ?? ''); ?>">
        </div>

        <div class="form-group">
            <label for="password">Password (Leave blank to keep current):</label>
            <input type="password" id="password" name="password">
            <span class="toggle-password" onclick="togglePasswordVisibility('password')">👁️</span>
        </div>
        
        <input type="submit" value="Save Changes">
    </form>
    
    <a href="admin_dashboard.php">Back to Dashboard</a>
</div>

<script>
    function togglePasswordVisibility(fieldId) {
        const field = document.getElementById(fieldId);
        const icon = document.querySelector(`.toggle-password[onclick*="${fieldId}"]`);

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