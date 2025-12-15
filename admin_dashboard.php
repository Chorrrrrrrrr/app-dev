<?php
session_start();
include 'db.php';

// === 1. Authorization Check ===
if (!isset($_SESSION['loggedin']) || $_SESSION['role'] != 'admin') {
    header("location: login.php");
    exit;
}

// === 2. Handle Profile Image Upload (Logic from previous file) ===
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['profile_image'])) {
    $admin_id = $_SESSION['user_id'];
    $target_dir = "uploads/";
    
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }
    
    $imageFileType = strtolower(pathinfo($_FILES["profile_image"]["name"], PATHINFO_EXTENSION));
    $target_file = $target_dir . "profile_" . $admin_id . "." . $imageFileType;
    
    $uploadOk = 1;
    $error_message = "";
    
    $check = getimagesize($_FILES["profile_image"]["tmp_name"]);
    if($check === false) {
        $error_message = "File is not an image.";
        $uploadOk = 0;
    }
    
    if ($_FILES["profile_image"]["size"] > 5000000) {
        $error_message = "File is too large. Maximum size is 5MB.";
        $uploadOk = 0;
    }
    
    if($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg" && $imageFileType != "gif" ) {
        $error_message = "Only JPG, JPEG, PNG & GIF files are allowed.";
        $uploadOk = 0;
    }
    
    foreach (glob($target_dir . "profile_" . $admin_id . ".*") as $old_file) {
        unlink($old_file);
    }
    
    if ($uploadOk == 1) {
        if (move_uploaded_file($_FILES["profile_image"]["tmp_name"], $target_file)) {
            header("Location: admin_dashboard.php?success_image_updated=1");
            exit;
        } else {
            $error_message = "There was an error uploading your file.";
        }
    }
    
    if (!empty($error_message)) {
        header("Location: admin_dashboard.php?error=" . urlencode($error_message));
        exit;
    }
}

// === 3. Fetch Admin Profile Info (Logic from previous file) ===
if (!isset($_SESSION['user_id'])) {
    $sql_get_id = "SELECT id FROM users WHERE username = ? AND role = 'admin' LIMIT 1";
    $stmt_get_id = mysqli_prepare($conn, $sql_get_id);
    mysqli_stmt_bind_param($stmt_get_id, "s", $_SESSION['username']);
    mysqli_stmt_execute($stmt_get_id);
    $result_get_id = mysqli_stmt_get_result($stmt_get_id);
    $row_id = mysqli_fetch_assoc($result_get_id);
    $_SESSION['user_id'] = $row_id['id'];
    mysqli_stmt_close($stmt_get_id);
}

$admin_id = $_SESSION['user_id'];
$sql_admin = "SELECT username, contact_number FROM users WHERE id = ?";
$stmt_admin = mysqli_prepare($conn, $sql_admin);
mysqli_stmt_bind_param($stmt_admin, "i", $admin_id);
mysqli_stmt_execute($stmt_admin);
$result_admin = mysqli_stmt_get_result($stmt_admin);
$admin_data = mysqli_fetch_assoc($result_admin);
mysqli_stmt_close($stmt_admin);

$profile_image = null;
$extensions = ['jpg', 'jpeg', 'png', 'gif'];
foreach ($extensions as $ext) {
    $temp_path = "uploads/profile_" . $admin_id . "." . $ext;
    if (file_exists($temp_path)) {
        $profile_image = $temp_path;
        break;
    }
}

// === 4. Fetch Metrics (Logic from previous file) ===
$result_products_count = mysqli_query($conn, "SELECT COUNT(id) AS total_products FROM products");
$products_count = mysqli_fetch_assoc($result_products_count)['total_products'];

$result_barista_count = mysqli_query($conn, "SELECT COUNT(id) AS total_baristas FROM users WHERE role = 'barista'");
$barista_count = mysqli_fetch_assoc($result_barista_count)['total_baristas'];

$result_customer_count = mysqli_query($conn, "SELECT COUNT(id) AS total_customers FROM users WHERE role = 'customer'");
$customer_count = mysqli_fetch_assoc($result_customer_count)['total_customers'];

