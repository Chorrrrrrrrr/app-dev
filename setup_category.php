<?php
session_start();
include 'db.php';

// Check if user is admin
if (!isset($_SESSION['loggedin']) || $_SESSION['role'] != 'admin') {
    die("Access denied. Admin only.");
}

$messages = [];
$errors = [];

// Check if category column exists
$check_column = "SHOW COLUMNS FROM products LIKE 'category'";
$result = mysqli_query($conn, $check_column);
$column_exists = (mysqli_num_rows($result) > 0);

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_category_column'])) {
    
    if (!$column_exists) {
        // Add category column
        $alter_query = "ALTER TABLE products ADD COLUMN category VARCHAR(50) DEFAULT 'drink'";
        if (mysqli_query($conn, $alter_query)) {
            $messages[] = "✓ Category column added successfully!";
            $column_exists = true;
        } else {
            $errors[] = "Error adding category column: " . mysqli_error($conn);
        }
    } else {
        $messages[] = "Category column already exists.";
    }
    
    // Update existing products with default category
    if ($column_exists) {
        $update_query = "UPDATE products SET category = 'drink' WHERE category IS NULL OR category = ''";
        if (mysqli_query($conn, $update_query)) {
            $messages[] = "✓ All existing products set to 'drink' category.";
        }
    }
}

// Fetch all products to show current status
$products_query = "SELECT * FROM products ORDER BY id";
$products_result = mysqli_query($conn, $products_query);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Category Setup</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f4f7f6;
            margin: 0;
            padding: 20px;
        }
        
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }
        
        h2 {
            color: #173f5f;
            border-bottom: 3px solid #e0e0e0;
            padding-bottom: 10px;
        }
        
        .status-box {
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
            font-weight: bold;
        }
        
        .status-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .status-warning {
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        
        .status-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .message {
            padding: 10px;
            margin: 10px 0;
            border-radius: 5px;
            background-color: #d4edda;
            color: #155724;
        }
        
        .error {
            padding: 10px;
            margin: 10px 0;
            border-radius: 5px;
            background-color: #f8d7da;
            color: #721c24;
        }
        
        button, input[type="submit"] {
            background-color: #20639b;
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
            margin: 10px 5px 10px 0;
        }
        
        button:hover, input[type="submit"]:hover {
            background-color: #173f5f;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        table th, table td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        table th {
            background-color: #e6f0fa;
            color: #173f5f;
            font-weight: bold;
        }
        
        .badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .badge-drink {
            background-color: #3498db;
            color: white;
        }
        
        .badge-snack {
            background-color: #e67e22;
            color: white;
        }
        
        .badge-none {
            background-color: #95a5a6;
            color: white;
        }
        
        a {
            color: #20639b;
            text-decoration: none;
            font-weight: bold;
        }
        
        a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
<div class="container">
    <h2>🛠️ Category Setup Tool</h2>
    
    <p><a href="admin_dashboard.php">← Back to Admin Dashboard</a></p>
    
    <?php foreach ($messages as $msg): ?>
        <div class="message"><?php echo $msg; ?></div>
    <?php endforeach; ?>
    
    <?php foreach ($errors as $err): ?>
        <div class="error"><?php echo $err; ?></div>
    <?php endforeach; ?>
    
    <div class="status-box <?php echo $column_exists ? 'status-success' : 'status-warning'; ?>">
        <?php if ($column_exists): ?>
            ✓ Category column EXISTS in products table
        <?php else: ?>
            ⚠ Category column DOES NOT exist in products table
        <?php endif; ?>
    </div>
    
    <?php if (!$column_exists): ?>
        <form method="post">
            <p>Click the button below to add the category column to your products table and set all existing products to the 'drink' category by default.</p>
            <input type="submit" name="add_category_column" value="Add Category Column Now">
        </form>
    <?php else: ?>
        <div style="background-color: #e6f0fa; padding: 15px; border-radius: 8px; margin: 20px 0;">
            <h3 style="margin-top: 0;">✓ Setup Complete!</h3>
            <p>The category column is ready. You can now:</p>
            <ul>
                <li>Add new products with categories using <a href="add_product.php">Add Product</a></li>
                <li>Edit existing products to change their category using the Edit links below</li>
                <li>View categorized products on the <a href="customer_dashboard.php">Customer Dashboard</a></li>
            </ul>
        </div>
    <?php endif; ?>
    
    <h3>Current Products</h3>
    <table>
        <tr>
            <th>ID</th>
            <th>Name</th>
            <th>Price</th>
            <th>Category</th>
            <th>Action</th>
        </tr>
        <?php while ($product = mysqli_fetch_assoc($products_result)): ?>
        <tr>
            <td><?php echo $product['id']; ?></td>
            <td><?php echo htmlspecialchars($product['name']); ?></td>
            <td>₱ <?php echo number_format($product['price'], 2); ?></td>
            <td>
                <?php 
                $category = isset($product['category']) ? $product['category'] : '';
                if ($category == 'drink') {
                    echo '<span class="badge badge-drink">☕ DRINK</span>';
                } elseif ($category == 'snack') {
                    echo '<span class="badge badge-snack">🍪 SNACK</span>';
                } else {
                    echo '<span class="badge badge-none">Not Set</span>';
                }
                ?>
            </td>
            <td>
                <a href="edit_product.php?id=<?php echo $product['id']; ?>">Edit Category</a>
            </td>
        </tr>
        <?php endwhile; ?>
    </table>
    
</div>
</body>
</html>