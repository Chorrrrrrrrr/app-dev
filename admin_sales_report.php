<?php
session_start();
include 'db.php';

// === 1. Authorization Check ===
if (!isset($_SESSION['loggedin']) || $_SESSION['role'] != 'admin') {
    header("location: login.php");
    exit;
}

// === 2. Fetch Overall Metrics ===
$result_pos_sales = mysqli_query($conn, "SELECT IFNULL(SUM(total_amount), 0) AS total_pos_sales FROM transactions");
$total_pos_sales = mysqli_fetch_assoc($result_pos_sales)['total_pos_sales'];

$result_online_sales = mysqli_query($conn, "SELECT IFNULL(SUM(total_amount), 0) AS total_online_sales FROM online_orders WHERE status != 'Canceled'");
$total_online_sales = mysqli_fetch_assoc($result_online_sales)['total_online_sales'];

$grand_total_revenue = $total_pos_sales + $total_online_sales;

// === 3. Fetch Barista List ===
$sql_baristas = "SELECT id, username FROM users WHERE role = 'barista' ORDER BY username ASC";
$result_baristas = mysqli_query($conn, $sql_baristas);

// === 4. Handle Barista Selection & Individual Metrics ===
$selected_barista_id = isset($_GET['barista_id']) ? (int)$_GET['barista_id'] : null;
$selected_barista_username = null;
$barista_pos_sales = 0;
$barista_online_prepared = 0;
$result_barista_transactions = null;
$result_barista_petty_cash = null;
$stmt_transactions = null;
$stmt_petty_cash = null;


