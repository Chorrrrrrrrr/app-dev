<?php
session_start();
include 'db.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['role'] != 'barista') {
    header("location: login.php");
    exit;
}

// === 1. Handle Form Submission ===
if (isset($_POST['add_petty_cash'])) {
    $amount = filter_var($_POST['amount'], FILTER_VALIDATE_FLOAT);
    $type = $_POST['type']; // 'in' or 'out'
    $description = trim($_POST['description']);

    if ($amount === false || $amount <= 0 || empty($description)) {
        $_SESSION['message_error'] = "Invalid amount or missing description.";
        header("location: petty_cash.php");
        exit;
    }

    $user_id_sql = "SELECT id FROM users WHERE username='{$_SESSION['username']}'";
    $user_id_result = mysqli_query($conn, $user_id_sql);
    $user_id_row = mysqli_fetch_assoc($user_id_result);
    $user_id = $user_id_row['id'];

    // Securely insert petty cash entry using prepared statement
    $sql_petty = "INSERT INTO petty_cash (user_id, type, amount, description, entry_date) VALUES (?, ?, ?, ?, NOW())";
    $stmt_petty = mysqli_prepare($conn, $sql_petty);
    mysqli_stmt_bind_param($stmt_petty, "isds", $user_id, $type, $amount, $description);
    
    if (mysqli_stmt_execute($stmt_petty)) {
         $_SESSION['message_success'] = "Petty cash entry added successfully!";
    } else {
        $_SESSION['message_error'] = "Error adding petty cash entry.";
    }
    mysqli_stmt_close($stmt_petty);
    header("location: petty_cash.php");
    exit;
}

// === 2. Calculate Balance ===
$sql_petty_in = "SELECT SUM(amount) AS total_in FROM petty_cash WHERE type='in'";
$result_in = mysqli_query($conn, $sql_petty_in);
$row_in = mysqli_fetch_assoc($result_in);
$total_in = $row_in['total_in'] ? $row_in['total_in'] : 0;

$sql_petty_out = "SELECT SUM(amount) AS total_out FROM petty_cash WHERE type='out'";
$result_out = mysqli_query($conn, $sql_petty_out);
$row_out = mysqli_fetch_assoc($result_out);
$total_out = $row_out['total_out'] ? $row_out['total_out'] : 0;

$petty_cash_balance = $total_in - $total_out;

// === 3. Fetch Recent Entries ===
$sql_entries = "SELECT * FROM petty_cash ORDER BY entry_date DESC LIMIT 10";
$result_entries = mysqli_query($conn, $sql_entries);