$result_pos_sales = mysqli_query($conn, "SELECT IFNULL(SUM(total_amount), 0) AS total_pos_sales FROM transactions");
$pos_sales = mysqli_fetch_assoc($result_pos_sales)['total_pos_sales'];

$result_online_sales = mysqli_query($conn, "SELECT IFNULL(SUM(total_amount), 0) AS total_online_sales FROM online_orders WHERE status != 'Canceled'");
$online_sales = mysqli_fetch_assoc($result_online_sales)['total_online_sales'];

// === Fetch Unread Inquiry Count (Logic from previous file) ===
$result_unread_inquiries = mysqli_query($conn, "SELECT COUNT(id) AS unread_count FROM customer_inquiries WHERE is_read = 0");
$unread_inquiry_count = mysqli_fetch_assoc($result_unread_inquiries)['unread_count'];


// === 5. New PHP: Fetch Sales Data for Charts (RESTORED LOGIC) ===
date_default_timezone_set('Asia/Manila');

// --- A. Monthly Sales Data (POS + Online) ---
$monthly_sales_data = [];
for ($i = 0; $i < 6; $i++) {
    $month_ts = strtotime("-$i months");
    $month_label = date('M Y', $month_ts);
    $year = date('Y', $month_ts);
    $month = date('m', $month_ts);
    
    // POS Sales for the month
    $sql_pos = "SELECT IFNULL(SUM(total_amount), 0) FROM transactions 
                WHERE YEAR(transaction_date) = ? AND MONTH(transaction_date) = ?";
    $stmt_pos = mysqli_prepare($conn, $sql_pos);
    mysqli_stmt_bind_param($stmt_pos, "ss", $year, $month);
    mysqli_stmt_execute($stmt_pos);
    $result_pos = mysqli_stmt_get_result($stmt_pos);
    $pos_sale = mysqli_fetch_row($result_pos)[0];
    mysqli_stmt_close($stmt_pos);
    
    // Online Sales for the month
    $sql_online = "SELECT IFNULL(SUM(total_amount), 0) FROM online_orders 
                   WHERE YEAR(order_date) = ? AND MONTH(order_date) = ? AND status != 'Canceled'";
    $stmt_online = mysqli_prepare($conn, $sql_online);
    mysqli_stmt_bind_param($stmt_online, "ss", $year, $month);
    mysqli_stmt_execute($stmt_online);
    $result_online = mysqli_stmt_get_result($stmt_online);
    $online_sale = mysqli_fetch_row($result_online)[0];
    mysqli_stmt_close($stmt_online);
    
    $monthly_sales_data[$month_label] = $pos_sale + $online_sale;
}
$monthly_sales_data = array_reverse($monthly_sales_data, true); // Sort oldest to newest

// --- B. Best Seller Drink Data ---
$sql_best_sellers = "
    SELECT 
        p.name, 
        SUM(COALESCE(oi.quantity, 0) + COALESCE(ooi.quantity, 0)) AS total_sold
    FROM products p
    LEFT JOIN order_items oi ON p.id = oi.product_id
    LEFT JOIN online_order_items ooi ON p.id = ooi.product_id
    WHERE p.category = 'drink' 
    GROUP BY p.name
    ORDER BY total_sold DESC
    LIMIT 5
";
$result_best_sellers = mysqli_query($conn, $sql_best_sellers);

$best_seller_labels = [];
$best_seller_data = [];

if ($result_best_sellers) {
    while ($row = mysqli_fetch_assoc($result_best_sellers)) {
        $best_seller_labels[] = htmlspecialchars($row['name']);
        $best_seller_data[] = $row['total_sold'];
    }
}
// === END RESTORED PHP LOGIC ===


// === 6. Determine Limits, Search and Fetch Products (Logic from previous file) ===
$limit = 10;
$show_all_baristas = isset($_GET['show_all_baristas']);
$show_all_customers = isset($_GET['show_all_customers']);
$show_all_products = isset($_GET['show_all_products']);

$search_term = '';
$stmt_products = null;
$result_products = null;