if ($selected_barista_id) {
    $stmt_user = mysqli_prepare($conn, "SELECT username FROM users WHERE id = ? AND role = 'barista'");
    mysqli_stmt_bind_param($stmt_user, "i", $selected_barista_id);
    mysqli_stmt_execute($stmt_user);
    $result_user = mysqli_stmt_get_result($stmt_user);
    if ($user = mysqli_fetch_assoc($result_user)) {
        $selected_barista_username = $user['username'];
    }
    mysqli_stmt_close($stmt_user);

    // Fetch POS Sales
    $stmt_sales = mysqli_prepare($conn, "SELECT IFNULL(SUM(total_amount), 0) AS total_sales FROM transactions WHERE user_id = ?");
    mysqli_stmt_bind_param($stmt_sales, "i", $selected_barista_id);
    mysqli_stmt_execute($stmt_sales);
    $result_sales = mysqli_stmt_get_result($stmt_sales);
    $barista_pos_sales_row = mysqli_fetch_assoc($result_sales);
    $barista_pos_sales = $barista_pos_sales_row['total_sales'];
    mysqli_stmt_close($stmt_sales);

    // Fetch Online Orders Prepared
    $stmt_online = mysqli_prepare($conn, "SELECT COUNT(id) AS total_prepared FROM online_orders WHERE prepared_by_user_id = ?");
    mysqli_stmt_bind_param($stmt_online, "i", $selected_barista_id);
    mysqli_stmt_execute($stmt_online);
    $result_online = mysqli_stmt_get_result($stmt_online);
    $barista_online_row = mysqli_fetch_assoc($result_online);
    $barista_online_prepared = $barista_online_row['total_prepared'];
    mysqli_stmt_close($stmt_online);

    // Fetch POS Transactions
    $stmt_transactions = mysqli_prepare($conn, "SELECT id, total_amount, transaction_date FROM transactions WHERE user_id = ? ORDER BY transaction_date DESC");
    mysqli_stmt_bind_param($stmt_transactions, "i", $selected_barista_id);
    mysqli_stmt_execute($stmt_transactions);
    $result_barista_transactions = mysqli_stmt_get_result($stmt_transactions);
    
    // Fetch Petty Cash
    $stmt_petty_cash = mysqli_prepare($conn, "SELECT id, amount, description, entry_date, type FROM petty_cash WHERE user_id = ? ORDER BY entry_date DESC");
    mysqli_stmt_bind_param($stmt_petty_cash, "i", $selected_barista_id);
    mysqli_stmt_execute($stmt_petty_cash);
    $result_barista_petty_cash = mysqli_stmt_get_result($stmt_petty_cash);
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Admin Sales Report</title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* ==================================
           1. GLOBAL & LAYOUT STYLES (Admin Blue Theme)
           ================================== */
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f4f7f6; /* Light gray background */
            color: #333;
            margin: 0;
            padding: 20px;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.05);
        }

        h2 {
            color: #1A237E; /* Deep Blue Header */
            border-bottom: 3px solid #E0E0E0;
            padding-bottom: 10px;
            margin-bottom: 25px;
        }

        h3 {
            color: #20639b;
            margin-top: 25px;
            margin-bottom: 15px;
        }

        h4 {
            color: #4E342E; /* Dark Brown/Gray */
            font-weight: 600;
        }

        a {
            color: #20639b;
            text-decoration: none;
            transition: color 0.2s;
        }

        a:hover {
            color: #173f5f;
            text-decoration: underline;
        }
        
        hr {
            border: 0;
            height: 1px;
            background-color: #e0e0e0;
            margin: 20px 0;
        }

        /* ==================================
           2. METRICS CARDS
           ================================== */
        .metrics-card-container {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
            gap: 20px;
        }
        
        .metric-card {
            flex: 1;
            min-width: 250px;
            padding: 20px;
            background-color: #f8fbfd; /* Very light blue background */
            border: 1px solid #c8e6fc;
            border-radius: 8px;
            text-align: center;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }
        
        .metric-card p {
            font-size: 2em;
            font-weight: 700;
            color: #173f5f; /* Dark Blue */
            margin: 5px 0 0 0;
        }

        .grand-total-card {
            background-color: #d4edda; /* Light Green for Total */
            border-color: #c3e6cb;
        }
        .grand-total-card p {
            color: #155724; /* Dark Green Text */
        }
        
        /* ==================================
           3. BARISTA SELECTION LIST
           ================================== */
        .barista-list {
            list-style: none;
            padding: 0;
            margin: 10px 0 30px 0;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .barista-list a {
            display: inline-block;
            padding: 10px 18px;
            border-radius: 20px; /* Pill shape */
            background-color: #e6f0fa; /* Very light blue background */
            color: #20639b;
            font-weight: 600;
            transition: background-color 0.2s, color 0.2s;
        }
        
        .barista-list a:hover {
            background-color: #c8e6fc;
            text-decoration: none;
        }
        
        .barista-list a.active {
            background-color: #20639b; /* Active: Dark Blue */
            color: white;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
        }

        /* ==================================
           4. DETAILED REPORTS
           ================================== */
        .report-section h3 {
             color: #1A237E;
             border-bottom: 1px solid #ccc;
             padding-bottom: 8px;
        }
        
        .barista-summary-card {
            background-color: #f7f7f7;
            padding: 15px;
            border-radius: 8px;
            border: 1px solid #e0e0e0;
            margin-bottom: 25px;
        }
        
        .summary-metric {
            display: flex;
            gap: 20px;
            justify-content: flex-start;
            margin-top: 15px;
        }

        .summary-metric-item {
            padding: 15px 25px;
            background-color: #e6f0fa;
            border-radius: 6px;
            text-align: center;
        }
        .summary-metric-item p {
            font-size: 1.8em;
            font-weight: 700;
            color: #20639b;
            margin: 5px 0 0 0;
        }

        /* Tables */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            font-size: 0.9em;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        table th, table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #f0f0f0;
        }

        table th {
            background-color: #20639b; /* Dark Blue Header */
            color: white;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .clickable-row:hover {
            background-color: #f1f1f1;
            cursor: pointer;
        }
        
    </style>
</head>
<body>
<div class="container">
    <h2>Sales and Financial Reporting</h2>
    <p><a href="admin_dashboard.php">← Back to Admin Dashboard</a> | <a href="logout.php">Logout</a></p>

    <h3>Overall Revenue Metrics (All Time)</h3>
    <div class="metrics-card-container">
        
        <div class="metric-card">
            <h4>Total POS Sales</h4>
            <p>₱ <?php echo number_format($total_pos_sales, 2); ?></p>
        </div>

        <div class="metric-card">
            <h4>Total Online Sales</h4>
            <p>₱ <?php echo number_format($total_online_sales, 2); ?></p>
        </div>

        <div class="metric-card grand-total-card">
            <h4>GRAND TOTAL REVENUE</h4>
            <p>₱ <?php echo number_format($grand_total_revenue, 2); ?></p>
        </div>
    </div>
    
    <hr>

    <h3>Barista Performance Breakdown (POS & Petty Cash)</h3>
    
    <h4>Select Barista:</h4>
    <div style="overflow-x: auto;">
        <ul class="barista-list">
            <?php 
            // Rewind result pointer if needed
            mysqli_data_seek($result_baristas, 0); 
            while($barista = mysqli_fetch_assoc($result_baristas)): 
            ?>
            <li>
                <a href="?barista_id=<?php echo $barista['id']; ?>" 
                   class="<?php echo ($barista['id'] == $selected_barista_id) ? 'active' : ''; ?>">
                    <?php echo htmlspecialchars($barista['username']); ?>
                </a>
            </li>
            <?php endwhile; ?>
        </ul>
    </div>

    <?php if ($selected_barista_id && $selected_barista_username): ?>
    
        <div class="report-section">
            <h3 style="color: #1A237E;">Report for: <?php echo htmlspecialchars($selected_barista_username); ?></h3>
            
            <div class="barista-summary-card">
                <div class="summary-metric">
                    <div class="summary-metric-item">
                        <h4>POS Sales Generated</h4>
                        <p>₱ <?php echo number_format($barista_pos_sales, 2); ?></p>
                    </div>
                    
                    <div class="summary-metric-item">
                        <h4>Online Orders Prepared</h4>
                        <p><?php echo number_format($barista_online_prepared); ?> Orders</p>
                    </div>
                </div>
            </div>

            <h4>POS Transaction History</h4>
            <table>
                <tr>
                    <th>Transaction ID</th>
                    <th>Date/Time</th>
                    <th>Amount</th>
                </tr>
                <?php if ($result_barista_transactions && mysqli_num_rows($result_barista_transactions) > 0): ?>
                    <?php while($transaction = mysqli_fetch_assoc($result_barista_transactions)): ?>
                    <tr>
                        <td>
                            <a href="receipt_module_admin.php?type=pos&id=<?php echo $transaction['id']; ?>" target="_blank">
                                <?php echo $transaction['id']; ?>
                            </a>
                        </td>
                        <td><?php echo date('Y-m-d H:i:s', strtotime($transaction['transaction_date'])); ?></td>
                        <td>₱ <?php echo number_format($transaction['total_amount'], 2); ?></td>
                    </tr>
                    <?php endwhile; ?>
                    <?php if ($stmt_transactions) mysqli_stmt_close($stmt_transactions); ?>
                <?php else: ?>
                    <tr><td colspan="3" style="text-align: center;">No POS transactions found for this barista.</td></tr>
                <?php endif; ?>
            </table>
            
            <br>

            <h4>Petty Cash Entries</h4>
            <table>
                <tr>
                    <th>Entry ID</th>
                    <th>Date/Time</th>
                    <th>Description</th>
                    <th>Type</th>
                    <th>Amount</th>
                </tr>
                <?php if ($result_barista_petty_cash && mysqli_num_rows($result_barista_petty_cash) > 0): ?>
                    <?php while($petty_cash = mysqli_fetch_assoc($result_barista_petty_cash)): ?>
                    <?php 
                        $js_description = htmlspecialchars($petty_cash['description'], ENT_QUOTES);
                        $js_type = htmlspecialchars(ucfirst($petty_cash['type']), ENT_QUOTES);
                        $js_amount = "₱ " . number_format($petty_cash['amount'], 2);
                    ?>
                    <tr class="clickable-row" onclick="alert('Petty Cash Entry <?php echo $petty_cash['id']; ?>:\n\nDescription: <?php echo $js_description; ?>\nType: <?php echo $js_type; ?>\nAmount: <?php echo $js_amount; ?>');">
                        <td><?php echo $petty_cash['id']; ?></td>
                        <td><?php echo date('Y-m-d H:i:s', strtotime($petty_cash['entry_date'])); ?></td>
                        <td><?php echo htmlspecialchars($petty_cash['description']); ?></td>
                        <td style="color: <?php echo ($petty_cash['type'] == 'in') ? 'green' : 'red'; ?>;">
                            <?php echo htmlspecialchars(ucfirst($petty_cash['type'])); ?>
                        </td>
                        <td>₱ <?php echo number_format($petty_cash['amount'], 2); ?></td>
                    </tr>
                    <?php endwhile; ?>
                    <?php if ($stmt_petty_cash) mysqli_stmt_close($stmt_petty_cash); ?>
                <?php else: ?>
                    <tr><td colspan="5" style="text-align: center;">No petty cash entries found for this barista.</td></tr>
                <?php endif; ?>
            </table>
            
        </div>
    <?php elseif ($result_baristas && mysqli_num_rows($result_baristas) > 0): ?>
        <p>Please select a barista from the list above to view their performance report.</p>
    <?php else: ?>
        <p>No baristas found in the system.</p>
    <?php endif; ?>

</div>
</body>
</html>