$current_username = htmlspecialchars($_SESSION['username']);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Petty Cash Management</title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* ==================================
           1. GLOBAL & LAYOUT STYLES (Matching Dashboard)
           ================================== */
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #F8F4EF; /* Very light tan background */
            color: #4E342E; /* Dark espresso brown text */
            margin: 0;
            padding: 20px;
        }

        .container {
            max-width: 900px; /* Adjusted size for focus */
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
        }

        h2, h3, h4 {
            color: #4E342E; 
            border-bottom: 2px solid #E0E0E0;
            padding-bottom: 5px;
            margin-top: 20px;
        }

        a {
            color: #8B4513; 
            text-decoration: none;
            font-weight: bold;
            padding: 5px;
        }

        a:hover {
            color: #5D4037;
            text-decoration: underline;
        }
        
        /* Message Styles */
        .message-success {
            background-color: #E8F5E9; 
            color: #4CAF50; 
            padding: 10px;
            margin: 10px 0;
            border-radius: 5px;
            border: 1px solid #C8E6C9;
            font-weight: bold;
        }

        .message-error {
            background-color: #FFEBEE;
            color: #E57373;
            padding: 10px;
            margin: 10px 0;
            border-radius: 5px;
            border: 1px solid #FFCDD2;
            font-weight: bold;
        }


        /* ==================================
           2. BALANCE CARD
           ================================== */
        .balance-card {
            background-color: #8B4513; /* Primary Brown Color */
            color: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
            text-align: center;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
        }
        .balance-card h3 {
            color: white;
            border-bottom: 1px solid rgba(255, 255, 255, 0.5);
            padding-bottom: 10px;
            margin-top: 0;
            margin-bottom: 15px;
        }
        .balance-amount {
            font-size: 2.5em;
            font-weight: bold;
            display: block;
        }


        /* ==================================
           3. FORM STYLES (New Entry)
           ================================== */
        form {
            display: flex;
            gap: 10px;
            align-items: center;
            padding: 15px;
            background-color: #FDFDFD;
            border: 1px solid #E0E0E0;
            border-radius: 8px;
            margin-bottom: 30px;
        }

        input[type="number"], 
        input[type="text"], 
        select {
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 1em;
        }
        
        input[name="amount"] {
            width: 120px;
            text-align: right;
        }

        input[name="description"] {
            flex-grow: 1;
        }

        select[name="type"] {
            width: 120px;
        }
        
        input[type="submit"] {
            background-color: #66BB6A; /* Green Add Button */
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1em;
            font-weight: bold;
            transition: background-color 0.2s;
        }
        input[type="submit"]:hover {
            background-color: #4CAF50;
        }


        /* ==================================
           4. TABLE STYLES (Entries)
           ================================== */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
        }

        table th {
            background-color: #F5F5F5;
            color: #4E342E;
            font-weight: bold;
            padding: 12px 10px;
            text-align: left;
            border-bottom: 2px solid #E0E0E0;
            text-transform: uppercase;
        }

        table td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #F0F0F0;
        }
        
        table tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        
        /* Coloring for Type (IN/OUT) */
        .type-in {
            color: #4CAF50; /* Green */
            font-weight: bold;
        }
        .type-out {
            color: #D32F2F; /* Red */
            font-weight: bold;
        }

    </style>
</head>
<body>
<div class="container">
    <h2>Petty Cash Management</h2>
    <p>Welcome, <?php echo $current_username; ?>! | <a href="barista_dashboard.php">Back to Dashboard</a> | <a href="logout.php">Logout</a></p>

    <?php if (isset($_SESSION['message_success'])): ?>
        <p class="message-success"><?php echo $_SESSION['message_success']; unset($_SESSION['message_success']); ?></p>
    <?php endif; ?>
    <?php if (isset($_SESSION['message_error'])): ?>
        <p class="message-error"><?php echo $_SESSION['message_error']; unset($_SESSION['message_error']); ?></p>
    <?php endif; ?>
    
    <div class="balance-card">
        <h3>Current Petty Cash Balance</h3>
        <span class="balance-amount">₱ <?php echo number_format($petty_cash_balance, 2); ?></span>
    </div>

    <form action="" method="post">
        <input type="number" step="0.01" name="amount" placeholder="Amount" required>
        <select name="type">
            <option value="in">Money In (+)</option>
            <option value="out">Money Out (-)</option>
        </select>
        <input type="text" name="description" placeholder="Description / Reason" required>
        <input type="submit" name="add_petty_cash" value="Add Entry">
    </form>

    <h4>Recent Entries</h4>
    <table>
        <tr>
            <th>Date</th>
            <th>Type</th>
            <th>Amount</th>
            <th>Description</th>
        </tr>
        <?php
        while($row = mysqli_fetch_assoc($result_entries)) {
            $type_class = ($row['type'] == 'in') ? 'type-in' : 'type-out';
            echo "<tr>";
            echo "<td>" . $row['entry_date'] . "</td>";
            echo "<td class='$type_class'>" . htmlspecialchars(ucfirst($row['type'])) . "</td>";
            echo "<td class='$type_class'>₱ " . number_format($row['amount'], 2) . "</td>";
            echo "<td>" . htmlspecialchars($row['description']) . "</td>";
            echo "</tr>";
        }
        ?>
    </table>
</div>
</body>
</html>