if (isset($_GET['product_search']) && !empty(trim($_GET['product_search']))) {
    $search_term = trim($_GET['product_search']);
    
    $sql_products = "SELECT id, name, price, category, subcategory FROM products WHERE name LIKE ? OR id = ? ORDER BY id DESC"; 
    $stmt_products = mysqli_prepare($conn, $sql_products); 

    $like_term = '%' . $search_term . '%';
    mysqli_stmt_bind_param($stmt_products, "si", $like_term, $search_term); 
    
    mysqli_stmt_execute($stmt_products);
    $result_products = mysqli_stmt_get_result($stmt_products);
} else {
    if ($show_all_products) {
        $sql_products = "SELECT id, name, price, category, subcategory FROM products ORDER BY id DESC"; 
    } else {
        $sql_products = "SELECT id, name, price, category, subcategory FROM products ORDER BY id DESC LIMIT $limit";
    }
    $result_products = mysqli_query($conn, $sql_products);
}

// === 7. Fetch Barista Users (Logic from previous file) ===
if ($show_all_baristas) {
    $sql_baristas = "SELECT id, username, role, contact_number FROM users WHERE role = 'barista' ORDER BY id DESC";
} else {
    $sql_baristas = "SELECT id, username, role, contact_number FROM users WHERE role = 'barista' ORDER BY id DESC LIMIT $limit";
}
$result_baristas = mysqli_query($conn, $sql_baristas);

// === 8. Fetch Customer Users (Logic from previous file) ===
if ($show_all_customers) {
    $sql_customers = "SELECT id, username, role, contact_number FROM users WHERE role = 'customer' ORDER BY id DESC";
} else {
    $sql_customers = "SELECT id, username, role, contact_number FROM users WHERE role = 'customer' ORDER BY id DESC LIMIT $limit";
}
$result_customers = mysqli_query($conn, $sql_customers);

?>

