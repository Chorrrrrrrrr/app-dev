<?php
$order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
$qr_link = "delivery_scan.php?order_id=" . $order_id;
$qr_size = 300;

$qr_image_url = "https://api.qrserver.com/v1/create-qr-code/?size={$qr_size}x{$qr_size}&data=" . urlencode($qr_link);

if ($order_id > 0) {
    echo $qr_image_url;
} else {
    echo "Invalid Order ID.";
}
?>