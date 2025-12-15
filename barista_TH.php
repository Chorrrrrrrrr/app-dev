<?php
session_start();
include 'db.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['role'] != 'barista') {
    header("location: login.php");
    exit;
}

// 1. Fetch all POS transactions (Secure query - no direct user input)
$sql_transactions = "SELECT t.*, u.username 
                     FROM transactions t 
                     JOIN users u ON t.user_id = u.id 
                     ORDER BY t.transaction_date DESC";
$result_transactions = mysqli_query($conn, $sql_transactions);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Barista POS Transaction History</title>
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
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
        }

        h2 {
            color: #4E342E; 
            border-bottom: 2px solid #E0E0E0;
            padding-bottom: 10px;
            margin-top: 0;
            margin-bottom: 25px;
        }

        h3 {
            color: #8B4513; /* Brown Accent */
            border-bottom: 1px solid #F0F0F0;
            padding-bottom: 5px;
            margin-top: 25px;
            margin-bottom: 15px;
        }

        a {
            color: #8B4513; 
            text-decoration: none;
            font-weight: bold;
            transition: color 0.2s;
        }

        a:hover {
            color: #5D4037;
            text-decoration: underline;
        }
        
        /* ==================================
           2. MAIN TABLE STYLES (POS History)
           ================================== */
        .order-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            border-radius: 8px;
            overflow: hidden; 
        }

        .order-table thead th {
            background-color: #4E342E; /* Dark Brown Header */
            color: white;
            padding: 12px 15px;
            text-align: left;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.9em;
        }

        .order-table tbody tr {
            background-color: #FFFFFF;
            /* Allow clicking the row to toggle details */
            cursor: pointer; 
            transition: background-color 0.1s;
        }

        .order-table tbody tr:hover {
            background-color: #F8F4EF; /* Light tan hover */
        }
        
        /* Style for the button inside the table */
        .order-table td button {
            background-color: #D7CCC8;
            color: #4E342E;
            padding: 5px 10px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.85em;
            transition: background-color 0.2s;
        }
        .order-table td button:hover {
            background-color: #BCAAA4;
        }
        
        /* Highlight for Change amount */
        .change-due {
            font-weight: bold;
            color: #4CAF50; /* Green */
        }


        /* ==================================
           3. COLLAPSIBLE ITEM DETAILS (Nested Row)
           ================================== */
        .item-details-row {
            background-color: #FDFDFD;
        }

        .details-content {
            padding: 15px 30px;
            background-color: #FDFDFD;
            border-top: 3px solid #E0E0E0;
            border-bottom: 1px solid #E0E0E0;
        }

        .nested-table {
            width: 90%;
            margin: 10px auto;
            border: 1px solid #ccc;
            border-radius: 4px;
            overflow: hidden;
        }
        
        .nested-table th {
            background-color: #F5F5F5;
            color: #555;
            padding: 8px 10px;
            font-size: 0.85em;
            text-transform: capitalize;
        }
        .nested-table td {
            padding: 8px 10px;
            font-size: 0.9em;
        }
    </style>
</head>
<body>
<div class="container">
    <h2>POS Transaction History</h2>
    <p>
        <a href="barista_dashboard.php">Back to POS</a> | 
        <a href="barista_OOH.php">Online Order History</a> | 
        <a href="logout.php">Logout</a>
    </p>

    <h3>All POS Transactions</h3>
    <?php if (mysqli_num_rows($result_transactions) == 0): ?>
        <p>No POS transactions found.</p>
    <?php else: ?>
        <table class="order-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Date</th>
                    <th>Total</th>
                    <th>Paid</th>
                    <th>Change</th>
                    <th>Cashier</th>
                    <th>Details</th>
                </tr>
            </thead>
            <tbody>
                <?php while($transaction = mysqli_fetch_assoc($result_transactions)): 
                    $change_amount = $transaction['amount_paid'] - $transaction['total_amount'];
                ?>
                <tr>
                    <td><?php echo $transaction['id']; ?></td>
                    <td><?php echo date('Y-m-d H:i', strtotime($transaction['transaction_date'])); ?></td>
                    <td>₱ <?php echo number_format($transaction['total_amount'], 2); ?></td>
                    <td>₱ <?php echo number_format($transaction['amount_paid'], 2); ?></td>
                    <td class="change-due">₱ <?php echo number_format($change_amount, 2); ?></td>
                    <td><?php echo htmlspecialchars($transaction['username']); ?></td>
                    <td>
                        <button onclick="toggleItems(<?php echo $transaction['id']; ?>)">View Items</button>
                    </td>
                </tr>
                
                <tr id="items-<?php echo $transaction['id']; ?>" style="display:none;" class="item-details-row">
                    <td colspan="7">
                        <div class="details-content">
                            <h4 style="color: #4E342E; margin: 0 0 5px 0; border-bottom: none;">Items Purchased:</h4>
                            
                            <table class="nested-table">
                                <thead>
                                    <tr>
                                        <th>Item</th>
                                        <th>Quantity</th>
                                        <th>Unit Price</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    // --- SECURED SELECT FOR TRANSACTION ITEMS ---
                                    $transaction_id = $transaction['id'];
                                    $sql_items = "SELECT oi.quantity, oi.price, p.name 
                                                  FROM order_items oi 
                                                  JOIN products p ON oi.product_id = p.id 
                                                  WHERE oi.transaction_id = ?";
                                    $stmt_items = mysqli_prepare($conn, $sql_items);
                                    mysqli_stmt_bind_param($stmt_items, "i", $transaction_id);
                                    mysqli_stmt_execute($stmt_items);
                                    $result_items = mysqli_stmt_get_result($stmt_items);
                                    
                                    while($item = mysqli_fetch_assoc($result_items)) {
                                        echo "<tr>";
                                        echo "<td>" . htmlspecialchars($item['name']) . "</td>";
                                        echo "<td>" . $item['quantity'] . "</td>";
                                        echo "<td>₱ " . number_format($item['price'], 2) . "</td>";
                                        echo "</tr>";
                                    }
                                    mysqli_stmt_close($stmt_items);
                                    ?>
                                </tbody>
                            </table>
                            <p style="margin-top: 15px;">
                                <a href="receipt_module_barista.php?type=pos&id=<?php echo $transaction['id']; ?>" target="_blank">View Printable Receipt</a>
                            </p>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<script>
    // Toggle function for collapsible rows (reused from your original code)
    function toggleItems(transactionId) {
        var element = document.getElementById('items-' + transactionId);
        if (element.style.display === "none") {
            element.style.display = "table-row";
        } else {
            element.style.display = "none";
        }
    }
</script>
</body>
</html>