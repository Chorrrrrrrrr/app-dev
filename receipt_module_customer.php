<?php
session_start();
include 'db.php';

$type = isset($_GET['type']) ? $_GET['type'] : '';
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id <= 0) {
    die("Invalid ID provided.");
}

$receipt_data = [];
$items = [];
$title = "";

if ($type == 'pos') {
    $title = "POS Transaction Receipt";
    
    $sql_transaction = "SELECT t.*, u.username 
                        FROM transactions t 
                        JOIN users u ON t.user_id = u.id 
                        WHERE t.id = ?";
    $stmt_transaction = mysqli_prepare($conn, $sql_transaction);
    mysqli_stmt_bind_param($stmt_transaction, "i", $id);
    mysqli_stmt_execute($stmt_transaction);
    $result_transaction = mysqli_stmt_get_result($stmt_transaction);
    $receipt_data = mysqli_fetch_assoc($result_transaction);
    mysqli_stmt_close($stmt_transaction);

    if ($receipt_data) {
        $sql_items = "SELECT oi.quantity, oi.price, p.name 
                      FROM order_items oi 
                      JOIN products p ON oi.product_id = p.id 
                      WHERE oi.transaction_id = ?";
        $stmt_items = mysqli_prepare($conn, $sql_items);
        mysqli_stmt_bind_param($stmt_items, "i", $id);
        mysqli_stmt_execute($stmt_items);
        $items_result = mysqli_stmt_get_result($stmt_items);
        while ($row = mysqli_fetch_assoc($items_result)) {
            $items[] = $row;
        }
        mysqli_stmt_close($stmt_items);
    }

} elseif ($type == 'online') {
    $title = "Online Order Receipt";

    $sql_order = "SELECT o.*, u.username 
                  FROM online_orders o 
                  JOIN users u ON o.user_id = u.id 
                  WHERE o.id = ?";
    $stmt_order = mysqli_prepare($conn, $sql_order);
    mysqli_stmt_bind_param($stmt_order, "i", $id);
    mysqli_stmt_execute($stmt_order);
    $result_order = mysqli_stmt_get_result($stmt_order);
    $receipt_data = mysqli_fetch_assoc($result_order);
    mysqli_stmt_close($stmt_order);

    if ($receipt_data) {
        $sql_items = "SELECT ooi.quantity, ooi.price, p.name 
                      FROM online_order_items ooi 
                      JOIN products p ON ooi.product_id = p.id 
                      WHERE ooi.order_id = ?";
        $stmt_items = mysqli_prepare($conn, $sql_items);
        mysqli_stmt_bind_param($stmt_items, "i", $id);
        mysqli_stmt_execute($stmt_items);
        $items_result = mysqli_stmt_get_result($stmt_items);
        while ($row = mysqli_fetch_assoc($items_result)) {
            $items[] = $row;
        }
        mysqli_stmt_close($stmt_items);
    }
}

if (!$receipt_data) {
    die("Receipt not found.");
}
?>

<!DOCTYPE html>
<html>
<head>
    <title><?php echo $title; ?> #<?php echo $id; ?></title>
    <link rel="stylesheet" href="style.css">
    <style>
        body { font-family: monospace; max-width: 400px; margin: 0 auto; padding: 20px; }
        h2, h3 { text-align: center; }
        .receipt-info { border-bottom: 1px dashed #000; padding-bottom: 5px; margin-bottom: 5px; }
        .item-row { display: flex; justify-content: space-between; margin-bottom: 3px; }
        .total-row { border-top: 1px dashed #000; padding-top: 5px; margin-top: 5px; font-weight: bold; }
        @media print {
            a { display: none; }
        }
    </style>
</head>
<body>
    <h2>Hossana Cafe</h2>
    <h3><?php echo $title; ?></h3>
    <div class="receipt-info">
        <p>Receipt/Order ID: #<?php echo $id; ?></p>
        <?php if ($type == 'pos'): ?>
            <p>Cashier: <?php echo htmlspecialchars($receipt_data['username']); ?></p>
            <p>Date: <?php echo date('Y-m-d H:i:s', strtotime($receipt_data['transaction_date'])); ?></p>
        <?php elseif ($type == 'online'): ?>
            <p>Customer: <?php echo htmlspecialchars($receipt_data['username']); ?></p>
            <p>Date: <?php echo date('Y-m-d H:i:s', strtotime($receipt_data['order_date'])); ?></p>
            <p>Address: <?php echo htmlspecialchars($receipt_data['delivery_address']); ?></p>
            <p>Status: <?php echo htmlspecialchars($receipt_data['status']); ?></p>
        <?php endif; ?>
    </div>

    <h3>Items</h3>
    <?php foreach ($items as $item): ?>
        <div class="item-row">
            <span><?php echo $item['quantity']; ?>x <?php echo htmlspecialchars($item['name']); ?></span>
            <span>₱ <?php echo number_format($item['price'], 2); ?></span>
            <span>₱ <?php echo number_format($item['price'] * $item['quantity'], 2); ?></span>
        </div>
    <?php endforeach; ?>

    <div class="total-row">
        <div class="item-row">
            <span>TOTAL AMOUNT:</span>
            <span>₱ <?php echo number_format($receipt_data['total_amount'], 2); ?></span>
        </div>
    </div>
    
    <?php if ($type == 'pos'): ?>
        <div class="receipt-info">
            <div class="item-row">
                <span>CASH PAID:</span>
                <span>₱ <?php echo number_format($receipt_data['amount_paid'], 2); ?></span>
            </div>
            <div class="item-row">
                <span>CHANGE:</span>
                <span>₱ <?php echo number_format($receipt_data['amount_paid'] - $receipt_data['total_amount'], 2); ?></span>
            </div>
        </div>
    <?php endif; ?>

    <p style="text-align: center; margin-top: 20px;">THANK YOU FOR YOUR PURCHASE!</p>
    <a href="#" onclick="window.print(); return false;">Print Receipt</a>
    <br><a href="barista_dashboard.php">Go Back</a>

</body>
</html>