<!DOCTYPE html>
<html>
<head>
    <title>Admin Dashboard</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.0/dist/chart.min.js"></script>
    <style>
        /* ==================================
           1. GLOBAL & LAYOUT STYLES
           ================================== */
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f4f7f6;
            color: #333;
            margin: 0;
            padding: 0;
        }

        /* ==================================
           2. ADMIN PROFILE HEADER
           ================================== */
        .admin-header {
            background-color: #173f5f;
            color: white;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .admin-header h1 {
            margin: 0;
            font-size: 1.5em;
        }

        .admin-profile {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .admin-avatar {
            width: 45px;
            height: 45px;
            background-color: #20639b;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3em;
            font-weight: bold;
            color: white;
            cursor: pointer;
            transition: transform 0.2s;
            overflow: hidden;
        }

        .admin-avatar:hover {
            transform: scale(1.1);
        }

        .admin-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 9999;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.8);
            justify-content: center;
            align-items: center;
        }

        .modal-content {
            position: relative;
            max-width: 500px;
            max-height: 90vh;
            background-color: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
        }

        .modal-content img {
            width: 100%;
            height: auto;
            border-radius: 10px;
        }

        .modal-avatar-placeholder {
            text-align: center;
            padding: 50px;
            background: #e6f0fa;
            border-radius: 10px;
        }

        .modal-avatar-placeholder div {
            font-size: 150px;
            color: #20639b;
        }

        .close-modal {
            position: absolute;
            top: 10px;
            right: 20px;
            font-size: 35px;
            font-weight: bold;
            color: #333;
            cursor: pointer;
            background: white;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            line-height: 1;
        }

        .close-modal:hover {
            color: #cc0000;
        }

        .modal-profile-info {
            text-align: center;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 2px solid #e0e0e0;
        }

        .modal-profile-info h3 {
            margin: 10px 0;
            color: #173f5f;
        }

        .modal-profile-info p {
            color: #666;
            margin: 5px 0;
        }

        .modal-buttons {
            margin-top: 15px;
            display: flex;
            gap: 10px;
            justify-content: center;
        }

        .modal-btn {
            background: #20639b;
            color: white;
            padding: 10px 20px;
            border-radius: 5px;
            text-decoration: none;
            display: inline-block;
            border: none;
            cursor: pointer;
            font-size: 14px;
            transition: background 0.3s;
        }

        .modal-btn:hover {
            background: #173f5f;
        }

        .modal-btn-secondary {
            background: #6c757d;
        }

        .modal-btn-secondary:hover {
            background: #5a6268;
        }

        .admin-info {
            text-align: right;
        }

        .admin-info .admin-name {
            font-weight: bold;
            font-size: 1em;
            margin-bottom: 3px;
        }

        .admin-info .admin-role {
            font-size: 0.85em;
            color: #b8d4e8;
        }

        .admin-info a {
            color: #ffd700;
            text-decoration: none;
            font-size: 0.85em;
            margin-left: 10px;
        }

        .admin-info a:hover {
            text-decoration: underline;
        }

        /* Hidden file input */
        #profileImageInput {
            display: none;
        }

        /* ==================================
           3. CONTAINER & CONTENT
           ================================== */
        .container {
            max-width: 1400px;
            margin: 30px auto;
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.05);
        }

        h2 {
            color: #173f5f;
            padding-bottom: 10px;
            border-bottom: 3px solid #e0e0e0;
            margin-bottom: 25px;
        }

        h3 {
            color: #20639b;
            margin-top: 25px;
            margin-bottom: 15px;
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
           4. METRICS CARDS
           ================================== */
        .metrics-card-container {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
            gap: 20px;
            flex-wrap: wrap;
        }

        .metric-card {
            flex: 1;
            min-width: 200px;
            padding: 20px;
            background-color: #ffffff;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            text-align: center;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.05);
            transition: transform 0.2s;
        }

        .metric-card:hover {
            transform: translateY(-3px);
        }

        .metric-card h4 {
            margin-top: 0;
            color: #555;
            font-weight: 500;
            font-size: 1em;
        }

        .metric-card p {
            font-size: 2em;
            font-weight: 700;
            color: #173f5f;
            margin: 5px 0 0 0;
        }

        .metric-card:nth-child(4) p, 
        .metric-card:nth-child(5) p {
            color: #008080;
        }

        /* ==================================
           5. TABLES
           ================================== */
        .dashboard-section {
            background-color: #fff;
            padding: 20px;
            border-radius: 8px;
            border: 1px solid #e0e0e0;
            margin-bottom: 25px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            font-size: 0.9em;
        }

        table th, table td {
            padding: 12px 10px;
            text-align: left;
            border-bottom: 1px solid #f0f0f0;
        }

        table th {
            background-color: #e6f0fa;
            color: #173f5f;
            font-weight: 600;
            text-transform: uppercase;
        }

        table tr:hover {
            background-color: #f9f9f9;
        }

        td a[href*="edit"] { color: #20639b; }
        td a[href*="delete"] { color: #cc0000; }

        /* ==================================
           6. VIEW ALL BUTTON
           ================================== */
        .view-all-container {
            text-align: center;
            margin-top: 15px;
            padding: 15px;
            background-color: #f9f9f9;
            border-radius: 5px;
        }

        .view-all-btn {
            display: inline-block;
            background-color: #20639b;
            color: white;
            padding: 10px 25px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: bold;
            transition: background-color 0.3s;
        }

        .view-all-btn:hover {
            background-color: #173f5f;
            text-decoration: none;
        }

        .show-less-btn {
            display: inline-block;
            background-color: #6c757d;
            color: white;
            padding: 10px 25px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: bold;
            transition: background-color 0.3s;
        }

        .show-less-btn:hover {
            background-color: #5a6268;
            text-decoration: none;
        }

        /* ==================================
           7. MESSAGES & SEARCH
           ================================== */
        .message-success {
            background-color: #d4edda;
            color: #155724;
            padding: 12px;
            margin: 15px 0;
            border-radius: 5px;
            border: 1px solid #c3e6cb;
            font-weight: bold;
        }

        .message-error {
            background-color: #f8d7da;
            color: #721c24;
            padding: 12px;
            margin: 15px 0;
            border-radius: 5px;
            border: 1px solid #f5c6cb;
            font-weight: bold;
        }

        .product-search-form {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
        }
        
        .product-search-form input[type="text"] {
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 4px;
            flex-grow: 1;
        }

        .product-search-form input[type="submit"] {
            background-color: #20639b;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.2s;
        }

        .product-search-form input[type="submit"]:hover {
            background-color: #173f5f;
        }
        /* --- NEW SUBCATEGORY LINK STYLE --- */
        .subcategory-link {
            font-size: 0.7em;
            margin-left: 10px;
            color: #008080;
        }

        /* ==================================
           8. CHART STYLES (New)
           ================================== */
        .chart-container {
            display: flex;
            gap: 20px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }
        .chart-box {
            flex: 1;
            min-width: 450px;
            background: #ffffff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.05);
            border: 1px solid #e0e0e0;
        }
        .chart-box h4 {
            margin-top: 0;
            margin-bottom: 15px;
            color: #173f5f;
            border-bottom: 1px solid #e0e0e0;
            padding-bottom: 5px;
            text-align: center; 
        }
    </style>
</head>
<body>

<div class="admin-header">
    <h1>☕ Coffee Shop Admin Dashboard</h1>
    <div class="admin-profile">
        <div>
            <div class="admin-info">
                <div class="admin-name"><?php echo htmlspecialchars($admin_data['username']); ?></div>
                <div class="admin-role">Administrator</div>
                <a href="edit_user.php?id=<?php echo $admin_id; ?>">Edit Profile</a>
                <a href="logout.php">Logout</a>
            </div>
        </div>
        <div class="admin-avatar" onclick="openProfileModal()" title="Click to view profile">
            <?php if ($profile_image): ?>
                <img src="<?php echo $profile_image; ?>?v=<?php echo time(); ?>" alt="Admin Profile">
            <?php else: ?>
                <?php echo strtoupper(substr($admin_data['username'], 0, 1)); ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<div id="profileModal" class="modal" onclick="closeProfileModal()">
    <div class="modal-content" onclick="event.stopPropagation()">
        <span class="close-modal" onclick="closeProfileModal()">&times;</span>
        <?php if ($profile_image): ?>
            <img src="<?php echo $profile_image; ?>?v=<?php echo time(); ?>" alt="Admin Profile Picture">
        <?php else: ?>
            <div class="modal-avatar-placeholder">
                <div><?php echo strtoupper(substr($admin_data['username'], 0, 1)); ?></div>
            </div>
        <?php endif; ?>
        <div class="modal-profile-info">
            <h3><?php echo htmlspecialchars($admin_data['username']); ?></h3>
            <p><strong>Role:</strong> Administrator</p>
            <p><strong>Contact:</strong> <?php echo htmlspecialchars($admin_data['contact_number'] ?? 'Not set'); ?></p>
            <div class="modal-buttons">
                <button onclick="triggerImageUpload()" class="modal-btn">📷 Edit Image</button>
                <a href="edit_user.php?id=<?php echo $admin_id; ?>" class="modal-btn modal-btn-secondary">Edit Profile</a>
            </div>
        </div>
    </div>
</div>

<form id="imageUploadForm" method="POST" enctype="multipart/form-data" style="display: none;">
    <input type="file" id="profileImageInput" name="profile_image" accept="image/*" onchange="submitImageForm()">
</form>

<script>
function openProfileModal() {
    document.getElementById('profileModal').style.display = 'flex';
}

function closeProfileModal() {
    document.getElementById('profileModal').style.display = 'none';
}

function triggerImageUpload() {
    document.getElementById('profileImageInput').click();
}

function submitImageForm() {
    const fileInput = document.getElementById('profileImageInput');
    if (fileInput.files.length > 0) {
        if (fileInput.files[0].size > 5000000) {
            alert('File is too large. Maximum size is 5MB.');
            fileInput.value = '';
            return;
        }
        const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
        if (!allowedTypes.includes(fileInput.files[0].type)) {
            alert('Only JPG, JPEG, PNG & GIF files are allowed.');
            fileInput.value = '';
            return;
        }
        document.getElementById('imageUploadForm').submit();
    }
}

document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeProfileModal();
    }
});
</script>

