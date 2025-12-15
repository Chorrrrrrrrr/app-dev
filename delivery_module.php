<?php
session_start();
include 'db.php'; // Include your database connection

// === 1. Authorization Check ===
if (!isset($_SESSION['loggedin']) || $_SESSION['role'] != 'delivery') {
    header("location: login.php");
    exit;
}

$error = $success = "";

// === 2. Handle Order Status Update (Mark Delivered) ===
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['mark_delivered'])) {
    $order_id_to_update = intval($_POST['order_id']);
    $new_status = 'Delivered';

    // Update only if the current status is 'Prepared' or 'Out for Delivery'
    $sql_update = "UPDATE online_orders SET status = ? WHERE id = ? AND status IN ('Prepared', 'Out for Delivery')";
    $stmt_update = mysqli_prepare($conn, $sql_update);
    
    if ($stmt_update) {
        mysqli_stmt_bind_param($stmt_update, "si", $new_status, $order_id_to_update);

        if (mysqli_stmt_execute($stmt_update)) {
            if (mysqli_stmt_affected_rows($stmt_update) > 0) {
                $success = "Order #$order_id_to_update marked as Delivered successfully!";
            } else {
                $error = "Order #$order_id_to_update could not be updated (status might not be 'Prepared' or 'Out for Delivery').";
            }
        } else {
            $error = "Error executing status update: " . mysqli_error($conn);
        }
        mysqli_stmt_close($stmt_update);
    } else {
        $error = "Error preparing status update statement: " . mysqli_error($conn);
    }
}

// === 3. Fetch Orders Ready for Delivery ===
$sql_orders = "SELECT o.*, u.username, u.contact_number 
              FROM online_orders o 
              JOIN users u ON o.user_id = u.id 
              WHERE o.status IN ('Prepared', 'Out for Delivery') 
              ORDER BY o.order_date ASC";
$result_orders = mysqli_query($conn, $sql_orders);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Delivery Module</title>
    <style>
        /* ==================================
           1. GLOBAL & LAYOUT STYLES (Clean Blue & White)
           ================================== */
        body {
            font-family: Arial, sans-serif;
            background-color: #f7f9fa; /* Very light gray background */
            color: #333;
            margin: 0;
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08); /* Soft shadow */
        }

        h2 {
            color: #1a237e; /* Deep blue header */
            border-bottom: 2px solid #e0e0e0;
            padding-bottom: 10px;
            margin-bottom: 25px;
        }

        h3 {
            color: #455a64; /* Dark gray sub-header */
            margin-top: 25px;
        }

        a {
            color: #0d47a1; /* Standard blue link */
            text-decoration: none;
            transition: color 0.2s;
        }

        a:hover {
            color: #002f6c;
            text-decoration: underline;
        }
        
        /* Message Styling */
        p[style*='color:green'] {
            background-color: #e8f5e9;
            color: #2e7d32 !important;
            padding: 10px;
            border-radius: 4px;
            border: 1px solid #c8e6c9;
            font-weight: bold;
        }
        p[style*='color:red'] {
            background-color: #ffebee;
            color: #d32f2f !important;
            padding: 10px;
            border-radius: 4px;
            border: 1px solid #ffcdd2;
            font-weight: bold;
        }

        /* ==================================
           2. TABLE STYLES
           ================================== */
        table {
            border-collapse: collapse;
            margin-top: 15px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
            border-radius: 6px;
            overflow: hidden; /* Ensures border-radius is visible on table corners */
        }

        table th {
            background-color: #e3f2fd; /* Light blue header background */
            color: #1a237e; /* Deep blue header text */
            padding: 12px 10px;
            text-align: left;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 0.85em;
        }

        table td {
            padding: 12px 10px;
            text-align: left;
            border-bottom: 1px solid #f0f0f0;
            font-size: 0.9em;
        }

        table tr:last-child td {
            border-bottom: none;
        }

        table tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        table tr:hover {
            background-color: #e8eaf6; /* Very light highlight on hover */
        }

        /* ==================================
           3. STATUS & ACTION BUTTONS
           ================================== */

        /* General Action Link/Button */
        td a.nav-link {
            display: inline-block;
            text-align: center;
            background-color: #4caf50 !important; /* Green for completion action */
            color: white !important;
            padding: 8px 12px;
            border-radius: 4px;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 0.8em;
            transition: background-color 0.2s;
        }

        td a.nav-link:hover {
            background-color: #388e3c !important;
            text-decoration: none;
        }
        
        /* Status Badges */
        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 12px;
            font-weight: bold;
            font-size: 0.8em;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .status-prepared {
            background-color: #fff9c4; /* Light yellow */
            color: #fbc02d; /* Dark yellow text */
        }

        .status-out-for-delivery {
            background-color: #b3e5fc; /* Light blue */
            color: #0288d1; /* Darker blue text */
        }
    </style>
</head>
<body>
<div class="container">
    <h2>Delivery Module</h2>
    <p><a href="delivery_history.php">View History</a> | <a href="logout.php">Logout</a></p>

    <?php if ($success): ?><p style='color:green;'><?php echo $success; ?></p><?php endif; ?>
    <?php if ($error): ?><p style='color:red;'><?php echo $error; ?></p><?php endif; ?>

    <h3>Orders Ready for Delivery</h3>
    <?php if (mysqli_num_rows($result_orders) == 0): ?>
        <p>No orders currently prepared for delivery.</p>
    <?php else: ?>
        <table width="100%">
            <tr>
                <th>Order ID</th>
                <th>Customer</th>
                <th>Contact</th>
                <th>Total</th>
                <th>Address</th>
                <th>Notes</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
            <?php while($order = mysqli_fetch_assoc($result_orders)): 
                // Determine CSS class based on status
                $status_class = strtolower(str_replace(' ', '-', $order['status']));
            ?>
            <tr>
                <td><?php echo htmlspecialchars($order['id']); ?></td>
                <td><?php echo htmlspecialchars($order['username']); ?></td>
                <td><?php echo htmlspecialchars($order['contact_number'] ?? 'N/A'); ?></td>
                <td>₱ <?php echo number_format($order['total_amount'], 2); ?></td>
                <td><?php echo htmlspecialchars($order['delivery_address']); ?></td>
                <td><?php echo htmlspecialchars($order['customer_notes']); ?></td>
                <td>
                    <span class="status-badge status-<?php echo $status_class; ?>">
                        <?php echo htmlspecialchars($order['status']); ?>
                    </span>
                </td>
                <td>
                    <?php if ($order['status'] != 'Delivered'): ?>
                        <a href="delivery_scan.php?order_id=<?php echo $order['id']; ?>" class="nav-link">
                            Scan/Complete
                        </a>
                    <?php else: ?>
                    **DELIVERED**
                    <?php endif; ?>
                </td>
            </tr>
            <?php endwhile; ?>
        </table>
    <?php endif; ?>
</div>
</body>
</html>