<div class="container">
    <?php 
    if (isset($_GET['success_image_updated'])) {
        echo '<p class="message-success">Profile image updated successfully!</p>';
    }
    if (isset($_GET['success_product_deleted'])) {
        echo '<p class="message-success">Product deleted successfully!</p>';
    }
    if (isset($_GET['success_product_added'])) {
        echo '<p class="message-success">Product "' . htmlspecialchars($_GET['success_product_added']) . '" added successfully!</p>';
    }
    if (isset($_GET['success_product_updated'])) {
        echo '<p class="message-success">Product "' . htmlspecialchars($_GET['success_product_updated']) . '" updated successfully!</p>';
    }
    if (isset($_GET['success_user_deleted'])) {
        echo '<p class="message-success">User deleted successfully!</p>';
    }
    if (isset($_GET['success_barista_added'])) {
        echo '<p class="message-success">Barista "' . htmlspecialchars($_GET['success_barista_added']) . '" added successfully!</p>';
    }
    if (isset($_GET['success_customer_added'])) {
        echo '<p class="message-success">Customer "' . htmlspecialchars($_GET['success_customer_added']) . '" added successfully!</p>';
    }
    if (isset($_GET['error'])) {
        echo '<p class="message-error">' . htmlspecialchars($_GET['error']) . '</p>';
    }
    ?>

    <h2>Quick Metrics Overview</h2>
    <div class="metrics-card-container">
        <div class="metric-card">
            <h4>Total Products</h4>
            <p><?php echo number_format($products_count); ?></p>
        </div>
        <div class="metric-card">
            <h4>Barista Staff</h4>
            <p><?php echo number_format($barista_count); ?></p>
        </div>
        <div class="metric-card">
            <h4>Customer Accounts</h4>
            <p><?php echo number_format($customer_count); ?></p>
        </div>
        <div class="metric-card">
            <h4>Total POS Sales</h4>
            <p>₱ <?php echo number_format($pos_sales, 2); ?></p>
        </div>
        <div class="metric-card">
            <h4>Total Online Sales</h4>
            <p>₱ <?php echo number_format($online_sales, 2); ?></p>
        </div>
    </div>
    
    <h2>Sales Performance & Best Sellers</h2>
    <div class="chart-container">
        
        <div class="chart-box">
            <h4>Last 6 Months Total Sales (POS + Online)</h4>
            <canvas id="monthlySalesChart"></canvas>
        </div>

        <div class="chart-box">
            <h4>Top 5 Best Selling Drinks (Quantity)</h4>
            <canvas id="bestSellerChart"></canvas>
        </div>

    </div>
    
    <h2>Sales and Performance</h2>
    <p>
        <a href="admin_sales_report.php" style="font-weight: bold; color: #008080; text-decoration: none; margin-right: 20px;">
            📈 View Detailed Sales, Barista Performance, and Revenue Charts
        </a>
        <a href="admin_inquiry_inbox.php" style="font-weight: bold; color: #cc0000; text-decoration: none;">
            📧 Customer Inquiries 
            <?php if ($unread_inquiry_count > 0): ?>
                <span style="background-color: #cc0000; color: white; padding: 2px 6px; border-radius: 12px; font-size: 0.8em; margin-left: 5px;">
                    <?php echo $unread_inquiry_count; ?> New
                </span>
            <?php endif; ?>
        </a>
    </p>
    <hr>
    
    <div class="dashboard-section">
        <h3>👨‍🍳 Barista Staff Management <a href="add_barista.php" style="font-size: 0.7em;">[Add New Barista]</a></h3>
        <table>
            <tr>
                <th>ID</th>
                <th>Username</th>
                <th>Contact Number</th>
                <th>Role</th>
                <th>Action</th>
            </tr>
            <?php 
            if (mysqli_num_rows($result_baristas) > 0) {
                while($barista = mysqli_fetch_assoc($result_baristas)): 
            ?>
            <tr>
                <td><?php echo $barista['id']; ?></td>
                <td><?php echo htmlspecialchars($barista['username']); ?></td>
                <td><?php echo htmlspecialchars($barista['contact_number'] ?? 'N/A'); ?></td>
                <td><?php echo htmlspecialchars($barista['role']); ?></td>
                <td>
                    <a href="edit_user.php?id=<?php echo $barista['id']; ?>">Edit</a> |
                    <a href="delete_user.php?id=<?php echo $barista['id']; ?>" onclick="return confirm('Are you sure you want to delete this barista account?');">Delete</a>
                </td>
            </tr>
            <?php 
                endwhile;
            } else {
                echo '<tr><td colspan="5" style="text-align: center;">No barista accounts found.</td></tr>';
            }
            ?>
        </table>
        
        <?php if ($barista_count > $limit): ?>
            <div class="view-all-container">
                <?php if ($show_all_baristas): ?>
                    <a href="admin_dashboard.php" class="show-less-btn">← Show Less (Top 10)</a>
                <?php else: ?>
                    <a href="admin_dashboard.php?show_all_baristas=1" class="view-all-btn">View All Baristas (<?php echo $barista_count; ?>) →</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="dashboard-section">
        <h3>👤 Customer Account Management </h3>
        <table>
            <tr>
                <th>ID</th>
                <th>Username</th>
                <th>Contact Number</th>
                <th>Role</th>
                <th>Action</th>
            </tr>
            <?php 
            if (mysqli_num_rows($result_customers) > 0) {
                while($customer = mysqli_fetch_assoc($result_customers)): 
            ?>
            <tr>
                <td><?php echo $customer['id']; ?></td>
                <td><?php echo htmlspecialchars($customer['username']); ?></td>
                <td><?php echo htmlspecialchars($customer['contact_number'] ?? 'N/A'); ?></td>
                <td><?php echo htmlspecialchars($customer['role']); ?></td>
                <td>
                    
                    <a href="delete_user.php?id=<?php echo $customer['id']; ?>" onclick="return confirm('Are you sure you want to delete this customer account?');">Delete</a>
                </td>
            </tr>
            <?php 
                endwhile;
            } else {
                echo '<tr><td colspan="5" style="text-align: center;">No customer accounts found.</td></tr>';
            }
            ?>
        </table>
        
        <?php if ($customer_count > $limit): ?>
            <div class="view-all-container">
                <?php if ($show_all_customers): ?>
                    <a href="admin_dashboard.php" class="show-less-btn">← Show Less (Top 10)</a>
                <?php else: ?>
                    <a href="admin_dashboard.php?show_all_customers=1" class="view-all-btn">View All Customers (<?php echo $customer_count; ?>) →</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="dashboard-section">
        <h3>☕ Product Management <a href="add_product.php" style="font-size: 0.7em;">[Add New Product]</a>
            <a href="manage_subcategories.php" class="subcategory-link">[Manage Drink Subcategories]</a>
        </h3>
        
        <form action="" method="get" class="product-search-form">
            <input type="text" name="product_search" placeholder="Search by Name or ID" value="<?php echo htmlspecialchars($search_term); ?>">
            <input type="submit" value="Search">
            <?php if (!empty($search_term)): ?>
                <a href="admin_dashboard.php" style="margin-left: 10px;">Clear Filter</a>
            <?php endif; ?>
        </form>
        
        <table>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Price</th>
                <th>Category</th>
                <th>Subcategory</th> 
                <th>Action</th>
            </tr>
            <?php 
            if (mysqli_num_rows($result_products) > 0) {
                while($product = mysqli_fetch_assoc($result_products)): 
            ?>
            <tr>
                <td><?php echo $product['id']; ?></td>
                <td><?php echo htmlspecialchars($product['name']); ?></td>
                <td>₱ <?php echo number_format($product['price'], 2); ?></td>
                <td><?php echo htmlspecialchars(ucwords($product['category'] ?? 'N/A')); ?></td> 
                <td><?php echo htmlspecialchars(ucwords($product['subcategory'] ?? 'N/A')); ?></td> 
                <td>
                    <a href="edit_product.php?id=<?php echo $product['id']; ?>">Edit</a> |
                    <a href="delete_product.php?id=<?php echo $product['id']; ?>" onclick="return confirm('Are you sure you want to delete this product?');">Delete</a>
                </td>
            </tr>
            <?php 
                endwhile;
            } else {
                echo '<tr><td colspan="6" style="text-align: center;">No products found.</td></tr>';
            }
            ?>
        </table>

        <?php if ($products_count > $limit && empty($search_term)): ?>
            <div class="view-all-container">
                <?php if ($show_all_products): ?>
                    <a href="admin_dashboard.php" class="show-less-btn">← Show Less (Top 10)</a>
                <?php else: ?>
                    <a href="admin_dashboard.php?show_all_products=1" class="view-all-btn">View All Products (<?php echo $products_count; ?>) →</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php 
        if (isset($stmt_products) && $stmt_products) {
            mysqli_stmt_close($stmt_products);
        }
        ?>
    </div>
</div>

<script>
// PHP data arrays transferred to JavaScript (RESTORED)
const monthlySalesLabels = <?php echo json_encode(array_keys($monthly_sales_data)); ?>;
const monthlySalesData = <?php echo json_encode(array_values($monthly_sales_data)); ?>;
const bestSellerLabels = <?php echo json_encode($best_seller_labels); ?>;
const bestSellerData = <?php echo json_encode(array_map('floatval', $best_seller_data)); ?>; 

// --- Chart Rendering ---
document.addEventListener('DOMContentLoaded', function() {
    
    // 1. Monthly Sales Chart
    const salesCtx = document.getElementById('monthlySalesChart').getContext('2d');
    new Chart(salesCtx, {
        type: 'line', 
        data: {
            labels: monthlySalesLabels,
            datasets: [{
                label: 'Total Sales (₱)',
                data: monthlySalesData,
                backgroundColor: 'rgba(32, 99, 155, 0.5)', 
                borderColor: 'rgba(23, 63, 95, 1)',
                borderWidth: 2,
                tension: 0.3,
                fill: true
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Revenue (PHP)'
                    }
                }
            },
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            let label = context.dataset.label || '';
                            if (label) {
                                label += ': ';
                            }
                            if (context.parsed.y !== null) {
                                label += '₱ ' + context.parsed.y.toLocaleString('en-US', { minimumFractionDigits: 2 });
                            }
                            return label;
                        }
                    }
                }
            }
        }
    });

    // 2. Best Seller Chart (Bar)
    const bestSellerCtx = document.getElementById('bestSellerChart').getContext('2d');
    new Chart(bestSellerCtx, {
        type: 'bar', 
        data: {
            labels: bestSellerLabels,
            datasets: [{
                label: 'Units Sold',
                data: bestSellerData,
                backgroundColor: [
                    'rgba(139, 69, 19, 0.8)', 
                    'rgba(189, 126, 84, 0.8)',
                    'rgba(215, 204, 200, 0.8)',
                    'rgba(150, 110, 80, 0.8)',
                    'rgba(110, 70, 50, 0.8)'
                ],
                borderColor: '#4E342E',
                borderWidth: 1
            }]
        },
        options: {
            indexAxis: 'y', 
            responsive: true,
            scales: {
                x: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Total Quantity Sold'
                    }
                }
            },
            plugins: {
                legend: {
                    display: false
                }
            }
        }
    });
});


// (Omitted JavaScript functions from previous file)
function openProfileModal() {
    document.getElementById('profileModal').style.display = 'flex';
}

function closeProfileModal() {
    document.getElementById('profileModal').style.display = 'none';
}

function triggerImageUpload() {
    document.getElementById('profileImageInput').click();
}

function submitImageForm() {
    const fileInput = document.getElementById('profileImageInput');
    if (fileInput.files.length > 0) {
        if (fileInput.files[0].size > 5000000) {
            alert('File is too large. Maximum size is 5MB.');
            fileInput.value = '';
            return;
        }
        const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
        if (!allowedTypes.includes(fileInput.files[0].type)) {
            alert('Only JPG, JPEG, PNG & GIF files are allowed.');
            fileInput.value = '';
            return;
        }
        document.getElementById('imageUploadForm').submit();
    }
}

document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeProfileModal();
    }
});
</script>
</body>
</html>